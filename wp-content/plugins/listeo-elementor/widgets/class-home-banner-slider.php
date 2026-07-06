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
class HomeBannerSlider extends Widget_Base
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
		return 'listeo-homebanner-slider';
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
		return __('Home Banner Slider', 'listeo_elementor');
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
				'default' => __('Find Nearby Attractions', 'listeo_elementor'),
			)
		);
		$this->add_control(
			'subtitle',
			array(
				'label'   => __('Subtitle', 'listeo_elementor'),
				'type'    => Controls_Manager::TEXT,
				'default' => __('Explore top-rated attractions, activities and more!', 'listeo_elementor'),
			)
		);

		$this->add_control(
			'typed',
			[
				'label' => __('Enable Type words effect', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);
		$this->add_control(
			'typed_text',
			array(
				'label'   => __('Text to displayed in "typed" section, separate by coma', 'listeo_elementor'),
				'label_block' => true,
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __('Attractions, Restaurants, Hotels', 'listeo_elementor'),
			)
		);
		if (function_exists('listeo_get_search_forms_dropdown')) {

			$search_forms = listeo_get_search_forms_dropdown('fullwidth');
			$this->add_control(
				'home_banner_form',
				[
					'label' => __('Form source ', 'listeo_elementor'),
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
					'listing' => __('Listing', 'listeo_elementor'),
					'page' => __('Page', 'listeo_elementor'),
					'custom' => __('Custom', 'listeo_elementor'),
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
			'shapes',
			[
				'label' => __('Enable Shapes animation  effect', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);

		$this->add_control(
			'home_banner_text_align',
			[
				'label' => __('Text alignment ', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'left',
				'options' => [
					'center' => __('Center', 'listeo_elementor'),
					'left' 	 => __('Left', 'listeo_elementor'),

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
				'selector' => '{{WRAPPER}} .main-search-container.plain-color',
			]
		);
		// $this->add_control(
		// 	'background',
		// 	[
		// 		'label' => __( 'Choose Background Image', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,
		// 		'selectors' => [

		// 			'{{WRAPPER}} .main-search-container.plain-color' => 'background-image:  url({{VALUE}})',
		// 		],
		// 	]
		// );





		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'slide_title_1st',
			[
				'label' => __('Title first line', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __('List Title', 'plugin-domain'),
				'label_block' => true,
			]
		);


		$repeater->add_control(
			'list_background',
			[
				'label' => __('Content', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::MEDIA,

				'show_label' => false,
			]
		);
		$this->add_control(
			'list',
			[
				'label' => __('Slides', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'prevent_empty' => false,
				'title_field' => '{{{ slide_title_1st }}}',
			]
		);

		$this->add_control(
			'featured_categories_status',
			[
				'label' => __('Show Featured Categories', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);


		$this->add_control(
			'tax-listing_category',
			[
				'label' => __('Show in Featured Categories this terms', 'listeo_elementor'),
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
	protected function render()
	{
		$settings = $this->get_settings_for_display();

		$this->add_inline_editing_attributes('title', 'none');
		$this->add_inline_editing_attributes('subtitle', 'none');

		if (!empty($settings['background']['url'])) {

			$background_image = $settings['background']['url'];
		} else {
			$background_image = get_option('listeo_search_bg');
		}

		$video = false;

		if (isset($settings['video']['url']) && !empty($settings['video']['url'])) {
			$video = $settings['video']['url'];
		}

		$classes = array();

		// if($settings['solid_background'] == 'solid'){
		// 	$classes[] = 'solid-bg-home-banner';
		// }
		// if( $settings['search_form_style'] == 'boxed') { 
		// 	$classes[] = 'alt-search-box centered';
		// }

		if ($video) {
			$classes[] = 'dark-overlay';
		}

?>


		<div class="main-search-container  elementor-main-search-container  plain-color main-search-container-with-slider ">
			<div class="main-search-inner">

				<div class="container">
					<div class="row">
						<div class="col-md-12">

							<h1><?php echo $settings['title']; ?> <span class="typed-words"></span></h1>
							<?php if (!empty($settings['subtitle'])) { ?><h4><?php echo $settings['subtitle']; ?></h4><?php } ?>

							<?php
							$home_banner_form_action_page = $settings['home_banner_form_action_page'];
							$home_banner_form_action_custom = $settings['home_banner_form_action_custom'];
							$home_banner_form_action = $settings['home_banner_form_action'];
							if ($home_banner_form_action == 'page' && !empty($home_banner_form_action_page)) {
								$home_banner_form_action = get_permalink($home_banner_form_action_page);
							} else if ($home_banner_form_action == 'custom' && !empty($home_banner_form_action_custom)) {
								$home_banner_form_action = $home_banner_form_action_custom;
							} else {
								$home_banner_form_action = get_post_type_archive_link('listing');
							}

							?>
							<?php

							echo do_shortcode('[listeo_search_form action=' . $home_banner_form_action . ' source="' . $settings['home_banner_form'] . '"  custom_class="main-search-form"]') ?>

						</div>
					</div>

					<?php
					if ($settings['featured_categories_status'] == 'yes') :

						if (isset($settings['tax-listing_category'])) :
							$category = is_array($settings['tax-listing_category']) ? $settings['tax-listing_category'] : array_filter(array_map('trim', explode(',', $settings['tax-listing_category'])));


							if (!empty($category)) : ?>
								<div class="row">
									<div class="col-md-12">
										<h5 class="highlighted-categories-headline"><?php esc_html_e('Or browse featured categories:', 'listeo_elementor') ?></h5>


										<div class="highlighted-categories">

											<?php

											foreach ($category as $value) {

												$term = get_term($value, 'listing_category');

												if ($term && !is_wp_error($term)) {
													$icon = get_term_meta($value, 'icon', true);
													$_icon_svg = get_term_meta($value, '_icon_svg', true);
											?>
													<!-- Box -->
													<a href="<?php echo get_term_link($term->slug, 'listing_category'); ?>" class="highlighted-category">
														<?php if (!empty($_icon_svg)) { ?>
															<i>
																<?php echo listeo_render_svg_icon($_icon_svg); ?>
															</i>
														<?php } else if ($icon && $icon != 'empty') { ?><i class="<?php echo esc_attr($icon); ?>"></i><?php }; ?>
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

			</div>
			<!-- Main Search Photo Slider -->
			<div class="container msps-container">


				<?php if ($settings['list']) { ?>
					<div class="main-search-photo-slider">
						<div class="msps-slider-container">
							<div class="msps-slider">
								<?php
								foreach ($settings['list'] as $item) { ?>
									<div class="item"><img  src="<?php echo $item['list_background']['url']; ?>" class="item" title="<?php echo $item['slide_title_1st']; ?>" /></div>
								<?php }

								?>
							</div>
						</div>
					</div>
				<?php } ?>
				<?php if ($settings['shapes'] == 'yes') { ?>
					<div class="msps-shapes" id="scene">

						<div class="layer" data-depth="0.2">
							<svg height="40" width="40" class="shape-a">
								<circle cx="20" cy="20" r="17" stroke-width="4" fill="transparent" stroke="#C400FF" />
							</svg>
						</div>

						<div class="layer" data-depth="0.5">
							<svg width="90" height="90" viewBox="0 0 500 800" class="shape-b">
								<g transform="translate(281,319)">
									<path fill="transparent" style="transform:rotate(25deg)" stroke-width="35" stroke="#F56C83" fill d="M260.162831,132.205081
					A18,18 0 0,1 262.574374,141.205081
					A18,18 0 0,1 244.574374,159.205081H-244.574374
					A18,18 0 0,1 -262.574374,141.205081
					A18,18 0 0,1 -260.162831,132.205081L-15.588457,-291.410162
					A18,18 0 0,1 0,-300.410162
					A18,18 0 0,1 15.588457,-291.410162Z" />
								</g>
							</svg>
						</div>

						<div class="layer" data-depth="0.2" data-invert-x="false" data-invert-y="false" style="z-index: -10">
							<svg height="200" width="200" viewbox="0 0 250 250" class="shape-c">
								<path d="
					    M 0, 30
					    C 0, 23.400000000000002 23.400000000000002, 0 30, 0
					    S 60, 23.400000000000002 60, 30
					        36.599999999999994, 60 30, 60
					        0, 36.599999999999994 0, 30
					" fill="#FADB5F" transform="rotate(
					    -25,
					    100,
					    100
					) translate(
					    0
					    0
					) scale(3.5)"></path>
							</svg>
						</div>


						<div class="layer" data-depth="0.6" style="z-index: -10">
							<svg height="120" width="120" class="shape-d">
								<circle cx="60" cy="60" r="60" fill="#222" />
							</svg>
						</div>


						<div class="layer" data-depth="0.2">
							<svg height="70" width="70" viewBox="0 0 200 200" class="shape-e">
								<path fill="#FF0066" d="M68.5,-24.5C75.5,-0.8,58.7,28.5,33.5,46.9C8.4,65.4,-25.2,73.1,-42.2,60.2C-59.2,47.4,-59.6,13.9,-49.8,-13.7C-40,-41.3,-20,-63.1,5.4,-64.8C30.7,-66.6,61.5,-48.3,68.5,-24.5Z" transform="translate(100 100)" />
							</svg>
						</div>

					</div>
				<?php } else { ?>
					<div class="msps-shapes" id="scene"></div>
				<?php } ?>
			</div>


		</div>
		<?php
if($settings['typed'] == 'yes') {
    $typed = $settings['typed_text'];
    $typed_array = explode(',', $typed);
    $typed_array = array_map('trim', $typed_array); // Clean up any extra spaces
    
    // Check if TranslatePress is active
    $is_translatepress_active = is_plugin_active('translatepress-multilingual/index.php') || 
                               class_exists('TRP_Translate_Press') || 
                               function_exists('trp_translate');
    ?>
    
    <script>
    // Check if TranslatePress is active (client-side detection as backup)
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
                showCursor: true
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
                const words = <?php echo json_encode($typed_array); ?>;
                
                createWordSwapper(typedElement, words, {
                    swapDelay: 3000,
                    fadeSpeed: 300,
                    startDelay: 700,
                    loop: true,
                    showCursor: true
                });
            }
        });

    } else {
        // Use original typed.js for the typing effect when TranslatePress is not active
        <?php 
        // Include the original typed.js library code here
        ?>
        /*!
         * typed.js - A JavaScript Typing Animation Library
         * Author: Matt Boldt <me@mattboldt.com>
         * Version: v2.0.9
         */
        (function(t,e){"object"==typeof exports&&"object"==typeof module?module.exports=e():"function"==typeof define&&define.amd?define([],e):"object"==typeof exports?exports.Typed=e():t.Typed=e()})(this,function(){return function(t){function e(n){if(s[n])return s[n].exports;var i=s[n]={exports:{},id:n,loaded:!1};return t[n].call(i.exports,i,i.exports,e),i.loaded=!0,i.exports}var s={};return e.m=t,e.c=s,e.p="",e(0)}([function(t,e,s){"use strict";function n(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}Object.defineProperty(e,"__esModule",{value:!0});var i=function(){function t(t,e){for(var s=0;s<e.length;s++){var n=e[s];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,n.key,n)}}return function(e,s,n){return s&&t(e.prototype,s),n&&t(e,n),e}}(),r=s(1),o=s(3),a=function(){function t(e,s){n(this,t),r.initializer.load(this,s,e),this.begin()}return i(t,[{key:"toggle",value:function(){this.pause.status?this.start():this.stop()}},{key:"stop",value:function(){this.typingComplete||this.pause.status||(this.toggleBlinking(!0),this.pause.status=!0,this.options.onStop(this.arrayPos,this))}},{key:"start",value:function(){this.typingComplete||this.pause.status&&(this.pause.status=!1,this.pause.typewrite?this.typewrite(this.pause.curString,this.pause.curStrPos):this.backspace(this.pause.curString,this.pause.curStrPos),this.options.onStart(this.arrayPos,this))}},{key:"destroy",value:function(){this.reset(!1),this.options.onDestroy(this)}},{key:"reset",value:function(){var t=arguments.length<=0||void 0===arguments[0]||arguments[0];clearInterval(this.timeout),this.replaceText(""),this.cursor&&this.cursor.parentNode&&(this.cursor.parentNode.removeChild(this.cursor),this.cursor=null),this.strPos=0,this.arrayPos=0,this.curLoop=0,t&&(this.insertCursor(),this.options.onReset(this),this.begin())}},{key:"begin",value:function(){var t=this;this.typingComplete=!1,this.shuffleStringsIfNeeded(this),this.insertCursor(),this.bindInputFocusEvents&&this.bindFocusEvents(),this.timeout=setTimeout(function(){t.currentElContent&&0!==t.currentElContent.length?t.backspace(t.currentElContent,t.currentElContent.length):t.typewrite(t.strings[t.sequence[t.arrayPos]],t.strPos)},this.startDelay)}},{key:"typewrite",value:function(t,e){var s=this;this.fadeOut&&this.el.classList.contains(this.fadeOutClass)&&(this.el.classList.remove(this.fadeOutClass),this.cursor&&this.cursor.classList.remove(this.fadeOutClass));var n=this.humanizer(this.typeSpeed),i=1;return this.pause.status===!0?void this.setPauseStatus(t,e,!0):void(this.timeout=setTimeout(function(){e=o.htmlParser.typeHtmlChars(t,e,s);var n=0,r=t.substr(e);if("^"===r.charAt(0)&&/^\^\d+/.test(r)){var a=1;r=/\d+/.exec(r)[0],a+=r.length,n=parseInt(r),s.temporaryPause=!0,s.options.onTypingPaused(s.arrayPos,s),t=t.substring(0,e)+t.substring(e+a),s.toggleBlinking(!0)}if("`"===r.charAt(0)){for(;"`"!==t.substr(e+i).charAt(0)&&(i++,!(e+i>t.length)););var u=t.substring(0,e),l=t.substring(u.length+1,e+i),c=t.substring(e+i+1);t=u+l+c,i--}s.timeout=setTimeout(function(){s.toggleBlinking(!1),e===t.length?s.doneTyping(t,e):s.keepTyping(t,e,i),s.temporaryPause&&(s.temporaryPause=!1,s.options.onTypingResumed(s.arrayPos,s))},n)},n))}},{key:"keepTyping",value:function(t,e,s){0===e&&(this.toggleBlinking(!1),this.options.preStringTyped(this.arrayPos,this)),e+=s;var n=t.substr(0,e);this.replaceText(n),this.typewrite(t,e)}},{key:"doneTyping",value:function(t,e){var s=this;this.options.onStringTyped(this.arrayPos,this),this.toggleBlinking(!0),this.arrayPos===this.strings.length-1&&(this.complete(),this.loop===!1||this.curLoop===this.loopCount)||(this.timeout=setTimeout(function(){s.backspace(t,e)},this.backDelay))}},{key:"backspace",value:function(t,e){var s=this;if(this.pause.status===!0)return void this.setPauseStatus(t,e,!0);if(this.fadeOut)return this.initFadeOut();this.toggleBlinking(!1);var n=this.humanizer(this.backSpeed);this.timeout=setTimeout(function(){e=o.htmlParser.backSpaceHtmlChars(t,e,s);var n=t.substr(0,e);if(s.replaceText(n),s.smartBackspace){var i=s.strings[s.arrayPos+1];i&&n===i.substr(0,e)?s.stopNum=e:s.stopNum=0}e>s.stopNum?(e--,s.backspace(t,e)):e<=s.stopNum&&(s.arrayPos++,s.arrayPos===s.strings.length?(s.arrayPos=0,s.options.onLastStringBackspaced(),s.shuffleStringsIfNeeded(),s.begin()):s.typewrite(s.strings[s.sequence[s.arrayPos]],e))},n)}},{key:"complete",value:function(){this.options.onComplete(this),this.loop?this.curLoop++:this.typingComplete=!0}},{key:"setPauseStatus",value:function(t,e,s){this.pause.typewrite=s,this.pause.curString=t,this.pause.curStrPos=e}},{key:"toggleBlinking",value:function(t){this.cursor&&(this.pause.status||this.cursorBlinking!==t&&(this.cursorBlinking=t,t?this.cursor.classList.add("typed-cursor--blink"):this.cursor.classList.remove("typed-cursor--blink")))}},{key:"humanizer",value:function(t){return Math.round(Math.random()*t/2)+t}},{key:"shuffleStringsIfNeeded",value:function(){this.shuffle&&(this.sequence=this.sequence.sort(function(){return Math.random()-.5}))}},{key:"initFadeOut",value:function(){var t=this;return this.el.className+=" "+this.fadeOutClass,this.cursor&&(this.cursor.className+=" "+this.fadeOutClass),setTimeout(function(){t.arrayPos++,t.replaceText(""),t.strings.length>t.arrayPos?t.typewrite(t.strings[t.sequence[t.arrayPos]],0):(t.typewrite(t.strings[0],0),t.arrayPos=0)},this.fadeOutDelay)}},{key:"replaceText",value:function(t){this.attr?this.el.setAttribute(this.attr,t):this.isInput?this.el.value=t:"html"===this.contentType?this.el.innerHTML=t:this.el.textContent=t}},{key:"bindFocusEvents",value:function(){var t=this;this.isInput&&(this.el.addEventListener("focus",function(e){t.stop()}),this.el.addEventListener("blur",function(e){t.el.value&&0!==t.el.value.length||t.start()}))}},{key:"insertCursor",value:function(){this.showCursor&&(this.cursor||(this.cursor=document.createElement("span"),this.cursor.className="typed-cursor",this.cursor.innerHTML=this.cursorChar,this.el.parentNode&&this.el.parentNode.insertBefore(this.cursor,this.el.nextSibling)))}}]),t}();e["default"]=a,t.exports=e["default"]},function(t,e,s){"use strict";function n(t){return t&&t.__esModule?t:{"default":t}}function i(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}Object.defineProperty(e,"__esModule",{value:!0});var r=Object.assign||function(t){for(var e=1;e<arguments.length;e++){var s=arguments[e];for(var n in s)Object.prototype.hasOwnProperty.call(s,n)&&(t[n]=s[n])}return t},o=function(){function t(t,e){for(var s=0;s<e.length;s++){var n=e[s];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,n.key,n)}}return function(e,s,n){return s&&t(e.prototype,s),n&&t(e,n),e}}(),a=s(2),u=n(a),l=function(){function t(){i(this,t)}return o(t,[{key:"load",value:function(t,e,s){if("string"==typeof s?t.el=document.querySelector(s):t.el=s,t.options=r({},u["default"],e),t.isInput="input"===t.el.tagName.toLowerCase(),t.attr=t.options.attr,t.bindInputFocusEvents=t.options.bindInputFocusEvents,t.showCursor=!t.isInput&&t.options.showCursor,t.cursorChar=t.options.cursorChar,t.cursorBlinking=!0,t.elContent=t.attr?t.el.getAttribute(t.attr):t.el.textContent,t.contentType=t.options.contentType,t.typeSpeed=t.options.typeSpeed,t.startDelay=t.options.startDelay,t.backSpeed=t.options.backSpeed,t.smartBackspace=t.options.smartBackspace,t.backDelay=t.options.backDelay,t.fadeOut=t.options.fadeOut,t.fadeOutClass=t.options.fadeOutClass,t.fadeOutDelay=t.options.fadeOutDelay,t.isPaused=!1,t.strings=t.options.strings.map(function(t){return t.trim()}),"string"==typeof t.options.stringsElement?t.stringsElement=document.querySelector(t.options.stringsElement):t.stringsElement=t.options.stringsElement,t.stringsElement){t.strings=[],t.stringsElement.style.display="none";var n=Array.prototype.slice.apply(t.stringsElement.children),i=n.length;if(i)for(var o=0;o<i;o+=1){var a=n[o];t.strings.push(a.innerHTML.trim())}}t.strPos=0,t.arrayPos=0,t.stopNum=0,t.loop=t.options.loop,t.loopCount=t.options.loopCount,t.curLoop=0,t.shuffle=t.options.shuffle,t.sequence=[],t.pause={status:!1,typewrite:!0,curString:"",curStrPos:0},t.typingComplete=!1;for(var o in t.strings)t.sequence[o]=o;t.currentElContent=this.getCurrentElContent(t),t.autoInsertCss=t.options.autoInsertCss,this.appendAnimationCss(t)}},{key:"getCurrentElContent",value:function(t){var e="";return e=t.attr?t.el.getAttribute(t.attr):t.isInput?t.el.value:"html"===t.contentType?t.el.innerHTML:t.el.textContent}},{key:"appendAnimationCss",value:function(t){var e="data-typed-js-css";if(t.autoInsertCss&&(t.showCursor||t.fadeOut)&&!document.querySelector("["+e+"]")){var s=document.createElement("style");s.type="text/css",s.setAttribute(e,!0);var n="";t.showCursor&&(n+="\n        .typed-cursor{\n          opacity: 1;\n        }\n        .typed-cursor.typed-cursor--blink{\n          animation: typedjsBlink 0.7s infinite;\n          -webkit-animation: typedjsBlink 0.7s infinite;\n                  animation: typedjsBlink 0.7s infinite;\n        }\n        @keyframes typedjsBlink{\n          50% { opacity: 0.0; }\n        }\n        @-webkit-keyframes typedjsBlink{\n          0% { opacity: 1; }\n          50% { opacity: 0.0; }\n          100% { opacity: 1; }\n        }\n      "),t.fadeOut&&(n+="\n        .typed-fade-out{\n          opacity: 0;\n          transition: opacity .25s;\n        }\n        .typed-cursor.typed-cursor--blink.typed-fade-out{\n          -webkit-animation: 0;\n          animation: 0;\n        }\n      "),0!==s.length&&(s.innerHTML=n,document.body.appendChild(s))}}}]),t}();e["default"]=l;var c=new l;e.initializer=c},function(t,e){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var s={strings:["These are the default values...","You know what you should do?","Use your own!","Have a great day!"],stringsElement:null,typeSpeed:0,startDelay:0,backSpeed:0,smartBackspace:!0,shuffle:!1,backDelay:700,fadeOut:!1,fadeOutClass:"typed-fade-out",fadeOutDelay:500,loop:!1,loopCount:1/0,showCursor:!0,cursorChar:"|",autoInsertCss:!0,attr:null,bindInputFocusEvents:!1,contentType:"html",onComplete:function(t){},preStringTyped:function(t,e){},onStringTyped:function(t,e){},onLastStringBackspaced:function(t){},onTypingPaused:function(t,e){},onTypingResumed:function(t,e){},onReset:function(t){},onStop:function(t,e){},onStart:function(t,e){},onDestroy:function(t){}};e["default"]=s,t.exports=e["default"]},function(t,e){"use strict";function s(t,e){if(!(t instanceof e))throw new TypeError("Cannot call a class as a function")}Object.defineProperty(e,"__esModule",{value:!0});var n=function(){function t(t,e){for(var s=0;s<e.length;s++){var n=e[s];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(t,n.key,n)}}return function(e,s,n){return s&&t(e.prototype,s),n&&t(e,n),e}}(),i=function(){function t(){s(this,t)}return n(t,[{key:"typeHtmlChars",value:function(t,e,s){if("html"!==s.contentType)return e;var n=t.substr(e).charAt(0);if("<"===n||"&"===n){var i="";for(i="<"===n?">":";";t.substr(e+1).charAt(0)!==i&&(e++,!(e+1>t.length)););e++}return e}},{key:"backSpaceHtmlChars",value:function(t,e,s){if("html"!==s.contentType)return e;var n=t.substr(e).charAt(0);if(">"===n||";"===n){var i="";for(i=">"===n?"<":"&";t.substr(e-1).charAt(0)!==i&&(e--,!(e<0)););e--}return e}}]),t}();e["default"]=i;var r=new i;e.htmlParser=r}])});

        // Initialize original typed.js
        document.addEventListener('DOMContentLoaded', function() {
            const typedElement = document.querySelector('.typed-words');
            if (typedElement) {
                var typed = new Typed('.typed-words', {
                    strings: <?php echo json_encode($typed_array); ?>,
                    typeSpeed: 70,
                    backSpeed: 80,
                    backDelay: 4000,
                    startDelay: 1000,
                    loop: true,
                    showCursor: true
                });
            }
        });
    }
    </script>
    
    <?php
} ?>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/parallax/2.1.3/parallax.min.js"></script>

		<script>
			/* ----------------- Start Document ----------------- */
			(function($) {
				"use strict";

				$(document).ready(function() {

					<?php if ($settings['shapes'] == 'yes') { ?>
						$(window).on('load', function() {
							$('.msps-shapes').addClass('shapes-animation')

						});
						const parent = document.getElementById('scene');
						const parallax = new Parallax(parent, {
							limitX: 50,
							limitY: 50,
						});
					<?php } ?>


					$('.msps-slider').slick({
						infinite: true,
						slidesToShow: 1,
						slidesToScroll: 1,
						dots: true,
						arrows: false,
						autoplay: true,
						autoplaySpeed: 5000,
						speed: 1000,
						fade: true,
						cssEase: 'linear'
					});

				});

			})(this.jQuery);
		</script>
<?php

	}

	protected function get_terms($taxonomy)
	{
		$taxonomies = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

		$options = ['' => ''];

		if (!empty($taxonomies)) :
			foreach ($taxonomies as $taxonomy) {
				$options[$taxonomy->term_id] = $taxonomy->name;
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
