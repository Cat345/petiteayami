<?php
/**
 * Tax calculation input customer details.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Utils\Readonly_Object;

/**
 * Tax calculation input customer details.
 */
class Customer_Details extends Readonly_Object {
	/**
	 * Creates a customer details object.
	 *
	 * @param string $country Address country.
	 * @param string $state Address state.
	 * @param string $city Address city.
	 * @param string $postal_code Address postal code.
	 * @param string $line1 Address line 1.
	 * @param string $line2 Address line 2.
	 * @param string $address_source Address source.
	 * @param string $taxability_override Taxability override.
	 */
	public function __construct(
		string $country,
		string $state,
		string $city,
		string $postal_code,
		string $line1,
		string $line2,
		string $address_source,
		$taxability_override
	) {
		$entries = array(
			'address'             => array(
				'country'     => $country,
				'state'       => $state,
				'city'        => $city,
				'postal_code' => $postal_code,
				'line1'       => $line1,
				'line2'       => $line2,
			),
			'address_source'      => $address_source,
			'taxability_override' => $taxability_override,
		);

		parent::__construct( $entries );
	}
}
