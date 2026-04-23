<?php
/**
 * Cart_Evaluator
 *
 * Evaluates cart-based conditions.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Engine
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Engine;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Condition;
use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart_Evaluator class.
 *
 * @since 2.0.0
 */
class Cart_Evaluator {

	/**
	 * Evaluate a cart-based condition.
	 *
	 * @since 2.0.0
	 * @param Condition $condition Condition to evaluate.
	 * @param array     $cart_info Cart information.
	 * @return bool True if condition is met, false otherwise.
	 */
	public static function evaluate( $condition, $cart_info = null ) {
		if ( ! $condition instanceof Condition || 'cart' !== $condition->get_type() ) {
			return false;
		}

		// Get cart info if not provided.
		if ( null === $cart_info ) {
			$cart_info = self::get_cart_info();
		}

		$operator     = $condition->get_operator();
		$operand_type = $condition->get_operand_type();
		$operand      = $condition->get_operand();
		$value        = $condition->get_value();

		// Evaluate based on operator.
		switch ( $operator ) {
			case 'cart_contains':
				return self::evaluate_cart_contains( $operand_type, $operand, $cart_info );

			case 'cart_not_contains':
				return ! self::evaluate_cart_contains( $operand_type, $operand, $cart_info );

			case 'cart_only_contains':
				return self::evaluate_cart_only_contains( $operand_type, $operand, $cart_info );

			case 'cart_subtotal_eq':
				return self::evaluate_cart_subtotal( '==', $value, $cart_info );

			case 'cart_subtotal_ne':
				return self::evaluate_cart_subtotal( '!=', $value, $cart_info );

			case 'cart_subtotal_gt':
				return self::evaluate_cart_subtotal( '>', $value, $cart_info );

			case 'cart_subtotal_lt':
				return self::evaluate_cart_subtotal( '<', $value, $cart_info );

			case 'cart_subtotal_gte':
				return self::evaluate_cart_subtotal( '>=', $value, $cart_info );

			case 'cart_subtotal_lte':
				return self::evaluate_cart_subtotal( '<=', $value, $cart_info );

			case 'cart_total_eq':
				return self::evaluate_cart_total( '==', $value, $cart_info );

			case 'cart_total_ne':
				return self::evaluate_cart_total( '!=', $value, $cart_info );

			case 'cart_total_gt':
				return self::evaluate_cart_total( '>', $value, $cart_info );

			case 'cart_total_lt':
				return self::evaluate_cart_total( '<', $value, $cart_info );

			case 'cart_total_gte':
				return self::evaluate_cart_total( '>=', $value, $cart_info );

			case 'cart_total_lte':
				return self::evaluate_cart_total( '<=', $value, $cart_info );

			case 'shipping_weight_eq':
				return self::evaluate_shipping_weight( '==', $value, $cart_info );

			case 'shipping_weight_gt':
				return self::evaluate_shipping_weight( '>', $value, $cart_info );

			case 'shipping_weight_lt':
				return self::evaluate_shipping_weight( '<', $value, $cart_info );

			case 'cart_is_virtual_eq':
				return self::evaluate_cart_is_virtual( '==', $value, $cart_info );

			case 'cart_is_virtual_ne':
				return self::evaluate_cart_is_virtual( '!=', $value, $cart_info );

			case 'cart_item_count_eq':
				return self::evaluate_cart_item_count( '==', $value, $cart_info );

			case 'cart_item_count_ne':
				return self::evaluate_cart_item_count( '!=', $value, $cart_info );

			case 'cart_item_count_gt':
				return self::evaluate_cart_item_count( '>', $value, $cart_info );

			case 'cart_item_count_lt':
				return self::evaluate_cart_item_count( '<', $value, $cart_info );

			case 'cart_item_count_gte':
				return self::evaluate_cart_item_count( '>=', $value, $cart_info );

			case 'cart_item_count_lte':
				return self::evaluate_cart_item_count( '<=', $value, $cart_info );

			case 'cart_coupon_contains':
				return self::evaluate_cart_coupon_contains( $operand, $cart_info );

			case 'cart_coupon_not_contains':
				return ! self::evaluate_cart_coupon_contains( $operand, $cart_info );

			default:
				return false;
		}
	}

	/**
	 * Get cart information.
	 *
	 * @since 2.0.0
	 * @return array Cart information.
	 */
	public static function get_cart_info() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}

		$cart = WC()->cart;

		// Ensure cart totals are calculated before reading values.
		// This fixes issues where cart_total conditions fail because
		// totals haven't been computed yet on initial page load.
		if ( ! did_action( 'woocommerce_cart_totals_computed' ) ) {
			$cart->calculate_totals();
		}

		// Generate cart hash for caching.
		$cart_hash = $cart->get_cart_hash();

		// Try to get from cache.
		$cart_info = Cache_Manager::get_cart_data( $cart_hash );

		if ( false !== $cart_info ) {
			return $cart_info;
		}

		// Build cart info.
		$products         = array();
		$categories       = array();
		$tags             = array();
		$shipping_classes = array();
		$product_types    = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];

			if ( ! $product ) {
				continue;
			}

			// Product IDs - include both variation ID and parent ID for variations.
			$product_id = $product->get_id();
			$products[] = $product_id;

			// For variations, also add the parent product ID so rules matching parent work.
			$parent_product = null;
			if ( $product instanceof \WC_Product_Variation ) {
				$parent_id = $product->get_parent_id();
				if ( $parent_id ) {
					$products[]     = $parent_id;
					$parent_product = wc_get_product( $parent_id );
				}
			}

			// Categories - for variations, get from parent product as variations don't have categories.
			$product_categories = $product->get_category_ids();
			if ( empty( $product_categories ) && $parent_product ) {
				$product_categories = $parent_product->get_category_ids();
			}
			if ( is_array( $product_categories ) ) {
				$categories = array_merge( $categories, $product_categories );
			}

			// Tags - for variations, get from parent product as variations don't have tags.
			$product_tags = $product->get_tag_ids();
			if ( empty( $product_tags ) && $parent_product ) {
				$product_tags = $parent_product->get_tag_ids();
			}
			if ( is_array( $product_tags ) ) {
				$tags = array_merge( $tags, $product_tags );
			}

			// Shipping class - for variations, get from parent if not set.
			$shipping_class_id = $product->get_shipping_class_id();
			if ( ! $shipping_class_id && $parent_product ) {
				$shipping_class_id = $parent_product->get_shipping_class_id();
			}
			if ( $shipping_class_id ) {
				$shipping_classes[] = $shipping_class_id;
			}

			// Product type.
			$product_types[] = $product->get_type();
		}

		$cart_is_virtual = false;
		if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'is_cart_is_virtual' ) ) {
			$cart_is_virtual = \WFACP_Common::is_cart_is_virtual();
		}

		// Get applied coupons (lowercase for case-insensitive comparison).
		$applied_coupons = array_map( 'strtolower', $cart->get_applied_coupons() );

		$subtotal = (float) $cart->get_subtotal();
		$total    = (float) $cart->get_total( 'edit' );
		// Convert to display currency when multi-currency plugins are active (user enters rule value in displayed currency).
		if ( class_exists( 'BWF_Plugin_Compatibilities' ) && is_callable( array( 'BWF_Plugin_Compatibilities', 'get_fixed_currency_price' ) ) ) {
			$subtotal = (float) \BWF_Plugin_Compatibilities::get_fixed_currency_price( $subtotal );
			$total    = (float) \BWF_Plugin_Compatibilities::get_fixed_currency_price( $total );
		}

		$cart_info = array(
			'products'         => array_unique( $products ),
			'categories'       => array_unique( $categories ),
			'tags'             => array_unique( $tags ),
			'shipping_classes' => array_unique( $shipping_classes ),
			'product_types'    => array_unique( $product_types ),
			'subtotal'         => $subtotal,
			'total'            => $total,
			'shipping_weight'  => $cart->get_cart_contents_weight(),
			'is_virtual'       => $cart_is_virtual,
			'item_count'       => $cart->get_cart_contents_count(), // Total quantity of all items.
			'line_count'       => count( $cart->get_cart() ),       // Number of unique line items.
			'applied_coupons'  => $applied_coupons,
		);

		// Cache cart info.
		Cache_Manager::set_cart_data( $cart_hash, $cart_info );

		return $cart_info;
	}

	/**
	 * Evaluate cart contains condition.
	 *
	 * @since 2.0.0
	 * @param string $operand_type Operand type (product, category, tag, etc.).
	 * @param mixed  $operand Operand value.
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_cart_contains( $operand_type, $operand, $cart_info ) {
		if ( ! is_array( $operand ) ) {
			$operand = array( $operand );
		}

		$cart_items = array();

		switch ( $operand_type ) {
			case 'product':
				$cart_items = isset( $cart_info['products'] ) ? $cart_info['products'] : array();
				break;

			case 'category':
				$cart_items = isset( $cart_info['categories'] ) ? $cart_info['categories'] : array();
				break;

			case 'tag':
				$cart_items = isset( $cart_info['tags'] ) ? $cart_info['tags'] : array();
				break;

			case 'shipping_class':
				$cart_items = isset( $cart_info['shipping_classes'] ) ? $cart_info['shipping_classes'] : array();
				break;

			case 'product_type':
				$cart_items = isset( $cart_info['product_types'] ) ? $cart_info['product_types'] : array();
				break;
		}

		// Ensure both arrays are indexed (not associative) for array_intersect.
		$operand    = array_values( $operand );
		$cart_items = array_values( $cart_items );

		// Check if cart contains any of the operand values.
		$intersection = array_intersect( $operand, $cart_items );

		return ! empty( $intersection );
	}

	/**
	 * Evaluate cart only contains condition.
	 *
	 * @since 2.0.0
	 * @param string $operand_type Operand type.
	 * @param mixed  $operand Operand value.
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_cart_only_contains( $operand_type, $operand, $cart_info ) {
		if ( ! is_array( $operand ) ) {
			$operand = array( $operand );
		}

		$cart_items = array();

		switch ( $operand_type ) {
			case 'product':
				$cart_items = isset( $cart_info['products'] ) ? $cart_info['products'] : array();
				break;

			case 'category':
				$cart_items = isset( $cart_info['categories'] ) ? $cart_info['categories'] : array();
				break;

			case 'tag':
				$cart_items = isset( $cart_info['tags'] ) ? $cart_info['tags'] : array();
				break;

			case 'shipping_class':
				$cart_items = isset( $cart_info['shipping_classes'] ) ? $cart_info['shipping_classes'] : array();
				break;

			case 'product_type':
				$cart_items = isset( $cart_info['product_types'] ) ? $cart_info['product_types'] : array();
				break;
		}

		// Ensure both arrays are indexed (not associative) for array_diff.
		$operand    = array_values( $operand );
		$cart_items = array_values( $cart_items );

		// Check if cart only contains items from operand.
		$diff = array_diff( $cart_items, $operand );

		return empty( $diff );
	}

	/**
	 * Evaluate cart subtotal condition.
	 *
	 * @since 2.0.0
	 * @param string $comparison Comparison operator (==, >, <).
	 * @param float  $value Value to compare.
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_cart_subtotal( $comparison, $value, $cart_info ) {
		$subtotal = isset( $cart_info['subtotal'] ) ? floatval( $cart_info['subtotal'] ) : 0;
		$value    = floatval( $value );

		switch ( $comparison ) {
			case '==':
				return abs( $subtotal - $value ) < 0.01;

			case '!=':
				return abs( $subtotal - $value ) >= 0.01;

			case '>':
				return $subtotal > $value;

			case '<':
				return $subtotal < $value;

			case '>=':
				return $subtotal >= $value;

			case '<=':
				return $subtotal <= $value;

			default:
				return false;
		}
	}

	/**
	 * Evaluate cart total condition.
	 *
	 * @since 2.0.0
	 * @param string $comparison Comparison operator.
	 * @param float  $value Value to compare.
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_cart_total( $comparison, $value, $cart_info ) {
		$total = isset( $cart_info['total'] ) ? floatval( $cart_info['total'] ) : 0;
		$value = floatval( $value );

		$result = false;
		switch ( $comparison ) {
			case '==':
				$result = abs( $total - $value ) < 0.01;
				break;

			case '!=':
				$result = abs( $total - $value ) >= 0.01;
				break;

			case '>':
				$result = $total > $value;
				break;

			case '<':
				$result = $total < $value;
				break;

			case '>=':
				$result = $total >= $value;
				break;

			case '<=':
				$result = $total <= $value;
				break;

			default:
				$result = false;
		}

		// Debug logging.
		self::debug_log( "Cart Total Evaluation: $total $comparison $value = " . ( $result ? 'TRUE' : 'FALSE' ) );

		return $result;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private static function debug_log( $message ) {
		// Debug logging disabled.
	}

	/**
	 * Evaluate cart is virtual condition.
	 *
	 * @since 2.0.0
	 * @param string $comparison Comparison operator (==, !=).
	 * @param string $value Value to compare ('yes' or 'no').
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_cart_is_virtual( $comparison, $value, $cart_info ) {
		$is_virtual = isset( $cart_info['is_virtual'] ) ? $cart_info['is_virtual'] : false;
		$expected   = ( 'yes' === $value );

		if ( '==' === $comparison ) {
			return $is_virtual === $expected;
		}

		if ( '!=' === $comparison ) {
			return $is_virtual !== $expected;
		}

		return false;
	}

	/**
	 * Evaluate shipping weight condition.
	 *
	 * @since 2.0.0
	 * @param string $comparison Comparison operator.
	 * @param float  $value Value to compare.
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_shipping_weight( $comparison, $value, $cart_info ) {
		$weight = isset( $cart_info['shipping_weight'] ) ? floatval( $cart_info['shipping_weight'] ) : 0;
		$value  = floatval( $value );

		switch ( $comparison ) {
			case '==':
				return abs( $weight - $value ) < 0.01;

			case '>':
				return $weight > $value;

			case '<':
				return $weight < $value;

			default:
				return false;
		}
	}

	/**
	 * Evaluate cart item count condition.
	 *
	 * Uses total quantity of all items in cart (not unique line items).
	 *
	 * @since 2.5.0
	 * @param string $comparison Comparison operator.
	 * @param int    $value Value to compare.
	 * @param array  $cart_info Cart information.
	 * @return bool
	 */
	private static function evaluate_cart_item_count( $comparison, $value, $cart_info ) {
		$count = isset( $cart_info['item_count'] ) ? intval( $cart_info['item_count'] ) : 0;
		$value = intval( $value );

		$result = false;
		switch ( $comparison ) {
			case '==':
				$result = $count === $value;
				break;

			case '!=':
				$result = $count !== $value;
				break;

			case '>':
				$result = $count > $value;
				break;

			case '<':
				$result = $count < $value;
				break;

			case '>=':
				$result = $count >= $value;
				break;

			case '<=':
				$result = $count <= $value;
				break;

			default:
				$result = false;
		}

		self::debug_log( "Cart Item Count Evaluation: $count $comparison $value = " . ( $result ? 'TRUE' : 'FALSE' ) );

		return $result;
	}

	/**
	 * Evaluate cart coupon contains condition.
	 *
	 * Checks if any of the specified coupon codes are applied to the cart.
	 *
	 * @since 2.5.0
	 * @param array $coupon_codes Array of coupon codes to check.
	 * @param array $cart_info Cart information.
	 * @return bool True if cart contains any of the specified coupons.
	 */
	private static function evaluate_cart_coupon_contains( $coupon_codes, $cart_info ) {
		if ( ! is_array( $coupon_codes ) ) {
			$coupon_codes = array( $coupon_codes );
		}

		// Normalize coupon codes to lowercase.
		$coupon_codes = array_map( 'strtolower', array_filter( $coupon_codes ) );

		if ( empty( $coupon_codes ) ) {
			return false;
		}

		$applied_coupons = isset( $cart_info['applied_coupons'] ) ? $cart_info['applied_coupons'] : array();

		if ( empty( $applied_coupons ) ) {
			self::debug_log( 'Cart Coupon Evaluation: No coupons applied, returning FALSE' );
			return false;
		}

		// Check if any of the specified coupons are applied.
		$intersection = array_intersect( $coupon_codes, $applied_coupons );
		$result       = ! empty( $intersection );

		self::debug_log( 'Cart Coupon Evaluation: Looking for ' . implode( ', ', $coupon_codes ) . ' in ' . implode( ', ', $applied_coupons ) . ' = ' . ( $result ? 'TRUE' : 'FALSE' ) );

		return $result;
	}
}
