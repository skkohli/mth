<?php
/**
 * Listeo Forms Editor Field Integration
 * 
 * Integrates AI Search field with Listeo Forms and Fields Editor
 * 
 * @package Listeo_AI_Search
 * @since 1.1.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Field_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook to inject AI search field into forms editor
        add_filter('listeo_search_form_visual_fields', array($this, 'inject_ai_search_field_to_editor'));
        
        // Hook to handle AI search field rendering in search forms
        add_filter('listeo_search_field_ai_search', array($this, 'render_ai_search_field'), 10, 2);
    }
    
    /**
     * Inject AI search field to forms editor
     */
    public function inject_ai_search_field_to_editor($visual_fields) {
        // Add AI search field to the visual fields array
        $visual_fields['ai_search'] = array(
            'class'         => '',
            'id'            => 'ai_search',
            'placeholder'   => __('AI Search Field', 'listeo_core'),
            'name'          => __('AI Search Field', 'listeo_core'),
            'key'           => 'ai_search',
            'default'       => '',
            'priority'      => 1,
            'place'         => 'main',
            'type'          => 'ai_search',
        );
        
        return $visual_fields;
    }
    
    /**
     * Render AI search field in search forms
     */
    public function render_ai_search_field($output, $data) {
        ob_start();
        
        if(isset($_GET[$data->name])) {
            $value = stripslashes(sanitize_text_field($_GET[$data->name]));
        } else {
            if(isset($data->default) && !empty($data->default)){
                $value = $data->default;
            } else {
                $value = '';
            }
        }
        ?>
        
        <div class="<?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
            <?php 
            // Get button action from field data
            $button_action = isset($data->button_action) ? $data->button_action : 'quick_picks';
            
            // Always render the AI search shortcode, button visibility is controlled by button_action
            if (shortcode_exists('listeo_ai_search')) {
                // Get max results from admin settings
                $max_results_setting = get_option('listeo_ai_search_max_results', 10);
                
                $shortcode_atts = array(
                    'placeholder' => isset($data->placeholder) ? $data->placeholder : __('Search anything, just ask!', 'ai-chat-search'),
                    'button_text' => __('AI Quick Picks', 'ai-chat-search'),
                    'show_toggle' => 'true',
                    'limit' => $max_results_setting,
                    'listing_types' => 'all',
                    'button_action' => $button_action
                );
                echo do_shortcode('[listeo_ai_search placeholder="' . esc_attr($shortcode_atts['placeholder']) . '" button_text="' . esc_attr($shortcode_atts['button_text']) . '" show_toggle="' . esc_attr($shortcode_atts['show_toggle']) . '" limit="' . esc_attr($shortcode_atts['limit']) . '" listing_types="' . esc_attr($shortcode_atts['listing_types']) . '" button_action="' . esc_attr($shortcode_atts['button_action']) . '"]');
            } else {
                // Fallback if AI search plugin is not active
                ?>
                <input autocomplete="off" name="<?php echo esc_attr($data->name);?>" id="<?php echo esc_attr($data->name);?>" class="<?php echo esc_attr($data->name);?>" type="text" placeholder="<?php echo esc_attr($data->placeholder);?>" value="<?php if(isset($value)){ echo esc_attr($value);  } ?>"/>
                <?php
            }
            ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
}

// Initialize the integration
new Listeo_AI_Search_Field_Integration();