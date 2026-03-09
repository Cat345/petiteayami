<?php
/**
 * Stripe Product Tax Codes Repository Service.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\StripeTax_Options;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;

/**
 * Stripe Product Tax Codes Repository Service
 */
abstract class Product_Tax_Code_Repo {
	/**
	 * Database table name for storing additional WooCommerce product fields
	 *
	 * @var string
	 */
	public const TABLE_NAME = STRIPE_TAX_FOR_WOOCOMMERCE_DB_PREFIX . 'products';

	/**
	 * Returns a product, shipping or fee item tax code based on its type and id
	 *
	 * @param string   $item_type Item type.
	 * @param int|null $item_id   Item ID.
	 *
	 * @return string
	 */
	public static function get_tax_code_by_type_and_id( $item_type, $item_id = null ) {
		global $wpdb;
		$tax_code = '';
		switch ( $item_type ) {
			case 'shipping':
				return Options::DEFAULT_OPTION_SHIPPING_TAX_CODE;
			case 'fee':
				return Options::get_fee_tax_code();
			case 'line_item':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT %i FROM %i WHERE %i = %d',
						array(
							'tax_code',
							static::TABLE_NAME,
							'product_id',
							$item_id,
						)
					),
					ARRAY_A
				);

				if ( isset( $result['tax_code'] ) ) {
					$tax_code = $result['tax_code'];
				}

				if ( ! $tax_code ) {
					$tax_code = Options::get_tax_code();
				}

				break;
		}

		return $tax_code;
	}

	/**
	 * Sets a product, shipping or fee item tax code based on its type and id
	 *
	 * @param string   $item_type Item type.
	 * @param int|null $item_id   Item ID.
	 * @param string   $tax_code  Item tax code.
	 */
	public static function set_tax_code( $item_type, $item_id, $tax_code ) {
		global $wpdb;

		switch ( $item_type ) {
			case 'shipping':
				break;
			case 'line_item':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->query(
					$wpdb->prepare(
						'INSERT INTO %i ( %i, %i ) VALUES ( %d, %s ) ON DUPLICATE KEY UPDATE %i = %s',
						array(
							static::TABLE_NAME,
							'product_id',
							'tax_code',
							$item_id,
							$tax_code ?? '',
							'tax_code',
							$tax_code ?? '',
						)
					)
				);
				break;

			default:
		}
	}
}
