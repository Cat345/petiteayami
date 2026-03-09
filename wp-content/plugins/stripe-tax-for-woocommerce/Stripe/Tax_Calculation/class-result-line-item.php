<?php
/**
 * Tax calculation operations result line item class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Utils\Readonly_Object;

/**
 * Tax calculation operations result line item class.
 */
class Result_Line_Item extends Input_Line_Item {

	/**
	 * Creates a tax calculation result line item
	 *
	 * @param string $reference Line item reference.
	 * @param int    $amount Line item total amount.
	 * @param int    $quantity Line item quantity.
	 * @param string $tax_behavior Line item tax behavior.
	 * @param string $tax_code Line item tax code.
	 * @param int    $amount_subtotal Line item subtotal amount.
	 * @param string $id Line item ID.
	 * @param int    $amount_subtotal_tax Line item tax subtotal amount.
	 * @param int    $amount_tax Line item tax total amount.
	 * @param array  $tax_breakdown Line item tax breakdown.
	 */
	public function __construct(
		$reference,
		$amount,
		$quantity,
		$tax_behavior,
		$tax_code,
		$amount_subtotal,
		$id,
		$amount_subtotal_tax,
		$amount_tax,
		$tax_breakdown
	) {
		$entries = array(
			'reference'                     => $reference,
			'amount'                        => $amount,
			'quantity'                      => $quantity,
			'tax_behavior'                  => $tax_behavior,
			'amount_subtotal'               => $amount_subtotal,
			'id'                            => $id,
			'amount_subtotal_tax'           => $amount_subtotal_tax,
			'amount_tax'                    => $amount_tax,
			'wc_tax_breakdown'              => array(),
			'amount_tax_breakdown'          => array(),
			'amount_subtotal_tax_breakdown' => array(),
		);

		if ( $tax_code ) {
			$entries['tax_code'] = $tax_code;
		}

		foreach ( $tax_breakdown as $tax_breakdown_line ) {
			$rate_id = $tax_breakdown_line['rate_id'];

			$entries['amount_tax_breakdown'][ $rate_id ]          = $tax_breakdown_line['amount_tax'];
			$entries['amount_subtotal_tax_breakdown'][ $rate_id ] = $tax_breakdown_line['amount_subtotal_tax'];

			$entries['wc_tax_breakdown'][ $rate_id ] = array(
				'rate'         => $tax_breakdown_line['rate_percent'],
				'rate_percent' => $tax_breakdown_line['rate_percent'],
				'label'        => $tax_breakdown_line['rate_label'],
				'shipping'     => 'no',
				'compound'     => 'no',
			);

			unset(
				$tax_breakdown_line['rate_percent'],
				$tax_breakdown_line['rate_label'],
				$tax_breakdown_line['rate_code'],
				$tax_breakdown_line['rate_id']
			);
		}

		Readonly_Object::__construct( $entries );
	}
}
