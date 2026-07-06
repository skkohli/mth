<?php

/**
 * Theme self-update against the PureThemes Updates Manager.
 *
 * Polls a static JSON manifest hosted by the licenser plugin's admin UI,
 * compares versions against wp_get_theme(), and injects the update row into
 * core's themes-update transient so WP's normal "Update Available" UI in
 * Dashboard › Updates handles the rest.
 */
if (!defined('ABSPATH')) exit;

class Listeo_Theme_Updater
{

    const MANIFEST_URL               = 'https://purethe.me/themes/listeo.json';
    const PROTECTED_UPDATE_CHECK_URL = 'https://purethe.me/wp-json/purethemes-license-proxy/v1/check-plugin-update';
    const SLUG                       = 'listeo'; // must match get_template() / wp_get_theme()
    const PROTECTED_PACKAGE_SLUG     = 'listeo';
    const LICENSE_OPTION             = 'Listeo_lic_Key';
    const CACHE_KEY                  = 'listeo_update_manifest';
    const ERROR_CACHE_KEY            = 'listeo_update_error';
    const CACHE_TTL                  = 12 * HOUR_IN_SECONDS;
    const ERROR_CACHE_TTL            = HOUR_IN_SECONDS;

    private $manifest_url;
    private $protected_update_check_url;
    private $cache_ttl;

    public function __construct()
    {
        $this->manifest_url = apply_filters('listeo_theme_update_manifest_url', self::MANIFEST_URL);
        $this->protected_update_check_url = apply_filters('listeo_theme_protected_update_check_url', self::PROTECTED_UPDATE_CHECK_URL);
        $this->cache_ttl = apply_filters('listeo_theme_update_cache_ttl', self::CACHE_TTL);

        add_filter('pre_set_site_transient_update_themes', [$this, 'inject_update']);
        add_filter('themes_api',                           [$this, 'theme_info'], 10, 3);
        add_filter('upgrader_pre_download',                 [$this, 'download_protected_package'], 10, 4);
        add_action('in_theme_update_message-' . self::SLUG, [$this, 'show_license_update_message'], 10, 2);
        // Reset update data after an install/update completes.
        add_action('upgrader_process_complete', [$this, 'flush_cache'], 10, 0);
    }

    /** Fetch and cache the manifest. Returns array or null on failure. */
    private function get_manifest()
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached))          return $cached;
        if ($cached === 'fail')         return null; // negative cache

        $response = wp_remote_get($this->manifest_url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient(self::CACHE_KEY, 'fail', HOUR_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['version']) || empty($data['download_url'])) {
            set_transient(self::CACHE_KEY, 'fail', HOUR_IN_SECONDS);
            return null;
        }

        set_transient(self::CACHE_KEY, $data, $this->cache_ttl);
        return $data;
    }

    public function flush_cache()
    {
        self::clear_update_cache();
    }

    public static function clear_update_cache()
    {
        delete_transient(self::CACHE_KEY);
        delete_transient(self::ERROR_CACHE_KEY);
        delete_site_transient('update_themes');

        if (function_exists('wp_clean_themes_cache')) {
            wp_clean_themes_cache();
        }
    }

    /** Inject an update row into the themes-update transient. */
    public function inject_update($transient)
    {
        if (empty($transient->checked)) return $transient;

        if (isset($_GET['force-check'])) {
            delete_transient(self::CACHE_KEY);
            $this->clear_update_error();
        }

        $manifest  = $this->get_manifest();
        if (!$manifest) return $transient;

        $installed = wp_get_theme(self::SLUG)->get('Version');
        if (version_compare($manifest['version'], $installed, '<=')) {
            return $transient;
        }

        $package_url = $this->get_package_url($manifest);

        $transient->response[self::SLUG] = [
            'theme'        => self::SLUG,
            'new_version'  => $manifest['version'],
            'url'          => $manifest['homepage']     ?? '',
            'package'      => $package_url,
            'requires'     => $manifest['requires']     ?? '',
            'requires_php' => $manifest['requires_php'] ?? '',
        ];
        return $transient;
    }

    /** Populate the "View version details" modal. */
    public function theme_info($result, $action, $args)
    {
        if ($action !== 'theme_information' || empty($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }
        $manifest = $this->get_manifest();
        if (!$manifest) return $result;

        $info = new stdClass();
        $info->name          = $manifest['name']         ?? 'Listeo';
        $info->slug          = self::SLUG;
        $info->version       = $manifest['version'];
        $info->requires      = $manifest['requires']     ?? '';
        $info->tested        = $manifest['tested']       ?? '';
        $info->requires_php  = $manifest['requires_php'] ?? '';
        $info->homepage      = $manifest['homepage']     ?? '';
        $info->download_link = $this->get_package_url($manifest);
        $info->last_updated  = $manifest['last_updated'] ?? '';
        $info->sections      = $manifest['sections']     ?? [];
        return $info;
    }

    private function get_package_url($manifest)
    {
        $package_url = !empty($manifest['download_url']) ? $this->normalize_download_url($manifest['download_url']) : '';
        if (empty($package_url)) {
            return '';
        }

        if ($this->is_protected_download_url($package_url)) {
            return $this->resolve_protected_download_url($package_url, $manifest['version']);
        }

        $this->clear_update_error();

        return $package_url;
    }

    private function normalize_download_url($url)
    {
        $url = esc_url_raw($url);

        return preg_replace('~^(https?://purethe\.me)/index\.php/wp-json/~', '$1/wp-json/', $url);
    }

    private function is_protected_download_url($url)
    {
        return is_array($this->parse_protected_download_url($url));
    }

    private function parse_protected_download_url($url)
    {
        $paths = [];
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!empty($path)) {
            $paths[] = $path;
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (!empty($query)) {
            parse_str($query, $query_args);
            if (!empty($query_args['rest_route'])) {
                $paths[] = $query_args['rest_route'];
            }
        }

        foreach ($paths as $candidate_path) {
            if (preg_match('~/purethemes-license-proxy/v1/download-package/([a-z0-9-]+)/([^/?#]+)~', $candidate_path, $matches)) {
                return [
                    'package' => sanitize_key(rawurldecode($matches[1])),
                    'version' => sanitize_text_field(rawurldecode($matches[2])),
                ];
            }
        }

        return null;
    }

    private function resolve_protected_download_url($manifest_download_url, $version)
    {
        $package = $this->parse_protected_download_url($manifest_download_url);

        if (
            empty($package['package'])
            || $package['package'] !== self::PROTECTED_PACKAGE_SLUG
            || empty($package['version'])
            || $package['version'] !== $version
        ) {
            $this->set_update_error('invalid_manifest_download_url', $version);
            return '';
        }

        $license_key = sanitize_text_field((string) get_option(self::LICENSE_OPTION, ''));
        if (empty($license_key)) {
            $this->set_update_error('license_missing', $version);
            return '';
        }

        $response = wp_remote_post($this->protected_update_check_url, [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'package'               => $package['package'],
                'version'               => $version,
                'manifest_download_url' => $manifest_download_url,
                'license_key'           => $license_key,
            ]),
            'timeout'   => 10,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            $this->set_update_error('update_check_failed', $version);
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200) {
            $this->set_update_error(isset($body['code']) ? $body['code'] : 'update_check_failed', $version);
            return '';
        }

        if (
            !is_array($body)
            || empty($body['remote_updates_allowed'])
            || empty($body['download_url'])
        ) {
            $this->set_update_error(isset($body['code']) ? $body['code'] : 'update_package_unavailable', $version);
            return '';
        }

        $this->clear_update_error();

        return $this->normalize_download_url($body['download_url']);
    }

    private function set_update_error($code, $version = '')
    {
        $code = is_scalar($code) ? sanitize_key((string) $code) : 'update_package_unavailable';

        set_transient(self::ERROR_CACHE_KEY, [
            'code'    => $code,
            'message' => $this->get_public_update_error_message($code),
            'version' => sanitize_text_field($version),
        ], self::ERROR_CACHE_TTL);
    }

    private function clear_update_error()
    {
        delete_transient(self::ERROR_CACHE_KEY);
    }

    private function get_update_error()
    {
        $error = get_transient(self::ERROR_CACHE_KEY);

        return is_array($error) ? $error : null;
    }

    private function get_public_update_error_message($code)
    {
        $code = is_scalar($code) ? sanitize_key((string) $code) : '';

        switch ($code) {
            case 'license_missing':
                return __('Please activate your Listeo license to install this update.', 'listeo');

            case 'license_not_found':
                return __('Your license could not be verified. Please check your license key or contact PureThemes support.', 'listeo');

            case 'license_inactive':
                return __('Your license is not active. Please reactivate your license or contact PureThemes support.', 'listeo');

            case 'product_mismatch':
                return __('This license is not valid for Listeo updates. Please use the correct license key or contact PureThemes support.', 'listeo');

            case 'rate_limit_exceeded':
                return __('Too many update checks. Please wait about one hour and try again.', 'listeo');

            case 'invalid_download_token':
            case 'download_token_expired':
            case 'rest_missing_callback_param':
                return __('The secure download link expired. Please click Check again and retry the update.', 'listeo');

            default:
                return __('The update package is temporarily unavailable. Please contact PureThemes support.', 'listeo');
        }
    }

    public function download_protected_package($reply, $package, $upgrader, $hook_extra)
    {
        if (false !== $reply || empty($package)) {
            return $reply;
        }

        $protected_package = $this->parse_protected_download_url($package);
        if (
            empty($protected_package['package'])
            || $protected_package['package'] !== self::PROTECTED_PACKAGE_SLUG
            || (!empty($hook_extra['theme']) && $hook_extra['theme'] !== self::SLUG)
        ) {
            return $reply;
        }

        $download_file = download_url($package, 300);
        if (!is_wp_error($download_file)) {
            $this->clear_update_error();
            return $download_file;
        }

        $code = $this->get_download_error_code($download_file);
        $this->set_update_error($code, !empty($protected_package['version']) ? $protected_package['version'] : '');

        return new WP_Error('download_failed', $this->get_public_update_error_message($code));
    }

    private function get_download_error_code($error)
    {
        $data = $error->get_error_data();
        if (is_array($data) && !empty($data['body'])) {
            $body = json_decode($data['body'], true);
            if (is_array($body) && !empty($body['code'])) {
                return $body['code'];
            }
        }

        if ($error->get_error_code() === 'http_no_url') {
            return 'update_package_unavailable';
        }

        return 'update_check_failed';
    }

    public function show_license_update_message($theme, $response)
    {
        if (!empty($response['package'])) {
            return;
        }

        $message = __('Activate a valid Listeo license to enable automatic updates.', 'listeo');
        $update_error = $this->get_update_error();
        if (
            !empty($update_error['message'])
            && (empty($update_error['version']) || empty($response['new_version']) || $update_error['version'] === $response['new_version'])
        ) {
            $message = $update_error['message'];
        }

        echo '<br><span class="listeo-update-license-message"><strong>' . esc_html__('Automatic update unavailable:', 'listeo') . '</strong> ';
        echo esc_html($message) . ' ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=listeo_license')) . '">' . esc_html__('Manage your Listeo license.', 'listeo') . '</a>';
        echo '</span>';
    }
}

new Listeo_Theme_Updater();
