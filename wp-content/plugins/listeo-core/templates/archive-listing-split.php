<?php
$template_loader = new Listeo_Core_Template_Loader;
$searchsource = 'half';
if (is_tax()) {
	// check if it has a search form
	$search_form = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_search_form', true);
	if (!empty($search_form)) {
		$searchsource = $search_form;
	}
}

?>
<!-- Content
================================================== -->
<div class="fs-container">

	<div class="fs-inner-container content">
		<div class="fs-content">

			<section class="search">
				<a href="#" id="show-map-button" class="show-map-button" data-enabled="<?php esc_attr_e('Show Map ', 'listeo'); ?>" data-disabled="<?php esc_attr_e('Hide Map ', 'listeo'); ?>"><?php esc_html_e('Show Map ', 'listeo') ?></a>
				<div class="row">
					<div class="col-md-12">

						<?php echo do_shortcode('[listeo_search_form source="'. $searchsource.'" more_custom_class="margin-bottom-30"]'); ?>

					</div>
				</div>

			</section>
			<!-- Search / End -->

			<?php $content_layout = get_option('pp_listings_layout', 'grid');
			if (is_tax()) {
		
				$content_layout = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_content_layout', true);
				if (empty($content_layout)) {
					$content_layout = get_option('pp_listings_layout', 'grid');
				}
			} ?>
			<section class="listings-container margin-top-45">
				<!-- Sorting / Layout Switcher -->
				<div class="row fs-switcher">
					<?php
					do_action('listeo_archive_split_before_title');
					if (get_option('listeo_show_archive_title') == 'enable') { ?>
						<div class="col-md-12">
							<?php
							$title = get_option('listeo_listings_archive_title');
							if (!empty($title) && is_post_type_archive('listing')) { ?>
								<h1 class="page-title"><?php echo esc_html($title); ?></h1>
							<?php } else {
								the_archive_title('<h1 class="page-title">', '</h1>');
							} ?>
						</div>
					<?php }
					do_action('listeo_archive_split_after_title'); ?>

					<?php $top_buttons = get_option('listeo_listings_top_buttons','enable');

					if ($top_buttons == 'enable') {
						$top_buttons_conf = get_option('listeo_listings_top_buttons_conf',array('order','layout'));
						if (is_array($top_buttons_conf) && !empty($top_buttons_conf)) {

							if (($key = array_search('radius', $top_buttons_conf)) !== false) {
								unset($top_buttons_conf[$key]);
							}
							if (($key = array_search('filters', $top_buttons_conf)) !== false) {
								unset($top_buttons_conf[$key]);
							}
							$list_top_buttons = implode("|", $top_buttons_conf);
						} else {
							$list_top_buttons = '';
						}
					?>

						<?php do_action('listeo_before_archive', $content_layout, $list_top_buttons); ?>

					<?php
					} ?>

				</div>

				<!-- Listings -->
				<div class=" row fs-listings">

					<?php

					switch ($content_layout) {
						case 'list':
						case 'list_old':
						case 'grid':
						case 'grid_old':
							$container_class = $content_layout . '-layout';
							break;

						case 'compact':
							$container_class = $content_layout;
							break;

						default:
							$container_class = 'list-layout';
							break;
					}

					$data = '';
				
					$category = get_query_var('tax-listing_category');
					//check if it's a category  archive page
					if (is_tax('listing_category')) {
						$term = get_queried_object();
						$category = $term->slug;
					}
					$listing_type = get_query_var('_listing_type');
				

					$region = get_query_var('tax-region');
					if (is_tax('region')) {
						$term = get_queried_object();
						$region = $term->slug;
					}
					$feature = get_query_var('tax-listing_feature');
					if (is_tax('listing_feature')) {
						$term = get_queried_object();
						$feature = $term->slug;
					}
					if (is_array($region)) {
						$region = end($region);
					}
					if (is_array($category)) {
						
						$category = end($category);
					}
					if (is_array($feature)) {
						$feature = $feature[0];
					}
					$data .= ' data-region="' . $region . '" ';
					$data .= ' data-_listing_type="' . $listing_type . '" ';
					$data .= ' data-category="' . $category . '" ';
					$data .= ' data-feature="' . $feature . '" ';

					// Add data attributes for all listing type taxonomies (standard + custom)
					$all_listing_types = listeo_core_get_listing_types(true);

					foreach ($all_listing_types as $type_key => $type_data) {
						$taxonomy_name = $type_key . '_category';
						$query_var = $taxonomy_name;
						$data .= ' data-' . $taxonomy_name . '="' . get_query_var($query_var) . '" ';
					}
					$orderby_value = isset($_GET['listeo_core_order']) ? (string) $_GET['listeo_core_order']  : get_option('listeo_sort_by', 'date');
					?>
					<!-- Listings -->
					<div data-grid_columns="2"  <?php echo $data; ?> data-orderby="<?php echo $orderby_value;  ?>" data-style="<?php echo esc_attr($content_layout) ?>" class="listings-container <?php  if($content_layout=="grid") { echo 'new-grid-layout-nl '; }  echo esc_attr($container_class) ?>" id="listeo-listings-container">
						<div class="loader-ajax-container">
							<div class="loader-ajax"></div>
						</div>
						<?php



						$ad_filter = array(
							'listing_category' 	=> $category,
							'listing_feature'	=> $feature,
							'region' 			=> $region,
						);
						
						// get posts from ad
						$ads = listeo_get_ids_listings_for_ads('search', $ad_filter);
						
						// if no ads, don't show them
						if (!empty($ads)) {
							
						
						$ad_posts_count = count($ads);
						$ad_posts_index = 0;

						$ads_args = array(
							'post_type' => 'listing',
							'post_status' => 'publish',
							'posts_per_page' => 2,
							'orderby' => 'rand',
							'post__in' => $ads,
						);
						$ads_query = new \WP_Query($ads_args);

						if ($ads_query->have_posts()) {
							while ($ads_query->have_posts()) {
								$ads_query->the_post();
								$ad_posts_index++;
								$ad_data = array(
									'ad' => true,
									'ad_id' => get_the_ID(),
								);
								switch ($content_layout) {
										case 'list':
											$template_loader->set_template_data($ad_data)->get_template_part('content-listing');
											break;
										case 'list_old':
											$template_loader->set_template_data($ad_data)->get_template_part('content-listing-old');
											break;
										case 'grid':

											$template_loader->set_template_data($ad_data)->get_template_part('content-listing-grid');

											break;
										case 'grid_old':
											echo '<div class="col-lg-6 col-md-12"> ';
											$template_loader->set_template_data($ad_data)->get_template_part('content-listing-grid-old');
											echo '</div>';
											break;

										case 'compact':
											echo '<div class="col-lg-6 col-md-12"> ';
											$template_loader->set_template_data($ad_data)->get_template_part('content-listing-compact');
											echo '</div>';
											break;


										default:
										$template_loader->set_template_data($ad_data)->get_template_part('content-listing');
										break;
								}
							}
						}
						// reset post data
						wp_reset_postdata();
						}
						/* Start the Loop */

						if (have_posts()) :


							/* Start the Loop */
							while (have_posts()) : the_post();

								switch ($content_layout) {
									case 'list':
										$template_loader->get_template_part('content-listing');
										break;
									case 'list_old':
										$template_loader->get_template_part('content-listing-old');
										break;
									case 'grid_old':
										echo '<div class="col-lg-6 col-md-12"> ';
										$template_loader->get_template_part('content-listing-grid-old');
										echo '</div>';
										break;
									
									case 'grid':

										$template_loader->get_template_part('content-listing-grid');

										break;
									case 'compact':
										echo '<div class="col-lg-6 col-md-12"> ';
										$template_loader->get_template_part('content-listing-compact');
										echo '</div>';
										break;

									default:
										$template_loader->get_template_part('content-listing');
										break;
								}

							endwhile;


						else :

							$template_loader->get_template_part('archive/no-found');

						endif; ?>

						<div class="clearfix"></div>
					</div>
					<?php
					$ajax_browsing = get_option('listeo_ajax_browsing');
					$infinite_scroll = get_option('listeo_listeo_infinite_scroll', 'off');
					global $wp_query;
					?>
					<?php if( $infinite_scroll == 'on' && $ajax_browsing == 'on' && $wp_query->max_num_pages > 1 ) : ?>
						<div class="listeo-load-more-container row">
							<div class="col-md-12" style="text-align: center;">
								<button class="listeo-load-more-button button loading" data-next-page="2">
									<span class="button-text"><?php esc_html_e('Loading...', 'listeo_core'); ?></span>
									<i class="fa fa-spinner fa-spin loading-icon" style="margin-left: 8px;"></i>
								</button>
							</div>
						</div>
					<?php else : ?>
						<div class="pagination-container margin-top-45 margin-bottom-60 row  <?php if (isset($ajax_browsing) && $ajax_browsing == 'on') {
																									echo esc_attr('ajax-search');
																								} ?>">
							<nav class="pagination col-md-12">
								<?php
								if ($ajax_browsing == 'on') {
									$pages = $wp_query->max_num_pages;
									echo listeo_core_ajax_pagination($pages, 1);
								} else
								if (function_exists('wp_pagenavi')) {
									wp_pagenavi(array(
										'next_text' => '<i class="fa fa-chevron-right"></i>',
										'prev_text' => '<i class="fa fa-chevron-left"></i>',
										'use_pagenavi_css' => false,
									));
								} else {
									the_posts_navigation();
								} ?>
							</nav>
						</div>
					<?php endif; ?>
					<?php if (term_description()) { ?>
						<div class="row term-description" style="    padding: 15px 55px 25px;
 ">
							<?php echo term_description(); ?>
						</div>
					<?php } ?>
					<div class="copyrights margin-top-0"><?php $copyrights = get_option('pp_copyrights', '&copy; Theme by Purethemes.net. All Rights Reserved.');

															if (function_exists('icl_register_string')) {
																icl_register_string('Copyrights in footer', 'copyfooter', $copyrights);
																echo icl_t('Copyrights in footer', 'copyfooter', $copyrights);
															} else {
																echo wp_kses($copyrights, array('a' => array('href' => array(), 'title' => array()), 'br' => array(), 'em' => array(), 'strong' => array(),));
															} ?>

					</div>
				</div>
			</section>

		</div>
	</div>
	<div class="fs-inner-container map-fixed">

		<!-- Map -->
		<div id="map-container" class="">
			<div id="map" class="split-map" data-map-zoom="<?php echo get_option('listeo_map_zoom_global', 9); ?>" data-map-scroll="true">
				<!-- map goes here -->
			</div>

		</div>

	</div>
</div>

<div class="clearfix"></div>

<?php get_footer('empty'); ?>