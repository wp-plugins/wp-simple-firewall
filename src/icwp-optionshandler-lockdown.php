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

if ( !class_exists('ICWP_OptionsHandler_Lockdown') ):

class ICWP_OptionsHandler_Lockdown extends ICWP_OptionsHandler_Base_WPSF {
	
	const StoreName = 'lockdown_options';
	
	public function __construct( $insPrefix, $insVersion, $infInit = false ) {
		parent::__construct( $insPrefix, self::StoreName, $insVersion, $infInit );
	}
	
	public function doPrePluginOptionsSave() {
	}
	
	public function definePluginOptions() {

		$aBase = array(
			'section_title' => 'Enable Lockdown Feature',
			'section_options' => array(
				array(
					'enable_lockdown',
					'',
					'N',
					'checkbox',
					'Enable Lockdown',
					'Enable (or Disable) The Lockdown Feature',
					'Regardless of any other settings, this option will turn Off the Lockdown feature, or enable your selected Lockdown options.'
				)
			)
		);
		$aAccess = array(
			'section_title' => 'Access Options',
			'section_options' => array(
				array(
					'disable_file_editing',
					'',
					'N',
					'checkbox',
					'Disable File Editing',
					'Disable Ability To Edit Files',
					'Removes the option to directly edit any files from within the WordPress admin area.
					<br />Equivalent to setting DISALLOW_FILE_EDIT to TRUE.'
				)
			)
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

		$this->m_aOptions = array(
			$aBase,
			$aAccess
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