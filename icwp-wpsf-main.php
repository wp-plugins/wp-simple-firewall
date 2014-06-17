<?php

/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "WordPress Simple Firewall" is
 * distributed under the GNU General Public License, Version 2,
 * June 1991. Copyright (C) 1989, 1991 Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110, USA
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

require_once( dirname(__FILE__).'/src/icwp-feature-master.php' );
require_once( dirname(__FILE__).'/src/icwp-data-processor.php' );

if ( !function_exists( '_wpsf_e' ) ) {
	function _wpsf_e( $insStr ) {
		_e( $insStr, 'wp-simple-firewall' );
	}
}
if ( !function_exists( '_wpsf__' ) ) {
	function _wpsf__( $insStr ) {
		return __( $insStr, 'wp-simple-firewall' );
	}
}

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

class ICWP_Wordpress_Simple_Firewall extends ICWP_Feature_Master {

	/**
	 * @var string
	 */
	const AdminAccessKeyCookieName		= 'icwp_wpsf_aakcook';

	/**
	 * @var ICWP_OptionsHandler_Firewall
	 */
	protected $m_oFirewallOptions;
	/**
	 * @var ICWP_OptionsHandler_LoginProtect
	 */
	protected $m_oLoginProtectOptions;

	/**
	 * @var ICWP_OptionsHandler_PrivacyProtect
	 */
	protected $m_oPrivacyProtectOptions;

	/**
	 * @var ICWP_OptionsHandler_CommentsFilter
	 */
	protected $m_oCommentsFilterOptions;

	/**
	 * @var ICWP_OptionsHandler_Lockdown
	 */
	protected $m_oLockdownOptions;

	/**
	 * @var ICWP_OptionsHandler_AutoUpdates
	 */
	protected $m_oAutoUpdatesOptions;

	/**
	 * @var ICWP_OptionsHandler_Email_Wpsf
	 */
	protected $m_oEmailOptions;

	/**
	 * @var ICWP_FirewallProcessor
	 */
	protected $m_oFirewallProcessor;

	/**
	 * @var ICWP_LoginProtectProcessor
	 */
	protected $m_oLoginProtectProcessor;

	/**
	 * @var ICWP_CommentsFilterProcessor
	 */
	protected $m_oCommentsFilterProcessor;
	/**
	 * @var ICWP_LockdownProcessor
	 */
	protected $m_oLockdownProcessor;

	/**
	 * @var ICWP_WPSF_PrivacyProtectProcessor
	 */
	protected $m_oPrivacyProtectProcessor;

	/**
	 * @var ICWP_WPSF_AutoUpdatesProcessor
	 */
	protected $m_oAutoUpdatesProcessor;

	/**
	 * @var ICWP_WPSF_LoggingProcessor
	 */
	protected $m_oLoggingProcessor;

	/**
	 * @var ICWP_EmailProcessor
	 */
	protected $m_oEmailProcessor;

	/**
	 * @var bool
	 */
	private $fAdminAccessPermSubmit = null;

	/**
	 */
	public function __construct( ICWP_Wordpress_Simple_Firewall_Plugin $oPluginVo ) {

		parent::__construct(
			$oPluginVo,
			array(
				'logging'			=> 'Logging',
				'email'				=> 'Email',
				'firewall'			=> 'Firewall',
				'login_protect'		=> 'LoginProtect',
				'comments_filter'	=> 'CommentsFilter',
//				'privacy_protect'	=> 'PrivacyProtect',
				'autoupdates'		=> 'AutoUpdates',
				'lockdown'			=> 'Lockdown'
			),
			array(
				'm_oPluginMainOptions',
				'm_oEmailOptions',
				'm_oFirewallOptions',
				'm_oLoginProtectOptions',
				'm_oCommentsFilterOptions',
				'm_oPrivacyProtectOptions',
				'm_oLockdownOptions',
				'm_oAutoUpdatesOptions'
			)
		);

		// loads the base plugin options from 1 db call
		if ( is_admin() ) {
			$this->loadOptionsHandler( 'all' );
		}
		else {
			$this->loadOptionsHandler( 'PluginMain' );
		}
		$this->fAutoPluginUpgrade = false && $this->m_oPluginMainOptions->getOpt( 'enable_auto_plugin_upgrade' ) == 'Y';

		// checks for filesystem based firewall overrides
		$this->override();

		if ( isset( $_GET['turnoffperm'] ) ) {
			$this->setPermissionToSubmit( false );
		}

		add_filter( 'pre_update_option', array($this, 'blockOptionsSaves'), 1, 3 );
	}

	/**
	 * @return string
	 */
	protected function override() {
		$sSetting = parent::override();
		if ( empty( $sSetting ) ) {
			return $sSetting;
		}
		$this->m_oPluginMainOptions->setOpt( 'enable_admin_access_restriction', $sSetting );
		$this->m_oPluginMainOptions->savePluginOptions();
		return $sSetting;
	}

	/**
	 * Should be called from the constructor so as to ensure it is called as early as possible.
	 *
	 * @return void
	 */
	public function runFirewallProcess() {

		$this->loadProcessor( 'Firewall' );
		$fFirewallBlockUser = !$this->m_oFirewallProcessor->doFirewallCheck();

		if ( $fFirewallBlockUser ) {
			if ( $this->m_oFirewallProcessor->getNeedsEmailHandler() ) {
				$this->loadProcessor( 'Email' );
				$this->m_oFirewallProcessor->setEmailHandler( $this->m_oEmailProcessor );
			}
			$this->m_oFirewallProcessor->doPreFirewallBlock();
		}

		if ( $fFirewallBlockUser ) {
			$this->shutdown();
			$this->m_oFirewallProcessor->doFirewallBlock();
		}
	}

	/**
	 * Handles the running of all Login Protection processes.
	 */
	public function runLoginProtect() {
		$this->loadProcessor( 'LoginProtect' );
		$this->loadProcessor( 'Email' );
		$this->m_oLoginProtectProcessor->setEmailHandler( $this->m_oEmailProcessor );
		$this->m_oLoginProtectProcessor->run();
	}

	/**
	 * Handles the running of all Auto Update processes.
	 */
	public function runAutoUpdates() {
		$this->loadProcessor( 'AutoUpdates' );
		$this->m_oAutoUpdatesProcessor->run( $this->getPluginBaseFile() );
	}

	protected function createPluginSubMenuItems() {
//		$aItems = array(
//			Menu Page Title => Menu Item name, page ID (slug), callback function for this page - i.e. what to do/load.
//			$this->getSubmenuPageTitle( _wpsf__('Firewall') )			=> array( 'Firewall', $this->getSubmenuId('firewall'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Login Protect') )		=> array( 'Login Protect', $this->getSubmenuId('login_protect'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Comments Filter') )	=> array( 'Comments Filter', $this->getSubmenuId('comments_filter'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Privacy Protect') )	=> array( 'Privacy Protect', $this->getSubmenuId('privacy_protect'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Automatic Updates') )	=> array( 'Automatic Updates', $this->getSubmenuId('autoupdates'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Lockdown') )			=> array( 'Lockdown', $this->getSubmenuId('lockdown'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Firewall Log' ) )		=> array( 'Firewall Log', $this->getSubmenuId('firewall_log'), 'onDisplayAll' ),
//			$this->getSubmenuPageTitle( _wpsf__('Privacy Log' ) )		=> array( 'Privacy Log', $this->getSubmenuId('privacy_protect_log'), 'onDisplayAll' )
//		);
		$this->aPluginMenu = apply_filters( 'icwp_wpsf_filter_plugin_submenu_items', array() );
		$this->aPluginMenu[ _wpsf__('Firewall Log' ) ] = array( 'Firewall Log', $this->getSubmenuId('firewall_log'), 'onDisplayAll' );
	}

	protected function handlePluginUpgrade() {
		parent::handlePluginUpgrade();

		$sCurrentPluginVersion = $this->m_oPluginMainOptions->getVersion();

		if ( $sCurrentPluginVersion !== $this->oPluginVo->getVersion() && current_user_can( 'manage_options' ) ) {

			$this->loadOptionsHandler( 'all' );

			// refactoring so that email and logging options are more independent
			if ( version_compare( $sCurrentPluginVersion, '2.3.0', '<' ) ) {
				$this->deleteOption( 'whitelist_admins' );

				$this->m_oEmailOptions->setOpt( 'block_send_email_address', $this->m_oPluginMainOptions->getOpt( 'block_send_email_address') );
				$this->m_oEmailOptions->setOpt( 'send_email_throttle_limit', $this->m_oPluginMainOptions->getOpt( 'send_email_throttle_limit') );
			}//v2.3.0

			$this->loadProcessor( 'Logging' );
			$this->m_oLoggingProcessor->handleInstallUpgrade( $sCurrentPluginVersion );

			// clears all the processor caches
			$this->clearCaches();
		}
	}

	/**
	 * Displaying all views now goes through this central function and we work out
	 * what to display based on the name of current hook/filter being processed.
	 */
	public function onDisplayAll() {

		if ( !$this->hasPermissionToView() ) {
			$this->onDisplayAccessKeyRequest();
			return;
		}

		// Just to ensure the nag bar disappears if/when they visit the dashboard
		// regardless of clicking the button.
		$this->updateVersionUserMeta();

		$sPrefix = str_replace(' ', '-', strtolower( $this->oPluginVo->getAdminMenuTitle() ) ) .'_page_'.$this->getPluginPrefix().'-';
		$sCurrent = str_replace( $sPrefix, '', current_filter() );

		switch( $sCurrent ) {
			case 'toplevel_page_'. $this->oPluginVo->getFullPluginPrefix():
				$this->onDisplayMainMenu();
				break;
			case 'privacy_protect_log' :
				$this->onDisplayPrivacyProtectLog();
				break;
			case 'firewall_log' :
				$this->onDisplayFirewallLog();
				break;
			default:
				$aFeatures = $this->getFeaturesMap();
				$this->loadOptionsHandler( $aFeatures[$sCurrent] );
				$sOptionsName = 'm_o'.$aFeatures[$sCurrent].'Options';
				$this->onDisplayConfig( $this->{$sOptionsName}, $sCurrent );
				break;
		}
	}

	/**
	 * @param string $sSubmenu
	 * @return array
	 */
	protected function getBaseDisplayData( $sSubmenu = '' ) {
		$aBaseData = parent::getBaseDisplayData( $sSubmenu );
		$aBaseData['aMainOptions'] = $this->m_oPluginMainOptions->getPluginOptionsValues();
		return $aBaseData;
	}

	public function onDisplayAccessKeyRequest() {
		$aData = array(
			'nonce_field'		=> $this->getPluginPrefix(),
			'requested_page'	=> $this->getCurrentWpAdminPage()
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( 'icwp_wpsf_access_key_request_index', $aData );
	}

	public function onDisplayMainMenu() {

		$this->loadOptionsHandler( 'all', true );
		$aAvailableOptions = array_merge( $this->m_oPluginMainOptions->getOptions(), $this->m_oEmailOptions->getOptions() );
		$sMainOptions = $this->m_oPluginMainOptions->collateAllFormInputsForAllOptions();
		$sEmailMainOptions = $this->m_oEmailOptions->collateAllFormInputsForAllOptions();
		$sAllFormInputOptions = $sMainOptions.(ICWP_OptionsHandler_Base_Wpsf::CollateSeparator).$sEmailMainOptions;

		$aData = array(
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$aData['aSummaryData'] = $this->getDashboardSummaryDisplayData();

		if ( $this->getIsMainFeatureEnabled('firewall') ) {
			$this->loadOptionsHandler( 'Firewall' );
			$aData['aFirewallOptions'] = $this->m_oFirewallOptions->getPluginOptionsValues();
		}
		if ( $this->getIsMainFeatureEnabled('login_protect') ) {
			$this->loadOptionsHandler( 'LoginProtect' );
			$aData['aLoginProtectOptions'] = $this->m_oLoginProtectOptions->getPluginOptionsValues();
		}
		if ( $this->getIsMainFeatureEnabled('comments_filter') ) {
			$this->loadOptionsHandler( 'CommentsFilter' );
			$aData['aCommentsFilterOptions'] = $this->m_oCommentsFilterOptions->getPluginOptionsValues();
		}
		if ( $this->getIsMainFeatureEnabled('lockdown') ) {
			$this->loadOptionsHandler( 'Lockdown' );
			$aData['aLockdownOptions'] = $this->m_oLockdownOptions->getPluginOptionsValues();
		}
		if ( $this->getIsMainFeatureEnabled('autoupdates') ) {
			$this->loadOptionsHandler( 'AutoUpdates' );
			$aData['aAutoUpdatesOptions'] = $this->m_oAutoUpdatesOptions->getPluginOptionsValues();
		}
		$this->display( $this->doPluginPrefix( 'index', '_' ), $aData );
	}

	protected function getDashboardSummaryDisplayData() {

		$aSummaryData = array();
		$aSummaryData[] = array(
			$this->m_oPluginMainOptions->getOpt( 'enable_admin_access_restriction' ) == 'Y',
			_wpsf__('Admin Access Protection'),
			$this->getSubmenuId()
		);

		$aSummaryData[] = array(
			$this->getIsMainFeatureEnabled('firewall'),
			_wpsf__('Firewall'),
			$this->getSubmenuId( 'firewall' )
		);

		$aSummaryData[] = array(
			$this->getIsMainFeatureEnabled('login_protect'),
			_wpsf__('Login Protection'),
			$this->getSubmenuId( 'login_protect' )
		);

		$aSummaryData[] = array(
			$this->getIsMainFeatureEnabled('comments_filter'),
			_wpsf__('Comments Filter'),
			$this->getSubmenuId( 'comments_filter' )
		);

		$aSummaryData[] = array(
			$this->getIsMainFeatureEnabled('autoupdates'),
			_wpsf__('Auto Updates'),
			$this->getSubmenuId( 'autoupdates' )
		);

		$aSummaryData[] = array(
			$this->getIsMainFeatureEnabled('lockdown'),
			_wpsf__('Lock Down'),
			$this->getSubmenuId( 'lockdown' )
		);

		return $aSummaryData;
	}

	protected function onDisplayPrivacyProtectLog() {

		$this->loadProcessor( 'PrivacyProtect' );
		$aData = array(
			'urlrequests_log'	=> $this->m_oPrivacyProtectProcessor->getLogs( true )
		);
		$aData = array_merge( $this->getBaseDisplayData('privacy_protect_log'), $aData );
		$this->display( 'icwp_wpsf_privacy_protect_log_index', $aData );
	}

	protected function onDisplayFirewallLog() {

		$this->loadOptionsHandler( 'Firewall' );
		$aIpWhitelist = $this->m_oFirewallOptions->getOpt( 'ips_whitelist' );
		$aIpBlacklist = $this->m_oFirewallOptions->getOpt( 'ips_blacklist' );
		$this->loadProcessor( 'Logging' );

		$aLogData = $this->m_oLoggingProcessor->getLogs( true );
		$aData = array(
			'firewall_log'		=> $aLogData,
			'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
			'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
		);
		$aData = array_merge( $this->getBaseDisplayData('firewall_log'), $aData );
		$this->display( 'icwp_wpsf_firewall_log_index', $aData );
	}

	/**
	 *
	 * @param ICWP_OptionsHandler_Base_WPSF $inoOptions
	 * @param string $sSlug
	 */
	protected function onDisplayConfig( $inoOptions, $sSlug ) {
		$aAvailableOptions = $inoOptions->getOptions();
		$sAllFormInputOptions = $inoOptions->collateAllFormInputsForAllOptions();

		$aData = array(
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
		);
		$aData = array_merge( $this->getBaseDisplayData($sSlug), $aData );
		$this->display( 'icwp_wpsf_config_'.$sSlug.'_index', $aData );
	}

	/**
	 * @return boolean
	 */
	protected function isIcwpPluginFormSubmit() {
		if ( empty($_POST) && empty($_GET) ) {
			return false;
		}

		$aFormSubmitOptions = array(
			'icwp_plugin_form_submit',
			'icwp_link_action',
			'icwp_wpsf_admin_access_key_request'
		);
		foreach( $aFormSubmitOptions as $sOption ) {
			if ( !is_null( $this->fetchRequest( $sOption, false ) ) ) {
				return true;
			}
		}
		return false;
	}

	protected function handlePluginFormSubmit() {
		if ( !is_null( $this->fetchPost( $this->doPluginPrefix( 'admin_access_key_request', '_' ) ) ) ) {
			return $this->handleSubmit_AccessKeyRequest();
		}

		if ( !$this->hasPermissionToSubmit() || !$this->isIcwpPluginFormSubmit() ) {
			return false;
		}

		// Force run automatic updates
		if ( $this->fetchGet( 'force_run_auto_updates' ) == 'now' ) {
			$oProc = $this->getProcessor_Autoupdates();
			$oProc->setForceRunAutoUpdates( true );
			return;
		}

		// If we're dealing with a standard options form submit
		$aInputOptions = $this->fetchPost( $this->doPluginPrefix('all_options_input', '_') );
		if ( !empty( $aInputOptions ) ) {
			check_admin_referer( $this->getPluginPrefix() );
		}

		// When they've clicked to terminate all logged in authenticated users.
		if ( $this->fetchPost( 'terminate-all-logins' ) ) {
			$oProc = $this->getProcessor_LoginProtect();
			$oProc->doTerminateAllVerifiedLogins();
			return;
		}

		// Import from Firewall2 Plugin
		if ( $this->fetchPost( 'import-wpf2-submit') ) {
			$this->importFromFirewall2Plugin();
			return;
		}

		$sCurrentPage = $this->getCurrentWpAdminPage();
		if ( !empty( $sCurrentPage ) ) {
			// if it's the main dashboard, or one of the main features, load everything and save them
			if ( $this->getIsPage_PluginAdmin() ) {
				$this->loadOptionsHandler( 'all' );
				do_action( 'icwp_wpsf_form_submit', $aInputOptions );
				$this->saveOptions();
				$this->clearCaches();

				if ( $this->getIsPage_PluginMainDashboard() && !$this->fetchPost( $this->doPluginPrefix('enable_admin_access_restriction', '_') ) ) {
					$this->setPermissionToSubmit( false );
				}

				wp_safe_redirect(  $this->getUrl_PluginDashboard( $sCurrentPage ) );
				return true;
			}

			switch ( $sCurrentPage ) {
				case $this->getSubmenuId( 'firewall_log' ):
					$this->handleSubmit_FirewallLog();
					break;
				case $this->getSubmenuId( 'privacy_protect_log' ):
					$this->handleSubmit_PrivacyProtectLog();
					break;
				default:
					return false;
					break;
			}
		}
	}

	/**
	 * @param bool $infPermission
	 */
	protected function setPermissionToSubmit( $infPermission = false ) {
		if ( $infPermission ) {
			$this->loadDataProcessor();
			$sValue = md5( $this->m_oPluginMainOptions->getOpt( 'admin_access_key' ).ICWP_WPSF_DataProcessor::GetVisitorIpAddress() );
			$sTimeout = $this->m_oPluginMainOptions->getOpt( 'admin_access_timeout' ) * 60;
			$_COOKIE[ self::AdminAccessKeyCookieName ] = $sValue;
			setcookie( self::AdminAccessKeyCookieName, $sValue, time()+$sTimeout, COOKIEPATH, COOKIE_DOMAIN, false );
		}
		else {
			unset( $_COOKIE[ self::AdminAccessKeyCookieName ] );
			setcookie( self::AdminAccessKeyCookieName, "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 * @return boolean
	 */
	protected function hasPermissionToSubmit() {

		if ( !is_null( $this->fAdminAccessPermSubmit ) ) {
			return $this->fAdminAccessPermSubmit;
		}

		$this->fAdminAccessPermSubmit = true;
		if ( !parent::hasPermissionToSubmit() ) {
			$this->fAdminAccessPermSubmit = false;
		}
		if ( $this->fAdminAccessPermSubmit && $this->m_oPluginMainOptions->getOpt( 'enable_admin_access_restriction' ) == 'Y' ) {
			$sAccessKey = $this->m_oPluginMainOptions->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				$this->loadDataProcessor();
				$sHash = md5( $sAccessKey.ICWP_WPSF_DataProcessor::GetVisitorIpAddress() );
				$this->fAdminAccessPermSubmit = isset( $_COOKIE[ self::AdminAccessKeyCookieName ] ) && ( $sHash == $_COOKIE[ self::AdminAccessKeyCookieName ] );
			}
		}
		return $this->fAdminAccessPermSubmit;
	}

	protected function handleSubmit_AccessKeyRequest() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getPluginPrefix() );

		$sAccessKey = md5( trim( $this->fetchPost( $this->doPluginPrefix('admin_access_key_request', '_') ) ) );
		$sStoredAccessKey = $this->m_oPluginMainOptions->getOpt( 'admin_access_key' );

		if ( $sAccessKey === $sStoredAccessKey ) {
			$this->setPermissionToSubmit( true );
			header( 'Location: '.$this->getUrl_PluginDashboard( sanitize_text_field( $this->fetchPost('icwp_wpsf_requested_page') ) ) );
			exit();
		}
		return false;
	}

	/**
	 * Right before a plugin option is due to update it will check that we have permissions to do so and if not, will
	 * revert the option to save to the previous one.
	 *
	 * @param $mValue
	 * @param $sOption
	 * @param $mOldValue
	 * @return mixed
	 */
	public function blockOptionsSaves( $mValue, $sOption, $mOldValue ) {
		if ( !preg_match( '/^'.self::$sOptionPrefix.'.*_options$/', $sOption ) || $this->fHasFtpOverride ) {
			return $mValue;
		}
		return $this->hasPermissionToSubmit()? $mValue : $mOldValue;
	}

	protected function handleSubmit_FirewallLog() {

		// Ensures we're actually getting this request from a valid WP submission.
		$sNonce = $this->fetchRequest( '_wpnonce', false );
		if ( is_null( $sNonce ) || !wp_verify_nonce( $sNonce, $this->getSubmenuId( 'firewall_log' ) ) ) {
			wp_die();
		}

		$this->loadOptionsHandler( 'Firewall' );

		// At the time of writing the page only has 1 form submission item - clear log
		if ( !is_null( $this->fetchPost( 'clear_log_submit' ) ) ) {
			$this->loadProcessor( 'Logging' );
			$this->m_oLoggingProcessor->recreateTable();
		}
		else {
			$this->m_oFirewallOptions->addRawIpsToFirewallList( 'ips_whitelist', array( $this->fetchGet( 'whiteip' ) ) );
			$this->m_oFirewallOptions->removeRawIpsFromFirewallList( 'ips_whitelist', array( $this->fetchGet( 'unwhiteip' ) ) );
			$this->m_oFirewallOptions->addRawIpsToFirewallList( 'ips_blacklist', array( $this->fetchGet( 'blackip' ) ) );
			$this->m_oFirewallOptions->removeRawIpsFromFirewallList( 'ips_blacklist', array( $this->fetchGet( 'unblackip' ) ) );
			$this->resetProcessor( 'Firewall' );
		}
		wp_safe_redirect( $this->getUrl_PluginDashboard( 'firewall_log' ) ); //means no admin message is displayed
		exit();
	}

	protected function handleSubmit_PrivacyProtectLog() {

		// Ensures we're actually getting this request from a valid WP submission.
		$sNonce = $this->fetchRequest( '_wpnonce', false );
		if ( is_null( $sNonce ) || !wp_verify_nonce( $sNonce, $this->getSubmenuId( 'privacy_protect_log' ) ) ) {
			wp_die();
		}

		$this->loadOptionsHandler( 'PrivacyProtect' );

		// At the time of writing the page only has 1 form submission item - clear log
		if ( !is_null( $this->fetchPost( 'clear_log_submit' ) ) ) {
			$this->loadProcessor( 'PrivacyProtect' );
			$this->m_oPrivacyProtectProcessor->recreateTable();
		}
		else {
//			$this->m_oFirewallOptions->addRawIpsToFirewallList( 'ips_whitelist', array( $this->fetchGet( 'whiteip' ) ) );
//			$this->m_oFirewallOptions->removeRawIpsFromFirewallList( 'ips_whitelist', array( $this->fetchGet( 'unwhiteip' ) ) );
//			$this->m_oFirewallOptions->addRawIpsToFirewallList( 'ips_blacklist', array( $this->fetchGet( 'blackip' ) ) );
//			$this->m_oFirewallOptions->removeRawIpsFromFirewallList( 'ips_blacklist', array( $this->fetchGet( 'unblackip' ) ) );
//			$this->resetProcessor( 'Firewall' );
		}
		wp_safe_redirect( $this->getUrl_PluginDashboard( 'privacy_protect_log' ) ); //means no admin message is displayed
		exit();
	}

	protected function importFromFirewall2Plugin() {
		$this->loadOptionsHandler( 'all' );
		require_once( dirname(__FILE__).'/src/icwp-import-wpf2-processor.php' );
		$oImportProcessor = new ICWP_ImportWpf2Processor( $this->m_oPluginMainOptions, $this->m_oFirewallOptions );
		$oImportProcessor->runImport();
	}

	public function onWpPluginsLoaded() {
		parent::onWpPluginsLoaded();

		$aFeatures = $this->getFeaturesMap();
		foreach( $aFeatures as $sFeatureSlug => $sProcessor ) {
			if ( !$this->getIsMainFeatureEnabled( $sFeatureSlug ) ) {
				continue;
			}
			if ( $sFeatureSlug == 'firewall' ) {
				$this->runFirewallProcess();
			}
			else if ( $sFeatureSlug == 'login_protect' ) {
				$this->runLoginProtect();
			}
			else if ( $sFeatureSlug == 'autoupdates' ) {
				$this->runAutoUpdates();
			}
			else {
				$sProcessorVariable = $this->loadProcessor( $sProcessor );
				$sProcessorVariable->run();
			}
		}

		if ( $this->isValidAdminArea()
				&& $this->m_oPluginMainOptions->getOpt('enable_upgrade_admin_notice') == 'Y'
				&& $this->hasPermissionToSubmit()
			) {
			$this->m_fDoAutoUpdateCheck = true;
		}
	}

	public function onWpAdminInit() {
		parent::onWpAdminInit();

		if ( $this->isValidAdminArea() ) {
			//Someone clicked the button to acknowledge the update
			$sMetaFlag = $this->doPluginPrefix( 'hide_update_notice' );
			if ( $this->fetchRequest( $sMetaFlag ) == 1 ) {
				$this->updateVersionUserMeta();
				if ( $this->isShowMarketing() ) {
					wp_redirect( $this->getUrl_PluginDashboard() );
				}
				else {
					wp_redirect( network_admin_url( $_POST['redirect_page'] ) );
				}
			}

			$sMetaFlag = $this->doPluginPrefix( 'hide_translation_notice' );
			if ( $this->fetchRequest( $sMetaFlag ) == 1 ) {
				$this->updateTranslationNoticeShownUserMeta();
				wp_redirect( network_admin_url( $_POST['redirect_page'] ) );
			}

			$sMetaFlag = $this->doPluginPrefix( 'hide_mailing_list_signup' );
			if ( $this->fetchRequest( $sMetaFlag ) == 1 ) {
				$this->updateMailingListSignupShownUserMeta();
			}
		}
	}

	/**
	 * @return bool
	 */
	protected function isShowMarketing() {
		// don't show marketing on the first 24hrs.
		if ( $this->getInstallationDays() < 1 ) {
			return false;
		}
		return parent::isShowMarketing();
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 *
	 * @see ICWP_Pure_Base_V1::removePluginConflicts()
	 */
	protected function removePluginConflicts() {
		if ( class_exists('AIO_WP_Security') && isset( $GLOBALS['aio_wp_security'] ) ) {
			remove_action( 'init', array( $GLOBALS['aio_wp_security'], 'wp_security_plugin_init'), 0 );
		}
	}

	/**
	 * Updates the current log data with new data.
	 *
	 * @return void
	 */
	protected function updateLogStore() {

		if ( isset( $this->m_oFirewallProcessor ) && is_object( $this->m_oFirewallProcessor ) && $this->getIsMainFeatureEnabled( 'firewall' ) ) {
			$aLogData = $this->m_oFirewallProcessor->flushLogData();
			if ( !is_null( $aLogData ) && !empty( $aLogData ) ) {
				$this->loadProcessor( 'Logging' );
				$this->m_oLoggingProcessor->addDataToWrite( $aLogData );
			}
		}

		if ( isset( $this->m_oLoginProtectProcessor ) && is_object( $this->m_oLoginProtectProcessor ) && $this->getIsMainFeatureEnabled( 'login_protect' ) ) {
			$aLogData = $this->m_oLoginProtectProcessor->flushLogData();
			if ( !is_null( $aLogData ) && !empty( $aLogData ) ) {
				$this->loadProcessor( 'Logging' );
				$this->m_oLoggingProcessor->addDataToWrite( $aLogData );
			}
		}
	}

	protected function shutdown() {
		$this->updateLogStore();
		parent::shutdown();
	}

	protected function getPluginsListUpdateMessage() {
		return _wpsf__( 'Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features.' );
	}

	protected function getAdminNoticeHtml_Translations() {

		if ( $this->getInstallationDays() < 7 ) {
			return '';
		}

		$sMetaFlag = $this->doPluginPrefix( 'hide_translation_notice' );

		$sRedirectPage = 'index.php';
		ob_start(); ?>
			<style>
				a#fromIcwp { padding: 0 5px; border-bottom: 1px dashed rgba(0,0,0,0.1); color: blue; font-weight: bold; }
			</style>
			<form id="IcwpTranslationsNotice" method="post" action="admin.php?page=<?php echo $this->getSubmenuId('firewall'); ?>&<?php echo $sMetaFlag; ?>=1">
				<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
				<input type="hidden" value="1" name="<?php echo $sMetaFlag; ?>" id="<?php echo $sMetaFlag; ?>">
				<h4 style="margin:10px 0 3px;">
					<?php _wpsf_e( 'Would you like to help translate the WordPress Simple Firewall into your language?' ); ?>
					<?php printf( _wpsf__( 'Head over to: %s' ), '<a href="http://translate.icontrolwp.com" target="_blank">translate.icontrolwp.com</a>' ); ?>
				</h4>
				<input type="submit" value="<?php _wpsf_e( 'Dismiss this notice' ); ?>" name="submit" class="button" style="float:left; margin-bottom:10px;">
				<div style="clear:both;"></div>
			</form>
		<?php
		$sNotice = ob_get_contents();
		ob_end_clean();
		return $sNotice;
	}

	protected function getAdminNoticeHtml_VersionUpgrade() {

		// for now just showing this for the first 3 days of installation.
		if ( $this->getInstallationDays() > 7 ) {
			return '';
		}

		$sMetaFlag = $this->doPluginPrefix( 'hide_update_notice' );

		$sRedirectPage = 'admin.php?page=icwp-wpsf';
		ob_start(); ?>
			<style>a#fromIcwp { padding: 0 5px; border-bottom: 1px dashed rgba(0,0,0,0.1); color: blue; font-weight: bold; }</style>
			<form id="IcwpUpdateNotice" method="post" action="admin.php?page=<?php echo $this->getSubmenuId('firewall'); ?>&<?php echo $sMetaFlag; ?>=1">
				<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
				<input type="hidden" value="1" name="<?php echo $sMetaFlag; ?>" id="<?php echo $sMetaFlag; ?>">
				<p>
					<?php _wpsf_e( 'Note: WordPress Simple Firewall plugin does not automatically turn on when you install/update.' ); ?>
					<?php printf( _wpsf__( 'There may also be %simportant updates to read about%s.' ), '<a href="http://icwp.io/27" id="fromIcwp" title="'._wpsf__( 'WordPress Simple Firewall' ).'" target="_blank">', '</a>' ); ?>
				</p>
				</h4>
				<input type="submit" value="<?php _wpsf_e( 'Okay, show me the dashboard' ); ?>" name="submit" class="button" style="float:left; margin-bottom:10px;">
				<div style="clear:both;"></div>
			</form>
		<?php
		$sNotice = ob_get_contents();
		ob_end_clean();
		return $sNotice;
	}

	/**
	 * @return string|void
	 */
	protected function getAdminNoticeHtml_MailingListSignup() {

		$nDays = $this->getInstallationDays();
		if ( $nDays < 2 ) {
			return '';
		}
		$sMetaFlag = $this->doPluginPrefix( 'hide_mailing_list_signup' );

		ob_start(); ?>
		<!-- Begin MailChimp Signup Form -->
		<div id="mc_embed_signup">
			<form class="form form-inline" action="http://hostliketoast.us2.list-manage1.com/subscribe/post?u=e736870223389e44fb8915c9a&amp;id=0e1d527259" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
				<p>The WordPress Simple Firewall team has launched a education initiative to raise awareness of WordPress security and to provide further help with the WordPress Simple Firewall plugin. Get Involved here:</p>
				<input type="text" value="" name="EMAIL" class="required email" id="mce-EMAIL" placeholder="Your Email" />
				<input type="text" value="" name="FNAME" class="" id="mce-FNAME" placeholder="Your Name" />
				<input type="hidden" value="<?php echo $nDays; ?>" name="DAYS" class="" id="mce-DAYS" />
				<input type="submit" value="Get The News" name="subscribe" id="mc-embedded-subscribe" class="button" />
				<a href="<?php echo $this->getUrl_PluginDashboard().'&'.$sMetaFlag.'=1';?>">Dismiss</a>
				<div id="mce-responses" class="clear">
					<div class="response" id="mce-error-response" style="display:none"></div>
					<div class="response" id="mce-success-response" style="display:none"></div>
				</div>    <!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
				<div style="position: absolute; left: -5000px;"><input type="text" name="b_e736870223389e44fb8915c9a_0e1d527259" tabindex="-1" value=""></div>
				<div class="clear"></div>
			</form>
		</div>

		<!--End mc_embed_signup-->
		<?php
		$sNotice = ob_get_contents();
		ob_end_clean();
		return $sNotice;
	}

	protected function getAdminNoticeHtml_OptionsUpdated() {
		$sAdminFeedbackNotice = $this->m_oPluginMainOptions->getOpt( 'feedback_admin_notice' );
		if ( !empty( $sAdminFeedbackNotice ) ) {
			$sNotice = '<p>'.$sAdminFeedbackNotice.'</p>';
			return $sNotice;
			$this->m_oPluginMainOptions->setOpt( 'feedback_admin_notice', '' );
		}
	}

	/**
	 *
	 */
	protected function getShowAdminNotices() {
		return $this->m_oPluginMainOptions->getOpt('enable_upgrade_admin_notice') == 'Y';
	}

	/**
	 * @return int
	 */
	protected function getInstallationDays() {
		$nTimeInstalled = $this->m_oPluginMainOptions->getOpt( 'installation_time' );
		if ( empty($nTimeInstalled) ) {
			return 0;
		}
		return round( ( time() - $nTimeInstalled ) / DAY_IN_SECONDS );
	}

	protected function getAdminBarNodes() {
		return array(); //disabled for now
		$aMenu = array(
			'id'	=> self::$sOptionPrefix.'admin_menu',
			'title'	=> '<span class="pluginlogo_16">&nbsp;</span>'._wpsf__('Firewall').'',
			'href'	=> 'bob',
		);
		return array( $aMenu );
	}

	/**
	 * @return ICWP_WPSF_LoginProtectProcessor|null
	 */
	public function getProcessor_LoginProtect() {
		return $this->getProcessorVar('LoginProtect');
	}

	/**
	 * @return ICWP_WPSF_AutoUpdatesProcessor|null
	 */
	public function getProcessor_Autoupdates() {
		return $this->getProcessorVar('AutoUpdates');
	}
}

endif;

//$oICWP_Wpsf = ICWP_Wordpress_Simple_Firewall::GetInstance( 'ICWP_Wordpress_Simple_Firewall' );
