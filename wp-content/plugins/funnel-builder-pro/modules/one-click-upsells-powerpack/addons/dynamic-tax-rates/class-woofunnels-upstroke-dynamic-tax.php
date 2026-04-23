<?php
defined( 'ABSPATH' ) || exit;

/**
 * Streamlined Tax Calculator - Focus on batching and add-to-cart method
 */
if ( ! class_exists( 'WooFunnels_UpStroke_Dynamic_Tax' ) ) {

	class WooFunnels_UpStroke_Dynamic_Tax {

		public static $instance;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Enhanced tax calculation with variation support and key mapping
		 */
		public function calculate_product_taxes( $products, $location, $order ) {
			// Check tax exemption
			$is_tax_exempt = $order->get_meta( 'is_vat_exempt' );
			if ( ! empty( $is_tax_exempt ) && 'yes' === $is_tax_exempt ) {
				return array(
					'total_tax'  => 0,
					'tax_data'   => array(),
					'tax_exempt' => true,
				);
			}

			if ( empty( $products ) ) {
				return array(
					'total_tax'  => 0,
					'tax_data'   => array(),
					'tax_exempt' => false,
				);
			}
			add_filter('woocommerce_is_cart', '__return_true');
			// Set customer location for tax calculation
			list( $country, $state, $city, $postcode ) = $location;

			WC()->customer->set_location( $country, $state, $postcode, $city );

			// Store original cart contents
			$original_cart = WC()->cart->get_cart_contents();
			WC()->cart->empty_cart();

			$cart_position = 0;
			$key_mapping   = array();

			// Add products to cart for tax calculation
			foreach ( $products as $index => $product ) {
				$product_id   = $product['product_id'];
				$qty          = $product['qty'];
				$variation_id = isset( $product['variation_id'] ) ? $product['variation_id'] : 0;
				$offer_key    = isset( $product['offer_key'] ) ? $product['offer_key'] : null;
				$price        = isset( $product['price'] ) ? (float) $product['price'] : 0;
				$cart_item_data = array();

				// Track the mapping between cart position and offer key
				if ( $offer_key ) {
					$key_mapping[ $cart_position ] = $offer_key;
					$cart_item_data['wfocu_offer_key'] = $offer_key;
				}
				if ( $price > 0 ) {
					$cart_item_data['wfocu_offer_price'] = $price;
				}

				// Handle variation products
				if ( $variation_id > 0 ) {
					// For variation products, get the parent product ID
					$variation_product = wc_get_product( $variation_id );
					if ( $variation_product && $variation_product->is_type( 'variation' ) ) {
						$parent_id = $variation_product->get_parent_id();

						WC()->cart->add_to_cart(
							$parent_id, // Parent product ID
							$qty,
							$variation_id, // Variation ID
							array(), // Cart item data
							$cart_item_data
						);
					} else {
						// Fallback to regular product addition
						WC()->cart->add_to_cart( $product_id, $qty, 0, array(), $cart_item_data );
					}
				} else {
					// Regular product
					WC()->cart->add_to_cart( $product_id, $qty, 0, array(), $cart_item_data );
				}

				++$cart_position;
			}

			// Apply offer prices before calculating totals
			if ( ! empty( WC()->cart->cart_contents ) ) {
				foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
					if ( isset( $cart_item['wfocu_offer_price'] ) && $cart_item['wfocu_offer_price'] > 0 && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
						$cart_item['data']->set_price( (float) $cart_item['wfocu_offer_price'] );
						WC()->cart->cart_contents[ $cart_item_key ]['data'] = $cart_item['data'];
					}
				}
			}

			// Calculate totals
			WC()->cart->calculate_totals();
			$cart_contents = WC()->cart->get_cart_contents();
			// Extract tax data from cart with proper key mapping
			$tax_data        = array();
			$tax_data_by_key = array();
			$total_tax       = 0;
			$cart_index      = 0;

			foreach ( $cart_contents as $cart_item_key => $cart_item ) {
				$line_tax   = $cart_item['line_tax'];
				$total_tax += $line_tax;

				$tax_item = array(
					'product_id'   => $cart_item['product_id'],
					'variation_id' => isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0,
					'line_total'   => $cart_item['line_total'],
					'line_tax'     => $line_tax,
				);

				$tax_data[] = $tax_item;

				// Map tax data to offer key if available
				$offer_key = null;
				if ( isset( $cart_item['wfocu_offer_key'] ) ) {
					$offer_key = $cart_item['wfocu_offer_key'];
				} elseif ( isset( $key_mapping[ $cart_index ] ) ) {
					$offer_key = $key_mapping[ $cart_index ];
				}

				if ( $offer_key ) {
					if ( ! isset( $tax_data_by_key[ $offer_key ] ) ) {
						$tax_data_by_key[ $offer_key ] = 0;
					}
					$tax_data_by_key[ $offer_key ] += $line_tax;
					$tax_item['offer_key']          = $offer_key;
				}

				++$cart_index;
			}

			// Clean up cart and restore original contents
			WC()->cart->empty_cart();
			if ( ! empty( $original_cart ) ) {
				WC()->cart->set_cart_contents( $original_cart );
			}

			return array(
				'total_tax'       => $total_tax,
				'tax_data'        => $tax_data,
				'tax_data_by_key' => $tax_data_by_key,
				'tax_exempt'      => false,
			);
		}
	}
}

// Initialize the class
if ( class_exists( 'WooFunnels_UpStroke_Dynamic_Tax' ) ) {
	WooFunnels_UpStroke_Dynamic_Tax::instance();
}
