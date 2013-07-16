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
				
				<?php if ( !$icwp_fFirewallOn ) : ?>
					Firewall is currently OFF.
				<?php else: ?>
				
					<ul>
						<li>Firewall is: <?php echo $icwp_fFirewallOn? 'ON' : 'OFF'; ?></li>
						<li>Firewall logging is: <?php echo $icwp_fFirewallLogOn? 'ON' : 'OFF'; ?></li>
						<li>When the firewall blocks a visit, it <?php echo $icwp_fBlockSendEmail? 'will': 'will not'; ?> send an email and then :
							<?php
							if( $icwp_sBlockResponse == 'redirect_die' ) {
								echo 'die.';
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
			  </div>
		  </div><!-- / span6 -->
		  <div class="span6" id="tbs_docs_examples">
			  <div class="well">
				<h3>Change log for the v1.1.x release:</h3>
				<p>The following summarises the main changes to the plugin in the 1.1.x release</p>
				<p><span class="label ">new</span> means for the absolute latest release.</p>
				<?php 
				$aNewLog = array(
					'Option to check Cookies values in firewall testing.'
				);
				?>
				<ul>
				<?php foreach( $aNewLog as $sItem ) : ?>
					<li><span class="label">new</span> <?php echo $sItem; ?></li>
				<?php endforeach; ?>
				</ul>
				<?php
				$aLog = array(
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
				);
				?>
				<ul>
				<?php foreach( $aLog as $sItem ) : ?>
					<li><?php echo $sItem; ?></li>
				<?php endforeach; ?>
				</ul>
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