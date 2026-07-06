<?php

if(isset($_GET[$data->name .'_input'])) {
	$value = stripslashes(sanitize_text_field($_GET[$data->name.'_input']));
    
} else {
	if(isset($data->default) && !empty($data->default)){
		$value = $data->default;
	} else {
		$value = '';	
	}
}
    
    // Get button action from field data
    $button_action = isset($data->button_action) ? $data->button_action : 'quick_picks';

?>

<div class="<?php if(isset($data->class)) { echo esc_attr($data->class); } ?> <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
    <?php 
    // Always render AI search functionality, but button visibility depends on action
    if (shortcode_exists('listeo_ai_search')) {
        $shortcode_atts = array(
            'placeholder' => isset($data->placeholder) ? $data->placeholder : __('Search anything, just ask!', 'ai-chat-search'),
            'button_text' =>  __('AI Quick Picks', 'ai-chat-search'),
            'show_toggle' => 'true',
            'limit' => '10',
            'value' => $value,
            'listing_types' => 'all',
            'button_action' => $button_action
        );

        echo do_shortcode('[listeo_ai_search placeholder="' . esc_attr($shortcode_atts['placeholder']) . '" button_text="' . esc_attr($shortcode_atts['button_text']) . '" show_toggle="' . esc_attr($shortcode_atts['show_toggle']) . '" limit="' . esc_attr($shortcode_atts['limit']) . '" listing_types="' . esc_attr($shortcode_atts['listing_types']) . '" button_action="' . esc_attr($shortcode_atts['button_action']) . '" value="' . esc_attr($shortcode_atts['value']) . '"]');
    } else {
        // Fallback if AI search plugin is not active
        ?>
        <input autocomplete="off" name="<?php echo esc_attr($data->name);?>" id="<?php echo esc_attr($data->name);?>" class="<?php echo esc_attr($data->name);?>" type="text" placeholder="<?php echo esc_attr($data->placeholder);?>" value="<?php if(isset($value)){ echo esc_attr($value);  } ?>"/>
        
        <?php
    }
    ?>
</div>
