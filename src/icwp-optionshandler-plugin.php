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

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_WPSF_FeatureHandler_Plugin') ):

class ICWP_WPSF_FeatureHandler_Plugin extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var ICWP_WPSF_Processor_Plugin
	 */
	protected $oFeatureProcessor;

	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Dashboard');
		$this->sFeatureSlug = 'plugin';
		parent::__construct( $oPluginVo, 'plugin' );

		add_action( 'deactivate_plugin', array( $this, 'onWpHookDeactivatePlugin' ), 1, 1 );
		add_filter( $this->doPluginPrefix( 'report_email_address' ), array( $this, 'getPluginReportEmail' ) );
	}

	/**
	 * @return ICWP_WPSF_Processor_Plugin|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir().'icwp-processor-plugin.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_Plugin( $this );
		}
		return $this->oFeatureProcessor;
	}

	public function getActivePluginFeatures() {
		$aActiveFeatures = $this->getOptionsVo()->getOptionRawConfig( 'active_plugin_features' );
		$aPluginFeatures = array();
		foreach( $aActiveFeatures['value'] as $aFeature ) {
			$aPluginFeatures[ $aFeature['slug'] ] = $aFeature['storage_key'];
		}
		return $aPluginFeatures;
	}

	/**
	 * @return mixed
	 */
	public function getIsMainFeatureEnabled() {
		return true;
	}

	/**
	 * @param array $aSummaryData
	 * @return array
	 */
	public function filter_getFeatureSummaryData( $aSummaryData ) {
		return $aSummaryData;
	}

	/**
	 */
	public function displayFeatureConfigPage( ) {

		if ( !apply_filters( $this->doPluginPrefix( 'has_permission_to_view' ), true ) ) {
			$this->displayViewAccessRestrictedPage();
			return;
		}

		$aPluginSummaryData = apply_filters( $this->doPluginPrefix( 'get_feature_summary_data' ), array() );

		$aData = array(
			'aSummaryData'		=> $aPluginSummaryData
		);
		$aData = array_merge( $this->getBaseDisplayData(), $aData );
		$this->display( $aData );
	}

	/**
	 * Hooked to 'deactivate_plugin' and can be used to interrupt the deactivation of this plugin.
	 * @param string $insPlugin
	 */
	public function onWpHookDeactivatePlugin( $insPlugin ) {
		if ( strpos( $this->oPluginVo->getRootFile(), $insPlugin ) !== false ) {
			if ( !apply_filters( $this->doPluginPrefix( 'has_permission_to_submit' ), true ) ) {
				wp_die(
					_wpsf__( 'Sorry, you do not have permission to disable this plugin.')
					. _wpsf__( 'You need to authenticate first.' )
				);
			}
		}
	}

	/**
	 * @param $sEmail
	 * @return string
	 */
	public function getPluginReportEmail( $sEmail ) {
		$sReportEmail = $this->getOpt( 'block_send_email_address' );
		if ( !empty( $sReportEmail ) && is_email( $sReportEmail ) ) {
			$sEmail = $sReportEmail;
		}
		return $sEmail;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams['section_slug'];
		switch( $aOptionsParams['section_slug'] ) {

			case 'section_general_plugin_options' :
				$sTitle = _wpsf__( 'General Plugin Options' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams['section_title'] = $sTitle;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams['key'];
		switch( $sKey ) {

			case 'block_send_email_address' :
				$sName = _wpsf__( 'Report Email' );
				$sSummary = _wpsf__( 'Where to send email reports' );
				$sDescription = _wpsf__( 'If this is empty, it will default to the blog admin email address.' );
				break;

			case 'enable_upgrade_admin_notice' :
				$sName = _wpsf__( 'Plugin Notices' );
				$sSummary = _wpsf__( 'Display Notices For Updates' );
				$sDescription = _wpsf__( 'Disable this option to hide certain plugin admin notices about available updates and post-update notices' );
				break;

			case 'delete_on_deactivate' :
				$sName = _wpsf__( 'Delete Plugin Settings' );
				$sSummary = _wpsf__( 'Delete All Plugin Settings Upon Plugin Deactivation' );
				$sDescription = _wpsf__( 'Careful: Removes all plugin options when you deactivate the plugin' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams['name'] = $sName;
		$aOptionsParams['summary'] = $sSummary;
		$aOptionsParams['description'] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		$this->setOpt( 'enable_logging', 'Y' );

		$nInstalledAt = $this->getOpt( 'installation_time' );
		if ( empty($nInstalledAt) || $nInstalledAt <= 0 ) {
			$this->setOpt( 'installation_time', time() );
		}
	}

	protected function updateHandler() {
		parent::updateHandler();
		if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
			$aAllOptions = apply_filters( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array() );
			$this->setOpt( 'block_send_email_address', $aAllOptions['block_send_email_address'] );
		}
	}
}

endif;