<?php

/**
 * The template for displaying author archive pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package listeo
 */

/**
 * Display custom user fields that are enabled in Listeo Owner Widget settings
 * Only shows fields that admin has enabled via widget checkboxes
 */
function display_author_custom_fields($user_id) {
    $custom_fields = get_option('listeo_owner_fields', array());
    if (empty($custom_fields) || !is_array($custom_fields)) {
        return;
    }

    // Get widget settings to check which fields are enabled
    $widget_settings = get_widget_settings_for_owner_widget();
    if (!$widget_settings) {
        return;
    }

    // Define built-in fields that are already displayed elsewhere
    $excluded_fields = array(
        'phone', 'email', 'description', 'user_description', 'bio',
        'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'whatsapp', 'skype', 'tiktok', '_telegram',
        'telegram', 'full_phone'
    );

    $fields_to_display = array();
    
    // Check which custom fields are enabled AND have data
    foreach ($custom_fields as $field_key => $field_data) {
        if (isset($field_data['type']) && $field_data['type'] !== 'header') {
            $field_id = isset($field_data['id']) ? $field_data['id'] : $field_key;
            
            // Skip fields that are already handled
            if (in_array($field_id, $excluded_fields)) {
                continue;
            }

            // Check if field is enabled in widget settings (with custom_ prefix)
            $widget_field_key = 'custom_' . $field_id;
            if (!isset($widget_settings[$widget_field_key]) || $widget_settings[$widget_field_key] !== 'on') {
                continue; // Field not enabled in widget settings
            }

            // Get field value from user meta
            $field_value = get_user_meta($user_id, $field_id, true);
            
            // Only include if field has a value
            if (!empty($field_value) && $field_value !== '') {
                $fields_to_display[$field_id] = array(
                    'data' => $field_data,
                    'value' => $field_value
                );
            }
        }
    }

    // If we have fields to display, render them
    if (!empty($fields_to_display)) {
        echo '<div class="contact-section-modern">';
        echo '<div class="section-title-modern">' . esc_html__('Additional Info', 'listeo') . '</div>';
        echo '<ul class="listing-details-sidebar custom-user-fields">';
        
        foreach ($fields_to_display as $field_id => $field_info) {
            $field_data = $field_info['data'];
            $field_value = $field_info['value'];
            
            $field_name = isset($field_data['name']) ? strip_tags($field_data['name']) : ucfirst(str_replace('_', ' ', $field_id));
            $field_type = isset($field_data['type']) ? $field_data['type'] : 'text';
            
            echo '<li class="custom-field-' . esc_attr($field_id) . '">';
            
            // Add icon if specified
            if (isset($field_data['icon']) && !empty($field_data['icon'])) {
                echo '<i class="' . esc_attr($field_data['icon']) . '"></i> ';
            }
            
            // Render field based on type
            render_author_field_by_type($field_name, $field_value, $field_type, $field_data, $field_id);
            
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
}

/**
 * Get Owner Widget settings from the first active instance
 */
function get_widget_settings_for_owner_widget() {
    $all_widgets = get_option('widget_widget_listing_owner', array());
    
    if (empty($all_widgets)) {
        return get_default_owner_widget_settings();
    }

    // Find the first active widget instance
    foreach ($all_widgets as $instance) {
        if (is_array($instance) && !empty($instance)) {
            return $instance;
        }
    }

    return get_default_owner_widget_settings();
}

/**
 * Get default Owner Widget settings with all custom fields enabled by default
 */
function get_default_owner_widget_settings() {
    $settings = array();
    
    $custom_fields = get_option('listeo_owner_fields', array());
    if (!empty($custom_fields) && is_array($custom_fields)) {
        $excluded_fields = array(
            'phone', 'email', 'description', 'user_description', 'bio',
            'twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'whatsapp', 'skype', 'tiktok', 'telegram'
        );
        
        foreach ($custom_fields as $field_key => $field_data) {
            if (isset($field_data['type']) && $field_data['type'] !== 'header') {
                $field_id = isset($field_data['id']) ? $field_data['id'] : $field_key;
                
                if (!in_array($field_id, $excluded_fields)) {
                    $settings['custom_' . $field_id] = 'on';
                }
            }
        }
    }
    
    return $settings;
}

/**
 * Render individual field based on its type
 */
function render_author_field_by_type($field_name, $field_value, $field_type, $field_data, $field_id) {
    // Handle array fields (multi-select, multi-checkbox, etc.)
    if (is_array($field_value) && !empty($field_value)) {
        render_author_array_field($field_name, $field_value, $field_type, $field_data, $field_id);
        return;
    }
    
    switch ($field_type) {
        case 'url':
            if (filter_var($field_value, FILTER_VALIDATE_URL)) {
                echo '<strong>' . esc_html($field_name) . ':</strong> <a href="' . esc_url($field_value) . '" target="_blank">' . esc_html($field_value) . '</a>';
            } else {
                echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($field_value);
            }
            break;
            
        case 'email':
            if (filter_var($field_value, FILTER_VALIDATE_EMAIL)) {
                echo '<strong>' . esc_html($field_name) . ':</strong> <a href="mailto:' . esc_attr($field_value) . '">' . esc_html($field_value) . '</a>';
            } else {
                echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($field_value);
            }
            break;
            
        case 'textarea':
            echo '<strong>' . esc_html($field_name) . ':</strong><br>' . wpautop(esc_html($field_value));
            break;
            
        case 'checkbox':
            // Handle single checkbox
            if ($field_value === 'on' || $field_value === '1' || $field_value === true) {
                $display_value = esc_html__('Yes', 'listeo');
            } else {
                // Check if there are options for this checkbox
                $has_options = isset($field_data['options']) && is_array($field_data['options']);
                if ($has_options && isset($field_data['options'][$field_value])) {
                    $display_value = $field_data['options'][$field_value];
                } else {
                    $display_value = esc_html__('No', 'listeo');
                }
            }
            echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($display_value);
            break;
            
        case 'select':
        case 'radio':
            // Handle select and radio fields with options
            $has_options = isset($field_data['options']) && is_array($field_data['options']);
            if ($has_options && isset($field_data['options'][$field_value])) {
                $display_value = $field_data['options'][$field_value];
            } else {
                $display_value = $field_value;
            }
            echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($display_value);
            break;
            
        default:
            // For all other field types, check if there are options to map the value
            $has_options = isset($field_data['options']) && is_array($field_data['options']);
            if ($has_options && isset($field_data['options'][$field_value])) {
                $display_value = $field_data['options'][$field_value];
            } else {
                $display_value = $field_value;
            }
            echo '<strong>' . esc_html($field_name) . ':</strong> ' . esc_html($display_value);
            break;
    }
}

/**
 * Render array fields (multi-select, multi-checkbox, etc.)
 */
function render_author_array_field($field_name, $field_value, $field_type, $field_data, $field_id) {
    $display_items = array();
    $has_options = isset($field_data['options']) && is_array($field_data['options']);
    
    foreach ($field_value as $key => $value) {
        if (empty($value)) {
            continue;
        }
        
        if (($field_type === 'multicheck' || $field_type === 'multicheck_split' || $field_type === 'multi-checkbox') && $value === 'on') {
            $display_items[] = esc_html__('Yes', 'listeo');
            continue;
        }
        
        if ($has_options && isset($field_data['options'][$value])) {
            $display_items[] = esc_html($field_data['options'][$value]);
        } elseif ($has_options && isset($field_data['options'][$key])) {
            $display_items[] = esc_html($field_data['options'][$key]);
        } else {
            $display_items[] = esc_html($value);
        }
    }
    
    if (!empty($display_items)) {
        echo '<strong>' . esc_html($field_name) . ':</strong> ';
        
        if (count($display_items) > 3) {
            echo '<ul><li>' . implode('</li><li>', $display_items) . '</li></ul>';
        } else {
            echo implode(', ', $display_items);
        }
    }
}

$full_width_header = get_option('listeo_full_width_header');

if ($full_width_header == 'enable') {
	get_header('fullwidth');
} else {
	get_header();
}

$template_loader = new Listeo_Core_Template_Loader;

$user = (get_query_var('author_name')) ? get_user_by('slug', get_query_var('author_name')) : get_userdata(get_query_var('author'));
$user_info = get_userdata($user->ID);
$email = $user_info->user_email;

// Get additional user meta data
$phone = get_user_meta($user->ID, 'phone', true);
$website = get_the_author_meta('url', $user->ID);
$twitter = get_user_meta($user->ID, 'twitter', true);
$facebook = get_user_meta($user->ID, 'facebook', true);
$linkedin = get_user_meta($user->ID, 'linkedin', true);
$instagram = get_user_meta($user->ID, 'instagram', true);
$youtube = get_user_meta($user->ID, 'youtube', true);
$whatsapp = get_user_meta($user->ID, 'whatsapp', true);
$tiktok = get_user_meta($user->ID, 'tiktok', true);
$telegram = get_user_meta($user->ID, '_telegram', true);
$verified = get_user_meta($user->ID, 'listeo_verified_user', true);
$member_since = get_userdata($user->ID)->user_registered;

// Calculate statistics
$user_listings = get_posts(array(
    'author' => $user->ID,
    'post_type' => 'listing',
    'post_status' => 'publish',
    'numberposts' => -1
));
$total_listings = count($user_listings);

$active_listings = get_posts(array(
    'author' => $user->ID,
    'post_type' => 'listing',
    'post_status' => 'publish',
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_listing_expires',
            'value' => date('Y-m-d'),
            'compare' => '>='
        ),
        array(
            'key' => '_listing_expires',
            'compare' => 'NOT EXISTS'
        )
    ),
    'numberposts' => -1
));
$active_listings_count = count($active_listings);

// Fallback: if no active listings found but total listings exist, assume all are active
if ($active_listings_count == 0 && $total_listings > 0) {
    $active_listings_count = $total_listings;
}

// Calculate reviews and ratings - reviews RECEIVED on user's listings
$user_listing_ids = wp_list_pluck($user_listings, 'ID');
$total_visitor_reviews = array();
$review_total = 0;
$review_count = 0;
$rating = 0;

// Only proceed if user has listings
if (!empty($user_listing_ids)) {
    $total_visitor_reviews_args = array(
        'post__in'    	=> $user_listing_ids,
        'parent'      	=> 0,
        'status' 	  	=> 'approve',
        'post_type'   	=> 'listing',
        'orderby' 		=> 'post_date',
        'order' 		=> 'DESC',
        'meta_key'      => 'listeo-rating', // Only get comments that have ratings
        'meta_compare'  => 'EXISTS'
    );
    add_filter('comments_clauses', 'listeo_top_comments_only');
    $total_visitor_reviews = get_comments($total_visitor_reviews_args);
    remove_filter('comments_clauses', 'listeo_top_comments_only');

    foreach ($total_visitor_reviews as $review) {
        $rating_value = get_comment_meta($review->comment_ID, 'listeo-rating', true);
        if ($rating_value && is_numeric($rating_value) && $rating_value > 0) {
            $review_total += (float) $rating_value;
            $review_count++;
        }
    }
    $rating = $review_count > 0 ? $review_total / $review_count : 0;
}

// Visibility settings
$visibility_setting = get_option('listeo_user_contact_details_visibility');
$show_details = false;

if ($visibility_setting == 'show_all') {
    $show_details = true;
} elseif ($visibility_setting == 'show_logged' && is_user_logged_in()) {
    $show_details = true;
} elseif (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    if ($current_user->ID == $user->ID) {
        $show_details = true;
    }
}

$show_contact = !get_option('listeo_lock_contact_info_to_paid_bookings');
if (!$show_contact) {
    $show_details = false;
}

// Enqueue author page specific CSS
wp_enqueue_style('author-page-css', get_template_directory_uri() . '/css/author-page.css', array(), '1.0.0');
?>

<!-- Modern Author Profile Page -->
<div class="author-profile-modern">
    <div class="container">
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-lg-4 col-md-5">
                <div class="profile-sidebar-modern">
                    <!-- Profile Header -->
                    <div class="profile-header-modern">
                        <div class="profile-avatar-container">
                            <div class="profile-avatar-modern">
                                <?php echo get_avatar($user->ID, 100); ?>
                            </div>
                            <?php if ($verified || $user_info->user_verified) : ?>
                                <div class="verified-badge-modern">
                                    <i class="fa fa-check"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-name-modern"><?php echo esc_html($user_info->display_name); ?></div>
                        
                        <?php if ($rating > 0) : ?>
                            <div class="star-rating" data-rating="<?php echo esc_attr(round($rating, 2)); ?>">
                                <div class="rating-counter">
                                    <strong><?php printf("%0.1f", $rating); ?></strong>
                                    (<?php echo $review_count; ?> <?php echo _n('review', 'reviews', $review_count, 'listeo'); ?>)
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="member-since-modern">
                            <i class="sl sl-icon-calender"></i>
                            <?php esc_html_e('Member since', 'listeo'); ?> <?php echo date('Y', strtotime($member_since)); ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons-modern">
                        <?php if (get_option('listeo_messages_page')) : ?>
                            <?php if (is_user_logged_in()) : ?>
                                <a href="#small-dialog" class="btn-primary-modern popup-with-zoom-anim">
                                    <i class="fa fa-envelope"></i>
                                    <?php esc_html_e('Send Message', 'listeo'); ?>
                                </a>
                            <?php else : ?>
                                <a href="#sign-in-dialog" class="btn-primary-modern popup-with-zoom-anim">
                                    <i class="fa fa-envelope"></i>
                                    <?php esc_html_e('Send Message', 'listeo'); ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($show_details && $whatsapp) : ?>
                            <a href="<?php if (strpos($whatsapp, 'http') === 0) { echo esc_url($whatsapp); } else { echo 'https://api.whatsapp.com/send?phone=' . esc_attr($whatsapp) . '&text=Hello'; } ?>" target="_blank" class="send-message-to-owner button whatsapp-profile">
                                <i class="fa fa-whatsapp"></i> <?php esc_html_e('Chat via WhatsApp', 'listeo'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Information -->
                    <div class="contact-section-modern">
                        <div class="section-title-modern"><?php esc_html_e('Contact Information', 'listeo'); ?></div>
                        
                        <?php if ($show_details) : ?>
                            <?php if ($email) : ?>
                                <div class="contact-item-modern">
                                    <i class="fa fa-envelope"></i>
                                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($phone) : ?>
                                <div class="contact-item-modern">
                                    <i class="fa fa-phone"></i>
                                    <span><?php echo esc_html($phone); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($website) : ?>
                                <div class="contact-item-modern">
                                    <i class="fa fa-globe"></i>
                                    <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($visibility_setting != 'hide_all') : ?>
                            <div class="contact-locked-modern">
                                <i class="fa fa-lock"></i>
                                <p><?php esc_html_e('Please sign in to see contact details.', 'listeo'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Social Media -->
                    <?php if ($show_details && ($twitter || $facebook || $linkedin || $instagram || $youtube || $whatsapp || $tiktok || $telegram)) : ?>
                        <div class="social-section-modern">
                            <div class="section-title-modern"><?php esc_html_e('Social Media', 'listeo'); ?></div>
                            
                            <ul class="listing-details-sidebar social-profiles">
                                <?php if ($twitter) : ?>
                                    <li><a href="<?php echo esc_url($twitter); ?>" target="_blank" class="twitter-profile"><i class="fa-brands fa-x-twitter"></i> <?php esc_html_e('X.com', 'listeo'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if ($facebook) : ?>
                                    <li><a href="<?php echo esc_url($facebook); ?>" target="_blank" class="facebook-profile"><i class="fa fa-facebook-square"></i> <?php esc_html_e('Facebook', 'listeo'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if ($instagram) : ?>
                                    <li><a href="<?php echo esc_url($instagram); ?>" target="_blank" class="instagram-profile"><i class="fa fa-instagram"></i> <?php esc_html_e('Instagram', 'listeo'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if ($linkedin) : ?>
                                    <li><a href="<?php echo esc_url($linkedin); ?>" target="_blank" class="linkedin-profile"><i class="fa fa-linkedin"></i> <?php esc_html_e('LinkedIn', 'listeo'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if ($youtube) : ?>
                                    <li><a href="<?php echo esc_url($youtube); ?>" target="_blank" class="youtube-profile"><i class="fa fa-youtube"></i> <?php esc_html_e('YouTube', 'listeo'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if ($whatsapp) : ?>
                                    <li><a href="<?php if (strpos($whatsapp, 'http') === 0) { echo esc_url($whatsapp); } else { echo "https://wa.me/" . $whatsapp; } ?>" target="_blank" class="whatsapp-profile"><i class="fa fa-whatsapp"></i> <?php esc_html_e('WhatsApp', 'listeo'); ?></a></li>
                                <?php endif; ?>
                                
                                <?php if ($tiktok) : ?>
                                    <li><a href="<?php if (strpos($tiktok, 'http') === 0) { echo esc_url($tiktok); } else { echo "https://www.tiktok.com/@" . esc_attr($tiktok); } ?>" target="_blank" class="tiktok-profile"><i class="fa-brands fa-tiktok"></i> <?php esc_html_e('TikTok', 'listeo'); ?></a></li>
                                <?php endif; ?>

                                <?php if ($telegram) : ?>
                                    <li><a href="<?php if (strpos($telegram, 'http') === 0) { echo esc_url($telegram); } else { echo "https://t.me/" . esc_attr($telegram); } ?>" target="_blank" class="telegram-profile"><i class="fa-brands fa-telegram"></i> <?php esc_html_e('Telegram', 'listeo'); ?></a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Display custom fields from Listeo Forms & Fields Editor (only if enabled in widget settings)
                    if ($show_details) {
                        display_author_custom_fields($user->ID);
                    }
                    ?>

                    <?php
                    // Get extended statistics from database
                    global $wpdb;
                    
                    // 1. Booking Statistics
                    $booking_stats = $wpdb->get_row($wpdb->prepare("
                        SELECT 
                            COUNT(*) as total_bookings,
                            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bookings,
                            COUNT(DISTINCT bookings_author) as unique_customers
                        FROM {$wpdb->prefix}bookings_calendar 
                        WHERE owner_id = %d
                    ", $user->ID));
                    
                    // Get total views from user meta (maintained automatically by Listeo)
                    $total_views = intval(get_user_meta($user->ID, 'listeo_total_listing_views', true));
                    ?>

                    <!-- Professional Stats -->
                    <?php
                    // Check if statistics section is enabled
                    if (get_option('listeo_author_disable_statistics', 'enable') !== 'disable') :
                        // Get enabled statistics items
                        $enabled_stats = get_option('listeo_author_statistics_items', array( 'active_listings', 'reviews', 'rating', 'total_bookings', 'guests_hosted', 'total_views' ));

                        // Check if any statistics have data
                        $has_stats = false;
                        if (in_array('active_listings', $enabled_stats) && $active_listings_count > 0) $has_stats = true;
                        if (in_array('reviews', $enabled_stats) && $review_count > 0) $has_stats = true;
                        if (in_array('rating', $enabled_stats) && $rating > 0) $has_stats = true;
                        if (in_array('total_bookings', $enabled_stats) && $booking_stats && $booking_stats->total_bookings > 0) $has_stats = true;
                        if (in_array('guests_hosted', $enabled_stats) && $booking_stats && $booking_stats->unique_customers > 0) $has_stats = true;
                        if (in_array('total_views', $enabled_stats) && $total_views > 0) $has_stats = true;

                        if ($has_stats) :
                    ?>
                    <div class="stats-section-modern">
                        <div class="section-title-modern"><?php esc_html_e('Statistics', 'listeo'); ?></div>

                        <div class="stats-grid-modern">
                            <?php if (in_array('active_listings', $enabled_stats) && $active_listings_count > 0) : ?>
                            <div class="stat-item-modern">
                                <i class="sl sl-icon-check"></i>
                                <div class="stat-number"><?php echo $active_listings_count; ?></div>
                                <div class="stat-label"><?php esc_html_e('Active Listings', 'listeo'); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('reviews', $enabled_stats) && $review_count > 0) : ?>
                            <div class="stat-item-modern">
                                <i class="sl sl-icon-speech"></i>
                                <div class="stat-number"><?php echo $review_count; ?></div>
                                <div class="stat-label"><?php esc_html_e('Reviews', 'listeo'); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('rating', $enabled_stats) && $rating > 0) : ?>
                            <div class="stat-item-modern">
                                <i class="sl sl-icon-star"></i>
                                <div class="stat-number"><?php echo number_format($rating, 1); ?></div>
                                <div class="stat-label"><?php esc_html_e('Rating', 'listeo'); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('total_bookings', $enabled_stats) && $booking_stats && $booking_stats->total_bookings > 0) : ?>
                            <div class="stat-item-modern">
                                <i class="sl sl-icon-notebook"></i>
                                <div class="stat-number"><?php echo intval($booking_stats->total_bookings); ?></div>
                                <div class="stat-label"><?php esc_html_e('Total Bookings', 'listeo'); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('guests_hosted', $enabled_stats) && $booking_stats && $booking_stats->unique_customers > 0) : ?>
                            <div class="stat-item-modern">
                                <i class="sl sl-icon-user"></i>
                                <div class="stat-number"><?php echo intval($booking_stats->unique_customers); ?></div>
                                <div class="stat-label"><?php esc_html_e('Guests Hosted', 'listeo'); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('total_views', $enabled_stats) && $total_views > 0) : ?>
                            <div class="stat-item-modern">
                                <i class="sl sl-icon-eye"></i>
                                <div class="stat-number"><?php echo number_format($total_views); ?></div>
                                <div class="stat-label"><?php esc_html_e('Total Views', 'listeo'); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                        endif; // has_stats
                    endif; // statistics enabled
                    ?>
                    
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8 col-md-7">
                <div class="main-content-modern">
                    <!-- About Section -->
                    <?php
                    $authorDesc = get_the_author_meta('description', $user->ID);
                    if ($authorDesc && get_option('listeo_author_disable_about_section', 'enable') !== 'disable') : ?>
                        <div class="content-section-modern">
                            <div class="section-header-modern"><?php esc_html_e('About Me', 'listeo'); ?></div>
                            <div class="about-text-modern">
                                <?php echo wpautop($authorDesc); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Overall Rating Section -->
                    <?php if (!empty($total_visitor_reviews) && $rating > 0 && get_option('listeo_author_disable_overall_rating', 'enable') !== 'disable') : ?>
                        <div class="content-section-modern">
                            <div class="section-header-modern"><?php esc_html_e('Overall Rating', 'listeo'); ?></div>

                            <!-- Rating Overview using theme's design -->
                            <div class="rating-overview">
                                <div class="rating-overview-box">
                                    <span class="rating-overview-box-total"><?php printf("%0.1f", $rating); ?></span>
                                    <span class="rating-overview-box-percent"><?php esc_html_e('out of 5.0', 'listeo'); ?></span>
                                    <div class="star-rating" data-rating="<?php echo esc_attr(round($rating, 2)); ?>"></div>
                                </div>

                                <div class="rating-bars">
                                    <?php
                                    $criteria_fields = listeo_get_reviews_criteria();

                                    // Calculate average ratings for each criteria across all user's listings
                                    foreach ($criteria_fields as $key => $value) {
                                        $total_criteria_rating = 0;
                                        $criteria_count = 0;

                                        foreach ($user_listings as $listing) {
                                            $listing_criteria_rating = get_post_meta($listing->ID, $key . '-avg', true);
                                            if ($listing_criteria_rating) {
                                                $listing_review_count = get_comments_number($listing->ID);
                                                $total_criteria_rating += $listing_criteria_rating * $listing_review_count;
                                                $criteria_count += $listing_review_count;
                                            }
                                        }

                                        if ($criteria_count > 0) {
                                            $avg_criteria_rating = $total_criteria_rating / $criteria_count;
                                            ?>
                                            <div class="rating-bars-item">
                                                <span class="rating-bars-name"><?php echo stripslashes(esc_html($value['label'])) ?>
                                                    <?php if (isset($value['tooltip']) && !empty($value['tooltip'])) : ?><i class="tip" data-tip-content="<?php echo stripslashes(esc_html($value['tooltip'])); ?>"></i> <?php endif; ?></span>
                                                <span class="rating-bars-inner">
                                                    <span class="rating-bars-rating" data-rating="<?php echo esc_attr($avg_criteria_rating); ?>">
                                                        <span class="rating-bars-rating-inner"></span>
                                                    </span>
                                                    <strong><?php printf("%0.1f", $avg_criteria_rating); ?></strong>
                                                </span>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- Rating Overview / End -->
                        </div>
                    <?php endif; ?>

                    <!-- Dokan Store Section -->
                    <?php if (class_exists('WeDevs_Dokan') && get_option('listeo_author_disable_store_section', 'enable') !== 'disable') {
                        $is_vendor = get_user_meta($user->ID, 'dokan_enable_selling', true);
                        if ($is_vendor == 'yes') {
                            $vendor_id = $user->ID;
                            $vendor = dokan()->vendor->get($vendor_id);
                            $store_name = $vendor->get_shop_name();
                            $store_url = $vendor->get_shop_url();
                            
                            // Check if vendor has products before showing store section
                            $args = array(
                                'post_type' => 'product',
                                'post_status' => 'publish',
                                'author' => $vendor_id,
                                'orderby' => 'date',
                                'order' => 'DESC',
                                'limit' => 6,
                               // 'type' => 'simple'  // Only simple products
                            );

                            // Exclude listing booking and listing package products (like in store template)
                            $args['tax_query'] = array(
                                array(
                                    'taxonomy' => 'product_cat',
                                    'field' => 'slug',
                                    'terms' => array('listeo-booking'),
                                    'operator' => 'NOT IN'
                                ),
                                array(
                                    'taxonomy' => 'product_type',
                                    'field' => 'slug',
                                    'terms' => array('listing_package'),
                                    'operator' => 'NOT IN'
                                )
                            );

                            $products = wc_get_products($args);
                            
                            // Only show store section if user has products
                            if ($products) : ?>
                            <div class="content-section-modern">
                                <div class="section-header-modern store-sct"><?php esc_html_e('Store Information', 'listeo'); ?>
									<div class="store-info-modern">
										<a href="<?php echo esc_url($store_url); ?>" class="btn-primary-modern" target="_blank">
											<i class="fa fa-store"></i>
											<?php esc_html_e('Visit Store', 'listeo'); ?>
										</a>
									</div>
							</div>

                                <div class="">
                                    <!-- Using exact same carousel structure as single-listing-store.php -->
                                    <div class="simple-slick-carousel listeo-products-slider dots-nav">
                                            <?php foreach ($products as $product) : ?>
                                                <div class="fw-carousel-item">
                                                    <div <?php post_class('', $product->get_id()); ?>>
                                                        <div class="mediaholder">
                                                            <a href="<?php echo get_permalink($product->get_id()); ?>">
                                                                <?php
                                                                $size = 'listeo_core-avatar';
                                                                $image_size = apply_filters('single_product_archive_thumbnail_size', $size);
                                                                echo $product->get_image($image_size);
                                                                ?>
                                                            </a>
                                                            <?php 
                                                            $link = $product->add_to_cart_url();
                                                            $label = apply_filters('add_to_cart_text', esc_html__('Add to cart', 'listeo'));
                                                            ?>
                                                            <a href="<?php echo esc_url($link); ?>" class="button">
                                                                <i class="fa fa-shopping-cart"></i> <?php echo esc_html($label); ?>
                                                            </a>
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
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php wp_reset_postdata(); ?>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    } ?>

                    <!-- Listings Section -----------------------------------------------
                         Gated by `listeo_author_show_listings` (Listeo Core →
                         Settings → General → "Author Page"). Defaults to `on`
                         so existing installs keep rendering the section.
                         `get_option(..., 'on')` returns `''` (empty string)
                         when the admin explicitly UN-ticks the checkbox in
                         Core's settings UI — we treat empty-or-'off' as hidden.
                    -------------------------------------------------------------- -->
                    <?php
                    $show_listings_setting = get_option( 'listeo_author_show_listings', 'on' );
                    $show_listings         = ! in_array( (string) $show_listings_setting, array( '', 'off', '0', 'false' ), true );
                    if ( $show_listings ) :
                    ?>
                    <div class="content-section-modern">
                        <div class="services-header-modern">
                            <div class="section-header-modern"><?php echo esc_html($user_info->display_name); ?><?php esc_html_e("'s Listings", "listeo"); ?></div>
                        </div>

                        <div class="author-listings-modern">
                            <?php if (have_posts()) : ?>
                                <?php while (have_posts()) : the_post(); ?>
                                    <?php $template_loader->get_template_part('content-listing'); ?>
                                <?php endwhile; ?>

                                <div class="pagination-container-modern">
                                    <?php
                                    if (function_exists('wp_pagenavi')) {
                                        wp_pagenavi(array(
                                            'next_text' => '<i class="fa fa-chevron-right"></i>',
                                            'prev_text' => '<i class="fa fa-chevron-left"></i>',
                                            'use_pagenavi_css' => false,
                                        ));
                                    } else {
                                        the_posts_navigation();
                                    }
                                    ?>
                                </div>
                            <?php else : ?>
                                <div class="empty-listings-modern">
                                    <i class="fa fa-list-alt"></i>
                                    <p><?php esc_html_e('No listings found.', 'listeo'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Posts Section ------------------------------------------------
                         Renders the user's published blog posts when the admin has
                         opted in under Listeo Core → Settings → General →
                         "Author Page". Pulled as a secondary query so the main
                         WP_Query (filtered to `listing` by `Listeo_Core_Search::
                         pre_get_posts_listings()`) stays untouched.
                    -------------------------------------------------------------- -->
                    <?php
                    if ( get_option( 'listeo_author_show_posts' ) ) :
                        $posts_per_page = (int) get_option( 'listeo_author_posts_per_page', 6 );
                        if ( $posts_per_page < 1 ) {
                            $posts_per_page = 6;
                        }

                        $author_posts_page = isset( $_GET['author-posts-page'] )
                            ? max( 1, (int) $_GET['author-posts-page'] )
                            : 1;

                        $author_posts_query = new WP_Query( array(
                            'post_type'      => 'post',
                            'post_status'    => 'publish',
                            'author'         => $user->ID,
                            'posts_per_page' => $posts_per_page,
                            'paged'          => $author_posts_page,
                            'no_found_rows'  => false,
                        ) );

                        if ( $author_posts_query->have_posts() ) : ?>
                            <div class="content-section-modern">
                                <div class="services-header-modern">
                                    <div class="section-header-modern">
                                        <?php
                                        printf(
                                            /* translators: %s: author display name */
                                            esc_html__( "%s's Posts", 'listeo' ),
                                            esc_html( $user_info->display_name )
                                        );
                                        ?>
                                    </div>
                                </div>

                                <div class="author-posts-modern">
                                    <?php while ( $author_posts_query->have_posts() ) : $author_posts_query->the_post(); ?>
                                        <article class="author-post-item">
                                            <?php if ( has_post_thumbnail() ) : ?>
                                                <a class="author-post-thumb" href="<?php the_permalink(); ?>">
                                                    <?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); ?>
                                                </a>
                                            <?php endif; ?>
                                            <div class="author-post-body">
                                                <h3 class="author-post-title">
                                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                                </h3>
                                                <div class="author-post-meta">
                                                    <span class="author-post-date">
                                                        <i class="fa fa-calendar-alt" aria-hidden="true"></i>
                                                        <?php echo esc_html( get_the_date() ); ?>
                                                    </span>
                                                    <?php
                                                    $cats = get_the_category();
                                                    if ( ! empty( $cats ) ) :
                                                        $cat = $cats[0];
                                                    ?>
                                                        <span class="author-post-cat">
                                                            <i class="fa fa-folder" aria-hidden="true"></i>
                                                            <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">
                                                                <?php echo esc_html( $cat->name ); ?>
                                                            </a>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ( has_excerpt() || get_the_content() ) : ?>
                                                    <p class="author-post-excerpt">
                                                        <?php echo esc_html( wp_trim_words( get_the_excerpt(), 28, '…' ) ); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endwhile; ?>
                                </div>

                                <?php
                                // Custom pagination — uses our own `author-posts-page`
                                // query var so it doesn't clash with the main author
                                // query's pagination (which serves the listings list).
                                $total_post_pages = (int) $author_posts_query->max_num_pages;
                                if ( $total_post_pages > 1 ) :
                                    $base_url = remove_query_arg( 'author-posts-page' );
                                ?>
                                    <nav class="pagination-container-modern author-posts-pagination" aria-label="<?php esc_attr_e( 'Author posts pagination', 'listeo' ); ?>">
                                        <?php if ( $author_posts_page > 1 ) : ?>
                                            <a class="page-link" href="<?php echo esc_url( add_query_arg( 'author-posts-page', $author_posts_page - 1, $base_url ) ); ?>" rel="prev">
                                                <i class="fa fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php for ( $p = 1; $p <= $total_post_pages; $p++ ) : ?>
                                            <?php if ( $p === $author_posts_page ) : ?>
                                                <span class="page-link is-current"><?php echo (int) $p; ?></span>
                                            <?php else : ?>
                                                <a class="page-link" href="<?php echo esc_url( add_query_arg( 'author-posts-page', $p, $base_url ) ); ?>"><?php echo (int) $p; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <?php if ( $author_posts_page < $total_post_pages ) : ?>
                                            <a class="page-link" href="<?php echo esc_url( add_query_arg( 'author-posts-page', $author_posts_page + 1, $base_url ) ); ?>" rel="next">
                                                <i class="fa fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Reset the global $post so the surrounding
                            // template (Reviews section etc.) sees the
                            // author/user context, not the last looped post.
                            wp_reset_postdata();
                        endif; ?>
                    <?php endif; ?>

                    <!-- Reviews Section -->
                    <?php if (!empty($total_visitor_reviews) && get_option('listeo_author_disable_reviews', 'enable') !== 'disable') : ?>
                        <div class="content-section-modern">
                            <h2 class="listing-desc-headline margin-top-0 margin-bottom-20"><?php
                                printf( // WPCS: XSS OK.
                                    esc_html(_nx('Review %1$s', 'Reviews %1$s', $review_count, 'comments title', 'listeo')),
                                    '<span class="reviews-amount">(' . number_format_i18n($review_count) . ')</span>'
                                );
                            ?></h2>
                            
                            <?php
                            $limit = 5;
                            $visitor_reviews_page = (isset($_GET['author-reviews-page'])) ? $_GET['author-reviews-page'] : 1;
                            $visitor_reviews_offset = ($visitor_reviews_page * $limit) - $limit;
                            
                            $visitor_reviews_args = array(
                                'post__in'    	=> $user_listing_ids,
                                'parent'      	=> 0,
                                'status' 		=> 'approve',
                                'post_type' 	=> 'listing',
                                'number' 		=> $limit,
                                'offset' 		=> $visitor_reviews_offset,
                            );
                            $visitor_reviews_pages = ceil(count($total_visitor_reviews) / $limit);
                            add_filter('comments_clauses', 'listeo_top_comments_only');
                            $visitor_reviews = get_comments($visitor_reviews_args);
                            remove_filter('comments_clauses', 'listeo_top_comments_only');
                            ?>

                            <!-- Reviews using theme's existing design -->
                            <section id="comments" class="comments listing-reviews">
                                <ul class="comment-list">
                                    <?php foreach ($visitor_reviews as $review) : ?>
                                        <li class="comment" id="comment-<?php echo $review->comment_ID; ?>">
                                            <div class="avatar"><?php echo get_avatar($review, 70); ?></div>
                                            <div class="comment-content">
                                                <div class="arrow-comment"></div>
                                                <div class="comment-by">
                                                    <h5><?php echo esc_html($review->comment_author); ?></h5>
                                                    <span class="date"><?php echo date_i18n(get_option('date_format'), strtotime($review->comment_date)); ?>
                                                        <?php esc_html_e('on', 'listeo'); ?>
                                                        <a href="<?php echo esc_url(get_permalink($review->comment_post_ID)); ?>"><?php echo get_the_title($review->comment_post_ID); ?></a>
                                                    </span>
                                                    <?php 
                                                    $review_rating = get_comment_meta($review->comment_ID, 'listeo-rating', true);
                                                    if ($review_rating) : ?>
                                                        <div class="star-rating" data-rating="<?php echo esc_attr($review_rating); ?>"></div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php echo wpautop($review->comment_content); ?>
                                                
                                                <?php
                                                $photos = get_comment_meta($review->comment_ID, 'listeo-attachment-id', false);
                                                if ($photos) : ?>
                                                    <div class="review-images mfp-gallery-container">
                                                        <?php foreach ($photos as $key => $attachment_id) {
                                                            $image = wp_get_attachment_image_src($attachment_id, 'listeo-gallery');
                                                            $image_thumb = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                                                        ?>
                                                            <a href="<?php echo esc_attr($image[0]); ?>" class="mfp-gallery"><img src="<?php echo esc_attr($image_thumb[0]); ?>" alt=""></a>
                                                        <?php } ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php $review_rating_helpful = get_comment_meta($review->comment_ID, 'listeo-review-rating', true); ?>
                                                <a href="#" id="review-<?php echo $review->comment_ID; ?>" data-comment="<?php echo $review->comment_ID; ?>" class="rate-review listeo_core-rate-review">
                                                    <i class="sl sl-icon-like"></i> <?php esc_html_e('Helpful Review', 'listeo'); ?>
                                                    <?php if ($review_rating_helpful) { echo "<span>" . $review_rating_helpful . "</span>"; } ?>
                                                </a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>

                            <?php if ($visitor_reviews_pages > 1) : ?>
                                <div class="clearfix"></div>
                                <div class="pagination-container margin-top-30 margin-bottom-0">
                                    <nav class="pagination">
                                        <?php
                                        echo paginate_links(array(
                                            'base'         	=> @add_query_arg('author-reviews-page', '%#%'),
                                            'format'       	=> '?author-reviews-page=%#%',
                                            'current' 		=> $visitor_reviews_page,
                                            'total' 		=> $visitor_reviews_pages,
                                            'type' 			=> 'list',
                                            'prev_next'    	=> true,
                                            'prev_text'    	=> '<i class="sl sl-icon-arrow-left"></i>',
                                            'next_text'    	=> '<i class="sl sl-icon-arrow-right"></i>',
                                            'add_args'     => false,
                                            'add_fragment' => ''
                                        ));
                                        ?>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Message Modal -->
<?php if (get_option('listeo_messages_page')) : ?>
    <div id="small-dialog" class="zoom-anim-dialog mfp-hide">
        <div class="small-dialog-header">
            <h3><?php esc_html_e('Send Message', 'listeo'); ?></h3>
        </div>
        <div class="message-reply margin-top-0">
            <form action="" id="send-message-from-widget">
                <textarea required data-recipient="<?php echo esc_attr($user->ID); ?>" data-referral="author_archive" cols="40" id="contact-message" name="message" rows="3" placeholder="<?php esc_attr_e('Your message to ', 'listeo'); echo ' ' . esc_attr($user_info->display_name); ?>"></textarea>
                <button class="button">
                    <i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i><?php esc_html_e('Send Message', 'listeo'); ?>
                </button>
                <div class="notification closeable success margin-top-20"></div>
            </form>
        </div>
    </div>
<?php endif; ?>

<style>
/* Extended Statistics Styles */
.extended-stats {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #eee;
}

.stats-subsection {
    margin-bottom: 20px;
}

.subsection-label {
    font-size: 12px;
    font-weight: 600;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stats-mini-grid {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.stat-mini {
    flex: 1;
    min-width: 0;
    text-align: center;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #f0f0f0;
}

.stat-mini-value {
    display: block;
    font-size: 16px;
    font-weight: 700;
    color: #333;
    line-height: 1.2;
}

.stat-mini-label {
    display: block;
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

.stats-info-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 12px 15px;
    font-size: 13px;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stats-info-box i {
    color: #999;
    font-size: 12px;
}

@media (max-width: 768px) {
    .stats-mini-grid {
        gap: 8px;
    }
    
    .stat-mini {
        padding: 6px;
    }
    
    .stat-mini-value {
        font-size: 14px;
    }
}
</style>

<?php get_footer(); ?>