<?php 
$video = get_post_meta( $post->ID, '_video', true ); 

if($video) :
$videos =  preg_split('/\r\n|\r|\n/',  $video);  
?> 
<!-- Video -->

<div id="listing-video" class="listing-section">
	<h2 class="listing-desc-headline margin-top-60 margin-bottom-30"><?php esc_html_e('Video','listeo_core'); ?></h2>
	

		<?php 
		foreach ($videos as $key => $vid) {
			echo '<div class="responsive-iframe">';
			echo wp_oembed_get( $vid ); 
			echo '</div>';
		}
		?>

</div>
<?php endif; ?>