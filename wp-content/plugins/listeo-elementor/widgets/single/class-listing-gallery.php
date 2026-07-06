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
class ListingGallery extends Widget_Base
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
        return 'listeo-listing-gallery';
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
        return __('Listing Gallery', 'listeo_elementor');
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
        return 'eicon-gallery-group';
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
        // 'title' 		=> 'Service Title',
        // 	    'url' 			=> '',
        // 	    'url_title' 	=> '',

        // 	   	'icon'          => 'im im-icon-Office',
        // 	    'type'			=> 'box-1', // 'box-1, box-1 rounded, box-2, box-3, box-4'
        // 	    'with_line' 	=> 'yes',


        $this->start_controls_section(
            'section_content',
            array(
                'label' => __('Content', 'listeo_elementor'),
            )
        );

        $this->add_control(
            'type',
            [
                'label' => __('Gallery type ', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'h3',
                'options' => [
                    'fullwidth' => __(
                        'Full Width',
                        'listeo_elementor'
                    ),
                    'content' => __('In Content', 'listeo_elementor'),

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
        $gallery = get_post_meta(get_the_ID(), '_gallery', true);

        $count_gallery = listeo_count_gallery_items($post->ID);

        if ($count_gallery < 4) {
            $settings['type'] = 'content';
        }
        if ($count_gallery == 1) {
            $settings['type'] = 'none';
        }

        if ($settings['type'] == 'none') {
            $gallery = get_post_meta($post->ID, '_gallery', true);
            if (!empty($gallery)) :

                foreach ((array) $gallery as $attachment_id => $attachment_url) {
                    $image = wp_get_attachment_image_src($attachment_id, 'listeo-gallery');
                    echo '<img src="' . esc_url($image[0]) . '" class="single-gallery margin-bottom-40" style="    margin: 0px auto; text-align: center; display: block;"></a>';
                }

            endif;
        } else if ($settings['type'] == 'fullwidth') {


            if (!empty($gallery)) : ?>

                <!-- Slider -->
            <?php
                echo '<div class="listing-slider mfp-gallery-container margin-bottom-0">';
                $count = 0;
                foreach ((array) $gallery as $attachment_id => $attachment_url) {
                    $image = wp_get_attachment_image_src($attachment_id, 'listeo-gallery');
                    $thumb = wp_get_attachment_image_src($attachment_id, 'medium');
                    if($image){
                        echo '<a href="' . esc_url($image[0]) . '" data-background-image="' . esc_attr($image[0]) . '" class="item mfp-gallery"></a>';
                    }
                    
                }
                echo '</div>';
            endif;
        } else {
            ?>
            <div class="listing-slider-small mfp-gallery-container margin-bottom-0">
                <?php

                foreach ((array) $gallery as $attachment_id => $attachment_url) {
                    $image = wp_get_attachment_image_src($attachment_id, 'listeo-gallery');
                    if ($image)
                        echo '<a href="' . esc_url($image[0]) . '" data-background-image="' . esc_attr($image[0]) . '" class="item mfp-gallery"></a>';
                }

                ?>
            </div>
<?php
        }
    }
}
