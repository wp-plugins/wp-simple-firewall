<?php

if ( !defined('WORPIT_DS') ) {
	define( 'WORPIT_DS', DIRECTORY_SEPARATOR );
}

if ( !function_exists( '_hlt_e' ) ) {
	function _hlt_e( $insStr ) {
		_e( $insStr, 'hlt-wordpress-bootstrap-css' );
	}
}
if ( !function_exists( '_hlt__' ) ) {
	function _hlt__( $insStr ) {
		return __( $insStr, 'hlt-wordpress-bootstrap-css' );
	}
}

if ( !class_exists('ICWP_WPSF_Base_Plugin') ):

class ICWP_WPSF_Base_Plugin {

	static public $VERSION;

	static public $PLUGIN_NAME;
	static public $PLUGIN_PATH;
	static public $PLUGIN_DIR;
	static public $PLUGIN_URL;
	static public $PLUGIN_BASENAME;
	static public $OPTION_PREFIX;

	const ParentTitle		= 'iControlWP Plugins';
	const ParentName		= 'Simple Firewall';
	const ParentPermissions	= 'manage_options';
	const ParentMenuId		= 'icwp';
	const VariablePrefix	= 'icwp';
	const BaseOptionPrefix	= 'icwp_';

	const ViewExt			= '.php';
	const ViewDir			= 'views';

	protected $m_aPluginMenu;

	protected $m_aAllPluginOptions;
	
	protected $m_sParentMenuIdSuffix;
	
	protected $m_fShowMarketing = '';
	
	static protected $m_fUpdateSuccessTracker;
	static protected $m_aFailedUpdateOptions;

	public function __construct() {

		add_action( 'plugins_loaded', array( &$this, 'onWpPluginsLoaded' ) );
		add_action( 'init', array( &$this, 'onWpInit' ), 0 );
		if ( is_admin() ) {
			add_action( 'admin_init', array( &$this, 'onWpAdminInit' ) );
			add_action( 'admin_notices', array( &$this, 'onWpAdminNotices' ) );
			add_action( 'admin_menu', array( &$this, 'onWpAdminMenu' ) );
			add_action( 'plugin_action_links', array( &$this, 'onWpPluginActionLinks' ), 10, 4 );
		}
		/**
		 * We make the assumption that all settings updates are successful until told otherwise
		 * by an actual failing update_option call.
		 */
		self::$m_fUpdateSuccessTracker = true;
		self::$m_aFailedUpdateOptions = array();

		$this->m_sParentMenuIdSuffix = 'base';
	}

	protected function getFullParentMenuId() {
		return self::ParentMenuId .'-'. $this->m_sParentMenuIdSuffix;
	}//getFullParentMenuId

	protected function display( $insView, $inaData = array() ) {
		$sFile = dirname(__FILE__).WORPIT_DS.'..'.WORPIT_DS.self::ViewDir.WORPIT_DS.$insView.self::ViewExt;

		if ( !is_file( $sFile ) ) {
			echo "View not found: ".$sFile;
			return false;
		}

		if ( count( $inaData ) > 0 ) {
			extract( $inaData, EXTR_PREFIX_ALL, self::VariablePrefix );
		}

		ob_start();
		include( $sFile );
		$sContents = ob_get_contents();
		ob_end_clean();

		echo $sContents;
		return true;
	}

	protected function getImageUrl( $insImage ) {
		return self::$PLUGIN_URL.'resources/images/'.$insImage;
	}
	protected function getCssUrl( $insCss ) {
		return self::$PLUGIN_URL.'resources/css/'.$insCss;
	}
	protected function getJsUrl( $insJs ) {
		return self::$PLUGIN_URL.'resources/js/'.$insJs;
	}

	protected function getSubmenuPageTitle( $insTitle ) {
		return self::ParentTitle.' - '.$insTitle;
	}
	protected function getSubmenuId( $insId ) {
		return $this->getFullParentMenuId().'-'.$insId;
	}

	public function onWpPluginsLoaded() {

		if ( is_admin() ) {
			//Handle plugin upgrades
			$this->handlePluginUpgrade();
		}

		if ( $this->isIcwpPluginAdminPage() ) {
			//Handle form submit
			$this->handlePluginFormSubmit();
		}
	}//onWpPluginsLoaded

	public function onWpInit() { }

	public function onWpAdminInit() {

		//Do Plugin-Specific Admin Work
		if ( $this->isIcwpPluginAdminPage() ) {

			//Links up CSS styles for the plugin itself (set the admin bootstrap CSS as a dependency also)
			$this->enqueuePluginAdminCss();
			
		}
		
		// Determine whether to show ads and marketing messages
		// Currently this is when the site uses the iControlWP service and is linked
		$this->isShowMarketing();
		
	}//onWpAdminInit
	
	public function onWpAdminMenu() {

		$sFullParentMenuId = $this->getFullParentMenuId();

		add_menu_page( self::ParentTitle, self::ParentName, self::ParentPermissions, $sFullParentMenuId, array( $this, 'onDisplayMainMenu' ), $this->getImageUrl( 'icontrolwp_16x16.png' ) );

		//Create and Add the submenu items
		$this->createPluginSubMenuItems();
		if ( !empty($this->m_aPluginMenu) ) {
			foreach ( $this->m_aPluginMenu as $sMenuTitle => $aMenu ) {
				list( $sMenuItemText, $sMenuItemId, $sMenuCallBack ) = $aMenu;
				add_submenu_page( $sFullParentMenuId, $sMenuTitle, $sMenuItemText, self::ParentPermissions, $sMenuItemId, array( &$this, $sMenuCallBack ) );
			}
		}

		$this->fixSubmenu();

	}//onWpAdminMenu

	protected function createPluginSubMenuItems(){
		/* Override to create array of sub-menu items
		 $this->m_aPluginMenu = array(
		 		//Menu Page Title => Menu Item name, page ID (slug), callback function onLoad.
		 		$this->getSubmenuPageTitle( 'Content by Country' ) => array( 'Content by Country', $this->getSubmenuId('main'), 'onDisplayCbcMain' ),
		 );
		*/
	}//createPluginSubMenuItems

	protected function fixSubmenu() {
		global $submenu;
		$sFullParentMenuId = $this->getFullParentMenuId();
		if ( isset( $submenu[$sFullParentMenuId] ) ) {
			$submenu[$sFullParentMenuId][0][0] = 'Dashboard';
		}
	}

	/**
	 * The callback function for the main admin menu index page
	 */
	public function onDisplayMainMenu() {
		$aData = array(
			'plugin_url'	=> self::$PLUGIN_URL,
			'fShowAds'		=> $this->isShowMarketing()
		);
		$this->display( 'icwp_'.$this->m_sParentMenuIdSuffix.'_index', $aData );
	}
	
	protected function isShowMarketing() {

		if ( $this->m_fShowMarketing == 'Y' ) {
			return true;
		}
		elseif ( $this->m_fShowMarketing == 'N' ) {
			return false;
		}
		
		$sServiceClassName = 'Worpit_Plugin';
		$this->m_fShowMarketing = 'Y';
		if ( class_exists( 'Worpit_Plugin' ) ) {
			if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
				$this->m_fShowMarketing = Worpit_Plugin::IsLinked() ? 'N' : 'Y';
			}
			elseif ( function_exists( 'get_option' )
					&& get_option( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
					&& get_option( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {
		
				$this->m_fShowMarketing = 'N';
			}
		}
		return $this->m_fShowMarketing === 'N' ? false : true;
	}

	/**
	 * The Action Links in the main plugins page. Defaults to link to the main Dashboard page
	 * 
	 * @param $inaLinks
	 * @param $insFile
	 */
	public function onWpPluginActionLinks( $inaLinks, $insFile ) {
		if ( $insFile == self::$PLUGIN_BASENAME ) {
			$sSettingsLink = '<a href="'.admin_url( "admin.php" ).'?page='.$this->getFullParentMenuId().'">' . __( 'Settings', 'worpit' ) . '</a>';
			array_unshift( $inaLinks, $sSettingsLink );
		}
		return $inaLinks;
	}

	/**
	 * Override this method to handle all the admin notices
	 */
	public function onWpAdminNotices() { }

	/**
	 * This is called from within onWpAdminInit. Use this solely to manage upgrades of the plugin
	 */
	protected function handlePluginUpgrade() { }

	protected function handlePluginFormSubmit() { }

	protected function enqueuePluginAdminCss() {
		$iRand = rand();
		wp_register_style( 'worpit_plugin_css'.$iRand, $this->getCssUrl('icontrolwp-plugin.css'), array('worpit_bootstrap_wpadmin_css_fixes'), self::$VERSION );
		wp_enqueue_style( 'worpit_plugin_css'.$iRand );
	}//enqueuePluginAdminCss
	
	/**
	 * Provides the basic HTML template for printing a WordPress Admin Notices
	 *
	 * @param $insNotice - The message to be displayed.
	 * @param $insMessageClass - either error or updated
	 * @param $infPrint - if true, will echo. false will return the string
	 * @return boolean|string
	 */
	protected function getAdminNotice( $insNotice = '', $insMessageClass = 'updated', $infPrint = false ) {

		$sFullNotice = '
			<div id="message" class="'.$insMessageClass.'">
				<style>
					#message form { margin: 0px; }
				</style>
				'.$insNotice.'
			</div>
		';

		if ( $infPrint ) {
			echo $sFullNotice;
			return true;
		} else {
			return $sFullNotice;
		}
	}//getAdminNotice

	protected function redirect( $insUrl, $innTimeout = 1 ) {
		echo '
			<script type="text/javascript">
				function redirect() {
					window.location = "'.$insUrl.'";
				}
				var oTimer = setTimeout( "redirect()", "'.($innTimeout * 1000).'" );
			</script>';
	}

	/**
	 * A little helper function that populates all the plugin options arrays with DB values
	 */
	protected function readyAllPluginOptions() {
		$this->initPluginOptions();
		$this->populateAllPluginOptions();
	}

	/**
	 * Override to create the plugin options array.
	 * 
	 * Returns false if nothing happens - i.e. not over-rided.
	 */
	protected function initPluginOptions() {
		return false;
	}

	/**
	 * Reads the current value for ALL plugin options from the WP options db.
	 * 
	 * Assumes the standard plugin options array structure. Over-ride to change.
	 * 
	 * NOT automatically executed on any hooks.
	 */
	protected function populateAllPluginOptions() {

		if ( empty($this->m_aAllPluginOptions) && !$this->initPluginOptions() ) {
			return false;
		}
		self::PopulatePluginOptions( $this->m_aAllPluginOptions );

	}//populateAllPluginOptions
	
	public static function PopulatePluginOptions( &$inaAllOptions ) {

		if ( empty($inaAllOptions) ) {
			return false;
		}
		foreach ( $inaAllOptions as &$aOptionsSection ) {
			self::PopulatePluginOptionsSection($aOptionsSection);
		}
	}
	
	public static function PopulatePluginOptionsSection( &$inaOptionsSection ) {

		if ( empty($inaOptionsSection) ) {
			return false;
		}
		foreach ( $inaOptionsSection['section_options'] as &$aOptionParams ) {
			
			list( $sOptionKey, $sOptionCurrent, $sOptionDefault, $sOptionType ) = $aOptionParams;
			$mCurrentOptionVal = self::getOption( $sOptionKey );
			if ( $sOptionType == 'ip_addresses' ) {
				if ( !empty( $mCurrentOptionVal ) ) {
					$mCurrentOptionVal = implode( "\n", self::ConvertIpListForDisplay( $mCurrentOptionVal ) );
				}
				else {
					$mCurrentOptionVal = '';
				}
			}
			else if ( $sOptionType == 'comma_separated_lists' ) {
				if ( !empty( $mCurrentOptionVal ) ) {
					$aNewValues = array();
					foreach( $mCurrentOptionVal as $sPage => $aParams ) {
						$aNewValues[] = $sPage.', '. implode( ", ", $aParams );
					}
					$mCurrentOptionVal = implode( "\n", $aNewValues );
				}
				else {
					$mCurrentOptionVal = '';
				}
			}
			$aOptionParams[1] = ($mCurrentOptionVal == '' )? $sOptionDefault : $mCurrentOptionVal;
		}
	}
	
	public static function ConvertIpListForDisplay( $inaIpList = array() ) {

		$aDisplay = array();
		if ( empty( $inaIpList ) || empty( $inaIpList['ips'] ) ) {
			return $aDisplay;
		}
		
		foreach( $inaIpList['ips'] as $sAddress ) {
			
			if ( strpos( $sAddress, '-' ) === false ) { //plain IP address
				$sDisplayText = long2ip( $sAddress );
			}
			else {
				list($nStart, $nEnd) = explode( '-', $sAddress );
				$sDisplayText = long2ip( $nStart ) .'-'. long2ip( $nEnd );
			}
			$sLabel = $inaIpList['meta'][ md5($sAddress) ];
			$sLabel = trim( $sLabel, '()' );
			$aDisplay[] = $sDisplayText . ' ('.$sLabel.')';
		}
		return $aDisplay;
	}

	/**
	 * $sAllOptionsInput is a comma separated list of all the input keys to be processed from the $_POST
	 */
	protected function updatePluginOptionsFromSubmit( $sAllOptionsInput ) {

		if ( empty($sAllOptionsInput) ) {
			return;
		}

		$aAllInputOptions = explode( ',', $sAllOptionsInput);
		foreach ( $aAllInputOptions as $sInputKey ) {
			$aInput = explode( ':', $sInputKey );
			list( $sOptionType, $sOptionKey ) = $aInput;
			
			$sOptionValue = $this->getAnswerFromPost( $sOptionKey );
			if ( is_null($sOptionValue) ) {
				
				if ( $sOptionType == 'text' || $sOptionType == 'email' ) { //if it was a text box, and it's null, don't update anything
					continue;
				} else if ( $sOptionType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				} else if ( $sOptionType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
			}
			else { //handle any pre-processing we need to.

				if ( $sOptionType == 'integer' ) {
					$sOptionValue = intval( $sOptionValue );
				}
				else if ( $sOptionType == 'ip_addresses' ) { //ip addresses are textareas, where each is separated by newline
					
					if ( !class_exists('ICWP_DataProcessor') ) {
						require_once ( dirname(__FILE__).'/icwp-data-processor.php' );
					}
					$oProcessor = new ICWP_DataProcessor();
					$sOptionValue = $oProcessor->ExtractIpAddresses( $sOptionValue );
				}
				else if ( $sOptionType == 'email' && function_exists( 'is_email' ) && !is_email( $sOptionValue ) ) {
					$sOptionValue = '';
				}
				else if ( $sOptionType == 'comma_separated_lists' ) {
					if ( !class_exists('ICWP_DataProcessor') ) {
						require_once ( dirname(__FILE__).'/icwp-data-processor.php' );
					}
					$oProcessor = new ICWP_DataProcessor();
					$sOptionValue = $oProcessor->ExtractCommaSeparatedList( $sOptionValue );
				}
			}
			$this->updateOption( $sOptionKey, $sOptionValue );
		}
		
		return true;
	}//updatePluginOptionsFromSubmit
	
	protected function collateAllFormInputsForAllOptions($aAllOptions, $sInputSeparator = ',') {

		if ( empty($aAllOptions) ) {
			return '';
		}
		$iCount = 0;
		foreach ( $aAllOptions as $aOptionsSection ) {
			
			if ( $iCount == 0 ) {
				$sCollated = $this->collateAllFormInputsForOptionsSection($aOptionsSection, $sInputSeparator);
			} else {
				$sCollated .= $sInputSeparator.$this->collateAllFormInputsForOptionsSection($aOptionsSection, $sInputSeparator);
			}
			$iCount++;
		}
		return $sCollated;
		
	}//collateAllFormInputsAllOptions

	/**
	 * Returns a comma seperated list of all the options in a given options section.
	 *
	 * @param array $aOptionsSection
	 */
	protected function collateAllFormInputsForOptionsSection( $aOptionsSection, $sInputSeparator = ',' ) {

		if ( empty($aOptionsSection) ) {
			return '';
		}
		$iCount = 0;
		foreach ( $aOptionsSection['section_options'] as $aOption ) {

			list($sKey, $fill1, $fill2, $sType) =  $aOption;
			
			if ( $iCount == 0 ) {
				$sCollated = $sType.':'.$sKey;
			} else {
				$sCollated .= $sInputSeparator.$sType.':'.$sKey;
			}
			$iCount++;
		}
		return $sCollated;
	}//collateAllFormInputsForOptionsSection

	protected function isIcwpPluginAdminPage() {

		$sSubPageNow = isset( $_GET['page'] )? $_GET['page']: '';
		if ( is_admin() && !empty($sSubPageNow) && (strpos( $sSubPageNow, $this->getFullParentMenuId() ) === 0 )) { //admin area, and the 'page' begins with 'worpit'
			return true;
		}

		return false;
	}//isIcwpPluginAdminPage
	
	protected function deleteAllPluginDbOptions() {

		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( empty($this->m_aAllPluginOptions) && !$this->initPluginOptions() ) {
			return;
		}
		
		foreach ( $this->m_aAllPluginOptions as $aOptionsSection ) {
			foreach ( $aOptionsSection['section_options'] as $aOptionParams ) {
				if ( isset( $aOptionParams[0] ) ) {
					$this->deleteOption($aOptionParams[0]);
				}
			}
		}
		
	}//deleteAllPluginDbOptions

	/**
	 * Cloudflare compatible.
	 * @return number
	 */
	public static function GetVisitorIpAddress() {
	
		$sIpAddress = empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["REMOTE_ADDR"] : $_SERVER["HTTP_X_FORWARDED_FOR"];
	
		if( strpos($sIpAddress, ',') !== false ) {
			$sIpAddress = explode(',', $sIpAddress);
			$sIpAddress = $sIpAddress[0];
		}
	
		return $sIpAddress;
	
	}//GetVisitorIpAddress

	protected function getAnswerFromPost( $insKey, $insPrefix = null ) {
		if ( is_null( $insPrefix ) ) {
			$insKey = self::$OPTION_PREFIX.$insKey;
		}
		return ( isset( $_POST[$insKey] )? $_POST[$insKey]: null );
	}

	static public function getOption( $insKey, $insAddPrefix = '' ) {
		return get_option( self::$OPTION_PREFIX.$insKey );
	}

	static public function addOption( $insKey, $insValue ) {
		return add_option( self::$OPTION_PREFIX.$insKey, $insValue );
	}

	static public function updateOption( $insKey, $insValue ) {
		if ( !is_object( $insValue ) && self::getOption( $insKey ) == $insValue ) {
			return true;
		}
		$fResult = update_option( self::$OPTION_PREFIX.$insKey, $insValue );
		if ( !$fResult ) {
			self::$m_fUpdateSuccessTracker = false;
			self::$m_aFailedUpdateOptions[] = self::$OPTION_PREFIX.$insKey;
		}
	}

	static public function deleteOption( $insKey ) {
		return delete_option( self::$OPTION_PREFIX.$insKey );
	}

	public function onWpActivatePlugin() { }
	public function onWpDeactivatePlugin() { }
	
	public function onWpUninstallPlugin() {
	
		//Do we have admin priviledges?
		if ( current_user_can( 'manage_options' ) ) {
			$this->deleteAllPluginDbOptions();
		}
	}
	
	/**
	 * Takes an array, an array key, and a default value. If key isn't set, sets it to default.
	 */
	protected function def( &$aSrc, $insKey, $insValue = '' ) {
		if ( !isset( $aSrc[$insKey] ) ) {
			$aSrc[$insKey] = $insValue;
		}
	}
	/**
	 * Takes an array, an array key and an element type. If value is empty, sets the html element
	 * string to empty string, otherwise forms a complete html element parameter.
	 *
	 * E.g. noEmptyElement( aSomeArray, sSomeArrayKey, "style" )
	 * will return String: style="aSomeArray[sSomeArrayKey]" or empty string.
	 */
	protected function noEmptyElement( &$inaArgs, $insAttrKey, $insElement = '' ) {
		$sAttrValue = $inaArgs[$insAttrKey];
		$insElement = ( $insElement == '' )? $insAttrKey : $insElement;
		$inaArgs[$insAttrKey] = ( empty($sAttrValue) ) ? '' : ' '.$insElement.'="'.$sAttrValue.'"';
	}

}//CLASS ICWP_WPSF_Base_Plugin

endif;

