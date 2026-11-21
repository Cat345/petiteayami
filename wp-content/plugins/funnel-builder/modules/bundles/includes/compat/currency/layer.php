<?php
/**
 * Switcher-agnostic currency facade.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches currency operations to the first active adapter.
 *
 * Nothing outside `includes/compat/currency/` may name a currency-switcher
 * plugin. With no adapter active every method is identity, and every dispatch
 * is wrapped so a throwing switcher cannot take the store down with it.
 */
class Layer {

	/**
	 * Adapter class names, as strings, in resolution order.
	 *
	 * Strings rather than instances so the autoloader is never asked for a class
	 * before its plugin is known to be present. Order is the tie-break when two
	 * switchers are active at once.
	 */
	private static $adapters = array(
		Woocs::class,
		Aelia::class,
		Yaycurrency::class,
	);

	/** @var Switcher|null|false Resolved adapter; false = not yet resolved. */
	private static $active = false;

	/** @var bool Is this request pinned to base currency whatever the shopper picked? */
	private static $base_only = false;

	/** Resolve the adapter registry once per request. Called from Plugin::init(). */
	public static function boot() {
		self::active_adapter();
	}

	/**
	 * The adapter handling this request, or null.
	 *
	 * Memoised: which switcher is installed cannot change mid-request. The
	 * currency can, so nothing else here is cached.
	 *
	 * @return Switcher|null
	 */
	public static function active_adapter() {
		if ( false !== self::$active ) {
			return self::$active;
		}
		self::$active = null;
		foreach ( self::registered() as $class ) {
			if ( ! is_string( $class ) || ! self::present( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			try {
				$adapter = new $class();
				if ( $adapter instanceof Switcher && $adapter->is_active() ) {
					self::$active = $adapter;
					break;
				}
			} catch ( \Exception $e ) {
				self::log( $class . ' failed to boot: ' . $e->getMessage() );
			} catch ( \Error $e ) {
				self::log( $class . ' failed to boot: ' . $e->getMessage() );
			}
		}
		return self::$active;
	}

	/**
	 * Is the switcher an adapter targets loaded? Asked before the adapter's file is.
	 *
	 * Each test is the first thing the adapter itself requires. An adapter with no
	 * test here is always loaded and asked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Adapter class name.
	 * @return bool
	 */
	private static function present( $class ) {
		switch ( $class ) {
			case Woocs::class:
				return isset( $GLOBALS['WOOCS'] );
			case Aelia::class:
				return isset( $GLOBALS['woocommerce-aelia-currencyswitcher'] );
			case Yaycurrency::class:
				return class_exists( '\Yay_Currency\Helpers\YayCurrencyHelper' );
		}
		return true;
	}

	/**
	 * The currency adapters that may boot on this request.
	 *
	 * @return string[] Adapter class names.
	 */
	private static function registered() {
		return (array) apply_filters( 'wffn_bundles_currency_adapters', self::$adapters );
	}

	/**
	 * Price this request in base currency, whatever the shopper selected.
	 *
	 * A switcher resolves its currency from a cookie or user profile, so a
	 * merchant who browsed the storefront in one currency carries that choice
	 * into wp-admin. Request-scoped and one-way; nothing unpins it.
	 */
	public static function pin_to_base() {
		self::$base_only = true;
	}

	/**
	 * Is any currency switcher converting prices on this request?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return ! self::$base_only && null !== self::active_adapter();
	}

	/**
	 * The shopper's currency, falling back to WooCommerce's own.
	 *
	 * @return string
	 */
	public static function get_current_currency() {
		if ( self::$base_only ) {
			return self::get_base_currency();
		}
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		}
		return (string) self::dispatch( $adapter, 'get_current_currency', array(), '' );
	}

	/**
	 * The store's base currency.
	 *
	 * A switcher filters `woocommerce_currency`, so the raw option is the only
	 * honest source and reading it belongs here, behind the adapter's answer.
	 *
	 * @return string
	 */
	public static function get_base_currency() {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return (string) get_option( 'woocommerce_currency', '' );
		}
		return (string) self::dispatch( $adapter, 'get_base_currency', array(), (string) get_option( 'woocommerce_currency', '' ) );
	}

	/**
	 * Does this switcher still convert a price the plugin writes onto a product?
	 *
	 * A discount engine recalculates line totals with the price filters unset
	 * (`Cart::charge_unit()`), so a switcher whose conversion survives that
	 * window converts a pre-converted figure twice. Optional on the interface.
	 *
	 * @return bool
	 */
	public static function reconverts_written_price() {
		$adapter = self::active_adapter();
		if ( null === $adapter || ! method_exists( $adapter, 'reconverts_written_price' ) ) {
			return false;
		}
		return (bool) self::dispatch( $adapter, 'reconverts_written_price', array(), false );
	}

	/**
	 * Base → display. Identity with no adapter.
	 *
	 * @param float       $amount Amount in base currency.
	 * @param string|null $to     Target currency; null → the current one.
	 * @return float
	 */
	public static function convert( $amount, $to = null ) {
		// Only the default target is pinned: an explicit code is a caller asking for
		// a named currency on purpose, which admin screens never do.
		if ( self::$base_only && null === $to ) {
			return (float) $amount;
		}
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return (float) $amount;
		}
		return (float) self::dispatch( $adapter, 'convert', array( (float) $amount, $to ), (float) $amount );
	}

	/**
	 * Display → base. Identity with no adapter.
	 *
	 * @param float       $amount Amount in `$from`.
	 * @param string|null $from   Source currency; null → the current one.
	 * @return float
	 */
	public static function get_base_amount( $amount, $from = null ) {
		if ( self::$base_only && null === $from ) {
			return (float) $amount;
		}
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return (float) $amount;
		}
		return (float) self::dispatch( $adapter, 'get_base_amount', array( (float) $amount, $from ), (float) $amount );
	}

	/**
	 * Decimal places for a currency.
	 *
	 * @param string|null $currency Currency code; null → the current one.
	 * @return int
	 */
	public static function get_decimals( $currency = null ) {
		$fallback = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		if ( self::$base_only && null === $currency ) {
			$currency = self::get_base_currency();
		}
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return $fallback;
		}
		return (int) self::dispatch( $adapter, 'get_decimals', array( $currency ), $fallback );
	}

	/**
	 * Format an amount already expressed in `$currency`.
	 *
	 * @param float       $amount   Amount in `$currency`.
	 * @param string|null $currency Currency code; null → the current one.
	 * @return string
	 */
	public static function format( $amount, $currency = null ) {
		if ( self::$base_only && null === $currency ) {
			$currency = self::get_base_currency();
		}
		// A switcher relabels every wc_price() with its live symbol, whatever currency
		// is asked for, so a base amount is formatted from the store's own settings.
		if ( null !== $currency && self::get_base_currency() === strtoupper( (string) $currency ) && null !== self::active_adapter() ) {
			return self::base_format( (float) $amount );
		}
		$fallback = self::wc_format( $amount, $currency );
		$adapter  = self::active_adapter();
		if ( null === $adapter ) {
			return $fallback;
		}
		return (string) self::dispatch( $adapter, 'format', array( (float) $amount, $currency ), $fallback );
	}

	/**
	 * Would the switcher replace this product's price with a non-rate-derived one?
	 *
	 * @param \WC_Product $product Product to test.
	 * @param float|null  $raw     Base price to resolve against; null reads the product's own.
	 * @return bool
	 */
	public static function has_custom_price( $product, $raw = null ) {
		// The adapter answers for the currency the shopper selected, which is what
		// an admin request must not be told.
		if ( self::$base_only ) {
			return false;
		}
		$adapter = self::active_adapter();
		if ( null === $adapter || ! $product instanceof \WC_Product ) {
			return false;
		}
		return (bool) self::dispatch( $adapter, 'has_custom_price', array( $product, $raw ), false );
	}

	/**
	 * The switcher's own resolved display price for a product.
	 *
	 * @param \WC_Product $product Product to price.
	 * @param float|null  $raw     Base price to convert; null reads the product's own.
	 * @return float
	 */
	public static function get_display_price( $product, $raw = null ) {
		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}
		if ( self::$base_only ) {
			return null === $raw ? self::raw_base_price( $product ) : (float) $raw;
		}
		$fallback = null === $raw ? (float) $product->get_price() : self::convert( (float) $raw );
		$adapter  = self::active_adapter();
		if ( null === $adapter ) {
			return $fallback;
		}
		return (float) self::dispatch( $adapter, 'get_display_price', array( $product, $raw ), $fallback );
	}

	/**
	 * A product's unit price in base currency, before any custom-price handling.
	 *
	 * `get_price( 'edit' )` is only a raw read under a switcher that converts
	 * through a filter; one that writes the converted price back onto the
	 * instance leaves it converted in every context.
	 *
	 * @param \WC_Product $product Product to price.
	 * @return float
	 */
	public static function raw_base_price( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}
		$fallback = (float) $product->get_price( 'edit' );
		$adapter  = self::active_adapter();
		if ( null === $adapter ) {
			return $fallback;
		}
		return (float) self::dispatch( $adapter, 'raw_base_price', array( $product ), $fallback );
	}

	/**
	 * A product's unit price in base currency, honouring a custom one.
	 *
	 * A manual per-currency, user-role or geo price is resolved inside the
	 * switcher's filter, which the raw read bypasses. Where one exists this
	 * returns its base equivalent, so callers keep doing base arithmetic.
	 *
	 * @param \WC_Product $product Product to price.
	 * @return float
	 */
	public static function get_base_price( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}
		$raw = self::raw_base_price( $product );
		// A variable parent's `_price` is the synced cheapest variation and a
		// switcher resolves custom prices per variation, never at parent level.
		if ( $product->is_type( 'variable' ) && self::is_active() && self::get_current_currency() !== self::get_base_currency() ) {
			list( $min, ) = self::get_base_variation_range( $product );
			return $min > 0 ? $min : $raw;
		}
		if ( ! self::has_custom_price( $product ) ) {
			return $raw;
		}
		return self::get_base_amount( self::get_display_price( $product ) );
	}

	/**
	 * A variable product's [min, max] unit price in base currency.
	 *
	 * The per-variation walk only runs when a switcher is active in a non-base
	 * currency, i.e. when a variation's price could be a custom one.
	 *
	 * @param \WC_Product $product Variable product.
	 * @return array{0:float,1:float} Min and max, or [0.0, 0.0] when unreadable.
	 */
	public static function get_base_variation_range( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return array( 0.0, 0.0 );
		}
		$prices = self::with_raw_variation_prices(
			function () use ( $product ) {
				return $product->get_variation_prices( true );
			}
		);
		$raw = is_array( $prices ) && is_array( $prices['price'] ?? null ) ? $prices['price'] : array();
		if ( empty( $raw ) ) {
			return array( 0.0, 0.0 );
		}
		$plain = self::get_current_currency() === self::get_base_currency();
		$vals  = array();
		foreach ( $raw as $variation_id => $price ) {
			$variation = ( $plain || ! self::is_active() ) ? null : wc_get_product( (int) $variation_id );
			$vals[]    = $variation instanceof \WC_Product ? self::get_base_price( $variation ) : (float) $price;
		}
		return array( min( $vals ), max( $vals ) );
	}

	/**
	 * An order's amount converted back to base at the rate the order was placed at.
	 *
	 * @param \WC_Order $order  Order the amount was recorded against.
	 * @param float     $amount Amount in the order's own currency.
	 * @return float
	 */
	public static function get_order_base_amount( $order, $amount ) {
		if ( ! $order instanceof \WC_Order ) {
			return (float) $amount;
		}
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			$adapter = self::order_adapter( $order );
		}
		if ( null === $adapter ) {
			return (float) $amount;
		}
		return (float) self::dispatch( $adapter, 'get_order_base_amount', array( $order, (float) $amount ), (float) $amount );
	}

	/**
	 * An adapter that can bring this order back to base from what its switcher stored.
	 *
	 * Some switchers stay out of a request on purpose (WOOCS skips the REST API), so an
	 * order moved to a new status there would be recorded in its own currency as base.
	 *
	 * @param \WC_Order $order Order in a currency other than the store's.
	 * @return Switcher|null
	 */
	private static function order_adapter( \WC_Order $order ) {
		$currency = strtoupper( (string) $order->get_currency() );
		if ( '' === $currency || strtoupper( (string) get_option( 'woocommerce_currency', '' ) ) === $currency ) {
			return null;
		}
		foreach ( self::registered() as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) || ! method_exists( $class, 'converts_stored_order' ) ) {
				continue;
			}
			try {
				if ( $class::converts_stored_order( $order ) ) {
					$adapter = new $class();
					if ( $adapter instanceof Switcher ) {
						return $adapter;
					}
				}
			} catch ( \Throwable $e ) {
				self::log( $class . ' could not read its stored order rate: ' . $e->getMessage() );
			}
		}
		return null;
	}

	/**
	 * Cast a switcher-produced price to float without losing the cents.
	 *
	 * Switchers return `number_format()` strings, so `(float) "40,50"` is 40.0
	 * under a comma decimal separator.
	 *
	 * @param mixed $value Price as the switcher returned it.
	 * @return float
	 */
	public static function to_float( $value ) {
		if ( is_float( $value ) || is_int( $value ) ) {
			return (float) $value;
		}
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return self::wc_to_float( $value );
		}
		return (float) self::dispatch( $adapter, 'to_float', array( $value ), self::wc_to_float( $value ) );
	}

	/**
	 * Parse a price string using WooCommerce's own separators.
	 *
	 * @param mixed $value Price string.
	 * @return float
	 */
	private static function wc_to_float( $value ) {
		$text = (string) $value;
		if ( function_exists( 'wc_get_price_thousand_separator' ) ) {
			$thousands = wc_get_price_thousand_separator();
			if ( '' !== $thousands ) {
				$text = str_replace( $thousands, '', $text );
			}
			$decimal = wc_get_price_decimal_separator();
			if ( '' !== $decimal ) {
				$text = str_replace( $decimal, '.', $text );
			}
		}
		return (float) preg_replace( '/[^0-9.\-]/', '', $text );
	}

	/**
	 * Read variation prices with the switcher's conversion suspended.
	 *
	 * `get_variation_prices( false )` is not a raw read under every switcher, so
	 * the suspension is the adapter's job.
	 *
	 * @param callable $read Reader returning the raw prices.
	 * @return mixed Whatever `$read` returns, or null if the suspension failed.
	 */
	public static function with_raw_variation_prices( callable $read ) {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return $read();
		}
		try {
			return $adapter->with_raw_variation_prices( $read );
		} catch ( \Exception $e ) {
			self::log( 'with_raw_variation_prices failed: ' . $e->getMessage() );
		} catch ( \Error $e ) {
			self::log( 'with_raw_variation_prices failed: ' . $e->getMessage() );
		}
		// Not retried: the adapter's `finally` has put conversion back, so a second
		// read would return converted prices labelled as raw.
		return null;
	}

	/**
	 * Call one adapter method, returning `$fallback` if it throws.
	 *
	 * @param Switcher $adapter  Active adapter.
	 * @param string   $method   Interface method name.
	 * @param array    $args     Positional arguments.
	 * @param mixed    $fallback Value to return on failure.
	 * @return mixed
	 */
	private static function dispatch( Switcher $adapter, $method, array $args, $fallback ) {
		try {
			return call_user_func_array( array( $adapter, $method ), $args );
		} catch ( \Exception $e ) {
			self::log( $method . '() failed: ' . $e->getMessage() );
		} catch ( \Error $e ) {
			self::log( $method . '() failed: ' . $e->getMessage() );
		}
		return $fallback;
	}

	/**
	 * A base-currency amount in the store's own price format (symbol, position,
	 * separators, decimals), read from WooCommerce's settings rather than through
	 * the price filters a currency switcher rewrites.
	 *
	 * @param float $amount Base-currency amount.
	 * @return string Same markup as wc_price().
	 */
	private static function base_format( $amount ) {
		$code     = (string) self::get_base_currency();
		$symbols  = function_exists( 'get_woocommerce_currency_symbols' ) ? (array) get_woocommerce_currency_symbols() : array();
		$symbol   = (string) ( $symbols[ $code ] ?? $code );
		$decimals = max( 0, (int) get_option( 'woocommerce_price_num_decimals', 2 ) );
		$number   = number_format( abs( $amount ), $decimals, (string) get_option( 'woocommerce_price_decimal_sep', '.' ), (string) get_option( 'woocommerce_price_thousand_sep', ',' ) );
		$formats  = array(
			'left'        => '%1$s%2$s',
			'right'       => '%2$s%1$s',
			'left_space'  => '%1$s&nbsp;%2$s',
			'right_space' => '%2$s&nbsp;%1$s',
		);
		$format   = $formats[ (string) get_option( 'woocommerce_currency_pos', 'left' ) ] ?? $formats['left'];
		$price    = sprintf( $format, '<span class="woocommerce-Price-currencySymbol">' . $symbol . '</span>', $number );

		return '<span class="woocommerce-Price-amount amount"><bdi>' . ( $amount < 0 ? '-' : '' ) . $price . '</bdi></span>';
	}

	/**
	 * WooCommerce's own money formatter, used when no adapter is present.
	 *
	 * @param float       $amount   Amount.
	 * @param string|null $currency Currency code, or null for the store's.
	 * @return string
	 */
	private static function wc_format( $amount, $currency = null ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format( (float) $amount, 2 );
		}
		$args = $currency ? array( 'currency' => $currency ) : array();
		return wc_price( (float) $amount, $args );
	}

	/**
	 * Record a currency-compatibility problem for the merchant.
	 *
	 * Never thrown onward: a broken switcher must degrade to unconverted prices,
	 * not to a fatal.
	 *
	 * @param string $message What went wrong.
	 */
	public static function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( (string) $message, array( 'source' => 'wffn-bundles-currency' ) );
		}
	}
}
