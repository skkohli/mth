<?php 

	/**
	* Headline shortcode
	* Usage: [iconbox title="Service Title" url="#" icon="37"] test [/headline]
	*/
	function listeo_posts_carousel( $atts, $content ) {
 		extract(shortcode_atts(array(
            'limit'=>'6',
            'orderby'=> 'date',
            'order'=> 'DESC',
            'categories' => '',
            'exclude_posts' => '',
            'include_posts' => '',
            'ignore_sticky_posts' => 1,
            'limit_words' => 15,
            'from_vs' => 'no'
            ), $atts));

        $output = '';
        $randID = rand(1, 99); // Get unique ID for carousel

		$args = array(
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            );

        if(!empty($exclude_posts)) {
            $exl = is_array( $exclude_posts ) ? $exclude_posts : array_filter( array_map( 'trim', explode( ',', $exclude_posts ) ) );
            $args['post__not_in'] = $exl;
        }

        if(!empty($include_posts)) {
            $exl = is_array( $include_posts ) ? $include_posts : array_filter( array_map( 'trim', explode( ',', $include_posts ) ) );
            $args['post__in'] = $exl;
        }

        if($from_vs === 'yes'){
            if(!empty($categories)) {
                //$categories         = is_array( $categories ) ? $categories : array_filter( array_map( 'trim', explode( ',', $categories ) ) );
                $args['category_name'] = $categories;
            }
        } else {
            if(!empty($categories)) {
                
                $args['category_name'] = $categories;
            }

        }

        if(!empty($tags)) {
            $tags         = is_array( $tags ) ? $tags : array_filter( array_map( 'trim', explode( ',', $tags ) ) );
            $args['tag__in'] = $tags;
        }
        $i = 0;

        $wp_query = new WP_Query( $args );
      

		ob_start(); ?>
 <div class="listeo-post-grid-wrapper">
<div class="row">

		<?php if ( $wp_query->have_posts() ) {
			?>


				<?php while ( $wp_query->have_posts() ) : $wp_query->the_post();
				$i++;
                $id = $wp_query->post->ID;
                $thumb = get_post_thumbnail_id();
                $img_url = wp_get_attachment_url( $thumb,'listeo-blog-related-post');
                if($img_url){
                    $image = aq_resize( $img_url, 620, 450, true, false, true ); //resize & crop the image 
                }
                

                        ?>
			<div class="col-md-4">
                <a href="<?php the_permalink(); ?>" class="blog-compact-item-container">
                    <div class="blog-compact-item">
                        <?php 
                            the_post_thumbnail('listeo-blog-related-post'); 
                        ?>
                         <?php 
                            $categories_list = wp_get_post_categories($wp_query->post->ID);
                            $cats = array();

                            $output = '';
                            foreach($categories_list as $c){
                                $cat = get_category( $c );
                                $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug, 'url' => get_category_link($cat->cat_ID) );
                            }
                            $single_cat = array_shift( $cats );
                            echo '<span class="blog-item-tag">'.$single_cat['name'].'</span>';
                            
                        ?>
                        
                        <div class="blog-compact-item-content">
                            <ul class="blog-post-tags">
                                <li><?php $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
                    if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
                        $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
                    }

                    printf( $time_string,
                        esc_attr( get_the_date( 'c' ) ),
                        esc_html( get_the_date() ),
                        esc_attr( get_the_modified_date( 'c' ) ),
                        esc_html( get_the_modified_date() )
                    );

                    ?></li>
                            </ul>
                            <h3><?php the_title(); ?></h3>
                             <p><?php 
                                $excerpt = get_the_excerpt();
                                echo listeo_string_limit_words($excerpt,$limit_words); ?></p>
                        </div>
                    </div>
                </a>
            </div>


		
			<?php 
			 endwhile; // end of the loop. 
		} else {
			//do_action( "woocommerce_shortcode_{$loop_name}_loop_no_results" );
		}
        ?>
            </div>
        </div>
        <div class="col-md-12 centered-content">
                <a href="<?php echo get_permalink( get_option( 'page_for_posts' ) ); ?>" class="button border margin-top-10"><?php esc_html_e( 'View Blog', 'listeo-shortcodes'); ?></a>
            </div>
        <?php 
		wp_reset_postdata();

		return ob_get_clean();
	}

?>