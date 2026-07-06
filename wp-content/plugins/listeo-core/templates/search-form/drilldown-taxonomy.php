<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$multi = false;
if (isset($data->multi) && $data->multi) {
    $multi = true;
}


if (isset($_GET[$data->name])) {
    if (is_array($_GET[$data->name])) {
        $selected = $_GET[$data->name];
    } else {
        $selected = sanitize_text_field($_GET[$data->name]);
    }
} else {
    $selected = '';

    if (is_tax($data->taxonomy)) {
        $selected = get_query_var($data->taxonomy);
    }
}

if (empty($selected) && isset($data->default)) {
    $selected = $data->default;
};


if (is_array($selected)) {
    $selected = array_map('sanitize_text_field', $selected);
} else {
    // if it's a string we need to get id of the term
    $selected = sanitize_text_field($selected);
    $term = get_term_by('slug', $selected, $data->taxonomy);
    if ($term) {
        $selected = $term->term_id;
    } else {
        $selected = '';
    }
    // If it's a single value, convert it to an array for consistency
    if (!is_array($selected)) {
        $selected = array($selected);
    }
}



$taxonomy = get_taxonomy($data->taxonomy);
if (isset($data->placeholder) && !empty($data->placeholder)) {
    $menu_label = $data->placeholder;
} else {
    $menu_label = __('Choose ', 'listeo_core') . $taxonomy->labels->singular_name;
}

// Get listing type for filtering if dynamic taxonomies are enabled
$listing_type = null;
if ($data->taxonomy == 'listing_category' && get_option('listeo_dynamic_taxonomies') == 'on') {
    $listing_type = isset($_GET['_listing_type']) ? sanitize_text_field($_GET['_listing_type']) : null;
}

$hide_all = isset($data->hide_all) && $data->hide_all;
$categories = listeo_get_nested_categories($data->taxonomy, $listing_type, $hide_all);
$categories_json = json_encode($categories);

?>

<!-- First drilldown menu instance with custom categories -->
<!-- First drilldown menu instance with custom categories and additional random data -->
<div class="<?php if (isset($data->class)) {
                echo esc_attr($data->class);
            } ?> <?php if (isset($data->css_class)) {
                        echo esc_attr($data->css_class);
                    } ?>">
    <div id="listeo-drilldown-<?php echo esc_attr($data->name); ?>" data-label="<?php echo esc_attr($menu_label); ?>" <?php if (!$multi) { ?> data-single-select="true" <?php } ?> data-name="<?php echo esc_attr($data->name); ?>" class="drilldown-menu" data-categories='<?php echo esc_attr($categories_json); ?>'>
        <?php if (is_array($selected) && !empty($selected)) {
            foreach ($selected as $key => $value) { ?>
                <input type="hidden" name="<?php echo esc_attr($data->name); ?>[]" value="<?php echo esc_attr($value); ?>" class="drilldown-values drilldown-generated">
            <?php }
        } else { ?>
            <input type="hidden" class="drilldown-values" name="<?php echo esc_attr($data->name); ?>">
        <?php } ?>
        <div class=" menu-toggle">
            <span class="menu-label"><?php echo esc_html($menu_label); ?></span>
            <span class="reset-button" style="display:none;">&times;</span>
        </div>
        <div class="menu-panel">
            <div class="menu-levels">
                <!-- Levels will be injected here -->
            </div>
        </div>
    </div>
</div>