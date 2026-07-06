<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listeo Core Custom Listing Types Admin Interface
 * 
 * Handles admin interface for managing custom listing types
 * 
 * @since 1.0.0
 */
class Listeo_Core_Custom_Listing_Types_Admin
{

    /**
     * The single instance of the class.
     */
    private static $_instance = null;

    /**
     * Main admin page slug
     */
    const ADMIN_PAGE_SLUG = 'listeo-listing-types';

    /**
     * Allows for accessing single instance of class. Class should only be constructed once per call.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add to Listeo Editor menu
        add_action('admin_menu', array($this, 'add_options_page'), 15);
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_listeo_save_custom_type', array($this, 'ajax_save_custom_type'));
        add_action('wp_ajax_listeo_delete_custom_type', array($this, 'ajax_delete_custom_type'));
        add_action('wp_ajax_listeo_reorder_listing_types', array($this, 'ajax_reorder_listing_types'));
    }

    /**
     * Add submenu page to Listeo Editor
     */
    public function add_options_page()
    {
        add_submenu_page(
            'listeo-fields-and-form',
            __('Listing Types', 'listeo_core'),
            __('Listing Types', 'listeo_core') . ' <span class="listeo-new-badge">NEW</span>',
            'manage_options',
            'listeo-listing-types',
            array($this, 'output')
        );
    }

    /**
     * Main output method for Listeo Editor integration
     */
    public function output()
    {
        // Force migration check
        $custom_types_manager = listeo_core_custom_listing_types();
        $custom_types_manager->maybe_create_table();
        $custom_types_manager->migrate_default_types();

        $listing_types = $custom_types_manager->get_listing_types(false, true);

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;

        // Handle force seed action
        if ($action === 'force_seed' && current_user_can('manage_options')) {
            $custom_types_manager->force_seed_default_types();
            wp_redirect(admin_url('admin.php?page=listeo-listing-types&seeded=1'));
            exit;
        }

        // Handle force update defaults action
        if ($action === 'force_update_defaults' && current_user_can('manage_options')) {
            $custom_types_manager->force_update_default_types();
            wp_redirect(admin_url('admin.php?page=listeo-listing-types&defaults_updated=1'));
            exit;
        }

        // Handle refresh taxonomies action
        if ($action === 'refresh_taxonomies' && current_user_can('manage_options')) {
            if (class_exists('Listeo_Core_Post_Types')) {
                Listeo_Core_Post_Types::refresh_dynamic_taxonomies();
            }
            wp_redirect(admin_url('admin.php?page=listeo-listing-types&taxonomies_refreshed=1'));
            exit;
        }

        // Scripts are enqueued in enqueue_admin_scripts() via the admin_enqueue_scripts hook
        
        // Remove WordPress footer that might appear in the middle of content
        remove_action('admin_footer_text', '__return_false');
        add_filter('admin_footer_text', '__return_false');
        add_filter('update_footer', '__return_false', 20);

?>
        <h2><?php _e('Listing Types Editor', 'listeo_core'); ?></h2>
        <div class="listeo-editor-wrap">
            <div class="nav-tab-container">
                <h2 class="nav-tab-wrapper form-builder">
                    <?php
                    // Get all listing types for navigation
                    $custom_types_manager = listeo_core_custom_listing_types();
                    $all_types = $custom_types_manager->get_listing_types(false); // Get all types including inactive
                    $current_type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;
                    $current_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

                    // Main listing types tab (active when in list view)
                    $main_tab_class = ($current_action === 'list') ? 'nav-tab nav-tab-active' : 'nav-tab';
                    ?>
                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types'); ?>" class="<?php echo esc_attr($main_tab_class); ?>">
                        <?php _e('All Listing Types', 'listeo_core'); ?>
                    </a>

                    <?php if (!empty($all_types)): ?>

                        <?php foreach ($all_types as $type):
                            $is_current = ($current_type_id === intval($type->id));
                            $tab_class = $is_current ? 'nav-tab nav-tab-active' : 'nav-tab';
                            $edit_url = admin_url('admin.php?page=listeo-listing-types&action=edit&type_id=' . $type->id);
                        ?>
                            <a href="<?php echo esc_url($edit_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                                <?php echo esc_html($type->name); ?>
                                <?php if (!$type->is_active): ?>
                                    <span style="opacity: 0.6;">(<?php _e('Inactive', 'listeo_core'); ?>)</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <button id="add-new-listeo-term-fields" class="nav-tab nav-tab-add-new listeo-add-type-btn" type="button" onclick="window.location.href='<?php echo admin_url('admin.php?page=listeo-listing-types&action=add'); ?>'">+ <?php _e('Add listing type', 'listeo_core'); ?></button>
                </h2>
            </div>

            <div class="wrap listeo-form-editor listeo-forms-builder listeo-fields-builder">
                <?php if (isset($_GET['seeded'])): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><strong><?php _e('Default listing types have been seeded successfully!', 'listeo_core'); ?></strong></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['defaults_updated'])): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><strong><?php _e('Default listing types have been updated to new booking system successfully!', 'listeo_core'); ?></strong></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['taxonomies_refreshed'])): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><strong><?php _e('Taxonomies have been refreshed successfully!', 'listeo_core'); ?></strong></p>
                    </div>
                <?php endif; ?>

                <?php
                switch ($action) {
                    case 'add':
                    case 'edit':
                        $this->render_editor_form($type_id);
                        break;
                    case 'list':
                    default:
                        $this->render_editor_list($listing_types);
                        break;
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Render listing types list for Listeo Editor
     */
    private function render_editor_list($listing_types)
    {
    ?>
        <div class="listeo-form-editor">
            <p style="margin-bottom: 20px; color: #666;">
                <?php _e('Define different types of listings with specific features, booking options, and capabilities. Custom listing types allow you to extend beyond the default Service, Rental, Event, and Classifieds types.', 'listeo_core'); ?>
            </p>

            <p style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=refresh_taxonomies'); ?>" class="button">
                    <i class="fa fa-refresh"></i> <?php _e('Refresh Taxonomies', 'listeo_core'); ?>
                </a>
                <?php if (current_user_can('manage_options') && (defined('WP_DEBUG') && WP_DEBUG)): ?>
                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=force_seed'); ?>" class="button" style="margin-left: 10px;" onclick="return confirm('<?php _e('This will reset all default types. Are you sure?', 'listeo_core'); ?>')">
                        <i class="fa fa-refresh"></i> <?php _e('Reset Types', 'listeo_core'); ?>
                    </a>
                <?php endif; ?>
            </p>

            <div class="listeo-droppable-container">
                <?php if (empty($listing_types)): ?>
                    <div style="text-align: center; padding: 40px; background: #f9f9f9; border: 2px dashed #ddd; border-radius: 8px;">
                        <h3><?php _e('No listing types found', 'listeo_core'); ?></h3>
                        <p><?php _e('Create your first custom listing type to get started!', 'listeo_core'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="listeo-types-grid">
                        <?php foreach ($listing_types as $index => $type): ?>
                            <div class="form_item listeo-type-item" data-type-id="<?php echo $type->id; ?>">
                                <div class="listeo-type-header">
                                    <div class="listeo-type-icon">
                                        <?php
                                        // Check for icon in new system first, then fallback to old option format
                                        $icon_id = 0;
                                        if ($type->icon_id !== null) {
                                            $icon_id = intval($type->icon_id);
                                        } else {
                                            // Only check legacy option when DB value is NULL (not yet migrated)
                                            $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                        }

                                        if ($icon_id) {
                                            $icon_url = wp_get_attachment_url($icon_id);
                                            
                                            if ($icon_url) {
                                                if (get_post_mime_type($icon_id) === 'image/svg+xml') {
                                                    echo '<div style="width: 32px; height: 32px; overflow: hidden; display: flex; align-items: center; justify-content: center;"><div style="max-width: 32px; max-height: 32px;">' . listeo_smart_svg_render($icon_id) . '</div></div>';
                                                } else {
                                                    echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($type->name) . '" style="width: 32px; height: 32px; object-fit: contain;">';
                                                }
                                            }
                                        } else {
                                            echo '<div style="width: 32px; height: 32px; background: #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 14px;">?</div>';
                                        }
                                   
                                        ?>
                                    </div>
                                    <div class="listeo-type-title">
                                        <h4>
                                            <?php echo esc_html($type->name); ?>
                                            <?php if ($type->is_default): ?>
                                                <span class="listeo-default-badge"><?php _e('Default', 'listeo_core'); ?></span>
                                            <?php endif; ?>
                                        </h4>
                                        <span class="listeo-type-slug"><?php echo esc_html($type->slug); ?></span>
                                    </div>
                                    <div class="listeo-type-status">
                                        <?php if ($type->is_active): ?>
                                            <span class="listeo-status-active">● <?php _e('Active', 'listeo_core'); ?></span>
                                        <?php else: ?>
                                            <span class="listeo-status-inactive">● <?php _e('Inactive', 'listeo_core'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($type->description): ?>
                                    <p class="listeo-type-description"><?php echo esc_html($type->description); ?></p>
                                <?php endif; ?>

                                <div class="listeo-type-features">
                                    <?php
                                    // Show booking type
                                    $booking_type = isset($type->booking_type) ? $type->booking_type : 'none';
                                    $presets = Listeo_Core_Custom_Listing_Types::get_booking_type_presets();
                                    if (isset($presets[$booking_type])) {
                                        $booking_label = $presets[$booking_type]['label'];
                                        $badge_class = $booking_type === 'none' ? 'listeo-feature-tag-disabled' : 'listeo-feature-tag-primary';
                                        echo '<span class="listeo-feature-tag ' . $badge_class . '">' . esc_html($booking_label) . '</span>';
                                    }

                                    // Show active features from JSON
                                    $features = isset($type->booking_features) ? json_decode($type->booking_features, true) : array();
                                    if (is_array($features) && !empty($features)) {
                                        $available_features = Listeo_Core_Custom_Listing_Types::get_available_booking_features();
                                        foreach ($features as $feature_key) {
                                            if (isset($available_features[$feature_key])) {
                                                $feature_label = $available_features[$feature_key]['label'];
                                                echo '<span class="listeo-feature-tag">' . esc_html($feature_label) . '</span>';
                                            }
                                        }
                                    }

                                    // Show taxonomy if enabled
                                    if (isset($type->register_taxonomy) && $type->register_taxonomy): ?>
                                        <span class="listeo-feature-tag listeo-feature-tag-secondary"><?php _e('Taxonomy', 'listeo_core'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="listeo-type-meta">
                                    <span class="listeo-listings-count">
                                        <?php
                                        $count = isset($type->listing_count) ? $type->listing_count : 0;
                                        printf(_n('%s listing', '%s listings', $count, 'listeo_core'), number_format_i18n($count));
                                        ?>
                                    </span>
                                </div>

                                <div class="listeo-type-actions">
                                    <div class="button listeo-drag-btn" title="<?php _e('Drag to reorder', 'listeo_core'); ?>">
                                        <span class="dashicons dashicons-menu"></span>
                                    </div>
                                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=edit&type_id=' . $type->id); ?>" class="button listeo-edit-btn">
                                        <?php _e('Edit', 'listeo_core'); ?>
                                    </a>
                                    <?php if (!$type->is_default): ?>
                                        <button type="button" class="button listeo-delete-btn" data-type-id="<?php echo $type->id; ?>" data-type-name="<?php echo esc_attr($type->name); ?>">
                                            <?php _e('Delete', 'listeo_core'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="droppable-helper"></div>
            </div>

            <a class="add_new_item button-primary add-type" href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=add'); ?>">
                <?php _e('Add New Listing Type', 'listeo_core'); ?>
            </a>
        </div>

        <!-- CSS moved to admin.css file -->

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.listeo-delete-btn').on('click', function(e) {
                    e.preventDefault();
                    var typeId = $(this).data('type-id');
                    var typeName = $(this).data('type-name');

                    if (confirm(<?php echo wp_json_encode( __('Are you sure you want to delete this listing type?', 'listeo_core') ); ?> + '\n\n"' + typeName + '"')) {
                        $.post(ajaxurl, {
                            action: 'listeo_delete_custom_type',
                            type_id: typeId,
                            nonce: '<?php echo wp_create_nonce('listeo_custom_types'); ?>'
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || <?php echo wp_json_encode( __('Error deleting type', 'listeo_core') ); ?>);
                            }
                        });
                    }
                });
            });
        </script>
        </div> <!-- .listeo-editor-wrap -->
    <?php
    }

    /**
     * Render form for Listeo Editor
     */
    private function render_editor_form($type_id = 0)
    {
        $custom_types_manager = listeo_core_custom_listing_types();
        $type = null;

        if ($type_id > 0) {
            global $wpdb;
            $type = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$custom_types_manager->get_table_name()} WHERE id = %d",
                $type_id
            ));
        }

        $is_edit = ($type !== null);
    ?>
        <div class="listeo-form-editor">
            <form method="post" class="listeo-editor-type-form listeo-custom-listing-type-editor">
                <!-- Basic Information Section -->
                <div class="lc-settings-block">
                    <div class="lc-block-header">
                        <div class="lc-block-icon"><i class="fa fa-info-circle"></i></div>
                        <div>
                            <h3 class="lc-block-title"><?php echo $is_edit ? __('Edit Listing Type', 'listeo_core') : __('Add New Listing Type', 'listeo_core'); ?></h3>
                            <p class="lc-block-description"><?php _e('Configure the basic information and display settings for this listing type', 'listeo_core'); ?></p>
                        </div>
                    </div>
                    <div class="lc-block-content">
                    <?php wp_nonce_field('listeo_custom_type_form', 'listeo_custom_type_nonce'); ?>
                    <input type="hidden" name="listeo_custom_type_action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="type_id" value="<?php echo $type->id; ?>">
                        <input type="hidden" name="slug" value="<?php echo esc_attr($type->slug); ?>">
                    <?php endif; ?>

                    <div class="lc-form-row">
                        <div class="lc-form-label-column">
                            <div class="lc-form-label"><?php _e('Name', 'listeo_core'); ?> <span class="required-asterisk">*</span></div>
                            <div class="lc-form-description"><?php _e('The display name for this listing type', 'listeo_core'); ?></div>
                        </div>
                        <div class="lc-form-field-column">
                            <input type="text" id="editor_type_name" name="name" value="<?php echo $is_edit ? esc_attr($type->name) : ''; ?>" class="lc-input" required>
                        </div>
                    </div>

                    <div class="lc-form-row">
                        <div class="lc-form-label-column">
                            <div class="lc-form-label"><?php _e('Plural Name', 'listeo_core'); ?> <span class="required-asterisk">*</span></div>
                            <div class="lc-form-description"><?php _e('Enter the plural form manually (e.g., "Services", "Rentals")', 'listeo_core'); ?></div>
                        </div>
                        <div class="lc-form-field-column">
                            <input type="text" id="editor_type_plural_name" name="plural_name" value="<?php echo $is_edit ? esc_attr($type->plural_name) : ''; ?>" class="lc-input" required>
                        </div>
                    </div>

                    <div class="lc-form-row">
                        <div class="lc-form-label-column">
                            <div class="lc-form-label"><?php _e('Slug', 'listeo_core'); ?> <span class="required-asterisk">*</span></div>
                            <div class="lc-form-description">
                                <?php _e('URL-friendly version. Must be unique', 'listeo_core'); ?>
                                <?php if ($is_edit): ?>
                                    <br><strong><?php _e('Note: Cannot be changed after creation', 'listeo_core'); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="lc-form-field-column">
                            <input type="text" id="editor_type_slug" name="slug" value="<?php echo $is_edit ? esc_attr($type->slug) : ''; ?>" class="lc-input" required <?php echo $is_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>

                    <div class="lc-form-row">
                        <div class="lc-form-label-column">
                            <div class="lc-form-label"><?php _e('Description', 'listeo_core'); ?></div>
                            <div class="lc-form-description"><?php _e('Optional description for this listing type', 'listeo_core'); ?></div>
                        </div>
                        <div class="lc-form-field-column">
                            <textarea id="editor_type_description" name="description" rows="3" class="lc-input lc-textarea"><?php echo $is_edit ? esc_textarea($type->description) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="lc-form-row">
                        <div class="lc-form-label-column">
                            <div class="lc-form-label"><?php _e('Icon', 'listeo_core'); ?></div>
                            <div class="lc-form-description"><?php _e('Choose an icon for this listing type. Supports SVG and image formats', 'listeo_core'); ?></div>
                        </div>
                        <div class="lc-form-field-column">
                            <?php
                            $icon_id = 0;
                            if ($is_edit) {
                                if ($type->icon_id !== null) {
                                    $icon_id = intval($type->icon_id);
                                } else {
                                    // Only check legacy option when DB value is NULL (not yet migrated)
                                    $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                }
                            }
                            $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';

                            ?>
                            <div class="listeo-media-uploader">
                                <input type="hidden" id="editor_type_icon" name="icon_id" value="<?php echo esc_attr($icon_id); ?>">
                                <div class="listeo-media-preview">
                                    <?php if ($icon_url): ?>
                                        <img src="<?php echo esc_url($icon_url); ?>" alt="<?php _e('Icon preview', 'listeo_core'); ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="media-buttons-group">
                                    <button type="button" class="lc-button lc-button-secondary listeo-media-upload" data-target="editor_type_icon">
                                        <i class="fa fa-upload"></i> <?php _e('Choose Icon', 'listeo_core'); ?>
                                    </button>
                                    <?php if ($icon_id): ?>
                                        <button type="button" class="lc-button lc-button-ghost listeo-media-remove" data-target="editor_type_icon">
                                            <i class="fa fa-trash"></i> <?php _e('Remove', 'listeo_core'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
        <!-- Booking Configuration Section -->
        <div class="lc-settings-block">
            <div class="lc-block-header">
                <div class="lc-block-icon"><i class="fa fa-calendar"></i></div>
                <div>
                    <h3 class="lc-block-title"><?php _e('Booking Configuration', 'listeo_core'); ?></h3>
                    <p class="lc-block-description"><?php _e('Configure booking types and features for this listing type', 'listeo_core'); ?></p>
                </div>
            </div>
            <div class="lc-block-content">
                <div class="lc-form-row">
                    <div class="lc-form-label-column">
                        <div class="lc-form-label"><?php _e('Booking Type', 'listeo_core'); ?></div>
                        <div class="lc-form-description"><?php _e('Select the booking behavior for this listing type', 'listeo_core'); ?></div>
                    </div>
                    <div class="lc-form-field-column">
                        <?php
                        $current_booking_type = $is_edit && isset($type->booking_type) ? $type->booking_type : 'none';
                        $presets = Listeo_Core_Custom_Listing_Types::get_booking_type_presets();
                        ?>
                        <select id="editor_booking_type" name="booking_type" class="lc-select">
                            <?php foreach ($presets as $preset_key => $preset_data): ?>
                                <option value="<?php echo esc_attr($preset_key); ?>" <?php selected($current_booking_type, $preset_key); ?>>
                                    <?php echo esc_html($preset_data['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="booking-type-description" class="booking-type-description">
                            <strong id="preset-label"></strong>
                            <p id="preset-description"></p>
                        </div>
                    </div>
                </div>

                <div class="lc-form-row">
                    <div class="lc-form-label-column">
                        <div class="lc-form-label"><?php _e('Booking Features', 'listeo_core'); ?></div>
                        <div class="lc-form-description"><?php _e('Select specific features for this listing type. Mix and match for maximum flexibility', 'listeo_core'); ?></div>
                    </div>
                    <div class="lc-form-field-column">
                        <?php
                        $current_features = array();
                        if ($is_edit && isset($type->booking_features)) {
                            $current_features = json_decode($type->booking_features, true);
                            if (!is_array($current_features)) {
                                $current_features = array();
                            }
                        }
                        $available_features = Listeo_Core_Custom_Listing_Types::get_available_booking_features();
                        ?>
                        <div id="booking-features-container">
                            <?php foreach ($available_features as $feature_key => $feature_data): ?>
                                <?php
                                $is_core_feature = isset($feature_data['core_feature']) && $feature_data['core_feature'];
                                $preset_only = isset($feature_data['preset_only']) ? $feature_data['preset_only'] : array();
                                ?>
                                <div class="listeo-feature-toggle-item feature-option <?php echo $is_core_feature ? 'core-feature' : ''; ?>"
                                    data-preset-only="<?php echo esc_attr(implode(',', $preset_only)); ?>">
                                    <div class="listeo-feature-content">
                                        <h4 class="listeo-feature-title">
                                            <?php echo esc_html($feature_data['label']); ?>
                                            <?php if ($is_core_feature): ?>
                                                <em class="core-feature-badge">(<?php _e('Built into booking type', 'listeo_core'); ?>)</em>
                                            <?php endif; ?>
                                        </h4>
                                        <p class="listeo-feature-description"><?php echo esc_html($feature_data['description']); ?></p>
                                    </div>
                                    <label class="lc-toggle" for="feature_<?php echo esc_attr($feature_key); ?>">
                                        <input type="checkbox"
                                            id="feature_<?php echo esc_attr($feature_key); ?>"
                                            name="booking_features[]"
                                            value="<?php echo esc_attr($feature_key); ?>"
                                            <?php checked(in_array($feature_key, $current_features)); ?>>
                                        <span class="lc-toggle-slider"></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Settings Section -->
        <div class="lc-settings-block">
            <div class="lc-block-header">
                <div class="lc-block-icon"><i class="fa fa-cog"></i></div>
                <div>
                    <h3 class="lc-block-title"><?php _e('Business Settings', 'listeo_core'); ?></h3>
                    <p class="lc-block-description"><?php _e('Configure business-specific features and taxonomies', 'listeo_core'); ?></p>
                </div>
            </div>
            <div class="lc-block-content">
                <div class="lc-form-row lc-form-row-toggle">
                    <div class="listeo-settings-toggle-item">
                        <div class="listeo-settings-toggle-content">
                            <h4 class="listeo-settings-toggle-title"><?php _e('Enable business hours display', 'listeo_core'); ?></h4>
                            <p class="lc-toggle-description"><?php _e('Allow listings of this type to display opening/closing hours', 'listeo_core'); ?></p>
                        </div>
                        <label class="lc-toggle" for="editor_supports_opening_hours">
                            <input type="checkbox" id="editor_supports_opening_hours" name="supports_opening_hours" value="1" <?php echo ($is_edit ? ($type->supports_opening_hours ? 'checked' : '') : 'checked="checked"'); ?>>
                            <span class="lc-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="lc-form-row lc-form-row-toggle">
                    <div class="listeo-settings-toggle-item">
                        <div class="listeo-settings-toggle-content">
                            <h4 class="listeo-settings-toggle-title"><?php _e('Register dedicated taxonomy', 'listeo_core'); ?></h4>
                            <p class="lc-toggle-description"><?php _e('Creates a dedicated <strong>category taxonomy</strong> for this listing type and displays dropdown with categories in <strong>add listing form</strong>', 'listeo_core'); ?></p>
                        </div>
                        <label class="lc-toggle" for="editor_register_taxonomy">
                            <input type="checkbox" id="editor_register_taxonomy" name="register_taxonomy" value="1" <?php echo ($is_edit ? (isset($type->register_taxonomy) && $type->register_taxonomy ? 'checked' : '') : 'checked="checked"'); ?>>
                            <span class="lc-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Taxonomy Slug Translations -->
                <div id="taxonomy_slug_translations_section"  style="<?php echo (!$is_edit || (isset($type->register_taxonomy) && !$type->register_taxonomy)) ? 'display: none;' : ''; ?>">
                    <div class="listeo-settings-subsection slug-translations-container">
                        <div class="slug-translations-left">
                            <h4 class="listeo-settings-toggle-title"><?php _e('Taxonomy Slug Translations', 'listeo_core'); ?></h4>
                            <p class="lc-toggle-description"><?php _e('Translate the taxonomy URL slug for different languages. Leave empty to use the default slug.', 'listeo_core'); ?></p>
                            <p class="lc-toggle-description"><?php _e('The default slug will be: <strong>[type-slug]-category</strong>', 'listeo_core'); ?></p>
                        </div>
                        <div class="slug-translations-right">
                            <?php
                            // Get available languages
                            $available_languages = $this->get_available_languages();
                            $current_translations = array();

                            if ($is_edit && isset($type->slug_translations) && !empty($type->slug_translations)) {
                                $current_translations = json_decode($type->slug_translations, true);
                                if (!is_array($current_translations)) {
                                    $current_translations = array();
                                }
                            }

                            if (!empty($available_languages)) :
                            ?>
                            <div class="slug-translations-grid">
                                <?php foreach ($available_languages as $lang_code => $lang_name) : ?>
                                <div class="slug-translation-field">
                                    <label for="slug_trans_<?php echo esc_attr($lang_code); ?>">
                                        <strong><?php echo esc_html($lang_name); ?></strong> (<?php echo esc_html($lang_code); ?>)
                                    </label>
                                    <input type="text"
                                           id="slug_trans_<?php echo esc_attr($lang_code); ?>"
                                           name="slug_translations[<?php echo esc_attr($lang_code); ?>]"
                                           value="<?php echo esc_attr(isset($current_translations[$lang_code]) ? $current_translations[$lang_code] : ''); ?>"
                                           placeholder="<?php echo esc_attr($is_edit ? $type->slug . '-category' : 'type-category'); ?>"
                                           class="regular-text slug-translation-input">
                                    <span class="description"><?php printf(__('e.g., %s-kategorie', 'listeo_core'), $is_edit ? $type->slug : 'type'); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else : ?>
                            <div class="notice notice-info inline">
                                <p><?php _e('No additional languages detected. Install WPML, Polylang, or configure WordPress multilingual settings to enable slug translations.', 'listeo_core'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Settings Section -->
        <div class="lc-settings-block">
            <div class="lc-block-header">
                <div class="lc-block-icon"><i class="fa fa-eye"></i></div>
                <div>
                    <h3 class="lc-block-title"><?php _e('Display Settings', 'listeo_core'); ?></h3>
                    <p class="lc-block-description"><?php _e('Control the visibility and availability of this listing type', 'listeo_core'); ?></p>
                </div>
            </div>
            <div class="lc-block-content">
                <div class="lc-form-row lc-form-row-toggle">
                    <div class="listeo-settings-toggle-item">
                        <div class="listeo-settings-toggle-content">
                            <h4 class="listeo-settings-toggle-title"><?php _e('Active (available for new listings)', 'listeo_core'); ?></h4>
                            <p class="lc-toggle-description"><?php _e('Inactive types will not be available when creating new listings', 'listeo_core'); ?></p>
                        </div>
                        <label class="lc-toggle" for="editor_is_active">
                            <input type="checkbox" id="editor_is_active" name="is_active" value="1" <?php echo ($is_edit && $type->is_active) || !$is_edit ? 'checked' : ''; ?>>
                            <span class="lc-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <?php do_action('listeo_listing_type_editor_sections', $type, $is_edit); ?>

        <!-- Actions Section - Outside of settings blocks -->
        <div class="listeo-form-actions">
            <div class="action-buttons-group">
                <button type="submit" name="submit" class="lc-button lc-button-primary">
                    <i class="fa fa-save"></i> <?php echo $is_edit ? __('Update Listing Type', 'listeo_core') : __('Create Listing Type', 'listeo_core'); ?>
                </button>
                <?php if ($is_edit && $type && !$type->is_default): ?>
                    <button type="button" class="lc-button lc-button-danger listeo-delete-type-btn" 
                            data-type-id="<?php echo esc_attr($type->id); ?>" 
                            data-type-name="<?php echo esc_attr($type->name); ?>">
                        <i class="fa fa-trash"></i> <?php _e('Delete Listing Type', 'listeo_core'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        </form>
        </div> <!-- .listeo-form-editor -->

                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // Auto-generate slug from name
                        $('#editor_type_name').on('input', function() {
                            if (!$('#editor_type_slug').prop('readonly')) {
                                var slug = $(this).val()
                                    .toLowerCase()
                                    .replace(/[^a-z0-9\s-]/g, '')
                                    .replace(/[\s]+/g, '-')
                                    .replace(/-+/g, '-')
                                    .replace(/^-|-$/g, '');
                                $('#editor_type_slug').val(slug);
                            }
                        });

                        // Note: Plural name field is now manual input only - no auto-generation

                        // Booking type presets functionality
                        var presets = <?php echo wp_json_encode(Listeo_Core_Custom_Listing_Types::get_booking_type_presets()); ?>;
                        var features = <?php echo wp_json_encode(Listeo_Core_Custom_Listing_Types::get_available_booking_features()); ?>;

                        function updateBookingFeatures(bookingType, applyPresets) {
                            // Default to true if not specified (backward compatibility)
                            applyPresets = applyPresets !== false;
                            
                            var preset = presets[bookingType];
                            if (preset) {
                                // Update description
                                $('#preset-label').text(preset.label);
                                if (bookingType === 'none') {
                                    $('#preset-description').text(preset.description + ' Only booking-optional features (e.g. Add-on Services) remain available.');
                                } else {
                                    $('#preset-description').text(preset.description);
                                }

                                // Are there any features that work without a booking flow?
                                // If yes, keep the section visible so the admin can still toggle them when bookingType === 'none'.
                                var hasBookingOptional = Object.keys(features).some(function(k) {
                                    return features[k] && features[k].booking_optional;
                                });

                                // Show/hide booking features section based on booking type
                                if (bookingType === 'none' && !hasBookingOptional) {
                                    // No booking + no booking-optional features → hide/disable whole section
                                    $('#booking-features-container').closest('.lc-form-row').addClass('disabled-section');
                                    $('#booking-features-container').addClass('disabled-features');
                                } else {
                                    // Show booking features section (either booking is enabled, or we have booking-optional features)
                                    $('#booking-features-container').closest('.lc-form-row').removeClass('disabled-section');
                                    $('#booking-features-container').removeClass('disabled-features');
                                }

                                // Handle feature availability based on booking type
                                $('.feature-option').each(function() {
                                    var $container = $(this);
                                    var $checkbox = $container.find('input[type="checkbox"]');
                                    var featureKey = $checkbox.val();
                                    var feature = features[featureKey];

                                    // Reset styles
                                    $container.removeClass('feature-disabled').css('opacity', '1');
                                    $checkbox.prop('disabled', false);

                                    if (bookingType === 'none') {
                                        if (feature && feature.booking_optional) {
                                            // Booking-optional feature: stays interactive even with no booking.
                                            // Apply preset (which empties features for 'none') only when the
                                            // user just switched booking type; on page load preserve saved state.
                                            if (applyPresets) {
                                                $checkbox.prop('checked', false);
                                            }
                                        } else {
                                            // Booking-dependent feature: uncheck and disable.
                                            $checkbox.prop('checked', false);
                                            $checkbox.prop('disabled', true);
                                            $container.addClass('feature-disabled').css('opacity', '0.4');
                                        }
                                    } else if (bookingType === 'custom') {
                                        // In custom mode, all features are available
                                        // Only reset checkboxes if we're applying presets (user changed booking type)
                                        if (applyPresets) {
                                            $checkbox.prop('checked', false); // Reset for manual selection
                                        }
                                    } else {
                                        // Check if this feature is a core feature that should be disabled for this preset
                                        if (feature.core_feature && feature.preset_only && !feature.preset_only.includes(bookingType)) {
                                            // Gray out and disable core features not for this preset
                                            $container.addClass('feature-disabled').css('opacity', '0.4');
                                            $checkbox.prop('disabled', true);
                                            $checkbox.prop('checked', false);
                                        } else {
                                            // Only auto-select features if we're applying presets (user changed booking type)
                                            // On page load, respect the saved database values
                                            if (applyPresets) {
                                                $checkbox.prop('checked', preset.features.includes(featureKey));
                                            }
                                        }
                                    }
                                });
                            }
                        }

                        // Handle feature conflicts and availability
                        function handleFeatureConflicts(changedFeature, isChecked) {
                            if (isChecked && features[changedFeature] && features[changedFeature].conflicts_with) {
                                features[changedFeature].conflicts_with.forEach(function(conflictingFeature) {
                                    $('#feature_' + conflictingFeature).prop('checked', false);
                                });
                            }
                            
                            // Update hourly_picker availability based on current selections
                            updateHourlyPickerAvailability();
                        }
                        
                        // Special logic for hourly_picker availability
                        function updateHourlyPickerAvailability() {
                            var currentBookingType = $('#editor_booking_type').val();
                            var dateRangeChecked = $('#feature_date_range').is(':checked');
                            var timeSlotsChecked = $('#feature_time_slots').is(':checked');
                            var ticketsChecked = $('#feature_tickets').is(':checked');
                            var hourlyPickerCheckbox = $('#feature_hourly_picker');
                            var hourlyPickerContainer = hourlyPickerCheckbox.closest('.feature-option');
                            
                            // If booking type is "none", hourly picker should be disabled
                            if (currentBookingType === 'none') {
                                hourlyPickerCheckbox.prop('disabled', true).prop('checked', false);
                                hourlyPickerContainer.addClass('feature-disabled').css('opacity', '0.4');
                                return;
                            }
                            
                            // Hourly picker is only available when:
                            // 1. Date Range is selected, OR
                            // 2. Neither Date Range nor Time Slots are selected
                            // AND tickets is not selected
                            var shouldBeAvailable = !ticketsChecked && (dateRangeChecked || (!dateRangeChecked && !timeSlotsChecked));
                            
                            if (shouldBeAvailable) {
                                hourlyPickerCheckbox.prop('disabled', false);
                                hourlyPickerContainer.removeClass('feature-disabled').css('opacity', '1');
                            } else {
                                hourlyPickerCheckbox.prop('disabled', true).prop('checked', false);
                                hourlyPickerContainer.addClass('feature-disabled').css('opacity', '0.4');
                            }
                        }

                        // Initialize preset description on page load
                        // Pass false to NOT apply presets - respect saved database values
                        updateBookingFeatures($('#editor_booking_type').val(), false);
                        
                        // Initialize hourly picker availability
                        updateHourlyPickerAvailability();

                        // Handle booking type changes
                        $('#editor_booking_type').on('change', function() {
                            // Pass true to apply presets when user changes booking type
                            updateBookingFeatures($(this).val(), true);
                            // Update hourly picker availability after preset changes
                            setTimeout(function() {
                                updateHourlyPickerAvailability();
                            }, 100);
                        });

                        // Handle individual feature checkbox changes
                        $('#booking-features-container input[type="checkbox"]').on('change', function() {
                            var feature = $(this).val();
                            var isChecked = $(this).is(':checked');
                            handleFeatureConflicts(feature, isChecked);
                        });

                        // Media uploader functionality
                        $('.listeo-media-upload').on('click', function(e) {
                            e.preventDefault();
                            var button = $(this);
                            var targetInputId = button.data('target');
                            var uploader = button.closest('.listeo-media-uploader');
                            var preview = uploader.find('.listeo-media-preview');
                            var $inputField = $('#' + targetInputId);

                            // Verify input field exists
                            if (!$inputField.length) {
                                console.error('Icon input field not found: #' + targetInputId);
                                return;
                            }

                            // Verify wp.media is available
                            if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
                                console.error('WordPress media library not loaded. Try reloading the page.');
                                alert('<?php echo esc_js(__('Media library failed to load. Please reload the page.', 'listeo_core')); ?>');
                                return;
                            }

                            var mediaUploader = wp.media({
                                title: <?php echo wp_json_encode( __('Choose Icon', 'listeo_core') ); ?>,
                                button: {
                                    text: <?php echo wp_json_encode( __('Use This Icon', 'listeo_core') ); ?>
                                },
                                multiple: false,
                                library: {
                                    type: ['image']
                                }
                            });

                            mediaUploader.on('select', function() {
                                var attachment = mediaUploader.state().get('selection').first().toJSON();

                                // Update hidden input value
                                $inputField.val(attachment.id);

                                // Update preview
                                preview.html('<img src="' + attachment.url + '" style="max-width: 64px; max-height: 64px; border: 1px solid #ddd; padding: 5px;">');

                                // Show/add remove button
                                var removeBtn = uploader.find('.listeo-media-remove');
                                if (removeBtn.length) {
                                    removeBtn.show();
                                } else {
                                    // Create remove button if it doesn't exist
                                    var $removeBtn = $('<button type="button" class="lc-button lc-button-ghost listeo-media-remove"></button>')
                                        .attr('data-target', targetInputId)
                                        .html('<i class="fa fa-trash"></i> ' + <?php echo wp_json_encode( __('Remove', 'listeo_core') ); ?>);
                                    uploader.find('.media-buttons-group').append($removeBtn);
                                }
                            });

                            mediaUploader.open();
                        });

                        $(document).on('click', '.listeo-media-remove', function(e) {
                            e.preventDefault();
                            var button = $(this);
                            var targetInput = button.data('target');
                            var preview = button.closest('.listeo-media-uploader').find('.listeo-media-preview');

                            $('#' + targetInput).val('');
                            preview.empty();
                            button.hide();
                        });

                        // Toggle taxonomy slug translations visibility
                        $('#editor_register_taxonomy').on('change', function() {
                            if ($(this).is(':checked')) {
                                $('#taxonomy_slug_translations_section').slideDown(300);
                            } else {
                                $('#taxonomy_slug_translations_section').slideUp(300);
                            }
                        });

                        // Delete listing type handler
                        $('.listeo-delete-type-btn').on('click', function(e) {
                            e.preventDefault();
                            var button = $(this);
                            var typeId = button.data('type-id');
                            var typeName = button.data('type-name');

                            if (confirm(<?php echo wp_json_encode( __('Are you sure you want to delete this listing type?', 'listeo_core') ); ?> + '\n\n"' + typeName + '"')) {
                                button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + <?php echo wp_json_encode( __('Deleting...', 'listeo_core') ); ?>);

                                $.post(ajaxurl, {
                                    action: 'listeo_delete_custom_type',
                                    type_id: typeId,
                                    nonce: '<?php echo wp_create_nonce('listeo_custom_types'); ?>'
                                }, function(response) {
                                    if (response.success) {
                                        window.location.href = '<?php echo esc_url( admin_url('admin.php?page=listeo-listing-types') ); ?>';
                                    } else {
                                        alert(response.data.message || <?php echo wp_json_encode( __('Error deleting type', 'listeo_core') ); ?>);
                                        button.prop('disabled', false).html('<i class="fa fa-trash"></i> ' + <?php echo wp_json_encode( __('Delete Listing Type', 'listeo_core') ); ?>);
                                    }
                                }).fail(function() {
                                    alert(<?php echo wp_json_encode( __('Error deleting type', 'listeo_core') ); ?>);
                                    button.prop('disabled', false).html('<i class="fa fa-trash"></i> ' + <?php echo wp_json_encode( __('Delete Listing Type', 'listeo_core') ); ?>);
                                });
                            }
                        });
                    });
                </script>

                <!-- CSS moved to admin.css file -->
            </div>
        </div>
    <?php
    }

    /**
     * Render integrated page (called from main admin)
     */
    public function render_integrated_page()
    {
        $custom_types_manager = listeo_core_custom_listing_types();
        $listing_types = $custom_types_manager->get_listing_types(false, true);

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $type_id = isset($_GET['type_id']) ? intval($_GET['type_id']) : 0;

        // Enqueue scripts for this page
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_media(); // For media uploader

        // Debug migration if requested
        if (isset($_GET['debug_migration'])) {
            include_once LISTEO_PLUGIN_DIR . 'debug-migration.php';
            return;
        }

    ?>
        <div class="wrap listeo-listing-types-admin">
            <h1>
                <?php _e('Listing Types Management', 'listeo_core'); ?>
                <?php if ($action == 'list'): ?>
                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=add'); ?>" class="page-title-action">
                        <?php _e('Add New Type', 'listeo_core'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=refresh_taxonomies'); ?>" class="page-title-action">
                        <?php _e('Refresh Taxonomies', 'listeo_core'); ?>
                    </a>
                    <?php if (current_user_can('manage_options') && (defined('WP_DEBUG') && WP_DEBUG)): ?>
                        <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=force_update_defaults'); ?>" class="page-title-action" onclick="return confirm('<?php _e('This will update default types to new booking system. Are you sure?', 'listeo_core'); ?>')">
                            <?php _e('Update Default Types', 'listeo_core'); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </h1>
            <p class="description">
                <?php _e('Manage your custom listing types. Define different types of listings with specific features, booking options, and capabilities.', 'listeo_core'); ?>
            </p>

            <?php
            switch ($action) {
                case 'add':
                case 'edit':
                    $this->render_modern_type_form($type_id);
                    break;
                case 'list':
                default:
                    $this->render_grid_types_list($listing_types);
                    break;
            }
            ?>
        </div>

        <!-- CSS moved to admin.css file -->
    <?php
    }

    /**
     * Render content for Listeo Core UI tab
     */
    public function render_tab_content()
    {
        $custom_types_manager = listeo_core_custom_listing_types();

        // Force migration check every time we access the admin
        $custom_types_manager->maybe_create_table();
        $custom_types_manager->migrate_default_types();

        // Enqueue media scripts for icon upload
        wp_enqueue_media();

        $listing_types = $custom_types_manager->get_listing_types(false, true);

        $action = isset($_GET['listeo_action']) ? sanitize_text_field($_GET['listeo_action']) : 'list';
        $type_id = isset($_GET['listeo_type_id']) ? intval($_GET['listeo_type_id']) : 0;

        // Handle force seed action
        if ($action === 'force_seed' && current_user_can('manage_options')) {
            $custom_types_manager->force_seed_default_types();
            wp_redirect(admin_url('admin.php?page=listeo_settings&tab=listing_types&seeded=1'));
            exit;
        }

        // Handle force update defaults action
        if ($action === 'force_update_defaults' && current_user_can('manage_options')) {
            $custom_types_manager->force_update_default_types();
            wp_redirect(admin_url('admin.php?page=listeo_settings&tab=listing_types&defaults_updated=1'));
            exit;
        }

        // Enqueue scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');

        // Render appropriate content based on action
        if ($action === 'add' || $action === 'edit') {
            $this->render_tab_form($type_id);
        } else {
            //    $this->render_tab_list($listing_types);
        }
    }

    /**
     * Render listing types list for tab
     */
    private function render_tab_list($listing_types)
    {
    ?>
        <div class="listeo-types-tab-content">
            <?php if (isset($_GET['seeded'])): ?>
                <div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
                    <p><strong><?php _e('Default listing types have been seeded successfully!', 'listeo_core'); ?></strong></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['defaults_updated'])): ?>
                <div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
                    <p><strong><?php _e('Default listing types have been updated to new booking system successfully!', 'listeo_core'); ?></strong></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['taxonomies_refreshed'])): ?>
                <div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
                    <p><strong><?php _e('Taxonomies have been refreshed successfully!', 'listeo_core'); ?></strong></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><?php _e('Manage Listing Types', 'listeo_core'); ?></h3>
                <div>
                    <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types&listeo_action=add'); ?>" class="button button-primary">
                        <i class="fa fa-plus"></i> <?php _e('Add New Type', 'listeo_core'); ?>
                    </a>
                    <?php if (current_user_can('manage_options') && (defined('WP_DEBUG') && WP_DEBUG)): ?>
                        <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types&listeo_action=force_seed'); ?>" class="button" style="margin-left: 10px;" onclick="return confirm('<?php _e('This will reset all default types. Are you sure?', 'listeo_core'); ?>')">
                            <i class="fa fa-refresh"></i> <?php _e('Force Seed Defaults', 'listeo_core'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types&listeo_action=force_update_defaults'); ?>" class="button" style="margin-left: 10px;" onclick="return confirm('<?php _e('This will update default types to new booking system. Are you sure?', 'listeo_core'); ?>')">
                            <i class="fa fa-sync"></i> <?php _e('Update Default Types', 'listeo_core'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <p class="description" style="margin-bottom: 25px;">
                <?php _e('Define different types of listings with specific features, booking options, and capabilities. Custom listing types allow you to extend beyond the default Service, Rental, Event, and Classifieds types.', 'listeo_core'); ?>
            </p>


            <?php if (empty($listing_types)): ?>
                <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 4px;">
                    <h4><?php _e('No custom listing types found', 'listeo_core'); ?></h4>
                    <p><?php _e('Create your first custom listing type to get started!', 'listeo_core'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types&listeo_action=add'); ?>" class="button button-primary">
                        <?php _e('Add Your First Listing Type', 'listeo_core'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="listeo-types-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px;">
                    <?php foreach ($listing_types as $type): ?>
                        <div class="listeo-type-card" style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center;">
                                    <?php
                                    // Check for icon in DB first, only fallback to legacy option when DB value is NULL (not yet migrated)
                                    $icon_id = 0;
                                    if ($type->icon_id !== null) {
                                        $icon_id = intval($type->icon_id);
                                    } else {
                                        $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                    }

                                    if ($icon_id) {
                                        $icon_url = wp_get_attachment_url($icon_id);
                                        if ($icon_url) {
                                            if (get_post_mime_type($icon_id) === 'image/svg+xml') {
                                                echo '<div style="width: 32px; height: 32px; margin-right: 12px; flex-shrink: 0;">' . listeo_render_svg_icon($icon_id) . '</div>';
                                            } else {
                                                echo '<img src="' . esc_url($icon_url) . '" style="width: 32px; height: 32px; object-fit: contain; margin-right: 12px; flex-shrink: 0;">';
                                            }
                                        }
                                    }
                                    ?>
                                    <div>
                                        <h4 style="margin: 0 0 5px 0; display: flex; align-items: center;">
                                            <?php echo esc_html($type->name); ?>
                                            <?php if ($type->is_default): ?>
                                                <span style="background: #72aee6; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 8px;">
                                                    <?php _e('Default', 'listeo_core'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </h4>
                                        <code style="background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-size: 12px;">
                                            <?php echo esc_html($type->slug); ?>
                                        </code>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($type->is_active): ?>
                                        <span style="color: #46b450; font-weight: 600; font-size: 12px;">●</span>
                                    <?php else: ?>
                                        <span style="color: #dc3232; font-weight: 600; font-size: 12px;">●</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($type->description): ?>
                                <p style="color: #666; font-size: 13px; margin: 0 0 15px 0;">
                                    <?php echo esc_html($type->description); ?>
                                </p>
                            <?php endif; ?>

                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <?php
                                    // Show booking type
                                    $booking_type = isset($type->booking_type) ? $type->booking_type : 'none';
                                    $presets = Listeo_Core_Custom_Listing_Types::get_booking_type_presets();
                                    if (isset($presets[$booking_type])) {
                                        $booking_label = $presets[$booking_type]['label'];
                                        $badge_color = $booking_type === 'none' ? '#999' : '#0073aa';
                                        echo '<span style="background: ' . $badge_color . '; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">' . esc_html($booking_label) . '</span>';
                                    }

                                    // Show active features from JSON
                                    $features = isset($type->booking_features) ? json_decode($type->booking_features, true) : array();
                                    if (is_array($features) && !empty($features)) {
                                        $available_features = Listeo_Core_Custom_Listing_Types::get_available_booking_features();
                                        foreach ($features as $feature_key) {
                                            if (isset($available_features[$feature_key])) {
                                                $feature_label = $available_features[$feature_key]['label'];
                                                echo '<span style="background: #46b450; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">' . esc_html($feature_label) . '</span>';
                                            }
                                        }
                                    }

                                    // Show taxonomy if enabled
                                    if (isset($type->register_taxonomy) && $type->register_taxonomy): ?>
                                        <span style="background: #f56e28; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;"><?php _e('Taxonomy', 'listeo_core'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #eee;">
                                <div style="font-size: 13px; color: #666;">
                                    <?php
                                    $count = isset($type->listing_count) ? $type->listing_count : 0;
                                    printf(_n('%s listing', '%s listings', $count, 'listeo_core'), number_format_i18n($count));
                                    ?>
                                </div>
                                <div>
                                    <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types&listeo_action=edit&listeo_type_id=' . $type->id); ?>" class="button button-small">
                                        <i class="fa fa-edit"></i> <?php _e('Edit', 'listeo_core'); ?>
                                    </a>
                                    <?php if (!$type->is_default): ?>
                                        <button type="button" class="button button-small listeo-delete-btn" data-type-id="<?php echo $type->id; ?>" data-type-name="<?php echo esc_attr($type->name); ?>" style="color: #a00; margin-left: 5px;">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.listeo-delete-btn').on('click', function(e) {
                    e.preventDefault();
                    var typeId = $(this).data('type-id');
                    var typeName = $(this).data('type-name');

                    if (confirm(<?php echo wp_json_encode( __('Are you sure you want to delete this listing type?', 'listeo_core') ); ?> + '\n\n"' + typeName + '"')) {
                        $.post(ajaxurl, {
                            action: 'listeo_delete_custom_type',
                            type_id: typeId,
                            nonce: '<?php echo wp_create_nonce('listeo_custom_types'); ?>'
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || <?php echo wp_json_encode( __('Error deleting type', 'listeo_core') ); ?>);
                            }
                        });
                    }
                });
            });
        </script>
    <?php
    }

    /**
     * Render form for tab
     */
    private function render_tab_form($type_id = 0)
    {
        $custom_types_manager = listeo_core_custom_listing_types();
        $type = null;

        if ($type_id > 0) {
            global $wpdb;
            $type = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$custom_types_manager->get_table_name()} WHERE id = %d",
                $type_id
            ));
        }

        $is_edit = ($type !== null);
        $form_title = $is_edit ? __('Edit Listing Type', 'listeo_core') : __('Add New Listing Type', 'listeo_core');
    ?>

        <div class="listeo-types-tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><?php echo $form_title; ?></h3>
                <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types'); ?>" class="button">
                    ← <?php _e('Back to Listing Types', 'listeo_core'); ?>
                </a>
            </div>

            <form method="post" class="listeo-tab-type-form" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <?php wp_nonce_field('listeo_custom_type_tab_form', 'listeo_custom_type_tab_nonce'); ?>
                <input type="hidden" name="listeo_custom_type_tab_action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="type_id" value="<?php echo $type->id; ?>">
                <?php endif; ?>

                <!-- Basic Information Section -->
                <div style="margin-bottom: 30px;">
                    <h4 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                        <i class="fa fa-info-circle"></i> <?php _e('Basic Information', 'listeo_core'); ?>
                    </h4>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="tab_type_name"><?php _e('Name', 'listeo_core'); ?> <span style="color: #d63638;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="tab_type_name" name="name" value="<?php echo $is_edit ? esc_attr($type->name) : ''; ?>" class="regular-text" required>
                                    <p class="description"><?php _e('The display name for this listing type.', 'listeo_core'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="tab_type_plural_name"><?php _e('Plural Name', 'listeo_core'); ?> <span style="color: #d63638;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="tab_type_plural_name" name="plural_name" value="<?php echo $is_edit ? esc_attr($type->plural_name) : ''; ?>" class="regular-text" required>
                                    <p class="description"><?php _e('The plural form of the name (e.g., "Services", "Rentals").', 'listeo_core'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="tab_type_slug"><?php _e('Slug', 'listeo_core'); ?> <span style="color: #d63638;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="tab_type_slug" name="slug" value="<?php echo $is_edit ? esc_attr($type->slug) : ''; ?>" class="regular-text" required <?php echo $is_edit ? 'readonly' : ''; ?>>
                                    <p class="description">
                                        <?php _e('URL-friendly version of the name. Must be unique and contain only lowercase letters, numbers, and hyphens.', 'listeo_core'); ?>
                                        <?php if ($is_edit): ?>
                                            <br><strong><?php _e('Note: Slug cannot be changed after creation.', 'listeo_core'); ?></strong>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="tab_type_description"><?php _e('Description', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <textarea id="tab_type_description" name="description" rows="3" class="large-text"><?php echo $is_edit ? esc_textarea($type->description) : ''; ?></textarea>
                                    <p class="description"><?php _e('Optional description of this listing type.', 'listeo_core'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="tab_type_icon"><?php _e('Icon', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    $icon_id = 0;
                                    if ($is_edit) {
                                        if ($type->icon_id !== null) {
                                            $icon_id = intval($type->icon_id);
                                        } else {
                                            // Only check legacy option when DB value is NULL (not yet migrated)
                                            $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                        }
                                    }

                                    $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';
                                    ?>
                                    <div class="listeo-media-uploader">
                                        <input type="hidden" id="tab_type_icon" name="icon_id" value="<?php echo esc_attr($icon_id); ?>">
                                        <div class="listeo-media-preview" style="margin-bottom: 10px;">
                                            <?php if ($icon_url): ?>
                                                <img src="<?php echo esc_url($icon_url); ?>" style="max-width: 64px; max-height: 64px; border: 1px solid #ddd; padding: 5px;">
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button listeo-media-upload" data-target="tab_type_icon">
                                            <?php _e('Choose Icon', 'listeo_core'); ?>
                                        </button>
                                        <?php if ($icon_id): ?>
                                            <button type="button" class="button listeo-media-remove" data-target="tab_type_icon" style="margin-left: 5px;">
                                                <?php _e('Remove', 'listeo_core'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php _e('Choose an icon for this listing type. Supports SVG and image formats.', 'listeo_core'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Booking Settings Section -->
                <div style="margin-bottom: 30px;">
                    <h4 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                        <i class="fa fa-calendar-check-o"></i> <?php _e('Booking Settings', 'listeo_core'); ?>
                    </h4>
                    <table class="form-table">
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <!-- Supported Features Section -->
                <div style="margin-bottom: 30px;">
                    <h4 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                        <i class="fa fa-cogs"></i> <?php _e('Supported Features', 'listeo_core'); ?>
                    </h4>
                    <table class="form-table">
                        <tbody>
                            <!-- Old feature support checkboxes removed - now handled by booking_features system -->
                        </tbody>
                    </table>
                </div>

                <!-- Taxonomy Settings Section -->
                <div style="margin-bottom: 30px;">
                    <h4 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                        <i class="fa fa-tags"></i> <?php _e('Taxonomy Settings', 'listeo_core'); ?>
                    </h4>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="tab_register_taxonomy"><?php _e('Dedicated Category Taxonomy', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <div class="lc-toggle-container">
                                        <div class="lc-toggle-content">
                                            <div class="lc-toggle-text">
                                                <h4 class="lc-toggle-title"><?php _e('Register dedicated taxonomy', 'listeo_core'); ?></h4>
                                                <p class="lc-toggle-description"><?php _e('Creates a dedicated category taxonomy (e.g., "Service Categories") for this listing type.', 'listeo_core'); ?></p>
                                            </div>
                                        </div>
                                        <label class="lc-toggle" for="tab_register_taxonomy">
                                            <input type="checkbox" id="tab_register_taxonomy" name="register_taxonomy" value="1" <?php echo ($is_edit ? (isset($type->register_taxonomy) && $type->register_taxonomy ? 'checked' : '') : 'checked="checked"'); ?>>
                                            <span class="lc-toggle-slider"></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Display Settings Section -->
                <div style="margin-bottom: 30px;">
                    <h4 style="border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                        <i class="fa fa-eye"></i> <?php _e('Display Settings', 'listeo_core'); ?>
                    </h4>
                    <table class="form-table">
                        <tbody>

                            <tr>
                                <th scope="row">
                                    <label for="tab_is_active"><?php _e('Status', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <div class="lc-toggle-container">
                                        <div class="lc-toggle-content">
                                            <div class="lc-toggle-text">
                                                <h4 class="lc-toggle-title"><?php _e('Active (available for new listings)', 'listeo_core'); ?></h4>
                                                <p class="lc-toggle-description"><?php _e('Inactive types will not be available when creating new listings.', 'listeo_core'); ?></p>
                                            </div>
                                        </div>
                                        <label class="lc-toggle" for="tab_is_active">
                                            <input type="checkbox" id="tab_is_active" name="is_active" value="1" <?php echo ($is_edit && $type->is_active) || !$is_edit ? 'checked' : ''; ?>>
                                            <span class="lc-toggle-slider"></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="text-align: right; padding-top: 20px; border-top: 1px solid #ddd;">
                    <a href="<?php echo admin_url('admin.php?page=listeo_settings&tab=listing_types'); ?>" class="button" style="margin-right: 10px;">
                        <?php _e('Cancel', 'listeo_core'); ?>
                    </a>
                    <input type="submit" name="submit" class="button-primary" value="<?php echo $is_edit ? __('Update Listing Type', 'listeo_core') : __('Create Listing Type', 'listeo_core'); ?>">
                </div>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Auto-generate slug from name
                $('#tab_type_name').on('input', function() {
                    if (!$('#tab_type_slug').prop('readonly')) {
                        var slug = $(this).val()
                            .toLowerCase()
                            .replace(/[^a-z0-9\s-]/g, '')
                            .replace(/[\s]+/g, '-')
                            .replace(/-+/g, '-')
                            .replace(/^-|-$/g, '');
                        $('#tab_type_slug').val(slug);
                    }
                });

                // Note: Plural name field is now manual input only - no auto-generation

                // Toggle booking-related options based on booking enabled
                // Old booking feature checkboxes removed - now handled by booking_features system

                // Media uploader functionality
                $('.listeo-media-upload').on('click', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var targetInputId = button.data('target');
                    var uploader = button.closest('.listeo-media-uploader');
                    var preview = uploader.find('.listeo-media-preview');

                    var mediaUploader = wp.media({
                        title: <?php echo wp_json_encode( __('Choose Icon', 'listeo_core') ); ?>,
                        button: {
                            text: <?php echo wp_json_encode( __('Use This Icon', 'listeo_core') ); ?>
                        },
                        multiple: false,
                        library: {
                            type: ['image']
                        }
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();

                        // Update hidden input value
                        var $inputField = $('#' + targetInputId);
                        if ($inputField.length) {
                            $inputField.val(attachment.id);
                        }

                        // Update preview
                        preview.html('<img src="' + attachment.url + '" style="max-width: 64px; max-height: 64px; border: 1px solid #ddd; padding: 5px;">');

                        // Show/add remove button
                        var removeBtn = uploader.find('.listeo-media-remove');
                        if (removeBtn.length) {
                            removeBtn.show();
                        }
                    });

                    mediaUploader.open();
                });

                $(document).on('click', '.listeo-media-remove', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var targetInput = button.data('target');
                    var preview = button.closest('.listeo-media-uploader').find('.listeo-media-preview');

                    $('#' + targetInput).val('');
                    preview.empty();
                    button.hide();
                });
            });
        </script>
    <?php
    }

    /**
     * Admin initialization
     */
    public function admin_init()
    {
        // Handle form submissions
        if (isset($_POST['listeo_custom_type_action'])) {
            $this->handle_form_submission();
        }

        // Handle tab form submissions
        if (isset($_POST['listeo_custom_type_tab_action'])) {
            $this->handle_tab_form_submission();
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, self::ADMIN_PAGE_SLUG) !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_media();
            wp_enqueue_style('wp-admin');

            // Enqueue admin CSS for drag and drop styles and toggle sliders
            wp_enqueue_style('listeo-core-admin', plugin_dir_url(__FILE__) . '../assets/css/admin.css', array(), '1.0.0');

            // Only enqueue modern admin CSS if it's not already enqueued by the main admin class
            if (!wp_style_is('listeo-modern-admin', 'enqueued')) {
                wp_enqueue_style('listeo-modern-admin', plugin_dir_url(__FILE__) . '../assets/css/listeo-modern-admin.css', array(), '1.0.0');
            }

            // Add JavaScript for drag and drop functionality in admin footer
            add_action('admin_footer', array($this, 'add_sortable_javascript'));
        }
    }

    /**
     * Add JavaScript for sortable functionality
     */
    public function add_sortable_javascript()
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Debug: Check if elements exist
            console.log('Listeo Debug: Grid elements found:', $('.listeo-types-grid').length);
            console.log('Listeo Debug: Type items found:', $('.listeo-type-item').length);
            console.log('Listeo Debug: Drag buttons found:', $('.listeo-drag-btn').length);
            console.log('Listeo Debug: jQuery UI Sortable available:', typeof $.fn.sortable);
            
            // Simple test for drag button responsiveness
            $('.listeo-drag-btn').on('mousedown', function(e) {
                console.log('Listeo Debug: Drag button mousedown event triggered');
            });
            
            // Test simple sortable first (without handle restriction)
            if ($('.listeo-types-grid').length > 0) {
                console.log('Listeo Debug: Attempting to initialize sortable...');
                
                try {
                    $('.listeo-types-grid').sortable({
                    items: '.listeo-type-item',
                    // Don't restrict handles - allow dragging anywhere on the item
                    cursor: 'move',
                    placeholder: 'listeo-type-item-placeholder',
                    tolerance: 'pointer',
                    opacity: 0.8,
                    containment: 'parent',
                    disabled: false,
                start: function(event, ui) {
                    // Add visual feedback when dragging starts
                    ui.item.addClass('listeo-dragging');
                    $('.listeo-types-grid').addClass('listeo-sorting-active');
                },
                stop: function(event, ui) {
                    // Remove visual feedback when dragging stops
                    ui.item.removeClass('listeo-dragging');
                    $('.listeo-types-grid').removeClass('listeo-sorting-active');
                    
                    // Get the new order of type IDs
                    var typeIds = [];
                    $('.listeo-type-item').each(function() {
                        var typeId = $(this).data('type-id');
                        if (typeId) {
                            typeIds.push(typeId);
                        }
                    });
                    
                    // Send AJAX request to update the order
                    if (typeIds.length > 0) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'listeo_reorder_listing_types',
                                nonce: '<?php echo wp_create_nonce('listeo_custom_types'); ?>',
                                type_ids: typeIds
                            },
                            beforeSend: function() {
                                // Show loading indicator
                                ui.item.append('<div class="listeo-reorder-loading"><span class="spinner is-active"></span></div>');
                            },
                            success: function(response) {
                                // Remove loading indicator
                                $('.listeo-reorder-loading').remove();
                                
                                if (response.success) {
                                    // Show success feedback
                                    ui.item.addClass('listeo-reorder-success');
                                    setTimeout(function() {
                                        ui.item.removeClass('listeo-reorder-success');
                                    }, 2000);
                                } else {
                                    // Show error and revert order
                                    alert('<?php echo esc_js(__('Error reordering types: ', 'listeo_core')); ?>' + response.data.message);
                                    $('.listeo-types-grid').sortable('cancel');
                                }
                            },
                            error: function() {
                                // Remove loading indicator and show error
                                $('.listeo-reorder-loading').remove();
                                alert('<?php echo esc_js(__('Error: Could not save new order', 'listeo_core')); ?>');
                                $('.listeo-types-grid').sortable('cancel');
                            }
                        });
                    }
                }
                    });
                    
                    console.log('Listeo Debug: Sortable initialized successfully');
                } catch (error) {
                    console.error('Listeo Debug: Error initializing sortable:', error);
                }
            } else {
                console.log('Listeo Debug: No .listeo-types-grid found!');
            }
            
            // Handle delete button clicks
            $('.listeo-delete-btn').on('click', function(e) {
                e.preventDefault();
                var typeId = $(this).data('type-id');
                var typeName = $(this).data('type-name');
                
                if (confirm('Are you sure you want to delete the "' + typeName + '" listing type? This action cannot be undone.')) {
                    // Add your delete logic here
                    console.log('Delete type:', typeId);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render modern listing types list
     */
    private function render_modern_types_list($listing_types)
    {
    ?>
        <div class="card">
            <h2><?php _e('Available Listing Types', 'listeo_core'); ?></h2>
            <?php if (empty($listing_types)): ?>
                <div class="inside">
                    <p><?php _e('No listing types found. Add your first custom listing type to get started!', 'listeo_core'); ?></p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=add'); ?>" class="button button-primary">
                            <?php _e('Add Your First Listing Type', 'listeo_core'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <table class="listeo-listing-types-table widefat">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-name column-primary">
                                <?php _e('Name', 'listeo_core'); ?>
                            </th>
                            <th scope="col" class="manage-column column-icon" style="width: 60px;">
                                <?php _e('Icon', 'listeo_core'); ?>
                            </th>
                            <th scope="col" class="manage-column column-slug">
                                <?php _e('Slug', 'listeo_core'); ?>
                            </th>
                            <th scope="col" class="manage-column column-features">
                                <?php _e('Booking Type & Features', 'listeo_core'); ?>
                            </th>
                            <th scope="col" class="manage-column column-listings">
                                <?php _e('Listings', 'listeo_core'); ?>
                            </th>
                            <th scope="col" class="manage-column column-status">
                                <?php _e('Status', 'listeo_core'); ?>
                            </th>
                            <th scope="col" class="manage-column column-actions">
                                <?php _e('Actions', 'listeo_core'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listing_types as $type): ?>
                            <tr>
                                <td class="column-name column-primary">
                                    <strong><?php echo esc_html($type->name); ?></strong>
                                    <?php if ($type->is_default): ?>
                                        <span class="listeo-default-badge" title="<?php _e('Default type', 'listeo_core'); ?>">
                                            <?php _e('Default', 'listeo_core'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=edit&type_id=' . $type->id); ?>">
                                                <?php _e('Edit', 'listeo_core'); ?>
                                            </a>
                                        </span>
                                        <?php if (!$type->is_default): ?>
                                            | <span class="delete">
                                                <a href="#" class="listeo-delete-type" data-type-id="<?php echo $type->id; ?>" data-type-name="<?php echo esc_attr($type->name); ?>">
                                                    <?php _e('Delete', 'listeo_core'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-icon" style="text-align: center;">
                                    <?php
                                    // Check for icon in DB first, only fallback to legacy option when DB value is NULL (not yet migrated)
                                    $icon_id = 0;
                                    if ($type->icon_id !== null) {
                                        $icon_id = intval($type->icon_id);
                                    } else {
                                        $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                    }

                                    if ($icon_id) {
                                        $icon_url = wp_get_attachment_url($icon_id);
                                        if ($icon_url) {
                                            if (get_post_mime_type($icon_id) === 'image/svg+xml') {
                                                echo '<div style="width: 32px; height: 32px; display: inline-block;">' . listeo_render_svg_icon($icon_id) . '</div>';
                                            } else {
                                                echo '<img src="' . esc_url($icon_url) . '" style="width: 32px; height: 32px; object-fit: contain;">';
                                            }
                                        } else {
                                            echo '<span style="color: #999;" title="Invalid icon ID: ' . $icon_id . '">—</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #999;" title="No icon set">—</span>';
                                    }
                                    ?>
                                </td>
                                <td class="column-slug">
                                    <code><?php echo esc_html($type->slug); ?></code>
                                </td>
                                <td class="column-features">
                                    <div class="listeo-type-features">
                                        <?php
                                        // Show booking type
                                        $booking_type = isset($type->booking_type) ? $type->booking_type : 'none';
                                        $presets = Listeo_Core_Custom_Listing_Types::get_booking_type_presets();
                                        if (isset($presets[$booking_type])) {
                                            $booking_label = $presets[$booking_type]['label'];
                                            $badge_color = $booking_type === 'none' ? '#999' : '#0073aa';
                                            echo '<span class="listeo-feature-badge" style="background: ' . $badge_color . '; color: white; margin-right: 4px;">' . esc_html($booking_label) . '</span>';
                                        }

                                        // Show active features from JSON
                                        $features = isset($type->booking_features) ? json_decode($type->booking_features, true) : array();
                                        if (is_array($features)) {
                                            $available_features = Listeo_Core_Custom_Listing_Types::get_available_booking_features();
                                            $feature_count = 0;
                                            foreach ($features as $feature_key) {
                                                if (isset($available_features[$feature_key]) && $feature_count < 3) {
                                                    $feature_label = $available_features[$feature_key]['label'];
                                                    echo '<span class="listeo-feature-badge" style="background: #46b450; color: white; font-size: 10px;">' . esc_html($feature_label) . '</span>';
                                                    $feature_count++;
                                                }
                                            }
                                            if (count($features) > 3) {
                                                echo '<span class="listeo-feature-badge" style="background: #666; color: white; font-size: 10px;">+' . (count($features) - 3) . ' more</span>';
                                            }
                                        }

                                        // Show taxonomy if enabled
                                        if (isset($type->register_taxonomy) && $type->register_taxonomy): ?>
                                            <span class="listeo-feature-badge" style="background: #f56e28; color: white; font-size: 10px;"><?php _e('Taxonomy', 'listeo_core'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-listings">
                                    <?php
                                    $count = isset($type->listing_count) ? $type->listing_count : 0;
                                    printf(_n('%s listing', '%s listings', $count, 'listeo_core'), number_format_i18n($count));
                                    ?>
                                </td>
                                <td class="column-status">
                                    <?php if ($type->is_active): ?>
                                        <span class="listeo-status-active"><?php _e('Active', 'listeo_core'); ?></span>
                                    <?php else: ?>
                                        <span class="listeo-status-inactive"><?php _e('Inactive', 'listeo_core'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=edit&type_id=' . $type->id); ?>" class="button button-small">
                                        <?php _e('Edit', 'listeo_core'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- CSS moved to admin.css file -->

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.listeo-delete-type').on('click', function(e) {
                    e.preventDefault();
                    var typeId = $(this).data('type-id');
                    var typeName = $(this).data('type-name');

                    if (confirm(<?php echo wp_json_encode( __('Are you sure you want to delete this listing type?', 'listeo_core') ); ?> + '\n\n"' + typeName + '"')) {
                        $.post(ajaxurl, {
                            action: 'listeo_delete_custom_type',
                            type_id: typeId,
                            nonce: '<?php echo wp_create_nonce('listeo_custom_types'); ?>'
                        }, function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || <?php echo wp_json_encode( __('Error deleting type', 'listeo_core') ); ?>);
                            }
                        });
                    }
                });
            });
        </script>
    <?php
    }

    /**
     * Render grid layout for listing types (sortable)
     */
    private function render_grid_types_list($listing_types)
    {
    ?>
        <div class="card">
            <h2><?php _e('Available Listing Types', 'listeo_core'); ?></h2>
            <p class="description">
                <?php _e('Drag and drop to reorder listing types. Changes are saved automatically.', 'listeo_core'); ?>
            </p>
            
            <?php if (empty($listing_types)): ?>
                <div class="inside">
                    <div style="text-align: center; padding: 40px; background: #f9f9f9; border: 2px dashed #ddd; border-radius: 8px;">
                        <h3><?php _e('No listing types found', 'listeo_core'); ?></h3>
                        <p><?php _e('Create your first custom listing type to get started!', 'listeo_core'); ?></p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=add'); ?>" class="button button-primary">
                                <?php _e('Add Your First Listing Type', 'listeo_core'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="inside">
                    <div class="listeo-types-grid">
                        <?php foreach ($listing_types as $index => $type): ?>
                            <div class="form_item listeo-type-item" data-type-id="<?php echo $type->id; ?>">
                                <div class="listeo-type-header">
                                    <div class="listeo-type-icon">
                                        <?php
                                        // Check for icon in DB first, only fallback to legacy option when DB value is NULL (not yet migrated)
                                        $icon_id = 0;
                                        if ($type->icon_id !== null) {
                                            $icon_id = intval($type->icon_id);
                                        } else {
                                            $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                        }

                                        if ($icon_id) {
                                            $icon_url = wp_get_attachment_url($icon_id);
                                            if ($icon_url) {
                                                if (get_post_mime_type($icon_id) === 'image/svg+xml') {
                                                    echo '<div class="listeo-type-icon-wrapper"><div class="listeo-type-icon-content">' . listeo_render_svg_icon($icon_id) . '</div></div>';
                                                } else {
                                                    echo '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($type->name) . '" class="listeo-type-icon-img">';
                                                }
                                            }
                                        } else {
                                            echo '<div class="listeo-type-icon-placeholder">?</div>';
                                        }
                                        ?>
                                    </div>

                                    <div class="listeo-type-title">
                                        <div class="listeo-type-info">
                                            <div class="listeo-type-status">
                                                <?php if ($type->is_active): ?>
                                                    <span class="listeo-status-active">● <?php _e('Active', 'listeo_core'); ?></span>
                                                <?php else: ?>
                                                    <span class="listeo-status-inactive">● <?php _e('Inactive', 'listeo_core'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <h4 class="listeo-type-name">
                                            <?php echo esc_html($type->name); ?>
                                            <?php if ($type->is_default): ?>
                                                <span class="listeo-default-badge"><?php _e('Default', 'listeo_core'); ?></span>
                                            <?php endif; ?>
                                        </h4>

                                        <?php if ($type->description): ?>
                                            <p class="listeo-type-description"><?php echo esc_html($type->description); ?></p>
                                        <?php endif; ?>

                                        <div class="listeo-type-features">
                                            <?php
                                            // Show booking type
                                            $booking_type = isset($type->booking_type) ? $type->booking_type : 'none';
                                            $presets = Listeo_Core_Custom_Listing_Types::get_booking_type_presets();
                                            if (isset($presets[$booking_type])) {
                                                $booking_label = $presets[$booking_type]['label'];
                                                $badge_class = $booking_type === 'none' ? 'listeo-feature-tag-disabled' : 'listeo-feature-tag-primary';
                                                echo '<span class="listeo-feature-tag ' . $badge_class . '">' . esc_html($booking_label) . '</span>';
                                            }

                                            // Show active features from JSON
                                            $features = isset($type->booking_features) ? json_decode($type->booking_features, true) : array();
                                            if (is_array($features) && !empty($features)) {
                                                $available_features = Listeo_Core_Custom_Listing_Types::get_available_booking_features();
                                                foreach ($features as $feature_key) {
                                                    if (isset($available_features[$feature_key])) {
                                                        $feature_label = $available_features[$feature_key]['label'];
                                                        echo '<span class="listeo-feature-tag">' . esc_html($feature_label) . '</span>';
                                                    }
                                                }
                                            }

                                            // Show taxonomy if enabled
                                            if (isset($type->register_taxonomy) && $type->register_taxonomy): ?>
                                                <span class="listeo-feature-tag listeo-feature-tag-secondary"><?php _e('Taxonomy', 'listeo_core'); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="listeo-type-meta">
                                            <span class="listeo-listings-count">
                                                <?php
                                                $count = isset($type->listing_count) ? $type->listing_count : 0;
                                                printf(_n('%s listing', '%s listings', $count, 'listeo_core'), number_format_i18n($count));
                                                ?>
                                            </span>
                                        </div>

                                        <div class="listeo-type-actions">
                                            <div class="button listeo-drag-btn" title="<?php _e('Drag to reorder', 'listeo_core'); ?>">
                                                <span class="dashicons dashicons-menu"></span>
                                            </div>
                                            <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=edit&type_id=' . $type->id); ?>" class="button listeo-edit-btn">
                                                <?php _e('Edit', 'listeo_core'); ?>
                                            </a>
                                            <?php if (!$type->is_default): ?>
                                                <button type="button" class="button listeo-delete-btn" data-type-id="<?php echo $type->id; ?>" data-type-name="<?php echo esc_attr($type->name); ?>">
                                                    <?php _e('Delete', 'listeo_core'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card-footer">
                <a href="<?php echo admin_url('admin.php?page=listeo-listing-types&action=add'); ?>" class="button button-primary">
                    <?php _e('Add New Listing Type', 'listeo_core'); ?>
                </a>
            </div>
        </div>
    <?php
    }

    /**
     * Render modern type add/edit form
     */
    private function render_modern_type_form($type_id = 0)
    {
        $custom_types_manager = listeo_core_custom_listing_types();
        $type = null;

        if ($type_id > 0) {
            global $wpdb;
            $type = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$custom_types_manager->get_table_name()} WHERE id = %d",
                $type_id
            ));
        }

        $is_edit = ($type !== null);
        $form_title = $is_edit ? __('Edit Listing Type', 'listeo_core') : __('Add New Listing Type', 'listeo_core');
    ?>

        <div class="listeo-type-form">
            <h2><?php echo $form_title; ?></h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=listeo-listing-types'); ?>" class="button">
                    ← <?php _e('Back to Listing Types', 'listeo_core'); ?>
                </a>
            </p>

            <form method="post" class="listeo-custom-type-form">
                <?php wp_nonce_field('listeo_custom_type_form', 'listeo_custom_type_nonce'); ?>
                <input type="hidden" name="listeo_custom_type_action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="type_id" value="<?php echo $type->id; ?>">
                <?php endif; ?>

                <!-- Basic Information Section -->
                <div class="listeo-form-section">
                    <h3><?php _e('Basic Information', 'listeo_core'); ?></h3>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="type_name"><?php _e('Name', 'listeo_core'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="type_name" name="name" value="<?php echo $is_edit ? esc_attr($type->name) : ''; ?>" class="regular-text" required>
                                    <p class="description"><?php _e('The display name for this listing type.', 'listeo_core'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="type_plural_name"><?php _e('Plural Name', 'listeo_core'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="type_plural_name" name="plural_name" value="<?php echo $is_edit ? esc_attr($type->plural_name) : ''; ?>" class="regular-text" required>
                                    <p class="description"><?php _e('The plural form of the name (e.g., "Services", "Rentals").', 'listeo_core'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="type_slug"><?php _e('Slug', 'listeo_core'); ?> <span class="required">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="type_slug" name="slug" value="<?php echo $is_edit ? esc_attr($type->slug) : ''; ?>" class="regular-text" required <?php echo $is_edit ? 'readonly' : ''; ?>>
                                    <p class="description">
                                        <?php _e('URL-friendly version of the name. Must be unique and contain only lowercase letters, numbers, and hyphens.', 'listeo_core'); ?>
                                        <?php if ($is_edit): ?>
                                            <br><strong><?php _e('Note: Slug cannot be changed after creation.', 'listeo_core'); ?></strong>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="type_description"><?php _e('Description', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <textarea id="type_description" name="description" rows="3" class="large-text"><?php echo $is_edit ? esc_textarea($type->description) : ''; ?></textarea>
                                    <p class="description"><?php _e('Optional description of this listing type.', 'listeo_core'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="type_icon"><?php _e('Icon', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    $icon_id = 0;
                                    if ($is_edit) {
                                        if ($type->icon_id !== null) {
                                            $icon_id = intval($type->icon_id);
                                        } else {
                                            // Only check legacy option when DB value is NULL (not yet migrated)
                                            $icon_id = intval(get_option('listeo_' . $type->slug . '_type_icon', 0));
                                        }
                                    }

                                    $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';
                                    ?>
                                    <div class="listeo-media-uploader">
                                        <input type="hidden" id="type_icon" name="icon_id" value="<?php echo esc_attr($icon_id); ?>">
                                        <div class="listeo-media-preview" style="margin-bottom: 10px;">
                                            <?php if ($icon_url): ?>
                                                <img src="<?php echo esc_url($icon_url); ?>" style="max-width: 64px; max-height: 64px; border: 1px solid #ddd; padding: 5px;">
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="button listeo-media-upload" data-target="type_icon">
                                            <?php _e('Choose Icon', 'listeo_core'); ?>
                                        </button>
                                        <?php if ($icon_id): ?>
                                            <button type="button" class="button listeo-media-remove" data-target="type_icon" style="margin-left: 5px;">
                                                <?php _e('Remove', 'listeo_core'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php _e('Choose an icon for this listing type. Supports SVG and image formats.', 'listeo_core'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Booking Settings Section -->
                <div class="listeo-form-section">
                    <h3><?php _e('Booking Settings', 'listeo_core'); ?></h3>
                    <table class="form-table">
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <!-- Supported Features Section -->
                <div class="listeo-form-section">
                    <h3><?php _e('Supported Features', 'listeo_core'); ?></h3>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><?php _e('Feature Support', 'listeo_core'); ?></th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text"><?php _e('Supported Features', 'listeo_core'); ?></legend>

                                        <!-- Old feature support checkboxes removed - now handled by booking_features system -->
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Taxonomy Settings Section -->
                <div class="listeo-form-section">
                    <h3><?php _e('Taxonomy Settings', 'listeo_core'); ?></h3>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="register_taxonomy"><?php _e('Dedicated Category Taxonomy', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <div class="lc-toggle-container">
                                        <div class="lc-toggle-content">
                                            <div class="lc-toggle-text">
                                                <h4 class="lc-toggle-title"><?php _e('Register dedicated taxonomy', 'listeo_core'); ?></h4>
                                                <p class="lc-toggle-description"><?php _e('Creates a dedicated category taxonomy (e.g., "Service Categories") for this listing type. Note: Disabling this will hide the existing taxonomy from admin menus but won\'t delete existing categories.', 'listeo_core'); ?></p>
                                            </div>
                                        </div>
                                        <label class="lc-toggle" for="register_taxonomy">
                                            <input type="checkbox" id="register_taxonomy" name="register_taxonomy" value="1" <?php echo ($is_edit ? (isset($type->register_taxonomy) && $type->register_taxonomy ? 'checked' : '') : 'checked="checked"'); ?>>
                                            <span class="lc-toggle-slider"></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Display Settings Section -->
                <div class="listeo-form-section">
                    <h3><?php _e('Display Settings', 'listeo_core'); ?></h3>
                    <table class="form-table">
                        <tbody>

                            <tr>
                                <th scope="row">
                                    <label for="is_active"><?php _e('Status', 'listeo_core'); ?></label>
                                </th>
                                <td>
                                    <div class="lc-toggle-container">
                                        <div class="lc-toggle-content">
                                            <div class="lc-toggle-text">
                                                <h4 class="lc-toggle-title"><?php _e('Active (available for new listings)', 'listeo_core'); ?></h4>
                                                <p class="lc-toggle-description"><?php _e('Inactive types will not be available when creating new listings.', 'listeo_core'); ?></p>
                                            </div>
                                        </div>
                                        <label class="lc-toggle" for="is_active">
                                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($is_edit && $type->is_active) || !$is_edit ? 'checked' : ''; ?>>
                                            <span class="lc-toggle-slider"></span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="<?php echo $is_edit ? __('Update Listing Type', 'listeo_core') : __('Create Listing Type', 'listeo_core'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=listeo-listing-types'); ?>" class="button">
                        <?php _e('Cancel', 'listeo_core'); ?>
                    </a>
                </p>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Auto-generate slug from name
                $('#type_name').on('input', function() {
                    if (!$('#type_slug').prop('readonly')) {
                        var slug = $(this).val()
                            .toLowerCase()
                            .replace(/[^a-z0-9\s-]/g, '')
                            .replace(/[\s]+/g, '-')
                            .replace(/-+/g, '-')
                            .replace(/^-|-$/g, '');
                        $('#type_slug').val(slug);
                    }
                });

                // Note: Plural name field is now manual input only - no auto-generation

                // Toggle booking-related options based on booking enabled
                // Old booking feature checkboxes removed - now handled by booking_features system

                // Media uploader functionality
                $('.listeo-media-upload').on('click', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var targetInputId = button.data('target');
                    var uploader = button.closest('.listeo-media-uploader');
                    var preview = uploader.find('.listeo-media-preview');

                    var mediaUploader = wp.media({
                        title: <?php echo wp_json_encode( __('Choose Icon', 'listeo_core') ); ?>,
                        button: {
                            text: <?php echo wp_json_encode( __('Use This Icon', 'listeo_core') ); ?>
                        },
                        multiple: false,
                        library: {
                            type: ['image']
                        }
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();

                        // Update hidden input value
                        var $inputField = $('#' + targetInputId);
                        if ($inputField.length) {
                            $inputField.val(attachment.id);
                        }

                        // Update preview
                        preview.html('<img src="' + attachment.url + '" style="max-width: 64px; max-height: 64px; border: 1px solid #ddd; padding: 5px;">');

                        // Show/add remove button
                        var removeBtn = uploader.find('.listeo-media-remove');
                        if (removeBtn.length) {
                            removeBtn.show();
                        }
                    });

                    mediaUploader.open();
                });

                $(document).on('click', '.listeo-media-remove', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var targetInput = button.data('target');
                    var preview = button.closest('.listeo-media-uploader').find('.listeo-media-preview');

                    $('#' + targetInput).val('');
                    preview.empty();
                    button.hide();
                });
            });
        </script>
<?php
    }

    /**
     * Handle form submission
     */
    private function handle_form_submission()
    {
        if (!wp_verify_nonce($_POST['listeo_custom_type_nonce'], 'listeo_custom_type_form')) {
            wp_die(__('Security check failed', 'listeo_core'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'listeo_core'));
        }

        $action = sanitize_text_field($_POST['listeo_custom_type_action']);
        $custom_types_manager = listeo_core_custom_listing_types();

        // Prepare data
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'plural_name' => sanitize_text_field($_POST['plural_name']),
            'slug' => sanitize_title($_POST['slug']),
            'description' => sanitize_textarea_field($_POST['description']),
            'icon_id' => isset($_POST['icon_id']) ? absint($_POST['icon_id']) : null,
            'booking_type' => isset($_POST['booking_type']) ? sanitize_text_field($_POST['booking_type']) : 'none',
            'booking_features' => isset($_POST['booking_features']) && is_array($_POST['booking_features']) ? $_POST['booking_features'] : array(),
            // Old feature support fields removed - now handled by booking_features system
            'supports_opening_hours' => isset($_POST['supports_opening_hours']) ? 1 : 0,
            'register_taxonomy' => isset($_POST['register_taxonomy']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'slug_translations' => isset($_POST['slug_translations']) && is_array($_POST['slug_translations']) ? $_POST['slug_translations'] : array()
        );



        if ($action === 'add') {
            $result = $custom_types_manager->insert_listing_type($data);
            if (!is_wp_error($result)) {
                do_action('listeo_listing_type_after_save', $data['slug'], $data, $_POST);
            }
        } else {
            $type_id = intval($_POST['type_id']);
            $slug = sanitize_title($_POST['slug']);
            unset($data['slug']); // Don't allow slug changes on edit
            $result = $custom_types_manager->update_listing_type($slug, $data);
            if (!is_wp_error($result)) {
                // Clear legacy icon option when icon is removed
                if (isset($data['icon_id']) && empty($data['icon_id'])) {
                    delete_option('listeo_' . $slug . '_type_icon');
                }
                do_action('listeo_listing_type_after_save', $slug, $data, $_POST);
            }
        }

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $message = $action === 'add'
                ? __('Listing type created successfully!', 'listeo_core')
                : __('Listing type updated successfully!', 'listeo_core');
            $this->add_admin_notice($message, 'success');

            // Schedule permalink flush if translations were saved
            if (!empty($data['slug_translations'])) {
                set_transient('listeo_flush_rewrite_rules', '1', 60);
            }

            // Redirect behavior
            if ($action === 'add') {
                // For new types, redirect to list page
                wp_redirect(admin_url('admin.php?page=listeo-listing-types'));
            } else {
                // For updates, stay on same page
                $type_id = intval($_POST['type_id']);
                wp_redirect(admin_url('admin.php?page=listeo-listing-types&action=edit&type_id=' . $type_id . '&updated=1'));
            }
            exit;
        }
    }

    /**
     * Handle tab form submission
     */
    private function handle_tab_form_submission()
    {
        if (!wp_verify_nonce($_POST['listeo_custom_type_tab_nonce'], 'listeo_custom_type_tab_form')) {
            wp_die(__('Security check failed', 'listeo_core'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'listeo_core'));
        }

        $action = sanitize_text_field($_POST['listeo_custom_type_tab_action']);
        $custom_types_manager = listeo_core_custom_listing_types();

        // Prepare data
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'plural_name' => sanitize_text_field($_POST['plural_name']),
            'slug' => sanitize_title($_POST['slug']),
            'description' => sanitize_textarea_field($_POST['description']),
            'icon_id' => isset($_POST['icon_id']) ? absint($_POST['icon_id']) : null,
            'booking_type' => isset($_POST['booking_type']) ? sanitize_text_field($_POST['booking_type']) : 'none',
            'booking_features' => isset($_POST['booking_features']) && is_array($_POST['booking_features']) ? $_POST['booking_features'] : array(),
            // Old feature support fields removed - now handled by booking_features system
            'supports_opening_hours' => isset($_POST['supports_opening_hours']) ? 1 : 0,
            'register_taxonomy' => isset($_POST['register_taxonomy']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'slug_translations' => isset($_POST['slug_translations']) && is_array($_POST['slug_translations']) ? $_POST['slug_translations'] : array()
        );

        if ($action === 'add') {
            $result = $custom_types_manager->insert_listing_type($data);
            if (!is_wp_error($result)) {
                do_action('listeo_listing_type_after_save', $data['slug'], $data, $_POST);
            }
        } else {
            // Get the current type to retrieve the slug
            global $wpdb;
            $type_id = intval($_POST['type_id']);
            $current_type = $wpdb->get_row($wpdb->prepare(
                "SELECT slug FROM {$custom_types_manager->get_table_name()} WHERE id = %d",
                $type_id
            ));

            if ($current_type) {
                unset($data['slug']); // Don't allow slug changes on edit
                $result = $custom_types_manager->update_listing_type($current_type->slug, $data);
                if (!is_wp_error($result)) {
                    do_action('listeo_listing_type_after_save', $current_type->slug, $data, $_POST);
                }
            } else {
                $result = new WP_Error('type_not_found', __('Listing type not found', 'listeo_core'));
            }
        }

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $message = $action === 'add'
                ? __('Listing type created successfully!', 'listeo_core')
                : __('Listing type updated successfully!', 'listeo_core');
            $this->add_admin_notice($message, 'success');

            // Schedule permalink flush if translations were saved
            if (!empty($data['slug_translations'])) {
                set_transient('listeo_flush_rewrite_rules', '1', 60);
            }

            // Redirect behavior
            if ($action === 'add') {
                // For new types, redirect to list page
                wp_redirect(admin_url('admin.php?page=listeo_settings&tab=listing_types'));
            } else {
                // For updates, stay on same page
                $type_id = intval($_POST['type_id']);
                wp_redirect(admin_url('admin.php?page=listeo_settings&tab=listing_types&listeo_action=edit&listeo_type_id=' . $type_id . '&updated=1'));
            }
            exit;
        }
    }

    /**
     * AJAX handler for deleting custom type
     */
    public function ajax_delete_custom_type()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_custom_types')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'listeo_core')));
        }

        $type_id = intval($_POST['type_id']);
        $custom_types_manager = listeo_core_custom_listing_types();

        // Get the type to find its slug
        global $wpdb;
        $type = $wpdb->get_row($wpdb->prepare(
            "SELECT slug FROM {$custom_types_manager->get_table_name()} WHERE id = %d",
            $type_id
        ));

        if (!$type) {
            wp_send_json_error(array('message' => __('Type not found', 'listeo_core')));
        }

        $result = $custom_types_manager->delete_listing_type($type->slug);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Type deleted successfully', 'listeo_core')));
        }
    }

    /**
     * AJAX handler for reordering listing types
     */
    public function ajax_reorder_listing_types()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'listeo_custom_types')) {
            wp_send_json_error(array('message' => __('Security check failed', 'listeo_core')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'listeo_core')));
        }

        if (!isset($_POST['type_ids']) || !is_array($_POST['type_ids'])) {
            wp_send_json_error(array('message' => __('Invalid type IDs', 'listeo_core')));
        }

        global $wpdb;
        $custom_types_table = $wpdb->prefix . 'listeo_listing_types';
        
        // Sanitize the type IDs
        $type_ids = array_map('intval', $_POST['type_ids']);
        
        // Update menu_order for each type based on the new order
        $success_count = 0;
        foreach ($type_ids as $index => $type_id) {
            $menu_order = $index + 1; // Start from 1 instead of 0
            $result = $wpdb->update(
                $custom_types_table,
                array('menu_order' => $menu_order),
                array('id' => $type_id),
                array('%d'),
                array('%d')
            );
            
            if ($result !== false) {
                $success_count++;
            }
        }

        // Clear any caches
        $custom_types_manager = listeo_core_custom_listing_types();
        if (method_exists($custom_types_manager, 'clear_cache')) {
            $custom_types_manager->clear_cache();
        }

        if ($success_count === count($type_ids)) {
            wp_send_json_success(array(
                'message' => __('Listing types reordered successfully', 'listeo_core'),
                'updated_count' => $success_count
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(__('Only %d of %d types were reordered', 'listeo_core'), $success_count, count($type_ids))
            ));
        }
    }

    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info')
    {
        add_action('admin_notices', function () use ($message, $type) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }

    /**
     * Get available languages for translations
     * Supports WPML, Polylang, and WordPress native languages
     *
     * @return array Array of language codes => language names
     */
    private function get_available_languages()
    {
        $languages = array();

        // Check for WPML
        if (function_exists('icl_get_languages')) {
            $wpml_languages = icl_get_languages('skip_missing=0');
            if (!empty($wpml_languages)) {
                foreach ($wpml_languages as $lang) {
                    $languages[$lang['code']] = $lang['native_name'];
                }
                return $languages;
            }
        }

        // Check for Polylang
        if (function_exists('pll_languages_list')) {
            $polylang_languages = pll_languages_list(array('fields' => 'name'));
            $polylang_codes = pll_languages_list(array('fields' => 'slug'));

            if (!empty($polylang_codes)) {
                foreach ($polylang_codes as $index => $code) {
                    $languages[$code] = isset($polylang_languages[$index]) ? $polylang_languages[$index] : $code;
                }
                return $languages;
            }
        }

        // Check for TranslatePress
        if (function_exists('trp_get_languages')) {
            $trp_languages = trp_get_languages();
            if (!empty($trp_languages)) {
                return $trp_languages;
            }
        }

        // Fallback to WordPress available translations
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        $translations = wp_get_available_translations();

        // Add current site language first
        $site_locale = get_locale();
        $site_language = 'English'; // Default

        if ($site_locale === 'en_US') {
            $languages['en'] = 'English (US)';
        } else {
            // Get the language name from translations
            $locale_base = substr($site_locale, 0, 2);
            if (isset($translations[$site_locale])) {
                $site_language = $translations[$site_locale]['native_name'];
            }
            $languages[$locale_base] = $site_language;
        }

        // Add other installed languages
        $installed_languages = get_available_languages();
        foreach ($installed_languages as $locale) {
            if ($locale !== $site_locale) {
                $locale_base = substr($locale, 0, 2);
                if (isset($translations[$locale])) {
                    $languages[$locale_base] = $translations[$locale]['native_name'];
                } else {
                    $languages[$locale_base] = $locale;
                }
            }
        }

        return $languages;
    }
}


// Initialize the admin interface
if (is_admin()) {
    // Initialize early so it's available when admin menu is built
    Listeo_Core_Custom_Listing_Types_Admin::instance();
}
