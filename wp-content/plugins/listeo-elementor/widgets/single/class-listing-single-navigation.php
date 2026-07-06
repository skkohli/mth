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
class ListingSingleNavigation extends Widget_Base
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
        return 'listeo-listing-single-navigation';
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
        return __('Listing Single Navigation', 'listeo_elementor');
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
        return 'eicon-nav-menu';
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

        // add elementor repeater control

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'title',
            [
                'label' => __('Section title', 'plugin-domain'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('List Title', 'plugin-domain'),
                'label_block' => true,
            ]
        );


        $repeater->add_control(
            'hook',
            [
                'label' => __('Section Hook (id of element to scroll to) ', 'plugin-domain'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'show_label' => true,
                'label_block' => true,
            ]
        );
        $this->add_control(
            'list',
            [
                'label' => __('Custom Sections', 'plugin-domain'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'prevent_empty' => false,
                'title_field' => '{{{ title }}}',
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
        $nav_list_id = 'nav-list-dynamic';
?>
        <div id="listing-nav" class="listing-nav-container">
            <ul id="<?php echo esc_attr($nav_list_id); ?>" class="listing-nav dynamic-nav">
                <?php do_action('listeo/single-listing/navigation-start'); ?>
                <li class="nav-listing-overview"><a href="#listing-overview" class="active"><?php esc_html_e('Overview', 'listeo_elementor'); ?></a></li>
                <?php
                foreach ($settings['list'] as $item) { ?>
                    <li class="nav-listing-<?php echo sanitize_title($item['title']); ?>"><a href="<?php echo $item['hook']; ?>" ><?php echo $item['title']; ?></a></li>
                <?php }

                ?>
                <?php
                do_action('listeo/single-listing/navigation-end');
                ?>


            </ul>
        </div>
        <script>

        </script>
<?php
    }
}
