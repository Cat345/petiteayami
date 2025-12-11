<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_Product_Bundles' ) ) {
	class WFOCU_Compatibility_With_Product_Bundles {
		public $accepted_ids = [];

		public function __construct() {
			if ( true === class_exists( 'WC_Bundles' ) ) {

				add_action( 'woocommerce_bundled_add_to_order', array( $this, 'record_bundled_ids_during_upsell' ) );
				add_filter( 'wfocu_add_products_to_the_order', array( $this, 'maybe_add_bundle_to_the_cart' ), 10, 2 );
				add_action( 'wfocu_after_reduce_stock_on_batching', array( $this, 'maybe_reduce_stock_for_bundled_upsells' ), 10, 2 );

				add_filter( 'wfocu_products_compatible_for_stock_check', array( $this, 'allow_bundle_products_in_stock' ) );
				add_filter( 'wfocu_product_raw_price', array( $this, 'pass_bundle_product_regular_price' ), 10, 3 );
				add_filter( 'wfocu_product_raw_sale_price', array( $this, 'pass_bundle_product_sale_price' ), 10, 4 );
				add_filter( 'wfocu_offer_product_data', array( $this, 'setup_bundle_discount_prices' ), 10, 5 );
				add_filter( 'wfocu_upsell_package', array( $this, 'recreate_upsell_package_for_bundled_products' ), 5 );


			}
		}


		/**
		 * @hooked over `wfocu_add_products_to_the_order`
		 *
		 * @param array $products products in the package
		 * @param WC_Order $order current order
		 *
		 * @return mixed
		 */
		public function maybe_add_bundle_to_the_cart( $products, $order ) {
			$ins = WC_PB_Order::instance();
			foreach ( $products['products'] as $key => $product ) {


				$get_product = $product['data'];
				if ( $get_product && $get_product->is_type( 'bundle' ) ) {


					$configuration = [];

					$get_current_key = $key;
					$bundled_items   = $get_product->get_bundled_items();
					foreach ( $products['products'] as $key_bundle => $product_bundle ) {

						if ( isset( $product_bundle['_child_of_bundle'] ) && $get_current_key === $product_bundle['_child_of_bundle'] ) {


							$configuration[ $product_bundle['_bundle_item_id'] ] = array(
								'discount' => 0,
								'args'     => array(
									'total'    => $product_bundle['args']['total'],
									'subtotal' => $product_bundle['args']['subtotal'],
								)
							);

							$bundled_item         = $bundled_items[ $product_bundle['_bundle_item_id'] ];
							$bundled_product_type = $bundled_item->product->get_type();

							/**
							 * Handle bundle item subscription product case
							 */
							if ( $bundled_item->is_subscription() && class_exists( 'WC_Subscriptions_Product' ) ) {

								/**
								 * get signup fees and add in total
								 */
								$signup_fee = WC_Subscriptions_Product::get_sign_up_fee( $bundled_item->get_product() );
								if ( 0 !== absint( WC_Subscriptions_Product::get_trial_length( $bundled_item->get_product() ) ) ) {
									$price = wc_format_decimal( 0, wc_get_price_decimals() );

									/**
									 * Set price 0 if subscription product have trial length
									 */
									$configuration[ $product_bundle['_bundle_item_id'] ]['args'] = array(
										'total'    => ( float) $price + ( float) $signup_fee,
										'subtotal' => ( float) $price + ( float) $signup_fee,
									);
									$product['args']['total']                                    = $price;
									$product['args']['subtotal']                                 = $price;

								} else {
									$configuration[ $product_bundle['_bundle_item_id'] ]['args'] = array(
										'total'    => ( float) $product_bundle['args']['total'] + $signup_fee,
										'subtotal' => ( float) $product_bundle['args']['subtotal'] + $signup_fee,
									);
								}
							}


							/**
							 * Check if bundle product is variable and has a single filtered variation
							 * then in this case we can take the liberty to take it as a simple product
							 * and pass variation ID and attributes to the config.
							 */
							if ( 'variable' === $bundled_product_type || 'variable-subscription' === $bundled_product_type ) {

								if ( $bundled_item->has_filtered_variations() ) {
									$filtered_variations = $bundled_item->get_filtered_variations();
									if ( count( $filtered_variations ) === 1 ) {
										$get_variation                                                       = wc_get_product( $filtered_variations[0] );
										$configuration[ $product_bundle['_bundle_item_id'] ]['variation_id'] = $filtered_variations[0];
										$configuration[ $product_bundle['_bundle_item_id'] ]['attributes']   = $get_variation->get_variation_attributes();
									}
								}
							}
							/**
							 * Setting up prices by applying individual discounts
							 */

						}
						unset( $products['products'][ $key_bundle ] );
					}
					$meta_args = array(
						[
							'key'   => '_upstroke_purchase',
							'value' => 'yes'
						]
					);

					$args = array_merge( $product['args'], [ 'configuration' => $configuration ], [ 'meta_data' => $meta_args ] );
					$ins->add_bundle_to_order( $get_product, $order, $product['qty'], $args );

					unset( $products['products'][ $key ] );
				}
			}


			return $products;

		}

		public function is_enable() {
			if ( false === class_exists( 'WC_Bundles' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * @hooked over `woocommerce_bundled_add_to_order`
		 * lets collect ids that are added during the upsell so that we can further reduce stock on that basis
		 *
		 * @param $bundled_order_item_id
		 */
		public function record_bundled_ids_during_upsell( $bundled_order_item_id ) {
			array_push( $this->accepted_ids, $bundled_order_item_id );
		}


		/**
		 * @hooked over `wfocu_after_reduce_stock_on_batching`
		 * Lets reduce the bundled item stock in case of batching, order item ids are collected by WFOCU_Compatibility_With_Product_Bundles::record_bundled_ids_during_upsell()
		 *
		 * @param $products
		 * @param WC_Order $order
		 */
		public function maybe_reduce_stock_for_bundled_upsells( $products, $order ) {

			if ( empty( $this->accepted_ids ) ) {
				return;
			}
			$get_all_items = $order->get_items();
			foreach ( $this->accepted_ids as $id ) {

				$product_item = ( isset( $get_all_items[ $id ] ) ) ? $get_all_items[ $id ] : null;
				if ( null === $product_item ) {
					continue;
				}

				$product = $product_item->get_product();

				if ( $product->managing_stock() ) {
					$qty       = apply_filters( 'woocommerce_order_item_quantity', $product_item->get_quantity(), $order, $id );
					$item_name = $product->get_formatted_name();
					$new_stock = wc_update_product_stock( $product, $qty, 'decrease' );

					if ( ! is_wp_error( $new_stock ) ) {
						/* translators: 1: item name 2: old stock quantity 3: new stock quantity */
						$order->add_order_note( sprintf( __( '%1$s stock reduced from %2$s to %3$s.', 'woocommerce' ), $item_name, $new_stock + $qty, $new_stock ) );

						// Get the latest product data.
						$product = wc_get_product( $product->get_id() );

						if ( '' !== get_option( 'woocommerce_notify_no_stock_amount' ) && $new_stock <= get_option( 'woocommerce_notify_no_stock_amount' ) ) {
							do_action( 'woocommerce_no_stock', $product );
						} elseif ( '' !== get_option( 'woocommerce_notify_low_stock_amount' ) && $new_stock <= get_option( 'woocommerce_notify_low_stock_amount' ) ) {
							do_action( 'woocommerce_low_stock', $product );
						}

						if ( $new_stock < 0 ) {
							do_action( 'woocommerce_product_on_backorder', array(
								'product'  => $product,
								'order_id' => WFOCU_WC_Compatibility::get_order_id( $order ),
								'quantity' => $qty,
							) );
						}
					}
				}
			}
		}

		/**
		 * @hooked into wfocu_offer_product_types
		 * Allow subscription product in the offers
		 *
		 * @param array $product_types
		 *
		 * @return mixed
		 */
		public function allow_bundle_products_in_stock( $product_types ) {

			array_push( $product_types, 'bundle' );

			return $product_types;
		}

		/**
		 * @param $regular_price
		 * @param WC_Product $product
		 * @param $options
		 */
		public function pass_bundle_product_regular_price( $regular_price, $product, $options ) {

			if ( ! $product->is_type( 'bundle' ) ) {
				return $regular_price;
			}

			return $this->get_regular_price( $product ) * $options->quantity;
		}

		/**
		 * @param $regular_price
		 * @param WC_Product_Bundle $product
		 * @param $options
		 */
		public function pass_bundle_product_sale_price( $sale_price, $product, $options ) {

			if ( ! $product->is_type( 'bundle' ) ) {
				return $sale_price;
			}

			return $this->get_price( $product ) * $options->quantity;
		}


		public function setup_bundle_discount_prices( $product_details, $output, $offer_data, $is_front, $hash_key ) {
			if ( $product_details->data->is_type( 'bundle' ) && true === $is_front ) {


				$product_details->regular_price_incl_tax = wc_get_price_including_tax( $product_details->data, array( 'price' => $this->get_regular_price( $product_details->data ) ) ) * $offer_data->fields->{$hash_key}->quantity;
				$product_details->regular_price_excl_tax = wc_get_price_excluding_tax( $product_details->data, array( 'price' => $this->get_regular_price( $product_details->data ) ) ) * $offer_data->fields->{$hash_key}->quantity;

				$product_details->sale_price_incl_tax        = WFOCU_Core()->offers->get_product_price( $product_details->data, $offer_data->fields->{$hash_key}, true, $offer_data );
				$product_details->sale_price_raw_incl_tax    = WFOCU_Core()->offers->get_product_price( $product_details->data, $offer_data->fields->{$hash_key}, true, $offer_data );
				$product_details->sale_price_excl_tax        = WFOCU_Core()->offers->get_product_price( $product_details->data, $offer_data->fields->{$hash_key}, false, $offer_data );
				$product_details->sale_price_incl_tax_html   = WFOCU_Core()->offers->get_product_price_display( $product_details->data, $offer_data->fields->{$hash_key}, true, $offer_data, $offer_data );
				$product_details->sale_price_excl_tax_html   = WFOCU_Core()->offers->get_product_price_display( $product_details->data, $offer_data->fields->{$hash_key}, false, $offer_data, $offer_data );
				$product_details->sale_modify_price_excl_tax = WFOCU_Core()->offers->get_product_price( $product_details->data, $offer_data->fields->{$hash_key}, false, $offer_data, true );
				$product_details->sale_modify_price_incl_tax = WFOCU_Core()->offers->get_product_price( $product_details->data, $offer_data->fields->{$hash_key}, true, $offer_data, true );

				if ( WFOCU_Core()->offers->show_price_including_tax() ) {
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

			return $product_details;
		}

		public function recreate_upsell_package_for_bundled_products( $package ) {
			if ( empty( $package['products'] ) ) {
				return $package;
			}
			$new_package             = $package;
			$new_package['products'] = [];
			$current_offer_data      = WFOCU_Core()->data->get( '_current_offer_data', '', 'funnel' );
			foreach ( $package['products'] as &$product ) {

				$get_product = $product['data'];
				$_offer_data = $product['_offer_data'];
				if ( is_a( $get_product, 'WC_Product' ) && $get_product->is_type( 'bundle' ) ) {


					$configuration = [];


					$bundled_items         = $get_product->get_bundled_items();
					$individual_price_data = [];

					$bundles = [];

					/**
					 * Loop over all the bundled items and setup price data to add to the order
					 */
					foreach ( $bundled_items as $item_id => $itemdata ) {

						$bundles[ $item_id ] = array( 'product' => $itemdata->product, 'price' => 0 );
						/**
						 * Skip if not set to price individually, we ll deal it later below
						 */
						if ( ! $itemdata->is_priced_individually() ) {
							continue;
						}

						/**
						 * Setting up price filters so that below functions returns correct prices
						 */
						$itemdata->add_price_filters();
						/**
						 * Setting up prices by applying individual discounts
						 */

						$price                     = ( 'percentage_on_sale' === $_offer_data->discount_type || 'fixed_on_sale' === $_offer_data->discount_type ) ? $itemdata->product->get_price() : $itemdata->product->get_regular_price();
						$configuration[ $item_id ] = array(
							'discount' => 0,
							'args'     => array(
								'total'    => WFOCU_Common::apply_discount( wc_get_price_excluding_tax( $itemdata->product, [ 'price' => $price ] ), $current_offer_data->fields->{$product['hash']}, $product ),
								'subtotal' => WFOCU_Common::apply_discount( wc_get_price_excluding_tax( $itemdata->product, [ 'price' => $price ] ), $current_offer_data->fields->{$product['hash']}, $product )
							)
						);
						$bundles[ $item_id ]       = array(
							'product' => $itemdata->product,
							'price'   => $configuration[ $item_id ]['args']['total']
						);

						/**
						 * contains all the individual prices
						 */
						array_push( $individual_price_data, $configuration[ $item_id ]['args']['total'] );
					}
					$individual_price = number_format( array_sum( $individual_price_data ), 2, '.', '' );
					$total_price      = number_format( $product['args']['total'], 2, '.', '' );

					/**
					 * Comparing individual prices with the total price just to ensure if all the items are marked as priced individually or not
					 */
					if ( $individual_price === $total_price ) {
						/**
						 * make the parent price as zero so that it would not impact on the price totals as we have all the child prices individualy
						 */
						$product['args']['total']    = 0;
						$product['args']['subtotal'] = 0;

						$product['price']          = 0;
						$new_package['products'][] = $product;


					} else {

						/**
						 * If not all prices are set to individual prices then we need to pass main bundle product total by removing total of individual prices.
						 */
						$product['args']['total']    = ( 0.00 === $total_price ) ? $individual_price : $total_price - $individual_price;
						$product['args']['subtotal'] = ( 0.00 === $total_price ) ? $individual_price : $total_price - $individual_price;

						$product['price']          = ( 0.00 === $total_price ) ? $individual_price : $total_price - $individual_price;
						$new_package['products'][] = $product;
					}

					$get_parent_bundle_index = count( $new_package['products'] ) - 1;
					foreach ( $bundles as $item_id => $item_data ) {
						$offer_data                = clone $_offer_data;
						$offer_data->id            = $item_data['product']->get_id();
						$offer_data->name          = $item_data['product']->get_title();
						$offer_data->type          = $item_data['product']->get_type();
						$new_package['products'][] = array(
							'id'  => $item_data['product']->get_id(),
							'qty' => $product['qty'],

							'price'            => $item_data['price'],
							'args'             => array(
								'total'     => ( $item_data['price'] ),
								'variation' => isset( $product['variation'] ) ? $product['variation'] : null,
								'subtotal'  => $item_data['price'],
							),
							'hash'             => $product['hash'],
							'data'             => $item_data['product'],
							'_offer_data'      => $offer_data,
							'_child_of_bundle' => $get_parent_bundle_index,
							'_bundle_item_id'  => $item_id,
						);
					}


				} else {
					$new_package['products'][] = $product;
				}


			}

			return $new_package;
		}

		public function get_regular_price( $get_product ) {

			$price_prop    = 'regular_price';
			$price_fn      = 'get_' . $price_prop;
			$qty           = 1;
			$bundled_items = $get_product->get_bundled_items();
			$price         = wc_format_decimal( WC_PB_Product_Prices::get_product_price( $get_product, array(
				'price' => $get_product->$price_fn(),
				'qty'   => $qty,
				'calc'  => '',
			) ), wc_get_price_decimals() );
			if ( ! empty( $bundled_items ) ) {
				foreach ( $bundled_items as $bundled_item ) {
					if ( false === $bundled_item->is_purchasable() ) {
						continue;
					}

					if ( false === $bundled_item->is_priced_individually() ) {
						continue;
					}

					$bundled_item_qty = $qty * $bundled_item->get_quantity( 'min', array( 'context' => 'price', 'check_optional' => 'min' === 'min' ) );

					if ( $bundled_item_qty ) {
						/**
						 * Handle bundle item subscription product case
						 */
						if ( $bundled_item->is_subscription() && class_exists( 'WC_Subscriptions_Product' ) ) {

							/**
							 * get signup fees and add in total
							 */
							$price += WC_Subscriptions_Product::get_sign_up_fee( $bundled_item->get_product() );

							if ( 0 !== absint( WC_Subscriptions_Product::get_trial_length( $bundled_item->get_product() ) ) ) {
								/**
								 * Set price 0 if subscription product have trial length
								 */
								$price += wc_format_decimal( 0, wc_get_price_decimals() );
							} else {
								$price += wc_format_decimal( $bundled_item->calculate_price( array(
									'min_or_max' => 'min',
									'qty'        => $bundled_item_qty,
									'calc'       => '',
									'prop'       => 'regular_price',
									'recurring'  => true,
								) ), wc_get_price_decimals() );

							}
						} else {
							$price += wc_format_decimal( $bundled_item->calculate_price( array(
								'min_or_max' => 'min',
								'qty'        => $bundled_item_qty,
								'calc'       => '',
								'prop'       => 'regular_price',
								'recurring'  => true,
							) ), wc_get_price_decimals() );
						}
					}

				}
			}

			return $price;

		}

		/**
		 * @param WC_Product_Bundle $get_product
		 *
		 * @return float|string
		 */
		public function get_price( $get_product ) {

			$price_prop    = 'price';
			$price_fn      = 'get_' . $price_prop;
			$qty           = 1;
			$bundled_items = $get_product->get_bundled_items();
			$price         = wc_format_decimal( WC_PB_Product_Prices::get_product_price( $get_product, array(
				'price' => $get_product->$price_fn(),
				'qty'   => $qty,
				'calc'  => '',
			) ), wc_get_price_decimals() );
			if ( ! empty( $bundled_items ) ) {
				foreach ( $bundled_items as $bundled_item ) {
					if ( false === $bundled_item->is_purchasable() ) {
						continue;
					}

					if ( false === $bundled_item->is_priced_individually() ) {
						continue;
					}

					$bundled_item_qty = $qty * $bundled_item->get_quantity( 'min', array( 'context' => 'price', 'check_optional' => 'min' === 'min' ) );

					if ( $bundled_item_qty ) {

						$price += wc_format_decimal( $bundled_item->calculate_price( array(
							'min_or_max' => 'min',
							'qty'        => $bundled_item_qty,
							'calc'       => '',
							'prop'       => 'price',
							'recurring'  => true,
						) ), wc_get_price_decimals() );
					}

				}
			}

			return $price;

		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Product_Bundles(), 'product_bundles' );


}
