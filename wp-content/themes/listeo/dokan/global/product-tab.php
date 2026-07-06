<?php

/**
 * Dokan Seller Single product tab Template
 *
 * @since 2.4
 *
 * @package dokan
 */
?>

<h2><?php esc_html_e('Vendor Information', 'dokan-lite'); ?></h2>
<div id="dokan-seller-listing-wrap" class="grid-view listeo-dokan-widget">
    <div class="seller-listing-content">
        <ul class="dokan-seller-wrap">
            <?php

            $vendor            = dokan()->vendor->get($author->ID);
            $store_banner_id   = $vendor->get_banner_id();
            $store_name        = $vendor->get_shop_name();
            $store_url         = $vendor->get_shop_url();
            $store_rating      = $vendor->get_rating();
            $is_store_featured = $vendor->is_featured();
            $store_phone       = $vendor->get_phone();
            $store_info        = dokan_get_store_info($author->ID);
            $store_address     = dokan_get_seller_short_address($author->ID);
            $store_banner_url  = $store_banner_id ? wp_get_attachment_image_src($store_banner_id, 'full') : DOKAN_PLUGIN_ASSEST . '/images/default-store-banner.png';

            $show_store_open_close    = dokan_get_option('store_open_close', 'dokan_appearance', 'on');
            $dokan_store_time_enabled = isset($store_info['dokan_store_time_enabled']) ? $store_info['dokan_store_time_enabled'] : '';
            $store_open_is_on = ('on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled && !$is_store_featured) ? 'store_open_is_on' : '';
            ?>

            <li class="dokan-single-seller woocommerce coloum-<?php echo esc_attr($per_row); ?> <?php echo (!$store_banner_id) ? 'no-banner-img' : ''; ?>">
                <a href="<?php echo esc_url($store_url); ?>">
                    <div class="store-wrapper">
                        <div class="store-header">
                            <div class="store-banner">

                                <img src="<?php echo is_array($store_banner_url) ? esc_attr($store_banner_url[0]) : esc_attr($store_banner_url); ?>">

                            </div>
                        </div>

                        <div class="store-content <?php echo !$store_banner_id ? esc_attr('default-store-banner') : '' ?>">
                            <div class="store-data-container">
                                <div class="featured-favourite">
                                    <?php if ($is_store_featured) : ?>
                                        <div class="featured-label"><?php esc_html_e('Featured', 'dokan-lite'); ?></div>
                                    <?php endif ?>

                                    <?php do_action('dokan_seller_listing_after_featured', $author, $store_info); ?>
                                </div>

                                <?php if ('on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled) : ?>
                                    <?php if (dokan_is_store_open($author->ID)) { ?>
                                        <span class="dokan-store-is-open-close-status dokan-store-is-open-status" title="<?php esc_attr_e('Store is Open', 'dokan-lite'); ?>"><?php esc_html_e('Open', 'dokan-lite'); ?></span>
                                    <?php } else { ?>
                                        <span class="dokan-store-is-open-close-status dokan-store-is-closed-status" title="<?php esc_attr_e('Store is Closed', 'dokan-lite'); ?>"><?php esc_html_e('Closed', 'dokan-lite'); ?></span>
                                    <?php } ?>
                                <?php endif ?>

                                <div class="store-data <?php echo esc_attr($store_open_is_on); ?>">
                                    <h2><?php echo esc_html($store_name); ?></h2>


                                    <?php $rating = dokan_get_readable_seller_rating($author->ID); ?>
                                    <div class="dokan-store-rating <?php if (!strpos($rating, 'seller-rating') == '<') {
                                                                        echo "no-reviews-rating";
                                                                    } ?>">
                                        <i class="fa fa-star"></i>
                                        <?php echo wp_kses_post($rating); ?>
                                    </div>


                                    <?php if (!dokan_is_vendor_info_hidden('address') && $store_address) : ?>
                                        <?php
                                        $allowed_tags = array(
                                            'span' => array(
                                                'class' => array(),
                                            ),
                                            'br' => array()
                                        );
                                        ?>
                                        <p class="store-address"><?php echo wp_kses($store_address, $allowed_tags); ?></p>
                                    <?php endif ?>

                                    <?php if (!dokan_is_vendor_info_hidden('phone') && $store_phone) { ?>
                                        <p class="store-phone">
                                            <i class="fa fa-phone" aria-hidden="true"></i> <?php echo esc_html($store_phone); ?>
                                        </p>
                                    <?php } ?>

                                    <?php do_action('dokan_seller_listing_after_store_data', $author, $store_info); ?>
                                </div>
                            </div>
                        </div>

                        <div class="store-footer">

                            <?php $rating = dokan_get_readable_seller_rating($author->ID); ?>
                            <div class="dokan-store-rating <?php if (!strpos($rating, 'seller-rating') == '<') {
                                                                echo "no-reviews-rating";
                                                            } ?>">
                                <i class="fa fa-star"></i>
                                <?php echo wp_kses_post($rating); ?>
                            </div>

                            <div class="seller-avatar">

                                <img src="<?php echo esc_url($vendor->get_avatar()) ?>" alt="<?php echo esc_attr($vendor->get_shop_name()) ?>" size="150">

                            </div>

                            <span class="dashicons dashicons-arrow-right-alt2 dokan-btn-theme dokan-btn-round"></span>

                            <?php do_action('dokan_seller_listing_footer_content', $author, $store_info); ?>
                        </div>
                    </div>
                </a>
            </li>

            <div class="dokan-clearfix"></div>
        </ul> <!-- .dokan-seller-wrap -->
    </div>
</div>