<?php
/**
 * Field_Visibility
 *
 * Handles filtering checkout fields based on conditional rules.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields\Frontend
 */

namespace FunnelKit\Checkout\Modules\Conditional_Fields\Frontend;

use FunnelKit\Checkout\Modules\Conditional_Fields\Storage\Rule_Storage;
use FunnelKit\Checkout\Modules\Conditional_Fields\Engine\Rule_Engine;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field_Visibility class.
 *
 * @since 2.0.0
 */
class Field_Visibility {

	/**
	 * Singleton instance.
	 *
	 * @var Field_Visibility
	 */
	private static $instance = null;

	/**
	 * Cached visibility map for current request (avoids re-evaluating per field).
	 *
	 * @var array|null
	 */
	private $visibility_map_cache = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Field_Visibility
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 */
	private function init_hooks() {
		// add_filter( 'wfacp_checkout_fields', array( $this, 'filter_checkout_fields' ), 999 );
		// FunnelKit form.php applies this filter before each field is rendered; use it to add class and attributes.
		add_filter( 'wfacp_forms_field', array( $this, 'add_field_attributes' ), 10, 2 );
	}

	/**
	 * Add class and data attribute to field args; add fkcf-hidden when field should be hidden.
	 * Used by FunnelKit checkout form.php (wfacp_forms_field filter).
	 *
	 * @since 2.3.0
	 * @param array  $field Field arguments (class, custom_attributes, etc.).
	 * @param string $key   Field key (e.g. billing_first_name).
	 * @return array Modified field args.
	 */
	public function add_field_attributes( $field, $key ) {
		if ( empty( $field ) || ! is_array( $field ) ) {
			return $field;
		}

		$checkout_id = $this->get_current_checkout_id();
		if ( ! $checkout_id || ! Rule_Storage::has_rules( $checkout_id ) ) {
			return $field;
		}

		if ( ! isset( $field['class'] ) || ! is_array( $field['class'] ) ) {
			$field['class'] = array();
		}
		$field['class'][] = 'fkcf-field';
		// Add field key as class so frontend JS can target via .{fieldId} (e.g. .billing_email).

		if ( ! ( $key === 'billing_same_as_shipping' || $key === 'shipping_same_as_billing' ) ) {
			$field['class'][] = sanitize_html_class( $key );
		}

		// Add fkcf-hidden server-side so field is hidden on first paint (no flash before JS runs).
		if ( $this->is_field_hidden_by_rules( $checkout_id, $key ) ) {
			$field['class'][] = 'fkcf-hidden';
		}

		if ( ! isset( $field['custom_attributes'] ) || ! is_array( $field['custom_attributes'] ) ) {
			$field['custom_attributes'] = array();
		}
		$field['custom_attributes']['data-fkcf-field-id'] = $key;

		return $field;
	}

	/**
	 * Whether the field should be hidden by conditional rules (uses cached visibility map).
	 *
	 * @since 2.3.0
	 * @param int    $checkout_id Checkout page ID.
	 * @param string $field_key   Field key (e.g. billing_first_name).
	 * @return bool True if hidden.
	 */
	private function is_field_hidden_by_rules( $checkout_id, $field_key ) {
		if ( null === $this->visibility_map_cache ) {
			$engine                     = Rule_Engine::get_instance();
			$this->visibility_map_cache = $engine->get_hierarchical_visibility_map( $checkout_id );
		}

		$fields = isset( $this->visibility_map_cache['fields'] ) ? $this->visibility_map_cache['fields'] : array();
		return isset( $fields[ $field_key ] ) && false === $fields[ $field_key ];
	}

	/**
	 * Filter checkout fields based on conditional rules.
	 *
	 * @since 2.0.0
	 * @param array $fields Checkout fields.
	 * @return array Filtered checkout fields.
	 */
	public function filter_checkout_fields( $fields ) {
		// Get current checkout ID.
		$checkout_id = $this->get_current_checkout_id();

		if ( ! $checkout_id ) {
			return $fields;
		}

		// Check if checkout has rules.
		if ( ! Rule_Storage::has_rules( $checkout_id ) ) {
			return $fields;
		}

		// Get hidden fields.
		$engine        = Rule_Engine::get_instance();
		$hidden_fields = $engine->get_hidden_fields( $checkout_id );

		if ( empty( $hidden_fields ) ) {
			return $fields;
		}

		// Remove hidden fields from checkout.
		$fields = $this->remove_hidden_fields( $fields, $hidden_fields );

		return $fields;
	}

	/**
	 * Remove hidden fields from checkout fields array.
	 *
	 * @since 2.0.0
	 * @param array $fields Checkout fields.
	 * @param array $hidden_fields Hidden field IDs.
	 * @return array Filtered fields.
	 */
	private function remove_hidden_fields( $fields, $hidden_fields ) {
		$sections = array( 'billing', 'shipping', 'account', 'order' );

		foreach ( $sections as $section ) {
			if ( ! isset( $fields[ $section ] ) || ! is_array( $fields[ $section ] ) ) {
				continue;
			}

			foreach ( $hidden_fields as $field_id ) {
				// Check if field exists in this section.
				if ( isset( $fields[ $section ][ $field_id ] ) ) {
					// Remove the field.
					unset( $fields[ $section ][ $field_id ] );
				}

				// Also check for custom fields (advanced section).
				if ( 'order' === $section && isset( $fields[ $section ][ $field_id ] ) ) {
					unset( $fields[ $section ][ $field_id ] );
				}
			}
		}

		// Handle custom/advanced fields registered by FunnelKit.
		if ( isset( $fields['advanced'] ) && is_array( $fields['advanced'] ) ) {
			foreach ( $hidden_fields as $field_id ) {
				if ( isset( $fields['advanced'][ $field_id ] ) ) {
					unset( $fields['advanced'][ $field_id ] );
				}
			}
		}

		return $fields;
	}

	/**
	 * Get current checkout ID.
	 *
	 * @since 2.0.0
	 * @return int Checkout ID or 0.
	 */
	private function get_current_checkout_id() {
		if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'get_id' ) ) {
			return absint( \WFACP_Common::get_id() );
		}

		global $post;
		if ( $post && 'wfacp_checkout' === get_post_type( $post ) ) {
			return absint( $post->ID );
		}

		return 0;
	}
}
