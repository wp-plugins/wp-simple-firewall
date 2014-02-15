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

require_once( dirname(__FILE__).'/icwp-basedb-processor.php' );

if ( !class_exists('ICWP_LoggingProcessor_V1') ):

class ICWP_LoggingProcessor_V1 extends ICWP_BaseDbProcessor_WPSF {
	
	const Slug = 'logging';
	const TableName = 'wpsf_log';
	const DaysToKeepLog = 7;

	protected $m_sRequestId;

	public function __construct( $insOptionPrefix = '' ) {
		parent::__construct( $this->constructStorageKey( $insOptionPrefix, self::Slug ), self::TableName );
		$this->createTable();
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
			$inaLogData['request_id'] = $this->m_sRequestId;
		}
		if ( !isset( $inaLogData['ip'] ) ) {
			$inaLogData['ip'] = self::GetVisitorIpAddress( false );
		}
		if ( !isset( $inaLogData['ip_long'] ) ) {
			$inaLogData['ip_long'] = ip2long( $inaLogData['ip'] );
		}
		if ( !isset( $inaLogData['created_at'] ) ) {
			$inaLogData['created_at'] = time();
		}
		return $inaLogData;
	}
	
	public function createTable() {
	
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
		$sSqlTables = sprintf( $sSqlTables, $this->m_sTableName );
		return $this->doSql( $sSqlTables );
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
		$nTimeStamp = time() - DAY_IN_SECONDS * self::DaysToKeepLog;
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}

endif;

if ( !class_exists('ICWP_WPSF_LoggingProcessor') ):
	class ICWP_WPSF_LoggingProcessor extends ICWP_LoggingProcessor_V1 { }
endif;