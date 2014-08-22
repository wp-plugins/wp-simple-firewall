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

require_once( dirname(__FILE__).'/icwp-base-processor.php' );

if ( !class_exists('ICWP_WPSF_Processor_LoginProtect_V4') ):

class ICWP_WPSF_Processor_LoginProtect_V4 extends ICWP_WPSF_Processor_Base {
	
	/**
	 * @var ICWP_WPSF_FeatureHandler_LoginProtect
	 */
	protected $oFeatureOptions;

	/**
	 * @param ICWP_WPSF_FeatureHandler_LoginProtect $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_LoginProtect $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
		$this->reset();
	}

	/**
	 * @return bool|void
	 */
	public function getIsLogging() {
		return $this->getIsOption( 'enable_login_protect_log', 'Y' );
	}

	/**
	 */
	public function run() {
		parent::run();
		$this->loadDataProcessor();

		$fIsPost = ICWP_WPSF_DataProcessor::GetIsRequestPost();

		$aWhitelist = $this->getOption( 'ips_whitelist', array() );
		if ( !empty( $aWhitelist ) && $this->isIpOnlist( $aWhitelist, self::$nRequestIp ) ) {
			return true;
		}

		$oWp = $this->oFeatureOptions->loadWpFunctionsProcessor();
		// XML-RPC Compatibility
		if ( $oWp->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return true;
		}

		// check for remote posting before anything else.
		if ( $fIsPost && $this->getIsOption( 'enable_prevent_remote_post', 'Y' ) ) {
			add_filter( 'authenticate',			array( $this, 'checkRemotePostLogin_Filter' ), 9, 3);
		}

		// Add GASP checking to the login form.
		if ( $this->getIsOption( 'enable_login_gasp_check', 'Y' ) ) {
			require_once('icwp-processor-loginprotect_gasp.php');
			$oGaspProcessor = new ICWP_WPSF_Processor_LoginProtect_Gasp( $this->oFeatureOptions );
			$oGaspProcessor->run();
		}

		if ( $fIsPost && $this->getOption( 'login_limit_interval' ) > 0 ) {
			require_once('icwp-processor-loginprotect_cooldown.php');
			$oCooldownProcessor = new ICWP_WPSF_Processor_LoginProtect_Cooldown( $this->oFeatureOptions );
			$oCooldownProcessor->run();
		}

		// check for Yubikey auth after user is authenticated with WordPress.
		if ( $this->getIsOption( 'enable_yubikey', 'Y' ) ) {
			require_once('icwp-processor-loginprotect_yubikey.php');
			$oYubikeyProcessor = new ICWP_WPSF_Processor_LoginProtect_Yubikey( $this->oFeatureOptions );
			$oYubikeyProcessor->run();
		}

		if ( $this->oFeatureOptions->getIsTwoFactorAuthOn() ) {
			require_once('icwp-processor-loginprotect_twofactorauth.php');
			$oTwoFactorAuthProcessor = new ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth( $this->oFeatureOptions );
			$oTwoFactorAuthProcessor->run();
		}

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( ! $oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$this->loadDataProcessor();
		$sForceLogout = ICWP_WPSF_DataProcessor::FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout == 6 ) {
			$oError->add( 'wpsf-forcelogout', _wpsf__('Your Two-Factor Authentication Was Verified.').'<br />'._wpsf__('Please login again.') );
		}
		return $oError;
	}

	/**
	 * @param $oUser
	 * @param $sUsername
	 * @param $sPassword
	 * @return mixed
	 */
	public function checkRemotePostLogin_Filter( $oUser, $sUsername, $sPassword ) {
		$this->loadDataProcessor();
		$sHttpRef = ICWP_WPSF_DataProcessor::FetchServer( 'HTTP_REFERER' );

		if ( !empty( $sHttpRef ) ) {
			$aHttpRefererParts = parse_url( $sHttpRef );
			$aHomeUrlParts = parse_url( home_url() );

			if ( !empty( $aHttpRefererParts['host'] ) && !empty( $aHomeUrlParts['host'] ) && ( $aHttpRefererParts['host'] === $aHomeUrlParts['host'] ) ) {
				$this->doStatIncrement( 'login.remotepost.success' );
				return $oUser;
			}
		}

		$this->logWarning(
			sprintf( _wpsf__('User "%s" attempted to login but the HTTP REFERER was either empty or it was a remote login attempt. Bot Perhaps? HTTP REFERER: "%s".'), $sUsername, $sHttpRef )
		);
		$this->doStatIncrement( 'login.remotepost.fail' );
		wp_die(
			_wpsf__( 'Sorry, you must login directly from within the site.' )
			.' '._wpsf__( 'Remote login is not supported.' )
			.'<br /><a href="http://icwp.io/4n" target="_blank">&rarr;'._wpsf__('More Info').'</a>'
		);
	}

	/**
	 * Should return false when logging is disabled.
	 *
	 * @return false|array	- false when logging is disabled, array with log data otherwise
	 * @see ICWP_WPSF_Processor_Base::getLogData()
	 */
	public function flushLogData() {
	
		if ( !$this->getIsLogging() || empty( $this->m_aLogMessages ) ) {
			return false;
		}

		$this->m_aLog = array(
			'category'			=> self::LOG_CATEGORY_LOGINPROTECT,
			'messages'			=> serialize( $this->m_aLogMessages )
		);
		$this->resetLog();
		return $this->m_aLog;
	}
	
}
endif;

if ( !class_exists('ICWP_WPSF_Processor_LoginProtect') ):
	class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_LoginProtect_V4 { }
endif;
