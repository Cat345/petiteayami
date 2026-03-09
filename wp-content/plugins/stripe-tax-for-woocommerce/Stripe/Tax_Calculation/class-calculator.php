<?php
/**
 * Tax calculator class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\SDK\lib\StripeClient;
use Stripe\StripeTaxForWooCommerce\Stripe\CalculateTax;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTax_Tax_Rate_Controller;
use Throwable;

/**
 * Tax calculator class.
 */
abstract class Calculator {
	/**
	 * Tax calculations cache
	 *
	 * @var array Tax calculations cache.
	 */
	public static $calculations = array();

	/**
	 * Calculates and returns taxes based in and tax calculation input.
	 *
	 * @param Input  $calculation_input Tax calculation input.
	 * @param string $cache_key Cache key.
	 */
	public static function calculate( Input $calculation_input, $cache_key ) {
		unset( static::$calculations[ $cache_key ] );

		$calculation_input_payload = $calculation_input->get_payload();
		$calculate_tax             = new CalculateTax( Options::get_current_mode_key(), $calculation_input_payload );

		$api_calculation_response = $calculate_tax->get_response();

		\Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTaxTaxRateMemRepo::$tax_rates = array();

		StripeTax_Tax_Rate_Controller::create_tax_rates_from_api_calculation_result( $api_calculation_response );

		$tax_calculation_result = Result::from_api_calculation_result( $api_calculation_response );

		static::$calculations[ $cache_key ] = array(
			'input'  => $calculation_input,
			'result' => $tax_calculation_result,
		);

		return $tax_calculation_result;
	}
}
