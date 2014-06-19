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

if ( !class_exists('ICWP_OptionsHandler_AutoUpdates_V2') ):

class ICWP_OptionsHandler_AutoUpdates_V2 extends ICWP_OptionsHandler_Base_Wpsf {

	/**
	 * @var ICWP_WPSF_AutoUpdatesProcessor
	 */
	protected $oFeatureProcessor;
	
	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Automatic Updates');
		$this->sFeatureSlug = 'autoupdates';
		parent::__construct( $oPluginVo, $this->sFeatureSlug.'_options' );
	}

	/**
	 * @return ICWP_WPSF_AutoUpdatesProcessor|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( dirname(__FILE__).'/icwp-processor-autoupdates.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_AutoUpdatesProcessor( $this );
		}
		return $this->oFeatureProcessor;
	}

	public function doPrePluginOptionsSave() {}
	
	public function defineOptions() {

		$aAutoUpdatesBase = array(
			'section_title' => _wpsf__('Enable Automatic Updates Section'),
			'section_options' => array(
				array(
					'enable_autoupdates',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Enable Auto Updates' ),
					_wpsf__( 'Enable (or Disable) The Simple Firewall Automatic Updates Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('Automatic Updates') ),
					'<a href="http://icwp.io/3w" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);
		$aAutoUpdateAll = array(
			'section_title' => _wpsf__('Disable ALL WordPress Automatic Updates'),
			'section_options' => array(
				array(
					'enable_autoupdate_disable_all',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Disable All' ),
					_wpsf__( 'Completely Disable WordPress Automatic Updates' ),
					_wpsf__( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!' ),
					'<a href="http://icwp.io/3v" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);
		$aAutoUpdatePlugin = array(
			'section_title' => _wpsf__('Automatic Plugin Self-Update'),
			'section_options' => array(
				array(
					'autoupdate_plugin_self',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Auto Update Plugin' ),
					_wpsf__( 'Always Automatically Update This Plugin' ),
					_wpsf__( 'Regardless of any component settings below, automatically update the WordPress Simple Firewall plugin.' ),
					'<a href="http://icwp.io/3u" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);
		$aAutoUpdateOptions = array( 'select',
			array( 'core_never',		_wpsf__('Never') ),
			array( 'core_minor',		_wpsf__('Minor Versions Only') ),
			array( 'core_major', 		_wpsf__('Major and Minor Versions') ),
		);
		$aAutoUpdateComponents = array(
			'section_title' => _wpsf__('Choose Which WordPress Components To Allow Automatic Updates'),
			'section_options' => array(
				array(
					'autoupdate_core',
					'',
					'core_minor',
					$aAutoUpdateOptions,
					_wpsf__( 'WordPress Core Updates' ),
					_wpsf__( 'Decide how the WordPress Core will automatically update, if at all' ),
					_wpsf__( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).' ),
					'<a href="http://icwp.io/3x" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_autoupdate_translations',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Translations' ),
					_wpsf__( 'Automatically Update Translations' ),
					_wpsf__( 'Note: Automatic updates for translations are enabled on WordPress by default.' )
				),
				array(
					'enable_autoupdate_plugins',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Plugins' ),
					_wpsf__( 'Automatically Update Plugins' ),
					_wpsf__( 'Note: Automatic updates for plugins are disabled on WordPress by default.' )
				),
				array(
					'enable_autoupdate_themes',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Themes' ),
					_wpsf__( 'Automatically Update Themes' ),
					_wpsf__( 'Note: Automatic updates for themes are disabled on WordPress by default.' )
				),
				array(
					'enable_autoupdate_ignore_vcs',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Ignore Version Control' ),
					_wpsf__( 'Ignore Version Control Systems Such As GIT and SVN' ),
					_wpsf__( 'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates.' )
				)
			)
		);
		
		$aAutoUpdateEmail = array(
			'section_title' => _wpsf__('Automatic Update Email Notifications'),
			'section_options' => array(
				array(
					'enable_upgrade_notification_email',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Send Report Email' ),
					_wpsf__( 'Send email notices after automatic updates' ),
					_wpsf__( 'You can turn on/off email notices from automatic updates by un/checking this box.' )
				),
				array(
					'override_email_address',
					'',
					'',
					'email',
					_wpsf__( 'Report Email Address' ),
					_wpsf__( 'Where to send upgrade notification reports' ),
					_wpsf__( 'If this is empty, it will default to the Site admin email address' )
				)
			)
		);

		$this->m_aOptions = array(
			$aAutoUpdatesBase,
			$aAutoUpdateAll,
			$aAutoUpdatePlugin,
			$aAutoUpdateComponents,
			$aAutoUpdateEmail
		);
	}
}

endif;

class ICWP_OptionsHandler_AutoUpdates extends ICWP_OptionsHandler_AutoUpdates_V2 { }