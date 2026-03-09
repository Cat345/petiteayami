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
use Throwable;
/**
 * Base class for hook handlers.
 */
abstract class Hook_Handlers {
	const ACTIONS            = array();
	const FILTERS            = array();
	const ACTIVATION_OPTIONS = array();

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
	protected static function is_enabled() {
		if ( ! Options::is_live_mode_enabled() || ! wc_tax_enabled() ) {
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

		if ( is_admin() ) {
			static::show_admin_error( $err );
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

		echo '<span class="stripe_tax_for_woocommerce_message_span_id_" id="stripe_tax_for_woocommerce_message_id_"> </span>'
			. '<div class="stripe_tax_for_woocommerce_message stripe_tax_for_woocommerce_message_" id="stripe_tax_for_woocommerce_message_id_">'
			. '<p>Err: <strong>' . ( esc_html( $err->getMessage() ) ) . '</strong></p>'
			. '</div>';
	}

	/**
	 * Handles generic errors.
	 *
	 * @param throwable $err The error.
	 */
	protected static function on_generic_error( $err ) {
		StripeTaxLogger::log_error( $err->getMessage() );

		if ( is_admin() ) {
			static::show_admin_error( $err );
		}
	}
}
