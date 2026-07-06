<?php 
$current = '';
if(isset($data)) :
	$current	 	= (isset($data->current)) ? $data->current : '' ;
endif;
if(is_user_logged_in()) : ?>
<div class="col-md-4">
	<div class="sidebar left">

		<div class="my-account-nav-container">
			
			<ul class="my-account-nav">
				<li class="sub-nav-title"><?php esc_html_e('Manage Account','listeo_core');?></li>
				<li>
					<a href="<?php echo get_permalink(get_option( 'my_account_page' )); ?>" 
					<?php if( $current == 'profile' ) { echo 'class="current"'; }?>>
					<i class="sl sl-icon-user"></i> <?php esc_html_e('My Profile','listeo_core');?>
					</a>
				</li>
				<li>
					<a href="<?php echo get_permalink(get_option( 'bookmarks_page' ))?>" 
					<?php if( $current == 'bookmarks' ) { echo 'class="current"'; }?>>
						<i class="sl sl-icon-star"></i> <?php esc_html_e('Bookmarked Listings','listeo_core');?>
					</a>
				</li>
			</ul>
			
			<ul class="my-account-nav">
				<li class="sub-nav-title"><?php esc_html_e('Manage Listings','listeo_core');?></li>
				<?php if( get_option( 'my_listings_page' ) ) { ?>
				<li>
					<a href="<?php echo get_permalink( get_option( 'my_listings_page' ) ); ?>" 
					<?php if( $current == 'my_listings' ) { echo 'class="current"'; }?> >
						<i class="sl sl-icon-docs"></i> 
						<?php esc_html_e('My Properties','listeo_core');?>
					</a>
				</li>
				<?php } ?>
				<?php if( get_option( 'submit_listing_page' ) ) { ?>
				<li>
					<a href="<?php echo get_permalink( get_option( 'submit_listing_page' ) ); ?>"
					<?php if( $current == 'submit' ) { echo 'class="current"'; }?> >
						<i class="sl sl-icon-action-redo"></i> 
						<?php esc_html_e('Submit New Listing','listeo_core');?>
					</a>
				</li>
				<?php } ?>	
				<?php if( get_option( 'listing_packages_page' ) ) { ?>
				<li>
					<a href="<?php echo get_permalink( get_option( 'listing_packages_page' ) ); ?>"
					<?php if( $current == 'my_packages' ) { echo 'class="current"'; }?> >
						<i class="sl sl-icon-basket"></i> 
						<?php esc_html_e('My Packages','listeo_core');?>
					</a>
				</li>
				<?php } ?>	
			</ul>

			<ul class="my-account-nav">
				<?php if( get_option( 'change_password_page' ) ) { ?>
				<li>
					<a href="<?php echo get_permalink( get_option( 'change_password_page' ) ); ?>"
					<?php if( $current == 'password' ) { echo 'class="current"'; }?> >
					<i class="sl sl-icon-lock"></i> <?php esc_html_e('Change Password','listeo_core');?>
					</a>
				</li>
				<?php } ?>
				<li><a href="<?php echo wp_logout_url(get_permalink(get_option( 'my_account_page' ))); ?>"><i class="sl sl-icon-power"></i> <?php esc_html_e('Log Out','listeo_core');?></a></li>
			</ul>

		</div>

	</div>
</div>
<?php endif; ?>