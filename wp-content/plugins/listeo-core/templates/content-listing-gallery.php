<?php
if (isset($data) && isset($data->size)) {
    $size = $data->size;
} else {
    $size = 'listeo-listing-grid';
}

// Use helper function if available
if (function_exists('lds_get_listing_gallery')) {
    $gallery_data = lds_get_listing_gallery($id);

    if (!empty($gallery_data)) {
        // Check if there's a featured image (_thumbnail_id) that should be first
        $thumbnail_id = get_post_thumbnail_id($id);

        if ($thumbnail_id) {
            // Remove thumbnail from gallery if it exists to avoid duplicates
            $gallery_data = array_filter($gallery_data, function($photo) use ($thumbnail_id) {
                return !($photo['source'] === 'wordpress' && $photo['id'] == $thumbnail_id);
            });

            // Add thumbnail as first item
            array_unshift($gallery_data, [
                'url' => wp_get_attachment_image_url($thumbnail_id, $size),
                'id' => $thumbnail_id,
                'source' => 'wordpress',
                'attribution' => ''
            ]);
        }

        // Limit to 3 images
        $slider_option = get_option('listeo_listings_gallery_slider', 'yes');
        $limit = ($slider_option == 'disable') ? 1 : 3;
        $gallery_data = array_slice($gallery_data, 0, $limit);

        $listing_title = get_the_title($id);
        foreach ($gallery_data as $photo) {
            $alt = $listing_title;
            if (!empty($photo['id'])) {
                $att_alt = get_post_meta($photo['id'], '_wp_attachment_image_alt', true);
                if (!empty($att_alt)) {
                    $alt = $att_alt;
                }
            }
            if ($photo['source'] === 'google') {
                echo '<img src="' . esc_url($photo['url']) . '" alt="' . esc_attr($alt) . '" class="slider-image-nl">';
            } else {
                $image_url = wp_get_attachment_image_url($photo['id'], $size);
                if (!empty($image_url)) {
                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" class="slider-image-nl">';
                }
            }
        }
        return;
    }
}

// Original fallback code for compatibility
$gallery = (array) get_post_meta($id, '_gallery', true);

$ids = array_keys($gallery);

// Check if there's a featured image (_thumbnail_id) that should be first
$thumbnail_id = get_post_thumbnail_id($id);

if ($thumbnail_id) {
	// Remove thumbnail from gallery if it exists to avoid duplicates
	$ids = array_filter($ids, function($attachment_id) use ($thumbnail_id) {
		return $attachment_id != $thumbnail_id;
	});

	// Add thumbnail as first item
	array_unshift($ids, $thumbnail_id);
}

$slider_option = get_option('listeo_listings_gallery_slider', 'yes');
if($slider_option == 'disable'){
	$ids = array_slice($ids, 0, 1);
} else {
	$ids = array_slice($ids, 0, 3);
}
// Limit to 3 images


$listing_title_fallback = get_the_title($id);
if (!empty($ids) && $ids[0] !== 0) {
	foreach ($ids as $attachment_id) {
		if (!empty($attachment_id) && $attachment_id !== 0) {
			$image_url = wp_get_attachment_image_url($attachment_id, $size);
			if (!empty($image_url)) {
				$att_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
				$alt = !empty($att_alt) ? $att_alt : $listing_title_fallback;
?>
				<img src="<?php echo esc_attr($image_url); ?>" alt="<?php echo esc_attr($alt); ?>" class="slider-image-nl">
		<?php
			}
		}
	}
} else {
	// fallback - no gallery
	if (has_post_thumbnail()) {
		$image_url = get_the_post_thumbnail_url(get_the_ID(), $size);
	} else {
		$image_url = get_listeo_core_placeholder_image();
	}

	if (!empty($image_url)) {
		?>
		<img src="<?php echo esc_attr($image_url); ?>" alt="<?php echo esc_attr($listing_title_fallback); ?>" class="slider-image-nl">
<?php
	}
}
?>