<?php
/**
 * Listeo Elementor AI Chat Widget class.
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

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * AI Chat widget class.
 *
 * @since 1.0.0
 */
class AiChat extends Widget_Base {

	/**
	 * Retrieve the widget name.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'listeo-ai-chat';
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
	public function get_title() {
		return __( 'AI Chat', 'listeo_elementor' );
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
	public function get_icon() {
		return 'eicon-comments';
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
	public function get_categories() {
		return array( 'listeo' );
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords() {
		return array( 'listeo', 'ai', 'chat', 'chatbot', 'assistant' );
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
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Settings', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'height',
			array(
				'label' => __( 'Chat Height', 'listeo_elementor' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range' => array(
					'px' => array(
						'min' => 300,
						'max' => 1000,
						'step' => 10,
					),
					'vh' => array(
						'min' => 20,
						'max' => 100,
						'step' => 5,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 600,
				),
				'description' => __( 'Set the height of the chat widget', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'pictures',
			array(
				'label' => __( 'Show Listing Images', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'global',
				'options' => array(
					'global' => __( 'Use Global Setting', 'listeo_elementor' ),
					'enabled' => __( 'Show Images', 'listeo_elementor' ),
					'disabled' => __( 'Hide Images', 'listeo_elementor' ),
				),
				'description' => __( 'Control whether listing images are displayed in chat results', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'style',
			array(
				'label' => __( 'Chat Style', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'style1',
				'options' => array(
					'style1' => __( 'Style 1', 'listeo_elementor' ),
					'style2' => __( 'Style 2', 'listeo_elementor' ),
				),
				'description' => __( 'Choose the chat widget style', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'show_popular_searches',
			array(
				'label' => __( 'Show Popular Searches', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __( 'Display popular search tags below the chat widget', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'popular_searches_limit',
			array(
				'label' => __( 'Number of Popular Searches', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 3,
				'max' => 15,
				'step' => 1,
				'default' => 5,
				'description' => __( 'How many popular search tags to display', 'listeo_elementor' ),
				'condition' => array(
					'show_popular_searches' => 'yes',
				),
			)
		);

		$this->add_control(
			'popular_searches_title',
			array(
				'label' => __( 'Popular Searches Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Popular Searches:', 'listeo_elementor' ),
				'description' => __( 'Text displayed above popular search tags', 'listeo_elementor' ),
				'condition' => array(
					'show_popular_searches' => 'yes',
				),
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
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Build shortcode attributes
		$shortcode_atts = array();

		// Height
		if ( ! empty( $settings['height']['size'] ) ) {
			$unit = ! empty( $settings['height']['unit'] ) ? $settings['height']['unit'] : 'px';
			$shortcode_atts[] = 'height="' . esc_attr( $settings['height']['size'] . $unit ) . '"';
		}

		// Pictures
		if ( ! empty( $settings['pictures'] ) && $settings['pictures'] !== 'global' ) {
			$shortcode_atts[] = 'pictures="' . esc_attr( $settings['pictures'] ) . '"';
		}

		// Popular searches
		if ( ! empty( $settings['show_popular_searches'] ) && $settings['show_popular_searches'] === 'yes' ) {
			$shortcode_atts[] = 'show_popular_searches="yes"';

			// Popular searches limit
			if ( ! empty( $settings['popular_searches_limit'] ) ) {
				$shortcode_atts[] = 'popular_searches_limit="' . esc_attr( intval( $settings['popular_searches_limit'] ) ) . '"';
			}

			// Popular searches title
			if ( ! empty( $settings['popular_searches_title'] ) ) {
				$shortcode_atts[] = 'popular_searches_title="' . esc_attr( $settings['popular_searches_title'] ) . '"';
			}
		}

		// Build shortcode
		$shortcode = '[listeo_ai_chat';
		if ( ! empty( $shortcode_atts ) ) {
			$shortcode .= ' ' . implode( ' ', $shortcode_atts );
		}
		$shortcode .= ']';

		// Check if Style 2 is selected
		$use_style2 = ! empty( $settings['style'] ) && $settings['style'] === 'style2';

		// Wrap in elementor-chat-style class if Style 2 is selected
		if ( $use_style2 ) {
			echo '<div class="elementor-chat-style">';
		}

		// Render shortcode (let the shortcode handle its own error messages)
		echo do_shortcode( $shortcode );

		if ( $use_style2 ) {
			echo '</div>';
		}
	}

	/**
	 * Render plain content - forces Elementor to use PHP render() method in editor
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function render_plain_content() {
		// Return empty to force Elementor to use render() method
	}
}
