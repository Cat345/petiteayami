<?php
if ( ! class_exists( 'WFOCU_Affiliate_WP_Compatibility' ) ) {
	class WFOCU_Affiliate_WP_Compatibility {

		public function __construct() {
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'wfocu_add_affiliate_on_order' ), 10, 5 );

		}

		public function is_enable() {
			if ( defined( 'AFFILIATEWP_VERSION' ) ) {
				return true;
			}

			return false;
		}

		public function wfocu_add_affiliate_on_order( $offer_id, $package, $order, $new_order, $transaction_id ) {
			if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
				define( 'WOOCOMMERCE_CHECKOUT', true );
			}

			if ( class_exists( 'Affiliate_WP_WooCommerce' ) ) {
				$obj = new Affiliate_WP_WooCommerce;
				if ( ! empty( $new_order ) && is_object( $new_order ) ) {
					$order_id = $new_order->get_id();
					$obj->add_pending_referral( $order_id );
					$obj->mark_referral_complete( $order_id );
				} else {
					$order_id     = $order->get_id();
					$existing     = affiliate_wp()->referrals->get_by( 'reference', $order_id, 'woocommerce' );
					$affiliate_id = $obj->get_affiliate_id( $order_id );
					if ( ! is_null( $existing ) ) {
						affiliate_wp()->referrals->update_referral( $existing->referral_id, array(
							'amount'       => $this->get_amount( wc_get_order( $order_id ), $affiliate_id, $obj ),
							'reference'    => $order_id,
							'description'  => $existing->description,
							'campaign'     => $existing->campaign,
							'affiliate_id' => $affiliate_id,
							'visit_id'     => $existing->visit_id,
							'order_total'  => $order->get_total(),
							'customer'     => $obj->get_customer( $order_id ),
							'context'      => 'woocommerce',
						) );
					}
				}
			}

		}

		/**
		 * @param WC_Order $order
		 * @param Integer $affiliate_id
		 *
		 * @return mixed|void
		 */
		public function get_amount( $order, $affiliate_id, $affiliate_obj ) {
			$cart_shipping = $order->get_total_shipping();

			if ( ! affiliate_wp()->settings->get( 'exclude_tax' ) ) {
				$cart_shipping += $order->get_shipping_tax();
			}

			if ( affwp_is_per_order_rate( $affiliate_id ) ) {

				$amount = $affiliate_obj->calculate_referral_amount();

			} else {

				$items = $order->get_items();

				// Calculate the referral amount based on product prices
				$amount = 0.00;

				foreach ( $items as $product ) {

					if ( get_post_meta( $product['product_id'], '_affwp_woocommerce_referrals_disabled', true ) ) {
						continue; // Referrals are disabled on this product
					}

					if ( ! empty( $product['variation_id'] ) && get_post_meta( $product['variation_id'], '_affwp_woocommerce_referrals_disabled', true ) ) {
						continue; // Referrals are disabled on this variation
					}

					// Get the categories associated with the download.
					$categories = get_the_terms( $product['product_id'], 'product_cat' );

					// Get the first category ID for the product.
					$category_id = $categories && ! is_wp_error( $categories ) ? $categories[0]->term_id : 0;

					// The order discount has to be divided across the items
					$product_total = $product['line_total'];
					$shipping      = 0;

					if ( $cart_shipping > 0 && ! affiliate_wp()->settings->get( 'exclude_shipping' ) ) {
						$shipping      = $cart_shipping / count( $items );
						$product_total += $shipping;
					}

					if ( ! affiliate_wp()->settings->get( 'exclude_tax' ) ) {
						$product_total += $product['line_tax'];
					}

					if ( $product_total <= 0 && 'flat' !== affwp_get_affiliate_rate_type( $affiliate_id ) ) {
						continue;
					}

					$product_id_for_rate = $product['product_id'];

					if ( ! empty( $product['variation_id'] ) && $affiliate_obj->get_product_rate( $product['variation_id'] ) ) {
						$product_id_for_rate = $product['variation_id'];
					}

					$amount += $affiliate_obj->calculate_referral_amount( $product_total, $order->get_id(), $product_id_for_rate, $affiliate_id, $category_id );
				}
			}

			/**
			 * Filters the referral amount immediately after WooCommerce calculations have completed.
			 *
			 * @param float $amount Calculated referral amount.
			 * @param int $order_id Order ID (reference)
			 * @param int $affiliate_id Affiliate ID.
			 * @param \Affiliate_WP_WooCommerce $this WooCommerce integration class instance.
			 *
			 * @since 2.4.4
			 *
			 */
			$amount = apply_filters( 'affwp_woocommerce_add_pending_referral_amount', $amount, $order->get_id(), $affiliate_id, $this );

			return $amount;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Affiliate_WP_Compatibility(), 'wfocu_affiliate_wp' );
}