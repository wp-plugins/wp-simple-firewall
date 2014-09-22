<?php

class ICWP_WPSF_Foundation {

	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	public function loadDataProcessor() {
		require_once( 'icwp-data-processor.php' );
		return ICWP_WPSF_DataProcessor::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	public function loadFileSystemProcessor() {
		require_once( 'icwp-wpfilesystem.php' );
		return ICWP_WPSF_WpFilesystem::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	public function loadWpFunctionsProcessor() {
		require_once( 'icwp-wpfunctions.php' );
		return ICWP_WPSF_WpFunctions::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_YamlProcessor
	 */
	public function loadYamlProcessor() {
		require_once( 'icwp-processor-yaml.php' );
		return ICWP_WPSF_YamlProcessor::GetInstance();
	}

	/**
	 * @return ICWP_Stats_WPSF
	 */
	public function loadStatsProcessor() {
		require_once( 'icwp-wpsf-stats.php' );
	}

}