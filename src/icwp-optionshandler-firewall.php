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
 */

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_OptionsHandler_Firewall') ):

class ICWP_OptionsHandler_Firewall extends ICWP_OptionsHandler_Base_Wpsf {
	
	const StoreName = 'firewall_options';
	
	public function __construct( $oPluginVo ) {
		parent::__construct( $oPluginVo, self::StoreName );

		$this->sFeatureName = _wpsf__('Firewall');
		$this->sFeatureSlug = 'firewall';
	}

	/**
	 */
	public function doPrePluginOptionsSave() {

		$aIpWhitelist = $this->getOpt( 'ips_whitelist' );
		if ( $aIpWhitelist === false ) {
			$aIpWhitelist = '';
			$this->setOpt( 'ips_whitelist', $aIpWhitelist );
		}
		$this->processIpFilter( 'ips_whitelist', 'icwp_simple_firewall_whitelist_ips' );
		
		$aIpBlacklist = $this->getOpt( 'ips_blacklist' );
		if ( $aIpBlacklist === false ) {
			$aIpBlacklist = '';
			$this->setOpt( 'ips_blacklist', $aIpBlacklist );
		}
		$this->processIpFilter( 'ips_blacklist', 'icwp_simple_firewall_blacklist_ips' );
		
		$aPageWhitelist = $this->getOpt( 'page_params_whitelist' );
		if ( $aPageWhitelist === false ) {
			$aPageWhitelist = '';
			$this->setOpt( 'page_params_whitelist', $aPageWhitelist );
		}
		
		$sBlockResponse = $this->getOpt( 'block_response' );
		if ( empty( $sBlockResponse ) ) {
			$sBlockResponse = 'redirect_die_message';
			$aIpWhitelist = $this->setOpt( 'block_response', $sBlockResponse );
		}
	}

	/**
	 * @return bool|void
	 */
	public function defineOptions() {
		$aFirewallBase = 	array(
			'section_title' => sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), _wpsf__('WordPress Firewall') ),
			'section_options' => array(
				array(
					'enable_firewall',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Enable Firewall' ),
					_wpsf__( 'Enable (or Disable) The WordPress Firewall Feature' ),
					sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), _wpsf__('WordPress Firewall') ),
					'<a href="http://icwp.io/43" target="_blank">'._wpsf__( 'more info' ).'</a>'
					.' | <a href="http://icwp.io/wpsf01" target="_blank">'._wpsf__( 'blog' ).'</a>'
				)
			)
		);
		$aBlockTypesSection =  array(
			'section_title' => _wpsf__( 'Firewall Blocking Options' ),
			'section_options' => array(
				array(
					'include_cookie_checks',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Include Cookies' ),
					_wpsf__( 'Also Test Cookie Values In Firewall Tests' ),
					_wpsf__( 'The firewall tests GET and POST, but with this option checked it will also COOKIE values.' )
				),
				array(
					'block_dir_traversal',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Directory Traversals' ),
					_wpsf__( 'Block Directory Traversals' ),
					_wpsf__( 'This will block directory traversal paths in in application parameters (e.g. ../, ../../etc/passwd, etc.).' )
				),
				array(
					'block_sql_queries',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'SQL Queries' ),
					_wpsf__( 'Block SQL Queries' ),
					_wpsf__( 'This will block sql in application parameters (e.g. union select, concat(, /**/, etc.).' )
				),
				array(
					'block_wordpress_terms',
					'',
					'N',
					'checkbox',
					_wpsf__( 'WordPress Terms' ),
					_wpsf__( 'Block WordPress Specific Terms' ),
					_wpsf__( 'This will block WordPress specific terms in application parameters (wp_, user_login, etc.).' )
				),
				array(
					'block_field_truncation',
					'',
					'Y',
					'checkbox',
					_wpsf__( 'Field Truncation' ),
					_wpsf__( 'Block Field Truncation Attacks' ),
					_wpsf__( 'This will block field truncation attacks in application parameters.' )
				),
				array(
					'block_php_code',
					'',
					'N',
					'checkbox',
					_wpsf__( 'PHP Code' ),
					sprintf( _wpsf__( 'Block %s' ), _wpsf__( 'PHP Code Includes' ) ),
					_wpsf__( 'This will block any data that appears to try and include PHP files.' )
					.'<br />'. _wpsf__( 'Will probably block saving within the Plugin/Theme file editors.' )
				),
				array(
					'block_exe_file_uploads',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Exe File Uploads' ),
					_wpsf__( 'Block Executable File Uploads' ),
					_wpsf__( 'This will block executable file uploads (.php, .exe, etc.).' )
				),
				array(
					'block_leading_schema',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Leading Schemas' ),
					_wpsf__( 'Block Leading Schemas (HTTPS / HTTP)' ),
					_wpsf__( 'This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with other plugins).' )
				)
			),
		);
		$aRedirectOptions = array( 'select',
			array( 'redirect_die_message',	_wpsf__( 'Die With Message' ) ),
			array( 'redirect_die', 			_wpsf__( 'Die' ) ),
			array( 'redirect_home',			_wpsf__( 'Redirect To Home Page' ) ),
			array( 'redirect_404',			_wpsf__( 'Return 404' ) ),
		);
		$aBlockSection = array(
			'section_title' => _wpsf__( 'Choose Firewall Block Response' ),
			'section_options' => array(
				array(
					'block_response',
					'',
					'none',
					$aRedirectOptions,
					_wpsf__( 'Block Response' ),
					_wpsf__( 'Choose how the firewall responds when it blocks a request' ),
					_wpsf__( 'We recommend dying with a message so you know what might have occurred when the firewall blocks you' )
				),
				array(
					'block_send_email',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Send Email Report' ),
					_wpsf__( 'When a visitor is blocked the firewall will send an email to the configured email address' ),
					_wpsf__( 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host' )
				)
			)
		);
		
		$aWhitelistSection = array(
			'section_title' => _wpsf__( 'Whitelists - IPs, Pages, Parameters, and Users that by-pass the Firewall' ),
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'',
					'ip_addresses',
					_wpsf__( 'Whitelist IP Addresses' ),
					_wpsf__( 'Choose IP Addresses that are never subjected to Firewall Rules' ),
					sprintf( _wpsf__( 'Take a new line per address. Your IP address is: %s' ), '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' )
				),
				array(
					'page_params_whitelist',
					'',
					'',
					'comma_separated_lists',
					_wpsf__( 'Whitelist Parameters' ),
					_wpsf__( 'Detail pages and parameters that are whitelisted (ignored by the firewall)' ),
					_wpsf__( 'This should be used with caution and you should only provide parameter names that you must have excluded' )
						.' '.sprintf( _wpsf__( '%sHelp%s' ), '[<a href="http://icwp.io/2a" target="_blank">', '</a>]' )
				),
				array(
					'whitelist_admins',
					'',
					'Y',
					'checkbox',
					sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Administrators' ) ),
					_wpsf__( 'Ignore users logged in as Administrator' ),
					_wpsf__( 'Authenticated administrator users will not be processed by the firewall' )
				),
				array(
					'ignore_search_engines',
					'',
					'N',
					'checkbox',
					sprintf( _wpsf__( 'Ignore %s' ), _wpsf__( 'Search Engines' ) ),
					_wpsf__( 'Ignore Search Engine Bots' ),
					_wpsf__( 'When selected, the firewall will try to recognise search engine spiders/bots and not apply firewall rules to them' )
				)
			)
		);
		
		$aBlacklistSection = array(
			'section_title' => _wpsf__( 'Choose IP Addresses To Blacklist' ),
			'section_options' => array(
				array(
					'ips_blacklist',
					'',
					'',
					'ip_addresses',
					_wpsf__( 'Blacklist IP Addresses' ),
					_wpsf__( 'Choose IP Addresses that are always blocked from accessing the site' ),
					_wpsf__( 'Take a new line per address. Each IP Address must be valid and will be checked' )
				)
			)
		);
		$aMisc = array(
			'section_title' => _wpsf__( 'Miscellaneous Plugin Options' ),
			'section_options' => array(
				array(
					'enable_firewall_log',
					'',
					'N',
					'checkbox',
					_wpsf__( 'Firewall Logging' ),
					_wpsf__( 'Turn on a detailed Firewall Log' ),
					_wpsf__( 'Will log every visit to the site and how the firewall processes it. Not recommended to leave on unless you want to debug something and check the firewall is working as you expect' )
				)
			)
		);

		$this->m_aOptions = array(
			$aFirewallBase,
			$aBlockSection,
			$aWhitelistSection,
			$aBlacklistSection,
			$aBlockTypesSection,
			$aMisc
		);
	}

	public function addRawIpsToFirewallList( $insListName, $inaNewIps ) {
		if ( empty( $inaNewIps ) ) {
			return;
		}
		
		$aIplist = $this->getOpt( $insListName );
		if ( empty( $aIplist ) ) {
			$aIplist = array();
		}
		$aNewList = array();
		foreach( $inaNewIps as $sAddress ) {
			$aNewList[ $sAddress ] = '';
		}
		$this->setOpt( $insListName, ICWP_WPSF_DataProcessor::Add_New_Raw_Ips( $aIplist, $aNewList ) );
	}

	public function removeRawIpsFromFirewallList( $insListName, $inaRemoveIps ) {
		if ( empty( $inaRemoveIps ) ) {
			return;
		}
		
		$aIplist = $this->getOpt( $insListName );
		if ( empty( $aIplist ) || empty( $inaRemoveIps ) ) {
			return;
		}
		$this->setOpt( $insListName, ICWP_WPSF_DataProcessor::Remove_Raw_Ips( $aIplist, $inaRemoveIps ) );
	}
	
}

endif;