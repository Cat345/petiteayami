<?php

#[AllowDynamicProperties]
class WFACP_Compatibility_Subscription {
	private static $instance;

	/**
	 * @return mixed
	 */
	public static function getInstance() {
		if ( self::$instance == null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'wfacp_show_product_price', array( $this, 'stop_printing_price' ), 10, 2 );
		add_action( 'wfacp_show_product_price_placeholder', array( $this, 'display_price' ), 10, 3 );
		add_action( 'wfacp_before_product_switcher_html', array( $this, 'before' ) );
		add_action( 'wfacp_after_product_switcher_html', array( $this, 'after' ) );
		add_action( 'wfacp_after_coupon_html', array( $this, 'add_hidden_html' ) );
		if(class_exists( 'WCSG_Checkout' )) {
			remove_action( 'woocommerce_before_checkout_shipping_form', array( 'WCSG_Checkout', 'maybe_display_recipient_shipping_notice' ), 10 );
		}

		$this->remove_filter();

		// Wrap the gift notice so native WooCommerce Subscriptions JS can find it
		add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'wrap_gift_notice_start' ), 5 );
		add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'wrap_gift_notice_end' ), 15 );

		add_action( 'wfacp_internal_css', array( $this, 'add_css' ) );
	}

	public function remove_filter() {
		remove_filter( 'woocommerce_cart_totals_coupon_html', array( 'WC_Subscriptions_Coupon', 'mark_recurring_coupon_in_initial_cart_for_hiding' ), 10 );
	}

	public static function is_enabled() {
		return class_exists( 'WC_Subscriptions' );
	}


	public function before() {

		add_filter( 'woocommerce_subscriptions_product_limitation', array( $this, 'allow_product_purchasable' ), 9999 );
	}

	public function after() {
		remove_filter( 'woocommerce_subscriptions_product_limitation', array( $this, 'allow_product_purchasable' ), 9999 );
	}

	public function add_hidden_html( $coupon ) {
		if ( ! class_exists( 'WC_Subscriptions_Cart', false ) || ! method_exists( 'WC_Subscriptions_Cart', 'all_cart_items_have_free_trial' ) ) {
			return;
		}

		if ( WC_Subscriptions_Cart::all_cart_items_have_free_trial() && in_array(
			wcs_get_coupon_property( $coupon, 'discount_type' ),
			array(
				'recurring_fee',
				'recurring_percent',
			)
		) ) {
			echo '<span class="wcs-hidden-coupon" type="hidden"></span>';
		}
	}

	/**
	 * @param $status
	 * @param $product \WC_Product;
	 */
	public function allow_product_purchasable() {
		return 'no';
	}

	/**
	 * @param $status boolean
	 * @param $pro WC_Product
	 *
	 * @return bool
	 */
	public function stop_printing_price( $status, $pro ) {
		if ( in_array( $pro->get_type(), WFACP_Common::get_subscription_product_type() ) ) {
			$status = false;
		}

		return $status;
	}

	/**
	 * @param $pro WC_Product
	 * @param $cart_item_key String
	 * @param $price_data []
	 */
	public function display_price( $pro, $cart_item_key, $price_data ) {
		/**
		 * @var $pro WC_Product
		 */
		if ( in_array( $pro->get_type(), WFACP_Common::get_subscription_product_type() ) ) {
			/**
			 * @var $temp WC_Product
			 */
			$temp                  = wc_get_product( $pro->get_id() );
			$s_price_data          = $price_data;
			$s_price_data['price'] = $s_price_data['regular_org'];
			$main_product_price    = WFACP_Common::get_subscription_price( $temp, $s_price_data );
			if ( '' !== $cart_item_key ) {
				$price_html = $price_data['price'];
			} else {

				$price_html = WFACP_Common::get_subscription_price( $pro, $price_data );
			}

			if ( $main_product_price == $price_html || $price_html > $main_product_price ) {
				echo wc_price( $price_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo wc_format_sale_price( $main_product_price, $price_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Check if gift notice should be wrapped.
	 * Uses the same logic as WooCommerce Subscriptions plugin.
	 *
	 * @return bool
	 */
	private function should_wrap_gift_notice() {
		if ( ! class_exists( 'WCSG_Admin' ) || ! class_exists( 'WCSG_Cart' ) ) {
			return false;
		}

		// Same conditions as WCSG_Checkout::maybe_display_recipient_shipping_notice()
		return WCSG_Admin::is_gifting_enabled() && WCSG_Cart::contains_gift_recipient_email();
	}

	/**
	 * Wrap the gift notice in woocommerce-shipping-fields so native plugin JS can find it.
	 * Hooked at priority 5 (before notice output at priority 10).
	 */
	public function wrap_gift_notice_start() {
		if ( ! $this->should_wrap_gift_notice() ) {
			return;
		}

		echo '<div class="wfacp-form-control-wrapper wfacp-col-full woocommerce-shipping-fields wfacp-woocommerce-shipping-fields">';
	}

	/**
	 * Close the wrapper div for gift notice.
	 * Hooked at priority 15 (after notice output at priority 10).
	 */
	public function wrap_gift_notice_end() {
		if ( ! $this->should_wrap_gift_notice() ) {
			return;
		}

		echo '</div>';
	}

	public function add_css() {
		?>

		<style>
			body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .wfacp-form-control-wrapper.wfacp-wc-subscription-gift .woocommerce-info {
				padding-bottom: 0 !important;    color: #969595;
			}

			body .wcsg_add_recipient_fields_container label {
				margin: 0;
				font-weight: normal;
				line-height: 1;
			}
			body .wcsg_add_recipient_fields_container{
				margin: 4px 0;
			}
			#wfacp-e-form .wfacp_main_form .wcsg_add_recipient_fields_container input[type=checkbox] {
				position: relative;
				left: auto;
				right: auto;
				bottom: auto;
				top: auto;
				margin: 0 4px 0 0;
			}
			body .wcsg_add_recipient_fields_container .wcsg_add_recipient_fields input {
				padding: 8px !important;
				height: auto !important;
				min-height: 1px !important;
			}

			body .wcsg_add_recipient_fields_container .wcsg_add_recipient_fields .woocommerce_subscriptions_gifting_recipient_email {
				margin-bottom: 16px;
			}
			body .wcsg_add_recipient_fields {
				margin-top: 8px;
			}
			body #shortcode-validate-error-invalid-gifting-recipient span,
			body #wfacp-e-form #shortcode-validate-error-invalid-gifting-recipient span{
				color: var(--wc-red, #cc1818);
				font-size: 12px;
				font-weight: 500;
				font-style: normal;
				line-height: 16px;

			}
			body #wfacp-e-form .wcsg_add_recipient_fields_container .wcsg_add_recipient_fields .woocommerce_subscriptions_gifting_recipient_email{
				margin-bottom: 0;
			}

			body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .wfacp-form-control-wrapper.wfacp-col-full.woocommerce-shipping-fields.wfacp-woocommerce-shipping-fields .woocommerce-info {
				padding-bottom: 0 !important;
			}

		</style>

		<?php
	}
}

add_action(
	'wfacp_after_template_found',
	function () {
		if ( ! WFACP_Compatibility_Subscription::is_enabled() ) {
			return;
		}
		if ( ! function_exists( 'wcs_cart_coupon_remove_link_html' ) ) {
			if ( version_compare( WFACP_Common_Helper::get_subscription_version(), '2.4.0', '<' ) ) {
				function wcs_cart_coupon_remove_link_html( $coupon ) {
					$html = '<a href="' . esc_url( add_query_arg( 'remove_coupon', urlencode( wcs_get_coupon_property( $coupon, 'code' ) ), defined( 'WOOCOMMERCE_CHECKOUT' ) ? wc_get_checkout_url() : wc_get_cart_url() ) ) . '" class="woocommerce-remove-coupon" data-coupon="' . esc_attr( wcs_get_coupon_property( $coupon, 'code' ) ) . '">' . __( '[Remove]', 'woocommerce-subscriptions' ) . '</a>';
					echo wp_kses( $html, array_replace_recursive( wp_kses_allowed_html( 'post' ), array( 'a' => array( 'data-coupon' => true ) ) ) );
				}
			}
		}
		WFACP_Compatibility_Subscription::getInstance();
	}
);
