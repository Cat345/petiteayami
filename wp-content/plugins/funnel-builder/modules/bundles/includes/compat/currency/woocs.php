<?php
/**
 * Adapter for FOX / WOOCS (Currency Switcher Professional), 1.5.x.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WOOCS currency-switcher adapter.
 *
 * The only file in the plugin allowed to name WOOCS symbols; everything else
 * goes through Compat\Currency\Layer. WOOCS converts once at its own
 * `woocommerce_product_get_price` filter, so nothing here converts a price that
 * is about to be charged.
 */
class Woocs implements Switcher {

	/** Currencies WOOCS always renders without minor units, whatever the config says. */
	const NO_CENTS_FALLBACK = array( 'JPY', 'TWD' );

	/** @var string[] Currency codes already logged as unusable, so one bad rate logs once. */
	private static $logged = array();

	/** @var bool Is a raw variation-price read already in progress? */
	private static $suspended = false;

	/**
	 * The live WOOCS instance, or null.
	 *
	 * `class_exists` is the wrong test: WOOCS sets `$GLOBALS['WOOCS']` to boolean
	 * false on pages it excludes while `WOOCS_VERSION` stays defined.
	 *
	 * @return \WOOCS|null
	 */
	private function woocs() {
		return ( isset( $GLOBALS['WOOCS'] ) && $GLOBALS['WOOCS'] instanceof \WOOCS ) ? $GLOBALS['WOOCS'] : null;
	}

	/**
	 * Present, and in multi-currency mode.
	 *
	 * Read from the resolved property rather than `woocs_is_multiple_allowed`:
	 * WOOCS forces the property true when geolocation manipulation is on
	 * (`classes/woocs.php:110-113`), and every price filter gates on it.
	 *
	 * @return bool
	 */
	public function is_active() {
		$woocs = $this->woocs();
		return null !== $woocs && (bool) $woocs->is_multiple_allowed;
	}

	/**
	 * The currency the shopper is browsing in.
	 *
	 * @return string
	 */
	public function get_current_currency() {
		$woocs = $this->woocs();
		return null === $woocs ? '' : (string) $woocs->current_currency;
	}

	/**
	 * The store's base currency.
	 *
	 * @return string
	 */
	public function get_base_currency() {
		$woocs = $this->woocs();
		return null === $woocs ? (string) get_option( 'woocommerce_currency', '' ) : (string) $woocs->default_currency;
	}

	/**
	 * WOOCS converts on read, so a price we write is converted again.
	 *
	 * Its conversion survives ADP's recalculation window
	 * (`WcNoFilterWorker::calculateTotals()`), so a line is charged converted
	 * whether or not this plugin pre-converts.
	 *
	 * @return bool
	 */
	public function reconverts_written_price() {
		return true;
	}

	/**
	 * Base → display.
	 *
	 * @param float       $amount Amount in base currency.
	 * @param string|null $to     Target currency; null → the current one.
	 * @return float
	 */
	public function convert( $amount, $to = null ) {
		$amount = (float) $amount;
		$code   = $this->code( $to );
		if ( '' === $code || $code === $this->get_base_currency() ) {
			return $amount;
		}
		$rate = $this->rate( $code );
		return null === $rate ? $amount : $amount * $rate;
	}

	/**
	 * Display → base.
	 *
	 * @param float       $amount Amount in `$from`.
	 * @param string|null $from   Source currency; null → the current one.
	 * @return float
	 */
	public function get_base_amount( $amount, $from = null ) {
		$amount = (float) $amount;
		$code   = $this->code( $from );
		if ( '' === $code || $code === $this->get_base_currency() ) {
			return $amount;
		}
		$rate = $this->rate( $code );
		return null === $rate ? $amount : $amount * ( 1 / $rate );
	}

	/**
	 * An order's amount converted back to base at the rate stamped on the order.
	 *
	 * WOOCS writes `_woocs_order_rate` at checkout (`classes/woocs.php:2991`);
	 * missing it, the current rate is the best available answer.
	 *
	 * @param \WC_Order $order  Order the amount was recorded against.
	 * @param float     $amount Amount in the order's own currency.
	 * @return float
	 */
	public function get_order_base_amount( \WC_Order $order, $amount ) {
		$amount   = (float) $amount;
		$currency = strtoupper( (string) $order->get_currency() );
		if ( '' === $currency || $currency === $this->get_base_currency() ) {
			return $amount;
		}
		$rate = (float) $order->get_meta( '_woocs_order_rate' );
		if ( $rate > 0 ) {
			return $amount * ( 1 / $rate );
		}
		return $this->get_base_amount( $amount, $currency );
	}

	/**
	 * Whether this order can be brought back to base from what WOOCS stored, WOOCS running or not.
	 *
	 * @param \WC_Order $order Order in a currency other than the store's.
	 * @return bool
	 */
	public static function converts_stored_order( \WC_Order $order ) {
		if ( (float) $order->get_meta( '_woocs_order_rate' ) > 0 ) {
			return true;
		}
		$currencies = get_option( 'woocs', array() );
		$code       = strtoupper( (string) $order->get_currency() );
		return is_array( $currencies ) && isset( $currencies[ $code ]['rate'] ) && (float) $currencies[ $code ]['rate'] > 0;
	}

	/**
	 * Exchange rate for a currency, or null when there is no usable one.
	 *
	 * Read from the `woocs` option directly, never `get_currencies()`, which
	 * truncates the list to two entries (`classes/woocs.php:1773-1775`). A zero
	 * rate counts as absent: multiplying by it would wipe the price out.
	 *
	 * @param string $code Currency code.
	 * @return float|null
	 */
	private function rate( $code ) {
		$currencies = get_option( 'woocs', array() );
		$rate       = is_array( $currencies ) && isset( $currencies[ $code ]['rate'] ) ? (float) $currencies[ $code ]['rate'] : 0.0;
		if ( $rate > 0 ) {
			return $rate;
		}
		if ( ! isset( self::$logged[ $code ] ) ) {
			self::$logged[ $code ] = true;
			Layer::log( sprintf( 'No usable WOOCS exchange rate for %s; amounts left unconverted.', $code ) );
		}
		return null;
	}

	/**
	 * Normalise a currency argument to a code, defaulting to the current currency.
	 *
	 * @param string|null $currency Currency code or null.
	 * @return string
	 */
	private function code( $currency ) {
		return '' === (string) $currency || null === $currency
			? (string) $this->get_current_currency()
			: strtoupper( (string) $currency );
	}

	/**
	 * Decimal places for a currency.
	 *
	 * Mirrors the precision WOOCS picks in `raw_woocommerce_price()` — its
	 * `no_cents` list wins over the per-currency setting, so JPY and TWD are 0
	 * even when configured as 2.
	 *
	 * @param string|null $currency Currency code; null → the current one.
	 * @return int
	 */
	public function get_decimals( $currency = null ) {
		$woocs = $this->woocs();
		if ( null === $woocs ) {
			return function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		}
		$code     = $this->code( $currency );
		$no_cents = is_array( $woocs->no_cents ) ? $woocs->no_cents : self::NO_CENTS_FALLBACK;
		if ( in_array( $code, $no_cents, true ) ) {
			return 0;
		}
		// Straight from the option: `get_currency_price_num_decimals()` goes through
		// the truncating `get_currencies()` and can answer with the wrong precision.
		$currencies = get_option( 'woocs', array() );
		if ( is_array( $currencies ) && isset( $currencies[ $code ]['decimals'] ) ) {
			return (int) $currencies[ $code ]['decimals'];
		}
		// WooCommerce's own default, not wc_get_price_decimals(): WOOCS overrides
		// that through `pre_option_woocommerce_price_num_decimals`.
		return 2;
	}

	/**
	 * Format an amount already expressed in `$currency`.
	 *
	 * @param float       $amount   Amount in `$currency`.
	 * @param string|null $currency Currency code; null → the current one.
	 * @return string
	 */
	public function format( $amount, $currency = null ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format( (float) $amount, $this->get_decimals( $currency ) );
		}
		$code = $this->code( $currency );
		return wc_price(
			(float) $amount,
			array(
				'currency' => $code,
				'decimals' => $this->get_decimals( $code ),
			)
		);
	}

	/**
	 * Does WOOCS resolve a non-rate-derived price for this product?
	 *
	 * Such a price replaces whatever the product object carries
	 * (`classes/woocs.php:2290-2291`), so a discount written with `set_price()`
	 * in base is silently discarded. `_get_product_geo_price()` is private, so a
	 * geo price is detected by comparison against a plain rate conversion.
	 *
	 * @param \WC_Product $product Product to test.
	 * @param float|null  $raw     Base price to resolve against; null reads the product's own.
	 * @return bool
	 */
	public function has_custom_price( \WC_Product $product, $raw = null ) {
		$woocs = $this->woocs();
		if ( null === $woocs || $this->get_current_currency() === $this->get_base_currency() ) {
			return false;
		}
		// A variable parent is never custom at parent level — WOOCS short-circuits
		// it to -1 and resolves per variation instead (`classes/woocs.php:2039-2040`).
		if ( $product->is_type( 'variable' ) ) {
			return false;
		}
		$type      = $product->is_type( 'variation' ) ? 'variation' : 'single';
		$raw       = null === $raw ? (float) $product->get_price( 'edit' ) : (float) $raw;
		$precision = $this->get_decimals();

		// The resolver objects are only built when their option was on at boot
		// (`classes/woocs.php:485`, `:495`); asking a null one fatals inside WOOCS.
		if ( $woocs->is_fixed_enabled && is_object( $woocs->fixed )
			&& -1 !== (int) $woocs->_get_product_fixed_price( $product, $type, $raw, $precision ) ) {
			return true;
		}
		if ( $woocs->is_fixed_user_role && is_object( $woocs->fixed_user_role )
			&& -1 !== (int) $woocs->_get_product_fixed_user_role_price( $product, $type, $raw, $precision ) ) {
			return true;
		}
		if ( ! $woocs->is_geoip_manipulation || $raw <= 0 ) {
			return false;
		}
		$tolerance = pow( 10, -$precision ) / 2;
		return abs( $this->get_display_price( $product, $raw ) - $this->convert( $raw ) ) > $tolerance;
	}

	/**
	 * WOOCS's own resolved display price for a product.
	 *
	 * Calls the converter directly rather than reading `get_price()`, so the
	 * plugin's priority-10000 ratio filter cannot participate — that filter is
	 * defined in terms of this value, so reading through the hooks is circular.
	 *
	 * @param \WC_Product $product Product to price.
	 * @param float|null  $raw     Base price to convert; null reads the product's own.
	 * @return float
	 */
	public function get_display_price( \WC_Product $product, $raw = null ) {
		$woocs = $this->woocs();
		$raw   = null === $raw ? (float) $product->get_price( 'edit' ) : (float) $raw;
		if ( null === $woocs ) {
			return $raw;
		}
		return $this->to_float( $woocs->raw_woocommerce_price( $raw, $product ) );
	}

	/**
	 * A product's unit price in base currency.
	 *
	 * WOOCS converts through a filter and never writes the converted figure back
	 * onto the product, so the edit-context read is the base price.
	 *
	 * @param \WC_Product $product Product to price.
	 * @return float
	 */
	public function raw_base_price( \WC_Product $product ) {
		return (float) $product->get_price( 'edit' );
	}

	/**
	 * Read variation prices with WOOCS's conversion suspended.
	 *
	 * `woocommerce_variation_prices` takes only `( $prices_array )` in WOOCS
	 * (`classes/woocs.php:1865`), so `get_variation_prices( false )` is still
	 * converted and the callback has to come off. Core memoises the filtered
	 * array under the price hash, hence the discriminator.
	 *
	 * @param callable $read Reader returning the raw prices.
	 * @return mixed Whatever `$read` returns.
	 */
	public function with_raw_variation_prices( callable $read ) {
		$woocs = $this->woocs();
		// Nesting would let the inner `finally` restore conversion, and drop the
		// hash discriminator, while the outer read is still running.
		if ( null === $woocs || self::$suspended ) {
			return $read();
		}
		self::$suspended = true;
		$callback        = array( $woocs, 'woocommerce_variation_prices' );
		$removed  = remove_filter( 'woocommerce_variation_prices', $callback, 9999 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'raw_prices_hash' ), 10000 );
		try {
			return $read();
		} finally {
			self::$suspended = false;
			remove_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'raw_prices_hash' ), 10000 );
			if ( $removed ) {
				add_filter( 'woocommerce_variation_prices', $callback, 9999, 3 );
			}
		}
	}

	/**
	 * Discriminate the variation-price cache key for a raw read.
	 *
	 * A named method, not a closure: a closure on a shared filter can never be
	 * removed again.
	 *
	 * @param array|string $price_hash Cache key components.
	 * @return array|string
	 */
	public static function raw_prices_hash( $price_hash ) {
		if ( is_array( $price_hash ) ) {
			$price_hash['wffn_bundles_raw'] = 1;
			return $price_hash;
		}
		return $price_hash . 'wffn_bundles_raw';
	}

	/**
	 * Cast a WOOCS price to float.
	 *
	 * WOOCS returns `number_format( …, $precision, $this->decimal_sep, '' )`
	 * (`classes/woocs.php:2085`), so under a comma decimal separator a bare
	 * `(float)` cast truncates the cents.
	 *
	 * @param mixed $value Price as WOOCS returned it.
	 * @return float
	 */
	public function to_float( $value ) {
		if ( is_float( $value ) || is_int( $value ) ) {
			return (float) $value;
		}
		$woocs = $this->woocs();
		$text  = (string) $value;
		if ( null !== $woocs ) {
			if ( '' !== (string) $woocs->thousands_sep ) {
				$text = str_replace( (string) $woocs->thousands_sep, '', $text );
			}
			if ( '' !== (string) $woocs->decimal_sep ) {
				$text = str_replace( (string) $woocs->decimal_sep, '.', $text );
			}
		}
		return (float) preg_replace( '/[^0-9.\-]/', '', $text );
	}
}
