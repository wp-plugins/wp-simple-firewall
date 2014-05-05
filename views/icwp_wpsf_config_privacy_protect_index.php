<?php
include_once( dirname(__FILE__).ICWP_DS.'icwp_options_helper.php' );
include_once( dirname(__FILE__).ICWP_DS.'widgets'.ICWP_DS.'icwp_widgets.php' );
$sPluginName = 'WordPress Simple Firewall';
$aLogTypes = array(
	0	=>	_wpsf__('Info'),
	1	=>	_wpsf__('Warning'),
	2	=>	_wpsf__('Critical')
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
	}
	td .section-ip {
		width: 68%;
	}
	td .section-timestamp {
		text-align: right;
		width: 28%;
	}
</style>

<div class="wrap">
	<div class="bootstrap-wpadmin">
		<?php echo printOptionsPageHeader( _wpsf__('Privacy Log') ); ?>

		<div class="row">
			<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
				<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
					<?php
						wp_nonce_field( $icwp_nonce_field );
					?>
					<div class="form-actions">
						<input type="hidden" name="icwp_plugin_form_submit" value="Y" />
						<button type="submit" class="btn btn-primary" name="clear_log_submit"><?php _wpsf_e( 'Clear/Fix Log' ); ?></button>
					</div>
				</form>

				<?php if ( !$icwp_urlrequests_log ) : ?>
					<?php echo 'There are currently no logs to display. If you expect there to be some, use the button above to Clean/Fix them.'; ?>
				<?php else : ?>

				<table class="table table-bordered table-hover table-condensed">
					<tr>
						<th><?php _wpsf_e('Date'); ?></th>
						<th><?php _wpsf_e('URL'); ?></th>
						<th><?php _wpsf_e('Method'); ?></th>
						<th><?php _wpsf_e('Data'); ?></th>
						<th><?php _wpsf_e('Error?'); ?></th>
					</tr>
					<?php foreach( $icwp_urlrequests_log as $sId => $aLogData ) : ?>
						<tr class="row-log-header">
							<td>
								<span class="cell-section section-timestamp"><?php echo date( 'Y/m/d H:i:s', $aLogData['requested_at'] ); ?></span>
							</td>
							<td><?php echo $aLogData['request_url']; ?></td>
							<td><?php echo $aLogData['request_method']; ?></td>
							<td>
								<ul>
									<?php
									$aArgs = unserialize( $aLogData['request_args'] );
									foreach( $aArgs as $sKey => $mValue ) {
										echo sprintf( '<li>%s: %s</li>', $sKey, is_scalar( $mValue )? esc_attr( urldecode($mValue) ) : print_r( $mValue, true ) );
									}
									?>
								</ul>
							</td>
							<td><?php echo $aLogData['is_error']; ?></td>
						</tr>
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