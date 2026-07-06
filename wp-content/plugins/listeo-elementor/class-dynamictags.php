<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Get all Listeo custom fields with full field data.
 *
 * Returns complete field arrays (id, name, type, options, etc.) from all sources:
 * core meta boxes, Forms & Fields Editor listing type fields, custom listing types,
 * and taxonomy term-specific fields.
 *
 * @since 2.2.0
 * @return array Associative array of field_id => field_data_array
 */
function listeo_elementor_get_all_custom_fields_full() {
    static $fields = null;

    if ( $fields !== null ) {
        return $fields;
    }

    $fields = array();

    // Add core listing status fields first
    $fields['_verified'] = array(
        'id'   => '_verified',
        'name' => __( 'Claimed/Verified (on = yes)', 'listeo_elementor' ),
        'type' => 'text',
    );
    $fields['_featured'] = array(
        'id'   => '_featured',
        'name' => __( 'Featured (1 = yes)', 'listeo_elementor' ),
        'type' => 'text',
    );
    $fields['_user_package_id'] = array(
        'id'   => '_user_package_id',
        'name' => __( 'Paid Listing (has package)', 'listeo_elementor' ),
        'type' => 'text',
    );
    $fields['_listing_type'] = array(
        'id'   => '_listing_type',
        'name' => __( 'Listing Type', 'listeo_elementor' ),
        'type' => 'text',
    );

    if ( ! class_exists( 'Listeo_Core_Meta_Boxes' ) ) {
        return $fields;
    }

    $field_count = 4;
    $max_fields = 500;

    // 1. Core meta box fields
    $metabox_types = array( 'service', 'location', 'event', 'prices', 'contact', 'rental', 'classifieds', 'custom' );

    foreach ( $metabox_types as $type ) {
        $method_name = "meta_boxes_{$type}";
        if ( method_exists( '\Listeo_Core_Meta_Boxes', $method_name ) ) {
            $metabox = \Listeo_Core_Meta_Boxes::$method_name();
            if ( ! empty( $metabox['fields'] ) && is_array( $metabox['fields'] ) ) {
                foreach ( $metabox['fields'] as $field ) {
                    if ( isset( $field['id'] ) ) {
                        $fields[ $field['id'] ] = $field;
                        $field_count++;
                        if ( $field_count >= $max_fields ) {
                            break 2;
                        }
                    }
                }
            }
        }
    }

    // 2. Listing type fields from Forms & Fields Editor
    $default_types = array( 'service', 'rental', 'event', 'classifieds', 'contact', 'custom', 'locations' );

    foreach ( $default_types as $type ) {
        $saved_fields = get_option( "listeo_{$type}_tab_fields", array() );
        if ( is_array( $saved_fields ) ) {
            foreach ( $saved_fields as $field ) {
                if ( isset( $field['id'] ) && $field_count < $max_fields ) {
                    $fields[ $field['id'] ] = $field;
                    $field_count++;
                }
            }
        }
    }

    // 3. Custom listing types from Forms & Fields Editor
    if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
        $custom_types_manager = listeo_core_custom_listing_types();
        $listing_types = $custom_types_manager->get_listing_types( false, true );

        foreach ( $listing_types as $type ) {
            if ( $type->is_active ) {
                $saved_fields = get_option( "listeo_{$type->slug}_tab_fields", array() );
                if ( is_array( $saved_fields ) ) {
                    foreach ( $saved_fields as $field ) {
                        if ( isset( $field['id'] ) && $field_count < $max_fields ) {
                            $fields[ $field['id'] ] = $field;
                            $field_count++;
                        }
                    }
                }
            }
        }
    }

    // 4. Taxonomy term-specific fields
    $listing_taxonomies = get_object_taxonomies( 'listing', 'objects' );
    foreach ( $listing_taxonomies as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy->name,
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            $term_fields = get_option( "listeo_tax-{$taxonomy->name}_term_{$term->term_id}_fields", array() );
            if ( empty( $term_fields ) ) {
                $term_fields = get_option( "listeo_{$taxonomy->name}_{$term->term_id}_fields", array() );
            }

            if ( ! empty( $term_fields ) && is_array( $term_fields ) ) {
                foreach ( $term_fields as $field ) {
                    if ( isset( $field['id'] ) && $field_count < $max_fields ) {
                        if ( isset( $field['name'] ) ) {
                            $field['name'] = $field['name'] . " ({$term->name})";
                        }
                        $fields[ $field['id'] ] = $field;
                        $field_count++;
                    }
                }
            }
        }
    }

    return $fields;
}

/**
 * Get all Listeo custom fields.
 *
 * Helper function to retrieve all custom fields as a simplified id => name map.
 * Used by both dynamic tags and display conditions.
 *
 * @since 2.1.0
 * @return array Associative array of field_id => field_name
 */
function listeo_elementor_get_all_custom_fields() {
    $full_fields = listeo_elementor_get_all_custom_fields_full();
    $fields = array();

    foreach ( $full_fields as $id => $field ) {
        $fields[ $id ] = isset( $field['name'] ) ? $field['name'] : $id;
    }

    return $fields;
}

/**
 * Register Request Variables Dynamic Tag Group.
 *
 * Register new dynamic tag group for Request Variables.
 *
 * @since 1.0.0
 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Elementor dynamic tags manager.
 * @return void
 */
function register_custom_fields_dynamic_tag_group( $dynamic_tags_manager ) {

    $dynamic_tags_manager->register_group(
        'request-variables',
        [
            'title' => esc_html__( 'Listeo Custom Fields', 'listeo_elementor' )
        ]
    );

}
add_action( 'elementor/dynamic_tags/register', 'register_custom_fields_dynamic_tag_group' );

/**
* Register Server Variable Dynamic Tag.
*
* Include dynamic tag file and register tag class.
*
* @since 1.0.0
* @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Elementor dynamic tags manager.
* @return void
*/
function register_custom_fields_dynamic_tag( $dynamic_tags_manager ) {

require_once( __DIR__ . '/dynamic-tags/custom-fields-dynamic-tag.php' );

$dynamic_tags_manager->register( new \Elementor_Dynamic_Tag_Listeo_Custom_Fields );

}
add_action( 'elementor/dynamic_tags/register', 'register_custom_fields_dynamic_tag' );

/**
 * Register Listeo Custom Field Display Condition.
 *
 * Adds Listeo custom fields as Display Conditions in Elementor Pro 3.19+.
 * This allows users to show/hide widgets based on custom field values.
 *
 * @since 2.1.0
 * @param object $conditions_manager Elementor Pro conditions manager.
 * @return void
 */
function listeo_elementor_register_display_conditions( $conditions_manager ) {
    // Check if the base class exists (Elementor Pro 3.19+)
    if ( ! class_exists( '\ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base' ) ) {
        return;
    }

    require_once __DIR__ . '/conditions/class-listeo-custom-field-condition.php';

    if ( class_exists( 'Listeo_Custom_Field_Condition' ) ) {
        // Elementor Pro API changed - register_condition() is now private in newer versions
        // Use reflection to check if method is accessible before calling
        try {
            $reflection = new \ReflectionMethod( $conditions_manager, 'register_condition' );
            if ( $reflection->isPublic() ) {
                $conditions_manager->register_condition( new \Listeo_Custom_Field_Condition() );
            }
        } catch ( \Exception $e ) {
            // Method doesn't exist or isn't accessible - Display Conditions feature not available
            // This is expected in newer Elementor Pro versions where the API changed
        }
    }
}

// Register Display Conditions for Elementor Pro 3.19+ (new API)
add_action( 'elementor/display_conditions/register', 'listeo_elementor_register_display_conditions' );

/**
 * Register Listeo Custom Field as Theme Builder Condition.
 *
 * For Theme Builder template conditions (which pages to apply templates to).
 *
 * @since 2.1.0
 * @param object $conditions_manager Elementor Pro Theme Builder conditions manager.
 * @return void
 */
function listeo_elementor_register_theme_conditions( $conditions_manager ) {
    // Check if the base class exists (Elementor Pro Theme Builder)
    if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base' ) ) {
        return;
    }

    require_once __DIR__ . '/conditions/class-listeo-theme-condition.php';

    if ( class_exists( 'Listeo_Theme_Condition' ) ) {
        $general = $conditions_manager->get_condition( 'general' );
        if ( $general ) {
            $general->register_sub_condition( new \Listeo_Theme_Condition() );
        }
    }
}

// Register Theme Builder conditions (for template assignment)
add_action( 'elementor/theme/register_conditions', 'listeo_elementor_register_theme_conditions' );
