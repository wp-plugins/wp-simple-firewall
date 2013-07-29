<?php 
include_once( dirname(__FILE__).'/widgets/icwp_widgets.php' );
$sPluginName = 'WordPress Simple Firewall';

$aLogTypes = array(
	0	=>	'Info',
	1	=>	'Warning',
	2	=>	'Critical'
);
?>
<style>
	tr.row-Info td {
	}
	tr.row-Warning td {
		background-color: #F2D5AE;
	}
	tr.row-Critical td {
		background-color: #DBAFB0;
	}
	tr.row-log-header td {
		border-top: 2px solid #999 !important;
	}
	td.cell-log-type {
		text-align: right !important;
	}
	td .cell-section {
		display: inline-block;
		width: 48%;
	}
	td .section-ip {
	}
	td .section-timestamp {
		text-align: right;
	}
</style>

<div class="wrap">
	<div class="bootstrap-wpadmin">

		<div class="page-header">
			<a href="http://icwp.io/t" target="_blank"><div class="icon32" id="icontrolwp-icon"><br /></div></a>
			<h2>Firewall Log :: <?php echo $sPluginName; ?> Plugin (from iControlWP)</h2>
		</div>
		
		<div class="row">
			<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
			<?php if ( !$icwp_firewall_log ) : ?>
				<?php echo 'There are currently no logs to display.'; ?>
			<?php else : ?>
			
				<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
					<?php
						wp_nonce_field( $icwp_nonce_field );
					?>
					<div class="form-actions">
						<input type="hidden" name="icwp_plugin_form_submit" value="Y" />
						<button type="submit" class="btn btn-primary" name="clear_log_submit"><?php _hlt_e( 'Clear Log'); ?></button>
					</div>
				</form>
			
				<table class="table table-bordered table-hover table-condensed">
					<tr>
						<th>Message Type</th>
						<th>Message</th>
					</tr>
				<?php foreach( $icwp_firewall_log as $sId => $aLogData ) : ?>
					<tr class="row-log-header">
						<td>IP: <strong><?php echo $aLogData['ip']; ?></strong></td>
						<td colspan="2">
							<span class="cell-section section-ip">
								[ <a href="http://whois.domaintools.com/<?php echo $aLogData['ip']; ?>" target="_blank">IPWHOIS Lookup</a> ]
								[
								<?php if ( in_array( $aLogData['ip_long'], $icwp_ip_blacklist ) ) : ?>
									<a href="<?php echo $icwp_form_action; ?>&unblackip=<?php echo $aLogData['ip']; ?>&nonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1">Remove From Blacklist</a>
								<?php else: ?>
									<a href="<?php echo $icwp_form_action; ?>&blackip=<?php echo $aLogData['ip']; ?>&nonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1">Add To Blacklist</a>
								<?php endif; ?>
								]
								[
								<?php if ( in_array( $aLogData['ip_long'], $icwp_ip_whitelist ) ) : ?>
									<a href="<?php echo $icwp_form_action; ?>&unwhiteip=<?php echo $aLogData['ip']; ?>&nonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1">Remove From Whitelist</a>
								<?php else: ?>
									<a href="<?php echo $icwp_form_action; ?>&whiteip=<?php echo $aLogData['ip']; ?>&nonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1">Add To Whitelist</a>
								<?php endif; ?>
								]
							</span>
							<span class="cell-section section-timestamp"><?php echo date( 'Y/m/d H:i:s', $aLogData['created_at'] ); ?></span>
						</td>
					</tr>
					<?php
					$aMessages = unserialize( $aLogData['messages'] );
					foreach( $aMessages as $aLogItem ) :
						list( $sLogType, $sLogMessage ) = $aLogItem;
					?>
						<tr class="row-<?php echo $aLogTypes[$sLogType]; ?>">
							<td class="cell-log-type"><?php echo $aLogTypes[$sLogType] ?></td>
							<td><?php echo esc_attr($sLogMessage); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</table>

			<?php endif; ?>
			</div><!-- / span9 -->
		
			<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
		  		<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
			<?php endif; ?>
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