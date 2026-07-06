<?php

/**
 * The Template for displaying vendor biography.
 *
 * @package dokan
 */

$store_user = get_userdata(get_query_var('author'));
$store_info   = dokan_get_store_info($store_user->ID);
$map_location = $store_user->get_location();
$layout       = get_theme_mod('store_layout', 'left');
$layout       = 'right';

get_header('shop');
$store_user               = dokan()->vendor->get(get_query_var('author'));
$header_background = $store_user->get_banner();

?>

<div id="titlebar" class="store-titlebar <?php if (empty($header_background)) : ?> no-store-bg <?php endif; ?>">

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <?php dokan_get_template_part('store-header'); ?>
            </div>
        </div>
    </div>
</div>

<?php // do_action('woocommerce_before_main_content'); ?>

<div class="container listeo-shop-grid <?php echo esc_attr($layout); ?>">

    <?php if ('left' === $layout) { ?>
        <?php dokan_get_template_part('store', 'sidebar', array('store_user' => $store_user, 'store_info' => $store_info, 'map_location' => $map_location)); ?>
    <?php } ?>

    <div id="dokan-primary" class="dokan-single-store  col-md-8 col-lg-9">
        <div id="dokan-content" class="store-review-wrap woocommerce" role="main">
            <div id="vendor-biography">
                <div id="comments">
                    <?php do_action('dokan_vendor_biography_tab_before', $store_user, $store_info); ?>

                    <h2 class="headline"><?php echo apply_filters('dokan_vendor_biography_title', __('Vendor Biography', 'dokan')); ?></h2>

                    <?php
                    if (!empty($store_info['vendor_biography'])) {
                        printf('%s', apply_filters('the_content', $store_info['vendor_biography']));
                    }
                    ?>

                    <?php do_action('dokan_vendor_biography_tab_after', $store_user, $store_info); ?>
                </div>
            </div>

        </div><!-- #content .site-content -->
    </div><!-- #primary .content-area -->

    <?php if ('right' === $layout) { ?>
        <?php dokan_get_template_part('store', 'sidebar', array('store_user' => $store_user, 'store_info' => $store_info, 'map_location' => $map_location)); ?>
    <?php } ?>

</div><!-- .dokan-store-wrap -->
</div><!-- .dokan-store-wrap -->

<?php do_action('woocommerce_after_main_content'); ?>

<?php get_footer(); ?>