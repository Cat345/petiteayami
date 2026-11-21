<?php
/**
 * Currency-switcher adapter contract.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The contract every currency-switcher adapter implements.
 *
 * All bundle arithmetic happens in base currency and the switcher converts
 * once, at its own `woocommerce_product_get_price` filter. No method here may
 * mutate the switcher's persisted currency selection.
 */
interface Switcher {

	/**
	 * Is this switcher present and in a mode where it actually converts prices?
	 *
	 * Presence alone is not enough: every switcher has a single-currency mode in
	 * which none of its price filters are registered.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * The currency the shopper is browsing in.
	 *
	 * @return string 3-letter code.
	 */
	public function get_current_currency();

	/**
	 * The store's base ("etalon") currency.
	 *
	 * @return string 3-letter code.
	 */
	public function get_base_currency();

	/**
	 * Base currency → display currency.
	 *
	 * @param float       $amount Amount in base currency.
	 * @param string|null $to     Target currency code; null → the current one.
	 * @return float Converted amount, or `$amount` unchanged when no usable rate exists.
	 */
	public function convert( $amount, $to = null );

	/**
	 * Display currency → base currency.
	 *
	 * @param float       $amount Amount in `$from`.
	 * @param string|null $from   Source currency code; null → the current one.
	 * @return float Amount in base currency, or `$amount` unchanged when no usable rate exists.
	 */
	public function get_base_amount( $amount, $from = null );

	/**
	 * Decimal places for a currency — never assume 2 (JPY and TWD use 0).
	 *
	 * @param string|null $currency Currency code; null → the current one.
	 * @return int
	 */
	public function get_decimals( $currency = null );

	/**
	 * Format an amount that is already in `$currency`.
	 *
	 * @param float       $amount   Amount in `$currency`.
	 * @param string|null $currency Currency code; null → the current one.
	 * @return string
	 */
	public function format( $amount, $currency = null );

	/**
	 * Does the switcher resolve a price for this product that is not rate-derived?
	 *
	 * Such a price replaces whatever the product object carries, so a discount
	 * written with `set_price()` in base is discarded and callers must switch to
	 * a unitless-ratio path.
	 *
	 * @param \WC_Product $product Product to test.
	 * @param float|null  $raw     Base price to resolve against; null reads the
	 *                             product's own, which is wrong for a cart line
	 *                             whose price an earlier pricing pass overwrote.
	 * @return bool
	 */
	public function has_custom_price( \WC_Product $product, $raw = null );

	/**
	 * The switcher's own resolved display unit price for a product.
	 *
	 * Read without side effects and without our own price filters participating.
	 *
	 * @param \WC_Product $product Product to price.
	 * @param float|null  $raw     Base price to convert; null reads the product's own.
	 * @return float
	 */
	public function get_display_price( \WC_Product $product, $raw = null );

	/**
	 * An order's amount converted back to base at the rate that order was placed at.
	 *
	 * Not today's rate: an order re-synced months later must reduce to the same
	 * base figure it did on the day. Switchers stamp the rate on the order.
	 *
	 * @param \WC_Order $order  Order the amount was recorded against.
	 * @param float     $amount Amount in the order's own currency.
	 * @return float
	 */
	public function get_order_base_amount( \WC_Order $order, $amount );

	/**
	 * Cast a price the switcher produced to float.
	 *
	 * Converted values come back as `number_format()` strings, so under a comma
	 * decimal separator a bare `(float)` cast truncates the cents.
	 *
	 * @param mixed $value Price as the switcher returned it.
	 * @return float
	 */
	public function to_float( $value );

	/**
	 * A product's unit price in shop base currency, from a source the switcher
	 * cannot have overwritten.
	 *
	 * `get_price( 'edit' )` is a raw base read only for switchers that convert
	 * through a filter; implementations must resolve from storage, never from a
	 * property. Never null — 0.0 when nothing resolves.
	 *
	 * @param \WC_Product $product Product to price.
	 * @return float
	 */
	public function raw_base_price( \WC_Product $product );

	/**
	 * Run `$read` with the switcher's variation-price conversion suspended.
	 *
	 * `WC_Product_Variable::get_variation_prices()` has no `'edit'` equivalent.
	 * The implementation must also discriminate the variation-prices hash, or
	 * the raw array is memoised under the display cache key for the request.
	 *
	 * @param callable $read Reader returning the raw prices.
	 * @return mixed Whatever `$read` returns.
	 */
	public function with_raw_variation_prices( callable $read );
}
