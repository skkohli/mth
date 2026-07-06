<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
$field = $data->field;
$key = $data->key;
$value = (isset($field['value'])) ? $field['value'] : '';
$allowed_mime_types = array_keys(!empty($field['allowed_mime_types']) ? $field['allowed_mime_types'] : get_allowed_mime_types());



if (!empty($field['value'])) : ?>
	<div class="listeo-uploaded-file">

		<?php
		if (is_numeric($value)) {
			$image_src = wp_get_attachment_url(absint($value));
			$filetype = wp_check_filetype($image_src);
			$extension = $filetype['ext'];
		} else {
			$image_src = $value;
			$extension = !empty($extension) ? $extension : substr(strrchr($image_src, '.'), 1);
		}


		if ('image' === wp_ext2type($extension)) : ?>
			<span class="listeo-uploaded-file-preview"><img src="<?php echo esc_url($image_src); ?>" />
				<a class="remove-uploaded-file" href="#"><?php _e('Remove file', 'listeo_core'); ?></a></span>
		<?php else : ?>
			<span class="listeo-uploaded-file-name"><?php echo esc_html(basename($image_src)); ?>
				<a class="remove-uploaded-file" href="#"><?php _e('Remove file', 'listeo_core'); ?></a></span>
		<?php endif; ?>

		<input type="hidden" <?php if (!empty($field['required'])) echo 'required'; ?> class="input-text" name="current_<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr($value); ?>" />

	</div>

<?php endif; ?>


<!-- Upload Button -->
<div class="uploadButton margin-top-0">

	<?php
	// Get Listeo max file size setting (in MB)
	$listeo_max_size_mb = get_option('listeo_max_filesize', 2);
	// Convert to bytes
	$listeo_max_size_bytes = $listeo_max_size_mb * 1024 * 1024;
	// Get WordPress/server upload size limit in bytes
	$server_max_size = wp_max_upload_size();
	// Use the smaller of the two limits
	$upload_max_size = min($listeo_max_size_bytes, $server_max_size);
	// Convert to MB for display
	$max_size_mb = round($upload_max_size / (1024 * 1024));
	// Get allowed mime types from WordPress
	$allowed_mimes = get_allowed_mime_types();
	// Extract file extensions from mime types for user-friendly display
	$allowed_extensions = array();
	foreach ($allowed_mimes as $ext => $mime) {
		$exts = explode('|', $ext);
		foreach ($exts as $ext_single) {
			$allowed_extensions[] = '.' . $ext_single;
		}
	}
	$allowed_extensions_str = implode(', ', $allowed_extensions);
	// Create a comma-separated string of accepted file types for the 'accept' attribute
	$accept_attribute = implode(',', $allowed_extensions);
	?>
	<input max="<?php echo esc_attr($upload_max_size); ?>"
		data-max-size="<?php echo esc_attr($upload_max_size); ?>"
		onchange="validateFileSize(this, <?php echo esc_attr($upload_max_size); ?>)" accept="<?php echo esc_attr($accept_attribute); ?>" <?php if (empty($field['value'])) :  if (!empty($field['required'])) echo 'required';
																																			endif; ?> class="uploadButton-input" type="file" name="<?php echo esc_attr(isset($field['name']) ? $field['name'] : $key); ?>" id="<?php echo esc_attr($key); ?>" />


	<label class="uploadButton-button ripple-effect" for="<?php echo esc_attr($key); ?>"><?php esc_html_e('Upload Files', 'listeo_core'); ?></label>
	<span class="uploadButton-file-name"><?php printf(esc_html__('Maximum file size: %s.', 'listeo_core'), size_format($upload_max_size)); ?></span>
	
	<div id="file-error-message" class="uploadButton-error-message" style="display: none; color: red;"></div>	
</div>
<script>
	function validateFileSize(input, maxSize) {
		const fileErrorMessage = document.getElementById('file-error-message');
		fileErrorMessage.style.display = 'none';

		if (input.files && input.files[0]) {
			const fileSize = input.files[0].size;

			if (fileSize > maxSize) {
				const maxSizeMB = Math.round(maxSize / (1024 * 1024));
				fileErrorMessage.textContent = `File size exceeds the maximum limit of ${maxSizeMB} MB.`;
				fileErrorMessage.style.display = 'block';
				input.value = ''; // Clear the file input
				return false;
			}

			// Check file extension
			const fileName = input.files[0].name;
			const fileExt = '.' + fileName.split('.').pop().toLowerCase();
			const allowedExtensions = '<?php echo esc_js($allowed_extensions_str); ?>'.split(', ');

			if (!allowedExtensions.includes(fileExt)) {
				fileErrorMessage.textContent = `File type ${fileExt} is not allowed. Accepted file types: <?php echo esc_js($allowed_extensions_str); ?>`;
				fileErrorMessage.style.display = 'block';
				input.value = ''; // Clear the file input
				return false;
			}
		}

		return true;
	}
</script>