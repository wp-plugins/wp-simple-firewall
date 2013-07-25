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

if ( !class_exists('ICWP_DatabaseProcessor') ):

class ICWP_DatabaseProcessor {

	/**
	 * A link to the WordPress Database object so we don't have to "global" that each time.
	 * 
	 * @var wpdb
	 */
	protected $m_oWpdb;
	
	/**
	 * The database table prefix.
	 * 
	 * @var unknown_type
	 */
	protected $m_sTablePrefix;
	
	protected $m_sLogTableName;
	
	function __construct( $insTablePrefix = 'icwp_' ) {
		global $wpdb;
		$this->m_oWpdb = $wpdb;
		$this->m_sTablePrefix = $insTablePrefix;
		$this->m_sTableName_Log = $this->m_sTablePrefix.'log';
	}

	public function createTables() {
	
		// Set up log table
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`request_id` varchar(255) NOT NULL DEFAULT '',
			`messages` text NOT NULL,
			`created_at` int(15) NOT NULL DEFAULT '0',
			`deleted_at` int(15) NOT NULL DEFAULT '0',
			`ip` varchar(20) NOT NULL DEFAULT '',
			`ip_long` bigint(20) NOT NULL DEFAULT '0',
			`uri` text NOT NULL,
			`params` text NOT NULL,
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$sSqlTables = sprintf( $sSqlTables, $this->getFullTableName( 'log' ) );
		$this->doSql( $sSqlTables );
	}
	
	public function deleteAllTables() {
		$aTables = array( 'log' );
		$sQuery = 'DROP TABLE IF EXISTS %s';
		foreach( $aTables as $sTableName ) {
			$this->m_oWpdb->query( sprintf( $sQuery, $this->getFullTableName( $sTableName ) ) );
		}
	}
	
	public function doSql( $insSql ) {
		$oResponse = $this->m_oWpdb->query( $insSql );
	}
	
	public function insertToTable( $insTableName, $aData ) {
		$this->m_oWpdb->insert( $this->getFullTableName( $insTableName ), $aData );
	}

	public function selectAllFromTable( $insTableName ) {
		$sQuery = sprintf( "SELECT * FROM `%s` WHERE `deleted_at` = '0'", $this->getFullTableName( $insTableName ) );
		return $this->m_oWpdb->get_results( $sQuery, ARRAY_A );
	}

	public function emptyTable( $insTableName ) {
		$sQuery = sprintf( "TRUNCATE TABLE `%s`", $this->getFullTableName( $insTableName ) );
		return $this->doSql( $sQuery );
	}
	
	private function getTableName( $insTableName ) {
		return $this->m_sTablePrefix . $insTableName;
	}
	
	private function getFullTableName( $insTableName ) {
		return $this->m_oWpdb->base_prefix . $this->getTableName( $insTableName );
	}
}

endif;