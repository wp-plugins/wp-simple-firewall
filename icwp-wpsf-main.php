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
	 * @var ICWP_OptionsHandler_AdminAccessRestriction
	 */
	protected $oAdminAccessRestrictionOptions;
	/**
	 * @var ICWP_OptionsHandler_Firewall
	 */
	protected $oFirewallOptions;
	/**
	 * @var ICWP_OptionsHandler_LoginProtect
	 */
	protected $oLoginProtectOptions;

	/**
	 * @var ICWP_OptionsHandler_PrivacyProtect
	 */
	protected $oPrivacyProtectOptions;

	/**
	 * @var ICWP_OptionsHandler_CommentsFilter
	 */
	protected $oCommentsFilterOptions;

	/**
	 * @var ICWP_OptionsHandler_Lockdown
	 */
	protected $oLockdownOptions;

	/**
	 * @var ICWP_OptionsHandler_AutoUpdates
	 */
	protected $oAutoUpdatesOptions;

	/**
	 * @var ICWP_OptionsHandler_Email
	 */
	protected $oEmailOptions;

	/**
	 * @var ICWP_OptionsHandler_Logging
	 */
	protected $oLoggingOptions;

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
				'admin_access_restriction'			=> 'AdminAccessRestriction',
				'firewall'			=> 'Firewall',
				'login_protect'		=> 'LoginProtect',
				'comments_filter'	=> 'CommentsFilter',
//				'privacy_protect'	=> 'PrivacyProtect',
				'autoupdates'		=> 'AutoUpdates',
				'lockdown'			=> 'Lockdown'
			),
			array(
				'oPluginMainOptions',
				'oAdminAccessRestrictionOptions',
				'oEmailOptions',
				'oFirewallOptions',
				'oLoginProtectOptions',
				'oCommentsFilterOptions',
				'oPrivacyProtectOptions',
				'oLockdownOptions',
				'oAutoUpdatesOptions'
			)
		);

		$this->loadOptionsHandler( 'all' );
		$this->fAutoPluginUpgrade = false && $this->oPluginMainOptions->getOpt( 'enable_auto_plugin_upgrade' ) == 'Y';

		// checks for filesystem based firewall overrides
		$this->override();

		add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'hasPermissionToView' ) );
		add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'hasPermissionToSubmit' ) );
		add_filter( 'pre_update_option', array($this, 'blockOptionsSaves'), 1, 3 );
	}

	/**
	 * @return string
	 */
	protected function override() {
		$sSetting = parent::override();
		if ( !empty( $sSetting ) ) {
			$this->oPluginMainOptions->setOpt( 'enable_admin_access_restriction', $sSetting );
			$this->oPluginMainOptions->savePluginOptions();
		}
		return $sSetting;
	}

	/**
	 * @param array $aItems
	 * @return array $aItems
	 */
	public function filter_addExtraAdminMenuItems( $aItems ) {
		$aItems[ _wpsf__('Firewall Log' ) ] = array( 'Firewall Log', $this->getSubmenuId('firewall_log'), array( $this, 'onDisplayAll' ) );
		return $aItems;
	}

	/**
	 */
	protected function handlePluginUpgrade() {
		parent::handlePluginUpgrade();

		$sCurrentPluginVersion = $this->oPluginMainOptions->getVersion();
		if ( $sCurrentPluginVersion !== $this->oPluginVo->getVersion() && current_user_can( $this->oPluginVo->getBasePermissions() ) ) { }
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
			default:
				die( 'Report Error 0x010 to support' );
				break;
		}
	}

	/**
	 * @param string $sSubmenu
	 * @return array
	 */
	protected function getBaseDisplayData( $sSubmenu = '' ) {
		$aBaseData = parent::getBaseDisplayData( $sSubmenu );
		$aBaseData['aMainOptions'] = $this->oPluginMainOptions->getPluginOptionsValues();
		return $aBaseData;
	}
//
//	public function onDisplayAccessKeyRequest() {
//		$aData = array(
//			'requested_page'	=> $this->getCurrentWpAdminPage()
//		);
//		$aData = array_merge( $this->getBaseDisplayData(), $aData );
//		$this->display( $this->doPluginPrefix( 'access_key_request_index', '_' ), $aData );
//	}
//
//	public function onDisplayMainMenu() {
//		$this->loadOptionsHandler( 'all', true );
//		$aAvailableOptions = array_merge( $this->oPluginMainOptions->getOptions(), $this->oEmailOptions->getOptions() );
//		$sMainOptions = $this->oPluginMainOptions->collateAllFormInputsForAllOptions();
//		$sEmailMainOptions = $this->oEmailOptions->collateAllFormInputsForAllOptions();
//		$sAllFormInputOptions = $sMainOptions.(ICWP_OptionsHandler_Base_Wpsf::CollateSeparator).$sEmailMainOptions;
//
//		$aData = array(
//			'aAllOptions'		=> $aAvailableOptions,
//			'all_options_input'	=> $sAllFormInputOptions,
//		);
//		$aData = array_merge( $this->getBaseDisplayData(), $aData );
//		$aData['aSummaryData'] = $this->getDashboardSummaryDisplayData();
//
//		if ( $this->getIsMainFeatureEnabled('firewall') ) {
//			$this->loadOptionsHandler( 'Firewall' );
//			$aData['aFirewallOptions'] = $this->oFirewallOptions->getPluginOptionsValues();
//		}
//		if ( $this->getIsMainFeatureEnabled('login_protect') ) {
//			$this->loadOptionsHandler( 'LoginProtect' );
//			$aData['aLoginProtectOptions'] = $this->oLoginProtectOptions->getPluginOptionsValues();
//		}
//		if ( $this->getIsMainFeatureEnabled('comments_filter') ) {
//			$this->loadOptionsHandler( 'CommentsFilter' );
//			$aData['aCommentsFilterOptions'] = $this->oCommentsFilterOptions->getPluginOptionsValues();
//		}
//		if ( $this->getIsMainFeatureEnabled('lockdown') ) {
//			$this->loadOptionsHandler( 'Lockdown' );
//			$aData['aLockdownOptions'] = $this->oLockdownOptions->getPluginOptionsValues();
//		}
//		if ( $this->getIsMainFeatureEnabled('autoupdates') ) {
//			$this->loadOptionsHandler( 'AutoUpdates' );
//			$aData['aAutoUpdatesOptions'] = $this->oAutoUpdatesOptions->getPluginOptionsValues();
//		}
//		$this->display( $this->doPluginPrefix( 'index', '_' ), $aData );
//	}
//
//	protected function getDashboardSummaryDisplayData() {
//
//		$aSummaryData = array();
//		$aSummaryData[] = array(
//			$this->oPluginMainOptions->getOpt( 'enable_admin_access_restriction' ) == 'Y',
//			_wpsf__('Admin Access Protection'),
//			$this->doPluginPrefix()
//		);
//
//		$aSummaryData[] = array(
//			$this->getIsMainFeatureEnabled('firewall'),
//			_wpsf__('Firewall'),
//			$this->doPluginPrefix( 'firewall' )
//		);
//
//		$aSummaryData[] = array(
//			$this->getIsMainFeatureEnabled('login_protect'),
//			_wpsf__('Login Protection'),
//			$this->doPluginPrefix( 'login_protect' )
//		);
//
//		$aSummaryData[] = array(
//			$this->getIsMainFeatureEnabled('comments_filter'),
//			_wpsf__('Comments Filter'),
//			$this->doPluginPrefix( 'comments_filter' )
//		);
//
//		$aSummaryData[] = array(
//			$this->getIsMainFeatureEnabled('autoupdates'),
//			_wpsf__('Auto Updates'),
//			$this->doPluginPrefix( 'autoupdates' )
//		);
//
//		$aSummaryData[] = array(
//			$this->getIsMainFeatureEnabled('lockdown'),
//			_wpsf__('Lock Down'),
//			$this->doPluginPrefix( 'lockdown' )
//		);
//
//		return $aSummaryData;
//	}

	protected function onDisplayPrivacyProtectLog() {

		$oPrivacyProcessor = $this->getProcessor_PrivacyProtect();
		$aData = array(
			'urlrequests_log'	=> $oPrivacyProcessor->getLogs( true )
		);
		$aData = array_merge( $this->getBaseDisplayData('privacy_protect_log'), $aData );
		$this->display( $this->doPluginPrefix( 'privacy_protect_log_index', '_' ), $aData );
	}

	protected function onDisplayFirewallLog() {

		$this->loadOptionsHandler( 'Firewall' );
		$aIpWhitelist = $this->oFirewallOptions->getOpt( 'ips_whitelist' );
		$aIpBlacklist = $this->oFirewallOptions->getOpt( 'ips_blacklist' );

		$oLoggingProcessor = $this->getProcessor_Logging();
		$aLogData = $oLoggingProcessor->getLogs( true );

		$aData = array(
			'firewall_log'		=> $aLogData,
			'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
			'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
		);
		$aData = array_merge( $this->getBaseDisplayData('firewall_log'), $aData );
		$this->display( $this->doPluginPrefix( 'firewall_log_index', '_' ), $aData );
	}

//	/**
//	 * @param bool $fPermission
//	 */
//	protected function setPermissionToSubmit( $fPermission = false ) {
//		if ( $fPermission ) {
//			$this->loadDataProcessor();
//			$sValue = md5( $this->oPluginMainOptions->getOpt( 'admin_access_key' ).ICWP_WPSF_DataProcessor::GetVisitorIpAddress() );
//			$sTimeout = $this->oPluginMainOptions->getOpt( 'admin_access_timeout' ) * 60;
//			$_COOKIE[ self::AdminAccessKeyCookieName ] = $sValue;
//			setcookie( self::AdminAccessKeyCookieName, $sValue, time()+$sTimeout, COOKIEPATH, COOKIE_DOMAIN, false );
//		}
//		else {
//			unset( $_COOKIE[ self::AdminAccessKeyCookieName ] );
//			setcookie( self::AdminAccessKeyCookieName, "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
//		}
//	}

//	protected function handleSubmit_AccessKeyRequest() {
//		//Ensures we're actually getting this request from WP.
//		check_admin_referer( $this->getPluginPrefix() );
//
//		$sAccessKey = md5( trim( $this->fetchPost( $this->doPluginPrefix('admin_access_key_request', '_') ) ) );
//		$sStoredAccessKey = $this->oPluginMainOptions->getOpt( 'admin_access_key' );
//
//		if ( $sAccessKey === $sStoredAccessKey ) {
//			$this->setPermissionToSubmit( true );
//			header( 'Location: '.$this->getUrl_PluginDashboard( sanitize_text_field( $this->fetchPost('icwp_wpsf_requested_page') ) ) );
//			exit();
//		}
//		return false;
//	}

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
		return apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true )? $mValue : $mOldValue;
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
		$sAdminFeedbackNotice = $this->oPluginMainOptions->getOpt( 'feedback_admin_notice' );
		if ( !empty( $sAdminFeedbackNotice ) ) {
			$sNotice = '<p>'.$sAdminFeedbackNotice.'</p>';
			return $sNotice;
			$this->oPluginMainOptions->setOpt( 'feedback_admin_notice', '' );
		}
	}

	/**
	 *
	 */
	protected function getShowAdminNotices() {
		return $this->oPluginMainOptions->getOpt('enable_upgrade_admin_notice') == 'Y';
	}

	/**
	 * @return int
	 */
	protected function getInstallationDays() {
		$nTimeInstalled = $this->oPluginMainOptions->getOpt( 'installation_time' );
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
			do_action( $this->doPluginPrefix( 'delete_plugin_options' ) );
		}
	}

	/**
	 * @return ICWP_OptionsHandler_AdminAccessRestriction|null
	 */
	public function getFeatureHandler_MainPlugin() {
		return $this->loadOptionsHandler( 'PluginMain' );
	}

	/**
	 * @return ICWP_OptionsHandler_AdminAccessRestriction|null
	 */
	public function getFeatureHandler_AdminAccessRestriction() {
		return $this->loadOptionsHandler( 'AdminAccessRestriction' );
	}

	/**
	 * @return ICWP_OptionsHandler_AdminAccessRestriction|null
	 */
	public function getProcessor_AdminAccessRestriction() {
		return $this->getFeatureHandler_AdminAccessRestriction()->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_FirewallProcessor|null
	 */
	public function getProcessor_Firewall() {
		$this->loadOptionsHandler('Firewall');
		return $this->oFirewallOptions->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_LoginProtectProcessor|null
	 */
	public function getProcessor_LoginProtect() {
		$this->loadOptionsHandler('LoginProtect');
		return $this->oLoginProtectOptions->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_AutoUpdatesProcessor|null
	 */
	public function getProcessor_Autoupdates() {
		$this->loadOptionsHandler('AutoUpdates');
		return $this->oAutoUpdatesOptions->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_PrivacyProtectProcessor|null
	 */
	public function getProcessor_PrivacyProtect() {
		$this->loadOptionsHandler( 'PrivacyProtect' );
		return $this->oPrivacyProtectOptions->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_LoggingProcessor|null
	 */
	public function getProcessor_Logging() {
		$this->loadOptionsHandler('Logging');
		return $this->oLoggingOptions->getProcessor();
	}

	/**
	 * @return ICWP_WPSF_EmailProcessor|null
	 */
	public function getProcessor_Email() {
		return $this->oPluginMainOptions->getEmailProcessor();
	}
}

endif;
