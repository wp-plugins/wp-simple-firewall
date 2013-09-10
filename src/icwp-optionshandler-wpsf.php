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

class ICWP_OptionsHandler_Wpsf extends ICWP_OptionsHandler_Base_WPSF {

	const StoreName = 'plugin_options';
	const Default_AccessKeyTimeout = 30;
	
	public function __construct( $insPrefix, $insVersion, $infInit = false ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion, $infInit );
	}
	
	public function definePluginOptions() {

		$this->m_aIndependentOptions = array(
			'firewall_processor',
			'login_processor',
			'comments_processor',
			'lockdown_processor',
			'logging_processor',
			'email_processor'
		);
		
		$aNonUiOptions = array(
			'secret_key',
			'feedback_admin_notice',
			'update_success_tracker',
		);
		$this->mergeNonUiOptions( $aNonUiOptions );
		
		if ( $this->hasEncryptOption() ) {
			
			$aAccessKey = array(
				'section_title' => 'Admin Access Restriction',
				'section_options' => array(
					array(
						'enable_admin_access_restriction',
						'',
						'N',
						'checkbox',
						'Enable Access Key',
						'Enforce Admin Access Restriction',
						sprintf( 
							'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin. [%smore info%s]',
							'<a href="http://icwp.io/2r" target="_blank">',
							'</a>'
						)
					),
					array(
						'admin_access_timeout',
						'',
						self::Default_AccessKeyTimeout,
						'integer',
						'Access Key Timeout',
						'Specify A Timeout For Plugin Admin Access',
						'This will automatically expire your WordPress Simple Firewall session. Does not apply until you enter the access key again. Default: 30 minutes.'
					),
					array(
						'admin_access_key',
						'',
						'',
						'password',
						'Admin Access Key',
						'Specify Your Plugin Access Key',
						'If you forget this, you could potentially lock yourself out from using this plugin. <strong>Leave it blank to <u>not</u> update it</strong>.'
					)
				)
			);
		}
		
		$aGeneral = array(
			'section_title' => 'General Plugin Options',
			'section_options' => array(
				array(
					'enable_firewall',
					'',	'N',
					'checkbox',
					'Enable Firewall',
					'Enable (or Disable) The WordPress Firewall Feature',
					'Regardless of any other settings, this option will turn Off the Firewall feature, or enable your selected Firewall options.'
				),
				array(
					'enable_login_protect',
					'',
					'N',
					'checkbox',
					'Enable Login Protect',
					'Enable (or Disable) The Login Protection Feature',
					'Regardless of any other settings, this option will turn Off the Login Protect feature, or enable your selected Login Protect options.'
				),
				array(
					'enable_comments_filter',
					'',
					'N',
					'checkbox',
					'Enable Comments Filter',
					'Enable (or Disable) The Comments Filter Feature',
					'Regardless of any other settings, this option will turn Off the Comments Filter feature, or enable your selected Comments Filter options.'
				),
				array(
					'enable_lockdown',
					'',
					'N',
					'checkbox',
					'Enable Lockdown',
					'Enable (or Disable) The Lockdown Feature',
					'Regardless of any other settings, this option will turn Off the Lockdown feature, or enable your selected Lockdown options.'
				),
				/*
				array(
					'enable_auto_plugin_upgrade',
					'',
					'N',
					'checkbox',
					'Auto-Upgrade',
					'When an upgrade is detected, the plugin will automatically initiate the upgrade.',
					'If you prefer to manage plugin upgrades, deselect this option. Otherwise, this plugin will auto-upgrade once any available update is detected.'
				),
				*/
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					'Delete Plugin Settings',
					'Delete All Plugin Settings Upon Plugin Deactivation',
					'Careful: Removes all plugin options when you deactivite the plugin.'
				)
			)
		);
		
		$aEmail = array(
			'section_title' => 'Email Options',
			'section_options' => array(
				array(
					'block_send_email_address',
					'',
					'',
					'email',
					'Report Email',
					'Where to send email reports',
					'If this is empty, it will default to the blog admin email address.'
				),
				array(
					'send_email_throttle_limit',
					'',
					'10',
					'integer',
					'Email Throttle Limit',
					'Limit Emails Per Second',
					'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10'
				)
			)
		);

		$this->m_aOptions = array(
			$aGeneral,
			$aEmail
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
		
		$sEmail = $this->getOpt( 'block_send_email_address');
		if ( empty( $sEmail ) || !is_email( $sEmail ) ) {
			$sEmail = get_option('admin_email');
		}
		if ( is_email( $sEmail ) ) {
			$this->setOpt( 'block_send_email_address', $sEmail );
		}

		$sLimit = $this->getOpt( 'send_email_throttle_limit' );
		if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
			$sLimit = 0;
		}
		$this->setOpt( 'send_email_throttle_limit', $sLimit );
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
	}

}

endif;