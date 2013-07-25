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

if ( !class_exists('ICWP_FirewallProcessor') ):

class ICWP_FirewallProcessor extends ICWP_BaseProcessor {
	
	protected $m_sRequestId;
	protected $m_nRequestIp;
	protected $m_nRequestTimestamp;
	
	protected $m_aBlockSettings;
	protected $m_sBlockResponse;
	protected $m_aWhitelistIps;
	protected $m_aBlacklistIps;

	protected $m_aWhitelistPages;
	protected $m_aWhitelistPagesPatterns;
	protected $m_aCustomWhitelistPageParams;

	protected $m_aRequestUriParts;

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
	
	public function __construct( $inaBlockSettings, $inaIpWhitelist, $inaIpBlacklist, $inaPageParamWhitelist, $insBlockResponse ) {

		$this->m_aBlockSettings = $inaBlockSettings;
		$this->m_sBlockResponse = $insBlockResponse;
		$this->m_aWhitelistIps = $inaIpWhitelist;
		$this->m_aBlacklistIps = $inaIpBlacklist;
		$this->m_aCustomWhitelistPageParams = empty( $inaPageParamWhitelist )? array() : $inaPageParamWhitelist;
		$this->reset();
	}
	
	public function reset() {
		parent::reset();
		
		$this->setRequestUriPageParts();
		$this->setPageParams();
		$this->filterWhitelistedPagesAndParams();
		
		$this->m_nRequestIp = self::GetVisitorIpAddress();
		$this->m_sRequestId = uniqid();
		$this->m_nRequestTimestamp = time();
		$this->m_aPageParamValuesToCheck = array_values( $this->m_aPageParams );
	}
	
	public function getLogData() {
		$this->m_aLog = array(
			'request_id'		=> $this->m_sRequestId,
			'messages'			=> serialize( $this->m_aLogMessages ),
			'created_at'		=> $this->m_nRequestTimestamp,
			'ip'				=> long2ip( $this->m_nRequestIp ),
			'ip_long'			=> $this->m_nRequestIp,
			'uri'				=> serialize( $this->m_aRequestUriParts ),
			'params'			=> serialize( $this->m_aOrigPageParams ),
		);
		return $this->m_aLog;
		/*
		`request_id` varchar(255) NOT NULL DEFAULT '',
		`type` int(1) NOT NULL DEFAULT '0',
		`created_at` int(15) NOT NULL DEFAULT '0',
		`deleted_at` int(15) NOT NULL DEFAULT '0',
		`ip` varchar(20) NOT NULL DEFAULT '',
		`ip_long` longint(20) NOT NULL DEFAULT '',
		`uri` varchar(255) NOT NULL DEFAULT '',
		`referrer` varchar(255) NOT NULL DEFAULT '',
		*/
	}
	
	/**
	 * @return boolean - true if visitor is permitted, false if it should be blocked.
	 */
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
				sprintf( 'Visitor was blacklisted by IP Address. Label: %s',
					empty( $this->m_sListItemLabel )? 'No label.' : $this->m_sListItemLabel
				)
			);
			return false;
		}
		
		$this->logInfo( 'Visitor IP address was neither whitelisted nor blacklisted.' );
		
		// if we couldn't process the REQUEST_URI parts, we can't firewall so we effectively whitelist without erroring.
		if ( empty( $this->m_aRequestUriParts ) ) {
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
			$fIsPermittedVisitor = $this->doPassCheckBlockLeadingSchema();
		}

		return $fIsPermittedVisitor;
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
		$fPass = $this->doPassCheck( $this->m_aPageParamValuesToCheck, $aTerms );
		if ( !$fPass ) {
			$this->logWarning( 'Blocked Directory Traversal.' );
		}
		return $fPass;
	}
	
	protected function doPassCheckBlockSqlQueries() {
		$aTerms = array(
			'/concat\s*\(/i',
			'/group_concat/i',
			'/union.*select/i'
		);
		$fPass = $this->doPassCheck( $this->m_aPageParamValuesToCheck, $aTerms, true );
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
		$fPass = $this->doPassCheck( $this->m_aPageParamValuesToCheck, $aTerms, true );
		if ( !$fPass ) {
			$this->logWarning( 'Blocked WordPress Terms.' );
		}
		return $fPass;
	}
	
	protected function doPassCheckBlockFieldTruncation() {
		$aTerms = array(
			'/\s{49,}/i',
			'/\x00/'
		);
		$fPass = $this->doPassCheck( $this->m_aPageParamValuesToCheck, $aTerms, true );
		if ( !$fPass ) {
			$this->logWarning( 'Blocked Field Truncation.' );
		}
		return $fPass;
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
			$fPass = $this->doPassCheck( $aFileNames, $aTerms, true );
			if ( !$fPass ) {
				$this->logWarning( 'Blocked EXE File Uploads.' );
			}
			return $fPass;
		}
		return true;
	}
	
	protected function doPassCheckBlockLeadingSchema() {
		$aTerms = array(
			'/^http/i', '/\.shtml$/i'
		);
		$fPass = $this->doPassCheck( $this->m_aPageParamValuesToCheck, $aTerms, true );
		if ( !$fPass ) {
			$this->logWarning( 'Blocked Leading Schema.' );
		}
		return $fPass;
	}
	
	/**
	 * Returns false when check fails - that is to say, it should be blocked by the firewall.
	 * 
	 * @param array $inaParamValues
	 * @param array $inaMatchTerms
	 * @param boolean $infRegex
	 * @return boolean
	 */
	private function doPassCheck( $inaParamValues, $inaMatchTerms, $infRegex = false ) {
		
		foreach ( $inaParamValues as $sValue ) {
			foreach ( $inaMatchTerms as $sTerm ) {
				
				if ( $infRegex && preg_match( $sTerm, $sValue ) ) { //dodgy term pattern found in a parameter value
					$this->logWarning( 
						sprintf( 'Page parameter failed firewall check. The value was %s and the term matched was %s', $sValue, $sTerm )
					);
					return false;
				}
				else {
					if ( strpos( $sValue, $sTerm ) !== false ) { //dodgy term found in a parameter value
						$this->logWarning(
							sprintf( 'Page parameter failed firewall check. The value was %s and the term matched was %s', $sValue, $sTerm )
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
				header( "Location: ".home_url() );
				exit();
				break;
			case 'redirect_404':
				header( "Location: ".home_url().'/404' );
				exit();
				break;
			case 'redirect_die':
				die();
		}
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function isPageWhitelisted() {
		return empty( $this->m_aPageParams );
	}
	
	public function filterWhitelistedPagesAndParams() {

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
		if ( $this->checkPagesForWhiteListing( $this->m_aWhitelistPagesPatterns, true ) ) {
			return true;
		}
	}
	
	/**
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
		foreach ( $inaWhitelistPagesParams as $sPageName => $aWhitlistedParams ) {

			if ( $infUseRegex ) {
				if ( preg_match( $sPageName, $sRequestPage ) ) {
					$fPageWhitelisted = true;
					break;
				}
			}
			else {
				if ( preg_match( self::PcreDelimiter. preg_quote( $sPageName, self::PcreDelimiter ).'$'.self::PcreDelimiter, $sRequestPage ) ) {
					$fPageWhitelisted = true;
					break;
				}
			}
		}
		
		// There's a list of globally whitelisted parameters (i.e. parameter ignored for all pages)
		if ( array_key_exists( '*', $inaWhitelistPagesParams ) ) {
			foreach ( $inaWhitelistPagesParams['*'] as $sWhitelistParam ) {
				if ( array_key_exists( $sWhitelistParam, $this->m_aPageParams ) ) {
					unset( $this->m_aPageParams[ $sWhitelistParam ] );
				}
			}
		}
		
		// There's a list of globally whitelisted parameters (i.e. parameter ignored for all pages)
		if ( $fPageWhitelisted ) {
			// the current page is whitelisted - now check if it has request parameters.
			if ( empty( $aWhitlistedParams ) ) {
				return true; //because it's just plain whitelisted as represented by an empty or unset array
			}
			foreach ( $aWhitlistedParams as $sWhitelistParam ) {
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
		if ( $this->m_aBlockSettings[ 'include_cookie_checks' ] ) {
			$this->m_aPageParams = array_merge( $this->m_aPageParams, $_COOKIE );
		}
		$this->m_aOrigPageParams = $this->m_aPageParams;
		return true;
	}
	
	private function setWhitelistPages() {
		
		$aDefaultWlPages = array(
			'/wp-admin/options-general.php' => array(),
			'/wp-admin/post-new.php'		=> array(),
			'/wp-admin/page-new.php'		=> array(),
			'/wp-admin/link-add.php'		=> array(),
			'/wp-admin/media-upload.php'	=> array(),
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

		if ( is_array($this->m_aCustomWhitelistPageParams) ) {
			$this->m_aWhitelistPages = array_merge( $aDefaultWlPages, $this->m_aCustomWhitelistPageParams );
		}
		else {
			$this->m_aWhitelistPages = $aDefaultWlPages;
		}

		$this->m_aWhitelistPagesPatterns = array(
			self::PcreDelimiter.'\/wp-admin\/\*'.self::PcreDelimiter => array(
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
	 * @param string $insEmailAddress
	 */
	public function sendBlockEmail( $insEmailAddress ) {

		$aMessage = array(
			'WordPress Simple Firewall has blocked a visitor to your site.',
			'Log details for this visitor are below:',
			'- IP Address: '.$this->m_aLog['ip'],
		);
		foreach( $this->m_aLogMessages as $aLogItem ) {
			list( $sLogType, $sLogMessage ) = $aLogItem;
			$aMessage[] = '-  '.$sLogMessage;
		}
		
		$aMessage[] = 'You could look up the offending IP Address here: http://ip-lookup.net/?ip='. $this->m_aLog['ip'];
		$sEmailSubject = 'Firewall Block Alert: ' . home_url();
		$this->logInfo(
			sprintf( 'Block email sent to %s', $insEmailAddress )
		);
		$this->sendEmail( $insEmailAddress, $sEmailSubject, $aMessage );
	}
}

endif;