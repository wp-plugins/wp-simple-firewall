<?php
/*
Plugin Name: WordPress Simple Firewall
Plugin URI: http://icwp.io/2f
Description: A Simple WordPress Firewall
Version: 1.4.2
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
require_once( dirname(__FILE__).'/src/icwp-data-processor.php' );
require_once( dirname(__FILE__).'/src/icwp-optionshandler-wpsf.php' );
require_once( dirname(__FILE__).'/src/icwp-optionshandler-firewall.php' );
require_once( dirname(__FILE__).'/src/icwp-optionshandler-loginprotect.php' );

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Base_Plugin {
	
	const InputPrefix				= 'icwp_wpsf_';
	const OptionPrefix				= 'icwp_wpsf_';	//ALL database options use this as the prefix.
	
	/**
	 * Should be updated each new release.
	 * @var string
	 */
	static public $VERSION			= '1.4.2';

	/**
	 * @var ICWP_OptionsHandler_Wpsf
	 */
	protected $m_oWpsfOptions;

	/**
	 * @var ICWP_OptionsHandler_Firewall
	 */
	protected $m_oFirewallOptions;
	
	/**
	 * @var ICWP_FirewallProcessor
	 */
	protected $m_oFirewallProcessor;

	/**
	 * @var ICWP_OptionsHandler_LoginProtect
	 */
	protected $m_oLoginProtectOptions;
	
	/**
	 * @var ICWP_LoginProcessor
	 */
	protected $m_oLoginProcessor;
	
	/**
	 * @var ICWP_LoggingProcessor
	 */
	protected $m_oLoggingProcessor;
	
	/**
	 * @var ICWP_EmailProcessor
	 */
	protected $m_oEmailProcessor;
	
	public function __construct() {
		parent::__construct();

		register_activation_hook( __FILE__, array( $this, 'onWpActivatePlugin' ) );
		register_deactivation_hook( __FILE__, array( $this, 'onWpDeactivatePlugin' ) );
	//	register_uninstall_hook( __FILE__, array( &$this, 'onWpUninstallPlugin' ) );
		
		self::$PLUGIN_HUMAN_NAME = "WordPress Simple Firewall";
		self::$PLUGIN_NAME	= basename(__FILE__);
		self::$PLUGIN_PATH	= plugin_basename( dirname(__FILE__) );
		self::$PLUGIN_FILE	= plugin_basename(__FILE__);
		self::$PLUGIN_DIR	= WP_PLUGIN_DIR.ICWP_DS.self::$PLUGIN_PATH.ICWP_DS;
		self::$PLUGIN_URL	= plugins_url( '/', __FILE__ ) ;
		self::$OPTION_PREFIX = self::OptionPrefix;
		
		$this->m_sParentMenuIdSuffix = 'wpsf';
		
		// loads the base plugin options from 1 db call
		$this->loadWpsfOptions();
		$this->m_fAutoPluginUpgrade = $this->m_oWpsfOptions->getOpt( 'enable_auto_plugin_upgrade' ) == 'Y';
		
		// checks for filesystem based firewall overrides
		$this->override();
		
	//	add_filter( 'user_has_cap', array( $this, 'disable_file_editing' ), 0, 3 );
		
		if ( $this->getIsMainFeatureEnabled( 'firewall' ) ) {
			
			$this->loadFirewallOptions();
			
			require_once( ABSPATH . 'wp-includes/pluggable.php' );
			if ( is_super_admin() && $this->getOption( 'whitelist_admins' ) != 'Y' ) {
				$this->runFirewallProcess();
			}
		}
		
		if ( $this->getIsMainFeatureEnabled( 'login_protect' ) ) {
			$this->runLoginProtect();
		}
		
		add_action( 'in_plugin_update_message-'.self::$PLUGIN_FILE, array( $this, 'onWpPluginUpdateMessage' ) );
		
	}//__construct
	
	public function removePluginConflicts() {
		if ( class_exists('AIO_WP_Security') && isset( $GLOBALS['aio_wp_security'] ) ) {
	        remove_action( 'init', array( $GLOBALS['aio_wp_security'], 'wp_security_plugin_init'), 0 );
		}
	}

	public function disable_file_editing( $allcaps, $cap, $args ) {
		
		$aEditCapabilities = array( 'edit_themes', 'edit_plugins', 'edit_files' );
		$sRequestedCapability = $args[0];
		
		if ( !in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			return $allcaps;
		}
		$allcaps[ $sRequestedCapability ] = false;
		return $allcaps;
	}
	
	protected function override() {
		if ( is_file( self::$PLUGIN_DIR . 'forceOff' ) ) {
			$this->setSharedOption( 'enable_firewall', 'N' );
			$this->setSharedOption( 'enable_login_protect', 'N' );
		}
		else if ( is_file( self::$PLUGIN_DIR . 'forceOn' ) ) {
			$this->setSharedOption( 'enable_firewall', 'Y' );
			$this->setSharedOption( 'enable_login_protect', 'Y' );
		}
		else {
			return true;
		}
		$this->resetFirewallProcessor();
		$this->resetLoginProcessor();
	}
	
	protected function genSecretKey() {
		$sKey = $this->m_oWpsfOptions->getOpt( 'secret_key' );
		if ( empty( $sKey ) ) {
			$sKey = md5( mt_rand() );
			$this->m_oWpsfOptions->setOpt( 'secret_key', $sKey );
		}
		return $sKey;
	}
	
	/**
	 * @return boolean
	 */
	public function getIsMainFeatureEnabled( $insFeature ) {
		
		if ( is_file( self::$PLUGIN_DIR . 'forceOff' ) ) {
			return false;
		}
		else if ( is_file( self::$PLUGIN_DIR . 'forceOn' ) ) {
			return true;
		}
		
		switch ( $insFeature ) {
			case 'firewall':
				$fEnabled = $this->m_oWpsfOptions->getOpt( 'enable_firewall' ) == 'Y';
				break;
			case 'login_protect':
				$fEnabled = $this->m_oWpsfOptions->getOpt( 'enable_login_protect' ) == 'Y';
				break;
			default:
				$fEnabled = false;
		}
		return $fEnabled;
	}
	
	/**
	 * This is necessary because we store these values in several places and we need to always keep it in sync.
	 * 
	 * @param string $sFeature
	 * @param boolean $infEnabled
	 * @return boolean
	 */
	public function setSharedOption( $insOption, $inmValue ) {
		
		switch ( $insOption ) {
			case 'enable_firewall':
				$this->loadFirewallOptions();
				$this->m_oFirewallOptions->setOpt( $insOption, $inmValue );
				break;
			case 'enable_login_protect':
				$this->loadLoginProtectOptions();
				$this->m_oLoginProtectOptions->setOpt( $insOption, $inmValue );
				break;
		}
		
		$this->m_oWpsfOptions->setOpt( $insOption, $inmValue );
		self::updateOption( $insOption, $inmValue );
	}
	
	/**
	 * Updates the current log data with new data.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	protected function updateLogStore() {

		if ( isset( $this->m_oFirewallProcessor ) && is_object( $this->m_oFirewallProcessor ) && $this->getIsMainFeatureEnabled( 'firewall' ) ) {
			$aLogData = $this->m_oFirewallProcessor->flushLogData();
			if ( !is_null( $aLogData ) && $aLogData ) {
				$this->loadLoggingProcessor();
				$this->m_oLoggingProcessor->writeLog( $aLogData );
			}
		}

		if ( isset( $this->m_oLoginProcessor ) && is_object( $this->m_oLoginProcessor ) && $this->getIsMainFeatureEnabled( 'login_protect' ) ) {
			$aLogData = $this->m_oLoginProcessor->flushLogData();
			if ( !is_null( $aLogData ) && $aLogData ) {
				$this->loadLoggingProcessor();
				$this->m_oLoggingProcessor->writeLog( $aLogData );
			}
		}
	}
	
	protected function loadAllOptions() {
		$this->loadWpsfOptions();
		$this->loadFirewallOptions();
		$this->loadLoginProtectOptions();
	}
	
	protected function loadWpsfOptions() {
		if ( !isset( $this->m_oWpsfOptions ) ) {
			$this->m_oWpsfOptions = new ICWP_OptionsHandler_Wpsf( self::OptionPrefix, 'plugin_options', self::$VERSION );
		}
	}
	
	protected function loadFirewallOptions() {
		if ( !isset( $this->m_oFirewallOptions ) ) {
			$this->m_oFirewallOptions = new ICWP_OptionsHandler_Firewall( self::OptionPrefix, 'firewall_options', self::$VERSION );
		}
	}
	
	protected function loadLoginProtectOptions() {
		if ( !isset( $this->m_oLoginProtectOptions ) ) {
			$this->m_oLoginProtectOptions = new ICWP_OptionsHandler_LoginProtect( self::OptionPrefix, 'loginprotect_options', self::$VERSION );
		}
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
				$this->loadFirewallOptions();
				
				// collect up all the settings to pass to the processor
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
				foreach( $aSettingSlugs as $sSettingKey ) {
					$aBlockSettings[ $sSettingKey ] = $this->m_oFirewallOptions->getOpt( $sSettingKey ) == 'Y';
					
				}

				$aIpWhitelist = $this->m_oFirewallOptions->getOpt( 'ips_whitelist' );
				if ( $aIpWhitelist === false ) {
					$aIpWhitelist = '';
					$this->m_oFirewallOptions->setOpt( 'ips_whitelist', $aIpWhitelist );
				}
				
				$aIpBlacklist = $this->m_oFirewallOptions->getOpt( 'ips_blacklist' );
				if ( $aIpBlacklist === false ) {
					$aIpBlacklist = '';
					$this->m_oFirewallOptions->setOpt( 'ips_blacklist', $aIpBlacklist );
				}
				
				$aPageWhitelist = $this->m_oFirewallOptions->getOpt( 'page_params_whitelist' );
				if ( $aPageWhitelist === false ) {
					$aPageWhitelist = '';
					$this->m_oFirewallOptions->setOpt( 'page_params_whitelist', $aPageWhitelist );
				}
				
				$sBlockResponse = $this->m_oFirewallOptions->getOpt( 'block_response' );
				if ( empty( $sBlockResponse ) ) {
					$sBlockResponse = 'redirect_die_message';
					$aIpWhitelist = $this->m_oFirewallOptions->setOpt( 'block_response', $sBlockResponse );
				}
				
				$this->m_oFirewallProcessor = new ICWP_FirewallProcessor( $aBlockSettings, $aIpWhitelist, $aIpBlacklist, $aPageWhitelist, $sBlockResponse );
				$this->m_oFirewallProcessor->setLogging( $this->m_oFirewallOptions->getOpt( 'enable_firewall_log' ) == 'Y' );
			}
		}
		else if ( $infReset ) {
			$this->m_oFirewallProcessor->reset();
		}
		
		if ( $this->m_oFirewallOptions->getOpt( 'block_send_email' ) === 'Y' ) {
			$this->loadEmailProcessor();
			$this->m_oFirewallProcessor->setEmailHandler( $this->m_oEmailProcessor );
		}
	}
	
	/**
	 * Loads the Login Protect Processor for use throughout.  Will draw upon the db-cached object where
	 * appropriate.
	 * 
	 * @param boolean $infReset
	 */
	protected function loadLoginProcessor( $infReset = false ) {
				
		$this->loadLoginProtectOptions();
		
		require_once( dirname(__FILE__).'/src/icwp-login-processor.php' );
		
		if ( empty( $this->m_oLoginProcessor ) ) {
			$this->m_oLoginProcessor = self::getOption( 'login_processor' );

			if ( is_object( $this->m_oLoginProcessor ) && ( $this->m_oLoginProcessor instanceof ICWP_LoginProcessor ) ) {
				$this->m_oLoginProcessor->reset();
			}
			else {
				// collect up all the settings to pass to the processor
				$this->m_oLoginProcessor = new ICWP_LoginProcessor( $this->genSecretKey() );
				
				$this->m_oLoginProcessor->setLogging( $this->m_oLoginProtectOptions->getOpt( 'enable_login_protect_log' ) );
				$this->m_oLoginProcessor->setLoginCooldownInterval( $this->m_oLoginProtectOptions->getOpt( 'login_limit_interval' ) );
				$this->m_oLoginProcessor->setTwoFactorByPassOnFail( $this->m_oLoginProtectOptions->getOpt( 'enable_two_factor_bypass_on_email_fail' ) == 'Y' );
			}
		}
		else if ( $infReset ) {
			$this->m_oLoginProcessor->reset();
		}
		
		$this->loadEmailProcessor();
		$this->m_oLoginProcessor->setEmailHandler( $this->m_oEmailProcessor );
	}
	
	/**
	 * Loads the Logging Processor for use throughout.  Will draw upon the db-cached object where
	 * appropriate.
	 * 
	 * @param boolean $infReset
	 */
	protected function loadLoggingProcessor( $infReset = false ) {
		
		require_once( dirname(__FILE__).'/src/icwp-logging-processor.php' );

		if ( empty( $this->m_oLoggingProcessor ) ) {
			$this->m_oLoggingProcessor = self::getOption( 'logging_processor' );
			
			if ( is_object( $this->m_oLoggingProcessor ) && ( $this->m_oLoggingProcessor instanceof ICWP_LoggingProcessor ) ) {
				$this->m_oLoggingProcessor->reset();
			}
			else {
				// collect up all the settings to pass to the processor
				$this->m_oLoggingProcessor = new ICWP_LoggingProcessor();
			}
		}
		else if ( $infReset ) {
			$this->m_oLoggingProcessor->reset();
		}
		$this->loadEmailProcessor();
		$this->m_oLoggingProcessor->setEmailHandler( $this->m_oEmailProcessor );
	}
	
	/**
	 * Loads the Email Processor for use throughout.  Will draw upon the db-cached object where
	 * appropriate.
	 * 
	 * @param boolean $infReset
	 */
	protected function loadEmailProcessor( $infReset = false ) {
		
		require_once( dirname(__FILE__).'/src/icwp-email-processor.php' );

		if ( empty( $this->m_oEmailProcessor ) ) {
			$this->m_oEmailProcessor = self::getOption( 'email_processor' );
			
			if ( is_object( $this->m_oEmailProcessor ) && ( $this->m_oEmailProcessor instanceof ICWP_EmailProcessor ) ) {
				$this->m_oEmailProcessor->reset();
			}
			else {
				// collect up all the settings to pass to the processor
				$this->m_oEmailProcessor = new ICWP_EmailProcessor();
				
				require_once( ABSPATH . 'wp-includes/pluggable.php' );
				$sSiteName = ( function_exists('get_bloginfo') )? get_bloginfo('name') : '';
				
				$sEmail = $this->m_oWpsfOptions->getOpt( 'block_send_email_address');
				if ( empty( $sEmail ) || !is_email( $sEmail ) ) {
					$sEmail = get_option('admin_email');
				}
				if ( is_email( $sEmail ) ) {
					$this->m_oEmailProcessor->setRecipientAddress( $sEmail );
				}

				$sLimit = $this->m_oWpsfOptions->getOpt( 'send_email_throttle_limit' );
				if ( !is_numeric( $sLimit ) ) {
					$sLimit = 0;
				}
				$this->m_oEmailProcessor->setThrottleLimit( $sLimit );
				$this->m_oEmailProcessor->setSiteName( get_bloginfo('name') );
			}
		}
		else if ( $infReset ) {
			$this->m_oEmailProcessor->reset();
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
				case 'redirect_die':
					$this->m_oFirewallProcessor->logWarning(
						sprintf( 'Firewall Block: Visitor connection was killed with %s', 'die()' )
					);
					break;
				case 'redirect_die_message':
					$this->m_oFirewallProcessor->logWarning(
						sprintf( 'Firewall Block: Visitor connection was killed with %s and message', 'wp_die()' )
					);
					break;
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
			}
			
			if ( $this->m_oFirewallOptions->getOpt( 'block_send_email' ) === 'Y' ) {
				$this->m_oFirewallProcessor->sendBlockEmail();
			}
			
			$this->updateLogStore();
			self::updateOption( 'firewall_processor', $this->m_oFirewallProcessor );
			self::updateOption( 'email_processor', $this->m_oEmailProcessor );
			$this->m_oFirewallProcessor->doFirewallBlock();
		}
	}
	
	/**
	 * Handles the running of all login protection processes.
	 */
	public function runLoginProtect() {
		$this->loadLoginProcessor();
		$this->m_oLoginProcessor->run( $this->m_oLoginProtectOptions );
	}

	/**
	 * Make sure and cache the processors after all is said and done.
	 */
	public function saveProcessors_Action() {
		
		$this->updateLogStore();
		
		if ( isset( $this->m_oWpsfOptions ) ) {
			$this->m_oWpsfOptions->savePluginOptions( false );
		}
		if ( isset( $this->m_oFirewallOptions ) ) {
			$this->m_oFirewallOptions->savePluginOptions( false );
		}
		if ( isset( $this->m_oLoginProtectOptions ) ) {
			$this->m_oLoginProtectOptions->savePluginOptions( false );
		}
		if ( isset( $this->m_oFirewallProcessor ) ) {
			self::updateOption( 'firewall_processor', $this->m_oFirewallProcessor );
		}
		if ( isset( $this->m_oLoginProcessor ) ) {
			self::updateOption( 'login_processor', $this->m_oLoginProcessor );
		}
		if ( isset( $this->m_oLoggingProcessor ) ) {
			self::updateOption( 'logging_processor', $this->m_oLoggingProcessor );
		}
		if ( isset( $this->m_oEmailProcessor ) ) {
			self::updateOption( 'email_processor', $this->m_oEmailProcessor );
		}
	}
	
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
		parent::handlePluginUpgrade();
		
		$sCurrentPluginVersion = $this->m_oWpsfOptions->getOpt( 'current_plugin_version' );
		
		if ( $sCurrentPluginVersion !== self::$VERSION && current_user_can( 'manage_options' ) ) {
			
			$this->loadLoggingProcessor();
			$this->m_oLoggingProcessor->handleInstallUpgrade( $sCurrentPluginVersion );
			
			// handles migration to new dedicated options system
			$this->loadAllOptions();

			// clears all the processor caches
			$this->clearCaches();
			
			// delete all the old stuff
			$aOldOptionKeys = array (
				'current_plugin_version',
				'feedback_admin_notice',
				'secret_key',
				'block_send_email',
				'block_send_email_address',
				'send_email_throttle_limit',
				'delete_on_deactivate',
				'include_cookie_checks',
				'block_wplogin_access',
				'block_dir_traversal',
				'block_sql_queries',
				'block_wordpress_terms',
				'block_field_truncation',
				'block_exe_file_uploads',
				'block_leading_schema',
				'ips_whitelist',
				'ips_blacklist',
				'page_params_whitelist',
				'block_response',
				'enable_firewall_log',
				'enable_two_factor_auth_by_ip',
				'enable_two_factor_bypass_on_email_fail',
				'login_limit_interval',
				'enable_login_gasp_check',
				'enable_login_protect_log'
			);
			foreach( $aOldOptionKeys as $sOptionKey ) {
 				self::deleteOption( $sOptionKey );
			}
			
			// delete the old option.
			if ( version_compare( $sCurrentPluginVersion, '1.1.1', '<' ) ) {
				$this->deleteOption( 'firewall_log' );
			}
			
			// introduced IP ranges in version 1.1.0 so anyone that is less than this must convert their IPs.
			// as of v1.3.3+ no longer support this conversion.
			if ( version_compare( $sCurrentPluginVersion, '1.1.0', '<' ) ) {
				/*
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
				*/
			}
			
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
		// Do we have admin priviledges?
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		parent::onWpAdminNotices();

		$this->adminNoticeVersionUpgrade();
		$this->adminNoticeOptionsUpdated();
	}
	
	public function onDisplayMainMenu() {

		$aAvailableOptions = $this->m_oWpsfOptions->getOptions();
		$sAllFormInputOptions = $this->m_oWpsfOptions->collateAllFormInputsForAllOptions();
		
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'aAllOptions'		=> $aAvailableOptions,
			'fShowAds'			=> $this->isShowMarketing(),
			'all_options_input'	=> $sAllFormInputOptions,
			'nonce_field'		=> $this->getSubmenuId('wpsf-dashboard'),
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId()
		);

		$aData['aMainOptions'] = $this->m_oWpsfOptions->getOptionValues();
		if ( $this->getIsMainFeatureEnabled('firewall') ) {
			$aData['aFirewallOptions'] = $this->m_oFirewallOptions->getOptionValues();
		}
		if ( $this->getIsMainFeatureEnabled('login_protect') ) {
			$aData['aLoginProtectOptions'] = $this->m_oLoginProtectOptions->getOptionValues();
		}

		$this->display( 'icwp_'.$this->m_sParentMenuIdSuffix.'_index', $aData );
	}

	public function onDisplayFirewallConfig() {

		$this->loadFirewallOptions();
		$aAvailableOptions = $this->m_oFirewallOptions->getOptions();
		$sAllFormInputOptions = $this->m_oFirewallOptions->collateAllFormInputsForAllOptions();

		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'fShowAds'			=> $this->isShowMarketing(),
			'aAllOptions'		=> $aAvailableOptions,
			'all_options_input'	=> $sAllFormInputOptions,
			'nonce_field'		=> $this->getSubmenuId('firewall-config'),
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId('firewall-config')
		);
		$this->display( 'icwp_wpsf_firewall_config_index', $aData );
	}
	
	public function onDisplayFirewallLog() {

		$this->loadFirewallOptions();
		$aIpWhitelist = $this->m_oFirewallOptions->getOpt( 'ips_whitelist' );
		$aIpBlacklist = $this->m_oFirewallOptions->getOpt( 'ips_blacklist' );
		
		$this->loadLoggingProcessor();

		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'firewall_log'		=> $this->m_oLoggingProcessor->getLogs( true ),
			'ip_whitelist'		=> isset( $aIpWhitelist['ips'] )? $aIpWhitelist['ips'] : array(),
			'ip_blacklist'		=> isset( $aIpBlacklist['ips'] )? $aIpBlacklist['ips'] : array(),
			'fShowAds'			=> $this->isShowMarketing(),
			'nonce_field'		=> $this->getSubmenuId('firewall-log'),
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId('firewall-log'),
		);

		$this->display( 'icwp_wpsf_firewall_log_index', $aData );
	}
	
	public function onDisplayLoginProtect() {
		
		$this->loadLoginProtectOptions();
		$aAvailableOptions = $this->m_oLoginProtectOptions->getOptions();
		$sAllFormInputOptions = $this->m_oLoginProtectOptions->collateAllFormInputsForAllOptions();

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
				case $this->getSubmenuId():
					$this->handleSubmit_Dashboard();
					break;
				case $this->getSubmenuId( 'firewall-config' ):
					$this->handleSubmit_FirewallConfig();
					break;
				case $this->getSubmenuId( 'firewall-log' ):
					$this->handleSubmit_FirewallLog();
					break;
				case $this->getSubmenuId( 'login-protect' ):
					$this->handleSubmit_LoginProtect();
					break;
				default:
					return;
					break;
			}
		}
		
		if ( !self::$m_fUpdateSuccessTracker ) {
			$this->m_oWpsfOptions->setOpt( 'feedback_admin_notice', 'Updating Settings <strong>Failed</strong>.' );
		}
		else {
			$this->m_oWpsfOptions->setOpt( 'feedback_admin_notice', 'Updating Settings <strong>Succeeded</strong>.' );
		}
	}
	
	protected function handleSubmit_Dashboard() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('wpsf-dashboard') );

		if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}

		$this->loadWpsfOptions();
		$this->m_oWpsfOptions->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
		
		$this->setSharedOption( 'enable_firewall', $this->m_oWpsfOptions->getOpt( 'enable_firewall' ) );
		$this->setSharedOption( 'enable_login_protect', $this->m_oWpsfOptions->getOpt( 'enable_login_protect' ) );
		$this->clearCaches();
	}
	
	protected function handleSubmit_FirewallConfig() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('firewall-config' ) );

		if ( isset($_POST[ 'import-wpf2-submit' ] ) ) {
			$this->importFromFirewall2Plugin();
		}
		else if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}
		else {
			$this->loadFirewallOptions();
			$this->m_oFirewallOptions->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
		}

		$this->setSharedOption( 'enable_firewall', $this->m_oFirewallOptions->getOpt( 'enable_firewall' ) );
		$this->resetFirewallProcessor();
	}
	
	protected function handleSubmit_LoginProtect() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('login-protect' ) );
		
		if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->loadLoginProtectOptions();
		$this->m_oLoginProtectOptions->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
		$this->setSharedOption( 'enable_login_protect', $this->m_oLoginProtectOptions->getOpt( 'enable_login_protect' ) );
		$this->resetLoginProcessor();
	}
	
	protected function handleSubmit_FirewallLog() {

		// Ensures we're actually getting this request from a valid WP submission.
		if ( !isset( $_REQUEST['_wpnonce'] ) || !wp_verify_nonce( $_REQUEST['_wpnonce'], $this->getSubmenuId('firewall-log') ) ) {
			wp_die();
		}
		
		// At the time of writing the page only has 1 form submission item - clear log
		if ( isset( $_POST['clear_log_submit'] ) ) {
			$this->loadLoggingProcessor();
			$this->m_oLoggingProcessor->emptyTable();
		}
		else if ( isset( $_GET['blackip'] ) ) {
			$this->addRawIpsToFirewallList( 'ips_blacklist', array( $_GET['blackip'] ) );
		}
		else if ( isset( $_GET['unblackip'] ) ) {
			$this->removeRawIpsFromFirewallList( 'ips_blacklist', array( $_GET['unblackip'] ) );
		}
		else if ( isset( $_GET['whiteip'] ) ) {
			$this->addRawIpsToFirewallList( 'ips_whitelist', array( $_GET['whiteip'] ) );
		}
		else if ( isset( $_GET['unwhiteip'] ) ) {
			$this->removeRawIpsFromFirewallList( 'ips_whitelist', array( $_GET['unwhiteip'] ) );
		}
		wp_safe_redirect( admin_url( "admin.php?page=".$this->getSubmenuId('firewall-log') ) ); //means no admin message is displayed
		exit();
	}
	
	public function clearCaches() {
		$this->resetFirewallProcessor();
		$this->resetLoginProcessor();
		$this->resetLoggingProcessor();
	}
	
	protected function resetEmailProcessor() {
		$this->m_oEmailProcessor = false;
		self::deleteOption( 'email_processor' );
		$this->loadEmailProcessor();
	}
	
	protected function resetFirewallProcessor() {
		$this->resetEmailProcessor();
		$this->m_oFirewallProcessor = false;
		self::deleteOption( 'firewall_processor' );
		$this->loadFirewallProcessor();
	}
	
	protected function resetLoginProcessor() {
		$this->m_oLoginProcessor = false;
		self::deleteOption( 'login_processor' );
		$this->loadLoginProcessor();
	}
	
	protected function resetLoggingProcessor() {
		$this->m_oLoggingProcessor = false;
		self::deleteOption( 'logging_processor' );
		$this->loadLoggingProcessor();
	}
	
	protected function importFromFirewall2Plugin() {
		$this->loadAllOptions();
		require_once( dirname(__FILE__).'/src/icwp-import-wpf2-processor.php' );
		$oImportProcessor = new ICWP_ImportWpf2Processor( $this->m_oWpsfOptions, $this->m_oFirewallOptions );
		$oImportProcessor->runImport();
	}
	
	public function onWpPluginActionLinks( $inaLinks, $insFile ) {
		if ( $insFile == plugin_basename( __FILE__ ) ) {
			$sSettingsLink = '<a href="'.admin_url( "admin.php" ).'?page='.$this->getSubmenuId('firewall-config').'">' . 'Firewall' . '</a>';
			array_unshift( $inaLinks, $sSettingsLink );
		}
		return $inaLinks;
	}
	
	public function onWpPluginsLoaded() {
		parent::onWpPluginsLoaded();
		$this->removePluginConflicts(); // removes conflicts with other plugins
	}
	
	public function onWpShutdown() {
		parent::onWpShutdown();
		$this->saveProcessors_Action();
	}
	
	protected function deleteAllPluginDbOptions() {
		
		parent::deleteAllPluginDbOptions();
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$this->loadLoggingProcessor();
		$this->m_oLoggingProcessor->dropTable();

		$this->loadLoginProcessor();
		$this->m_oLoginProcessor->dropTable();
		
		$this->loadFirewallOptions();
		$this->m_oFirewallOptions->deletePluginOptions();
		$this->loadLoginProtectOptions();
		$this->m_oLoginProtectOptions->deletePluginOptions();
		$this->loadWpsfOptions();
		$this->m_oWpsfOptions->deletePluginOptions();
		
		remove_action( 'shutdown', array( $this, 'saveProcessors_Action' ) );
	}
	
	public function onWpPluginUpdateMessage() {
		echo '<div style="color: #dd3333;">'
			."Upgrade Now To Keep Your Firewall Up-To-Date With The Latest Features."
			. '</div>';
	}
	
	public function onWpDeactivatePlugin() {
		if ( $this->m_oWpsfOptions->getOpt( 'delete_on_deactivate' ) == 'Y' ) {
			$this->deleteAllPluginDbOptions();
		}
	}
	
	public function onWpActivatePlugin() { }
	
	public function enqueueBootstrapAdminCss() {
		wp_register_style( 'worpit_bootstrap_wpadmin_css', $this->getCssUrl( 'bootstrap-wpadmin.css' ), false, self::$VERSION );
		wp_enqueue_style( 'worpit_bootstrap_wpadmin_css' );
		wp_register_style( 'worpit_bootstrap_wpadmin_css_fixes', $this->getCssUrl('bootstrap-wpadmin-fixes.css'),  array('worpit_bootstrap_wpadmin_css'), self::$VERSION );
		wp_enqueue_style( 'worpit_bootstrap_wpadmin_css_fixes' );
	}

	public function addRawIpsToFirewallList( $insListName, $inaNewIps ) {

		$this->loadFirewallOptions();
		
		$aIplist = $this->m_oFirewallOptions->getOpt( $insListName );
		if ( empty( $aIplist ) ) {
			$aIplist = array();
		}
		$aNewList = array();
		foreach( $inaNewIps as $sAddress ) {
			$aNewList[ $sAddress ] = '';
		}
		$aIplist = $this->m_oFirewallOptions->setOpt( $insListName, ICWP_DataProcessor::Add_New_Raw_Ips( $aIplist, $aNewList ) );
		$this->resetFirewallProcessor();
	}

	public function removeRawIpsFromFirewallList( $insListName, $inaRemoveIps ) {

		$this->loadFirewallOptions();
		
		$aIplist = $this->m_oFirewallOptions->getOpt( $insListName );
		if ( empty( $aIplist ) || empty( $inaRemoveIps ) ) {
			return;
		}
		$aIplist = $this->m_oFirewallOptions->setOpt( $insListName, ICWP_DataProcessor::Remove_Raw_Ips( $aIplist, $inaRemoveIps ) );
		$this->resetFirewallProcessor();
	}
	
	/**
	 */
	protected function filterIpLists() {
		
		$nNewAddedCount = 0;
		$mResult = $this->processIpFilter( 'ips_whitelist', 'icwp_simple_firewall_whitelist_ips', $nNewAddedCount );
		if ( $mResult !== false && $nNewAddedCount > 0 ) {
			$this->m_oFirewallOptions->setOpt( 'ips_whitelist', $mResult );
			$this->resetFirewallProcessor();
		}
		
		$nNewAddedCount = 0;
		$mResult = $this->processIpFilter( 'ips_blacklist', 'icwp_simple_firewall_blacklist_ips', $nNewAddedCount );
		if ( $mResult !== false && $nNewAddedCount > 0 ) {
			$this->m_oFirewallOptions->setOpt( 'ips_blacklist', $mResult );
			$this->resetFirewallProcessor();
		}
	}

	/**
	 * @param string $insExistingListKey
	 * @param string $insFilterName
	 * @param integer $outnNewAdded
	 * @return array|false
	 */
	protected function processIpFilter( $insExistingListKey, $insFilterName, &$outnNewAdded = 0 ) {
		
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
			
			// Get the existing list
			$this->loadFirewallOptions();
			$aExistingIpList = $this->m_oFirewallOptions->getOpt( $insExistingListKey );
			if ( !is_array( $aExistingIpList ) ) {
				$aExistingIpList = array();
			}
			return ICWP_DataProcessor::Add_New_Raw_Ips( $aExistingIpList, $aNewIps, $outnNewAdded );
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
			
		}
		
	private function adminNoticeOptionsUpdated() {
			
		$sAdminFeedbackNotice = $this->m_oWpsfOptions->getOpt( 'feedback_admin_notice' );
		if ( !empty( $sAdminFeedbackNotice ) ) {
			$sNotice = '<p>'.$sAdminFeedbackNotice.'</p>';
			$this->getAdminNotice( $sNotice, 'updated', true );
			$this->m_oWpsfOptions->setOpt( 'feedback_admin_notice', '' );
		}
		
	}//adminNoticeOptionsUpdated
}

endif;

$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall();
