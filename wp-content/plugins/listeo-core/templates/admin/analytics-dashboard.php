<?php
/**
 * Listeo Analytics Dashboard Template
 *
 * @package Listeo_Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap listeo-analytics-wrap analytics-admin">
    <h1></h1>

    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully!', 'listeo_core'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$enabled): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Analytics tracking is currently disabled.', 'listeo_core'); ?></strong>
                <?php printf(
                    __('Enable it in the %sSettings tab%s to start collecting data.', 'listeo_core'),
                    '<a href="' . esc_url(add_query_arg('tab', 'settings')) . '">',
                    '</a>'
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Page Header with Toggle -->
    <div class="listeo-analytics-header">
        <div class="listeo-analytics-title"><?php esc_html_e('Listeo Analytics', 'listeo_core'); ?></div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="listeo-analytics-toggle-form">
            <input type="hidden" name="action" value="listeo_save_analytics_settings">
            <?php wp_nonce_field('listeo_analytics_settings', 'listeo_analytics_nonce'); ?>

            <label class="listeo-toggle-switch">
                <input type="checkbox"
                       name="listeo_analytics_enabled"
                       id="listeo_analytics_enabled"
                       value="1"
                       <?php checked($enabled, true); ?>
                       onchange="this.form.submit()">
                <span class="listeo-toggle-slider"></span>
            </label>

            <span class="listeo-analytics-status <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                <?php if ($enabled): ?>
                    <i class="fa fa-check-circle"></i>
                    <strong><?php esc_html_e('All data tracking is ENABLED', 'listeo_core'); ?></strong>
                <?php else: ?>
                    <i class="fa fa-times-circle"></i>
                    <strong><?php esc_html_e('All data tracking is DISABLED', 'listeo_core'); ?></strong>
                <?php endif; ?>
            </span>
        </form>
    </div>

    <!-- Time Range Filter -->
    <div class="listeo-analytics-filters">
        <form method="get" class="listeo-filter-form">
            <input type="hidden" name="page" value="listeo-analytics">
            <label for="days"><?php esc_html_e('Time Range:', 'listeo_core'); ?></label>
            <select name="days" id="days" onchange="this.form.submit()">
                <option value="1" <?php selected($days, 1); ?>><?php esc_html_e('Today', 'listeo_core'); ?></option>
                <option value="7" <?php selected($days, 7); ?>><?php esc_html_e('Last 7 Days', 'listeo_core'); ?></option>
                <option value="30" <?php selected($days, 30); ?>><?php esc_html_e('Last 30 Days', 'listeo_core'); ?></option>
                <option value="90" <?php selected($days, 90); ?>><?php esc_html_e('Last 90 Days', 'listeo_core'); ?></option>
                <option value="365" <?php selected($days, 365); ?>><?php esc_html_e('Last Year', 'listeo_core'); ?></option>
            </select>

            <label for="listing_id" style="margin-left: 20px;"><?php esc_html_e('Filter by Listing:', 'listeo_core'); ?></label>
            <select name="listing_id" id="listing_id" class="listeo-listing-select2" style="width: 300px;">
                <option value="0"><?php esc_html_e('All Listings', 'listeo_core'); ?></option>
                <?php if ($selected_listing_id > 0): ?>
                    <option value="<?php echo esc_attr($selected_listing_id); ?>" selected="selected">
                        <?php echo esc_html(get_the_title($selected_listing_id)); ?>
                    </option>
                <?php endif; ?>
            </select>
        </form>
    </div>

    <!-- Tab Navigation -->
    <div class="listeo-analytics-tabs">
        <a href="#overview"
           class="listeo-analytics-tab <?php echo ($active_tab === 'overview') ? 'active' : ''; ?>"
           data-tab="overview">
            <i class="fa fa-chart-bar"></i>
            <?php esc_html_e('Overview', 'listeo_core'); ?>
        </a>
        <a href="#bookings"
           class="listeo-analytics-tab <?php echo ($active_tab === 'bookings') ? 'active' : ''; ?>"
           data-tab="bookings">
            <i class="fa fa-calendar-check"></i>
            <?php esc_html_e('Bookings', 'listeo_core'); ?>
        </a>
        <a href="#ai-search"
           class="listeo-analytics-tab <?php echo ($active_tab === 'ai-search') ? 'active' : ''; ?>"
           data-tab="ai-search">
            <i class="fa fa-search"></i>
            <?php esc_html_e('AI Search', 'listeo_core'); ?>
        </a>
        <a href="#messages"
           class="listeo-analytics-tab <?php echo ($active_tab === 'messages') ? 'active' : ''; ?>"
           data-tab="messages">
            <i class="fa fa-comments"></i>
            <?php esc_html_e('Messages', 'listeo_core'); ?>
        </a>
    </div>

    <!-- Tab Content -->
    <div class="listeo-tab-content">
        <!-- Overview Tab Panel -->
        <div class="listeo-tab-panel <?php echo ($active_tab === 'overview') ? 'active' : ''; ?>" id="overview-tab" data-tab-content="overview">

                <h2 class="listeo-section-headline">
                    <i class="fa fa-chart-bar"></i>
                    <?php esc_html_e('Overall', 'listeo_core'); ?>
                </h2>

                <!-- Hero Stats -->
                <div class="listeo-hero-stats">
                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-eye"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Total Views', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($comparison['current']['total_views'] ?? 0); ?>
                            <?php if (isset($comparison['changes']['views'])): ?>
                                <span class="listeo-stat-change <?php echo $comparison['changes']['views'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($comparison['changes']['views'] >= 0): ?>
                                        <i class="fa fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fa fa-arrow-down"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-users"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Unique Visitors', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($comparison['current']['unique_views'] ?? 0); ?>
                            <?php if (isset($comparison['changes']['unique_views'])): ?>
                                <span class="listeo-stat-change <?php echo $comparison['changes']['unique_views'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($comparison['changes']['unique_views'] >= 0): ?>
                                        <i class="fa fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fa fa-arrow-down"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-phone"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Contact Clicks', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($comparison['current']['total_contacts'] ?? 0); ?>
                            <?php if (isset($comparison['changes']['contacts'])): ?>
                                <span class="listeo-stat-change <?php echo $comparison['changes']['contacts'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($comparison['changes']['contacts'] >= 0): ?>
                                        <i class="fa fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fa fa-arrow-down"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-calendar"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Booking Interactions', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($comparison['current']['total_bookings'] ?? 0); ?>
                            <?php if (isset($comparison['changes']['bookings'])): ?>
                                <span class="listeo-stat-change <?php echo $comparison['changes']['bookings'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($comparison['changes']['bookings'] >= 0): ?>
                                        <i class="fa fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fa fa-arrow-down"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="listeo-charts-row">
                    <!-- Views Over Time Chart -->
                    <div class="listeo-chart-box <?php echo $selected_listing_id > 0 ? 'full-width' : ''; ?>">
                        <h3><?php esc_html_e('Views Over Time', 'listeo_core'); ?></h3>
                        <canvas id="viewsChart"></canvas>
                        <script type="application/json" id="viewsChartData">
                            <?php echo json_encode($views_over_time); ?>
                        </script>
                    </div>

                    <?php if ($selected_listing_id == 0): ?>
                        <!-- Top Listings Chart (only show when viewing all listings) -->
                        <div class="listeo-chart-box">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="margin: 0;"><?php esc_html_e('Top Listings by Views', 'listeo_core'); ?></h3>
                                <div class="listeo-chart-filter" style="display: flex; align-items: baseline;">
                                    <label for="top-listings-limit" style="margin-right: 8px;"><?php esc_html_e('Show:', 'listeo_core'); ?></label>
                                    <select id="top-listings-limit" style="padding: 4px 8px;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                            </div>
                            <div id="topListingsChartContainer">
                                <canvas id="topListingsChart"></canvas>
                            </div>
                            <script type="application/json" id="topListingsChartData">
                                <?php echo json_encode($top_listings); ?>
                            </script>
                        </div>
                    <?php endif; ?>

                    <?php
                    /**
                     * Slot for add-on plugins to inject resource-level
                     * analytics (e.g. Listeo Booking Plus' "Top Resources"
                     * table for a specific listing). Receives the
                     * currently-selected listing id (0 = all listings)
                     * and the chosen date range so consumers can scope
                     * their queries the same way Core does.
                     */
                    do_action( 'listeo_analytics_after_top_listings', isset( $selected_listing_id ) ? (int) $selected_listing_id : 0, isset( $days ) ? (int) $days : 30, 'admin' );
                    ?>
                </div>

                <!-- Engagement Breakdown -->
                <div class="listeo-charts-row">
                    <div class="listeo-chart-box">
                        <h3><?php esc_html_e('Contact Link Clicks on Listing Pages', 'listeo_core'); ?></h3>
                        <canvas id="contactMethodsChart"></canvas>
                        <script type="application/json" id="contactMethodsChartData">
                            <?php echo json_encode($contact_clicks); ?>
                        </script>
                    </div>

                    <div class="listeo-chart-box">
                        <h3><?php esc_html_e('Social Media Platform Clicks on Listing Pages', 'listeo_core'); ?></h3>
                        <canvas id="socialPlatformsChart"></canvas>
                        <script type="application/json" id="socialPlatformsChartData">
                            <?php echo json_encode($social_stats); ?>
                        </script>
                    </div>
                </div>

        </div>
        <!-- End Overview Tab -->

        <!-- Bookings Tab Panel -->
        <div class="listeo-tab-panel <?php echo ($active_tab === 'bookings') ? 'active' : ''; ?>" id="bookings-tab" data-tab-content="bookings">

            <h2 class="listeo-section-headline">
                <i class="fa fa-calendar-check"></i>
                <?php esc_html_e('Booking & Revenue Statistics', 'listeo_core'); ?>
            </h2>

            <!-- Booking & Revenue Hero Cards -->
                    <div class="listeo-hero-stats">
                        <div class="listeo-stat-card">
                            <div class="listeo-stat-header">
                                <span class="listeo-stat-icon">
                                    <i class="fa fa-calendar-alt"></i>
                                </span>
                                <span class="listeo-stat-label"><?php esc_html_e('Total Bookings', 'listeo_core'); ?></span>
                            </div>
                            <div class="listeo-stat-value">
                                <?php echo number_format_i18n($booking_stats['total_bookings'] ?? 0); ?>
                                <?php if (isset($booking_comparison['changes']['total_bookings'])): ?>
                                    <span class="listeo-stat-change <?php echo $booking_comparison['changes']['total_bookings'] >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php if ($booking_comparison['changes']['total_bookings'] >= 0): ?>
                                            <i class="fa fa-arrow-up"></i>
                                        <?php else: ?>
                                            <i class="fa fa-arrow-down"></i>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="listeo-stat-card">
                            <div class="listeo-stat-header">
                                <span class="listeo-stat-icon">
                                    <i class="fa fa-check-circle"></i>
                                </span>
                                <span class="listeo-stat-label"><?php esc_html_e('Paid Bookings', 'listeo_core'); ?></span>
                            </div>
                            <div class="listeo-stat-value">
                                <?php echo number_format_i18n($booking_stats['confirmed_bookings'] ?? 0); ?>
                                <?php if (isset($booking_comparison['changes']['confirmed_bookings'])): ?>
                                    <span class="listeo-stat-change <?php echo $booking_comparison['changes']['confirmed_bookings'] >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php if ($booking_comparison['changes']['confirmed_bookings'] >= 0): ?>
                                            <i class="fa fa-arrow-up"></i>
                                        <?php else: ?>
                                            <i class="fa fa-arrow-down"></i>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="listeo-stat-card">
                            <div class="listeo-stat-header">
                                <span class="listeo-stat-icon">
                                    <i class="fa fa-money-bill-wave"></i>
                                </span>
                                <span class="listeo-stat-label"><?php esc_html_e('Total Revenue', 'listeo_core'); ?></span>
                            </div>
                            <div class="listeo-stat-value">
                                <?php
                                $total_revenue = $revenue_stats['total_revenue'] ?? 0;
                                echo $revenue_stats['currency'] ?? '';
                                echo number_format_i18n($total_revenue, 2);
                                ?>
                                <?php if (isset($revenue_comparison['changes']['revenue'])): ?>
                                    <span class="listeo-stat-change <?php echo $revenue_comparison['changes']['revenue'] >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php if ($revenue_comparison['changes']['revenue'] >= 0): ?>
                                            <i class="fa fa-arrow-up"></i>
                                        <?php else: ?>
                                            <i class="fa fa-arrow-down"></i>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="listeo-stat-card">
                            <div class="listeo-stat-header">
                                <span class="listeo-stat-icon">
                                    <i class="fa fa-chart-line"></i>
                                </span>
                                <span class="listeo-stat-label"><?php esc_html_e('Total Commissions', 'listeo_core'); ?></span>
                            </div>
                            <div class="listeo-stat-value">
                                <?php
                                $total_commission = $revenue_stats['total_commission'] ?? 0;
                                echo $revenue_stats['currency'] ?? '';
                                echo number_format_i18n($total_commission, 2);
                                ?>
                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                    <?php
                                    $commission_rate = $revenue_stats['commission_rate'] ?? 10;
                                    printf(
                                        __('%s%% platform fee', 'listeo_core'),
                                        number_format($commission_rate, 1)
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="listeo-stat-card">
                            <div class="listeo-stat-header">
                                <span class="listeo-stat-icon">
                                    <i class="fa fa-percentage"></i>
                                </span>
                                <span class="listeo-stat-label"><?php esc_html_e('Booking Conversion', 'listeo_core'); ?></span>
                            </div>
                            <div class="listeo-stat-value">
                                <?php echo number_format($booking_conversion['conversion_rate'] ?? 0, 2); ?>%
                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                    <?php
                                    printf(
                                        __('%d clicks → %d bookings', 'listeo_core'),
                                        $booking_conversion['booking_clicks'] ?? 0,
                                        $booking_conversion['actual_bookings'] ?? 0
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Charts -->
                    <div class="listeo-charts-row" style="margin-top: 30px;">
                        <!-- Bookings Over Time -->
                        <div class="listeo-chart-box">
                            <h3><?php esc_html_e('Bookings Over Time', 'listeo_core'); ?></h3>
                            <canvas id="bookingsOverTimeChart"></canvas>
                            <script type="application/json" id="bookingsOverTimeData">
                                <?php echo json_encode($bookings_over_time); ?>
                            </script>
                        </div>

                        <!-- Booking Status Breakdown -->
                        <div class="listeo-chart-box">
                            <h3><?php esc_html_e('Booking Status Breakdown', 'listeo_core'); ?></h3>
                            <canvas id="bookingStatusChart"></canvas>
                            <script type="application/json" id="bookingStatusData">
                                <?php echo json_encode($booking_status_breakdown); ?>
                            </script>
                        </div>
                    </div>

        </div>
        <!-- End Bookings Tab -->

        <!-- AI Search Tab Panel -->
        <div class="listeo-tab-panel <?php echo ($active_tab === 'ai-search') ? 'active' : ''; ?>" id="ai-search-tab" data-tab-content="ai-search">

            <?php if ($ai_search_active && $ai_search_stats): ?>

                <h2 class="listeo-section-headline">
                    <i class="fa fa-search"></i>
                    <?php esc_html_e('AI Search Stats', 'listeo_core'); ?>
                </h2>

                <!-- AI Stats Hero Cards -->
                <div class="listeo-hero-stats">
                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-search"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Total AI Searches', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value"><?php echo number_format_i18n($ai_search_stats['total_searches']); ?></div>
                    </div>

                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-list"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Avg Results per Search', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value"><?php echo number_format($ai_search_stats['avg_results_per_search'], 1); ?></div>
                    </div>
                </div>

                <!-- AI Search Charts -->
                <div class="listeo-charts-row" style="margin-top: 30px;">
                    <!-- Popular Search Queries -->
                    <div class="listeo-chart-box full-width">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;"><?php esc_html_e('Top AI Search Queries', 'listeo_core'); ?></h3>
                            <div>
                                <label for="ai-queries-limit" style="margin-right: 8px;"><?php esc_html_e('Show:', 'listeo_core'); ?></label>
                                <select id="ai-queries-limit" style="padding: 4px 8px;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                        <div id="aiSearchQueriesChartContainer">
                            <canvas id="aiSearchQueriesChart"></canvas>
                        </div>
                        <script type="application/json" id="aiSearchQueriesData">
                            <?php echo json_encode($ai_search_stats['popular_queries']); ?>
                        </script>
                    </div>
                </div>

            <?php else: ?>

                <div class="listeo-no-data-message">
                    <i class="fa fa-search" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3><?php esc_html_e('AI Search Not Available', 'listeo_core'); ?></h3>
                    <p><?php esc_html_e('The AI Chat Search plugin is not active or has no data yet.', 'listeo_core'); ?></p>
                </div>

            <?php endif; ?>

        </div>
        <!-- End AI Search Tab -->

        <!-- Messages Tab Panel -->
        <div class="listeo-tab-panel <?php echo ($active_tab === 'messages') ? 'active' : ''; ?>" id="messages-tab" data-tab-content="messages">

            <?php
            // Check if viewing conversation detail
            if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])):
                // Display single conversation
                if ($conversations_table):
                    $conversations_table->display_conversation_detail(absint($_GET['id']));
                endif;
            else:
                // Display messages stats and list
            ?>

                <h2 class="listeo-section-headline">
                    <i class="fa fa-comments"></i>
                    <?php esc_html_e('Private Messages & Conversations', 'listeo_core'); ?>
                </h2>

                <!-- Message Stats Hero Cards -->
                <div class="listeo-hero-stats">
                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-comments"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Total Messages', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($message_comparison['current']['messages'] ?? 0); ?>
                            <?php if (isset($message_comparison['changes']['messages'])): ?>
                                <span class="listeo-stat-change <?php echo $message_comparison['changes']['messages'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($message_comparison['changes']['messages'] >= 0): ?>
                                        <i class="fa fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fa fa-arrow-down"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-inbox"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Total Conversations', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($message_comparison['current']['conversations'] ?? 0); ?>
                            <?php if (isset($message_comparison['changes']['conversations'])): ?>
                                <span class="listeo-stat-change <?php echo $message_comparison['changes']['conversations'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($message_comparison['changes']['conversations'] >= 0): ?>
                                        <i class="fa fa-arrow-up"></i>
                                    <?php else: ?>
                                        <i class="fa fa-arrow-down"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="listeo-stat-card">
                        <div class="listeo-stat-header">
                            <span class="listeo-stat-icon">
                                <i class="fa fa-paperclip"></i>
                            </span>
                            <span class="listeo-stat-label"><?php esc_html_e('Messages with Attachments', 'listeo_core'); ?></span>
                        </div>
                        <div class="listeo-stat-value">
                            <?php echo number_format_i18n($message_stats['messages_with_attachments'] ?? 0); ?>
                        </div>
                    </div>
                </div>

                <!-- Conversations List -->
                <div class="listeo-conversations-list" style="margin-top: 40px;">
                    <h3><?php esc_html_e('Recent Conversations', 'listeo_core'); ?></h3>
                    <?php if ($conversations_table): ?>
                        <form method="get">
                            <input type="hidden" name="page" value="listeo-analytics">
                            <input type="hidden" name="tab" value="messages">
                            <input type="hidden" name="days" value="<?php echo esc_attr($days); ?>">
                            <?php
                            $conversations_table->display();
                            ?>
                        </form>
                    <?php else: ?>
                        <p><?php esc_html_e('No conversations data available.', 'listeo_core'); ?></p>
                    <?php endif; ?>
                </div>

            <?php endif; // End conversation detail check ?>

        </div>
        <!-- End Messages Tab -->

    </div>
    <!-- End Tab Content -->

    <!-- Conversation Modal -->
    <div id="listeo-conversation-modal" class="listeo-modal" style="display: none;">
        <div class="listeo-modal-overlay"></div>
        <div class="listeo-modal-content">
            <div class="listeo-modal-header">
                <h2><?php esc_html_e('Conversation Details', 'listeo_core'); ?></h2>
                <button class="listeo-modal-close">&times;</button>
            </div>
            <div class="listeo-modal-body">
                <div class="listeo-modal-loading">
                    <i class="fa fa-spinner fa-spin"></i>
                    <?php esc_html_e('Loading conversation...', 'listeo_core'); ?>
                </div>
                <div class="listeo-modal-conversation-content"></div>
            </div>
        </div>
    </div>
</div>
