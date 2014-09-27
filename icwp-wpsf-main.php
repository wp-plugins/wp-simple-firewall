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

require_once( dirname(__FILE__).ICWP_DS.'src'.ICWP_DS.'icwp-pure-base.php' );

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

	class ICWP_Wordpress_Simple_Firewall extends ICWP_Pure_Base_V6 {

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
		 */
		public function __construct( ICWP_WPSF_Plugin_Controller $oPluginVo ) {
			parent::__construct( $oPluginVo );

			$this->loadAllFeatures();
			add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'hasPermissionToView' ) );
			add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'hasPermissionToSubmit' ) );
			add_filter( $this->doPluginPrefix( 'plugin_update_message' ), array( $this, 'getPluginsListUpdateMessage' ) );
		}

		public function onWpActivatePlugin() {
			$this->loadAllFeatures( true, true );
		}

		/**
		 * @param bool $fRecreate
		 * @param bool $fFullBuild
		 * @return bool
		 */
		protected function loadAllFeatures( $fRecreate = false, $fFullBuild = false ) {

			$oMainPluginFeature = $this->getController()->loadCorePluginFeatureHandler();
			$aPluginFeatures = $oMainPluginFeature->getActivePluginFeatures();

			$fSuccess = true;
			foreach( $aPluginFeatures as $sSlug => $aFeatureProperties ) {
				try {
					$this->getController()->loadFeatureHandler( $aFeatureProperties, $fRecreate, $fFullBuild );
					$fSuccess = true;
				}
				catch( Exception $oE ) {
					wp_die( $oE->getMessage() );
				}
			}
			return $fSuccess;
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
			parent::onWpAdminInit();

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

		/**
		 * @return bool
		 */
		protected function isShowMarketing() {
			return apply_filters( $this->doPluginPrefix( 'show_marketing' ), true );
		}

		public function getPluginsListUpdateMessage( $sMessage ) {
			return _wpsf__( 'Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features.' );
		}

		/**
		 * @return bool
		 */
		protected function getShowAdminNotices() {
			return $this->getController()->loadCorePluginFeatureHandler()->getOpt( 'enable_upgrade_admin_notice' ) == 'Y';
		}

		protected function getAdminBarNodes() {
			return array(); //disabled for now
			$aMenu = array(
				'id'	=> $this->doPluginOptionPrefix( 'admin_menu' ),
				'title'	=> '<span class="pluginlogo_16">&nbsp;</span>'._wpsf__('Firewall').'',
				'href'	=> 'bob',
			);
			return array( $aMenu );
		}

		public function onWpDeactivatePlugin() {
			if ( $this->getController()->loadCorePluginFeatureHandler()->getOpt( 'delete_on_deactivate' ) == 'Y' && current_user_can( $this->getController()->getBasePermissions() ) ) {
				do_action( $this->doPluginPrefix( 'delete_plugin' ) );
			}
		}

		/**
		 * @return ICWP_WPSF_FeatureHandler_AdminAccessRestriction|null
		 */
		public function getFeatureHandler_AdminAccessRestriction() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'admin_access_restriction' ) );
		}

		/**
		 * @return ICWP_WPSF_FeatureHandler_AdminAccessRestriction|null
		 */
		public function getProcessor_AdminAccessRestriction() {
			return $this->getFeatureHandler_AdminAccessRestriction()->getProcessor();
		}

		/**
		 * @return ICWP_WPSF_Processor_Firewall|null
		 */
		public function getProcessor_Firewall() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'firewall' ) )->getProcessor();
		}

		/**
		 * @return ICWP_WPSF_Processor_LoginProtect|null
		 */
		public function getProcessor_LoginProtect() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'login_protect' ) )->getProcessor();
		}

		/**
		 * @return ICWP_WPSF_Processor_Autoupdates|null
		 */
		public function getProcessor_Autoupdates() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'autoupdates' ) )->getProcessor();
		}

		/**
		 * @return ICWP_WPSF_Processor_PrivacyProtect|null
		 */
		public function getProcessor_PrivacyProtect() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'privacy_protect' ) )->getProcessor();
		}

		/**
		 * @return ICWP_WPSF_Processor_AuditTrail|null
		 */
		public function getProcessor_AuditTrail() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'audit_trail' ) )->getProcessor();
		}

		/**
		 * @return ICWP_WPSF_Processor_Logging|null
		 */
		public function getProcessor_Logging() {
			return $this->getController()->loadFeatureHandler( array( 'slug' => 'logging' ) )->getProcessor();
		}
	}

endif;

require_once( 'icwp-plugin-controller.php');
$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall( ICWP_WPSF_Plugin_Controller::GetInstance( ICWP_WPSF_Spec::GetInstance() ) );