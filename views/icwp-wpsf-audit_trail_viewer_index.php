<?php
include_once( 'icwp-wpsf-config_header.php' );

$aLogTypes = array(
	0	=>	_wpsf__('Info'),
	1	=>	_wpsf__('Warning'),
	2	=>	_wpsf__('Critical')
);

function printAuditTrailTable( $aAuditData ) {
	?>
	<table class="table table-bordered">
		<tr>
			<th><?php _wpsf_e('Time'); ?></th>
			<th><?php _wpsf_e('Username'); ?></th>
			<th><?php _wpsf_e('Event'); ?></th>
			<th><?php _wpsf_e('Category'); ?></th>
			<th><?php _wpsf_e('Message'); ?></th>
		</tr>
		<?php foreach( $aAuditData as $aAuditEntry ) : ?>
			<tr>
				<td><?php echo date( 'Y/m/d H:i:s', $aAuditEntry['created_at'] ); ?></td>
				<td><?php echo $aAuditEntry['wp_username']; ?></td>
				<td><?php echo $aAuditEntry['event']; ?></td>
				<td><?php echo $aAuditEntry['category']; ?></td>
				<td><?php echo $aAuditEntry['message']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php
}
?>
	<div class="row">
		<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">

			<?php printAuditTrailTable($icwp_aAuditDataUsers); ?>

			<?php printAuditTrailTable($icwp_aAuditDataPlugins); ?>

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
		}
		td .section-ip {
			width: 68%;
		}
		td .section-timestamp {
			text-align: right;
			width: 28%;
		}
	</style>

<?php include_once( 'icwp-wpsf-config_footer.php' );