<?php
//Get array of terms
$template_loader = new Listeo_Core_Template_Loader;
$other_listings = get_post_meta($post->ID, '_my_listings');
$other_listings_section_status = get_post_meta($post->ID, '_my_listings_section_status');
$title = get_post_meta($post->ID, '_my_listings_title', true);
if(!$title){
    $title = esc_html__('My Other Listings', 'listeo_core');
}
if(!$other_listings_section_status){
    return;
}
if ($other_listings) :
  
    //Query posts with tax_query. Choose in 'IN' if want to query posts with any of the terms
    //Chose 'AND' if you want to query for posts with all terms
    $args = array(
        'post_type' => 'listing',
        'ignore_sticky_posts' => 1,
        'orderby' => 'rand',
        'post__not_in' => array($post->ID),
        'post__in' => $other_listings
    );

    if (get_option('listeo_single_related_current_author')) {
        global $post;
        $args['author'] = $post->post_author;
    }

    $args['no_found_rows'] = true;
    $second_query = new WP_Query($args);
    //Loop through posts and display...
    if ($second_query->have_posts()) { ?>
        <h3 class="desc-headline no-border margin-bottom-35 margin-top-60 print-no"><?php echo $title; ?></h3>
        <div class=" margin-bottom-35 simple-slick-carousel " data-slick='{"autoplay": true,"slidesToShow": 2}'>

            <?php
            while ($second_query->have_posts()) : $second_query->the_post();
            ?> <div class="fw-carousel-item">
                    <?php
                    $style = get_option('listeo_similar_grid_style', 'compact');
                    $template_loader->get_template_part('content-listing-' . $style); ?>
                </div>
            <?php
            endwhile;
            wp_reset_postdata();
            wp_reset_query();
            ?>
        </div>
<?php }
endif;

?>