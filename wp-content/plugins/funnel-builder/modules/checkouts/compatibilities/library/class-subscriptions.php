<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

#[AllowDynamicProperties]
class WFACP_Compatibility_Subscription {
	private static $instance;
	private $filters_added = false;

	/**
	 * The cart row Subscriptions is currently printing the recurring price for, or null.
	 *
	 * @var array|null
	 */
	private $price_string_cart_item = null;

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

		// Apply purchasability filters during cart addition to ensure all variations can be added
		// Without this, WCS 2.0+ limitations prevent variations from being added to cart
		if ( $this->should_apply_purchasability_fix() ) {
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'before' ), 5 );
			add_action( 'wfacp_after_add_to_cart', array( $this, 'after' ), 5 );
		}

		$this->remove_filter();

		// Wrap the gift notice so native WooCommerce Subscriptions JS can find it
		add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'wrap_gift_notice_start' ), 5 );
		add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'wrap_gift_notice_end' ), 15 );

		add_action( 'wfacp_internal_css', array( $this, 'add_css' ) );

		// Subscriptions 9.0.1+ renders the recurring price itself; see price_string_uses_line_quantity().
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'capture_cart_item_for_price_string' ), 9, 3 );
		add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'release_cart_item_for_price_string' ), 11, 3 );
		add_filter( 'woocommerce_subscriptions_product_price_string_inclusions', array( $this, 'price_string_uses_line_quantity' ), 10, 2 );
		add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( $this, 'sign_up_fee_uses_line_quantity' ), 10, 2 );
	}

	/**
	 * Hold the row while Subscriptions builds its price string.
	 *
	 * WC_Subscriptions_Cart::checkout_cart_item_details() runs on this filter at priority 10 and
	 * calls get_price_string() from inside it. That is the only point where the cart row and the
	 * price string exist together, so the row is held either side of it.
	 *
	 * @param string $quantity_html Quantity markup, returned untouched.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return string
	 */
	public function capture_cart_item_for_price_string( $quantity_html, $cart_item, $cart_item_key ) {
		$this->price_string_cart_item = $cart_item;

		return $quantity_html;
	}

	/**
	 * Release the row once Subscriptions is done with it.
	 *
	 * @param string $quantity_html Quantity markup, returned untouched.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return string
	 */
	public function release_cart_item_for_price_string( $quantity_html, $cart_item, $cart_item_key ) {
		$this->price_string_cart_item = null;

		return $quantity_html;
	}

	/**
	 * Show the recurring price for the whole line instead of for one unit.
	 *
	 * Subscriptions sources the amount from wcs_get_price_excluding_tax( $product ), which is the
	 * price of a single unit, because its own checkout prints "x 2" beside it for the reader to
	 * multiply. A two-pack therefore read "$49.00 / month" on a line billing $98.00. Multiplying by
	 * the line quantity puts the string in the same terms as the price column next to it.
	 *
	 * The amount is rebuilt from the recurring price rather than taken from line_subtotal on
	 * purpose: for a free trial or a sign-up fee the cart line holds what is due today, not what
	 * recurs, and this string describes the recurring charge.
	 *
	 * A bundle or composite container is priced by Subscriptions as the aggregate of itself and its
	 * child items for one container, so that aggregate is what gets multiplied; the container's own
	 * price is often 0 when the children carry the prices.
	 *
	 * @param array      $include Price string parts, with 'price' pre-formatted by Subscriptions.
	 * @param WC_Product $product Subscription product.
	 *
	 * @return array
	 */
	public function price_string_uses_line_quantity( $include, $product ) {
		$cart_item = $this->get_priced_cart_item( $product );

		if ( is_null( $cart_item ) || empty( $include['price'] ) ) {
			return $include;
		}

		$quantity = $this->get_line_quantity( $cart_item );

		if ( $quantity <= 1 ) {
			return $include;
		}

		$tax_calculation = isset( $include['tax_calculation'] ) ? $include['tax_calculation'] : '';
		$bundle_type     = $this->get_bundle_type( $cart_item );

		if ( 'container' === $bundle_type && method_exists( 'WCS_ATT_Integration_PB_CP', 'get_container_aggregate_price' ) ) {
			$line_price = (float) WCS_ATT_Integration_PB_CP::get_container_aggregate_price( $cart_item, $tax_calculation ) * $quantity;
		} elseif ( '' === $bundle_type ) {
			// Tax on the whole line, not on one rounded unit, so it matches the cart total.
			$line_price = ( 'incl' === $tax_calculation ) ? wcs_get_price_including_tax( $product, array( 'qty' => $quantity ) ) : wcs_get_price_excluding_tax( $product, array( 'qty' => $quantity ) );
		} else {
			return $include;
		}

		$include['price'] = wc_price( $line_price );

		return $include;
	}

	/**
	 * Put the "Sign-up fee:" line in the same terms as the recurring price: the whole line, not one unit.
	 *
	 * A bundle or composite child is left alone: Subscriptions only prints details for the container,
	 * and passes the container's own fee here, not an aggregate.
	 *
	 * @param float|string $sign_up_fee Sign-up fee for one unit.
	 * @param WC_Product   $product     Subscription product.
	 *
	 * @return float|string
	 */
	public function sign_up_fee_uses_line_quantity( $sign_up_fee, $product ) {
		$cart_item = $this->get_priced_cart_item( $product );

		if ( empty( $sign_up_fee ) || is_null( $cart_item ) || 'child' === $this->get_bundle_type( $cart_item ) ) {
			return $sign_up_fee;
		}

		$quantity = $this->get_line_quantity( $cart_item );

		return $quantity > 1 ? (float) $sign_up_fee * $quantity : $sign_up_fee;
	}

	/**
	 * The held cart row, when Subscriptions is pricing that row's product on a FunnelKit checkout.
	 *
	 * wfacp_template() is the signal that holds for both a page load and the update_order_review
	 * fragment. $is_checkout is only set while a page request resolves the checkout post, and
	 * _wfacp_post_id is not a request key during the fragment: WooCommerce serialises the whole form
	 * into a single post_data parameter.
	 *
	 * @param WC_Product $product Product Subscriptions is pricing.
	 *
	 * @return array|null
	 */
	private function get_priced_cart_item( $product ) {
		$cart_item = $this->price_string_cart_item;

		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product || ! $product instanceof WC_Product || is_null( wfacp_template() ) ) {
			return null;
		}

		return $cart_item['data']->get_id() === $product->get_id() ? $cart_item : null;
	}

	/**
	 * Quantity of a cart row. wc_stock_amount() keeps a decimal-quantity store's 0.5 instead of zeroing it.
	 *
	 * @param array $cart_item Cart item.
	 *
	 * @return float|int
	 */
	private function get_line_quantity( $cart_item ) {
		return isset( $cart_item['quantity'] ) ? wc_stock_amount( $cart_item['quantity'] ) : 1;
	}

	/**
	 * Whether a row is a bundle/composite container, one of its child items, or neither.
	 *
	 * Same test as WC_Subscriptions_Cart::is_bundle_type_cart_line(), which is private.
	 *
	 * @param array $cart_item Cart item.
	 *
	 * @return string 'container', 'child' or ''.
	 */
	private function get_bundle_type( $cart_item ) {
		if ( ! class_exists( 'WCS_ATT_Integration_PB_CP' ) ) {
			return '';
		}

		if ( WCS_ATT_Integration_PB_CP::is_bundle_type_container_cart_item( $cart_item ) ) {
			return 'container';
		}

		return WCS_ATT_Integration_PB_CP::is_bundle_type_cart_item( $cart_item ) ? 'child' : '';
	}

	public function remove_filter() {
		remove_filter( 'woocommerce_cart_totals_coupon_html', array( 'WC_Subscriptions_Coupon', 'mark_recurring_coupon_in_initial_cart_for_hiding' ), 10 );
	}

	public static function is_enabled() {
		return class_exists( 'WC_Subscriptions' );
	}

	private function should_apply_purchasability_fix() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		try {
			if ( ! class_exists( 'WFACP_Common_Helper' ) || ! method_exists( 'WFACP_Common_Helper', 'get_subscription_version' ) ) {
				return true;
			}

			$version = WFACP_Common_Helper::get_subscription_version();
			if ( empty( $version ) ) {
				return true;
			}

			// Apply fix for WCS 2.0.0+ where product limitations became stricter
			return version_compare( $version, '2.0.0', '>=' );
		} catch ( Throwable $e ) {
			return true;
		}
	}

	public function before() {
		try {
			if ( ! self::is_enabled() ) {
				return;
			}

			$this->filters_added = true;

			// Priority 9999 ensures we override WCS_Limiter (priority 12) to bypass subscription limitations
			add_filter( 'woocommerce_subscriptions_product_limitation', array( $this, 'allow_product_purchasable' ), 9999 );
			add_filter( 'woocommerce_subscription_variation_is_purchasable', array( $this, 'allow_product_purchasable' ), 9999 );
			// Only override for subscription products to avoid affecting regular products
			add_filter( 'woocommerce_is_purchasable', array( $this, 'maybe_allow_subscription_purchasable' ), 9999, 2 );
		} catch ( Throwable $e ) {
			$this->filters_added = false;
		}
	}

	public function after() {
		try {
			if ( $this->filters_added ) {
				remove_filter( 'woocommerce_subscriptions_product_limitation', array( $this, 'allow_product_purchasable' ), 9999 );
				remove_filter( 'woocommerce_subscription_variation_is_purchasable', array( $this, 'allow_product_purchasable' ), 9999 );
				remove_filter( 'woocommerce_is_purchasable', array( $this, 'maybe_allow_subscription_purchasable' ), 9999 );
				$this->filters_added = false;
			}
		} catch ( Throwable $e ) {
			$this->filters_added = false;
		}
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
		return true;
	}

	public function maybe_allow_subscription_purchasable( $is_purchasable, $product ) {
		try {
			if ( ! $product instanceof WC_Product ) {
				return $is_purchasable;
			}

			// Only override for subscription products to avoid affecting regular products
			$subscription_types = WFACP_Common::get_subscription_product_type();
			if ( ! empty( $subscription_types ) && in_array( $product->get_type(), $subscription_types, true ) ) {
				// Only change if product was blocked - preserves existing purchasable status
				if ( ! $is_purchasable ) {
					return true;
				}
			}

			return $is_purchasable;
		} catch ( Throwable $e ) {
			return $is_purchasable;
		}
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

			$regular_org = isset( $price_data['regular_org'] ) ? floatval( $price_data['regular_org'] ) : 0;
			$price       = isset( $price_data['price'] ) ? floatval( $price_data['price'] ) : 0;

			if ( $regular_org > 0 && round( $price, 2 ) !== round( $regular_org, 2 ) && $price < $regular_org ) {
				$price_output = wc_format_sale_price( $main_product_price, $price_html );
			} else {
				$price_output = wc_price( $price_html );
			}

			// Product-switcher context only; cart lines use wfacp_subscription_price_display with ( $html, $_product, $cart_item, $cart_item_key ).
			echo apply_filters( 'wfacp_subscription_price_display_switcher', $price_output, $pro, $price_data, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- woocommerce_form_field() output; contains form controls (B0 register).
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
				function wcs_cart_coupon_remove_link_html( $coupon ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- guarded polyfill of the WooCommerce Subscriptions function for WCS < 2.4.
					// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Using WooCommerce Subscriptions text domain for compatibility
					$html = '<a href="' . esc_url( add_query_arg( 'remove_coupon', urlencode( wcs_get_coupon_property( $coupon, 'code' ) ), defined( 'WOOCOMMERCE_CHECKOUT' ) ? wc_get_checkout_url() : wc_get_cart_url() ) ) . '" class="woocommerce-remove-coupon" data-coupon="' . esc_attr( wcs_get_coupon_property( $coupon, 'code' ) ) . '">' . __( '[Remove]', 'woocommerce-subscriptions' ) . '</a>';
					echo wp_kses( $html, array_replace_recursive( wp_kses_allowed_html( 'post' ), array( 'a' => array( 'data-coupon' => true ) ) ) );
				}
			}
		}
		WFACP_Compatibility_Subscription::getInstance();
	}
);
