<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package listeo
 */
// get post meta listeo_full_width_header

$full_width_header = get_post_meta($post->ID, 'listeo_full_width_header', TRUE);
if(empty($full_width_header)){
	$full_width_header = 'use_global';
};


if($full_width_header == 'use_global'){
	$full_width_header = get_option('listeo_full_width_header');
	
}

if($full_width_header == 'enable' || $full_width_header == 'true') {
	get_header('fullwidth');
} else {
	get_header();
}


while ( have_posts() ) : the_post();

	get_template_part( 'template-parts/content', 'page' );

endwhile; // End of the loop.

get_footer();
