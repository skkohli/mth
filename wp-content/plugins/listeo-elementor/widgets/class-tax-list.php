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
class TaxonomyList extends Widget_Base {

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
		return 'listeo-taxonomy-list';
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
		return __( 'Taxonomy List', 'listeo_elementor' );
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
		return 'eicon-bullet-list';
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

		//title
		//hide empty
		//show counter
		//type = group_by_parents/ show all/ show only parent/ show children from selected parent
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'listeo_elementor' ),
			)
		);
// 	'taxonomy' => '',
			// 'xd' 	=> '',
			// 'only_top' 	=> 'yes',
			// 'autoplay'      => '',
   //          'autoplayspeed'      => '3000',
		$this->add_control(
			'title',
			array(
				'label'   => __( 'Title', 'listeo_elementor' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Title', 'listeo_elementor' ),
			)
		);	

		$this->add_control(
			'type',
			[
				'label' => __( 'List Type ', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'group_by_parents' => __( 'Group by parents', 'listeo_elementor' ),
					'all' => __( 'Show all categories', 'listeo_elementor' ),
					'only_parents' => __( 'Show only parent categories', 'listeo_elementor' ),
					'parent' => __( 'Show only child categories from single parent', 'listeo_elementor' ),

				],
			]
		);

		// $this->add_control(
		// 		$value->name.'_include',
		// 		[
		// 			'label' => __( 'Parent category from '.$value->label, 'listeo_elementor' ),
		// 			'type' => Controls_Manager::SELECT2,
		// 			'label_block' => true,
		// 			'multiple' => false,
		// 			'options' => $this->get_terms($value->name),
		// 			'condition' => [
		// 				'taxonomy' => $value->name,
		// 				'type' => 'parent'
		// 			],
		// 		]
		// 	);


		$this->add_control(
			'taxonomy',
			[
				'label' => __( 'Taxonomy', 'elementor-pro' ),
				'type' => Controls_Manager::SELECT2,
				'label_block' => true,
				'default' => [],
				'options' => $this->get_taxonomies(),
				
			]
		);

		$taxonomy_names = get_object_taxonomies( 'listing','object' );
		foreach ($taxonomy_names as $key => $value) {
			
			$this->add_control(
				$value->name.'_include',
				[
					'label' => __( 'Include '.$value->label, 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_terms($value->name),
					'condition' => [
						'taxonomy' => $value->name,
					],
				]
			);
			$this->add_control(
				$value->name.'_exclude',
				[
					'label' => __( 'Exclude '.$value->label, 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => true,
					'default' => [],
					'options' => $this->get_terms($value->name),
					'condition' => [
						'taxonomy' => $value->name,
					],
				]
			);

			$this->add_control(
				$value->name.'_parent_id',
				[
					'label' => __( 'Parent category '.$value->label, 'listeo_elementor' ),
					'type' => Controls_Manager::SELECT2,
					'label_block' => true,
					'multiple' => false,
					'default' => [],
					'options' => $this->get_terms($value->name),
					'condition' => [
						'taxonomy' => $value->name,
						'type' => 'parent'
					],
				]
			);
			
		}

	



		$this->add_control(
			'orderby',
			[
				'label' => __( 'Order by', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'name',
				'options' => [
					'name' =>  __( 'Name', 'listeo_elementor' ),
					'term_id' =>  __(  'Order by term id. ', 'listeo_elementor' ),
					'term_order' =>  __(  'Order by term order. ', 'listeo_elementor' ),
					'count'=>  __(  'Order by number of listings assinged.', 'listeo_elementor' ),
					'include' =>  __(  'Match the order of the "Include listing" param.', 'listeo_elementor' ),
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
			'number',
			[
				'label' => __( 'Terms to display', 'listeo_elementor' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 99,
				'step' => 1,
				'default' => 6,
			]
		);
		$this->add_control(
			'hide_empty',
			[ 
				'label' => __( 'Hide empty categories', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'your-plugin' ),
				'label_off' => __( 'No', 'your-plugin' ),
				'return_value' => 'yes',
				'default' => 'yes',
				
			]
		);


		$this->add_control(
			'show_counter',
			[
				'label' => __( 'Show listings counter', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'your-plugin' ),
				'label_off' => __( 'Hide', 'your-plugin' ),
				'return_value' => 'yes',
				'default' => 'yes',
				
			]
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
	protected function render() {
		$settings = $this->get_settings_for_display();

	
		$taxonomy_names = get_object_taxonomies( 'listing','object' );
		
		$taxonomy = $settings['taxonomy'];

        
      	if(empty($taxonomy)){
      		$taxonomy = "listing_category";
      	}

      	if($settings['hide_empty'] == 'yes' ){
      		$hide_empty = true;
      	} else {
      		$hide_empty = false;
      	}

       	$output = '';
       	$type = $settings['type'];

       	if($type == 'all') {
     
	        $categories = get_terms( $settings['taxonomy'], array(
	            'orderby'    => $settings['orderby'], // id count name - Default slug term_group - Not fully implemented (avoid using) none
	            'order'      => $settings['order'],
	            'hide_empty' => $hide_empty,
	            'number' 	 => $settings['number'],
	            'include' => $settings[$taxonomy.'_include'],
				'exclude' => $settings[$taxonomy.'_exclude'],
	        ) );
	        
	        if ( !is_wp_error( $categories ) ) {
	            $output = '<div class="categories-group categories-group-all">
	            <div class="container">
	                <div class="row">
	                    <div class="col-md-3"><h4 class="parent-listings-category">'.$settings['title'].'</h4></div>';
	            $chunks = listeo_partition($categories, 3);
	            foreach ($chunks as $chunk) {
	                $output .= '<div class="col-md-3">
	                        <ul>';
	                        foreach ($chunk as $term) {
	                        	$t_id = $term->term_id;
	                        	$icon = get_term_meta($t_id,'icon',true);
								$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
						        if(empty($icon)) {
									$icon = 'fa fa-globe' ;
						        }
						   
								$count = listeo_get_term_post_count($settings['taxonomy'],$term->term_id);
	                        	
	                        	$output .= '<li class="child-listing-category">
									<a href="'.get_term_link( $term ).'" class="child-category">
										<div class="child-category-icon-container">';
									if (!empty($_icon_svg_image)) {
										$output .= '<i class="child-category-icon listeo-svg-icon-box-grid">'.listeo_render_svg_icon($_icon_svg).'</i>';
					          		 } else { 
					          			if($icon != 'emtpy') {
					          				$check_if_im = substr($icon, 0, 3);
						                    if($check_if_im == 'im ') {
						                       $output .= ' <i class="'.esc_attr($icon).'"></i>'; 
						                    } else {
						                       $output .= ' <i class="fa '.esc_attr($icon).'"></i>'; 
						                    }
					          			}
					          		} 
									
										if($settings['show_counter']=="yes") { 
											$output.='<span class="child-category-counter">'.$count.'</span>';
										}
										$output.='</div>
										<div class="child-category-title">'.$term->name.'</div>
									</a>
								</li>';

	                        }
	                $output .= '</ul>
	                    </div>';
	            }
	            $output .= '</div>
	            	</div>
	            </div>';
        	}
    	} 

    	if($type == 'only_parents') {
     
	        $categories = get_terms( $settings['taxonomy'], array(
	            'orderby'    => $settings['orderby'], // id count name - Default slug term_group - Not fully implemented (avoid using) none
	            'order'      => $settings['order'],
	            'hide_empty' => $hide_empty,
	            'number' 	 => $settings['number'],
	              'include' => $settings[$taxonomy.'_include'],
				'exclude' => $settings[$taxonomy.'_exclude'],
	            'parent'     => 0
	         ) );
	        if ( !is_wp_error( $categories ) ) {
	            $output .= '<div class="categories-group categories-group-only_parents">
	             <div class="container">
	                <div class="row">
	                    <div class="col-md-3"><h4 class="parent-listings-category">'.$settings['title'].'</h4></div>';
	            $chunks = listeo_partition($categories, 3);
	            foreach ($chunks as $chunk) {
	                $output .= '<div class="col-md-3">
	                        <ul>';
	                        foreach ($chunk as $term) {
	                           $t_id = $term->term_id;
	                        	$icon = get_term_meta($t_id,'icon',true);
								$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
						        if(empty($icon)) {
									$icon = 'fa fa-globe' ;
						        }
						   
								$count = listeo_get_term_post_count($settings['taxonomy'],$term->term_id);
	                        	
	                        	$output .= '<li class="child-listing-category">
									<a href="'.get_term_link( $term ).'" class="child-category">
										<div class="child-category-icon-container">';
									if (!empty($_icon_svg_image)) {
										$output .= '<i class="child-category-icon listeo-svg-icon-box-grid">'.listeo_render_svg_icon($_icon_svg).'</i>';
					          		 } else { 
					          			if($icon != 'emtpy') {
					          				$check_if_im = substr($icon, 0, 3);
						                    if($check_if_im == 'im ') {
						                       $output .= ' <i class="'.esc_attr($icon).'"></i>'; 
						                    } else {
						                       $output .= ' <i class="fa '.esc_attr($icon).'"></i>'; 
						                    }
					          			}
					          		} 
									
										if($settings['show_counter']=="yes") { 
											$output.='<span class="child-category-counter">'.$count.'</span>';
										}
										$output.='</div>
										<div class="child-category-title">'.$term->name.'</div>
									</a>
								</li>';

	                        }
	                $output .= '</ul>
	                    </div>';
	            }
	            $output .= '</div>
	            	</div>
	            </div>';
	        }
	    }

	    if($type == 'group_by_parents') {

	        $parents =  get_terms($settings['taxonomy'], array(
	            'orderby'    => $settings['orderby'], // id count name - Default slug term_group - Not fully implemented (avoid using) none
	            'order'      => $settings['order'],
	            'hide_empty' => $hide_empty,
	            'number' 	 => $settings['number'],
	            'include' => $settings[$taxonomy.'_include'],
				'exclude' => $settings[$taxonomy.'_exclude'],
	            'parent'     => 0
	            ));
	        if ( !is_wp_error( $parents ) ) {
	            foreach($parents as $key => $term) :
	                $subterms = get_terms($settings['taxonomy'], array("orderby" => $settings['orderby'], "parent" => $term->term_id,    'include' => $settings[$taxonomy.'_include'],
				'exclude' => $settings[$taxonomy.'_exclude'],'hide_empty' => $hide_empty));
	                if($subterms) :
	                    $output .= '<div class="categories-group categories-group-by_parents">
	                    <div class="container">
	                    <div class="row">
	                        <div class="col-md-3"><h4 class="parent-listings-category">';
	                         $t_id = $term->term_id;
	                         $count = listeo_get_term_post_count($settings['taxonomy'],$term->term_id);
	                        	$icon = get_term_meta($t_id,'icon',true);
								$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
						        if(empty($icon)) {
									$icon = 'fa fa-globe' ;
						        }
								$output .= '<a href="' . get_term_link( $term ) . '" class="parent-category">
								<div class="child-category-icon-container">';
									if (!empty($_icon_svg_image)) {
										$output .= '<i class="parent-category-icon listeo-svg-icon-box-grid">'.listeo_render_svg_icon($_icon_svg).'</i>';
					          		 } else { 
					          			if($icon != 'emtpy') {
					          				$check_if_im = substr($icon, 0, 3);
						                    if($check_if_im == 'im ') {
						                       $output .= ' <i class="'.esc_attr($icon).'"></i>'; 
						                    } else {
						                       $output .= ' <i class="fa '.esc_attr($icon).'"></i>'; 
						                    }
					          			}
					          		} 

									$output .= '<span class="child-category-counter">'.$count.'</span></div>
								<div class="child-category-title">'. $term->name .'</div>
								</a>
								</h4></div>';
	                           
	                            $chunks = listeo_partition($subterms, 3);
	                            foreach ($chunks as $chunk) {
	                                $output .= '<div class="col-md-3">
	                                        <ul>';
	                                        foreach ($chunk as $subterms) {
	                                            $t_id = $subterms->term_id;
	                        	$icon = get_term_meta($t_id,'icon',true);
								$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
						        if(empty($icon)) {
									$icon = 'fa fa-globe' ;
						        }
						   
								$count = listeo_get_term_post_count($settings['taxonomy'],$subterms->term_id);
	                        	
	                        	$output .= '<li class="child-listing-category">
									<a href="'.get_term_link( $subterms ).'" class="child-category">
										<div class="child-category-icon-container">';
									if (!empty($_icon_svg_image)) {
										$output .= '<i class="child-category-icon listeo-svg-icon-box-grid">'.listeo_render_svg_icon($_icon_svg).'</i>';
					          		 } else { 
					          			if($icon != 'emtpy') {
					          				$check_if_im = substr($icon, 0, 3);
						                    if($check_if_im == 'im ') {
						                       $output .= ' <i class="'.esc_attr($icon).'"></i>'; 
						                    } else {
						                       $output .= ' <i class="fa '.esc_attr($icon).'"></i>'; 
						                    }
					          			}
					          		} 
									
										if($settings['show_counter']=="yes") { 
											$output.='<span class="child-category-counter">'.$count.'</span>';
										}
										$output.='</div>
										<div class="child-category-title">'.$subterms->name.'</div>
									</a>
								</li>';

	                                        }
	                                $output .= '</ul>
	                                    </div>';
	                            }
	                           
	                    $output .= '</div>
	                    	</div>
	                    </div>';
	                 endif;
	            endforeach;
	        }
	    }

	    if($type == 'parent') {
	    	$taxonomy = $settings['taxonomy'];
	    	$selected_parent = $settings[$taxonomy.'_parent_id'];
	    	if(!empty($selected_parent)){
				$categories = get_terms( $settings['taxonomy'], array(
		           'orderby'    => $settings['orderby'], // id count name - Default slug term_group - Not fully implemented (avoid using) none
		            'order'      => $settings['order'],
		            'hide_empty' => $hide_empty,
		            'number' 	=> $settings['number'],
		              'include' => $settings[$taxonomy.'_include'],
				'exclude' => $settings[$taxonomy.'_exclude'],
		         ) );
		        if ( !is_wp_error( $categories ) ) {
		            $subterms =  get_terms($settings['taxonomy'], array(
		                'orderby'    => $settings['orderby'], // id count name - Default slug term_group - Not fully implemented (avoid using) none
			            'order'      => $settings['order'],
			            'hide_empty' => $hide_empty,
			            'number' 	 => $settings['number'],
		                'parent'     => $selected_parent,
		                  'include' => $settings[$taxonomy.'_include'],
				'exclude' => $settings[$taxonomy.'_exclude'],
		                ));
		            $term = get_term( $selected_parent, $settings['taxonomy'] );
		           
		            if($subterms) :
		                    $output .= '<div class="categories-group">
		                    <div class="container">
		                    <div class="row">
		                        <div class="col-md-3"><h4 class="parent-listings-category">';
	                         $t_id = $term->term_id;
	                         $count = listeo_get_term_post_count($settings['taxonomy'],$term->term_id);
	                        	$icon = get_term_meta($t_id,'icon',true);
								$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
						        if(empty($icon)) {
									$icon = 'fa fa-globe' ;
						        }
								$output .= '<a href="' . get_term_link( $term ) . '" class="parent-category">
								<div class="child-category-icon-container">';
									if (!empty($_icon_svg_image)) {
										$output .= '<i class="parent-category-icon listeo-svg-icon-box-grid">'.listeo_render_svg_icon($_icon_svg).'</i>';
					          		 } else { 
					          			if($icon != 'emtpy') {
					          				$check_if_im = substr($icon, 0, 3);
						                    if($check_if_im == 'im ') {
						                       $output .= ' <i class="'.esc_attr($icon).'"></i>'; 
						                    } else {
						                       $output .= ' <i class="fa '.esc_attr($icon).'"></i>'; 
						                    }
					          			}
					          		} 

									$output .= '<span class="child-category-counter">'.$count.'</span></div>
								<div class="child-category-title">'. $term->name .'</div>
								</a>
								</h4></div>';
		                           
		                            $chunks = listeo_partition($subterms, 3);
		                            foreach ($chunks as $chunk) {
		                                $output .= '<div class="col-md-3">
		                                        <ul>';
		                                        foreach ($chunk as $subterms) {
		                                           $t_id = $subterms->term_id;
	                        	$icon = get_term_meta($t_id,'icon',true);
								$_icon_svg = get_term_meta($t_id,'_icon_svg',true);
								$_icon_svg_image = wp_get_attachment_image_src($_icon_svg,'medium');
						        if(empty($icon)) {
									$icon = 'fa fa-globe' ;
						        }
						   
								$count = listeo_get_term_post_count($settings['taxonomy'],$subterms->term_id);
	                        	
	                        	$output .= '<li class="child-listing-category">
									<a href="'.get_term_link( $subterms ).'" class="child-category">
										<div class="child-category-icon-container">';
									if (!empty($_icon_svg_image)) {
										$output .= '<i class="child-category-icon listeo-svg-icon-box-grid">'.listeo_render_svg_icon($_icon_svg).'</i>';
					          		 } else { 
					          			if($icon != 'emtpy') {
					          				$check_if_im = substr($icon, 0, 3);
						                    if($check_if_im == 'im ') {
						                       $output .= ' <i class="'.esc_attr($icon).'"></i>'; 
						                    } else {
						                       $output .= ' <i class="fa '.esc_attr($icon).'"></i>'; 
						                    }
					          			}
					          		} 
									
										if($settings['show_counter']=="yes") { 
											$output.='<span class="child-category-counter">'.$count.'</span>';
										}
										$output.='</div>
										<div class="child-category-title">'.$subterms->name.'</div>
									</a>
								</li>';

		                                        }
		                                $output .= '</ul>
		                                    </div>';
		                            }
		                           
		                    $output .= '</div>
		                    </div>
		                    </div>';
		                 endif;
		         }
	    	}
	        
	        
	    }

    	echo $output;

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
		$taxonomies = get_terms( array( 'taxonomy' =>$taxonomy,'hide_empty' => false) );

		$options = [ '' => '' ];
		
		if ( !empty($taxonomies) ) :
			foreach ( $taxonomies as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		endif;

		return $options;
	}

}