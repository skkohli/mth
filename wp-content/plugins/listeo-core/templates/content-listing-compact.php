<?php
$show_as_ad = false;
if (isset($data)) :
    $style        = (isset($data->style)) ? $data->style : '';
    $grid_columns        = (isset($data->grid_columns)) ? $data->grid_columns : '';
    $show_as_ad = isset($data->ad) ? $data->ad : '';
    if ($show_as_ad) {
        $ad_type = get_post_meta($post->ID, 'ad_type', true);
        $ad_id = get_post_meta($post->ID, 'ad_id', true);
    }
endif;
$style = 'compact';


$template_loader = new Listeo_Core_Template_Loader;
$listing_type = get_post_meta($post->ID, '_listing_type', true);
$is_featured = listeo_core_is_featured($post->ID);  ?>
<!-- Listing Item -->
<?php if (isset($style) && $style == 'compact') {
    if (!isset($grid_columns)) {
        $grid_columns = 2;
    }
    if ($grid_columns == 3) { ?>
        <div class="col-lg-4 col-md-6">
        <?php } else { ?>
            <div class="col-lg-6 col-md-12">
        <?php }
} ?>
        <a href="<?php the_permalink(); ?>" <?php if ($show_as_ad) : ?> data-ad-id="<?php echo $ad_id; ?>" data-campaign-type="<?php echo $ad_type; ?>" <?php endif; ?> class="listing-item-container listing-geo-data compact" <?php echo listeo_get_geo_data($post); ?>>
            <div class="listing-item  <?php if ($is_featured) { ?>featured-listing<?php } ?>">
                <div class="listing-small-badges-container">
                    <?php if ($is_featured) { ?>
                        <div class="listing-small-badge featured-badge"><i class="fa fa-star"></i> <?php esc_html_e('Featured', 'listeo_core'); ?></div>
                    <?php } ?>
                    <?php if (get_the_listing_price_range()) : ?>
                        <div class="listing-small-badge pricing-badge"><i class="fa fa-<?php echo esc_attr(get_option('listeo_price_filter_icon', 'tag')); ?>"></i><?php echo get_the_listing_price_range(); ?></div>
                    <?php endif; ?>
                    <?php
                    $vendor_id = get_post_field('post_author', $post->ID);
                    $is_vendor = get_user_meta($vendor_id, 'dokan_enable_selling', true);

                    // Get the WP_User object (the vendor) from author ID
                    $_store_widget_status = get_post_meta($post->ID, '_store_widget_status', true);

                    if ($is_vendor == "yes" && $_store_widget_status) {
                    ?>
                        <div class="listing-small-badge shop-badge"><i class="fa fa-store"></i></i> <?php esc_html_e('Store', 'listeo_core'); ?></div>
                    <?php
                    }
                    ?>
                    
                    <?php
                    // Display distance badge for nearby listings
                    if (isset($GLOBALS['listeo_current_distance']) && !is_null($GLOBALS['listeo_current_distance'])) {
                        $distance = $GLOBALS['listeo_current_distance'];
                        $unit = isset($GLOBALS['listeo_distance_unit']) ? $GLOBALS['listeo_distance_unit'] : 'km';
                        $formatted_distance = listeo_format_distance($distance, $unit);
                    ?>
                        <div class="listing-small-badge distance-badge"><i class="fa fa-location-arrow"></i> <?php echo esc_html($formatted_distance); ?></div>
                    <?php } ?>
                    
                    <?php
                    
                        if ($listing_type == 'classifieds') {
                            $price = get_post_meta($post->ID, '_classifieds_price', true);
                            $currency_abbr = get_option('listeo_currency');
                            $currency_postion = get_option('listeo_currency_postion');
                            $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
                            if ($price) { ?>
                                <div class="listing-small-badge pricing-badge classifieds-pricing-badge"><i class="fa fa-<?php echo esc_attr(get_option('listeo_price_filter_icon', 'tag')); ?>"></i><?php if ($currency_postion == "before") {
                                                                                                                                                                                                            echo $currency_symbol;
                                                                                                                                                                                                        }
                                                                                                                                                                                                        if (is_numeric($price)) {
                                                                                                                                                                                                            $decimals = get_option('listeo_number_decimals', 2);
                                                                                                                                                                                                            echo number_format($price, $decimals);
                                                                                                                                                                                                        } else {
                                                                                                                                                                                                            echo $price;
                                                                                                                                                                                                        }
                                                                                                                                                                                                        if ($currency_postion == "after") {
                                                                                                                                                                                                            echo $currency_symbol;
                                                                                                                                                                                                        } ?></div>
                    <?php }
                        }
                 ?>
                    <?php
                    if (listeo_core_listing_type_supports($listing_type, 'event_date')) {

                        $_event_datetime = get_post_meta($post->ID, '_event_date', true); // mm/dd/yy
                        if ($_event_datetime) {
                            $_event_date = list($_event_datetime) = explode(' ', $_event_datetime);

                            if ($_event_date) :
                                $date_format = get_option('date_format');
                                
                                // Improved date parsing with error handling
                                try {
                                    $php_format = listeo_date_time_wp_format_php();
                                    $date_obj = DateTime::createFromFormat($php_format, $_event_date[0]);
                                    
                                    if ($date_obj === false) {
                                        // Fallback: try with strtotime if DateTime::createFromFormat fails
                                        $meta_value_stamp = strtotime($_event_date[0]);
                                    } else {
                                        $meta_value_stamp = $date_obj->getTimestamp();
                                    }
                                    
                                    $meta_value = date_i18n(get_option('date_format'), $meta_value_stamp);
                                    
                                } catch (Exception $e) {
                                    // Final fallback: display raw value if all parsing fails
                                    $meta_value = $_event_date[0];
                                } ?>
                                <div class="listing-small-badge"><i class="fa fa-calendar-check"></i><?php echo esc_html($meta_value); ?></div>
                    <?php endif;
                        }
                    }  ?>

                </div>
                <?php
                $template_loader->get_template_part('content-listing-image');  ?>

                <?php
                if (get_post_meta($post->ID, '_opening_hours_status', true)) {
                    if (listeo_check_if_open()) { ?>
                        <div class="listing-badge now-open"><?php esc_html_e('Now Open', 'listeo_core'); ?></div>
                        <?php } else {
                        if (listeo_check_if_has_hours()) { ?>
                            <div class="listing-badge now-closed"><?php esc_html_e('Now Closed', 'listeo_core'); ?></div>
                        <?php } ?>
                <?php }
                }
                ?>


                <div class="listing-item-content">
                    <?php
                    if (!get_option('listeo_disable_reviews')) {
                        // Use the new combined rating display function
                        $rating_data = listeo_get_rating_display($post->ID);
                        $rating = $rating_data['rating'];
                        
                        if (isset($rating) && $rating > 0) : ?>
                            <div class="numerical-rating" data-rating="<?php $rating = str_replace(',', '.', $rating);
                                                                        $rating_value = esc_attr(round((float)$rating, 1));
                                                                        printf("%0.1f", $rating_value); ?>"></div>
                    <?php endif;
                    } ?>

                    <h3><?php if ($show_as_ad): ?><div class="listeo-ad-badge tip" data-tip-content="<?php echo esc_html_e('This is paid advertisment','listeo_core'); ?>"><?php esc_html_e('Sponsored', 'listeo_core'); ?></div><br><?php endif; ?> <?php the_title(); ?> <?php if (listeo_core_is_verified($post->ID)) : ?><i class="verified-icon"></i><?php endif; ?></h3>
                    <?php if (get_the_listing_address()) { ?><span><?php the_listing_address(); ?></span><?php } ?>

                </div>
                <?php
                if (listeo_core_check_if_bookmarked($post->ID)) {
                    $nonce = wp_create_nonce("listeo_core_bookmark_this_nonce"); ?>
                    <span class="like-icon listeo_core-unbookmark-it liked" data-post_id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"></span>
                    <?php } else {
                    if (is_user_logged_in()) {
                        $nonce = wp_create_nonce("listeo_core_remove_fav_nonce"); ?>
                        <span class="save listeo_core-bookmark-it like-icon" data-post_id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"></span>
                    <?php } else { ?>
                        <span class="save like-icon tooltip left" title="<?php esc_html_e('Login To Bookmark Items', 'listeo_core'); ?>"></span>
                    <?php } ?>
                <?php } ?>
            </div>
        </a>
        <?php if (isset($style) && $style == 'compact') { ?>
            </div>
        <?php } ?>


        <!-- Listing Item / End -->