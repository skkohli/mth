<?php
/**
 * Listeo Core Booking Manager
 * 
 * Coordinates booking functionality and integrates with custom listing types system
 * 
 * @package Listeo Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Core_Booking_Manager
{
    /**
     * Single instance of the class
     */
    private static $_instance = null;

    /**
     * Availability calculator instance
     */
    private $availability_calculator;

    /**
     * Pricing calculator instance  
     */
    private $pricing_calculator;

    /**
     * Form renderer instance
     */
    private $form_renderer;

    /**
     * Custom listing types manager
     */
    private $custom_types_manager;

    /**
     * Get instance
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the booking system
     */
    private function init()
    {
        // Initialize custom types manager if available
        if (function_exists('listeo_core_custom_listing_types')) {
            $this->custom_types_manager = listeo_core_custom_listing_types();
        }

    }

    /**
     * Check if listing supports booking
     * 
     * @param int $listing_id Listing post ID
     * @return bool
     */
    public function listing_supports_booking($listing_id)
    {
        // Check basic booking status
        $booking_status = get_post_meta($listing_id, '_booking_status', true);
        if (!$booking_status) {
            return false;
        }

        // Check listing type configuration
        $listing_type = get_post_meta($listing_id, '_listing_type', true);
        
        if ($this->custom_types_manager) {
            $type_config = $this->custom_types_manager->get_listing_type_by_slug($listing_type);
            
            if ($type_config) {
                // Use new flexible booking type system
                return ($type_config->booking_type && $type_config->booking_type !== 'none');
            }
        }

        // Fallback to old system for backward compatibility
        return in_array($listing_type, ['service', 'rental', 'event']);
    }

    /**
     * Get booking type configuration for listing
     * 
     * @param int $listing_id Listing post ID
     * @return object|null Booking configuration
     */
    public function get_booking_config($listing_id)
    {
        $listing_type = get_post_meta($listing_id, '_listing_type', true);
        
        if ($this->custom_types_manager) {
            $type_config = $this->custom_types_manager->get_listing_type_by_slug($listing_type);
            
            if ($type_config) {
                return (object) [
                    'booking_type' => $type_config->booking_type,
                    'booking_features' => json_decode($type_config->booking_features, true) ?: [],
                    'listing_type' => $listing_type,
                    'type_config' => $type_config
                ];
            }
        }

        // Fallback configuration for default types
        $default_configs = [
            'service' => [
                'booking_type' => 'single_day',
                'booking_features' => ['time_slots', 'services', 'calendar']
            ],
            'rental' => [
                'booking_type' => 'date_range', 
                'booking_features' => ['date_range', 'hourly_picker', 'services', 'calendar']
            ],
            'event' => [
                'booking_type' => 'tickets',
                'booking_features' => ['tickets', 'services']
            ]
        ];

        if (isset($default_configs[$listing_type])) {
            return (object) array_merge($default_configs[$listing_type], [
                'listing_type' => $listing_type,
                'type_config' => null
            ]);
        }

        return null;
    }

  

}