<?php
include_once( 'icwp_wpsf_config_header.php' );
?>
<div class="wrap">
	<div class="bootstrap-wpadmin">
		<?php echo printOptionsPageHeader( _wpsf__('Login Protection') ); ?>
		
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
					<button type="submit" class="btn btn-primary" name="submit"><?php _wpsf_e( 'Save All Settings' ); ?></button>
					<button type="submit" class="btn btn-warning" name="terminate-all-logins" value="1" style="margin-left: 15px"><?php _wpsf_e( 'Clear All Verified Logins' ); ?></button>
					</div>
				</form>
				
			</div><!-- / span9 -->
		
			<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
		  		<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
			<?php endif; ?>
		</div><!-- / row -->
	
	</div><!-- / bootstrap-wpadmin -->
	<?php include_once( dirname(__FILE__).'/include_js.php' ); ?>
</div>