<?php
/**
 * TaxRate class
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

use ArrayObject;

/**
 * TaxRate class
 */
class TaxRate extends ArrayObject {
	/**
	 * Constructor
	 *
	 * @param int    $id Rate ID.
	 * @param string $country Rate country.
	 * @param string $state Rate state.
	 * @param float  $rate Rate percentage.
	 * @param string $name Rate name.
	 * @param int    $priority Rate priority.
	 * @param string $city Rate cisty.
	 * @param string $postal_code Rate postal / zip code.
	 */
	public function __construct( $id, string $country, ?string $state, float $rate, string $name, $priority, string $city, string $postal_code ) {
		parent::__construct(
			array(
				'id'          => $id,
				'country'     => strtoupper( $country ),
				'state'       => is_null( $state ) ? '' : strtoupper( $state ),
				'rate'        => number_format( (float) $rate, 4, '.', '' ),
				'name'        => $name,
				'priority'    => $priority,
				'compound'    => 0,
				'shipping'    => 1,
				'order'       => 1,
				'class'       => '',
				'city'        => strtoupper( trim( $city ) ),
				'postal_code' => strtoupper( trim( $postal_code ) ),
			)
		);
	}

	/**
	 * Returns tax rate aggregate key.
	 */
	public function get_aggregate_key() {
		return $this['country'] . '-' . $this['state'] . '-' . $this['rate'] . '-' . $this['name'];
	}

	/**
	 * Returns tax rate code.
	 */
	public function get_code() {
		return $this['country'] . '-' . $this['state'] . '-' . $this['name'] . '-' . $this['rate'];
	}

	/**
	 * Given an aggregate key, creates a tax rate from it.
	 *
	 * @param int    $id Rate ID.
	 * @param string $aggregate_key Aggregate key.
	 */
	public static function from_aggregate_key( $id, $aggregate_key ) {
		$tax_rate_parts = explode( '-', $aggregate_key );

		$tax_rate_parts[2] = (float) $tax_rate_parts[2];
		$tax_rate_parts[]  = 1;
		$tax_rate_parts[]  = '';
		$tax_rate_parts[]  = '';

		$tax_rate = new static( $id, ...$tax_rate_parts );

		return $tax_rate;
	}
}
