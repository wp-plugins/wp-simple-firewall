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

if ( !class_exists('ICWP_OptionsHandler_PrivacyProtect') ):

class ICWP_OptionsHandler_PrivacyProtect extends ICWP_OptionsHandler_Base_Wpsf {
	
	const StoreName = 'privacyprotect_options';
	
	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::StoreName );

		$this->sFeatureName = _wpsf__('Privacy Protect');
		$this->sFeatureSlug = 'privacy_protect';
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
		$aFurtherOptions = array(
			'section_title' => _wpsf__( 'Data Filtering and Logging Options' ),
			'section_options' => array(
				array(
					'ignore_local_requests',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Ignore Local Requests' ),
					_wpsf__( 'Ignore Requests This Site Makes To Itself' ),
					_wpsf__( 'Does not log any requests this site makes to itself - for example the WP Cron' ),
					'<a href="http://icwp.io/3y" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'filter_wordpressorg_update_data',
					'',
					'Y',
					'checkbox',
					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('WordPress.org Privacy') ),
					_wpsf__( 'Enable Filtering Of Identifiable Data Sent To WordPress.org' ),
					_wpsf__( 'With this option enabled, any identifiable information about your site will be stripped from the web requests made to WordPress.org' ),
					'<a href="http://icwp.io/3y" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'filter_site_url',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Site URL Filter') ),
					_wpsf__( 'Enable Filtering Of Your Site URL From All Web Calls' ),
					_wpsf__( 'With this option enabled, all web calls made that contain your site URL will filter this URL and replace it with a random string' ),
					'<a href="http://icwp.io/3y" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);

		$this->m_aOptions = array(
			$aOptionsBase,
			$aFurtherOptions
		);
	}
}

endif;