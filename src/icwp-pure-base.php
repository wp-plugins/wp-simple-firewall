<?php

if ( !defined('ICWP_DS') ) {
	define( 'ICWP_DS', DIRECTORY_SEPARATOR );
}

require_once( dirname(__FILE__).'/icwp-once.php' );
require_once( dirname(__FILE__).'/icwp-wpfunctions.php' );
require_once( dirname(__FILE__).'/icwp-wpfilesystem.php' );

if ( !class_exists('ICWP_Pure_Base_V4') ):

class ICWP_Pure_Base_V4 extends ICWP_WPSF_Once {

	const BaseTitle				= 'iControlWP Plugins';
	const BaseSlug				= 'icwp';
	const BasePermissions		= 'manage_options';
	
	const ViewExt				= '.php';
	const ViewDir				= 'views';
	
	/**
	 * @var string
	 */
	protected $m_sVersion;
	/**
	 * Set to true if it should never be shown in the dashboard
	 * @var string
	 */
	protected $m_fHeadless = false;
	/**
	 * Set to true if this contains components from another plugin to stand alone
	 * @var string
	 */
	protected $m_fStandAlone = false;
	/**
	 * Set to true if this contains components from another plugin to stand alone
	 * @var string
	 */
	protected $m_sAutoUpdateUrl = '';
	/**
	 * @var boolean
	 */
	protected $m_fIsMultisite;
	/**
	 * @var boolean
	 */
	protected $m_fNetworkAdminOnly = false;

	/**
	 * @var string
	 */
	protected $m_sPluginHumanName;
	/**
	 * @var string
	 */
	protected $m_sPluginTextDomain;
	/**
	 * @var string
	 */
	protected $m_sPluginMenuTitle;

	/**
	 * @var string
	 */
	protected $m_sPluginRootFile;
	/**
	 * @var string
	 */
	protected $m_sPluginName;
	/**
	 * @var string
	 */
	protected $m_sPluginDir;
	/**
	 * @var string
	 */
	protected $m_sPluginPath;
	/**
	 * @var string
	 */
	protected $m_sPluginFile;
	/**
	 * @var string
	 */
	protected $m_sPluginUrl;
	/**
	 * @var string
	 */
	protected $m_sOptionPrefix;

	protected $m_aPluginMenu;
	
	protected $m_sParentMenuIdSuffix;
	
	protected $m_sPluginSlug;
	
	protected $m_fShowMarketing = '';
	
	protected $m_fAutoPluginUpgrade = false;
	
	/**
	 * @var ICWP_WpFunctions_V1;
	 */
	protected $m_oWpFunctions;
	
	/**
	 * @var ICWP_WpFilesystem_V1;
	 */
	protected $m_oWpFs;

	public function __construct() {
		
		add_action( 'plugins_loaded',			array( $this, 'onWpPluginsLoaded' ) );
		add_action( 'init',						array( $this, 'onWpInit' ), 0 );
		if ( $this->isValidAdminArea() ) {
			add_action( 'admin_init',				array( $this, 'onWpAdminInit' ) );
			add_action( 'admin_notices',			array( $this, 'onWpAdminNotices' ) );
			add_action( 'network_admin_notices',	array( $this, 'onWpAdminNotices' ) );
			add_action( 'admin_menu',				array( $this, 'onWpAdminMenu' ) );
			add_action(	'network_admin_menu',		array( $this, 'onWpNetworkAdminMenu' ) );
			add_action( 'plugin_action_links',		array( $this, 'onWpPluginActionLinks' ), 10, 4 );
			add_action( 'deactivate_plugin',		array( $this, 'onWpHookDeactivatePlugin' ), 1, 1 );
		}
		add_action( 'in_plugin_update_message-'.$this->m_sPluginFile, array( $this, 'onWpPluginUpdateMessage' ) );
		add_action( 'shutdown',					array( $this, 'onWpShutdown' ) );

		$this->m_fIsMultisite = function_exists( 'is_multisite' ) && is_multisite();
		$this->m_oWpFs = ICWP_WpFilesystem_V1::GetInstance();
		$this->setPaths();
		$this->registerActivationHooks();
	}
	
	/**
	 * This is a generic plugin auto-update checker. Since the library is never included WordPress.org
	 * plugins, this may never actually run.
	 * 
	 * @return void
	 */
	protected function setupAutoUpdates() {
		$sLibSource = $this->m_sPluginDir.'/src/lib/plugin-update-checker.php';
		if ( !is_file($sLibSource) || empty( $this->m_sAutoUpdateUrl ) ) {
			return;
		}
		require_once( $sLibSource );
		$oUpdateChecker = new PluginUpdateChecker(
			$this->m_sAutoUpdateUrl,
			$this->m_sPluginRootFile,
			$this->m_sPluginTextDomain
		);
	}
	
	protected function isValidAdminArea() {
		if ( !$this->m_fIsMultisite && is_admin() ) {
			return true;
		}
		else if ( $this->m_fNetworkAdminOnly && $this->m_fIsMultisite && is_network_admin() ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Registers the plugins activation, deactivate and uninstall hooks.
	 */
	protected function registerActivationHooks() {
		register_activation_hook( $this->m_sPluginRootFile, array( $this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( $this->m_sPluginRootFile, array( $this, 'onWpDeactivatePlugin' ) );
	//	register_uninstall_hook( $this->m_sPluginRootFile, array( $this, 'onWpUninstallPlugin' ) );
	}
	
	/**
	 * @since v3.0.0
	 */
	protected function setPaths() {
		
		if ( empty( $this->m_sPluginRootFile ) ) {
			$this->m_sPluginRootFile = __FILE__;
		}
		$this->m_sPluginName	= basename( $this->m_sPluginRootFile );
		$this->m_sPluginPath	= plugin_basename( dirname( $this->m_sPluginRootFile ) );
		$this->m_sPluginFile	= plugin_basename( $this->m_sPluginRootFile );
		$this->m_sPluginDir		= dirname( $this->m_sPluginRootFile ).ICWP_DS;
		$this->m_sPluginUrl		= plugins_url( '/', $this->m_sPluginRootFile ) ; //this seems to use SSL more reliably than WP_PLUGIN_URL
	}
	
	/**
	 * @return string
	 */
	public function getPluginFile() {
		return $this->m_sPluginFile;
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
		return is_super_admin() && current_user_can( 'manage_options' );
	}
	
	public function doPluginUpdateCheck() {
		$this->loadWpFunctions();
		$this->m_oWpFunctions->getIsPluginUpdateAvailable( $this->m_sPluginPath );
	}

	protected function getFullParentMenuId() {
		return self::BaseSlug .'-'. $this->m_sParentMenuIdSuffix;
	}

	protected function display( $insView, $inaData = array() ) {
		$sFile = $this->m_sPluginDir.self::ViewDir.ICWP_DS.$insView.self::ViewExt;

		if ( !is_file( $sFile ) ) {
			echo "View not found: ".$sFile;
			return false;
		}

		if ( count( $inaData ) > 0 ) {
			extract( $inaData, EXTR_PREFIX_ALL, self::BaseSlug );
		}

		ob_start();
		include( $sFile );
		$sContents = ob_get_contents();
		ob_end_clean();

		echo $sContents;
		return true;
	}

	protected function getSubmenuPageTitle( $insTitle ) {
		return self::BaseTitle.' - '.$insTitle;
	}
	protected function getSubmenuId( $insId = '' ) {
		$sExtension = empty($insId)? '' : '-'.$insId;
		return $this->getFullParentMenuId().$sExtension;
	}

	/**
	 * Hooked to 'plugins_loaded'
	 */
	public function onWpPluginsLoaded() {
		$this->setupAutoUpdates();
		if ( is_admin() ) {
			//Handle plugin upgrades
			$this->handlePluginUpgrade();
			$this->doPluginUpdateCheck();
			$this->load_textdomain();
		}
		if ( $this->isIcwpPluginFormSubmit() ) {
			$this->handlePluginFormSubmit();
		}
		add_filter( 'all_plugins', array( $this, 'hidePluginFromTableList' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'hidePluginUpdatesFromUI' ) );
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
	public function hidePluginFromTableList( $inaPlugins ) {
		
		if ( !$this->m_fHeadless ) {
			return $inaPlugins;
		}
		
		foreach ( $inaPlugins as $sName => $aData ) {
			if ( $this->m_sPluginFile === $sName ) {
				unset( $inaPlugins[$sName] );
			}
		}
		return $inaPlugins;
	}
	
	/**
	 * Added to the WordPress filter ('site_transient_update_plugins') in order to remove visibility of updates 
	 * from the WordPress Admin UI.
	 * 
	 * In order to ensure that WordPress still checks for plugin updates it will not remove this plugin from
	 * the list of plugins if DOING_CRON is set to true.
	 * 
	 * @uses $this->m_fHeadless if the plugin is headless, it is hidden
	 * @return StdClass 
	 */
	public function hidePluginUpdatesFromUI( $inoPlugins ) {
		
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || !$this->m_fHeadless ) {
			return $inoPlugins;
		}
		
		if ( !empty( $inoPlugins->response ) ) {
			$aResponse = $inoPlugins->response;
			foreach ( $aResponse as $sPluginFile => $oData ) {
				if ( $sPluginFile == $this->m_sPluginFile ) {
					unset( $inoPlugins->response[$sPluginFile] );
				}
			}
		}
		return $inoPlugins;
	}
	
	/**
	 * Load the multilingual aspect of the plugin
	 */
	public function load_textdomain() {
		$stest = dirname( $this->m_sPluginRootFile );
//		var_dump($stest);
//		var_dump($this->m_sPluginTextDomain);
		load_plugin_textdomain( $this->m_sPluginTextDomain, false, dirname($this->m_sPluginFile) . '/languages/' );
	}

	public function onWpInit() { }

	public function onWpAdminInit() {
		//Do Plugin-Specific Admin Work
		if ( $this->isIcwpPluginAdminPage() ) {
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
	
	public function onWpNetworkAdminMenu() {
		if ( !$this->isValidAdminArea() ) {
			return true;
		}
		$this->createMenu();
	}
	
	protected function createMenu() {

		if ( $this->m_fHeadless ) {
			return true;
		}
		
		$sFullParentMenuId = $this->getFullParentMenuId();
		add_menu_page( self::BaseTitle, $this->m_sPluginMenuTitle, self::BasePermissions, $sFullParentMenuId, array( $this, 'onDisplayAll' ), $this->getImageUrl( 'pluginlogo_16x16.png' ) );
		//Create and Add the submenu items
		$this->createPluginSubMenuItems();
		if ( !empty($this->m_aPluginMenu) ) {
			foreach ( $this->m_aPluginMenu as $sMenuTitle => $aMenu ) {
				list( $sMenuItemText, $sMenuItemId, $sMenuCallBack ) = $aMenu;
				add_submenu_page( $sFullParentMenuId, $sMenuTitle, $sMenuItemText, self::BasePermissions, $sMenuItemId, array( $this, $sMenuCallBack ) );
			}
		}
		$this->fixSubmenu();
	}

	protected function createPluginSubMenuItems(){
		/* Override to create array of sub-menu items
		 $this->m_aPluginMenu = array(
		 		//Menu Page Title => Menu Item name, page ID (slug), callback function onLoad.
		 		$this->getSubmenuPageTitle( 'Content by Country' ) => array( 'Content by Country', $this->getSubmenuId('main'), 'onDisplayCbcMain' ),
		 );
		*/
	}

	protected function fixSubmenu() {
		global $submenu;
		$sFullParentMenuId = $this->getFullParentMenuId();
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
			'plugin_url'	=> $this->m_sPluginUrl,
			'fShowAds'		=> $this->isShowMarketing()
		);
		$this->display( self::BaseSlug.'_'.$this->m_sParentMenuIdSuffix.'_index', $aData );
	}

	protected function getBaseDisplayData( $insSubmenuId = '' ) {
		return array(
			'plugin_url'		=> $this->m_sPluginUrl,
			'var_prefix'		=> $this->m_sOptionPrefix,
			'sPluginName'		=> $this->m_sPluginHumanName,
			'fShowAds'			=> $this->isShowMarketing(),
			'nonce_field'		=> $this->getSubmenuId( $insSubmenuId ),
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId( $insSubmenuId )
		);
	}
	
	protected function isShowMarketing() {

		if ( $this->m_fShowMarketing == 'Y' ) {
			return true;
		}
		elseif ( $this->m_fShowMarketing == 'N' ) {
			return false;
		}

		$sServiceClassName = 'Worpit_Plugin';
		$this->m_fShowMarketing = 'Y';
		if ( class_exists( 'Worpit_Plugin' ) ) {
			if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
				$this->m_fShowMarketing = Worpit_Plugin::IsLinked() ? 'N' : 'Y';
			}
			elseif ( function_exists( 'get_option' )
					&& get_option( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
					&& get_option( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {
		
				$this->m_fShowMarketing = 'N';
			}
		}
		return $this->m_fShowMarketing === 'N' ? false : true;
	}
	
	/**
	 * On the plugins listing page, hides the edit and deactivate links
	 * for this plugin based on permissions
	 * 
	 * @see ICWP_Pure_Base_V1::onWpPluginActionLinks()
	 */
	public function onWpPluginActionLinks( $inaActionLinks, $insFile ) {
		
		if ( $insFile == $this->m_sPluginFile ) {
			if ( !$this->hasPermissionToSubmit() ) {
				if ( array_key_exists( 'edit', $inaActionLinks ) ) {
					unset( $inaActionLinks['edit'] );
				}
				if ( array_key_exists( 'deactivate', $inaActionLinks ) ) {
					unset( $inaActionLinks['deactivate'] );
				}
			}
			$sSettingsLink = '<a href="'.network_admin_url( "admin.php" ).'?page='.$this->getSubmenuId().'">' . 'Dashboard' . '</a>';
			array_unshift( $inaActionLinks, $sSettingsLink );
		}
		return $inaActionLinks;
	}

	/**
	 * Override this method to handle all the admin notices
	 */
	public function onWpAdminNotices() {
		if ( !$this->isValidAdminArea() ) {
			return true;
		}
		// Do we have admin priviledges?
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->doAdminNoticeOptionsUpdated();
		if ( $this->hasPermissionToView() ) {
			$this->doAdminNoticePostUpgrade();
		}
		if ( $this->hasPermissionToView() ) {
			$this->doAdminNoticeTranslations();
		}
		if ( $this->hasPermissionToSubmit() ) {
			$this->doAdminNoticePluginUpgradeAvailable();
		}
	}
	
	protected function doAdminNoticePluginUpgradeAvailable() {

		// Don't show on the update page.
		if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] == 'update.php' ) {
			return;
		}
		// We need to have the correct plugin file set before proceeding.
		if ( !isset( $this->m_sPluginFile ) ) {
			return;
		}
		if ( !$this->getShowAdminNotices() ) {
			return;
		}

		$this->loadWpFunctions();
		$oUpdate = $this->m_oWpFunctions->getIsPluginUpdateAvailable( $this->m_sPluginFile );
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
		
		if ( !$this->getShowAdminNotices() ) {
			return;
		}
	
		$oCurrentUser = wp_get_current_user();
		if ( !($oCurrentUser instanceof WP_User) ) {
			return;
		}
		$nUserId = $oCurrentUser->ID;
		$sCurrentVersion = get_user_meta( $nUserId, $this->m_sOptionPrefix.'current_version', true );
		// A guard whereby if we can't ever get a value for this meta, it means we can never set it.
		// If we can never set it, we shouldn't force the Ads on those users who can't get rid of it.
		if ( empty( $sCurrentVersion ) ) { //the value has never been set, or it's been installed for the first time.
			$this->updateVersionUserMeta( $nUserId );
			return; //meaning we don't show the update notice upon new installations and for those people who can't set the version in their meta.
		}
		
		if ( $sCurrentVersion === $this->m_sVersion ) {
			return;
		}
		$sHtml = $this->getAdminNoticeHtml_VersionUpgrade();
		if ( !empty($sHtml) ) {
			$this->getAdminNoticeHtml( $sHtml, 'updated', true );
		}
	}
	
	protected function doAdminNoticeTranslations(){
		
		if ( !$this->getShowAdminNotices() ) {
			return;
		}
		
		$oCurrentUser = wp_get_current_user();
		if ( !($oCurrentUser instanceof WP_User) ) {
			return;
		}
		$nUserId = $oCurrentUser->ID;
		
		$sAlreadyShowTranslationNotice = get_user_meta( $nUserId, $this->m_sOptionPrefix.'plugin_translation_notice', true );
		// A guard whereby if we can't ever get a value for this meta, it means we can never set it.
		if ( empty( $sAlreadyShowTranslationNotice ) ) {
			//the value has never been set, or it's been installed for the first time.
			$this->updateTranslationNoticeShownUserMeta( $nUserId, 'M' );
			return; //meaning we don't show the update notice upon new installations and for those people who can't set the version in their meta.
		}
		if ( $sAlreadyShowTranslationNotice === 'Y' ) {
			return;
		}
		
		$sHtml = $this->getAdminNoticeHtml_Translations();
		if ( !empty($sHtml) ) {
			$this->getAdminNoticeHtml( $sHtml, 'updated', true );
		}
	}

	/**
	 * Override this to change the message for the particular plugin upgrade.
	 */
	protected function getAdminNoticeHtml_PluginUpgradeAvailable() {
		$sUpgradeLink = $this->m_oWpFunctions->getPluginUpgradeLink( $this->m_sPluginFile );
		$sNotice = '<p>There is an update available for the %s plugin. <a href="%s">Click to update immediately</a>.</p>';
		$sNotice = sprintf( $sNotice, $this->m_sPluginHumanName, $sUpgradeLink );
		return $sNotice;
	}

	protected function getAdminNoticeHtml_OptionsUpdated() { }
	protected function getAdminNoticeHtml_VersionUpgrade() { }
	protected function getAdminNoticeHtml_Translations() { }

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
				<style>
					#message form { margin: 0px; }
				</style>
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
	 * @param $innId
	 */
	protected function updateTranslationNoticeShownUserMeta( $innId = '', $insValue = 'Y' ) {
		$this->updateUserMeta( 'plugin_translation_notice', $insValue, $innId );
	}
	
	/**
	 * Updates the current (or supplied user ID) user meta data with the version of the plugin
	 *  
	 * @param unknown_type $innId
	 */
	protected function updateVersionUserMeta( $innId = '' ) {
		$this->updateUserMeta( 'current_version', $this->m_sVersion, $innId );
	}
	
	/**
	 * Updates the current (or supplied user ID) user meta data with the version of the plugin
	 * 
	 * @param string $insKey
	 * @param anything $inmValue
	 * @param integeter $innId		-user ID
	 */
	protected function updateUserMeta( $insKey, $inmValue, $innId = null ) {
		if ( empty( $innId ) ) {
			$oCurrentUser = wp_get_current_user();
			if ( !($oCurrentUser instanceof WP_User) ) {
				return;
			}
			$nUserId = $oCurrentUser->ID;
		}
		else {
			$nUserId = $innId;
		}
		update_user_meta( $nUserId, $this->m_sOptionPrefix.$insKey, $inmValue );
	}

	/**
	 * This is called from within onWpAdminInit. Use this solely to manage upgrades of the plugin
	 */
	protected function handlePluginUpgrade() {
		if ( !is_admin() || !current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( $this->m_fAutoPluginUpgrade ) {
			$this->loadWpFunctions();
			$this->m_oWpFunctions->doPluginUpgrade( $this->m_sPluginFile );
		}
	}

	protected function handlePluginFormSubmit() { }

	protected function isIcwpPluginFormSubmit() {
		return isset( $_POST['icwp_plugin_form_submit'] );
	}

	protected function isIcwpPluginAdminPage() {
		$sSubPageNow = isset( $_GET['page'] )? $_GET['page']: '';
		if ( is_admin() && !empty($sSubPageNow) && (strpos( $sSubPageNow, $this->getFullParentMenuId() ) === 0 )) { //admin area, and the 'page' begins with 'worpit'
			return true;
		}
		return false;
	}
		
	public function enqueueBootstrapAdminCss() {
		wp_register_style( $this->m_sOptionPrefix.'bootstrap_wpadmin_css', $this->getCssUrl( 'bootstrap-wpadmin.css' ), false, $this->m_sVersion );
		wp_enqueue_style( $this->m_sOptionPrefix.'bootstrap_wpadmin_css' );
	}

	public function enqueueBootstrapLegacyAdminCss() {
		wp_register_style( $this->m_sOptionPrefix.'bootstrap_wpadmin_legacy_css', $this->getCssUrl( 'bootstrap-wpadmin-legacy.css' ), false, $this->m_sVersion );
		wp_enqueue_style( $this->m_sOptionPrefix.'bootstrap_wpadmin_legacy_css' );
		wp_register_style( $this->m_sOptionPrefix.'bootstrap_wpadmin_css_fixes', $this->getCssUrl('bootstrap-wpadmin-fixes.css'),  array( $this->m_sOptionPrefix.'bootstrap_wpadmin_legacy_css'), $this->m_sVersion );
		wp_enqueue_style( $this->m_sOptionPrefix.'bootstrap_wpadmin_css_fixes' );
	}

	public function enqueuePluginAdminCss() {
		wp_register_style( $this->m_sOptionPrefix.'plugin_css', $this->getCssUrl('plugin.css'), array($this->m_sOptionPrefix.'bootstrap_wpadmin_css_fixes'), $this->m_sVersion );
		wp_enqueue_style( $this->m_sOptionPrefix.'plugin_css' );
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
		if ( strpos( $insPlugin, $this->m_sPluginName ) !== false ) {
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
		return $this->m_sOptionPrefix.$insKey;
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
	
	protected function loadWpFunctions() {
		if ( !isset( $this->m_oWpFunctions ) ) {
			$this->m_oWpFunctions = new ICWP_WpFunctions_V1();
		}
	}

	protected function flushCaches() {
		if (function_exists('w3tc_pgcache_flush')) {
			w3tc_pgcache_flush();
		}
	}

	protected function getImageUrl( $insImage ) {
		return $this->m_sPluginUrl.'resources/images/'.$insImage;
	}
	protected function getCssUrl( $insCss ) {
		return $this->m_sPluginUrl.'resources/css/'.$insCss;
	}
	protected function getJsUrl( $insJs ) {
		return $this->m_sPluginUrl.'resources/js/'.$insJs;
	}
	
	/**
	 * @param string $insKey
	 * @return mixed|null
	 */
	protected function fetchRequest( $insKey, $infIncludeCookie = true ) {
		$mFetchVal = $this->fetchPost( $insKey );
		if ( is_null( $mFetchVal ) ) {
			$mFetchVal = $this->fetchGet( $insKey );
			if ( is_null( $mFetchVal ) ) {
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
	 * @param string $insKey		The $_GET key
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
	
}//CLASS

endif;
