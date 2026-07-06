<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Booking_Fields_Editor
{

    /**
     * Stores static instance of class.
     *
     * @access protected
     * @var  The single instance of the class
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

        add_action('admin_menu', array($this, 'add_options_page')); //create tab pages

        // add_filter('listeo_user_owner_fields', array( $this,'add_listeo_owner_fields')); 
        // add_filter('listeo_user_guest_fields', array( $this,'add_listeo_guest_fields')); 

    }



    // function add_listeo_owner_fields($fields) {
    //     $new_fields =  get_option('listeo_owner_fields');
    //     if(!empty($new_fields)) { $fields = $new_fields; } return $fields;
    // }      

    // function add_listeo_guest_fields($fields) {
    //     $new_fields =  get_option('listeo_guest_fields');
    //     if(!empty($new_fields)) { $fields = $new_fields; } return $fields;
    // }    



    /**
     * Add menu options page
     * @since 0.1.0
     */
    public function add_options_page()
    {
        add_submenu_page('listeo-fields-and-form', 'Booking Fields', 'Booking Fields', 'manage_options', 'listeo-booking-fields-builder', array($this, 'output'));
    }
    public function output()
    {

        // Get dynamic tabs including custom listing types
        $tabs = $this->get_booking_tabs();
        
        // Default to first available tab if no tab specified
        $default_tab = !empty($tabs) ? array_key_first($tabs) : 'service';
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $default_tab;

        if (!empty($_GET['reset-fields']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reset')) {
            delete_option("listeo_{$tab}_booking_fields");
            echo '<div class="updated"><p>' . __('The fields were successfully reset.', 'listeo') . '</p></div>';
        }

        if (!empty($_POST)) {
            // Verify nonce for security
            // if (!wp_verify_nonce($_POST['_wpnonce'], 'save-' . $tab)) {
            //     wp_die(__('Security check failed. Please try again.', 'listeo-fafe'));
            // }
            
            echo $this->form_editor_save($tab);
        }


        $field_types = apply_filters(
            'listeo_form_field_types',
            array(
                'text'              => __('Text', 'listeo-editor'),
                'wp-editor'         => __('Textarea', 'listeo-editor'),
                'radio'             => __('Radio', 'listeo-editor'),
                'select'            => __('Select', 'listeo-editor'),
                'select_multiple'   => __('Multi Select', 'listeo-editor'),
                'checkbox'          => __('Checkbox', 'listeo-editor'),
                'multicheck_split'  => __('Multi Checkbox', 'listeo-editor'),
                'file'              => __('File upload', 'listeo-editor'),
                'header'            => __('Header', 'listeo-editor'),

            )
        );


        $default_fields = array();
        $options = get_option("listeo_{$tab}_booking_fields");

        $fields = (!empty($options)) ? get_option("listeo_{$tab}_booking_fields") : $default_fields;

        if (isset($fields['fields'])) {
            $fields = $fields['fields'];
        }

?>
        <h2>Booking Fields Editor</h2>
        <div class="listeo-editor-wrap">

            <div class="nav-tab-container">
                <h2 class=" nav-tab-wrapper form-builder">
                    <?php
                    foreach ($tabs as $key => $value) {

                        $active = ($key == $tab) ? 'nav-tab-active' : '';
                        echo '<a class="nav-tab ' . $active . '" href="' . admin_url('admin.php?page=listeo-booking-fields-builder&tab=' . esc_attr($key)) . '">' . esc_html($value) . '</a>';
                    }
                    ?>
                </h2>
            </div>
            <div class="wrap listeo-form-editor listeo-booking-fields-builder listeo-forms-builder">

                <form method="post" id="mainform" action="admin.php?page=listeo-booking-fields-builder&amp;tab=<?php echo esc_attr($tab); ?>">
                    <h3 class="listeo-editor-form-header">
                        <?php
                        foreach ($tabs as $key => $value) {
                            if ($active = ($key == $tab)) {
                                echo esc_html__($value);
                            }
                        } ?>
                        <input name="Submit" type="submit" class="button-primary" value="Save Settings">
                    </h3>
                    <div class="listeo-forms-builder-top">
                        <div class="form-editor-container" id="listeo-fafe-fields-editor" data-clone="<?php
                                                                                                        ob_start();
                                                                                                        $index = -2;
                                                                                                        $field_key = 'clone';
                                                                                                        $field = array(
                                                                                                            'name' => 'clone',
                                                                                                            'id' => '_clone',
                                                                                                            'type' => 'text',
                                                                                                            'invert' => '',
                                                                                                            'desc' => '',
                                                                                                            'options_source' => '',
                                                                                                            'options_cb' => '',
                                                                                                            'options' => array()
                                                                                                        ); ?>
                <div class=" form_item" data-priority="<?php echo  $index; ?>">
                            <span class="handle dashicons dashicons-editor-justify"></span>
                            <div class="element_title"><?php echo esc_attr($field['name']);  ?> <span>(<?php echo $field['type']; ?>)</span> </div>
                            <?php include(plugin_dir_path(__DIR__) . 'views/form-field-edit.php'); ?>
                            <div class="remove_item"> <span class="dashicons dashicons-remove"></span> </div>
                        </div>
                        <?php echo esc_attr(ob_get_clean()); ?>">

                        <?php
                        $index = 0;
                        $fields = maybe_unserialize($fields);
                        foreach ($fields as $field_key => $field) {
                            $index++;

                            if (is_array($field)) { ?>
                                <div class="form_item">
                                    <span class="handle dashicons dashicons-editor-justify"></span>
                                    <div class="element_title"><?php echo esc_attr((isset($field['name'])) ? $field['name'] : $field['label']); ?>
                                        <div class="element_title_edit"><span class="dashicons dashicons-edit"></span> Edit</div>
                                    </div>
                                    <?php include(plugin_dir_path(__DIR__) . 'views/form-field-edit.php'); ?>
                                    <div class="remove_item"> <span class="dashicons dashicons-remove"></span> </div>
                                </div>
                        <?php }
                        }  ?>
                        <div class="droppable-helper"></div>
                    </div>
                    <a class="add_new_item button-primary add-field" href="#"><?php _e('Add field', 'listeo'); ?></a>
            </div>

            <?php wp_nonce_field('save-' . $tab); ?>

            <div class="listeo-forms-builder-bottom">

                <input type="submit" class="save-fields button-primary" value="<?php _e('Save Changes', 'listeo'); ?>" />
                <a href="<?php echo wp_nonce_url(add_query_arg('reset-fields', 1), 'reset'); ?>" class="reset button-secondary"><?php _e('Reset to defaults', 'listeo'); ?></a>
            </div>
            </form>
        </div>
        </div>
        <div class="modal micromodal-slide" id="listeo-add-field-modal" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="add-field-title">
                    <header class="modal__header">
                        <h2 class="modal__title" id="add-field-title">Add New Field</h2>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content">
                        <form id="listeo-add-field-form">
                            <label for="new-field-name">Field name</label>
                            <input type="text" id="new-field-name" name="new-field-name" required minlength="2" style="width: 100%; padding: 6px;">
                            <div style="margin-top: 1rem;">
                                <button type="submit" class="button button-primary">Add Field</button>
                            </div>
                        </form>
                    </main>
                </div>
            </div>
        </div>
<?php
    }



    private function form_editor_save($tab)
    {

        $field_name             = !empty($_POST['name']) ? array_map('sanitize_text_field', $_POST['name'])                     : array();
        $field_id               = !empty($_POST['id']) ? array_map('sanitize_text_field', $_POST['id'])                         : array();
        $field_type             = !empty($_POST['type']) ? array_map('sanitize_text_field', $_POST['type'])                     : array();
        $field_required         = !empty($_POST['required']) ? array_map('sanitize_text_field', $_POST['required'])             : array();
        $field_width            = !empty($_POST['width']) ? array_map('sanitize_text_field', $_POST['width'])                   : array();
        $field_icon             = !empty($_POST['icon']) ? array_map('sanitize_text_field', $_POST['icon'])                     : array();
        $field_css              = !empty($_POST['css']) ? array_map('sanitize_text_field', $_POST['css'])                     : array();
        $field_desc             = !empty($_POST['desc']) ? array_map('sanitize_text_field', $_POST['desc'])                    : array();
        $field_placeholder      = !empty($_POST['placeholder']) ? array_map('sanitize_text_field', $_POST['placeholder'])                    : array();
        $field_options_cb       = !empty($_POST['options_cb']) ? array_map('sanitize_text_field', $_POST['options_cb'])        : array();
        $field_options_source   = !empty($_POST['options_source']) ? array_map('sanitize_text_field', $_POST['options_source']) : array();
        $field_options          = !empty($_POST['options']) ? $this->sanitize_array($_POST['options'])                : array();
        $new_fields             = array();
        $index                  = 0;

        foreach ($field_name as $key => $field) {

            if (empty($field_name[$key])) {
                continue;
            }
            $name            = sanitize_title($field_id[$key]);
            $options        = array();
            if (!empty($field_options[$key])) {
                foreach ($field_options[$key] as $op_key => $op_value) {
                    $options[stripslashes($op_value['name'])] = stripslashes($op_value['value']);
                }
            }

            $new_field                      = array();
            $new_field['name']              = stripslashes($field_name[$key]);
            $new_field['label']             = $field_name[$key];
            $new_field['id']                = $field_id[$key];
            $new_field['type']              = $field_type[$key];
            $new_field['required']          = $field_required[$key];
            $new_field['width']              = $field_width[$key];
            $new_field['icon']              = isset($field_icon[$key]) ? $field_icon[$key] : false;
            $new_field['css']              = isset($field_css[$key]) ? $field_css[$key] : false;
            $new_field['placeholder']       = isset($field_placeholder[$key]) ? $field_placeholder[$key] : false;
            $new_field['desc']               = isset($field_desc[$key]) ? stripslashes($field_desc[$key]) : false;
            $new_field['options_source']    = isset($field_options_source[$key]) ? $field_options_source[$key] : false;
            $new_field['options_cb']        = isset($field_options_cb[$key]) ? $field_options_cb[$key] : false;

            if (!empty($field_options_cb[$key])) {
                $new_field['options']           = array();
            } else {
                $new_field['options']           = $options;
            }

            $new_fields[$name]       = $new_field;
        }

        $result = update_option("listeo_{$tab}_booking_fields", $new_fields);

        if (true === $result) {
            echo '<div class="updated"><p>' . __('The fields were successfully saved.', 'listeo-editor') . '</p></div>';
        }
    }

    /**
     * Sanitize a 2d array
     * @param  array $array
     * @return array
     */
    private function sanitize_array($input)
    {
        if (is_array($input)) {
            foreach ($input as $k => $v) {
                $input[$k] = $this->sanitize_array($v);
            }
            return $input;
        } else {
            return sanitize_text_field($input);
        }
    }

    /**
     * Get booking field tabs for all listing types (default and custom)
     */
    private function get_booking_tabs()
    {
        $tabs = array();
        
        // Get all listing types (custom and default)
        if (function_exists('listeo_core_custom_listing_types')) {
            $custom_types_manager = listeo_core_custom_listing_types();
            $listing_types = $custom_types_manager->get_listing_types(false, true);
            
            foreach ($listing_types as $type) {
                if ($type->is_active) {
                    // Only include types that support booking
                    if ($type->booking_type && $type->booking_type !== 'none') {
                        $tab_label = sprintf(__('%s Booking Fields', 'listeo-fafe'), $type->name);
                        $tabs[$type->slug] = $tab_label;
                    }
                }
            }
        } else {
            // Fallback to hardcoded default types if custom types not available
            $tabs = array(
                'service' => __('Service Booking Fields', 'listeo-fafe'),
                'rental'  => __('Rental Booking Fields', 'listeo-fafe'), 
                'event'   => __('Event Booking Fields', 'listeo-fafe'),
            );
        }
        
        return $tabs;
    }
}
