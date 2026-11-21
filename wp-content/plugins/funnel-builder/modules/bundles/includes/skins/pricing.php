<?php
/** Pricing — tiny display-pricing helper for skins. */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Currency\Layer;
use WFFN\Bundles\Includes\Compat\Discount\Layer as Discount_Layer;
use WFFN\Bundles\Includes\Frontend\Cart;

/**
 * Display-pricing helpers shared by every skin view-model.
 */
class Pricing {

	/**
	 * Compound an active discount engine's own rule into a display price.
	 *
	 * Routed through Cart::fold_engine_discount so the advertised and charged
	 * figures cannot drift. Line totals in, line total out.
	 *
	 * @param float $discounted Our discounted line total, base currency.
	 * @param float $original   Undiscounted line total, base currency.
	 * @param int   $qty        Units in the line.
	 * @param mixed $product    Anchor product, or null when the caller has none.
	 * @return float
	 */
	public static function fold_engine( $discounted, $original, $qty, $product = null ) {
		if ( ! $product instanceof \WC_Product || (float) $original <= 0 ) {
			return (float) $discounted;
		}
		$qty  = max( 1, (int) $qty );
		// `pricedUnit` is what the cart line carries; Cart::stored_unit() reads it back.
		$item = array(
			'data'       => $product,
			'quantity'   => $qty,
			'wffn_bundles_bundle' => array(
				'unit'       => (float) $original / $qty,
				'pricedUnit' => (float) $discounted / $qty,
			),
		);
		return Cart::fold_engine_discount( $item, (float) $discounted / $qty ) * $qty;
	}

	/**
	 * Format a base-currency amount as plain text, stripping wc_price markup and
	 * decoding entities so the result is safe as textContent.
	 *
	 * The display-side conversion boundary: every caller hands it base, and the
	 * currency switcher converts here exactly once.
	 *
	 * @param float $amount Amount in base currency.
	 * @return string
	 */
	public static function format( $amount ) {
		return self::format_display( Layer::convert( (float) $amount ) );
	}

	/**
	 * A line of `$qty` units in display currency, as the cart charges it.
	 *
	 * The cart rounds the line in base, converts the unit and lets WooCommerce
	 * multiply. A switcher that rounds every conversion makes that differ from
	 * converting the line; for any other switcher the two are the same figure.
	 *
	 * @param float $amount Line total, base currency.
	 * @param int   $qty    Units in the line.
	 * @return float
	 */
	public static function convert_line( $amount, $qty = 1 ) {
		$qty  = max( 1, (int) $qty );
		$line = round( (float) $amount, Layer::get_decimals( Layer::get_base_currency() ) );
		// A push-style engine converts and charges the whole line itself, at full precision.
		if ( Discount_Layer::is_active() && Discount_Layer::supplies_own_price() ) {
			return Layer::to_float( Layer::convert( $line ) );
		}
		return Layer::to_float( Layer::convert( $line / $qty ) ) * $qty;
	}

	/**
	 * Format a base-currency line of `$qty` units as the cart charges it.
	 *
	 * @param float $amount Line total, base currency.
	 * @param int   $qty    Units in the line.
	 * @return string
	 */
	public static function format_line( $amount, $qty ) {
		return self::format_display( self::convert_line( $amount, $qty ) );
	}

	/**
	 * format_range() for lines of `$qty` units.
	 *
	 * @param float $lo  Cheap end, base currency.
	 * @param float $hi  Dear end, base currency.
	 * @param int   $qty Units in the line.
	 * @return string
	 */
	public static function format_line_range( $lo, $hi, $qty ) {
		return (float) $hi > (float) $lo
			? self::format_line( $lo, $qty ) . ' – ' . self::format_line( $hi, $qty )
			: self::format_line( $lo, $qty );
	}

	/**
	 * Format an amount already in display currency as plain text.
	 *
	 * @param float $display Amount in display currency.
	 * @return string
	 */
	public static function format_display( $display ) {
		if ( function_exists( 'wc_price' ) ) {
			$plain = wp_strip_all_tags( Layer::format( (float) $display ) );
			return html_entity_decode( $plain, ENT_QUOTES, 'UTF-8' );
		}
		return '$' . number_format( (float) $display, 2 );
	}

	/**
	 * "lo – hi" when the ends differ, else the single price. Both ends are base
	 * currency; the spaced en dash matches every other range in the plugin.
	 *
	 * @param float $lo Cheap end.
	 * @param float $hi Dear end.
	 * @return string
	 */
	public static function format_range( $lo, $hi ) {
		return (float) $hi > (float) $lo
			? self::format( $lo ) . ' – ' . self::format( $hi )
			: self::format( $lo );
	}
}
