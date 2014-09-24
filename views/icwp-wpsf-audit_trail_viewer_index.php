<?php
include_once( 'icwp-wpsf-config_header.php' );

$aLogTypes = array(
	0	=>	_wpsf__('Info'),
	1	=>	_wpsf__('Warning'),
	2	=>	_wpsf__('Critical')
);

function printAuditTrailTable( $aAuditData ) {
	if ( empty( $aAuditData ) ) {
		return;
	}
	?>
	<table class="table table-hover table-striped table-audit_trail">
		<tr>
			<th class="cell-time"><?php _wpsf_e('Time'); ?></th>
			<th class="cell-event"><?php _wpsf_e('Event'); ?></th>
			<th class="cell-message"><?php _wpsf_e('Message'); ?></th>
			<th class="cell-username"><?php _wpsf_e('Username'); ?></th>
			<th class="cell-category"><?php _wpsf_e('Category'); ?></th>
		</tr>
		<?php foreach( $aAuditData as $aAuditEntry ) : ?>
			<tr>
				<td><?php echo date( 'Y/m/d', $aAuditEntry['created_at'] ).'<br />'.date( 'H:i:s', $aAuditEntry['created_at'] ); ?></td>
				<td><?php echo $aAuditEntry['event']; ?></td>
				<td><?php echo $aAuditEntry['message']; ?></td>
				<td><?php echo $aAuditEntry['wp_username']; ?></td>
				<td><?php echo $aAuditEntry['category']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php
}
?>
	<div class="row">
		<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">

			<h4 class="table-title"><?php _wpsf_e( 'Users' ); ?></h4>
			<?php printAuditTrailTable( $icwp_aAuditDataUsers ); ?>

			<h4 class="table-title"><?php _wpsf_e( 'Plugins' ); ?></h4>
			<?php printAuditTrailTable( $icwp_aAuditDataPlugins ); ?>

			<h4 class="table-title"><?php _wpsf_e( 'Themes' ); ?></h4>
			<?php printAuditTrailTable( $icwp_aAuditDataThemes ); ?>

			<h4 class="table-title"><?php _wpsf_e( 'WordPress' ); ?></h4>
			<?php printAuditTrailTable( $icwp_aAuditDataWordpress ); ?>

			<h4 class="table-title"><?php _wpsf_e( 'Posts' ); ?></h4>
			<?php printAuditTrailTable( $icwp_aAuditDataPosts ); ?>

			<h4 class="table-title"><?php _wpsf_e( 'Emails' ); ?></h4>
			<?php printAuditTrailTable( $icwp_aAuditDataEmails ); ?>

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

		h4.table-title {
			font-size: 20px;
			margin: 20px 0 10px 5px;
		}
		table.table.table-audit_trail {
			border: 2px solid #777777;
			margin-bottom: 40px;
		}
		th.cell-time {
			width: 90px;
			max-width: 90px;
		}
		th.cell-username {
			width: 120px;
			max-width: 120px;
		}
		th.cell-event {
			width: 150px;
			max-width: 150px;
		}
		th.cell-category {
			width: 80px;
			max-width: 80px;
		}
		th.cell-message {
		}
		th {
			background-color: white;
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