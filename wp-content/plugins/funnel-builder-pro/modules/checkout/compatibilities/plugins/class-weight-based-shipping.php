<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility: Weight Based Shipping for WooCommerce.
 *
 * WBS forces wc_shipping_method_count >= 1, keeping the shipping section visible.
 * Two validation gaps result:
 *  1. WC skips the entire shipping fieldset when ship_to_different_address is absent
 *     from POST (FunnelKit's single-form layout never posts it).
 *  2. FunnelKit sets required=false for phone fields in WC checkout fields so
 *     intl-tel-input can own validation — but FunnelKit's own process_phone_field()
 *     only checks format, never required.
 *
 * Fix: after woocommerce_checkout_process (before WC's notice check), walk every
 * required field in FunnelKit's config. Skip any field WC will already validate on
 * its own (required=true in WC fields AND in a fieldset WC won't skip) to avoid
 * duplicate notices. Enforce everything else.
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Weight_Based_Shipping' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Weight_Based_Shipping {

		public function __construct() {
			add_action( 'woocommerce_checkout_process', array( $this, 'require_fields' ), 20 );
		}

		public function require_fields() {
			if ( ! class_exists( 'WFACP_Common' ) ) {
				return;
			}
			$id = WFACP_Common::get_id();
			if ( empty( $id ) ) {
				return;
			}

			$all_fields = WFACP_Common::get_checkout_fields( $id );
			if ( empty( $all_fields ) ) {
				return;
			}

			// WC skips the shipping fieldset when ship_to_different_address is absent.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$wc_skips_shipping = empty( $_POST['ship_to_different_address'] );

			// WC's field definitions with all filters applied (includes FunnelKit's required=false overrides).
			$wc_fields = function_exists( 'WC' ) && WC()->checkout() ? WC()->checkout()->get_checkout_fields() : array();

			// intl-tel-input hidden-state JSON for phone fields.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$intl = isset( $_POST['wfacp_input_phone_field'] ) ? json_decode( stripslashes_deep( wp_unslash( $_POST['wfacp_input_phone_field'] ) ), true ) : array();

			foreach ( $all_fields as $fieldset_key => $fields ) {
				if ( ! is_array( $fields ) ) {
					continue;
				}

				$fieldset_skipped_by_wc = ( 'shipping' === $fieldset_key && $wc_skips_shipping );

				foreach ( $fields as $field_key => $field ) {
					if ( empty( $field['required'] ) || ! wc_string_to_bool( $field['required'] ) ) {
						continue;
					}

					// If WC marks this field required AND its fieldset isn't skipped, WC will
					// enforce it — skip here to avoid a duplicate notice.
					$wc_field_required = ! empty( $wc_fields[ $fieldset_key ][ $field_key ]['required'] );
					if ( $wc_field_required && ! $fieldset_skipped_by_wc ) {
						continue;
					}

					// For intl-tel-input phone fields, honour the hidden flag (field collapsed).
					if ( in_array( $field_key, array( 'billing_phone', 'shipping_phone' ), true ) ) {
						$phone_type = str_replace( '_phone', '', $field_key );
						if ( isset( $intl[ $phone_type ]['hidden'] ) && 'yes' === $intl[ $phone_type ]['hidden'] ) {
							continue;
						}
					}

					// Skip fields not present in the submitted form data.
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					if ( ! isset( $_POST[ $field_key ] ) ) {
						continue;
					}

					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					if ( '' !== trim( wc_clean( wp_unslash( $_POST[ $field_key ] ) ) ) ) {
						continue;
					}

					$label   = isset( $field['label'] ) ? $field['label'] : $field_key;
					$message = sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $label ) . '</strong>' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch

					if ( ! ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) ) {
						wc_add_notice( $message, 'error' );
					}
				}
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Weight_Based_Shipping(), 'weight_based_shipping' );
}
