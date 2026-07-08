<?php
/**
 * Cache_Manager
 *
 * Handles caching for rules and cart data to optimize performance.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Storage
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Storage;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Rule;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache_Manager class.
 *
 * @since 2.0.0
 */
#[\AllowDynamicProperties]
class Cache_Manager {

	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'fkcf';

	/**
	 * Rules cache prefix.
	 *
	 * @var string
	 */
	const RULES_PREFIX = 'rules_';

	/**
	 * Raw rules data cache prefix (for get_all_rules_v2 and has_rules).
	 *
	 * @var string
	 */
	const RAW_RULES_PREFIX = 'raw_rules_';

	/**
	 * Section rules cache prefix.
	 *
	 * @var string
	 */
	const SECTION_RULES_PREFIX = 'section_rules_';

	/**
	 * Cart cache prefix.
	 *
	 * @var string
	 */
	const CART_PREFIX = 'cart_';

	/**
	 * Cache expiration time (in seconds).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 300; // 5 minutes.

	/**
	 * Get rules from cache.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return array|false Rules array or false if not cached.
	 */
	public static function get_rules( $checkout_id ) {
		$cache_key = self::RULES_PREFIX . absint( $checkout_id );

		// Try object cache first (Redis/Memcached if available).
		$rules = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $rules && self::is_valid_rules_array( $rules ) ) {
			return $rules;
		}

		// Fallback to transient.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		$rules         = get_transient( $transient_key );

		// If found in transient, validate (may contain old FKCF_Rule serialized objects).
		if ( false !== $rules && self::is_valid_rules_array( $rules ) ) {
			wp_cache_set( $cache_key, $rules, self::CACHE_GROUP, self::CACHE_EXPIRATION );
			return $rules;
		}

		return false;
	}

	/**
	 * Check if cached rules array contains only valid Rule instances.
	 * Old cache may contain serialized FKCF_Rule objects that unserialize as __PHP_Incomplete_Class.
	 *
	 * @param mixed $rules Value from cache.
	 * @return bool True if array of Rule instances only.
	 */
	private static function is_valid_rules_array( $rules ) {
		if ( ! is_array( $rules ) ) {
			return false;
		}
		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof Rule ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Set rules in cache.
	 *
	 * @param int   $checkout_id Checkout page ID.
	 * @param array $rules Rules array.
	 * @return bool True on success, false on failure.
	 */
	public static function set_rules( $checkout_id, $rules ) {
		if ( ! is_array( $rules ) ) {
			return false;
		}

		$cache_key = self::RULES_PREFIX . absint( $checkout_id );

		// Store in object cache.
		wp_cache_set( $cache_key, $rules, self::CACHE_GROUP, 0 ); // No expiration for rules.

		// Store in transient as fallback.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		set_transient( $transient_key, $rules, YEAR_IN_SECONDS );

		return true;
	}

	/**
	 * Delete rules from cache.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_rules( $checkout_id ) {
		$cache_key = self::RULES_PREFIX . absint( $checkout_id );

		// Delete from object cache.
		wp_cache_delete( $cache_key, self::CACHE_GROUP );

		// Delete from transient.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		delete_transient( $transient_key );

		// Also delete raw rules cache.
		self::delete_raw_rules( $checkout_id );

		return true;
	}

	/**
	 * Get raw rules data from cache (unparsed post meta).
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return array|false Raw rules data or false if not cached.
	 */
	public static function get_raw_rules( $checkout_id ) {
		$cache_key = self::RAW_RULES_PREFIX . absint( $checkout_id );

		// Try object cache first (Redis/Memcached if available).
		$rules_data = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $rules_data ) {
			return $rules_data;
		}

		return false;
	}

	/**
	 * Set raw rules data in cache.
	 *
	 * @param int   $checkout_id Checkout page ID.
	 * @param mixed $rules_data Raw rules data from post meta.
	 * @return bool True on success, false on failure.
	 */
	public static function set_raw_rules( $checkout_id, $rules_data ) {
		$cache_key = self::RAW_RULES_PREFIX . absint( $checkout_id );

		// Store in object cache only (no transient for raw data).
		wp_cache_set( $cache_key, $rules_data, self::CACHE_GROUP, 0 );

		return true;
	}

	/**
	 * Delete raw rules data from cache.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_raw_rules( $checkout_id ) {
		$cache_key = self::RAW_RULES_PREFIX . absint( $checkout_id );

		// Delete from object cache.
		wp_cache_delete( $cache_key, self::CACHE_GROUP );

		return true;
	}

	/**
	 * Get cart data from cache.
	 *
	 * @param string $cart_hash Cart hash.
	 * @return array|false Cart data or false if not cached.
	 */
	public static function get_cart_data( $cart_hash ) {
		if ( empty( $cart_hash ) ) {
			return false;
		}

		$cache_key = self::CART_PREFIX . sanitize_key( $cart_hash );

		// Try object cache first.
		$cart_data = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cart_data ) {
			return $cart_data;
		}

		// Fallback to transient (with shorter expiration).
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		$cart_data     = get_transient( $transient_key );

		// If found in transient, store in object cache too.
		if ( false !== $cart_data ) {
			wp_cache_set( $cache_key, $cart_data, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $cart_data;
	}

	/**
	 * Set cart data in cache.
	 *
	 * @param string $cart_hash Cart hash.
	 * @param array  $cart_data Cart data.
	 * @return bool True on success, false on failure.
	 */
	public static function set_cart_data( $cart_hash, $cart_data ) {
		if ( empty( $cart_hash ) || ! is_array( $cart_data ) ) {
			return false;
		}

		$cache_key = self::CART_PREFIX . sanitize_key( $cart_hash );

		// Store in object cache.
		wp_cache_set( $cache_key, $cart_data, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		// Store in transient as fallback.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		set_transient( $transient_key, $cart_data, self::CACHE_EXPIRATION );

		return true;
	}

	/**
	 * Delete cart data from cache.
	 *
	 * @param string $cart_hash Cart hash. If empty, clears all cart cache.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_cart_data( $cart_hash = '' ) {
		if ( ! empty( $cart_hash ) ) {
			$cache_key = self::CART_PREFIX . sanitize_key( $cart_hash );

			// Delete from object cache.
			wp_cache_delete( $cache_key, self::CACHE_GROUP );

			// Delete from transient.
			$transient_key = self::CACHE_GROUP . '_' . $cache_key;
			delete_transient( $transient_key );
		}

		return true;
	}

	/**
	 * Clear all cart data cache (useful during AJAX updates).
	 *
	 * @return bool True on success.
	 */
	public static function clear_all_cart_cache() {
		global $wpdb;

		// Clear all cart transients.
		$prefix     = self::CACHE_GROUP . '_' . self::CART_PREFIX;
		$pattern    = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$pattern_to = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $pattern, $pattern_to ) );

		return true;
	}

	/**
	 * Get section rule from cache.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $section_id Section ID.
	 * @return Section_Rule|false Section rule object or false if not cached.
	 */
	public static function get_section_rule( $checkout_id, $section_id ) {
		$cache_key = self::SECTION_RULES_PREFIX . absint( $checkout_id ) . '_' . sanitize_key( $section_id );

		// Try object cache first.
		$section_rule = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $section_rule ) {
			return $section_rule;
		}

		// Fallback to transient.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		$section_rule  = get_transient( $transient_key );

		// If found in transient, store in object cache too.
		if ( false !== $section_rule ) {
			wp_cache_set( $cache_key, $section_rule, self::CACHE_GROUP, 0 ); // No expiration.
		}

		return $section_rule;
	}

	/**
	 * Set section rule in cache.
	 *
	 * @param int          $checkout_id Checkout page ID.
	 * @param string       $section_id Section ID.
	 * @param Section_Rule $section_rule Section rule object.
	 * @return bool True on success, false on failure.
	 */
	public static function set_section_rule( $checkout_id, $section_id, $section_rule ) {
		$cache_key = self::SECTION_RULES_PREFIX . absint( $checkout_id ) . '_' . sanitize_key( $section_id );

		// Store in object cache.
		wp_cache_set( $cache_key, $section_rule, self::CACHE_GROUP, 0 ); // No expiration for section rules.

		// Store in transient as fallback.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		set_transient( $transient_key, $section_rule, YEAR_IN_SECONDS );

		return true;
	}

	/**
	 * Delete section rule from cache.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $section_id Section ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_section_rule( $checkout_id, $section_id ) {
		$cache_key = self::SECTION_RULES_PREFIX . absint( $checkout_id ) . '_' . sanitize_key( $section_id );

		// Delete from object cache.
		wp_cache_delete( $cache_key, self::CACHE_GROUP );

		// Delete from transient.
		$transient_key = self::CACHE_GROUP . '_' . $cache_key;
		delete_transient( $transient_key );

		return true;
	}

	/**
	 * Delete all conditional fields cache for a checkout (rules, section rules, transients).
	 * Call when rules are saved to ensure fresh data on next request.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_all_conditional_fields_cache( $checkout_id ) {
		$checkout_id = absint( $checkout_id );
		if ( ! $checkout_id ) {
			return false;
		}

		// Clear rules cache.
		self::delete_rules( $checkout_id );

		// Clear all section rules for this checkout (transients by prefix).
		global $wpdb;
		$prefix     = self::CACHE_GROUP . '_' . self::SECTION_RULES_PREFIX . $checkout_id . '_';
		$pattern    = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$pattern_to = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $pattern, $pattern_to ) );

		// Clear section rules from object cache (known section keys from rules data).
		$rules_data = get_post_meta( $checkout_id, '_fkcf_conditional_rules', true );
		if ( is_array( $rules_data ) && ! empty( $rules_data['sections'] ) ) {
			foreach ( array_keys( $rules_data['sections'] ) as $section_id ) {
				self::delete_section_rule( $checkout_id, $section_id );
			}
		}

		// Flush object cache group if supported (clears any orphaned section rule keys).
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}

		return true;
	}
}
