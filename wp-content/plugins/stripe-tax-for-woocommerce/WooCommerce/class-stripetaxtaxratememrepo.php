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
	protected static $tax_rates = array();

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

		$id = $max_id + 1;

		static::$tax_rates[ $id ] = TaxRate::from_aggregate_key( $id, $tax_rate->get_aggregate_key() );

		return $id;
	}

	/**
	 * Finds a tax rate.
	 *
	 * @param TaxRate $tax_rate Tax rate to find.
	 */
	protected static function find_tax_rate( TaxRate $tax_rate ) {
		$rate_aggregate_key = $tax_rate->get_aggregate_key();

		foreach ( static::$tax_rates as $tax_rate_array ) {
			if ( $tax_rate_array->get_aggregate_key() === $rate_aggregate_key ) {
				return $tax_rate_array;
			}
		}

		return null;
	}

	/**
	 * Search for a given tax rate and, if not found create id.
	 *
	 * @param TaxRate $tax_rate Tax rate to find.
	 */
	public static function create_or_read_id( TaxRate $tax_rate ) {
		$existing_tax_rate = static::find_tax_rate( $tax_rate );

		if ( $existing_tax_rate ) {
			$rate_id = $existing_tax_rate['id'];
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
			'',
			$country,
			$state,
			(float) $percentage_decimal,
			$name,
			1,
			'',
			''
		);

		$rate_id = static::create_or_read_id( $tax_rate );

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
