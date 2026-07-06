<?php

/**
 * Modern "Received Booking Request" Item Template - Corrected Version
 *
 * This template combines the modern design of "my bookings" with the
 * owner-specific logic and actions from the "received booking requests" page.
 * All missing functionality from the original template has been restored.
 */

// --- Start of CSS ---
// NOTE: Including CSS directly in a template part is not standard practice, but done as requested.
// It's recommended to enqueue this via functions.php for better performance.
?>
<link rel="stylesheet" href="<?php echo LISTEO_CORE_URL . 'templates/booking/dashboard-booking.css'; ?>">
<?php
// --- End of CSS ---

if (!isset($data) || $data->comment == 'owner reservations') {
    return;
}

// --- SVG Icons Helper ---
$svg_icons = [
    'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>',
    'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    'map-pin' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
    'dollar-sign' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
    'message' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
    'mail' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>',
    'phone' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>',
    'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>',
    'clock' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
    'x-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>',
    'alert-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg>',
    'trash' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
    'info' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
    'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
    'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9,22 9,12 15,12 15,22"></polyline></svg>',
    'settings' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
    'credit-card' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>',
    'video' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"></path><rect width="14" height="12" x="2" y="6" rx="2" ry="2"></rect></svg>',
];

// --- Data Preparation ---
$listing_id = $data->listing_id;
$listing_type = get_post_meta($listing_id, '_listing_type', true);
$details = json_decode($data->comment);

// Default action button visibility
$show_approve = false;
$show_reject = false;
$show_cancel = false;
$show_delete = false;
$show_renew = false;

// Determine contact info visibility
$show_contact = !get_option('listeo_lock_contact_info_to_paid_bookings');

// Payment method logic (from original template)
$payment_method = '';
if (isset($data->order_id) && !empty($data->order_id) && in_array($data->status, array('confirmed', 'pay_to_confirm'))) {
    $order = wc_get_order($data->order_id);
    if ($order) {
        $payment_method = $order->get_payment_method();
    }
    if (get_option('listeo_disable_payments')) {
        $payment_method = 'cod';
    }
}
$_payment_option = get_post_meta($listing_id, '_payment_option', true) ?: 'pay_now';
$_rental_timepicker = get_post_meta($listing_id, '_rental_timepicker', true);
if ($_payment_option == "pay_cash") {
    $payment_method = 'cod';
}

// Status-specific configuration (matching original template logic)
$status_config = [];
switch ($data->status) {
    case 'waiting':
        $status_config = [
            'text' => esc_html__('Pending', 'listeo_core'),
            'icon' => $svg_icons['clock'],
            'class' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'border' => 'border-l-4 border-yellow-500'
        ];
        $show_approve = true;
        $show_reject = true;
        $show_renew = false;
        break;
    case 'pay_to_confirm':
        $status_config = [
            'text' => esc_html__('Waiting for user payment', 'listeo_core'),
            'icon' => $svg_icons['clock'],
            'class' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'border' => 'border-l-4 border-yellow-500'
        ];
        $show_approve = false;
        $show_reject = false;
        $show_renew = false;
        $show_cancel = true;
        break;
    case 'confirmed':
        $payment_text = '';
        if ($data->price > 0) {
            if ($_payment_option == "pay_cash") {
                $payment_text = ' - ' . esc_html__('Cash payment', 'listeo_core');
            } else {
                $payment_text = ' - ' . esc_html__('Unpaid', 'listeo_core');
            }
        }
        $status_config = [
            'text' => esc_html__('Approved', 'listeo_core') . $payment_text,
            'icon' => $svg_icons['check-circle'],
            'class' => 'bg-blue-100 text-blue-800 border-blue-200',
            'border' => 'border-l-4 border-blue-500'
        ];
        $show_approve = false;
        $show_reject = false;
        $show_renew = false;
        $show_cancel = true;
        break;
    case 'paid':
        $status_config = [
            'text' => esc_html__('Approved', 'listeo_core') . ' - ' . esc_html__('Paid', 'listeo_core'),
            'icon' => $svg_icons['check-circle'],
            'class' => 'bg-green-100 text-green-800 border-green-200',
            'border' => 'border-l-4 border-green-500'
        ];
        $show_contact = true;
        $show_approve = false;
        $show_renew = false;
        $show_reject = false;
        $show_cancel = true;
        break;
    case 'cancelled':
        $status_config = [
            'text' => esc_html__('Canceled', 'listeo_core'),
            'icon' => $svg_icons['x-circle'],
            'class' => 'bg-red-100 text-red-800 border-red-200',
            'border' => 'border-l-4 border-red-500'
        ];
        $show_approve = false;
        $show_reject = false;
        $show_renew = false;
        $show_delete = true;
        break;
    case 'refund':
        $status_config = [
            'text' => esc_html__('Refunded', 'listeo_core'),
            'icon' => $svg_icons['x-circle'],
            'class' => 'bg-red-100 text-red-800 border-red-200',
            'border' => 'border-l-4 border-red-500'
        ];
        $show_approve = false;
        $show_reject = false;
        $show_renew = false;
        $show_delete = true;
        break;
    case 'expired':
        $status_config = [
            'text' => esc_html__('Expired', 'listeo_core'),
            'icon' => $svg_icons['alert-circle'],
            'class' => 'bg-gray-100 text-gray-800 border-gray-200',
            'border' => 'border-l-4 border-gray-500'
        ];
        $show_approve = false;
        $show_reject = false;
        $show_renew = true;
        $show_delete = true;
        break;
}

// Check for expired payment links (from original template)
if ($data->status != 'paid' && isset($data->order_id) && !empty($data->order_id) && $data->status == 'confirmed') {
    $order = wc_get_order($data->order_id);
    if ($order) {
        $payment_url = $order->get_checkout_payment_url();
        $order_data = $order->get_data();
        $order_status = $order_data['status'];
    }
    if (isset($data->expiring) && $data->expiring != '0000-00-00 00:00:00' && new DateTime() > new DateTime($data->expiring)) {
        $payment_url = false;
        $status_config = [
            'text' => esc_html__('Expired', 'listeo_core'),
            'icon' => $svg_icons['alert-circle'],
            'class' => 'bg-gray-100 text-gray-800 border-gray-200',
            'border' => 'border-l-4 border-gray-500'
        ];
        $show_delete = true;
        $show_approve = false;
        $show_reject = false;
        $show_cancel = false;
        $show_renew = false;
    }
}

// Check if current date has passed the booking dates to disable renew (from original template)
$today = date('Y-m-d H:i:s');
if (
    listeo_core_listing_type_supports($listing_type, 'booking') &&
    ($today > $data->date_start || $today > $data->date_end)
) {
    $show_renew = false;
}

// Guest count string with proper logic from original template
$guest_count_str = '';
$details_guests_output = [];
if (!get_option('listeo_remove_guests')) {
    $has_children = false;
    if (isset($details->children) && $details->children > 0) {
        $has_children = true;
    }

    if (isset($details->adults) && $details->adults > 0 && $has_children == false) {
        $details_guests_output[] = sprintf(_n('%d Guest', '%d Guests', $details->adults, 'listeo_core'), $details->adults);
    }
    if (isset($details->adults) && $details->adults > 0 && $has_children == true) {
        $details_guests_output[] = sprintf(_n('%d Adult', '%d Adults', $details->adults, 'listeo_core'), $details->adults);
    }
    if (isset($details->children) && $details->children > 0) {
        $details_guests_output[] = sprintf(_n('%d Child', '%d Children', $details->children, 'listeo_core'), $details->children);
    }
    if (isset($details->infants) && $details->infants > 0) {
        $details_guests_output[] = sprintf(_n('%d Infant', '%d Infants', $details->infants, 'listeo_core'), $details->infants);
    }
    if (isset($details->animals) && $details->animals > 0) {
        $details_guests_output[] = sprintf(_n('%d Animal', '%d Animals', $details->animals, 'listeo_core'), $details->animals);
    }
    if (isset($details->tickets) && $details->tickets > 0) {
        $details_guests_output[] = sprintf(_n('%d Ticket', '%d Tickets', $details->tickets, 'listeo_core'), $details->tickets);
    }

    // if (!empty($details_guests_output)) {
    //     if (count($details_guests_output) > 1) {
    //         $guest_count_str = implode(', ', $details_guests_output);
    //     } else {
    //         $guest_count_str = implode(' ', $details_guests_output);
    //     }
    // }
}

// Price formatting (commission is already included in $data->price during booking creation)
$price_formatted = '';
if ($data->price) {
    $currency_abbr = get_option('listeo_currency');
    $currency_abbr = apply_filters('vessno_listeo_currency_user_dashboard', $currency_abbr, $data);
    $currency_postion = get_option('listeo_currency_postion');
    $currency_symbol = Listeo_Core_Listing::get_currency_symbol($currency_abbr);
    $decimals = get_option('listeo_number_decimals', 2);
    $price_val = is_numeric($data->price) ? number_format_i18n($data->price, $decimals) : esc_html($data->price);
    $price_formatted = ($currency_postion == 'before') ? $currency_symbol . ' ' . $price_val : $price_val . ' ' . $currency_symbol;
}

// Date formatting
$date_format = get_option('date_format');
// Use Listeo clock format setting instead of WordPress time format
$clock_format = get_option('listeo_clock_format', '12');
$time_format = ($clock_format == '24') ? 'H:i' : 'g:i a';

?>
<!-- Booking Card Start -->
<div class="booking-card <?php echo esc_attr($status_config['border'] ?? ''); ?>" id="booking-list-<?php echo esc_attr($data->ID); ?>">

    <!-- Card Header -->
    <div class="booking-card-header">
        <div class="min-w-0 flex-1 flex items-center">
            <a href="<?php echo get_author_posts_url($data->bookings_author); ?>" class="mr-4 flex-shrink-0"><?php echo get_avatar($data->bookings_author, 40); ?></a>
            <div class="min-w-0 flex-1">
                <h3 class="booking-title">
                    <a href="<?php echo esc_url(get_permalink($listing_id)); ?>"><?php echo esc_html(get_the_title($listing_id)); ?></a>
                </h3>
                <p class="booking-subtitle">
                    <?php printf(esc_html__('Booking #%s', 'listeo_core'), esc_html($data->ID)); ?>
                    <?php if (isset($data->order_id) && $data->order_id): ?>
                        - <?php printf(esc_html__('Order #%s', 'listeo_core'), esc_html($data->order_id)); ?>
                        <?php
                        // Show order billing name if available (from original template)
                        $order = wc_get_order($data->order_id);
                        if ($order) {
                            echo ' ' . esc_html($order->get_billing_first_name()) . ' ' . esc_html($order->get_billing_last_name());
                        }
                        ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php if (!empty($status_config)): ?>
            <span class="booking-status-badge <?php echo esc_attr($status_config['class']); ?>">
                <?php echo $status_config['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                ?>
                <?php echo esc_html($status_config['text']); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Card Content -->
    <div class="booking-content-grid">
        <div class="booking-content-grid-details">
            <!-- Date & Time Section -->
            <div class="booking-section">
                <div class="booking-section-header">
                    <?php echo $svg_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <h4><?php esc_html_e('Booking Date:', 'listeo_core'); ?></h4>
                </div>
                <div class="booking-section-content">
                    <div class="booking-date-range">

                        <?php if (listeo_get_booking_type($data->listing_id) == 'date_range'): ?>
                            <div class="booking-date">
                                <span class="booking-date-label"><?php esc_html_e('From', 'listeo_core'); ?></span>
                                <span class="booking-date-value">
                                    <?php
                                    if ($_rental_timepicker == 'on') {
                                        echo date_i18n($date_format . ' ' . $time_format, strtotime($data->date_start));
                                    } else {
                                        echo date_i18n($date_format, strtotime($data->date_start));
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="booking-date">
                                <span class="booking-date-label"><?php esc_html_e('To', 'listeo_core'); ?></span>
                                <span class="booking-date-value">
                                    <?php
                                    if ($_rental_timepicker == 'on') {
                                        echo date_i18n($date_format . ' ' . $time_format, strtotime($data->date_end));
                                    } else {
                                        echo date_i18n($date_format, strtotime($data->date_end));
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php elseif (listeo_get_booking_type($data->listing_id) == 'single_day'): ?>
                            <div class="booking-date">
                                <span class="booking-date-label"><?php esc_html_e('Date', 'listeo_core'); ?></span>
                                <span class="booking-date-value">
                                    <?php echo date_i18n($date_format, strtotime($data->date_start)); ?> <?php esc_html_e('at', 'listeo_core'); ?>
                                    <span class="booking-time">
                                        <?php
                                        $time_start = date_i18n($time_format, strtotime($data->date_start));
                                        $time_end = date_i18n($time_format, strtotime($data->date_end));
                                        echo esc_html($time_start);
                                        if ($time_start != $time_end) echo ' - ' . $time_end;
                                        ?>
                                    </span>
                                </span>
                            </div>
                        <?php else: // event
                        ?>
                            <div class="booking-date">
                                <span class="booking-date-label"><?php esc_html_e('Event Date', 'listeo_core'); ?></span>
                                <span class="booking-date-value">
                                    <?php
                                    /**
                                     * Filter the event date HTML shown on the owner booking card.
                                     *
                                     * @param string $html    Override HTML. Empty to use default.
                                     * @param object $data    Booking row.
                                     * @param object $details Decoded comment JSON.
                                     */
                                    $bp_event_date = apply_filters( 'listeo_booking_card_event_date', '', $data, $details );
                                    if ( ! empty( $bp_event_date ) ) {
                                        echo wp_kses_post( $bp_event_date );
                                    } else {
                                        // Use the booking record's date_start (the actually booked date)
                                        // instead of the listing's _event_date anchor, which is wrong
                                        // for recurring events.
                                        $booked_ts = ! empty( $data->date_start ) ? strtotime( $data->date_start ) : false;

                                        if ( $booked_ts ) {
                                            echo esc_html( date_i18n( $date_format, $booked_ts ) );

                                            $booked_end_ts = ! empty( $data->date_end ) ? strtotime( $data->date_end ) : false;
                                            if ( $booked_end_ts && wp_date( 'Y-m-d', $booked_end_ts ) !== wp_date( 'Y-m-d', $booked_ts ) ) {
                                                echo ' - ' . esc_html( date_i18n( $date_format, $booked_end_ts ) );
                                            }
                                        } else {
                                            // Fallback to listing post meta for very old bookings.
                                            $meta_value = get_post_meta($listing_id, '_event_date', true);
                                            $meta_value_timestamp = (int) get_post_meta($listing_id, '_event_date_timestamp', true);
                                            if ( ! empty( $meta_value_timestamp ) ) {
                                                $meta_value_date = explode(' ', $meta_value, 2);
                                                echo esc_html( date_i18n($date_format, $meta_value_timestamp) );
                                                if (isset($meta_value_date[1])) {
                                                    $time = str_replace('-', '', $meta_value_date[1]);
                                                    echo esc_html__(' at ', 'listeo_core');
                                                    echo esc_html( date_i18n($time_format, strtotime($time)) );
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Zoom Meeting Section -->
            <?php
            $zoom_client_id = get_option('listeo_zoom_oauth_client_id');
            $zoom_meeting_id = get_booking_meta($data->ID, 'zoom_meeting_id', true);
            if (!empty($zoom_client_id) && !empty($zoom_meeting_id)):
                $zoom_join_url = get_booking_meta($data->ID, 'zoom_join_url', true);
                $zoom_start_url = get_booking_meta($data->ID, 'zoom_start_url', true);
                $zoom_password = get_booking_meta($data->ID, 'zoom_password', true);
            ?>
                <div class="booking-section">
                    <div class="booking-section-header">
                        <?php echo $svg_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <h4><?php esc_html_e('Zoom Meeting:', 'listeo_core'); ?></h4>
                    </div>
                    <div class="booking-section-content">
                        <div class="booking-zoom-info">
                            <div class="booking-detail-item">
                                <strong><?php esc_html_e('Meeting ID:', 'listeo_core'); ?></strong>
                                <?php echo esc_html($zoom_meeting_id); ?>
                            </div>
                            <?php if (!empty($zoom_password)): ?>
                                <div class="booking-detail-item">
                                    <strong><?php esc_html_e('Password:', 'listeo_core'); ?></strong>
                                    <?php echo esc_html($zoom_password); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($zoom_start_url)): ?>
                                <div class="booking-detail-item">
                                    <a href="<?php echo esc_url($zoom_start_url); ?>" target="_blank" class="booking-btn booking-btn-primary" style="display: inline-block; margin-top: 10px;">
                                        <?php echo $svg_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php esc_html_e('Start Zoom Meeting (Host)', 'listeo_core'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Client Info Section -->
            <?php if ($show_contact): ?>
                <div class="booking-section">
                    <div class="booking-section-header">
                        <?php echo $svg_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                        <h4><?php esc_html_e('Client:', 'listeo_core'); ?></h4>
                    </div>
                    <div class="booking-section-content">
                        <div class="booking-guest-info">
                            <?php if (isset($details->first_name) || isset($details->last_name)): ?>
                                <div class="booking-guest-name">
                                    <a href="<?php echo get_author_posts_url($data->bookings_author); ?>">
                                        <?php if (isset($details->first_name)) echo esc_html(stripslashes($details->first_name)); ?>
                                        <?php if (isset($details->last_name)) echo ' ' . esc_html(stripslashes($details->last_name)); ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($details->email)): ?>
                                <div class="booking-contact">
                                    <?php echo $svg_icons['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                    ?>
                                    <a href="mailto:<?php echo esc_attr($details->email); ?>" class="booking-contact-link"><?php echo esc_html($details->email); ?></a>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($details->phone) && !empty(trim($details->phone))): ?>
                                <div class="booking-contact">
                                    <?php echo $svg_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                    ?>
                                    <a href="tel:<?php echo esc_attr($details->phone); ?>" class="booking-contact-link"><?php echo esc_html($details->phone); ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Client Address Section -->
            <?php if ($show_contact && isset($details->billing_address_1) && !empty($details->billing_address_1)): ?>
                <div class="booking-section">
                    <div class="booking-section-header">
                        <?php echo $svg_icons['home']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                        <h4><?php esc_html_e('Address:', 'listeo_core'); ?></h4>
                    </div>
                    <div class="booking-section-content">
                        <div class="booking-address-info">
                            <?php if (isset($details->billing_address_1)): ?>
                                <div><?php echo esc_html(stripslashes($details->billing_address_1)); ?></div>
                            <?php endif; ?>
                            <?php if (isset($details->billing_postcode)): ?>
                                <div><?php echo esc_html(stripslashes($details->billing_postcode)); ?></div>
                            <?php endif; ?>
                            <?php if (isset($details->billing_city)): ?>
                                <div><?php echo esc_html(stripslashes($details->billing_city)); ?></div>
                            <?php endif; ?>
                            <?php if (isset($details->billing_country)): ?>
                                <div><?php echo esc_html(stripslashes($details->billing_country)); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>


        <div class="booking-content-grid-details">
            <?php
            /** @see listeo_booking_card_after_date hook in content-user-booking.php */
            do_action( 'listeo_booking_card_after_date', $data, $details, $svg_icons );
            ?>
            <!-- Guest Info & Booking Details Section -->
            <?php if (!empty($details_guests_output)): ?>
                <div class="booking-section">
                    <div class="booking-section-header">
                        <?php echo $svg_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                        <h4><?php esc_html_e('Booking Details:', 'listeo_core'); ?></h4>
                    </div>
                    <div class="booking-section-content">
                        <div class="booking-guest-info">
                            <?php foreach ($details_guests_output as $key => $value) { ?>
                                <div class="booking-guest-count"><?php echo esc_html($value); ?></div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Details Grid Section -->
            <div class="booking-section">
                <?php if ($price_formatted): ?>
                    <div class="booking-detail-item">
                        <div class="booking-section-header">
                            <?php echo $svg_icons['dollar-sign']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                            ?>
                            <h4><?php esc_html_e('Price:', 'listeo_core'); ?></h4>
                        </div>
                        <p class="booking-price"><?php echo esc_html($price_formatted); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Extra Services Section -->
        <?php if (isset($details->service) && !empty($details->service)): ?>
            <div class="booking-section">
                <div class="booking-section-header">
                    <?php echo $svg_icons['settings']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <h4><?php esc_html_e('Extra Services:', 'listeo_core'); ?></h4>
                </div>
                <div class="booking-section-content">
                    <div class="booking-extra-services">
                        <?php echo listeo_get_extra_services_html($details->service); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Custom Booking Fields Section -->
        <?php
        $fields = get_option("listeo_{$listing_type}_booking_fields");
        if ($fields): ?>
            <?php
            $has_custom_fields = false;
            ob_start();
            foreach ($fields as $field): ?>
                <?php if ($field['type'] == 'header') continue; ?>
                <?php $meta = get_booking_meta($data->ID, $field['id']); ?>
                <?php if (!empty($meta)):
                    $has_custom_fields = true;
                ?>
                    <div class="booking-detail-item">
                        <div class="booking-section-header">
                            <h4><?php echo esc_html($field['name']); ?></h4>
                        </div>
                        <div class="booking-section-content">
                            <div class="booking-custom-field <?php if ($field['type'] == 'checkbox') echo 'checkboxed'; ?>">
                                <?php
                                if (is_array($meta)) {
                                    $output = [];
                                    $i = 0;
                                    $last = count($meta);
                                    foreach ($meta as $key) {
                                        $i++;
                                        $output[] = esc_html($field['options'][$key]);
                                        if ($i >= 0 && $i < $last) $output[] = ", ";
                                    }
                                    echo implode('', $output);
                                } else {
                                    switch ($field['type']) {
                                        case 'file':
                                            echo '<a href="' . esc_url($meta) . '" target="_blank">' . esc_html__('Download', 'listeo_core') . ' ' . wp_basename($meta) . '</a>';
                                            break;
                                        case 'radio':
                                        case 'select':
                                            echo esc_html($field['options'][$meta]);
                                            break;
                                        default:
                                            echo esc_html($meta);
                                            break;
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php
            $custom_fields_html = ob_get_clean();
            if ($has_custom_fields): ?>
                <div class="booking-section">
                    <div class="booking-details-grid">
                        <?php echo $custom_fields_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Client Message Section -->
        <?php if (isset($details->message) && !empty($details->message)): ?>
            <div class="booking-section">
                <div class="booking-section-header">
                    <?php echo $svg_icons['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <h4><?php esc_html_e('Message:', 'listeo_core'); ?></h4>
                </div>
                <div class="booking-section-content">
                    <div class="booking-message"><?php echo wpautop(esc_html(Listeo_Core_Messages::filter_contact_info(stripslashes($details->message)))); ?></div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Card Footer -->
    <div class="booking-card-footer">
        <div class="booking-meta">
            <span><?php esc_html_e('Request sent:', 'listeo_core'); ?>
                <?php echo date_i18n($date_format, strtotime($data->created)); ?>
                <?php
                $date_created = explode(' ', $data->created);
                if (isset($date_created[1])) {
                    echo ' ' . esc_html__('at', 'listeo_core') . ' ' . date_i18n($time_format, strtotime($date_created[1]));
                }
                ?>
            </span>
            <?php if (isset($data->expiring) && $data->expiring != '0000-00-00 00:00:00' && $data->expiring != $data->created): ?>
                <span class="booking-expires">
                    <?php echo $svg_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Payment due:', 'listeo_core'); ?>
                    <?php echo date_i18n($date_format, strtotime($data->expiring)); ?>
                    <?php
                    $date_expiring = explode(' ', $data->expiring);
                    if (isset($date_expiring[1])) {
                        echo ' ' . esc_html__('at', 'listeo_core') . ' ' . date_i18n($time_format, strtotime($date_expiring[1]));
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="booking-actions">
            <?php if (get_option('listeo_messages_page')): ?>
                <a href="#small-dialog" data-recipient="<?php echo esc_attr($data->bookings_author); ?>" data-booking_id="booking_<?php echo esc_attr($data->ID); ?>" class="booking-btn booking-message booking-btn-secondary popup-with-zoom-anim">
                    <?php echo $svg_icons['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Send Message', 'listeo_core'); ?>
                </a>
            <?php endif; ?>

            <?php if ($data->status != 'paid' && ($payment_method == 'cod' || $payment_method == 'cheque' || $_payment_option == 'pay_maybe')): ?>
                <a href="#" class="booking-btn booking-btn-info mark-as-paid" data-booking_id="<?php echo esc_attr($data->ID); ?>">
                    <?php echo $svg_icons['check-circle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Confirm Payment', 'listeo_core'); ?>
                </a>
            <?php endif; ?>

            <?php if ($show_reject): ?>
                <a href="#" class="booking-btn booking-btn-danger reject" data-booking_id="<?php echo esc_attr($data->ID); ?>">
                    <?php echo $svg_icons['x-circle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Reject', 'listeo_core'); ?>
                </a>
            <?php endif; ?>

            <?php if ($show_cancel): ?>
                <a href="#" class="booking-btn booking-btn-danger cancel" data-booking_id="<?php echo esc_attr($data->ID); ?>">
                    <?php echo $svg_icons['x-circle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Cancel', 'listeo_core'); ?>
                </a>
            <?php endif; ?>

            <?php if ($show_delete): ?>
                <a href="#" class="booking-btn booking-btn-danger delete" data-booking_id="<?php echo esc_attr($data->ID); ?>">
                    <?php echo $svg_icons['trash']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Delete', 'listeo_core'); ?>
                </a>
            <?php endif; ?>

            <?php if ($show_renew): ?>
                <a href="#" class="booking-btn booking-btn-secondary renew_booking" data-booking_id="<?php echo esc_attr($data->ID); ?>">
                    <?php echo $svg_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Renew', 'listeo_core'); ?>
                </a>
            <?php endif; ?>

            <?php if ($show_approve): ?>
                <a href="#" class="booking-btn booking-btn-primary approve" data-booking_id="<?php echo esc_attr($data->ID); ?>">
                    <?php echo $svg_icons['check-circle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                    <?php esc_html_e('Approve', 'listeo_core'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Booking Card End -->