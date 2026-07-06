<?php
/**
 * Universal Settings Admin Interface
 *
 * Clean admin interface for configuring content sources
 *
 * @package Listeo_AI_Search
 * @since 1.6.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Universal_Settings {

    /**
     * Default post types that cannot be removed
     *
     * @var array
     */
    private $default_types = array();

    /**
     * Get whitelisted and enabled post types (delegates to Database Manager)
     *
     * @return array Filtered array of enabled post types
     */
    public static function get_enabled_post_types() {
        return Listeo_AI_Search_Database_Manager::get_enabled_post_types();
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_listeo_ai_get_post_type_stats', array($this, 'ajax_get_post_type_stats'));
        add_action('wp_ajax_listeo_ai_toggle_post_type', array($this, 'ajax_toggle_post_type'));
        add_action('wp_ajax_listeo_ai_get_posts_for_selection', array($this, 'ajax_get_posts_for_selection'));
        add_action('wp_ajax_listeo_ai_generate_selected_posts', array($this, 'ajax_generate_selected_posts'));
        add_action('wp_ajax_listeo_ai_get_total_count', array($this, 'ajax_get_total_count'));
        add_action('wp_ajax_listeo_ai_add_custom_post_types', array($this, 'ajax_add_custom_post_types'));
        add_action('wp_ajax_listeo_ai_remove_custom_post_type', array($this, 'ajax_remove_custom_post_type'));
        add_action('wp_ajax_listeo_ai_get_custom_type_count', array($this, 'ajax_get_custom_type_count'));
        add_action('wp_ajax_listeo_ai_get_bulk_post_ids', array($this, 'ajax_get_bulk_post_ids'));
        add_action('wp_ajax_listeo_ai_get_custom_fields_for_post_type', array($this, 'ajax_get_custom_fields_for_post_type'));
        add_action('wp_ajax_listeo_ai_suggest_custom_fields_for_post_type', array($this, 'ajax_suggest_custom_fields_for_post_type'));
        add_action('wp_ajax_listeo_ai_save_custom_fields_for_post_type', array($this, 'ajax_save_custom_fields_for_post_type'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings with explicit args for multisite compatibility
        $settings_args = array(
            'type' => 'array',
            'sanitize_callback' => null, // We handle sanitization in AJAX handlers
            'default' => array(),
        );

        register_setting('listeo_ai_content_sources', 'listeo_ai_search_enabled_post_types', $settings_args);
        register_setting('listeo_ai_content_sources', 'listeo_ai_search_custom_meta_fields', $settings_args);

        // Add allowed_options filter for multisite compatibility
        add_filter('allowed_options', array($this, 'add_allowed_options'));
    }

    /**
     * Add plugin options to allowed options list for multisite compatibility
     *
     * @param array $allowed_options Array of allowed options
     * @return array Modified array of allowed options
     */
    public function add_allowed_options($allowed_options) {
        $allowed_options['listeo_ai_content_sources'] = array(
            'listeo_ai_search_enabled_post_types',
            'listeo_ai_search_custom_meta_fields',
            'listeo_ai_search_custom_post_types',
            'listeo_ai_search_manual_selections',
        );

        return $allowed_options;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on Listeo AI Search admin page
        if ($hook !== 'toplevel_page_ai-chat-search') {
            return;
        }

        // Only load on database tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        if ($active_tab !== 'database') {
            return;
        }

        wp_enqueue_style(
            'listeo-ai-universal-settings',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/css/universal-settings.css',
            array(),
            LISTEO_AI_SEARCH_VERSION
        );

        wp_enqueue_script(
            'listeo-ai-universal-settings',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/js/universal-settings.js',
            array('jquery'),
            LISTEO_AI_SEARCH_VERSION,
            true
        );

        wp_localize_script('listeo-ai-universal-settings', 'listeoAiUniversalSettings', array(
            'ajax_url' => get_admin_url(get_current_blog_id(), 'admin-ajax.php'),
            'nonce' => wp_create_nonce('listeo_ai_universal_settings'),
            'database_nonce' => wp_create_nonce('listeo_ai_search_nonce'),
            'strings' => array(
                'confirm_toggle' => __('Enable embeddings for this content type?', 'ai-chat-search'),
                'confirm_reindex' => __('This will regenerate all embeddings for this content type. Continue?', 'ai-chat-search'),
                'reindexing' => __('Reindexing...', 'ai-chat-search'),
                'success' => __('Success!', 'ai-chat-search'),
                'error' => __('Error occurred', 'ai-chat-search'),
                'upload_documents' => __('Upload documents', 'ai-chat-search'),
                'add_external_pages' => __('Add external pages', 'ai-chat-search'),
                'manual_selection_active' => __('Manual selection active', 'ai-chat-search'),
                'clear' => __('Clear', 'ai-chat-search'),
                'manual_selection' => __('Manual selection', 'ai-chat-search'),
                'selected_of' => __('of', 'ai-chat-search'),
                'selected' => __('selected', 'ai-chat-search'),
                'indexed' => __('Indexed', 'ai-chat-search'),
                'pending' => __('Pending', 'ai-chat-search'),
                'verified' => __('Verified', 'ai-chat-search'),
                'error_loading_posts' => __('Error loading posts', 'ai-chat-search'),
                'error_loading_count' => __('Error loading content count', 'ai-chat-search'),
                'configure_custom_fields' => __('Configure Custom Fields', 'ai-chat-search'),
                'loading_custom_fields' => __('Loading custom fields...', 'ai-chat-search'),
                'no_custom_fields' => __('No custom fields found for this post type.', 'ai-chat-search'),
                'selected_fields' => __('selected fields', 'ai-chat-search'),
                'retrain_required' => __('Retrain this content type to update AI context.', 'ai-chat-search'),
                'listing_auto_fields' => __('Listing fields are selected automatically through the Listeo integration. No action is needed.', 'ai-chat-search'),
                'auto_detecting_fields' => __('Detecting fields...', 'ai-chat-search'),
                'auto_detected_fields' => __('Auto Detection selected suggested fields. Review them before saving.', 'ai-chat-search'),
                'auto_detected_fields_inline' => __('Success. Suggestions applied.', 'ai-chat-search'),
                'no_suggested_fields' => __('Auto Detection did not find fields to suggest.', 'ai-chat-search'),
                'no_manual_custom_fields' => __('No custom selection has been saved for this post type yet.', 'ai-chat-search'),
            )
        ));
    }

    /**
     * Render just the content sources cards (no wrapping)
     */
    public function render_content_sources_cards() {
        // Get enabled post types from option (raw, no defaults applied by helper)
        $enabled_post_types = get_option('listeo_ai_search_enabled_post_types', array('listing'));

        // Get custom types that have been added
        $custom_post_types_added = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types_added)) {
            $custom_post_types_added = array();
        }

        // Default types + added custom types (exclude ai_pdf_document from loop, it's rendered separately)
        $default_types = array('listing', 'post', 'page', 'product');
        $allowed_post_types = array_merge($default_types, $custom_post_types_added);

        // Store default types for checking if a type is custom (include ai_pdf_document for validation)
        $this->default_types = array_merge($default_types, array('ai_pdf_document'));

        // Get only allowed post types
        $post_types = array();
        foreach ($allowed_post_types as $post_type_name) {
            $post_type_obj = get_post_type_object($post_type_name);
            if ($post_type_obj) {
                $post_types[$post_type_name] = $post_type_obj;
            }
        }

        // Get detected custom post types that haven't been added yet
        // Counts are loaded lazily via AJAX to prevent timeout on large databases
        $detected_custom_types = Listeo_AI_Search_Database_Manager::get_detected_custom_post_types();
        $available_custom_types = array();

        foreach ($detected_custom_types as $custom_type) {
            if (!in_array($custom_type['name'], $custom_post_types_added)) {
                $custom_type['count'] = 0; // Placeholder, loaded via JS
                $available_custom_types[] = $custom_type;
            }
        }

        // Get manual selections once (cheap option lookup, no DB queries)
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());

        ?>
        <div class="listeo-ai-universal-settings">

            <?php $this->maybe_render_memory_limit_notice(); ?>

            <!-- Post Types Grid -->
            <div class="listeo-ai-post-types-grid" style="margin-top: 0;">
                <?php foreach ($post_types as $post_type):
                    $is_enabled = in_array($post_type->name, $enabled_post_types);
                    $is_custom = !in_array($post_type->name, $this->default_types);

                    // PRO FEATURE CHECK: Check if post type is locked
                    $is_locked = AI_Chat_Search_Pro_Manager::is_post_type_locked($post_type->name);

                    // Stats loaded lazily via AJAX to prevent timeout on large databases
                    $has_manual_selection = array_key_exists($post_type->name, $manual_selections);
                    $badge_class = 'loading';
                ?>
                    <div class="post-type-card <?php echo $is_enabled ? 'enabled' : ''; ?> <?php echo $is_custom ? 'is-custom' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>"
                         data-post-type="<?php echo esc_attr($post_type->name); ?>">

                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="post-type-icon">
                                <?php echo $this->get_post_type_icon($post_type->name); ?>
                            </div>
                            <div class="post-type-info">
                                <h3>
                                    <?php if ($is_locked): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                    <?php endif; ?>
                                    <?php if ($is_custom): ?>
                                    <span class="dashicons dashicons-no-alt delete-custom-type" data-post-type="<?php echo esc_attr($post_type->name); ?>" title="<?php _e('Remove this post type', 'ai-chat-search'); ?>"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html($post_type->label); ?>
                                    <?php if ($is_locked): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                    <?php else: ?>
                                        <span class="custom-type-badge <?php echo $badge_class; ?>">
                                            <span class="airs-spinner airs-spinner--small"></span>
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <code><?php echo esc_html($post_type->name); ?></code>

                                <?php if ($is_locked): ?>
                                    <!-- Locked state -->
                                    <div class="manual-selection-actions">
                                        <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('post_type_' . $post_type->name)); ?>"
                                           class="upgrade-link" target="_blank">
                                            <?php _e('Upgrade to Pro', 'ai-chat-search'); ?> →
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <!-- Manual selection link -->
                                    <div class="manual-selection-actions">
                                        <?php if ($has_manual_selection): ?>
                                            <a href="#" class="manual-selection-link active" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                                <span class="dashicons dashicons-yes"></span>
                                                <?php _e('Manual selection active', 'ai-chat-search'); ?>
                                            </a>
                                            <a href="#" class="clear-selection-link" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                                <?php _e('Clear', 'ai-chat-search'); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="#" class="manual-selection-link" data-post-type="<?php echo esc_attr($post_type->name); ?>">
                                                <span class="dashicons dashicons-admin-generic"></span>
                                                <?php _e('Manual selection', 'ai-chat-search'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Toggle Switch -->
                            <?php if (!$is_locked): ?>
                                <label class="toggle-switch">
                                    <input
                                        type="checkbox"
                                        class="post-type-toggle"
                                        value="<?php echo esc_attr($post_type->name); ?>"
                                        <?php checked($is_enabled); ?>
                                    >
                                    <span class="toggle-slider"></span>
                                </label>
                            <?php else: ?>
                                <div class="toggle-switch-locked" title="<?php _e('Pro version required', 'ai-chat-search'); ?>">
                                    <span class="dashicons dashicons-lock"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                // Render Documents card (always, not dependent on listing post type)
                $this->render_pdf_documents_card();
                ?>

                <?php
                // Render External Pages card (Pro feature)
                $this->render_external_pages_card();
                ?>

                <?php
                // Allow PRO plugin to add additional custom cards
                do_action('listeo_ai_after_post_types_grid');
                ?>
            </div>

            <?php if (!empty($available_custom_types)): ?>
            <div class="listeo-ai-custom-tools-row">
            <!-- Detected Custom Post Types Section -->
            <?php endif; ?>

            <?php if (!empty($available_custom_types)):
                // Check if custom post types feature is locked (FREE version)
                $custom_types_locked = !AI_Chat_Search_Pro_Manager::is_pro_active();
            ?>
            <div class="listeo-ai-custom-types-section <?php echo $custom_types_locked ? 'locked' : ''; ?>">
                <h3 class="collapsible-header" data-toggle="custom-types-content">
                    <span class="collapsible-header-text">
                        <?php if ($custom_types_locked): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                        <?php endif; ?>
                        <?php _e('Detected Custom Post Types', 'ai-chat-search'); ?>
                        <?php if ($custom_types_locked): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                        <?php else: ?>
                            <span class="custom-type-badge has-content">
                                <?php echo count($available_custom_types); ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </h3>
                <div id="custom-types-content" class="collapsible-content" style="display: none;">
                    <p class="custom-types-description"><?php _e('Select custom post types to add to your training content. Once added, they will appear above as cards.', 'ai-chat-search'); ?></p>
                    <div class="custom-types-checkboxes <?php echo $custom_types_locked ? 'disabled' : ''; ?>">
                    <?php foreach ($available_custom_types as $custom_type): ?>
                        <label>
                            <input type="checkbox"
                                   class="custom-post-type-checkbox"
                                   value="<?php echo esc_attr($custom_type['name']); ?>"
                                   <?php disabled($custom_types_locked); ?>>
                            <span>
                                <strong><?php echo esc_html($custom_type['label']); ?></strong>
                                <span class="custom-type-badge loading" data-custom-type="<?php echo esc_attr($custom_type['name']); ?>">
                                    <span class="airs-spinner airs-spinner--small"></span>
                                </span>
                                <br>
                                <code><?php echo esc_html($custom_type['name']); ?></code>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                    <button type="button"
                            id="add-custom-post-types-btn"
                            class="airs-button airs-button-primary"
                            <?php disabled($custom_types_locked); ?>>
                        <?php _e('Add Selected Types', 'ai-chat-search'); ?>
                    </button>
                </div> <!-- Close collapsible-content -->
            </div>
            <?php endif; ?>

            <?php if (!empty($available_custom_types)): ?>
            </div>
            <?php endif; ?>

            <!-- Manual Selection Modal -->
            <div id="manual-selection-modal" class="listeo-ai-modal" style="display: none;">
                <div class="listeo-ai-modal-overlay"></div>
                <div class="listeo-ai-modal-content">
                    <div class="listeo-ai-modal-header">
                        <h2 id="modal-title"><?php _e('Manual Selection', 'ai-chat-search'); ?></h2>
                        <button type="button" class="listeo-ai-modal-close">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="listeo-ai-modal-body">
                        <div class="listeo-ai-modal-controls">
                            <input type="text" id="modal-search" placeholder="<?php _e('Search...', 'ai-chat-search'); ?>" class="widefat" />
                            <div class="button-group">
                                <button type="button" id="select-all-posts" class="button button-small"><?php _e('Select All', 'ai-chat-search'); ?></button>
                                <button type="button" id="deselect-all-posts" class="button button-small"><?php _e('Deselect All', 'ai-chat-search'); ?></button>
                                <button type="button" id="select-pending-posts" class="button button-small"><?php _e('Select Pending Only', 'ai-chat-search'); ?></button>
                                <button type="button" id="select-verified-posts" class="button button-small" style="display:none;"><?php _e('Select Verified Only', 'ai-chat-search'); ?></button>
                            </div>
                        </div>
                        <div id="modal-posts-list" class="listeo-ai-posts-list">
                            <p class="loading-message"><span class="airs-spinner" style="margin-right: 6px;"></span><?php _e('Loading posts...', 'ai-chat-search'); ?></p>
                        </div>
                    </div>
                    <div class="listeo-ai-modal-footer">
                        <span id="modal-selection-count"></span>
                        <div class="modal-footer-buttons">
                            <button type="button" class="button" id="modal-cancel"><?php _e('Cancel', 'ai-chat-search'); ?></button>
                            <button type="button" class="button button-secondary" id="modal-save"><?php _e('Save Selection', 'ai-chat-search'); ?></button>
                            <button type="button" class="button button-primary" id="modal-train-now">
                                <svg class="airs-button-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                    <path d="M3 21v-5h5"></path>
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                    <path d="M16 8h5V3"></path>
                                </svg>
                                <?php _e('Train Now', 'ai-chat-search'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the advanced custom fields manager.
     */
    public function render_custom_fields_manager() {
        $custom_field_post_types = $this->get_custom_field_configurable_post_types();
        $listing_post_type = get_post_type_object('listing');
        if ($listing_post_type) {
            $custom_field_post_types = array('listing' => $listing_post_type) + $custom_field_post_types;
        }

        if (empty($custom_field_post_types)) {
            return;
        }

        ?>
        <div class="listeo-ai-custom-fields-config">
            <div>
                <h3><?php _e('Configure Custom Fields', 'ai-chat-search'); ?></h3>
            </div>
            <button type="button" id="configure-custom-fields-btn" class="airs-button airs-button-secondary">
                <svg class="airs-button-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                <?php _e('Manage Fields', 'ai-chat-search'); ?>
            </button>
        </div>

        <div id="custom-fields-modal" class="listeo-ai-modal" style="display: none;">
            <div class="listeo-ai-modal-overlay custom-fields-modal-close"></div>
            <div class="listeo-ai-modal-content listeo-ai-custom-fields-modal-content">
                <div class="listeo-ai-modal-header">
                    <h2><?php _e('Configure Custom Fields', 'ai-chat-search'); ?></h2>
                    <button type="button" class="custom-fields-modal-close listeo-ai-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="listeo-ai-modal-body">
                    <p class="custom-fields-modal-intro"><?php _e('Choose extra custom fields for your post types so the AI can use this additional data in answers.', 'ai-chat-search'); ?></p>
                    <div class="airs-help-text airs-blue" style="margin-bottom: 15px !important;">
                        <strong><?php _e('Retraining required', 'ai-chat-search'); ?></strong><br>
                        <?php _e('After changing custom fields, retrain the affected content type so the AI can use the updated data.', 'ai-chat-search'); ?>
                    </div>
                    <div class="listeo-ai-modal-controls custom-fields-controls">
                        <label for="custom-fields-post-type" class="screen-reader-text"><?php _e('Post type', 'ai-chat-search'); ?></label>
                        <select id="custom-fields-post-type" class="widefat">
                            <?php foreach ($custom_field_post_types as $post_type_name => $post_type_obj): ?>
                                <option value="<?php echo esc_attr($post_type_name); ?>">
                                    <?php echo esc_html($post_type_obj->label); ?> (<?php echo esc_html($post_type_name); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="button-group">
                            <button type="button" id="custom-fields-refresh" class="button button-small"><?php _e('Refresh', 'ai-chat-search'); ?></button>
                            <button type="button" id="custom-fields-select-all" class="button button-small"><?php _e('Select All', 'ai-chat-search'); ?></button>
                            <button type="button" id="custom-fields-deselect-all" class="button button-small"><?php _e('Deselect All', 'ai-chat-search'); ?></button>
                        </div>
                    </div>
                    <div id="custom-fields-ai-helper" class="custom-fields-ai-helper">
                        <strong><?php _e('Not sure which fields to choose?', 'ai-chat-search'); ?></strong>
                        <p><?php _e('Let AI review the field names and examples, then suggest useful fields.', 'ai-chat-search'); ?></p>
                        <button type="button" id="custom-fields-auto-detect" class="button button-secondary"><?php _e('Auto Detection', 'ai-chat-search'); ?></button>
                        <span id="custom-fields-auto-detect-status" class="custom-fields-ai-status" aria-live="polite"></span>
                    </div>
                    <div id="custom-fields-list" class="listeo-ai-custom-fields-list">
                        <p class="loading-message"><span class="airs-spinner" style="margin-right: 6px;"></span><?php _e('Select a post type to load custom fields.', 'ai-chat-search'); ?></p>
                    </div>
                </div>
                <div class="listeo-ai-modal-footer">
                    <span id="custom-fields-selection-count"></span>
                    <div class="modal-footer-buttons">
                        <button type="button" class="button custom-fields-modal-close"><?php _e('Cancel', 'ai-chat-search'); ?></button>
                        <button type="button" class="button button-primary" id="custom-fields-save"><?php _e('Save Fields', 'ai-chat-search'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Show a warning if PHP memory limit is too low for the amount of enabled content.
     */
    private function maybe_render_memory_limit_notice() {
        $wp_limit     = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '40M';
        $server_limit = ini_get('memory_limit');
        $wp_bytes     = wp_convert_hr_to_bytes($wp_limit);
        $server_bytes = wp_convert_hr_to_bytes($server_limit);

        // Unlimited server memory — no warning needed
        if ($server_bytes === -1) {
            return;
        }

        // Effective limit is whichever is lower
        $effective_bytes = min($wp_bytes, $server_bytes);

        // Count only enabled post types, respecting manual selections
        $enabled_post_types = get_option('listeo_ai_search_enabled_post_types', array('listing'));
        if (!is_array($enabled_post_types) || empty($enabled_post_types)) {
            return;
        }

        $manual_selections = get_option('listeo_ai_search_manual_selections', array());
        $total_posts = 0;
        foreach ($enabled_post_types as $pt) {
            if (array_key_exists($pt, $manual_selections)) {
                $selected_ids = is_array($manual_selections[$pt])
                    ? array_filter(array_map('intval', $manual_selections[$pt]))
                    : array();
                $total_posts += count($selected_ids);
            } else {
                $counts = wp_count_posts($pt);
                $total_posts += isset($counts->publish) ? (int) $counts->publish : 0;
            }
        }

        // Tiered memory recommendations based on content size
        $tiers = array(
            5000  => array('limit' => 1024, 'label' => '1 GB'),
            2500  => array('limit' => 512,  'label' => '512 MB'),
        );

        $recommended = null;
        foreach ($tiers as $threshold => $tier) {
            if ($total_posts >= $threshold) {
                $min_bytes = $tier['limit'] * 1024 * 1024;
                if ($effective_bytes < $min_bytes) {
                    $recommended = $tier;
                    break;
                }
            }
        }

        if ($recommended === null) {
            return;
        }

        ?>
        <div class="airs-notice airs-notice-warning airs-memory-notice" style="margin-bottom: 16px; padding: 14px 18px; border-radius: 6px; display: flex; align-items: flex-start; gap: 10px; border-left: 4px solid #ffc107; position: relative;">
            <span class="dashicons dashicons-warning" style="color: #856404; margin-top: 2px;"></span>
            <div style="flex: 1;">
                <strong><?php _e('Low PHP Memory Limit Detected', 'ai-chat-search'); ?></strong>
                <p style="margin: 6px 0 0;">
                    <?php
                    printf(
                        /* translators: 1: WP memory limit, 2: server memory limit, 3: total number of content items, 4: recommended memory size */
                        __('Your site has %3$s content items to train. WordPress memory limit is <strong>%1$s</strong> and server memory limit is <strong>%2$s</strong> — we recommend at least <strong>%4$s</strong> for both. Set <code>define(\'WP_MEMORY_LIMIT\', \'%4$s\');</code> in <code>wp-config.php</code> and increase the server limit in your hosting panel.', 'ai-chat-search'),
                        esc_html($wp_limit),
                        esc_html($server_limit),
                        '<strong>' . number_format_i18n($total_posts) . '</strong>',
                        esc_html($recommended['label'])
                    );
                    ?>
                </p>
            </div>
            <button type="button" class="airs-memory-notice-dismiss" style="background: none; border: none; cursor: pointer; padding: 0; margin: 0; line-height: 1; color: #856404; font-size: 18px; opacity: 0.7;" title="<?php esc_attr_e('Dismiss', 'ai-chat-search'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <script>
        jQuery(function($){
            $('.airs-memory-notice-dismiss').on('click', function(){
                $(this).closest('.airs-memory-notice').fadeOut(200);
            });
        });
        </script>
        <?php
    }

    /**
     * Render Documents card (PDF, TXT, MD, XML, CSV)
     */
    private function render_pdf_documents_card() {
        $post_type = 'ai_pdf_document';

        // Check if documents are enabled
        $enabled_types = get_option('listeo_ai_search_enabled_post_types', array('listing'));
        $is_enabled = in_array($post_type, $enabled_types);

        // Check if locked (PRO feature)
        $is_locked = AI_Chat_Search_Pro_Manager::is_post_type_locked($post_type);

        // Get document count
        $pdf_count = wp_count_posts($post_type);
        $total = isset($pdf_count->publish) ? $pdf_count->publish : 0;

        // Get indexed count
        global $wpdb;
        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $indexed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT e.listing_id)
             FROM {$embeddings_table} e
             INNER JOIN {$wpdb->posts} p ON e.listing_id = p.ID
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            $post_type
        ));

        $badge_class = $total > 0 ? 'has-content' : 'empty';
        ?>
        <div class="post-type-card pdf-card <?php echo $is_enabled ? 'enabled' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>"
             data-post-type="<?php echo esc_attr($post_type); ?>">

            <!-- Card Header -->
            <div class="card-header">
                <div class="post-type-icon">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
                <div class="post-type-info">
                    <h3>
                        <?php if ($is_locked): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                        <?php endif; ?>
                        <?php _e('Documents', 'ai-chat-search'); ?>
                        <?php if ($is_locked): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                        <?php else: ?>
                            <span class="custom-type-badge <?php echo $badge_class; ?>">
                                <?php echo number_format($total); ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <code>ai_document</code>

                    <?php if ($is_locked): ?>
                        <!-- Locked state -->
                        <div class="manual-selection-actions">
                            <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('pdf_documents')); ?>"
                               class="upgrade-link" target="_blank">
                                <?php _e('Upgrade to Pro', 'ai-chat-search'); ?> →
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Upload documents button (PRO active) -->
                        <div class="manual-selection-actions">
                            <a href="#" class="pdf-upload-link" id="upload-pdf-btn">
                                <span class="dashicons dashicons-upload"></span>
                                <?php _e('Upload documents', 'ai-chat-search'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Toggle Switch -->
                <?php if (!$is_locked): ?>
                    <label class="toggle-switch">
                        <input
                            type="checkbox"
                            class="post-type-toggle"
                            value="<?php echo esc_attr($post_type); ?>"
                            <?php checked($is_enabled); ?>
                        >
                        <span class="toggle-slider"></span>
                    </label>
                <?php else: ?>
                    <div class="toggle-switch-locked" title="<?php _e('Pro version required', 'ai-chat-search'); ?>">
                        <span class="dashicons dashicons-lock"></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render External Pages card (Pro feature)
     */
    private function render_external_pages_card() {
        $post_type = 'ai_external_page';

        // Check if external pages are enabled
        $enabled_types = get_option('listeo_ai_search_enabled_post_types', array());
        $is_enabled = in_array($post_type, $enabled_types);

        // Check if locked (Pro feature)
        $is_locked = AI_Chat_Search_Pro_Manager::is_post_type_locked($post_type);

        // Get total count
        global $wpdb;
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ai_external_page' AND post_status = 'publish'"
        );

        // Get indexed count
        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $indexed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT e.listing_id)
             FROM {$embeddings_table} e
             INNER JOIN {$wpdb->posts} p ON e.listing_id = p.ID
             WHERE p.post_type = %s AND p.post_status = 'publish'",
            $post_type
        ));

        $badge_class = $total > 0 ? 'has-content' : 'empty';
        ?>
        <div class="post-type-card external-pages-card <?php echo $is_enabled ? 'enabled' : ''; ?> <?php echo $is_locked ? 'locked' : ''; ?>"
             data-post-type="<?php echo esc_attr($post_type); ?>">

            <!-- Card Header -->
            <div class="card-header">
                <div class="post-type-icon">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                </div>
                <div class="post-type-info">
                    <h3>
                        <?php if ($is_locked): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                        <?php endif; ?>
                        <?php _e('External Pages', 'ai-chat-search'); ?>
                        <?php if ($is_locked): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                        <?php else: ?>
                            <span class="custom-type-badge <?php echo $badge_class; ?>">
                                <?php echo number_format($total); ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <code>ai_external_page</code>

                    <?php if ($is_locked): ?>
                        <!-- Locked state -->
                        <div class="manual-selection-actions">
                            <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('external_pages')); ?>"
                               class="upgrade-link" target="_blank">
                                <?php _e('Upgrade to Pro', 'ai-chat-search'); ?> →
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Add external pages button (PRO active) -->
                        <div class="manual-selection-actions">
                            <a href="#" class="external-pages-link" id="manage-external-pages-btn">
                                <span class="dashicons dashicons-admin-site-alt3"></span>
                                <?php _e('Add external pages', 'ai-chat-search'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Toggle Switch -->
                <?php if (!$is_locked): ?>
                    <label class="toggle-switch">
                        <input
                            type="checkbox"
                            class="post-type-toggle"
                            value="<?php echo esc_attr($post_type); ?>"
                            <?php checked($is_enabled); ?>
                        >
                        <span class="toggle-slider"></span>
                    </label>
                <?php else: ?>
                    <div class="toggle-switch-locked" title="<?php _e('Pro version required', 'ai-chat-search'); ?>">
                        <span class="dashicons dashicons-lock"></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get post type icon
     */
    private function get_post_type_icon($post_type) {
        $icons = array(
            'listing' => 'dashicons-location-alt',
            'post' => 'dashicons-admin-post',
            'page' => 'dashicons-admin-page',
            'product' => 'dashicons-products',
            'attachment' => 'dashicons-admin-media',
        );

        $icon_class = isset($icons[$post_type]) ? $icons[$post_type] : 'dashicons-admin-generic';

        return '<span class="dashicons ' . $icon_class . '"></span>';
    }

    /**
     * Get post type statistics
     * Supports three states:
     * 1. No manual selection → count all posts
     * 2. Manual selection with 0 posts → count 0
     * 3. Manual selection with specific IDs → count those
     *
     * Posts are considered "indexed" if they have:
     * - A direct embedding, OR
     * - Content chunks (which have their own embeddings)
     */
    private function get_post_type_stats($post_type) {
        global $wpdb;

        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $chunk_post_type = Listeo_AI_Content_Chunker::CHUNK_POST_TYPE;

        // Check if there's a manual selection for this post type
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());
        $has_manual_selection = array_key_exists($post_type, $manual_selections);

        if ($has_manual_selection) {
            // Manual selection active
            $selected_ids = is_array($manual_selections[$post_type])
                ? array_filter(array_map('intval', $manual_selections[$post_type]))
                : array();

            if (empty($selected_ids)) {
                return array(
                    'total' => 0,
                    'indexed' => 0,
                    'pending' => 0,
                    'has_manual_selection' => true
                );
            }

            $placeholders = implode(',', array_fill(0, count($selected_ids), '%d'));

            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE ID IN ($placeholders) AND post_status = 'publish'",
                ...$selected_ids
            ));

            // Count indexed — query from embeddings/chunks side (fast, uses indexes)
            // Direct embeddings among selected posts
            $direct = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$embeddings_table} e
                 WHERE e.listing_id IN ($placeholders)",
                ...$selected_ids
            ));

            // Chunk-covered posts among selected (no direct embedding)
            $via_chunks = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} chunk ON pm.post_id = chunk.ID
                 WHERE chunk.post_type = %s
                 AND pm.meta_key = '_chunk_parent_id'
                 AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
                 AND CAST(pm.meta_value AS UNSIGNED) NOT IN (
                     SELECT e2.listing_id FROM {$embeddings_table} e2
                     WHERE e2.listing_id IN ($placeholders)
                 )",
                ...array_merge(array($chunk_post_type), $selected_ids, $selected_ids)
            ));

            $indexed = $direct + $via_chunks;
        } else {
            // No manual selection — count all published posts
            if ($post_type === 'product') {
                // Exclude listeo-booking products
                $total = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                     WHERE p.post_type = %s AND p.post_status = 'publish'
                     AND NOT EXISTS (
                         SELECT 1 FROM {$wpdb->term_relationships} tr
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                         INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                         WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat' AND t.slug = 'listeo-booking'
                     )",
                    $post_type
                ));
            } else {
                $total = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = %s AND post_status = 'publish'",
                    $post_type
                ));
            }

            // Count indexed — query from embeddings/chunks side (fast, starts from smaller tables)
            // Posts with direct embedding
            $direct = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT e.listing_id)
                 FROM {$embeddings_table} e
                 INNER JOIN {$wpdb->posts} p ON e.listing_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = 'publish'",
                $post_type
            ));

            // Posts covered by chunks but without direct embedding
            $via_chunks = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} chunk ON pm.post_id = chunk.ID
                 INNER JOIN {$wpdb->posts} parent ON CAST(pm.meta_value AS UNSIGNED) = parent.ID
                 WHERE chunk.post_type = %s
                 AND pm.meta_key = '_chunk_parent_id'
                 AND parent.post_type = %s
                 AND parent.post_status = 'publish'
                 AND parent.ID NOT IN (
                     SELECT e2.listing_id FROM {$embeddings_table} e2
                 )",
                $chunk_post_type,
                $post_type
            ));

            $indexed = $direct + $via_chunks;
        }

        return array(
            'total' => $total,
            'indexed' => $indexed,
            'pending' => max(0, $total - $indexed),
            'has_manual_selection' => $has_manual_selection
        );
    }

    /**
     * Get post types that can use explicit custom field selection.
     *
     * Listings are intentionally excluded because they use the dedicated Listeo extractor.
     *
     * @param array|null $post_types Optional post type objects already loaded for the UI.
     * @return array
     */
    private function get_custom_field_configurable_post_types($post_types = null) {
        if ($post_types === null) {
            $custom_post_types_added = get_option('listeo_ai_search_custom_post_types', array());
            if (!is_array($custom_post_types_added)) {
                $custom_post_types_added = array();
            }

            $post_type_names = array_merge(array('post', 'page', 'product'), $custom_post_types_added);
            $post_types = array();
            foreach ($post_type_names as $post_type_name) {
                $post_type_obj = get_post_type_object($post_type_name);
                if ($post_type_obj) {
                    $post_types[$post_type_name] = $post_type_obj;
                }
            }
        }

        $configurable = array();
        foreach ($post_types as $post_type_name => $post_type_obj) {
            if (!$this->is_custom_field_configurable_post_type($post_type_name)) {
                continue;
            }

            $configurable[$post_type_name] = $post_type_obj;
        }

        return $configurable;
    }

    /**
     * Check whether a post type may use the explicit custom field selector.
     *
     * @param string $post_type Post type.
     * @return bool
     */
    private function is_custom_field_configurable_post_type($post_type) {
        if (!post_type_exists($post_type)) {
            return false;
        }

        $excluded_post_types = array(
            'listing',
            'ai_pdf_document',
            'ai_external_page',
            'ai_content_chunk',
            'attachment',
            'revision',
            'nav_menu_item',
        );

        if (in_array($post_type, $excluded_post_types, true)) {
            return false;
        }

        if (AI_Chat_Search_Pro_Manager::is_post_type_locked($post_type)) {
            return false;
        }

        $custom_post_types_added = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types_added)) {
            $custom_post_types_added = array();
        }

        $allowed_post_types = array_merge(array('post', 'page', 'product'), $custom_post_types_added);

        return in_array($post_type, $allowed_post_types, true);
    }

    /**
     * Check whether Auto Config may inspect fields for a detected CPT before it is saved.
     *
     * This intentionally does not change the normal custom-fields modal rules.
     *
     * @param string $post_type Post type.
     * @return bool
     */
    private function is_detected_custom_field_candidate_post_type($post_type) {
        if (!post_type_exists($post_type)) {
            return false;
        }

        if (class_exists('AI_Chat_Search_Pro_Manager') && AI_Chat_Search_Pro_Manager::is_post_type_locked($post_type)) {
            return false;
        }

        if (!class_exists('Listeo_AI_Search_Database_Manager')) {
            return false;
        }

        $detected = Listeo_AI_Search_Database_Manager::get_detected_custom_post_types();
        if (!is_array($detected)) {
            return false;
        }

        foreach ($detected as $name => $data) {
            $slug = is_array($data) && !empty($data['name']) ? sanitize_key((string) $data['name']) : sanitize_key((string) $name);
            if ($slug === $post_type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read saved custom field config in the normalized option shape.
     *
     * @return array
     */
    private function get_custom_fields_config() {
        $config = get_option('listeo_ai_search_custom_meta_fields', array());

        return is_array($config) ? $config : array();
    }

    /**
     * Get selected custom fields for a post type.
     *
     * @param string $post_type Post type.
     * @return array
     */
    private function get_selected_custom_fields_for_post_type($post_type) {
        $config = $this->get_custom_fields_config();

        if (!isset($config[$post_type]) || !is_array($config[$post_type])) {
            return array();
        }

        if (isset($config[$post_type]['fields']) && is_array($config[$post_type]['fields'])) {
            return array_values(array_filter(array_map('strval', $config[$post_type]['fields'])));
        }

        return array_values(array_filter(array_map('strval', $config[$post_type])));
    }

    /**
     * Detect a compact value type label for the custom field UI.
     *
     * @param mixed $value Meta value.
     * @return string
     */
    private function detect_meta_value_type($value) {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_serialized($value)) {
            return 'serialized';
        }

        if (is_numeric($value)) {
            return 'number';
        }

        if (is_string($value) && preg_match('/^\s*[\[{]/', $value) && json_decode($value, true) !== null) {
            return 'json';
        }

        if (is_string($value) && preg_match('/<[^>]+>/', $value)) {
            return 'html';
        }

        return 'text';
    }

    /**
     * Format a sample meta value for display.
     *
     * @param mixed $value Meta value.
     * @return string
     */
    private function format_meta_sample($value) {
        if (is_serialized($value)) {
            $value = maybe_unserialize($value);
        } elseif (is_string($value) && preg_match('/^\s*[\[{]/', $value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }

        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        if (strlen($value) > 260) {
            $value = substr($value, 0, 260) . '...';
        }

        return $value;
    }

    /**
     * Get available custom fields for a configurable post type.
     *
     * @param string $post_type Post type.
     * @param array  $args Optional behavior flags.
     * @return array|WP_Error
     */
    public function get_available_custom_fields_for_post_type($post_type, $args = array()) {
        $post_type = sanitize_text_field((string) $post_type);
        $args = wp_parse_args($args, array(
            'allow_detected_custom_type' => false,
        ));

        if (
            !$this->is_custom_field_configurable_post_type($post_type) &&
            (empty($args['allow_detected_custom_type']) || !$this->is_detected_custom_field_candidate_post_type($post_type))
        ) {
            return new WP_Error('invalid_post_type', __('Post type is not available for custom field selection', 'ai-chat-search'));
        }

        global $wpdb;

        $where = array(
            $wpdb->prepare('p.post_type = %s', $post_type),
            "p.post_status = 'publish'",
            "pm.meta_key <> ''",
        );

        if ($post_type === 'product') {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat' AND t.slug = 'listeo-booking'
            )";
        }

        $where_clause = implode(' AND ', $where);
        $rows = $wpdb->get_results(
            "SELECT pm.meta_key,
                    COUNT(DISTINCT pm.post_id) AS usage_count,
                    MIN(CASE WHEN pm.meta_value <> '' THEN pm.meta_id ELSE NULL END) AS sample_meta_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE {$where_clause}
             GROUP BY pm.meta_key
             ORDER BY pm.meta_key ASC",
            ARRAY_A
        );

        $sample_ids = array();
        foreach ($rows as $row) {
            if (!empty($row['sample_meta_id'])) {
                $sample_ids[] = (int) $row['sample_meta_id'];
            }
        }

        $samples = array();
        if (!empty($sample_ids)) {
            $placeholders = implode(',', array_fill(0, count($sample_ids), '%d'));
            $sample_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id IN ({$placeholders})",
                    ...$sample_ids
                ),
                ARRAY_A
            );

            foreach ($sample_rows as $sample_row) {
                $samples[(int) $sample_row['meta_id']] = $sample_row['meta_value'];
            }
        }

        $selected_fields = $this->get_selected_custom_fields_for_post_type($post_type);
        $fields = array();

        foreach ($rows as $row) {
            $sample_id = !empty($row['sample_meta_id']) ? (int) $row['sample_meta_id'] : 0;
            $sample_value = $sample_id && isset($samples[$sample_id]) ? $samples[$sample_id] : '';

            $fields[] = array(
                'meta_key' => $row['meta_key'],
                'usage_count' => (int) $row['usage_count'],
                'type' => $this->detect_meta_value_type($sample_value),
                'sample' => $this->format_meta_sample($sample_value),
                'selected' => in_array($row['meta_key'], $selected_fields, true),
            );
        }

        return array(
            'post_type' => $post_type,
            'fields' => $fields,
            'selected_fields' => $selected_fields,
            'has_manual_config' => array_key_exists($post_type, $this->get_custom_fields_config()),
        );
    }

    /**
     * AJAX: Get custom fields for a post type.
     */
    public function ajax_get_custom_fields_for_post_type() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat-search'));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        $result = $this->get_available_custom_fields_for_post_type($post_type);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Get the cheap suggestion model for the current provider.
     *
     * @param Listeo_AI_Provider $provider AI provider instance.
     * @return string
     */
    private function get_custom_fields_suggestion_model($provider) {
        switch ($provider->get_provider()) {
            case 'openrouter':
                return 'openai/gpt-5.4-nano';
            case 'gemini':
                return 'gemini-3.1-flash-lite';
            case 'mistral':
                return 'mistral-small-latest';
            default:
                return 'gpt-5.4-nano';
        }
    }

    /**
     * Normalize custom field data received from the UI for AI suggestions.
     *
     * @param mixed $raw_fields Raw fields.
     * @return array
     */
    private function normalize_custom_fields_for_suggestion($raw_fields) {
        if (!is_array($raw_fields)) {
            return array();
        }

        $fields = array();
        foreach ($raw_fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $meta_key = isset($field['meta_key']) ? sanitize_text_field($field['meta_key']) : '';
            if ($meta_key === '' || strlen($meta_key) > 255) {
                continue;
            }

            $sample = isset($field['sample']) ? wp_strip_all_tags((string) $field['sample']) : '';
            $sample = preg_replace('/\s+/', ' ', trim($sample));
            if (strlen($sample) > 500) {
                $sample = substr($sample, 0, 500);
            }

            $fields[] = array(
                'meta_key' => $meta_key,
                'type' => isset($field['type']) ? sanitize_text_field($field['type']) : '',
                'usage_count' => isset($field['usage_count']) ? absint($field['usage_count']) : 0,
                'sample' => $sample,
            );

            if (count($fields) >= 120) {
                break;
            }
        }

        return $fields;
    }

    /**
     * Parse AI custom field suggestions.
     *
     * @param array $data Provider response body.
     * @param array $allowed_keys Allowed meta keys.
     * @return array|WP_Error
     */
    private function parse_custom_fields_suggestion_response($data, $allowed_keys) {
        if (empty($data['choices'][0]['message']['content'])) {
            return new WP_Error('no_content', __('Empty response from provider', 'ai-chat-search'));
        }

        $content = trim($data['choices'][0]['message']['content']);
        $content = preg_replace('/^```(?:json)?\s*|\s*```\s*$/m', '', $content);
        $json = json_decode($content, true);

        if (!is_array($json)) {
            return new WP_Error('parse_failed', __('Could not parse Auto Detection response.', 'ai-chat-search'));
        }

        if (isset($json['fields']) && is_array($json['fields'])) {
            $suggested = $json['fields'];
        } elseif (isset($json['selected_fields']) && is_array($json['selected_fields'])) {
            $suggested = $json['selected_fields'];
        } elseif (array_values($json) === $json) {
            $suggested = $json;
        } else {
            $suggested = array();
        }

        $allowed_lookup = array_fill_keys($allowed_keys, true);
        $fields = array();
        foreach ($suggested as $field) {
            if (is_array($field) && isset($field['meta_key'])) {
                $field = $field['meta_key'];
            }

            if (!is_string($field)) {
                continue;
            }

            $field = sanitize_text_field($field);
            if (isset($allowed_lookup[$field])) {
                $fields[] = $field;
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * Suggest useful custom fields with the configured AI provider.
     *
     * @param string $post_type Post type.
     * @param mixed  $raw_fields Optional field list. If omitted, fields are discovered first.
     * @param array  $args Optional behavior flags.
     * @return array|WP_Error
     */
    public function suggest_custom_fields_for_post_type($post_type, $raw_fields = null, $args = array()) {
        $post_type = sanitize_text_field((string) $post_type);
        $args = wp_parse_args($args, array(
            'allow_detected_custom_type' => false,
        ));

        if (
            !$this->is_custom_field_configurable_post_type($post_type) &&
            (empty($args['allow_detected_custom_type']) || !$this->is_detected_custom_field_candidate_post_type($post_type))
        ) {
            return new WP_Error('invalid_post_type', __('Post type is not available for custom field selection', 'ai-chat-search'));
        }

        if ($raw_fields === null) {
            $available_fields = $this->get_available_custom_fields_for_post_type($post_type, array(
                'allow_detected_custom_type' => !empty($args['allow_detected_custom_type']),
            ));
            if (is_wp_error($available_fields)) {
                return $available_fields;
            }

            $raw_fields = $available_fields['fields'];
        }

        $fields = $this->normalize_custom_fields_for_suggestion($raw_fields);

        if (empty($fields)) {
            return new WP_Error('no_fields', __('No custom fields available for Auto Detection.', 'ai-chat-search'));
        }

        $provider = new Listeo_AI_Provider();
        if (empty($provider->get_api_key())) {
            return new WP_Error('missing_api_key', __('No AI provider API key is configured.', 'ai-chat-search'));
        }

        $allowed_keys = wp_list_pluck($fields, 'meta_key');
        $system = 'You help a WordPress admin choose custom fields that are useful for chatbot answers and search training. Return ONLY valid JSON in this exact shape: {"fields":["meta_key"]}. Select fields likely to contain human-readable content, product details, ingredients, tabs, descriptions, specifications, FAQs, or other useful text. Avoid technical IDs, hashes, counters, cache keys, SEO metadata, image IDs, layout settings, empty fields, and purely internal builder settings unless the sample clearly contains readable content. Select at most 12 fields.';
        $user = wp_json_encode(array(
            'post_type' => $post_type,
            'fields' => $fields,
        ));

        $payload = array(
            'model' => $this->get_custom_fields_suggestion_model($provider),
            'messages' => array(
                array('role' => 'system', 'content' => $system),
                array('role' => 'user', 'content' => $user),
            ),
            'response_format' => array('type' => 'json_object'),
        );

        $payload = $provider->normalize_chat_payload($payload, array(
            'max_tokens' => 900,
            'temperature' => 0.1,
            'reasoning' => 'low',
        ));

        $response = wp_remote_post($provider->get_endpoint('chat'), array(
            'headers' => $provider->get_headers(),
            'body' => wp_json_encode($payload),
            'timeout' => 45,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code !== 200) {
            return new WP_Error('provider_error', sprintf(__('AI provider error: HTTP %d', 'ai-chat-search'), $code));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new WP_Error('invalid_response', __('Could not parse Auto Detection response.', 'ai-chat-search'));
        }

        $suggested_fields = $this->parse_custom_fields_suggestion_response($data, $allowed_keys);

        if (is_wp_error($suggested_fields)) {
            return $suggested_fields;
        }

        return array(
            'suggested_fields' => $suggested_fields,
            'model' => isset($data['model']) ? sanitize_text_field($data['model']) : $this->get_custom_fields_suggestion_model($provider),
        );
    }

    /**
     * AJAX: Suggest useful custom fields with the configured AI provider.
     */
    public function ajax_suggest_custom_fields_for_post_type() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat-search'));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        $fields_json = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : '';
        $raw_fields = json_decode((string) $fields_json, true);
        $result = $this->suggest_custom_fields_for_post_type($post_type, $raw_fields);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Save custom field selection for a post type.
     *
     * Empty field selections are saved only when allow_empty is explicitly true.
     *
     * @param string $post_type Post type.
     * @param array  $raw_fields Raw selected field keys.
     * @param array  $args Save options.
     * @return array|WP_Error
     */
    public function save_custom_fields_for_post_type($post_type, $raw_fields = array(), $args = array()) {
        $post_type = sanitize_text_field((string) $post_type);
        if (!$this->is_custom_field_configurable_post_type($post_type)) {
            return new WP_Error('invalid_post_type', __('Post type is not available for custom field selection', 'ai-chat-search'));
        }

        $args = wp_parse_args($args, array(
            'reset' => false,
            'allow_empty' => false,
        ));

        $config = $this->get_custom_fields_config();
        $reset = (bool) $args['reset'];

        if ($reset) {
            unset($config[$post_type]);
        } else {
            $raw_fields = is_array($raw_fields) ? $raw_fields : array();
            $fields = array();

            foreach ($raw_fields as $field) {
                if (is_array($field) || is_object($field)) {
                    continue;
                }

                $field = sanitize_text_field($field);
                $field = trim($field);

                if ($field === '' || strlen($field) > 255) {
                    continue;
                }

                $fields[] = $field;
            }

            $fields = array_values(array_unique($fields));

            if (empty($fields) && !$args['allow_empty']) {
                return new WP_Error('empty_fields_not_allowed', __('No custom fields selected.', 'ai-chat-search'));
            }

            if (!empty($fields)) {
                $available_fields = $this->get_available_custom_fields_for_post_type($post_type);
                if (is_wp_error($available_fields)) {
                    return $available_fields;
                }

                $allowed_keys = array_fill_keys(wp_list_pluck($available_fields['fields'], 'meta_key'), true);
                $invalid_fields = array();

                foreach ($fields as $field) {
                    if (!isset($allowed_keys[$field])) {
                        $invalid_fields[] = $field;
                    }
                }

                if (!empty($invalid_fields)) {
                    return new WP_Error('invalid_fields', __('One or more selected custom fields are not available for this post type.', 'ai-chat-search'));
                }
            }

            $config[$post_type] = array(
                'fields' => $fields,
                'updated_at' => time(),
            );
        }

        unset($config['listing']);

        update_option('listeo_ai_search_custom_meta_fields', $config, false);
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('listeo_ai_search_custom_meta_fields', 'options');

        return array(
            'message' => $reset
                ? __('Custom field selection cleared.', 'ai-chat-search')
                : __('Custom field selection saved. Retrain this content type to update AI context.', 'ai-chat-search'),
            'post_type' => $post_type,
            'selected_fields' => $reset ? array() : $config[$post_type]['fields'],
            'has_manual_config' => !$reset,
        );
    }

    /**
     * AJAX: Save custom field selection for a post type.
     */
    public function ajax_save_custom_fields_for_post_type() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-chat-search'));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
        $reset = isset($_POST['reset']) && $_POST['reset'] === 'true';
        $raw_fields = isset($_POST['fields']) ? (array) wp_unslash($_POST['fields']) : array();
        $result = $this->save_custom_fields_for_post_type($post_type, $raw_fields, array(
            'reset' => $reset,
            'allow_empty' => true,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get post type stats
     */
    public function ajax_get_post_type_stats() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $stats = $this->get_post_type_stats($post_type);

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Get published post count for a custom post type
     */
    public function ajax_get_custom_type_count() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $post_type = sanitize_text_field($_POST['post_type']);
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));

        wp_send_json_success(array('count' => $count));
    }

    /**
     * AJAX: Toggle post type
     */
    public function ajax_toggle_post_type() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_type = sanitize_text_field($_POST['post_type']);

        // Handle boolean conversion properly (AJAX sends 'true'/'false' as strings or 1/0)
        $enabled_raw = $_POST['enabled'] ?? false;
        $enabled = filter_var($enabled_raw, FILTER_VALIDATE_BOOLEAN);

        // Validate post type exists
        if (!post_type_exists($post_type)) {
            wp_send_json_error('Invalid post type');
        }

        // Get custom types that have been added
        $custom_post_types_added = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types_added)) {
            $custom_post_types_added = array();
        }

        // Validate post type is either default or has been added as custom
        $default_types = array('listing', 'post', 'page', 'product', 'ai_pdf_document', 'ai_external_page');
        $allowed_post_types = array_merge($default_types, $custom_post_types_added);
        if (!in_array($post_type, $allowed_post_types)) {
            wp_send_json_error('Post type not available for training');
        }

        // Get current enabled post types (raw, no filtering)
        $enabled_post_types = get_option('listeo_ai_search_enabled_post_types', array('listing'));
        if (!is_array($enabled_post_types)) {
            $enabled_post_types = array('listing');
        }

        if ($enabled) {
            // Enable: Add if not already in array
            if (!in_array($post_type, $enabled_post_types)) {
                $enabled_post_types[] = $post_type;
            }
        } else {
            // Disable: Remove from array
            $enabled_post_types = array_diff($enabled_post_types, array($post_type));

            // Allow empty array - user can disable all post types to show 0 counts
        }

        // Save the option
        $enabled_post_types = array_values($enabled_post_types); // Re-index
        update_option('listeo_ai_search_enabled_post_types', $enabled_post_types);

        wp_send_json_success(array(
            'message' => $enabled ? __('Post type enabled', 'ai-chat-search') : __('Post type disabled', 'ai-chat-search'),
            'enabled_post_types' => $enabled_post_types
        ));
    }

    /**
     * AJAX: Get posts for manual selection (paginated, with server-side search)
     */
    public function ajax_get_posts_for_selection() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $per_page  = isset($_POST['per_page']) ? absint($_POST['per_page']) : 50;
        $page      = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        $search    = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $filter    = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : ''; // 'pending', 'verified', 'indexed'

        // Validate post type exists
        if (!post_type_exists($post_type)) {
            wp_send_json_error('Invalid post type');
        }

        // Validate post type is allowed
        $custom_post_types_added = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types_added)) {
            $custom_post_types_added = array();
        }
        $default_types = array('listing', 'post', 'page', 'product', 'ai_pdf_document', 'ai_external_page');
        $allowed_post_types = array_merge($default_types, $custom_post_types_added);
        if (!in_array($post_type, $allowed_post_types)) {
            wp_send_json_error('Post type not available for training');
        }

        global $wpdb;
        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $chunk_post_type  = Listeo_AI_Content_Chunker::CHUNK_POST_TYPE;

        // Pre-collect chunk parent IDs (single fast query instead of per-row EXISTS)
        $chunk_parents_subquery = $wpdb->prepare(
            "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) AS parent_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} chunk ON pm.post_id = chunk.ID
             WHERE chunk.post_type = %s AND pm.meta_key = '_chunk_parent_id'",
            $chunk_post_type
        );

        // Build WHERE conditions
        $where = array();
        $where[] = $wpdb->prepare("p.post_type = %s", $post_type);
        $where[] = "p.post_status = 'publish'";

        // Search filter
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare("p.post_title LIKE %s", $like);
        }

        // Product exclusion
        if ($post_type === 'product') {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat' AND t.slug = 'listeo-booking'
            )";
        }

        // Status filter (pending/indexed)
        if ($filter === 'pending') {
            $where[] = "e.listing_id IS NULL AND cp.parent_id IS NULL";
        } elseif ($filter === 'indexed') {
            $where[] = "(e.listing_id IS NOT NULL OR cp.parent_id IS NOT NULL)";
        } elseif ($filter === 'verified' && $post_type === 'listing') {
            $where[] = "pm_v.meta_value = 'on'";
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;

        // Build SELECT columns
        $extra_select = '';
        $extra_join = '';
        if ($post_type === 'listing') {
            $extra_select = ", CASE WHEN pm_v.meta_value = 'on' THEN 1 ELSE 0 END as is_verified";
            $extra_join = "LEFT JOIN {$wpdb->postmeta} pm_v ON p.ID = pm_v.post_id AND pm_v.meta_key = '_verified'";
        }

        // Get total count for pagination
        $total_query = "SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$embeddings_table} e ON p.ID = e.listing_id
            LEFT JOIN ({$chunk_parents_subquery}) cp ON p.ID = cp.parent_id
            {$extra_join}
            WHERE {$where_clause}";
        $total_count = (int) $wpdb->get_var($total_query);

        // Get paginated posts
        $posts = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_modified,
                   CASE WHEN e.listing_id IS NOT NULL OR cp.parent_id IS NOT NULL THEN 1 ELSE 0 END as has_embedding
                   {$extra_select}
            FROM {$wpdb->posts} p
            LEFT JOIN {$embeddings_table} e ON p.ID = e.listing_id
            LEFT JOIN ({$chunk_parents_subquery}) cp ON p.ID = cp.parent_id
            {$extra_join}
            WHERE {$where_clause}
            ORDER BY p.post_title ASC
            LIMIT {$per_page} OFFSET {$offset}
        ", ARRAY_A);

        // Get currently selected post IDs
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());
        $selected_ids = isset($manual_selections[$post_type]) ? $manual_selections[$post_type] : array();

        wp_send_json_success(array(
            'posts'           => $posts ?: array(),
            'post_type'       => $post_type,
            'post_type_label' => get_post_type_object($post_type)->label,
            'selected_ids'    => $selected_ids,
            'page'            => $page,
            'per_page'        => $per_page,
            'total'           => $total_count,
            'total_pages'     => ceil($total_count / $per_page),
        ));
    }

    /**
     * AJAX: Get all post IDs matching a filter (for bulk select operations)
     */
    public function ajax_get_bulk_post_ids() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $filter    = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';

        if (!post_type_exists($post_type)) {
            wp_send_json_error('Invalid post type');
        }

        global $wpdb;
        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $chunk_post_type  = Listeo_AI_Content_Chunker::CHUNK_POST_TYPE;

        $chunk_parents_subquery = $wpdb->prepare(
            "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) AS parent_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} chunk ON pm.post_id = chunk.ID
             WHERE chunk.post_type = %s AND pm.meta_key = '_chunk_parent_id'",
            $chunk_post_type
        );

        $where = array();
        $where[] = $wpdb->prepare("p.post_type = %s", $post_type);
        $where[] = "p.post_status = 'publish'";

        if ($post_type === 'product') {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat' AND t.slug = 'listeo-booking'
            )";
        }

        $extra_join = '';
        if ($filter === 'pending') {
            $where[] = "e.listing_id IS NULL AND cp.parent_id IS NULL";
        } elseif ($filter === 'indexed') {
            $where[] = "(e.listing_id IS NOT NULL OR cp.parent_id IS NOT NULL)";
        } elseif ($filter === 'verified' && $post_type === 'listing') {
            $extra_join = "LEFT JOIN {$wpdb->postmeta} pm_v ON p.ID = pm_v.post_id AND pm_v.meta_key = '_verified'";
            $where[] = "pm_v.meta_value = 'on'";
        }

        $where_clause = implode(' AND ', $where);

        $ids = $wpdb->get_col("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$embeddings_table} e ON p.ID = e.listing_id
            LEFT JOIN ({$chunk_parents_subquery}) cp ON p.ID = cp.parent_id
            {$extra_join}
            WHERE {$where_clause}
        ");

        wp_send_json_success(array(
            'ids' => array_map('intval', $ids),
        ));
    }

    /**
     * AJAX: Save manual selection for a post type
     */
    public function ajax_generate_selected_posts() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', (array) $_POST['post_ids']) : array();

        // Validate post type exists
        if (!post_type_exists($post_type)) {
            wp_send_json_error('Invalid post type');
        }

        // Get custom types that have been added
        $custom_post_types_added = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types_added)) {
            $custom_post_types_added = array();
        }

        // Validate post type is either default or has been added as custom
        $default_types = array('listing', 'post', 'page', 'product', 'ai_pdf_document', 'ai_external_page');
        $allowed_post_types = array_merge($default_types, $custom_post_types_added);
        if (!in_array($post_type, $allowed_post_types)) {
            wp_send_json_error('Post type not available for training');
        }

        // Get current manual selections
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());

        // Distinguish between "clear selection" (button) vs "save with 0 selected" (modal)
        // If post_ids is empty but request came from modal save, treat as 0 posts selected
        // If post_ids is empty from clear button, remove manual selection entirely

        $is_clear_request = isset($_POST['clear']) && $_POST['clear'] === 'true';

        if (empty($post_ids)) {
            if ($is_clear_request) {
                // Clear button clicked - remove manual selection (revert to all)
                unset($manual_selections[$post_type]);
            } else {
                // Modal saved with 0 posts - treat as explicit empty selection
                $manual_selections[$post_type] = array();
            }
        } else {
            // Save manual selection for this post type
            $manual_selections[$post_type] = $post_ids;
        }

        // Update with autoload false to prevent caching issues
        update_option('listeo_ai_search_manual_selections', $manual_selections, false);

        // Clear WordPress cache to ensure option is immediately available
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('listeo_ai_search_manual_selections', 'options');

        // Debug log
        if (get_option('listeo_ai_search_debug_mode', false)) {
            error_log(sprintf(
                '[AI Chat] Manual selection saved for %s: %s (is_clear: %s)',
                $post_type,
                json_encode($manual_selections[$post_type] ?? 'REMOVED'),
                $is_clear_request ? 'true' : 'false'
            ));
        }

        // Get updated stats
        $stats = $this->get_post_type_stats($post_type);

        wp_send_json_success(array(
            'message' => empty($post_ids)
                ? __('Manual selection cleared - all posts will be processed', 'ai-chat-search')
                : sprintf(__('Saved selection of %d posts', 'ai-chat-search'), count($post_ids)),
            'total' => count($post_ids),
            'stats' => $stats
        ));
    }

    /**
     * AJAX: Get total count across all enabled post types
     * Respects manual selections for accurate counts
     *
     * Posts are considered "indexed" if they have:
     * - A direct embedding, OR
     * - Content chunks (which have their own embeddings)
     */
    public function ajax_get_total_count() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        // Get whitelisted enabled post types
        $enabled_post_types = self::get_enabled_post_types();

        // If no post types are enabled, return zero counts immediately
        if (empty($enabled_post_types)) {
            wp_send_json_success(array(
                'total' => 0,
                'indexed' => 0,
                'pending' => 0,
                'enabled_types' => array(),
                'enabled_count' => 0
            ));
            return;
        }

        // Get manual selections
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());
        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $chunk_post_type = Listeo_AI_Content_Chunker::CHUNK_POST_TYPE;

        // Debug log
        if (get_option('listeo_ai_search_debug_mode', false)) {
            error_log('[AI Chat] ajax_get_total_count called');
            error_log('[AI Chat] Enabled post types: ' . json_encode($enabled_post_types));
            error_log('[AI Chat] Manual selections: ' . json_encode($manual_selections));
        }

        // Calculate total and indexed counts respecting manual selections
        $total = 0;
        $indexed = 0;
        $type_breakdown = array(); // Store per-type breakdown

        foreach ($enabled_post_types as $post_type) {
            // Three states for manual selection:
            // 1. Not set (not in array) → count all posts
            // 2. Set to empty array ([]) → count 0 posts (user explicitly selected 0)
            // 3. Set to array with IDs ([123, 456]) → count those posts

            if (array_key_exists($post_type, $manual_selections)) {
                $selected_ids = is_array($manual_selections[$post_type])
                    ? array_filter(array_map('intval', $manual_selections[$post_type]))
                    : array();

                if (empty($selected_ids)) {
                    continue;
                }

                $placeholders = implode(',', array_fill(0, count($selected_ids), '%d'));

                $type_total = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE ID IN ($placeholders) AND post_status = 'publish'",
                    ...$selected_ids
                ));

                // Count indexed from embeddings/chunks side (fast)
                $direct = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$embeddings_table} e
                     WHERE e.listing_id IN ($placeholders)",
                    ...$selected_ids
                ));

                $via_chunks = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} chunk ON pm.post_id = chunk.ID
                     WHERE chunk.post_type = %s
                     AND pm.meta_key = '_chunk_parent_id'
                     AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
                     AND CAST(pm.meta_value AS UNSIGNED) NOT IN (
                         SELECT e2.listing_id FROM {$embeddings_table} e2
                         WHERE e2.listing_id IN ($placeholders)
                     )",
                    ...array_merge(array($chunk_post_type), $selected_ids, $selected_ids)
                ));

                $type_indexed = $direct + $via_chunks;

                $total += $type_total;
                $indexed += $type_indexed;

                $post_type_obj = get_post_type_object($post_type);
                if ($post_type_obj && $type_total > 0) {
                    $type_breakdown[] = array(
                        'label' => $post_type_obj->label,
                        'total' => $type_total,
                        'indexed' => $type_indexed
                    );
                }
            } else {
                // No manual selection — count all published posts
                if ($post_type === 'product') {
                    $type_total = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} p
                         WHERE p.post_type = %s AND p.post_status = 'publish'
                         AND NOT EXISTS (
                             SELECT 1 FROM {$wpdb->term_relationships} tr
                             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                             WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat' AND t.slug = 'listeo-booking'
                         )",
                        $post_type
                    ));
                } else {
                    $type_total = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts}
                         WHERE post_type = %s AND post_status = 'publish'",
                        $post_type
                    ));
                }

                // Count indexed from embeddings/chunks side (fast)
                $direct = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT e.listing_id)
                     FROM {$embeddings_table} e
                     INNER JOIN {$wpdb->posts} p ON e.listing_id = p.ID
                     WHERE p.post_type = %s AND p.post_status = 'publish'",
                    $post_type
                ));

                $via_chunks = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT CAST(pm.meta_value AS UNSIGNED))
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} chunk ON pm.post_id = chunk.ID
                     INNER JOIN {$wpdb->posts} parent ON CAST(pm.meta_value AS UNSIGNED) = parent.ID
                     WHERE chunk.post_type = %s
                     AND pm.meta_key = '_chunk_parent_id'
                     AND parent.post_type = %s
                     AND parent.post_status = 'publish'
                     AND parent.ID NOT IN (
                         SELECT e2.listing_id FROM {$embeddings_table} e2
                     )",
                    $chunk_post_type,
                    $post_type
                ));

                $type_indexed = $direct + $via_chunks;

                $total += $type_total;
                $indexed += $type_indexed;

                $post_type_obj = get_post_type_object($post_type);
                if ($post_type_obj && $type_total > 0) {
                    $type_breakdown[] = array(
                        'label' => $post_type_obj->label,
                        'total' => $type_total,
                        'indexed' => $type_indexed
                    );
                }
            }
        }

        // Get enabled type labels for display
        $type_labels = array();
        foreach ($enabled_post_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj) {
                $type_labels[] = $post_type_obj->label;
            }
        }

        wp_send_json_success(array(
            'total' => (int) $total,
            'indexed' => (int) $indexed,
            'pending' => max(0, (int) $total - (int) $indexed),
            'enabled_types' => $type_labels,
            'enabled_count' => count($enabled_post_types),
            'type_breakdown' => $type_breakdown
        ));
    }

    /**
     * AJAX: Add custom post types to the training-ready list
     */
    public function ajax_add_custom_post_types() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $selected_types = isset($_POST['post_types']) ? (array) $_POST['post_types'] : array();
        $selected_types = array_map('sanitize_text_field', $selected_types);

        if (empty($selected_types)) {
            wp_send_json_error(__('No post types selected', 'ai-chat-search'));
        }

        // Validate all selected types exist
        foreach ($selected_types as $post_type) {
            if (!post_type_exists($post_type)) {
                wp_send_json_error(sprintf(__('Invalid post type: %s', 'ai-chat-search'), $post_type));
            }
        }

        // Get current custom types
        $custom_post_types = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types)) {
            $custom_post_types = array();
        }

        // Add selected types (merge and remove duplicates)
        $custom_post_types = array_unique(array_merge($custom_post_types, $selected_types));
        update_option('listeo_ai_search_custom_post_types', $custom_post_types);

        // Also automatically enable the newly added types
        $enabled_post_types = get_option('listeo_ai_search_enabled_post_types', array('listing'));
        if (!is_array($enabled_post_types)) {
            $enabled_post_types = array('listing');
        }
        $enabled_post_types = array_unique(array_merge($enabled_post_types, $selected_types));
        update_option('listeo_ai_search_enabled_post_types', $enabled_post_types);

        wp_send_json_success(array(
            'message' => sprintf(
                _n(
                    '%d post type added successfully',
                    '%d post types added successfully',
                    count($selected_types),
                    'ai-chat-search'
                ),
                count($selected_types)
            ),
            'added_types' => $selected_types,
            'reload_required' => true
        ));
    }

    /**
     * AJAX: Remove custom post type
     */
    public function ajax_remove_custom_post_type() {
        check_ajax_referer('listeo_ai_universal_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_type = sanitize_text_field($_POST['post_type']);

        // Validate post type exists
        if (!post_type_exists($post_type)) {
            wp_send_json_error('Invalid post type');
        }

        // Don't allow removing default types
        $default_types = array('listing', 'post', 'page', 'product');
        if (in_array($post_type, $default_types)) {
            wp_send_json_error(__('Cannot remove default post types', 'ai-chat-search'));
        }

        // Get current custom types
        $custom_post_types = get_option('listeo_ai_search_custom_post_types', array());
        if (!is_array($custom_post_types)) {
            $custom_post_types = array();
        }

        // Remove from custom types list
        $custom_post_types = array_diff($custom_post_types, array($post_type));
        update_option('listeo_ai_search_custom_post_types', array_values($custom_post_types));

        // Also remove from enabled types
        $enabled_post_types = get_option('listeo_ai_search_enabled_post_types', array('listing'));
        if (is_array($enabled_post_types)) {
            $enabled_post_types = array_diff($enabled_post_types, array($post_type));
            update_option('listeo_ai_search_enabled_post_types', array_values($enabled_post_types));
        }

        // Clear manual selections for this type
        $manual_selections = get_option('listeo_ai_search_manual_selections', array());
        if (isset($manual_selections[$post_type])) {
            unset($manual_selections[$post_type]);
            update_option('listeo_ai_search_manual_selections', $manual_selections);
        }

        $custom_fields_config = $this->get_custom_fields_config();
        if (isset($custom_fields_config[$post_type])) {
            unset($custom_fields_config[$post_type]);
            update_option('listeo_ai_search_custom_meta_fields', $custom_fields_config, false);
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Post type "%s" removed successfully', 'ai-chat-search'), $post_type),
            'post_type' => $post_type
        ));
    }
}

// Initialize
new Listeo_AI_Search_Universal_Settings();
