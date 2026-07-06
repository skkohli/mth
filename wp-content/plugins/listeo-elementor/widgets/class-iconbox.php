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
class IconBox extends Widget_Base
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
		return 'listeo-iconbox';
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
		return __('IconBox', 'listeo_elementor');
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
		return 'eicon-image-box';
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
			'title',
			array(
				'label'   => __('Title', 'listeo_elementor'),
				'type'    => Controls_Manager::TEXT,
				'default' => __('Title', 'listeo_elementor'),
				'label_block' => true,
				'dynamic' => [
					'active' => true,
				],
			)
		);

		$this->add_control(
			'content',
			array(
				'label'   => __('Content', 'listeo_elementor'),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => __('Content', 'listeo_elementor'),
				'label_block' => true,
				'dynamic' => [
					'active' => true,
				],
			)
		);
		$this->add_control(
			'url',
			[
				'label' => __('Link', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => __('https://your-link.com', 'listeo_elementor'),
				'show_external' => true,
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section',
			[
				'label' => __('Style Section', 'listeo_elementor'),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'style',
			[
				'label' => __('Type of iconbox', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'top',
				'options' => [
					'top' =>  __('Icon on top', 'listeo_elementor'),
					'next' =>  __('Icon on the right. ', 'listeo_elementor'),
					'color' => __('Icon with color. ', 'listeo_elementor'),


				],
			]
		);

		$this->add_control(
			'icon',
			[
				'label' => __('Icon', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-star',
					'library' => 'solid',
				],
			]
		);
		
		// add colorpicker control for icon if  setting style is set to color
		$this->add_control(
			'color',
			[
				'label' => __('Icon Color', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#000000',
				'condition' => [
					'style' => 'color',
				],
				'selectors' => [
					'{{WRAPPER}} .ibc-ico' => 'background-color: {{VALUE}};',
				],
			]
		);
		// add colorpicker control for icon if  setting style is set to color
		$this->add_control(
			'bg_color',
			[
				'label' => __('Icon Background Color', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ff0000',
				'alpha' => true, 
				'condition' => [
					'style' => 'color',
				],
				'selectors' => [
					'{{WRAPPER}} .ibc-ico i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ibc-ico svg g, {{WRAPPER}} .ibc-ico svg circle, {{WRAPPER}} .ibc-ico svg rect, {{WRAPPER}} .ibc-ico svg path' => 'fill: {{VALUE}};',
				],
			]
		);
		





		/* Add the options you'd like to show in this tab here */

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

		$this->add_inline_editing_attributes('title', 'none');
		$this->add_inline_editing_attributes('subtitle', 'none');
		$target = $settings['url']['is_external'] ? ' target="_blank"' : '';
		$nofollow = $settings['url']['nofollow'] ? ' rel="nofollow"' : '';

		if ($settings['style'] == 'top') {
			if (!empty($settings['url']['url'])) {
				echo '<a href="' . $settings['url']['url'] . '"' . $target . $nofollow . '>';
			} ?>

			<div class="icon-box-2 ">
				<?php if (isset($settings['icon']['library']) && $settings['icon']['library'] == 'svg') { ?>
					<i class="listeo-svg-icon-box"><?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);  ?></i>
				<?php } else {
					\Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);
				} ?>
				<h3><?php echo $settings['title'] ?></h3>
				<p><?php echo $settings['content'] ?></p>
			</div>

			<?php
			if (!empty($settings['url']['url'])) {
				echo "</a>";
			}
		} elseif ($settings['style'] == 'next') { ?>
			<a href="<?php echo $settings['url']['url']; ?>" class="icon-box-v3">
				<div class="ibv3-icon">
					<?php if (isset($settings['icon']['library']) && $settings['icon']['library'] == 'svg') { ?>
						<i class="listeo-svg-icon-box"><?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);  ?></i>
					<?php } else {
						\Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);
					} ?>
				</div>
				<div class="ibv3-content">
					<h4><?php echo $this->get_settings('title') ?></h4>
					<p><?php echo $this->get_settings('content') ?></p>
				</div>
			</a>
		<?php } elseif ($settings['style'] == 'color') { ?>
			<?php if (!empty($settings['url']['url'])) {
				echo '<a href="' . $settings['url']['url'] . '"' . $target . $nofollow . '>';
			} ?>
			<div class="icon-box-color-icon">
				<div class="ibc-ico"><?php if (isset($settings['icon']['library']) && $settings['icon']['library'] == 'svg') { ?>
						<i class="listeo-svg-icon-box"><?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);  ?></i>
					<?php } else {
											\Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);
										} ?>
				</div>
				<h3><?php echo $this->get_settings('title') ?></h3>
				<p><?php echo $this->get_settings('content') ?></p>
			</div>
<?php if (!empty($settings['url']['url'])) {
				echo "</a>";
			}
		}
	}
}
