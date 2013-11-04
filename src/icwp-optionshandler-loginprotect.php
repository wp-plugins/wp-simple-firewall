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

class ICWP_OptionsHandler_LoginProtect extends ICWP_OptionsHandler_Base_WPSF {
	
	const StoreName = 'loginprotect_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function defineOptions() {

		$this->m_aDirectSaveOptions = array();
		
		$this->m_aOptionsBase = array(
			'section_title' => __( 'Enable Login Protection', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'enable_login_protect',
					'',
					'Y',
					'checkbox',
					__( 'Enable Login Protect', 'wp-simple-firewall' ),
					__( 'Enable (or Disable) The Login Protection Feature', 'wp-simple-firewall' ),
					__( 'Regardless of any other settings, this option will turn Off the Login Protect feature, or enable your selected Login Protect options', 'wp-simple-firewall' )
				)
			),
		);
		$this->m_aWhitelist = array(
			'section_title' => __( 'Whitelist IPs that by-pass Login Protect', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'',
					'ip_addresses',
					__( 'Whitelist IP Addresses', 'wp-simple-firewall' ),
					__( 'Specify IP Addresses that by-pass all Login Protect rules', 'wp-simple-firewall' ),
					sprintf( __( 'Take a new line per address. Your IP address is: %s', 'wp-simple-firewall' ), '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' )
				)
			)
		);
		$this->m_aTwoFactorAuth = array(
			'section_title' => __( 'Two-Factor Authentication Protection Options', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'enable_two_factor_auth_by_ip',
					'',
					'N',
					'checkbox',
					__( 'Two-Factor Authentication', 'wp-simple-firewall' ),
					__( 'Two-Factor Login Authentication By IP Address', 'wp-simple-firewall' ),
					__( 'All users will be required to authenticate their logins by email-based two-factor authentication when logging in from a new IP address', 'wp-simple-firewall' )
				),
				array(
					'enable_two_factor_bypass_on_email_fail',
					'',
					'N',
					'checkbox',
					__( 'By-Pass On Failure', 'wp-simple-firewall' ),
					__( 'If Sending Verification Email Sending Fails, Two-Factor Login Authentication Is Ignored', 'wp-simple-firewall' ),
					__( 'If you enable two-factor authentication and sending the email with the verification link fails, turning this setting on will by-pass the verification step. Use with caution', 'wp-simple-firewall' )
				)
			)
		);
		$this->m_aLoginProtect = array(
			'section_title' => __( 'Login Protection Options', 'wp-simple-firewall' ),
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
					__( 'G.A.S.P Protection', 'wp-simple-firewall' ),
					__( 'Use G.A.S.P. Protection To Prevent Login Attempts By Bots', 'wp-simple-firewall' ),
					__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON', 'wp-simple-firewall' )
				)
			)
		);
		
		$this->m_aLoggingSection = array(
			'section_title' => __( 'Logging Options', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'enable_login_protect_log',
					'',
					'N',
					'checkbox',
					__( 'Login Protect Logging', 'wp-simple-firewall' ),
					__( 'Turn on a detailed Login Protect Log', 'wp-simple-firewall' ),
					__( 'Will log every event related to login protection and how it is processed. Not recommended to leave on unless you want to debug something and check the login protection is working as you expect.', 'wp-simple-firewall' )
				)
			)
		);

		$this->m_aOptions = array(
			$this->m_aOptionsBase,
			$this->m_aWhitelist,
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