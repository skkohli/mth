<?php
// Nearby Listings Section (Separate from Related Listings)
$template_loader = new Listeo_Core_Template_Loader;
$nearby_enabled = get_option('listeo_nearby_listings_status');

if (!$nearby_enabled) {
    return; // Exit if nearby listings feature is disabled
}

$radius = get_option('listeo_nearby_listings_radius', 50);
$unit = get_option('listeo_nearby_listings_unit', 'km');
$style = get_option('listeo_nearby_listings_grid_style', 'compact');
$taxonomy = get_option('listeo_nearby_listings_taxonomy', 'all');
$limit = get_option('listeo_nearby_listings_limit', 6);

// Prepare additional query args based on taxonomy filter
$additional_args = array();

// Add taxonomy filtering if not set to "all"
if ($taxonomy === 'listing_type') {
    // Filter by the same listing type (meta field)
    $current_listing_type = get_post_meta($post->ID, '_listing_type', true);
    if ($current_listing_type) {
        $additional_args['meta_query'] = array(
            array(
                'key' => '_listing_type',
                'value' => $current_listing_type,
                'compare' => '='
            )
        );
    }
} elseif ($taxonomy === 'listing_category+region') {
    // Category + Region combination filtering
    // Get the listing type to determine correct category taxonomy
    $current_listing_type = get_post_meta($post->ID, '_listing_type', true);
    $category_taxonomy = 'listing_category'; // Default fallback

    // Use listing-type-specific category taxonomy if available
    if ($current_listing_type && function_exists('listeo_core_get_taxonomy_for_listing_type')) {
        $category_taxonomy = listeo_core_get_taxonomy_for_listing_type($current_listing_type);
    }

    $category_terms = get_the_terms($post->ID, $category_taxonomy, 'string');
    $region_terms = get_the_terms($post->ID, 'region', 'string');

    if ($category_terms && $region_terms) {
        $category_term_ids = wp_list_pluck($category_terms, 'term_id');
        $region_term_ids = wp_list_pluck($region_terms, 'term_id');

        $additional_args['tax_query'] = array(
            'relation' => 'AND',
            array(
                'taxonomy' => $category_taxonomy,
                'field' => 'id',
                'terms' => $category_term_ids,
                'operator' => 'IN'
            ),
            array(
                'taxonomy' => 'region',
                'field' => 'id',
                'terms' => $region_term_ids,
                'operator' => 'IN'
            )
        );
    }
} elseif ($taxonomy !== 'all') {
    // Original taxonomy-based filtering
    $terms = get_the_terms($post->ID, $taxonomy, 'string');
    if ($terms) {
        $term_ids = wp_list_pluck($terms, 'term_id');
        $additional_args['tax_query'] = array(
            array(
                'taxonomy' => $taxonomy,
                'field' => 'id',
                'terms' => $term_ids,
                'operator' => 'IN'
            )
        );
    }
}

// Set posts per page limit (use larger number for query, we'll limit display later)
$query_limit = ($limit > 0) ? min($limit * 3, 50) : 20; // Query more than needed to account for distance filtering
$additional_args['posts_per_page'] = $query_limit;

// Get nearby listings using the cached function
if (function_exists('listeo_get_cached_nearby_listings')) {
    $nearby_listings = listeo_get_cached_nearby_listings($post->ID, $radius, $unit, $additional_args);
} else {
    $nearby_listings = array(); // Function not found, show empty array
}

// Apply limit to final results if specified
if ($limit > 0 && count($nearby_listings) > $limit) {
    $nearby_listings = array_slice($nearby_listings, 0, $limit);
}

// Display nearby listings if we have any
if (!empty($nearby_listings)) { ?>
  <h3 class="desc-headline no-border margin-bottom-35 margin-top-60 print-no">
    <?php esc_html_e('Nearby Listings', 'listeo_core'); ?>
  </h3>
  <div class="simple-slick-carousel" data-slick='{"autoplay": true,"slidesToShow": 2}'>
    <?php
    foreach ($nearby_listings as $nearby_item) {
      $nearby_post = $nearby_item['post'];
      $distance = $nearby_item['distance'];
      
      // Set up global $post for template
      $GLOBALS['post'] = $nearby_post;
      setup_postdata($nearby_post);
      
      // Store distance in global variable for template access
      $GLOBALS['listeo_current_distance'] = $distance;
      $GLOBALS['listeo_distance_unit'] = $unit;
      ?>
      <div class="fw-carousel-item">
        <?php $template_loader->get_template_part('content-listing-' . $style); ?>
      </div>
      <?php
    }
    wp_reset_postdata();
    wp_reset_query();
    // Clear distance globals
    unset($GLOBALS['listeo_current_distance']);
    unset($GLOBALS['listeo_distance_unit']);
    ?>
  </div>
<?php }
?>