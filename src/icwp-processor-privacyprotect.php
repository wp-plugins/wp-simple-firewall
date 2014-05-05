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

if ( !class_exists('ICWP_PrivacyProtectProcessor_V1') ):

class ICWP_PrivacyProtectProcessor_V1 extends ICWP_BaseDbProcessor_WPSF {

	const Slug = 'privacy_protect';

	public function __construct( $insOptionPrefix = '' ) {
		parent::__construct( $this->constructStorageKey( $insOptionPrefix, self::Slug ), self::Slug );
		$this->createTable();
		$this->reset();
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function reset() {
		parent::reset();
		$this->m_sUniqueToken = '';
		$this->m_sCommentStatus = '';
	}
	
	/**
	 */
	public function run() {
		parent::run();

		if ( $this->m_aOptions[ 'enable_privacy_protect' ] == 'Y' ) {
			add_action( 'http_api_debug',			array( $this, 'logHttpRequest' ), 1000, 5 );
		}
	}

	public function logHttpRequest( $oHttpResponse, $sResponse, $sCallingClass, $aRequestArgs, $sRequestUrl ) {
		// Now add new pending entry
		$nNow = time();
		$aData = array();
		$aData[ 'request_url' ]		= $sRequestUrl;
		$aData[ 'request_method' ]	= $aRequestArgs['method'];
		$aData[ 'is_ssl' ]			= strpos( $sRequestUrl, 'https' ) === 0? 1 : 0;
		$aData[ 'is_error' ]		= is_wp_error( $oHttpResponse )? 1 : 0;
		$aData[ 'request_args' ]	= serialize( $aRequestArgs );
		$aData[ 'requested_at' ]	= $nNow;

		$mResult = $this->insertIntoTable( $aData );
		return $mResult;
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

	public function createTable() {
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`request_url` varchar(255) NOT NULL DEFAULT '',
			`request_port` mediumint(5) UNSIGNED NOT NULL DEFAULT 80,
			`request_method` varchar(4) NOT NULL DEFAULT 'GET',
			`request_args` text NOT NULL DEFAULT '',
			`is_ssl` tinyint(1) NOT NULL DEFAULT 0,
			`is_error` tinyint(1) NOT NULL DEFAULT 0,
			`requested_at` int(15) NOT NULL DEFAULT 0,
			`deleted_at` int(15) NOT NULL DEFAULT 0,
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$sSqlTables = sprintf( $sSqlTables, $this->m_sTableName );
		$mResult = $this->doSql( $sSqlTables );
	}
}

endif;

if ( !class_exists('ICWP_WPSF_PrivacyProtectProcessor') ):
	class ICWP_WPSF_PrivacyProtectProcessor extends ICWP_PrivacyProtectProcessor_V1 { }
endif;