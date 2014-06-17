<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
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

class ICWP_Feature_Master extends ICWP_Pure_Base_V5 {
	
	/**
	 *@var array
	 */
	protected $aFeatures;
	
	/**
	 *@var array
	 */
	protected $m_aOptionsHandlers;
	
	/**
	 * @var ICWP_OptionsHandler_Wpsf
	 */
	protected $m_oPluginMainOptions;

	protected $fHasFtpOverride = false;

	public function __construct( ICWP_Wordpress_Simple_Firewall_Plugin $oPluginVo, $aFeatures, $inaOptions ) {
		parent::__construct( $oPluginVo );
		$this->aFeatures = $aFeatures;
		$this->m_aOptionsHandlers = $inaOptions;
	}
	
	/**
	 * Based on the existence of files placed within the plugin directory, will enable or disable
	 * all registered features and return the value of the override setting that was put in place.
	 * 
	 * @return string - override settings (empty string if none).
	 */
	protected function override() {
		
		if ( $this->m_oWpFs->exists( path_join($this->sPluginRootDir, 'forceOff') ) ) {
			$fHasFtpOverride = true;
			$sSetting = 'N';
		}
		else if ( $this->m_oWpFs->exists( path_join($this->sPluginRootDir, 'forceOn') ) ) {
			$fHasFtpOverride = true;
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
		return $this->aFeatures;
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
		
		if ( $this->m_oWpFs->exists( $this->sPluginRootDir . 'forceOff' ) ) {
			return false;
		}
		else if ( $this->m_oWpFs->exists( $this->sPluginRootDir . 'forceOn' ) ) {
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
	 * @param string $insOption
	 * @param mixed $inmValue
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
	
	protected function loadOptionsHandler( $insOptionHandler = 'PluginMain', $infRecreate = false, $infFullBuild = false ) {

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
			$sSourceFile = dirname(__FILE__).'/icwp-optionshandler-'.$this->sPluginSlug.'.php'; // e.g. icwp-optionshandler-wpsf.php
			$sClassName = 'ICWP_OptionsHandler_'.ucfirst( $this->sPluginSlug ); // e.g. ICWP_OptionsHandler_Wpsf
		}
		else {
			$sSourceFile = dirname(__FILE__).'/icwp-optionshandler-'.strtolower($insOptionHandler).'.php'; // e.g. icwp-optionshandler-wpsf.php
			$sClassName = 'ICWP_OptionsHandler_'.$insOptionHandler; // e.g. ICWP_OptionsHandler_Wpsf
		}
		
		require_once( $sSourceFile );
		if ( $infRecreate || !isset( $this->{$sOptionsVarName} ) ) {
		 	$this->{$sOptionsVarName} = new $sClassName( $this->oPluginVo );
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
		$sClassName = 'ICWP_'.strtoupper( $this->sPluginSlug ).'_'.$insProcessorName.'Processor'; // e.g. ICWP_WPSF_FirewallProcessor
		$sStorageKey = array_search($insProcessorName, $aAllProcessors).'_processor'; // e.g. firewall_processor
		$sOptionsHandlerVarName = 'm_o'.$insProcessorName.'Options'; // e.g. m_oFirewallOptions
		
		require_once( $sSourceFile );
		if ( $infRebuild || empty( $this->{$sProcessorVarName} ) ) {
			$oTemp = $this->getOption( $sStorageKey );
			if ( !$infRebuild && is_object( $oTemp ) && ( $oTemp instanceof $sClassName ) ) {
				$oTemp->reset();
			}
			else {
				$oTemp = new $sClassName( $this->oPluginVo, self::$sOptionPrefix );
			}
			$this->{$sProcessorVarName} = $oTemp;
		}
		if ( $this->loadOptionsHandler( $insProcessorName ) ) {
			$aOptionsValues = $this->{$sOptionsHandlerVarName}->getPluginOptionsValues();
			$this->{$sProcessorVarName}->setOptions( $aOptionsValues );
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
	 * @param string $insProcessorName
	 * @param bool $infLoad
	 * @return null|ICWP_WPSF_BaseProcessor
	 */
	protected function getProcessorVar( $insProcessorName, $infLoad = false ) {
		if ( !$this->getIsFeature( $insProcessorName ) ) {
			return null;
		}
		$sProcessorVariable = 'm_o'.$insProcessorName.'Processor';
		if ( $infLoad || !isset( $this->{$sProcessorVariable} ) ) {
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