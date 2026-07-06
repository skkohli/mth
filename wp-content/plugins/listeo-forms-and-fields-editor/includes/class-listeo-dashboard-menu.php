<?php
if (! defined('ABSPATH')) {
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

        add_action('admin_menu', array($this, 'add_options_page')); //create tab pages
        add_filter('listeo_reviews_criteria', array($this, 'add_criteria_reviews_from_option'));
    }

    /**
     * Add menu options page
     * @since 0.1.0
     */
    public function add_options_page()
    {
        add_submenu_page('listeo-fields-and-form', 'Reviews Criteria', 'Reviews Criteria', 'manage_options', 'listeo-reviews-criteria', array($this, 'output'));
    }


    public function output()
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'reviews_criteria';

        $tabs = array(
            'reviews_criteria'          => __('Reviews Criteria', 'listeo-fafe'),
        );
        if (! empty($_POST)) { /* add nonce tu*/
            echo $this->form_editor_save($tab);
        }
        if (! empty($_GET['reset-fields']) && ! empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reset')) {
            delete_option("listeo_{$tab}_fields");
            echo '<div class="updated"><p>' . __('The fields were successfully reset.', 'listeo') . '</p></div>';
        }
        $default_fields = array();
        switch ($tab) {
            case 'reviews_criteria':
                $default_fields = listeo_get_reviews_criteria();
                break;


            default:
                $default_fields = listeo_get_reviews_criteria();
                break;
        }

?>
        <div class="wrap listeo-form-editor">
            <h2>Listeo Editor</h2>
            <div class="nav-tab-container">
                <h2 class="nav-tab-wrapper">
                    <?php
                    foreach ($tabs as $key => $value) {
                        $active = ($key == $tab) ? 'nav-tab-active' : '';
                        echo '<a class="nav-tab ' . $active . '" href="' . admin_url('admin.php?page=listeo-reviews-criteria&tab=' . esc_attr($key)) . '">' . esc_html($value) . '</a>';
                    }

                    ?>
                </h2>
            </div>
            <form method="post" id="mainform" action="admin.php?page=listeo-reviews-criteria&amp;tab=<?php echo esc_attr($tab); ?>">
                <h3 class="listeo-editor-form-header">
                    <?php
                    foreach ($tabs as $key => $value) {
                        if ($active = ($key == $tab)) {
                            echo esc_html__($value);
                        }
                    } ?>
                    <input name="Submit" type="submit" class="button-primary" value="Save Settings">
                </h3>
                <div class="listeo-form-editor main-options">
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <td width="20%">Criterium Title</td>
                                <td>Tooltip (optional)</td>
                                <td width="20%"></td>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <td colspan="3">
                                    <a class=" button-primary add-new-main-option" href="#">Add New</a>
                                </td>
                            </tr>
                        </tfoot>

                        <tbody data-field="<?php
                                            echo esc_attr('<tr>
                            <td><input type="text" class="input-text options" name="label[-1]" /></td>
                            <td> <textarea name="tooltip[-1]"  rows="5"></textarea></td>
                            <td><a class="remove-row button" href="#">Remove</a></td></tr>');
                                            ?>">
                            <?php
                            $i = 0;
                            foreach ($default_fields as $key => $value) { ?>
                                <tr>
                                    <td>
                                        <input type="text" value="<?php echo stripslashes(esc_attr($value['label'])); ?>" class="input-text options" name="label[<?php echo esc_attr($i); ?>]" />
                                    </td>
                                    <td>
                                        <textarea name="tooltip[<?php echo esc_attr($i); ?>]" id="tooltip-<?php echo esc_attr($i); ?>" rows="5"><?php echo stripslashes(esc_attr($value['tooltip'])); ?></textarea>
                                    </td>

                                    <td><a class="remove-row button" href="#">Remove</a></td>
                                </tr>
                            <?php
                                $i++;
                            } ?>
                        </tbody>
                    </table>
                </div>

                <div class="listeo-forms-editor-bottom">
                    <input type="submit" class="save-fields button-primary" value="<?php _e('Save Changes', 'listeo'); ?>" />
                    <a href="<?php echo wp_nonce_url(add_query_arg('reset-fields', 1), 'reset'); ?>" class="reset button-secondary"><?php _e('Reset to defaults', 'listeo'); ?></a>
                </div>
            </form>
    <?php
    }

    private function form_editor_save($tab)
    {

        if ($tab == "reviews_criteria") {


            $field_name    = ! empty($_POST['label']) ? array_map('sanitize_text_field', $_POST['label'])  : array();
            $field_value   = ! empty($_POST['tooltip']) ? array_map('sanitize_text_field', $_POST['tooltip'])      : array();

            // $field_label    = ! empty( $_POST['label'] ) ? array_map( 'sanitize_text_field', $_POST['label'] )  : array();
            // $field_tooltip  = ! empty( $_POST['key'] ) ? array_map( 'sanitize_text_field', $_POST['key'] )      : array();
            $new_fields             = array();
            $index                  = 0;

            foreach ($field_name as $key => $field) {

                if (empty($field_name[$key])) {
                    continue;
                }
                $name            = sanitize_title($field_name[$key]);

                $new_field                      = array();
                $new_field['label']              = $field_name[$key];
                $new_field['tooltip']             = $field_value[$key];
                $new_fields[$name]       = $new_field;
            }
        } else {
            $values             = ! empty($_POST['value']) ? array_map('sanitize_text_field', $_POST['value'])                     : array();
            $new_fields             = array();
            $index                  = 0;

            foreach ($values as $key => $field) {
                if (empty($values[$key])) {
                    continue;
                }
                $new_fields[]       = $values[$key];
            }
        }

        $result = update_option("listeo_{$tab}_fields", $new_fields);

        if (true === $result) {
            echo '<div class="updated"><p>' . __('The fields were successfully saved.', 'wp-job-manager-applications') . '</p></div>';
        }
    }


    function add_criteria_reviews_from_option($r)
    {
        $reviews_criteria =  get_option('listeo_reviews_criteria_fields');
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
