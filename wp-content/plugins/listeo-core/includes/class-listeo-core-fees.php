<?php
/**
 * Repeatable Fees Engine
 *
 * Replaces the historical "flat per-booking" treatment of
 * `_mandatory_fees` rows with a small engine that supports:
 *
 *   - Two types:  `flat` (default) or `percent` (of subtotal).
 *   - Six frequencies: `per_stay` (default), `per_night`, `per_guest`,
 *     `per_guest_per_night`, `per_hour`, `per_ticket`.
 *   - Optional conditions per row (min/max guests, min/max nights,
 *     date range, weekends_only, optional listing-type whitelist).
 *
 * Backward compatibility is the design priority: rows that pre-date
 * the engine have no `type`/`frequency`/`conditions` keys, in which
 * case the runtime defaults (`flat` + `per_stay` + no conditions)
 * produce the identical flat-per-booking total the old code did.
 *
 * Stable `id` per row: introduced so add-on plugins (Listeo Booking
 * Plus) can REPLACE a specific fee on a per-resource basis instead of
 * the all-or-nothing override the historical resolver supported.
 * Rows without an `id` get a deterministic fallback derived from the
 * array index — safe to read, only persisted on the next save.
 *
 * Public API:
 *   - listeo_calculate_fee( $fee, $context ): float
 *   - listeo_fee_conditions_pass( $fee, $context ): bool
 *   - listeo_sum_listing_fees( $listing_id, $context ): float
 *   - listeo_normalize_fees( $fees ): array
 *   - listeo_get_listing_fees( $listing_id ): array
 *   - listeo_get_applicable_listing_fees( $listing_id, $context ): array
 *   - listeo_format_fee_line( $fee, $context, $currency_args = [] ): array
 *
 * Filters:
 *   - `listeo_calculate_fee` — amount, fee, context.
 *   - `listeo_fee_conditions_pass` — bool, fee, context.
 *   - `listeo_sum_listing_fees` — total, fees, context, listing_id.
 *   - `listeo_normalize_fees` — fees array, after defaults applied.
 *   - `listeo_fee_frequencies` — frequency_slug → human label map.
 *   - `listeo_fee_types` — type_slug → human label map.
 *
 * @package Listeo_Core
 * @since 2.0.40
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =====================================================================
 * Vocabulary
 * ===================================================================== */

if ( ! function_exists( 'listeo_fees_advanced_ui_enabled' ) ) {
    /**
     * Whether the advanced fees UI (type / frequency / conditions) is
     * exposed in admin + frontend submit forms.
     *
     * The engine itself ALWAYS supports the full schema — this gate is
     * purely about hiding the more complex controls on Listeo Core-only
     * installs where the legacy "title + price + description" UX is
     * enough. With Listeo Booking Plus active, the richer fields show
     * because per-resource overrides, percent-of-subtotal pricing, and
     * scaled frequencies (per night/guest/hour/ticket) are the whole
     * point of the engine.
     *
     * Backward compatibility: stored rows with type/frequency keys are
     * still honored at runtime regardless of this flag — so disabling
     * the UI (e.g. by deactivating LBP) doesn't break pricing math on
     * listings that were configured while LBP was active.
     */
    function listeo_fees_advanced_ui_enabled() {
        return (bool) apply_filters(
            'listeo_fees_advanced_ui_enabled',
            class_exists( 'LBP_Frontend' )
        );
    }
}

if ( ! function_exists( 'listeo_fee_types' ) ) {
    /**
     * Map of supported fee types. Filterable so add-ons can register
     * extra types (e.g. tiered).
     */
    function listeo_fee_types() {
        return apply_filters( 'listeo_fee_types', array(
            'flat'    => __( 'Flat amount', 'listeo_core' ),
            'percent' => __( 'Percentage', 'listeo_core' ),
        ) );
    }
}

if ( ! function_exists( 'listeo_fee_frequencies' ) ) {
    /**
     * Map of supported fee frequencies. Filterable so add-ons can
     * register extra frequencies (e.g. `per_room` for hotels).
     */
    function listeo_fee_frequencies() {
        return apply_filters( 'listeo_fee_frequencies', array(
            'per_stay'            => __( 'Per booking',           'listeo_core' ),
            'per_night'           => __( 'Per night',             'listeo_core' ),
            'per_guest'           => __( 'Per guest',             'listeo_core' ),
            'per_guest_per_night' => __( 'Per guest, per night',  'listeo_core' ),
            'per_hour'            => __( 'Per hour',              'listeo_core' ),
            'per_ticket'          => __( 'Per ticket',            'listeo_core' ),
        ) );
    }
}

/* =====================================================================
 * Normalization
 * ===================================================================== */

if ( ! function_exists( 'listeo_normalize_fee' ) ) {
    /**
     * Apply runtime defaults to a single fee row. Idempotent. The
     * returned array is safe to read from anywhere — every key the
     * engine cares about is guaranteed present.
     *
     * @param array    $fee      The raw row (may be missing keys).
     * @param int|null $position 0-based row position. Used to derive
     *                           the deterministic fallback `id` when
     *                           the row didn't carry one.
     */
    function listeo_normalize_fee( $fee, $position = 0 ) {
        if ( ! is_array( $fee ) ) {
            return array();
        }

        $defaults = array(
            'id'          => '',
            'title'       => '',
            'price'       => '',
            'description' => '',
            'type'        => 'flat',
            'frequency'   => 'per_stay',
            'taxable'     => false,
            'optional'    => false,
            'conditions'  => array(),
        );
        $fee = array_merge( $defaults, $fee );

        // Validate enum-ish fields. Unknown values silently fall back
        // to the safest default so a corrupted/forged row can't break
        // pricing math.
        $types = array_keys( listeo_fee_types() );
        if ( ! in_array( $fee['type'], $types, true ) ) {
            $fee['type'] = 'flat';
        }
        $frequencies = array_keys( listeo_fee_frequencies() );
        if ( ! in_array( $fee['frequency'], $frequencies, true ) ) {
            $fee['frequency'] = 'per_stay';
        }

        // Stable id — required for per-row override targeting. We
        // don't write back here (read-only normalization); persisting
        // happens via the sanitization callback on save.
        if ( '' === $fee['id'] ) {
            $fee['id'] = 'fee_' . absint( $position );
        }

        // Normalize conditions array — accept missing keys.
        $cond_defaults = array(
            'min_guests'    => null,
            'max_guests'    => null,
            'min_nights'    => null,
            'max_nights'    => null,
            'date_from'     => '',
            'date_to'       => '',
            'weekends_only' => false,
        );
        $fee['conditions'] = is_array( $fee['conditions'] )
            ? array_merge( $cond_defaults, $fee['conditions'] )
            : $cond_defaults;

        return $fee;
    }
}

if ( ! function_exists( 'listeo_normalize_fees' ) ) {
    /**
     * Run `listeo_normalize_fee()` over a whole list. Filterable so
     * downstream code can drop or rewrite entries before pricing math.
     */
    function listeo_normalize_fees( $fees ) {
        if ( ! is_array( $fees ) ) {
            return array();
        }
        $out = array();
        foreach ( array_values( $fees ) as $i => $fee ) {
            $normalized = listeo_normalize_fee( $fee, $i );
            if ( '' !== $normalized['title'] || '' !== $normalized['price'] ) {
                $out[] = $normalized;
            }
        }
        return apply_filters( 'listeo_normalize_fees', $out );
    }
}

if ( ! function_exists( 'listeo_get_listing_fees' ) ) {
    /**
     * Read + normalize the mandatory fees stored on a listing. Use
     * this rather than `get_post_meta($id, '_mandatory_fees', true)`
     * directly so the engine's defaults are applied consistently.
     */
    function listeo_get_listing_fees( $listing_id ) {
        $raw = get_post_meta( (int) $listing_id, '_mandatory_fees', true );
        return listeo_normalize_fees( $raw );
    }
}

/* =====================================================================
 * Condition evaluation
 * ===================================================================== */

if ( ! function_exists( 'listeo_fee_conditions_pass' ) ) {
    /**
     * Return true when a fee should apply for the given booking
     * context. Conditions are optional; a row with no conditions
     * always passes.
     *
     * @param array $fee     Normalized fee row.
     * @param array $context Booking context (see header docblock).
     */
    function listeo_fee_conditions_pass( $fee, $context ) {
        $pass = true;

        if ( ! empty( $fee['conditions'] ) && is_array( $fee['conditions'] ) ) {
            $c = $fee['conditions'];

            $guests = isset( $context['guests'] ) ? (int) $context['guests'] : 0;
            $nights = isset( $context['nights'] ) ? (int) $context['nights'] : 0;
            $date_start = isset( $context['date_start'] ) ? (string) $context['date_start'] : '';

            // Guest range.
            if ( null !== $c['min_guests'] && '' !== $c['min_guests'] && $guests < (int) $c['min_guests'] ) {
                $pass = false;
            }
            if ( $pass && null !== $c['max_guests'] && '' !== $c['max_guests'] && $guests > (int) $c['max_guests'] ) {
                $pass = false;
            }

            // Nights range.
            if ( $pass && null !== $c['min_nights'] && '' !== $c['min_nights'] && $nights < (int) $c['min_nights'] ) {
                $pass = false;
            }
            if ( $pass && null !== $c['max_nights'] && '' !== $c['max_nights'] && $nights > (int) $c['max_nights'] ) {
                $pass = false;
            }

            // Date range. Empty string = no bound on that side.
            if ( $pass && ! empty( $c['date_from'] ) && $date_start ) {
                if ( strtotime( $date_start ) < strtotime( $c['date_from'] ) ) {
                    $pass = false;
                }
            }
            if ( $pass && ! empty( $c['date_to'] ) && $date_start ) {
                if ( strtotime( $date_start ) > strtotime( $c['date_to'] ) ) {
                    $pass = false;
                }
            }

            // Weekends-only. Saturday (6) + Sunday (7) per `date('N')`.
            if ( $pass && ! empty( $c['weekends_only'] ) && $date_start ) {
                $weekday = (int) date( 'N', strtotime( $date_start ) );
                if ( $weekday < 6 ) {
                    $pass = false;
                }
            }
        }

        return (bool) apply_filters( 'listeo_fee_conditions_pass', $pass, $fee, $context );
    }
}

/* =====================================================================
 * Calculation
 * ===================================================================== */

if ( ! function_exists( 'listeo_calculate_fee' ) ) {
    /**
     * Resolve one fee row to its monetary contribution.
     *
     * @param array $fee     Normalized or raw fee row.
     * @param array $context Booking context:
     *                       - nights:    int   (days for hourly listings: 1)
     *                       - guests:    int
     *                       - hours:     int
     *                       - tickets:   int
     *                       - subtotal:  float (used by `percent` type)
     *                       - date_start:string YYYY-MM-DD
     *                       - listing_type: string
     * @return float
     */
    function listeo_calculate_fee( $fee, $context = array() ) {
        $fee = listeo_normalize_fee( $fee );
        if ( '' === $fee['price'] || ! is_numeric( $fee['price'] ) ) {
            return 0.0;
        }
        if ( ! listeo_fee_conditions_pass( $fee, $context ) ) {
            return 0.0;
        }

        // Phase 5 — optional fees opt-out. If the caller provides an
        // `accepted_optional_fees` whitelist, exclude any optional row
        // whose id isn't in it. When the key is absent, all optional
        // fees apply (backward-compat default — preserves behaviour for
        // every call site that hasn't been wired up yet).
        if ( ! empty( $fee['optional'] ) && isset( $context['accepted_optional_fees'] ) ) {
            $accepted = is_array( $context['accepted_optional_fees'] )
                ? $context['accepted_optional_fees']
                : array();
            if ( ! in_array( $fee['id'], $accepted, true ) ) {
                return 0.0;
            }
        }

        $price    = (float) $fee['price'];
        $nights   = isset( $context['nights'] )   ? max( 1, (int) $context['nights'] )   : 1;
        $guests   = isset( $context['guests'] )   ? max( 1, (int) $context['guests'] )   : 1;
        $hours    = isset( $context['hours'] )    ? max( 1, (int) $context['hours'] )    : 1;
        $tickets  = isset( $context['tickets'] )  ? max( 1, (int) $context['tickets'] )  : 1;
        $subtotal = isset( $context['subtotal'] ) ? (float) $context['subtotal']         : 0.0;

        switch ( $fee['frequency'] ) {
            case 'per_night':
                $multiplier = $nights;
                break;
            case 'per_guest':
                $multiplier = $guests;
                break;
            case 'per_guest_per_night':
                $multiplier = $guests * $nights;
                break;
            case 'per_hour':
                $multiplier = $hours;
                break;
            case 'per_ticket':
                $multiplier = $tickets;
                break;
            case 'per_stay':
            default:
                $multiplier = 1;
                break;
        }

        if ( 'percent' === $fee['type'] ) {
            // Price interpreted as percentage of subtotal. Multiplier
            // still applies so "10% per night" remains expressible.
            $amount = $subtotal * ( $price / 100 ) * $multiplier;
        } else {
            $amount = $price * $multiplier;
        }

        return (float) apply_filters( 'listeo_calculate_fee', $amount, $fee, $context );
    }
}

if ( ! function_exists( 'listeo_sum_listing_fees' ) ) {
    /**
     * Sum every applicable mandatory fee for a listing against the
     * given context. Replaces the 5+ identical summing loops scattered
     * through `class-listeo-core-bookings-calendar.php`.
     */
    function listeo_sum_listing_fees( $listing_id, $context = array() ) {
        $fees = listeo_get_listing_fees( $listing_id );
        if ( empty( $fees ) ) {
            return 0.0;
        }
        $total = 0.0;
        foreach ( $fees as $fee ) {
            $total += listeo_calculate_fee( $fee, $context );
        }
        return (float) apply_filters( 'listeo_sum_listing_fees', $total, $fees, $context, (int) $listing_id );
    }
}

if ( ! function_exists( 'listeo_get_applicable_listing_fees' ) ) {
    /**
     * Returns the subset of a listing's fees that pass conditions for
     * a given context, each annotated with its calculated `amount`.
     * Use this when rendering an itemized line-by-line breakdown
     * (booking widget summary, confirmation page, email tags).
     *
     * @return array[] Each entry is a normalized fee row plus an
     *                 `amount` key (float).
     */
    function listeo_get_applicable_listing_fees( $listing_id, $context = array() ) {
        $fees = listeo_get_listing_fees( $listing_id );
        if ( empty( $fees ) ) {
            return array();
        }
        $out = array();
        foreach ( $fees as $fee ) {
            $amount = listeo_calculate_fee( $fee, $context );
            if ( 0.0 === $amount ) {
                continue;
            }
            $fee['amount'] = $amount;
            $out[] = $fee;
        }
        return $out;
    }
}

/* =====================================================================
 * Request-context bridge for optional fees
 *
 * Phase 5 wires "customer can opt out" to many calculate_* call sites
 * across `class-listeo-core-bookings-calendar.php`. Threading a new
 * `accepted_optional_fees` argument through every signature (rentals,
 * hours, per-hour, event/tickets) would touch dozens of call sites and
 * every add-on that wraps them. Instead we hook the engine's existing
 * `listeo_calculate_fee` filter to read `$_POST['optional_fees']` —
 * present on the booking submission / recalc paths — and treat absent
 * ids as opt-outs.
 *
 * Callers that prefer explicit control (REST APIs, server-to-server,
 * tests) can still pass `accepted_optional_fees` directly in $context;
 * we skip the bridge in that case to avoid double-filtering.
 *
 * Falls back silently when nothing relevant is in $_POST, which keeps
 * legacy admin-side recalculations behaving exactly as before.
 * ===================================================================== */

if ( ! function_exists( 'listeo_fees_persist_accepted_on_booking' ) ) {
    /**
     * Persist the customer's accepted optional fees on the booking row.
     *
     * Hooks `listeo_before_insert_booking_data` (which fires for every
     * booking insert path — rentals, hours, events, services), reads
     * `$_POST['optional_fees']`, and merges the list into the comment
     * JSON alongside `assigned_resource` and other booking metadata.
     *
     * Empty `$_POST['optional_fees']` with no key means "no opt-out
     * happened" (legacy path) — we don't write the key so older booking
     * rows aren't retroactively annotated. An explicit empty array
     * (`optional_fees=[]`) means "opted out of everything" — we still
     * write the key so reporting can distinguish opt-out from legacy.
     */
    function listeo_fees_persist_accepted_on_booking( $args ) {
        if ( ! isset( $_POST['optional_fees'] ) ) {
            return $args;
        }
        $accepted = is_array( $_POST['optional_fees'] )
            ? array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['optional_fees'] ) ) ) )
            : array();

        if ( ! isset( $args['comment'] ) ) {
            return $args;
        }
        $comment = $args['comment'];
        $decoded = null;
        if ( is_string( $comment ) ) {
            $decoded = json_decode( $comment, true );
        } elseif ( is_array( $comment ) ) {
            $decoded = $comment;
        }
        if ( ! is_array( $decoded ) ) {
            return $args;
        }
        $decoded['accepted_optional_fees'] = $accepted;
        $args['comment'] = wp_json_encode( $decoded );
        return $args;
    }
    add_filter( 'listeo_before_insert_booking_data', 'listeo_fees_persist_accepted_on_booking', 10, 1 );
}

if ( ! function_exists( 'listeo_fees_optional_request_bridge' ) ) {
    function listeo_fees_optional_request_bridge( $amount, $fee, $context ) {
        if ( empty( $fee['optional'] ) ) {
            return $amount;
        }
        // Explicit context wins — engine already handled the whitelist.
        if ( isset( $context['accepted_optional_fees'] ) ) {
            return $amount;
        }
        if ( ! isset( $_POST['optional_fees'] ) ) {
            return $amount;
        }
        $accepted = is_array( $_POST['optional_fees'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['optional_fees'] ) )
            : array();
        if ( ! in_array( $fee['id'], $accepted, true ) ) {
            return 0.0;
        }
        return $amount;
    }
    add_filter( 'listeo_calculate_fee', 'listeo_fees_optional_request_bridge', 10, 3 );
}

if ( ! function_exists( 'listeo_format_fee_line' ) ) {
    /**
     * Produce a presentation-ready label + formatted amount for one
     * applicable fee. Used by templates that need to itemize fees.
     *
     * @return array{label:string, amount_formatted:string, amount:float}
     */
    function listeo_format_fee_line( $fee, $context = array(), $currency_args = array() ) {
        $fee = listeo_normalize_fee( $fee );
        $amount = isset( $fee['amount'] ) ? (float) $fee['amount'] : listeo_calculate_fee( $fee, $context );

        // Resolve currency once. Caller can short-circuit by passing
        // pre-fetched symbol/position/decimals.
        $currency_symbol = isset( $currency_args['symbol'] )
            ? (string) $currency_args['symbol']
            : ( class_exists( 'Listeo_Core_Listing' )
                ? Listeo_Core_Listing::get_currency_symbol( get_option( 'listeo_currency' ) )
                : '' );
        $currency_pos = isset( $currency_args['position'] )
            ? (string) $currency_args['position']
            : (string) get_option( 'listeo_currency_postion', 'before' );
        $decimals = isset( $currency_args['decimals'] )
            ? (int) $currency_args['decimals']
            : (int) get_option( 'listeo_number_decimals', 2 );

        $amount_formatted = number_format_i18n( $amount, $decimals );
        $amount_formatted = ( 'after' === $currency_pos )
            ? $amount_formatted . ' ' . $currency_symbol
            : $currency_symbol . $amount_formatted;

        // Annotate label with frequency hint when the fee scales — gives
        // the customer a quick "ah, that's per-night" cue without
        // forcing a redesign of the summary table.
        $label = $fee['title'];
        $frequency_label = '';
        if ( 'per_stay' !== $fee['frequency'] ) {
            $frequencies = listeo_fee_frequencies();
            if ( isset( $frequencies[ $fee['frequency'] ] ) ) {
                $frequency_label = '(' . strtolower( $frequencies[ $fee['frequency'] ] ) . ')';
                $label          .= ' ' . $frequency_label;
            }
        }

        return array(
            'id'               => $fee['id'],
            'title'            => $fee['title'],
            'label'            => $label,
            'frequency_label'  => $frequency_label,
            'amount'           => $amount,
            'amount_formatted' => $amount_formatted,
        );
    }
}
