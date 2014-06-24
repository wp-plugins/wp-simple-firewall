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

if ( !class_exists('ICWP_BaseDbProcessor_WPSF') ):

class ICWP_BaseDbProcessor_WPSF extends ICWP_WPSF_BaseProcessor {
	
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
	
	public function __construct( ICWP_OptionsHandler_Base_WPSF $oFeatureOptions, $sTableName = null ) {
		parent::__construct( $oFeatureOptions );
		$this->setTableName( $sTableName );
		$this->createCleanupCron();
	}

	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		if ( $this->getTableExists() ) {
			add_action( self::CleanupCronActionHook, array( $this, 'cleanupDatabase' ) );
		}
	}
	
	/**
	 * Loads our WPDB object if required.
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
	 * 
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
	
	public function selectAllFromTable( $innFormat = ARRAY_A ) {
		$oDb = $this->loadWpdb();
		$sQuery = sprintf( "SELECT * FROM `%s` WHERE `deleted_at` = '0'", $this->getTableName() );
		return $oDb->get_results( $sQuery, $innFormat );
	}
	
	public function selectCustomFromTable( $insQuery ) {
		$oDb = $this->loadWpdb();
		return $oDb->get_results( $insQuery, ARRAY_A );
	}
	
	public function selectRowFromTable( $insQuery ) {
		$oDb = $this->loadWpdb();
		return $oDb->get_row( $insQuery, ARRAY_A );
	}
	
	public function updateRowsFromTable( $inaData, $inaWhere ) {
		$oDb = $this->loadWpdb();
		return $oDb->update( $this->getTableName(), $inaData, $inaWhere );
	}
	
	public function deleteRowsFromTable( $inaWhere ) {
		$oDb = $this->loadWpdb();
		return $oDb->delete( $this->getTableName(), $inaWhere );
	}
	
	protected function deleteAllRowsOlderThan( $innTimeStamp ) {
		$sQuery = "
			DELETE from `%s`
			WHERE
				`created_at`		< '%s'
		";
		$sQuery = sprintf( $sQuery,
				$this->getTableName(),
				$innTimeStamp
		);
		$this->doSql( $sQuery );
	}

	public function createTable() {
		//Override this function to create the Table you want.
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
	 * @param string $insSql
	 */
	public function doSql( $insSql ) {
		$oDb = $this->loadWpdb();
		$fResult = $oDb->query( $insSql );
		return $fResult;
	}
	
	private function setTableName( $sTableName = null ) {
		$oDb = $this->loadWpdb();
		$this->sFullTableName = esc_sql(
			$oDb->prefix
			. self::DB_TABLE_PREFIX
			. is_null( $sTableName ) ? $this->oFeatureOptions->getFeatureSlug() : $sTableName
		);
		return $this->sFullTableName;
	}

	protected function getTableName() {
		if ( empty( $this->sFullTableName ) ) {
			return $this->setTableName();
		}
		return $this->sFullTableName;
	}

	/**
	 * Override this to provide custom cleanup.
	 */
	public function deleteAndCleanUp() {
		parent::deleteAndCleanUp();
		$this->dropTable();
	}

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function createCleanupCron() {
		if ( ! wp_next_scheduled( self::CleanupCronActionHook ) && ! defined( 'WP_INSTALLING' ) ) {
			$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			wp_schedule_event( $nNextRun, 'daily', self::CleanupCronActionHook );
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