<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
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

	const Slug = 'lockdown';

	public function __construct( $insOptionPrefix = '' ) {
		parent::__construct( $this->constructStorageKey( $insOptionPrefix, self::Slug ) );
	}
	
	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
	}
	
	/**
	 */
	public function run() {
		
		if ( $this->m_aOptions['disable_file_editing'] == 'Y' ) {
			add_filter( 'user_has_cap', array( $this, 'disableFileEditing' ), 0, 3 );
		}
		
		if ( !empty( $this->m_aOptions['mask_wordpress_version'] ) ) {
			global $wp_version;
			$wp_version = $this->m_aOptions['mask_wordpress_version'];
// 			add_filter( 'bloginfo', array( $this, 'maskWordpressVersion' ), 1, 2 );
// 			add_filter( 'bloginfo_url', array( $this, 'maskWordpressVersion' ), 1, 2 );
		} 

		if ( false && $this->m_aOptions['action_reset_auth_salts'] == 'Y' ) {
			add_action( 'init', array( $this, 'resetAuthKeysSalts' ), 1 );
		}
	}
	
	/**
	 * @return array
	 */
	public function disableFileEditing( $inaAllCaps, $cap, $inaArgs ) {
		
		$aEditCapabilities = array( 'edit_themes', 'edit_plugins', 'edit_files' );
		$sRequestedCapability = $inaArgs[0];
		
		if ( !in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			return $inaAllCaps;
		}
		$inaAllCaps[ $sRequestedCapability ] = false;
		return $inaAllCaps;
	}
	
	/**
	 * @return array
	 */
	public function maskWordpressVersion( $insOutput, $insShow ) {
// 		if ( $insShow === 'version' ) {
// 			$insOutput = $this->m_aOptions['mask_wordpress_version'];
// 		}
// 		return $insOutput;
	}
	
	/**
	 * 
	 */
	public function resetAuthKeysSalts() {
		
		require_once( dirname(__FILE__).'/icwp-wpfilesystem.php' );
		$oWpFs = ICWP_WpFilesystem_V1::GetInstance();
		
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