<?php

/**
 * Listing Nearby widget class.
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
 * Listing Nearby widget class.
 *
 * @since 1.0.0
 */
class ListingNearby extends Widget_Base
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
        return 'listeo-listing-nearby';
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
        return __('Listing Nearby', 'listeo_elementor');
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
                'default' => __('Nearby Listings', 'listeo_elementor'),
                'placeholder' => __('Type your title here', 'listeo_elementor'),
            ]
        );

        $this->add_control(
            'radius',
            [
                'label' => __('Radius', 'listeo_elementor'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 1000,
                'step' => 1,
                'default' => 50,
                'description' => __('Search radius for nearby listings', 'listeo_elementor'),
            ]
        );

        $this->add_control(
            'unit',
            [
                'label' => __('Distance Unit', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'km',
                'options' => [
                    'km' => __('Kilometers', 'listeo_elementor'),
                    'miles' => __('Miles', 'listeo_elementor'),
                ],
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Limit', 'listeo_elementor'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 50,
                'step' => 1,
                'default' => 6,
                'description' => __('Maximum number of nearby listings to show', 'listeo_elementor'),
            ]
        );

        $this->add_control(
            'style',
            [
                'label' => __('Display Style', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'compact',
                'options' => [
                    'compact' => __('Compact', 'listeo_elementor'),
                    'grid' => __('Grid', 'listeo_elementor'),
                ],
            ]
        );

        $this->add_control(
            'show_distance',
            [
                'label' => __('Show Distance', 'listeo_elementor'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'listeo_elementor'),
                'label_off' => __('Hide', 'listeo_elementor'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'taxonomy_filter',
            [
                'label' => __('Filter by Taxonomy', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all' => __('All Categories', 'listeo_elementor'),
                    'listing_category' => __('Same Category', 'listeo_elementor'),
                    'region' => __('Same Region', 'listeo_elementor'),
                ],
                'description' => __('Filter nearby listings by taxonomy', 'listeo_elementor'),
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'carousel',
                'options' => [
                    'carousel' => __('Carousel', 'listeo_elementor'),
                    'grid' => __('Grid', 'listeo_elementor'),
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'listeo_elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'listeo_elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .desc-headline' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .desc-headline',
            ]
        );

        $this->add_responsive_control(
            'title_margin',
            [
                'label' => __('Title Margin', 'listeo_elementor'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .desc-headline' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
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

        // Get settings with defaults
        $title = !empty($settings['title']) ? $settings['title'] : __('Nearby Listings', 'listeo_elementor');
        $radius = !empty($settings['radius']) ? intval($settings['radius']) : 50;
        $unit = !empty($settings['unit']) ? $settings['unit'] : 'km';
        $limit = !empty($settings['limit']) ? intval($settings['limit']) : 6;
        $style = !empty($settings['style']) ? $settings['style'] : 'compact';
        $show_distance = $settings['show_distance'] === 'yes';
        $taxonomy_filter = !empty($settings['taxonomy_filter']) ? $settings['taxonomy_filter'] : 'all';
        $layout = !empty($settings['layout']) ? $settings['layout'] : 'carousel';

        // Prepare additional query args based on taxonomy filter
        $additional_args = array();

        // Add taxonomy filtering if not set to "all"
        if ($taxonomy_filter !== 'all' && $post) {
            $terms = get_the_terms($post->ID, $taxonomy_filter);
            if ($terms && !is_wp_error($terms)) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                $additional_args['tax_query'] = array(
                    array(
                        'taxonomy' => $taxonomy_filter,
                        'field' => 'id',
                        'terms' => $term_ids,
                        'operator' => 'IN'
                    )
                );
            }
        }

        // Set posts per page limit (use larger number for query, we'll limit display later)
        $query_limit = ($limit > 0) ? min($limit * 3, 50) : 20; // Query more than needed to account for distance filtering
        $additional_args['posts_per_page'] = $query_limit;

        // Get nearby listings using the cached function
        $nearby_listings = array();
        if ($post && function_exists('listeo_get_cached_nearby_listings')) {
            $nearby_listings = listeo_get_cached_nearby_listings($post->ID, $radius, $unit, $additional_args);
        }

        // Apply limit to final results if specified
        if ($limit > 0 && count($nearby_listings) > $limit) {
            $nearby_listings = array_slice($nearby_listings, 0, $limit);
        }

        // Display nearby listings if we have any
        if (!empty($nearby_listings)) { ?>
            <div class="listeo-nearby-listings-widget">
                <?php if (!empty($title)) : ?>
                    <h3 class="desc-headline no-border margin-bottom-35 margin-top-60">
                        <?php echo esc_html($title); ?>
                    </h3>
                <?php endif; ?>

                <?php if ($layout === 'carousel') : ?>
                    <div class="simple-slick-carousel" data-slick='{"autoplay": true,"slidesToShow": 2, "responsive":[{"breakpoint": 768,"settings":{"slidesToShow": 1}}]}'>
                        <?php
                        foreach ($nearby_listings as $nearby_item) {
                            $nearby_post = $nearby_item['post'];
                            $distance = $nearby_item['distance'];

                            // Set up global $post for template
                            $GLOBALS['post'] = $nearby_post;
                            setup_postdata($nearby_post);

                            // Store distance in global variable for template access
                            if ($show_distance) {
                                $GLOBALS['listeo_current_distance'] = $distance;
                                $GLOBALS['listeo_distance_unit'] = $unit;
                            }
                            ?>
                            <div class="fw-carousel-item">
                                <?php
                                $template_loader = new \Listeo_Core_Template_Loader;
                                $template_loader->get_template_part('content-listing-' . $style);
                                ?>
                            </div>
                            <?php
                        }
                        wp_reset_postdata();
                        wp_reset_query();
                        // Clear distance globals
                        unset($GLOBALS['listeo_current_distance']);
                        unset($GLOBALS['listeo_distance_unit']);
                        ?>
                    </div>
                <?php else : // Grid layout ?>
                    <div class="listeo-nearby-grid">
                        <?php
                        foreach ($nearby_listings as $nearby_item) {
                            $nearby_post = $nearby_item['post'];
                            $distance = $nearby_item['distance'];

                            // Set up global $post for template
                            $GLOBALS['post'] = $nearby_post;
                            setup_postdata($nearby_post);

                            // Store distance in global variable for template access
                            if ($show_distance) {
                                $GLOBALS['listeo_current_distance'] = $distance;
                                $GLOBALS['listeo_distance_unit'] = $unit;
                            }
                            ?>
                            <div class="nearby-listing-item">
                                <?php
                                $template_loader = new \Listeo_Core_Template_Loader;
                                $template_loader->get_template_part('content-listing-' . $style);
                                ?>
                            </div>
                            <?php
                        }
                        wp_reset_postdata();
                        wp_reset_query();
                        // Clear distance globals
                        unset($GLOBALS['listeo_current_distance']);
                        unset($GLOBALS['listeo_distance_unit']);
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($layout === 'grid') : ?>
                <style>
                .listeo-nearby-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                @media (max-width: 768px) {
                    .listeo-nearby-grid {
                        grid-template-columns: 1fr;
                    }
                }
                </style>
            <?php endif; ?>
        <?php } else {
            // Show message in editor if no nearby listings found
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-info">' . __('No nearby listings found. This widget will display nearby listings on the frontend when viewing a single listing page.', 'listeo_elementor') . '</div>';
            }
        } ?>

        <?php
    }
}