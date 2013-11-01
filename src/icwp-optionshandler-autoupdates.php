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

class ICWP_OptionsHandler_AutoUpdates extends ICWP_OptionsHandler_Base_WPSF {
	
	const StoreName = 'autoupdates_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function doPrePluginOptionsSave() {}
	
	public function defineOptions() {

		$aAutoUpdatesBase = 	array(
			'section_title' => 'Enable Automatic Updates Section',
			'section_options' => array(
				array(
					'enable_autoupdates',
					'',
					'N',
					'checkbox',
					'Enable Auto Updates',
					'Enable (or Disable) The WordPress Automatic Updates Feature',
					'Regardless of any other settings, this option will turn Off the Auto Updates feature, or enable your selected Auto Updates options.'
				)
			)
		);
		$aAutoUpdateOptions = array( 'select',
			array( 'core_never',		'Never' ),
			array( 'core_minor',		'Minor Versions Only' ),
			array( 'core_major', 		'Major and Minor Versions' ),
		);
		$aAutoUpdateCore = array(
			'section_title' => 'Automatic Plugin Self-Update',
			'section_options' => array(
				array(
					'autoupdate_plugin_wpsf',
					'',
					'Y',
					'checkbox',
					'Auto Update Plugin',
					'Always Automatically Update This Plugin',
					'Regardless of any component settings below, automatically update the WordPress Simple Firewall plugin.'
				)
			)
		);
		$aAutoUpdateComponents = array(
			'section_title' => 'Choose Which WordPress Components To Allow Automatic Updates',
			'section_options' => array(
				array(
					'autoupdate_core',
					'',
					'core_minor',
					$aAutoUpdateOptions,
					'WordPress Core Updates',
					'Decide how the WordPress Core will automatically update, if at all.',
					'At least automatically upgrading minor versions is recommended (and is the WordPress default).'
				),
				array(
					'enable_autoupdate_translations',
					'',
					'Y',
					'checkbox',
					'Translations',
					'Automatically Update Translations',
					'Note: Automatic updates for translations are enabled on WordPress by default.'
				),
				array(
					'enable_autoupdate_plugins',
					'',
					'N',
					'checkbox',
					'Plugins',
					'Automatically Update Plugins',
					'Note: Automatic updates for plugins are disabled on WordPress by default.'
				),
				array(
					'enable_autoupdate_themes',
					'',
					'N',
					'checkbox',
					'Themes',
					'Automatically Update Themes',
					'Note: Automatic updates for themes are disabled on WordPress by default.'
				),
				array(
					'enable_autoupdate_ignore_vcs',
					'',
					'N',
					'checkbox',
					'Ignore Version Control',
					'Ignore Version Control Systems Such As GIT and SVN',
					'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates'
				)
			)
		);
		$aAutoUpdateAll = array(
			'section_title' => 'Disable ALL Automatic Updates',
			'section_options' => array(
				array(
					'enable_autoupdate_disable_all',
					'',
					'N',
					'checkbox',
					'Disable All',
					'Completely Disable Automatic Updates',
					'When selected, regardless of any setting above, all automatic updates on this site will be completely disabled'
				)
			)
		);

		$this->m_aOptions = array(
			$aAutoUpdatesBase,
			$aAutoUpdateCore,
			$aAutoUpdateComponents,
			$aAutoUpdateAll
		);
	}

	public function updateHandler() {

		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
		if ( version_compare( $sCurrentVersion, '1.9.0', '<' ) ) {
		}//v1.9.0
	}
}

endif;