<?php
/*
Plugin Name: WordPress Simple Firewall
Plugin URI: http://icwp.io/2f
Description: A Simple WordPress Firewall
Version: 1.2.6
Author: iControlWP
Author URI: http://icwp.io/2e
*/

/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * "WordPress Simple Firewall" is
 * distributed under the GNU General Public License, Version 2,
 * June 1991. Copyright (C) 1989, 1991 Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110, USA
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

require_once( dirname(__FILE__).'/src/icwp-plugins-base.php' );
require_once( dirname(__FILE__).'/src/icwp-database-processor.php' );
require_once( dirname(__FILE__).'/src/icwp-data-processor.php' );

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Base_Plugin {
	
	const InputPrefix				= 'icwp_wpsf_';
	const OptionPrefix				= 'icwp_wpsf_';	//ALL database options use this as the prefix.
	
	/**
	 * Should be updated each new release.
	 * @var string
	 */
	static public $VERSION			= '1.2.6';
	
	protected $m_aAllPluginOptions;
	protected $m_aPluginOptions_FirewallBase;
	protected $m_aPluginOptions_BlockTypesSection;
	protected $m_aPluginOptions_WhitelistSection;
	protected $m_aPluginOptions_BlacklistSection;
	protected $m_aPluginOptions_BlockSection;
	protected $m_aPluginOptions_FirewallMiscSection;

	protected $m_aPluginOptions_LoginProtectBase;
	protected $m_aPluginOptions_LoginProtectTwoFactorSection;
	protected $m_aPluginOptions_LoginProtectOptionsSection;
	protected $m_aPluginOptions_LoginProtectLoggingSection;
	protected $m_aPluginOptions_LoginProtectMiscSection;

	/**
	 * @var ICWP_FirewallProcessor
	 */
	protected $m_oFirewallProcessor;
	
	/**
	 * @var ICWP_LoginProcessor
	 */
	protected $m_oLoginProcessor;
	
	/**
	 * @var ICWP_DatabaseProcessor
	 */
	protected $m_oDbProcessor;
	
	/**
	 * @var string
	 */
	protected $m_sDbTablePrefix;
	
	public function __construct() {
		parent::__construct();

		register_activation_hook( __FILE__, array( $this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( __FILE__, array( $this, 'onWpDeactivatePlugin' ) );
	//	register_uninstall_hook( __FILE__, array( &$this, 'onWpUninstallPlugin' ) );
		
		self::$PLUGIN_NAME	= basename(__FILE__);
		self::$PLUGIN_PATH	= plugin_basename( dirname(__FILE__) );
		self::$PLUGIN_DIR	= WP_PLUGIN_DIR.WORPIT_DS.self::$PLUGIN_PATH.WORPIT_DS;
		self::$PLUGIN_URL	= plugins_url( '/', __FILE__ ) ;
		self::$OPTION_PREFIX = self::OptionPrefix;
		
		$this->m_sDbTablePrefix = self::OptionPrefix;
		$this->m_sParentMenuIdSuffix = 'wpsf';

		// checks for filesystem based firewall overrides
		$this->override();

		if ( $this->isFirewallEnabled() ) {
			
			require_once( ABSPATH . 'wp-includes/pluggable.php' );
			if ( self::getOption( 'whitelist_admins' ) != 'Y' && is_super_admin()  ) {
				$this->runFirewallProcess();
			}
		}
		
		if ( $this->isLoginProtectEnabled() ) {
			$this->runLoginProtect();
		}

	}//__construct
	
	protected function override() {
		if ( is_file( self::$PLUGIN_DIR . 'forceOff' ) ) {
			self::updateOption( 'enable_firewall', 'N' );
			self::updateOption( 'enable_login_protect', 'N' );
		}
		else if ( is_file( self::$PLUGIN_DIR . 'forceOn' ) ) {
			self::updateOption( 'enable_firewall', 'Y' );
			self::updateOption( 'enable_login_protect', 'Y' );
		}
		else {
			return true;
		}
		$this->clearFirewallProcessorCache();
		$this->clearLoginProcessorCache();
	}
	
	protected function genSecretKey() {
		$sKey = self::getOption( 'secret_key' );
		if ( $sKey === false ) {
			$sKey = md5( mt_rand() );
			self::updateOption( 'secret_key', $sKey );
		}
		return $sKey;
	}
	
	/**
	 * @return boolean
	 */
	public function isFirewallEnabled() {
		
		if ( is_file( self::$PLUGIN_DIR . 'forceOff' ) ) {
			return false;
		}
		else if ( is_file( self::$PLUGIN_DIR . 'forceOn' ) ) {
			return true;
		}
		return self::getOption( 'enable_firewall' ) == 'Y';
	}
	
	/**
	 * @return boolean
	 */
	public function isLoginProtectEnabled() {
		
		if ( is_file( self::$PLUGIN_DIR . 'forceOff' ) ) {
			return false;
		}
		else if ( is_file( self::$PLUGIN_DIR . 'forceOn' ) ) {
			return true;
		}
		return self::getOption( 'enable_login_protect' ) == 'Y';
	}
	
	/**
	 * Updates the current log data with new data.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	protected function updateLogStore( $inaNewLogData ) {
		
		if ( !$this->isFirewallEnabled() ) {
			return true;
		}

		$aLogData = $this->m_oFirewallProcessor->getLogData();
		$this->loadDatabaseProcessor();
		$this->m_oDbProcessor->insertToTable( 'log', $aLogData );
	}
	
	protected function createLogStore() {
		$this->loadDatabaseProcessor();
		$this->m_oDbProcessor->createTables();
	}
	
	protected function getLogStore() {
		$this->loadDatabaseProcessor();
		return $this->m_oDbProcessor->selectAllFromTable( 'log' );
	}
	
	/**
	 * Loads the Firewall Processor for use throughout.  Will draw upon the db-cached object where
	 * appropriate.
	 * 
	 * @param boolean $infReset
	 */
	protected function loadFirewallProcessor( $infReset = false ) {
		
		require_once( dirname(__FILE__).'/src/icwp-firewall-processor.php' );
		
		if ( empty( $this->m_oFirewallProcessor ) ) {
			
			$this->m_oFirewallProcessor = self::getOption( 'firewall_processor' );
			
			if ( is_object( $this->m_oFirewallProcessor ) && ( $this->m_oFirewallProcessor instanceof ICWP_FirewallProcessor ) ) {
				$this->m_oFirewallProcessor->reset();
			}
			else {
				//collect up all the settings to pass to the processor
				$aSettingSlugs = array(
					'include_cookie_checks',
					'block_wplogin_access',
					'block_dir_traversal',
					'block_sql_queries',
					'block_wordpress_terms',
					'block_field_truncation',
					'block_exe_file_uploads',
					'block_leading_schema'
				);
				$aBlockSettings = array();
				foreach( $aSettingSlugs as $sSetting ) {
					$aBlockSettings[ $sSetting ] = self::getOption( $sSetting ) == 'Y';
				}
				$aIpWhitelist = self::getOption( 'ips_whitelist' );
				if ( $aIpWhitelist === false || !is_array( $aIpWhitelist ) ) {
					$aIpWhitelist = array();
					self::updateOption( 'ips_whitelist', $aIpWhitelist );
				}
				$aIpBlacklist = self::getOption( 'ips_blacklist' );
				if ( $aIpBlacklist === false || !is_array( $aIpBlacklist ) ) {
					$aIpBlacklist = array();
					self::updateOption( 'ips_blacklist', $aIpBlacklist );
				}
				$aPageWhitelist = self::getOption( 'page_params_whitelist' );
				if ( $aPageWhitelist === false || !is_array( $aPageWhitelist ) ) {
					$aPageWhitelist = array();
					self::updateOption( 'page_params_whitelist', $aPageWhitelist );
				}
				$sBlockResponse = self::getOption( 'block_response' );
				if ( empty( $sBlockResponse ) ) {
					$sBlockResponse = 'redirect_die';
					self::updateOption( 'block_response', $sBlockResponse );
				}
				$this->m_oFirewallProcessor = new ICWP_FirewallProcessor( $aBlockSettings, $aIpWhitelist, $aIpBlacklist, $aPageWhitelist, $sBlockResponse );

				self::updateOption( 'firewall_processor', $this->m_oFirewallProcessor ); // save it for the future
			}
			
		}
		else if ( $infReset ) {
			$this->m_oFirewallProcessor->reset();
		}
	}
	
	/**
	 * @param boolean $infReset
	 */
	protected function loadLoginProcessor( $infReset = false ) {
		
		require_once( dirname(__FILE__).'/src/icwp-login-processor.php' );

		if ( empty( $this->m_oLoginProcessor ) ) {
			$this->m_oLoginProcessor = self::getOption( 'login_processor' );

			if ( is_object( $this->m_oLoginProcessor ) && ( $this->m_oLoginProcessor instanceof ICWP_LoginProcessor ) ) {
				$this->m_oLoginProcessor->reset();
			}
			else {
				// collect up all the settings to pass to the processor
				$nRequiredLoginInterval = self::getOption( 'login_limit_interval' );
				if ( $nRequiredLoginInterval === false || $nRequiredLoginInterval < 0 ) {
					$nRequiredLoginInterval = 0;
					self::updateOption( 'login_limit_interval', $nRequiredLoginInterval );
				}
				$this->m_oLoginProcessor = new ICWP_LoginProcessor( 'login_auth', $nRequiredLoginInterval, $this->genSecretKey() );
			}
		}
		else if ( $infReset ) {
			$this->m_oLoginProcessor->reset();
		}
	}

	/**
	 * @param boolean $infReset
	 */
	protected function loadDatabaseProcessor( $infReset = false ) {
		if ( !isset( $this->m_oDbProcessor ) ) {
			$this->m_oDbProcessor = new ICWP_DatabaseProcessor( $this->m_sDbTablePrefix );
		}
	}
	
	/**
	 * Should be called from the constructor so as to ensure it is called as early as possible.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	public function runFirewallProcess() {
		
		$this->loadFirewallProcessor( true );
		
		$fFirewallBlockUser = !$this->m_oFirewallProcessor->doFirewallCheck();

		if ( $fFirewallBlockUser ) {
			switch( $sBlockResponse ) {
				case 'redirect_home':
					$this->m_oFirewallProcessor->logWarning(
						sprintf( 'Firewall Block: Visitor was sent HOME: %s', home_url() )
					);
					break;
				case 'redirect_404':
					$this->m_oFirewallProcessor->logWarning(
						sprintf( 'Firewall Block: Visitor was sent 404: %s', home_url().'/404?testfirewall' )
					);
					break;
				case 'redirect_die':
					$this->m_oFirewallProcessor->logWarning(
						sprintf( 'Firewall Block: Visitor connection was killed with %s', 'die()' )
					);
					break;
			}
			
			if ( self::getOption( 'block_send_email' ) === 'Y' ) {
				$sEmail = self::getOption( 'block_send_email_address');
				if ( empty($sEmail) || !is_email($sEmail) ) {
					$sEmail = get_option('admin_email');
				}
				if ( is_email( $sEmail ) ) {
					$this->m_oFirewallProcessor->sendBlockEmail( $sEmail );
				}
			}
			
			$this->updateLogStore( $this->m_oFirewallProcessor->getLogData() );
			$this->m_oFirewallProcessor->doFirewallBlock();
			
		}
		else {
			$this->updateLogStore( $this->m_oFirewallProcessor->getLogData() );
		}
	}
	
	/**
	 * Handles the creation of the database necessary for managing login protection.
	 */
	public function createLoginProtectStore() {
		$this->loadLoginProcessor();
		$this->m_oLoginProcessor->createTable();
	}
	
	/**
	 * Handles the running of all login protection processes.
	 */
	public function runLoginProtect() {

		if ( self::getOption( 'enable_two_factor_auth_by_ip' ) == 'Y' ) {

			// User has clicked a link in their email to validate their IP address for login.
			if ( isset( $_GET['wpsf-action'] ) && $_GET['wpsf-action'] == 'linkauth' ) {
				$this->validateUserAuthLink();
			}
			
			// If their click was successful we give them a lovely message
			if ( isset( $_GET['wpsfipverified']) ) {
				add_filter( 'login_message', array( $this, 'displayVerifiedUserMessage_Filter' ) );
			}
			
			// Add GASP checking to the login form.
			if ( self::getOption( 'enable_login_gasp_check' ) == 'Y' ) {
				add_action( 'login_form', array( $this, 'printGaspLoginCheck_Action' ) );
				add_filter( 'login_form_middle', array( $this, 'printGaspLoginCheck_Filter' ) );
			}
		
			// Performs all the custom login authentication checking
			add_action( 'wp_authenticate', array( $this, 'prepareLoginProcessor_Action' ) );
			
			// Check the current logged-in user every page load.
			add_action( 'init', array( $this, 'checkCurrentUserAuth_Action' ) );
			
			// we can hook this to the end because unlike the firewall, it doesn't kill a page load or redirect.
			add_action( 'shutdown', array( $this, 'saveLoginProcessor_Action' ) );
		}
	}
	
	public function printGaspLoginCheck_Action() {
		$this->loadLoginProcessor();
		echo $this->m_oLoginProcessor->getGaspLoginHtml();
	}
	
	public function printGaspLoginCheck_Filter() {
		$this->loadLoginProcessor();
		return $this->m_oLoginProcessor->getGaspLoginHtml();
	}
	
	/**
	 * Checks whether the current user that is logged-in is authenticated by IP address.
	 * 
	 * If the user is not found to be valid, they're logged out.
	 * 
	 * Should be hooked to 'init'
	 */
	public function checkCurrentUserAuth_Action() {
		
		if ( is_user_logged_in() ) {

			$this->loadLoginProcessor();
			
			$oUser = wp_get_current_user();
			$aData = array( 'wp_username' => $oUser->user_login );
			
			if ( !$this->m_oLoginProcessor->isUserVerified( $aData ) ) {
				wp_logout();
			}
		}
	}
	
	/**
	 * By hooking this action to the wp_authenticate action hook, we ensure we only load the login processor
	 * when it's necessary to do so - i.e. when there's a login in progress.
	 */
	public function prepareLoginProcessor_Action() {

		$this->loadLoginProcessor();
		
		// We give it a priority of 9 so we can check that the GASP requirements before all else.
		if ( self::getOption( 'enable_login_gasp_check' ) == 'Y' ) {
			add_filter( 'authenticate', array( $this->m_oLoginProcessor, 'checkLoginForGasp_Filter' ), 9, 3);
		}
		
		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', array( $this->m_oLoginProcessor, 'checkLoginInterval_Filter' ), 10, 3);
		
		// At this stage (30,3) WordPress has already authenticated the user. So if the login
		// is valid, the filter will have a valid WP_User object passed to it.
		add_filter( 'authenticate', array( $this->m_oLoginProcessor, 'checkUserAuthLogin_Filter' ), 30, 3);
	}
	
	/**
	 * Make sure and save the login processor after all is said and done.
	 */
	public function saveLoginProcessor_Action() {
		if ( isset( $this->m_oLoginProcessor ) ) {
			self::updateOption( 'login_processor', $this->m_oLoginProcessor );
		}
	}
	
	/**
	 * Checks the link details to ensure all is valid before setting the currently pending IP to active.
	 * 
	 * @return boolean
	 */
	public function validateUserAuthLink() {
		// wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid
		
		if ( !isset( $_GET['wpsfkey'] ) || $_GET['wpsfkey'] !== self::getOption('secret_key') ) {
			return false;
		}
		if ( empty( $_GET['username'] ) || empty( $_GET['uniqueid'] ) ) {
			return false;
		}

		// By now we have ascertain the verify link is valid.
		$this->loadLoginProcessor();
		$aWhere = array(
			'unique_id'		=> $_GET['uniqueid'],
			'wp_username'	=> $_GET['username']
		);
		
		if ( $this->m_oLoginProcessor->loginAuthMakeActive( $aWhere ) ) {
			header( "Location: ".site_url().'/wp-login.php?wpsfipverified=1' );
		}
		else {
			header( "Location: ".home_url() );
		}
	}
	
	public function displayVerifiedUserMessage_Filter( $insMessage ) {
		$sStyles .= 'background-color: #FAFFE8; border: 1px solid #DDDDDD; margin: 8px 0 10px 8px; padding: 16px;';
		$insMessage .= '<h3 style="'.$sStyles.'">You successfully verified your IP address - you may now login.</h3>';
		return $insMessage;
	}
	
	protected function initPluginOptions() {

		$this->m_aPluginOptions_FirewallBase = 	array(
			'section_title' => 'Enable WordPress Firewall',
			'section_options' => array(
				array(
					'enable_firewall',
					'',	'N',
					'checkbox',
					'Enable Firewall',
					'Enable (or Disable) The WordPress Firewall Feature',
					'Regardless of any other settings, this option will turn Off the Firewall feature, or enable your selected Firewall options.' )
			)
		);
		$this->m_aPluginOptions_BlockTypesSection = 	array(
			'section_title' => 'Firewall Blocking Options',
			'section_options' => array(
				array(
					'include_cookie_checks',
					'',
					'N',
					'checkbox',
					'Include Cookies',
					'Also Test Cookie Values In Firewall Tests',
					'The firewall will test GET and POST, but with this option checked, it will also COOKIE values for the same.'
				),
				array(
					'block_wplogin_access',
					'',
					'N',
					'checkbox',
					'Login Access',
					'Block WP Login Access',
					'This will block access to the WordPress Login (wp-login.php) except to IP Addresses on the whitelist. If the IP whitelist is empty, this setting is ignored (so you do not lock yourself out!)'
				),
				array(
					'block_dir_traversal',
					'',
					'N',
					'checkbox',
					'Directory Traversals',
					'Block Directory Traversals',
					'This will block directory traversal paths in in application parameters (../, ../../etc/passwd, etc.)'
				),
				array(
					'block_sql_queries',
					'',
					'N',
					'checkbox',
					'SQL Queries',
					'Block SQL Queries',
					'This will block in application parameters (union select, concat(, /**/, etc.).'
				),
				array(
					'block_wordpress_terms',
					'',
					'N',
					'checkbox',
					'WordPress Terms',
					'Block WordPress Specific Terms',
					'This will block WordPress specific terms in application parameters (wp_, user_login, etc.).'
				),
				array(
					'block_field_truncation',
					'',
					'N',
					'checkbox',
					'Field Truncation',
					'Block Field Truncation Attacks',
					'This will block field truncation attacks in application parameters.'
				),
				array(
					'block_exe_file_uploads',
					'',
					'N',
					'checkbox',
					'Exe File Uploads',
					'Block Executable File Uploads',
					'This will block executable file uploads (.php, .exe, etc.).'
				),
				array(
					'block_leading_schema',
					'',
					'N',
					'checkbox',
					'Leading Schemas',
					'Block Leading Schemas (HTTPS / HTTP)',
					'This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with many plugins).'
				)
			),
		);
		$this->m_aRedirectOptions = array( 'select',
			array( 'redirect_die', 		'Die' ),
			array( 'redirect_home',		'Redirect To Home Page' ),
			array( 'redirect_404',		'Return 404' ),
		);
		$this->m_aPluginOptions_BlockSection = array(
			'section_title' => 'Choose Firewall Block Response',
			'section_options' => array(
				array( 'block_response',			'',	'none',	$this->m_aRedirectOptions,	'Block Response',	'Choose how the firewall responds when it blocks a request', '' ),
				array( 'block_send_email',			'',	'N',	'checkbox',	'Send Email Report',	'When a visitor is blocked it will send an email to the blog admin', 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host.' ),
				array( 'block_send_email_address',	'',	'',		'email',	'Report Email',		'Where to send email reports', 'If this is empty, it will default to the blog admin email address.' ),
			)
		);
		
		$this->m_aPluginOptions_WhitelistSection = array(
			'section_title' => 'Whitelist - IPs, Pages, Parameters, and Users that by-pass the Firewall',
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'none',
					'ip_addresses',
					'Whitelist IP Addresses',
					'Choose IP Addresses that are never subjected to Firewall Rules',
					sprintf( 'Take a new line per address. Your IP address is: %s', '<span class="code">'.self::GetVisitorIpAddress().'</span>' )
				),
				array(
					'page_params_whitelist',
					'',
					'',
					'comma_separated_lists',
					'Whitelist Paramaters',
					'Detail pages and parameters that are whitelisted (ignored)',
					'This should be used with caution and you should only provide parameter names that you need to have excluded.'
						.' [<a href="http://icwp.io/2a" target="_blank">Help</a>]'
				),
				array(
					'whitelist_admins',
					'',
					'N',
					'checkbox',
					'Ignore Administrators',
					'Ignore users logged in as Administrator',
					'Authenticated administrator users will not be processed by the firewall.'
				)
			)
		);
		
		$this->m_aPluginOptions_BlacklistSection = array(
			'section_title' => 'Choose IP Addresses To Blacklist',
			'section_options' => array(
				array(
					'ips_blacklist',
					'',
					'none',
					'ip_addresses',
					'Blacklist IP Addresses',
					'Choose IP Addresses that are always blocked access to the site',
					'Take a new line per address. Each IP Address must be valid and will be checked.'
				)
			)
		);
		$this->m_aPluginOptions_FirewallMiscSection = array(
			'section_title' => 'Miscellaneous Plugin Options',
			'section_options' => array(
				array(
					'enable_firewall_log',
					'',	'N',
					'checkbox',
					'Firewall Logging',
					'Turn on a detailed Firewall Log',
					'Will log every visit to the site and how the firewall processes it. Not recommended to leave on unless you want to debug something and check the firewall is working as you expect.'
				),
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					'Delete Plugin Settings',
					'Delete All Plugin Settings Upon Plugin Deactivation',
					'Careful: Removes all plugin options when you deactivite the plugin.'
				),
			),
		);
		
		$this->m_aPluginOptions_LoginProtectBase = array(
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
				),
			),
		);
		$this->m_aPluginOptions_LoginProtectTwoFactorSection = array(
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
			),
		);
		$this->m_aPluginOptions_LoginProtectOptionsSection = array(
			'section_title' => 'Login Protection Options',
			'section_options' => array(
				array(
					'login_limit_interval',
					'',
					'0',
					'integer',
					'Login Cooldown Interval',
					'Limit login attempts to every X seconds',
					'WordPress will process only ONE login attempt for every number of seconds specified. Zero (0) turns this off.'
				),
				array(
					'enable_login_gasp_check',
					'',
					'0',
					'checkbox',
					'G.A.S.P Protection',
					'Prevent Login By Bots using G.A.S.P. Protection',
					'Adds a dynamically (Javascript) generated checkbox to the login form that prevents bots using automated login techniques.'
				),
			),
		);
		
		$this->m_aPluginOptions_LoginProtectLoggingSection = array(
			'section_title' => 'Logging Options',
			'section_options' => array(
				/*
				array(
					'enable_login_protect_log',
					'',	'N',
					'checkbox',
					'Login Protect Logging',
					'Turn on a detailed Login Protect Log',
					'Will log every event related to login protection and how it is processed. Not recommended to leave on unless you want to debug something and check the login protection is working as you expect.'
				),
				*/
			),
		);
		
		$this->m_aPluginOptions_LoginProtectMiscSection = array(
			'section_title' => 'Miscellaneous Plugin Options',
			'section_options' => array(
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					'Delete Plugin Settings',
					'Delete All Plugin Settings Upon Plugin Deactivation',
					'Careful: Removes all plugin options when you deactivite the plugin.'
				),
			),
		);

		$this->m_aAllPluginOptions = array(
			&$this->m_aPluginOptions_FirewallBase,
			&$this->m_aPluginOptions_BlockSection,
			&$this->m_aPluginOptions_WhitelistSection,
			&$this->m_aPluginOptions_BlacklistSection,
			&$this->m_aPluginOptions_BlockTypesSection,
			&$this->m_aPluginOptions_FirewallMiscSection,
			&$this->m_aPluginOptions_LoginProtectBase,
			&$this->m_aPluginOptions_LoginProtectTwoFactorSection,
			&$this->m_aPluginOptions_LoginProtectOptionsSection,
			&$this->m_aPluginOptions_LoginProtectLoggingSection,
			&$this->m_aPluginOptions_LoginProtectMiscSection
		);

		return true;
		
	}//initPluginOptions
	
	public function onWpPluginsLoaded() {
		parent::onWpPluginsLoaded();
	}//onWpPluginsLoaded

	public function onWpInit() {
		parent::onWpInit();
	}//onWpInit
	
	public function onWpAdminInit() {
		parent::onWpAdminInit();
		
		// If it's a plugin admin page, we do certain things we don't do anywhere else.
		if ( $this->isIcwpPluginAdminPage()) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueueBootstrapAdminCss' ), 99 );
		}
		
		// This is only done on WP Admin loads so as not to affect the front-end.
		$this->filterIpLists();
		
		//Multilingual support.
		load_plugin_textdomain( 'hlt-wordpress-bootstrap-css', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	
	protected function createPluginSubMenuItems(){
		$this->m_aPluginMenu = array(
			//Menu Page Title => Menu Item name, page ID (slug), callback function for this page - i.e. what to do/load.
			$this->getSubmenuPageTitle( 'Firewall' ) => array( 'Firewall', $this->getSubmenuId('firewall-config'), 'onDisplayFirewallConfig' ),
			$this->getSubmenuPageTitle( 'Login Protect' ) => array( 'Login Protect', $this->getSubmenuId('login-protect'), 'onDisplayLoginProtect' ),
			$this->getSubmenuPageTitle( 'Log' ) => array( 'Log', $this->getSubmenuId('firewall-log'), 'onDisplayFirewallLog' )
		);
	}//createPluginSubMenuItems

	protected function handlePluginUpgrade() {
		
		$sCurrentPluginVersion = self::getOption( 'current_plugin_version' );
		
		if ( $sCurrentPluginVersion !== self::$VERSION && current_user_can( 'manage_options' ) ) {

			$this->clearFirewallProcessorCache();
			$this->clearLoginProcessorCache();
			
			// Added Login Protect since v1.2.0
			$this->createLoginProtectStore();
			
			// create new log database table
			$this->createLogStore();
			// delete the old option.
			if ( version_compare( $sCurrentPluginVersion, '1.1.1', '<' ) ) {
				$this->deleteOption( 'firewall_log' );
			}
			
			// introduced IP ranges in version 1.1.0 so anyone that is less than this must convert their IPs.
			if ( version_compare( $sCurrentPluginVersion, '1.1.0', '<' ) ) {
				
				$aIpWhitelist = self::getOption( 'ips_whitelist' );
				if ( !empty( $aIpWhitelist ) ) {
					$aNewList = array();
					foreach( $aIpWhitelist as $sAddress ) {
						$aNewList[ $sAddress ] = '';
					}
					self::updateOption( 'ips_whitelist', ICWP_DataProcessor::Add_New_Raw_Ips( array(), $aNewList ) );
				}
				
				$aIpBlacklist = self::getOption( 'ips_blacklist' );
				if ( !empty($aIpBlacklist) ) {
					$aNewList = array();
					foreach( $aIpBlacklist as $sAddress ) {
						$aNewList[ $sAddress ] = '';
					}
					self::updateOption( 'ips_blacklist', ICWP_DataProcessor::Add_New_Raw_Ips( array(), $aNewList ) );
				}
			}
			
			//Set the flag so that this update handler isn't run again for this version.
			self::updateOption( 'current_plugin_version', self::$VERSION );
			
		}//if
		
		//Someone clicked the button to acknowledge the update
		if ( isset( $_POST[self::OptionPrefix.'hide_update_notice'] ) && isset( $_POST['user_id'] ) ) {
			$result = update_user_meta( $_POST['user_id'], self::OptionPrefix.'current_version', self::$VERSION );
			
			if ( $this->isShowMarketing() ) {
				wp_redirect( admin_url( "admin.php?page=".$this->getFullParentMenuId() ) );
			}
			else {
				wp_redirect( admin_url( $_POST['redirect_page'] ) );
			}
		}
	}
	
	public function onWpAdminNotices() {
		
		//Do we have admin priviledges?
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->adminNoticeVersionUpgrade();
		$this->adminNoticeOptionsUpdated();
	}
	
	public function onDisplayMainMenu() {

		//populates plugin options with existing configuration
		$this->readyAllPluginOptions();
		
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'aAllOptions'		=> $aAvailableOptions,
			'fShowAds'			=> $this->isShowMarketing(),

			'fFirewallOn'		=> $this->isFirewallEnabled(),
			'fFirewallLogOn'	=> self::getOption( 'enable_firewall_log' )== 'Y',
			'sBlockResponse'	=> self::getOption( 'block_response' ),
			'fBlockSendEmail'	=> self::getOption( 'block_send_email' ) == 'Y',
			'aIpWhitelist'		=> self::getOption( 'ips_whitelist' ),
			'aIpBlacklist'		=> self::getOption( 'ips_blacklist' ),
			'fBlockLogin'		=> self::getOption( 'block_wplogin_access' )== 'Y',
			'fBlockDirTrav'		=> self::getOption( 'block_dir_traversal' )== 'Y',
			'fBlockSql'			=> self::getOption( 'block_sql_queries' )== 'Y',
			'fBlockWpTerms'		=> self::getOption( 'block_wordpress_terms' )== 'Y',
			'fBlockFieldTrun'	=> self::getOption( 'block_field_truncation' )== 'Y',
			'fBlockExeFile'		=> self::getOption( 'block_exe_file_uploads' )== 'Y',
			'fBlockSchema'		=> self::getOption( 'block_leading_schema' )== 'Y',

			'fLoginProtectOn'		=> $this->isLoginProtectEnabled(),
			'fTwoFactorIpOn'		=> self::getOption( 'enable_two_factor_auth_by_ip' ) == 'Y',
			'sLoginLimitInterval'	=> self::getOption( 'login_limit_interval' ),
		);

		$this->display( 'icwp_'.$this->m_sParentMenuIdSuffix.'_index', $aData );
	}

	public function onDisplayFirewallConfig() {
		
		//populates plugin options with existing configuration
		$this->readyAllPluginOptions();

		//Specify what set of options are available for this page
		if ( $this->isFirewallEnabled() ) {
			$aAvailableOptions = array(
				&$this->m_aPluginOptions_FirewallBase,
				&$this->m_aPluginOptions_BlockSection,
				&$this->m_aPluginOptions_WhitelistSection,
				&$this->m_aPluginOptions_BlacklistSection,
				&$this->m_aPluginOptions_BlockTypesSection,
				&$this->m_aPluginOptions_FirewallMiscSection
			);
		}
		else {
			$aAvailableOptions = array(
				&$this->m_aPluginOptions_FirewallBase,
			);
		}

		$sAllFormInputOptions = $this->collateAllFormInputsForAllOptions( $aAvailableOptions );
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'fShowAds'			=> $this->isShowMarketing(),
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
			'nonce_field'		=> $this->getSubmenuId('firewall').'_config',
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId('firewall-config'),
		);

		$this->display( 'icwp_wpsf_firewall_config_index', $aData );
	}
	
	public function onDisplayFirewallLog() {

		$aIpWhitelist = self::getOption( 'ips_whitelist' );
		$aIpBlacklist = self::getOption( 'ips_blacklist' );

		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'firewall_log'		=> array_reverse( $this->getLogStore() ),
			'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
			'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
			'fShowAds'			=> $this->isShowMarketing(),
			'nonce_field'		=> $this->getSubmenuId('firewall').'_log',
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId('firewall-log'),
		);

		$this->display( 'icwp_wpsf_firewall_log_index', $aData );
	}
	
	public function onDisplayLoginProtect() {
		
		//populates plugin options with existing configuration
		$this->readyAllPluginOptions();

		//Specify what set of options are available for this page
		if ( $this->isLoginProtectEnabled() ) {
			$aAvailableOptions = array(
				&$this->m_aPluginOptions_LoginProtectBase,
				&$this->m_aPluginOptions_LoginProtectTwoFactorSection,
				&$this->m_aPluginOptions_LoginProtectOptionsSection,
// 				&$this->m_aPluginOptions_LoginProtectLoggingSection,
				&$this->m_aPluginOptions_LoginProtectMiscSection
			);
		}
		else {
			$aAvailableOptions = array(
				&$this->m_aPluginOptions_LoginProtectBase
			);
		}

		$sAllFormInputOptions = $this->collateAllFormInputsForAllOptions( $aAvailableOptions );
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'fShowAds'			=> $this->isShowMarketing(),
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
			'nonce_field'		=> $this->getSubmenuId('login-protect'),
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId('login-protect'),
		);

		$this->display( 'icwp_wpsf_login_protect_config_index', $aData );
	}
	
	protected function handlePluginFormSubmit() {
		
		if ( !isset( $_POST['icwp_plugin_form_submit'] ) && !isset( $_GET['icwp_link_action'] ) ) {
			return;
		}
		
		if ( isset( $_GET['page'] ) ) {
			switch ( $_GET['page'] ) {
				case $this->getSubmenuId( 'firewall-config' ):
					$this->handleSubmit_FirewallConfigOptions();
					break;
				case $this->getSubmenuId( 'firewall-log' ):
					$this->handleSubmit_FirewallLog();
					break;
				case $this->getSubmenuId( 'login-protect' ):
					$this->handleSubmit_LoginProtect();
					break;
			}
		}
		
		if ( !self::$m_fUpdateSuccessTracker ) {
			self::updateOption( 'feedback_admin_notice', 'Updating Settings <strong>Failed</strong>.' );
		}
		else {
			self::updateOption( 'feedback_admin_notice', 'Updating Settings <strong>Succeeded</strong>.' );
		}
		
	}
	
	protected function handleSubmit_FirewallConfigOptions() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('firewall').'_config' );

		$this->clearFirewallProcessorCache();
		
		if ( isset($_POST[ 'import-wpf2-submit' ] ) ) {
			$this->importFromFirewall2Plugin();
			return;
		}
		
		if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
	}
	
	protected function handleSubmit_LoginProtect() {
		//Ensures we're actually getting this request from WP.
		wp_verify_nonce ( $this->getSubmenuId('login-protect' ) );

		$this->clearLoginProcessorCache();
		
		if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
	}
	
	protected function handleSubmit_FirewallLog() {

		// Ensures we're actually getting this request from a valid WP submission.
		wp_verify_nonce ( $this->getSubmenuId('firewall').'_log' );
		
		// At the time of writing the page only has 1 form submission item - clear log
		if ( isset( $_POST['clear_log_submit'] ) ) {
			$this->loadDatabaseProcessor();
			$this->m_oDbProcessor->emptyTable( 'log' );
		}
		else if ( isset( $_GET['blackip'] ) ) {
			$this->addRawIpsToList( 'ips_blacklist', array( $_GET['blackip'] ) );
		}
		else if ( isset( $_GET['unblackip'] ) ) {
			$this->removeRawIpsFromList( 'ips_blacklist', array( $_GET['unblackip'] ) );
		}
		else if ( isset( $_GET['whiteip'] ) ) {
			$this->addRawIpsToList( 'ips_whitelist', array( $_GET['whiteip'] ) );
		}
		else if ( isset( $_GET['unwhiteip'] ) ) {
			$this->removeRawIpsFromList( 'ips_whitelist', array( $_GET['unwhiteip'] ) );
		}
		wp_safe_redirect( admin_url( "admin.php?page=".$this->getSubmenuId('firewall-log') ) ); //means no admin message is displayed
		exit();
	}
	
	protected function clearFirewallProcessorCache() {
		$this->m_oFirewallProcessor = false;
		self::updateOption( 'firewall_processor', $this->m_oFirewallProcessor );
	}
	
	protected function clearLoginProcessorCache() {
		$this->m_oLoginProcessor = false;
		self::updateOption( 'login_processor', $this->m_oLoginProcessor );
	}
	
	protected function importFromFirewall2Plugin() {

		require_once( dirname(__FILE__).'/src/icwp-import-wpf2-processor.php' );
		$oImportProcessor = new ICWP_ImportWpf2Processor( self::$OPTION_PREFIX );
		$oImportProcessor->runImport();
	}
	
	public function onWpPluginActionLinks( $inaLinks, $insFile ) {
		if ( $insFile == plugin_basename( __FILE__ ) ) {
			$sSettingsLink = '<a href="'.admin_url( "admin.php" ).'?page='.$this->getSubmenuId('firewall-config').'">' . 'Firewall' . '</a>';
			array_unshift( $inaLinks, $sSettingsLink );
		}
		return $inaLinks;
	}
	
	protected function deleteAllPluginDbOptions() {
		
		parent::deleteAllPluginDbOptions();
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->loadDatabaseProcessor();
		$this->m_oDbProcessor->deleteAllTables();

		$this->loadLoginProcessor();
		$this->m_oLoginProcessor->dropTable();
		
		$aExtras = array(
			'current_plugin_version',
			'feedback_admin_notice',
			'firewall_processor',
			'login_processor',
			'secret_key'
		);
		foreach( $aExtras as $sOption ) {
			$this->deleteOption( $sOption );
		}
	}
	
	public function onWpDeactivatePlugin() {
		
		if ( $this->getOption('delete_on_deactivate') == 'Y' ) {
			$this->deleteAllPluginDbOptions();
		}
		
	}//onWpDeactivatePlugin
	
	public function onWpActivatePlugin() { }
	
	public function enqueueBootstrapAdminCss() {
		wp_register_style( 'worpit_bootstrap_wpadmin_css', $this->getCssUrl( 'bootstrap-wpadmin.css' ), false, self::$VERSION );
		wp_enqueue_style( 'worpit_bootstrap_wpadmin_css' );
		wp_register_style( 'worpit_bootstrap_wpadmin_css_fixes', $this->getCssUrl('bootstrap-wpadmin-fixes.css'),  array('worpit_bootstrap_wpadmin_css'), self::$VERSION );
		wp_enqueue_style( 'worpit_bootstrap_wpadmin_css_fixes' );
	}

	public function addRawIpsToList( $insListName, $inaNewIps ) {
		
		$aIplist = self::getOption( $insListName );
		if ( empty( $aIplist ) ) {
			$aIplist = array();
		}
		$aNewList = array();
		foreach( $inaNewIps as $sAddress ) {
			$aNewList[ $sAddress ] = '';
		}
		self::updateOption( $insListName, ICWP_DataProcessor::Add_New_Raw_Ips( $aIplist, $aNewList ) );
		$this->clearFirewallProcessorCache();
	}

	public function removeRawIpsFromList( $insListName, $inaRemoveIps ) {
		
		$aIplist = self::getOption( $insListName );
		if ( empty( $aIplist ) || empty( $inaRemoveIps ) ) {
			return;
		}
		self::updateOption( $insListName, ICWP_DataProcessor::Remove_Raw_Ips( $aIplist, $inaRemoveIps ) );
		$this->clearFirewallProcessorCache();
	}
	
	/**
	 * 
	 */
	protected function filterIpLists() {

		$aWhitelistIps = self::getOption( 'ips_whitelist' );
		if ( !is_array($aWhitelistIps) ) {
			$aWhitelistIps = array();
			self::updateOption( 'ips_whitelist', $aWhitelistIps );
		}
		$nNewAddedCount = 0;
		$mResult = $this->processIpFilter( $aWhitelistIps, 'icwp_simple_firewall_whitelist_ips', $nNewAddedCount );
		if ( $mResult !== false && $nNewAddedCount > 0 ) {
			$this->clearFirewallProcessorCache();
			self::updateOption( 'ips_whitelist', $mResult );
		}

		$aBlacklistIps = self::getOption( 'ips_blacklist' );
		if ( !is_array($aBlacklistIps) ) {
			$aBlacklistIps = array();
			self::updateOption( 'ips_blacklist', $aBlacklistIps );
		}
		$nNewAddedCount = 0;
		$mResult = $this->processIpFilter( $aBlacklistIps, 'icwp_simple_firewall_blacklist_ips', $nNewAddedCount );
		if ( $mResult !== false && $nNewAddedCount > 0 ) {
			self::updateOption( 'ips_blacklist', $mResult );
			$this->clearFirewallProcessorCache();
		}
	}

	/**
	 * @param array $inaRawIpList
	 * @return array
	 */
	protected function processIpFilter( $inaExistingList, $insFilterName, &$outnNewAdded = 0 ) {
		
		$aFilterIps = array();
		$aFilterIps = apply_filters( $insFilterName, $aFilterIps );
		
		if ( !empty( $aFilterIps ) ) {
			
			$aNewIps = array();
			
			foreach( $aFilterIps as $mKey => $sValue ) {
				
				if ( is_string( $mKey ) ) { //it's the IP
					$sIP = $mKey;
					$sLabel = $sValue;
				}
				else { //it's not an associative array, so the value is the IP
					$sIP = $sValue;
					$sLabel = '';
				}
				$aNewIps[ $sIP ] = $sLabel;
			}
			return ICWP_DataProcessor::Add_New_Raw_Ips( $inaExistingList, $aNewIps, $outnNewAdded );
		}
		return false;
	}
	
	/**
	 * Shows the update notification - will bail out if the current user is not an admin
	 */
	private function adminNoticeVersionUpgrade() {
	
		$oCurrentUser = wp_get_current_user();
		if ( !($oCurrentUser instanceof WP_User) ) {
			return;
		}
		$nUserId = $oCurrentUser->ID;
		$sCurrentVersion = get_user_meta( $nUserId, self::OptionPrefix.'current_version', true );
		// A guard whereby if we can't ever get a value for this meta, it means we can never set it.
		// If we can never set it, we shouldn't force the Ads on those users who can't get rid of it.
		if ( $sCurrentVersion === false ) { //the value has never been set, or it's been installed for the first time.
			$result = update_user_meta( $nUserId, self::OptionPrefix.'current_version', self::$VERSION );
			return; //meaning we don't show the update notice upon new installations and for those people who can't set the version in their meta.
		}
	
		if ( $sCurrentVersion !== self::$VERSION ) {
				
			$sRedirectPage = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : 'index.php';
			$sRedirectPage = 'admin.php?page=icwp-wpsf';
			ob_start();
			?>
					<style>
						a#fromIcwp { padding: 0 5px; border-bottom: 1px dashed rgba(0,0,0,0.1); color: blue; font-weight: bold; }
					</style>
					<form id="IcwpUpdateNotice" method="post" action="admin.php?page=<?php echo $this->getSubmenuId('firewall-config'); ?>">
						<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
						<input type="hidden" value="1" name="<?php echo self::OptionPrefix; ?>hide_update_notice" id="<?php echo self::OptionPrefix; ?>hide_update_notice">
						<input type="hidden" value="<?php echo $nUserId; ?>" name="user_id" id="user_id">
						<h4 style="margin:10px 0 3px;">
							Note: WordPress Simple Firewall plugin <u>does not automatically turn on</u> when you install/update. There may also be
							<a href="http://icwp.io/27" id="fromIcwp" title="WordPress Simple Firewall Plugin" target="_blank">important updates to read about</a>.
						</h4>
						<input type="submit" value="Okay, show me the dashboard." name="submit" class="button" style="float:left; margin-bottom:10px;">
						<div style="clear:both;"></div>
					</form>
				<?php
				$sNotice = ob_get_contents();
				ob_end_clean();
				
				$this->getAdminNotice( $sNotice, 'updated', true );
			}
			
		}//adminNoticeVersionUpgrade
		
	private function adminNoticeOptionsUpdated() {
			
		$sAdminFeedbackNotice = $this->getOption( 'feedback_admin_notice' );
		if ( !empty( $sAdminFeedbackNotice ) ) {
			$sNotice = '<p>'.$sAdminFeedbackNotice.'</p>';
			$this->getAdminNotice( $sNotice, 'updated', true );
			$this->updateOption( 'feedback_admin_notice', '' );
		}
		
	}//adminNoticeOptionsUpdated
}

endif;

$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall();
