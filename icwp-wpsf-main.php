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

class ICWP_Wordpress_Simple_Firewall extends ICWP_Pure_Base_V5 {

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
	protected $pFeatureHandlerLogging;

	/**
	 */
	public function __construct( ICWP_Wordpress_Simple_Firewall_Plugin $oPluginVo ) {
		parent::__construct( $oPluginVo );

		$this->loadAllFeatures();
		add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'hasPermissionToView' ) );
		add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'hasPermissionToSubmit' ) );
	}

	public function onWpActivatePlugin() {
		$this->loadAllFeatures( true, true );
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Plugin
	 */
	protected function loadCorePluginFeature() {
		if ( isset( $this->oPluginOptions ) ) {
			return $this->oPluginOptions;
		}
		return $this->loadFeatureHandler( 'plugin' );
	}

	/**
	 * @param bool $fRecreate
	 * @param bool $fFullBuild
	 * @return bool
	 */
	protected function loadAllFeatures( $fRecreate = false, $fFullBuild = false ) {

		$oMainPluginFeature = $this->loadCorePluginFeature();
		$aPluginFeatures = $oMainPluginFeature->getActivePluginFeatures();

		$fSuccess = true;
		foreach( $aPluginFeatures as $sSlug => $sStorageKey ) {
			try {
				$this->loadFeatureHandler( $sSlug, $fRecreate, $fFullBuild );
				$fSuccess = true;
			}
			catch( Exception $oE ) {
				wp_die( $oE->getMessage() );
			}
		}
		return $fSuccess;
	}

	/**
	 * @param string $sFeatureSlug
	 * @param bool $fRecreate
	 * @param bool $fFullBuild
	 * @return mixed
	 * @throws Exception
	 */
	protected function loadFeatureHandler( $sFeatureSlug, $fRecreate = false, $fFullBuild = false ) {

		$sFeatureName = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $sFeatureSlug ) ) );
		$sOptionsVarName = sprintf( 'oFeatureHandler%s', $sFeatureName ); // e.g. oFeatureHandlerOptions

		if ( isset( $this->{$sOptionsVarName} ) ) {
			return $this->{$sOptionsVarName};
		}

		$sSourceFile = $this->oPluginVo->getSourceDir(). sprintf( 'icwp-optionshandler-%s.php', $sFeatureSlug ); // e.g. icwp-optionshandler-plugin.php
		$sClassName = sprintf( 'ICWP_WPSF_FeatureHandler_%s', $sFeatureName ); // e.g. ICWP_WPSF_FeatureHandler_Plugin

		$oFs = $this->loadWpFilesystem();
		if ( !$oFs->exists( $sSourceFile ) ) {
			throw new Exception( sprintf( 'Source File For Feature "%s" Does NOT Exist. You should re-install the Simple Firewall plugin.', $sFeatureName ) );
		}

		require_once( $sSourceFile );
		if ( $fRecreate || !isset( $this->{$sOptionsVarName} ) ) {
			$this->{$sOptionsVarName} = new $sClassName( $this->oPluginVo );
		}
		if ( $fFullBuild ) {
			$this->{$sOptionsVarName}->buildOptions();
		}
		return $this->{$sOptionsVarName};
	}

	/**
	 * @param array $aItems
	 * @return array $aItems
	 */
	public function filter_addExtraAdminMenuItems( $aItems ) {
		$aItems[ _wpsf__('Firewall Log' ) ] = array( 'Firewall Log', $this->getSubmenuId('firewall_log'), array( $this, 'onDisplayAll' ) );
		$aItems[ _wpsf__('Audit Trail Viewer' ) ] = array( 'Audit Trail Viewer', $this->getSubmenuId('audit_trail_viewer'), array( $this, 'onDisplayAll' ) );
		return $aItems;
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
			case 'privacy_protect_log' :
				$this->onDisplayPrivacyProtectLog();
				break;
			case 'firewall_log' :
				$this->onDisplayFirewallLog();
				break;
			case 'audit_trail_viewer' :
				$this->onDisplayAuditTrailViewer();
				break;
			default:
				$this->getFeatureHandler_MainPlugin()->displayFeatureConfigPage();
				break;
		}
	}

	protected function onDisplayPrivacyProtectLog() {

		$oPrivacyProcessor = $this->getProcessor_PrivacyProtect();
		$aData = array(
			'urlrequests_log'	=> $oPrivacyProcessor->getLogs( true )
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( $this->doPluginPrefix( 'privacy_protect_log_index' ), $aData );
	}

	protected function onDisplayFirewallLog() {

		$this->loadFeatureHandler( 'firewall' );
		$aIpWhitelist = $this->oFirewallOptions->getOpt( 'ips_whitelist' );
		$aIpBlacklist = $this->oFirewallOptions->getOpt( 'ips_blacklist' );

		$oLoggingProcessor = $this->getProcessor_Logging();
		$aLogData = $oLoggingProcessor->getLogs( true );

		$aData = array(
			'sFeatureName'		=> _wpsf__('Firewall Log'),
			'firewall_log'		=> $aLogData,
			'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
			'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( $this->doPluginPrefix( 'firewall_log_index' ), $aData );
	}

	protected function onDisplayAuditTrailViewer() {

		$oAuditTrail = $this->getProcessor_AuditTrail();
		$aAuditData = $oAuditTrail->getAllAuditEntries();

		$aAuditDataUser = array();
		$aAuditDataPlugin = array();
		$aAuditDataTheme = array();
		foreach( $aAuditData as $aAudit ) {
			if ( $aAudit['context'] == 'user' ) {
				$aAuditDataUser[] = $aAudit;
			}
			if ( $aAudit['context'] == 'plugin' ) {
				$aAuditDataPlugin[] = $aAudit;
			}
			if ( $aAudit['context'] == 'theme' ) {
				$aAuditDataTheme[] = $aAudit;
			}
		}

		$aData = array(
			'sFeatureName'		=> _wpsf__('Audit Trail Viewer'),
			'aAuditDataUser'	=> $aAuditDataUser,
			'aAuditDataPlugin'	=> $aAuditDataPlugin,
			'aAuditDataTheme'	=> $aAuditDataTheme
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( $this->doPluginPrefix( 'audit_trail_viewer_index' ), $aData );
	}

	public function onWpAdminInit() {
		parent::onWpAdminInit();

		$oDp = $this->loadDataProcessor();
		if ( $this->isValidAdminArea() ) {
			//Someone clicked the button to acknowledge the update
			$sMetaFlag = $this->doPluginPrefix( 'hide_update_notice' );
			if ( $oDp->FetchRequest( $sMetaFlag ) == 1 ) {
				$this->updateVersionUserMeta();
				if ( $this->isShowMarketing() ) {
					wp_redirect( $this->getUrl_PluginDashboard() );
				}
				else {
					wp_redirect( network_admin_url( $_POST['redirect_page'] ) );
				}
			}

			$sMetaFlag = $this->doPluginPrefix( 'hide_translation_notice' );
			if ( $oDp->FetchRequest( $sMetaFlag ) == 1 ) {
				$this->updateTranslationNoticeShownUserMeta();
				wp_redirect( network_admin_url( $_POST['redirect_page'] ) );
			}

			$sMetaFlag = $this->doPluginPrefix( 'hide_mailing_list_signup' );
			if ( $oDp->FetchRequest( $sMetaFlag ) == 1 ) {
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

	/**
	 * @return string|void
	 */
	protected function getAdminNoticeHtml_OptionsUpdated() {
		$sAdminFeedbackNotice = $this->loadCorePluginFeature()->getOpt( 'feedback_admin_notice' );
		if ( !empty( $sAdminFeedbackNotice ) ) {
			$sNotice = '<p>'.$sAdminFeedbackNotice.'</p>';
			return $sNotice;
		}
	}

	/**
	 * @return bool
	 */
	protected function getShowAdminNotices() {
		return $this->loadCorePluginFeature()->getOpt('enable_upgrade_admin_notice') == 'Y';
	}

	/**
	 * @return int
	 */
	protected function getInstallationDays() {
		$nTimeInstalled = $this->loadCorePluginFeature()->getOpt( 'installation_time' );
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

	public function onWpDeactivatePlugin() {
		if ( $this->getFeatureHandler_MainPlugin()->getOpt( 'delete_on_deactivate' ) == 'Y' && current_user_can( $this->oPluginVo->getBasePermissions() ) ) {
			do_action( $this->doPluginPrefix( 'delete_plugin' ) );
		}
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_Plugin|null
	 */
	public function getFeatureHandler_MainPlugin() {
		return $this->loadFeatureHandler( 'plugin' );
	}

	/**
	 * @return ICWP_WPSF_FeatureHandler_AdminAccessRestriction|null
	 */
	public function getFeatureHandler_AdminAccessRestriction() {
		return $this->loadFeatureHandler( 'admin_access_restriction' );
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
		$this->loadFeatureHandler( 'firewall' );
		return $this->oFeatureHandlerFirewall->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect|null
	 */
	public function getProcessor_LoginProtect() {
		$this->loadFeatureHandler( 'login_protect' );
		return $this->oFeatureHandlerLoginProtect->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_Processor_Autoupdates|null
	 */
	public function getProcessor_Autoupdates() {
		$this->loadFeatureHandler( 'autoupdates' );
		return $this->oFeatureHandlerAutoupdates->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_Processor_PrivacyProtect|null
	 */
	public function getProcessor_PrivacyProtect() {
		$this->loadFeatureHandler( 'privacy_protect' );
		return $this->oFeatureHandlerPrivacyProtect->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail|null
	 */
	public function getProcessor_AuditTrail() {
		$this->loadFeatureHandler( 'audit_trail' );
		return $this->oFeatureHandlerAuditTrail->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_Processor_Logging|null
	 */
	public function getProcessor_Logging() {
		$this->loadFeatureHandler( 'logging' );
		return $this->oFeatureHandlerLogging->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_Processor_Email|null
	 */
	public function getProcessor_Email() {
		return $this->oFeatureHandlerEmail->getEmailProcessor();
	}
}

endif;
