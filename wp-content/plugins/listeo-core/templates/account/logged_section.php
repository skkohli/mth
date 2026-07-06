<?php
$my_account_display = get_option('listeo_my_account_display', true);
$submit_display = get_option('listeo_submit_display', true);
if (true == $my_account_display) : ?>

	<?php if (is_user_logged_in()) {
		$user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		$roles = $current_user->roles;
		$role = array_shift($roles);
		if (!empty($current_user->user_firstname)) {
			$name = $current_user->user_firstname;
		} else {
			$name =  $current_user->display_name;
		}
	?>
		<div class="user-menu">
			<div class="user-name"><span><?php echo get_avatar($current_user->user_email, 32); ?></span>
				<div class="usrname"><?php esc_html_e('My Account', 'listeo_core') ?></div>
			</div>
			<ul>


				<?php if (in_array($role, array('administrator', 'admin', 'owner', 'seller'))) : ?>
					<?php $dashboard_page = get_option('listeo_dashboard_page');
					if ($dashboard_page) : ?>
						<li><a href="<?php echo esc_url(get_permalink($dashboard_page)); ?>"><i class="sl sl-icon-settings"></i> <?php esc_html_e('Dashboard', 'listeo_core'); ?></a></li>
					<?php endif; ?>
				<?php endif; ?>
				<?php
				if (in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
					if (class_exists('WeDevs_Dokan')) :  ?>
						<?php $store_page = get_option('dokan_pages');
						if (isset($store_page['dashboard'])) : ?>
							<li><a href="<?php echo esc_url(get_permalink($store_page['dashboard'])); ?>/?path=%2Fanalytics%2FOverview"><i class="sl sl-icon-handbag"></i> <?php esc_html_e('Store Dashboard', 'listeo_core'); ?></a></li>
				<?php
						endif;
					endif;
				endif; ?>
				<?php if (!in_array($role, array('owner', 'seller'))) : ?>
					<?php $user_bookings_page = get_option('listeo_user_bookings_page');
					if ($user_bookings_page) : ?>
						<li><a href="<?php echo esc_url(get_permalink($user_bookings_page)); ?>"><i class="fa fa-calendar-check"></i> <?php esc_html_e('My Bookings', 'listeo_core'); ?></a></li>
					<?php endif; ?>
				<?php endif; ?>
				<?php if (in_array($role, array('administrator', 'admin', 'owner', 'seller'))) : ?>
					<?php $listings_page = get_option('listeo_listings_page');
					if ($listings_page) : ?>
						<li><a href="<?php echo esc_url(get_permalink($listings_page)); ?>"><i class="sl sl-icon-layers"></i> <?php esc_html_e('My Listings', 'listeo_core'); ?></a></li>
					<?php endif; ?>
				<?php endif; ?>
				<?php if (!in_array($role, array('owner', 'seller'))) : ?>
					<?php $reviews_page = get_option('listeo_reviews_page');
					if ($reviews_page) : ?>
						<li><a href="<?php echo esc_url(get_permalink($reviews_page)); ?>"><i class="sl sl-icon-star"></i> <?php esc_html_e('Reviews', 'listeo_core'); ?></a></li>
					<?php endif; ?>
				<?php endif; ?>


				<?php $bookmarks_page = get_option('listeo_bookmarks_page');
				if ($bookmarks_page) : ?>
					<li><a href="<?php echo esc_url(get_permalink($bookmarks_page)); ?>"><i class="sl sl-icon-heart"></i> <?php esc_html_e('Bookmarks', 'listeo_core'); ?></a></li>
				<?php endif; ?>


				<?php $messages_page = get_option('listeo_messages_page');
				if ($messages_page) : ?>
					<li><a href="<?php echo esc_url(get_permalink($messages_page)); ?>"><i class="sl sl-icon-envelope-open"></i> <?php esc_html_e('Messages', 'listeo_core'); ?>
							<?php
							$counter = listeo_get_unread_counter();
							if ($counter) { ?>
								<span class="nav-tag messages"><?php echo esc_html($counter); ?></span>
							<?php } ?>
						</a></li>
				<?php endif; ?>

				<?php if (in_array($role, array('administrator', 'admin', 'owner', 'seller'))) : ?>
					<?php $bookings_page = get_option('listeo_bookings_page');
					if ($bookings_page) : ?>
						<li><a href="<?php echo esc_url(get_permalink($bookings_page)); ?>/?status=waiting"><i class="fa fa-calendar-check"></i> <?php esc_html_e('Bookings', 'listeo_core'); ?>
								<?php $count_pending = listeo_count_bookings($user_id, 'waiting');
								if (isset($count_pending)) : ?><span class="nav-tag blue"><?php echo esc_html($count_pending); ?></span><?php endif; ?></a></li>
					<?php endif; ?>

					<?php $stats_page = get_option('listeo_stats_page');
					if ($stats_page) : ?>
						<li><a href="<?php echo esc_url(get_permalink($stats_page)); ?>"><i class="sl sl-icon-chart"></i> <?php esc_html_e('Statistics', 'listeo_core'); ?></a></li>
					<?php endif; ?>
				<?php endif; ?>



				<?php $profile_page = get_option('listeo_profile_page');
				if ($profile_page) : ?>
					<li><a href="<?php echo esc_url(get_permalink($profile_page)); ?>"><i class="sl sl-icon-user"></i> <?php esc_html_e('My Profile', 'listeo_core'); ?></a></li>
				<?php endif; ?>

				<?php wp_nav_menu(array('theme_location' => 'dashboard_top_menu', 'menu_id' => 'dashboard_top_menu', 'container' => false, 'items_wrap' => '%3$s', 'fallback_cb' => false)); ?>
				<li>
					<a href="<?php echo wp_logout_url(home_url()); ?>"><i class="sl sl-icon-power"></i> <?php esc_html_e('Logout', 'listeo_core'); ?></a>
				</li>
			</ul>
		</div>
		<?php } else {

		$popup_login = get_option('listeo_popup_login');
		$submit_page = get_option('listeo_submit_page');
		if (function_exists('Listeo_Core')) :
			if ($popup_login == 'ajax' && !is_page_template('template-dashboard.php')) { ?>
				<a href="#sign-in-dialog" class="sign-in popup-with-zoom-anim"><i class="sl sl-icon-login"></i> <?php esc_html_e('Sign In', 'listeo_core'); ?></a>
			<?php } else {
				$login_page = get_option('listeo_profile_page') ?>
				<a href="<?php echo esc_url(get_permalink($login_page)); ?>" class="sign-in"><i class="sl sl-icon-login"></i> <?php esc_html_e('Sign In', 'listeo_core'); ?></a>
		<?php }
		endif; ?>
	<?php } ?>

<?php endif; ?>

<?php if (true == $submit_display) : ?>
	<?php if (is_user_logged_in()) {
		$user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		$roles = $current_user->roles;
		$role = array_shift($roles);
	?>
		<?php if (in_array($role, array('administrator', 'admin', 'owner', 'seller'))) : ?>
			<?php $submit_page = apply_filters('listeo_submit_page', get_option('listeo_submit_page'));
			if ($submit_page) : ?>
				<a href="<?php echo esc_url(get_permalink($submit_page)); ?>" class="button border with-icon"><?php esc_html_e('Add Listing', 'listeo_core'); ?> <i class="sl sl-icon-plus"></i></a>
			<?php endif; ?>
		<?php else : ?>
			<?php $browse_page = get_post_type_archive_link('listing');
			if ($browse_page) : ?>
				<a href="<?php echo esc_url($browse_page); ?>" class="button border"><?php esc_html_e('Browse Listings', 'listeo_core'); ?></i></a>
			<?php endif; ?>
		<?php endif; ?>
	<?php } else { ?>

		<?php
		$submit_page = apply_filters('listeo_submit_page_anonymous', get_option('listeo_submit_page'));
		if ($submit_page) : ?>
			<a href="<?php echo esc_url(get_permalink($submit_page)); ?>" class="button border with-icon"><?php esc_html_e('Add Listing', 'listeo_core'); ?> <i class="sl sl-icon-plus"></i></a>
		<?php endif; ?>
	<?php } ?>

<?php endif; ?>