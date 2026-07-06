<?php 

	function listeo_taxonomy_grid($atts, $content = null) {

	
		$shortcode_atts  = array(
			'taxonomy' => '',
			'style' => '',
			'show_counter' 	=> 'yes',
			'only_top' 	=> 'yes'
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
			'pad_counts' => 'true',
			'hide_empty' => false,
			
		);
		if($atts['only_top'] == 'yes'){
			$query_args['parent'] = 0;
		}
		
       	$terms = get_terms( $atts['taxonomy'],$query_args);
       	ob_start();
       	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
       	?>

		<div class="categories-boxes-container<?php if($atts['style']=='alt'){ echo "-alt"; }?> margin-top-5 margin-bottom-30">
			
			<!-- Item -->
			<?php 
      		foreach ( $terms as $term ) { 
		        $t_id = $term->term_id;
		        
				$icon = get_term_meta($t_id,'icon',true);
				$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
				$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
		        if(empty($icon)) {
					$icon = 'fa fa-globe' ;
		        }
		        
		        ?>
			<a href="<?php echo get_term_link( $term ); ?>" class="category-small-box<?php if($atts['style']=='alt'){ echo "-alt"; }?>">
				<?php if (!empty($_icon_svg_image)) { ?>
					<i class="listeo-svg-icon-box-grid">
						<?php echo listeo_render_svg_icon($_icon_svg); ?>
					</i>
          		<?php } else { 
          			if($icon != 'empty') {
          				$check_if_im = substr($icon, 0, 3);
	                    if($check_if_im == 'im ') {
	                       echo' <i class="'.esc_attr($icon).'"></i>'; 
	                    } else {
	                       echo ' <i class="fa '.esc_attr($icon).'"></i>'; 
	                    }
          			}
          		} ?>
				<h4><?php echo $term->name; ?></h4>
				<?php if($atts['show_counter']=="yes" || $atts['show_counter']=="true") { ?><span  class="category-box-counter<?php if($atts['style']=='alt'){ echo "-alt"; }?>"><?php echo $term->count ?></span> <?php } ?>
				<?php if($atts['style']=='alt'){ 
					$cover_id = get_term_meta($term->term_id,'_cover',true);
					if($cover_id) {
						$cover = wp_get_attachment_image_src($cover_id,'listeo-blog-post');  ?>
						<img src="<?php echo $cover[0];  ?>">
					<?php }
				 } ?>
			</a>

			<?php } ?>
		</div>

 		<?php }

	    return ob_get_clean(); 
    }
?>