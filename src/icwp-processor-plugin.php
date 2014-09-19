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

require_once( dirname(__FILE__).'/icwp-processor-base.php' );

if ( !class_exists('ICWP_WPSF_Processor_Plugin') ):

class ICWP_WPSF_Processor_Plugin extends ICWP_WPSF_Processor_Base {

	/**
	 * @param ICWP_WPSF_FeatureHandler_Plugin $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Plugin $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
	}

	/**
	 */
	public function run() {
		$oFO = $this->getFeatureOptions();
		$this->removePluginConflicts();
		add_filter( $oFO->doPluginPrefix( 'show_marketing' ), array( $this, 'getIsShowMarketing' ) );

		if ( $this->isValidAdminArea() && $this->getIfShowAdminNotices() ) {
			add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeForceOffActive' ) );
			add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeMailingListSignup' ) );
			// TODO: this->
//			add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticePostPluginUpgrade' ) );
//			add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticeTranslations' ) );
//			add_filter( $oFO->doPluginPrefix( 'admin_notices' ), array( $this, 'adminNoticePluginUpgradeAvailable' ) );
		}
	}

	/**
	 * @param array $aAdminNotices
	 * @return array
	 */
	public function adminNoticeForceOffActive( $aAdminNotices ) {
		$fOverride = $this->getFeatureOptions()->getIfOverride();
		if ( !$fOverride ) {
			return $aAdminNotices;
		}

		ob_start();
		include( $this->getFeatureOptions()->getViewSnippet( 'admin_notice_override.php' ) );
		$sNoticeMessage = ob_get_contents();
		ob_end_clean();

		$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'error', false );
		return $aAdminNotices;
	}

	/**
	 * @param array $aAdminNotices
	 * @return array
	 */
	public function adminNoticeMailingListSignup( $aAdminNotices ) {

		$nDays = $this->getInstallationDays();
		if ( $nDays < 2 ) {
			return $aAdminNotices;
		}

		$sCurrentMetaValue = $this->getFeatureOptions()->getUserMeta( 'plugin_mailing_list_signup' );
		if ( $sCurrentMetaValue == 'Y' ) {
			return;
		}

		$sLink_HideNotice = $this->getUrl_PluginDashboard().'&'.$this->getFeatureOptions()->doPluginPrefix( 'hide_mailing_list_signup' ).'=1';
		ob_start();
		include( $this->getFeatureOptions()->getViewSnippet( 'admin_notice_mailchimp.php' ) );
		$sNoticeMessage = ob_get_contents();
		ob_end_clean();

		$aAdminNotices[] = $this->getAdminNoticeHtml( $sNoticeMessage, 'updated', false );
		return $aAdminNotices;
	}

	public function adminNoticePostPluginUpgrade( $aAdminNotices ) {
		return $aAdminNotices;
	}

	public function adminNoticePluginUpgradeAvailable( $aAdminNotices ) {
		return $aAdminNotices;
	}

	public function adminNoticeTranslations( $aAdminNotices ) {
		return $aAdminNotices;
	}

	/**
	 * @param $fShow
	 * @return bool
	 */
	public function getIsShowMarketing( $fShow ) {
		if ( !$fShow ) {
			return $fShow;
		}

		$oWpFunctions = $this->loadWpFunctionsProcessor();
		if ( class_exists( 'Worpit_Plugin' ) ) {
			if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
				$fShow = !Worpit_Plugin::IsLinked();
			}
			else if ( $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
				&& $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {

				$fShow = false;
			}
		}

		if ( $this->getInstallationDays() < 1 ) {
			$fShow = false;
		}

		return $fShow;
	}

	/**
	 * @return int
	 */
	protected function getInstallationDays() {
		$nTimeInstalled = $this->getFeatureOptions()->getOpt( 'installation_time' );
		if ( empty( $nTimeInstalled ) ) {
			return 0;
		}
		return round( ( time() - $nTimeInstalled ) / DAY_IN_SECONDS );
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
	 * @return bool
	 */
	protected function getIfShowAdminNotices() {
		return $this->getFeatureOptions()->getOptIs( 'enable_upgrade_admin_notice', 'Y' );
	}

	/**
	 * Provides the basic HTML template for printing a WordPress Admin Notices
	 *
	 * @param $sNotice - The message to be displayed.
	 * @param $sMessageClass - either error or updated
	 * @param $infPrint - if true, will echo. false will return the string
	 * @return boolean|string
	 */
	protected function getAdminNoticeHtml( $sNotice = '', $sMessageClass = 'updated', $infPrint = false ) {
		$sWrapper = '<div class="%s icwp-admin-notice"><style>#message form { margin: 0px; padding-bottom: 8px; }</style>%s</div>';
		$sFullNotice = sprintf( $sWrapper, $sMessageClass, $sNotice );
		if ( $infPrint ) {
			echo $sFullNotice;
			return true;
		} else {
			return $sFullNotice;
		}
	}

	/**
	 * @param string $sFeaturePage - leave empty to get the main dashboard
	 * @return mixed
	 */
	protected function getUrl_PluginDashboard( $sFeaturePage = '' ) {
		return network_admin_url( sprintf( 'admin.php?page=%s', $this->getFeatureOptions()->doPluginPrefix( $sFeaturePage ) ) );
	}
}

endif;
