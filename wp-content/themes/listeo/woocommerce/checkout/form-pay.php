<?php

/**
 * Pay for order form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-pay.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 8.2.0
 */

defined('ABSPATH') || exit;

$totals = $order->get_order_item_totals();

$total = $order->get_total();
WC()->cart->empty_cart();

foreach ($order->get_items() as $item_id => $item) {
    $product = wc_get_product($item->get_product_id());
    $product->set_price($item->get_total());
    $custom_price = $item->get_total();
    // Cart item data to send & save in order
    $cart_item_data = array('custom_price' => $custom_price);
    // woocommerce function to add product into cart check its documentation also
    // what we need here is only $product_id & $cart_item_data other can be default.
    WC()->cart->add_to_cart($item->get_product_id(), $item->get_quantity(), NULL, NULL, $cart_item_data);
}
// Calculate totals
WC()->cart->calculate_totals();
// Save cart to session
WC()->cart->set_session();
// Maybe set cart cookies
WC()->cart->maybe_set_cart_cookies();
WC()->session->set('cart_created_for_payment_request_btn', true);
?>
<script>
    jQuery(document.body).trigger('updated_cart_totals');
    jQuery(document.body).trigger('updated_checkout');
</script>

<?php add_filter('wc_stripe_show_payment_request_on_checkout', '__return_true');
do_action('woocommerce_checkout_before_customer_details'); ?>
<div id="payment-request-button">
    <!-- A Stripe Element will be inserted here. Niro-->
</div>


<form id="order_review" class="listeo-pay-form" method="post">

    <table class="shop_table">
        <thead>
            <tr>
                <th class="product-name"><?php esc_html_e('Product', 'listeo'); ?></th>
                <th class="product-quantity"><?php esc_html_e('Qty', 'listeo'); ?></th>
                <th class="product-total"><?php esc_html_e('Totals', 'listeo'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($order->get_items()) > 0) : ?>
                <?php foreach ($order->get_items() as $item_id => $item) :

                    $services = $order->get_meta('listeo_services');
                ?>
                    <?php
                    if (!apply_filters('woocommerce_order_item_visible', true, $item)) {
                        continue;
                    }
                    ?>
                    <tr class="<?php echo esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order)); ?>">
                        <td class="product-name">
                            <?php
                            echo apply_filters('woocommerce_order_item_name', esc_html($item->get_name()), $item, false); // @codingStandardsIgnoreLine

                            do_action('woocommerce_order_item_meta_start', $item_id, $item, $order, false);

                            wc_display_item_meta($item);

                            do_action('woocommerce_order_item_meta_end', $item_id, $item, $order, false);

                            ?>
                            <?php


                            $booking_id = $order->get_meta('booking_id');
                            if ($booking_id) {
                                $bookings = new Listeo_Core_Bookings_Calendar;
                                $booking_data = $bookings->get_booking($booking_id);

                                $listing_id = $order->get_meta('listing_id');

                                //get post type to show proper date
                                $listing_type = get_post_meta($listing_id, '_listing_type', true);
                                echo '<div class="inner-booking-list">';
                                if ($listing_type == 'rental') { ?>

                                    <h5><?php esc_html_e('Dates:', 'listeo'); ?></h5>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($booking_data['date_start'])); ?> - <?php echo date_i18n(get_option('date_format'), strtotime($booking_data['date_end'])); ?></li>
                                <?php } else if ($listing_type == 'service') { ?>

                                    <h5><?php esc_html_e('Dates:', 'listeo'); ?></h5>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($booking_data['date_start'])); ?>
                                    <?php esc_html_e('at', 'listeo'); ?> <?php echo date_i18n(get_option('time_format'), strtotime($booking_data['date_start'])); ?> <?php if ($booking_data['date_start'] != $booking_data['date_end']) echo  '- ' . date_i18n(get_option('time_format'), strtotime($booking_data['date_end'])); ?></li>
                                <?php } else { //event

                                    $meta_value = get_post_meta($listing_id, '_event_date', true);
                                    if (!empty($meta_value)) {
                                        $meta_value_date = explode(' ', $meta_value, 2);
                                        $date_format = get_option('date_format');

                                        // Try to create DateTime object with improved error handling
                                        try {
                                            $php_format = listeo_date_time_wp_format_php();
                                            $date_obj = DateTime::createFromFormat($php_format, $meta_value_date[0]);

                                            if ($date_obj === false) {
                                                // Fallback: try with strtotime if DateTime::createFromFormat fails
                                                $meta_value_stamp = strtotime($meta_value_date[0]);
                                            } else {
                                                $meta_value_stamp = $date_obj->getTimestamp();
                                            }

                                            $meta_value = date_i18n(get_option('date_format'), $meta_value_stamp);

                                            // Handle time part with improved parsing
                                            if (isset($meta_value_date[1]) && !empty($meta_value_date[1])) {
                                                $time_part = trim($meta_value_date[1]);

                                                // Check if it's a time range (contains '-')
                                                if (strpos($time_part, '-') !== false) {
                                                    $time_range = explode('-', $time_part);
                                                    $start_time = trim($time_range[0]);
                                                    $end_time = isset($time_range[1]) ? trim($time_range[1]) : '';

                                                    $meta_value .= esc_html__(' from ', 'listeo');
                                                    $meta_value .= date_i18n(get_option('time_format'), strtotime($start_time));

                                                    if (!empty($end_time)) {
                                                        $meta_value .= esc_html__(' to ', 'listeo');
                                                        $meta_value .= date_i18n(get_option('time_format'), strtotime($end_time));
                                                    }
                                                } else {
                                                    // Single time
                                                    $meta_value .= esc_html__(' at ', 'listeo');
                                                    $meta_value .= date_i18n(get_option('time_format'), strtotime($time_part));
                                                }
                                            }
                                        } catch (Exception $e) {
                                            // Final fallback: display raw value if all parsing fails
                                            $meta_value = $meta_value;
                                        }

                                        echo $meta_value;
                                    }
                                } ?>
                                </div>
                                <div class="inner-booking-list">
                                    <h5><?php esc_html_e('Extra Services:', 'listeo'); ?></h5>
                                    <?php echo listeo_get_extra_services_html($services); //echo wpautop( $details->service); 
                                    ?>
                                </div>
                                <?php
                                if (get_option('listeo_remove_guests') != 'on') {


                                    $details = json_decode($booking_data['comment']);
                                    $has_children = false;
                                    if (isset($details->children) && $details->children > 0) {
                                        $has_children = true;
                                    }
                                    if (
                                        (isset($details->childrens) && $details->childrens > 0)
                                        ||
                                        (isset($details->adults) && $details->adults > 0)
                                        ||
                                        (isset($details->tickets) && $details->tickets > 0)
                                    ) { ?>
                                        <div class="inner-booking-list">
                                            <h5><?php esc_html_e('Booking Details:', 'listeo'); ?></h5>

                                            <ul class="booking-list">
                                                <li class="highlighted" id="details">
                                                    <?php
                                                    $details_guests_output = array();
                                                    if (
                                                        isset($details->adults)  && $details->adults > 0 &&
                                                        $has_children == false
                                                    ) :  ?>
                                                        <?php $details_guests_output[] = sprintf(_n('%d Guest', '%d Guests', $details->adults, 'listeo_core'), $details->adults) ?>
                                                    <?php endif; ?>
                                                    <?php if (
                                                        isset($details->adults)  && $details->adults > 0 &&
                                                        $has_children == true
                                                    ) : ?>
                                                        <?php $details_guests_output[] = sprintf(_n('%d Adult', '%d Adults', $details->adults, 'listeo_core'), $details->adults) ?>
                                                    <?php endif; ?>
                                                    <?php if (isset($details->children) && $details->children > 0) : ?>
                                                        <?php $details_guests_output[] = sprintf(_n('%d Child', '%d Children', $details->children, 'listeo_core'), $details->children) ?>
                                                    <?php endif; ?>
                                                    <?php if (isset($details->infants) && $details->infants > 0) : ?>
                                                        <?php $details_guests_output[] = sprintf(_n('%d Infant', '%d Infants', $details->infants, 'listeo_core'), $details->infants) ?>
                                                    <?php endif; ?>
                                                    <?php if (isset($details->animals) && $details->animals > 0) : ?>
                                                        <?php $details_guests_output[] = sprintf(_n('%d Animal', '%d Animals', $details->animals, 'listeo_core'), $details->animals) ?>
                                                    <?php endif; ?>

                                                    <?php if (isset($details->tickets)  && $details->tickets > 0) : ?>
                                                        <?php $details_guests_output[] = sprintf(_n('%d Ticket', '%d Tickets', $details->tickets, 'listeo_core'), $details->tickets) ?>
                                                    <?php endif; ?>

                                                    <?php if (count($details_guests_output) > 1) : ?>
                                                        <?php echo implode(', ', $details_guests_output); ?>
                                                    <?php else: ?>
                                                        <?php echo implode(' ', $details_guests_output); ?>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                            <?php }
                                }
                            }
                            ?>
                        </td>
                        <td class="product-quantity"><?php echo apply_filters('woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf('&times; %s', esc_html($item->get_quantity())) . '</strong>', $item); ?></td><?php // @codingStandardsIgnoreLine 
                                                                                                                                                                                                                                                    ?>
                        <td class="product-subtotal"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></td><?php // @codingStandardsIgnoreLine 
                                                                                                                                    ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php if ($totals) : ?>
                <?php foreach ($totals as $total) : ?>
                    <tr>
                        <th scope="row" colspan="2"><?php echo wp_kses_post($total['label']); ?></th><?php // @codingStandardsIgnoreLine 
                                                                                                        ?>
                        <td class="product-total"><?php echo wp_kses_post($total['value']); ?></td><?php // @codingStandardsIgnoreLine 
                                                                                                    ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tfoot>
    </table>

    <?php
    // Hidden billing fields for payment gateways (e.g., PayPal) that read billing data from form inputs
    $billing_fields = array(
        'billing_first_name' => $order->get_billing_first_name(),
        'billing_last_name'  => $order->get_billing_last_name(),
        'billing_email'      => $order->get_billing_email(),
        'billing_phone'      => $order->get_billing_phone(),
        'billing_address_1'  => $order->get_billing_address_1(),
        'billing_address_2'  => $order->get_billing_address_2(),
        'billing_city'       => $order->get_billing_city(),
        'billing_state'      => $order->get_billing_state(),
        'billing_postcode'   => $order->get_billing_postcode(),
        'billing_country'    => $order->get_billing_country(),
    );
    foreach ( $billing_fields as $field_id => $field_value ) : ?>
        <input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $field_value ); ?>" />
    <?php endforeach; ?>

    <div id="payment">
        <?php if ($order->needs_payment()) : ?>
            <ul class="wc_payment_methods payment_methods methods">
                <?php
                if (!empty($available_gateways)) {
                    foreach ($available_gateways as $gateway) {
                        wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
                    }
                } else {
                    echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">' . apply_filters('woocommerce_no_available_payment_methods_message', esc_html__('Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'listeo')) . '</li>'; // @codingStandardsIgnoreLine
                }
                ?>
            </ul>
        <?php endif; ?>
        <div class="form-row">
            <input type="hidden" name="woocommerce_pay" value="1" />

            <?php wc_get_template('checkout/terms.php'); ?>

            <?php do_action('woocommerce_pay_order_before_submit'); ?>

            <?php echo apply_filters('woocommerce_pay_order_button_html', '<button type="submit" class="button alt" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '">' . esc_html($order_button_text) . '</button>'); // @codingStandardsIgnoreLine 
            ?>

            <?php do_action('woocommerce_pay_order_after_submit'); ?>

            <?php wp_nonce_field('woocommerce-pay', 'woocommerce-pay-nonce'); ?>
        </div>
    </div>
</form>