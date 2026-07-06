<?php
if (!defined('ABSPATH')) {
	exit;
}

global $post;

$template_loader = new Listeo_Core_Template_Loader;


$full_width_header = get_post_meta($post->ID, 'listeo_full_width_header', TRUE);
if (empty($full_width_header)) {
	$full_width_header = 'use_global';
};


if ($full_width_header == 'use_global') {
	$full_width_header = get_option('listeo_full_width_header');
}


if ($full_width_header == 'enable' || $full_width_header == 'true') {
	get_header('fullwidth');
} else {
	get_header(get_option('header_bar_style', 'standard'));
}



$layout = get_option('listeo_single_layout', 'right-sidebar');
$mobile_layout = get_option('listeo_single_mobile_layout', 'right-sidebar');

$gallery_style = get_post_meta($post->ID, '_gallery_style', true);
$listing_logo = get_post_meta($post->ID, '_listing_logo', true);

if (empty($gallery_style)) {
	$gallery_style = get_option('listeo_gallery_type', 'top');
}

$count_gallery = listeo_count_gallery_items($post->ID);

if ($count_gallery < 4) {
	$gallery_style = 'content';
}
if ($count_gallery == 1) {
	$gallery_style = 'none';
}


$packages_disabled_modules = get_option('listeo_listing_packages_options', array());
if (empty($packages_disabled_modules)) {
	$packages_disabled_modules = array();
}

$user_package = get_post_meta($post->ID, '_user_package_id', true);

if ($user_package) {
	$package = listeo_core_get_user_package($user_package);
} else {
	$package = false;
}


$load_gallery = false;
if (in_array('option_gallery', $packages_disabled_modules)) {
	if ($package && $package->has_listing_gallery() == 1) {
		$load_gallery = true;
	}
} else {
	$load_gallery = true;
}

$load_pricing_menu = false;
if (in_array('option_pricing_menu', $packages_disabled_modules)) {
	if ($package && $package->has_listing_pricing_menu() == 1) {
		$load_pricing_menu = true;
	}
} else {
	$load_pricing_menu = true;
}

$load_video = false;
if (in_array('option_video', $packages_disabled_modules)) {
	if ($package && $package->has_listing_video() == 1) {
		$load_video = true;
	}
} else {
	$load_video = true;
}

$load_reviews = false;
if (in_array('option_reviews', $packages_disabled_modules)) {
	if ($package && $package->has_listing_reviews() == 1) {
		$load_reviews = true;
	}
} else {
	$load_reviews = true;
}

if (have_posts()) :



	$listing_type = get_post_meta(get_the_ID(), '_listing_type', true);

	if ($gallery_style == 'top' && $load_gallery == true) :

		$template_loader->get_template_part('single-partials/single-listing', 'gallery');
	else : ?>
		<!-- Gradient-->
		<div class="single-listing-page-titlebar"></div>
	<?php endif; ?>

	<!-- Content
================================================== -->

	<div class="container <?php echo esc_attr($listing_type); ?>">
		<div class="row sticky-wrapper">
			<!-- Sidebar
		================================================== -->
			<!-- " -->

			<?php if ($layout == "left-sidebar" || ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar")) : ?>
				<div class="col-lg-4 col-md-4 listeo-single-listing-sidebar <?php if ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar") echo "col-lg-push-8"; ?> margin-top-75 sticky">
					<?php do_action('listeo/single-listing/sidebar-start'); ?>
					<?php if ($listing_type == 'classifieds') {
						$currency_abbr = get_option('listeo_currency');
						$currency_postion = get_option('listeo_currency_postion');
						$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

					?>
						<span id="classifieds_price">
							<?php
							$classifieds_price = get_post_meta($post->ID, '_classifieds_price', true);
							if (is_numeric($classifieds_price) && $currency_postion == "before") {
								echo $currency_symbol;
							}

							if (is_numeric($classifieds_price)) {
								$decimals = get_option('listeo_number_decimals', 2);
								echo number_format_i18n(get_post_meta($post->ID, '_classifieds_price', true), $decimals);
							} else {
								echo $classifieds_price;
							}

							if (is_numeric($classifieds_price) && $currency_postion == "after") {
								echo $currency_symbol;
							} ?>
						</span>
					<?php } ?>

					<?php $template_loader->get_template_part('single-partials/single-listing', 'claim');  ?>
					<?php get_sidebar('listing'); ?>
					<?php do_action('listeo/single-listing/sidebar-end'); ?>
				</div>
				<!-- Sidebar / End -->
			<?php endif; ?>

			<?php while (have_posts()) : the_post();  ?>
				<!--  -->
				<div class="col-lg-8 col-md-8 listeo-single-listing-content <?php if ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar") {
																				echo "col-lg-pull-4";
																			}  ?> padding-right-30">

					<!-- Titlebar -->
					<div id="titlebar" class="listing-titlebar">
						<?php
						if ($listing_logo) { ?>
							<div class="listing-logo"> <img src="<?php echo $listing_logo; ?>" alt=""></div>
						<?php } ?>
						<div class="listing-titlebar-title">
							<div class="listing-titlebar-tags">
								<?php
								// First try to display global listing_category
								$terms = get_the_terms(get_the_ID(), 'listing_category');
								if ($terms && !is_wp_error($terms)) :
									$categories = array();
									foreach ($terms as $term) {
										// Create URL with only the child term slug, not full hierarchy
										$term_url = home_url(untrailingslashit(get_option('listeo_listing_category_rewrite_slug', 'listing-category')) . '/' . $term->slug . '/');

										$categories[] = sprintf(
											'<a href="%1$s">%2$s</a>',
											esc_url(get_term_link($term)),

											esc_html($term->name)
										);
									}

									$categories_list = join(", ", $categories);
								?>
									<span class="listing-tag">
										<?php echo ($categories_list) ?>
									</span>
								<?php endif; ?>
								<?php
								// Now get the type-specific taxonomy
								$taxonomy_name = listeo_get_listing_taxonomy(get_the_ID());

								// If it's the same as listing_category, we already displayed it
								if ($taxonomy_name !== 'listing_category') {
									$type_terms = get_the_terms(get_the_ID(), $taxonomy_name);
								} else {
									$type_terms = false;
								}
								if (isset($type_terms)) {
									if ($type_terms && !is_wp_error($type_terms)) :
										$categories = array();
										foreach ($type_terms as $term) {
											// Create URL with only the child term slug, not full hierarchy
											$taxonomy_base = str_replace('_', '-', $taxonomy_name);
											$term_url = home_url($taxonomy_base . '/' . $term->slug . '/');

											$categories[] = sprintf(
												'<a href="%1$s">%2$s</a>',
												esc_url($term_url),
												esc_html($term->name)
											);
										}

										$categories_list = join(", ", $categories);
								?>
										<span class="listing-tag">
											<?php echo ($categories_list) ?>
										</span>
								<?php endif;
								}
								?>

								<?php
								// Display region tags
								$region_terms = get_the_terms(get_the_ID(), 'region');
								if ($region_terms && !is_wp_error($region_terms)) :
									$region_categories = array();
									foreach ($region_terms as $term) {
										// Create URL with only the child term slug, not full hierarchy
										$region_url = home_url('region/' . $term->slug . '/');

										$region_categories[] = sprintf(
											'<a href="%1$s">%2$s</a>',
											esc_url(get_term_link($term)),
											esc_html($term->name)
										);
									}

									$region_categories_list = join(", ", $region_categories);
								?>
									<span class="listing-tag">
										<?php echo ($region_categories_list) ?>
									</span>
								<?php endif; ?>

								<?php


								if (get_the_listing_price_range()) : ?>
									<span class="listing-pricing-tag"><i class="fa fa-<?php echo esc_attr(get_option('listeo_price_filter_icon', 'tag')); ?>"></i><?php echo get_the_listing_price_range(); ?></span>
								<?php endif;



								do_action('listeo/single-listing/tags');

								?>

							</div>

							<h1><?php the_title(); ?></h1>
							<?php if (get_the_listing_address()) : ?>
								<span>
									<a href="#listing-location" class="listing-address">
										<i class="fa fa-map-marker"></i>
										<?php the_listing_address(); ?>
									</a>
								</span> <br>
								<?php endif;


							if (!get_option('listeo_disable_reviews')) {
								// Use the new combined rating display function
								$rating_data = listeo_get_rating_display($post->ID);
								$rating = $rating_data['rating'];
								$number = $rating_data['count'];

								if (isset($rating) && $rating > 0) :
									$rating_type = get_option('listeo_rating_type', 'star');
									if ($rating_type == 'numerical') { ?>
										<div class="numerical-rating" data-rating="<?php $rating_value = esc_attr(round($rating, 1));
																					printf("%0.1f", $rating_value); ?>">
										<?php } else { ?>
											<div class="star-rating" data-rating="<?php echo $rating; ?>">
											<?php } ?>

											<?php
											// Check if Listeo reviews exist
											$listeo_reviews = get_comments(array(
												'post_id' => $post->ID,
												'status' => 'approve'
											));
											// Link to Google reviews if no Listeo reviews exist
											$reviews_link = !empty($listeo_reviews) ? '#listing-reviews' : '#listing-google-reviews';
											?>
											<div class="rating-counter"><a href="<?php echo esc_attr($reviews_link); ?>"><strong><?php esc_attr(round($rating, 1));
																											printf("%0.1f", $rating);  ?></strong>
																											<?php if($number > 0) { ?> (<?php printf(_n('%s review', '%s reviews', $number, 'listeo_core'), number_format_i18n($number));  ?>) <?php } ?></a></div>
											</div>
									<?php endif;
							} ?>

										</div>

						</div>
						<?php
						if ($listing_type == 'classifieds') {
							$load_reviews = false;
						}
						?>

						<!-- Content
			================================================== -->
						<?php
						if ($gallery_style == 'none'  && $load_gallery == true) :
							$gallery = get_post_meta($post->ID, '_gallery', true);
							if (!empty($gallery)) :

								foreach ((array) $gallery as $attachment_id => $attachment_url) {
									$image = wp_get_attachment_image_src($attachment_id, 'listeo-gallery');
									echo '<img src="' . esc_url($image[0]) . '" class="single-gallery margin-bottom-40" style="margin-top:-30px;"></a>';
								}

							endif;
						endif; ?>

						<!-- Listing Nav -->
						<div id="listing-nav" class="listing-nav-container">
							<ul class="listing-nav">
								<?php do_action('listeo/single-listing/navigation-start'); ?>
								<li><a href="#listing-overview" class="active"><?php esc_html_e('Overview', 'listeo_core'); ?></a></li>
								<?php if ($count_gallery > 0 && $gallery_style == 'content'  && $load_gallery == true) : ?><li><a href="#listing-gallery"><?php esc_html_e('Gallery', 'listeo_core'); ?></a></li>
									<?php endif;
								$_menu = get_post_meta(get_the_ID(), '_menu_status', 1);

								if (!empty($_menu)) {
									$_bookable_show_menu =  get_post_meta(get_the_ID(), '_hide_pricing_if_bookable', true);
									if (!$_bookable_show_menu) { ?>
										<li><a href="#listing-pricing-list"><?php esc_html_e('Pricing', 'listeo_core'); ?></a></li>
									<?php } ?>

								<?php } ?>
								<?php if (class_exists('WeDevs_Dokan') && get_post_meta(get_the_ID(), '_store_section_status', 1)) : ?><li><a href="#listing-store"><?php esc_html_e('Store', 'listeo_core'); ?></a></li><?php endif; ?>
								<?php $video = get_post_meta($post->ID, '_video', true);
								if ($load_video && !empty($video)) :  ?>
									<li id="listing-nav-video"><a href="#listing-video"><?php esc_html_e('Video', 'listeo_core'); ?></a></li>
								<?php endif;
								$latitude = get_post_meta($post->ID, '_geolocation_lat', true);
								if (!empty($latitude)) :  ?>
									<li id="listing-nav-location"><a href="#listing-location"><?php esc_html_e('Location', 'listeo_core'); ?></a></li>
								<?php
								endif; ?>
								<?php
								$faq_status = get_post_meta($post->ID, '_faq_status', true);
								if ($faq_status == 'on') : ?>
									<li id="listing-nav-faq"><a href="#listing-faq"><?php esc_html_e('FAQ', 'listeo_core'); ?></a></li>
									<?php
								endif;
								if ($listing_type !== 'classifieds') {
									if ($load_reviews && !get_option('listeo_disable_reviews')) {
										$reviews = get_comments(array(
											'post_id' => $post->ID,
											'status' => 'approve' //Change this to the type of comments to be displayed
										));
										if ($reviews) : ?>
											<li><a href="#listing-reviews"><?php esc_html_e('Reviews', 'listeo_core'); ?></a></li>
											<?php else:
											$place_data = listeo_get_google_reviews($post);
											if (!empty($place_data['result']['reviews'])) { ?>
												<li><a href="#listing-google-reviews"><?php esc_html_e('Google Reviews', 'listeo_core'); ?></a></li>

											<?php }
										endif; ?>
										<?php
										$usercomment = false;
										if (is_user_logged_in()) {
											$usercomment = get_comments(array(
												'user_id' => get_current_user_id(),
												'post_id' => $post->ID,
											));
										}
										//TODO if open comments
										if (!$usercomment) { ?>
											<li><a href="#add-review"><?php esc_html_e('Add Review', 'listeo_core'); ?></a></li>
										<?php } ?>
								<?php }
								}
								do_action('listeo/single-listing/navigation-end');
								?>


							</ul>
						</div>
						<?php


						// 		$d = DateTime::createFromFormat('d-m-Y', $expires);
						// 		echo $d->getTimestamp(); 
						?>
						<!-- Overview -->
						<div id="listing-overview" class="listing-section">
							<?php $template_loader->get_template_part('single-partials/single-listing', 'main-details');  ?>

							<!-- Description -->
							<?php do_action('listeo/single-listing/before-content'); ?>
							<?php the_content(); ?>
							<?php do_action('listeo/single-listing/after-content'); ?>
							<?php
							if (in_array('option_social_links', $packages_disabled_modules)) {
								if ($package && $package->has_listing_social_links() == 1) {
									$template_loader->get_template_part('single-partials/single-listing', 'socials');
								}
							} else {
								$template_loader->get_template_part('single-partials/single-listing', 'socials');
							}
							?>
							<?php $template_loader->get_template_part('single-partials/single-listing', 'features');  ?>
						</div>

						<?php do_action('listeo/single-listing/after-overview'); ?>

						<?php


						if ($count_gallery > 0 && $gallery_style == 'content'  && $load_gallery == true) : $template_loader->get_template_part('single-partials/single-listing', 'gallery-content');
						endif; ?>
						<?php $template_loader->get_template_part('single-partials/single-listing', 'pricing');  ?>
						<?php if (class_exists('WeDevs_Dokan') &&  get_post_meta(get_the_ID(), '_store_section_status', 1)) :   $template_loader->get_template_part('single-partials/single-listing', 'store');
						endif; ?>
						<?php if ($load_video) {
							$template_loader->get_template_part('single-partials/single-listing', 'video');
						} ?>
						<?php $template_loader->get_template_part('single-partials/single-listing', 'location');  ?>
						<?php do_action('listeo/single-listing/after-location'); ?>

						<?php
						if (listeo_core_listing_type_supports($listing_type, 'booking') && get_option('listeo_show_calendar_single')) {
							$template_loader->get_template_part('single-partials/single-listing', 'calendar');
						}
						$template_loader->get_template_part('single-partials/single-listing', 'faq');
						$template_loader->get_template_part('single-partials/single-listing', 'other-listings');
						?>
						<?php
						if (get_option('listeo_related_listings_status')) {
							$template_loader->get_template_part('single-partials/single-listing', 'related');
						}
						?>
						<?php
						// Nearby Listings - Separate Independent Feature
						if (get_option('listeo_nearby_listings_status')) {
							$template_loader->get_template_part('single-partials/single-listing', 'nearby');
						}
						?>
						<?php $template_loader->get_template_part('single-partials/single-listing', 'google-reviews'); ?>
						<?php if ($load_reviews && !get_option('listeo_disable_reviews')) {

							$template_loader->get_template_part('single-partials/single-listing', 'reviews');
						} ?>
						<?php do_action('listeo/single-listing/end-content'); ?>
					</div>
				<?php endwhile; // End of the loop. 
				?>

				<?php

				if ($layout == "right-sidebar" && $mobile_layout != "left-sidebar") : ?>
					<div class="col-lg-4 col-md-4  listeo-single-listing-sidebar margin-top-75 sticky">
						<?php do_action('listeo/single-listing/sidebar-start'); ?>
						<?php
						$classifieds_price = get_post_meta($post->ID, '_classifieds_price', true);

					if ($listing_type == 'classifieds' && !empty($classifieds_price)) {

							$currency_abbr = get_option('listeo_currency');
							$currency_postion = get_option('listeo_currency_postion');
							$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

						?>
							<span id="classifieds_price">
								<?php if (is_numeric($classifieds_price) && $currency_postion == "before") {
									echo $currency_symbol;
								}
								$classifieds_price = get_post_meta($post->ID, '_classifieds_price', true);
								if (is_numeric($classifieds_price)) {
									$decimals = get_option('listeo_number_decimals', 2);
									echo number_format_i18n(get_post_meta($post->ID, '_classifieds_price', true), $decimals);
								} else {
									echo $classifieds_price;
								}

								if (is_numeric($classifieds_price) && $currency_postion == "after") {
									echo $currency_symbol;
								} ?>
							</span>
						<?php } ?>
						<?php $template_loader->get_template_part('single-partials/single-listing', 'claim');  ?>
						<?php get_sidebar('listing'); ?>
						<?php do_action('listeo/single-listing/sidebar-end'); ?>
					</div>
					<!-- Sidebar / End -->
				<?php endif; ?>
				</div>
		</div>



	<?php else : ?>

		<?php get_template_part('content', 'none'); ?>

	<?php endif; ?>


	<?php get_footer(); ?>