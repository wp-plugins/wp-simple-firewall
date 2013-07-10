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
						<button type="submit" class="btn btn-primary" name="submit"><?php _hlt_e( 'Clear Log'); ?></button>
					</div>
				</form>
			
				<table class="table table-bordered table-hover table-condensed">
					<tr>
						<th>&nbsp;</th>
						<th>Time</th>
						<th>Message Type</th>
						<th>Message</th>
					</tr>
				<?php foreach( $icwp_firewall_log as $sId => $aLogData ) :
					list( $sRequestIp, $sRequestId ) = explode( '_', $sId );
				?>
					<tr>
						<td colspan="4">IP: <?php echo $sRequestIp; ?> (Request ID: <?php echo $sRequestId; ?>)</td>
					</tr>
					<?php foreach( $aLogData as $aLogItem ) :
						list( $sTime, $sLogType, $sLogMessage ) = $aLogItem;
					?>
						<tr class="row-<?php echo $aLogTypes[$sLogType]; ?>">
							<td>&nbsp;</td>
							<td><?php echo date( 'Y/m/d H:i:s', $sTime ); ?></td>
							<td><?php echo $aLogTypes[$sLogType] ?></td>
							<td><?php echo $sLogMessage; ?></td>
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