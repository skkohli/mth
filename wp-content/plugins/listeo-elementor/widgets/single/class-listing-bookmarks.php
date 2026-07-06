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
class ListingBookmarks extends Widget_Base
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
        return 'listeo-listing-bookmarks';
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
        return __('Listing Bookmarks', 'listeo_elementor');
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
        return 'eicon-check-circle';
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
        ?>
        <div class="listing-widget widget listeo_core widget_buttons">
										<div class="listing-share margin-top-40 margin-bottom-40 no-border">
											<?php
											$classObj = new \Listeo_Core_Bookmarks;
											$nonce = wp_create_nonce("listeo_core_bookmark_this_nonce");

											if ($classObj->check_if_added($post->ID)) {                                                      ?>
												<button onclick="window.location.href='<?php echo get_permalink(get_option('listeo_bookmarks_page')) ?>'" class="like-button save liked"><span class="like-icon liked"></span> <b class="bookmark-btn-title"><?php esc_html_e('Bookmarked', 'listeo_core') ?></b>
												</button>
												<?php } else {
												if (is_user_logged_in()) { ?>
													<button class="like-button listeo_core-bookmark-it" data-post_id="<?php echo esc_attr($post->ID); ?>" data-confirm="<?php esc_html_e('Bookmarked!', 'listeo_core'); ?>" data-nonce="<?php echo esc_attr($nonce); ?>"><span class="like-icon"></span> <b class="bookmark-btn-title"><?php esc_html_e('Bookmark this listing', 'listeo_core') ?></b>
													</button>
													<?php } else {
													$popup_login = get_option('listeo_popup_login', 'ajax');
													if ($popup_login == 'ajax') { ?>
														<button href="#sign-in-dialog" class="like-button-notlogged sign-in popup-with-zoom-anim"><span class="like-icon"></span> <b class="bookmark-btn-title"><?php esc_html_e('Login To Bookmark Items', 'listeo_core') ?></b></button>
													<?php } else {
														$login_page = get_option('listeo_profile_page'); ?>
														<a href="<?php echo esc_url(get_permalink($login_page)); ?>" class="like-button-notlogged"><span class="like-icon"></span> <b class="bookmark-btn-title"><?php esc_html_e('Login To Bookmark Items', 'listeo_core') ?></b></a>
													<?php } ?>
												<?php }
											}

											$count = get_post_meta($post->ID, 'bookmarks_counter', true);
											if ($count) :
												if ($count < 0) {
													$count = 0;
												} ?>
												<span id="bookmarks-counter"><?php printf(_n('%s person bookmarked this place', '%s people bookmarked this place', $count, 'listeo_core'), number_format_i18n($count)); ?> </span>
											<?php endif; ?>
										</div>
									</div>
        <?php

    }
}
