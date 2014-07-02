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

if ( !class_exists('ICWP_WPSF_DataProcessor_V2') ):

	class ICWP_WPSF_DataProcessor_V2 {

		public static $fUseFilter = false;

		/**
		 * @var string
		 */
		protected static $sIpAddress;

		/**
		 * @var integer
		 */
		protected static $nRequestTime;

		/**
		 * @return int
		 */
		public static function GetRequestTime() {
			if ( empty( self::$nRequestTime ) ) {
				self::$nRequestTime = time();
			}
			return self::$nRequestTime;
		}

		/**
		 * Cloudflare compatible.
		 *
		 * @param boolean $infAsLong
		 * @return bool|integer - visitor IP Address as IP2Long
		 */
		public static function GetVisitorIpAddress( $infAsLong = true ) {

			if ( !empty( self::$sIpAddress ) ) {
				return $infAsLong? ip2long( self::$sIpAddress ) : self::$sIpAddress;
			}

			$aAddressSourceOptions = array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED',
				'REMOTE_ADDR'
			);
			$fCanUseFilter = function_exists( 'filter_var' ) && defined( 'FILTER_FLAG_NO_PRIV_RANGE' ) && defined( 'FILTER_FLAG_IPV4' );

			foreach( $aAddressSourceOptions as $sOption ) {
				if ( empty( $_SERVER[ $sOption ] ) ) {
					continue;
				}
				$sIpAddressToTest = $_SERVER[ $sOption ];

				$aIpAddresses = explode( ',', $sIpAddressToTest ); //sometimes a comma-separated list is returned
				foreach( $aIpAddresses as $sIpAddress ) {

					if ( $fCanUseFilter && !self::IsAddressInPublicIpRange( $sIpAddress ) ) {
						continue;
					}
					else {
						self::$sIpAddress = $sIpAddress;
						return $infAsLong? ip2long( self::$sIpAddress ) : self::$sIpAddress;
					}
				}
			}
			return false;
		}

		/**
		 * Assumes a valid IPv4 address is provided as we're only testing for a whether the IP is public or not.
		 *
		 * @param string $insIpAddress
		 * @uses filter_var
		 * @return boolean
		 */
		public static function IsAddressInPublicIpRange( $insIpAddress ) {
			return filter_var( $insIpAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE );
		}

		static public function ExtractIpAddresses( $insAddresses = '' ) {

			$aRawAddresses = array();

			if ( empty( $insAddresses ) ) {
				return $aRawAddresses;
			}
			$aRawList = array_map( 'trim', explode( "\n", $insAddresses ) );

			self::$fUseFilter = function_exists('filter_var') && defined( FILTER_VALIDATE_IP );

			foreach( $aRawList as $sKey => $sRawAddressLine ) {

				if ( empty( $sRawAddressLine ) ) {
					continue;
				}

				// Each line can have a Label which is the IP separated with a space.
				$aParts = explode( ' ', $sRawAddressLine, 2 );
				if ( count( $aParts ) == 1 ) {
					$aParts[] = '';
				}
				$aRawAddresses[ $aParts[0] ] = trim( $aParts[1] );
			}
			return self::Add_New_Raw_Ips( array(), $aRawAddresses );
		}

		static public function ExtractCommaSeparatedList( $insRawList = '' ) {

			$aRawList = array();
			if ( empty( $insRawList ) ) {
				return $aRawList;
			}
// 		$aRawList = array_map( 'trim', explode( "\n", $insRawList ) );
			$aRawList = array_map( 'trim', preg_split( '/\r\n|\r|\n/', $insRawList ) );
			$aNewList = array();
			$fHadStar = false;
			foreach( $aRawList as $sKey => $sRawLine ) {

				if ( empty( $sRawLine ) ) {
					continue;
				}
				$sRawLine = str_replace( ' ', '', $sRawLine );
				$aParts = explode( ',', $sRawLine, 2 );
				// we only permit 1x line beginning with *
				if ( $aParts[0] == '*' ) {
					if ( $fHadStar ) {
						continue;
					}
					$fHadStar = true;
				}
				else {
					//If there's only 1 item on the line, we assume it to be a global
					// parameter rule
					if ( count( $aParts ) == 1 || empty( $aParts[1] ) ) { // there was no comma in this line in the first place
						array_unshift( $aParts, '*' );
					}
				}

				$aParams = empty( $aParts[1] )? array() : explode( ',', $aParts[1] );
				$aNewList[ $aParts[0] ] = $aParams;
			}
			return $aNewList;
		}

		/**
		 * Given a list of new IPv4 address ($inaNewRawAddresses) it'll add them to the existing list
		 * ($inaCurrent) where they're not already found
		 *
		 * @param array $inaCurrent			- the list to which to add the new addresses
		 * @param array $inaNewRawAddresses	- the new IPv4 addresses
		 * @param int $outnNewAdded			- the count of newly added IPs
		 * @return unknown|Ambigous <multitype:multitype: , string>
		 */
		public static function Add_New_Raw_Ips( $inaCurrent, $inaNewRawAddresses, &$outnNewAdded = 0 ) {

			$outnNewAdded = 0;

			if ( empty( $inaNewRawAddresses ) ) {
				return $inaCurrent;
			}

			if ( !array_key_exists( 'ips', $inaCurrent ) ) {
				$inaCurrent['ips'] = array();
			}
			if ( !array_key_exists( 'meta', $inaCurrent ) ) {
				$inaCurrent['meta'] = array();
			}

			foreach( $inaNewRawAddresses as $sRawIpAddress => $sLabel ) {
				$mVerifiedIp = self::Verify_Ip( $sRawIpAddress );
				if ( $mVerifiedIp !== false && !in_array( $mVerifiedIp, $inaCurrent['ips'] ) ) {
					$inaCurrent['ips'][] = $mVerifiedIp;
					if ( empty($sLabel) ) {
						$sLabel = 'no label';
					}
					$inaCurrent['meta'][ md5( $mVerifiedIp ) ] = $sLabel;
					$outnNewAdded++;
				}
			}
			return $inaCurrent;
		}

		/**
		 * @param array $inaCurrent
		 * @param array $inaRawAddresses - should be a plain numerical array of IPv4 addresses
		 * @return array:
		 */
		public static function Remove_Raw_Ips( $inaCurrent, $inaRawAddresses ) {
			if ( empty( $inaRawAddresses ) ) {
				return $inaCurrent;
			}

			if ( !array_key_exists( 'ips', $inaCurrent ) ) {
				$inaCurrent['ips'] = array();
			}
			if ( !array_key_exists( 'meta', $inaCurrent ) ) {
				$inaCurrent['meta'] = array();
			}

			foreach( $inaRawAddresses as $sRawIpAddress ) {
				$mVerifiedIp = self::Verify_Ip( $sRawIpAddress );
				if ( $mVerifiedIp === false ) {
					continue;
				}
				$mKey = array_search( $mVerifiedIp, $inaCurrent['ips'] );
				if ( $mKey !== false ) {
					unset( $inaCurrent['ips'][$mKey] );
					unset( $inaCurrent['meta'][ md5( $mVerifiedIp ) ] );
				}
			}
			return $inaCurrent;
		}

		public static function Verify_Ip( $insIpAddress ) {

			$sAddress = self::Clean_Ip( $insIpAddress );

			// Now, determine if this is an IP range, or just a plain IP address.
			if ( strpos( $sAddress, '-' ) === false ) { //plain IP address
				return self::Verify_Ip_Address( $sAddress );
			}
			else {
				return self::Verify_Ip_Range( $sAddress );
			}
		}

		public static function Clean_Ip( $insRawAddress ) {
			$insRawAddress = preg_replace( '/[a-z\s]/i', '', $insRawAddress );
			$insRawAddress = str_replace( '.', 'PERIOD', $insRawAddress );
			$insRawAddress = str_replace( '-', 'HYPEN', $insRawAddress );
			$insRawAddress = preg_replace( '/[^a-z0-9]/i', '', $insRawAddress );
			$insRawAddress = str_replace( 'PERIOD', '.', $insRawAddress );
			$insRawAddress = str_replace( 'HYPEN', '-', $insRawAddress );
			return $insRawAddress;
		}

		public static function Verify_Ip_Address( $insIpAddress ) {
			if ( self::$fUseFilter ) {
				if ( filter_var( $insIpAddress, FILTER_VALIDATE_IP ) ) {
					return ip2long( $insIpAddress );
				}
			}
			else {
				if ( preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\z/', $insIpAddress ) ) { //It's a valid IPv4 format, now check components
					$aParts = explode( '.', $insIpAddress );
					foreach ( $aParts as $sPart ) {
						$sPart = intval( $sPart );
						if ( $sPart < 0 || $sPart > 255 ) {
							return false;
						}
					}
					return ip2long( $insIpAddress );
				}
			}
			return false;
		}

		/**
		 * Taken from http://www.phacks.net/detecting-search-engine-bot-and-web-spiders/
		 */
		public static function IsSearchEngineBot() {

			if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
				return false;
			}
			$sUserAgent = $_SERVER['HTTP_USER_AGENT'];
			$sBots = 'Googlebot|bingbot|Twitterbot|Baiduspider|ia_archiver|R6_FeedFetcher|NetcraftSurveyAgent'
				.'|Sogou web spider|Yahoo! Slurp|facebookexternalhit|PrintfulBot|msnbot|UnwindFetchor|urlresolver|Butterfly|TweetmemeBot';

			return ( preg_match( "/$sBots/", $sUserAgent ) > 0 );
//
//		$aBots = array(
//			'Googlebot',
//			'bingbot',
//			'Twitterbot',
//			'Baiduspider',
//			'ia_archiver',
//			'R6_FeedFetcher',
//			'NetcraftSurveyAgent',
//			'Sogou web spider',
//			'Yahoo! Slurp',
//			'facebookexternalhit',
//			'PrintfulBot',
//			'msnbot',
//			'UnwindFetchor',
//			'urlresolver',
//			'Butterfly',
//			'TweetmemeBot'
//		);
			//
//		$aCrawlers = array(
//			'Google' => 'Google',
//			'msnbot' => 'MSN',
//			'Rambler' => 'Rambler',
//			'Yahoo' => 'Yahoo',
//			'AbachoBOT' => 'AbachoBOT',
//			'accoona' => 'Accoona',
//			'AcoiRobot' => 'AcoiRobot',
//			'ASPSeek' => 'ASPSeek',
//			'CrocCrawler' => 'CrocCrawler',
//			'Dumbot' => 'Dumbot',
//			'FAST-WebCrawler' => 'FAST-WebCrawler',
//			'GeonaBot' => 'GeonaBot',
//			'Gigabot' => 'Gigabot',
//			'Lycos' => 'Lycos spider',
//			'MSRBOT' => 'MSRBOT',
//			'Scooter' => 'Altavista robot',
//			'AltaVista' => 'Altavista robot',
//			'IDBot' => 'ID-Search Bot',
//			'eStyle' => 'eStyle Bot',
//			'Scrubby' => 'Scrubby robot'
//		);

			return array_key_exists( $sUserAgent, $aCrawlers );
		}

		/**
		 * The only ranges currently accepted are a.b.c.d-f.g.h.j
		 * @param string $insIpAddressRange
		 * @return string|boolean
		 */
		public static function Verify_Ip_Range( $insIpAddressRange ) {

			list( $sIpRangeStart, $sIpRangeEnd ) = explode( '-', $insIpAddressRange, 2 );

			if ( $sIpRangeStart == $sIpRangeEnd ) {
				return self::Verify_Ip_Address( $sIpRangeStart );
			}
			else if ( self::Verify_Ip_Address( $sIpRangeStart ) && self::Verify_Ip_Address( $sIpRangeEnd ) ) {
				$nStart = ip2long( $sIpRangeStart );
				$nEnd = ip2long( $sIpRangeEnd );

				// do our best to order it
				if (
					( $nStart > 0 && $nEnd > 0 && $nStart > $nEnd )
					|| ( $nStart < 0 && $nEnd < 0 && $nStart > $nEnd )
				) {
					$nTemp = $nStart;
					$nStart = $nEnd;
					$nEnd = $nTemp;
				}
				return $nStart.'-'.$nEnd;
			}
			return false;
		}

		public static function CleanYubikeyUniqueKeys( $insRawKeys ) {
			$aKeys = explode( "\n", $insRawKeys );
			foreach( $aKeys as $nIndex => $sUsernameKey ) {
				if ( empty( $sUsernameKey ) ) {
					unset( $aKeys[$nIndex] );
					continue;
				}
				$aParts = array_map( 'trim', explode( ',', $sUsernameKey ) );
				if ( empty( $aParts[0] ) || empty( $aParts[1] ) || strlen( $aParts[1] ) < 12 ) {
					unset( $aKeys[$nIndex] );
					continue;
				}
				$aParts[1] = substr( $aParts[1], 0, 12 );
				$aKeys[$nIndex] = array( $aParts[0] => $aParts[1] );
			}
			return $aKeys;
		}

		/**
		 * @param integer $innLength
		 * @param boolean $infBeginLetter
		 * @return string
		 */
		static public function GenerateRandomString( $innLength = 10, $infBeginLetter = false ) {
			$aChars = array( 'abcdefghijkmnopqrstuvwxyz' );
			$aChars[] = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

			$sCharset = implode( '', $aChars );
			if ( $infBeginLetter ) {
				$sPassword = $sCharset[ ( rand() % strlen( $sCharset ) ) ];
			}
			else {
				$sPassword = '';
			}
			$sCharset .= '023456789';

			for ( $i = $infBeginLetter? 1 : 0; $i < $innLength; $i++ ) {
				$sPassword .= $sCharset[ ( rand() % strlen( $sCharset ) ) ];
			}
			return $sPassword;
		}

		/**
		 * @return bool
		 */
		static public function GetIsRequestPost() {
			$sRequestMethod = self::ArrayFetch( $_SERVER, 'REQUEST_METHOD' );
			return strtolower( empty($sRequestMethod)? '' : $sRequestMethod ) == 'post';
		}

		/**
		 * @return string|null
		 */
		static public function GetScriptName() {
			$sScriptName = self::FetchServer( 'SCRIPT_NAME' );
			return !empty( $sScriptName )? $sScriptName : self::FetchServer( 'PHP_SELF' );
		}

		/**
		 * @param string $sKey
		 * @return mixed|null
		 */
		public static function FetchServer( $sKey ) {
			if ( function_exists( 'filter_input' ) && defined( 'INPUT_SERVER' ) ) {
				$sPossible = filter_input( INPUT_SERVER, $sKey );
				if ( !empty( $sPossible ) ) {
					return $sPossible;
				}
			}
			return self::ArrayFetch( $_SERVER, $sKey );
		}

		/**
		 * @param string $sKey
		 * @return mixed|null
		 */
		public static function FetchEnv( $sKey ) {
			if ( function_exists( 'filter_input' ) && defined( 'INPUT_ENV' ) ) {
				$sPossible = filter_input( INPUT_ENV, $sKey );
				if ( !empty( $sPossible ) ) {
					return $sPossible;
				}
			}
			return self::ArrayFetch( $_ENV, $sKey );
		}

		/**
		 * @param string $insKey
		 * @param boolean $infIncludeCookie
		 * @return mixed|null
		 */
		public static function FetchRequest( $insKey, $infIncludeCookie = true ) {
			$mFetchVal = self::FetchPost( $insKey );
			if ( is_null( $mFetchVal ) ) {
				$mFetchVal = self::FetchGet( $insKey );
				if ( is_null( $mFetchVal && $infIncludeCookie ) ) {
					$mFetchVal = self::FetchCookie( $insKey );
				}
			}
			return $mFetchVal;
		}
		/**
		 * @param string $insKey
		 * @return mixed|null
		 */
		public static function FetchGet( $insKey ) {
			if ( function_exists( 'filter_input' ) && defined( 'INPUT_GET' ) ) {
				return filter_input( INPUT_GET, $insKey );
			}
			return self::ArrayFetch( $_GET, $insKey );
		}
		/**
		 * @param string $insKey		The $_POST key
		 * @return mixed|null
		 */
		public static function FetchPost( $insKey ) {
			if ( function_exists( 'filter_input' ) && defined( 'INPUT_POST' ) ) {
				return filter_input( INPUT_POST, $insKey );
			}
			return self::ArrayFetch( $_POST, $insKey );
		}
		/**
		 * @param string $insKey		The $_POST key
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function FetchCookie( $insKey, $mDefault = null ) {
			if ( function_exists( 'filter_input' ) && defined( 'INPUT_COOKIE' ) ) {
				return filter_input( INPUT_COOKIE, $insKey );
			}
			return self::ArrayFetch( $_COOKIE, $insKey, $mDefault );
		}

		/**
		 * @param array $inaArray
		 * @param string $insKey		The array key
		 * @param mixed $mDefault
		 * @return mixed|null
		 */
		public static function ArrayFetch( &$inaArray, $insKey, $mDefault = null ) {
			if ( empty( $inaArray ) ) {
				return $mDefault;
			}
			if ( !isset( $inaArray[$insKey] ) ) {
				return $mDefault;
			}
			return $inaArray[$insKey];
		}
	}
endif;

if ( !class_exists('ICWP_WPSF_DataProcessor') ):
	class ICWP_WPSF_DataProcessor extends ICWP_WPSF_DataProcessor_V2 { }
endif;