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

require_once( dirname(__FILE__).'/icwp-import-base-processor.php' );

if ( !class_exists('ICWP_ImportWpf2Processor') ):

class ICWP_ImportWpf2Processor extends ICWP_ImportBaseProcessor {
	
	/**
	 * @var ICWP_OptionsHandler_Wpsf
	 */
	protected $m_oWpsfOptions;
	/**
	 * @var ICWP_OptionsHandler_Firewall
	 */
	protected $m_oFirewallOptions;
	
	public function __construct( $inoPluginOptions, $inoFirewallOptions ) {
		
		$this->m_oWpsfOptions = $inoPluginOptions;
		$this->m_oFirewallOptions = $inoFirewallOptions;
		
		$this->m_aOptionsMap = array(
			'WP_firewall_redirect_page'				=> 'block_response',
			'WP_firewall_email_enable'				=> 'block_send_email',
			/* 'WP_firewall_email_type'				=> '', unused */
			'WP_firewall_email_address'				=> 'block_send_email_address',
			'WP_firewall_exclude_directory'			=> 'block_dir_traversal',
			'WP_firewall_exclude_queries'			=> 'block_sql_queries',
			'WP_firewall_exclude_terms'				=> 'block_wordpress_terms',
			'WP_firewall_exclude_spaces'			=> 'block_field_truncation',
			'WP_firewall_exclude_file'				=> 'block_exe_file_uploads',
			'WP_firewall_exclude_http'				=> 'block_leading_schema',
			'WP_firewall_whitelisted_ip'			=> 'ips_whitelist',
			'WP_firewall_whitelisted_page'			=> 'page_params_whitelist',
			'WP_firewall_whitelisted_variable'		=> 'page_params_whitelist',
			/* 'WP_firewall_plugin_url'				=> '', unused */
			/* 'WP_firewall_default_whitelisted_page'	=> '', unused */
			/* 'WP_firewall_previous_attack_var'	=> '', unused */
			/* 'WP_firewall_previous_attack_ip'		=> '', unused */
			/* 'WP_firewall_email_limit'			=> '', unused */
		);
	}
	
	protected function mapOptionsToTarget() {
		
		$aPluginOptions = array(
			'block_send_email_address'
		);
		
		$aFirewallOptions = array(
			'block_response',
			'block_send_email',
			'block_send_email_address',
			'block_dir_traversal',
			'block_sql_queries',
			'block_wordpress_terms',
			'block_field_truncation',
			'block_exe_file_uploads',
			'block_leading_schema',
			'ips_whitelist',
			'page_params_whitelist',
			'page_params_whitelist'
		);
		
		//redirect option
		if ( $this->m_aSourceValues[ 'WP_firewall_redirect_page' ] == 'homepage' ) {
			$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_redirect_page'], 'redirect_home' );
// 			$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_redirect_page'], 'redirect_home' );
		}
		else if ( $this->m_aSourceValues[ 'WP_firewall_redirect_page' ] == '404page' ) {
			$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_redirect_page'], 'redirect_404' );
// 			$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_redirect_page'], 'redirect_404' );
		}
		
		//Email enable
		if ( $this->m_aSourceValues[ 'WP_firewall_email_enable' ] == 'enable' ) {
			$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_email_enable'], 'Y' );
// 			$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_email_enable'], 'Y' );
		}
		else { // actually the WPF2 doesn't give the option to turn off email(!)
			$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_email_enable'], 'N' );
// 			$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_email_enable'], 'N' );
		}
		
		//Email address
		$this->m_oWpsfOptions->setOpt( $this->m_aOptionsMap['WP_firewall_email_address'], $this->m_aSourceValues[ 'WP_firewall_email_address' ] );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_email_address'], $this->m_aSourceValues[ 'WP_firewall_email_address' ] );
		
		//Firewall block options - uses 'allow' to signify the block is in place.  :|
		$sTargetValue = ( $this->m_aSourceValues[ 'WP_firewall_exclude_directory' ]	== 'allow' )? 'Y' : 'N';
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_exclude_directory'], $sTargetValue );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_exclude_directory'], $sTargetValue );
		$sTargetValue = ( $this->m_aSourceValues[ 'WP_firewall_exclude_queries' ]	== 'allow' )? 'Y' : 'N';
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_exclude_queries'], $sTargetValue );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_exclude_queries'], $sTargetValue );
		$sTargetValue = ( $this->m_aSourceValues[ 'WP_firewall_exclude_terms' ]		== 'allow' )? 'Y' : 'N';
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_exclude_terms'], $sTargetValue );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_exclude_terms'], $sTargetValue );
		$sTargetValue = ( $this->m_aSourceValues[ 'WP_firewall_exclude_spaces' ]	== 'allow' )? 'Y' : 'N';
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_exclude_spaces'], $sTargetValue );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_exclude_spaces'], $sTargetValue );
		$sTargetValue = ( $this->m_aSourceValues[ 'WP_firewall_exclude_file' ]		== 'allow' )? 'Y' : 'N';
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_exclude_file'], $sTargetValue );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_exclude_file'], $sTargetValue );
		$sTargetValue = ( $this->m_aSourceValues[ 'WP_firewall_exclude_http' ]		== 'allow' )? 'Y' : 'N';
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_exclude_http'], $sTargetValue );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_exclude_http'], $sTargetValue );
		
		// Cookie checking - WPF2 does this by default
// 		$this->updateTargetOption( 'include_cookie_checks', 'Y' );
		$this->m_oFirewallOptions->setOpt( 'include_cookie_checks', 'Y' );
		
		// Whitelisted IPs
		$aSourceIps = maybe_unserialize( $this->m_aSourceValues[ 'WP_firewall_whitelisted_ip' ] );
		$aNewList = array();
		foreach( $aSourceIps as $sIp ) {
			$aNewList[ $sIp ] = '';
		}
		$aTargetIpWhitelist = $this->m_oFirewallOptions->getOpt( $this->m_aOptionsMap['WP_firewall_whitelisted_ip'] );
		if ( empty( $aTargetIpWhitelist ) ) {
			$aTargetIpWhitelist = array();
		}
		$this->m_oFirewallOptions->setOpt( $this->m_aOptionsMap['WP_firewall_whitelisted_ip'], ICWP_WPSF_DataProcessor::Add_New_Raw_Ips( $aTargetIpWhitelist, $aNewList ) );
// 		$this->updateTargetOption( $this->m_aOptionsMap['WP_firewall_whitelisted_ip'], ICWP_WPSF_DataProcessor::Add_New_Raw_Ips( $aTargetIpWhitelist, $aNewList ) );

		// Whitelisted Pages and Vars... maybe later :|
	}

}

endif;