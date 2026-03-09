<?php
/**
 * Order tax transaction hook handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WordPress\Hook_Handlers;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Data;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Order_Controller;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeOrderItemTax;
use Stripe\StripeTaxForWooCommerce\Stripe\CalculateTax;
use Stripe\StripeTaxForWooCommerce\Stripe\TaxTransaction;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\InvalidRequestException;
use Throwable;

/**
 * Class for handling hooks used in tax transactions
 */
abstract class Order_Tax_Transaction extends Hook_Handlers {
	const ACTIONS = array(
		'order_status_changed',
	);
	const FILTERS = array();

	const ACTIVATION_OPTIONS = array();

	/**
	 * Creates Stripe Tax transaction when order payment made (status became "processing" or suddenly "completed").
	 *
	 * @param int       $order_id Order id.
	 * @param string    $status_from From status.
	 * @param string    $status_to To status.
	 * @param \WC_Order $wc_order The WooCommerce order.
	 */
	public static function order_status_changed( $order_id, $status_from, $status_to, $wc_order ) {
		/**
		 * WC Order.
		 *
		 * @var \WC_Order $wc_order
		 */
		if ( ! static::is_enabled() ) {
			return;
		}

		if ( apply_filters( 'stripe_tax_skip_calculation_and_transaction_on_order_status_change', false, $order_id, $status_from, $status_to, $wc_order ) ) {
			return;
		}

		try {
			if ( 'completed' === $status_to && 'processing' === $status_from ) {
				return;
			}

			if ( 'processing' !== $status_to && 'completed' !== $status_to ) {
				return;
			}

			TaxTransaction::create_order_transaction( $order_id );

		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
		}
	}
}
