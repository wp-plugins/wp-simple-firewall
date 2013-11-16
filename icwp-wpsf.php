<?php
/*
 * Plugin Name: WordPress Simple Firewall
 * Plugin URI: http://icwp.io/2f
 * Description: A Simple WordPress Firewall
 * Version: 2.1.2
 * Text Domain: wp-simple-firewall
 * Author: iControlWP
 * Author URI: http://icwp.io/2e
 */

/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
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

require_once( dirname(__FILE__).'/src/icwp-pure-base.php' );
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

class ICWP_Wordpress_Simple_Firewall extends ICWP_Pure_Base_V1 {

	/**
	 * Should be updated each new release.
	 * @var string
	 */
	const PluginVersion					= '2.1.2';  //SHOULD BE UPDATED UPON EACH NEW RELEASE
	/**
	 * Should be updated each new release.
	 * @var string
	 */
	const PluginTextDomain				= 'wp-simple-firewall';  //SHOULD BE UPDATED UPON EACH NEW RELEASE
	/**
	 * Should be updated each new release.
	 * @var string
	 */
	const PluginSlug					= 'wpsf';	//ALL database options use this as the prefix.
	/**
	 * @var string
	 */
	const AdminAccessKeyCookieName		= 'icwp_wpsf_aakcook';
	
	/**
	 * @var ICWP_OptionsHandler_Wpsf
	 */
	protected $m_oPluginMainOptions;

	/**
	 * @var ICWP_OptionsHandler_Firewall
	 */
	protected $m_oFirewallOptions;

	/**
	 * @var ICWP_OptionsHandler_LoginProtect
	 */
	protected $m_oLoginProtectOptions;

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
	 * @var ICWP_AutoUpdatesProcessor
	 */
	protected $m_oAutoUpdatesProcessor;
	
	/**
	 * @var ICWP_LoggingProcessor
	 */
	protected $m_oLoggingProcessor;
	
	/**
	 * @var ICWP_EmailProcessor
	 */
	protected $m_oEmailProcessor;
	
	public function __construct() {
		
		$this->m_fNetworkAdminOnly = true;
		$this->m_sPluginRootFile = __FILE__; //ensure all relative paths etc. are setup.
		parent::__construct();
		
		$this->m_sVersion				= self::PluginVersion;
		$this->m_sPluginHumanName		= "WordPress Simple Firewall";
		$this->m_sPluginTextDomain		= self::PluginTextDomain;
		$this->m_sPluginMenuTitle		= "Simple Firewall";
		$this->m_sOptionPrefix			= sprintf( '%s_%s_', self::BaseSlug, self::PluginSlug );
		$this->m_sParentMenuIdSuffix	= self::PluginSlug;

		// loads the base plugin options from 1 db call
		$this->loadOptionsHandler( 'PluginMain' );
		$this->m_fAutoPluginUpgrade = false && $this->m_oPluginMainOptions->getOpt( 'enable_auto_plugin_upgrade' ) == 'Y';

		// checks for filesystem based firewall overrides
		$this->override();

		if ( isset( $_GET['turnoffperm'] ) ) {
			$this->setPermissionToSubmit( false );
		}
	}

	/**
	 * @param array $aPlugins
	 * @return unknown
	 */
	public function hide_plugin( $inaPlugins ) {
		foreach ( $inaPlugins as $sSlug => $aData ) {
			if ( strpos( $sSlug, 'icwp-wpsf.php' ) !== false ) {
				unset( $inaPlugins[$sSlug] );
			}
		}
		return $inaPlugins;
	}
	
	protected function override() {
		if ( is_file( path_join($this->m_sPluginDir, 'forceOff') ) ) {
			$this->setSharedOption( 'enable_firewall', 'N' );
			$this->setSharedOption( 'enable_login_protect', 'N' );
			$this->setSharedOption( 'enable_comments_filter', 'N' );
			$this->setSharedOption( 'enable_autoupdates', 'N' );
			$this->setSharedOption( 'enable_admin_access_restriction', 'N' );
		}
		else if ( is_file( path_join($this->m_sPluginDir, 'forceOn') ) ) {
			$this->setSharedOption( 'enable_firewall', 'Y' );
			$this->setSharedOption( 'enable_login_protect', 'Y' );
			$this->setSharedOption( 'enable_comments_filter', 'Y' );
			$this->setSharedOption( 'enable_autoupdates', 'Y' );
			$this->setSharedOption( 'enable_admin_access_restriction', 'Y' );
		}
		else {
			return true;
		}
		$this->resetFirewallProcessor();
		$this->resetLoginProtectProcessor();
	}
	
	protected function genSecretKey() {
		$sKey = $this->m_oPluginMainOptions->getOpt( 'secret_key' );
		if ( empty( $sKey ) ) {
			$sKey = md5( mt_rand() );
			$this->m_oPluginMainOptions->setOpt( 'secret_key', $sKey );
		}
		return $sKey;
	}
	
	protected function getFeaturesMap() {
		return array(
			'firewall'			=> 'Firewall',
			'login_protect'		=> 'LoginProtect',
			'comments_filter'	=> 'CommentsFilter',
			'lockdown'			=> 'Lockdown',
			'autoupdates'		=> 'AutoUpdates'
		);
	}
	
	/**
	 * @param string $insFeature	- firewall, login_protect, comments_filter, lockdown
	 * @return boolean
	 */
	public function getIsMainFeatureEnabled( $insFeature ) {
		
		if ( is_file( $this->m_sPluginPath . 'forceOff' ) ) {
			return false;
		}
		else if ( is_file( $this->m_sPluginPath . 'forceOn' ) ) {
			return true;
		}
		
		$aFeatures = $this->getFeaturesMap();
		
		switch ( $insFeature ) {
			case 'admin_access':
				$fEnabled = $this->m_oPluginMainOptions->getOpt( 'enable_admin_access_restriction' ) == 'Y';
				break;
			default:
				if ( array_key_exists( $insFeature, $aFeatures ) ) {
					$fEnabled = $this->m_oPluginMainOptions->getOpt( 'enable_'.$insFeature ) == 'Y';
				}
				else {
					$fEnabled = false;
				}
				break;
		}
		return $fEnabled;
	}
	
	/**
	 * This is necessary because we store these values in several places and we need to always keep it in sync.
	 * 
	 * @param string $sFeature
	 * @param boolean $infEnabled
	 * @return boolean
	 */
	public function setSharedOption( $insOption, $inmValue ) {

		$aFeatures = $this->getFeaturesMap();
		
		$sFeature = str_replace( 'enable_', '', $insOption );
		if ( !array_key_exists( $sFeature, $aFeatures ) ) {
			return;
		}
		
		$this->loadOptionsHandler( $aFeatures[$sFeature] );
		$sOptions = 'm_o'.$aFeatures[$sFeature].'Options';// e.g. m_oFirewallOptions
		$this->{$sOptions}->setOpt( $insOption, $inmValue );
		$this->m_oPluginMainOptions->setOpt( $insOption, $inmValue );
	}
	
	/**
	 * Updates the current log data with new data.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	protected function updateLogStore() {

		if ( isset( $this->m_oFirewallProcessor ) && is_object( $this->m_oFirewallProcessor ) && $this->getIsMainFeatureEnabled( 'firewall' ) ) {
			$aLogData = $this->m_oFirewallProcessor->flushLogData();
			if ( !is_null( $aLogData ) && !empty( $aLogData ) ) {
				$this->loadProcessor( 'Logging' );
				$this->m_oLoggingProcessor->writeLog( $aLogData );
			}
		}

		if ( isset( $this->m_oLoginProtectProcessor ) && is_object( $this->m_oLoginProtectProcessor ) && $this->getIsMainFeatureEnabled( 'login_protect' ) ) {
			$aLogData = $this->m_oLoginProtectProcessor->flushLogData();
			if ( !is_null( $aLogData ) && !empty( $aLogData ) ) {
				$this->loadProcessor( 'Logging' );
				$this->m_oLoggingProcessor->writeLog( $aLogData );
			}
		}
	}
	
	protected function loadOptionsHandler( $insOptionHandler, $infFullBuild = false ) {

		$aAllHandlers = array_values( $this->getFeaturesMap() );
		$aAllHandlers[] = 'PluginMain';
		
		// special case
		if ( $insOptionHandler == 'all' ) {
			foreach( $aAllHandlers as $sHandler ) {
				$fSuccess = $this->loadOptionsHandler( $sHandler, $infFullBuild );
			}
			return $fSuccess;
		}
		
		if ( !in_array( $insOptionHandler, $aAllHandlers ) ) {
			return false;
		}
		
		$sOptionsVarName = 'm_o'.$insOptionHandler.'Options'; // e.g. m_oPluginMainOptions
		$sSourceFile = dirname(__FILE__).'/src/icwp-optionshandler-'.strtolower($insOptionHandler).'.php'; // e.g. icwp-optionshandler-wpsf.php

		if ( $insOptionHandler == 'PluginMain' ) {
			$sClassName = 'ICWP_OptionsHandler_'.ucfirst( self::PluginSlug ); // e.g. ICWP_OptionsHandler_Wpsf
		}
		else {
			$sClassName = 'ICWP_OptionsHandler_'.$insOptionHandler; // e.g. ICWP_OptionsHandler_Wpsf
		}
		
		require_once( $sSourceFile );
		if ( !isset( $this->{$sOptionsVarName} ) ) {
		 	$this->{$sOptionsVarName} = new $sClassName( $this->m_sOptionPrefix, $this->m_sVersion, $infFullBuild );
		}
		if ( $infFullBuild ) {
			$this->{$sOptionsVarName}->buildOptions();
		}
		return true;
	}
	
	protected function loadProcessor( $insProcessorName, $infReset = false ) {

		$aAllProcessors = $this->getFeaturesMap();
		$aAllProcessors['logging'] = 'Logging';
		$aAllProcessors['email'] = 'Email';

		if ( !in_array( $insProcessorName, array_values($aAllProcessors) ) ) {
			wp_die( 'Processor is not permitted here.' );
		}
		
		$sProcessorVarName = 'm_o'.$insProcessorName.'Processor'; // e.g. m_oFirewallProcessor
		$sSourceFile = dirname(__FILE__).'/src/icwp-processor-'.strtolower($insProcessorName).'.php'; // e.g. icwp-optionshandler-wpsf.php
		$sClassName = 'ICWP_'.$insProcessorName.'Processor'; // e.g. ICWP_FirewallProcessor
		$sStorageKey = array_search($insProcessorName, $aAllProcessors).'_processor'; // e.g. firewall_processor
		$sOptionsHandlerVarName = 'm_o'.$insProcessorName.'Options'; // e.g. m_oFirewallOptions
		
		require_once( $sSourceFile );
		if ( empty( $this->{$sProcessorVarName} ) ) {
			$this->{$sProcessorVarName} = $this->getOption( $sStorageKey );
			if ( is_object( $this->{$sProcessorVarName} ) && ( $this->{$sProcessorVarName} instanceof $sClassName ) ) {
				$this->{$sProcessorVarName}->reset();
			}
			else {
				$this->{$sProcessorVarName} = new $sClassName( $this->m_sOptionPrefix );
				// Also loads the options handler where appropriate
				if ( $this->loadOptionsHandler( $insProcessorName ) ) {
					$this->{$sProcessorVarName}->setOptions( $this->{$sOptionsHandlerVarName}->getPluginOptionsValues() );
				}
			}
		}
		else if ( $infReset ) {
			$this->{$sProcessorVarName}->reset();
		}
		// Now we handle any custom processor stuff
		if ( $insProcessorName == 'LoginProtect' ) {
			$this->m_oLoginProtectProcessor->setSecretKey( $this->genSecretKey() );
		}
		else if ( $insProcessorName == 'Email' ) {
			$this->m_oEmailProcessor->setDefaultRecipientAddress( $this->m_oPluginMainOptions->getOpt( 'block_send_email_address' ) );
			$this->m_oEmailProcessor->setThrottleLimit( $this->m_oPluginMainOptions->getOpt( 'send_email_throttle_limit' ) );
			$sSiteName = function_exists( 'get_bloginfo' )? get_bloginfo('name') : '';
			$this->m_oEmailProcessor->setSiteName( $sSiteName );
		}
		return $this->{$sProcessorVarName};
	}
	
	/**
	 * Should be called from the constructor so as to ensure it is called as early as possible.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	public function runFirewallProcess() {

		$this->loadOptionsHandler('Firewall');
		if ( is_super_admin() && $this->m_oFirewallOptions->getOpt( 'whitelist_admins' ) == 'Y' ) {
			return;
		}

		$this->loadProcessor( 'Firewall' );
		$fFirewallBlockUser = !$this->m_oFirewallProcessor->doFirewallCheck();

		if ( $fFirewallBlockUser ) {

			if ( $this->m_oFirewallProcessor->getNeedsEmailHandler() ) {
				$this->loadProcessor( 'Email' );
				$this->m_oFirewallProcessor->setEmailHandler( $this->m_oEmailProcessor );
				$this->m_oFirewallProcessor->doPreFirewallBlock();
				$this->m_oEmailProcessor->store();
			}
			else {
				$this->m_oFirewallProcessor->doPreFirewallBlock();
			}
		}
		$this->updateLogStore();
		$this->m_oFirewallProcessor->store( $this->getOptionKey( 'firewall_processor' ) );
		
		if ( $fFirewallBlockUser ) {
			$this->m_oFirewallProcessor->doFirewallBlock();
		}
		
		unset( $this->m_oFirewallProcessor );
	}
	
	/**
	 * Handles the running of all Login Protection processes.
	 */
	public function runLoginProtect() {
		$this->loadProcessor( 'LoginProtect' );
		$this->m_oLoginProtectProcessor->run();

		// We don't want to load the email handler unless we really need it.
		// 29 is just before we'll need it if we do
		if ( $this->m_oLoginProtectProcessor->getNeedsEmailHandler() ) {
			$this->loadProcessor( 'Email' );
			$this->m_oLoginProtectProcessor->setEmailHandler( $this->m_oEmailProcessor );
		}
	}
	
	protected function getAllOptions() {
		$aOptionNames = array(
			'm_oPluginMainOptions',
			'm_oFirewallOptions',
			'm_oLoginProtectOptions',
			'm_oCommentsFilterOptions',
			'm_oLockdownOptions',
			'm_oAutoUpdatesOptions'
		);
		
		$this->loadOptionsHandler('all');
		$aOptions = array();
		foreach( $aOptionNames as $sName ) {
			if ( isset( $this->{$sName} ) ) {
				$aOptions[] = &$this->{$sName};
			}
		}
		return $aOptions;
	}
	
	public function getProcessor( $insProcessor ) {
		return $this->loadProcessor( $insProcessor );
	}
	
	protected function getAllProcessors() {
		$aProcessorNames = array(
			'firewall_processor'		=> 'm_oFirewallProcessor',
			'login_protect_processor'	=> 'm_oLoginProtectProcessor',
			'comments_filter_processor'	=> 'm_oCommentsFilterProcessor',
			'lockdown_processor'		=> 'm_oLockdownProcessor',
			'autoupdates_processor'		=> 'm_oAutoUpdatesProcessor',
			'logging_processor'			=> 'm_oLoggingProcessor',
			'email_processor'			=> 'm_oEmailProcessor'
		);
		$aProcessors = array();
		foreach( $aProcessorNames as $sKey => $sName ) {
			if ( isset( $this->{$sName} ) ) {
				$aProcessors[$sKey] = &$this->{$sName};
			}
		}
		return $aProcessors;
	}
	
	/**
	 * Make sure and cache the processors after all is said and done.
	 */
	public function saveProcessors_Action() {

		$this->updateLogStore();
		
		$aOptions = $this->getAllOptions();
		foreach( $aOptions as &$oOption ) {
			if ( isset( $oOption ) ) {
				$oOption->savePluginOptions();
			}
		}
		$aProcessors = $this->getAllProcessors();
		foreach( $aProcessors as $sKey => &$oProcessor ) {
			if ( is_object($oProcessor) ) {
				$oProcessor->store( $this->getOptionKey( $sKey ) );
			}
		}
	}

	public function onWpAdminInit() {
		parent::onWpAdminInit();

		// This is only done on WP Admin loads so as not to affect the front-end and only if the firewall is enabled
		if ( $this->getIsMainFeatureEnabled( 'firewall' ) ||  $this->getIsMainFeatureEnabled( 'login_protect' ) ) {
			$this->filterIpLists();
		}
	}
	
	protected function createPluginSubMenuItems() {
		if ( !$this->hasPermissionToView() ) {
			return;
		}
		$this->m_aPluginMenu = array(
			//Menu Page Title => Menu Item name, page ID (slug), callback function for this page - i.e. what to do/load.
			$this->getSubmenuPageTitle( 'Firewall' )		=> array( 'Firewall', $this->getSubmenuId('firewall'), 'onDisplayAll' ),
			$this->getSubmenuPageTitle( 'Login Protect' )	=> array( 'Login Protect', $this->getSubmenuId('login_protect'), 'onDisplayAll' ),
			$this->getSubmenuPageTitle( 'Comments Filter' )	=> array( 'Comments Filter', $this->getSubmenuId('comments_filter'), 'onDisplayAll' ),
			$this->getSubmenuPageTitle( 'Lockdown' )		=> array( 'Lockdown', $this->getSubmenuId('lockdown'), 'onDisplayAll' ),
			$this->getSubmenuPageTitle( 'Auto Updates' )	=> array( 'Auto Updates', $this->getSubmenuId('autoupdates'), 'onDisplayAll' ),
			$this->getSubmenuPageTitle( 'Log' )				=> array( 'Log', $this->getSubmenuId('firewall_log'), 'onDisplayAll' )
		);
	}

	protected function handlePluginUpgrade() {
		parent::handlePluginUpgrade();
		
		$sCurrentPluginVersion = $this->m_oPluginMainOptions->getOpt( 'current_plugin_version' );
		
		if ( $sCurrentPluginVersion !== $this->m_sVersion && current_user_can( 'manage_options' ) ) {
			$this->loadProcessor( 'Logging' );
			$this->m_oLoggingProcessor->handleInstallUpgrade( $sCurrentPluginVersion );
			
			// handles migration to new dedicated options system
			$this->loadOptionsHandler( 'all' );

			// clears all the processor caches
			$this->clearCaches();
			$this->deleteOption('login_processor');
			$this->deleteOption('comments_processor');
		}//if
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

		$sPrefix = str_replace(' ', '-', strtolower($this->m_sPluginMenuTitle) ) .'_page_'.self::BaseSlug.'-'.self::PluginSlug.'-';
		$sCurrent = str_replace( $sPrefix, '', current_filter() );
		
		switch( $sCurrent ) {
			case 'toplevel_page_'.self::BaseSlug.'-'.self::PluginSlug : //special case
				$this->onDisplayMainMenu();
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
	
	public function onDisplayAccessKeyRequest() {
		$aData = array(
			'nonce_field'		=> $this->getSubmenuId( 'wpsf-access-key' ),
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( 'icwp_wpsf_access_key_request_index', $aData );
	}
	
	public function onDisplayMainMenu() {

		// Just to ensure the nag bar disappears if/when they visit the dashboard
		// regardless of clicking the button.
		$this->updateVersionUserMeta();

		$this->loadOptionsHandler( 'all' );
		$aAvailableOptions = $this->m_oPluginMainOptions->getOptions();
		$sAllFormInputOptions = $this->m_oPluginMainOptions->collateAllFormInputsForAllOptions();
		
		$aData = array(
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );

		$aData['aMainOptions'] = $this->m_oPluginMainOptions->getPluginOptionsValues();
		
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
		$this->display( 'icwp_'.$this->m_sParentMenuIdSuffix.'_index', $aData );
	}
	
	protected function onDisplayFirewallLog() {

		$this->loadOptionsHandler( 'Firewall' );
		$aIpWhitelist = $this->m_oFirewallOptions->getOpt( 'ips_whitelist' );
		$aIpBlacklist = $this->m_oFirewallOptions->getOpt( 'ips_blacklist' );
		$this->loadProcessor( 'Logging' );

		$aData = array(
			'firewall_log'		=> $this->m_oLoggingProcessor->getLogs( true ),
			'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
			'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
		);
		$aData = array_merge( $this->getBaseDisplayData('firewall_log'), $aData );
		$this->display( 'icwp_wpsf_firewall_log_index', $aData );
	}
	
	/**
	 * 
	 * @param ICWP_OptionsHandler_Base_WPSF $inoOptions
	 * @param string $insSlug
	 */
	protected function onDisplayConfig( $inoOptions, $insSlug ) {
		
		$aAvailableOptions = $inoOptions->getOptions();
		$sAllFormInputOptions = $inoOptions->collateAllFormInputsForAllOptions();

		$aData = array(
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
		);
		$aData = array_merge( $this->getBaseDisplayData($insSlug), $aData );
		$this->display( 'icwp_wpsf_config_'.$insSlug.'_index', $aData );
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
			if ( isset( $_POST[$sOption] ) || isset( $_GET[$sOption] ) ) {
				return true;
			}
		}
		return false;
	}
	
	protected function handlePluginFormSubmit() {
		if ( isset( $_POST['icwp_wpsf_admin_access_key_request'] ) ) {
			return $this->handleSubmit_AccessKeyRequest();
		}
		
		if ( !$this->hasPermissionToSubmit() || !$this->isIcwpPluginFormSubmit() ) {
			return false;
		}
		
		if ( isset( $_GET['page'] ) ) {
			switch ( $_GET['page'] ) {
				case $this->getSubmenuId():
					$this->handleSubmit_Dashboard();
					break;
				case $this->getSubmenuId( 'firewall' ):
					$this->handleSubmit_FirewallConfig();
					break;
				case $this->getSubmenuId( 'login_protect' ):
					$this->handleSubmit_LoginProtect();
					break;
				case $this->getSubmenuId( 'comments_filter' ):
					$this->handleSubmit_CommentsFilter();
					break;
				case $this->getSubmenuId( 'lockdown' ):
					$this->handleSubmit_Lockdown();
					break;
				case $this->getSubmenuId( 'autoupdates' ):
					$this->handleSubmit_AutoUpdates();
					break;
				case $this->getSubmenuId( 'firewall_log' ):
					$this->handleSubmit_FirewallLog();
					break;
				default:
					return false;
					break;
			}
		}
		$this->resetLoggingProcessor();
		return true;
	}
	
	protected function setPermissionToSubmit( $infPermission = false ) {
		if ( $infPermission ) {
			$sValue = $this->m_oPluginMainOptions->getOpt( 'admin_access_key' );
			$sTimeout = $this->m_oPluginMainOptions->getOpt( 'admin_access_timeout' ) * 60;
			$_COOKIE[ self::AdminAccessKeyCookieName ] = 1;
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
		if ( !parent::hasPermissionToSubmit() ) {
			return false;
		}
		
		if ( $this->m_oPluginMainOptions->getOpt( 'enable_admin_access_restriction' ) == 'Y' ) {
			$sAccessKey = $this->m_oPluginMainOptions->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				return isset( $_COOKIE[ self::AdminAccessKeyCookieName ] );
			}
		}
		return true;
	}
	
	protected function handleSubmit_AccessKeyRequest() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('wpsf-access-key') );
		
		$this->loadOptionsHandler( 'PluginMain' );
		$sAccessKey = md5( trim( $_POST['icwp_wpsf_admin_access_key_request'] ) );
		$sStoredAccessKey = $this->m_oPluginMainOptions->getOpt( 'admin_access_key' );

		if ( $sAccessKey === $sStoredAccessKey ) {
			$this->setPermissionToSubmit( true );
			header( 'Location: '.admin_url('admin.php?page=icwp-wpsf') );
			exit();
		}
		return false;
	}
	
	protected function handleSubmit_Dashboard() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId() );

		if ( !isset($_POST[$this->m_sOptionPrefix.'all_options_input']) ) {
			return false;
		}

		$this->loadOptionsHandler( 'PluginMain' );
		$this->m_oPluginMainOptions->updatePluginOptionsFromSubmit( $_POST[$this->m_sOptionPrefix.'all_options_input'] );
		
		$this->setSharedOption( 'enable_firewall',			$this->m_oPluginMainOptions->getOpt( 'enable_firewall' ) );
		$this->setSharedOption( 'enable_login_protect',		$this->m_oPluginMainOptions->getOpt( 'enable_login_protect' ) );
		$this->setSharedOption( 'enable_comments_filter',	$this->m_oPluginMainOptions->getOpt( 'enable_comments_filter' ) );
		$this->setSharedOption( 'enable_lockdown',			$this->m_oPluginMainOptions->getOpt( 'enable_lockdown' ) );
		$this->setSharedOption( 'enable_autoupdates',		$this->m_oPluginMainOptions->getOpt( 'enable_autoupdates' ) );
		
		$this->clearCaches();
	}
	
	protected function handleSubmit_FirewallConfig() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId( 'firewall' ) );

		if ( isset($_POST[ 'import-wpf2-submit' ] ) ) {
			$this->importFromFirewall2Plugin();
		}
		else if ( !isset($_POST[$this->m_sOptionPrefix.'all_options_input']) ) {
			return;
		}
		else {
			$this->loadOptionsHandler( 'Firewall' );
			$this->m_oFirewallOptions->updatePluginOptionsFromSubmit( $_POST[$this->m_sOptionPrefix.'all_options_input'] );
		}
		$this->setSharedOption( 'enable_firewall', $this->m_oFirewallOptions->getOpt( 'enable_firewall' ) );
		$this->resetFirewallProcessor();
	}
	
	protected function handleSubmit_LoginProtect() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('login_protect' ) );
		
		if ( !isset($_POST[$this->m_sOptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->loadOptionsHandler( 'LoginProtect' );
		$this->m_oLoginProtectOptions->updatePluginOptionsFromSubmit( $_POST[$this->m_sOptionPrefix.'all_options_input'] );
		$this->setSharedOption( 'enable_login_protect', $this->m_oLoginProtectOptions->getOpt( 'enable_login_protect' ) );
		$this->resetLoginProtectProcessor();
	}
	
	protected function handleSubmit_CommentsFilter() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('comments_filter' ) );
		
		if ( !isset($_POST[$this->m_sOptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->loadOptionsHandler( 'CommentsFilter' );
		$this->m_oCommentsFilterOptions->updatePluginOptionsFromSubmit( $_POST[$this->m_sOptionPrefix.'all_options_input'] );
		$this->setSharedOption( 'enable_comments_filter', $this->m_oCommentsFilterOptions->getOpt( 'enable_comments_filter' ) );
		$this->resetCommentsFilterProcessor();
	}
	
	protected function handleSubmit_Lockdown() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('lockdown' ) );
		
		if ( !isset($_POST[$this->m_sOptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->loadOptionsHandler( 'Lockdown' );
		$this->m_oLockdownOptions->updatePluginOptionsFromSubmit( $_POST[$this->m_sOptionPrefix.'all_options_input'] );
		$this->setSharedOption( 'enable_lockdown', $this->m_oLockdownOptions->getOpt( 'enable_lockdown' ) );
		$this->resetLockdownProcessor();
	}
	
	protected function handleSubmit_AutoUpdates() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId( 'autoupdates' ) );
		
		if ( isset( $_GET['force_run_auto_updates'] ) && $_GET['force_run_auto_updates'] == 'now' ) {
			$this->loadProcessor( 'AutoUpdates' );
			add_action( 'init', array( $this->m_oAutoUpdatesProcessor, 'force_run_autoupdates' ) );
			return;
		}
		
		if ( !isset($_POST[$this->m_sOptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->loadOptionsHandler( 'AutoUpdates' );
		$this->m_oAutoUpdatesOptions->updatePluginOptionsFromSubmit( $_POST[$this->m_sOptionPrefix.'all_options_input'] );
		$this->setSharedOption( 'enable_autoupdates', $this->m_oAutoUpdatesOptions->getOpt( 'enable_autoupdates' ) );
		$this->resetAutoUpdatesProcessor();
	}
	
	protected function handleSubmit_FirewallLog() {

		// Ensures we're actually getting this request from a valid WP submission.
		if ( !isset( $_REQUEST['_wpnonce'] ) || !wp_verify_nonce( $_REQUEST['_wpnonce'], $this->getSubmenuId( 'firewall_log' ) ) ) {
			wp_die();
		}
		
		// At the time of writing the page only has 1 form submission item - clear log
		if ( isset( $_POST['clear_log_submit'] ) ) {
			$this->loadProcessor( 'Logging' );
			$this->m_oLoggingProcessor->recreateTable();
		}
		else if ( isset( $_GET['blackip'] ) ) {
			$this->addRawIpsToFirewallList( 'ips_blacklist', array( $_GET['blackip'] ) );
		}
		else if ( isset( $_GET['unblackip'] ) ) {
			$this->removeRawIpsFromFirewallList( 'ips_blacklist', array( $_GET['unblackip'] ) );
		}
		else if ( isset( $_GET['whiteip'] ) ) {
			$this->addRawIpsToFirewallList( 'ips_whitelist', array( $_GET['whiteip'] ) );
		}
		else if ( isset( $_GET['unwhiteip'] ) ) {
			$this->removeRawIpsFromFirewallList( 'ips_whitelist', array( $_GET['unwhiteip'] ) );
		}
		wp_safe_redirect( admin_url( "admin.php?page=".$this->getSubmenuId('firewall_log') ) ); //means no admin message is displayed
		exit();
	}
	
	public function clearCaches() {
		$aFeatures = $this->getFeaturesMap();
		foreach( $aFeatures as $sFeatureSlug => $sProcessor ) {
			$sFunctionName = 'reset'.$sProcessor.'Processor';
			$this->{$sFunctionName}();
		}
		$this->resetLoggingProcessor();
	}
	
	protected function resetEmailProcessor() {
		$this->m_oEmailProcessor = false;
		$this->deleteOption( 'email_processor' );
		$this->loadProcessor( 'Email' );
	}
	
	protected function resetFirewallProcessor() {
		$this->resetEmailProcessor();
		$this->m_oFirewallProcessor = false;
		$this->deleteOption( 'firewall_processor' );
		$this->loadProcessor( 'Firewall' );
	}
	
	protected function resetLoginProtectProcessor() {
		$this->m_oLoginProtectProcessor = false;
		$this->deleteOption( 'login_protect_processor' );
		$this->loadProcessor( 'LoginProtect' );
	}
	
	protected function resetCommentsFilterProcessor() {
		$this->m_oCommentsFilterProcessor = false;
		$this->deleteOption( 'comments_filter_processor' );
		$this->loadProcessor( 'CommentsFilter' );
	}
	
	protected function resetLockdownProcessor() {
		$this->m_oLockdownProcessor = false;
		$this->deleteOption( 'lockdown_processor' );
		$this->loadProcessor( 'Lockdown' );
	}
	
	protected function resetAutoUpdatesProcessor() {
		$this->m_oLockdownProcessor = false;
		$this->deleteOption( 'autoupdates_processor' );
		$this->loadProcessor( 'AutoUpdates' );
	}
	
	protected function resetLoggingProcessor() {
		$this->m_oLoggingProcessor = false;
		$this->deleteOption( 'logging_processor' );
		$this->loadProcessor( 'Logging' );
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
			else {
				$sProcessorVariable = $this->getProcessorVar($sProcessor, true);
				$sProcessorVariable->run();
			}
		}
		
		if ( $this->isValidAdminArea() ) {
			//Someone clicked the button to acknowledge the update
			if ( isset( $_POST[$this->m_sOptionPrefix.'hide_update_notice'] ) && isset( $_POST['user_id'] ) ) {
				$this->updateVersionUserMeta( $_POST['user_id'] );
				if ( $this->isShowMarketing() ) {
					wp_redirect( admin_url( "admin.php?page=".$this->getFullParentMenuId() ) );
				}
				else {
					wp_redirect( admin_url( $_POST['redirect_page'] ) );
				}
			}
			if ( isset( $_POST[$this->m_sOptionPrefix.'hide_translation_notice'] ) && isset( $_POST['user_id'] ) ) {
				$this->updateTranslationNoticeShownUserMeta( $_POST['user_id'] );
				wp_redirect( admin_url( $_POST['redirect_page'] ) );
			}
		}
		
		if ( $this->isValidAdminArea()
				&& $this->m_oPluginMainOptions->getOpt('enable_upgrade_admin_notice') == 'Y'
				&& $this->hasPermissionToSubmit()
			) {
			$this->m_fDoAutoUpdateCheck = true;
		}
	}

	protected function getProcessorVar( $insProcessorName, $infLoad = false ) {
		$aOptions = array_values( $this->getFeaturesMap() );
		if ( !in_array($insProcessorName, $aOptions) ) {
			return null;
		}
		if ( $infLoad ) {
			$this->loadProcessor( $insProcessorName );
		}
		$sProcessorVariable = 'm_o'.$insProcessorName.'Processor';
		return $this->{$sProcessorVariable};
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
	
	public function onWpShutdown() {
		parent::onWpShutdown();
		$this->saveProcessors_Action();
	}
	
	protected function deleteAllPluginDbOptions() {
		
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$this->loadProcessor( 'Logging' );
		$this->m_oLoggingProcessor->dropTable();

		$this->loadProcessor( 'LoginProtect' );
		$this->m_oLoginProtectProcessor->dropTable();

		$this->loadProcessor( 'CommentsFilter' );
		$this->m_oCommentsFilterProcessor->dropTable();

		$aOptions = $this->getAllOptions();
		foreach( $aOptions as &$oOption ) {
			$oOption->deletePluginOptions();
		}
		$aProcessors = $this->getAllProcessors();
		foreach( $aProcessors as $oProcessor ) {
			$oProcessor->deleteStore();
		}
		remove_action( 'shutdown', array( $this, 'onWpShutdown' ) );
	}
	
	public function onWpDeactivatePlugin() {
		if ( $this->m_oPluginMainOptions->getOpt( 'delete_on_deactivate' ) == 'Y' ) {
			$this->deleteAllPluginDbOptions();
		}
	}
	
	public function onWpActivatePlugin() {
		$this->loadOptionsHandler( 'all', true );
	}
	
	public function addRawIpsToFirewallList( $insListName, $inaNewIps ) {

		$this->loadOptionsHandler( 'Firewall' );
		
		$aIplist = $this->m_oFirewallOptions->getOpt( $insListName );
		if ( empty( $aIplist ) ) {
			$aIplist = array();
		}
		$aNewList = array();
		foreach( $inaNewIps as $sAddress ) {
			$aNewList[ $sAddress ] = '';
		}
		$aIplist = $this->m_oFirewallOptions->setOpt( $insListName, ICWP_DataProcessor::Add_New_Raw_Ips( $aIplist, $aNewList ) );
		$this->resetFirewallProcessor();
	}

	public function removeRawIpsFromFirewallList( $insListName, $inaRemoveIps ) {

		$this->loadOptionsHandler( 'Firewall' );
		
		$aIplist = $this->m_oFirewallOptions->getOpt( $insListName );
		if ( empty( $aIplist ) || empty( $inaRemoveIps ) ) {
			return;
		}
		$aIplist = $this->m_oFirewallOptions->setOpt( $insListName, ICWP_DataProcessor::Remove_Raw_Ips( $aIplist, $inaRemoveIps ) );
		$this->resetFirewallProcessor();
	}
	
	/**
	 */
	protected function filterIpLists() {
		
		$aNewFilterIps = $this->processIpFilter( 'ips_whitelist', 'icwp_simple_firewall_whitelist_ips', $nNewAddedCount );
		if ( $aNewFilterIps !== false ) {
			
			$this->loadOptionsHandler( 'Firewall' );
			$aExistingIpList = $this->m_oFirewallOptions->getOpt( 'ips_whitelist' );
			if ( !is_array( $aExistingIpList ) ) {
				$aExistingIpList = array();
			}
			$aNewList = ICWP_DataProcessor::Add_New_Raw_Ips( $aExistingIpList, $aNewFilterIps, $nNewAddedCount );
			if ( $nNewAddedCount > 0 ) {
				$this->m_oFirewallOptions->setOpt( 'ips_whitelist', $aNewList );
				$this->resetFirewallProcessor();
			}
			$this->loadOptionsHandler( 'LoginProtect' );
			$aExistingIpList = $this->m_oLoginProtectOptions->getOpt( 'ips_whitelist' );
			if ( !is_array( $aExistingIpList ) ) {
				$aExistingIpList = array();
			}
			$aNewList = ICWP_DataProcessor::Add_New_Raw_Ips( $aExistingIpList, $aNewFilterIps, $nNewAddedCount );
			if ( $nNewAddedCount > 0 ) {
				$this->m_oLoginProtectOptions->setOpt( 'ips_whitelist', $aNewList );
				$this->resetLoginProtectProcessor();
			}
		}
		
		$nNewAddedCount = 0;
		$mResult = $this->processIpFilter( 'ips_blacklist', 'icwp_simple_firewall_blacklist_ips', $nNewAddedCount );
		if ( $mResult !== false && $nNewAddedCount > 0 ) {
			$this->m_oFirewallOptions->setOpt( 'ips_blacklist', $mResult );
			$this->resetFirewallProcessor();
		}
	}

	/**
	 * @param string $insExistingListKey
	 * @param string $insFilterName
	 * @param integer $outnNewAdded
	 * @return array|false
	 */
	protected function processIpFilter( $insExistingListKey, $insFilterName, &$outnNewAdded = 0 ) {
		
		$aFilterIps = array();
		$aFilterIps = apply_filters( $insFilterName, $aFilterIps );
		
		if ( !empty( $aFilterIps ) ) {
			
			$aNewIps = array();
			foreach( $aFilterIps as $mKey => $sValue ) {
				
				if ( is_string( $mKey ) ) { //it's the IP
					$sIP = $mKey;
					$sLabel = $sValue;
				}
				else { //it's not an associative array, so the value is the IP
					$sIP = $sValue;
					$sLabel = '';
				}
				$aNewIps[ $sIP ] = $sLabel;
			}
			return $aNewIps;
		}
		return false;
	}
	
	protected function getPluginsListUpdateMessage() {
		return _wpsf__( 'Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features.' );
	}
	
	protected function getAdminNoticeHtml_Translations() {
		$oCurrentUser = wp_get_current_user();
		if ( !($oCurrentUser instanceof WP_User) ) {
			return '';
		}
		$nUserId = $oCurrentUser->ID;
		
		$sRedirectPage = 'index.php';
		ob_start(); ?>
			<style>
				a#fromIcwp { padding: 0 5px; border-bottom: 1px dashed rgba(0,0,0,0.1); color: blue; font-weight: bold; }
			</style>
			<form id="IcwpUpdateNotice" method="post" action="admin.php?page=<?php echo $this->getSubmenuId('firewall'); ?>">
				<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
				<input type="hidden" value="1" name="<?php echo $this->m_sOptionPrefix; ?>hide_translation_notice" id="<?php echo $this->m_sOptionPrefix; ?>hide_translation_notice">
				<input type="hidden" value="<?php echo $nUserId; ?>" name="user_id" id="user_id">
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

		$oCurrentUser = wp_get_current_user();
		if ( !($oCurrentUser instanceof WP_User) ) {
			return '';
		}
		$nUserId = $oCurrentUser->ID;
		
// 		$sRedirectPage = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : 'index.php';
		$sRedirectPage = 'admin.php?page=icwp-wpsf';
		ob_start(); ?>
			<style>
				a#fromIcwp { padding: 0 5px; border-bottom: 1px dashed rgba(0,0,0,0.1); color: blue; font-weight: bold; }
			</style>
			<form id="IcwpUpdateNotice" method="post" action="admin.php?page=<?php echo $this->getSubmenuId('firewall'); ?>">
				<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
				<input type="hidden" value="1" name="<?php echo $this->m_sOptionPrefix; ?>hide_update_notice" id="<?php echo $this->m_sOptionPrefix; ?>hide_update_notice">
				<input type="hidden" value="<?php echo $nUserId; ?>" name="user_id" id="user_id">
				<h4 style="margin:10px 0 3px;">
					<?php _wpsf_e( 'Note: WordPress Simple Firewall plugin does not automatically turn on when you install/update.' ); ?>
					<?php printf( _wpsf__( 'There may also be %simportant updates to read about%s.' ), '<a href="http://icwp.io/27" id="fromIcwp" title="WordPress Simple Firewall" target="_blank">', '</a>' ); ?>
				</h4>
				<input type="submit" value="Okay, show me the dashboard." name="submit" class="button" style="float:left; margin-bottom:10px;">
				<div style="clear:both;"></div>
			</form>
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
}

endif;

$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall();
