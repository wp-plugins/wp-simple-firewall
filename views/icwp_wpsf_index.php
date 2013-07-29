<?php 
include_once( dirname(__FILE__).'/widgets/icwp_widgets.php' );
$sPluginName = 'WordPress Simple Firewall';
?>

<div class="wrap">
	<div class="bootstrap-wpadmin">

		<div class="page-header">
			<a href="http://icwp.io/t" target="_blank"><div class="icon32" id="icontrolwp-icon"><br /></div></a>
			<h2>Dashboard :: <?php echo $sPluginName; ?> Plugin (from iControlWP)</h2>
		</div>

		<?php include_once( dirname(__FILE__).'/widgets/icwp_common_widgets.php' ); ?>

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
		  <div class="span6" id="tbs_docs_shortcodes">
			  <div class="well">
				<h3>Current Firewall Configuration</h3>
				<p>The following summarises your current firewall configuration:</p>
				
				<h4 style="margin-top:20px;">Firewall is currently <?php echo $icwp_fFirewallOn? 'ON' : 'OFF'; ?>. [ <a href="admin.php?page=icwp-wpsf-firewall-config">Turn it <?php echo !$icwp_fFirewallOn? 'ON' : 'OFF'; ?></a> ]</h4>
				<?php if ( $icwp_fFirewallOn ) : ?>
					<ul>
						<li>Firewall logging is: <?php echo $icwp_fFirewallLogOn? 'ON' : 'OFF'; ?></li>
						<li>When the firewall blocks a visit, it <?php echo $icwp_fBlockSendEmail? 'will': 'will not'; ?> send an email and then :
							<?php
							if( $icwp_sBlockResponse == 'redirect_die' ) {
								echo 'die.';
							}
							else if ( $icwp_sBlockResponse == 'redirect_die_message' ) {
								echo 'Dies with a message.';
							}
							else if ( $icwp_sBlockResponse == 'redirect_home' ) {
								echo 'Redirect to home.';
							}
							else if ( $icwp_sBlockResponse == 'redirect_404' ) {
								echo 'Redirect to 404 page.';
							}
							else {
								echo 'Unknown.';	
							}
						?>
						</li>
						<?php if ( isset($icwp_aIpWhitelist['ips']) ) : ?>
							<li>You have <?php echo count( $icwp_aIpWhitelist['ips'] );?> whitelisted IP addresses:
								<?php foreach( $icwp_aIpWhitelist['ips'] as $sIp ) : ?>
								<br /><?php echo long2ip($sIp); ?> labelled as <?php echo $icwp_aIpWhitelist['meta'][md5( $sIp )]?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>
						
						<?php if ( isset($icwp_aIpBlacklist['ips']) ) : ?>
							<li>You have <?php echo count( $icwp_aIpBlacklist['ips'] );?> blacklisted IP addresses:
								<?php foreach( $icwp_aIpBlacklist['ips'] as $sIp ) : ?>
								<br /><?php echo long2ip($sIp); ?> labelled as <?php echo $icwp_aIpWhitelist['meta'][md5( $sIp )]?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>
						<li>Firewall blocks WP Login Access: <?php echo $icwp_fBlockLogin? 'ON' : 'OFF'; ?>
							<?php if ( $icwp_fBlockLogin && count($icwp_aIpWhitelist) == 0 ) : ?>
								<strong>But, there are no whitelisted IPs so it is effectively off.</strong>
							<?php endif; ?>
						</li>
						<li>Firewall blocks Directory Traversals: <?php echo $icwp_fBlockDirTrav? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks SQL Queries: <?php echo $icwp_fBlockSql? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks WordPress Specific Terms: <?php echo $icwp_fBlockWpTerms? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks Field Truncation Attacks: <?php echo $icwp_fBlockFieldTrun? 'ON' : 'OFF'; ?></li>
						<li>Firewall blocks Executable File Uploads:<?php echo $icwp_fBlockExeFile? 'ON' : 'OFF'; ?> </li>
						<li>Firewall blocks Leading Schemas (HTTPS / HTTP): <?php echo $icwp_fBlockSchema? 'ON' : 'OFF'; ?></li>
					</ul>
				<?php endif; ?>
				
				<h4 style="margin-top:20px;">Login Protection is currently <?php echo $icwp_fLoginProtectOn? 'ON' : 'OFF'; ?>. [ <a href="admin.php?page=icwp-wpsf-login-protect">Turn it <?php echo !$icwp_fLoginProtectOn? 'ON' : 'OFF'; ?></a> ]</h4>
				<?php if ( $icwp_fLoginProtectOn ) : ?>
					<ul>
						<li>Two Factor Login Authentication is: <?php echo $icwp_fTwoFactorIpOn? 'ON' : 'OFF'; ?></li>
						<li>Login Cooldown Interval is: <?php echo ($icwp_sLoginLimitInterval == 0)? 'OFF' : $icwp_sLoginLimitInterval.' seconds'; ?></li>
					</ul>
				<?php endif; ?>
			  </div>
		  </div><!-- / span6 -->
		  <div class="span6" id="tbs_docs_examples">
			  <div class="well">
				<h3>Change log for the v1.3.x release:</h3>
				<p>The following summarises the main changes to the plugin in the 1.3.x release</p>
				<p><span class="label ">new</span> means for the absolute latest release.</p>
				<?php
				$aNewLog = array(
					"Email Throttle Feature - this will prevent you getting bombarded by 1000s of emails in case you're hit by a bot.",
					"Another Firewall die() option. New option will print a message and uses the wp_die() function instead.",
					"Option to separately log Login Protect features.",
					"Refactored and improved the logging system.",
					"Option to by-pass 2-factor authentication in the case sending the verification email fails.",
					"Login Protect checking now better logs out users immediately with a redirect.",
					"We now escape the log data being printed - just in case there's any HTML/JS etc in there we don't want.",
					"Optimized and cleaned a lot of the option caching code to improve reliability and performance (more to come).",
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

</div><!-- / wrap -->