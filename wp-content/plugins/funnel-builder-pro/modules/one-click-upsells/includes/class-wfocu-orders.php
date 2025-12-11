<?php
if ( ! class_exists( 'WFOCU_Orders' ) ) {
	/**
	 * Create,show,delete,edit and manages the process related to offers in the plugin.
	 * Class WFOCU_Offers
	 */
	class WFOCU_Orders {

		private static $ins = null;
		public $initial_order_status = 'pending';
		public $gatways_do_not_support_payment_complete = array( 'bacs', 'cheque', 'cod' );
		public $order_table_rendered = false;
		public $is_shortcode_output = false;
		public $item_shipping_batch = 0;
		public $temp;

		public function __construct() {

			/**
			 * Register custom order status
			 */
			add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'register_order_status' ), 99, 1 );

			/**
			 * Adding custom order status to WooCommerce native ones
			 */
			add_filter( 'wc_order_statuses', array( $this, 'order_statuses' ), 99, 1 );

			/**
			 * Cron Handler for `fk_fb_every_4_minute`
			 * @see WFOCU_Schedules::maybe_schedule_recurring_events()
			 */
			add_action( 'fk_fb_every_4_minute', array( $this, 'maybe_handle_cron_normalize_stasuses' ) );

			/**
			 * Normalizing the order statuses on funnel end
			 */
			add_action( 'wfocu_funnel_ended_event', array( $this, 'maybe_normalize_order_statuses' ), 10, 2 );

			/**
			 * Handles order status change during primary order
			 */
			add_action( 'wfocu_front_init_funnel_hooks', array( $this, 'register_order_status_to_primary_order' ), 10 );

			/**
			 * Show related orders in thankyou page
			 */
			add_action( 'woocommerce_before_template_part', array( $this, 'maybe_show_related_orders' ), 10, 4 );
			add_action( 'woocommerce_after_template_part', array( $this, 'maybe_show_related_orders' ), 10, 4 );

			/**
			 * Record payment complete action attempt by payment gateway.
			 */
			add_action( 'woocommerce_payment_complete_order_status_wfocu-pri-order', array( $this, 'maybe_record_payment_complete_during_funnel_run' ), 999 );

			/**
			 * Run necessary hooks in order to make payment complete for the order
			 */
			add_action( 'wfocu_after_normalize_order_status', array( $this, 'maybe_run_payment_complete_actions' ), 10 );

			add_shortcode( 'wfocu_order_details_section', array( $this, 'maybe_show_order_details' ) );

			add_action( 'wfocu_before_normalize_order_status', array( $this, 'maybe_detach_increase_stock' ) );

			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'handle_custom_query_var' ), 10, 2 );

			add_action( 'woocommerce_thankyou', array( $this, 'mark_order_as_thankyou_visited' ), 999 );

			add_action( 'fk_fb_every_4_minute', array( $this, 'maybe_execute_thankyou_hook' ), 20 );

			add_action( 'wfocu_db_event_row_created_' . WFOCU_DB_Track::OFFER_ACCEPTED_ACTION_ID, array( $this, 'maybe_add_shipping_item_id_as_meta' ) );
			add_filter( 'woocommerce_order_query_args', [ $this, 'handle_hpos_meta_query' ], 10 );


		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function handle_hpos_meta_query( $query_vars ) {
			if ( ! WFOCU_Common::is_hpos_enabled() ) {
				return $query_vars;
			}

			return $this->handle_custom_query_var( $query_vars, $query_vars );
		}

		/**
		 * Handle a custom 'customvar' query var to get orders with the 'customvar' meta.
		 *
		 * @param array $query - Args for WP_Query.
		 * @param array $query_vars - Query vars from WC_Order_Query.
		 *
		 * @return array modified $query
		 */
		public function handle_custom_query_var( $query, $query_vars ) {
			$query['meta_query'] = $query['meta_query'] ?? array();
			if ( isset( $query_vars['_wfocu_schedule_status'] ) && true === $query_vars['_wfocu_schedule_status'] ) {
				$query['meta_query'][] = array(
					'key'     => '_wfocu_schedule_status',
					'compare' => 'EXISTS',

				);
			}

			if ( isset( $query_vars['_wfocu_status_schedule_for_cb'] ) && true === $query_vars['_wfocu_status_schedule_for_cb'] ) {
				$query['meta_query'][] = array(
					'key'     => '_wfocu_status_schedule_for_cb',
					'compare' => 'EXISTS',

				);
			}

			if ( isset( $query_vars['_wfocu_pending_mails'] ) && true === $query_vars['_wfocu_pending_mails'] ) {
				$query['meta_query'][] = array(
					'key'     => '_wfocu_pending_mails',
					'compare' => 'EXISTS',

				);
			}

			if ( isset( $query_vars['_wfocu_version'] ) ) {
				$query['meta_query'][] = array(
					'key'     => '_wfocu_version',
					'value'   => $query_vars['_wfocu_version'],
					'compare' => '>=',

				);
			}

			return $query;
		}

		/**
		 * @hooked into `woocommerce_register_shop_order_post_statuses`
		 *
		 * @param $status
		 *
		 * @return mixed
		 */
		public function register_order_status( $status ) {
			$get_all_global_options = get_option( 'wfocu_global_settings' );
			if ( is_array( $get_all_global_options ) && isset( $get_all_global_options['primary_order_status_title'] ) && ! empty( $get_all_global_options['primary_order_status_title'] ) ) {
				$get_title = $get_all_global_options['primary_order_status_title'];
			} else {
				$get_title = _x( 'Primary Order Accepted', 'Order status', 'woofunnels-upstroke-one-click-upsell' );
			}
			$status['wc-wfocu-pri-order'] = array(
				'label'                     => $get_title,
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( $get_title . ' <span class="count">(%s)</span>', $get_title . ' <span class="count">(%s)</span>' ),
			);

			return $status;
		}


		/**
		 * @hooked into `wc_order_statuses`
		 *
		 * @param $status
		 *
		 * @return mixed
		 */
		public function order_statuses( $status ) {

			$status['wc-wfocu-pri-order'] = WFOCU_Core()->data->get_option( 'primary_order_status_title' );

			return $status;
		}

		/**
		 * @hooked into `wfocu_front_init_funnel_hooks`
		 * Register filter to modify payment_complete_order_status to our custom status
		 * WC_Order @param $order
		 */
		public function register_order_status_to_primary_order( $order ) {

			if ( false === is_a( $order, 'WC_Order' ) ) {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': No valid order' . __FUNCTION__ );

				return;
			}
			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;

			if ( false === $is_batching_on ) {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': Avoid changing the order ' . __FUNCTION__ );

				return;
			}

			add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'maybe_set_completed_order_status' ), 999, 3 );

		}

		/**
		 * @hooked into `woocommerce_payment_complete_order_status`
		 *
		 * @param string $status
		 * @param string $id
		 * @param WC_Order $order
		 *
		 * @return string
		 */
		public function maybe_set_completed_order_status( $status, $id, $order ) {
			if ( false === is_a( $order, 'WC_Order' ) ) {
				WFOCU_Core()->log->log( 'Order #' . $id . ': No valid order' . __FUNCTION__ );

				return $status;
			}

			/**
			 * Removing our filter, as Sometimes there may be chances that as we modify order status, 3rd party plugin try to manage order statsues on the change of the subscription status
			 * @see WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment()
			 * This function tries to run `woocommerce_payment_complete_order_status` again and this could be the reason for the wrong order status save in our cookies.
			 */
			remove_filter( 'woocommerce_payment_complete_order_status', array( $this, 'maybe_set_completed_order_status' ), 999 );

			do_action( 'wfocu_front_primary_order_status_change', 'wc-wfocu-pri-order', $status, $order );

			return 'wfocu-pri-order';

		}

		/**
		 * @hooked into `wfocu_funnel_ended_event`
		 *
		 * @param $funnel_id
		 * @param $funnel_key
		 * @param int $order_id
		 *
		 * @see WFOCU_Orders::normalize_order_statuses()
		 *
		 */
		public function maybe_normalize_order_statuses( $funnel_id, $order_id ) {

			$old_status     = WFOCU_Core()->data->get( 'porder_status' );
			$initial_status = WFOCU_Core()->data->get( 'sorder_status' );

			$this->normalize_order_statuses( $order_id, $old_status, $initial_status );
		}

		/**
		 * @hooked into cron action `fk_fb_every_4_minute`
		 * Checks for the order status and if the order status is `wc-wfocu-pri-order`
		 * Switch the status to the valid one
		 *
		 * @param int $order_id order id in the process
		 * @param string $order_status order status to move order to
		 * @param string $initial_status order status as bridge status to move order to initial_status and then successful one
		 *
		 * @see WFOCU_Orders::maybe_normalize_order_statuses()
		 *
		 */
		public function normalize_order_statuses( $order_id, $order_status, $initial_status = 'pending' ) {

			$order = wc_get_order( $order_id );

			if ( false === is_a( $order, 'WC_Order' ) ) {
				WFOCU_Core()->log->log( 'Order #' . $order_id . ': No valid order' . __FUNCTION__ );

				return;
			}

			$get_status = WFOCU_WC_Compatibility::get_order_status( $order );

			if ( 'wc-wfocu-pri-order' !== $get_status ) {
				return;
			}

			WFOCU_Core()->log->log( 'Order # ' . $order_id . ': Normalizing Order stasuses with hook:' . current_action() );
			/**
			 * Allowing hooks to register before we apply the initial order status i.e. pending by default
			 */
			do_action( 'wfocu_before_normalize_order_status' );

			/**
			 * This is the status we will apply to the order as we need other plugin (and woocommerce) to work smoothly
			 * 3rd party plugins as well as woocommerce uses status transition hooks (eg: order_status_changed_from_pending_to_processing) to accomplish some of the tasks.
			 * By first moving to pending from our custom status we ensure the above mentioned functionality
			 */
			$midway_status = apply_filters( 'wfocu_mail_initial_status', $initial_status );

			$order->update_status( $midway_status );
			if ( 'completed' === $order_status ) {
				$order_status = apply_filters( 'woocommerce_payment_complete_order_status', $order->needs_processing() ? 'processing' : 'completed', $order_id, $order );

			}
			$order_status = apply_filters( 'wfocu_front_order_status_after_funnel', $order_status, $order );

			/**
			 * Moving forward and allowing plugin to unhook/hook according to the situation
			 * In this process we move to the actual successful status we already contained
			 */
			do_action( 'wfocu_before_normalize_order_status_to_successful', $midway_status, $order_status );

			$order->update_status( $order_status );

			do_action( 'wfocu_after_normalize_order_status', $order, $order_status, current_action() );

			$order->delete_meta_data( '_wfocu_schedule_status' );
			$order->save_meta_data();

			WFOCU_Core()->log->log( 'Order # ' . $order_id . ': ' . $get_status . ' -> ' . $midway_status . '->' . $order_status );
		}

		public function maybe_handle_cron_normalize_stasuses() {

			WFOCU_Common::$start_time = time();

			$get_orders = WFOCU_Common::wc_get_orders( array(
				'status'    => 'wfocu-pri-order',
				'limit'     => 100,
				'post_type' => 'shop_order',
			), [ 'key' => '_wfocu_schedule_status', 'value' => true ] );

			$i       = 0;
			$get_ttl = WFOCU_Core()->data->get_option( 'ttl_funnel' );

			if ( ! empty( $get_orders ) ) {

				do {

					if ( ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) ) {
						// Batch limits reached.
						break;
					}
					$order             = $get_orders[ $i ];
					$get_schedule_meta = $order->get_meta( '_wfocu_schedule_status', true );

					list( $status, $source_status, $time ) = array_values( $get_schedule_meta );

					/**
					 * check if the funnel end time reached or not
					 */
					if ( $time + ( MINUTE_IN_SECONDS * $get_ttl ) <= time() ) {
						$this->normalize_order_statuses( $order->get_id(), $status, $source_status );
					}

					unset( $get_orders[ $i ] );
					$i ++;
				} while ( ! ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) && ! empty( $get_orders ) );
			}
		}

		public function maybe_execute_thankyou_hook() {
			WFOCU_Common::$start_time = time();


			add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'wfocu_execute_thankyou_order_status' ) );
			global $wpdb;
			$statuses_to_query = wc_get_is_paid_statuses();
			$stasuses_in       = implode( ',', array_map( function ( $a ) {
				return "'wc-{$a}'";
			}, $statuses_to_query ) );
			remove_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'wfocu_execute_thankyou_order_status' ) );
			// @codingStandardsIgnoreStart

			if ( WFOCU_Common::is_hpos_enabled() ) {
				$order_table      = $wpdb->prefix . 'wc_orders';
				$order_meta_table = $wpdb->prefix . 'wc_orders_meta';
				$query            = $wpdb->prepare( "SELECT ord.id as ID FROM {$order_table} ord
                                INNER JOIN {$order_meta_table} om ON (ord.id = om.order_id AND om.meta_key = '_wfocu_upsell_abandoned')
                                WHERE ord.type = %s
                                AND ord.status IN ({$stasuses_in}) 
                                ORDER BY ord.date_created_gmt DESC LIMIT 0, 100", 'shop_order' );

			} else {
				$query = $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p
                                INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_wfocu_upsell_abandoned')
                                WHERE p.post_type = %s
                                AND p.post_status IN ({$stasuses_in}) 
                                ORDER BY p.post_date DESC LIMIT 0, 100", 'shop_order' );
			}

			$query_results = $wpdb->get_results( $query );

			// @codingStandardsIgnoreEnd

			if ( ! empty( $query_results ) && is_array( $query_results ) ) {
				$get_orders = array_map( function ( $query_instance ) {
					return wc_get_order( $query_instance->ID );
				}, $query_results );

				$i       = 0;
				$get_ttl = WFOCU_Core()->data->get_option( 'ttl_funnel' );
				if ( ! empty( $get_orders ) ) {

					do {
						if ( ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) ) {
							// Batch limits reached.
							break;
						}
						$order = $get_orders[ $i ];
						$time  = $order->get_date_created()->getTimestamp();
						/**
						 * check if the funnel end time reached or not
						 */
						if ( $time + ( MINUTE_IN_SECONDS * $get_ttl ) <= time() ) {

							remove_action( 'woocommerce_thankyou', array( WFOCU_Core()->public, 'maybe_log_thankyou_visited' ), 999 );

							try {
								do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
								do_action( 'woocommerce_thankyou', $order->get_id() );

							} catch ( Error $e ) {
								/**
								 * We only placed this code here to silently capture the PHP errors during thankyou hook running
								 */
							}

						}

						unset( $get_orders[ $i ] );
						$i ++;
					} while ( ! ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) && ! empty( $get_orders ) );
				}
			}


			/**
			 * We are handling cases of stripe gateway here,
			 * Any order which is stuck in pending status and has stripe gateway as payment method, we will try to complete the payment for that order
			 */
			if ( class_exists( 'FKWCS_Gateway_Stripe' ) ) {
				WFOCU_Common::$start_time = time();

				// @codingStandardsIgnoreStart

				if ( WFOCU_Common::is_hpos_enabled() ) {
					$order_table      = $wpdb->prefix . 'wc_orders';
					$order_meta_table = $wpdb->prefix . 'wc_orders_meta';
					$query            = $wpdb->prepare( "SELECT ord.id as ID FROM {$order_table} ord
                                INNER JOIN {$order_meta_table} om ON (ord.id = om.order_id AND om.meta_key = '_fkwcs_webhook_paid')
                                WHERE ord.type = %s
                                ORDER BY ord.date_created_gmt DESC LIMIT 0, 100", 'shop_order' );

				} else {
					$query = $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p
                                INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_fkwcs_webhook_paid')
                                WHERE p.post_type = %s
                                ORDER BY p.post_date DESC LIMIT 0, 100", 'shop_order' );
				}

				$query_results = $wpdb->get_results( $query );

				// @codingStandardsIgnoreEnd
				if ( ! empty( $query_results ) && is_array( $query_results ) ) {

					$get_orders = array_map( function ( $query_instance ) {
						return wc_get_order( $query_instance->ID );
					}, $query_results );

					$i = 0;
					if ( ! empty( $get_orders ) ) {

						do {
							if ( ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) ) {
								// Batch limits reached.
								break;
							}
							$order = $get_orders[ $i ];

							try {


								/**
								 * Delete the metadata straight way to avoid any scenario of processing the order more than once
								 */
								$order->delete_meta_data( '_fkwcs_webhook_paid' );
								$order->save_meta_data();


								/**
								 * If the order is already paid, we should not process it again
								 */
								if ( ! is_null( $order->get_date_paid() ) ) {
									continue;
								}

								/**
								 * @var $gateway FKWCS\Gateway\Stripe\CreditCard
								 */
								$gateway = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ];

								if ( ! $gateway instanceof WC_Payment_Gateway ) {
									continue;
								}
								$intent = $gateway->get_intent_from_order( $order );
								if ( false === $intent ) {
									FKWCS\Gateway\Stripe\Helper::log( " Intent Not Found  - " . $order->get_id() );

									continue;
								}
								if ( method_exists( $gateway, 'handle_intent_success' ) ) {
									FKWCS\Gateway\Stripe\Helper::log( " Upsell schedule Processing order  - " . $order->get_id() );

									$gateway->handle_intent_success( $intent, $order );
								}

							} catch ( Error|Exception $e ) {
								if ( isset( $order ) && $order instanceof WC_Order ) {
									$order->delete_meta_data( '_fkwcs_webhook_paid' );
									$order->save_meta_data();
								}
								FKWCS\Gateway\Stripe\Helper::log( " Upsell schedule Error occurred - " . $e->getMessage() );

							}

							unset( $get_orders[ $i ] );
							$i ++;
						} while ( ! ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) && ! empty( $get_orders ) );
					}
				}
			}


		}

		public function wfocu_execute_thankyou_order_status( $all_status ) {
			if ( is_array( $all_status ) ) {
				array_push( $all_status, 'on-hold' );
			}

			return $all_status;
		}

		/**
		 * Adding product to the order
		 *
		 * @param $package
		 * @param WC_Order $order
		 */
		public function add_products_to_order( $package, $order ) {
			$ids     = array();
			$package = apply_filters( 'wfocu_add_products_to_the_order', $package, $order );
			foreach ( $package['products'] as $product ) {

				$ids[] = $item_id = $order->add_product( wc_get_product( $product['id'] ), $product['qty'], $product['args'] );

				$item_meta = apply_filters( 'wfocu_add_product_to_order_item_meta', [ '_upstroke_purchase' => 'yes' ], $item_id, $product );
				foreach ( $item_meta as $key => $value ) {
					wc_add_order_item_meta( $item_id, $key, $value );
				}
			}

			foreach ( $order->get_shipping_methods() as $item_id => $item ) {

				$this->temp['shipping'][ $item_id ] = $item->get_taxes();
			}

			/**
			 *
			 */
			add_action( 'woocommerce_order_item_shipping_after_calculate_taxes', array( $this, 'maybe_add_ashipping_item_taxes' ) );
			/**
			 * Filter added here to let other snippets modify if to recalculate taxes after batching
			 */
			$order->calculate_totals( apply_filters( 'wfocu_do_calculate_taxes_after_batch', true, $order, $package ) );

			return apply_filters( 'wfocu_added_products_to_the_order', $ids, $order );

		}

		/**
		 *
		 * @param $get_package
		 * @param WC_Order $order
		 */
		public function set_total( $get_package, $order ) {

			$order->set_total( $get_package['total'] );
		}

		/**
		 *
		 * @param $get_package
		 * @param WC_Order $order
		 */
		public function maybe_handle_shipping( $get_package, $order ) {
			if ( is_array( $get_package['shipping'] ) ) {

				$get_shipping_items = $order->get_items( 'shipping' );

				if ( $get_shipping_items && is_array( $get_shipping_items ) ) {

					/**
					 * var WC_Order_Item_Shipping $item_shipping
					 */
					$item_shipping     = current( $get_shipping_items );
					$item_shipping_key = key( $get_shipping_items );
					if ( ! is_null( $get_package['shipping']['value'] ) && ( false === strpos( $get_package['shipping']['value'], 'free_shipping' ) || 'fixed' === $get_package['shipping']['value'] ) ) {

						if ( isset( $get_package['shipping']['override'] ) && 'true' === $get_package['shipping']['override'] ) {
							$item = new WC_Order_Item_Shipping();
							$item->set_props( array(
								'method_title' => $get_package['shipping']['label'],
								'method_id'    => $get_package['shipping']['value'],
								'total'        => $get_package['shipping']['diff']['cost'],
							) );
							$item->save();
							$order->add_item( $item );

							$offer_shipping_items = array();
							if ( isset( $get_package['products'] ) && is_array( $get_package['products'] ) ) {
								foreach ( $get_package['products'] as $prodct ) {
									$offer_shipping_items[] = get_the_title( $prodct['id'] );
								}
							}

							$item_id                   = $item->get_id();
							$this->item_shipping_batch = $item_id;
							$offer_itmes               = implode( ',', $offer_shipping_items );

							wc_add_order_item_meta( $item_id, 'Items', $offer_itmes );

						} else {
							/**
							 * Setting up shipping total of previous + what we just charged in the offer
							 */
							$item_shipping->set_total( $item_shipping->get_total() + $get_package['shipping']['diff']['cost'] );

							$get_taxes = $item_shipping->get_taxes();

							/**
							 * get the taxes and iterate over them to create a new set of taxes to apply to the item
							 */
							if ( is_array( $get_taxes ) && count( $get_taxes ) > 0 ) {
								$taxes = [ 'total' => [] ];
								foreach ( $get_taxes['total'] as $key => $val ) {
									$taxes['total'][ $key ] = $val + $get_package['shipping']['diff']['tax'];
								}

								$item_shipping->set_taxes( $taxes );
								/**
								 * We initially contain the shipping taxes so that we can apply them after recalculatiing order item.
								 * In this case as we are modifying the shipping in between we need to unset the saves taxes value to make impact in this shipping item.
								 */
								if ( isset( $this->temp['shipping'][ $item_shipping->get_id() ] ) ) {
									unset( $this->temp['shipping'][ $item_shipping->get_id() ] );
								}
							}

							$item_shipping->save();
							/**
							 * lets change the items in the shipping item meta so that the WC always show to the customer that upsell product also included in this shipping item
							 */
							$item_id                   = $item_shipping->get_id();
							$this->item_shipping_batch = $item_id;
							$get_items                 = wc_get_order_item_meta( $item_id, 'Items', true );

							$offer_shipping_items = array();
							if ( isset( $get_package['products'] ) && is_array( $get_package['products'] ) ) {
								foreach ( $get_package['products'] as $prodct ) {
									$offer_shipping_items[] = get_the_title( $prodct['id'] );
								}
							}

							$offer_itmes = implode( ',', $offer_shipping_items ) . ',' . $get_items;
							wc_update_order_item_meta( $item_id, 'Items', $offer_itmes );

						}
					} else {

						/**
						 * If we are in this case that means user opted for the free shipping option provided by us.
						 * We have to apply free shipping method to the current order and remove the previous one.
						 */
						$order->remove_item( $item_shipping_key );
						$item = new WC_Order_Item_Shipping();
						$item->set_props( array(
							'method_title' => $get_package['shipping']['label'],
							'method_id'    => $get_package['shipping']['value'],
							'total'        => 0,
						) );
						$item->save();
						$this->item_shipping_batch = $item->get_id();
						$order->add_item( $item );

					}

					/**
					 * @todo handle for local-pickup case for out of shop base address users.
					 * In case of local pickup shipping, taxes were calculated based on shop base address but not users shipping on front end.
					 * But as soon as we run WC_Order::calculate_totals(), WC does not consider local pickup and apply taxes based on customer.
					 * This ends up messing prices in the order.
					 */
					$order->calculate_totals();

					$order->save();
				} else {
					$item = new WC_Order_Item_Shipping();
					$item->set_props( array(
						'method_title' => $get_package['shipping']['label'],
						'method_id'    => $get_package['shipping']['value'],
						'total'        => $get_package['shipping']['diff']['cost'],
					) );
					$item->save();
					$order->add_item( $item );
					$this->item_shipping_batch = $item->get_id();
					$order->calculate_totals();

					$order->save();

					/**
					 * When there is no shipping exists for the parent order we have to add a new method
					 */
					/**
					 * @todo handle this case as we have to allow customer to chosen between the offered shipping methods
					 */

				}

			}

			$this->maybe_add_shipping_address( $order );

		}

		/**
		 *
		 * @param array $package
		 * @param WC_Order $parent
		 * @param WC_Order $new
		 */
		public function maybe_handle_shipping_new_order( $package, $parent, $new ) {
			if ( is_array( $package['shipping'] ) ) {

				$get_shipping_items = $parent->get_items( 'shipping' );

				if ( $get_shipping_items && is_array( $get_shipping_items ) ) {

					if ( 'free_shipping' !== $package['shipping']['value'] || 'fixed' === $package['shipping']['value'] ) {

						$item = new WC_Order_Item_Shipping();
						$item->set_props( array(
							'method_title' => $package['shipping']['label'],
							'method_id'    => $package['shipping']['value'],
							'total'        => $package['shipping']['diff']['cost'],
						) );
						$item->save();
						$new->add_item( $item );
					} else {
						/**
						 * If we are in this case that means user opted for the free shipping option provided by us.
						 * We have to apply free shipping method to the current order and remove the previous one.
						 */

						$item = new WC_Order_Item_Shipping();
						$item->set_props( array(
							'method_title' => $package['shipping']['label'],
							'method_id'    => $package['shipping']['value'],
							'total'        => 0,
						) );
						$item->save();
						$new->add_item( $item );

					}

					/**
					 * @todo handle for local-pickup case for  out of shop base address users.
					 * In case of local pickup shipping, taxes were calculated based on shop base address but not users shipping on front end.
					 * But as soon as we run WC_Order::calculate_totals(), WC does not consider local pickup and apply taxes based on customer.
					 * This ends up messing prices in the order.
					 */
					$new->calculate_totals();

					$new->save();
				} else {
					/**
					 * When there is no shipping exists for the parent order we have to add a new method
					 */
					/**
					 * @todo handle this case as we have to allow customer to chosen between the offered shipping methods
					 */
					$item = new WC_Order_Item_Shipping();
					$item->set_props( array(
						'method_title' => $package['shipping']['label'],
						'method_id'    => $package['shipping']['value'],
						'total'        => $package['shipping']['diff']['cost'],
					) );
					$item->save();
					$new->add_item( $item );
					$new->calculate_totals();

					$new->save();

				}

			}
			$this->maybe_add_shipping_address( $new );
		}


		/**
		 * Updates shipping address for an order, falling back to billing address if shipping details are empty.
		 * If customer exists, tries to fetch address details from customer data as fallback.
		 *
		 * @param WC_Order $order Order object to update
		 *
		 * @return void
		 */
		public function maybe_add_shipping_address( $order ) {
			/**
			 * Retrieve shipping details, falling back to billing details if necessary.
			 */
			if ( true === WFOCU_Core()->process_offer->package_needs_shipping() ) {
				try {
					$country    = $order->get_shipping_country() ?: $order->get_billing_country();
					$state      = $order->get_shipping_state() ?: $order->get_billing_state();
					$city       = $order->get_shipping_city() ?: $order->get_billing_city();
					$postcode   = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
					$add_line_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
					$add_line_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
					$first_name = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
					$last_name  = $order->get_shipping_last_name() ?: $order->get_billing_last_name();
					$company    = $order->get_shipping_company() ?: $order->get_billing_company();

					// Retrieve customer ID
					$customer_id = WFOCU_WC_Compatibility::get_order_data( $order, '_customer_user' );

					if ( $customer_id > 0 ) {
						$customer = new WC_Customer( $customer_id );

						$country    = $country ?: $customer->get_shipping_country() ?: $customer->get_billing_country();
						$state      = $state ?: $customer->get_shipping_state() ?: $customer->get_billing_state();
						$city       = $city ?: $customer->get_shipping_city() ?: $customer->get_billing_city();
						$postcode   = $postcode ?: $customer->get_shipping_postcode() ?: $customer->get_billing_postcode();
						$add_line_1 = $add_line_1 ?: $customer->get_shipping_address_1() ?: $customer->get_billing_address_1();
						$add_line_2 = $add_line_2 ?: $customer->get_shipping_address_2() ?: $customer->get_billing_address_2();
						$first_name = $first_name ?: $customer->get_shipping_first_name() ?: $customer->get_billing_first_name();
						$last_name  = $last_name ?: $customer->get_shipping_last_name() ?: $customer->get_billing_last_name();
						$company    = $company ?: $customer->get_shipping_company() ?: $customer->get_billing_company();
					}

					/**
					 * Set up the shipping details in the order
					 */
					$order->set_shipping_country( $country );
					$order->set_shipping_state( $state );
					$order->set_shipping_city( $city );
					$order->set_shipping_postcode( $postcode );
					$order->set_shipping_address_1( $add_line_1 );
					$order->set_shipping_address_2( $add_line_2 );
					$order->set_shipping_first_name( $first_name );
					$order->set_shipping_last_name( $last_name );
					$order->set_shipping_company( $company );

					$order->save();
				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( 'Error comes during copy billing address into shipping ' . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				}
			}
		}

		/**
		 * @param array $args
		 * @param WC_Order $order_to_inherit
		 *
		 * @return WC_Order
		 */
		public function create_new_order( $args, $order_to_inherit ) {
			$args['basic']           = array();
			$args['basic']['status'] = 'wc-pending';

			$args = wp_parse_args( $args, $this->get_default_order_args() );

			return $this->create_order( $args, $order_to_inherit );

		}

		public function get_default_order_args() {
			return array(
				'basic'    => array(
					'status' => 'pending',
				),
				'products' => array(),

			);
		}

		/**
		 * Create a new order in woocommerce
		 *
		 * @param $args
		 * @param WC_Order $order_to_inherit
		 *
		 * @return bool|WC_Order|WP_Error
		 */
		private function create_order( $args, $order_to_inherit ) {

			if ( ! empty( $order_to_inherit ) ) {
				$parent_order_billing = $order_to_inherit->get_address( 'billing' );

				if ( ! empty( $parent_order_billing['email'] ) ) {
					$customer_id = $order_to_inherit->get_customer_id();
					$order       = new WC_Order();
					$order->set_customer_id( $customer_id );
					$order->set_status( $args['basic']['status'] );

					$args = apply_filters( 'wfocu_add_products_to_the_order', $args, $order );
					foreach ( $args['products'] as $key => $values ) {
						$item                       = new WC_Order_Item_Product();
						$product                    = $values['data'];
						$item->legacy_values        = $values; // @deprecated 4.4.0 For legacy actions.
						$item->legacy_cart_item_key = $key; // @deprecated 4.4.0 For legacy actions.
						$item->set_props( array(
							'quantity'  => isset( $values['qty'] ) ? $values['qty'] : 0,
							'variation' => isset( $values['args']['variation'] ) ? $values['args']['variation'] : array(),
							'subtotal'  => isset( $values['args']['subtotal'] ) ? $values['args']['subtotal'] : 0,
							'total'     => isset( $values['args']['total'] ) ? $values['args']['total'] : 0
						) );

						if ( $product ) {
							$item->set_props( array(
								'name'         => $product->get_name(),
								'tax_class'    => $product->get_tax_class(),
								'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
								'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
							) );
						}

						$item->set_backorder_meta();
						$order->add_item( $item );


						$item_meta = apply_filters( 'wfocu_add_product_to_order_item_meta', [ '_upstroke_purchase' => 'yes' ], $item->get_id(), $values );
						foreach ( $item_meta as $m_key => $m_value ) {
							$item->add_meta_data( $m_key, $m_value, true );
						}


					}
					$order = apply_filters( 'wfocu_new_order_before_calculate', $order, $args, $order_to_inherit );

					$order->save();

					$order->set_address( $order_to_inherit->get_address( 'billing' ), 'billing' );
					$order->set_address( $order_to_inherit->get_address( 'shipping' ), 'shipping' );
					$order->set_created_via( 'upstroke' );

					// set shipping

					$order->set_payment_method( $order_to_inherit->get_payment_method() );
					$order->set_payment_method_title( $order_to_inherit->get_payment_method_title() );

					// reports won't track orders if these values are not set
					if ( ! wc_tax_enabled() ) {
						$order->set_shipping_tax( 0 );
						$order->set_cart_tax( 0 );
					}

					/**
					 * Copying the meta provided by the user from primary order to the new one
					 */
					$meta_keys_to_copy = WFOCU_Core()->data->get_option( 'order_copy_meta_keys' );

					$meta_keys_to_copy = explode( '|', $meta_keys_to_copy );

					/*
					 * add checkout meta keys for show checkout info in admin area
					 */
					array_push( $meta_keys_to_copy, '_wfacp_post_id', '_wfacp_source' );

					$explode_meta_keys = apply_filters( 'wfocu_order_copy_meta_keys', $meta_keys_to_copy, $order );


					/**
					 * We are copying the order attribution meta from the primary order to the new order
					 * This is required for the order attribution feature of the WooCommerce
					 */

					if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\Orders\OrderAttributionController' ) && class_exists( 'Automattic\WooCommerce\Internal\Features\FeaturesController' ) && $container = wc_get_container() ) { //phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found, WordPress.CodeAnalysis.AssignmentInCondition.Found


						$order_attribute_instance = $container->get( \Automattic\WooCommerce\Internal\Orders\OrderAttributionController::class );
						if ( $order_attribute_instance instanceof \Automattic\WooCommerce\Internal\Orders\OrderAttributionController ) {
							$feature_enabled = $container->get( Automattic\WooCommerce\Internal\Features\FeaturesController::class );
							if ( $feature_enabled->feature_is_enabled( 'order_attribution' ) ) {

								$fields = [];
								if ( method_exists( $order_attribute_instance, 'get_fields' ) ) {
									$fields = $order_attribute_instance->get_fields();
								} elseif ( method_exists( $order_attribute_instance, 'get_field_names' ) ) {
									$fields = $order_attribute_instance->get_field_names();

								}

								$prefix = (string) apply_filters( 'wc_order_attribution_tracking_field_prefix', 'wc_order_attribution_' );

								// Remove leading and trailing underscores.
								$prefix = trim( $prefix, '_' );

								// Ensure the prefix ends with _, and set the prefix.

								foreach ( $fields as $field ) {
									if ( 'type' === $field ) {
										$field = 'source_type';
									} elseif ( 'url' === $field ) {
										$field = 'referrer';
									}
									$explode_meta_keys[] = '_' . $prefix . '_' . $field;
								}
							}
						}
					}


					if ( is_array( $explode_meta_keys ) ) {
						foreach ( $explode_meta_keys as $key ) {

							/**
							 * special handling for the payment tokens
							 */
							if ( '_payment_tokens' === $key ) {
								$all_tokens = $order_to_inherit->get_payment_tokens();
								if ( is_array( $all_tokens ) && count( $all_tokens ) > 0 ) {
									foreach ( $all_tokens as $token ) {
										if ( ! $token instanceof WC_Payment_Token ) {
											$token = WC_Payment_Tokens::get( $token );

										}
										$order->add_payment_token( $token );

									}
								}
								continue;
							}
							$order->update_meta_data( $key, WFOCU_Common::get_order_meta( $order_to_inherit, $key ) );
						}
						$order->update_meta_data( '_wfocu_primary_order', WFOCU_WC_Compatibility::get_order_id( $order_to_inherit ) );
						$order->save_meta_data();
						$order->read_meta_data( true );
					}
					$order->calculate_totals();
				}

				return $order;
			}

			return false;
		}

		/**
		 * @param array $args
		 * @param $order_to_inherit
		 *
		 * @return bool|WC_Order|WP_Error
		 */
		public function create_failed_order( $args, $order_to_inherit ) {

			$args['basic']           = array();
			$args['basic']['status'] = WFOCU_Core()->data->get_option( 'create_new_order_status_fail' );
			$args                    = wp_parse_args( $args, $this->get_default_order_args() );

			return $this->create_order( $args, $order_to_inherit );

		}

		/**
		 * Controller of WC_Order::payment_complete() & reduction of stock for non completed gateways
		 * We need to restrict payment_complete function to run for the not supported gateways
		 *
		 * @param $transaction_id
		 * @param WC_Order $order
		 */
		public function payment_complete( $transaction_id, $order ) {

			if ( false === in_array( $order->get_payment_method(), $this->gatways_do_not_support_payment_complete, true ) ) {
				$order->payment_complete( $transaction_id );
			} elseif ( 'cod' === $order->get_payment_method() ) {
				$order->set_status( 'processing' );
				wc_reduce_stock_levels( $order );

			} elseif ( 'bacs' === $order->get_payment_method() || 'cheque' === $order->get_payment_method() ) {
				$order->set_status( 'on-hold' );
				wc_reduce_stock_levels( $order );

			}
		}


		public function process_cancellation( $get_parent_order ) {
			$gateway_controller = WC_Payment_Gateways::instance();
			$all_gateways       = $gateway_controller->payment_gateways();
			$payment_method     = $get_parent_order->get_payment_method();
			$gateway            = isset( $all_gateways[ $payment_method ] ) ? $all_gateways[ $payment_method ] : false;
			do_action( 'wfocu_before_cancelling_order', $get_parent_order );
			/**
			 * When primary order is from paypal standard than we need to check if we have transaction ID in the order (IPN payment completed or not) before processsing refund.
			 * WC paypal standard needs
			 */
			if ( 'paypal' === $payment_method && 'no' === WFOCU_Core()->data->get_option( 'paypal_ref_trans' ) && empty( $get_parent_order->get_transaction_id() ) ) {
				$get_parent_order->add_order_note( __( 'Order refund request accepted from upstroke as offer accepted by the customer. Waiting for IPN to process refund.', 'woofunnels-upstroke-one-click-upsell' ) );
				$get_parent_order->update_meta_data( '_wfocu_pending_refund', 'yes' );
				$get_parent_order->save_meta_data();
				do_action( 'wfocu_front_primary_order_cancelled', $get_parent_order );

				return;
			}
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );
			/*
			 * Only process refunds when the order total is greater than the upsell charges, means we are unable to charge difference
			 */
			if ( ! isset( $get_package['_diff_charged'] ) ) {
				/*
				 * maintain old refund behaviour if primary order amount greater than upsell
				 */
				if ( ! $gateway->supports( 'refunds' ) || ( 0 === $get_parent_order->get_total() ) ) {
					$get_parent_order->update_status( 'wc-cancelled' );
				} else {
					wc_create_refund( array(
						'order_id'       => WFOCU_WC_Compatibility::get_order_id( $get_parent_order ),
						'amount'         => $get_parent_order->get_total(),
						'reason'         => __( 'Refund Processed', 'woofunnels-upstroke-one-click-upsell' ),
						'refund_payment' => true,
						'restock_items'  => true,
						'line_items'     => $get_parent_order->get_items()
					) );
				}
			} else {

				/**
				 * we just need to mark then cancelled.
				 */
				$get_parent_order->update_status( 'wc-cancelled' );
			}

			do_action( 'wfocu_front_primary_order_cancelled', $get_parent_order );

			return;
		}


		/**
		 * Controller of stock reduction after an order
		 *
		 * @param WC_Order $order
		 */
		public function reduce_stock( $order, $items = array() ) {

			$package       = WFOCU_Core()->data->get( '_upsell_package' );
			$stock_reduced = $order->get_data_store()->get_stock_reduced( $order->get_id() );
			if ( true === $stock_reduced && isset( $package['products'] ) && is_array( $package['products'] ) && count( $package['products'] ) > 0 && 'yes' === get_option( 'woocommerce_manage_stock' ) ) {

				$index = 0;

				foreach ( $package['products'] as $product_data ) {

					$product = $product_data['data'];

					$get_item_from_id = ( isset( $items[ $index ] ) ? $order->get_item( $items[ $index ] ) : 0 );

					if ( $product->managing_stock() ) {
						$qty       = apply_filters( 'woocommerce_order_item_quantity', $product_data['qty'], $order, $get_item_from_id );
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
					$index ++;
				}
				do_action( 'wfocu_after_reduce_stock_on_batching', $package['products'], $order, $items );
			}

		}


		public function maybe_show_related_orders( $template_name, $template_path, $located, $args ) {

			/**
			 * There are two areas that we need to hook in our code
			 * It could be above the customer details OR After the order details
			 * So when it entered with one of the condition we need to remove the other one so that it would not get printed twice.
			 */
			if ( ( ( 'woocommerce_before_template_part' === current_action() && 'order/order-details-customer.php' === $template_name ) || ( 'woocommerce_after_template_part' === current_action() && 'order/order-details.php' === $template_name ) ) ) {

				if ( isset( $args['order_id'] ) ) {
					$order = wc_get_order( $args['order_id'] );
				} else {
					$order = $args['order'];
				}

				if ( ! $order instanceof WC_Order ) {
					return;
				}

				$sustain_id = WFOCU_WC_Compatibility::get_order_id( $order );

				/**
				 * get primary order id if primary order cancel from upsell order
				 */
				$primary_id = $order->get_meta( '_wfocu_primary_order', true );

				if ( empty( $primary_id ) ) {
					$primary_id = $sustain_id;
				}

				/**
				 * get upsell id for get upsell setting
				 */
				$funnel_id = WFOCU_Common::get_order_meta( wc_get_order( $primary_id ), '_wfocu_funnel_id' );

				if ( empty( $funnel_id ) ) {
					return;
				}

				WFOCU_Core()->funnels->setup_funnel_options( $funnel_id );
				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );

				/**
				 * return if upsell setting not configure for create new order
				 */
				if ( empty( $order_behavior ) || 'create_order' !== $order_behavior ) {
					return;
				}

				$results = WFOCU_Core()->track->query_results( array(
					'data'         => array(),
					'where'        => array(
						array(
							'key'      => 'session.order_id',
							'value'    => WFOCU_WC_Compatibility::get_order_id( $order ),
							'operator' => '=',
						),
						array(
							'key'      => 'events.action_type_id',
							'value'    => 4,
							'operator' => '=',
						),
					),
					'where_meta'   => array(
						array(
							'type'       => 'meta',
							'meta_key'   => '_new_order', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value' => '', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							'operator'   => '!=',
						),
					),
					'session_join' => true,
					'order_by'     => 'events.id DESC',
					'query_type'   => 'get_results',
				) );

				if ( is_wp_error( $results ) || ( is_array( $results ) && empty( $results ) ) ) {

					/**
					 * Fallback when we are unable to fetch it through our session table, case of cancellation of primary order
					 */
					$get_meta = $order->get_meta( '_wfocu_sibling_order', false );

					if ( ( is_array( $get_meta ) && ! empty( $get_meta ) ) ) {
						$results = [];
						foreach ( $get_meta as $meta ) {
							$single = new stdClass();
							if ( $meta->get_data()['value'] instanceof WC_Order ) {
								$single->meta_value = $meta->get_data()['value']->get_id();
							} else {
								$single->meta_value = absint( $meta->get_data()['value'] );
							}

							$results[] = $single;
						}
					}
					if ( is_array( $results ) && empty( $results ) ) {
						return;
					}
				}

				?>

                <style>
                    .wfocu-additional-order-wrapper .woocommerce-order-details__title {
                        display: none !important;
                    }

                    .wfocu-additional-order-wrapper .woocommerce-customer-details {
                        display: none !important;
                    }
                </style>

				<?php

				foreach ( $results as $rows ) {
					$order = wc_get_order( $rows->meta_value );
					echo '<div class="wfocu-additional-order-wrapper">';

					woocommerce_order_details_table( $order->get_id() );
					echo '</div>';
				}
				?>
                </tbody>
                </table>
				<?php
				if ( 'woocommerce_before_template_part' === current_action() ) {
					remove_action( 'woocommerce_after_template_part', array( $this, 'maybe_show_related_orders' ), 10, 4 );

				} else {
					remove_action( 'woocommerce_before_template_part', array( $this, 'maybe_show_related_orders' ), 10, 4 );

				}
			}
		}

		/**
		 * @hooked over `woocommerce_payment_complete_order_status_wfocu-pri-order`
		 * Record attempt for payment complete in the meta
		 *
		 * @param $order_id
		 */
		public function maybe_record_payment_complete_during_funnel_run( $order_id ) {
			$get_order = wc_get_order( $order_id );

			WFOCU_Core()->log->log( 'Order # ' . $order_id . ': Recorded Payment Complete' );

			if ( $get_order instanceof WC_Order ) {

				$get_order->update_meta_data( '_wfocu_payment_complete_on_hold', 'yes' );
				$get_order->save_meta_data();
			}
		}

		/**
		 * @hooked over `wfocu_after_normalize_order_status`
		 * Perform action to let other plugin know that payment is completed for the given order
		 *
		 * @param $order
		 */
		public function maybe_run_payment_complete_actions( $order ) {
			$get_order = $order;

			if ( $get_order instanceof WC_Order ) {

				$have_pending_payment_complete_action = $get_order->get_meta( '_wfocu_payment_complete_on_hold', true );
				if ( 'yes' === $have_pending_payment_complete_action ) {
					do_action( 'woocommerce_payment_complete', WFOCU_WC_Compatibility::get_order_id( $get_order ) );
				}
			}

		}

		public function maybe_show_order_details( $atts ) {
			$result = false;

			$atts = shortcode_atts( array(
				'order_id' => '',
			), $atts );

			if ( ! empty( $_GET['key'] ) || ! empty( $_GET['ctp_order_key'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$order_key = '';

				if ( ! empty( $_GET['key'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$order_key = wc_clean( $_GET['key'] );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}

				/**
				 * Compatibility with "Custom Thank You Pages Per Product for WooCommerce"
				 * In case of custom thank you page setup by external plugin.
				 */
				if ( ! empty( $_GET['ctp_order_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$order_key = wc_clean( $_GET['ctp_order_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}

				$order = wc_get_order_id_by_order_key( $order_key );

				$order_id = WFOCU_WC_Compatibility::get_order_id( $order );

				if ( empty( $atts['order_id'] ) && ! empty( $order_id ) ) {
					$atts['order_id'] = $order_id;
				}
			}

			if ( ! empty( $atts['order_id'] ) ) {
				$order = wc_get_order( intval( $atts['order_id'] ) );

				if ( ! empty( $order ) ) {
					$this->is_shortcode_output = true;
					ob_start();

					echo '<div class="woocommerce">';

					echo '<link rel="stylesheet" href="' . esc_url( str_replace( array( //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
								'http:',
								'https:',
							), '', WC()->plugin_url() ) . '/assets/css/woocommerce.css' ) . '" type="text/css" media="all" />';

					wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );

					echo '</div>';

					$result                    = ob_get_clean();
					$this->is_shortcode_output = false;

				}
			}

			return $result;
		}


		/**
		 * While we are transitioning order stasuses in case batching then there would be no case we want to increase stock
		 * Unhooking woocommerce functions wc_maybe_increase_stock_levels() so escape any chances to increase stock in case of pending status.
		 */
		public function maybe_detach_increase_stock() {

			remove_action( 'woocommerce_order_status_pending', 'wc_maybe_increase_stock_levels' );
		}

		public function mark_order_as_thankyou_visited( $order_id ) {

			$get_order = wc_get_order( $order_id );

			$get_order->delete_meta_data( '_wfocu_upsell_abandoned' );
			$get_order->save_meta_data();

		}

		public function maybe_set_funnel_running_status( $order ) {
			/**
			 * Move to our custom status
			 */
			$old_status = $order->get_status();

			if ( $old_status === 'wfocu-pri-order' ) {
				return;
			}
			WFOCU_Core()->log->log( 'Moving Order status to "Primary-order::" Current action' . current_action() );

			/**
			 * Tell the plugin that order status modified so that we can initiate schedule hooks
			 */
			do_action( 'wfocu_front_primary_order_status_change', 'wc-wfocu-pri-order', $old_status, $order );
			remove_filter( 'woocommerce_payment_complete_order_status', array( WFOCU_Core()->orders, 'maybe_set_completed_order_status' ), 999, 3 );
			$order->set_status( 'wfocu-pri-order' );
			$order->save();
		}

		public function maybe_add_shipping_item_id_as_meta( $event ) {
			WFOCU_Core()->track->add_meta( $event, '_shipping_batch_id', $this->item_shipping_batch );
		}

		public function maybe_add_ashipping_item_taxes( $item ) {
			if ( isset( $this->temp['shipping'][ $item->get_id() ] ) ) {
				$item->set_taxes( $this->temp['shipping'][ $item->get_id() ] );
			}
		}


	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'orders', 'WFOCU_Orders' );
	}

}
