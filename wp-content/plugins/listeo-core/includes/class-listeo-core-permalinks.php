<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles front admin page for WP Job Manager.
 *
 * @package listeo-core
 * @see https://github.com/woocommerce/woocommerce/blob/3.0.8/includes/admin/class-wc-admin-permalink-settings.php  Based on WooCommerce's implementation.
 * @since 1.27.0
 */
class Listeo_Core_Permalink_Settings {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.27.0
	 */
	private static $_instance = null;

	/**
	 * Permalink settings.
	 *
	 * @var array
	 * @since 1.27.0
	 */
	private $permalinks = array();

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.27.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_fields();
		$this->permalinks = Listeo_Core_Post_Types::get_permalink_structure();
		
		// Hook into WordPress permalink processing at the right time
		add_action('load-options-permalink.php', array($this, 'maybe_save_custom_permalinks'));
		
	}

	/**
	 * Add setting fields related to permalinks.
	 */
	public function setup_fields() {
		add_settings_field(
			'listeo_listing_base_slug',
			__( 'Listing base', 'listeo-core' ),
			array( $this, 'listeo_listing_base_slug_input' ),
			'permalink',
			'optional'
		);
		add_settings_field(
			'listeo_listing_category_slug',
			__( 'Listing category base', 'listeo-core' ),
			array( $this, 'listeo_listing_category_slug_input' ),
			'permalink',
			'optional'
		);
		
		add_settings_field(
			'listeo_listings_archive_slug',
			__( 'Listings archive page', 'listeo-core' ),
			array( $this, 'listeo_listings_archive_slug_input' ),
			'permalink',
			'optional'
		);

		// Add custom permalink fields
		add_settings_field(
			'listeo_custom_permalinks',
			__( 'Custom Permalink Structures', 'listeo-core' ),
			array( $this, 'listeo_custom_permalinks_input' ),
			'permalink',
			'optional'
		);
		
	}


	/**
	 * Show a slug input box for listing post type slug.
	 */
	public function listeo_listing_base_slug_input() {
		?>
		<input name="listeo_listing_base_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['listing_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'listing', 'Listing permalink placeholder', 'listeo-core' ); ?>" /><br>
		 <code>http://example.com/<strong>listing</strong>/single-listing</code>
		<?php
	}

	/**
	 * Show a slug input box for listing category slug.
	 */
	public function listeo_listing_category_slug_input() {
		?>
		<input name="listeo_listing_category_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['category_base'] ); ?>" placeholder="<?php echo esc_attr_x( 'listing-category', 'Listing category slug', 'listeo-core' ); ?>" /><br>
		<code>http://example.com/<strong>listing-category</strong>/hotels</code>
		<?php
	}


	/**
	 * Show a slug input box for listing archive slug.
	 */
	public function listeo_listings_archive_slug_input() {
		?>
		<input name="listeo_listings_archive_slug" type="text" class="regular-text code" value="<?php echo esc_attr( $this->permalinks['listings_archive'] ); ?>" placeholder="<?php echo esc_attr( $this->permalinks['listings_archive_rewrite_slug'] ); ?>" /><br>
		<code>http://example.com/<strong>listings</strong>/</code>
		<?php
	}

	/**
	 * Show custom permalink structure options
	 */
	public function listeo_custom_permalinks_input() {
		$settings = $this->get_custom_permalink_settings();
		$enabled = isset($settings['custom_permalinks_enabled']) && $settings['custom_permalinks_enabled'] === '1';
		$custom_permalinks_enabled = $enabled; // Fix: add the missing variable
		$structure = !empty($settings['custom_structure']) ? $settings['custom_structure'] : '%listing_category%/%listing%';
		$enable_redirects = isset($settings['enable_redirects']) && $settings['enable_redirects'] === '1';
		

		// Enqueue JavaScript for this feature
		wp_enqueue_script('listeo-custom-permalinks-admin', LISTEO_CORE_URL . 'assets/js/custom-permalinks-admin.js', array('jquery'), '1.0.1', true);
		
		// Get token parser for examples
		if (class_exists('Listeo_Core_Permalink_Token_Parser')) {
			$token_parser = new Listeo_Core_Permalink_Token_Parser();
			$available_tokens = $token_parser->get_available_tokens();
			$predefined_structures = array();
			if (class_exists('Listeo_Core_Custom_Permalink_Manager')) {
				$manager = Listeo_Core_Custom_Permalink_Manager::instance();
				$predefined_structures = $manager->get_predefined_structures();
			}
		} else {
			$available_tokens = array();
			$predefined_structures = array();
		}
		?>
		<!-- Hidden field to ensure custom permalink settings are processed -->
		<input type="hidden" name="listeo_custom_permalinks_save" value="1" />
		<input type="hidden" id="listeo_permalink_mode" name="listeo_permalink_mode" value="<?php echo $enabled && !empty($structure) ? 'custom' : 'default'; ?>" />
		<!-- Hidden field for custom structure - IMPORTANT: This stores the selected structure -->
		<input type="hidden" id="listeo_custom_structure" name="listeo_custom_structure" value="<?php echo esc_attr($structure); ?>" />
		
		<?php
		// Show warning notification when WordPress permalink structure is set to "Plain"
		$permalink_structure = get_option('permalink_structure');
		if (empty($permalink_structure)) :
		?>
		<div id="listeo-permalink-warning" class="listeo-permalink-warning" style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; margin-bottom: 15px; border-radius: 4px; border-left: 4px solid #dc3545;">
			<div style="display: flex; align-items: center;">
				<span class="dashicons dashicons-warning" style="color: #dc3545; margin-right: 8px; font-size: 16px;"></span>
				<strong><?php esc_html_e('Warning:', 'listeo-core'); ?></strong>
				<?php esc_html_e('Custom permalink structures require Pretty Permalinks. Please select \'Post name\' or another option above in Common Settings to use custom permalinks.', 'listeo-core'); ?>
			</div>
		</div>
		<?php endif; ?>
		
		<div id="listeo-custom-permalinks-section" class="listeo-permalink-card">
			<div class="listeo-permalink-header">
				<div class="listeo-permalink-title" style="margin-bottom: -10px;">
					<label for="listeo_custom_permalinks_enabled" class="listeo-permalink-main-label">
						<input style="top: 2px; position: relative;"  type="checkbox" id="listeo_custom_permalinks_enabled" 
						       name="listeo_custom_permalinks_enabled" value="1" 
						       <?php checked($enabled); ?> />
						<span class="listeo-permalink-label-text">
							<?php esc_html_e('Custom Permalink Structures', 'listeo-core'); ?>
							<b style="color: #ff9500; font-size: 13px; background: #ff95001a; border-radius: 50px; padding: 2px 8px; line-height: 16px;">Experimental</b>
						</span>
					</label>
					<p class="listeo-permalink-description">
						<?php esc_html_e('Create custom URL structures using tokens like category, region, and date. WordPress will handle redirects automatically.', 'listeo-core'); ?>
					</p>
				</div>
			</div>

			<div id="listeo-custom-permalinks-options" class="listeo-permalink-content" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
				<!-- Predefined Structures Dropdown -->
				<?php if (!empty($predefined_structures)) : ?>
				<div class="listeo-permalink-section">
					<!-- <h4 class="listeo-permalink-section-title">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e('Quick Presets', 'listeo-core'); ?>
					</h4> -->
					<div class="listeo-preset-grid">
						<?php foreach ($predefined_structures as $preset_structure => $preset_data) : 
							// Determine if this preset should be selected
							$is_selected = false;
							if ($preset_structure === 'default') {
								// Default is selected when custom permalinks are disabled or structure is empty
								$is_selected = !$custom_permalinks_enabled || empty($structure);
							} else {
								// Regular presets are selected by exact match
								$is_selected = $structure === $preset_structure && $custom_permalinks_enabled;
							}
						?>
							<div class="listeo-preset-option <?php echo $is_selected ? 'selected' : ''; ?>" 
							     data-structure="<?php echo esc_attr($preset_structure); ?>">
								<div class="listeo-preset-label"><?php echo esc_html($preset_data['label']); ?></div>
								<div class="listeo-preset-example"><?php echo esc_html($preset_data['example']); ?></div>
								<div class="listeo-preset-description"><?php echo esc_html($preset_data['description']); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Visual Token Builder -->
				<?php if (false && !empty($available_tokens)) : // HIDDEN: Tokens section disabled for safety ?>
				<div class="listeo-permalink-section">
					<h4 class="listeo-permalink-section-title">
						<span class="dashicons dashicons-tag"></span>
						<?php esc_html_e('Available Tokens', 'listeo-core'); ?>
					</h4>
					<div class="listeo-token-grid">
						<?php foreach ($available_tokens as $token => $token_data) : ?>
							<div class="listeo-token-card" 
							     data-token="<?php echo esc_attr($token); ?>"
							     title="<?php echo esc_attr($token_data['description']); ?>">
								<div class="listeo-token-name">
									<?php echo esc_html($token_data['label']); ?>
									<?php if ($token_data['required']) : ?>
										<span class="listeo-token-required">*</span>
									<?php endif; ?>
								</div>
								<div class="listeo-token-example"><?php echo esc_html($token_data['example']); ?></div>
								<div class="listeo-token-code"><?php echo esc_html($token); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="listeo-token-help">
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e('Click tokens to add them to your custom structure. Required tokens are marked with *.', 'listeo-core'); ?>
					</p>
				</div>
				<?php endif; ?>

				<!-- Custom Structure Input HIDDEN FOR SAFETY -->
				<?php if (false) : // HIDDEN: Custom structure disabled for safety ?>
				<div class="listeo-permalink-section">
					<h4 class="listeo-permalink-section-title">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e('Custom Structure', 'listeo-core'); ?>
					</h4>
					<div class="listeo-structure-builder">
						<div class="listeo-structure-input-wrapper">
							<input type="text" id="listeo_custom_structure" name="listeo_custom_structure" 
							       class="listeo-structure-input" 
							       value="<?php echo esc_attr($structure); ?>" 
							       placeholder="%listing_category%/%listing%" />
							<div id="listeo-structure-validation" class="listeo-structure-validation"></div>
						</div>
						<div class="listeo-structure-preview-wrapper">
							<label class="listeo-preview-label">
								<span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e('Live Preview', 'listeo-core'); ?>
							</label>
							<div class="listeo-structure-preview">
								<span id="listeo-structure-preview">
									<?php 
									if (class_exists('Listeo_Core_Permalink_Token_Parser')) {
										echo esc_html($token_parser->generate_example($structure));
									} else {
										echo esc_html(home_url('restaurants/amazing-restaurant'));
									}
									?>
								</span>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- Safe Mode Options -->
				<div class="listeo-permalink-section">
					<h4 class="listeo-permalink-section-title">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e('Safe Mode', 'listeo-core'); ?>
					</h4>
					<div class="listeo-advanced-options">
						<label class="listeo-checkbox-label" for="listeo_permalink_safe_mode">
							<input type="checkbox" id="listeo_permalink_safe_mode" 
							       name="listeo_permalink_safe_mode" value="1" 
							       <?php checked(isset($settings['permalink_safe_mode']) && $settings['permalink_safe_mode'] === '1'); ?> />
							<span class="listeo-checkbox-text">
								<strong><?php esc_html_e('Enable Safe Mode', 'listeo-core'); ?></strong>
								<span class="listeo-checkbox-desc"><?php esc_html_e('Keep the /listing/ prefix in URLs to prevent conflicts with pages and posts. ', 'listeo-core'); ?></span>
							</span>
						</label>
						
						<div class="listeo-safe-mode-notice" style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
							<p style="margin: 0; font-size: 13px; color: #856404;">
								<span class="dashicons dashicons-info" style="color: #856404; margin-right: 5px;"></span>
								<?php esc_html_e('Safe Mode is highly recommended to prevent URL conflicts and 404 errors.', 'listeo-core'); ?>
							</p>
						</div>
					</div>
				</div>

				
			</div>
		</div>

		<script>
		// Pass data to JavaScript
		window.listeoCustomPermalinks = {
			availableTokens: <?php echo wp_json_encode($available_tokens); ?>,
			predefinedStructures: <?php echo wp_json_encode($predefined_structures); ?>,
			homeUrl: <?php echo wp_json_encode(home_url()); ?>,
			nonce: <?php echo wp_json_encode(wp_create_nonce('listeo_custom_permalinks')); ?>
		};
		
		</script>
		<?php
	}

	/**
	 * Get custom permalink settings
	 */
	private function get_custom_permalink_settings() {
		$raw_settings = Listeo_Core_Post_Types::get_raw_permalink_settings();
		
		$defaults = array(
			'custom_permalinks_enabled' => '0',
			'custom_structure' => '%listing_category%/%listing%',
			'enable_redirects' => '0',
			'permalink_safe_mode' => '0', // Default OFF for existing sites
		);

		$settings = wp_parse_args($raw_settings, $defaults);
		
		return $settings;
	}
	

	/**
	 * Handle custom permalink saving when the permalink page loads
	 */
	public function maybe_save_custom_permalinks() {
		// Only process POST requests with permalink data
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['permalink_structure'])) {
			return;
		}

		// Only process if our custom permalink form was submitted (using hidden field as trigger)
		if (!isset($_POST['listeo_custom_permalinks_save'])) {
			return;
		}

		// Verify nonce
		if (!wp_verify_nonce($_POST['_wpnonce'], 'update-permalink')) {
			return;
		}


		// Get current settings  
		$permalink_settings = Listeo_Core_Post_Types::get_raw_permalink_settings();

		// Handle existing Listeo fields exactly like before
		if (isset($_POST['listeo_listing_base_slug'])) {
			$permalink_settings['listing_base'] = sanitize_title_with_dashes($_POST['listeo_listing_base_slug']);
		}
		if (isset($_POST['listeo_listing_category_slug'])) {
			$permalink_settings['category_base'] = sanitize_title_with_dashes($_POST['listeo_listing_category_slug']);
		}
		if (isset($_POST['listeo_listings_archive_slug'])) {
			$permalink_settings['listings_archive'] = sanitize_title_with_dashes($_POST['listeo_listings_archive_slug']);
		}

		// Handle custom permalink settings - THE KEY FIX
		// Checkbox is only sent when checked, so we check for its existence
		$permalink_settings['custom_permalinks_enabled'] = isset($_POST['listeo_custom_permalinks_enabled']) ? '1' : '0';
		
		// Handle Safe Mode setting - only save if custom permalinks are enabled
		if ($permalink_settings['custom_permalinks_enabled'] === '1') {
			$old_safe_mode = isset($permalink_settings['permalink_safe_mode']) ? $permalink_settings['permalink_safe_mode'] : '0';
			$permalink_settings['permalink_safe_mode'] = isset($_POST['listeo_permalink_safe_mode']) ? '1' : '0';
			$new_safe_mode = $permalink_settings['permalink_safe_mode'];
			
			// Check if Safe Mode status changed for redirect generation
			if ($old_safe_mode !== $new_safe_mode) {
				// Store the change for later processing
				set_transient('listeo_safe_mode_changed', array(
					'old' => $old_safe_mode,
					'new' => $new_safe_mode
				), 300); // 5 minutes
			}
		} else {
			// If custom permalinks are disabled, also disable Safe Mode
			$permalink_settings['permalink_safe_mode'] = '0';
		}
		
		// Handle permalink mode
		if (isset($_POST['listeo_permalink_mode'])) {
			$permalink_settings['permalink_mode'] = sanitize_text_field($_POST['listeo_permalink_mode']);
		}
		
		if (isset($_POST['listeo_custom_structure'])) {
			$custom_structure = sanitize_text_field($_POST['listeo_custom_structure']);
			
			// Handle "default" option - this disables custom permalinks
			if ($custom_structure === 'default' || empty($custom_structure)) {
				$permalink_settings['custom_permalinks_enabled'] = '0';
				$permalink_settings['custom_structure'] = '';
				$permalink_settings['permalink_mode'] = 'default';
			} else {
				$permalink_settings['custom_structure'] = $custom_structure;
				$permalink_settings['permalink_mode'] = 'custom';
			}
		}
		
		$permalink_settings['enable_redirects'] = isset($_POST['listeo_enable_redirects']) ? '1' : '0';


		// Save settings
		update_option('listeo_core_permalinks', wp_json_encode($permalink_settings));


		// Force immediate cleanup of any bad redirects
		if (class_exists('Listeo_Core_Permalink_Redirect_Manager')) {
			$redirect_manager = new Listeo_Core_Permalink_Redirect_Manager();
			$cleanup_result = $redirect_manager->emergency_cleanup_malformed_redirects();
			
		}

		// Flush rewrite rules immediately when custom permalinks settings change
		if (class_exists('Listeo_Core_Custom_Permalink_Manager')) {
			// Force immediate rewrite rules flush
			flush_rewrite_rules(false);
			
		}
	}
}

Listeo_Core_Permalink_Settings::instance();
