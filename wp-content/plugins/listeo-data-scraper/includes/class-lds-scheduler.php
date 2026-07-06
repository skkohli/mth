<?php
/**
 * Scheduled Imports (PRO)
 *
 * Lets an admin schedule a one-time import to run via WP-Cron at a chosen time
 * ("run in X minutes/hours from now"). Imports normally run client-side through a chain of
 * AJAX calls; a scheduled run has no browser, so this class re-runs that chain server-side
 * by reusing the existing API classes, AI processing and importer.
 *
 * @package Listeo_Data_Scraper
 */

if (!defined('ABSPATH')) {
    exit;
}

class LDS_Scheduler {

    /** Option holding all scheduled tasks (array keyed by id). */
    const OPTION = 'lds_scheduled_imports';

    /** Cron hook fired for each scheduled import. Receives the task id as its only arg. */
    const CRON_HOOK = 'lds_run_scheduled_import';

    /** Transient used as a re-entrancy lock so two scheduled runs can never overlap. */
    const LOCK_TRANSIENT = 'lds_scheduled_import_running';

    /** Safety ceiling for the lock in case a run dies before its finally block. */
    const LOCK_TTL = 30 * MINUTE_IN_SECONDS;

    /** Pro feature key. */
    const FEATURE = 'schedule_import';

    /** Minimum / maximum delay before a scheduled run, in seconds. */
    const MIN_DELAY = 1 * MINUTE_IN_SECONDS;    // 1 minute (smallest valid future time)
    const MAX_DELAY = 30 * DAY_IN_SECONDS;      // 30 days

    /** Rows shown per page in the admin card. */
    const LIST_LIMIT = 5;

    /** Max finished (completed/failed) tasks kept in storage; older ones are pruned. */
    const KEEP_FINISHED = 30;

    public function __construct() {
        add_action(self::CRON_HOOK, [$this, 'run_scheduled_import'], 10, 1);
        add_action('wp_ajax_lds_save_schedule', [$this, 'ajax_save_schedule']);
        add_action('wp_ajax_lds_delete_schedule', [$this, 'ajax_delete_schedule']);
        add_action('wp_ajax_lds_run_schedule_now', [$this, 'ajax_run_schedule_now']);
        add_action('wp_ajax_lds_rerun_schedule', [$this, 'ajax_rerun_schedule']);
        add_action('admin_init', [$this, 'maybe_reconcile']);
    }

    /**
     * Mark pending tasks as failed when their time has passed but no cron event backs them
     * any more - e.g. the plugin was deactivated, or the site never ran WP-Cron. Without
     * this they would sit forever as "Pending" with a time in the past. Runs cheaply on
     * admin page loads and only writes when something actually changed.
     */
    public function maybe_reconcile() {
        $all = get_option(self::OPTION, []);
        if (!is_array($all) || empty($all)) {
            return;
        }

        $now     = time();
        $grace   = 5 * MINUTE_IN_SECONDS; // WP-Cron is request-triggered; allow a small lag.
        $changed = false;

        foreach ($all as $id => $task) {
            if (($task['status'] ?? '') !== 'pending') {
                continue;
            }
            $run_at = (int) ($task['run_at'] ?? 0);

            // Still has a queued cron event -> leave it alone (it just hasn't fired yet).
            if ($run_at && $run_at < ($now - $grace) && !wp_next_scheduled(self::CRON_HOOK, [$id])) {
                $all[$id]['status'] = 'failed';
                $all[$id]['result'] = [
                    'imported' => 0,
                    'skipped'  => 0,
                    'message'  => __('This scheduled import did not run (no cron activity, or the plugin was inactive at the scheduled time). Create a new schedule to retry.', 'listeo-data-scraper'),
                    'finished' => $now,
                ];
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::OPTION, $all, false);
        }
    }

    /* ---------------------------------------------------------------------
     * Storage helpers
     * ------------------------------------------------------------------- */

    /**
     * Get all scheduled tasks, newest first.
     *
     * @return array
     */
    public static function get_all() {
        $all = get_option(self::OPTION, []);
        if (!is_array($all)) {
            return [];
        }
        // Newest first by created time.
        uasort($all, function ($a, $b) {
            return ($b['created'] ?? 0) <=> ($a['created'] ?? 0);
        });
        return $all;
    }

    /**
     * Get a single task by id.
     *
     * @param string $id
     * @return array|null
     */
    public static function get($id) {
        $all = get_option(self::OPTION, []);
        return (is_array($all) && isset($all[$id])) ? $all[$id] : null;
    }

    /**
     * Persist a single task.
     *
     * @param array $task
     */
    private static function put($task) {
        $all = get_option(self::OPTION, []);
        if (!is_array($all)) {
            $all = [];
        }
        $all[$task['id']] = $task;
        update_option(self::OPTION, $all, false);
    }

    /**
     * Remove a single task by id.
     *
     * @param string $id
     */
    private static function forget($id) {
        $all = get_option(self::OPTION, []);
        if (is_array($all) && isset($all[$id])) {
            unset($all[$id]);
            update_option(self::OPTION, $all, false);
        }
    }

    /**
     * Keep storage bounded: never drops pending/running tasks, but trims finished
     * (completed/failed) ones beyond KEEP_FINISHED, oldest first.
     */
    private static function prune_finished() {
        $all = get_option(self::OPTION, []);
        if (!is_array($all)) {
            return;
        }

        $finished = array_filter($all, function ($t) {
            return in_array(($t['status'] ?? ''), ['completed', 'failed'], true);
        });
        if (count($finished) <= self::KEEP_FINISHED) {
            return;
        }

        // Newest finished first, then drop everything past the keep limit.
        uasort($finished, function ($a, $b) {
            $ax = $a['result']['finished'] ?? ($a['created'] ?? 0);
            $bx = $b['result']['finished'] ?? ($b['created'] ?? 0);
            return $bx <=> $ax;
        });

        foreach (array_slice(array_keys($finished), self::KEEP_FINISHED) as $id) {
            unset($all[$id]);
        }
        update_option(self::OPTION, $all, false);
    }

    /* ---------------------------------------------------------------------
     * AJAX: save / edit
     * ------------------------------------------------------------------- */

    public function ajax_save_schedule() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'listeo-data-scraper')]);
        }

        if (LDS_Pro_Manager::is_feature_locked(self::FEATURE)) {
            wp_send_json_error([
                'message'     => __('Scheduled Imports is a Pro feature.', 'listeo-data-scraper'),
                'type'        => 'pro_required',
                'upgrade_url' => LDS_Pro_Manager::get_upgrade_url(self::FEATURE),
            ]);
        }

        // Delay (in minutes from now) -> absolute run time.
        $delay_minutes = isset($_POST['delay_minutes']) ? absint($_POST['delay_minutes']) : 0;
        $delay_seconds = $delay_minutes * MINUTE_IN_SECONDS;

        if ($delay_seconds < self::MIN_DELAY || $delay_seconds > self::MAX_DELAY) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %d: maximum number of days. */
                    __('Choose a time in the future, up to %d days from now.', 'listeo-data-scraper'),
                    (int) (self::MAX_DELAY / DAY_IN_SECONDS)
                ),
            ]);
        }

        $run_at = time() + $delay_seconds;

        // Editing an existing task only changes its run time; settings stay frozen.
        $edit_id = isset($_POST['schedule_id']) ? sanitize_text_field(wp_unslash($_POST['schedule_id'])) : '';
        if (!empty($edit_id)) {
            $task = self::get($edit_id);
            if (!$task) {
                wp_send_json_error(['message' => __('That scheduled import no longer exists.', 'listeo-data-scraper')]);
            }
            // Cannot reschedule a job that has already run.
            if (($task['status'] ?? 'pending') !== 'pending') {
                wp_send_json_error(['message' => __('Only pending imports can be rescheduled. Delete it and create a new one.', 'listeo-data-scraper')]);
            }

            $task['run_at']        = $run_at;
            $task['delay_minutes'] = $delay_minutes;
            self::put($task);
            self::reschedule_event($edit_id, $run_at);

            wp_send_json_success([
                'id'       => $edit_id,
                'row_html' => self::render_row($task),
            ]);
        }

        // Creating a new task: parse + sanitize the captured import settings.
        $raw = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';
        $settings = $this->sanitize_settings(is_array($raw) ? $raw : json_decode($raw, true));

        if (is_wp_error($settings)) {
            wp_send_json_error(['message' => $settings->get_error_message()]);
        }

        $id   = 'sched_' . wp_generate_password(12, false);
        $task = [
            'id'            => $id,
            'user_id'       => get_current_user_id(),
            'created'       => time(),
            'run_at'        => $run_at,
            'delay_minutes' => $delay_minutes,
            'status'        => 'pending',
            'label'         => $this->build_label($settings),
            'settings'      => $settings,
            'result'        => ['imported' => 0, 'skipped' => 0, 'message' => '', 'finished' => 0],
        ];

        self::put($task);
        self::prune_finished();
        self::reschedule_event($id, $run_at);

        lds_log("Scheduled import {$id} created for " . gmdate('c', $run_at), 'SCHEDULE');

        wp_send_json_success([
            'id'       => $id,
            'row_html' => self::render_row($task),
        ]);
    }

    /* ---------------------------------------------------------------------
     * AJAX: delete
     * ------------------------------------------------------------------- */

    public function ajax_delete_schedule() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'listeo-data-scraper')]);
        }

        $id = isset($_POST['schedule_id']) ? sanitize_text_field(wp_unslash($_POST['schedule_id'])) : '';
        if (empty($id) || !self::get($id)) {
            wp_send_json_error(['message' => __('That scheduled import no longer exists.', 'listeo-data-scraper')]);
        }

        wp_clear_scheduled_hook(self::CRON_HOOK, [$id]);
        self::forget($id);

        lds_log("Scheduled import {$id} deleted", 'SCHEDULE');

        wp_send_json_success(['id' => $id]);
    }

    /* ---------------------------------------------------------------------
     * AJAX: run a pending schedule immediately
     * ------------------------------------------------------------------- */

    public function ajax_run_schedule_now() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'listeo-data-scraper')]);
        }

        if (LDS_Pro_Manager::is_feature_locked(self::FEATURE)) {
            wp_send_json_error([
                'message'     => __('Scheduled Imports is a Pro feature.', 'listeo-data-scraper'),
                'type'        => 'pro_required',
                'upgrade_url' => LDS_Pro_Manager::get_upgrade_url(self::FEATURE),
            ]);
        }

        $id   = isset($_POST['schedule_id']) ? sanitize_text_field(wp_unslash($_POST['schedule_id'])) : '';
        $task = self::get($id);
        if (empty($id) || !$task) {
            wp_send_json_error(['message' => __('That scheduled import no longer exists.', 'listeo-data-scraper')]);
        }
        if (($task['status'] ?? '') !== 'pending') {
            wp_send_json_error(['message' => __('Only a pending import can be run now.', 'listeo-data-scraper')]);
        }

        // Cancel the future event - we are running it right now instead.
        wp_clear_scheduled_hook(self::CRON_HOOK, [$id]);

        // Runs synchronously; the same guards (re-entrancy lock, pro check) apply.
        $this->run_scheduled_import($id);

        wp_send_json_success([
            'id'       => $id,
            'row_html' => self::render_row(self::get($id)),
        ]);
    }

    /**
     * Re-run a finished (completed/failed) import again, immediately, with its stored
     * settings. Resets the task to pending and runs it now.
     */
    public function ajax_rerun_schedule() {
        check_ajax_referer('lds_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'listeo-data-scraper')]);
        }

        if (LDS_Pro_Manager::is_feature_locked(self::FEATURE)) {
            wp_send_json_error([
                'message'     => __('Scheduled Imports is a Pro feature.', 'listeo-data-scraper'),
                'type'        => 'pro_required',
                'upgrade_url' => LDS_Pro_Manager::get_upgrade_url(self::FEATURE),
            ]);
        }

        $id   = isset($_POST['schedule_id']) ? sanitize_text_field(wp_unslash($_POST['schedule_id'])) : '';
        $task = self::get($id);
        if (empty($id) || !$task) {
            wp_send_json_error(['message' => __('That scheduled import no longer exists.', 'listeo-data-scraper')]);
        }
        if (!in_array(($task['status'] ?? ''), ['completed', 'failed'], true)) {
            wp_send_json_error(['message' => __('Only a finished import can be re-run.', 'listeo-data-scraper')]);
        }

        // Reset to pending so the runner's guard passes, then run immediately.
        $task['status'] = 'pending';
        $task['run_at'] = time();
        $task['result'] = ['imported' => 0, 'skipped' => 0, 'message' => '', 'finished' => 0];
        self::put($task);
        wp_clear_scheduled_hook(self::CRON_HOOK, [$id]);

        $this->run_scheduled_import($id);

        wp_send_json_success([
            'id'       => $id,
            'row_html' => self::render_row(self::get($id)),
        ]);
    }

    /* ---------------------------------------------------------------------
     * Cron: run a scheduled import
     * ------------------------------------------------------------------- */

    /**
     * WP-Cron callback. Runs a single scheduled import with several guards that make it
     * impossible to drain the API in a loop.
     *
     * @param string $id Task id.
     */
    public function run_scheduled_import($id) {
        $task = self::get($id);

        // Idempotency: only ever run a task once, even on a double cron fire.
        if (!$task || ($task['status'] ?? '') !== 'pending') {
            return;
        }

        // Re-entrancy guard: never overlap with another running import.
        if (get_transient(self::LOCK_TRANSIENT)) {
            $this->finish($id, 'failed', [
                'message' => __('Another import was already running, so this run was skipped to protect your API usage.', 'listeo-data-scraper'),
            ]);
            return;
        }

        // Pro guard at fire time: a lapsed licence must not make API calls.
        if (LDS_Pro_Manager::is_feature_locked(self::FEATURE)) {
            $this->finish($id, 'failed', [
                'message' => __('Scheduled import skipped: a Pro licence is required.', 'listeo-data-scraper'),
            ]);
            return;
        }

        set_transient(self::LOCK_TRANSIENT, $id, self::LOCK_TTL);
        $this->set_status($id, 'running');

        // create_listing() uses the current user as the post author.
        if (!empty($task['user_id'])) {
            wp_set_current_user((int) $task['user_id']);
        }

        try {
            $result = $this->execute_import($task['settings']);
            $this->finish($id, 'completed', $result);
        } catch (\Throwable $e) {
            lds_log('Scheduled import failed: ' . $e->getMessage(), 'SCHEDULE_ERROR', 'ERROR');
            $this->finish($id, 'failed', ['message' => $e->getMessage()]);
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    /* ---------------------------------------------------------------------
     * Server-side import runner (replays the client-side AJAX chain)
     * ------------------------------------------------------------------- */

    /**
     * Run one full import from stored settings.
     *
     * @param array $settings
     * @return array { imported, skipped, message }
     * @throws \Exception on a hard failure (missing key, etc).
     */
    private function execute_import($settings) {
        // Photo sideloading needs these admin helpers, which are not auto-loaded in cron.
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $api_source = get_option('lds_api_source', 'google');
        $api_key    = ($api_source === 'outscraper')
            ? get_option('lds_outscraper_api_key')
            : get_option('lds_google_api_key');

        if (empty($api_key)) {
            throw new \Exception(__('API key is not configured in Settings.', 'listeo-data-scraper'));
        }

        $lang_setting = get_option('lds_description_language', 'site-default');
        $language     = ($lang_setting === 'site-default') ? get_locale() : $lang_setting;
        $limit        = LDS_Pro_Manager::get_import_limit();

        $api = ($api_source === 'outscraper')
            ? new LDS_Outscraper_API($api_key, $language)
            : new LDS_Google_API($api_key, $language);

        // 1) Build the search query exactly like the import form does.
        $business = $settings['query'];
        $location = $settings['location'];
        if ($settings['search_mode'] === 'map' && !empty($location)) {
            $query = "{$business} near {$location}";
        } elseif (!empty($location)) {
            $query = "{$business} in {$location}";
        } else {
            $query = $business;
        }

        // 2) Find new (not-yet-imported) place ids, capped at the import limit.
        $place_ids = $this->find_new_place_ids($api, $query, $limit);
        if (empty($place_ids)) {
            return ['imported' => 0, 'skipped' => 0, 'message' => __('No new listings found for this search.', 'listeo-data-scraper')];
        }

        // 3) Fetch full details for each place (mirrors fetch_place_details()).
        if ($api_source === 'google') {
            $photo_limit = get_option('lds_enable_photo_import', 0) ? LDS_Pro_Manager::get_photo_import_limit() : 0;
        } else {
            $photo_limit = LDS_Pro_Manager::get_photo_import_limit();
        }
        $ai_enabled    = (bool) get_option('lds_enable_ai_descriptions', 1);
        $fetch_reviews = $ai_enabled || !empty($settings['import_prefs']['import_place_id']);

        $places         = [];
        $fetch_failures = 0;
        foreach ($place_ids as $place_id) {
            $place_data = ($api_source === 'outscraper')
                ? $api->get_place_details($place_id, $photo_limit, $fetch_reviews)
                : $api->get_place_details($place_id, $photo_limit);

            if (!is_wp_error($place_data) && !empty($place_data)) {
                $places[] = $place_data;
            } else {
                $fetch_failures++;
            }
        }

        if (empty($places)) {
            return ['imported' => 0, 'skipped' => 0, 'message' => __('Could not fetch place details from the API.', 'listeo-data-scraper')];
        }

        // 4) Generate descriptions / SEO (reuses the exact AJAX-handler logic).
        $handler  = new LDS_Ajax_Handler();
        $listings = $handler->build_processed_listings($places);

        // 5) Create the listings.
        $import_prefs = $settings['import_prefs'];
        $importer     = new LDS_Importer();
        $imported     = 0;

        foreach ($listings as $listing_data) {
            // Match process_single_job(): strip phone/website lines the user opted out of.
            if (!empty($listing_data['description'])) {
                if (empty($import_prefs['import_website'])) {
                    $listing_data['description'] = preg_replace('/<li>\x{1F310}.*?<\/li>/su', '', $listing_data['description']);
                }
                if (empty($import_prefs['import_phone'])) {
                    $listing_data['description'] = preg_replace('/<li>\x{1F4DE}.*?<\/li>/su', '', $listing_data['description']);
                }
                $listing_data['description'] = preg_replace('/<ul>\s*<\/ul>/s', '', $listing_data['description']);
            }

            $post_id = $importer->create_listing(
                $listing_data,
                $settings['category_ids'],
                $settings['region_ids'],
                $settings['listing_type'],
                $settings['region_taxonomy'],
                $settings['category_taxonomy'],
                $import_prefs,
                $settings['feature_ids']
            );

            if ($post_id) {
                $imported++;
            }
        }

        // Skipped = places fetched but not created (e.g. duplicates) + detail-fetch failures.
        $skipped = (count($places) - $imported) + $fetch_failures;

        return [
            'imported' => $imported,
            'skipped'  => max(0, $skipped),
            'message'  => sprintf(
                /* translators: %d: number of listings imported. */
                _n('Imported %d listing.', 'Imported %d listings.', $imported, 'listeo-data-scraper'),
                $imported
            ),
        ];
    }

    /**
     * Paginate the search API and collect place ids that are not already imported.
     * Mirrors the dedupe core of LDS_Ajax_Handler::get_place_ids().
     *
     * @param object $api   API instance.
     * @param string $query Search query.
     * @param int    $limit Max new ids to return.
     * @return array
     */
    private function find_new_place_ids($api, $query, $limit) {
        $found      = [];
        $page_token = null;
        $page       = 0;
        $max_pages  = 10;

        do {
            $page++;
            $response = $api->fetch_place_ids_paginated($query, $page_token, $limit);

            if (is_wp_error($response)) {
                break;
            }

            $ids        = isset($response['place_ids']) ? $response['place_ids'] : [];
            $page_token = isset($response['next_page_token']) ? $response['next_page_token'] : null;

            if (empty($ids)) {
                break;
            }

            // Which of these ids already exist as listings?
            $existing_post_ids = get_posts([
                'post_type'      => 'listing',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => [[
                    'key'     => '_place_id',
                    'value'   => $ids,
                    'compare' => 'IN',
                ]],
            ]);

            $existing = [];
            foreach ($existing_post_ids as $existing_id) {
                $meta = get_post_meta($existing_id, '_place_id', true);
                if ($meta) {
                    $existing[] = $meta;
                }
            }

            foreach (array_diff($ids, $existing) as $new_id) {
                if (count($found) < $limit) {
                    $found[] = $new_id;
                } else {
                    break;
                }
            }
        } while (count($found) < $limit && !empty($page_token) && $page < $max_pages);

        return $found;
    }

    /* ---------------------------------------------------------------------
     * Status helpers
     * ------------------------------------------------------------------- */

    private function set_status($id, $status) {
        $task = self::get($id);
        if ($task) {
            $task['status'] = $status;
            self::put($task);
        }
    }

    /**
     * Store the final outcome of a run.
     *
     * @param string $id
     * @param string $status completed|failed
     * @param array  $result
     */
    private function finish($id, $status, $result) {
        $task = self::get($id);
        if (!$task) {
            return;
        }
        $task['status'] = $status;
        $task['result'] = wp_parse_args($result, [
            'imported' => 0,
            'skipped'  => 0,
            'message'  => '',
        ]);
        $task['result']['finished'] = time();
        self::put($task);

        lds_log("Scheduled import {$id} {$status}: " . $task['result']['message'], 'SCHEDULE');
    }

    /* ---------------------------------------------------------------------
     * Cron event helpers
     * ------------------------------------------------------------------- */

    /**
     * (Re)schedule the one-shot cron event for a task.
     *
     * @param string $id
     * @param int    $run_at
     */
    private static function reschedule_event($id, $run_at) {
        wp_clear_scheduled_hook(self::CRON_HOOK, [$id]);
        wp_schedule_single_event($run_at, self::CRON_HOOK, [$id]);
    }

    /* ---------------------------------------------------------------------
     * Settings sanitization + labelling
     * ------------------------------------------------------------------- */

    /**
     * Validate and sanitize the captured import settings.
     *
     * @param mixed $raw Decoded settings array (or null).
     * @return array|WP_Error
     */
    private function sanitize_settings($raw) {
        if (!is_array($raw)) {
            return new WP_Error('bad_settings', __('Invalid import settings.', 'listeo-data-scraper'));
        }

        $search_mode = (isset($raw['search_mode']) && $raw['search_mode'] === 'map') ? 'map' : 'text';

        $category_ids = isset($raw['category_ids']) && is_array($raw['category_ids'])
            ? array_values(array_filter(array_map('absint', $raw['category_ids'])))
            : [];
        $region_ids = isset($raw['region_ids']) && is_array($raw['region_ids'])
            ? array_values(array_filter(array_map('absint', $raw['region_ids'])))
            : [];
        $feature_ids = isset($raw['feature_ids']) && is_array($raw['feature_ids'])
            ? array_values(array_filter(array_map('absint', $raw['feature_ids'])))
            : [];

        $settings = [
            'query'             => isset($raw['query']) ? sanitize_text_field($raw['query']) : '',
            'location'          => isset($raw['location']) ? sanitize_text_field($raw['location']) : '',
            'search_mode'       => $search_mode,
            'lat'               => isset($raw['lat']) ? sanitize_text_field($raw['lat']) : '',
            'lng'               => isset($raw['lng']) ? sanitize_text_field($raw['lng']) : '',
            'category_ids'      => $category_ids,
            'category_taxonomy' => isset($raw['category_taxonomy']) ? sanitize_text_field($raw['category_taxonomy']) : 'listing_category',
            'region_ids'        => $region_ids,
            'region_taxonomy'   => isset($raw['region_taxonomy']) ? sanitize_text_field($raw['region_taxonomy']) : 'region',
            'listing_type'      => isset($raw['listing_type']) ? sanitize_text_field($raw['listing_type']) : 'service',
            'feature_ids'       => $feature_ids,
            'import_prefs'      => $this->sanitize_import_prefs(isset($raw['import_prefs']) ? $raw['import_prefs'] : []),
        ];

        // Required fields mirror the import form's own validation.
        if (empty($settings['query'])) {
            return new WP_Error('missing_query', __('Business type is required.', 'listeo-data-scraper'));
        }
        if (empty($settings['listing_type'])) {
            return new WP_Error('missing_listing_type', __('Listing type is required.', 'listeo-data-scraper'));
        }
        if (empty($settings['category_ids'])) {
            return new WP_Error('missing_category', __('Please select at least one category.', 'listeo-data-scraper'));
        }
        if ($settings['search_mode'] === 'map') {
            if ($settings['lat'] === '' || $settings['lng'] === '') {
                return new WP_Error('missing_coords', __('Map coordinates are required for map search.', 'listeo-data-scraper'));
            }
        } elseif (empty($settings['location'])) {
            return new WP_Error('missing_location', __('Location is required.', 'listeo-data-scraper'));
        }

        return $settings;
    }

    private function sanitize_import_prefs($prefs) {
        $keys = ['import_phone', 'import_website', 'import_socials', 'import_hours', 'import_place_id', 'import_photos'];
        $out  = [];
        foreach ($keys as $key) {
            // Default to enabled (1), matching the form defaults.
            $out[$key] = (isset($prefs[$key]) && (int) $prefs[$key] === 0) ? 0 : 1;
        }
        return $out;
    }

    private function build_label($settings) {
        $business = $settings['query'];
        $where    = $settings['search_mode'] === 'map'
            ? ($settings['location'] !== '' ? $settings['location'] : __('map location', 'listeo-data-scraper'))
            : $settings['location'];

        if ($where !== '') {
            /* translators: 1: business type, 2: location. */
            return sprintf(__('%1$s in %2$s', 'listeo-data-scraper'), $business, $where);
        }
        return $business;
    }

    /* ---------------------------------------------------------------------
     * Rendering (single source of truth for a row, used by PHP + AJAX)
     * ------------------------------------------------------------------- */

    /**
     * Render the whole schedule list (used on initial page load).
     *
     * @return string
     */
    public static function render_list() {
        $html = '<ul class="lds-schedule-list" id="lds-schedule-list">';

        // Without a Pro licence the feature (and any leftover tasks from a previous
        // licence) is hidden behind an upsell.
        if (LDS_Pro_Manager::is_feature_locked(self::FEATURE)) {
            $html .= self::locked_state_html();
            $html .= '</ul><div class="lds-schedule-pagination" id="lds-schedule-pagination"></div>';
            return $html;
        }

        $tasks = self::get_all(); // newest first

        // All rows are rendered; the browser paginates them (LIST_LIMIT per page).
        if (empty($tasks)) {
            $html .= self::empty_state_html();
        } else {
            $i = 0;
            foreach ($tasks as $task) {
                // Hide rows past the first page on first paint to avoid a flash of all
                // rows before the JS pager kicks in.
                $html .= self::render_row($task, $i >= self::LIST_LIMIT);
                $i++;
            }
        }
        $html .= '</ul>';
        $html .= '<div class="lds-schedule-pagination" id="lds-schedule-pagination"></div>';

        return $html;
    }

    public static function empty_state_html() {
        return '<li class="lds-schedule-empty">' .
            esc_html__('No scheduled imports yet.', 'listeo-data-scraper') .
            '</li>';
    }

    public static function locked_state_html() {
        return '<li class="lds-schedule-locked">' .
            '<p>' . esc_html__('Schedule imports to run automatically at a time you choose - set it and forget it.', 'listeo-data-scraper') . '</p>' .
            '<a href="' . esc_url(LDS_Pro_Manager::get_upgrade_url(self::FEATURE)) . '" class="lds-settings-button" target="_blank">' .
            esc_html__('Upgrade to Pro', 'listeo-data-scraper') .
            '</a>' .
            '</li>';
    }

    /**
     * Render a single schedule row.
     *
     * @param array $task
     * @param bool  $hidden Hide the row on first paint (rows past page 1) until JS paginates.
     * @return string
     */
    public static function render_row($task, $hidden = false) {
        $status   = isset($task['status']) ? $task['status'] : 'pending';
        $run_at   = isset($task['run_at']) ? (int) $task['run_at'] : 0;
        $is_pending = ($status === 'pending');

        $status_labels = [
            'pending'   => __('Pending', 'listeo-data-scraper'),
            'running'   => __('Processing', 'listeo-data-scraper'),
            'completed' => __('Completed', 'listeo-data-scraper'),
            'failed'    => __('Failed', 'listeo-data-scraper'),
        ];
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);

        // When it will / did run.
        if ($is_pending && $run_at) {
            $time_text = sprintf(
                /* translators: %s: human-readable date/time. */
                __('Runs %s', 'listeo-data-scraper'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), $run_at)
            );
        } elseif (!empty($task['result']['finished'])) {
            $time_text = sprintf(
                /* translators: %s: human-readable date/time. */
                __('Ran %s', 'listeo-data-scraper'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $task['result']['finished'])
            );
        } else {
            $time_text = '';
        }

        // Result line for finished jobs.
        $result_text = '';
        if (in_array($status, ['completed', 'failed'], true) && !empty($task['result']['message'])) {
            $result_text = $task['result']['message'];
        }

        ob_start();
        ?>
        <li class="lds-schedule-row" data-schedule-id="<?php echo esc_attr($task['id']); ?>"<?php echo $hidden ? ' style="display:none;"' : ''; ?>>
            <div class="lds-schedule-row__main">
                <span class="lds-schedule-row__label"><?php echo esc_html($task['label']); ?></span>
                <span class="lds-sched-status lds-sched-status--<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span>
            </div>
            <?php if ($time_text) : ?>
                <div class="lds-schedule-row__time"><?php echo esc_html($time_text); ?></div>
            <?php endif; ?>
            <?php if ($result_text) : ?>
                <div class="lds-schedule-row__result"><?php echo esc_html($result_text); ?></div>
            <?php endif; ?>
            <div class="lds-schedule-row__actions">
                <?php if ($is_pending) : ?>
                    <button type="button" class="lds-schedule-edit" data-schedule-id="<?php echo esc_attr($task['id']); ?>" data-label="<?php echo esc_attr($task['label']); ?>" data-delay="<?php echo esc_attr(isset($task['delay_minutes']) ? (int) $task['delay_minutes'] : 60); ?>">
                        <?php echo esc_html__('Edit', 'listeo-data-scraper'); ?>
                    </button>
                <?php elseif (in_array($status, ['completed', 'failed'], true)) : ?>
                    <button type="button" class="lds-schedule-rerun" data-schedule-id="<?php echo esc_attr($task['id']); ?>">
                        <?php echo esc_html__('Rerun', 'listeo-data-scraper'); ?>
                    </button>
                <?php endif; ?>
                <button type="button" class="lds-schedule-delete" data-schedule-id="<?php echo esc_attr($task['id']); ?>">
                    <?php echo esc_html__('Delete', 'listeo-data-scraper'); ?>
                </button>
            </div>
        </li>
        <?php
        return ob_get_clean();
    }
}
