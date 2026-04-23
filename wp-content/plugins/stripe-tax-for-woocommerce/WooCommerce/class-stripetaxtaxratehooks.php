<?php
/**
 * Tax rate hooks handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WordPress\Hook_Handlers;

/**
 * StripeTaxTaxRateHooks class.
 */
class StripeTaxTaxRateHooks extends Hook_Handlers {
	const FILTERS = array(
		'find_rates',
		'rate_code',
		'rate_label',
		'rate_compound',
	);

	/**
	 * Passthrough; read-only.
	 *
	 * @param array $rates Rates.
	 * @param array $args  Args passed.
	 */
	public static function find_rates( $rates, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! self::is_enabled() ) {
			return $rates;
		}
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
