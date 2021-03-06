<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_Statistics', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_Statistics extends ICWP_WPSF_FeatureHandler_Base {

		public function doPrePluginOptionsSave() { }

		public function displayStatisticsDataViewer() {

			/** @var ICWP_WPSF_Processor_Statistics $oStatics */
			$oStatics = $this->loadFeatureProcessor();

			$aStatsData = $this->getStatsData();
			ksort( $aStatsData );

			$this->display( array( 'aStatsData' => $aStatsData ), 'subfeature-statistics_data_viewer.php' );
		}

		/**
		 * @return array
		 */
		protected function getStatsData() {
			$this->loadStatsProcessor();
			return ICWP_Stats_WPSF::GetStatsData();}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $aOptionsParams['section_slug'] ) {

				case 'section_enable_plugin_feature_statistics' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Helps you see at a glance how effective the plugin has been.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), $this->getMainFeatureName() ) )
					);
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_stats_sharing' :
					$sTitle = _wpsf__( 'Statistics Sharing' );
					$aSummary = array(
						sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Help us to provide globally accessible statistics on the effectiveness of the plugin.' ) ),
						sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Enabling this option helps us improve our plugin over time.' ) )
						. _wpsf__( 'All statistics data collection is 100% anonymous.' )._wpsf__( 'Neither we nor anyone else will be able to trace the data back to the originating site.' )

					);
					$sTitleShort = _wpsf__( 'Sharing' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
			$aOptionsParams['section_summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
			$aOptionsParams['section_title_short'] = $sTitleShort;
			return $aOptionsParams;
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_Options( $aOptionsParams ) {

			$sKey = $aOptionsParams['key'];
			switch( $sKey ) {

				case 'enable_statistics' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'enable_stats_sharing' :
					$sName = _wpsf__( 'Enable Statistic Sharing' );
					$sSummary = _wpsf__( 'The plugin will share its statistics to allow for global data gathering and analysis' );
					$sDescription = _wpsf__( 'Sharing the statistics garnered from the plugin will help show how effective we are and areas we can improve.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}
	}

endif;