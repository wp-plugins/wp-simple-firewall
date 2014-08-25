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

if ( !class_exists('ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth') ):

class ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth extends ICWP_WPSF_BaseDbProcessor {

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
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getTwoFactorAuthTableName() );
	}

	/**
	 */
	public function run() {
		parent::run();
		$oDp = $this->loadDataProcessor();

		if ( $this->oFeatureOptions->getOpt( 'two_factor_auth_table_created' ) !== true ) {
			$this->createTable();
//			$this->recreateTable();
			$this->oFeatureOptions->setOpt( 'two_factor_auth_table_created', true );
		}

		// User has clicked a link in their email to validate their IP address for login.
		if ( $oDp->FetchGet( 'wpsf-action' ) == 'linkauth' ) {
			add_action( 'init', array( $this, 'validateUserAuthLink' ), 10 );
		}

		// Check the current logged-in user every page load.
		add_action( 'init', array( $this, 'checkCurrentUserAuth' ), 11 );

		// At this stage (30,3) WordPress has already (20) authenticated the user. So if the login
		// is valid, the filter will have a valid WP_User object passed to it.
		add_filter( 'authenticate', array( $this, 'doUserTwoFactorAuth' ), 30, 3);
	}

	/**
	 * Checks whether the current user that is logged-in is authenticated by IP address.
	 * 
	 * If the user is not found to be valid, they're logged out.
	 * 
	 * Should be hooked to 'init' so we have is_user_logged_in()
	 */
	public function checkCurrentUserAuth() {

		if ( is_user_logged_in() ) {

			$oWp = $this->loadWpFunctionsProcessor();
			$oUser = $oWp->getCurrentWpUser();
			if ( !is_null( $oUser ) ) {

				if ( $this->getIsUserLevelSubjectToTwoFactorAuth( $oUser->user_level ) && !$this->getUserHasValidAuth( $oUser ) ) {

					$this->logWarning(
						sprintf( _wpsf__('User "%s" was forcefully logged out as they are not verified by either cookie or IP address (or both).'), $oUser->user_login )
					);

					$this->doStatIncrement( 'login.userverify.fail' );
					$oWp->forceUserRelogin( array( 'wpsf-forcelogout' => 6 ) );
				}
			}
		}
	}

	/**
	 * Checks whether a given user is authenticated.
	 *
	 * @param WP_User $oUser
	 * @return boolean
	 */
	public function getUserHasValidAuth( $oUser ) {

		$fVerified = false;
		$aUserAuthData = $this->query_GetActiveAuthForUser( $oUser );

		if ( !is_null( $aUserAuthData ) ) {

			// Now we test based on which types of 2-factor auth is enabled
			$fVerified = true;
			if ( $this->oFeatureOptions->getIsTwoFactorAuthOn('ip') && ( self::$nRequestIp != $aUserAuthData['ip_long'] ) ) {
				$fVerified = false;
			}

			if ( $fVerified && $this->oFeatureOptions->getIsTwoFactorAuthOn('cookie') && !$this->getIsAuthCookieValid( $aUserAuthData['unique_id'] ) ) {
				$fVerified = false;
			}
		}

		if ( !$fVerified ) {
			$this->logWarning(
				sprintf( _wpsf__('User "%s" was found to be un-verified at the given IP Address "%s"'), $oUser->user_login, long2ip( self::$nRequestIp ) )
			);
		}

		return $fVerified;
	}

	/**
	 * Checks the link details to ensure all is valid before authorizing the user.
	 */
	public function validateUserAuthLink() {
		$oDp = $this->loadDataProcessor();
		// wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid

		if ( $oDp->FetchGet( 'wpsfkey' ) !== $this->oFeatureOptions->getTwoAuthSecretKey() ) {
			return false;
		}

		$sUsername = $oDp->FetchGet( 'username' );
		$sUniqueId = $oDp->FetchGet( 'uniqueid' );

		if ( empty( $sUsername ) || empty( $sUniqueId ) ) {
			return false;
		}

		$oWp = $this->loadWpFunctionsProcessor();
		if ( $this->setLoginAuthActive( $sUniqueId, $sUsername ) ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" verified their identity using Two-Factor Authentication.'), $sUsername )
			);
			$this->doStatIncrement( 'login.twofactor.verified' );
			$oWp->setUserLoggedIn( $sUsername );
			$oWp->redirectToAdmin();
		}
		else {
			$oWp->redirectToLogin();
		}
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
	 * @param WP_User|string $oUser	- the docs say the first parameter a string, WP actually gives a WP_User object (or null)
	 * @param string $sUsername
	 * @param string $sPassword
	 * @return WP_Error|WP_User|null	- WP_User when the login success AND the IP is authenticated. null when login not successful but IP is valid. WP_Error otherwise.
	 */
	public function doUserTwoFactorAuth( $oUser, $sUsername, $sPassword ) {

		if ( empty( $sUsername ) ) {
			return $oUser;
		}
	
		$fUserLoginSuccess = is_object( $oUser ) && ( $oUser instanceof WP_User );

		if ( $fUserLoginSuccess ) {

			if ( !$this->getIsUserLevelSubjectToTwoFactorAuth( $oUser->user_level ) ) {
				return $oUser;
			}
			if ( $this->getUserHasValidAuth( $oUser ) ) {
				return $oUser;
			}

			// Create a new 2-factor auth pending entry
			$aNewAuthData = $this->query_DoCreatePendingLoginAuth( $oUser->user_login );

			// Now send email with authentication link for user.
			if ( is_array( $aNewAuthData ) ) {
				$this->doStatIncrement( 'login.twofactor.started' );
				$fEmailSuccess = $this->sendEmailTwoFactorVerify( $oUser, $aNewAuthData['ip'], $aNewAuthData['unique_id'] );

				// Failure to send email - log them in.
				if ( !$fEmailSuccess && $this->getIsOption( 'enable_two_factor_bypass_on_email_fail', 'Y' ) ) {
					$this->setLoginAuthActive( $aNewAuthData['unique_id'], $aNewAuthData['wp_username'] );
					return $oUser;
				}
			}
		}

		// We default to returning a login cooldown error if that's in place.
		if ( is_wp_error( $oUser ) ) {
			$aCodes = $oUser->get_error_codes();
			if ( in_array( 'wpsf_logininterval', $aCodes ) ) {
				return $oUser;
			}
		}

		$sErrorString = _wpsf__( "Login is protected by 2-factor authentication." )
		.' '._wpsf__( "If your login details were correct, you will have received an email to complete the login process." ) ;
		return new WP_Error( 'wpsf_loginauth', $sErrorString );
	}

	/**
	 * @param string $sUniqueId
	 * @param string $sUsername
	 * @return boolean
	 */
	public function setLoginAuthActive( $sUniqueId, $sUsername ) {
		// 1. Terminate old entries
		$this->query_DoTerminateActiveLoginForUser( $sUsername );

		// 2. Authenticate new entry
		$aWhere = array(
			'unique_id'		=> $sUniqueId,
			'wp_username'	=> $sUsername
		);
		$this->query_DoMakePendingLoginAuthActive( $aWhere );

		// 3. Set Auth Cookie
		$this->setAuthActiveCookie( $sUniqueId );

		return true;
	}

	/**
	 * TODO: http://stackoverflow.com/questions/3499104/how-to-know-the-role-of-current-user-in-wordpress
	 * @param integer $nUserLevel
	 * @return bool
	 */
	protected function getIsUserLevelSubjectToTwoFactorAuth( $nUserLevel ) {

		$aSubjectedUserLevels = $this->getOption( 'two_factor_auth_user_roles' );
		if ( empty($aSubjectedUserLevels) || !is_array($aSubjectedUserLevels) ) {
			$aSubjectedUserLevels = array( 1, 2, 3, 8 ); // by default all roles except subscribers!
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
	protected function query_DoCreatePendingLoginAuth( $sUsername ) {
		
		if ( empty( $sUsername ) ) {
			return false;
		}
		
		// First set any other pending entries for the given user to be deleted.
		$aSetDeleted = array(
			'deleted_at'	=> self::$nRequestTimestamp,
			'expired_at'	=> self::$nRequestTimestamp,
		);
		$aOldPendingAuth = array(
			'pending'		=> 1,
			'deleted_at'	=> 0,
			'wp_username'	=> $sUsername
		);
		$this->updateRowsFromTable( $aSetDeleted, $aOldPendingAuth );

		// Now add new pending entry
		$aNewData = array();
		$aNewData[ 'unique_id' ]	= uniqid();
		$aNewData[ 'ip_long' ]		= self::$nRequestIp;
		$aNewData[ 'ip' ]			= long2ip( self::$nRequestIp );
		$aNewData[ 'wp_username' ]	= $sUsername;
		$aNewData[ 'pending' ]		= 1;
		$aNewData[ 'created_at' ]	= self::$nRequestTimestamp;

		$mResult = $this->insertIntoTable( $aNewData );
		return $mResult ? $aNewData : $mResult;
	}
	
	/**
	 * Given a unique ID and a corresponding WordPress username, will update the authentication table so that it is active (pending=0).
	 * 
	 * @param array $aWhere - unique_id, wp_username
	 * @return boolean
	 */
	public function query_DoMakePendingLoginAuthActive( $aWhere ) {
		
		$aChecks = array( 'unique_id', 'wp_username' );
		if ( !$this->validateParameters( $aWhere, $aChecks ) ) {
			return false;
		}

		// Activate the new one.
		$aWhere['pending'] 		= 1;
		$aWhere['deleted_at']	= 0;
		$mResult = $this->updateRowsFromTable( array( 'pending' => 0 ), $aWhere );
		return $mResult;
	}

	/**
	 * Invalidates all currently active two-factor logins and redirects to admin (->login)
	 */
	public function doTerminateAllVerifiedLogins() {
		$this->query_DoTerminateAllVerifiedLogins();
		$oWp = $this->loadWpFunctionsProcessor();
		$oWp->redirectToAdmin();
	}

	/**
	 * Given a username will soft-delete any currently active two-factor authentication.
	 *
	 * @param $sUsername
	 */
	protected function query_DoTerminateActiveLoginForUser( $sUsername ) {
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
	protected function query_DoTerminateAllVerifiedLogins() {
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
	 * @param $sUniqueId
	 */
	public function setAuthActiveCookie( $sUniqueId ) {
		$nWeek = defined( 'WEEK_IN_SECONDS' )? WEEK_IN_SECONDS : 24*60*60;
		setcookie( self::AuthActiveCookie, $sUniqueId, self::$nRequestTimestamp+$nWeek, COOKIEPATH, COOKIE_DOMAIN, false );
	}

	/**
	 * @param WP_User $oUser
	 * @return mixed
	 */
	protected function query_GetActiveAuthForUser( $oUser ) {
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
			$oUser->user_login
		);
		$mResult = $this->selectCustomFromTable( $sQuery );
		return ( is_array( $mResult ) && count( $mResult ) == 1 ) ? $mResult[0] : null ;
	}

	/**
	 * @param $sUniqueId
	 * @return bool
	 */
	protected function getIsAuthCookieValid( $sUniqueId ) {
		$oDp = $this->loadDataProcessor();
		return $oDp->FetchCookie( self::AuthActiveCookie ) == $sUniqueId;
	}

	/**
	 * Given the necessary components, creates the 2-factor verification link for giving to the user.
	 * 
	 * @param string $sUser
	 * @param string $sUniqueId
	 * @return string
	 */
	protected function generateTwoFactorVerifyLink( $sUser, $sUniqueId ) {
		$aQueryArgs = array(
			'wpsfkey' 		=> $this->oFeatureOptions->getTwoAuthSecretKey(),
			'wpsf-action'	=> 'linkauth',
			'username'		=> $sUser,
			'uniqueid'		=> $sUniqueId
		);
		return add_query_arg( $aQueryArgs, home_url() );
	}

	/**
	 * @param WP_User $oUser
	 * @param string $sIpAddress
	 * @param string $insUniqueId
	 * @return boolean
	 */
	public function sendEmailTwoFactorVerify( WP_User $oUser, $sIpAddress, $insUniqueId ) {
	
		$sEmail = $oUser->user_email;
		$sAuthLink = $this->generateTwoFactorVerifyLink( $oUser->user_login, $insUniqueId );
		
		$aMessage = array(
			_wpsf__('You, or someone pretending to be you, just attempted to login into your WordPress site.'),
			_wpsf__('The IP Address / Cookie from which they tried to login is not currently verified.'),
			_wpsf__('Click the following link to validate and complete the login process.') .' '._wpsf__('You will be logged in automatically upon successful authentication.'),
			sprintf( _wpsf__('Username: %s'), $oUser->user_login ),
			sprintf( _wpsf__('IP Address: %s'), $sIpAddress ),
			sprintf( _wpsf__('Authentication Link: %s'), $sAuthLink ),
		);
		$sEmailSubject = sprintf( _wpsf__('Two-Factor Login Verification for: %s'), home_url() );

		// add filters to email sending (for now only Mandrill)
		add_filter( 'mandrill_payload', array ($this, 'customiseMandrill' ) );

		$fResult = $this->getEmailProcessor()->sendEmailTo( $sEmail, $sEmailSubject, $aMessage );
		if ( $fResult ) {
			$this->logInfo(
				sprintf( _wpsf__('User "%s" was sent an email to verify their Identity using Two-Factor Login Auth for IP address "%s".'), $oUser->user_login, $sIpAddress )
			);
		}
		else {
			$this->logCritical(
				sprintf( _wpsf__('Tried to send User "%s" email to verify their Identity using Two-Factor Login Auth for IP Address "%s", but email sending failed.'), $oUser->user_login, $sIpAddress )
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
