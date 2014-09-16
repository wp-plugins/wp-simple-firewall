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
 *
 */

require_once( dirname(__FILE__).'/icwp-base-processor.php' );

if ( !class_exists('ICWP_WPSF_BaseDbProcessor') ):

class ICWP_WPSF_BaseDbProcessor extends ICWP_WPSF_Processor_Base {
	
	const DB_TABLE_PREFIX	= 'icwp_';

	/**
	 */
	const CleanupCronActionHook = 'icwp_wpsf_cron_cleanupactionhook';

	/**
	 * A link to the WordPress Database object so we don't have to "global" that every time.
	 * @var wpdb
	 */
	protected $oWpdb;

	/**
	 * The full database table name.
	 * @var string
	 */
	protected $sFullTableName;
	/**
	 * @var array 
	 */
	protected $m_aDataToWrite;
	
	public function __construct( ICWP_WPSF_FeatureHandler_Base $oFeatureOptions, $sTableName = null ) {
		parent::__construct( $oFeatureOptions );
		$this->setTableName( $sTableName );
		$this->createCleanupCron();
		add_action( $this->oFeatureOptions->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteDatabase' )  );
	}

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		if ( $this->getTableExists() ) {
			$sFullHookName = $this->oFeatureOptions->doPluginPrefix( self::CleanupCronActionHook, '_' );
			add_action( $sFullHookName, array( $this, 'cleanupDatabase' ) );
		}
		else {
			$this->createTable();
		}
	}

	/**
	 */
	public function deleteDatabase() {
		if ( apply_filters( $this->oFeatureOptions->doPluginPrefix( 'has_permission_to_submit' ), true ) && $this->getTableExists() ) {
			$this->dropTable();
		}
	}
	
	/**
	 * Loads our WPDB object if required.
	 *
	 * @return wpdb
	 */
	protected function loadWpdb() {
		if ( is_null( $this->oWpdb ) ) {
			global $wpdb;
			$this->oWpdb = $wpdb;
		}
		return $this->oWpdb;
	}
	
	/**
	 * @param array $inaLogData
	 * @return type
	 */
	public function addDataToWrite( $inaLogData ) {
		if ( empty( $inaLogData ) ) {
			return;
		}
		if ( empty( $this->m_aDataToWrite ) ) {
			$this->m_aDataToWrite = array();
		}
		$this->m_aDataToWrite[] = $this->completeDataForWrite( $inaLogData );
	}
	
	/**
	 * Ensures the data provided for writing to the db meets all the requirements.
	 * 
	 * This should be overridden per implementation
	 * 
	 * @param array $aLogData
	 * @return array
	 */
	protected function completeDataForWrite( $aLogData ) {
		if ( is_null( $aLogData ) ) {
			return array();
		}
		return $aLogData;
	}
	
	/**
	 * @return boolean - whether the write to the DB was successful.
	 */
	public function commitData() {
		if ( empty( $this->m_aDataToWrite ) ) {
			return;
		}
		$fSuccess = true;
		foreach( $this->m_aDataToWrite as $aDataEntry ) {
			if ( empty( $aDataEntry ) ) {
				continue;
			}
			$fSuccess = $fSuccess && $this->insertIntoTable( $aDataEntry );
		}
		if ( $fSuccess ) {
			$this->flushData();
		}
		return $fSuccess;
	}
	
	/**
	 */
	protected function flushData() {
		$this->m_aDataToWrite = null;
	}

	/**
	 * @param $aData
	 * @return boolean
	 */
	public function insertIntoTable( $aData ) {
		$oDb = $this->loadWpdb();
		return $oDb->insert( $this->getTableName(), $aData );
	}

	/**
	 * @param $nFormat
	 * @return array|boolean
	 */
	public function selectAllFromTable( $nFormat = ARRAY_A ) {
		$oDb = $this->loadWpdb();
		$sQuery = sprintf( "SELECT * FROM `%s` WHERE `deleted_at` = '0'", $this->getTableName() );
		return $oDb->get_results( $sQuery, $nFormat );
	}

	/**
	 * @param string $sQuery
	 * @param $nFormat
	 * @return array|boolean
	 */
	public function selectCustomFromTable( $sQuery, $nFormat = ARRAY_A ) {
		$oDb = $this->loadWpdb();
		return $oDb->get_results( $sQuery, $nFormat );
	}

	/**
	 * @param string $sQuery
	 * @param $nFormat
	 * @return array|boolean
	 */
	public function selectRowFromTable( $sQuery, $nFormat = ARRAY_A ) {
		$oDb = $this->loadWpdb();
		return $oDb->get_row( $sQuery, $nFormat );
	}

	/**
	 * @param array $aData - new insert data (associative array, column=>data)
	 * @param array $aWhere - insert where (associative array)
	 * @return integer|boolean (number of rows affected)
	 */
	public function updateRowsFromTable( $aData, $aWhere ) {
		$oDb = $this->loadWpdb();
		return $oDb->update( $this->getTableName(), $aData, $aWhere );
	}

	/**
	 * @param array $aWhere - delete where (associative array)
	 * @return integer|boolean (number of rows affected)
	 */
	public function deleteRowsFromTable( $aWhere ) {
		$oDb = $this->loadWpdb();
		return $oDb->delete( $this->getTableName(), $aWhere );
	}

	/**
	 * @param integer $nTime
	 */
	protected function deleteAllRowsOlderThan( $nTime ) {
		$sQuery = "
			DELETE from `%s`
			WHERE
				`created_at`		< '%s'
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			$nTime
		);
		return $this->doSql( $sQuery );
	}

	/**
	 * @return bool|int
	 */
	public function createTable() {
		$sSql = $this->getCreateTableSql();
		if ( !empty( $sSql ) ) {
			return $this->doSql( $sSql );
		}
		return true;
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return '';
	}
	
	/**
	 * Will remove all data from this table (to delete the table see dropTable)
	 */
	public function emptyTable() {
		$sQuery = sprintf( "TRUNCATE TABLE `%s`", $this->getTableName() );
		return $this->doSql( $sQuery );
	}

	/**
	 * Will recreate the whole table
	 */
	public function recreateTable() {
		$this->dropTable();
		$this->createTable();
	}
	
	/**
	 * Will completely remove this table from the database
	 */
	public function dropTable() {
		$sQuery = sprintf( 'DROP TABLE IF EXISTS `%s`', $this->getTableName() ) ;
		return $this->doSql( $sQuery );
	}

	/**
	 * Given any SQL query, will perform it using the WordPress database object.
	 * 
	 * @param string $sSqlQuery
	 * @return integer|boolean (number of rows affected or just true/false)
	 */
	public function doSql( $sSqlQuery ) {
		$oDb = $this->loadWpdb();
		$mResult = $oDb->query( $sSqlQuery );
		return $mResult;
	}

	/**
	 * @return string
	 */
	protected function getTableName() {
		if ( empty( $this->sFullTableName ) ) {
			return $this->setTableName();
		}
		return $this->sFullTableName;
	}

	/**
	 * @param string $sTableName
	 * @return string
	 * @throws Exception
	 */
	private function setTableName( $sTableName = '' ) {
		if ( empty( $sTableName ) ) {
			throw new Exception( 'Database Table Name is EMPTY' );
		}
		$oDb = $this->loadWpdb();
		$sTableString =
			$oDb->prefix
			. $sTableName;
		$this->sFullTableName = esc_sql( $sTableString );
		return $this->sFullTableName;
	}

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function createCleanupCron() {
		$sFullHookName = $this->oFeatureOptions->doPluginPrefix( self::CleanupCronActionHook, '_' );
		if ( ! wp_next_scheduled( $sFullHookName ) && ! defined( 'WP_INSTALLING' ) ) {
			$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			wp_schedule_event( $nNextRun, 'daily', $sFullHookName );
		}
	}

	// by default does nothing - override this method
	public function cleanupDatabase() { }

	/**
	 * @return bool
	 */
	public function getTableExists() {
		$oDb = $this->loadWpdb();
		$sQuery = "
			SHOW TABLES LIKE '%s'
		";
		$sQuery = sprintf( $sQuery, $this->getTableName() );
		$mResult = $oDb->get_var( $sQuery );
		return !is_null( $mResult );
	}
}

endif;