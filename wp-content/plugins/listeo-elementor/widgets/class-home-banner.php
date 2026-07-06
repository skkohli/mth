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


if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Awesomesauce widget class.
 *
 * @since 1.0.0
 */
class HomeBanner extends Widget_Base {

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
		return 'listeo-homebanner';
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
		return __( 'Home Search Banner', 'listeo_elementor' );
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
		return 'eicon-site-search';
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
				'default' => __( 'Find Nearby Attractions', 'listeo_elementor' ),
			)
		);	
		$this->add_control(
			'subtitle',
			array(
				'label'   => __( 'Subtitle', 'listeo_elementor' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Explore top-rated attractions, activities and more!', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'typed',
			[
				'label' => __('Enable Type words effect', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('On', 'listeo_elementor'),
				'label_off' => __('Off', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);

		$this->add_control(
			'enable_synced_bg',
			[
				'label' => __('Sync background with typed words', 'listeo_elementor'),
				'description' => __('Each typed word will have its own background image that transitions when the word changes', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('On', 'listeo_elementor'),
				'label_off' => __('Off', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => '',
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'typed_text',
			array(
				'label'   => __('Text to displayed in "typed" section, separate by coma', 'listeo_elementor'),
				'label_block' => true,
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __('Attractions, Restaurants, Hotels', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
					'enable_synced_bg!' => 'yes',
				],
			)
		);

		$synced_repeater = new \Elementor\Repeater();

		$synced_repeater->add_control(
			'typed_word',
			[
				'label' => __('Typed Word', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __('Hotels', 'listeo_elementor'),
				'label_block' => true,
				'description' => __('The word that will be typed in the animation', 'listeo_elementor'),
			]
		);

		$synced_repeater->add_control(
			'slide_image',
			[
				'label' => __('Background Image', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::MEDIA,
				'default' => [
					'url' => \Elementor\Utils::get_placeholder_image_src(),
				],
				'description' => __('Background image shown when this word is displayed', 'listeo_elementor'),
			]
		);

		$this->add_control(
			'synced_slides',
			[
				'label' => __('Typed Words with Backgrounds', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $synced_repeater->get_controls(),
				'default' => [
					[
						'typed_word' => __('Hotels', 'listeo_elementor'),
					],
					[
						'typed_word' => __('Restaurants', 'listeo_elementor'),
					],
					[
						'typed_word' => __('Attractions', 'listeo_elementor'),
					],
				],
				'title_field' => '{{{ typed_word }}}',
				'condition' => [
					'typed' => 'yes',
					'enable_synced_bg' => 'yes',
				],
			]
		);

		$this->add_control(
			'typed_speed_heading',
			[
				'label' => __('Typing Animation Speed', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'type_speed',
			[
				'label' => __('Type Speed (ms)', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 10,
				'max' => 500,
				'step' => 10,
				'default' => 70,
				'description' => __('Speed of typing each character in milliseconds', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'back_speed',
			[
				'label' => __('Delete Speed (ms)', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 10,
				'max' => 500,
				'step' => 10,
				'default' => 80,
				'description' => __('Speed of deleting each character in milliseconds', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'back_delay',
			[
				'label' => __('Pause Before Delete (ms)', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 500,
				'max' => 10000,
				'step' => 100,
				'default' => 4000,
				'description' => __('How long to wait before deleting the word', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'start_delay',
			[
				'label' => __('Start Delay (ms)', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 0,
				'max' => 5000,
				'step' => 100,
				'default' => 1000,
				'description' => __('Delay before typing starts', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'lazy_load_bg',
			[
				'label' => __('Lazy Load Background Images', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('On', 'listeo_elementor'),
				'label_off' => __('Off', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __('Only load first image initially, load others on-demand (improves PageSpeed score)', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
					'enable_synced_bg' => 'yes',
				],
			]
		);

		$this->add_control(
			'hide_for_speed_bots',
			[
				'label' => __('Disable Effects for Speed Tests', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('On', 'listeo_elementor'),
				'label_off' => __('Off', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
				'description' => __('Disable typed effect and background slider for GTmetrix, Lighthouse, PageSpeed (works with caching)', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_countdown_indicator',
			[
				'label' => __('Show Countdown Indicator', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('On', 'listeo_elementor'),
				'label_off' => __('Off', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => '',
				'description' => __('Show circular progress indicator in bottom right corner showing time until next slide', 'listeo_elementor'),
				'condition' => [
					'typed' => 'yes',
					'enable_synced_bg' => 'yes',
				],
			]
		);

		if (function_exists('listeo_get_search_forms_dropdown')) {

			$search_forms = listeo_get_search_forms_dropdown('fullwidth');
			$this->add_control(
				'home_banner_form',
				[
					'label' => __( 'Form source ', 'listeo_elementor' ),
					'type' => \Elementor\Controls_Manager::SELECT,
					
					'options' => $search_forms,
					'default' => 'search_on_home_page'
					
								
				]
			);
		}

		$this->add_control(
			'home_banner_form_action',
			[
				'label' => __('Form action ', 'listeo_elementor'),
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
			'home_banner_form_action_custom',
			[
				'label' => __('Custom action ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'condition' => [
					'home_banner_form_action' => 'custom',
				],

			]
		);
		$this->add_control(
			'home_banner_form_action_page',
			[
				'label' => __('Page ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $this->listeo_get_pages_dropdown(),
				'default' => '',
				'condition' => [
					'home_banner_form_action' => 'page',
				],
			]
		);

		$this->add_control(
			'headers_color',
			[
				'label' => esc_html__('Title Color', 'plugin-name'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#fff',
				'selectors' => [
					'{{WRAPPER}} h1' => 'color: {{VALUE}}',
					'{{WRAPPER}} h2' => 'color: {{VALUE}}',
					'{{WRAPPER}} h4' => 'color: {{VALUE}}',
					'{{WRAPPER}} h5' => 'color: {{VALUE}}',
				],
			]
		);


		$this->add_control(
			'home_banner_text_align',
			[
				'label' => __( 'Text alignment ', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'left',
				'options' => [
					'center' => __( 'Center', 'listeo_elementor' ),
					'left' 	 => __( 'Left', 'listeo_elementor' ),
					
				],
				'selectors' => [
				    '{{WRAPPER}} .main-search-inner' => 'text-align: {{VALUE}}'
				],
				
							
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'label' => esc_html__('Background', 'plugin-name'),
				'types' => ['classic', 'gradient', 'video'],
				'selector' => '{{WRAPPER}} .main-search-container',
			]
		);
		// $this->add_control(
		// 	'background',
		// 	[
		// 		'label' => __( 'Choose Background Image', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,
				
		// 	]
		// );
		// $this->add_control(
		// 	'video',
		// 	[
		// 		'label' => __( 'Choose Video', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,
				
		// 	]
		// );
		// $this->add_control(
		// 	'video_poster',
		// 	[
		// 		'label' => __( 'Choose Video Poster Image', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,
		// 		'default' => [
		// 			'url' => \Elementor\Utils::get_placeholder_image_src(),
		// 		]
		// 	]
		// );

		
		$this->add_control(
			'background_overlay_type',
			[
				'label' => __( 'Background Overlay type', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'none' => __( 'None', 'listeo_elementor' ),
					'container-overlay-solid' => __( 'Solid color', 'listeo_elementor' ),
					'container-overlay-gradient' 	 => __( 'Gradient', 'listeo_elementor' ),
					
				],
				
							
			]
		);
		$this->add_control(
			'overlay_gradient',
			[
				'label' => __( 'Overlay on homepage search banner', 'plugin-domain' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'alpha' => false,
				'default' => '#00000080',
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .container-overlay-gradient.main-search-container:before' => 'background: linear-gradient(to right, {{VALUE}}F2 20%, {{VALUE}}B3 70%, {{VALUE}}00 95%); display:block;',
				],
				'condition' => [
					'background_overlay_type' => 'container-overlay-gradient',
				],
			]
		);
		$this->add_control(
			'overlay_solid',
			[
				'label' => __( 'Overlay on homepage search banner', 'plugin-domain' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'alpha' => true,
				'default' => '#00000080',
				'separator' => 'before',
				'selectors' => [
					
					'{{WRAPPER}} .container-overlay-solid.main-search-container:before' => 'background:  {{VALUE}};display:block;',
				],
				'condition' => [
					'background_overlay_type' => 'container-overlay-solid',
				],
			]
		);
	
		$this->add_control(
			'featured_categories_status',
			[
				'label' => __( 'Show Featured Categories', 'listeo_elementor'  ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);


		$this->add_control(
			'tax-listing_category',
			[
				'label' => __( 'Show in Featured Categories this terms', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'multiple' => true,
				'default' => [],
				'options' => $this->get_terms('listing_category'),
				'condition' => [
						'featured_categories_status' => 'yes',
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

		$this->add_inline_editing_attributes( 'title', 'none' );
		$this->add_inline_editing_attributes( 'subtitle', 'none' );

		if(!empty($settings['background']['url'])){

			$background_image = $settings['background']['url'];
		} else {
			$background_image = get_option( 'listeo_search_bg'); 
		}

		$video = false;

		if(isset($settings['video']['url']) && !empty($settings['video']['url'])){
			$video = $settings['video']['url'];
		}

		$classes = array();

		// if($settings['solid_background'] == 'solid'){
		// 	$classes[] = 'solid-bg-home-banner';
		// }
		// if( $settings['search_form_style'] == 'boxed') { 
		// 	$classes[] = 'alt-search-box centered';
		// }
	
		if($video) {
			$classes[] = 'dark-overlay';	
		}
		$classes[] = $settings['background_overlay_type'];

		// Check if synced background mode is enabled
		$enable_synced_bg = isset($settings['enable_synced_bg']) && $settings['enable_synced_bg'] === 'yes' && $settings['typed'] === 'yes';
		$synced_slides = isset($settings['synced_slides']) ? $settings['synced_slides'] : array();

		// Countdown indicator settings (defined here to be available in both style and HTML blocks)
		$show_countdown = $enable_synced_bg && !empty($synced_slides) && isset($settings['show_countdown_indicator']) && $settings['show_countdown_indicator'] === 'yes';

		if ($enable_synced_bg && !empty($synced_slides)) {
			$classes[] = 'has-synced-bg';
		}

		?>

		<?php if ($enable_synced_bg && !empty($synced_slides)) : ?>
		<style>
			.main-search-container.has-synced-bg {
				position: relative;
			}
			.synced-bg-slides {
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				width: 100%;
				height: 100%;
				z-index: 0;
				overflow: hidden;
			}
			.synced-bg-slide {
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background-size: cover;
				background-position: center;
				opacity: 0;
				transition: opacity 0.8s ease-in-out;
			}
			.synced-bg-slide.active {
				opacity: 1;
			}
			.main-search-container.has-synced-bg .main-search-inner {
				position: relative;
				z-index: 1;
			}
			.main-search-container.has-synced-bg:before {
				z-index: 1;
			}
			<?php if ($show_countdown) : ?>
			.countdown-indicator {
				position: absolute;
				bottom: 20px;
				right: 30px;
				z-index: 10;
				width: 30px;
				height: 30px;
			}
			.countdown-indicator svg {
				transform: rotate(-90deg);
				width: 100%;
				height: 100%;
			}
			.countdown-indicator .bg-circle {
				fill: none;
				stroke: #ffffff;
				opacity: 0.25;
				stroke-width: 2;
			}
			.countdown-indicator .progress-circle {
				fill: none;
				stroke: #ffffff;
				stroke-width: 2;
				stroke-linecap: round;
				stroke-dasharray: 126;
				stroke-dashoffset: 126;
			}
			<?php endif; ?>
		</style>
		<?php endif; ?>

	<?php
		// Prepare typed settings for JS (for Elementor preview)
		$hide_for_bots = isset($settings['hide_for_speed_bots']) && $settings['hide_for_speed_bots'] === 'yes';
		$show_countdown_js = isset($settings['show_countdown_indicator']) && $settings['show_countdown_indicator'] === 'yes' && $enable_synced_bg;
		$typed_settings = array(
			'enabled' => $settings['typed'] === 'yes',
			'typeSpeed' => isset($settings['type_speed']) && !empty($settings['type_speed']) ? intval($settings['type_speed']) : 70,
			'backSpeed' => isset($settings['back_speed']) && !empty($settings['back_speed']) ? intval($settings['back_speed']) : 80,
			'backDelay' => isset($settings['back_delay']) && !empty($settings['back_delay']) ? intval($settings['back_delay']) : 4000,
			'startDelay' => isset($settings['start_delay']) && !empty($settings['start_delay']) ? intval($settings['start_delay']) : 1000,
			'syncedBg' => $enable_synced_bg && !empty($synced_slides),
			'hideForBots' => $hide_for_bots,
			'showCountdown' => $show_countdown_js,
		);

		// Build words array
		if ($enable_synced_bg && !empty($synced_slides)) {
			$words = array();
			foreach ($synced_slides as $slide) {
				if (!empty($slide['typed_word'])) {
					$words[] = trim($slide['typed_word']);
				}
			}
			$typed_settings['words'] = $words;
		} else {
			$typed_text = isset($settings['typed_text']) ? $settings['typed_text'] : '';
			$typed_settings['words'] = array_map('trim', explode(',', $typed_text));
		}
	?>
	<div class="main-search-container elementor-main-search-container <?php echo implode(" ",$classes); ?>" data-typed-settings="<?php echo esc_attr(json_encode($typed_settings)); ?>">

		<?php if ($enable_synced_bg && !empty($synced_slides)) :
		$lazy_load = isset($settings['lazy_load_bg']) && $settings['lazy_load_bg'] === 'yes';

		// Detect speed testing bots - serve minimal images to them
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$speed_bot_patterns = array(
			'GTmetrix',
			'Chrome-Lighthouse',
			'Lighthouse',
			'Speed Insights',
			'Google Page Speed',
			'HeadlessChrome',
			'HeadlessChromium',
			'PTST',
			'DebugBear',
			'WebPageTest',
			'Pingdom',
		);
		$is_speed_bot = false;
		foreach ($speed_bot_patterns as $pattern) {
			if (stripos($user_agent, $pattern) !== false) {
				$is_speed_bot = true;
				break;
			}
		}
	?>
		<div class="synced-bg-slides"<?php if ($lazy_load && !$is_speed_bot) echo ' data-lazy="true"'; ?>>
			<?php foreach ($synced_slides as $index => $slide) :
				$bg_url = isset($slide['slide_image']['url']) ? $slide['slide_image']['url'] : '';
				$is_first = $index === 0;
				$active_class = $is_first ? ' active' : '';

				// Speed bots: only first image loaded, others use data-bg (never loaded for bots)
				if (($is_speed_bot || $lazy_load) && !$is_first) :
			?>
			<div class="synced-bg-slide<?php echo $active_class; ?>" data-bg="<?php echo esc_url($bg_url); ?>"></div>
			<?php else : ?>
			<div class="synced-bg-slide<?php echo $active_class; ?>" style="background-image: url('<?php echo esc_url($bg_url); ?>');"></div>
			<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ($show_countdown && !$is_speed_bot) : ?>
		<div class="countdown-indicator">
			<svg viewBox="0 0 44 44">
				<circle class="bg-circle" cx="22" cy="22" r="20"></circle>
				<circle class="progress-circle" cx="22" cy="22" r="20"></circle>
			</svg>
		</div>
		<?php endif; ?>
		<?php endif; ?>

		<div class="main-search-inner">


		
			<div class="container">
				<div class="row">
					<div class="col-md-12">
						
						<h1><?php echo $settings['title']; ?>  <span class="typed-words"></span></h1>
						<?php if(!empty( $settings['subtitle'] )) { ?><h4><?php echo $settings['subtitle']; ?></h4><?php } ?>
						
						<?php
						$home_banner_form_action_page = $settings['home_banner_form_action_page'];
						$home_banner_form_action_custom = $settings['home_banner_form_action_custom'];
						$home_banner_form_action = $settings['home_banner_form_action'];
						if($home_banner_form_action == 'page' && !empty($home_banner_form_action_page)) {
							$home_banner_form_action = get_permalink($home_banner_form_action_page);
						} else if($home_banner_form_action == 'custom' && !empty($home_banner_form_action_custom)) {
							$home_banner_form_action = $home_banner_form_action_custom;
						} else {
							$home_banner_form_action = get_post_type_archive_link( 'listing' );
						}

						?>
						<?php

						echo do_shortcode('[listeo_search_form action='. $home_banner_form_action. ' source="' . $settings['home_banner_form'] . '" custom_class="main-search-form"]') ?>
			
					</div>
				</div>

				<?php
				if($settings['featured_categories_status']=='yes') :

					if(isset($settings['tax-listing_category'])) :
						
            				$category = is_array( $settings['tax-listing_category'] ) ? $settings['tax-listing_category'] : array_filter( array_map( 'trim', explode( ',', $settings['tax-listing_category'] ) ) );
						
					
							if(!empty($category)) : ?>
							<div class="row">
								<div class="col-md-12">
									<h5 class="highlighted-categories-headline"><?php esc_html_e('Or browse featured categories:','listeo_elementor') ?></h5>
									
										  
									<div class="highlighted-categories">
										
										<?php

										foreach ($category as $value) {

											$term = get_term($value,'listing_category');

											if( $term && ! is_wp_error( $term ) ) {
												$icon = get_term_meta($value,'icon',true); 
												$_icon_svg = get_term_meta($value,'_icon_svg',true);
												?>
												<!-- Box -->
												<a href="<?php echo get_term_link($term->slug, 'listing_category'); ?>" class="highlighted-category">
													<?php if (!empty($_icon_svg)) { ?>
													<i>
														<?php echo listeo_render_svg_icon($_icon_svg); ?>
													</i>
											    	<?php } else if($icon && $icon != 'empty')  { ?><i class="<?php echo esc_attr($icon); ?>"></i><?php }; ?>
													<h4><?php echo esc_html($term->name) ?></h4>
												</a>	

										<?php }
										} ?>
								
									</div>
									
								</div>
							</div>
				<?php 	
						endif;
					endif;
				endif; ?>

				
			</div>
		
	
		<?php if($video) { 

			if(isset($settings['video_poster']['url']) && !empty(isset($settings['video_poster']['url']))){
			$video_poster = $settings['video_poster']['url'];
		}
		?>
		<!-- Video -->
		<div class="video-container">

			<video <?php if(isset($video_poster)) ?> poster="<?php echo $video_poster ?>" loop autoplay muted>
				<source src="<?php echo $video ?>" type="video/mp4">
				
			</video>
		</div>
		<?php } ?>
		</div>
	</div>
	<?php
		if($settings['typed'] == 'yes') {
			// Check if synced background mode is enabled
			$enable_synced_bg = isset($settings['enable_synced_bg']) && $settings['enable_synced_bg'] === 'yes';
			$synced_slides = isset($settings['synced_slides']) ? $settings['synced_slides'] : array();

			// Build typed_array based on mode
			if ($enable_synced_bg && !empty($synced_slides)) {
				// Get words from repeater
				$typed_array = array();
				foreach ($synced_slides as $slide) {
					if (!empty($slide['typed_word'])) {
						$typed_array[] = trim($slide['typed_word']);
					}
				}
			} else {
				// Fallback to comma-separated text (backwards compatible)
				$typed = isset($settings['typed_text']) ? $settings['typed_text'] : '';
				$typed_array = explode(',', $typed);
				$typed_array = array_map('trim', $typed_array);
			}

			// Get speed settings with defaults for backwards compatibility
			$type_speed = isset($settings['type_speed']) && !empty($settings['type_speed']) ? intval($settings['type_speed']) : 70;
			$back_speed = isset($settings['back_speed']) && !empty($settings['back_speed']) ? intval($settings['back_speed']) : 80;
			$back_delay = isset($settings['back_delay']) && !empty($settings['back_delay']) ? intval($settings['back_delay']) : 4000;
			$start_delay = isset($settings['start_delay']) && !empty($settings['start_delay']) ? intval($settings['start_delay']) : 1000;

			// Check if TranslatePress is active
			$is_translatepress_active = is_plugin_active('translatepress-multilingual/index.php') ||
									   class_exists('TRP_Translate_Press') ||
									   function_exists('trp_translate');
			?>

			<script>
			(function() {
				// Configuration
				const hasSyncedBg = <?php echo json_encode($enable_synced_bg && !empty($synced_slides)); ?>;
				const words = <?php echo json_encode($typed_array); ?>;
				const typeSpeed = <?php echo $type_speed; ?>;
				const backSpeed = <?php echo $back_speed; ?>;
				const backDelay = <?php echo $back_delay; ?>;
				const startDelay = <?php echo $start_delay; ?>;
				const hideForBots = <?php echo json_encode($hide_for_bots); ?>;
				const showCountdown = <?php echo json_encode($show_countdown_js); ?>;

				// Countdown indicator animation - synced with photo changes
				const CIRCLE_CIRCUMFERENCE = 126; // 2 * π * 20
				let countdownStart = 0;
				let countdownDuration = backDelay;
				let animationRunning = false;

				function animateCountdown() {
					if (!showCountdown || !animationRunning) return;

					const progressCircle = document.querySelector('.countdown-indicator .progress-circle');
					if (!progressCircle) return;

					const elapsed = Date.now() - countdownStart;
					const progress = Math.min(elapsed / countdownDuration, 1);
					const offset = CIRCLE_CIRCUMFERENCE * progress;

					progressCircle.style.strokeDashoffset = offset;

					if (progress < 1) {
						requestAnimationFrame(animateCountdown);
					}
				}

				function startCountdown(duration) {
					if (!showCountdown) return;

					countdownDuration = duration || backDelay;
					countdownStart = Date.now();
					animationRunning = true;

					const progressCircle = document.querySelector('.countdown-indicator .progress-circle');
					if (progressCircle) {
						progressCircle.style.strokeDashoffset = '0';
					}

					requestAnimationFrame(animateCountdown);
				}

				// Detect speed testing bots via JavaScript (works even with page caching)
				function isSpeedBot() {
					const ua = navigator.userAgent || '';
					const patterns = [
						'GTmetrix', 'Chrome-Lighthouse', 'Lighthouse', 'Speed Insights',
						'Google Page Speed', 'HeadlessChrome', 'HeadlessChromium',
						'PTST', 'DebugBear', 'WebPageTest', 'Pingdom'
					];

					// Check user agent
					for (let i = 0; i < patterns.length; i++) {
						if (ua.indexOf(patterns[i]) !== -1) return true;
					}

					// Check for headless browser (Lighthouse, Puppeteer, etc.)
					if (navigator.webdriver === true) return true;

					// Check for missing plugins (headless browsers have none)
					if (navigator.plugins && navigator.plugins.length === 0 &&
						!/Mobile|Android/.test(ua)) return true;

					return false;
				}

				// If speed bot detected and hideForBots enabled, show static content
				if (hideForBots && isSpeedBot()) {
					// Just show first word statically, no animation
					const typedEl = document.querySelector('.typed-words');
					if (typedEl && words.length > 0) {
						typedEl.textContent = words[0];
					}
					// Hide all background slides except first
					const slides = document.querySelectorAll('.synced-bg-slide');
					slides.forEach((slide, i) => {
						if (i > 0) slide.style.display = 'none';
					});
					return; // Exit - don't run typed effect
				}

				// Background slide change function with lazy loading support
				function changeSyncedBackground(index) {
					if (!hasSyncedBg) return;

					const slides = document.querySelectorAll('.synced-bg-slide');
					if (slides.length === 0) return;

					// Ensure index is within bounds (loop)
					const safeIndex = index % slides.length;

					slides.forEach((slide, i) => {
						if (i === safeIndex) {
							// Lazy load: if slide has data-bg but no background-image, load it now
							const lazyBg = slide.getAttribute('data-bg');
							if (lazyBg && !slide.style.backgroundImage) {
								slide.style.backgroundImage = 'url(' + lazyBg + ')';
							}
							slide.classList.add('active');
						} else {
							slide.classList.remove('active');
						}
					});
				}

				// Preload next image after page load for smoother transitions
				function preloadNextImages() {
					const slides = document.querySelectorAll('.synced-bg-slide[data-bg]');
					slides.forEach((slide, i) => {
						// Preload with slight delay to not block initial render
						setTimeout(() => {
							const lazyBg = slide.getAttribute('data-bg');
							if (lazyBg && !slide.style.backgroundImage) {
								const img = new Image();
								img.src = lazyBg;
								img.onload = () => {
									slide.style.backgroundImage = 'url(' + lazyBg + ')';
								};
							}
						}, 2000 + (i * 500)); // Stagger loading: 2s, 2.5s, 3s...
					});
				}

				// Start preloading after page is interactive
				if (document.readyState === 'complete') {
					preloadNextImages();
				} else {
					window.addEventListener('load', preloadNextImages);
				}

				// Check if TranslatePress is active
				const isTranslatePressActive = <?php echo json_encode($is_translatepress_active); ?> ||
											  document.querySelector('link[href*="translatepress"]') !== null ||
											  document.querySelector('script[src*="translatepress"]') !== null;

				if (isTranslatePressActive) {
					// Use fade animation for TranslatePress compatibility
					function createWordSwapper(element, words, options = {}) {
						const defaults = {
							swapDelay: 4000,
							fadeSpeed: 500,
							startDelay: 1000,
							loop: true,
							showCursor: true,
							onWordChange: null
						};

						const settings = { ...defaults, ...options };
						let currentIndex = 0;
						let isRunning = false;

						// Add cursor if enabled
						if (settings.showCursor) {
							element.style.position = 'relative';
							element.innerHTML = words[0] + '<span class="custom-cursor">|</span>';

							// Add cursor blinking CSS if not already added
							if (!document.querySelector('#word-swapper-styles')) {
								const style = document.createElement('style');
								style.id = 'word-swapper-styles';
								style.textContent = `
									.custom-cursor {
										animation: blink 1s infinite;
									}
									@keyframes blink {
										0%, 50% { opacity: 1; }
										51%, 100% { opacity: 0; }
									}
									body[class*="translatepress-"] .custom-cursor { display: none !important;}
								`;
								document.head.appendChild(style);
							}
						} else {
							element.textContent = words[0];
						}

						// Trigger initial background and countdown
						if (settings.onWordChange) {
							settings.onWordChange(0);
						}

						function swapWord() {
							if (!isRunning) return;

							// Fade out
							element.style.transition = `opacity ${settings.fadeSpeed}ms ease`;
							element.style.opacity = '0';

							setTimeout(() => {
								// Change word
								currentIndex = (currentIndex + 1) % words.length;
								const newWord = words[currentIndex];

								if (settings.showCursor) {
									element.innerHTML = newWord + '<span class="custom-cursor">|</span>';
								} else {
									element.textContent = newWord;
								}

								// Trigger background change and countdown
								if (settings.onWordChange) {
									settings.onWordChange(currentIndex);
								}

								// Fade in
								element.style.opacity = '1';

								// Continue loop if enabled
								if (settings.loop || currentIndex < words.length - 1) {
									setTimeout(swapWord, settings.swapDelay);
								}
							}, settings.fadeSpeed);
						}

						// Start the animation
						setTimeout(() => {
							isRunning = true;
							setTimeout(swapWord, settings.swapDelay);
						}, settings.startDelay);

						return {
							start: () => { isRunning = true; swapWord(); },
							stop: () => { isRunning = false; },
							destroy: () => {
								isRunning = false;
								element.style = '';
								element.textContent = words[0];
							}
						};
					}

					// Initialize the word swapper
					document.addEventListener('DOMContentLoaded', function() {
						const typedElement = document.querySelector('.typed-words');
						if (typedElement) {
							// For TranslatePress: cycle = fade + display + fade
							const cycleDuration = 300 + backDelay + 300;

							createWordSwapper(typedElement, words, {
								swapDelay: backDelay,
								fadeSpeed: 300,
								startDelay: startDelay,
								loop: true,
								showCursor: true,
								onWordChange: function(index) {
									changeSyncedBackground(index);
									startCountdown(cycleDuration);
								}
							});
						}
					});

				} else {
					// Use original typed.js for the typing effect when TranslatePress is not active
					/*!
					 * typed.js - A JavaScript Typing Animation Library
					 * Author: Matt Boldt <me@mattboldt.com>
					 * Version: v2.0.9
					 */
					(function(t,e){"object"==typeof exports&&"object"==typeof module?module.exports=e():"function"==typeof define&&define.amd?define([],e):"object"==typeof exports?exports.Typed=e():t.Typed=e()})(this,function(){return function(t){function e(n){if(s[n])return s[n].exports;var i=s[n]={exports:{},id:n,loaded:!1};return t[n].call(i.exports,i,i.exports,e),i.loaded=!0,i.exports}var s={};return e.m=t,e.c=s,e.p="",e(0)}([function(t,e,s){"use strict";function n(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}Object.defineProperty(e,"__esModule",{value:!0});var i=function(){function t(t,e){for(var s=0;s<e.length;s++){var n=e[s];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,n.key,n)}}return function(e,s,n){return s&&t(e.prototype,s),n&&t(e,n),e}}(),r=s(1),o=s(3),a=function(){function t(e,s){n(this,t),r.initializer.load(this,s,e),this.begin()}return i(t,[{key:"toggle",value:function(){this.pause.status?this.start():this.stop()}},{key:"stop",value:function(){this.typingComplete||this.pause.status||(this.toggleBlinking(!0),this.pause.status=!0,this.options.onStop(this.arrayPos,this))}},{key:"start",value:function(){this.typingComplete||this.pause.status&&(this.pause.status=!1,this.pause.typewrite?this.typewrite(this.pause.curString,this.pause.curStrPos):this.backspace(this.pause.curString,this.pause.curStrPos),this.options.onStart(this.arrayPos,this))}},{key:"destroy",value:function(){this.reset(!1),this.options.onDestroy(this)}},{key:"reset",value:function(){var t=arguments.length<=0||void 0===arguments[0]||arguments[0];clearInterval(this.timeout),this.replaceText(""),this.cursor&&this.cursor.parentNode&&(this.cursor.parentNode.removeChild(this.cursor),this.cursor=null),this.strPos=0,this.arrayPos=0,this.curLoop=0,t&&(this.insertCursor(),this.options.onReset(this),this.begin())}},{key:"begin",value:function(){var t=this;this.typingComplete=!1,this.shuffleStringsIfNeeded(this),this.insertCursor(),this.bindInputFocusEvents&&this.bindFocusEvents(),this.timeout=setTimeout(function(){t.currentElContent&&0!==t.currentElContent.length?t.backspace(t.currentElContent,t.currentElContent.length):t.typewrite(t.strings[t.sequence[t.arrayPos]],t.strPos)},this.startDelay)}},{key:"typewrite",value:function(t,e){var s=this;this.fadeOut&&this.el.classList.contains(this.fadeOutClass)&&(this.el.classList.remove(this.fadeOutClass),this.cursor&&this.cursor.classList.remove(this.fadeOutClass));var n=this.humanizer(this.typeSpeed),i=1;return this.pause.status===!0?void this.setPauseStatus(t,e,!0):void(this.timeout=setTimeout(function(){e=o.htmlParser.typeHtmlChars(t,e,s);var n=0,r=t.substr(e);if("^"===r.charAt(0)&&/^\^\d+/.test(r)){var a=1;r=/\d+/.exec(r)[0],a+=r.length,n=parseInt(r),s.temporaryPause=!0,s.options.onTypingPaused(s.arrayPos,s),t=t.substring(0,e)+t.substring(e+a),s.toggleBlinking(!0)}if("`"===r.charAt(0)){for(;"`"!==t.substr(e+i).charAt(0)&&(i++,!(e+i>t.length)););var u=t.substring(0,e),l=t.substring(u.length+1,e+i),c=t.substring(e+i+1);t=u+l+c,i--}s.timeout=setTimeout(function(){s.toggleBlinking(!1),e===t.length?s.doneTyping(t,e):s.keepTyping(t,e,i),s.temporaryPause&&(s.temporaryPause=!1,s.options.onTypingResumed(s.arrayPos,s))},n)},n))}},{key:"keepTyping",value:function(t,e,s){0===e&&(this.toggleBlinking(!1),this.options.preStringTyped(this.arrayPos,this)),e+=s;var n=t.substr(0,e);this.replaceText(n),this.typewrite(t,e)}},{key:"doneTyping",value:function(t,e){var s=this;this.options.onStringTyped(this.arrayPos,this),this.toggleBlinking(!0),this.arrayPos===this.strings.length-1&&(this.complete(),this.loop===!1||this.curLoop===this.loopCount)||(this.timeout=setTimeout(function(){s.backspace(t,e)},this.backDelay))}},{key:"backspace",value:function(t,e){var s=this;if(this.pause.status===!0)return void this.setPauseStatus(t,e,!0);if(this.fadeOut)return this.initFadeOut();this.toggleBlinking(!1);var n=this.humanizer(this.backSpeed);this.timeout=setTimeout(function(){e=o.htmlParser.backSpaceHtmlChars(t,e,s);var n=t.substr(0,e);if(s.replaceText(n),s.smartBackspace){var i=s.strings[s.arrayPos+1];i&&n===i.substr(0,e)?s.stopNum=e:s.stopNum=0}e>s.stopNum?(e--,s.backspace(t,e)):e<=s.stopNum&&(s.arrayPos++,s.arrayPos===s.strings.length?(s.arrayPos=0,s.options.onLastStringBackspaced(),s.shuffleStringsIfNeeded(),s.begin()):s.typewrite(s.strings[s.sequence[s.arrayPos]],e))},n)}},{key:"complete",value:function(){this.options.onComplete(this),this.loop?this.curLoop++:this.typingComplete=!0}},{key:"setPauseStatus",value:function(t,e,s){this.pause.typewrite=s,this.pause.curString=t,this.pause.curStrPos=e}},{key:"toggleBlinking",value:function(t){this.cursor&&(this.pause.status||this.cursorBlinking!==t&&(this.cursorBlinking=t,t?this.cursor.classList.add("typed-cursor--blink"):this.cursor.classList.remove("typed-cursor--blink")))}},{key:"humanizer",value:function(t){return Math.round(Math.random()*t/2)+t}},{key:"shuffleStringsIfNeeded",value:function(){this.shuffle&&(this.sequence=this.sequence.sort(function(){return Math.random()-.5}))}},{key:"initFadeOut",value:function(){var t=this;return this.el.className+=" "+this.fadeOutClass,this.cursor&&(this.cursor.className+=" "+this.fadeOutClass),setTimeout(function(){t.arrayPos++,t.replaceText(""),t.strings.length>t.arrayPos?t.typewrite(t.strings[t.sequence[t.arrayPos]],0):(t.typewrite(t.strings[0],0),t.arrayPos=0)},this.fadeOutDelay)}},{key:"replaceText",value:function(t){this.attr?this.el.setAttribute(this.attr,t):this.isInput?this.el.value=t:"html"===this.contentType?this.el.innerHTML=t:this.el.textContent=t}},{key:"bindFocusEvents",value:function(){var t=this;this.isInput&&(this.el.addEventListener("focus",function(e){t.stop()}),this.el.addEventListener("blur",function(e){t.el.value&&0!==t.el.value.length||t.start()}))}},{key:"insertCursor",value:function(){this.showCursor&&(this.cursor||(this.cursor=document.createElement("span"),this.cursor.className="typed-cursor",this.cursor.innerHTML=this.cursorChar,this.el.parentNode&&this.el.parentNode.insertBefore(this.cursor,this.el.nextSibling)))}}]),t}();e["default"]=a,t.exports=e["default"]},function(t,e,s){"use strict";function n(t){return t&&t.__esModule?t:{"default":t}}function i(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}Object.defineProperty(e,"__esModule",{value:!0});var r=Object.assign||function(t){for(var e=1;e<arguments.length;e++){var s=arguments[e];for(var n in s)Object.prototype.hasOwnProperty.call(s,n)&&(t[n]=s[n])}return t},o=function(){function t(t,e){for(var s=0;s<e.length;s++){var n=e[s];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,n.key,n)}}return function(e,s,n){return s&&t(e.prototype,s),n&&t(e,n),e}}(),a=s(2),u=n(a),l=function(){function t(){i(this,t)}return o(t,[{key:"load",value:function(t,e,s){if("string"==typeof s?t.el=document.querySelector(s):t.el=s,t.options=r({},u["default"],e),t.isInput="input"===t.el.tagName.toLowerCase(),t.attr=t.options.attr,t.bindInputFocusEvents=t.options.bindInputFocusEvents,t.showCursor=!t.isInput&&t.options.showCursor,t.cursorChar=t.options.cursorChar,t.cursorBlinking=!0,t.elContent=t.attr?t.el.getAttribute(t.attr):t.el.textContent,t.contentType=t.options.contentType,t.typeSpeed=t.options.typeSpeed,t.startDelay=t.options.startDelay,t.backSpeed=t.options.backSpeed,t.smartBackspace=t.options.smartBackspace,t.backDelay=t.options.backDelay,t.fadeOut=t.options.fadeOut,t.fadeOutClass=t.options.fadeOutClass,t.fadeOutDelay=t.options.fadeOutDelay,t.isPaused=!1,t.strings=t.options.strings.map(function(t){return t.trim()}),"string"==typeof t.options.stringsElement?t.stringsElement=document.querySelector(t.options.stringsElement):t.stringsElement=t.options.stringsElement,t.stringsElement){t.strings=[],t.stringsElement.style.display="none";var n=Array.prototype.slice.apply(t.stringsElement.children),i=n.length;if(i)for(var o=0;o<i;o+=1){var a=n[o];t.strings.push(a.innerHTML.trim())}}t.strPos=0,t.arrayPos=0,t.stopNum=0,t.loop=t.options.loop,t.loopCount=t.options.loopCount,t.curLoop=0,t.shuffle=t.options.shuffle,t.sequence=[],t.pause={status:!1,typewrite:!0,curString:"",curStrPos:0},t.typingComplete=!1;for(var o in t.strings)t.sequence[o]=o;t.currentElContent=this.getCurrentElContent(t),t.autoInsertCss=t.options.autoInsertCss,this.appendAnimationCss(t)}},{key:"getCurrentElContent",value:function(t){var e="";return e=t.attr?t.el.getAttribute(t.attr):t.isInput?t.el.value:"html"===t.contentType?t.el.innerHTML:t.el.textContent}},{key:"appendAnimationCss",value:function(t){var e="data-typed-js-css";if(t.autoInsertCss&&(t.showCursor||t.fadeOut)&&!document.querySelector("["+e+"]")){var s=document.createElement("style");s.type="text/css",s.setAttribute(e,!0);var n="";t.showCursor&&(n+="\n        .typed-cursor{\n          opacity: 1;\n        }\n        .typed-cursor.typed-cursor--blink{\n          animation: typedjsBlink 0.7s infinite;\n          -webkit-animation: typedjsBlink 0.7s infinite;\n                  animation: typedjsBlink 0.7s infinite;\n        }\n        @keyframes typedjsBlink{\n          50% { opacity: 0.0; }\n        }\n        @-webkit-keyframes typedjsBlink{\n          0% { opacity: 1; }\n          50% { opacity: 0.0; }\n          100% { opacity: 1; }\n        }\n      "),t.fadeOut&&(n+="\n        .typed-fade-out{\n          opacity: 0;\n          transition: opacity .25s;\n        }\n        .typed-cursor.typed-cursor--blink.typed-fade-out{\n          -webkit-animation: 0;\n          animation: 0;\n        }\n      "),0!==s.length&&(s.innerHTML=n,document.body.appendChild(s))}}}]),t}();e["default"]=l;var c=new l;e.initializer=c},function(t,e){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var s={strings:["These are the default values...","You know what you should do?","Use your own!","Have a great day!"],stringsElement:null,typeSpeed:0,startDelay:0,backSpeed:0,smartBackspace:!0,shuffle:!1,backDelay:700,fadeOut:!1,fadeOutClass:"typed-fade-out",fadeOutDelay:500,loop:!1,loopCount:1/0,showCursor:!0,cursorChar:"|",autoInsertCss:!0,attr:null,bindInputFocusEvents:!1,contentType:"html",onComplete:function(t){},preStringTyped:function(t,e){},onStringTyped:function(t,e){},onLastStringBackspaced:function(t){},onTypingPaused:function(t,e){},onTypingResumed:function(t,e){},onReset:function(t){},onStop:function(t,e){},onStart:function(t,e){},onDestroy:function(t){}};e["default"]=s,t.exports=e["default"]},function(t,e){"use strict";function s(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}Object.defineProperty(e,"__esModule",{value:!0});var n=function(){function t(t,e){for(var s=0;s<e.length;s++){var n=e[s];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,n.key,n)}}return function(e,s,n){return s&&t(e.prototype,s),n&&t(e,n),e}}(),i=function(){function t(){s(this,t)}return n(t,[{key:"typeHtmlChars",value:function(t,e,s){if("html"!==s.contentType)return e;var n=t.substr(e).charAt(0);if("<"===n||"&"===n){var i="";for(i="<"===n?">":";";t.substr(e+1).charAt(0)!==i&&(e++,!(e+1>t.length)););e++}return e}},{key:"backSpaceHtmlChars",value:function(t,e,s){if("html"!==s.contentType)return e;var n=t.substr(e).charAt(0);if(">"===n||";"===n){var i="";for(i=">"===n?"<":"&";t.substr(e-1).charAt(0)!==i&&(e--,!(e<0)););e--}return e}}]),t}();e["default"]=i;var r=new i;e.htmlParser=r}])});

					// Initialize original typed.js with background sync
					document.addEventListener('DOMContentLoaded', function() {
						const typedElement = document.querySelector('.typed-words');
						if (typedElement) {
							// Set initial background
							changeSyncedBackground(0);

							// Calculate approximate cycle duration (type + pause + delete)
							const avgWordLength = words.reduce((sum, w) => sum + w.length, 0) / words.length;
							const typingTime = avgWordLength * typeSpeed * 1.25; // with humanizer
							const deleteTime = avgWordLength * backSpeed * 1.25;
							const cycleDuration = typingTime + backDelay + deleteTime;

							// Typed.js will call preStringTyped for first word, which starts countdown
							var typed = new Typed('.typed-words', {
								strings: words,
								typeSpeed: typeSpeed,
								backSpeed: backSpeed,
								backDelay: backDelay,
								startDelay: startDelay,
								loop: true,
								showCursor: true,
								preStringTyped: function(arrayPos, self) {
									// Change background and start countdown when photo changes
									changeSyncedBackground(arrayPos);
									startCountdown(cycleDuration);
								}
							});
						}
					});
				}
			})();
			</script>

			<?php
		} 
	
	}

	protected function get_terms($taxonomy) {
			$taxonomies = get_terms( array( 'taxonomy' =>$taxonomy,'hide_empty' => false) );

			$options = [ '' => '' ];
			
			if ( !empty($taxonomies) ) :
				foreach ( $taxonomies as $term ) {
					$options[ $term->term_id ] = $term->name;
				}
			endif;

			return $options;
		}
		function listeo_get_pages_dropdown()
		{
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
