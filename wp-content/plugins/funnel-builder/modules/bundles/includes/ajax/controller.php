<?php
/** Ajax\Controller — one place to register admin-ajax actions + shared helpers. */
namespace WFFN\Bundles\Includes\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Compat\Funnel_Builder\Adapter as Funnel_Builder_Adapter;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Helpers\Request_Context;
use WFFN\Bundles\Includes\Skins\Options_Display;
use WFFN\Bundles\Includes\Helpers\Markup;
use WFFN\Bundles\Includes\Helpers\WooCommerce;

/**
 * Registers this plugin's admin-ajax actions and the helpers they share.
 */
class Controller {

	/** Shared nonce action for every front-end → admin-ajax call. */
	const NONCE = 'wffn_bundles_ajax';

	/**
	 * Action map: ajax action => [ callback, public ].
	 *
	 * `public` also registers the logged-out (nopriv) handler. Add new
	 * front-end endpoints here rather than scattering add_action() calls.
	 */
	private function routes() {
		/**
		 * Filters the front-end AJAX action map.
		 *
		 * An add-on registers its own endpoints here rather than calling
		 * add_action() itself, so every route goes through the same nonce and
		 * nopriv handling below.
		 *
		 * @since 1.0.0
		 *
		 * @param array $routes ajax action => [ callback, public ].
		 */
		return (array) apply_filters( 'wffn_bundles_ajax_routes', array(
			'wffn_bundles_add_bundle'  => array( array( Cart::class, 'ajax_add' ), true ),
			'wffn_bundles_add_single'  => array( array( Cart::class, 'ajax_add_single' ), true ),
			'wffn_bundles_quote'       => array( array( Cart::class, 'ajax_quote' ), true ),
			'wffn_bundles_aero_pack'   => array( array( Funnel_Builder_Adapter::class, 'ajax_repoint_pack' ), true ),
			'wffn_bundles_aero_set'    => array( array( Funnel_Builder_Adapter::class, 'ajax_rebuild_set' ), true ),
			// A quick view closed without confirming: what it quoted is dropped, so
			// the row keeps the variations it was already built from.
			'wffn_bundles_aero_cancel' => array( array( Funnel_Builder_Adapter::class, 'ajax_discard_picks' ), true ),
		) );
	}

	/**
	 * Hook every registered action: logged-in always, logged-out when public.
	 *
	 * @return void
	 */
	public function register() {
		foreach ( $this->routes() as $action => $route ) {
			list( $callback, $is_public ) = $route;
			add_action( 'wp_ajax_' . $action, $callback );
			if ( $is_public ) {
				add_action( 'wp_ajax_nopriv_' . $action, $callback );
			}
			// Also on `wc-ajax`, which skips the admin boot `admin-ajax` pays for.
			// Public routes only: WooCommerce has no `nopriv` half to withhold there.
			if ( $is_public ) {
				add_action( 'wc_ajax_' . $action, $callback );
			}
		}
	}

	/**
	 * Verify the shared nonce; emits -1 and dies on failure.
	 *
	 * @return void
	 */
	public static function verify() {
		if ( ! Request_Context::active() ) {
			check_ajax_referer( self::NONCE, 'nonce' );
			return;
		}
		$nonce = Request_Context::post( 'nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), self::NONCE ) ) {
			wp_die( -1, 403 );
		}
	}

	/**
	 * A POSTed parameter, sanitized as plain text.
	 *
	 * @param string $key     Parameter name.
	 * @param mixed  $default Value returned when the parameter is absent.
	 * @return mixed
	 */
	public static function param( $key, $default = '' ) {
		$value = Request_Context::post( $key );
		return null === $value ? $default : sanitize_text_field( $value );
	}

	/**
	 * A POSTed parameter, unslashed but unsanitized, for JSON the caller decodes and checks.
	 *
	 * @param string $key Parameter name.
	 * @return string Empty when absent or not a string.
	 */
	public static function raw( $key ) {
		$value = Request_Context::post( $key );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * A POSTed JSON parameter, decoded and sanitized.
	 *
	 * @param string $key Parameter name.
	 * @return array|null Null when absent or not a JSON object or list.
	 */
	public static function json( $key ) {
		return self::decode_json( self::raw( $key ) );
	}

	/**
	 * Decode a JSON string from the request and sanitize every key and string in it.
	 *
	 * @param string $raw Unslashed JSON.
	 * @return array|null Null when it is not a JSON object or list.
	 */
	public static function decode_json( $raw ) {
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $decoded ) ? self::sanitize_json( $decoded ) : null;
	}

	/**
	 * Sanitize a decoded JSON value: strings lose tags and invalid UTF-8, numbers and booleans pass.
	 *
	 * @param mixed $value Decoded value.
	 * @return mixed
	 */
	private static function sanitize_json( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $k => $v ) {
				$clean[ is_int( $k ) ? $k : self::sanitize_json( $k ) ] = self::sanitize_json( $v );
			}
			return $clean;
		}
		if ( is_string( $value ) ) {
			// Not sanitize_text_field(): it deletes the %xx octets of non-Latin attribute slugs.
			return wp_strip_all_tags( wp_check_invalid_utf8( $value ) );
		}
		return is_int( $value ) || is_float( $value ) || is_bool( $value ) ? $value : null;
	}

	/**
	 * Fail with a machine code and message as JSON, then stop.
	 *
	 * @param string $message Machine code or message.
	 * @param int    $code    HTTP status.
	 * @return void
	 */
	public static function fail( $message = 'bad_request', $code = 400 ) {
		self::send_error( $message, $code );
	}

	/**
	 * Reject with a machine code and a ready-to-render sentence.
	 *
	 * The code still drives `.wffn-bundles-error[data-wffn-bundles-error]`; the text replaces the
	 * generic per-code copy from config()['errors'].
	 *
	 * @param string $code Machine code, as keyed in config()['errors'].
	 * @param string $text Translated sentence to render in place of that copy.
	 * @param int    $http HTTP status.
	 * @return void
	 */
	public static function fail_detail( $code, $text, $http = 400 ) {
		self::send_error( array( 'code' => (string) $code, 'text' => (string) $text ), $http );
	}

	/**
	 * Send a refusal, after a caller answering in another plugin's shape has had its say.
	 *
	 * @param string|array $data   Machine code, or { code, text }.
	 * @param int          $status HTTP status.
	 * @return void
	 */
	private static function send_error( $data, $status ) {
		/**
		 * Filters a refused ajax request before it is sent.
		 *
		 * An integration that runs these handlers for another plugin's request
		 * reshapes the refusal for that plugin's script here.
		 *
		 * @since 1.0.0
		 *
		 * @param array $response { data: string|array, status: int }.
		 */
		$response = apply_filters(
			'wffn_bundles_ajax_error',
			array(
				'data'   => $data,
				'status' => $status,
			)
		);
		if ( ! is_array( $response ) || ! array_key_exists( 'data', $response ) ) {
			$response = array(
				'data'   => $data,
				'status' => $status,
			);
		}
		wp_send_json_error( $response['data'], (int) ( $response['status'] ?? $status ) );
	}

	/**
	 * Emit trusted, plugin-stamped HTML and stop.
	 *
	 * @param string $markup Markup to print.
	 * @return void
	 */
	public static function html( $markup ) {
		Markup::output( $markup );
		wp_die();
	}

	/**
	 * The config the front end needs to call these endpoints.
	 *
	 * @return array
	 */
	public static function config() {
		$config = array(
			'url'     => admin_url( 'admin-ajax.php' ),
			// The quote only reads, so it skips the admin boot; wc-ajax carries the action in the URL.
			'quoteUrl' => class_exists( '\WC_AJAX' ) ? \WC_AJAX::get_endpoint( 'wffn_bundles_quote' ) : admin_url( 'admin-ajax.php' ),
			// The adds only touch the cart, so they skip the admin boot as well.
			'addUrl'       => class_exists( '\WC_AJAX' ) ? \WC_AJAX::get_endpoint( 'wffn_bundles_add_bundle' ) : admin_url( 'admin-ajax.php' ),
			'addSingleUrl' => class_exists( '\WC_AJAX' ) ? \WC_AJAX::get_endpoint( 'wffn_bundles_add_single' ) : admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE ),
			'cartUrl' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'currency' => \WFFN\Bundles\Includes\Compat\Currency\Layer::get_current_currency(),
			'optionsDisplay' => Options_Display::mode(),
			'offersLimit'    => Product_Display::OFFERS_LIMIT,
			// The theme's product-price element, for the live re-quote.
			'priceSelectors' => \WFFN\Bundles\Includes\Compat\Render_Api::price_selectors(),
			'i18n'   => array(
				'selectOptions'      => WooCommerce::translated_or_woo( __( 'Select options', 'funnel-builder' ), 'Select options', 'Select options' ),
				'selectOptionsFirst' => __( 'Select options first', 'funnel-builder' ),
				/* translators: %d: total number of offers in the bundle. */
				'showAllOffers'      => __( 'Show all %d options', 'funnel-builder' ),
				'showFewerOffers'    => __( 'Show fewer options', 'funnel-builder' ),
				'done'               => __( 'Done', 'funnel-builder' ),
				'close'              => WooCommerce::translated_or_woo( __( 'Close', 'funnel-builder' ), 'Close', 'Close' ),
				// The native WooCommerce notice is suppressed on the AJAX add path.
				'addedToCart'        => __( 'Bundle added to your cart.', 'funnel-builder' ),
				'viewCart'           => WooCommerce::translated_or_woo( __( 'View cart', 'funnel-builder' ), 'View cart', 'View cart' ),
				// A variable product's own options live in the theme's form, not the set.
				'fbtChooseHost'      => __( 'Choose this product’s options first.', 'funnel-builder' ),
				/* translators: %s: name of the product whose options are missing. */
				'fbtChooseItem'      => __( 'Choose options for %s first.', 'funnel-builder' ),
				'fbtChooseAny'       => __( 'Choose at least one item.', 'funnel-builder' ),
				'fbtAdded'         => WooCommerce::translated_or_woo( __( 'Added to your cart.', 'funnel-builder' ), 'Added to your cart.', 'Added to cart' ),
			),
			// Human copy per rejection code; the server sends only the machine code.
			// Keys map 1:1 to the codes emitted in includes/frontend/cart.php.
			'errors' => array(
				'not_available'     => __( 'This bundle is no longer available. Please refresh the page and try again.', 'funnel-builder' ),
				'bad_bundle'      => __( 'This bundle is no longer available. Please refresh the page and try again.', 'funnel-builder' ),
				'bad_offer'         => __( 'That option is no longer available. Please choose another and try again.', 'funnel-builder' ),
				'invalid_selection' => __( 'Some of the items in your selection can’t be added. Please review your selection and try again.', 'funnel-builder' ),
				'out_of_stock'      => __( 'One or more items in this bundle are out of stock, so the bundle can’t be added right now.', 'funnel-builder' ),
				// Fallback only: the stock gate sends its own sentence naming the product.
				'insufficient_stock' => __( 'There isn’t enough stock to add this bundle. Please reduce the quantity and try again.', 'funnel-builder' ),
				'rules_not_met'     => __( 'Your selection doesn’t meet this bundle’s requirements. Please adjust the quantity and try again.', 'funnel-builder' ),
				'empty_selection'   => __( 'Please add items to your bundle before adding to cart.', 'funnel-builder' ),
				'payload_too_large' => __( 'Your selection is too large. Please reduce the number of items and try again.', 'funnel-builder' ),
				'no_cart'           => __( 'We couldn’t reach your cart. Please refresh the page and try again.', 'funnel-builder' ),
				// Not from Controller::fail: the behavior turns WordPress's bare -1 into this.
				'session_expired'   => __( 'This page has been open a while, so your session expired. Please refresh the page and try again.', 'funnel-builder' ),
				'add_failed'        => __( 'We couldn’t add this bundle to your cart. Please try again.', 'funnel-builder' ),
				'_default'          => __( 'Sorry, this bundle couldn’t be added to your cart. Please try again.', 'funnel-builder' ),
			),
		);

		/**
		 * Filters the config the storefront scripts call these endpoints with.
		 *
		 * An add-on adds the copy its own scripts show under `i18n`.
		 *
		 * @since 1.0.0
		 *
		 * @param array $config Endpoint, nonce, display options, `i18n` and `errors`.
		 */
		return (array) apply_filters( 'wffn_bundles_ajax_config', $config );
	}
}
