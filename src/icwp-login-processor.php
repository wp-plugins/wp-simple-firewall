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

if ( !class_exists('ICWP_LoginProcessor') ):

class ICWP_LoginProcessor extends ICWP_BaseDbProcessor {
	
	public function __construct( $insTableName ) {
		parent::__construct( $insTableName );
	}
	
	/**
	 * @param array $inaData
	 * @return boolean
	 */
	public function loginAuthAddPending( $inaData ) {
		
		$aChecks = array( 'wp_username' );
		if ( !$this->validateInputData( $inaData, $aChecks) ) {
			return false;
		}
		
		$sNow = time();
		
		// First set any other pending entries for the given user to be deleted.
		$aOldData = array(
			'deleted_at'	=> $sNow,
			'expired_at'	=> $sNow,
		);
		$aOldWhere = array(
			'pending'		=> 1,
			'deleted_at'	=> 0,
			'wp_username'	=> $inaData[ 'wp_username' ]
		);
		$this->updateRowsFromTable( $aOldData, $aOldWhere );

		// Now add new pending entry
		$inaData[ 'unique_id' ]		= uniqid();
		$inaData[ 'ip' ]			= self::GetVisitorIpAddress( false );
		$inaData[ 'ip_long' ]		= ip2long( $inaData[ 'ip' ] );
		$inaData[ 'pending' ]		= 1;
		$inaData[ 'created_at' ]	= time();
		
		if ( $this->insertIntoTable( $inaData ) === false ) {
			return false;
		}
		else {
			return $inaData;
		}
	}
	
	/**
	 * Given a unique Id and a corresponding WordPress username, will update the authentication table so that it is active (pending=0).
	 * 
	 * @param array $inaWhere - unique_id, wp_username
	 * @return boolean
	 */
	public function loginAuthMakeActive( $inaWhere ) {
		
		$aChecks = array( 'unique_id', 'wp_username' );
		if ( !$this->validateInputData( $inaWhere, $aChecks) ) {
			return false;
		}
		
		$sNow = time();
		
		// First set any active, non-pending entries for the given user to be deleted.
		$sQuery = "
			UPDATE `%s`
			SET `deleted_at`	= '%s',
				`expired_at`	= '%s'
			WHERE
				`wp_username`		= '%s'
				AND `deleted_at`	= '0'
				AND `pending`		= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$sNow,
			$sNow,
			$inaWhere['wp_username']
		);
		$this->doSql( $sQuery );
		
		$inaWhere['pending']	= 1;
		$inaWhere['deleted_at']	= 0;
		
		// Now activate the new one.
		return $this->updateRowsFromTable( array( 'pending' => 0 ), $inaWhere );
	}
	
	/**
	 * Checks whether a given user is authenticated.
	 * 
	 * @param array $inaWhere
	 * @return boolean
	 */
	public function isUserAuthenticated( $inaWhere ) {
		
		$aChecks = array( 'wp_username' );
		if ( !$this->validateInputData( $inaWhere, $aChecks) ) {
			return false;
		}
		
		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`wp_username`		= '%s'
				AND `ip_long`		= '%s'
				AND `pending`		= '0'
				AND `deleted_at`	= '0'
				AND `expired_at`	= '0'
		";
		$sQuery = sprintf( $sQuery,
			$this->m_sTableName,
			$inaWhere['wp_username'],
			self::GetVisitorIpAddress()
		);

		$mResult = $this->selectCustomFromTable( $sQuery );
		return ( is_array( $mResult ) && count( $mResult ) == 1 )? true : false; 
	}

	/**
	 * @param string $insEmailAddress
	 */
	public function sendLoginAuthenticationEmail( $insEmailAddress, $insIpAddress, $insAuthLink ) {
	
		$aMessage = array(
			'You, or someone pretending to be you, just attempted to login into your WordPress site.',
			'The IP Address from which they tried to login is not currently valid.',
			'To validate this address, click the following link, and then login again.',
			'IP Address: '. $insIpAddress,
			'Authentication Link: '. $insAuthLink
		);
		$sEmailSubject = 'Two-Factor Login Verification: ' . home_url();
		$this->sendEmail( $insEmailAddress, $sEmailSubject, $aMessage );
	}
	
	public function getAuthenticationLink( $insKey, $insUser, $insUniqueId ) {
		$sSiteUrl = home_url() . '?wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid=%s';
		$sAction = 'linkauth';
		return sprintf( $sSiteUrl, $insKey, $sAction, $insUser, $insUniqueId ); 
	}
	
	public function createTable() {

		// Set up login processor table
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`unique_id` varchar(20) NOT NULL DEFAULT '',
			`wp_username` varchar(255) NOT NULL DEFAULT '',
			`ip` varchar(20) NOT NULL DEFAULT '',
			`ip_long` bigint(20) NOT NULL DEFAULT '0',
			`pending` int(1) NOT NULL DEFAULT '0',
			`created_at` int(15) NOT NULL DEFAULT '0',
			`deleted_at` int(15) NOT NULL DEFAULT '0',
			`expired_at` int(15) NOT NULL DEFAULT '0',
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$sSqlTables = sprintf( $sSqlTables, $this->m_sTableName );
		$mResult = $this->doSql( $sSqlTables );
	}
	
	public function reset() {
		parent::reset();
	}
	
	public function getLogData() {
		return parent::getLogData();
	}
	
	/**
	 * Assumes that unique_id AND wp_username have been set correctly in the data array (no checking).
	 * 
	 * @param array $inaData
	 * @return array
	 */
	protected function getLoginAuthData( $inaData ) {

		$sQuery = "SELECT * FROM %s WHERE `unique_id` = `%s` AND `wp_username` = %s";
		$sQuery = sprintf( $sQuery, $this->m_sTableName, $inaData['unique_id'], $inaData['wp_username'] );
		return $this->selectRowFromTable( $sQuery );
	}
	
	/**
	 * Checks the $inaData contains valid key values as laid out in $inaChecks
	 * 
	 * @param array $inaData
	 * @param array $inaChecks
	 * @return boolean
	 */
	protected function validateInputData( $inaData, $inaChecks ) {
		
		if ( !is_array( $inaData ) ) {
			return false;
		}
		
		foreach( $inaChecks as $sCheck ) {
			if ( !array_key_exists( $sCheck, $inaData ) || empty( $inaData[ $sCheck ] ) ) {
				return false;
			}
		}
		return true;
	}
	
}

endif;