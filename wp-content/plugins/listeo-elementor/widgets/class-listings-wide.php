<?php

/**
 * Awesomesauce class.
 *
 * @category   Class
 * @package    ElementorAwesomesauce
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

if (!defined('ABSPATH')) {
    // Exit if accessed directly.
    exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class ListingsWide extends Widget_Base
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
        return 'listeo-listings-wide';
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
        return __('Listings Carousel Grid', 'listeo_elementor');
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
        return 'eicon-carousel-loop';
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

        //             'layout'        =>'standard',


        //             'relation'    => 'OR',
        //         
        //             '_property_type' => '',
        //             '_offer_type'   => '',
        //             'featured'      => '',
        //             'fullwidth'     => '',
        //             'style'         => '',
        //             'autoplay'      => '',
        //             'autoplayspeed'      => '3000',
        //             'from_vs'       => 'no',


        $this->start_controls_section(
            'section_query',
            array(
                'label' => __('Query', 'listeo_elementor'),
            )
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Listings to display', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 21,
                'step' => 1,
                'default' => 6,
            ]
        );


        $this->add_control(
            'orderby',
            [
                'label' => __('Order by', 'plugin-domain'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'none' =>  __('No order', 'listeo_elementor'),
                    'ID' =>  __('Order by post id. ', 'listeo_elementor'),
                    'author' =>  __('Order by author.', 'listeo_elementor'),
                    'title' =>  __('Order by title.', 'listeo_elementor'),
                    'name' =>  __(' Order by post name (post slug).', 'listeo_elementor'),
                    'type' =>  __(' Order by post type.', 'listeo_elementor'),
                    'date' =>  __(' Order by date.', 'listeo_elementor'),
                    'modified' =>  __(' Order by last modified date.', 'listeo_elementor'),
                    'parent' =>  __(' Order by post/page parent id.', 'listeo_elementor'),
                    'rand' =>  __(' Random order.', 'listeo_elementor'),
                    'comment_count' =>  __(' Order by number of commen', 'listeo_elementor'),
                    'event_date' =>  __(' Event date', 'listeo_elementor'),

                ],
            ]
        );
        $this->add_control(
            'order',
            [
                'label' => __('Order', 'plugin-domain'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'DESC' =>  __('Descending', 'listeo_elementor'),
                    'ASC' =>  __('Ascending. ', 'listeo_elementor'),


                ],
            ]
        );


        // Get dynamic listing type options for wide layout
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
                'rental' =>  __('Rentals. ', 'listeo_elementor'),
                'event' =>  __('Events. ', 'listeo_elementor'),
                'classifieds' => __('Classifieds', 'listeo_elementor'),
            );
        }

        $this->add_control(
            '_listing_type',
            [
                'label' => __(
                    'Show only Listing Types',
                    'listeo_elementor'
                ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'label_block' => true,
                'default' => '',
                'options' => $listing_type_options,
            ]
        );

        // Dynamic per-listing-type category controls. See
        // listeo-elementor.php for the helper definition.
        listeo_elementor_add_listing_taxonomy_controls( $this );

        $this->add_control(
            'exclude_posts',
            [
                'label' => __('Exclude listings', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'default' => [],
                'options' => [], // AJAX-loaded via editor.js
                'description' => __('Type to search listings', 'listeo_elementor'),
            ]
        );
        $this->add_control(
            'include_posts',
            [
                'label' => __('Include listings', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'default' => [],
                'options' => [], // AJAX-loaded via editor.js
                'description' => __('Type to search listings', 'listeo_elementor'),
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
            'relation',
            [
                'label' => __('Taxonomy Relation', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'OR',
                'options' => [
                    'OR' =>  __('OR (listings in one of selected taxonomies)', 'listeo_elementor'),
                    'AND' =>  __('AND  (listings in all of selected taxonomies) ', 'listeo_elementor'),


                ],
            ]
        );

        $this->add_control(
            'featured',
            [
                'label' => __('Show only featured listings', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'your-plugin'),
                'label_off' => __('Hide', 'your-plugin'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'verified',
            [
                'label' => __('Show only verified listings', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'your-plugin'),
                'label_off' => __('Hide', 'your-plugin'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->end_controls_section();
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Settings', 'listeo_elementor'),
            )
        );


       


        $this->add_control(
            'autoplay',
            [
                'label' => __('Auto Play', 'listeo_elementor'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'your-plugin'),
                'label_off' => __('Hide', 'your-plugin'),
                'return_value' => 'yes',
                'default' => 'yes',

            ]
        );


        $this->add_control(
            'autoplayspeed',
            array(
                'label'   => __('Auto Play Speed', 'listeo_elementor'),
                'type'    => Controls_Manager::NUMBER,
                'default' => __('Subtitle', 'listeo_elementor'),
                'min' => 1000,
                'max' => 10000,
                'step' => 500,
                'default' => 3000,
            )
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
        // 'limit'         =>'6',
        //           'layout'        =>'standard',
        //           'orderby'       => 'date',
        //           'order'         => 'DESC',
        //           'tax-listing_category'    => '',
        //           'tax-service_category'    => '',
        //           'tax-rental_category'    => '',
        //           'tax-event_category'    => '',
        //           'relation'    => 'OR',
        //           'exclude_posts' => '',
        //           'include_posts' => '',
        //           'feature'       => '',
        //           'region'        => '',
        //           '_property_type' => '',
        //           '_offer_type'   => '',
        //           'featured'      => '',
        //           'fullwidth'     => '',
        //           'style'         => '',
        //           'autoplay'      => '',
        //           'autoplayspeed'      => '3000',
        //           'from_vs'       => 'no',
        $settings = $this->get_settings_for_display();
        $template_loader = new \Listeo_Core_Template_Loader;

        $limit = $settings['limit'] ? $settings['limit'] : 3;
        $orderby = $settings['orderby'] ? $settings['orderby'] : 'title';
        $order = $settings['order'] ? $settings['order'] : 'ASC';
        $exclude_posts = $settings['exclude_posts'] ? $settings['exclude_posts'] : array();
        $include_posts = $settings['include_posts'] ? $settings['include_posts'] : array();


        //var_dump($settings);

        $output = '';
        $randID = rand(1, 99); // Get unique ID for carousel

        $meta_query = array();


        $args = array(
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            'tax_query'              => array(),
            'meta_query'              => array(),
        );
        
        if ($orderby == 'event_date') {
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_event_date_timestamp';
        }

        if (isset($settings['featured']) && $settings['featured'] == 'yes') {
            $args['meta_key'] = '_featured';
            $args['meta_value'] = 'on';
        }

        if (isset($settings['verified']) && $settings['verified'] == 'yes') {
            array_push($args['meta_query'], array(
                'key'     => '_verified',
                'value'   => 'on',
                'compare' => '='
            ));
        }

        if (!empty($exclude_posts)) {
            $exl = is_array($exclude_posts) ? $exclude_posts : array_filter(array_map('trim', explode(',', $exclude_posts)));
            $args['post__not_in'] = $exl;
        }

        if (!empty($include_posts)) {
            $inc = is_array($include_posts) ? $include_posts : array_filter(array_map('trim', explode(',', $include_posts)));
            $args['post__in'] = $inc;
        }

        if ($settings['feature']) {
            $feature = is_array($settings['feature']) ? $settings['feature'] : array_filter(array_map('trim', explode(',', $settings['feature'])));
            foreach ($feature as $key) {
                array_push($args['tax_query'], array(
                    'taxonomy' =>   'listing_feature',
                    'field'    =>   'slug',
                    'terms'    =>   $key,

                ));
            }
        }

        // Dynamic per-taxonomy filtering — replaces the 4 hardcoded
        // blocks above and now also honours custom listing types.
        // See listeo-elementor.php for the helper that builds the
        // tax_query clauses from the widget settings.
        $extra_clauses = listeo_elementor_build_listing_tax_query( $settings );
        if ( ! empty( $extra_clauses ) ) {
            $args['tax_query'] = array_merge( $args['tax_query'], $extra_clauses );
        }

        if ($settings['region']) {

            array_push($args['tax_query'], array(
                'taxonomy' =>   'region',
                'field'    =>   'slug',
                'terms'    =>   $settings['region'],
                'operator' =>   'IN'

            ));
        }
        $args['tax_query']['relation'] =  $settings['relation'];

        if ($settings['_listing_type']) {
            array_push($args['meta_query'], array(
                'key'     => '_listing_type',
                'value'   => $settings['_listing_type'],
                'compare' => '='

            ));
        }


        if (!empty($tags)) {
            $tags         = is_array($tags) ? $tags : array_filter(array_map('trim', explode(',', $tags)));
            $args['tag__in'] = $tags;
        }


        $i = 0;

        $wp_query = new \WP_Query($args);
        if (!class_exists('Listeo_Core_Template_Loader')) {
            return;
        }
        if($limit > $wp_query->found_posts){
            $limit = $wp_query->found_posts;
        }
        $modulus = $limit % 3;
        if ($modulus !== 0) {
            $posts_to_repeat = 3 - $modulus;
        } else {
            $posts_to_repeat = 0;
        }
        
        $template_loader = new \Listeo_Core_Template_Loader;
    $filesize = array('size' => 'listeo-gallery');
        ob_start();
?>
        <div class="fullgrid-slick-carousel listings-wide-carousel" <?php if ($settings['autoplay'] == 'yes') { ?>data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
            <?php

            // I need to modify wp_qyery so 

            if ($wp_query->have_posts()) {
                $counter = 0; // Initialize counter

                while ($wp_query->have_posts()) : $wp_query->the_post();

                    // If counter is divisible by 3, close the current group and start a new one
                    if ($counter % 3 == 0) {
                        if ($counter > 0) {
                            echo '</div></div>'; // Close the previous group
                        }
                        echo '<div class="fw-carousel-item">
                        <div class="taxonoomy-wide-grid-element">'; // Start a new group
                    }
                    if ($counter % 3 == 0) { ?>
                        <div class="slg-half">
                            <a data-grid-start-index="<?php echo $counter; ?>" href="<?php echo the_permalink(); ?>" class="slg-gallery-cover"><?php $template_loader->set_template_data($filesize)->get_template_part('content-listing-image'); ?></a>
                            <h4><?php the_title(); ?></h4>
                        </div><?php
                            }
                            if ($counter % 3 == 1) { ?>
                        <div class="slg-half">
                            <div class="slg-grid">
                                <div class="slg-grid-top">
                                    <div class="slg-grid-inner">
                                        <a data-grid-start-index="<?php echo $counter; ?>" href="<?php echo the_permalink(); ?>" class="slg-gallery-cover"><?php $template_loader->set_template_data($filesize)->get_template_part('content-listing-image'); ?></a>
                                        <h4><?php the_title(); ?></h4>
                                    </div>
                                </div>
                            <?php }
                            if ($counter % 3 == 2) { ?>
                                <div class="slg-grid-bottom">
                                    <div class="slg-grid-inner">
                                        <a data-grid-start-index="<?php echo $counter; ?>" href="<?php echo the_permalink(); ?>" class="slg-gallery-cover"><?php $template_loader->set_template_data($filesize)->get_template_part('content-listing-image'); ?></a>
                                        <h4><?php the_title(); ?></h4>
                                    </div>

                                </div>

                            </div>
                        </div>
                        <?php }


                            $counter++; // Increment counter

                        endwhile; // end of the loop.
                        if (
                            $posts_to_repeat > 0
                        ) {
                            // Get the original query arguments
                            $original_args = $wp_query->query_vars;
                 
                            // Modify the 'orderby' argument to 'rand'
                            $original_args['orderby'] = 'rand';
                            // exclude first post from the random order
                            // $original_args['post__not_in'] = array_merge($original_args['post__not_in'], array_map(function ($post) {
                            //     return $post->ID;
                            // }, $wp_query->posts));

                            // Create a new WP_Query object with the modified arguments
                            $wp_query = new \WP_Query($original_args);

                            $wp_query->rewind_posts(); // Wróć do początku listy postów

                            //modify posts for random order
                            
                            for (
                                $i = 0;
                                $i < $posts_to_repeat;
                                $i++
                            ) {
                                if ($wp_query->have_posts()) {
                                    $wp_query->the_post();

                                    if ($counter % 3 == 0) {
                                        if ($counter > 0) {
                                            echo '</div></div>'; // Close the previous group
                                        }
                                        echo '<div class="fw-carousel-item">
                        <div class="taxonoomy-wide-grid-element">'; // Start a new group
                                    }
                                    if ($counter % 3 == 0) { ?>
                                <div class="slg-half">
                                    <a data-grid-start-index="<?php echo $counter; ?>" href="<?php echo the_permalink(); ?>" class="slg-gallery-cover"><?php $template_loader->set_template_data($filesize)->get_template_part('content-listing-image'); ?></a>
                                    <h4><?php the_title(); ?></h4>
                                </div><?php
                                    }
                                    if ($counter % 3 == 1) { ?>
                                <div class="slg-half">
                                    <div class="slg-grid">
                                        <div class="slg-grid-top">
                                            <div class="slg-grid-inner">
                                                <a data-grid-start-index="<?php echo $counter; ?>" href="<?php echo the_permalink(); ?>" class="slg-gallery-cover"><?php $template_loader->set_template_data($filesize)->get_template_part('content-listing-image'); ?></a>
                                                <h4><?php the_title(); ?></h4>
                                            </div>
                                        </div>
                                    <?php }
                                    if ($counter % 3 == 2) { ?>
                                        <div class="slg-grid-bottom">
                                            <div class="slg-grid-inner">
                                                <a data-grid-start-index="<?php echo $counter; ?>" href="<?php echo the_permalink(); ?>" class="slg-gallery-cover"><?php $template_loader->set_template_data($filesize)->get_template_part('content-listing-image'); ?></a>
                                                <h4><?php the_title(); ?></h4>
                                            </div>

                                        </div>

                                    </div>
                                </div>
            <?php }
                                    $counter++;
                                }
                            }
                        }
                        //   echo '</div>'; // Close the last group

                    } ?>
        </div>
<?php wp_reset_postdata();
        wp_reset_query();

        echo ob_get_clean();
    }


    protected function get_terms($taxonomy)
    {
        $args = array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'all' // Ensure we get objects
        );

        $taxonomies = get_terms($args);

        $options = ['' => ''];

        if (!empty($taxonomies) && !is_wp_error($taxonomies)) :
            foreach ($taxonomies as $term) {
                if (is_object($term)) {
                    $options[$term->slug] = $term->name;
                }
            }
        endif;

        return $options;
    }

    protected function get_posts()
    {
        $posts = get_posts(
            array(
                'numberposts' => 199,
                'post_type' => 'listing',
                'suppress_filters' => true
            )
        );

        $options = ['' => ''];

        if (!empty($posts)) :
            foreach ($posts as $post) {
                $options[$post->ID] = get_the_title($post->ID);
            }
        endif;

        return $options;
    }
}
