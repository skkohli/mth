<!-- Content
================================================== -->
<?php $gallery = get_post_meta( $post->ID, '_gallery', true );

if(!empty($gallery)) : ?>
<!-- Slider -->
<div id="listing-gallery" class="listing-section">
	<h2 class="listing-desc-headline margin-top-70"><?php esc_html_e('Gallery','listeo_core'); ?></h2>
	<div class="listing-slider-small mfp-gallery-container margin-bottom-0">
	<?php

		foreach ( (array) $gallery as $attachment_id => $attachment_url ) {
			$image = wp_get_attachment_image_src( $attachment_id, 'listeo-gallery' );
			if($image) {
				$attachment_post = get_post( $attachment_id );
				$caption = $attachment_post ? esc_attr( $attachment_post->post_excerpt ) : '';
				echo '<a href="'.esc_url($image[0]).'" data-background-image="'.esc_attr($image[0]).'" class="item mfp-gallery" title="'.esc_attr($caption).'"></a>';
			}
		}

	?>
	</div>
</div>

<?php endif; ?>
<!-- Slider -->
		