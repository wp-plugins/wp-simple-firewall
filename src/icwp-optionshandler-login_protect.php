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

if ( !class_exists('ICWP_WPSF_FeatureHandler_LoginProtect') ):

class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_Base {

	const TwoFactorAuthTableName = 'login_auth';

	/**
	 * @var ICWP_WPSF_Processor_LoginProtect
	 */
	protected $oFeatureProcessor;

	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Login Protection');
		$this->sFeatureSlug = 'login_protect';
		parent::__construct( $oPluginVo, 'loginprotect' ); //TODO: align this naming with the feature slug etc. as with the other features.
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir().'icwp-processor-loginprotect.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_LoginProtect( $this );
		}
		return $this->oFeatureProcessor;
	}
	
	public function doPrePluginOptionsSave() {
		$aIpWhitelist = $this->getOpt( 'ips_whitelist' );
		if ( $aIpWhitelist === false ) {
			$aIpWhitelist = '';
			$this->setOpt( 'ips_whitelist', $aIpWhitelist );
		}
		$this->processIpFilter( 'ips_whitelist', 'icwp_simple_firewall_whitelist_ips' );

		$aTwoFactorAuthRoles = $this->getOpt( 'two_factor_auth_user_roles' );
		if ( empty($aTwoFactorAuthRoles) || !is_array( $aTwoFactorAuthRoles ) ) {
			$this->setOpt( 'two_factor_auth_user_roles', $this->getTwoFactorUserAuthRoles( true ) );
		}

		// ensures they have values
		$this->setKeys();
		$this->getLastLoginTimeFilePath();
	}

	/**
	 * @return array
	 */
	protected function getOptionsDefinitions() {
		$aOptionsBase = array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('Login Protection') ),
			'section_options' => array(
				array(
					'enable_login_protect',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Login Protection') ),
					_wpsf__( 'Enable (or Disable) The Login Protection Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('Login Protection') ),
					'<a href="http://icwp.io/51" target="_blank">'._wpsf__( 'more info' ).'</a>'
					.' | <a href="http://icwp.io/wpsf03" target="_blank">'._wpsf__( 'blog' ).'</a>'
				)
			)
		);
		$aWhitelist = array(
			'section_title' => sprintf( _wpsf__( 'By-Pass %s' ), _wpsf__('Login Protection') ),
			'section_options' => array(
				array(
					'enable_xmlrpc_compatibility',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'XML-RPC Compatibility' ),
					_wpsf__( 'Allow Login Through XML-RPC To By-Pass Login Protection Rules' ),
					_wpsf__( 'Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App.' )
				),
				array(
					'ips_whitelist',
					'',
					'',
					'ip_addresses',
					_wpsf__( 'Whitelist IP Addresses' ),
					_wpsf__( 'Specify IP Addresses that by-pass all Login Protect rules' ),
					sprintf( _wpsf__( 'Take a new line per address. Your IP address is: %s' ), '<span class="code">'.( ICWP_WPSF_DataProcessor::GetVisitorIpAddress(false) ).'</span>' ),
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

		$aOptionsDefinitions = array(
			$aOptionsBase,
			$aWhitelist,
			$aLoginProtect,
			$aTwoFactorAuth,
			$aYubikeyProtect,
			$aLoggingSection
		);
		return $aOptionsDefinitions;
	}

	/**
	 * @return array
	 */
	protected function getNonUiOptions() {
		$aNonUiOptions = array(
			'gasp_key',
			'two_factor_secret_key',
			'last_login_time',
			'last_login_time_file_path',
			'log_category',
			'two_factor_auth_table_name',
			'two_factor_auth_table_created',
		);
		return $aNonUiOptions;
	}

	/**
	 * @return bool|void
	 */
	public function handleFormSubmit() {
		$fSuccess = parent::handleFormSubmit();
		if ( !$fSuccess ) {
			return;
		}

		// When they've clicked to terminate all logged in authenticated users.
		if ( ICWP_WPSF_DataProcessor::FetchPost( 'terminate-all-logins' ) ) {
			$oProc = $this->getProcessor();
			$oProc->doTerminateAllVerifiedLogins();
			return;
		}

	}

	/**
	 * @param boolean $fAsDefaults
	 * @return array
	 */
	protected function getTwoFactorUserAuthRoles( $fAsDefaults = false ) {
		$aTwoAuthRoles = array( 'type' => 'multiple_select',
			0	=> _wpsf__('Subscribers'),
			1	=> _wpsf__('Contributors'),
			2	=> _wpsf__('Authors'),
			3	=> _wpsf__('Editors'),
			8	=> _wpsf__('Administrators')
		);
		if ( $fAsDefaults ) {
			unset($aTwoAuthRoles['type']);
			unset($aTwoAuthRoles[0]);
			return array_keys( $aTwoAuthRoles );
		}
		return $aTwoAuthRoles;
	}

	/**
	 * @return string
	 */
	public function getLastLoginTimeFilePath() {
		$sPath = $this->getOpt( 'last_login_time_file_path' );
		if ( empty( $sPath ) ) {
			$sPath = $this->oPluginVo->getRootDir().'mode.login_throttled';
			$this->setOpt( 'last_login_time_file_path', $sPath );
		}
		return $sPath;
	}

	/**
	 * @return string
	 */
	public function setKeys() {
		$this->getTwoAuthSecretKey();
		$this->getGaspKey();
	}

	/**
	 * @return string
	 */
	public function getGaspKey() {
		$sKey = $this->getOpt( 'gasp_key' );
		if ( empty( $sKey ) ) {
			$sKey = uniqid();
			$this->setOpt( 'gasp_key', $sKey );
		}
		return $sKey;
	}

	/**
	 * @return string
	 */
	public function getTwoFactorAuthTableName() {
		$sName = $this->getOpt( 'two_factor_auth_table_name' );
		if ( empty( $sName ) ) {
			$sName = self::TwoFactorAuthTableName;
			$this->setOpt( 'two_factor_auth_table_name', $sName );
		}
		return $sName;
	}

	/**
	 * @return string
	 */
	public function getTwoAuthSecretKey() {
		$sKey = $this->getOpt( 'two_factor_secret_key' );
		if ( empty( $sKey ) ) {
			$sKey = md5( mt_rand() );
			$this->setOpt( 'two_factor_secret_key', $sKey );
		}
		return $sKey;
	}

	/**
	 * @param string $sType		can be either 'ip' or 'cookie'. If empty, both are checked looking for either.
	 * @return bool
	 */
	public function getIsTwoFactorAuthOn( $sType = '' ) {

		$fIp = $this->getOptIs( 'enable_two_factor_auth_by_ip', 'Y' );
		$fCookie = $this->getOptIs( 'enable_two_factor_auth_by_cookie', 'Y' );

		switch( $sType ) {
			case 'ip':
				return $fIp;
				break;
			case 'cookie':
				return $fCookie;
				break;
			default:
				return $fIp || $fCookie;
				break;
		}
	}
}

endif;