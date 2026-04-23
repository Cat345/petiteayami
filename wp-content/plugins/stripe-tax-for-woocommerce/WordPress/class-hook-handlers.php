<?php
/**
 * Base class for hook handlers.
 *
 * @package Stripe\StripeTaxForWooCommerce\WordPress
 */

namespace Stripe\StripeTaxForWooCommerce\WordPress;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\StripeTax_Options;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxLogger;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxPluginHelper;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Input_Exception;
use Throwable;
/**
 * Base class for hook handlers.
 */
abstract class Hook_Handlers {
	const ACTIONS            = array();
	const FILTERS            = array();
	const ACTIVATION_OPTIONS = array();

	/**
	 * Whether the current request has failed.
	 *
	 * @var bool
	 */
	protected static $current_request_failed = false;

	/**
	 * Stores a human-readable error for the current request failure.
	 *
	 * @var string
	 */
	protected static $current_request_error_message = '';

	/**
	 * Register action and filter hook handlers
	 */
	public static function register_hook_handlers(): void {
		static::register_hook_handler_by_names( 'action', static::ACTIONS );
		static::register_hook_handler_by_names( 'filter', static::FILTERS );
	}

	/**
	 * Unegisters action and filter hook handlers
	 */
	public static function unregister_hook_handlers(): void {
		static::unregister_hook_handler_by_names( 'action', static::ACTIONS );
		static::unregister_hook_handler_by_names( 'filter', static::FILTERS );
	}

	/**
	 * Register hook handlers by their type and name
	 *
	 * @param string          $hook_type Filter or action.
	 * @param string|string[] $hook_names Filter or action names.
	 */
	protected static function register_hook_handler_by_names( $hook_type, $hook_names ) {
		$registration_function_name = 'add_' . $hook_type;

		foreach ( $hook_names as $hook_name ) {
			$registration_function_name(
				'woocommerce_' . $hook_name,
				array( static::class, $hook_name ),
				100,
				4
			);
		}
	}

	/**
	 * Unegister hook handlers by their type and name
	 *
	 * @param string          $hook_type Filter or action.
	 * @param string|string[] $hook_names Filter or action names.
	 */
	protected static function unregister_hook_handler_by_names( $hook_type, $hook_names ) {
		$unregistration_function_name = 'remove_' . $hook_type;

		foreach ( $hook_names as $hook_name ) {
			$unregistration_function_name(
				'woocommerce_' . $hook_name,
				array( static::class, $hook_name ),
				100
			);
		}
	}

	/**
	 * Shows if handles should be enabled
	 */
	public static function is_enabled() {
		if ( ! Options::is_live_mode_enabled() || self::is_current_request_failed() || ! wc_tax_enabled() ) {
			return false;
		}
		return true;
	}

	/**
	 * Handles errors.
	 *
	 * @param throwable $err The error.
	 */
	protected static function on_error( $err ) {
		StripeTaxLogger::log_info( $err->getMessage() );
		static::$current_request_error_message = static::get_customer_safe_error_message( $err );

		if ( is_admin() ) {
			$message = StripeTaxPluginHelper::format_api_error_message( $err );
			static::show_admin_error( $message );
		}
	}

	/**
	 * Given a throwable object, formats and outputs an error mesage
	 *
	 * @param object $err The throwable object.
	 */
	protected static function show_admin_error( $err ) {

		$message = $err instanceof \Throwable ? $err->getMessage() : (string) $err;

		if ( wp_doing_ajax() ) {

			// ✅ Verify nonce BEFORE using $_REQUEST.
			$security = isset( $_REQUEST['security'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ) : '';
			if ( ! $security || ! wp_verify_nonce( $security, 'order-item' ) ) {
				StripeTaxLogger::log_info( 'StripeTax AJAX: invalid/missing nonce in show_admin_error' );
				return;
			}

			$order_id = isset( $_REQUEST['order_id'] )
				? absint( wp_unslash( $_REQUEST['order_id'] ) )
				: 0;

			if ( $order_id ) {
				update_post_meta( $order_id, '_stripe_tax_last_error', wp_strip_all_tags( $message ) );
			} else {
				$action = isset( $_REQUEST['action'] )
					? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
					: 'none';

				StripeTaxLogger::log_info( 'StripeTax AJAX: missing order_id (action=' . $action . ')' );
			}

			return;
		}

		echo '<div class="notice notice-error"><p><strong>'
			. esc_html__( 'Stripe Tax:', 'stripe-tax-for-woocommerce' )
			. '</strong> ' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handles generic errors.
	 *
	 * @param throwable $err The error.
	 */
	protected static function on_generic_error( $err ) {
		StripeTaxLogger::log_error( $err->getMessage() );
		static::$current_request_error_message = static::get_customer_safe_error_message( $err );

		if ( is_admin() ) {
			$message = StripeTaxPluginHelper::format_api_error_message( $err );
			static::show_admin_error( $message );
		}
	}

	/**
	 * Sets the failure state for the current request.
	 *
	 * This flag is used to track whether tax calculation failed during
	 * the current checkout request.
	 *
	 * @param bool $status True to mark the current request as failed.
	 *
	 * @return void
	 */
	protected static function set_current_request_failed( $status ): void {
		static::$current_request_failed = (bool) $status;

		if ( ! static::$current_request_failed ) {
			static::$current_request_error_message = '';
		}
	}

	/**
	 * Checks whether the current request has been marked as failed.
	 *
	 * This can be used during checkout processing to determine whether
	 * tax calculation failed earlier in the request.
	 *
	 * @return bool True if the current request failed, false otherwise.
	 */
	public static function is_current_request_failed(): bool {
		return static::$current_request_failed;
	}

	/**
	 * Returns the error message associated with current request failure.
	 *
	 * @return string
	 */
	public static function get_current_request_error_message(): string {
		return static::$current_request_error_message;
	}

	/**
	 * Returns a checkout-safe error message for customers.
	 *
	 * @param Throwable $err The captured exception.
	 *
	 * @return string
	 */
	protected static function get_customer_safe_error_message( Throwable $err ): string {
		if ( $err instanceof Input_Exception ) {
			return wp_strip_all_tags( (string) $err->getMessage() );
		}

		return '';
	}
}
