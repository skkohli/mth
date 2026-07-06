<?php
/**
 * Template loader for PW Sample Plugin.
 *
 * Only need to specify class listings here.
 *
 */
class Listeo_Core_Template_Loader extends Gamajo_Template_Loader {
 
	/**
	 * Prefix for filter names.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $filter_prefix = 'listeo_core';
 
	/**
	 * Directory name where custom templates for this plugin should be found in the theme.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $theme_template_directory = 'listeo-core';
 
	/**
	 * Reference to the root directory path of this plugin.
	 *
	 * @since 1.0.0
	 * @type string
	 */
	protected $plugin_directory = LISTEO_PLUGIN_DIR;

	/**
	   * Directory name where templates are found in this plugin.
	   *
	   * Can either be a defined constant, or a relative reference from where the subclass lives.
	   *
	   * e.g. 'templates' or 'includes/templates', etc.
	   *
	   * @since 1.1.0
	   *
	   * @var string
	   */
	  protected $plugin_template_directory = 'templates';

	/**
	 * Prepend listing-type-specific template names before generic ones.
	 *
	 * For a rental listing in grid view, the resolution order becomes:
	 * content-listing-grid-rental.php → content-listing-rental.php → content-listing-grid.php → content-listing.php
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Template slug.
	 * @param string $name Template variation name.
	 *
	 * @return array
	 */
	protected function get_template_file_names( $slug, $name ) {
		$templates = parent::get_template_file_names( $slug, $name );

		// Only apply to content-listing templates.
		if ( strpos( $slug, 'content-listing' ) !== 0 ) {
			return $templates;
		}

		global $post;
		if ( ! $post || 'listing' !== get_post_type( $post ) ) {
			return $templates;
		}

		$listing_type = get_post_meta( $post->ID, '_listing_type', true );
		if ( empty( $listing_type ) ) {
			return $templates;
		}

		$listing_type = sanitize_file_name( $listing_type );

		// For each generic template, prepend its type-specific variant directly before it.
		// e.g. [content-listing-grid.php, content-listing.php] becomes:
		// [content-listing-grid-rental.php, content-listing-grid.php, content-listing-rental.php, content-listing.php]
		$result = array();
		foreach ( $templates as $template ) {
			$result[] = str_replace( '.php', '-' . $listing_type . '.php', $template );
			$result[] = $template;
		}

		return $result;
	}
}


?>