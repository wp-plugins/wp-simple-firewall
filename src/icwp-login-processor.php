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
	
	protected $m_nRequiredLoginInterval;
	protected $m_nLastLoginTime;
	protected $m_sSecretKey;
	
	public function __construct( $insTableName, $innRequiredLoginInterval, $insSecretKey ) {
		parent::__construct( $insTableName );
		$this->m_nRequiredLoginInterval = ( $innRequiredLoginInterval < 0 )? 0 : $innRequiredLoginInterval;
		$this->m_sSecretKey = $insSecretKey;
	}
	
	// WordPress Hooks and Filters:

	/**
	 * Shouild be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * 
	 * @param null|WP_User|WP_Error $inoUser
	 * @param string $insUsername
	 * @param string $insPassword
	 * @return unknown|WP_Error
	 */
	public function checkLoginInterval_Filter( $inoUser, $insUsername, $insPassword ) {
	
		// No login attempt was made.
		if ( empty( $insUsername ) ) {
			return $inoUser;
		}
	
		// Is there an interval set?
		$nRequiredLoginInterval = $this->m_nRequiredLoginInterval;
		if ( $nRequiredLoginInterval === false || $nRequiredLoginInterval == 0 ) {
			return $inoUser;
		}
	
		// Get the last login time (and update it also for the next time)
		$sNow = time();
		$nLastLoginTime = $this->m_nLastLoginTime;
	
		if ( $nLastLoginTime == false || $nLastLoginTime < 0 ) {
			$nLastLoginTime = 0;
		}

		// If we're outside the interval, let the login process proceed as per normal.
		$nLoginInterval = $sNow - $nLastLoginTime;
		if ( $nLoginInterval > $nRequiredLoginInterval ) {
			$this->m_nLastLoginTime = $sNow;
			return $inoUser;
		}
	
		// At this point someone has attempted to login within the previous login wait interval
		// So we remove WordPress's authentication filter, and our own user check authentication
		// And finally return a WP_Error which will be reflected back to the user.
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20, 3 );  // wp-includes/user.php
	
		$sErrorString = sprintf( "Sorry, you must wait %s seconds before attempting to login again.", ($nRequiredLoginInterval - $nLoginInterval ) );
		$oError = new WP_Error( 'wpsf_logininterval', $sErrorString );
		return $oError;
	}

	/**
	 * If $inoUser is a valid WP_User object, then the user logged in correctly.
	 *
	 * The flow is as follows:
	 * 0. If username is empty, there was no login attempt.
	 * 1. First we determine whether the user's login credentials were valid according to WordPress ($fUserLoginSuccess)
	 * 2. Then we ask our 2-factor processor whether the current IP address + username combination is authenticated.
	 * 		a) if yes, we return the WP_User object and login proceeds as per usual.
	 * 		b) if no, we return null, which will send the message back to the user that the login details were invalid.
	 * 3. If however the user's IP address + username combination is not authenticated, we react differently. We do not want
	 * 	to give away whether a login was successful, or even the login username details exist. So:
	 * 		a) if the login was a success we add a pending record to the authentication DB for this username+IP address combination and send the appropriate verification email
	 * 		b) then, we give back a message saying that if the login was successful, they would have received a verification email. In this way we give nothing away.
	 * 		c) note at this stage, if the username was empty, we give back nothing (this happens when wp-login.php is loaded as normal.
	 *
	 * @param WP_User|string $inmUser	- the docs say the first parameter a string, WP actually gives a WP_User object (or null)
	 * @param string $insUsername
	 * @param string $insPassword
	 * @return WP_Error|WP_User|null	- WP_User when the login success AND the IP is authenticated. null when login not successful but IP is valid. WP_Error otherwise.
	 */
	public function checkUserAuthLogin_Filter( $inoUser, $insUsername, $insPassword ) {
	
		if ( empty( $insUsername ) ) {
			return $inoUser;
		}
	
		$fUserLoginSuccess = is_object( $inoUser ) && ( $inoUser instanceof WP_User );
	
		if ( is_wp_error( $inoUser ) ) {
			$aCodes = $inoUser->get_error_codes();
			if ( in_array( 'wpsf_logininterval', $aCodes ) ) {
				return $inoUser;
			}
		} else if ( $fUserLoginSuccess ) {
			
			$aData = array( 'wp_username' => $insUsername );
			if ( $this->isUserVerified( $aData ) ) {
				return $inoUser;
			}
			else {
				// Create a new 2-factor auth pending entry
				$aNewAuthData = $this->loginAuthAddPending( array( 'wp_username' => $inoUser->user_login ) );
	
				// Now send email with authentication link for user.
				if ( is_array( $aNewAuthData ) ) {
					$sAuthLink = $this->getTwoFactorVerifyLink( $this->m_sSecretKey, $inoUser->user_login, $aNewAuthData['unique_id'] );
					$this->sendEmailTwoFactorVerify( $inoUser->user_email, $aNewAuthData['ip'], $sAuthLink );
				}
			}
		}
		
		$sErrorString = "Login is protected by 2-factor authentication. If your login details were correct, you would have received an email to verify this IP address.";
		return new WP_Error( 'wpsf_loginauth', $sErrorString );
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
	public function isUserVerified( $inaWhere ) {
		
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
	
	public function getTwoFactorVerifyLink( $insKey, $insUser, $insUniqueId ) {
		$sSiteUrl = home_url() . '?wpsfkey=%s&wpsf-action=%s&username=%s&uniqueid=%s';
		$sAction = 'linkauth';
		return sprintf( $sSiteUrl, $insKey, $sAction, $insUser, $insUniqueId ); 
	}

	/**
	 * @param string $insEmailAddress
	 * @param string $insIpAddress
	 * @param string $insAuthLink
	 */
	public function sendEmailTwoFactorVerify( $insEmailAddress, $insIpAddress, $insAuthLink ) {
	
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
	
	public function getLogData() {
		return parent::getLogData();
	}
	
	/**
	 * Assumes that unique_id AND wp_username have been set correctly in the data array (no checking done).
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