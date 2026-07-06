<?php

/**
 * The template for displaying listings
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package listeo
 */
$top_layout = get_option('pp_listings_top_layout', 'map');
if (is_tax()) {

	$top_layout = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_top_layout', true);
	if (empty($top_layout)) {
		$top_layout = get_option('pp_listings_top_layout', 'map');
	}
}


$full_width_header = get_option('listeo_full_width_header');


if ($full_width_header == 'enable' || $full_width_header == 'true') {

	($top_layout == 'half' || $top_layout == 'halfsidebar') ? get_header('fullwidthnosearch') : get_header('fullwidth');
} else {
	($top_layout == 'half') ? get_header('split') : get_header();
}

$content_layout = get_option('pp_listings_layout', 'grid');
$searchsource = 'home';
if (is_tax()) {
	// check if it has a search form
	$search_form = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_search_form', true);
	if (!empty($search_form)) {
		$searchsource = $search_form;
	}

	// check for custom layout settings

	$content_layout = get_term_meta(get_queried_object_id(), 'listeo_taxonomy_content_layout', true);
	if (empty($content_layout)) {
		$content_layout = get_option('pp_listings_layout', 'grid');
	}
}

$template_loader = new Listeo_Core_Template_Loader;




switch ($top_layout) {
	case 'map_searchform':
		$template_loader->get_template_part('archive/map');
		break;
	case 'map': ?>
		<!-- Map
		================================================== -->

		<div id="map-container" class="fullwidth-home-map">

			<div id="map" data-map-zoom="<?php echo get_option('listeo_map_zoom_global', 9); ?>">
				<!-- map goes here -->
			</div>

			<!-- Scroll Enabling Button -->
			<a href="#" id="scrollEnabling" title="<?php esc_html_e('Enable or disable scrolling on map', 'listeo_core') ?>"><?php esc_html_e('Enable Scrolling', 'listeo_core'); ?></a>

		</div>
		<a href="#" id="show-map-button" class="show-map-button" data-enabled="<?php esc_attr_e('Show Map ', 'listeo'); ?>" data-disabled="<?php esc_attr_e('Hide Map ', 'listeo'); ?>"><?php esc_html_e('Show Map ', 'listeo') ?></a>
	<?php
		break;


	case 'half':
	case 'halfsidebar':
	case 'disable':
		/*empty*/
		break;

	default:
		$template_loader->get_template_part('archive/titlebar');
		break;
}

if ($top_layout == 'half') {
	$template_loader->get_template_part('archive-listing-split');
} elseif ($top_layout == 'halfsidebar') {
	$template_loader->get_template_part('archive-listing-split-sidebar');
} else { ?>



	<?php
	$layout = get_option('pp_listings_sidebar_layout', 'right-sidebar');
	$mobile_layout = get_option('listeo_listings_mobile_layout', 'right-sidebar');
	?>

	<!-- Content
================================================== -->
	<div class="container <?php echo esc_attr($layout);
							if ($top_layout == 'map') {
								echo esc_attr(' margin-top-40');
							} ?> ?>">
		<div class="row sticky-wrapper">

			<?php if ($layout == "left-sidebar" || ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar")) : ?>
				<div class="col-lg-3 col-md-4 <?php if ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar") echo "col-lg-push-9"; ?> margin-top-75 sticky">
					<?php $template_loader->get_template_part('sidebar-listeo'); ?>
				</div>
				<!-- Sidebar / End -->
			<?php endif; ?>

			<?php switch ($layout) {
				case 'full-width':
			?><div class="col-md-12"><?php
										break;
									case 'left-sidebar':
										?><div class="col-lg-9 col-md-8 listings-column-content mobile-content-container <?php if ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar") {
																																echo "col-lg-pull-4";
																															}  ?> "><?php
																																			break;
																																		case 'right-sidebar':
																																			?><div class="col-lg-9 col-md-8 padding-right-30 listings-column-content mobile-content-container <?php if ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar") {
																																																						echo "col-lg-pull-3";
																																																					}  ?> "><?php
																																																																														break;

																																																																													default:
																																																																														?><div class="col-lg-9 col-md-8 padding-right-30 listings-column-content <?php if ($layout == 'right-sidebar' && $mobile_layout == "left-sidebar") {
																																																																															echo "col-lg-pull-4";
																																																																														}  ?> "><?php
																																																																														break;
																																																																												} ?>
							<!-- Search -->

							<?php
							if ($top_layout == 'search') {
								echo do_shortcode('[listeo_search_form action=' . get_post_type_archive_link('listing') . ' source="' . $searchsource . '" custom_class="gray-style margin-top-0 margin-bottom-40"]');
							} ?>

							<!-- Search Section / End -->

							<?php $top_buttons = get_option('listeo_listings_top_buttons');

							if ($top_buttons == 'enable') {
								$top_buttons_conf = get_option('listeo_listings_top_buttons_conf');
								if (is_array($top_buttons_conf) && !empty($top_buttons_conf)) {
									$list_top_buttons = implode("|", $top_buttons_conf);
								} else {
									$list_top_buttons = '';
								}
							?>
								<div class="row margin-bottom-15">
									<?php do_action('listeo_before_archive', $content_layout, $list_top_buttons); ?>
								</div>
							<?php
							} else {
								// Hook for elements that should appear even when top buttons are disabled
								do_action('listeo_archive_search_tools');
							} ?>
							<?php
							switch ($content_layout) {
								case 'list':
									$container_class = $content_layout . '-layout';
									break;
								case 'list_old':
								case 'grid_old':
									$container_class = $content_layout . '-layout';
									break;

								case 'compact':
								case 'grid_old':
									$container_class = $content_layout . '-layout row';
									break;
								default:
									$container_class = 'list-layout';
									break;
							}


							?>

							<!-- Listings -->
							<!-- Listings -->
							<?php
							do_action('listeo_archive_split_before_title');
							if (get_option('listeo_show_archive_title') == 'enable') { ?>

								<?php
								$title = get_option('listeo_listings_archive_title');
								if (!empty($title) && is_post_type_archive('listing')) { ?>
									<h1 class="page-title"><?php echo esc_html($title); ?></h1>
								<?php } else {
									the_archive_title('<h1 class="page-title">', '</h1>');
								} ?>

							<?php }

							do_action('listeo_archive_split_after_title'); ?>
							<div class="listings-container <?php
															echo esc_attr($container_class) ?>">
								<?php if ($content_layout == 'list' || $content_layout == 'list_old'): ?>
									<div class="row">
									<?php endif;

								if (have_posts()) :
									$data = '';
									if ($content_layout == 'grid' || $content_layout == 'grid_old'  || $content_layout == 'compact') {
										if ($layout == 'full-width') {
											$data .= 'data-grid_columns="3"';
										} else {
											$data .= 'data-grid_columns="2"';
										}
									}
									if (isset($_GET['tax-region'])) {
										$data .= ' data-region="' . esc_attr($_GET['tax-region']) . '" ';
									} else {
										$data .= ' data-region="' . get_query_var('region') . '" ';
									}
									$category = get_query_var('tax-listing_category');
									$listing_type = get_query_var('_listing_type');
									//check if it's a category  archive page
									if (is_tax('listing_category')) {
										$term = get_queried_object();
										$category = $term->slug;
									}
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
										$region = $region[0];
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
										<div <?php echo $data; ?> data-orderby="<?php echo $orderby_value;  ?>" data-style="<?php echo esc_attr($content_layout) ?>" id="listeo-listings-container" class="<?php if ($content_layout == "grid") {
																																																				echo 'new-grid-layout-nl ';
																																																			} ?>">
											<div class="loader-ajax-container" style="">
												<div class="loader-ajax"></div>
											</div>
											<?php

											$category = get_query_var('tax-listing_category');
											//check if it's a category  archive page
											if (is_tax('listing_category')) {
												$term = get_queried_object();
												$category = $term->slug;
											}
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
											} /* Start the Loop */
											while (have_posts()) : the_post();

												switch ($content_layout) {
													case 'list_old':
														$template_loader->get_template_part('content-listing-old');
														break;

													case 'list':
														$template_loader->get_template_part('content-listing');
														break;


													case 'grid':

														$template_loader->get_template_part('content-listing');

														break;

													case 'grid_old':
														if ($layout == 'full-width') {
															echo '<div class="col-lg-4 col-md-6 "> ';
														} else {
															echo '<div class="col-lg-6 col-md-12"> ';
														}

														$template_loader->get_template_part('content-listing-grid-old');
														echo '</div>';
														break;

													case 'compact':
														if ($layout == 'full-width') {
															echo '<div class="col-lg-4 col-md-6 "> ';
														} else {
															echo '<div class="col-lg-6 col-md-12"> ';
														}
														$template_loader->get_template_part('content-listing-compact');
														echo '</div>';
														break;

													default:
														$template_loader->get_template_part('content-listing');
														break;
												}

											endwhile;

											?>
											<div class="clearfix"></div>
										</div>
										<?php
										$ajax_browsing = get_option('listeo_ajax_browsing');
										$infinite_scroll = get_option('listeo_listeo_infinite_scroll', 'off');
										global $wp_query;
										?>
										<?php if ($infinite_scroll == 'on' && $ajax_browsing == 'on' && $wp_query->max_num_pages > 1) : ?>
											<div class="col-lg-12 col-md-12 listeo-load-more-container">
												<button class="listeo-load-more-button button loading" data-next-page="2">
													<span class="button-text"><?php esc_html_e('Loading...', 'listeo_core'); ?></span>
													<i class="fa fa-spinner fa-spin loading-icon" style="margin-left: 8px;"></i>
												</button>
											</div>
										<?php else : ?>
											<div class="col-lg-12 col-md-12 pagination-container  margin-top-0 margin-bottom-60  <?php if (isset($ajax_browsing) && $ajax_browsing == 'on') {
																																		echo esc_attr('ajax-search');
																																	} ?>">
												<nav class="pagination">
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
									<?php


								else :

									$template_loader->get_template_part('archive/no-found');

								endif; ?>
									<?php if ($content_layout == 'list'): ?>
									</div>
								<?php endif; ?>
								<?php if (term_description()) { ?>
									<div class="row term-description" style="    padding: 15px 15px 25px;
 ">
										<?php echo term_description(); ?>
									</div>
								<?php } ?>
							</div>
								</div>

								<?php if ($layout == "right-sidebar" && $mobile_layout != "left-sidebar") : ?>
									<div class="col-lg-3 col-md-4  margin-top-75 sticky">
										<?php $template_loader->get_template_part('sidebar-listeo'); ?>
									</div>
									<!-- Sidebar / End -->
								<?php endif; ?>


							</div>
						</div>

						<?php get_footer(); ?>
					<?php } //eof split 
					?>