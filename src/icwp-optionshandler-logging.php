<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
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

if ( !class_exists('ICWP_OptionsHandler_Logging') ):

class ICWP_OptionsHandler_Logging extends ICWP_OptionsHandler_Base_Wpsf {

	const StoreName = 'logging_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !isset( $this->m_aOptionsKeys ) ) {
			$this->m_aOptionsKeys = array(
				'enable_logging'
			);
		}
	}
	
	public function defineOptions() {
		$aBase = array(
			'section_title' => _wpsf__( 'Enable Logging' ),
			'section_options' => array(
				array(
					'enable_logging',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Enable Logging' ),
					_wpsf__( 'Enable (or Disable) The Plugin Logging Feature.' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Logging system, or enable your chosen Logging options.' )
				)
			)
		);
		$this->m_aOptions = array(
			$aBase
		);
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() { }
}

endif;
