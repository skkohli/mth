<?php
/**
 * listeo class.
 *
 * @category   Class
 * @package    Elementorlisteo
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

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * listeo widget class.
 *
 * @since 1.0.0
 */
class Headline extends Widget_Base {

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
		return 'listeo-headline';
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
		return __( 'Headline', 'listeo_elementor' );
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
		return 'eicon-editor-h1';
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
				'label' => __( 'Content', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'listeo_elementor' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Title', 'listeo_elementor' ),
			)
		);	
		$this->add_control(
			'subtitle',
			array(
				'label'   => __( 'Subtitle', 'listeo_elementor' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
		  'style_section',
		  [
		    'label' => __( 'Style Section', 'listeo_elementor' ),
		    'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		  ]
		);

		$this->add_control(
			'type',
			[
				'label' => __( 'Element tag ', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [
					'h1' => __( 'H1', 'listeo_elementor' ),
					'h2' => __( 'H2', 'listeo_elementor' ),
					'h3' => __( 'H3', 'listeo_elementor' ),
					'h4' => __( 'H4', 'listeo_elementor' ),
					'h5' => __( 'H5', 'listeo_elementor' ),
				],
			]
		);


		$this->add_control(
		  'text_align',
		  [
		    'label' => __( 'Text align', 'listeo_elementor' ),
		    'type' => \Elementor\Controls_Manager::CHOOSE,
		    'options' => [
		      'left' => [
		        'title' => __( 'Left', 'listeo_elementor' ),
		        'icon' => 'fa fa-align-left',
		      ],
		      'center' => [
		        'title' => __( 'Center', 'listeo_elementor' ),
		        'icon' => 'fa fa-align-center',
		      ],
		      'right' => [
		        'title' => __( 'Right', 'listeo_elementor' ),
		        'icon' => 'fa fa-align-right',
		      ],
		    ],
		    'default' => 'center',
		    'toggle' => true,
		  ]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'title_typography',
				'label' => __( 'Title Typography', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .headline',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'subtitle_typography',
				'label' => __( 'Subtitle Typography', 'listeo_elementor' ),
				'selector' => '{{WRAPPER}} .headline span',
			]
		);

		$this->add_control(
			'with_border',
			[
				'label' => __( 'With Border', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label' => __( 'Title Color', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .headline' => 'color: {{VALUE}} !important;',
				],
			]
		);

		$this->add_control(
			'subtitle_color',
			[
				'label' => __( 'Subtitle Color', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .headline span' => 'color: {{VALUE}} !important;',
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
	protected function render() {
		$settings = $this->get_settings_for_display();

		$this->add_inline_editing_attributes( 'title', 'none' );
		$this->add_inline_editing_attributes( 'subtitle', 'none' );
		$css_class = 'headline ';
		if(isset($settings['text_align'])) {
				switch ($settings['text_align']) {
					case 'left':
						$css_class .= ' headline-aligned-to-left ';
						break;
					case 'right':
						$css_class .= ' headline-aligned-to-right ';
						break;
					case 'center':
						$css_class .= ' headline-aligned-to-center headline-extra-spacing';
						break;
					
					default:
						# code...
						break;
				}	
			}
		if ( 'yes' === $settings['with_border'] ) {
			$css_class .= ' headline-box ';
		}

		if ( !empty($settings['subtitle']) ) {
			$css_class .= ' headline-with-subtitle ';
		}
		$style = 'style="';
		$style .= (isset($settings['text_align'])) ? 'text-align:'.$settings['text_align'].';' : '' ;
		$style .= '"';
		?>
		<<?php echo  $settings['type']; ?> 
		<?php echo $style; ?> class="<?php echo esc_attr($css_class); ?>"> <?php echo $settings['title']; ?> <?php if($settings['subtitle']) : ?> <span <?php echo $this->get_render_attribute_string( 'subtitle' ); ?>> <?php echo $settings['subtitle']; ?></span><?php endif; ?></<?php echo $settings['type'] ?>>
		<?php
	}

	/**
	 * Render the widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */

}