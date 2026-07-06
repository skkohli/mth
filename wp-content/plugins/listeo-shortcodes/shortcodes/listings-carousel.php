<?php

function listeo_listings_carousel( $atts, $content ) {
        extract(shortcode_atts(array(
            'limit'         =>'6',
            'layout'        =>'standard',
            'orderby'       => 'date',
            'order'         => 'DESC',
            'tax-listing_category'    => '',
            'tax-service_category'    => '',
            'tax-rental_category'    => '',
            'tax-event_category'    => '',
            'relation'    => 'OR',
            'exclude_posts' => '',
            'include_posts' => '',
            'feature'       => '',
            'region'        => '',
        
            'featured'      => '',
            'fullwidth'     => '',
            'style'         => '',
            'autoplay'      => '',
            'autoplayspeed'      => '3000',
            'from_vs'       => 'no',

            ), $atts));

        $output = '';
        $randID = rand(1, 99); // Get unique ID for carousel

        $meta_query = array();

       
        $args = array(
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            'tax_query'              => array(),
            );

        if($featured){
            $args['meta_key'] = '_featured';
            $args['meta_value'] = 'on';
 
        }
 
        if(!empty($exclude_posts)) {
            $exl = is_array( $exclude_posts ) ? $exclude_posts : array_filter( array_map( 'trim', explode( ',', $exclude_posts ) ) );
            $args['post__not_in'] = $exl;
        }

        if(!empty($include_posts)) {
            $inc = is_array( $include_posts ) ? $include_posts : array_filter( array_map( 'trim', explode( ',', $include_posts ) ) );
            $args['post__in'] = $inc;
        }

        if($feature){
            $feature = is_array( $feature ) ? $feature : array_filter( array_map( 'trim', explode( ',', $feature ) ) );
            foreach ($feature as $key) {
                array_push($args['tax_query'] , array(
                   'taxonomy' =>   'listing_feature',
                   'field'    =>   'slug',
                   'terms'    =>   $key,
                   
                ));
            }
        }

        if(isset($atts['tax-listing_category'])){
            $category = is_array( $atts['tax-listing_category'] ) ? $atts['tax-listing_category'] : array_filter( array_map( 'trim', explode( ',', $atts['tax-listing_category'] ) ) );
            foreach ($category as $key) {
                array_push($args['tax_query'] , array(
                   'taxonomy' =>   'listing_category',
                   'field'    =>   'slug',
                   'terms'    =>   $key,
                   
                ));
            }
        }

        if(isset($atts['tax-service_category'])){
            $category = is_array( $atts['tax-service_category'] ) ? $atts['tax-service_category'] : array_filter( array_map( 'trim', explode( ',', $atts['tax-service_category'] ) ) );
            foreach ($category as $key) {
                array_push($args['tax_query'] , array(
                   'taxonomy' =>   'service_category',
                   'field'    =>   'slug',
                   'terms'    =>   $key,
                   
                ));
            }
        }
        if(isset($atts['tax-rental_category'])){
            $category = is_array( $atts['tax-rental_category'] ) ? $atts['tax-rental_category'] : array_filter( array_map( 'trim', explode( ',', $atts['tax-rental_category'] ) ) );
            foreach ($category as $key) {
                array_push($args['tax_query'] , array(
                   'taxonomy' =>   'rental_category',
                   'field'    =>   'slug',
                   'terms'    =>   $key,
                   
                ));
            }
        }

        if(isset($atts['tax-event_category'])){
            $category = is_array( $atts['tax-event_category'] ) ? $atts['tax-event_category'] : array_filter( array_map( 'trim', explode( ',', $atts['tax-event_category'] ) ) );
            foreach ($category as $key) {
                array_push($args['tax_query'] , array(
                   'taxonomy' =>   'event_category',
                   'field'    =>   'slug',
                   'terms'    =>   $key,
                   
                ));
            }
        }

        if($region){
            
                array_push($args['tax_query'] , array(
                   'taxonomy' =>   'region',
                   'field'    =>   'slug',
                   'terms'    =>   $region,
                   'operator' =>   'IN'
                   
                ));
            
        }
         $args['tax_query']['relation'] =  $relation;

        
       

        if(!empty($tags)) {
            $tags         = is_array( $tags ) ? $tags : array_filter( array_map( 'trim', explode( ',', $tags ) ) );
            $args['tag__in'] = $tags;
        }
       
        
        $i = 0;

        $wp_query = new WP_Query( $args );
        if(!class_exists('Listeo_Core_Template_Loader')) {
            return;
        }
        $template_loader = new Listeo_Core_Template_Loader;

        ob_start();
        if($fullwidth) { ?>
        <!-- Carousel / Start -->
        <div class="simple-fw-slick-carousel dots-nav" <?php if($autoplay == 'on') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $autoplayspeed; ?>}' <?php } ?> >
        <?php } else { ?>
        <!-- Carousel / Start -->
        <div class="simple-slick-carousel dots-nav" <?php if($autoplay == 'on') { ?> data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $autoplayspeed; ?>}' <?php } ?>>
        <?php } 
            if ( $wp_query->have_posts() ) {
               
                    while ( $wp_query->have_posts() ) : $wp_query->the_post(); ?>
                    <div class="fw-carousel-item">
                    
                        <?php
                        if($style=="style-1") { 
                            $template_loader->get_template_part( 'content-listing-compact' );  
                        } else {
                            $template_loader->get_template_part( 'content-listing-grid' );  
                        }
                        ?>

                    </div>
                  <?php endwhile; // end of the loop. 
            } ?>
        </div>
        <?php wp_reset_postdata();
        wp_reset_query();

        return ob_get_clean();
    }
