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

class ICWP_OptionsHandler_LoginProtect extends ICWP_OptionsHandler_Base_Wpsf {
	
	const StoreName = 'loginprotect_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !isset( $this->m_aOptionsKeys ) ) {
			$this->m_aOptionsKeys = array(
				'enable_login_protect',
				'ips_whitelist',
				'enable_two_factor_auth_by_ip',
				'enable_two_factor_auth_by_cookie',
				'enable_two_factor_bypass_on_email_fail',
				'login_limit_interval',
				'enable_login_gasp_check',
				'enable_login_protect_log'
			);
		}
	}
	
	public function doPrePluginOptionsSave() {
		$aIpWhitelist = $this->getOpt( 'ips_whitelist' );
		if ( $aIpWhitelist === false ) {
			$aIpWhitelist = '';
			$this->setOpt( 'ips_whitelist', $aIpWhitelist );
		}
		$this->processIpFilter( 'ips_whitelist', 'icwp_simple_firewall_whitelist_ips' );
	}
	
	public function defineOptions() {

		$this->m_aDirectSaveOptions = array();
		
		$aOptionsBase = array(
			'section_title' => _wpsf__( 'Enable Login Protection' ),
			'section_options' => array(
				array(
					'enable_login_protect',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Login Protect' ),
					_wpsf__( 'Enable (or Disable) The Login Protection Feature' ),
					_wpsf__( 'Regardless of any other settings, this option will turn off the Login Protect feature, or enable your selected Login Protect options' ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3y" target="_blank">', '</a>' )
				)
			),
		);
		$aWhitelist = array(
			'section_title' => _wpsf__( 'Whitelist IPs that by-pass Login Protect' ),
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'',
					'ip_addresses',
					_wpsf__( 'Whitelist IP Addresses' ),
					_wpsf__( 'Specify IP Addresses that by-pass all Login Protect rules' ),
					sprintf( _wpsf__( 'Take a new line per address. Your IP address is: %s' ), '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' )
				)
			)
		);
		$aTwoFactorAuth = array(
			'section_title' => _wpsf__( 'Two-Factor Authentication Protection Options' ),
			'section_options' => array(
				array(
					'enable_two_factor_auth_by_ip',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('IP') ),
					_wpsf__( 'Two-Factor Login Authentication By IP Address' ),
					_wpsf__( 'All users will be required to authenticate their logins by email-based two-factor authentication when logging in from a new IP address' ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3s" target="_blank">', '</a>' )
				),
				array(
					'enable_two_factor_auth_by_cookie',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('Cookie') ),
					_wpsf__( 'Two-Factor Login Authentication By Cookie' ),
					_wpsf__( 'This will restrict all user login sessions to a single browser. Use this if your users have dynamic IP addresses.' ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3t" target="_blank">', '</a>' )
				),
				array(
					'enable_two_factor_bypass_on_email_fail',
					'',
					'N',
					'checkbox',
					_wpsf__( 'By-Pass On Failure' ),
					_wpsf__( 'If Sending Verification Email Sending Fails, Two-Factor Login Authentication Is Ignored' ),
					_wpsf__( 'If you enable two-factor authentication and sending the email with the verification link fails, turning this setting on will by-pass the verification step. Use with caution' )
				)
			)
		);
		$aLoginProtect = array(
			'section_title' => _wpsf__( 'Login Protection Options' ),
			'section_options' => array(
				array(
					'login_limit_interval',
					'',
					'10',
					'integer',
					_wpsf__('Login Cooldown Interval'),
					_wpsf__('Limit login attempts to every X seconds'),
					_wpsf__('WordPress will process only ONE login attempt for every number of seconds specified. Zero (0) turns this off. Suggested: 5'),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3q" target="_blank">', '</a>' )
				),
				array(
					'enable_login_gasp_check',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'G.A.S.P Protection' ),
					_wpsf__( 'Use G.A.S.P. Protection To Prevent Login Attempts By Bots' ),
					_wpsf__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON' ),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/3r" target="_blank">', '</a>' )
				)
			)
		);
		
		$aLoggingSection = array(
			'section_title' => _wpsf__( 'Logging Options' ),
			'section_options' => array(
				array(
					'enable_login_protect_log',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Login Protect Logging' ),
					_wpsf__( 'Turn on a detailed Login Protect Log' ),
					_wpsf__( 'Will log every event related to login protection and how it is processed. Not recommended to leave on unless you want to debug something and check the login protection is working as you expect.' )
				)
			)
		);

		$this->m_aOptions = array(
			$aOptionsBase,
			$aWhitelist,
			$aTwoFactorAuth,
			$aLoginProtect,
			$aLoggingSection
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