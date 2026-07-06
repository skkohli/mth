<?php
/**
 * Submit-listing repeater for `_mandatory_fees`.
 *
 * Frontend equivalent of the admin CMB2 group — owners get the full
 * repeatable-fees-engine schema (id, title, type, price, frequency,
 * conditions, description) instead of just title/price. Generic
 * `repeatable.php` rendered only those two and would have flattened
 * the schema on resave; the merge-by-id sanitizer in
 * `class-listeo-core-submit.php` prevents data loss but owners still
 * need to see and edit the richer fields.
 *
 * Uses a div-based layout (not the legacy `<table>` markup of
 * repeatable.php) with a dedicated add/remove handler in frontend.js
 * keyed off `.listeo-mandatory-fees-list`. This sidesteps style
 * collisions with the existing `repeatable-list-item` rules and gives
 * us a predictable grid for the main row plus a collapsible conditions
 * panel.
 *
 * No per-row "Optional" toggle — the section is "Mandatory Fees"; see
 * feedback memory `feedback_no_optional_in_mandatory_section.md`.
 *
 * @var object $data {
 *     @type string $key   Field key (always `_mandatory_fees`).
 *     @type array  $field Field configuration with `value` containing saved fees.
 * }
 *
 * @package Listeo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$field = $data->field;
$key   = $data->key;
$value = isset( $field['value'] ) && is_array( $field['value'] ) ? $field['value'] : array();

// Advanced UI (type / frequency) only renders when LBP is active.
// Core-only sites see the simpler legacy layout: title + price.
$fees_advanced = function_exists( 'listeo_fees_advanced_ui_enabled' ) && listeo_fees_advanced_ui_enabled();

$types = function_exists( 'listeo_fee_types' ) ? listeo_fee_types() : array(
    'flat'    => __( 'Flat amount', 'listeo_core' ),
    'percent' => __( 'Percentage', 'listeo_core' ),
);
$frequencies = function_exists( 'listeo_fee_frequencies' ) ? listeo_fee_frequencies() : array(
    'per_stay'            => __( 'Per booking', 'listeo_core' ),
    'per_night'           => __( 'Per night', 'listeo_core' ),
    'per_guest'           => __( 'Per guest', 'listeo_core' ),
    'per_guest_per_night' => __( 'Per guest, per night', 'listeo_core' ),
    'per_hour'            => __( 'Per hour', 'listeo_core' ),
    'per_ticket'          => __( 'Per ticket', 'listeo_core' ),
);

$currency_abbr   = get_option( 'listeo_currency' );
$currency_symbol = Listeo_Core_Listing::get_currency_symbol( $currency_abbr );

/**
 * Render a single fee row. Used for both stored rows and the empty
 * starter row when the listing has no fees yet. The JS cloner in
 * frontend.js uses the LAST row in the list as a template; values are
 * cleared on clone so the starter row is functionally identical to a
 * blank-template row.
 */
$render_row = function ( $i, $row ) use ( $key, $types, $frequencies, $currency_symbol, $fees_advanced ) {
    $row   = is_array( $row ) ? $row : array();
    $rid   = isset( $row['id'] ) ? $row['id'] : '';
    $title = isset( $row['title'] ) ? $row['title'] : '';
    $price = isset( $row['price'] ) ? $row['price'] : '';
    $type  = isset( $row['type'] ) ? $row['type'] : 'flat';
    $freq  = isset( $row['frequency'] ) ? $row['frequency'] : 'per_stay';
    $desc  = isset( $row['description'] ) ? $row['description'] : '';
    $name  = $key . '[' . $i . ']';
    // Conditions are intentionally not exposed in the frontend submit
    // form (too much surface area for a typical owner). The engine
    // still respects them — admins can configure conditions per fee in
    // the CMB2 metabox, and any stored values round-trip through the
    // merge-by-id sanitizer untouched.
    //
    // When LBP is not active (`$fees_advanced` false), the row also
    // hides type + frequency — only title + price + description show.
    // Hidden `[type]` and `[frequency]` inputs preserve any previously
    // stored values so the engine still knows what they are.
    ?>
    <div class="listeo-fee-row<?php echo $fees_advanced ? '' : ' listeo-fee-row--simple'; ?>" data-iterator="<?php echo esc_attr( $i ); ?>">
        <input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $rid ); ?>">

        <?php if ( ! $fees_advanced ) : ?>
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>[type]" value="<?php echo esc_attr( $type ); ?>">
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>[frequency]" value="<?php echo esc_attr( $freq ); ?>">
        <?php endif; ?>

        <div class="listeo-fee-main">
            <input type="text"
                   class="listeo-fee-input listeo-fee-input-title"
                   placeholder="<?php esc_attr_e( 'Title (e.g. Cleaning fee)', 'listeo_core' ); ?>"
                   name="<?php echo esc_attr( $name ); ?>[title]"
                   value="<?php echo esc_attr( $title ); ?>">

            <?php if ( $fees_advanced ) : ?>
                <select class="listeo-fee-input listeo-fee-input-type"
                        name="<?php echo esc_attr( $name ); ?>[type]">
                    <?php foreach ( $types as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php
            // The price field's placeholder + trailing glyph swap
            // based on the type select to the right. Currency on flat
            // ("Price"), percent symbol on percent ("Rate"). Both the
            // currency symbol and the localized labels are stashed in
            // data attributes so the JS handler doesn't have to call
            // back into PHP for them.
            $is_percent = ( 'percent' === $type );
            $price_placeholder = $is_percent
                ? __( 'Rate', 'listeo_core' )
                : __( 'Price', 'listeo_core' );
            ?>
            <div class="listeo-fee-input listeo-fee-input-price"
                 data-flat-symbol="<?php echo esc_attr( $currency_symbol ); ?>"
                 data-percent-symbol="%"
                 data-flat-placeholder="<?php esc_attr_e( 'Price', 'listeo_core' ); ?>"
                 data-percent-placeholder="<?php esc_attr_e( 'Rate', 'listeo_core' ); ?>">
                <input type="number"
                       step="0.01"
                       min="0"
                       placeholder="<?php echo esc_attr( $price_placeholder ); ?>"
                       name="<?php echo esc_attr( $name ); ?>[price]"
                       value="<?php echo esc_attr( $price ); ?>">
                <span class="listeo-fee-currency"><?php echo esc_html( $is_percent ? '%' : $currency_symbol ); ?></span>
            </div>

            <?php if ( $fees_advanced ) : ?>
                <select class="listeo-fee-input listeo-fee-input-frequency"
                        name="<?php echo esc_attr( $name ); ?>[frequency]">
                    <?php foreach ( $frequencies as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $freq, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button type="button" class="listeo-fee-btn listeo-fee-remove" title="<?php esc_attr_e( 'Remove fee', 'listeo_core' ); ?>">
                <i class="fa fa-remove" aria-hidden="true"></i>
            </button>
        </div>

        <input type="text"
               class="listeo-fee-input listeo-fee-input-description"
               placeholder="<?php esc_attr_e( 'Short description (optional)', 'listeo_core' ); ?>"
               name="<?php echo esc_attr( $name ); ?>[description]"
               value="<?php echo esc_attr( $desc ); ?>">
    </div>
    <?php
};
?>

<div class="listeo-mandatory-fees-list" data-field-name="<?php echo esc_attr( $key ); ?>">
    <?php
    if ( ! empty( $value ) ) {
        $i = 0;
        foreach ( $value as $row ) {
            $render_row( $i, $row );
            $i++;
        }
    } else {
        $render_row( 0, array() );
    }
    ?>
    <button type="button" class="button listeo-fee-add">
        + <?php esc_html_e( 'Add Fee', 'listeo_core' ); ?>
    </button>
</div>
