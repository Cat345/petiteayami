<?php
/**
 * WooCommerce Product Bundles compatibility for FunnelKit Order Bump
 *
 * Ensures bundle products can be added as order bumps with proper configuration.
 * Without this, bundle add-to-cart fails because WC_PB expects configuration from $_POST.
 *
 * @package FunnelKit Order Bump
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_WooCommerce_Product_Bundles' ) ) {

	/**
	 * Class WFOB_Compatibility_WooCommerce_Product_Bundles
	 */
	class WFOB_Compatibility_WooCommerce_Product_Bundles {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_bundle_configuration_for_order_bump' ), 5, 3 );
			add_filter( 'wfob_exclude_cart_item_in_rule', array( $this, 'exclude_bundled_items_from_rules' ), 10, 2 );
		}

		/**
		 * Check if WooCommerce Product Bundles is available
		 *
		 * @return bool
		 */
		public function is_available() {
			return function_exists( 'WC_PB' ) && class_exists( 'WC_PB_Cart' );
		}

		/**
		 * Add bundle configuration (stamp) to cart item data when adding bundle as order bump
		 *
		 * WC_PB builds configuration from $_POST which is empty during order bump AJAX.
		 * We inject the default configuration so the bundle can be added successfully.
		 *
		 * @param array $cart_item_data Cart item data.
		 * @param int   $product_id     Product ID.
		 * @param int   $variation_id   Variation ID (unused for bundles).
		 * @return array
		 */
		public function add_bundle_configuration_for_order_bump( $cart_item_data, $product_id, $variation_id ) {
			if ( ! $this->is_available() ) {
				return $cart_item_data;
			}

			// Only for order bump adds.
			if ( empty( $cart_item_data['_wfob_product'] ) ) {
				return $cart_item_data;
			}

			$product_type = WC_Product_Factory::get_product_type( $product_id );
			if ( 'bundle' !== $product_type ) {
				return $cart_item_data;
			}

			// WC_PB will use our stamp if set, skipping get_posted_bundle_configuration.
			if ( isset( $cart_item_data['stamp'] ) ) {
				return $cart_item_data;
			}

			$bundle = wc_get_product( $product_id );
			if ( ! $bundle || ! $bundle->is_type( 'bundle' ) ) {
				return $cart_item_data;
			}

			$configuration = WC_PB_Cart::instance()->parse_bundle_configuration( $bundle, array() );

			// Handle variable bundled products - add variation_id and attributes.
			foreach ( $configuration as $item_id => $item_config ) {
				$bundled_product = wc_get_product( $item_config['product_id'] );
				if ( ! $bundled_product || ! $bundled_product->is_type( 'variable' ) ) {
					continue;
				}

				$default_attributes = $bundled_product->get_default_attributes();
				if ( empty( $default_attributes ) ) {
					continue;
				}

				$variation_id = $this->get_variation_id_from_attributes( $bundled_product, $default_attributes );
				if ( $variation_id ) {
					$configuration[ $item_id ]['variation_id'] = $variation_id;
					$configuration[ $item_id ]['attributes']   = array();
					foreach ( $default_attributes as $attr_name => $attr_value ) {
						$configuration[ $item_id ]['attributes'][ wc_variation_attribute_name( $attr_name ) ] = $attr_value;
					}
				}
			}

			$cart_item_data['stamp']         = $configuration;
			$cart_item_data['bundled_items'] = array();

			return $cart_item_data;
		}

		/**
		 * Get variation ID from product and default attributes
		 *
		 * @param WC_Product $product            Variable product.
		 * @param array      $default_attributes Default attributes.
		 * @return int|null Variation ID or null.
		 */
		private function get_variation_id_from_attributes( $product, $default_attributes ) {
			if ( ! $product instanceof WC_Product_Variable ) {
				return null;
			}
			$variations = $product->get_available_variations();
			if ( empty( $variations ) ) {
				return null;
			}

			foreach ( $variations as $variation ) {
				$variation_attrs = isset( $variation['attributes'] ) ? $variation['attributes'] : array();
				$match           = true;
				foreach ( $default_attributes as $attr_name => $attr_value ) {
					$variation_key = wc_variation_attribute_name( $attr_name );
					if ( ! isset( $variation_attrs[ $variation_key ] ) || (string) $variation_attrs[ $variation_key ] !== (string) $attr_value ) {
						$match = false;
						break;
					}
				}
				if ( $match ) {
					return (int) $variation['variation_id'];
				}
			}

			// Fallback to first available variation.
			return isset( $variations[0]['variation_id'] ) ? (int) $variations[0]['variation_id'] : null;
		}

		/**
		 * Exclude bundled child items from order bump rules (e.g. cart product rules)
		 *
		 * Bundled items are part of the parent; rules should evaluate the parent only.
		 *
		 * @param bool  $status Exclude status.
		 * @param array $item   Cart item.
		 * @return bool
		 */
		public function exclude_bundled_items_from_rules( $status, $item ) {
			if ( isset( $item['bundled_by'] ) ) {
				return true;
			}

			return $status;
		}
	}

	new WFOB_Compatibility_WooCommerce_Product_Bundles();
}
