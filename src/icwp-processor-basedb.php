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

require_once( dirname(__FILE__).'/icwp-processor-base.php' );

if ( !class_exists('ICWP_WPSF_BaseDbProcessor') ):

abstract class ICWP_WPSF_BaseDbProcessor extends ICWP_WPSF_Processor_Base {
	
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
	 * @var boolean
	 */
	protected $fTableExists;

	/**
	 * @param ICWP_WPSF_FeatureHandler_Base $oFeatureOptions
	 * @param null $sTableName
	 *
	 * @throws Exception
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Base $oFeatureOptions, $sTableName = null ) {
		parent::__construct( $oFeatureOptions );
		$this->setTableName( $sTableName );
		$this->createCleanupCron();
		$this->initializeTable();
		add_action( $this->getFeatureOptions()->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteDatabase' )  );
	}

	/**
	 */
	public function deleteDatabase() {
		if ( apply_filters( $this->getFeatureOptions()->doPluginPrefix( 'has_permission_to_submit' ), true ) && $this->getTableExists() ) {
			$this->deleteCleanupCron();
			$this->dropTable();
		}
	}

	/**
	 * @return bool|int
	 */
	protected function createTable() {
		$sSql = $this->getCreateTableSql();
		if ( !empty( $sSql ) ) {
			return $this->loadDbProcessor()->doSql( $sSql );
		}
		return true;
	}

	/**
	 */
	protected function initializeTable() {
		if ( $this->getTableExists() ) {
			$this->recreateTable();
			$sFullHookName = $this->getDbCleanupHookName();
			add_action( $sFullHookName, array( $this, 'cleanupDatabase' ) );
		}
		else {
			$this->createTable();
		}
	}

	/**
	 * @param array $aData
	 *
	 * @return bool|int
	 */
	public function insertData( $aData ) {
		return $this->loadDbProcessor()->insertDataIntoTable( $this->getTableName(), $aData );
	}

	/**
	 * @param boolean $fIncludeDeleted
	 * @param $nFormat
	 *
	 * @return array|boolean
	 */
	public function selectAllRows( $fIncludeDeleted = false, $nFormat = ARRAY_A ) {
		$sQuery = sprintf( "SELECT * FROM `%s` WHERE %s ( `deleted_at` = '0' )", $this->getTableName(), $fIncludeDeleted ? 'NOT' : '' );
		return $this->selectCustom( $sQuery, $nFormat );
	}

	/**
	 * @param string $sQuery
	 * @param $nFormat
	 * @return array|boolean
	 */
	public function selectCustom( $sQuery, $nFormat = ARRAY_A ) {
		return $this->loadDbProcessor()->selectCustom( $sQuery, $nFormat );
	}

	/**
	 * @param array $aData - new insert data (associative array, column=>data)
	 * @param array $aWhere - insert where (associative array)
	 * @return integer|boolean (number of rows affected)
	 */
	public function updateRowsWhere( $aData, $aWhere ) {
		return $this->loadDbProcessor()->updateRowsFromTableWhere( $this->getTableName(), $aData, $aWhere );
	}

	/**
	 * @param array $aWhere - delete where (associative array)
	 *
	 * @return integer|boolean (number of rows affected)
	 */
	public function deleteRowsWhere( $aWhere ) {
		return $this->loadDbProcessor()->deleteRowsFromTableWhere( $this->getTableName(), $aWhere );
	}

	/**
	 * @param integer $nTimeStamp
	 * @return bool|int
	 */
	protected function deleteAllRowsOlderThan( $nTimeStamp ) {
		$sQuery = "
			DELETE from `%s`
			WHERE
				`created_at` < '%s'
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			esc_sql( $nTimeStamp )
		);
		return $this->loadDbProcessor()->doSql( $sQuery );
	}

	/**
	 * @return string
	 */
	abstract protected function getCreateTableSql();
	
	/**
	 * Will remove all data from this table (to delete the table see dropTable)
	 */
	public function emptyTable() {
		return $this->loadDbProcessor()->doEmptyTable( $this->getTableName() );
	}

	/**
	 * Will recreate the whole table
	 */
	public function recreateTable() {
		if ( $this->getFeatureOptions()->getOpt( 'recreate_database_table', false ) ) {
			$this->getFeatureOptions()->setOpt( 'recreate_database_table', false );
			$this->getFeatureOptions()->savePluginOptions();
			$this->dropTable();
			$this->createTable();
		}
	}
	
	/**
	 * Will completely remove this table from the database
	 */
	public function dropTable() {
		return $this->loadDbProcessor()->doDropTable( $this->getTableName() );
	}

	/**
	 * Given any SQL query, will perform it using the WordPress database object.
	 * 
	 * @param string $sSqlQuery
	 *
	 * @return integer|boolean (number of rows affected or just true/false)
	 */
	public function doSql( $sSqlQuery ) {
		$mResult = $this->loadDbProcessor()->doSql( $sSqlQuery );
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
		$this->sFullTableName = $this->loadDbProcessor()->getPrefix() . esc_sql( $sTableName );
		return $this->sFullTableName;
	}

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function createCleanupCron() {
		$sFullHookName = $this->getDbCleanupHookName();
		if ( ! wp_next_scheduled( $sFullHookName ) && ! defined( 'WP_INSTALLING' ) ) {
			$nNextRun = strtotime( 'tomorrow 6am' ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			wp_schedule_event( $nNextRun, 'daily', $sFullHookName );
		}
	}

	/**
	 * Will setup the cleanup cron to clean out old entries. This should be overridden per implementation.
	 */
	protected function deleteCleanupCron() {
		wp_clear_scheduled_hook( $this->getDbCleanupHookName() );
	}

	/**
	 * @return string
	 */
	protected function getDbCleanupHookName() {
		return $this->getController()->doPluginPrefix( $this->getFeatureOptions()->getFeatureSlug().'_db_cleanup' );
	}

	// by default does nothing - override this method
	public function cleanupDatabase() { }

	/**
	 * @return bool
	 */
	public function getTableExists() {

		// only return true if this is true.
		if ( $this->fTableExists === true ) {
			return true;
		}

		$this->fTableExists = $this->loadDbProcessor()->getIfTableExists( $this->getTableName() );
		return $this->fTableExists;
	}
}

endif;