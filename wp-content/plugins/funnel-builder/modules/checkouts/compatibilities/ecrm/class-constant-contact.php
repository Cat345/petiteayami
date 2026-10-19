<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Constant_Contact' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Constant_Contact {
		public function __construct() {
			add_filter( 'wfacp_advanced_fields', array( $this, 'add_fields' ) );
			add_action( 'wfacp_internal_css', array( $this, 'actions' ) );

			/* prevent third party fields and wrapper*/
			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );
		}

		public function actions() {
			add_filter( 'woocommerce_form_field_args', array( $this, 'add_default_wfacp_styling' ), 10, 2 );
		}

		public function add_fields( $field ) {
			if ( $this->is_enable() ) {
				$field['wc_constant_contact_subscribe'] = array(
					'type'          => 'checkbox',
					'default'       => false,
					'label'         => __( 'Constant Contact', 'woocommerce-constant-contact' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse the integrated plugin's translation.
					'validate'      => array(),
					'id'            => 'wc_constant_contact_subscribe',
					'required'      => false,
					'wrapper_class' => array(),
				);

			}

			return $field;
		}

		public function is_enable() {
			if ( function_exists( 'wc_constant_contact' ) ) {
				if ( ! wc_constant_contact()->get_api() || wc_constant_contact()->get_api()->customer_has_already_subscribed() ) {
					return false;
				} else {
					return true;
				}
			}

			return false;
		}

		public function add_default_wfacp_styling( $args, $key ) {
			if ( $key == 'wc_constant_contact_subscribe' && $this->is_enable() ) {
				$optin_text = get_option( 'wc_constant_contact_subscribe_checkbox_label' );
				if ( '' !== $optin_text ) {
					$args['label'] = $optin_text;
				}
				if ( ! empty( $_POST['wc_constant_contact_subscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Restores the subscribe checkbox state from the WC checkout POST during re-render; WooCommerce core has already verified its checkout nonce before this hook/filter runs.
					$value = ( 'yes' === $_POST['wc_constant_contact_subscribe'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Restores the subscribe checkbox state from the WC checkout POST during re-render; WooCommerce core has already verified its checkout nonce before this hook/filter runs.
				} else {
					$value = ( 'checked' === get_option( 'wc_constant_contact_subscribe_checkbox_default', 'unchecked' ) ) ? 1 : 0;
				}

				$args['default'] = $value;
			}

			return $args;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Constant_Contact(), 'constant_contact' );
}
