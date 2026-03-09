<?php
/**
 * Tax rate repo
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * StripeTaxTaxRateMemRepo class.
 */
abstract class StripeTaxTaxRateMemRepo {
	const MIN_ID = 500000;

	/**
	 * Static cache.
	 *
	 * @var array
	 */
	public static $tax_rates = array();

	/**
	 * Creates and stores a tax rate.
	 *
	 * @param TaxRate $tax_rate Tax Rate to build the new tax from.
	 */
	public static function create( TaxRate $tax_rate ) {
		$max_id = array_key_last( static::$tax_rates );

		if ( is_null( $max_id ) ) {
			$max_id = static::MIN_ID;
		}
		$crc = sprintf(
			'%u',
			crc32(
				( isset( $tax_rate['country'] ) ? $tax_rate['country'] : '' ) . '-' .
				( isset( $tax_rate['state'] ) ? $tax_rate['state'] : '' ) . '-' .
				( isset( $tax_rate['rate'] ) ? $tax_rate['rate'] : '' ) . '-' .
				( isset( $tax_rate['name'] ) ? $tax_rate['name'] : '' ) . '-' .
				( isset( $tax_rate['shipping'] ) ? $tax_rate['shipping'] : '' ) . '-' .
				( isset( $tax_rate['priority'] ) ? $tax_rate['priority'] : '' ) . '-' .
				( isset( $tax_rate['city'] ) ? $tax_rate['city'] : '' ) . '-' .
				( isset( $tax_rate['postal_code'] ) ? $tax_rate['postal_code'] : '' )
			)
		);

		$id = $max_id + 1 + $crc;

		static::$tax_rates[ $id ] = TaxRate::clone_with_id( $id, $tax_rate );

		return $id;
	}

	/**
	 * Finds a tax rate.
	 *
	 * @param TaxRate $tax_rate Tax rate to find.
	 */
	public static function find_tax_rate( TaxRate $tax_rate ) {
		$result = static::find_by_properties(
			array(
				'country' => $tax_rate['country'],
				'state'   => $tax_rate['state'],
				'rate'    => $tax_rate['rate'],
				'name'    => $tax_rate['name'],
			)
		);
		return 0 === count( $result ) ? null : $result[0];
	}

	/**
	 * Search for a given tax rate and, if not found create id.
	 *
	 * @param TaxRate $tax_rate Tax rate to find.
	 * @param bool    $shipping Tax rates applies to shipping.
	 */
	public static function create_or_read_id( TaxRate $tax_rate, $shipping = false ) {
		$existing_tax_rate = static::find_tax_rate( $tax_rate );

		if ( $existing_tax_rate ) {
			$rate_id                       = $existing_tax_rate['id'];
			$existing_tax_rate['shipping'] = $shipping;
		} else {
			$rate_id = static::create( $tax_rate );
		}

		return $rate_id;
	}

	/**
	 * Search for a given tax rate properties and, if not found create id.
	 *
	 * @param string $country Tax rate country.
	 * @param string $state Tax rate state.
	 * @param float  $percentage_decimal Tax rate percentage.
	 * @param string $name Tax rate name.
	 */
	public static function find_or_create( $country, $state, $percentage_decimal, $name ) {

		$tax_rate = new TaxRate(
			null,
			$country,
			$state,
			(float) $percentage_decimal,
			$name,
			true,
			'',
			'',
			''
		);

		$rate_id = static::create_or_read_id( $tax_rate, false );

		return $rate_id;
	}

	/**
	 * Finds a tax rate by its code.
	 *
	 * @param string $rate_code Tax rate code.
	 */
	public static function read_by_code( $rate_code ) {
		foreach ( static::$tax_rates as $tax_rate ) {
			if ( method_exists( $tax_rate, 'get_code' ) && $tax_rate->get_code() === $rate_code ) {
				return $tax_rate;
			}
		}

		return null;
	}

	/**
	 * Finds a tax rate by a given property name and value.
	 *
	 * @param string $column_name Tax rate property name.
	 * @param mixed  $column_value Tax rate property value.
	 */
	protected static function read_by_column( $column_name, $column_value ) {
		foreach ( static::$tax_rates as $tax_rate ) {
			if ( $tax_rate[ $column_name ] === $column_value ) {
				return $tax_rate;
			}
		}

		return null;
	}

	/**
	 * Find tax rates by their properties
	 *
	 * @param array $properties Tax rates properties.
	 */
	public static function find_by_properties( $properties ) {
		$result = array();

		foreach ( static::$tax_rates as $tax_rate ) {
			foreach ( $properties as $property_name => $property_value ) {
				// phpcs:ignore
				if ( $tax_rate[ $property_name ] != $property_value ) {
					continue 2;
				}
			}

			$result[] = $tax_rate;
		}

		return $result;
	}

	/**
	 * Finds a tax rate by its rate id.
	 *
	 * @param int $rate_id Tax rate id.
	 */
	public static function read( $rate_id ) {
		return static::read_by_column( 'id', $rate_id );
	}

	/**
	 * Finds a tax rate by rate id and returns its code.
	 *
	 * @param int $rate_id Tax rate id.
	 */
	public static function read_rate_code( $rate_id ) {
		$rate = static::read( $rate_id );

		if ( ! $rate ) {
			return;
		}

		$rate_code = $rate->get_code();

		return $rate_code;
	}

	/**
	 * Finds a tax rate by rate id and returns its label.
	 *
	 * @param int $rate_id Tax rate id.
	 */
	public static function read_rate_label( $rate_id ) {
		$rate = static::read( $rate_id );

		if ( ! $rate ) {
			return '';
		}

		return $rate['name'];
	}

	/**
	 * Returns false for a tax rate.
	 *
	 * @param int $rate_id Tax rate id.
	 */
	public static function read_rate_compound( $rate_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return 'no';
	}
}
