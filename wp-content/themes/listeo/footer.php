<?php

/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package listeo
 */

// foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

// 	if ($cart_item['data']->product_type == 'listing_booking') {
// 		WC()->cart->remove_cart_item($cart_item_key);
// 	} 
// }


?>
<?php if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('footer')) { ?>
	<!-- Footer
================================================== -->
	<?php
	$sticky = get_option('listeo_sticky_footer');
	$style = get_option('listeo_footer_style');

	if (is_singular()) {

		$sticky_singular = get_post_meta($post->ID, 'listeo_sticky_footer', TRUE);

		switch ($sticky_singular) {
			case 'on':
			case 'enable':
				$sticky = true;
				break;

			case 'disable':
				$sticky = false;
				break;

			case 'use_global':
				$sticky = get_option('listeo_sticky_footer');
				break;

			default:
				$sticky = get_option('listeo_sticky_footer');
				break;
		}

		$style_singular = get_post_meta($post->ID, 'listeo_footer_style', TRUE);
		switch ($style_singular) {
			case 'light':
				$style = 'light';
				break;

			case 'dark':
				$style = 'dark';
				break;

			case 'use_global':
				$style = get_option('listeo_footer_style');
				break;

			default:
				$sticky = get_option('listeo_footer_style');
				break;
		}
	}

	$sticky = apply_filters('listeo_sticky_footer_filter', $sticky);
	?>
	<div id="footer" class="<?php if (get_option('listeo_custom_footer') == "enable") echo 'custom-footer ';
							echo esc_attr($style);
							echo esc_attr(($sticky == 'on' || $sticky == 1 || $sticky == true) ? " sticky-footer" : ''); ?> ">
		<!-- Main -->
		<div class="container">
			<div class="row">
				<?php
				$footer_layout = get_option('pp_footer_widgets', '6,3,3');

				$footer_layout_array = explode(',', $footer_layout);
				$x = 0;
				foreach ($footer_layout_array as $value) {
					$x++;
				?>
					<div class="col-md-<?php echo esc_attr($value); ?> col-sm-6 col-xs-12">
						<?php
						if (is_active_sidebar('footer' . $x)) {
							dynamic_sidebar('footer' . $x);
						}
						?>
					</div>
				<?php } ?>

			</div>
			<!-- Copyright -->
			<div class="row">
				<div class="col-md-12">
					<div class="footer-new-bottom-inner">
						<div class="footer-new-bottom-left"><?php $copyrights = get_option('pp_copyrights', '&copy; Theme by Purethemes.net. All Rights Reserved.');

															echo wp_kses(do_shortcode($copyrights), array('a' => array('href' => array(), 'target' => array(), 'title' => array()), 'br' => array(), 'em' => array(), 'strong' => array(),));
															?>
						</div>
						<div class="footer-new-bottom-right">

							<?php /* get the slider array */
							$footericons =  get_option('pp_footericons', array());
							if (!empty($footericons)) {

								echo '<ul class="new-footer-social-icons">';
								foreach ($footericons as $icon) {
									if ($icon['icons_service'] == 'telegram') {
										echo '<li><a target="_blank" title="' . esc_attr($icon['icons_service']) . '" href="' . esc_url($icon['icons_url']) . '"><i class="fa fa-' . $icon['icons_service'] . '"></i></a></li>';
									} elseif( $icon['icons_service'] == 'twitter' ) {
										echo '<li><a target="_blank" title="' . esc_attr($icon['icons_service']) . '" href="' . esc_url($icon['icons_url']) . '"><i class="fa-brands fa-x-' . $icon['icons_service'] . '"></i></a></li>';
									} elseif( $icon['icons_service'] == 'tiktok' ) {
										echo '<li><a target="_blank" title="' . esc_attr($icon['icons_service']) . '" href="' . esc_url($icon['icons_url']) . '"><i class="fa-brands fa-' . $icon['icons_service'] . '"></i></a></li>';
									}
									 else {
										echo '<li><a target="_blank" title="' . esc_attr($icon['icons_service']) . '" href="' . esc_url($icon['icons_url']) . '"><i class="icon-brand-' . $icon['icons_service'] . '"></i></a></li>';
									}
								}
								echo '</ul>';
							}
							?>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>

	<!-- Back To Top Button -->
	<div id="backtotop"><a href="#"></a></div>


	</div> <!-- weof wrapper -->
<?php } ?>

<?php if (is_singular('listing')) :
	$_booking_status = get_post_meta($post->ID, '_booking_status', true);

	if ($_booking_status) : ?>
		<!-- Booking Sticky Footer -->
		<div class="booking-sticky-footer">
			<div class="container">
				<div class="bsf-left">
					<?php
					$price_min = get_post_meta($post->ID, '_price_min', true);
					if (is_numeric($price_min)) {
						$decimals = get_option('listeo_number_decimals', 2);
						$price_min_raw = number_format_i18n($price_min, $decimals);
					}
					$currency_abbr = get_option('listeo_currency');
					$currency_postion = get_option('listeo_currency_postion');
					$currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

					if ($price_min) : ?>
						<h4><?php esc_html_e('Starting from', 'listeo') ?> <?php if ($currency_postion == 'after') {
																				echo $price_min_raw . $currency_symbol;
																			} else {
																				echo $currency_symbol . $price_min_raw;
																			} ?></h4>
					<?php else : ?>
						<h4><?php esc_html_e('Select dates to see prices', 'listeo') ?></h4>
					<?php endif; ?>

					<?php
					if (!get_option('listeo_disable_reviews')) {
						// Use the new combined rating display function
						if (function_exists('listeo_get_rating_display')) {
							$rating_data = listeo_get_rating_display($post->ID);
							$rating = $rating_data['rating'];
						}

						if (isset($rating) && $rating > 0) :
							$rating_type = get_option('listeo_rating_type', 'star');
							if ($rating_type == 'numerical') { ?>
								<div class="numerical-rating" data-rating="<?php $rating = str_replace(',', '.', $rating);
																			$rating_value = esc_attr(round($rating, 1));
																			printf("%0.1f", $rating_value); ?>">
								<?php } else { ?>
									<div class="star-rating" data-rating="<?php echo $rating; ?>">
									<?php } ?>

									</div>
							<?php endif;
					} ?>

								</div>
								<div class="bsf-right">
									<?php $book_btn = get_post_meta($post->ID, '_booking_link', true);
									if ($book_btn) { ?>
										<a href="<?php echo $book_btn; ?>" class="button"><?php esc_html_e('Book Now', 'listeo') ?></a>
									<?php } else { ?>
										<a href="#booking-widget-anchor" class="button"><?php esc_html_e('Book Now', 'listeo') ?></a>
									<?php } ?>
								</div>
				</div>
			</div>
	<?php endif;
endif; ?>
	<?php if ((is_page_template('template-home-search.php') || is_page_template('template-home-search-slider.php') || is_page_template('template-home-search-video.php') || is_page_template('template-home-search-splash.php')) && get_option('listeo_home_typed_status', 'enable') == 'enable') {
		$typed = get_option('listeo_home_typed_text');
		$typed_array = explode(',', $typed);
	?>
		<script src="https://cdn.jsdelivr.net/npm/typed.js@2.0.9"></script>
		<script>
			var typed = new Typed('.typed-words', {
				strings: <?php echo json_encode($typed_array); ?>,
				typeSpeed: 80,
				backSpeed: 80,
				backDelay: 4000,
				startDelay: 1000,
				loop: true,
				showCursor: true
			});
		</script>
	<?php } ?>
	<?php wp_footer(); ?>

	</body>

	</html>