<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_GR_WC' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_GR_WC {
		public function __construct() {

			/* checkout page */
			add_filter( 'wfacp_advanced_fields', array( $this, 'add_fields' ) );
			add_action( 'wfacp_internal_css', array( $this, 'gr_field_register' ) );
			/* prevent third party fields and wrapper*/

			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );
		}

		public function gr_field_register() {

			add_filter( 'woocommerce_form_field_args', array( $this, 'add_default_wfacp_styling' ), 10, 2 );
		}

		public function add_fields( $field ) {
			$field['gr_checkout_checkbox'] = array(
				'type'          => 'checkbox',
				'default'       => true,
				'label'         => 'GetResponse',
				'validate'      => array(),
				'id'            => 'gr_checkout_checkbox',
				'required'      => false,
				'wrapper_class' => array(),
				'class'         => array( 'gr-wc-checkbox' ),
			);

			return $field;
		}

		public function add_default_wfacp_styling( $args, $key ) {

			$default        = false;
			$checkout_label = __( 'Sign up to our newsletter!', 'funnel-builder' );

			if ( $key == 'gr_checkout_checkbox' ) {
				if ( function_exists( 'gr_get_option' ) ) {
					$checked = gr_get_option( 'checkout_checked' );
					if ( $checked ) {
						$default = true;
					}

					$checkout_label_text = gr_get_option( 'checkout_label' );

					if ( isset( $checkout_label_text ) && $checkout_label_text != '' ) {
						$checkout_label = $checkout_label_text;
					}
				}

				$args['label']       = $checkout_label;
				$args['default']     = $default;
				$args['input_class'] = array( 'GR_checkoutbox' );

			}

			return $args;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_GR_WC(), 'gr-wc' );
}
