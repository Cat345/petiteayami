<?php
/**
 * Cart_Controller class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Calculator;
use Stripe\StripeTaxForWooCommerce\Utils\Amount_Utility;
use Stripe\StripeTaxForWooCommerce\StripeTax_Options;

use WC_Cart;

use Throwable;

/**
 * Cart_Controller class.
 */
abstract class Cart_Controller {
	/**
	 * Calculates taxes for a given cart and tax behavior.
	 *
	 * @param WC_Cart $cart The cart.
	 * @param string  $tax_behavior Tax behavior.
	 */
	public static function calculate_taxes( WC_Cart $cart, string $tax_behavior ) {
		$customer = $cart->get_customer();

		$tax_calculation_input      = Cart_Input::from_cart( $cart, $tax_behavior );
		$non_taxable_shipping_total = Cart_Input::get_non_taxable_shipping_cost_amount( $cart );

		$tax_calculation_result = Calculator::calculate( $tax_calculation_input, 'cart' );

		$currency = $tax_calculation_result->currency;

		$cart_contents_total = 0;
		$cart_contents_tax   = 0;
		$cart_contents_taxes = array();

		$discount_total = 0;
		$discount_tax   = 0;

		$fees_total     = 0;
		$fees_total_tax = 0;

		$shipping_tax   = $tax_calculation_result->shipping_cost->amount_tax;
		$shipping_taxes = $tax_calculation_result->shipping_cost->amount_tax_breakdown;
		$shipping_total = $tax_calculation_result->shipping_cost->amount;

		foreach ( $cart->cart_contents as $cart_item_key => &$cart_item ) {
			$reference = Cart_Input::build_item_reference_by_type( $cart_item, 'line_item' );

			$tax_calculation_line = $tax_calculation_result->get_line_item_by_reference( $reference );

			if ( ! $tax_calculation_line ) {
				continue;
			}

			$amount_subtotal     = $tax_calculation_line['amount_subtotal'];
			$amount              = $tax_calculation_line['amount'];
			$amount_subtotal_tax = $tax_calculation_line['amount_subtotal_tax'];
			$amount_tax          = $tax_calculation_line['amount_tax'];

			$discount_total += $amount_subtotal - $amount;
			$discount_tax   += $amount_subtotal_tax - $amount_tax;

			$cart_item['line_subtotal_tax'] = $amount_subtotal_tax;
			$cart_item['line_tax']          = $amount_tax;

			$cart_contents_total += $amount;
			$cart_contents_tax   += $amount_tax;

			$cart_item['line_tax_data'] = array(
				'total'    => $tax_calculation_line['amount_tax_breakdown'],
				'subtotal' => $tax_calculation_line['amount_subtotal_tax_breakdown'],
			);

			foreach ( $cart_item['line_tax_data']['total'] as $rate_id => $tax_amount ) {
				if ( ! isset( $cart_contents_taxes[ $rate_id ] ) ) {
					$cart_contents_taxes[ $rate_id ] = 0;
				}

				$cart_contents_taxes[ $rate_id ] += $tax_amount;
			}

			if ( Cart_Input::TAX_BEHAVIOR_INCLUSIVE === $tax_calculation_line->tax_behavior ) {
				$cart_item['line_subtotal'] = $amount_subtotal;
				$cart_item['line_total']    = $amount;
			}
		}

		$fees      = $cart->get_fees();
		$fee_taxes = array();

		foreach ( $fees as $fee ) {
			$reference = Cart_Input::build_item_reference_by_type( $fee, 'fee' );

			$tax_calculation_line = $tax_calculation_result->get_line_item_by_reference( $reference );

			if ( ! $tax_calculation_line ) {
				continue;
			}

			$fee->tax      = $tax_calculation_line['amount_tax'];
			$fee->tax_data = $tax_calculation_line['amount_tax_breakdown'];

			if ( Cart_Input::TAX_BEHAVIOR_INCLUSIVE === $tax_calculation_line->tax_behavior ) {
				$fee->total -= $fee->tax;
			}
			foreach ( $fee->tax_data as $rate_id => $tax_amount ) {
				if ( ! isset( $fee_taxes[ $rate_id ] ) ) {
					$fee_taxes[ $rate_id ] = 0;
				}

				$fee_taxes[ $rate_id ] += $tax_amount;
			}
		}

		$cart->set_discount_total( Amount_Utility::round( $discount_total ) );
		$cart->set_discount_tax( Amount_Utility::round( $discount_tax ) );

		$cart->set_total( $tax_calculation_result->amount_total );
		$cart->set_total_tax( $tax_calculation_result->tax_amount_inclusive + $tax_calculation_result->tax_amount_exclusive );

		$cart->set_shipping_tax( $shipping_tax );
		$cart->set_shipping_taxes( $shipping_taxes );
		$cart->set_shipping_total( $shipping_total );

		$cart->set_fee_tax( $tax_calculation_result->fees_tax_amount_inclusive + $tax_calculation_result->fees_tax_amount_exclusive );
		$cart->set_fee_taxes( $fee_taxes );

		$cart->set_cart_contents_tax( $cart_contents_tax );
		$cart->set_cart_contents_taxes( $cart_contents_taxes );
		$cart->set_cart_contents_total( $cart_contents_total );

		$cart->set_subtotal_tax( $tax_calculation_result->subtotal_tax_amount_inclusive + $tax_calculation_result->subtotal_tax_amount_exclusive );
		$cart->set_subtotal( $tax_calculation_result->amount_subtotal );
	}
}
