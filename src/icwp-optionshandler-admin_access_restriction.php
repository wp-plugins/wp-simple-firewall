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
	 * @const integer
	 */
	const Default_AccessKeyTimeout = 30;

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
		$this->fShowFeatureMenuItem = true;
		parent::__construct( $oPluginVo );

		add_filter( $this->doPluginPrefix( 'has_permission_to_submit' ), array( $this, 'doCheckHasPermissionToSubmit' ) );
		add_filter( $this->doPluginPrefix( 'has_permission_to_view' ), array( $this, 'doCheckHasPermissionToSubmit' ) );
	}

	/**
	 * @return ICWP_WPSF_Processor_AdminAccessRestriction|null
	 */
	protected function loadFeatureProcessor() {
		if ( !isset( $this->oFeatureProcessor ) ) {
			require_once( $this->oPluginVo->getSourceDir().'icwp-processor-adminaccessrestriction.php' );
			$this->oFeatureProcessor = new ICWP_WPSF_Processor_AdminAccessRestriction( $this );
		}
		return $this->oFeatureProcessor;
	}

	/**
	 *
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
				$sCookieValue = $oDp->FetchCookie( self::AdminAccessKeyCookieName );
				$this->fHasPermissionToSubmit = ( $sCookieValue === $sHash );
			}
		}
		return $this->fHasPermissionToSubmit;
	}

	/**
	 *
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
	 * @param bool $fPermission
	 */
	protected function setPermissionToSubmit( $fPermission = false ) {
		if ( $fPermission ) {
			$sAccessKey = $this->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				$sValue = md5( $sAccessKey );
				$sTimeout = $this->getOpt( 'admin_access_timeout' ) * 60;
				$_COOKIE[ self::AdminAccessKeyCookieName ] = $sValue;
				setcookie( self::AdminAccessKeyCookieName, $sValue, time()+$sTimeout, COOKIEPATH, COOKIE_DOMAIN, false );
			}
		}
		else {
			unset( $_COOKIE[ self::AdminAccessKeyCookieName ] );
			setcookie( self::AdminAccessKeyCookieName, "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 * @return bool|void
	 */
	protected function getOptionsDefinitions() {

		if ( $this->hasEncryptOption() ) {

			$aAccessKey = array(
				'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('Admin Access Restriction') ),
				'section_options' => array(
					array(
						'enable_admin_access_restriction',
						'',
						'N',
						'checkbox',
						sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Admin Access') ),
						_wpsf__( 'Enforce Admin Access Restriction' ),
						_wpsf__( 'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin.' ),
						'<a href="http://icwp.io/40" target="_blank">'._wpsf__( 'more info' ).'</a>'
						.' | <a href="http://icwp.io/wpsf02" target="_blank">'._wpsf__( 'blog' ).'</a>'
					),
					array(
						'admin_access_key',
						'',
						'',
						'password',
						_wpsf__( 'Admin Access Key' ),
						_wpsf__( 'Specify Your Plugin Access Key' ),
						_wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' )
						.' <strong>'._wpsf__( 'Leave it blank to not update it' ).'</strong>',
						'<a href="http://icwp.io/42" target="_blank">'._wpsf__( 'more info' ).'</a>'
					),
					array(
						'admin_access_timeout',
						'',
						self::Default_AccessKeyTimeout,
						'integer',
						_wpsf__( 'Access Key Timeout' ),
						_wpsf__( 'Specify A Timeout For Plugin Admin Access' ),
						_wpsf__( 'This will automatically expire your WordPress Simple Firewall session. Does not apply until you enter the access key again.').'<br />'.sprintf(_wpsf__( 'Default: %s minutes.' ), self::Default_AccessKeyTimeout ),
						'<a href="http://icwp.io/41" target="_blank">'._wpsf__( 'more info' ).'</a>'
					)
				)
			);
		}
		$aOptionsDefinitions = array(
			$aAccessKey
		);
		return $aOptionsDefinitions;
	}
	
	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		
		if ( $this->getOpt( 'admin_access_key_timeout' ) <= 0 ) {
			$this->setOpt( 'admin_access_key_timeout', self::Default_AccessKeyTimeout );
		}
		
		$sAccessKey = $this->getOpt( 'admin_access_key');
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}
	}

	protected function updateHandler() {
		parent::updateHandler();
		if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
			$aAllOptions = apply_filters( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array() );
			$this->setOpt( 'enable_admin_access_restriction', $aAllOptions['enable_admin_access_restriction'] );
			$this->setOpt( 'admin_access_key', $aAllOptions['admin_access_key'] );
			$this->setOpt( 'admin_access_timeout', $aAllOptions['admin_access_timeout'] );
		}
	}
}

endif;