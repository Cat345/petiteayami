<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: WooCommerce Checkout Field Editor by WooCommerce (1.7.13)
 */
if ( ! class_exists( 'WFACP_Woocommerce_Checkout_Field_Editor' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Woocommerce_Checkout_Field_Editor {

		public function __construct() {
			/**
			 * Add Field for advanced field using filter hook
			 */
			add_filter( 'wfacp_advanced_order_fields', array( $this, 'add_advanced_fields' ) );

			add_filter( 'wfacp_detect_extra_fields', array( $this, 'detect_extra_fields' ), 9, 3 );

			/**
			 * Persist additional-section custom fields to the order. The plugin's own save_data()
			 * reads from $posted (WC get_posted_data), but Aero replaces woocommerce_checkout_fields
			 * with its own set and drops the additional section, so $posted lacks them and the value
			 * is lost. The value IS in $_POST, so save it from there.
			 */
			add_action( 'woocommerce_checkout_create_order', array( $this, 'save_advanced_fields_to_order' ), 20, 2 );
		}

		/**
		 * Save WooCommerce Checkout Field Editor "additional" custom fields to the order from $_POST,
		 * using the field name as the meta key (what the plugin reads to print it on the thank-you page).
		 *
		 * @param WC_Order $order
		 * @param array    $data
		 *
		 * @return void
		 */
		public function save_advanced_fields_to_order( $order, $data ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$additional = get_option( 'wc_fields_additional', array() );
			if ( ! is_array( $additional ) ) {
				return;
			}

			foreach ( $additional as $name => $field ) {
				if ( empty( $field['custom'] ) ) {
					continue;
				}
				if ( ! isset( $_POST[ $name ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					continue;
				}
				if ( '' !== (string) $order->get_meta( $name ) ) {
					continue;
				}
				$value = wc_clean( wp_unslash( $_POST[ $name ] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				if ( '' === $value || array() === $value ) {
					continue;
				}
				$order->update_meta_data( $name, $value );
			}
		}

		public function add_advanced_fields( $fields ) {
			if ( ! function_exists( 'wc_checkout_fields_modify_order_fields' ) ) {
				return $fields;
			}

			if ( isset( $fields['order'] ) || count( $fields['order'] ) == 0 ) {
				$temp = wc_checkout_fields_modify_order_fields( $fields );
				if ( isset( $temp['order'] ) ) {
					$fields['order'] = $temp['order'];
				}
			}

			return $fields;
		}

		public function detect_extra_fields( $fields, $others_fields, $temp ) {

			if ( is_array( $fields ) && count( $fields ) > 0 ) {
				foreach ( $fields as $key => $value ) {

					if ( false !== strpos( $value['id'], '_wc_custom_field' ) && ! in_array( $value['id'], $temp ) ) {
						if ( is_array( $others_fields ) && count( $others_fields ) > 0 ) {
							foreach ( $others_fields as $k => $v ) {
								if ( false !== strpos( $k, 'shipping_' ) ) {
									$v['class'][] = 'wfacp_shipping_field_hide';
									$v['class'][] = 'wfacp_shipping_fields';
								} else {
									$v['class'][] = 'wfacp_billing_field_hide';
									$v['class'][] = 'wfacp_billing_fields';
								}

								$fields[] = $v;
							}
						}
					}
				}
			}

			return $fields;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Woocommerce_Checkout_Field_Editor(), 'woocommerce-checkout-field-editor' );
}
