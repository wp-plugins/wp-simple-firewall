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

class ICWP_OptionsHandler_Lockdown extends ICWP_OptionsHandler_Base_WPSF {
	
	const StoreName = 'lockdown_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function doPrePluginOptionsSave() {
		
		if ( $this->getOpt( 'action_reset_auth_salts' ) == 'Y' ) {
			$this->setOpt( 'action_reset_auth_salts', 'P' );
		}
		else if ( $this->getOpt( 'action_reset_auth_salts' ) == 'P' ) {
			$this->setOpt( 'action_reset_auth_salts', 'N' );
		}
	}
	
	public function defineOptions() {

		$aBase = array(
			'section_title' => 'Enable Lockdown Feature',
			'section_options' => array(
				array(
					'enable_lockdown',
					'',
					'N',
					'checkbox',
					'Enable Lockdown',
					'Enable (or Disable) The Lockdown Feature',
					'Regardless of any other settings, this option will turn Off the Lockdown feature, or enable your selected Lockdown options.'
				)
			)
		);
		$aAccess = array(
			'section_title' => 'Access Options',
			'section_options' => array(
				array(
					'disable_file_editing',
					'',
					'N',
					'checkbox',
					'Disable File Editing',
					'Disable Ability To Edit Files',
					'Removes the option to directly edit any files from within the WordPress admin area.
					<br />Equivalent to setting DISALLOW_FILE_EDIT to TRUE.'
				)
			)
		);

		$this->m_aOptions = array(
			$aBase,
			$aAccess
		);
		
		if ( false && $this->getCanDoAuthSalts() ) {
			$this->m_aOptions[] = array(
				'section_title' => 'Security Actions',
				'section_options' => array(
					array(
						'action_reset_auth_salts',
						'',
						'N',
						'checkbox',
						'Reset Auth Keys/Salts',
						'Reset WordPress Authentication Keys and Salts',
						'Selecting this and saving will reset the WordPress Authentication Keys and Salts in your wp-config.php file.
						<br /><strong>Note: This will log you and all other users out of their current session.</strong>'
					)
				)
			);
		}
	}
	
	protected function getCanDoAuthSalts() {
		require_once( dirname(__FILE__).'/icwp-wpfilesystem.php' );
		$oWpFilesystem = new ICWP_WpFilesystem_WPSF();
		
		if ( !$oWpFilesystem->getCanWpRemoteGet() ) {
			return false;
		}
		
		if ( !$oWpFilesystem->getCanDiskWrite() ) {
			return false;
		}
		
 		$sWpConfigPath = is_file( ABSPATH.'wp-config.php' )? ABSPATH.'wp-config.php' : ABSPATH.'..'.ICWP_DS.'wp-config.php';
 		
 		if ( !is_file( $sWpConfigPath ) ) {
 			var_dump('no wpconfig');
 			return false;
 		}
 		$mResult = $oWpFilesystem->getCanReadWriteFile( $sWpConfigPath );
 		return !empty( $mResult );
	}

	public function updateHandler() {
	}
}

endif;