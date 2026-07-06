<?php
/*
 * Plugin Name: Listeo - Forms&Fields Editor
 * Version: 2.0.31
 * Plugin URI: http://www.purethemes.net/
 * Description: Editor for Listeo - Directory Plugin from Purethemes.net
 * Author: Purethemes.net
 * Author URI: http://www.purethemes.net/
 * Requires at least: 4.7
 * Tested up to: 4.8.2
 *
 * Text Domain: listeo-fafe
 * Domain Path: /languages/
 *
 * @package WordPress
 * @author Lukasz Girek
 * @since 1.0.0
 */


class Listeo_Forms_And_Fields_Editor
{


    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;


    public $fields;
    public $submit;
    //$this->booking  = Listeo_BookingForm_Editor::instance();
    public $forms;
    public $reviews_criteria;
    public $reviews_criteria_advanced;
    public $users;
    public $booking_fields;
    //$this->import_export  = Listeo_Forms_Import_Export::instance();

    public $registration;
    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * Initiate our hooks
     * @since 0.1.0
     */
    public function __construct($file = '', $version = '1.8.0')
    {
        $this->_version = $version;
        add_action('admin_menu', array($this, 'add_options_page')); //create tab pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));

        // Load plugin environment variables
        $this->file = __FILE__;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

        include('includes/class-listeo-forms-builder.php');
        include('includes/class-listeo-fields-builder.php');
        include('includes/class-listeo-user-fields-builder.php');

        include('includes/class-listeo-reviews-criteria.php');
        include('includes/class-listeo-reviews-criteria-advanced.php');
        include('includes/class-listeo-submit-builder.php');
        include('includes/class-listeo-booking-fields-builder.php');
        //include( 'includes/class-listeo-bookingform-builder.php' );
        include('includes/class-listeo-registration-form-builder.php');
        //include( 'includes/class-listeo-import-export.php' );

        $this->fields  = Listeo_Fields_Editor::instance();
        $this->submit  = Listeo_Submit_Editor::instance();
        //$this->booking  = Listeo_BookingForm_Editor::instance();
        $this->forms  = Listeo_Forms_Editor::instance();
        $this->reviews_criteria  = Listeo_Reviews_Criteria::instance();
        $this->reviews_criteria_advanced  = Listeo_Reviews_Criteria_Advanced::instance();
        $this->users  = Listeo_User_Fields_Editor::instance();
        $this->booking_fields  = Listeo_Booking_Fields_Editor::instance();
        //$this->import_export  = Listeo_Forms_Import_Export::instance();

        $this->registration  = Listeo_Registration_Form_Editor::instance();

        add_action('admin_init', array($this, 'listeo_process_settings_export'));
        add_action('admin_init', array($this, 'listeo_process_settings_import'));
        add_action('admin_init', array($this, 'listeo_process_featured_fix'));
        add_action('admin_init', array($this, 'listeo_process_events_fix'));
        add_action('admin_init', array($this, 'listeo_fix_author_dropdown'));
        
        // AJAX handlers for event timestamp processing
        add_action('wp_ajax_process_events_batch', array($this, 'process_events_batch'));
        add_action('wp_ajax_nopriv_process_events_batch', array($this, 'process_events_batch'));

        add_filter('admin_body_class', array($this, 'listeo_editor_admin_classes'));
    }
    function listeo_editor_admin_classes($classes)
    {
        global $current_screen;


        if (in_array($current_screen->base, array(
            'listeo-editor_page_listeo-submit-builder',
            'listeo-editor_page_listeo-forms-builder',
            //'listeo-editor_page_listeo-bookingform-builder',
            'listeo-editor_page_listeo-fields-builder',
            'listeo-editor_page_listeo-reviews-criteria',
            'reviews-criteria_page_listeo-reviews-criteria',
            'reviews-criteria_page_listeo-reviews-criteria-types',
            'reviews-criteria_page_listeo-reviews-criteria-taxonomies',
            'listeo-editor_page_listeo-user-fields-builder',
            'listeo-editor_page_listeo-booking-fields-builder',
            'listeo-editor_page_listeo-user-registration-builder',
            'listeo-editor_page_listeo-user-fields-registration'

        ), true)) {
            $classes .= ' listeo-editor';
        }

        return $classes;
    }



    public function enqueue_scripts_and_styles($hook)
    {

        if (!in_array(
            $hook,
            array(
                'toplevel_page_listeo-fields-and-form',
                'listeo-editor_page_listeo-submit-builder',
                'listeo-editor_page_listeo-forms-builder',
                //'listeo-editor_page_listeo-bookingform-builder',
                'listeo-editor_page_listeo-fields-builder',
                'listeo-editor_page_listeo-reviews-criteria',
                'listeo-editor_page_listeo-user-fields-builder',
                'listeo-editor_page_listeo-booking-fields-builder',
                'listeo-editor_page_listeo-user-registration-builder',
                'listeo-editor_page_listeo-user-fields-registration',
                'listeo-editor_page_listeo-listing-types'
            )
        )) {
            return;
        }

        // Listing Types page only needs CSS, not the form editor JS
        $is_listing_types_page = ($hook === 'listeo-editor_page_listeo-listing-types');

        if (!$is_listing_types_page) {
            wp_enqueue_script('listeo-fafe-script', esc_url($this->assets_url) . 'js/admin.js', array('jquery', 'jquery-ui-droppable', 'jquery-ui-draggable', 'jquery-ui-sortable',  'jquery-ui-resizable'));

            // Localize script with nonce for AJAX calls
            wp_localize_script('listeo-fafe-script', 'listeo_admin', array(
                'nonce' => wp_create_nonce('listeo_admin_nonce'),
                'ajax_url' => admin_url('admin-ajax.php')
            ));

            wp_enqueue_script('micromodal', 'https://unpkg.com/micromodal/dist/micromodal.min.js', array(), null, true);

            // Load icon selector for proper icon picker functionality
            wp_enqueue_script('listeo-icon-selector', get_template_directory_uri() . '/js/iconselector.min.js', array('jquery'), '20180323', true);
        }

        wp_enqueue_style('listeo-icons', get_template_directory_uri() . '/css/all.css');
        wp_enqueue_style('listeo-icons-fav4', get_template_directory_uri() . '/css/fav4-shims.min.css');
        wp_enqueue_style('listeo-iconsmind', get_template_directory_uri() . '/css/icons.css');

        wp_register_style('listeo-fafe-styles', esc_url($this->assets_url) . 'css/admin.css', array(), $this->_version);
        wp_enqueue_style('listeo-fafe-styles');
        //wp_enqueue_style('wp-jquery-ui-dialog');
    }

    /**
     * Add menu options page
     * @since 0.1.0
     */
    public function add_options_page()
    {
        add_menu_page('Listeo Forms and Fields Editor', 'Listeo Editor', 'manage_options', 'listeo-fields-and-form', array($this, 'output'), 'dashicons-forms', 80);

        //add_submenu_page( 'listeo-fields-and-form', 'Property Fields', 'Property Fields', 'manage_options', 'realte-fields-builder', array( $this, 'output' ));
    }

    public function output()
    {
        if (!empty($_GET['import'])) {
            echo '<div class="updated"><p>' . __('The file was imported successfully.', 'listeo') . '</p></div>';
        } ?>
        <div class="metabox-holder listeo-import-export ">
            <div class="postbox">
                <h3><span><?php _e('Export Settings'); ?></span></h3>
                <div class="inside">
                    <p><?php _e('Export fields and forms settings for this site as a .json file. This allows you to easily import the configuration into another site or make a backup.'); ?></p>
                    <form method="post">
                        <p><input type="hidden" name="listeo_action" value="export_settings" /></p>
                        <p>
                            <?php wp_nonce_field('listeo_export_nonce', 'listeo_export_nonce'); ?>
                            <?php submit_button(__('Export'), 'secondary', 'submit', false); ?>
                        </p>
                    </form>
                </div><!-- .inside -->
            </div><!-- .postbox -->

            <div class="postbox">
                <h3><span><?php _e('Import Settings'); ?></span></h3>
                <div class="inside">
                    <p><?php _e('Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.'); ?></p>
                    <form method="post" enctype="multipart/form-data">
                        <p>
                            <input type="file" name="import_file" />
                        </p>
                        <p>
                            <input type="hidden" name="listeo_action" value="import_settings" />
                            <?php wp_nonce_field('listeo_import_nonce', 'listeo_import_nonce'); ?>
                            <?php submit_button(__('Import'), 'secondary', 'submit', false); ?>
                        </p>
                    </form>
                </div><!-- .inside -->
            </div><!-- .postbox -->
            <div class="postbox">
                <h3><span><?php _e('Fix Featured listings '); ?></span></h3>
                <div class="inside">
                    <p><?php _e('We have changed the way featured listings information is storred since version 1.3.3. If you have updated from older version, please run the fix function by clicking button below'); ?></p>
                    <?php $args = array(
                        'post_type' => 'listing',
                        'posts_per_page'   => -1,
                    );
                    $counter = 0;
                    $post_query = new WP_Query($args);
                    $posts_array = get_posts($args);
                    foreach ($posts_array as $post_array) {
                        $featured = get_post_meta($post_array->ID, '_featured', true);

                        if ($featured !== 'on' && $featured !== "0") {
                            $counter++;
                            //update_post_meta($post_array->ID, '_featured', false);
                        }
                    }
                    wp_reset_query();
                    echo "There are " . $counter . " listings to be fixed"; ?>
                    <form method="post" enctype="multipart/form-data">

                        <p>
                            <input type="hidden" name="listeo_action" value="fix_featured" />
                            <?php wp_nonce_field('fix_featured_nonce', 'fix_featured_nonce'); ?>
                            <?php submit_button(__('Fix Featured'), 'secondary', 'submit', false); ?>
                        </p>
                    </form>
                </div><!-- .inside -->
            </div><!-- .postbox -->

            <div class="postbox">
                <h3><span><?php _e('Fix Event Dates Timestamps'); ?></span></h3>
                <div class="inside">
                    <p><?php _e('This tool fixes event date timestamps for proper date-based search functionality. It uses the same logic as core event processing to ensure consistency across all date formats. Events are processed in batches to prevent timeouts on large sites.'); ?></p>
                    
                    <?php 
                    // Count total events
                    $args = array(
                        'post_type' => 'listing',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'meta_key' => '_listing_type',
                        'meta_value' => 'event',
                        'fields' => 'ids'
                    );
                    $all_events = get_posts($args);
                    $total_events = count($all_events);
                    
                    // Count events with timestamp issues
                    $events_without_timestamps = 0;
                    $events_with_bad_timestamps = 0;
                    $events_without_start_date = 0;
                    
                    if ($total_events > 0) {
                        foreach ($all_events as $event_id) {
                            // Check if event has start date meta field
                            $event_date = get_post_meta($event_id, '_event_date', true);
                            if (empty($event_date)) {
                                $events_without_start_date++;
                                continue;
                            }
                            
                            // Check for start date timestamp
                            $timestamp = get_post_meta($event_id, '_event_date_timestamp', true);
                            if (empty($timestamp)) {
                                $events_without_timestamps++;
                            } else {
                                // Check for invalid timestamps - use stricter validation
                                $current_year = (int)date('Y');
                                $timestamp_year = (int)date('Y', $timestamp);
                                
                                if ($timestamp_year < 1900 || $timestamp_year > ($current_year + 50)) {
                                    $events_with_bad_timestamps++;
                                }
                            }
                        }
                    }
                    ?>
                    
                    <div id="events-stats">
                        <p><strong>Statistics:</strong></p>
                        <ul>
                            <li>Total Events: <strong><?php echo $total_events; ?></strong></li>
                            <li>Events without start date: <strong><?php echo $events_without_start_date; ?></strong></li>
                            <li>Events without timestamps: <strong><?php echo $events_without_timestamps; ?></strong></li>
                            <li>Events with invalid timestamps: <strong><?php echo $events_with_bad_timestamps; ?></strong></li>
                        </ul>
                        <?php if ($events_without_start_date > 0): ?>
                        <p style="color: #d63638; margin-top: 10px;"><strong>Warning:</strong> <?php echo $events_without_start_date; ?> events have no start date and cannot be fixed automatically.</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_events > 0): ?>
                        <div id="events-fix-controls">
                            <p>
                                <label>
                                    <input type="checkbox" id="reset-timestamps" checked> 
                                    Reset existing timestamps (recommended)
                                </label>
                            </p>
                            <p>
                                <label>
                                    Batch size: 
                                    <select id="batch-size">
                                        <option value="25">25 events</option>
                                        <option value="50" selected>50 events</option>
                                        <option value="100">100 events</option>
                                        <option value="200">200 events</option>
                                    </select>
                                </label>
                            </p>
                            <p>
                                <button id="fix-events-btn" class="button button-primary">
                                    Fix Event Timestamps
                                </button>
                                <button id="stop-processing-btn" class="button button-secondary" style="display: none;">
                                    Stop Processing
                                </button>
                            </p>
                        </div>
                        
                        <div id="events-progress" style="display: none;">
                            <div class="progress-info">
                                <p id="progress-text">Preparing to process events...</p>
                                <div class="progress-bar-container" style="background: #f1f1f1; border: 1px solid #ccc; border-radius: 3px; height: 20px; margin: 10px 0;">
                                    <div id="progress-bar" style="background: #0073aa; height: 100%; width: 0%; border-radius: 2px; transition: width 0.3s ease;"></div>
                                </div>
                                <p id="progress-details" style="font-size: 12px; color: #666;"></p>
                            </div>
                            <div id="processing-log" style="max-height: 200px; overflow-y: auto; background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px; display: none;">
                            </div>
                            <p>
                                <label>
                                    <input type="checkbox" id="show-log"> Show detailed log
                                </label>
                            </p>
                        </div>
                        
                        <div id="events-results" style="display: none;">
                            <div id="results-content"></div>
                            <p style="margin-top: 15px;">
                                <button id="refresh-stats-btn" class="button button-secondary">
                                    Refresh Statistics
                                </button>
                            </p>
                        </div>
                        
                        <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            var isProcessing = false;
                            var currentBatch = 1;
                            var totalBatches = 0;
                            var batchSize = 50;
                            var resetTimestamps = true;
                            var totalEvents = <?php echo $total_events; ?>;
                            
                            // Update batch size when changed
                            $('#batch-size').on('change', function() {
                                batchSize = parseInt($(this).val());
                                totalBatches = Math.ceil(totalEvents / batchSize);
                                updateProgressText();
                            });
                            
                            // Update reset timestamps when changed
                            $('#reset-timestamps').on('change', function() {
                                resetTimestamps = $(this).is(':checked');
                            });
                            
                            // Show/hide log
                            $('#show-log').on('change', function() {
                                if ($(this).is(':checked')) {
                                    $('#processing-log').show();
                                } else {
                                    $('#processing-log').hide();
                                }
                            });
                            
                            // Initialize
                            totalBatches = Math.ceil(totalEvents / batchSize);
                            
                            function updateProgressText() {
                                if (totalBatches > 0) {
                                    $('#progress-details').text('Estimated ' + totalBatches + ' batches of ' + batchSize + ' events each');
                                }
                            }
                            updateProgressText();
                            
                            // Start processing
                            $('#fix-events-btn').on('click', function() {
                                if (isProcessing) return;
                                
                                isProcessing = true;
                                currentBatch = 1;
                                batchSize = parseInt($('#batch-size').val());
                                resetTimestamps = $('#reset-timestamps').is(':checked');
                                totalBatches = Math.ceil(totalEvents / batchSize);
                                
                                // Update UI
                                $('#fix-events-btn').hide();
                                $('#stop-processing-btn').show();
                                $('#events-progress').show();
                                $('#events-results').hide();
                                $('#processing-log').empty();
                                
                                // Reset progress
                                $('#progress-bar').css('width', '0%');
                                $('#progress-text').text('Starting batch processing...');
                                $('#progress-details').text('Batch 0 of ' + totalBatches);
                                
                                // Start processing
                                processBatch();
                            });
                            
                            // Stop processing
                            $('#stop-processing-btn').on('click', function() {
                                isProcessing = false;
                                $(this).hide();
                                $('#fix-events-btn').show();
                                $('#progress-text').text('Processing stopped by user');
                                addToLog('Processing stopped by user');
                            });
                            
                            // Refresh statistics
                            $('#refresh-stats-btn').on('click', function() {
                                location.reload();
                            });
                            
                            function processBatch() {
                                if (!isProcessing) return;
                                
                                var offset = (currentBatch - 1) * batchSize;
                                
                                $('#progress-text').text('Processing batch ' + currentBatch + ' of ' + totalBatches + '...');
                                $('#progress-details').text('Events ' + (offset + 1) + ' to ' + Math.min(offset + batchSize, totalEvents) + ' of ' + totalEvents);
                                
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'process_events_batch',
                                        batch_number: currentBatch,
                                        batch_size: batchSize,
                                        reset_timestamps: resetTimestamps ? 1 : 0,
                                        nonce: '<?php echo wp_create_nonce("process_events_batch"); ?>'
                                    },
                                    success: function(response) {
                                        console.log('AJAX Response:', response);
                                        if (response.success) {
                                            // Update progress bar
                                            var progress = response.progress;
                                            $('#progress-bar').css('width', progress + '%');
                                            
                                            // Add to log
                                            var logMessage = 'Batch ' + response.batch_number + ': Processed ' + response.processed + ' events';
                                            if (response.errors > 0) {
                                                logMessage += ' (' + response.errors + ' errors)';
                                            }
                                            addToLog(logMessage);
                                            addToLog('DEBUG: Total=' + response.total + ', Remaining=' + response.remaining + ', Progress=' + response.progress + '%');
                                            
                                            // Check if we're done
                                            if (response.remaining <= 0) {
                                                // Processing complete
                                                isProcessing = false;
                                                $('#stop-processing-btn').hide();
                                                $('#fix-events-btn').show();
                                                $('#progress-text').text('Processing completed successfully!');
                                                
                                                // Show results
                                                showResults(response);
                                                
                                            } else {
                                                // Continue with next batch
                                                currentBatch++;
                                                setTimeout(function() {
                                                    processBatch();
                                                }, 500); // Small delay between batches
                                            }
                                        } else {
                                            // Error occurred
                                            isProcessing = false;
                                            $('#stop-processing-btn').hide();
                                            $('#fix-events-btn').show();
                                            $('#progress-text').text('Error occurred during processing');
                                            addToLog('ERROR: ' + (response.data || 'Unknown error occurred'));
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        isProcessing = false;
                                        $('#stop-processing-btn').hide();
                                        $('#fix-events-btn').show();
                                        $('#progress-text').text('Network error occurred');
                                        addToLog('NETWORK ERROR: ' + error);
                                    }
                                });
                            }
                            
                            function addToLog(message) {
                                var timestamp = new Date().toLocaleTimeString();
                                $('#processing-log').append('<div>[' + timestamp + '] ' + message + '</div>');
                                $('#processing-log').scrollTop($('#processing-log')[0].scrollHeight);
                            }
                            
                            function showResults(finalResponse) {
                                var resultsHtml = '<div class="notice notice-success"><p><strong>Processing Complete!</strong></p></div>';
                                resultsHtml += '<h4>Final Results:</h4>';
                                resultsHtml += '<ul>';
                                resultsHtml += '<li>Total events processed: <strong>' + (currentBatch - 1) * batchSize + '</strong></li>';
                                resultsHtml += '<li>Final batch processed: <strong>' + finalResponse.processed + '</strong> events</li>';
                                if (finalResponse.errors > 0) {
                                    resultsHtml += '<li>Total errors: <strong>' + finalResponse.errors + '</strong></li>';
                                }
                                resultsHtml += '</ul>';
                                resultsHtml += '<p><em>Check the detailed log above for specific information about any errors.</em></p>';
                                
                                $('#results-content').html(resultsHtml);
                                $('#events-results').show();
                                
                                // Don't refresh immediately, let user see results first
                                // location.reload();
                            }
                        });
                        </script>
                        
                    <?php else: ?>
                        <p><em>No events found to process.</em></p>
                    <?php endif; ?>
                    
                </div><!-- .inside -->
            </div><!-- .postbox -->


            <div class="postbox">
                <h3><span><?php _e('Fix Users '); ?></span></h3>
                <div class="inside">
                    <?php _e('If you do not see all users available in your Author dropdown, please click the button below'); ?></p>
                    <form method="post" enctype="multipart/form-data">

                        <p>
                            <input type="hidden" name="listeo_action" value="fix_author_dropdown" />
                            <?php wp_nonce_field('fix_author_dropdown_nonce', 'fix_author_dropdown_nonce'); ?>
                            <?php submit_button(__('Fix Author dropdown'), 'secondary', 'submit', false); ?>
                        </p>

                    </form>
                </div>

            </div>
        </div><!-- .metabox-holder -->
<?php
    }


    /**
     * Process a settings export that generates a .json file of the shop settings
     */
    function listeo_process_settings_export()
    {

        if (empty($_POST['listeo_action']) || 'export_settings' != $_POST['listeo_action'])
            return;

        if (!wp_verify_nonce($_POST['listeo_export_nonce'], 'listeo_export_nonce'))
            return;

        if (!current_user_can('manage_options'))
            return;

        $settings = array();
        $settings['property_types']         = get_option('listeo_property_types_fields');
        $settings['property_rental']        = get_option('listeo_rental_periods_fields');
        $settings['property_offer_types']   = get_option('listeo_offer_types_fields');

        $settings['submit']                 = get_option('listeo_submit_form_fields');

        $settings['price_tab']              = get_option('listeo_price_tab_fields');
        $settings['main_details_tab']       = get_option('listeo_main_details_tab_fields');
        $settings['details_tab']            = get_option('listeo_details_tab_fields');
        $settings['location_tab']           = get_option('listeo_locations_tab_fields');

        $settings['sidebar_search']         = get_option('listeo_sidebar_search_form_fields');
        $settings['full_width_search']      = get_option('listeo_full_width_search_form_fields');
        $settings['half_map_search']        = get_option('listeo_search_on_half_map_form_fields');
        $settings['home_page_search']       = get_option('listeo_search_on_home_page_form_fields');
        $settings['home_page_alt_search']   = get_option('listeo_search_on_home_page_alt_form_fields');

        ignore_user_abort(true);

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=listeo-settings-export-' . date('m-d-Y') . '.json');
        header("Expires: 0");

        echo json_encode($settings);
        exit;
    }

    /**
     * Process a settings import from a json file
     */
    function listeo_process_settings_import()
    {

        if (empty($_POST['listeo_action']) || 'import_settings' != $_POST['listeo_action'])
            return;

        if (!wp_verify_nonce($_POST['listeo_import_nonce'], 'listeo_import_nonce'))
            return;

        if (!current_user_can('manage_options'))
            return;

        $extension = end(explode('.', $_FILES['import_file']['name']));

        if ($extension != 'json') {
            wp_die(__('Please upload a valid .json file'));
        }

        $import_file = $_FILES['import_file']['tmp_name'];

        if (empty($import_file)) {
            wp_die(__('Please upload a file to import'));
        }

        // Retrieve the settings from the file and convert the json object to an array.
        $settings = json_decode(file_get_contents($import_file), true);

        update_option('listeo_property_types_fields', $settings['property_types']);
        update_option('listeo_rental_periods_fields', $settings['property_rental']);
        update_option('listeo_offer_types_fields', $settings['property_offer_types']);

        update_option('listeo_submit_form_fields', $settings['submit']);

        update_option('listeo_price_tab_fields', $settings['price_tab']);
        update_option('listeo_main_details_tab_fields', $settings['main_details_tab']);
        update_option('listeo_details_tab_fields', $settings['details_tab']);
        update_option('listeo_locations_tab_fields', $settings['location_tab']);

        update_option('listeo_sidebar_search_form_fields', $settings['sidebar_search']);
        update_option('listeo_full_width_search_form_fields', $settings['full_width_search']);
        update_option('listeo_search_on_half_map_form_fields', $settings['half_map_search']);
        update_option('listeo_search_on_home_page_form_fields', $settings['home_page_search']);
        update_option('listeo_search_on_home_page_alt_form_fields', $settings['home_page_alt_search']);


        wp_safe_redirect(admin_url('admin.php?page=listeo-fields-and-form&import=success'));
        exit;
    }


    function listeo_fix_author_dropdown()
    {
        if (empty($_POST['listeo_action']) || 'fix_author_dropdown' != $_POST['listeo_action'])
            return;

        if (!current_user_can('manage_options'))
            return;

        $ownerusers = get_users(array('role__in' => array('owner', 'seller')));

        foreach ($ownerusers as $user) {
            $user->add_cap('level_1');
        }
    }
    function listeo_process_featured_fix()
    {
        if (empty($_POST['listeo_action']) || 'fix_featured' != $_POST['listeo_action'])
            return;

        if (!wp_verify_nonce($_POST['fix_featured_nonce'], 'fix_featured_nonce'))
            return;

        if (!current_user_can('manage_options'))
            return;

        $args = array(
            'post_type' => 'listing',
            'posts_per_page'   => -1,
        );
        $counter = 0;
        $post_query = new WP_Query($args);
        $posts_array = get_posts($args);
        foreach ($posts_array as $post_array) {
            $featured = get_post_meta($post_array->ID, '_featured', true);

            if ($featured !== 'on' && $featured !== "0") {

                update_post_meta($post_array->ID, '_featured', '0');
            }
        }
    }

    function listeo_process_events_fix()
    {
        if (empty($_POST['listeo_action']) || 'fix_events' != $_POST['listeo_action'])
            return;

        if (!wp_verify_nonce($_POST['fix_events_nonce'], 'fix_events_nonce'))
            return;

        if (!current_user_can('manage_options'))
            return;

        // Get batch parameters
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 1;
        $reset_timestamps = isset($_POST['reset_timestamps']) ? true : false;
        
        // Calculate offset
        $offset = ($batch_number - 1) * $batch_size;

        // Get total count for progress tracking
        $total_args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'meta_key' => '_listing_type',
            'meta_value' => 'event',
            'fields' => 'ids'
        );
        $total_posts = get_posts($total_args);
        $total_count = count($total_posts);

        // Get current batch
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'meta_key' => '_listing_type',
            'meta_value' => 'event',
        );

        $posts_array = get_posts($args);
        $processed = 0;
        $errors = 0;

        foreach ($posts_array as $post_array) {
            
            // Reset timestamps if requested
            if ($reset_timestamps) {
                delete_post_meta($post_array->ID, '_event_date_timestamp');
                delete_post_meta($post_array->ID, '_event_date_end_timestamp');
            }

            // Process event start date
            $event_date = get_post_meta($post_array->ID, '_event_date', true);
            if ($event_date) {
                $success = $this->process_event_date_timestamp($post_array->ID, $event_date, '_event_date_timestamp');
                if (!$success) {
                    $errors++;
                  
                }
            }
            
            // Process event end date
            $event_date_end = get_post_meta($post_array->ID, '_event_date_end', true);
            if ($event_date_end) {
                $success = $this->process_event_date_timestamp($post_array->ID, $event_date_end, '_event_date_end_timestamp');
                if (!$success) {
                    $errors++;
                   
                }
            }
            
            $processed++;
        }

        // Send response with progress info
        $response = array(
            'success' => true,
            'batch_number' => $batch_number,
            'batch_size' => $batch_size,
            'processed' => $processed,
            'errors' => $errors,
            'total' => $total_count,
            'remaining' => max(0, $total_count - ($batch_number * $batch_size)),
            'progress' => min(100, round(($batch_number * $batch_size) / $total_count * 100, 2)),
            'message' => "Batch {$batch_number}: Processed {$processed} events" . ($errors > 0 ? " ({$errors} errors)" : "")
        );

        wp_send_json($response);
    }

    /**
     * Helper function to process individual event date timestamps
     */
    private function process_event_date_timestamp($post_id, $date_value, $meta_key)
    {
        // Use the same logic as the core Listeo_Core_Post_Types class for consistency
        // This ensures all event timestamps are created uniformly regardless of entry method
        
        // Validate input
        if (empty($date_value)) {
            error_log("Listeo Fix Timestamps: Empty date string for post {$post_id}, field {$meta_key}");
            return false;
        }

        // Extract date part - handle both "date time" and "date" formats
        $date_part = explode(' ', $date_value)[0];
        if (empty($date_part)) {
            error_log("Listeo Fix Timestamps: Invalid date format for post {$post_id}, field {$meta_key}: {$date_value}");
            return false;
        }

        // Get the configured date format from WordPress settings
        $wp_format = get_option('date_format', 'Y-m-d');
        
        // Convert WordPress date format to PHP DateTime format
        $php_format = 'Y-m-d'; // Default fallback
        if (function_exists('listeo_date_time_wp_format_php')) {
            $php_format = listeo_date_time_wp_format_php();
        }

        // Try parsing with configured format first
        $timestamp = false;
        $date_obj = DateTime::createFromFormat($php_format, $date_part);
        
        if ($date_obj !== false) {
            $errors = DateTime::getLastErrors();
            if (!$errors || ($errors['error_count'] === 0 && $errors['warning_count'] === 0)) {
                // Validate by formatting back
                if ($date_obj->format($php_format) === $date_part) {
                    $timestamp = $date_obj->getTimestamp();
                }
            }
        }

        // If primary format failed, try common fallback formats
        if ($timestamp === false) {
            $fallback_formats = array(
                'Y-m-d',    // ISO format
                'm/d/Y',    // US format
                'd/m/Y',    // European format  
                'Y/m/d',    // Alternative ISO
                'd-m-Y',    // European with dashes
                'm-d-Y',    // US with dashes
                'd.m.Y',    // European with dots
                'm.d.Y'     // US with dots
            );

            foreach ($fallback_formats as $format) {
                $date_obj = DateTime::createFromFormat($format, $date_part);
                if ($date_obj !== false) {
                    $errors = DateTime::getLastErrors();
                    if (!$errors || ($errors['error_count'] === 0 && $errors['warning_count'] === 0)) {
                        if ($date_obj->format($format) === $date_part) {
                            $timestamp = $date_obj->getTimestamp();
                            error_log("Listeo Fix Timestamps: Successfully parsed post {$post_id} date '{$date_part}' using fallback format '{$format}'");
                            break;
                        }
                    }
                }
            }
        }

        // Final validation and save
        if ($timestamp !== false) {
            // Validate timestamp is reasonable (not too far in past or future)
            $current_year = (int)date('Y');
            $timestamp_year = (int)date('Y', $timestamp);
            
            if ($timestamp_year < 1900 || $timestamp_year > ($current_year + 50)) {
                error_log("Listeo Fix Timestamps: Invalid timestamp year {$timestamp_year} for post {$post_id}, field {$meta_key}: {$date_value}");
                return false;
            }

            // Set timestamp to beginning of day (00:00:00) for consistency
            $date_obj = new DateTime();
            $date_obj->setTimestamp($timestamp);
            $date_obj->setTime(0, 0, 0);
            $timestamp = $date_obj->getTimestamp();

            update_post_meta($post_id, $meta_key, $timestamp);
            error_log("Listeo Fix Timestamps: Successfully created timestamp {$timestamp} for post {$post_id}, field {$meta_key}");
            return true;
        }

        error_log("Listeo Fix Timestamps: Failed to parse date for post {$post_id}, field {$meta_key}: {$date_value}");
        return false;
    }

    /**
     * AJAX handler for batch processing events
     */
    function process_events_batch()
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'process_events_batch')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Handle AJAX request for batch processing events
        $batch_number = intval($_POST['batch_number']);
        $batch_size = intval($_POST['batch_size']);
        $reset_timestamps = !empty($_POST['reset_timestamps']);
        $offset = ($batch_number - 1) * $batch_size;

        // Get total count for progress tracking
        $total_args = array(
            'post_type' => 'listing',
            'posts_per_page' => -1,
            'meta_key' => '_listing_type',
            'meta_value' => 'event',
            'fields' => 'ids'
        );
        $total_posts = get_posts($total_args);
        $total_count = count($total_posts);

        // Get current batch
        $args = array(
            'post_type' => 'listing',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'meta_key' => '_listing_type',
            'meta_value' => 'event',
        );

        $posts_array = get_posts($args);
        $processed = 0;
        $errors = 0;

        foreach ($posts_array as $post_array) {
            $post_errors = 0;
            
            // Reset timestamps if requested
            if ($reset_timestamps) {
                delete_post_meta($post_array->ID, '_event_date_timestamp');
                delete_post_meta($post_array->ID, '_event_date_end_timestamp');
            }

            // Process event start date (required for events)
            $event_date = get_post_meta($post_array->ID, '_event_date', true);
            if ($event_date) {
                $success = $this->process_event_date_timestamp($post_array->ID, $event_date, '_event_date_timestamp');
                if (!$success) {
                    $post_errors++;
                    error_log("Listeo Fix Timestamps: Failed to process _event_date for post ID {$post_array->ID}: '{$event_date}'");
                }
            } else {
                // Event without start date - this is a problem
                $post_errors++;
                error_log("Listeo Fix Timestamps: Event post ID {$post_array->ID} has no _event_date meta field");
            }
            
            // Process event end date (optional for events)
            $event_date_end = get_post_meta($post_array->ID, '_event_date_end', true);
            if (!empty($event_date_end)) {
                $success = $this->process_event_date_timestamp($post_array->ID, $event_date_end, '_event_date_end_timestamp');
                if (!$success) {
                    $post_errors++;
                    error_log("Listeo Fix Timestamps: Failed to process _event_date_end for post ID {$post_array->ID}: '{$event_date_end}'");
                }
            }
            
            $errors += $post_errors;
            $processed++;
        }

        // Send response with progress info
        $response = array(
            'success' => true,
            'batch_number' => $batch_number,
            'batch_size' => $batch_size,
            'processed' => $processed,
            'errors' => $errors,
            'total' => $total_count,
            'remaining' => max(0, $total_count - ($batch_number * $batch_size)),
            'progress' => min(100, round(($batch_number * $batch_size) / $total_count * 100, 2)),
            'message' => "Batch {$batch_number}: Processed {$processed} events" . ($errors > 0 ? " ({$errors} errors)" : "")
        );

        wp_send_json($response);
    }
}

$Listeo_Form_Editor = new Listeo_Forms_And_Fields_Editor();
