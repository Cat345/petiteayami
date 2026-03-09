<?php
/**
 * Tax rate repo
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Result;

/**
 * StripeTax_Tax_Rate_Controller class.
 */
abstract class StripeTax_Tax_Rate_Controller {

	/**
	 * Creates and saves tax rates returned by a Stripe API
	 *
	 * @param object $api_calculation_result A Stripe API tax calculation result.
	 */
	public static function create_tax_rates_from_api_calculation_result( $api_calculation_result ) {
		foreach ( $api_calculation_result->line_items as $i => $api_line_item ) {
			static::create_tax_rate_from_api_calculation_result_line( $api_line_item, false );
		}

		if ( ! is_null( $api_calculation_result->shipping_cost ) ) {
			// @todo Create a generic shiping tax rate
			// Older tax calculation don't have a shipping cost tax breakdown
			self::create_tax_rate_from_api_calculation_result_line( $api_calculation_result->shipping_cost, true );
		}
	}

	/**
	 * Creates tax rates taken from a Stripe API tax calculation response line
	 *
	 * @param object $api_calculation_result_line A Stripe API tax calculation response line.
	 * @param bool   $shipping Taxc rates applies to shipping.
	 */
	public static function create_tax_rate_from_api_calculation_result_line( $api_calculation_result_line, $shipping ) {
		foreach ( $api_calculation_result_line->tax_breakdown as $tax_breakdown_line ) {
			self::save_tax_rate_from_api_calculation_result_tax_breakdown_line(
				$tax_breakdown_line,
				$shipping
			);
		}
	}

	/**
	 * Saves tax rates taken from a Stripe API tax calculation response line tax breakdown
	 *
	 * @param object $tax_breakdown_line A Stripe API tax calculation response line tax breakdown.
	 * @param bool   $shipping Taxc rates applies to shipping.
	 */
	public static function save_tax_rate_from_api_calculation_result_tax_breakdown_line( $tax_breakdown_line, $shipping ) {
		$tax_rate = self::create_tax_rate_from_api_calculation_result_tax_breakdown_line( $tax_breakdown_line, $shipping );

		$tax_item = array(
			'rate_code'    => $tax_rate->get_code(),
			'rate_label'   => $tax_rate['name'],
			'rate_percent' => $tax_rate['rate'],
		);

		$tax_item['rate_id'] = StripeTaxTaxRateMemRepo::create_or_read_id(
			$tax_rate,
			$shipping
		);

		$test = StripeTaxTaxRateMemRepo::$tax_rates;

		return $tax_item;
	}

	/**
	 * Create a tax rate from a api calculation result line item tax breakdown if it does not exist
	 *
	 * @param object $tax_breakdown_line Tax calculation line item tax breakdown.
	 * @param bool   $shipping Taxc rates applies to shipping.
	 */
	public static function create_tax_rate_from_api_calculation_result_tax_breakdown_line( $tax_breakdown_line, $shipping ) {
		$tax_rate_details = $tax_breakdown_line->tax_rate_details;

		$tax_jurisdiction = $tax_breakdown_line->jurisdiction;
		$priority         = Result::JURISDICTION_LEVEL_PRIORITY[ strtolower( $tax_jurisdiction->level ) ];

		$jurisdiction_display_name = $tax_jurisdiction->display_name;

		$rate_percent = $tax_rate_details ? $tax_rate_details->percentage_decimal : '0.00';
		$name         = $tax_jurisdiction->display_name . ( $tax_rate_details && $tax_rate_details->display_name ? ' ' . $tax_rate_details->display_name : '' );

		$tax_rate = new TaxRate(
			null,
			$tax_jurisdiction->country,
			$tax_jurisdiction->state,
			(float) $rate_percent,
			$name,
			$shipping,
			$priority,
			'',
			''
		);

		return $tax_rate;
	}

	/**
	 * Escapes a tax rate code part
	 *
	 * @param string $rate_code_component Tax code part.
	 */
	protected static function escape_rate_code_component( $rate_code_component ) {
		return str_replace( '-', ' ', $rate_code_component );
	}

	/**
	 * Finds a tax rate by its property taken from an result line tax breakdown line
	 *
	 * @param object $tax_breakdown_line A tax breakdown line.
	 */
	public static function find_by_api_tax_breakdown( $tax_breakdown_line ) {
		if ( $tax_breakdown_line->jurisdiction ) {
			$properties = array(
				'country' => $tax_breakdown_line->jurisdiction->country,
				'state'   => $tax_breakdown_line->jurisdiction->state,
			);
		}

		if ( $tax_breakdown_line->tax_rate_details ) {
			$properties['rate'] = (float) $tax_breakdown_line->tax_rate_details->percentage_decimal;
		} else {
			$properties['rate'] = 0;
		}

		$tax_rates = StripeTaxTaxRateMemRepo::find_by_properties( $properties );

		$tax_rate = $tax_rates[0];

		$existing_tax_rate = StripeTaxTaxRateMemRepo::find_tax_rate( $tax_rate );

		return $existing_tax_rate;
	}

	/**
	 * Creates ax rates from an order tax details
	 *
	 * @param object $order The order.
	 */
	public static function create_tax_rates_from_order( $order ) {
	}
}
