<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Product_Variation_Bundles' ) ) {
	/**
	 * Product Bundles - Variation Bundles
	 *
	 * Class WFOCU_Compatibility_With_Product_Variation_Bundles
	 */
	class WFOCU_Compatibility_With_Product_Variation_Bundles {

		public function __construct() {
			if ( true === class_exists( 'WC_Bundles' ) && $this->is_enable() ) {
				add_filter( 'wfocu_upsell_package', array( $this, 'maybe_add_bundle_product_in_variation' ), 4 );
			}
		}

		public function is_enable() {
			if ( true === class_exists( 'WC_PB_Variable_Bundles' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * check bundle product in variation and regenerate package
		 *
		 * @param $package
		 *
		 * @return mixed
		 */
		public function maybe_add_bundle_product_in_variation( $package ) {

			if ( empty( $package['products'] ) ) {
				return $package;
			}
			foreach ( $package['products'] as &$product ) {
				$get_product = $product['data'];
				if ( is_a( $get_product, 'WC_Product' ) && $get_product->is_type( 'variation' ) ) {
					if ( ! empty( $get_product->get_meta( '_wc_pb_variable_bundle' ) ) ) {
						$bundle_id      = $get_product->get_meta( '_wc_pb_variable_bundle' );
						$bundle_product = wc_get_product( $bundle_id );

						if ( is_a( $bundle_product, 'WC_Product' ) ) {
							$product['id']                = $bundle_product->get_id();
							$product['data']              = $bundle_product;
							$product['_offer_data']->type = 'bundle';
							$product['_offer_data']->name = $bundle_product->get_title();
							$product['_offer_data']->data = $bundle_product;
							unset( $product['_offer_data']->variations_data );
						}

					}
				}
			}

			return $package;
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Product_Variation_Bundles(), 'product_variation_bundles' );
}