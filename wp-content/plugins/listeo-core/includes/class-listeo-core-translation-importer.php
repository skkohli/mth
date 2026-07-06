<?php
/**
 * Listeo Core Translation Importer
 *
 * Integrated translation importer functionality for Listeo Core
 * Safely replaces the standalone translation-importer plugin
 *
 * @package Listeo Core
 * @since 1.9.45
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Safety check: Don't load if standalone translation importer plugin is active
if (class_exists('PT_Translation_Importer_Core') || class_exists('PT_Admin_Page')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>Listeo Core:</strong> <?php _e('Conflict detected! Please deactivate the standalone "Translation Importer" plugin as this functionality is now integrated into Listeo Core.', 'listeo_core'); ?></p>
        </div>
        <?php
    });
    return; // Stop loading this file
}

/**
 * Listeo Core Translation Importer Class
 */
class Listeo_Core_Translation_Importer
{
    /**
     * The single instance of the class.
     *
     * @var self
     * @since  1.9.45
     */
    private static $_instance = null;

    private $active_theme_slug;
    private $theme_config;

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
        if (class_exists('PT_Translation_Importer_Core') || 
            class_exists('PT_Admin_Page') ||
            (function_exists('is_plugin_active') && is_plugin_active('translation-importer/translation-importer.php'))) {
            add_action('admin_notices', array($this, 'show_conflict_notice'));
            return;
        }

        // Initialize theme detection
        $this->init_theme_detection();

        add_action('admin_post_listeo_import_language', array($this, 'handle_import_request'));
        add_action('wp_ajax_listeo_check_language_availability', array($this, 'ajax_check_availability'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Show conflict notice if standalone plugin is detected
     */
    public function show_conflict_notice()
    {
        ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Listeo Core - Conflict Detected!', 'listeo_core'); ?></strong></p>
            <p><?php _e('The standalone "Translation Importer" plugin is still active. Please deactivate it as this functionality is now built into Listeo Core.', 'listeo_core'); ?></p>
            <p><?php _e('Go to Plugins → Installed Plugins and deactivate "Translation Importer" to use the integrated version.', 'listeo_core'); ?></p>
        </div>
        <?php
    }

    /**
     * Initialize theme detection
     */
    private function init_theme_detection()
    {
        $current_theme = wp_get_theme();
        
        // Get both stylesheet (child theme) and template (parent theme) directory names
        $stylesheet_slug = $current_theme->get_stylesheet();
        $template_slug = $current_theme->get_template();
        
        $supported_themes = ['listeo', 'workscout'];
        $this->active_theme_slug = null;
        
        // Check both stylesheet and template slugs (case-insensitive)
        foreach ($supported_themes as $supported_theme) {
            if (stripos($template_slug, $supported_theme) !== false || 
                stripos($stylesheet_slug, $supported_theme) !== false ||
                strtolower($template_slug) === $supported_theme ||
                strtolower($stylesheet_slug) === $supported_theme) {
                $this->active_theme_slug = $supported_theme;
                break;
            }
        }
        
        // Additional fallback: check theme name
        if (!$this->active_theme_slug) {
            $theme_name = strtolower($current_theme->get('Name'));
            foreach ($supported_themes as $supported_theme) {
                if (stripos($theme_name, $supported_theme) !== false) {
                    $this->active_theme_slug = $supported_theme;
                    break;
                }
            }
        }

        $this->theme_config = $this->get_theme_config($this->active_theme_slug);
    }

    /**
     * Get theme configuration
     */
    private function get_theme_config($theme_slug)
    {
        if (!$theme_slug) {
            return null;
        }

        $all_configs = [
            'workscout' => [
                'name' => 'WorkScout',
                'url' => 'https://purethemes.net/workscout-theme-translations/',
                'support_url' => 'https://themeforest.net/item/workscout-job-board-wordpress-theme/13591801/support/contact',
                'text_domains' => [
                    'workscout'                     => 'theme',
                    'workscout_core'                => 'plugin',
                    'workscout_elementor'           => 'plugin',
                    'workscout-freelancer'          => 'plugin',
                    'wp-job-manager'                => 'plugin',
                    'wp-job-manager-alerts'         => 'plugin',
                    'wp-job-manager-applications'   => 'plugin',
                    'wp-job-manager-bookmarks'      => 'plugin',
                    'wp-job-manager-resumes'        => 'plugin',
                    'wp-job-manager-wc-paid-listings' => 'plugin',
                ]
            ],
            'listeo' => [
                'name' => 'Listeo',
                'url' => 'https://purethemes.net/listeo-theme-translations/',
                'support_url' => 'https://themeforest.net/item/listeo-directory-listings-wordpress-theme/23239259/support/contact',
                'text_domains' => [
                    'listeo'            => 'theme',
                    'listeo_core'       => 'plugin',
                    'listeo_elementor'  => 'plugin',
                    'ai-chat-search'    => 'plugin',
                    'listeo-poi'        => 'plugin',
                ]
            ]
        ];

        return $all_configs[$theme_slug] ?? null;
    }

    /**
     * Enqueue scripts for the translation importer page
     */
    public function enqueue_scripts($hook)
    {
        if ('settings_page_listeo-translation-importer' !== $hook || !$this->active_theme_slug) {
            return;
        }
        
        wp_enqueue_style('dashicons');
        
        wp_localize_script('jquery-core', 'listeoTranslationImporter', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('listeo_translation_importer_nonce'),
            'site_locale' => get_locale(),
        ]);
    }

    /**
     * Render the translation importer page
     */
    public function render_import_page()
    {
        // Final safety check
        if ((class_exists('PT_Translation_Importer_Core') || class_exists('PT_Admin_Page')) && !defined('LISTEO_TRANSLATION_IMPORTER_INTEGRATED')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Cannot load translation importer due to plugin conflict. Please deactivate the standalone Translation Importer plugin.', 'listeo_core') . '</p></div></div>';
            return;
        }

        ?>
        <style>
            #listeo-translation-card { margin-top: 20px; max-width: 600px; border-radius: 5px; box-shadow: 0 3px 10px #00000010; border: none; padding: 20px 15px 0 15px; }
            #listeo-translation-card .inside { padding: 1px 16px 16px; }
            #listeo-translation-card h2.title { font-size: 1.3em; padding: 12px 16px; margin: 0; display: flex; align-items: center; }
            #listeo-translation-card h2.title .dashicons { margin-right: 8px; }
            #listeo-translation-card p { font-size: 14px; }
            #listeo-translation-card select { padding: 3px 10px; margin-right: 4px; width: 100%; max-width: 100%; }
            .listeo-notice { border-left-width: 4px; border-left-style: solid; padding: 10px 12px; margin: 15px 0; box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); }
            .listeo-notice p { margin: 0; padding: 0; }
            .listeo-notice p + p { margin-top: 8px; }
            .listeo-notice.listeo-notice-success { border-left-color: #46b450; background: #46b45010; }
            .listeo-notice.listeo-notice-warning { border-left-color: #ffb900; background: #ffb90010; }
            .listeo-notice.listeo-notice-error { border-left-color: #dc3232; background: #dc323210; }
            .listeo-notice.listeo-notice-info { border-left-color: #72aee6; background: #72aee610; }
            .listeo-spinner { float: none; vertical-align: middle; margin-top: 3px; margin-left: 5px; }
        </style>
        
        <div class="wrap">
            <h1>
                <?php _e('Translation Importer', 'listeo_core'); ?>
            </h1>
            
            <?php if (!$this->active_theme_slug) : ?>
                <div class="notice notice-error card">
                    <p><?php _e('This functionality requires the WorkScout or Listeo theme to be active.', 'listeo_core'); ?></p>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <div id="listeo-translation-card" class="card">
                <h2 class="title">
                    <span class="dashicons dashicons-translation"></span>
                    <?php printf(__('Install Translations for %s', 'listeo_core'), esc_html($this->theme_config['name'])); ?>
                </h2>
                <div class="inside">
                    
                    <div id="listeo-import-results"><?php $this->display_admin_notices(); ?></div>

                    <p><?php _e('Select a language from the list below. The importer will check if translations are available and then download and install all necessary files for your theme and its plugins.', 'listeo_core'); ?></p>

                    <form id="listeo-translation-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="listeo_import_language">
                        <?php wp_nonce_field('listeo_import_action', 'listeo_translation_nonce'); ?>
                        
                        <div style="display: flex; align-items: center; margin-top: 20px;">
                            <select id="language_locale" name="language_locale" style="min-width: 350px;">
                                <?php $this->render_language_dropdown(); ?>
                            </select>
                            <span id="listeo-spinner" class="spinner listeo-spinner"></span>
                        </div>
                        
                        <div id="listeo-available-notice" class="listeo-notice listeo-notice-success" style="display: none;">
                            <p><?php _e('Translations for this language are available. Click the button below to install them.', 'listeo_core'); ?></p>
                        </div>
                        
                        <div id="listeo-english-notice" class="listeo-notice listeo-notice-info" style="display: none;">
                            <p><?php _e('English is the default language of the theme and does not need to be installed.', 'listeo_core'); ?></p>
                        </div>
                        
                        <div id="listeo-mismatch-notice" class="listeo-notice listeo-notice-warning" style="display: none;">
                            <p>
                                <strong><?php _e('Potential Mismatch Detected!', 'listeo_core'); ?></strong><br>
                                <?php _e('Your site language is', 'listeo_core'); ?> <code id="listeo-site-locale-val"></code>, <?php _e('but the available version is', 'listeo_core'); ?> <code id="listeo-selected-locale-val"></code>.
                            </p>
                             <p>
                                <?php _e('For the translation to work correctly, you can change your site language in', 'listeo_core'); ?>
                                <a href="<?php echo esc_url(admin_url('options-general.php')); ?>" target="_blank"><?php _e('Settings → General', 'listeo_core'); ?></a>.
                            </p>
                            <p>
                                <?php printf(
                                    __('Alternatively, if you need the specific %s version, our team can prepare it for you. Please <a href="%s" target="_blank" rel="noopener">submit a support ticket</a> to make a request.', 'listeo_core'),
                                    '<code id="listeo-needed-locale-val"></code>',
                                    esc_url($this->theme_config['support_url'])
                                ); ?>
                            </p>
                        </div>
                        
                        <div id="listeo-unavailable-notice" class="listeo-notice listeo-notice-warning" style="display: none;">
                            <p><?php _e('A translation for the selected language is not yet available.', 'listeo_core'); ?></p>
                            <p>
                                <?php printf(
                                    __('However, our team can prepare it for you. Please <a href="%s" target="_blank" rel="noopener">submit a support ticket</a> to make a request.', 'listeo_core'),
                                    esc_url($this->theme_config['support_url'])
                                ); ?>
                            </p>
                        </div>

                        <div id="listeo-current-language-notice" class="listeo-notice listeo-notice-info" style="margin-top: 20px;">
                            <p>
                                <strong><?php _e('Current Website Language:', 'listeo_core'); ?></strong> 
                                <?php 
                                $current_locale = get_locale();
                                $languages = $this->get_all_languages();
                                $current_language_name = isset($languages[$current_locale]) ? $languages[$current_locale] : $current_locale;
                                echo esc_html($current_language_name . ' (' . $current_locale . ')');
                                ?>
                            </p>
                            <p style="font-size: 13px; margin-top: 5px; opacity: 0.8;">
                                <?php _e('You can change your site language in', 'listeo_core'); ?> 
                                <a href="<?php echo esc_url(admin_url('options-general.php')); ?>" target="_blank"><?php _e('Settings → General', 'listeo_core'); ?></a>.
                            </p>
                        </div>

                        <?php submit_button(__('Download and Install', 'listeo_core'), 'primary', 'submit', true, ['disabled' => 'disabled']); ?>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('language_locale');
            const spinner = document.getElementById('listeo-spinner');
            const unavailableNotice = document.getElementById('listeo-unavailable-notice');
            const availableNotice = document.getElementById('listeo-available-notice');
            const englishNotice = document.getElementById('listeo-english-notice');
            const mismatchNotice = document.getElementById('listeo-mismatch-notice');
            const submitButton = document.getElementById('submit');
            const resultsDiv = document.getElementById('listeo-import-results');
            const siteLocale = listeoTranslationImporter.site_locale;

            // Define the change event handler
            select.addEventListener('change', function() {
                const locale = this.value;
                
                // Reset UI state
                submitButton.disabled = true;
                unavailableNotice.style.display = 'none';
                availableNotice.style.display = 'none';
                englishNotice.style.display = 'none';
                mismatchNotice.style.display = 'none';
                resultsDiv.style.display = 'none'; 

                if (!locale) { return; }
                
                // Handle English Variants
                if (locale.startsWith('en_')) {
                    englishNotice.style.display = 'block';
                    return;
                }
                
                spinner.classList.add('is-active');

                // Check Availability via AJAX
                jQuery.post(listeoTranslationImporter.ajax_url, {
                    action: 'listeo_check_language_availability',
                    _ajax_nonce: listeoTranslationImporter.nonce,
                    locale: locale,
                }).done(function(response) {
                    if (response.success && response.data.available) {
                        // File exists on the server. Check for mismatch.
                        const siteBaseLang = siteLocale.split('_')[0];
                        const selectedBaseLang = locale.split('_')[0];
                        
                        if (locale !== siteLocale && siteBaseLang === selectedBaseLang) {
                            // Mismatch found AND file is available. Show the specific mismatch notice.
                            mismatchNotice.querySelector('#listeo-site-locale-val').textContent = siteLocale;
                            mismatchNotice.querySelector('#listeo-selected-locale-val').textContent = locale;
                            mismatchNotice.querySelector('#listeo-needed-locale-val').textContent = siteLocale;
                            mismatchNotice.style.display = 'block';
                        } else {
                            // No mismatch, everything is perfect. Show the standard available notice.
                            availableNotice.style.display = 'block';
                        }
                        submitButton.disabled = false; // Enable button in both "available" cases.
                    } else {
                        // File does NOT exist on the server. Just show the unavailable notice.
                        unavailableNotice.style.display = 'block';
                    }
                }).always(function() {
                    spinner.classList.remove('is-active');
                });
            });

            // Check if a results message is already visible from a redirect.
            if (resultsDiv.children.length === 0) {
                // If the results area is EMPTY, it's a fresh visit. Trigger the check for pre-selected language.
                if (select.value && select.value !== '') {
                    select.dispatchEvent(new Event('change'));
                }
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to check language availability
     */
    public function ajax_check_availability()
    {
        check_ajax_referer('listeo_translation_importer_nonce');
        
        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : '';
        if (empty($locale) || !$this->theme_config) { 
            wp_send_json_error(); 
        }
        
        $theme_text_domain = $this->active_theme_slug;
        $check_filename = "{$theme_text_domain}-{$locale}.mo";
        $url_to_check = $this->theme_config['url'] . 'mo/' . $check_filename;
        
        $response = wp_remote_head($url_to_check, ['timeout' => 10]);
        
        if (200 === wp_remote_retrieve_response_code($response)) {
            wp_send_json_success(['available' => true]);
        } else {
            wp_send_json_success(['available' => false]);
        }
    }

    /**
     * Handle form submission and import process
     */
    public function handle_import_request()
    {
        if (!isset($_POST['listeo_translation_nonce']) || !wp_verify_nonce($_POST['listeo_translation_nonce'], 'listeo_import_action')) {
            wp_die(__('Security check failed.', 'listeo_core'));
        }
        
        if (!current_user_can('manage_options')) { 
            wp_die(__('Permission denied.', 'listeo_core')); 
        }
        
        $locale = isset($_POST['language_locale']) ? sanitize_text_field($_POST['language_locale']) : '';
        
        if (empty($locale) || !$this->theme_config) {
            $this->set_admin_notice('error', __('Invalid request.', 'listeo_core'));
        } else {
            $result = $this->download_and_import_language($locale);
            $this->set_admin_notice($result['status'], $result['message']);
        }
        
        wp_safe_redirect(admin_url('options-general.php?page=listeo-translation-importer'));
        exit;
    }

    /**
     * Download and import language files
     */
    private function download_and_import_language($locale)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        WP_Filesystem();
        global $wp_filesystem;

        $dest_theme_dir = trailingslashit(WP_LANG_DIR) . 'themes/';
        $dest_plugin_dir = trailingslashit(WP_LANG_DIR) . 'plugins/';

        if (!$wp_filesystem->is_dir($dest_theme_dir) && !$wp_filesystem->mkdir($dest_theme_dir, FS_CHMOD_DIR)) {
            return ['status' => 'error', 'message' => '<strong>' . __('Critical Error:', 'listeo_core') . '</strong> ' . __('Could not create directory:', 'listeo_core') . ' <code>wp-content/languages/themes/</code>. ' . __('Please check folder permissions.', 'listeo_core')];
        }
        
        if (!$wp_filesystem->is_dir($dest_plugin_dir) && !$wp_filesystem->mkdir($dest_plugin_dir, FS_CHMOD_DIR)) {
            return ['status' => 'error', 'message' => '<strong>' . __('Critical Error:', 'listeo_core') . '</strong> ' . __('Could not create directory:', 'listeo_core') . ' <code>wp-content/languages/plugins/</code>. ' . __('Please check folder permissions.', 'listeo_core')];
        }

        $base_url = $this->theme_config['url'];
        $text_domains = $this->theme_config['text_domains'];
        $debug_log = [];
        
        foreach ($text_domains as $domain => $type) {
            foreach (['po', 'mo'] as $ext) {
                $filename = "{$domain}-{$locale}.{$ext}";
                $remote_url = "{$base_url}{$ext}/{$filename}";
                $this->process_file($filename, $remote_url, $type, $dest_theme_dir, $dest_plugin_dir, $debug_log);
            }
        }

        return $this->generate_final_report($debug_log);
    }

    /**
     * Process individual file download and installation
     */
    private function process_file($filename, $remote_url, $type, $dest_theme_dir, $dest_plugin_dir, &$debug_log)
    {
        global $wp_filesystem;
        
        $temp_file = download_url($remote_url, 15);

        if (is_wp_error($temp_file)) {
            $debug_log[] = ['file' => $filename, 'status' => 'not_found'];
            return;
        }

        $destination_path = ($type === 'theme') ? $dest_theme_dir . $filename : $dest_plugin_dir . $filename;

        if ($wp_filesystem->move($temp_file, $destination_path, true)) {
            $debug_log[] = ['file' => $filename, 'status' => 'success'];
        } else {
            $debug_log[] = ['file' => $filename, 'status' => 'move_failed'];
            $wp_filesystem->delete($temp_file);
        }
    }

    /**
     * Generate final import report
     */
    private function generate_final_report($debug_log)
    {
        $success_count = 0;
        $not_found_count = 0;
        $failed_count = 0;
        $html_report = '<ul style="margin: 0; list-style-type: none; font-family: monospace; font-size: 13px;">';

        foreach($debug_log as $log) {
            switch($log['status']) {
                case 'success':
                    $success_count++;
                    $html_report .= '<li><span style="color: #46b450; margin-right: 5px;">✔</span>' . esc_html($log['file']) . '</li>';
                    break;
                case 'not_found':
                    $not_found_count++;
                    $html_report .= '<li><span style="color: #ffb900; margin-right: 5px;">!</span>' . esc_html($log['file']) . ' (' . __('Not found on server', 'listeo_core') . ')</li>';
                    break;
                case 'move_failed':
                    $failed_count++;
                    $html_report .= '<li><span style="color: #dc3232; margin-right: 5px;">✖</span>' . esc_html($log['file']) . ' (' . __('Move failed', 'listeo_core') . ')</li>';
                    break;
            }
        }
        $html_report .= '</ul>';

        if ($success_count > 0) {
            $status = ($failed_count > 0) ? 'warning' : 'success';
            $message = sprintf('<strong>' . __('Import Complete:', 'listeo_core') . '</strong> ' . __('%d files were successfully installed.', 'listeo_core'), $success_count);
            if ($failed_count > 0) {
                 $message .= ' ' . sprintf(__('%d files failed to be moved.', 'listeo_core'), $failed_count);
            }
             if ($not_found_count > 0) {
                 $message .= ' ' . sprintf(__('%d files could not be found on the server.', 'listeo_core'), $not_found_count);
            }
            $message .= '<hr style="margin: 10px 0; border: 0; border-top: 1px solid #ddd;">' . $html_report;
            return ['status' => $status, 'message' => $message];
        }

        return ['status' => 'error', 'message' => '<strong>' . __('Import Failed.', 'listeo_core') . '</strong> ' . __('No matching files were found on the server or they could not be installed. Please review the report below.', 'listeo_core') . '<hr>' . $html_report];
    }

    /**
     * Render language dropdown
     */
    private function render_language_dropdown()
    {
        $languages = $this->get_all_languages();
        $current_locale = get_locale();
        
        echo '<option value="">' . __('-- Select a Language --', 'listeo_core') . '</option>';
        foreach ($languages as $locale => $name) {
            $selected = ($locale === $current_locale) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($locale) . '" ' . $selected . '>' . esc_html($name . ' (' . $locale . ')') . '</option>';
        }
    }

    /**
     * Get comprehensive list of WordPress locales
     * Returns all available WordPress languages (like regions importer)
     */
    private function get_all_languages()
    {
        // Comprehensive language list for mapping locale codes to names
        $all_languages = [
            'af' => 'Afrikaans', 'ar' => 'العربية', 'ary' => 'العربية المغربية', 'as' => 'অসমীয়া',
            'az' => 'Azərbaycan dili', 'azb' => 'گؤنئی آذربایجان', 'bel' => 'Беларуская мова',
            'bg_BG' => 'Български', 'bn_BD' => 'বাংলা', 'bo' => 'བོད་ཡིག', 'bs_BA' => 'Bosanski',
            'ca' => 'Català', 'ceb' => 'Cebuano', 'cs_CZ' => 'Čeština‎', 'cy' => 'Cymraeg',
            'da_DK' => 'Dansk', 'de_AT' => 'Deutsch (Österreich)', 'de_CH' => 'Deutsch (Schweiz)',
            'de_DE' => 'Deutsch', 'de_DE_formal' => 'Deutsch (Sie)', 'dzo' => 'རྫོང་ཁ',
            'el' => 'Ελληνικά', 'en_AU' => 'English (Australia)', 'en_CA' => 'English (Canada)',
            'en_GB' => 'English (UK)', 'en_NZ' => 'English (New Zealand)', 'en_ZA' => 'English (South Africa)',
            'eo' => 'Esperanto', 'es_AR' => 'Español de Argentina', 'es_CL' => 'Español de Chile',
            'es_CO' => 'Español de Colombia', 'es_CR' => 'Español de Costa Rica', 'es_DO' => 'Español de República Dominicana',
            'es_EC' => 'Español de Ecuador', 'es_ES' => 'Español', 'es_GT' => 'Español de Guatemala',
            'es_MX' => 'Español de México', 'es_PE' => 'Español de Perú', 'es_PR' => 'Español de Puerto Rico',
            'es_VE' => 'Español de Venezuela', 'et' => 'Eesti', 'eu' => 'Euskara', 'fa_AF' => 'فارسی (افغانستان)',
            'fa_IR' => 'فارسی', 'fi' => 'Suomi', 'fo' => 'Føroyskt', 'fr_BE' => 'Français de Belgique',
            'fr_CA' => 'Français du Canada', 'fr_FR' => 'Français', 'fur' => 'Friulian', 'gd' => 'Gàidhlig',
            'gl_ES' => 'Galego', 'gu' => 'ગુજરાતી', 'haz' => 'هزاره گی', 'he_IL' => 'עִבְרִית',
            'hi_IN' => 'हिन्दी', 'hr' => 'Hrvatski', 'hsb' => 'Hornjoserbsce', 'hu_HU' => 'Magyar',
            'hy' => 'Հայերեն', 'id_ID' => 'Bahasa Indonesia', 'is_IS' => 'Íslenska', 'it_IT' => 'Italiano',
            'ja' => '日本語', 'jv_ID' => 'Basa Jawa', 'ka_GE' => 'ქართული', 'kab' => 'Taqbaylit',
            'kk' => 'Қазақ тілі', 'km' => 'ភាសាខ្មែរ', 'kn' => 'ಕನ್ನಡ', 'ko_KR' => '한국어',
            'ckb' => 'کوردی‎', 'lo' => 'ພາສາລາວ', 'lt_LT' => 'Lietuvių kalba', 'lv' => 'Latviešu valoda',
            'mk_MK' => 'Македонски јазик', 'ml_IN' => 'മലയാളം', 'mn' => 'Монгол', 'mr' => 'मराठी',
            'ms_MY' => 'Bahasa Melayu', 'my_MM' => 'ဗမာစာ', 'nb_NO' => 'Norsk bokmål', 'ne_NP' => 'नेपाली',
            'nl_BE' => 'Nederlands (België)', 'nl_NL' => 'Nederlands', 'nl_NL_formal' => 'Nederlands (Formeel)',
            'nn_NO' => 'Norsk nynorsk', 'oci' => 'Occitan', 'pa_IN' => 'ਪੰਜਾਬੀ', 'pl_PL' => 'Polski',
            'ps' => 'پښتو', 'pt_AO' => 'Português de Angola', 'pt_BR' => 'Português do Brasil',
            'pt_PT' => 'Português', 'rhg' => 'Ruáinga', 'ro_RO' => 'Română', 'ru_RU' => 'Русский',
            'sah' => 'Сахалыы', 'si_LK' => 'සිංහල', 'sk_SK' => 'Slovenčina', 'sl_SI' => 'Slovenščina',
            'sq' => 'Shqip', 'sr_RS' => 'Српски језик', 'sv_SE' => 'Svenska', 'szl' => 'Ślōnskŏ gŏdka',
            'ta_IN' => 'தமிழ்', 'ta_LK' => 'தமிழ்', 'te' => 'తెలుగు', 'th' => 'ไทย',
            'tl' => 'Tagalog', 'tr_TR' => 'Türkçe', 'tt_RU' => 'Татарча', 'tah' => 'Reo Tahiti',
            'ug_CN' => 'ئۇيغۇرچە', 'uk' => 'Українська', 'ur' => 'اردو', 'uz_UZ' => 'O\'zbekcha',
            'vi' => 'Tiếng Việt', 'zh_CN' => '简体中文', 'zh_HK' => '香港中文版', 'zh_TW' => '繁體中文'
        ];
        
        // Sort by language name for better user experience
        asort($all_languages);
        
        return $all_languages;
    }

    /**
     * Set admin notice
     */
    private function set_admin_notice($type, $message)
    {
        set_transient('listeo_translation_importer_admin_notice', ['type' => $type, 'message' => $message], 30);
    }

    /**
     * Display admin notices
     */
    private function display_admin_notices()
    {
        $notice = get_transient('listeo_translation_importer_admin_notice');
        if ($notice) {
            $class = 'listeo-notice listeo-notice-' . esc_attr($notice['type']);
            printf('<div class="%s">%s</div>', $class, wp_kses_post($notice['message'], ['p' => [], 'ul' => ['style' => true], 'li' => ['style' => true], 'span' => ['style' => true], 'hr' => ['style' => true], 'strong' => [], 'br' => [], 'code' => [], 'a' => ['href' => true, 'target' => true, 'rel' => true]]));
            delete_transient('listeo_translation_importer_admin_notice');
        }
    }
}

// Define constant to indicate integrated version is loaded
if (!defined('LISTEO_TRANSLATION_IMPORTER_INTEGRATED')) {
    define('LISTEO_TRANSLATION_IMPORTER_INTEGRATED', true);
}
