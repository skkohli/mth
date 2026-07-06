<?php
$current_user = wp_get_current_user();
$user_post_count = count_user_posts($current_user->ID, 'listing');
$roles = $current_user->roles;
$role = array_shift($roles);

if (!in_array($role, array('administrator', 'admin', 'owner', 'seller'))) :
	$template_loader = new Listeo_Core_Template_Loader;
	$template_loader->get_template_part('account/owner_only');
	return;
endif;

?>

<!-- Notice -->
<!--  -->

<!-- Content -->
<div class="row listeo-dashoard-widgets">

	<?php
	$listings_page = get_option('listeo_listings_page');
	if ($listings_page) : ?>
		<a href="<?php echo esc_url(get_permalink($listings_page)); ?>?status=active">
		<?php endif; ?>
		<!-- Item -->
		<div class="col-lg-3 col-md-6" id="dashboard-active-listing-tile">
			<div class="dashboard-stat color-1">
				<div class="dashboard-stat-content">
					<h4><?php $user_post_count = count_user_posts($current_user->ID, 'listing');
						echo $user_post_count; ?></h4> <span><?php esc_html_e('Active Listings', 'listeo_core'); ?></span>
				</div>
				<div class="dashboard-stat-icon">
					<svg id="Layer_1" style="enable-background:new 0 0 128 128;" version="1.1" viewBox="0 0 128 128" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
						<g>
							<path d="M121.8,34.9L96.1,23.2c0,0-0.1,0-0.1,0c-0.1,0-0.1-0.1-0.2-0.1c-0.1,0-0.1,0-0.2,0c-0.1,0-0.2,0-0.2,0s-0.2,0-0.2,0   c-0.1,0-0.1,0-0.2,0c-0.1,0-0.2,0-0.2,0.1c0,0-0.1,0-0.1,0l-8.7,3.9c-3.5-5.2-9.5-8.6-16.3-8.6c0,0,0,0,0,0c-5.2,0-10.2,2-13.9,5.8   c-0.9,0.9-1.7,1.8-2.4,2.9l-8.7-3.9c0,0-0.1,0-0.1,0c-0.1,0-0.1-0.1-0.2-0.1c-0.1,0-0.1,0-0.2,0c-0.1,0-0.2,0-0.2,0   c-0.1,0-0.2,0-0.2,0c-0.1,0-0.1,0-0.2,0c-0.1,0-0.2,0-0.2,0.1c0,0-0.1,0-0.1,0L17.5,34.9c-0.7,0.3-1.1,1-1.1,1.7v65.9   c0,0.6,0.3,1.2,0.9,1.6c0.3,0.2,0.7,0.3,1,0.3c0.3,0,0.5-0.1,0.8-0.2l24.9-11.3l24.9,11.3c0.1,0,0.1,0,0.2,0.1c0,0,0.1,0,0.1,0   c0.2,0,0.3,0.1,0.5,0.1c0.2,0,0.3,0,0.5-0.1c0,0,0.1,0,0.1,0c0.1,0,0.1,0,0.2-0.1l24.9-11.3l24.9,11.3c0.2,0.1,0.5,0.2,0.8,0.2   c0.4,0,0.7-0.1,1-0.3c0.5-0.3,0.9-0.9,0.9-1.6V36.6C122.9,35.9,122.4,35.2,121.8,34.9z M69.6,22.3C69.6,22.3,69.6,22.3,69.6,22.3   c8.8,0,15.9,7.1,15.9,15.9c0,8.3-12.5,25.6-15.9,27.6c-3.4-2-15.9-19.3-15.9-27.6c0-4.2,1.7-8.2,4.7-11.2   C61.4,23.9,65.4,22.3,69.6,22.3z M93.4,89.7l-21.9,9.9V69c6.1-3.8,17.8-22.2,17.8-30.8c0-2.7-0.6-5.4-1.6-7.7l5.8-2.6V89.7z    M67.7,69v30.6l-21.9-9.9V27.8l5.8,2.6c-1,2.4-1.6,5-1.6,7.7C50,46.7,61.7,65.2,67.7,69z M20.1,37.8l21.9-10v61.9l-21.9,9.9V37.8z    M119.1,99.6l-21.9-9.9V27.8l21.9,10V99.6z" />
							<path d="M57.5,36.8c0,6.7,5.4,12.1,12.1,12.1c6.7,0,12.1-5.4,12.1-12.1c0-6.7-5.4-12.1-12.1-12.1C62.9,24.7,57.5,30.1,57.5,36.8z    M78,36.8c0,4.6-3.7,8.4-8.4,8.4c-4.6,0-8.4-3.7-8.4-8.4c0-4.6,3.7-8.4,8.4-8.4C74.2,28.4,78,32.2,78,36.8z" />
						</g>
					</svg>
				</div>
			</div>

		</div>
		<?php if ($listings_page) : ?>
		</a>
	<?php endif; ?>


	<?php $total_views = get_user_meta($current_user->ID, 'listeo_total_listing_views', true); ?>
	<!-- Item -->
	<?php $stats_page = get_option('listeo_stats_page');
	if ($stats_page) :  ?>
		<!-- Item -->
		<a href="<?php echo esc_url(get_permalink($stats_page)); ?>">
		<?php endif; ?>
		<div class="col-lg-3 col-md-6" id="dashboard-stat-listing-tile">
			<div class="dashboard-stat color-2">
				<div class="dashboard-stat-content">
					<h4><?php echo esc_html($total_views); ?></h4> <span><?php esc_html_e('Total Views', 'listeo_core'); ?></span>
				</div>
				<div class="dashboard-stat-icon"><svg id="Layer_1" style="enable-background:new 0 0 256 256;" version="1.1" viewBox="0 0 256 256" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
						<g>
							<path d="M29.4,190.9c2.8,0,5-2.2,5-5v-52.3l30.2-16.2v42.9c0,2.8,2.2,5,5,5c2.8,0,5-2.2,5-5v-59.6l-50.1,26.9v58.3   C24.4,188.6,26.6,190.9,29.4,190.9z" />
							<path d="M89.6,153c2.8,0,5-2.2,5-5V59.6l30.2-16.2v143.8c0,2.8,2.2,5,5,5c2.8,0,5-2.2,5-5V26.7L84.6,53.6V148   C84.6,150.7,86.8,153,89.6,153z" />
							<path d="M149.8,185.7c2.8,0,5-2.2,5-5V85.4L185,69.2v86.3c0,2.8,2.2,5,5,5c2.8,0,5-2.2,5-5v-103l-50.1,26.9v101.3   C144.8,183.5,147.1,185.7,149.8,185.7z" />
							<path d="M250,146.2c-0.9-1.5-2.5-2.5-4.3-2.5h-34.5c-1.8,0-3.4,1-4.3,2.5c-0.9,1.5-0.9,3.4,0,5l6.2,10.7l-27.5,16.7   c-4.2,2.6-8.3,5.1-12.5,7.7c-3,1.9-6,3.7-8.9,5.6c-7.8,5-14.7,9.5-21.1,13.9c-3.3,2.2-6.5,4.5-9.8,6.7l-44.8-43.8L7.7,220.1   c-2.3,1.5-3,4.6-1.5,6.9c1,1.5,2.6,2.3,4.2,2.3c0.9,0,1.8-0.3,2.7-0.8l74-47.1l45.2,44.1l6.1-4.4c3.4-2.4,6.8-4.8,10.3-7.1   c6.3-4.3,13.1-8.7,20.9-13.7c2.9-1.9,5.9-3.7,8.8-5.6c4.1-2.6,8.3-5.1,12.4-7.7l25.3-15.5l2-1.2l6.1,10.6c0.9,1.5,2.5,2.5,4.3,2.5   s3.4-1,4.3-2.5l17.3-29.9C250.9,149.7,250.9,147.8,250,146.2z M228.4,168.6l-8.6-14.9H237L228.4,168.6z" />
						</g>
					</svg></div>
			</div>
		</div>
		<?php if ($stats_page) :  ?>
		</a>
	<?php endif; ?>


	<?php

	$author_posts_comments_count = listeo_count_user_comments(
		array(
			'author_id' => $current_user->ID, // Author ID
			'author_email' => $current_user->user_email, // Author ID
			'approved' => 1, // Approved or not Approved
		)
	);

	?>
	<?php $reviews_page = get_option('listeo_reviews_page');
	if ($reviews_page) :  ?>
		<!-- Item -->
		<a href="<?php echo esc_url(get_permalink($reviews_page)); ?>">
		<?php endif; ?>
		<div class="col-lg-3 col-md-6" id="dashboard-reviews-listing-tile">
			<div class="dashboard-stat color-3">
				<div class="dashboard-stat-content">
					<h4><?php echo esc_html($author_posts_comments_count); ?></h4> <span><?php esc_html_e('Total Reviews', 'listeo_core'); ?></span>
				</div>
				<div class="dashboard-stat-icon"><svg enable-background="new 0 0 48 48" version="1.1" viewBox="0 0 48 48" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
						<g id="Expanded">
							<g>
								<g>
									<path d="M6,45.414V36H3c-1.654,0-3-1.346-3-3V7c0-1.654,1.346-3,3-3h42c1.654,0,3,1.346,3,3v26c0,1.654-1.346,3-3,3H15.414     L6,45.414z M3,6C2.448,6,2,6.448,2,7v26c0,0.552,0.448,1,1,1h5v6.586L14.586,34H45c0.552,0,1-0.448,1-1V7c0-0.552-0.448-1-1-1H3z     " />
								</g>
								<g>
									<circle cx="16" cy="20" r="2" />
								</g>
								<g>
									<circle cx="32" cy="20" r="2" />
								</g>
								<g>
									<circle cx="24" cy="20" r="2" />
								</g>
							</g>
						</g>
					</svg></div>
			</div>
		</div>
		<?php if ($reviews_page) :  ?>
		</a>
	<?php endif; ?>


	<!-- Item -->
	<?php $total_bookmarks = get_user_meta($current_user->ID, 'listeo_total_listing_bookmarks', true); ?>
	<div class="col-lg-3 col-md-6" id="dashboard-bookmarks-listing-tile">
		<div class="dashboard-stat color-4">
			<div class="dashboard-stat-content">
				<h4><?php echo esc_html($total_bookmarks); ?></h4> <span><?php esc_html_e('Times Bookmarked', 'listeo_core') ?></span>
			</div>
			<div class="dashboard-stat-icon"><svg enable-background="new 0 0 32 32" height="32px" id="Layer_1" version="1.1" viewBox="0 0 32 32" width="32px" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
					<g id="heart">
						<path clip-rule="evenodd" d="M29.193,5.265c-3.629-3.596-9.432-3.671-13.191-0.288   C12.242,1.594,6.441,1.669,2.81,5.265c-3.741,3.704-3.741,9.709,0,13.415c1.069,1.059,11.053,10.941,11.053,10.941   c1.183,1.172,3.096,1.172,4.278,0c0,0,10.932-10.822,11.053-10.941C32.936,14.974,32.936,8.969,29.193,5.265z M27.768,17.268   L16.715,28.209c-0.393,0.391-1.034,0.391-1.425,0L4.237,17.268c-2.95-2.92-2.95-7.671,0-10.591   c2.844-2.815,7.416-2.914,10.409-0.222l1.356,1.22l1.355-1.22c2.994-2.692,7.566-2.594,10.41,0.222   C30.717,9.596,30.717,14.347,27.768,17.268z" fill="#333333" fill-rule="evenodd" />
						<path clip-rule="evenodd" d="M9.253,7.501c-0.002,0-0.002,0.001-0.004,0.001   c-2.345,0.002-4.246,1.903-4.246,4.249l0,0c0,0.276,0.224,0.5,0.5,0.5s0.5-0.224,0.5-0.5V11.75c0-1.794,1.455-3.249,3.249-3.249   h0.001c0.276,0,0.5-0.224,0.5-0.5S9.53,7.501,9.253,7.501z" fill="#333333" fill-rule="evenodd" />
					</g>
				</svg></div>
		</div>
	</div>

</div>


<div class="row">

	<!-- Recent Activity -->
	<div class="col-lg-6 col-md-12">
		<div class="dashboard-list-box with-icons margin-top-20" style="position: relative;">
			<h4><?php esc_html_e('Recent Activities', 'listeo_core'); ?></h4>
			<a href="#" id="listeo-clear-activities" class="clear-all-activities" data-nonce="<?php echo wp_create_nonce('delete_activities'); ?>"><?php esc_html_e('Clear All', 'listeo_core') ?></a>
			<?php echo do_shortcode('[listeo_activities]'); ?>

		</div>
		<?php if (get_option('listeo_new_listing_requires_purchase')) : ?>
			<!-- Invoices -->
			<div class="col-lg-6 col-md-12">
				<div class="dashboard-list-box invoices with-icons margin-top-20">
					<h4><?php esc_html_e('Your Listing Packages', 'listeo_core') ?></h4>
					<ul class="products user-packages">
						<?php
						$user_packages = listeo_core_user_packages(get_current_user_id());
						if ($user_packages) :
							foreach ($user_packages as $key => $package) :
								$package = listeo_core_get_package($package);
						?>
								<li class="user-job-package">
									<i class="list-box-icon sl sl-icon-diamond"></i>
									<strong><?php echo $package->get_title(); ?></strong>
									<p>
										<?php
										if ($package->get_limit()) {
											printf(_n('You have %1$s listings posted out of %2$d', 'You have %1$s listings posted out of %2$d', $package->get_count(), 'listeo_core'), $package->get_count(), $package->get_limit());
										} else {
											printf(_n('You have %s listings posted', 'You have %s listings posted', $package->get_count(), 'listeo_core'), $package->get_count());
										}

										if ($package->get_duration() && $package->get_duration() > 1) {
											printf(', ' . _n('listed for %s day', 'listed for %s days', $package->get_duration(), 'listeo_core'), $package->get_duration());
										}

										$checked = 0;
										?>
									</p>

								</li>
							<?php endforeach;
						else : ?>
							<li class="no-icon"><?php esc_html_e("You don't have any listing packages yet.", 'listeo_core'); ?></li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php

		if (get_option('listeo_stats_status')) {
			if (!get_option('listeo_dashboard_chart_status')) { ?>


				<div class="col-lg-6 col-md-12">
					<!-- Dashboard Box -->
					<div class="dashboard-list-box dashboard-stats-box with-icons margin-top-20" style="position: relative;">
						<h4><?php esc_html_e('Your Listings Views', 'listeo_core'); ?>
							<!-- Date Range -->


							<div id="chart-date-range" style="display: none;">
								<span></span>
							</div>
						</h4>


						<?php echo do_shortcode('[listeo_stats]'); ?>
						<div class="chart-button">
							<?php $stats_page = get_option('listeo_stats_page');
							if ($stats_page) { ?>
								<a href="<?php echo get_permalink($stats_page); ?>" class="button center"> <?php esc_html_e('Check more stats', 'listeo_core') ?> </a>
							<?php } ?>
						</div>
					</div>

				</div>
			<?php } ?>
		<?php } ?>