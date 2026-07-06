<div class="listing-added-notice">
	<div class="booking-confirmation-page">
		<i class="fa fa-check-circle"></i>
		<h2 class="margin-top-30"><?php esc_html_e('Thanks for your submission!','listeo_core') ?></h2>
		<p><?php // Successful

		switch ( get_post_status( $data->id ) ) {
			case 'publish' :
				esc_html_e( 'Your listing has been published.', 'listeo_core' );
			break;				
			case 'pending_payment' :
				esc_html_e( 'Your listing has been saved and is pending payment. It will be published once the order is completed', 'listeo_core' );
			break;			
			case 'pending' :
			case 'draft' :
				esc_html_e( 'Your listing has been saved and is awaiting admin approval', 'listeo_core' );
			break;
			default :
				esc_html_e( 'Your changes have been saved.', 'listeo_core' );
			break;
		} ?>
		</p>
		<?php if(get_post_status( $data->id ) == 'publish') : ?>
			<a class="button margin-top-30" href="<?php echo get_permalink( $data->id ); ?>"><?php  esc_html_e( 'View &rarr;', 'listeo_core' );  ?></a>
		<?php endif; ?>
	</div>
</div>

