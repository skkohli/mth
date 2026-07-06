<?php $template_loader = new Listeo_Core_Template_Loader; ?>
<div class="item">
	<div class="listing-item compact">

		<a href="<?php the_permalink(); ?>" class="listing-img-container">

			<div class="listing-badges">
				<span class="featured"><?php esc_html_e('Featured','listeo_core'); ?></span>
				<?php the_listing_offer_type(); ?>
			</div>

			<div class="listing-img-content">
				<span class="listing-compact-title"><?php the_title(); ?><i><?php the_listing_price(); ?></i></span>
				<?php 
				$data = array( 'class' => 'listing-hidden-content' );
				$template_loader->set_template_data( $data )->get_template_part( 'single-partials/single-listing','main-details' );  ?>
				
			</div>

			<?php the_post_thumbnail(); ?>
		</a>

	</div>
</div>
<!-- Item / End -->
