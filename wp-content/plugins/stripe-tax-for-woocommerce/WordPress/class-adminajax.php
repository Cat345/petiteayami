<?php
/**
 * Connections to the Stripe API functionality.
 *
 * @package Stripe\StripeTaxForWooCommerce\WordPress
 */

namespace Stripe\StripeTaxForWooCommerce\WordPress;

// Exit if script started not from WordPress.
defined( 'ABSPATH' ) || exit;

use Exception;
use stdClass;
use Stripe\StripeTaxForWooCommerce\SDK\lib\StripeClient;
use Stripe\StripeTaxForWooCommerce\Stripe\Validate;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTax;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxRegistrations;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxHealthCheck;

/**
 * Class provides AJAX functionality.
 */
class AdminAjax {
	/**
	 * Clear Stripe API connection settings.
	 *
	 * @throws Exception If something goes wrong.
	 */
	public static function disconnect_from_stripe() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		$data  = new stdClass();
		try {
			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'stripe_tax_for_woocommerce_disconnect_from_stripe' ) ) {
				throw new Exception( __( 'Form expired. Refresh page and try again.', 'stripe-tax-for-woocommerce' ) );
			}
			Options::delete_option( Options::OPTION_LIVE_MODE_SECRET_KEY );
			Options::enable_live_mode( false );
			$data->message = __( 'Disconnection successful. Refreshing page...', 'stripe-tax-for-woocommerce' );
			wp_send_json_success( $data, 200 );
		} catch ( \Throwable $e ) {
			$data->message = $e->getMessage();
			wp_send_json_error( $data, 200 );
		}
	}

	/**
	 * Persists the dismissal of the sandbox mode admin notice for the current user.
	 *
	 * @return void
	 */
	public static function dismiss_sandbox_notice() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		$data  = new stdClass();

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, Hooks::SANDBOX_NOTICE_DISMISS_ACTION ) ) {
			$data->message = __( 'Form expired. Refresh page and try again.', 'stripe-tax-for-woocommerce' );
			wp_send_json_error( $data, 403 );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_woocommerce is a capability registered by WooCommerce.
			$data->message = __( 'You are not allowed to perform this action.', 'stripe-tax-for-woocommerce' );
			wp_send_json_error( $data, 403 );
		}

		update_user_meta( get_current_user_id(), Hooks::SANDBOX_NOTICE_DISMISSED_META, Hooks::get_sandbox_notice_token() );
		wp_send_json_success( $data, 200 );
	}

	/**
	 * This function is used to test the current connection to the Stripe API.
	 *
	 * @param ?StripeClient $stripe_client An instance of a StripeClient to test the connection if any.
	 *
	 * @throws Exception If something goes wrong.
	 */
	public static function test_connection( ?StripeClient $stripe_client = null ) {
		$nonce  = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		$action = 'stripe_tax_for_woocommerce_test_connection_live_key';
		$data   = new stdClass();
		try {
			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
				throw new Exception( __( 'Form expired. Refresh page and try again.', 'stripe-tax-for-woocommerce' ) );
			}

			$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : Options::MODE_LIVE;
			$key  = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

			$stripe_validation = StripeTax::validate_keys( $mode, $key );
			if ( is_array( $stripe_validation ) && isset( $stripe_validation['error'] ) ) {
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				throw new Exception( __( $stripe_validation['error_msg'], 'stripe-tax-for-woocommerce' ) );
			}

			if ( is_null( $stripe_client ) ) {
				$stripe_client = new StripeClient( $key );
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$response = $stripe_client->taxCodes->all( array( 'limit' => 1 ) );

			if ( empty( $response->object ) && 'list' !== $response->object ) {
				throw new Exception( __( 'Unexpected response from Stripe', 'stripe-tax-for-woocommerce' ) . ': ' . wp_json_encode( $response ) );
			}
			$data->message = __( 'Connection successful', 'stripe-tax-for-woocommerce' );
			wp_send_json_success( $data, 200 );
		} catch ( \Throwable $e ) {
			$data->message = $e->getMessage();
			wp_send_json_error( $data, 200 );
		}
	}

	/**
	 * Force refresh tax registrations by clearing the cache.
	 *
	 * @throws Exception If something goes wrong.
	 */
	public static function force_refresh_tax_registrations() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		$data  = new stdClass();
		try {
			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'stripe_tax_for_woocommerce_force_refresh_tax_registrations' ) ) {
				throw new Exception( __( 'Form expired. Refresh page and try again.', 'stripe-tax-for-woocommerce' ) );
			}

			$api_key = Options::get_current_mode_key();
			if ( empty( $api_key ) ) {
				throw new Exception( __( 'No API key configured.', 'stripe-tax-for-woocommerce' ) );
			}

			$tax_registrations = new TaxRegistrations( $api_key );
			$tax_registrations->clear_cache();

			$data->message = __( 'Tax registrations cache cleared. Refreshing page...', 'stripe-tax-for-woocommerce' );
			wp_send_json_success( $data, 200 );
		} catch ( \Throwable $e ) {
			$data->message = $e->getMessage();
			wp_send_json_error( $data, 200 );
		}
	}

	/**
	 * Runs Stripe Tax diagnostics and returns rendered diagnostics HTML.
	 *
	 * @throws Exception If something goes wrong.
	 */
	public static function run_diagnostics() {
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		$data  = new stdClass();
		try {
			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'stripe_tax_run_diagnostics' ) ) {
				throw new Exception( __( 'Form expired. Refresh page and try again.', 'stripe-tax-for-woocommerce' ) );
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_woocommerce is a capability registered by WooCommerce.
				throw new Exception( __( 'You are not allowed to perform this action.', 'stripe-tax-for-woocommerce' ) );
			}

			$health_report = TaxHealthCheck::run( true );
			if ( TaxHealthCheck::has_only_missing_restricted_key_issue( $health_report ) ) {
				$data->html = '';
			} else {
				ob_start();
				include STRIPE_TAX_FOR_WOOCOMMERCE_TEMPLATES_DIR . 'tax-diagnostics-details.php';
				$data->html = ob_get_clean();
			}
			wp_send_json_success( $data, 200 );
		} catch ( \Throwable $e ) {
			$data->message = $e->getMessage();
			wp_send_json_error( $data, 200 );
		}
	}
}
