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

if ( !class_exists('ICWP_OptionsHandler_Wpsf') ):

class ICWP_OptionsHandler_Wpsf extends ICWP_OptionsHandler_Base_Wpsf {

	const StoreName = 'plugin_options';
	const Default_AccessKeyTimeout = 30;
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !isset( $this->m_aOptionsKeys ) ) {
			$this->m_aOptionsKeys = array(
				'enable_admin_access_restriction',
				'admin_access_timeout',
				'admin_access_key',
				'enable_firewall',
				'enable_logging',
				'enable_login_protect',
				'enable_comments_filter',
				'enable_lockdown',
				'enable_autoupdates',
				'enable_upgrade_admin_notice',
				'delete_on_deactivate'
			);
		}
	}
	
	public function defineOptions() {
		
		$aNonUiOptions = array(
			'secret_key',
			'feedback_admin_notice',
			'update_success_tracker',
			'capability_can_disk_write',
			'capability_can_remote_get'
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
						sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/40" target="_blank">', '</a>' )
						.' | '.sprintf( _wpsf__( '%sblog%s' ), '<a href="http://icwp.io/45" target="_blank">', '</a>' )
					),
					array(
						'admin_access_timeout',
						'',
						self::Default_AccessKeyTimeout,
						'integer',
						_wpsf__( 'Access Key Timeout' ),
						_wpsf__( 'Specify A Timeout For Plugin Admin Access' ),
						_wpsf__( 'This will automatically expire your WordPress Simple Firewall session. Does not apply until you enter the access key again. Default: 30 minutes.' ),
						sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/41" target="_blank">', '</a>' )
					),
					array(
						'admin_access_key',
						'',
						'',
						'password',
						_wpsf__( 'Admin Access Key' ),
						_wpsf__( 'Specify Your Plugin Access Key' ),
						_wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' )
							.'<strong>'._wpsf__( 'Leave it blank to not update it' ).'</strong>',
						sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/42" target="_blank">', '</a>' )
					)
				)
			);
		}
		
		$aGeneral = array(
			'section_title' => _wpsf__( 'General Plugin Options' ),
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
				),
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
			$aGeneral
		);
		if ( isset( $aAccessKey ) ) {
			array_unshift( $this->m_aOptions, $aAccessKey );
		}
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		
		$nTimeout = $this->getOpt( 'admin_access_key_timeout');
		if ( $nTimeout <= 0 ) {
			$nTimeout = self::Default_AccessKeyTimeout;
		}
		$this->setOpt( 'admin_access_key_timeout', $nTimeout );
		
		$sAccessKey = $this->getOpt( 'admin_access_key');
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}

		$this->setOpt( 'enable_logging', 'Y' );
	}
	
	protected function updateHandler() {

		// the 'current_plugin_version' value moved from a direct save option to be
		// included in the plugin options object, so we have to account for it being
		// empty.
		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
		if ( version_compare( $sCurrentVersion, '1.4.0', '<' ) ) {
			$aSettingsKey = array(
				'current_plugin_version',
				'enable_firewall',
				'enable_login_protect',
				'feedback_admin_notice',
				'secret_key',
				'block_send_email_address',
				'send_email_throttle_limit',
				'delete_on_deactivate'
			);
			$this->migrateOptions( $aSettingsKey );
		}// '1.4.0', '<'
		
		if ( version_compare( $sCurrentVersion, '1.8.2', '<=' ) ) {
			
			$fCanRemoteGet = $this->getOpt( 'capability_can_remote_get' );
			$fCanDiskWrite = $this->getOpt( 'capability_can_disk_write' );
			
			if ( $fCanDiskWrite === false || $fCanRemoteGet === false ) {
				require_once( dirname(__FILE__).'/icwp-wpfunctions.php' );
				$oWpFs = new ICWP_WpFilesystem_V1();
				
				$fCanRemoteGet = $oWpFs->getCanWpRemoteGet();
				$this->setOpt( 'capability_can_remote_get', $fCanRemoteGet? 'Y' : 'N' );
				
				$fCanDiskWrite = $oWpFs->getCanDiskWrite();
				$this->setOpt( 'capability_can_disk_write', $fCanDiskWrite? 'Y' : 'N' );
			}
		}// '1.8.2', '<='
	}

}

endif;