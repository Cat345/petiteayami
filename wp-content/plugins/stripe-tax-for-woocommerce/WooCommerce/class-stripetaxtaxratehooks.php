<?php
/**
 * Tax rate hooks handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * StripeTaxTaxRateHooks class.
 */
class StripeTaxTaxRateHooks {
	const FILTERS = array(
		'find_rates',
		'rate_code',
		'rate_label',
		'rate_compound',
	);

	/**
	 * Register WooCommerce filters
	 */
	public static function register(): void {
		add_filter( 'woocommerce_rate_code', array( static::class, 'rate_code' ), 10, 2 );
		add_filter( 'woocommerce_rate_label', array( static::class, 'rate_label' ), 10, 2 );
		add_filter( 'woocommerce_rate_compound', array( static::class, 'rate_compound' ), 10, 2 );

		add_filter( 'woocommerce_find_rates', array( static::class, 'find_rates' ), 10, 2 );
	}

	/**
	 * Passthrough; read-only.
	 *
	 * @param array $rates Rates.
	 * @param array $args  Args passed.
	 */
	public static function find_rates( $rates, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return array();
	}

	/**
	 * Override displayed code if present; else passthrough.
	 *
	 * @param string $rate_code Rate code.
	 * @param int    $rate_id Rate id.
	 */
	public static function rate_code( $rate_code, $rate_id ) {
		$stripe_code = StripeTaxTaxRateMemRepo::read_rate_code( $rate_id );

		return $stripe_code ? $stripe_code : $rate_code;
	}

	/**
	 * Override label if present; else passthrough.
	 *
	 * @param string $rate_label Rate label.
	 * @param int    $rate_id Rate id.
	 */
	public static function rate_label( $rate_label, $rate_id ) {
		$stripe_label = StripeTaxTaxRateMemRepo::read_rate_label( $rate_id );

		return $stripe_label ? $stripe_label : $rate_label;
	}

	/**
	 * Override compound if present; else passthrough.
	 *
	 * @param string $rate_compound Rate label.
	 * @param int    $rate_id Rate id.
	 */
	public static function rate_compound( $rate_compound, $rate_id ) {
		$stripe_compound = StripeTaxTaxRateMemRepo::read_rate_compound( $rate_id );

		return $stripe_compound ? $stripe_compound : $rate_compound;
	}
}
