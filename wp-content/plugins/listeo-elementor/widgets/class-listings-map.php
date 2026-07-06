<?php

/**
 * listeo class.
 *
 * @category   Class
 * @package    Elementorlisteo
 * @subpackage WordPress
 * @author     Ben Marshall <me@benmarshall.me>
 * @copyright  2020 Ben Marshall
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @link       link(https://www.benmarshall.me/build-custom-elementor-widgets/,
 *             Build Custom Elementor Widgets)
 * @since      1.0.0
 * php version 7.3.9
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;

if (! defined('ABSPATH')) {
    // Exit if accessed directly.
    exit;
}

/**
 * listeo widget class.
 *
 * @since 1.0.0
 */
class ListingsMap extends Widget_Base
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
        return 'listeo-listings-map';
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
        return __('Listings Map', 'listeo_elementor');
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
        return 'eicon-editor-h1';
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
        return array('listeo');
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
        // Query Settings Section
        $this->start_controls_section(
            'section_query',
            array(
                'label' => __('Query Settings', 'listeo_elementor'),
            )
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Listings to display', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 99,
                'step' => 1,
                'default' => 50,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __('Order by', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' =>  __('Order by date.', 'listeo_elementor'),
                    'rand' =>  __('Random order.', 'listeo_elementor'),
                    'featured' =>  __('Featured', 'listeo_elementor'),
                    'highest' =>  __('Best rated', 'listeo_elementor'),
                    'views' =>  __('Most views', 'listeo_elementor'),
                    'reviewed' =>  __('Most reviews', 'listeo_elementor'),
                    'ID' =>  __('Order by post id.', 'listeo_elementor'),
                    'author' =>  __('Order by author.', 'listeo_elementor'),
                    'title' =>  __('Order by title.', 'listeo_elementor'),
                    'name' =>  __('Order by post name (post slug).', 'listeo_elementor'),
                    'modified' =>  __('Order by last modified date.', 'listeo_elementor'),
                    'parent' =>  __('Order by post/page parent id.', 'listeo_elementor'),
                    'comment_count' =>  __('Order by number of comments', 'listeo_elementor'),
                    'upcoming-event' =>  __('Event date', 'listeo_elementor'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Order', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'DESC' =>  __('Descending', 'listeo_elementor'),
                    'ASC' =>  __('Ascending.', 'listeo_elementor'),
                ],
            ]
        );

        // Get dynamic listing type options
        $listing_type_options = array('' => __('All', 'listeo_elementor'));
        if (function_exists('listeo_core_custom_listing_types')) {
            $custom_types_manager = listeo_core_custom_listing_types();
            $available_types = $custom_types_manager->get_listing_types(true);
            foreach ($available_types as $type) {
                $listing_type_options[$type->slug] = $type->name;
            }
        } else {
            // Fallback to old system
            $listing_type_options = array(
                '' =>  __('All', 'listeo_elementor'),
                'service' =>  __('Service', 'listeo_elementor'),
                'rental' =>  __('Rentals.', 'listeo_elementor'),
                'event' =>  __('Events.', 'listeo_elementor'),
                'classifieds' => __('Classifieds', 'listeo_elementor'),
            );
        }

        $this->add_control(
            '_listing_type',
            [
                'label' => __('Show only Listing Types', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'label_block' => true,
                'default' => '',
                'options' => $listing_type_options,
            ]
        );

        $this->add_control(
            'tax-listing_category',
            [
                'label' => __('Show only from categories', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'default' => [],
                'options' => $this->get_terms('listing_category'),
            ]
        );

        $this->add_control(
            'feature',
            [
                'label' => __('Show only listings with features', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'default' => [],
                'options' => $this->get_terms('listing_feature'),
            ]
        );

        $this->add_control(
            'region',
            [
                'label' => __('Show only listings from region', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'default' => [],
                'options' => $this->get_terms('region'),
            ]
        );

        $this->add_control(
            'keyword',
            array(
                'label'   => __('Keyword search', 'listeo_elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => '',
            )
        );

        $this->add_control(
            'location',
            array(
                'label'   => __('Location search', 'listeo_elementor'),
                'type'    => Controls_Manager::TEXT,
                'default' => '',
            )
        );

        $this->add_control(
            'featured',
            [
                'label' => __('Show only featured listings', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'listeo_elementor'),
                'label_off' => __('Hide', 'listeo_elementor'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->end_controls_section();

        // Map Settings Section
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Map Settings', 'listeo_elementor'),
            )
        );

        $this->add_control(
            'with_search_form',
            [
                'label' => __('Show search form', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('On', 'listeo_elementor'),
                'label_off' => __('Off', 'listeo_elementor'),
                'return_value' => 'yes',
                'default' => 'yes',

            ]
        );

        if (function_exists('listeo_get_search_forms_dropdown')) {
            $search_forms = listeo_get_search_forms_dropdown('fullwidth');
            $this->add_control(
                'home_banner_form',
                [
                    'label' => __('Search Form source', 'listeo_elementor'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $search_forms,
                    'default' => 'search_on_home_page',
                    'condition' => [
                        'with_search_form' => 'yes',
                    ],
                ]
            );
        }

        $this->add_control(
            'home_banner_form_action',
            [
                'label' => __('Form action', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'listing' => __('Listings results', 'listeo_elementor'),
                    'page' => __('Page', 'listeo_elementor'),
                    'custom' => __('Custom link', 'listeo_elementor'),
                ],
                'default' => 'listing',
                'condition' => [
                    'with_search_form' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'home_banner_form_action_custom',
            [
                'label' => __('Custom action', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'condition' => [
                    'home_banner_form_action' => 'custom',
                    'with_search_form' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'home_banner_form_action_page',
            [
                'label' => __('Page', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $this->listeo_get_pages_dropdown(),
                'default' => '',
                'condition' => [
                    'home_banner_form_action' => 'page',
                    'with_search_form' => 'yes',
                ],
            ]
        );
        // $this->add_control(
        //     'title',
        //     array(
        //         'label'   => __('Title', 'listeo_elementor'),
        //         'type'    => Controls_Manager::TEXT,
        //         'default' => __('Title', 'listeo_elementor'),
        //     )
        // );
        // $this->add_control(
        //     'subtitle',
        //     array(
        //         'label'   => __('Subtitle', 'listeo_elementor'),
        //         'type'    => Controls_Manager::TEXT,
        //         'default' => '',
        //     )
        // );

        // $this->end_controls_section();

        // $this->start_controls_section(
        //     'style_section',
        //     [
        //         'label' => __('Style Section', 'listeo_elementor'),
        //         'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        //     ]
        // );

        // $this->add_control(
        //     'type',
        //     [
        //         'label' => __('Element tag ', 'listeo_elementor'),
        //         'type' => \Elementor\Controls_Manager::SELECT,
        //         'default' => 'h3',
        //         'options' => [
        //             'h1' => __('H1', 'listeo_elementor'),
        //             'h2' => __('H2', 'listeo_elementor'),
        //             'h3' => __('H3', 'listeo_elementor'),
        //             'h4' => __('H4', 'listeo_elementor'),
        //             'h5' => __('H5', 'listeo_elementor'),
        //         ],
        //     ]
        // );


        // $this->add_control(
        //     'text_align',
        //     [
        //         'label' => __('Text align', 'listeo_elementor'),
        //         'type' => \Elementor\Controls_Manager::CHOOSE,
        //         'options' => [
        //             'left' => [
        //                 'title' => __('Left', 'listeo_elementor'),
        //                 'icon' => 'fa fa-align-left',
        //             ],
        //             'center' => [
        //                 'title' => __('Center', 'listeo_elementor'),
        //                 'icon' => 'fa fa-align-center',
        //             ],
        //             'right' => [
        //                 'title' => __('Right', 'listeo_elementor'),
        //                 'icon' => 'fa fa-align-right',
        //             ],
        //         ],
        //         'default' => 'center',
        //         'toggle' => true,
        //     ]
        // );

        // $this->add_control(
        //     'with_border',
        //     [
        //         'label' => __('With Border', 'listeo_elementor'),
        //         'type' => \Elementor\Controls_Manager::SWITCHER,
        //         'label_on' => __('Show', 'listeo_elementor'),
        //         'label_off' => __('Hide', 'listeo_elementor'),
        //         'return_value' => 'yes',
        //         'default' => 'yes',
        //     ]
        // );

        /* Add the options you'd like to show in this tab here */

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

        // Prepare query args from settings
        $limit = $settings['limit'] ? $settings['limit'] : 50;
        $orderby = $settings['orderby'] ? $settings['orderby'] : 'date';
        $order = $settings['order'] ? $settings['order'] : 'DESC';
        $featured = $settings['featured'] ? true : null;

        $args = array(
            'posts_per_page'    => $limit,
            'orderby'           => $orderby,
            'order'             => $order,
            'keyword'           => $settings['keyword'],
            'location'          => $settings['location'],
            'search_radius'     => 50, // Default radius to prevent undefined key warning
            'listeo_orderby'    => $orderby,
        );

        // Add listing type filter
        if (isset($settings['_listing_type']) && !empty($settings['_listing_type'])) {
            $args['_listing_type'] = $settings['_listing_type'];
        }

        // Add featured filter
        if ($featured) {
            $args['featured'] = true;
        }

        // Process main category filter
        if (isset($settings['tax-listing_category']) && !empty($settings['tax-listing_category'])) {
            if (is_array($settings['tax-listing_category'])) {
                if (count($settings['tax-listing_category']) == 1) {
                    $args['tax-listing_category'] = $settings['tax-listing_category'][0];
                } else {
                    $args['tax-listing_category'] = implode(',', $settings['tax-listing_category']);
                }
            }
        }

        if (isset($settings['feature']) && !empty($settings['feature'])) {
            if (is_array($settings['feature'])) {
                if (count($settings['feature']) == 1) {
                    $args['tax-listing_feature'] = $settings['feature'][0];
                } else {
                    $args['tax-listing_feature'] = implode(',', $settings['feature']);
                }
            }
        }

        if (isset($settings['region']) && !empty($settings['region'])) {
            if (is_array($settings['region'])) {
                if (count($settings['region']) == 1) {
                    $args['tax-region'] = $settings['region'][0];
                } else {
                    $args['tax-region'] = implode(',', $settings['region']);
                }
            }
        }

        // Generate filtered markers based on our query settings
        $filtered_markers = $this->generate_filtered_markers($args);
        $widget_id = 'listeo-map-' . $this->get_id();

?>
        <!-- Map
================================================== -->
        <div id="map-container" class="fullwidth-home-map">

            <?php if (\Elementor\Plugin::$instance->editor->is_edit_mode()) : ?>
                <!-- Elementor Editor Preview Notice -->
                <div style="height:500px; background:#f8f9fa; border:2px dashed #dee2e6; display:flex; align-items:center; justify-content:center; text-align:center; color:#6c757d; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
                    <div>
                        <div style="font-size:48px; margin-bottom:15px;">🗺️</div>
                        <h3 style="margin:0 0 10px 0; color:#495057;">Interactive Map</h3>
                        <p style="margin:0; font-size:14px;">
                            <?php echo __('Save and view page to see your map', 'listeo_elementor'); ?>
                        </p>
                    </div>
                </div>
            <?php else : ?>
                <?php
                // Enqueue map scripts only on frontend
                wp_enqueue_script('listeo_core-leaflet');
                wp_enqueue_script('listeo_core-leaflet-geocoder');
                wp_enqueue_script('listeo-big-leaflet', get_template_directory_uri() . '/js/listeo.big.leaflet.min.js', array('jquery', 'listeo-custom', 'listeo_core-leaflet'), '1.0', false);

                // Pass our filtered markers to JavaScript
                wp_localize_script('listeo-big-leaflet', 'listeo_big_map', $filtered_markers);

                $map_zoom = get_option('listeo_map_zoom_global', 9);
                ?>
                <div id="bigmap" data-map-zoom="<?php echo esc_attr($map_zoom); ?>" style="height:500px;"><!-- map goes here --></div>
            <?php endif; ?>

            <div class="main-search-inner">

                <?php if ($settings['with_search_form'] == 'yes') : ?>
                    <div class="container">
                        <div class="row">
                            <div class="col-md-12">
                                <?php
                                $home_banner_form_action_page = $settings['home_banner_form_action_page'];
                                $home_banner_form_action_custom = $settings['home_banner_form_action_custom'];
                                $home_banner_form_action = $settings['home_banner_form_action'];
                                if ($home_banner_form_action == 'page' && !empty($home_banner_form_action_page)) {
                                    $home_banner_form_action = get_permalink($home_banner_form_action_page);
                                } else if ($home_banner_form_action == 'custom' && !empty($home_banner_form_action_custom)) {
                                    $home_banner_form_action = $home_banner_form_action_custom;
                                } else {
                                    $home_banner_form_action = get_post_type_archive_link('listing');
                                }

                                ?>
                                <?php

                                echo do_shortcode('[listeo_search_form action=' . $home_banner_form_action . ' source="' . $settings['home_banner_form'] . '" custom_class="main-search-form"]') ?>

                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <a href="#" id="show-map-button" class="show-map-button" data-enabled="<?php esc_attr_e('Show Map ', 'listeo'); ?>" data-disabled="<?php esc_attr_e('Hide Map ', 'listeo'); ?>"><?php esc_html_e('Show Map ', 'listeo') ?></a>

            <!-- Scroll Enabling Button -->
            <a href="#" id="scrollEnabling" title="<?php esc_attr_e('Enable or disable scrolling on map', 'listeo') ?>"><?php esc_html_e('Enable Scrolling', 'listeo') ?></a>

        </div>

<?php
    }

    /**
     * Render the widget output in the editor.
     *
     * Written as a Backbone JavaScript template and used to generate the live preview.
     *
     * @since 1.0.0
     *
     * @access protected
     */
    /**
     * Generate filtered markers for the map based on widget query settings
     */
    private function generate_filtered_markers($args)
    {
        // Get filtered listings using Listeo Core
        $listeo_core_query = \Listeo_Core_Listing::get_real_listings(apply_filters('listeo_core_output_defaults_args', $args));

        $markers = array();

        if ($listeo_core_query->have_posts()) {
            while ($listeo_core_query->have_posts()) {
                $listeo_core_query->the_post();

                $id = get_the_ID();
                $lat = get_post_meta($id, '_geolocation_lat', true);
                $lng = get_post_meta($id, '_geolocation_long', true);

                if (!empty($lat) && !empty($lng)) {
                    // Validate and normalize coordinates before adding to markers
                    $validated_coords = $this->validate_coordinates($lat, $lng, $id);

                    if ($validated_coords !== null) {
                        // Get the marker icon (same logic as ListeoMaps)
                        $icon = $this->get_marker_icon($id);

                        // Get the infobox content (same logic as ListeoMaps)
                        $ibcontent = $this->get_infobox_content($id);

                        // Store the full marker data with validated coordinates
                        $marker = array(
                            'id' => $id,
                            'lat' => $validated_coords['lat'],
                            'lng' => $validated_coords['lng'],
                            'icon' => $icon,
                            'ibcontent' => $ibcontent
                        );

                        $markers[] = $marker;
                    }
                }
            }
        }

        wp_reset_postdata();
        return $markers;
    }

    /**
     * Validate and normalize latitude/longitude coordinates
     *
     * @param mixed $lat Latitude value (can be string, float, or int)
     * @param mixed $lng Longitude value (can be string, float, or int)
     * @param int $listing_id Listing ID for error logging
     * @return array|null Array with validated lat/lng on success, null on failure
     */
    private function validate_coordinates($lat, $lng, $listing_id = 0)
    {
        // Check for null, empty, or invalid input
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            error_log(sprintf(
                'Listeo Map Widget: Listing #%d has empty coordinates (lat: %s, lng: %s)',
                $listing_id,
                var_export($lat, true),
                var_export($lng, true)
            ));
            return null;
        }

        // Convert to float, handling various formats
        // Replace comma with period for locale compatibility
        $lat_str = is_numeric($lat) ? (string)$lat : trim(str_replace(',', '.', (string)$lat));
        $lng_str = is_numeric($lng) ? (string)$lng : trim(str_replace(',', '.', (string)$lng));

        // Convert to float
        $lat_float = (float)$lat_str;
        $lng_float = (float)$lng_str;

        // Validate that conversion was successful (not NaN or infinite)
        if (!is_finite($lat_float) || !is_finite($lng_float)) {
            error_log(sprintf(
                'Listeo Map Widget: Listing #%d has invalid numeric coordinates (lat: %s -> %s, lng: %s -> %s)',
                $listing_id,
                var_export($lat, true),
                var_export($lat_float, true),
                var_export($lng, true),
                var_export($lng_float, true)
            ));
            return null;
        }

        // Validate latitude range (-90 to 90)
        if ($lat_float < -90 || $lat_float > 90) {
            error_log(sprintf(
                'Listeo Map Widget: Listing #%d has latitude out of range: %s (must be between -90 and 90)',
                $listing_id,
                $lat_float
            ));
            return null;
        }

        // Validate longitude range (-180 to 180)
        if ($lng_float < -180 || $lng_float > 180) {
            error_log(sprintf(
                'Listeo Map Widget: Listing #%d has longitude out of range: %s (must be between -180 and 180)',
                $listing_id,
                $lng_float
            ));
            return null;
        }

        // Check for suspicious zero coordinates (0,0 = Gulf of Guinea)
        if ($lat_float == 0 && $lng_float == 0) {
            error_log(sprintf(
                'Listeo Map Widget: Listing #%d has suspicious coordinates (0,0) - likely missing data',
                $listing_id
            ));
            return null;
        }

        // Return validated and normalized coordinates
        return array(
            'lat' => $lat_float,
            'lng' => $lng_float
        );
    }

    /**
     * Get marker icon for a listing.
     *
     * Delegates to the core helper `get_listing_marker_icons()` so this widget stays in sync
     * with archive/single map icon logic. The previous local copy of this function only
     * looked at `listing_category` term meta and the per-listing `_icon` post meta - it did
     * NOT honor `{listing_type}_category` taxonomy icons (used by custom listing types like
     * service_category, rental_category) nor custom listing type icons set via the Listeo
     * Editor. Result: listings using a custom listing type rendered fine on the standard
     * `/listings` map but fell back to a generic pin on the Elementor "big map" widget.
     */
    private function get_marker_icon($id)
    {
        if (function_exists('get_listing_marker_icons')) {
            $icons = get_listing_marker_icons($id);
            if (is_array($icons)) {
                if (!empty($icons['has_svg']) && !empty($icons['icon_svg'])) {
                    return $icons['icon_svg'];
                }
                if (!empty($icons['icon'])) {
                    return '<i class="' . esc_attr($icons['icon']) . '"></i>';
                }
            }
        }

        return '<i class="im im-icon-Map-Marker2"></i>';
    }

    /**
     * Get infobox content for a listing (copied from ListeoMaps)
     */
    private function get_infobox_content($id)
    {
        ob_start();
?>
        <a href="<?php echo get_permalink($id); ?>" class="leaflet-listing-img-container">
            <div class="infoBox-close"><i class="fa fa-times"></i></div>
            <?php
            if (has_post_thumbnail($id)) {
                echo get_the_post_thumbnail($id, 'listeo-listing-grid');
            } else {
                $gallery = get_post_meta($id, '_gallery', true);
                if (!empty($gallery)) {
                    $ids = array_keys($gallery);
                    $image = wp_get_attachment_image_src($ids[0], 'listeo-listing-grid');
                    echo '<img src="' . esc_url($image[0]) . '">';
                } else {
                    echo '<img src="' . get_listeo_core_placeholder_image() . '" >';
                }
            }
            ?>
            <div class="leaflet-listing-item-content">
                <h3><?php echo get_the_title($id); ?></h3>
                <span>
                    <?php
                    $friendly_address = get_post_meta($id, '_friendly_address', true);
                    $address = get_post_meta($id, '_address', true);
                    echo (!empty($friendly_address)) ? $friendly_address : $address;
                    ?>
                </span>
            </div>
        </a>

        <?php
        // Use the new combined rating display function and proper rating format
        if (!get_option('listeo_disable_reviews')) {
            if (function_exists('listeo_get_rating_display')) {
                $rating_data = listeo_get_rating_display($id);
                $rating = $rating_data['rating'];
                $review_count = $rating_data['count'];
            }
        }
        ?>
        <div class="leaflet-listing-content">
            <div class="listing-title">
                <?php if (isset($rating) && $rating > 0) : ?>
                    <?php echo listeo_generate_star_rating($rating, $review_count); ?>
                <?php endif; ?>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    function listeo_get_pages_dropdown()
    {
        $pages = get_pages();
        $options = ['' => ''];
        if (!empty($pages)) :
            foreach ($pages as $page) {
                $options[$page->ID] = $page->post_title;
            }
        endif;
        return $options;
    }

    protected function get_terms($taxonomy)
    {
        $taxonomies = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

        $options = ['' => ''];

        if (!empty($taxonomies)) :
            foreach ($taxonomies as $term) {
                if (is_object($term)) {
                    $options[$term->slug] = $term->name;
                }
            }
        endif;

        return $options;
    }
}
