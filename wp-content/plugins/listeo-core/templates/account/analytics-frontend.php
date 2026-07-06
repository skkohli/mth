<?php
/**
 * Listeo Analytics Frontend Dashboard Template
 *
 * @package Listeo_Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap listeo-analytics-wrap analytics-user">

    <!-- Time Range Filter -->
    <div class="listeo-analytics-filters">
        <form method="get" class="listeo-filter-form">
            <label for="days"><?php esc_html_e('Time Range:', 'listeo_core'); ?></label>
            <select name="days" id="days" class="listeo-days-select2" style="width: 200px;">
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

    <!-- Overview Content -->
    <div class="listeo-tab-content">
        <div class="listeo-tab-panel active" id="overview-tab">

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
                     * analytics. Same shape as the admin template hook
                     * — the third arg distinguishes the surface so the
                     * consumer can render owner-appropriate copy
                     * (linking to the frontend resource form rather
                     * than the wp-admin edit screen, etc.).
                     */
                    do_action( 'listeo_analytics_after_top_listings', isset( $selected_listing_id ) ? (int) $selected_listing_id : 0, isset( $days ) ? (int) $days : 30, 'frontend' );
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

                <!-- Booking & Revenue Stats Section -->
                <?php if (!get_option('listeo_bookings_disabled')) : ?>
                <div class="listeo-booking-revenue-stats-section" style="margin-top: 40px;">
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
                <?php endif; ?>

        </div>
    </div>
</div>
