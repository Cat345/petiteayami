<?php
/**
 * Order_Controller class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Calculator;
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxLogger;
use Stripe\StripeTaxForWooCommerce\Utils\Amount_Utility;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeOrderItemTax;

use WC_Customer;
use WC_Order;
use WC_Order_Item_Shipping;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;

use Throwable;

/**
 * Order_Controller class.
 */
abstract class Order_Controller {
	/**
	 * Validates that order line items and tax calculation line items refer to the same entries.
	 *
	 * @param WC_Order $order The order.
	 * @param object   $tax_calculation The tax calculation result.
	 */
	private static function has_matching_line_items( WC_Order $order, $tax_calculation ) {
		if ( ! isset( $tax_calculation['line_items'] ) || ! is_array( $tax_calculation['line_items'] ) ) {
			return false;
		}

		$order_lines  = array();
		$result_lines = array();

		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			$reference = Order_Input::build_item_reference_by_type( $item );
			$quantity  = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1;

			$order_lines[] = $reference . '|' . (string) $quantity;
		}

		foreach ( $tax_calculation['line_items'] as $result_line ) {
			$reference = isset( $result_line['reference'] ) ? $result_line['reference'] : null;

			if ( ! is_string( $reference ) || '' === $reference ) {
				return false;
			}

			$quantity       = isset( $result_line['quantity'] ) ? (int) $result_line['quantity'] : 1;
			$result_lines[] = $reference . '|' . (string) $quantity;
		}

		if ( count( $order_lines ) !== count( $result_lines ) ) {
			StripeTaxLogger::log_error(
				'Order line items count mismatch: order_lines count = ' . count( $order_lines ) .
				', result_lines count = ' . count( $result_lines )
			);
			return false;
		}

		sort( $order_lines );
		sort( $result_lines );

		return $order_lines === $result_lines;
	}

	/**
	 * Calculates taxes for a given order.
	 *
	 * @param WC_Order $order The order.
	 * @param array    $tax_location_override WC args.
	 * @param int      $customer_user_id_override Customer user ID.
	 */
	public static function calculate_taxes( WC_Order $order, $tax_location_override, $customer_user_id_override ) {

		$tax_calculation_input = Order_Input::from_order( $order, $tax_location_override, $customer_user_id_override );

		$order_id = $order->get_id();

		if ( ! $order_id ) {
			$order->save();
			$order_id = $order->get_id();
		}

		if ( ! isset( $tax_calculation_input['line_items'] ) || ! is_array( $tax_calculation_input['line_items'] ) ) {
			return;
		}
		$tax_calculation_result = Calculator::calculate( $tax_calculation_input, $order_id );

		$order_id = $order->get_id();

		static::create_order_tax_items_from_tax_calculation_result( $order, $tax_calculation_result );

		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
			/**
			 * Current order item.
			 *
			 * @var \WC_Order_Item_Product|\WC_Order_Item_Fee|\WC_Order_Item_Shipping $item
			 */
			$item_reference = Order_Input::build_item_reference_by_type( $item );
			$result_line    = $tax_calculation_result->get_line_item_by_reference( $item_reference );

			if ( ! $result_line ) {
				continue;
			}

			if ( Result::TAX_BEHAVIOR_INCLUSIVE !== $result_line->tax_behavior ) {
				continue;
			}

			$item->set_total( $result_line->amount );

			if ( method_exists( $item, 'get_subtotal' ) ) {
				$item->set_subtotal( $result_line->amount_subtotal );
			}

			$item->save();
		}

		$result_line = $tax_calculation_result['shipping_cost'];

		if ( $result_line && Result::TAX_BEHAVIOR_INCLUSIVE === $result_line->tax_behavior ) {
			$order->set_shipping_total( $result_line->amount );
		}
	}

	/**
	 * Creates an order tax items from a tax calculation result.
	 *
	 * @param object $order The order.
	 * @param object $tax_calculation Tax calculation result.
	 */
	public static function create_order_tax_items_from_tax_calculation_result( $order, $tax_calculation ) {
		if ( ! $tax_calculation ) {
			return;
		}
		// find all rate ids in $tax_calculation.
		$rate_ids_to_create = array();

		foreach ( array(
			'line_items',
			'shipping_cost',
		) as $result_key ) {

			$result_lines = $tax_calculation[ $result_key ];

			if ( 'shipping_cost' === $result_key ) {
				$result_lines = array( $result_lines );
			}

			foreach ( $result_lines as $result_line ) {
				$tax_breakdown = $result_line['amount_tax_breakdown'];

				foreach ( $tax_breakdown as $rate_id => $tax ) {
					$rate_ids_to_create[ $rate_id ] = $rate_id;
				}
			}
		}

		$order->save();

		$items = $order->get_items( 'tax' );

		foreach ( $items as $item_id => $item ) {
			$item_rate_id  = $item->get_rate_id();
			$order_item_id = $item->get_id();

			if ( ( $item instanceof StripeOrderItemTax )
				&& isset( $rate_ids_to_create[ $item_rate_id ] ) ) {
				unset( $rate_ids_to_create[ $item_rate_id ] );
				continue;
			}

			$order->remove_item( $item_id );
		}
		$order->save();
		// Create new tax items for each remaining rate id $rate_ids_to_create.
		foreach ( $rate_ids_to_create  as $rate_id ) {
			$item = StripeOrderItemTax::from_rate_id( $rate_id );

			$order->add_item( $item );
		}

		$order->save();
	}

	/**
	 * Update an order totals from Stripe API calculation
	 *
	 * @param object $order The order.
	 */
	public static function sync_order_totals( $order ) {
		$order_id                   = $order->get_id();
		$non_taxable_shipping_total = Order_Input::get_non_taxable_shipping_cost_amount( $order );

		$tax_calculation = isset( Calculator::$calculations[ $order_id ]['result'] ) ? Calculator::$calculations[ $order_id ]['result'] : null;

		if ( ! $tax_calculation ) {
			return;
		}

		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
			$item_reference = Order_Input::build_item_reference_by_type( $item );
			$result_line    = $tax_calculation->get_line_item_by_reference( $item_reference );

			if ( ! $result_line ) {
				continue;
			}

			if ( ! ( $item instanceof WC_Order_Item_Shipping ) ) {
				$item->set_total( $result_line->amount );
				$item->set_total_tax( $result_line->amount_tax );
			}
		}

		$shipping_result_line = $tax_calculation['shipping_cost'];
		$is_inclusive         = $shipping_result_line
			&& Result::TAX_BEHAVIOR_INCLUSIVE === $shipping_result_line->tax_behavior;

		if ( $is_inclusive && self::has_matching_line_items( $order, $tax_calculation ) ) {
				$order->set_total( $tax_calculation->amount_total );
				$order->set_shipping_total( $shipping_result_line->amount );
		}
	}

	/**
	 * Updates an order item taxes from previously cached tax calculation.
	 *
	 * @param object $item The order item.
	 */
	public static function update_item_taxes( $item ) {
		$order_id = $item->get_order_id();

		$tax_calculation = isset( Calculator::$calculations[ $order_id ]['result'] ) ? Calculator::$calculations[ $order_id ]['result'] : null;

		if ( ! $tax_calculation ) {
			return;
		}

		$item_reference = Order_Input::build_item_reference_by_type( $item );
		$result_line    = $tax_calculation->get_line_item_by_reference( $item_reference );

		if ( ! $result_line ) {
			return array();
		}

		static::update_order_item_taxes_from_calculation_result_line( $item, $result_line );
	}

	/**
	 * Updates an order item taxes from previously cached tax calculation line.
	 *
	 * @param object $item The order item.
	 * @param object $result_line The tax calculation line.
	 */
	public static function update_order_item_taxes_from_calculation_result_line( $item, $result_line ) {
		$tax_totals    = array();
		$tax_subtotals = array();

		foreach ( $result_line['amount_tax_breakdown'] as $rate_id => $amount_tax ) {
			$amount_subtotal_tax = $result_line['amount_subtotal_tax_breakdown'][ $rate_id ];

			$tax_totals[ $rate_id ]    = $amount_tax;
			$tax_subtotals[ $rate_id ] = $amount_subtotal_tax;
		}

		$taxes = array(
			'total' => $tax_totals,
		);

		if ( method_exists( $item, 'get_subtotal' ) ) {
			$taxes['subtotal'] = $tax_subtotals;
		}

		$item->set_taxes( $taxes );
	}

	/**
	 * Stores a new order item price inclding taxes.
	 *
	 * @param object $item The order item.
	 */
	public static function init_new_order_item_meta( $item ) {
		if ( $item instanceof WC_Order_Item_Product ) {
			$product_id = $item->get_variation_id();

			if ( ! $product_id ) {
				$product_id = $item->get_product_id();
			}

			if ( $product_id ) {
				$product = wc_get_product( $product_id );

				if ( $product ) {
					$price_inclusive_tax = $product->get_price();
				}
			}
		} elseif ( $item instanceof WC_Order_Item_Shipping ) {
			$price_inclusive_tax = $item->get_total();
		} elseif ( $item instanceof WC_Order_Item_Fee ) {
			$price_inclusive_tax = $item->get_total();
		}

		if ( isset( $price_inclusive_tax ) ) {
			$item->update_meta_data( '__stripe_tax_price_inclusive_tax', $price_inclusive_tax );
			$item->save();
		}
	}

	/**
	 * Stores tax-inclusive total and subtotal values as meta on an order item.
	 *
	 * These values are later used when rebuilding Stripe Tax input for orders
	 * created with prices that include tax.
	 *
	 * @param object $order_item The order item.
	 */
	public static function store_totals_tax_inclusive( $order_item ) {
		$order_item_type = $order_item->get_type();

		if ( ! in_array( $order_item_type, array( 'line_item', 'fee', 'shipping' ), true ) ) {
			return;
		}

		$has_subtotal_tax_inclusive_meta = $order_item->get_meta( '_stripe_tax_checkout_subtotal_tax_inclusive' ) !== '';

		$stripe_checkout_total_tax_inclusive    = (float) $order_item->get_total() + (float) $order_item->get_total_tax();
		$stripe_checkout_subtotal_tax_inclusive = $stripe_checkout_total_tax_inclusive;

		switch ( $order_item_type ) {
			case 'fee':
				$stripe_checkout_total_tax_inclusive    = (float) $order_item->get_total() + (float) $order_item->get_total_tax();
				$stripe_checkout_subtotal_tax_inclusive = $stripe_checkout_total_tax_inclusive;
				break;
			case 'shipping':
				break;
			default:
				if ( method_exists( $order_item, 'get_subtotal' ) ) {
					$stripe_checkout_subtotal_tax_inclusive = 0 + $order_item->get_subtotal();

					if ( method_exists( $order_item, 'get_subtotal_tax' ) ) {
						$stripe_checkout_subtotal_tax_inclusive += 0 + $order_item->get_subtotal_tax();
					}
				}
		}

		$order_item->update_meta_data( '_stripe_tax_checkout_total_tax_inclusive', $stripe_checkout_total_tax_inclusive );

		if ( ! $has_subtotal_tax_inclusive_meta ) {
			$order_item->update_meta_data( '_stripe_tax_checkout_subtotal_tax_inclusive', $stripe_checkout_subtotal_tax_inclusive );
		}
	}
}
