<?php
/**
 * Wdr — Discount Rules for WooCommerce (WDR, Flycart) adapter.
 *
 * The only file in the plugin permitted to name WDR. WDR is pull-style: bundle
 * lines are excluded from its pricing pass, it is then asked through its own
 * filter what it would have charged, and `Cart::apply_pricing()` folds that in.
 *
 * Ordering is settled by exclusion, never by re-prioritising: WDR prices at
 * `woocommerce_before_calculate_totals`:1000 and we price at :20. Outside the
 * tested range (v2 free 2.6.x) the adapter reports itself inactive.
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
 * Discount Rules for WooCommerce adapter.
 */
class Wdr implements Engine {

	/** Lowest WDR version whose internals this adapter was written against. */
	const MIN_VERSION = '2.6.0';

	/** First WDR version this adapter does not claim; the gate is `< MAX_VERSION`. */
	const MAX_VERSION = '2.7.0';

	/** WDR's main settings option. */
	const OPTION_CONFIG = 'woo-discount-config-v2';

	/** WDR's switcher-compatibility option. */
	const OPTION_COMPAT = 'awdr_compatibility';

	/** The three bulk tallies a bundle line is kept out of; every condition path is left alone. */
	const BULK_COUNT_TYPES = array( 'individual_product_count', 'cumulative_count', 'product_variation_count' );

	/** @var string[]|null WDR-originated coupon codes, resolved once per request. */
	private $coupon_codes = null;

	/** @var bool Are we already inside a WDR price query? */
	private $deriving = false;

	/**
	 * Is WDR present, running v2 free, and within the tested version range?
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( ! self::is_present() ) {
			return false;
		}
		return self::version_supported();
	}

	/**
	 * Is WDR v2 free loaded, with its runtime instance installed?
	 *
	 * Probes `Router::$manage_discount` rather than a class symbol: the v2 classes
	 * are autoloaded and would answer true on requests where no engine booted.
	 *
	 * @return bool
	 */
	private static function is_present() {
		if ( ! defined( 'WDR_CORE' ) || class_exists( '\WDR\Pro\Route' ) ) {
			return false;
		}
		if ( ! class_exists( '\Wdr\App\Router' ) ) {
			return false;
		}
		return ! empty( \Wdr\App\Router::$manage_discount );
	}

	/**
	 * Is the installed WDR one this adapter was written against?
	 *
	 * @return bool
	 */
	private static function version_supported() {
		if ( ! defined( 'WDR_VERSION' ) ) {
			return false;
		}
		return version_compare( WDR_VERSION, self::MIN_VERSION, '>=' )
			&& version_compare( WDR_VERSION, self::MAX_VERSION, '<' );
	}

	/**
	 * Keep our callbacks off WDR's suppression list, active adapter or not.
	 *
	 * Registered on presence, not activity: WDR's `wp_loaded` pass deletes foreign
	 * callbacks whether or not this adapter is pricing the request.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_defensive_hooks() {
		if ( ! defined( 'WDR_CORE' ) ) {
			return;
		}
		// Priority 5: early, so a later registrant cannot undo it.
		add_filter( 'advanced_woo_discount_rules_exclude_hooks_from_removing', array( $this, 'allow_our_hooks' ), 5 );
	}

	/**
	 * Register WDR's own seams.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'advanced_woo_discount_rules_calculate_discount_for_cart_item', array( $this, 'exclude_bundle_lines' ), 10, 2 );
		add_filter( 'advanced_woo_discount_rules_include_cart_item_to_count_quantity', array( $this, 'exclude_from_counts' ), 10, 3 );
		add_filter( 'advanced_woo_discount_rules_do_apply_price_discount', array( $this, 'veto_price_write' ), 10, 5 );
		if ( self::coupon_seam_available() ) {
			add_filter( 'woocommerce_coupon_get_items_to_apply', array( $this, 'scope_coupon_items' ), 10, 2 );
		}
	}

	/**
	 * Is the cart-level coupon seam available on this WooCommerce?
	 *
	 * @return bool
	 */
	private static function coupon_seam_available() {
		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, Adp::COUPON_SEAM_WC, '>=' );
	}

	/**
	 * Keep WDR's pricing pass off bundle lines.
	 *
	 * @param bool  $calculate Should WDR price this line.
	 * @param array $cart_item Cart item WDR is about to price.
	 * @return bool
	 */
	public function exclude_bundle_lines( $calculate, $cart_item = array() ) {
		return ( is_array( $cart_item ) && ! empty( $cart_item['wffn_bundles_bundle'] ) ) ? false : $calculate;
	}

	/**
	 * Second veto, immediately before WDR's `set_price()`.
	 *
	 * Covers any path reaching the write without passing the pricing exclusion.
	 *
	 * @param bool  $apply     Should WDR write this price.
	 * @param mixed $price     Price WDR computed.
	 * @param array $cart_item Cart item being written.
	 * @param mixed $cart      WDR's cart handle.
	 * @param array $prices    WDR's per-line price set.
	 * @return bool
	 */
	public function veto_price_write( $apply, $price = null, $cart_item = array(), $cart = null, $prices = array() ) {
		return ( is_array( $cart_item ) && ! empty( $cart_item['wffn_bundles_bundle'] ) ) ? false : $apply;
	}

	/**
	 * Keep bundle lines out of WDR's three bulk quantity tallies.
	 *
	 * Condition paths are left alone, including `qty_based_on_filters`, which is a
	 * cart condition despite its name: excluding it would move a non-bundle price.
	 *
	 * @param bool   $counts    Should this line feed the tally.
	 * @param array  $cart_item Cart item being counted.
	 * @param string $type      Which tally WDR is building.
	 * @return bool
	 */
	public function exclude_from_counts( $counts, $cart_item = array(), $type = '' ) {
		if ( ! is_array( $cart_item ) || empty( $cart_item['wffn_bundles_bundle'] ) ) {
			return $counts;
		}
		return in_array( (string) $type, self::BULK_COUNT_TYPES, true ) ? false : $counts;
	}

	/**
	 * Append our callbacks to WDR's suppression allowlist, keeping WDR's own.
	 *
	 * `isCallbackMatch()` compares `get_class()` against the string before the pipe,
	 * so these must stay fully qualified and the callbacks must stay non-static.
	 *
	 * @param array $allowed Hook-keyed allowlist.
	 * @return array
	 */
	public function allow_our_hooks( $allowed ) {
		if ( ! is_array( $allowed ) ) {
			return $allowed;
		}
		$cart = 'WFFN\Bundles\Includes\Frontend\Cart';
		$ours = array(
			'woocommerce_before_calculate_totals' => array( $cart . '|lock_quantities', $cart . '|apply_pricing' ),
			'woocommerce_cart_item_price'         => array( $cart . '|item_price' ),
			'woocommerce_cart_item_subtotal'      => array( $cart . '|item_price' ),
		);
		foreach ( $ours as $hook => $entries ) {
			$existing         = isset( $allowed[ $hook ] ) ? (array) $allowed[ $hook ] : array();
			$allowed[ $hook ] = array_values( array_unique( array_merge( $existing, $entries ) ) );
		}
		return $allowed;
	}

	/**
	 * No-op: exclusion is a filter here, not a stamp on the line.
	 *
	 * @param array $cart_item Cart item, left untouched.
	 * @return void
	 */
	public function claim_line( array &$cart_item ) {
	}

	/**
	 * WDR never writes our price for us, so the cart must fold its answer.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function supplies_own_price() {
		return false;
	}

	/**
	 * What WDR would have discounted this line by, as a percentage of our base unit.
	 *
	 * The base is passed as WDR's `$custom_price`, so the answer cannot depend on
	 * the price our own pass wrote, and comes back in that same unit.
	 *
	 * @param array $cart_item Cart item to interrogate.
	 * @return float 0.0 when WDR would apply nothing, or when the answer is unusable.
	 */
	private function derive_percentage( array $cart_item ) {
		if ( $this->deriving || ! $this->is_active() ) {
			return 0.0;
		}
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}
		$base = Cart::stored_unit( $cart_item );
		// Gift lines and Fixed Bundle members price at 0; dividing is a PHP 8 fatal.
		if ( $base <= 0 ) {
			return 0.0;
		}
		if ( $this->non_percentage_rule_matches( $product ) ) {
			return 0.0;
		}
		$qty = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );

		$this->deriving = true;
		try {
			$answer = $this->query_isolated(
				function () use ( $base, $product, $qty ) {
					return apply_filters(
						'advanced_woo_discount_rules_get_product_discount_price_from_custom_price',
						$base,
						$product,
						$qty,
						$base,
						'discounted_price',
						true,
						false
					);
				}
			);
		} finally {
			$this->deriving = false;
		}

		if ( ! is_int( $answer ) && ! is_float( $answer ) ) {
			return 0.0;
		}
		$percent = ( 1 - ( Layer::to_float( $answer ) / $base ) ) * 100;
		if ( $percent <= 0 ) {
			return 0.0;
		}
		return min( 100.0, $percent );
	}

	/**
	 * Run `$query` with WDR's request-scoped statics snapshotted and restored.
	 *
	 * The query writes four public statics on `DiscountCalculator` and would leak
	 * its exclusive-rule resolution into WDR's own later pass. The recalculation
	 * veto shares the window: a product rule with a cart condition would re-enter
	 * `WC_Cart::calculate_totals()` from inside our pass.
	 *
	 * @param callable $query Delegated query to run.
	 * @return mixed Whatever `$query` returns.
	 */
	private function query_isolated( callable $query ) {
		$calculator = '\Wdr\App\Controllers\DiscountCalculator';
		if ( ! class_exists( $calculator ) ) {
			return $query();
		}
		$saved = array(
			'applied_rules'             => $calculator::$applied_rules,
			'filtered_exclusive_rule'   => $calculator::$filtered_exclusive_rule,
			'original_price_of_product' => $calculator::$original_price_of_product,
			'total_discounts'           => $calculator::$total_discounts,
		);
		// Priority 99: below WDR's own listeners, and removed again in the finally.
		add_filter( 'advanced_woo_discount_rules_do_recalculate_total', '__return_false', 99 );
		try {
			return $query();
		} catch ( \Exception $e ) {
			Layer::log( 'WDR delegated query failed: ' . $e->getMessage() );
			return null;
		} catch ( \Error $e ) {
			Layer::log( 'WDR delegated query failed: ' . $e->getMessage() );
			return null;
		} finally {
			remove_filter( 'advanced_woo_discount_rules_do_recalculate_total', '__return_false', 99 );
			$calculator::$applied_rules             = $saved['applied_rules'];
			$calculator::$filtered_exclusive_rule   = $saved['filtered_exclusive_rule'];
			$calculator::$original_price_of_product = $saved['original_price_of_product'];
			$calculator::$total_discounts           = $saved['total_discounts'];
		}
	}

	/**
	 * Would a matching rule hand us an amount WDR has already converted?
	 *
	 * WDR converts `flat` and `fixed_price` amounts before we see them, so they do
	 * not match a base-currency `$custom_price`. Declined wholesale rather than per
	 * rule, because WDR returns one blended figure and never names the rule.
	 *
	 * @param \WC_Product $product Product the line carries.
	 * @return bool
	 */
	private function non_percentage_rule_matches( \WC_Product $product ) {
		if ( ! self::converting_compat_active() ) {
			return false;
		}
		foreach ( $this->rules() as $rule ) {
			if ( ! $this->rule_can_fire( $rule ) ) {
				continue;
			}
			try {
				if ( ! $rule->isFilterPassed( $product ) ) {
					continue;
				}
			} catch ( \Exception $e ) {
				continue;
			} catch ( \Error $e ) {
				continue;
			}
			if ( self::has_non_percentage_adjustment( $rule ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does any adjustment on this rule carry a type other than `percentage`?
	 *
	 * Both walks matter: the delegated query passes a quantity, so a flat bulk range
	 * in `getBulkAdjustments()` reaches the answer too.
	 *
	 * @param object $rule WDR rule helper.
	 * @return bool
	 */
	private static function has_non_percentage_adjustment( $rule ) {
		$sets = array();
		if ( method_exists( $rule, 'getProductAdjustments' ) ) {
			$sets[] = $rule->getProductAdjustments();
		}
		if ( method_exists( $rule, 'getBulkAdjustments' ) ) {
			$sets[] = $rule->getBulkAdjustments();
		}
		foreach ( $sets as $set ) {
			if ( empty( $set ) ) {
				continue;
			}
			foreach ( ( is_array( $set ) ? $set : array( $set ) ) as $adjustment ) {
				if ( ! is_object( $adjustment ) || ! isset( $adjustment->type ) ) {
					continue;
				}
				if ( 'percentage' !== (string) $adjustment->type ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Is a WDR switcher-compat class converting rule amounts on this request?
	 *
	 * Aelia's converts base to the active currency, which is identity at base, so
	 * that key additionally requires a non-base currency. The other three convert
	 * unconditionally inside their own callbacks, so the key alone is decisive.
	 *
	 * @return bool
	 */
	private static function converting_compat_active() {
		$compat = get_option( self::OPTION_COMPAT, array() );
		if ( ! is_array( $compat ) ) {
			return false;
		}
		foreach ( array( 'compatible_multicurrency_by_wpml', 'compatible_multicurrency_tivnet', 'compatible_wcpbc_oscargare' ) as $key ) {
			if ( ! empty( $compat[ $key ] ) ) {
				return true;
			}
		}
		if ( empty( $compat['compatible_cs_aelia'] ) ) {
			return false;
		}
		return get_woocommerce_currency() !== get_option( 'woocommerce_currency' );
	}

	/**
	 * Can this rule fire at all right now?
	 *
	 * WDR's rule query returns disabled and expired rules too, so every caller has
	 * to apply the window itself.
	 *
	 * @param object $rule WDR rule helper.
	 * @return bool
	 */
	private function rule_can_fire( $rule ) {
		if ( ! is_object( $rule ) || ! method_exists( $rule, 'isFilterPassed' ) || ! method_exists( $rule, 'isEnabled' ) ) {
			return false;
		}
		if ( ! $rule->isEnabled() ) {
			return false;
		}
		if ( method_exists( $rule, 'checkRuleUsageLimits' ) && ! $rule->checkRuleUsageLimits() ) {
			return false;
		}
		$now   = time();
		$start = method_exists( $rule, 'getStartDate' ) ? $rule->getStartDate( true ) : null;
		$end   = method_exists( $rule, 'getEndDate' ) ? $rule->getEndDate( true ) : null;
		if ( ! empty( $start ) && $now < (int) $start ) {
			return false;
		}
		if ( ! empty( $end ) && $now > (int) $end ) {
			return false;
		}
		return true;
	}

	/**
	 * WDR's rules, wrapped in its own helper objects.
	 *
	 * `Models\DBTable::getRules()` is not a valid source: it returns plain stdClass
	 * rows, so every helper method called on one would fatal.
	 *
	 * @return array
	 */
	private function rules() {
		if ( ! self::is_present() ) {
			return array();
		}
		try {
			$manage = \Wdr\App\Router::$manage_discount;
			if ( ! empty( $manage::$available_rules ) ) {
				return (array) $manage::$available_rules;
			}
			if ( method_exists( $manage, 'getDiscountRules' ) ) {
				return (array) $manage->getDiscountRules();
			}
		} catch ( \Exception $e ) {
			Layer::log( 'getDiscountRules() failed: ' . $e->getMessage() );
		} catch ( \Error $e ) {
			Layer::log( 'getDiscountRules() failed: ' . $e->getMessage() );
		}
		return array();
	}

	/**
	 * What WDR would have applied to this line, had it not been excluded.
	 *
	 * Always a percentage, even for a flat WDR rule: a percentage is unitless and
	 * therefore safe to fold before the currency layer converts.
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
				'source' => 'wdr',
				'amount' => $percent,
				'type'   => 'percentage',
			),
		);
	}

	/**
	 * The line's unit price before WDR's discounts, in base currency.
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
	 * WDR has no API for recording a foreign plugin's discount.
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
	 * Bundle pricing computes first; WDR's product rules fold into it.
	 *
	 * @return int
	 */
	public function get_application_order() {
		return 0;
	}

	/**
	 * Run `$render` with WDR's price-display filters suspended.
	 *
	 * Three of the five are registered only when their sale-badge option is on, so
	 * each is removed on `has_filter()` and restored at the priority it was found at.
	 *
	 * @param callable $render Renderer to run inside the window.
	 * @return mixed
	 */
	public function with_display_suppressed( callable $render ) {
		if ( ! self::is_present() ) {
			return $render();
		}
		$manage  = \Wdr\App\Router::$manage_discount;
		$targets = array(
			array( 'woocommerce_get_price_html', 'getPriceHtml', 100 ),
			array( 'woocommerce_get_price_html', 'getPriceHtmlSalePriceAdjustment', 9 ),
			array( 'woocommerce_variable_price_html', 'getVariablePriceHtml', 100 ),
			array( 'woocommerce_product_is_on_sale', 'isProductInSale', 100 ),
			array( 'woocommerce_sale_flash', 'replaceSaleTagText', 100 ),
		);
		$removed = array();
		foreach ( $targets as $target ) {
			$callback = array( $manage, $target[1] );
			if ( false !== has_filter( $target[0], $callback ) ) {
				remove_filter( $target[0], $callback, $target[2] );
				$removed[] = $target;
			}
		}
		try {
			return $render();
		} finally {
			foreach ( $removed as $target ) {
				add_filter( $target[0], array( $manage, $target[1] ), $target[2], 10 );
			}
		}
	}

	/**
	 * Drop bundle lines from a WDR-originated coupon's item list.
	 *
	 * Identification fails open: a virtual coupon not positively attributable to WDR
	 * is left alone rather than silently scoped away.
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
		if ( ! $this->is_wdr_coupon( $coupon ) ) {
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
	 * Is this virtual coupon one WDR put in the cart?
	 *
	 * @param \WC_Coupon $coupon Coupon being applied.
	 * @return bool
	 */
	private function is_wdr_coupon( \WC_Coupon $coupon ) {
		return in_array( strtolower( (string) $coupon->get_code() ), $this->wdr_coupon_codes(), true );
	}

	/**
	 * The virtual coupon codes WDR is serving on this request, lowercased.
	 *
	 * `$apply_as_coupon_values` keys are the label after `woocommerce_coupon_code`
	 * filtered it, and are written during the request they are read in, so never
	 * memoised.
	 *
	 * @return string[]
	 */
	private function wdr_coupon_codes() {
		$manage = '\Wdr\App\Controllers\ManageDiscount';
		if ( ! class_exists( $manage ) ) {
			return array();
		}
		$codes = array();
		foreach ( array_keys( (array) $manage::$apply_as_coupon_values ) as $code ) {
			$codes[] = strtolower( (string) $code );
		}
		return $codes;
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
		if ( ! defined( 'WDR_CORE' ) && ! class_exists( '\WDR\Pro\Route' ) ) {
			return;
		}
		if ( ! $this->is_active() ) {
			$this->notice(
				sprintf(
					/* translators: 1: installed WDR version, 2: tested version range. */
					esc_html__( 'Bundles For WooCommerce has not been tested with Discount Rules %1$s. Bundle pricing is left to Discount Rules on this version, so a bundle may be charged a different price from the one the widget advertises. Tested range: %2$s, free version only.', 'funnel-builder' ),
					esc_html( defined( 'WDR_VERSION' ) ? WDR_VERSION : __( 'unknown', 'funnel-builder' ) ),
					esc_html( self::MIN_VERSION . ' to ' . self::MAX_VERSION )
				)
			);
			return;
		}
		if ( Layer::is_active() && ! Layer::active_adapter() instanceof self ) {
			$this->notice(
				esc_html__( 'Advanced Dynamic Pricing and Discount Rules are both active. Bundle pricing follows the Advanced Dynamic Pricing integration, and Discount Rules applies its own rules to bundle lines unmanaged. Keep one pricing plugin active.', 'funnel-builder' )
			);
		}
		$config = get_option( self::OPTION_CONFIG, array() );
		if ( is_array( $config ) && ! empty( $config['suppress_other_discount_plugins'] ) ) {
			$this->notice(
				esc_html__( 'Discount Rules is set to suppress other discount plugins. Bundles For WooCommerce callbacks are re-added automatically, but a currency switcher\'s are not and cannot be. Turn the setting off under Discount Rules, Settings.', 'funnel-builder' )
			);
		}
		if ( ! self::coupon_seam_available() ) {
			$this->notice(
				sprintf(
					/* translators: %s: required WooCommerce version. */
					esc_html__( 'WooCommerce %s or later is needed to keep Discount Rules cart discounts off bundle lines. On this version a cart-level rule can discount a bundle line that already carries bundle pricing.', 'funnel-builder' ),
					esc_html( Adp::COUPON_SEAM_WC )
				)
			);
		}
		$this->render_counting_notice();
		$this->render_overlap_notices();
	}

	/**
	 * The three effects of the count exclusion that cannot be corrected from here.
	 *
	 * @return void
	 */
	private function render_counting_notice() {
		$this->notice(
			esc_html__( 'Bundle lines no longer feed Discount Rules bulk quantity tiers, so a bulk rule may award a standalone line of the same product less than before. A bundle line\'s money does still count toward spend thresholds, and where the same product sits both standalone and inside a bundle, a cart rule\'s percentage is diluted by an amount that moves with the bundle\'s quantity.', 'funnel-builder' )
		);
	}

	/**
	 * Tell the merchant what happens where a WDR rule meets a bundle.
	 *
	 * @return void
	 */
	private function render_overlap_notices() {
		$overlap = false;
		$flat    = false;
		$member  = false;
		foreach ( $this->rules() as $rule ) {
			if ( ! $this->rule_can_fire( $rule ) ) {
				continue;
			}
			foreach ( $this->filtered_product_ids( $rule ) as $product_id ) {
				$bundle = Bundle_Store::active_for_product( $product_id );
				if ( ! $bundle ) {
					continue;
				}
				if ( ! $overlap ) {
					$overlap = true;
					$this->render_overlap_notice( $rule, (string) ( $bundle['name'] ?? '' ) );
				}
				if ( ! $flat && self::has_non_percentage_adjustment( $rule ) && self::converting_compat_active() ) {
					$flat = true;
					$this->notice(
						esc_html__( 'A Discount Rules rule uses a flat or fixed amount while a currency-compatibility option is on. Discount Rules has already converted that amount, so the rule is not combined into bundle pricing. Any percentage rule matching the same product is dropped with it, because Discount Rules returns one figure and does not say which rule produced it.', 'funnel-builder' )
					);
				}
				if ( ! $member && Bundle_Store::TYPE_FIXED_BUNDLES === (int) ( $bundle['type'] ?? 0 ) ) {
					$member = true;
					$this->notice(
						esc_html__( 'A Discount Rules rule names a product that is a member of a Fixed Bundle. That rule will not fire on the bundled line, because the pack price sits on the bundle rather than on the member. An all-products rule still applies, on the pack. Other bundle lines, add-ons and single-product lines are not affected.', 'funnel-builder' )
					);
				}
			}
		}
	}

	/**
	 * One overlap warning, worded by whether the rule is cart-level or product-level.
	 *
	 * @param object $rule WDR rule helper.
	 * @param string $name Bundle name.
	 * @return void
	 */
	private function render_overlap_notice( $rule, $name ) {
		$cart_level = method_exists( $rule, 'hasCartDiscount' ) && $rule->hasCartDiscount();
		$this->notice(
			$cart_level
				? sprintf(
					/* translators: %s: bundle name. */
					esc_html__( 'This Discount Rules rule also matches products in bundle %s. Cart discounts are not applied to bundle lines.', 'funnel-builder' ),
					esc_html( $name )
				)
				: sprintf(
					/* translators: %s: bundle name. */
					esc_html__( 'This Discount Rules rule also matches products in bundle %s. Its discount is combined with the bundle pricing on those lines.', 'funnel-builder' ),
					esc_html( $name )
				)
		);
	}

	/**
	 * Product ids a rule names outright.
	 *
	 * A `not_in_list` filter matches everything except the listed ids, so its values
	 * are skipped. Category and tag filters are not resolved.
	 *
	 * @param object $rule WDR rule helper.
	 * @return int[]
	 */
	private function filtered_product_ids( $rule ) {
		if ( ! method_exists( $rule, 'getFilter' ) ) {
			return array();
		}
		$ids = array();
		foreach ( (array) $rule->getFilter() as $filter ) {
			if ( ! is_object( $filter ) || ! isset( $filter->type ) || 'products' !== (string) $filter->type ) {
				continue;
			}
			if ( isset( $filter->method ) && 'not_in_list' === (string) $filter->method ) {
				continue;
			}
			$values = array_merge(
				isset( $filter->value ) ? (array) $filter->value : array(),
				isset( $filter->product_variants ) ? (array) $filter->product_variants : array()
			);
			foreach ( $values as $value ) {
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
