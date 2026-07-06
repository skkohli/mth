<div class="notification closeable notice">
	<p><strong><?php esc_html_e('Notice!', 'listeo_core'); ?></strong> <?php esc_html_e("This is preview of listing you've submitted, please confirm or edit your submission using buttons at the end of that page.", 'listeo_core'); ?></p><a class="close" href="#"></a>
</div>

<div class="listing_preview_container">

	<?php
	$template_loader = new Listeo_Core_Template_Loader;
	$post = get_post();
	$post_id = $post->ID;
	?>
	<?php


	$gallery_style = 'content';
	$listing_logo = get_post_meta($post_id, '_listing_logo', true);
	if ($gallery_style == 'top') :
		$template_loader->get_template_part('single-partials/single-listing', 'gallery');
	endif; ?>
	<div id="titlebar" class="listing-titlebar">
		<?php
		if ($listing_logo) { ?>
			<div class="listing-logo"> <img src="<?php echo $listing_logo; ?>" alt=""></div>
		<?php } ?>
		<div class="listing-titlebar-title">
			<div class="listing-titlebar-tags">
				<?php
				$listing_type = get_post_meta(get_the_ID(), '_listing_type', true);
				$terms = get_the_terms(get_the_ID(), 'listing_category');
				if ($terms && !is_wp_error($terms)) :
					$categories = array();
					foreach ($terms as $term) {

						$categories[] = sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url(get_term_link($term)),
							esc_html($term->name)
						);
					}

					$categories_list = join(", ", $categories);
				?>
					<span class="listing-tag">
						<?php echo ($categories_list) ?>
					</span>
				<?php endif; ?>
				<?php
				switch ($listing_type) {
					case 'service':
						$type_terms = get_the_terms(get_the_ID(), 'service_category');
						$taxonomy_name = 'service_category';
						break;
					case 'rental':
						$type_terms = get_the_terms(get_the_ID(), 'rental_category');
						$taxonomy_name = 'rental_category';
						break;
					case 'event':
						$type_terms = get_the_terms(get_the_ID(), 'event_category');
						$taxonomy_name = 'event_category';
						break;
					case 'classifieds':
						$type_terms = get_the_terms(get_the_ID(), 'classifieds_category');
						$taxonomy_name = 'classifieds_category';
						break;

					default:
						# code...
						break;
				}
				if (isset($type_terms)) {
					if ($type_terms && !is_wp_error($type_terms)) :
						$categories = array();
						foreach ($type_terms as $term) {
							$categories[] = sprintf(
								'<a href="%1$s">%2$s</a>',
								esc_url(get_term_link($term)),
								esc_html($term->name)
							);
						}

						$categories_list = join(", ", $categories);
				?>
						<span class="listing-tag">
							<?php echo ($categories_list) ?>
						</span>
				<?php endif;
				}
				?>
				<?php if (get_the_listing_price_range()) : ?>
					<span class="listing-pricing-tag"><i class="fa fa-<?php echo esc_attr(get_option('listeo_price_filter_icon', 'tag')); ?>"></i><?php echo get_the_listing_price_range(); ?></span>
				<?php endif; ?>
			</div>

			<h1><?php the_title(); ?></h1>
			<?php if (get_the_listing_address()) : ?>
				<span>
					<a href="#listing-location" class="listing-address">
						<i class="fa fa-map-marker"></i>
						<?php the_listing_address(); ?>
					</a>
				</span>
			<?php endif; ?>
			<?php 
			// Use the new combined rating display function
			$rating_data = listeo_get_rating_display($post->ID);
			$rating = $rating_data['rating'];
			$number = $rating_data['count'];
			
			if (isset($rating) && $rating > 0) :
				$rating_type = get_option('listeo_rating_type', 'star');
				if ($rating_type == 'numerical') { ?>
					<div class="numerical-rating" data-rating="<?php $rating_value = esc_attr(round($rating, 1));
																printf("%0.1f", $rating_value); ?>">
					<?php } else { ?>
						<div class="star-rating" data-rating="<?php echo $rating; ?>">
						<?php } ?>
						<?php if($number > 0){ ?>
						
						<div class="rating-counter">(<?php printf(_n('%s review', '%s reviews', $number, 'listeo_core'), number_format_i18n($number));  ?>)</div>
						<?php } ?>
						</div>
					<?php endif; ?>
					</div>
		</div>
		<div id="listing-nav" class="listing-nav-container">
			<ul class="listing-nav">
				<li><a href="#listing-overview" class="active"><?php esc_html_e('Overview', 'listeo_core'); ?></a></li>
				<?php if ($gallery_style == 'content') : ?><li><a href="#listing-gallery"><?php esc_html_e('Gallery', 'listeo_core'); ?></a></li>
				<?php endif;
				$_menu = get_post_meta(get_the_ID(), '_menu', 1);
				if (!empty($_menu)) { ?>
					<li><a href="#listing-pricing-list"><?php esc_html_e('Pricing', 'listeo_core'); ?></a></li>
				<?php } ?>
				<?php if (class_exists('WeDevs_Dokan') && get_post_meta(get_the_ID(), '_store_section_status', 1)) : ?><li><a href="#listing-store"><?php esc_html_e('Store', 'listeo_core'); ?></a></li><?php endif; ?>
				<?php $latitude = get_post_meta($post->ID, '_geolocation_lat', true);
				if (!empty($latitude)) :  ?>
					<li><a href="#listing-location"><?php esc_html_e('Location', 'listeo_core'); ?></a></li>
				<?php
				endif;
				?>
			</ul>
		</div>
		<!-- Overview -->
		<div id="listing-overview" class="listing-section">
			<?php $template_loader->get_template_part('single-partials/single-listing', 'main-details');  ?>

			<!-- Description -->

			<?php do_action('listeo/single-listing/before-content'); ?>
			<?php the_content(); ?>
			<?php do_action('listeo/single-listing/after-content'); ?>
			<?php $template_loader->get_template_part('single-partials/single-listing', 'socials');  ?>
			<?php $template_loader->get_template_part('single-partials/single-listing', 'features');  ?>
		</div>

		<?php do_action('listeo/single-listing/after-overview'); ?>

		<style>
			#listing-gallery {
				width: calc(100vw - 460px)
			}

			@media (max-width: 992px) {
				#listing-gallery {
					width: calc(100vw - 160px)
				}
			}
		</style>
		<!-- <div class="row">
			<div class="col-md-12"> -->
		<?php if ($gallery_style == 'content') : $template_loader->get_template_part('single-partials/single-listing', 'gallery-content');
		endif; ?>
		<!-- </div>
			
		</div> -->

		<?php $template_loader->get_template_part('single-partials/single-listing', 'pricing');  ?>
		<?php if (class_exists('WeDevs_Dokan') &&  get_post_meta(get_the_ID(), '_store_section_status', 1)) :   $template_loader->get_template_part('single-partials/single-listing', 'store');
		endif; ?>
		<?php $template_loader->get_template_part('single-partials/single-listing', 'opening');  ?>
		<?php $template_loader->get_template_part('single-partials/single-listing', 'video');  ?>
		<?php $template_loader->get_template_part('single-partials/single-listing', 'location');
		$template_loader->get_template_part('single-partials/single-listing', 'faq');
		//$template_loader->get_template_part('single-partials/single-listing', 'other-listings'); ?>

	</div>
	<?php if (get_option('listeo_edit_listing_requires_approval')) { ?>
		<div class="notification closeable notice">
			<?php esc_html_e('Editing listing requires admin approval, your listing will be unpublished if you Save Changes.', 'listeo_core'); ?>
		</div>
	<?php } ?>

	<form method="post" id="listing_preview">
		<div class="row margin-bottom-30">
			<div class="col-md-12">

				<button type="submit" value="edit_listing" name="edit_listing" class="button border margin-top-20"><i class="fa fa-edit"></i> <?php esc_attr_e('Edit listing', 'listeo_core'); ?></button>
				<!-- <input type="submit" name="continue"> -->
				<button type="submit" value="<?php echo apply_filters('submit_listing_step_preview_submit_text', __('Submit Listing', 'listeo_core')); ?>" name="continue" class="button margin-top-20"><i class="fa fa-check"></i>
					<?php
					if (isset($_GET["action"]) && $_GET["action"] == 'edit') {
						esc_html_e('Save Changes', 'listeo_core');
					} else {
						echo apply_filters('submit_listing_step_preview_submit_text', __('Submit Listing', 'listeo_core'));
					} ?>
				</button>

				<input type="hidden" name="listing_id" value="<?php echo esc_attr($data->listing_id); ?>" />
				<input type="hidden" name="step" value="<?php echo esc_attr($data->step); ?>" />
				<input type="hidden" name="listeo_core_form" value="<?php echo $data->form; ?>" />
			</div>
		</div>
	</form>