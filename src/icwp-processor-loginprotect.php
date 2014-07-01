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

if ( !class_exists('ICWP_WPSF_Processor_LoginProtect_V3') ):

class ICWP_WPSF_Processor_LoginProtect_V3 extends ICWP_WPSF_BaseDbProcessor {
	
	const TableName = 'login_auth';
	const AuthActiveCookie = 'wpsf_auth';

	/**
	 * @var ICWP_WPSF_FeatureHandler_LoginProtect
	 */
	protected $oFeatureOptions;
	/**
	 * @var string
	 */
	protected $nDaysToKeepLog = 1;

	/**
	 * @param ICWP_WPSF_FeatureHandler_LoginProtect $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_LoginProtect $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, self::TableName );
		$this->createTable();
		$this->reset();
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

		$fIp = $this->getIsOption( 'enable_two_factor_auth_by_ip', 'Y' );
		$fCookie = $this->getIsOption( 'enable_two_factor_auth_by_cookie', 'Y' );

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
	 * @return bool|void
	 */
	public function getIsLogging() {
		return $this->getIsOption( 'enable_login_protect_log', 'Y' );
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
		if ( !empty( $aWhitelist ) && $this->isIpOnlist( $aWhitelist, self::$nRequestIp ) ) {
			return true;
		}

		// check for remote posting before anything else.
		if ( $fIsPost && $this->getIsOption('enable_prevent_remote_post', 'Y') ) {
			add_filter( 'authenticate',			array( $this, 'checkRemotePostLogin_Filter' ), 9, 3);
		}

		// Add GASP checking to the login form.
		if ( $this->getIsOption( 'enable_login_gasp_check', 'Y' ) ) {
			require_once('icwp-processor-loginprotect_gasp.php');
			$oGaspProcessor = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->oFeatureOptions );
			$oGaspProcessor->run();
		}

		if ( $fIsPost && $this->getOption( 'login_limit_interval' ) > 0 ) {
			require_once('icwp-processor-loginprotect_cooldown.php');
			$oCooldownProcessor = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->oFeatureOptions );
			$oCooldownProcessor->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $fIsPost && $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			require_once('icwp-processor-loginprotect_yubikey.php');
			$oYubikeyProcessor = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->oFeatureOptions );
			$oYubikeyProcessor->run();
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
	 * @see ICWP_WPSF_Processor_Base::getLogData()
	 */
	public function flushLogData() {
	
		if ( !$this->getIsLogging() || empty( $this->m_aLogMessages ) ) {
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

		if ( ICWP_WPSF_DataProcessor::FetchGet( 'wpsfkey' ) !== $this->oFeatureOptions->getTwoAuthSecretKey() ) {
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
//
//	/**
//	 */
//	public function printYubikeyOtp_Action() {
//		$sHtml =
//			'<p class="yubikey-otp">
//				<label>%s<br />
//					<input type="text" name="yubiotp" class="input" value="" size="20" />
//				</label>
//			</p>
//		';
//		echo sprintf( $sHtml, '<a href="http://icwp.io/4i" target="_blank">'._wpsf__('Yubikey OTP').'</a>' );
//	}

//	/**
//	 * @param WP_User $inoUser
//	 * @return WP_User|WP_Error
//	 */
//	public function checkYubikeyOtpAuth_Filter( $inoUser ) {
//		$oError = new WP_Error();
//
//		// Before anything else we check that a Yubikey pair has been provided for this username (and that there are pairs in the first place!)
//		$aYubikeyUsernamePairs = $this->getOption('yubikey_unique_keys');
//		if ( !$this->getIsYubikeyConfigReady() ) { // configuration is clearly not completed yet.
//			return $inoUser;
//		}
//
//		$sOneTimePassword =  empty( $_POST['yubiotp'] )? '' : trim( $_POST['yubiotp'] );
//		$sAppId = $this->getOption('yubikey_app_id');
//		$sApiKey = $this->getOption('yubikey_api_key');
//
//		// check that if we have a list of permitted keys, that the one used is on that list connected with the username.
//		$sYubikey12 = substr( $sOneTimePassword, 0 , 12 );
//		$fUsernameFound = false; // if username is never found, it means there's no yubikey specified which means we can bypass this authentication method.
//		$fFoundMatch = false;
//		foreach( $aYubikeyUsernamePairs as $aUsernameYubikeyPair ) {
//			if ( isset( $aUsernameYubikeyPair[$inoUser->user_login] ) ) {
//				$fUsernameFound = true;
//				if ( $aUsernameYubikeyPair[$inoUser->user_login] == $sYubikey12 ) {
//					$fFoundMatch = true;
//					break;
//				}
//			}
//		}
//
//		// If no yubikey-username pair found for given username, we by-pass Yubikey auth.
//		if ( !$fUsernameFound ) {
//			$this->logWarning(
//				sprintf( _wpsf__('User "%s" logged in without a Yubikey One Time Password because no username-yubikey pair was found for this user.'), $inoUser->user_login )
//			);
//			return $inoUser;
//		}
//
//		// Username was found in the list of key pairs, but the yubikey provided didn't match that username.
//		if ( !$fFoundMatch ) {
//			$oError->add(
//				'yubikey_not_allowed',
//				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey provided is not on the list of permitted keys for this user.') )
//			);
//			$this->logWarning(
//				sprintf( _wpsf__('User "%s" attempted to login but Yubikey ID used was not in list of authorised keys: "%s".'), $inoUser->user_login, $sYubikey12 )
//			);
//			return $oError;
//		}
//
//		$oFs = $this->loadFileSystemProcessor();
//
//		$sNonce = md5( uniqid( rand() ) );
//		$sUrl = sprintf( self::YubikeyVerifyApiUrl, $sAppId, $sOneTimePassword, $sNonce );
//		$sRawYubiRequest = $oFs->getUrlContent( $sUrl );
//
//		// Validate response.
//		// 1. Check OTP and Nonce
//		if ( !preg_match( '/otp='.$sOneTimePassword.'/', $sRawYubiRequest, $aMatches )
//			|| !preg_match( '/nonce='.$sNonce.'/', $sRawYubiRequest, $aMatches )
//		) {
//			$oError->add(
//				'yubikey_validate_fail',
//				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey authentication was not validated successfully.') )
//			);
//			$this->logWarning(
//				sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API.'), $inoUser->user_login )
//			);
//			return $oError;
//		}
//
//		// Optionally we can check the hash, but since we're using HTTPS, this isn't necessary and adds more PHP requirements
//
//		// 2. Check status directly within response
//		preg_match( '/status=([a-zA-Z0-9_]+)/', $sRawYubiRequest, $aMatches );
//		$sStatus = $aMatches[1];
//
//		if ( $sStatus != 'OK' && $sStatus != 'REPLAYED_OTP' ) {
//			$oError->add(
//				'yubikey_validate_fail',
//				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__('The Yubikey authentication was not validated successfully.') )
//			);
//			$this->logWarning(
//				sprintf( _wpsf__('User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API response status: %s.'), $inoUser->user_login, $sStatus )
//			);
//			return $oError;
//		}
//
//		$this->logInfo(
//			sprintf( _wpsf__('User "%s" successfully logged in using a validated Yubikey One Time Password.'), $inoUser->user_login )
//		);
//		return $inoUser;
//	}
//
//	/**
//	 * @return bool
//	 */
//	protected function getIsYubikeyConfigReady() {
//		$sAppId = $this->getOption('yubikey_app_id');
//		$sApiKey = $this->getOption('yubikey_api_key');
//		$aYubikeyKeys = $this->getOption('yubikey_unique_keys');
//		return !empty($sAppId) && !empty($sApiKey) && !empty($aYubikeyKeys);
//	}

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
					if ( !$fEmailSuccess && $this->getIsOption( 'enable_two_factor_bypass_on_email_fail', 'Y' ) ) {
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

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	public function addNewPendingLoginAuth( $sUsername ) {
		
		if ( empty( $sUsername ) ) {
			return false;
		}
		
		// First set any other pending entries for the given user to be deleted.
		$aOldData = array(
			'deleted_at'	=> self::$nRequestTimestamp,
			'expired_at'	=> self::$nRequestTimestamp,
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
		$aNewData[ 'ip_long' ]		= self::$nRequestIp;
		$aNewData[ 'ip' ]			= long2ip( self::$nRequestIp );
		$aNewData[ 'wp_username' ]	= $sUsername;
		$aNewData[ 'pending' ]		= 1;
		$aNewData[ 'created_at' ]	= self::$nRequestTimestamp;

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
		$oUser = version_compare( $oWp->getWordpressVersion(), '2.8.0', '<' )? get_userdatabylogin( $sUsername ) : get_user_by( 'login', $sUsername );

		wp_clear_auth_cookie();
		wp_set_current_user ( $oUser->ID, $oUser->user_login );
		wp_set_auth_cookie  ( $oUser->ID, true );
		do_action( 'wp_login', $oUser->user_login, $oUser );
	}

	/**
	 * Given a username will soft-delete any currently active two-factor authentication.
	 *
	 * @param $sUsername
	 */
	protected function terminateActiveLoginForUser( $sUsername ) {
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
			$this->getTableName(),
			self::$nRequestTimestamp,
			self::$nRequestTimestamp,
			esc_sql( $sUsername )
		);
		$this->doSql( $sQuery );
	}

	/**
	 *
	 */
	protected function terminateAllVerifiedLogins() {
		$sQuery = "
			UPDATE `%s`
			SET `deleted_at`	= '%s',
				`expired_at`	= '%s'
			WHERE
				`deleted_at`	= '0'
				AND `pending`	= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->getTableName(),
			self::$nRequestTimestamp,
			self::$nRequestTimestamp
		);
		return $this->doSql( $sQuery );
	}

	/**
	 * @param $insUniqueId
	 */
	public function setAuthActiveCookie( $insUniqueId ) {
		$nWeek = defined( 'WEEK_IN_SECONDS' )? WEEK_IN_SECONDS : 24*60*60;
		setcookie( self::AuthActiveCookie, $insUniqueId, self::$nRequestTimestamp+$nWeek, COOKIEPATH, COOKIE_DOMAIN, false );
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
			$this->getTableName(),
			$sUsername
		);

		$mResult = $this->selectCustomFromTable( $sQuery );

		if ( is_array( $mResult ) && count( $mResult ) == 1 ) {
			// Now we test based on which types of 2-factor auth is enabled
			$fVerified = true;
			$aUserAuthData = $mResult[0];
			if ( $this->getIsTwoFactorAuthOn('ip') && ( self::$nRequestIp != $aUserAuthData['ip_long'] ) ) {
				$fVerified = false;
			}
			if ( $fVerified && $this->getIsTwoFactorAuthOn('cookie') && !$this->isAuthCookieValid($aUserAuthData['unique_id']) ) {
				$fVerified = false;
			}
			return $fVerified;
		}
		else {
			$this->logWarning(
				sprintf( _wpsf__('User "%s" was found to be un-verified at the given IP Address "%s"'), $sUsername, long2ip( self::$nRequestIp ) )
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
					sprintf( _wpsf__('User "%s" was forcefully logged out as they are not verified by either cookie or IP address (or both).'), $oUser->user_login )
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
		return sprintf( $sSiteUrl, $this->oFeatureOptions->getTwoAuthSecretKey(), $sAction, $sUser, $sUniqueId );
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

		$fResult = $this->getEmailProcessor()->sendEmailTo( $sEmail, $sEmailSubject, $aMessage );
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
	 * @param array $aMessage
	 * @return array
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
		$sSqlTables = sprintf( $sSqlTables, $this->getTableName() );
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
		$sQuery = sprintf( $sQuery, $this->getTableName(), $inaData['unique_id'], $inaData['wp_username'] );
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
		$nTimeStamp = self::$nRequestTimestamp - (DAY_IN_SECONDS * $this->nDaysToKeepLog);
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
			$this->getTableName(),
			esc_sql( $nTimeStamp )
		);
		$this->doSql( $sQuery );
	}

}
endif;

if ( !class_exists('ICWP_WPSF_Processor_LoginProtect') ):
	class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_LoginProtect_V3 { }
endif;
