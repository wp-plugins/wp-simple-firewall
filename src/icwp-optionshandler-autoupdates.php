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
					__( 'Enable Auto Updates', 'wp-simple-firewall' ),
					__( 'Enable (or Disable) The Simple Firewall Automatic Updates Feature', 'wp-simple-firewall' ),
					__( 'Regardless of any other settings, this option will turn off the Auto Updates feature, or enable your selected Auto Updates options.', 'wp-simple-firewall' )
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
					__( 'Auto Update Plugin', 'wp-simple-firewall' ),
					__( 'Always Automatically Update This Plugin', 'wp-simple-firewall' ),
					__( 'Regardless of any component settings below, automatically update the WordPress Simple Firewall plugin.', 'wp-simple-firewall' )
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
					__( 'WordPress Core Updates', 'wp-simple-firewall' ),
					__( 'Decide how the WordPress Core will automatically update, if at all', 'wp-simple-firewall' ),
					__( 'At least automatically upgrading minor versions is recommended (and is the WordPress default).', 'wp-simple-firewall' )
				),
				array(
					'enable_autoupdate_translations',
					'',
					'Y',
					'checkbox',
					__( 'Translations', 'wp-simple-firewall' ),
					__( 'Automatically Update Translations', 'wp-simple-firewall' ),
					__( 'Note: Automatic updates for translations are enabled on WordPress by default.', 'wp-simple-firewall' )
				),
				array(
					'enable_autoupdate_plugins',
					'',
					'N',
					'checkbox',
					__( 'Plugins', 'wp-simple-firewall' ),
					__( 'Automatically Update Plugins', 'wp-simple-firewall' ),
					__( 'Note: Automatic updates for plugins are disabled on WordPress by default.', 'wp-simple-firewall' )
				),
				array(
					'enable_autoupdate_themes',
					'',
					'N',
					'checkbox',
					__( 'Themes', 'wp-simple-firewall' ),
					__( 'Automatically Update Themes', 'wp-simple-firewall' ),
					__( 'Note: Automatic updates for themes are disabled on WordPress by default.', 'wp-simple-firewall' )
				),
				array(
					'enable_autoupdate_ignore_vcs',
					'',
					'N',
					'checkbox',
					__( 'Ignore Version Control', 'wp-simple-firewall' ),
					__( 'Ignore Version Control Systems Such As GIT and SVN', 'wp-simple-firewall' ),
					__( 'If you use SVN or GIT and WordPress detects it, automatic updates are disabled by default. Check this box to ignore version control systems and allow automatic updates.', 'wp-simple-firewall' )
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
					__( 'Disable All', 'wp-simple-firewall' ),
					__( 'Completely Disable WordPress Automatic Updates', 'wp-simple-firewall' ),
					__( 'When selected, regardless of any setting above, all automatic updates on this site will be completely disabled!', 'wp-simple-firewall' )
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