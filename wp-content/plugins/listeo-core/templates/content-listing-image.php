<?php 	

if(isset($data) && isset($data->size)){
	$size = $data->size;
} else {
	$size = 'listeo-listing-grid';
}

// First, try to get thumbnail URL using helper function (supports both WordPress + Google)
$thumbnail_url = '';

if (function_exists('lds_get_listing_gallery')) {
    $gallery_data = lds_get_listing_gallery($id);
    
    if (!empty($gallery_data)) {
        $first_photo = $gallery_data[0];
        if ($first_photo['source'] === 'google') {
            // Use Google photo URL directly
            $thumbnail_url = $first_photo['url'];
        } else {
            // Use WordPress attachment with proper sizing
            $thumbnail_url = wp_get_attachment_image_url($first_photo['id'], $size);
        }
    }
}


// If no thumbnail from gallery helper, try standard WordPress methods
if (!$thumbnail_url) {
    if(has_post_thumbnail()){ 
        $thumbnail_url = get_the_post_thumbnail_url($id, $size);
    } else { 
        // Try standard gallery
        $gallery = (array) get_post_meta($id, '_gallery', true);
        $ids = array_keys($gallery);
        if(!empty($ids[0]) && $ids[0] !== 0){ 
            $thumbnail_url = wp_get_attachment_image_url($ids[0], $size); 
        }
    }
}

// If still no thumbnail, try listing logo as fallback
if (!$thumbnail_url) {
    $listing_logo = get_post_meta($id, '_listing_logo', true);
    if (!empty($listing_logo)) {
        // Listing logo is stored as URL, not attachment ID
        $thumbnail_url = $listing_logo;
    }
}

// Display the image
if ($thumbnail_url) {
    echo '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr(get_the_title($id)) . '" class="listing-thumbnail">';
} else {
    // Final fallback - placeholder or category image
    $image_url = get_listeo_core_placeholder_image();
    
    if(empty($image_url)){
        // check if this post is assigned to a category, if it is, take first category and see if it has Category Image, if it doesn't check next one 
        $terms = get_the_terms($id, 'listing_category');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $term_meta = get_term_meta($term->term_id);
                if (isset($term_meta['_cover']) && !empty($term_meta['_cover'][0])) {
                    $image_url = wp_get_attachment_image_url($term_meta['_cover'][0], $size);
                    break;
                }
            }
        }
    }

    $image_url = apply_filters('listeo_category_image_fallback', $image_url);
    ?>
    <img src="<?php echo esc_attr($image_url); ?>" alt="<?php echo esc_attr(get_the_title($id)); ?>">
    <?php
}