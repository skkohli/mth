<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Advanced Review Criteria Management
 *
 * Provides admin UI for managing per-type and per-taxonomy review criteria
 *
 * @class Listeo_Reviews_Criteria_Advanced
 * @since 1.9.25
 */
class Listeo_Reviews_Criteria_Advanced
{

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 * @var Listeo_Reviews_Criteria_Advanced The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Returns static instance of class.
	 *
	 * @return self
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		add_action('admin_menu', array($this, 'add_submenu_pages'), 20);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

		// AJAX handlers
		add_action('wp_ajax_listeo_load_type_criteria', array($this, 'ajax_load_type_criteria'));
		add_action('wp_ajax_listeo_save_type_criteria', array($this, 'ajax_save_type_criteria'));
		add_action('wp_ajax_listeo_load_taxonomy_criteria', array($this, 'ajax_load_taxonomy_criteria'));
		add_action('wp_ajax_listeo_save_taxonomy_criteria', array($this, 'ajax_save_taxonomy_criteria'));
		add_action('wp_ajax_listeo_get_taxonomy_terms', array($this, 'ajax_get_taxonomy_terms'));
		add_action('wp_ajax_listeo_copy_criteria', array($this, 'ajax_copy_criteria'));
	}

	/**
	 * Add submenu pages
	 *
	 * DISABLED - All functionality has been merged into the main class-listeo-reviews-criteria.php
	 * which now uses a unified sidebar tab interface instead of separate dropdown-based pages.
	 *
	 * This class is kept for backwards compatibility but its submenu pages are no longer created.
	 * The AJAX handlers below may still be used by the old JavaScript if needed.
	 */
	public function add_submenu_pages()
	{
		// DISABLED - Now using unified sidebar tab interface in main Reviews Criteria page
		return;
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets($hook)
	{
		if ($hook !== 'reviews-criteria_page_listeo-reviews-criteria-types' && $hook !== 'reviews-criteria_page_listeo-reviews-criteria-taxonomies') {
			return;
		}

		wp_enqueue_style('listeo-reviews-criteria-advanced', plugin_dir_url(__FILE__) . '../css/reviews-criteria-advanced.css', array(), '1.0.0');
		wp_enqueue_script('listeo-reviews-criteria-advanced', plugin_dir_url(__FILE__) . '../js/reviews-criteria-advanced.js', array('jquery'), '1.0.0', true);

		wp_localize_script('listeo-reviews-criteria-advanced', 'listeoReviewsCriteriaL10n', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('listeo_reviews_criteria_nonce'),
			'confirm_remove' => __('Are you sure you want to remove this criteria?', 'listeo-fafe'),
			'saved_successfully' => __('Criteria saved successfully!', 'listeo-fafe'),
			'error_occurred' => __('An error occurred. Please try again.', 'listeo-fafe'),
			'select_type_first' => __('Please select a listing type first', 'listeo-fafe'),
			'select_taxonomy_first' => __('Please select a taxonomy first', 'listeo-fafe'),
			'select_term_first' => __('Please select a term first', 'listeo-fafe'),
		));
	}

	/**
	 * Output per-type criteria page
	 */
	public function output_type_criteria()
	{
		// Get all listing types
		$listing_types = array();
		if (class_exists('Listeo_Core_Custom_Listing_Types')) {
			$listing_types = Listeo_Core_Custom_Listing_Types::instance()->get_listing_types(true, false);
		}

?>
		<div class="wrap listeo-reviews-criteria-advanced">
			<h1><?php esc_html_e('Review Criteria - Per Listing Type', 'listeo-fafe'); ?></h1>

			<p class="description">
				<?php esc_html_e('Configure custom review criteria for specific listing types. When set, these criteria will override the global criteria for listings of that type.', 'listeo-fafe'); ?>
			</p>

			<div class="listeo-criteria-selector-wrap">
				<div class="listeo-criteria-selector">
					<label for="listeo-type-selector">
						<?php esc_html_e('Select Listing Type:', 'listeo-fafe'); ?>
					</label>
					<select id="listeo-type-selector" class="listeo-type-selector">
						<option value=""><?php esc_html_e('-- Choose Type --', 'listeo-fafe'); ?></option>
						<?php foreach ($listing_types as $type) : ?>
							<option value="<?php echo esc_attr($type->slug); ?>">
								<?php echo esc_html($type->name); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button type="button" id="listeo-copy-criteria-btn" class="button" disabled>
						<?php esc_html_e('Copy From...', 'listeo-fafe'); ?>
					</button>
				</div>

				<div id="listeo-copy-criteria-modal" class="listeo-modal" style="display:none;">
					<div class="listeo-modal-content">
						<h3><?php esc_html_e('Copy Criteria From', 'listeo-fafe'); ?></h3>
						<div class="listeo-modal-body">
							<label>
								<input type="radio" name="copy_source" value="global" checked>
								<?php esc_html_e('Global Default Criteria', 'listeo-fafe'); ?>
							</label>
							<?php foreach ($listing_types as $type) : ?>
								<label>
									<input type="radio" name="copy_source" value="type:<?php echo esc_attr($type->slug); ?>">
									<?php printf(esc_html__('Type: %s', 'listeo-fafe'), esc_html($type->name)); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="listeo-modal-footer">
							<button type="button" class="button button-primary" id="listeo-copy-confirm">
								<?php esc_html_e('Copy', 'listeo-fafe'); ?>
							</button>
							<button type="button" class="button" id="listeo-copy-cancel">
								<?php esc_html_e('Cancel', 'listeo-fafe'); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<div id="listeo-criteria-editor" style="display:none;">
				<form id="listeo-type-criteria-form">
					<input type="hidden" id="listeo-selected-type" name="listing_type" value="">

					<div class="listeo-form-editor main-options">
						<table class="widefat fixed">
							<thead>
								<tr>
									<th width="20%"><?php esc_html_e('Criterium Title', 'listeo-fafe'); ?></th>
									<th><?php esc_html_e('Tooltip (optional)', 'listeo-fafe'); ?></th>
									<th width="20%"></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<td colspan="3">
										<a class="button-primary add-new-main-option" href="#">
											<?php esc_html_e('Add New', 'listeo-fafe'); ?>
										</a>
									</td>
								</tr>
							</tfoot>
							<tbody id="listeo-criteria-rows">
								<!-- Criteria rows will be loaded here via AJAX -->
							</tbody>
						</table>
					</div>

					<div class="listeo-forms-editor-bottom">
						<button type="submit" class="save-fields button-primary">
							<?php esc_html_e('Save Changes', 'listeo-fafe'); ?>
						</button>
						<button type="button" id="listeo-reset-type-criteria" class="reset button-secondary">
							<?php esc_html_e('Clear Type-Specific Criteria (Use Global)', 'listeo-fafe'); ?>
						</button>
					</div>
				</form>
			</div>

			<div id="listeo-loading" style="display:none;">
				<p><?php esc_html_e('Loading...', 'listeo-fafe'); ?></p>
			</div>
		</div>
<?php
	}

	/**
	 * Output per-taxonomy criteria page
	 */
	public function output_taxonomy_criteria()
	{
?>
		<div class="wrap listeo-reviews-criteria-advanced">
			<h1><?php esc_html_e('Review Criteria - Per Taxonomy Term', 'listeo-fafe'); ?></h1>

			<p class="description">
				<?php esc_html_e('Configure custom review criteria for specific taxonomy terms (categories, regions, etc.). When set, these criteria will override type-specific and global criteria for listings with that term.', 'listeo-fafe'); ?>
			</p>

			<div class="listeo-criteria-selector-wrap">
				<div class="listeo-criteria-selector">
					<label for="listeo-taxonomy-selector">
						<?php esc_html_e('Select Taxonomy:', 'listeo-fafe'); ?>
					</label>
					<select id="listeo-taxonomy-selector" class="listeo-taxonomy-selector">
						<option value=""><?php esc_html_e('-- Choose Taxonomy --', 'listeo-fafe'); ?></option>
						<option value="listing_category"><?php esc_html_e('Listing Category', 'listeo-fafe'); ?></option>
						<option value="region"><?php esc_html_e('Region', 'listeo-fafe'); ?></option>
						<option value="listing_feature"><?php esc_html_e('Features', 'listeo-fafe'); ?></option>
					</select>

					<label for="listeo-term-selector">
						<?php esc_html_e('Select Term:', 'listeo-fafe'); ?>
					</label>
					<select id="listeo-term-selector" class="listeo-term-selector" disabled>
						<option value=""><?php esc_html_e('-- First select taxonomy --', 'listeo-fafe'); ?></option>
					</select>

					<button type="button" id="listeo-copy-criteria-tax-btn" class="button" disabled>
						<?php esc_html_e('Copy From...', 'listeo-fafe'); ?>
					</button>
				</div>
			</div>

			<div id="listeo-criteria-editor-tax" style="display:none;">
				<div id="listeo-hierarchy-context" class="notice notice-info inline" style="display:none;">
					<p><strong><?php esc_html_e('Hierarchy:', 'listeo-fafe'); ?></strong> <span id="listeo-hierarchy-path"></span></p>
				</div>

				<form id="listeo-taxonomy-criteria-form">
					<input type="hidden" id="listeo-selected-taxonomy" name="taxonomy" value="">
					<input type="hidden" id="listeo-selected-term" name="term_id" value="">

					<div class="listeo-form-editor main-options">
						<table class="widefat fixed">
							<thead>
								<tr>
									<th width="20%"><?php esc_html_e('Criterium Title', 'listeo-fafe'); ?></th>
									<th><?php esc_html_e('Tooltip (optional)', 'listeo-fafe'); ?></th>
									<th width="20%"></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<td colspan="3">
										<a class="button-primary add-new-main-option-tax" href="#">
											<?php esc_html_e('Add New', 'listeo-fafe'); ?>
										</a>
									</td>
								</tr>
							</tfoot>
							<tbody id="listeo-criteria-rows-tax">
								<!-- Criteria rows will be loaded here via AJAX -->
							</tbody>
						</table>
					</div>

					<div class="listeo-forms-editor-bottom">
						<button type="submit" class="save-fields button-primary">
							<?php esc_html_e('Save Changes', 'listeo-fafe'); ?>
						</button>
						<button type="button" id="listeo-reset-taxonomy-criteria" class="reset button-secondary">
							<?php esc_html_e('Clear Term-Specific Criteria (Use Type/Global)', 'listeo-fafe'); ?>
						</button>
					</div>
				</form>
			</div>

			<div id="listeo-loading-tax" style="display:none;">
				<p><?php esc_html_e('Loading...', 'listeo-fafe'); ?></p>
			</div>
		</div>
<?php
	}

	/**
	 * AJAX: Load criteria for a specific listing type
	 */
	public function ajax_load_type_criteria()
	{
		check_ajax_referer('listeo_reviews_criteria_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo-fafe')));
		}

		$listing_type = isset($_POST['listing_type']) ? sanitize_text_field($_POST['listing_type']) : '';

		if (empty($listing_type)) {
			wp_send_json_error(array('message' => __('Invalid listing type', 'listeo-fafe')));
		}

		// Get type-specific criteria
		$types_criteria = get_option('listeo_reviews_criteria_types', array());
		$criteria = isset($types_criteria[$listing_type]) ? $types_criteria[$listing_type] : array();

		// If no type-specific criteria, get global as starting point
		if (empty($criteria)) {
			$global = get_option('listeo_reviews_criteria_global');
			if (!empty($global) && is_array($global)) {
				$criteria = $global;
			} else {
				// Fallback to hardcoded defaults
				$criteria = listeo_get_reviews_criteria();
			}
		}

		wp_send_json_success(array('criteria' => $criteria));
	}

	/**
	 * AJAX: Save criteria for a specific listing type
	 */
	public function ajax_save_type_criteria()
	{
		check_ajax_referer('listeo_reviews_criteria_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo-fafe')));
		}

		$listing_type = isset($_POST['listing_type']) ? sanitize_text_field($_POST['listing_type']) : '';
		$labels = isset($_POST['label']) ? array_map('sanitize_text_field', $_POST['label']) : array();
		$tooltips = isset($_POST['tooltip']) ? array_map('sanitize_textarea_field', $_POST['tooltip']) : array();

		if (empty($listing_type)) {
			wp_send_json_error(array('message' => __('Invalid listing type', 'listeo-fafe')));
		}

		// Build criteria array
		$new_criteria = array();
		foreach ($labels as $key => $label) {
			if (empty($label)) {
				continue;
			}
			$slug = sanitize_title($label);
			$new_criteria[$slug] = array(
				'label' => $label,
				'tooltip' => isset($tooltips[$key]) ? $tooltips[$key] : '',
			);
		}

		// Get existing type criteria
		$types_criteria = get_option('listeo_reviews_criteria_types', array());

		// Update or add this type's criteria
		$types_criteria[$listing_type] = $new_criteria;

		// Save back
		update_option('listeo_reviews_criteria_types', $types_criteria);

		// Clear cache
		wp_cache_flush_group('listeo_reviews');

		wp_send_json_success(array('message' => __('Criteria saved successfully!', 'listeo-fafe')));
	}

	/**
	 * AJAX: Load criteria for a specific taxonomy term
	 */
	public function ajax_load_taxonomy_criteria()
	{
		check_ajax_referer('listeo_reviews_criteria_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo-fafe')));
		}

		$taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;

		if (empty($taxonomy) || empty($term_id)) {
			wp_send_json_error(array('message' => __('Invalid taxonomy or term', 'listeo-fafe')));
		}

		// Get term details for hierarchy display
		$term = get_term($term_id, $taxonomy);
		if (is_wp_error($term)) {
			wp_send_json_error(array('message' => __('Term not found', 'listeo-fafe')));
		}

		// Build hierarchy path
		$hierarchy_path = $this->get_term_hierarchy_path($term);

		// Get taxonomy-specific criteria
		$taxonomies_criteria = get_option('listeo_reviews_criteria_taxonomies', array());
		$criteria = isset($taxonomies_criteria[$taxonomy][$term_id]) ? $taxonomies_criteria[$taxonomy][$term_id] : array();

		// If no taxonomy-specific criteria, get global as starting point
		if (empty($criteria)) {
			$global = get_option('listeo_reviews_criteria_global');
			if (!empty($global) && is_array($global)) {
				$criteria = $global;
			} else {
				// Fallback to hardcoded defaults
				$criteria = listeo_get_reviews_criteria();
			}
		}

		wp_send_json_success(array(
			'criteria' => $criteria,
			'hierarchy_path' => $hierarchy_path
		));
	}

	/**
	 * AJAX: Save criteria for a specific taxonomy term
	 */
	public function ajax_save_taxonomy_criteria()
	{
		check_ajax_referer('listeo_reviews_criteria_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo-fafe')));
		}

		$taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		$labels = isset($_POST['label']) ? array_map('sanitize_text_field', $_POST['label']) : array();
		$tooltips = isset($_POST['tooltip']) ? array_map('sanitize_textarea_field', $_POST['tooltip']) : array();

		if (empty($taxonomy) || empty($term_id)) {
			wp_send_json_error(array('message' => __('Invalid taxonomy or term', 'listeo-fafe')));
		}

		// Build criteria array
		$new_criteria = array();
		foreach ($labels as $key => $label) {
			if (empty($label)) {
				continue;
			}
			$slug = sanitize_title($label);
			$new_criteria[$slug] = array(
				'label' => $label,
				'tooltip' => isset($tooltips[$key]) ? $tooltips[$key] : '',
			);
		}

		// Get existing taxonomy criteria
		$taxonomies_criteria = get_option('listeo_reviews_criteria_taxonomies', array());

		// Initialize taxonomy array if not exists
		if (!isset($taxonomies_criteria[$taxonomy])) {
			$taxonomies_criteria[$taxonomy] = array();
		}

		// Update or add this term's criteria
		$taxonomies_criteria[$taxonomy][$term_id] = $new_criteria;

		// Save back
		update_option('listeo_reviews_criteria_taxonomies', $taxonomies_criteria);

		// Clear cache
		wp_cache_flush_group('listeo_reviews');

		wp_send_json_success(array('message' => __('Criteria saved successfully!', 'listeo-fafe')));
	}

	/**
	 * AJAX: Get terms for a selected taxonomy
	 */
	public function ajax_get_taxonomy_terms()
	{
		check_ajax_referer('listeo_reviews_criteria_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo-fafe')));
		}

		$taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';

		if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
			wp_send_json_error(array('message' => __('Invalid taxonomy', 'listeo-fafe')));
		}

		$terms = get_terms(array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC',
		));

		if (is_wp_error($terms)) {
			wp_send_json_error(array('message' => __('Could not load terms', 'listeo-fafe')));
		}

		$terms_data = array();
		foreach ($terms as $term) {
			$terms_data[] = array(
				'id' => $term->term_id,
				'name' => $term->name,
				'parent' => $term->parent,
			);
		}

		wp_send_json_success(array('terms' => $terms_data));
	}

	/**
	 * AJAX: Copy criteria from another source
	 */
	public function ajax_copy_criteria()
	{
		check_ajax_referer('listeo_reviews_criteria_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'listeo-fafe')));
		}

		$source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

		if (empty($source)) {
			wp_send_json_error(array('message' => __('Invalid source', 'listeo-fafe')));
		}

		$criteria = array();

		if ($source === 'global') {
			// Copy from global
			$global = get_option('listeo_reviews_criteria_global');
			if (!empty($global) && is_array($global)) {
				$criteria = $global;
			} else {
				$criteria = listeo_get_reviews_criteria();
			}
		} elseif (strpos($source, 'type:') === 0) {
			// Copy from another type
			$type_slug = str_replace('type:', '', $source);
			$types_criteria = get_option('listeo_reviews_criteria_types', array());
			if (isset($types_criteria[$type_slug])) {
				$criteria = $types_criteria[$type_slug];
			}
		}

		if (empty($criteria)) {
			wp_send_json_error(array('message' => __('Source criteria not found', 'listeo-fafe')));
		}

		wp_send_json_success(array('criteria' => $criteria));
	}

	/**
	 * Get term hierarchy path for display
	 *
	 * @param WP_Term $term
	 * @return string
	 */
	private function get_term_hierarchy_path($term)
	{
		$path = array();
		$current = $term;

		// Build path from term to root
		while ($current) {
			array_unshift($path, $current->name);
			if ($current->parent) {
				$current = get_term($current->parent, $current->taxonomy);
				if (is_wp_error($current)) {
					break;
				}
			} else {
				break;
			}
		}

		// Add taxonomy name
		$taxonomy_obj = get_taxonomy($term->taxonomy);
		if ($taxonomy_obj) {
			array_unshift($path, $taxonomy_obj->labels->name);
		}

		return implode(' &raquo; ', $path);
	}
}
