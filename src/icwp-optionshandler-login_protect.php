<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
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

require_once( 'icwp-optionshandler-base.php' );

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_LoginProtect', false ) ):

	class ICWP_WPSF_FeatureHandler_LoginProtect extends ICWP_WPSF_FeatureHandler_Base {

		/**
		 * @return string
		 */
		protected function getProcessorClassName() {
			return 'ICWP_WPSF_Processor_LoginProtect';
		}

		protected function doExecuteProcessor() {
			$sIp = $this->loadDataProcessor()->getVisitorIpAddress();
			$aIpWhitelist = apply_filters( $this->doPluginPrefix( 'ip_whitelist' ), array() );
			if ( is_array( $aIpWhitelist ) && ( in_array( $sIp, $aIpWhitelist )  ) ) {
				return;
			}
			parent::doExecuteProcessor();
		}

		public function doPrePluginOptionsSave() {

			$sCustomLoginPath = $this->getOpt( 'rename_wplogin_path', '' );
			if ( !empty( $sCustomLoginPath ) ) {
				$sCustomLoginPath = preg_replace( '#[^0-9a-zA-Z-]#', '', trim( $sCustomLoginPath, '/' ) );
				$this->setOpt( 'rename_wplogin_path', $sCustomLoginPath );
			}

			if ( $this->getOpt( 'login_limit_interval' ) < 0 ) {
				$this->getOptionsVo()->resetOptToDefault( 'login_limit_interval' );
			}

			$aTwoFactorAuthRoles = $this->getOpt( 'two_factor_auth_user_roles' );
			if ( empty($aTwoFactorAuthRoles) || !is_array( $aTwoFactorAuthRoles ) ) {
				$this->setOpt( 'two_factor_auth_user_roles', $this->getTwoFactorUserAuthRoles( true ) );
			}

			// ensures they have values
			$this->setKeys();
			$this->getLastLoginTimeFilePath();

			$this->setOpt( 'ips_whitelist', '' );
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_login_protection' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					break;

				case 'section_bypass_login_protection' :
					$sTitle = _wpsf__( 'By-Pass Login Protection' );
					break;

				case 'section_rename_wplogin' :
					$sTitle = _wpsf__( 'Rename WP Login Page' );
					break;

				case 'section_two_factor_authentication' :
					$sTitle = _wpsf__('Two-Factor Authentication');
					break;

				case 'section_brute_force_login_protection' :
					$sTitle = _wpsf__('Brute Force Login Protection');
					break;

				case 'section_yubikey_authentication' :
					$sTitle = _wpsf__('Yubikey Authentication');
					break;

				case 'section_login_logging' :
					$sTitle = _wpsf__('Logging');
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {

			$oDp = $this->loadDataProcessor();
			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'enable_login_protect' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'enable_xmlrpc_compatibility' :
					$sName = _wpsf__( 'XML-RPC Compatibility' );
					$sSummary = _wpsf__( 'Allow Login Through XML-RPC To By-Pass Login Protection Rules' );
					$sDescription = _wpsf__( 'Enable this if you need XML-RPC functionality e.g. if you use the WordPress iPhone/Android App.' );
					break;

				case 'ips_whitelist' :
					$sName = _wpsf__( 'Whitelist IP Addresses' );
					$sSummary = _wpsf__( 'Specify IP Addresses that by-pass all Login Protect rules' );
					$sDescription = sprintf(
						_wpsf__( 'Take a new line per address. Your IP address is: %s' ),
						'<span class="code">'.( $oDp->getVisitorIpAddress( true ) ).'</span>'
					);
					break;

				case 'rename_wplogin_path' :
					$sName = _wpsf__( 'Rename WP Login' );
					$sSummary = _wpsf__( 'Rename The WordPress Login Page' );
					$sDescription = _wpsf__( 'Creating a path here will disable your wp-login.php' )
					                .'<br />'
					                .sprintf( _wpsf__( 'Only letters and numbers are permitted: %s'), '<strong>abc123</strong>' )
					                .'<br />'
					                .sprintf( _wpsf__( 'Your current login URL is: %s'), '<br /><strong>&nbsp;&nbsp;'.wp_login_url().'</strong>' )
					;
					break;

				case 'two_factor_auth_user_roles' :
					$sName = _wpsf__( 'Two-Factor Auth User Roles' );
					$sSummary = _wpsf__( 'All User Roles Subject To Two-Factor Authentication' );
					$sDescription = _wpsf__( 'Select which types of users/roles will be subject to two-factor login authentication.' );
					break;

				case 'enable_two_factor_auth_by_ip' :
					$sName = sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('IP') );
					$sSummary = sprintf( _wpsf__( 'Two-Factor Login Authentication By %s' ), _wpsf__('IP Address') );
					$sDescription = _wpsf__( 'All users will be required to authenticate their login by email-based two-factor authentication, when logging in from a new IP address' );
					break;

				case 'enable_two_factor_auth_by_cookie' :
					$sName = sprintf( _wpsf__( 'Two-Factor Authentication (%s)' ), _wpsf__('Cookie') );
					$sSummary = sprintf( _wpsf__( 'Two-Factor Login Authentication By %s' ), _wpsf__('Cookie') );
					$sDescription = _wpsf__( 'This will restrict all user login sessions to a single browser. Use this if your users have dynamic IP addresses.' );
					break;

				case 'enable_two_factor_bypass_on_email_fail' :
					$sName = _wpsf__( 'By-Pass On Failure' );
					$sSummary = _wpsf__( 'If Sending Verification Email Sending Fails, Two-Factor Login Authentication Is Ignored' );
					$sDescription = _wpsf__( 'If you enable two-factor authentication and sending the email with the verification link fails, turning this setting on will by-pass the verification step. Use with caution.' );
					break;

				case 'login_limit_interval' :
					$sName = _wpsf__('Login Cooldown Interval');
					$sSummary = _wpsf__('Limit login attempts to every X seconds');
					$sDescription = _wpsf__( 'WordPress will process only ONE login attempt for every number of seconds specified.' )
									.'<br />'._wpsf__( 'Zero (0) turns this off.' )
									.' '.sprintf( _wpsf__( 'Default: "%s".' ), $this->getOptionsVo()->getOptDefault( 'login_limit_interval' ) );
					break;

				case 'enable_login_gasp_check' :
					$sName = _wpsf__( 'G.A.S.P Protection' );
					$sSummary = _wpsf__( 'Use G.A.S.P. Protection To Prevent Login Attempts By Bots' );
					$sDescription = _wpsf__( 'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques. Recommended: ON' );
					break;

				case 'enable_prevent_remote_post' :
					$sName = _wpsf__( 'Prevent Remote Login' );
					$sSummary = _wpsf__( 'Prevents Remote Login Attempts From Anywhere Except Your Site' );
					$sDescription = _wpsf__( 'Prevents any login attempts that do not originate from your website. This prevent bots from attempting to login remotely. Recommended: ON' );
					break;

				case 'enable_yubikey' :
					$sName = _wpsf__('Enable Yubikey Authentication');
					$sSummary = _wpsf__('Turn On / Off Yubikey Authentication On This Site');
					$sDescription = _wpsf__('Combined with your Yubikey API Key (below) this will form the basis of your Yubikey Authentication');
					break;

				case 'yubikey_app_id' :
					$sName = _wpsf__( 'Yubikey App ID' );
					$sSummary = _wpsf__( 'Your Unique Yubikey App ID' );
					$sDescription = _wpsf__( 'Combined with your Yubikey API Key this will form the basis of your Yubikey Authentication' )
									. _wpsf__( 'Please review the info link on how to obtain your own Yubikey App ID and API Key.' );
					break;

				case 'yubikey_api_key' :
					$sName = _wpsf__( 'Yubikey API Key' );
					$sSummary = _wpsf__( 'Your Unique Yubikey App API Key' );
					$sDescription = _wpsf__( 'Combined with your Yubikey App ID this will form the basis of your Yubikey Authentication.' )
									. _wpsf__( 'Please review the info link on how to get your own Yubikey App ID and API Key.' );
					break;

				case 'yubikey_unique_keys' :
					$sName = _wpsf__( 'Yubikey Unique Keys' );
					$sSummary = _wpsf__( 'Permitted "Username - Yubikey" Pairs For This Site' );
					$sDescription = '<strong>'. sprintf( _wpsf__( 'Format: %s' ), 'Username,Yubikey' ).'</strong>'
									.'<br />- '. _wpsf__( 'Provide Username<->Yubikey Pairs that are usable for this site.')
									.'<br />- '. _wpsf__( 'If a Username if not assigned a Yubikey, Yubikey Authentication is OFF for that user.' )
									.'<br />- '. _wpsf__( 'Each [Username,Key] pair should be separated by a new line: you only need to provide the first 12 characters of the yubikey.' );
					break;

				case 'enable_login_protect_log' :
					$sName = _wpsf__( 'Login Protect Logging' );
					$sSummary = _wpsf__( 'Turn on a detailed Login Protect Log' );
					$sDescription = _wpsf__( 'Will log every event related to login protection and how it is processed. ' )
									.'<br />'. _wpsf__( 'Not recommended to leave on unless you want to debug something and check the login protection is working as you expect.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
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
			// we always update it (but it wont need saved because we compare)
			$this->setOpt( 'last_login_time_file_path', $this->getController()->getRootDir().'mode.login_throttled' );
			return $this->getOpt( 'last_login_time_file_path' );
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
		public function getCustomLoginPath() {
			return $this->getOpt( 'rename_wplogin_path', '' );
		}

		/**
		 * @return bool
		 */
		public function getIsCustomLoginPathEnabled() {
			$sPath = $this->getOpt( 'rename_wplogin_path', '' );
			return !empty( $sPath );
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
			return $this->doPluginPrefix( $this->getOpt( 'two_factor_auth_table_name' ), '_' );
		}

		/**
		 * @return string
		 */
		public function getTwoFactorAuthCookieName() {
			return $this->getOpt( 'two_factor_auth_cookie_name' );
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

		/**
		 */
		protected function updateHandler() {
			parent::updateHandler();
			if ( version_compare( $this->getVersion(), '4.1.0', '<' ) ) {
				$this->setOpt( 'recreate_database_table', true );
			}
		}
	}

endif;