<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * Version: 2013-11-15-V1
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

if ( !class_exists('ICWP_OptionsHandler_Base_V2') ):

class ICWP_OptionsHandler_Base_V2 {
	
	/**
	 * @var string
	 */
	const CollateSeparator = '--SEP--';
	/**
	 * @var string
	 */
	const PluginVersionKey = 'current_plugin_version';
	
	/**
	 * @var boolean
	 */
	protected $m_fNeedSave;
	
	/**
	 * @var boolean
	 */
	protected $m_fFullInit;
	
	/**
	 * @var string
	 */
	protected $m_sOptionPrefix;
	
	/**
	 * @var string
	 */
	protected $m_sVersion;

	/**
	 * @var array
	 */
	protected $m_aOptions;
	
	/**
	 * @var array
	 */
	protected $m_aDirectSaveOptions;
	
	/**
	 * @var boolean
	 */
	protected $m_fIsMultisite;
	
	/**
	 * This is used primarily for the options deletion/cleanup.  We store the names
	 * of options here that are not modified directly by the user/UI so that we can
	 * cleanup later on.
	 * 
	 * @var array
	 */
	protected $m_aIndependentOptions;
	
	/**
	 * These are options that need to be stored, but are never set by the UI.
	 * 
	 * @var array
	 */
	protected $m_aNonUiOptions;

	/**
	 * @var array
	 */
	protected $m_aOptionsValues;
	
	/**
	 * @var array
	 */
	protected $m_aOptionsStoreName;
	
	/**
	 * @var array
	 */
	protected $m_aOptionsKeys;
	
	public function __construct( $insPrefix, $insStoreName, $insVersion ) {
		$this->m_sOptionPrefix = $insPrefix;
		$this->m_aOptionsStoreName = $insStoreName;
		$this->m_sVersion = $insVersion;
		
		$this->m_fIsMultisite = function_exists( 'is_multisite' ) && is_multisite();
		
		// Handle any upgrades as necessary (only go near this if it's the admin area)
		add_action( 'plugins_loaded', array( $this, 'onWpPluginsLoaded' ), 1 );
	}
	
	/**
	 * A action added to WordPress 'plugins_loaded' hook
	 */
	public function onWpPluginsLoaded() {
		$this->doUpdates();
	}
	
	protected function doUpdates() {
		if ( $this->hasPluginManageRights() ) {
			$this->buildOptions();
			$this->updateHandler();
		}
	}
	
	public function hasPluginManageRights() {
		if ( !current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( $this->m_fIsMultisite && is_network_admin() ) {
			return true;
		}
		else if ( !$this->m_fIsMultisite && is_admin() ) {
			return true;
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return $this->getOpt( self::PluginVersionKey );
	}

	/**
	 * @return string
	 */
	public function setVersion( $insVersion ) {
		return $this->setOpt( self::PluginVersionKey, $insVersion );
	}
	
	/**
	 * Gets the array of all possible options keys
	 * 
	 * @return array
	 */
	public function getOptionsKeys() {
		$this->setOptionsKeys();
		return $this->m_aOptionsKeys;
	}
	
	/**
	 * @return void
	 */
	public function setOptionsKeys() {
		if ( !isset( $this->m_aOptionsKeys ) ) {
			$this->m_aOptionsKeys = array();
		}
	}
	
	/**
	 * Determines whether the given option key is a valid options
	 * 
	 * @return boolean
	 */
	public function getIsOptionKey( $insOptionKey ) {
		if ( $insOptionKey == self::PluginVersionKey ) {
			return true;
		}
		$this->setOptionsKeys();
		return ( in_array( $insOptionKey, $this->m_aOptionsKeys ) );
	}
	
	/**
	 * Sets the value for the given option key
	 * 
	 * @param string $insKey
	 * @param mixed $inmValue
	 * @return boolean
	 */
	public function setOpt( $insKey, $inmValue ) {
		
		if ( !$this->getIsOptionKey( $insKey ) ) {
			return false;
		}
		
		if ( !isset( $this->m_aOptionsValues ) ) {
			$this->loadStoredOptionsValues();
		}
		
		if ( $this->getOpt( $insKey ) === $inmValue ) {
			return true;
		}
		
		$this->m_aOptionsValues[ $insKey ] = $inmValue;
		
		if ( !$this->m_fNeedSave ) {
			$this->m_fNeedSave = true;
		}
		return true;
	}

	/**
	 * @param string $insKey
	 * @return Ambigous <boolean, multitype:>
	 */
	public function getOpt( $insKey ) {
		if ( !isset( $this->m_aOptionsValues ) ) {
			$this->loadStoredOptionsValues();
		}
		return ( isset( $this->m_aOptionsValues[ $insKey ] )? $this->m_aOptionsValues[ $insKey ] : false );
	}
	
	/**
	 * Retrieves the full array of options->values
	 * 
	 * @return array
	 */
	public function getOptions() {
		if ( !isset( $this->m_aOptions ) ) {
			$this->buildOptions();
		}
		return $this->m_aOptions;
	}

	/**
	 * Loads the options and their stored values from the WordPress Options store.
	 *
	 * @return array
	 */
	public function getPluginOptionsValues() {
		$this->generateOptionsValues();
		return $this->m_aOptionsValues;
	}
	
	/**
	 * Saves the options to the WordPress Options store.
	 * 
	 * It will also update the stored plugin options version.
	 */
	public function savePluginOptions() {
		
		$this->doPrePluginOptionsSave();
		$this->updateOptionsVersion();
		
		if ( !$this->m_fNeedSave ) {
			return true;
		}
		
		$this->updateOption( $this->m_aOptionsStoreName, $this->m_aOptionsValues );
		
		// Direct save options allow us to get fast access to certain values without loading the whole thing
		if ( isset( $this->m_aDirectSaveOptions ) && is_array( $this->m_aDirectSaveOptions ) ) {
			foreach( $this->m_aDirectSaveOptions as $sOptionKey ) {
				$this->updateOption( $sOptionKey, $this->getOpt( $sOptionKey ) );
			}
		}
		
		$this->m_fNeedSave = false;
	}
	
	public function collateAllFormInputsForAllOptions() {

		if ( !isset( $this->m_aOptions ) ) {
			$this->buildOptions();
		}
		
		$aToJoin = array();
		foreach ( $this->m_aOptions as $aOptionsSection ) {
			
			if ( empty( $aOptionsSection ) ) {
				continue;
			}
			foreach ( $aOptionsSection['section_options'] as $aOption ) {
				list($sKey, $fill1, $fill2, $sType) =  $aOption;
				$aToJoin[] = $sType.':'.$sKey;
			}
		}
		return implode( self::CollateSeparator, $aToJoin );
	}
	
	/**
	 * @return array
	 */
	protected function generateOptionsValues() {
		if ( !isset( $this->m_aOptionsValues ) ) {
			$this->loadStoredOptionsValues();
		}
		if ( empty( $this->m_aOptionsValues ) ) {
			$this->buildOptions();	// set the defaults
		}
	}
	
	/**
	 * Loads the options and their stored values from the WordPress Options store.
	 */
	protected function loadStoredOptionsValues() {
		if ( empty( $this->m_aOptionsValues ) ) {
			$this->m_aOptionsValues = $this->getOption( $this->m_aOptionsStoreName );
			if ( empty( $this->m_aOptionsValues ) ) {
				$this->m_aOptionsValues = array();
				$this->m_fNeedSave = true;
			}
		}
	}
	
	protected function defineOptions() {
		
		if ( !empty( $this->m_aOptions ) ) {
			return true;
		}
		
		$aMisc = array(
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
		$this->m_aOptions = array( $aMisc );
	}

	/**
	 * Will initiate the plugin options structure for use by the UI builder.
	 * 
	 * It will also fill in $this->m_aOptionsValues with defaults where appropriate.
	 * 
	 * It doesn't set any values, just populates the array created in buildOptions()
	 * with values stored.
	 * 
	 * It has to handle the conversion of stored values to data to be displayed to the user.
	 * 
	 * @param string $insUpdateKey - if only want to update a single key, supply it here.
	 */
	public function buildOptions() {

		$this->defineOptions();
		$this->loadStoredOptionsValues();

		foreach ( $this->m_aOptions as &$aOptionsSection ) {
			
			if ( empty( $aOptionsSection ) || !isset( $aOptionsSection['section_options'] ) ) {
				continue;
			}
			
			foreach ( $aOptionsSection['section_options'] as &$aOptionParams ) {
				
				list( $sOptionKey, $sOptionValue, $sOptionDefault, $sOptionType ) = $aOptionParams;
				
				if ( $this->getOpt( $sOptionKey ) === false ) {
					$this->setOpt( $sOptionKey, $sOptionDefault );
				}
				$mCurrentOptionVal = $this->getOpt( $sOptionKey );
				
				if ( $sOptionType == 'password' && !empty( $mCurrentOptionVal ) ) {
					$mCurrentOptionVal = '';
				}
				else if ( $sOptionType == 'ip_addresses' ) {
					
					if ( empty( $mCurrentOptionVal ) ) {
						$mCurrentOptionVal = '';
					}
					else {
						$mCurrentOptionVal = implode( "\n", $this->convertIpListForDisplay( $mCurrentOptionVal ) );
					}
				}
				else if ( $sOptionType == 'comma_separated_lists' ) {
					
					if ( empty( $mCurrentOptionVal ) ) {
						$mCurrentOptionVal = '';
					}
					else {
						$aNewValues = array();
						foreach( $mCurrentOptionVal as $sPage => $aParams ) {
							$aNewValues[] = $sPage.', '. implode( ", ", $aParams );
						}
						$mCurrentOptionVal = implode( "\n", $aNewValues );
					}
				}
				$aOptionParams[1] = $mCurrentOptionVal;
			}
		}
		
		// Cater for Non-UI options that don't necessarily go through the UI
		if ( isset($this->m_aNonUiOptions) && is_array($this->m_aNonUiOptions) ) {
			foreach( $this->m_aNonUiOptions as $sOption ) {
				if ( !$this->getOpt( $sOption ) ) {
					$this->setOpt( $sOption, '' );
				}
			}
		}
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() { }

	/**
	 * Will return the 'current_plugin_version' if it is set, 0.0 otherwise.
	 * 
	 * @return string
	 */
	public function getPluginOptionsVersion() {
		$sVersion = $this->getOpt( 'current_plugin_version' );
		return empty( $sVersion )? '0.0' :$sVersion;
	}
	
	/**
	 * Updates the 'current_plugin_version' to the offical plugin version.
	 */
	protected function updateOptionsVersion() {
		$this->setOpt( 'current_plugin_version', $this->m_sVersion );
	}
	
	/**
	 * Deletes all the options including direct save.
	 */
	public function deletePluginOptions() {

		$this->deleteOption( $this->m_aOptionsStoreName );
		
		// Direct save options allow us to get fast access to certain values without loading the whole thing
		if ( isset($this->m_aDirectSaveOptions) && is_array( $this->m_aDirectSaveOptions ) ) {
			foreach( $this->m_aDirectSaveOptions as $sOptionKey ) {
				$this->deleteOption( $sOptionKey );
			}
		}
		// Independent options are those untouched by the User/UI that are saved elsewhere and directly to the WP Options table. They are "meta" options
		if ( isset($this->m_aIndependentOptions) && is_array( $this->m_aIndependentOptions ) ) {
			foreach( $this->m_aIndependentOptions as $sOptionKey ) {
				$this->deleteOption( $sOptionKey );
			}
		}
	}
	
	protected function convertIpListForDisplay( $inaIpList = array() ) {

		$aDisplay = array();
		if ( empty( $inaIpList ) || empty( $inaIpList['ips'] ) ) {
			return $aDisplay;
		}
		foreach( $inaIpList['ips'] as $sAddress ) {
			// offset=1 in the case that it's a range and the first number is negative on 32-bit systems
			$mPos = strpos( $sAddress, '-', 1 );
			
			if ( $mPos === false ) { //plain IP address
				$sDisplayText = long2ip( $sAddress );
			}
			else {
				//we remove the first character in case this is '-'
				$aParts = array( substr( $sAddress, 0, 1 ), substr( $sAddress, 1 ) );
				list( $nStart, $nEnd ) = explode( '-', $aParts[1], 2 );
				$sDisplayText = long2ip( $aParts[0].$nStart ) .'-'. long2ip( $nEnd );
			}
			$sLabel = $inaIpList['meta'][ md5($sAddress) ];
			$sLabel = trim( $sLabel, '()' );
			$aDisplay[] = $sDisplayText . ' ('.$sLabel.')';
		}
		return $aDisplay;
	}

	/**
	 * @param string $sAllOptionsInput - comma separated list of all the input keys to be processed from the $_POST
	 * @return void|boolean
	 */
	public function updatePluginOptionsFromSubmit( $sAllOptionsInput ) {
		
		require_once ( dirname(__FILE__).'/icwp-data-processor.php' );
		$oProcessor = new ICWP_WPSF_DataProcessor();
	
		if ( empty( $sAllOptionsInput ) ) {
			return;
		}
		
		$this->loadStoredOptionsValues();
		
		$aAllInputOptions = explode( self::CollateSeparator, $sAllOptionsInput );
		foreach ( $aAllInputOptions as $sInputKey ) {
			$aInput = explode( ':', $sInputKey );
			list( $sOptionType, $sOptionKey ) = $aInput;
			
			if ( !$this->getIsOptionKey( $sOptionKey ) ) {
				continue;
			}

			$sOptionValue = $this->getFromPost( $sOptionKey );
			if ( is_null($sOptionValue) ) {
	
				if ( $sOptionType == 'text' || $sOptionType == 'email' ) { //if it was a text box, and it's null, don't update anything
					continue;
				}
				else if ( $sOptionType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$sOptionValue = 'N';
				}
				else if ( $sOptionType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$sOptionValue = 0;
				}
			}
			else { //handle any pre-processing we need to.
	
				if ( $sOptionType == 'integer' ) {
					$sOptionValue = intval( $sOptionValue );
				}
				else if ( $sOptionType == 'password' && $this->hasEncryptOption() ) { //md5 any password fields
					$sTempValue = trim( $sOptionValue );
					if ( empty( $sTempValue ) ) {
						continue;
					}
					$sOptionValue = md5( $sTempValue );
				}
				else if ( $sOptionType == 'ip_addresses' ) { //ip addresses are textareas, where each is separated by newline
					$sOptionValue = $oProcessor->ExtractIpAddresses( $sOptionValue );
				}
				else if ( $sOptionType == 'email' && function_exists( 'is_email' ) && !is_email( $sOptionValue ) ) {
					$sOptionValue = '';
				}
				else if ( $sOptionType == 'comma_separated_lists' ) {
					$sOptionValue = $oProcessor->ExtractCommaSeparatedList( $sOptionValue );
				}
			}
			$this->setOpt( $sOptionKey, $sOptionValue );
		}
		return $this->savePluginOptions( true );
	}
	
	/**
	 * Should be over-ridden by each new class to handle upgrades.
	 * 
	 * Called upon construction and after plugin options are initialized.
	 */
	protected function updateHandler() { }
	
	/**
	 * @param array $inaNewOptions
	 */
	protected function mergeNonUiOptions( $inaNewOptions = array() ) {

		if ( !empty( $this->m_aNonUiOptions ) ) {
			$this->m_aNonUiOptions = array_merge( $this->m_aNonUiOptions, $inaNewOptions );
		}
		else {
			$this->m_aNonUiOptions = $inaNewOptions;
		}
	}
	
	/**
	 * Copies WordPress Options to the options array and optionally deletes the original.
	 * 
	 * @param array $inaOptions
	 * @param boolean $fDeleteOld
	 */
	protected function migrateOptions( $inaOptions, $fDeleteOld = false ) {
		foreach( $inaOptions as $sOptionKey ) {
			$mCurrentValue = $this->getOption( $sOptionKey );
			if ( $mCurrentValue === false ) {
				continue;
			}
			$this->setOpt( $sOptionKey, $mCurrentValue );
			if ( $fDeleteOld ) {
				$this->deleteOption( $sOptionKey );
			}
		}
	}

	/**
	 * @return boolean
	 */
	public function hasEncryptOption() {
		return function_exists( 'md5' );
	//	return extension_loaded( 'mcrypt' );
	}
	
	protected function getVisitorIpAddress( $infAsLong = true ) {
		require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		return ICWP_WPSF_DataProcessor::GetVisitorIpAddress( $infAsLong );
	}
	
	/**
	 * @param string $insKey		-	the POST key
	 * @param string $insPrefix
	 * @return Ambigous <null, string>
	 */
	protected function getFromPost( $insKey, $insPrefix = null ) {
		$sKey = ( is_null( $insPrefix )? $this->m_sOptionPrefix : $insPrefix ) . $insKey;
		return ( isset( $_POST[ $sKey ] )? $_POST[ $sKey ]: null );
	}
	public function getOption( $insKey ) {
		$sKey = $this->m_sOptionPrefix.$insKey;
		return $this->m_fIsMultisite? get_site_option($sKey) : get_option($sKey);
	}
	public function addOption( $insKey, $insValue ) {
		$sKey = $this->m_sOptionPrefix.$insKey;
		return $this->m_fIsMultisite? add_site_option($sKey, $insValue) : add_option($sKey, $insValue);
	}
	public function updateOption( $insKey, $insValue ) {
		$sKey = $this->m_sOptionPrefix.$insKey;
		return $this->m_fIsMultisite? update_site_option($sKey, $insValue) : update_option($sKey, $insValue);
	}
	public function deleteOption( $insKey ) {
		$sKey = $this->m_sOptionPrefix.$insKey;
		return $this->m_fIsMultisite? delete_site_option($sKey) : delete_option($sKey);
	}

	
	/**
	 * @param string $insExistingListKey
	 * @param string $insFilterName
	 * @return array|false
	 */
	protected function processIpFilter( $insExistingListKey, $insFilterName ) {
		$aFilterIps = apply_filters( $insFilterName, array() );
		if ( empty( $aFilterIps ) ) {
			return false;
		}
			
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
		
		// now add and store the new IPs
		$aExistingIpList = $this->getOpt( $insExistingListKey );
		if ( !is_array( $aExistingIpList ) ) {
			$aExistingIpList = array();
		}

		$this->loadDataProcessor();
		$nNewAddedCount = 0;
		$aNewList = ICWP_WPSF_DataProcessor::Add_New_Raw_Ips( $aExistingIpList, $aNewIps, $nNewAddedCount );
		if ( $nNewAddedCount > 0 ) {
			$this->setOpt( $insExistingListKey, $aNewList );
		}
	}
	
	protected function loadDataProcessor() {
		if ( !class_exists('ICWP_WPSF_DataProcessor') ) {
			require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		}
	}
}

endif;

class ICWP_OptionsHandler_Base_Wpsf extends ICWP_OptionsHandler_Base_V2 { }