<?php
//Get related listings based on taxonomy or listing type
$template_loader = new Listeo_Core_Template_Loader;
$taxonomy = get_option('listeo_single_related_taxonomy');

// Check if we should filter by listing type instead of taxonomy
if ($taxonomy === 'listing_type') {
    // Filter by the same listing type (meta field)
    $current_listing_type = get_post_meta($post->ID, '_listing_type', true);

    if ($current_listing_type) {
        $args = array(
            'post_type' => 'listing',
            'meta_query' => array(
                array(
                    'key' => '_listing_type',
                    'value' => $current_listing_type,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 20,
            'ignore_sticky_posts' => 1,
            'orderby' => 'rand',
            'post__not_in' => array($post->ID)
        );
    } else {
        $args = false; // No listing type found
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

    $category_terms = get_the_terms($post->ID, $category_taxonomy);
    // Fallback to main listing_category if no terms in type-specific taxonomy
    if (!$category_terms && $category_taxonomy !== 'listing_category') {
        $category_taxonomy = 'listing_category';
        $category_terms = get_the_terms($post->ID, $category_taxonomy);
    }
    $region_terms = get_the_terms($post->ID, 'region');

    if ($category_terms && $region_terms) {
        $category_term_ids = wp_list_pluck($category_terms, 'term_id');
        $region_term_ids = wp_list_pluck($region_terms, 'term_id');

        $args = array(
            'post_type' => 'listing',
            'tax_query' => array(
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
            ),
            'posts_per_page' => 20,
            'ignore_sticky_posts' => 1,
            'orderby' => 'rand',
            'post__not_in' => array($post->ID)
        );
    } else {
        $args = false; // Missing category or region terms
    }
} else {
    // Original taxonomy-based filtering
    $terms = get_the_terms($post->ID, $taxonomy);
    if ($terms) {
        //Pluck out the IDs to get an array of IDS
        $term_ids = wp_list_pluck($terms, 'term_id');

        //Query posts with tax_query. Choose in 'IN' if want to query posts with any of the terms
        //Chose 'AND' if you want to query for posts with all terms
        $args = array(
            'post_type' => 'listing',
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'id',
                    'terms' => $term_ids,
                    'operator' => 'IN' //Or 'AND' or 'NOT IN'
                )
            ),
            'posts_per_page' => 20,
            'ignore_sticky_posts' => 1,
            'orderby' => 'rand',
            'post__not_in' => array($post->ID)
        );
    } else {
        $args = false; // No terms found
    }
}

if ($args) {
    // Add author filter if enabled
    if(get_option('listeo_single_related_current_author')){
        global $post;
        $args['author'] = $post->post_author;
    }

    $args['no_found_rows'] = true;
    $second_query = new WP_Query($args);
    //Loop through posts and display...
    if ($second_query->have_posts()) { ?>
    <h3 class="desc-headline no-border margin-bottom-35 margin-top-60 print-no"><?php esc_html_e('Similar Listings', 'listeo_core'); ?></h3>
    <div class="simple-slick-carousel "  data-slick='{"autoplay": true,"slidesToShow": 2}'>

      <?php
      while ($second_query->have_posts()) : $second_query->the_post();
      ?> <div class="fw-carousel-item">
          <?php
          $style= get_option('listeo_similar_grid_style','compact');
          $template_loader->get_template_part('content-listing-'.$style); ?>
        </div>
      <?php
      endwhile;
      wp_reset_postdata();
      wp_reset_query();
      ?>
    </div>
<?php }
}
?>