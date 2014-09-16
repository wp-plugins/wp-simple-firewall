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

require_once( dirname(__FILE__).'/icwp-basedb-processor.php' );

if ( !class_exists('ICWP_LoggingProcessor_V1') ):

class ICWP_LoggingProcessor_V1 extends ICWP_WPSF_BaseDbProcessor {
	
	const DaysToKeepLog = 7;

	protected $sVisitorRequestId;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Logging $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Logging $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getGeneralLoggingTableName() );
	}

	public function reset() {
		parent::reset();
		$this->m_sRequestId = uniqid();
	}

	/**
	 * @param boolean $infReverseOrder
	 * @return array - numerical array of all log data entries.
	 */
	public function getLogs( $infReverseOrder = false ) {
		$aLogData = $this->selectAllFromTable();
		if ( $infReverseOrder && $aLogData && is_array( $aLogData ) ) {
			$aLogData = array_reverse( $aLogData );
		}
		return $aLogData;		
	}
	
	/**
	 * Ensures the log data provided has all the necessary data points to be written to the DB
	 * 
	 * @param array $inaLogData
	 * @return array
	 */
	protected function completeDataForWrite( $inaLogData ) {
		
		if ( !isset( $inaLogData['category'] ) ) {
			$inaLogData['category'] = self::LOG_CATEGORY_DEFAULT;
		}
		if ( !isset( $inaLogData['request_id'] ) ) {
			$inaLogData['request_id'] = $this->sVisitorRequestId;
		}
		if ( !isset( $inaLogData['ip'] ) ) {
			$inaLogData['ip'] = self::$nRequestIp;
		}
		if ( !isset( $inaLogData['ip_long'] ) ) {
			$inaLogData['ip_long'] = ip2long( self::$nRequestIp );
		}
		if ( !isset( $inaLogData['created_at'] ) ) {
			$inaLogData['created_at'] = self::$nRequestTimestamp;
		}
		return $inaLogData;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		// Set up log table
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`request_id` varchar(255) NOT NULL DEFAULT '',
			`category` int(5) NOT NULL DEFAULT '0',
			`messages` text NOT NULL,
			`ip` varchar(20) NOT NULL DEFAULT '',
			`ip_long` bigint(20) NOT NULL DEFAULT '0',
			`created_at` int(15) NOT NULL DEFAULT '0',
			`deleted_at` int(15) NOT NULL DEFAULT '0',
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		return sprintf( $sSqlTables, $this->getTableName() );
	}
	
	public function handleInstallUpgrade( $insCurrentVersion = '' ) {
		if ( version_compare( $insCurrentVersion, '1.3.0', '<' ) ) {
			// full delete of the log and recreate
			$this->recreateTable();
		}
	}

	/**
	 * This is hooked into a cron in the base class and overrides the parent method.
	 *
	 * It'll delete everything older than 7 days.
	 */
	public function cleanupDatabase() {
		if ( !$this->getTableExists() ) {
			return;
		}
		$nTimeStamp = self::$nRequestTimestamp - DAY_IN_SECONDS * self::DaysToKeepLog;
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Logging') ):
	class ICWP_WPSF_Processor_Logging extends ICWP_LoggingProcessor_V1 { }
endif;