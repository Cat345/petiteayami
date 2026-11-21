<?php
/** Adapter — WooCommerce PayPal Payments compatibility. */
namespace WFFN\Bundles\Includes\Compat\Paypal_Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Frontend\Product_Display;

/**
 * Suppresses PayPal Payments' product-page Buy Now button on a bundle product.
 *
 * That button binds to WooCommerce's native `form.cart`, which this plugin
 * only fades via CSS and never removes (a page whose JS fails must stay
 * purchasable). Clicking it drives PayPal's own ChangeCartEndpoint, which
 * empties `WC()->cart` and rebuilds it from the native form's raw product id
 * and quantity alone — silently dropping the bundle's tiers, members and any
 * accepted upsell/gift before checkout ever runs. Forcing the shopper through
 * this plugin's own add-to-cart keeps the real cart intact; PayPal's
 * checkout-page button reads that cart correctly and is left untouched.
 */
class Adapter implements Integration {

	/**
	 * Is PayPal Payments active?
	 *
	 * @return bool
	 */
	public function is_enable() {
		return class_exists( '\WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway' );
	}

	/**
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_paypal_payments_localized_script_data', array( $this, 'suppress_product_button' ) );
	}

	/**
	 * @param array $data PayPal's own localized script config.
	 * @return array
	 */
	public function suppress_product_button( $data ) {
		if ( ! is_array( $data ) || empty( $data['single_product_buttons_enabled'] ) ) {
			return $data;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $data;
		}
		$pid = get_queried_object_id();
		if ( ! $pid || empty( Product_Display::flags_for_product( $pid ) ) ) {
			return $data;
		}
		$data['single_product_buttons_enabled'] = false;
		return $data;
	}
}
