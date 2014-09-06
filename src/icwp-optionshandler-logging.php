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
		$this->fShowFeatureMenuItem = false;
		parent::__construct( $oPluginVo );
	}

	/**
	 * @return ICWP_WPSF_Processor_Logging|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir().'icwp-processor-logging.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_Logging( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 * @return array
	 */
	protected function getOptionsDefinitions() {
		$aBase = array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('Logging') ),
			'section_options' => array(
				array(
					'enable_logging',
					'',
					'Y',
					'checkbox',
					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Logging') ),
					_wpsf__( 'Enable (or Disable) The Plugin Logging Feature.' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Logging system, or enable your chosen Logging options.' )
				)
			)
		);

		$aOptionsDefinitions = array(
			$aBase
		);
		return $aOptionsDefinitions;
	}

}

endif;
