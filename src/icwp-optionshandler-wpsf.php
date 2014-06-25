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

if ( !class_exists('ICWP_OptionsHandler_Wpsf') ):

class ICWP_OptionsHandler_Wpsf extends ICWP_OptionsHandler_Base_Wpsf {

	const Default_AccessKeyTimeout = 30;
	
	/**
	 * @var ICWP_WPSF_PluginProcessor
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
	 * @return ICWP_WPSF_PluginProcessor|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( dirname(__FILE__).'/icwp-processor-plugin.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_PluginProcessor( $this );
		}
		return $this->oFeatureProcessor;
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
			'aAllOptions'		=> $this->getOptions(),
			'all_options_input'	=> $this->collateAllFormInputsForAllOptions(),
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
				wp_die( 'Sorry, you do not have permission to disable this plugin. You need to authenticate first.' );
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
	 * @return array
	 */
	protected function getOptionsDefinitions() {
		$aGeneral = array(
			'section_title' => _wpsf__( 'General Plugin Options' ),
			'section_options' => array(
				array(
					'block_send_email_address',
					'',
					'',
					'email',
					_wpsf__( 'Report Email' ),
					_wpsf__( 'Where to send email reports from the Firewall' ),
					_wpsf__( 'If this is empty, it will default to the blog admin email address' )
				),
				array(
					'enable_upgrade_admin_notice',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Plugin Notices' ),
					_wpsf__( 'Display Notices For Updates' ),
					_wpsf__( 'Disable this option to hide certain plugin admin notices about available updates and post-update notices' )
				),
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Delete Plugin Settings' ),
					_wpsf__( 'Delete All Plugin Settings Upon Plugin Deactivation' ),
					_wpsf__( 'Careful: Removes all plugin options when you deactivate the plugin' )
				)
			)
		);

		$aOptionsDefinitions = array(
			$aGeneral
		);
		return $aOptionsDefinitions;
	}

	/**
	 * @return array
	 */
	protected function getNonUiOptions() {
		$aNonUiOptions = array(
			'installation_time',
			'secret_key',
			'feedback_admin_notice',
			'update_success_tracker',
			'capability_can_disk_write',
			'capability_can_remote_get'
		);
		return $aNonUiOptions;
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		
		if ( $this->getOpt( 'admin_access_key_timeout' ) <= 0 ) {
			$this->setOpt( 'admin_access_key_timeout', self::Default_AccessKeyTimeout );
		}
		
		$sAccessKey = $this->getOpt( 'admin_access_key');
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}

		$this->setOpt( 'enable_logging', 'Y' );

		$nInstalledAt = $this->getOpt( 'installation_time' );
		if ( empty($nInstalledAt) || $nInstalledAt <= 0 ) {
			$this->setOpt( 'installation_time', time() );
		}
	}

	protected function updateHandler() {
		if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
			$aAllOptions = apply_filters( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array() );
			$this->setOpt( 'block_send_email_address', $aAllOptions['block_send_email_address'] );
		}
	}
}

endif;