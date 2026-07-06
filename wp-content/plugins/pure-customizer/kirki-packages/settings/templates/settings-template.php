<?php
/**
 * Settings page template.
 *
 * @package Kirki
 */

defined( 'ABSPATH' ) || die( "Can't access directly" );

return function () {
	?>

	<div class="wrap heatbox-wrap kirki-settings-page" data-setup-udb-nonce="<?php echo esc_attr( wp_create_nonce( 'Kirki_Prepare_Install_Udb' ) ); ?>">

		<div class="heatbox-container heatbox-container-center heatbox-column-container">

			<div class="heatbox-main heatbox-panel-wrapper">

				<!-- Faking H1 tag to place admin notices -->
				<h1 style="display: none;"></h1>

				<div class="heatbox-admin-panel kirki-settings-panel">
					<?php
					require __DIR__ . '/metaboxes/clear-font-cache.php';
					
					?>

				</div>


			</div>

			<div class="heatbox-sidebar">
				<?php require __DIR__ . '/metaboxes/documentation.php'; ?>
			</div>

		</div>

	</div>

	<?php
};
