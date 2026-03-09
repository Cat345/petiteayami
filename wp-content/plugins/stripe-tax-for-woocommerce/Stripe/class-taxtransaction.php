<?php
/**
 * Tax Transaction service
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

// Exit if script started not from WordPress.
defined( 'ABSPATH' ) || exit;

use WC_Order;
use WC_Order_Refund;
use Stripe\StripeTaxForWooCommerce\Stripe\CalculateTax;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\ApiErrorException;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\InvalidRequestException;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Calculator;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
use Stripe\StripeTaxForWooCommerce\Utils\Amount_Utility;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Order_Input;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Util\Util;
use Throwable;

/**
 * Tax Transaction service
 */
abstract class TaxTransaction {

	const TABLE_NAME = STRIPE_TAX_FOR_WOOCOMMERCE_DB_PREFIX . 'tax_transactions';

	/**
	 * Cache of Tax Transactions to reduce database calls
	 *
	 * @var array<int, \stdClass>
	 */
	protected static $transactions = array();

	/**
	 * Create Stripe Tax Transaction from Tax Calculation
	 *
	 * @param int $order_id WooCommerce Order ID.
	 *
	 * @return \stdClass
	 * @throws ApiErrorException In case of API error.
	 * @see https://stripe.com/docs/api/tax/transactions/create_from_calculation
	 */
	public static function create_order_transaction( int $order_id ) {
		global $wpdb;

		$order = wc_get_order( $order_id );
		$order->calculate_totals( true );

		$tax_calculation        = Calculator::$calculations[ $order_id ];
		$tax_calculation_input  = $tax_calculation['input'];
		$tax_calculation_result = $tax_calculation['result'];

		static::reverse_order_last_transaction( $order_id );

		$last_tax_transaction = static::create_api_tax_transaction(
			$order_id,
			'Order ' . $order_id . ' order timestamp ' . time(),
			$tax_calculation_result
		);

		$order_refunds = static::get_order_refunds( $order_id );

		if ( count( $order_refunds ) > 0 ) {
			foreach ( $order_refunds as $order_refund ) {
				static::create_order_refund_reversal( $order_refund );
			}
		}
		$test = Calculator::$calculations;
		unset( Calculator::$calculations[ $order_id ] );
		$test2 = Calculator::$calculations;

		$tax_calculation_payload = $tax_calculation_input->get_payload();
		$calculate_tax           = new CalculateTax( Options::get_current_mode_key(), $tax_calculation_payload );
		$calculate_tax->delete();

		return $last_tax_transaction;
	}

	/**
	 * Reverse order last tax transaction
	 *
	 * @param int $order_id WooCommerce Order ID.
	 *
	 * @throws ApiErrorException In case of API error.
	 */
	public static function reverse_order_last_transaction( int $order_id ): void {
		$last_tax_transaction = static::get_order_last_transaction( $order_id );

		if ( ! $last_tax_transaction ) {
			return;
		}

		static::create_full_reversal( $order_id, $last_tax_transaction );
	}

	/**
	 * Creates a reversal partial tax transaction from an order refund object.
	 *
	 * @param object $order_refund WooCommerce Order Refund.
	 *
	 * @throws ApiErrorException In case of API error.
	 */
	public static function create_order_refund_reversal( $order_refund ) {
		if ( ! is_object( $order_refund ) ) {
			$order_refund = new WC_Order_Refund( $order_refund );
		}
		$order_id = $order_refund->get_parent_id();

		$order_last_transaction = static::get_order_last_transaction( $order_id );

		if ( ! $order_last_transaction ) {
			return;
		}
		$tax_transaction = $order_last_transaction->tax_transaction;
		$line_items      = static::get_tax_transaction_refund_line_items( $order_refund, $tax_transaction );

		$currency = $order_refund->get_currency();

		$shipping_total = (float) $order_refund->get_shipping_total( 'edit' );
		$shipping_tax   = (float) $order_refund->get_shipping_tax( 'edit' );

		$shipping_cost = array();

		if ( $shipping_total < 0.0 || $shipping_tax < 0.0 ) {
			$shipping_cost = array(
				'amount'     => Amount_Utility::to_cents( $shipping_total, $currency ),
				'amount_tax' => Amount_Utility::to_cents( $shipping_tax, $currency ),
			);
		}

		static::create_api_tax_reversal(
			$order_id,
			'Refund order ' . $order_id . ', refund timestamp ' . microtime(),
			$order_last_transaction->tax_transaction->id,
			$line_items,
			$shipping_cost
		);
	}

	/**
	 * Given an order and a refund order item return the original refunded order item.
	 *
	 * @param object $order_refund Order refund.
	 * @param object $order_refund_item Order refund item.
	 */
	protected static function get_original_item_reference( $order_refund, $order_refund_item ) {
		$order                  = wc_get_order( $order_refund->get_parent_id() );
		$order_refunded_item_id = $order_refund_item->get_meta( '_refunded_item_id' );
		$order_refunded_item    = $order->get_item( $order_refunded_item_id );

		$reference = Order_Input::build_item_reference_by_type( $order_refunded_item );

		return $reference;
	}

	/**
	 * Given an order refund and a previously tax calculation, return tax calculation lines associated with the refund
	 *
	 * @param object $wc_order_refund Order refund.
	 * @param object $tax_transaction_data Tax calculation data.
	 */
	public static function get_tax_transaction_refund_line_items( $wc_order_refund, object $tax_transaction_data ): array {
		$tax_transaction_data = Util::convertToStripeObject( $tax_transaction_data, array() );
		$items                = $wc_order_refund->get_items();
		$currency             = $wc_order_refund->get_currency();
		$line_items           = array();
		$line_items_counter   = 0;

		$items_reference_already_added = array();

		foreach ( $items as $item ) {
			$reference = static::get_original_item_reference( $wc_order_refund, $item );

			$original_line_item_id = '';
			// @phpstan-ignore-next-line
			foreach ( $tax_transaction_data->line_items as $transaction_line_item ) {
				if ( $reference === $transaction_line_item->reference ) {
					$original_line_item_id = $transaction_line_item->id;
					break;
				}
			}

			if ( ! $original_line_item_id ) {
				continue;
			}

			$quantity   = - $item->get_quantity();
			$amount     = Amount_Utility::to_cents( $item->get_total( 'edit' ), $currency );
			$tax_amount = Amount_Utility::to_cents( $item->get_total_tax( 'edit' ), $currency );

			$line_items[ $line_items_counter ] = array(
				'amount'             => $amount,
				'reference'          => $reference,
				'quantity'           => $quantity,
				'original_line_item' => $original_line_item_id,
				'amount_tax'         => $tax_amount,
			);

		}

		return $line_items;
	}
	/**
	 * Returns an order's refunds sorted by date desc
	 *
	 * @param int $order_id WooCommerce Order ID.
	 */
	public static function get_order_refunds( $order_id ) {
		$order         = new WC_Order( $order_id );
		$order_refunds = $order->get_refunds();

		usort( $order_refunds, array( static::class, 'compare_order_refunds_by_id_asc' ) );

		return $order_refunds;
	}

	/**
	 * Compares two order refunds by their ID.
	 *
	 * @param object $refund1 WooCommerce Order Refund.
	 * @param object $refund2 WooCommerce Order Refund.
	 */
	public static function compare_order_refunds_by_id_asc( $refund1, $refund2 ) {
		return $refund1->get_id() > $refund2->get_id();
	}

	/**
	 * Returns order all tax transactions from the database.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 */
	public static function get_order_all_transactions( int $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT %i, %i, %i FROM %i WHERE %i = %d',
				array(
					'order_id',
					'tax_calculation',
					'tax_transaction',
					static::TABLE_NAME,
					'order_id',
					$order_id,
				)
			)
		);

		if ( ! $result ) {
			return null;
		} else {
			$transactions = array();

			foreach ( $result as $record ) {
				$transaction                                       = self::create_transaction_dto( $record->order_id, $record->tax_transaction, $record->tax_calculation );
				$transactions[ $transaction->tax_transaction->id ] = $transaction;
			}
		}

		uasort( $transactions, array( static::class, 'compare_tax_transactions_by_created_asc' ) );

		static::$transactions[ $order_id ] = array();

		foreach ( $transactions as $transaction ) {
			static::prepend_transaction( $order_id, $transaction );
		}

		return static::$transactions[ $order_id ];
	}

	/**
	 * Prepends an order tax transaction to the static cache.
	 *
	 * @param int|object $order_id WooCommerce Order ID.
	 * @param object     $transaction Tax Transaction.
	 */
	protected static function prepend_transaction( $order_id, $transaction ) {
		$api_tax_transaction = $transaction->tax_transaction;

		if ( ! isset( static::$transactions[ $order_id ] ) ) {
			static::$transactions[ $order_id ] = array();
		}

		static::$transactions[ $order_id ] = array_merge(
			array( $api_tax_transaction->id => $transaction ),
			static::$transactions[ $order_id ]
		);

		if ( $api_tax_transaction->reversal ) {
			$original_api_tax_transaction_id = $api_tax_transaction->reversal->original_transaction;

			if ( isset( static::$transactions[ $order_id ][ $original_api_tax_transaction_id ] ) ) {
				$original_transaction = static::$transactions[ $order_id ][ $original_api_tax_transaction_id ];

				$original_transaction->reversals = array_merge(
					array( $api_tax_transaction->id => $transaction ),
					$original_transaction->reversals
				);
			}
		}
	}

	/**
	 * Compares two order tax transactions by their creation date.
	 *
	 * @param object $transaction1 Tax Transaction.
	 * @param object $transaction2 Tax Transaction.
	 */
	public static function compare_tax_transactions_by_created_asc( $transaction1, $transaction2 ) {
		$created1 = $transaction1->tax_transaction->created;
		$created2 = $transaction2->tax_transaction->created;

		if ( $created1 < $created2 ) {
			return -1;
		}

		if ( $created1 > $created2 ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Get order last tax transaction from cache or database.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return object|null
	 */
	public static function get_order_last_transaction( int $order_id ) {
		$all_tax_transactions = static::get_order_all_transactions( $order_id );

		if ( ! is_array( $all_tax_transactions ) ) {
			return null;
		}

		foreach ( $all_tax_transactions as $transaction ) {
			if ( ! $transaction->tax_transaction->reversal ) {
				return $transaction;
			}
		}

		return null;
	}

	/**
	 * Creates a full reversal for a given order and transaction
	 *
	 * @param int    $order_id Order ID.
	 * @param object $transaction Transaction.
	 * @return object|null
	 */
	public static function create_full_reversal( $order_id, $transaction ) {
		foreach ( $transaction->reversals as $reversal ) {
			static::create_full_reversal( $order_id, $reversal );
		}

		return static::create_full_api_tax_reversal(
			$order_id,
			'Refund ' . $transaction->tax_transaction->reference,
			$transaction->tax_transaction->id
		);
	}

	/**
	 * Creates an API tax transaction for a given order with a given reference
	 * and saves the result to the database.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $reference Line items.
	 * @param object $api_tax_calculation The result of a tax calculation API call.
	 *
	 * @return object|null
	 * @throws ApiErrorException In case of API error.
	 * @see https://stripe.com/docs/api/tax/transactions/create_from_calculation
	 */
	protected static function create_api_tax_transaction( $order_id, $reference, $api_tax_calculation ) {
		$stripe_client = StripeClientFactory::get_stripe_client();

		$api_tax_transaction = $stripe_client->tax->transactions->createFromCalculation(
			array(
				'calculation' => $api_tax_calculation->id,
				'reference'   => $reference,
				'expand'      => array( 'line_items' ),
			)
		);

		$transaction = static::save_transaction( $order_id, $api_tax_transaction, $api_tax_calculation );

		return $transaction;
	}

	/**
	 * Creates an API tax transaction partial reversal for given order and tax transaction with a given reference
	 * and saves the result to the database.
	 *
	 * @param int    $order_id                    Order ID.
	 * @param string $reference                   Line items.
	 * @param string $original_api_transaction_id Tax transaction to reverse id.
	 * @param array  $line_items                  Items to reverse for partial reversals.
	 * @param array  $shipping_cost               Shipping cost for partial reversals.
	 * @return object|null
	 * @throws ApiErrorException In case of API error.
	 * @see https://stripe.com/docs/api/tax/transactions/create_api_tax_reversal
	 */
	protected static function create_api_tax_reversal( int $order_id, $reference, $original_api_transaction_id, array $line_items = array(), array $shipping_cost = array() ) {
		$stripe_client = StripeClientFactory::get_stripe_client();

		$reversal_transaction_request_data = array(
			'mode' => $line_items ? 'partial' : 'full',
		);

		$reversal_transaction_request_data['original_transaction'] = $original_api_transaction_id;
		$reversal_transaction_request_data['reference']            = $reference;
		$reversal_transaction_request_data['expand']               = array( 'line_items' );

		if ( $line_items ) {
			$reversal_transaction_request_data['line_items'] = $line_items;
		}

		if ( $shipping_cost ) {
			$reversal_transaction_request_data['shipping_cost'] = $shipping_cost;
		}

		$api_tax_transaction = $stripe_client->tax->transactions->createReversal( $reversal_transaction_request_data );

		$transaction = static::save_transaction( $order_id, $api_tax_transaction );

		return $transaction;
	}

	/**
	 * Creates an API tax transaction full reversal for given order and tax transaction with a given reference
	 * and saves the result to the database.
	 *
	 * @param int    $order_id                        Order ID.
	 * @param string $reference                       Line items.
	 * @param object $original_api_tax_transaction_id Transaction to reverse id.
	 *
	 * @return object|null
	 * @throws ApiErrorException In case of API error.
	 * @see https://stripe.com/docs/api/tax/transactions/create_api_tax_reversal
	 */
	public static function create_full_api_tax_reversal( $order_id, $reference, $original_api_tax_transaction_id ) {
		$stripe_client = StripeClientFactory::get_stripe_client();

		$full_api_tax_reversal_request_data = array(
			'mode'                 => 'full',
			'original_transaction' => $original_api_tax_transaction_id,
			'reference'            => $reference,
			'expand'               => array( 'line_items' ),
		);

		try {
			$full_api_tax_reversal = $stripe_client->tax->transactions->createReversal( $full_api_tax_reversal_request_data );
			$transaction           = static::save_transaction( $order_id, $full_api_tax_reversal );

			return $transaction;
		} catch ( Throwable $err ) {
			static::on_error(
				$err,
				array(
					'order_id'             => $order_id,
					'reference'            => $reference,
					'original_transaction' => $original_api_tax_transaction_id,
				)
			);
			return null;
		}
	}

	/**
	 * Error handler.
	 *
	 * @param \Throwable $err     The error object.
	 * @param array|null $context Error context.
	 *
	 * @throws \Throwable When the error cannot be handled.
	 */
	protected static function on_error( $err, $context = null ) {
		if ( ! ( $err instanceof InvalidRequestException ) ) {
			throw $err;
		}

		if ( ! is_array( $context ) || ! array_key_exists( 'original_transaction', $context ) ) {
			throw $err;
		}

		$err_message = $err->getMessage();

		if ( strpos( $err_message, 'Fully reverse the partial reversals' ) === false ) {
			throw $err;
		}

		preg_match_all( '/(tax_[a-z0-9]+)/i', $err_message, $reversal_tax_ids );
		$reversal_tax_ids = $reversal_tax_ids[1];

		$order_id                       = $context['order_id'];
		$original_transaction_reference = $context['reference'];
		$original_transaction           = $context['original_transaction'];

		$original_transaction_key = array_search( $original_transaction, $reversal_tax_ids, true );

		if ( false === $original_transaction_key ) {
			throw $err;
		}

		unset( $reversal_tax_ids[ $original_transaction_key ] );

		static::create_full_api_tax_reversal_with_partial_reversals( $order_id, 'Refund ' . $original_transaction_reference, $original_transaction, $reversal_tax_ids );
	}

	/**
	 * Creates an API tax transaction full reversal for given order and tax transaction that have partial reversals
	 * with a given reference and saves the result to the database.
	 *
	 * @param int    $order_id             Order ID.
	 * @param string $reference            Line items.
	 * @param string $original_transaction Transaction id.
	 * @param array  $reversal_tax_ids     Reversal transactions of the origina transaction.
	 *
	 * @return object|null
	 * @throws ApiErrorException In case of API error.
	 */
	protected static function create_full_api_tax_reversal_with_partial_reversals( $order_id, $reference, $original_transaction, $reversal_tax_ids ) {
		$stripe_client = StripeClientFactory::get_stripe_client();

		$reversal_references = array();

		foreach ( $reversal_tax_ids as $key => $reversal_tax_id ) {
			$api_tax_reversal                        = $stripe_client->tax->transactions->retrieve( $reversal_tax_id );
			$reversal_references[ $reversal_tax_id ] = $api_tax_reversal->reference;

			if ( $api_tax_reversal->reversal->original_transaction !== $original_transaction ) {
				return null;
			}
		}

		foreach ( $reversal_references as $reversal_tax_id => $reversal_reference ) {
			static::create_full_api_tax_reversal( $order_id, 'Refund' . $reversal_reference, $reversal_tax_id );
		}

		return static::create_full_api_tax_reversal( $order_id, $reference, $original_transaction );
	}

	/**
	 * Saves a transaction to the database after its creation via API calls.
	 *
	 * @param int    $order_id            Order ID.
	 * @param object $api_tax_transaction Tax creation API call response.
	 * @param object $api_tax_calculation Tax calculation API call response.
	 *
	 * @return object
	 */
	protected static function save_transaction( $order_id, $api_tax_transaction, $api_tax_calculation = null ) {
		global $wpdb;

		$json_api_tax_calculation = wp_json_encode( $api_tax_calculation );
		$json_api_tax_transaction = ! is_null( $api_tax_transaction ) ? wp_json_encode( $api_tax_transaction ) : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (%i, %i, %i) VALUES (%d, %s, %s) ON DUPLICATE KEY UPDATE %i = %s, %i = %s',
				array(
					static::TABLE_NAME,
					'order_id',
					'tax_calculation',
					'tax_transaction',
					$order_id,
					$json_api_tax_calculation,
					$json_api_tax_transaction,
					'tax_calculation',
					$json_api_tax_calculation,
					'tax_transaction',
					$json_api_tax_transaction,
				)
			)
		);

		$last_transaction = self::create_transaction_dto( $order_id, $json_api_tax_transaction, $json_api_tax_calculation );

		static::prepend_transaction( $order_id, $last_transaction );

		return $last_transaction;
	}

	/**
	 * Creates transaction objects to store in the static cache.
	 *
	 * @param int    $order_id                 Order ID.
	 * @param object $json_api_tax_transaction Tax creation API call response.
	 * @param object $json_api_tax_calculation Tax calculation API call response.
	 *
	 * @return object
	 */
	private static function create_transaction_dto( $order_id, $json_api_tax_transaction, $json_api_tax_calculation = null ) {
		$transaction = new \stdClass();

		$transaction->order_id = $order_id;

		$transaction->tax_transaction = Util::convertToStripeObject( json_decode( $json_api_tax_transaction, true ), array() );
		$transaction->tax_calculation = Util::convertToStripeObject( json_decode( $json_api_tax_calculation, true ), array() );
		$transaction->reversals       = array();

		return $transaction;
	}
}
