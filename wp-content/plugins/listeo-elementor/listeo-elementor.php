<?php
/*
 * Plugin Name: Listeo Elementor
 * Version: 2.0.28
 * Plugin URI: http://www.purethemes.net/
 * Description: Listeo widgets for Elementor
 * Author: Purethemes.net
 * Author URI: http://www.purethemes.net/
 *
 * Text Domain: listeo_elementor
 * Domain Path: /languages/
 *
 * @package WordPress
 * @author Lukasz Girek
 * @since 1.0.0
 */


define( 'ELEMENTOR_LISTEO', __FILE__ );

/**
 * Defensive fallback for `listeo_render_svg_icon()`.
 *
 * The function lives in the Listeo theme (`inc/extras.php`). When this
 * plugin is active on a site that isn't running the Listeo theme — or
 * is mid-upgrade with the theme files not yet loaded — every widget
 * that calls the function unguarded would fatal with
 * "Call to undefined function". Many widget files already wrap the
 * call in `function_exists()`, but several (notably
 * `class-listing-tax-checkboxes.php`, `class-tax-box.php`,
 * `class-home-banner*.php`, `class-tax-grid.php`,
 * `class-hierarchical-taxonomy.php`, `class-tax-list.php`) don't.
 *
 * Rather than retrofit every call site with a guard, we define a
 * minimal fallback here. The fallback returns a basic `<img>` tag
 * (the same `<img class="listeo-map-svg-icon" src="…" />` shape
 * already used in commented-out fallback code throughout the
 * plugin). It only registers when the theme hasn't already declared
 * its own implementation, so the Listeo theme keeps providing the
 * full inline-SVG version when present.
 *
 * Plays nice with namespaces: PHP's "unqualified function call inside
 * a namespace falls back to global scope" rule means callers like
 * `ElementorListeo\Widgets\…` resolve here without needing a `\` prefix
 * at the call site.
 */
/**
 * Defer the fallback registration to `after_setup_theme`. WordPress
 * loads plugins BEFORE the theme's `functions.php`, so a fallback
 * declared inline here would claim the global function name before
 * the theme's own `inc/extras.php` had a chance to define it — the
 * theme's unguarded `function listeo_render_svg_icon()` would then
 * fatal with "Cannot redeclare function".
 *
 * By the time `after_setup_theme` fires:
 *   - The theme's functions.php has run, which `require`s extras.php.
 *   - So if Listeo is the active theme and its files are intact,
 *     the function is already defined globally and our guard skips.
 *   - If the theme is missing/broken (the original bug we're guarding
 *     against), our fallback registers and keeps Elementor widgets
 *     from white-screening.
 *
 * Either way the theme's full inline-SVG implementation wins on
 * healthy installs.
 */
add_action( 'after_setup_theme', function () {
    if ( function_exists( 'listeo_render_svg_icon' ) ) {
        return;
    }
    function listeo_render_svg_icon( $value ) {
        $attachment_id = is_numeric( $value ) ? (int) $value : 0;
        if ( ! $attachment_id ) {
            return '';
        }
        $src = wp_get_attachment_image_url( $attachment_id, 'medium' );
        if ( ! $src ) {
            return '';
        }
        $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        return sprintf(
            '<img class="listeo-svg-icon" src="%s" alt="%s" />',
            esc_url( $src ),
            esc_attr( $alt )
        );
    }
}, 1 );

/**
 * Dynamic listing-type → taxonomy helpers for Elementor widgets.
 *
 * Historically each Listings widget (Carousel, Grid, Wide, etc.) shipped
 * with 5 hardcoded `tax-<type>_category` controls — one for each of the
 * four built-in listing types plus the universal `listing_category`. When
 * an admin adds a custom listing type via Listeo → Listing Types, the
 * type appears in the "Show only Listing Types" dropdown but its
 * taxonomy isn't exposed as a category filter, AND saved values for
 * removed-but-still-present default types stop being honoured.
 *
 * The three helpers below normalise this: one returns the currently
 * registered listing types (custom system first, hardcoded fallback),
 * one registers an `add_control()` per type taxonomy on a widget, one
 * builds the matching tax_query clauses from a settings array so each
 * widget's render() stays a one-line call instead of a copy-pasted
 * if-block for every type.
 */
if ( ! function_exists( 'listeo_elementor_get_listing_types_for_controls' ) ) {

    function listeo_elementor_get_listing_types_for_controls() {
        if ( function_exists( 'listeo_core_custom_listing_types' ) ) {
            $manager = listeo_core_custom_listing_types();
            $types   = $manager->get_listing_types( true ); // active only
            $out     = array();
            if ( is_array( $types ) ) {
                foreach ( $types as $type ) {
                    if ( ! empty( $type->slug ) ) {
                        $out[ $type->slug ] = isset( $type->name ) ? $type->name : ucfirst( $type->slug );
                    }
                }
            }
            if ( ! empty( $out ) ) {
                return $out;
            }
        }
        // Fallback when the custom-types system isn't loaded yet
        // (e.g. early bootstrap, broken Core install). Mirrors the
        // original hardcoded list so existing widgets keep working.
        return array(
            'service'     => __( 'Service', 'listeo_elementor' ),
            'rental'      => __( 'Rental', 'listeo_elementor' ),
            'event'       => __( 'Event', 'listeo_elementor' ),
            'classifieds' => __( 'Classifieds', 'listeo_elementor' ),
        );
    }

    /**
     * Build term-slug → name options for a single taxonomy. Pulled out
     * of each widget's per-taxonomy `get_terms()` method so the helper
     * doesn't need access to the widget instance.
     */
    function listeo_elementor_get_taxonomy_term_options( $taxonomy ) {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return array( '' => '' );
        }
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ) );
        $options = array( '' => '' );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( is_object( $term ) && isset( $term->slug, $term->name ) ) {
                    $options[ $term->slug ] = $term->name;
                }
            }
        }
        return $options;
    }

    /**
     * Add the universal `tax-listing_category` control plus one
     * `tax-<type_category>` control for every registered listing type
     * whose taxonomy actually exists. Stable control ids — when an
     * existing widget instance has a saved value for a type that's
     * still registered, the control keeps rendering with the same
     * id and the value flows through unchanged.
     *
     * @param \Elementor\Widget_Base $widget The widget to attach
     *                                       controls to (typically `$this`).
     */
    function listeo_elementor_add_listing_taxonomy_controls( $widget ) {
        if ( ! is_object( $widget ) || ! method_exists( $widget, 'add_control' ) ) {
            return;
        }

        // Universal "listing_category" stays first — every listing
        // type inherits it (it's registered on the `listing` post
        // type, not per type).
        $widget->add_control( 'tax-listing_category', array(
            'label'       => __( 'Show only from listing categories', 'listeo_elementor' ),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'label_block' => true,
            'multiple'    => true,
            'default'     => array(),
            'options'     => listeo_elementor_get_taxonomy_term_options( 'listing_category' ),
        ) );

        $types         = listeo_elementor_get_listing_types_for_controls();
        $seen_taxonomy = array( 'listing_category' => true ); // avoid duplicates

        foreach ( $types as $slug => $name ) {
            $taxonomy = function_exists( 'listeo_core_get_taxonomy_for_listing_type' )
                ? listeo_core_get_taxonomy_for_listing_type( $slug )
                : $slug . '_category';

            if ( isset( $seen_taxonomy[ $taxonomy ] ) ) {
                continue;
            }
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }
            $seen_taxonomy[ $taxonomy ] = true;

            $widget->add_control( 'tax-' . $taxonomy, array(
                'label'       => sprintf(
                    /* translators: %s: listing type label, e.g. "service" / "rental" */
                    __( 'Show only from %s categories', 'listeo_elementor' ),
                    strtolower( $name )
                ),
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple'    => true,
                'default'     => array(),
                'options'     => listeo_elementor_get_taxonomy_term_options( $taxonomy ),
            ) );
        }
    }

    /**
     * Inspect a widget's settings array and return a `tax_query`-style
     * list of clauses for every category control that has values.
     *
     * Used by each widget's render() to replace the hardcoded if-blocks
     * that only ever knew about the 4 default listing types. The
     * returned array can be appended directly to whatever the widget
     * is building (typically a `tax_query` with a `relation` key set
     * separately).
     *
     * @param array $settings Widget settings — usually
     *                        `$this->get_settings_for_display()`.
     * @return array          Indexed array of WP_Tax_Query clauses.
     */
    function listeo_elementor_build_listing_tax_query( $settings ) {
        if ( ! is_array( $settings ) ) {
            return array();
        }

        $out      = array();
        $handled  = array();

        // Universal first.
        if ( ! empty( $settings['tax-listing_category'] ) ) {
            $terms = is_array( $settings['tax-listing_category'] )
                ? $settings['tax-listing_category']
                : array_filter( array_map( 'trim', explode( ',', (string) $settings['tax-listing_category'] ) ) );
            if ( ! empty( $terms ) ) {
                $out[] = array(
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $terms,
                );
            }
            $handled['listing_category'] = true;
        }

        // Per-type. Iterate the same dynamic source the controls do
        // so new types are picked up automatically.
        $types = listeo_elementor_get_listing_types_for_controls();
        foreach ( $types as $slug => $name ) {
            $taxonomy = function_exists( 'listeo_core_get_taxonomy_for_listing_type' )
                ? listeo_core_get_taxonomy_for_listing_type( $slug )
                : $slug . '_category';
            if ( isset( $handled[ $taxonomy ] ) ) {
                continue;
            }
            $setting_key = 'tax-' . $taxonomy;
            if ( empty( $settings[ $setting_key ] ) ) {
                continue;
            }
            $terms = is_array( $settings[ $setting_key ] )
                ? $settings[ $setting_key ]
                : array_filter( array_map( 'trim', explode( ',', (string) $settings[ $setting_key ] ) ) );
            if ( empty( $terms ) ) {
                continue;
            }
            $out[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $terms,
            );
            $handled[ $taxonomy ] = true;
        }

        return $out;
    }
}


/**
 * Include the Elementor_Listeo class.
 */
require plugin_dir_path( ELEMENTOR_LISTEO ) . 'class-elementor-listeo.php';