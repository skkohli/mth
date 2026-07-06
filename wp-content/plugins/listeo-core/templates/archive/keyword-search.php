<?php 
if(isset($_GET['keyword_search'])) {
	$value = class_exists('Listeo_Core_Search') ? Listeo_Core_Search::sanitize_keyword_search($_GET['keyword_search']) : sanitize_text_field(wp_unslash($_GET['keyword_search']));
} else {
	$value = '';
}
$maxlength = class_exists('Listeo_Core_Search') ? Listeo_Core_Search::get_keyword_search_max_length() : 200;
?>
<form action="" method="GET">
	<!-- Main Search Input -->
	<div class="main-search-input margin-bottom-35">
		<input type="text" class="ico-01" id="keyword_search" name="keyword_search" placeholder="<?php esc_html_e('Enter address e.g. street, city and state or zip','listeo_core') ?>" value="<?php if(isset($value)) { echo esc_attr($value);}?>" maxlength="<?php echo esc_attr($maxlength); ?>"/>
		<button class="button"><?php esc_html_e('Search','listeo_core'); ?></button>
		<?php
		if (class_exists('Listeo_Core_Search')) {
			Listeo_Core_Search::render_keyword_search_bot_fields();
		}
		?>
	</div>
</form>
