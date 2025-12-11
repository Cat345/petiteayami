<?php
/**
 * Defines a simple class that enables the usage of the WC_Item_Totals trait.
 *
 * @package Stripe\StripeTaxForWooCommerce\WordPress
 */

namespace Stripe\StripeTaxForWooCommerce\WordPress;

use WC_Item_Totals;

/**
 * A simple class that enables the usage of the WC_Item_Totals trait.
 */
abstract class ItemTotals {
	use WC_Item_Totals {
		round_line_tax as protected_round_line_tax;
	}

	/**
	 * Return rounded value of $value bases on 'woocommerce_tax_round_at_subtotal' option
	 *
	 * @param float $value    - Value.
	 * @param bool  $in_cents - true if $value is in cents, false otherwise.
	 */
	public static function round_line_tax( $value, $in_cents = true ) {
		return static::protected_round_line_tax( $value, $in_cents );
	}
}
