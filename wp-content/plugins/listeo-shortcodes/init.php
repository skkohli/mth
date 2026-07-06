<?php 
/*
Plugin Name: Listeo Shortcodes
Plugin URI:
Description: Shortcodes required by Listeo Theme.
Version: 1.5.22
Author: Purethemes.net
Author URI: http://purethemes.net
Text Domain: listeo-shortcodes
Domain Path: /languages
*/

// Begin Shortcodes
class ListeoShortcodes {
    
    function __construct() {
    
        //Initialize shortcodes
        add_action( 'init', array( $this, 'add_shortcodes' ) );
        add_action( 'init', array( $this, 'load_localisation' ), 0 );    
    }


    public function load_localisation () {
        load_plugin_textdomain( 'listeo-shortcodes', false,  basename( dirname( __FILE__ ) ) . '/languages/' );

    } 
    function add_shortcodes() {

        $shortcodes = array(
            // 'recent-properties',
            'headline',
            'taxonomy-carousel',
            'taxonomy-grid',
            'iconbox',
            'imagebox',
            'posts-carousel',
            'listings-carousel',
            'flip-banner',
            'testimonials',
            'pricing-table',
            'pricingwrapper',
            'logo-slider',
            // 'fullwidth-property-slider',
            // 'counters',
            // 'counter',
            // 'agents',
            'address-box',
            'button',
            'alertbox',
            'list',
            'pricing-tables-wc',
            'masonry'

        );

        foreach ( $shortcodes as $shortcode ) {
            $function = 'listeo_' . str_replace( '-', '_', $shortcode );
            
            include_once wp_normalize_path( dirname( __FILE__ ) . '/shortcodes/'.$shortcode.'.php' );
            
            add_shortcode( $shortcode, $function);
          
        }
    }

     public static function get_filters($categories = false) {

     
         $terms = get_terms("listing_category");    
        $count = count($terms);

        if ( $count > 0 ){ 
            $output = '
            <div id="filters">
                <ul class="option-set alt">
                    <li><a href="#filter" class="selected" data-filter="*">'.__('All', 'listeo-shortcodes').'</a></li>';
                    foreach ( $terms as $term ) {
                        $output .= '<li><a href="#filter" data-filter=".'.$term->slug.'">'. $term->name .'</a></li>';
                    } 
                $output .= '</ul>
                <div class="clearfix"></div>
            </div>';
            return $output;
        }
    }   

    
}

new ListeoShortcodes();
?>