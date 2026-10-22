<?php
/**
 * Cart tax calculation hook handlers
 *
 * @package Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce\Hook_Handlers;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\Stripe\StripeCalculationTracker;
use Stripe\StripeTaxForWooCommerce\WordPress\Hook_Handlers;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
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

		$skip_cart = Options::is_skip_cart_tax_calculation();

		if ( ! StripeCalculationTracker::is_calculation_needed() ) {
			if ( $skip_cart ) {
				self::restore_cached_cart_taxes( $cart );
			}
			return;
		}

		try {
			$tax_behavior = wc_prices_include_tax() ? Data::TAX_BEHAVIOR_INCLUSIVE : Data::TAX_BEHAVIOR_EXCLUSIVE;

			Cart_Controller::calculate_taxes( $cart, $tax_behavior );

			if ( $skip_cart ) {
				self::cache_cart_taxes( $cart );
			}
		} catch ( InvalidRequestException $err ) {
			static::on_error( $err );
		} catch ( Throwable $err ) {
			static::on_generic_error( $err );
		}
	}

	/**
	 * Cache the cart's tax data to WC session for reuse on the cart page.
	 *
	 * @param WC_Cart $cart The cart.
	 */
	private static function cache_cart_taxes( WC_Cart $cart ): void {
		if ( ! WC()->session ) {
			return;
		}

		$tax_totals        = $cart->get_tax_totals();
		$cached_tax_totals = array();
		foreach ( $tax_totals as $code => $tax ) {
			$cached_tax_totals[ $code ] = array(
				'label'            => $tax->label,
				'amount'           => $tax->amount,
				'formatted_amount' => $tax->formatted_amount,
				'is_compound'      => $tax->is_compound,
			);
		}

		WC()->session->set(
			'stripe_tax_cached_cart_taxes',
			array(
				'total_tax'  => $cart->get_total_tax(),
				'tax_totals' => $cached_tax_totals,
				'cart_hash'  => self::get_stable_cart_hash( $cart ),
			)
		);
	}

	/**
	 * Restore cached tax data to the cart on the cart page.
	 *
	 * @param WC_Cart $cart The cart.
	 */
	private static function restore_cached_cart_taxes( WC_Cart $cart ): void {
		if ( ! WC()->session ) {
			return;
		}

		$cached = WC()->session->get( 'stripe_tax_cached_cart_taxes' );

		if ( empty( $cached ) || ! isset( $cached['total_tax'] ) || (float) $cached['total_tax'] <= 0 ) {
			return;
		}

		if ( isset( $cached['cart_hash'] ) && self::get_stable_cart_hash( $cart ) !== $cached['cart_hash'] ) {
			return;
		}

		$total_tax = (float) $cached['total_tax'];
		$cart->set_total_tax( $total_tax );

		if ( ! wc_prices_include_tax() ) {
			$raw_total = (float) $cart->get_total( 'edit' );
			$cart->set_total( $raw_total + $total_tax );
		}
	}

	/**
	 * Generate a cart hash based on product IDs, variation IDs, and quantities.*
	 *
	 * @param WC_Cart $cart The cart.
	 *
	 * @return string Cart contents hash.
	 */
	private static function get_stable_cart_hash( WC_Cart $cart ): string {
		$items = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			$items[ $key ] = array(
				'product_id'   => $item['product_id'] ?? 0,
				'variation_id' => $item['variation_id'] ?? 0,
				'quantity'     => $item['quantity'] ?? 0,
			);
		}

		$coupons = $cart->get_applied_coupons();
		sort( $coupons );

		return md5(
			(string) wp_json_encode(
				array(
					'items'   => $items,
					'coupons' => $coupons,
				)
			)
		);
	}
}
