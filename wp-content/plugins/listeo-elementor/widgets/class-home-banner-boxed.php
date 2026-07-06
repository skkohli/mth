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
class HomeBannerBoxed extends Widget_Base
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
		return 'listeo-homebanner-boxed';
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
		return __('Home Search Banner Boxed', 'listeo_elementor');
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
		if (function_exists('listeo_get_search_forms_dropdown')) {

			$search_forms = listeo_get_search_forms_dropdown('boxed');
			$this->add_control(
				'home_banner_form',
				[
					'label' => __('Form source ', 'listeo_elementor'),
					'type' => \Elementor\Controls_Manager::SELECT,
					'options' => $search_forms,
					'default' => 'search_on_homebox_page'


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
				'label_on' => __('On', 'listeo_elementor'),
				'label_off' => __('Off', 'listeo_elementor'),
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


		$this->add_group_control(
			\Elementor\Group_Control_Background::get_type(),
			[
				'name' => 'background',
				'label' => esc_html__('Background', 'plugin-name'),
				'types' => ['classic', 'gradient', 'video'],
				'selector' => '{{WRAPPER}} .main-search-container',
			]
		);
		$this->add_control(
			'background_overlay_type',
			[
				'label' => __('Background Overlay type', 'listeo_elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'none' => __('None', 'listeo_elementor'),
					'container-overlay-solid' => __('Solid color', 'listeo_elementor'),
					'container-overlay-gradient' 	 => __('Gradient', 'listeo_elementor'),

				],


			]
		);
		$this->add_control(
			'overlay_gradient',
			[
				'label' => __('Overlay on homepage search banner', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'alpha' => false,
				'default' => '#00000080',
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .alt-search-box.container-overlay-gradient.main-search-container:before' => 'background: linear-gradient(to right, {{VALUE}}F2 20%, {{VALUE}}B3 70%, {{VALUE}}00 95%) !important; display:block;',
				],
				'condition' => [
					'background_overlay_type' => 'container-overlay-gradient',
				],
			]
		);
		$this->add_control(
			'overlay_solid',
			[
				'label' => __('Overlay on homepage search banner', 'plugin-domain'),
				'type' => \Elementor\Controls_Manager::COLOR,
				'alpha' => true,
				'default' => '#00000080',
				'separator' => 'before',
				'selectors' => [

					'{{WRAPPER}} .alt-search-box.container-overlay-solid.main-search-container:before' => 'background:  {{VALUE}} !important; display:block;',
				],
				'condition' => [
					'background_overlay_type' => 'container-overlay-solid',
				],
			]
		);



		// $this->add_control(
		// 	'background',
		// 	[
		// 		'label' => __('Choose Background Image', 'listeo_elementor'),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,

		// 	]
		// );
		// $this->add_control(
		// 	'video',
		// 	[
		// 		'label' => __('Choose Video', 'listeo_elementor'),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,

		// 	]
		// );
		// $this->add_control(
		// 	'video_poster',
		// 	[
		// 		'label' => __('Choose Video Poster Image', 'listeo_elementor'),
		// 		'type' => \Elementor\Controls_Manager::MEDIA,
		// 		'default' => [
		// 			'url' => \Elementor\Utils::get_placeholder_image_src(),
		// 		]
		// 	]
		// );


		// $this->add_control(
		// 	'background_overlay_type',
		// 	[
		// 		'label' => __('Background Overlay type', 'listeo_elementor'),
		// 		'type' => \Elementor\Controls_Manager::SELECT,
		// 		'default' => 'container-overlay-solid',
		// 		'options' => [
		// 			'container-overlay-solid' => __('Solid color', 'listeo_elementor'),
		// 			'container-overlay-gradient' 	 => __('Gradient', 'listeo_elementor'),

		// 		],


		// 	]
		// );
		// $this->add_control(
		// 	'overlay_gradient',
		// 	[
		// 		'label' => __('Overlay on homepage search banner', 'plugin-domain'),
		// 		'type' => \Elementor\Controls_Manager::COLOR,
		// 		'alpha' => false,
		// 		'default' => '#000',
		// 		'separator' => 'before',
		// 		'selectors' => [
		// 			'{{WRAPPER}} .container-overlay-gradient.main-search-container:before' => 'background: linear-gradient(to right, {{VALUE}}F2 20%, {{VALUE}}B3 70%, {{VALUE}}00 95%)',
		// 		],
		// 		'condition' => [
		// 			'background_overlay_type' => 'container-overlay-gradient',
		// 		],
		// 	]
		// );
		// $this->add_control(
		// 	'overlay_solid',
		// 	[
		// 		'label' => __('Overlay on homepage search banner', 'plugin-domain'),
		// 		'type' => \Elementor\Controls_Manager::COLOR,
		// 		'alpha' => true,
		// 		'default' => '#00000080',
		// 		'separator' => 'before',
		// 		'selectors' => [

		// 			'{{WRAPPER}} .container-overlay-solid.main-search-container:before' => 'background:  {{VALUE}}',
		// 		],
		// 		'condition' => [
		// 			'background_overlay_type' => 'container-overlay-solid',
		// 		],
		// 	]
		// );
		// $this->add_control(
		// 	'title_color',
		// 	[
		// 		'label' => __('Overlay on homepage search banner', 'plugin-domain'),
		// 		'type' => \Elementor\Controls_Manager::COLOR,
		// 		'alpha' => false,
		// 		'separator' => 'before',
		// 		'selectors' => [
		// 			'{{WRAPPER}} .main-search-container:before' => 'background: linear-gradient(to right, {{VALUE}}F2 20%, {{VALUE}}B3 70%, {{VALUE}}00 95%)',
		// 		],
		// 	]
		// );	


		// $this->add_control(
		// 	'home_full_screen',
		// 	[
		// 		'label' => __( 'Full screen banner', 'listeo_elementor'  ),
		// 		'type' => \Elementor\Controls_Manager::SWITCHER,
		// 		'label_on' => __( 'Show', 'listeo_elementor' ),
		// 		'label_off' => __( 'Hide', 'listeo_elementor' ),
		// 		'return_value' => 'yes',
		// 		'default' => 'no',
		// 	]
		// );






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
		$classes[] = $settings['background_overlay_type'];
		$classes[] = 'full-height';

?>

		<div class="main-search-container elementor-main-search-container  <?php echo implode(" ", $classes); ?> alt-search-box centered">
			<div class="main-search-inner">

				<div class="container">
					<div class="row">
						<div class="col-md-12">

							<div class="main-search-input">

								<div class="main-search-input-headline">
									<h1><?php echo $settings['title']; ?> <span class="typed-words"></span></h1>
									<?php if (!empty($settings['subtitle'])) { ?><h4><?php echo $settings['subtitle']; ?></h4><?php } ?>

								</div>

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

								echo do_shortcode('[listeo_search_form action=' . $home_banner_form_action . ' source="' . $settings['home_banner_form'] . '" custom_class="main-search-form"]') ?>


							</div>
						</div>
					</div>

				</div>
				<?php if ($video) {
					if (isset($settings['video_poster']['url']) && !empty(isset($settings['video_poster']['url']))) {
						$video_poster = $settings['video_poster']['url'];
					}
				?>
					<!-- Video -->
					<div class="video-container">

						<video <?php if (isset($video_poster)) ?> poster="<?php echo $video_poster ?>" loop autoplay muted>
							<source src="<?php echo $video ?>" type="video/mp4">

						</video>
					</div>
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
} 
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
