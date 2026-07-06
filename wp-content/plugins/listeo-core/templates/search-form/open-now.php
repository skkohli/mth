<?php
if (isset($_GET['open_now'])) {
    $value = sanitize_text_field($_GET['open_now']);
} else {
    $value = '';
} ?>
<div class="checkboxes margin-top-10 <?php if(isset($data->css_class)) { echo esc_attr($data->css_class); }?>">
    <input id="open_now" name="open_now" type="checkbox" value="1" <?php checked($value, '1'); ?> />
    <label for="open_now"><?php echo esc_html__('Open Now', 'listeo_core'); ?></label>
</div>