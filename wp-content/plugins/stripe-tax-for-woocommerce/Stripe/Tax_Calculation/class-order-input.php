<?php
/**
 * Tax calculation operations input built from an order.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use WC_Tax;
use WC;
use WC_Order;
use WC_Customer;
use WC_Order_Item_Shipping;
use WC_Order_Item_Fee;

use Stripe\StripeTaxForWooCommerce\StripeTax_Options;
use Stripe\StripeTaxForWooCommerce\Utils\Amount_Utility;
use Stripe\StripeTaxForWooCommerce\Stripe\Product_Tax_Code_Repo;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
use Throwable;

/**
 * Tax calculation operations input built from an order.
 */
class Order_Input extends Input {
	/**
	 * Creates an input from an order
	 *
	 * @param WC_Order $order The order to build the input from.
	 * @param array    $tax_location_override Args passed by WooCommerce.
	 * @param int      $customer_user_id_override Customer user id.
	 */
	public static function from_order( WC_Order $order, $tax_location_override, $customer_user_id_override = null ) {
		if ( $customer_user_id_override ) {
			$customer = new WC_Customer( $customer_user_id_override );

			$taxability_override = self::get_customer_taxability_override( $customer );
		} else {
			$customer_id = $order->get_customer_id();
			$customer    = new WC_Customer( $customer_id );

			$taxability_override = self::get_customer_taxability_override( $customer );
		}

		$order_status = $order->get_status();

		$tax_behavior = $order->get_prices_include_tax() ? self::TAX_BEHAVIOR_INCLUSIVE : self::TAX_BEHAVIOR_EXCLUSIVE;

		$order_id = $order->get_id();

		$currency = $order->get_currency();

		$shipping_cost_details = static::get_shipping_cost_details( $order, $currency );

		$shipping_cost_amount = $shipping_cost_details['shipping_cost_amount'];
		$shipping_tax_code    = $shipping_cost_details['shipping_tax_code'];

		$tax_location = static::get_taxable_location( $order, $tax_location_override );

		$items = $order->get_items( 'shipping' );

		$shipping_tax_behavior = '';
		$shipping_item         = null;

		if ( is_array( $items ) && count( $items ) > 0 ) {
			$shipping_item         = reset( $items );
			$shipping_tax_behavior = $shipping_item->get_meta( '__stripe_tax_behavior' );

			if ( self::TAX_BEHAVIOR_INCLUSIVE === $shipping_tax_behavior ) {
				$stripe_checkout_total_tax_inclusive = $shipping_item->get_meta( '_stripe_tax_checkout_total_tax_inclusive' );

				if ( '' !== $stripe_checkout_total_tax_inclusive ) {
					$shipping_cost_amount = Amount_Utility::to_cents( 0 + (float) $stripe_checkout_total_tax_inclusive, $currency );
				}
			}
		}

		if ( ! $shipping_tax_behavior ) {
			$shipping_tax_behavior = self::TAX_BEHAVIOR_INCLUSIVE === $tax_behavior && StripeTax_Options::item_allow_price_tax_inclusive( 'shipping' ) ? self::TAX_BEHAVIOR_INCLUSIVE : self::TAX_BEHAVIOR_EXCLUSIVE;
		}

		$shipping_cost = new Input_Line_Item(
			static::SHIPPING_COST_REFERENCE,
			$shipping_cost_amount,
			1,
			$shipping_tax_behavior,
			$shipping_tax_code,
			$shipping_cost_amount
		);

		$customer_details = new Customer_Details(
			$tax_location['country'],
			$tax_location['state'],
			$tax_location['city'],
			$tax_location['postcode'],
			$tax_location['line1'],
			$tax_location['line2'],
			$tax_location['source'],
			$taxability_override
		);

		$items = $order->get_items( array( 'line_item', 'fee' ) );

		$input_lines = array();
		foreach ( $items as $item ) {
			/**
			 * Current order item from line item or fee collection.
			 *
			 * @var \WC_Order_Item_Product|\WC_Order_Item_Fee $item
			 */
			$total             = null;
			$subtotal          = null;
			$item_tax_behavior = $item->get_meta( '__stripe_tax_behavior' );
			$type              = $item->get_type();

			if ( '' === $item_tax_behavior ) {
				$item_tax_behavior = self::TAX_BEHAVIOR_INCLUSIVE === $tax_behavior && StripeTax_Options::item_allow_price_tax_inclusive( $type )
					? self::TAX_BEHAVIOR_INCLUSIVE
					: self::TAX_BEHAVIOR_EXCLUSIVE;
			}

			$tax_code = static::get_item_tax_code( $item, $type );

			$reference = self::build_item_reference_by_type( $item );

			if ( self::TAX_BEHAVIOR_INCLUSIVE === $item_tax_behavior ) {
				$stripe_checkout_total_tax_inclusive = $item->get_meta( '_stripe_tax_checkout_total_tax_inclusive' );

				if ( '' !== $stripe_checkout_total_tax_inclusive ) {
					$total = 0 + (float) $stripe_checkout_total_tax_inclusive;
				}

				$stripe_checkout_subtotal_tax_inclusive = $item->get_meta( '_stripe_tax_checkout_subtotal_tax_inclusive' );

				if ( '' !== $stripe_checkout_subtotal_tax_inclusive ) {
					$subtotal = 0 + (float) $stripe_checkout_subtotal_tax_inclusive;
				}
			}

			switch ( $type ) {
				case 'fee':
					if ( is_null( $total ) ) {
						$total    = $item->get_total();
						$subtotal = $item->get_amount();
					}

					$quantity = 1;

					break;

				default:
					if ( is_null( $total ) ) {
						$total    = $item->get_total();
						$subtotal = $item->get_subtotal();
					}

					$quantity = $item->get_quantity();
					break;
			}

			$input_line2 = new Input_Line_Item(
				$reference,
				Amount_Utility::to_cents( $total, $currency ),
				$quantity,
				$item_tax_behavior,
				$tax_code,
				Amount_Utility::to_cents( $subtotal, $currency )
			);

			$input_lines[] = $input_line2;
		}
		// @phpstan-ignore-next-line
		return new static( $currency, $customer_details, $input_lines, $shipping_cost, $tax_behavior );
	}


	/**
	 * Calculates and returns an order shipping cost amount by taxability.
	 *
	 * @param object $order The order.
	 * @param bool   $is_taxable Whether to sum taxable or non-taxable shipping methods.
	 */
	protected static function get_shipping_cost_amount_by_taxability( $order, $is_taxable ) {
		$shipping_cost_amount = 0;

		$items = $order->get_items( 'shipping' );

		foreach ( $items as $item ) {
			$method_id = $item->get_method_id();

			if ( $method_id ) {
				$instance_id = $item->get_instance_id();

				$shipping_method_settings   = get_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings' );
				$shipping_method_is_taxable = isset( $shipping_method_settings['tax_status'] ) && 'taxable' === $shipping_method_settings['tax_status'];
			} else {
				$shipping_method_is_taxable = 'taxable' === $item->get_tax_status();
			}

			$item_is_taxable = $shipping_method_is_taxable;

			if ( $is_taxable !== $item_is_taxable ) {
				continue;
			}

			$shipping_cost_amount += $item->get_total();
		}

		return $shipping_cost_amount;
	}

	/**
	 * Determines and return an order tax location
	 *
	 * @param object $order The order.
	 * @param array  $tax_location_override Passed by WooCommerce.
	 */
	protected static function get_taxable_location( $order, $tax_location_override ) {
		if ( $tax_location_override ) {
			$order_tax_location = $order->get_taxable_location( $tax_location_override );
		}

		$tax_location = self::get_customer_tax_location( $order, isset( $order_tax_location ) ? $order_tax_location : null );

		return $tax_location;
	}

	/**
	 * Resets item taxes to totals for items on orders created with prices include taxes.
	 *
	 * @param object $order The order.
	 */
	public static function reset_item_taxes_to_totals( $order ) {
		$items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );

		foreach ( $items as $item ) {
			$item_type = $item->get_type();

			if ( 'shipping' === $item_type ) {
				// In inclusive-price orders shipping total is already tax-inclusive; only clear tax data.
				if ( is_callable( array( $item, 'set_taxes' ) ) ) {
					$item->set_taxes( array( 'total' => array() ) );
				}
			} elseif ( 'fee' === $item_type ) {
				// Fees keep tax in total and clear fee tax data through the public API.
				$item->set_total( $item->get_total() + $item->get_total_tax() );
				if ( is_callable( array( $item, 'set_total_tax' ) ) ) {
					$item->set_total_tax( 0 );
				} elseif ( is_callable( array( $item, 'set_taxes' ) ) ) {
					$item->set_taxes( array( 'total' => array() ) );
				}
			} else {
				// Product line items use subtotal/subtotal_tax.
				$item->set_subtotal( $item->get_subtotal() + $item->get_subtotal_tax() );
				$item->set_subtotal_tax( 0 );
			}
			$item->save();
		}

		$order->save();
	}

	/**
	 * Removes calculated taxes from line item totals for an order created with price include taxes.
	 *
	 * @param object $order Order.
	 */
	public static function remove_item_taxes_from_totals( $order ) {
	}

	/**
	 * Build and returns an item reference based on its type
	 *
	 * @param object $item Order line item.
	 * @param bool   $is_refund Whether the reference is built for a refund context.
	 */
	public static function build_item_reference_by_type( $item, $is_refund = false ) {
		$cart_item_reference = $item->get_meta( static::CART_ITEM_REFERENCE_META_NAME );

		if ( '' !== $cart_item_reference ) {
			return $cart_item_reference;
		}

		$item_type                 = $item->get_type();
		$item_id                   = 0;
		$item_name                 = '';
		$item_variation_attributes = array();
		$item_variation_id         = 0;

		$order_item_id = $item->get_id();

		if ( 0 === $order_item_id ) {
			$item->save();
			$order_item_id = $item->get_id();
		}

		switch ( $item_type ) {
			case 'fee':
				$item_name = $item->get_name();

				break;

			case 'line_item':
				$item_id           = $item->get_product_id();
				$item_variation_id = $item->get_variation_id();
				if ( 0 !== $item_variation_id ) {
					$variation = wc_get_product( $item_variation_id );

					// @phpstan-ignore-next-line
					$variation_attributes = $variation->get_variation_attributes();

					foreach ( $variation_attributes as $variation_attribute_name => $variation_attribute_value ) {
						$item_variation_attribute_name  = substr( $variation_attribute_name, 10 );
						$item_variation_attribute_value = $item->get_meta( $item_variation_attribute_name );

						$item_variation_attributes[ $item_variation_attribute_name ] = $item_variation_attribute_value;
					}
				}

				$item_name = $item->get_name();

				break;

			case 'shipping':
				$item_type = self::SHIPPING_COST_KEY;
				$item_name = 'Shipping';

				return 'shipping_cost';
		}

		$reference = static::build_item_reference( $item_type, $item_id, $item_variation_id, $item_name, $item_variation_attributes, $is_refund ? null : $order_item_id );

		return $reference;
	}

	/**
	 * Returns a order item tax code by its type
	 *
	 * @param object $item Order item.
	 * @param string $type Order item type.
	 */
	protected static function get_item_tax_code( $item, $type ) {
		$tax_code = Product_Tax_Code_Repo::get_tax_code_by_type_and_id( $type, 'fee' === $type ? $item->get_name() : $item->get_product_id() );

		if ( 'fee' === $type ) {
			if ( ! static::is_fee_item_taxable( $item ) ) {
				$tax_code = Options::DEFAULT_OPTION_NON_TAXABLE_FEE_TAX_CODE;
			}
		}

		return $tax_code;
	}

	/**
	 * Checks if a fee item is taxable or not.
	 *
	 * Uses explicit (string) cast on tax_class to avoid PHP type-juggling:
	 * WC_Order_Item_Fee can return integer 0 from pending changes,
	 * and '0' !== 0 is TRUE in strict comparison, causing non-taxable
	 * fees to be incorrectly treated as taxable.
	 *
	 * @param object $item The fee item.
	 */
	protected static function is_fee_item_taxable( $item ) {
		$wc_tax_status = $item->get_tax_status();
		$wc_tax_class  = (string) $item->get_tax_class();
		$wc_is_taxable = 'taxable' === $wc_tax_status && '0' !== $wc_tax_class;

		return $wc_is_taxable;
	}
}
