<?php

if ( !class_exists( 'ICWP_WPSF_FeatureHandler_AuditTrail_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_FeatureHandler_AuditTrail_V1 extends ICWP_WPSF_FeatureHandler_Base {

		protected function doExecuteProcessor() {
			if ( ! apply_filters( $this->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {
				parent::doExecuteProcessor();
			}
		}

		/**
		 */
		public function doPrePluginOptionsSave() {

			$nAutoClean = $this->getOpt( 'audit_trail_auto_clean' );
			if ( $nAutoClean < 0 ) {
				$this->getOptionsVo()->resetOptToDefault( 'audit_trail_auto_clean' );
			}
		}

		public function displayAuditTrailViewer() {

			/** @var ICWP_WPSF_Processor_AuditTrail $oAuditTrail */
			$oAuditTrail = $this->loadFeatureProcessor();

			$aContexts = array(
				'users',
				'plugins',
				'themes',
				'wordpress',
				'posts',
				'emails',
				'wpsf'
			);

			$aDisplayData = array(
				'nYourIp'			=> $this->loadDataProcessor()->getVisitorIpAddress( true ),
				'sFeatureName'		=> _wpsf__('Audit Trail Viewer')
			);

			$oWp = $this->loadWpFunctionsProcessor();
			$sTimeFormat = $oWp->getOption( 'time_format' );
			$sDateFormat = $oWp->getOption( 'date_format' );

			foreach( $aContexts as $sContext ) {
				$aAuditData = $oAuditTrail->getAuditEntriesForContext( $sContext );

				if ( is_array( $aAuditData ) ) {
					foreach( $aAuditData as &$aAuditEntry ) {
						$aAuditEntry[ 'event' ] = str_replace( '_', ' ', $aAuditEntry[ 'event' ] );
						$aAuditEntry[ 'created_at' ] = date_i18n( $sTimeFormat . ' ' . $sDateFormat, $aAuditEntry[ 'created_at' ] );
					}
				}
				$aDisplayData[ 'aAuditData' . ucfirst( $sContext ) ] = $aAuditData;
			}

			$this->display( $aDisplayData, 'subfeature-audit_trail_viewer' );
		}
		/**
		 * @return string
		 */
		public function getAuditTrailTableName() {
			return $this->doPluginPrefix( $this->getOpt( 'audit_trail_table_name' ), '_' );
		}

		/**
		 * @param array $aOptionsParams
		 * @return array
		 * @throws Exception
		 */
		protected function loadStrings_SectionTitles( $aOptionsParams ) {

			$sSectionSlug = $aOptionsParams['section_slug'];
			switch( $sSectionSlug ) {

				case 'section_enable_plugin_feature_audit_trail' :
					$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
					$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
					break;

				case 'section_audit_trail_options' :
					$sTitle = _wpsf__( 'Audit Trail Options' );
					$sTitleShort = _wpsf__( 'Options' );
					break;

				case 'section_enable_audit_contexts' :
					$sTitle = _wpsf__( 'Enable Audit Contexts' );
					$sTitleShort = _wpsf__( 'Audit Contexts' );
					break;

				default:
					throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
			}
			$aOptionsParams['section_title'] = $sTitle;
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

				case 'enable_audit_trail' :
					$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
					$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
					$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
					break;

				case 'audit_trail_auto_clean' :
					$sName = _wpsf__( 'Auto Clean' );
					$sSummary = _wpsf__( 'Enable Audit Auto Cleaning' );
					$sDescription = _wpsf__( 'Events older than the number of days specified will be automatically cleaned from the database.' );
					break;

				case 'enable_audit_context_users' :
					$sName = _wpsf__( 'Users And Logins' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Users And Logins' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will track user activity and significant events such as user login etc.' );
					break;

				case 'enable_audit_context_plugins' :
					$sName = _wpsf__( 'Plugins' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Plugins' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will track activity relating to WordPress plugins.' );
					break;

				case 'enable_audit_context_themes' :
					$sName = _wpsf__( 'Themes' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Themes' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will track activity relating to WordPress themes.' );
					break;

				case 'enable_audit_context_posts' :
					$sName = _wpsf__( 'Posts And Pages' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Posts And Pages' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will track activity relating to the editing and publishing of posts and pages.' );
					break;

				case 'enable_audit_context_wordpress' :
					$sName = _wpsf__( 'WordPress And Settings' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'WordPress And Settings' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will track WordPress upgrades and changes to particular WordPress settings.' );
					break;

				case 'enable_audit_context_emails' :
					$sName = _wpsf__( 'Emails' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Emails' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will attempt to track attempts at sending email.' );
					break;

				case 'enable_audit_context_wpsf' :
					$sName = _wpsf__( 'Simple Firewall' );
					$sSummary = sprintf( _wpsf__( 'Enable Audit Context - %s' ), _wpsf__( 'Simple Firewall' ) );
					$sDescription = _wpsf__( 'When this context is enabled, the audit trail will track activity directly related to the WordPress Simple Firewall plugin.' );
					break;

				default:
					throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
			}

			$aOptionsParams['name'] = $sName;
			$aOptionsParams['summary'] = $sSummary;
			$aOptionsParams['description'] = $sDescription;
			return $aOptionsParams;
		}

		/**
		 */
		protected function updateHandler() {
			parent::updateHandler();
			if ( version_compare( $this->getVersion(), '4.1.0', '<' ) ) {
				$this->setOpt( 'recreate_database_table', true );
			}
		}
	}

endif;

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_AuditTrail_V1 { }