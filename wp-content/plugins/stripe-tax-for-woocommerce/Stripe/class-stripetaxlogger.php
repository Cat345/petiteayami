<?php
/**
 *  Stripe Tax Logger
 *  Log errors and info messages
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

use Stripe\StripeTaxForWooCommerce\SDK\lib\Stripe;
use Throwable;
use WC_Logger;

/**
 * Stripe Tax Logger class.
 */
class StripeTaxLogger {
	/**
	 * WC Logger instance
	 *
	 * @var WC_Logger
	 */
	private static $wc_logger;
	const WC_LOG_FILENAME = 'stripe-tax-for-woocommerce';

	/**
	 * Unique log id
	 *
	 * @var string|null
	 */
	private static $log_id = null;

	/**
	 * Log info in file.
	 *
	 * @param string $message Info message.
	 * @param array  $context Context.
	 * @return void
	 */
	public static function log_info( string $message, array $context = array() ): void {
		if ( ! self::can_log() ) {
			return;
		}

		if ( null === self::$log_id ) {
			self::$log_id = uniqid();
		}

		if ( empty( self::$wc_logger ) ) {
			self::$wc_logger = wc_get_logger();
		}

		$log_entry  = "\n" . '(' . self::$log_id . ') ==== Stripe Tax Version: ' . Stripe::getAppInfo()['version'] . ' ====' . "\n";
		$log_entry .= '(' . self::$log_id . ') ==== Start Log ====' . "\n";
		$log_entry .= '(' . self::$log_id . ') Info message: ' . "\n" . $message . "\n";
		if ( ! empty( $context ) ) {
			$details = self::get_details_from_context( $context );
			if ( ! empty( $details ) ) {
				$log_entry .= '(' . self::$log_id . ') More details: ' . "\n\t" . $details . "\n";
			}
		}
		$log_entry .= '(' . self::$log_id . ') ==== End Log ====' . "\n\n";

		self::$wc_logger->info(
			$log_entry,
			array( 'source' => self::WC_LOG_FILENAME . '-info' )
		);
	}

	/**
	 * Get details from specific context.
	 *
	 * @param array $context Context.
	 * @return string
	 */
	private static function get_details_from_context( array $context ): string {
		$details = array();

		if ( array_key_exists( 'cart_totals', $context ) ) {
			$details[] = '=== Cart Totals ===';
			$details[] = 'cart_totals = ' . wp_json_encode( $context['cart_totals'] );
		}

		if ( array_key_exists( 'order_data', $context ) ) {
			$details[] = '=== Order Data ===';
			$keys      = array(
				'prices_include_tax',
				'discount_total',
				'discount_tax',
				'shipping_total',
				'shipping_tax',
				'cart_tax',
				'total',
				'total_tax',
			);
			foreach ( $context['order_data'] as $key => $data ) {
				if ( in_array( $key, $keys, true ) ) {
					$details[] = $key . ' = ' . $data;
				}
			}
		}

		if ( array_key_exists( 'calculation_response', $context ) ) {
			$details[] = '=== Calculation Response ===';
			$details[] = 'id = ' . $context['calculation_response']->id;
			$details[] = 'address_country = ' . $context['calculation_response']->customer_details->address->country;
			$details[] = 'address_state = ' . $context['calculation_response']->customer_details->address->state;
			$details[] = 'address_postal_code = ' . $context['calculation_response']->customer_details->address->postal_code;
			$details[] = 'address_source = ' . $context['calculation_response']->customer_details->address_source;

			foreach ( $context['calculation_response']->line_items->data as $data_index => $data ) {
				unset( $context['calculation_response']->line_items->data[ $data_index ]->id );
				unset( $context['calculation_response']->line_items->data[ $data_index ]->livemode );
				unset( $context['calculation_response']->line_items->data[ $data_index ]->object );

				$details[] = 'line_items_data[' . $data_index . '] = ' . wp_json_encode( $data );
			}

			$details[] = 'line_items_total_count = ' . $context['calculation_response']->line_items->total_count;
			$details[] = 'shipping_cost = ' . wp_json_encode( $context['calculation_response']->shipping_cost );
			$details[] = 'tax_amount_exclusive = ' . $context['calculation_response']->tax_amount_exclusive;
			$details[] = 'tax_amount_inclusive = ' . $context['calculation_response']->tax_amount_inclusive;

			foreach ( $context['calculation_response']->tax_breakdown as $data_index => $data ) {
				$details[] = 'tax_breakdown[' . $data_index . '] = ' . wp_json_encode( $data );
			}
		}

		return ! empty( $details ) ? implode( "\n\t", $details ) : '';
	}

	/**
	 * Log errors in file.
	 *
	 * @param Throwable $e Error object.
	 * @return void
	 */
	public static function log_throwable( Throwable $e ) {
		static::log_error( $e->getMessage() );
	}


	/**
	 * Log errors in file.
	 *
	 * @param string $err_message Error message.
	 * @return void
	 */
	public static function log_error( $err_message ): void {
		if ( ! self::can_log() ) {
			return;
		}

		if ( null === self::$log_id ) {
			self::$log_id = uniqid();
		}

		if ( empty( self::$wc_logger ) ) {
			self::$wc_logger = wc_get_logger();
		}

		$log_entry  = "\n" . '(' . self::$log_id . ') ==== Stripe Tax Version: ' . Stripe::getAppInfo()['version'] . ' ====' . "\n";
		$log_entry .= '(' . self::$log_id . ') ==== Start Log ====' . "\n";
		$log_entry .= '(' . self::$log_id . ') Error message: ' . "\n\t" . $err_message . "\n";
		$log_entry .= '(' . self::$log_id . ') ==== End Log ====' . "\n\n";

		self::$wc_logger->error(
			$log_entry,
			array( 'source' => self::WC_LOG_FILENAME . '-errors' )
		);
	}
	/**
	 * Check if WC Logger is available and logging can proceed.
	 *
	 * @return bool True if logging is possible, false otherwise.
	 */
	private static function can_log(): bool {
		return class_exists( 'WC_Logger' );
	}
}
