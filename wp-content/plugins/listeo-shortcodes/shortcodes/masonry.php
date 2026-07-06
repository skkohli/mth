<?php 

	/**
	* Headline shortcode
	* Usage: [iconbox title="Service Title" url="#" icon="37"] test [/headline]
	*/
	function listeo_masonry( $atts, $content ) {
 		extract(shortcode_atts(array(
            'limit'=>'6',
            'orderby'=> 'date',
            'order'=> 'DESC',
            'categories' => 'listing_category',
            'exclude_posts' => '',
            'include_posts' => '',
            
            'per_page' => 10,
            'limit_words' => 15,
            'from_vs' => 'no'
            ), $atts));
            wp_enqueue_script( 'isotope-min' );
            $output = '';
            $randID = rand(1, 99); // Get unique ID for carousel
           
            $template_loader = new Listeo_Core_Template_Loader;

            $get_listings = array(
                    'posts_per_page'    => $per_page,
                    'orderby'           => $orderby,
                    'order'             => $order,
                   
                );
            
            $query_args = array(
                'post_type'              => 'listing',
                'post_status'            => 'publish',
                'ignore_sticky_posts'    => 10,
                'posts_per_page'         => intval( $per_page ),
                'orderby'           => $orderby,
                'order'             => $order,
                'tax_query'              => array(),
                'meta_query'             => array(),
            );
            if(!empty($categories)) {
                $categories = explode(",", $categories);
            }
            $listeo_core_query = new WP_Query( $query_args );
            ob_start();
            
            if ( $listeo_core_query->have_posts() ) {
                  ?>
                  <div class="row">
                        <div class="col-md-12 margin-bottom-30">

                             <?php echo ListeoShortcodes::get_filters($categories); ?>

                        </div>
                  </div>
                  <div class="row">
                        <!-- Projects -->
                        <div class="projects isotope-wrapper">
                          <?php 
                          $style_data = array(
                            
                           
                            );
                          while ( $listeo_core_query->have_posts() ) {
                              
                                // Setup listing data
                                $listeo_core_query->the_post(); 
                                  $term_list = wp_get_post_terms($listeo_core_query->post->ID, 'listing_category', array("fields" => "slugs"));?>
                                <div class="col-lg-4 col-md-6 isotope-item <?php  echo implode( " ",$term_list ); ?>">
                                <?php $template_loader->set_template_data( $style_data )->get_template_part( 'content-listing','compact' );  ?>
                                </div>
                            <?php } ?>
                        </div>
                  </div>     
            <?php } else {
                  //do_action( "woocommerce_shortcode_{$loop_name}_loop_no_results" );
            }
        ?>

        

        <?php 
            wp_reset_postdata();

            return ob_get_clean();
      }

?>