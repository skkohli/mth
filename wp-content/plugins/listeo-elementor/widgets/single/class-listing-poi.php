<?php

/**
 * Listing POI widget class.
 *
 * @category   Class
 * @package    ElementorListeo
 * @subpackage WordPress
 * @author     Purethemes.net
 * @copyright  Purethemes.net
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @since      1.0.0
 * php version 7.3.9
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;

if (!defined('ABSPATH')) {
    // Exit if accessed directly.
    exit;
}

/**
 * Listing POI widget class.
 *
 * @since 1.0.0
 */
class ListingPOI extends Widget_Base
{

    /**
     * Retrieve the widget name.
     *
     * @since 1.0.0
     *
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name()
    {
        return 'listeo-listing-poi';
    }

    /**
     * Retrieve the widget title.
     *
     * @since 1.0.0
     *
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title()
    {
        return __('Listing POI', 'listeo_elementor');
    }

    /**
     * Retrieve the widget icon.
     *
     * @since 1.0.0
     *
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon()
    {
        return 'eicon-map-pin';
    }

    /**
     * Retrieve the list of categories the widget belongs to.
     *
     * Used to determine where to display the widget in the editor.
     *
     * Note that currently Elementor supports only one category.
     * When multiple categories passed, Elementor uses the first one.
     *
     * @since 1.0.0
     *
     * @access public
     *
     * @return array Widget categories.
     */
    public function get_categories()
    {
        return array('listeo-single');
    }

    /**
     * Register the widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     *
     * @access protected
     */
    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Content', 'listeo_elementor'),
            )
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'listeo_elementor'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Points of Interest', 'listeo_elementor'),
                'placeholder' => __('Type your title here', 'listeo_elementor'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     *
     * @access protected
     */
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        global $post;

        // Check if POI plugin is active
        if (!class_exists('Listeo_POI')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">' . __('Listeo POI plugin is not active. Please activate it to use this widget.', 'listeo_elementor') . '</div>';
            }
            return;
        }

        // Check if we're on a single listing page
        if (!is_singular('listing')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">' . __('This widget is only displayed on single listing pages.', 'listeo_elementor') . '</div>';
            }
            return;
        }

        // Check if POI is enabled in plugin settings
        if (!get_option('listeo_poi_enabled', 1)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">' . __('POI display is disabled in plugin settings.', 'listeo_elementor') . '</div>';
            }
            return;
        }

        // Check if listing has POI disabled
        $poi_disabled = get_post_meta($post->ID, '_poi_disabled', true);
        if ($poi_disabled) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">' . __('POI display is disabled for this listing.', 'listeo_elementor') . '</div>';
            }
            return;
        }

        // Check if API key is configured
        $api_key = get_option('listeo_poi_google_api_key', '');
        if (empty($api_key)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">' . __('Google Places API key not configured in POI plugin settings.', 'listeo_elementor') . '</div>';
            }
            return;
        }

        // Get listing coordinates
        $lat = get_post_meta($post->ID, '_geolocation_lat', true);
        $lng = get_post_meta($post->ID, '_geolocation_long', true);

        if (empty($lat) || empty($lng)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">' . __('This listing needs geocoded coordinates to display POI data.', 'listeo_elementor') . '</div>';
            }
            return;
        }

        // Override POI section title if custom title is set
        if (!empty($settings['title']) && $settings['title'] !== __('Points of Interest', 'listeo_elementor')) {
            add_filter('listeo_poi_section_title', function() use ($settings) {
                return esc_html($settings['title']);
            });
        }

        // Use existing POI frontend functionality
        if (class_exists('Listeo_POI_Frontend')) {
            $poi_frontend = new \Listeo_POI_Frontend();

            // Get listing address for directions
            $listing_address = get_post_meta($post->ID, '_address', true);

            // Pre-fetch cached POI data for all enabled categories
            $cached_poi_data = $poi_frontend->get_cached_poi_data($post->ID);

            // Load the POI template directly
            $template_path = LISTEO_POI_PLUGIN_DIR . 'templates/poi-display.php';
            if (file_exists($template_path)) {
                // Set up variables for template
                $listing_id = $post->ID;

                // Include the template
                include $template_path;
            }
        }
    }
}