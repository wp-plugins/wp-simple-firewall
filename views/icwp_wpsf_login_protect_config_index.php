<?php
include_once( dirname(__FILE__).WORPIT_DS.'icwp_options_helper.php' );
include_once( dirname(__FILE__).WORPIT_DS.'widgets'.WORPIT_DS.'icwp_widgets.php' );
?>
<div class="wrap">
	<div class="bootstrap-wpadmin">

		<div class="page-header">
			<a href="http://wwwicontrolwp.com/"><div class="icon32" id="icontrolwp-icon"><br /></div></a>
			<h2><?php _hlt_e( 'Login Protect Configuration - WordPress Simple Firewall (from iControlWP)' ); ?></h2>
		</div>
		
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
					<button type="submit" class="btn btn-primary" name="submit"><?php _hlt_e( 'Save All Settings'); ?></button>
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