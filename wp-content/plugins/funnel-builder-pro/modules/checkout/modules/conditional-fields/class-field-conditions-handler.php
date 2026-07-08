<?php
/**
 * Field Conditions Handler
 *
 * Single source of truth for conditional field rules. Handles save and read
 * via REST API, using _fkcf_conditional_rules as the only storage.
 *
 * New schema: { sections: { section_id: { conditions } }, fields: { field_id: or_groups } }
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields;

use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Cache_Manager;
use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field_Conditions_Handler class.
 *
 * @since 2.0.0
 */
#[\AllowDynamicProperties]
class Field_Conditions_Handler {

	/**
	 * REST API filter/rule to FKCF type/operator map.
	 *
	 * @var array
	 */
	private static $rest_to_fkcf = array(
		'customer_logged_in' => array(
			'==' => array( 'user', 'user_logged_in_eq' ),
			'!=' => array( 'user', 'user_logged_in_ne' ),
		),
		'customer_status'    => array(
			'==' => array( 'user', 'user_logged_in_eq' ),
			'!=' => array( 'user', 'user_logged_in_ne' ),
		),
		'customer_role'      => array(
			'in'    => array( 'user', 'user_role_eq' ),
			'notin' => array( 'user', 'user_role_ne' ),
		),
		'cart_is_virtual'    => array(
			'==' => array( 'cart', 'cart_is_virtual_eq' ),
			'!=' => array( 'cart', 'cart_is_virtual_ne' ),
		),
		'cart_total'         => array(
			'==' => array( 'cart', 'cart_total_eq' ),
			'!=' => array( 'cart', 'cart_total_ne' ),
			'>'  => array( 'cart', 'cart_total_gt' ),
			'<'  => array( 'cart', 'cart_total_lt' ),
			'>=' => array( 'cart', 'cart_total_gte' ),
			'<=' => array( 'cart', 'cart_total_lte' ),
		),
		'cart_subtotal'      => array(
			'==' => array( 'cart', 'cart_subtotal_eq' ),
			'!=' => array( 'cart', 'cart_subtotal_ne' ),
			'>'  => array( 'cart', 'cart_subtotal_gt' ),
			'<'  => array( 'cart', 'cart_subtotal_lt' ),
			'>=' => array( 'cart', 'cart_subtotal_gte' ),
			'<=' => array( 'cart', 'cart_subtotal_lte' ),
		),
		'cart_item_count'    => array(
			'==' => array( 'cart', 'cart_item_count_eq' ),
			'!=' => array( 'cart', 'cart_item_count_ne' ),
			'>'  => array( 'cart', 'cart_item_count_gt' ),
			'<'  => array( 'cart', 'cart_item_count_lt' ),
			'>=' => array( 'cart', 'cart_item_count_gte' ),
			'<=' => array( 'cart', 'cart_item_count_lte' ),
		),
		'cart_coupons'       => array(
			'any'  => array( 'cart', 'cart_coupon_contains', 'coupon' ),
			'none' => array( 'cart', 'cart_coupon_not_contains', 'coupon' ),
		),
		'cart_category'      => array(
			'any'  => array( 'cart', 'cart_contains', 'category' ),
			'all'  => array( 'cart', 'cart_only_contains', 'category' ),
			'none' => array( 'cart', 'cart_not_contains', 'category' ),
		),
		'cart_items'         => array(
			'any'  => array( 'cart', 'cart_contains', 'product' ),
			'all'  => array( 'cart', 'cart_only_contains', 'product' ),
			'none' => array( 'cart', 'cart_not_contains', 'product' ),
		),
		'cart_tag'           => array(
			'any'  => array( 'cart', 'cart_contains', 'tag' ),
			'all'  => array( 'cart', 'cart_only_contains', 'tag' ),
			'none' => array( 'cart', 'cart_not_contains', 'tag' ),
		),
	);

	/**
	 * FKCF operator to REST filter/rule map (reverse conversion).
	 *
	 * @var array
	 */
	private static $fkcf_to_rest = array(
		'user_logged_in_eq'        => array( 'customer_logged_in', '==', 'data' ),
		'user_logged_in_ne'        => array( 'customer_logged_in', '!=', 'data' ),
		'user_role_eq'             => array( 'customer_role', 'in', 'data' ),
		'user_role_ne'             => array( 'customer_role', 'notin', 'data' ),
		'cart_is_virtual_eq'       => array( 'cart_is_virtual', '==', 'data' ),
		'cart_is_virtual_ne'       => array( 'cart_is_virtual', '!=', 'data' ),
		'cart_total_eq'            => array( 'cart_total', '==', 'data' ),
		'cart_total_ne'            => array( 'cart_total', '!=', 'data' ),
		'cart_total_gt'            => array( 'cart_total', '>', 'data' ),
		'cart_total_lt'            => array( 'cart_total', '<', 'data' ),
		'cart_total_gte'           => array( 'cart_total', '>=', 'data' ),
		'cart_total_lte'           => array( 'cart_total', '<=', 'data' ),
		'cart_subtotal_eq'         => array( 'cart_subtotal', '==', 'data' ),
		'cart_subtotal_ne'         => array( 'cart_subtotal', '!=', 'data' ),
		'cart_subtotal_gt'         => array( 'cart_subtotal', '>', 'data' ),
		'cart_subtotal_lt'         => array( 'cart_subtotal', '<', 'data' ),
		'cart_subtotal_gte'        => array( 'cart_subtotal', '>=', 'data' ),
		'cart_subtotal_lte'        => array( 'cart_subtotal', '<=', 'data' ),
		'cart_item_count_eq'       => array( 'cart_item_count', '==', 'data' ),
		'cart_item_count_ne'       => array( 'cart_item_count', '!=', 'data' ),
		'cart_item_count_gt'       => array( 'cart_item_count', '>', 'data' ),
		'cart_item_count_lt'       => array( 'cart_item_count', '<', 'data' ),
		'cart_item_count_gte'      => array( 'cart_item_count', '>=', 'data' ),
		'cart_item_count_lte'      => array( 'cart_item_count', '<=', 'data' ),
		'cart_coupon_contains'     => array( 'cart_coupons', 'any', 'operand' ),
		'cart_coupon_not_contains' => array( 'cart_coupons', 'none', 'operand' ),
		'cart_contains'            => array( null, 'any', 'operand' ),
		'cart_not_contains'        => array( null, 'none', 'operand' ),
		'cart_only_contains'       => array( null, 'all', 'operand' ),
	);

	/**
	 * Canonical section name overrides (base name => FKCF key).
	 *
	 * Used for known sections to ensure consistent keys. Custom sections (unlimited,
	 * any name) fall back to their sanitized base name as the FKCF key.
	 *
	 * @var array<string, string> Map of base name => FKCF key.
	 */
	private static $section_name_to_fkcf = array(
		'shipping-address'     => 'shipping',
		'shipping_address'     => 'shipping',
		'shipping-method'      => 'shipping_method',
		'shipping_method'      => 'shipping_method',
		'contact-information'  => 'billing',
		'customer-information' => 'billing',
		'billing-address'      => 'billing',
		'billing_address'      => 'billing',
		'account'              => 'account',
		'order-notes'          => 'order',
		'order'                => 'order',
		'advanced'             => 'advanced',
	);

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wfacp_save_field_conditions', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wfacp_field_conditions', array( __CLASS__, 'get_for_api' ), 10, 2 );
		add_action( 'wfacp_template_removed', array( __CLASS__, 'delete_conditions' ) );
		add_action( 'wfacp_update_page_layout', array( __CLASS__, 'cleanup_removed_fields' ), 10, 2 );
	}

	/**
	 * Meta key for storing REST format.
	 *
	 * @var string
	 */
	const REST_META_KEY = '_fkcf_field_conditions_rest';

	/**
	 * Save conditions from REST API to _fkcf_conditional_rules.
	 *
	 * New schema: { sections: { section_id: { conditions } }, fields: { field_id: or_groups } }
	 *
	 * @param int   $step_id   Checkout step ID.
	 * @param array $conditions REST API payload.
	 */
	public static function save( $step_id, $conditions ) {
		$step_id = absint( $step_id );

		if ( ! $step_id || ! is_array( $conditions ) ) {
			return;
		}

		// Get existing rules BEFORE saving new ones (to detect removed rules).
		$existing_rules       = Rule_Storage::get_all_rules_v2( $step_id );
		$existing_section_ids = array();
		$existing_field_ids   = array();

		if ( ! empty( $existing_rules['sections'] ) && is_array( $existing_rules['sections'] ) ) {
			foreach ( $existing_rules['sections'] as $section_id => $section_data ) {
				// Track sections that have section_rule.
				if ( ! empty( $section_data['section_rule'] ) ) {
					$existing_section_ids[] = $section_id;
				}
				// Track fields that have rules.
				if ( ! empty( $section_data['field_rules'] ) && is_array( $section_data['field_rules'] ) ) {
					$existing_field_ids = array_merge( $existing_field_ids, array_keys( $section_data['field_rules'] ) );
				}
			}
		}

		// Store REST format for API response.
		update_post_meta( $step_id, self::REST_META_KEY, $conditions );

		// Track which sections/fields are processed in this save.
		$processed_section_ids = array();
		$processed_field_ids   = array();

		// Save section rules.
		$sections = isset( $conditions['sections'] ) && is_array( $conditions['sections'] ) ? $conditions['sections'] : array();
		foreach ( $sections as $rest_section_id => $section_data ) {
			$rest_section_id = sanitize_text_field( $rest_section_id );
			$fkcf_section_id = self::rest_section_to_fkcf( $rest_section_id );

			if ( empty( $fkcf_section_id ) ) {
				continue;
			}

			$processed_section_ids[] = $fkcf_section_id;

			$or_groups = isset( $section_data['conditions'] ) && is_array( $section_data['conditions'] ) ? $section_data['conditions'] : array();

			if ( empty( $or_groups ) ) {
				Rule_Storage::delete_section_rule( $step_id, $fkcf_section_id );
				continue;
			}

			$rule_data = self::rest_to_fkcf_rule( $or_groups );

			if ( ! empty( $rule_data ) ) {
				Rule_Storage::save_section_rule( $step_id, $fkcf_section_id, $rule_data );
			} else {
				Rule_Storage::delete_section_rule( $step_id, $fkcf_section_id );
			}
		}

		// Delete section rules that existed before but are NOT in the new payload.
		foreach ( $existing_section_ids as $old_section_id ) {
			if ( ! in_array( $old_section_id, $processed_section_ids, true ) ) {
				Rule_Storage::delete_section_rule( $step_id, $old_section_id );
			}
		}

		// Save field rules.
		$fields = isset( $conditions['fields'] ) && is_array( $conditions['fields'] ) ? $conditions['fields'] : array();

		// Expand virtual "All Fields" keys to individual address field IDs.
		$fields = self::expand_all_fields_keys( $fields, $step_id );

		foreach ( $fields as $field_id => $or_groups ) {
			$field_id = sanitize_text_field( $field_id );

			if ( empty( $field_id ) ) {
				continue;
			}

			$processed_field_ids[] = $field_id;

			if ( empty( $or_groups ) || ! is_array( $or_groups ) ) {
				Rule_Storage::delete_field_rule( $step_id, $field_id );
				continue;
			}

			$rule_data = self::rest_to_fkcf_rule( $or_groups );

			if ( ! empty( $rule_data ) ) {
				Rule_Storage::save_field_rule( $step_id, $field_id, $rule_data );
			} else {
				Rule_Storage::delete_field_rule( $step_id, $field_id );
			}
		}

		// Delete field rules that existed before but are NOT in the new payload.
		foreach ( $existing_field_ids as $old_field_id ) {
			if ( ! in_array( $old_field_id, $processed_field_ids, true ) ) {
				Rule_Storage::delete_field_rule( $step_id, $old_field_id );
			}
		}

		delete_post_meta( $step_id, '_wfacp_field_conditions' );

		// Clear all conditional fields cache (rules, section rules, transients).
		Cache_Manager::delete_all_conditional_fields_cache( $step_id );

		// Purge page cache so frontend serves fresh content.
		self::purge_checkout_page_cache( $step_id );
	}

	/**
	 * Delete all conditional fields data for a checkout step.
	 * Hooked to wfacp_template_removed (fires on design delete).
	 *
	 * @param int $step_id Checkout step ID.
	 */
	public static function delete_conditions( $step_id ) {
		$step_id = absint( $step_id );
		if ( ! $step_id ) {
			return;
		}

		delete_post_meta( $step_id, '_fkcf_conditional_rules' );
		delete_post_meta( $step_id, self::REST_META_KEY );
		delete_post_meta( $step_id, '_wfacp_field_conditions' );

		Cache_Manager::delete_all_conditional_fields_cache( $step_id );
	}

	/**
	 * Remove conditional rules for fields that no longer exist in the layout.
	 * Hooked to wfacp_update_page_layout (fires on form save).
	 *
	 * @param int   $step_id Checkout step ID.
	 * @param array $data    Layout data from save.
	 */
	public static function cleanup_removed_fields( $step_id, $data ) {
		$step_id = absint( $step_id );
		if ( ! $step_id || ! is_array( $data ) ) {
			return;
		}

		// Use $data directly — get_page_layout() may return cached/stale data.
		$valid_field_ids = self::extract_field_ids_from_layout( $data );
		if ( empty( $valid_field_ids ) ) {
			return;
		}

		// Clean up REST meta (primary storage for admin UI).
		$rest_data = get_post_meta( $step_id, self::REST_META_KEY, true );
		if ( empty( $rest_data ) || ! is_array( $rest_data ) ) {
			return;
		}

		$changed = false;

		// Clean field-level rules.
		if ( ! empty( $rest_data['fields'] ) && is_array( $rest_data['fields'] ) ) {
			foreach ( array_keys( $rest_data['fields'] ) as $field_id ) {
				if ( ! in_array( $field_id, $valid_field_ids, true ) ) {
					unset( $rest_data['fields'][ $field_id ] );
					Rule_Storage::delete_field_rule( $step_id, $field_id );
					$changed = true;
				}
			}
		}

		if ( $changed ) {
			update_post_meta( $step_id, self::REST_META_KEY, $rest_data );
			Cache_Manager::delete_all_conditional_fields_cache( $step_id );
		}
	}

	/**
	 * Extract all valid field IDs from layout data.
	 *
	 * Uses the $data array directly (as passed to wfacp_update_page_layout action)
	 * to avoid stale cache from get_page_layout().
	 *
	 * @param array $data Layout data.
	 * @return array Field IDs.
	 */
	private static function extract_field_ids_from_layout( $data ) {
		$field_ids = array();

		// Fields from fieldsets (billing_email, billing_first_name, etc.).
		if ( ! empty( $data['fieldsets'] ) && is_array( $data['fieldsets'] ) ) {
			foreach ( $data['fieldsets'] as $sections ) {
				if ( ! is_array( $sections ) ) {
					continue;
				}
				foreach ( $sections as $section ) {
					if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
						continue;
					}
					foreach ( $section['fields'] as $field ) {
						if ( ! empty( $field['id'] ) ) {
							$field_ids[] = $field['id'];
						}
					}
				}
			}
		}

		// Address sub-fields from address_order (with billing_/shipping_ prefix).
		$address_order = isset( $data['address_order'] ) && is_array( $data['address_order'] ) ? $data['address_order'] : array();

		// Billing address fields (only active ones with status=true).
		$billing_fields = isset( $address_order['address'] ) && is_array( $address_order['address'] ) ? $address_order['address'] : array();
		foreach ( $billing_fields as $field ) {
			if ( is_array( $field ) && ! empty( $field['key'] ) && self::is_address_field_active( $field ) ) {
				$field_ids[] = 'billing_' . $field['key'];
			}
		}

		// Shipping address fields (only active ones with status=true).
		$shipping_fields = array();
		if ( isset( $address_order['shipping-address'] ) && is_array( $address_order['shipping-address'] ) ) {
			$shipping_fields = $address_order['shipping-address'];
		} elseif ( isset( $address_order['shipping_address'] ) && is_array( $address_order['shipping_address'] ) ) {
			$shipping_fields = $address_order['shipping_address'];
		}
		foreach ( $shipping_fields as $field ) {
			if ( is_array( $field ) && ! empty( $field['key'] ) && self::is_address_field_active( $field ) ) {
				$field_ids[] = 'shipping_' . $field['key'];
			}
		}

		// Include virtual _all_billing / _all_shipping keys so they don't get cleaned up.
		if ( ! empty( $billing_fields ) ) {
			$field_ids[] = '_all_billing';
		}
		if ( ! empty( $shipping_fields ) ) {
			$field_ids[] = '_all_shipping';
		}

		return $field_ids;
	}

	/**
	 * Check if an address sub-field is active (status = true).
	 *
	 * @param array $field Address field data from address_order.
	 * @return bool
	 */
	private static function is_address_field_active( $field ) {
		if ( ! isset( $field['status'] ) ) {
			return true;
		}

		return in_array( $field['status'], array( true, 1, '1', 'true' ), true );
	}

	/**
	 * Purge page cache for checkout post when conditional rules change.
	 * Integrates with popular cache plugins (LiteSpeed, WP Rocket, etc.).
	 *
	 * @param int $checkout_id Checkout page ID.
	 */
	private static function purge_checkout_page_cache( $checkout_id ) {
		$checkout_id = absint( $checkout_id );
		if ( ! $checkout_id ) {
			return;
		}

		// WordPress core: clear post from in-memory cache.
		\clean_post_cache( $checkout_id );

		// LiteSpeed Cache.
		if ( defined( 'LSCWP_V' ) && function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_post', $checkout_id );
		}

		// WP Rocket.
		if ( function_exists( 'rocket_clean_post' ) ) {
			\rocket_clean_post( $checkout_id );
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_post' ) ) {
			\w3tc_flush_post( $checkout_id );
		}

		// SiteGround Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
		}

		// WP-Optimize.
		if ( function_exists( 'wpo_cache_flush' ) ) {
			\wpo_cache_flush();
		}

		// Allow other plugins to purge when conditional fields are saved.
		do_action( 'wfacp_conditional_fields_cache_purged', $checkout_id );
	}

	/**
	 * Get FKCF section key from section name (e.g. "Order Notes" -> "order").
	 *
	 * Used by Section_Visibility and Rule_Engine to map template section names
	 * to the FKCF keys used in rule storage.
	 *
	 * Known sections use canonical keys from the map. Custom sections (unlimited,
	 * any name) use their sanitized base name as the key so rules work without hardcoding.
	 *
	 * @param string $section_name Section display name (e.g. "Order Notes", "Contact Information").
	 * @return string FKCF key (e.g. order, billing) or sanitized base name for custom sections.
	 */
	public static function get_fkcf_key_from_section_name( $section_name ) {
		if ( empty( $section_name ) || ! is_string( $section_name ) ) {
			return '';
		}
		$base_name = preg_replace( '/-\d+$/', '', strtolower( sanitize_title( $section_name ) ) );
		if ( '' === $base_name ) {
			return '';
		}
		$name_map = apply_filters( 'wfacp_fkcf_section_name_to_fkcf_key', self::$section_name_to_fkcf );
		return isset( $name_map[ $base_name ] ) ? $name_map[ $base_name ] : $base_name;
	}

	/**
	 * Map REST section ID to FKCF section ID.
	 *
	 * REST IDs are generated as {sanitized_name}-{index} (e.g. order-notes-3).
	 * Known sections use canonical keys from the map. Custom sections use their
	 * sanitized base name so unlimited user-created sections work without hardcoding.
	 *
	 * @param string $rest_section_id REST section ID (e.g. shipping-address-1).
	 * @return string FKCF section ID (e.g. shipping) or base name for custom sections.
	 */
	private static function rest_section_to_fkcf( $rest_section_id ) {
		$rest_section_id = strtolower( trim( $rest_section_id ) );
		if ( '' === $rest_section_id ) {
			return '';
		}

		// Extract base name: strip trailing -N (e.g. order-notes-3 -> order-notes).
		$base_name = preg_replace( '/-\d+$/', '', $rest_section_id );
		if ( '' === $base_name ) {
			return '';
		}

		$name_map = apply_filters( 'wfacp_fkcf_section_name_to_fkcf_key', self::$section_name_to_fkcf );

		// Known section: use canonical key.
		if ( isset( $name_map[ $base_name ] ) ) {
			return $name_map[ $base_name ];
		}

		// Full ID might match (e.g. "account", "order" without index).
		if ( isset( $name_map[ $rest_section_id ] ) ) {
			return $name_map[ $rest_section_id ];
		}

		// Custom section: use base name as FKCF key (supports unlimited user sections).
		return $base_name;
	}

	/**
	 * Sync all rules from REST meta to Rule_Storage when FKCF rules are missing.
	 *
	 * Called by Frontend when _fkcf_field_conditions_rest has data but
	 * _fkcf_conditional_rules is empty (desync scenario).
	 *
	 * @param int $step_id Checkout step ID.
	 * @return bool True if synced, false otherwise.
	 */
	public static function sync_all_rules_from_rest_meta( $step_id ) {
		$step_id = absint( $step_id );

		if ( ! $step_id ) {
			return false;
		}

		$rest_meta = get_post_meta( $step_id, self::REST_META_KEY, true );

		if ( empty( $rest_meta ) || ! is_array( $rest_meta ) ) {
			return false;
		}

		// Check if there's actually data to sync.
		$has_sections = ! empty( $rest_meta['sections'] ) && is_array( $rest_meta['sections'] );
		$has_fields   = ! empty( $rest_meta['fields'] ) && is_array( $rest_meta['fields'] );

		if ( ! $has_sections && ! $has_fields ) {
			return false;
		}

		// Re-run save() to convert REST format to FKCF format.
		self::save( $step_id, $rest_meta );

		return true;
	}

	/**
	 * Sync section rule from REST meta to Rule_Storage when missing.
	 *
	 * Handles desync when REST meta has conditions (e.g. from builder) but Rule_Storage
	 * does not (e.g. save ran before shipping-method mapping existed).
	 *
	 * @param int    $step_id         Checkout step ID.
	 * @param string $fkcf_section_id FKCF section key (e.g. shipping_method).
	 * @return bool True if synced, false otherwise.
	 */
	public static function sync_section_rule_from_rest_meta( $step_id, $fkcf_section_id ) {
		$step_id = absint( $step_id );
		if ( ! $step_id || ! is_string( $fkcf_section_id ) || '' === $fkcf_section_id ) {
			return false;
		}

		$rest_meta = get_post_meta( $step_id, self::REST_META_KEY, true );
		if ( empty( $rest_meta['sections'] ) || ! is_array( $rest_meta['sections'] ) ) {
			return false;
		}

		foreach ( $rest_meta['sections'] as $rest_section_id => $section_data ) {
			if ( self::rest_section_to_fkcf( $rest_section_id ) !== $fkcf_section_id ) {
				continue;
			}

			$or_groups = isset( $section_data['conditions'] ) && is_array( $section_data['conditions'] ) ? $section_data['conditions'] : array();
			if ( empty( $or_groups ) ) {
				continue;
			}

			$rule_data = self::rest_to_fkcf_rule( $or_groups );
			if ( ! empty( $rule_data ) ) {
				Rule_Storage::save_section_rule( $step_id, $fkcf_section_id, $rule_data );
				return true;
			}
		}

		return false;
	}

	/**
	 * Map FKCF section ID to REST section ID (for API response).
	 *
	 * Resolves the actual REST section ID from the step's form layout when available,
	 * so the API returns IDs that match the React UI's fieldSets.
	 *
	 * @param string $fkcf_section_id FKCF section ID (e.g. shipping, billing).
	 * @param int    $step_id         Checkout step ID (0 to skip layout lookup).
	 * @return string REST section ID.
	 */
	private static function fkcf_section_to_rest( $fkcf_section_id, $step_id = 0 ) {
		$step_id = absint( $step_id );
		if ( $step_id > 0 && class_exists( '\WFACP_Common' ) ) {
			return self::get_rest_section_id_from_layout( $fkcf_section_id, $step_id );
		}

		return '';
	}

	/**
	 * Get REST section ID for an FKCF key from the step's layout.
	 *
	 * Replicates the section ID generation from format_checkout_fieldset so we
	 * return IDs that match what the React UI receives.
	 *
	 * @param string $fkcf_section_id FKCF section key.
	 * @param int    $step_id        Checkout step ID.
	 * @return string REST section ID or empty if not found.
	 */
	private static function get_rest_section_id_from_layout( $fkcf_section_id, $step_id ) {
		$layout = \WFACP_Common::get_page_layout( $step_id );
		if ( empty( $layout['fieldsets'] ) || ! is_array( $layout['fieldsets'] ) ) {
			return '';
		}

		$i = 0;
		foreach ( $layout['fieldsets'] as $sections ) {
			if ( ! is_array( $sections ) ) {
				continue;
			}
			foreach ( $sections as $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}
				$rest_id = isset( $section['name'] )
					? sanitize_title( $section['name'] . '-' . $i )
					: (string) $i;
				++$i;

				if ( self::rest_section_to_fkcf( $rest_id ) === $fkcf_section_id ) {
					return $rest_id;
				}
			}
		}

		return '';
	}

	/**
	 * Get conditions for REST API.
	 *
	 * @param array $conditions Default (empty).
	 * @param int   $step_id    Checkout step ID.
	 * @return array REST API format { sections, fields }.
	 */
	public static function get_for_api( $conditions, $step_id ) {
		$step_id = absint( $step_id );

		if ( ! $step_id ) {
			return $conditions;
		}

		// One-time migration from legacy format.
		$legacy = get_post_meta( $step_id, '_wfacp_field_conditions', true );
		if ( ! empty( $legacy ) && is_array( $legacy ) && ! Rule_Storage::has_rules( $step_id ) ) {
			$migrated = self::migrate_legacy_to_new_schema( $legacy );
			if ( ! empty( $migrated ) ) {
				self::save( $step_id, $migrated );
			}
		}

		$stored = get_post_meta( $step_id, self::REST_META_KEY, true );
		if ( ! empty( $stored ) && is_array( $stored ) ) {
			// Sync REST → FKCF when REST has field rules but FKCF does not (desync fix).
			$has_rest_fields = ! empty( $stored['fields'] ) && is_array( $stored['fields'] );
			if ( $has_rest_fields && ! Rule_Storage::has_rules( $step_id ) ) {
				self::sync_all_rules_from_rest_meta( $step_id );
			}
			return $stored;
		}

		return self::fkcf_to_rest_format( $step_id );
	}

	/**
	 * Migrate legacy schema to new { sections, fields } format.
	 *
	 * @param array $legacy Legacy payload.
	 * @return array New schema.
	 */
	private static function migrate_legacy_to_new_schema( $legacy ) {
		$sections = array();
		$fields   = array();

		foreach ( $legacy as $section_key => $section_data ) {
			if ( ! is_array( $section_data ) ) {
				continue;
			}

			$apply_to           = isset( $section_data['applyTo'] ) ? sanitize_text_field( $section_data['applyTo'] ) : '';
			$field_conditions   = isset( $section_data['fieldConditions'] ) && is_array( $section_data['fieldConditions'] )
				? $section_data['fieldConditions']
				: array();
			$section_conditions = isset( $section_data['conditions'] ) && is_array( $section_data['conditions'] )
				? $section_data['conditions']
				: array();

			if ( 'section' === $apply_to && ! empty( $section_conditions ) ) {
				$sections[ $section_key ] = array( 'conditions' => $section_conditions );
			}

			foreach ( $field_conditions as $field_id => $or_groups ) {
				if ( ! empty( $or_groups ) && is_array( $or_groups ) ) {
					$fields[ $field_id ] = $or_groups;
				}
			}
		}

		return array(
			'sections' => $sections,
			'fields'   => $fields,
		);
	}

	/**
	 * Convert _fkcf_conditional_rules to new REST format { sections, fields }.
	 *
	 * @param int $step_id Checkout step ID.
	 * @return array
	 */
	private static function fkcf_to_rest_format( $step_id ) {
		$all = Rule_Storage::get_all_rules_v2( $step_id );

		if ( empty( $all['sections'] ) ) {
			return array(
				'sections' => array(),
				'fields'   => array(),
			);
		}

		$sections = array();
		$fields   = array();

		foreach ( $all['sections'] as $section_id => $section_data ) {
			$rest_section_id = self::fkcf_section_to_rest( $section_id, $step_id );

			// Section rules (only when we can resolve REST section ID).
			if ( '' !== $rest_section_id ) {
				$section_rule = isset( $section_data['section_rule'] ) ? $section_data['section_rule'] : null;
				if ( $section_rule && is_object( $section_rule ) ) {
					$or_groups = self::fkcf_rule_to_rest_groups( $section_rule );
					if ( ! empty( $or_groups ) ) {
						$sections[ $rest_section_id ] = array( 'conditions' => $or_groups );
					}
				}
			}

			// Field rules (always add; keyed by field_id).
			$field_rules = isset( $section_data['field_rules'] ) ? $section_data['field_rules'] : array();
			foreach ( $field_rules as $field_id => $rule ) {
				if ( ! is_object( $rule ) ) {
					continue;
				}

				$or_groups = self::fkcf_rule_to_rest_groups( $rule );

				if ( ! empty( $or_groups ) ) {
					$fields[ $field_id ] = $or_groups;
				}
			}
		}

		return array(
			'sections' => $sections,
			'fields'   => $fields,
		);
	}

	/**
	 * Convert FKCF Rule object to REST or_groups format.
	 *
	 * @param object $rule Rule object with get_rule_groups().
	 * @return array Array of OR groups.
	 */
	private static function fkcf_rule_to_rest_groups( $rule ) {
		$groups = method_exists( $rule, 'get_rule_groups' ) ? $rule->get_rule_groups() : array();

		if ( empty( $groups ) ) {
			return array();
		}

		$rest_groups = array();

		foreach ( $groups as $group ) {
			$conditions = method_exists( $group, 'get_conditions' ) ? $group->get_conditions() : array();

			if ( empty( $conditions ) ) {
				continue;
			}

			$and_conditions = array();

			foreach ( $conditions as $cond ) {
				$rest_cond = self::fkcf_condition_to_rest( $cond );

				if ( ! empty( $rest_cond ) ) {
					$and_conditions[] = $rest_cond;
				}
			}

			if ( ! empty( $and_conditions ) ) {
				$rest_groups[] = $and_conditions;
			}
		}

		return $rest_groups;
	}

	/**
	 * Convert FKCF Condition to REST format.
	 *
	 * @param object $cond Condition object.
	 * @return array|null REST condition or null.
	 */
	private static function fkcf_condition_to_rest( $cond ) {
		if ( ! is_object( $cond ) ) {
			return null;
		}

		$operator     = method_exists( $cond, 'get_operator' ) ? $cond->get_operator() : '';
		$value        = method_exists( $cond, 'get_value' ) ? $cond->get_value() : '';
		$operand      = method_exists( $cond, 'get_operand' ) ? $cond->get_operand() : '';
		$operand_type = method_exists( $cond, 'get_operand_type' ) ? $cond->get_operand_type() : '';

		if ( isset( self::$fkcf_to_rest[ $operator ] ) ) {
			list( $filter, $rule, $key ) = self::$fkcf_to_rest[ $operator ];

			$data = ( 'operand' === $key && ! empty( $operand_type ) ) ? self::operand_to_rest_data( $operand, $operand_type ) : $value;

			$filter_from_type = array(
				'category' => 'cart_category',
				'product'  => 'cart_items',
				'tag'      => 'cart_tag',
			);
			$filter           = $filter ?? ( isset( $filter_from_type[ $operand_type ] ) ? $filter_from_type[ $operand_type ] : '' );

			if ( empty( $filter ) ) {
				return null;
			}

			return array(
				'filter' => $filter,
				'rule'   => $rule,
				'data'   => $data,
			);
		}

		return null;
	}

	/**
	 * Convert operand (IDs) to REST data format [{ key, label }].
	 *
	 * @param mixed  $operand      Operand value (IDs).
	 * @param string $operand_type Operand type (category, product, tag).
	 * @return array
	 */
	private static function operand_to_rest_data( $operand, $operand_type ) {
		if ( ! is_array( $operand ) ) {
			$operand = array( $operand );
		}

		$operand = array_map( 'absint', $operand );
		$operand = array_filter( $operand );

		$result = array();
		foreach ( $operand as $id ) {
			$label = $id;
			if ( 'category' === $operand_type && taxonomy_exists( 'product_cat' ) ) {
				$term  = get_term( $id, 'product_cat' );
				$label = $term && ! is_wp_error( $term ) ? $term->name : (string) $id;
			} elseif ( 'product' === $operand_type ) {
				$product = wc_get_product( $id );
				$label   = $product ? $product->get_name() : (string) $id;
			} elseif ( 'tag' === $operand_type && taxonomy_exists( 'product_tag' ) ) {
				$term  = get_term( $id, 'product_tag' );
				$label = $term && ! is_wp_error( $term ) ? $term->name : (string) $id;
			}
			$result[] = array(
				'key'   => (string) $id,
				'label' => $label,
			);
		}

		return $result;
	}

	/**
	 * Convert REST or_groups to FKCF rule format.
	 *
	 * @param array $or_groups REST format.
	 * @return array Rule data for Rule_Storage.
	 */
	private static function rest_to_fkcf_rule( $or_groups ) {
		$rule_groups = array();

		foreach ( $or_groups as $and_conditions ) {
			if ( ! is_array( $and_conditions ) ) {
				continue;
			}

			$group_conditions = array();

			foreach ( $and_conditions as $cond ) {
				$fkcf = self::rest_condition_to_fkcf( $cond );

				if ( ! empty( $fkcf ) ) {
					$group_conditions[] = $fkcf;
				}
			}

			if ( ! empty( $group_conditions ) ) {
				$rule_groups[] = $group_conditions;
			}
		}

		if ( empty( $rule_groups ) ) {
			return array();
		}

		return array(
			'action'      => 'show',
			'group_logic' => 'OR',
			'enabled'     => true,
			'rule_groups' => $rule_groups,
		);
	}

	/**
	 * Convert single REST condition to FKCF format.
	 *
	 * @param array $cond REST condition.
	 * @return array|null FKCF condition or null.
	 */
	private static function rest_condition_to_fkcf( $cond ) {
		if ( ! is_array( $cond ) ) {
			return null;
		}

		$filter = isset( $cond['filter'] ) ? sanitize_text_field( $cond['filter'] ) : '';
		$rule   = isset( $cond['rule'] ) ? sanitize_text_field( $cond['rule'] ) : '';
		// sanitize_text_field converts < to &lt;, so <= becomes &lt;= and breaks lookup. Restore for comparison operators.
		$rule = html_entity_decode( $rule, ENT_QUOTES, 'UTF-8' );
		$data = isset( $cond['data'] ) ? $cond['data'] : '';

		if ( empty( $filter ) || empty( $rule ) ) {
			return null;
		}

		// Normalize rule: BWF may send 'is' (label) instead of '==' (key) for customer_status.
		if ( in_array( $filter, array( 'customer_status', 'customer_logged_in' ), true ) && 'is' === $rule ) {
			$rule = '==';
		}

		// Field-based filters (field_billing_country, field_shipping_country, etc.).
		if ( 0 === strpos( $filter, 'field_' ) ) {
			$field_id = substr( $filter, 6 );
			if ( '' === $field_id ) {
				return null;
			}

			$rule_to_operator = array(
				'in'           => 'value_in',
				'any'          => 'value_in',     // Country/multiselect: "matches any of"
				'none'         => 'value_none',   // Country/multiselect: "matches none of"
				'=='           => 'value_eq',
				'is'           => 'value_eq',
				'!='           => 'value_ne',
				'is_not'       => 'value_ne',
				'empty'        => 'value_empty',
				'is_blank'     => 'value_empty',
				'not_empty'    => 'value_not_empty',
				'is_not_blank' => 'value_not_empty',
				'contains'     => 'value_contains',
			);

			$operator = isset( $rule_to_operator[ $rule ] ) ? $rule_to_operator[ $rule ] : '';
			if ( '' === $operator ) {
				return null;
			}

			// Extract value from data. For list operators, data is [{key, label}] - use keys.
			$list_operators = array( 'value_in', 'value_none' );
			if ( in_array( $operator, $list_operators, true ) && is_array( $data ) ) {
				$value = array();
				foreach ( $data as $item ) {
					if ( is_array( $item ) && isset( $item['key'] ) ) {
						$value[] = sanitize_text_field( (string) $item['key'] );
					} elseif ( is_string( $item ) ) {
						$value[] = sanitize_text_field( $item );
					}
				}
			} elseif ( 'value_empty' === $operator || 'value_not_empty' === $operator ) {
				$value = '';
			} else {
				$value = is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : sanitize_text_field( (string) $data );
				if ( is_array( $value ) && 1 === count( $value ) ) {
					$value = reset( $value );
				}
			}

			return array(
				'type'     => 'field',
				'operator' => $operator,
				'value'    => $value,
				'field_id' => $field_id,
			);
		}

		// Direct mapping (customer_logged_in, cart_is_virtual).
		if ( isset( self::$rest_to_fkcf[ $filter ][ $rule ] ) ) {
			$mapping = self::$rest_to_fkcf[ $filter ][ $rule ];

			if ( 3 === count( $mapping ) ) {
				// Cart operand type (cart_category, cart_items, cart_tag, coupon).
				list( $type, $operator, $operand_type ) = $mapping;

				// Coupons use codes (strings), not IDs.
				if ( 'coupon' === $operand_type ) {
					$operand = self::rest_data_to_coupon_codes( $data );
				} else {
					$operand = self::rest_data_to_operand( $data );
				}

				return array(
					'type'         => $type,
					'operator'     => $operator,
					'operand_type' => $operand_type,
					'operand'      => $operand,
					'value'        => '',
				);
			}

			list( $type, $operator ) = $mapping;
			$value                   = is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : sanitize_text_field( (string) $data );

			// Convert customer_status (logged_in/logged_out) to user_logged_in format (yes/no).
			if ( 'customer_status' === $filter || 'customer_logged_in' === $filter ) {
				$raw = $value;
				// Handle object format from BWF: { key: 'logged_out', label: 'Logged Out' }.
				if ( is_array( $raw ) && isset( $raw['key'] ) ) {
					$raw = $raw['key'];
				} elseif ( is_array( $raw ) ) {
					$raw = isset( $raw[0] ) ? $raw[0] : (string) reset( $raw );
				}
				$raw   = is_string( $raw ) ? $raw : (string) $raw;
				$value = ( 'logged_in' === $raw || 'yes' === $raw ) ? 'yes' : 'no';
			}

			// Convert customer_role data to array of role slugs.
			if ( 'customer_role' === $filter ) {
				$roles = array();
				if ( is_array( $data ) ) {
					foreach ( $data as $item ) {
						if ( is_array( $item ) && isset( $item['key'] ) ) {
							$roles[] = sanitize_text_field( $item['key'] );
						} elseif ( is_string( $item ) ) {
							$roles[] = sanitize_text_field( $item );
						}
					}
				} elseif ( is_string( $data ) ) {
					$roles[] = sanitize_text_field( $data );
				}
				$value = ! empty( $roles ) ? $roles : array();
			}

			// Ensure cart_item_count value is a non-negative integer.
			if ( 'cart_item_count' === $filter ) {
				$value = absint( $value );
			}

			$fkcf = array(
				'type'     => $type,
				'operator' => $operator,
				'value'    => $value,
			);

			if ( in_array( $operator, array( 'cart_is_virtual_eq', 'cart_is_virtual_ne' ), true ) ) {
				$fkcf['operand_type'] = '';
				$fkcf['operand']      = '';
			}

			return $fkcf;
		}

		return null;
	}

	/**
	 * Extract operand (IDs) from REST data.
	 *
	 * @param mixed $data REST data (string, array of IDs, or [{key, label}]).
	 * @return array Array of IDs.
	 */
	private static function rest_data_to_operand( $data ) {
		if ( is_array( $data ) ) {
			$ids = array();
			foreach ( $data as $item ) {
				if ( is_array( $item ) && isset( $item['key'] ) ) {
					$ids[] = absint( $item['key'] );
				} elseif ( is_numeric( $item ) ) {
					$ids[] = absint( $item );
				}
			}

			return array_filter( $ids );
		}

		if ( is_numeric( $data ) ) {
			return array( absint( $data ) );
		}

		return array_filter( array_map( 'absint', explode( ',', (string) $data ) ) );
	}

	/**
	 * Extract coupon codes from REST data.
	 *
	 * Coupons are stored by their code (string), not by ID.
	 *
	 * @param mixed $data REST data (string, array of codes, or [{key, label}]).
	 * @return array Array of coupon codes (lowercase).
	 */
	private static function rest_data_to_coupon_codes( $data ) {
		$codes = array();

		if ( is_array( $data ) ) {
			foreach ( $data as $item ) {
				if ( is_array( $item ) && isset( $item['key'] ) ) {
					$codes[] = strtolower( sanitize_text_field( $item['key'] ) );
				} elseif ( is_string( $item ) ) {
					$codes[] = strtolower( sanitize_text_field( $item ) );
				}
			}
		} elseif ( is_string( $data ) ) {
			$codes[] = strtolower( sanitize_text_field( $data ) );
		}

		return array_filter( $codes );
	}

	/**
	 * Expand virtual _all_billing / _all_shipping keys
	 * into individual address field IDs using the checkout's address order.
	 *
	 * @param array $fields  Field conditions keyed by field ID.
	 * @param int   $step_id Checkout step ID.
	 * @return array Expanded fields.
	 */
	private static function expand_all_fields_keys( $fields, $step_id ) {
		$has_billing  = isset( $fields['_all_billing'] );
		$has_shipping = isset( $fields['_all_shipping'] );

		if ( ! $has_billing && ! $has_shipping ) {
			return $fields;
		}

		$address_order = array();
		if ( class_exists( '\WFACP_Common' ) && method_exists( '\WFACP_Common', 'get_page_layout' ) ) {
			$layout        = \WFACP_Common::get_page_layout( $step_id );
			$address_order = isset( $layout['address_order'] ) && is_array( $layout['address_order'] ) ? $layout['address_order'] : array();
		}

		if ( $has_billing ) {
			$or_groups      = $fields['_all_billing'];
			$billing_fields = isset( $address_order['address'] ) && is_array( $address_order['address'] ) ? $address_order['address'] : array();
			foreach ( $billing_fields as $field ) {
				if ( ! is_array( $field ) || empty( $field['key'] ) ) {
					continue;
				}
				// Only expand active fields (include same_as toggle).
				$status    = isset( $field['status'] ) ? $field['status'] : false;
				$is_active = ( true === $status || 1 === $status || '1' === $status || 'true' === $status );
				if ( ! $is_active ) {
					continue;
				}
				// address_order stores raw keys (e.g. address_1, city); checkout fields use billing_ prefix.
				$prefixed_key = 'billing_' . $field['key'];
				if ( ! isset( $fields[ $prefixed_key ] ) ) {
					$fields[ $prefixed_key ] = $or_groups;
				}
			}
			unset( $fields['_all_billing'] );
		}

		if ( $has_shipping ) {
			$or_groups = $fields['_all_shipping'];
			// Key may be 'shipping-address' (hyphen) or 'shipping_address' (underscore) depending on layout version.
			$shipping_fields = array();
			if ( isset( $address_order['shipping-address'] ) && is_array( $address_order['shipping-address'] ) ) {
				$shipping_fields = $address_order['shipping-address'];
			} elseif ( isset( $address_order['shipping_address'] ) && is_array( $address_order['shipping_address'] ) ) {
				$shipping_fields = $address_order['shipping_address'];
			}
			foreach ( $shipping_fields as $field ) {
				if ( ! is_array( $field ) || empty( $field['key'] ) ) {
					continue;
				}
				$status    = isset( $field['status'] ) ? $field['status'] : false;
				$is_active = ( true === $status || 1 === $status || '1' === $status || 'true' === $status );
				if ( ! $is_active ) {
					continue;
				}
				// address_order stores raw keys (e.g. address_1, city); checkout fields use shipping_ prefix.
				$prefixed_key = 'shipping_' . $field['key'];
				if ( ! isset( $fields[ $prefixed_key ] ) ) {
					$fields[ $prefixed_key ] = $or_groups;
				}
			}
			unset( $fields['_all_shipping'] );
		}

		return $fields;
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private static function debug_log( $message ) {
		// Debug logging disabled.
	}
}
