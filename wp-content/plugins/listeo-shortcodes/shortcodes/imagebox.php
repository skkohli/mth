<?php 
	
	/**
	* Headline shortcode
	* Usage: [iconbox title="Service Title" url="#" icon="37"] test [/headline]
	*/
	function listeo_imagebox( $atts, $content ) {
	  extract(shortcode_atts(array(
		    'category' 		=> '',/*it's used for region but it's too late to rename it */
		    'listing_feature' => '',
		    'listing_category' => '',
		    'event_category' => '',
		    'service_category' => '',
		    'rental_category' => '',
		    
		    'featured' 		=> '',
		    'show_counter' 	=> '',
	        'background'	=> '',
	        'style'	=> '',//alternative-imagebox
		    'from_vs' 		=> 'no',
	    ), $atts));

 		if($from_vs=='yes') {
	    	$background = wp_get_attachment_url( $background );
		}

		if($category) {
			$term = get_term_by( 'id', $category, 'region' );
			$url = get_term_link((int) $category,'region');
		}

		if($listing_feature) {
			$term = get_term_by( 'id', $listing_feature, 'listing_feature' );
			$url = get_term_link((int) $listing_feature,'listing_feature');
		}

		if($listing_category) {
			$term = get_term_by( 'id', $listing_category, 'listing_category' );
			$url = get_term_link((int) $listing_category,'listing_category');
		}

		if($event_category) {
			$term = get_term_by( 'id', $event_category, 'event_category' );
			$url = get_term_link((int) $event_category, 'event_category');
		}

		if($service_category) {
			$term = get_term_by( 'id', $service_category, 'service_category' );
			$url = get_term_link((int) $service_category, 'service_category');
		}

		if($rental_category) {
			$term = get_term_by( 'id', $rental_category, 'rental_category' );
			$url = get_term_link((int) $rental_category, 'rental_category');
		}
	
		if( is_wp_error( $term ) || $term == false)   {
			return;
		} 
		if( is_wp_error( $url ) || $url == false)   {
			return;
		} 
        ob_start(); ?>
		 <!-- Image Box -->
		<?php if($style == 'alternative-imagebox') { ?>
		<div class="alternative-imagebox">
		<?php } ?> 
		<a href="<?php echo esc_url($url); ?>" class="img-box " data-background-image="<?php echo esc_attr($background); ?>">
			
			<?php if($featured) : ?>
			<!-- Badge -->
			<div class="listing-badges">
				<span class="featured"><?php esc_html_e('Featured','listeo-shortcodes') ?></span>
			</div>
			<?php endif; ?>

			<div class="img-box-content visible">
				<h4><?php echo $term->name; ?></h4>
				<?php if($show_counter) : ?><span><?php echo $term->count; ?> <?php esc_html_e('Listings','listeo-shortcodes') ?></span> <?php endif; ?>
			</div>
		</a>
		<?php if($style == 'alternative-imagebox') { ?>
		</div>
		<?php } ?> 

	    <?php
	    $output =  ob_get_clean() ;
       	return  $output ;
	}

?>