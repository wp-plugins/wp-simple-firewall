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

if ( !class_exists('ICWP_OptionsHandler_Lockdown') ):

class ICWP_OptionsHandler_Lockdown extends ICWP_OptionsHandler_Base_Wpsf {

	const StoreName = 'lockdown_options';
	
	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::StoreName );

		$this->sFeatureName = _wpsf__('Lockdown');
		$this->sFeatureSlug = 'lockdown';
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

	/**
	 * @return bool|void
	 */
	public function defineOptions() {

		$aBase = array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('WordPress Lockdown') ),
			'section_options' => array(
				array(
					'enable_lockdown',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Lockdown' ),
					_wpsf__( 'Enable (or Disable) The Lockdown Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('WordPress Lockdown') ),
					'<a href="http://icwp.io/4r" target="_blank">'._wpsf__( 'more info' ).'</a>'
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
					.'<br />'._wpsf__( 'Equivalent to setting DISALLOW_FILE_EDIT to TRUE.' ),
					'<a href="http://icwp.io/4q" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'force_ssl_login',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Force SSL Login' ),
					_wpsf__( 'Forces Login Form To Be Submitted Over SSL' ),
					_wpsf__( 'Please only enable this option if you have a valid SSL certificate installed.' )
					.'<br />'._wpsf__( 'Equivalent to setting FORCE_SSL_LOGIN to TRUE.' ),
					'<a href="http://icwp.io/4s" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'force_ssl_admin',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Force SSL Admin' ),
					_wpsf__( 'Forces WordPress Admin Dashboard To Be Delivered Over SSL' ),
					_wpsf__( 'Please only enable this option if you have a valid SSL certificate installed.' )
					.'<br />'._wpsf__( 'Equivalent to setting FORCE_SSL_ADMIN to TRUE.' ),
					'<a href="http://icwp.io/4t" target="_blank">'._wpsf__( 'more info' ).'</a>'
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
						.'<br />'._wpsf__( 'Warning: This may interfere with WordPress plugins that rely on the $wp_version variable.' ),
					'<a href="http://icwp.io/43" target="_blank">'._wpsf__( 'more info' ).'</a>'
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
		$oWpFs = $this->loadFileSystemProcessor();
		
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