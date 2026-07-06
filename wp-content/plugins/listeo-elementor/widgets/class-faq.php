<?php
/**
 * Listeo FAQ Widget
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
 * Listeo FAQ widget class.
 *
 * @since 1.0.0
 */
class FAQ extends Widget_Base {

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
		return 'listeo-faq';
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
		return __( 'Listeo FAQ', 'listeo_elementor' );
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
		return 'eicon-accordion';
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
		return [ 'listeo-faq-style' ];
	}

	/**
	 * Enqueue scripts.
	 */
	public function get_script_depends() {
		return [ 'listeo-faq-script' ];
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

		// Content Section
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'faq_title',
			[
				'label' => __( 'FAQ Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Frequently Asked Questions', 'listeo_elementor' ),
				'placeholder' => __( 'Enter FAQ title', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'show_title',
			[
				'label' => __( 'Show Title', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'question',
			[
				'label' => __( 'Question', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Question goes here?', 'listeo_elementor' ),
				'placeholder' => __( 'Enter question', 'listeo_elementor' ),
			]
		);

		$repeater->add_control(
			'answer',
			[
				'label' => __( 'Answer', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => __( 'Answer content goes here. Provide detailed information to help users understand the topic.', 'listeo_elementor' ),
				'placeholder' => __( 'Enter answer', 'listeo_elementor' ),
			]
		);

		$repeater->add_control(
			'is_active',
			[
				'label' => __( 'Open by Default', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Open', 'listeo_elementor' ),
				'label_off' => __( 'Closed', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => '',
			]
		);

		$this->add_control(
			'faq_items',
			[
				'label' => __( 'FAQ Items', 'listeo_elementor' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'question' => __( 'How does this service work?', 'listeo_elementor' ),
						'answer' => __( 'Our service uses advanced technology to provide you with the best results. We analyze your needs and provide customized solutions.', 'listeo_elementor' ),
						'is_active' => 'yes',
					],
					[
						'question' => __( 'What are the pricing options?', 'listeo_elementor' ),
						'answer' => __( 'We offer flexible pricing plans to suit different needs and budgets. Contact us for a customized quote.', 'listeo_elementor' ),
						'is_active' => '',
					],
					[
						'question' => __( 'Is customer support available?', 'listeo_elementor' ),
						'answer' => __( 'Yes, we provide 24/7 customer support through multiple channels including email, phone, and live chat.', 'listeo_elementor' ),
						'is_active' => '',
					],
				],
				'title_field' => '{{{ question }}}',
			]
		);

		$this->add_control(
			'accordion_behavior',
			[
				'label' => __( 'Accordion Behavior', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'single',
				'options' => [
					'single' => __( 'Single (close others when opening)', 'listeo_elementor' ),
					'multiple' => __( 'Multiple (allow multiple open)', 'listeo_elementor' ),
				],
			]
		);

		$this->end_controls_section();

		// Style Section - Title
		$this->start_controls_section(
			'title_style_section',
			[
				'label' => __( 'Title', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'title_typography',
				'selector' => '{{WRAPPER}} .faq-title',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label' => __( 'Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .faq-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'title_align',
			[
				'label' => __( 'Alignment', 'listeo_elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'listeo_elementor' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'listeo_elementor' ),
						'icon' => 'eicon-text-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'listeo_elementor' ),
						'icon' => 'eicon-text-align-right',
					],
				],
				'default' => 'center',
				'selectors' => [
					'{{WRAPPER}} .faq-title' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'title_margin',
			[
				'label' => __( 'Margin', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .faq-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Style Section - FAQ Items
		$this->start_controls_section(
			'items_style_section',
			[
				'label' => __( 'FAQ Items', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'item_spacing',
			[
				'label' => __( 'Item Spacing', 'listeo_elementor' ),
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
					'size' => 16,
				],
				'selectors' => [
					'{{WRAPPER}} .faq-item' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'item_background',
				'types' => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .faq-item',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'item_border',
				'selector' => '{{WRAPPER}} .faq-item',
			]
		);

		$this->add_control(
			'item_border_radius',
			[
				'label' => __( 'Border Radius', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .faq-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'item_box_shadow',
				'selector' => '{{WRAPPER}} .faq-item',
			]
		);

		$this->end_controls_section();

		// Style Section - Questions
		$this->start_controls_section(
			'question_style_section',
			[
				'label' => __( 'Questions', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'question_typography',
				'selector' => '{{WRAPPER}} .faq-question',
			]
		);

		$this->add_control(
			'question_color',
			[
				'label' => __( 'Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .faq-question' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'question_hover_color',
			[
				'label' => __( 'Hover Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .faq-question:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'question_padding',
			[
				'label' => __( 'Padding', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .faq-question' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Style Section - Answers
		$this->start_controls_section(
			'answer_style_section',
			[
				'label' => __( 'Answers', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'answer_typography',
				'selector' => '{{WRAPPER}} .faq-answer-content',
			]
		);

		$this->add_control(
			'answer_color',
			[
				'label' => __( 'Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .faq-answer-content' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'answer_padding',
			[
				'label' => __( 'Padding', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .faq-answer-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Style Section - Icon
		$this->start_controls_section(
			'icon_style_section',
			[
				'label' => __( 'Icon', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'icon_size',
			[
				'label' => __( 'Icon Size', 'listeo_elementor' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 12,
						'max' => 48,
						'step' => 1,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 24,
				],
				'selectors' => [
					'{{WRAPPER}} .faq-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .faq-icon::before' => 'width: calc({{SIZE}}{{UNIT}} * 0.67);',
					'{{WRAPPER}} .faq-icon::after' => 'height: calc({{SIZE}}{{UNIT}} * 0.67);',
				],
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label' => __( 'Icon Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .faq-icon::before, {{WRAPPER}} .faq-icon::after' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'icon_active_color',
			[
				'label' => __( 'Active Icon Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .faq-item.active .faq-icon::before, {{WRAPPER}} .faq-item.active .faq-icon::after' => 'background-color: {{VALUE}};',
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
	protected function render() {
		$settings = $this->get_settings_for_display();
		
		if ( empty( $settings['faq_items'] ) ) {
			echo '<p>' . __( 'No FAQ items to display. Please add some FAQ items in the widget settings.', 'listeo_elementor' ) . '</p>';
			return;
		}
		?>
		
		<div class="faq-container" data-behavior="<?php echo esc_attr( $settings['accordion_behavior'] ); ?>">
			<?php if ( ! empty( $settings['show_title'] ) && $settings['show_title'] === 'yes' && ! empty( $settings['faq_title'] ) ) : ?>
				<h2 class="faq-title"><?php echo esc_html( $settings['faq_title'] ); ?></h2>
			<?php endif; ?>
			
			<?php foreach ( $settings['faq_items'] as $index => $item ) : ?>
				<div class="faq-item <?php echo ! empty( $item['is_active'] ) && $item['is_active'] === 'yes' ? 'active' : ''; ?>">
					<button class="faq-question" type="button" aria-expanded="<?php echo ! empty( $item['is_active'] ) && $item['is_active'] === 'yes' ? 'true' : 'false'; ?>">
						<?php echo esc_html( $item['question'] ); ?>
						<span class="faq-icon" aria-hidden="true"></span>
					</button>
					<div class="faq-answer">
						<div class="faq-answer-content">
							<?php echo wp_kses_post( wpautop( $item['answer'] ) ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
