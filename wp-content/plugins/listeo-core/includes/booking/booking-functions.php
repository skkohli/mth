<?php
/**
 * Listeo Core Booking Functions
 * 
 * Global functions for accessing the booking system
 * 
 * @package Listeo Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Get the booking manager instance
 * 
 * @return Listeo_Core_Booking_Manager
 */
function listeo_get_booking_manager() {
    return Listeo_Core_Booking_Manager::instance();
}

/**
 * Check if listing supports booking
 * 
 * @param int $listing_id Listing post ID
 * @return bool
 */
function listeo_listing_supports_booking($listing_id = null) {
    if (!$listing_id) {
        global $post;
        $listing_id = $post->ID ?? 0;
    }
    
    if (!$listing_id) {
        return false;
    }
    
    return listeo_get_booking_manager()->listing_supports_booking($listing_id);
}

/**
 * Get booking configuration for listing
 * 
 * @param int $listing_id Listing post ID
 * @return object|null
 */
function listeo_get_booking_config($listing_id = null) {
    if (!$listing_id) {
        global $post;
        $listing_id = $post->ID ?? 0;
    }
    
    if (!$listing_id) {
        return null;
    }
    
    return listeo_get_booking_manager()->get_booking_config($listing_id);
}


// I need function that checks if listing supports some feature
// e.g. tickets, persons, etc.
// and returns true/false
// based on listing type and its booking configuration
// e.g. listeo_listing_supports_feature('tickets', $listing_id)
function listeo_listing_supports_feature($feature, $listing_id = null) {
    if (!$listing_id) {
        global $post;
        $listing_id = $post->ID ?? 0;
    }
    
    if (!$listing_id) {
        return false;
    }
    
    $booking_config = listeo_get_booking_config($listing_id);

    if (!$booking_config || !isset($booking_config->booking_features)) {
        return false;
    }
    
    return in_array($feature, $booking_config->booking_features);
}

// I need function that checkx booking type of listing
// e.g.booking_type can return single_day, date_range, tickets, none or custom
function listeo_get_booking_type($listing_id = null) {
    if (!$listing_id) {
        global $post;
        $listing_id = $post->ID ?? 0;
    }

    $booking_config = listeo_get_booking_config($listing_id);

    if (!$booking_config || !isset($booking_config->booking_type)) {
        return null;
    }

    return $booking_config->booking_type;
}