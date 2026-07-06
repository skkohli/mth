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
class ReviewsCarousel extends Widget_Base
{



	// public function __construct( $data = array(), $args = null ) {
	// 	parent::__construct( $data, $args );

	// 	wp_register_script( 'listeo-taxonomy-carousel-elementor', plugins_url( '/assets/tax-carousel/tax-carousel.js', ELEMENTOR_LISTEO ), array(), '1.0.0' );
	// }


	// public function get_script_depends() {
	// 	  $scripts = ['listeo-taxonomy-carousel-elementor'];

	// 	  return $scripts;
	// }
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
		return 'listeo-reviews-carousel';
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
		return __('Reviews Carousel', 'listeo_elementor');
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
		return 'eicon-gallery-grid';
	}


	// public function get_script_depends() {
	//    return [ 'listeo-taxonomy-carousel-script' ];
	//  }


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
		// 	'taxonomy' => '',
		// 'xd' 	=> '',
		// 'only_top' 	=> 'yes',
		// 'autoplay'      => '',
		//          'autoplayspeed'      => '3000',





		//add control for minimum rating value - a dropdown with values from 1 to 5
		$this->add_control(
			'rating',
			array(
				'label'   => __('Show reviews with minimum Rating', 'listeo_elementor'),
				'type'    => Controls_Manager::SELECT,
				'default' => '4',
				'options' => array(
					'1' => __('1', 'listeo_elementor'),
					'2' => __('2', 'listeo_elementor'),
					'3' => __('3', 'listeo_elementor'),
					'4' => __('4', 'listeo_elementor'),
					'5' => __('5', 'listeo_elementor'),
				),
			)
		);

		// add control for the number of reviews to show
		$this->add_control(
			'limit',
			array(
				'label'   => __('Number of Reviews', 'listeo_elementor'),
				'type'    => Controls_Manager::NUMBER,
				'default' => __('Subtitle', 'listeo_elementor'),
				'min' => 9,
				'max' => 100,
				'step' => 1,
				'default' => 9,
			)
		);

		// add control to limit the length of the content
		$this->add_control(
			'content_length',
			array(
				'label'   => __('Maximum Comment Length', 'listeo_elementor'),
				'type'    => Controls_Manager::NUMBER,
				'default' => 20,
				'min' => 10,
				'max' => 200,
				'step' => 1,
				'default' => 20,
			)
		);


		$this->add_control(
			'autoplay',
			[
				'label' => __('Auto Play', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'your-plugin'),
				'label_off' => __('Hide', 'your-plugin'),
				'return_value' => 'yes',
				'default' => 'yes',

			]
		);

		$this->add_control(
			'autoplayspeed',
			array(
				'label'   => __('Auto Play Speed', 'listeo_elementor'),
				'type'    => Controls_Manager::NUMBER,
				'default' => __('Subtitle', 'listeo_elementor'),
				'min' => 1000,
				'max' => 10000,
				'step' => 500,
				'default' => 3000,
			)
		);
		// $taxonomy_names = get_object_taxonomies( 'listing','object' );

		// foreach ($taxonomy_names as $key => $value) {
		// 	$shortcode_atts[$value->name.'_include'] = '';
		// 	$shortcode_atts[$value->name.'_exclude'] = '';
		// }


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
		$limit = $settings['limit'];
		// $comments = get_comments($args);
		// $comments_count = count($comments);	// get the count of comments
		$args = array(
			'orderby' => 'comment_date',
			'order' => 'DESC',
			'status' => 'approve',
			'number' => $limit,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'listeo-rating',
					'value'   =>  $settings['rating'],
					'compare' => '>='
				)
			)
		);
		$comment_query = new \WP_Comment_Query($args);
		$comments = $comment_query->comments;

		if (!class_exists('Listeo_Core_Template_Loader')) {
			return;
		}

		if ($limit > $comment_query->found_comments) {
			$limit = $comment_query->found_comments;
		}
		$modulus = $limit % 3;
		if ($modulus !== 0) {
			$posts_to_repeat = 3 - $modulus;
		} else {
			$posts_to_repeat = 0;
		}


		if (!empty($comments) && !is_wp_error($comments)) {
			$tcomments_count = count($comments);


			while (count($comments) < 12) {
				$comments = array_merge($comments, $comments);
			}

			// If the array now has more than 12 items, trim it down to the first 12
			if (count($comments) > 12) {
				$comments = array_slice($comments, 0, 12);
			}
		}
		if(!$comments){
			echo "You need to have at least couple reviews on your listings to use this widget.";
			return;
		}
		if (!empty($comments) && !is_wp_error($comments)) {
?>

			<div class="reviews-slick-carousel reviews-carousel" <?php if ($settings['autoplay'] == 'yes') { ?>data-slick='{"autoplay": true, "autoplaySpeed": <?php echo $settings['autoplayspeed']; ?>}' <?php } ?>>
				<!-- Item -->

				<?php
				// i need to loop the terms array but in one lopp I need to get 3 terms and then the next 3 terms

				for ($i = 0; $i < count($comments); $i += 2) {
					$comment1 = isset($comments[$i]) ? $comments[$i] : null;
					$comment2 = isset($comments[$i + 1]) ? $comments[$i + 1] : null;

					// Now you can use $term1, $term2, and $term3
					// Be sure to check if they are not null before using them
					if ($comment1 == null && $comment2 == null) {
						break;
					}
					$items = 0;

					if ($comment1 != null) {


						$items++;
					}


					if ($comment2 != null) {


						$items++;
					}
				?>
					<div class="fw-carousel-item">

						<div class="reviews-carousel-element">

							<div class="rating-box-wrapper">
								<?php $rating  = get_comment_meta($comment1->comment_ID, 'listeo-rating', true); ?>
								<p><?php
									$content_length = $settings['content_length'];
									$comment_content = $comment1->comment_content;
									$words = explode(' ', $comment_content);

									if (str_word_count($comment_content) > $content_length) {
										$comment_content = implode(' ', array_slice($words, 0, $content_length)) . '...';
									}

									echo $comment_content;
									?></p>
								<div class="star-rating" data-rating="<?php echo esc_attr($rating); ?>"></div>
								<div class="rating-box-footer">
									<?php echo get_avatar($comment1, 70); ?>
									<div class="rating-box-author">
										<span><?php echo $comment1->comment_author; ?> <?php esc_html_e('reviewed', 'listeo_elementor'); ?></span>
										<h5> <a href="<?php echo get_comment_link($comment1->comment_ID); ?>"><?php echo get_the_title($comment1->comment_post_ID); ?></a></h5>
									</div>
								</div>

							</div>

							<div class="rating-box-wrapper">
								<?php $rating  = get_comment_meta($comment2->comment_ID, 'listeo-rating', true); ?>
								<p><?php
									$content_length = $settings['content_length'];
									$comment_content = $comment2->comment_content;
									$words = explode(' ', $comment_content);

									if (str_word_count($comment_content) > $content_length) {
										$comment_content = implode(' ', array_slice($words, 0, $content_length)) . '...';
									}

									echo $comment_content; ?>
								</p>
								<div class="star-rating" data-rating="<?php echo esc_attr($rating); ?>"></div>
								<div class="rating-box-footer">
									<?php echo get_avatar($comment2, 70); ?>
									<div class="rating-box-author">
										<span><?php echo $comment2->comment_author; ?> <?php esc_html_e('reviewed', 'listeo_elementor'); ?></span>
										<h5> <a href="<?php echo get_comment_link($comment2->comment_ID); ?>"><?php echo get_the_title($comment2->comment_post_ID); ?></a></h5>
									</div>
								</div>

							</div>

						</div>


					</div>

				<?php } ?>
			</div>
<?php }
	}



	protected function get_taxonomies()
	{
		$taxonomies = get_object_taxonomies('listing', 'objects');

		$options = ['' => ''];

		foreach ($taxonomies as $taxonomy) {
			$options[$taxonomy->name] = $taxonomy->label;
		}

		return $options;
	}

	protected function get_terms($taxonomy)
	{
		$taxonomies = get_terms($taxonomy, array(
			'hide_empty' => false,
		));
		$options = ['' => ''];

		if (!empty($taxonomies)) :
			foreach ($taxonomies as $taxonomy) {
				$options[$taxonomy->term_id] = $taxonomy->name;
			}
		endif;

		return $options;
	}
}
