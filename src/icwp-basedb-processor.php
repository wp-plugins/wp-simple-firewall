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

if ( !class_exists('ICWP_BaseDbProcessor') ):

class ICWP_BaseDbProcessor extends ICWP_BaseProcessor {
	
	const DB_TABLE_PREFIX	= 'icwp_';

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
	
	public function __construct( $insTableName ) {
		$this->reset();
		$this->setTableName( $insTableName );
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		global $wpdb;
		$this->m_oWpdb = $wpdb;
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

	/**
	 * Will remove all data from this table (to delete the table see dropTable)
	 */
	public function emptyTable() {
		$sQuery = sprintf( "TRUNCATE TABLE `%s`", $this->m_sTableName );
		return $this->doSql( $sQuery );
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
	 * Ensure that when we save the object later, it doesn't save unnecessary data.
	 */
	public function doPreSave() {
		unset( $this->m_oWpdb );
	}
}

endif;