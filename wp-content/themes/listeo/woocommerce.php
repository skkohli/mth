<?php

/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package WorkScout
 */

$full_width_header = get_post_meta($post->ID, 'listeo_full_width_header', TRUE);
if (empty($full_width_header)) {
	$full_width_header = 'use_global';
};

if ($full_width_header == 'use_global') {
	$full_width_header = get_option('listeo_full_width_header');
}

if ($full_width_header == 'enable') {
	get_header('fullwidth');
} else {
	get_header();
}


$parallax 			= get_option('listeo_shop_header_bg');
$parallax_color 	= get_option('listeo_shop_header_color');
$parallax_opacity 	= get_option('listeo_shop_header_bg_opacity');


$parallax_output  	= '';
$parallax_output .= (!empty($parallax)) ? ' data-background="' . esc_url($parallax) . '" ' : '';
$parallax_output .= (!empty($parallax_color)) ? ' data-color="' . esc_attr($parallax_color) . '" ' : '';
$parallax_output .= (!empty($parallax_opacity)) ? ' data-color-opacity="' . esc_attr($parallax_opacity) . '" ' : '';

if ( class_exists('WeDevs_Dokan') && is_single()) {
	$authordata = get_userdata($post->post_author);
	$author = $authordata->ID;

	$store_user = dokan()->vendor->get($author);
	$header_background = $store_user->get_banner();
?>

	<div id="titlebar" class="store-titlebar  no-store-bg">
		
		<div class="container">
			<div class="row">
				<div class="col-md-12">
					<?php dokan_get_template_part('store-header'); ?>
				</div>
			</div>
		</div>
	</div>
	
		<div class="container single-product-titlebar">
			<div class="row">
				<div class="col-md-12">

					
					<!-- Breadcrumbs -->
					<?php if (function_exists('bcn_display')) { ?>
						<nav id="breadcrumbs">
							<ul>
								<?php bcn_display_list(); ?>
							</ul>
						</nav>
					<?php } ?>

				</div>
			</div>
		</div>
	
	<?php } else {
	if (is_shop() && $parallax) { ?>
		<div class="parallax titlebar" <?php echo wp_kses_post($parallax_output); //XSS ok, escaped above 
										?>>
		<?php } ?>
		<!-- Titlebar
================================================== -->
		<div id="titlebar">
			<div class="container">
				<div class="row">
					<div class="col-md-12">

						<h1>
							<?php
							if (is_shop() || is_tax()) {
								the_archive_title();
							} else {
								the_title();
							}
							?>
						</h1>

						<!-- Breadcrumbs -->
						<?php if (function_exists('bcn_display')) { ?>
							<nav id="breadcrumbs">
								<ul>
									<?php bcn_display_list(); ?>
								</ul>
							</nav>
						<?php } ?>

					</div>
				</div>
			</div>
		</div>
		<?php if ($parallax) { ?>
		</div>
	<?php } ?>
<?php
}


$layout = get_option('pp_shop_layout', 'full-width');
$class  = ($layout != "full-width") ? "col-md-8 col-lg-9" : "col-md-12";
?>
<div class="container listeo-shop-grid <?php echo esc_attr($layout); ?>">

	<div class="row">

		<article id="post-<?php the_ID(); ?>" <?php post_class($class); ?>>
			<?php woocommerce_content(); ?>
		</article>

		<?php if ($layout != "full-width") {
			get_sidebar('shop');
		} ?>
	</div>

</div>



<?php


// $allProducts = get_posts(array(
// 	'posts_per_page' => -1,
// 	'post_type' => 'product',
// 	'tax_query' => array(
// 		array(
// 			'taxonomy' => 'product_cat',
// 			'field' => 'term_id',
// 			'terms' => 20 // Where term_id of Term 1 is "1".
// 		)
// 	)

// ));

// foreach ($allProducts as $product) {
// 	$terms = array('exclude-from-catalog', 'exclude-from-search');
	
// 	$id = $product->ID;
	
// 	wp_set_object_terms($id, $terms, 'product_visibility', true);
// 	$child_product = wc_get_product($id);
// 	// Change the product visibility
// 	$child_product->set_catalog_visibility('visible');
// 	// Save and sync the product visibility
// 	$child_product->save();
	
// }


get_footer(); ?>