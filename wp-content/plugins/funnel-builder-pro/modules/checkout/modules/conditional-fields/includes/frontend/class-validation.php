<?php
/**
 * Validation
 *
 * Handles validation management for hidden fields.
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
 * Validation class.
 *
 * @since 2.0.0
 */
class Validation {

	/**
	 * Singleton instance.
	 *
	 * @var Validation
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.0.0
	 * @return Validation
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
		add_filter( 'wfacp_checkout_posted_data', array( $this, 'remove_hidden_field_validation' ), 10 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'unrequire_hidden_fields' ), 9999 );
	}

	/**
	 * Remove hidden fields from validation.
	 *
	 * @since 2.0.0
	 * @param array $data Posted checkout data.
	 * @return array Modified checkout data.
	 */
	public function remove_hidden_field_validation( $data ) {
		// Get current checkout ID.
		$checkout_id = $this->get_current_checkout_id();

		if ( ! $checkout_id ) {
			return $data;
		}

		// Check if checkout has rules.
		if ( ! Rule_Storage::has_rules( $checkout_id ) ) {
			return $data;
		}

		// Get all hidden fields (including fields in hidden sections).
		$hidden_fields = $this->get_all_hidden_fields( $checkout_id );

		if ( empty( $hidden_fields ) ) {
			return $data;
		}

		// Remove hidden fields from posted data.
		foreach ( $hidden_fields as $field_id ) {
			if ( isset( $data[ $field_id ] ) ) {
				unset( $data[ $field_id ] );
			}
		}

		return $data;
	}

	/**
	 * Make hidden fields not required so WooCommerce does not validate them on process checkout.
	 *
	 * @since 2.0.0
	 * @param array $fields Checkout fields by section.
	 * @return array Modified checkout fields.
	 */
	public function unrequire_hidden_fields( $fields ) {
		$checkout_id = $this->get_current_checkout_id();

		if ( ! $checkout_id ) {
			return $fields;
		}

		if ( ! Rule_Storage::has_rules( $checkout_id ) ) {
			return $fields;
		}

		// Get all hidden fields (including fields in hidden sections).
		$hidden_fields = $this->get_all_hidden_fields( $checkout_id );

		if ( empty( $hidden_fields ) ) {
			return $fields;
		}

		$hidden_keys = array_flip( $hidden_fields );

		foreach ( $fields as $section => $section_fields ) {
			if ( ! is_array( $section_fields ) ) {
				continue;
			}
			foreach ( $section_fields as $key => $field ) {
				if ( isset( $hidden_keys[ $key ] ) && is_array( $field ) ) {
					$fields[ $section ][ $key ]['required'] = false;
				}
			}
		}

		return $fields;
	}

	/**
	 * Get all hidden fields, including fields in hidden sections.
	 *
	 * @since 2.5.0
	 * @param int $checkout_id Checkout page ID.
	 * @return array Array of hidden field IDs.
	 */
	private function get_all_hidden_fields( $checkout_id ) {
		$engine         = Rule_Engine::get_instance();
		$visibility_map = $engine->get_hierarchical_visibility_map( $checkout_id );

		$hidden_fields = array();

		// Get fields hidden by field rules.
		if ( ! empty( $visibility_map['fields'] ) && is_array( $visibility_map['fields'] ) ) {
			foreach ( $visibility_map['fields'] as $field_id => $is_visible ) {
				if ( false === $is_visible ) {
					$hidden_fields[] = $field_id;
				}
			}
		}

		// Get fields in hidden sections.
		if ( ! empty( $visibility_map['sections'] ) && is_array( $visibility_map['sections'] ) ) {
			$hidden_sections = array();
			foreach ( $visibility_map['sections'] as $section_id => $is_visible ) {
				if ( false === $is_visible ) {
					$hidden_sections[] = $section_id;
				}
			}

			// If there are hidden sections, get all fields that belong to them.
			if ( ! empty( $hidden_sections ) ) {
				$section_fields = $this->get_fields_in_hidden_sections( $checkout_id, $hidden_sections );
				$hidden_fields  = array_merge( $hidden_fields, $section_fields );
			}
		}

		return array_unique( $hidden_fields );
	}

	/**
	 * Get all field IDs that belong to hidden sections.
	 *
	 * @since 2.5.0
	 * @param int   $checkout_id     Checkout page ID.
	 * @param array $hidden_sections Array of hidden section IDs.
	 * @return array Array of field IDs in hidden sections.
	 */
	private function get_fields_in_hidden_sections( $checkout_id, $hidden_sections ) {
		$fields_in_sections = array();

		if ( ! class_exists( '\WFACP_Common' ) ) {
			return $fields_in_sections;
		}

		$layout = \WFACP_Common::get_page_layout( $checkout_id );

		if ( empty( $layout['fieldsets'] ) || ! is_array( $layout['fieldsets'] ) ) {
			return $fields_in_sections;
		}

		// Build a map of section IDs to their field IDs.
		foreach ( $layout['fieldsets'] as $step => $sections ) {
			if ( ! is_array( $sections ) ) {
				continue;
			}
			foreach ( $sections as $section_index => $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}

				// Get the DOM section ID (same logic as Section_Visibility).
				$dom_section_id = isset( $section['id'] ) && is_string( $section['id'] ) && '' !== trim( $section['id'] )
					? sanitize_html_class( $section['id'] )
					: $step . '_fieldset_' . $section_index;

				// Check if this section is hidden.
				if ( ! in_array( $dom_section_id, $hidden_sections, true ) ) {
					continue;
				}

				// Get all fields in this section.
				if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field ) {
						$field_id = '';
						if ( is_array( $field ) && ! empty( $field['id'] ) ) {
							$field_id = $field['id'];
						} elseif ( is_string( $field ) ) {
							$field_id = $field;
						}

						if ( '' === $field_id ) {
							continue;
						}

						// Expand composite address fields to individual WooCommerce field IDs.
						$expanded           = $this->expand_composite_field( $field_id );
						$fields_in_sections = array_merge( $fields_in_sections, $expanded );
					}
				}
			}
		}

		return $fields_in_sections;
	}

	/**
	 * Expand composite field IDs to individual WooCommerce field IDs.
	 *
	 * @since 2.5.1
	 * @param string $field_id The field ID from the layout.
	 * @return array Array of expanded field IDs.
	 */
	private function expand_composite_field( $field_id ) {
		// Billing address composite field.
		if ( 'address' === $field_id ) {
			return $this->get_address_field_ids( 'billing' );
		}

		// Shipping address composite field.
		if ( 'shipping-address' === $field_id ) {
			return $this->get_address_field_ids( 'shipping' );
		}

		// Not a composite field, return as-is.
		return array( $field_id );
	}

	/**
	 * Get all address-related field IDs for a given prefix.
	 *
	 * Gets fields from WooCommerce checkout that belong to the address composite.
	 * Only includes address-specific fields, not all billing/shipping fields.
	 *
	 * @since 2.5.1
	 * @param string $prefix The field prefix ('billing' or 'shipping').
	 * @return array Array of field IDs.
	 */
	private function get_address_field_ids( $prefix ) {
		// Core address field suffixes that are part of the address composite.
		$address_suffixes = array(
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'postcode',
			'country',
			'state',
			'phone',
		);

		$field_ids = array();

		// Add core address fields.
		foreach ( $address_suffixes as $suffix ) {
			$field_ids[] = $prefix . '_' . $suffix;
		}

		// Also include any third-party fields that have address_group marker.
		if ( function_exists( 'WC' ) && WC()->checkout() ) {
			$checkout_fields = WC()->checkout()->get_checkout_fields( $prefix );
			if ( ! empty( $checkout_fields ) && is_array( $checkout_fields ) ) {
				foreach ( $checkout_fields as $key => $field ) {
					// Include fields marked as address_group or with class 'address-field'.
					$is_address_field = false;
					if ( isset( $field['address_group'] ) && $field['address_group'] ) {
						$is_address_field = true;
					}
					if ( isset( $field['class'] ) && is_array( $field['class'] ) && in_array( 'address-field', $field['class'], true ) ) {
						$is_address_field = true;
					}
					if ( $is_address_field && ! in_array( $key, $field_ids, true ) ) {
						$field_ids[] = $key;
					}
				}
			}
		}

		return $field_ids;
	}

	/**
	 * Get current checkout ID.
	 * Uses same fallbacks as Fragments so it works on update_order_review and process_checkout.
	 *
	 * @since 2.0.0
	 * @return int Checkout ID or 0.
	 */
	private function get_current_checkout_id() {
		if ( class_exists( 'WFACP_Common' ) && method_exists( 'WFACP_Common', 'get_id' ) ) {
			$id = absint( \WFACP_Common::get_id() );
			if ( $id > 0 ) {
				return $id;
			}
		}

		// Fallback: during AJAX (update_order_review / process_checkout), checkout ID may be in POST.
		if ( wp_doing_ajax() && isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$post_data = array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsing form query string; _wfacp_post_id is absint below.
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data );
			if ( ! empty( $post_data['_wfacp_post_id'] ) ) {
				return absint( $post_data['_wfacp_post_id'] );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reading checkout ID for validation context; value is absint.
		if ( wp_doing_ajax() && ! empty( $_POST['_wfacp_post_id'] ) ) {
			return absint( wp_unslash( $_POST['_wfacp_post_id'] ) );
		}

		global $post;
		if ( $post && 'wfacp_checkout' === get_post_type( $post ) ) {
			return absint( $post->ID );
		}

		return 0;
	}
}
