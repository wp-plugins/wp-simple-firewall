<?php
/*
Plugin Name: WordPress Simple Firewall
Plugin URI: http://www.icontrolwp.com/
Description: A Simple WordPress Firewall
Version: 1.2.0
Author: iControlWP
Author URI: http://icwp.io/v
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
require_once( dirname(__FILE__).'/src/icwp-firewall-processor.php' );
require_once( dirname(__FILE__).'/src/icwp-login-processor.php' );
require_once( dirname(__FILE__).'/src/icwp-database-processor.php' );
require_once( dirname(__FILE__).'/src/icwp-data-processor.php' );

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Base_Plugin {
	
	const InputPrefix				= 'icwp_wpsf_';
	const OptionPrefix				= 'icwp_wpsf_'; //ALL database options use this as the prefix.
	
	static public $VERSION			= '1.2.0'; //SHOULD BE UPDATED UPON EACH NEW RELEASE
	
	protected $m_aAllPluginOptions;
	protected $m_aPluginOptions_Base;
	protected $m_aPluginOptions_BlockTypesSection;
	protected $m_aPluginOptions_WhitelistSection;
	protected $m_aPluginOptions_BlacklistSection;
	protected $m_aPluginOptions_BlockSection;
	protected $m_aPluginOptions_MiscOptionsSection;

	protected $m_aPluginOptions_LoginProtectSection;
	protected $m_aPluginOptions_LoginProtectTwoFactorSection;

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

		// generates the plugin's unique secret key. 
		$this->genSecretKey();
		
		if ( self::getOption( 'enable_firewall' ) == 'Y' ) {
			
			require_once( ABSPATH . 'wp-includes/pluggable.php' );
			if ( self::getOption( 'whitelist_admins' ) != 'Y' && is_super_admin()  ) {
				$this->m_oFirewallProcessor = self::getOption( 'firewall_processor' );
				$this->runFirewallProcess();
			}
		}
		
		if ( self::getOption( 'enable_login_protect' ) == 'Y' ) {
			$this->runLoginProtectProcess();
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
	}
	
	protected function genSecretKey() {
		if ( self::getOption( 'secret_key' ) === false ) {
			$sKey = md5( mt_rand() );
			self::updateOption( 'secret_key', $sKey );
		}
	}
	
	/**
	 * Updates the current log data with new data.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	protected function updateLogStore( $inaNewLogData ) {
		
		if ( self::getOption( 'enable_firewall_log' ) != 'Y' ) {
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
	 * 
	 */
	protected function loadFirewallProcessor( $infReset = false ) {
		
		$this->m_oFirewallProcessor = self::getOption( 'firewall_processor' );
		if ( empty( $this->m_oFirewallProcessor ) ) {
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
			if ( empty($aIpWhitelist) ) {
				$aIpWhitelist = array();
			}
			$aIpBlacklist = self::getOption( 'ips_blacklist' );
			if ( empty($aIpBlacklist) ) {
				$aIpBlacklist = array();
			}
			$aPageWhitelist = self::getOption( 'page_params_whitelist' );
			if ( empty($aPageWhitelist) ) {
				$aPageWhitelist = array();
			}
			$sBlockResponse = self::getOption( 'block_response' );
			
			$this->m_oFirewallProcessor = new ICWP_FirewallProcessor( $aBlockSettings, $aIpWhitelist, $aIpBlacklist, $aPageWhitelist, $sBlockResponse );
			self::updateOption( 'firewall_processor', $this->m_oFirewallProcessor );
		} else if ( $infReset ) {
			$this->m_oFirewallProcessor->reset();
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
	 * @param boolean $infReset
	 */
	protected function loadLoginProcessor( $infReset = false ) {
		if ( !isset( $this->m_oLoginProcessor ) ) {
			$this->m_oLoginProcessor = new ICWP_LoginProcessor( 'login_auth' );
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
	public function runLoginProtectProcess() {
		
		$this->loadLoginProcessor( true );
		
		if ( self::getOption( 'enable_two_factor_auth_by_ip' ) == 'Y' ) {
			
			// User has clicked a link in their email to validate their IP addres for login.
			if ( isset( $_GET['wpsf-action'] ) && $_GET['wpsf-action'] == 'linkauth' ) {
				$this->validateUserAuthLink();
			}
			
			// At this stage (30,3) WordPress has already authenticated the user. So if the login
			// is valid, the filter will have a valid WP_User object passed to it.
			add_filter( 'authenticate', array( $this, 'checkUserAuthLogin' ), 30, 3);
			
			// Check the logged-in current user every page load.
			add_action( 'init', array( $this, 'checkCurrentUserAuth' ) );
		}
	}
	
	/**
	 * Checks the link details to ensure all is valid before setting the currently pending IP to active
	 * 
	 * @return boolean
	 */
	public function validateUserAuthLink() {
		// wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid
		
		if ( !isset( $_GET['wpsfkey'] ) && $_GET['wpsfkey'] !== self::getOption('secret_key') ) {
			return false;
		}
		if ( empty( $_GET['username'] ) || empty( $_GET['uniqueid'] ) ) {
			return false;
		}
		
		$aWhere = array(
			'unique_id'		=> $_GET['uniqueid'],
			'wp_username'	=> $_GET['username']
		);
		if ( $this->m_oLoginProcessor->loginAuthMakeActive( $aWhere ) ) {
			header( "Location: ".home_url().'/wp-login.php' );
		}
	}
	
	/**
	 * If $inoUser is a valid WP_User object, then the user logged in correctly.
	 * 
	 * The flow is as follows:
	 * 0. If username is empty, there was no login attempt.
	 * 1. First we determine whether the user's login credentials were valid according to WordPress ($fUserLoginSuccess)
	 * 2. Then we ask our 2-factor processor whether the current IP address + username combination is authenticated.
	 * 		a) if yes, we return the WP_User object and login proceeds as per usual.
	 * 		b) if no, we return null, which will send the message back to the user that the login details were invalid.
	 * 3. If however the user's IP address + username combination is not authenticated, we react differently. We do not want
	 * 	to give away whether a login was successful, or even the login username details exist. So:
	 * 		a) if the login was a success we add a pending record to the authentication DB for this username+IP address combination and send the appropriate verification email
	 * 		b) then, we give back a message saying that if the login was successful, they would have received a verification email. In this way we give nothing away.
	 * 		c) note at this stage, if the username was empty, we give back nothing (this happens when wp-login.php is loaded as normal.
	 * 
	 * @param WP_User|string $inmUser	- the docs say the first parameter a string, WP actually gives a WP_User object (or null)
	 * @param string $insUsername
	 * @param string $insPassword
	 * @return WP_Error|WP_User|null	- WP_User when the login success AND the IP is authenticated. null when login not successful but IP is valid. WP_Error otherwise.
	 */
	public function checkUserAuthLogin( $inoUser, $insUsername, $insPassword ) {
		
		if ( empty( $insUsername ) ) {
			return $inoUser;
		}

		$fUserLoginSuccess = is_object( $inoUser ) && ( $inoUser instanceof WP_User );
		
		$aData = array( 'wp_username'	=> $insUsername );
		if ( $this->m_oLoginProcessor->isUserAuthenticated( $aData ) ) {
			if ( $fUserLoginSuccess ) {
				$oUser = $inoUser;
			}
			else {
				$oUser = null; 
			}
			return $oUser;
		}
		else {
			if ( $fUserLoginSuccess ) {
				// Create a new 2-factor auth pending entry
				$aNewAuthData = $this->m_oLoginProcessor->loginAuthAddPending( array( 'wp_username' => $inoUser->user_login ) );

				// Now send email with authentication link for user.
				if ( is_array( $aNewAuthData ) ) {
					$sAuthLink = $this->m_oLoginProcessor->getAuthenticationLink( self::getOption('secret_key'), $inoUser->user_login, $aNewAuthData['unique_id'] );
					$this->m_oLoginProcessor->sendLoginAuthenticationEmail( $inoUser->user_email, $aNewAuthData['ip'], $sAuthLink );
				}
			}
			$sErrorString = "Login is protected by 2-factor authentication. If your login details were correct, you would have received an email to verify this IP address.";
			return new WP_Error( 'loginauth', $sErrorString );
		}
	}
	
	/**
	 * Checks whether the current user that is logged-in is authenticated by IP address.
	 */
	public function checkCurrentUserAuth() {
		
		$fIsLoggedIn = is_user_logged_in();
		if ( $fIsLoggedIn ) {
			$oUser = wp_get_current_user();
			$aData = array( 'wp_username' => $oUser->user_login );
			$fIsAuthenticated = $this->m_oLoginProcessor->isUserAuthenticated( $aData );
			
			if ( !$fIsAuthenticated ) {
				wp_logout();
			}
		}
	}
	
	protected function initPluginOptions() {

		$this->m_aPluginOptions_Base = 	array(
			'section_title' => 'WordPress Firewall Options',
			'section_options' => array(
				array( 'enable_firewall',		'',	'N',	'checkbox',	'Enable Firewall',	'Completely turn on/off the firewall',	'Regardless of any settings anywhere else, this option will turn off the whole firewall, or enable your desired options below.' ),
				array( 'enable_firewall_log',	'',	'N',	'checkbox',	'Firewall Logging',	'Turn on a detailed Firewall Log',	'Will log every visit to the site and how the firewall processes it. Not recommended to leave on unless you want to debug something and check the firewall is working as you expect.' )
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
		$this->m_aPluginOptions_MiscOptionsSection = array(
			'section_title' => 'Miscellaneous Plugin Options',
			'section_options' => array(
				array(
					'delete_on_deactivate',
					'',
					'N',
					'checkbox',
					'Delete Plugin Settings',
					'Delete All Plugin Setting Upon Plugin Deactivation',
					'Careful: Removes all plugin options when you deactivite the plugin.'
				),
			),
		);
		
		$this->m_aPluginOptions_LoginProtectSection = array(
			'section_title' => 'Login Protection Options',
			'section_options' => array(
				array(
					'enable_login_protect',
					'',
					'N',
					'checkbox',
					'Turn On/Off Login Protect',
					'Turn On/Off All Login Protection Features',
					'Regardless of any settings below, this option will turn Off the login protection functionality, or enable your options below.'
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

		$this->m_aAllPluginOptions = array(
			&$this->m_aPluginOptions_Base,
			&$this->m_aPluginOptions_BlockSection,
			&$this->m_aPluginOptions_WhitelistSection,
			&$this->m_aPluginOptions_BlacklistSection,
			&$this->m_aPluginOptions_BlockTypesSection,
			&$this->m_aPluginOptions_MiscOptionsSection,
			&$this->m_aPluginOptions_LoginProtectSection,
			&$this->m_aPluginOptions_LoginProtectTwoFactorSection
		);

		return true;
		
	}//initPluginOptions
	
	public function onWpPluginsLoaded() {
		parent::onWpPluginsLoaded();
	}//onWpPluginsLoaded

	public function onWpInit() {
		parent::onWpInit();
		add_action( 'wp_enqueue_scripts', array( $this, 'onWpPrintStyles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'onWpEnqueueScripts' ) );
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

	/**
	 */
	protected function handlePluginUpgrade() {
		
		$sCurrentPluginVersion = self::getOption( 'current_plugin_version' );
		
		if ( $sCurrentPluginVersion !== self::$VERSION && current_user_can( 'manage_options' ) ) {
			
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
					
					if ( !class_exists('ICWP_DataProcessor') ) {
						require_once ( dirname(__FILE__).'/src/icwp-data-processor.php' );
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
			
			$this->clearFirewallProcessorCache();
			
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

			'fFirewallOn'		=> self::getOption( 'enable_firewall' ) == 'Y',
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
			'fBlockSchema'		=> self::getOption( 'block_leading_schema' )== 'Y'
		);

		$this->display( 'icwp_'.$this->m_sParentMenuIdSuffix.'_index', $aData );
	}

	public function onDisplayFirewallConfig() {
		
		//populates plugin options with existing configuration
		$this->readyAllPluginOptions();

		//Specify what set of options are available for this page
		$aAvailableOptions = array(
			&$this->m_aPluginOptions_Base,
			&$this->m_aPluginOptions_BlockSection,
			&$this->m_aPluginOptions_WhitelistSection,
			&$this->m_aPluginOptions_BlacklistSection,
			&$this->m_aPluginOptions_BlockTypesSection,
			&$this->m_aPluginOptions_MiscOptionsSection
		);

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
		$aAvailableOptions = array(
			&$this->m_aPluginOptions_LoginProtectSection,
			&$this->m_aPluginOptions_LoginProtectTwoFactorSection
		);

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
		
		if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
	}
	
	protected function handleSubmit_LoginProtect() {
		//Ensures we're actually getting this request from WP.
		wp_verify_nonce ( $this->getSubmenuId('login-protect' ) );

		$this->clearFirewallProcessorCache();
		
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
		self::updateOption( 'firewall_processor', false );
	}
	
	public function onWpPrintStyles() {
	}
	
	public function onWpEnqueueScripts() {
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
		
		$aExtras = array(
			'current_plugin_version',
			'feedback_admin_notice',
			'firewall_processor'
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
	
	public function addRawIpToBlacklist() {
		
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
						<h4 style="margin:10px 0 3px;">WordPress Firewall plugin has been updated- there may or may not be <a href="http://icwp.io/27" id="fromIcwp" title="WordPress Simple Firewall Plugin" target="_blank">important updates to know about</a>.</h4>
						<input type="submit" value="Show me and hide this notice." name="submit" class="button" style="float:left; margin-bottom:10px;">
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
