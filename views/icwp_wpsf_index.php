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
						<li>You have <?php echo count($icwp_aIpWhitelist);?> whitelisted IP addresses.
							<?php foreach( $icwp_aIpWhitelist as $sIp ) : ?>
							<p><?php echo $sIp; ?><p>
							<?php endforeach; ?>
						</li>
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