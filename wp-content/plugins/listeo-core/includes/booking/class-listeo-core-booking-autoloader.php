<?php
/**
 * Listeo Core Booking System Autoloader
 * 
 * Handles loading of booking system classes
 * 
 * @package Listeo Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Listeo_Core_Booking_Autoloader
{
    /**
     * Register autoloader and include files
     */
    public static function init()
    {
        self::include_files();
        self::register_hooks();
        
        // Debug log
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log('Listeo Booking Autoloader: Initialized successfully. Manager class exists: ' . 
        //         (class_exists('Listeo_Core_Booking_Manager') ? 'YES' : 'NO'));
        // }
    }

    /**
     * Include booking system files
     */
    private static function include_files()
    {
        $booking_path = plugin_dir_path(__FILE__);
        
        $files = [
            'class-listeo-core-booking-manager.php',
            'class-listeo-core-booking-widget.php',
            'booking-functions.php'
        ];

        foreach ($files as $file) {
            $file_path = $booking_path . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Register WordPress hooks for booking system
     */
    private static function register_hooks()
    {
        // Initialize booking manager
        add_action('init', [__CLASS__, 'initialize_booking_system'], 15);
        
        // Register booking widget replacement
        add_action('widgets_init', [__CLASS__, 'register_booking_widgets']);
    }

    /**
     * Initialize booking system
     */
    public static function initialize_booking_system()
    {
        // Initialize the booking manager instance
        Listeo_Core_Booking_Manager::instance();
    }

    /**
     * Register booking widgets
     */
    public static function register_booking_widgets()
    {
        // The old Listeo_Core_Booking_Widget class now uses the new modular system internally
        // No need to register separate widgets - seamless backward compatibility achieved
    }
}