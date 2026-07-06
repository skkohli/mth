<?php
/**
 * Listeo Theme Builder Condition for Elementor Pro
 *
 * @package Listeo_Elementor
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Listeo_Theme_Condition
 *
 * Adds Listeo custom fields as Theme Builder Conditions in Elementor Pro.
 * Used to control which templates are applied based on custom field values.
 */
class Listeo_Theme_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    /**
     * Get condition type.
     *
     * @return string
     */
    public static function get_type() {
        return 'singular';
    }

    /**
     * Get condition name.
     *
     * @return string
     */
    public function get_name() {
        return 'listeo_custom_field';
    }

    /**
     * Get condition label.
     *
     * @return string
     */
    public function get_label() {
        return esc_html__( 'Listeo Custom Field', 'listeo_elementor' );
    }

    /**
     * Get all labels.
     *
     * @return array
     */
    public function get_all_label() {
        return esc_html__( 'All Listeo Custom Fields', 'listeo_elementor' );
    }

    /**
     * Register controls for condition.
     *
     * @return void
     */
    protected function register_controls() {
        $fields = listeo_elementor_get_all_custom_fields();

        $this->add_control(
            'field_key',
            [
                'section' => 'settings',
                'type' => \Elementor\Controls_Manager::SELECT,
                'label' => esc_html__( 'Field', 'listeo_elementor' ),
                'options' => $fields,
            ]
        );

        $this->add_control(
            'comparator',
            [
                'section' => 'settings',
                'type' => \Elementor\Controls_Manager::SELECT,
                'label' => esc_html__( 'Comparison', 'listeo_elementor' ),
                'options' => [
                    'is_not_empty' => esc_html__( 'Is Not Empty', 'listeo_elementor' ),
                    'is_empty'     => esc_html__( 'Is Empty', 'listeo_elementor' ),
                    'equals'       => esc_html__( 'Equals', 'listeo_elementor' ),
                    'not_equals'   => esc_html__( 'Does Not Equal', 'listeo_elementor' ),
                    'contains'     => esc_html__( 'Contains', 'listeo_elementor' ),
                    'not_contains' => esc_html__( 'Does Not Contain', 'listeo_elementor' ),
                ],
                'default' => 'is_not_empty',
            ]
        );

        $this->add_control(
            'field_value',
            [
                'section' => 'settings',
                'type' => \Elementor\Controls_Manager::TEXT,
                'label' => esc_html__( 'Value', 'listeo_elementor' ),
                'condition' => [
                    'comparator' => [ 'equals', 'not_equals', 'contains', 'not_contains' ],
                ],
            ]
        );
    }

    /**
     * Check condition.
     *
     * @param array $args Condition arguments.
     * @return bool
     */
    public function check( $args ) {
        $field_key = isset( $args['field_key'] ) ? $args['field_key'] : '';
        $comparator = isset( $args['comparator'] ) ? $args['comparator'] : 'is_not_empty';
        $field_value = isset( $args['field_value'] ) ? $args['field_value'] : '';

        if ( empty( $field_key ) ) {
            return false;
        }

        $post_id = get_the_ID();

        if ( ! $post_id ) {
            return false;
        }

        $value = get_post_meta( $post_id, $field_key, true );

        // Handle array values
        if ( is_array( $value ) ) {
            $value = implode( ', ', $value );
        }

        switch ( $comparator ) {
            case 'is_empty':
                return empty( $value );

            case 'is_not_empty':
                return ! empty( $value );

            case 'equals':
                return (string) $value === (string) $field_value;

            case 'not_equals':
                return (string) $value !== (string) $field_value;

            case 'contains':
                return strpos( (string) $value, $field_value ) !== false;

            case 'not_contains':
                return strpos( (string) $value, $field_value ) === false;

            default:
                return ! empty( $value );
        }
    }
}
