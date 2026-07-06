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
class TaxonomyWide extends Widget_Base
{



    // public function __construct( $data = array(), $args = null ) {
    // 	parent::__construct( $data, $args );

    // 	wp_register_script( 'listeo-taxonomy-carousel-elementor', plugins_url( '/assets/tax-carousel/tax-carousel.js', ELEMENTOR_LISTEO ), array(), '1.0.0' );
    // }


    // public function get_script_depends() {
    // 	  $scripts = ['listeo-taxonomy-carousel-elementor'];

    // 	  return $scripts;
    // }
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
        return 'listeo-taxonomy-wide';
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
        return __('Taxonomy Carousel Grid', 'listeo_elementor');
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
        return 'eicon-gallery-grid';
    }


    // public function get_script_depends() {
    //    return [ 'listeo-taxonomy-carousel-script' ];
    //  }


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
        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Content', 'listeo_elementor'),
            )
        );
        // 	'taxonomy' => '',
        // 'xd' 	=> '',
        // 'only_top' 	=> 'yes',
        // 'autoplay'      => '',
        //          'autoplayspeed'      => '3000',

        $this->add_control(
            'taxonomy',
            [
                'label' => __('Taxonomy', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'default' => 'listing_category',
                'options' => $this->get_taxonomies(),

            ]
        );

        $taxonomy_names = get_object_taxonomies('listing', 'object');
        foreach ($taxonomy_names as $key => $value) {

            $this->add_control(
                $value->name . '_include',
                [
                    'label' => __('Include listing from ' . $value->label, 'listeo_elementor'),
                    'type' => Controls_Manager::SELECT2,
                    'label_block' => true,
                    'default' => [],
                    'multiple' => true,
                    'options' => $this->get_terms($value->name),
                    'condition' => [
                        'taxonomy' => $value->name,
                    ],
                ]
            );
            $this->add_control(
                $value->name . '_exclude',
                [
                    'label' => __('Exclude listings from ' . $value->label, 'listeo_elementor'),
                    'type' => Controls_Manager::SELECT2,
                    'label_block' => true,
                    'default' => [],
                    'multiple' => true,
                    'options' => $this->get_terms($value->name),
                    'condition' => [
                        'taxonomy' => $value->name,
                    ],
                ]
            );
        }

        $this->add_control(
            'number',
            [
                'label' => __('Terms to display', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 99,
                'step' => 1,
                'default' => 6,
            ]
        );

        $this->add_control(
            'only_top',
            [
                'label' => __('Show only top terms', 'listeo_elementor'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'your-plugin'),
                'label_off' => __('Hide', 'your-plugin'),
                'return_value' => 'yes',
                'default' => 'yes',

            ]
        );


        $this->add_control(
            'show_counter',
            [
                'label' => __('Show listings counter', 'listeo_elementor'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'your-plugin'),
                'label_off' => __('Hide', 'your-plugin'),
                'return_value' => 'yes',
                'default' => 'yes',

            ]
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
        // $taxonomy_names = get_object_taxonomies( 'listing','object' );

        // foreach ($taxonomy_names as $key => $value) {
        // 	$shortcode_atts[$value->name.'_include'] = '';
        // 	$shortcode_atts[$value->name.'_exclude'] = '';
        // }


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

        $taxonomy_names = get_object_taxonomies('listing', 'object');

        $taxonomy = $settings['taxonomy'];
        $query_args = array(
            'include' => $settings[$taxonomy . '_include'],
            'exclude' => $settings[$taxonomy . '_exclude'],
            'hide_empty' => false,
            'number' => $settings['number'],
            'meta_query' => array(
                array(
                    'key'     => '_cover',
                    'compare' => 'EXISTS', // Only get terms where the '_cover' meta key exists
                ),
                array(
                    'key'     => '_cover',
                    'value'   => '', // Only get terms where the '_cover' meta key is not empty
                    'compare' => '!=',
                ),
            ),
        );

        if ($settings['only_top'] == 'yes') {
            $query_args['parent'] = 0;
        }
        $terms = get_terms($settings['taxonomy'], $query_args);
        // Ensure minimum of 12 items for visual consistency when user wants 12 or fewer
        // But respect exact user settings when they want more than 12
        if (!empty($terms) && !is_wp_error($terms)) {
            $terms_count = count($terms);
            $desired_count = $settings['number'];

            // Only duplicate if user wants 12 or fewer terms
            if ($desired_count <= 12 && $terms_count < 12) {
                while (count($terms) < 12) {
                    $terms = array_merge($terms, $terms);
                }
                // Trim to exactly 12 if we exceeded during duplication
                if (count($terms) > 12) {
                    $terms = array_slice($terms, 0, 12);
                }
            }
            // If user wants more than 12, just show what we have (no duplication)
        }





        if (!empty($terms) && !is_wp_error($terms)) {
?>

            <div class="fullgrid-slick-carousel taxonomy-wide-carousel" <?php if ($settings['autoplay'] == 'yes') { ?>data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
                <!-- Item -->

                <?php
                // i need to loop the terms array but in one lopp I need to get 3 terms and then the next 3 terms

                for ($i = 0; $i < count($terms); $i += 3) {
                    $term1 = isset($terms[$i]) ? $terms[$i] : null;
                    $term2 = isset($terms[$i + 1]) ? $terms[$i + 1] : null;
                    $term3 = isset($terms[$i + 2]) ? $terms[$i + 2] : null;

                    // Now you can use $term1, $term2, and $term3
                    // Be sure to check if they are not null before using them
                    if ($term1 == null && $term2 == null && $term3 == null) {
                        break;
                    }
                    $items = 0;

                    if ($term1 != null) {
                        $cover_id_1     = get_term_meta($term1->term_id, '_cover', true);
                        $cover_1        = wp_get_attachment_image_src($cover_id_1, 'listeo-blog-post');
                        $term_name_1  = $term1->name;
                        // Create URL with only the child term slug, not full hierarchy
                        $taxonomy_base_1 = str_replace('_', '-', $settings['taxonomy']);
                        $term_url_1 = home_url($taxonomy_base_1 . '/' . $term1->slug . '/');
                        $term_url_1 = get_term_link($term1);
                        if ($cover_1 != null) {
                            $items++;
                        }
                    }
                    

                    if ($term2 != null) {
                        $cover_id_2     = get_term_meta($term2->term_id, '_cover', true);
                        $cover_2        = wp_get_attachment_image_src($cover_id_2, 'listeo-blog-post');
                        $term_name_2 = $term2->name;
                        // Create URL with only the child term slug, not full hierarchy
                        $taxonomy_base_2 = str_replace('_', '-', $settings['taxonomy']);
                        $term_url_2 = home_url($taxonomy_base_2 . '/' . $term2->slug . '/');
                        $term_url_2 = get_term_link($term2);

                        if ($cover_2 != null) {
                            $items++;
                        }
                    }

                    if ($term3 != null) {
                        $cover_id_3     = get_term_meta($term3->term_id, '_cover', true);
                        $cover_3        = wp_get_attachment_image_src($cover_id_3, 'listeo-blog-post');
                        $term_name_3 = $term3->name;
                        // Create URL with only the child term slug, not full hierarchy
                        $taxonomy_base_3 = str_replace('_', '-', $settings['taxonomy']);
                        $term_url_3 = home_url($taxonomy_base_3 . '/' . $term3->slug . '/');
                        $term_url_3 = get_term_link($term3);

                        if ($cover_3 != null) {
                            $items++;
                        }
                    }

                ?>
                    <div class="fw-carousel-item">

                        <div class="taxonoomy-wide-grid-element">
                            <?php if ($items == 1) { ?>
                                <a href="<?php echo $term_url_1; ?>" class="slg-gallery-cover"><img src="<?php echo $cover_1[0]; ?>" /> </a>
                                <h4><?php echo $term_name_1; ?></h4>

                            <?php } else { ?>
                                <?php if ($items >= 1) { ?>
                                    <div class="slg-half">
                                        <a data-grid-start-index="0" href="<?php echo $term_url_1;; ?>" class="slg-gallery-cover"><img src="<?php echo $cover_1[0]; ?>" /></a>
                                        <h4><?php echo $term_name_1; ?></h4>
                                    </div>
                                <?php } ?>
                                <?php if ($items > 2) { ?>
                                    <div class="slg-half">
                                        <div class="slg-grid">
                                            <div class="slg-grid-top">
                                                <div class="slg-grid-inner">
                                                    <a data-grid-start-index="1" href="<?php echo $term_url_2; ?>" class="slg-gallery-cover"><img src="<?php echo $cover_2[0]; ?>" /></a>
                                                    <h4><?php echo $term_name_2; ?></h4>
                                                </div>
                                            </div>
                                            <?php if ($items == 3) { ?>
                                                <div class="slg-grid-bottom">
                                                    <div class="slg-grid-inner">
                                                        <a data-grid-start-index="4" href="<?php echo $term_url_3; ?>" class="slg-gallery-cover"><img src="<?php echo $cover_3[0]; ?>" /></a>
                                                        <h4><?php echo $term_name_3; ?></h4>
                                                    </div>

                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <div class="slg-half">
                                        <a data-grid-start-index="1" href="<?php echo $term_url_2; ?>" class="slg-gallery-cover"><img src="<?php echo $cover_2[0]; ?>" /></a>
                                        <h4><?php echo $term_name_2; ?></h4>
                                    </div>
                            <?php }
                            } ?>
                        </div>
                    </div>

                <?php } ?>
            </div>
<?php }
    }



    protected function get_taxonomies()
    {
        $taxonomies = get_object_taxonomies('listing', 'objects');

        $options = ['' => ''];

        foreach ($taxonomies as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->label;
        }

        return $options;
    }

    protected function get_terms($taxonomy)
    {
        $taxonomies = get_terms($taxonomy, array(
            'hide_empty' => false,
        ));
        $options = ['' => ''];

        if (!empty($taxonomies)) :
            foreach ($taxonomies as $taxonomy) {
                $options[$taxonomy->term_id] = $taxonomy->name;
            }
        endif;

        return $options;
    }
}
