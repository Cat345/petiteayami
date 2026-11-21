<?php

namespace FKWCS\Gateway\Stripe;

use FKWCS\Gateway\Stripe\Traits\WC_Subscriptions_Trait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[\AllowDynamicProperties]
class klarna extends LocalGateway {

	use WC_Subscriptions_Trait;

	/**
	 * Gateway id
	 *
	 * @var string
	 */
	public $id                       = 'fkwcs_stripe_klarna';
	public $payment_method_types     = 'klarna';
	protected $payment_element       = true;
	public $supports_success_webhook = true;

	/**
	 * Setup general properties and settings
	 *
	 * @return void
	 */
	protected function init() {

		$this->method_title       = __( 'Klarna', 'funnelkit-stripe-woo-payment-gateway' );
		$this->method_description = __( 'Accepts payments via Klarna. The gateway should be enabled in your Stripe Account. Log into your Stripe account to review the <a href="https://dashboard.stripe.com/account/payments/settings" target="_blank">available gateways</a> <br/>Supported Currency: <strong> EUR, DKK, GBP, NOK, SEK, USD, CZK, AUD, NZD, CAD, PLN, CHF</strong>', 'funnelkit-stripe-woo-payment-gateway' );
		$this->subtitle           = __( 'klarna is an online banking payment method that enables your customers in e-commerce to make an online purchase', 'funnelkit-stripe-woo-payment-gateway' );
		$this->init_form_fields();
		$this->init_settings();
		$this->maybe_init_subscriptions();
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->enabled        = $this->get_option( 'enabled' );
		$this->capture_method = $this->get_option( 'charge_type' );
		add_action( 'fkwcs_webhook_event_intent_succeeded', array( $this, 'handle_webhook_intent_succeeded' ), 10, 2 );
		add_filter( 'fkwcs_payment_intent_data', array( $this, 'add_klarna_subscription_data' ), 20, 3 );
		add_action( 'fkwcs_' . $this->id . '_before_redirect', array( $this, 'save_payment_method_from_setup_intent' ) );
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'update_payment_method_after_confirmation' ), 10, 2 );
	}

	/**
	 * Switch a subscription to Klarna only once Klarna confirms (save_payment_method_from_setup_intent), not when the
	 * change form is submitted. This also keeps "use for all subscriptions" off: a Klarna method is tied to one subscription reference.
	 *
	 * @param bool   $update         Whether WCS updates the payment method right away.
	 * @param string $payment_method New payment method ID.
	 *
	 * @return bool
	 */
	public function update_payment_method_after_confirmation( $update, $payment_method ) {
		if ( $this->id !== $payment_method || ! $this->is_changing_payment_method_for_subscription() ) {
			return $update;
		}

		return false;
	}

	/**
	 * Free trials ($0 today) and payment method changes have nothing to charge now, so Klarna is set up
	 * with a SetupIntent. Everything else goes through the regular Klarna payment.
	 *
	 * @param int $order_id Order or subscription ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order instanceof \WC_Order && ( $this->maybe_change_subscription_payment_method( $order_id ) || ( 0 >= (float) $order->get_total() && $this->has_subscription( $order_id ) ) ) ) {
			try {
				$intent = $this->create_setup_intent( '', $this->get_customer_id( $order ), $order );
				if ( empty( $intent ) || empty( $intent->client_secret ) ) {
					throw new \Exception( __( 'Unable to process this payment, please try again or use alternative method.', 'funnelkit-stripe-woo-payment-gateway' ) );
				}
				$order->update_meta_data(
					'_fkwcs_setup_intent',
					array(
						'id'            => $intent->id,
						'client_secret' => $intent->client_secret,
					)
				);
				$order->save_meta_data();

				/** WCS change-payment redirects without applying woocommerce_payment_successful_result, so build the confirm hash here */
				if ( $this->maybe_change_subscription_payment_method( $order_id ) ) {
					$verification_url = add_query_arg(
						array(
							'order'     => $order->get_id(),
							'order_key' => $order->get_order_key(),
							'gateway'   => $this->id,
						),
						\WC_AJAX::get_endpoint( 'fkwcs_stripe_verify_payment_intent' )
					);

					return array(
						'result'   => 'success',
						'redirect' => sprintf( '#fkwcs-confirm-si-%s:%s:%d:%s:%s', $intent->client_secret, rawurlencode( $verification_url ), $order->get_id(), $this->id, 'no' ),
					);
				}

				return array(
					'result'                    => 'success',
					'fkwcs_redirect'            => $this->get_return_url( $order ),
					'fkwcs_setup_intent_secret' => $intent->client_secret,
					'token_used'                => 'no',
				);
			} catch ( \Exception $e ) {
				Helper::log( $e->getMessage(), 'warning' );
				wc_add_notice( $e->getMessage(), 'error' );

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		}

		return parent::process_payment( $order_id );
	}

	/**
	 * A payment method change returns to an already active subscription, which the shared verifier
	 * skips (it only processes pending/failed orders). Save the new Klarna method here instead.
	 *
	 * @return void
	 */
	public function verify_intent() {
		$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order || ! function_exists( 'wcs_is_subscription' ) || ! wcs_is_subscription( $order ) || $order->has_status( array( 'pending', 'failed' ) ) ) {
			parent::verify_intent();

			return;
		}

		if ( ! isset( $_GET['order_key'] ) || ! $order->key_is_valid( wc_clean( wp_unslash( $_GET['order_key'] ) ) ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wc_add_notice( __( 'Payment verification error: Invalid Order Key.', 'funnelkit-stripe-woo-payment-gateway' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'subscriptions' ) );
			exit;
		}

		$redirect = $order->get_view_order_url();
		if ( $this->save_payment_method_from_setup_intent( $order->get_id() ) ) {
			wc_add_notice( __( 'Payment method updated.', 'funnelkit-stripe-woo-payment-gateway' ) );
		} else {
			wc_add_notice( __( 'Unable to update the payment method, please try again.', 'funnelkit-stripe-woo-payment-gateway' ), 'error' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Store the Klarna method from a succeeded SetupIntent (free trial or payment method change).
	 *
	 * @param int $order_id Order or subscription ID.
	 *
	 * @return bool
	 */
	public function save_payment_method_from_setup_intent( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		$intent = $this->get_intent_from_order( $order );
		if ( empty( $intent ) || 'setup_intent' !== $intent->object || 'succeeded' !== $intent->status || empty( $intent->payment_method ) ) {
			return false;
		}

		$customer = ! empty( $intent->customer ) ? $intent->customer : Helper::get_meta( $order, Helper::get_customer_key() );
		$source   = (object) array(
			'customer'       => $customer,
			'source'         => $intent->payment_method,
			'payment_method' => $intent->payment_method,
		);
		$order->update_meta_data( Helper::get_customer_key(), $customer );
		$order->update_meta_data( '_fkwcs_source_id', $intent->payment_method );
		if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) {
			$order->save_meta_data();
			\WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $order, $this->id );
		} else {
			$order->save_meta_data();
			$this->maybe_update_source_on_subscription_order( $order, $source );
		}

		return true;
	}

	/**
	 * A Klarna method has no card brand/last4 to show, so keep the default gateway title instead of "N/A".
	 *
	 * @param string           $payment_method_to_display Default text (gateway title).
	 * @param \WC_Subscription $subscription              Subscription.
	 *
	 * @return string
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		return $payment_method_to_display;
	}

	/**
	 * The shared intent handlers save off_session methods as card tokens, which a Klarna method can't be.
	 * Store it on the order and its subscriptions instead.
	 *
	 * @param \WC_Order $order  Order.
	 * @param object    $intent PaymentIntent or SetupIntent.
	 *
	 * @return void
	 */
	public function save_payment_method( $order, $intent ) {
		$this->save_payment_method_details( $order, $intent );
	}

	/**
	 * Store the Klarna payment method on the order and its subscriptions so renewals can charge it.
	 *
	 * @param \WC_Order $order          Order.
	 * @param object    $payment_intent Charge or PaymentIntent from the successful payment.
	 *
	 * @return void
	 */
	public function save_payment_method_details( $order, $payment_intent ) {
		if ( ! $order instanceof \WC_Order || empty( $payment_intent->payment_method ) || ! $this->has_subscription( $order->get_id() ) ) {
			return;
		}

		$customer = ! empty( $payment_intent->customer ) ? $payment_intent->customer : Helper::get_meta( $order, Helper::get_customer_key() );
		$source   = (object) array(
			'customer'       => $customer,
			'source'         => $payment_intent->payment_method,
			'payment_method' => $payment_intent->payment_method,
		);

		$order->update_meta_data( Helper::get_customer_key(), $customer );
		$order->update_meta_data( '_fkwcs_source_id', $payment_intent->payment_method );
		$order->save_meta_data();
		$this->maybe_update_source_on_subscription_order( $order, $source );
	}

	/**
	 * Klarna needs the payment method saved for off-session use and the subscription details
	 * (payment_method_options[klarna][subscriptions]) on the first payment and on every renewal,
	 * with the same reference. Card mandate options added by the subscriptions trait don't apply.
	 *
	 * @param array     $data            PaymentIntent or SetupIntent request.
	 * @param \WC_Order $order           Order (or subscription, when changing its payment method).
	 * @param bool      $is_setup_intent True for a SetupIntent (free trial / payment method change).
	 *
	 * @return array
	 */
	public function add_klarna_subscription_data( $data, $order, $is_setup_intent = false ) {
		if ( ! $order instanceof \WC_Order || ! in_array( $this->payment_method_types, (array) ( $data['payment_method_types'] ?? array() ), true ) ) {
			return $data;
		}
		$is_subscription = function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order );
		if ( ! $is_subscription && $order->get_payment_method() !== $this->id ) {
			return $data;
		}

		$is_renewal    = 0 < did_action( 'woocommerce_scheduled_subscription_payment_' . $this->id );
		$subscriptions = array();
		if ( $is_subscription ) {
			$subscriptions = array( $order );
		} elseif ( $is_renewal && function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
		} elseif ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => array( 'parent', 'switch', 'renewal' ) ) );
		}
		if ( empty( $subscriptions ) ) {
			return $data;
		}

		unset( $data['payment_method_options']['card'] );
		if ( $is_setup_intent ) {
			unset( $data['mandate_data'] );
			$data['usage']                                        = 'off_session';
			$data['payment_method_options']['klarna']['currency'] = strtolower( $order->get_currency() );
		} elseif ( ! $is_renewal ) {
			$data['setup_future_usage'] = 'off_session';
		}

		$klarna_subscriptions = array();
		foreach ( $subscriptions as $subscription ) {
			$item = array(
				'reference'      => 'wcs_' . $subscription->get_id(),
				'interval'       => $subscription->get_billing_period(),
				'interval_count' => (int) $subscription->get_billing_interval(),
			);
			$next = $subscription->get_time( 'next_payment' );
			/** A renewal (scheduled or early) pushes the next payment one interval further once paid */
			if ( $next && ( $is_renewal || ( function_exists( 'wcs_order_contains_early_renewal' ) && wcs_order_contains_early_renewal( $order ) ) ) ) {
				$next = wcs_add_time( $subscription->get_billing_interval(), $subscription->get_billing_period(), $next );
			}
			if ( $next && 0 < (float) $subscription->get_total( 'edit' ) ) {
				$item['next_billing'] = array(
					'amount' => Helper::get_stripe_amount( $subscription->get_total( 'edit' ), strtolower( $subscription->get_currency() ) ),
					'date'   => gmdate( 'Y-m-d', $next ),
				);
			}
			$klarna_subscriptions[] = $item;
		}
		$data['payment_method_options']['klarna']['subscriptions'] = $klarna_subscriptions;

		return $data;
	}

	protected function override_defaults() {
		$this->supported_currency          = array( 'EUR', 'DKK', 'GBP', 'NOK', 'SEK', 'USD', 'CZK', 'AUD', 'NZD', 'CAD', 'CHF', 'PLN' );
		$this->specific_country            = array( 'AU', 'CA', 'US', 'DK', 'NO', 'SE', 'GB', 'PL', 'CH', 'NZ', 'AT', 'BE', 'DE', 'ES', 'FI', 'FR', 'GR', 'IE', 'IT', 'NL', 'PT' );
		$this->except_country              = array();
		$this->setting_enable_label        = __( 'Enable Klarna', 'funnelkit-stripe-woo-payment-gateway' );
		$this->setting_title_default       = __( 'Klarna - Pay Over Time', 'funnelkit-stripe-woo-payment-gateway' );
		$this->setting_description_default = __( 'After clicking the checkout button, you will be redirected to Klarna to <br> complete your purchase securely', 'funnelkit-stripe-woo-payment-gateway' );
	}

	public function init_form_fields() {

		$settings = array(
			'enabled'          => array(
				'label'   => ' ',
				'type'    => 'checkbox',
				'title'   => $this->setting_enable_label,
				'default' => 'no',
			),
			'title'            => array(
				'title'       => __( 'Title', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Change the payment gateway title that appears on the checkout.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => $this->setting_title_default,
				'desc_tip'    => true,
			),
			'description'      => array(
				'title'       => __( 'Description', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'textarea',
				'css'         => 'width:25em',
				'description' => __( 'Change the payment gateway description that appears on the checkout.', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => $this->setting_description_default,
				'desc_tip'    => true,
			),
			'charge_type'      => array(
				'title'       => __( 'Charge Type', 'funnelkit-stripe-woo-payment-gateway' ),
				'type'        => 'select',
				'description' => $this->get_charge_type_recommendation_text(),
				'default'     => 'automatic',
				'options'     => array(
					'automatic' => __( 'Charge', 'funnelkit-stripe-woo-payment-gateway' ),
					'manual'    => __( 'Authorize', 'funnelkit-stripe-woo-payment-gateway' ),
				),
				'desc_tip'    => false,
			),
			'paylater_section' => array(
				'title'       => __( 'Klarna Message Location', 'funnelkit-stripe-woo-payment-gateway' ),
				'default'     => array( 'cart' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'css'         => 'min-width: 350px;',
				'desc_tip'    => true,
				/* translators: gateway title */
				'description' => sprintf( __( 'This option lets you limit the %1$s Messages to Specific Pages.', 'funnelkit-stripe-woo-payment-gateway' ), $this->method_title ),
				'options'     => array(
					'product' => __( 'Product Page', 'funnelkit-stripe-woo-payment-gateway' ),
					'cart'    => __( 'Cart Page', 'funnelkit-stripe-woo-payment-gateway' ),
					'shop'    => __( 'Shop/Categories Page', 'funnelkit-stripe-woo-payment-gateway' ),
				),
			),
		);

		$stripe_account_settings = get_option( 'fkwcs_stripe_account_settings', array() );

		$admin_country = ! empty( $stripe_account_settings ) ? strtoupper( $stripe_account_settings['country'] ) : wc_format_country_state_string( get_option( 'woocommerce_default_country', '' ) )['country'];

		/**
		 * WooCommerce lists Puerto Rico as its own country code (PR), but Stripe and Klarna
		 * treat it as part of the United States, so a US account transacts with PR buyers too.
		 */
		if ( 'PR' === $admin_country ) {
			$admin_country = 'US';
		}

		if ( in_array( $admin_country, $this->specific_country, true ) ) {

			if ( in_array( $admin_country, $this->list_of_eaa_and_supported_across_countries(), true ) ) {

				$this->specific_country = $this->list_of_eaa_and_supported_across_countries();
			} elseif ( 'US' === $admin_country ) {
				$this->specific_country = array( 'US', 'PR' );
			} else {
				$this->specific_country = array( $admin_country );
			}
		} else {
			$this->specific_country = array();
		}
		$countries_fields = $this->get_countries_admin_fields( $this->selling_country_type, $this->except_country, $this->specific_country );
		if ( isset( $countries_fields['allowed_countries']['options']['all'] ) ) {
			unset( $countries_fields['allowed_countries']['options']['all'] );
		}

		if ( isset( $countries_fields['allowed_countries']['options']['all_except'] ) ) {
			unset( $countries_fields['allowed_countries']['options']['all_except'] );
		}

		if ( isset( $countries_fields['except_countries'] ) ) {
			unset( $countries_fields['except_countries'] );
		}
		$countries_fields['specific_countries']['options'] = $this->specific_country;
		$this->form_fields                                 = apply_filters( $this->id . '_payment_form_fields', array_merge( $settings, $countries_fields ) );
	}

	public function list_of_eaa_and_supported_across_countries() {
		$countryCodes = array(
			'AT', // Austria - EEA
			'BE', // Belgium - EEA
			'DK', // Denmark - EEA
			'FI', // Finland - EEA
			'FR', // France - EEA
			'DE', // Germany - EEA
			'GR', // Greece - EEA
			'IE', // Ireland - EEA
			'IT', // Italy - EEA
			'NL', // Netherlands - EEA
			'NO', // Norway - EEA
			'NZ', // New Zealand - NZ
			'PL', // Poland - EEA
			'PT', // Portugal - EEA
			'ES', // Spain - EEA
			'SE', // Sweden - EEA
			'CH', // Switzerland
			'GB',  // United Kingdom - GB
		);

		return $countryCodes;
	}

	/**
	 * @param \stdclass $intent
	 * @param \WC_Order $order
	 *
	 * @return void
	 */
	public function handle_webhook_intent_succeeded( $intent, $order ) {

		if ( false === wc_string_to_bool( $this->enabled ) ) {
			return;
		}

		if ( ! $order instanceof \WC_Order || $order->get_payment_method() !== $this->id || $order->is_paid() || ! is_null( $order->get_date_paid() ) || $order->has_status( 'wfocu-pri-order' ) ) {
			return;
		}

		$save_intent = $this->get_intent_from_order( $order );
		if ( empty( $save_intent ) ) {
			Helper::log( 'Could not find intent in the order handle_webhook_intent_succeeded ' . $order->get_id() );

			return;
		}

		if ( class_exists( '\WFOCU_Core' ) ) {
			Helper::log( $order->get_id() . ' :: Saving meta data during webhook to later process this order' );

			$order->update_meta_data( '_fkwcs_webhook_paid', 'yes' );
			$order->save_meta_data();
		} else {

			try {
				Helper::log( $order->get_id() . ' :: Processing order during webhook' );

				$this->handle_intent_success( $intent, $order );

			} catch ( \Exception $e ) {

			}
		}
	}
}
