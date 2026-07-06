<?php

/**
 * ListeoMaps class with file-based caching and improved map loading
 */
class ListeoMaps
{
	protected $plugin_slug = 'listeo-map';
	protected $cache_dir = 'listeo-cache'; // Directory within wp-content/uploads
	protected $cache_file = 'markers-data.json';
	protected $cache_meta_file = 'markers-meta.json';
	protected $cache_expiration = 86400; // 24 hours in seconds

	function __construct()
	{
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('wp_print_scripts', array($this, 'listeo_map_dequeue_script'), 100);

		// Check if cache directory exists and create it if needed
		$this->check_cache_directory();

		// Clear cache when a listing is created, updated or deleted
		add_action('save_post_listing', array($this, 'clear_markers_cache'));
		add_action('deleted_post', array($this, 'check_deleted_listing'));
		add_action('trashed_post', array($this, 'check_deleted_listing'));

		// Add admin menu item for cache management
		add_action('admin_menu', array($this, 'add_admin_menu'));
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 */
	public function enqueue_scripts()
	{
		// Enqueue the leaflet library and geocoder if they exist
		if (!wp_script_is('listeo_core-leaflet-geocoder', 'registered')) {
			// If the geocoder isn't registered, check if we need to register leaflet first
			if (!wp_script_is('listeo_core-leaflet', 'registered')) {
				wp_register_script('listeo_core-leaflet', get_template_directory_uri() . '/js/leaflet.min.js', array('jquery'), '1.0', false);
			}
			// Register the geocoder
			wp_register_script('listeo_core-leaflet-geocoder', get_template_directory_uri() . '/js/leaflet-geocoder.min.js', array('listeo_core-leaflet'), '1.0', false);
		}

		// Always enqueue leaflet library
		wp_enqueue_script('listeo_core-leaflet');
		wp_enqueue_script('listeo_core-leaflet-geocoder');

		// Register our map script but don't enqueue it yet - we'll do that in show_map
		wp_register_script($this->plugin_slug . '-script', get_template_directory_uri() . '/js/listeo.big.leaflet.min.js', array('jquery', 'listeo-custom', 'listeo_core-leaflet'), '1.0', false);
	}

	/**
	 * Add admin menu option for cache management
	 */
	public function add_admin_menu()
	{
		add_submenu_page(
			'options-general.php',
			'Listeo Maps Cache',
			'Listeo Maps Cache',
			'manage_options',
			'listeo-maps-cache',
			array($this, 'render_admin_page')
		);

		// Handle cache clear action
		if (isset($_GET['clear_map_cache']) && $_GET['clear_map_cache'] == 1 && current_user_can('manage_options')) {
			$this->clear_markers_cache();
			wp_redirect(admin_url('options-general.php?page=listeo-maps-cache&cleared=1'));
			exit;
		}

		// Handle cache rebuild action
		if (isset($_GET['rebuild_map_cache']) && $_GET['rebuild_map_cache'] == 1 && current_user_can('manage_options')) {
			$this->generate_markers_cache();
			wp_redirect(admin_url('options-general.php?page=listeo-maps-cache&rebuilt=1'));
			exit;
		}
	}

	/**
	 * Render admin page for cache management
	 */
	public function render_admin_page()
	{
		$cache_dir = $this->get_cache_dir();
		$cache_meta_file = $cache_dir . '/' . $this->cache_meta_file;
		$cache_file = $cache_dir . '/' . $this->cache_file;

		echo '<div class="wrap">';
		echo '<h1>Listeo Maps Cache</h1>';

		if (isset($_GET['cleared']) && $_GET['cleared'] == 1) {
			echo '<div class="notice notice-success"><p>Map cache has been successfully cleared.</p></div>';
		}

		if (isset($_GET['rebuilt']) && $_GET['rebuilt'] == 1) {
			echo '<div class="notice notice-success"><p>Map cache has been successfully rebuilt.</p></div>';
		}

		echo '<div class="card">';
		echo '<h2>Cache Status</h2>';

		if (file_exists($cache_meta_file)) {
			$meta = json_decode(file_get_contents($cache_meta_file), true);

			if (isset($meta['expires']) && $meta['expires'] > time()) {
				echo '<p>Cache status: <span style="color:green;font-weight:bold;">Active</span></p>';
				echo '<p>Total markers cached: ' . number_format($meta['count']) . '</p>';
				echo '<p>Cache created: ' . date('F j, Y, g:i a', $meta['generated']) . '</p>';
				echo '<p>Cache expires: ' . date('F j, Y, g:i a', $meta['expires']) . '</p>';

				if (file_exists($cache_file)) {
					$filesize = filesize($cache_file);
					echo '<p>Cache file size: ' . $this->format_filesize($filesize) . '</p>';
				}
			} else {
				echo '<p>Cache status: <span style="color:orange;font-weight:bold;">Expired</span></p>';
				echo '<p>Cache will be regenerated on next map view.</p>';
			}
		} else {
			echo '<p>Cache status: <span style="color:orange;font-weight:bold;">Not generated yet</span></p>';
			echo '<p>Cache will be generated on next map view.</p>';
		}

		echo '<div class="actions" style="margin-top: 20px;">';
		echo '<a href="' . admin_url('options-general.php?page=listeo-maps-cache&rebuild_map_cache=1') . '" class="button button-primary" style="margin-right: 10px;">Rebuild Cache Now</a>';
		echo '<a href="' . admin_url('options-general.php?page=listeo-maps-cache&clear_map_cache=1') . '" class="button">Clear Cache</a>';
		echo '</div>';

		echo '</div>'; // Close card

		// Advanced settings
		echo '<div class="card" style="margin-top: 20px;">';
		echo '<h2>Cache Settings</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields('listeo_maps_cache_settings');

		$cache_expiration = get_option('listeo_maps_cache_expiration', $this->cache_expiration);

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row">Cache Duration (seconds)</th>';
		echo '<td><input type="number" name="listeo_maps_cache_expiration" value="' . esc_attr($cache_expiration) . '" min="3600" step="3600" />';
		echo '<p class="description">How long should the cache be valid (in seconds)? Default: 86400 (24 hours)</p></td>';
		echo '</tr>';
		echo '</table>';

		submit_button('Save Settings');
		echo '</form>';
		echo '</div>'; // Close card

		// Troubleshooting section
		echo '<div class="card" style="margin-top: 20px;">';
		echo '<h2>Troubleshooting</h2>';
		echo '<p>If your map is not displaying correctly:</p>';
		echo '<ol>';
		echo '<li>Check your browser\'s console for JavaScript errors</li>';
		echo '<li>Make sure your theme has the required Leaflet libraries</li>';
		echo '<li>Try clearing and rebuilding the cache</li>';
		echo '<li>Verify the map container exists with ID "bigmap" in your template</li>';
		echo '</ol>';
		echo '</div>'; // Close card

		echo '</div>'; // Close wrap
	}

	/**
	 * Format filesize into human readable format
	 */
	private function format_filesize($bytes)
	{
		if ($bytes >= 1048576) {
			return number_format($bytes / 1048576, 2) . ' MB';
		} elseif ($bytes >= 1024) {
			return number_format($bytes / 1024, 2) . ' KB';
		} else {
			return $bytes . ' bytes';
		}
	}

	/**
	 * Register settings for the admin page
	 */
	public function register_settings()
	{
		register_setting('listeo_maps_cache_settings', 'listeo_maps_cache_expiration', array(
			'type' => 'integer',
			'sanitize_callback' => array($this, 'sanitize_cache_expiration'),
			'default' => 86400,
		));
	}

	/**
	 * Sanitize cache expiration setting
	 */
	public function sanitize_cache_expiration($value)
	{
		$value = intval($value);
		return ($value < 3600) ? 3600 : $value; // Minimum 1 hour
	}

	/**
	 * Check if cache directory exists and create it if needed
	 */
	private function check_cache_directory()
	{
		$cache_dir = $this->get_cache_dir();

		// Create directory if it doesn't exist
		if (!file_exists($cache_dir)) {
			wp_mkdir_p($cache_dir);

			// Create .htaccess to protect the cache directory
			$htaccess_file = $cache_dir . '/.htaccess';
			if (!file_exists($htaccess_file)) {
				$htaccess_content = "# Deny direct access to files\n";
				$htaccess_content .= "<FilesMatch \"\.json$\">\n";
				$htaccess_content .= "Order deny,allow\n";
				$htaccess_content .= "Deny from all\n";
				$htaccess_content .= "</FilesMatch>\n";
				file_put_contents($htaccess_file, $htaccess_content);
			}

			// Create empty index.php to prevent directory listing
			$index_file = $cache_dir . '/index.php';
			if (!file_exists($index_file)) {
				file_put_contents($index_file, "<?php\n// Silence is golden.\n");
			}
		}
	}

	/**
	 * Get cache directory path
	 */
	private function get_cache_dir()
	{
		$upload_dir = wp_upload_dir();
		return trailingslashit($upload_dir['basedir']) . $this->cache_dir;
	}

	/**
	 * Clear the markers cache files
	 */
	public function clear_markers_cache()
	{
		$cache_dir = $this->get_cache_dir();
		$cache_file = $cache_dir . '/' . $this->cache_file;
		$cache_meta_file = $cache_dir . '/' . $this->cache_meta_file;

		// Delete cache files if they exist
		if (file_exists($cache_file)) {
			@unlink($cache_file);
		}

		if (file_exists($cache_meta_file)) {
			@unlink($cache_meta_file);
		}
	}

	/**
	 * Check if the deleted post is a listing and clear cache if it is
	 */
	public function check_deleted_listing($post_id)
	{
		if (get_post_type($post_id) === 'listing') {
			$this->clear_markers_cache();
		}
	}

	/**
	 * Check if cache is valid
	 */
	private function is_cache_valid()
	{
		$cache_dir = $this->get_cache_dir();
		$cache_meta_file = $cache_dir . '/' . $this->cache_meta_file;
		$cache_file = $cache_dir . '/' . $this->cache_file;

		if (file_exists($cache_meta_file) && file_exists($cache_file)) {
			$meta = json_decode(file_get_contents($cache_meta_file), true);
			if (isset($meta['expires']) && $meta['expires'] > time()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate and save markers data to cache file with full content
	 */
	private function generate_markers_cache()
	{
		$cache_dir = $this->get_cache_dir();
		$cache_file = $cache_dir . '/' . $this->cache_file;
		$cache_meta_file = $cache_dir . '/' . $this->cache_meta_file;

		// Get cache expiration from settings or use default
		$cache_expiration = get_option('listeo_maps_cache_expiration', $this->cache_expiration);

		$query_args = array(
			'post_type'       => 'listing',
			'post_status'     => 'publish',
			'posts_per_page'  => 300,
		);

		$wp_query = new WP_Query($query_args);
		$markers = array();

		if ($wp_query->have_posts()) :
			$i = 0;
			while ($wp_query->have_posts()) :
				$wp_query->the_post();

				$id = $wp_query->post->ID;
				$lat = get_post_meta($id, '_geolocation_lat', true);
				$lng = get_post_meta($id, '_geolocation_long', true);

				if (!empty($lat) && !empty($lng)) {
					// Get the marker icon
					$icon = $this->get_marker_icon($id);

					// Get the infobox content
					$ibcontent = $this->get_infobox_content($id);

					// Store the full marker data
					$marker = array(
						'id' => $id,
						'lat' => (float)$lat,
						'lng' => (float)$lng,
						'icon' => $icon,
						'ibcontent' => $ibcontent
					);

					$markers[] = $marker;
					$i++;
				}

			endwhile;
		endif;
		wp_reset_postdata();

		// Save the markers data to file
		file_put_contents($cache_file, json_encode($markers));

		// Save metadata (expiration, etc.)
		$meta = array(
			'count' => count($markers),
			'generated' => time(),
			'expires' => time() + $cache_expiration
		);
		file_put_contents($cache_meta_file, json_encode($meta));

		return $markers;
	}

	/**
	 * Get marker icon for a listing
	 */
	private function get_marker_icon($id)
	{
		$icon = '';
		$terms = get_the_terms($id, 'listing_category');

		if ($terms) {
			$term = array_pop($terms);
			$t_id = $term->term_id;
			// retrieve the existing value(s) for this meta field. This returns an array
			$icon = get_term_meta($t_id, 'icon', true);
			if ($icon) {
				$icon = '<i class="' . $icon . '"></i>';
			}

			if (isset($t_id)) {
				$_icon_svg = get_term_meta($t_id, '_icon_svg', true);
				if (!empty($_icon_svg)) {
					$_icon_svg_image = wp_get_attachment_image_src($_icon_svg, 'medium');
					if (!empty($_icon_svg_image)) {
						$icon = listeo_render_svg_icon($_icon_svg);
					}
				}
			}
		}

		if (empty($icon)) {
			$icon = get_post_meta($id, '_icon', true);
		}

		if (empty($icon)) {
			$icon = '<i class="sl sl-icon-location"></i>';
		}

		return $icon;
	}

	/**
	 * Get infobox content for a listing
	 */
	private function get_infobox_content($id)
	{
		ob_start();
?>
		<a href="<?php echo get_permalink($id); ?>" class="leaflet-listing-img-container">
			<div class="infoBox-close"><i class="fa fa-times"></i></div>
			<?php
			if (has_post_thumbnail($id)) {
				echo get_the_post_thumbnail($id, 'listeo-listing-grid');
			} else {
				$gallery = get_post_meta($id, '_gallery', true);
				if (!empty($gallery)) {
					$ids = array_keys($gallery);
					$image = wp_get_attachment_image_src($ids[0], 'listeo-listing-grid');
					echo '<img src="' . esc_url($image[0]) . '">';
				} else {
					echo '<img src="' . get_listeo_core_placeholder_image() . '" >';
				}
			}
			?>
			<div class="leaflet-listing-item-content">
				<h3><?php echo get_the_title($id); ?></h3>
				<span>
					<?php
					$friendly_address = get_post_meta($id, '_friendly_address', true);
					$address = get_post_meta($id, '_address', true);
					echo (!empty($friendly_address)) ? $friendly_address : $address;
					?>
				</span>
			</div>
		</a>

		<?php
		// Use the new combined rating display function
		if (function_exists('listeo_get_rating_display')) {
			$rating_data = listeo_get_rating_display($id);
			$rating = $rating_data['rating'];
		}
		?>
		<div class="leaflet-listing-content">
			<div class="listing-title">
				<?php if (isset($rating) && $rating > 0) : ?>
					<div class="star-rating" data-rating="<?php echo esc_attr($rating); ?>">
						<?php $number = $rating_data['count'];
						if($number > 0) { ?>
						<div class="rating-counter">(<?php printf(_n('%s review', '%s reviews', $number, 'listeo'), number_format_i18n($number));  ?>)</div>
						<?php } ?>
					</div>
				<?php else : ?>
					<div class="star-rating">
						<div class="rating-counter"><span><?php esc_html_e('No reviews yet', 'listeo') ?></span></div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php
		return ob_get_clean();
	}

	/**
	 * Show the map with all markers
	 */
	public function show_map()
	{
		$height = '500';

		// Check if cache is valid, if not generate it
		if (!$this->is_cache_valid()) {
			$markers = $this->generate_markers_cache();
		} else {
			// Load markers from cache file
			$cache_dir = $this->get_cache_dir();
			$cache_file = $cache_dir . '/' . $this->cache_file;
			$markers = json_decode(file_get_contents($cache_file), true);
		}

		// Enqueue the map script here (was only registered before)
		wp_enqueue_script($this->plugin_slug . '-script');

		// Add an inline script to debug the loading process
		wp_add_inline_script(
			$this->plugin_slug . '-script',
			'console.log("Map initialization starting...");
            console.log("Markers count: " + (typeof listeo_big_map !== "undefined" ? Object.keys(listeo_big_map).length : "undefined"));
            jQuery(document).ready(function($) {
                console.log("Document ready for map initialization");
                console.log("Map container exists: " + ($("#bigmap").length > 0));
            });'
		);

		// Localize script with marker data
		wp_localize_script($this->plugin_slug . '-script', 'listeo_big_map', $markers);

		// Output map container
		ob_start();
	?>
		<div id="bigmap" data-map-zoom="<?php echo get_option('listeo_map_zoom_global', 9); ?>" style="height:<?php echo esc_attr($height); ?>px;"><!-- map goes here --></div>
<?php
		echo ob_get_clean(); // Directly output instead of returning
	}

	function listeo_map_dequeue_script()
	{
		if (is_page_template('template-home-search-map.php')) {
			wp_dequeue_script('listeo_core-leaflet');
		}
	}
}

// Initialize the class
new ListeoMaps();
?>