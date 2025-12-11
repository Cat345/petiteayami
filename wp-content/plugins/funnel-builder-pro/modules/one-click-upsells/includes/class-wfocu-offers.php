<?php
if ( ! class_exists( 'WFOCU_Offers' ) ) {
	/**
	 * Create,show,delete,edit and manages the process related to offers in the plugin.
	 * Class WFOCU_Offers
	 */
	class WFOCU_Offers {

		const INVALIDATION_PRODUCT_IN_ORDER = 1;
		const INVALIDATION_NOT_PURCHASABLE = 2;
		const INVALIDATION_PAST_PURCHASED = 3;
		const INVALIDATION_NOT_SUPPORT_SUBSCRIPTION = 4;
		const INVALIDATION_IS_SOLD_IND = 5;
		private static $ins = null;
		public $is_custom_page = false;

		public function __construct() {
			add_filter( 'wfocu_offer_product_data', array( $this, 'offer_product_setup_stock_data' ), 9, 4 );
			add_filter( 'wfocu_view_body_classes', array( $this, 'append_offer_unique_class' ), 10, 1 );
			add_filter( 'wfocu_offer_product_price', array( $this, 'update_offer_price_on_cancel_primary' ), 10, 4 );
			add_filter( 'wfocu_upsell_package', array( $this, 'update_offer_modify_price' ), 10, 1 );
			add_action( 'wfocu_before_handle_success_upsell_package', array( $this, 'update_package_on_cancel_primary' ), 10 );
			add_action( 'wfocu_before_handle_failure_upsell_package', array( $this, 'update_package_on_cancel_primary' ), 10 );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function validate( $post ) {

			$funnel_check = WFOCU_Core()->funnels->validate();

			if ( false === $funnel_check ) {
				WFOCU_Core()->log->log( 'Validation Failed: Funnel Check Failed' );

				return false;
			}

			$get_current_offer = WFOCU_Core()->data->get_current_offer();

			if ( false === $get_current_offer ) {
				WFOCU_Core()->log->log( 'Validation Failed: Unable to find the current offer' );

				return false;
			}

			/**
			 * if we do not have any offer to show then fail the validation.
			 */
			if ( false === $post ) {
				WFOCU_Core()->log->log( 'Validation Failed: Unable to find the current offer' );

				return false;
			}

			/**
			 * IF current offer is not the one we are expecting in the funnel
			 */
			if ( $get_current_offer !== $post ) {
				WFOCU_Core()->log->log( 'Validation Failed: Current offer set in the session doesn\'t match with the one opening now.' );

				return false;
			}

			return true;
		}

		public function get_offers( $funnel_id ) {
			if ( $funnel_id ) {
				return get_post_meta( $funnel_id, '_funnel_upsell_downsell', true );
			}

			return false;
		}

		/**
		 * Return the first offer in the list
		 * @return int|null|string
		 */
		public function get_the_first_offer() {
			$get_offers = WFOCU_Core()->data->get( 'funnel' );

			return $this->get_the_offer( 'yes', null, $get_offers );

		}

		public function get_the_offer( $type, $offer, $get_offers ) {

			if ( null === $offer ) {
				if ( is_array( $get_offers ) ) {
					reset( $get_offers );
					$offer = key( $get_offers );
				}

				return apply_filters( 'wfocu_get_offer_id_filter', absint( $offer ), $type, $get_offers );
			}

			if ( $type === 'clean' ) {
				$get_funnel              = WFOCu_Core()->data->get_funnel_id();
				$get_funnel_steps        = WFOCU_Core()->funnels->get_funnel_steps( $get_funnel );
				$get_current_offer_index = WFOCU_Core()->funnels->get_current_index( $get_funnel_steps, $offer );
				if ( false !== $get_current_offer_index ) {
					return apply_filters( 'wfocu_get_offer_id_filter', absint( WFOCU_Core()->funnels->get_next_upsell( $get_funnel_steps, $get_current_offer_index ) ), $type, $get_offers );
				}

			}

			$get_offer_type_key = $this->get_meta_key_for_offer_type( $type );
			if ( $get_offers && is_array( $get_offers ) && count( $get_offers ) > 0 && isset( $get_offers[ $offer ] ) && isset( $get_offers[ $offer ][ $get_offer_type_key ] ) ) {
				return apply_filters( 'wfocu_get_offer_id_filter', absint( $get_offers[ $offer ][ $get_offer_type_key ] ), $type, $get_offers );
			}

			return 0;
		}

		public function get_meta_key_for_offer_type( $type = 'yes' ) {
			$offer_type = array(
				'y' => 'yes',
				'n' => 'no',
			);

			return array_search( $type, $offer_type, true );

		}

		/**
		 * get the next offer from the current offer
		 *
		 * @param string $type yes|no|clean the type of operation to get the next offer in the ladder
		 *
		 * @return int
		 */
		public function get_the_next_offer( $type = 'yes', $offer = null ) {
			$get_offers = WFOCU_Core()->data->get( 'funnel' );

			if ( ! empty( $offer ) ) {
				$get_current_offer = $offer;
			} else {
				$get_current_offer = WFOCU_Core()->data->get( 'current_offer' );
			}
			$get_the_previous_offer_response = WFOCU_Core()->data->get( '_offer_result', null );

			if ( false === is_null( $get_the_previous_offer_response ) ) {
				if ( ! empty( $offer ) ) {
					$get_offer_data = WFOCU_Core()->offers->get_offer( $offer );
				} else {
					$get_offer_data = WFOCU_Core()->data->get( '_current_offer', '' );
				}

				if ( true === $get_the_previous_offer_response ) {
					if ( '' !== $get_offer_data && ! empty( $get_offer_data->settings->terminate_if_accepted ) && true === $get_offer_data->settings->terminate_if_accepted ) {
						return 0;
					}
				} else {
					if ( '' !== $get_offer_data && ! empty( $get_offer_data->settings->terminate_if_declined ) && true === $get_offer_data->settings->terminate_if_declined ) {
						return 0;
					}
				}
			}

			$get_current_offer = apply_filters( 'wfocu_get_current_offer_id', absint( $get_current_offer ), $type, $get_offers );

			return $this->get_the_offer( $type, $get_current_offer, $get_offers );
		}

		public function get_offer_index( $offer_id, $funnel_id = 0 ) {
			/** return in case no funnel id: customizer preview case */
			if ( 0 === $funnel_id ) {
				return - 1;
			}
			$get_funnel_steps = WFOCU_Core()->funnels->get_funnel_steps( $funnel_id );
			$index            = - 1;
			if ( is_array( $get_funnel_steps ) && count( $get_funnel_steps ) > 0 ) {
				$key = 0;
				foreach ( $get_funnel_steps as $step ) {

					if ( $step['id'] === $offer_id || absint( $step['id'] ) === $offer_id ) {
						$index = $key;
						break;
					}

					$key ++;
				}
			}

			return $index;

		}


		public function get_offer_id_by_index( $index, $funnel_id = 0 ) {
			/** return in case no funnel id: customizer preview case */
			if ( 0 === $funnel_id ) {
				return - 1;
			}
			$get_funnel_steps = WFOCU_Core()->funnels->get_funnel_steps( $funnel_id );
			$id               = 0;
			if ( is_array( $get_funnel_steps ) && count( $get_funnel_steps ) > 0 ) {
				$key = 0;
				foreach ( $get_funnel_steps as $step ) {

					if ( $key === $index ) {
						$id = $step['id'];
						break;
					}

					$key ++;
				}
			}

			return $id;

		}

		public function get_offer_attributes( $offer_id, $get = 'type' ) {
			/** return in case no funnel id: customizer preview case */
			if ( false === WFOCU_Core()->data->get_funnel_id() ) {
				return;
			}
			$get_funnel_steps = WFOCU_Core()->funnels->get_funnel_steps( WFOCU_Core()->data->get_funnel_id() );

			$upsells   = 1;
			$downsells = 1;
			if ( is_array( $get_funnel_steps ) && count( $get_funnel_steps ) > 0 ) {
				foreach ( $get_funnel_steps as $step ) {

					if ( $step['id'] === $offer_id || absint( $step['id'] ) === $offer_id ) {
						$type = $step['type'];
						switch ( $get ) {
							case 'type':
								return $type;
								break;
							case 'index':
								return ( 'upsell' === $type ) ? $upsells : $downsells;
							case 'state':
								return $step['state'];
						}

						break;
					}

					if ( 'upsell' === $step['type'] ) {
						$upsells ++;
					} else {
						$downsells ++;
					}
				}
			}

			return null;

		}

		public function get_offer_meta( $offer_id ) {
			return apply_filters( 'wfocu_get_offer_meta', get_post_meta( $offer_id, '_wfocu_setting', true ), $offer_id );
		}

		public function prepare_shipping_package( $offer_meta, $posted_data = array() ) {

			$complete_package = array();

			$offer_products          = $offer_meta->products;
			$offer_products_settings = $offer_meta->fields;
			$chosen_hashes           = array();

			if ( is_array( $posted_data ) && count( $posted_data ) > 0 ) {
				$chosen_hashes = wp_list_pluck( $posted_data, 'hash' );
			}
			$i = 0;
			foreach ( $chosen_hashes as $key => $hash ) {

				if ( isset( $posted_data[ $key ]['data'] ) ) {
					$complete_package[ $i ]                   = array();
					$complete_package[ $i ]['product']        = ( false !== $posted_data[ $key ]['data']['variation'] ) ? $posted_data[ $key ]['data']['variation'] : $offer_products->{$hash};
					$complete_package[ $i ]['qty']            = ( isset( $offer_products_settings->{$hash} ) ) ? $offer_products_settings->{$hash}->quantity : 0;
					$complete_package[ $i ]['price']          = $this->get_product_price( $complete_package[ $i ]['product'], $offer_products_settings->{$hash}, false, $offer_products_settings );
					$complete_package[ $i ]['price_with_tax'] = $this->get_product_price( $complete_package[ $i ]['product'], $offer_products_settings->{$hash}, true, $offer_products_settings );
					$complete_package[ $i ]['_product']       = wc_get_product( $complete_package[ $i ]['product'] );
					$complete_package[ $i ]['meta']           = $posted_data[ $key ]['data']['attributes'];

				} else {
					$complete_package[ $i ]                   = array();
					$complete_package[ $i ]['product']        = ( isset( $offer_products->{$hash} ) ) ? (int) $offer_products->{$hash} : '37';
					$complete_package[ $i ]['qty']            = ( isset( $offer_products_settings->{$hash} ) ) ? $offer_products_settings->{$hash}->quantity : 0;
					$complete_package[ $i ]['price']          = $this->get_product_price( $complete_package[ $i ]['product'], $offer_products_settings->{$hash}, false, $offer_products_settings );
					$complete_package[ $i ]['price_with_tax'] = $this->get_product_price( $complete_package[ $i ]['product'], $offer_products_settings->{$hash}, true, $offer_products_settings );
					$complete_package[ $i ]['_product']       = wc_get_product( $complete_package[ $i ]['product'] );
					$complete_package[ $i ]['meta']           = array();
				}
				$i ++;
			}

			return $complete_package;
		}

		/**
		 * @param $product
		 * @param $options
		 * @param bool $incl_tax
		 * @param $offer_settings
		 *
		 * @return float
		 */
		public function get_product_price( $product, $options, $incl_tax = false, $offer_settings = array(), $original_price = false ) {

			if ( ! $product instanceof WC_Product ) {
				$product = wc_get_product( $product );
			}

			$regular_price          = ! empty( $product->get_regular_price() ) ? $product->get_regular_price() * $options->quantity : 0;
			$get_product_raw_price  = $price = apply_filters( 'wfocu_product_raw_price', $regular_price, $product, $options );
			$do_not_apply_discounts = apply_filters( 'wfocu_do_not_apply_discounts', false, $product, $options, $offer_settings );
			if ( is_object( $options ) && isset( $options->discount_type ) && false === $do_not_apply_discounts ) {
				if ( in_array( $options->discount_type, [ 'percentage_on_sale', 'fixed_on_sale' ], true ) ) {
					$sale_price            = floatval( $product->get_price() ) * $options->quantity;
					$get_product_raw_price = apply_filters( 'wfocu_product_raw_sale_price', $sale_price, $product, $options, $get_product_raw_price );
				}

				$price = WFOCU_Common::apply_discount( $get_product_raw_price, $options, $product );

			}

			/**
			 * Rounding at this place will ensure that reverse tax calculation will never over count the price,
			 * Case of 7.99 becoming 8.00 after the order
			 */
			$price = round( $price, wc_get_price_decimals() );

			$price = ( true === $incl_tax ) ? wc_get_price_including_tax( $product, array( 'price' => $price ) ) : wc_get_price_excluding_tax( $product, array( 'price' => $price ) );

			return apply_filters( 'wfocu_offer_product_price', $price, $incl_tax, $original_price, $offer_settings, $product );

		}

		public function parse_posted_data() {
			$posted_data = array();
			$data        = $_POST;   // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( false === in_array( filter_input( INPUT_POST, 'action', FILTER_UNSAFE_RAW ), apply_filters( 'wfocu_allow_ajax_actions_for_charge_setup', array(
					WFOCU_AJAX_Controller::CHARGE_ACTION,
					WFOCU_AJAX_Controller::SHIPPING_CALCULATION_ACTION,
				) ), true ) ) {

				return $posted_data;
			}

			if ( isset( $data['items'] ) && is_array( $data['items'] ) && count( $data['items'] ) > 0 ) {

				foreach ( $data['items'] as $key => $hash ) {
					$posted_data[ $key ] = array(
						'hash' => $hash,
					);

					if ( isset( $data['itemsData'] ) && isset( $data['itemsData'][ $key ] ) ) {

						$get_attribute_values = WFOCU_Core()->data->get( 'attribute_variation_stock_' . $hash, array(), 'variations' );
						$variation_attributes = array();
						wp_parse_str( implode( '&', $data['itemsData'][ $key ] ), $variation_attributes );
						$exclude = array( '_wfocu_variation' );

						$filtered = array_filter( $variation_attributes, function ( $key ) use ( $exclude ) {
							return ! in_array( $key, $exclude, true );
						}, ARRAY_FILTER_USE_KEY );

						$result = array();

						if ( ! empty( $get_attribute_values ) ) {
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

						$posted_data[ $key ]['data'] = array(
							'variation'  => ( isset( $variation_attributes['_wfocu_variation'] ) ? $variation_attributes['_wfocu_variation'] : false ),
							'attributes' => $result,
						);
					}
				}
			}

			return $posted_data;

		}

		public function get_offer_from_post( $post ) {

			if ( ! $post instanceof WP_Post ) {

				$post = get_post( $post );
			}

			if ( ! $post instanceof WP_Post ) {

				return false;
			}

			//if single offer page
			if ( WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {
				return $post->ID;
			}

			$get_offer  = WFOCU_Core()->data->get_current_offer();
			$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );

			if ( $get_offer && is_object( $offer_data ) && 'custom-page' === $offer_data->template ) {
				$get_custom_page = get_post_meta( $get_offer, '_wfocu_custom_page', true );

				if ( absint( $get_custom_page ) === absint( $post->ID ) ) {
					$this->is_custom_page = true;

					return $get_offer;
				}
			}

			return false;
		}

		/**
		 * Here we find out whether to show tax info during side cart totals.
		 * The decision for it came from the settings for the woocommerce.
		 * So if woocommerce says "show cart items including prices" that means no separate row needs to be make on cart table
		 * @return bool
		 */
		public function show_tax_info_in_confirmation() {

			return wc_tax_enabled() && ( ! WFOCU_WC_Compatibility::display_prices_including_tax() );
		}

		public function offer_product_setup_stock_data( $product_details, $output, $offer_data, $is_front ) {
			if ( true === $is_front ) {

				if ( in_array( $product_details->data->get_type(), WFOCU_Common::get_variable_league_product_types(), true ) && true === $product_details->data->is_purchasable() ) {
					$product_details->is_purchasable = true;
				} else {
					$product_details->is_purchasable = $product_details->data->is_purchasable();
				}
				if ( in_array( $product_details->data->get_type(), $this->product_compatible_for_stock_check(), true ) ) {


					$product_details->is_in_stock        = $product_details->data->is_in_stock();
					$product_details->max_qty            = $this->get_max_purchase_quantity( $product_details->data );
					$product_details->backorders_allowed = $product_details->data->backorders_allowed();

				}
			}

			return $product_details;
		}

		/**
		 * @param WC_Product $product_object
		 * @param $is_variation
		 *
		 * @return array|int|mixed|string|null
		 */
		public function get_max_purchase_quantity( $product_object, $is_variation = false ) {

			/**
			 * If wc returns infinite qty for the product then return as it is
			 */
			if ( - 1 === $product_object->get_max_purchase_quantity() ) {
				return - 1;
			}
			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
			$qty_in_order   = 0;
			if ( $is_batching_on ) {
				$order = WFOCU_Core()->data->get_parent_order();


				if ( $order instanceof WC_Order ) {
					$get_items = $order->get_items();
					if ( is_array( $get_items ) && count( $get_items ) > 0 ) {
						foreach ( $get_items as $item ) {
							if ( $item['product_id'] === $product_object->get_id() || ( $is_variation === true && $item['variation_id'] === $product_object->get_id() ) ) {
								$qty_in_order += $item['quantity'];
							}
						}
					}
				}

			}

			return 0 === $product_object->get_max_purchase_quantity() ? 0 : $product_object->get_max_purchase_quantity() - $qty_in_order;

		}

		public function product_compatible_for_stock_check() {
			return apply_filters( 'wfocu_products_compatible_for_stock_check', array( 'simple', 'variation', 'subscription', 'subscription_variation' ) );
		}

		/**
		 * This method is to validate the product in the current offer against purchasable and stock standards
		 * Based on these results we hide/show Or redirect the user
		 *
		 * @param $offer_build
		 *
		 * @return bool
		 */
		public function validate_product_offers( $offer_build ) {

			if ( new stdClass() === $offer_build->products ) {
				//no products
				return false;
			}
			$get_order                = WFOCU_Core()->data->get_parent_order();
			$treat_variable_as_simple = WFOCU_Core()->data->get_option( 'treat_variable_as_simple' );
			if ( true === $offer_build->settings->skip_exist ) {

				$items            = $get_order->get_items();
				$offer_items_sold = array();
				$offer_items      = array();

				foreach ( $offer_build->products as $product_data ) {
					$offer_product_id = $product_data->data->get_id();
					$offer_product    = wc_get_product( $offer_product_id );

					$offer_items[ $offer_product_id ] = 1;

					foreach ( $items as $item ) {
						$product = WFOCU_WC_Compatibility::get_product_from_item( $get_order, $item );

						/**
						 * By Default, If global settings are checked to treat variable product as simple then We treat order variaion as variable and matches with the variable product in the offer.
						 * Rest all the cases handled in the else where we check direct product ID match.
						 */
						if ( true === $treat_variable_as_simple && $offer_product->is_type( 'variable' ) && $product->is_type( 'variation' ) ) {
							$order_product_id = $product->get_parent_id();
							if ( $offer_product_id === $order_product_id ) {
								$offer_items_sold[ $offer_product_id ] = 1;
							}
						} elseif ( $offer_product_id === $product->get_id() ) {
							$offer_items_sold[ $offer_product_id ] = 1;
						}
					}
				}
				/**
				 * Items are already purchased. as count of sold items in the cart matches to the offer items sold in the prev order
				 */
				if ( count( $offer_items_sold ) > 0 ) {
					WFOCU_Core()->template_loader->invalidation_reason = self::INVALIDATION_PRODUCT_IN_ORDER;
					WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Items are already purchased in parent order' );
					WFOCU_Core()->session_db->set_skip_id( 7 );

					return false;
				}
			}

			if ( true === $offer_build->settings->skip_purchased ) {

				if ( ! function_exists( 'bwf_get_contact' ) ) {
					return true;
				}
				$bwf_contact = bwf_get_contact( $get_order->get_customer_id(), $get_order->get_billing_email() );

				if ( ! $bwf_contact instanceof WooFunnels_Contact ) {
					return true;
				}
				$bwf_contact->set_customer_child();
				$purchased_products = $bwf_contact->get_customer_purchased_products();
				$purchased          = false;

				foreach ( $offer_build->products as $product_data ) {
					$offer_product_id = $product_data->data->get_id();
					$offer_product    = $product_data->data;

					if ( $offer_product->is_type( 'variation' ) && true === $treat_variable_as_simple ) {
						$offer_product_id = $offer_product->get_parent_id();
					}


					if ( in_array( $offer_product_id, $purchased_products, true ) ) {
						/**
						 * If any of the offer Product IDs matches with the purchased product then
						 */
						$purchased = true;
						break;
					}
				}

			/**
			 * Items are already purchased. as products in offer are available in purchased products
			 */
			if ( $purchased ) {
				WFOCU_Core()->template_loader->invalidation_reason = self::INVALIDATION_PAST_PURCHASED;
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Items are already purchased in past' );
				WFOCU_Core()->session_db->set_skip_id( 8 );
				return false;
			}
		}
		$is_sold_indiv = false;
		foreach ( $offer_build->products as $product_data ) {
			$iteration = true;

				if ( 'trash' === $product_data->status ) {
					WFOCU_Core()->session_db->set_skip_id( 11 );
				}

				if ( $product_data->data->is_type( 'variable' ) ) {
					if ( ! isset( $product_data->variations_data ) ) {
						$iteration = false;
						continue;
					}

					if ( ! isset( $product_data->variations_data['available_variations'] ) ) {
						$iteration = false;
						continue;
					}

					if ( empty( $product_data->variations_data['available_variations'] ) ) {
						$iteration = false;
						continue;
					}
				}
				if ( false === $product_data->is_purchasable ) {
					$iteration = false;
					continue;
				}
				if ( isset( $product_data->is_in_stock ) ) {

					// Enable or disable the add to cart button
					if ( ! $product_data->is_purchasable || ! ( isset( $product_data->is_in_stock ) && $product_data->is_in_stock ) ) {

						$iteration = false;
					}

					/**
					 * if product is in stock
					 * if backorder not allowed
					 * if max_qty is not -1
					 */
					if ( ( isset( $product_data->is_in_stock ) && false === $product_data->backorders_allowed ) && ( isset( $product_data->max_qty ) && - 1 !== $product_data->max_qty ) ) {

						$current_stock = $product_data->max_qty;
						$offer_qty     = (int) $product_data->quantity;

						if ( $current_stock < $offer_qty ) {
							$is_sold_indiv = true;
							$iteration     = false;
						}
					}
				}

				/**
				 * If all product passes the check, then show the upsell
				 */
				if ( true === $iteration ) {
					return true;
				}
			}

			if ( $is_sold_indiv ) {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Offer product(s) are marked as sold individually' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
				WFOCU_Core()->template_loader->invalidation_reason = self::INVALIDATION_IS_SOLD_IND;
				if ( ! WFOCU_Core()->session_db->get_skip_id() ) {
					WFOCU_Core()->session_db->set_skip_id( 14 );
				}
			} else {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Offer product(s) are not purchasable/instock' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
				WFOCU_Core()->template_loader->invalidation_reason = self::INVALIDATION_NOT_PURCHASABLE;
				if ( ! WFOCU_Core()->session_db->get_skip_id() ) {
					WFOCU_Core()->session_db->set_skip_id( 9 );
				}
			}


			return false;

		}

		public function append_offer_unique_class( $classes ) {
			array_push( $classes, 'wfocu_offer' . WFOCU_Core()->data->get_current_offer() );

			return $classes;
		}

		public function get_the_link( $offer ) {

			$offer_data = WFOCU_Core()->offers->get_offer( $offer );

			if ( is_object( $offer_data ) && 'custom-page' === $offer_data->template ) {
				$custom_page_id = get_post_meta( $offer, '_wfocu_custom_page', true );
				if ( ! empty( $custom_page_id ) ) {
					return apply_filters( 'wfocu_front_offer_url', get_permalink( $custom_page_id ) );
				}
			}

			return apply_filters( 'wfocu_front_offer_url', get_permalink( $offer ) );

		}

		public function get_offer( $offer_id, $build = false ) {
			$data = get_post_meta( $offer_id, '_wfocu_setting', true );

			$offer_data = apply_filters( 'wfocu_offer_setting', $data, $offer_id );
			if ( false !== $build ) {
				return $this->build_offer_product( $offer_data );
			}

			return $offer_data;
		}

		public function build_offer_product( $offer_data, $offer_id = 0, $is_front = false ) {

			$variations = new stdClass();
			$products   = new stdClass();
			if ( empty( $offer_data ) || ! isset( $offer_data->products ) ) {
				$default                        = new stdClass();
				$default->products              = new stdClass();
				$default->fields                = new stdClass();
				$default->variations            = new stdClass();
				$default->settings              = $this->get_default_offer_setting();
				$default->template              = '';
				$default->template_group        = '';
				$default->have_multiple_product = 1;

				return $default;
			}
			$offer_data = apply_filters( 'wfocu_build_offer_product_before', $offer_data, $offer_id, $is_front );

			$offer_settings                = isset( $offer_data->settings ) ? $offer_data->settings : [];
			$offer_data->settings          = (object) array_merge( (array) $this->get_default_offer_setting(), (array) $offer_settings );
			$products_list                 = $offer_data->products;
			$output                        = new stdClass();
			$output->fields                = $offer_data->fields;
			$output->settings              = ! empty( $offer_data->settings ) ? $offer_data->settings : $this->get_default_offer_setting();
			$output->template_group        = isset( $offer_data->template_group ) ? $offer_data->template_group : '';
			$output->have_multiple_product = isset( $offer_data->have_multiple_product ) ? $offer_data->have_multiple_product : 1;
			$output->is_show_confirmation  = $offer_data->settings->ask_confirmation;
			$output->shipping_preferece    = ( true === $offer_data->settings->ship_dynamic ) ? 'dynamic' : 'flat';

			if ( $is_front === true ) {
				$output->template = isset( $offer_data->template ) ? $offer_data->template : WFOCU_Core()->template_loader->get_default_template( $offer_data );

			} else {
				$output->template = isset( $offer_data->template ) ? $offer_data->template : '';
			}
			if ( false === class_exists( 'WooFunnels_UpStroke_Dynamic_Shipping' ) ) {
				$output->shipping_preferece     = 'flat';
				$output->settings->ship_dynamic = false;
			}
			$output->allow_free_shipping = false;

			$custom_page = get_post_meta( $offer_id, '_wfocu_custom_page', true );

			if ( $custom_page !== '' ) {
				$output->template_custom_path = get_edit_post_link( $custom_page );
				$output->template_custom_name = get_the_title( $custom_page );
				$output->template_custom_id   = $custom_page;
			}
			foreach ( $products_list as $hash_key => $pid ) {
				$offer_data->fields->{$hash_key}->discount_type = WFOCU_Common::get_discount_setting( $offer_data->fields->{$hash_key}->discount_type );
				$pro                                            = wc_get_product( $pid );
				if ( $pro instanceof WC_Product ) {
					if ( $pro->is_type( 'variable' ) ) {

						foreach ( $pro->get_children() as $child_id ) {
							$variation = wc_get_product( $child_id );

							$variation_id = $child_id;
							$vpro         = $variation;

							if ( $vpro ) {
								$variation_options                    = new stdClass();
								$variation_options->vid               = $variation_id;
								$variation_options->is_enable         = false;
								$variation_options->attributes        = new stdClass();
								$variation_options->attributes        = WFOCU_Common::get_variation_attribute( $vpro );
								$variation_options->regular_price     = wc_price( $vpro->get_regular_price() );
								$variation_options->regular_price_raw = wc_get_price_including_tax( $vpro, array( 'price' => $vpro->get_regular_price() ) );
								$variation_options->price             = wc_price( $vpro->get_price() );
								$variation_options->price_raw         = $vpro->get_price();

								if ( false === $is_front && isset( $variation_options->price ) && $variation_options->regular_price === $variation_options->price ) {
									unset( $variation_options->price );
								}

								$variation_options->display_price   = wc_price( $vpro->get_price() );
								$variation_options->discount_amount = 0;
								$variation_options->name            = WFOCU_Common::get_formatted_product_name( $vpro );
								$variation_options->is_in_stock     = $vpro->is_in_stock();

								if ( isset( $offer_data->variations->{$hash_key} ) ) {
									if ( isset( $offer_data->variations->{$hash_key}[ $variation_id ] ) ) {
										$vars = $offer_data->variations->{$hash_key}[ $variation_id ];
										foreach ( $vars as $vkey => $vval ) {
											$variation_options->is_enable = true;
											$variation_options->{$vkey}   = $vval;
										}
									}
								}

								$variations->{$hash_key}[ $variation_id ] = $variation_options;
								unset( $variation_options );
							}
						}
					}

					$image_url = wp_get_attachment_url( $pro->get_image_id() );

					if ( false === $image_url || '' === $image_url ) {
						$image_url = WFOCU_PLUGIN_URL . '/assets/img/product_default_icon.jpg';
					}
					$product_details       = new stdClass();
					$product_details->id   = $pid;
					$product_details->name = ( false === $is_front ) ? WFOCU_Common::get_formatted_product_name( $pro ) : $pro->get_title();

					$product_details->image  = $image_url;
					$product_options         = $product_details;
					$product_details->type   = $pro->get_type();
					$product_details->status = $pro->get_status();
					if ( false === $pro->is_type( 'variable' ) ) {

						if ( false === $is_front ) {
							if ( $pro->is_type( 'subscription' ) ) {
								$product_details->regular_price = WC_Subscriptions_Product::get_price_string( $pro, array( 'price' => wc_price( $pro->get_regular_price() ) ) );
							} else {
								$product_details->regular_price = wc_price( $pro->get_regular_price() );
							}
							$product_details->regular_price_raw = $pro->get_regular_price();
							if ( $pro->is_type( 'subscription' ) ) {
								$product_details->price     = WC_Subscriptions_Product::get_price_string( $pro, array( 'price' => wc_price( $pro->get_price() ) ) );
								$product_details->price_raw = $pro->get_price();
							} else {
								$product_details->price     = wc_price( $pro->get_price() );
								$product_details->price_raw = $pro->get_price();
							}

							if ( $product_details->regular_price === $product_details->price ) {
								unset( $product_details->price );
							}
						} else {
							$product_details->regular_price_incl_tax = ! empty( $pro->get_regular_price() ) ? wc_get_price_including_tax( $pro, array( 'price' => $pro->get_regular_price() ) ) * $offer_data->fields->{$hash_key}->quantity : 0;
							$product_details->regular_price_excl_tax = ! empty( $pro->get_regular_price() ) ? wc_get_price_excluding_tax( $pro, array( 'price' => $pro->get_regular_price() ) ) * $offer_data->fields->{$hash_key}->quantity : 0;

							$product_details->sale_price_incl_tax        = WFOCU_Core()->offers->get_product_price( $pro, $offer_data->fields->{$hash_key}, true, $offer_data );
							$product_details->sale_price_raw_incl_tax    = WFOCU_Core()->offers->get_product_price( $pro, $offer_data->fields->{$hash_key}, true, $offer_data );
							$product_details->sale_price_excl_tax        = WFOCU_Core()->offers->get_product_price( $pro, $offer_data->fields->{$hash_key}, false, $offer_data );
							$product_details->sale_price_incl_tax_html   = WFOCU_Core()->offers->get_product_price_display( $pro, $offer_data->fields->{$hash_key}, true, $offer_data );
							$product_details->sale_price_excl_tax_html   = WFOCU_Core()->offers->get_product_price_display( $pro, $offer_data->fields->{$hash_key}, false, $offer_data );
							$product_details->sale_modify_price_excl_tax = WFOCU_Core()->offers->get_product_price( $pro, $offer_data->fields->{$hash_key}, false, $offer_data, true );
							$product_details->sale_modify_price_incl_tax = WFOCU_Core()->offers->get_product_price( $pro, $offer_data->fields->{$hash_key}, true, $offer_data, true );


							if ( $this->show_price_including_tax() ) {
								$product_details->price         = $product_details->sale_price_incl_tax;
								$product_details->price_raw     = $product_details->sale_price_incl_tax;
								$product_details->display_price = $product_details->sale_price_incl_tax_html;
								$product_details->regular_price = $product_details->regular_price_incl_tax;
							} else {
								$product_details->price_raw     = $product_details->sale_price_excl_tax;
								$product_details->display_price = $product_details->sale_price_excl_tax_html;
								$product_details->regular_price = $product_details->regular_price_excl_tax;
							}
							$product_details->tax = $product_details->sale_price_incl_tax - $product_details->sale_price_excl_tax;

						}
					}
					$product_details->data = $pro;
					$temp_fields           = $offer_data->fields->{$hash_key};
					if ( ! empty( $temp_fields ) ) {
						foreach ( $temp_fields as $fkey => $t_fields ) {
							$product_details->{$fkey} = $t_fields;
						}
					}

					if ( ! property_exists( $product_details, 'shipping_cost_flat' ) ) {
						$product_details->shipping_cost_flat = 10;
					}
					$product_details->shipping_cost_flat = WFOCU_Plugin_Compatibilities::get_fixed_currency_price( $product_details->shipping_cost_flat );
					if ( ! property_exists( $product_details, 'shipping_cost_flat_tax' ) ) {
						$product_details->shipping_cost_flat_tax = $is_front ? WFOCU_Core()->shipping->get_flat_shipping_rates( $product_details->shipping_cost_flat ) : 0;
					}
					if ( ! property_exists( $product_details, 'needs_shipping' ) ) {
						$product_details->needs_shipping = wc_shipping_enabled() && $pro->needs_shipping();
					}


					$products->{$hash_key} = apply_filters( 'wfocu_offer_product_data', $product_details, $output, $offer_data, $is_front, $hash_key );
					unset( $product_details );
					unset( $product_options );
				}
				if ( false === WFOCU_Common::is_add_on_exist( 'MultiProduct' ) ) {
					break;
				}
			}
			$output->last_edit  = $this->get_offer_last_edit( $offer_id );
			$output->products   = $products;
			$output->variations = $variations;
			$output             = apply_filters( 'wfocu_offer_data', $output, $offer_data, $is_front );

			return $output;
		}

		public function get_default_offer_setting() {
			$obj                            = new stdClass();
			$obj->ship_dynamic              = false;
			$obj->ask_confirmation          = false;
			$obj->allow_free_ship_select    = false;
			$obj->skip_exist                = false;
			$obj->skip_purchased            = false;
			$obj->check_add_offer_script    = false;
			$obj->check_add_offer_purchase  = false;
			$obj->upsell_page_track_code    = '';
			$obj->upsell_page_purchase_code = '';
			$obj->qty_selector              = false;
			$obj->qty_max                   = '10';
			$obj->jump_on_accepted          = false;
			$obj->jump_on_rejected          = false;
			$obj->jump_to_offer_on_accepted = 'automatic';
			$obj->jump_to_offer_on_rejected = 'automatic';

			return apply_filters( 'wfocu_offer_settings_default', $obj );
		}

		/**
		 * @param $product
		 * @param $options
		 * @param bool $incl_tax
		 * @param $offer_data
		 *
		 * @return string
		 */
		public function get_product_price_display( $product, $options, $incl_tax, $offer_data ) {

			if ( ! $product instanceof WC_Product ) {
				$product = wc_get_product( $product );
			}

			$get_price = $this->get_product_price( $product, $options, $incl_tax, $offer_data );

			return wc_price( $get_price );

		}

		public function show_price_including_tax() {
			return true;
		}

		public function get_offer_last_edit( $offer_id ) {
			$get_last_edit = get_post_meta( $offer_id, '_wfocu_edit_last', true );

			return ( '' !== $get_last_edit ) ? $get_last_edit : 0;
		}

		public function filter_product_object_for_db( $product ) {
			$keys_to_filter = array(
				'settings' => array( 'needs_shipping', 'shipping_cost_flat_tax' ),
				'is_in_stock',
				'max_qty',
				'is_purchasable',
				'backorders_allowed',
				'name',
				'image',
			);

			if ( isset( $product->options ) ) {
				unset( $product->options );
			}
			foreach ( $keys_to_filter as $key => $value ) {

				if ( is_array( $value ) ) {
					foreach ( $value as $internal_keys ) {
						if ( isset( $product->{$key}->{$internal_keys} ) ) {
							unset( $product->{$key}->{$internal_keys} );
						}
					}
				} else {
					if ( isset( $product->{$value} ) ) {
						unset( $product->{$value} );
					}
				}
			}

			return $product;
		}

		public function filter_step_object_for_db( $step ) {
			$keys_to_filter = array(
				'url',
			);
			if ( $step['state'] === '1' || $step['state'] === 'true' || $step['state'] === true || $step['state'] === 1 ) {
				$step['state'] = '1';
			} else {
				$step['state'] = '0';
			}

			/***
			 * update post status on disabled offer state
			 */
			if ( absint( $step['id'] ) > 0 ) {
				wp_update_post( array(
					'ID'          => $step['id'],
					'post_status' => ( 1 === absint( $step['state'] ) ) ? 'publish' : 'draft'
				) );
			}

			foreach ( $keys_to_filter as $value ) {

				if ( isset( $step[ $value ] ) ) {
					unset( $step[ $value ] );
				}
			}

			return $step;
		}

		public function filter_fields_object_for_db( $fields ) {
			$keys_to_filter = array( 'needs_shipping', 'shipping_cost_flat_tax' );

			foreach ( $keys_to_filter as $value ) {

				foreach ( $fields as $k => $config ) {  //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
					if ( isset( $fields->{$k}->{$value} ) ) {
						unset( $fields->{$k}->{$value} );
					}
				}
			}

			return $fields;
		}


		public function get_parent_funnel( $offer_id ) {

			return get_post_meta( $offer_id, '_funnel_id', true );
		}

		public function get_invalidation_reason_string( $identifier ) {
			$reasons = $this->invalidation_reasons();

			if ( array_key_exists( $identifier, $reasons ) ) {
				return '<span class="skip-reason">' . $reasons[ $identifier ] . '</span>';
			}

			return 'NA';

		}

		public function invalidation_reasons() {
			return array(
				self::INVALIDATION_PRODUCT_IN_ORDER         => __( 'Offer product(s) exist in parent order.', 'woofunnels-upstroke-one-click-upsell' ),
				self::INVALIDATION_NOT_PURCHASABLE          => __( 'Offer Product is not purchasable/in stock.', 'woofunnels-upstroke-one-click-upsell' ),
				self::INVALIDATION_PAST_PURCHASED           => __( 'Offer product(s) previously purchased by customer.', 'woofunnels-upstroke-one-click-upsell' ),
				self::INVALIDATION_NOT_SUPPORT_SUBSCRIPTION => __( 'Offer product(s) is of type subscription and gateway not supported. Please contact support.', 'woofunnels-upstroke-one-click-upsell' ),
				self::INVALIDATION_IS_SOLD_IND              => __( 'Offer product(s) are marked as sold individually.', 'woofunnels-upstroke-one-click-upsell' ),
			);
		}

		public function get_default_offer_schema() {
			return array(
				'id'    => '{{offer_id}}',
				'name'  => __( 'Sample Offer- Do not miss', 'woofunnels-upstroke-one-click-upsell' ),
				'type'  => 'upsell',
				'state' => '1',
				'slug'  => 'sample-offer-do-not-miss',
				'meta'  => array(

					'_offer_type'    => 'upsell',
					'_wfocu_setting' => (object) array(
						'products'              => (object) array(
							$this->get_default_product_key( $this->get_default_product() ) => $this->get_default_product(),
						),
						'variations'            => (object) array(),
						'fields'                => (object) array(
							$this->get_default_product_key( $this->get_default_product() ) => (object) array(
								'discount_amount'    => '10',
								'discount_type'      => 'percentage_on_reg',
								'quantity'           => '1',
								'shipping_cost_flat' => 0.0,
							),
						),
						'have_multiple_product' => 1,
						'template'              => '',
						'template_group'        => '',
						'settings'              => (object) array(
							'ship_dynamic'           => false,
							'ask_confirmation'       => false,
							'allow_free_ship_select' => false,
							'skip_exist'             => false,
							'skip_purchased'         => false,
						),
					),
				),
			);
		}

		public function get_default_product_key( $post ) {
			return md5( $post );

		}

		public function get_default_product() {
			$bwf_cache      = WooFunnels_Cache::get_instance();
			$latest_product = $bwf_cache->get_cache( 'get_latest_product', 'upstroke' );
			if ( empty( $latest_product ) ) {
				$query    = new WC_Product_Query( array(
					'limit'  => 1,
					'type'   => 'simple',
					'return' => 'ids',
				) );
				$products = $query->get_products();

				if ( is_array( $products ) ) {
					$bwf_cache->set_cache( 'get_latest_product', $products[0], 'upstroke' );

					return $products[0];
				} else {
					return false;
				}
			}

			return $latest_product;

		}

		public function get_offer_state( $steps, $offer_id ) {
			foreach ( is_array( $steps ) ? $steps : array() as $step ) {
				if ( intval( $step['id'] ) === intval( $offer_id ) || absint( $step['id'] ) === $offer_id ) {
					return $step['state'];
				}
			}

			return null;
		}

		public function get_product_key_by_index( $index, $products ) {

			$get_keys = get_object_vars( $products );

			if ( empty( $get_keys ) ) {
				return false;
			}
			$get_keys = array_keys( $get_keys );
			if ( empty( $get_keys ) ) {
				return false;
			}
			if ( ! is_numeric( $index ) || ! isset( $get_keys[ $index - 1 ] ) ) {
				return false;
			}

			return $get_keys[ $index - 1 ];
		}

		/**
		 * Exclude primary order price in offer when primary order cancelled setting enabled
		 *
		 * @param $price
		 * @param $incl_tax
		 * @param $original_price
		 *
		 * @return float|mixed
		 */
		public function update_offer_price_on_cancel_primary( $price, $incl_tax, $original_price, $offer_settings ) {
			if ( true !== $original_price ) {
				return $price;
			}

			$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new stdClass();
			$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;

			/**
			 * If qty selector is enabled then we simply need not to calculate the price since we need to switch to refund method
			 */
			if ( false !== $qty_selector_enabled ) {
				return $price;
			}
			$order_behavior  = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$cancel_original = WFOCU_Core()->funnels->get_funnel_option( 'is_cancel_order' );
			if ( 'create_order' !== $order_behavior || 'yes' !== $cancel_original ) {
				return $price;
			}
			$get_offer_id      = WFOCU_Core()->data->get( 'current_offer' );
			$get_target_offers = apply_filters( 'wfocu_offers_to_cancel_primary', [ WFOCU_Core()->offers->get_the_first_offer() ] );

			/* check which accept offers to cancel the primary order */
			if ( ! in_array( $get_offer_id, $get_target_offers ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				return $price;
			}

			$get_parent_order = WFOCU_Core()->data->get( 'porder', false, '_orders' );
			if ( ! empty( $get_parent_order ) ) {
				/* Less primary order price, shipping and tax from offer price
					shipping auto added parent get_total function like price = 10 and shipping = 2 so get_total return 12
				*/
				if ( $get_parent_order->get_total() <= $price ) {
					if ( true === $incl_tax ) {
						$price = $price - $get_parent_order->get_total();
					} else {
						$price = $price - ( $get_parent_order->get_total() - $get_parent_order->get_total_tax() );
					}


				}

			}

			return $price;
		}

		/**
		 * modify upsell offer price if primary order cancel
		 *
		 * @param $package
		 *
		 * @return mixed
		 */
		public function update_offer_modify_price( $package ) {
			$total           = 0;
			$tax             = 0;
			$order_behavior  = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$cancel_original = WFOCU_Core()->funnels->get_funnel_option( 'is_cancel_order' );
			if ( 'create_order' !== $order_behavior || 'yes' !== $cancel_original ) {
				return $package;
			}

			/**
			 * For any case where we have multiple products in the package we are switching back to refund method
			 */
			if ( count( $package['products'] ) > 1 ) {
				return $package;
			}
			foreach ( $package['products'] as &$product ) {

				if ( ( 'variable' === $product['_offer_data']->type || 'variable-subscription' === $product['_offer_data']->type ) ) {

					if ( isset( $product['_offer_data']->variations_data ) && isset( $product['_offer_data']->variations_data['prices'][ $product['id'] ] ) && isset( $product['_offer_data']->variations_data['prices'][ $product['id'] ]['sale_modify_price_excl_tax'] ) ) {
						$product_variation_data = $product['_offer_data']->variations_data['prices'][ $product['id'] ];
						if ( $product_variation_data['sale_modify_price_excl_tax'] !== $product_variation_data['price_excl_tax'] ) {


							/**
							 * its a free trail case with zero charge
							 */
							if ( array_key_exists( 'free_trial_length', $product_variation_data ) && ! empty( $product_variation_data['free_trial_length'] ) && empty( $product_variation_data['signup_fee_excluding_tax'] ) ) {
								continue;
							}

							/**
							 * Here we are handling free trial with sign upfee cases
							 */
							if ( array_key_exists( 'free_trial_length', $product_variation_data ) && ! empty( $product_variation_data['free_trial_length'] ) && array_key_exists( 'signup_fee_excluding_tax', $product_variation_data ) && ! empty( $product_variation_data['signup_fee_excluding_tax'] ) ) {

								$modify_price          = ( $product_variation_data['signup_fee_including_tax'] - ( $product_variation_data['price_incl_tax'] - $product_variation_data['sale_modify_price_incl_tax'] ) );
								$modify_price_excl_tax = ( $product_variation_data['signup_fee_excluding_tax'] - ( $product_variation_data['price_excl_tax'] - $product_variation_data['sale_modify_price_excl_tax'] ) );

								if ( $modify_price_excl_tax !== $modify_price ) {
									$tax += $modify_price - $modify_price_excl_tax;
								}

								$total += $modify_price_excl_tax;


							} else {
								$modify_price          = $product['_offer_data']->variations_data['prices'][ $product['id'] ]['sale_modify_price_incl_tax'];
								$modify_price_excl_tax = $product['_offer_data']->variations_data['prices'][ $product['id'] ]['sale_modify_price_excl_tax'];
								if ( $modify_price !== $modify_price_excl_tax ) {
									$tax += $modify_price - $modify_price_excl_tax;
								}
								$total += $modify_price_excl_tax;
							}


							/*
							 * Modify package difference charge price before charging order
							 * Send correct price for gateways
							 */
							if ( isset( $product['price'] ) ) {
								$product['old_price'] = $product['price'];
								$product['price']     = $modify_price;
							}
							if ( isset( $product['args'] ) ) {
								$product['args']['subtotal'] = $modify_price;
								$product['args']['total']    = $modify_price;
							}

							break;
						}

					}
				} else {


					if ( $product['_offer_data']->type === 'bundle' || isset( $product['_child_of_bundle'] ) ) {
						continue;
					}
					$temp_product = get_object_vars( $product['_offer_data'] );
					if ( is_array( $temp_product ) && array_key_exists( 'sale_modify_price_excl_tax', $temp_product ) ) {


						if ( $product['_offer_data']->sale_modify_price_excl_tax !== $product['_offer_data']->sale_price_excl_tax ) {

							/**
							 * its a free trail case with zero charge
							 */
							if ( array_key_exists( 'free_trial_length', $temp_product ) && ! empty( $product['_offer_data']->free_trial_length ) && empty( $product['_offer_data']->signup_fee_excluding_tax ) ) {
								continue;
							}


							if ( array_key_exists( 'free_trial_length', $temp_product ) && ! empty( $product['_offer_data']->free_trial_length ) && array_key_exists( 'signup_fee_excluding_tax', $temp_product ) && ! empty( $product['_offer_data']->signup_fee_excluding_tax ) ) {
								$modify_price          = ( $product['_offer_data']->signup_fee_including_tax - ( $product['_offer_data']->sale_price_incl_tax - $product['_offer_data']->sale_modify_price_incl_tax ) );
								$modify_price_excl_tax = ( $product['_offer_data']->signup_fee_excluding_tax - ( $product['_offer_data']->sale_price_excl_tax - $product['_offer_data']->sale_modify_price_excl_tax ) );

								if ( $modify_price_excl_tax !== $modify_price ) {
									$tax += $modify_price - $modify_price_excl_tax;
								}
								$total += $modify_price_excl_tax;
							} else {
								$modify_price          = $product['_offer_data']->sale_modify_price_incl_tax;
								$modify_price_excl_tax = $product['_offer_data']->sale_modify_price_excl_tax;
								if ( $modify_price !== $modify_price_excl_tax ) {
									$tax += $modify_price - $modify_price_excl_tax;
								}
								$total += $modify_price_excl_tax;
							}


							/*
							 * Modify package difference charge price before charging order
							 * Send correct price for gateways
							 */
							if ( isset( $product['price'] ) ) {
								$product['old_price'] = $product['price'];
								$product['price']     = $modify_price;
							}
							if ( isset( $product['args'] ) ) {
								$product['args']['subtotal'] = $modify_price;
								$product['args']['total']    = $modify_price;
							}
							break;
						}
					}
				}
			}

			if ( $total === 0 ) {
				return $package;
			}

			/**
			 * Save meta value for diced primary order is canceled or refund on yes primary order canceled mark
			 */
			$package['_diff_charged'] = 'yes';

			$shipping = ( isset( $package['shipping'] ) && isset( $package['shipping']['diff'] ) ) ? $package['shipping']['diff']['cost'] : 0;
			$shipping = ( isset( $package['shipping'] ) && isset( $package['shipping']['diff'] ) && isset( $package['shipping']['diff']['tax'] ) ) ? $shipping + ( $package['shipping']['diff']['tax'] ) : $shipping;
			$taxes    = $tax;

			//modified charging amount
			$package['total'] = $total + $shipping + $taxes;

			return $package;
		}

		/**
		 * @param $is_batching_on
		 *
		 * @return void
		 */
		public function update_package_on_cancel_primary( $is_batching_on ) {
			if ( true === $is_batching_on ) {
				return;
			}

			/*
			 * Restore old package charge price after charged order process
			 * and before handle success or failed upsell package
			 */
			$package = WFOCU_Core()->data->get( '_upsell_package' );
			if ( isset( $package ) && is_array( $package['products'] ) ) {
				foreach ( $package['products'] as &$product ) {
					if ( is_array( $product ) && isset( $product['old_price'] ) ) {
						$org_price                   = $product['old_price'];
						$product['price']            = $org_price;
						$product['args']['subtotal'] = $org_price;
						$product['args']['total']    = $org_price;

					}
				}
			}
			WFOCU_Core()->data->set( '_upsell_package', $package );
			WFOCU_Core()->data->save();
		}

		/**
		 * @param $duplicate_id
		 * @param $title
		 * @param $upsell_id
		 * @param $funnel_meta
		 *
		 *  Duplicate single offer
		 *  if funnel_meta is false then new created offer meta not add in upsell meta
		 *  funnel_meta param mainly create for experiment for variant duplicate
		 *
		 * @return int|WP_Error
		 */
		public function duplicate_offer( $duplicate_id, $title = '', $upsell_id = 0, $funnel_meta = true ) {
			$offer_id_new = 0;
			$steps_data   = [];
			$new_step     = [];

			if ( 0 === absint( $duplicate_id ) ) {
				return $offer_id_new;
			}

			$offer_state_new = ! empty( $title ) ? '1' : '0';
			$offer_type_new  = 'upsell';
			if ( 0 === $upsell_id ) {
				$upsell_id = get_post_meta( $duplicate_id, '_funnel_id', true );
			}

			if ( false === $funnel_meta ) {
				$upsell_id = 0;
			}

			if ( absint( $upsell_id ) > 0 ) {
				$steps_data = get_post_meta( $upsell_id, '_funnel_steps', true );

				if ( is_array( $steps_data ) && count( $steps_data ) > 0 ) {
					$search_key = array_search( absint( $duplicate_id ), array_map( 'intval', wp_list_pluck( $steps_data, 'id' ) ), true );
					if ( false !== $search_key ) {

						if ( isset( $steps_data[ $search_key ]['state'] ) && ( $steps_data[ $search_key ]['state'] === '1' || true === $steps_data[ $search_key ]['state'] || 1 === $steps_data[ $search_key ]['state'] ) ) {
							$offer_state_new = '1';
						}

						if ( isset( $steps_data[ $search_key ]['type'] ) ) {
							$offer_type_new = $steps_data[ $search_key ]['type'];
						}
					}
				}
			}

			$step_post = get_post( $duplicate_id );

			if ( null === $step_post || ! $step_post instanceof WP_Post ) {
				return $offer_id_new;
			}

			$offer_name_new = ! empty( $title ) ? $title : $step_post->post_title . ' - Copy';

			$offer_post_new = array(
				'post_title'   => $offer_name_new,
				'post_type'    => WFOCU_Common::get_offer_post_type_slug(),
				'post_status'  => ( 1 === absint( $offer_state_new ) ) ? 'publish' : 'draft',
				'post_content' => $step_post->post_content,
				'post_name'    => sanitize_title( $offer_name_new ),
			);

			$offer_id_new = wp_insert_post( $offer_post_new );
			if ( ! is_wp_error( $offer_id_new ) && $offer_id_new ) {
				$get_offer = $duplicate_id;

				$offer_custom = get_option( 'wfocu_c_' . $get_offer, '' );
				if ( absint( $upsell_id ) > 0 ) {
					update_post_meta( $offer_id_new, '_funnel_id', $upsell_id );
				}
				update_post_meta( $offer_id_new, '_wfocu_edit_last', time() );

				if ( ! empty( $offer_custom ) ) {
					update_option( 'wfocu_c_' . $offer_id_new, $offer_custom, 'no' );
				}

				$new_offer_slug = get_post( $offer_id_new )->post_name;

				$new_step['id']     = $offer_id_new;
				$new_step['name']   = $offer_name_new;
				$new_step['type']   = $offer_type_new;
				$new_step['state']  = $offer_state_new;
				$new_step['slug']   = $new_offer_slug;
				$new_step['old_id'] = $get_offer;
				$new_step['url']    = get_site_url() . '?wfocu_offer=' . $new_offer_slug;

				$exclude_meta_keys_to_copy = apply_filters( 'wfocu_do_not_duplicate_meta', [ '_funnel_id', '_wfocu_edit_last', '_bwf_ab_variation_of' ], $get_offer, $offer_id_new, $new_step );

				global $wpdb;

				$post_meta_all = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$get_offer" ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				if ( ! empty( $post_meta_all ) ) {
					$sql_query_selects = [];
					foreach ( $post_meta_all as $meta_info ) {

						$meta_key = $meta_info->meta_key;

						if ( in_array( $meta_key, $exclude_meta_keys_to_copy, true ) ) {
							continue;
						}

						$meta_key   = esc_sql( $meta_key );
						$meta_value = esc_sql( $meta_info->meta_value );

						$sql_query_selects[] = "( $offer_id_new, '$meta_key', '$meta_value')"; //db call ok; no-cache ok; WPCS: unprepared SQL ok.
					}

					$sql_query_meta_val = implode( ',', $sql_query_selects );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta ) );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared

				}

				do_action( 'wfocu_offer_duplicated', $offer_id_new, $get_offer );

			} else {
				return 0;
			}

			if ( absint( $upsell_id ) > 0 ) {
				if ( is_array( $steps_data ) && count( $steps_data ) > 0 ) {
					array_push( $steps_data, $new_step );
				} else {
					$steps_data   = [];
					$steps_data[] = $new_step;
				}
				update_post_meta( $upsell_id, '_funnel_steps', $steps_data );
				$new_funnel_upsell_downsells = WFOCU_Core()->funnels->prepare_upsell_downsells( $steps_data );
				update_post_meta( $upsell_id, '_funnel_upsell_downsell', $new_funnel_upsell_downsells );
			}

			return $offer_id_new;
		}

	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'offers', 'WFOCU_Offers' );
	}
}
