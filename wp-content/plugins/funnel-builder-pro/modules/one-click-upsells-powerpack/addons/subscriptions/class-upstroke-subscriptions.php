<?php
/**
 * Author Woofunnels.
 */
if ( ! class_exists( 'UpStroke_Subscriptions' ) ) {
	class UpStroke_Subscriptions {

		public static $instance = null;
		public $current_offer_data = null;

		public function __construct() {

			add_action( 'admin_enqueue_scripts', array( $this, 'maybe_render_assets' ) );

			/**
			 * Product Types subscription in the search while adding products
			 */
			add_filter( 'wfocu_offer_product_types', array( $this, 'allow_subscription_products_in_offer' ) );

			/**
			 * Handle subscription while cancelling the parent order
			 */
			add_action( 'wfocu_front_primary_order_cancelled', array( $this, 'maybe_cancel_primary_subscription' ) );

			/**
			 * Create New subscriptions when offer is accepted
			 */
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'maybe_create_new_subscriptions' ), 1, 5 );
			add_action( 'wfocu_offer_new_order_created_before_complete', array( $this, 'maybe_create_new_subscriptions_on_new_order' ), 1, 1 );

			add_action( 'wfocu_offer_payment_failed_event', array( $this, 'create_pending_subscription' ), 10, 1 );

			add_action( 'footer_after_print_scripts', array( $this, 'render_js' ) );
			add_action( 'wfocu_front_before_custom_offer_page', array( $this, 'maybe_register_js_print' ) );

			add_filter( 'wfocu_offer_validation_result', array( $this, 'maybe_validate_subscriptions' ), 10, 2 );
			add_filter( 'wfocu_offer_data', array( $this, 'maybe_add_signup_fee' ), 10, 3 );
			add_filter( 'wfocu_offer_data', array( $this, 'maybe_add_variable_subscription_prices' ), 999, 3 );

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'maybe_set_paypal_profile' ), 10, 2 );

			add_action( 'valid-paypal-standard-ipn-request', array( $this, 'maybe_handle_paypal_ipn_on_subscriptions' ), - 1 );

			add_action( 'wfocu_template_price_html', array( $this, 'maybe_modify_visual_price_for_subscriptions' ), 10, 6 );

			add_filter( 'wfocu_offer_settings_default', array( $this, 'add_subscription_discount_setting' ) );

			add_filter( 'wfocu_upsell_package', array( $this, 'update_upsell_package' ), 10 );

			add_action( 'wfocu_front_skip_funnel', array( $this, 'skip_running_funnel_on_renewals' ), 99, 2 );
			add_filter( 'wfocu_do_not_apply_discounts', array( $this, 'maybe_stop_applying_discount_on_offer' ), 10, 4 );

			add_filter( 'wfocu_shortcode_merge_tags', array( $this, 'register_merge_tags' ) );

			add_shortcode( 'wfocu_product_recurring_total_string', array( $this, 'product_recurring_total_string' ) );
			add_shortcode( 'wfocu_product_signup_fee', array( $this, 'product_signup_fee' ) );

			add_filter( 'wfocu_customizer_fieldset', array( $this, 'maybe_add_customizer_fields' ), 10, 2 );
			add_filter( 'wfocu_allow_free_upsells', array( $this, 'maybe_disallow_free_upsells_on_free_trail' ) );

		}

		/**
		 * Creates and instance of the class
		 *
		 * @return UpStroke_Subscriptions
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Hooked into wfocu_offer_product_types
		 * Allow subscription product in the offers
		 *
		 * @param array $product_types
		 *
		 * @return mixed
		 */
		public function allow_subscription_products_in_offer( $product_types ) {

			array_push( $product_types, 'subscription', 'variable-subscription', 'subscription_variation' );

			return $product_types;
		}

		/**
		 * Maybe cancel subscription if it contains one
		 *
		 * @param string|int $parent_order
		 */
		public function maybe_cancel_primary_subscription( $parent_order ) {
			/**
			 * Canceling subscription if available
			 */
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $parent_order ) ) {
				$parent_subscription = wcs_get_subscriptions_for_order( WFOCU_WC_Compatibility::get_order_id( $parent_order ) );

				if ( ! empty( $parent_subscription ) ) {
					// consider, we have only one subscription in parent order

					$parent_subscription = array_pop( $parent_subscription );

					if ( ! empty( $parent_subscription ) ) {
						$parent_subscription->update_status( 'cancelled', __( 'Subscription replaced by the UpStroke', 'woofunnels-upstroke-power-pack' ) );
					}
				}
			}
		}


		/**
		 * Create New Subscriptions with the data provided
		 *
		 * @param $get_offer_id
		 * @param $get_package
		 * @param $get_parent_order
		 * @param $new_order
		 * @param $get_transaction_id
		 */
		public function maybe_create_new_subscriptions( $get_offer_id, $get_package, $get_parent_order, $new_order, $get_transaction_id ) {

			/**
			 * Creation of a new order
			 */
			if ( $new_order instanceof WC_Order && did_action( 'wfocu_offer_new_order_created_before_complete' ) ) {
				return;
			}

			if ( $new_order instanceof WC_Order ) {
				$subscription_order = $new_order;

			} else {
				$subscription_order = $get_parent_order;
			}

			$user_created = null;
			foreach ( $get_package['products'] as $product ) {
				$get_product = $product['data'];
				if ( $get_product instanceof WC_Product && ( $get_product->get_type() === 'subscription' || $get_product->get_type() === 'subscription_variation' || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {
					if ( is_user_logged_in() ) {
						$user_id = $subscription_order->get_user_id();
					} else {
						$user_id      = ( null === $user_created ) ? WFOCU_Common::create_new_customer( WFOCU_WC_Compatibility::get_order_data( $subscription_order, 'billing_email' ), $subscription_order ) : $user_created;
						$user_created = $user_id;
						$subscription_order->set_customer_id( $user_id );
						$subscription_order->save();
					}

					$args = array(
						'product'          => $get_product,
						'order'            => $subscription_order,
						'user_id'          => $user_id,
						'transaction_id'   => $get_transaction_id,
						'amt'              => $product['price'],
						'_recurring_price' => $product['_recurring_price'],
					);

					$subscription = $this->_create_new_subscription( $args, $this->get_subscription_status( $subscription_order ), $product );

					if ( false !== $subscription ) {
						do_action( 'wfocu_subscription_created_for_upsell', $subscription, $product['hash'], $subscription_order );
					}
				}
			}

		}


		/**
		 * Creates a new subscription, calculates totals, move statuses.
		 *
		 * @param $args
		 * @param $status
		 * @param $data
		 *
		 * @return false|WC_Order
		 * @throws WC_Data_Exception
		 */
		private function _create_new_subscription( $args, $status, $offer_data ) {

			// create a subscription
			try {
				$product         = $args['product'];
				$order_id        = WFOCU_WC_Compatibility::get_order_id( $args['order'] );
				$order           = $args['order'];
				$current_user_id = $args['user_id'];
				$transaction_id  = $args['transaction_id'];
				$start_date      = gmdate( 'Y-m-d H:i:s' );

				$period   = $this->get_period( $product, $offer_data );
				$interval = $this->get_interval( $product, $offer_data );


				$trial_period = $this->get_trial_period( $product, $offer_data );

				WFOCU_Core()->log->log( 'Creating subscription for give args:' . print_R( array( //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						'start_date'       => $start_date,
						'order_id'         => $order_id,
						'billing_period'   => $period,
						'billing_interval' => $interval,
						'customer_note'    => $order->get_customer_note(),
						'customer_id'      => $current_user_id,
					), true ) );
				$subscription = wcs_create_subscription( array(
					'start_date'       => $start_date,
					'order_id'         => $order_id,
					'billing_period'   => $period,
					'billing_interval' => $interval,
					'customer_note'    => $order->get_customer_note(),
					'customer_id'      => $current_user_id,
				) );

				if ( is_wp_error( $subscription ) ) {
					WFOCU_Core()->log->log( 'WP Error captured :' . print_r( $subscription, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					return false;
				}
				if ( ! empty( $current_user_id ) && ! empty( $subscription ) ) {
					// turn back discounted price to it's original state (for next sub payments)

					// link subscription product & copy address details

					WFOCU_Core()->log->log( 'Creating Subscription For:' . $args['_recurring_price'] );
					$product->set_price( $args['_recurring_price'] );
					$qty                  = ( is_array( $offer_data ) && isset( $offer_data['qty'] ) && (int) $offer_data['qty'] > 1 ) ? $offer_data['qty'] : 1;
					$subscription_item_id = $subscription->add_product( $product, $qty ); // $args


					if ( defined( 'WC_PB_ABSPATH' ) ) {
						require_once( WC_PB_ABSPATH . 'includes/admin/class-wc-pb-admin-order.php' );
						\WC_PB_Admin_Order::add_bundled_items( $subscription_item_id, $subscription->get_item( $subscription_item_id ), $subscription );

					}

					$subscription = wcs_copy_order_address( $order, $subscription );

					if ( isset( $args['add_shipping'] ) ) {
						$items = $order->get_items( 'shipping' );
						if ( count( $items ) > 0 ) {
							$item = new WC_Order_Item_Shipping();
							$item->set_props( array(
								'method_title' => current( $items )->get_method_title(),
								'method_id'    => current( $items )->get_method_id(),
								'total'        => current( $items )->get_total(),
							) );
							$item->save();
							$subscription->add_item( $item );
						}

					}
					// set subscription dates

					$trial_end_date    = $this->get_trial_expiration_date( $product->get_id(), $start_date, $offer_data );
					$next_payment_date = $this->get_first_renewal_payment_date( $product->get_id(), $offer_data, $start_date );
					$end_date          = WC_Subscriptions_Product::get_expiration_date( $product->get_id(), $start_date );

					if ( $trial_end_date > 0 ) {

						$subscription->update_dates( array(
							'trial_end'    => $trial_end_date,
							'next_payment' => $next_payment_date,
							'end'          => $end_date,
						) );
					} else {
						$subscription->update_dates( array(
							'next_payment' => $next_payment_date,
							'end'          => $end_date,
						) );
					}


					if ( $this->get_trial_length( $product, $offer_data ) > 0 ) {
						wc_add_order_item_meta( $subscription_item_id, '_has_trial', 'true' );
					}

					// save trial period for PayPal

					if ( ! empty( $trial_period ) ) {
						$subscription->set_trial_period( $trial_period );


					}

					$subscription->set_payment_method( $order->get_payment_method() );
					$subscription->set_payment_method_title( $order->get_payment_method_title() );

					if ( ! empty( $current_user_id ) ) {
						$subscription->update_meta_data( '_customer_user', $current_user_id );
					}
					$subscription->calculate_totals();

					if ( 'completed' === $status ) {
						$subscription->payment_complete( $transaction_id );

					} else {
						$subscription->update_status( $status );
					}
					$subscription->save();


					return $subscription;
				}
			} catch ( Exception $ex ) {
				WFOCU_Core()->log->log( 'An exception found during subscription ' . $ex->getMessage() );

				if ( isset( $subscription ) ) {
					return $subscription;
				}
			}

			return false;
		}

		/**
		 * Get current Subscription status
		 *
		 * @param WC_Order $order
		 */
		public function get_subscription_status( $order ) {
			$get_payment_method = $order->get_payment_method();

			if ( in_array( $get_payment_method, [ 'bacs', 'cheque' ], true ) ) {
				return 'on-hold';
			}

			return 'completed';
		}

		/**
		 * Hooked over `wfocu_offer_payment_failed_event`
		 * Create a Pending subscription of a pending order when upsell fails.
		 *
		 * @param $args
		 */
		public function create_pending_subscription( $args ) {


			$subscription_order = $args['_failed_order'];
			$user_created       = null;
			$get_package        = WFOCU_Core()->data->get( '_upsell_package' );

			foreach ( $get_package['products'] as $product ) {

				$get_product = wc_get_product( $product['id'] );
				if ( $get_product instanceof WC_Product && ( $get_product->get_type() === 'subscription' || $get_product->get_type() === 'subscription_variation' || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {
					if ( is_user_logged_in() ) {
						$user_id = $subscription_order->get_user_id();
					} else {
						$user_id      = ( null === $user_created ) ? WFOCU_Common::create_new_customer( WFOCU_WC_Compatibility::get_order_data( $subscription_order, 'billing_email' ), $subscription_order ) : $user_created;
						$user_created = $user_id;
						$subscription_order->set_customer_id( $user_id );
						$subscription_order->save();
					}

					$args = array(
						'product'          => $get_product,
						'order'            => $subscription_order,
						'user_id'          => $user_id,
						'transaction_id'   => '',
						'amt'              => $product['price'],
						'_recurring_price' => $product['_recurring_price'],

					);

					$this->_create_new_subscription( $args, 'pending', $product );
				}
			}
		}

		public function maybe_create_new_subscriptions_on_new_order( $new_order ) {

			$subscription_order = $new_order;
			$user_created       = null;
			$get_package        = WFOCU_Core()->data->get( '_upsell_package' );
			foreach ( $get_package['products'] as $product ) {

				$get_product = wc_get_product( $product['id'] );
				if ( $get_product instanceof WC_Product && ( $get_product->get_type() === 'subscription' || $get_product->get_type() === 'subscription_variation' || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {
					if ( is_user_logged_in() ) {
						$user_id = $subscription_order->get_user_id();
					} else {
						$user_id      = ( null === $user_created ) ? WFOCU_Common::create_new_customer( WFOCU_WC_Compatibility::get_order_data( $subscription_order, 'billing_email' ), $subscription_order ) : $user_created;
						$user_created = $user_id;
						$subscription_order->set_customer_id( $user_id );
						$subscription_order->save();
					}

					$args = array(
						'product'          => $get_product,
						'order'            => $subscription_order,
						'user_id'          => $user_id,
						'transaction_id'   => '',
						'amt'              => $product['price'],
						'_recurring_price' => $product['_recurring_price'],
						'add_shipping'     => true
					);

					$subscription = $this->_create_new_subscription( $args, 'pending', $product );
					if ( false !== $subscription ) {
						do_action( 'wfocu_subscription_created_for_upsell', $subscription, $product['hash'], $subscription_order );
					}
				}
			}
		}


		/**
		 * Parse price html for the subscription product and fetch subscription info from it.
		 *
		 * @param $html
		 * @param $price
		 *
		 * @return mixed
		 */
		public function parse_subscription_price_from_price_html( $html, $price ) {

			$html = str_replace( $price, '', $html );

			return $html;
		}

		/**
		 * Adds subscription prices in the variation array
		 *
		 * @param $prices_array
		 * @param $variation
		 *
		 * @return mixed
		 */
		public function maybe_add_variable_subscription_prices( $output, $offer_data, $is_front ) {

			if ( true === $is_front && $this->offer_contains_subscription( $output->products ) ) {

				if ( true === $offer_data->settings->is_override_free_trial && ! empty( $offer_data->settings->free_trial_length ) ) {
					$this->add_filters_for_trial( $offer_data );
				}

				foreach ( $output->products as &$product ) {
					if ( is_a( $product->data, 'WC_Product' ) && ( WC_Subscriptions_Product::is_subscription( $product->data ) || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {

						if ( is_a( $product->data, 'WC_Product_Variable_Subscription' ) ) {

							foreach ( $product->variations_data['prices'] as $variation_id => &$price_data ) {
								$variation = ( $product->variations_data['variation_objects'][ $variation_id ] );

								$price_data['subscription_str'] = $this->parse_subscription_price_from_price_html( WC_Subscriptions_Product::get_price_string( $variation, array(
									'price'       => wc_price( $price_data['price_incl_tax'] ),
									'sign_up_fee' => false,
								) ), wc_price( $price_data['price_incl_tax'] ) );

							}
						}
					}
				}
				$this->remove_filters_for_trial();
			}

			return $output;
		}

		/**
		 * Iterate over the offer products & check if the offer contains any subscription products
		 *
		 * @param $offer_build_products
		 *
		 * @return boolean
		 */
		public function offer_contains_subscription( $offer_build_products ) {

			foreach ( $offer_build_products as $product ) {

				if ( is_a( $product->data, 'WC_Product' ) && ( WC_Subscriptions_Product::is_subscription( $product->data->get_id() ) || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {
					return true;

				}
			}

			return false;
		}

		public function render_js() {
			?>
            <script>

                (function ($) {
                    'use strict';

                    $(document).ready(function () {
                        $(document).on('wfocu_populatePrices', function (event, price, regPrice, key, variationPrices, variationID, Bucket) {
                            if (typeof variationPrices[variationID]['subscription_str'] === 'undefined') {
                                $('.wfocu_variable_price_sale[data-key="' + key + '"] .subscription-details').remove();
                            }


                            if (typeof variationPrices[variationID].free_trial_length !== "undefined" && 0 !== parseInt(variationPrices[variationID].free_trial_length)) {


                                $("span.wfocu_variable_price_sale[data-key='" + key + "']").html(Bucket.formatMoney(0)).show();

                            }


                            if (typeof variationPrices[variationID]['signup_fee'] !== "undefined" && 0 < variationPrices[variationID]['signup_fee']) {
                                $('.signup_details_wrap[data-key="' + key + '"] .amount').remove();
                                $('.signup_details_wrap[data-key="' + key + '"] .rec_price').remove();

                                /**
                                 * Prepare the new string to append
                                 * @type {string}
                                 */
                                let str = '<span class="rec_price">' + Bucket.formatMoney(variationPrices[variationID]['signup_fee']) + '</span>';

                                $(str).insertAfter('.signup_details_wrap[data-key="' + key + '"] .signup_price_label');
                                /**
                                 * Show the recurring price element
                                 */
                                $('.signup_details_wrap[data-key="' + key + '"]').show();

                            } else {
                                /**
                                 * hide recurring details section in this case from bottom
                                 */
                                $('.signup_details_wrap[data-key="' + key + '"]').hide();
                            }

                            var PriceOnRecurring = 0;
                            if (true === Bucket.globalVars.offer_data.settings.subscription_discount) {
                                PriceOnRecurring = price;
                            } else {
                                PriceOnRecurring = regPrice;
                            }
                            /**
                             * Clear all the previous prices and subscription price string from the head
                             */
                            $('.recurring_details_wrap[data-key="' + key + '"] .amount').remove();
                            $('.recurring_details_wrap[data-key="' + key + '"] .rec_price').remove();
                            $('.recurring_details_wrap[data-key="' + key + '"] .subscription-details').remove();


                            /**
                             * Prepare the new string to append
                             * @type {string}
                             */
                            let str = '<span class="rec_price">' + Bucket.formatMoney(PriceOnRecurring) + '</span>';
                            str = str + variationPrices[variationID]['subscription_str'];
                            $(str).insertAfter('.recurring_details_wrap[data-key="' + key + '"] .recurring_price_label');


                            /**
                             * Show the recurring price element
                             */
                            $('.recurring_details_wrap[data-key="' + key + '"]').show();


                        });

                        wfocuCommons.addFilter('wfocu_additem_price', function (price, key, variationID) {
                            if ('' === variationID) {

                                if (typeof wfocu_vars.offer_data.products[key] === 'undefined') {
                                    return price;
                                }

                                if (typeof wfocu_vars.offer_data.products[key].free_trial_length === 'undefined') {
                                    return price;
                                }

                                if (parseInt(wfocu_vars.offer_data.products[key].free_trial_length) > 0) {

                                    return (wfocu_vars.offer_data.products[key].signup_fee_excluding_tax > 0) ? wfocu_vars.offer_data.products[key].signup_fee_excluding_tax : 0;

                                }
                            } else {
                                if (typeof wfocu_vars.offer_data.products[key] === 'undefined') {
                                    return price;
                                }
                                if (typeof wfocu_vars.offer_data.products[key].variations_data === 'undefined') {
                                    return price;
                                }
                                if (typeof wfocu_vars.offer_data.products[key].variations_data.prices === 'undefined') {
                                    return price;
                                }
                                if (typeof wfocu_vars.offer_data.products[key].variations_data.prices[variationID] === 'undefined') {
                                    return price;
                                }

                                if (parseInt(wfocu_vars.offer_data.products[key].variations_data.prices[variationID].free_trial_length) > 0) {
                                    return (wfocu_vars.offer_data.products[key].variations_data.prices[variationID].signup_fee_excluding_tax > 0) ? parseFloat(wfocu_vars.offer_data.products[key].variations_data.prices[variationID].signup_fee_excluding_tax) : 0;

                                }
                            }
                            return price;
                        });

                        wfocuCommons.addFilter('wfocu_additem_taxes', function (price, key, variationID) {
                            if ('' === variationID) {

                                if (typeof wfocu_vars.offer_data.products[key] === 'undefined') {
                                    return price;
                                }

                                if (typeof wfocu_vars.offer_data.products[key].free_trial_length === 'undefined') {
                                    return price;
                                }

                                if (parseInt(wfocu_vars.offer_data.products[key].free_trial_length) > 0) {

                                    return (wfocu_vars.offer_data.products[key].signup_fee_including_tax > 0) ? wfocu_vars.offer_data.products[key].signup_fee_including_tax - wfocu_vars.offer_data.products[key].signup_fee_excluding_tax : 0;

                                }
                            } else {
                                if (typeof wfocu_vars.offer_data.products[key] === 'undefined') {
                                    return price;
                                }
                                if (typeof wfocu_vars.offer_data.products[key].variations_data === 'undefined') {
                                    return price;
                                }
                                if (typeof wfocu_vars.offer_data.products[key].variations_data.prices === 'undefined') {
                                    return price;
                                }
                                if (typeof wfocu_vars.offer_data.products[key].variations_data.prices[variationID] === 'undefined') {
                                    return price;
                                }

                                if (parseInt(wfocu_vars.offer_data.products[key].variations_data.prices[variationID].free_trial_length) > 0) {
                                    return (wfocu_vars.offer_data.products[key].variations_data.prices[variationID].signup_fee_excluding_tax > 0) ? parseFloat(wfocu_vars.offer_data.products[key].variations_data.prices[variationID].signup_fee_including_tax) - parseFloat(wfocu_vars.offer_data.products[key].variations_data.prices[variationID].signup_fee_excluding_tax) : 0;

                                }
                            }
                            return price;
                        });


                    });

                })
                (jQuery);

                function wfocu_subscription_item_display(index, Bucket) {

                    var variationID = Bucket.getItemDataByIndex(index, '_wfocu_variation');
                    var key = Bucket.items[index];
                    if ('' === variationID) {

                        if (typeof wfocu_vars.offer_data.products[key] === 'undefined') {
                            return '';
                        }

                        if (typeof wfocu_vars.offer_data.products[key].subscription_str === 'undefined') {
                            return '';
                        }

                        return Bucket.formatMoney(wfocu_vars.offer_data.products[key].price_incl_tax_raw) + wfocu_vars.offer_data.products[key].subscription_str;


                    } else {

                        if (typeof wfocu_vars.offer_data.products[key] === 'undefined') {
                            return '';
                        }
                        if (typeof wfocu_vars.offer_data.products[key].variations_data === 'undefined') {
                            return '';
                        }
                        if (typeof wfocu_vars.offer_data.products[key].variations_data.prices === 'undefined') {
                            return '';
                        }
                        if (typeof wfocu_vars.offer_data.products[key].variations_data.prices[variationID] === 'undefined') {
                            return '';
                        }
                        if (typeof wfocu_vars.offer_data.products[key].variations_data.prices[variationID].subscription_str === 'undefined') {
                            return '';
                        }


                        return Bucket.formatMoney(wfocu_vars.offer_data.products[key].variations_data.prices[variationID].price_incl_tax_raw) + wfocu_vars.offer_data.products[key].variations_data.prices[variationID].subscription_str;

                    }
                }
            </script>
			<?php
		}

		public function maybe_register_js_print() {
			add_Action( 'wp_footer', array( $this, 'render_js' ) );
		}

		public function maybe_validate_subscriptions( $result, $offer_build ) {

			if ( false === $result ) {
				return $result;
			}
			if ( new stdClass() === $offer_build->products ) {
				WFOCU_Core()->log->log( 'Offer Validation failed, No Products in offer build ' );

				//no products
				return false;
			}

			if ( $this->offer_contains_subscription( $offer_build->products ) && ! $this->is_current_order_supports_subscriptions() ) {
				WFOCU_Core()->log->log( 'Offer Validation failed, Subscription in the cart & do not have payment gateway supported ' );

				if ( defined( 'WFOCU_Offers::INVALIDATION_NOT_SUPPORT_SUBSCRIPTION' ) ) {
					WFOCU_Core()->template_loader->invalidation_reason = WFOCU_Core()->offers::INVALIDATION_NOT_SUPPORT_SUBSCRIPTION;
					if ( ! empty( WFOCU_Core()->session_db ) && method_exists( WFOCU_Core()->session_db, 'set_skip_id' ) ) {
						WFOCU_Core()->session_db->set_skip_id( 10 );
					}

				}

				return false;
			}

			return $result;

		}

		/**
		 * Check if parent order supports a subscription or not
		 *
		 * @return bool
		 * @see UpStroke_Subscriptions::maybe_validate_subscriptions()
		 */
		public function is_current_order_supports_subscriptions() {
			$order        = WFOCU_Core()->data->get_current_order();
			$gateway      = $order->get_payment_method();
			$get_gateways = WC()->payment_gateways()->payment_gateways();


			if ( empty( $gateway ) ) {
				/**
				 * if no gateway found in the parent order then try to find if our stripe gateway integration is enabled.
				 */
				$get_stripe_integration = WFOCU_Core()->gateways->get_integration( 'fkwcs_stripe' );
				if ( $get_stripe_integration instanceof WFOCU_Gateway && $get_stripe_integration->supports( 'no-gateway-upsells' ) ) {
					return $get_stripe_integration->is_enabled( $order );

				}
			}


			/**
			 * Check if gateway is ready for subscriptions
			 */
			if ( is_array( $get_gateways ) && isset( $get_gateways[ $gateway ] ) && $this->is_gateway_supports_subscription( $get_gateways[ $gateway ] ) ) {

				/**
				 * Check if our gateway integration is ready for subscriptions
				 */
				if ( false === in_array( $gateway, $this->get_supported_gateways(), true ) ) {
					return false;
				}

				if ( in_array( $gateway, array( 'paypal_express', 'paypal_pro_payflow' ), true ) ) {

					/**
					 * Check if reference transactions are enabled or not.
					 * IF not enabled then return false
					 */
					$is_reference_transaction_on = WFOCU_Core()->data->get_option( 'paypal_ref_trans' );
					if ( 'yes' === $is_reference_transaction_on ) {
						return true;
					} else {
						return false;
					}
				}

				return true;
			}


			return false;
		}

		/**
		 * Checks whether the current gateway supports subscription or not.
		 *
		 * @param WC_Payment_Gateway $gateway
		 */
		public function is_gateway_supports_subscription( $gateway ) {
			$accept_manual_renewals = ( 'no' !== get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no' ) );
			/**
			 * If support manual renewals then all gateways supports subscriptions
			 */
			if ( $accept_manual_renewals ) {
				return true;
			}

			return $gateway->supports( 'subscriptions' );

		}

		public function get_supported_gateways() {
			return apply_filters( 'wfocu_subscriptions_get_supported_gateways', [
				'wfocu_test',
				'bacs',
				'cheque',
				'cod',
				'stripe',
				'paypal',
				'ppec_paypal',
				'paypal_express',
				'authorize_net_cim_credit_card',
				'braintree_paypal',
				'braintree_credit_card',
				'woocommerce_payments',
				'ppcp-gateway',
			] );
		}

		public function maybe_add_signup_fee( $output, $offer_data, $is_front ) {

			if ( true === $is_front && $this->offer_contains_subscription( $output->products ) ) {
				foreach ( $output->products as $hash => &$product ) {
					if ( is_a( $product->data, 'WC_Product' ) && ( WC_Subscriptions_Product::is_subscription( $product->data ) || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {

						if ( is_a( $product->data, 'WC_Product_Variable_Subscription' ) ) {

							foreach ( $product->variations_data['prices'] as $variation_id => &$price_data ) {
								$sign_up_fee                         = WC_Subscriptions_Product::get_sign_up_fee( $product->variations_data['variation_objects'][ $variation_id ] );
								$sign_up_fee_excl_tax                = wcs_get_price_excluding_tax( $product->variations_data['variation_objects'][ $variation_id ], array(
									'price' => $sign_up_fee,
								) );
								$sign_up_fee_incl_tax                = wcs_get_price_including_tax( $product->variations_data['variation_objects'][ $variation_id ], array(
									'price' => $sign_up_fee,
								) );
								$sign_up_fee                         = WC_Subscriptions_Product::get_sign_up_fee( $product->variations_data['variation_objects'][ $variation_id ] );
								$free_trial                          = $this->get_trial_length( $product->variations_data['variation_objects'][ $variation_id ], $offer_data, $product );
								$variation_settings                  = new stdClass();
								$variation_settings->quantity        = $product->quantity;
								$variation_settings->discount_type   = $product->discount_type;
								$variation_settings->discount_amount = $offer_data->variations->{$hash}[ $variation_id ]->discount_amount;

								if ( true === $offer_data->settings->subscription_signup_discount ) {
									$price_data['signup_fee_including_tax'] = WFOCU_Common::apply_discount( $sign_up_fee_incl_tax, $variation_settings );
									$price_data['signup_fee_excluding_tax'] = WFOCU_Common::apply_discount( $sign_up_fee_excl_tax, $variation_settings );

								} else {
									$price_data['signup_fee_including_tax'] = $sign_up_fee_incl_tax;
									$price_data['signup_fee_excluding_tax'] = $sign_up_fee_excl_tax;

								}

								$price_data['regular_price_excl_tax']     += $price_data['signup_fee_excluding_tax'];
								$price_data['regular_price_incl_tax']     += $price_data['signup_fee_including_tax'];
								$price_data['price_excl_tax']             += $price_data['signup_fee_excluding_tax'];
								$price_data['price_incl_tax']             += $price_data['signup_fee_including_tax'];
								$price_data['sale_modify_price_excl_tax'] += $price_data['signup_fee_excluding_tax'];
								$price_data['sale_modify_price_incl_tax'] += $price_data['signup_fee_including_tax'];
								$price_data['free_trial_length']          = $free_trial;
								$price_data['free_trial_period']          = $this->get_trial_period( $product->variations_data['variation_objects'][ $variation_id ], $offer_data, $product );
								$price_data['signup_fee']                 = $sign_up_fee;

							}
						} else {
							$sign_up_fee          = WC_Subscriptions_Product::get_sign_up_fee( $product->data );
							$sign_up_fee_excl_tax = wcs_get_price_excluding_tax( $product->data, array(
								'price' => $sign_up_fee,
							) );
							$sign_up_fee_incl_tax = wcs_get_price_including_tax( $product->data, array(
								'price' => $sign_up_fee,
							) );

							$free_trial = $this->get_trial_length( $product->data, $offer_data, $product );

							if ( true === $offer_data->settings->subscription_signup_discount ) {
								$product->signup_fee_including_tax = WFOCU_Common::apply_discount( $sign_up_fee_incl_tax, $offer_data->fields->{$hash} );
								$product->signup_fee_excluding_tax = WFOCU_Common::apply_discount( $sign_up_fee_excl_tax, $offer_data->fields->{$hash} );

							} else {
								$product->signup_fee_including_tax = $sign_up_fee_incl_tax;
								$product->signup_fee_excluding_tax = $sign_up_fee_excl_tax;

							}

							if ( isset( $product->price ) ) {
								$product->price += $product->signup_fee_including_tax;
							}
							if ( isset( $product->regular_price_excl_tax ) ) {
								$product->regular_price_excl_tax += $sign_up_fee;
							}
							if ( isset( $product->regular_price_incl_tax ) ) {
								$product->regular_price_incl_tax += $sign_up_fee;
							}
							if ( isset( $product->sale_price_incl_tax ) ) {
								$product->sale_price_incl_tax        += $product->signup_fee_including_tax;
								$product->sale_modify_price_incl_tax += $product->signup_fee_including_tax;
							}
							if ( isset( $product->sale_price_excl_tax ) ) {
								$product->sale_price_excl_tax        += $product->signup_fee_excluding_tax;
								$product->sale_modify_price_excl_tax += $product->signup_fee_excluding_tax;
							}

							$product->free_trial_length = $free_trial;
							$product->free_trial_period = $this->get_trial_period( $product->data, $offer_data, $product );

							if ( isset( $product->sale_price_incl_tax ) && isset( $product->sale_price_excl_tax ) ) {
								$product->tax = $product->sale_price_incl_tax - $product->sale_price_excl_tax;
							}

						}
					}
				}
			}

			return $output;
		}

		public function maybe_set_paypal_profile( $subscription, $key ) {
			$get_profile_ids = WFOCU_Core()->data->get( '_profile_ids', array(), 'paypal' );
			if ( empty( $get_profile_ids ) || false === isset( $get_profile_ids[ $key ] ) ) {
				return;
			}

			wcs_set_paypal_id( $subscription, $get_profile_ids[ $key ] );
			wcs_set_objects_property( $subscription, '_wfocu_paypal_subscription', 'yes', 'save' );

		}

		public function maybe_handle_paypal_ipn_on_subscriptions( $transaction_details ) {
			$use_sandbox = ( 'yes' === WCS_PayPal::get_option( 'testmode' ) ) ? true : false;

			if ( version_compare( WC_Subscriptions::$version, '7.5.0', '>=' ) ) {
			    require_once plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'includes/core/gateways/paypal/includes/class-wcs-paypal-standard-ipn-handler.php';
			} elseif ( version_compare( WC_Subscriptions::$version, '4.0.0', '>=' ) ) {
			    require_once plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'vendor/woocommerce/subscriptions-core/includes/gateways/paypal/includes/class-wcs-paypal-standard-ipn-handler.php';
			} else {
			    require_once plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'includes/gateways/paypal/includes/class-wcs-paypal-standard-ipn-handler.php';
			}

			require_once 'gateways/class-wfocu-wcs-paypal-standard-ipn-handler.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath

			$ipn_handler = new WCS_WFOCU_Paypal_Standard_IPN_Handler( $use_sandbox, WCS_PayPal::get_option( 'receiver_email' ) );
			WFOCU_Core()->log->log( 'PayPal IPN Response: ' . print_R( $transaction_details, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$ipn_handler->valid_response( $transaction_details );
		}

		public function maybe_render_assets() {

			if ( false === class_exists( 'WFOCU_Common' ) ) {
				return;
			}
			if ( true === WFOCU_Common::is_load_admin_assets( 'builder' ) ) {

				wp_enqueue_script( 'wfocu_subscription_admin_script', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'wfocu-admin-builder' ), time() );

			}
		}

		public function update_upsell_package( $package ) {

			if ( empty( $package['products'] ) ) {
				return $package;
			}
			$get_offer_data = WFOCU_Core()->data->get( '_current_offer', '' );
			if ( $this->is_package_contains_subscription( $package ) ) {
				foreach ( $package['products'] as &$products ) {
					$product_object = $products['data'];
					$_offer_data    = $products['_offer_data'];
					$hash           = $products['hash'];
					if ( is_a( $product_object, 'WC_Product' ) && ( WC_Subscriptions_Product::is_subscription( $product_object->get_id() ) || apply_filters( 'wfocu_force_subscription_product', false, $products ) ) ) {

						if ( isset( $get_offer_data->settings->subscription_discount ) && true === $get_offer_data->settings->subscription_discount ) {

							if ( isset( $get_offer_data->variations->{$hash} ) ) {
								$discount_amount = $get_offer_data->variations->{$hash}[ $product_object->get_id() ]->discount_amount;
							} else {
								$discount_amount = $_offer_data->discount_amount;
							}

							$variation_settings                  = new stdClass();
							$variation_settings->quantity        = $_offer_data->quantity;
							$variation_settings->discount_type   = $_offer_data->discount_type;
							$variation_settings->discount_amount = $discount_amount;

							$amount = WFOCU_Core()->offers->get_product_price( $product_object, $variation_settings, wc_prices_include_tax(), $_offer_data );

						} else {
							$amount = wc_prices_include_tax() ? wc_get_price_including_tax( $product_object ) : wc_get_price_excluding_tax( $product_object );
						}

						$products['_recurring_price'] = apply_filters( 'wfocu_customize_recurring_price', $amount, $get_offer_data, $products );
					}
				}
			}

			return $package;
		}

		public function is_package_contains_subscription( $get_package = array() ) {

			if ( empty( $get_package ) ) {
				$get_package = WFOCU_Core()->data->get( '_upsell_package' );
			}

			if ( false === is_array( $get_package ) ) {
				return false;
			}

			foreach ( $get_package['products'] as $products ) {
				$product_object = $products['data'];
				if ( is_a( $product_object, 'WC_Product' ) && ( WC_Subscriptions_Product::is_subscription( $product_object->get_id() ) || apply_filters( 'wfocu_force_subscription_product', false, $products ) ) ) {
					return true;

				}
			}

			return false;

		}

		public function add_subscription_discount_setting( $object ) {

			$object->subscription_discount        = false;
			$object->subscription_signup_discount = false;
			$object->is_override_free_trial       = false;
			$object->free_trial_length            = 0;
			$object->free_trial_period            = 'day';

			return $object;
		}

		public function maybe_modify_visual_price_for_subscriptions( $html, $regular_price_raw, $regular_price, $sale_price_raw, $sale_price, $data ) {

			$product     = $data['product']->data;
			$product_key = $data['key'];
			if ( $product->get_type() === 'variable-subscription' || $product->get_type() === 'subscription_variation' || $product->get_type() === 'subscription' ) {
				ob_start();
				echo $html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$signup_str    = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_signup_price_label' );
				$recurring_str = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_rec_price_label' );
				echo WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . $signup_str . '"}}' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				echo WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string key="' . $product_key . '" recurring_label="' . $recurring_str . '"}}' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$html = ob_get_clean();
			}

			return $html;

		}

		public function print_subscription_details() {
			?>
            <div class="wfocu-oc-subscription-details">
                <# print(wfocu_subscription_item_display(i,data.Bucket)); #>
            </div>
			<?php
		}

		/**
		 * Is the current gateway is paypal gatway we support
		 *
		 * @param $gateway
		 *
		 * @return bool
		 */
		public function is_paypal_gateway( $gateway ) {
			$paypal_gateways = array( 'paypal', 'ppec_paypal', 'paypal_express' );

			return in_array( $gateway, $paypal_gateways, true );
		}

		public function notice_on_paypal_without_ref_transaction() {
			$get_enabled_gateways       = WFOCU_Core()->data->get_option( 'gateways' );
			$get_ref_transaction_status = WFOCU_Core()->data->get_option( 'paypal_ref_trans' );

			if ( is_array( $get_enabled_gateways ) && ( in_array( 'paypal', $get_enabled_gateways, true ) || in_array( 'ppec_paypal', $get_enabled_gateways, true ) || in_array( 'paypal_pro_payflow', $get_enabled_gateways, true ) ) ) {
				if ( 'no' === $get_ref_transaction_status ) {
					$this->paypal_on_notice();
				}
			}
		}

		public function paypal_on_notice() {
			?>

            <div class="notice notice-error">
                <p><?php echo wp_kses_post( __( 'UpStroke Notice: For <strong>UpStroke Subscription </strong> to work with  PayPal, Reference Transactions should be enabled for your Paypal Account. Learn how to get Reference Transactions enabled. <br/><br/> Note: If you don\'t have  Reference Transactions enabled for your accounts, UpStroke won\'t trigger funnels having subscription product offers. Falsely, indicating enablement of Reference Transactions will lead to payment failures.', 'woo-funnels-one-click-upsell' ) ); ?>
                    <a target="_blank" href="https://buildwoofunnels.com/docs/upstroke/supported-payment-methods/paypal-reference-transactions/">Learn more about reference transactions</a></p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=upstroke&tab=settings' ) ); ?>" class="button"><?php esc_html_e( 'Go to settings', 'woofunnels-upstroke-power-pack' ); ?></a>
                </p>
            </div>
			<?php
		}

		public function skip_running_funnel_on_renewals( $should_run, $order ) {

			$is_renewal = wcs_order_contains_renewal( $order );

			if ( $is_renewal ) {
				return true;
			}

			return $should_run;
		}

		public function maybe_stop_applying_discount_on_offer( $do_discount, $product = '', $options = array(), $offer_settings = array() ) {
			if ( empty( $product ) ) {
				return $do_discount;
			}

			if ( $product->get_type() === 'subscription_variation' || $product->get_type() === 'variable-subscription' || $product->get_type() === 'subscription' ) {

				$free_trial = $this->get_trial_length( $product, $offer_settings );

				if ( $free_trial && is_object( $offer_settings ) && isset( $offer_settings->settings ) && false === $offer_settings->settings->subscription_discount ) {

					return true;
				}
			}

			return $do_discount;
		}

		public function product_recurring_total_string( $attr, $raw = false ) {

			$data                = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr                = shortcode_atts( array(
				'key'             => 1, //has to be user friendly , user will not understand 12:45 PM (g:i A) (https://codex.wordpress.org/Formatting_Date_and_Time)
				'info'            => 'yes',
				'recurring_label' => __( 'Recurring Total: ', 'woocommerce-subscription' ),
			), $attr );
			$price               = 0;
			$shipping_difference = 0;
			$html                = '';

			if ( ! isset( $data->products ) ) {
				return __return_empty_string();
			}

			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}

			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) ) {

				/**
				 * Shipping
				 */
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && isset( $data->products->{$attr['key']}->shipping ) && is_array( $data->products->{$attr['key']}->shipping ) ) {
					if ( $data->products->{$attr['key']}->shipping['shipping'] && count( $data->products->{$attr['key']}->shipping['shipping'] ) > 0 ) {
						$current      = current( $data->products->{$attr['key']}->shipping['shipping'] );
						$current_cost = (float) $current['cost'] + (float) $current['shipping_tax'];
						$prev_cost    = $data->products->{$attr['key']}->shipping['shipping_prev']['cost'] + $data->products->{$attr['key']}->shipping['shipping_prev']['tax'];

						$shipping_difference = $current_cost - $prev_cost;
					}
				}

				/**
				 * If variable product
				 */
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && $data->products->{$attr['key']}->data instanceof WC_Product && 'variable-subscription' === $data->products->{$attr['key']}->data->get_type() ) {

					$is_show_tax = WFOCU_Core()->funnels->show_prices_including_tax( $data, $attr['key'] );
					if ( true === $is_show_tax ) {
						$variable_price = ( false === $data->settings->subscription_discount ) ? $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['regular_price_incl_tax_raw'] : $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['price_incl_tax_raw'];
					} else {
						$variable_price = ( false === $data->settings->subscription_discount ) ? $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['regular_price_excl_tax_raw'] : $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->variations_data['default'] ]['price_excl_tax_raw'];

					}

					$price = $variable_price + $shipping_difference;

					if ( true === $raw ) {
						return $price;
					}

					if ( $data->products->{$attr['key']}->data->is_type( 'variable-subscription' ) ) {
						if ( isset( $attr['info'] ) && 'yes' === $attr['info'] ) {
							$get_default_variation_object = $data->products->{$attr['key']}->variations_data['variation_objects'][ $data->products->{$attr['key']}->default_variation ];


							if ( true === $data->settings->is_override_free_trial && ! empty( $data->settings->free_trial_length ) ) {
								$this->add_filters_for_trial( $data );
								$price = WC_Subscriptions_Product::get_price_string( $get_default_variation_object, array(
									'price'       => wc_price( $price ),
									'sign_up_fee' => false,
								) );
								$this->remove_filters_for_trial();
							} else {
								$price = WC_Subscriptions_Product::get_price_string( $get_default_variation_object, array(
									'price'       => wc_price( $price ),
									'sign_up_fee' => false,
								) );
							}


							$html = '';
							if ( ! empty( $attr['recurring_label'] ) ) {
								$html = '<div class="recurring_details_wrap" data-key="' . $attr['key'] . '"><span class="recurring_price_label">' . $attr['recurring_label'] . '</span>' . $price . '</div>';

							}

							return $html;

						} else {
							return __return_empty_string();
						}
					} else {
						return __return_empty_string();

					}

					return sprintf( '<span class="wfocu_variable_price_sale" data-key="%s" data-info="%s">%s</span>', $attr['key'], $attr['info'], $price );
				}
			}

			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && ( $data->products->{$attr['key']}->data->is_type( 'subscription' ) || $data->products->{$attr['key']}->data->is_type( 'subscription_variation' ) ) ) {

				$is_show_tax = WFOCU_Core()->funnels->show_prices_including_tax( $data, $attr['key'] );

				if ( true === $is_show_tax ) {

					$price = ( false === $data->settings->subscription_discount ) ? $data->products->{$attr['key']}->regular_price : $data->products->{$attr['key']}->sale_price_incl_tax;
				} else {
					$price = ( false === $data->settings->subscription_discount ) ? $data->products->{$attr['key']}->regular_price_excl_tax : $data->products->{$attr['key']}->sale_price_excl_tax;
				}

				if ( ! empty( $data->products->{$attr['key']}->signup_fee_including_tax ) && true === $data->settings->subscription_discount ) {
					if ( true === $is_show_tax ) {

						$price = $data->products->{$attr['key']}->sale_price_incl_tax - $data->products->{$attr['key']}->signup_fee_including_tax;
					} else {
						$price = $data->products->{$attr['key']}->sale_price_excl_tax - $data->products->{$attr['key']}->signup_fee_excluding_tax;
					}
				}

				$price = $price + $shipping_difference;

				if ( true === $data->settings->is_override_free_trial && ! empty( $data->settings->free_trial_length ) ) {
					$this->add_filters_for_trial( $data );
					$price = WC_Subscriptions_Product::get_price_string( $data->products->{$attr['key']}->data, array(
						'price'       => wc_price( $price ),
						'sign_up_fee' => false,
					) );
					$this->remove_filters_for_trial();
				} else {
					$price = WC_Subscriptions_Product::get_price_string( $data->products->{$attr['key']}->data, array(
						'price'       => wc_price( $price ),
						'sign_up_fee' => false,
					) );
				}

				if ( ! empty( $attr['recurring_label'] ) ) {
					$html = '<div class="recurring_details_wrap" data-key="' . $attr['key'] . '"><span class="recurring_price_label">' . $attr['recurring_label'] . '</span>' . $price . '</div>';
				}

				return $html;

			} else {
				return __return_empty_string();

			}
		}

		public function product_signup_fee( $attr ) {
			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr = shortcode_atts( array(
				'key'          => 1,
				'signup_label' => __( 'Signup Fee: ', 'woocommerce-subscription' ),
			), $attr );

			$html = '';

			if ( ! isset( $data->products ) ) {
				return __return_empty_string();
			}

			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}

			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) ) {

				/**
				 * If variable product
				 */
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && $data->products->{$attr['key']}->data instanceof WC_Product && ( 'variable' === $data->products->{$attr['key']}->data->get_type() || 'variable-subscription' === $data->products->{$attr['key']}->data->get_type() ) ) {

					if ( $data->products->{$attr['key']}->data->is_type( 'variable-subscription' ) ) {
						$signup_fee = $data->products->{$attr['key']}->variations_data['prices'][ $data->products->{$attr['key']}->default_variation ]['signup_fee_including_tax'];
						if ( absint( $signup_fee ) > 0 ) {
							$html = '<div class="signup_details_wrap" data-key="' . $attr['key'] . '"><span class="signup_price_label">' . $attr['signup_label'] . '</span>' . wc_price( $signup_fee ) . '</div>';

						} else {
							$html = '<div class="signup_details_wrap" data-key="' . $attr['key'] . '" style="display: none;"><span class="signup_price_label">' . $attr['signup_label'] . '</span></div>';

						}

						return $html;

					} else {
						return __return_empty_string();

					}
				}
			}

			if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) && ( $data->products->{$attr['key']}->data->is_type( 'subscription' ) || $data->products->{$attr['key']}->data->is_type( 'subscription_variation' ) ) ) {

				$signup_fee = $data->products->{$attr['key']}->signup_fee_including_tax;
				if ( absint( $signup_fee ) > 0 ) {
					$html = '<div class="signup_details_wrap" data-key="' . $attr['key'] . '"><span class="signup_price_label">' . $attr['signup_label'] . '</span>' . wc_price( $signup_fee ) . '</div>';

				} else {
					$html = '<div class="signup_details_wrap" data-key="' . $attr['key'] . '" style="display: none;"><span class="signup_price_label">' . $attr['signup_label'] . '</span></div>';

				}

				return $html;

			} else {
				return __return_empty_string();

			}
		}

		public function register_merge_tags( $tags ) {
			array_push( $tags, 'product_recurring_total_string' );
			array_push( $tags, 'product_signup_fee' );

			return $tags;
		}

		public function maybe_add_customizer_fields( $customizer_data ) {

			$products = WFOCU_Core()->template_loader->get_template_ins()->data->products;

			if ( $products ) {
				foreach ( $products as $key => $product ) {

					if ( $product->data->is_type( 'variable-subscription' ) || $product->data->is_type( 'subscription' ) || $product->data->is_type( 'subscription_variation' ) ) {

						$recurring_price_label_field = array(
							'rec_price_label' => array(
								'type'          => 'text',
								'label'         => __( 'Recurring Price Label', 'woofunnels-upstroke-power-pack' ),
								'default'       => __( 'Recurring Total: ', 'woocommerce-subscription' ),
								'transport'     => 'postMessage',
								'priority'      => 111,
								'wfocu_partial' => array(
									'elem' => '.recurring_details_wrap[data-key="' . $key . '"] .recurring_price_label',
								),
							),

						);

						$signup_price_label_field = array(
							'signup_price_label' => array(
								'type'          => 'text',
								'label'         => __( 'Signup Fee Label', 'woofunnels-upstroke-power-pack' ),
								'default'       => __( 'Signup Fee: ', 'woocommerce-subscription' ),
								'transport'     => 'postMessage',
								'priority'      => 111,
								'wfocu_partial' => array(
									'elem' => '.signup_details_wrap[data-key="' . $key . '"] .signup_price_label',
								),
							),

						);

						foreach ( $customizer_data as &$val ) {
							$secion_slug = array_keys( $val );

							if ( 'wfocu_product' !== $secion_slug[0] ) {
								continue;
							}

							$target_arr                                                        = $val[ $secion_slug[0] ]['sections'][ 'product_' . $key ]['fields'];
							$val[ $secion_slug[0] ]['sections'][ 'product_' . $key ]['fields'] = array_merge( $target_arr, $recurring_price_label_field, $signup_price_label_field );

						}
					}
				}
			}

			return $customizer_data;
		}

		public function get_trial_length( $product, $product_args, $product_data = [] ) {

			if ( ! empty( $product_args ) && isset( $product_args->settings ) ) {


				if ( isset( $product_args->settings->is_override_free_trial ) && true === wc_string_to_bool( $product_args->settings->is_override_free_trial ) && ! empty( $product_args->settings->free_trial_length ) ) {
					return $product_args->settings->free_trial_length;
				}
			} else {

				$product_data = $product_args;

				$offer = ( is_array( $product_args ) && isset( $product_args['_offer_data'] ) ) ? $product_args['_offer_data'] : '';

				if ( ! empty( $offer ) && isset( $offer->free_trial_length ) && ! empty( $offer->free_trial_length ) ) {

					return $offer->free_trial_length;

				}
			}


			$trial_length = $this->maybe_subscriptions_value( $product_data, 'trial_length' );
			if ( false !== $trial_length ) {
				return $trial_length;
			}

			return apply_filters( 'wfocu_trial_length', WC_Subscriptions_Product::get_trial_length( $product ), $product, $product_args );
		}


		public function get_trial_period( $product, $product_args, $product_data = [] ) {


			if ( ! empty( $product_args ) && isset( $product_args->settings ) ) {


				if ( isset( $product_args->settings->is_override_free_trial ) && true === wc_string_to_bool( $product_args->settings->is_override_free_trial ) && ! empty( $product_args->settings->free_trial_period ) ) {
					return $product_args->settings->free_trial_period;
				}
			} else {

				$product_data = $product_args;

				$offer = ( is_array( $product_args ) && isset( $product_args['_offer_data'] ) ) ? $product_args['_offer_data'] : '';

				if ( ! empty( $offer ) && isset( $offer->free_trial_period ) && ! empty( $offer->free_trial_period ) ) {

					return $offer->free_trial_period;

				}
			}


			$trial_period = $this->maybe_subscriptions_value( $product_data, 'trial_period' );


			if ( false !== $trial_period ) {
				return $trial_period;
			}

			return apply_filters( 'wfocu_trial_period', WC_Subscriptions_Product::get_trial_period( $product ), $product, $product_args );
		}

		public function get_trial_expiration_date( $product, $from_date = '', $offer_data = [] ) {
			$trial_length = $this->get_trial_length( $product, $offer_data );

			if ( $trial_length > 0 ) {

				if ( empty( $from_date ) ) {
					$from_date = gmdate( 'Y-m-d H:i:s' );
				}

				$trial_expiration_date = gmdate( 'Y-m-d H:i:s', wcs_add_time( $trial_length, $this->get_trial_period( $product, $offer_data ), wcs_date_to_time( $from_date ) ) );

			} else {

				$trial_expiration_date = 0;

			}

			return $trial_expiration_date;
		}

		public function get_first_renewal_payment_date( $product, $offer_data, $from_date = '', $timezone = 'gmt' ) {
			$first_renewal_timestamp = $this->get_first_renewal_payment_time( $product, $offer_data, $from_date, $timezone );

			if ( $first_renewal_timestamp > 0 ) {
				$first_renewal_date = gmdate( 'Y-m-d H:i:s', $first_renewal_timestamp );
			} else {
				$first_renewal_date = 0;
			}

			return apply_filters( 'woocommerce_subscriptions_product_first_renewal_payment_date', $first_renewal_date, $product, $from_date, $timezone );

		}

		public function get_first_renewal_payment_time( $product, $offer_data, $from_date = '', $timezone = 'gmt' ) {
			if ( ! WC_Subscriptions_Product::is_subscription( $product ) && ! apply_filters( 'wfocu_force_subscription_product', false, $offer_data ) ) {
				return 0;
			}

			$from_date_param = $from_date;

			$billing_interval = $this->get_interval( $product, $offer_data );
			$billing_length   = $this->get_length( $product, $offer_data );
			$trial_length     = $this->get_trial_length( $product, $offer_data );

			if ( $billing_interval !== $billing_length || $trial_length > 0 ) {

				if ( empty( $from_date ) ) {
					$from_date = gmdate( 'Y-m-d H:i:s' );
				}

				// If the subscription has a free trial period, the first renewal payment date is the same as the expiration of the free trial
				if ( $trial_length > 0 ) {

					$first_renewal_timestamp = wcs_date_to_time( $this->get_trial_expiration_date( $product, $from_date, $offer_data ) );

				} else {

					$site_time_offset = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

					// As wcs_add_time() calls wcs_add_months() which checks for last day of month, pass the site time
					$period                  = $this->get_period( $product, $offer_data );
					$first_renewal_timestamp = wcs_add_time( $billing_interval, $period, wcs_date_to_time( $from_date ) + $site_time_offset );

					if ( 'site' !== $timezone ) {
						$first_renewal_timestamp -= $site_time_offset;
					}
				}
			} else {
				$first_renewal_timestamp = 0;
			}

			return apply_filters( 'woocommerce_subscriptions_product_first_renewal_payment_time', $first_renewal_timestamp, $product, $from_date_param, $timezone );

		}

		/**
		 * Add filters to modify free trial to show correct prices string on front end
		 * specific to usage
		 *
		 * @param $offer_data
		 */
		public function add_filters_for_trial( $offer_data ) {

			$this->current_offer_data = $offer_data;

			add_filter( 'woocommerce_subscriptions_product_trial_length', array( $this, 'maybe_modifiy_trial_length_for_price_output' ), 10, 2 );

			add_filter( 'woocommerce_subscriptions_product_trial_period', array( $this, 'maybe_modifiy_trial_period_for_price_output' ), 10, 2 );
		}

		/**
		 * remove filters to rollback free trial to native to show correct prices string on front end
		 */
		public function remove_filters_for_trial() {
			$this->current_offer_data = null;
			remove_filter( 'woocommerce_subscriptions_product_trial_length', array( $this, 'maybe_modifiy_trial_length_for_price_output' ), 10, 2 );
			remove_filter( 'woocommerce_subscriptions_product_trial_period', array( $this, 'maybe_modifiy_trial_period_for_price_output' ), 10, 2 );

		}

		/**
		 * @hooked over `woocommerce_subscriptions_product_trial_length`
		 * If free trial override settings are checked, return overridden trial
		 *
		 * @param $trial_length
		 * @param $product
		 *
		 * @return mixed|void
		 */
		public function maybe_modifiy_trial_length_for_price_output( $trial_length, $product ) {

			if ( isset( $this->current_offer_data->settings ) && isset( $this->current_offer_data->settings->is_override_free_trial ) && true === wc_string_to_bool( $this->current_offer_data->settings->is_override_free_trial ) ) {
				if ( ! empty( $this->current_offer_data->settings->free_trial_length ) ) {
					return $this->current_offer_data->settings->free_trial_length;
				}
			}

			return apply_filters( 'wfocu_trial_length', $trial_length, $product, $this->current_offer_data );
		}

		/**
		 * @hooked over `woocommerce_subscriptions_product_trial_period`
		 *
		 * @param $trial_period
		 * @param $product
		 *
		 * @return mixed|void
		 */
		public function maybe_modifiy_trial_period_for_price_output( $trial_period, $product ) {
			if ( isset( $this->current_offer_data->settings ) && isset( $this->current_offer_data->settings->is_override_free_trial ) && true === wc_string_to_bool( $this->current_offer_data->settings->is_override_free_trial ) ) {
				if ( ! empty( $this->current_offer_data->settings->free_trial_period ) ) {
					return $this->current_offer_data->settings->free_trial_period;
				}
			}

			return apply_filters( 'wfocu_trial_period', $trial_period, $product, $this->current_offer_data );
		}

		/**
		 * Is the current product is woocommerce all thing subscription variant
		 *
		 * @param $data
		 *
		 * @return false|mixed
		 */
		public function maybe_subscriptions_value( $data, $key ) {

			if ( is_array( $data ) && isset( $data['args']['variation']['_convert_sub_plan_data'] ) ) {
				$plan_data = json_decode( stripslashes( $data['args']['variation']['_convert_sub_plan_data'] ) );

				return ( ! empty( $plan_data->subscription_scheme ) ) ? $plan_data->subscription_scheme->$key : false;
			}

			return false;

		}

		public function get_period( $product, $offer_data ) {
			$get_period = $this->maybe_subscriptions_value( $offer_data, 'period' );

			return ( false !== $get_period ) ? $get_period : WC_Subscriptions_Product::get_period( $product );
		}

		public function get_interval( $product, $offer_data ) {
			$get_interval = $this->maybe_subscriptions_value( $offer_data, 'interval' );

			return ( false !== $get_interval ) ? $get_interval : WC_Subscriptions_Product::get_interval( $product );
		}

		public function get_length( $product, $offer_data ) {
			$length = $this->maybe_subscriptions_value( $offer_data, 'length' );

			return ( false !== $length ) ? $length : WC_Subscriptions_Product::get_length( $product );
		}


		public function maybe_disallow_free_upsells_on_free_trail( $bool ) {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );
			$order       = WFOCU_Core()->data->get_parent_order();
			if ( in_array( $order->get_payment_method(), [
					'paypal',
					'ppec_paypal',
					'paypal_express'
				], true ) && 'no' === WFOCU_Core()->data->get_option( 'paypal_ref_trans' ) && $this->is_package_contains_subscription( $get_package ) ) {
				return false;
			}

			return $bool;
		}


	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		UpStroke_Subscriptions::get_instance();
	}
}