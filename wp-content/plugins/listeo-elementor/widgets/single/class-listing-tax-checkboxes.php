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
class ListingTaxonomyCheckboxes extends Widget_Base
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
        return 'listeo-listing-taxonomy-checkboxes';
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
        return __('Listing Taxonomy Checkboxes', 'listeo_elementor');
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
        return 'eicon-checkbox';
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
            'taxonomy',
            [
                'label' => __('Taxonomy', 'listeo_elementor'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'default' => ['listing_feature'],
                'multiple' => true,
                'options' => $this->get_taxonomies(),

            ]
        );
        //add checkbox control
        $this->add_control(
            'show_title',
            [
                'label' => __('Show with titles', 'listeo_elementor'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'listeo_elementor'),
                'label_off' => __('Hide', 'listeo_elementor'),
                'return_value' => 'yes',
                'default' => 'yes',
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


        $taxonomies = $settings['taxonomy'];

        if (empty($taxonomies)) {
            return;
        }
        foreach ($taxonomies as $tax) {
            $term_list = get_the_terms($post->ID, $tax);
            $tax_obj = get_taxonomy($tax);
            $taxonomy = get_taxonomy_labels($tax_obj);


            if (!empty($term_list)) { ?>
                <?php if ($settings['show_title']) { ?>
                    <h2 class="listing-desc-headline"><?php echo $taxonomy->name; ?></h2>
                  
                <?php } ?>
                <ul class="listing-features checkboxes margin-top-0">
	<?php
    
		foreach ($term_list as $term) {
                    $svg_flag = false;
                    echo '';
                    $term_link = get_term_link($term);
                    if (is_wp_error($term_link))
                        continue;
                    $t_id = $term->term_id;
                    if (isset($t_id)) {
                        $_icon_svg = get_term_meta($t_id, '_icon_svg', true);
                        $_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
                    }
                    $icon = false;
                    if (isset($_icon_svg_image) && !empty($_icon_svg_image)) {
                        $svg_flag = true;
                        $icon = listeo_render_svg_icon($_icon_svg);
                        //$icon = '<img class="listeo-map-svg-icon" src="'.$_icon_svg_image[0].'"/>';


                    } else {

                        if (!$icon) {

                            $icon = get_term_meta($t_id, 'icon', true);
                        }
                    }

                    if (!empty($icon)) {

                        if ($svg_flag == true) {
                            echo '<li class="feature-has-icon"><span class="feature-svg-icon">' . $icon . '</span><a href="' . esc_url($term_link) . '">' . $term->name . '</a></li>';
                        } else {
                            echo '<li class="feature-has-icon faicon"><i class="' . $icon . '"></i> <a href="' . esc_url($term_link) . '">' . $term->name . '</a></li>';
                        }
                    } else {
                        echo '<li class="feature-no-icon"><a href="' . esc_url($term_link) . '">' . $term->name . '</a></li>';
                    }
                    $icon = false;
                }
		?>
		</ul>
            <?php }
        };

        ?>
<?php
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
}
