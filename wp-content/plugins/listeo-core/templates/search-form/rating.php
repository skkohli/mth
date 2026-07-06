<?php

if (isset($_GET['rating-filter'])) {
    $selected = array($_GET['rating-filter']);
  
    if (is_array($selected[0])) {
        $selected = $selected[0];
    }
} else {
    $selected = array();
}

?>

    <div class="rating-filter <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
        <label class="rating-filter__field">
            <input <?php echo $is_selected = in_array('any', $selected) ? ' checked="checked" ' : ''; ?> type="radio" name="rating-filter" value="any" class="rating-filter__input" checked>
            <span class="rating-filter__text"><?php esc_html_e('Any', 'listeo_core'); ?></span>
        </label>
        <label class="rating-filter__field">
            <input <?php echo $is_selected = in_array('3', $selected) ? ' checked="checked" ' : ''; ?>type="radio" name="rating-filter" value="3" class="rating-filter__input" >
            <span class="rating-filter__text"><i class="fa fa-star-o"></i> 3.0+</span>
        </label>
        <label class="rating-filter__field">
            <input <?php echo $is_selected = in_array('4', $selected) ? ' checked="checked" ' : ''; ?> type="radio" name="rating-filter" value="4" class="rating-filter__input">
            <span class="rating-filter__text"><i class="fa fa-star-o"></i> 4.0+</span>
        </label>
        <label class="rating-filter__field">
            <input <?php echo $is_selected = in_array('4.5', $selected) ? ' checked="checked" ' : ''; ?> type="radio" name="rating-filter" value="4.5" class="rating-filter__input">
            <span class="rating-filter__text"><i class="fa fa-star-o"></i> 4.5+</span>
        </label>
    </div>
