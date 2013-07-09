<?php
/*
Plugin Name: WordPress Simple Firewall
Plugin URI: http://www.icontrolwp.com/
Description: A Simple WordPress Firewall
Version: 1.0.1
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
 *
 */

require_once( dirname(__FILE__).'/src/icwp-plugins-base.php' );

if ( !class_exists('ICWP_Wordpress_Simple_Firewall') ):

class ICWP_Wordpress_Simple_Firewall extends ICWP_WPSF_Base_Plugin {
	
	const InputPrefix				= 'icwp_wpsf_';
	const OptionPrefix				= 'icwp_wpsf_'; //ALL database options use this as the prefix.
	
	static public $VERSION			= '1.0.1'; //SHOULD BE UPDATED UPON EACH NEW RELEASE
	
	protected $m_aAllPluginOptions;
	protected $m_aPluginOptions_Base;
	protected $m_aPluginOptions_BlockTypesSection;
	protected $m_aPluginOptions_BlockSection;
	protected $m_aPluginOptions_WhitelistSection;
	protected $m_aPluginOptions_BlacklistSection;
	
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

		$this->m_sParentMenuIdSuffix = 'wpsf';
		
		$this->override();
		
		if ( self::getOption( 'enable_firewall' ) == 'Y' ) {
			require_once( dirname(__FILE__).'/src/icwp-firewall-processor.php' );
			$this->runFirewallProcess();
		}

	}//__construct
	
	protected function override() {
		if ( is_file( self::$PLUGIN_DIR . 'forceOff' ) ) {
			self::updateOption( 'enable_firewall', 'N' );
		}
		else if ( is_file( self::$PLUGIN_DIR . 'forceOn' ) ) {
			self::updateOption( 'enable_firewall', 'Y' );
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
		
		//Save the firewall log
		$aFullLog = self::getOption( 'firewall_log' );
		if ( !$aFullLog ) {
			$aFullLog = array();
		}
		return self::updateOption( 'firewall_log', array_merge( $inaNewLogData, $aFullLog ) );
	}
	
	/**
	 * Should be called from the constructor so as to ensure it is called as early as possible.
	 * 
	 * @param array $inaNewLogData
	 * @return boolean
	 */
	public function runFirewallProcess() {
		
		$oFP = self::getOption( 'firewall_processor', $oFP );
		if ( empty( $oFP ) ) {
			
			//collect up all the settings to pass to the processor
			$aSettingSlugs = array(
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
			$sBlockResponse = self::getOption( 'block_response' );
			$oFP = new ICWP_FirewallProcessor( $aBlockSettings, $aIpWhitelist, $aIpBlacklist, $sBlockResponse );
			
			self::updateOption( 'firewall_processor', $oFP );
			
		} else {
			$oFP->reset();
		}

		$fFirewallBlockUser = !$oFP->doFirewallCheck();

		if ( $fFirewallBlockUser ) {
			switch( $sBlockResponse ) {
				case 'redirect_home':
					$oFP->logWarning(
						sprintf( 'Firewall Block: Visitor was sent HOME: %s', home_url() )
					);
					break;
				case 'redirect_404':
					$oFP->logWarning(
						sprintf( 'Firewall Block: Visitor was sent 404: %s', home_url().'/404?testfirewall' )
					);
					break;
				case 'redirect_die':
					$oFP->logWarning(
						sprintf( 'Firewall Block: Visitor connection was killed with %s', 'die()' )
					);
					break;
			}
			$this->updateLogStore( $oFP->getLog() );
			
			if ( self::getOption( 'block_send_email' ) === 'Y' ) {
				$sEmail = get_option('admin_email');
				if ( is_email( $sEmail ) ) {
					$oFP->sendBlockEmail( $sEmail );
				}
			}
			$oFP->doFirewallBlock();
			
		}
		else {
			$this->updateLogStore( $oFP->getLog() );
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
				array( 'block_response',	'',	'none',	$this->m_aRedirectOptions,	'Block Response',	'Choose how the firewall responds when it blocks a request', '' ),
				array( 'block_send_email',	'',	'N',	'checkbox',	'Send Email Report',	'When a visitor is blocked it will send an email to the blog admin', 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host.' ),
			)
		);
		
		$this->m_aPluginOptions_WhitelistSection = array(
			'section_title' => 'Choose IP Addresses To Whitelist',
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'none',
					'ip_addresses',
					'Whitelist IP Addresses',
					'Choose IP Addresses that are never subjected to Firewall Rules',
					'Take a new line per address. Each IP Address must be valid and will be checked.'
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

		$this->m_aAllPluginOptions = array(
			&$this->m_aPluginOptions_Base,
			&$this->m_aPluginOptions_BlockSection,
			&$this->m_aPluginOptions_WhitelistSection,
			&$this->m_aPluginOptions_BlacklistSection,
			&$this->m_aPluginOptions_BlockTypesSection
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
		$this->filterIpsLists();
		
		//Multilingual support.
		load_plugin_textdomain( 'hlt-wordpress-bootstrap-css', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	
	protected function createPluginSubMenuItems(){
		$this->m_aPluginMenu = array(
			//Menu Page Title => Menu Item name, page ID (slug), callback function for this page - i.e. what to do/load.
			$this->getSubmenuPageTitle( 'Firewall' ) => array( 'Firewall', $this->getSubmenuId('firewall-config'), 'onDisplayFirewallConfig' ),
			$this->getSubmenuPageTitle( 'Log' ) => array( 'Log', $this->getSubmenuId('firewall-log'), 'onDisplayFirewallLog' )
		);
	}//createPluginSubMenuItems
	
	/**
	 * Handles the upgrade from version 1 to version 2 of Twitter Bootstrap as well as any other plugin upgrade
	 */
	protected function handlePluginUpgrade() {
		
		$sCurrentPluginVersion = self::getOption( 'current_plugin_version' );
		
		if ( $sCurrentPluginVersion !== self::$VERSION && current_user_can( 'manage_options' ) ) {

			//Do any upgrade handling here.
			
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
			&$this->m_aPluginOptions_BlockTypesSection
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
		
		$sAllFormInputOptions = $this->collateAllFormInputsForAllOptions( $aAvailableOptions );
		$aData = array(
			'plugin_url'		=> self::$PLUGIN_URL,
			'var_prefix'		=> self::OptionPrefix,
			'firewall_log'		=> self::getOption( 'firewall_log' ),
			'fShowAds'			=> $this->isShowMarketing(),
			'nonce_field'		=> $this->getSubmenuId('firewall').'_log',
			'form_action'		=> 'admin.php?page='.$this->getSubmenuId('firewall-log'),
		);

		$this->display( 'icwp_wpsf_firewall_log_index', $aData );
	}
	
	protected function handlePluginFormSubmit() {
		
		if ( !isset( $_POST['icwp_plugin_form_submit'] ) ) {
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

		self::updateOption( 'firewall_processor', false );
		
		if ( !isset($_POST[self::OptionPrefix.'all_options_input']) ) {
			return;
		}
		$this->updatePluginOptionsFromSubmit( $_POST[self::OptionPrefix.'all_options_input'] );
	}
	
	protected function handleSubmit_FirewallLog() {
		//Ensures we're actually getting this request from WP.
		check_admin_referer( $this->getSubmenuId('firewall').'_log' );
		
		// At the time of writing the page only has 1 form submission item - clear log
		self::updateOption( 'firewall_log', array() );
		wp_safe_redirect( admin_url( "admin.php?page=".$this->getSubmenuId('firewall-log') ) ); //means no admin message is displayed
		exit();
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
		
	}
	
	public function onWpDeactivatePlugin() {
		
		if ( $this->getOption('delete_on_deactivate') == 'Y' ) {
			$this->deleteAllPluginDbOptions();
		}
		
		$this->deleteOption( 'current_plugin_version' );
		$this->deleteOption( 'feedback_admin_notice' );
		
	}//onWpDeactivatePlugin
	
	public function onWpActivatePlugin() { }
	
	public function enqueueBootstrapAdminCss() {
		wp_register_style( 'worpit_bootstrap_wpadmin_css', $this->getCssUrl( 'bootstrap-wpadmin.css' ), false, self::$VERSION );
		wp_enqueue_style( 'worpit_bootstrap_wpadmin_css' );
		wp_register_style( 'worpit_bootstrap_wpadmin_css_fixes', $this->getCssUrl('bootstrap-wpadmin-fixes.css'),  array('worpit_bootstrap_wpadmin_css'), self::$VERSION );
		wp_enqueue_style( 'worpit_bootstrap_wpadmin_css_fixes' );
	}

	/**
	 * 
	 */
	protected function filterIpsLists() {

		$aWhitelistIps = self::getOption('ips_whitelist');
		if ( !is_array($aWhitelistIps) ) {
			$aWhitelistIps = array();
			self::updateOption( 'ips_whitelist', $aWhitelistIps );
		}
		$aWhitelistIpsFiltered = apply_filters( 'icwp_simple_firewall_whitelist_ips', $aWhitelistIps );
		if ( !is_array( $aWhitelistIpsFiltered ) ) {
			$aWhitelistIpsFiltered = array();
		}
		$aDiff = array_diff( $aWhitelistIpsFiltered, $aWhitelistIps );
		if ( !empty( $aDiff ) ) {
			self::updateOption( 'ips_whitelist', $this->verifyIpAddressList( $aWhitelistIpsFiltered ) );
		}

		$aBlacklistIps = self::getOption('ips_blacklist');
		if ( !is_array($aBlacklistIps) ) {
			$aBlacklistIps = array();
			self::updateOption( 'ips_blacklist', $aBlacklistIps );
		}
		$aBlacklistIpsFiltered = apply_filters( 'icwp_simple_firewall_blacklist_ips', $aBlacklistIps );
		if ( !is_array( $aBlacklistIpsFiltered ) ) {
			$aBlacklistIpsFiltered = array();
		}
		$aDiff = array_diff( $aBlacklistIpsFiltered, $aBlacklistIps );
		if ( !empty( $aDiff ) ) {
			self::updateOption( 'ips_blacklist', $this->verifyIpAddressList( $aBlacklistIpsFiltered ) );
		}
	}

	protected function verifyIpAddressList( $inaIpList ) {

		if ( function_exists('filter_var') && defined( FILTER_VALIDATE_IP )  ) {
			$fUseFilter = true;
		}
		else {
			$fUseFilter = false;
		}

		if ( !class_exists('ICWP_DataProcessor') ) {
			require_once ( dirname(__FILE__).'/src/icwp-data-processor.php' );
		}
		foreach( $inaIpList as $sKey => $sIpAddress ) {
			if ( !ICWP_DataProcessor::Verify_Ip( $sIpAddress, $fUseFilter ) ) {
				unset( $inaIpList[ $sKey ] );
			}
		}
		return $inaIpList;
	}
}

endif;

$oICWP_Wpsf = new ICWP_Wordpress_Simple_Firewall();
