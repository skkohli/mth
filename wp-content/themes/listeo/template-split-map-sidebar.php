<?php

/**
 * Template Name: Listing With Map - Split Page with Sidebar
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package Listeo
 */

$full_width_header = get_option('listeo_full_width_header');
if ($full_width_header == 'enable' || $full_width_header == 'true') {
	get_header('fullwidthnosearch');
} else {
	get_header('split');
} ?>
<?php
$template_loader = new Listeo_Core_Template_Loader;
$content_layout = get_option('pp_listings_layout', 'list');

$sidebar_status = get_option('pp_listings_split-sidebar-status', 'hide');
?>
<!-- Page Content
================================================== -->
<div class="full-page-container full-page-jobs">

	<div class="full-page-sidebar hidden-sidebar <?php if ($sidebar_status == 'show') { ?>enabled-sidebar<?php } ?>">
		<div class="full-page-sidebar-inner">
			<div class="sidebar-container">
				<div class="filter-button-container">
					<button class="enable-filters-button">
						<i class="enable-filters-button-icon"></i>
						<span class="show-text"><?php esc_html_e('Show Filters', 'listeo') ?></span>
						<span class="hide-text"><?php esc_html_e('Hide Filters', 'listeo') ?></span>
					</button>
				</div>
				<?php $template_loader->get_template_part('sidebar-listeo'); ?>
			</div>
		</div>
	</div>
	<!-- Full Page Sidebar / End -->
	<!-- Full Page Content -->
	<div class="full-page-content-container">

		<div class="sticky-filter-button">
			<div class="filter-button-container">
				<button class="enable-filters-button <?php if ($sidebar_status == 'show') { ?>active<?php } ?>">
					<i class="enable-filters-button-icon"></i>
					<span class="show-text"><?php esc_html_e('Show Filters', 'listeo') ?></span>
					<span class="hide-text"><?php esc_html_e('Hide Filters', 'listeo') ?></span>
				</button>
			</div>

		</div>
		<div class="full-page-content-inner">

			<!-- Filters Container -->
			<div class="filters-container">

				<!-- Page Title
		<h3 class="filters-headline">
			<?php $count_jobs = wp_count_posts('listing', 'readable');
			printf(_n('We have <strong class="count_jobs">%s</strong> <strong class="job_text">listing</strong> that could be the right fit!', 'We have <strong class="count_jobs">%s</strong> <strong class="job_text">listings</strong> that could be the right fit!', $count_jobs->publish, 'listeo'), $count_jobs->publish); ?>
		</h3> -->


				<!-- Enable Filters Button -->
				<div class="filter-button-container">
					<button class="enable-filters-button <?php if ($sidebar_status == 'show') { ?>active<?php } ?>">
						<i class="enable-filters-button-icon"></i>
						<span class="show-text"><?php esc_html_e('Show Filters', 'listeo') ?></span>
						<span class="hide-text"><?php esc_html_e('Hide Filters', 'listeo') ?></span>
					</button>

					<?php $slider_status = get_option('pp_listings_split-categories-slider-options', 'show_all');
					if($slider_status == 'show_all' || $slider_status == 'show_nonempty' || $slider_status == 'show_preselected' || $slider_status == 'show_listing_types') { ?>
						<div class="slider-container">
						<button class="nav-button prev hidden">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
								<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
							</svg>
						</button>

						<div class="category-slider" id="categorySlider">
							<!-- Category Items will be inserted here via JavaScript -->
						</div>

						<button class="nav-button next">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
								<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
							</svg>
						</button>
					</div>

						<?php
						$ifis_tax = get_query_var('listing_category');
						// check if it's string or array, if it's array take the first element, make sure it's not a 0
						if (is_array($ifis_tax)) {
							$ifis_tax = $ifis_tax[0];
						}
						wp_enqueue_script('listeo_core-categories-split-slider');
						wp_localize_script('listeo_core-categories-split-slider', 'sliderData', [
							'categories' => json_decode(listeo_get_slider_split_categories_json($ifis_tax), true),
							'currentCategory' => $ifis_tax,
						]);  ?>
					<?php } ?>
				</div>

			</div>
			<!-- Filters Container / End -->


			<!-- Full Page Job Listings Container -->
			<div class="full-page-job-listings-container">
				<div class="row fs-switcher" style="padding: 0px;">
					<?php
					while (have_posts()) : the_post(); ?>
						<article id="post-<?php the_ID(); ?>" <?php post_class('col-md-12'); ?>>
							<?php the_content(); ?>
						</article>
					<?php endwhile;   ?>
				</div>
			</div>
		</div>
	</div>
	<!-- Full Page Content / End -->


	<!-- Full Page Map -->
	<div class="full-page-map-container  map-fixed">
		<!-- Preloader -->
		<!-- Map -->
		<div id="map-container" class="">
			<div id="map" class="split-map" data-map-zoom="<?php echo get_option('listeo_map_zoom_global', 9); ?>" data-map-scroll="true">
				<!-- map goes here -->
			</div>
		</div>
	</div>
	<!-- Full Page Map / End -->
</div>
<?php get_footer('empty'); ?>