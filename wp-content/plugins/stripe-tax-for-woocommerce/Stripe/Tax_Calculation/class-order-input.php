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
			$taxability_override = self::get_user_taxability_override_by_id( $customer_user_id_override );
		} else {
			$customer_id = $order->get_customer_id();
			$customer    = new WC_Customer( $customer_id );

			$taxability_override = self::get_customer_taxability_override( $customer );
		}

		$order_status = $order->get_status();

		$tax_behavior = $order->get_prices_include_tax() ? self::TAX_BEHAVIOR_INCLUSIVE : self::TAX_BEHAVIOR_EXCLUSIVE;

		if ( self::TAX_BEHAVIOR_INCLUSIVE === $tax_behavior ) {
			self::add_item_taxes_to_totals( $order );
		}

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
		}

		if ( ! $shipping_tax_behavior && $shipping_item ) {
			$comp_meta = $shipping_item->get_meta( '_stripe_not_subtotal_include_tax' );

			if ( $comp_meta && 'yes' === $comp_meta ) {
				$shipping_tax_behavior = self::TAX_BEHAVIOR_EXCLUSIVE;
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
			 *
			 * Current order item.
			 *
			 * @var \WC_Order_Item_Product|\WC_Order_Item_Fee $item
			 */
			$tax_status = $item->get_tax_status();

			$type     = $item->get_type();
			$tax_code = static::get_item_tax_code( $item, $type );

			$reference = self::build_item_reference_by_type( $item );

			switch ( $type ) {
				case 'fee':
					$total    = $item->get_total();
					$subtotal = $item->get_amount();
					$quantity = 1;

					break;

				default:
					$total    = $item->get_total();
					$subtotal = $item->get_subtotal();
					$quantity = $item->get_quantity();
					break;
			}

			$item_tax_behavior = $item->get_meta( '__stripe_tax_behavior' );

			if ( ! $item_tax_behavior ) {
				$comp_meta = $item->get_meta( '_stripe_not_subtotal_include_tax' );

				if ( $comp_meta && 'yes' === $comp_meta ) {
					$item_tax_behavior = self::TAX_BEHAVIOR_EXCLUSIVE;
				} else {
					$item_tax_behavior = self::TAX_BEHAVIOR_INCLUSIVE === $tax_behavior && StripeTax_Options::item_allow_price_tax_inclusive( $type ) ? self::TAX_BEHAVIOR_INCLUSIVE : self::TAX_BEHAVIOR_EXCLUSIVE;
				}
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

		$id       = $order->get_customer_id();
		$customer = new WC_Customer( $order->get_customer_id() );

		$tax_location = self::get_customer_tax_location( $order, isset( $order_tax_location ) ? $order_tax_location : null );

		return $tax_location;
	}

	/**
	 * Resets an order totals and taxes.
	 *
	 * @param object $order The order.
	 */
	public static function reset_item_taxes_to_totals( $order ) {
		$items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );

		foreach ( $items as $item ) {
			/**
			*
			* Current order item.
			 *
			 * @var \WC_Order_Item_Product|\WC_Order_Item_Fee|\WC_Order_Item_Shipping $item
			*/
			$tax_excluded_from_subtotal_meta = $item->get_meta( static::TAX_EXCLUDED_META_NAME );

			if ( 'yes' === $tax_excluded_from_subtotal_meta ) {
				$subtotal = $item->get_meta( '__stripe_tax_price_inclusive_tax' );

				if ( '' !== $subtotal ) {
					$subtotal = (float) $subtotal * $item->get_quantity();
				} elseif ( method_exists( $item, 'get_subtotal' ) ) {
					$subtotal = $item->get_subtotal();
				}

				if ( method_exists( $item, 'set_subtotal' ) ) {
					$item->set_subtotal( $subtotal );
					$item->set_subtotal_tax( 0 );
				}

				$item->set_total( $subtotal );
				$item->set_taxes( array() );

				if ( ! ( $item instanceof WC_Order_Item_Shipping ) ) {
					$item->set_total_tax( 0 );
				}

				$item->update_meta_data( static::TAX_EXCLUDED_META_NAME, 'no' );
				$item->save();
			}
		}

		$order->save();
	}
	/**
	 * For order with price including taxes, restores order line item subtotals by adding each line taxes
	 *
	 * @param object $order The order.
	 */
	public static function add_item_taxes_to_totals( $order ) {
		$items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );

		foreach ( $items as $item ) {
			/**
			 *
			 * Current order item.
			 *
			 * @var \WC_Order_Item_Product|\WC_Order_Item_Fee|\WC_Order_Item_Shipping $item
			 */
			$tax_excluded_from_subtotal_meta = $item->get_meta( static::TAX_EXCLUDED_META_NAME );

			if ( 'yes' === $tax_excluded_from_subtotal_meta ) {
				$item_type = $item->get_type();

				if ( 'fee' === $item_type && ! static::is_fee_item_taxable( $item ) ) {
					$item->update_meta_data( static::TAX_EXCLUDED_META_NAME, '' );
					continue;
				}
				$subtotal = null;

				$stripe_tax_price_inclusive_tax = $item->get_meta( '__stripe_tax_price_inclusive_tax' );

				if ( '' !== $stripe_tax_price_inclusive_tax ) {
					$subtotal = (float) $stripe_tax_price_inclusive_tax * $item->get_quantity();
				}

				if ( is_null( $subtotal ) ) {
					if ( $item instanceof WC_Order_Item_Fee ) {
						$subtotal     = $item->get_amount();
						$subtotal_tax = 0;

						// @phpstan-ignore-next-line
						$subtotal += $subtotal_tax;
					} elseif ( method_exists( $item, 'get_subtotal' ) ) {
						$subtotal     = $item->get_subtotal();
						$subtotal_tax = $item->get_subtotal_tax();

						$subtotal += $subtotal_tax;
					}
				}

				if ( method_exists( $item, 'set_subtotal' ) ) {
					$item->set_subtotal( $subtotal );
					$item->set_subtotal_tax( 0 );
				}

				$total     = $item->get_total();
				$total_tax = $item->get_total_tax();
				// @phpstan-ignore-next-line
				$item->set_total( $total + $total_tax );
				$item->set_taxes( array() );

				if ( ! ( $item instanceof WC_Order_Item_Shipping ) ) {
					$item->set_total_tax( 0 );
				}

				$item->update_meta_data( static::TAX_EXCLUDED_META_NAME, 'no' );
				$item->save();
			}
		}

		$order->save();
	}

	/**
	 * Removes calculated taxes from line item totals for an order created with price include taxes.
	 *
	 * @param object $order Order.
	 */
	public static function remove_item_taxes_from_totals( $order ) {
		$order_id = $order->get_id();

		$tax_calculation = isset( Calculator::$calculations[ $order_id ]['result'] ) ? Calculator::$calculations[ $order_id ]['result'] : null;

		if ( ! $tax_calculation ) {
			return;
		}

		$items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );

		foreach ( $items as $item ) {
			/**
			 *
			 * Current order item.
			 *
			 * @var \WC_Order_Item_Product|\WC_Order_Item_Fee|\WC_Order_Item_Shipping $item
			 */
			$tax_excluded_from_subtotal_meta = $item->get_meta( static::TAX_EXCLUDED_META_NAME );
			if ( $item instanceof WC_Order_Item_Shipping ) {
				continue;
			}
			if ( 'no' === $tax_excluded_from_subtotal_meta ) {
				$total        = $item->get_total();
				$subtotal     = null;
				$subtotal_tax = 0;
				if ( $total > 0 && method_exists( $item, 'set_subtotal' ) ) {
					$subtotal = $item->get_subtotal();

					$subtotal_tax = $item->get_meta( '__stripe_tax_item_subtotal_tax' );

					$item->set_subtotal( $subtotal - $subtotal_tax );
					$item->set_subtotal_tax( $subtotal_tax );
				}

				if ( $total > 0 ) {
					if ( ! is_null( $subtotal ) && $total !== $subtotal ) {
						$total_tax = $subtotal_tax * $total / $subtotal;
					} else {
						$total_tax = $item->get_total_tax();
					}

					$item->set_total_tax( $total_tax );
					$item->set_total( $total - $total_tax );
				}

				$item->update_meta_data( static::TAX_EXCLUDED_META_NAME, 'yes' );
				$item->save();
			}
		}

		$order->save();
	}

	/**
	 * Build and returns an item reference based on its type
	 *
	 * @param object $item Order line item.
	 */
	public static function build_item_reference_by_type( $item ) {
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

		$reference = static::build_item_reference( $item_type, $item_id, $item_variation_id, $item_name, $item_variation_attributes, $order_item_id );

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
			$wc_tax_status = $item->get_tax_status();
			$wc_tax_class  = $item->get_tax_class();
			$wc_is_taxable = 'taxable' === $wc_tax_status && '0' !== $wc_tax_class;

			if ( ! $wc_is_taxable ) {
				$tax_code = Options::DEFAULT_OPTION_NON_TAXABLE_FEE_TAX_CODE;
			}
		}

		return $tax_code;
	}

	/**
	 * Checks if a fee item is taxable or not
	 *
	 * @param object $item The fee item.
	 */
	protected static function is_fee_item_taxable( $item ) {
		$wc_tax_status = $item->get_tax_status();
		$wc_tax_class  = $item->get_tax_class();
		$wc_is_taxable = 'taxable' === $wc_tax_status && '0' !== $wc_tax_class;

		return $wc_is_taxable;
	}
}
