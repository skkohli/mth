<?php


function listeo_pricing_tables_wc($atts, $content)
{
    extract(shortcode_atts(array(
        "orderby" => "price",
        "custom_url" => "",
        "from_vs" => 'no'

    ), $atts));
    ob_start();
    global $wp_query;

    $args = array(
        'post_type'  => 'product',
        'limit'      => -1,
        'tax_query'  => array(
            array(
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => array('listing_package', 'listing_package_subscription')
            )
        )
    );
    switch ($orderby) {
        case 'price':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            $args['order'] = 'asc';
            break;

        case 'price-desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            $args['order'] = 'desc';
            break;

        case 'rating':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_wc_average_rating';
            $args['order'] = 'desc';
            break;

        case 'popularity':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'total_sales';
            $args['order'] = 'desc';
            break;

        case 'random':
            $args['orderby'] = 'rand';
            $args['order'] = '';
            $args['meta_key'] = '';
            break;
        case 'title':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            $args['meta_key'] = '';
            break;
    }

    $products = new WP_Query($args);
?>
    <div class="pricing-container margin-top-30">

        <?php
        while ($products->have_posts()) : $products->the_post();


            $product = wc_get_product(get_post()->ID);

            if (!$product->is_type(array('listing_package', 'listing_package_subscription')) || !$product->is_purchasable()) {
                continue;
            }
        ?>
            <div class="plan <?php echo ($product->is_featured()) ? 'featured' : ''; ?>">
                <?php if ($product->is_featured()) : ?>
                    <div class="listing-badge">
                        <span class="featured"><?php esc_html_e('Featured', 'listeo-shortcodes') ?></span>
                    </div>
                <?php endif; ?>

                <div class="plan-price">

                    <h3><?php echo $product->get_title(); ?></h3>
                    <span class="value"> <?php echo $product->get_price_html(); ?></span>
                    <span class="period"><?php echo $product->get_short_description(); ?></span>
                </div>

                <div class="plan-features">
                    <ul class="plan-features-auto-wc">
                        <?php
                        $listingslimit = $product->get_limit();
                        if (!$listingslimit) {
                            echo "<li>";
                            esc_html_e('Unlimited number of listings', 'listeo-shortcodes');
                            echo "</li>";
                        } else { ?>
                            <li>
                                <?php esc_html_e('This plan includes ', 'listeo-shortcodes');
                                printf(_n('%d listing', '%s listings', $listingslimit, 'listeo-shortcodes') . ' ', $listingslimit); ?>
                            </li>
                        <?php }

                        $duration = $product->get_duration();
                        if ($duration > 0) : ?>
                            <li>
                                <?php esc_html_e('Listings are visible ', 'listeo-shortcodes');
                                printf(_n('for %s day', 'for %s days', $product->get_duration(), 'listeo-shortcodes'), $product->get_duration()); ?>
                            </li>
                        <?php else : ?>
                            <li>
                                <?php esc_html_e('Unlimited availability of listings', 'listeo-shortcodes');  ?>
                            </li>
                        <?php endif; ?>
                        <?php if (get_option('listeo_populate_listing_package_options')) : ?>
                            <?php
                            $bookingOptions = $product->has_listing_booking();
                            if ($bookingOptions) : ?>
                                <li>
                                    <?php esc_html_e('Booking Module enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>


                            <?php
                            $reviewsOptions = $product->has_listing_reviews();
                            if ($reviewsOptions) : ?>
                                <li>
                                    <?php esc_html_e('Reviews Module enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>

                            <?php
                            $sociallinksOptions = $product->has_listing_social_links();
                            if ($sociallinksOptions) : ?>
                                <li>
                                    <?php esc_html_e('Social Links Module enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>

                            <?php
                            $openinghoursOptions = $product->has_listing_opening_hours();
                            if ($openinghoursOptions) : ?>
                                <li>
                                    <?php esc_html_e('Opening Hours Module enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>

                            <?php
                            $vidosOptions = $product->has_listing_video();
                            if ($vidosOptions) : ?>
                                <li>
                                    <?php esc_html_e('Video option enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>

                            <?php
                            $couponsOptions = $product->has_listing_coupons();
                            if ($couponsOptions == 'yes') : ?>
                                <li>
                                    <?php esc_html_e('Coupons option enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>
                            <?php
                            $faqOptions = $product->has_listing_faq();
                            if ($faqOptions == 'yes') : ?>
                                <li>
                                    <?php esc_html_e('FAQ option enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>

                            <?php
                            $pricingMenuOptions = $product->has_listing_pricing_menu();
                            if ($pricingMenuOptions == 'yes') : ?>
                                <li>
                                    <?php esc_html_e('Pricing Menu Module enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>
                            <?php
                            $galleryOptions = $product->has_listing_gallery();
                            if ($galleryOptions == 'yes') : ?>
                                <li>
                                    <?php esc_html_e('Gallery Module enabled', 'listeo_core');  ?>
                                </li>
                            <?php endif; ?>
                            <?php
                            $gallery_limitOptions = $product->get_option_gallery_limit();
                            if ($gallery_limitOptions) : ?>
                                <li>
                                    <?php printf(esc_html__('Maximum  %s images in gallery', 'listeo_core'), $product->get_option_gallery_limit());  ?>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>

                    </ul>
                    <?php

                    echo $product->get_description();
                    $link   = $product->add_to_cart_url();
                    $label  = apply_filters('add_to_cart_text', esc_html__('Add Listing', 'listeo-shortcodes'));

                    ?>
                    <a href="<?php echo esc_url($link); ?>" class="button"><i class="fa fa-shopping-cart"></i> <?php echo esc_html($label); ?></a>


                </div>

            </div>

        <?php endwhile; ?>
    </div>
<?php $pricing__output =  ob_get_clean();
    wp_reset_postdata();
    return $pricing__output;
}

?>