<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once( dirname(__FILE__).'/icwp-basedb-processor.php' );

if ( !class_exists('ICWP_LoginProtectProcessor_V1') ):

class ICWP_LoginProtectProcessor_V1 extends ICWP_BaseDbProcessor_WPSF {
	
	const Slug = 'login_protect';
	const TableName = 'login_auth';
	const AuthActiveCookie = 'wpsf_auth';

	/**
	 * @var string
	 */
	static protected $sModeFile_LoginThrottled;
	
	/**
	 * The number of seconds between each authenticated login
	 * @var integer
	 */
	protected $m_nRequiredLoginInterval;
	
	protected $m_nLastLoginTime;
	protected $m_sSecretKey;
	
	protected $m_sGaspKey;
	
	/**
	 * Flag as to whether Two Factor Authentication will be by-pass when sending the verification
	 * email fails.
	 * 
	 * @var boolean
	 */
	protected $m_fAllowTwoFactorByPass;

	public function __construct( $insOptionPrefix = '' ) {
		parent::__construct( $this->constructStorageKey( $insOptionPrefix, self::Slug ), self::TableName );
		$this->m_sGaspKey = uniqid();
		$this->updateLastLoginThrottleTime( time() );
		$this->createTable();
		$this->reset();
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		self::$sModeFile_LoginThrottled = dirname( __FILE__ ).'/../mode.login_throttled';
		$this->genSecretKey();
	}
	
	/**
	 * Set the secret key by which authentication is validated.
	 * 
	 * @param string $insSecretKey
	 */
	public function genSecretKey( $infForceUpdate = false ) {
		if ( empty( $this->m_sSecretKey ) || $infForceUpdate ) {
			$this->m_sSecretKey = md5( mt_rand() );
		}
	}
	
	/**
	 * Set the secret key by which authentication is validated.
	 * 
	 * @param string $insSecretKey
	 */
	public function setSecretKey( $insSecretKey = '' ) {
		if ( !empty( $insSecretKey ) ) {
			$this->genSecretKey();
		}
		else {
			$this->m_sSecretKey = $insSecretKey;
		}
	}
	
	/**
	 *
	 * @param array $inoOptions
	 */
	public function setOptions( &$inaOptions ) {
		parent::setOptions( $inaOptions );
		$this->setLogging();
		$this->setLoginCooldownInterval();
		$this->setTwoFactorByPassOnFail();
	}
	
	/**
	 * @return boolean
	 */
	public function getNeedsEmailHandler() {
		return $this->getIsTwoFactorAuthOn();
	}

	/**
	 * @param string $insType		can be either 'ip' or 'cookie'. If empty, both are checked looking for either.
	 * @return bool
	 */
	public function getIsTwoFactorAuthOn( $insType = '' ) {

		$fIp = isset( $this->m_aOptions['enable_two_factor_auth_by_ip'] ) && ($this->m_aOptions['enable_two_factor_auth_by_ip'] == 'Y');
		$fCookie = isset( $this->m_aOptions['enable_two_factor_auth_by_cookie'] ) && ($this->m_aOptions['enable_two_factor_auth_by_cookie'] == 'Y');

		switch( $insType ) {
			case 'ip':
				return $fIp;
				break;
			case 'cookie':
				return $fCookie;
				break;
			default:
				return $fIp || $fCookie;
				break;
		}
	}
	
	public function setLogging() {
		parent::setLogging( $this->m_aOptions[ 'enable_login_protect_log' ] == 'Y' );
	}

	/**
	 */
	public function run() {
		parent::run();
		
		$aWhitelist = $this->m_aOptions['ips_whitelist'];
		if ( !empty( $aWhitelist ) && $this->isIpOnlist( $aWhitelist, self::GetVisitorIpAddress() ) ) {
			return true;
		}
		
		// Add GASP checking to the login form.
		if ( $this->m_aOptions['enable_login_gasp_check'] == 'Y' ) {
			add_action( 'login_form',			array( $this, 'printGaspLoginCheck_Action' ) );
			add_filter( 'login_form_middle',	array( $this, 'printGaspLoginCheck_Filter' ) );
			add_filter( 'authenticate',			array( $this, 'checkLoginForGasp_Filter' ), 9, 3);
		}

		if ( $this->m_aOptions['login_limit_interval'] > 0 ) {
			// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
			add_filter( 'authenticate', array( $this, 'checkLoginInterval_Filter' ), 10, 3);
		}

		if ( $this->getIsTwoFactorAuthOn() ) {
			// User has clicked a link in their email to validate their IP address for login.
			if ( isset( $_GET['wpsf-action'] ) && $_GET['wpsf-action'] == 'linkauth' ) {
				$this->validateUserAuthLink();
			}

			// If their click was successful we give them a lovely message
			if ( isset( $_GET['wpsfipverified']) ) {
				add_filter( 'login_message', array( $this, 'displayVerifiedUserMessage_Filter' ) );
			}


			// Check the current logged-in user every page load.
			add_action( 'init', array( $this, 'checkCurrentUserAuth_Action' ) );

			// At this stage (30,3) WordPress has already authenticated the user. So if the login
			// is valid, the filter will have a valid WP_User object passed to it.
			add_filter( 'authenticate', array( $this, 'checkUserAuthLogin_Filter' ), 30, 3);
		}
	}
	
	public function printGaspLoginCheck_Action() {
		echo $this->getGaspLoginHtml();
	}
	
	public function printGaspLoginCheck_Filter() {
		return $this->getGaspLoginHtml();
	}

	public function checkLoginForGasp_Filter( $inoUser, $insUsername, $insPassword ) {
	
		if ( empty( $insUsername ) || is_wp_error( $inoUser ) ) {
			return $inoUser;
		}
		if ( $this->doGaspChecks( $insUsername ) ) {
			return $inoUser;
		}
		return null;
	}
	
	/**
	 * Checks whether the current user that is logged-in is authenticated by IP address.
	 * 
	 * If the user is not found to be valid, they're logged out.
	 * 
	 * Should be hooked to 'init' so we have is_user_logged_in()
	 */
	public function checkCurrentUserAuth_Action() {
		if ( is_user_logged_in() ) {
			$this->verifyCurrentUser();
		}
	}
	
	public function displayVerifiedUserMessage_Filter( $insMessage ) {
		$sStyles = 'background-color: #FAFFE8; border: 1px solid #DDDDDD; margin: 8px 0 10px 8px; padding: 16px;';
		$insMessage .= '<h3 style="'.$sStyles.'">'._wpsf__('You have successfully verified your identity - you may now login').'</h3>';
		return $insMessage;
	}
	
	/**
	 * Should return false when logging is disabled.
	 *
	 * @return false|array	- false when logging is disabled, array with log data otherwise
	 * @see ICWP_WPSF_BaseProcessor::getLogData()
	 */
	public function flushLogData() {
	
		if ( !$this->m_fLoggingEnabled || empty( $this->m_aLogMessages ) ) {
			return false;
		}

		$this->m_aLog = array(
			'category'			=> self::LOG_CATEGORY_LOGINPROTECT,
			'messages'			=> serialize( $this->m_aLogMessages )
		);
		$this->resetLog();
		return $this->m_aLog;
	}
	
	/**
	 * Checks the link details to ensure all is valid before authorizing the user.
	 *
	 * @return boolean
	 */
	public function validateUserAuthLink() {
		// wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid
	
		if ( !isset( $_GET['wpsfkey'] ) || $_GET['wpsfkey'] !== $this->m_sSecretKey ) {
			return false;
		}
		if ( empty( $_GET['username'] ) || empty( $_GET['uniqueid'] ) ) {
			return false;
		}
	
		$aWhere = array(
			'unique_id'		=> $_GET['uniqueid'],
			'wp_username'	=> $_GET['username']
		);
	
		if ( $this->loginAuthMakeActive( $aWhere ) ) {
			$this->redirectToLogin( '?wpsfipverified=1' );
		}
		else {
			header( "Location: ".home_url() );
		}
	}
	
	public function redirectToLogin( $sParams = '' ) {
		header( "Location: ".site_url().'/wp-login.php'.$sParams );
	}
	
	// WordPress Hooks and Filters:

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * 
	 * @param null|WP_User|WP_Error $inoUser
	 * @param string $insUsername
	 * @param string $insPassword
	 * @return unknown|WP_Error
	 */
	public function checkLoginInterval_Filter( $inoUser, $insUsername, $insPassword ) {
		// No login attempt was made.
		if ( empty( $insUsername ) ) {
			return $inoUser;
		}

		// Is there an interval set?
		$this->setLoginCooldownInterval();
		$nRequiredLoginInterval = $this->m_nRequiredLoginInterval;
		if ( $nRequiredLoginInterval === false || $nRequiredLoginInterval == 0 ) {
			return $inoUser;
		}
		
		// Get the last login time (and update it also for the next time)
		$sNow = time();
		$this->m_nLastLoginTime = $this->getLastLoginTime();

		if ( empty( $this->m_nLastLoginTime ) || $this->m_nLastLoginTime < 0 ) {
			$this->updateLastLoginThrottleTime( $sNow );
		}
		
		// If we're outside the interval, let the login process proceed as per normal and
		// update our last login time.
		$nLoginInterval = $sNow - $this->m_nLastLoginTime;
		if ( $nLoginInterval > $nRequiredLoginInterval ) {
			$this->updateLastLoginThrottleTime( $sNow );
			return $inoUser;
		}

		// At this point someone has attempted to login within the previous login wait interval
		// So we remove WordPress's authentication filter and our own user check authentication
		// And finally return a WP_Error which will be reflected back to the user.
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20, 3 );  // wp-includes/user.php
		remove_filter( 'authenticate', array( $this, 'checkUserAuthLogin_Filter' ), 30, 3);
	
		$sErrorString = sprintf( _wpsf__( "Login Cooldown in effect. You must wait %s seconds before attempting to login again." ), ($nRequiredLoginInterval - $nLoginInterval ) );
		$oError = new WP_Error( 'wpsf_logininterval', $sErrorString );
		return $oError;
	}
	
	protected function getLastLoginTime() {
		$oWpFs = ICWP_WpFilesystem_V1::GetInstance();
		// Check that there is a login throttle file. If it exists and its modified time is greater than the 
		// current $this->m_nLastLoginTime it suggests another process has touched the file and updated it
		// concurrently. So, we update our $this->m_nEmailThrottleTime accordingly.
		if ( $oWpFs->fileAction( 'file_exists', self::$sModeFile_LoginThrottled ) ) {
			$nModifiedTime = filemtime( self::$sModeFile_LoginThrottled );
			if ( $nModifiedTime > $this->m_nLastLoginTime ) {
				$this->m_nLastLoginTime = $nModifiedTime;
			}
		}
		else { }
		return $this->m_nLastLoginTime;
	}
	
	public function updateLastLoginThrottleTime( $innLastLoginTime ) {
		$oWpFs = ICWP_WpFilesystem_V1::GetInstance();
		$this->m_nLastLoginTime = $innLastLoginTime;
		$oWpFs->fileAction( 'touch', array(self::$sModeFile_LoginThrottled, $innLastLoginTime) );
		$this->setNeedSave();
	}

	/**
	 * If $inoUser is a valid WP_User object, then the user logged in correctly.
	 *
	 * The flow is as follows:
	 * 0. If username is empty, there was no login attempt.
	 * 1. First we determine whether the user's login credentials were valid according to WordPress ($fUserLoginSuccess)
	 * 2. Then we ask our 2-factor processor whether the current IP address + username combination is authenticated.
	 * 		a) if yes, we return the WP_User object and login proceeds as per usual.
	 * 		b) if no, we return null, which will send the message back to the user that the login details were invalid.
	 * 3. If however the user's IP address + username combination is not authenticated, we react differently. We do not want
	 * 	to give away whether a login was successful, or even the login username details exist. So:
	 * 		a) if the login was a success we add a pending record to the authentication DB for this username+IP address combination and send the appropriate verification email
	 * 		b) then, we give back a message saying that if the login was successful, they would have received a verification email. In this way we give nothing away.
	 * 		c) note at this stage, if the username was empty, we give back nothing (this happens when wp-login.php is loaded as normal.
	 *
	 * @param WP_User|string $inoUser	- the docs say the first parameter a string, WP actually gives a WP_User object (or null)
	 * @param string $insUsername
	 * @param string $insPassword
	 * @return WP_Error|WP_User|null	- WP_User when the login success AND the IP is authenticated. null when login not successful but IP is valid. WP_Error otherwise.
	 */
	public function checkUserAuthLogin_Filter( $inoUser, $insUsername, $insPassword ) {
	
		if ( empty( $insUsername ) ) {
			return $inoUser;
		}
	
		$fUserLoginSuccess = is_object( $inoUser ) && ( $inoUser instanceof WP_User );

		if ( is_wp_error( $inoUser ) ) {
			$aCodes = $inoUser->get_error_codes();
			if ( in_array( 'wpsf_logininterval', $aCodes ) ) {
				return $inoUser;
			}
		}
		else if ( $fUserLoginSuccess ) {
			
			$aData = array( 'wp_username' => $insUsername );
			if ( $this->isUserVerified( $aData ) ) {
				return $inoUser;
			}
			else {
				// Create a new 2-factor auth pending entry
				$aNewAuthData = $this->loginAuthAddPending( array( 'wp_username' => $inoUser->user_login ) );
	
				// Now send email with authentication link for user.
				if ( is_array( $aNewAuthData ) ) {
					$fEmailSuccess = $this->sendEmailTwoFactorVerify( $inoUser, $aNewAuthData['ip'], $aNewAuthData['unique_id'] );
					
					// Failure to send email - log them in.
					if ( !$fEmailSuccess && $this->getTwoFactorByPassOnFail() ) {
						$this->loginAuthMakeActive( $aNewAuthData );
						return $inoUser;
					}
				}
			}
		}
		
		$sErrorString = "Login is protected by 2-factor authentication. If your login details were correct, you would have received an email to verify this IP address.";
		return new WP_Error( 'wpsf_loginauth', $sErrorString );
	}
	
	public function getGaspLoginHtml() {
	
		$sLabel = _wpsf__("I'm a human.");
		$sAlert = _wpsf__("Please check the box to show us you're a human.");
	
		$sUniqElem = 'icwp_wpsf_login_p'.uniqid();
		
		$sStyles = '
			<style>
				#'.$sUniqElem.' {
					clear:both;
					border: 1px solid #888;
					padding: 6px 8px 4px 10px;
					margin: 0 0px 12px !important;
					border-radius: 2px;
					background-color: #f9f9f9;
				}
				#'.$sUniqElem.' input {
					margin-right: 5px;
				}
			</style>
		';
	
		$sHtml =
			$sStyles.
			'<p id="'.$sUniqElem.'"></p>
			<script type="text/javascript">
				var icwp_wpsf_login_p		= document.getElementById("'.$sUniqElem.'");
				var icwp_wpsf_login_cb		= document.createElement("input");
				var icwp_wpsf_login_text	= document.createTextNode(" '.$sLabel.'");
				icwp_wpsf_login_cb.type		= "checkbox";
				icwp_wpsf_login_cb.id		= "'.$this->getGaspCheckboxName().'";
				icwp_wpsf_login_cb.name		= "'.$this->getGaspCheckboxName().'";
				icwp_wpsf_login_p.appendChild( icwp_wpsf_login_cb );
				icwp_wpsf_login_p.appendChild( icwp_wpsf_login_text );
				var frm = icwp_wpsf_login_cb.form;
				frm.onsubmit = icwp_wpsf_login_it;
				function icwp_wpsf_login_it(){
					if(icwp_wpsf_login_cb.checked != true){
						alert("'.$sAlert.'");
						return false;
					}
					return true;
				}
			</script>
			<noscript>'._wpsf__('You MUST enable Javascript to be able to login').'</noscript>
			<input type="hidden" id="icwp_wpsf_login_email" name="icwp_wpsf_login_email" value="" />
		';

		return $sHtml;
	}
	
	public function getGaspCheckboxName() {
		if ( empty( $this->m_sGaspKey ) ) {
			$this->m_sGaspKey = uniqid();
		}
		return "icwp_wpsf_$this->m_sGaspKey";
	}
	
	public function doGaspChecks( $insUsername ) {
		if ( !isset( $_POST[ $this->getGaspCheckboxName() ] ) ) {
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but GASP checkbox was not present. Bot Perhaps? IP Address: "%s".'), $insUsername, long2ip($this->m_nRequestIp) )
			);
			wp_die( "You must check that box to say you're not a bot." );
			return false;
		}
		else if ( isset( $_POST['icwp_wpsf_login_email'] ) && $_POST['icwp_wpsf_login_email'] !== '' ){
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but they were caught by the GASP honey pot. Bot Perhaps? IP Address: "%s".'), $insUsername, long2ip($this->m_nRequestIp) )
			);
			wp_die( _wpsf__('You appear to be a bot - terminating login attempt.') );
			return false;
		}
		return true;
	}
	
	public function setTwoFactorByPassOnFail() {
		$this->m_fAllowTwoFactorByPass = $this->m_aOptions[ 'enable_two_factor_bypass_on_email_fail' ] == 'Y';
	}
	
	public function getTwoFactorByPassOnFail() {
		if ( !isset( $this->m_fAllowTwoFactorByPass ) ) {
			$this->m_fAllowTwoFactorByPass = false;
		}
		return $this->m_fAllowTwoFactorByPass;
	}
	
	public function setLoginCooldownInterval() {
		$nInterval = intval( $this->m_aOptions[ 'login_limit_interval' ] );
		$this->m_nRequiredLoginInterval = ( $nInterval < 0 )? 0 : $nInterval;
	}
	
	/**
	 * @param array $inaData
	 * @return boolean
	 */
	public function loginAuthAddPending( $inaData ) {
		
		$aChecks = array( 'wp_username' );
		if ( !$this->validateParameters( $inaData, $aChecks) ) {
			return false;
		}
		
		$sNow = time();
		
		// First set any other pending entries for the given user to be deleted.
		$aOldData = array(
			'deleted_at'	=> $sNow,
			'expired_at'	=> $sNow,
		);
		$aOldWhere = array(
			'pending'		=> 1,
			'deleted_at'	=> 0,
			'wp_username'	=> $inaData[ 'wp_username' ]
		);
		$this->updateRowsFromTable( $aOldData, $aOldWhere );

		// Now add new pending entry
		$inaData[ 'unique_id' ]		= uniqid();
		$inaData[ 'ip_long' ]		= $this->m_nRequestIp;
		$inaData[ 'ip' ]			= long2ip( $this->m_nRequestIp );
		$inaData[ 'pending' ]		= 1;
		$inaData[ 'created_at' ]	= time();

		$mResult = $this->insertIntoTable( $inaData );
		if ( $mResult ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" created a pending Two-Factor Authentication for IP Address "%s".'), $inaData[ 'wp_username' ], $inaData[ 'ip' ] )
			);
			$mResult = $inaData;
		}
		return $mResult;
	}
	
	/**
	 * Given a unique Id and a corresponding WordPress username, will update the authentication table so that it is active (pending=0).
	 * 
	 * @param array $inaWhere - unique_id, wp_username
	 * @return boolean
	 */
	public function loginAuthMakeActive( $inaWhere ) {
		
		$aChecks = array( 'unique_id', 'wp_username' );
		if ( !$this->validateParameters( $inaWhere, $aChecks ) ) {
			return false;
		}
		
		$sNow = time();
		
		// First set any active, non-pending entries for the given user to be deleted.
		$sQuery = "
			UPDATE `%s`
			SET `deleted_at`	= '%s',
				`expired_at`	= '%s'
			WHERE
				`wp_username`		= '%s'
				AND `deleted_at`	= '0'
				AND `pending`		= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$sNow,
			$sNow,
			$inaWhere['wp_username']
		);
		$this->doSql( $sQuery );

		// Now activate the new one.

		// Set the necessary cookie
		$this->setAuthActiveCookie( $inaWhere['unique_id'] );

		// Updates the database
		$inaWhere['pending']	= 1;
		$inaWhere['deleted_at']	= 0;
		$mResult = $this->updateRowsFromTable( array( 'pending' => 0 ), $inaWhere );
		if ( $mResult ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" verified their identity using Two-Factor Authentication.'), $inaWhere[ 'wp_username' ] )
			);
		}
		return $mResult;
	}

	/**
	 * @param $insUniqueId
	 */
	public function setAuthActiveCookie( $insUniqueId ) {
		$nWeek = defined( 'WEEK_IN_SECONDS' )? WEEK_IN_SECONDS : 24*60*60;
		setcookie( self::AuthActiveCookie, $insUniqueId, time()+$nWeek, COOKIEPATH, COOKIE_DOMAIN, false );
	}
	
	/**
	 * Checks whether a given user is authenticated.
	 * 
	 * @param array $inaWhere
	 * @return boolean
	 */
	public function isUserVerified( $inaWhere ) {
		
		$aChecks = array( 'wp_username' );
		if ( !$this->validateParameters( $inaWhere, $aChecks ) ) {
			return false;
		}
		
		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`wp_username`		= '%s'
				AND `pending`		= '0'
				AND `deleted_at`	= '0'
				AND `expired_at`	= '0'
		";
//				AND `ip_long`		= '%s'
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$inaWhere['wp_username']
		);
//			$this->m_nRequestIp

		$mResult = $this->selectCustomFromTable( $sQuery );


		if ( is_array( $mResult ) && count( $mResult ) == 1 ) {
			// Now we test based on which types of 2-factor auth is enabled
			$fVerified = true;
			$aUserAuthData = $mResult[0];
			if ( $this->getIsTwoFactorAuthOn('ip') && ( $this->m_nRequestIp != $aUserAuthData['ip_long'] ) ) {
				$fVerified = false;
			}
			if ( $fVerified && $this->getIsTwoFactorAuthOn('cookie') && !$this->isAuthCookieValid($aUserAuthData['unique_id']) ) {
				$fVerified = false;
			}
			return $fVerified;
		}
		else {
			$this->logWarning(
				sprintf( _wpsf__('User "%s" was found to be un-verified at the given IP Address "%s"'), $inaWhere[ 'wp_username' ], long2ip( $this->m_nRequestIp ) )
			);
			return false;
		}
	}

	public function isAuthCookieValid( $insUniqueId ) {
		return isset( $_COOKIE[self::AuthActiveCookie] ) && $_COOKIE[self::AuthActiveCookie] == $insUniqueId;
	}
	
	public function verifyCurrentUser() {
		$oUser = wp_get_current_user();
		if ( is_object( $oUser ) && $oUser instanceof WP_User ) {
			
			$aData = array( 'wp_username' => $oUser->user_login );
			if ( !$this->isUserVerified( $aData ) ) {
				$this->logWarning(
					sprintf( _wpsf__('User "%s" was forcefully logged out as they are not verified.'), $oUser->user_login )
				);
				wp_logout();
				$this->redirectToLogin();
			}
		}
	}
	
	/**
	 * Given the necessary components, creates the 2-factor verification link for giving to the user.
	 * 
	 * @param string $insKey
	 * @param string $insUser
	 * @param string $insUniqueId
	 * @return string
	 */
	public function getTwoFactorVerifyLink( $insKey, $insUser, $insUniqueId ) {
		$sSiteUrl = home_url() . '?wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid=%s';
		$sAction = 'linkauth';
		return sprintf( $sSiteUrl, $insKey, $sAction, $insUser, $insUniqueId ); 
	}

	/**
	 * @param WP_User $inoUser
	 * @param string $insIpAddress
	 * @param string $insUniqueId
	 * @return boolean
	 */
	public function sendEmailTwoFactorVerify( WP_User $inoUser, $insIpAddress, $insUniqueId ) {
	
		$sEmail = $inoUser->user_email;
		$sAuthLink = $this->getTwoFactorVerifyLink( $this->m_sSecretKey, $inoUser->user_login, $insUniqueId );
		
		$aMessage = array(
			_wpsf__('You, or someone pretending to be you, just attempted to login into your WordPress site.'),
			_wpsf__('The IP Address / Cookie from which they tried to login is not currently verified.'),
			_wpsf__('To validate this user, click the following link and then attempt to login again.'),
			sprintf( _wpsf__('IP Address: %s'), $insIpAddress ),
			sprintf( _wpsf__('Authentication Link: %s'), $sAuthLink ),
		);
		$sEmailSubject = sprintf( _wpsf__('Two-Factor Login Verification for: %s'), home_url() );
		
		$fResult = $this->sendEmailTo( $sEmail, $sEmailSubject, $aMessage );
		if ( $fResult ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" was sent an email to verify their Identity using Two-Factor Login Auth for IP address "%s".'), $inoUser->user_login, $insIpAddress )
			);
		}
		else {
			$this->logCritical(
				sprintf( _wpsf__('Tried to send User "%s" email to verify their Identity using Two-Factor Login Auth for IP Address "%s", but email sending failed.'), $inoUser->user_login, $insIpAddress )
			);
		}
		return $fResult;
	}
	
	public function createTable() {

		// Set up login processor table
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`unique_id` varchar(20) NOT NULL DEFAULT '',
			`wp_username` varchar(255) NOT NULL DEFAULT '',
			`ip` varchar(20) NOT NULL DEFAULT '',
			`ip_long` bigint(20) NOT NULL DEFAULT '0',
			`pending` int(1) NOT NULL DEFAULT '0',
			`created_at` int(15) NOT NULL DEFAULT '0',
			`deleted_at` int(15) NOT NULL DEFAULT '0',
			`expired_at` int(15) NOT NULL DEFAULT '0',
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$sSqlTables = sprintf( $sSqlTables, $this->m_sTableName );
		$mResult = $this->doSql( $sSqlTables );
	}
	
	/**
	 * Assumes that unique_id AND wp_username have been set correctly in the data array (no checking done).
	 * 
	 * @param array $inaData
	 * @return array
	 */
	protected function getLoginAuthData( $inaData ) {

		$sQuery = "SELECT * FROM %s WHERE `unique_id` = `%s` AND `wp_username` = %s";
		$sQuery = sprintf( $sQuery, $this->m_sTableName, $inaData['unique_id'], $inaData['wp_username'] );
		return $this->selectRowFromTable( $sQuery );
	}

	/**
	 * This is hooked into a cron in the base class and overrides the parent method.
	 * 
	 * It'll delete everything older than 24hrs.
	 */
	public function cleanupDatabase() {
		$nTimeStamp = time() - DAY_IN_SECONDS;
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}

	/**
	 * @param $innTimeStamp
	 */
	protected function deleteAllRowsOlderThan( $innTimeStamp ) {
		$sQuery = "
			DELETE from `%s`
			WHERE
				`created_at`		< '%s'
				AND `pending`		= '1'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$innTimeStamp
		);
		$this->doSql( $sQuery );
	}

}

endif;

if ( !class_exists('ICWP_WPSF_LoginProtectProcessor') ):
	class ICWP_WPSF_LoginProtectProcessor extends ICWP_LoginProtectProcessor_V1 { }
endif;