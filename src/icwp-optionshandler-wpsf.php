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

if ( !class_exists('ICWP_OptionsHandler_Wpsf') ):

class ICWP_OptionsHandler_Wpsf extends ICWP_OptionsHandler_Base_Wpsf {

	const StoreName = 'plugin_options';
	const Default_AccessKeyTimeout = 30;
	
	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::StoreName );

		$this->sFeatureName = _wpsf__('Dashboard');
		$this->sFeatureSlug = 'dashboard';
		$this->fShowFeatureMenuItem = false;
	}

	/**
	 * @return bool|void
	 */
	public function defineOptions() {
		
		$aNonUiOptions = array(
			'installation_time',
			'secret_key',
			'feedback_admin_notice',
			'update_success_tracker',
			'capability_can_disk_write',
			'capability_can_remote_get',
			'enable_firewall',
			'enable_login_protect',
			'enable_comments_filter',
			'enable_lockdown',
			'enable_autoupdates'
		);
		$this->mergeNonUiOptions( $aNonUiOptions );
		
		if ( $this->hasEncryptOption() ) {
			
			$aAccessKey = array(
				'section_title' => _wpsf__( 'Admin Access Restriction' ),
				'section_options' => array(
					array(
						'enable_admin_access_restriction',
						'',
						'N',
						'checkbox',
						_wpsf__( 'Enable Access Key' ),
						_wpsf__( 'Enforce Admin Access Restriction' ),
						_wpsf__( 'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin.' ),
						'<a href="http://icwp.io/40" target="_blank">'._wpsf__( 'more info' ).'</a>'
						.' | <a href="http://icwp.io/wpsf02" target="_blank">'._wpsf__( 'blog' ).'</a>'
					),
					array(
						'admin_access_timeout',
						'',
						self::Default_AccessKeyTimeout,
						'integer',
						_wpsf__( 'Access Key Timeout' ),
						_wpsf__( 'Specify A Timeout For Plugin Admin Access' ),
						_wpsf__( 'This will automatically expire your WordPress Simple Firewall session. Does not apply until you enter the access key again. Default: 30 minutes.' ),
						'<a href="http://icwp.io/41" target="_blank">'._wpsf__( 'more info' ).'</a>'
					),
					array(
						'admin_access_key',
						'',
						'',
						'password',
						_wpsf__( 'Admin Access Key' ),
						_wpsf__( 'Specify Your Plugin Access Key' ),
						_wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' )
							.' <strong>'._wpsf__( 'Leave it blank to not update it' ).'</strong>',
						'<a href="http://icwp.io/42" target="_blank">'._wpsf__( 'more info' ).'</a>'
					)
				)
			);
		}
		
		$aGeneral = array(
			'section_title' => _wpsf__( 'General Plugin Options' ),
			'section_options' => array(
				array(
					'enable_upgrade_admin_notice',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Plugin Notices' ),
					_wpsf__( 'Display Notices For Updates' ),
					_wpsf__( 'Disable this option to hide certain plugin admin notices about available updates and post-update notices' )
				),
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Delete Plugin Settings' ),
					_wpsf__( 'Delete All Plugin Settings Upon Plugin Deactivation' ),
					_wpsf__( 'Careful: Removes all plugin options when you deactivate the plugin' )
				)
			)
		);

		$aGlobal = array(
			'section_title' => _wpsf__( 'Global Plugin Features' ),
			'section_options' => array(
				array(
					'enable_firewall',
					'',	'N',
					'checkbox',
					_wpsf__( 'Enable Firewall' ),
					_wpsf__( 'Enable (or Disable) The WordPress Firewall Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Firewall feature, or enable your selected Firewall options' )
				),
				array(
					'enable_login_protect',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Login Protect' ),
					_wpsf__( 'Enable (or Disable) The Login Protection Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Login Protect feature, or enable your selected Login Protect options' )
				),
				array(
					'enable_comments_filter',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Comments Filter' ),
					_wpsf__( 'Enable (or Disable) The Comments Filter Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Comments Filter feature, or enable your selected Comments Filter options' )
				),
//				array(
//					'enable_privacy_protect',
//					'',
//					'N',
//					'checkbox',
//					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Privacy Protection') ),
//					sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), _wpsf__('Privacy Protection') ),
//					_wpsf__( 'Regardless of any other settings, this option will turn off the Privacy Protection feature, or enable your selected Privacy Protection options' ),
//					'<a href="http://icwp.io/3y" target="_blank">'._wpsf__( 'more info' ).'</a>'
//				),
				array(
					'enable_lockdown',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Lockdown' ),
					_wpsf__( 'Enable (or Disable) The Lockdown Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Lockdown feature, or enable your selected Lockdown options' )
				),
				array(
					'enable_autoupdates',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Enable Auto Updates' ),
					_wpsf__( 'Enable (or Disable) The Auto Updates Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Auto Updates feature, or enable your selected Auto Updates options' )
				)
			)
		);

		$this->m_aOptions = array(
			$aGeneral,
//			$aGlobal
		);
		if ( isset( $aAccessKey ) ) {
			array_unshift( $this->m_aOptions, $aAccessKey );
		}
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		
		if ( $this->getOpt( 'admin_access_key_timeout' ) <= 0 ) {
			$this->setOpt( 'admin_access_key_timeout', self::Default_AccessKeyTimeout );
		}
		
		$sAccessKey = $this->getOpt( 'admin_access_key');
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}

		$this->setOpt( 'enable_logging', 'Y' );

		$nInstalledAt = $this->getOpt( 'installation_time' );
		if ( empty($nInstalledAt) || $nInstalledAt <= 0 ) {
			$this->setOpt( 'installation_time', time() );
		}
	}
}

endif;