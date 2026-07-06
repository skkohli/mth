<?php
/**
 * Listeo Taxonomy Tabs Widget
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
 * Listeo Taxonomy Tabs widget class.
 *
 * @since 1.0.0
 */
class ServicesShowcase extends Widget_Base {

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
		return 'listeo-taxonomy-tabs';
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
		return __( 'Listeo Taxonomy Tabs', 'listeo_elementor' );
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
		return 'eicon-tabs';
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
	 * Enqueue styles.
	 */
	public function get_style_depends() {
		return [ 'listeo-taxonomy-tabs-style' ];
	}

	/**
	 * Enqueue scripts.
	 */
	public function get_script_depends() {
		return [ 'listeo-taxonomy-tabs-script' ];
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
			'widget_notice',
			[
				'type' => Controls_Manager::RAW_HTML,
				'raw' => '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-bottom: 15px; color: #495057;"><strong style="color: #212529;">Taxonomy Tabs Widget:</strong><br>• Configure each tab individually with custom content or taxonomy selection<br>• Mix and match custom tabs with taxonomy-based tabs<br>• Full control over each tab\'s appearance and content</div>',
			]
		);

		// Tabs repeater
		$repeater = new Repeater();

		$repeater->add_control(
			'tab_type',
			[
				'label' => __( 'Tab Type', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'custom',
				'options' => [
					'custom' => __( 'Custom Content', 'listeo_elementor' ),
					'taxonomy' => __( 'Taxonomy Term', 'listeo_elementor' ),
				],
			]
		);

		// Custom tab controls
		$repeater->add_control(
			'tab_title',
			[
				'label' => __( 'Tab Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Service', 'listeo_elementor' ),
				'condition' => [
					'tab_type' => 'custom',
				],
			]
		);

		$repeater->add_control(
			'tab_icon',
			[
				'label' => __( 'Tab Icon', 'listeo_elementor' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-cog',
					'library' => 'fa-solid',
				],
				'condition' => [
					'tab_type' => 'custom',
				],
			]
		);

		// Taxonomy tab controls
		$repeater->add_control(
			'taxonomy_term',
			[
				'label' => __( 'Select Term', 'listeo_elementor' ),
				'type' => Controls_Manager::SELECT2,
				'default' => '',
				'options' => $this->get_taxonomy_terms_options(),
				'condition' => [
					'tab_type' => 'taxonomy',
				],
			]
		);

		$repeater->add_control(
			'taxonomy_title_override',
			[
				'label' => __( 'Title Override', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'description' => __( 'Leave empty to use taxonomy term name', 'listeo_elementor' ),
				'condition' => [
					'tab_type' => 'taxonomy',
				],
			]
		);

		// Content controls (shared by both types)
		$repeater->add_control(
			'content_title',
			[
				'label' => __( 'Content Title', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Service Title', 'listeo_elementor' ),
			]
		);

		$repeater->add_control(
			'content_description',
			[
				'label' => __( 'Content Description', 'listeo_elementor' ),
				'type' => Controls_Manager::WYSIWYG,
				'default' => __( 'Brief description about this service category.', 'listeo_elementor' ),
				'description' => __( 'Optional description text above the list items', 'listeo_elementor' ),
			]
		);

		$repeater->add_control(
			'content_items',
			[
				'label' => __( 'Content List Items', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => __( 'Professional service item 1', 'listeo_elementor' ) . "\n" . __( 'Professional service item 2', 'listeo_elementor' ),
				'description' => __( 'Enter each list item on a new line', 'listeo_elementor' ),
			]
		);

		$repeater->add_control(
			'check_icon',
			[
				'label' => __( 'Check Icon', 'listeo_elementor' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-check',
					'library' => 'fa-solid',
				],
				'description' => __( 'Icon to display next to each list item', 'listeo_elementor' ),
			]
		);

		$repeater->add_control(
			'content_image',
			[
				'label' => __( 'Content Image', 'listeo_elementor' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Utils::get_placeholder_image_src(),
				],
			]
		);

		$repeater->add_control(
			'show_button',
			[
				'label' => __( 'Show Call to Action Button', 'listeo_elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'listeo_elementor' ),
				'label_off' => __( 'Hide', 'listeo_elementor' ),
				'return_value' => 'yes',
				'default' => '',
			]
		);

		$repeater->add_control(
			'button_text',
			[
				'label' => __( 'Button Text', 'listeo_elementor' ),
				'type' => Controls_Manager::TEXT,
				'default' => __( 'Learn More', 'listeo_elementor' ),
				'condition' => [
					'show_button' => 'yes',
				],
			]
		);

		$repeater->add_control(
			'button_link',
			[
				'label' => __( 'Button Link', 'listeo_elementor' ),
				'type' => Controls_Manager::URL,
				'placeholder' => __( 'https://your-link.com', 'listeo_elementor' ),
				'default' => [
					'url' => '',
					'is_external' => true,
					'nofollow' => true,
				],
				'condition' => [
					'show_button' => 'yes',
				],
			]
		);

		$repeater->add_control(
			'button_icon',
			[
				'label' => __( 'Button Icon', 'listeo_elementor' ),
				'type' => Controls_Manager::ICONS,
				'default' => [
					'value' => 'fas fa-arrow-right',
					'library' => 'fa-solid',
				],
				'condition' => [
					'show_button' => 'yes',
				],
			]
		);

		$repeater->add_control(
			'tab_background_color',
			[
				'label' => __( 'Tab Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#e8f4f0',
			]
		);

		$this->add_control(
			'tabs',
			[
				'label' => __( 'Tabs Configuration', 'listeo_elementor' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'tab_type' => 'custom',
						'tab_title' => __( 'Apartments', 'listeo_elementor' ),
						'content_title' => __( 'Luxury Apartments', 'listeo_elementor' ),
						'content_description' => __( 'Discover our premium apartment listings with modern amenities.', 'listeo_elementor' ),
						'content_items' => __( 'Modern kitchen facilities', 'listeo_elementor' ) . "\n" . __( 'High-speed internet included', 'listeo_elementor' ) . "\n" . __( '24/7 security service', 'listeo_elementor' ),
						'tab_background_color' => '#e8f4f0',
					],
					[
						'tab_type' => 'custom',
						'tab_title' => __( 'Houses', 'listeo_elementor' ),
						'content_title' => __( 'Family Houses', 'listeo_elementor' ),
						'content_description' => __( 'Spacious houses perfect for families with gardens and parking.', 'listeo_elementor' ),
						'content_items' => __( 'Private garden space', 'listeo_elementor' ) . "\n" . __( 'Dedicated parking', 'listeo_elementor' ) . "\n" . __( 'Family-friendly neighborhood', 'listeo_elementor' ),
						'tab_background_color' => '#fff3e0',
					],
				],
				'title_field' => '<# 
					var title = "";
					if ( tab_type == "custom" ) {
						title = tab_title || "Custom Tab";
					} else if ( tab_type == "taxonomy" ) {
						if ( taxonomy_term ) {
							var termParts = taxonomy_term.split("_");
							if ( termParts[0] == "category" ) {
								title = taxonomy_title_override || "Category Selected";
							} else if ( termParts[0] == "region" ) {
								title = taxonomy_title_override || "Region Selected";
							} else {
								title = taxonomy_title_override || "Taxonomy Selected";
							}
						} else {
							title = "No Taxonomy Selected";
						}
					}
					var tabTypeLabel = tab_type.charAt(0).toUpperCase() + tab_type.slice(1);
				#>
				{{ tabTypeLabel }}: {{ title }}',
			]
		);

		$this->end_controls_section();

		// Style Section - Container
		$this->start_controls_section(
			'container_style_section',
			[
				'label' => __( 'Container', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'container_background',
				'types' => [ 'classic', 'gradient' ],
				'selector' => '{{WRAPPER}} .services-container',
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'container_border',
				'selector' => '{{WRAPPER}} .services-container',
			]
		);

		$this->add_control(
			'container_border_radius',
			[
				'label' => __( 'Border Radius', 'listeo_elementor' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .services-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'container_box_shadow',
				'selector' => '{{WRAPPER}} .services-container',
			]
		);

		$this->end_controls_section();

		// Style Section - Navigation
		$this->start_controls_section(
			'navigation_style_section',
			[
				'label' => __( 'Navigation', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'nav_typography',
				'selector' => '{{WRAPPER}} .nav-label',
			]
		);

		$this->start_controls_tabs( 'nav_style_tabs' );

		$this->start_controls_tab(
			'nav_normal_tab',
			[
				'label' => __( 'Normal', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'nav_text_color',
			[
				'label' => __( 'Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-label' => 'color: {{VALUE}};',
					'{{WRAPPER}} .nav-icon' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'nav_background_color',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-item' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'nav_active_tab',
			[
				'label' => __( 'Active', 'listeo_elementor' ),
			]
		);

		$this->add_control(
			'nav_active_text_color',
			[
				'label' => __( 'Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-item.active .nav-label' => 'color: {{VALUE}};',
					'{{WRAPPER}} .nav-item.active .nav-icon' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'nav_active_background_color',
			[
				'label' => __( 'Background Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-item.active' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'nav_active_indicator_color',
			[
				'label' => __( 'Active Indicator Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .nav-item.active::after' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		// Style Section - Content
		$this->start_controls_section(
			'content_style_section',
			[
				'label' => __( 'Content', 'listeo_elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'content_title_typography',
				'selector' => '{{WRAPPER}} .content-title',
			]
		);

		$this->add_control(
			'content_title_color',
			[
				'label' => __( 'Title Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .content-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'content_text_typography',
				'selector' => '{{WRAPPER}} .content-item',
			]
		);

		$this->add_control(
			'content_text_color',
			[
				'label' => __( 'Text Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .content-item' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'check_icon_color',
			[
				'label' => __( 'Check Icon Color', 'listeo_elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .check-icon' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Get combined taxonomy terms options for individual tabs
	 */
	protected function get_taxonomy_terms_options() {
		$options = [ '' => __( 'Select a term...', 'listeo_elementor' ) ];
		
		// Get listing categories
		$categories = get_terms( [
			'taxonomy' => 'listing_category',
			'hide_empty' => false,
		] );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			foreach ( $categories as $term ) {
				$options[ 'category_' . $term->term_id ] = 'Category: ' . $term->name;
			}
		}

		// Get regions
		$regions = get_terms( [
			'taxonomy' => 'region',
			'hide_empty' => false,
		] );

		if ( ! empty( $regions ) && ! is_wp_error( $regions ) ) {
			foreach ( $regions as $term ) {
				$options[ 'region_' . $term->term_id ] = 'Region: ' . $term->name;
			}
		}

		return $options;
	}

	/**
	 * Get icon from taxonomy term meta or category settings
	 */
	protected function get_term_icon( $term_id, $taxonomy ) {
		// First try SVG icon using Listeo's method
		$svg_id = get_term_meta( $term_id, '_icon_svg', true );
		if ( $svg_id && function_exists( 'listeo_render_svg_icon' ) ) {
			return 'svg:' . $svg_id; // Return with prefix to indicate SVG
		}
		
		// Try to get font icon from term meta
		$icon = get_term_meta( $term_id, 'icon', true );
		
		// Try alternative meta key used by some themes
		if ( empty( $icon ) ) {
			$icon = get_term_meta( $term_id, '_icon', true );
		}
		
		// Try Font Awesome icon meta
		if ( empty( $icon ) ) {
			$icon = get_term_meta( $term_id, 'fa_icon', true );
		}
		
		// For listing categories, try category-specific meta
		if ( empty( $icon ) && $taxonomy === 'listing_category' ) {
			$icon = get_term_meta( $term_id, 'category_icon', true );
		}
		
		// Try to get from category meta table (if using custom fields)
		if ( empty( $icon ) ) {
			global $wpdb;
			$icon_result = $wpdb->get_var( $wpdb->prepare( 
				"SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = 'icon'", 
				$term_id 
			) );
			if ( $icon_result ) {
				$icon = $icon_result;
			}
		}
		
		// Default fallback
		if ( empty( $icon ) ) {
			$icon = 'fa fa-star';
		}
		
		return $icon;
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
		
		$tabs_data = [];
		
		// Process each tab individually
		if ( ! empty( $settings['tabs'] ) ) {
			foreach ( $settings['tabs'] as $tab ) {
				$tab_data = [];
				
				if ( $tab['tab_type'] === 'custom' ) {
					// Custom tab - use provided data directly
					$tab_data = [
						'tab_title' => $tab['tab_title'],
						'tab_icon' => $tab['tab_icon'],
						'content_title' => $tab['content_title'],
						'content_description' => $tab['content_description'],
						'content_items' => $tab['content_items'],
						'content_image' => $tab['content_image'],
						'tab_background_color' => $tab['tab_background_color'],
						'show_button' => $tab['show_button'],
						'button_text' => $tab['button_text'],
						'button_link' => $tab['button_link'],
						'button_icon' => $tab['button_icon'],
						'check_icon' => $tab['check_icon'],
					];
				} else if ( $tab['tab_type'] === 'taxonomy' && ! empty( $tab['taxonomy_term'] ) ) {
					// Taxonomy tab - get data from selected term
					$term_parts = explode( '_', $tab['taxonomy_term'] );
					if ( count( $term_parts ) >= 2 ) {
						$taxonomy = $term_parts[0] === 'category' ? 'listing_category' : 'region';
						$term_id = $term_parts[1];
						
						$term = get_term( $term_id, $taxonomy );
						if ( $term && ! is_wp_error( $term ) ) {
							$icon = $this->get_term_icon( $term_id, $taxonomy );
							
							// Handle icon data properly for both SVG and font icons
							$tab_icon_data = [];
							if ( strpos( $icon, 'svg:' ) === 0 ) {
								// SVG icon - store as special type
								$tab_icon_data = [
									'type' => 'svg',
									'svg_id' => str_replace( 'svg:', '', $icon )
								];
							} else {
								// Font icon - store as standard Elementor icon
								$tab_icon_data = [
									'value' => $icon,
									'library' => 'fa-solid',
								];
							}
							
							// Get image
							$cover_url = Utils::get_placeholder_image_src();
							if ( ! empty( $tab['content_image']['url'] ) ) {
								$cover_url = $tab['content_image']['url'];
							} else {
								$cover_id = get_term_meta( $term_id, '_cover', true );
								if ( $cover_id ) {
									$cover_image = wp_get_attachment_image_src( $cover_id, 'large' );
									if ( $cover_image ) {
										$cover_url = $cover_image[0];
									}
								}
							}
							
							$tab_data = [
								'tab_title' => ! empty( $tab['taxonomy_title_override'] ) ? $tab['taxonomy_title_override'] : $term->name,
								'tab_icon' => $tab_icon_data,
								'content_title' => $tab['content_title'],
								'content_description' => $tab['content_description'],
								'content_items' => $tab['content_items'],
								'content_image' => [
									'url' => $cover_url,
								],
								'tab_background_color' => $tab['tab_background_color'],
								'show_button' => $tab['show_button'],
								'button_text' => $tab['button_text'],
								'button_link' => $tab['button_link'],
								'button_icon' => $tab['button_icon'],
								'check_icon' => $tab['check_icon'],
							];
						}
					}
				}
				
				if ( ! empty( $tab_data ) ) {
					$tabs_data[] = $tab_data;
				}
			}
		}
		
		if ( empty( $tabs_data ) ) {
			echo '<p>' . __( 'No items to display. Please configure the widget settings.', 'listeo_elementor' ) . '</p>';
			return;
		}
		?>
		
		<div class="services-container">
			<nav class="services-nav">
				<?php foreach ( $tabs_data as $index => $item ) : ?>
					<div class="nav-item <?php echo $index === 0 ? 'active' : ''; ?>" data-tab="tab-<?php echo $index; ?>" data-bg-color="<?php echo esc_attr( $item['tab_background_color'] ); ?>">
						<div class="nav-icon">
							<?php if ( ! empty( $item['tab_icon'] ) ) : ?>
								<?php if ( isset( $item['tab_icon']['type'] ) && $item['tab_icon']['type'] === 'svg' ) : ?>
									<?php
									// Render SVG icon using Listeo's method (for taxonomy tabs)
									if ( function_exists( 'listeo_render_svg_icon' ) ) {
										echo listeo_render_svg_icon( $item['tab_icon']['svg_id'] );
									}
									?>
								<?php elseif ( ! empty( $item['tab_icon']['value'] ) ) : ?>
									<?php
									// Check if the icon value is an SVG attachment ID
									if ( is_array( $item['tab_icon']['value'] ) && ! empty( $item['tab_icon']['value']['id'] ) ) {
										// This is an SVG selected through Elementor's icon picker
										if ( function_exists( 'listeo_render_svg_icon' ) ) {
											echo listeo_render_svg_icon( $item['tab_icon']['value']['id'] );
										} else {
											// Fallback to icon class if SVG render function not available
											echo '<i class="' . esc_attr( $item['tab_icon']['value']['value'] ) . '"></i>';
										}
									} elseif ( is_array( $item['tab_icon']['value'] ) ) { ?>
										<i class="<?php echo esc_attr( $item['tab_icon']['value']['value'] ); ?>"></i>
									<?php } else { ?>
										<i class="<?php echo esc_attr( $item['tab_icon']['value'] ); ?>"></i>
									<?php } ?>
								<?php else : ?>
									<i class="fa fa-star"></i>
								<?php endif; ?>
							<?php else : ?>
								<i class="fa fa-star"></i>
							<?php endif; ?>
						</div>
						<div class="nav-label"><?php echo esc_html( $item['tab_title'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</nav>

			<div class="content-area">
				<?php foreach ( $tabs_data as $index => $item ) : ?>
					<div class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>" 
						 id="tab-<?php echo $index; ?>" 
						 data-bg-color="<?php echo esc_attr( $item['tab_background_color'] ); ?>"
						 style="background-color: <?php echo esc_attr( $item['tab_background_color'] ); ?>;">
						<div class="content-background-image">
							<img src="<?php echo esc_url( $item['content_image']['url'] ); ?>" alt="<?php echo esc_attr( $item['content_title'] ); ?>">
						</div>
						<div class="content-card">
							<h2 class="content-title"><?php echo esc_html( $item['content_title'] ); ?></h2>
							<?php if ( ! empty( $item['content_description'] ) ) : ?>
								<div class="content-description"><?php echo wp_kses_post( $item['content_description'] ); ?></div>
							<?php endif; ?>
							<?php if ( ! empty( $item['content_items'] ) ) : ?>
								<ul class="content-list">
									<?php 
									$content_items = explode( "\n", $item['content_items'] );
									foreach ( $content_items as $content_item ) :
										if ( trim( $content_item ) ) :
									?>
										<li class="content-item">
											<span class="check-icon">
												<?php if ( ! empty( $item['check_icon']['value'] ) ) : ?>
													<?php if ( is_array( $item['check_icon']['value'] ) ) : ?>
														<i class="<?php echo esc_attr( $item['check_icon']['value']['value'] ); ?>"></i>
													<?php else : ?>
														<i class="<?php echo esc_attr( $item['check_icon']['value'] ); ?>"></i>
													<?php endif; ?>
												<?php else : ?>
													<i class="fas fa-check"></i>
												<?php endif; ?>
											</span>
											<span><?php echo esc_html( trim( $content_item ) ); ?></span>
										</li>
									<?php 
										endif;
									endforeach; 
									?>
								</ul>
							<?php else : ?>
								<p class="no-content-message" style="color: #999; font-style: italic;">
									<?php _e( 'No content configured for this tab. Please add content in the widget settings.', 'listeo_elementor' ); ?>
								</p>
							<?php endif; ?>
							
							<?php if ( ! empty( $item['show_button'] ) && $item['show_button'] === 'yes' ) : ?>
								<div class="content-button-wrapper">
									<?php
									$button_tag = 'div';
									$button_attrs = [];
									
									if ( ! empty( $item['button_link']['url'] ) ) {
										$button_tag = 'a';
										$button_attrs[] = 'href="' . esc_url( $item['button_link']['url'] ) . '"';
										
										if ( $item['button_link']['is_external'] ) {
											$button_attrs[] = 'target="_blank"';
										}
										
										if ( $item['button_link']['nofollow'] ) {
											$button_attrs[] = 'rel="nofollow"';
										}
									}
									?>
									<<?php echo $button_tag; ?> class="content-button" <?php echo implode( ' ', $button_attrs ); ?>>
										<span class="button-text"><?php echo esc_html( $item['button_text'] ); ?></span>
										<?php if ( ! empty( $item['button_icon']['value'] ) ) : ?>
											<span class="button-icon">
												<?php if ( is_array( $item['button_icon']['value'] ) ) : ?>
													<i class="<?php echo esc_attr( $item['button_icon']['value']['value'] ); ?>"></i>
												<?php else : ?>
													<i class="<?php echo esc_attr( $item['button_icon']['value'] ); ?>"></i>
												<?php endif; ?>
											</span>
										<?php endif; ?>
									</<?php echo $button_tag; ?>>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
