<?php
/**
 * Dashboard: Saved Searches Template
 *
 * @package Listeo_Core
 * @since 2.0.23
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$searches = isset($data->searches) ? $data->searches : array();
$max_searches = isset($data->max_searches) ? $data->max_searches : 10;
$current_count = count($searches);

$saved_searches_instance = Listeo_Core_Saved_Searches::instance();
?>

<div class="dashboard-list-box margin-top-0">
    <h4>
        <?php esc_html_e('Saved Searches', 'listeo_core'); ?>
        <span class="saved-searches-count">(<?php echo esc_html($current_count); ?>/<?php echo esc_html($max_searches); ?>)</span>
    </h4>

    <?php if (!empty($searches)) : ?>
        <ul class="saved-searches-list">
            <?php foreach ($searches as $search) :
                $criteria = is_array($search['search_criteria']) ? $search['search_criteria'] : array();
                $criteria_summary = $saved_searches_instance->get_criteria_summary($criteria);
                $alerts_enabled = (bool) $search['email_alerts_enabled'];
            ?>
                <li class="saved-search-item" data-search-id="<?php echo esc_attr($search['id']); ?>">
                    <div class="list-box-listing">
                        <div class="list-box-listing-content">
                            <div class="inner">
                                <h3>
                                    <a href="<?php echo esc_url($search['search_url']); ?>">
                                        <?php echo esc_html($search['search_name']); ?>
                                    </a>
                                </h3>
                                <span class="saved-search-criteria">
                                    <?php echo esc_html($criteria_summary); ?>
                                </span>
                                <div class="saved-search-meta">
                                    <span class="saved-search-date">
                                        <i class="fa fa-calendar"></i>
                                        <?php
                                        printf(
                                            /* translators: %s: date */
                                            esc_html__('Saved: %s', 'listeo_core'),
                                            date_i18n(get_option('date_format'), strtotime($search['created_at']))
                                        );
                                        ?>
                                    </span>
                                    <?php if ($search['last_email_sent']) : ?>
                                        <span class="saved-search-last-alert">
                                            <i class="fa fa-envelope"></i>
                                            <?php
                                            printf(
                                                /* translators: %s: date */
                                                esc_html__('Last alert: %s', 'listeo_core'),
                                                date_i18n(get_option('date_format'), strtotime($search['last_email_sent']))
                                            );
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="buttons-to-right">
                        <!-- Run Search Button -->
                        <a href="<?php echo esc_url($search['search_url']); ?>"
                           class="button gray"
                           title="<?php esc_attr_e('Run this search', 'listeo_core'); ?>">
                            <i class="fa fa-search"></i>
                        </a>

                        <!-- Toggle Alerts Button -->
                        <button type="button"
                                class="button listeo-toggle-alerts <?php echo $alerts_enabled ? 'alerts-enabled' : 'alerts-disabled'; ?>"
                                data-search-id="<?php echo esc_attr($search['id']); ?>"
                                data-enabled="<?php echo $alerts_enabled ? '1' : '0'; ?>"
                                title="<?php echo $alerts_enabled ? esc_attr__('Email alerts enabled - click to disable', 'listeo_core') : esc_attr__('Email alerts disabled - click to enable', 'listeo_core'); ?>">
                            <i class="fa <?php echo $alerts_enabled ? 'fa-bell' : 'fa-bell-slash'; ?>"></i>
                        </button>

                        <!-- Delete Button -->
                        <button type="button"
                                class="button gray listeo-delete-saved-search"
                                data-search-id="<?php echo esc_attr($search['id']); ?>"
                                title="<?php esc_attr_e('Delete this saved search', 'listeo_core'); ?>">
                            <i class="sl sl-icon-close"></i>
                        </button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <div class="notification notice">
            <p>
                <span><?php esc_html_e('No saved searches!', 'listeo_core'); ?></span>
                <?php esc_html_e('You haven\'t saved any searches yet. Use the search page to find listings and save your search to get email alerts when new listings match your criteria.', 'listeo_core'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
.saved-searches-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.saved-search-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e8e8e8;
    transition: background-color 0.2s ease;
}

.saved-search-item:hover {
    background-color: #f9f9f9;
}

.saved-search-item:last-child {
    border-bottom: none;
}

.saved-search-item .list-box-listing {
    flex: 1;
}

.saved-search-item .list-box-listing-content {
    padding: 0;
}

.saved-search-item h3 {
    margin: 0 0 5px 0;
    font-size: 18px;
}

.saved-search-item h3 a {
    color: #333;
    text-decoration: none;
}

.saved-search-item h3 a:hover {
    color: #f91942;
}

.saved-search-criteria {
    display: block;
    color: #888;
    font-size: 13px;
    margin-bottom: 8px;
}

.saved-search-meta {
    font-size: 12px;
    color: #aaa;
}

.saved-search-meta span {
    margin-right: 15px;
}

.saved-search-meta i {
    margin-right: 4px;
}

.saved-search-item .buttons-to-right {
    display: flex;
    gap: 8px;
    align-items: center;
}

.saved-search-item .button {
    min-width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.listeo-toggle-alerts.alerts-enabled {
    background-color: #38b653;
    color: #fff;
}

.listeo-toggle-alerts.alerts-disabled {
    background-color: #ddd;
    color: #666;
}

.listeo-toggle-alerts:hover {
    opacity: 0.9;
}

.listeo-delete-saved-search:hover {
    background-color: #f91942;
    color: #fff;
}

.saved-searches-count {
    font-weight: normal;
    font-size: 14px;
    color: #888;
}

/* Notification notice inside saved searches */
.dashboard-list-box .notification.notice {
    margin: 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .saved-search-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .saved-search-item .buttons-to-right {
        margin-top: 15px;
        width: 100%;
        justify-content: flex-end;
    }

    .saved-search-meta span {
        display: block;
        margin-bottom: 5px;
    }
}
</style>
