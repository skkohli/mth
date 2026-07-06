<?php $user_packages = listeo_core_user_packages( get_current_user_id() ); ?>
<div class="col-md-8">
<?php if ( $user_packages ) : ?>
	<table class="manage-table responsive-table packages-table">
	<tr>
		<th><i class="fa fa-file-text"></i> <?php esc_html_e('Package name','listeo_core'); ?></th>
		<th><i class="fa fa-pencli"></i> <?php esc_html_e('Description','listeo_core'); ?></th>
		<th></th>
	</tr>
		<?php 
		foreach ( $user_packages as $key => $package ) :
			$package = listeo_core_get_package( $package );
			?>
			<tr>
				<td class="title-container"><span><?php echo $package->get_title(); ?></span></td>
				<td class="expire-date">
					<?php
					if ( $package->get_limit() ) {
						printf( _n( 'You have %1$s listings posted out of %2$d', 'You have %1$s listings posted out of %2$d', $package->get_count(), 'listeo_core' ), $package->get_count(), $package->get_limit() );
					} else {
						printf( _n( 'You have %s listings posted', 'You have %s listings posted', $package->get_count(), 'listeo_core' ), $package->get_count() );
					}

					if ( $package->get_duration() ) {
						printf( ', ' . _n( 'listed for %s day', 'listed for %s days', $package->get_duration(), 'listeo_core' ), $package->get_duration() );
					}

					$checked = 0; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>

<?php else: ?>
	<div class="notification notice margin-bottom-20">
			<p><?php echo sprintf( 
				esc_html_e( 'You haven\'t bought any packages yet, you can do it while <a href="%s">adding a listing</a> or by visiting <a href="%s">shop</a>', 'listeo_core' ), 
			get_permalink( get_option( 'submit_listing_page' ) ),
			get_permalink( wc_get_page_id( 'shop' ) ) );	 ?></p>
		</div><a href="<?php echo get_permalink( get_option( 'submit_listing_page' ) ); ?>" class="margin-top-20 button"> <?php esc_html_e('Submit New Listing','listeo_core'); ?></a>
<?php endif; ?>
	
	<a href="<?php echo get_permalink( wc_get_page_id( 'shop' ) ) ; ?>" class="margin-top-20 button"><i class="sl sl-icon-basket"></i> <?php esc_html_e('Purchase New Package','listeo_core'); ?></a>

</div>

</div>