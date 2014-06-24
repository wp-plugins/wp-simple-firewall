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

if ( !class_exists('ICWP_WPSF_OptionsHandler_UserSessions') ):

class ICWP_WPSF_OptionsHandler_UserSessions extends ICWP_OptionsHandler_Base_Wpsf {
	
	/**
	 * @var ICWP_WPSF_Processor_UserSessions
	 */
	protected $oFeatureProcessor;

	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('User Sessions');
		$this->sFeatureSlug = 'user_sessions';
		parent::__construct( $oPluginVo );
	}

	/**
	 * @return ICWP_WPSF_LoginProtectProcessor|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( dirname(__FILE__).'/icwp-processor-usersessions.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_UserSessions( $this );
		}
		return $this->oFeatureProcessor;
	}
	
	public function doPrePluginOptionsSave() { }

	/**
	 * @return array
	 */
	protected function getOptionsDefinitions() {
		$aOptionsBase = array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('User Accounts Management') ),
			'section_options' => array(
				array(
					'enable_user_accounts_management',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable User Accounts Management' ),
					_wpsf__( 'Enable (or Disable) The User Accounts Management Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('User Accounts Management') ),
					'<a href="http://icwp.io/51" target="_blank">'._wpsf__( 'more info' ).'</a>'
					.' | <a href="http://icwp.io/wpsf03" target="_blank">'._wpsf__( 'blog' ).'</a>'
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
					sprintf( _wpsf__( 'Take a new line per address. Your IP address is: %s' ), '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' ),
					'<a href="http://icwp.io/52" target="_blank">'._wpsf__( 'more info' ).'</a>'
				)
			)
		);

		$aTwoFactorAuth = array(
			'section_title' => _wpsf__( 'Two-Factor Authentication Protection Options' ),
			'section_options' => array(
				array(
					'two_factor_auth_user_roles',
					'',
					$this->getTwoFactorUserAuthRoles( true ), // default is Contributors, Authors, Editors and Administrators
					$this->getTwoFactorUserAuthRoles(),
					_wpsf__( 'Two-Factor Auth User Roles' ),
					_wpsf__( 'All User Roles Subject To Two-Factor Authentication' ),
					_wpsf__( 'Select which types of users/roles will be subject to two-factor login authentication.' ),
					'<a href="http://icwp.io/4v" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_two_factor_auth_by_ip',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('IP') ),
					sprintf( _wpsf__( 'Two-Factor Login Authentication By %s' ), _wpsf__('IP Address') ),
					_wpsf__( 'All users will be required to authenticate their logins by email-based two-factor authentication when logging in from a new IP address' ),
					'<a href="http://icwp.io/3s" target="_blank">'._wpsf__( 'more info' ).'</a>'
				),
				array(
					'enable_two_factor_auth_by_cookie',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('Cookie') ),
					sprintf( _wpsf__( 'Two-Factor Login Authentication By %s' ), _wpsf__('Cookie') ),
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

		$aOptionsDefinitions = array(
			$aOptionsBase,
			$aWhitelist,
			$aLoginProtect,
			$aTwoFactorAuth
		);
		return $aOptionsDefinitions;
	}

	/**
	 * @return array
	 */
	protected function getNonUiOptions() {
		$aNonUiOptions = array();
		return $aNonUiOptions;
	}
}

endif;