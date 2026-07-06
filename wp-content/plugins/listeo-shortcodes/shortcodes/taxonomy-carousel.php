<?php 

	function listeo_taxonomy_carousel($atts, $content = null) {
        $shortcode_atts  = array(
			'taxonomy' => '',
			'show_counter' 	=> '',
			'only_top' 	=> 'yes',
			'autoplay'      => '',
            'autoplayspeed'      => '3000',

		);

		$taxonomy_names = get_object_taxonomies( 'listing','object' );
		
		foreach ($taxonomy_names as $key => $value) {
			$shortcode_atts[$value->name.'_include'] = '';
			$shortcode_atts[$value->name.'_exclude'] = '';
		}
		$taxonomy = $atts['taxonomy'];
        $atts = shortcode_atts($shortcode_atts, $atts, 'terms-grid' );
        
		$query_args = array(
			'include' => $atts[$taxonomy.'_include'],
			'exclude' => $atts[$taxonomy.'_exclude'],
			'hide_empty' => false,
		);
		if($atts['only_top'] == 'yes'){
			$query_args['parent'] = 0;
		}
       	$terms = get_terms( $atts['taxonomy'],$query_args);
       	ob_start();
       	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
       	?>

		<div class="fullwidth-slick-carousel category-carousel" <?php if($atts['autoplay'] == 'on') { ?>data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $atts['autoplayspeed']; ?>}' <?php } ?>>
			<!-- Item -->
			<?php foreach ( $terms as $term ) { 
				$cover_id = get_term_meta($term->term_id,'_cover',true);
				$cover = wp_get_attachment_image_src($cover_id,'listeo-blog-post'); ?>

				<div class="fw-carousel-item">
					<div class="category-box-container">
						<a href="<?php echo esc_url(get_term_link( $term )); ?>" class="category-box" data-background-image="<?php echo $cover[0];  ?>">
							<div class="category-box-content">
								<h3><?php echo $term->name; ?></h3>
								<?php if($atts['show_counter'] == 'true'): ?><span><?php echo $term->count ?> <?php esc_html_e('listings','listeo-shortcodes'); ?></span><?php endif; ?>
							</div>
							<span class="category-box-btn"><?php esc_html_e('Browse','listeo-shortcodes') ?></span>
						</a>
					</div>
				</div>

			<?php } ?>
		</div>
 		<?php }

	    return ob_get_clean(); 
    }
?>