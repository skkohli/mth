<?php
/**
 * Fallback Search Engine Class
 * 
 * Handles traditional WordPress search functionality
 * 
 * @package Listeo_AI_Search
 * @since 1.0.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Fallback_Engine {
    
    /**
     * Perform fallback search using WordPress default search
     * 
     * @param string $query Search query
     * @param int $limit Number of results to return
     * @param int $offset Results offset for pagination
     * @param string $listing_types Comma-separated listing types or 'all'
     * @return array Search results
     */
    public static function search($query, $limit, $offset, $listing_types) {
        $args = array(
            'post_type' => 'listing',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            's' => $query,
            'meta_query' => array()
        );
        
        // Add listing type filter
        if ($listing_types !== 'all') {
            $types = array_map('trim', explode(',', $listing_types));
            $args['meta_query'][] = array(
                'key' => '_listing_type',
                'value' => $types,
                'compare' => 'IN'
            );
        }
        
        $search_query = new WP_Query($args);
        
        return array(
            'listings' => Listeo_AI_Search_Result_Formatter::format_search_results($search_query->posts, false),
            'total_found' => $search_query->found_posts,
            'search_type' => 'traditional',
            'query' => $query,
            'explanation' => sprintf(__('Here are listings matching "%s"', 'ai-chat-search'), $query),
            'has_more' => $search_query->found_posts > ($offset + $limit)
        );
    }
}
