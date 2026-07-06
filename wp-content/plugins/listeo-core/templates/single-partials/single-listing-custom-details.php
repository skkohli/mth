<!-- Main Details -->
<?php

$type = get_post_meta($post->ID, '_listing_type', true);

$class = (isset($data->class)) ? $data->class : 'listing-details';

$details = Listeo_Core_Meta_Boxes::meta_boxes_custom();
$details = isset($details['fields']) && is_array($details['fields']) ? array_values($details['fields']) : [];

// Filter out empty fields, but keep headers
$details = array_filter($details, function ($detail) {
	if (!isset($detail['display_type'])) return false;

	// Always allow headers so we can evaluate them later
	if ($detail['display_type'] === 'header') return true;

	// Allow checkbox fields even if processed_value is empty
	//if ($detail['display_type'] === 'checkbox') return true;

	// Allow non-empty values only
	return !empty($detail['processed_value']);
});


if (!empty($details)) :
	$box_open = false;
	$current_taxonomy = null;
	$pending_header = null; // Store header HTML until we know we need it
?>

	<?php foreach ($details as $i => $detail) :
		if (!isset($detail['display_type'])) continue;


		// Handle headers - but don't output them immediately
		if ($detail['display_type'] === 'header') {
			// Determine what taxonomy to look for (if this is a taxonomy header)
			$header_taxonomy = null;
			if (!empty($detail['config']['is_taxonomy_field'])) {
				$header_taxonomy = $detail['config']['taxonomy'];
			}

			// Check if there are visible fields after this header
			if (!has_visible_fields_after($details, $i, $header_taxonomy)) continue;

			// Close any open box
			if ($box_open) {
				echo '</ul>';
				$box_open = false;
			}

			// Store the header HTML for later output
			if ($detail['config']['icon']) {
				$pending_header = '<h4 class="listing-details-header detail-header-with-icon"><i class="' . esc_attr($detail['config']['icon']) . '"></i> ' . esc_html($detail['config']['name']) . '</h4>';
			} else {
				$pending_header = '<h4 class="listing-details-header">' . esc_html($detail['config']['name']) . '</h4>';
			}
			continue;
		}

		// Start new section if it's a taxonomy field and the taxonomy changed
		if (!empty($detail['config']['is_taxonomy_field'])) {
			$taxonomy = $detail['config']['taxonomy'];
			if ($taxonomy !== $current_taxonomy) {
				// Close previous box if open
				if ($box_open) {
					echo '</ul>';
					$box_open = false;
				}
				$current_taxonomy = $taxonomy;
			}
		} else {
			// If it's not a taxonomy field and a taxonomy was active, reset it
			if ($current_taxonomy !== null) {
				if ($box_open) {
					echo '</ul>';
					$box_open = false;
				}
				$current_taxonomy = null;
			}
		}

		// Now we have an actual field to display, so output pending header and open box if needed
		if ($pending_header) {
			echo $pending_header;
			$pending_header = null;
		}

		if (!$box_open) {
			echo '<ul class="' . esc_attr($class) . '" id="' . esc_attr($class) . '">';
			$box_open = true;
		}

		// Render the actual field
		if ($detail['display_type'] === 'checkbox') :
	?>
			<!-- Checkbox Field Template -->
			<li class="<?php echo esc_attr(implode(' ', $detail['css_classes'])); ?>">
				<i class="<?php echo esc_attr($detail['icon']); ?>"></i>
				<div class="checkboxed-single single-property-detail-label-<?php echo esc_attr($detail['config']['id']); ?>">
					<?php echo esc_html($detail['config']['name']); ?>

					<?php if (!empty($detail['config']['default'])) : ?>
						<span><?php echo esc_html($detail['config']['default']); ?></span>
					<?php else : ?>
						<span><?php echo esc_html("Yes", 'listeo_core'); ?></span>
					<?php endif; ?>

				</div>
			</li>

		<?php elseif ($detail['display_type'] === 'area') : ?>
			<!-- Area Field Template -->
			<?php $area_data = $detail['processed_value']; ?>
			<li class="<?php echo esc_attr(implode(' ', $detail['css_classes'])); ?>">
				<i class="<?php echo esc_attr($detail['icon']); ?>"></i>
				<?php if ($detail['is_inverted']) : ?>
					<?php echo esc_html($area_data['scale']); ?>
					<span><?php echo listeo_render_detail_value($detail); ?></span>
				<?php else : ?>
					<span><?php echo listeo_render_detail_value($detail); ?></span>
					<?php echo esc_html($area_data['scale']); ?>
				<?php endif; ?>
			</li>

		<?php elseif ($detail['display_type'] === 'file') : ?>
			<!-- File Field Template -->
			<li class="<?php echo esc_attr(implode(' ', $detail['css_classes'])); ?>">
				<i class="<?php echo esc_attr($detail['icon']); ?>"></i>
				<?php echo listeo_render_detail_value($detail); ?>
			</li>

		<?php else : ?>
			<!-- Regular Field Template -->
			<li class="<?php echo esc_attr(implode(' ', $detail['css_classes'])); ?>">
				<i class="<?php echo esc_attr($detail['icon']); ?>"></i>
				<?php if ($detail['is_inverted']) : ?>
					<span><?php echo listeo_render_detail_value($detail); ?></span>
					<div class="single-property-detail-label-<?php echo esc_attr($detail['config']['id']); ?>">
						<?php echo esc_html($detail['config']['name']); ?>
					</div>
				<?php else : ?>
					<div class="single-property-detail-label-<?php echo esc_attr($detail['config']['id']); ?>">
						<?php echo esc_html($detail['config']['name']); ?>
					</div>
					<span><?php echo listeo_render_detail_value($detail); ?></span>
				<?php endif; ?>
			</li>
		<?php endif; ?>
<?php endforeach;

	// Close any remaining open box
	if ($box_open) {
		echo '</ul>';
	}
endif; ?>
