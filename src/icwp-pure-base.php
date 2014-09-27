<?php

require_once( dirname(__FILE__).ICWP_DS.'icwp-foundation.php' );

if ( !class_exists('ICWP_Pure_Base_V6') ):

	class ICWP_Pure_Base_V6 extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_Plugin_Controller
		 */
		protected $oPluginController;

		protected $fShowMarketing;

		public function __construct( ICWP_WPSF_Plugin_Controller $oPluginController ) {

			// All core values of the plugin are derived from the values stored in this value object.
			$this->oPluginController				= $oPluginController;

//			add_action( 'plugins_loaded',			array( $this, 'onWpPluginsLoaded' ) );
//			add_action( 'init',						array( $this, 'onWpInit' ), 0 );
			if ( $this->getController()->getIsValidAdminArea( false ) ) {
				add_action( 'admin_init',				array( $this, 'onWpAdminInit' ) );
				add_action( 'admin_menu',				array( $this, 'onWpAdminMenu' ) );
				add_action(	'network_admin_menu',		array( $this, 'onWpAdminMenu' ) );
				add_action( 'plugin_action_links',		array( $this, 'onWpPluginActionLinks' ), 10, 4 );
//				add_action( 'wp_before_admin_bar_render',		array( $this, 'onWpAdminBar' ), 1, 9999 );
			}
			$this->registerActivationHooks();
		}

		/**
		 * @return ICWP_WPSF_Plugin_Controller
		 */
		public function getController() {
			return $this->oPluginController;
		}

		/**
		 * Returns this unique plugin prefix
		 *
		 * @param string $sGlue
		 * @return string
		 */
		public function getPluginPrefix( $sGlue = '-' ) {
			return $this->getController()->getPluginPrefix( $sGlue );
		}

		/**
		 * Will prefix and return any string with the unique plugin prefix.
		 *
		 * @param string $sSuffix
		 * @param string $sGlue
		 * @return string
		 */
		public function doPluginPrefix( $sSuffix = '', $sGlue = '-' ) {
			return $this->getController()->doPluginPrefix( $sSuffix, $sGlue );
		}

		/**
		 * Registers the plugins activation, deactivate and uninstall hooks.
		 */
		protected function registerActivationHooks() {
			register_activation_hook( $this->getController()->getRootFile(), array( $this, 'onWpActivatePlugin' ) );
			register_deactivation_hook( $this->getController()->getRootFile(), array( $this, 'onWpDeactivatePlugin' ) );
			//	register_uninstall_hook( $this->oPluginVo->getRootFile(), array( $this, 'onWpUninstallPlugin' ) );
		}

		/**
		 * This is the path to the main plugin file relative to the WordPress plugins directory.
		 *
		 * @return string
		 */
		public function getPluginBaseFile() {
			return $this->getController()->getPluginBaseFile();
		}

		/**
		 * @param boolean $fHasPermission
		 * @return boolean
		 */
		public function hasPermissionToView( $fHasPermission = true ) {
			return $this->hasPermissionToSubmit( $fHasPermission );
		}

		/**
		 * @param boolean $fHasPermission
		 * @return boolean
		 */
		public function hasPermissionToSubmit( $fHasPermission = true ) {
			// first a basic admin check
			return $fHasPermission && is_super_admin() && current_user_can( $this->getController()->getBasePermissions() );
		}

		/**
		 * @param string $sView
		 * @param array $aData
		 * @return bool
		 */
		protected function display( $sView, $aData = array() ) {
			$sFile = $this->getController()->getViewPath( $sView );

			if ( !is_file( $sFile ) ) {
				echo "View not found: ".$sFile;
				return false;
			}

			if ( count( $aData ) > 0 ) {
				extract( $aData, EXTR_PREFIX_ALL, $this->getController()->getParentSlug() ); //slug being 'icwp'
			}

			ob_start();
			include( $sFile );
			$sContents = ob_get_contents();
			ob_end_clean();

			echo $sContents;
			return true;
		}

		public function onWpAdminInit() {
			//Do Plugin-Specific Admin Work
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueuePluginGlobalAdminCss' ), 99 );
			if ( $this->getIsPage_PluginAdmin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueueBootstrapLegacyAdminCss' ), 99 );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueuePluginAdminCss' ), 99 );
			}
		}

		public function onWpAdminMenu() {
			if ( !$this->getController()->getIsValidAdminArea() ) {
				return true;
			}
			$this->createMenu();
		}

		protected function createMenu() {
			$oPluginController = $this->getController();

			$sFullParentMenuId = $this->getPluginPrefix();
			add_menu_page(
				$oPluginController->getHumanName(),
				$oPluginController->getAdminMenuTitle(),
				$oPluginController->getBasePermissions(),
				$sFullParentMenuId,
				array( $this, 'onDisplayAll' ),
				$this->getController()->getPluginUrl_Image( 'pluginlogo_16x16.png' )
			);
			//Create and Add the submenu items

			$aPluginMenuItems = apply_filters( $this->doPluginPrefix( 'filter_plugin_submenu_items' ), array() );
			if ( !empty( $aPluginMenuItems ) ) {
				foreach ( $aPluginMenuItems as $sMenuTitle => $aMenu ) {
					list( $sMenuItemText, $sMenuItemId, $aMenuCallBack ) = $aMenu;
					add_submenu_page(
						$sFullParentMenuId,
						$sMenuTitle,
						$sMenuItemText,
						$oPluginController->getBasePermissions(),
						$sMenuItemId,
						$aMenuCallBack
					);
				}
			}
			$this->fixSubmenu();
		}

		protected function fixSubmenu() {
			global $submenu;
			$sFullParentMenuId = $this->getPluginPrefix();
			if ( isset( $submenu[$sFullParentMenuId] ) ) {
				unset( $submenu[$sFullParentMenuId][0] );
			}
		}

		/**
		 * Displaying all views now goes through this central function and we work out
		 * what to display based on the name of current hook/filter being processed.
		 */
		public function onDisplayAll() { }

		/**
		 * @return bool
		 */
		protected function getIsPage_PluginMainDashboard() {
			$oWp = $this->loadWpFunctionsProcessor();
			return ( $oWp->getCurrentWpAdminPage() ==  $this->getPluginPrefix() );
		}

		/**
		 * @return bool
		 */
		protected function getIsPage_PluginAdmin() {
			$oWp = $this->loadWpFunctionsProcessor();
			return ( strpos( $oWp->getCurrentWpAdminPage(), $this->getPluginPrefix() ) === 0 );
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

				$sSettingsLink = sprintf( '<a href="%s">%s</a>', $this->getController()->getPluginUrl_AdminPage(), 'Dashboard' ); ;
				array_unshift( $aActionLinks, $sSettingsLink );
			}
			return $aActionLinks;
		}

		/**
		 * @return bool
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
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->updateUserMeta( $this->getController()->doPluginOptionPrefix( 'plugin_translation_notice' ), $sValue, $nId );
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 *
		 * @param $nId
		 * @param $sValue
		 */
		protected function updateMailingListSignupShownUserMeta( $nId = '', $sValue = 'Y' ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->updateUserMeta( $this->getController()->doPluginOptionPrefix( 'plugin_mailing_list_signup' ), $sValue, $nId );
		}

		/**
		 * Updates the current (or supplied user ID) user meta data with the version of the plugin
		 *
		 * @param integer $nId
		 */
		protected function updateVersionUserMeta( $nId = null ) {
			$oWp = $this->loadWpFunctionsProcessor();
			$oWp->updateUserMeta( $this->getController()->doPluginOptionPrefix( 'current_version' ), $this->getController()->getVersion(), $nId );
		}

		public function enqueueBootstrapAdminCss() {
			$sUnique = $this->doPluginPrefix( 'bootstrap_wpadmin_css' );
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css( 'bootstrap-wpadmin.css' ), false, $this->getController()->getVersion() );
			wp_enqueue_style( $sUnique );
		}

		public function enqueueBootstrapLegacyAdminCss() {
			$sUnique = $this->doPluginPrefix( 'bootstrap_wpadmin_legacy_css' );
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css( 'bootstrap-wpadmin-legacy.css' ), false, $this->getController()->getVersion() );
			wp_enqueue_style( $sUnique );

			$sUnique = $this->doPluginPrefix( 'bootstrap_wpadmin_css_fixes' );
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css('bootstrap-wpadmin-fixes.css'),  array( $this->doPluginPrefix( 'bootstrap_wpadmin_legacy_css' ) ), $this->getController()->getVersion() );
			wp_enqueue_style( $sUnique );
		}

		public function enqueuePluginAdminCss() {
			$sUnique = $this->doPluginPrefix( 'plugin_css' );
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css('plugin.css'), array( $this->doPluginPrefix( 'bootstrap_wpadmin_css_fixes' ) ), $this->getController()->getVersion().rand() );
			wp_enqueue_style( $sUnique );
		}

		public function enqueuePluginGlobalAdminCss() {
			$sUnique = $this->doPluginPrefix( 'global_plugin_css' );
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css('global-plugin.css'), false, $this->getController()->getVersion().rand() );
			wp_enqueue_style( $sUnique );
		}

		public function onWpActivatePlugin() { }

		public function onWpDeactivatePlugin() {
			if ( current_user_can( $this->getController()->getBasePermissions() ) ) {
				do_action( $this->doPluginPrefix( 'delete_plugin' ) );
			}
		}

		/**
		 */
		public function onWpAdminBar() {
			$aNodes = $this->getAdminBarNodes();
			if ( !is_array( $aNodes ) ) {
				return;
			}
			foreach( $aNodes as $aNode )  {
				$this->addAdminBarNode( $aNode );
			}
		}

		protected function getAdminBarNodes() { }

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
	}

endif;
