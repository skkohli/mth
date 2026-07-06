<?php
class LDS_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_pages' ] );
        // Settings registration moved to class-lds-settings.php
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_map_scripts' ] );
    }

    public function add_plugin_pages() {
        add_menu_page(
            'Listeo Data Scraper',
            'Listeo Importer',
            'manage_options',
            'listeo-data-scraper',
            [ $this, 'create_import_page' ],
            'dashicons-cloud-upload',
            6
        );

        add_submenu_page(
            'listeo-data-scraper',
            'Import Listings',
            'Import',
            'manage_options',
            'listeo-data-scraper',
            [ $this, 'create_import_page' ]
        );

        add_submenu_page(
            'listeo-data-scraper',
            'Importer Settings',
            'Settings',
            'manage_options',
            'lds-settings',
            [ $this, 'create_settings_page' ]
        );

        global $submenu;
        $submenu['listeo-data-scraper'][] = [
            __('License', 'listeo-data-scraper'),
            'manage_options',
            esc_url(admin_url('admin.php?page=lds-settings#lds-license-section')),
            __('License', 'listeo-data-scraper'),
        ];
    }

    public function create_import_page() {
        // Get current settings
        $limit = (int) get_option('lds_import_limit', 20);
        $api_source = get_option('lds_api_source', 'google');

        // For Google Places: Check lds_enable_photo_import (TOS compliance)
        // For Outscraper: Always allow photos (no TOS restrictions)
        if ($api_source === 'google') {
            $photo_enabled = (bool) get_option('lds_enable_photo_import', 0);
            $photo_limit = $photo_enabled ? LDS_Pro_Manager::get_photo_import_limit() : 0;
        } else {
            // Outscraper - no TOS restrictions, always enabled
            $photo_enabled = true;
            $photo_limit = 1; // Outscraper provides 1 main photo by default
        }
        $lang_setting = get_option('lds_description_language', 'site-default');
        $ai_enabled = (bool) get_option('lds_enable_ai_descriptions', 1);
        // Removed default listing type setting dependency
    
        // Determine language display text
        if ($lang_setting === 'site-default') {
            if (class_exists('Locale')) {
                try {
                    $wp_lang_name = \Locale::getDisplayLanguage(get_locale(), 'en');
                    $language_display = "Site Default (" . esc_html($wp_lang_name) . ")";
                } catch (\Exception $e) {
                    // Fallback if Locale class throws an exception
                    $locale = get_locale();
                    $language_display = "Site Default (" . esc_html($locale) . ")";
                }
            } else {
                // Fallback when Intl extension is not available
                $locale = get_locale();
                $language_display = "Site Default (" . esc_html($locale) . ")";
            }
        } else {
            $language_display = esc_html($lang_setting);
        }

        // AI status display
        $ai_status = $ai_enabled ? 'Enabled' : 'Disabled (Fast Mode)';
        $ai_status_class = $ai_enabled ? 'enabled' : 'disabled';
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 10px;">Import Listings</h1>
            <p style="color: #7f8c8d; margin-bottom: 25px;">Enter a search query and location to import listings from Google Places.</p>
    
            <div class="lds-main-container">
                
                <!-- Import Form Card -->
                <div class="lds-import-card">
                    <h3 class="lds-card-header"><?php echo lds_get_inline_svg_icon('download'); ?>Import New Listings</h3>
                    <div class="lds-card-body">
                        <form id="lds-import-form">
                            <?php wp_nonce_field( 'lds_import_nonce', 'lds_nonce' ); ?>
                            <!-- Hidden field for API source (for JavaScript to access) -->
                            <input type="hidden" id="lds_api_source_value" value="<?php echo esc_attr(get_option('lds_api_source', 'google')); ?>" />

                            <!-- Search Examples -->
                            <div class="lds-examples-box">
                                <div class="lds-examples-title">Search Examples</div>
                                <ul class="lds-examples-list">
                                    <li><strong>Business Type:</strong> "Car Repair" → <strong>Location:</strong> "New York, NY"</li>
                                    <li><strong>Business Type:</strong> "Italian Restaurants" → <strong>Location:</strong> "10001"</li>
                                </ul>
                            </div>
                            
                            <div class="lds-form-group">
                                <label for="lds_query">Business Type / Service</label>
                                <input 
                                    type="text" 
                                    id="lds_query" 
                                    name="lds_query" 
                                    class="lds-form-input" 
                                    required 
                                    placeholder="e.g., Plumbers, Restaurants, Hair Salons" 
                                />
                                <div class="lds-form-description">What type of business are you looking for?</div>
                            </div>

                            <div class="lds-form-group">
                                <label for="lds_location">Location</label>
                                
                                <!-- Location Mode Toggle -->
                                <?php
                                $map_enabled = 1; // Always enabled - map mode is default
                                $is_map_locked = LDS_Pro_Manager::is_feature_locked('map_mode');
                                // Check for maps API key first, fallback to main API key
                                $maps_api_key = get_option('lds_google_maps_api_key');
                                $main_api_key = get_option('lds_google_api_key');
                                $api_key = !empty($maps_api_key) ? $maps_api_key : $main_api_key;
                                if ($map_enabled && !empty($api_key)): ?>
                                    <!-- Show Map Mode Toggle for Both Free and Pro -->
                                    <div class="lds-location-mode" style="margin-bottom: 10px;">
                                        <button type="button" class="lds-mode-btn active" data-mode="text"><?php echo lds_get_inline_svg_icon('map-pin'); ?>Text</button>
                                        <button type="button" class="lds-mode-btn" data-mode="map">
                                            <?php echo lds_get_inline_svg_icon('map'); ?>Map <?php echo $is_map_locked ? LDS_Pro_Manager::get_pro_badge() : ''; ?>
                                        </button>
                                    </div>

                                <!-- Search Method Information -->
                                <div class="lds-callout lds-callout--info-subtle lds-search-method-info" style="margin-bottom: 15px;">
                                    <div class="lds-callout__content">
                                        <div id="lds-text-search-info">
                                            <strong>Text Search:</strong> Best for city-wide or general area searches (e.g., "bakery in London")
                                        </div>
                                        <div id="lds-map-search-info" style="display: none;">
                                            <strong>Map Search:</strong> Best for specific neighborhoods or streets (e.g., click exact area for local results).
                                        </div>
                                    </div>
                                </div>
                                <?php elseif ($map_enabled && empty($api_key)): ?>
                                <?php echo lds_render_notice(
                                    '<strong>Map Mode Unavailable:</strong> Google API Key is required. Please set it in <a href="' . esc_url(admin_url('admin.php?page=lds-settings')) . '">Settings</a>.',
                                    'warning'
                                ); ?>
                                <?php endif; ?>
                                
                                <!-- Text Mode (Default) -->
                                <div id="lds-text-mode" class="lds-location-input-mode">
                                    <input 
                                        type="text" 
                                        id="lds_location" 
                                        name="lds_location" 
                                        class="lds-form-input" 
                                        placeholder="e.g., New York, NY or 10001 or Times Square" 
                                    />
                                    <div class="lds-form-description">Enter city, state, zip code, or landmark</div>
                                </div>
                                
                                <!-- Map Mode -->
                                <?php if ($map_enabled && !empty($api_key)): ?>
                                <div id="lds-map-mode" class="lds-location-input-mode <?php echo $is_map_locked ? 'lds-pro-feature-locked' : ''; ?>" style="display: none; position: relative;">

                                    <?php if ($is_map_locked): ?>
                                        <!-- Lock Overlay for Free Version -->
                                        <div class="lds-lock-overlay">
                                            <div class="lds-lock-content">
                                                <span class="dashicons dashicons-lock" style="font-size: 48px; width: 48px; height: 48px; color: #0073ee; margin-bottom: 15px;"></span>
                                                <h3>Interactive Map Search</h3>
                                                <p>Click exact locations on the map for precise area targeting</p>
                                                <ul class="lds-benefits-list">
                                                    <li>Click anywhere on the map to search</li>
                                                    <li>Target specific neighborhoods</li>
                                                    <li>Perfect for local business discovery</li>
                                                </ul>
                                                <a href="<?php echo esc_url(LDS_Pro_Manager::get_upgrade_url('map_mode')); ?>" class="button button-primary button-hero" target="_blank">
                                                    <?php echo lds_get_inline_svg_icon('unlock', 'lds-inline-icon--lg'); ?>
                                                    <?php esc_html_e('Upgrade to Pro', 'listeo-data-scraper'); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <!-- Blurred Preview -->
                                        <div class="lds-map-preview-blurred">
                                    <?php endif; ?>

                                    <!-- Address Search with Geolocate Button -->
                                    <div class="lds-map-search" style="margin-bottom: 10px;">
                                        <div class="lds-location-input-wrapper" style="position: relative; display: flex; gap: 12px; align-items: stretch;">
                                            <div style="position: relative; flex: 1;">
                                                <input type="text" id="lds-address-search" placeholder="Type address (e.g., London)" style="width: 100%; padding-right: 120px;" class="lds-form-input">
                                                <button type="button" id="lds-use-location-btn" class="lds-geolocate-overlay-btn">
                                                    Geolocate me
                                                </button>
                                            </div>
                                            <button type="button" id="lds-search-btn" class="lds-search-button">
                                                Search
                                            </button>
                                        </div>
                                    </div>


                                    <!-- Selected Location Display -->
                                    <div id="lds-selected-location" style="margin-bottom: 10px;">
                                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                            <div>
                                                <strong>Selected Location:</strong> <span id="lds-location-text">Click on map to select a location</span>
                                            </div>

                                            <button type="button" id="lds-save-default-location" class="lds-save-default-btn" style="display: none;">
                                                Save as Default
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Google Map Container -->
                                    <div id="lds-google-map" style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;"></div>

                                    <!-- Hidden fields for coordinates -->
                                    <input type="hidden" id="lds-lat" name="lds_lat">
                                    <input type="hidden" id="lds-lng" name="lds_lng">
                                    <input type="hidden" id="lds-search-mode" name="lds_search_mode" value="text">

                                    <?php if ($is_map_locked): ?>
                                        </div><!-- Close lds-map-preview-blurred -->
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="lds-form-group">
                                <label for="lds_category"><?php esc_html_e('Assign to Categories', 'listeo-data-scraper'); ?> <i style="color:#d31c1c;font-style: normal; font-weight: 400"><?php esc_html_e('(Required)', 'listeo-data-scraper'); ?></i></label>
                                <?php
                                // Get all possible category taxonomies for all listing types
                                $all_categories = [];

                                // Default taxonomies (always check these)
                                $default_taxonomies = [
                                    'listing_category' => __('General Categories', 'listeo-data-scraper'),
                                    'service_category' => __('Service Categories', 'listeo-data-scraper'),
                                    'rental_category' => __('Rental Categories', 'listeo-data-scraper'),
                                    'event_category' => __('Event Categories', 'listeo-data-scraper'),
                                    'classifieds_category' => __('Classifieds Categories', 'listeo-data-scraper')
                                ];

                                // Check for custom listing type taxonomies
                                if (function_exists('listeo_core_custom_listing_types')) {
                                    $custom_types_manager = listeo_core_custom_listing_types();
                                    $types = $custom_types_manager->get_listing_types(true);

                                    foreach ($types as $type) {
                                        // Skip default types as we already have them
                                        if (!in_array($type->slug, ['service', 'rental', 'event', 'classifieds'])) {
                                            // Check if this custom type has a registered taxonomy
                                            if ($type->register_taxonomy) {
                                                $taxonomy_slug = $type->slug . '_category';
                                                if (taxonomy_exists($taxonomy_slug)) {
                                                    $default_taxonomies[$taxonomy_slug] = $type->plural_name . ' ' . __('Categories', 'listeo-data-scraper');
                                                }
                                            }
                                        }
                                    }
                                }

                                // Get categories for each taxonomy
                                foreach ($default_taxonomies as $taxonomy => $label) {
                                    if (taxonomy_exists($taxonomy)) {
                                        $terms = get_terms([
                                            'taxonomy' => $taxonomy,
                                            'hide_empty' => false,
                                            'hierarchical' => true,
                                            'orderby' => 'name',
                                            'order' => 'ASC'
                                        ]);

                                        if (!is_wp_error($terms) && !empty($terms)) {
                                            $all_categories[$taxonomy] = [
                                                'label' => $label,
                                                'terms' => $terms
                                            ];
                                        }
                                    }
                                }

                                if (empty($all_categories)) {
                                    echo '<p style="color: #f39c12;">' . esc_html__('No categories found. Please create categories first.', 'listeo-data-scraper') . '</p>';
                                } else {
                                    echo '<select id="lds_category" name="lds_category[]" class="lds-form-select" multiple>';
                                    echo '<option value="">' . esc_html__('-- Select Categories --', 'listeo-data-scraper') . '</option>';

                                    // If we have multiple taxonomies, group them
                                    if (count($all_categories) > 1) {
                                        foreach ($all_categories as $taxonomy => $data) {
                                            echo '<optgroup label="' . esc_attr($data['label']) . '" data-taxonomy="' . esc_attr($taxonomy) . '">';
                                            $this->build_hierarchical_category_options($data['terms'], 0, '', $taxonomy);
                                            echo '</optgroup>';
                                        }
                                    } else {
                                        // Single taxonomy, no need for optgroups
                                        $taxonomy_key = array_key_first($all_categories);
                                        $taxonomy_data = reset($all_categories);
                                        $this->build_hierarchical_category_options($taxonomy_data['terms'], 0, '', $taxonomy_key);
                                    }

                                    echo '</select>';

                                    // Add hidden field to store the selected taxonomy
                                    echo '<input type="hidden" id="lds_category_taxonomy" name="lds_category_taxonomy" value="" />';
                                }
                                ?>
                                <div class="lds-form-description"><?php esc_html_e('Search and select one or more categories to assign to all imported listings.', 'listeo-data-scraper'); ?></div>
                            </div>

                            <div class="lds-form-group">
                                <label for="lds_region"><?php esc_html_e('Assign to Regions (Optional)', 'listeo-data-scraper'); ?></label>
                                <?php
                                // Try multiple possible region taxonomy names - prioritize region (confirmed from regions importer)
                                $region_taxonomy = null;
                                $possible_region_taxonomies = ['region', 'listing_region', 'regions', 'location', 'listing_location'];

                                foreach ($possible_region_taxonomies as $tax_name) {
                                    if (taxonomy_exists($tax_name)) {
                                        $region_taxonomy = $tax_name;
                                        break;
                                    }
                                }

                                if ($region_taxonomy) {
                                    $regions = get_terms([
                                        'taxonomy' => $region_taxonomy,
                                        'hide_empty' => false,
                                        'hierarchical' => true,
                                        'orderby' => 'name',
                                        'order' => 'ASC'
                                    ]);

                                    if (is_wp_error($regions) || empty($regions)) {
                                        echo '<select id="lds_region" name="lds_region[]" class="lds-form-select" multiple disabled>';
                                        echo '<option value="">' . esc_html__('No regions found', 'listeo-data-scraper') . '</option>';
                                        echo '</select>';
                                        echo '<div class="lds-form-description" style="color: #e74c3c;">' . esc_html__('No regions are available in this taxonomy. You can create regions in your WordPress admin.', 'listeo-data-scraper') . '</div>';
                                    } else {
                                        echo '<select id="lds_region" name="lds_region[]" class="lds-form-select" multiple>';
                                        echo '<option value="">' . esc_html__('-- Select Regions --', 'listeo-data-scraper') . '</option>';

                                        // Build hierarchical options
                                        $this->build_hierarchical_region_options($regions, $region_taxonomy);

                                        echo '</select>';
                                        echo '<div class="lds-form-description">' . esc_html__('Search and select multiple regions to assign to all imported listings.', 'listeo-data-scraper') . '</div>';
                                    }

                                    // Add hidden field to store the detected taxonomy name
                                    echo '<input type="hidden" id="lds_region_taxonomy" name="lds_region_taxonomy" value="' . esc_attr($region_taxonomy) . '" />';
                                } else {
                                    echo '<select id="lds_region" name="lds_region[]" class="lds-form-select" multiple disabled>';
                                    echo '<option value="">' . esc_html__('No region taxonomy found', 'listeo-data-scraper') . '</option>';
                                    echo '</select>';
                                    echo '<div class="lds-form-description" style="color: #e74c3c;">' . esc_html__('No region taxonomy detected.', 'listeo-data-scraper') . '</div>';
                                }
                                ?>
                            </div>

                            <div class="lds-form-group">
                                <label for="lds_features"><?php esc_html_e('Assign Features (Optional)', 'listeo-data-scraper'); ?></label>
                                <?php
                                // The listing_feature taxonomy is registered by Listeo Core
                                if (taxonomy_exists('listing_feature')) {
                                    $features = get_terms([
                                        'taxonomy' => 'listing_feature',
                                        'hide_empty' => false,
                                        'orderby' => 'name',
                                        'order' => 'ASC'
                                    ]);

                                    if (is_wp_error($features) || empty($features)) {
                                        echo '<select id="lds_features" name="lds_features[]" class="lds-form-select" multiple disabled>';
                                        echo '<option value="">' . esc_html__('No features found', 'listeo-data-scraper') . '</option>';
                                        echo '</select>';
                                        echo '<div class="lds-form-description" style="color: #e74c3c;">' . esc_html__('No features are available. You can create features in Listings → Features.', 'listeo-data-scraper') . '</div>';
                                    } else {
                                        // Regular select - SearchableDropdown with multiSelect option will handle the UI
                                        echo '<select id="lds_features" name="lds_features[]" class="lds-form-select" multiple>';
                                        echo '<option value="">' . esc_html__('-- Select Features --', 'listeo-data-scraper') . '</option>';
                                        foreach ($features as $feature) {
                                            echo '<option value="' . esc_attr($feature->term_id) . '">' . esc_html($feature->name) . '</option>';
                                        }
                                        echo '</select>';
                                        echo '<div class="lds-form-description">' . esc_html__('Search and select multiple features to assign to all imported listings.', 'listeo-data-scraper') . '</div>';
                                    }
                                } else {
                                    echo '<select id="lds_features" name="lds_features[]" class="lds-form-select" multiple disabled>';
                                    echo '<option value="">' . esc_html__('Features taxonomy not available', 'listeo-data-scraper') . '</option>';
                                    echo '</select>';
                                    echo '<div class="lds-form-description" style="color: #e74c3c;">' . esc_html__('The listing_feature taxonomy is not registered. Please ensure Listeo Core plugin is active.', 'listeo-data-scraper') . '</div>';
                                }
                                ?>
                            </div>

                            <div class="lds-form-group">
                                <label for="lds_listing_type"><?php esc_html_e('Listing Type', 'listeo-data-scraper'); ?></label>
                                <?php
                                // Get listing types from the Custom Listing Types system
                                $listing_types = [];

                                // Check if custom listing types system exists
                                if (function_exists('listeo_core_custom_listing_types')) {
                                    $custom_types_manager = listeo_core_custom_listing_types();
                                    $types = $custom_types_manager->get_listing_types(true); // Get active types only

                                    if (!empty($types)) {
                                        foreach ($types as $type) {
                                            $listing_types[$type->slug] = $type->name;
                                        }
                                    }
                                }

                                // Fallback to default types if custom types system is not available
                                if (empty($listing_types)) {
                                    $listing_types = [
                                        'service' => 'Service',
                                        'rental' => 'Rental',
                                        'event' => 'Event',
                                        'classifieds' => 'Classifieds'
                                    ];
                                }

                                echo '<select id="lds_listing_type" name="lds_listing_type" class="lds-form-select" required>';

                                // Check if we have any types
                                if (!empty($listing_types)) {
                                    foreach ($listing_types as $value => $label) {
                                        // Default to 'service' if it exists, otherwise use first available type
                                        $selected = ($value === 'service' && isset($listing_types['service'])) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No listing types available</option>';
                                }

                                echo '</select>';
                                ?>
                                <div class="lds-form-description">Choose the type of listing that best describes these businesses.</div>
                            </div>

                            <!-- Select Data to Import Section -->
                            <div class="lds-form-group" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 10px;">
                                    <input type="checkbox" id="lds_select_data_toggle" name="lds_select_data_toggle" style="top: 1px; position: relative; margin-right: 8px;" />
                                    <span style="font-weight: 600;">Select data to import</span>
                                </label>
                                <div class="lds-form-description" style="margin-bottom: 0px; margin-top: -5px;">Choose which data fields to import from Google Places</div>

                                <!-- Checkboxes for data fields (hidden by default) -->
                                <div id="lds_data_fields" style="display: none; padding-left: 24px; margin-top: 20px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" name="lds_import_phone" id="lds_import_phone" value="1" checked style="margin-right: 6px;" />
                                            <span>Phone Number</span>
                                        </label>

                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" name="lds_import_website" id="lds_import_website" value="1" checked style="margin-right: 6px;" />
                                            <span>Website</span>
                                        </label>

                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" name="lds_import_socials" id="lds_import_socials" value="1" checked style="margin-right: 6px;" />
                                            <span>Social Links</span>
                                        </label>

                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox" name="lds_import_hours" id="lds_import_hours" value="1" checked style="margin-right: 6px;" />
                                            <span>Opening Hours</span>
                                        </label>

                                        <?php if ($api_source === 'outscraper') : ?>
                                        <label style="display: flex; align-items: center; cursor: pointer;" title="Fetch and cache Google Reviews from API (saves API calls when disabled)">
                                            <input type="checkbox" name="lds_import_place_id" id="lds_import_place_id" value="1" checked style="margin-right: 6px;" />
                                            <span>Fetch Google Reviews</span>
                                        </label>
                                        <?php endif; ?>

                                        <label style="display: flex; align-items: center; cursor: pointer;" id="lds_import_photos_label">
                                            <input type="checkbox" name="lds_import_photos" id="lds_import_photos" value="1" checked style="margin-right: 6px;" />
                                            <span>Photos</span>
                                        </label>
                                    </div>

                                    <!-- Outscraper-specific note about reviews -->
                                    <div class="lds-callout lds-callout--info" id="lds_reviews_speed_info" style="display: none; max-width: calc(100% - 50px);">
                                        <span class="lds-callout__icon"><svg viewBox="0 0 24 24"><?php echo lds_get_svg_icon_raw('zap'); ?></svg></span>
                                        <div class="lds-callout__content"><strong>Speed:</strong> <strong>Reviews can slow down the import process significantly.</strong> If you unselect the reviews option above, they won't be shown on the listing page, but <strong>they will be still fetched if AI SEO descriptions are enabled.</strong></div>
                                    </div>

                                    <div class="lds-callout lds-callout--warning" id="lds_photos_info" style="display: none; max-width: calc(100% - 50px);">
                                        <span class="lds-callout__icon"><svg viewBox="0 0 24 24"><?php echo lds_get_svg_icon_raw('camera'); ?></svg></span>
                                        <div class="lds-callout__content"><strong>Photos:</strong> <span id="lds_photos_info_text"></span></div>
                                    </div>

                                    <div class="lds-callout lds-callout--danger" id="lds_opening_hours_info" style="display: none; max-width: calc(100% - 50px);">
                                        <span class="lds-callout__icon"><svg viewBox="0 0 24 24"><?php echo lds_get_svg_icon_raw('clock'); ?></svg></span>
                                        <div class="lds-callout__content"><strong>Opening Hours:</strong> With Outscraper API, opening hours are often not available in the data. This is a limitation of the Outscraper service.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Manual Selection Checkbox -->
                            <div class="lds-form-group" style="margin-top: 10px; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 0;">
                                    <input type="checkbox" id="lds_manual_selection" name="lds_manual_selection" checked style="top: 1px; position: relative; margin-right: 8px;" />
                                    <span style="font-weight: 600;">Let me manually select places to be imported</span>
                                </label>
                                <div class="lds-form-description">When enabled, you can review and select which places to import after fetching from Google</div>
                            </div>
                            
                            <div class="lds-submit-area">
                                <button type="submit" class="lds-submit-button">
                                    <span class="button-text">Run Import</span>
                                    <span class="lds-spinner"></span>
                                </button>
                                <?php $schedule_locked = LDS_Pro_Manager::is_feature_locked('schedule_import'); ?>
                                <button type="button" id="lds-schedule-import-btn" class="lds-schedule-button<?php echo $schedule_locked ? ' is-locked' : ''; ?>">
                                    <?php echo lds_get_inline_svg_icon('clock'); ?>
                                    <span><?php esc_html_e('Schedule Import', 'listeo-data-scraper'); ?></span>
                                    <?php if ($schedule_locked) echo LDS_Pro_Manager::get_pro_badge(); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
    
                <!-- Right column: stacked cards -->
                <div class="lds-settings-column">

                    <!-- Current Settings Card -->
                    <div class="lds-settings-card">
                        <h3 class="lds-settings-header">
                            <?php echo lds_get_inline_svg_icon('settings'); ?>
                            Current Settings
                        </h3>
                        <div class="lds-settings-body">
                            <ul class="lds-settings-list">
                                <li>
                                    <strong>Listings per Import:</strong> <?php echo $limit; ?>
                                </li>
                                <li>
                                    <strong>Photos per Listing:</strong>
                                    <?php
                                    if (!$photo_enabled) {
                                        echo '0 (Photo import disabled)';
                                    } elseif ($api_source === 'outscraper') {
                                        echo '1 (Max with Outscraper)';
                                    } else {
                                        echo $photo_limit;
                                    }
                                    ?>
                                </li>
                                <li><strong>Reviews & AI Description Language:</strong> <?php echo $language_display; ?></li>
                                <li>
                                    <strong>AI Descriptions:</strong>
                                    <?php if (LDS_Pro_Manager::is_feature_locked('ai_descriptions')): ?>
                                        <?php echo LDS_Pro_Manager::get_lock_icon(); ?><span style="color: #64748b;">Locked (Pro Feature)</span>
                                    <?php else: ?>
                                        <span class="lds-ai-status <?php echo $ai_status_class; ?>"><?php echo $ai_status; ?></span>
                                    <?php endif; ?>
                                </li>
                            </ul>

                            <p style="color: #7f8c8d; font-size: 13px; margin-top: 15px;">These values can be changed on the settings page.</p>
                            <a href="<?php echo admin_url('admin.php?page=lds-settings'); ?>" class="lds-settings-button">
                                Change Settings
                            </a>
                        </div>
                    </div>

                    <!-- Scheduled Imports Card -->
                    <div class="lds-settings-card">
                        <h3 class="lds-settings-header">
                            <?php echo lds_get_inline_svg_icon('clock'); ?>
                            <?php esc_html_e('Scheduled Imports', 'listeo-data-scraper'); ?>
                            <?php if (LDS_Pro_Manager::is_feature_locked('schedule_import')) echo LDS_Pro_Manager::get_pro_badge(); ?>
                        </h3>
                        <div class="lds-settings-body">
                            <?php
                            // Single source of truth for row markup (shared with the save AJAX response).
                            echo LDS_Scheduler::render_list();
                            ?>
                        </div>
                    </div>

                </div>

            </div>
    
            <!-- Import Results Area -->
            <div id="lds-import-results"></div>
        </div>

        <!-- Schedule Import Modal -->
        <div id="lds-schedule-modal" class="lds-modal-overlay" style="display: none;" aria-hidden="true">
            <div class="lds-modal" role="dialog" aria-modal="true" aria-labelledby="lds-schedule-modal-title">
                <div class="lds-modal__header">
                    <h3 id="lds-schedule-modal-title"><?php esc_html_e('Schedule Import', 'listeo-data-scraper'); ?></h3>
                    <button type="button" class="lds-modal__close" id="lds-schedule-cancel" aria-label="<?php esc_attr_e('Close', 'listeo-data-scraper'); ?>">&times;</button>
                </div>
                <div class="lds-modal__body">
                    <p class="lds-modal__intro" id="lds-schedule-intro"><?php esc_html_e('This import will run automatically with the settings below.', 'listeo-data-scraper'); ?></p>

                    <div class="lds-schedule-summary" id="lds-schedule-summary"></div>

                    <div class="lds-schedule-when">
                        <label class="lds-schedule-when__label"><?php esc_html_e('Run this import in:', 'listeo-data-scraper'); ?></label>
                        <div class="lds-schedule-when__controls">
                            <input type="number" id="lds-schedule-amount" min="1" step="1" value="1" class="lds-schedule-when__amount" />
                            <select id="lds-schedule-unit" class="lds-schedule-when__unit">
                                <option value="minutes"><?php esc_html_e('minutes', 'listeo-data-scraper'); ?></option>
                                <option value="hours" selected><?php esc_html_e('hours', 'listeo-data-scraper'); ?></option>
                            </select>
                        </div>
                        <p class="lds-schedule-when__hint" id="lds-schedule-hint"></p>
                    </div>

                    <div class="lds-schedule-error" id="lds-schedule-error" style="display: none;"></div>
                </div>
                <div class="lds-modal__footer">
                    <button type="button" class="lds-runnow-button" id="lds-schedule-run-now" style="display: none;"><?php esc_html_e('Run now', 'listeo-data-scraper'); ?></button>
                    <button type="button" class="lds-submit-button" id="lds-schedule-save">
                        <span class="button-text"><?php esc_html_e('Schedule Import', 'listeo-data-scraper'); ?></span>
                        <span class="lds-spinner"></span>
                    </button>
                </div>
            </div>
        </div>

        <?php
    }

    public function create_settings_page() {
        ?>
        <div class="wrap lds-wrap">
            <h1 style="margin: 0 0 20px 0;">Listeo Data Importer Settings</h1>
            
            <div class="lds-settings-wrapper">
                <h3 class="lds-settings-wrapper-header">
                    Plugin Configuration
                </h3>
                <div class="lds-settings-wrapper-content">
                    <form method="post" action="options.php">
                        <?php
                            settings_fields( 'lds_settings_group' );
                            do_settings_sections( 'lds-settings-page' );
                        ?>
                        <div class="lds-settings-save-bar">
                            <input type="submit" name="submit" class="lds-submit-button" value="Save Settings" />
                        </div>
                    </form>
                </div>
            </div>

            <?php
            // Render license section card after settings wrapper
            do_action('lds_after_settings_wrapper');
            ?>
        </div>
        <?php
    }

    // Settings registration methods removed - now handled by class-lds-settings.php

    /**
     * Build hierarchical category options for the select dropdown
     *
     * @param array $terms All category terms
     * @param int $parent_id Parent term ID (0 for top level)
     * @param string $indent Indentation for child terms
     * @param string $taxonomy_name The taxonomy name (for data attribute)
     */
    private function build_hierarchical_category_options($terms, $parent_id = 0, $indent = '', $taxonomy_name = 'listing_category') {
        foreach ($terms as $term) {
            if ($term->parent == $parent_id) {
                $display_name = $indent . $term->name;

                // Add count of child categories for parent categories
                if ($parent_id == 0) {
                    $child_count = $this->count_child_terms($terms, $term->term_id);
                    if ($child_count > 0) {
                        $display_name .= '';
                    }
                }

                echo '<option value="' . esc_attr($term->term_id) . '" data-taxonomy="' . esc_attr($taxonomy_name) . '">';
                echo esc_html($display_name);
                echo '</option>';

                // Recursively add child terms with indentation
                $this->build_hierarchical_category_options($terms, $term->term_id, $indent . '&nbsp;&nbsp;&nbsp;&nbsp;', $taxonomy_name);
            }
        }
    }

    /**
     * Build hierarchical region options for the select dropdown
     * 
     * @param array $terms All region terms
     * @param string $taxonomy_name The taxonomy name
     * @param int $parent_id Parent term ID (0 for top level)
     * @param string $indent Indentation for child terms
     */
    private function build_hierarchical_region_options($terms, $taxonomy_name, $parent_id = 0, $indent = '') {
        foreach ($terms as $term) {
            if ($term->parent == $parent_id) {
                $display_name = $indent . $term->name;
                
                // Add count of child terms for parent regions
                if ($parent_id == 0) {
                    $child_count = $this->count_child_terms($terms, $term->term_id);
                    if ($child_count > 0) {
                        $display_name .= '';
                    }
                }
                
                echo '<option value="' . esc_attr($term->term_id) . '" data-taxonomy="' . esc_attr($taxonomy_name) . '">';
                echo esc_html($display_name);
                echo '</option>';
                
                // Recursively add child terms with indentation
                $this->build_hierarchical_region_options($terms, $taxonomy_name, $term->term_id, $indent . '&nbsp;&nbsp;&nbsp;&nbsp;');
            }
        }
    }

    /**
     * Count child terms for a given parent
     * 
     * @param array $terms All terms
     * @param int $parent_id Parent term ID
     * @return int Number of child terms
     */
    private function count_child_terms($terms, $parent_id) {
        $count = 0;
        foreach ($terms as $term) {
            if ($term->parent == $parent_id) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Enqueue Google Maps API and map functionality scripts
     */
    public function enqueue_map_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'listeo-data-scraper') === false) {
            return;
        }

        $map_enabled = 1; // Always enabled - map mode is default
        if (!$map_enabled) {
            return;
        }

        // Try to use the maps-specific API key first, fallback to main API key
        $maps_api_key = get_option('lds_google_maps_api_key');
        $main_api_key = get_option('lds_google_api_key');
        
        $api_key = !empty($maps_api_key) ? $maps_api_key : $main_api_key;
        
        if (empty($api_key)) {
            return;
        }

        // Enqueue our map functionality script
        wp_enqueue_script(
            'lds-map-js',
            LDS_PLUGIN_URL . 'assets/js/lds-map.js',
            ['jquery'],
            LDS_VERSION,
            false // Load in head, not footer
        );

        // Pass settings to JavaScript
        $map_settings = [
            'default_lat' => get_option('lds_default_map_center_lat', '51.5074'),
            'default_lng' => get_option('lds_default_map_center_lng', '-0.1278'),
            'default_zoom' => (int) get_option('lds_map_zoom_level', 12),
            'default_radius' => (float) get_option('lds_default_radius', 2.0),
            'ajax_url' => admin_url('admin-ajax.php')
        ];

        wp_localize_script('lds-map-js', 'ldsMapSettings', $map_settings);

        // Enqueue Google Maps API after our script with callback
        wp_enqueue_script(
            'google-maps-api',
            "https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places&callback=initLDSMap",
            ['lds-map-js'],
            null,
            false // Load in head, not footer
        );
    }
}
