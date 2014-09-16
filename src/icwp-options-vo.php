<?php
/**
 * Created by PhpStorm.
 * User: Paul
 * Date: 04/09/14
 * Time: 16:51
 */

class ICWP_WPSF_OptionsVO {

	/**
	 * @var array
	 */
	protected $aOptionsValues;

	/**
	 * @var array
	 */
	protected $aRawOptionsConfigData;

	/**
	 * @var boolean
	 */
	protected $fIsYaml;

	/**
	 * @var boolean
	 */
	protected $fNeedSave;

	/**
	 * @var string
	 */
	protected $aOptionsKeys;

	/**
	 * @var string
	 */
	protected $sOptionsStorageKey;

	/**
	 * @var string
	 */
	protected $sOptionsName;

	/**
	 * @param string $sOptionsName
	 */
	public function __construct( $sOptionsName ) {
		$this->sOptionsName = $sOptionsName;
	}

	/**
	 * @return bool
	 */
	public function doOptionsSave() {
		$this->cleanOptions();
		if ( !$this->getNeedSave() ) {
			return true;
		}
		$oWpFunc = $this->loadWpFunctionsProcessor();
		$this->setNeedSave( false );
		return $oWpFunc->updateOption( $this->getOptionsStorageKey(), $this->getAllOptionsValues() );
	}

	/**
	 * @return bool
	 */
	public function doOptionsDelete() {
		$oWpFunc = $this->loadWpFunctionsProcessor();
		return $oWpFunc->deleteOption( $this->getOptionsStorageKey() );
	}

	/**
	 * @return array
	 */
	public function getAllOptionsValues() {
		return $this->loadStoredOptionsValues();
	}

	/**
	 * @param $sProperty
	 * @return null|mixed
	 */
	public function getFeatureProperty( $sProperty ) {
		$aRawConfig = $this->getRawOptionsConfigData();
		return ( isset( $aRawConfig['properties'] ) && isset( $aRawConfig['properties'][$sProperty] ) ) ? $aRawConfig['properties'][$sProperty] : null;
	}

	/**
	 * @param string $sKey
	 * @return boolean
	 */
	public function getIsOptionKey( $sKey ) {
		return in_array( $sKey, $this->getOptionsKeys() );
	}

	/**
	 * Determines whether the given option key is a valid option
	 *
	 * @param string
	 * @return boolean
	 */
	public function getIsValidOptionKey( $sOptionKey ) {
		return in_array( $sOptionKey, $this->getOptionsKeys() );
	}

	/**
	 * @return array
	 */
	public function getLegacyOptionsConfigData() {

		$aRawData = $this->getRawOptionsConfigData();
		$aLegacyData = array();

		foreach( $aRawData['sections'] as $nPosition => $aRawSection ) {

			if ( isset( $aRawSection['hidden'] ) && $aRawSection['hidden'] ) {
				continue;
			}

			$aLegacySection = array();
			$aLegacySection['section_slug'] = $aRawSection['slug'];
			$aLegacySection['section_options'] = array();
			foreach( $aRawData['options'] as $aRawOption ) {

				if ( $aRawOption['section'] != $aRawSection['slug'] ) {
					continue;
				}

				if ( isset( $aRawOption['hidden'] ) && $aRawOption['hidden'] ) {
					continue;
				}

				$aLegacyRawOption = array();
				$aLegacyRawOption['key'] = $aRawOption['key'];
				$aLegacyRawOption['value'] = ''; //value
				$aLegacyRawOption['default'] = $aRawOption['default'];
				$aLegacyRawOption['type'] = $aRawOption['type'];

				$aLegacyRawOption['value_options'] = array();
				if ( in_array( $aLegacyRawOption['type'], array( 'select', 'multiple_select' ) ) ) {
					foreach( $aRawOption['value_options'] as $aValueOptions ) {
						$aLegacyRawOption['value_options'][ $aValueOptions['value_key'] ] = $aValueOptions['text'];
					}
				}

				$aLegacyRawOption['info_link'] = isset( $aRawOption['link_info'] ) ? $aRawOption['link_info'] : '';
				$aLegacyRawOption['blog_link'] = isset( $aRawOption['link_blog'] ) ? $aRawOption['link_blog'] : '';
				$aLegacySection['section_options'][] = $aLegacyRawOption;
			}

			$aLegacyData[ $nPosition ] = $aLegacySection;
		}
		return $aLegacyData;
	}

	/**
	 * @return array
	 */
	public function getLegacyOptionsNonUi() {
		$aRawData = $this->getRawOptionsConfigData();
		foreach( $aRawData['sections'] as $aRawSection ) {

			if ( !isset( $aRawSection['hidden'] ) || !$aRawSection['hidden'] ) {
				continue;
			}
			$sKey = $aRawSection['slug'];
			break;
		}
		$aNonUi = array();
		foreach( $aRawData['options'] as $aOption ) {
			if ( $aOption['section'] != $sKey ) {
				continue;
			}
			$aNonUi[] = $aOption['key'];
		}
		return $aNonUi;
	}

	/**
	 * @return string
	 */
	public function getNeedSave() {
		return $this->fNeedSave;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed $mDefault
	 * @return mixed
	 */
	public function getOpt( $sOptionKey, $mDefault = false ) {
		$aOptionsValues = $this->getAllOptionsValues();
		if ( !isset( $aOptionsValues[ $sOptionKey ] ) ) {
			$this->setOpt( $sOptionKey, $this->getOptDefault( $sOptionKey, $mDefault ), true );
		}
		return $this->aOptionsValues[ $sOptionKey ];
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed $mDefault
	 * @return mixed|null
	 */
	public function getOptDefault( $sOptionKey, $mDefault = null ) {
		$aRawOptionsData = $this->getRawOptionsConfigData();
		$aOptions = $aRawOptionsData['options'];
		foreach( $aOptions as $aOption ) {
			if ( $aOption['key'] == $sOptionKey ) {
				return isset( $aOption['default'] ) ? $aOption['default'] : $mDefault;
			}
		}
		return $mDefault;
	}

	/**
	 * @param $sKey
	 * @param mixed $mValueToTest
	 * @param boolean $fStrict
	 * @return bool
	 */
	public function getOptIs( $sKey, $mValueToTest, $fStrict = false ) {
		$mOptionValue = $this->getOpt( $sKey );
		return $fStrict? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * @param string $sOptionKey
	 * @return null|array
	 */
	public function getOptionRawConfig( $sOptionKey ) {
		foreach( $this->getAllRawOptions() as $aOption ) {
			if ( $sOptionKey == $aOption['key'] ) {
				return $aOption;
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getOptionsKeys() {
		if ( !isset( $this->aOptionsKeys ) ) {
			$this->aOptionsKeys = array();
			foreach( $this->getAllRawOptions() as $aOption ) {
				$this->aOptionsKeys[] = $aOption['key'];
			}
		}
		return $this->aOptionsKeys;
	}

	/**
	 * @return string
	 */
	public function getOptionsStorageKey() {
		return $this->sOptionsStorageKey;
	}

	/**
	 * @return array
	 */
	public function getRawOptionsConfigData() {
		if ( empty( $this->aRawOptionsConfigData ) ) {
			$this->aRawOptionsConfigData = $this->readYamlConfiguration( $this->sOptionsName );
		}
		return $this->aRawOptionsConfigData;
	}

	/**
	 * Return the section of the Raw config that is the "options" key only.
	 *
	 * @return array
	 */
	protected function getAllRawOptions() {
		$aAllRawOptions = $this->getRawOptionsConfigData();
		return isset( $aAllRawOptions['options'] ) ? $aAllRawOptions['options'] : array();
	}

	/**
	 * @param string $sOptionKey
	 * @return boolean
	 */
	public function resetOptToDefault( $sOptionKey ) {
		return $this->setOpt( $sOptionKey, $this->getOptDefault( $sOptionKey ) );
	}

	/**
	 * @param string $sKey
	 */
	public function setOptionsStorageKey( $sKey ) {
		$this->sOptionsStorageKey = $sKey;
	}

	/**
	 * @param boolean $fNeed
	 */
	public function setNeedSave( $fNeed ) {
		$this->fNeedSave = $fNeed;
	}

	/**
	 * @param string $sOptionKey
	 * @param mixed $mValue
	 * @param boolean $fForce
	 * @return mixed
	 */
	public function setOpt( $sOptionKey, $mValue, $fForce = false ) {

		if ( $fForce || $this->getOpt( $sOptionKey ) !== $mValue ) {
			$this->aOptionsValues[ $sOptionKey ] = $mValue;
			$this->setNeedSave( true );
		}
		return true;
	}

	/**
	 * @param array $aOptions
	 */
	public function setRawOptionsConfigData( $aOptions ) {
		$this->aRawOptionsConfigData = $aOptions;
	}

	/**
	 * @param string $sOptionKey
	 * @return mixed
	 */
	public function unsetOpt( $sOptionKey ) {

		unset( $this->aOptionsValues[$sOptionKey] );
		$this->setNeedSave( true );
		return true;
	}

	/** PRIVATE STUFF */

	/**
	 */
	private function cleanOptions() {
		if ( empty( $this->aOptionsValues ) || !is_array( $this->aOptionsValues ) ) {
			return;
		}
		foreach( $this->aOptionsValues as $sKey => $mValue ) {
			if ( !$this->getIsValidOptionKey( $sKey ) ) {
				$this->setNeedSave( true );
				unset( $this->aOptionsValues[$sKey] );
			}
		}
	}

	/**
	 * @param boolean $fReload
	 * @return array
	 * @throws Exception
	 */
	private function loadStoredOptionsValues( $fReload = false ) {

		if ( $fReload || empty( $this->aOptionsValues ) ) {

			$sStorageKey = $this->getOptionsStorageKey();
			if ( empty( $sStorageKey ) ) {
				throw new Exception( 'Options Storage Key Is Empty' );
			}

			$oWpFunc = $this->loadWpFunctionsProcessor();
			$this->aOptionsValues = $oWpFunc->getOption( $sStorageKey, array() );
			if ( empty( $this->aOptionsValues ) ) {
				$this->aOptionsValues = array();
				$this->setNeedSave( true );
			}
		}
		return $this->aOptionsValues;
	}

	/**
	 * @return ICWP_WPSF_YamlProcessor
	 */
	private function loadYamlProcessor() {
		require_once( dirname(__FILE__) . '/icwp-processor-yaml.php' );
		return ICWP_WPSF_YamlProcessor::GetInstance();
	}

	/**
	 * @param string $sName
	 * @return array
	 * @throws Exception
	 */
	private function readYamlConfiguration( $sName ) {
		$oFs = $this->loadFileSystemProcessor();

		$aConfig = array();
		$sConfigFile = dirname( __FILE__ ). sprintf( '/config/options-%s.yaml', $sName );
		if ( !$oFs->exists( $sConfigFile ) ) {
			throw new Exception( 'YAML configuration file for options does not exist. Options: '.$sName );
		}
		if ( $oFs->exists( $sConfigFile ) ) {
			$sContents = $oFs->getFileContent( $sConfigFile );
			if ( !empty( $sContents ) ) {
				$oYaml = $this->loadYamlProcessor();
				$aConfig = $oYaml->parseYamlString( $sContents );
			}
		}
		return $aConfig;
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	private function loadFileSystemProcessor() {
		if ( !class_exists('ICWP_WPSF_WpFilesystem') ) {
			require_once( dirname(__FILE__) . '/icwp-wpfilesystem.php' );
		}
		return ICWP_WPSF_WpFilesystem::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	private function loadWpFunctionsProcessor() {
		if ( !class_exists('ICWP_WPSF_WpFunctions') ) {
			require_once( dirname(__FILE__) . '/icwp-wpfunctions.php' );
		}
		return ICWP_WPSF_WpFunctions::GetInstance();
	}
}