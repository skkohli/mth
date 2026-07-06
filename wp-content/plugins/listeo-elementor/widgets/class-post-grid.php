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
class PostGrid extends Widget_Base {

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
		return 'listeo-posts-grid';
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
		return __( 'Posts Grid', 'listeo_elementor' );
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
		return 'eicon-gallery-grid';
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
  // 'limit'=>'6',
  //           'orderby'=> 'date',
  //           'order'=> 'DESC',
  //           'categories' => '',
  //           'exclude_posts' => '',
  //           'include_posts' => '',
  //           'ignore_sticky_posts' => 1,
  //           'limit_words' => 15,
  //           'from_vs' => 'no'


		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Query', 'listeo_elementor' ),
			)
		);

		$this->add_control(
			'limit',
			[
				'label' => __( 'Posts to display', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 21,
				'step' => 1,
				'default' => 3,
			]
		);


		$this->add_control(
			'orderby',
			[
				'label' => __( 'Order by', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'none' =>  __( 'No order', 'listeo_elementor' ),
					'ID' =>  __(  'Order by post id. ', 'listeo_elementor' ),
					'author'=>  __(  'Order by author.', 'listeo_elementor' ),
					'title' =>  __(  'Order by title.', 'listeo_elementor' ),
					'name' =>  __( ' Order by post name (post slug).', 'listeo_elementor' ),
					'type'=>  __( ' Order by post type.', 'listeo_elementor' ),
					'date' =>  __( ' Order by date.', 'listeo_elementor' ),
					'modified' =>  __( ' Order by last modified date.', 'listeo_elementor' ),
					'parent' =>  __( ' Order by post/page parent id.', 'listeo_elementor' ),
					'rand' =>  __( ' Random order.', 'listeo_elementor' ),
					'comment_count' =>  __( ' Order by number of commen', 'listeo_elementor' ),
					
				],
			]
		);
		$this->add_control(
			'order',
			[
				'label' => __( 'Order', 'listeo_elementor'  ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => [
					'DESC' =>  __( 'Descending', 'listeo_elementor' ),
					'ASC' =>  __(  'Ascending. ', 'listeo_elementor' ),
				
					
				],
			]
		);


			$this->add_control(
				'categories',
				[
					'label' => __( 'Show from categories', 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_terms('category'),
					
				]
			);	

			$this->add_control(
				'exclude_posts',
				[
					'label' => __( 'Exclude posts', 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_posts(),
					
				]
			);	
			$this->add_control(
				'include_posts',
				[
					'label' => __( 'Include posts', 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_posts(),
					
				]
			);

			$this->add_control(
				'limit_words',
				[
					'label' => __( 'Excerpt length', 'listeo_elementor' ),
					'type' => \Elementor\Controls_Manager::NUMBER,
					'min' => 5,
					'max' => 99,
					'step' => 1,
					'default' => 15,
				]
			);

			$this->add_control(
			'after_excerpt',
			[
				'label' => __( 'Add after excerpt', 'listeo_elementor'  ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( '...', 'plugin-domain' ),
				
			]);

			$this->add_control(
				'show_view_blog_button',
				[
					'label' => __( 'Show "View Blog" button', 'listeo_elementor'  ),
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label_on' => __( 'Show', 'listeo_elementor' ),
					'label_off' => __( 'Hide', 'listeo_elementor' ),
					'return_value' => 'yes',
					'default' => 'yes',
				]
			);
			$this->add_control(
				'show_excerpt',
				[
					'label' => __( 'Show post excerpt', 'listeo_elementor' ),
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label_on' => __( 'Show', 'listeo_elementor' ),
					'label_off' => __( 'Hide', 'listeo_elementor' ),
					'return_value' => 'yes',
					'default' => 'no',
				]
			);		
			$this->add_control(
				'show_date',
				[
					'label' => __( 'Show post date', 'listeo_elementor'  ),
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label_on' => __( 'Show', 'listeo_elementor' ),
					'label_off' => __( 'Hide', 'listeo_elementor' ),
					'return_value' => 'yes',
					'default' => 'no',
				]
			);
			$this->add_control(
				'show_category',
				[
					'label' => __( 'Show post category', 'listeo_elementor'  ),
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label_on' => __( 'Show', 'listeo_elementor' ),
					'label_off' => __( 'Hide', 'listeo_elementor' ),
					'return_value' => 'yes',
					'default' => 'no',
				]
			);




		$this->end_controls_section();


		// $this->add_control(
		// 	'with_line',
		// 	[
		// 		'label' => __( 'With Line', 'plugin-domain' ),
		// 		'type' => \Elementor\Controls_Manager::SWITCHER,
		// 		'label_on' => __( 'Show', 'listeo_elementor' ),
		// 		'label_off' => __( 'Hide', 'listeo_elementor' ),
		// 		'return_value' => 'yes',
		// 		'default' => 'yes',
		// 	]
		// );
	



		/* Add the options you'd like to show in this tab here */

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
		$limit = $settings['limit'] ? $settings['limit'] : 3;
		$orderby = $settings['orderby'] ? $settings['orderby'] : 'title';
		$order = $settings['order'] ? $settings['order'] : 'ASC';
		$exclude_posts = $settings['exclude_posts'] ? $settings['exclude_posts'] : 'ASC';
		$categories = $settings['categories'] ? $settings['categories'] : array();
		$limit_words = $settings['limit_words'] ? $settings['limit_words'] : 15;
	

		$args = array(
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'orderby' => $orderby,
            'order' => $order,
            );

        if(!empty($exclude_posts)) {
            $exl = is_array( $exclude_posts ) ? $exclude_posts : array_filter( array_map( 'trim', explode( ',', $exclude_posts ) ) );
            $args['post__not_in'] = $exl;
        }

        if(!empty($include_posts)) {
            $exl = is_array( $include_posts ) ? $include_posts : array_filter( array_map( 'trim', explode( ',', $include_posts ) ) );
            $args['post__in'] = $exl;
        }

       
        if(!empty($categories)) {
            $categories         = is_array( $categories ) ? $categories : array_filter( array_map( 'trim', explode( ',', $categories ) ) );
            $args['category__in'] = $categories;
        }
      

        $i = 0;

        $wp_query = new \WP_Query( $args ); ?>
		
		<div class="listeo-post-grid-wrapper">
			<div class="row">

			<?php if ( $wp_query->have_posts() ) { ?>


				<?php while ( $wp_query->have_posts() ) : $wp_query->the_post();
				$i++;
                $id = $wp_query->post->ID;
                $thumb = get_post_thumbnail_id();
                $img_url = wp_get_attachment_url( $thumb,'listeo-blog-related-post');
               
                

                        ?>
			<div class="col-md-4">
                <a href="<?php the_permalink(); ?>" class="blog-compact-item-container">
                    <div class="blog-compact-item">
                        <?php 
                            the_post_thumbnail('listeo-blog-related-post'); 
                      
                        if($settings['show_category'] == 'yes'){


                            $categories_list = wp_get_post_categories($wp_query->post->ID);
                            $cats = array();

                            $output = '';
                            foreach($categories_list as $c){
                                $cat = get_category( $c );
                                $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug, 'url' => get_category_link($cat->cat_ID) );
                            }
                            $single_cat = array_shift( $cats );
                            echo '<span class="blog-item-tag">'.$single_cat['name'].'</span>';

                        } 
                        ?>
                        
                        <div class="blog-compact-item-content">
                            <?php if($settings['show_date'] == 'yes'){ ?>
                            <ul class="blog-post-tags">
                                <li>
                                	<?php $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
			                    if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			                        $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
			                    }

			                    printf( $time_string,
			                        esc_attr( get_the_date( 'c' ) ),
			                        esc_html( get_the_date() ),
			                        esc_attr( get_the_modified_date( 'c' ) ),
			                        esc_html( get_the_modified_date() )
			                    );

                    			?>
                    				
                    			</li>
                            </ul>
                        	<?php } ?>
                            <h3><?php the_title(); ?></h3>
                            <?php if($settings['show_excerpt'] == 'yes'){ ?>
                            <p><?php 
                                $excerpt = get_the_excerpt();
                                echo listeo_string_limit_words($excerpt,$limit_words); echo $settings['after_excerpt']; ?>
                            </p>
                            <?php } ?>
                        </div>
                    </div>
                </a>
            </div>
<?php 
			 endwhile; // end of the loop. 
		} else {
			//do_action( "woocommerce_shortcode_{$loop_name}_loop_no_results" );
		}
        ?>
  </div>
        </div>
        <?php if($settings['show_view_blog_button'] == 'yes'){ ?>
        <div class="col-md-12 centered-content">
                <a href="<?php echo get_permalink( get_option( 'page_for_posts' ) ); ?>" class="button border margin-top-10"><?php esc_html_e( 'View Blog', 'listeo_elementor' ); ?></a>
            </div>
        <?php 
    }
		wp_reset_postdata();
	
	
		
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

		protected function get_posts() {
			$posts = get_posts( array( 'numberposts' => 99,) );

			$options = [ '' => '' ];
			
			if ( !empty($posts) ) :
				foreach ( $posts as $post ) {
					$options[ $post->ID ] = get_the_title($post->ID);
				}
			endif;

			return $options;
		}
	
}