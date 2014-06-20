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

if ( !class_exists('ICWP_LockdownProcessor_V1') ):

class ICWP_LockdownProcessor_V1 extends ICWP_WPSF_BaseProcessor {

	/**
	 * @param ICWP_OptionsHandler_Lockdown $oFeatureOptions
	 */
	public function __construct( ICWP_OptionsHandler_Lockdown $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions );
	}

	/**
	 */
	public function run() {
		
		if ( $this->getIsOption( 'disable_file_editing', 'Y' ) ) {
			if ( !defined('DISALLOW_FILE_EDIT') ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
			add_filter( 'user_has_cap', array( $this, 'disableFileEditing' ), 0, 3 );
		}

		$sWpVersionMask = $this->getOption('mask_wordpress_version');
		if ( !empty( $sWpVersionMask ) ) {
			global $wp_version;
			$wp_version = $sWpVersionMask;
// 			add_filter( 'bloginfo', array( $this, 'maskWordpressVersion' ), 1, 2 );
// 			add_filter( 'bloginfo_url', array( $this, 'maskWordpressVersion' ), 1, 2 );
		}

		if ( false && $this->getOption('action_reset_auth_salts') == 'Y' ) {
			add_action( 'init', array( $this, 'resetAuthKeysSalts' ), 1 );
		}

		if ( $this->getIsOption( 'force_ssl_login', 'Y' ) && function_exists('force_ssl_login') ) {
			if ( !defined('FORCE_SSL_LOGIN') ) {
				define( 'FORCE_SSL_LOGIN', true );
			}
			force_ssl_login( true );
		}

		if ( $this->getIsOption( 'force_ssl_admin', 'Y' ) && function_exists('force_ssl_admin') ) {
			if ( !defined('FORCE_SSL_ADMIN') ) {
				define( 'FORCE_SSL_ADMIN', true );
			}
			force_ssl_admin( true );
		}
	}

	/**
	 * @return array
	 */
	public function disableFileEditing( $aAllCaps, $cap, $aArgs ) {
		
		$aEditCapabilities = array( 'edit_themes', 'edit_plugins', 'edit_files' );
		$sRequestedCapability = $aArgs[0];
		
		if ( !in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			return $aAllCaps;
		}
		$aAllCaps[ $sRequestedCapability ] = false;
		return $aAllCaps;
	}
	
	/**
	 * @return array
	 */
	public function maskWordpressVersion( $insOutput, $insShow ) {
// 		if ( $insShow === 'version' ) {
// 			$insOutput = $this->aOptions['mask_wordpress_version'];
// 		}
// 		return $insOutput;
	}
	
	/**
	 * 
	 */
	public function resetAuthKeysSalts() {
		$oWpFs = $this->loadFileSystemProcessor();
		
		// Get the new Salts
		$sSaltsUrl = 'https://api.wordpress.org/secret-key/1.1/salt/';
		$sSalts = $oWpFs->getUrlContent( $sSaltsUrl );
		
		$sWpConfigContent = $oWpFs->getContent_WpConfig();
		if ( is_null( $sWpConfigContent ) ) {
			return;
		}
		
		$aKeys = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT'
		);

		$aContent = explode( PHP_EOL, $sWpConfigContent );
		$fKeyFound = false;
		$nStartLine = 0;
		foreach( $aContent as $nLineNumber => $sLine ) {
			foreach( $aKeys as $nPosition => $sKey ) {
				if ( strpos( $sLine, $sKey ) === false ) {
					continue;
				}
				if ( $nStartLine == 0 ) {
					$nStartLine = $nLineNumber;
				}
				else {
					unset( $aContent[ $nLineNumber ] );
				}
				$fKeyFound = true;
			}
		}
		$aContent[$nStartLine] = $sSalts;
		$oWpFs->putContent_WpConfig( implode( PHP_EOL, $aContent ) );
	}
}

endif;

if ( !class_exists('ICWP_WPSF_LockdownProcessor') ):
	class ICWP_WPSF_LockdownProcessor extends ICWP_LockdownProcessor_V1 { }
endif;