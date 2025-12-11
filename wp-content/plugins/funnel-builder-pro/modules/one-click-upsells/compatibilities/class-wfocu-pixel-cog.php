<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFOCU_Compatibility_Pixel_COG
 * plugin weblink: http://www.pixelyoursite.com/
 * plugin-name: Cost of Goods by PixelYourSite
 */

if ( ! class_exists( 'WFOCU_Compatibility_Pixel_COG' ) ) {
	class WFOCU_Compatibility_Pixel_COG {
		public function __construct() {
			add_filter( 'wfocu_ecommerce_pixel_tracking_value', [ $this, 'cog_line_subtotal' ], 10, 4 );
		}

		public function is_enable() {
			if ( defined( 'PIXEL_COG_VERSION' ) ) {
				return true;
			}

			return false;
		}

		public function cog_line_subtotal( $total, $data, $mode, $settings ) {

			if ( ! $this->is_enable() ) {
				return $total;
			}

			if ( ! $data instanceof WC_Order ) {
				return $total;
			}

			if ( empty( $data->get_id() ) || 0 === $data->get_id() ) {
				return $total;
			}

			if ( 'fb' !== $mode && 'pint' !== $mode ) {
				return $total;
			}

			$cog_value = $this->get_available_product_cog_order( $data->get_id() );
			if ( $cog_value !== '' ) {
				return (float) round( $cog_value, 2 );
			}

			return $total;
		}

		/**
		 * @param $order_id
		 *
		 * @return float|int|mixed|string
		 */
		public function get_available_product_cog_order( $order_id ) {
			$order        = wc_get_order( $order_id );
			$order_total  = 0.0;
			$cost         = 0;
			$notice       = '';
			$custom_total = 0;
			$cat_isset    = 0;
			$isWithoutTax = get_option( '_pixel_cog_tax_calculating' ) === 'no';

			$shipping    = $order->get_shipping_total( "edit" );
			$order_total = $order->get_total( 'edit' ) - $shipping;

			if ( $isWithoutTax ) {
				$order_total -= $order->get_total_tax( 'edit' );
			} else {
				$order_total -= $order->get_shipping_tax( "edit" );
			}

			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
				$product    = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$cost_type    = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_type', true );
				$product_cost = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_val', true );

				if ( ! $product_cost && $product->is_type( "variation" ) ) {
					$cost_type    = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
					$product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
				}


				$args = array( 'qty' => 1, 'price' => $product->get_price() );
				$qlt  = $item['quantity'];

				if ( $isWithoutTax ) {
					$price = wc_get_price_excluding_tax( $product, $args );
				} else {
					$price = wc_get_price_including_tax( $product, $args );
				}
				$price=floatval($price);
				if ( $product_cost ) {
					$cost         = ( $cost_type === 'percent' ) ? $cost + ( $price * ( $product_cost / 100 ) * $qlt ) : $cost + ( $product_cost * $qlt );
					$custom_total = $custom_total + ( $price * $qlt );
				} else {
					$product_cost = $this->get_product_cost_by_cat( $product_id );
					$cost_type    = $this->get_product_type_by_cat( $product_id );
					if ( $product_cost ) {
						$cost         = ( $cost_type === 'percent' ) ? $cost + ( $price * ( $product_cost / 100 ) * $qlt ) : $cost + ( $product_cost * $qlt );
						$custom_total = $custom_total + ( $price * $qlt );
					} else {
						$product_cost = get_option( '_pixel_cost_of_goods_cost_val' );
						$cost_type    = get_option( '_pixel_cost_of_goods_cost_type' );
						if ( $product_cost ) {
							$cost         = ( $cost_type === 'percent' ) ? (float) $cost + ( (float) $price * ( (float) $product_cost / 100 ) * $qlt ) : (float) $cost + ( (float) $product_cost * $qlt );
							$custom_total = $custom_total + ( $price * $qlt );
						}
					}
				}
			}

			return $order_total - $cost;

		}

		/**
		 * @param $product_id
		 *
		 * @return false|mixed|string
		 */
		public function get_product_cost_by_cat( $product_id ) {
			$term_list = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			$cost      = array();
			foreach ( $term_list as $term_id ) {
				$cost[ $term_id ] = get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true );
			}
			if ( empty( $cost ) ) {
				return '';
			} else {
				asort( $cost );
				$max = end( $cost );

				return $max;
			}
		}

		/**
		 * @param $product_id
		 *
		 * @return mixed|string
		 */
		public function get_product_type_by_cat( $product_id ) {
			$term_list = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			$cost      = array();
			foreach ( $term_list as $term_id ) {
				$cost[ $term_id ] = array(
					get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true ),
					get_term_meta( $term_id, '_pixel_cost_of_goods_cost_type', true )
				);
			}
			if ( empty( $cost ) ) {
				return '';
			} else {
				asort( $cost );
				$max = end( $cost );

				return $max[1];
			}
		}

	}


	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_Pixel_COG(), 'pixel_cog' );
}
