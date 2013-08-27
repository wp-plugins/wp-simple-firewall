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
	
	public function definePluginOptions() {

		$this->m_aDirectSaveOptions = array( 'enable_firewall', 'whitelist_admins' );
		
		$this->m_aFirewallBase = 	array(
			'section_title' => 'Enable WordPress Firewall',
			'section_options' => array(
				array(
					'enable_firewall',
					'',	'N',
					'checkbox',
					'Enable Firewall',
					'Enable (or Disable) The WordPress Firewall Feature',
					'Regardless of any other settings, this option will turn Off the Firewall feature, or enable your selected Firewall options.'
				)
			)
		);
		$this->m_aBlockTypesSection =  array(
			'section_title' => 'Firewall Blocking Options',
			'section_options' => array(
				array(
					'include_cookie_checks',
					'',
					'N',
					'checkbox',
					'Include Cookies',
					'Also Test Cookie Values In Firewall Tests',
					'The firewall will test GET and POST, but with this option checked, it will also COOKIE values for the same.'
				),
				array(
					'block_dir_traversal',
					'',
					'N',
					'checkbox',
					'Directory Traversals',
					'Block Directory Traversals',
					'This will block directory traversal paths in in application parameters (../, ../../etc/passwd, etc.)'
				),
				array(
					'block_sql_queries',
					'',
					'N',
					'checkbox',
					'SQL Queries',
					'Block SQL Queries',
					'This will block in application parameters (union select, concat(, /**/, etc.).'
				),
				array(
					'block_wordpress_terms',
					'',
					'N',
					'checkbox',
					'WordPress Terms',
					'Block WordPress Specific Terms',
					'This will block WordPress specific terms in application parameters (wp_, user_login, etc.).'
				),
				array(
					'block_field_truncation',
					'',
					'N',
					'checkbox',
					'Field Truncation',
					'Block Field Truncation Attacks',
					'This will block field truncation attacks in application parameters.'
				),
				array(
					'block_exe_file_uploads',
					'',
					'N',
					'checkbox',
					'Exe File Uploads',
					'Block Executable File Uploads',
					'This will block executable file uploads (.php, .exe, etc.).'
				),
				array(
					'block_leading_schema',
					'',
					'N',
					'checkbox',
					'Leading Schemas',
					'Block Leading Schemas (HTTPS / HTTP)',
					'This will block leading schemas http:// and https:// in application parameters (off by default; may cause problems with many plugins).'
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
			'section_title' => 'Choose Firewall Block Response',
			'section_options' => array(
				array( 'block_response',	'',	'none',	$aRedirectOptions,	'Block Response',	'Choose how the firewall responds when it blocks a request', '' ),
				array( 'block_send_email',	'',	'N',	'checkbox',	'Send Email Report',	'When a visitor is blocked it will send an email to the blog admin', 'Use with caution - if you get hit by automated bots you may send out too many emails and you could get blocked by your host.' )
			)
		);
		
		$this->m_aWhitelistSection = array(
			'section_title' => 'Whitelist - IPs, Pages, Parameters, and Users that by-pass the Firewall',
			'section_options' => array(
				array(
					'ips_whitelist',
					'',
					'',
					'ip_addresses',
					'Whitelist IP Addresses',
					'Choose IP Addresses that are never subjected to Firewall Rules',
					sprintf( 'Take a new line per address. Your IP address is: %s', '<span class="code">'.$this->getVisitorIpAddress( false ).'</span>' )
				),
				array(
					'page_params_whitelist',
					'',
					'',
					'comma_separated_lists',
					'Whitelist Paramaters',
					'Detail pages and parameters that are whitelisted (ignored)',
					'This should be used with caution and you should only provide parameter names that you need to have excluded.'
						.' [<a href="http://icwp.io/2a" target="_blank">Help</a>]'
				),
				array(
					'whitelist_admins',
					'',
					'N',
					'checkbox',
					'Ignore Administrators',
					'Ignore users logged in as Administrator',
					'Authenticated administrator users will not be processed by the firewall.'
				)
			)
		);
		
		$this->m_aBlacklistSection = array(
			'section_title' => 'Choose IP Addresses To Blacklist',
			'section_options' => array(
				array(
					'ips_blacklist',
					'',
					'',
					'ip_addresses',
					'Blacklist IP Addresses',
					'Choose IP Addresses that are always blocked access to the site',
					'Take a new line per address. Each IP Address must be valid and will be checked.'
				)
			)
		);
		$this->m_aFirewallMiscSection = array(
			'section_title' => 'Miscellaneous Plugin Options',
			'section_options' => array(
				array(
					'enable_firewall_log',
					'',	'N',
					'checkbox',
					'Firewall Logging',
					'Turn on a detailed Firewall Log',
					'Will log every visit to the site and how the firewall processes it. Not recommended to leave on unless you want to debug something and check the firewall is working as you expect.'
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
		}//'1.4.0', '<'
	}
}

endif;