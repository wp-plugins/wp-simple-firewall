<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
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

if ( !class_exists('ICWP_WPSF_WpFilesystem') ):

	class ICWP_WPSF_WpFilesystem {

		/**
		 * @var ICWP_WPSF_WpFilesystem
		 */
		protected static $oInstance = NULL;

		/**
		 * @var WP_Filesystem
		 */
		protected $oWpfs = null;

		/**
		 * @var string
		 */
		protected $m_sWpConfigPath = null;

		/**
		 * @return ICWP_WPSF_WpFilesystem
		 */
		public static function GetInstance() {
			if ( is_null( self::$oInstance ) ) {
				self::$oInstance = new self();
			}
			return self::$oInstance;
		}

		/**
		 * @param string $sBase
		 * @param string $sPath
		 * @return string
		 */
		public function pathJoin( $sBase, $sPath ) {
			return rtrim( $sBase, ICWP_DS ).ICWP_DS.ltrim( $sPath, ICWP_DS );
		}

		/**
		 * @param $sFilePath
		 * @return boolean|null	true/false whether file/directory exists
		 */
		public function exists( $sFilePath ) {
			$oFs = $this->getWpfs();
			if ( $oFs ) {
				return $oFs->exists( $sFilePath );
			}
			return function_exists( 'file_exists' ) ? file_exists( $sFilePath ) : null;
		}

		/**
		 * @param string $sNeedle
		 * @param string $sDir
		 * @param boolean $fCaseSensitive
		 * @return boolean
		 */
		public function fileExistsInDir( $sNeedle, $sDir, $fCaseSensitive = true ) {
			if ( $fCaseSensitive ) {
				return $this->exists( $this->pathJoin( $sDir, $sNeedle ) );
			}
			$sNeedle = strtolower( $sNeedle );
			if ( $oHandle = opendir( $sDir ) ) {

				while ( false !== ( $sFileEntry = readdir( $oHandle ) ) ) {
					if ( !$this->isFile( $this->pathJoin( $sDir, $sFileEntry ) ) ) {
						continue;
					}
					if ( $sNeedle == strtolower( $sFileEntry ) ) {
						return true;
					}
				}
			}

			return false;
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

		public function getContent_WpConfig() {
			return $this->getFileContent( $this->m_sWpConfigPath );
		}

		/**
		 * @param string $sContent
		 * @return bool
		 */
		public function putContent_WpConfig( $sContent ) {
			return $this->putFileContent( $this->m_sWpConfigPath, $sContent );
		}

		/**
		 * @param string $sUrl
		 * @param boolean $fSecure
		 * @return boolean
		 */
		public function getIsUrlValid( $sUrl, $fSecure = false ) {
			$sSchema = $fSecure? 'https://' : 'http://';
			$sUrl = ( strpos( $sUrl, 'http' ) !== 0 )? $sSchema.$sUrl : $sUrl;
			return ( $this->getUrl( $sUrl ) != false );
		}

		/**
		 * @return string
		 */
		public function getWpConfigPath() {
			return $this->m_sWpConfigPath;
		}

		/**
		 * @param string $sUrl
		 * @return bool
		 */
		public function getUrl( $sUrl ) {
			$mResult = wp_remote_get( $sUrl );
			if ( is_wp_error( $mResult ) ) {
				return false;
			}
			if ( !isset( $mResult['response']['code'] ) || $mResult['response']['code'] != 200 ) {
				return false;
			}
			return $mResult;
		}

		/**
		 * @param string $sUrl
		 * @return bool|string
		 */
		public function getUrlContent( $sUrl ) {
			$aResponse = $this->getUrl( $sUrl );
			if ( !$aResponse || !isset( $aResponse['body'] ) ) {
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
		 * @param string $sFilePath
		 * @return int|null
		 */
		public function getModifiedTime( $sFilePath ) {
			return $this->getTime( $sFilePath, 'modified' );
		}

		/**
		 * @param string $sFilePath
		 * @return int|null
		 */
		public function getAccessedTime( $sFilePath ) {
			return $this->getTime( $sFilePath, 'accessed' );
		}

		/**
		 * @param string $sFilePath
		 * @param string $sProperty
		 * @return int|null
		 */
		public function getTime( $sFilePath, $sProperty = 'modified' ) {

			if ( !$this->exists( $sFilePath ) ) {
				return null;
			}

			$oFs = $this->getWpfs();
			switch ( $sProperty ) {

				case 'modified' :
					return $oFs? $oFs->mtime( $sFilePath ) : filemtime( $sFilePath );
					break;
				case 'accessed' :
					return $oFs? $oFs->atime( $sFilePath ) : fileatime( $sFilePath );
					break;
				default:
					return null;
					break;
			}
		}

		/**
		 * @param string $sFilePath
		 * @return NULL|boolean
		 */
		public function getCanReadWriteFile( $sFilePath ) {
			if ( !file_exists( $sFilePath ) ) {
				return null;
			}

			$nFileSize = filesize( $sFilePath );
			if ( $nFileSize === 0 ) {
				return null;
			}

			$sFileContent = $this->getFileContent( $sFilePath );
			if ( empty( $sFileContent ) ) {
				return false; //can't even read the file!
			}
			return $this->putFileContent( $sFilePath, $sFileContent );
		}

		/**
		 * @param string $sFilePath
		 * @return string|null
		 */
		public function getFileContent( $sFilePath ) {
			$sContents = null;
			$oFs = $this->getWpfs();
			if ( $oFs ) {
				$sContents = $oFs->get_contents( $sFilePath );
			}

			if ( empty( $sContents ) && function_exists( 'file_get_contents' ) ) {
				$sContents = file_get_contents( $sFilePath );
			}
			return $sContents;
		}

		/**
		 * @param string $sFilePath
		 * @param string $sContents
		 * @return boolean|null
		 */
		public function putFileContent( $sFilePath, $sContents ) {
			$oFs = $this->getWpfs();
			if ( $oFs ) {
				return $oFs->put_contents( $sFilePath, $sContents, FS_CHMOD_FILE );
			}

			if ( function_exists( 'file_put_contents' ) ) {
				return file_put_contents( $sFilePath, $sContents ) !== false;
			}
			return null;
		}

		/**
		 * @param $sFilePath
		 * @return boolean|null
		 */
		public function deleteFile( $sFilePath ) {
			$oFs = $this->getWpfs();
			if ( $oFs ) {
				return $oFs->delete( $sFilePath );
			}

			return function_exists( 'unlink' ) ? unlink( $sFilePath ) : null;
		}

		/**
		 * @param $sFilePath
		 * @return bool|mixed
		 */
		public function isFile( $sFilePath ) {
			$oFs = $this->getWpfs();
			if ( $oFs ) {
				return $oFs->is_file( $sFilePath );
			}
			return function_exists( 'is_file' ) ? is_file( $sFilePath ) : null;
		}

		/**
		 * @param string $sFilePath
		 * @param int $nTime
		 * @return bool|mixed
		 */
		public function touch( $sFilePath, $nTime ) {
			$oFs = $this->getWpfs();
			if ( $oFs ) {
				return $oFs->touch( $sFilePath, $nTime );
			}
			return function_exists( 'touch' ) ? touch( $sFilePath, $nTime ) : null;
		}

		/**
		 */
		protected function getWpfs() {
			if ( is_null( $this->oWpfs ) ) {
				$this->initFileSystem();
			}
			return $this->oWpfs;
		}

		/**
		 */
		private function initFileSystem() {
			if ( is_null( $this->oWpfs ) ) {
				require_once( ABSPATH . 'wp-admin'.ICWP_DS.'includes'.ICWP_DS.'file.php' );
				WP_Filesystem();
				global $wp_filesystem;
				if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) ) {
					$this->oWpfs = $wp_filesystem;
				}
				else {
					$this->oWpfs = false;
				}
			}
		}
	}
endif;
