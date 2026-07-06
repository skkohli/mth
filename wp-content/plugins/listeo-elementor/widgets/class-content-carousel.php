<?php
/**
 * Listeo Content Carousel Widget
 *
 * @category   Class
 * @package    ElementorListeo
 * @subpackage WordPress
 * @author     Custom Development
 * @copyright  2025
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @since      1.0.0
 * php version 7.3.9
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Repeater;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Listeo Content Carousel widget class.
 *
 * @since 1.0.0
 */
class ContentCarousel extends Widget_Base {

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
		return 'listeo-content-carousel';
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
		return __( 'Listeo Content Carousel', 'listeo_elementor' );
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
		return 'eicon-slider-3d';
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
		return [ 'listeo' ];
	}

	/**
	 * Enqueue styles.
	 */
	public function get_style_depends() {
		return [ 'listeo-content-carousel-style' ];
	}

	/**
	 * Enqueue scripts.
	 */
	public function get_script_depends() {
		return [ 'listeo-content-carousel-script' ];
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

		// Content Section - Navigation Tabs
		$this->start_controls_section(
			'tabs_section',
			[
				'label' => __( 'Navigation Tabs', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_tabs',
			[
				'label' => __( 'Show Navigation Tabs', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$tabs_repeater = new Repeater();

		$tabs_repeater->add_control(
			'tab_title',
			[
				'label' => __( 'Tab Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Tab Title', 'listeo_elementor' ),
				'placeholder' => __( 'Enter tab title', 'listeo_elementor' ),
			]
		);

		$tabs_repeater->add_control(
			'jump_to_position',
			[
				'label' => __( 'Jump to Card Position', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 0,
				'min' => 0,
				'description' => __( 'Card index to scroll to when this tab is clicked (0 = first card)', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'navigation_tabs',
			[
				'label' => __( 'Tabs', 'listeo_elementor' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $tabs_repeater->get_controls(),
				'default' => [
					[
						'tab_title' => __( 'Restaurants & Cafes', 'listeo_elementor' ),
						'jump_to_position' => 0,
					],
					[
						'tab_title' => __( 'Professional Services', 'listeo_elementor' ),
						'jump_to_position' => 4,
					],
					[
						'tab_title' => __( 'Hotels & Travel', 'listeo_elementor' ),
						'jump_to_position' => 8,
					],
				],
				'title_field' => '{{{ tab_title }}}',
				'condition' => [
					'show_tabs' => 'yes',
				],
			]
		);

		$this->end_controls_section();

		// Content Section - Carousel Cards
		$this->start_controls_section(
			'cards_section',
			[
				'label' => __( 'Carousel Cards', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$cards_repeater = new Repeater();

		$cards_repeater->add_control(
			'card_type',
			[
				'label' => __( 'Card Type', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'text',
				'options' => [
					'text' => __( 'Text Card', 'listeo_elementor' ),
					'image' => __( 'Image Card', 'listeo_elementor' ),
					'taxonomy' => __( 'Taxonomy Card', 'listeo_elementor' ),
					'testimonial' => __( 'Testimonial Card', 'listeo_elementor' ),
					'wide' => __( 'Text Card Wide', 'listeo_elementor' ),
				],
			]
		);

		// Text Card Fields
		$cards_repeater->add_control(
			'text_title',
			[
				'label' => __( 'Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Card Title', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_description',
			[
				'label' => __( 'Description', 'listeo_elementor' ),
				'type' => Controls_Manager::WYSIWYG,
				'default' => __( 'Card description goes here with details about the content.', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_cta_text',
			[
				'label' => __( 'CTA Button Text', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Read full case study', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_cta_link',
			[
				'label' => __( 'CTA Button Link', 'listeo_elementor' ),
				'type' => Controls_Manager::URL,
				'placeholder' => __( 'https://your-link.com', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_cta_inverted',
			[
				'label' => __( 'Button Inverted Colors', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'listeo_elementor' ),
				'label_off' => __( 'No', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'no',
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_custom_bg_color',
			[
				'label' => __( 'Custom Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default theme background', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_custom_title_color',
			[
				'label' => __( 'Custom Title Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default theme color', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_custom_text_color',
			[
				'label' => __( 'Custom Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default theme color', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_typography_divider',
			[
				'type' => Controls_Manager::DIVIDER,
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'text_title_typography',
				'label' => __( 'Title Typography', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} {{CURRENT_ITEM}} .card-title',
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'text_description_typography',
				'label' => __( 'Text Typography', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} {{CURRENT_ITEM}} .card-description',
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_button_divider',
			[
				'type' => Controls_Manager::DIVIDER,
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_button_color',
			[
				'label' => __( 'Button Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default button color', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_button_bg_color',
			[
				'label' => __( 'Button Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default button background', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_button_hover_color',
			[
				'label' => __( 'Button Hover Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default hover color', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		$cards_repeater->add_control(
			'text_button_hover_bg_color',
			[
				'label' => __( 'Button Hover Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'description' => __( 'Leave empty to use default hover background', 'listeo_elementor' ),
				'condition' => [
					'card_type' => [ 'text', 'wide' ],
				],
			]
		);

		// Image Card Fields
		$cards_repeater->add_control(
			'media_image',
			[
				'label' => __( 'Choose Image', 'listeo_elementor' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => \Elementor\Utils::get_placeholder_image_src(),
				],
				'condition' => [
					'card_type' => 'image',
				],
			]
		);

		$cards_repeater->add_control(
			'media_title',
			[
				'label' => __( 'Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Image Card Title', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'image',
				],
			]
		);

		$cards_repeater->add_control(
			'media_description',
			[
				'label' => __( 'Description', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => __( 'Brief description for the image card.', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'image',
				],
			]
		);

		$cards_repeater->add_control(
			'media_icon',
			[
				'label' => __( 'Icon', 'listeo_elementor' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-star',
					'library' => 'fa-solid',
				],
				'condition' => [
					'card_type' => 'image',
				],
			]
		);

		$cards_repeater->add_control(
			'media_background_overlay',
			[
				'label' => __( 'Background Overlay', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'default' => 'rgba(0, 0, 0, 0.5)',
				'condition' => [
					'card_type' => 'image',
				],
			]
		);

		$cards_repeater->add_control(
			'media_enable_link',
			[
				'label' => __( 'Enable Link', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'listeo_elementor' ),
				'label_off' => __( 'No', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => '',
				'condition' => [
					'card_type' => 'image',
				],
			]
		);

		$cards_repeater->add_control(
			'media_link',
			[
				'label' => __( 'Link', 'listeo_elementor' ),
				'type' => Controls_Manager::URL,
				'placeholder' => __( 'https://your-link.com', 'listeo_elementor' ),
				'default' => [
					'url' => '',
				],
				'condition' => [
					'card_type' => 'image',
					'media_enable_link' => 'yes',
				],
			]
		);

		// Taxonomy Card Fields
		$cards_repeater->add_control(
			'taxonomy_type',
			[
				'label' => __( 'Taxonomy Type', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'listing_category',
				'options' => [
					'listing_category' => __( 'Categories', 'listeo_elementor' ),
					'region' => __( 'Regions', 'listeo_elementor' ),
				],
				'condition' => [
					'card_type' => 'taxonomy',
				],
				'frontend_available' => true,
			]
		);

		$cards_repeater->add_control(
			'taxonomy_term',
			[
				'label' => __( 'Select Term', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT2,
				'default' => '',
				'options' => $this->get_taxonomy_terms_options(),
				'condition' => [
					'card_type' => 'taxonomy',
				],
				'frontend_available' => true,
			]
		);

		$cards_repeater->add_control(
			'taxonomy_title_override',
			[
				'label' => __( 'Custom Title (optional)', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'description' => __( 'Leave empty to use taxonomy term name', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'taxonomy',
				],
			]
		);

		$cards_repeater->add_control(
			'taxonomy_manual_image',
			[
				'label' => __( 'Manual Image Override', 'listeo_elementor' ),
				'type' => Controls_Manager::MEDIA,
				'description' => __( 'Override taxonomy image with custom image', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'taxonomy',
				],
			]
		);

		$cards_repeater->add_control(
			'taxonomy_icon_override',
			[
				'label' => __( 'Custom Icon', 'listeo_elementor' ),
				'type' => Controls_Manager::ICONS,
				'description' => __( 'Override taxonomy icon with custom icon', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'taxonomy',
				],
			]
		);

		$cards_repeater->add_control(
			'taxonomy_enable_link',
			[
				'label' => __( 'Enable Link', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'listeo_elementor' ),
				'label_off' => __( 'No', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __( 'Make taxonomy card clickable to view listings', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'taxonomy',
				],
			]
		);

		$cards_repeater->add_control(
			'taxonomy_show_count',
			[
				'label' => __( 'Show Listing Count', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => [
					'card_type' => 'taxonomy',
				],
			]
		);

		$cards_repeater->add_control(
			'taxonomy_background',
			[
				'label' => __( 'Background', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#764ba2',
				'condition' => [
					'card_type' => 'taxonomy',
				],
			]
		);

		// Testimonial Card Fields
		$cards_repeater->add_control(
			'testimonial_quote',
			[
				'label' => __( 'Quote', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => __( 'This is an amazing testimonial quote from a satisfied customer.', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'testimonial',
				],
			]
		);

		$cards_repeater->add_control(
			'testimonial_author',
			[
				'label' => __( 'Author Name', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'John Doe', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'testimonial',
				],
			]
		);

		$cards_repeater->add_control(
			'testimonial_title',
			[
				'label' => __( 'Author Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'CEO, Company Name', 'listeo_elementor' ),
				'condition' => [
					'card_type' => 'testimonial',
				],
			]
		);

		$cards_repeater->add_control(
			'testimonial_background',
			[
				'label' => __( 'Background', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#667eea',
				'condition' => [
					'card_type' => 'testimonial',
				],
			]
		);

		$this->add_control(
			'carousel_cards',
			[
				'label' => __( 'Carousel Cards', 'listeo_elementor' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $cards_repeater->get_controls(),
				'default' => [
					[
						'card_type' => 'text',
						'text_title' => __( 'Sample Case Study', 'listeo_elementor' ),
						'text_description' => __( 'This is a sample case study showing how our platform helped a business grow.', 'listeo_elementor' ),
						'text_cta_text' => __( 'Read full case study', 'listeo_elementor' ),
					],
					[
						'card_type' => 'image',
						'media_title' => __( 'Premium Listings', 'listeo_elementor' ),
					],
					[
						'card_type' => 'text',
						'text_title' => __( 'Another Text Card', 'listeo_elementor' ),
						'text_description' => __( 'This is another sample text card.', 'listeo_elementor' ),
					],
				],
				'title_field' => '<# 
					var title = "";
					if ( card_type == "text" || card_type == "wide" ) {
						title = text_title || "Text Card";
					} else if ( card_type == "metric" ) {
						title = metric_title || "Metric Card";
					} else if ( card_type == "image" ) {
						title = media_title || "Image Card";
					} else if ( card_type == "taxonomy" ) {
						if ( taxonomy_term ) {
							// Use custom title if provided, otherwise show selected taxonomy
							if ( taxonomy_title_override ) {
								title = taxonomy_title_override;
							} else {
								// Extract taxonomy name from the selected option
								// Format is like "category_123" -> need to get the actual term name
								// For now, show a generic label since we can\'t access the full options here
								var termParts = taxonomy_term.split("_");
								if ( termParts[0] == "category" ) {
									title = "Category Selected";
								} else if ( termParts[0] == "region" ) {
									title = "Region Selected";
								} else {
									title = "Taxonomy Selected";
								}
							}
						} else {
							title = "No Taxonomy Selected";
						}
					} else if ( card_type == "testimonial" ) {
						title = testimonial_author || "Testimonial Card";
					}
					var cardTypeLabel = card_type.charAt(0).toUpperCase() + card_type.slice(1);
				#>
				{{ cardTypeLabel }}: {{ title }}',
			]
		);

		$this->end_controls_section();

		// Carousel Settings
		$this->start_controls_section(
			'carousel_settings_section',
			[
				'label' => __( 'Carousel Settings', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'autoplay',
			[
				'label' => __( 'Autoplay', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'listeo_elementor' ),
				'label_off' => __( 'No', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => '',
			]
		);

		$this->add_control(
			'autoplay_speed',
			[
				'label' => __( 'Autoplay Speed (ms)', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 5000,
				'condition' => [
					'autoplay' => 'yes',
				],
			]
		);

		$this->add_control(
			'animation_speed',
			[
				'label' => __( 'Animation Speed (ms)', 'listeo_elementor' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 600,
			]
		);

		$this->add_control(
			'card_width',
			[
				'label' => __( 'Card Width (px)', 'listeo_elementor' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 200,
						'max' => 500,
						'step' => 10,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 280,
				],
				'description' => __( 'Wide cards will automatically be double this width', 'listeo_elementor' ),
				'selectors' => [
					'{{WRAPPER}} .listeo-carousel-widget' => '--carousel-card-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'card_min_height',
			[
				'label' => __( 'Card Min Height (px)', 'listeo_elementor' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 200,
						'max' => 600,
						'step' => 10,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 300,
				],
				'description' => __( 'Minimum height for all carousel cards', 'listeo_elementor' ),
				'selectors' => [
					'{{WRAPPER}} .carousel-card' => 'min-height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'show_navigation',
			[
				'label' => __( 'Show Navigation Arrows', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->end_controls_section();

		// Style Section - Tabs
		$this->start_controls_section(
			'tabs_style_section',
			[
				'label' => __( 'Tabs Style', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_tabs' => 'yes',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'tabs_typography',
				'label' => __( 'Typography', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .carousel-tab',
			]
		);

		$this->start_controls_tabs( 'carousel_tabs_style_tabs' );

		$this->start_controls_tab(
			'carousel_tabs_normal_tab',
			[
				'label' => __( 'Normal', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'carousel_tabs_text_color',
			[
				'label' => __( 'Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .carousel-tab' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_tabs_background_color',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .carousel-tab' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_tabs_normal_border_color',
			[
				'label' => __( 'Border Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .carousel-tab' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'carousel_tabs_active_tab',
			[
				'label' => __( 'Active', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'carousel_tabs_active_text_color',
			[
				'label' => __( 'Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .carousel-tab.active' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_tabs_active_background_color',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .carousel-tab.active' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_tabs_active_border_color',
			[
				'label' => __( 'Border Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .carousel-tab.active' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'carousel_tab_border',
				'label' => __( 'Border', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .carousel-tab',
			]
		);

		$this->add_control(
			'carousel_tab_border_radius',
			[
				'label' => __( 'Border Radius', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .carousel-tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'carousel_tab_padding',
			[
				'label' => __( 'Padding', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .carousel-tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Style Section - Cards
		$this->start_controls_section(
			'cards_style_section',
			[
				'label' => __( 'Cards Style', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'cards_gap',
			[
				'label' => __( 'Gap Between Cards', 'listeo_elementor' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
						'step' => 1,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 24,
				],
				'selectors' => [
					'{{WRAPPER}} .carousel-track' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'cards_border',
				'label' => __( 'Cards Border', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .carousel-card',
			]
		);

		$this->add_control(
			'cards_border_radius',
			[
				'label' => __( 'Cards Border Radius', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .carousel-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'cards_box_shadow',
				'label' => __( 'Cards Box Shadow', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .carousel-card',
			]
		);

		$this->end_controls_section();

		// Style Section - Navigation
		$this->start_controls_section(
			'navigation_style_section',
			[
				'label' => __( 'Navigation Style', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_navigation' => 'yes',
				],
			]
		);

		$this->add_control(
			'nav_arrows_size',
			[
				'label' => __( 'Arrow Size', 'listeo_elementor' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 30,
						'max' => 80,
						'step' => 1,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 48,
				],
				'selectors' => [
					'{{WRAPPER}} .nav-arrow' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->start_controls_tabs( 'carousel_navigation_style_tabs' );

		$this->start_controls_tab(
			'carousel_nav_normal_tab',
			[
				'label' => __( 'Normal', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'carousel_nav_text_color',
			[
				'label' => __( 'Arrow Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-arrow' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_nav_background_color',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-arrow' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_nav_border_color',
			[
				'label' => __( 'Border Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-arrow' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'carousel_nav_hover_tab',
			[
				'label' => __( 'Hover', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'carousel_nav_hover_text_color',
			[
				'label' => __( 'Arrow Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-arrow:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_nav_hover_background_color',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-arrow:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'carousel_nav_hover_border_color',
			[
				'label' => __( 'Border Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-arrow:hover' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Get taxonomy terms options for individual carousel cards
	 * Returns hierarchical list of all listing taxonomies (categories, regions, features, and listing type-specific categories)
	 */
	protected function get_taxonomy_terms_options() {
		$options = [ '' => __( 'Select a term...', 'listeo_elementor' ) ];

		// Get ALL taxonomies registered for the listing post type
		// This includes global taxonomies (listing_category, region, listing_feature)
		// AND listing type-specific taxonomies (service_category, event_category, etc.)
		$taxonomies = get_object_taxonomies( 'listing', 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			// Skip non-public taxonomies
			if ( ! $taxonomy->public ) {
				continue;
			}

			// Get terms for this taxonomy
			$terms = get_terms( [
				'taxonomy'   => $taxonomy->name,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			] );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				// Determine prefix based on taxonomy name
				$prefix = $this->get_taxonomy_prefix( $taxonomy->name );
				$type_label = $taxonomy->label;

				$this->add_hierarchical_terms( $options, $terms, $prefix, $type_label );
			}
		}

		return $options;
	}

	/**
	 * Get a short prefix for taxonomy term identifiers
	 * Maps taxonomy names to short prefixes for term selection values
	 */
	protected function get_taxonomy_prefix( $taxonomy_name ) {
		// Map common taxonomies to short prefixes
		$prefix_map = [
			'listing_category' => 'category',
			'region'           => 'region',
			'listing_feature'  => 'feature',
		];

		if ( isset( $prefix_map[ $taxonomy_name ] ) ) {
			return $prefix_map[ $taxonomy_name ];
		}

		// For listing type-specific categories (e.g., service_category, event_category)
		// Use the taxonomy name as the prefix
		return $taxonomy_name;
	}

	/**
	 * Add hierarchical terms to options array
	 * Shows parent > child relationship in the label
	 */
	protected function add_hierarchical_terms( &$options, $terms, $prefix, $type_label ) {
		// Build a lookup array for parent names
		$term_names = [];
		foreach ( $terms as $term ) {
			$term_names[ $term->term_id ] = $term->name;
		}

		// Sort terms: parents first, then children
		$sorted_terms = [];
		$children = [];

		foreach ( $terms as $term ) {
			if ( $term->parent == 0 ) {
				$sorted_terms[] = $term;
			} else {
				$children[] = $term;
			}
		}

		// Add children after their parents
		foreach ( $children as $child ) {
			$sorted_terms[] = $child;
		}

		// Build options with hierarchy indication
		foreach ( $sorted_terms as $term ) {
			$label = $type_label . ': ';

			// If has parent, show parent > child
			if ( $term->parent > 0 && isset( $term_names[ $term->parent ] ) ) {
				$label .= $term_names[ $term->parent ] . ' → ' . $term->name;
			} else {
				$label .= $term->name;
			}

			$options[ $prefix . '_' . $term->term_id ] = $label;
		}
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
		
		// Process content based on source
		$carousel_data = $this->process_carousel_content( $settings );
		
		if ( empty( $carousel_data['cards'] ) ) {
			echo '<p>' . __( 'No carousel content to display. Please configure the widget settings.', 'listeo_elementor' ) . '</p>';
			return;
		}
		?>
		
		<div class="listeo-carousel-widget" 
			 data-autoplay="<?php echo esc_attr( $settings['autoplay'] ); ?>"
			 data-autoplay-speed="<?php echo esc_attr( $settings['autoplay_speed'] ); ?>"
			 data-animation-speed="<?php echo esc_attr( $settings['animation_speed'] ); ?>">

			<?php if ( ! empty( $settings['show_tabs'] ) && $settings['show_tabs'] === 'yes' && ! empty( $settings['navigation_tabs'] ) ) : ?>
				<div class="carousel-tabs">
					<?php foreach ( $settings['navigation_tabs'] as $index => $tab ) : ?>
						<div class="carousel-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
							 data-position="<?php echo esc_attr( $tab['jump_to_position'] ); ?>">
							<?php echo esc_html( $tab['tab_title'] ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="carousel-container">
				<div class="carousel-wrapper">
					<div class="carousel-track">
						<?php foreach ( $carousel_data['cards'] as $index => $card ) : ?>
							<?php $this->render_carousel_card( $card, $index ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $settings['show_navigation'] ) && $settings['show_navigation'] === 'yes' ) : ?>
				<div class="carousel-navigation">
					<button class="nav-arrow nav-prev"><i class="fas fa-chevron-left"></i></button>
					<button class="nav-arrow nav-next"><i class="fas fa-chevron-right"></i></button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Process carousel content
	 */
	protected function process_carousel_content( $settings ) {
		$cards = [];
		
		// Use carousel cards configuration
		if ( ! empty( $settings['carousel_cards'] ) ) {
			$cards = $settings['carousel_cards'];
		}
		
		return [
			'cards' => $cards,
		];
	}

	/**
	 * Render individual carousel card
	 */
	protected function render_carousel_card( $card, $index = 0 ) {
		$card_type = $card['card_type'];
		$card_class = 'carousel-card card-' . $card_type;
		
		// Add card-text class for text-based cards
		if ( in_array( $card_type, [ 'text', 'wide' ] ) ) {
			$card_class .= ' card-text';
		}
		
		// Build custom styles for text cards
		$card_styles = '';
		if ( in_array( $card_type, [ 'text', 'wide' ] ) ) {
			$custom_styles = [];
			$has_custom_colors = false;
			
			if ( ! empty( $card['text_custom_bg_color'] ) ) {
				$custom_styles[] = '--card-custom-bg: ' . esc_attr( $card['text_custom_bg_color'] );
				$has_custom_colors = true;
			}
			
			if ( ! empty( $card['text_custom_title_color'] ) ) {
				$custom_styles[] = '--card-custom-title-color: ' . esc_attr( $card['text_custom_title_color'] );
				$has_custom_colors = true;
			}
			
			if ( ! empty( $card['text_custom_text_color'] ) ) {
				$custom_styles[] = '--card-custom-text-color: ' . esc_attr( $card['text_custom_text_color'] );
				$has_custom_colors = true;
			}
			
			// Button custom colors
			if ( ! empty( $card['text_button_color'] ) ) {
				$custom_styles[] = '--card-button-color: ' . esc_attr( $card['text_button_color'] );
				$has_custom_colors = true;
			}
			
			if ( ! empty( $card['text_button_bg_color'] ) ) {
				$custom_styles[] = '--card-button-bg: ' . esc_attr( $card['text_button_bg_color'] );
				$has_custom_colors = true;
			}
			
			if ( ! empty( $card['text_button_hover_color'] ) ) {
				$custom_styles[] = '--card-button-hover-color: ' . esc_attr( $card['text_button_hover_color'] );
				$has_custom_colors = true;
			}
			
			if ( ! empty( $card['text_button_hover_bg_color'] ) ) {
				$custom_styles[] = '--card-button-hover-bg: ' . esc_attr( $card['text_button_hover_bg_color'] );
				$has_custom_colors = true;
			}
			
			// Add custom color class if any custom colors are set
			if ( $has_custom_colors ) {
				$card_class .= ' card-custom-color';
			}
			
			if ( ! empty( $custom_styles ) ) {
				$card_styles = ' style="' . implode( '; ', $custom_styles ) . '"';
			}
		}
		
		// Check if this card should be a link
		$is_link = false;
		$link_url = '';
		$link_target = '';
		$link_nofollow = '';
		
		if ( $card_type === 'image' && $card['media_enable_link'] === 'yes' && ! empty( $card['media_link']['url'] ) ) {
			$is_link = true;
			$link_url = $card['media_link']['url'];
			$link_target = $card['media_link']['is_external'] ? ' target="_blank"' : '';
			$link_nofollow = $card['media_link']['nofollow'] ? ' rel="nofollow"' : '';
		} elseif ( $card_type === 'taxonomy' ) {
			// For taxonomy cards, check if linking is enabled (default to 'yes' if not set)
			$enable_link = isset( $card['taxonomy_enable_link'] ) ? $card['taxonomy_enable_link'] : 'yes';
			if ( $enable_link === 'yes' ) {
				$is_link = true;
				$link_url = $this->get_taxonomy_link_url( $card );
			}
		}
		
		if ( $is_link && $link_url ) {
			echo '<a href="' . esc_url( $link_url ) . '" class="' . esc_attr( $card_class ) . '" data-elementor-repeater-item-' . esc_attr( $card['_id'] ) . '"' . $link_target . $link_nofollow . $card_styles . '>';
		} else {
			echo '<div class="' . esc_attr( $card_class ) . '" data-elementor-repeater-item-' . esc_attr( $card['_id'] ) . '"' . $card_styles . '>';
		}
		
		switch ( $card_type ) {
			case 'text':
			case 'wide':
				$this->render_text_card( $card );
				break;
			case 'image':
				$this->render_image_card( $card );
				break;
			case 'taxonomy':
				$this->render_taxonomy_card( $card );
				break;
			case 'testimonial':
				$this->render_testimonial_card( $card );
				break;
		}
		
		if ( $is_link && $link_url ) {
			echo '</a>';
		} else {
			echo '</div>';
		}
	}

	/**
	 * Get taxonomy link URL for card
	 */
	protected function get_taxonomy_link_url( $card ) {
		$term_selection = $card['taxonomy_term'];

		if ( ! empty( $term_selection ) && strpos( $term_selection, '_' ) !== false ) {
			// Parse the term selection - format is {prefix}_{term_id}
			// Split on the LAST underscore since prefix can contain underscores (e.g., service_category_123)
			$last_underscore_pos = strrpos( $term_selection, '_' );
			$prefix = substr( $term_selection, 0, $last_underscore_pos );
			$term_id = substr( $term_selection, $last_underscore_pos + 1 );

			// Map prefix to actual taxonomy name
			$actual_taxonomy = $this->get_taxonomy_from_prefix( $prefix );

			// Get term object first, then get link - this is more reliable
			$term = get_term( $term_id, $actual_taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_link = get_term_link( $term, $actual_taxonomy );
				if ( ! is_wp_error( $term_link ) ) {
					return $term_link;
				}
			}
		}

		return '';
	}

	/**
	 * Get the actual taxonomy name from a prefix
	 * Reverse of get_taxonomy_prefix()
	 */
	protected function get_taxonomy_from_prefix( $prefix ) {
		// Map short prefixes back to taxonomy names
		$taxonomy_map = [
			'category' => 'listing_category',
			'region'   => 'region',
			'feature'  => 'listing_feature',
		];

		if ( isset( $taxonomy_map[ $prefix ] ) ) {
			return $taxonomy_map[ $prefix ];
		}

		// For listing type-specific taxonomies, the prefix IS the taxonomy name
		// (e.g., service_category, event_category)
		return $prefix;
	}

	/**
	 * Render text/wide card
	 */
	protected function render_text_card( $card ) {
		?>
		<div class="card-title"><?php echo esc_html( $card['text_title'] ); ?></div>
		<div class="card-description"><?php echo wp_kses_post( $card['text_description'] ); ?></div>
		<?php if ( ! empty( $card['text_cta_text'] ) && ! empty( $card['text_cta_link']['url'] ) ) : ?>
			<?php 
			$cta_class = 'card-cta';
			if ( ! empty( $card['text_cta_inverted'] ) && $card['text_cta_inverted'] === 'yes' ) {
				$cta_class .= ' cta-inverted-colors';
			}
			?>
			<a href="<?php echo esc_url( $card['text_cta_link']['url'] ); ?>" class="<?php echo esc_attr( $cta_class ); ?>"
			   <?php echo ! empty( $card['text_cta_link']['is_external'] ) ? 'target="_blank"' : ''; ?>
			   <?php echo ! empty( $card['text_cta_link']['nofollow'] ) ? 'rel="nofollow"' : ''; ?>>
				<?php echo esc_html( $card['text_cta_text'] ); ?>
			</a>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render image card
	 */
	protected function render_image_card( $card ) {
		$overlay_color = ! empty( $card['media_background_overlay'] ) ? $card['media_background_overlay'] : 'rgba(0, 0, 0, 0.5)';
		$image_url = ! empty( $card['media_image']['url'] ) ? $card['media_image']['url'] : \Elementor\Utils::get_placeholder_image_src();
		?>
		<div class="card-content" style="background: linear-gradient(<?php echo esc_attr( $overlay_color ); ?>, <?php echo esc_attr( $overlay_color ); ?>), url('<?php echo esc_url( $image_url ); ?>') center/cover;">
			<?php if ( ! empty( $card['media_icon']['value'] ) ) : ?>
				<div class="media-icon">
					<?php if ( is_array( $card['media_icon']['value'] ) ) : ?>
						<i class="<?php echo esc_attr( $card['media_icon']['value']['value'] ); ?>"></i>
					<?php else : ?>
						<i class="<?php echo esc_attr( $card['media_icon']['value'] ); ?>"></i>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="card-title"><?php echo esc_html( $card['media_title'] ); ?></div>
			<?php if ( ! empty( $card['media_description'] ) ) : ?>
				<div class="card-description"><?php echo esc_html( $card['media_description'] ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get font icon class from taxonomy term meta
	 */
	protected function get_term_icon( $term_id, $taxonomy ) {
		// Try to get Font Awesome icon from term meta first
		$icon = get_term_meta( $term_id, 'icon', true );
		
		// Try alternative meta key used by some themes
		if ( empty( $icon ) ) {
			$icon = get_term_meta( $term_id, '_icon', true );
		}
		
		// Try Font Awesome icon meta
		if ( empty( $icon ) ) {
			$icon = get_term_meta( $term_id, 'fa_icon', true );
		}
		
		// For listing categories, try category-specific meta
		if ( empty( $icon ) && $taxonomy === 'listing_category' ) {
			$icon = get_term_meta( $term_id, 'category_icon', true );
		}
		
		// Try to get from category meta table (if using custom fields)
		if ( empty( $icon ) ) {
			global $wpdb;
			$icon_result = $wpdb->get_var( $wpdb->prepare( 
				"SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = 'icon'", 
				$term_id
			) );
			if ( $icon_result ) {
				$icon = $icon_result;
			}
		}
		
		// Return the font icon class
		return $icon;
	}

	/**
	 * Render taxonomy card
	 */
	protected function render_taxonomy_card( $card ) {
		$background_color = ! empty( $card['taxonomy_background'] ) ? $card['taxonomy_background'] : '#764ba2';
		$taxonomy = $card['taxonomy_type'];
		$term_selection = $card['taxonomy_term'];
		$show_count = $card['taxonomy_show_count'] === 'yes';
		$manual_image = ! empty( $card['taxonomy_manual_image']['url'] ) ? $card['taxonomy_manual_image']['url'] : '';

		// Parse the term selection - format is {prefix}_{term_id}
		// Split on the LAST underscore since prefix can contain underscores (e.g., service_category_123)
		if ( ! empty( $term_selection ) && strpos( $term_selection, '_' ) !== false ) {
			$last_underscore_pos = strrpos( $term_selection, '_' );
			$prefix = substr( $term_selection, 0, $last_underscore_pos );
			$term_id = substr( $term_selection, $last_underscore_pos + 1 );

			// Map prefix to actual taxonomy name
			$actual_taxonomy = $this->get_taxonomy_from_prefix( $prefix );
			
			$term = get_term( $term_id, $actual_taxonomy );
			
			// If term doesn't exist, pick a random term from the chosen taxonomy
			if ( ! $term || is_wp_error( $term ) ) {
				$terms = get_terms( [
					'taxonomy' => $actual_taxonomy,
					'hide_empty' => false,
				] );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$term = $terms[ array_rand( $terms ) ];
				} else {
					$term = false;
				}
			}

			if ( $term && ! is_wp_error( $term ) ) {
				$title = ! empty( $card['taxonomy_title_override'] ) ? $card['taxonomy_title_override'] : $term->name;
				
				// Get listing count for this taxonomy term
				$listing_count = 0;
				if ( $show_count ) {
					// Use WP_Query to get accurate count without loading all posts
					$count_query = new \WP_Query( [
						'post_type' => 'listing',
						'post_status' => 'publish',
						'tax_query' => [
							[
								'taxonomy' => $actual_taxonomy,
								'field'    => 'term_id',
								'terms'    => $term_id,
							],
						],
						'posts_per_page' => 1, // We only need the count, not the posts
						'fields' => 'ids', // Only retrieve IDs to minimize memory usage
						'no_found_rows' => false, // We need found_posts for the count
					] );
					$listing_count = $count_query->found_posts;
					wp_reset_postdata();
				}
				
				// Get taxonomy term image/icon
				$background_image = '';
				$icon_html = '';
				
				// Check for custom icon override first
				if ( ! empty( $card['taxonomy_icon_override']['value'] ) ) {
					if ( is_array( $card['taxonomy_icon_override']['value'] ) ) {
						// Font Awesome icon
						$icon_html = '<i class="' . esc_attr( $card['taxonomy_icon_override']['value']['value'] ) . '"></i>';
					} else {
						// String icon
						$icon_html = '<i class="' . esc_attr( $card['taxonomy_icon_override']['value'] ) . '"></i>';
					}
				}
				
				// Check for manual image override
				if ( $manual_image ) {
					$background_image = $manual_image;
				} else {
					// Try to get image from taxonomy meta
					if ( $actual_taxonomy === 'listing_category' ) {
						// Try to get cover image first
						$cover_id = get_term_meta( $term_id, '_cover', true );
						if ( $cover_id ) {
							$image_data = wp_get_attachment_image_src( $cover_id, 'large' );
							if ( $image_data ) {
								$background_image = $image_data[0];
							}
						}
						
						// If no cover, try SVG icon as background
						if ( ! $background_image ) {
							$icon_svg_id = get_term_meta( $term_id, '_icon_svg', true );
							if ( $icon_svg_id ) {
								$image_data = wp_get_attachment_image_src( $icon_svg_id, 'large' );
								if ( $image_data ) {
									$background_image = $image_data[0];
								}
							}
						}
					} elseif ( $actual_taxonomy === 'region' ) {
						// For regions, try to get cover image
						$cover_id = get_term_meta( $term_id, '_cover', true );
						if ( $cover_id ) {
							$image_data = wp_get_attachment_image_src( $cover_id, 'large' );
							if ( $image_data ) {
								$background_image = $image_data[0];
							}
						}
						
						// If no cover, try SVG icon as background
						if ( ! $background_image ) {
							$icon_svg_id = get_term_meta( $term_id, '_icon_svg', true );
							if ( $icon_svg_id ) {
								$image_data = wp_get_attachment_image_src( $icon_svg_id, 'large' );
								if ( $image_data ) {
									$background_image = $image_data[0];
								}
							}
						}
					}
				}
				
				// Get icon regardless of background image (moved outside image logic)
				if ( ! $icon_html ) {
					// First try SVG icon using Listeo's method
					$svg_id = get_term_meta( $term_id, '_icon_svg', true );
					if ( $svg_id && function_exists( 'listeo_render_svg_icon' ) ) {
						$icon_html = listeo_render_svg_icon( $svg_id );
					} else {
						// Fallback to font icon
						$icon = $this->get_term_icon( $term_id, $actual_taxonomy );
						if ( $icon ) {
							$icon_html = '<i class="' . esc_attr( $icon ) . '"></i>';
						}
					}
				}
				
				// Add fallback default icons if no icon was found
				if ( ! $icon_html ) {
					if ( $actual_taxonomy === 'listing_category' ) {
						$icon_html = '<i class="fa fa-list"></i>'; // Default category icon
					} elseif ( $actual_taxonomy === 'region' ) {
						$icon_html = '<i class="fa fa-map-marker"></i>'; // Default region icon
					}
				}
				
				// Set up background style
				$background_style = "background: $background_color;";
				if ( $background_image ) {
					// Convert hex color to rgba for overlay
					$hex_color = $background_color;
					if ( substr( $hex_color, 0, 1 ) == '#' ) {
						$hex_color = substr( $hex_color, 1 );
					}
					$r = hexdec( substr( $hex_color, 0, 2 ) );
					$g = hexdec( substr( $hex_color, 2, 2 ) );
					$b = hexdec( substr( $hex_color, 4, 2 ) );
					$overlay_color = "rgba($r, $g, $b, 0.7)";
					
					$background_style = "background: linear-gradient($overlay_color, $overlay_color), url('$background_image') center/cover;";
				}
				
				?>
				<div class="card-content" style="<?php echo esc_attr( $background_style ); ?>">
					<?php if ( $icon_html ) : ?>
						<div class="taxonomy-icon">
							<?php echo $icon_html; ?>
						</div>
					<?php endif; ?>
					<div class="card-title"><?php echo esc_html( $title ); ?></div>
					<?php if ( $show_count ) : ?>
						<div class="taxonomy-count"><?php echo esc_html( sprintf( _n( '%d listing', '%d listings', $listing_count, 'listeo_elementor' ), $listing_count ) ); ?></div>
					<?php endif; ?>
					<?php if ( $term->description ) : ?>
						<div class="taxonomy-description"><?php echo esc_html( $term->description ); ?></div>
					<?php endif; ?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Render testimonial card
	 */
	protected function render_testimonial_card( $card ) {
		$background_color = ! empty( $card['testimonial_background'] ) ? $card['testimonial_background'] : '#667eea';
		?>
		<div class="card-content" style="background: <?php echo esc_attr( $background_color ); ?>">
			<div class="carousel-testimonial-quote"><?php echo esc_html( $card['testimonial_quote'] ); ?></div>
			<div class="carousel-testimonial-author"><?php echo esc_html( $card['testimonial_author'] ); ?></div>
			<div class="carousel-testimonial-title"><?php echo esc_html( $card['testimonial_title'] ); ?></div>
		</div>
		<?php
	}

}
