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

if ( !class_exists('ICWP_OptionsHandler_Email') ):

class ICWP_OptionsHandler_Email extends ICWP_OptionsHandler_Base_Wpsf {

	/**
	 * @var ICWP_WPSF_EmailProcessor
	 */
	protected $oEmailProcessor;

	/**
	 * @param $oPluginVo
	 */
	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Email');
		$this->sFeatureSlug = 'email';
		$this->fShowFeatureMenuItem = false;
		parent::__construct( $oPluginVo, $this->sFeatureSlug.'_options' );
	}

	/**
	 * @return ICWP_WPSF_EmailProcessor|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oEmailProcessor ) ) {
			require_once( dirname(__FILE__).'/icwp-processor-logging.php' );
			$this->oEmailProcessor = new ICWP_WPSF_EmailProcessor( $this );
		}
		return $this->oEmailProcessor;
	}

	/**
	 * @return bool|void
	 */
	public function defineOptions() {
		$aEmail = array(
			'section_title' => _wpsf__( 'Email Options' ),
			'section_options' => array(
				array(
					'block_send_email_address',
					'',
					'',
					'email',
					_wpsf__( 'Report Email' ),
					_wpsf__( 'Where to send email reports' ),
					_wpsf__( 'If this is empty, it will default to the blog admin email address' )
				),
				array(
					'send_email_throttle_limit',
					'',
					'10',
					'integer',
					_wpsf__( 'Email Throttle Limit' ),
					_wpsf__( 'Limit Emails Per Second' ),
					_wpsf__( 'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10' )
				)
			)
		);

		$this->m_aOptions = array(
			$aEmail
		);
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		$sEmail = $this->getOpt( 'block_send_email_address');
		if ( empty( $sEmail ) || !is_email( $sEmail ) ) {
			$sEmail = get_option('admin_email');
		}
		if ( is_email( $sEmail ) ) {
			$this->setOpt( 'block_send_email_address', $sEmail );
		}

		$sLimit = $this->getOpt( 'send_email_throttle_limit' );
		if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
			$sLimit = 0;
		}
		$this->setOpt( 'send_email_throttle_limit', $sLimit );
	}
	
}

endif;