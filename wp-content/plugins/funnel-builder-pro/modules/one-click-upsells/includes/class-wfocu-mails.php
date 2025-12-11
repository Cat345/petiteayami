<?php
if ( ! class_exists( 'WFOCU_Mails' ) ) {
	class WFOCU_Mails {

		private static $ins = null;


		public function __construct() {

			/**
			 * Adding email actions to fire emails on the certain events
			 */
			add_action( 'woocommerce_email_actions', array( $this, 'add_email_actions' ), 999 );

			/**
			 * registering action to trigger processing notification when order status change happen
			 */
			add_action( 'wfocu_front_init_funnel_hooks', array( $this, 'setup_primary_order_mail' ), 999 );

			/**
			 * Registering email class to the WooCommerce of `WFOCU_WC_Email_Updated_Order`
			 */
			add_filter( 'woocommerce_email_classes', array( $this, 'add_email_class' ) );

			/**
			 * Force turning off woocommerce's defer transactional mails
			 */
			add_filter( 'woocommerce_defer_transactional_emails', '__return_false', 1 );

			/**
			 * Maybe stopping woocommerce to fire emails over processing/completed (conditional)
			 */
			add_action( 'wfocu_before_normalize_order_status_to_successful', array( $this, 'maybe_hold_mails_after_processing' ), 10, 2 );

			/**************** CRON SCHEDULE HANDLING ****************************/

			/**
			 * Cron Handler for `fk_fb_every_4_minute`
			 */
			add_action( 'fk_fb_every_4_minute', array( $this, 'maybe_handle_cron_pending_mails' ), 40 );

			/**
			 * Cron Handler for `fk_fb_every_4_minute`
			 */
			add_action( 'fk_fb_every_4_minute', array( $this, 'maybe_handle_cron_pending_mails_for_cb' ), 30 );

			/**
			 * Handles sending mails when primary order gateway is bacs or cheque.
			 */
			add_action( 'wfocu_front_init_funnel_hooks', array( $this, 'prevent_sending_mails_on_bacs_n_cheque' ), 10 );

			/**
			 * Normalizing the order statuses on funnel end
			 */
			add_action( 'wfocu_funnel_ended_event', array( $this, 'maybe_send_pending_emails_on_funnel_end' ), 10, 3 );

			/**
			 * Normalizing the order statuses on funnel end for bacs and cheque
			 */
			add_action( 'wfocu_funnel_ended_event', array( $this, 'maybe_send_pending_email_for_bacs_for_cheque' ), 10, 3 );

			/**
			 * prevent emails to send in orders from paypal standard while funnel was running
			 * Or case of refund & late IPN
			 */
			add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_prevent_mails_on_new_order' ), 10, 2 );
			add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_prevent_mails_on_new_order' ), 10, 2 );
			add_action( 'woocommerce_order_status_on-hold', array( $this, 'maybe_prevent_mails_on_new_order' ), 10, 2 );

			add_action( 'wfocu_before_normalize_order_status_to_successful', array( $this, 'initialize_payment_gateway_before_normalize' ), 10 );
			add_action( 'wfocu_before_cancelling_order', array( $this, 'maybe_restrict_emails_on_cancel_refund_expired' ), 10 );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Adding our custom Email to the WooCommerce Email Framework
		 * @hooked into `woocommerce_email_classes`
		 *
		 * @param WC_Email[] $email_classes
		 *
		 * @return mixed
		 */
		public function add_email_class( $email_classes ) {

			$email_classes['WFOCU_WC_Email_Updated_Order']       = include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'emails/class-wfocu-wc-email-updated-order.php';
			$email_classes['WFOCU_WC_Email_Updated_Order_Admin'] = include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'emails/class-wfocu-wc-email-updated-order-admin.php';

			return $email_classes;
		}


		/**
		 * Setting up dynamic events to fire emails at our event in WooCommerce way
		 * @hooked into `woocommerce_email_actions`
		 *
		 * @param array $email_actions
		 *
		 * @return mixed
		 */
		public function add_email_actions( $email_actions ) {

			array_push( $email_actions, 'woocommerce_order_status_pending_to_wfocu-pri-order' );

			array_push( $email_actions, 'wfocu_offer_accepted_and_processed' );

			return $email_actions;
		}


		/**
		 * @hooked into `wfocu_front_init_funnel_hooks`
		 * Triggers WooCommerce's Customer Processing notification
		 *
		 * @param WC_Order $order
		 */
		public function setup_primary_order_mail( $order ) {

			$wc_mails = WC()->mailer();
			WC()->payment_gateways();

			if ( isset( $wc_mails->emails['WC_Email_Customer_Processing_Order'] ) && is_a( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'WC_Email' ) ) {
				$order_behavior  = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$cancel_original = WFOCU_Core()->funnels->get_funnel_option( 'is_cancel_order' );

				/**
				 * case 1: If batching is on and we have to send mail on start
				 */
				if ( 'batching' === $order_behavior && 'start' === WFOCU_Core()->data->get_option( 'send_processing_mail_on' ) ) {

					$needs_processing = $order->needs_processing();
					if ( true === $needs_processing ) {
						add_action( 'woocommerce_order_status_pending_to_wfocu-pri-order_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );

					} else {
						add_action( 'woocommerce_order_status_pending_to_wfocu-pri-order_notification', array( $wc_mails->emails['WC_Email_Customer_Completed_Order'], 'trigger' ), 10, 2 );

					}
					add_action( 'woocommerce_order_status_pending_to_wfocu-pri-order_notification', array( $wc_mails->emails['WC_Email_New_Order'], 'trigger' ), 10, 2 );

				}

				/**
				 * case 1: If batching is off & crete a separate order and we have to send mail on end
				 * case 2: If batching is off & crete a separate order (with cancellation of parent order) and we have to send mail on end
				 */
				if ( 'create_order' === $order_behavior && ( ( 'yes' === $cancel_original && 'end' === WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch_cancel' ) ) || ( 'no' === $cancel_original && 'end' === WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch' ) ) ) ) {


					remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );
					remove_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );
					remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );

					/**
					 * For cased when status is on hold but not processing , bank and cheque
					 */
					remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );
					remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );

					remove_action( 'woocommerce_order_status_completed_notification', array( $wc_mails->emails['WC_Email_Customer_Completed_Order'], 'trigger' ), 10, 2 );


				}
			}

		}

		/**
		 * @hooked `wfocu_before_normalize_order_status_to_successful`
		 * Removing actions for the woocommerce mailing that will ensure no duplicate processing mail will sent to user/admin
		 *
		 * @param $from
		 * @param $to
		 */
		public function maybe_hold_mails_after_processing( $from, $to ) {
			$wc_mails = WC()->mailer();

			if ( isset( $wc_mails->emails['WC_Email_Customer_Processing_Order'] ) && is_a( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'WC_Email' ) && 'start' === WFOCU_Core()->data->get_option( 'send_processing_mail_on' ) ) {
				remove_all_actions( 'woocommerce_order_status_' . $from . '_to_' . $to . '_notification' );
				remove_all_actions( 'woocommerce_order_status_completed_notification' );
			}
		}

		public function maybe_send_pending_emails_on_funnel_end( $funnel_id, $order_id, $order_email ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$get_parent = WFOCU_Core()->data->get_parent_order();

			$args = array(
				WFOCU_WC_Compatibility::get_order_id( $get_parent ),
				WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' ),
				WFOCU_Core()->funnels->get_funnel_option( 'is_cancel_order' ),
				WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch' ),
				WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch_cancel' ),
			);

			list( $order_id, $funnel_order_behavior, $cancel_original, $send_processing_mail_on_no_batch, $send_processing_mail_on_no_batch_cancel ) = $args;

			WFOCU_Core()->log->log( 'Funnel End: Sending Pending Emails For Order: ' . print_r( $args, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$has_meta = $get_parent->get_meta( '_wfocu_pending_mails' );
			/**
			 * return if we do not have scheduled event running
			 * Means scheduled event have already sent the emails which are pending
			 */
			if ( empty( $has_meta ) ) {
				WFOCU_Core()->log->log( 'Discard: No Schedule Event Set for  ' . print_r( $args, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return;
			}

			$this->maybe_send_pending_emails( $order_id, $funnel_order_behavior, $cancel_original, $send_processing_mail_on_no_batch, $send_processing_mail_on_no_batch_cancel );

		}

		public function maybe_send_pending_emails( $order, $order_behaviour, $cancel_original, $send_processing_mail_on_no_batch, $send_processing_mail_on_no_batch_cancel ) {
			$get_order = wc_get_order( $order );
			if ( 'create_order' === $order_behaviour && ( 'no' === $cancel_original && 'end' === $send_processing_mail_on_no_batch ) || ( 'yes' === $cancel_original && 'end' === $send_processing_mail_on_no_batch_cancel ) ) {

				$status   = $get_order->get_status();
				$wc_mails = WC()->mailer();
				WC()->payment_gateways();
				switch ( $status ) {
					case 'processing':
						$wc_mails->emails['WC_Email_Customer_Processing_Order']->trigger( WFOCU_WC_Compatibility::get_order_id( $get_order ) );
						break;
					case 'completed':
						$wc_mails->emails['WC_Email_Customer_Completed_Order']->trigger( WFOCU_WC_Compatibility::get_order_id( $get_order ) );
						break;
					case 'on-hold':
						$wc_mails->emails['WC_Email_Customer_On_Hold_Order']->trigger( WFOCU_WC_Compatibility::get_order_id( $get_order ) );
						break;
					default:
				}
			}

			$get_order->delete_meta_data( '_wfocu_pending_mails' );

			/**
			 * we can safely delete that meta that we do not have to take care of emails in case of no cancellation after funnel ends.
			 */
			if ( 'create_order' === $order_behaviour && 'no' === $cancel_original ) {
				$get_order->delete_meta_data( '_wfocu_prevent_mail_paypal' );

			}

			$get_order->save_meta_data();

		}


		/**
		 * @param WC_Order $order
		 */
		public function prevent_sending_mails_on_bacs_n_cheque( $order ) {
			if ( false === is_a( $order, 'WC_Order' ) ) {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': No valid order' . __FUNCTION__ );

				return;
			}
			WC()->mailer();
			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );

			if ( in_array( $order->get_payment_method(), array( 'bacs', 'cheque' ), true ) ) {
				/**
				 * case 1: If batching is on and we have to send mail on end
				 */
				if ( 'batching' === $order_behavior && 'end' === WFOCU_Core()->data->get_option( 'send_processing_mail_on' ) ) {
					/**
					 * For cased when status is on hold but not processing, bank and cheque
					 */
					remove_all_actions( 'woocommerce_order_status_pending_to_on-hold_notification', 10 );
					remove_all_actions( 'woocommerce_order_status_failed_to_on-hold_notification', 2 );

					$order->update_meta_data( '_wfocu_status_schedule_for_cb', time() );
					$order->save_meta_data();



				}

				return;
			}
		}

		public function maybe_send_pending_email_for_bacs_for_cheque( $funnel_or_order_id, $order_id = '' ) {

			$get_parent = wc_get_order( $order_id );

			if ( ! $get_parent instanceof WC_Order ) {
				return;
			}
			WC_Emails::init_transactional_emails();
			$get_parent = WFOCU_Core()->data->get_parent_order();

			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );

			$has_meta = $get_parent->get_meta( '_wfocu_status_schedule_for_cb' );
			/**
			 * If scheduled event not registered then do not proceed.
			 * it means this order does not require any pending mails to fore from upstroke side
			 * OR funnel is getting ended after mail sent for bacs and cheque
			 */
			if ( ! empty( $has_meta ) ) {
				if ( in_array( $get_parent->get_payment_method(), array( 'bacs', 'cheque' ), true ) ) {
					/**
					 * case 1: If batching is on and we have to send mail on start
					 */
					if ( 'batching' === $order_behavior && 'end' === WFOCU_Core()->data->get_option( 'send_processing_mail_on' ) ) {
						/**initiate payment gateways so that they could contribute in emails **/
						WC()->payment_gateways();
						$wc_mails         = WC()->mailer();
						add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );
						add_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );
						add_action( 'woocommerce_order_status_cancelled_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );
						if ( isset( $wc_mails->emails['WC_Email_New_Order'] ) ) {
							add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $wc_mails->emails['WC_Email_New_Order'], 'trigger' ), 10, 2 );
						}
						/**
						 * For cased when status is on hold but not processing , bank and cheque
						 */
						do_action( 'woocommerce_order_status_pending_to_on-hold_notification', $order_id, false );

						$get_parent->delete_meta_data( '_wfocu_status_schedule_for_cb' );
						$get_parent->save_meta_data();
					}

					return;
				}
			}

		}

		public function maybe_handle_cron_pending_mails_for_cb() {

			WFOCU_Common::$start_time = time();
			$get_orders               = WFOCU_Common::wc_get_orders( array(
				'limit'     => 100,
				'post_type' => 'shop_order',
			), [ 'key' => '_wfocu_status_schedule_for_cb', 'value' => true ] );
			$i                        = 0;
			$get_ttl                  = WFOCU_Core()->data->get_option( 'ttl_funnel' );
			if ( ! empty( $get_orders ) ) {

				do {
					if ( ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) ) {
						// Batch limits reached.
						break;
					}
					$order             = $get_orders[ $i ];
					$get_schedule_meta = $order->get_meta( '_wfocu_status_schedule_for_cb', true );

					/**
					 * check if the funnel end time reached or not
					 */
					if ( absint( $get_schedule_meta ) + ( MINUTE_IN_SECONDS * $get_ttl ) <= time() ) {
						$this->maybe_send_pending_email_for_bacs_for_cheque_scheduled( $order->get_id() );
					}
					unset( $get_orders[ $i ] );
					$i ++;
				} while ( ! ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) && ! empty( $get_orders ) );
			}
		}

		public function maybe_send_pending_email_for_bacs_for_cheque_scheduled( $order_id ) {

			$get_parent = wc_get_order( $order_id );

			if ( ! $get_parent instanceof WC_Order ) {
				return;
			}

			$wc_mails = WC()->mailer();
			WC()->payment_gateways();
			/**
			 * For cased when status is on hold but not processing , bank and cheque
			 */
			$wc_mails->emails['WC_Email_Customer_On_Hold_Order']->trigger( WFOCU_WC_Compatibility::get_order_id( $get_parent ) );
			$get_parent->delete_meta_data( '_wfocu_status_schedule_for_cb' );
			$get_parent->save_meta_data();
		}

		public function maybe_handle_cron_pending_mails() {

			WFOCU_Common::$start_time = time();
			$get_orders               = WFOCU_Common::wc_get_orders( array(
				'limit'     => 100,
				'post_type' => 'shop_order',
			), [ 'key' => '_wfocu_pending_mails', 'value' => true ] );
			$i                        = 0;
			$get_ttl                  = WFOCU_Core()->data->get_option( 'ttl_funnel' );
			if ( ! empty( $get_orders ) ) {

				do {
					if ( ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) ) {
						// Batch limits reached.
						break;
					}
					$order = $get_orders[ $i ];


					if ( ! $order instanceof WC_Order ) {
						continue;
					}
					$get_schedule_meta = $order->get_meta( '_wfocu_pending_mails', true );

					/**
					 * additionally check if meta doesn't exist, onto the next order
					 */
					if ( empty( $get_schedule_meta ) || ! is_array( $get_schedule_meta ) || ! isset( $get_schedule_meta[0] ) ) {
						continue;
					}


					list( $order_id, $funnel_order_behavior, $cancel_original, $send_processing_mail_on_no_batch, $send_processing_mail_on_no_batch_cancel, $time ) = array_values( $get_schedule_meta );

					/**
					 * check if the funnel end time reached or not
					 */
					if ( $time + ( MINUTE_IN_SECONDS * $get_ttl ) <= time() ) {
						$this->maybe_send_pending_emails( $order_id, $funnel_order_behavior, $cancel_original, $send_processing_mail_on_no_batch, $send_processing_mail_on_no_batch_cancel );
					}
					unset( $get_orders[ $i ] );
					$i ++;
				} while ( ! ( WFOCU_Common::time_exceeded() || WFOCU_Common::memory_exceeded() ) && ! empty( $get_orders ) );
			}
		}

		public function maybe_prevent_mails_on_new_order( $order_id, $order = false ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}
			$get_schedule_meta = $order->get_meta( '_wfocu_prevent_mail_paypal', true );

			if ( empty( $get_schedule_meta ) ) {
				return;
			}

			$wc_mails         = WC()->mailer();
			$needs_processing = $order->needs_processing();
			if ( true === $needs_processing ) {
				remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );
				remove_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );
				remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $wc_mails->emails['WC_Email_Customer_Processing_Order'], 'trigger' ), 10, 2 );

				/**
				 * For cased when status is on hold but not processing , bank and cheque
				 */
				remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );
				remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $wc_mails->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ), 10, 2 );
			} else {

				remove_action( 'woocommerce_order_status_completed_notification', array( $wc_mails->emails['WC_Email_Customer_Completed_Order'], 'trigger' ), 10, 2 );

			}

		}

		public function initialize_payment_gateway_before_normalize() {
			WC()->payment_gateways();
		}

		public function maybe_restrict_emails_on_cancel_refund_expired() {


			if ( 'end' === WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch_cancel' ) ) {
				add_filter( 'woocommerce_email_enabled_customer_refunded_order', '__return_false' );
				add_filter( 'woocommerce_email_enabled_cancelled_order', '__return_false' );

				add_filter( 'woocommerce_email_enabled_wc_memberships_user_membership_ended_email', '__return_false' );
				add_filter( 'woocommerce_email_enabled_wcs_email_cancelled_subscription', '__return_false' );
			}

		}

	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'mails', 'WFOCU_Mails' );
	}
}