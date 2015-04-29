<div class="row">
	<div class="<?php echo $icwp_fShowAds? 'span10' : 'span12'; ?>">

		<div>
				<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
					<?php
					wp_nonce_field( $icwp_nonce_field );
					printAllPluginOptionsFormTabs( $icwp_aAllOptions, $icwp_var_prefix, 1 );
					?>
					<div class="form-actions">
						<input type="hidden" name="<?php echo $icwp_var_prefix; ?>all_options_input" value="<?php echo $icwp_all_options_input; ?>" />
						<input type="hidden" name="<?php echo $icwp_var_prefix; ?>plugin_form_submit" value="Y" />
						<button type="submit" class="btn btn-primary btn-large" name="submit"><?php _wpsf_e( 'Save All Settings' ); ?></button>
					</div>
				</form>
		</div>
		<?php if ( $icwp_fShowAds ) : ?>
		<div class="row-fluid">
			<div class="span12">
				<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
			</div>
		</div>
		<?php endif; ?>
	</div><!-- / span9 -->

	<?php if ( $icwp_fShowAds ) : ?>
		<div class="span3" id="side_widgets">
			<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
		</div>
	<?php endif; ?>
</div><!-- / row -->