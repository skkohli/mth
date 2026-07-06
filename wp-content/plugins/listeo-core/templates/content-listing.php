<?php
$template_loader = new Listeo_Core_Template_Loader;
$is_featured = listeo_core_is_featured($post->ID);
$is_instant = listeo_core_is_instant_booking($post->ID);
$listing_type = get_post_meta($post->ID, '_listing_type', true);

$show_as_ad = false;
if (isset($data)) :

	$show_as_ad = isset($data->ad) ? $data->ad : '';
	if ($show_as_ad) {
		$ad_type = get_post_meta($post->ID, 'ad_type', true);
		$ad_id = get_post_meta($post->ID, 'ad_id', true);
	}
endif;

$elements = get_option('listeo_listings_list_items', array(
	'category',
	'bookmark',
	'location',
	'customfields',
	'features',
	'open_now',
	'price',
));
// category
// bookmark
// location
// customfields
// features
// open_now
// excerpt
?>
<!-- Listing Item -->


<div <?php if ($show_as_ad) : ?> data-ad-id="<?php echo $ad_id; ?>" data-campaign-type="<?php echo $ad_type; ?>" <?php endif; ?> class="listing-card-container-nl margin-bottom-30 listing-geo-data" <?php echo listeo_get_geo_data($post); ?>>
	<div class="listing-card-nl">
		<!-- ===== LEFT: IMAGE SLIDER ===== -->
		<div class="listing-image-container-nl">
			<a href="<?php the_permalink(); ?>">
				<div class="slider-wrapper-nl">
					<?php $template_loader->get_template_part('content-listing-gallery');  ?>
				</div>
			</a>

			<?php if (in_array('open_now', $elements) && get_post_meta($post->ID, '_opening_hours_status', true)) {
				if (listeo_check_if_open()) { ?>
					<div class="status-button-nl">
						<?php esc_html_e('Now Open', 'listeo_core'); ?>
					</div>
				<?php } else { ?>
					<div class="status-button-nl closed-nl">
						<?php esc_html_e('Now Closed', 'listeo_core'); ?>
					</div>
			<?php }
			} ?>
			<div class="slider-arrow-nl left-nl" id="prevBtn"><i class="fa-solid fa-chevron-left"></i></div>
			<div class="slider-arrow-nl right-nl" id="nextBtn"><i class="fa-solid fa-chevron-right"></i></div>
			<div class="image-overlay-top-nl">

				<?php
				// First try to get global categories
				$terms = get_the_terms(get_the_ID(), 'listing_category');

				// If no global categories, try type-specific taxonomy
				if ((!$terms || is_wp_error($terms)) && isset($post)) {
					$taxonomy = listeo_get_listing_taxonomy($post);
					if ($taxonomy !== 'listing_category') {
						$terms = get_the_terms(get_the_ID(), $taxonomy);
					}
				}

				if (in_array('category', $elements) && $terms && !is_wp_error($terms)) :
					$categories = array();
					$count = 0;
					$cat_limit = get_option('listeo_listings_categories_number', 3);
					foreach ($terms as $term) {
						if ($count++ > $cat_limit) {
							break;
						}
						$categories[] = $term->name;
					}

					$categories_list = join(", ", $categories);
					echo '<span class="listing-category-tag-nl">';
					esc_html_e($categories_list);
					echo '</span>';
				endif; ?>
				<?php if (in_array('bookmark', $elements)) { ?>

					<?php
					if (listeo_core_check_if_bookmarked($post->ID)) {
						$nonce = wp_create_nonce("listeo_core_bookmark_this_nonce"); ?>
						<div class="favorite-icon-nl" id="favoriteBtn">
							<span class="listeo_core-unbookmark-it fa-solid fa-heart" style="display: block;" data-post_id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"></span>
						</div>
						<?php } else {
						if (is_user_logged_in()) {
							$nonce = wp_create_nonce("listeo_core_remove_fav_nonce"); ?>
							<div class="favorite-icon-nl" id="favoriteBtn">
								<span class="save listeo_core-bookmark-it fa-regular fa-heart" data-post_id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"></span>
							</div>

						<?php } else { ?>
							<div class="favorite-icon-nl" id="favoriteBtn">
								<span class="save fa-regular fa-heart tooltip left" title="<?php esc_html_e('Login To Bookmark Items', 'listeo_core'); ?>"></span>
							</div>
						<?php } ?>
					<?php } ?>


				<?php } ?>
			</div>
		</div>

		<!-- ===== RIGHT: LISTING DETAILS (NOW 2-COLUMN) ===== -->
		<a href="<?php the_permalink(); ?>" class="listing-details-nl">

			<!-- Main Content Column (Left) -->
			<div class="details-main-col-nl">
				<div class="listing-badges-nl">
					<?php if ($show_as_ad) : ?><span class="badge-nl sponsored-nl"><?php esc_html_e('Sponsored', 'listeo_core'); ?></span><?php endif; ?>
					<?php if ($is_featured) : ?><span class="badge-nl featured-nl"><i class="fa-solid fa-star"></i> <?php esc_html_e('Featured', 'listeo_core'); ?></span><?php endif; ?>
				</div>
				<h2 class="listing-title-nl"><?php the_title(); ?>
					<div class="listing-title-badges-nl">
						<?php if (listeo_core_is_verified($post->ID)) : ?>
							<div class="verified-icon-nl title-badge-nl">
								<i class="fa fa-check"></i>
								<span class="tooltip-nl"><?php esc_html_e('Verified Listing', 'listeo_core'); ?></span>
							</div>
						<?php endif; ?>
						<?php if ($is_instant) { ?>
							<div class="instant-badge-nl title-badge-nl">
								<i class=" fa fa-bolt"></i>
								<span class="tooltip-nl"><?php esc_html_e('Instant Booking', 'listeo_core'); ?></span>
							</div>
						<?php } ?>
					</div>
				</h2>
				<?php if (in_array('location', $elements) && has_listing_location($post)) { ?><p class="listing-location-nl"><?php the_listing_location_link($post->ID, false); ?></p><?php } ?>


				<?php
				if (in_array('excerpt', $elements)) {
					$excerpt = get_the_excerpt();

					if (!empty($excerpt)) {
						// Split the excerpt into words and limit to 15 words
						$words = explode(' ', $excerpt);
						$limited_excerpt = implode(' ', array_slice($words, 0, 10));

						// Add ellipsis if the original excerpt was longer
						if (count($words) > 10) {
							$limited_excerpt .= '...';
						}

						echo '<p class="listing-excerpt-nl">' . esc_html($limited_excerpt) . '</p>';
					}
				}
				if (in_array('customfields', $elements)) {
					
					get_custom_fields_for_list($post, false);
				}
				$term_list = get_the_terms($post->ID, 'listing_feature');
				$tax_obj = get_taxonomy('listing_feature');

				if (in_array('features', $elements) && !empty($term_list)) {

					// limit of features to show
					$feature_limit = get_option('listeo_listings_features_number', 10);
				?>

					<div class="listing-amenities-nl">
						<?php
						$term_count = 0;
						foreach ($term_list as $term) {
							if ($term_count++ >= $feature_limit) {
								break;
							}

							$svg_flag = false;
							$icon = false;

							$term_link = get_term_link($term);
							if (is_wp_error($term_link))
								continue;
							$t_id = $term->term_id;
							if (isset($t_id)) {
								$_icon_svg = get_term_meta($t_id, '_icon_svg', true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
							}

							if (isset($_icon_svg_image) && !empty($_icon_svg_image)) {
								$svg_flag = true;
								$icon = listeo_render_svg_icon($_icon_svg);
								//$icon = '<img class="listeo-map-svg-icon" src="'.$_icon_svg_image[0].'"/>';


							} else {

								if (!$icon) {

									$icon = get_term_meta($t_id, 'icon', true);
								}
							}

							if (!empty($icon)) {
								echo '<div class="amenity-icon-nl">';
								if ($svg_flag == true) {
									echo '<span class="feature-svg-icon">' . $icon . '</span><span class="tooltip-nl">' . $term->name . '</span>';
								} else {
									echo '<i class="' . $icon . '"></i> <span class="tooltip-nl">' . $term->name . '</span>';
								}
								echo '</div>';
							}
						}
						?>

					</div>
				<?php } ?>
			</div>

			<!-- Sidebar Column (Right) -->
			<div class="details-sidebar-col-nl">
				<div class="details-sidebar-upper-nl">

					<?php
					if (!get_option('listeo_disable_reviews')) {
						// Use the new combined rating display function
						$rating_data = listeo_get_rating_display($post->ID);
						$rating = $rating_data['rating'];
						$review_count = $rating_data['count'];

						if (isset($rating) && $rating > 0) :
							$rating_type = get_option('listeo_rating_type', 'star');
					?>
							<div class="listing-rating-nl">
								<div class="stars-nl" data-rating="<?php echo $rating; ?>">

									<?php
									echo listeo_generate_star_rating($rating, $review_count);
									?>
								</div>
							</div>
					<?php endif;
					} ?>
				</div>
				<?php
				// if listing type supports classifieds pricing, show price
				if ($listing_type == 'classifieds') {
					$price = get_post_meta($post->ID, '_classifieds_price', true);
					$currency_abbr = get_option('listeo_currency');
					$currency_postion = get_option('listeo_currency_postion');
					$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
					if ($price) { ?>
						<div class="listing-booking-nl">
							<p class="price-nl"><?php
												if (is_numeric($price) && $currency_postion == "before") {
													echo $currency_symbol;
												}
												if (is_numeric($price)) {
													$decimals = get_option('listeo_number_decimals', 2);
													echo number_format($price, $decimals);
												} else {
													echo $price;
												}
												if (is_numeric($price) && $currency_postion == "after") {
													echo $currency_symbol;
												} ?></p>
						</div>
					<?php }
				}
				if (in_array('price', $elements)) {
					if (get_the_listing_price_range()) : ?>
						<div class="listing-booking-nl">
							<p class="price-nl"><?php echo get_the_listing_price_range(); ?></p>
							<?php $price_type = get_post_meta($post->ID, '_count_by_hour', true) ? esc_html__('per hour', 'listeo_core') : esc_html__('per day', 'listeo_core');


							if (listeo_core_listing_type_supports($listing_type, 'date_range_booking') && $price_type) { ?>
								<span class="price-period-nl"><?php echo $price_type; ?></span>
							<?php } ?>
						</div>
				<?php endif;
				} ?>


			</div>


		</a>
	</div>
</div>


<!-- Listing Item / End -->