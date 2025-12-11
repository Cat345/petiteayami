<?php

namespace FKCart\Pro;

use FKCart\Includes\Data;
use FKCart\Includes\Front as Front;

if ( ! class_exists( '\FKCart\Pro\Upsells' ) ) {
	#[\AllowDynamicProperties]
	class Upsells {
		private static $instance = null;

		private function __construct() {
			$data = Data::get_db_settings();
			if ( ! isset( $data['enable_cart'] ) || 0 === intval( $data['enable_cart'] ) ) {
				return false;
			}
			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'woocommerce_create_order_line_item' ], 999999, 3 );
			add_action( 'woocommerce_order_fully_refunded', array( $this, 'fully_refunded_process' ) );
			add_action( 'woocommerce_order_partially_refunded', array( $this, 'partially_refunded_process' ), 10, 2 );
			add_action( 'woocommerce_checkout_create_order', [ $this, 'update_reward_data_in_order' ] );
			add_action( 'woocommerce_delete_order', [ $this, 'fully_refunded_process' ] );
		}

		/**
		 * @return Upsells
		 */
		public static function getInstance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;

		}

		/**
		 * @param $item \WC_Order_Item
		 * @param $cart_item_key
		 * @param $values
		 */
		public function woocommerce_create_order_line_item( $item, $cart_item_key, $values ) {
			if ( isset( $values['_fkcart_upsell'] ) ) {
				$item->add_meta_data( '_fkcart_upsell', 'yes' );
			}
			if ( isset( $values['_fkcart_free_gift'] ) ) {
				$item->add_meta_data( '_fkcart_free_gift', 'yes' );
			}
		}

		public function update_reward_data_in_order( $order ) {
			if ( ! $order instanceof \WC_Order ) {
				return;
			}

			$reward = Rewards::getInstance();
			$data   = [
				'_fkcart_upsell_views'          => $this->get_upsell_views(),
				'_fkcart_free_gift_views'       => $reward->get_free_gift_views(),
				'_fkcart_free_shipping_methods' => $reward->get_applied_free_shipping(),
				'_fkcart_discount_code_views'   => $reward->get_discount_views(),
			];

			// Filter out empty values
			$data = array_filter( $data, function ( $value ) {
				return ! empty( $value ) || $value === '0';
			} );

			if ( empty( $data ) ) {
				return;
			}

			// Convert arrays to JSON strings and add meta data
			foreach ( $data as $key => $value ) {
				if ( $key !== '_fkcart_free_shipping_methods' ) {
					$value = wp_json_encode( array_map( 'strval', (array) $value ) );
				}
				$order->add_meta_data( $key, $value );
			}
		}

		/**
		 * Mark refunded product items status refunded
		 *
		 * @param $order_id
		 * @param $refund_id
		 *
		 * @return void
		 */
		public function partially_refunded_process( $order_id, $refund_id ) {
			try {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof \WC_Order ) {
					return;
				}

				/** Check if order status refunded */
				if ( $order->has_status( 'refunded' ) ) {
					$this->fully_refunded_process( $order_id );

					return;
				}

				$refund = wc_get_order( $refund_id );
				if ( ! $refund instanceof \WC_Order_Refund ) {
					return;
				}

				$items = $refund->get_items();
				if ( 0 === count( $items ) ) {
					return;
				}

				/** Process each line item */
				foreach ( $items as $refund_item ) {
					$item_id = $refund_item->get_meta( '_refunded_item_id', true );
					if ( empty( $item_id ) ) {
						/** No cart upsell product */
						continue;
					}

					$item = $order->get_item( $item_id );
					if ( ! $item instanceof \WC_Order_Item ) {
						continue;
					}

					$is_upsell     = $item->get_meta( '_fkcart_upsell' );
					$is_spl_addon  = $item->get_meta( '_fkcart_spl_addon' );
					$refund_amount = abs( $refund_item->get_total() );
					$product_id    = $item->get_product_id();

					if ( $refund_amount > 0 ) {
						if ( '' !== $is_upsell ) {
							$this->update_refund_price( $product_id, $order_id, $refund_amount, 1 );
						}
						if ( '' !== $is_spl_addon ) {
							$this->update_refund_price( $product_id, $order_id, $refund_amount, 3 );
						}
					}

				}
			} catch ( \Exception|\Error $e ) {

			}


		}

		public function update_refund_price( $product_id, $order_id, $refund_amount, $type ) {
			global $wpdb;
			$upsell_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, price FROM " . $wpdb->prefix . "fk_cart_products WHERE type = %d AND product_id = %d AND oid = %d ", $type, $product_id, $order_id ), ARRAY_A );
			if ( is_array( $upsell_data ) && count( $upsell_data ) > 0 ) {
				$upsell_args = array(
					'price' => ( $upsell_data['price'] <= $refund_amount ) ? 0 : $upsell_data['price'] - $refund_amount
				);
				$wpdb->update( $wpdb->prefix . "fk_cart_products", $upsell_args, [ 'type' => 1, 'id' => $upsell_data['id'] ] );
			}
		}

		/**
		 * Mark refunded order all product items status as refunded
		 *
		 * @param $order_id
		 *
		 * @return void
		 */
		public function fully_refunded_process( $order_id ) {
			try {
				global $wpdb;
				// Start the transaction to ensure both deletes happen together
				$wpdb->query( 'START TRANSACTION' );
				$wpdb->delete( $wpdb->prefix . 'fk_cart_products', array( 'oid' => $order_id ) );
				$wpdb->delete( $wpdb->prefix . 'fk_cart', array( 'oid' => $order_id ) );
				$wpdb->query( 'COMMIT' );
			} catch ( \Exception|\Error $e ) {

			}

		}

		/**
		 * Get upsell products for cart
		 *
		 * @return array
		 */
		public function get_upsell_products() {
			/** Validate */
			if ( Plugin::valid_l() === false ) {
				return [];
			}

			$upsell_ids = $this->get_upsell_ids();
			if ( empty( $upsell_ids ) ) {
				return $upsell_ids;
			}

			$max_upsell = Data::get_value( 'upsell_max_count' );
			$max_upsell = absint( $max_upsell );
			$upsells    = [];
			foreach ( $upsell_ids as $product_id ) {
				if ( empty( $product_id ) ) {
					continue;
				}
				$product = wc_get_product( $product_id );
				if ( ! $product instanceof \WC_Product || ! $product->is_in_stock() || 'publish' !== $product->get_status() ) {
					continue;
				}
				if ( $max_upsell > 0 && count( $upsells ) >= $max_upsell ) {
					break;
				}

				$upsells[ $product_id ] = Front::get_instance()->get_preview_item( $product );
			}
			$this->update_upsell_view( $upsells );// Update Upsell views in session

			return $upsells;
		}

		/**
		 * Get upsell products ids
		 *
		 * @return array
		 */
		public function get_upsell_ids() {
			$items = Front::get_instance()->get_items();
			if ( empty( $items ) ) {
				return [];
			}

			/** @var \WC_Product $_product */
			$r_type          = $this->get_recommendation_type();
			$out_puts        = [];
			$already_used    = [];
			$default_upsells = [];

			$show_default_upsells = Data::get_value( 'show_default_upsell' );
			$show_default_upsells = ( 1 === intval( $show_default_upsells ) || true === $show_default_upsells || 'true' === strval( $show_default_upsells ) );

			foreach ( $items as $item ) {
				$_product = isset( $item['product'] ) ? $item['product'] : null;
				if ( is_null( $_product ) || ! $_product instanceof \WC_Product ) {
					continue;
				}
				$product_id     = $_product->get_id();
				$already_used[] = $product_id;
				if ( fkcart_is_variation_product_type( $_product->get_type() ) ) {
					$parent_id      = $_product->get_parent_id();
					$_product       = wc_get_product( $parent_id );
					$already_used[] = $parent_id;
				}

				if ( 'both' === $r_type ) {
					$upsell_ids = array_merge( $_product->get_upsell_ids(), $_product->get_cross_sell_ids() );
				} else {
					$upsell_ids = 'upsell' === $r_type ? $_product->get_upsell_ids() : $_product->get_cross_sell_ids();
				}
				$out_puts = array_merge( $out_puts, $upsell_ids );
				unset( $_product, $upsell_ids, $is_variation, $product_id );
			}

			/** If no upsell & cross-sells found then check for default upsells OR always show default upsell setting is enabled */
			if ( empty( $out_puts ) || $show_default_upsells ) {
				$default_upsells = $this->get_default_upsells();
			}

			$out_puts = array_merge( $default_upsells, $out_puts );
			if ( empty( $out_puts ) ) {
				return [];
			}

			return array_filter( array_unique( $out_puts ), function ( $single ) use ( $already_used ) {
				return ! in_array( $single, $already_used );
			} );
		}

		/**
		 * Get upsell recommendation products types
		 *
		 * @return mixed|string
		 */
		public function get_recommendation_type() {
			return Data::get_value( 'upsell_type' );
		}

		/**
		 * Get default upsell products ids saved in the DB
		 *
		 * @return array
		 */
		public function get_default_upsells() {
			$default_upsell = Data::get_value( 'default_upsell' );
			if ( empty( $default_upsell ) ) {
				return [];
			}

			return apply_filters( 'fkcart_default_upsells', array_map( 'intval', array_column( $default_upsell, 'key' ) ) );
		}


		/***
		 * Update available upsells view in sessions during cart process.
		 *
		 * @param $upsells []
		 *
		 * @return void
		 */
		public function update_upsell_view( $upsells ) {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return;
			}
			$already_upsell_views = WC()->session->get( '_fkcart_upsell_views', [] );
			$upsells              = array_merge( $already_upsell_views, array_keys( $upsells ) );
			WC()->session->set( '_fkcart_upsell_views', array_unique( $upsells ) );
		}

		/**
		 * return no of upsell view during checkout process.
		 * @return array
		 */
		public function get_upsell_views() {
			if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
				return [];
			}

			return WC()->session->get( '_fkcart_upsell_views' );
		}


	}

}
