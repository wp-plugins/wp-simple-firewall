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
require_once( dirname(__FILE__).'/icwp-optionshandler-lockdown.php' );

if ( !class_exists('ICWP_OptionsHandler_Lockdown') ):

class ICWP_OptionsHandler_Lockdown extends ICWP_OptionsHandler_Base_Wpsf {
	
	const StoreName = 'lockdown_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !isset( $this->m_aOptionsKeys ) ) {
			$this->m_aOptionsKeys = array(
				'enable_lockdown',
				'disable_file_editing',
				'mask_wordpress_version',
				'action_reset_auth_salts'
			);
		}
	}
	
	public function doPrePluginOptionsSave() {
		
		if ( $this->getOpt( 'action_reset_auth_salts' ) == 'Y' ) {
			$this->setOpt( 'action_reset_auth_salts', 'P' );
		}
		else if ( $this->getOpt( 'action_reset_auth_salts' ) == 'P' ) {
			$this->setOpt( 'action_reset_auth_salts', 'N' );
		}

		$sCurrent = $this->getOpt( 'mask_wordpress_version' );
		if ( !empty( $sCurrent ) ) {
			$this->setOpt( 'mask_wordpress_version', preg_replace( '/[^a-z0-9_.-]/i', '', $sCurrent ) );
		}
	}
	
	public function defineOptions() {

		$aBase = array(
			'section_title' => _wpsf__( 'Enable Lockdown Feature' ),
			'section_options' => array(
				array(
					'enable_lockdown',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Lockdown' ),
					_wpsf__( 'Enable (or Disable) The Lockdown Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Lockdown feature, or enable your selected Lockdown options' )
				)
			)
		);
		$aAccess = array(
			'section_title' => _wpsf__( 'Access Options' ),
			'section_options' => array(
				array(
					'disable_file_editing',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Disable File Editing' ),
					_wpsf__( 'Disable Ability To Edit Files' ),
					_wpsf__( 'Removes the option to directly edit any files from within the WordPress admin area.' )
					.'<br />'._wpsf__( 'Equivalent to setting DISALLOW_FILE_EDIT to TRUE.' )
				)
			)
		);
		$aObscurity = array(
			'section_title' => _wpsf__( 'WordPress Obscurity Options' ),
			'section_options' => array(
				array(
					'mask_wordpress_version',
					'',
					'',
					'text',
					_wpsf__( 'Mask WordPress Version' ),
					_wpsf__( 'Prevents Public Display Of Your WordPress Version' ),
					_wpsf__( 'Enter how you would like your WordPress version displayed publicly. Leave blank to disable this feature.' )
						.'<br />'._wpsf__( 'Warning: This may interfere with WordPress plugins that rely on the $wp_version variable.' )
				)
			)
		);

		$this->m_aOptions = array(
			$aBase,
			$aAccess,
			$aObscurity
		);
		
		if ( false && $this->getCanDoAuthSalts() ) {
			$this->m_aOptions[] = array(
				'section_title' => _wpsf__( 'Security Actions' ),
				'section_options' => array(
					array(
						'action_reset_auth_salts',
						'',
						'N',
						'checkbox',
						_wpsf__( 'Reset Auth Keys/Salts' ),
						_wpsf__( 'Reset WordPress Authentication Keys and Salts' ),
						_wpsf__( 'Selecting this will reset the WordPress Authentication Keys and Salts in your wp-config.php file.' )
						.'<br /><strong>'._wpsf__( 'Note: This will log you and all other users out of their current session.' ).'</strong>'
					)
				)
			);
		}
	}
	
	protected function getCanDoAuthSalts() {
		require_once( dirname(__FILE__).'/icwp-wpfilesystem.php' );
		$oWpFs = ICWP_WpFilesystem_V1::GetInstance();
		
		if ( !$oWpFs->getCanWpRemoteGet() ) {
			return false;
		}
		
		if ( !$oWpFs->getCanDiskWrite() ) {
			return false;
		}
		
 		$sWpConfigPath = $oWpFs->exists( ABSPATH.'wp-config.php' )? ABSPATH.'wp-config.php' : ABSPATH.'..'.ICWP_DS.'wp-config.php';
 		
 		if ( !$oWpFs->exists( $sWpConfigPath ) ) {
 			return false;
 		}
 		$mResult = $oWpFs->getCanReadWriteFile( $sWpConfigPath );
 		return !empty( $mResult );
	}

}

endif;