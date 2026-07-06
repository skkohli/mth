<?php

global $post;
global $wpdb; 	


$place_id = get_post_meta($post->ID,'_place_id', true);

if(!empty($place_id)){
	$place_data = listeo_get_google_reviews($post);

	if(empty($place_data['result']['reviews'])){
		return;
	} else {
		$reviews = $place_data['result']['reviews'];	
	}
	

?>

<div id="listing-google-reviews" class="listing-section">
	<h2 class="listing-desc-headline margin-top-75 margin-bottom-20"><?php esc_html_e('Google Reviews','listeo_core'); ?></h2>
	
    <?php
	if(isset($reviews) && !empty($reviews)){?>
	
	<div class="google-reviews-summary">
	    <div class="google-reviews-summary-logo"></div>
	    <div class="google-reviews-summary-avg">
	        <strong><?php echo number_format_i18n($place_data['result']['rating'],1); ?></strong>
	        <div class="star-rating" data-rating="<?php echo $place_data['result']['rating']; ?>"></div>
	        <span>
<?php
		$google_reviews_count = $place_data['result']['user_ratings_total'];
		printf( // WPCS: XSS OK.
			esc_html( _nx(  'Review %1$s','%1$s reviews', $google_reviews_count, 'comments title', 'listeo_core' ) ),
			 number_format_i18n(  $google_reviews_count )
		);
	?>
	        </span>
	    </div>
	    <div class="google-reviews-read-more">
	        <a href="https://search.google.com/local/writereview?placeid=<?php echo $place_id; ?>" target="_blank"><img src="<?php echo get_template_directory_uri(); ?>/images/google-reviews-button-icon.svg" alt=""><?php esc_html_e('Add Review','listeo_core'); ?></a>
	    </div>
	    
	</div>
	
    <section class="comments listing-reviews">
    	<ul class="comment-list">
    		<?php  foreach ($reviews as $key => $review) { ?>
    			<li>
    
                   	<div class="avatar"><img src="<?php echo esc_attr($review['profile_photo_url'] ?? ''); ?>" alt="<?php echo $review['author_name'];  ?>"></div>
            		<div class="comment-content"><div class="arrow-comment"></div>
            		
            			<div class="comment-by">

            				<h5>
            					<?php if (!empty($review['author_url'])) : ?>
            						<a href="<?php echo esc_url($review['author_url']); ?>" target="_blank"><?php echo $review['author_name'];  ?></a>
            					<?php else : ?>
            						<?php echo $review['author_name'];  ?>
            					<?php endif; ?>
            				</h5> 
            		        <span class="date"><?php echo esc_attr($review['relative_time_description'] ?? ''); ?></span>
    			        	<div class="star-rating" data-rating="<?php echo esc_attr($review['rating'] ?? 0); ?>"></div>
            			</div>
            			<p>	<?php echo $review['text'] ?? ''; ?></p>
                    </div>
    
    			</li>
     		<?php } ?>
    	</ul>
    <?php }
      ?>
      
    </section>
	    <div class="google-reviews-read-more bottom">
	        <a href="https://search.google.com/local/reviews?placeid=<?php echo $place_id ?>" target="_blank"><img src="<?php echo get_template_directory_uri(); ?>/images/google-reviews-logo.svg" alt=""><?php esc_html_e('Read More Reviews','listeo_core'); ?></a>
	    </div>
	  </div>
<?php } ?>
