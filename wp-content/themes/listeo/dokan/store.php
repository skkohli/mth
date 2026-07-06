<?php

/**
 * The Template for displaying all single posts.
 *
 * @package dokan
 * @package dokan - 2014 1.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

$store_user   = dokan()->vendor->get(get_query_var('author'));
$store_info   = $store_user->get_shop_info();
$map_location = $store_user->get_location();
$layout       = 'right';

$full_width_header = get_post_meta($post->ID, 'listeo_full_width_header', TRUE);
if (empty($full_width_header)) {
    $full_width_header = 'use_global';
};

if ($full_width_header == 'use_global') {
    $full_width_header = get_option('listeo_full_width_header');
}

if ($full_width_header == 'enable') {
    get_header('fullwidth');
} else {
    get_header();
}
$store_user               = dokan()->vendor->get(get_query_var('author'));
$header_background = $store_user->get_banner();

?>

<div id="titlebar" class="store-titlebar <?php if(empty($header_background)) : ?> no-store-bg <?php endif;?>">
    
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <?php dokan_get_template_part('store-header'); ?>
            </div>
        </div>
    </div>
</div>

<?php //do_action('woocommerce_before_main_content');
?>

<div class="container listeo-shop-grid <?php echo esc_attr($layout); ?>">

    <div class="row">
        <?php if ('left' === $layout) { ?>
            <?php dokan_get_template_part('store', 'sidebar', array('store_user' => $store_user, 'store_info' => $store_info, 'map_location' => $map_location)); ?>
        <?php } ?>

        <div id="dokan-primary" class="dokan-single-store col-md-8 col-lg-9">
            <div id="dokan-content" class="store-page-wrap woocommerce" role="main">

                <?php // do_action('dokan_store_profile_frame_after', $store_user->data, $store_info); 
                $store_products = dokan_get_option('store_products', 'dokan_appearance');

                if (!empty($store_products['hide_product_filter'])) {
                    return;
                }

                $orderby_options = dokan_store_product_catalog_orderby();
                $store_user      = dokan()->vendor->get(get_query_var('author'));
                $store_id        = $store_user->get_id();
                ?>
                <div class="dokan-store-products-filter-area dokan-clearfix">
                    <form class="dokan-store-products-ordeby_" method="get">
                        <div class="dokan-store-products-ordeby-search">
                            <input type="text" name="product_name" class="product-name-search dokan-store-products-filter-search" placeholder="<?php esc_attr_e('Enter product name', 'dokan-lite'); ?>" autocomplete="off" data-store_id="<?php echo esc_attr($store_id); ?>">
                            <div id="dokan-store-products-search-result" class="dokan-ajax-store-products-search-result"></div>
                            <input type="submit" name="search_store_products" class="search-store-products dokan-btn-theme" value="<?php esc_attr_e('Search', 'dokan-lite'); ?>">
                        </div>
                        <div class="dokan-store-products-ordeby-select">
                            <?php if (is_array($orderby_options['catalogs']) && isset($orderby_options['orderby'])) : ?>
                                <select name="product_orderby" class="listeo-orderby listeo-orderby-search" aria-label="<?php esc_attr_e('Shop order', 'dokan-lite'); ?>" onchange='if(this.value != 0) { this.form.submit(); }'>
                                    <?php foreach ($orderby_options['catalogs'] as $id => $name) : ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($orderby_options['orderby'], $id); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <input type="hidden" name="paged" value="1" />
                        </div>
                    </form>
                </div>


                <?php if (have_posts()) { ?>

                    <div class="seller-items">

                        <?php woocommerce_product_loop_start(); ?>

                        <?php while (have_posts()) : the_post(); ?>

                            <?php wc_get_template_part('content', 'product'); ?>

                        <?php endwhile; // end of the loop. 
                        ?>

                        <?php woocommerce_product_loop_end(); ?>

                    </div>

                    <?php dokan_content_nav('nav-below'); ?>

                <?php } else { ?>

                    <p class="dokan-info"><?php esc_html_e('No products were found of this vendor!', 'dokan-lite'); ?></p>

                <?php } ?>
            </div>

        </div><!-- .dokan-single-store -->


        <?php dokan_get_template_part('store', 'sidebar', array('store_user' => $store_user, 'store_info' => $store_info, 'map_location' => $map_location)); ?>


    </div><!-- .dokan-store-wrap -->
</div><!-- .dokan-store-wrap -->

<?php do_action('woocommerce_after_main_content'); ?>

<?php get_footer('shop'); ?>