<?php
/**
 * Rule_Storage
 *
 * Handles saving and loading rules from post meta.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Storage
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Storage;

use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Rule;
use FunnelKit\Checkout\Modules\Conditional_Fields\Models\Section_Rule;
use FunnelKit\Checkout\Modules\Conditional_Fields\Field_Conditions_Handler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule_Storage class.
 *
 * @since 2.0.0
 */
class Rule_Storage {

	/**
	 * Meta key for storing rules.
	 *
	 * @var string
	 */
	const META_KEY = '_fkcf_conditional_rules';

	/**
	 * Data version.
	 *
	 * @var string
	 */
	const DATA_VERSION = '2.0';

	/**
	 * Get all rules for a checkout page.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array of rules indexed by field ID.
	 */
	public static function get_rules( $checkout_id ) {
		$checkout_id = absint( $checkout_id );

		if ( ! $checkout_id ) {
			return array();
		}

		// Try to get from cache first.
		$rules = Cache_Manager::get_rules( $checkout_id );

		if ( false !== $rules ) {
			return $rules;
		}

		// Get raw rules data (uses caching internally).
		$rules_data = self::get_raw_rules_data( $checkout_id );

		// Parse and validate rules.
		$rules = self::parse_rules( $rules_data );

		// Cache the rules.
		Cache_Manager::set_rules( $checkout_id, $rules );

		return $rules;
	}

	/**
	 * Get raw rules data from post meta with caching.
	 * This avoids multiple get_post_meta calls during a single request.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return array|mixed Raw rules data from post meta.
	 */
	public static function get_raw_rules_data( $checkout_id ) {
		$checkout_id = absint( $checkout_id );

		if ( ! $checkout_id ) {
			return array();
		}

		// Try to get from cache first.
		$rules_data = Cache_Manager::get_raw_rules( $checkout_id );

		if ( false !== $rules_data ) {
			return $rules_data;
		}

		// Get from post meta.
		$rules_data = get_post_meta( $checkout_id, self::META_KEY, true );

		// Cache the raw data.
		Cache_Manager::set_raw_rules( $checkout_id, $rules_data );

		return $rules_data;
	}

	/**
	 * Save rules for a checkout page.
	 *
	 * @param int   $checkout_id Checkout page ID.
	 * @param array $rules Array of rules indexed by field ID.
	 * @return bool True on success, false on failure.
	 */
	public static function save_rules( $checkout_id, $rules ) {
		$checkout_id = absint( $checkout_id );

		if ( ! $checkout_id || ! is_array( $rules ) ) {
			return false;
		}

		// Prepare rules data.
		$rules_data = array(
			'version' => self::DATA_VERSION,
			'fields'  => array(),
		);

		foreach ( $rules as $field_id => $rule_data ) {
			if ( $rule_data instanceof Rule ) {
				$rules_data['fields'][ $field_id ] = $rule_data->to_array();
			} elseif ( is_array( $rule_data ) ) {
				$rules_data['fields'][ $field_id ] = $rule_data;
			}
		}

		// Save to post meta.
		update_post_meta( $checkout_id, self::META_KEY, $rules_data );

		// Clear cache.
		Cache_Manager::delete_rules( $checkout_id );

		// update_post_meta returns false if value is unchanged, but that's ok
		// It returns the meta ID on insert, true on update, false if unchanged or error
		// We should return true if the data is now in the database, regardless of whether it changed
		return true;
	}

	/**
	 * Get rule for a specific field.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $field_id Field ID.
	 * @return Rule|null Rule object or null if not found.
	 */
	public static function get_field_rule( $checkout_id, $field_id ) {
		$checkout_id = absint( $checkout_id );
		$field_id    = sanitize_text_field( $field_id );

		if ( ! $checkout_id || ! $field_id ) {
			return null;
		}

		// Use cached raw rules data.
		$rules_data = self::get_raw_rules_data( $checkout_id );

		// Check if v2.0 structure.
		if ( isset( $rules_data['sections'] ) ) {
			// Determine section for this field.
			$section_id = self::get_section_for_field( $field_id );

			// Check if field rule exists.
			if ( isset( $rules_data['sections'][ $section_id ]['field_rules'][ $field_id ] ) ) {
				$field_rule_data             = $rules_data['sections'][ $section_id ]['field_rules'][ $field_id ];
				$field_rule_data['field_id'] = $field_id;
				return Rule::from_array( $field_rule_data );
			}
		}

		return null;
	}

	/**
	 * Save rule for a specific field.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $field_id Field ID.
	 * @param array  $rule_data Rule data.
	 * @return bool True on success, false on failure.
	 */
	public static function save_field_rule( $checkout_id, $field_id, $rule_data ) {
		$checkout_id = absint( $checkout_id );
		$field_id    = sanitize_text_field( $field_id );

		if ( ! $checkout_id || ! $field_id ) {
			self::debug_log( "save_field_rule: invalid checkout_id=$checkout_id or field_id=$field_id" );
			return false;
		}

		// Add field_id to rule data.
		$rule_data['field_id'] = $field_id;

		self::debug_log( "save_field_rule: Creating rule for $field_id with data: " . wp_json_encode( $rule_data ) );

		// Create rule object.
		$rule = Rule::from_array( $rule_data );

		if ( ! $rule ) {
			self::debug_log( "save_field_rule: Rule::from_array returned null for $field_id - rule is invalid" );
			// Log more details about why the rule might be invalid.
			$temp_rule = new Rule( $rule_data );
			self::debug_log( 'save_field_rule: Rule validation details - field_id=' . $temp_rule->get_field_id() . ', action=' . $temp_rule->get_action() . ', group_logic=' . $temp_rule->get_group_logic() . ', rule_groups_count=' . count( $temp_rule->get_rule_groups() ) );
			return false;
		}

		// Determine section for this field.
		$section_id = self::get_section_for_field( $field_id );

		// Get existing rules data (raw).
		$rules_data = get_post_meta( $checkout_id, self::META_KEY, true );

		// Migrate v1.0 to v2.0 if needed.
		if ( is_array( $rules_data ) && isset( $rules_data['fields'] ) && ! isset( $rules_data['sections'] ) ) {
			$rules_data = self::migrate_v1_to_v2( $rules_data );
		}

		// Initialize v2.0 structure if empty or invalid.
		if ( ! is_array( $rules_data ) || ! isset( $rules_data['sections'] ) ) {
			$rules_data = array(
				'version'  => self::DATA_VERSION,
				'sections' => array(),
			);
		}

		// Ensure section exists.
		if ( ! isset( $rules_data['sections'][ $section_id ] ) ) {
			$rules_data['sections'][ $section_id ] = array(
				'section_rule' => null,
				'field_rules'  => array(),
			);
		}

		// Update field rule.
		$rules_data['sections'][ $section_id ]['field_rules'][ $field_id ] = $rule->to_array();

		// Save to database.
		update_post_meta( $checkout_id, self::META_KEY, $rules_data );

		// Clear cache.
		Cache_Manager::delete_rules( $checkout_id );

		return true;
	}

	/**
	 * Delete rule for a specific field.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $field_id Field ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_field_rule( $checkout_id, $field_id ) {
		$checkout_id = absint( $checkout_id );
		$field_id    = sanitize_text_field( $field_id );

		if ( ! $checkout_id || ! $field_id ) {
			return false;
		}

		// Get raw rules data.
		$rules_data = get_post_meta( $checkout_id, self::META_KEY, true );

		if ( ! is_array( $rules_data ) ) {
			return false;
		}

		// Check if v2.0 structure.
		if ( isset( $rules_data['sections'] ) ) {
			// Determine section for this field.
			$section_id = self::get_section_for_field( $field_id );

			// Delete from section's field_rules.
			if ( isset( $rules_data['sections'][ $section_id ]['field_rules'][ $field_id ] ) ) {
				unset( $rules_data['sections'][ $section_id ]['field_rules'][ $field_id ] );

				// Save updated data.
				$result = update_post_meta( $checkout_id, self::META_KEY, $rules_data );

				// Clear cache.
				Cache_Manager::delete_rules( $checkout_id );

				return true;
			}
		} elseif ( isset( $rules_data['fields'][ $field_id ] ) ) {
			// v1.0 structure.
			unset( $rules_data['fields'][ $field_id ] );

			// Save updated data.
			$result = update_post_meta( $checkout_id, self::META_KEY, $rules_data );

			// Clear cache.
			Cache_Manager::delete_rules( $checkout_id );

			return true;
		}

		return false;
	}

	/**
	 * Delete all rules for a checkout page.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_rules( $checkout_id ) {
		$checkout_id = absint( $checkout_id );

		if ( ! $checkout_id ) {
			return false;
		}

		// Delete post meta.
		$result = delete_post_meta( $checkout_id, self::META_KEY );

		// Clear cache.
		Cache_Manager::delete_rules( $checkout_id );

		return $result;
	}

	/**
	 * Parse rules data from storage.
	 *
	 * @param  mixed $rules_data Rules data from storage.
	 * @return array Parsed rules array.
	 */
	private static function parse_rules( $rules_data ) {
		if ( ! is_array( $rules_data ) ) {
			return array();
		}

		$rules = array();

		// Check if v2.0 structure (hierarchical with sections).
		if ( isset( $rules_data['sections'] ) && is_array( $rules_data['sections'] ) ) {
			// Extract all field rules from all sections.
			foreach ( $rules_data['sections'] as $section_id => $section_data ) {
				if ( isset( $section_data['field_rules'] ) && is_array( $section_data['field_rules'] ) ) {
					foreach ( $section_data['field_rules'] as $field_id => $rule_data ) {
						$rule_data['field_id'] = $field_id;
						$rule                  = Rule::from_array( $rule_data );

						if ( $rule ) {
							$rules[ $field_id ] = $rule;
						}
					}
				}
			}
		} elseif ( isset( $rules_data['fields'] ) && is_array( $rules_data['fields'] ) ) {
			// v1.0 structure (flat).
			foreach ( $rules_data['fields'] as $field_id => $rule_data ) {
				$rule_data['field_id'] = $field_id;
				$rule                  = Rule::from_array( $rule_data );

				if ( $rule ) {
					$rules[ $field_id ] = $rule;
				}
			}
		}

		return $rules;
	}

	/**
	 * Get all rules in v2.0 hierarchical structure (sections + fields).
	 *
	 * @since 2.3.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array with 'version' and 'sections' keys, where sections contain section_rule and field_rules.
	 */
	public static function get_all_rules_v2( $checkout_id ) {
		$checkout_id = absint( $checkout_id );

		if ( ! $checkout_id ) {
			return array(
				'version'  => self::DATA_VERSION,
				'sections' => array(),
			);
		}

		// Use cached raw rules data.
		$rules_data = self::get_raw_rules_data( $checkout_id );

		if ( ! is_array( $rules_data ) ) {
			return array(
				'version'  => self::DATA_VERSION,
				'sections' => array(),
			);
		}

		// Ensure v2.0 structure.
		if ( ! isset( $rules_data['sections'] ) ) {
			return array(
				'version'  => self::DATA_VERSION,
				'sections' => array(),
			);
		}

		// Parse sections into Rule/Section_Rule objects.
		$parsed_sections = array();

		foreach ( $rules_data['sections'] as $section_id => $section_data ) {
			$parsed_section = array(
				'section_rule' => null,
				'field_rules'  => array(),
			);

			// Parse section rule if exists.
			if ( ! empty( $section_data['section_rule'] ) && is_array( $section_data['section_rule'] ) ) {
				$section_data['section_rule']['section_id'] = $section_id;
				$parsed_section['section_rule']             = Section_Rule::from_array( $section_data['section_rule'] );
			}

			// Parse field rules.
			if ( ! empty( $section_data['field_rules'] ) && is_array( $section_data['field_rules'] ) ) {
				foreach ( $section_data['field_rules'] as $field_id => $field_rule_data ) {
					$field_rule_data['field_id']                = $field_id;
					$parsed_section['field_rules'][ $field_id ] = Rule::from_array( $field_rule_data );
				}
			}

			$parsed_sections[ $section_id ] = $parsed_section;
		}

		return array(
			'version'  => $rules_data['version'] ?? self::DATA_VERSION,
			'sections' => $parsed_sections,
		);
	}

	/**
	 * Check if checkout has any rules (field rules or section rules).
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return bool True if has rules, false otherwise.
	 */
	public static function has_rules( $checkout_id ) {
		$checkout_id = absint( $checkout_id );

		if ( ! $checkout_id ) {
			return false;
		}

		// Use cached raw rules data.
		$rules_data = self::get_raw_rules_data( $checkout_id );

		if ( ! is_array( $rules_data ) ) {
			return false;
		}

		// Check for section rules.
		if ( ! empty( $rules_data['sections'] ) ) {
			foreach ( $rules_data['sections'] as $section_data ) {
				if ( ! empty( $section_data['section_rule'] ) ) {
					return true;
				}
				if ( ! empty( $section_data['field_rules'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get enabled rules count for a checkout.
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return int Number of enabled rules.
	 */
	public static function get_enabled_rules_count( $checkout_id ) {
		$rules         = self::get_rules( $checkout_id );
		$enabled_count = 0;

		foreach ( $rules as $rule ) {
			if ( $rule instanceof Rule && $rule->is_enabled() ) {
				++$enabled_count;
			}
		}

		return $enabled_count;
	}

	/**
	 * Get all checkout IDs that have rules.
	 *
	 * @return array Array of checkout IDs.
	 */
	public static function get_checkouts_with_rules() {
		global $wpdb;

		// Query posts that have our meta key.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_KEY
			)
		);

		return array_map( 'absint', $results );
	}

	/**
	 * Export rules for a checkout (for future Phase 2).
	 *
	 * @param int $checkout_id Checkout page ID.
	 * @return string JSON string of rules.
	 */
	public static function export_rules( $checkout_id ) {
		$rules_data = get_post_meta( $checkout_id, self::META_KEY, true );

		if ( ! $rules_data ) {
			return wp_json_encode( array() );
		}

		return wp_json_encode( $rules_data, JSON_PRETTY_PRINT );
	}

	/**
	 * Import rules for a checkout (for future Phase 2).
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $json_data JSON string of rules.
	 * @return bool True on success, false on failure.
	 */
	public static function import_rules( $checkout_id, $json_data ) {
		$rules_data = json_decode( $json_data, true, 10 );

		if ( ! is_array( $rules_data ) ) {
			return false;
		}

		// Validate structure.
		if ( ! isset( $rules_data['version'] ) || ! isset( $rules_data['fields'] ) ) {
			return false;
		}

		// Update post meta.
		$result = update_post_meta( $checkout_id, self::META_KEY, $rules_data );

		// Clear cache.
		Cache_Manager::delete_rules( $checkout_id );

		return false !== $result;
	}

	/**
	 * Migrate v1.0 data structure to v2.0.
	 *
	 * @param array $v1_data Old v1.0 rules data.
	 * @return array New v2.0 rules data.
	 */
	private static function migrate_v1_to_v2( $v1_data ) {
		$v2_data = array(
			'version'  => self::DATA_VERSION,
			'sections' => array(),
		);

		// Check if v1 data has fields.
		if ( ! isset( $v1_data['fields'] ) || ! is_array( $v1_data['fields'] ) ) {
			return $v2_data;
		}

		// Group fields by section.
		foreach ( $v1_data['fields'] as $field_id => $field_rule_data ) {
			$section_id = self::get_section_for_field( $field_id );

			// Ensure section exists.
			if ( ! isset( $v2_data['sections'][ $section_id ] ) ) {
				$v2_data['sections'][ $section_id ] = array(
					'section_rule' => null,
					'field_rules'  => array(),
				);
			}

			// Add field rule to section.
			$v2_data['sections'][ $section_id ]['field_rules'][ $field_id ] = $field_rule_data;
		}

		return $v2_data;
	}

	/**
	 * Get section for a field ID (helper method for v2.0).
	 *
	 * @param string $field_id Field ID.
	 * @return string Section ID.
	 */
	private static function get_section_for_field( $field_id ) {
		if ( strpos( $field_id, 'billing_' ) === 0 ) {
			return 'billing';
		}
		if ( strpos( $field_id, 'shipping_' ) === 0 ) {
			return 'shipping';
		}
		if ( strpos( $field_id, 'account_' ) === 0 ) {
			return 'account';
		}
		if ( strpos( $field_id, 'order_' ) === 0 ) {
			return 'order';
		}
		return 'advanced';
	}

	/**
	 * Get section rule.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $section_id Section ID.
	 * @return Section_Rule|null Section rule object or null if not found.
	 */
	public static function get_section_rule( $checkout_id, $section_id ) {
		$checkout_id = absint( $checkout_id );
		$section_id  = sanitize_text_field( $section_id );

		if ( ! $checkout_id || ! $section_id ) {
			return null;
		}

		// Use cached raw rules data.
		$rules_data = self::get_raw_rules_data( $checkout_id );

		// Check if v2.0 structure and section exists.
		if ( isset( $rules_data['sections'][ $section_id ]['section_rule'] ) ) {
			$section_rule_data = $rules_data['sections'][ $section_id ]['section_rule'];

			if ( ! empty( $section_rule_data ) ) {
				return Section_Rule::from_array( $section_rule_data );
			}
		}

		return null;
	}

	/**
	 * Get section rule by matching section name or dom_section_id to rules keys.
	 * Rules may be stored under custom IDs (e.g. rule-country-us-company--ssn) that don't match
	 * get_fkcf_key_from_section_name output. Try each rules key with section_rule and match by
	 * normalized section name.
	 *
	 * @since 2.6.0
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $section_name Section display name.
	 * @param string $dom_section_id DOM section ID (e.g. single_step_fieldset_11).
	 * @return Section_Rule|null Section rule or null if not found.
	 */
	public static function get_section_rule_by_section_match( $checkout_id, $section_name, $dom_section_id ) {
		$checkout_id = absint( $checkout_id );
		if ( ! $checkout_id ) {
			return null;
		}

		$rules_data = self::get_raw_rules_data( $checkout_id );
		if ( ! is_array( $rules_data ) || empty( $rules_data['sections'] ) ) {
			return null;
		}

		$name_key        = Field_Conditions_Handler::get_fkcf_key_from_section_name( $section_name );
		$normalized_name = str_replace( '--', '-', $name_key );

		foreach ( $rules_data['sections'] as $rules_key => $section_data ) {
			if ( empty( $section_data['section_rule'] ) ) {
				continue;
			}
			$normalized_rules = str_replace( '--', '-', $rules_key );
			if ( $rules_key === $dom_section_id || $rules_key === $name_key
				|| $normalized_rules === $normalized_name ) {
				return self::get_section_rule( $checkout_id, $rules_key );
			}
		}

		return null;
	}

	/**
	 * Save section rule.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $section_id Section ID.
	 * @param array  $rule_data Section rule data.
	 * @return bool True on success, false on failure.
	 */
	public static function save_section_rule( $checkout_id, $section_id, $rule_data ) {
		$checkout_id = absint( $checkout_id );
		$section_id  = sanitize_text_field( $section_id );

		if ( ! $checkout_id || ! $section_id ) {
			return false;
		}

		// Add section_id to rule data.
		$rule_data['section_id'] = $section_id;

		// Create Section_Rule object.
		$section_rule = Section_Rule::from_array( $rule_data );

		if ( ! $section_rule ) {
			return false;
		}

		// Get existing rules data (raw).
		$rules_data = get_post_meta( $checkout_id, self::META_KEY, true );

		// Migrate v1.0 to v2.0 if needed.
		if ( is_array( $rules_data ) && isset( $rules_data['fields'] ) && ! isset( $rules_data['sections'] ) ) {
			$rules_data = self::migrate_v1_to_v2( $rules_data );
		}

		// Initialize v2.0 structure if empty or invalid.
		if ( ! is_array( $rules_data ) || ! isset( $rules_data['sections'] ) ) {
			$rules_data = array(
				'version'  => self::DATA_VERSION,
				'sections' => array(),
			);
		}

		// Ensure section exists.
		if ( ! isset( $rules_data['sections'][ $section_id ] ) ) {
			$rules_data['sections'][ $section_id ] = array(
				'section_rule' => null,
				'field_rules'  => array(),
			);
		}

		// Update section rule.
		$rules_data['sections'][ $section_id ]['section_rule'] = $section_rule->to_array();

		// Save to database.
		$result = update_post_meta( $checkout_id, self::META_KEY, $rules_data );

		// Clear cache.
		Cache_Manager::delete_rules( $checkout_id );

		return true;
	}

	/**
	 * Delete section rule.
	 *
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $section_id Section ID.
	 * @return bool True on success, false if not found.
	 */
	public static function delete_section_rule( $checkout_id, $section_id ) {
		$checkout_id = absint( $checkout_id );
		$section_id  = sanitize_text_field( $section_id );

		if ( ! $checkout_id || ! $section_id ) {
			return false;
		}

		// Get existing rules data.
		$rules_data = get_post_meta( $checkout_id, self::META_KEY, true );

		// Check if section rule exists.
		if ( isset( $rules_data['sections'][ $section_id ]['section_rule'] ) ) {
			$rules_data['sections'][ $section_id ]['section_rule'] = null;

			// Save to database.
			update_post_meta( $checkout_id, self::META_KEY, $rules_data );

			// Clear cache.
			Cache_Manager::delete_rules( $checkout_id );

			return true;
		}

		return false;
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
