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

if ( !class_exists('ICWP_WPSF_FeatureHandler_AdminAccessRestriction') ):

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_Base {

	/**
	 * @var string
	 */
	const AdminAccessKeyCookieName		= 'icwp_wpsf_aakcook';

	private $fHasPermissionToSubmit;
	
	/**
	 * @var ICWP_WPSF_Processor_AdminAccessRestriction
	 */
	protected $oFeatureProcessor;

	public function __construct( $oPluginVo ) {
		$this->sFeatureName = _wpsf__('Admin Access');
		$this->sFeatureSlug = 'admin_access_restriction';
		parent::__construct( $oPluginVo );

		add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'doCheckHasPermissionToSubmit' ) );
		add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'doCheckHasPermissionToSubmit' ) );
	}

	/**
	 * @return ICWP_WPSF_Processor_AdminAccessRestriction|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir().sprintf( 'icwp-processor-%s.php', $this->getFeatureSlug() ) );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_AdminAccessRestriction( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 * @param bool $fHasPermission
	 * @return bool
	 */
	public function doCheckHasPermissionToSubmit( $fHasPermission = true ) {

		$oDp = $this->loadDataProcessor();
		$sAccessKeyRequest = $oDp->FetchPost( $this->doPluginPrefix( 'admin_access_key_request', '_' ) );
		if ( !empty( $sAccessKeyRequest ) ) {
			$sAccessKeyRequest = md5( trim( $sAccessKeyRequest ) );
			if ( $sAccessKeyRequest === $this->getOpt( 'admin_access_key' ) ) {
				$this->setPermissionToSubmit( true );
				wp_safe_redirect( network_admin_url() );
			}
		}

		if ( isset( $this->fHasPermissionToSubmit ) ) {
			return $this->fHasPermissionToSubmit;
		}

		$this->fHasPermissionToSubmit = $fHasPermission;
		if ( $this->getIsMainFeatureEnabled() )  {
			$sAccessKey = $this->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				$this->loadDataProcessor();
				$sHash = md5( $sAccessKey );
				$sCookieValue = $oDp->FetchCookie( $this->getAdminAccessKeyCookieName() );
				$this->fHasPermissionToSubmit = ( $sCookieValue === $sHash );
			}
		}
		return $this->fHasPermissionToSubmit;
	}

	/**
	 */
	public function handleFormSubmit() {
		$fSuccess = parent::handleFormSubmit();
		if ( !$fSuccess ) {
			return $fSuccess;
		}

		$oDp = $this->loadDataProcessor();
		if ( $this->getIsCurrentPageConfig() && is_null( $oDp->FetchPost( $this->doPluginPrefix( 'enable_admin_access_restriction', '_' ) ) ) ) {
			$this->setPermissionToSubmit( false );
		}
	}

	/**
	 * @return string
	 */
	public function getAdminAccessKeyCookieName() {
		return $this->getOpt( 'admin_access_key_cookie_name' );
	}

	/**
	 * @param bool $fPermission
	 */
	protected function setPermissionToSubmit( $fPermission = false ) {
		$sCookieName = $this->getAdminAccessKeyCookieName();
		if ( $fPermission ) {
			$sAccessKey = $this->getOpt( 'admin_access_key' );

			if ( !empty( $sAccessKey ) ) {
				$sValue = md5( $sAccessKey );
				$sTimeout = $this->getOpt( 'admin_access_timeout' ) * 60;
				$_COOKIE[ $sCookieName ] = $sValue;
				setcookie( $sCookieName, $sValue, time()+$sTimeout, COOKIEPATH, COOKIE_DOMAIN, false );
			}
		}
		else {
			unset( $_COOKIE[ $sCookieName ] );
			setcookie( $sCookieName, "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams['section_slug'];
		switch( $aOptionsParams['section_slug'] ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('Admin Access Restriction') );
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

			case 'enable_admin_access_restriction' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Admin Access') );
				$sSummary = _wpsf__( 'Enforce Admin Access Restriction' );
				$sDescription = _wpsf__( 'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin.' );
				break;

			case 'admin_access_key' :
				$sName = _wpsf__( 'Admin Access Key' );
				$sSummary = _wpsf__( 'Provide/Update Admin Access Key' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' ) );
				break;

			case 'admin_access_timeout' :
				$sName = _wpsf__( 'Admin Access Timeout' );
				$sSummary = _wpsf__( 'Specify An Automatic Timeout Interval For Admin Access' );
				$sDescription = _wpsf__( 'This will automatically expire your WordPress Simple Firewall session. Does not apply until you enter the access key again.')
					.'<br />'.sprintf(_wpsf__( 'Default: %s minutes.' ), $this->getOptionsVo()->getOptDefault( 'admin_access_timeout' ) );
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
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		if ( $this->getOpt( 'admin_access_timeout' ) < 1 ) {
			$this->getOptionsVo()->resetOptToDefault( 'admin_access_timeout' );
		}

		$sNotificationEmail = $this->getOpt( 'enable_admin_login_email_notification' );
		if ( !empty( $sNotificationEmail ) && !is_email( $sNotificationEmail ) ) {
			$this->setOpt( 'enable_admin_login_email_notification', '' );
		}

		$sAccessKey = $this->getOpt( 'admin_access_key' );
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}
	}

	protected function updateHandler() {
		parent::updateHandler();

		if ( $this->getVersion() == '0.0' ) {
			return;
		}

		if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
			$aAllOptions = apply_filters( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array() );
			$this->setOpt( 'enable_admin_access_restriction', $aAllOptions['enable_admin_access_restriction'] );
			$this->setOpt( 'admin_access_key', $aAllOptions['admin_access_key'] );
			$this->setOpt( 'admin_access_timeout', $aAllOptions['admin_access_timeout'] );
		}
	}
}

endif;