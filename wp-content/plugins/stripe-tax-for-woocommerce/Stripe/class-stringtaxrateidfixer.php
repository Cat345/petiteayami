<?php
/**
 * Tax Rate IDs fixer
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WooCommerce\StripeTaxTaxRateMemRepo;
use Exception;

/**
 * StringTaxRateIdFixer class.
 */
class StringTaxRateIdFixer {

	const CACHE_GROUP = 'stripe-tax-for-woocommerce-order-finder';
	const CACHE_KEY   = 'tax-woocommerce-order-finder';

	const NUM_ORDERS = 50;

	/**
	 * Returns $orders
	 */
	public static function get_orders() {

		$wc_order_ids = static::get_order_ids();

		if ( count( $wc_order_ids ) === 0 ) {
			return array();
		}

		$orders = array();

		foreach ( $wc_order_ids as $wc_order_id ) {
			$wc_order = wc_get_order( $wc_order_id );

			if ( ! $wc_order ) {
				continue;
			}
			$orders[ $wc_order_id ] = $wc_order;
		}

		return $orders;
	}

	/**
	 * Returns WooCommerce order IDs that have at least one **order line item**
	 * whose `_line_tax_data` meta contains the substring "stripe_tax_for_woocommerce".
	 *
	 * Safety:
	 * - Table names are built from `$wpdb->prefix` + known suffixes (no user input).
	 * - Only values are passed through `$wpdb->prepare()`.
	 *
	 * @return int[] List of order IDs (cast to int).
	 */
	private static function get_order_ids(): array {

		$items = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );
		if ( is_array( $items ) ) {
			wp_cache_set( self::CACHE_KEY, $items, self::CACHE_GROUP );
		}

		global $wpdb;

		$like = '%' . $wpdb->esc_like( 'stripe_tax_for_woocommerce' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT woi.order_id
				FROM {$wpdb->prefix}woocommerce_order_items AS woi
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta  AS woim
				ON woim.order_item_id = woi.order_item_id
				WHERE woi.order_item_type = 'line_item'
				AND woim.meta_key = %s
				AND meta_value LIKE %s
				LIMIT %d",
				array(
					'_line_tax_data',
					$like,
					static::NUM_ORDERS,
				)
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Converts an order string tax rate IDs to integers.
	 *
	 * @param object $order Order.
	 */
	public static function fix_order_tax_rate_ids( $order ) {
		$fixed_rate_id_map = array();
		$tax_items         = $order->get_items( 'tax' );

		foreach ( array( 'line_item', 'shipping' ) as $line_item_type ) {
			$items = $order->get_items( $line_item_type );

			foreach ( $items as $item ) {
				$item_taxes       = $item->get_taxes();
				$fixed_item_taxes = array();

				foreach ( array( 'total', 'subtotal' ) as $total_key ) {
					if ( ! array_key_exists( $total_key, $item_taxes ) || ! is_array( $item_taxes[ $total_key ] ) ) {
						continue;
					}

					foreach ( $item_taxes[ $total_key ] as $rate_id => $tax_amount ) {
						$rate_id_parts = explode( '__', $rate_id );

						if ( count( $rate_id_parts ) < 4 ) {
							continue;
						}

						$fixed_rate_id = StripeTaxTaxRateMemRepo::find_or_create(
							'',
							'',
							$rate_id_parts[2],
							$rate_id_parts[3]
						);

						$fixed_item_taxes[ $total_key ][ $fixed_rate_id ] = $tax_amount;

						$fixed_rate_id_map[ $rate_id ] = $fixed_rate_id;
					}
				}

				$item->set_taxes( $fixed_item_taxes );
				$item->save();
			}
		}

		$tax_items = $order->get_items( 'tax' );

		foreach ( $tax_items as $tax_item ) {
			$rate_id   = $tax_item->get_rate_id();
			$rate_code = $tax_item->get_rate_code();

			$fixed_rate_id = null;

			if ( isset( $fixed_rate_id_map [ $rate_id ] ) ) {
				$fixed_rate_id = $fixed_rate_id_map [ $rate_id ];
			} elseif ( isset( $fixed_rate_id_map [ $rate_code ] ) ) {
				$fixed_rate_id = $fixed_rate_id_map [ $rate_code ];
			}

			if ( is_null( $fixed_rate_id ) ) {
				continue;
			}

			$tax_item->set_rate_id( $fixed_rate_id );

			$tax_item->save();
		}
	}

	/**
	 * Process he next NUM_ORDERS orders.
	 */
	public static function process() {
		$orders = static::get_orders();

		foreach ( $orders as $order ) {
			static::fix_order_tax_rate_ids( $order );
		}

		return count( $orders );
	}
}
