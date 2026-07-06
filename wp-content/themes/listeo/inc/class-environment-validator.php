<?php
/**
 * Environment validation and configuration handler
 *
 * @package Listeo
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Listeo_Environment_Validator
 * Handles environment detection and validation routines
 */
class Listeo_Environment_Validator {

    /**
     * @var array Runtime configuration
     */
    private static $config = null;

    /**
     * Get runtime configuration
     * @return array
     */
    private static function get_config() {
        if (self::$config === null) {
            self::$config = array(
                // Extension patterns (checked via suffix match)
                'ext' => array(
                    '.dev',
                    '.dev.cc',
                    '.test',
                    '.local',
                    '.localhost',
                    '.staging',
                    '.example',
                    '.invalid',
                ),
                // Prefix patterns (checked via prefix match)
                'pfx' => array(
                    'local.',
                    'dev.',
                    'test.',
                    'stage.',
                    'staging.',
                ),
                // Contains patterns (checked via strpos)
                'cnt' => array(
                    '.myftpupload.com',
                    '.cloudwaysapps.com',
                    '.wpsandbox.pro',
                    '.ngrok.io',
                    '.ngrok-free.app',
                    '.mystagingwebsite.com',
                    '.tempurl.host',
                    '.wpmudev.host',
                    '.websitepro-staging.com',
                    '.websitepro.hosting',
                    '.instawp.xyz',
                    '.wpengine.com',
                    '.wpenginepowered.com',
                    '.pantheonsite.io',
                    '.kinsta.com',
                    '.kinsta.cloud',
                    '.10web.site',
                    '.10web.cloud',
                    '.localsite.io',
                    '.local.host',
                ),
                // Regex patterns
                'rgx' => array(
                    '/^staging\d+\./i',
                    '/^dev-.*\.pantheonsite\.io$/i',
                    '/^test-.*\.pantheonsite\.io$/i',
                    '/^staging-.*\.kinsta\.(com|cloud)$/i',
                    '/.*-dev\.10web\.(site|cloud)$/i',
                    '/^stg\d*\./i',
                    '/^stage\d+\./i',
                    '/^preprod\./i',
                    '/^preview\./i',
                ),
                // Local identifiers
                'lcl' => array('localhost', '127.0.0.1', '::1'),
            );
        }
        return self::$config;
    }

    /**
     * Validate environment type
     * @return bool
     */
    public static function validate() {
        $url = function_exists('site_url') ? site_url() : '';
        $host = parse_url($url, PHP_URL_HOST);

        if (empty($host)) {
            return false;
        }

        $h = strtolower($host);
        $c = self::get_config();

        // Check extensions
        foreach ($c['ext'] as $e) {
            if (substr($h, -strlen($e)) === $e) {
                return true;
            }
        }

        // Check prefixes
        foreach ($c['pfx'] as $p) {
            if (strpos($h, $p) === 0) {
                return true;
            }
        }

        // Check contains
        foreach ($c['cnt'] as $n) {
            if (strpos($h, $n) !== false) {
                return true;
            }
        }

        // Check regex
        foreach ($c['rgx'] as $r) {
            if (preg_match($r, $h)) {
                return true;
            }
        }

        // Check local
        if (in_array($h, $c['lcl'])) {
            return true;
        }

        // Check private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (
                strpos($host, '192.168.') === 0 ||
                strpos($host, '10.') === 0 ||
                preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get cache duration in seconds
     * @return int
     */
    public static function get_cache_ttl() {
        return 12 * HOUR_IN_SECONDS;
    }
}
