<?php 
$listings = '';
if(isset($data)) :
	$listings	 	= (isset($data->listings)) ? $data->listings : '' ;
	$listings_top	 	= (isset($data->listings_top)) ? $data->listings_top : '' ;
endif; 

?>

<div class="nothing-compares-2u" <?php if(!empty($listings)){ ?> style="display:none;" <?php } ?>>
	<section id="listings-not-found" class="margin-bottom-50">
		<h2><?php esc_html_e('No listings to compare','listeo_core'); ?> </h2>
		<p><?php _e( 'Looks like you haven\'t add anything to compare yet!', 'listeo_core' ); ?></p>
	</section>
	<?php 
	$template_loader = new Listeo_Core_Template_Loader;
	  $wp_query = new WP_Query( array(
	      'post_type' => 'listing',
	      'posts_per_page' => 6,
	      'ignore_sticky_posts' => 1,
	      'orderby' => 'rand',
	   ) );

    if($wp_query->have_posts()) { ?>
	    <h3 class="desc-headline no-border margin-bottom-35 margin-top-60 print-no"><?php esc_html_e('Check Other Properties','listeo_core'); ?></h3>
	     <div class="carousel">
			<?php 
	        if ( $wp_query->have_posts() ) {
			        while ( $wp_query->have_posts() ) : $wp_query->the_post(); ?>
	                <div class="carousel-item">
		               <?php
	                        $template_loader->get_template_part( 'content-listing' );  
	                    ?>
			        </div>
			 <?php endwhile; // end of the loop. 
			} else {
				
			} ?>
	        </div>
			<?php wp_reset_postdata();
	        wp_reset_query();
	     }
	?>
</div>
	
<?php if(!empty($listings)){?>

<div class="compare-list-container">
	<ul id="compare-list">
		<li class="compare-list-listings">
			<div class="blank-div"></div>
			<?php  
			$nonce = wp_create_nonce("listeo_core_uncompare_this_nonce");

			foreach ($listings_top as $id => $value) {
				if($id == 0) continue;


					?>
						<div>
							<a href="<?php echo esc_attr($value['url']); ?>">
								<div class="clp-img">
									<?php if($value['image']) { ?>
										<img src="<?php echo esc_attr($value['image']); ?>" alt="">
									<?php } else { ?>
										<img src="<?php echo get_listeo_core_placeholder_image(); ?>" alt="">
									<?php } ?>
									<span data-post_id="<?php echo esc_attr($value['id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" class="remove-from-compare"><i class="fa fa-close"></i></span>
								</div>

								<div class="clp-title">
									<h4><?php echo esc_attr($value['title']); ?></h4>
									<span><?php echo esc_attr($value['price']); ?></span>
								</div>
							</a>
						</div>

					<?php 
				
			}?>
			
		</li>
		<?php 
		 foreach ($listings as $id => $value) {
		 	if( in_array($id,array('title','url','image','price'))) {
		 		continue;
		 	}
		 	if(count(array_filter($value)) == 1){
		 		continue;
		 	};
		 	echo '<li>';
		 	foreach ($value as $key => $_value) {?>
		 		<div><?php echo (!empty($_value)) ?  $_value : '<span class="fa fa-minus"></span>';?></div>
			<?php }
			echo '</li>';
		 	}
		?>

	</ul>
</div>
<?php } ?>

<!-- Compare List / End -->