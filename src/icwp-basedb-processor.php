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
	protected $m_oWpdb;

	/**
	 * The full database table name.
	 * @var string
	 */
	protected $m_sTableName;
	/**
	 * @var array 
	 */
	protected $m_aDataToWrite;
	
	public function __construct( $insStorageKey, $insTableName ) {
		parent::__construct( $insStorageKey );
		$this->reset();
		$this->setTableName( $insTableName );
		$this->createCleanupCron();
	}

	/**
	 * Ensure that when we save the object later, it doesn't save unnecessary data.
	 */
	public function doPreStore() {
		parent::doPreStore();
		$this->commitData();
		unset( $this->m_oWpdb );
	}
	
	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->loadWpdb();
	}
	
	/**
	 * Override to set what this processor does when it's "run"
	 */
	public function run() {
		add_action( self::CleanupCronActionHook, array( $this, 'cleanupDatabase' ) );
	}
	
	/**
	 * Loads our WPDB object if required.
	 */
	protected function loadWpdb() {
		if ( !is_null( $this->m_oWpdb ) ) {
			return;
		}
		global $wpdb;
		$this->m_oWpdb = $wpdb;
	}
	
	/**
	 * @param array $inaLogData
	 * @return type
	 */
	public function addDataToWrite( $inaLogData ) {
		if ( empty( $inaLogData ) || empty( $inaLogData['messages'] ) ) {
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
	 * @return array
	 */
	protected function completeDataForWrite( $inaLogData ) {
		if ( is_null( $inaLogData ) ) {
			return array();
		}
		return $inaLogData;
	}
	
	/**
	 * @return boolean - whether the write to the DB was successful.
	 */
	public function commitData() {
		if ( empty( $this->m_aDataToWrite ) ) {
			return;
		}
		$this->loadWpdb();
		$fSuccess = true;
		foreach( $this->m_aDataToWrite as $aDataEntry ) {
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
	
	public function insertIntoTable( $inaData ) {
		return $this->m_oWpdb->insert( $this->m_sTableName, $inaData );
	}
	
	public function selectAllFromTable( $innFormat = ARRAY_A ) {
		$sQuery = sprintf( "SELECT * FROM `%s` WHERE `deleted_at` = '0'", $this->m_sTableName );
		return $this->m_oWpdb->get_results( $sQuery, $innFormat );
	}
	
	public function selectCustomFromTable( $insQuery ) {
		return $this->m_oWpdb->get_results( $insQuery, ARRAY_A );
	}
	
	public function selectRowFromTable( $insQuery ) {
		return $this->m_oWpdb->get_row( $insQuery, ARRAY_A );
	}
	
	public function updateRowsFromTable( $inaData, $inaWhere ) {
		return $this->m_oWpdb->update( $this->m_sTableName, $inaData, $inaWhere );
	}
	
	public function deleteRowsFromTable( $inaWhere ) {
		return $this->m_oWpdb->delete( $this->m_sTableName, $inaWhere );
	}
	
	protected function deleteAllRowsOlderThan( $innTimeStamp ) {
		$sQuery = "
			DELETE from `%s`
			WHERE
				`created_at`		< '%s'
		";
		$sQuery = sprintf( $sQuery,
				$this->m_sTableName,
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
		$sQuery = sprintf( "TRUNCATE TABLE `%s`", $this->m_sTableName );
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
		$sQuery = sprintf( 'DROP TABLE IF EXISTS `%s`', $this->m_sTableName ) ;
		return $this->doSql( $sQuery );
	}

	/**
	 * Given any SQL query, will perform it using the WordPress database object.
	 * 
	 * @param string $insSql
	 */
	public function doSql( $insSql ) {
		return $this->m_oWpdb->query( $insSql );
	}
	
	private function setTableName( $insTableName ) {
		return $this->m_sTableName = $this->m_oWpdb->base_prefix . self::DB_TABLE_PREFIX . $insTableName;
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
	
	public function cleanupDatabase() {
		//by default do nothing - oiverrde this method
	}
	
}

endif;