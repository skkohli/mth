<?php
if (defined('REST_REQUEST') && REST_REQUEST) {
    return;
}

if (! function_exists('WC') || ! WC() || ! property_exists(WC(), 'cart') || is_null(WC()->cart)) {
    return;
}
if(is_woocommerce_activated() && get_option('listeo_cart_display')) : ?>
<div class="listeo-cart-container">

    <div class="mini-cart-button"><i class="fa fa-shopping-cart"></i><span class="badge"><?php echo sprintf(_n('%d', '%d', WC()->cart->cart_contents_count, 'listeo'), WC()->cart->cart_contents_count); ?></span></div>
    <div class="listeo-cart-wrapper">
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
    </div>
</div>
<?php endif; ?>