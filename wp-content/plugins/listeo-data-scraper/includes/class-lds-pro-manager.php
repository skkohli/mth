<?php
/**
 * Pro Manager Class
 * Handles Pro/Free feature restrictions and license checks
 *
 * @package Listeo_Data_Scraper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LDS_Pro_Manager {

    /**
     * Pro upgrade URL
     */
    const PRO_UPGRADE_URL = 'https://purethemes.net/listeo-data-importer/';

    /**
     * List of Pro-only features
     */
    const PRO_FEATURES = [
        'ai_descriptions',
        'map_mode',
        'import_limit_100',
        'outscraper_api',
        'photo_regeneration',
        'ai_description_regeneration',
        'schedule_import'
    ];

    /**
     * Check if Pro version is active
     *
     * @return bool True if Pro is active, false otherwise
     */
    public static function is_pro_active() {
        // Use proxy-based license manager for validation
        $license_manager = LDS_Proxy_License_Manager::get_instance();
        return $license_manager->is_license_valid();
    }

    /**
     * Check if a specific feature is locked
     *
     * @param string $feature Feature name to check
     * @return bool True if locked (not available), false if unlocked (available)
     */
    public static function is_feature_locked($feature) {
        // If Pro is active, nothing is locked
        if (self::is_pro_active()) {
            return false;
        }

        // Check if this is a Pro feature
        return in_array($feature, self::PRO_FEATURES);
    }

    /**
     * Get upgrade URL with optional tracking parameter
     *
     * @param string $feature Optional feature context for tracking
     * @return string Upgrade URL
     */
    public static function get_upgrade_url($feature = '') {
        $url = self::PRO_UPGRADE_URL;

        if (!empty($feature)) {
            $url = add_query_arg('feature', $feature, $url);
        }

        return esc_url($url);
    }

    /**
     * Get Pro badge HTML
     *
     * @return string HTML for Pro badge
     */
    public static function get_pro_badge() {
        return '<span class="lds-pro-badge">PRO</span>';
    }

    /**
     * Get lock icon HTML
     *
     * @return string HTML for lock icon
     */
    public static function get_lock_icon() {
        return '<span class="lds-lock-icon">' . lds_get_inline_svg_icon('lock') . '</span>';
    }

    /**
     * Render upgrade overlay/notice
     *
     * @param array $args {
     *     Arguments for rendering the upgrade notice
     *
     *     @type string $feature_id    Feature identifier for tracking
     *     @type string $title         Title of the feature
     *     @type string $description   Description of what the feature does
     *     @type array  $benefits      List of benefits (optional)
     * }
     * @return string HTML for upgrade notice
     */
    public static function render_upgrade_notice($args) {
        $defaults = [
            'feature_id' => '',
            'title' => 'Pro Feature',
            'description' => 'This feature is available in the Pro version.',
            'benefits' => []
        ];

        $args = wp_parse_args($args, $defaults);

        ob_start();
        ?>
        <div class="lds-upgrade-notice">
            <p>
                <strong><?php echo esc_html($args['title']); ?></strong>
                <?php if (!empty($args['description'])): ?>
                    <br><?php echo esc_html($args['description']); ?>
                <?php endif; ?>
            </p>

            <?php if (!empty($args['benefits'])): ?>
                <ul class="lds-pro-benefits">
                    <?php foreach ($args['benefits'] as $benefit): ?>
                        <li><?php echo lds_get_inline_svg_icon('check'); ?><?php echo esc_html($benefit); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <a href="<?php echo self::get_upgrade_url($args['feature_id']); ?>"
               class="button button-primary"
               target="_blank">
                Upgrade to Pro
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get max import limit based on Pro status
     *
     * @return int Maximum import limit
     */
    public static function get_max_import_limit() {
        return self::is_pro_active() ? 100 : 5;
    }

    /**
     * Get current import limit (respects Pro/Free limits)
     *
     * @return int Current import limit
     */
    public static function get_import_limit() {
        $user_limit = (int) get_option('lds_import_limit', 5);
        $max_limit = self::get_max_import_limit();

        return min($user_limit, $max_limit);
    }

    /**
     * Get max photos per listing based on Pro status
     *
     * @return int Maximum photo import limit
     */
    public static function get_max_photo_limit() {
        return self::is_pro_active() ? 10 : 1;
    }

    /**
     * Get current photo import limit (respects Pro/Free limits)
     *
     * @return int Current photo import limit
     */
    public static function get_photo_import_limit() {
        $user_limit = (int) get_option('lds_photo_import_limit', 0);
        $max_limit = self::get_max_photo_limit();

        return min($user_limit, $max_limit);
    }

    /**
     * Get list of Pro features with descriptions
     *
     * @return array Array of features with descriptions
     */
    public static function get_pro_features_list() {
        return [
            'ai_descriptions' => [
                'icon' => 'bot',
                'title' => 'AI-Generated Descriptions',
                'description' => 'Automatically generate unique, SEO-optimized descriptions for each imported listing using OpenAI GPT.'
            ],
            'map_mode' => [
                'icon' => 'map',
                'title' => 'Interactive Map Search',
                'description' => 'Click exact locations on an interactive map for precise area targeting instead of text-only searches.'
            ],
            'import_limit_100' => [
                'icon' => 'chart',
                'title' => 'Import Up to 100 Listings',
                'description' => 'Import up to 100 listings per batch (vs. 10 in Free version) for faster bulk imports.'
            ],
            'schedule_import' => [
                'icon' => 'clock',
                'title' => 'Scheduled Imports',
                'description' => 'Schedule imports to run automatically at a chosen time, with your selected settings - set it and forget it.'
            ],
            'priority_support' => [
                'icon' => 'zap',
                'title' => 'Priority Support',
                'description' => 'Get faster response times and priority assistance from the PureThemes support team.'
            ],
            'lifetime_updates' => [
                'icon' => 'refresh',
                'title' => 'Lifetime Updates',
                'description' => 'Receive all future updates and new features for the lifetime of your license.'
            ]
        ];
    }
}
