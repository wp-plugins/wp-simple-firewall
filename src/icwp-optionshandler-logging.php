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

if ( !class_exists('ICWP_WPSF_FeatureHandler_Logging') ):

class ICWP_WPSF_FeatureHandler_Logging extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var ICWP_WPSF_Processor_Logging
	 */
	protected $oFeatureProcessor;

	/**
	 * @param $oPluginVo
	 */
	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Logging');
		$this->sFeatureSlug = 'logging';
		parent::__construct( $oPluginVo );
	}

	/**
	 * @return ICWP_WPSF_Processor_Logging|null
	 */
	protected function loadFeatureProcessor() {

		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir( sprintf( 'icwp-processor-%s.php', $this->getFeatureSlug() ) ) );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_Logging( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams['section_slug'];
		switch( $aOptionsParams['section_slug'] ) {

			case 'section_logging_options' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('Logging') );
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

			case 'enable_logging' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
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
	 * @return string
	 */
	public function getGeneralLoggingTableName() {
		return $this->doPluginPrefix( $this->getOpt( 'general_logging_table_name' ), '_' );
	}
}

endif;
