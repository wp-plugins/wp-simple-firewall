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

if ( !class_exists('ICWP_OptionsHandler_PrivacyProtect') ):

class ICWP_OptionsHandler_PrivacyProtect extends ICWP_OptionsHandler_Base_Wpsf {
	
	const StoreName = 'privacyprotect_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function doPrePluginOptionsSave() { }

	/**
	 * @return bool|void
	 */
	public function defineOptions() {

		$aOptionsBase = array(
			'section_title' => _wpsf__( 'Enable Privacy Protection' ),
			'section_options' => array(
				array(
					'enable_privacy_protect',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Privacy Protection') ),
					sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), _wpsf__('Privacy Protection') ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Privacy Protection feature, or enable your selected Privacy Protection options' ),
					'<a href="http://icwp.io/3y" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			),
		);

		$this->m_aOptions = array(
			$aOptionsBase
		);
	}

	public function updateHandler() {
		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
	}
}

endif;