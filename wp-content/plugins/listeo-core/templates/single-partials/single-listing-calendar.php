<?php
// if $data is not null
if (!empty($data)) {
	// extract $data as variables
	$show_title = $data->show_title;
} else {
	$show_title = true;
}
?>
<?php if($show_title) { ?>
<h2 class="listing-desc-headline"><?php echo esc_html_e('Calendar view','listeo_core')?></h2>
<?php } ?>
	<div id="calendar-wrapper">
	    <div id='calendar' data-listing-id="<?php echo get_the_ID(); ?>"></div>
	</div>