<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFFN_Optin_Simple_Cloudflare_Turnstile' ) ) {
	class WFFN_Optin_Simple_Cloudflare_Turnstile {

		public function __construct() {
			add_action( 'wfopp_inside_form_before_submit', array( $this, 'render_turnstile_widget' ), 10, 3 );
			add_filter( 'wffn_optin_captcha_verification', array( $this, 'verify_turnstile' ), 10, 2 );
			add_action( 'wp_head', array( $this, 'add_css' ) );
		}

		public function render_turnstile_widget( $optin_page_id, $optin_settings, $form_controller ) {
			if ( ! function_exists( 'cfturnstile_field_show' ) ) {
				return;
			}

			cfturnstile_field_show( '', '', 'wffn-optin', '-wffn-optin-' . absint( $optin_page_id ), 'sct-wffn-optin' );
		}

		public function verify_turnstile( $response, $posted_data ) {
			if ( ! function_exists( 'cfturnstile_check' ) ) {
				return $response;
			}

			$token  = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$result = cfturnstile_check( $token );

			if ( ! is_array( $result ) || empty( $result['success'] ) ) {
				return array(
					'success' => false,
					'message' => __( 'Turnstile verification failed. Please try again.', 'funnel-builder' ),
				);
			}

			return $response;
		}

		public function add_css() {
			if ( ! function_exists( 'WFOPP_Core' ) || ! isset( WFOPP_Core()->optin_pages ) || ! WFOPP_Core()->optin_pages->is_wfop_page() ) {
				return;
			}
			?>
			<style>.wffn-optin-form .cf-turnstile { margin-bottom: 10px; }</style>
			<?php
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Optin_Simple_Cloudflare_Turnstile(), 'simple_cloudflare_turnstile' );
}
