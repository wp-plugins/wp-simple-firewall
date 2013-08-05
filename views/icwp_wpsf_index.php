<?php 
include_once( dirname(__FILE__).ICWP_DS.'icwp_options_helper.php' );
include_once( dirname(__FILE__).ICWP_DS.'widgets'.ICWP_DS.'icwp_widgets.php' );
$sPluginName = 'WordPress Simple Firewall';
$fFirewallOn = $icwp_aMainOptions['enable_firewall'] == 'Y';
$fLoginProtectOn = $icwp_aMainOptions['enable_login_protect'] == 'Y';
?>

<div class="wrap">
	<div class="bootstrap-wpadmin">
		<div class="page-header">
			<a href="http://icwp.io/t" target="_blank"><div class="icon32" id="icontrolwp-icon"><br /></div></a>
			<h2>Dashboard :: <?php echo $sPluginName; ?> Plugin (from iControlWP)</h2>
		</div>

		<?php include_once( dirname(__FILE__).'/widgets/icwp_common_widgets.php' ); ?>
		
		<div class="row">
			<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
			
				<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
				<?php
					wp_nonce_field( $icwp_nonce_field );
					printAllPluginOptionsForm( $icwp_aAllOptions, $icwp_var_prefix, 1 );
				?>
					<div class="form-actions">
						<input type="hidden" name="<?php echo $icwp_var_prefix; ?>all_options_input" value="<?php echo $icwp_all_options_input; ?>" />
						<input type="hidden" name="icwp_plugin_form_submit" value="Y" />
						<button type="submit" class="btn btn-primary" name="submit"><?php _hlt_e( 'Save All Settings'); ?></button>
					</div>
				</form>
				
			</div><!-- / span9 -->
		
			<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
		  		<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
			<?php endif; ?>
		</div><!-- / row -->

		<?php if ( $icwp_fShowAds ) : ?>
		<div class="row" id="worpit_promo">
		  <div class="span12">
		  	<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
		  </div>
		</div><!-- / row -->

		<div class="row" id="developer_channel_promo">
		  <div class="span12">
		  	<?php echo getWidgetIframeHtml('dashboard-widget-developerchannel-wtb'); ?>
		  </div>
		</div><!-- / row -->
		
		<?php endif; ?>
		
		<div class="row" id="tbs_docs">
			<h2>Plugin Configuration Summary</h2>
			<div class="span6" id="tbs_docs_shortcodes">
			  <div class="well">
				<h3>Firewall Configuration</h3>
				
				<h4 style="margin-top:20px;">Firewall is currently <?php echo $fFirewallOn ? 'ON' : 'OFF'; ?>.
				[ <a href="admin.php?page=icwp-wpsf-firewall-config">Configure Now</a> ]</h4>
				<?php if ( $fFirewallOn ) : ?>
					<ul>
						<li>Firewall logging is: <?php echo $icwp_aFirewallOptions['enable_firewall'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>When the firewall blocks a visit, it will:
							<?php
							if( $icwp_aFirewallOptions['block_response'] == 'redirect_die' ) {
								echo 'Die.';
							}
							else if ( $icwp_aFirewallOptions['block_response'] == 'redirect_die_message' ) {
								echo 'Die with a message.';
							}
							else if ( $icwp_aFirewallOptions['block_response'] == 'redirect_home' ) {
								echo 'Redirect to home.';
							}
							else if ( $icwp_aFirewallOptions['block_response'] == 'redirect_404' ) {
								echo 'Redirect to 404 page.';
							}
							else {
								echo 'Unknown.';	
							}
						?>
						</li>
						<?php if ( isset($icwp_aFirewallOptions['ips_whitelist']['ips']) ) : ?>
							<li>You have <?php echo count( $icwp_aFirewallOptions['ips_whitelist']['ips'] );?> whitelisted IP addresses:
								<?php foreach( $icwp_aFirewallOptions['ips_whitelist']['ips'] as $sIp ) : ?>
								<br /><?php echo long2ip($sIp); ?> labelled as <?php echo $icwp_aFirewallOptions['ips_whitelist']['meta'][md5( $sIp )]?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>
						
						<?php if ( isset($icwp_aFirewallOptions['ips_blacklist']['ips']) ) : ?>
							<li>You have <?php echo count( $icwp_aFirewallOptions['ips_blacklist']['ips'] );?> blacklisted IP addresses:
								<?php foreach( $icwp_aFirewallOptions['ips_blacklist']['ips'] as $sIp ) : ?>
								<br /><?php echo long2ip($sIp); ?> labelled as <?php echo $icwp_aFirewallOptions['ips_whitelist']['meta'][md5( $sIp )]?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>
						<li>Firewall blocks WP Login Access: <?php echo $icwp_aFirewallOptions['block_wplogin_access'] == 'Y' ? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks Directory Traversals: <?php echo $icwp_aFirewallOptions['block_dir_traversal'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks SQL Queries: <?php echo $icwp_aFirewallOptions['block_sql_queries'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks WordPress Specific Terms: <?php echo $icwp_aFirewallOptions['block_wordpress_terms'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks Field Truncation Attacks: <?php echo $icwp_aFirewallOptions['block_field_truncation'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks Executable File Uploads:<?php echo $icwp_aFirewallOptions['block_exe_file_uploads'] == 'Y'? 'ON' : 'OFF'; ?> </li>
						<li>Firewall blocks Leading Schemas (HTTPS / HTTP): <?php echo $icwp_aFirewallOptions['block_leading_schema'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Firewall Logging: <?php echo ($icwp_aFirewallOptions['enable_firewall_log']  == 'Y')? 'ON' : 'OFF';?></li>
					</ul>
				<?php endif; ?>
				
				<h4 style="margin-top:20px;">Login Protection is currently <?php echo $fLoginProtectOn? 'ON' : 'OFF'; ?>.
				[ <a href="admin.php?page=icwp-wpsf-login-protect">Configure Now</a> ]</h4>
				<?php if ( $fLoginProtectOn ) : ?>
					<ul>
						<li>Two Factor Login Authentication is: <?php echo $icwp_aLoginProtectOptions['enable_two_factor_auth_by_ip'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Two Factor Login By Pass is: <?php echo $icwp_aLoginProtectOptions['enable_two_factor_bypass_on_email_fail'] == 'Y'? 'ON' : 'OFF'; ?></li>
						<li>Login Cooldown Interval is: <?php echo ($icwp_aLoginProtectOptions['login_limit_interval'] == 0)? 'OFF' : $icwp_aLoginProtectOptions['login_limit_interval'].' seconds'; ?></li>
						<li>Login Form GASP Protection: <?php echo ($icwp_aLoginProtectOptions['enable_login_gasp_check']  == 'Y')? 'ON' : 'OFF';?></li>
						<li>Login Protect Logging: <?php echo ($icwp_aLoginProtectOptions['enable_login_protect_log']  == 'Y')? 'ON' : 'OFF';?></li>
					</ul>
				<?php endif; ?>
			  </div>
		  </div><!-- / span6 -->
		  <div class="span6" id="tbs_docs_examples">
			  <div class="well">
				<h3>v1.4.x Release:</h3>
				<p>The following summarises the main changes to the plugin in the 1.4.x release</p>
				<p><span class="label ">new</span> means for the absolute latest release.</p>
				<?php
				$aNewLog = array(
					'Brand new plugin options system making them more efficient, easier to manage/update, using fewer WordPress database options',
					'Huge improvements on database calls and efficiency in loading plugin options'
				);
				?>
				<ul>
				<?php foreach( $aNewLog as $sItem ) : ?>
					<li><span class="label">new</span> <?php echo $sItem; ?></li>
				<?php endforeach; ?>
				</ul>
				<?php
				$aLog = array(
				);
				?>
				<ul>
				<?php foreach( $aLog as $sItem ) : ?>
					<li><?php echo $sItem; ?></li>
				<?php endforeach; ?>
				</ul>
				
				<?php
				$aLog = array(

					'1.3.x'	=> array(
						"New Feature - Email Throttle. It will prevent you getting bombarded by 1000s of emails in case you're hit by a bot.",
						"Another Firewall die() option. New option will print a message and uses the wp_die() function instead.",
						"Option to separately log Login Protect features.",
						"Refactored and improved the logging system.",
						"Option to by-pass 2-factor authentication in the case sending the verification email fails.",
						"Login Protect checking now better logs out users immediately with a redirect.",
						"We now escape the log data being printed - just in case there's any HTML/JS etc in there we don't want.",
						"Optimized and cleaned a lot of the option caching code to improve reliability and performance (more to come).",
					),
					
					'1.2.x'	=> array(
						'New Feature - Ability to import settings from WordPress Firewall 2 Plugin.',
						'New Feature - Login Form GASP-based Anti-Bot Protection.',
						'New Feature - Login Cooldown Interval.',
						'Performance optimizations.',
						'UI Cleanup and code improvements.',
						'Added new Login Protect feature where you can add 2-Factor Authentication to your WordPress user logins.',
						'Improved method for processing the IP address lists to be more cross-platform reliable.',
						'Improved .htaccess rules (thanks MickeyRoush).',
						'Mailing method now uses WP_MAIL.'
					),
					
					'1.1.x'	=> array(
						'Option to check Cookies values in firewall testing.',
						'Ability to whitelist particular pages and their parameters.',
						'Quite a few improvements made to the reliability of the firewall processing.',
						'Option to completely ignore logged-in Administrators from the Firewall processing (they wont even trigger logging etc).',
						'Ability to (un)blacklist and (un)whitelist IP addresses directly from within the log.',
						'Helpful link to IP WHOIS from within the log.',
						'Firewall logging now has its own dedicated database table.',
						'Fix: Block email not showing the IPv4 friendly address.',
						'You can now specify IP ranges in whitelists and blacklists.',
						'You can now specify which email address to send the notification emails.',
						"You can now add a comment to IP addresses in the whitelist/blacklist. To do this, write your IP address then type a SPACE and write whatever you want (don't take a new line').",
						'You can now set to delete ALL firewall settings when you deactivate the plugin.',
						'Improved formatting of the firewall log.'
					)
				);
				?>
				<?php foreach( $aLog as $sVersion => $aItems ) : ?>
				<h3>Change log for the v<?php echo $sVersion; ?> release:</h3>
				<ul>
					<?php foreach( $aItems as $sItem ) : ?>
						<li><?php echo $sItem; ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endforeach; ?>
			  </div>
		  </div><!-- / span6 -->
		</div><!-- / row -->
		
		<div class="row">
		  <div class="span6">
		  </div><!-- / span6 -->
		  <div class="span6">
		  	<p></p>
		  </div><!-- / span6 -->
		</div><!-- / row -->
		
	</div><!-- / bootstrap-wpadmin -->
	<?php include_once( dirname(__FILE__).'/include_js.php' ); ?>
</div><!-- / wrap -->