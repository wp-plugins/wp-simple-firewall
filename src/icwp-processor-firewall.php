<?php
/**
 * Copyright (c) 2015 iControlWP <support@icontrolwp.com>
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

require_once( dirname(__FILE__).ICWP_DS.'icwp-processor-base.php' );

if ( !class_exists('ICWP_FirewallProcessor_V1') ):

	class ICWP_FirewallProcessor_V1 extends ICWP_WPSF_Processor_Base {

		protected $m_aWhitelistPages;
		protected $m_aWhitelistPagesPatterns;
		protected $m_aCustomWhitelistPageParams;

		protected $m_aRequestUriParts;

		private $m_nLoopProtect;
		private $sFirewallDieMessage;

		private $fDoFirewallBlock;

		/**
		 * @var boolean
		 */
		protected $fRequestIsWhitelisted;

		/**
		 * @var string
		 */
		protected $m_sListItemLabel;

		/**
		 * A combination of all current request $_GET and $_POST (and optionally $_COOKIE)
		 * @var array
		 */
		protected $m_aOrigPageParams;

		/**
		 * This is $m_aOrigPageParams after any parameter whitelisting has taken place
		 * @var array
		 */
		protected $m_aPageParams;

		/**
		 * All the remaining values of the page parameters after they've been filtered
		 * @var array
		 */
		protected $m_aPageParamValues;

		/**
		 * @param ICWP_WPSF_FeatureHandler_Firewall $oFeatureOptions
		 */
		public function __construct( ICWP_WPSF_FeatureHandler_Firewall $oFeatureOptions ) {
			parent::__construct( $oFeatureOptions );

			$sMessage = _wpsf__( "You were blocked by the %s." );
			$this->sFirewallDieMessage = sprintf(
				$sMessage,
				'<a href="http://wordpress.org/plugins/wp-simple-firewall/" target="_blank">'
				.( $this->getController()->getHumanName() )
				.'</a>'
			);
		}

		public function reset() {
			parent::reset();
			$this->m_nLoopProtect = 0;
			$this->setRequestIsWhiteListed( false );
		}

		public function run() {
			$this->fDoFirewallBlock = !$this->doFirewallCheck();
			$this->doPreFirewallBlock();
			$this->doFirewallBlock();
		}

		/**
		 * @return bool
		 */
		public function getIfDoFirewallBlock() {
			return isset( $this->fDoFirewallBlock ) ? $this->fDoFirewallBlock : false;
		}

		/**
		 * @return boolean - true if visitor is permitted, false if it should be blocked.
		 */
		public function doFirewallCheck() {
			if ( $this->getOption('whitelist_admins') == 'Y' && is_super_admin() ) {
//				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Logged-in administrators by-pass firewall') );
//				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
				return true;
			}

			// Check if the visitor is excluded from the firewall from the outset.
			if ( $this->isVisitorOnWhitelist() ) {
				$sAuditMessage =  _wpsf__('Visitor is white-listed by IP Address.')
					.' '.sprintf( _wpsf__('Label: %s.'), empty( $this->m_sListItemLabel )? _wpsf__('No label') : $this->m_sListItemLabel );
//				$this->addToAuditEntry( $sAuditMessage, 1, 'firewall_skip' );
				$this->doStatIncrement( 'firewall.allowed.whitelist' );
				return true;
			}

			// Check if the visitor is excluded from the firewall from the outset.
			if ( $this->isVisitorOnBlacklist() ) {
				$this->sFirewallDieMessage .= ' Your IP is Blacklisted.';
				$sAuditMessage =  _wpsf__('Visitor was black-listed by IP Address.')
					.' '.sprintf( _wpsf__('Label: %s.'), empty( $this->m_sListItemLabel )? _wpsf__('No label') : $this->m_sListItemLabel );
				$this->doStatIncrement( 'firewall.blocked.blacklist' );
				return false;
			}

			// if we couldn't process the REQUEST_URI parts, we can't firewall so we effectively whitelist without erroring.
			$this->setRequestUriPageParts();
			if ( empty( $this->m_aRequestUriParts ) ) {
				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Parsing the URI failed') );
				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
				return true;
			}

			$oDp = $this->loadDataProcessor();
			if ( $this->getOption('ignore_search_engines') == 'Y' && $oDp->IsSearchEngineBot() ) {
				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('Visitor detected as Search Engine Bot') );
				$this->addToAuditEntry( $sAuditMessage, 2, 'firewall_skip' );
				return true;
			}

			// Set up the page parameters ($_GET and $_POST and optionally $_COOKIE). If there are none, quit since there's nothing for the firewall to check.
			$this->getPageParams();
			if ( empty( $this->m_aPageParams ) ) {
//				$sAuditMessage = sprintf( _wpsf__('Skipping firewall checking for this visit: %s.'), _wpsf__('After whitelist options were applied, there were no page parameters to check') );
//				$this->addToAuditEntry( $sAuditMessage, 1, 'firewall_skip' );
				return true;
			}

			$fIsPermittedVisitor = true;
			// Check if the page and its parameters are whitelisted.
			if ( $fIsPermittedVisitor && $this->isPageWhitelisted() ) {
//				$sAuditMessage = _wpsf__('All page request parameters were white-listed.');
//				$this->addToAuditEntry( $sAuditMessage, 1, 'firewall_skip' );
				$this->doStatIncrement( 'firewall.allowed.pagewhitelist' );
				return true;
			}

			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_dir_traversal', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockDirTraversal();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_sql_queries', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockSqlQueries();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_wordpress_terms', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockWordpressTerms();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_field_truncation', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockFieldTruncation();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_php_code', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckPhpCode();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_exe_file_uploads', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockExeFileUploads();
			}
			if ( $fIsPermittedVisitor && $this->getIsOption( 'block_leading_schema', 'Y' ) ) {
				$fIsPermittedVisitor = $this->doPassCheckBlockLeadingSchema();
			}

			return $fIsPermittedVisitor;
		}

		/**
		 * @return array
		 */
		protected function getParamsToCheck() {
			return $this->m_aPageParams;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockDirTraversal() {
			$aTerms = array(
				'etc/passwd',
				'proc/self/environ',
				'../'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Directory Traversal') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.dirtraversal' );
			}
			return $fPass;
		}

		protected function doPassCheckBlockSqlQueries() {
			$aTerms = array(
				'/concat\s*\(/i',
				'/group_concat/i',
				'/union.*select/i'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('SQL Queries') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.sqlqueries' );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockWordpressTerms() {
			$aTerms = array(
				'/^wp_/i',
				'/^user_login/i',
				'/^user_pass/i',
				'/0x[0-9a-f][0-9a-f]/i',
				'/\/\*\*\//'
			);

			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('WordPress Terms') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.wpterms' );
			}
			return $fPass;
		}

		/**
		 * @return bool
		 */
		protected function doPassCheckBlockFieldTruncation() {
			$aTerms = array(
				'/\s{49,}/i',
				'/\x00/'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Field Truncation') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.fieldtruncation' );
			}
			return $fPass;
		}

		protected function doPassCheckPhpCode() {
			$aTerms = array(
				'/(include|include_once|require|require_once)(\s*\(|\s*\'|\s*"|\s+\w+)/i'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('PHP Code') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.phpcode' );
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
					$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('EXE File Uploads') );
					$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
					$this->doStatIncrement( 'firewall.blocked.exefile' );
				}
				return $fPass;
			}
			return true;
		}

		protected function doPassCheckBlockLeadingSchema() {
			$aTerms = array(
				'/^http/i', '/\.shtml$/i'
			);
			$fPass = $this->doPassCheck( $this->getParamsToCheck(), $aTerms, true );
			if ( !$fPass ) {
				$sAuditMessage = sprintf( _wpsf__('Firewall Trigger: %s.'), _wpsf__('Leading Schema') );
				$this->addToAuditEntry( $sAuditMessage, 3, 'firewall_block' );
				$this->doStatIncrement( 'firewall.blocked.schema' );
			}
			return $fPass;
		}

		/**
		 * Returns false when check fails - that is to say, it should be blocked by the firewall.
		 *
		 * @param array $aParamValues
		 * @param array $aMatchTerms
		 * @param boolean $fRegex
		 * @return boolean
		 */
		private function doPassCheck( $aParamValues, $aMatchTerms, $fRegex = false ) {

			$fFAIL = false;
			foreach ( $aParamValues as $sParam => $mValue ) {
				if ( is_array( $mValue ) ) {

					// Protection against an infinite loop and we limit depth to 3.
					if ( $this->m_nLoopProtect > 2 ) {
						return true;
					}
					else {
						$this->m_nLoopProtect++;
					}

					if ( !$this->doPassCheck( $mValue, $aMatchTerms, $fRegex ) ) {
						return false;
					}

					$this->m_nLoopProtect--;
				}
				else {
					$mValue = (string) $mValue;
					foreach ( $aMatchTerms as $sTerm ) {

						if ( $fRegex && preg_match( $sTerm, $mValue ) ) { //dodgy term pattern found in a parameter value
							$fFAIL = true;
						}
						else if ( strpos( $mValue, $sTerm ) !== false ) { //dodgy term found in a parameter value
							$fFAIL = true;
						}

						if ( $fFAIL ) {
							$this->sFirewallDieMessage .= ' '._wpsf__("Something in the URL, Form or Cookie data wasn't appropriate.");
							$sAuditMessage = _wpsf__('Page parameter failed firewall check.')
								.' '.sprintf( _wpsf__( 'The offending parameter was "%s" with a value of "%s".' ), $sParam, $mValue );
							$this->addToAuditEntry( $sAuditMessage, 3 );
							return false;
						}

					}//foreach
				}
			}//foreach

			return true;
		}

		protected function doPreFirewallBlock() {

			if ( !$this->getIfDoFirewallBlock() ) {
				return true;
			}

			switch( $this->getOption( 'block_response' ) ) {
				case 'redirect_die':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor connection was killed with wp_die()') );
					break;
				case 'redirect_die_message':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor connection was killed with wp_die() and a message') );
					break;
				case 'redirect_home':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor was sent HOME') );
					break;
				case 'redirect_404':
					$sEntry = sprintf( _wpsf__('Firewall Block Response: %s.'), _wpsf__('Visitor was sent 404') );
					break;
			}

			$this->addToAuditEntry( $sEntry );

			if ( $this->getIsOption( 'block_send_email', 'Y' ) ) {

				$sRecipient = $this->getPluginDefaultRecipientAddress();
				$fSendSuccess = $this->sendBlockEmail( $sRecipient );
				if ( $fSendSuccess ) {
					$this->addToAuditEntry( sprintf( _wpsf__('Successfully sent Firewall Block email alert to: %s'), $sRecipient ) );
				}
				else {
					$this->addToAuditEntry( sprintf( _wpsf__('Failed to send Firewall Block email alert to: %s'), $sRecipient ) );
				}
			}
		}

		/**
		 */
		protected function doFirewallBlock() {

			if ( !$this->getIfDoFirewallBlock() ) {
				return true;
			}

			switch( $this->getOption( 'block_response' ) ) {
				case 'redirect_die':
					break;
				case 'redirect_die_message':
					wp_die( $this->sFirewallDieMessage );
					break;
				case 'redirect_home':
					header( "Location: ".home_url() );
					exit();
					break;
				case 'redirect_404':
					header( "Location: ".home_url().'/404' );
					break;
				default:
					break;
			}
			exit();
		}

		/**
		 * @return boolean
		 */
		public function isPageWhitelisted() {
			$aPageParams = $this->getPageParams();
			return empty( $aPageParams ) || $this->getRequestIsWhiteListed();
		}

		public function filterWhitelistedPagesAndParams() {

			if ( empty( $this->m_aWhitelistPages ) ) {
				$this->setWhitelistPages();
				if ( empty( $this->m_aWhitelistPages ) ) {
					return false;
				}
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

			if ( !is_array( $this->m_aRequestUriParts ) || count( $this->m_aRequestUriParts ) < 1 ) {
				return true;
			}
			$sRequestPage = $this->m_aRequestUriParts[0];

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
					$sWhitelistedPageAsPreg = preg_quote( $sPageName, self::PcreDelimiter );
					$sWhitelistedPageAsPreg = sprintf( '%s%s$%s', self::PcreDelimiter, $sWhitelistedPageAsPreg, self::PcreDelimiter );
					if ( preg_match( $sWhitelistedPageAsPreg, $sRequestPage ) ) {
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

			// Given the page is found to be on the whitelist, we want to check if it's the whole page, or certain parameters only
			if ( $fPageWhitelisted ) {
				// the current page is whitelisted - now check if it has request parameters.
				if ( empty( $aWhitlistedParams ) ) {
					$this->setRequestIsWhiteListed( true );
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
					$this->setRequestIsWhiteListed( true );
					return true;
				}
			}
			return false;
		}

		/**
		 * @return bool
		 */
		protected function getRequestIsWhiteListed() {
			return $this->fRequestIsWhitelisted;
		}

		/**
		 * @param bool $fWhitelisted
		 */
		protected function setRequestIsWhiteListed( $fWhitelisted = true ) {
			$this->fRequestIsWhitelisted = $fWhitelisted;
		}

		protected function setRequestUriPageParts() {

			if ( !isset( $_SERVER['REQUEST_URI'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
				$this->m_aRequestUriParts = false;
				return false;
			}
			$this->m_aRequestUriParts = explode( '?', $_SERVER['REQUEST_URI'] );
			return true;
		}

		protected function getPageParams() {

			if ( isset( $this->m_aPageParams ) ) {
				return $this->m_aPageParams;
			}

			$this->m_aPageParams = $this->getRequestParams();
			if ( empty( $this->m_aPageParams ) ) {
				$this->setRequestIsWhiteListed( true );
			}
			else {
				$this->filterWhitelistedPagesAndParams();
			}
			return true;
		}

		/**
		 * @return array
		 */
		protected function getRequestParams() {
			$aParams = array_merge( $_GET, $_POST );
			if ( $this->getIsOption( 'include_cookie_checks', 'Y' ) ) {
				$aParams = array_merge( $aParams, $_COOKIE );
			}
			return $aParams;
		}

		private function setWhitelistPages() {

			$aDefaultWlPages = array(
				'/wp-admin/options-general.php' => array(),
				'/wp-admin/post-new.php'		=> array(),
				'/wp-admin/page-new.php'		=> array(),
				'/wp-admin/link-add.php'		=> array(),
				'/wp-admin/media-upload.php'	=> array(),
				'/wp-admin/post.php'			=> array( 'content' ),
				'/wp-admin/plugin-editor.php'	=> array( 'newcontent' ),
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

			$aCustomWhitelistPageParams = is_array( $this->getOption( 'page_params_whitelist' ) )? $this->getOption( 'page_params_whitelist' ) : array();
			$this->m_aWhitelistPages = array_merge( $aDefaultWlPages, $aCustomWhitelistPageParams );

			$this->m_aWhitelistPagesPatterns = array(
				self::PcreDelimiter.'\/wp-admin\/\*'.self::PcreDelimiter => array(
					'_wp_original_http_referer',
					'_wp_http_referer'
				),
			);
		}

		public function isVisitorOnWhitelist() {
			return $this->isIpOnlist( $this->getOption( 'ips_whitelist', array() ), $this->ip(), $this->m_sListItemLabel );
		}

		public function isVisitorOnBlacklist() {
			return $this->isIpOnlist( $this->getOption( 'ips_blacklist', array() ), $this->ip(), $this->m_sListItemLabel );
		}

		/**
		 * @param string $sRecipient
		 * @return bool
		 */
		protected function sendBlockEmail( $sRecipient ) {

			$sIp = $this->loadDataProcessor()->getVisitorIpAddress( true );
			$aMessage = array(
				_wpsf__('WordPress Simple Firewall has blocked a page visit to your site.'),
				_wpsf__('Log details for this visitor are below:'),
				'- '.sprintf( _wpsf__('IP Address: %s'), $sIp )
			);
			$aMessage = array_merge( $aMessage, $this->getRawAuditMessage( '- ' ) );
			// TODO: Get audit trail messages
			$aMessage[] = sprintf( _wpsf__('You can look up the offending IP Address here: %s'), 'http://ip-lookup.net/?ip='.$sIp );
			$sEmailSubject = sprintf( _wpsf__('Firewall Block Email Alert: %s'), home_url() );

			$fSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $aMessage );
			return $fSendSuccess;
		}
	}

endif;

if ( !class_exists('ICWP_WPSF_Processor_Firewall') ):
	class ICWP_WPSF_Processor_Firewall extends ICWP_FirewallProcessor_V1 { }
endif;