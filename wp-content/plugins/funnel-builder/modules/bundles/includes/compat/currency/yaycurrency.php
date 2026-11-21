<?php
/**
 * Adapter for YayCurrency (WooCommerce Multi Currency Switcher), 3.3.5.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Compat\Currency;

use WFFN\Bundles\Includes\Helpers\Request_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * YayCurrency currency-switcher adapter.
 *
 * The only file in the plugin allowed to name YayCurrency symbols; everything
 * else goes through Compat\Currency\Layer. Yay converts through filters and
 * writes nothing back onto the product instance, so `raw_base_price()` is the
 * plain edit-context read. Its conversion rounds in every configuration, so
 * `convert()` mirrors that; and it stops converting entirely on checkout,
 * order-pay and order-received for a default store, which `suppressed()` tracks.
 *
 * Citation convention: `Yay/<path>:n` is line n of the YayCurrency plugin's own folder.
 */
class Yaycurrency implements Switcher {

	/** YayCurrency Pro's per-currency price meta (`class-yaycurrency.php:58` in Funnel Builder Pro). */
	const META_FIXED = 'yay_currency_custom_fixed_prices';

	/** The option gating Pro's fixed prices (`class-yaycurrency.php:24`). */
	const OPTION_FIXED = 'yay_currency_set_fixed_price';

	/** Rate stamped on an order (`Yay/includes/Engine/FEPages/WooCommerceCurrency.php:299-316`). */
	const META_ORDER_RATE = 'yay_currency_order_rate';

	/** Cookie holding the selected currency's post ID (`Yay/includes/Helpers/YayCurrencyHelper.php:9`). */
	const COOKIE = 'yay_currency_widget';

	/** Our AJAX actions, added to Yay's allow-list (`includes/ajax/controller.php:26-31`). */
	const AJAX_ACTIONS = array( 'wffn_bundles_quote', 'wffn_bundles_add_bundle', 'wffn_bundles_add_single', 'wffn_bundles_gift_modal' );

	/** Ceiling for Yay's price-filter priority, one below the plugin's own 10000 override. */
	const PRIORITY_CEILING = 9999;

	/** The variation-price hooks suspended for a raw read (`Yay/includes/Helpers/YayCurrencyHelper.php:169-171`). */
	const VARIATION_HOOKS = array(
		'woocommerce_variation_prices_price',
		'woocommerce_variation_prices_regular_price',
		'woocommerce_variation_prices_sale_price',
	);

	/** @var string[] Currency codes already logged as unusable, so one bad rate logs once. */
	private static $logged = array();

	/** @var int[] Order ids already logged as missing a rate stamp. */
	private static $logged_orders = array();

	/** @var bool Is a raw variation-price read already in progress? */
	private static $suspended = false;

	/** @var bool Have the two Yay guard filters been registered this request? */
	private static $guarded = false;

	/**
	 * Registers the two guards on Yay's own filters.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->register_guards();
	}

	/**
	 * Hooks the price-priority cap and the AJAX allow-list entry.
	 *
	 * Gated on `class_exists` alone and never on `is_active()`: Yay applies the
	 * allow-list filter from inside `is_reload_permitted()`, which `is_active()`
	 * consults, so gating would make our own AJAX endpoints unreachable.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	private function register_guards() {
		if ( self::$guarded || ! class_exists( '\Yay_Currency\Helpers\YayCurrencyHelper' ) ) {
			return;
		}
		self::$guarded = true;
		add_filter( 'yay_currency_detect_action_args', array( $this, 'allow_ajax_actions' ) );
		// Priority 9999 so a later callback restoring Yay's B2B value still loses to ours.
		add_filter( 'yay_currency_product_prices_filters_priority', array( $this, 'cap_price_priority' ), 9999 );
	}

	/**
	 * Adds the plugin's AJAX actions to Yay's allow-list.
	 *
	 * @since 2.4.0
	 * @param mixed $actions Allow-listed `admin-ajax` actions.
	 * @return array
	 */
	public function allow_ajax_actions( $actions ) {
		return array_values( array_unique( array_merge( (array) $actions, self::AJAX_ACTIONS ) ) );
	}

	/**
	 * Caps Yay's price-filter priority below the plugin's own override.
	 *
	 * Yay raises it to 100000 under B2B Wholesale Suite, Price by Country or
	 * B2BKing (`Yay/includes/Helpers/SupportHelper.php:474-481`), which would
	 * re-convert the line the plugin supplies at 10000.
	 *
	 * @since 2.4.0
	 * @param mixed $priority Priority Yay resolved.
	 * @return int
	 */
	public function cap_price_priority( $priority ) {
		return min( (int) $priority, self::PRIORITY_CEILING );
	}

	/**
	 * Is YayCurrency present, converting, and configured with more than one currency?
	 *
	 * Only predicates answerable at `plugins_loaded` are used. The layer memoises
	 * the adapter, so `is_checkout()` and friends would answer for the wrong
	 * request; those live in `suppressed()` instead.
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	public function is_active() {
		if ( ! class_exists( '\Yay_Currency\Helpers\YayCurrencyHelper' ) || ! class_exists( '\Yay_Currency\Helpers\Helper' ) ) {
			return false;
		}
		if ( ! $this->request_is_converted() ) {
			return false;
		}
		return $this->currency_count() > 1;
	}

	/**
	 * Does Yay register its price filters on this request?
	 *
	 * Mirrors the plugins_loaded-safe half of `is_reload_permitted()`
	 * (`Yay/includes/Helpers/YayCurrencyHelper.php:387-413`) and the WooCommerce
	 * CRUD REST exclusion in `should_run_for_request()` (`:367-385`).
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	private function request_is_converted() {
		if ( $this->is_wc_crud_rest() ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			return $this->ajax_action_allowed();
		}
		return ! is_admin();
	}

	/**
	 * Is this a WooCommerce CRUD REST request, where Yay neuters its own converter?
	 *
	 * Store API is deliberately excluded from the exclusion, exactly as Yay does.
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	private function is_wc_crud_rest() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return false !== strpos( $uri, '/wp-json/wc/' ) && false === strpos( $uri, '/wp-json/wc/store/' );
	}

	/**
	 * Is the current AJAX action one Yay stays hooked on?
	 *
	 * Asked of Yay's own allow-list rather than a copy, so the entries added by
	 * `allow_ajax_actions()` are the same ones tested here.
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	private function ajax_action_allowed() {
		$helper = '\Yay_Currency\Helpers\YayCurrencyHelper';
		// Read-only routing check against Yay's own action allow-list.
		$action = sanitize_key( Request_Context::text( 'action' ) );
		$wc     = sanitize_key( Request_Context::text( 'wc-ajax' ) );
		if ( '' !== $action && method_exists( $helper, 'list_ajax_actions_allow' ) ) {
			return in_array( $action, (array) $helper::list_ajax_actions_allow(), true );
		}
		if ( '' !== $wc && method_exists( $helper, 'list_wc_ajax_actions_allow' ) ) {
			return in_array( $wc, (array) $helper::list_wc_ajax_actions_allow(), true );
		}
		return false;
	}

	/**
	 * Published currency posts, counted directly.
	 *
	 * `wp_count_posts()` answers 0 here: the post type is registered on `init`
	 * and this runs at `plugins_loaded`. Yay's own list getter deletes
	 * duplicate-titled posts as a side effect (`Yay/includes/Helpers/Helper.php:86-98`).
	 *
	 * @since 2.4.0
	 * @return int
	 */
	private function currency_count() {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return 0;
		}
		$post_type = \Yay_Currency\Helpers\Helper::get_post_type();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- wp_count_posts() returns 0 this early (plugins_loaded), so the count is read from the table.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				(string) $post_type
			)
		);
	}

	/**
	 * Has Yay stopped converting on this request?
	 *
	 * True on checkout, order-pay and order-received for a store that has not
	 * enabled `yay_currency_checkout_different_currency`. Delegated rather than
	 * re-derived: Yay's predicate carries three filters and an off-status branch.
	 *
	 * @since 2.4.0
	 * @return bool
	 */
	private function suppressed() {
		$helper = '\Yay_Currency\Helpers\YayCurrencyHelper';
		if ( ! method_exists( $helper, 'disable_fallback_option_in_checkout_page' ) ) {
			return false;
		}
		return (bool) $helper::disable_fallback_option_in_checkout_page( $this->currency_row( $this->current_code() ) );
	}

	/**
	 * The currency the shopper is browsing in.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	public function get_current_currency() {
		return $this->suppressed() ? $this->get_base_currency() : $this->current_code();
	}

	/**
	 * The store's base currency.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	public function get_base_currency() {
		if ( class_exists( '\Yay_Currency\Helpers\Helper' ) ) {
			return strtoupper( (string) \Yay_Currency\Helpers\Helper::default_currency_code() );
		}
		return strtoupper( (string) get_option( 'woocommerce_currency', '' ) );
	}

	/**
	 * The selected currency's code, ignoring suppression.
	 *
	 * Resolved from the cookie's post ID rather than Yay's own getter, which
	 * deletes duplicate-titled posts on the way (`Yay/includes/Helpers/Helper.php:86-98`).
	 * Geolocation is covered: Yay writes its resolved choice into `$_COOKIE`
	 * during `init`, before any pricing runs.
	 *
	 * @since 2.4.0
	 * @return string
	 */
	private function current_code() {
		$helper = '\Yay_Currency\Helpers\YayCurrencyHelper';
		if ( method_exists( $helper, 'get_id_selected_currency' ) ) {
			$id = (int) $helper::get_id_selected_currency();
		} else {
			$id = isset( $_COOKIE[ self::COOKIE ] ) ? (int) sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : 0;
		}
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && \Yay_Currency\Helpers\Helper::get_post_type() === $post->post_type ) {
				return strtoupper( (string) $post->post_title );
			}
		}
		return $this->get_base_currency();
	}

	/**
	 * Normalise a currency argument to a code.
	 *
	 * @since 2.4.0
	 * @param string|null $currency Currency code, or null for the current one.
	 * @return string
	 */
	private function code( $currency ) {
		return ( null === $currency || '' === (string) $currency )
			? $this->current_code()
			: strtoupper( (string) $currency );
	}

	/**
	 * Yay's resolved settings row for a currency code.
	 *
	 * `get_yay_currency_by_currency_code()` is a plain `get_posts()` with no
	 * delete. The meta argument to `get_apply_currency_meta_data()` is required —
	 * called with one argument the rate silently becomes 1.0
	 * (`Yay/includes/Helpers/YayCurrencyHelper.php:200-203`).
	 *
	 * @since 2.4.0
	 * @param string $code Currency code.
	 * @return array Row, or an empty array when no post matches.
	 */
	private function currency_row( $code ) {
		$code = strtoupper( (string) $code );
		if ( '' === $code || ! class_exists( '\Yay_Currency\Helpers\Helper' ) ) {
			return array();
		}
		$post = \Yay_Currency\Helpers\Helper::get_yay_currency_by_currency_code( $code );
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}
		$row = \Yay_Currency\Helpers\YayCurrencyHelper::get_apply_currency_meta_data( $post, get_post_meta( $post->ID, '', false ) );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Exchange rate including Yay's fee, or null when there is no usable one.
	 *
	 * A rate is stored as the literal string `'N/A'` when the finance API fails
	 * (`Yay/includes/Helpers/RateHelper.php:97`), and `(float) 'N/A'` is 0.0,
	 * which a configured fee turns into a small plausible rate.
	 *
	 * @since 2.4.0
	 * @param string     $code Currency code.
	 * @param array|null $row  Resolved row, or null to look it up.
	 * @return float|null
	 */
	private function rate( $code, $row = null ) {
		$row = is_array( $row ) ? $row : $this->currency_row( $code );
		$raw = isset( $row['rate'] ) ? $row['rate'] : null;
		if ( ! empty( $row ) && is_numeric( $raw ) ) {
			$rate = $this->rate_with_fee( $row, (float) $raw );
			if ( $rate > 0 ) {
				return $rate;
			}
		}
		$code = strtoupper( (string) $code );
		if ( ! isset( self::$logged[ $code ] ) ) {
			self::$logged[ $code ] = true;
			Layer::log( sprintf( 'No usable YayCurrency exchange rate for %s; amounts left unconverted.', $code ) );
		}
		return null;
	}

	/**
	 * Apply the currency's fee to its rate.
	 *
	 * Yay's `get_rate_fee()` reads `$row['fee']['type']` unguarded
	 * (`Yay/includes/Helpers/YayCurrencyHelper.php:552`), so a row whose
	 * serialized fee did not survive is a fatal there.
	 *
	 * @since 2.4.0
	 * @param array $row  Currency row.
	 * @param float $rate Raw rate.
	 * @return float
	 */
	private function rate_with_fee( array $row, $rate ) {
		$fee = isset( $row['fee'] ) ? $row['fee'] : null;
		if ( ! is_array( $fee ) || ! isset( $fee['type'], $fee['value'] ) || ! is_numeric( $fee['value'] ) ) {
			return (float) $rate;
		}
		return (float) \Yay_Currency\Helpers\YayCurrencyHelper::get_rate_fee( $row );
	}

	/**
	 * Base currency → display currency.
	 *
	 * Yay's own rounding is mirrored rather than skipped, which makes the
	 * conversion non-linear: callers must convert the unit and let WooCommerce
	 * multiply.
	 *
	 * @since 2.4.0
	 * @param float       $amount Amount in base currency.
	 * @param string|null $to     Target currency; null → the current one.
	 * @return float
	 */
	public function convert( $amount, $to = null ) {
		$amount = (float) $amount;
		if ( null === $to && $this->suppressed() ) {
			return $amount;
		}
		$code = $this->code( $to );
		if ( '' === $code || $code === $this->get_base_currency() ) {
			return $amount;
		}
		$row = $this->currency_row( $code );
		if ( empty( $row ) || null === $this->rate( $code, $row ) ) {
			return $amount;
		}
		return (float) \Yay_Currency\Helpers\YayCurrencyHelper::calculate_price_by_currency( $amount, false, $row );
	}

	/**
	 * Display currency → base currency.
	 *
	 * A bare division, mirroring Yay's own reverse
	 * (`Yay/includes/Helpers/YayCurrencyHelper.php:656-660`), which undoes
	 * neither rounding branch — so this is approximate by construction.
	 *
	 * @since 2.4.0
	 * @param float       $amount Amount in `$from`.
	 * @param string|null $from   Source currency; null → the current one.
	 * @return float
	 */
	public function get_base_amount( $amount, $from = null ) {
		$amount = (float) $amount;
		if ( null === $from && $this->suppressed() ) {
			return $amount;
		}
		return $this->reduce( $amount, $this->code( $from ) );
	}

	/**
	 * Divide an amount by a currency's rate, ignoring suppression.
	 *
	 * @since 2.4.0
	 * @param float  $amount Amount in `$code`.
	 * @param string $code   Currency the amount is expressed in.
	 * @return float
	 */
	private function reduce( $amount, $code ) {
		$amount = (float) $amount;
		$code   = strtoupper( (string) $code );
		if ( '' === $code || $code === $this->get_base_currency() ) {
			return $amount;
		}
		$rate = $this->rate( $code );
		return null === $rate ? $amount : $amount / $rate;
	}

	/**
	 * An order's amount converted back to base at the rate stamped on the order.
	 *
	 * The stamp is only written when the store keeps the shopper's currency
	 * through checkout; a default store's orders are created in base instead,
	 * which the short-circuit catches before any rate is read.
	 *
	 * @since 2.4.0
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
			return $amount / $rate;
		}
		$id = (int) $order->get_id();
		if ( ! isset( self::$logged_orders[ $id ] ) ) {
			self::$logged_orders[ $id ] = true;
			Layer::log( sprintf( 'Order %1$d is in %2$s with no YayCurrency rate stamp; reduced at today\'s rate.', $id, $currency ) );
		}
		return $this->reduce( $amount, $currency );
	}

	/**
	 * Decimal places for a currency.
	 *
	 * From the currency's own row, never `wc_get_price_decimals()` — Yay filters
	 * that, so reading it for a base-currency figure returns the browsing
	 * currency's precision.
	 *
	 * @since 2.4.0
	 * @param string|null $currency Currency code; null → the current one.
	 * @return int
	 */
	public function get_decimals( $currency = null ) {
		$row = $this->currency_row( $this->code( $currency ) );
		if ( isset( $row['numberDecimal'] ) && is_numeric( $row['numberDecimal'] ) ) {
			return (int) $row['numberDecimal'];
		}
		$default = class_exists( '\Yay_Currency\Helpers\Helper' )
			? \Yay_Currency\Helpers\Helper::default_price_num_decimals()
			: get_option( 'woocommerce_price_num_decimals', 2 );
		return is_numeric( $default ) ? (int) $default : 2;
	}

	/**
	 * Format an amount already expressed in `$currency`.
	 *
	 * A null `$currency` resolves through `get_current_currency()`, which is base
	 * on a suppressed request; Yay's live selection would render a euro symbol
	 * against a dollar charge on a default store's checkout.
	 *
	 * @since 2.4.0
	 * @param float       $amount   Amount in `$currency`.
	 * @param string|null $currency Currency code; null → the current one.
	 * @return string
	 */
	public function format( $amount, $currency = null ) {
		$code = ( null === $currency || '' === (string) $currency )
			? $this->get_current_currency()
			: strtoupper( (string) $currency );
		if ( ! function_exists( 'wc_price' ) ) {
			return number_format( (float) $amount, $this->get_decimals( $code ) );
		}
		$args = array(
			'currency' => $code,
			'decimals' => $this->get_decimals( $code ),
		);
		$row  = $code === $this->current_code() ? array() : $this->currency_row( $code );
		if ( empty( $row['symbol'] ) ) {
			return wc_price( (float) $amount, $args );
		}
		// Yay renders its live selection's symbol and separators whatever currency wc_price() is given.
		$args['thousand_separator'] = (string) ( $row['thousandSeparator'] ?? wc_get_price_thousand_separator() );
		$args['decimal_separator']  = (string) ( $row['decimalSeparator'] ?? wc_get_price_decimal_separator() );
		$symbol                     = (string) $row['symbol'];
		$pin                        = function () use ( $symbol ) {
			return $symbol;
		};
		add_filter( 'yay_currency_woocommerce_currency_symbol', $pin, PHP_INT_MAX );
		try {
			return wc_price( (float) $amount, $args );
		} finally {
			remove_filter( 'yay_currency_woocommerce_currency_symbol', $pin, PHP_INT_MAX );
		}
	}

	/**
	 * Does YayCurrency Pro resolve a fixed per-currency price for this product?
	 *
	 * @since 2.4.0
	 * @param \WC_Product $product Product to test.
	 * @param float|null  $raw     Unused: a fixed price is stored per currency and
	 *                             never derived from the product's own price.
	 * @return bool
	 */
	public function has_custom_price( \WC_Product $product, $raw = null ) {
		if ( $this->suppressed() ) {
			return false;
		}
		return null !== $this->fixed_price( $product, $this->get_current_currency() );
	}

	/**
	 * YayCurrency's own resolved display price for a product.
	 *
	 * @since 2.4.0
	 * @param \WC_Product $product Product to price.
	 * @param float|null  $raw     Base price to convert; null reads the product's own.
	 * @return float
	 */
	public function get_display_price( \WC_Product $product, $raw = null ) {
		$raw = null === $raw ? $this->raw_base_price( $product ) : (float) $raw;
		if ( $this->suppressed() ) {
			return $raw;
		}
		$fixed = $this->fixed_price( $product, $this->get_current_currency() );
		return null === $fixed ? $this->convert( $raw ) : $fixed;
	}

	/**
	 * Pro's fixed-price entry for a product in one currency.
	 *
	 * @since 2.4.0
	 * @param \WC_Product $product Product to read.
	 * @param string      $code    Currency code.
	 * @return array|null Entry, or null when Pro is absent, the option is off, or no entry exists.
	 */
	private function fixed_entry( \WC_Product $product, $code ) {
		$code = strtoupper( (string) $code );
		if ( '' === $code || $code === $this->get_base_currency() ) {
			return null;
		}
		if ( ! class_exists( '\Yay_Currency\Helpers\FixedPriceHelper' ) || ! get_option( self::OPTION_FIXED, 0 ) ) {
			return null;
		}
		$entries = $product->get_meta( self::META_FIXED, true );
		if ( ! is_array( $entries ) || ! isset( $entries[ $code ] ) || ! is_array( $entries[ $code ] ) ) {
			return null;
		}
		return $entries[ $code ];
	}

	/**
	 * The price Pro's entry resolves to, or null.
	 *
	 * Null rather than 0.0 for "nothing resolved": a zero would read as a
	 * legitimate free price and invert every sale comparison downstream.
	 *
	 * @since 2.4.0
	 * @param \WC_Product $product Product to price.
	 * @param string      $code    Currency code.
	 * @return float|null
	 */
	private function fixed_price( \WC_Product $product, $code ) {
		$entry = $this->fixed_entry( $product, $code );
		if ( null === $entry || empty( $entry['price'] ) || ! is_numeric( $entry['price'] ) ) {
			return null;
		}
		$on_sale = $product->is_on_sale( 'edit' );
		$key     = $on_sale ? 'sale_price' : 'regular_price';
		if ( isset( $entry[ $key ] ) && is_numeric( $entry[ $key ] ) && (float) $entry[ $key ] > 0 ) {
			return (float) $entry[ $key ];
		}
		return (float) $entry['price'];
	}

	/**
	 * A product's unit price in base currency.
	 *
	 * Yay converts through filters and never writes the converted figure back
	 * onto the product, so the edit-context read is the base price.
	 *
	 * @since 2.4.0
	 * @param \WC_Product $product Product to price.
	 * @return float
	 */
	public function raw_base_price( \WC_Product $product ) {
		return (float) $product->get_price( 'edit' );
	}

	/**
	 * Read variation prices with Yay's conversion suspended.
	 *
	 * The priority is resolved at call time, never hardcoded: Yay registers at
	 * `SupportHelper::get_filters_priority()`, 10 by default and 100000 under
	 * B2B, and `remove_filter()` no-ops silently on a priority mismatch.
	 *
	 * @since 2.4.0
	 * @param callable $read Reader returning the raw prices.
	 * @return mixed Whatever `$read` returns.
	 */
	public function with_raw_variation_prices( callable $read ) {
		// Nesting would let the inner `finally` restore conversion, and drop the hash
		// discriminator, while the outer read is still running.
		if ( self::$suspended || ! class_exists( '\Yay_Currency\Helpers\SupportHelper' ) ) {
			return $read();
		}
		self::$suspended = true;
		$priority        = (int) \Yay_Currency\Helpers\SupportHelper::get_filters_priority();
		$removed         = array();
		foreach ( self::VARIATION_HOOKS as $hook ) {
			foreach ( $this->yay_price_callbacks( $hook, $priority ) as $callback ) {
				if ( remove_filter( $hook, $callback, $priority ) ) {
					$removed[] = array( $hook, $callback );
				}
			}
		}
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'raw_prices_hash' ), 10000 );
		try {
			return $read();
		} finally {
			self::$suspended = false;
			remove_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'raw_prices_hash' ), 10000 );
			foreach ( $removed as $entry ) {
				add_filter( $entry[0], $entry[1], $priority, 2 );
			}
		}
	}

	/**
	 * Yay's own price callbacks registered on one hook at one priority.
	 *
	 * Found by inspection rather than rebuilt from a class name: Yay's
	 * registrations live on an instance it exposes no accessor for, and a
	 * reconstructed callback would not match the registered one.
	 *
	 * @since 2.4.0
	 * @param string $hook     Filter name.
	 * @param int    $priority Priority to inspect.
	 * @return array[] Callables.
	 */
	private function yay_price_callbacks( $hook, $priority ) {
		global $wp_filter;
		$found = array();
		if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
			return $found;
		}
		foreach ( (array) $wp_filter[ $hook ]->callbacks[ $priority ] as $entry ) {
			$callback = isset( $entry['function'] ) ? $entry['function'] : null;
			if ( ! is_array( $callback ) || 2 !== count( $callback ) || ! is_object( $callback[0] ) ) {
				continue;
			}
			if ( 'custom_raw_price' === $callback[1] && $callback[0] instanceof \Yay_Currency\Engine\FEPages\WooCommerceCurrency ) {
				$found[] = $callback;
			}
		}
		return $found;
	}

	/**
	 * Discriminate the variation-price cache key for a raw read.
	 *
	 * A named method, not a closure: a closure on a shared filter can never be
	 * removed again.
	 *
	 * @since 2.4.0
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
	 * Cast a YayCurrency price to float.
	 *
	 * `format_price_currency()` returns a `number_format()` string with the
	 * currency's own separators, so a bare cast of `"40,50"` loses the cents.
	 * Never suppressed: Yay's separator filters stay registered on checkout.
	 *
	 * @since 2.4.0
	 * @param mixed $value Price as YayCurrency returned it.
	 * @return float
	 */
	public function to_float( $value ) {
		if ( is_float( $value ) || is_int( $value ) ) {
			return (float) $value;
		}
		$text = (string) $value;
		$row  = $this->currency_row( $this->current_code() );
		if ( ! empty( $row['thousandSeparator'] ) ) {
			$text = str_replace( (string) $row['thousandSeparator'], '', $text );
		}
		if ( ! empty( $row['decimalSeparator'] ) ) {
			$text = str_replace( (string) $row['decimalSeparator'], '.', $text );
		}
		return (float) preg_replace( '/[^0-9.\-]/', '', $text );
	}
}
