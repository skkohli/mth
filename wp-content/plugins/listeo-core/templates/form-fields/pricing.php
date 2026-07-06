<!-- Section -->
<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
$field = $data->field;
$key = $data->key;

$currency_abbr = get_option('listeo_currency');
$currency = Listeo_Core_Listing::get_currency_symbol($currency_abbr);

/**
 * Render a single pricing item row. Markup is shared by both the saved-rows
 * loop and the empty/pattern row at the bottom of the template, so they
 * stay in sync — the JS-cloned "Add Item" row in `newMenuItem()` mirrors
 * this same structure.
 *
 * Layout: two-column grid. Left column owns identity (image, title,
 * description, bookable toggle); right column owns booking config (price,
 * charge type, quantity, plus any plugin extras like duration / individual
 * via the `listeo_menu_element_extra_fields` action).
 *
 * @param array  $menu_el  Saved row data (or empty for pattern).
 * @param string $key      Field meta key (usually `_menu`).
 * @param int    $i        Menu section index.
 * @param int    $z        Menu element index inside the section.
 * @param string $currency Currency symbol.
 */
if ( ! function_exists( 'listeo_render_pricing_row' ) ) :
function listeo_render_pricing_row( $menu_el, $key, $i, $z, $currency ) {
	$has_cover = ! empty( $menu_el['cover'] );
	$cover_thumb = $has_cover ? wp_get_attachment_image_src( $menu_el['cover'], 'small' ) : false;
	$extra_service_types = get_option( 'listeo_extra_services_options_type', array() );
	if ( ! is_array( $extra_service_types ) ) { $extra_service_types = array(); }
	$show_charge_type = count( $extra_service_types ) < 4;
	$bookings_disabled = get_option( 'listeo_bookings_disabled' );

	$is_pattern = ( $z === 0 && empty( $menu_el ) ) ? 'pattern' : '';
	?>
	<?php
	$qty_id = sprintf( '%s_%d_%d_qty', sanitize_html_class( $key ), (int) $i, (int) $z );
	?>
	<tr class="pricing-list-item <?php echo esc_attr( $is_pattern ); ?>" data-iterator="<?php echo esc_attr( $z ); ?>">
		<td>
			<div class="fm-move"><i class="sl sl-icon-cursor-move"></i></div>
			<div class="pricing-row-grid">

				<!-- Column 1: Image (no label — the placeholder thumbnail
				     is self-explanatory and the section headers in cols 2/3
				     supply the visual structure). -->
				<div class="pricing-row-image">
					<div class="fm-input pricing-cover">
						<div class="pricing-cover-wrapper" data-tippy-placement="bottom" title="<?php esc_attr_e( 'Change Cover', 'listeo_core' ); ?>">
							<?php if ( $has_cover && $cover_thumb ) : ?>
								<img class="cover-pic" src="<?php echo esc_url( $cover_thumb[0] ); ?>" alt="" />
								<a class="remove-cover" href="#"><?php esc_html_e( 'Remove Cover', 'listeo_core' ); ?></a>
								<input type="hidden" class="menu-cover-id" name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][cover]" value="<?php echo esc_attr( $menu_el['cover'] ); ?>" />
							<?php else : ?>
								<img class="cover-pic" src="<?php echo esc_url( get_template_directory_uri() . '/images/pricing-cover-placeholder.png' ); ?>" alt="" />
							<?php endif; ?>
							<div class="upload-button"></div>
							<input class="file-upload" type="file" accept="image/*" name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][cover]" />
						</div>
					</div>
				</div>

				<!-- Column 2: Basic info — Title / Description / Price + Charge Type -->
				<div class="pricing-row-info pricing-row-section">
					<h4 class="pricing-row-section-title">
						<span class="pricing-row-section-num">1</span>
						<?php esc_html_e( 'Basic service info', 'listeo_core' ); ?>
					</h4>

					<div class="fm-input pricing-name">
						<label class="fm-input-label"><?php esc_html_e( 'Title', 'listeo_core' ); ?></label>
						<input type="text" name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][name]" value="<?php echo isset( $menu_el['name'] ) ? esc_attr( $menu_el['name'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Title', 'listeo_core' ); ?>" />
					</div>

					<div class="fm-input pricing-ingredients">
						<label class="fm-input-label"><?php esc_html_e( 'Description', 'listeo_core' ); ?></label>
						<textarea name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][description]" placeholder="<?php esc_attr_e( 'Description', 'listeo_core' ); ?>" rows="3"><?php echo isset( $menu_el['description'] ) ? esc_textarea( $menu_el['description'] ) : ''; ?></textarea>
					</div>

					<div class="pricing-row-pair">
						<div class="fm-input pricing-price">
							<label class="fm-input-label"><?php esc_html_e( 'Price', 'listeo_core' ); ?></label>
							<input type="number" step="0.01" name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][price]" value="<?php echo isset( $menu_el['price'] ) ? esc_attr( $menu_el['price'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Price (optional)', 'listeo_core' ); ?>" data-unit="<?php echo esc_attr( $currency ); ?>" />
						</div>

						<?php if ( ! $bookings_disabled && $show_charge_type ) : ?>
							<div class="fm-input pricing-bookable-options">
								<label class="fm-input-label"><?php esc_html_e( 'Charge Type', 'listeo_core' ); ?></label>
								<select class="select2-single" name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][bookable_options]">
									<?php if ( ! in_array( 'onetime', $extra_service_types, true ) ) : ?><option <?php if ( isset( $menu_el['bookable_options'] ) ) selected( $menu_el['bookable_options'], 'onetime' ); ?> value="onetime"><?php esc_html_e( 'One time fee', 'listeo_core' ); ?></option><?php endif; ?>
									<?php if ( ! in_array( 'byguest', $extra_service_types, true ) ) : ?><option <?php if ( isset( $menu_el['bookable_options'] ) ) selected( $menu_el['bookable_options'], 'byguest' ); ?> value="byguest"><?php esc_html_e( 'Multiply by guests', 'listeo_core' ); ?></option><?php endif; ?>
									<?php if ( ! in_array( 'bydays', $extra_service_types, true ) ) : ?><option <?php if ( isset( $menu_el['bookable_options'] ) ) selected( $menu_el['bookable_options'], 'bydays' ); ?> value="bydays"><?php esc_html_e( 'Multiply by days', 'listeo_core' ); ?></option><?php endif; ?>
									<?php if ( ! in_array( 'byguestanddays', $extra_service_types, true ) ) : ?><option <?php if ( isset( $menu_el['bookable_options'] ) ) selected( $menu_el['bookable_options'], 'byguestanddays' ); ?> value="byguestanddays"><?php esc_html_e( 'Multiply by guests & days', 'listeo_core' ); ?></option><?php endif; ?>
								</select>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Column 3: Booking config — Bookable / Individual + Duration / Allow Qty + Max Qty -->
				<div class="pricing-row-right pricing-row-section">
					<h4 class="pricing-row-section-title">
						<span class="pricing-row-section-num">2</span>
						<?php esc_html_e( 'Booking & availability settings', 'listeo_core' ); ?>
					</h4>

					<?php if ( ! $bookings_disabled ) :
						$bookable_id = sprintf( '%s_%d_%d_bookable', sanitize_html_class( $key ), (int) $i, (int) $z );
						?>
						<div class="fm-input pricing-bookable">
							<div class="switcher-tip" data-tip-content="<?php esc_attr_e( 'Click to make this item bookable in booking widget', 'listeo_core' ); ?>">
								<input type="checkbox" class="input-checkbox switch_1"
									id="<?php echo esc_attr( $bookable_id ); ?>"
									name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][bookable]"
									<?php checked( isset( $menu_el['bookable'] ) ); ?> value="on" />
								<label for="<?php echo esc_attr( $bookable_id ); ?>" class="pricing-bookable-label"><?php esc_html_e( 'Bookable service', 'listeo_core' ); ?></label>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! $bookings_disabled ) :
						/**
						 * Render additional per-service fields (e.g. Booking Plus
						 * service duration + individual). Fires inside the right
						 * column above the Allow Quantity pair so plugin-rendered
						 * fields stack consistently. The wrapping `.lbp-pricing-extras-row`
						 * div LBP emits is styled as a 2-col grid in
						 * `lbp-service-constraints.css` so Duration + Individual
						 * sit side-by-side, matching the Allow Qty pair below.
						 *
						 * @param array  $menu_el Current service row.
						 * @param string $key     Field meta key (usually `_menu`).
						 * @param int    $i       Menu section index.
						 * @param int    $z       Menu element index.
						 */
						do_action( 'listeo_menu_element_extra_fields', $menu_el, $key, $i, $z );
					endif; ?>

					<?php if ( ! $bookings_disabled && $show_charge_type ) :
						$qty_enabled = isset( $menu_el['bookable_quantity'] );
						?>
						<div class="pricing-row-pair pricing-quantity-pair">
							<div class="fm-input pricing-quantity">
								<div class="checkboxes in-row pricing-quantity-row">
									<input type="checkbox" class="input-checkbox pricing-quantity-enable"
										name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][bookable_quantity]"
										id="<?php echo esc_attr( $qty_id ); ?>"
										<?php checked( $qty_enabled ); ?>
										value="on" />
									<label for="<?php echo esc_attr( $qty_id ); ?>"><?php esc_html_e( 'Allow quantity', 'listeo_core' ); ?></label>
								</div>
								<p class="pricing-field-desc"><?php esc_html_e( 'Customer can pick more', 'listeo_core' ); ?></p>
							</div>
							<div class="fm-input pricing-quantity-max-wrap">
								<label class="fm-input-label"><?php esc_html_e( 'Max quantity', 'listeo_core' ); ?></label>
								<input type="number" class="bookable_quantity_max" step="1" min="1"
									name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_elements][<?php echo esc_attr( $z ); ?>][bookable_quantity_max]"
									value="<?php echo isset( $menu_el['bookable_quantity_max'] ) ? esc_attr( $menu_el['bookable_quantity_max'] ) : ''; ?>"
									placeholder="<?php esc_attr_e( 'Optional', 'listeo_core' ); ?>"
									<?php disabled( ! $qty_enabled ); ?> />
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- Column 4: Delete -->
				<div class="pricing-row-close">
					<div class="fm-close"><a class="delete" href="#"><i class="fa fa-remove"></i></a></div>
				</div>
			</div>
		</td>
	</tr>
	<?php
}
endif;

if ( isset( $field['value'] ) && is_array( $field['value'] ) ) : ?>

	<div class="row">
		<div class="col-md-12">
			<table id="pricing-list-container">
				<?php
				$i = 0;
				foreach ( $field['value'] as $m_key => $menu ) {
					if ( isset( $menu['menu_title'] ) ) { ?>
						<tr class="pricing-list-item pricing-submenu" data-number="<?php echo esc_attr( $i ); ?>">
							<td>
								<div class="fm-move"><i class="sl sl-icon-cursor-move"></i></div>
								<div class="fm-input"><input type="text" name="<?php echo esc_attr( $key ); ?>[<?php echo esc_attr( $i ); ?>][menu_title]" value="<?php echo esc_attr( $menu['menu_title'] ); ?>" placeholder="<?php esc_attr_e( 'Category Title', 'listeo_core' ); ?>"></div>
								<div class="fm-close"><a class="delete" href="#"><i class="fa fa-remove"></i></a></div>
							</td>
						</tr>
					<?php }
					$z = 0;
					if ( isset( $menu['menu_elements'] ) ) {
						foreach ( $menu['menu_elements'] as $el_key => $menu_el ) {
							listeo_render_pricing_row( $menu_el, $key, $i, $z, $currency );
							$z++;
						}
					}
					$i++;
				} ?>
			</table>
			<a href="#" class="button add-pricing-list-item"><?php esc_html_e( 'Add Item', 'listeo_core' ); ?></a>
			<a href="#" class="button add-pricing-submenu"><?php esc_html_e( 'Add Category', 'listeo_core' ); ?></a>
		</div>
	</div>

<?php else : ?>
	<div class="row">
		<div class="col-md-12">
			<table id="pricing-list-container">
				<?php listeo_render_pricing_row( array(), '_menu', 0, 0, $currency ); ?>
			</table>
			<a href="#" class="button add-pricing-list-item"><?php esc_html_e( 'Add Item', 'listeo_core' ); ?></a>
			<a href="#" class="button add-pricing-submenu"><?php esc_html_e( 'Add Category', 'listeo_core' ); ?></a>
		</div>
	</div>
<?php endif; ?>
