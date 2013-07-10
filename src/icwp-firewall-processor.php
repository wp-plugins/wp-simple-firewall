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

if ( !class_exists('ICWP_DataProcessor') ):

class ICWP_FirewallProcessor {
	
	const PcreDelimiter = '/';
	const LOG_MESSAGE_TYPE_INFO = 0;
	const LOG_MESSAGE_TYPE_WARNING = 1;
	const LOG_MESSAGE_TYPE_CRITICAL = 2;

	protected $m_sRequestId;
	protected $m_nRequestIp;
	
	protected $m_aBlockSettings;
	protected $m_sBlockResponse;
	protected $m_aWhitelistIps;
	protected $m_aBlacklistIps;

	protected $m_aWhitelistPages;
	protected $m_aWhitelistPagesPatterns;

	protected $m_aRequestUriParts;
	
	protected $m_aLog;

	/**
	 * @var string
	 */
	protected $m_sListItemLabel;

	/**
	 * A combination of the current request $_GET and $_POST
	 * @var array
	 */
	protected $m_aPageParams;
	
	/**
	 * All the remaining values of the page parameters after they've been filtered
	 * @var array
	 */
	protected $m_aPageParamValues;
	
	public function __construct( $inaBlockSettings, $inaIpWhitelist, $inaIpBlacklist, $insBlockResponse ) {

		$this->reset();
		$this->m_aBlockSettings = $inaBlockSettings;
		$this->m_sBlockResponse = $insBlockResponse;
		$this->m_aWhitelistIps = $inaIpWhitelist;
		$this->m_aBlacklistIps = $inaIpBlacklist;
	}
	
	public function reset() {
		$this->m_nRequestIp = self::GetVisitorIpAddress();
		$this->m_sRequestId =  long2ip( $this->m_nRequestIp ).'_'.uniqid();
		$this->m_aRequestUriParts = array();
		$this->m_aPageParams = array();
		$this->m_aPageParamValues = array();
		$this->resetLog();
	}
	
	public function resetLog() {
		if ( !is_array($this->m_aLog) ) {
			$this->m_aLog = array();
		}
		$this->m_aLog[ $this->m_sRequestId ] = array();
	}
	
	public function getLog() {
		return $this->m_aLog;
	}
	
	/**
	 * 
	 * @param string $insLogMessage
	 * @param string $insMessageType
	 */
	public function writeLog( $insLogMessage = '', $insMessageType = self::LOG_MESSAGE_TYPE_INFO ) {
		if ( !isset( $this->m_aLog[ $this->m_sRequestId ] ) ) {
			$this->resetLog();
		}
		$this->m_aLog[ $this->m_sRequestId ][] = array( time(), $insMessageType, $insLogMessage );
	}
	
	public function doFirewallCheck() {
		
		//Check if the visitor is excluded from the firewall from the outset.
		if ( $this->isVisitorOnWhitelist() ) {
			$this->logInfo(
				sprintf( 'Visitor is whitelisted by IP Address. Label: %s',
					empty( $this->m_sListItemLabel )? 'No label.' : $this->m_sListItemLabel
				)
			);
			return true;
		}
		
		//Check if the visitor is excluded from the firewall from the outset.
		if ( $this->isVisitorOnBlacklist() ) {
			$this->logWarning(
				sprintf( 'Visitor is blacklisted by IP Address. Label: %s',
					empty( $this->m_sListItemLabel )? 'No label.' : $this->m_sListItemLabel
				)
			);
			return false;
		}
		
		$this->logInfo( 'Visitor is neither whitelisted nor blacklisted by IP Address' );
		
		// if we can't process the REQUEST_URI parts, we can't firewall so we effectively whitelist without erroring.
		if ( !$this->setRequestUriPageParts() ) {
			return true;
		}
		
		$fIsPermittedVisitor = true;
		
		//Checking this comes before all else but after the IP whitelist check.
		if ( $this->m_aBlockSettings[ 'block_wplogin_access' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockWpLogin();
		}
		
		// Set up the page parameters ($_GET and $_POST). If there are none, quit since there's nothing for the firewall to check.
		$this->setPageParams();
		if ( $fIsPermittedVisitor && empty( $this->m_aPageParams ) ) {
			$this->logInfo( 'There were no page parameters to check on this visit.' );
			return true;
		}
		
		// Check if the page and its parameters are whitelisted.
		if ( $fIsPermittedVisitor && $this->isPageWhitelisted() ) {
			return true;
		}
		
		// ensures we have a simple array with all the values that need to be checked.
		$this->m_aPageParamValues = array_values( $this->m_aPageParams );
		
		if ( $fIsPermittedVisitor && $this->m_aBlockSettings[ 'block_dir_traversal' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockDirTraversal();
		}
		if ( $fIsPermittedVisitor && $this->m_aBlockSettings[ 'block_sql_queries' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockSqlQueries();
		}
		if ( $fIsPermittedVisitor && $this->m_aBlockSettings[ 'block_wordpress_terms' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockWordpressTerms();
		}
		if ( $fIsPermittedVisitor && $this->m_aBlockSettings[ 'block_field_truncation' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockFieldTruncation();
		}
		if ( $fIsPermittedVisitor && $this->m_aBlockSettings[ 'block_exe_file_uploads' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockExeFileUploads();
		}
		if ( $fIsPermittedVisitor && $this->m_aBlockSettings[ 'block_leading_schema' ] ) {
			$fIsPermittedVisitor = $this->doPassCheckBlockLoadingSchema();
		}

		return $fIsPermittedVisitor || isset($_GET['testfirewall']); //testing
	}
	
	/**
	 * This function assumes that isVisitorOnWhitelist() check has been run previously. Meaning the
	 * current visitor is NOT on the whitelist and so if the page they're accessing is wp-login then
	 * they do not pass this check.
	 * 
	 * If whitelisted IPs is empty, we never block access.
	 * 
	 * @return boolean
	 */
	protected function doPassCheckBlockWpLogin() {
		
		list( $sRequestPage, $sRequestQuery ) = $this->m_aRequestUriParts;
		
		if ( substr_count( $sRequestPage, '/wp-login.php' ) > 0 ) {
			
			// We don't block wp-login.php if there are no whitelisted IPs.
			if ( count( $this->m_aWhitelistIps ) < 1 ) {
				$this->logInfo( 'Requested: Block access to wp-login.php, but this was skipped because whitelisted IPs list was empty.' );
				return true;
			}
			
			$this->logWarning( 'Requested: Block access to wp-login.php. Visitor not on IP whitelist and blocked by firewall.' );
			return false;
		}
		return true;
	}
	
	protected function doPassCheckBlockDirTraversal() {
		$aTerms = array(
			'etc/passwd',
			'proc/self/environ',
			'../'
		);
		$fPass = $this->doPassCheck( $this->m_aPageParamValues, $aTerms );
		if ( !$fPass ) {
			$this->logWarning( 'Requested: Block Directory Traversal. This visitor.' );
		}
		return $fPass;
	}
	
	protected function doPassCheckBlockSqlQueries() {
		$aTerms = array(
			'/concat\s*\(/i',
			'/group_concat/i',
			'/union.*select/i'
		);
		$fPass = $this->doPassCheck( $this->m_aPageParamValues, $aTerms, true );
		if ( !$fPass ) {
			$this->logWarning( 'Requested: Block access to wp-login.php, but this was skipped because whitelisted IPs list was empty.' );
		}
		return $fPass;
	}
	
	protected function doPassCheckBlockWordpressTerms() {
		$aTerms = array(
			'/wp_/i',
			'/user_login/i',
			'/user_pass/i',
			'/0x[0-9a-f][0-9a-f]/i',
			'/\/\*\*\//'
		);
		return $this->doPassCheck( $this->m_aPageParamValues, $aTerms, true );
	}
	
	protected function doPassCheckBlockFieldTruncation() {
		$aTerms = array(
			'/\s{49,}/i',
			'/\x00/'
		);
		return $this->doPassCheck( $this->m_aPageParamValues, $aTerms, true );
	}
	
	protected function doPassCheckBlockExeFileUploads() {
		$aTerms = array(
			'/\.dll$/i', '/\.rb$/i', '/\.py$/i', '/\.exe$/i', '/\.php[3-6]?$/i', '/\.pl$/i',
			'/\.perl$/i', '/\.ph[34]$/i', '/\.phl$/i', '/\.phtml$/i', '/\.phtm$/i'
		);
		
		if ( isset( $_FILES ) && !empty( $_FILES ) ) {
			$aFileNames = array();
			foreach( $_FILES as $aFile ) {
				if ( !empty( $aFile['name'] ) ) {
					$aFileNames[] = $aFile['name'];
				}
			}
			return $this->doPassCheck( $aFileNames, $aTerms, true );
		}
		
		return true;
	}
	
	protected function doPassCheckBlockLoadingSchema() {
		$aTerms = array(
			'/^http/i', '/\.shtml$/i'
		);
		return $this->doPassCheck( $this->m_aPageParamValues, $aTerms, true );
	}
	
	/**
	 * Returns false when check fails - that is to say, it should be blocked by the firewall.
	 * 
	 * @param array $inaValues
	 * @param array $inaTerms
	 * @param boolean $infRegex
	 * @return boolean
	 */
	private function doPassCheck( $inaValues, $inaTerms, $infRegex = false ) {
		
		foreach ( $inaValues as $sValue ) {
			foreach ( $inaTerms as $sTerm ) {
				
				if ( $infRegex && preg_match( $sTerm, $sValue ) ) { //dodgy term pattern found in a parameter value
					$this->logWarning( 
						sprintf( 'Page parameter failed firewall check. The value was %s and the term is matched was %s', $sValue, $sTerm )
					);
					return false;
				}
				else {
					if ( strpos( $sValue, $sTerm ) !== false ) { //dodgy term found in a parameter value
						$this->logWarning(
							sprintf( 'Page parameter failed firewall check. The value was %s and the search term it matched was %s', $sValue, $sTerm )
						);
						return false;
					}
				}
			}
		}
		return true;
	}

	public function doFirewallBlock() {
		
		switch( $this->m_sBlockResponse ) {

			case 'redirect_home':
				header( "Location: ".home_url().'?testfirewall' );
				exit();
				break;
			case 'redirect_404':
				header( "Location: ".home_url().'/404?testfirewall' );
				exit();
				break;
			case 'redirect_die':
				die();
		}
	}
	
	public function isPageWhitelisted() {

		if ( empty( $this->m_aWhitelistPages ) ) {
			$this->setWhitelistPages();
		}
		if ( empty( $this->m_aWhitelistPages ) ) {
			return false;
		}

		// Check normal whitelisting pages without patterns.
		if ( $this->checkPagesForWhiteListing( $this->m_aWhitelistPages ) ) {
			return true;
		}
		// Check pattern-based whitelisting pages.
		if ( $this->checkPagesForWhiteListing( $this->m_aWhitelistPagesPatterns ) ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * 
	 * @param array $inaWhitelistPagesParams
	 * @param boolean $infUseRegex
	 * @return boolean
	 */
	protected function checkPagesForWhiteListing( $inaWhitelistPagesParams = array(), $infUseRegex = false ) {
		
		list( $sRequestPage, $sRequestQuery ) = $this->m_aRequestUriParts;
		
		// Now we compare pages in the whitelist with the parts of the request uri. If we get a match, that page is whitelisted
		$aWhitelistPages = array_keys( $inaWhitelistPagesParams );
		
		// 1. Is the page in the list of white pages?
		$fPageWhitelisted = false;
		if ( $infUseRegex ) {
			foreach ( $aWhitelistPages as $sPagePattern ) {
				if ( preg_match( $sPagePattern, $sRequestPage ) ) {
					$fPageWhitelisted = true;
					break;
				}
			}
		}
		else if ( in_array( $sRequestPage, $aWhitelistPages ) ) {
			$fPageWhitelisted = true;
		}
		
		if ( $fPageWhitelisted ) {
			// the current page is whitelisted - now check if it has request parameters.
			if ( empty( $inaWhitelistPagesParams[$sRequestPage] ) ) {
				return true; //because it's just plain whitelisted
			}
			foreach ( $inaWhitelistPagesParams[$sRequestPage] as $sWhitelistParam ) {
				if ( array_key_exists( $sWhitelistParam, $this->m_aPageParams ) ) {
					unset( $this->m_aPageParams[ $sWhitelistParam ] );
				}
			}
			// After removing all the whitelisted params, we now check if there are any params left that'll
			// need matched later in the firewall checking. If there are no parameters left, we return true.
			if ( empty( $this->m_aPageParams ) ) {
				return true;
			}
		}
		return false;
	}
	
	protected function setRequestUriPageParts() {
		
		if ( !isset( $_SERVER['REQUEST_URI'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			$this->m_aRequestUriParts = false;
			$this->logWarning( 'Could not parse the details of this page call as $_SERVER[REQUEST_URI] was empty or not defined.' );
			return false;
		}
		$this->m_aRequestUriParts = explode( '?', $_SERVER['REQUEST_URI'] );
		$this->logInfo( sprintf( 'Page Request URI: %s', $_SERVER['REQUEST_URI'] ) );
		return true;
	}
	
	protected function setPageParams() {
		$this->m_aPageParams = array_merge( $_GET, $_POST );
		return true;
	}
	
	private function setWhitelistPages() {
		
		$aDefaultWlPages = array(
			'/wp-admin/options-general.php' => array(),
			'/wp-admin/post-new.php'		=> array(),
			'/wp-admin/page-new.php'		=> array(),
			'/wp-admin/link-add.php'		=> array(),
			'/wp-admin/media-upload.php'		=> array(),
			'/wp-admin/post.php'			=> array(),
			'/wp-admin/page.php'			=> array(),
			'/wp-admin/admin-ajax.php'		=> array(),
			'/wp-comments-post.php'			=> array(
				'url',
				'comment'
			),
			'/wp-login.php'					=> array(
				'redirect_to'
			)
		);
		
		// add in custom whitelisted pages later
		
		$this->m_aWhitelistPages = $aDefaultWlPages;
		$this->m_aWhitelistPagesPatterns = array(
			'/wp-admin/\*' => array(
				'_wp_original_http_referer',
				'_wp_http_referer'
			),
		);
	}
	
	public function isVisitorOnWhitelist() {
		return $this->isIpOnlist( $this->m_aWhitelistIps, $this->m_nRequestIp );
	}
	
	public function isVisitorOnBlacklist() {
		return $this->isIpOnlist( $this->m_aBlacklistIps, $this->m_nRequestIp );
	}
	
	public function isIpOnlist( $inaIpList, $innIpAddress = '' ) {
		
		if ( empty( $innIpAddress ) || !isset( $inaIpList['ips'] ) ) {
			return true;
		}
		
		foreach( $inaIpList['ips'] as $mWhitelistAddress ) {
			
			if ( strpos( $mWhitelistAddress, '-' ) === false ) { //not a range
				if ( $innIpAddress == $mWhitelistAddress ) {
					$this->m_sListItemLabel = $inaIpList['meta'][ md5( $mWhitelistAddress ) ];
					return true;
				}
			}
			else {
				list( $sStart, $sEnd ) = explode( '-', $mWhitelistAddress, 2 );
				if ( $sStart <= $mWhitelistAddress && $mWhitelistAddress <= $sEnd ) {
					$this->m_sListItemLabel = $inaIpList['meta'][ md5( $mWhitelistAddress ) ];
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Cloudflare compatible.
	 * 
	 * @return number
	 */
	public static function GetVisitorIpAddress() {
	
		$sIpAddress = empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["REMOTE_ADDR"] : $_SERVER["HTTP_X_FORWARDED_FOR"];
	
		if( strpos($sIpAddress, ',') !== false ) {
			$sIpAddress = explode(',', $sIpAddress);
			$sIpAddress = $sIpAddress[0];
		}
	
		return ip2long( $sIpAddress );
	
	}//GetVisitorIpAddress

	/**
	 * @param string $insLogMessage
	 */
	public function logInfo( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_TYPE_INFO );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logWarning( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_TYPE_WARNING );
	}
	/**
	 * @param string $insLogMessage
	 */
	public function logCritical( $insLogMessage ) {
		$this->writeLog( $insLogMessage, self::LOG_MESSAGE_TYPE_CRITICAL );
	}

	public function sendBlockEmail( $insEmailAddress ) {

		$aMessage = array(
			'WordPress Simple Firewall has blocked a visitor to your site.',
			'Log details for this visitor are below:',
			'- IP Address: '.$this->m_nRequestIp
		);
		foreach( $this->m_aLog[ $this->m_sRequestId ] as $aLogItem ) {
			list( $sTime, $sLogType, $sLogMessage ) = $aLogItem;
			$aMessage[] = '-  '.$aLogItem[2];
		}
		
		$aMessage[] = 'You could look up the offending IP Address here: http://ip-lookup.net/?ip='. $this->m_nRequestIp;
		$sEmailSubject = 'Firewall Block Alert: ' . home_url();
		$sSiteName = get_bloginfo('name');
		$aHeaders   = array(
			'MIME-Version: 1.0',
			'Content-type: text/plain; charset=iso-8859-1',
			"From: Simple Firewall Plugin - $sSiteName <$insEmailAddress>",
			"Reply-To: Site Admin <$insEmailAddress>",
			'Subject: '.$sEmailSubject,
			'X-Mailer: PHP/'.phpversion()
		);
		mail( $insEmailAddress, $sEmailSubject, implode( "\r\n", $aMessage ), implode( "\r\n", $aHeaders ) );
	}
}

endif;