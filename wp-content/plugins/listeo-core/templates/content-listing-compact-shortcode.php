<?php $template_loader = new Listeo_Core_Template_Loader;
$offer_type = get_the_listing_offer_type($post); ?>
<!-- Listing Item -->

<div class="listing-item compact" <?php listeo_get_geo_data($post); ?>>
	<?php if (listeo_core_is_featured($post->ID)) { ?>
		<i class="featured-icon tooltip right" title="<?php echo esc_attr_e('Featured Listing', 'listeo_core') ?>"></i>
	<?php } ?>
	<a href="<?php the_permalink(); ?>" class="listing-img-container">

		<div class="listing-badges">
			<?php if (listeo_core_is_featured($post->ID)) : ?><span class="featured"><?php esc_html_e('Featured', 'listeo_core'); ?></span><?php endif; ?>
			<?php the_listing_offer_type(); ?>
		</div>

		<div class="listing-img-content">
			<span class="listing-compact-title"><?php the_title(); ?> <i class="price_per_scale"><?php the_listing_price(); ?><?php if ($offer_type == 'rent') {
																																	echo ' / ' . get_post_meta($post->ID, '_rental_period', true);
																																} ?></i></span>
			<?php
			$data = array('class' => 'listing-hidden-content');
			$template_loader->set_template_data($data)->get_template_part('single-partials/single-listing', 'main-details');  ?>

		</div>
		<?php
		$template_loader->get_template_part('content-listing-compact-image');  ?>
	</a>
</div>

<!-- Listing Item / End -->


<?php $template_loader = new Listeo_Core_Template_Loader;
$offer_type = get_the_listing_offer_type($post); ?>
<!-- Listing Item -->
<?php
if (isset($data)) :
	$style        = (isset($data->style)) ? $data->style : '';
endif;

$template_loader = new Listeo_Core_Template_Loader; ?>
<!-- Listing Item -->
<?php if (isset($style) && $style == 'compact') { ?>
	<div class="col-lg-6">
	<?php } ?>
	<!-- Listing Item -->
	<div class="fw-carousel-item">
		<a href="<?php the_permalink(); ?>" class="listing-item-container compact">
			<div class="listing-item">
				<?php
				$template_loader->get_template_part('content-listing-image');  ?>


				<?php if (get_post_meta($post->ID, '_opening_hours_status', true)) {
					if (listeo_check_if_open()) { ?>
						<div class="listing-badge now-open"><?php esc_html_e('Now Open', 'listeo_core'); ?></div>
						<?php } else {
						if (listeo_check_if_has_hours()) { ?>
							<div class="listing-badge now-closed"><?php esc_html_e('Now Closed', 'listeo_core'); ?></div>
						<?php } ?>
				<?php }
				} ?>

				<div class="listing-item-content">
					<?php
					if (!get_option('listeo_disable_reviews')) {
						// Use the new combined rating display function
						$rating_data = listeo_get_rating_display($post->ID);
						$rating = $rating_data['rating'];
						
						if (isset($rating) && $rating > 0) : ?>
							<div class="numerical-rating" data-rating="<?php $rating_value = esc_attr(round((float)$rating, 1));
																		printf("%0.1f", $rating_value); ?>"></div>
					<?php endif;
					} ?>
					<h3><?php the_title(); ?></h3>
					<span><?php the_listing_address(); ?></span>
				</div>
				<span class="like-icon"></span>
			</div>
		</a>
	</div>

	<?php if (isset($style) && $style == 'compact') { ?>
	</div>
<?php } ?>
<!-- Listing Item / End 