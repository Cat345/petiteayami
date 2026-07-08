<?php
/**
 * Order tax calculation hook handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\StripeCalculationTracker;
use Stripe\StripeTaxForWooCommerce\WordPress\Hook_Handlers;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Order_Controller;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeOrderItemTax;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\InvalidRequestException;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Cart_Input;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Order_Input;
use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTaxTaxRateMemRepo;
use Stripe\StripeTaxForWooCommerce\StripeTax_Options;

use Throwable;

use WC_Order_Factory;
use WC_Order_Item_Fee;


/**
 * Class for handling hooks used in order tax calculations
 */
abstract class Order_Tax_Calculation extends Hook_Handlers {
	const ACTIONS = array(
		'checkout_create_order_line_item',
		'checkout_create_order_fee_item',
		'checkout_create_order_shipping_item',
		'order_before_calculate_taxes',
		'order_after_calculate_totals',
		'order_item_after_calculate_taxes',
		'order_item_shipping_after_calculate_taxes',
	);
	const FILTERS = array(
		'get_order_item_classname',
	);

	const ACTIVATION_OPTIONS = array();


	/**
	 * Creates reference meta for order items created from cart.
	 *
	 * @param object $order_item Order item.
	 * @param string $cart_item_key Cart item key.
	 * @param array  $cart_item Cart item values.
	 * @param object $order Order.
	 */
	public static function checkout_create_order_line_item( $order_item, $cart_item_key, $cart_item, $order ) {
		$reference = Cart_Input::build_item_reference_by_type( $cart_item, $order_item->get_type() );

		$order_item->add_meta_data( Cart_Input::CART_ITEM_REFERENCE_META_NAME, $reference, true );

		$prices_include_tax = wc_prices_include_tax();

		$tax_behavior = $prices_include_tax ? Order_Input::TAX_BEHAVIOR_INCLUSIVE : Order_Input::TAX_BEHAVIOR_EXCLUSIVE;

		$order_item->update_meta_data( '__stripe_tax_behavior', $tax_behavior );

		if ( Order_Input::TAX_BEHAVIOR_INCLUSIVE !== $tax_behavior ) {
			return;
		}

		Order_Controller::store_totals_tax_inclusive( $order_item );
	}

	/**
	 * Creates reference meta for order fee items created from cart.
	 *
	 * @param object $fee_order_item Order fee item.
	 * @param string $fee_item_id Fee item id.
	 * @param object $fee_cart_item Cart fee item values.
	 * @param object $order Order.
	 */
	public static function checkout_create_order_fee_item( $fee_order_item, $fee_item_id, $fee_cart_item, $order ) {
		$reference = Cart_Input::build_item_reference_by_type( $fee_cart_item, $fee_order_item->get_type() );

		$fee_order_item->add_meta_data( Cart_Input::CART_ITEM_REFERENCE_META_NAME, $reference, true );

		$prices_include_tax = wc_prices_include_tax();

		$tax_behavior = $prices_include_tax && StripeTax_Options::item_allow_price_tax_inclusive( 'fee' ) ? Order_Input::TAX_BEHAVIOR_INCLUSIVE : Order_Input::TAX_BEHAVIOR_EXCLUSIVE;

		$fee_order_item->update_meta_data( '__stripe_tax_behavior', $tax_behavior );

		if ( Order_Input::TAX_BEHAVIOR_INCLUSIVE !== $tax_behavior ) {
			return;
		}

		Order_Controller::store_totals_tax_inclusive( $fee_order_item );
	}

	/**
	 * Stores tax behavior metadata for shipping order items created from cart data.
	 *
	 * @param object $shipping_order_item Order shipping item.
	 * @param string $package_key Shipping package key.
	 * @param array  $package Shipping package values.
	 * @param object $order Order.
	 */
	public static function checkout_create_order_shipping_item( $shipping_order_item, $package_key, $package, $order ) {
		$prices_include_tax = wc_prices_include_tax();

		$tax_behavior = $prices_include_tax && StripeTax_Options::item_allow_price_tax_inclusive( 'shipping' ) ? Order_Input::TAX_BEHAVIOR_INCLUSIVE : Order_Input::TAX_BEHAVIOR_EXCLUSIVE;

		$shipping_order_item->update_meta_data( '__stripe_tax_behavior', $tax_behavior );

		if ( Order_Input::TAX_BEHAVIOR_INCLUSIVE !== $tax_behavior ) {
			return;
		}

		Order_Controller::store_totals_tax_inclusive( $shipping_order_item );
	}

	/**
	 * "woocommerce_order_before_calculate_taxes" hook handler
	 *
	 * @param array  $args WC args.
	 * @param object $order The order.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function order_before_calculate_taxes( $args, $order ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		try {
			if ( is_admin() ) {
				return;
			}

			Order_Controller::calculate_taxes( $order, null, null );
		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
			self::set_current_request_failed( true );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
			self::set_current_request_failed( true );
		}
	}

	/**
	 * 'woocommerce_order_after_calculate_totals' hook handler.
	 *
	 * @param bool   $and_taxes Hook arg.
	 * @param object $order The order.
	 */
	public static function order_after_calculate_totals( $and_taxes, $order ) {
		Order_Controller::sync_order_totals( $order );
	}

	/**
	 * "woocommerce_order_item_after_calculate_taxes" hook handler
	 *
	 * @param object $item Order item.
	 * @param array  $args WC args.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function order_item_after_calculate_taxes( $item, $args ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		try {
			Order_Controller::update_item_taxes( $item );
		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
		}
	}

	/**
	 * "woocommerce_order_item_shipping_after_calculate_taxes" hook handler
	 *
	 * @param object $item Order item.
	 * @param array  $tax_location Default tax location.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function order_item_shipping_after_calculate_taxes( $item, $tax_location ) {
		if ( ! static::is_enabled() ) {
			return;
		}

		try {
			Order_Controller::update_item_taxes( $item );
		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
		}
	}

	/**
	 * "woocommerce_get_order_item_classname" hook handler
	 *
	 * @param string $class_name Default class name.
	 * @param string $item_type Order item type.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function get_order_item_classname( $class_name, $item_type ) {
		if ( ! static::is_enabled() ) {
			return $class_name;
		}

		if ( 'tax' === $item_type ) {
			$class_name = StripeOrderItemTax::class;
		}

		return $class_name;
	}
}
