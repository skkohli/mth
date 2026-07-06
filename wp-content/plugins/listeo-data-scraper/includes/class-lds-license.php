<?php
/**
 * License Management Class
 * Handles license activation, deactivation, and validation
 *
 * @package Listeo_Data_Scraper
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LDS_License {

    /**
     * License manager instance
     */
    private $license_manager;

    /**
     * Constructor
     */
    public function __construct() {
        // Use proxy-based license manager
        $this->license_manager = LDS_Proxy_License_Manager::get_instance();

        // Register license settings
        add_action('admin_init', [$this, 'register_license_settings']);

        // Handle AJAX requests
        add_action('wp_ajax_lds_activate_license', [$this, 'ajax_activate_license']);
        add_action('wp_ajax_lds_deactivate_license', [$this, 'ajax_deactivate_license']);
        add_action('wp_ajax_lds_validate_license', [$this, 'ajax_validate_license']);

        // Enqueue admin scripts for license page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_license_scripts']);
    }

    /**
     * Register license settings
     */
    public function register_license_settings() {
        // Render license section as a standalone card after settings wrapper
        add_action('lds_after_settings_wrapper', [$this, 'render_license_section_card']);
    }

    /**
     * Render license section as a standalone card
     */
    public function render_license_section_card() {
        // Only show on plugin settings page
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'lds-settings') === false) {
            return;
        }

        $license_status = $this->license_manager->get_license_status();
        $license_key_masked = $this->license_manager->get_license_key_masked();
        $last_check = $this->license_manager->get_last_check_time();
        $is_valid = $license_status === 'valid';
        ?>

        <div id="lds-license-section" class="lds-license-section" style="">
            <h3 style="background: #0073ee10; color: #0073ee; padding: 20px 25px; margin: 0; font-size: 18px; font-weight: 600;">
                <span class="dashicons dashicons-admin-network" style="font-size: 20px; width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;"></span>
                License & Pro Features
            </h3>
            <div style="padding: 25px;">
                <?php
                $is_pro_active = LDS_Pro_Manager::is_pro_active();
                if ($is_pro_active):
                ?>

                <?php endif; ?>

                <div class="lds-license-management">
                    <?php if ($is_valid): ?>
                        <!-- License Active State -->
                        <div class="lds-license-active-state">
                            <!-- Status Badge -->
                            <div class="lds-license-status-badge lds-status-valid">
                                <?php echo lds_get_inline_svg_icon('check', 'status-icon'); ?>
                                <div class="status-content">
                                    <div class="status-title">License Active</div>
                                    <div class="status-message">All Pro features are unlocked</div>
                                </div>
                            </div>

                            <!-- License Details -->
                            <div class="lds-license-details">
                                <div class="lds-license-detail-row">
                                    <span class="detail-label">Product</span>
                                    <span class="detail-value">Listeo Data Importer Pro</span>
                                </div>
                                <div class="lds-license-detail-row">
                                    <span class="detail-label">License Key</span>
                                    <span class="detail-value"><code class="lds-license-key-code"><?php echo esc_html($license_key_masked); ?></code></span>
                                </div>
                                <?php if ($last_check > 0): ?>
                                <div class="lds-license-detail-row">
                                    <span class="detail-label">Last Validated</span>
                                    <span class="detail-value"><?php echo esc_html(human_time_diff($last_check, current_time('timestamp'))); ?> ago</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="lds-license-actions">
                                <button type="button" class="button button-secondary" id="lds-validate-license-btn">
                                    <?php echo lds_get_inline_svg_icon('refresh', 'lds-validate-license-icon'); ?>
                                    <?php echo lds_get_inline_svg_icon('loader', 'lds-validate-license-spinner lds-inline-icon--spin lds-button-icon-hidden'); ?>
                                    <span class="button-text"><?php esc_html_e('Validate License', 'listeo-data-scraper'); ?></span>
                                </button>
                                <button type="button" class="button button-link-delete" id="lds-deactivate-license-btn">
                                    <?php echo lds_get_inline_svg_icon('x', 'lds-deactivate-license-icon'); ?>
                                    <?php echo lds_get_inline_svg_icon('loader', 'lds-deactivate-license-spinner lds-inline-icon--spin lds-button-icon-hidden'); ?>
                                    <span class="button-text"><?php esc_html_e('Deactivate License', 'listeo-data-scraper'); ?></span>
                                </button>
                            </div>

                            <div id="lds-license-action-message"></div>
                        </div>

                    <?php else: ?>
                        <!-- License Inactive/Invalid State -->
                        <div class="lds-license-inactive-state">
                            <!-- Status Badge -->
                            <?php if ($license_status === 'invalid'): ?>
                            <div class="lds-license-status-badge lds-status-invalid">
                                <?php echo lds_get_inline_svg_icon('x', 'status-icon'); ?>
                                <div class="status-content">
                                    <div class="status-title">License Invalid</div>
                                    <div class="status-message">Please activate a valid license</div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="lds-license-status-badge lds-status-inactive">
                                <?php echo lds_get_inline_svg_icon('alert', 'status-icon'); ?>
                                <div class="status-content">
                                    <div class="status-title">No License Active</div>
                                    <div class="status-message">Activate your license to unlock Pro features</div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Activation Form -->
                            <form id="lds-license-activation-form" onsubmit="return false;">
                                <div class="lds-form-group">
                                    <label for="lds_license_key_input">License Key</label>
                                    <input type="text"
                                           id="lds_license_key_input"
                                           name="license_key"
                                           class="regular-text"
                                           placeholder="Enter your license key..."
                                           required>
                                    <p class="description">
                                        You can find your license key in your purchase confirmation email or account dashboard.
                                    </p>
                                </div>

                                <div class="lds-form-actions">
                                    <button type="button" class="button button-primary" id="lds-activate-license-btn">
                                        <?php echo lds_get_inline_svg_icon('check', 'lds-activate-license-icon'); ?>
                                        <?php echo lds_get_inline_svg_icon('loader', 'lds-activate-license-spinner lds-inline-icon--spin lds-button-icon-hidden'); ?>
                                        <span class="button-text"><?php esc_html_e('Activate License', 'listeo-data-scraper'); ?></span>
                                    </button>
                                </div>

                                <div id="lds-license-activation-message"></div>
                            </form>
                        </div>

                    <?php endif; ?>

                    <!-- Pro Features List -->
                    <?php if (!$is_valid): ?>
                    <div class="lds-pro-features-list">
                        <h4>Pro Features Included:</h4>
                        <ul>
                            <?php foreach (LDS_Pro_Manager::get_pro_features_list() as $feature_key => $feature): ?>
                                <li>
                                    <?php echo lds_get_inline_svg_icon(isset($feature['icon']) ? $feature['icon'] : 'check', 'lds-pro-feature-icon'); ?>
                                    <div>
                                        <strong><?php echo esc_html($feature['title']); ?></strong>
                                        <span><?php echo esc_html($feature['description']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="lds-pro-features-upgrade">
                            <a href="<?php echo LDS_Pro_Manager::get_upgrade_url('ai_descriptions'); ?>" class="button button-primary" target="_blank">
                                <?php echo lds_get_inline_svg_icon('unlock'); ?>
                                <?php esc_html_e('Upgrade to Pro', 'listeo-data-scraper'); ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var nonce = <?php echo wp_json_encode(wp_create_nonce('lds_license_nonce')); ?>;

            // Activate License - using click handler on button
            $('#lds-activate-license-btn').on('click', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $btnText = $btn.find('.button-text');
                var $btnIcon = $btn.find('.lds-activate-license-icon');
                var $btnSpinner = $btn.find('.lds-activate-license-spinner');
                var $message = $('#lds-license-activation-message');
                var licenseKey = $('#lds_license_key_input').val().trim();
                var originalText = $btnText.text();

                if (!licenseKey) {
                    $message.html('<div class="notice notice-error"><p>Please enter a license key.</p></div>');
                    return;
                }

                $btn.prop('disabled', true);
                $btnIcon.addClass('lds-button-icon-hidden');
                $btnSpinner.removeClass('lds-button-icon-hidden');
                $btnText.text('Activating...');
                $message.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_activate_license',
                        nonce: nonce,
                        license_key: licenseKey
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            $btn.prop('disabled', false);
                            $btnSpinner.addClass('lds-button-icon-hidden');
                            $btnIcon.removeClass('lds-button-icon-hidden');
                            $btnText.text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        $message.html('<div class="notice notice-error"><p>Connection error. Please try again.</p></div>');
                        $btn.prop('disabled', false);
                        $btnSpinner.addClass('lds-button-icon-hidden');
                        $btnIcon.removeClass('lds-button-icon-hidden');
                        $btnText.text(originalText);
                    }
                });
            });

            // Deactivate License
            $('#lds-deactivate-license-btn').on('click', function() {
                if (!confirm('Are you sure you want to deactivate this license? Pro features will be locked.')) {
                    return;
                }

                var $btn = $(this);
                var $btnText = $btn.find('.button-text');
                var $btnIcon = $btn.find('.lds-deactivate-license-icon');
                var $btnSpinner = $btn.find('.lds-deactivate-license-spinner');
                var $message = $('#lds-license-action-message');
                var originalText = $btnText.text();

                $btn.prop('disabled', true);
                $btnIcon.addClass('lds-button-icon-hidden');
                $btnSpinner.removeClass('lds-button-icon-hidden');
                $btnText.text('Deactivating...');
                $message.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_deactivate_license',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            $btn.prop('disabled', false);
                            $btnSpinner.addClass('lds-button-icon-hidden');
                            $btnIcon.removeClass('lds-button-icon-hidden');
                            $btnText.text(originalText);
                        }
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error"><p>Connection error. Please try again.</p></div>');
                        $btn.prop('disabled', false);
                        $btnSpinner.addClass('lds-button-icon-hidden');
                        $btnIcon.removeClass('lds-button-icon-hidden');
                        $btnText.text(originalText);
                    }
                });
            });

            // Validate License
            $('#lds-validate-license-btn').on('click', function() {
                var $btn = $(this);
                var $btnText = $btn.find('.button-text');
                var $btnIcon = $btn.find('.lds-validate-license-icon');
                var $btnSpinner = $btn.find('.lds-validate-license-spinner');
                var $message = $('#lds-license-action-message');
                var originalText = $btnText.text();

                $btn.prop('disabled', true);
                $btnIcon.addClass('lds-button-icon-hidden');
                $btnSpinner.removeClass('lds-button-icon-hidden');
                $btnText.text('Validating...');
                $message.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lds_validate_license',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            if (response.data.reload) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            $message.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                        $btn.prop('disabled', false);
                        $btnSpinner.addClass('lds-button-icon-hidden');
                        $btnIcon.removeClass('lds-button-icon-hidden');
                        $btnText.text(originalText);
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error"><p>Connection error. Please try again.</p></div>');
                        $btn.prop('disabled', false);
                        $btnSpinner.addClass('lds-button-icon-hidden');
                        $btnIcon.removeClass('lds-button-icon-hidden');
                        $btnText.text(originalText);
                    }
                });
            });
        });
        </script>

        <script>
        // Move license section below the settings form
        jQuery(document).ready(function($) {
            var $licenseSection = $('.lds-license-management').closest('[style*="max-width: 800px"]');
            var $form = $('form[action="options.php"]');

            if ($licenseSection.length && $form.length) {
                $licenseSection.insertAfter($form);
            }
        });
        </script>
        <?php
    }

    /**
     * Enqueue license management scripts
     */
    public function enqueue_license_scripts($hook) {
        // Only load on plugin settings page
        // Hook can be either 'toplevel_page_listeo-data-scraper' or 'listeo-scraper_page_lds-settings'
        if (strpos($hook, 'listeo') === false && strpos($hook, 'lds-settings') === false) {
            return;
        }

        // Localize script with nonce (correct handle is 'lds-admin-js')
        wp_localize_script('lds-admin-js', 'lds_license_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lds_license_nonce')
        ));
    }

    /**
     * Render license section introduction
     */
    public function render_license_section_intro() {
        $is_pro = LDS_Pro_Manager::is_pro_active();

        if ($is_pro) {
            echo '<div class="lds-license-section lds-license-active">';
            echo '<p class="lds-license-active-text">';
            echo '<span class="dashicons dashicons-yes-alt lds-license-active-icon"></span>';
            echo 'Pro Version Activated - All premium features are unlocked!';
            echo '</p>';
            echo '</div>';
        } else {
            echo '<div class="lds-license-section lds-license-inactive">';
            echo '<p class="lds-license-inactive-text">Enter your license key to unlock Pro features including AI descriptions, interactive map search, and bulk imports up to 60 listings.</p>';
            echo '<p><a href="' . esc_url(LDS_Pro_Manager::get_upgrade_url('license_page')) . '" target="_blank" class="button button-primary lds-get-pro-btn">';
            echo '<span class="dashicons dashicons-cart"></span>';
            echo 'Get Pro License';
            echo '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Render license management field - DEPRECATED
     * This method is no longer used. License management is now rendered
     * as a standalone card via render_license_section_card()
     */
    public function render_license_management_field() {
        // This method intentionally left empty
        // License section is now rendered separately
        return;
    }

    /**
     * AJAX: Activate License
     */
    public function ajax_activate_license() {
        check_ajax_referer('lds_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        $result = $this->license_manager->activate_license($license_key);

        if ($result['success']) {
            lds_log('License activated: ' . substr($license_key, 0, 8) . '...', 'LICENSE');
            wp_send_json_success($result);
        } else {
            lds_log('License activation failed: ' . $result['message'], 'LICENSE');
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Deactivate License
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('lds_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $result = $this->license_manager->deactivate_license();

        if ($result['success']) {
            lds_log('License deactivated', 'LICENSE');
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Validate License
     */
    public function ajax_validate_license() {
        check_ajax_referer('lds_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $is_valid = $this->license_manager->validate_license(true); // Force validation

        if ($is_valid) {
            wp_send_json_success([
                'message' => 'License validated successfully!',
                'reload' => false
            ]);
        } else {
            wp_send_json_error([
                'message' => 'License validation failed. Please check your license status.',
                'reload' => true
            ]);
        }
    }
}
