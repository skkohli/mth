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
class ImageBox extends Widget_Base {

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
		return 'listeo-imagebox';
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
		return __( 'ImageBox', 'listeo_elementor' );
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

		//link
		//image
		//featured
		//term
		//style alternative-imagebox
		//
		//


		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'listeo_elementor' ),
			)
		);


		$this->add_control(
			'url',
			[
				'label' => __( 'Link','listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::URL,
				'placeholder' => __( 'https://your-link.com', 'listeo_elementor' ),
				'show_external' => true,
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				],
			]
		);


		$this->add_control(
			'background',
			[
				'label' => __( 'Choose Background Image', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::MEDIA,
				'default' => [
					'url' => \Elementor\Utils::get_placeholder_image_src(),
				]
			]
		);



		// $this->add_control(
		// 	'featured',
		// 	[
		// 		'label' => __( 'Featured badge', 'listeo_elementor' ),
		// 		'type' => \Elementor\Controls_Manager::SWITCHER,
		// 		'label_on' => __( 'Show', 'your-plugin' ),
		// 		'label_off' => __( 'Hide', 'your-plugin' ),
		// 		'return_value' => 'yes',
		// 		'default' => 'yes',
		// 	]
		// );

		$this->add_control(
			'taxonomy',
			[
				'label' => __( 'Taxonomy', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'default' => [],
				'options' => $this->get_taxonomies(),
				
			]
		);

		$taxonomy_names = get_object_taxonomies( 'listing','object' );
		foreach ($taxonomy_names as $key => $value) {
	
			$this->add_control(
				$value->name.'term',
				[
					'label' => __( 'Show term from '.$value->label, 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'default' => [],
					'options' => $this->get_terms($value->name),
					'condition' => [
						'taxonomy' => $value->name,
					],
				]
			);
		}


		$this->add_control(
			'show_counter',
			[
				'label' => __( 'Show listings counter', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'your-plugin' ),
				'label_off' => __( 'Hide', 'your-plugin' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);


		$this->add_control(
			'style',
			[
				'label' => __( 'Style ', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'alternative-imagebox',
				'options' => [
					'standard' => __( 'Standard', 'listeo_elementor' ),
					'alternative-imagebox' => __( 'Alternative', 'listeo_elementor' ),
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

	
		$target = $settings['url']['is_external'] ? ' target="_blank"' : '';
		$nofollow = $settings['url']['nofollow'] ? ' rel="nofollow"' : '';

		$taxonomy_names = get_object_taxonomies( 'listing','object' );
		
		$taxonomy = $settings['taxonomy'];
		
		if($taxonomy){
			$term = get_term_by( 'id',$settings[$taxonomy.'term'], $taxonomy );

			if ($term) {
				$term_url = get_term_link($term, $taxonomy);
				if (is_wp_error($term_url)) {
					$term_url = '';
				}
			} else {
				// if no term is selected get random term from taxonomy
				$terms = get_terms(array(
					'taxonomy' => $taxonomy,
					'hide_empty' => false,
				));
				// count terms

				// get random term


				if (!empty($terms) && !is_wp_error($terms)) {
					$terms_count = count($terms);
					$term = $terms[rand(0, $terms_count - 1)];
					$term_url = get_term_link($term, $taxonomy);
				} else {
					$term_url = '';
				}
			}
			
		} else {
			$term_url = '';
		}
		$url = (!empty( $settings['url']['url'] )) ?  $settings['url']['url'] :  $term_url ;
		
		?>
		
		<?php if($settings['style'] == 'alternative-imagebox') { ?>
			<div class="alternative-imagebox">
		<?php } ?> 

			<a <?php echo 'href="' . esc_url($url) . '"' . $target . $nofollow; ?> class="img-box " 
				<?php if(isset($settings['background']['url']) && !empty(isset($settings['background']['url']))){ ?>data-background-image="<?php echo esc_url($settings['background']['url']); ?>" <?php } ?>>
				
				<div class="img-box-content visible">
					<?php if(isset($term)): ?>
						<h4><?php echo $term->name; ?></h4>
						<?php if($settings['show_counter']) : ?><span><?php  printf( _n( '%s Listing', '%s Listings', $term->count, 'listeo_elementor' ), $term->count ); ?>
							</span> <?php endif; ?>
					<?php endif; ?>
				</div>
			</a>
		<?php if($settings['style'] == 'alternative-imagebox') { ?>
			</div>
		<?php } ?> 

	
		<?php
		//lik
	}

	protected function get_taxonomies() {
		$taxonomies = get_object_taxonomies( 'listing', 'objects' );

		$options = [ '' => '' ];

		foreach ( $taxonomies as $taxonomy ) {
			$options[ $taxonomy->name ] = $taxonomy->label;
		}

		return $options;
	}

	protected function get_terms($taxonomy) {
		$taxonomies = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false) );

		$options = [ '' => '' ];
		
		if ( !empty($taxonomies) ) :
			foreach ( $taxonomies as $term ) {
				
				$options[ $term->term_id ] = $term->name;
			}
		endif;

		return $options;
	}
	
}