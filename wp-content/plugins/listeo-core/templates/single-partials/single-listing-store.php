<?php
$set_products = get_post_meta($post->ID, '_store_products');
$vendor_id = get_post_field('post_author', $post->ID);
$vendor            = dokan()->vendor->get($vendor_id);

$store_name        = $vendor->get_shop_name();
$store_url         = $vendor->get_shop_url();
?>
<div id="listing-store" class="listing-section">
    <div class="listeo-store-browse-more">
        <h2 class="listing-desc-headline margin-top-60 margin-bottom-30"><?php esc_html_e('Store', 'listeo_core'); ?>

        </h2>
        <a class="button" href="<?php echo esc_url($store_url); ?>" ><?php esc_html_e('Browse All Products', 'listeo_core'); ?></a>
    </div>

    <?php


    $orderby =   'title';
    $order =   'ASC';
    //$exclude_posts = $settings['exclude_posts'] ? $settings['exclude_posts'] : array();
    $include_posts = $set_products ? $set_products : array();


    $output = '';
    $randID = rand(1, 99); // Get unique ID for carousel

    $meta_query = array();


    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'author'    => $vendor_id,
        'orderby' => $orderby,
        'order' => $order,

    );
    // if (isset($settings['product_types']) && is_array($settings['product_types']) && !empty($settings['product_types'])) {
    //     $args['type'] = $settings['product_types'];
    // }

    // if (isset($settings['tax-product_cat']) && is_array($settings['tax-product_cat']) && !empty($settings['tax-product_cat'])) {
    //     $args['category'] = $settings['tax-product_cat'];
    // }



    // if (!empty($exclude_posts)) {
    //     $exl = is_array($exclude_posts) ? $exclude_posts : array_filter(array_map('trim', explode(',', $exclude_posts)));
    //     $args['exclude'] = $exl;
    // }

    if (!empty($include_posts)) {
        $inc = is_array($include_posts) ? $include_posts : array_filter(array_map('trim', explode(',', $include_posts)));
        $args['include'] = $inc;
    }



    $i = 0;
    $args['exclude_listing_booking'] = 'true';
    $args['tax_query'][] = array(
        'taxonomy' => 'product_cat',
        'field' => 'slug',
        'terms' => array('listeo-booking'), // Don't display products in the clothing category on the shop page.
        'operator' => 'NOT IN'
    );
    $args['tax_query'][] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listing_package'), // Don't display products in the clothing category on the shop page.
        'operator' => 'NOT IN'
    );
    $products = wc_get_products($args);


    ob_start();
    ?>
    <!-- Carousel / Start -->
    <div class="simple-slick-carousel  listeo-products-slider dots-nav">
        <?php
        if ($products) {
            $count = 0;
            foreach ($products as $product) {
                $count++;
                $thumbnail_id = $product->get_image_id();
                // $product = wc_get_product(get_the_ID());

        ?>
                <div class="fw-carousel-item">

                    <div <?php post_class('', $product->get_id()); ?>>
                        <div class="mediaholder">
                            <a href="<?php echo get_permalink($product->get_id()); ?>">
                                <?php
                               // if (has_post_thumbnail($product->get_id())) {
                                    $props            = wc_get_product_attachment_props(get_post_thumbnail_id(), $product->get_id());
                                    $image            = get_the_post_thumbnail($product->get_id(), apply_filters('single_product_large_thumbnail_size', 'shop_single'), array(
                                        'title'     => $props['title'],
                                        'alt'    => $props['alt'],
                                    ));
                                    $size = 'listeo_core-avatar';
                                    $image_size = apply_filters('single_product_archive_thumbnail_size', $size);
                                    echo $product->get_image($image_size);
                                
                                ?>
                            </a>
                            <?php $link     = $product->add_to_cart_url();
                            $label     = apply_filters('add_to_cart_text', esc_html__('Add to cart', 'listeo'));
                            ?>
                            <a href="<?php echo esc_url($link); ?>" class="button"><i class="fa fa-shopping-cart"></i> <?php echo esc_html($label); ?></a>
                        </div>
                        <section>
                            <span class="product-category">
                                <?php
                                $product_cats = wp_get_post_terms($product->get_id(), 'product_cat');
                                if ($product_cats && !is_wp_error($product_cats)) {
                                    $single_cat = array_shift($product_cats);
                                    echo esc_html($single_cat->name);
                                } ?>
                            </span>

                            <h5><a href="<?php echo get_permalink($product->get_id()); ?>"><?php echo $product->get_title(); ?></a></h5>

                            <?php echo $product->get_price_html(); ?>
                        </section>
                    </div>



                </div>
        <?php
            }
        }



        ?>
    </div>
    <?php wp_reset_postdata();
    wp_reset_query();

    echo ob_get_clean();
    ?>


</div>