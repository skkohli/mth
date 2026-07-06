<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Elementor Dynamic Tag - Server Variable
 *
 * Elementor dynamic tag that returns a server variable.
 *
 * @since 1.0.0
 */
class Elementor_Dynamic_Tag_Listeo_Custom_Fields extends \Elementor\Core\DynamicTags\Tag
{

    /**
     * Get dynamic tag name.
     *
     * Retrieve the name of the server variable tag.
     *
     * @since 1.0.0
     * @access public
     * @return string Dynamic tag name.
     */
    public function get_name()
    {
        return 'server-variable';
    }

    /**
     * Get dynamic tag title.
     *
     * Returns the title of the server variable tag.
     *
     * @since 1.0.0
     * @access public
     * @return string Dynamic tag title.
     */
    public function get_title()
    {
        return esc_html__('Listeo Custom Fields', 'listeo_elementor');
    }

    /**
     * Get dynamic tag groups.
     *
     * Retrieve the list of groups the server variable tag belongs to.
     *
     * @since 1.0.0
     * @access public
     * @return array Dynamic tag groups.
     */
    public function get_group()
    {
        return ['request-variables'];
    }

    /**
     * Get dynamic tag categories.
     *
     * Retrieve the list of categories the server variable tag belongs to.
     *
     * @since 1.0.0
     * @access public
     * @return array Dynamic tag categories.
     */
    public function get_categories()
    {
        return [
            \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
            \Elementor\Modules\DynamicTags\Module::URL_CATEGORY
        ];
    }

    /**
     * Register dynamic tag controls.
     *
     * Add input fields to allow the user to customize the server variable tag settings.
     *
     * @since 1.0.0
     * @access protected
     * @return void
     */
    protected function register_controls()
    {
        // Use the shared helper function to get all custom fields
        $fields = listeo_elementor_get_all_custom_fields();

        $this->add_control(
            'selected_custom_field',
            [
                'type' => \Elementor\Controls_Manager::SELECT,
                'label' => esc_html__( 'Custom Field', 'listeo_elementor' ),
                'options' => $fields,
            ]
        );
    }

    /**
     * Render tag output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function render()
    {
        $selected_custom_field = $this->get_settings('selected_custom_field');

        if (!$selected_custom_field) {
            return;
        }

        // Get full field data to check type and options
        $all_fields = listeo_elementor_get_all_custom_fields_full();
        $field_data = isset( $all_fields[ $selected_custom_field ] ) ? $all_fields[ $selected_custom_field ] : null;
        $field_type = isset( $field_data['type'] ) ? $field_data['type'] : 'text';
        $options    = isset( $field_data['options'] ) ? $field_data['options'] : array();

        // For multicheck/select_multiple, retrieve all values
        if ( in_array( $field_type, array( 'multicheck_split', 'select_multiple' ), true ) ) {
            $values = get_post_meta( get_the_ID(), $selected_custom_field, false );
            if ( ! empty( $values ) && ! empty( $options ) ) {
                $labels = array();
                foreach ( $values as $val ) {
                    $labels[] = isset( $options[ $val ] ) ? $options[ $val ] : $val;
                }
                echo wp_kses_post( implode( ', ', $labels ) );
            } else {
                echo wp_kses_post( implode( ', ', $values ) );
            }
            return;
        }

        $value = get_post_meta( get_the_ID(), $selected_custom_field, true );

        // For select fields, resolve stored key to label
        if ( $field_type === 'select' && ! empty( $options ) ) {
            if ( is_array( $value ) ) {
                $labels = array();
                foreach ( $value as $val ) {
                    $labels[] = isset( $options[ $val ] ) ? $options[ $val ] : $val;
                }
                echo wp_kses_post( implode( ', ', $labels ) );
            } else {
                echo wp_kses_post( isset( $options[ $value ] ) ? $options[ $value ] : $value );
            }
            return;
        }

        if ( is_array( $value ) ) {
            $value = implode( ', ', $value );
        }
        echo wp_kses_post( $value );
    }
}
