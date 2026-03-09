<?php
/**
 * Cart tax calculation hook handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WordPress\Hook_Handlers;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Data;
use Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation\Cart_Controller;
use Stripe\StripeTaxForWooCommerce\SDK\lib\Exception\InvalidRequestException;

use WC_Cart;

use Throwable;

/**
 * Class for handling hooks used in cart tax calculations
 */
abstract class Cart_Tax_Calculation extends Hook_Handlers {
	const ACTIONS = array(
		'after_calculate_totals',
	);

	const FILTERS = array();

	const ACTIVATION_OPTIONS = array();

	/**
	 * "woocommerce_after_calculate_totals" hook handler
	 *
	 * @param WC_Cart $cart The cart.
	 *
	 * @throws Throwable Throws caught exception.
	 */
	public static function after_calculate_totals( WC_Cart $cart ): void {
		if ( ! static::is_enabled() ) {
			return;
		}

		try {
			$tax_behavior = wc_prices_include_tax() ? Data::TAX_BEHAVIOR_INCLUSIVE : Data::TAX_BEHAVIOR_EXCLUSIVE;

			Cart_Controller::calculate_taxes( $cart, $tax_behavior );
		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
		}
	}
}
