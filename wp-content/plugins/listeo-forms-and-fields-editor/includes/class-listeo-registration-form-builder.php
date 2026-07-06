<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Registration_Form_Editor
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

        add_action('admin_menu', array($this, 'add_options_page')); //create tab pages

        add_filter('listeo_user_owner_registration_form', array($this, 'add_listeo_user_owner_registration_form'));
        add_filter('listeo_user_guest_registration_form', array($this, 'add_listeo_user_guest_registration_form'));
    }



    function add_listeo_user_owner_registration_form($fields)
    {
        $new_fields =  get_option('listeo_owner_registration_form');
        if (!empty($new_fields)) {
            $fields = $new_fields;
        }
        return $fields;
    }

    function add_listeo_user_guest_registration_form($fields)
    {
        $new_fields =  get_option('listeo_guest_registration_form');
        if (!empty($new_fields)) {
            $fields = $new_fields;
        }
        return $fields;
    }



    /**
     * Add menu options page
     * @since 0.1.0
     */
    public function add_options_page()
    {
        add_submenu_page('listeo-fields-and-form', 'Registration Fields', 'Registration Fields', 'manage_options', 'listeo-user-fields-registration', array($this, 'output'));
    }


    public function output()
    {

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'owner';

        $tabs = array(
            'owner'                 => __('Owner Registration Fields', 'listeo-fafe'),
            'guest'                 => __('Guest Registration Fields', 'listeo-fafe'),


        );

        if (!empty($_GET['reset-fields']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reset')) {
            delete_option("listeo_{$tab}_registration_form");
            echo '<div class="updated"><p>' . __('The fields were successfully reset.', 'listeo') . '</p></div>';
        }

        if (!empty($_POST)) { /* add nonce tu*/

            echo $this->form_editor_save($tab);
        }


        $field_types = apply_filters(
            'listeo_form_field_types',
            array(
                'text'           => __('Text', 'listeo-editor'),
                'wp-editor'       => __('Textarea', 'listeo-editor'),
                'radio'         => __('Radio', 'listeo-editor'),
                'select'         => __('Select', 'listeo-editor'),
                'select_multiple'   => __('Multi Select', 'listeo-editor'),
                'checkbox'          => __('Checkbox', 'listeo-editor'),
                'multicheck_split'        => __('Multi Checkbox', 'listeo-editor'),
                'file'              => __('File upload', 'listeo-editor'),
                'header'              => __('Header', 'listeo-editor'),

            )
        );


        switch ($tab) {
            case 'owner':
                $fields =  get_option('listeo_owner_registration_form', array());
                break;
            case 'guest':
                $fields =  get_option('listeo_guest_registration_form', array());
                break;


            default:
                $fields = get_option('listeo_owner_registration_form');
                break;
        }

        if (isset($fields['fields'])) {
            $fields = $fields['fields'];
        }



?>

        <h2>Registration Fields Editor</h2>
        <div class="listeo-editor-wrap">

            <div class="nav-tab-container">
                <h2 class="nav-tab-wrapper form-builder">
                    <?php
                    foreach ($tabs as $key => $value) {

                        $active = ($key == $tab) ? 'nav-tab-active' : '';
                        echo '<a class="nav-tab ' . $active . '" href="' . admin_url('admin.php?page=listeo-user-fields-registration&tab=' . esc_attr($key)) . '">' . esc_html($value) . '</a>';
                    }
                    ?>
                </h2>
            </div>
            <div class="listeo-forms-builder wrap">
                <div class="wrap listeo-form-editor  listeo-registration-form-builder">
                    <form method="post" id="mainform" action="admin.php?page=listeo-user-fields-registration&amp;tab=<?php echo esc_attr($tab); ?>">
                        <h3 class="listeo-editor-form-header">
                            <?php
                            foreach ($tabs as $key => $value) {
                                if ($active = ($key == $tab)) {
                                    echo esc_html__($value);
                                }
                            } ?>
                            <input name="Submit" type="submit" class="button-primary" value="Save Settings">
                        </h3>
                        <div class="listeo-forms-builder-left">

                            <div class="form-editor-container main" id="listeo-fafe-fields-editor" data-clone="<?php
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
                                                                                                                );
                                                                                                                ?>
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
                            if ($fields) {

                                foreach ($fields as $field_key => $field) {
                                    $index++;

                                    if (is_array($field)) { ?>
                                        <div class="form_item">
                                            <span class="handle dashicons dashicons-editor-justify"></span>
                                            <div class="element_title"><?php echo esc_attr((isset($field['placeholder'])) ? $field['placeholder'] : $field['name']); ?>
                                                <div class="element_title_edit"><span class="dashicons dashicons-edit"></span> Edit</div>
                                            </div>
                                            <?php include(plugin_dir_path(__DIR__) .  'views/forms-editor/form-edit-registration.php'); ?>
                                            <div class="remove_item"> <span class="dashicons dashicons-remove"></span> </div>
                                        </div>
                            <?php }
                                }
                            }  ?>
                            <div class="droppable-helper"></div>
                        </div>


                        <?php wp_nonce_field('save-' . $tab); ?>

                        <div class="listeo-forms-builder-bottom">

                            <input type="submit" class="save-fields button-primary" value="<?php _e('Save Changes', 'listeo'); ?>" />
                            <a href="<?php echo wp_nonce_url(add_query_arg('reset-fields', 1), 'reset'); ?>" class="reset button-secondary"><?php _e('Reset to defaults', 'listeo'); ?></a>
                        </div>

                        <?php wp_nonce_field('save-fields'); ?>
                        <?php wp_nonce_field('save'); ?>

                </div>
                </form>
                <div class="listeo-forms-builder-right">

                    <h3>Available Fields</h3>
                    <div class="search-filter-container">
                        <input type="text" id="element-search" placeholder="Search elements..." class="element-search-input">
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const searchInput = document.getElementById('element-search');

                            searchInput.addEventListener('input', function() {
                                const searchTerm = this.value.toLowerCase();
                                const formItems = document.querySelectorAll('.form-editor-available-elements-container .form_item');

                                formItems.forEach(function(item) {
                                    const elementTitle = item.querySelector('.element_title').textContent.toLowerCase();

                                    if (elementTitle.includes(searchTerm)) {
                                        item.classList.remove('hidden');
                                    } else {
                                        item.classList.add('hidden');
                                    }
                                });
                            });
                        });
                    </script>
                    <div class="form-editor-available-elements-container">
                        <?php
                        $visual_fields = array();
                        $meta_fields = array(
                            Listeo_Core_Meta_Boxes::meta_boxes_user_owner(),
                            Listeo_Core_Meta_Boxes::meta_boxes_user_guest()

                        );

                        foreach ($meta_fields as $key) {
                            $key = maybe_unserialize($key);
                            foreach ($key as  $field) {

                                if (in_array($field['type'], array('radio', 'select', 'select_multiple', 'multicheck_split'))) {
                                    $visual_fields[$field['id']] = array(

                                        'placeholder'   => $field['name'],
                                        'name'          => $field['name'],
                                        'key'           => $field['id'],
                                        'class'         => '',
                                        'css_class'     => '',
                                        'id'            => $field['id'],
                                        'priority'      => 9,
                                        'place'         => 'main',
                                        'type'          => $field['type'],
                                        'options'       => $field['options'],
                                    );
                                } else {
                                    $visual_fields[$field['id']] = array(
                                        'placeholder'   => $field['name'],
                                        'name'          => $field['name'],
                                        'key'           => $field['id'],
                                        'class'         => '',
                                        'css_class'     => '',
                                        'id'            => $field['id'],
                                        'priority'      => 9,
                                        'place'         => 'main',
                                        'type'          => $field['type'],

                                    );
                                }
                            }
                        }

                        foreach ($visual_fields as $key => $field) {
                            $index++;
                        ?>
                            <div class="form_item" data-priority="0">
                                <span class="handle dashicons dashicons-editor-justify"></span>
                                <div class="element_title"><?php echo  $field['placeholder'];  ?> <div class="element_title_edit"><span class="dashicons dashicons-edit"></span> Edit</div>
                                </div>
                                <?php include(plugin_dir_path(__DIR__) .  'views/forms-editor/form-edit-ready-field.php'); ?>
                                <div class="remove_item"> <span class="dashicons dashicons-remove"></span> </div>
                            </div>
                        <?php }
                        ?>
                    </div>

                    <button id="listeo-show-names" class="button">Show fields names (adv users only)</button>
                </div>
                <div class="clear"></div>
            </div>
            <div class="clear"></div>
        </div>




<?php
    }

    private function form_editor_save($tab)
    {

        $field_name             = !empty($_POST['name']) ? array_map('sanitize_text_field', $_POST['name'])                    : array();
        $field_placeholder      = !empty($_POST['placeholder']) ? array_map('sanitize_text_field', $_POST['placeholder'])      : array();
        $field_id               = !empty($_POST['id']) ? array_map('sanitize_text_field', $_POST['id'])                        : array();
        $field_icon             = !empty($_POST['icon']) ? array_map('sanitize_text_field', $_POST['icon'])                    : array();
        $field_required         = !empty($_POST['required']) ? array_map('sanitize_text_field', $_POST['required'])            : array();
        $field_type             = !empty($_POST['type']) ? array_map('sanitize_text_field', $_POST['type'])                    : array();
        $field_default          = !empty($_POST['default']) ? array_map('sanitize_text_field', $_POST['default'])              : array();
        $field_invert           = !empty($_POST['invert']) ? array_map('sanitize_text_field', $_POST['invert'])                : array();
        $field_desc             = !empty($_POST['desc']) ? array_map('sanitize_text_field', $_POST['desc'])                    : array();
        $field_options_cb       = !empty($_POST['options_cb']) ? array_map('sanitize_text_field', $_POST['options_cb'])        : array();
        $field_options_source   = !empty($_POST['options_source']) ? array_map('sanitize_text_field', $_POST['options_source']) : array();
        $field_options          = !empty($_POST['options']) ? $this->sanitize_array($_POST['options'])                         : array();
        $new_fields             = array();
        $index                  = 0;

        foreach ($field_name as $key => $field) {

            $name            = sanitize_title($field_name[$key]);
            $options        = array();
            if (!empty($field_options[$key])) {
                foreach ($field_options[$key] as $op_key => $op_value) {

                    $options[stripslashes($op_value['name'])] = stripslashes($op_value['value']);
                }
            }

            $new_field                      = array();
            $new_field['name']              = $field_name[$key];
            $new_field['label']             = $field_name[$key];
            $new_field['placeholder']       = isset($field_placeholder[$key]) ? $field_placeholder[$key] : '';
            $new_field['required']          = isset($field_required[$key]) ? $field_required[$key] : '';
            $new_field['default']           = isset($field_default[$key]) ? $field_default[$key] : '';
            $new_field['type']              = isset($field_type[$key]) ? $field_type[$key] : 'text';
            $new_field['icon']              = isset($field_icon[$key]) ? $field_icon[$key] : '';

            // $new_field['desc']              = $field_desc[ $key ];

            $new_field['options']           = $options;


            $new_fields[$name]       = $new_field;
        }


        $result = update_option("listeo_{$tab}_registration_form", $new_fields);

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
}
