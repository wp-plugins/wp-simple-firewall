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

require_once( dirname(__FILE__).'/icwp-optionshandler-base.php' );

if ( !class_exists('ICWP_OptionsHandler_Firewall') ):

class ICWP_OptionsHandler_Firewall extends ICWP_OptionsHandler_Base_WPSF {
	
	const StoreName = 'firewall_options';
	
	public function __construct( $insPrefix, $insVersion ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion );
	}
	
	public function doPrePluginOptionsSave() {

		$aIpWhitelist = $this->getOpt( 'ips_blacklist' );
		if ( $aIpWhitelist === false ) {
			$aIpWhitelist = '';
			$this->setOpt( 'ips_whitelist', $aIpWhitelist );
		}
		
		$aIpBlacklist = $this->getOpt( 'ips_blacklist' );
		if ( $aIpBlacklist === false ) {
			$aIpBlacklist = '';
			$this->setOpt( 'ips_blacklist', $aIpBlacklist );
		}
		
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
	
	public function defineOptions() {

		$this->m_aDirectSaveOptions = array( 'whitelist_admins' );
		
		$this->m_aFirewallBase = 	array(
			'section_title' => __( 'Enable WordPress Firewall', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'enable_firewall',
					'',	'N',
					'checkbox',
					__( 'Enable Firewall', 'wp-simple-firewall' ),
					__( 'Enable (or Disable) The WordPress Firewall Feature', 'wp-simple-firewall' ),
					__( 'Regardless of any other settings, this option will turn off the Firewall feature, or enable your selected Firewall options', 'wp-simple-firewall' )
				)
			)
		);
		$this->m_aBlockTypesSection =  array(
			'section_title' => __( 'Firewall Blocking Options', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'include_cookie_checks',
					'',
					'N',
					'checkbox',
					__( 'Include Cookies', 'wp-simple-firewall' ),
					__( 'Also Test Cookie Values In Firewall Tests', 'wp-simple-firewall' ),
					__( 'The firewall tests GET and POST, but with this option checked it will also COOKIE values.', 'wp-simple-firewall' )
				),
				array(
					'block_dir_traversal',
					'',
					'N',
					'checkbox',
					__( 'Directory Traversals', 'wp-simple-firewall' ),
					__( 'Block Directory Traversals', 'wp-simple-firewall' ),
					__( 'This will block directory traversal paths in in application parameters (e.g. ../, ../../etc/passwd, etc.).', 'wp-simple-firewall' )
				),
				array(
					'block_sql_queries',
					'',
					'N',
					'checkbox',
					__( 'SQL Queries', 'wp-simple-firewall' ),
					__( 'Block SQL Queries', 'wp-simple-firewall' ),
					__( 'This will block sql in application parameters (e.g. union select, concat(, /**/, etc.).', 'wp-simple-firewall' )
				),
				array(
					'block_wordpress_terms',
					'',
					'N',
					'checkbox',
					__( 'WordPress Terms', 'wp-simple-firewall' ),
					__( 'Block WordPress Specific Terms', 'wp-simple-firewall' ),
					__( 'This will block WordPress specific terms in application parameters (wp_, user_login, etc.).', 'wp-simple-firewall' )
				),
				array(
					'block_field_truncation',
					'',
					'N',
					'checkbox',
					__( 'Field Truncation', 'wp-simple-firewall' ),
					__( 'Block Field Truncation Attacks', 'wp-simple-firewall' ),
					__( 'This will block field truncation attacks in application parameters.', 'wp-simple-firewall' )
				),
				array(
					'block_exe_file_uploads',
					'',
					'N',
					'checkbox',
					__( 'Exe File Uploads', 'wp-simple-firewall' ),
					__( 'Block Executable File Uploads', 'wp-simple-firewall' ),
					__( 'This will block executable file uploads (.php, .exe, etc.).', 'wp-simple-firewall' )
				),
				array(
					'block_leading_schema',
					'',
					'N',
					'checkbox',
					__( 'Leading Schemas', 'wp-simple-firewall' ),
					__( 'Block Leading Schemas (HTTPS / HTTP)', 'wp-simple-firewall' ),
					__( 'This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with other plugins).', 'wp-simple-firewall' )
				)
			),
		);
		$aRedirectOptions = array( 'select',
			array( 'redirect_die_message',	'Die With Message' ),
			array( 'redirect_die', 			'Die' ),
			array( 'redirect_home',			'Redirect To Home Page' ),
			array( 'redirect_404',			'Return 404' ),
		);
		$this->m_aBlockSection = array(
			'section_title' => __( 'Choose Firewall Block Response', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'block_response',
					'',
					'none',
					$aRedirectOptions,
					__( 'Block Response', 'wp-simple-firewall' ),
					__( 'Choose how the firewall responds when it blocks a request', 'wp-simple-firewall' ),
					__( 'We recommend dying with a message so you know what might have occurred when the firewall blocks you', 'wp-simple-firewall' )
				),
				array(
					'block_send_email',
					'',
					'N',
					'checkbox',
					__( 'Send Email Report', 'wp-simple-firewall' ),
					__( 'When a visitor is blocked the firewall will send an email to the configured email address', 'wp-simple-firewall' ),
					__( 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host', 'wp-simple-firewall' )
				)
			)
		);
		
		$this->m_aWhitelistSection = array(
			'section_title' => __( 'Whitelists - IPs, Pages, Parameters, and Users that by-pass the Firewall', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'',
					'ip_addresses',
					__( 'Whitelist IP Addresses', 'wp-simple-firewall' ),
					__( 'Choose IP Addresses that are never subjected to Firewall Rules', 'wp-simple-firewall' ),
					sprintf( __( 'Take a new line per address. Your IP address is: %s', 'wp-simple-firewall' ), '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' )
				),
				array(
					'page_params_whitelist',
					'',
					'',
					'comma_separated_lists',
					__( 'Whitelist Parameters', 'wp-simple-firewall' ),
					__( 'Detail pages and parameters that are whitelisted (ignored by the firewall)', 'wp-simple-firewall' ),
					__( 'This should be used with caution and you should only provide parameter names that you must have excluded', 'wp-simple-firewall' )
						.' '.sprintf( __( '%sHelp%s', 'wp-simple-firewall' ), '[<a href="http://icwp.io/2a" target="_blank">', '</a>]' )
				),
				array(
					'whitelist_admins',
					'',
					'N',
					'checkbox',
					__( 'Ignore Administrators', 'wp-simple-firewall' ),
					__( 'Ignore users logged in as Administrator', 'wp-simple-firewall' ),
					__( 'Authenticated administrator users will not be processed by the firewall', 'wp-simple-firewall' )
				)
			)
		);
		
		$this->m_aBlacklistSection = array(
			'section_title' => __( 'Choose IP Addresses To Blacklist', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'ips_blacklist',
					'',
					'',
					'ip_addresses',
					__( 'Blacklist IP Addresses', 'wp-simple-firewall' ),
					__( 'Choose IP Addresses that are always blocked from accessing the site', 'wp-simple-firewall' ),
					__( 'Take a new line per address. Each IP Address must be valid and will be checked', 'wp-simple-firewall' )
				)
			)
		);
		$this->m_aFirewallMiscSection = array(
			'section_title' => __( 'Miscellaneous Plugin Options', 'wp-simple-firewall' ),
			'section_options' => array(
				array(
					'enable_firewall_log',
					'',
					'N',
					'checkbox',
					__( 'Firewall Logging', 'wp-simple-firewall' ),
					__( 'Turn on a detailed Firewall Log', 'wp-simple-firewall' ),
					__( 'Will log every visit to the site and how the firewall processes it. Not recommended to leave on unless you want to debug something and check the firewall is working as you expect', 'wp-simple-firewall' )
				)
			)
		);

		$this->m_aOptions = array(
			$this->m_aFirewallBase,
			$this->m_aBlockSection,
			$this->m_aWhitelistSection,
			$this->m_aBlacklistSection,
			$this->m_aBlockTypesSection,
			$this->m_aFirewallMiscSection
		);
	}

	public function updateHandler() {

		$sCurrentVersion = empty( $this->m_aOptionsValues[ 'current_plugin_version' ] )? '0.0' : $this->m_aOptionsValues[ 'current_plugin_version' ];
		if ( version_compare( $sCurrentVersion, '1.4.0', '<' ) ) {
			$aSettingsKey = array(
				'current_plugin_version',
				'enable_firewall',
				'include_cookie_checks',
				'block_dir_traversal',
				'block_sql_queries',
				'block_wordpress_terms',
				'block_field_truncation',
				'block_exe_file_uploads',
				'block_leading_schema',
				'block_send_email',
				'ips_whitelist',
				'ips_blacklist',
				'page_params_whitelist',
				'block_response',
				'enable_firewall_log',
				'whitelist_admins'
			);
			$this->migrateOptions( $aSettingsKey );
		}//v1.4.0
	}
}

endif;