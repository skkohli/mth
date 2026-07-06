<?php
/**
 * Listing Resources Elementor widget.
 *
 * Surfaces the Listeo Booking Plus "Resources" section on the single
 * listing view so users editing the layout with Elementor Pro can place
 * it explicitly instead of relying on the default
 * `listeo/single-listing/after-overview` hook position.
 *
 * Registered conditionally — when LBP is not active, the require/register
 * in class-widgets.php is gated on `class_exists('LBP_Frontend')` so
 * Elementor doesn't surface a broken widget.
 *
 * @package ElementorListeo\Widgets
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ListingResources extends Widget_Base {

    public function get_name() {
        return 'listeo-listing-resources';
    }

    public function get_title() {
        return __( 'Listing Resources', 'listeo_elementor' );
    }

    public function get_icon() {
        // A grid icon fits the resource-cards layout better than the
        // generic alert icon used by sibling widgets.
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return array( 'listeo-single' );
    }

    public function get_keywords() {
        return array( 'listeo', 'booking', 'resources', 'rooms', 'stylists', 'tables', 'lbp' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __( 'Content', 'listeo_elementor' ),
            )
        );

        // Editor preview helper — the actual layout is rendered by
        // LBP_Frontend so we don't repeat its control surface here.
        $this->add_control(
            'listeo_resources_info',
            array(
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => sprintf(
                    /* translators: %s: Listing Resources */
                    '<div style="color:#666;font-size:12px;line-height:1.5">%s</div>',
                    esc_html__( 'This widget renders the Listing Resources block (powered by Listeo Booking Plus). Layout and per-listing settings are managed in the listing\'s metabox and the LBP settings page (Listeo → Settings → Booking).', 'listeo_elementor' )
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        // Hide the widget in archive / 404 / non-listing contexts so the
        // theme builder template doesn't blow up when previewed outside
        // a listing. LBP_Frontend::render_resources_section() also bails
        // on non-singular listings, but matching here keeps the editor
        // preview clean.
        if ( ! is_singular( 'listing' ) && ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            return;
        }

        if ( ! class_exists( 'LBP_Frontend' ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="elementor-alert elementor-alert-info">' .
                    esc_html__( 'Listeo Booking Plus is not active — install/activate it to render resources here.', 'listeo_elementor' ) .
                    '</div>';
            }
            return;
        }

        \LBP_Frontend::instance()->render_resources_section();
    }
}
