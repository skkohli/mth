<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Listeo_Core_Settings {

	/**
	 * The single instance of Listeo_Core_Settings.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The main plugin object.
	 * @var 	object
	 * @access  public
	 * @since 	1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin settings.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Available settings for plugin.
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = array();

	public function __construct ( $parent ) {
		$this->parent = $parent;
		/*prefix for all settings*/
		$this->base = 'listeo_core_';

		// Initialise settings
		add_action( 'init', array( $this, 'init_settings' ), 11 );

		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->parent->file ) , array( $this, 'add_settings_link' ) );
	}

	/**
	 * Initialise settings
	 * @return void
	 */
	public function init_settings () {
		$this->settings = $this->settings_fields();
	}

	/**
	 * Add settings page to admin menu
	 * @return void
	 */
	public function add_menu_item () {
		$page = add_menu_page( __( 'Listeo Core Settings', 'listeo_core' ) , __( 'Listeo Core Settings', 'listeo_core' ) , 'manage_options' , 'listeo_core_settings' ,  array( $this, 'settings_page' ) );
				add_submenu_page( 'listeo_core_settings', __( 'Listeo Core Settings', 'listeo_core' ), __( 'Listeo Core Settings', 'listeo_core' ),	'manage_options', 'listeo_core_settings');
		add_action( 'admin_print_styles-' . $page, array( $this, 'settings_assets' ) );
	}

	/**
	 * Load settings JS & CSS
	 * @return void
	 */
	public function settings_assets () {

		// We're including the farbtastic script & styles here because they're needed for the colour picker
		// If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below
		wp_enqueue_style( 'farbtastic' );
    	wp_enqueue_script( 'farbtastic' );

    	// We're including the WP media scripts here because they're needed for the image upload field
    	// If you're not including an image upload then you can leave this function call out
    	wp_enqueue_media();

    	wp_register_script( $this->parent->_token . '-settings-js', $this->parent->assets_url . 'js/settings' . $this->parent->script_suffix . '.js', array( 'farbtastic', 'jquery' ), '1.0.0' );
    	wp_enqueue_script( $this->parent->_token . '-settings-js' );
	}

	/**
	 * Add settings link to plugin list table
	 * @param  array $links Existing links
	 * @return array 		Modified links
	 */
	public function add_settings_link ( $links ) {
		$settings_link = '<a href="options-general.php?page=' . $this->parent->_token . '_settings">' . __( 'Settings', 'listeo_core' ) . '</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	/**
	 * Build settings fields
	 * @return array Fields to be displayed on settings page
	 */
	private function settings_fields () {

		$settings['listing'] = array(
			'title'					=> __( 'Listing Options', 'listeo_core' ),
			'description'			=> __( 'Single listing related options', 'listeo_core' ),
			'fields'				=> array(
				array(
					'id' 			=> 'currency',
					'label'			=> __( 'Currency', 'listeo_core' ),
					'description'	=> __( 'Choose a currency.', 'listeo_core' ),
					'type'			=> 'select',
					'options'		=> array(
							'none' => esc_html__( 'Disable Currency Symbol', 'listeo_core' ),
							'USD' => esc_html__( 'US Dollars', 'listeo_core' ),
							'AED' => esc_html__( 'United Arab Emirates Dirham', 'listeo_core' ),
							'ARS' => esc_html__( 'Argentine Peso', 'listeo_core' ),
							'AUD' => esc_html__( 'Australian Dollars', 'listeo_core' ),
							'BDT' => esc_html__( 'Bangladeshi Taka', 'listeo_core' ),
							'BHD' => esc_html__( 'Bahraini Dinar', 'listeo_core' ),
							'BRL' => esc_html__( 'Brazilian Real', 'listeo_core' ),
							'BGN' => esc_html__( 'Bulgarian Lev', 'listeo_core' ),
							'CAD' => esc_html__( 'Canadian Dollars', 'listeo_core' ),
							'CLP' => esc_html__( 'Chilean Peso', 'listeo_core' ),
							'CNY' => esc_html__( 'Chinese Yuan', 'listeo_core' ),
							'COP' => esc_html__( 'Colombian Peso', 'listeo_core' ),
							'CZK' => esc_html__( 'Czech Koruna', 'listeo_core' ),
							'DKK' => esc_html__( 'Danish Krone', 'listeo_core' ),
							'DOP' => esc_html__( 'Dominican Peso', 'listeo_core' ),
							'EUR' => esc_html__( 'Euros', 'listeo_core' ),
							'HKD' => esc_html__( 'Hong Kong Dollar', 'listeo_core' ),
							'HRK' => esc_html__( 'Croatia kuna', 'listeo_core' ),
							'HUF' => esc_html__( 'Hungarian Forint', 'listeo_core' ),
							'ISK' => esc_html__( 'Icelandic krona', 'listeo_core' ),
							'IDR' => esc_html__( 'Indonesia Rupiah', 'listeo_core' ),
							'INR' => esc_html__( 'Indian Rupee', 'listeo_core' ),
							'NPR' => esc_html__( 'Nepali Rupee', 'listeo_core' ),
							'ILS' => esc_html__( 'Israeli Shekel', 'listeo_core' ),
							'JPY' => esc_html__( 'Japanese Yen', 'listeo_core' ),
							'KIP' => esc_html__( 'Lao Kip', 'listeo_core' ),
							'KRW' => esc_html__( 'South Korean Won', 'listeo_core' ),
							'LKR' => esc_html__( 'Sri Lankan Rupee', 'listeo_core' ),
							'MYR' => esc_html__( 'Malaysian Ringgits', 'listeo_core' ),
							'MXN' => esc_html__( 'Mexican Peso', 'listeo_core' ),
							'NGN' => esc_html__( 'Nigerian Naira', 'listeo_core' ),
							'NOK' => esc_html__( 'Norwegian Krone', 'listeo_core' ),
							'NZD' => esc_html__( 'New Zealand Dollar', 'listeo_core' ),
							'PYG' => esc_html__( 'Paraguayan Guaraní', 'listeo_core' ),
							'PHP' => esc_html__( 'Philippine Pesos', 'listeo_core' ),
							'PLN' => esc_html__( 'Polish Zloty', 'listeo_core' ),
							'GBP' => esc_html__( 'Pounds Sterling', 'listeo_core' ),
							'RON' => esc_html__( 'Romanian Leu', 'listeo_core' ),
							'RUB' => esc_html__( 'Russian Ruble', 'listeo_core' ),
							'SGD' => esc_html__( 'Singapore Dollar', 'listeo_core' ),
							'ZAR' => esc_html__( 'South African rand', 'listeo_core' ),
							'SEK' => esc_html__( 'Swedish Krona', 'listeo_core' ),
							'CHF' => esc_html__( 'Swiss Franc', 'listeo_core' ),
							'TWD' => esc_html__( 'Taiwan New Dollars', 'listeo_core' ),
							'THB' => esc_html__( 'Thai Baht', 'listeo_core' ),
							'TRY' => esc_html__( 'Turkish Lira', 'listeo_core' ),
							'UAH' => esc_html__( 'Ukrainian Hryvnia', 'listeo_core' ),
							'USD' => esc_html__( 'US Dollars', 'listeo_core' ),
							'VND' => esc_html__( 'Vietnamese Dong', 'listeo_core' ),
							'EGP' => esc_html__( 'Egyptian Pound', 'listeo_core' ),
							'ZMK' => esc_html__( 'Zambian Kwacha', 'listeo_core' )
						),
					'default'		=> 'USD'
				),
				array(
					'id' 			=> 'currency_postion',
					'label'			=> __( 'Currency symbol postion', 'listeo_core' ),
					'description'	=> __( 'After or before the price.', 'listeo_core' ),
					'type'			=> 'radio',
					'options'		=> array( 'after' => 'After', 'before' => 'Before' ),
					'default'		=> 'after'
				),
				array(
					'id' 			=> 'maps_api',
					'label'			=> __( 'Google Maps API key' , 'listeo_core' ),
					'description'	=> __( 'Generate API key for google maps functionality.', 'listeo_core' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( 'API key', 'listeo_core' )
				),
				array(
					'id' 			=> 'google_maps_id',
					'label'			=> __( 'Google Map ID' , 'listeo_core' ),
					'description'	=> __( 'Optional: Enter your Google Cloud Styled Map ID for custom map styling. Create one in Google Cloud Console > Maps > Map Styles.', 'listeo_core' ),
					'type'			=> 'text',
					'default'		=> '',
					'placeholder'	=> __( 'Map ID (e.g., a1b2c3d4e5f6g7h8)', 'listeo_core' )
				),
				array(
					'id' 			=> 'scale',
					'label'			=> __( 'Scale', 'listeo_core' ),
					'description'	=> __( 'Choose a scale.', 'listeo_core' ),
					'type'			=> 'select',
					'options'		=> array(
							'sq m' => esc_html__( 'Square meter', 'listeo_core' ),
							'sq ft' => esc_html__( 'Square feet', 'listeo_core' ),
						),
					'default'		=> 'sq ft'
				),

			)
		);
/*
		$settings['extra'] = array(
			'title'					=> __( 'Extra', 'listeo_core' ),
			'description'			=> __( 'These are some extra input fields that maybe aren\'t as common as the others.', 'listeo_core' ),
			'fields'				=> array(
				array(
					'id' 			=> 'number_field',
					'label'			=> __( 'A Number' , 'listeo_core' ),
					'description'	=> __( 'This is a standard number field - if this field contains anything other than numbers then the form will not be submitted.', 'listeo_core' ),
					'type'			=> 'number',
					'default'		=> '',
					'placeholder'	=> __( '42', 'listeo_core' )
				),
				array(
					'id' 			=> 'colour_picker',
					'label'			=> __( 'Pick a colour', 'listeo_core' ),
					'description'	=> __( 'This uses WordPress\' built-in colour picker - the option is stored as the colour\'s hex code.', 'listeo_core' ),
					'type'			=> 'color',
					'default'		=> '#21759B'
				),
				array(
					'id' 			=> 'an_image',
					'label'			=> __( 'An Image' , 'listeo_core' ),
					'description'	=> __( 'This will upload an image to your media library and store the attachment ID in the option field. Once you have uploaded an imge the thumbnail will display above these buttons.', 'listeo_core' ),
					'type'			=> 'image',
					'default'		=> '',
					'placeholder'	=> ''
				),
				array(
					'id' 			=> 'multi_select_box',
					'label'			=> __( 'A Multi-Select Box', 'listeo_core' ),
					'description'	=> __( 'A standard multi-select box - the saved data is stored as an array.', 'listeo_core' ),
					'type'			=> 'select_multi',
					'options'		=> array( 'linux' => 'Linux', 'mac' => 'Mac', 'windows' => 'Windows' ),
					'default'		=> array( 'linux' )
				)
			)
		);
*/
		$settings = apply_filters( $this->parent->_token . '_settings_fields', $settings );

		return $settings;
	}

	/**
	 * Register plugin settings
	 * @return void
	 */
	public function register_settings () {
		if ( is_array( $this->settings ) ) {

			// Check posted/selected tab
			$current_section = '';
			if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
				$current_section = $_POST['tab'];
			} else {
				if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
					$current_section = $_GET['tab'];
				}
			}

			foreach ( $this->settings as $section => $data ) {

				if ( $current_section && $current_section != $section ) continue;

				// Add section to page
				add_settings_section( $section, $data['title'], array( $this, 'settings_section' ), $this->parent->_token . '_settings' );

				foreach ( $data['fields'] as $field ) {

					// Validation callback for field
					$validation = '';
					if ( isset( $field['callback'] ) ) {
						$validation = $field['callback'];
					}

					// Register field
					$option_name = $this->base . $field['id'];
					register_setting( $this->parent->_token . '_settings', $option_name, $validation );

					// Add field to page
					add_settings_field( $field['id'], $field['label'], array( $this->parent->admin, 'display_field' ), $this->parent->_token . '_settings', $section, array( 'field' => $field, 'prefix' => $this->base ) );
				}

				if ( ! $current_section ) break;
			}
		}
	}

	public function settings_section ( $section ) {
		$html = '<p> ' . $this->settings[ $section['id'] ]['description'] . '</p>' . "\n";
		echo $html;
	}

	/**
	 * Load settings page content
	 * @return void
	 */
	public function settings_page () {

		// Build page HTML
		$html = '<div class="wrap" id="' . $this->parent->_token . '_settings">' . "\n";
			$html .= '<h2>' . __( 'Plugin Settings' , 'listeo_core' ) . '</h2>' . "\n";

			$tab = '';
			if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
				$tab .= $_GET['tab'];
			}

			// Show page tabs
			if ( is_array( $this->settings ) && 1 < count( $this->settings ) ) {

				$html .= '<h2 class="nav-tab-wrapper">' . "\n";

				$c = 0;
				foreach ( $this->settings as $section => $data ) {

					// Set tab class
					$class = 'nav-tab';
					if ( ! isset( $_GET['tab'] ) ) {
						if ( 0 == $c ) {
							$class .= ' nav-tab-active';
						}
					} else {
						if ( isset( $_GET['tab'] ) && $section == $_GET['tab'] ) {
							$class .= ' nav-tab-active';
						}
					}

					// Set tab link
					$tab_link = add_query_arg( array( 'tab' => $section ) );
					if ( isset( $_GET['settings-updated'] ) ) {
						$tab_link = remove_query_arg( 'settings-updated', $tab_link );
					}

					// Output tab
					$html .= '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $data['title'] ) . '</a>' . "\n";

					++$c;
				}

				$html .= '</h2>' . "\n";
			}

			$html .= '<form method="post" action="options.php" enctype="multipart/form-data">' . "\n";

				// Get settings fields
				ob_start();
				settings_fields( $this->parent->_token . '_settings' );
				do_settings_sections( $this->parent->_token . '_settings' );
				$html .= ob_get_clean();

				$html .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings' , 'listeo_core' ) ) . '" />' . "\n";
				$html .= '</p>' . "\n";
			$html .= '</form>' . "\n";
		$html .= '</div>' . "\n";

		echo $html;
	}

	/**
	 * Main Listeo_Core_Settings Instance
	 *
	 * Ensures only one instance of Listeo_Core_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Listeo_Core()
	 * @return Main Listeo_Core_Settings instance
	 */
	public static function instance ( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'listeo_core' ), $this->parent->_version );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'listeo_core' ), $this->parent->_version );
	} // End __wakeup()

}