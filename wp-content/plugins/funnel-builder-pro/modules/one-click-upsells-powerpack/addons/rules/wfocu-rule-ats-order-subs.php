<?php
if ( ! class_exists( 'WFOCU_Rule_Order_Subs' ) ) {
	/** WooCommerce all thing subscription plugin rule */
	class WFOCU_Rule_Order_Subs extends WFOCU_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'order_subs' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function get_possible_rule_values() {
			$result   = array();
			$products = WFOCU_Plugin_Compatibilities::get_compatibility_class( 'wfocu_wc_atts' )->subs_product_search( 'get_data' );
			if ( is_array( $products ) && count( $products ) > 0 ) {
				foreach ( $products as $product_id => $product_name ) {
					$result[ $product_id ] = $product_name;
				}
			}

			return $result;
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result    = false;
			$type      = $rule_data['operator'];
			$all_terms = array();

			if ( $env === 'cart' ) {

				$cart_contents = (array) WC()->cart->cart_contents;
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) > 0 ) {
					foreach ( $cart_contents as $cart_item ) {
						$productID   = $cart_item['product_id'];
						$variationID = $cart_item['variation_id'];
						if ( absint( $productID ) === 0 ) {
							if ( $cart_item['data'] instanceof WC_Product_Variation ) {
								$productID = $cart_item['data']->get_parent_id();
							} elseif ( $cart_item['data'] instanceof WC_Product ) {
								$productID = $cart_item['data']->get_id();
							}
						}

						if ( isset( $cart_item['wcsatt_data'] ) && isset( $cart_item['wcsatt_data']['active_subscription_scheme'] ) ) {
							$all_terms[] = $productID;
							$all_terms[] = $variationID;
							if ( ! empty( $cart_item['wcsatt_data']['active_subscription_scheme'] ) ) {
								$all_terms[] = $productID . '-' . $cart_item['wcsatt_data']['active_subscription_scheme'];
								$all_terms[] = $variationID . '-' . $cart_item['wcsatt_data']['active_subscription_scheme'];

							}
						}
					}
				}
			} else {
				$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order    = wc_get_order( $order_id );
				if ( $order->get_items() && is_array( $order->get_items() ) && count( $order->get_items() ) ) {
					foreach ( $order->get_items() as $cart_item ) {

						$product   = WFOCU_WC_Compatibility::get_product_from_item( $order, $cart_item );
						$productID = $product->get_id();

						$productID = ( $product->get_parent_id() ) ? $product->get_parent_id() : $productID;

						if ( version_compare( WC()->version, '3.0', '>=' ) ) {
							$variationID = $cart_item->get_variation_id();
						} else {
							$variationID = ( is_array( $cart_item['variation_id'] ) && count( $cart_item['variation_id'] ) > 0 ) ? $cart_item['variation_id'][0] : 0;
						}

						if ( isset( $cart_item['wcsatt_data'] ) && isset( $cart_item['wcsatt_data']['active_subscription_scheme'] ) ) {
							$all_terms[] = $productID;
							$all_terms[] = $variationID;
							if ( ! empty( $cart_item['wcsatt_data']['active_subscription_scheme'] ) ) {
								$all_terms[] = $productID . '-' . $cart_item['wcsatt_data']['active_subscription_scheme'];
								$all_terms[] = $variationID . '-' . $cart_item['wcsatt_data']['active_subscription_scheme'];

							}
						}
					}
				}
			}

			if ( empty( $all_terms ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'all':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_terms ) ) === count( $rule_data['condition'] );
						}
						break;
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rule_data['condition'], $all_terms ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $all_terms ) ) {
							$result = ( count( array_intersect( $rule_data['condition'], $all_terms ) ) === 0 );
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );

		}

	}
}