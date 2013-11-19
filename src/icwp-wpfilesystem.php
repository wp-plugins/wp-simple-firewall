<?php
/**
 * Copyright (c) 2013 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * Version: 2013-11-19
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

if ( !class_exists('ICWP_WpFilesystem_V1') ):

class ICWP_WpFilesystem_V1 {

	/**
	 * @var ICWP_WpFilesystem_V1
	 */
	protected static $oInstance = NULL;

	/**
	 * @var object
	 */
	protected $m_oWpFilesystem = null;

	/**
	 * @var string
	 */
	protected $m_sWpConfigPath = null;
	
	/**
	 * @return ICWP_WpFilesystem_V1
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}
	
	public function __construct() {
		$this->initFileSystem();
// 		$this->setWpConfigPath();
	}
	
	/**
	 * @return boolean	true/false whether file/directory exists
	 */
	public function exists( $insPath ) {
		return $this->fileAction( 'file_exists', $insPath );
	}
	
	protected function setWpConfigPath() {
		$this->m_sWpConfigPath = ABSPATH.'wp-config.php';
		if ( !$this->exists($this->m_sWpConfigPath)  ) {
			$this->m_sWpConfigPath = ABSPATH.'..'.ICWP_DS.'wp-config.php';
			if ( !$this->exists($this->m_sWpConfigPath)  ) {
				$this->m_sWpConfigPath = false;
			}
		}
	}

	protected function initFileSystem() {
		if ( is_null( $this->m_oWpFilesystem ) ) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			WP_Filesystem();
			global $wp_filesystem;
			if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) ) {
				$this->m_oWpFilesystem = &$wp_filesystem;
			}
			else {
				$this->m_oWpFilesystem = false;
			}
		}
	}
	
	public function getContent_WpConfig() {
		return $this->getFileContent( $this->m_sWpConfigPath );
	}
	
	public function putContent_WpConfig( $insContent ) {
		return $this->putFileContent( $this->m_sWpConfigPath, $insContent );
	}
	
	
	/**
	 * @return string
	 */
	public function getWpConfigPath() {
		return $this->m_sWpConfigPath;
	}
	
	public function getUrl( $insUrl ) {
		$mResult = wp_remote_get( $insUrl );
		if ( is_wp_error( $mResult ) ) {
			return false;
		}
		if ( !isset( $mResult['response']['code'] ) || $mResult['response']['code'] != 200 ) {
			return false;
		}
		return $mResult;
	}
	
	public function getUrlContent( $insUrl ) {
		
		$aResponse = $this->getUrl( $insUrl );
		if ( !$aResponse ) {
			return false;
		}
		return $aResponse['body'];
	}
	
	public function getCanWpRemoteGet() {
		$aUrlsToTest = array(
			'https://www.microsoft.com',
			'https://www.google.com',
			'https://www.facebook.com'
		);
		foreach( $aUrlsToTest as $sUrl ) {
			if ( $this->getUrl( $sUrl ) !== false ) {
				return true;
			}
		}
		return false;
	}
	
	public function getCanDiskWrite() {
		$sFilePath = dirname( __FILE__ ).'/testfile.'.rand().'txt';
		$sContents = "Testing icwp file read and write.";
		
		// Write, read, verify, delete.
		if ( $this->putFileContent( $sFilePath, $sContents ) ) {
			$sFileContents = $this->getFileContent( $sFilePath );
			if ( !is_null( $sFileContents ) && $sFileContents === $sContents ) {
				return $this->deleteFile( $sFilePath );
			}
		}
		return false;
	}
	
	/**
	 * @param string $insFilePath
	 * @return NULL|boolean
	 */
	public function getCanReadWriteFile( $insFilePath ) {
		if ( !file_exists( $insFilePath ) ) {
			return null;
		}
		
		$nFileSize = filesize( $insFilePath );
		if ( $nFileSize === 0 ) {
			return null;
		}

		$sFileContent = $this->getFileContent( $insFilePath );
		if ( empty( $sFileContent ) ) {
			return false; //can't even read the file!
		}
		return $this->putFileContent( $insFilePath, $sFileContent );
	}
	
	/**
	 * @return string|null
	 */
	public function getFileContent( $insFilePath ) {
		if ( !$this->exists( $insFilePath ) ) {
			return null;
		}
		if ( $this->m_oWpFilesystem ) {
			return $this->m_oWpFilesystem->get_contents( $insFilePath );
		}
		else if ( function_exists('file_get_contents') ) {
			return file_get_contents( $insFilePath );
		}
		return null;
	}
	
	/**
	 * @return boolean
	 */
	public function putFileContent( $insFilePath, $insContents ) {
		if ( $this->m_oWpFilesystem ) {
			return $this->m_oWpFilesystem->put_contents( $insFilePath, $insContents, FS_CHMOD_FILE );
		}
		else if ( file_put_contents( $insFilePath, $insContents ) === false ) {
			return false;
		}
		return true;
	}

	/**
	 * @return boolean
	 */
	public function deleteFile( $insFilePath ) {
		if ( !file_exists( $insFilePath ) ) {
			return null;
		}
		if ( $this->m_oWpFilesystem ) {
			return $this->m_oWpFilesystem->delete( $insFilePath );
		}
		else {
			return unlink( $insFilePath );
		}
	}

	public function fileAction( $insFunctionName, $inaParams ) {
		$aFunctionMap = array(
			'file_exists'	=> 'exists',
			'touch'			=> 'touch'
		);
		
		if ( !is_array($inaParams) ) {
			$inaParams = array($inaParams);
		}
		
		if ( !$this->m_oWpFilesystem ) {
			if ( function_exists( $insFunctionName ) ) {
				call_user_func_array( $insFunctionName, $inaParams );
			}
			else {
				return false;
			}
		}
		if ( !array_key_exists($insFunctionName, $aFunctionMap) ) {
			return false;
		}
		$sWpFunctionName = $aFunctionMap[$insFunctionName];
		$sResult = call_user_func_array( array($this->m_oWpFilesystem, $sWpFunctionName), $inaParams );
		return $sResult;
	}
}

endif;