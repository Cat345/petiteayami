<?php
/**
 * Tax Transaction Reversal utility
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxLogger;
use Throwable;
use Exception;
use WP_CLI;

/**
 * Utility class for creating a reversal tax transaction given its original transaction ID.
 */
class TaxTransactionReversal {
	/**
	 * Creates a tax full reversal transaction given an original transaction ID.
	 *
	 * @param array $args Command line params.
	 */
	public function __invoke( $args ): void {
		try {
			$api_tax_transaction_id = $args[0];

			static::create_full_api_tax_reversal( $api_tax_transaction_id );

			WP_CLI::line( 'Transaction reversal succeeded for \'' . $api_tax_transaction_id . '\'' );
		} catch ( Throwable $err ) {
			$err_message = 'Tax transaction reversal failed for \'' . $api_tax_transaction_id . '\'. ' . $err->getMessage();
			StripeTaxLogger::log_error( $err_message );
			WP_CLI::error( $err_message );
		}
	}

	/**
	 * Creates a tax full reversal transaction given an original transaction ID.
	 *
	 * @param string $api_tax_transaction_id Original tax transaction id.
	 *
	 * @throws Exception Thrown if tax transaction reversal fails.
	 */
	public static function create_full_api_tax_reversal( $api_tax_transaction_id ) {
		$details = static::get_api_tax_transaction_reference_and_order_id( $api_tax_transaction_id );

		if ( ! is_array( $details ) || ! array_key_exists( 'order_id', $details ) || ! array_key_exists( 'reference', $details ) ) {
			throw new Exception( esc_html( 'Tax transaction reversal failed for "' . $api_tax_transaction_id . '": no order_id found in reference ("' . $reference . '")' ) );
		}

		$order_id  = $details['order_id'];
		$reference = 'Refund ' . $details['reference'];

		TaxTransaction::create_full_api_tax_reversal(
			$order_id,
			$reference,
			$api_tax_transaction_id
		);
	}

	/**
	 * Retrieves a tax transaction order_id and reference.
	 *
	 * @param string $api_tax_transaction_id Original tax transaction id.
	 */
	public static function get_api_tax_transaction_reference_and_order_id( $api_tax_transaction_id ) {
		$reference = static::get_api_tax_transaction_reference( $api_tax_transaction_id );

		preg_match( '/[a-z\s]+([0-9]+)/i', $reference, $matches );

		if ( ! $matches ) {
			return;
		}

		return array(
			'reference' => $reference,
			'order_id'  => $matches[1],
		);
	}

	/**
	 * Retrieves a tax transaction reference.
	 *
	 * @param string $api_tax_transaction_id Original tax transaction id.
	 */
	public static function get_api_tax_transaction_reference( $api_tax_transaction_id ) {
		$stripe_client = StripeClientFactory::get_stripe_client();

		$api_tax_transaction = $stripe_client->tax->transactions->retrieve( $api_tax_transaction_id );

		return $api_tax_transaction->reference;
	}
}
