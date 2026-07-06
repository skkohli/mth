<?php

/**
 * The template for displaying archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package listeo
 */


$sidebar_side = get_option('pp_blog_layout');

$full_width_header = get_option('listeo_full_width_header');


if ($full_width_header == 'enable' || $full_width_header == 'true') {
	get_header('fullwidth');
} else {
	get_header();
}
?>


<?php $titlebar_status = get_option('listeo_blog_titlebar_status', 'show');

if ($titlebar_status == 'show') : ?>
	<!-- Titlebar
================================================== -->
	<div id="titlebar" class="<?php echo esc_attr(get_option('listeo_blog_titlebar_style', 'gradient')); ?>">
		<div class="container">
			<div class="row">
				<div class="col-md-12">

					<h2><?php echo get_option('listeo_blog_title', 'Blog'); ?></h2>
					<span><?php echo get_option('listeo_blog_subtitle', 'Latest News'); ?></span>

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
<?php
endif;


if ($sidebar_side == 'left-sidebar' || $sidebar_side == 'right-sidebar') {
	$main_columns = 'col-lg-9 col-md-8';
} else {
	$main_columns = 'col-lg-12 col-md-12';
}
?>
<!-- Content
================================================== -->
<div class="container <?php echo esc_attr($sidebar_side);
						if ($titlebar_status == 'hide') {
							echo ' margin-top-50';
						} ?>">

	<!-- Blog Posts -->
	<div class="blog-page">
		<div class="row">
			<div class="col-lg-9 col-md-8 <?php echo esc_attr(($sidebar_side == 'left-sidebar') ? 'padding-left-30' : 'padding-right-30'); ?> col-blog">

				<?php
				if (have_posts()) :

					/* Start the Loop */
					while (have_posts()) : the_post();

						get_template_part('blog-parts/content', get_post_format());

					endwhile;

					the_posts_navigation();

				else :

					get_template_part('template-parts/content', 'none');

				endif; ?>


			</div>

			<!-- Widgets -->
			<div class="col-lg-3 col-md-4 col-sidebar">
				<div class="sidebar right">
					<?php get_sidebar(); ?>
				</div>
			</div>
			<!-- Sidebar / End -->
		</div>
		<!-- Sidebar / End -->

	</div>

</div>

<?php get_footer(); ?>