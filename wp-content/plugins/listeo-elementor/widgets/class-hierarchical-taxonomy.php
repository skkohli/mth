<?php
/**
 * Hierarchical Taxonomy Display Widget
 *
 * @category   Class
 * @package    ElementorListeo
 * @subpackage WordPress
 * @author     Purethemes.net
 * @copyright  Purethemes.net
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @since      1.0.0
 */

namespace ElementorListeo\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Utils;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
	// Exit if accessed directly.
	exit;
}

/**
 * Hierarchical Taxonomy Display widget class.
 *
 * @since 1.0.0
 */
class HierarchicalTaxonomy extends Widget_Base
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
		return 'listeo-hierarchical-taxonomy';
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
		return __('Hierarchical Taxonomy Display', 'listeo_elementor');
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
		return 'eicon-nested-carousel';
	}

	/**
	 * Get script dependencies.
	 *
	 * Retrieve the list of script dependencies the widget requires.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return array Widget script dependencies.
	 */
	public function get_script_depends() {
		return [ 'hierarchical-taxonomy-js' ];
	}

	/**
	 * Get style dependencies.
	 *
	 * Retrieve the list of style dependencies the widget requires.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return array Widget style dependencies.
	 */
	public function get_style_depends() {
		return [ 'hierarchical-taxonomy-css' ];
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
			'taxonomy',
			[
				'label' => __('Taxonomy', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT,
				'default' => 'listing_category',
				'options' => $this->get_taxonomies(),
			]
		);

		$this->add_control(
			'columns',
			[
				'label' => __('Columns', 'listeo_elementor'),
				'type' => Controls_Manager::SELECT,
				'default' => '3',
				'options' => [
					'2' => __('2 Columns', 'listeo_elementor'),
					'3' => __('3 Columns', 'listeo_elementor'),
					'4' => __('4 Columns', 'listeo_elementor'),
				],
			]
		);

		$this->add_control(
			'show_count',
			[
				'label' => __('Show Listings Count', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'hide_empty',
			[
				'label' => __('Hide Empty Terms', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Hide', 'listeo_elementor'),
				'label_off' => __('Show', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'no',
			]
		);

		$this->add_control(
			'expand_all',
			[
				'label' => __('Show All Children', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Show', 'listeo_elementor'),
				'label_off' => __('Hide', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'hide_child_icons',
			[
				'label' => __('Hide Icons for Child Categories', 'listeo_elementor'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => __('Hide', 'listeo_elementor'),
				'label_off' => __('Show', 'listeo_elementor'),
				'return_value' => 'yes',
				'default' => 'no',
				'description' => __('When enabled, child/subcategory items will not display icons, only parent categories will show icons.', 'listeo_elementor'),
			]
		);

		$this->end_controls_section();

		// Style Section
		$this->start_controls_section(
			'section_style',
			[
				'label' => __('Style', 'listeo_elementor'),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'card_background',
			[
				'label' => __('Card Background', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .hierarchical-taxonomy-item' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'card_hover_background',
			[
				'label' => __('Card Hover Background', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#f9fafb',
				'selectors' => [
					'{{WRAPPER}} .hierarchical-taxonomy-item:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'card_border',
				'label' => __('Card Border', 'listeo_elementor'),
				'selector' => '{{WRAPPER}} .hierarchical-taxonomy-item',
			]
		);

		$this->add_control(
			'card_border_radius',
			[
				'label' => __('Border Radius', 'listeo_elementor'),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => ['px', '%'],
				'selectors' => [
					'{{WRAPPER}} .hierarchical-taxonomy-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'card_shadow',
				'label' => __('Card Shadow', 'listeo_elementor'),
				'selector' => '{{WRAPPER}} .hierarchical-taxonomy-item',
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'title_typography',
				'label' => __('Title Typography', 'listeo_elementor'),
				'selector' => '{{WRAPPER}} .taxonomy-title',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label' => __('Title Color', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#111827',
				'selectors' => [
					'{{WRAPPER}} .taxonomy-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'count_typography',
				'label' => __('Count Typography', 'listeo_elementor'),
				'selector' => '{{WRAPPER}} .taxonomy-count',
			]
		);

		$this->add_control(
			'count_color',
			[
				'label' => __('Count Color', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#6b7280',
				'selectors' => [
					'{{WRAPPER}} .taxonomy-count' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label' => __('Icon Color', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#8b5cf6',
				'selectors' => [
					'{{WRAPPER}} .taxonomy-icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .taxonomy-icon svg' => 'fill: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'arrow_color',
			[
				'label' => __('Arrow Color', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#9ca3af',
				'selectors' => [
					'{{WRAPPER}} .taxonomy-arrow' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		// Child Style Section
		$this->start_controls_section(
			'section_child_style',
			[
				'label' => __('Child Terms Style', 'listeo_elementor'),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'child_background',
			[
				'label' => __('Child Background', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#f8fafc',
				'selectors' => [
					'{{WRAPPER}} .child-taxonomy-item' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'child_hover_background',
			[
				'label' => __('Child Hover Background', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#e2e8f0',
				'selectors' => [
					'{{WRAPPER}} .child-taxonomy-item:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'child_title_typography',
				'label' => __('Child Title Typography', 'listeo_elementor'),
				'selector' => '{{WRAPPER}} .child-taxonomy-title',
			]
		);

		$this->add_control(
			'child_title_color',
			[
				'label' => __('Child Title Color', 'listeo_elementor'),
				'type' => Controls_Manager::COLOR,
				'default' => '#374151',
				'selectors' => [
					'{{WRAPPER}} .child-taxonomy-title' => 'color: {{VALUE}};',
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
		
		$taxonomy = $settings['taxonomy'];
		$columns = $settings['columns'];
		$show_count = $settings['show_count'] === 'yes';
		$hide_empty = $settings['hide_empty'] === 'yes';
		$expand_all = $settings['expand_all'] === 'yes';
		$hide_child_icons = $settings['hide_child_icons'] === 'yes';

		if (empty($taxonomy)) {
			return;
		}

		// Get parent terms
		$parent_terms = get_terms(array(
			'taxonomy' => $taxonomy,
			'hide_empty' => $hide_empty,
			'parent' => 0,
			'orderby' => 'name',
			'order' => 'ASC',
		));

		if (empty($parent_terms) || is_wp_error($parent_terms)) {
			return;
		}

		$column_class = 'listeo-col-' . $columns;
		$widget_classes = 'hierarchical-taxonomy-display';
		if ($expand_all) {
			$widget_classes .= ' expand-all-default';
		}
?>
		<div class="<?php echo esc_attr($widget_classes); ?>">
			<div class="listeo-grid listeo-grid-cols-<?php echo esc_attr($columns); ?> listeo-gap-4">
				<?php foreach ($parent_terms as $parent_term) : 
					$children = get_terms(array(
						'taxonomy' => $taxonomy,
						'hide_empty' => $hide_empty,
						'parent' => $parent_term->term_id,
						'orderby' => 'name',
						'order' => 'ASC',
					));
					
					$has_children = !empty($children) && !is_wp_error($children);
					
					// Get term icon
					$icon = get_term_meta($parent_term->term_id, 'icon', true);
					$_icon_svg = get_term_meta($parent_term->term_id, '_icon_svg', true);
					$_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
					
					if (empty($icon) && empty($_icon_svg_image)) {
						$icon = 'fa fa-folder';
					}
					
					// Get listing count
					$listing_count = 0;
					if ($show_count) {
						$listing_count = listeo_get_term_post_count($taxonomy, $parent_term->term_id);
					}
				?>
					<div class="hierarchical-taxonomy-card" data-term-id="<?php echo esc_attr($parent_term->term_id); ?>">
						<div class="hierarchical-taxonomy-item listeo-p-4 listeo-cursor-pointer listeo-transition-colors listeo-duration-200 <?php echo $has_children ? 'has-children' : ''; ?>">
							<div class="listeo-flex listeo-items-center listeo-justify-between">
								<a href="<?php echo esc_url($this->get_clean_term_link($parent_term)); ?>" class="listeo-flex listeo-items-center listeo-space-x-3 listeo-flex-grow listeo-text-decoration-none">
									<div class="listeo-flex-shrink-0">
										<?php if (!empty($_icon_svg_image)) : ?>
											<span class="taxonomy-icon listeo-w-5 listeo-h-5">
												<?php echo listeo_render_svg_icon($_icon_svg); ?>
											</span>
										<?php else : ?>
											<?php if ($icon != 'empty') : ?>
												<i class="taxonomy-icon <?php echo esc_attr($icon); ?> listeo-w-5 listeo-h-5"></i>
											<?php endif; ?>
										<?php endif; ?>
									</div>
									<div>
										<h3 class="taxonomy-title listeo-font-semibold listeo-text-sm listeo-m-0"><?php echo esc_html($parent_term->name); ?></h3>
										<?php if ($show_count) : ?>
											<p class="taxonomy-count listeo-text-xs listeo-m-0"><?php echo sprintf(_n('%s listing', '%s listings', $listing_count, 'listeo_elementor'), number_format_i18n($listing_count)); ?></p>
										<?php endif; ?>
									</div>
								</a>
							</div>
						</div>
						
						<?php if ($has_children && $expand_all) : ?>
							<div class="listeo-children-container listeo-bg-gray-50">
								<?php foreach ($children as $child_term) : 
									$child_icon = get_term_meta($child_term->term_id, 'icon', true);
									$child_icon_svg = get_term_meta($child_term->term_id, '_icon_svg', true);
									$child_icon_svg_image = wp_get_attachment_image_src($child_icon_svg, 'medium');
									
									if (empty($child_icon) && empty($child_icon_svg_image)) {
										$child_icon = 'fa fa-file-o';
									}
									
									$child_listing_count = 0;
									if ($show_count) {
										$child_listing_count = listeo_get_term_post_count($taxonomy, $child_term->term_id);
									}
								?>
									<a href="<?php echo esc_url($this->get_clean_term_link($child_term)); ?>" class="listeo-child-taxonomy-item listeo-flex listeo-items-center listeo-justify-between listeo-px-4 listeo-py-3 listeo-border-t listeo-border-gray-200 listeo-transition-colors listeo-duration-200 listeo-text-decoration-none">
										<div class="listeo-flex listeo-items-center listeo-space-x-3">
											<?php if (!$hide_child_icons) : ?>
												<div class="listeo-flex-shrink-0">
													<?php if (!empty($child_icon_svg_image)) : ?>
														<span class="taxonomy-icon listeo-w-4 listeo-h-4">
															<?php echo listeo_render_svg_icon($child_icon_svg); ?>
														</span>
													<?php else : ?>
														<?php if ($child_icon != 'empty') : ?>
															<i class="taxonomy-icon <?php echo esc_attr($child_icon); ?> listeo-w-4 listeo-h-4"></i>
														<?php endif; ?>
													<?php endif; ?>
												</div>
											<?php endif; ?>
											<span class="listeo-child-taxonomy-title listeo-text-sm"><?php echo esc_html($child_term->name); ?></span>
										</div>
										<?php if ($show_count) : ?>
											<span class="taxonomy-count listeo-text-xs"><?php echo number_format_i18n($child_listing_count); ?></span>
										<?php endif; ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
<?php
	}

	/**
	 * Get clean term link without parent slugs for hierarchical display
	 *
	 * @param WP_Term $term The term object
	 * @return string Clean term link
	 */
	protected function get_clean_term_link($term)
	{
		// Check if the problematic filter is enabled and active
		if (get_option('listeo_region_in_links') && class_exists('\Listeo_Core_Post_Types')) {
			$post_types_instance = \Listeo_Core_Post_Types::instance();
			
			// Temporarily remove the filter that adds parent slugs to permalinks
			$had_filter = has_filter('term_link', array($post_types_instance, 'add_term_parents_to_permalinks'));
			
			if ($had_filter) {
				remove_filter('term_link', array($post_types_instance, 'add_term_parents_to_permalinks'), 10);
			}
			
			// Get the clean term link
			$clean_link = get_term_link($term);
			
			// Re-add the filter if it was there
			if ($had_filter) {
				add_filter('term_link', array($post_types_instance, 'add_term_parents_to_permalinks'), 10, 2);
			}
			
			return $clean_link;
		}
		
		// Fallback if the filter isn't active or Listeo Core isn't available
		return get_term_link($term);
	}

	/**
	 * Get available taxonomies
	 */
	protected function get_taxonomies()
	{
		$taxonomies = get_object_taxonomies('listing', 'objects');

		$options = [];

		foreach ($taxonomies as $taxonomy) {
			// Only include hierarchical taxonomies
			if ($taxonomy->hierarchical) {
				$options[$taxonomy->name] = $taxonomy->label;
			}
		}

		return $options;
	}
}
