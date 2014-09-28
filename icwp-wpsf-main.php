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

require_once( dirname(__FILE__).ICWP_DS.'src'.ICWP_DS.'icwp-foundation.php' );

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

	class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Foundation {

		/**
		 * @var ICWP_WPSF_FeatureHandler_Plugin
		 */
		protected $oFeatureHandlerPlugin;
		/**
		 * @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction
		 */
		protected $oFeatureHandlerAdminAccessRestriction;
		/**
		 * @var ICWP_WPSF_FeatureHandler_Firewall
		 */
		protected $oFeatureHandlerFirewall;
		/**
		 * @var ICWP_WPSF_FeatureHandler_LoginProtect
		 */
		protected $oFeatureHandlerLoginProtect;

		/**
		 * @var ICWP_WPSF_FeatureHandler_PrivacyProtect
		 */
		protected $oFeatureHandlerPrivacyProtect;

		/**
		 * @var ICWP_WPSF_FeatureHandler_AuditTrail
		 */
		protected $oFeatureHandlerAuditTrail;

		/**
		 * @var ICWP_WPSF_FeatureHandler_CommentsFilter
		 */
		protected $oFeatureHandlerCommentsFilter;

		/**
		 * @var ICWP_WPSF_FeatureHandler_Lockdown
		 */
		protected $oFeatureHandlerLockdown;

		/**
		 * @var ICWP_WPSF_FeatureHandler_Autoupdates
		 */
		protected $oFeatureHandlerAutoupdates;

		/**
		 * @var ICWP_WPSF_FeatureHandler_Email
		 */
		protected $oFeatureHandlerEmail;

		/**
		 * @var ICWP_WPSF_FeatureHandler_Logging
		 */
		protected $oFeatureHandlerLogging;

		/**
		 * @var ICWP_WPSF_Plugin_Controller
		 */
		protected $oPluginController;

		/**
		 */
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
			}

			$this->getController()->loadAllFeatures();
			add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'hasPermissionToView' ) );
			add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'hasPermissionToSubmit' ) );
			add_filter( $this->doPluginPrefix( 'plugin_update_message' ), array( $this, 'getPluginsListUpdateMessage' ) );
		}

//		protected function onDisplayFirewallLog() {
//
//			$oFirewallHandler = $this->loadFeatureHandler( 'firewall' );
//			if ( $oFirewallHandler instanceof ICWP_WPSF_FeatureHandler_Firewall ) {
//				$aIpWhitelist = $oFirewallHandler->getOpt( 'ips_whitelist' );
//				$aIpBlacklist = $oFirewallHandler->getOpt( 'ips_blacklist' );
//			}
//
//			$oLoggingProcessor = $this->getProcessor_Logging();
//			if ( $oLoggingProcessor instanceof ICWP_WPSF_Processor_Logging ) {
//				$aLogData = $oLoggingProcessor->getLogs( true );
//			}
//
//			$aData = array(
//				'sFeatureName'		=> _wpsf__('Firewall Log'),
//				'firewall_log'		=> $aLogData,
//				'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
//				'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
//			);
//			$aData = array_merge( $this->getBaseDisplayData(), $aData );
//			$this->display( $this->doPluginPrefix( 'firewall_log_index' ), $aData );
//		}

		public function onWpAdminInit() {

			//Do Plugin-Specific Admin Work
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueuePluginGlobalAdminCss' ), 99 );
			if ( $this->getController()->getIsPage_PluginAdmin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueueBootstrapLegacyAdminCss' ), 99 );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueuePluginAdminCss' ), 99 );
			}

			if ( $this->getController()->getIsValidAdminArea() ) {
				$oDp = $this->loadDataProcessor();
				$oWp = $this->loadWpFunctionsProcessor();

				$sRedirect = $oDp->FetchPost( 'redirect_page' );
				$sRedirect = empty( $sRedirect ) ? $this->getController()->getPluginUrl_AdminPage() : $sRedirect;
				//Someone clicked the button to acknowledge the update
				if ( $oDp->FetchRequest( $this->doPluginPrefix( 'hide_update_notice' ) ) == 1 ) {
					$this->updateVersionUserMeta();
					$oWp->doRedirect( $sRedirect );
				}

				if ( $oDp->FetchRequest( $this->doPluginPrefix( 'hide_translation_notice' ) ) == 1 ) {
					$this->updateTranslationNoticeShownUserMeta();
					$oWp->doRedirect( $sRedirect );
				}

				if ( $oDp->FetchRequest( $this->doPluginPrefix( 'hide_mailing_list_signup' ) ) == 1 ) {
					$this->updateMailingListSignupShownUserMeta();
				}
			}
		}

		public function getPluginsListUpdateMessage( $sMessage ) {
			return _wpsf__( 'Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features.' );
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
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css('plugin.css'), array( $this->doPluginPrefix( 'bootstrap_wpadmin_css_fixes' ) ), $this->getController()->getVersion() );
			wp_enqueue_style( $sUnique );
		}

		public function enqueuePluginGlobalAdminCss() {
			$sUnique = $this->doPluginPrefix( 'global_plugin_css' );
			wp_register_style( $sUnique, $this->getController()->getPluginUrl_Css('global-plugin.css'), false, $this->getController()->getVersion() );
			wp_enqueue_style( $sUnique );
		}
	}

endif;

require_once( 'icwp-plugin-controller.php');
$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall( ICWP_WPSF_Plugin_Controller::GetInstance( ICWP_WPSF_Spec::GetInstance() ) );