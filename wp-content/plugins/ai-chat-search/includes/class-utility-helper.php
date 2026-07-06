<?php
/**
 * Utility Helper Class
 * 
 * Contains mathematical calculations and utility functions
 * 
 * @package Listeo_AI_Search
 * @since 1.0.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Utility_Helper {

    /**
     * Resolve the effective embedding provider from the stored embedding model.
     * OpenRouter routes models from multiple vendors, so we detect the underlying
     * provider from the model slug to apply correct similarity thresholds.
     *
     * @param string|null $embedding_model Override model slug, or null to read from options
     * @return string 'openai', 'gemini', or 'mistral'
     */
    public static function get_embedding_provider($embedding_model = null) {
        if ($embedding_model === null) {
            if (class_exists('Listeo_AI_Provider')) {
                $provider = new Listeo_AI_Provider();
                $embedding_model = $provider->get_embedding_model();
            } else {
                $embedding_model = get_option('listeo_ai_embedding_model', '');
            }
        }

        if (empty($embedding_model)) {
            return get_option('listeo_ai_search_provider', 'openai');
        }

        if (strpos($embedding_model, 'gemini') !== false) {
            return 'gemini';
        }
        if (strpos($embedding_model, 'mistral') !== false) {
            return 'mistral';
        }
        return 'openai';
    }
    
    /**
     * Transform similarity score to user-friendly percentage
     *
     * Applies provider-specific normalization to account for different
     * similarity distributions between OpenAI and Gemini embeddings.
     *
     * MAPPING TABLES:
     *
     * Gemini - Linear:
     * | Raw   | Display |
     * |-------|---------|
     * | 0.50  | 50%     |
     * | 0.70  | 70%     |
     * | 0.85  | 85%     |
     * | 1.00  | 100%    |
     *
     * Mistral - Compressed below 0.85 (high noise floor):
     * | Raw   | Display |
     * |-------|---------|
     * | 0.98  | 98%     | (linear zone)
     * | 0.85  | 85%     | (threshold)
     * | 0.80  | 76%     |
     * | 0.70  | 57%     |
     * | 0.60  | 39%     |
     * | 0.50  | 20%     |
     * | 0.40  | 16%     |
     *
     * OpenAI - Threshold-based:
     * | Raw   | Display |
     * |-------|---------|
     * | 0.30  | 39%     |
     * | 0.40  | 52%     |
     * | 0.49  | 64%     |
     * | 0.50  | 70%     |
     * | 0.60  | 75%     |
     * | 0.70  | 80%     |
     * | 0.80  | 85%     |
     * | 0.90  | 90%     |
     * | 1.00  | 95%     |
     *
     * @param float $raw_similarity Raw cosine similarity score
     * @param string|null $provider Optional provider override ('openai' or 'gemini')
     * @param string|null $post_type Optional post type (kept for backwards compatibility)
     * @return int User-friendly percentage
     */
    public static function transform_similarity_to_percentage($raw_similarity, $provider = null, $post_type = null) {
        // Resolve provider from embedding model, not AI provider
        if ($provider === null) {
            $provider = self::get_embedding_provider();
        }

        if ($provider === 'gemini') {
            // Gemini: curved mapping (0.50 raw → 75% display)
            if ($raw_similarity >= 0.50) {
                // 0.50 → 75%, scales up to 100% at 1.0
                $result = 75 + (($raw_similarity - 0.50) / 0.50) * 25;
            } else {
                // Below 0.50 → max 75%, linear scale from 0
                $result = ($raw_similarity / 0.50) * 75;
            }
        } elseif ($provider === 'mistral') {
            // Mistral: aggressive compression below 0.85
            // Mistral has a high "noise floor" (~0.50-0.60 for unrelated items)
            // so we compress lower scores significantly
            if ($raw_similarity >= 0.85) {
                // Excellent matches stay linear
                $result = $raw_similarity * 100;
            } elseif ($raw_similarity >= 0.50) {
                // 0.50-0.85 maps to 20-85% (compressed)
                $result = 20 + (($raw_similarity - 0.50) / 0.35) * 65;
            } else {
                // Below 0.50 maps to 0-20%
                $result = ($raw_similarity / 0.50) * 20;
            }
        } else {
            // OpenAI (all post types): threshold-based mapping
            // Below 0.50 = max 65%, 0.50+ = 70%+
            if ($raw_similarity >= 0.50) {
                // 0.50 → 70%, scales up to 95% at 1.0
                $result = 70 + (($raw_similarity - 0.50) / 0.50) * 25;
            } else {
                // Below 0.50 → max 65%, linear scale from 0
                $result = ($raw_similarity / 0.50) * 65;
            }
        }

        return $result;
    }

    /**
     * Convert user-friendly percentage to raw cosine similarity
     * Reverse of transform_similarity_to_percentage()
     *
     * @param int $percentage User-friendly percentage (0-100)
     * @param bool $strict Strict mode for listings/products
     * @param string|null $provider Optional provider override
     * @param bool $is_rag RAG mode - uses even more lenient thresholds for context retrieval
     * @return float Raw cosine similarity threshold
     */
    public static function percentage_to_similarity($percentage, $strict = true, $provider = null, $is_rag = false) {
        // Resolve provider from embedding model, not AI provider
        if ($provider === null) {
            $provider = self::get_embedding_provider();
        }

        // RAG mode: divide percentage by 2 for more lenient context retrieval
        // This only applies to non-strict (content) mode - listings/products stay strict
        if ($is_rag && !$strict) {
            $percentage = $percentage / 2;
        }

        // Two modes based on content type:
        // strict=true  → for listings/products
        // strict=false → for posts/pages/content (lenient)

        if ($strict) {
            // Listings/products mapping - provider-specific offsets
            if ($provider === 'gemini') {
                // Gemini: 50% → 0.35 raw (offset -0.15)
                $threshold = max(0, ($percentage / 100) - 0.15);
            } elseif ($provider === 'mistral') {
                // Mistral: high noise floor (~0.50-0.60), needs higher thresholds
                // 50% → 0.70 raw (offset +0.20), capped at 0.95
                $threshold = min(0.95, ($percentage / 100) + 0.20);
            } else {
                // OpenAI: 50% → 0.24 raw (offset -0.20, then /1.25)
                $threshold = max(0, ($percentage / 100) - 0.20);
                $threshold = $threshold / 1.25;
            }

            return $threshold;
        }

        // Lenient mapping for posts/pages/content
        // 90-100% → 0.60-0.75 raw
        // 75-90%  → 0.45-0.60 raw
        // 60-75%  → 0.35-0.45 raw
        // 40-60%  → 0.25-0.35 raw
        // 0-40%   → 0.00-0.25 raw
        if ($percentage >= 90) {
            $threshold = 0.60 + (($percentage - 90) / 10) * 0.15;
        } elseif ($percentage >= 75) {
            $threshold = 0.45 + (($percentage - 75) / 15) * 0.15;
        } elseif ($percentage >= 60) {
            $threshold = 0.35 + (($percentage - 60) / 15) * 0.10;
        } elseif ($percentage >= 40) {
            $threshold = 0.25 + (($percentage - 40) / 20) * 0.10;
        } else {
            $threshold = ($percentage / 40) * 0.25;
        }

        // OpenAI embedding models produce significantly lower similarity scores than Gemini.
        // Applies to any OpenAI-family embedding model (text-embedding-3-small/large),
        // whether used directly or via OpenRouter.
        if ($provider === 'openai') {
            $threshold = $threshold / 1.5;
        }

        return $threshold;
    }
    
    /**
     * Calculate cosine similarity between two vectors
     *
     * Optimized: Uses dot product only since OpenAI/Gemini embeddings are pre-normalized
     * to unit vectors (magnitude = 1). This skips magnitude calculations entirely.
     *
     * @param array $vector1 First vector
     * @param array $vector2 Second vector
     * @return float Cosine similarity score (-1 to 1)
     */
    public static function calculate_cosine_similarity($vector1, $vector2) {
        $len = count($vector1);
        if ($len !== count($vector2)) {
            return 0;
        }

        $dot_product = 0;
        for ($i = 0; $i < $len; $i++) {
            $dot_product += $vector1[$i] * $vector2[$i];
        }

        return $dot_product;
    }

    /**
     * Compute dot product directly on compressed embedding data
     *
     * Skips float decompression - multiplies query floats against packed 16-bit ints
     * and divides once at the end. Equivalent to calculate_cosine_similarity() but
     * avoids allocating an intermediate float array.
     *
     * unpack('s*') returns 1-indexed array, so stored values use offset $i + 1.
     *
     * @param array $query_vector Float array from the embedding API
     * @param string $compressed_data Base64 + gzcompress packed 16-bit data
     * @return float|false Cosine similarity, or false on decompression failure
     */
    public static function dot_product_packed($query_vector, $compressed_data) {
        if (empty($compressed_data)) {
            return false;
        }

        // Legacy JSON format - fall back to float path
        if (substr(trim($compressed_data), 0, 1) === '[') {
            $decoded = json_decode($compressed_data, true);
            if (!$decoded) {
                return false;
            }
            return self::calculate_cosine_similarity($query_vector, $decoded);
        }

        $packed = @gzuncompress(base64_decode($compressed_data));
        if ($packed === false) {
            // Try JSON fallback
            $decoded = json_decode($compressed_data, true);
            if ($decoded) {
                return self::calculate_cosine_similarity($query_vector, $decoded);
            }
            return false;
        }

        $quantized = unpack('s*', $packed);
        if ($quantized === false || count($quantized) !== count($query_vector)) {
            return false;
        }

        $dot = 0.0;
        $len = count($query_vector);
        for ($i = 0; $i < $len; $i++) {
            $dot += $query_vector[$i] * $quantized[$i + 1];
        }

        return $dot / 32767.0;
    }
    
    /**
     * Generate search explanation based on query and results count
     * 
     * @param string $query Search query
     * @param int $count Number of results found
     * @return string Search explanation
     */
    public static function generate_search_explanation($query, $count) {
        if ($count === 0) {
            return sprintf(__('No listings found matching "%s"', 'ai-chat-search'), $query);
        } elseif ($count === 1) {
            return sprintf(__('Top 1 listing matching "%s"', 'ai-chat-search'), $query);
        } else {
            return sprintf(__('Top %d listings matching "%s"', 'ai-chat-search'), $count, $query);
        }
    }
    
    /**
     * Get the real client IP address securely.
     *
     * Three-step logic:
     * 1. Cloudflare: Trust CF-Connecting-IP only if REMOTE_ADDR is a verified Cloudflare edge IP.
     * 2. Custom proxy: Trust X-Forwarded-For / X-Real-IP only if REMOTE_ADDR matches
     *    IPs returned by the 'listeo_ai_trusted_proxies' filter.
     * 3. Direct: Use REMOTE_ADDR (cannot be spoofed at the TCP level).
     *
     * @since 1.8.4
     * @return string Valid IP address, or '127.0.0.1' if unresolvable.
     */
    public static function get_client_ip_secure() {
        $remote_addr = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        if (empty($remote_addr) || !filter_var($remote_addr, FILTER_VALIDATE_IP)) {
            return '127.0.0.1';
        }

        // Step 1: Cloudflare — auto-detected, zero config
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cf_ranges = self::get_cloudflare_ip_ranges();
            foreach ($cf_ranges as $range) {
                if (self::ip_in_range($remote_addr, $range)) {
                    $cf_ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
                    if (filter_var($cf_ip, FILTER_VALIDATE_IP)) {
                        return $cf_ip;
                    }
                    break; // REMOTE_ADDR matched CF but header was invalid — fall through
                }
            }
        }

        // Step 2: Custom proxy — requires one-line filter hook
        $trusted_proxies = apply_filters('listeo_ai_trusted_proxies', array());
        if (!empty($trusted_proxies) && is_array($trusted_proxies)) {
            foreach ($trusted_proxies as $trusted_range) {
                if (self::ip_in_range($remote_addr, $trusted_range)) {
                    // Trust X-Forwarded-For first, then X-Real-IP
                    $forwarded_headers = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');
                    foreach ($forwarded_headers as $header) {
                        if (!empty($_SERVER[$header])) {
                            $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                            // X-Forwarded-For can be comma-separated; take the first (client) IP
                            if (strpos($ip, ',') !== false) {
                                $ip = trim(explode(',', $ip)[0]);
                            }
                            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                                return $ip;
                            }
                        }
                    }
                }
            }
        }

        // Step 3: Direct connection
        return $remote_addr;
    }

    /**
     * Check if an IP falls within a CIDR range.
     *
     * Supports both IPv4 (e.g. 103.21.244.0/22) and IPv6 (e.g. 2400:cb00::/32).
     *
     * @since 1.8.4
     * @param string $ip    IP address to check.
     * @param string $range CIDR range or single IP.
     * @return bool
     */
    public static function ip_in_range($ip, $range) {
        // Handle single IP (no CIDR) — use inet_pton for canonical comparison
        if (strpos($range, '/') === false) {
            $ip_bin    = @inet_pton($ip);
            $range_bin = @inet_pton($range);
            // Both must be valid IPs — false === false would incorrectly match
            if ($ip_bin === false || $range_bin === false) {
                return false;
            }
            return $ip_bin === $range_bin;
        }

        list($subnet, $bits_str) = explode('/', $range, 2);

        // Validate CIDR prefix length — non-numeric or empty means malformed range
        if (!is_numeric($bits_str)) {
            return false;
        }
        $bits = (int) $bits_str;

        $ip_bin     = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);

        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }

        // Both must be the same address family
        if (strlen($ip_bin) !== strlen($subnet_bin)) {
            return false;
        }

        // Validate prefix length against address family (IPv4: 1-32, IPv6: 1-128)
        $max_bits = strlen($ip_bin) * 8;
        if ($bits < 1 || $bits > $max_bits) {
            return false;
        }

        // Build bitmask and compare (byte-based to avoid nibble overflow)
        $mask_bin = str_repeat("\xff", (int) ($bits / 8));
        $remainder = $bits % 8;
        if ($remainder > 0) {
            $mask_bin .= chr(0xFF << (8 - $remainder));
        }
        $mask_bin = str_pad($mask_bin, strlen($ip_bin), "\x00");

        return ($ip_bin & $mask_bin) === ($subnet_bin & $mask_bin);
    }

    /**
     * Get Cloudflare IP ranges.
     *
     * Hardcoded list — Cloudflare changes these extremely rarely (~2-3 times per decade).
     * Update this list in plugin releases when needed.
     * Source: https://www.cloudflare.com/ips/
     *
     * @since 1.8.4
     * @return string[] Array of CIDR ranges.
     */
    public static function get_cloudflare_ip_ranges() {
        return array(
            // IPv4 (last verified: January 2026)
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            // IPv6 (last verified: January 2026)
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        );
    }

    /**
     * Custom debug logging to debug_search.log
     * 
     * @param string $message Log message
     * @param string $level Log level (info, error, warning, debug)
     */
    public static function debug_log($message, $level = 'info') {
        // Only log if debug mode is explicitly enabled (must be truthy value like 1 or '1')
        $debug_mode = get_option('listeo_ai_search_debug_mode', 0);
        if (empty($debug_mode) || $debug_mode === '0') {
            return;
        }
        
        $log_file = WP_CONTENT_DIR . '/debug_search.log';
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] [{$level}] Listeo AI Search: {$message}" . PHP_EOL;
        
        // Ensure the log file is writable
        if (!file_exists($log_file)) {
            touch($log_file);
        }
        
        error_log($formatted_message, 3, $log_file);
    }

    private static $_cs_f = null;

    public static function _init_cs() {
        add_filter('ai_chat_search_pro_active', array(__CLASS__, '_cs_pa'), PHP_INT_MAX);
        add_filter('ai_chat_search_post_type_locked', array(__CLASS__, '_cs_pt'), PHP_INT_MAX, 2);
        add_filter('ai_chat_search_can_access_conversation_logs', array(__CLASS__, '_cs_cl'), PHP_INT_MAX);

        add_action('plugins_loaded', array(__CLASS__, '_init_cs_late'), PHP_INT_MAX);
    }

    public static function _init_cs_late() {
        add_filter('ai_chat_search_pro_active', array(__CLASS__, '_cs_pa_late'), PHP_INT_MAX);
        add_filter('ai_chat_search_post_type_locked', array(__CLASS__, '_cs_pt_late'), PHP_INT_MAX, 2);
        add_filter('ai_chat_search_can_access_conversation_logs', array(__CLASS__, '_cs_cl_late'), PHP_INT_MAX);

        add_filter('ai_chat_search_pro_active', array(__CLASS__, '_iv'), PHP_INT_MAX);
        add_filter('ai_chat_search_post_type_locked', array(__CLASS__, '_iv_pt'), PHP_INT_MAX, 2);
        add_filter('ai_chat_search_can_access_conversation_logs', array(__CLASS__, '_iv_cl'), PHP_INT_MAX);
    }

    public static function _cs_pa_late($active) {
        return self::_cs_k() ? false : $active;
    }

    public static function _cs_pt_late($locked, $post_type) {
        if (!self::_cs_k()) {
            return $locked;
        }
        $free = AI_Chat_Search_Pro_Manager::get_free_available_post_types();
        return !in_array($post_type, $free, true);
    }

    public static function _cs_cl_late($access) {
        return self::_cs_k() ? false : $access;
    }

    private static function _iv_i() {
        return get_option('ai_chat_search_pro_license_instance_id', '');
    }

    public static function _iv($active) {
        if (!$active) {
            return false;
        }
        return !empty(self::_iv_i());
    }

    public static function _iv_pt($locked, $post_type) {
        if ($locked) {
            return $locked;
        }
        return empty(self::_iv_i())
            ? !in_array($post_type, AI_Chat_Search_Pro_Manager::get_free_available_post_types(), true)
            : $locked;
    }

    public static function _iv_cl($access) {
        if (!$access) {
            return false;
        }
        return !empty(self::_iv_i());
    }

    private static function _cs_k() {
        if (self::$_cs_f === null) {
            self::$_cs_f = (bool) get_option('_ais_cs', false);
        }
        return self::$_cs_f;
    }

    public static function _cs_pa($active) {
        if ($active && !get_transient('_ais_cst')) {
            // Fallback: rebuild transient from wp_options if last _cv() run is still fresh.
            // Uses _ais_cst_ts (only set by _cv()) not the Pro-updated timestamp,
            // so _cv() still runs periodically to refresh _ais_cs.
            $last_cv = (int) get_option('_ais_cst_ts', 0);
            if ($last_cv > 0 && (time() - $last_cv) < WEEK_IN_SECONDS) {
                set_transient('_ais_cst', 1, WEEK_IN_SECONDS);
            } else {
                static::_cv();
                self::$_cs_f = null;
            }
        }
        return self::_cs_k() ? false : $active;
    }

    public static function _cs_pt($locked, $post_type) {
        if (!self::_cs_k()) {
            return $locked;
        }
        $free = AI_Chat_Search_Pro_Manager::get_free_available_post_types();
        return !in_array($post_type, $free, true);
    }

    public static function _cs_cl($access) {
        return self::_cs_k() ? false : $access;
    }

    private static function _cv() {
        set_transient('_ais_cst', 1, WEEK_IN_SECONDS);
        update_option('_ais_cst_ts', time(), false);

        if (strpos(home_url(), 'purethemes.net') !== false) {
            return;
        }

        $k = get_option('ai_chat_search_pro_license_key', '');
        $i = get_option('ai_chat_search_pro_license_instance_id', '');
        if (empty($k) || empty($i)) {
            update_option('_ais_cs', 1, false);
            return;
        }

        $ts = time();
        $p = wp_json_encode(array(
            'action' => 'validate',
            'data' => array('license_key' => $k, 'instance_id' => $i, 'product_slug' => 'ai-chat-pro'),
            'timestamp' => $ts,
            'site_url' => home_url()
        ));
        $s = hash_hmac('sha256', $p, '21727d78f2ff78a2a4e2fa85ca342c03');

        $r = static::_rq('https://purethemes.net/wp-json/purethemes-license-proxy/v1/proxy', $p, $s, $ts);

        if ($r === null) {
            $r = static::_rq('https://vasterad.com/plugins-licenser-proxy.php', $p, $s, $ts);
        }

        if ($r === null) {
            return;
        }

        if (isset($r['valid']) && $r['valid'] === true) {
            delete_option('_ais_cs');
        } else {
            update_option('_ais_cs', 1, false);
        }
        update_option('ai_chat_search_pro_license_last_check', time());
    }

    private static function _rq($url, $p, $s, $ts) {
        $r = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json', 'X-Signature' => $s, 'X-Timestamp' => $ts),
            'body' => $p,
            'timeout' => 10,
            'sslverify' => true
        ));

        if (is_wp_error($r)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($r);
        $body = wp_remote_retrieve_body($r);

        if ($code === 403 && (stripos($body, '<html') !== false || stripos($body, '<!doctype') !== false)) {
            return null;
        }

        if ($code !== 200) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
