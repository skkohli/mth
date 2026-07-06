<?php
/**
 * Minimal Search Form Widget
 *
 * @category   Class
 * @package    ElementorListeo
 * @subpackage WordPress
 * @author     PureThemes
 * @copyright  2025 PureThemes
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @since      1.0.0
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Minimal Search Form widget class.
 *
 * @since 1.0.0
 */
class MinimalSearchForm extends Widget_Base {

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
		return 'listeo-minimal-search-form';
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
		return __( 'Minimal Search Form', 'listeo_elementor' );
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
		return 'eicon-search';
	}

	/**
	 * Retrieve the list of categories the widget belongs to.
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
	 * Register the widget controls.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function register_controls() {

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'listeo_elementor' ),
			)
		);

		// Form source dropdown
		if (function_exists('listeo_get_search_forms_dropdown')) {
			$search_forms = listeo_get_search_forms_dropdown('fullwidth');
			$this->add_control(
				'search_form_source',
				[
					'label' => __( 'Form source', 'listeo_elementor' ),
					'type' => \Elementor\Controls_Manager::SELECT,
					'options' => $search_forms,
					'default' => 'search_on_home_page'
				]
			);
		}

		// Form action
		$this->add_control(
			'search_form_action',
			[
				'label' => __('Form action', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'listing' => __('Listings results', 'listeo_elementor'),
					'page' => __('Page', 'listeo_elementor'),
					'custom' => __('Custom link', 'listeo_elementor'),
				],
				'default' => 'listing'
			]
		);

		$this->add_control(
			'search_form_action_custom',
			[
				'label' => __('Custom action', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'condition' => [
					'search_form_action' => 'custom',
				],
			]
		);

		$this->add_control(
			'search_form_action_page',
			[
				'label' => __('Page', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $this->listeo_get_pages_dropdown(),
				'default' => '',
				'condition' => [
					'search_form_action' => 'page',
				],
			]
		);

		// Size control (zoom)
		$this->add_control(
			'form_size',
			[
				'label' => __('Form Size', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0.5,
						'max' => 1.5,
						'step' => 0.01,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 1,
				],
				'selectors' => [
					'{{WRAPPER}} .listeo-minimal-search-form-wrapper' => 'zoom: {{SIZE}};',
				],
			]
		);

		// Additional CSS class
		$this->add_control(
			'custom_class',
			[
				'label' => __('Custom CSS Class', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'description' => __('Add your custom class without the dot. e.g: my-custom-class', 'listeo_elementor'),
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
			'form_background',
			[
				'label' => __('Form Background Color', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .main-search-form' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'form_border_radius',
			[
				'label' => __('Border Radius', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'selectors' => [
					'{{WRAPPER}} .main-search-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'form_padding',
			[
				'label' => __('Padding', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .main-search-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'form_margin',
			[
				'label' => __('Margin', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .listeo-minimal-search-form-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'form_box_shadow',
				'label' => __('Box Shadow', 'listeo_elementor'),
				'selector' => '{{WRAPPER}} .main-search-form',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Determine form action
		$form_action = $settings['search_form_action'];
		if ($form_action == 'page' && !empty($settings['search_form_action_page'])) {
			$form_action = get_permalink($settings['search_form_action_page']);
		} else if ($form_action == 'custom' && !empty($settings['search_form_action_custom'])) {
			$form_action = $settings['search_form_action_custom'];
		} else {
			$form_action = get_post_type_archive_link('listing');
		}

		// Build the custom class string
		$custom_class = 'main-search-form';
		if (!empty($settings['custom_class'])) {
			$custom_class .= ' ' . esc_attr($settings['custom_class']);
		}

		// Output wrapper div
		?>
		<style>
			.listeo-minimal-search-form-wrapper .main-search-input {
				margin-top: 0;
			}
		</style>
		<div class="listeo-minimal-search-form-wrapper">
			<?php
			// Output the search form using shortcode
			echo do_shortcode('[listeo_search_form action=' . $form_action . ' source="' . $settings['search_form_source'] . '" custom_class="' . $custom_class . '"]');
			?>
		</div>
		<?php
	}

	/**
	 * Get pages dropdown for form action
	 *
	 * @return array
	 */
	function listeo_get_pages_dropdown() {
		$pages = get_pages();
		$options = ['' => ''];
		if (!empty($pages)) :
			foreach ($pages as $page) {
				$options[$page->ID] = $page->post_title;
			}
		endif;
		return $options;
	}
}