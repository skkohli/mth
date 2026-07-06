<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
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

<?php $titlebar_status = get_option('listeo_blog_titlebar_status','show');

if( $titlebar_status == 'show' ) : ?>
<!-- Titlebar
================================================== -->
<div id="titlebar" class="<?php echo esc_attr(get_option('listeo_blog_titlebar_style','gradient')); ?>">
	<div class="container">
		<div class="row">
			<div class="col-md-12">
				
				<h2><?php echo get_option('listeo_blog_title','Blog'); ?></h2>
				<span><?php echo get_option('listeo_blog_subtitle','Latest News'); ?></span>
	
				<!-- Breadcrumbs -->
				<?php if(function_exists('bcn_display')) { ?>
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
<div class="container <?php echo esc_attr($sidebar_side); if( $titlebar_status == 'hide' ) { echo ' margin-top-50'; } ?>">

	<!-- Blog Posts -->
	<div class="blog-page">
		<div class="row">
			<div class="<?php echo esc_attr($main_columns); ?> <?php  echo esc_attr(($sidebar_side == 'left-sidebar') ? 'padding-left-30' : 'padding-right-30' ); ?> col-blog">

			<?php
			if ( have_posts() ) :

				/* Start the Loop */
				while ( have_posts() ) : the_post();

					get_template_part( 'blog-parts/content', get_post_format() );
				
				endwhile;

				?>
				<!-- Pagination -->
				<div class="clearfix"></div>
				<div class="row">
					<div class="col-md-12">
						<!-- Pagination -->
						<div class="pagination-container margin-bottom-40">
							<nav class="pagination">
							<?php
								if(function_exists('wp_pagenavi')) { 
									wp_pagenavi(array(
										'next_text' => '<i class="fa fa-chevron-right"></i>',
										'prev_text' => '<i class="fa fa-chevron-left"></i>',
										'use_pagenavi_css' => false,
										));
								} else {
									the_posts_navigation();	
								}?>
							</nav>
						</div>
				</div>
			</div>
			<!-- Pagination / End -->
				<?php

			else :
				get_template_part( 'template-parts/content', 'none' );
			endif; ?>

		
			</div>

			<?php if ($sidebar_side == 'left-sidebar' || $sidebar_side == 'right-sidebar') : ?>
			<!-- Widgets -->
			<div class="col-lg-3 col-md-4 col-sidebar">
				<div class="sidebar right">
					<?php get_sidebar(); ?>
				</div>
			</div>
			<?php endif; ?>
			<!-- Sidebar / End -->
		</div>
	<!-- Sidebar / End -->

	</div>

</div>

<?php get_footer(); ?>