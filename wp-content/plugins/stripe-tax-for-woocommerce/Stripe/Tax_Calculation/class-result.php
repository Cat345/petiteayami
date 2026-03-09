<?php
/**
 * Tax calculation operations result class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTax_Tax_Rate_Controller;
use Stripe\StripeTaxForWooCommerce\Utils\Amount_Utility;

/**
 * Tax calculation operations result class.
 */
class Result extends Data {
	const JURISDICTION_LEVEL_PRIORITY = array(
		'country'  => 1000,
		'state'    => 1001,
		'county'   => 1002,
		'district' => 1003,
		'city'     => 1004,
	);

	/**
	 * Stripe tax calculation ID
	 *
	 * @var string Stripe tax calculation ID.
	 */
	public $id = '';

	/**
	 * Tax calculation total amount
	 *
	 * @var float Tax calculation total amount.
	 */
	public $amount_total = 0;

	/**
	 * Tax calculation subtotal amount
	 *
	 * @var float Tax calculation subtotal amount.
	 */
	public $amount_subtotal = 0;

	/**
	 * Tax calculation fees subtotal amount
	 *
	 * @var float Tax calculation fees subtotal amount.
	 */
	public $amount_fees_subtotal = 0;

	/**
	 * Tax calculation tax inclusive amount
	 *
	 * @var float Tax calculation tax inclusive amount.
	 */
	public $tax_amount_inclusive = 0;

	/**
	 * Tax calculation tax exclusive amount
	 *
	 * @var float Tax calculation tax exclusive amount.
	 */
	public $tax_amount_exclusive = 0;

	/**
	 * Tax calculation subtotal tax inclusive amount
	 *
	 * @var float Tax calculation subtotal tax inclusive amount.
	 */
	public $subtotal_tax_amount_inclusive = 0;

	/**
	 * Tax calculation subtotal tax exclusive amount
	 *
	 * @var float Tax calculation subtotal tax exclusive amount.
	 */
	public $subtotal_tax_amount_exclusive = 0;

	/**
	 * Tax calculation fee tax inclusive amount
	 *
	 * @var float Tax calculation fee tax inclusive amount.
	 */
	public $fees_tax_amount_inclusive = 0;

	/**
	 * Tax calculation fee tax exclusive amount
	 *
	 * @var float Tax calculation fee tax exclusive amount.
	 */
	public $fees_tax_amount_exclusive = 0;

	/**
	 * Shipping price
	 *
	 * @var string Shipping price.
	 */
	public $shipping_rate = null;

	/**
	 * Creates a new tax calculation result
	 *
	 * @param string $id Stripe tax calculation ID.
	 * @param string $currency Currency.
	 * @param string $customer_details Customer details (shipping address).
	 * @param array  $line_items Cart or order line items.
	 * @param float  $shipping_cost Shipping cost.
	 * @param string $shipping_rate Shipping_rate.
	 * @param float  $amount_total Total amount.
	 * @param float  $amount_subtotal Subtotal amount.
	 * @param float  $amount_fees_subtotal Fee subtotal amount.
	 * @param float  $tax_amount_inclusive Tax calculation tax inclusive amount.
	 * @param float  $tax_amount_exclusive Tax calculation tax exclusive amount.
	 * @param float  $subtotal_tax_amount_inclusive Tax calculation tax subtotal inclusive amount.
	 * @param float  $subtotal_tax_amount_exclusive Tax calculation tax subtotal exclusive amount.
	 * @param float  $fees_tax_amount_inclusive Tax calculation tax fee inclusive amount.
	 * @param float  $fees_tax_amount_exclusive Tax calculation tax fee exclusive amount.
	 */
	public function __construct(
		$id,
		$currency,
		$customer_details,
		$line_items,
		$shipping_cost,
		$shipping_rate,
		$amount_total,
		$amount_subtotal,
		$amount_fees_subtotal,
		$tax_amount_inclusive,
		$tax_amount_exclusive,
		$subtotal_tax_amount_inclusive,
		$subtotal_tax_amount_exclusive,
		$fees_tax_amount_inclusive,
		$fees_tax_amount_exclusive
	) {
		parent::__construct(
			array(
				'currency'         => strtolower( $currency ),
				'customer_details' => $customer_details,
				'line_items'       => $line_items,
				'shipping_cost'    => $shipping_cost,
			)
		);

		$this->id                            = $id;
		$this->amount_total                  = $amount_total;
		$this->amount_subtotal               = $amount_subtotal;
		$this->amount_fees_subtotal          = $amount_fees_subtotal;
		$this->tax_amount_inclusive          = $tax_amount_inclusive;
		$this->tax_amount_exclusive          = $tax_amount_exclusive;
		$this->subtotal_tax_amount_inclusive = $subtotal_tax_amount_inclusive;
		$this->subtotal_tax_amount_exclusive = $subtotal_tax_amount_exclusive;
		$this->fees_tax_amount_inclusive     = $fees_tax_amount_inclusive;
		$this->fees_tax_amount_exclusive     = $fees_tax_amount_exclusive;
		$this->shipping_rate                 = $shipping_rate;
	}

	/**
	 * Creates a new tax calculation result from an tax calculation API response
	 *
	 * @param object $api_calculation The tax calculation API response.
	 */
	public static function from_api_calculation_result( object $api_calculation ) {

		$postal_code = $api_calculation->customer_details->address->postal_code;
		$city        = $api_calculation->customer_details->address->city;

		$api_customer_details_address = $api_calculation->customer_details->address->toArray();

		$customer_details = new Customer_Details(
			$api_customer_details_address['country'],
			$api_customer_details_address['state'],
			$api_customer_details_address['city'],
			$api_customer_details_address['postal_code'],
			$api_customer_details_address['line1'],
			$api_customer_details_address['line2'],
			$api_calculation->customer_details->address_source,
			$api_calculation->customer_details->taxability_override
		);

		if ( ! is_null( $api_calculation->shipping_cost ) ) {
			$api_shipping_cost = $api_calculation->shipping_cost;

			$shipping_cost_amount       = Amount_Utility::from_cents( $api_shipping_cost->amount, $api_calculation->currency );
			$shipping_cost_quantity     = 1;
			$shipping_cost_amount_tax   = Amount_Utility::from_cents( $api_shipping_cost->amount_tax, $api_calculation->currency );
			$shipping_cost_tax_rate     = isset( $api_shipping_cost->tax_code ) ? $api_shipping_cost->tax_code : '';
			$shipping_cost_tax_behavior = $api_shipping_cost->tax_behavior;

			if ( static::TAX_BEHAVIOR_INCLUSIVE === $shipping_cost_tax_behavior ) {
				$shipping_cost_amount -= $shipping_cost_amount_tax;
			}

			$shipping_cost_tax_breakdown = self::tax_breakdown_from_api_calculation_result_line_item(
				$api_shipping_cost
			);

			foreach ( $shipping_cost_tax_breakdown as $rate_id => $tax_breakdown_line ) {
				$tax_amount = Amount_Utility::from_cents( $tax_breakdown_line['amount_tax'], $api_calculation->currency );

				$shipping_cost_tax_breakdown[ $rate_id ]['amount_tax']          = $tax_amount;
				$shipping_cost_tax_breakdown[ $rate_id ]['amount_subtotal_tax'] = $tax_amount;
			}
		} else {
			$shipping_cost_amount        = 0;
			$shipping_cost_quantity      = 0;
			$shipping_cost_tax_behavior  = '';
			$shipping_cost_tax_rate      = '';
			$shipping_cost_amount_tax    = 0;
			$shipping_cost_tax_breakdown = array();
		}

		$shipping_cost = new Result_Line_Item(
			self::SHIPPING_COST_REFERENCE,
			$shipping_cost_amount,
			$shipping_cost_quantity,
			$shipping_cost_tax_behavior,
			$shipping_cost_tax_rate,
			$shipping_cost_amount,
			'',
			$shipping_cost_amount_tax,
			$shipping_cost_amount_tax,
			$shipping_cost_tax_breakdown
		);

		$line_items = array();

		$subtotal                      = 0;
		$subtotal_tax_amount_exclusive = 0;
		$subtotal_tax_amount_inclusive = 0;

		$fees_subtotal             = 0;
		$fees_tax_amount_exclusive = 0;
		$fees_tax_amount_inclusive = 0;

		foreach ( $api_calculation->line_items as $i => $api_line_item ) {
			$item_type = self::get_line_item_type_by_reference( $api_line_item->reference );

			$amount          = $api_line_item->amount;
			$amount_subtotal = (int) $api_line_item['metadata']['amount_subtotal'];
			$has_discount    = ( $amount !== $amount_subtotal );

			$tax_breakdown = static::tax_breakdown_from_api_calculation_result_line_item(
				$api_line_item
			);

			$line_amount_subtotal_tax = static::calculate_amount_subtotal_tax( $api_line_item, $tax_breakdown, $has_discount );

			foreach ( $tax_breakdown as $rate_id => $tax_breakdown_line ) {
				$amount_tax          = Amount_Utility::from_cents( $tax_breakdown_line['amount_tax'], $api_calculation->currency );
				$amount_subtotal_tax = Amount_Utility::from_cents( $tax_breakdown_line['amount_subtotal_tax'], $api_calculation->currency );

				$tax_breakdown[ $rate_id ]['amount_tax']          = $amount_tax;
				$tax_breakdown[ $rate_id ]['amount_subtotal_tax'] = $amount_subtotal_tax;
			}

			if ( static::TAX_BEHAVIOR_INCLUSIVE === $api_line_item->tax_behavior ) {
				$amount          -= $api_line_item->amount_tax;
				$amount_subtotal -= $line_amount_subtotal_tax;
			}

			$line_items[] = new Result_Line_Item(
				$api_line_item->reference,
				Amount_Utility::from_cents( $amount, $api_calculation->currency ),
				$api_line_item->quantity,
				$api_line_item->tax_behavior,
				$api_line_item->tax_code,
				Amount_Utility::from_cents( $amount_subtotal, $api_calculation->currency ),
				$api_line_item->id,
				Amount_Utility::from_cents( $line_amount_subtotal_tax, $api_calculation->currency ),
				Amount_Utility::from_cents( $api_line_item->amount_tax, $api_calculation->currency ),
				$tax_breakdown
			);

			if ( 'line_item' === $item_type ) {
				$subtotal += $amount_subtotal;

				if ( self::TAX_BEHAVIOR_EXCLUSIVE === $api_line_item->tax_behavior ) {
					$subtotal_tax_amount_exclusive += $line_amount_subtotal_tax;
				} else {
					$subtotal_tax_amount_inclusive += $line_amount_subtotal_tax;
				}
			} else { // Fee.
				$fees_subtotal += $amount_subtotal;

				if ( self::TAX_BEHAVIOR_EXCLUSIVE === $api_line_item->tax_behavior ) {
					$fees_tax_amount_exclusive += $line_amount_subtotal_tax;
				} else {
					$fees_tax_amount_inclusive += $line_amount_subtotal_tax;
				}
			}
		}

		// @phpstan-ignore-next-line
		return new static(
			$api_calculation->id,
			$api_calculation->currency,
			$customer_details,
			$line_items,
			$shipping_cost,
			null,
			Amount_Utility::from_cents( $api_calculation->amount_total, $api_calculation->currency ),
			Amount_Utility::from_cents( $subtotal, $api_calculation->currency ),
			Amount_Utility::from_cents( $fees_subtotal, $api_calculation->currency ),
			Amount_Utility::from_cents( $api_calculation->tax_amount_inclusive, $api_calculation->currency ),
			Amount_Utility::from_cents( $api_calculation->tax_amount_exclusive, $api_calculation->currency ),
			Amount_Utility::from_cents( $subtotal_tax_amount_inclusive, $api_calculation->currency ),
			Amount_Utility::from_cents( $subtotal_tax_amount_exclusive, $api_calculation->currency ),
			Amount_Utility::from_cents( $fees_tax_amount_inclusive, $api_calculation->currency ),
			Amount_Utility::from_cents( $fees_tax_amount_exclusive, $api_calculation->currency )
		);
	}

	/**
	 * Calculates a line item subtotal amount.
	 *
	 * @param object $api_line_item Tax calculation API response line item.
	 * @param object $tax_breakdown Tax calculation API response line item tax breakdown.
	 * @param bool   $has_discount Shows if th eline item has a discount.
	 */
	protected static function calculate_amount_subtotal_tax( $api_line_item, &$tax_breakdown, $has_discount ) {
		$amount                   = $api_line_item->amount;
		$amount_subtotal          = (int) $api_line_item['metadata']['amount_subtotal'];
		$line_amount_subtotal_tax = 0;

		if ( ! $has_discount ) {
			$line_amount_subtotal_tax = $api_line_item->amount_tax;

			foreach ( $tax_breakdown as $rate_id => $tax_breakdown_line ) {
				$tax_breakdown[ $rate_id ]['amount_subtotal_tax'] = $tax_breakdown_line['amount_tax'];
			}
		} else {
			if ( self::TAX_BEHAVIOR_EXCLUSIVE === $api_line_item->tax_behavior ) {
				$line_taxable_amount_subtotal = $amount_subtotal;
			} else {
				$line_taxable_percent = 0;

				foreach ( $tax_breakdown as $rate_id => $tax_breakdown_line ) {
					$line_taxable_percent += $tax_breakdown_line['rate_percent'];
				}

				// No rounding.
				$line_taxable_amount_subtotal = ( $amount_subtotal * 100 ) / ( 100 + $line_taxable_percent );
			}

			foreach ( $tax_breakdown as $rate_id => $tax_breakdown_line ) {
				$amount_subtotal_tax = round( $line_taxable_amount_subtotal * $tax_breakdown_line['rate_percent'] / 100 );

				$line_amount_subtotal_tax += $amount_subtotal_tax;

				$tax_breakdown[ $rate_id ]['amount_subtotal_tax'] = $amount_subtotal_tax;
			}
		}

		return $line_amount_subtotal_tax;
	}

	/**
	 * Calculates a result line item tax breakdown from an tax calculation API response line item
	 *
	 * @param object $api_line_item Tax calculation response line item.
	 */
	protected static function tax_breakdown_from_api_calculation_result_line_item( $api_line_item ) {
		$api_line_item_breakdown = $api_line_item->tax_breakdown;
		$tax_breakdown           = array();

		foreach ( $api_line_item_breakdown as $tax_breakdown_line ) {
			$tax_rate = StripeTax_Tax_Rate_Controller::find_by_api_tax_breakdown( $tax_breakdown_line );

			if ( ! $tax_rate || 0.0 === (float) $tax_rate['rate'] || 0 === $tax_breakdown_line->amount ) {
				continue;
			}

			$line_tax_item = array(
				'rate_id'      => $tax_rate['id'],
				'rate_code'    => $tax_rate->get_code(),
				'rate_label'   => $tax_rate['name'],
				'rate_percent' => $tax_rate['rate'],
			);

			$rate_id = $line_tax_item['rate_id'];

			if ( isset( $tax_breakdown[ $rate_id ] ) ) {
				$tax_breakdown[ $rate_id ]['amount_tax'] += $tax_breakdown_line->amount;
				continue;
			}
			$tax_breakdown[ $rate_id ]                 = $line_tax_item;
			$tax_breakdown[ $rate_id ]['amount_tax']   = $tax_breakdown_line->amount;
			$tax_breakdown[ $rate_id ]['rate_percent'] = $line_tax_item['rate_percent'];
		}

		return $tax_breakdown;
	}
}
