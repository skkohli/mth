<?php
/**
 * Listeo Add-ons — single card template.
 *
 * Variables expected (set by Listeo_Core_Addons_Dashboard::render_card):
 *   $addon — normalized add-on array with extra keys:
 *     'install_state' → 'active' | 'inactive' | 'not_installed'
 *     'filter_state'  → 'active' | 'inactive' | 'available' | 'paid'
 *
 * Design: variant B (PRO ribbon + dark "Buy now" + inline price).
 *
 * @package Listeo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $addon ) || ! is_array( $addon ) ) {
	return;
}

$filter_state = isset( $addon['filter_state'] ) ? $addon['filter_state'] : 'available';
$is_paid      = ( 'paid' === $filter_state );
$discount     = isset( $addon['discount'] ) && is_array( $addon['discount'] )
	? $addon['discount']
	: array( 'active' => false, 'value' => 0, 'code' => '' );
$price        = isset( $addon['price'] ) ? trim( (string) $addon['price'] ) : '';

$learn_more_url = Listeo_Core_Addons_Dashboard::get_addon_homepage_url( $addon );
$buy_url        = $learn_more_url;
if ( $is_paid && ! empty( $discount['active'] ) && ! empty( $discount['code'] ) && $buy_url ) {
	$buy_url = add_query_arg( 'code', rawurlencode( $discount['code'] ), $buy_url );
}

$activate_url   = '';
$deactivate_url = '';
if ( ! empty( $addon['plugin_file'] ) && in_array( $addon['install_state'], array( 'active', 'inactive' ), true ) ) {
	$activate_url = wp_nonce_url(
		admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $addon['plugin_file'] ) ),
		'activate-plugin_' . $addon['plugin_file']
	);
	$deactivate_url = wp_nonce_url(
		admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $addon['plugin_file'] ) ),
		'deactivate-plugin_' . $addon['plugin_file']
	);
}

// Badge meta per filter state. In variant B the paid card uses the same
// gray "Available" badge as a free card — the PRO ribbon is the differentiator.
$badge_map = array(
	'active'    => array( 'class' => 'lba-badge--green', 'label' => __( 'Active', 'listeo_core' ) ),
	'inactive'  => array( 'class' => 'lba-badge--amber', 'label' => __( 'Installed · Inactive', 'listeo_core' ) ),
	'available' => array( 'class' => 'lba-badge--gray',  'label' => __( 'Available', 'listeo_core' ) ),
	'paid'      => array( 'class' => 'lba-badge--gray',  'label' => __( 'Available', 'listeo_core' ) ),
);
$badge = isset( $badge_map[ $filter_state ] ) ? $badge_map[ $filter_state ] : $badge_map['available'];
$icon_url = Listeo_Core_Addons_Dashboard::get_addon_icon_url( $addon );
?>
<article
	class="lba-card"
	data-filter-state="<?php echo esc_attr( $filter_state ); ?>"
	data-slug="<?php echo esc_attr( $addon['slug'] ); ?>"
	data-learn-more-url="<?php echo esc_url( $learn_more_url ); ?>"
>
	<?php if ( $is_paid ) : ?>
		<span class="lba-pro">PRO</span>
	<?php endif; ?>

	<div class="lba-card__head">
		<div class="lba-ico<?php echo $is_paid ? ' lba-ico--paid' : ''; ?>">
			<img src="<?php echo esc_url( $icon_url ); ?>" alt="" />
		</div>
		<div class="lba-card__heading">
			<h3 class="lba-card__title"><?php echo esc_html( $addon['name'] ); ?></h3>
			<span class="lba-badge <?php echo esc_attr( $badge['class'] ); ?>">
				<?php echo esc_html( $badge['label'] ); ?>
			</span>
		</div>
	</div>

	<p class="lba-card__desc"><?php echo esc_html( $addon['description'] ); ?></p>

	<?php if ( $is_paid && ! empty( $discount['active'] ) && ! empty( $discount['code'] ) && (int) $discount['value'] > 0 ) : ?>
		<div class="lba-discount">
			<span>
				<?php
				printf(
					/* translators: %d: discount percentage. */
					esc_html__( 'Save %d%% with code', 'listeo_core' ),
					(int) $discount['value']
				);
				?>
			</span>
			<button type="button" class="lba-discount__code" data-copy="<?php echo esc_attr( $discount['code'] ); ?>">
				<?php echo esc_html( $discount['code'] ); ?>
				<span class="lba-discount__copy"><?php esc_html_e( 'Copy', 'listeo_core' ); ?></span>
			</button>
		</div>
	<?php endif; ?>

	<div class="lba-card__actions lba-actions">
		<?php if ( 'active' === $filter_state ) : ?>

			<button type="button" class="lba-btn lba-btn--ghost" disabled aria-disabled="true">
				<?php echo Listeo_Core_Addons_Dashboard::icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Active', 'listeo_core' ); ?>
			</button>
			<a class="lba-link" href="<?php echo esc_url( $deactivate_url ); ?>">
				<?php esc_html_e( 'Deactivate', 'listeo_core' ); ?>
			</a>

		<?php elseif ( 'inactive' === $filter_state ) : ?>

			<a class="lba-btn lba-btn--primary" href="<?php echo esc_url( $activate_url ); ?>">
				<?php echo Listeo_Core_Addons_Dashboard::icon( 'power', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Activate', 'listeo_core' ); ?>
			</a>

		<?php elseif ( 'paid' === $filter_state ) : ?>

			<a class="lba-btn lba-btn--dark" href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener">
				<?php echo Listeo_Core_Addons_Dashboard::icon( 'bag', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Buy now', 'listeo_core' ); ?>
			</a>
			<?php if ( '' !== $price ) : ?>
				<span class="lba-price-inline"><?php echo esc_html( $price ); ?></span>
			<?php endif; ?>

		<?php elseif ( empty( $addon['license_ok'] ) ) : ?>

			<button
				type="button"
				class="lba-btn lba-btn--ghost"
				disabled
				aria-disabled="true"
				title="<?php esc_attr_e( 'Verify your Listeo license to install add-ons.', 'listeo_core' ); ?>"
			>
				<?php echo Listeo_Core_Addons_Dashboard::icon( 'shield', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'License required', 'listeo_core' ); ?>
			</button>
			<a class="lba-link" href="<?php echo esc_url( $addon['license_url'] ); ?>">
				<?php esc_html_e( 'Open License page', 'listeo_core' ); ?>
			</a>

		<?php else : ?>

			<button
				type="button"
				class="lba-btn lba-btn--primary lba-install"
				data-slug="<?php echo esc_attr( $addon['slug'] ); ?>"
				data-plugin-file="<?php echo esc_attr( $addon['plugin_file'] ); ?>"
			>
				<?php echo Listeo_Core_Addons_Dashboard::icon( 'down', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Install', 'listeo_core' ); ?>
			</button>

		<?php endif; ?>

		<?php if ( $learn_more_url ) : ?>
			<a class="lba-link" href="<?php echo esc_url( $learn_more_url ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Learn more', 'listeo_core' ); ?>
			</a>
		<?php endif; ?>
	</div>
</article>
