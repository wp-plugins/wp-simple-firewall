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

if ( !class_exists('ICWP_OptionsHandler_AutoUpdates') ):

class ICWP_OptionsHandler_AutoUpdates extends ICWP_OptionsHandler_Base_V1 {
	
	const StoreName = 'autoupdates_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
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
					_wpsf__( 'Regardless of any other settings, this option will turn off the Auto Updates feature, or enable your selected Auto Updates options.' )
				)
			)
		);
		$aAutoUpdatePlugin = array(
			'section_title' => _wpsf__('Automatic Plugin Self-Update'),
			'section_options' => array(
				array(
					'autoupdate_plugin_wpsf',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Auto Update Plugin' ),
					_wpsf__( 'Always Automatically Update This Plugin' ),
					_wpsf__( 'Regardless of any component settings below, automatically update the WordPress Simple Firewall plugin.' )
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
					_wpsf__( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).' )
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
					_wpsf__( 'When selected, regardless of any other settings, all WordPress automatic updates on this site will be completely disabled!' )
				)
			)
		);

		$this->m_aOptions = array(
			$aAutoUpdatesBase,
			$aAutoUpdateAll,
			$aAutoUpdatePlugin,
			$aAutoUpdateComponents,
		);
	}

	public function updateHandler() {

		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
		if ( version_compare( $sCurrentVersion, '1.9.0', '<' ) ) {
		}//v1.9.0
	}
}

endif;