<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

$field = $data->field;

$key = $data->key;
$value = isset($field['value']) ? $field['value'] : false;
$default = isset($field['default']) ? $field['default'] : false;

?>
<?php if (isset($field['form_type']) && $field['form_type'] == 'registration') { ?>
	<label class="listeo_core-switch"><?php echo $field['placeholder']; ?></label>
<?php } ?>

<div class="listeo-radios in-row margin-bottom-20">

	<?php foreach ($field['options'] as $slug => $name) : ?>
		<div>
			<input <?php if (!empty($field['required'])) echo 'required';  if($default === $slug) echo 'checked'; ?> id="<?php echo esc_html($slug) ?>" type="radio" name="<?php echo $key; ?>" <?php checked($value, $slug) ?> value="<?php echo esc_html($slug); ?>">
			<label for="<?php echo esc_html($slug) ?>"><?php echo esc_html($name) ?></label>
		</div>
	<?php endforeach; ?>

</div>