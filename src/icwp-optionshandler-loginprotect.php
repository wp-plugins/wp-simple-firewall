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
				'enable_prevent_remote_post',
				'enable_login_protect_log',
				'enable_yubikey',
				'yubikey_app_id',
				'yubikey_api_key',
				'yubikey_unique_keys'
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
					'<a href="http://icwp.io/3y" target="_blank">'._wpsf__( 'more info' ).'</a>'
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
					'<a href="http://icwp.io/3s" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_two_factor_auth_by_cookie',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('Cookie') ),
					_wpsf__( 'Two-Factor Login Authentication By Cookie' ),
					_wpsf__( 'This will restrict all user login sessions to a single browser. Use this if your users have dynamic IP addresses.' ),
					'<a href="http://icwp.io/3t" target="_blank">'._wpsf__( 'more info' ).'</a>'
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
					'<a href="http://icwp.io/3q" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_login_gasp_check',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'G.A.S.P Protection' ),
					_wpsf__( 'Use G.A.S.P. Protection To Prevent Login Attempts By Bots' ),
					_wpsf__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON' ),
					'<a href="http://icwp.io/3r" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_prevent_remote_post',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Prevent Remote Login' ),
					_wpsf__( 'Prevents Remote Login Attempts From Other Locations' ),
					_wpsf__( 'Prevents any login attempts that do not originate from your website. This prevent bots from attempting to login remotely. Recommended: ON' ),
					'<a href="http://icwp.io/4n" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);

		$aYubikeyProtect = array(
			'section_title' => _wpsf__( 'Yubikey Authentication' ),
			'section_options' => array(
				array(
					'enable_yubikey',
					'',
					'N',
					'checkbox',
					_wpsf__('Enable Yubikey Authentication'),
					_wpsf__('Turn On / Off Yubikey Authentication On This Site'),
					_wpsf__('Combined with your Yubikey API Key (below) this will form the basis of your Yubikey Authentication'),
					'<a href="http://icwp.io/4f" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'yubikey_app_id',
					'',
					'',
					'text',
					_wpsf__('Yubikey App ID'),
					_wpsf__('Your Unique Yubikey App ID'),
					_wpsf__('Combined with your Yubikey API Key (below) this will form the basis of your Yubikey Authentication')
					. _wpsf__( 'Please review the [more info] link on how to get your own Yubikey App ID and API Key.' ),
					'<a href="http://icwp.io/4g" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'yubikey_api_key',
					'',
					'',
					'text',
					_wpsf__( 'Yubikey API Key' ),
					_wpsf__( 'Your Unique Yubikey App API Key' ),
					_wpsf__( 'Combined with your Yubikey App ID (above) this will form the basis of your Yubikey Authentication.' )
					. _wpsf__( 'Please review the [more info] link on how to get your own Yubikey App ID and API Key.' ),
					'<a href="http://icwp.io/4g" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'yubikey_unique_keys',
					'',
					'',
					'yubikey_unique_keys',
					_wpsf__( 'Yubikey Unique Keys' ),
					_wpsf__( 'Permitted Username - Yubikey Pairs For This Site' ),
					'<strong>'. sprintf( _wpsf__( 'Format: %s' ), 'Username,Yubikey').'</strong>'
					.'<br />- '. _wpsf__( 'Provide Username<->Yubikey Pairs that are usable on this site.')
					.'<br />- '. _wpsf__( 'If a Username if not assigned a Yubikey, Yubikey Authentication is OFF for that user.')
					.'<br />- '. _wpsf__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.' ),
					'<a href="http://icwp.io/4h" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				/*
				array(
					'enable_yubikey_only',
					'',
					'N',
					'checkbox',
					_wpsf__('Enable Yubikey Only'),
					_wpsf__('Turn On / Off Yubikey Only Authentication'),
					_wpsf__('Yubikey Only Authentication is where you can login into your WordPress site with just a Yubikey OTP.')
					.'<br />- '. _wpsf__("You don't need to enter a username or a password, just a valid Yubikey OTP.")
					.'<br />- '. _wpsf__("Check your list of Yubikeys as only 1 WordPress username may be assigned to a given Yubikey ID (but you may have multiple Yubikeys for a given username)."),
					sprintf( _wpsf__( '%smore info%s' ), '<a href="http://icwp.io/4f" target="_blank">', '</a>' )
				),*/
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
			$aYubikeyProtect,
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