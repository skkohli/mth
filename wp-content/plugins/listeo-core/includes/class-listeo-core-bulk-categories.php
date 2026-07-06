<?php
/**
 * Listeo Core Bulk Categories
 *
 * Bulk add categories to Listeo using a textarea (one category per line)
 * Supports hierarchy with dash prefix and FontAwesome icons
 *
 * @package Listeo Core
 * @since 1.9.50
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Safety check: Don't load if standalone bulk categories plugin is active
if (class_exists('Listeo_Bulk_Categories')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>Listeo Core:</strong> <?php _e('Conflict detected! Please deactivate the standalone "Listeo Bulk Categories" plugin as this functionality is now integrated into Listeo Core.', 'listeo_core'); ?></p>
        </div>
        <?php
    });
    return;
}

/**
 * Listeo Core Bulk Categories Class
 */
class Listeo_Core_Bulk_Categories {

    private static $_instance = null;

    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        if (!is_admin()) {
            return;
        }

        add_action('admin_init', array($this, 'handle_form_submission'));
    }

    /**
     * Get all available listing category taxonomies
     */
    private function get_category_taxonomies() {
        $taxonomies = array();

        // Always include global listing_category
        $taxonomies['listing_category'] = __('Global Categories (All Listing Types)', 'listeo_core');

        // Get type-specific taxonomies
        $type_taxonomies = array(
            'service_category' => __('Service Categories', 'listeo_core'),
            'event_category'   => __('Event Categories', 'listeo_core'),
            'rental_category'  => __('Rental Categories', 'listeo_core'),
        );

        foreach ($type_taxonomies as $tax => $label) {
            if (taxonomy_exists($tax)) {
                $taxonomies[$tax] = $label;
            }
        }

        // Get custom listing type taxonomies
        if (class_exists('Listeo_Core_Custom_Listing_Types')) {
            $types_manager = Listeo_Core_Custom_Listing_Types::instance();
            $custom_types = $types_manager->get_listing_types(array('is_active' => 1));

            foreach ($custom_types as $type) {
                $register_taxonomy = is_object($type) ? $type->register_taxonomy : ($type['register_taxonomy'] ?? 0);
                $slug = is_object($type) ? $type->slug : ($type['slug'] ?? '');
                $name = is_object($type) ? $type->name : ($type['name'] ?? '');

                if (!empty($register_taxonomy) && $register_taxonomy == 1) {
                    $tax_name = $slug . '_category';
                    if (taxonomy_exists($tax_name) && !isset($taxonomies[$tax_name])) {
                        $taxonomies[$tax_name] = sprintf(
                            __('%s Categories', 'listeo_core'),
                            $name
                        );
                    }
                }
            }
        }

        return $taxonomies;
    }

    /**
     * Get existing terms for a taxonomy (for parent dropdown)
     */
    private function get_parent_terms($taxonomy) {
        $terms = get_terms(array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        return $terms;
    }

    /**
     * Build hierarchical term options
     */
    private function build_term_options($terms, $parent_id = 0, $depth = 0) {
        $options = array();

        foreach ($terms as $term) {
            if ($term->parent == $parent_id) {
                $options[] = array(
                    'id'    => $term->term_id,
                    'name'  => str_repeat('— ', $depth) . $term->name,
                    'depth' => $depth,
                );
                $options = array_merge($options, $this->build_term_options($terms, $term->term_id, $depth + 1));
            }
        }

        return $options;
    }

    /**
     * AJAX handler for getting taxonomy terms
     */
    public static function ajax_get_taxonomy_terms() {
        check_ajax_referer('listeo_bulk_categories_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';

        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error('Invalid taxonomy');
        }

        $instance = self::instance();
        $terms = $instance->get_parent_terms($taxonomy);
        $options = $instance->build_term_options($terms);

        wp_send_json_success($options);
    }

    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['listeo_bulk_categories_submit'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'listeo_core'));
        }

        check_admin_referer('listeo_bulk_categories_action', 'listeo_bulk_categories_nonce');

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'listing_category';
        $parent_id = isset($_POST['parent_term']) ? absint($_POST['parent_term']) : 0;
        $categories_text = isset($_POST['categories_textarea']) ? sanitize_textarea_field($_POST['categories_textarea']) : '';

        if (empty($categories_text)) {
            add_settings_error(
                'listeo_bulk_categories',
                'empty_categories',
                __('Please enter at least one category name.', 'listeo_core'),
                'error'
            );
            return;
        }

        if (!taxonomy_exists($taxonomy)) {
            add_settings_error(
                'listeo_bulk_categories',
                'invalid_taxonomy',
                __('Selected taxonomy does not exist.', 'listeo_core'),
                'error'
            );
            return;
        }

        // Parse categories (one per line)
        $lines = explode("\n", $categories_text);
        $created = array();
        $skipped = array();
        $errors = array();

        // Track parent IDs at each depth level
        $parent_stack = array(0 => $parent_id);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Count leading dashes to determine depth
            $depth = 0;
            while (isset($line[$depth]) && $line[$depth] === '-') {
                $depth++;
            }

            // Remove leading dashes and trim
            $line = trim(substr($line, $depth));

            if (empty($line)) {
                continue;
            }

            // Parse line for category name and optional icon
            $parts = explode('|', $line, 2);
            $category_name = trim($parts[0]);
            $icon = isset($parts[1]) ? trim($parts[1]) : '';

            if (empty($category_name)) {
                continue;
            }

            // Determine parent for this category
            $current_parent = $parent_id;
            if ($depth > 0) {
                for ($i = $depth - 1; $i >= 0; $i--) {
                    if (isset($parent_stack[$i])) {
                        $current_parent = $parent_stack[$i];
                        break;
                    }
                }
            }

            // Check if term already exists under this parent
            $existing = term_exists($category_name, $taxonomy, $current_parent);

            if ($existing) {
                $parent_stack[$depth] = is_array($existing) ? $existing['term_id'] : $existing;
                $skipped[] = str_repeat('— ', $depth) . $category_name;
                continue;
            }

            // Create the term
            $result = wp_insert_term(
                $category_name,
                $taxonomy,
                array('parent' => $current_parent)
            );

            if (is_wp_error($result)) {
                $errors[] = str_repeat('— ', $depth) . $category_name . ' (' . $result->get_error_message() . ')';
            } else {
                $term_id = $result['term_id'];
                $parent_stack[$depth] = $term_id;

                // Clear deeper levels from stack
                foreach (array_keys($parent_stack) as $key) {
                    if ($key > $depth) {
                        unset($parent_stack[$key]);
                    }
                }

                // Save icon if provided
                if (!empty($icon)) {
                    update_term_meta($term_id, 'icon', sanitize_text_field($icon));
                    $created[] = str_repeat('— ', $depth) . $category_name . ' (' . $icon . ')';
                } else {
                    $created[] = str_repeat('— ', $depth) . $category_name;
                }
            }
        }

        // Store results for display
        set_transient('listeo_bulk_categories_results', array(
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ), 60);

        // Redirect to prevent form resubmission
        wp_redirect(add_query_arg('results', '1', admin_url('edit.php?post_type=listing&page=listeo-bulk-categories')));
        exit;
    }

    /**
     * Render the admin page
     */
    public function render_import_page() {
        $taxonomies = $this->get_category_taxonomies();
        $default_taxonomy = 'listing_category';
        $default_terms = $this->get_parent_terms($default_taxonomy);
        $parent_options = $this->build_term_options($default_terms);

        // Get results from transient
        $results = get_transient('listeo_bulk_categories_results');
        if ($results) {
            delete_transient('listeo_bulk_categories_results');
        }

        // Enqueue styles
        wp_enqueue_style(
            'listeo-admin-categories',
            plugins_url('listeo-core/assets/css/admin-categories.css', dirname(dirname(__FILE__))),
            array(),
            '1.0.0'
        );

        ?>
        <style>
            .listeo-bulk-categories-wrap {
                max-width: 800px;
            }
            .listeo-bulk-categories-wrap textarea {
                width: 100%;
                font-family: monospace;
                min-height: 300px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
            }
            .listeo-bulk-categories-wrap .form-row {
                margin-bottom: 24px;
            }
            .listeo-bulk-categories-wrap .form-row label {
                display: block;
                font-weight: 500;
                margin-bottom: 8px;
                color: #1a1a1a;
            }
            .listeo-bulk-categories-wrap .form-row select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 14px;
            }
            .listeo-bulk-categories-wrap .form-row .description {
                color: #6b7280;
                font-size: 13px;
                margin-top: 6px;
            }
            .listeo-bulk-categories-wrap .form-row .description code {
                background: #f3f4f6;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 12px;
            }
            .listeo-bulk-categories-wrap .results-box {
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
            }
            .listeo-bulk-categories-wrap .results-box.success {
                background: #ecfdf5;
                border: 1px solid #10b981;
            }
            .listeo-bulk-categories-wrap .results-box.error {
                background: #fef2f2;
                border: 1px solid #ef4444;
            }
            .listeo-bulk-categories-wrap .results-box ul {
                margin: 10px 0 0 20px;
                list-style: disc;
            }
        </style>

        <div class="wrap listeo-categories-wrap listeo-bulk-categories-wrap">
            <h1><?php _e('Bulk Add Categories', 'listeo_core'); ?></h1>
            <p class="subtitle"><?php _e('Quickly add multiple categories at once using a simple text format.', 'listeo_core'); ?></p>

            <?php settings_errors('listeo_bulk_categories'); ?>

            <?php if ($results) : ?>
                <div class="results-box <?php echo !empty($results['created']) ? 'success' : 'error'; ?>">
                    <?php if (!empty($results['created'])) : ?>
                        <strong><?php printf(__('%d categories created successfully:', 'listeo_core'), count($results['created'])); ?></strong>
                        <ul>
                            <?php foreach ($results['created'] as $name) : ?>
                                <li><?php echo esc_html($name); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($results['skipped'])) : ?>
                        <strong><?php printf(__('%d categories skipped (already exist):', 'listeo_core'), count($results['skipped'])); ?></strong>
                        <ul>
                            <?php foreach ($results['skipped'] as $name) : ?>
                                <li><?php echo esc_html($name); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($results['errors'])) : ?>
                        <strong style="color: #ef4444;"><?php printf(__('%d errors:', 'listeo_core'), count($results['errors'])); ?></strong>
                        <ul>
                            <?php foreach ($results['errors'] as $error) : ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="listeo-section-description">
                <strong><?php _e('Format:', 'listeo_core'); ?></strong>
                <?php _e('Enter one category per line. Use dashes (-) for subcategories and pipe (|) for icons:', 'listeo_core'); ?>
                <code>Category|fas fa-icon</code>, <code>-Subcategory|far fa-icon</code>, <code>--Sub-subcategory</code>
                <br>
                <?php _e('Icon prefixes: fas (solid), far (regular), fab (brands)', 'listeo_core'); ?>
                <br><br>
                <strong><?php _e('Tip:', 'listeo_core'); ?></strong>
                <?php _e('Use', 'listeo_core'); ?> <strong><?php _e('ChatGPT, Claude, or any AI', 'listeo_core'); ?></strong> <?php _e('to generate categories. Copy this prompt and replace [YOUR BUSINESS TYPE]:', 'listeo_core'); ?>
                <br><br>
                <textarea id="ai-prompt-template" rows="6" style="width:100%; font-size: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; resize: none; min-height: 100px; max-height: 100px; overflow-y: auto;">Generate a list of categories and subcategories for a [YOUR BUSINESS TYPE] directory website. Use this exact format:
- One category per line (plain text, no bullet points or markdown)
- Add a line break after each category and subcategory
- Use dash prefix for hierarchy: no dash = main category, - = subcategory, -- = sub-subcategory
- Add FontAwesome 6 icon after pipe: Category Name|fas fa-icon-name
- Use fas (solid), far (regular), or fab (brands) prefix
- If I provide a list of categories, only format them - do not add new categories or change hierarchy levels unless I ask

Example output:
Category Name|fas fa-icon
-Subcategory|fas fa-icon
--3rd Level Category|fas fa-icon</textarea>
                <button type="button" class="button button-small" id="copy-prompt-btn" style="margin-top: 8px;">
                    <?php _e('Copy Prompt', 'listeo_core'); ?>
                </button>
                <script>
                document.getElementById('copy-prompt-btn').addEventListener('click', function() {
                    var textarea = document.getElementById('ai-prompt-template');
                    var btn = this;
                    textarea.removeAttribute('readonly');
                    textarea.select();
                    textarea.setSelectionRange(0, 99999);
                    try {
                        document.execCommand('copy');
                        btn.textContent = '<?php echo esc_js(__('Copied!', 'listeo_core')); ?>';
                    } catch (err) {
                        btn.textContent = '<?php echo esc_js(__('Select All & Copy manually', 'listeo_core')); ?>';
                    }
                    textarea.setAttribute('readonly', 'readonly');
                    setTimeout(function() {
                        btn.textContent = '<?php echo esc_js(__('Copy Prompt', 'listeo_core')); ?>';
                    }, 2000);
                });
                </script>
            </div>

            <div class="listeo-global-card">
                <form method="post" action="" style="width: 100%;">
                    <?php wp_nonce_field('listeo_bulk_categories_action', 'listeo_bulk_categories_nonce'); ?>

                    <div class="form-row">
                        <label for="taxonomy_select"><?php _e('Category Type', 'listeo_core'); ?></label>
                        <select name="taxonomy" id="taxonomy_select">
                            <?php foreach ($taxonomies as $tax => $label) : ?>
                                <option value="<?php echo esc_attr($tax); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select which category type to add categories to.', 'listeo_core'); ?></p>
                    </div>

                    <div class="form-row">
                        <label for="parent_term"><?php _e('Parent Category', 'listeo_core'); ?></label>
                        <select name="parent_term" id="parent_term">
                            <option value="0"><?php _e('None (Top Level)', 'listeo_core'); ?></option>
                            <?php foreach ($parent_options as $option) : ?>
                                <option value="<?php echo esc_attr($option['id']); ?>"><?php echo esc_html($option['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Optionally select a parent category. New categories will be added as children.', 'listeo_core'); ?></p>
                    </div>

                    <div class="form-row">
                        <label for="categories_textarea"><?php _e('Categories', 'listeo_core'); ?></label>
                        <textarea name="categories_textarea" id="categories_textarea" placeholder="<?php esc_attr_e("Restaurant|fas fa-utensils\n-Italian|fas fa-pizza-slice\n-Chinese|fas fa-bowl-rice\n--Szechuan\n--Cantonese\n-Mexican|fas fa-pepper-hot\nHotel|fas fa-bed\n-Luxury|fas fa-star\n-Budget|fas fa-dollar-sign\nSpa|fas fa-spa", 'listeo_core'); ?>"></textarea>
                        <p class="description">
                            <?php _e('Use dashes for hierarchy: no dash = top level, - = subcategory, -- = sub-subcategory, etc.', 'listeo_core'); ?>
                        </p>
                    </div>

                    <button type="submit" name="listeo_bulk_categories_submit" class="listeo-manage-btn">
                        <?php _e('Add Categories', 'listeo_core'); ?>
                    </button>
                </form>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $("#taxonomy_select").on("change", function() {
                    var taxonomy = $(this).val();
                    var $parentSelect = $("#parent_term");

                    $parentSelect.prop("disabled", true).html("<option value=\"0\"><?php echo esc_js(__('Loading...', 'listeo_core')); ?></option>");

                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "listeo_bulk_categories_get_terms",
                            taxonomy: taxonomy,
                            nonce: "<?php echo wp_create_nonce('listeo_bulk_categories_nonce'); ?>"
                        },
                        success: function(response) {
                            if (response.success) {
                                var options = "<option value=\"0\"><?php echo esc_js(__('None (Top Level)', 'listeo_core')); ?></option>";
                                $.each(response.data, function(i, term) {
                                    options += "<option value=\"" + term.id + "\">" + term.name + "</option>";
                                });
                                $parentSelect.html(options);
                            }
                            $parentSelect.prop("disabled", false);
                        }
                    });
                });
            });
        </script>
        <?php
    }
}

// Define constant to indicate integrated version is loaded
if (!defined('LISTEO_BULK_CATEGORIES_INTEGRATED')) {
    define('LISTEO_BULK_CATEGORIES_INTEGRATED', true);
}

// Register AJAX handler
add_action('wp_ajax_listeo_bulk_categories_get_terms', array('Listeo_Core_Bulk_Categories', 'ajax_get_taxonomy_terms'));
