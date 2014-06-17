<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
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

if ( !class_exists('ICWP_LoginProtectProcessor_V3') ):

class ICWP_LoginProtectProcessor_V3 extends ICWP_BaseDbProcessor_WPSF {
	
	const Slug = 'login_protect';
	const TableName = 'login_auth';
	const AuthActiveCookie = 'wpsf_auth';
	const YubikeyVerifyApiUrl = 'https://api.yubico.com/wsapi/2.0/verify?id=%s&otp=%s&nonce=%s';

	/**
	 * @var string
	 */
	static protected $sModeFile_LoginThrottled;
	
	/**
	 * The number of seconds between each authenticated login
	 * @var integer
	 */
	protected $m_nRequiredLoginInterval;

	/**
	 * @var integer
	 */
	protected $m_nLastLoginTime;
	/**
	 * @var string
	 */
	protected $m_sSecretKey;
	/**
	 * @var string
	 */
	protected $m_sGaspKey;
	/**
	 * @var string
	 */
	protected $nDaysToKeepLog = 1;
	
	/**
	 * Flag as to whether Two Factor Authentication will be by-pass when sending the verification
	 * email fails.
	 * 
	 * @var boolean
	 */
	protected $m_fAllowTwoFactorByPass;

	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::Slug, self::TableName );
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
	 * @param boolean $infForceUpdate
	 * @return string
	 */
	public function genSecretKey( $infForceUpdate = false ) {
		if ( empty( $this->m_sSecretKey ) || $infForceUpdate ) {
			$this->m_sSecretKey = md5( mt_rand() );
		}
		return $this->m_sSecretKey;
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
	 * @param array $inaOptions
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
	 * @param string $sType		can be either 'ip' or 'cookie'. If empty, both are checked looking for either.
	 * @return bool
	 */
	protected function getIsTwoFactorAuthOn( $sType = '' ) {

		$fIp = $this->getOption( 'enable_two_factor_auth_by_ip', 'N' ) == 'Y';
		$fCookie = $this->getOption( 'enable_two_factor_auth_by_cookie', 'N' ) == 'Y';

		switch( $sType ) {
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

	/**
	 * @param bool $fEnableLogging
	 */
	public function setLogging( $fEnableLogging = true ) {
		parent::setLogging( $this->getIsOption( 'enable_login_protect_log', 'Y' ) );
	}

	/**
	 */
	public function run() {
		parent::run();
		$this->loadDataProcessor();
//		$this->recreateTable();

		$sRequestMethod = ICWP_WPSF_DataProcessor::ArrayFetch( $_SERVER, 'REQUEST_METHOD' );
		$fIsPost = strtolower( empty($sRequestMethod)? '' : $sRequestMethod ) == 'post';

		$aWhitelist = $this->getOption( 'ips_whitelist', array() );
		if ( !empty( $aWhitelist ) && $this->isIpOnlist( $aWhitelist, self::GetVisitorIpAddress() ) ) {
			return true;
		}

		// check for remote posting before anything else.
		if ( $fIsPost && $this->getIsOption('enable_prevent_remote_post', 'Y') ) {
			add_filter( 'authenticate',			array( $this, 'checkRemotePostLogin_Filter' ), 9, 3);
		}

		// Add GASP checking to the login form.
		if ( $this->getIsOption('enable_login_gasp_check', 'Y') ) {
			add_action( 'login_form',				array( $this, 'printGaspLoginCheck_Action' ) );
			add_action( 'woocommerce_login_form',	array( $this, 'printGaspLoginCheck_Action' ) );
			add_filter( 'login_form_middle',		array( $this, 'printGaspLoginCheck_Filter' ) );
			add_filter( 'authenticate',				array( $this, 'checkLoginForGasp_Filter' ), 22, 3);
		}

		// Do GASP checking if it's a form submit.
		if ( $fIsPost && $this->getOption( 'login_limit_interval' ) > 0 ) {
			// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
			add_filter( 'authenticate', array( $this, 'checkLoginInterval_Filter' ), 10, 3);
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $fIsPost && $this->getOption('enable_yubikey') && $this->getIsYubikeyConfigReady() ) {
			add_filter( 'wp_authenticate_user', array( $this, 'checkYubikeyOtpAuth_Filter' ) );
			add_action( 'login_form',			array( $this, 'printYubikeyOtp_Action' ) );
		}

		if ( $this->getIsTwoFactorAuthOn() ) {

			// If their click was successful we give them a lovely message
			if ( ICWP_WPSF_DataProcessor::FetchGet( 'wpsfuserverified' ) ) {
				add_filter( 'login_message', array( $this, 'displayVerifiedUserMessage_Filter' ) );
			}

			// Check the current logged-in user every page load.
			add_action( 'init', array( $this, 'checkCurrentUserAuth_Action' ) );

			// At this stage (30,3) WordPress has already (20) authenticated the user. So if the login
			// is valid, the filter will have a valid WP_User object passed to it.
			add_filter( 'authenticate', array( $this, 'checkUserAuthLogin_Filter' ), 30, 3);
		}
	}

	/**
	 */
	public function printGaspLoginCheck_Action() {
		echo $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	public function printGaspLoginCheck_Filter() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @param $inoUser
	 * @param $insUsername
	 * @param $insPassword
	 * @return mixed
	 */
	public function checkRemotePostLogin_Filter( $inoUser, $insUsername, $insPassword ) {
		$this->loadDataProcessor();
		$sHttpRef = ICWP_WPSF_DataProcessor::ArrayFetch( $_SERVER, 'HTTP_REFERER' );
		$sHttpRef = is_null( $sHttpRef )? '' : $sHttpRef;
		if ( empty($sHttpRef) || ( strpos($sHttpRef, home_url()) !== 0 ) ) {
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but the HTTP REFERER was either empty or it was a remote login attempt. Bot Perhaps? HTTP REFERER: "%s".'), $insUsername, $sHttpRef )
			);
			$this->doStatIncrement( 'login.remotepost.fail' );
			wp_die(
				_wpsf__( 'Sorry, you must login directly from within the site.' )
					.'<br /><a href="http://icwp.io/4n" target="_blank">&rarr;'._wpsf__('More Info').'</a>'
			);
		}
		else {
			$this->doStatIncrement( 'login.remotepost.success' );
		}
		return $inoUser;
	}

	/**
	 * @param $inoUser
	 * @param $insUsername
	 * @param $insPassword
	 * @return WP_Error
	 */
	public function checkLoginForGasp_Filter( $inoUser, $insUsername, $insPassword ) {

		if ( empty( $insUsername ) || is_wp_error( $inoUser ) ) {
			return $inoUser;
		}
		if ( $this->doGaspChecks( $insUsername ) ) {
			return $inoUser;
		}
		//This doesn't actually ever get returned because we die() within doGaspChecks()
		return new WP_Error('wpsf_gaspfail', _wpsf__('G.A.S.P. Checking Failed.') );
	}

	/**
	 * Checks whether the current user that is logged-in is authenticated by IP address.
	 * 
	 * If the user is not found to be valid, they're logged out.
	 * 
	 * Should be hooked to 'init' so we have is_user_logged_in()
	 */
	public function checkCurrentUserAuth_Action() {

		// User has clicked a link in their email to validate their IP address for login.
		if ( ICWP_WPSF_DataProcessor::FetchGet( 'wpsf-action' ) == 'linkauth' ) {
			$this->validateUserAuthLink();
		}

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
	 */
	public function validateUserAuthLink() {
		$this->loadDataProcessor();
		// wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid

		if ( ICWP_WPSF_DataProcessor::FetchGet( 'wpsfkey' ) !== $this->m_sSecretKey ) {
			return false;
		}

		$sUsername = ICWP_WPSF_DataProcessor::FetchGet( 'username' );
		$sUniqueId = ICWP_WPSF_DataProcessor::FetchGet( 'uniqueid' );
		if ( empty( $sUsername ) || empty( $sUniqueId ) ) {
			return false;
		}
	
		$aWhere = array(
			'unique_id'		=> $sUniqueId,
			'wp_username'	=> $sUsername
		);

		$oWp = $this->loadWpFunctionsProcessor();
		if ( $this->doMakePendingLoginAuthActive( $aWhere ) ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" verified their identity using Two-Factor Authentication.'), $sUsername )
			);
			$this->setUserLoggedIn( $sUsername );
			$this->doStatIncrement( 'login.twofactor.verified' );
			$oWp->redirectToAdmin();
		}
		else {
			$oWp->redirectToHome();
		}
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
			$this->doStatIncrement( 'login.cooldown.success' );
			return $inoUser;
		}

		// At this point someone has attempted to login within the previous login wait interval
		// So we remove WordPress's authentication filter and our own user check authentication
		// And finally return a WP_Error which will be reflected back to the user.
		$this->doStatIncrement( 'login.cooldown.fail' );
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20, 3 );  // wp-includes/user.php
		remove_filter( 'authenticate', array( $this, 'checkUserAuthLogin_Filter' ), 30, 3);
	
		$sErrorString = sprintf( _wpsf__( "Login Cooldown in effect. You must wait %s seconds before attempting to login again." ), ($nRequiredLoginInterval - $nLoginInterval ) );
		$oError = new WP_Error( 'wpsf_logininterval', $sErrorString );
		return $oError;
	}

	/**
	 * @return int
	 */
	protected function getLastLoginTime() {
		$oWpFs = $this->loadFileSystemProcessor();
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

	/**
	 * @param $innLastLoginTime
	 */
	public function updateLastLoginThrottleTime( $innLastLoginTime ) {
		$oWpFs = $this->loadFileSystemProcessor();
		$this->m_nLastLoginTime = $innLastLoginTime;
		$oWpFs->fileAction( 'touch', array(self::$sModeFile_LoginThrottled, $innLastLoginTime) );
		$this->setNeedSave();
	}

	/**
	 */
	public function printYubikeyOtp_Action() {
		$sHtml =
			'<p class="yubikey-otp">
				<label>%s<br />
					<input type="text" name="yubiotp" class="input" value="" size="20" />
				</label>
			</p>
		';
		echo sprintf( $sHtml, '<a href="http://icwp.io/4i" target="_blank">'._wpsf__('Yubikey OTP').'</a>' );
	}

	/**
	 * @param WP_User $inoUser
	 * @return WP_User|WP_Error
	 */
	public function checkYubikeyOtpAuth_Filter( $inoUser ) {
		$oError = new WP_Error();

		// Before anything else we check that a Yubikey pair has been provided for this username (and that there are pairs in the first place!)
		$aYubikeyUsernamePairs = $this->getOption('yubikey_unique_keys');
		if ( !$this->getIsYubikeyConfigReady() ) { // configuration is clearly not completed yet.
			return $inoUser;
		}

		$sOneTimePassword =  empty( $_POST['yubiotp'] )? '' : trim( $_POST['yubiotp'] );
		$sAppId = $this->getOption('yubikey_app_id');
		$sApiKey = $this->getOption('yubikey_api_key');

		// check that if we have a list of permitted keys, that the one used is on that list connected with the username.
		$sYubikey12 = substr( $sOneTimePassword, 0 , 12 );
		$fUsernameFound = false; // if username is never found, it means there's no yubikey specified which means we can bypass this authentication method.
		$fFoundMatch = false;
		foreach( $aYubikeyUsernamePairs as $aUsernameYubikeyPair ) {
			if ( isset( $aUsernameYubikeyPair[$inoUser->user_login] ) ) {
				$fUsernameFound = true;
				if ( $aUsernameYubikeyPair[$inoUser->user_login] == $sYubikey12 ) {
					$fFoundMatch = true;
					break;
				}
			}
		}

		// If no yubikey-username pair found for given username, we by-pass Yubikey auth.
		if ( !$fUsernameFound ) {
			$this->logWarning(
				sprintf( _wpsf__('User "%s" logged in without a Yubikey One Time Password because no username-yubikey pair was found for this user.'), $inoUser->user_login )
			);
			return $inoUser;
		}

		// Username was found in the list of key pairs, but the yubikey provided didn't match that username.
		if ( !$fFoundMatch ) {
			$oError->add(
				'yubikey_not_allowed',
				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey provided is not on the list of permitted keys for this user.') )
			);
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but Yubikey ID used was not in list of authorised keys: "%s".'), $inoUser->user_login, $sYubikey12 )
			);
			return $oError;
		}

		$oFs = $this->loadFileSystemProcessor();

		$sNonce = md5( uniqid( rand() ) );
		$sUrl = sprintf( self::YubikeyVerifyApiUrl, $sAppId, $sOneTimePassword, $sNonce );
		$sRawYubiRequest = $oFs->getUrlContent( $sUrl );

		// Validate response.
		// 1. Check OTP and Nonce
		if ( !preg_match( '/otp='.$sOneTimePassword.'/', $sRawYubiRequest, $aMatches )
			|| !preg_match( '/nonce='.$sNonce.'/', $sRawYubiRequest, $aMatches )
		) {
			$oError->add(
				'yubikey_validate_fail',
				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey authentication was not validated successfully.') )
			);
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API.'), $inoUser->user_login )
			);
			return $oError;
		}

		// Optionally we can check the hash, but since we're using HTTPS, this isn't necessary and adds more PHP requirements

		// 2. Check status directly within response
		preg_match( '/status=([a-zA-Z0-9_]+)/', $sRawYubiRequest, $aMatches );
		$sStatus = $aMatches[1];

		if ( $sStatus != 'OK' && $sStatus != 'REPLAYED_OTP' ) {
			$oError->add(
				'yubikey_validate_fail',
				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey authentication was not validated successfully.') )
			);
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API response status: %s.'), $inoUser->user_login, $sStatus )
			);
			return $oError;
		}

		$this->logInfo(
			sprintf( _wpsf__('User "%s" successfully logged in using a validated Yubikey One Time Password.'), $inoUser->user_login )
		);
		return $inoUser;
	}

	/**
	 * @return bool
	 */
	protected function getIsYubikeyConfigReady() {
		$sAppId = $this->getOption('yubikey_app_id');
		$sApiKey = $this->getOption('yubikey_api_key');
		$aYubikeyKeys = $this->getOption('yubikey_unique_keys');
		return !empty($sAppId) && !empty($sApiKey) && !empty($aYubikeyKeys);
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

			if ( !$this->getIsUserLevelSubjectToTwoFactorAuth( $inoUser->user_level ) ) {
				return $inoUser;
			}

			if ( $this->isUserVerified( $insUsername ) ) {
				return $inoUser;
			}
			else {
				// Create a new 2-factor auth pending entry
				$aNewAuthData = $this->addNewPendingLoginAuth( $inoUser->user_login );
	
				// Now send email with authentication link for user.
				if ( is_array( $aNewAuthData ) ) {
					$this->doStatIncrement( 'login.twofactor.started' );
					$fEmailSuccess = $this->sendEmailTwoFactorVerify( $inoUser, $aNewAuthData['ip'], $aNewAuthData['unique_id'] );
					
					// Failure to send email - log them in.
					if ( !$fEmailSuccess && $this->getTwoFactorByPassOnFail() ) {
						$this->doMakePendingLoginAuthActive( $aNewAuthData );
						return $inoUser;
					}
				}
			}
		}
		
		$sErrorString = "Login is protected by 2-factor authentication. If your login details were correct, you would have received an email to verify this IP address.";
		return new WP_Error( 'wpsf_loginauth', $sErrorString );
	}

	/**
	 * TODO: http://stackoverflow.com/questions/3499104/how-to-know-the-role-of-current-user-in-wordpress
	 * @param integer $nUserLevel
	 * @return bool
	 */
	public function getIsUserLevelSubjectToTwoFactorAuth( $nUserLevel ) {

		$aSubjectedUserLevels = $this->getOption( 'two_factor_auth_user_roles' );
		if ( empty($aSubjectedUserLevels) || !is_array($aSubjectedUserLevels) ) {
			$aSubjectedUserLevels = array( 1, 2, 3, 8 ); // by default all except subscribers!
		}

		// see: https://codex.wordpress.org/Roles_and_Capabilities#User_Level_to_Role_Conversion

		// authors, contributors and subscribers
		if ( $nUserLevel < 3 && in_array( $nUserLevel, $aSubjectedUserLevels ) ) {
			return true;
		}
		// editors
		if ( $nUserLevel >= 3 && $nUserLevel < 8 && in_array( 3, $aSubjectedUserLevels ) ) {
			return true;
		}
		// administrators
		if ( $nUserLevel >= 8 && $nUserLevel <= 10 && in_array( 8, $aSubjectedUserLevels ) ) {
			return true;
		}
		return false;
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
			$this->doStatIncrement( 'login.gasp.checkbox.fail' );
			wp_die( "You must check that box to say you're not a bot." );
			return false;
		}
		else if ( isset( $_POST['icwp_wpsf_login_email'] ) && $_POST['icwp_wpsf_login_email'] !== '' ){
			$this->logWarning(
				sprintf( _wpsf__('User "%s" attempted to login but they were caught by the GASP honey pot. Bot Perhaps? IP Address: "%s".'), $insUsername, long2ip($this->m_nRequestIp) )
			);
			$this->doStatIncrement( 'login.gasp.honeypot.fail' );
			wp_die( _wpsf__('You appear to be a bot - terminating login attempt.') );
			return false;
		}
		return true;
	}
	
	public function setTwoFactorByPassOnFail() {
		$this->m_fAllowTwoFactorByPass = $this->getIsOption( 'enable_two_factor_bypass_on_email_fail', 'Y' );
	}
	
	public function getTwoFactorByPassOnFail() {
		if ( !isset( $this->m_fAllowTwoFactorByPass ) ) {
			$this->m_fAllowTwoFactorByPass = false;
		}
		return $this->m_fAllowTwoFactorByPass;
	}

	/**
	 */
	public function setLoginCooldownInterval() {
		$nInterval = intval( $this->getOption('login_limit_interval', 0) );
		$this->m_nRequiredLoginInterval = ( $nInterval < 0 )? 0 : $nInterval;
	}
	
	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	public function addNewPendingLoginAuth( $sUsername ) {
		
		if ( empty( $sUsername ) ) {
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
			'wp_username'	=> $sUsername
		);
		$this->updateRowsFromTable( $aOldData, $aOldWhere );

		// Now add new pending entry
		$aNewData = array();
		$aNewData[ 'unique_id' ]	= uniqid();
		$aNewData[ 'ip_long' ]		= $this->m_nRequestIp;
		$aNewData[ 'ip' ]			= long2ip( $this->m_nRequestIp );
		$aNewData[ 'wp_username' ]	= $sUsername;
		$aNewData[ 'pending' ]		= 1;
		$aNewData[ 'created_at' ]	= time();

		$mResult = $this->insertIntoTable( $aNewData );
		if ( $mResult ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" created a pending Two-Factor Authentication for IP Address "%s".'), $sUsername, $aNewData[ 'ip' ] )
			);
			$mResult = $aNewData;
		}
		return $mResult;
	}
	
	/**
	 * Given a unique Id and a corresponding WordPress username, will update the authentication table so that it is active (pending=0).
	 * 
	 * @param array $inaWhere - unique_id, wp_username
	 * @return boolean
	 */
	public function doMakePendingLoginAuthActive( $inaWhere ) {
		
		$aChecks = array( 'unique_id', 'wp_username' );
		if ( !$this->validateParameters( $inaWhere, $aChecks ) ) {
			return false;
		}
		
		// First set any active, non-pending entries for the given user to be deleted
		$this->terminateActiveLoginForUser( $inaWhere['wp_username'] );

		// Now activate the new one.

		// Updates the database
		$inaWhere['pending']	= 1;
		$inaWhere['deleted_at']	= 0;
		$mResult = $this->updateRowsFromTable( array( 'pending' => 0 ), $inaWhere );

		// Set the necessary cookie
		$this->setAuthActiveCookie( $inaWhere['unique_id'] );
		return $mResult;
	}

	/**
	 * Invalidates all currently active two-factor logins and redirects to admin (->login)
	 */
	public function doTerminateAllVerifiedLogins() {
		$this->terminateAllVerifiedLogins();
		$oWp = $this->loadWpFunctionsProcessor();
		$oWp->redirectToAdmin();
	}

	/**
	 * @param $sUsername
	 */
	protected function setUserLoggedIn( $sUsername ) {
		$oWp = $this->loadWpFunctionsProcessor();
		$oUser = version_compare( $oWp->getWordpressVersion(), '3.2.2', '<=' )? get_userdatabylogin( $sUsername ) : get_user_by( 'login', $sUsername );

		wp_clear_auth_cookie();
		wp_set_current_user ( $oUser->ID, $oUser->user_login );
		wp_set_auth_cookie  ( $oUser->ID, true );
		do_action( 'wp_login', $oUser->user_login );
	}

	/**
	 * Given a username will soft-delete any currently active two-factor authentication.
	 *
	 * @param $sUsername
	 */
	protected function terminateActiveLoginForUser( $sUsername ) {
		$sNow = time();
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
			esc_sql( $sUsername )
		);
		$this->doSql( $sQuery );
	}

	/**
	 *
	 */
	protected function terminateAllVerifiedLogins() {
		$sNow = time();
		$sQuery = "
			UPDATE `%s`
			SET `deleted_at`	= '%s',
				`expired_at`	= '%s'
			WHERE
				`deleted_at`	= '0'
				AND `pending`	= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$sNow,
			$sNow
		);
		$this->doSql( $sQuery );
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
	 * @param string $sUsername
	 * @return boolean
	 */
	public function isUserVerified( $sUsername ) {

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`wp_username`		= '%s'
				AND `pending`		= '0'
				AND `deleted_at`	= '0'
				AND `expired_at`	= '0'
		";

		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$sUsername
		);

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
				sprintf( _wpsf__('User "%s" was found to be un-verified at the given IP Address "%s"'), $sUsername, long2ip( $this->m_nRequestIp ) )
			);
			return false;
		}
	}

	/**
	 * @param $sUniqueId
	 * @return bool
	 */
	protected function isAuthCookieValid( $sUniqueId ) {
		$this->loadDataProcessor();
		return ICWP_WPSF_DataProcessor::FetchCookie( self::AuthActiveCookie ) == $sUniqueId;
	}

	/**
	 * If it cannot verify current user, will forcefully log them out and redirect to login
	 */
	public function verifyCurrentUser() {
		$oUser = wp_get_current_user();
		if ( is_object( $oUser ) && $oUser instanceof WP_User ) {
			
			if ( $this->getIsUserLevelSubjectToTwoFactorAuth( $oUser->user_level ) && !$this->isUserVerified( $oUser->user_login ) ) {
				$this->logWarning(
					sprintf( _wpsf__('User "%s" was forcefully logged out as they are not verified.'), $oUser->user_login )
				);
				$this->doStatIncrement( 'login.userverify.fail' );
				wp_logout();
				$oWp = $this->loadWpFunctionsProcessor();
				$oWp->redirectToLogin();
			}
		}
	}
	
	/**
	 * Given the necessary components, creates the 2-factor verification link for giving to the user.
	 * 
	 * @param string $sUser
	 * @param string $sUniqueId
	 * @return string
	 */
	protected function generateTwoFactorVerifyLink( $sUser, $sUniqueId ) {
		$sSiteUrl = home_url() . '?wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid=%s';
		$sAction = 'linkauth';
		return sprintf( $sSiteUrl, $this->m_sSecretKey, $sAction, $sUser, $sUniqueId );
	}

	/**
	 * @param WP_User $inoUser
	 * @param string $insIpAddress
	 * @param string $insUniqueId
	 * @return boolean
	 */
	public function sendEmailTwoFactorVerify( WP_User $inoUser, $insIpAddress, $insUniqueId ) {
	
		$sEmail = $inoUser->user_email;
		$sAuthLink = $this->generateTwoFactorVerifyLink( $inoUser->user_login, $insUniqueId );
		
		$aMessage = array(
			_wpsf__('You, or someone pretending to be you, just attempted to login into your WordPress site.'),
			_wpsf__('The IP Address / Cookie from which they tried to login is not currently verified.'),
			_wpsf__('To validate this user, click the following link and then attempt to login again.'),
			sprintf( _wpsf__('IP Address: %s'), $insIpAddress ),
			sprintf( _wpsf__('Authentication Link: %s'), $sAuthLink ),
		);
		$sEmailSubject = sprintf( _wpsf__('Two-Factor Login Verification for: %s'), home_url() );

		// add filters to email sending (for now only Mandrill)
		add_filter( 'mandrill_payload', array($this, 'customiseMandrill') );

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

	/**
	 *
	 */
	public function customiseMandrill( $aMessage ) {
		if ( empty( $aMessage['text'] ) ) {
			$aMessage['text'] = $aMessage['html'];
		}
		return $aMessage;
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
		if ( !$this->getTableExists() ) {
			return;
		}
		$nTimeStamp = time() - (DAY_IN_SECONDS * $this->nDaysToKeepLog);
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}

	/**
	 * @param $nTimeStamp
	 */
	protected function deleteAllRowsOlderThan( $nTimeStamp ) {
		$sQuery = "
			DELETE from `%s`
			WHERE
				`created_at`		< '%s'
				AND `pending`		= '1'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			esc_sql( $nTimeStamp )
		);
		$this->doSql( $sQuery );
	}

}
endif;

if ( !class_exists('ICWP_WPSF_LoginProtectProcessor') ):
	class ICWP_WPSF_LoginProtectProcessor extends ICWP_LoginProtectProcessor_V3 { }
endif;