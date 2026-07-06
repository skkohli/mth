<?php

/**
 * Change the Shop archive page title.
 * @param  string $title
 * @return string
 */


remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');

add_filter('user_has_cap', 'listeo_order_pay_without_login', 9999, 3);

function listeo_order_pay_without_login($allcaps, $caps, $args)
{
    if (isset($caps[0], $_GET['key'])) {
        if ($caps[0] == 'pay_for_order') {
            $order_id = isset($args[2]) ? $args[2] : null;
            $order = wc_get_order($order_id);
            if ($order) {
                $allcaps['pay_for_order'] = true;
            }
        }
    }
    return $allcaps;
}

add_filter('woocommerce_order_email_verification_required', '__return_false', 9999);

add_filter('woocommerce_enqueue_styles', '__return_empty_array');

add_filter('loop_shop_columns', 'loop_columns', 999);
if (!function_exists('loop_columns')) {
    function loop_columns()
    {

        $layout = get_option('pp_shop_layout', 'full-width');

        if ($layout == 'full-width') {
            return 3;
        } else {

            return 2;
        }
    }
}
add_filter('product_cat_class', 'remove_category_class', 21, 3); //woocommerce use priority 20, so if you want to do something after they finish be more lazy
function remove_category_class($classes)
{
    if ('product' == get_post_type()) {
        $classes = array_diff($classes, array('last', 'first'));
    }
    return $classes;
}
/**
 * Change number or products per row to 3
 */
add_filter('loop_shop_columns', 'loop_columns', 999);
if (!function_exists('loop_columns')) {
    function loop_columns()
    {
        return 3; // 3 products per row
    }
}

add_action('wp_enqueue_scripts', 'lv2_dequeue_wcselect2', 100);

function lv2_dequeue_wcselect2()
{
    if (class_exists('woocommerce')) {
        wp_dequeue_style('select2');
        wp_deregister_style('select2');

        wp_dequeue_script('select2');
        wp_deregister_script('select2');
    }
}

add_filter('woocommerce_product_data_store_cpt_get_products_query', 'listeo_exclude_listing_booking', 10, 2);

function listeo_exclude_listing_booking($query, $query_vars)
{
    
    if (!empty($query_vars['exclude_listing_booking'])) {
        $query['tax_query'][] = array(
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms' => array('listing_booking'), // 
            'operator' => 'NOT IN'
        );
        $query['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => array('listeo-booking'), // Don't display products in the clothing category on the shop page.
            'operator' => 'NOT IN'
        );
    }
    return $query;
}

//add_filter('woocommerce_short_description', 'listeo_woocommerce_short_description', 10, 1);
function listeo_woocommerce_short_description($post_excerpt)
{
    global $product;
    if ($product->get_type() == "property_package") {

        $output = '<ul>';

        $jobslimit = $product->get_limit();
        if (!$jobslimit) {
            $output .= "<li>";
            $output .= esc_html__('Unlimited number of listings', 'listeo');
            $output .=  "</li>";
        } else {
            $output .= '<li>';
            $output .= esc_html__('This plan includes ', 'listeo');
            $output .= sprintf(_n('%d listing', '%d listings', $jobslimit, 'listeo') . ' ', $jobslimit);
            $output .= '</li>';

            $jobduration =  $product->get_duration();
            if (!empty($jobduration)) {
                $output .= '<li>';
                $output .= esc_html__('Listings are published ', 'listeo');
                $output .= sprintf(_n('for %s day', 'for %s days', $product->get_duration(), 'listeo'), $product->get_duration());
                $output .= '</li>';
            }
            $output .= "</ul>";
        }

        $post_excerpt = $output . $post_excerpt;
    }
    return $post_excerpt;
}


remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5);
remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 10);

add_filter('woocommerce_show_page_title', 'listeo_hide_shop_title');
function listeo_hide_shop_title()
{
    return false;
}



remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
add_action('woocommerce_after_single_product_summary', 'woocommerce_output_upsells', 15);

if (!function_exists('woocommerce_output_upsells')) {
    function woocommerce_output_upsells()
    {
        woocommerce_upsell_display(3, 3); // Display 3 products in rows of 3
    }
}

add_filter('woocommerce_output_related_products_args', 'listeo_related_woo_per_page');

function listeo_related_woo_per_page($args)
{
    $args = wp_parse_args(array('posts_per_page' => 3), $args);
    return $args;
}


function listeo_woocommerce_remove_item($listeo_html, $cart_item_key)
{
    $cart_item_key = $cart_item_key;
    $listeo_html = sprintf('<a href="%s" class="remove" title="%s"><i class="fa fa-times" aria-hidden="true"></i></a>', esc_url(wc_get_cart_remove_url($cart_item_key)), esc_html__('Remove this item', 'listeo'));
    return $listeo_html;
}

add_filter('woocommerce_cart_item_remove_link', 'listeo_woocommerce_remove_item', 10, 2);


/**
 * Exclude products from a particular category on the shop page
 */
function listeo_remove_packages_pre_get_posts_query($q)
{

    $tax_query = (array) $q->get('meta_query');

    $tax_query[] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listing_package'), // Don't display products in the clothing category on the shop page.
        'operator' => 'NOT IN'
    );
    $tax_query[] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listing_package_subscription'), // Don't display products in the clothing category on the shop page.
        'operator' => 'NOT IN'
    );
    $tax_query[] = array(
        'taxonomy' => 'product_cat',
        'field' => 'slug',
        'terms' => array('listeo-booking'), // Don't display products in the clothing category on the shop page.
        'operator' => 'NOT IN'
    );
 
    $tax_query[] = array(
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => array('listeo_ad_campaign'), // Don't display products in the clothing category on the shop page.
        'operator' => 'NOT IN'
    );
 
    $q->set('tax_query', $tax_query);
}
add_action('woocommerce_product_query', 'listeo_remove_packages_pre_get_posts_query');


// add_filter('woocommerce_product_query_tax_query', 'only_grouped_products', 20, 1);
// function only_grouped_products($tax_query)
// {
//     $tax_query[] = array(
//         'taxonomy' => 'product_cat',
//         'field' => 'slug',
//         'terms' => array('listeo-booking'), // Don't display products in the clothing category on the shop page.
//         'operator' => 'NOT IN'
//     );
//     return $tax_query;
// }


function exclude_woocommerce_widget_product_categories($widget_args)
{
    $category = get_term_by('slug', 'listeo-booking', 'product_cat');


    $widget_args['exclude'] = array($category->term_id);

    return $widget_args;
}
add_filter('woocommerce_product_categories_widget_dropdown_args', 'exclude_woocommerce_widget_product_categories');
add_filter('woocommerce_product_categories_widget_args', 'exclude_woocommerce_widget_product_categories');

add_filter('get_terms', 'exclude_listeo_booking_from_shop_page', 10, 3);
function exclude_listeo_booking_from_shop_page($terms, $taxonomies, $args)
{
    $new_terms = array();
    // check if $taxonomies is not NULL
    if (!is_array($taxonomies)) {
        return $terms;
    }
    // if it is a product category and on the shop page
    if (in_array('product_cat', $taxonomies) && !is_admin() && is_shop()) {
        foreach ($terms as $key => $term) {
            if (!in_array($term->slug, array('listeo-booking'))) { //pass the slug name here
                $new_terms[] = $term;
            }
        }
        $terms = $new_terms;
    }
    return $terms;
}


add_filter('woocommerce_products_widget_query_args', function ($query_args) {
    // Set HERE your product category slugs 
    $categories = array('listeo-booking');

    $query_args['tax_query'] = array(array(
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $categories,
        'operator' => 'NOT IN'
    ));

    return $query_args;
}, 10, 1);

remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);



add_filter('woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_fragment');
function woocommerce_header_add_to_cart_fragment($fragments)
{
    global $woocommerce;

    ob_start();
?>
    <div class="mini-cart-button"><i class="fa fa-shopping-cart"></i><span class="badge"><?php echo sprintf(_n('%d', '%d', WC()->cart->cart_contents_count, 'listeo'), WC()->cart->cart_contents_count); ?></span></div>

<?php

    $fragments['div.mini-cart-button'] = ob_get_clean();
    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_summary_fragment');
function woocommerce_header_add_to_cart_summary_fragment($fragments)
{
    global $woocommerce;

    ob_start();
?>
    <p class="woocommerce-mini-cart__total total">
        <strong><?php esc_html_e('Subtotal', 'listeo'); ?></strong>
        <?php echo WC()->cart->get_cart_subtotal(); ?>
    </p>
<?php

    $fragments['p.woocommerce-mini-cart__total'] = ob_get_clean();
    return $fragments;
}



add_filter('woocommerce_add_to_cart_fragments', 'woocommerce_header_add_to_cart_content_fragment');
function woocommerce_header_add_to_cart_content_fragment($fragments)
{

    ob_start(); ?>
    <div class="listeo-mini-cart">
        <?php do_action('woocommerce_before_mini_cart'); ?>

        <?php if (!WC()->cart->is_empty()) : ?>

            <ul class="woocommerce-mini-cart cart_list cart-list product_list_widget">
                <?php
                do_action('woocommerce_before_mini_cart_contents');

                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    $_product   = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                    if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key)) {
                        $product_name      = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                        $thumbnail         = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                        $product_price     = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
                        $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                ?>
                        <li class="woocommerce-mini-cart-item <?php echo esc_attr(apply_filters('woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key)); ?>">

                            <?php if (empty($product_permalink)) : ?>
                                <?php echo $thumbnail . wp_kses_post($product_name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                ?>
                            <?php else : ?>
                                <a href="<?php echo esc_url($product_permalink); ?>">
                                    <?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                    ?>
                                </a>
                                <a href="<?php echo esc_url($product_permalink); ?>" class="mini-cart-product-name">
                                    <span class="mini-cart-product-price"><?php echo wp_kses_post($product_name); ?></span>
                                    <?php echo apply_filters('woocommerce_widget_cart_item_quantity', '<span class="mini-cart-quantity">' . sprintf('%s &times; %s', $cart_item['quantity'], $product_price) . '</span>', $cart_item, $cart_item_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                    ?>
                                </a>
                            <?php endif; ?>


                        </li>
                <?php
                    }
                }

                do_action('woocommerce_mini_cart_contents');
                ?>
            </ul>

            <p class="woocommerce-mini-cart__total total">
                <?php
                /**
                 * Hook: woocommerce_widget_shopping_cart_total.
                 *
                 * @hooked woocommerce_widget_shopping_cart_subtotal - 10
                 */
                do_action('woocommerce_widget_shopping_cart_total');
                ?>
            </p>

            <?php do_action('woocommerce_widget_shopping_cart_before_buttons'); ?>

            <p class="woocommerce-mini-cart__buttons buttons"><?php do_action('woocommerce_widget_shopping_cart_buttons'); ?></p>

            <?php do_action('woocommerce_widget_shopping_cart_after_buttons'); ?>

        <?php else : ?>

            <p class="woocommerce-mini-cart__empty-message"><?php esc_html_e('No products in the cart.', 'woocommerce'); ?></p>

        <?php endif; ?>

        <?php do_action('woocommerce_after_mini_cart'); ?>
    </div>

    <?php $fragments['div.listeo-mini-cart'] = ob_get_clean();
    return $fragments;
}

    ?>