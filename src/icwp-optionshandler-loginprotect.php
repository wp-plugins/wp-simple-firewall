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

if ( !class_exists('ICWP_OptionsHandler_LoginProtect') ):

class ICWP_OptionsHandler_LoginProtect extends ICWP_OptionsHandler_Base {
	
	public function definePluginOptions() {

		$this->m_aDirectSaveOptions = array( 'enable_login_protect' );
		
		$this->m_aOptionsBase = array(
			'section_title' => 'Enable Login Protection',
			'section_options' => array(
				array(
					'enable_login_protect',
					'',
					'N',
					'checkbox',
					'Enable Login Protect',
					'Enable (or Disable) The Login Protection Feature',
					'Regardless of any other settings, this option will turn Off the Login Protect feature, or enable your selected Login Protect options.'
				)
			),
		);
		$this->m_aTwoFactorAuth = array(
			'section_title' => 'Two-Factor Authentication Protection Options',
			'section_options' => array(
				array(
					'enable_two_factor_auth_by_ip',
					'',
					'N',
					'checkbox',
					'Two-Factor Authentication',
					'Two-Factor Login Authentication By IP Address',
					'All users will be required to authenticate their logins by email-based two-factor authentication when logging in from a new IP address.'
				),
				array(
					'enable_two_factor_bypass_on_email_fail',
					'',
					'N',
					'checkbox',
					'By-Pass On Failure',
					'If Sending Verification Email Sending Fails, Two-Factor Login Authentication Is Ignored',
					'If you enable two-factor authentication and sending the email with the verification link fails, turning this setting on will by-pass the verification step. Use with caution.'
				)
			)
		);
		$this->m_aLoginProtect = array(
			'section_title' => 'Login Protection Options',
			'section_options' => array(
				array(
					'login_limit_interval',
					'',
					'5',
					'integer',
					'Login Cooldown Interval',
					'Limit login attempts to every X seconds',
					'WordPress will process only ONE login attempt for every number of seconds specified. Zero (0) turns this off. Suggested: 5'
				),
				array(
					'enable_login_gasp_check',
					'',
					'Y',
					'checkbox',
					'G.A.S.P Protection',
					'Prevent Login By Bots using G.A.S.P. Protection',
					'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON'
				)
			)
		);
		
		$this->m_aLoggingSection = array(
			'section_title' => 'Logging Options',
			'section_options' => array(
				array(
					'enable_login_protect_log',
					'',	'N',
					'checkbox',
					'Login Protect Logging',
					'Turn on a detailed Login Protect Log',
					'Will log every event related to login protection and how it is processed. Not recommended to leave on unless you want to debug something and check the login protection is working as you expect.'
				)
			)
		);

		$this->m_aOptions = array(
			$this->m_aOptionsBase,
			$this->m_aTwoFactorAuth,
			$this->m_aLoginProtect,
			$this->m_aLoggingSection
		);
	}

	public function updateHandler() {

		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
		if ( version_compare( $sCurrentVersion, '1.4.0', '<' ) ) {
			$aSettingsKey = array(
				'current_plugin_version',
				'enable_login_protect',
				'enable_two_factor_auth_by_ip',
				'enable_two_factor_bypass_on_email_fail',
				'login_limit_interval',
				'enable_login_gasp_check',
				'enable_login_protect_log',
			);
			$this->migrateOptions( $aSettingsKey );
		}//'1.4.0', '<'
	}
}

endif;