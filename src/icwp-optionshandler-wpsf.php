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

class ICWP_OptionsHandler_Wpsf extends ICWP_OptionsHandler_Base {
	
	public function definePluginOptions() {

		$this->m_aIndependentOptions = array(
			'firewall_processor',
			'login_processor',
			'logging_processor',
			'email_processor'
		);
		
		$this->m_aDirectSaveOptions = array(
			'enable_firewall',
			'enable_login_protect'
		);
		
		$aNonUiOptions = array(
			'secret_key',
			'feedback_admin_notice'
		);
		
		if ( !empty( $this->m_aNonUiOptions ) ) {
			$this->m_aNonUiOptions = array_merge( $this->m_aNonUiOptions, $aNonUiOptions );
		}
		else {
			$this->m_aNonUiOptions = $aNonUiOptions;
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
					'enable_auto_plugin_upgrade',
					'',
					'Y',
					'checkbox',
					'Auto-Upgrade',
					'When an upgrade is detected, the plugin will automatically initiate the upgrade.',
					'If you prefer to manage plugin upgrades, deselect this option. Otherwise, this plugin will auto-upgrade once any available update is detected.'
				),
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