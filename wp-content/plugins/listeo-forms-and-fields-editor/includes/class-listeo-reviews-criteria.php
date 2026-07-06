<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Reviews_Criteria
{

    /**
     * Stores static instance of class.
     *
     * @access protected
     * @var Listeo_Submit The single instance of the class
     */
    protected static $_instance = null;

    /**
     * Returns static instance of class.
     *
     * @return self
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct($version = '1.0.0')
    {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_filter('listeo_reviews_criteria', array($this, 'add_criteria_reviews_from_option'));

        // AJAX handlers for modal
        add_action('wp_ajax_listeo_get_taxonomy_terms', array($this, 'ajax_get_taxonomy_terms'));
        add_action('wp_ajax_listeo_add_custom_criteria', array($this, 'ajax_add_custom_criteria'));
    }

    /**
     * Add menu options page
     * @since 0.1.0
     */
    public function add_options_page()
    {
        add_submenu_page(
            'listeo-fields-and-form',
            __('Reviews Criteria', 'listeo-fafe'),
            __('Reviews Criteria', 'listeo-fafe'),
            'manage_options',
            'listeo-reviews-criteria',
            array($this, 'output')
        );
    }

    /**
     * Main output method - renders sidebar and content
     */
    public function output()
    {
        // Get current tab from URL
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'global';

        // Build tabs array
        $tabs = $this->get_all_tabs();

        // Handle form submission
        if (!empty($_POST)) {
            echo $this->form_editor_save($tab);
        }

        // Handle reset/delete
        if (!empty($_GET['reset-fields']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reset')) {
            update_option('listeo_reviews_criteria_global', listeo_get_reviews_criteria());
            echo '<div class="updated"><p>' . __('The fields were successfully reset.', 'listeo-fafe') . '</p></div>';
        }

        if (!empty($_GET['delete-criteria']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete')) {
            $this->delete_criteria($tab);
            echo '<div class="updated"><p>' . __('Criteria deleted successfully.', 'listeo-fafe') . '</p></div>';
            // Refresh tabs after deletion
            $tabs = $this->get_all_tabs();
        }

        // Get fields for current tab
        $fields = $this->get_fields_for_tab($tab);

        // Render page header
        echo '<h2>Listeo Editor</h2>';

        // Render sidebar navigation and content
        $this->render_sidebar_navigation($tabs, $tab);
        $this->render_content_area($tab, $fields);

        // Render modal
        $this->render_add_criteria_modal();
    }

    /**
     * Get all tabs (global + listing types + taxonomy terms)
     */
    private function get_all_tabs()
    {
        $tabs = array(
            'global' => array(
                'section' => 'global',
                'label' => __('Global Criteria', 'listeo-fafe'),
                'description' => __('Default criteria used for all listings when no type-specific or taxonomy-specific criteria are configured. These serve as the fallback.', 'listeo-fafe')
            )
        );

        // Add listing type tabs - only show types that have been explicitly configured
        $custom_criteria = get_option('listeo_custom_reviews_criteria', array());
        if (!empty($custom_criteria['listing_types']) && class_exists('Listeo_Core_Custom_Listing_Types')) {
            $all_types = Listeo_Core_Custom_Listing_Types::instance()->get_listing_types(true);
            if (!empty($all_types)) {
                foreach ($all_types as $type) {
                    // Only show if explicitly added via "Add Criteria" modal
                    if (isset($custom_criteria['listing_types'][$type->slug])) {
                        $tabs['type_' . $type->slug] = array(
                            'section' => 'listing_types',
                            'label' => sprintf(__('%s Criteria', 'listeo-fafe'), $type->name),
                            'type_slug' => $type->slug,
                            'description' => sprintf(__('Criteria for %s listings. These override global criteria but can be overridden by taxonomy term criteria (if a listing has a category/region with custom criteria).', 'listeo-fafe'), $type->name)
                        );
                    }
                }
            }
        }

        // Add taxonomy term tabs
        $custom_criteria = get_option('listeo_custom_reviews_criteria', array());
        if (!empty($custom_criteria)) {
            foreach ($custom_criteria as $taxonomy => $terms) {
                if (!is_array($terms)) {
                    continue;
                }
                foreach ($terms as $term_id => $data) {
                    $term = get_term($term_id, $taxonomy);
                    if (!is_wp_error($term) && $term) {
                        $hierarchy_path = $this->get_term_hierarchy_path($term);
                        $tabs['term_' . $taxonomy . '_' . $term_id] = array(
                            'section' => 'taxonomy_terms',
                            'label' => sprintf(__('%s Criteria', 'listeo-fafe'), $term->name),
                            'taxonomy' => $taxonomy,
                            'term_id' => $term_id,
                            'description' => sprintf(__('Highest priority criteria for listings in: %s. These override both listing type and global criteria.', 'listeo-fafe'), $hierarchy_path)
                        );
                    }
                }
            }
        }

        return $tabs;
    }

    /**
     * Check if tabs array has a specific section
     */
    private function has_section($tabs, $section)
    {
        foreach ($tabs as $key => $data) {
            if (isset($data['section']) && $data['section'] === $section) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render sidebar navigation
     */
    private function render_sidebar_navigation($tabs, $current_tab)
    {
?>
        <div class="listeo-editor-wrap">
            <div class="nav-tab-container">
                <h2 class="nav-tab-wrapper form-builder">

                    <!-- Section 1: Global Criteria -->
                    <span class="nav-tab-subtitle"><?php esc_html_e('Global Criteria', 'listeo-fafe'); ?></span>
                    <?php
                    foreach ($tabs as $key => $data) {
                        if ($data['section'] === 'global') {
                            $active = ($current_tab === 'global') ? 'nav-tab-active' : '';
                            $url = admin_url('admin.php?page=listeo-reviews-criteria&tab=global');
                            echo '<a class="nav-tab ' . $active . '" href="' . esc_url($url) . '">';
                            echo esc_html($data['label']);
                            echo '</a>';
                        }
                    }
                    ?>

                    <!-- Section 2: Listing Type Criteria -->
                    <?php if ($this->has_section($tabs, 'listing_types')) : ?>
                        <span class="nav-tab-subtitle"><?php esc_html_e('Listing Type Criteria', 'listeo-fafe'); ?></span>
                        <?php
                        foreach ($tabs as $key => $data) {
                            if ($data['section'] === 'listing_types') {
                                $active = ($current_tab === $key) ? 'nav-tab-active' : '';
                                $url = admin_url('admin.php?page=listeo-reviews-criteria&tab=' . urlencode($key));
                                echo '<a class="nav-tab ' . $active . '" href="' . esc_url($url) . '">';
                                echo esc_html($data['label']);
                                echo '</a>';
                            }
                        }
                        ?>
                    <?php endif; ?>

                    <!-- Section 3: Taxonomy Term Criteria -->
                    <?php if ($this->has_section($tabs, 'taxonomy_terms')) : ?>
                        <span class="nav-tab-subtitle"><?php esc_html_e('Taxonomy Term Criteria', 'listeo-fafe'); ?></span>
                        <?php
                        foreach ($tabs as $key => $data) {
                            if ($data['section'] === 'taxonomy_terms') {
                                $active = ($current_tab === $key) ? 'nav-tab-active' : '';
                                $url = admin_url('admin.php?page=listeo-reviews-criteria&tab=' . urlencode($key));
                                echo '<a class="nav-tab ' . $active . '" href="' . esc_url($url) . '">';
                                echo esc_html($data['label']);
                                echo '</a>';
                            }
                        }
                        ?>
                    <?php endif; ?>

                    <!-- Add Criteria Button -->
                    <a id="add-new-reviews-criteria" class="nav-tab" style="background: #0a9;color: #fff;">
                        + <?php esc_html_e('Add Type/Term Criteria', 'listeo-fafe'); ?>
                    </a>

                </h2>
            </div>
        <?php
    }

    /**
     * Render content area
     */
    private function render_content_area($tab, $fields)
    {
        $tabs = $this->get_all_tabs();
        $current_tab_data = isset($tabs[$tab]) ? $tabs[$tab] : $tabs['global'];
        ?>
            <div class="wrap listeo-form-editor listeo-forms-builder">
                <form method="post" id="mainform" action="<?php echo esc_url(admin_url('admin.php?page=listeo-reviews-criteria&tab=' . esc_attr($tab))); ?>">

                    <h3 class="listeo-editor-form-header">
                        <?php echo esc_html($current_tab_data['label']); ?>
                        <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Settings', 'listeo-fafe'); ?>">
                    </h3>


                    <!-- Subtle Hierarchy Notice -->
                    <div class="notice notice-info inline listeo-criteria-hierarchy-notice" style="margin: 0 0 20px 0; padding: 8px 12px; background: #f8f9fa; border-left: 3px solid #72aee6;">
                        
                            <?php echo esc_html($current_tab_data['description']); ?>
                        
                    </div>

                    <!-- Criteria Table -->
                    <div class="listeo-form-editor main-options">
                        <table class="widefat fixed">
                            <thead>
                                <tr>
                                    <th width="20%"><?php esc_html_e('Criterium Title', 'listeo-fafe'); ?></th>
                                    <th><?php esc_html_e('Tooltip (optional)', 'listeo-fafe'); ?></th>
                                    <th width="20%"></th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <td colspan="3">
                                        <a class="button-primary add-new-main-option" href="#">
                                            <?php esc_html_e('Add New', 'listeo-fafe'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </tfoot>
                            <tbody data-field="<?php
                                                echo esc_attr('<tr>
									<td><input type="text" class="input-text options" name="label[-1]" /></td>
									<td><textarea name="tooltip[-1]" rows="5"></textarea></td>
									<td><a class="remove-row button" href="#">Remove</a></td>
								</tr>');
                                                ?>">
                                <?php
                                $i = 0;
                                foreach ($fields as $key => $value) { ?>
                                    <tr>
                                        <td>
                                            <input type="text" value="<?php echo stripslashes(esc_attr($value['label'])); ?>"
                                                class="input-text options" name="label[<?php echo esc_attr($i); ?>]" />
                                        </td>
                                        <td>
                                            <textarea name="tooltip[<?php echo esc_attr($i); ?>]" rows="5"><?php
                                                                                                            echo stripslashes(esc_attr($value['tooltip']));
                                                                                                            ?></textarea>
                                        </td>
                                        <td>
                                            <a class="remove-row button" href="#">
                                                <?php esc_html_e('Remove', 'listeo-fafe'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                    $i++;
                                } ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Actions -->
                    <div class="listeo-forms-editor-bottom">
                        <input type="submit" class="save-fields button-primary"
                            value="<?php esc_attr_e('Save Changes', 'listeo-fafe'); ?>" />
                        <?php if ($tab !== 'global') : ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('delete-criteria', 1), 'delete')); ?>"
                                class="reset button-secondary"
                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete these criteria? This cannot be undone.', 'listeo-fafe'); ?>');">
                                <?php esc_html_e('Delete These Criteria', 'listeo-fafe'); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('reset-fields', 1), 'reset')); ?>"
                                class="reset button-secondary"
                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset to default criteria?', 'listeo-fafe'); ?>');">
                                <?php esc_html_e('Reset to Defaults', 'listeo-fafe'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>
    <?php
    }

    /**
     * Get fields for a specific tab
     */
    private function get_fields_for_tab($tab)
    {
        if ($tab === 'global') {
            // Get global criteria
            $global = get_option('listeo_reviews_criteria_global');
            if (!empty($global) && is_array($global)) {
                return $global;
            }
            // Fallback to hardcoded defaults
            return listeo_get_reviews_criteria();
        }

        // Parse tab key for listing types
        if (strpos($tab, 'type_') === 0) {
            $type_slug = str_replace('type_', '', $tab);
            $types_criteria = get_option('listeo_reviews_criteria_types', array());

            if (isset($types_criteria[$type_slug]) && !empty($types_criteria[$type_slug])) {
                return $types_criteria[$type_slug];
            }

            // Fallback to global
            return $this->get_fields_for_tab('global');
        }

        // Parse tab key for taxonomy terms
        if (strpos($tab, 'term_') === 0) {
            $parts = explode('_', str_replace('term_', '', $tab));
            $term_id = array_pop($parts);
            $taxonomy = implode('_', $parts);

            $taxonomies_criteria = get_option('listeo_reviews_criteria_taxonomies', array());

            if (isset($taxonomies_criteria[$taxonomy][$term_id]) && !empty($taxonomies_criteria[$taxonomy][$term_id])) {
                return $taxonomies_criteria[$taxonomy][$term_id];
            }

            // Fallback to global
            return $this->get_fields_for_tab('global');
        }

        // Default fallback
        return listeo_get_reviews_criteria();
    }

    /**
     * Save form data for a specific tab
     */
    private function form_editor_save($tab)
    {
        if (!isset($_POST['label'])) {
            return '';
        }

        $field_name = !empty($_POST['label']) ? array_map('sanitize_text_field', $_POST['label']) : array();
        $field_value = !empty($_POST['tooltip']) ? array_map('sanitize_textarea_field', $_POST['tooltip']) : array();

        $new_fields = array();
        foreach ($field_name as $key => $field) {
            if (empty($field)) {
                continue;
            }
            $slug = sanitize_title($field);
            $new_fields[$slug] = array(
                'label' => $field,
                'tooltip' => isset($field_value[$key]) ? $field_value[$key] : ''
            );
        }

        // Save to appropriate option based on tab
        if ($tab === 'global') {
            update_option('listeo_reviews_criteria_global', $new_fields);
        } elseif (strpos($tab, 'type_') === 0) {
            $type_slug = str_replace('type_', '', $tab);
            $types_criteria = get_option('listeo_reviews_criteria_types', array());
            $types_criteria[$type_slug] = $new_fields;
            update_option('listeo_reviews_criteria_types', $types_criteria);
        } elseif (strpos($tab, 'term_') === 0) {
            $parts = explode('_', str_replace('term_', '', $tab));
            $term_id = array_pop($parts);
            $taxonomy = implode('_', $parts);

            $taxonomies_criteria = get_option('listeo_reviews_criteria_taxonomies', array());
            if (!isset($taxonomies_criteria[$taxonomy])) {
                $taxonomies_criteria[$taxonomy] = array();
            }
            $taxonomies_criteria[$taxonomy][$term_id] = $new_fields;
            update_option('listeo_reviews_criteria_taxonomies', $taxonomies_criteria);
        }

        // Clear cache
        wp_cache_flush_group('listeo_reviews');

        return '<div class="updated"><p>' . __('Criteria saved successfully.', 'listeo-fafe') . '</p></div>';
    }

    /**
     * Delete criteria for a specific tab
     */
    private function delete_criteria($tab)
    {
        if ($tab === 'global') {
            return; // Cannot delete global
        }

        if (strpos($tab, 'type_') === 0) {
            $type_slug = str_replace('type_', '', $tab);

            // Remove from criteria storage
            $types_criteria = get_option('listeo_reviews_criteria_types', array());
            unset($types_criteria[$type_slug]);
            update_option('listeo_reviews_criteria_types', $types_criteria);

            // Remove from tracking
            $custom_criteria = get_option('listeo_custom_reviews_criteria', array());
            if (isset($custom_criteria['listing_types'][$type_slug])) {
                unset($custom_criteria['listing_types'][$type_slug]);
                update_option('listeo_custom_reviews_criteria', $custom_criteria);
            }
        } elseif (strpos($tab, 'term_') === 0) {
            $parts = explode('_', str_replace('term_', '', $tab));
            $term_id = array_pop($parts);
            $taxonomy = implode('_', $parts);

            // Remove from criteria storage
            $taxonomies_criteria = get_option('listeo_reviews_criteria_taxonomies', array());
            if (isset($taxonomies_criteria[$taxonomy][$term_id])) {
                unset($taxonomies_criteria[$taxonomy][$term_id]);
                update_option('listeo_reviews_criteria_taxonomies', $taxonomies_criteria);
            }

            // Remove from tracking
            $custom_criteria = get_option('listeo_custom_reviews_criteria', array());
            if (isset($custom_criteria[$taxonomy][$term_id])) {
                unset($custom_criteria[$taxonomy][$term_id]);
                update_option('listeo_custom_reviews_criteria', $custom_criteria);
            }
        }

        // Clear cache
        wp_cache_flush_group('listeo_reviews');
    }

    /**
     * Get term hierarchy path for display
     */
    private function get_term_hierarchy_path($term)
    {
        $path = array();
        $current = $term;

        // Build path from term to root
        while ($current) {
            array_unshift($path, $current->name);
            if ($current->parent) {
                $current = get_term($current->parent, $current->taxonomy);
                if (is_wp_error($current)) {
                    break;
                }
            } else {
                break;
            }
        }

        // Add taxonomy name
        $taxonomy_obj = get_taxonomy($term->taxonomy);
        if ($taxonomy_obj) {
            array_unshift($path, $taxonomy_obj->labels->name);
        }

        return sprintf(__('Criteria for listings in: %s', 'listeo-fafe'), implode(' &raquo; ', $path));
    }

    /**
     * Render Add Criteria Modal
     */
    private function render_add_criteria_modal()
    {
    ?>
        <!-- Add Criteria Modal -->
        <div class="modal micromodal-slide" id="listeo-add-criteria-modal" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="listeo-add-criteria-title">
                    <header class="modal__header">
                        <h2 class="modal__title" id="listeo-add-criteria-title">
                            <?php esc_html_e('Add Type/Term Criteria', 'listeo-fafe'); ?>
                        </h2>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content" id="listeo-add-criteria-content">

                        <!-- Hierarchy Explanation -->
                        <div class="listeo-criteria-hierarchy-info" style="background: #f0f6fc; border-left: 4px solid #0a9; padding: 12px 15px; margin-bottom: 20px; font-size: 13px; line-height: 1.6;">
                            <strong style="display: block; margin-bottom: 8px; color: #0a9;">
                                <?php esc_html_e('Criteria Priority Order:', 'listeo-fafe'); ?>
                            </strong>
                            <ol style="margin: 0 0 0 20px; padding: 0;">
                                <li><strong><?php esc_html_e('Taxonomy Term Criteria', 'listeo-fafe'); ?></strong> - <?php esc_html_e('Highest priority. If a listing has a category/region/term with custom criteria, these will be used.', 'listeo-fafe'); ?></li>
                                <li><strong><?php esc_html_e('Listing Type Criteria', 'listeo-fafe'); ?></strong> - <?php esc_html_e('Used if no taxonomy term criteria exists for the listing.', 'listeo-fafe'); ?></li>
                                <li><strong><?php esc_html_e('Global Criteria', 'listeo-fafe'); ?></strong> - <?php esc_html_e('Default fallback used when no specific criteria are configured.', 'listeo-fafe'); ?></li>
                            </ol>
                            <p style="margin: 10px 0 0 0; color: #666; font-style: italic;">
                                <?php esc_html_e('Example: If you create criteria for "Restaurants" category, all restaurant listings will use those criteria regardless of their listing type.', 'listeo-fafe'); ?>
                            </p>
                        </div>

                        <form id="listeo-add-criteria-form">

                            <!-- Step 1: Choose Type -->
                            <div class="form-field" id="criteria-type-field">
                                <label for="listeo-criteria-type">
                                    <?php esc_html_e('Add criteria for:', 'listeo-fafe'); ?>
                                </label>
                                <select id="listeo-criteria-type" name="criteria_type" required>
                                    <option value=""><?php esc_html_e('-- Select --', 'listeo-fafe'); ?></option>
                                    <option value="listing_type"><?php esc_html_e('Listing Type', 'listeo-fafe'); ?></option>
                                    <option value="taxonomy_term"><?php esc_html_e('Taxonomy Term', 'listeo-fafe'); ?></option>
                                </select>
                            </div>

                            <!-- Step 2a: Listing Type Selection -->
                            <div class="form-field" id="listing-type-field" style="display:none;">
                                <label for="listeo-listing-type-select">
                                    <?php esc_html_e('Select Listing Type:', 'listeo-fafe'); ?>
                                </label>
                                <select id="listeo-listing-type-select" name="listing_type">
                                    <option value=""><?php esc_html_e('-- Select Type --', 'listeo-fafe'); ?></option>
                                    <?php
                                    if (class_exists('Listeo_Core_Custom_Listing_Types')) {
                                        $types = Listeo_Core_Custom_Listing_Types::instance()->get_listing_types(true);
                                        foreach ($types as $type) {
                                            echo '<option value="' . esc_attr($type->slug) . '">';
                                            echo esc_html($type->name);
                                            echo '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Step 2b: Taxonomy Selection -->
                            <div class="form-field" id="taxonomy-field" style="display:none;">
                                <label for="listeo-taxonomy-select">
                                    <?php esc_html_e('Select Taxonomy:', 'listeo-fafe'); ?>
                                </label>
                                <select id="listeo-taxonomy-select" name="taxonomy">
                                    <option value=""><?php esc_html_e('-- Select Taxonomy --', 'listeo-fafe'); ?></option>
                                    <option value="listing_category"><?php esc_html_e('Listing Category', 'listeo-fafe'); ?></option>
                                    <option value="region"><?php esc_html_e('Region', 'listeo-fafe'); ?></option>
                                    <option value="listing_feature"><?php esc_html_e('Features', 'listeo-fafe'); ?></option>
                                </select>
                            </div>

                            <!-- Step 3: Term Selection -->
                            <div class="form-field" id="term-field" style="display:none;">
                                <label for="listeo-term-select">
                                    <?php esc_html_e('Select Term:', 'listeo-fafe'); ?>
                                </label>
                                <select id="listeo-term-select" name="term_id">
                                    <option value=""><?php esc_html_e('-- First select taxonomy --', 'listeo-fafe'); ?></option>
                                </select>
                            </div>

                            <input type="submit" class="button button-primary"
                                value="<?php esc_attr_e('Create Criteria', 'listeo-fafe'); ?>">
                        </form>
                    </main>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * AJAX: Get taxonomy terms
     */
    public function ajax_get_taxonomy_terms()
    {
        check_ajax_referer('listeo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'listeo-fafe'));
        }

        $taxonomy = sanitize_text_field($_POST['taxonomy']);

        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error(__('Invalid taxonomy', 'listeo-fafe'));
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            wp_send_json_error(__('Could not load terms', 'listeo-fafe'));
        }

        $result = array();
        foreach ($terms as $term) {
            $result[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
            );
        }

        wp_send_json_success(array('terms' => $result));
    }

    /**
     * AJAX: Add custom criteria (type or taxonomy)
     */
    public function ajax_add_custom_criteria()
    {
        check_ajax_referer('listeo_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'listeo-fafe'));
        }

        $criteria_type = sanitize_text_field($_POST['criteria_type']);

        if ($criteria_type === 'listing_type') {
            $type_slug = sanitize_text_field($_POST['listing_type']);

            if (empty($type_slug)) {
                wp_send_json_error(__('Please select a listing type', 'listeo-fafe'));
            }

            // Track this listing type as having custom criteria
            $custom_criteria = get_option('listeo_custom_reviews_criteria', array());
            if (!isset($custom_criteria['listing_types'])) {
                $custom_criteria['listing_types'] = array();
            }
            $custom_criteria['listing_types'][$type_slug] = true;
            update_option('listeo_custom_reviews_criteria', $custom_criteria);

            // Initialize type criteria if not exists
            $types_criteria = get_option('listeo_reviews_criteria_types', array());
            if (!isset($types_criteria[$type_slug])) {
                $types_criteria[$type_slug] = listeo_get_reviews_criteria(); // Start with global
                update_option('listeo_reviews_criteria_types', $types_criteria);
            }

            // Redirect URL
            $redirect_url = admin_url('admin.php?page=listeo-reviews-criteria&tab=type_' . $type_slug);
        } elseif ($criteria_type === 'taxonomy_term') {
            $taxonomy = sanitize_text_field($_POST['taxonomy']);
            $term_id = absint($_POST['term_id']);

            if (empty($taxonomy) || empty($term_id)) {
                wp_send_json_error(__('Please select both taxonomy and term', 'listeo-fafe'));
            }

            // Save to custom criteria tracking
            $custom_criteria = get_option('listeo_custom_reviews_criteria', array());
            if (!isset($custom_criteria[$taxonomy])) {
                $custom_criteria[$taxonomy] = array();
            }
            $custom_criteria[$taxonomy][$term_id] = true; // Track that this exists
            update_option('listeo_custom_reviews_criteria', $custom_criteria);

            // Initialize taxonomy criteria if not exists
            $taxonomies_criteria = get_option('listeo_reviews_criteria_taxonomies', array());
            if (!isset($taxonomies_criteria[$taxonomy])) {
                $taxonomies_criteria[$taxonomy] = array();
            }
            if (!isset($taxonomies_criteria[$taxonomy][$term_id])) {
                $taxonomies_criteria[$taxonomy][$term_id] = listeo_get_reviews_criteria(); // Start with global
                update_option('listeo_reviews_criteria_taxonomies', $taxonomies_criteria);
            }

            // Redirect URL
            $redirect_url = admin_url('admin.php?page=listeo-reviews-criteria&tab=term_' . $taxonomy . '_' . $term_id);
        } else {
            wp_send_json_error(__('Invalid criteria type', 'listeo-fafe'));
        }

        wp_send_json_success(array(
            'message' => __('Criteria created successfully!', 'listeo-fafe'),
            'redirect' => $redirect_url
        ));
    }

    /**
     * Filter to add criteria from options
     */
    function add_criteria_reviews_from_option($r)
    {
        $reviews_criteria = get_option('listeo_reviews_criteria_fields');
        if (!empty($reviews_criteria)) {
            $r = array();
            foreach ($reviews_criteria as $key => $value) {
                $r[$key] = array(
                    'label' => $value['label'],
                    'tooltip' => $value['tooltip'],
                );
            }
        }

        return $r;
    }
}
