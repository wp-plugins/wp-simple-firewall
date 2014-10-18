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

require_once( dirname(__FILE__).'/icwp-processor-basedb.php' );

if ( !class_exists('ICWP_WPSF_Processor_UserManagement_V1') ):

class ICWP_WPSF_Processor_UserManagement_V1 extends ICWP_WPSF_BaseDbProcessor {

	const Session_Cookie	=	'wpsf_sesh_id';

	/**
	 * @var ICWP_WPSF_FeatureHandler_UserManagement
	 */
	protected $oFeatureOptions;
	/**
	 * @var string
	 */
	protected $nDaysToKeepLog = 30;

	/**
	 * @var string
	 */
	protected $sSessionId;

	/**
	 * @param ICWP_WPSF_FeatureHandler_UserManagement $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_UserManagement $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getUserSessionsTableName() );
	}

	/**
	 * @return bool
	 */
	public function run() {

		$oWp = $this->loadWpFunctionsProcessor();

		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return true;
		}

		if ( is_email( $this->getOption( 'enable_admin_login_email_notification' ) ) ) {
			require_once('icwp-processor-usermanagement_adminloginnotification.php');
			$oNotificationProcessor = new ICWP_WPSF_Processor_UserManagement_AdminLoginNotification( $this->getFeatureOptions() );
			$oNotificationProcessor->run();
		}

		// Check the current logged-in user every page load.
		add_action( 'init', array( $this, 'checkCurrentUser_Action' ) );

		// Check login attempts
		// At this stage (30,3) WordPress has already (20) authenticated the user. So if the login
		// is valid, the filter will have a valid WP_User object passed to it.
		add_filter( 'auth_cookie_expiration', array( $this, 'setWordpressTimeoutCookieExpiration_Filter' ), 100, 1 );

		// Check login attempts
		// At this stage (30,3) WordPress has already (20) authenticated the user. So if the login
		// is valid, the filter will have a valid WP_User object passed to it.
		add_filter( 'authenticate', array( $this, 'createNewUserSession_Filter' ), 30, 3 );

		// When we know user has successfully authenticated and we activate the session entry in the database
		add_action( 'wp_login', array( $this, 'handleUserSession' ) );

//		add_action( 'wp_loaded', array( $this, 'autoForwardFromLogin' ) );

		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );

		return true;
	}


	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( ! $oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$oDp = $this->loadDataProcessor();
		$sForceLogout = $oDp->FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout == 1 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your session has expired.').'<br />'._wpsf__('Please login again.') );
		}
		else if ( $sForceLogout == 2 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your session was idle for too long.').'<br />'._wpsf__('Please login again.') );
		}
		else if ( $sForceLogout == 3 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your session was locked to another IP Address.').'<br />'._wpsf__('Please login again.') );
		}
		else if ( $sForceLogout == 4 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('You do not currently have a Simple Firewall user session.').'<br />'._wpsf__('Please login again.') );
		}
		else if ( $sForceLogout == 5 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('An administrator has terminated this session.').'<br />'._wpsf__('Please login again.') );
		}
		return $oError;
	}

	/**
	 * Should be hooked to 'init' so we have is_user_logged_in()
	 */
	public function checkCurrentUser_Action() {
		$this->getSessionId();
		$oDp = $this->loadDataProcessor();

		if ( is_user_logged_in() ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oUser = $oWp->getCurrentWpUser();

			// only check the non-admin areas if specified to do so and it's not AJAX (we've removed the option to check admin only)
//			if ( !$oWp->getIsAjax() && ( is_admin() || !$this->getIsOption( 'session_check_admin_area_only', 'Y' ) ) ) {
			if ( is_admin() ) {
				$this->doVerifyCurrentUser( $oUser );
			}

			// At this point session is validated

			// This used to be an option, but to simplify, we've removed it and do it anyway.
//			if ( true || $this->getIsOption( 'session_auto_forward_to_admin_area', 'Y' ) ) {
			$sWpLogin = 'wp-login.php';
			if ( $oDp->FetchGet( 'action' ) != 'logout' && ( substr( $oWp->getCurrentPage(), -strlen( $sWpLogin ) ) === $sWpLogin ) ) {
				// send query arg to prevent redirect loop here.
				$oWp->redirectToAdmin();
			}

			// always track activity
			$this->updateSessionLastActivity( $oUser );
		}
	}

	/**
	/**
	 * If it cannot verify current user, will forcefully log them out and redirect to login
	 *
	 * @param WP_User $oUser
	 *
	 * @return bool
	 */
	public function doVerifyCurrentUser( $oUser ) {
		if ( !is_object( $oUser ) || ! ( $oUser instanceof WP_User ) ) {
			return false;
		}

		$oWp = $this->loadWpFunctionsProcessor();

		$aLoginSessionData = $this->getUserSessionRecord( $oUser->get( 'user_login' ) );
		if ( !$aLoginSessionData ) {
			$oWp->forceUserRelogin( array( 'wpsf-forcelogout' => 4 ) );
		}

		// check timeout interval
		$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
		if ( $nSessionTimeoutInterval > 0 && ( $this->time() - $aLoginSessionData['logged_in_at'] > $nSessionTimeoutInterval ) ) {
			$oWp->forceUserRelogin( array( 'wpsf-forcelogout' => 1 ) );
		}

		// check idle timeout interval
		$nSessionIdleTimeoutInterval = $this->getOption( 'session_idle_timeout_interval', 0 ) * HOUR_IN_SECONDS;
		if ( intval($nSessionIdleTimeoutInterval) > 0 && ( ($this->time() - $aLoginSessionData['last_activity_at']) > $nSessionIdleTimeoutInterval ) ) {
			$oWp->forceUserRelogin( array( 'wpsf-forcelogout' => 2 ) );
		}

		// check login ip address
		$fLockToIp = $this->getIsOption( 'session_lock_location', 'Y' );
		$sVisitorIp = $this->loadDataProcessor()->getVisitorIpAddress( true );
		if ( $fLockToIp && $sVisitorIp != $aLoginSessionData['ip'] ) {
			$oWp->forceUserRelogin( array( 'wpsf-forcelogout' => 3 ) );
		}

		return true;
	}

	/**
	 * @return integer
	 */
	protected function getSessionTimeoutInterval( ) {
		return $this->getOption( 'session_timeout_interval' ) * DAY_IN_SECONDS;
	}

	/**
	 * @param integer $nTimeout
	 * @return integer
	 */
	public function setWordpressTimeoutCookieExpiration_Filter( $nTimeout ) {
		$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
		return ( ( $nSessionTimeoutInterval > 0 )? $nSessionTimeoutInterval : $nTimeout );
	}

	/**
	 * If $oUser is a valid WP_User object, then the user logged in correctly.
	 *
	 * @param WP_User|string $oUser	- the docs say the first parameter a string, WP actually gives a WP_User object (or null)
	 * @param string $sUsername
	 * @param string $sPassword
	 * @return WP_Error|WP_User|null	- WP_User when the login success AND the IP is authenticated. null when login not successful but IP is valid. WP_Error otherwise.
	 */
	public function createNewUserSession_Filter( $oUser, $sUsername, $sPassword ) {

		//TODO: review is this the correct filter and processing?
		if ( empty( $sUsername ) ) {
			return $oUser;
		}

		$aCurrentRecord = $this->getUserSessionRecord( $sUsername );
		if ( !$aCurrentRecord ) {
			$this->addNewPendingUserSession( $sUsername );
		}

		$this->incrementUserLoginAttempts( $sUsername );

		$fUserLoginSuccess = is_object( $oUser ) && ( $oUser instanceof WP_User );
		if ( !$fUserLoginSuccess ) {
			return $oUser;
		}
		return $oUser;
	}

	/**
	 *
	 */
	public function onWpLogout() {
		$this->doTerminateCurrentUserSession();
	}

	/**
	 * @return boolean
	 */
	protected function doTerminateCurrentUserSession() {
		$oWp = $this->loadWpFunctionsProcessor();
		$oUser = $oWp->getCurrentWpUser();
		if ( empty( $oUser->user_login ) ) {
			return false;
		}

		$mResult = $this->doTerminateUserSession( $oUser->user_login, $this->getSessionId() );
		unset( $_COOKIE[ $this->oFeatureOptions->getUserSessionCookieName() ] );
		setcookie( $this->oFeatureOptions->getUserSessionCookieName(), "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
		return $mResult;
	}

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return boolean
	 */
	protected function doTerminateUserSession( $sUsername, $sSessionId ) {

		$aNewData = array(
			'deleted_at'	=> $this->time()
		);
		$aWhere = array(
			'session_id'	=> $sSessionId,
			'wp_username'	=> $sUsername,
			'deleted_at'	=> 0
		);
		return $this->updateRowsWhere( $aNewData, $aWhere );
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	protected function addNewPendingUserSession( $sUsername ) {
		if ( empty( $sUsername ) ) {
			return false;
		}

		$oDp = $this->loadDataProcessor();
		// Add new session entry
		// set attempts = 1 and then when we know it's a valid login, we zero it.
		// First set any other entries for the given user to be deleted.
		$aNewData = array();
		$aNewData[ 'session_id' ]			= $this->getSessionId();
		$aNewData[ 'ip' ]			    	= $oDp->getVisitorIpAddress( true );
		$aNewData[ 'wp_username' ]			= $sUsername;
		$aNewData[ 'login_attempts' ]		= 0;
		$aNewData[ 'pending' ]				= 1;
		$aNewData[ 'logged_in_at' ]			= $this->time();
		$aNewData[ 'last_activity_at' ]		= $this->time();
		$aNewData[ 'last_activity_uri' ]	= $oDp->FetchServer( 'REQUEST_URI' );
		$aNewData[ 'created_at' ]			= $this->time();
		$mResult = $this->insertData( $aNewData );

		return $mResult;
	}

	/**
	 */
	protected function setSessionCookie() {
		if ( $this->getSessionTimeoutInterval() > 0 ) {
			$oWp = $this->loadWpFunctionsProcessor();
			setcookie(
				$this->oFeatureOptions->getUserSessionCookieName(),
				$this->getSessionId(),
				$this->time() + $this->getSessionTimeoutInterval(),
				$oWp->getCookiePath(),
				$oWp->getCookieDomain(),
				false
			);
		}
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	protected function incrementUserLoginAttempts( $sUsername ) {
		if ( empty( $sUsername ) ) {
			return false;
		}

		$aSessionData = $this->getUserSessionRecord( $sUsername );
		$aNewData = array(
			'login_attempts'	=> $aSessionData['login_attempts'] + 1
		);
		return $this->updateCurrentSession( $sUsername, $aNewData );
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	public function handleUserSession( $sUsername ) {
		if ( empty( $sUsername ) ) {
			return false;
		}
		$this->activateUserSession( $sUsername );
		$this->doLimitUserSession( $sUsername );
		return true;
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	protected function activateUserSession( $sUsername ) {

		$aNewData = array(
			'pending'			=> 0,
			'logged_in_at'		=> $this->time(),
			'last_activity_at'	=> $this->time()
		);
		$aWhere = array(
			'session_id'	=> $this->getSessionId(),
			'pending'		=> 1,
			'wp_username'	=> $sUsername,
			'deleted_at'	=> 0
		);
		$mResult = $this->updateRowsWhere( $aNewData, $aWhere );

		// Now set session Cookie so it reflects the correct expiry
		$this->setSessionCookie();
		return $mResult;
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	protected function doLimitUserSession( $sUsername ) {

		$nSessionLimit = $this->getOption( 'session_username_concurrent_limit', 1 );
		if ( $nSessionLimit <= 0 ) {
			return true;
		}

		$aSessions = $this->getActiveSessionRecordsForUser( $sUsername );
		$nSessionsToKill = count( $aSessions ) - $nSessionLimit;
		if ( $nSessionsToKill < 1 ) {
			return true;
		}

		$mResult = true;
		for( $nCount = 0; $nCount < $nSessionsToKill; $nCount++ ) {
			$mResult = $this->doTerminateUserSession( $aSessions[$nCount]['wp_username'], $aSessions[$nCount]['session_id'] );
		}
		return $mResult;
	}

	/**
	 * This is the same as both updateSessionLastActivityAt() and updateSessionLastActivityUri()
	 *
	 * @param WP_User $oUser
	 * @return boolean
	 */
	protected function updateSessionLastActivity( $oUser ) {
		if ( !is_object( $oUser ) || ! ( $oUser instanceof WP_User ) ) {
			return false;
		}

		$oDp = $this->loadDataProcessor();
		$aNewData = array(
			'last_activity_at'	=> $this->time(),
			'last_activity_uri'	=> $oDp->FetchServer( 'REQUEST_URI' )
		);
		return $this->updateCurrentSession( $oUser->get( 'user_login' ), $aNewData );
	}

	/**
	 * @param $sUsername
	 * @param $aUpdateData
	 * @return boolean
	 */
	protected function updateCurrentSession( $sUsername, $aUpdateData ) {
		return $this->updateSession( $this->getSessionId(), $sUsername, $aUpdateData );
	}

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @param array $aUpdateData
	 * @return boolean
	 */
	protected function updateSession( $sSessionId, $sUsername, $aUpdateData ) {
		$aWhere = array(
			'session_id'	=> $sSessionId,
			'deleted_at'	=> 0,
			'wp_username'	=> $sUsername
		);
		$mResult = $this->updateRowsWhere( $aUpdateData, $aWhere );
		return $mResult;
	}

	/**
	 * @param string $sWpUsername
	 *
	 * @return array|bool
	 */
	public function getActiveUserSessionRecords( $sWpUsername = '' ) {

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`pending`			= '0'
				AND `deleted_at`	= '0'
				%s
			ORDER BY `last_activity_at` ASC
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			empty( $sWpUsername ) ? '' : "AND `wp_username`		= '".esc_sql( $sWpUsername )."'"
		);
		return $this->selectCustom( $sQuery );
	}

	/**
	 * Checks for and gets a user session.
	 *
	 * @param string $sWpUsername
	 * @return array|boolean
	 */
	public function getActiveSessionRecordsForUser( $sWpUsername ) {
		return $this->getActiveUserSessionRecords( $sWpUsername );
	}

	/**
	 * Checks for and gets a user session.
	 *
	 * @param integer $nTime - number of seconds back from now to look
	 * @return array|boolean
	 */
	public function getPendingOrFailedUserSessionRecordsSince( $nTime = 0 ) {

		$nTime = ( $nTime <= 0 ) ? 2*DAY_IN_SECONDS : $nTime;

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`pending`			= '1'
				AND `deleted_at`	= '0'
				AND `created_at`	> '%s'
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			( $this->time() - $nTime )
		);

		return $this->selectCustom( $sQuery );
	}

	/**
	 * Checks for and gets a user session.
	 *
	 * @param string $sUsername
	 * @return array|boolean
	 */
	protected function getUserSessionRecord( $sUsername ) {

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`wp_username`		= '%s'
				AND `session_id`	= '%s'
				AND `deleted_at`	= '0'
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			esc_sql( $sUsername ),
			esc_sql( $this->getSessionId() )
		);

		$mResult = $this->selectCustom( $sQuery );
		if ( is_array( $mResult ) && count( $mResult ) == 1 ) {
			return $mResult[0];
		}
		return false;
	}

	/**
	 *
	 */
	protected function getSessionId() {
		if ( empty( $this->sSessionId ) ) {
			$oDp = $this->loadDataProcessor();
			$this->sSessionId = $oDp->FetchCookie( $this->getFeatureOptions()->getUserSessionCookieName() );
			if ( empty( $this->sSessionId ) ) {
				$this->sSessionId = md5( uniqid() );
				$this->setSessionCookie();
			}
		}
		return $this->sSessionId;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`session_id` VARCHAR(32) NOT NULL DEFAULT '',
			`wp_username` VARCHAR(255) NOT NULL DEFAULT '',
			`ip` VARCHAR(40) NOT NULL DEFAULT '0',
			`logged_in_at` INT(15) NOT NULL DEFAULT '0',
			`last_activity_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
			`last_activity_uri` text NOT NULL DEFAULT '',
			`used_mfa` INT(1) NOT NULL DEFAULT '0',
			`pending` TINYINT(1) NOT NULL DEFAULT '0',
			`login_attempts` INT(1) NOT NULL DEFAULT '0',
			`created_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
			`deleted_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		return sprintf( $sSqlTables, $this->getTableName() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		return array(
			'id',
			'session_id',
			'wp_username',
			'ip',
			'logged_in_at',
			'last_activity_at',
			'last_activity_uri',
			'used_mfa',
			'pending',
			'login_attempts',
			'created_at',
			'deleted_at'
		);
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
		$nTimeStamp = $this->time() - (DAY_IN_SECONDS * $this->nDaysToKeepLog);
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}
endif;

if ( !class_exists('ICWP_WPSF_Processor_UserManagement') ):
	class ICWP_WPSF_Processor_UserManagement extends ICWP_WPSF_Processor_UserManagement_V1 { }
endif;
