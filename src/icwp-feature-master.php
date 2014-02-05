<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 *
 * This is
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

require_once( dirname(__FILE__).'/icwp-pure-base.php' );

if ( !class_exists('ICWP_Feature_Master') ):

class ICWP_Feature_Master extends ICWP_Pure_Base_V4 {
	
	/**
	 *@var array
	 */
	protected $m_aFeatures;
	
	/**
	 *@var array
	 */
	protected $m_aOptionsHandlers;
	
	/**
	 * @var ICWP_OptionsHandler_Wpsf
	 */
	protected $m_oPluginMainOptions;
	
	public function __construct( $inaFeatures, $inaOptions ) {
		parent::__construct();
		$this->m_aFeatures = $inaFeatures;
		$this->m_aOptionsHandlers = $inaOptions;
	}
	
	/**
	 * Based on the existance of files placed within the plugin directory, will enable or disable
	 * all registered features and return the value of the override setting that was put in place.
	 * 
	 * @return string - override settings (empty string if none).
	 */
	protected function override() {
		
		if ( $this->m_oWpFs->exists( path_join($this->m_sPluginDir, 'forceOff') ) ) {
			$sSetting = 'N';
		}
		else if ( $this->m_oWpFs->exists( path_join($this->m_sPluginDir, 'forceOn') ) ) {
			$sSetting = 'Y';
		}
		else {
			$sSetting = '';
		}
		
		if ( $sSetting == '' ) {
			return $sSetting;
		}
		
		$aFeatures = $this->getFeaturesMap();
		foreach( $aFeatures as $sFeature => $sName ) {
			$this->setSharedOption( 'enable_'.$sFeature, $sSetting );
		}
		return $sSetting;
	}
	
	/**
	 * @return array
	 */
	protected function getFeaturesMap() {
		return $this->m_aFeatures;
	}
	
	/**
	 * Given a certain feature 'slug' will return true if this is a particular supported feature of this plugin.
	 * 
	 * @param string $insFeature
	 * @return boolean
	 */
	public function getIsFeature( $insFeature ) {
		return array_key_exists( $insFeature, $this->getFeaturesMap() ) || in_array( $insFeature, $this->getFeaturesMap() );
	}
	
	/**
	 * @param string $insFeature	- firewall, login_protect, comments_filter, lockdown
	 * @return boolean
	 */
	public function getIsMainFeatureEnabled( $insFeature ) {
		
		if ( $this->m_oWpFs->exists( $this->m_sPluginPath . 'forceOff' ) ) {
			return false;
		}
		else if ( $this->m_oWpFs->exists( $this->m_sPluginPath . 'forceOn' ) ) {
			return true;
		}
		
		$aFeatures = $this->getFeaturesMap();
		if ( array_key_exists( $insFeature, $aFeatures ) ) {
			$fEnabled = $this->m_oPluginMainOptions->getOpt( 'enable_'.$insFeature ) == 'Y';
		}
		else {
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

		$aFeatures = $this->getFeaturesMap();
		
		$sFeature = str_replace( 'enable_', '', $insOption );
		if ( !array_key_exists( $sFeature, $aFeatures ) ) {
			return;
		}
		
		$this->loadOptionsHandler( $aFeatures[$sFeature] );
		$sOptions = 'm_o'.$aFeatures[$sFeature].'Options';// e.g. m_oFirewallOptions
		$this->{$sOptions}->setOpt( $insOption, $inmValue );
		$this->m_oPluginMainOptions->setOpt( $insOption, $inmValue );
	}
	
	protected function loadOptionsHandler( $insOptionHandler, $infRecreate = false, $infFullBuild = false ) {

		$aAllHandlers = array_values( $this->getFeaturesMap() );
		$aAllHandlers[] = 'PluginMain';
		
		// special case
		if ( $insOptionHandler == 'all' ) {
			foreach( $aAllHandlers as $sHandler ) {
				$fSuccess = $this->loadOptionsHandler( $sHandler, $infRecreate, $infFullBuild );
			}
			return $fSuccess;
		}
		
		if ( !in_array( $insOptionHandler, $aAllHandlers ) ) {
			return false;
		}
		
		$sOptionsVarName = 'm_o'.$insOptionHandler.'Options'; // e.g. m_oPluginMainOptions
		if ( $insOptionHandler == 'PluginMain' ) {
			$sSourceFile = dirname(__FILE__).'/icwp-optionshandler-'.$this->m_sPluginSlug.'.php'; // e.g. icwp-optionshandler-wpsf.php
			$sClassName = 'ICWP_OptionsHandler_'.ucfirst( $this->m_sPluginSlug ); // e.g. ICWP_OptionsHandler_Wpsf
		}
		else {
			$sSourceFile = dirname(__FILE__).'/icwp-optionshandler-'.strtolower($insOptionHandler).'.php'; // e.g. icwp-optionshandler-wpsf.php
			$sClassName = 'ICWP_OptionsHandler_'.$insOptionHandler; // e.g. ICWP_OptionsHandler_Wpsf
		}
		
		require_once( $sSourceFile );
		if ( $infRecreate || !isset( $this->{$sOptionsVarName} ) ) {
		 	$this->{$sOptionsVarName} = new $sClassName( $this->m_sOptionPrefix, $this->m_sVersion, $infFullBuild );
		}
		if ( $infFullBuild ) {
			$this->{$sOptionsVarName}->buildOptions();
		}
		return true;
	}
	
	/**
	 * Given a feature/processor name will load the variable for it, including the appropriate source file.
	 * 
	 * @param string $insProcessorName
	 * @param boolean $infRebuild
	 * @return ICWP_OptionsHandler_Base_Wpsf
	 */
	protected function loadProcessor( $insProcessorName, $infRebuild = false ) {
		$aAllProcessors = $this->getFeaturesMap();

		if ( !in_array( $insProcessorName, array_values($aAllProcessors) ) ) {
			$this->doWpDie( sprintf('Processor %s is not permitted here.', $insProcessorName) );
		}
		
		$sProcessorVarName = 'm_o'.$insProcessorName.'Processor'; // e.g. m_oFirewallProcessor
		$sSourceFile = dirname(__FILE__).'/icwp-processor-'.strtolower($insProcessorName).'.php'; // e.g. icwp-optionshandler-wpsf.php
		$sClassName = 'ICWP_'.strtoupper( $this->m_sPluginSlug ).'_'.$insProcessorName.'Processor'; // e.g. ICWP_WPSF_FirewallProcessor
		$sStorageKey = array_search($insProcessorName, $aAllProcessors).'_processor'; // e.g. firewall_processor
		$sOptionsHandlerVarName = 'm_o'.$insProcessorName.'Options'; // e.g. m_oFirewallOptions
		
		require_once( $sSourceFile );
		if ( $infRebuild || empty( $this->{$sProcessorVarName} ) ) {
			$oTemp = $this->getOption( $sStorageKey );
			if ( !$infRebuild && is_object( $oTemp ) && ( $oTemp instanceof $sClassName ) ) {
				$oTemp->reset();
			}
			else {
				$oTemp = new $sClassName( $this->m_sOptionPrefix );
			}
			$this->{$sProcessorVarName} = $oTemp;
		}
		if ( $this->loadOptionsHandler( $insProcessorName ) ) {
			$this->{$sProcessorVarName}->setOptions( $this->{$sOptionsHandlerVarName}->getPluginOptionsValues() );
		}
		return $this->{$sProcessorVarName};
	}
	
	protected function resetProcessor( $insProcessorName ) {
		if ( !$this->getIsFeature( $insProcessorName ) ) {
			$this->doWpDie('Not a processor: '.$insProcessorName);
			return;
		}
		$this->loadProcessor( $insProcessorName );
		return;
	}
	
	protected function resetOptionHandler( $insOptionName ) {
		if ( !$this->getIsFeature( $insOptionName ) ) {
			$this->doWpDie('Not a feature: '.$insOptionName);
			return;
		}
		$this->loadOptionsHandler( $insOptionName );
		return;
	}
	
	public function clearCaches() {
		$aFeatures = $this->getFeaturesMap();
		foreach( $aFeatures as $sFeature ) {
			$this->resetOptionHandler( $sFeature );
			$this->resetProcessor( $sFeature );
		}
	}
	
	protected function getAllOptionsHandlers() {
		$this->loadOptionsHandler('all');
		$aOptions = array();
		foreach( $this->m_aOptionsHandlers as $sName ) {
			if ( isset( $this->{$sName} ) ) {
				$aOptions[] = &$this->{$sName};
			}
		}
		return $aOptions;
	}
	
	/**
	 * Makes sure and cache the processors after all is said and done.
	 */
	public function saveProcessors() {
		$aFeatures = $this->getFeaturesMap();
		foreach( $aFeatures as $sSlug => $sProcessorName ) {
			$oProcessor = $this->getProcessorVar( $sProcessorName );
			if ( !is_null($oProcessor) && is_object($oProcessor) ) {
				$oProcessor->store();
			}
		}
	}
	
	/**
	 * Makes sure and cache the processors after all is said and done.
	 */
	public function saveOptions() {
		$aOptions = $this->getAllOptionsHandlers();
		foreach( $aOptions as &$oOption ) {
			if ( isset( $oOption ) ) {
				$oOption->savePluginOptions();
			}
		}
	}

	/**
	 * 
	 * @param type $insProcessorName
	 * @param type $infLoad
	 * @return null|ICWP_WPSF_BaseProcessor
	 */
	protected function getProcessorVar( $insProcessorName, $infLoad = false ) {
		if ( !$this->getIsFeature( $insProcessorName ) ) {
			return null;
		}
		if ( $infLoad ) {
			$this->loadProcessor( $insProcessorName );
		}
		$sProcessorVariable = 'm_o'.$insProcessorName.'Processor';
		return $this->{$sProcessorVariable};
	}

	protected function shutdown() {
		parent::shutdown();
		$this->saveOptions();
		$this->saveProcessors();
	}
	
	protected function deleteAllPluginDbOptions() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		$aOptions = $this->getAllOptionsHandlers();
		foreach( $aOptions as &$oOption ) {
			$oOption->deletePluginOptions();
		}
		
		$aFeatures = $this->getFeaturesMap();
		foreach( $aFeatures as $sSlug => $sProcessorName ) {
			$oProcessor = $this->getProcessorVar( $sProcessorName, true );
			if ( !is_null($oProcessor) && is_object($oProcessor) ) {
				$oProcessor->deleteAndCleanUp();
			}
		}
		remove_action( 'shutdown', array( $this, 'onWpShutdown' ) );
	}

	public function onWpActivatePlugin() {
		$this->loadOptionsHandler( 'all', true, true );
	}
	
	public function onWpDeactivatePlugin() {
		if ( $this->m_oPluginMainOptions->getOpt( 'delete_on_deactivate' ) == 'Y' ) {
			$this->deleteAllPluginDbOptions();
		}
	}

}

endif;