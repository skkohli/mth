<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Listeo Core Reviews Migration Class
 *
 * Handles migration from global-only criteria (v1.0) to advanced per-type/per-taxonomy criteria system (v2.0)
 *
 * @class Listeo_Core_Reviews_Migration
 * @since 2.0.0
 */
class Listeo_Core_Reviews_Migration
{

    /**
     * Migrate to advanced criteria system (v2.0)
     *
     * This method:
     * 1. Migrates existing global criteria to new storage structure
     * 2. Initializes empty per-type and per-taxonomy options
     * 3. Backfills criteria snapshots for existing reviews
     * 4. Updates version number
     *
     * @return boolean True if migration ran, false if already migrated
     */
    public static function migrate_to_advanced_criteria()
    {
        $version = get_option('listeo_reviews_criteria_version', '1.0');

        // Check if already migrated
        if (version_compare($version, '2.0', '>=')) {
            return false; // Already migrated
        }

        // Step 1: Migrate existing global criteria to new option name
        $existing = get_option('listeo_reviews_criteria_fields');
        if ($existing && is_array($existing)) {
            update_option('listeo_reviews_criteria_global', $existing);
            // Keep old option for rollback capability (can be deleted after 6 months)
        }

        // Step 2: Initialize empty per-type and per-taxonomy options
        add_option('listeo_reviews_criteria_types', array());
        add_option('listeo_reviews_criteria_taxonomies', array());

        // Step 3: Backfill criteria snapshots for existing reviews
        self::backfill_criteria_snapshots();

        // Step 4: Update version number
        update_option('listeo_reviews_criteria_version', '2.0');

        return true;
    }

    /**
     * Backfill criteria snapshots for existing reviews
     *
     * Adds _review_criteria_snapshot meta to all existing reviews that don't have it.
     * This ensures historical reviews display their original criteria even if criteria change.
     *
     * @return int Number of reviews updated
     */
    private static function backfill_criteria_snapshots()
    {
        global $wpdb;

        // Get all comment IDs that don't have a criteria snapshot
        $comments = $wpdb->get_results("
            SELECT comment_ID, comment_post_ID
            FROM {$wpdb->comments} c
            LEFT JOIN {$wpdb->commentmeta} cm
                ON c.comment_ID = cm.comment_id
                AND cm.meta_key = '_review_criteria_snapshot'
            WHERE c.comment_type = ''
            AND cm.meta_id IS NULL
        ");

        if (empty($comments)) {
            return 0;
        }

        // Get global criteria (what was used for all reviews before migration)
        $global_criteria = get_option('listeo_reviews_criteria_global');
        if (empty($global_criteria) || !is_array($global_criteria)) {
            // Fallback to hardcoded defaults
            $global_criteria = listeo_get_reviews_criteria();
        }

        $count = 0;

        foreach ($comments as $comment) {
            // Only store criteria that actually have ratings for this review
            // This prevents showing empty criteria fields
            $used_criteria = array();

            foreach ($global_criteria as $key => $value) {
                $rating = get_comment_meta($comment->comment_ID, $key, true);
                if (!empty($rating)) {
                    $used_criteria[$key] = $value;
                }
            }

            // Add snapshot if there are any criteria with ratings
            if (!empty($used_criteria)) {
                add_comment_meta($comment->comment_ID, '_review_criteria_snapshot', $used_criteria);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Rollback to version 1.0 (global criteria only)
     *
     * Removes new options and reverts to old system.
     * Criteria snapshots remain in database (harmless) for potential re-migration.
     *
     * @return boolean True if rollback successful
     */
    public static function rollback_to_v1()
    {
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Restore old version
        update_option('listeo_reviews_criteria_version', '1.0');

        // Optionally delete new options (uncomment to fully remove)
        // delete_option('listeo_reviews_criteria_global');
        // delete_option('listeo_reviews_criteria_types');
        // delete_option('listeo_reviews_criteria_taxonomies');

        // Note: Snapshots remain in database (they're harmless meta fields)
        // and allow for easy re-migration if needed

        return true;
    }

    /**
     * Get migration status information
     *
     * @return array Status information
     */
    public static function get_migration_status()
    {
        $version = get_option('listeo_reviews_criteria_version', '1.0');

        $status = array(
            'version' => $version,
            'migrated' => version_compare($version, '2.0', '>='),
            'global_criteria_exists' => (bool) get_option('listeo_reviews_criteria_global'),
            'old_criteria_exists' => (bool) get_option('listeo_reviews_criteria_fields'),
            'type_criteria_count' => count(get_option('listeo_reviews_criteria_types', array())),
            'taxonomy_criteria_count' => 0, // Count will vary by taxonomy
        );

        // Count taxonomy criteria sets
        $taxonomy_criteria = get_option('listeo_reviews_criteria_taxonomies', array());
        if (is_array($taxonomy_criteria)) {
            foreach ($taxonomy_criteria as $taxonomy => $terms) {
                if (is_array($terms)) {
                    $status['taxonomy_criteria_count'] += count($terms);
                }
            }
        }

        // Count reviews with snapshots
        global $wpdb;
        $status['reviews_with_snapshots'] = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT comment_id)
            FROM {$wpdb->commentmeta}
            WHERE meta_key = '_review_criteria_snapshot'
        ");

        $status['total_reviews'] = (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->comments}
            WHERE comment_type = ''
        ");

        return $status;
    }
}
