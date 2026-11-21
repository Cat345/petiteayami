<?php
/**
 * Adapter for Aelia Currency Switcher for WooCommerce, 5.x.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aelia currency-switcher adapter.
 *
 * The only file in the plugin allowed to name Aelia symbols; everything else
 * goes through Compat\Currency\Layer. Aelia converts by mutating the product
 * instance (WC27:943-945) on any view-context price read, so no price read off
 * a product object is trustworthy and `raw_base_price()` resolves from meta.
 *
 * Citation convention: `WC36:n` and `WC27:n` are line n of
 * `src/lib/classes/multiprice/WC36/wc-aelia-currencyprices-manager.php` and its
 * WC27 counterpart; every other path is written in full from the plugin root.
 */
class Aelia implements Switcher {

	/** Aelia's own settings option (`src/lib/classes/settings/wc-aelia-currencyswitcher-settings.php:15`). */
	const OPTION = 'wc_aelia_currency_switcher';

	/** The plugin slug Aelia keys its global by (`src/lib/classes/definitions/definitions.php:14`). */
	const GLOBAL_KEY = 'woocommerce-aelia-currencyswitcher';

	/**
	 * Precision handed to Aelia's converter.
	 *
	 * Aelia rounds inside `convert()` (`src/plugin-main.php:361`) unless given an
	 * explicit precision. Six is above every real currency's precision, so
	 * callers keep rounding where they always did.
	 */
	const CONVERT_DECIMALS = 6;

	/**
	 * Currencies with no minor unit.
	 *
	 * Inlined rather than calling Aelia's `default_currency_decimals()`: it has
	 * two `function_exists`-guarded definitions with different signatures and
	 * defaults, and whichever plugin loads first wins.
	 */
	const NO_DECIMALS = array( 'JPY', 'KRW', 'CLP', 'ISK', 'XAF', 'XOF', 'XPF', 'VUV', 'PYG', 'RWF', 'KMF', 'GNF', 'DJF', 'BIF' );

	/** Aelia's manual per-currency price meta keys (`WC27:60-65`). */
	const META_REGULAR           = '_regular_currency_prices';
	const META_SALE              = '_sale_currency_prices';
	const META_VARIATION_REGULAR = 'variable_regular_currency_prices';
	const META_VARIATION_SALE    = 'variable_sale_currency_prices';
	const META_PRODUCT_BASE      = '_product_base_currency';

	/** The rate Aelia stamps on an order (`src/lib/classes/definitions/definitions.php:111`). */
	const META_ORDER_RATE = '_base_currency_exchange_rate';

	/** @var string[] Currency codes already logged as unusable, so one bad rate logs once. */
	private static $logged = array();

	/** @var bool Is a raw variation-price read already in progress? */
	private static $suspended = false;

	/**
	 * The live Currency Switcher instance, or null.
	 *
	 * `isset` comes first because Aelia's own `instance()` is a bare `$GLOBALS`
	 * lookup that warns when called before Aelia booted.
	 *
	 * @return \WC_Aelia_CurrencySwitcher|null
	 */
	private function cs() {
		return ( isset( $GLOBALS[ self::GLOBAL_KEY ] ) && $GLOBALS[ self::GLOBAL_KEY ] instanceof \WC_Aelia_CurrencySwitcher )
			? $GLOBALS[ self::GLOBAL_KEY ]
			: null;
	}

	/**
	 * Present, and configured with more than one currency.
	 *
	 * Not `plugin_configured()`: Aelia's early return is `is_admin() &&
	 * !$this->plugin_configured()` (`src/plugin-main.php:806-810`), so on the
	 * storefront it registers every price filter regardless.
	 *
	 * @return bool
	 */
	public function is_active() {
		$cs = $this->cs();
		if ( null === $cs ) {
			return false;
		}
		$enabled = $cs->enabled_currencies();
		return is_array( $enabled ) && count( $enabled ) > 1;
	}

	/**
	 * The currency the shopper is browsing in.
	 *
	 * @return string
	 */
	public function get_current_currency() {
		$cs = $this->cs();
		return null === $cs ? '' : (string) $cs->get_selected_currency();
	}

	/**
	 * The store's base currency.
	 *
	 * @return string
	 */
	public function get_base_currency() {
		$cs = $this->cs();
		return null === $cs ? (string) get_option( 'woocommerce_currency', '' ) : (string) $cs->base_currency();
	}

	/**
	 * Base → display.
	 *
	 * Through the loose `wc_aelia_cs_convert` filter rather than the class, so
	 * this degrades to identity if Aelia vanishes mid-request.
	 *
	 * @param float       $amount Amount in base currency.
	 * @param string|null $to     Target currency; null → the current one.
	 * @return float
	 */
	public function convert( $amount, $to = null ) {
		$amount = (float) $amount;
		$code   = $this->code( $to );
		$base   = $this->get_base_currency();
		return $this->vendor_convert( $amount, $base, $code, self::CONVERT_DECIMALS );
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
		$base   = $this->get_base_currency();
		return $this->vendor_convert( $amount, $code, $base, self::CONVERT_DECIMALS );
	}

	/**
	 * One conversion through Aelia's loose filter, with our own rate guard.
	 *
	 * @param float    $amount   Amount in `$from`.
	 * @param string   $from     Source currency.
	 * @param string   $to       Target currency.
	 * @param int|null $decimals Precision to demand, or null for Aelia's own.
	 * @return float `$amount` unchanged when there is no usable rate.
	 */
	private function vendor_convert( $amount, $from, $to, $decimals ) {
		$amount = (float) $amount;
		if ( '' === $from || '' === $to || $from === $to ) {
			return $amount;
		}
		// Both legs must be usable: the rate Aelia divides by is the source's
		// (`src/plugin-main.php:336`), so a missing one there fails just as silently.
		if ( null === $this->rate( $from ) || null === $this->rate( $to ) ) {
			return $amount;
		}
		return $this->to_float( apply_filters( 'wc_aelia_cs_convert', $amount, $from, $to, $decimals ) );
	}

	/**
	 * Exchange rate for a currency, or null when there is no usable one.
	 *
	 * Read from Aelia's option rather than trusting its converter: a missing rate
	 * makes `convert()` fall back to `$exchange_rate = 1`
	 * (`src/plugin-main.php:336-356`), indistinguishable from a correct 1:1.
	 *
	 * @param string $code Currency code.
	 * @return float|null
	 */
	private function rate( $code ) {
		// Aelia forces the base currency's rate to 1 whatever the option says
		// (`settings/wc-aelia-currencyswitcher-settings.php:379-382`).
		if ( $code === $this->get_base_currency() ) {
			return 1.0;
		}
		$row  = $this->settings_row( $code );
		$rate = isset( $row['rate'] ) && is_numeric( $row['rate'] ) ? (float) $row['rate'] : 0.0;
		if ( $rate > 0 ) {
			return $rate;
		}
		if ( ! isset( self::$logged[ $code ] ) ) {
			self::$logged[ $code ] = true;
			Layer::log( sprintf( 'No usable Aelia exchange rate for %s; amounts left unconverted.', $code ) );
		}
		return null;
	}

	/**
	 * One currency's row from Aelia's `exchange_rates` setting.
	 *
	 * @param string $code Currency code.
	 * @return array Row, or empty when the currency is not configured.
	 */
	private function settings_row( $code ) {
		$settings = get_option( self::OPTION, array() );
		$rates    = is_array( $settings ) && isset( $settings['exchange_rates'] ) && is_array( $settings['exchange_rates'] )
			? $settings['exchange_rates']
			: array();
		return isset( $rates[ $code ] ) && is_array( $rates[ $code ] ) ? $rates[ $code ] : array();
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
	 * Never `wc_get_price_decimals()`: Aelia short-circuits
	 * `pre_option_woocommerce_price_num_decimals` to the current currency's
	 * precision, which base-currency arithmetic must not be rounded to.
	 *
	 * @param string|null $currency Currency code; null → the current one.
	 * @return int
	 */
	public function get_decimals( $currency = null ) {
		$code = $this->code( $currency );
		$row  = $this->settings_row( $code );
		if ( isset( $row['decimals'] ) && is_numeric( $row['decimals'] ) ) {
			return (int) $row['decimals'];
		}
		if ( in_array( $code, self::NO_DECIMALS, true ) ) {
			return 0;
		}
		return 2;
	}

	/**
	 * Format an amount already expressed in `$currency`.
	 *
	 * Aelia filters `woocommerce_currency_symbol` with 2 args, so an explicit
	 * currency gets the right symbol; separators and symbol position still come
	 * from the current currency.
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
	 * Does Aelia resolve a non-rate-derived price for this product?
	 *
	 * A product whose own base currency is not the shop's is priced from that
	 * currency (`WC36:180-183`) even while the shopper browses in base, so this
	 * does not short-circuit to false there the way the WOOCS adapter does.
	 *
	 * @param \WC_Product $product Product to test.
	 * @param float|null  $raw     Base price to resolve against; null reads the product's own.
	 * @return bool
	 */
	public function has_custom_price( \WC_Product $product, $raw = null ) {
		if ( null === $this->cs() ) {
			return false;
		}
		// Aelia resolves per variation and back-fills a parent's base from 'edit'
		// context (`WC27:466-475`), so a parent is never custom at parent level.
		if ( $product->is_type( 'variable' ) ) {
			return false;
		}
		return $this->resolves_own_price( $product );
	}

	/**
	 * Would Aelia price this product from something other than the plain rate?
	 *
	 * Shared with `get_display_price()` so "custom" and the figure handed back
	 * always answer the same question.
	 *
	 * @param \WC_Product $product Product to test.
	 * @return bool
	 */
	private function resolves_own_price( \WC_Product $product ) {
		if ( null !== $this->manual_entry( $product, $this->get_current_currency() ) ) {
			return true;
		}
		return $this->product_base_currency( $product ) !== $this->get_base_currency();
	}

	/**
	 * Aelia's own resolved display unit price for a product.
	 *
	 * Asked by product ID, never by object: `get_product_currency_prices()`
	 * back-fills its shop-base entry from the instance (`WC27:470`), which a
	 * conversion has already overwritten. Never reads `$product->get_price()` —
	 * the plugin's priority-10000 filter is defined in terms of this value.
	 *
	 * @param \WC_Product $product Product to price.
	 * @param float|null  $raw     Base price to convert; null reads the product's own.
	 * @return float
	 */
	public function get_display_price( \WC_Product $product, $raw = null ) {
		$current = $this->get_current_currency();
		if ( $this->resolves_own_price( $product ) ) {
			$resolved = $this->vendor_price( $product->get_id(), $current );
			if ( $resolved > 0 ) {
				return $resolved;
			}
		}
		$base = null === $raw ? $this->raw_base_price( $product ) : (float) $raw;
		return $this->convert( $base, $current );
	}

	/**
	 * Aelia's published resolution of a product's price in one currency.
	 *
	 * Third parties can rewrite the result through
	 * `wc_aelia_cs_converted_product_base_price` (`WC36:194`), so the return is
	 * advisory and the caller keeps its own sanity check.
	 *
	 * @param int    $product_id Product ID — never an object, see get_display_price().
	 * @param string $currency   Target currency.
	 * @return float 0.0 when nothing resolves.
	 */
	private function vendor_price( $product_id, $currency ) {
		$price = apply_filters( 'wc_aelia_cs_get_product_base_price_in_currency', '', (int) $product_id, 'price', $currency );
		return is_numeric( $price ) ? $this->to_float( $price ) : 0.0;
	}

	/**
	 * A product's price in shop base currency, resolved from meta alone.
	 *
	 * Not the passed object, whose price properties Aelia has likely overwritten,
	 * and not the vendor filter, whose shop-base entry comes off an instance
	 * (`WC27:470`). The ordering mirrors Aelia's own (`WC36:174, 180, 186`).
	 *
	 * @param \WC_Product $product Product to price.
	 * @return float
	 */
	public function raw_base_price( \WC_Product $product ) {
		$id = (int) $product->get_id();
		// A variable parent has no price fields and Aelia holds no manual price for
		// one, so its synced `_price` is the only usable figure.
		if ( $product->is_type( 'variable' ) ) {
			return (float) get_post_meta( $id, '_price', true );
		}
		$variation = $product->is_type( 'variation' );
		$regular   = $this->base_figure( $product, $variation ? self::META_VARIATION_REGULAR : self::META_REGULAR, '_regular_price' );
		$sale      = $this->base_figure( $product, $variation ? self::META_VARIATION_SALE : self::META_SALE, '_sale_price' );
		// Aelia's unresolved value is `''` and `'' < $sale` never picks the sale, so
		// an unresolved regular price yields no price at all (`WC36:239`).
		if ( null !== $sale && null !== $regular && $sale < $regular && $this->sale_period_active( $product ) ) {
			return $sale;
		}
		return null === $regular ? 0.0 : $regular;
	}

	/**
	 * One pass of the shop-base cascade, for either the regular or the sale array.
	 *
	 * @param \WC_Product $product    Product to price.
	 * @param string      $manual_key Manual per-currency prices meta key.
	 * @param string      $field      WooCommerce price field backing it.
	 * @return float|null Null when nothing resolves, mirroring Aelia's own `''`.
	 */
	private function base_figure( \WC_Product $product, $manual_key, $field ) {
		$id     = (int) $product->get_id();
		$manual = $this->manual_prices( $id, $manual_key );
		$base   = $this->get_base_currency();

		// `isset`, not "not empty": Aelia skips its back-fill whenever the key is
		// present at all (`WC27:462`), including as "" or 0, as importers write it.
		$figure = isset( $manual[ $base ] ) ? $manual[ $base ] : get_post_meta( $id, $field, true );
		if ( is_numeric( $figure ) && $figure > 0 ) {
			return (float) $figure;
		}

		$product_base = $this->product_base_currency( $product );
		if ( $product_base !== $base && isset( $manual[ $product_base ] ) && is_numeric( $manual[ $product_base ] ) && $manual[ $product_base ] > 0 ) {
			// Aelia's branch 3 converts with no explicit precision, rounding to the
			// target currency's decimals; six would resolve a figure it never charges.
			return $this->vendor_convert( (float) $manual[ $product_base ], $product_base, $base, null );
		}
		return null;
	}

	/**
	 * A product's own base currency, or the shop's when the meta is unusable.
	 *
	 * Aelia discards the meta unless it names an enabled currency with a rate
	 * above zero (`WC27:553-554`), and skipping that check would return a foreign
	 * figure as if it were base.
	 *
	 * @param \WC_Product $product Product to read.
	 * @return string Currency code.
	 */
	private function product_base_currency( \WC_Product $product ) {
		$base  = $this->get_base_currency();
		$value = (string) get_post_meta( (int) $product->get_id(), self::META_PRODUCT_BASE, true );
		if ( '' === $value || $value === $base ) {
			return $base;
		}
		$cs      = $this->cs();
		$enabled = null === $cs ? array() : (array) $cs->enabled_currencies();
		if ( ! in_array( $value, $enabled, true ) || null === $this->rate( $value ) ) {
			return $base;
		}
		return $value;
	}

	/**
	 * An explicit manual price for one currency, or null.
	 *
	 * Read from the decoded meta rather than through Aelia's accessor, which
	 * back-fills the shop-base slot from the product instance.
	 *
	 * @param \WC_Product $product  Product to read.
	 * @param string      $currency Currency code.
	 * @return float|null
	 */
	private function manual_entry( \WC_Product $product, $currency ) {
		if ( '' === (string) $currency ) {
			return null;
		}
		$id        = (int) $product->get_id();
		$variation = $product->is_type( 'variation' );
		foreach ( array(
			$variation ? self::META_VARIATION_REGULAR : self::META_REGULAR,
			$variation ? self::META_VARIATION_SALE : self::META_SALE,
		) as $key ) {
			$prices = $this->manual_prices( $id, $key );
			if ( isset( $prices[ $currency ] ) && is_numeric( $prices[ $currency ] ) && $prices[ $currency ] > 0 ) {
				return (float) $prices[ $currency ];
			}
		}
		return null;
	}

	/**
	 * Decode one manual per-currency prices meta value.
	 *
	 * JSON on write (`WC27:250-251`), but an import plugin may have stored a
	 * plain array — which Aelia itself tolerates (`WC27:438-440`).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Meta key.
	 * @return array Currency code => price.
	 */
	private function manual_prices( $product_id, $key ) {
		$raw = get_post_meta( (int) $product_id, $key, true );
		if ( ! is_array( $raw ) ) {
			$raw = json_decode( (string) $raw, true );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Is the product inside its sale window?
	 *
	 * Mirrors `WC36:258-267`, not `WC27`'s `product_is_on_sale()`, which is a
	 * different test. Sale dates are the one part of a product Aelia's conversion
	 * does not overwrite.
	 *
	 * @param \WC_Product $product Product to test.
	 * @return bool
	 */
	private function sale_period_active( \WC_Product $product ) {
		$from = $product->get_date_on_sale_from();
		if ( $from && $from->getTimestamp() > time() ) {
			return false;
		}
		$to = $product->get_date_on_sale_to();
		if ( $to && $to->getTimestamp() < time() ) {
			return false;
		}
		return true;
	}

	/**
	 * An order's amount converted back to base at the rate stamped on the order.
	 *
	 * Aelia stores `convert( 1, order currency, base )`, i.e. base per unit of
	 * order currency, so this multiplies — the inverse of the WOOCS stamp.
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
		$rate = (float) $order->get_meta( self::META_ORDER_RATE );
		if ( $rate > 0 ) {
			return $amount * $rate;
		}
		return $this->get_base_amount( $amount, $currency );
	}

	/**
	 * Cast a price Aelia produced to float.
	 *
	 * Mostly a pass-through, but `convert()` returns its input untouched for a
	 * non-numeric amount, a zero, or matching currencies, and a manual price may
	 * arrive as a numeric string.
	 *
	 * @param mixed $value Price as Aelia returned it.
	 * @return float
	 */
	public function to_float( $value ) {
		if ( is_float( $value ) || is_int( $value ) ) {
			return (float) $value;
		}
		$row  = $this->settings_row( $this->get_current_currency() );
		$text = (string) $value;
		if ( isset( $row['thousand_separator'] ) && '' !== (string) $row['thousand_separator'] ) {
			$text = str_replace( (string) $row['thousand_separator'], '', $text );
		}
		if ( isset( $row['decimal_separator'] ) && '' !== (string) $row['decimal_separator'] ) {
			$text = str_replace( (string) $row['decimal_separator'], '.', $text );
		}
		return (float) preg_replace( '/[^0-9.\-]/', '', $text );
	}

	/**
	 * Read variation prices with Aelia's conversion suspended.
	 *
	 * Mirrors Aelia's own `remove_product_price_hooks()` (`WC36:32-49`), which is
	 * protected. Three of the ten hooks register against a non-variation callback
	 * (`WC27:662-664`) and `remove_filter()` matches on callback identity, so
	 * mirroring hook names to method names would remove nothing.
	 *
	 * @param callable $read Reader returning the raw prices.
	 * @return mixed Whatever `$read` returns.
	 */
	public function with_raw_variation_prices( callable $read ) {
		$cs      = $this->cs();
		$manager = null === $cs ? null : $cs->currencyprices_manager();
		// Nesting would let the inner `finally` restore conversion, and drop the
		// hash discriminator, while the outer read is still running.
		if ( ! is_object( $manager ) || self::$suspended ) {
			return $read();
		}
		self::$suspended = true;
		$removed         = array();
		foreach ( self::price_hooks() as $hook => $callback ) {
			list( $method, $args ) = $callback;
			if ( remove_filter( $hook, array( $manager, $method ), 5 ) ) {
				$removed[ $hook ] = array( $method, $args );
			}
		}
		// Registered separately at priority 10, so the suspension above misses it and
		// the raw array would be memoised under the display currency's cache key.
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'raw_prices_hash' ), 10000 );
		try {
			return $read();
		} finally {
			self::$suspended = false;
			remove_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'raw_prices_hash' ), 10000 );
			foreach ( $removed as $hook => $callback ) {
				add_filter( $hook, array( $manager, $callback[0] ), 5, $callback[1] );
			}
		}
	}

	/**
	 * Aelia's product-price filters: hook => [ callback method, accepted args ].
	 *
	 * The last three variation entries are the trap — their callback is the
	 * non-variation method of the same shape.
	 *
	 * @return array
	 */
	private static function price_hooks() {
		return array(
			'woocommerce_variation_prices_price'                       => array( 'woocommerce_variation_prices_price', 3 ),
			'woocommerce_variation_prices_regular_price'               => array( 'woocommerce_variation_prices_regular_price', 3 ),
			'woocommerce_variation_prices_sale_price'                  => array( 'woocommerce_variation_prices_sale_price', 3 ),
			'woocommerce_product_get_price'                            => array( 'woocommerce_product_get_price', 2 ),
			'woocommerce_product_get_regular_price'                    => array( 'woocommerce_product_get_regular_price', 2 ),
			'woocommerce_product_get_sale_price'                       => array( 'woocommerce_product_get_sale_price', 2 ),
			'woocommerce_product_variation_get_price'                  => array( 'woocommerce_product_get_price', 2 ),
			'woocommerce_product_variation_get_regular_price'          => array( 'woocommerce_product_get_regular_price', 2 ),
			'woocommerce_product_variation_get_sale_price'             => array( 'woocommerce_product_get_sale_price', 2 ),
			'woocommerce_product_get_variation_prices_including_taxes' => array( 'woocommerce_product_get_variation_prices_including_taxes', 2 ),
		);
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
}
