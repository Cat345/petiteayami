<?php
/**
 * Stripe Calculation Tracker.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe;

// Exit if script started not from WordPress.
defined( 'ABSPATH' ) || exit;

use WP_Post;
use Stripe\StripeTaxForWooCommerce\WordPress\Options;

/**
 * Stripe Calculation Tracker service
 */
class StripeCalculationTracker {
	/**
	 * Should calculate variable
	 *
	 * @var bool
	 */
	private static $should_calculate = false;

	/**
	 * Subscribe to hooks
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action(
			'rest_dispatch_request',
			function ( $_, $request ) {
				$requests = $request->get_param( 'requests' );
				if ( ! empty( $requests ) ) {
					$data = reset( $requests );
					if ( array_key_exists( 'path', $data ) && strpos( $data['path'], 'cart' ) !== false ) {
						if ( ! Options::is_skip_cart_tax_calculation() || strpos( $data['path'], 'checkout' ) !== false || self::is_checkout_cart_endpoint( $data['path'] ) || self::is_checkout_request_context() ) {
							self::$should_calculate = true;
						}
					}
				}
			},
			10,
			2
		);

		add_action(
			'admin_init',
			function () {
				// @phpstan-ignore-next-line
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) && 'woocommerce_calc_line_taxes' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
					self::$should_calculate = true;
				}
			}
		);
	}

	/**
	 * REST API endpoints that should always allow a Stripe tax calculation.
	 *
	 * @var string[]
	 */
	public static $valid_rest_urls = array(
		// WooCommerce core REST endpoints commonly used by the mobile app.
		'/wp-json/wc/v3/orders',
		'/wp-json/wc/v2/orders',
		'/wp-json/wc/v1/orders',
		'/wc/v3/orders',
		'/wc/v2/orders',
		'/wc/v1/orders',

		// Jetpack Tunnel endpoints for WordPress.com authentication.
		'/?rest_route=%2Fwc%2Fv3%2Forders',
		'/?rest_route=%2Fwc%2Fv2%2Forders',
		'/?rest_route=%2Fwc%2Fv1%2Forders',

		// Store API (Cart/Checkout) endpoints.
		'/wp-json/wc/store/v1/checkout',
		'/wp-json/wc/store/v1/cart',
		'/wp-json/wc/store/v1/cart/update-customer',

		'rest_route=/wc/store/v1/checkout',
		'rest_route=%2Fwc%2Fstore%2Fv1%2Fcheckout',
		'rest_route=/wc/store/v1/cart',
		'rest_route=%2Fwc%2Fstore%2Fv1%2Fcart',
		'rest_route=/wc/store/v1/cart/update-customer',
		'rest_route=%2Fwc%2Fstore%2Fv1%2Fcart%2Fupdate-customer',
	);

	/**
	 * Get the allow-listed REST endpoints (filterable).
	 *
	 * @return string[]
	 */
	public static function get_valid_rest_urls(): array {
		$urls = apply_filters( 'stripe_tax_valid_rest_urls', self::$valid_rest_urls );
		return is_array( $urls ) ? $urls : array();
	}

	/**
	 * Check if a calculation is needed.
	 *
	 * @return bool
	 */
	public static function is_calculation_needed(): bool {
		try {
			if ( apply_filters( 'stripe_tax_skip_calculation', false ) ) {
				return false;
			}

			$skip_cart = Options::is_skip_cart_tax_calculation();

			if ( self::$should_calculate || ( ! $skip_cart && is_cart() ) || is_checkout() ) {
				return true;
			}

			if ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_SERVER['REQUEST_URI'] ) ) {
				$request_uri = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
				foreach ( self::get_valid_rest_urls() as $valid_rest_url ) {
					if ( strpos( $request_uri, $valid_rest_url ) !== false ) {
						if ( $skip_cart && self::is_cart_rest_url( $valid_rest_url ) && ! self::is_checkout_cart_endpoint( $request_uri ) && ! self::is_checkout_request_context() ) {
							continue;
						}
						return true;
					}
				}
			}

			$cart_page_id     = wc_get_page_id( 'cart' );
			$checkout_page_id = wc_get_page_id( 'checkout' );

			if ( get_queried_object_id() === $checkout_page_id ) {
				return true;
			}

			if ( ! $skip_cart && get_queried_object_id() === $cart_page_id ) {
				return true;
			}

			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$request = wp_parse_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
				$request = $request ? untrailingslashit( $request ) : '';

				if ( ! $skip_cart ) {
					$cart_url  = get_permalink( $cart_page_id );
					$cart_path = wp_parse_url( $cart_url, PHP_URL_PATH );
					$cart_path = $cart_path ? untrailingslashit( $cart_path ) : '';

					// Avoid false positives on plain permalinks where cart path becomes '/'.
					if ( $cart_path && '/' !== $cart_path && $request === $cart_path ) {
						return true;
					}
				}

				$checkout_url  = get_permalink( $checkout_page_id );
				$checkout_path = wp_parse_url( $checkout_url, PHP_URL_PATH );
				$checkout_path = $checkout_path ? untrailingslashit( $checkout_path ) : '';

				// Avoid false positives on plain permalinks where checkout path becomes '/'.
				if ( $checkout_path && '/' !== $checkout_path && $request === $checkout_path ) {
					return true;
				}
			}

			if ( ! $skip_cart && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-cart-nonce'] ?? '' ) ), 'woocommerce-cart' ) ) {
				return true;
			}

			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ?? '' ) ), 'woocommerce-process_checkout' ) ) {
				return true;
			}

			global $post;
			if ( $post instanceof WP_Post ) {
				$is_cart_content     = has_shortcode( $post->post_content, 'woocommerce_cart' ) ||
					strpos( $post->post_content, 'wp:woocommerce/cart' ) !== false;
				$is_checkout_content = has_shortcode( $post->post_content, 'woocommerce_checkout' ) ||
					strpos( $post->post_content, 'wp:woocommerce/checkout' ) !== false;

				if ( $is_checkout_content ) {
					return true;
				}

				if ( ! $skip_cart && $is_cart_content ) {
					return true;
				}
			}

			if ( $skip_cart ) {
				return self::is_checkout_ajax_context() || self::is_order_received_page();
			}

			return self::is_cart_ajax_context() || self::is_checkout_ajax_context() || self::is_order_received_page();
		} catch ( \Throwable $e ) {
			// TODO: add logger after issue #41 is done.
			return false;
		}
	}



	/**
	 * Check if the current REST request originates from the checkout page.
	 *
	 * @return bool
	 */
	private static function is_checkout_request_context(): bool {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			return false;
		}

		$referer          = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		$checkout_page_id = wc_get_page_id( 'checkout' );

		if ( $checkout_page_id <= 0 ) {
			return false;
		}

		$referer_post_id = url_to_postid( $referer );

		return $referer_post_id > 0 && $referer_post_id === $checkout_page_id;
	}

	/**
	 * Check if the given path is a checkout-specific cart endpoint.
	 *
	 * @param string $path The REST API path.
	 *
	 * @return bool
	 */
	private static function is_checkout_cart_endpoint( string $path ): bool {
		return strpos( $path, 'update-customer' ) !== false
			|| strpos( $path, 'select-shipping-rate' ) !== false;
	}

	/**
	 * Check if a REST URL is a cart-only URL (not checkout).
	 *
	 * @param string $url The REST URL to check.
	 *
	 * @return bool
	 */
	private static function is_cart_rest_url( string $url ): bool {
		if ( strpos( $url, 'checkout' ) !== false ) {
			return false;
		}
		return strpos( $url, 'cart' ) !== false;
	}

	/**
	 * Check if calculation is needed in cart ajax context
	 *
	 * @return bool
	 */
	private static function is_cart_ajax_context(): bool {
		$cart_ajax_actions = array(
			'update_cart',
			'apply_coupon',
			'remove_coupon',
			'get_cart_totals',
			'get_refreshed_fragments',
			'update_shipping_method',
			'checkout_coupon',
		);

		// @phpstan-ignore-next-line
		return defined( 'DOING_AJAX' ) && DOING_AJAX &&
			isset( $_GET['wc-ajax'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
			in_array( sanitize_text_field( $_GET['wc-ajax'] ), $cart_ajax_actions, true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
	}

	/**
	 * Check if calculation is needed in checkout ajax context
	 *
	 * @return bool
	 */
	private static function is_checkout_ajax_context(): bool {
		$checkout_ajax_actions = array(
			'update_order_review',
			'checkout',
			'checkout_coupon',
			// Express checkout actions used by the WooCommerce Stripe gateway.
			'wc_stripe_get_shipping_options',
			'wc_stripe_create_order',
			'wc_stripe_update_payment_intent',
			// Stripe gateway updates Checkout Session / PaymentIntent through this endpoint.
			'wc_stripe_update_checkout_session',
		);

		// @phpstan-ignore-next-line
		return defined( 'DOING_AJAX' ) && DOING_AJAX &&
			isset( $_GET['wc-ajax'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
			in_array( sanitize_text_field( $_GET['wc-ajax'] ), $checkout_ajax_actions, true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
	}

	/**
	 * Check if calculation is needed after clicking place order
	 *
	 * @return bool
	 */
	private static function is_order_received_page(): bool {
		return is_page( wc_get_page_id( 'checkout' ) ) &&
			isset( $_GET['key'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
			isset( $_GET['order-received'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only request inspection.
	}
}
