<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Google_captcha_Pro' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Google_captcha_Pro {


		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'actions' ) );
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'actions' ) );
			add_action( 'wfacp_internal_css', array( $this, 'wac_css_func' ) );
			add_action( 'wp_footer', array( $this, 'refresh_v3_token_script' ), 100 );
		}

		public function actions() {
			$is_user_logged_in = is_user_logged_in();

			if ( ! function_exists( 'gglcptch_pro_init' ) ) {
				return;
			}

			if ( ! gglcptch_is_recaptcha_required( 'woocommerce_checkout', $is_user_logged_in ) ) {
				return;

			}

			if ( ! function_exists( 'gglcptch_echo_recaptcha' ) ) {
				return;
			}

			remove_action( 'woocommerce_after_checkout_billing_form', 'gglcptch_echo_recaptcha', 10 );
			add_action( 'wfacp_woocommerce_review_order_before_submit', 'gglcptch_echo_recaptcha', 10, 0 );
		}

		public function refresh_v3_token_script() {
			if ( ! function_exists( 'wfacp_template' ) || ! wfacp_template() ) {
				return;
			}
			?>
			<script>
				( function ( $ ) {
					if ( ! $ ) { return; }
					$( document.body ).on( 'updated_checkout', function () {
						if ( typeof window.gglcptch === 'undefined' || typeof window.grecaptcha === 'undefined' || typeof gglcptch.prepare !== 'function' ) {
							return;
						}
						try {
							gglcptch.prepare();
						} catch ( e ) {}
					} );
				} )( window.jQuery );
			</script>
			<?php
		}

		public function wac_css_func( $selected_template_slug ) {

			?>
			<style>
				body .grecaptcha-badge {
					z-index: 2;
				}

			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Google_captcha_Pro(), 'gcp' );

}