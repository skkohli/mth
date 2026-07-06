<?php
/**
 * Listeo Custom Field Display Condition for Elementor Pro
 *
 * @package Listeo_Elementor
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Listeo_Custom_Field_Condition
 *
 * Adds Listeo custom fields as Display Conditions in Elementor Pro.
 * Works with Elementor Pro 3.8+ Display Conditions module.
 */
class Listeo_Custom_Field_Condition extends \ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base {

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
     * Get condition group.
     *
     * @return string
     */
    public function get_group() {
        return 'post';
    }

    /**
     * Check condition.
     *
     * @param array $args Condition arguments.
     * @return bool
     */
    public function check( $args ): bool {
        $field_key = $args['field_key'] ?? '';
        $comparator = $args['comparator'] ?? 'is_not_empty';
        $field_value = $args['field_value'] ?? '';

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

            case 'greater_than':
                return is_numeric( $value ) && is_numeric( $field_value ) && (float) $value > (float) $field_value;

            case 'less_than':
                return is_numeric( $value ) && is_numeric( $field_value ) && (float) $value < (float) $field_value;

            default:
                return ! empty( $value );
        }
    }

    /**
     * Get options for field_key control.
     *
     * @return array
     */
    public function get_options() {
        $fields = listeo_elementor_get_all_custom_fields();

        $options = array();
        foreach ( $fields as $key => $label ) {
            $options[] = array(
                'label' => $label,
                'value' => $key,
            );
        }

        return array(
            'field_key' => array(
                'label' => esc_html__( 'Field', 'listeo_elementor' ),
                'type' => 'select',
                'options' => $options,
            ),
            'comparator' => array(
                'label' => esc_html__( 'Comparison', 'listeo_elementor' ),
                'type' => 'select',
                'options' => array(
                    array(
                        'label' => esc_html__( 'Is Not Empty', 'listeo_elementor' ),
                        'value' => 'is_not_empty',
                    ),
                    array(
                        'label' => esc_html__( 'Is Empty', 'listeo_elementor' ),
                        'value' => 'is_empty',
                    ),
                    array(
                        'label' => esc_html__( 'Equals', 'listeo_elementor' ),
                        'value' => 'equals',
                    ),
                    array(
                        'label' => esc_html__( 'Does Not Equal', 'listeo_elementor' ),
                        'value' => 'not_equals',
                    ),
                    array(
                        'label' => esc_html__( 'Contains', 'listeo_elementor' ),
                        'value' => 'contains',
                    ),
                    array(
                        'label' => esc_html__( 'Does Not Contain', 'listeo_elementor' ),
                        'value' => 'not_contains',
                    ),
                    array(
                        'label' => esc_html__( 'Greater Than', 'listeo_elementor' ),
                        'value' => 'greater_than',
                    ),
                    array(
                        'label' => esc_html__( 'Less Than', 'listeo_elementor' ),
                        'value' => 'less_than',
                    ),
                ),
                'default' => 'is_not_empty',
            ),
            'field_value' => array(
                'label' => esc_html__( 'Value', 'listeo_elementor' ),
                'type' => 'text',
            ),
        );
    }
}
