<?php
include_once( 'icwp-wpsf-config_header.php' );
include_once( 'icwp-wpsf-config-options-table.php' );

?>
	<div class="row">
		<div class="span12">
			<h2><?php _wpsf_e('Current User Sessions');?></h2>

			<?php if ( !empty($icwp_aActiveSessions) ) : ?>
				<table class="table table-bordered">
					<tr>
						<th><?php _wpsf_e('Username'); ?></th>
						<th><?php _wpsf_e('Logged In At'); ?></th>
						<th><?php _wpsf_e('Last Activity At'); ?></th>
						<th><?php _wpsf_e('Last Activity URI'); ?></th>
						<th><?php _wpsf_e('Login IP'); ?></th>
						<th><?php _wpsf_e('Login Attempts'); ?></th>
					</tr>
					<?php foreach( $icwp_aActiveSessions as $aSessionData ) : ?>
						<tr>
							<td><?php echo $aSessionData['wp_username']; ?></td>
							<td><?php echo date( 'Y/m/d H:i:s', $aSessionData['logged_in_at'] ); ?></td>
							<td><?php echo date( 'Y/m/d H:i:s', $aSessionData['last_activity_at'] ); ?></td>
							<td><?php echo $aSessionData['last_activity_uri']; ?></td>
							<td>
								<a href="http://whois.domaintools.com/<?php echo long2ip( $aSessionData['ip_long'] ); ?>" target="_blank">
									<?php echo long2ip( $aSessionData['ip_long'] ); ?>
								</a>
							</td>
							<td><?php echo $aSessionData['login_attempts']; ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php else : ?>
				<?php _wpsf_e('You need to enable the User Management feature to view and manage user sessions.'); ?>
			<?php endif; ?>
		</div>
	</div>
<?php
include_once( 'icwp-wpsf-config_footer.php' );
