<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
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

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_WPSF_FeatureHandler_AuditTrail_V1') ):

class ICWP_WPSF_FeatureHandler_AuditTrail_V1 extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var ICWP_WPSF_Processor_AuditTrail
	 */
	protected $oFeatureProcessor;

	/**
	 * @return ICWP_WPSF_Processor_AuditTrail|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->getController()->getPath_SourceFile( sprintf( 'icwp-processor-%s.php', $this->getFeatureSlug() ) ) );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_AuditTrail( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 * @return bool|void
	 */
	public function doExtraSubmitProcessing() { }

	public function doPrePluginOptionsSave() {}

	public function displayAuditTrailViewer() {

		$oAuditTrail = $this->loadFeatureProcessor();
		$aData = array(
			'nYourIp'			=> $this->loadDataProcessor()->GetVisitorIpAddress(),
			'sFeatureName'		=> _wpsf__('Audit Trail Viewer'),
			'aAuditDataUsers'	=> $oAuditTrail->getAuditEntriesForContext( 'users' ),
			'aAuditDataPlugins'	=> $oAuditTrail->getAuditEntriesForContext( 'plugins' ),
			'aAuditDataThemes'	=> $oAuditTrail->getAuditEntriesForContext( 'themes' ),
			'aAuditDataWordpress'	=> $oAuditTrail->getAuditEntriesForContext( 'wordpress' ),
			'aAuditDataPosts'	=> $oAuditTrail->getAuditEntriesForContext( 'posts' ),
			'aAuditDataEmails'	=> $oAuditTrail->getAuditEntriesForContext( 'emails' ),
			'aAuditDataWpsf'	=> $oAuditTrail->getAuditEntriesForContext( 'wpsf' )
		);
		$this->display( $aData, $this->doPluginPrefix( 'audit_trail_viewer_index' ) );
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
				break;

			case 'section_enable_audit_contexts' :
				$sTitle = _wpsf__( 'Enable Audit Contexts' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams['section_title'] = $sTitle;
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
}

endif;

class ICWP_WPSF_FeatureHandler_AuditTrail extends ICWP_WPSF_FeatureHandler_AuditTrail_V1 { }