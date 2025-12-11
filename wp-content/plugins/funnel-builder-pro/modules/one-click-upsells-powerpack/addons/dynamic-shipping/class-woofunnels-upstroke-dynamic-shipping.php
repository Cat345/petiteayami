<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Class WooFunnels_UpStroke_Dynamic_Shipping
 */
if ( ! class_exists( 'WooFunnels_UpStroke_Dynamic_Shipping' ) ) {

	class WooFunnels_UpStroke_Dynamic_Shipping {

		public static $instance;

		public function __construct() {
			$this->init_hooks();
		}


		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function init_hooks() {
			if ( false === class_exists( 'WFOCU_Common' ) ) {

				add_action( 'admin_enqueue_scripts', array( $this, 'maybe_render_assets' ) );
			}
			/**
			 * API receiving hook to catch the paypal response and process billing agreement creation
			 */
			add_action( 'woocommerce_api_wfocu_cs', array( $this, 'maybe_handle_call_cs' ) );
		}

		public function maybe_render_assets() {
			if ( true === WFOCU_Common::is_load_admin_assets( 'builder' ) ) {
				wp_enqueue_script( 'wfocu_dynamic_shipping_script', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'wfocu-admin-builder' ), WF_UPSTROKE_POWERPACK_VERSION );
			}
		}

		/**
		 * Fires remote request to get the shipping rates
		 *
		 * @param $products
		 * @param $location
		 * @param $existing_methods
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		public function calculate_dynamic_shipping( $products, $location, $existing_methods, $order ) {

			$get_free_shipping  = array();
			$shipping           = array();
			$get_shipping_items = $order->get_items( 'shipping' );
			$is_tax_exempt      = $order->get_meta( 'is_vat_exempt' );

			if ( ! empty( $products ) ) {
				$response = wp_remote_post( WC()->api_request_url( 'wfocu_cs' ), array(
					'body'      => array(
						'products'        => $products,
						'location'        => $location,
						'chosen_shipping' => $existing_methods,
						'currency'        => $order->get_currency(),
					),
					'sslverify' => false,
					'timeout'   => 20,
				) );
				if ( is_wp_error( $response ) ) {
					return __return_empty_string();
				} else {
					$matches       = array();
					$response_body = wp_remote_retrieve_body( $response );
					preg_match( '/{"packages.*}/', $response_body, $matches );
					$response_packages = json_decode( $matches[0], true );
					$order_behavior    = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
					$is_batching_on    = ( 'batching' === $order_behavior ) ? true : false;
					$override          = false;
					$shipping_prev     = array();

					if ( isset( $response_packages['packages'] ) && is_array( $response_packages['packages'] ) && is_array( $response ) && count( $response ) > 0 ) {
						foreach ( $response_packages['packages'] as $package ) {
							foreach ( $package as $method_id => $method_data ) {

								//exclude tax when the order has tax exemption
								if ( ! empty( $is_tax_exempt ) && 'yes' === $is_tax_exempt ) {
									if ( isset( $method_data['shipping_tax'] ) ) {
										$method_data['shipping_tax'] = 0;
									}
									if ( isset( $method_data['taxes'] ) ) {
										$method_data['taxes'] = array();
									}
								}

								/**
								 * Few times we need all the shipping options to render in the offer
								 * In this case we check if parent method is not provided then return All the methods
								 */
								if ( count( $existing_methods ) > 0 && in_array( $method_id, $existing_methods, true ) && $is_batching_on ) {
									$shipping   = array();
									$shipping[] = array(
										$method_id => $method_data,
									);
									$override   = false;
									foreach ( is_array( $get_shipping_items ) ? $get_shipping_items : array() as $shipping_item ) {
										if ( $method_id === $shipping_item->get_method_id() ) {
											$shipping_prev = array(
												'cost' => $shipping_item->get_total(),
												'tax'  => ( ! empty( $is_tax_exempt ) && 'yes' === $is_tax_exempt ) ? 0 : $shipping_item->get_total_tax(),
											);
										}
									}
									break;
								} else {
									$shipping[] = array(
										$method_id => $method_data,
									);
									$override   = true;
								}
							}
						}

						//Iteration for free shipping
						foreach ( $response_packages['packages'] as $package ) {
							foreach ( $package as $method_id => $method_data ) {
								if ( WFOCU_Core()->shipping->is_free_shipping( $method_data['method'] ) && count( $get_free_shipping ) === 0 ) {
									$get_free_shipping = array(
										$method_id => $method_data,
									);
								}
							}
						}
					}
				}
			}


			$shipping_methods = array(
				'free_shipping' => $get_free_shipping,
				'shipping'      => $shipping,
				'override'      => $override,
			);
			if ( count( $shipping_prev ) > 0 ) {
				$shipping_methods['shipping_prev'] = $shipping_prev;
			}

			return apply_filters( 'wfocu_calculate_dynamic_shipping_methods', $shipping_methods, $existing_methods, $products, $order, $location );

		}

		/**
		 * Hooked over `maybe_handle_call_cs`
		 * This methods creates an environment and sets up the cart to find the shipping cost dynamically
		 * 1. Adds products to the cart
		 * 2. Sets up users location so that correct taxes and shipping costs get calculated
		 * 3. Calculates shipping & shipping taxes
		 * Sends a json response of shipping packages
		 *
		 * @throws Exception
		 * @throws WC_Data_Exception
		 */
		public function maybe_handle_call_cs() {
			if ( ! isset( $_POST['location'] ) ) {
				wp_send_json( array(
					'packages' => [],
				) );
			}

			ob_start();
			list( $country, $state, $city, $postcode ) = wc_clean( $_POST['location'] );
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );

			$products = isset( $_POST['products'] ) ? wc_clean( $_POST['products'] ) : [];
			WC()->session->set( 'chosen_shipping_methods', isset( $_POST['chosen_shipping'] ) ? wc_clean( $_POST['chosen_shipping'] ) : '' );
			if ( empty( $products ) ) {
				wp_send_json( array(
					'packages' => [],
				) );
			}
			$post_currency = isset( $_POST['currency'] ) ? wc_clean( wp_unslash( $_POST['currency'] ) ) : '';
			if ( $post_currency ) {
				$this->maybe_set_wmc_currency( $post_currency );
			}

			foreach ( $products as $product ) {
				WC()->session->set( 'chosen_shipping_methods', wc_clean( $_POST['chosen_shipping'] ) );
				try {
					$offer_product = ( isset( $product['offer_product'] ) && ( wc_clean( $product['offer_product'] ) ) ) ? array(
						'offer_product' => 1,
						'offer_key'     => md5( $product['product_id'] . microtime() . wp_rand() )
					) : array( 'offer_key' => md5( $product['product_id'] . microtime() . wp_rand() ) );

					if ( isset( $product['variation_attributes'] ) && is_array( $product['variation_attributes'] ) && count( $product['variation_attributes'] ) > 0 ) {
						$offer_product = array_merge( $offer_product, $product['variation_attributes'] );
					}
					remove_all_actions( 'woocommerce_add_to_cart' );
					$cart_item_id = WC()->cart->add_to_cart( $product['product_id'], $product['qty'], isset( $product['variation_id'] ) ? $product['variation_id'] : 0, $offer_product, array( '_mykey' => wp_rand( 0, 10000 ) ) );

					WFOCU_Core()->log->log( "Product with product id: {$product['product_id']} and qty: {$product['qty']} added to cart with cart item id: $cart_item_id" );

				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( 'reason no add to cart: ' . print_r( $e, true ) );
				}

				if ( ! isset( $cart_item_id ) && is_null( $cart_item_id ) ) {
					continue;
				}

				if ( ! is_object( WC()->cart->get_cart_contents()[ $cart_item_id ]['data'] ) ) {
					continue;
				}

				$price = $product['price'];
				WFOCU_Core()->log->log( "Post currency: $post_currency, price: $price" );
				if ( $post_currency ) {
					$this->maybe_set_wmc_currency( $post_currency );
					$price = $this->maybe_get_exchaged_price( $price, $post_currency );
				}
				WFOCU_Core()->log->log( "UpStroke PowerPack: Setting up the price: $price" );

				/**
				 * It is important to set the price of the item just added to the cart,
				 * because if we do not set the correct price there might be different cart totals for the current scenarios as woocommerce will pick default pricing of a product
				 * so suppose if a product is of $10 in real and in upsell its of $8 then cart totals should have $8 not $10
				 * If order total mismatches with the total showing user to the cart then, there might be free shipping offered right away even when offer total doesn't reach threshold
				 */
				WC()->cart->get_cart_contents()[ $cart_item_id ]['data']->set_price( $price );
			}

			WC()->session->set( 'chosen_shipping_methods', wc_clean( $_POST['chosen_shipping'] ) );
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();

			$packages      = WC()->shipping->get_packages();
			$offer_package = array();

			foreach ( is_array( $packages ) ? $packages : array() as $key => $package ) {
				if ( count( $offer_package ) > 0 ) {
					break;
				}
				if ( isset( $package['contents'] ) ) {
					foreach ( $package['contents'] as $pval ) {
						if ( isset( $pval['variation'] ) && isset( $pval['variation']['offer_product'] ) && 1 === $pval['variation']['offer_product'] ) {
							$offer_package[] = $packages[ $key ];
							break;
						}
					}
				}
			}

			$my_packages = array();
			foreach ( count( $offer_package ) > 0 ? $offer_package : $packages as $i => $package ) {
				$my_packages[ $i ] = $this->wfocu_parse_shipping_packages( $package['rates'] );
			}
			if ( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) {
				WFOCU_Core()->log->log( 'UpStroke PowerPack: Final parsed offer shipping Packages: ' . print_r( $my_packages, true ) );
			}

			WC()->cart->empty_cart();
			WC()->session->destroy_session();

			ob_get_clean();
			wp_send_json( array(
				'packages' => $my_packages,
			) );
		}

		public function wfocu_parse_shipping_packages( $package ) {
			$custom_package = array();
			if ( $package && is_array( $package ) && count( $package ) > 0 ) {
				foreach ( $package as $key => $ship ) {
					$custom_package[ $key ] = array(
						'method'       => $ship->get_method_id(),
						'label'        => $ship->get_label(),
						'cost'         => $ship->get_cost(),
						'shipping_tax' => $ship->get_shipping_tax(),
						'taxes'        => $ship->get_taxes(),

					);
				}
			}

			return $custom_package;
		}

		/**
		 * @param $currency
		 */
		public function maybe_set_wmc_currency( $currency ) {
			if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
				$data = new WOOMULTI_CURRENCY_Data();
				if ( $currency ) {
					$data->set_current_currency( $currency, false );
				}
			}
		}

		/**
		 * @param $price
		 * @param $currency
		 *
		 * @return false|float|int|string
		 */
		public function maybe_get_exchaged_price( $price, $currency ) {
			if ( function_exists( 'wmc_revert_price' ) && ! empty( $price ) && ! empty( $currency ) ) {
				$price = wmc_revert_price( $price, $currency );
			}

			return $price;

		}

	}
}
if ( class_exists( 'WooFunnels_UpStroke_Dynamic_Shipping' ) ) {
	WooFunnels_UpStroke_Dynamic_Shipping::instance();
}
