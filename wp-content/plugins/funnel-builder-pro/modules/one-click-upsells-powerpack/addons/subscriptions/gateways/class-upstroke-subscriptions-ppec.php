<?php
/**
 * Author PhpStorm.
 */
if ( ! class_exists( 'UpStroke_Subscriptions_PPEC' ) ) {
	class UpStroke_Subscriptions_PPEC extends WFOCU_Gateway_Integration_Paypal_Express_Checkout {

		public function __construct() {

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_stripe_source_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_stripe_keys_to_copy' ), 10, 1 );
			add_filter( 'wfocu_gateway_ppec_param_setexpresscheckout', array( $this, 'maybe_filter_paypal_setexpress_checkout_arguments' ), 10, 2 );
			add_filter( 'wfocu_gateway_in_offer_transaction_ppec_after_express_checkout_response', array( $this, 'perform_createrecurring_profile' ), 10, 4 );

		}

		/**
		 * Save Subscription Details
		 *
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_stripe_source_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$get_source_id = $order->get_meta( '_ppec_billing_agreement_id', true );

			if ( ! empty( $get_source_id ) ) {
				$subscription->update_meta_data( '_ppec_billing_agreement_id', $get_source_id );

				$subscription->save();
			}

		}

		public function set_stripe_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_ppec_billing_agreement_id' );

			return $meta_keys;
		}

		public function maybe_filter_paypal_setexpress_checkout_arguments( $arguments, $is_upsell = false ) {

			if ( false === $is_upsell ) {
				return $arguments;
			}

			$get_upstroke_subscription_instance = UpStroke_Subscriptions::get_instance();

			if ( $get_upstroke_subscription_instance->is_package_contains_subscription() ) {

				$get_package = WFOCU_Core()->data->get( '_upsell_package' );

				if ( false === is_array( $get_package ) ) {
					return false;
				}
				$incr = 0;
				foreach ( $get_package['products'] as $products ) {
					$product_object = $products['data'];
					if ( is_a( $product_object, 'WC_Product' ) && WC_Subscriptions_Product::is_subscription( $product_object->get_id() ) ) {
						$arguments[ 'L_BILLINGAGREEMENTDESCRIPTION' . $incr ] = wp_specialchars_decode( get_the_title( $product_object->get_id() ), ENT_QUOTES );
						$arguments[ 'L_BILLINGTYPE' . $incr ]                 = 'RecurringPayments';
						$incr ++;
					}
				}
			}

			return $arguments;

		}

		/**
		 * Create Recurring Profile
		 *
		 * @param $response
		 * @param WFOCU_Gateway_Integration_PayPal_Standard $PayPal_integration
		 */
		public function perform_createrecurring_profile( $api_response_result, $token, $payer_id, WFOCU_Gateway_Integration_Paypal_Express_Checkout $PayPal_integration ) {

			$get_upstroke_subscription_instance = UpStroke_Subscriptions::get_instance();
			$collect_profile_ids                = array();

			/**
			 * Create RecurringBillingProfileWhenNeeded
			 */
			$existing_package = WFOCU_Core()->data->get( 'upsell_package', '', 'paypal' );

			if ( $get_upstroke_subscription_instance->is_package_contains_subscription( $existing_package ) ) {

				$get_details = $PayPal_integration->get_express_checkout_details( $token );

				if ( is_array( $get_details ) && isset( $get_details['BILLINGAGREEMENTACCEPTEDSTATUS'] ) && 1 == absint( $get_details['BILLINGAGREEMENTACCEPTEDSTATUS'] ) ) {

					foreach ( $existing_package['products'] as $product ) {
						$product_object = $product['data'];
						if ( is_a( $product_object, 'WC_Product' ) && WC_Subscriptions_Product::is_subscription( $product_object->get_id() ) ) {

							$args       = $this->get_recurring_billing_profile_args( $product['data'], $product );
							$profile_id = $this->create_recurring_payments_profile( $args, $token, $payer_id, $get_details );
							if ( false !== $profile_id ) {
								$collect_profile_ids[ $product['hash'] ] = $profile_id;
							} else {
								$api_response_result = false;
							}
						}
					}
				}
			}

			WFOCU_Core()->data->set( '_profile_ids', $collect_profile_ids, 'paypal' );
			WFOCU_Core()->data->save( 'paypal' );

			return $api_response_result;

		}

		public function get_recurring_billing_profile_args( $product, $product_args ) {
			$get_upstroke_subscription_instance = UpStroke_Subscriptions::get_instance();
			$free_trial_length                  = $get_upstroke_subscription_instance->get_trial_length( $product, $product_args );
			$interval                           = WC_Subscriptions_Product::get_interval( $product );
			$period                             = WC_Subscriptions_Product::get_period( $product );

			$period               = ucwords( $period );
			$frequency            = $interval;
			$total_billing_cycles = WC_Subscriptions_Product::get_length( $product );

			if ( $free_trial_length > 0 ) {

				$trial_period = $get_upstroke_subscription_instance->get_trial_period( $product, $product_args );

				// Set start date to the end of the free trial.
				$profile_start = date( 'Y-m-d\Tg:i:s', strtotime( '+' . $free_trial_length . ' ' . ucwords( $trial_period ), current_time( 'timestamp' ) ) );

			} else {

				// Set start date to the first renewal date. Initial period is covered by the initial payment processed above
				$profile_start = date( 'Y-m-d\Tg:i:s', strtotime( '+' . $frequency . ' ' . $period, current_time( 'timestamp' ) ) );
			}

			// An initial period is being used to charge a sign-up fee
			if ( 0 !== $total_billing_cycles && 0 === $free_trial_length ) {
				$total_billing_cycles --;
			}

			return array(
				'amt'                  => $product_args['_recurring_price'],
				'profile_start_date'   => $profile_start,
				'desc'                 => wp_specialchars_decode( get_the_title( $product->get_id() ), ENT_QUOTES ),
				'billing_period'       => $period,
				'total_billing_cycles' => $total_billing_cycles,
				'billing_frequency'    => $frequency,

			);

		}

		public function create_recurring_payments_profile( $profile_args, $token, $payerID, $get_express_checkout_details ) {
			$create_recurring_billing_profile_args = array(
				'TOKEN'              => $token,
				'PAYERID'            => $payerID,
				'METHOD'             => 'CreateRecurringPaymentsProfile',
				'NOTIFY_URL'         => WC()->api_request_url( 'WC_Gateway_Paypal' ),
				'PROFILESTARTDATE'   => $profile_args['profile_start_date'],
				'DESC'               => $profile_args['desc'],
				'BILLINGPERIOD'      => $profile_args['billing_period'],
				'BILLINGFREQUENCY'   => $profile_args['billing_frequency'],
				'AMT'                => $profile_args['amt'],
				'CURRENCYCODE'       => $get_express_checkout_details['CURRENCYCODE'],
				'COUNTRYCODE'        => $get_express_checkout_details['COUNTRYCODE'],
				'TOTALBILLINGCYCLES' => $profile_args['total_billing_cycles'],
			);

			$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );

			if ( 'live' === $environment ) {
				$api_username  = $this->get_wc_gateway()->get_option( 'api_username' );
				$api_password  = $this->get_wc_gateway()->get_option( 'api_password' );
				$api_signature = $this->get_wc_gateway()->get_option( 'api_signature' );
			} else {
				$api_username  = $this->get_wc_gateway()->get_option( 'sandbox_api_username' );
				$api_password  = $this->get_wc_gateway()->get_option( 'sandbox_api_password' );
				$api_signature = $this->get_wc_gateway()->get_option( 'sandbox_api_signature' );

			}

			$this->set_api_credentials( $this->get_key(), $environment, $api_username, $api_password, $api_signature );
			$this->add_parameters( $create_recurring_billing_profile_args );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );
			$request                    = new stdClass();
			$request->path              = '';
			$request->method            = 'POST';
			$request->body              = $this->to_string();
			$response_agreement_profile = $this->perform_request( $request );
			WFOCU_Core()->log->log( 'PayPal In-offer transactions CreateRecurringPaymentsProfile response. ' . print_r( $response_agreement_profile, true ) );

			if ( ! $this->has_api_error( $response_agreement_profile ) ) {
				WFOCU_Core()->log->log( 'PayPal CreateRecurringPaymentsProfile Created, Profile ID is. ' . $response_agreement_profile['PROFILEID'] );

				return $response_agreement_profile['PROFILEID'];
			}

			return false;

		}
	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		new UpStroke_Subscriptions_PPEC();
	}
}