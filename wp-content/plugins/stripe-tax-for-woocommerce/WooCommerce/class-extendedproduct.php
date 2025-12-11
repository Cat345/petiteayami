<?php
/**
 * Extends WooCommerce product with additional fields.
 *
 * @package tripe\StripeTaxForWooCommerce\WooCommerce
 */

namespace Stripe\StripeTaxForWooCommerce\WooCommerce;

// Exit if script started not from WordPress.
use Stripe\StripeTaxForWooCommerce\Stripe\StripeTaxPluginHelper;
use Stripe\StripeTaxForWooCommerce\Stripe\Validate;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;
use WC_Data;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Product class replacement.
 */
class ExtendedProduct {

	/**
	 * Database table name for storing additional WooCommerce product fields
	 *
	 * @var string
	 */
	public const TABLE_NAME = STRIPE_TAX_FOR_WOOCOMMERCE_DB_PREFIX . 'products';

	/**
	 * WooCommerce Product id.
	 *
	 * @var int
	 */
	protected $product_id;

	/**
	 * Constructor for ExtendedProduct.
	 *
	 * @param int $product_id Product id.
	 */
	public function __construct( int $product_id = 0 ) {
		$this->product_id = $product_id;
	}

	/**
	 * Gets WooCommerce Product id.
	 */
	protected function get_product_id() {
		return $this->product_id;
	}

	/**
	 * Gets additional WooCommerce Product fields from WordPress object cache to reduce database requests.
	 *
	 * @param int|null $id Product id, null by default.
	 *
	 * @return array
	 */
	protected function get_from_object_cache( $id = null ): array {
		$product_id = $id ?? (int) $this->get_product_id();
		$products   = wp_cache_get( 'products', 'stripe-tax-for-woocommerce' );

		if ( ! is_array( $products ) || ! array_key_exists( $product_id, $products ) ) {
			return array();
		}

		return $products[ $product_id ];
	}

	/**
	 * Sets additional WooCommerce Product fields into WordPress object cache.
	 *
	 * @param array $product Array of additional WooCommerce Product fields to store.
	 */
	protected function set_into_object_cache( array $product ) {
		$products = wp_cache_get( 'products', 'stripe-tax-for-woocommerce' );

		if ( ! is_array( $products ) ) {
			$products = array();
		}

		$products [ $this->product_id ] = array(
			'tax_code' => $product['tax_code'] ?? '',
		);

		wp_cache_set( 'products', $products, 'stripe-tax-for-woocommerce' );
	}

	/**
	 * Populate tax code from submitted form.
	 *
	 * @param WC_Data $wc_data WC_Data object. Usually here will be WooCommerce Product object.
	 *
	 * @return string
	 * @throws \Exception In case of invalid tax code entered.
	 */
	public function get_on_save_post_parameter_tax_code( WC_Data $wc_data ): string {
		$tax_code = '';
		$nonce    = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		wp_verify_nonce( $nonce );

		if ( isset( $_POST['_stripe_tax_for_woocommerce_tax_code'] ) ) {
			$tax_code = sanitize_text_field( wp_unslash( $_POST['_stripe_tax_for_woocommerce_tax_code'] ) );
		} elseif ( $wc_data->get_parent_id() ) {
			$tax_code = $this->get_extended_product( $wc_data->get_parent_id() )['tax_code'];
		} else {
			$product = $this->get_extended_product();
			if ( ! empty( $product ) && ! empty( $product['tax_code'] ) ) {
				$tax_code = sanitize_text_field( wp_unslash( $product['tax_code'] ) );
			}
		}

		if ( '' !== $tax_code && 'stfwc_inherit' !== $tax_code ) { // Empty and "stfwc_inherit" "tax_code" values are valid and in this situation means "using global Stripe Tax Settings parameter".
			Validate::validate_tax_code( $tax_code, Options::get_live_mode_key() );
		}
		return $tax_code;
	}

	/**
	 * Gets additional WooCommerce Product fields from database.
	 *
	 * @param int|null $id Product id, null by default.
	 *
	 * @return array
	 */
	protected function get_from_db( $id = null ) {
		global $wpdb;

		$product_id = $id ?? $this->product_id;

		if ( 'product_variation' === (string) get_post_type( $product_id ) ) {
			$variation_tax_class = (string) get_post_meta( $product_id, '_tax_class', true );

			if ( '' === $variation_tax_class || 'parent' === $variation_tax_class ) {
				$product_id = (int) wp_get_post_parent_id( $product_id );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT %i FROM %i WHERE %i = %d',
				array(
					'tax_code',
					static::TABLE_NAME,
					'product_id',
					$product_id,
				)
			),
			ARRAY_A
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Store additional WooCommerce Product fields to database.
	 *
	 * @param array $product WooCommerce Product.
	 */
	public function set_into_db( array $product ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i ( %i, %i ) VALUES ( %d, %s ) ON DUPLICATE KEY UPDATE %i = %s',
				array(
					static::TABLE_NAME,
					'product_id',
					'tax_code',
					(int) $this->get_product_id(),
					$product['tax_code'] ?? '',
					'tax_code',
					$product['tax_code'] ?? '',
				)
			)
		);
	}

	/**
	 * Load additional WooCommerce Product fields from WordPress object cache or database.
	 *
	 * @param int|null $id Product id, null by default.
	 *
	 * @param bool     $skip_object_cache Skip object cache.
	 */
	public function get_extended_product( $id = null, $skip_object_cache = false ): array {
		if ( ! $skip_object_cache ) {
			$product = $this->get_from_object_cache( $id );
			if ( $product ) {
				return $product;
			}
		}

		$product = $this->get_from_db( $id );
		if ( $product ) {
			$this->set_into_object_cache( $product );

			return $product;
		}

		return array(
			'tax_code' => '',
		);
	}

	/**
	 * Save additional WooCommerce Product fields into WordPress object cache and database.
	 *
	 * @param array $product WooCommerce Product.
	 */
	public function save_extended_product( $product ) {
		$new_product = array(
			'tax_code' => $product['tax_code'] ?? '',
		);

		if ( 'stfwc_inherit' === $new_product['tax_code'] ) {
			$new_product['tax_code'] = '';
		}

		$this->set_into_db( $new_product );
		$this->set_into_object_cache( $new_product );
	}
}
