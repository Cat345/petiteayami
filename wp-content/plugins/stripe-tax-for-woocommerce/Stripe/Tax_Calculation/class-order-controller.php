<?php
/**
 * Order_Controller class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Calculator;
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
	 * Calculates taxes for a given order.
	 *
	 * @param WC_Order $order The order.
	 * @param array    $tax_location_override WC args.
	 * @param int      $customer_user_id_override Customer user ID.
	 */
	public static function calculate_taxes( WC_Order $order, $tax_location_override, $customer_user_id_override ) {
		if ( 'checkout-draft' === $order->get_status() ) {
			static::set_checkout_order_meta( $order );
		}

		$non_taxable_shipping_total = Order_Input::get_non_taxable_shipping_cost_amount( $order );
		$tax_calculation_input      = Order_Input::from_order( $order, $tax_location_override, $customer_user_id_override );

		$order_id = $order->get_id();
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

			$item->update_meta_data( '__stripe_tax_behavior', $result_line->tax_behavior );

			if ( Result::TAX_BEHAVIOR_INCLUSIVE !== $result_line->tax_behavior ) {
				continue;
			}

			$item->set_total( $result_line->amount );

			if ( method_exists( $item, 'get_subtotal' ) ) {
				$item->set_subtotal( $result_line->amount_subtotal );
			}

			$item->update_meta_data( '__stripe_tax_item_subtotal_tax', $result_line->amount_subtotal_tax );
			$item->update_meta_data( Result::TAX_EXCLUDED_META_NAME, 'yes' );

			$item->save();
		}

		$result_line = $tax_calculation_result['shipping_cost'];

		if ( Result::TAX_BEHAVIOR_INCLUSIVE === $result_line->tax_behavior ) {
			$order->set_shipping_total( $result_line->amount + $non_taxable_shipping_total );
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
		$order->set_total( $tax_calculation->amount_total );

		$shipping_result_line = $tax_calculation['shipping_cost'];
		$order->set_shipping_total( $shipping_result_line->amount );
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
	 * Marks an order item calculated with 'price includes tax'.
	 *
	 * @param object $order The order.
	 */
	public static function set_checkout_order_meta( $order ) {
		$tax_calculation = isset( Calculator::$calculations['cart']['result'] ) ? Calculator::$calculations['cart']['result'] : null;

		if ( ! $tax_calculation ) {
			return;
		}

		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
			$item_reference = Order_Input::build_item_reference_by_type( $item );
			$result_line    = $tax_calculation->get_line_item_by_reference( $item_reference );

			if ( ! $result_line ) {
				continue;
			}

			if ( Result::TAX_BEHAVIOR_INCLUSIVE !== $result_line->tax_behavior ) {
				continue;
			}

			$item->update_meta_data( Result::TAX_EXCLUDED_META_NAME, 'yes' );
		}
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
}
