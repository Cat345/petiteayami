<?php
/**
 * Adp — Advanced Dynamic Pricing for WooCommerce (AlgolPlus) adapter.
 *
 * The only file in the plugin permitted to name ADP. ADP collects our figure
 * through its own `adp_product_get_price` filter, so no priority contest with
 * ADP is needed. Outside the tested range (free 4.13.x to 5.0.x) the adapter reports
 * itself inactive.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Discount;

use WFFN\Bundles\Includes\Compat\Currency\Layer as Currency_Layer;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Storage\Bundle_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Dynamic Pricing for WooCommerce adapter.
 */
class Adp implements Engine {

	/** Lowest ADP version whose internals this adapter was written against. */
	const MIN_VERSION = '4.13.0';

	/** First ADP version this adapter does not claim; the gate is `< MAX_VERSION`. */
	const MAX_VERSION = '5.1.0';

	/** WooCommerce version that introduced `woocommerce_coupon_get_items_to_apply`. */
	const COUPON_SEAM_WC = '8.8';

	/** ADP's cart-item attribute that makes its own rules skip a line. */
	const ATTR_IMMUTABLE = 'immutable';

	/** @var string[]|null ADP-originated coupon codes, resolved once per request. */
	private $coupon_codes = null;

	/** @var bool Are we already inside an ADP price query? */
	private $deriving = false;

	/**
	 * Is ADP present, running, and within the tested version range?
	 *
	 * Probes `adp_functions()`, the instance ADP installs on `init`: its classes are
	 * defined at file load, so `class_exists()` would be true with no engine.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( ! function_exists( 'adp_functions' ) || null === adp_functions() ) {
			return false;
		}
		return self::version_supported();
	}

	/**
	 * Is the installed ADP one this adapter was written against?
	 *
	 * ADP Pro is never claimed. `WC_ADP_PRO_VERSION_PATH` is the same test as
	 * `Context::isBaseVersion()` without the dependency.
	 *
	 * @return bool
	 */
	private static function version_supported() {
		if ( defined( 'WC_ADP_PRO_VERSION_PATH' ) || ! defined( 'WC_ADP_VERSION' ) ) {
			return false;
		}
		return version_compare( WC_ADP_VERSION, self::MIN_VERSION, '>=' )
			&& version_compare( WC_ADP_VERSION, self::MAX_VERSION, '<' );
	}

	/**
	 * Is the cart-level coupon seam available on this WooCommerce?
	 *
	 * @return bool
	 */
	private static function coupon_seam_available() {
		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::COUPON_SEAM_WC, '>=' );
	}

	/**
	 * Register ADP's own seams.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'adp_product_get_price', array( $this, 'supply_price' ), 10, 6 );
		if ( self::coupon_seam_available() ) {
			add_filter( 'woocommerce_coupon_get_items_to_apply', array( $this, 'scope_coupon_items' ), 10, 2 );
		}
		// Priority 20: ADP builds this array at 10, and we zero our lines out of it.
		add_filter( 'woocommerce_coupon_custom_discounts_array', array( $this, 'scope_custom_discounts' ), 20, 2 );
	}

	/**
	 * No defensive hooks: `ExternalHooksSuppressor` builds its allowlist as a local
	 * array with no `apply_filters()`, so there is nothing to register into.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_defensive_hooks() {
	}

	/**
	 * ADP writes the charged price itself, through `supply_price()`.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function supplies_own_price() {
		return true;
	}

	/**
	 * The unit ADP charged this line, from the record it keeps on the cart item.
	 *
	 * A claimed container is left to our own pin. A record in another currency is
	 * from before a switch and is stale until ADP prices the cart again.
	 *
	 * @since 1.1.0
	 * @param array $cart_item Cart item to interrogate.
	 * @return float|null Unit in the shopper's currency, or null when ADP has not priced this line.
	 */
	public function get_charged_unit( array $cart_item ) {
		$record = isset( $cart_item['adp'] ) && is_array( $cart_item['adp'] ) ? $cart_item['adp'] : array();
		if ( ! isset( $record['new_price'] ) || ! is_numeric( $record['new_price'] ) ) {
			return null;
		}
		if ( in_array( self::ATTR_IMMUTABLE, (array) ( $record['attr'] ?? array() ), true ) ) {
			return null;
		}
		$code = isset( $record['currency']['code'] ) ? (string) $record['currency']['code'] : '';
		if ( '' === $code || Currency_Layer::get_current_currency() !== $code ) {
			return null;
		}
		return (float) $record['new_price'];
	}

	/**
	 * Stamp a Fixed Bundle container immutable, and nothing else.
	 *
	 * Ordinary bundle lines are left to ADP: it applies its rule to the bundle
	 * price we hand it, and stamping them would compound the discount every pass.
	 * A container is the exception: its catalog price is 0, so nothing can quote
	 * what ADP would do to it and the pack must keep its configured price.
	 *
	 * @param array $cart_item Cart item, mutated in place.
	 * @return void
	 */
	public function claim_line( array &$cart_item ) {
		if ( empty( $cart_item['wffn_bundles_bundle'] ) ) {
			return;
		}
		if ( 'container' !== (string) ( $cart_item['wffn_bundles_bundle']['role'] ?? '' ) ) {
			return;
		}
		if ( ! isset( $cart_item['adp'] ) || ! is_array( $cart_item['adp'] ) ) {
			$cart_item['adp'] = array();
		}
		if ( ! isset( $cart_item['adp']['attr'] ) || ! is_array( $cart_item['adp']['attr'] ) ) {
			$cart_item['adp']['attr'] = array();
		}
		if ( ! in_array( self::ATTR_IMMUTABLE, $cart_item['adp']['attr'], true ) ) {
			$cart_item['adp']['attr'][] = self::ATTR_IMMUTABLE;
		}
	}

	/**
	 * Supply our bundle per-unit figure from inside ADP's own pricing loop.
	 *
	 * ADP treats the return as the line's base price and applies its own rules to
	 * it, so the bundle price goes back, never one this layer has folded into.
	 *
	 * The figure is in the active currency: ADP persists line totals inside
	 * `WcNoFilterWorker::calculateTotals()`, where the switcher's conversion filter
	 * is unset and cannot run. The exception is a request ADP's own currency
	 * controller converts: ADP then reads the figure as base and applies its rate.
	 *
	 * @param mixed        $price       Incoming value, null unless another plugin set one.
	 * @param \WC_Product  $product     Product being priced.
	 * @param array        $variation   Variation attributes.
	 * @param int          $qty         Line quantity.
	 * @param array        $third_party Cart-item keys ADP does not own.
	 * @param mixed        $facade      ADP's cart-item facade, or null off the cart path.
	 * @return mixed Our base unit price, or `$price` untouched.
	 */
	public function supply_price( $price, $product, $variation = array(), $qty = 1, $third_party = array(), $facade = null ) {
		// Null off the cart path, where dereferencing it would fatal a product page.
		if ( ! is_object( $facade ) || ! method_exists( $facade, 'getQty' ) ) {
			return $price;
		}
		if ( ! is_array( $third_party ) || empty( $third_party['wffn_bundles_bundle'] ) ) {
			return $price;
		}
		if ( ! $product instanceof \WC_Product ) {
			return $price;
		}
		$item = array(
			'wffn_bundles_bundle' => (array) $third_party['wffn_bundles_bundle'],
			'data'       => $product,
			'quantity'   => max( 1, (int) $qty ),
		);
		$unit = Cart::stored_unit( $item );
		return self::engine_converts() ? $unit : Cart::charge_unit( $unit );
	}

	/**
	 * Does ADP's own currency controller convert this request's prices?
	 *
	 * Read from ADP's context rather than the switcher: ADP resolves the currency
	 * once, when it builds that context, and converts only when it saw a change.
	 *
	 * @return bool
	 */
	private static function engine_converts() {
		if ( ! function_exists( 'adp_context' ) ) {
			return false;
		}
		$context = adp_context();
		if ( ! is_object( $context ) || ! isset( $context->currencyController ) || ! is_object( $context->currencyController ) ) {
			return false;
		}
		return method_exists( $context->currencyController, 'isCurrencyChanged' ) && (bool) $context->currencyController->isCurrencyChanged();
	}

	/**
	 * What ADP would have discounted this line by, as a percentage of our base unit.
	 *
	 * @param array $cart_item Cart item to interrogate.
	 * @return float 0.0 when ADP would apply nothing, or when the answer is unusable.
	 */
	private function derive_percentage( array $cart_item ) {
		if ( $this->deriving || ! $this->is_active() ) {
			return 0.0;
		}
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}
		$bundle = isset( $cart_item['wffn_bundles_bundle'] ) ? (array) $cart_item['wffn_bundles_bundle'] : array();
		// ADP answers from the catalog price whatever the handed product carries, so
		// the percentage must be read against that same figure.
		$base = isset( $bundle['unit'] ) ? (float) $bundle['unit'] : Currency_Layer::raw_base_price( $product );
		// Gift lines and Fixed Bundle children price at 0; dividing is a PHP 8 fatal.
		if ( $base <= 0 ) {
			return 0.0;
		}
		// A Fixed Bundle container has a catalog price of 0, so ADP would answer free
		// and the quote would read as 100% off. The cart itself is unaffected.
		if ( Currency_Layer::raw_base_price( $product ) <= 0 ) {
			return 0.0;
		}
		$qty = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );

		// A clone, so nothing here can disturb the cart's own object.
		$clean = clone $product;
		$clean->set_price( $base );

		// Quoted as the cart is charged: there ADP takes our unit as the line's custom
		// price (supply_price()) and applies its rule to it. Its plain product quote
		// asks for no custom price and, for a product on sale, weighs the rule on the
		// regular price against the sale price, so an on-sale host quoted no rule at
		// all. The quote below hands ADP the same custom price through its own filter,
		// and carries the unit in its cart-item data: ADP caches a processed product
		// by id and that data, and the page's own price display has already cached
		// this product's plain quote.
		$quote_id = $clean->get_id();
		$custom   = static function ( $price, $p = null ) use ( $quote_id, $base ) {
			return null === $price && $p instanceof \WC_Product && $p->get_id() === $quote_id ? $base : $price;
		};
		add_filter( 'adp_product_get_price', $custom, 5, 2 );

		$this->deriving = true;
		try {
			// True, not false: false reuses the cart-aware processor, which sees the
			// price our own pass wrote and compounds it on every totals pass. The call
			// also seats that empty cart in the processor the quote below runs on.
			$adp       = adp_functions()->getDiscountedProductPrice( $clean, $qty, true );
			$processor = method_exists( adp_functions(), 'getProductProcessor' ) ? adp_functions()->getProductProcessor() : null;
			$processed = is_object( $processor ) && method_exists( $processor, 'calculateProduct' )
				? $processor->calculateProduct( $clean, $qty, array( 'wffn_bundles_quote' => (string) $base ) )
				: null;
			if ( is_object( $processed ) && ! method_exists( $processed, 'getLowestPrice' ) && method_exists( $processed, 'getPrice' ) ) {
				$adp = $processed->getPrice();
			}
		} catch ( \Exception $e ) {
			Layer::log( 'getDiscountedProductPrice() failed: ' . $e->getMessage() );
			return 0.0;
		} catch ( \Error $e ) {
			Layer::log( 'getDiscountedProductPrice() failed: ' . $e->getMessage() );
			return 0.0;
		} finally {
			$this->deriving = false;
			remove_filter( 'adp_product_get_price', $custom, 5 );
		}

		// A variable parent returns array( low, high ); no single value is correct.
		if ( ! is_int( $adp ) && ! is_float( $adp ) ) {
			return 0.0;
		}
		$percent = ( 1 - ( Layer::to_float( $adp ) / self::answer_reference( $base, Layer::to_float( $adp ) ) ) ) * 100;
		if ( $percent <= 0 ) {
			return 0.0;
		}
		return min( 100.0, $percent );
	}

	/**
	 * The figure ADP's answer is a discount ON, in the currency it answered in.
	 *
	 * ADP prices in the shopper's currency whenever a switcher feeds its own
	 * currency controller, while `$base` is base currency; dividing one by the other
	 * reads the exchange rate as a discount. Whether ADP converts depends on what
	 * its context resolved, so the reference is picked by nearness to the answer.
	 *
	 * @param float $base   Undiscounted unit price, base currency.
	 * @param float $answer What ADP returned.
	 * @return float Never zero; the caller has already excluded a zero base.
	 */
	private static function answer_reference( $base, $answer ) {
		if ( ! Currency_Layer::is_active() ) {
			return $base;
		}
		$converted = (float) Currency_Layer::convert( $base );
		if ( $converted <= 0 || $converted === $base ) {
			return $base;
		}
		// Nearness misreads a rate just above 1: at 1.20, 10% off sits nearer base.
		if ( self::engine_converts() ) {
			return $converted;
		}
		return abs( $answer - $converted ) < abs( $answer - $base ) ? $converted : $base;
	}

	/**
	 * What ADP would have applied to this line, had it not been claimed.
	 *
	 * Accepts any array carrying `data` and `quantity`, so a product-page offer row
	 * quotes with the same arithmetic the cart charges.
	 *
	 * @param array $cart_item Cart item to interrogate.
	 * @return array
	 */
	public function get_applied_discounts( array $cart_item ) {
		$percent = $this->derive_percentage( $cart_item );
		if ( $percent <= 0 ) {
			return array();
		}
		return array(
			array(
				'source' => 'adp',
				'amount' => $percent,
				'type'   => 'percentage',
			),
		);
	}

	/**
	 * The line's unit price before ADP's discounts, in base currency.
	 *
	 * @param array $cart_item Cart item to price.
	 * @return float
	 */
	public function get_price_before_discounts( array $cart_item ) {
		$bundle = isset( $cart_item['wffn_bundles_bundle'] ) ? (array) $cart_item['wffn_bundles_bundle'] : array();
		if ( isset( $bundle['unit'] ) ) {
			return (float) $bundle['unit'];
		}
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		return $product instanceof \WC_Product ? Currency_Layer::raw_base_price( $product ) : 0.0;
	}

	/**
	 * ADP has no API for recording a foreign plugin's discount.
	 *
	 * @param array  $cart_item Cart item the discount belongs to.
	 * @param float  $amount    Discount amount in base currency.
	 * @param string $type      One of `percentage`, `fixed`, `no_discount`.
	 * @return bool Always false.
	 */
	public function register_our_discount( array $cart_item, $amount, $type ) {
		return false;
	}

	/**
	 * Bundle pricing computes first; ADP's product rules fold into it.
	 *
	 * @return int
	 */
	public function get_application_order() {
		return 0;
	}

	/**
	 * Run `$render` with ADP's four price-display filters suspended.
	 *
	 * `processWithout()` is the only one of ADP's two removal helpers that restores;
	 * `removeHooks()` discards its `HighLander` and is irreversible.
	 *
	 * @param callable $render Renderer to run inside the window.
	 * @return mixed
	 */
	public function with_display_suppressed( callable $render ) {
		$price_display = '\ADP\BaseVersion\Includes\PriceDisplay\PriceDisplay';
		if ( ! class_exists( $price_display ) ) {
			return $render();
		}
		return call_user_func( array( $price_display, 'processWithout' ), $render );
	}

	/**
	 * Drop bundle lines from an ADP-originated coupon's item list.
	 *
	 * ADP's cart-level rules arrive as virtual core coupons that no item flag can
	 * stop. Identification fails open: an unrecognised virtual coupon is left alone
	 * rather than silently scoped away from bundle lines.
	 *
	 * @param array $items  Items the coupon will be applied to.
	 * @param mixed $coupon Coupon being applied.
	 * @return array
	 */
	public function scope_coupon_items( $items, $coupon = null ) {
		if ( ! is_array( $items ) || ! $coupon instanceof \WC_Coupon ) {
			return $items;
		}
		// A virtual coupon never reaches set_id() (`class-wc-coupon.php:122-127`).
		if ( 0 !== (int) $coupon->get_id() ) {
			return $items;
		}
		if ( ! $this->is_adp_coupon( $coupon ) ) {
			return $items;
		}
		foreach ( $items as $key => $item ) {
			$cart_item = isset( $item->object ) ? $item->object : null;
			if ( is_array( $cart_item ) && ! empty( $cart_item['wffn_bundles_bundle'] ) ) {
				unset( $items[ $key ] );
			}
		}
		return $items;
	}

	/**
	 * Zero bundle lines out of an ADP cart discount's per-item amounts.
	 *
	 * ADP serves cart-level discounts as a custom coupon type and fills the per-item
	 * array by reflection into `apply_coupon_percent()`, bypassing
	 * `woocommerce_coupon_get_items_to_apply`. Amounts are integer cents.
	 *
	 * @param array $discounts Discount per cart item key.
	 * @param mixed $coupon    Coupon being applied.
	 * @return array
	 */
	public function scope_custom_discounts( $discounts, $coupon = null ) {
		if ( ! is_array( $discounts ) || ! $coupon instanceof \WC_Coupon ) {
			return $discounts;
		}
		if ( ! $this->is_adp_coupon( $coupon ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $discounts;
		}
		foreach ( array_keys( $discounts ) as $key ) {
			$cart_item = WC()->cart->get_cart_item( $key );
			if ( is_array( $cart_item ) && ! empty( $cart_item['wffn_bundles_bundle'] ) ) {
				$discounts[ $key ] = 0;
			}
		}
		return $discounts;
	}

	/**
	 * Is this virtual coupon one ADP put in the cart?
	 *
	 * A cart adjustment with no configured `couponCode` is applied under a
	 * per-request UUID, so the configured set alone misses it.
	 *
	 * @param \WC_Coupon $coupon Coupon being applied.
	 * @return bool
	 */
	private function is_adp_coupon( \WC_Coupon $coupon ) {
		$code = strtolower( (string) $coupon->get_code() );
		if ( in_array( $code, $this->live_coupon_codes(), true ) ) {
			return true;
		}
		return in_array( $code, $this->coupon_codes(), true );
	}

	/**
	 * The virtual coupons ADP is serving on this request, lowercased.
	 *
	 * `CartCouponsProcessor::$readyCouponData` is protected with no getter, so it is
	 * read by reflection off the instance ADP put on
	 * `woocommerce_get_shop_coupon_data`. Read, never called: `getCouponData()`
	 * mutates the coupon it is passed.
	 *
	 * @return string[]
	 */
	private function live_coupon_codes() {
		global $wp_filter;
		if ( empty( $wp_filter['woocommerce_get_shop_coupon_data'] ) ) {
			return array();
		}
		$codes = array();
		foreach ( $wp_filter['woocommerce_get_shop_coupon_data'] as $callbacks ) {
			foreach ( (array) $callbacks as $callback ) {
				$fn = isset( $callback['function'] ) ? $callback['function'] : null;
				if ( ! is_array( $fn ) || ! isset( $fn[0] ) || ! is_object( $fn[0] ) ) {
					continue;
				}
				if ( 0 !== strpos( get_class( $fn[0] ), 'ADP\\' ) ) {
					continue;
				}
				try {
					$property = new \ReflectionProperty( $fn[0], 'readyCouponData' );
					$property->setAccessible( true );
					foreach ( array_keys( (array) $property->getValue( $fn[0] ) ) as $code ) {
						$codes[] = strtolower( (string) $code );
					}
				} catch ( \ReflectionException $e ) {
					continue;
				}
			}
		}
		return $codes;
	}

	/**
	 * Coupon codes ADP's configuration could generate, lowercased.
	 *
	 * Both a rule's `couponCode` and `default_discount_name` are merchant-
	 * configurable, so there is no fixed prefix to match on.
	 *
	 * @return string[]
	 */
	private function coupon_codes() {
		if ( null !== $this->coupon_codes ) {
			return $this->coupon_codes;
		}
		$codes   = array();
		$options = get_option( 'wdp_settings', array() );
		if ( is_array( $options ) && ! empty( $options['default_discount_name'] ) ) {
			$codes[] = strtolower( (string) $options['default_discount_name'] );
		}
		foreach ( $this->rules() as $rule ) {
			if ( ! is_object( $rule ) || ! method_exists( $rule, 'getCartAdjustments' ) ) {
				continue;
			}
			foreach ( (array) $rule->getCartAdjustments() as $adjustment ) {
				if ( ! is_object( $adjustment ) || ! method_exists( $adjustment, 'getCouponCode' ) ) {
					continue;
				}
				$code = (string) $adjustment->getCouponCode();
				if ( '' !== $code ) {
					$codes[] = strtolower( $code );
				}
			}
		}
		$this->coupon_codes = array_values( array_unique( $codes ) );
		return $this->coupon_codes;
	}

	/**
	 * ADP's enabled rules, read once per request.
	 *
	 * @return array
	 */
	private function rules() {
		$repository = '\ADP\BaseVersion\Includes\Database\Repository\RuleRepository';
		if ( ! class_exists( $repository ) ) {
			return array();
		}
		try {
			$repo = new $repository();
			return (array) $repo->getRules( array( 'active_only' => true ) );
		} catch ( \Exception $e ) {
			Layer::log( 'RuleRepository::getRules() failed: ' . $e->getMessage() );
		} catch ( \Error $e ) {
			Layer::log( 'RuleRepository::getRules() failed: ' . $e->getMessage() );
		}
		return array();
	}

	/**
	 * Merchant warnings. Read-only: nothing here can change a price.
	 *
	 * Rendered even when the adapter is inactive; a silent version gate is what the
	 * notice exists to prevent.
	 *
	 * @return void
	 */
	public function render_notices() {
		if ( ! function_exists( 'adp_functions' ) || null === adp_functions() ) {
			return;
		}
		if ( ! self::version_supported() ) {
			$this->notice(
				sprintf(
					/* translators: 1: installed ADP version, 2: tested version range. */
					esc_html__( 'Bundles For WooCommerce has not been tested with Advanced Dynamic Pricing %1$s. Bundle pricing is left to Advanced Dynamic Pricing on this version, so a bundle may be charged a different price from the one the widget advertises. Tested range: %2$s.', 'funnel-builder' ),
					esc_html( defined( 'WC_ADP_VERSION' ) ? WC_ADP_VERSION : __( 'unknown', 'funnel-builder' ) ),
					esc_html( self::MIN_VERSION . ' to ' . self::MAX_VERSION )
				)
			);
			return;
		}
		$options = get_option( 'wdp_settings', array() );
		if ( is_array( $options ) && ! empty( $options['suppress_other_pricing_plugins'] ) ) {
			$this->notice(
				esc_html__( 'Advanced Dynamic Pricing is set to suppress other pricing plugins in the frontend. That setting removes Bundles For WooCommerce callbacks from the cart display hooks, so bundle prices may render incorrectly. Turn it off under Advanced Dynamic Pricing, Options.', 'funnel-builder' )
			);
		}
		if ( ! self::coupon_seam_available() ) {
			$this->notice(
				sprintf(
					/* translators: %s: required WooCommerce version. */
					esc_html__( 'WooCommerce %s or later is needed to keep Advanced Dynamic Pricing cart discounts off bundle lines. On this version a cart-level rule can discount a bundle line that already carries bundle pricing.', 'funnel-builder' ),
					esc_html( self::COUPON_SEAM_WC )
				)
			);
		}
		$this->render_overlap_notice();
	}

	/**
	 * Tell the merchant what happens where an ADP rule meets a bundle.
	 *
	 * Matched on explicit product-id filters only; category and tag filters are not
	 * resolved, so the notice under-reports.
	 *
	 * @return void
	 */
	private function render_overlap_notice() {
		foreach ( $this->rules() as $rule ) {
			if ( ! is_object( $rule ) || ! method_exists( $rule, 'getFilters' ) ) {
				continue;
			}
			foreach ( $this->filtered_product_ids( $rule ) as $product_id ) {
				$bundle = Bundle_Store::active_for_product( $product_id );
				if ( ! $bundle ) {
					continue;
				}
				$name       = (string) ( $bundle['name'] ?? '' );
				$cart_level = method_exists( $rule, 'getCartAdjustments' ) && ! empty( $rule->getCartAdjustments() );
				$this->notice(
					$cart_level
						? sprintf(
							/* translators: %s: bundle name. */
							esc_html__( 'This Advanced Dynamic Pricing rule also matches products in bundle %s. Cart discounts are not applied to bundle lines.', 'funnel-builder' ),
							esc_html( $name )
						)
						: sprintf(
							/* translators: %s: bundle name. */
							esc_html__( 'This Advanced Dynamic Pricing rule also matches products in bundle %s. Its discount is combined with the bundle pricing on those lines.', 'funnel-builder' ),
							esc_html( $name )
						)
				);
				return;
			}
		}
	}

	/**
	 * Product ids a rule names outright.
	 *
	 * @param object $rule ADP rule.
	 * @return int[]
	 */
	private function filtered_product_ids( $rule ) {
		$ids = array();
		foreach ( (array) $rule->getFilters() as $filter ) {
			if ( ! is_object( $filter ) || ! method_exists( $filter, 'getType' ) || ! method_exists( $filter, 'getValue' ) ) {
				continue;
			}
			if ( 'products' !== (string) $filter->getType() ) {
				continue;
			}
			foreach ( (array) $filter->getValue() as $value ) {
				$id = (int) $value;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Render one dismissible warning.
	 *
	 * @param string $message Escaped message text.
	 * @return void
	 */
	private function notice( $message ) {
		Layer::notice( $message );
	}
}
