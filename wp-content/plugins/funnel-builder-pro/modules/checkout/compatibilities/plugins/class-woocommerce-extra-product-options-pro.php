<?php
/**
 * Compatibility class for WooCommerce Extra Product Options Pro
 *
 * Fixes issue where extra option pricing is not applied when products (simple or variable)
 * are added via the FunnelKit checkout product switcher popup.
 *
 * @since 3.22.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_WooCommerce_Extra_Product_Options_Pro' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WooCommerce_Extra_Product_Options_Pro {

		/**
		 * Original $_POST backup
		 *
		 * @var array
		 */
		private $original_post_backup = array();

		/**
		 * Original $_REQUEST backup
		 *
		 * @var array
		 */
		private $original_request_backup = array();

		/**
		 * Constructor
		 */
		public function __construct() {
			// Hook into product switcher add-to-cart to capture and inject EPO form data
			add_filter( 'wfacp_switch_product_ajax_custom_data', array( $this, 'inject_epo_form_data' ), 10, 4 );
		}

		/**
		 * Inject EPO form data into $_POST before add_to_cart is called
		 *
		 * This ensures that EPO's woocommerce_add_cart_item_data hook can read
		 * the field values from $_POST and apply pricing correctly.
		 *
		 * @param array      $custom_data Custom cart item data
		 * @param array|null $remove_cart_item The cart item being removed (if any)
		 * @param WC_Product $product_obj The product being added
		 * @param array      $post The AJAX request data
		 * @return array Modified custom data
		 * @since 3.22.0
		 */
		public function inject_epo_form_data( $custom_data, $remove_cart_item, $product_obj, $post ) {
			// Parse form data from post_data (serialized checkout form data)
			// This captures ALL form fields including EPO's hidden tracking fields
			$form_data = array();
			if ( isset( $_POST['post_data'] ) && ! empty( $_POST['post_data'] ) ) {
				parse_str( wp_unslash( $_POST['post_data'] ), $form_data );
			}

			// IMPORTANT: The quick view form fields are already serialized and sent in $post directly
			// by set_variation_data() function in checkout.js (lines 242-257)
			// Merge them into form_data
			if ( ! empty( $post ) && is_array( $post ) ) {
				foreach ( $post as $key => $value ) {
					// Skip internal FunnelKit keys
					if ( in_array( $key, array( 'wfacp_id', 'new_item', 'remove_item_key', 'quantity', 'variation_id', 'field_type', 'product_id', 'attributes' ), true ) ) {
						continue;
					}
					// Add all other fields (including EPO fields) to form_data
					$form_data[ $key ] = $value;
				}
			}

			// Check if EPO custom fields are present before processing
			// EPO uses specific field names that start with common prefixes
			if ( ! $this->has_epo_fields( $form_data ) ) {
				return $custom_data;
			}

			// Store original $_POST and $_REQUEST to restore later
			$this->original_post_backup    = $_POST;
			$this->original_request_backup = $_REQUEST;

			// Merge form data into $_POST so EPO hooks can read it
			$_POST = array_merge( $_POST, $form_data );

			// Also merge into $_REQUEST since EPO may read from $_REQUEST
			$_REQUEST = array_merge( $_REQUEST, $form_data );

			// Ensure product_id and variation_id are set for EPO
			if ( isset( $post['product_id'] ) ) {
				$_POST['product_id']    = absint( $post['product_id'] );
				$_REQUEST['product_id'] = absint( $post['product_id'] );
			}
			if ( isset( $post['variation_id'] ) && $post['variation_id'] > 0 ) {
				$_POST['variation_id']    = absint( $post['variation_id'] );
				$_REQUEST['variation_id'] = absint( $post['variation_id'] );
			}

			// Ensure variation attributes are in $_POST for EPO
			if ( isset( $post['attributes'] ) && is_array( $post['attributes'] ) ) {
				foreach ( $post['attributes'] as $attr_key => $attr_value ) {
					$_POST[ $attr_key ]    = $attr_value;
					$_REQUEST[ $attr_key ] = $attr_value;
				}
			}

			// Hook into add_to_cart to restore $_POST after EPO processes it
			add_action( 'woocommerce_add_to_cart', array( $this, 'restore_post_data' ), 999, 6 );

			return $custom_data;
		}

		/**
		 * Check if EPO custom fields are present in form data
		 *
		 * EPO fields use specific naming patterns. This method checks for:
		 * - thwepo_product_fields: Hidden field tracking which EPO fields are active
		 * - Fields starting with common EPO prefixes (thwepof_, thwepo_)
		 *
		 * @param array $form_data The form data to check
		 * @return bool True if EPO fields are present, false otherwise
		 * @since 3.22.0
		 */
		private function has_epo_fields( $form_data ) {
			if ( empty( $form_data ) || ! is_array( $form_data ) ) {
				return false;
			}

			// Check for EPO's hidden tracking field (most reliable indicator)
			if ( isset( $form_data['thwepo_product_fields'] ) && ! empty( $form_data['thwepo_product_fields'] ) ) {
				return true;
			}

			// Check for any fields with EPO prefixes
			foreach ( array_keys( $form_data ) as $key ) {
				// EPO fields typically start with 'thwepof_' or 'thwepo_'
				if ( strpos( $key, 'thwepof_' ) === 0 || strpos( $key, 'thwepo_' ) === 0 ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Restore original $_POST data after add_to_cart
		 *
		 * @param string $cart_item_key Cart item key
		 * @param int    $product_id Product ID
		 * @param int    $quantity Quantity
		 * @param int    $variation_id Variation ID
		 * @param array  $variation Variation data
		 * @param array  $cart_item_data Cart item data
		 * @since 3.22.0
		 */
		public function restore_post_data( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
			if ( ! empty( $this->original_post_backup ) ) {
				$_POST                      = $this->original_post_backup;
				$this->original_post_backup = array();
			}
			if ( ! empty( $this->original_request_backup ) ) {
				$_REQUEST                      = $this->original_request_backup;
				$this->original_request_backup = array();
			}
			remove_action( 'woocommerce_add_to_cart', array( $this, 'restore_post_data' ), 999 );
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WooCommerce_Extra_Product_Options_Pro(), 'woocommerce-extra-product-options-pro' );
}
