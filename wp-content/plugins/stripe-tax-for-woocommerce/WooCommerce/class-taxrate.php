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
 *
 * @phpstan-consistent-constructor
 */
class TaxRate extends ArrayObject {
	/**
	 * Constructor
	 *
	 * @param int|null $id Rate ID.
	 * @param string   $country Rate country.
	 * @param string   $state Rate state.
	 * @param float    $rate Rate percentage.
	 * @param string   $name Rate name.
	 * @param bool     $shipping Rate shipping.
	 * @param int      $priority Rate priority.
	 * @param string   $city Rate city.
	 * @param string   $postal_code Rate postal / zip code.
	 */
	public function __construct( ?int $id, string $country, ?string $state, float $rate, string $name, $shipping, $priority, string $city = '', string $postal_code = '' ) {
		parent::__construct(
			array(
				'id'          => $id,
				'country'     => strtoupper( $country ),
				'state'       => is_null( $state ) ? '' : strtoupper( $state ),
				'rate'        => number_format( (float) $rate, 4, '.', '' ),
				'name'        => $name,
				'priority'    => $priority,
				'compound'    => 0,
				'shipping'    => $shipping,
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
		return self::build_code( $this['country'], $this['state'], $this['name'], $this['rate'] );
	}

	/**
	 * Creates a tax rate code from parameters
	 *
	 * @param string $country Tax rate country.
	 * @param string $state Tax rate state.
	 * @param string $name Tax rate name.
	 * @param string $rate Tax rate rate.
	 */
	public static function build_code( $country, $state, $name, $rate ) {
		return $country . '-' . $state . '-' . $name . '-' . $rate;
	}

	/**
	 * Creates tax rate copy with a given id
	 *
	 * @param int    $id The ID.
	 * @param object $tax_rate Tax rate to copy.
	 */
	public static function clone_with_id( $id, $tax_rate ) {
		$tax_rate_clone = new static(
			$id,
			$tax_rate['country'],
			$tax_rate['state'],
			$tax_rate['rate'],
			$tax_rate['name'],
			$tax_rate['shipping'],
			$tax_rate['priority'],
			$tax_rate['city'],
			$tax_rate['postal_code']
		);

		return $tax_rate_clone;
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
