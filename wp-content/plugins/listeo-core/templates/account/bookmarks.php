<?php 
$ids = '';
if(isset($data)) :
	$ids	 	= (isset($data->ids)) ? $data->ids : '' ;

endif; 
$no_bookmarks = array();

?> 

<?php if(!empty($ids)) : ?>
<div class="dashboard-list-box margin-top-0">

<h4><?php esc_html_e('Bookmarked Listings','listeo_core'); ?></h4>
	<ul>
	<?php
	$nonce = wp_create_nonce("listeo_core_remove_fav_nonce");
	foreach ($ids as $listing_id) {
		if(!is_numeric($listing_id)) continue;
		if ( get_post_status( $listing_id ) !== 'publish' ) {
			$no_bookmarks[$listing_id] = true;
			continue;
			
		} 
		$listing = get_post($listing_id);
		$no_bookmarks[$listing_id] = false; ?>
		<li>
			<div class="list-box-listing">
				<div class="list-box-listing-img">
						<a href="<?php echo get_permalink( $listing ) ?>"><?php 
						if(has_post_thumbnail($listing_id)){ 
							echo get_the_post_thumbnail($listing_id,'listeo_core-preview');
						} else {
							$gallery = (array) get_post_meta( $listing_id, '_gallery', true );

							$ids = array_keys($gallery);
							if(!empty($ids[0]) && $ids[0] !== 0){ 
								$image_url = wp_get_attachment_image_url($ids[0],'listeo_core-preview'); 
							} else {
								$image_url = get_listeo_core_placeholder_image();
							}
							?>
							<img src="<?php echo esc_attr($image_url); ?>" alt="">
						<?php } ?></a>
					</div>
				<div class="list-box-listing-content">
					<div class="inner">
						<h3><a href="<?php echo get_permalink( $listing ) ?>"><?php echo get_the_title( $listing );?></a></h3>
						<span><?php the_listing_address($listing); ?></span>
						
						<?php 
						// Use the new combined rating display function
						$rating_data = listeo_get_rating_display($listing_id);
						$rating = $rating_data['rating'];
						$number = $rating_data['count'];
						
						if(isset($rating) && $rating > 0 ) : 
						$rating_type = get_option('listeo_rating_type','star');
						if($rating_type == 'numerical') { ?>
	                        <div class="numerical-rating" data-rating="<?php $rating = str_replace(',', '.', $rating); $rating_value = esc_attr(round($rating,1)); printf("%0.1f",$rating_value); ?>">
	                    <?php } else { ?>
	                        <div class="star-rating" data-rating="<?php echo $rating; ?>">
	                    <?php } ?>
							<div class="rating-counter">(<?php printf( _n( '%s review', '%s reviews', $number,'listeo_core'), number_format_i18n( $number ) );  ?>)</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="buttons-to-right">
				<a href="#" class="listeo_core-unbookmark-it delete button gray" data-post_id="<?php echo esc_attr($listing_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><i class="sl sl-icon-close"></i> <?php esc_html_e('Remove','listeo_core');?></a>
			</div>
		</li>
	<?php } ?>
		

	</ul>
</div>

<?php else: ?>
	<div class="notification notice ">
		<p><span><?php esc_html_e('No bookmarks!','listeo_core'); ?></span> <?php esc_html_e('You haven\'t saved anything yet!','listeo_core'); ?></p>
		
	</div>
<?php endif;
?>

<?php 

$number_of_bookmarks = count($no_bookmarks);

//all have to be tru to show 
$i = 0;

foreach ($no_bookmarks as $key => $value) {

	if($value==true){
		$i++;
	}

}
if($number_of_bookmarks == $i) : ?>
	<div class="notification notice ">
		<p><span><?php esc_html_e('You don\'t have any bookmarks yet.','listeo_core'); ?></span></p>
				
	</div>
<?php endif; ?>
