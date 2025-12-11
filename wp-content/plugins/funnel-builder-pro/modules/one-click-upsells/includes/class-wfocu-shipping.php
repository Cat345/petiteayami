<?php
if ( ! class_exists( 'WFOCU_Shipping' ) ) {
	class WFOCU_Shipping {

		private static $ins = null;

		public $calculate_shipping = false;

		public function __construct() {
			add_filter( 'wfocu_offer_product_data', array( $this, 'maybe_calculate_shipping' ), 10, 4 );
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_get_shipping_id' ) );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @param stdClass $product_details
		 * @param stdClass $output
		 * @param stdClass $offer_data
		 * @param bool $is_front
		 *
		 * @return mixed
		 */
		public function maybe_calculate_shipping( $product_details, $output, $offer_data, $is_front ) {

			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;

			/**
			 * bail out if not a front request
			 */
			if ( false === $is_front || true === WFOCU_Core()->public->is_preview || false === $product_details->needs_shipping ) {
				return $product_details;
			}

			$products         = array();
			$get_order        = WFOCU_Core()->data->get_current_order();
			$existing_methods = array();
			$shipping         = array();

			/**
			 * In case of flat shipping, cost is returned as a normal shipping array so that our JS script and order processing script will get in sync with it.
			 */
			if ( 'flat' === $output->shipping_preferece ) {

				if ( 0 < intval( $product_details->shipping_cost_flat ) ) {

					$product_details->shipping = array(
						'free_shipping' => array(),
						'shipping'      => array(),
						'shipping_prev' => array(
							'cost' => 0,
							'tax'  => 0,
						),

					);

				} else {
					/**
					 * No need to apply any shipping
					 */
				}
			} else {
				$methods                           = $get_order->get_shipping_methods();
				$old_shipping_cost                 = ( $is_batching_on ) ? array(
					'cost' => $get_order->get_shipping_total(),
					'tax'  => $get_order->get_shipping_tax(),
				) : array(
					'cost' => 0,
					'tax'  => 0,
				);
				$get_shipping_methods_from_session = WFOCU_Core()->data->get( 'chosen_shipping_methods', array() );

				if ( $methods && is_array( $methods ) && count( $methods ) ) {
					foreach ( $methods as $method ) {

						$method_id = WFOCU_WC_Compatibility::get_method_id( $method ) . ':' . WFOCU_WC_Compatibility::get_instance_id( $method );

						/**
						 * If it's a batching request and primary order made as a free shipping order.
						 * Then return the free shipping with a cost zero as we do not need to charge any shipping cost for this case.
						 */
						if ( $is_batching_on && $this->is_free_shipping( WFOCU_WC_Compatibility::get_method_id( $method ) ) ) {

							$get_free_shipping = array(
								$method_id => array(
									'method'       => WFOCU_WC_Compatibility::get_method_id( $method ),
									'label'        => $method->get_name(),
									'cost'         => 0,
									'shipping_tax' => 0,
								),
							);

							$product_details->shipping = array(
								'free_shipping' => $get_free_shipping,
								'shipping'      => $shipping,
								'shipping_prev' => $old_shipping_cost,

							);

							return $product_details;

						}

						array_push( $existing_methods, $method_id );

						/**
						 * Since WooCommerce 2.1, WooCommerce allows to add multiple shipping methods in one single order
						 * The idea was to split a cart in some logical grouping, more info here https://www.xadapter.com/woocommerce-split-cart-items-order-ship-via-multiple-shipping-methods/
						 * For now we just need to break it after one iteration, so that we always know which shipping method we need to process & replace.
						 */

						break;
					}
				} else {
					$product_details->shipping = array(
						'free_shipping' => array(),
						'shipping'      => array(),
						'shipping_prev' => array(
							'cost' => 0,
							'tax'  => 0,
						),

					);

					return $product_details;
				}

				if ( ! empty( $get_shipping_methods_from_session ) ) {
					$existing_methods = $get_shipping_methods_from_session;
				}

				/**
				 * In this case prepare product array to fire a call to find the dynamic shipping
				 */
				if ( $is_batching_on ) {
					foreach ( $get_order->get_items() as $item ) {

						array_push( $products, array(
							'product_id' => $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id(),
							'qty'        => $item->get_quantity(),
						) );
					}
				}

				/**
				 * In case of variations, we need to work on the default variation only
				 */
				if ( isset( $product_details->variations_data ) ) {
					array_push( $products, array(
						'product_id' => $product_details->variations_data['default'],
						'qty'        => $product_details->quantity,
					) );
				} else {
					array_push( $products, array(
						'product_id' => $product_details->data->get_id(),
						'qty'        => $product_details->quantity,
					) );
				}

				if ( class_exists( 'WooFunnels_UpStroke_Dynamic_Shipping' ) && true === apply_filters( 'wfocu_show_prices_with_shipping', false, $product_details ) ) {

					$get_dynamic_shipping_module = WooFunnels_UpStroke_Dynamic_Shipping::instance();

					/**
					 * Preparing location for taxes and shipping calculations
					 */
					$country  = empty( $get_order->get_shipping_country() ) ? $get_order->get_billing_country() : $get_order->get_shipping_country();
					$state    = empty( $get_order->get_shipping_state() ) ? $get_order->get_billing_state() : $get_order->get_shipping_state();
					$city     = empty( $get_order->get_shipping_city() ) ? $get_order->get_billing_city() : $get_order->get_shipping_city();
					$postcode = empty( $get_order->get_shipping_postcode() ) ? $get_order->get_billing_postcode() : $get_order->get_shipping_postcode();

					$customer_id = WFOCU_WC_Compatibility::get_order_data( $get_order, '_customer_user' );

					if ( $customer_id > 0 ) {
						$customer = new WC_Customer( $customer_id );

						if ( empty( $country ) ) {
							$country = empty( $customer->get_shipping_country() ) ? $customer->get_billing_country() : $customer->get_shipping_country();
						}

						if ( empty( $state ) ) {
							$state = empty( $customer->get_shipping_state() ) ? $customer->get_billing_state() : $customer->get_shipping_state();
						}

						if ( empty( $city ) ) {
							$city = empty( $customer->get_shipping_city() ) ? $customer->get_billing_city() : $customer->get_shipping_city();
						}

						if ( empty( $postcode ) ) {
							$postcode = empty( $customer->get_shipping_postcode() ) ? $customer->get_billing_postcode() : $customer->get_shipping_postcode();
						}
					}

					$location = array( $country, $state, $city, $postcode );

					$get_shipping = $get_dynamic_shipping_module->calculate_dynamic_shipping( $products, $location, $existing_methods, $get_order );

					$product_details->shipping = wp_parse_args( $get_shipping, array(
						'free_shipping' => array(),
						'shipping'      => array(),
						'shipping_prev' => $old_shipping_cost,

					) );
				} else {
					$product_details->shipping = array(
						'free_shipping' => array(),
						'shipping'      => array(),
						'shipping_prev' => $old_shipping_cost,

					);
				}
			}

			return $product_details;

		}

		public function get_flat_shipping_rates( $price ) {

			if ( false === wc_tax_enabled() ) {
				return 0;
			}
			$calculate_tax_for = WC_Tax::get_tax_location();
			if ( empty( $calculate_tax_for ) ) {
				return 0;
			}
			$calculate_tax_for = array(
				'country'  => $calculate_tax_for[0],
				'state'    => $calculate_tax_for[1],
				'postcode' => $calculate_tax_for[2],
				'city'     => $calculate_tax_for[3],
			);
			$tax_rates         = WC_Tax::find_shipping_rates( $calculate_tax_for );

			$taxes = WC_Tax::calc_tax( $price, $tax_rates, false );

			return is_array( $taxes ) ? array_sum( $taxes ) : 0;
		}

		public function is_free_shipping( $method ) {
			$re  = '/(free_shipping)/';
			$str = $method;
			preg_match_all( $re, $str, $matches, PREG_SET_ORDER, 0 );

			if ( is_array( $matches ) && count( $matches ) > 0 && isset( $matches[0][0] ) && 'free_shipping' === $matches[0][0] ) {

				return true;
			}

			return false;
		}

		public function maybe_get_shipping_id() {
			if ( WC()->session instanceof WC_Session && did_action( 'wfocu_session_loaded' ) ) {
				WFOCU_Core()->data->set( 'chosen_shipping_methods', WC()->session->get( 'chosen_shipping_methods' ) );
				WFOCU_Core()->data->save( 'funnel' );
			}
		}

		public function get_shipping_cost_from_package( $package, $incl_tax = false ) { //phpcs:ignore
			if ( is_array( $package['shipping'] ) && ! empty( $package['shipping'] ) ) {

				if ( isset( $package['shipping']['diff'] ) && isset( $package['shipping']['diff']['cost'] ) && ! empty( $package['shipping']['diff']['cost'] ) ) {

					return $package['shipping']['diff'];

				}
			}

			return 0;
		}


	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'shipping', 'WFOCU_Shipping' );
	}
}