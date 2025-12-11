<?php
if ( ! class_exists( 'WFOCU_Offer_Process' ) ) {
	class WFOCU_Offer_Process {

		private static $ins = null;
		public $total = 0;
		public $items = 0;
		public $items_data = 0;
		public $shipping = 0;
		public $taxes_total = 0;
		public $posted_data;
		public $posted_data_raw;

		public function __construct() {
			add_action( 'wfocu_before_building_offer_product_data', array( $this, 'set_custom_tax_address' ) );
			add_filter( 'wfocu_offer_product_price', [ $this, 'handle_tax_exempt_is_prices_include_tax' ], 10, 5 );
			add_action( 'wfocu_session_loaded', [ $this, 'maybe_remove_all_taxes_from_offers_when_exempt' ] );

		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		public function execute( $offer_meta ) {

			$upsell_package = $this->prepare_upsell_package( $offer_meta );

			$this->set_upsell_package( $upsell_package );
		}

		public function prepare_upsell_package( $offer_meta ) {

			$complete_package = array();
			$offer_meta       = apply_filters( 'wfocu_prepare_upsell_package_before', $offer_meta );

			$offer_products          = $offer_meta->products;
			$offer_products_settings = $offer_meta->fields;
			$chosen_hashes           = array();

			if ( is_array( $this->posted_data ) && count( $this->posted_data ) > 0 ) {
				$chosen_hashes = wp_list_pluck( $this->posted_data, 'hash' );
			}
			$i          = 0;
			$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );
			foreach ( $chosen_hashes as $key => $hash ) {
				$complete_package[ $i ]           = array();
				$complete_package[ $i ]['hash']   = $hash;
				$complete_package[ $i ]['_offer'] = $offer_data->products->{$hash};
				if ( isset( $this->posted_data[ $key ]['data'] ) ) {

					$complete_package[ $i ]['product']  = ( false !== $this->posted_data[ $key ]['data']['variation'] ) ? $this->posted_data[ $key ]['data']['variation'] : $offer_products->{$hash};
					$complete_package[ $i ]['qty']      = ( isset( $offer_products_settings->{$hash} ) ) ? absint( $this->posted_data[ $key ]['_qty'] ) * $offer_products_settings->{$hash}->quantity : 0;
					$complete_package[ $i ]['price']    = $this->posted_data[ $key ]['price'];
					$complete_package[ $i ]['_product'] = wc_get_product( $complete_package[ $i ]['product'] );

					$complete_package[ $i ]['meta'] = $this->posted_data[ $key ]['data']['attributes'];

				} else {

					$complete_package[ $i ]['product']  = ( isset( $offer_products->{$hash} ) ) ? (int) $offer_products->{$hash} : '0';
					$complete_package[ $i ]['qty']      = ( isset( $offer_products_settings->{$hash} ) ) ? absint( $this->posted_data[ $key ]['_qty'] ) * $offer_products_settings->{$hash}->quantity : 0;
					$complete_package[ $i ]['price']    = $this->posted_data[ $key ]['price'];
					$complete_package[ $i ]['_product'] = wc_get_product( $complete_package[ $i ]['product'] );
					$complete_package[ $i ]['meta']     = array();
				}
				$i ++;
			}

			return $complete_package;
		}

		public function set_upsell_package( $data ) {

			$package = array( 'products' => array() );
			if ( is_array( $data ) && count( $data ) > 0 ) {
				foreach ( $data as $key => $offer ) {
					$package['products'][ $key ] = array(
						'id'  => $offer['product'],
						'qty' => $offer['qty'],

						'price'       => $offer['price'],
						'args'        => array(
							'total'     => ( $offer['price'] ),
							'variation' => $offer['meta'],
							'subtotal'  => $offer['price'],
						),
						'hash'        => $offer['hash'],
						'data'        => $offer['_product'],
						'_offer_data' => $offer['_offer'],
					);

				}
			}

			$package['total']    = $this->total;
			$package['shipping'] = $this->shipping;
			$package['taxes']    = $this->taxes_total;


			/**Checking for the client error in the posted data to tell the gateways to process accordingly **/
			if ( isset( $_POST['_client_error'] ) && ! empty( wc_clean( $_POST['_client_error'] ) ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				$error_posted = wc_clean( $_POST['_client_error'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( is_string( $error_posted ) ) {
					$package['_client_error'] = $error_posted;
				} elseif ( is_array( $error_posted ) && isset( $error_posted['message'] ) ) {
					$package['_client_error'] = $error_posted['message'];
				} else {
					$package['_client_error'] = __( 'Unable to find error', 'woofunnels-upstroke-one-click-upsell' );
				}

			}

			$package = apply_filters( 'wfocu_upsell_package', $package );
			WFOCU_Core()->data->set( '_upsell_package', $package );

			return $package;
		}


		public function package_needs_shipping() {
			if ( 'no' === get_option( 'woocommerce_calc_shipping' ) ) {
				return false;
			}
			$package = WFOCU_Core()->data->get( '_upsell_package' );
			foreach ( $package['products'] as $product ) {
				/**
				 * @var WC_Product $product_object
				 */
				$product_object = $product['data'];
				if ( $product_object->needs_shipping() ) {
					return true;
				}
			}

			return false;
		}

		public function parse_posted_data( $posted_data = '' ) {

			if ( empty( $posted_data ) ) {
				$posted_data = $_POST; //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			$this->posted_data_raw = $posted_data;
			$posted_data_key       = [];
			if ( false === in_array( filter_input( INPUT_POST, 'action', FILTER_UNSAFE_RAW ), apply_filters( 'wfocu_allow_ajax_actions_for_charge_setup', array( //phpcs:ignore
					WFOCU_AJAX_Controller::CHARGE_ACTION,
					WFOCU_AJAX_Controller::SHIPPING_CALCULATION_ACTION,
				) ), true ) ) {

				return $posted_data;
			}

			if ( isset( $this->posted_data_raw['items'] ) && is_array( $this->posted_data_raw['items'] ) && count( $this->posted_data_raw['items'] ) > 0 ) {
				$this->total       = $this->posted_data_raw['totals']['total'];
				$this->taxes_total = $this->posted_data_raw['totals']['taxTotal'];
				if ( isset( $this->posted_data_raw['shipping'] ) ) {
					$this->shipping = $this->posted_data_raw['shipping'];
				}

				foreach ( $this->posted_data_raw['items'] as $key => $hash ) {

					$posted_data_key[ $key ] = array(
						'hash'  => $hash,
						'price' => $this->posted_data_raw['totals']['itemsPrices'][ $key ],
						'_qty'  => $this->posted_data_raw['itemQty'][ $key ],
					);

					if ( isset( $this->posted_data_raw['itemsData'] ) && isset( $this->posted_data_raw['itemsData'][ $key ] ) ) {
						$variation_attributes = array();
						$get_attribute_values = WFOCU_Core()->data->get( 'attribute_variation_stock_' . $hash, array(), 'variations' );

						foreach ( $this->posted_data_raw['itemsData'][ $key ] as $value ) {
							$get_key_pair = explode( '=', $value );

							if ( is_array( $get_key_pair ) && 2 === count( $get_key_pair ) ) {
								$variation_attributes[ $get_key_pair[0] ] = WFOCU_Common::handle_single_quote_variation_reverse( urldecode_deep( $get_key_pair[1] ) );

							}
						}
						$exclude = array( '_wfocu_variation' );

						$filtered = array_filter( $variation_attributes, function ( $key ) use ( $exclude ) {
							return ! in_array( $key, $exclude, true );
						}, ARRAY_FILTER_USE_KEY );

						if ( ! empty( $get_attribute_values ) ) {
							$result = [];
							array_walk( $filtered, function ( &$value, $key ) use ( &$result, $get_attribute_values ) {

								if ( isset( $get_attribute_values[ $key ] ) ) {
									$result[ $get_attribute_values[ $key ] ] = $value;
								} else {
									$result[ $key ] = $value;
								}

							} );
						} else {
							$result = $filtered;
						}

						$posted_data_key[ $key ]['data'] = array(
							'variation'  => ( isset( $variation_attributes['_wfocu_variation'] ) ? $variation_attributes['_wfocu_variation'] : false ),
							'attributes' => $result,
						);
					}
				}
			}
			$this->posted_data = $posted_data_key;

			return $posted_data_key;

		}


		public function _handle_upsell_charge( $response ) {
			try {

				$data = array();
				if ( true === $response ) {

					WFOCU_Core()->log->log( 'Payment Call is successful' );
					$data['message'] = WFOCU_Core()->data->get( '_transaction_message' );
					WFOCU_Core()->public->handle_success_upsell();

					$get_offer = WFOCU_Core()->offers->get_the_next_offer();

					$data['redirect_url'] = WFOCU_Core()->public->get_the_upsell_url( $get_offer );
					WFOCU_Core()->data->set( 'current_offer', $get_offer );
					WFOCU_Core()->data->save();

				} else {

					WFOCU_Core()->log->log( 'Payment Call is failed' );
					$data['message'] = WFOCU_Core()->data->get( '_transaction_message' );
					WFOCU_Core()->public->handle_failed_upsell();
					$data['redirect_url'] = WFOCU_Core()->public->get_clean_order_received_url();
					WFOCU_Core()->data->set( 'current_offer', 0 );
					WFOCU_Core()->data->save();
					//show success
					//terminate funnel
				}
			} catch ( Exception $ex ) {
				WFOCU_Core()->log->log( 'Payment Call is failed, here is the error: ' . $ex->getMessage() );
				$data['message'] = WFOCU_Core()->data->get( '_transaction_message' );
				if ( did_action( 'wfocu_offer_accepted_and_processed' ) === 0 ) {
					WFOCU_Core()->public->handle_failed_upsell();
				}
				$data['redirect_url'] = WFOCU_Core()->public->get_clean_order_received_url();
				WFOCU_Core()->data->set( 'current_offer', 0 );
				WFOCU_Core()->data->save();
			}

			return $data;

		}

		/**
		 * Get the product price without tax if tax is exempt and the price includes tax
		 * @param $price
		 * @param $incl_tax
		 * @param $original_price
		 * @param $offer_settings
		 * @param $product
		 *
		 * @return float|mixed|string
		 */
		public function handle_tax_exempt_is_prices_include_tax( $price, $incl_tax, $original_price, $offer_settings, $product ) {
			if ( $this->maybe_remove_all_taxes_from_offers_when_exempt() && 'yes' === get_option( 'woocommerce_prices_include_tax', '' ) ) {

				if ( ( true === $incl_tax ) ) {
					/**
					 * Turn on setting for set price to not include tax
					 */
					add_filter( 'wc_tax_enabled', '__return_true' );
					$price = wc_get_price_excluding_tax( $product, array( 'price' => $price ) );
				}
			}

			return $price;
		}

		public function maybe_remove_all_taxes_from_offers_when_exempt() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$is_tax_exempt = $order->get_meta( 'is_vat_exempt' );

			if ( 'yes' !== $is_tax_exempt ) {
				return false;
			}

			add_filter( 'wc_tax_enabled', '__return_false' );

			return true;
		}


	}


	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'process_offer', 'WFOCU_Offer_Process' );
	}
}