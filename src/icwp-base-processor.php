<?php
/**
 * Copyright (c) 2014 iControlWP <support@icontrolwp.com>
 * All rights reserved.
 * 
 * Version: 2013-08-27-B
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
 *
 */

if ( !class_exists('ICWP_BaseProcessor_V3') ):

class ICWP_BaseProcessor_V3 {

	const PcreDelimiter = '/';
	const LOG_MESSAGE_LEVEL_INFO = 0;
	const LOG_MESSAGE_LEVEL_WARNING = 1;
	const LOG_MESSAGE_LEVEL_CRITICAL = 2;

	const LOG_CATEGORY_DEFAULT = 0;
	const LOG_CATEGORY_FIREWALL = 1;
	const LOG_CATEGORY_LOGINPROTECT = 2;

	/**
	 * @var array
	 */
	protected $m_aLog;
	/**
	 * @var array
	 */
	protected $m_aLogMessages;
	
	/**
	 * @var long
	 */
	protected static $nRequestIp;

	/**
	 * @var array
	 */
	protected $aOptions;
	
	/**
	 * @var ICWP_OptionsHandler_Base_WPSF
	 */
	protected $oFeatureOptions;

	public function __construct( ICWP_OptionsHandler_Base_WPSF $oFeatureOptions ) {
		$this->oFeatureOptions = $oFeatureOptions;
		$this->reset();
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		if ( !isset( self::$nRequestIp ) ) {
			self::$nRequestIp = $this->getVisitorIpAddress();
		}
		$this->resetLog();
	}
	
	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() { }

	/**
	 */
	public function deleteStore() {
		delete_option( $this->constructStorageKey() );
	}

	/**
	 *
	 * @param array $aOptions
	 */
	public function setOptions( &$aOptions ) {
		$this->aOptions = $aOptions;
	}

	/**
	 * @param $sOptionKey
	 * @param bool $mDefault
	 * @return bool
	 */
	public function getOption( $sOptionKey, $mDefault = false ) {
		if ( !isset( $this->aOptions ) ) {
			$this->aOptions = $this->oFeatureOptions->getPluginOptionsValues();
		}
		return isset( $this->aOptions[$sOptionKey] )? $this->aOptions[$sOptionKey] : $mDefault;
	}

	/**
	 * @param $sKey
	 * @param mixed $mValueToTest
	 * @param boolean $fStrict
	 * @return bool
	 */
	public function getIsOption( $sKey, $mValueToTest, $fStrict = false ) {
		$mOptionValue = $this->getOption($sKey);
		return $fStrict? $mOptionValue === $mValueToTest : $mOptionValue == $mValueToTest;
	}

	/**
	 * Resets the log
	 */
	public function resetLog() {
		$this->m_aLogMessages = array();
	}

	/**
	 * @return bool
	 */
	public function getIsLogging() {
		return false;
	}

	/**
	 * Should return false when logging is disabled.
	 *
	 * @return false|array	- false when logging is disabled, array with log data otherwise
	 * @see ICWP_WPSF_BaseProcessor::getLogData()
	 */
	public function flushLogData() {
		return false;
		if ( !$this->getIsLogging() ) {
			return false;
		}
		return false;
	}
	
	/**
	 * Builds and returns the full log.
	 * 
	 * @return array (associative)
	 */
	public function getLogData() {
		
		if ( $this->getIsLogging() ) {
			$this->m_aLog = array( 'messages'			=> serialize( $this->m_aLogMessages ) );
		}
		else {
			$this->m_aLog = false;
		}
		
		return $this->m_aLog;
	}

	/**
	 * @return array
	 */
	public function getLogMessages() {
		if ( !is_array( $this->m_aLogMessages ) ) {
			$this->m_aLogMessages = array();
		}
		return $this->m_aLogMessages;
	}
	
	/**
	 * @param string $sLogMessage
	 * @param integer $sMessageType
	 */
	public function writeLog( $sLogMessage = '', $sMessageType = self::LOG_MESSAGE_LEVEL_INFO ) {
		if ( !is_array( $this->m_aLogMessages ) ) {
			$this->resetLog();
		}
		$this->m_aLogMessages[] = array( $sMessageType, $sLogMessage );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logInfo( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_LEVEL_INFO );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logWarning( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_LEVEL_WARNING );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logCritical( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_LEVEL_CRITICAL );
	}

	/**
	 * Cloudflare compatible.
	 * 
	 * @param boolean $infAsLong - visitor IP Address as IP2Long
	 * @return integer - visitor IP Address as IP2Long
	 */
	public function getVisitorIpAddress( $infAsLong = true ) {
		require_once( dirname(__FILE__).'/icwp-data-processor.php' );
		return ICWP_WPSF_DataProcessor::GetVisitorIpAddress( $infAsLong );
	}

	/**
	 * @param array $inaIpList
	 * @param integer $innIpAddress
	 * @return boolean
	 */
	public function isIpOnlist( $inaIpList, $innIpAddress = '', &$outsLabel = '' ) {

		if ( empty( $innIpAddress ) || !isset( $inaIpList['ips'] ) ) {
			return false;
		}
	
		$outsLabel = '';
		foreach( $inaIpList['ips'] as $mWhitelistAddress ) {
			
			$aIps = $this->parseIpAddress( $mWhitelistAddress );
			if ( count( $aIps ) === 1 ) { //not a range
				if ( $innIpAddress == $aIps[0] ) {
					$outsLabel = $inaIpList['meta'][ md5( $mWhitelistAddress ) ];
					return true;
				}
			}
			else if ( count( $aIps ) == 2 ) {
				if ( $aIps[0] <= $innIpAddress && $innIpAddress <= $aIps[1] ) {
					$outsLabel = $inaIpList['meta'][ md5( $mWhitelistAddress ) ];
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * @param string $insIpAddress	- an IP or IP address range in LONG format.
	 * @return array				- with 1 ip address, or 2 addresses if it is a range.
	 */
	protected function parseIpAddress( $insIpAddress ) {
		
		$aIps = array();
		
		if ( empty($insIpAddress) ) {
			return $aIps;
		}
		
		// offset=1 in the case that it's a range and the first number is negative on 32-bit systems
		$mPos = strpos( $insIpAddress, '-', 1 );

		if ( $mPos === false ) { //plain IP address
			$aIps[] = $insIpAddress;
		}
		else {
			//we remove the first character in case this is '-'
			$aParts = array( substr( $insIpAddress, 0, 1 ), substr( $insIpAddress, 1 ) );
			list( $sStart, $sEnd ) = explode( '-', $aParts[1], 2 );
			$aIps[] = $aParts[0].$sStart;
			$aIps[] = $sEnd;
		}
		return $aIps;
	}
	
	/**
	 * @return ICWP_WPSF_EmailProcessor
	 */
	public function getEmailProcessor() {
		return $this->oFeatureOptions->getEmailProcessor();
	}

	/**
	 * @return ICWP_WPSF_LoggingProcessor
	 */
	public function getLoggingProcessor() {
		return $this->oFeatureOptions->getLoggingProcessor();
	}

	/**
	 * Checks the $inaData contains valid key values as laid out in $inaChecks
	 *
	 * @param array $aData
	 * @param array $inaChecks
	 * @return boolean
	 */
	protected function validateParameters( $aData, $inaChecks ) {
	
		if ( !is_array( $aData ) ) {
			return false;
		}
	
		foreach( $inaChecks as $sCheck ) {
			if ( !array_key_exists( $sCheck, $aData ) || empty( $aData[ $sCheck ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return string
	 */
	protected function constructStorageKey() {
		return sprintf( '%s%s_processor', $this->oFeatureOptions->getOptionStoragePrefix(), $this->oFeatureOptions->getFeatureSlug() );
	}
	
	/**
	 * Override this to provide custom cleanup.
	 */
	public function deleteAndCleanUp() {
		$this->deleteStore();
	}

	/**
	 */
	protected function loadDataProcessor() {
		require_once( dirname(__FILE__) . '/icwp-data-processor.php' );
	}

	/**
	 * @return ICWP_WpFilesystem_WPSF
	 */
	protected function loadFileSystemProcessor() {
		require_once( dirname(__FILE__) . '/icwp-wpfilesystem.php' );
		return ICWP_WpFilesystem_WPSF::GetInstance();
	}

	/**
	 * @return ICWP_WpFunctions_WPSF
	 */
	protected function loadWpFunctionsProcessor() {
		require_once( dirname(__FILE__) . '/icwp-wpfunctions.php' );
		return ICWP_WpFunctions_WPSF::GetInstance();
	}

	/**
	 * @return ICWP_Stats_WPSF
	 */
	protected function loadWpsfStatsProcessor() {
		require_once( dirname(__FILE__) . '/icwp-wpsf-stats.php' );
	}

	/**
	 * @param $sStatKey
	 */
	protected function doStatIncrement( $sStatKey ) {
		$this->loadWpsfStatsProcessor();
		ICWP_Stats_WPSF::DoStatIncrement( $sStatKey );
	}
}

endif;

if ( !class_exists('ICWP_WPSF_BaseProcessor') ):
	class ICWP_WPSF_BaseProcessor extends ICWP_BaseProcessor_V3 { }
endif;