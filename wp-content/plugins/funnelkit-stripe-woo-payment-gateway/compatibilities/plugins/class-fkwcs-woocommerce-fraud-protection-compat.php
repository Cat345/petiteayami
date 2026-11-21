<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Fraud Protection by Automattic
 * https://github.com/woocommerce/woocommerce-fraud-protection
 *
 * Fraud Protection verifies every order against its Blackbox session, whose ID its scripts add when the checkout or
 * order-pay form is submitted. Express wallet payments (Apple Pay, Google Pay, Link, Amazon Pay) are sent by our own
 * request, on product and cart pages its script is not loaded at all, and on the order-pay page our wallets submit the
 * form with jQuery, which fires no native submit event. The order arrived without a session ID
 * (session_id_error "unknown") and was blocked.
 *
 * Load Fraud Protection's script wherever our express buttons are shown, and hand its session ID to our express payment
 * request and to the order-pay form.
 */
if ( ! class_exists( 'FKWCS_Compat_WooCommerce_Fraud_Protection' ) ) {
	class FKWCS_Compat_WooCommerce_Fraud_Protection {
		public function __construct() {
			// Footer: every template and button has enqueued its scripts by then (mini cart, order-pay template)
			add_action( 'wp_footer', array( $this, 'add_session_script' ), 1 );
		}

		/**
		 * Add the session script after Fraud Protection's own script, on pages where our express buttons or wallets are used.
		 *
		 * @return void
		 */
		public function add_session_script() {
			$protector = '\Automattic\WooCommerce\Internal\FraudProtectionPlugin\Protectors\ShortcodeCheckoutProtector';
			if ( ! class_exists( $protector ) || ! function_exists( 'wc_get_container' ) ) {
				return;
			}

			if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
				// Fraud Protection loads its own pay-for-order script there
				$handle = 'wc-fraud-protection-pay-for-order';
				if ( ! wp_script_is( 'fkwcs-stripe-js', 'enqueued' ) ) {
					return;
				}
			} else {
				$handle = 'wc-fraud-protection-shortcode-checkout';
				if ( ! wp_script_is( 'fkwcs-express-checkout-js', 'enqueued' ) ) {
					return;
				}
				try {
					$instance = wc_get_container()->get( $protector );
					if ( method_exists( $instance, 'enqueue_shortcode_checkout_script' ) ) {
						$instance->enqueue_shortcode_checkout_script();
					}
				} catch ( \Throwable $e ) {
					return;
				}
			}

			if ( wp_script_is( $handle, 'enqueued' ) ) {
				wp_add_inline_script( $handle, $this->get_session_script() );
			}
		}

		/**
		 * Hand Fraud Protection's prepared session ID to the payments our own code sends, the way its express checkout
		 * script does for WooPayments and Stripe: a session is kept ready, each payment takes it once, and Fraud
		 * Protection starts the next one. When none is ready yet, its "timeout" reason is sent instead.
		 * Order-pay: our wallets submit the form with jQuery, which Fraud Protection's pay-for-order script does not see,
		 * so the ID is added on that submit. Elsewhere: it is added to our express payment request.
		 *
		 * @return string
		 */
		private function get_session_script() {
			$lines = array(
				'( function ( $ ) {',
				'	var fraudProtection = window.wcFraudProtection;',
				'	if ( ! $ || ! fraudProtection || ! fraudProtection.config || "function" !== typeof fraudProtection.prepareSessionId || "function" !== typeof fraudProtection.takePreparedSessionId ) {',
				'		return;',
				'	}',
				'	var config = fraudProtection.config;',
				'	var endpoints = [ "wc_stripe_create_order", "fkwcs_button_payment_request", "fkwcs_gpay_button_payment_request" ];',
				'	var $payForm = $( "form#order_review" );',
				'	if ( $payForm.length ) {',
				'		$payForm.on( "submit", function () {',
				'			if ( document.getElementById( config.sessionIdField ) ) {',
				'				return;',
				'			}',
				'			var sessionId = fraudProtection.takePreparedSessionId();',
				'			if ( sessionId ) {',
				'				$( "<input>", { type: "hidden", name: config.sessionIdField, id: config.sessionIdField, value: sessionId } ).appendTo( this );',
				'			} else if ( config.sessionIdErrorField && ! $( this ).find( "input[name=\"" + config.sessionIdErrorField + "\"]" ).length ) {',
				'				$( "<input>", { type: "hidden", name: config.sessionIdErrorField, value: "timeout" } ).appendTo( this );',
				'			}',
				'		} );',
				'	} else {',
				'		$.ajaxPrefilter( function ( options ) {',
				'			var wcAjax;',
				'			try {',
				'				wcAjax = new URL( options.url, window.location.href ).searchParams.get( "wc-ajax" );',
				'			} catch ( e ) {',
				'				return;',
				'			}',
				'			if ( -1 === endpoints.indexOf( wcAjax ) || "string" !== typeof options.data ) {',
				'				return;',
				'			}',
				'			var fields = {};',
				'			fields[ config.sessionIdField ] = fraudProtection.takePreparedSessionId();',
				'			if ( ! fields[ config.sessionIdField ] && config.sessionIdErrorField ) {',
				'				fields[ config.sessionIdErrorField ] = "timeout";',
				'			}',
				'			options.data += ( options.data ? "&" : "" ) + $.param( fields );',
				'		} );',
				'	}',
				'	fraudProtection.prepareSessionId();',
				'} )( window.jQuery );',
			);

			return implode( "\n", $lines );
		}
	}

	new FKWCS_Compat_WooCommerce_Fraud_Protection();
}
