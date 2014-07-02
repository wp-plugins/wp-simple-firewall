<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * Version: 2013-08-14_A
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

require_once( dirname(__FILE__).'/icwp-data-processor.php' );

if ( !class_exists('ICWP_WpFunctions_V4') ):

class ICWP_WpFunctions_V4 {

	/**
	 * @var ICWP_WpFunctions_V4
	 */
	protected static $oInstance = NULL;

	/**
	 * @return ICWP_WpFunctions_V4
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @var string
	 */
	protected $m_sWpVersion;

	/**
	 * @var boolean
	 */
	protected $fIsMultisite;
	
	public function __construct() {}

	/**
	 * @param string $insPluginFile
	 * @return boolean|stdClass
	 */
	public function getIsPluginUpdateAvailable( $insPluginFile ) {
		$aUpdates = $this->getWordpressUpdates();
		if ( empty( $aUpdates ) ) {
			return false;
		}
		if ( isset( $aUpdates[ $insPluginFile ] ) ) {
			return $aUpdates[ $insPluginFile ];
		}
		return false;
	}

	public function getPluginUpgradeLink( $insPluginFile ) {
		$sUrl = self_admin_url( 'update.php' ) ;
		$aQueryArgs = array(
			'action' 	=> 'upgrade-plugin',
			'plugin'	=> urlencode( $insPluginFile ),
			'_wpnonce'	=> wp_create_nonce( 'upgrade-plugin_' . $insPluginFile )
		);
		return add_query_arg( $aQueryArgs, $sUrl );
	}
	
	public function getWordpressUpdates() {
		$oCurrent = $this->getTransient( 'update_plugins' );
		return $oCurrent->response;
	}
	
	/**
	 * The full plugin file to be upgraded.
	 * 
	 * @param string $insPluginFile
	 * @return boolean
	 */
	public function doPluginUpgrade( $insPluginFile ) {

		if ( !$this->getIsPluginUpdateAvailable($insPluginFile)
			|| ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] == 'update.php' ) ) {
			return true;
		}
		$sUrl = $this->getPluginUpgradeLink( $insPluginFile );
		wp_redirect( $sUrl );
		exit();
	}

	/**
	 * @param string $insKey
	 * @return object
	 */
	protected function getTransient( $insKey ) {
	
		// TODO: Handle multisite
	
		if ( version_compare( $this->getWordpressVersion(), '2.7.9', '<=' ) ) {
			return get_option( $insKey );
		}
	
		if ( function_exists( 'get_site_transient' ) ) {
			return get_site_transient( $insKey );
		}
	
		if ( version_compare( $this->getWordpressVersion(), '2.9.9', '<=' ) ) {
			return apply_filters( 'transient_'.$insKey, get_option( '_transient_'.$insKey ) );
		}
	
		return apply_filters( 'site_transient_'.$insKey, get_option( '_site_transient_'.$insKey ) );
	}
	
	/**
	 * @return string
	 */
	public function getWordpressVersion() {
		global $wp_version;
		
		if ( empty( $this->m_sWpVersion ) ) {
			$sVersionFile = ABSPATH.WPINC.'/version.php';
			$sVersionContents = file_get_contents( $sVersionFile );
			
			if ( preg_match( '/wp_version\s=\s\'([^(\'|")]+)\'/i', $sVersionContents, $aMatches ) ) {
				$this->m_sWpVersion = $aMatches[1];
			}
		}
		return empty( $this->m_sWpVersion )? $wp_version : $this->m_sWpVersion;
	}

	/**
	 * @param array $aQueryParams
	 */
	public function redirectToLogin( $aQueryParams = array() ) {
		$sLoginUrl = site_url() . '/wp-login.php';
		$this->doRedirect( $sLoginUrl, $aQueryParams );
		exit();
	}
	/**
	 * @param $aQueryParams
	 */
	public function redirectToAdmin( $aQueryParams = array() ) {
		$this->doRedirect( is_multisite()? get_admin_url() : admin_url(), $aQueryParams );
	}
	/**
	 * @param $aQueryParams
	 */
	public function redirectToHome( $aQueryParams = array() ) {
		$this->doRedirect( home_url(), $aQueryParams );
	}

	/**
	 * @param $sUrl
	 * @param $aQueryParams
	 */
	public function doRedirect( $sUrl, $aQueryParams = array() ) {
		$sUrl = empty( $aQueryParams ) ? $sUrl : add_query_arg( $aQueryParams, $sUrl ) ;
		wp_safe_redirect( $sUrl );
		exit();
	}

	/**
	 * @return string
	 */
	public function getCurrentPage() {
		global $pagenow;
		return $pagenow;
	}

	/**
	 * @param string
	 * @return string
	 */
	public function getIsCurrentPage( $sPage ) {
		return $sPage == $this->getCurrentPage();
	}

	/**
	 * @return bool
	 */
	public function getIsLoginRequest() {
		return ICWP_WPSF_DataProcessor::GetIsRequestPost()
			&& $this->getIsCurrentPage('wp-login.php')
			&& !is_null( ICWP_WPSF_DataProcessor::FetchPost('log') )
			&& !is_null( ICWP_WPSF_DataProcessor::FetchPost('pwd') );
	}

	/**
	 * @return string
	 */
	public function getSiteName() {
		return function_exists( 'get_bloginfo' )? get_bloginfo('name') : 'WordPress Site';
	}
	/**
	 * @return string
	 */
	public function getSiteAdminEmail() {
		return function_exists( 'get_bloginfo' )? get_bloginfo('admin_email') : '';
	}

	/**
	 * @return boolean
	 */
	public function getIsAjax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * @param string $sRedirectUrl
	 */
	public function logoutUser( $sRedirectUrl = '' ) {
		empty( $sRedirectUrl ) ? wp_logout() : wp_logout_url( $sRedirectUrl );
	}

	/**
	 * @return bool
	 */
	public function isMultisite() {
		if ( !isset( $this->fIsMultisite ) ) {
			$this->fIsMultisite = function_exists( 'is_multisite' ) && is_multisite();
		}
		return $this->fIsMultisite;
	}

	/**
	 * @param string $sKey
	 * @param $sValue
	 * @return mixed
	 */
	public function addOption( $sKey, $sValue ) {
		return $this->isMultisite() ? add_site_option( $sKey, $sValue ) : add_option( $sKey, $sValue );
	}

	/**
	 * @param string $sKey
	 * @param $sValue
	 * @return mixed
	 */
	public function updateOption( $sKey, $sValue ) {
		return $this->isMultisite() ? update_site_option( $sKey, $sValue ) : update_option( $sKey, $sValue );
	}

	/**
	 * @param string $sKey
	 * @param mixed $mDefault
	 * @return mixed
	 */
	public function getOption( $sKey, $mDefault = false ) {
		return $this->isMultisite() ? get_site_option( $sKey, $mDefault ) : get_option( $sKey, $mDefault );
	}

	/**
	 * @param string $sKey
	 * @return mixed
	 */
	public function deleteOption( $sKey ) {
		return $this->isMultisite() ? delete_site_option( $sKey ) : delete_option( $sKey );
	}

	/**
	 */
	public function getCurrentWpAdminPage() {
		$sScript = isset( $_SERVER['SCRIPT_NAME'] )? $_SERVER['SCRIPT_NAME'] : $_SERVER['PHP_SELF'];
		if ( is_admin() && !empty( $sScript ) && basename( $sScript ) == 'admin.php' && isset( $_GET['page'] ) ) {
			$sCurrentPage = $_GET['page'];
		}
		return empty($sCurrentPage)? '' : $sCurrentPage;
	}

	/**
	 * @return null|WP_User
	 */
	public function getCurrentWpUser() {
		if ( is_user_logged_in() ) {
			$oUser = wp_get_current_user();
			if ( is_object( $oUser ) && $oUser instanceof WP_User ) {
				return $oUser;
			}
		}
		return null;
	}

	/**
	 * @param $sUsername
	 */
	public function setUserLoggedIn( $sUsername ) {
		$oUser = version_compare( $this->getWordpressVersion(), '2.8.0', '<' )? get_userdatabylogin( $sUsername ) : get_user_by( 'login', $sUsername );

		wp_clear_auth_cookie();
		wp_set_current_user ( $oUser->ID, $oUser->user_login );
		wp_set_auth_cookie  ( $oUser->ID, true );
		do_action( 'wp_login', $oUser->user_login, $oUser );
	}
}
endif;

if ( !class_exists('ICWP_WPSF_WpFunctions') ):

	class ICWP_WPSF_WpFunctions extends ICWP_WpFunctions_V4 {
		/**
		 * @return ICWP_WPSF_WpFunctions
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}
	}
endif;