<?php
/**
 * Tax calculation input line item.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Utils\Readonly_Object;

/**
 * Tax calculation input line item.
 */
class Input_Line_Item extends Readonly_Object {
	/**
	 * Creates a readonly tax calculation input line item
	 *
	 * @param string $reference Item type.
	 * @param int    $amount Item amount.
	 * @param int    $quantity Item quantity.
	 * @param string $tax_behavior Item tax behavior.
	 * @param string $tax_code Item tax  code.
	 * @param int    $amount_subtotal Item subtotal - for inclusive tax behavior only.
	 */
	public function __construct(
		string $reference,
		int $amount,
		int $quantity,
		string $tax_behavior,
		string $tax_code,
		int $amount_subtotal
	) {
		$entries = array(
			'reference'    => $reference,
			'amount'       => $amount,
			'quantity'     => $quantity,
			'tax_behavior' => $tax_behavior,
			'tax_code'     => $tax_code,
			'metadata'     => array(
				'amount_subtotal' => $amount_subtotal,
			),
		);

		if ( $tax_code ) {
			$entries['tax_code'] = $tax_code;
		}

		parent::__construct( $entries );
	}
}
