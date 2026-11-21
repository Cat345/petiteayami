<?php
/**
 * Layer — the engine-agnostic facade the rest of the plugin talks to.
 *
 * Nothing outside `includes/compat/discount/` may name a discount-engine plugin.
 * Callers ask this class; it picks the first adapter reporting `is_active()` from
 * an ordered registry and dispatches to it.
 *
 * With no adapter active every method is identity. Every dispatch is wrapped, so
 * a discount plugin that fatals yields the caller's fallback instead.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Discount;

use WFFN\Bundles\Includes\Compat\Currency\Layer as Currency_Layer;
use WFFN\Bundles\Includes\Rest\User_Preference_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Engine-agnostic discount facade.
 */
class Layer {

	/**
	 * Adapter class names, as strings, in resolution order.
	 *
	 * Strings rather than instances: the autoloader must not load a class before
	 * its plugin is known to be present.
	 */
	private static $adapters = array(
		Adp::class,
		Wdr::class,
	);

	/** @var Engine|null|false Resolved adapter; false = not yet resolved. */
	private static $active = false;

	/** @var int Nesting depth of the display-suppression window. */
	private static $suppression_depth = 0;

	/**
	 * Register hooks. Called from Plugin::init() at `plugins_loaded:20`.
	 *
	 * Does not resolve the adapter: an engine may not have installed its runtime
	 * instance yet, and an eager resolution would memoise null for the request.
	 *
	 * @return void
	 */
	public static function boot() {
		// Priority 20: after the engines themselves, which install on `init` at 10.
		add_action( 'init', array( __CLASS__, 'register_adapter_hooks' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ), 10 );
	}

	/**
	 * Let every registered adapter warn the merchant about its engine.
	 *
	 * Not restricted to the active adapter: one reporting itself inactive on a
	 * version gate has the most important thing to say.
	 *
	 * @return void
	 */
	public static function render_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_notice_screen( (string) $screen->id ) ) {
			return;
		}
		foreach ( self::registered() as $class ) {
			if ( ! is_string( $class ) || ! self::present( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			try {
				$adapter = new $class();
				if ( $adapter instanceof Engine ) {
					$adapter->render_notices();
				}
			} catch ( \Exception $e ) {
				self::log( $class . ' notices failed: ' . $e->getMessage() );
			} catch ( \Error $e ) {
				self::log( $class . ' notices failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Whether an admin screen is one where the discount engines' warnings belong.
	 *
	 * @param string $screen_id Current screen id.
	 * @return bool
	 */
	private static function is_notice_screen( $screen_id ) {
		/**
		 * Filters the admin screens that show the discount-engine compatibility warnings.
		 *
		 * Each entry matches any screen id containing it; 'plugins' matches the Plugins screen.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $screens Screen id fragments.
		 */
		$screens = (array) apply_filters( 'wffn_bundles_discount_notice_screens', array( 'plugins', 'wffn-bundles', 'woo_discount_rules', 'wdp_settings' ) );
		foreach ( $screens as $fragment ) {
			$fragment = (string) $fragment;
			if ( 'plugins' === $fragment ? 'plugins' === $screen_id : ( '' !== $fragment && false !== strpos( $screen_id, $fragment ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Print one dismissible engine warning, unless this user has closed it.
	 *
	 * Keyed by its text, so a closed warning stays closed while a different one
	 * still shows.
	 *
	 * @param string $message Escaped message text.
	 * @return void
	 */
	public static function notice( $message ) {
		$key = 'discount_' . substr( md5( wp_strip_all_tags( (string) $message ) ), 0, 12 );
		if ( in_array( $key, User_Preference_Controller::dismissed_notices( get_current_user_id() ), true ) ) {
			return;
		}
		self::enqueue_dismiss_script();
		printf(
			'<div class="notice notice-warning is-dismissible" data-wffn-bundles-notice="%1$s"><p>%2$s</p></div>',
			esc_attr( $key ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Save a closed warning's key to the user's preferences.
	 *
	 * @return void
	 */
	private static function enqueue_dismiss_script() {
		if ( wp_script_is( 'wffn-bundles-notice-dismiss', 'enqueued' ) ) {
			return;
		}
		wp_register_script( 'wffn-bundles-notice-dismiss', false, array( 'wp-api-fetch' ), WFFN_BUNDLES_VERSION, true );
		wp_enqueue_script( 'wffn-bundles-notice-dismiss' );
		wp_add_inline_script(
			'wffn-bundles-notice-dismiss',
			'document.addEventListener("click",function(e){var b=e.target.closest("[data-wffn-bundles-notice] .notice-dismiss");if(!b){return;}'
			. 'wp.apiFetch({path:"/wffn-bundles-admin/v1/user-preferences/' . (int) get_current_user_id() . '",method:"POST",'
			. 'data:{data_type:"notification",data:[b.closest("[data-wffn-bundles-notice]").getAttribute("data-wffn-bundles-notice")]}});});'
		);
	}

	/**
	 * Let the active adapter register its own engine-specific hooks.
	 *
	 * @return void
	 */
	public static function register_adapter_hooks() {
		// Before active_adapter(): the null-adapter return below is the
		// version-gated-out case the defensive hooks exist for.
		self::boot_defensive_hooks();
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return;
		}
		self::dispatch( $adapter, 'register_hooks', array(), null );
	}

	/**
	 * Let every registered adapter defend our callbacks, active or not.
	 *
	 * An engine that suppresses foreign hooks does so whether or not it is the
	 * adapter pricing this request, so this cannot be restricted to the active one.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function boot_defensive_hooks() {
		foreach ( self::registered() as $class ) {
			if ( ! is_string( $class ) || ! self::present( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			try {
				$adapter = new $class();
				if ( $adapter instanceof Engine ) {
					$adapter->register_defensive_hooks();
				}
			} catch ( \Exception $e ) {
				self::log( $class . ' defensive hooks failed: ' . $e->getMessage() );
			} catch ( \Error $e ) {
				self::log( $class . ' defensive hooks failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * The adapter handling this request, or null.
	 *
	 * Resolved lazily and memoised. Before `init` the answer is null and is not
	 * memoised, or an engine installing on `init` would be latched absent.
	 *
	 * @return Engine|null
	 */
	public static function active_adapter() {
		if ( ! did_action( 'init' ) ) {
			return null;
		}
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
				if ( $adapter instanceof Engine && $adapter->is_active() ) {
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
	 * Is the engine an adapter targets loaded? Asked before the adapter's file is.
	 *
	 * Each test is the first thing the adapter's notices, defensive hooks and
	 * is_active() require. An adapter with no test here is always loaded and asked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Adapter class name.
	 * @return bool
	 */
	private static function present( $class ) {
		switch ( $class ) {
			case Adp::class:
				return function_exists( 'adp_functions' );
			case Wdr::class:
				return defined( 'WDR_CORE' ) || class_exists( '\WDR\Pro\Route' );
		}
		return true;
	}

	/**
	 * The discount adapters that may boot on this request.
	 *
	 * @return string[] Adapter class names.
	 */
	private static function registered() {
		return (array) apply_filters( 'wffn_bundles_discount_adapters', self::$adapters );
	}

	/**
	 * Is any discount engine pricing lines on this request?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return null !== self::active_adapter();
	}

	/**
	 * Mark one cart item as ours, so the active engine leaves its price alone.
	 *
	 * @param array $cart_item Cart item, mutated in place.
	 * @return void
	 */
	public static function claim_line( array &$cart_item ) {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return;
		}
		try {
			$adapter->claim_line( $cart_item );
		} catch ( \Exception $e ) {
			self::log( 'claim_line() failed: ' . $e->getMessage() );
		} catch ( \Error $e ) {
			self::log( 'claim_line() failed: ' . $e->getMessage() );
		}
	}

	/**
	 * What the active engine would have applied to this line.
	 *
	 * @param array $cart_item Cart item to interrogate.
	 * @return array Empty when no engine is active; never null.
	 */
	public static function get_applied_discounts( array $cart_item ) {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return array();
		}
		return (array) self::dispatch( $adapter, 'get_applied_discounts', array( $cart_item ), array() );
	}

	/**
	 * The line's unit price before the engine's discounts, in base currency.
	 *
	 * @param array $cart_item Cart item to price.
	 * @param float $fallback  Value to return when no engine is active.
	 * @return float
	 */
	public static function get_price_before_discounts( array $cart_item, $fallback = 0.0 ) {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return (float) $fallback;
		}
		return (float) self::dispatch( $adapter, 'get_price_before_discounts', array( $cart_item ), $fallback );
	}

	/**
	 * Offer the active engine our final figure to record as its own discount.
	 *
	 * @param array  $cart_item Cart item the discount belongs to.
	 * @param float  $amount    Discount amount in base currency.
	 * @param string $type      One of `percentage`, `fixed`, `no_discount`.
	 * @return bool
	 */
	public static function register_our_discount( array $cart_item, $amount, $type ) {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return false;
		}
		return (bool) self::dispatch( $adapter, 'register_our_discount', array( $cart_item, $amount, $type ), false );
	}

	/**
	 * Does the active engine write the charged price itself?
	 *
	 * @since 1.1.0
	 * @return bool True with no engine active, so the cart folds nothing.
	 */
	public static function supplies_own_price() {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return true;
		}
		return (bool) self::dispatch( $adapter, 'supplies_own_price', array(), true );
	}

	/**
	 * The unit price the active engine charged a line, in the shopper's currency.
	 *
	 * Optional on an adapter, so one written before it simply reports nothing.
	 *
	 * @since 1.1.0
	 * @param array $cart_item Cart item to interrogate.
	 * @return float|null Null when no engine priced this line.
	 */
	public static function get_charged_unit( array $cart_item ) {
		$adapter = self::active_adapter();
		if ( null === $adapter || ! method_exists( $adapter, 'get_charged_unit' ) ) {
			return null;
		}
		$unit = self::dispatch( $adapter, 'get_charged_unit', array( $cart_item ), null );
		return null === $unit ? null : (float) $unit;
	}

	/**
	 * Sort key deciding which engine computes first; ours is 0.
	 *
	 * @return int
	 */
	public static function get_application_order() {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return 0;
		}
		return (int) self::dispatch( $adapter, 'get_application_order', array(), 0 );
	}

	/**
	 * Run `$render` with the active engine's price-display filters suspended.
	 *
	 * Depth-counted, because an adapter's suspension helper need not be reentrant
	 * nor restore on an exception; the counter and the `finally` are ours.
	 *
	 * @param callable $render Renderer to run inside the window.
	 * @return mixed Whatever `$render` returns.
	 */
	public static function with_display_suppressed( callable $render ) {
		$adapter = self::active_adapter();
		if ( null === $adapter ) {
			return $render();
		}
		if ( self::$suppression_depth > 0 ) {
			++self::$suppression_depth;
			try {
				return $render();
			} finally {
				--self::$suppression_depth;
			}
		}
		++self::$suppression_depth;
		try {
			return $adapter->with_display_suppressed( $render );
		} catch ( \Exception $e ) {
			self::log( 'with_display_suppressed() failed: ' . $e->getMessage() );
		} catch ( \Error $e ) {
			self::log( 'with_display_suppressed() failed: ' . $e->getMessage() );
		} finally {
			--self::$suppression_depth;
		}
		// A failed suspension costs the price display, never the widget itself.
		return $render();
	}

	/**
	 * Cast a price a third party produced to float.
	 *
	 * Converted values can arrive as `number_format()` strings, where a bare
	 * `(float)` cast truncates the cents under a comma decimal separator.
	 *
	 * @param mixed $value Price as the third party returned it.
	 * @return float
	 */
	public static function to_float( $value ) {
		return Currency_Layer::to_float( $value );
	}

	/**
	 * Call one adapter method, returning `$fallback` if it throws.
	 *
	 * @param Engine $adapter  Active adapter.
	 * @param string $method   Method name.
	 * @param array  $args     Positional arguments.
	 * @param mixed  $fallback Value to return on failure.
	 * @return mixed
	 */
	private static function dispatch( Engine $adapter, $method, array $args, $fallback ) {
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
	 * Record a discount-compatibility problem for the merchant.
	 *
	 * Never thrown onward: a broken discount plugin degrades to our own pricing.
	 *
	 * @param string $message What went wrong.
	 * @return void
	 */
	public static function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( (string) $message, array( 'source' => 'wffn-bundles-discount' ) );
		}
	}
}
