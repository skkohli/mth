<?php
/**
 * Listeo Core Regions Importer
 *
 * Integrated regions importer functionality for Listeo Core
 * Safely replaces the standalone regions-importer plugin
 *
 * @package Listeo Core
 * @since 1.9.45
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Ensure is_plugin_active function is available
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Safety check: Don't load if standalone regions importer plugin is active
if (class_exists('Dynamic_Regions_Importer')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>Listeo Core:</strong> <?php _e('Conflict detected! Please deactivate the standalone "Regions Importer" plugin as this functionality is now integrated into Listeo Core.', 'listeo_core'); ?></p>
        </div>
        <?php
    });
    return; // Stop loading this file
}

/**
 * Listeo Core Regions Importer Class
 */
class Listeo_Core_Regions_Importer
{
    // The URL to your secure proxy server script.
    const PROXY_API_URL = 'https://purethemes.net/import-regions';

    private $notice = '';
    private $notice_type = 'success';

    /**
     * The single instance of the class.
     *
     * @var self
     * @since  1.9.45
     */
    private static $_instance = null;

    /**
     * Allows for accessing single instance of class. Class should only be constructed once per call.
     *
     * @since  1.9.45
     * @static
     * @return self Main instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor - only initialize if we're in admin and no conflicts
     */
    public function __construct()
    {
        // Only load in admin
        if (!is_admin()) {
            return;
        }

        // Double-check for conflicts - both class existence and plugin activation
        if (class_exists('Dynamic_Regions_Importer') || 
            (function_exists('is_plugin_active') && is_plugin_active('regions-importer/regions-import.php'))) {
            add_action('admin_notices', array($this, 'show_conflict_notice'));
            return;
        }

        add_action('admin_init', array($this, 'handle_import'));
        add_action('admin_notices', array($this, 'show_admin_notice'));
    }

    /**
     * Show conflict notice if standalone plugin is detected
     */
    public function show_conflict_notice()
    {
        ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Listeo Core - Conflict Detected!', 'listeo_core'); ?></strong></p>
            <p><?php _e('The standalone "Regions Importer" plugin is still active. Please deactivate it as this functionality is now built into Listeo Core.', 'listeo_core'); ?></p>
            <p><?php _e('Go to Plugins → Installed Plugins and deactivate "Regions Importer" to use the integrated version.', 'listeo_core'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the regions importer page
     */
    public function render_import_page()
    {
        // Final safety check
        if (class_exists('Dynamic_Regions_Importer') && !defined('LISTEO_REGIONS_IMPORTER_INTEGRATED')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Cannot load regions importer due to plugin conflict. Please deactivate the standalone Regions Importer plugin.', 'listeo_core') . '</p></div></div>';
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Import Country Regions', 'listeo_core'); ?></h1>
            <div class="dri-import-box">
                    <p><?php _e('Select a country and what you\'d like to import. The plugin will create the appropriate hierarchy for you.', 'listeo_core'); ?></p>
                    <form id="region-importer-form" method="post" action="">
                        <?php wp_nonce_field('listeo_import_regions_nonce', 'listeo_regions_nonce'); ?>
                        <input type="hidden" name="country_locale" id="country_locale" value="<?php echo esc_attr(get_locale()); ?>" />
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><label for="country_to_import"><?php _e('Select Country', 'listeo_core'); ?> <br> <span style="font-size: 13px; font-weight: 400; color: #888"><?php _e('Region names will use your site language.', 'listeo_core'); ?></span></label></th>
                                <td>
                                    <select id="country_to_import" name="country_to_import" style="min-width: 250px;">
                                        <option value=""><?php _e('— Select a country —', 'listeo_core'); ?></option>
                                        <?php
                                        $all_countries = array(
                                            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
                                            'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan',
                                            'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia',
                                            'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica',
                                            'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'East Timor',
                                            'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland',
                                            'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea',
                                            'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq',
                                            'Ireland', 'Israel', 'Italy', 'Ivory Coast', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati',
                                            'Kosovo', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein',
                                            'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania',
                                            'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar',
                                            'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia',
                                            'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines',
                                            'Poland', 'Portugal', 'Puerto Rico', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines',
                                            'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia',
                                            'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname',
                                            'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Tonga', 'Trinidad and Tobago',
                                            'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay',
                                            'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'
                                        );
                                        foreach ($all_countries as $country_name) :
                                        ?>
                                            <option value="<?php echo esc_attr($country_name); ?>"><?php echo esc_html($country_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <!-- Import Level Choice -->
                            <tr valign="top">
                                <th scope="row"><label><?php _e('Import Level', 'listeo_core'); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label><input type="radio" name="import_level" value="regions_and_cities" checked="checked"> <span><?php _e('Regions + 5 cities for each', 'listeo_core'); ?></span></label>
                                        <label><input type="radio" name="import_level" value="regions_only"> <span><?php _e('Regions Only', 'listeo_core'); ?></span></label>
                                    </fieldset>
                                </td>
                            </tr>
                            <!-- Clean Up Option -->
                            <tr valign="top">
                                <th scope="row"><label><?php _e('Clean Up', 'listeo_core'); ?></label></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="remove_existing_regions" id="remove_existing_regions" value="1"> 
                                            <span><?php _e('Remove existing regions before importing', 'listeo_core'); ?></span>
                                        </label>
                                        <div id="remove-warning" class="dri-remove-warning" style="display: none;">
                                            <strong><?php _e('Warning:', 'listeo_core'); ?></strong> <?php _e('This will permanently delete all existing regions and cities from your site before importing new ones. This action cannot be undone.', 'listeo_core'); ?>
                                        </div>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Import Regions', 'listeo_core'), 'primary', 'listeo_import_regions'); ?>
                    </form>
                    <div id="importer-loading" style="display: none; border: none; background:rgb(237, 244, 255); border-radius: 5px;">
                        <div style="display: flex;">
                            <span class="spinner is-active"></span>
                            <p><strong><?php _e('Importing...', 'listeo_core'); ?></strong> <?php _e('This may take a few moments. Please don\'t close this window.', 'listeo_core'); ?></p>
                        </div>
                        <div style="clear: both;"></div>
                    </div>
            </div>
        </div>

        <style>
            .dri-import-box { background: #fff; border: none; box-shadow: 0 2px 10px rgba(0, 0, 0, .08); padding: 20px 30px; margin-top: 15px; border-radius: 5px; max-width: 500px; }
            .dri-import-box p { font-size: 14px; }
            .dri-import-box .form-table th { padding-left: 0; }
            .dri-import-box .form-table td { padding-right: 0; }
            .dri-import-box fieldset { border: none; padding: 0; margin: 0; }
            .dri-import-box fieldset label { display: block; margin-bottom: 8px; }
            .dri-import-box fieldset input { margin-right: 5px; }
            .dri-remove-warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 8px 12px; border-radius: 4px; margin-top: 8px; font-size: 13px; }
            #importer-loading { display:none; margin-top: 15px; padding: 12px; border: 1px solid #c3c4c7; background-color: #fff; }
            #importer-loading .spinner { float: left; margin-right: 10px; }
            #importer-loading p { margin: 0; float: left; }
        </style>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.getElementById('region-importer-form');
                var select = document.getElementById('country_to_import');
                var submitButton = document.getElementById('submit');
                var loadingDiv = document.getElementById('importer-loading');
                var removeCheckbox = document.getElementById('remove_existing_regions');
                var removeWarning = document.getElementById('remove-warning');

                removeCheckbox.addEventListener('change', function() {
                    removeWarning.style.display = this.checked ? 'block' : 'none';
                });

                form.addEventListener('submit', function(e) {
                    if (!select.value) {
                        e.preventDefault();
                        alert('<?php echo esc_js(__('Please select a country.', 'listeo_core')); ?>');
                        return;
                    }
                    loadingDiv.style.display = 'block';
                    submitButton.value = '<?php _e('Importing...', 'listeo_core'); ?>';
                    submitButton.disabled = true;
                });
            });
        </script>
        <?php
    }

    /**
     * Handle form submission and import process
     */
    public function handle_import()
    {
        if (isset($_POST['listeo_import_regions']) && 
            isset($_POST['listeo_regions_nonce']) && 
            wp_verify_nonce($_POST['listeo_regions_nonce'], 'listeo_import_regions_nonce') && 
            current_user_can('manage_options')) {
            
            $selected_country_en = sanitize_text_field($_POST['country_to_import']);
            $selected_locale = sanitize_text_field($_POST['country_locale']);
            $import_level = isset($_POST['import_level']) ? sanitize_text_field($_POST['import_level']) : 'regions_and_cities';
            $remove_existing = isset($_POST['remove_existing_regions']) && $_POST['remove_existing_regions'] === '1';
            
            // Remove existing regions if requested
            if ($remove_existing) {
                $this->remove_existing_regions();
            }
            
            $regions_data = $this->fetch_regions_from_proxy($selected_country_en, $selected_locale, $import_level);

            if (is_wp_error($regions_data) || empty($regions_data)) {
                $this->notice = is_wp_error($regions_data) ? 'Error: ' . $regions_data->get_error_message() : __('Error: The import service returned empty data.', 'listeo_core');
                $this->notice_type = 'error';
                return;
            }
            
            // Unwrapping logic only applies if we're expecting cities.
            if ($import_level === 'regions_and_cities' && count($regions_data) === 1) {
                $first_key = key($regions_data);
                if (strcasecmp($first_key, $selected_country_en) == 0) {
                    $regions_data = reset($regions_data);
                }
            }

            $this->import_regions($regions_data);
            
            if ($this->notice_type !== 'error') {
                $cleanup_message = $remove_existing ? __(' (existing regions were removed first)', 'listeo_core') : '';
                $this->notice = __('Regions and cities have been successfully imported!', 'listeo_core') . $cleanup_message;
            }
        }
    }

    /**
     * Remove all existing regions and their children
     */
    private function remove_existing_regions()
    {
        $terms = get_terms(array(
            'taxonomy' => 'region',
            'hide_empty' => false,
            'fields' => 'ids'
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term($term_id, 'region');
            }
        }
    }

    /**
     * Import regions data into WordPress taxonomy
     */
    private function import_regions($data)
    {
        if (empty($data) || !is_array($data)) {
            $this->notice = __('Import failed: The data from the AI was not in the expected array format.', 'listeo_core');
            $this->notice_type = 'error';
            return;
        }

        foreach ($data as $key => $value) {
            // CASE 1: Data is a simple array of region names (from 'regions_only')
            if (is_int($key) && is_string($value)) {
                $clean_region_name = trim(str_replace('_', ' ', $value));
                if (!empty($clean_region_name)) {
                    wp_insert_term(sanitize_text_field($clean_region_name), 'region'); // No parent
                }
            }
            // CASE 2: Data is an associative array of regions and cities
            else if (is_string($key) && is_array($value)) {
                $clean_region_name = trim(str_replace('_', ' ', $key));
                if (empty($clean_region_name)) { continue; }

                $state_term = wp_insert_term(sanitize_text_field($clean_region_name), 'region');
                if (is_wp_error($state_term)) { continue; }
                
                $state_term_id = $state_term['term_id'];
                $cities = $value;

                foreach ($cities as $city_name) {
                    if (is_string($city_name)) {
                        $clean_city_name = trim($city_name);
                        if (!empty($clean_city_name)) {
                            wp_insert_term(sanitize_text_field($clean_city_name), 'region', ['parent' => $state_term_id]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Fetch regions data from proxy API
     */
    private function fetch_regions_from_proxy($country, $locale, $import_level)
    {
        $body = json_encode(['country' => $country, 'locale' => $locale, 'import_level' => $import_level]);
        $response = wp_remote_post(self::PROXY_API_URL, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => $body,
            'timeout' => 90
        ]);

        if (is_wp_error($response)) { 
            return new WP_Error('proxy_request_failed', __('Request to the import service failed.', 'listeo_core')); 
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_body = json_decode(wp_remote_retrieve_body($response), true);
            $message = $error_body['error'] ?? __('An unknown error occurred.', 'listeo_core');
            return new WP_Error('proxy_error', __('Import service error: ', 'listeo_core') . esc_html($message) . ' (Code: ' . esc_html($response_code) . ').');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) { 
            return new WP_Error('json_decode_error', __('Invalid data received from the import service.', 'listeo_core')); 
        }
        
        return $data;
    }

    /**
     * Show admin notices
     */
    public function show_admin_notice()
    {
        if (!empty($this->notice)) {
            ?>
            <div class="notice notice-<?php echo esc_attr($this->notice_type); ?> is-dismissible">
                <p><?php echo wp_kses_post($this->notice); ?></p>
            </div>
            <?php
        }
    }
}

// Define constant to indicate integrated version is loaded
if (!defined('LISTEO_REGIONS_IMPORTER_INTEGRATED')) {
    define('LISTEO_REGIONS_IMPORTER_INTEGRATED', true);
}
