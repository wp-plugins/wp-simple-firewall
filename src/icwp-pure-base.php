<?php

if ( !defined('ICWP_DS') ) {
	define( 'ICWP_DS', DIRECTORY_SEPARATOR );
}

require_once( dirname(__FILE__).'/icwp-once.php' );
require_once( dirname(__FILE__).'/icwp-wpfunctions.php' );
require_once( dirname(__FILE__).'/icwp-wpfilesystem.php' );

if ( !class_exists('ICWP_Pure_Base_V5') ):

class ICWP_Pure_Base_V5 extends ICWP_WPSF_Once {

	const ViewExt				= '.php';
	const ViewDir				= 'views';

	/**
	 * @var ICWP_Wordpress_Simple_Firewall_Plugin
	 */
	protected $oPluginVo;

	/**
	 * Set to true if it should never be shown in the dashboard
	 * @var string
	 */
	protected $fHeadless = false;

	/**
	 * Set to true if this contains components from another plugin to stand alone
	 * @var string
	 */
	protected $m_sAutoUpdateUrl = '';

	/**
	 * @var string
	 */
	protected $sPluginRootFile;
	/**
	 * @var string
	 */
	protected $sPluginFileName;
	/**
	 * @var string
	 */
	protected $sPluginRootDir;
	/**
	 * @var string
	 */
	protected $sPluginBaseFile;
	/**
	 * @var string
	 */
	protected $sPluginUrl;
	/**
	 * @var string
	 */
	protected static $sOptionPrefix = '';

	protected $aPluginMenu;
	
	protected $sPluginSlug;
	
	protected $fShowMarketing;
	
	protected $fAutoPluginUpgrade = false;
	
	/**
	 * @var ICWP_WpFunctions_WPSF;
	 */
	protected $m_oWpFunctions;
	
	/**
	 * @var ICWP_WpFilesystem_WPSF;
	 */
	protected $m_oWpFs;

	public function __construct( ICWP_Wordpress_Simple_Firewall_Plugin $oPluginVo ) {

		// All core values of the plugin are derived from the values stored in this value object.
		$this->oPluginVo				= $oPluginVo;
		$this->sPluginRootFile			= $this->oPluginVo->getRootFile();
		$this->fAutoPluginUpgrade		= $this->oPluginVo->getAutoUpgrade();
		$this->sPluginSlug				= $this->oPluginVo->getPluginSlug();
		self::$sOptionPrefix			= $this->oPluginVo->getOptionStoragePrefix();
		$this->setPaths();

		add_action( 'plugins_loaded',			array( $this, 'onWpPluginsLoaded' ) );
		add_action( 'init',						array( $this, 'onWpInit' ), 0 );
		if ( $this->isValidAdminArea() ) {
			add_action( 'admin_init',				array( $this, 'onWpAdminInit' ) );
			add_action( 'admin_notices',			array( $this, 'onWpAdminNotices' ) );
			add_action( 'network_admin_notices',	array( $this, 'onWpAdminNotices' ) );
			add_action( 'admin_menu',				array( $this, 'onWpAdminMenu' ) );
			add_action(	'network_admin_menu',		array( $this, 'onWpAdminMenu' ) );
			add_action( 'plugin_action_links',		array( $this, 'onWpPluginActionLinks' ), 10, 4 );
			add_action( 'deactivate_plugin',		array( $this, 'onWpHookDeactivatePlugin' ), 1, 1 );
			add_action( 'wp_before_admin_bar_render',		array( $this, 'onWpAdminBar' ), 1, 9999 );
		}
		add_action( 'in_plugin_update_message-'.$this->getPluginBaseFile(), array( $this, 'onWpPluginUpdateMessage' ) );
		add_action( 'shutdown',					array( $this, 'onWpShutdown' ) );

		$this->m_oWpFs = ICWP_WpFilesystem_WPSF::GetInstance();
		$this->registerActivationHooks();
	}

	/**
	 * Returns this unique plugin prefix
	 *
	 * @param string $sGlue
	 * @return string
	 */
	public function getPluginPrefix( $sGlue = '-' ) {
		return $this->oPluginVo->getFullPluginPrefix( $sGlue );
	}

	/**
	 * Will prefix and return any string with the unique plugin prefix.
	 *
	 * @param string $sSuffix
	 * @param string $sGlue
	 * @return string
	 */
	public function doPluginPrefix( $sSuffix = '', $sGlue = '-' ) {
		$sPrefix = $this->oPluginVo->getFullPluginPrefix( $sGlue );

		if ( $sSuffix == $sPrefix || strpos( $sSuffix, $sPrefix.$sGlue ) === 0 ) { //it already has the prefix
			return $sSuffix;
		}

		return sprintf( '%s%s%s', $sPrefix, empty($sSuffix)? '' : $sGlue, empty($sSuffix)? '' : $sSuffix );
	}
	
	/**
	 * This is a generic plugin auto-update checker. Since the library is never included WordPress.org
	 * plugins, this may never actually run.
	 * 
	 * @return void
	 */
//	protected function setupAutoUpdates() {
//		$sLibSource = $this->sPluginRootDir.'/src/lib/plugin-update-checker.php';
//		if ( !is_file($sLibSource) || empty( $this->m_sAutoUpdateUrl ) ) {
//			return;
//		}
//		require_once( $sLibSource );
//		$oUpdateChecker = new PluginUpdateChecker(
//			$this->m_sAutoUpdateUrl,
//			$this->sPluginRootFile,
//			$this->oPluginVo->getTextDomain()
//		);
//	}
	
	protected function isValidAdminArea() {
		$this->loadWpFunctions();
		if ( !$this->m_oWpFunctions->isMultisite() && is_admin() ) {
			return true;
		}
		else if ( $this->m_oWpFunctions->isMultisite() && $this->oPluginVo->getIsWpmsNetworkAdminOnly() && is_network_admin() ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Registers the plugins activation, deactivate and uninstall hooks.
	 */
	protected function registerActivationHooks() {
		register_activation_hook( $this->sPluginRootFile, array( $this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( $this->sPluginRootFile, array( $this, 'onWpDeactivatePlugin' ) );
	//	register_uninstall_hook( $this->sPluginRootFile, array( $this, 'onWpUninstallPlugin' ) );
	}
	
	/**
	 * @since v3.0.0
	 */
	protected function setPaths() {
		if ( empty( $this->sPluginRootFile ) ) {
			$this->sPluginRootFile = __FILE__;
		}
		$this->sPluginFileName	= basename( $this->sPluginRootFile );
		$this->getPluginBaseFile();
		$this->sPluginRootDir	= dirname( $this->sPluginRootFile ).ICWP_DS;
		$this->sPluginUrl		= plugins_url( '/', $this->sPluginRootFile ) ; //this seems to use SSL more reliably than WP_PLUGIN_URL
	}
	
	/**
	 * This is the path to the main plugin file relative to the WordPress plugins directory.
	 *
	 * @return string
	 */
	public function getPluginBaseFile() {
		if ( !isset( $this->sPluginBaseFile ) ) {
			$this->sPluginBaseFile	= plugin_basename( $this->sPluginRootFile );
		}
		return $this->sPluginBaseFile;
	}

	/**
	 * @return boolean
	 */
	protected function hasPermissionToView() {
		return $this->hasPermissionToSubmit();
	}

	/**
	 * @return boolean
	 */
	protected function hasPermissionToSubmit() {
		// first a basic admin check
		return is_super_admin() && current_user_can( $this->oPluginVo->getBasePermissions() );
	}
	
	public function doPluginUpdateCheck() {
		$this->loadWpFunctions();
		$this->m_oWpFunctions->getIsPluginUpdateAvailable( $this->getPluginBaseFile() );
	}

	protected function display( $insView, $inaData = array() ) {
		$sFile = $this->sPluginRootDir.self::ViewDir.ICWP_DS.$insView.self::ViewExt;

		if ( !is_file( $sFile ) ) {
			echo "View not found: ".$sFile;
			return false;
		}

		if ( count( $inaData ) > 0 ) {
			extract( $inaData, EXTR_PREFIX_ALL, $this->oPluginVo->getParentSlug() ); //slug being 'icwp'
		}

		ob_start();
		include( $sFile );
		$sContents = ob_get_contents();
		ob_end_clean();

		echo $sContents;
		return true;
	}

	protected function getSubmenuId( $sId = '' ) {
		return $this->doPluginPrefix( $sId, '-' );
	}

	/**
	 * Hooked to 'plugins_loaded'
	 */
	public function onWpPluginsLoaded() {
		if ( is_admin() ) {
			//Handle plugin upgrades
			$this->handlePluginUpgrade();
			$this->doPluginUpdateCheck();
			$this->load_textdomain();
		}
		if ( $this->isIcwpPluginFormSubmit() ) {
			$this->handlePluginFormSubmit();
		}
		add_filter( 'all_plugins', array( $this, 'filter_hidePluginFromTableList' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_hidePluginUpdatesFromUI' ) );
		$this->removePluginConflicts(); // removes conflicts with other plugins
	}

	/**
	 * Override this to remove conflicts with other plugins that may have loaded
	 * that interfere with normal operations.
	 */
	protected function removePluginConflicts() {}
	
	/**
	 * Added to a WordPress filter ('all_plugins') which will remove this particular plugin from the
	 * list of all plugins based on the "plugin file" name.
	 * 
	 * @uses $this->m_fHeadless if the plugin is headless, it is hidden
	 * @return array
	 */
	public function filter_hidePluginFromTableList( $aPlugins ) {
		
		if ( !$this->fHeadless ) {
			return $aPlugins;
		}

		$sPluginBaseFileName = $this->getPluginBaseFile();
		if ( isset( $aPlugins[$sPluginBaseFileName] ) ) {
			unset( $aPlugins[$sPluginBaseFileName] );
		}
		return $aPlugins;
	}
	
	/**
	 * Added to the WordPress filter ('site_transient_update_plugins') in order to remove visibility of updates 
	 * from the WordPress Admin UI.
	 * 
	 * In order to ensure that WordPress still checks for plugin updates it will not remove this plugin from
	 * the list of plugins if DOING_CRON is set to true.
	 * 
	 * @uses $this->fHeadless if the plugin is headless, it is hidden
	 * @param StdClass $oPlugins
	 * @return StdClass
	 */
	public function filter_hidePluginUpdatesFromUI( $oPlugins ) {
		
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || !$this->fHeadless ) {
			return $oPlugins;
		}

		if ( !empty( $oPlugins->response[ $this->getPluginBaseFile() ] ) ) {
			unset( $oPlugins->response[ $this->getPluginBaseFile() ] );
		}

		return $oPlugins;
	}
	
	/**
	 * Load the multilingual aspect of the plugin
	 */
	public function load_textdomain() {
		//TODO: Can replace with $this->sPluginRootDir ?
		load_plugin_textdomain( $this->oPluginVo->getTextDomain(), false, dirname( $this->getPluginBaseFile() ) . '/languages/' );
	}

	public function onWpInit() { }

	public function onWpAdminInit() {
		//Do Plugin-Specific Admin Work
		if ( $this->getIsPage_PluginAdmin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueBootstrapLegacyAdminCss' ), 99 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueuePluginAdminCss' ), 99 );
		}
	}
	
	public function onWpAdminMenu() {
		if ( !$this->isValidAdminArea() ) {
			return true;
		}
		$this->createMenu();
	}

	protected function createMenu() {

		if ( $this->fHeadless ) {
			return true;
		}
		
		$sFullParentMenuId = $this->getPluginPrefix();
		add_menu_page( $this->oPluginVo->getHumanName(), $this->oPluginVo->getAdminMenuTitle(), $this->oPluginVo->getBasePermissions(), $sFullParentMenuId, array( $this, 'onDisplayAll' ), $this->getPluginLogoUrl16() );
		//Create and Add the submenu items
		$this->createPluginSubMenuItems();
		if ( !empty($this->aPluginMenu) ) {
			foreach ( $this->aPluginMenu as $sMenuTitle => $aMenu ) {
				list( $sMenuItemText, $sMenuItemId, $sMenuCallBack ) = $aMenu;
				add_submenu_page( $sFullParentMenuId, $sMenuTitle, $sMenuItemText, $this->oPluginVo->getBasePermissions(), $sMenuItemId, array( $this, $sMenuCallBack ) );
			}
		}
		$this->fixSubmenu();
	}

	protected function createPluginSubMenuItems(){
		/* Override to create array of sub-menu items
		 $this->aPluginMenu = array(
		 		//Menu Page Title => Menu Item name, page ID (slug), callback function onLoad.
		 		$this->getSubmenuPageTitle( 'Content by Country' ) => array( 'Content by Country', $this->getSubmenuId('main'), 'onDisplayCbcMain' ),
		 );
		*/
	}

	protected function fixSubmenu() {
		global $submenu;
		$sFullParentMenuId = $this->getPluginPrefix();
		if ( isset( $submenu[$sFullParentMenuId] ) ) {
			$submenu[$sFullParentMenuId][0][0] = 'Dashboard';
		}
	}

	/**
	 * Displaying all views now goes through this central function and we work out
	 * what to display based on the name of current hook/filter being processed.
	 */
	public function onDisplayAll() {
		$this->onDisplayMainMenu();
	}
	
	/**
	 * The callback function for the main admin menu index page
	 */
	public function onDisplayMainMenu() {
		$aData = array(
			'plugin_url'	=> $this->sPluginUrl,
			'fShowAds'		=> $this->isShowMarketing()
		);
		$this->display( $this->oPluginVo->getFullPluginPrefix('_') .'_index', $aData );
	}

	protected function getBaseDisplayData( $sSubmenu = '' ) {
		return array(
			'plugin_url'		=> $this->sPluginUrl,
			'var_prefix'		=> self::$sOptionPrefix,
			'sPluginName'		=> $this->oPluginVo->getHumanName(),
			'fShowAds'			=> $this->isShowMarketing(),
			'nonce_field'		=> $this->getPluginPrefix(),
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId( $sSubmenu )
		);
	}

	/**
	 */
	protected function getCurrentWpAdminPage() {
		$sScript = isset( $_SERVER['SCRIPT_NAME'] )? $_SERVER['SCRIPT_NAME'] : $_SERVER['PHP_SELF'];
		if ( is_admin() && !empty( $sScript ) && basename( $sScript ) == 'admin.php' ) {
			$sCurrentPage = $this->fetchGet('page');
		}
		return empty($sCurrentPage)? '' : $sCurrentPage;
	}

	/**
	 */
	protected function getIsPage_PluginMainDashboard() {
		return ( $this->getCurrentWpAdminPage() ==  $this->getPluginPrefix() );
	}

	/**
	 */
	protected function getIsPage_PluginAdmin() {
		return ( strpos( $this->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0 );
	}

	/**
	 * @param string $sFeaturePage - leave empty to get the main dashboard
	 * @return mixed
	 */
	protected function getUrl_PluginDashboard( $sFeaturePage = '' ) {
		return network_admin_url( sprintf( 'admin.php?page=%s', $this->getSubmenuId( $sFeaturePage ) ) );
	}

	/**
	 * @return bool
	 */
	protected function isShowMarketing() {

		if ( isset($this->fShowMarketing) ) {
			return $this->fShowMarketing;
		}
		$this->fShowMarketing = true;
		if ( class_exists( 'Worpit_Plugin' ) ) {
			if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
				$this->fShowMarketing = !Worpit_Plugin::IsLinked();
			}
			else if ( function_exists( 'get_option' )
					&& get_option( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
					&& get_option( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {
		
				$this->fShowMarketing = false;
			}
		}
		return $this->fShowMarketing ;
	}
	
	/**
	 * On the plugins listing page, hides the edit and deactivate links
	 * for this plugin based on permissions
	 * 
	 * @see ICWP_Pure_Base_V1::onWpPluginActionLinks()
	 */
	public function onWpPluginActionLinks( $aActionLinks, $sPluginFile ) {
		
		if ( $sPluginFile == $this->getPluginBaseFile() ) {
			if ( !$this->hasPermissionToSubmit() ) {
				if ( array_key_exists( 'edit', $aActionLinks ) ) {
					unset( $aActionLinks['edit'] );
				}
				if ( array_key_exists( 'deactivate', $aActionLinks ) ) {
					unset( $aActionLinks['deactivate'] );
				}
			}

			$sSettingsLink = sprintf( '<a href="%s">%s</a>', $this->getUrl_PluginDashboard(), _wpsf__( 'Dashboard' ) ); ;
			array_unshift( $aActionLinks, $sSettingsLink );
		}
		return $aActionLinks;
	}

	/**
	 * Override this method to handle all the admin notices
	 */
	public function onWpAdminNotices() {
		// Do we have admin priviledges?
		if ( !$this->isValidAdminArea() || !current_user_can( 'manage_options' ) ) {
			return true;
		}

		$this->doAdminNoticeOptionsUpdated();

		// If we've set to not show admin notices ever
		if ( $this->getShowAdminNotices() ) {

			if ( $this->hasPermissionToView() ) {
				$this->doAdminNoticePostUpgrade();
				$this->doAdminNoticeTranslations();
				$this->doAdminNoticeMailingListSignup();
			}
			if ( $this->hasPermissionToSubmit() ) {
				$this->doAdminNoticePluginUpgradeAvailable();
			}
		}
	}
	
	protected function doAdminNoticePluginUpgradeAvailable() {

		// Don't show on the update page.
		if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] == 'update.php' ) {
			return;
		}

		$this->loadWpFunctions();
		$oUpdate = $this->m_oWpFunctions->getIsPluginUpdateAvailable( $this->getPluginBaseFile() );
		if ( !$oUpdate ) {
			return;
		}
		$sNotice = $this->getAdminNoticeHtml_PluginUpgradeAvailable();
		$this->getAdminNoticeHtml( $sNotice, 'updated', true );
	}
	
	protected function doAdminNoticeOptionsUpdated(){
		$sHtml = $this->getAdminNoticeHtml_OptionsUpdated();
		if ( !empty($sHtml) ) {
			$this->getAdminNoticeHtml( $sHtml, 'updated', true );
		}
	}
	
	protected function doAdminNoticePostUpgrade() {

		$sCurrentMetaValue = $this->getUserMeta( 'current_version' );
		if ( $sCurrentMetaValue === $this->oPluginVo->getVersion() ) {
			return;
		}
		$sHtml = $this->getAdminNoticeHtml_VersionUpgrade();
		if ( !empty($sHtml) ) {
			$this->getAdminNoticeHtml( $sHtml, 'updated', true );
		}
	}

	/**
	 *
	 */
	protected function doAdminNoticeTranslations(){

		$sCurrentMetaValue = $this->getUserMeta( 'plugin_translation_notice' );
		if ( $sCurrentMetaValue === 'Y' ) {
			return;
		}
		
		$sHtml = $this->getAdminNoticeHtml_Translations();
		if ( !empty($sHtml) ) {
			$this->getAdminNoticeHtml( $sHtml, 'updated', true );
		}
	}

	/**
	 *
	 */
	protected function doAdminNoticeMailingListSignup(){

		$sCurrentMetaValue = $this->getUserMeta( 'plugin_mailing_list_signup' );
		if ( $sCurrentMetaValue == 'Y' ) {
			return;
		}

		$sHtml = $this->getAdminNoticeHtml_MailingListSignup();
		if ( !empty($sHtml) ) {
			$this->getAdminNoticeHtml( $sHtml, 'updated', true );
		}
	}

	/**
	 * Override this to change the message for the particular plugin upgrade.
	 */
	protected function getAdminNoticeHtml_PluginUpgradeAvailable() {
		$sUpgradeLink = $this->m_oWpFunctions->getPluginUpgradeLink( $this->getPluginBaseFile() );
		$sNotice = '<p>There is an update available for the %s plugin. <a href="%s">Click to update immediately</a>.</p>';
		$sNotice = sprintf( $sNotice, $this->oPluginVo->getHumanName(), $sUpgradeLink );
		return $sNotice;
	}

	protected function getAdminNoticeHtml_OptionsUpdated() { }
	protected function getAdminNoticeHtml_VersionUpgrade() { }
	protected function getAdminNoticeHtml_Translations() { }
	protected function getAdminNoticeHtml_MailingListSignup() { }

	/**
	 * Provides the basic HTML template for printing a WordPress Admin Notices
	 *
	 * @param $insNotice - The message to be displayed.
	 * @param $insMessageClass - either error or updated
	 * @param $infPrint - if true, will echo. false will return the string
	 * @return boolean|string
	 */
	protected function getAdminNoticeHtml( $insNotice = '', $insMessageClass = 'updated', $infPrint = false ) {
	
		$sFullNotice = '
			<div id="message" class="'.$insMessageClass.'">
				<style>#message form { margin: 0px; padding-bottom: 8px; }</style>
				'.$insNotice.'
			</div>
		';
	
		if ( $infPrint ) {
			echo $sFullNotice;
			return true;
		} else {
			return $sFullNotice;
		}
	}

	/**
	 * 
	 */
	protected function getShowAdminNotices() {
		return true;
	}

	/**
	 * Updates the current (or supplied user ID) user meta data with the version of the plugin
	 *
	 * @param $nId
	 * @param $sValue
	 */
	protected function updateTranslationNoticeShownUserMeta( $nId = '', $sValue = 'Y' ) {
		$this->updateUserMeta( 'plugin_translation_notice', $sValue, $nId );
	}

	/**
	 * Updates the current (or supplied user ID) user meta data with the version of the plugin
	 *
	 * @param $nId
	 * @param $sValue
	 */
	protected function updateMailingListSignupShownUserMeta( $nId = '', $sValue = 'Y' ) {
		$this->updateUserMeta( 'plugin_mailing_list_signup', $sValue, $nId );
	}
	
	/**
	 * Updates the current (or supplied user ID) user meta data with the version of the plugin
	 *  
	 * @param integer $nId
	 */
	protected function updateVersionUserMeta( $nId = null ) {
		$this->updateUserMeta( 'current_version', $this->oPluginVo->getVersion(), $nId );
	}
	
	/**
	 * Updates the current (or supplied user ID) user meta data with the version of the plugin
	 * 
	 * @param string $insKey
	 * @param mixed $mValue
	 * @param integer $innId		-user ID
	 * @return boolean
	 */
	protected function updateUserMeta( $insKey, $mValue, $innId = null ) {
		if ( empty( $innId ) ) {
			$oCurrentUser = $this->getCurrentUser();
			if ( !$oCurrentUser ) {
				return;
			}
			$nUserId = $oCurrentUser->ID;
		}
		else {
			$nUserId = $innId;
		}
		return update_user_meta( $nUserId, self::$sOptionPrefix.$insKey, $mValue );
	}

	protected function getUserMeta( $sKey ) {

		$oCurrentUser = $this->getCurrentUser();
		if ( !$oCurrentUser ) {
			return;
		}
		$nUserId = $oCurrentUser->ID;

		$sCurrentMetaValue = get_user_meta( $nUserId, $this->doPluginPrefix( $sKey, '_' ), true );
		// A guard whereby if we can't ever get a value for this meta, it means we can never set it.
		if ( empty( $sCurrentMetaValue ) ) {
			//the value has never been set, or it's been installed for the first time.
			$this->updateUserMeta( $sKey, 'temp', $nUserId );
			return ''; //meaning we don't show the update notice upon new installations and for those people who can't set the version in their meta.
		}
		return $sCurrentMetaValue;
	}

	/**
	 * @return mixed
	 */
	protected function getCurrentUser() {
		if( !is_user_logged_in() ) {
			return false;
		}
		global $current_user;
		get_currentuserinfo();
		return $current_user;
	}

	/**
	 * This is called from within onWpAdminInit. Use this solely to manage upgrades of the plugin
	 */
	protected function handlePluginUpgrade() {
		if ( !is_admin() || !current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $this->fAutoPluginUpgrade ) {
			$this->loadWpFunctions();
			$this->m_oWpFunctions->doPluginUpgrade( $this->getPluginBaseFile() );
		}
	}

	protected function handlePluginFormSubmit() { }

	/**
	 * @return bool
	 */
	protected function isIcwpPluginFormSubmit() {
		return $this->fetchPost( 'icwp_plugin_form_submit' ) == 'Y';
	}

	public function enqueueBootstrapAdminCss() {
		$sUnique = $this->doPluginPrefix( 'bootstrap_wpadmin_css' );
		wp_register_style( $sUnique, $this->getCssUrl( 'bootstrap-wpadmin.css' ), false, $this->oPluginVo->getVersion() );
		wp_enqueue_style( $sUnique );
	}

	public function enqueueBootstrapLegacyAdminCss() {
		$sUnique = $this->doPluginPrefix( 'bootstrap_wpadmin_legacy_css' );
		wp_register_style( $sUnique, $this->getCssUrl( 'bootstrap-wpadmin-legacy.css' ), false, $this->oPluginVo->getVersion() );
		wp_enqueue_style( $sUnique );

		$sUnique = $this->doPluginPrefix( 'bootstrap_wpadmin_css_fixes' );
		wp_register_style( $sUnique, $this->getCssUrl('bootstrap-wpadmin-fixes.css'),  array( $this->doPluginPrefix( 'bootstrap_wpadmin_legacy_css' ) ), $this->oPluginVo->getVersion() );
		wp_enqueue_style( $sUnique );
	}

	public function enqueuePluginAdminCss() {
		$sUnique = $this->doPluginPrefix( 'plugin_css', '_' );
		wp_register_style( $sUnique, $this->getCssUrl('plugin.css'), array( $this->doPluginPrefix( 'bootstrap_wpadmin_css_fixes' ) ), $this->oPluginVo->getVersion() );
		wp_enqueue_style( $sUnique );
	}
	protected function redirect( $insUrl, $innTimeout = 1 ) {
		echo '
			<script type="text/javascript">
				function redirect() {
					window.location = "'.$insUrl.'";
				}
				var oTimer = setTimeout( "redirect()", "'.($innTimeout * 1000).'" );
			</script>';
	}
	
	/**
	 * Displays a message in the plugins listing when a plugin has an update available.
	 * @param string $insPlugin
	 */
	public function onWpPluginUpdateMessage() {
		echo '<div style="color: #dd3333;">'
			.$this->getPluginsListUpdateMessage()
			. '</div>';
	}

	protected function getPluginsListUpdateMessage() {
		return '';
	}
	
	/**
	 * Hooked to 'deactivate_plugin' and can be used to interrupt the deactivation of this plugin.
	 * @param string $insPlugin
	 */
	public function onWpHookDeactivatePlugin( $insPlugin ) {
		if ( strpos( $insPlugin, $this->sPluginFileName ) !== false ) {
			$this->doPreventDeactivation( $insPlugin );
		}
	}
	
	/**
	 * @param string $insPlugin - the path to the plugin file
	 */
	protected function doPreventDeactivation( $insPlugin ) {
		if ( !$this->hasPermissionToSubmit() ) {
			wp_die( 'Sorry, you do not have permission to disable this plugin. You need to authenticate first.' );
		}
	}
	
	/**
	 * Gets the WordPress option based on this object's option prefix.
	 * @param string $insKey
	 * @return mixed
	 */
	public function getOption( $insKey ) {
		return get_option( $this->getOptionKey($insKey) );
	}

	/**
	 * @param string $insKey
	 * @param mixed $insValue
	 * @return boolean
	 */
	public function addOption( $insKey, $inmValue ) {
		return add_option( $this->getOptionKey($insKey), $inmValue );
	}

	/**
	 * @param string $insKey
	 * @param mixed $inmValue
	 * @return boolean
	 */
	public function updateOption( $insKey, $inmValue ) {
		return update_option( $this->getOptionKey($insKey), $inmValue );
	}

	/**
	 * @param string $insKey
	 * @return boolean
	 */
	public function deleteOption( $insKey ) {
		return delete_option( $this->getOptionKey($insKey) );
	}

	public function getOptionKey( $insKey ) {
		return self::$sOptionPrefix.$insKey;
	}

	/**
	 * Use this to wrap up the function when the PHP process is coming to an end.  Call from onWpShudown()
	 */
	protected function shutdown() {
		
	}
	
	/**
	 * Hooked to 'shutdown'
	 */
	public function onWpShutdown() {
		$this->shutdown();
	}
	
	public function onWpActivatePlugin() { }
	public function onWpDeactivatePlugin() { }
	public function onWpUninstallPlugin() { }

	/**
	 * @return ICWP_WpFunctions_WPSF
	 */
	protected function loadWpFunctions() {
		if ( !isset( $this->m_oWpFunctions ) ) {
			$this->m_oWpFunctions = ICWP_WpFunctions_WPSF::GetInstance();
		}
		return $this->m_oWpFunctions;
	}

	protected function flushCaches() {
		if (function_exists('w3tc_pgcache_flush')) {
			w3tc_pgcache_flush();
		}
	}

	protected function getImageUrl( $insImage ) {
		return $this->sPluginUrl.'resources/images/'.$insImage;
	}
	protected function getCssUrl( $insCss ) {
		return $this->sPluginUrl.'resources/css/'.$insCss;
	}
	protected function getJsUrl( $insJs ) {
		return $this->sPluginUrl.'resources/js/'.$insJs;
	}
	
	/**
	 * @param string $insKey
	 * @param boolean $infIncludeCookie
	 * @return mixed|null
	 */
	protected function fetchRequest( $insKey, $infIncludeCookie = true ) {
		$mFetchVal = $this->fetchPost( $insKey );
		if ( is_null( $mFetchVal ) ) {
			$mFetchVal = $this->fetchGet( $insKey );
			if ( is_null( $mFetchVal && $infIncludeCookie ) ) {
				$mFetchVal = $this->fetchCookie( $insKey );
			}
		}
		return $mFetchVal;
	}
	/**
	 * @param string $insKey
	 * @return mixed|null
	 */
	protected function fetchGet( $insKey ) {
		if ( function_exists( 'filter_input' ) && defined( 'INPUT_GET' ) ) {
			return filter_input( INPUT_GET, $insKey );
		}
		return $this->arrayFetch( $_GET, $insKey );
	}
	/**
	 * @param string $insKey		The $_POST key
	 * @return mixed|null
	 */
	protected function fetchPost( $insKey ) {
		if ( function_exists( 'filter_input' ) && defined( 'INPUT_POST' ) ) {
			return filter_input( INPUT_POST, $insKey );
		}
		return $this->arrayFetch( $_POST, $insKey );
	}
	/**
	 * @param string $insKey		The $_POST key
	 * @return mixed|null
	 */
	protected function fetchCookie( $insKey ) {
		if ( function_exists( 'filter_input' ) && defined( 'INPUT_COOKIE' ) ) {
			return filter_input( INPUT_COOKIE, $insKey );
		}
		return $this->arrayFetch( $_COOKIE, $insKey );
	}

	/**
	 * @param array $inaArray
	 * @param string $insKey		The array key
	 * @return mixed|null
	 */
	protected function arrayFetch( &$inaArray, $insKey ) {
		if ( empty( $inaArray ) ) {
			return null;
		}
		if ( !isset( $inaArray[$insKey] ) ) {
			return null;
		}
		return $inaArray[$insKey];
	}

	/**
	 * Performs a wp_die() but lets us do something first.
	 */
	protected function doWpDie( $insText = '' ) {
		wp_die( $insText );
		exit();
	}

	public function onWpAdminBar() {
		$aNodes = $this->getAdminBarNodes();
		foreach( $aNodes as $aNode )  {
			$this->addAdminBarNode( $aNode );
		}
	}

	protected function getAdminBarNodes() {	}

	protected function addAdminBarNode( $aNode ) {
		global $wp_admin_bar;

		if ( isset( $aNode['children'] ) ) {
			$aChildren = $aNode['children'];
			unset( $aNode['children'] );
		}
		$wp_admin_bar->add_node( $aNode );

		if ( !empty($aChildren) ) {
			foreach( $aChildren as $aChild ) {
				$aChild['parent'] = $aNode['id'];
				$this->addAdminBarNode( $aChild );
			}
		}
	}

	protected function getPluginLogoUrl16() {
		return $this->getImageUrl( 'pluginlogo_16x16.png' );
	}

	protected function getPluginLogoUrl32() {
		return $this->getImageUrl( 'pluginlogo_32x32.png' );
	}

	/**
	 */
	protected function loadDataProcessor() {
		require_once( dirname(__FILE__) . '/icwp-data-processor.php' );
	}

}//CLASS

endif;
