<?php
/**
 * Tax calculation operations input built from cart.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use WC;
use WC_Cart;
use WC_Tax;

use Stripe\StripeTaxForWooCommerce\Utils\Amount_Utility;
use Stripe\StripeTaxForWooCommerce\Stripe\Product_Tax_Code_Repo;
use Stripe\StripeTaxForWooCommerce\StripeTax_Options;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;

/**
 * Tax calculation operations input built from cart.
 */
class Cart_Input extends Input {
	const MAX_LINE_ITEMS = 100;

	/**
	 * Creates a tax calculation input from cart
	 *
	 * @param WC_Cart $cart The cart.
	 * @param string  $tax_behavior Tax behavior.
	 */
	public static function from_cart( WC_Cart $cart, $tax_behavior ) {
		$customer = $cart->get_customer();

		$is_customer_vat_exempt = $customer->get_is_vat_exempt();

		if ( $is_customer_vat_exempt ) {
			return;
		}

		$tax_location        = self::get_tax_location( $cart );
		$taxability_override = self::get_customer_taxability_override( $customer );

		self::prevalidate_cart( $cart );

		$currency             = get_woocommerce_currency();
		$shipping_cost_amount = Amount_Utility::to_cents( self::get_taxable_shipping_cost_amount( $cart ), $currency );
		$shipping_tax_code    = Product_Tax_Code_Repo::get_tax_code_by_type_and_id( 'shipping' );
		$shipping_cost        = new Input_Line_Item(
			static::SHIPPING_COST_REFERENCE,
			$shipping_cost_amount,
			1,
			static::TAX_BEHAVIOR_INCLUSIVE === $tax_behavior && ! StripeTax_Options::item_allow_price_tax_inclusive( 'shipping' ) ? static::TAX_BEHAVIOR_EXCLUSIVE : $tax_behavior,
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

		$input_lines = array();

		foreach ( array(
			'line_item' => 'get_cart',
			'fee'       => 'get_fees',
		) as $type => $method_name
		) {
			foreach ( $cart->{$method_name}() as $item ) {
				switch ( $type ) {
					case 'line_item':
						$product    = $item['data'];
						$is_taxable = $product->is_taxable();

						$quantity = $item['quantity'];

						$total    = $item['line_total'];
						$subtotal = $item['line_subtotal'];

						$id_for_tax_code = $product->get_id();
						break;

					case 'fee':
						$is_taxable = $item->taxable;

						$quantity = 1;

						$total    = $item->total;
						$subtotal = $item->total;

						$id_for_tax_code = null;
						break;
				}

				$tax_code  = static::get_item_tax_code( $item, $type );
				$reference = static::build_item_reference_by_type( $item, $type );

				$total    = Amount_Utility::to_cents( $total, $currency );
				$subtotal = Amount_Utility::to_cents( $subtotal, $currency );

				if ( self::TAX_BEHAVIOR_INCLUSIVE === $tax_behavior && 'fee' === $type ) {
					$line_tax_behavior = StripeTax_Options::item_allow_price_tax_inclusive( 'fee' ) ? $tax_behavior : self::TAX_BEHAVIOR_EXCLUSIVE;
				} else {
					$line_tax_behavior = $tax_behavior;
				}

				$input_line = new Input_Line_Item(
					$reference,
					$total,
					$quantity,
					$line_tax_behavior,
					$tax_code,
					$subtotal
				);

				$input_lines[] = $input_line;
			}
		}
		// @phpstan-ignore-next-line
		return new static( $currency, $customer_details, $input_lines, $shipping_cost, $tax_behavior );
	}

	/**
	 * Build a cart line item reference based on its type
	 *
	 * @param object|array $item The cart line item.
	 * @param string       $item_type Item type.
	 */
	public static function build_item_reference_by_type( $item, $item_type = 'line_item' ) {

		$cart_item_data            = array();
		$item_id                   = 0;
		$item_name                 = '';
		$item_variation_attributes = array();
		$item_variation_id         = 0;

		switch ( $item_type ) {
			case 'fee':
				$item_name = is_object( $item ) ? (string) ( $item->name ?? '' ) : (string) ( $item['name'] ?? '' );

				break;

			case 'line_item':
				$product              = $item['data'];
				$item_id              = $item['product_id'];
				$item_variation_id    = $item['variation_id'];
				$variation_attributes = $item['variation'];
				if ( is_array( $variation_attributes ) ) {
					foreach ( $variation_attributes as $variation_attribute_name => $variation_attribute_value ) {
						$item_variation_attribute_name = substr( $variation_attribute_name, 10 );

						$item_variation_attributes[ $item_variation_attribute_name ] = $variation_attribute_value;
					}
				}
				$cart_item_data = static::get_cart_item_cart_item_data( $item );
				$item_name      = $product->get_name();

				break;
			case 'shipping':
				$item_type = Result::SHIPPING_COST_KEY;
				$item_name = 'Shipping';

				break;
		}

		return static::build_item_reference( $item_type, $item_id, $item_variation_id, $item_name, $item_variation_attributes, $cart_item_data );
	}

	/**
	 * Returns a cart item custom data.
	 *
	 * @param array $item Cart item.
	 */
	protected static function get_cart_item_cart_item_data( $item ) {
		$core_keys = array(
			'key',
			'product_id',
			'variation_id',
			'variation',
			'quantity',
			'data',
			'data_hash',
			'line_tax_data',
			'line_subtotal',
			'line_subtotal_tax',
			'line_total',
			'line_tax',
		);

		$cart_item_data = array_diff_key( $item, array_flip( $core_keys ) );

		return $cart_item_data;
	}

	/**
	 * Calculates and returns cart taxable shipping cost
	 *
	 * @param object $cart The cart.
	 */
	protected static function get_taxable_shipping_cost_amount( $cart ) {
		$shipping_cost_amount = 0;

		$shipping_methods = $cart->calculate_shipping();

		if ( ! $shipping_methods ) {
			return $shipping_cost_amount;
		}

		// @phpstan-ignore-next-line
		$packages = WC()->shipping->get_packages();
		$chosen   = WC()->session->get( 'chosen_shipping_methods' );

		foreach ( $packages as $idx => $package ) {
			$rates          = $package['rates'];
			$chosen_rate_id = isset( $chosen[ $idx ] ) ? $chosen[ $idx ] : null;

			if ( ! $chosen_rate_id || ! isset( $rates[ $chosen_rate_id ] ) ) {
				continue;
			}

			$chosen_rate_settings = get_option( 'woocommerce_' . str_replace( ':', '_', $chosen_rate_id ) . '_settings' );

			if ( ! isset( $chosen_rate_settings['tax_status'] ) || 'taxable' !== $chosen_rate_settings['tax_status'] ) {
				continue;
			}

			$rate = $rates[ $chosen_rate_id ];

			foreach ( $shipping_methods as $shipping_method ) {
				if ( $rate->instance_id === $shipping_method->instance_id ) {
					$shipping_cost_amount += (float) $rate->get_cost();
					break;
				}
			}
		}

		return $shipping_cost_amount;
	}

	/**
	 * Returns tax location for a cart
	 *
	 * @param WC_Cart $cart The cart.
	 */
	protected static function get_tax_location( WC_Cart $cart ) {
		$customer = $cart->get_customer();

		$tax_location = self::get_customer_tax_location( $customer );

		return $tax_location;
	}

	/**
	 * Validates a cart contents
	 *
	 * @param WC_Cart $cart The cart.
	 *
	 * @throws Input_Exception Throws an exception if the validation fails.
	 */
	protected static function prevalidate_cart( $cart ) {
		if ( count( $cart->cart_contents ) > static::MAX_LINE_ITEMS ) {
			throw new Input_Exception();
		}
	}

	/**
	 * Returns a cart item tax code by its type
	 *
	 * @param array|object $item Cart item.
	 * @param string       $type Cart item type.
	 */
	protected static function get_item_tax_code( $item, $type ) {
		$is_taxable      = null;
		$id_for_tax_code = null;

		switch ( $type ) {
			case 'line_item':
				$product         = $item['data'];
				$id_for_tax_code = $product->get_id();
				break;

			case 'fee':
				$is_taxable = is_object( $item ) ? $item->taxable : $item['taxable'];
				break;
		}

		if ( 'fee' === $type && ! $is_taxable ) {
			$tax_code = Options::DEFAULT_OPTION_NON_TAXABLE_FEE_TAX_CODE;
		} else {
			$tax_code = Product_Tax_Code_Repo::get_tax_code_by_type_and_id( $type, $id_for_tax_code );
		}

		return $tax_code;
	}
}
