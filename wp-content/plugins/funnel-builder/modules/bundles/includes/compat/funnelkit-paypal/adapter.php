<?php
/** Adapter — FunnelKit Payment Gateway for PayPal compatibility. */
namespace WFFN\Bundles\Includes\Compat\Funnelkit_Paypal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Ajax\Controller;
use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Compat\Currency\Layer;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Helpers\Request_Context;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Order_Data;

/**
 * Buys a bundle's full selection through the gateway's product-page button.
 *
 * That button never clicks the widget's Add to cart: it serializes `form.cart`
 * into its own `fkwcppcp_add_to_cart`, which empties the cart and adds the page
 * product alone. A bundle's lines and every accepted add-on were dropped, and a
 * variable product was refused outright.
 *
 * behavior/core.js writes the add the widget would post into that same form as
 * `wffn_bundles_payload`. This answers the gateway's add first, through the widget's own
 * add handler, so the cart the gateway then charges is the one the widget builds.
 */
class Adapter implements Integration {

	/** The gateway's add-to-cart endpoint, a WC-AJAX action. */
	const ADD_ACTION = 'fkwcppcp_add_to_cart';

	/** The gateway's constant naming the action it fires inside that endpoint, after its nonce check. */
	const EXPRESS_ADD_ACTION = 'FunnelKit\\PayPal\\Includes\\AJAX::EXPRESS_ADD_TO_CART_ACTION';

	/** The gateway's own nonce action for that endpoint. */
	const GATEWAY_NONCE = 'fkwcppcp-paypal';

	/** Handle of the product-page script. */
	const SCRIPT = 'wffn-bundles-compat-funnelkit-paypal';

	/** Its path inside the plugin. */
	const SCRIPT_PATH = 'assets/compat/funnelkit-paypal.js';

	/** Its stylesheet's path; the handle is the script's. */
	const STYLE_PATH = 'assets/compat/funnelkit-paypal.css';

	/**
	 * Is the FunnelKit PayPal gateway active?
	 *
	 * @return bool
	 */
	public function is_enable() {
		return class_exists( '\FunnelKit\PayPal\Main' );
	}

	/**
	 * Stage the bundle on the gateway's express add action, or ahead of its own
	 * handler on a gateway that predates that action.
	 *
	 * @return void
	 */
	public function init() {
		if ( defined( self::EXPRESS_ADD_ACTION ) ) {
			add_action( constant( self::EXPRESS_ADD_ACTION ), array( $this, 'add_bundle' ) );
		} else {
			add_action( 'wc_ajax_' . self::ADD_ACTION, array( $this, 'add_bundle' ), 5 );
		}
		// After the gateway's own enqueue at the default priority.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'wffn_bundles_quote_response', array( $this, 'quote_amount' ), 10, 2 );
		// Fired by the gateway once bin/docs/integrations/funnelkit-paypal/PATCHES.md is applied.
		add_filter( 'fkwcppcp_paypal_cart_line_items', array( $this, 'exact_cart_line' ), 10, 3 );
		add_filter( 'fkwcppcp_paypal_order_line_items', array( $this, 'exact_order_line' ), 10, 2 );
	}

	/**
	 * Load what holds the gateway's product buttons and Pay Later message to the
	 * bundle widget or bought-together set. See assets/compat/funnelkit-paypal.js
	 * and .css.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product_id = get_queried_object_id();
		if ( empty( Product_Display::flags_for_product( $product_id ) ) && null === Bundle_Store::active_for_product( $product_id, Bundle_Store::SLOT_FBT ) ) {
			return;
		}
		wp_enqueue_script( self::SCRIPT, WFFN_BUNDLES_URL . self::SCRIPT_PATH, array( 'wffn-bundles-skins-core' ), Assets::ver( self::SCRIPT_PATH ), true );
		wp_enqueue_style( self::SCRIPT, WFFN_BUNDLES_URL . self::STYLE_PATH, array(), Assets::ver( self::STYLE_PATH ) );
	}

	/**
	 * Send a bundle cart line to PayPal as items that add up to it exactly.
	 *
	 * A line priced off its total (3 for 64.97) has a unit price PayPal cannot
	 * carry. The gateway rounds it to 21.66 x 3 = 64.98 and settles the cent in
	 * the discount or the shipping line, so the review page shows a total the
	 * shopper was never quoted. Consumes the gateway's fkwcppcp_paypal_cart_line_items.
	 *
	 * @param array[] $items         The line's item, as a one-item list.
	 * @param array   $cart_item     The cart line.
	 * @param float   $line_subtotal The amount the gateway divided the unit price from.
	 * @return array[]
	 */
	public function exact_cart_line( $items, $cart_item, $line_subtotal ) {
		if ( ! is_array( $cart_item ) || empty( $cart_item['wffn_bundles_bundle'] ) ) {
			return $items;
		}
		return self::split_exact( $items, (float) $line_subtotal );
	}

	/**
	 * The same, for a bundle line on an order the gateway charges (pay for order,
	 * a card retry), where it divides the line's subtotal.
	 *
	 * @param array[]                $items The line's item, as a one-item list.
	 * @param \WC_Order_Item_Product $item  The order line.
	 * @return array[]
	 */
	public function exact_order_line( $items, $item ) {
		if ( ! $item instanceof \WC_Order_Item_Product || '' === (string) Order_Data::get( $item, '_wffn_bundles_role' ) ) {
			return $items;
		}
		return self::split_exact( $items, (float) $item->get_subtotal() );
	}

	/**
	 * Split one item into a lower and a higher unit price, one smallest currency
	 * unit apart, whose quantities add up to the original and whose amounts add up
	 * to `$amount` exactly. Left alone when it already divides evenly.
	 *
	 * @param array[] $items  The line's item, as a one-item list.
	 * @param float   $amount The line amount the items must add up to.
	 * @return array[]
	 */
	private static function split_exact( $items, $amount ) {
		if ( ! is_array( $items ) || 1 !== count( $items ) ) {
			return $items;
		}
		$item  = reset( $items );
		$qty   = (int) ( $item['quantity'] ?? 0 );
		$value = (string) ( $item['unit_amount']['value'] ?? '' );
		if ( $qty < 2 || '' === $value ) {
			return $items;
		}
		// The gateway's own formatting says how many decimals this currency carries.
		$dot      = strpos( $value, '.' );
		$decimals = false === $dot ? 0 : strlen( $value ) - $dot - 1;
		$scale    = 10 ** $decimals;
		$total    = (int) round( $amount * $scale );
		$base     = intdiv( $total, $qty );
		$extra    = $total - $base * $qty;
		if ( 0 === $extra || $base < 0 ) {
			return $items;
		}

		$low                          = $item;
		$low['quantity']              = (string) ( $qty - $extra );
		$low['unit_amount']['value']  = number_format( $base / $scale, $decimals, '.', '' );
		$high                         = $item;
		$high['quantity']             = (string) $extra;
		$high['unit_amount']['value'] = number_format( ( $base + 1 ) / $scale, $decimals, '.', '' );

		return array( $low, $high );
	}

	/**
	 * Answer a refused add in the gateway's own shape.
	 *
	 * The widget's handler refuses with a non-2xx status and `{ code, text }`; the
	 * gateway's script only reads `data.message` from a 200, so the shopper saw a
	 * generic payment error instead of why (an option out of stock, say).
	 *
	 * @param array $response { data: string|array, status: int }.
	 * @return array
	 */
	public function gateway_error( $response ) {
		$data   = is_array( $response ) ? ( $response['data'] ?? '' ) : '';
		$code   = is_array( $data ) ? (string) ( $data['code'] ?? '' ) : (string) $data;
		$text   = is_array( $data ) ? (string) ( $data['text'] ?? '' ) : '';
		$errors = Controller::config()['errors'] ?? array();
		if ( '' === $text ) {
			$text = (string) ( $errors[ $code ] ?? $errors['_default'] ?? '' );
		}

		return array(
			'data'   => array(
				'code'    => $code,
				'message' => $text,
			),
			'status' => 200,
		);
	}

	/**
	 * Ship the quoted bundle total as a plain number, for the Pay Later message.
	 *
	 * The quote otherwise carries it only as markup.
	 *
	 * @param array $response The quote payload.
	 * @param array $context  { bundle, type, offer, selection, totals }.
	 * @return array
	 */
	public function quote_amount( $response, $context ) {
		if ( ! is_array( $response ) || empty( $response['hasItems'] ) || ! isset( $context['totals']['finalMin'] ) ) {
			return $response;
		}
		$response['expressAmount'] = number_format( (float) Layer::convert( (float) $context['totals']['finalMin'] ), 2, '.', '' );

		return $response;
	}

	/**
	 * Add the posted bundle and answer in the gateway's own response shape.
	 *
	 * Returns without output when no bundle was posted, leaving the gateway to
	 * add the product as it always has.
	 *
	 * @return void
	 */
	public function add_bundle() {
		// Unverified: leave it to the gateway, which answers a bad nonce itself.
		if ( ! check_ajax_referer( self::GATEWAY_NONCE, 'security', false ) ) {
			return;
		}
		$raw = Controller::raw( 'wffn_bundles_payload' );
		if ( '' === $raw || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$payload = Controller::decode_json( $raw );
		if ( ! is_array( $payload ) ) {
			return;
		}
		// Express checkout buys this selection alone, as the gateway's own add does.
		// First, and on refusal too: the posted `add-to-cart` has already had
		// WooCommerce's form handler add the bare page product on wp_loaded. The
		// saved cart goes as well, or the next request merges that line back.
		WC()->cart->empty_cart();
		// Its notices speak for that add, which is gone.
		wc_clear_notices();

		if ( false === ( $payload['ready'] ?? true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please finish choosing your bundle options before paying.', 'funnel-builder' ) ) );
		}

		// The widget's add handler answers `{ success }`, the shape the gateway's
		// script checks; it exits either way.
		$fields = array(
			'action'    => empty( $payload['single'] ) ? 'wffn_bundles_add_bundle' : 'wffn_bundles_add_single',
			'nonce'     => (string) ( $payload['nonce'] ?? '' ),
			'currency'  => (string) ( $payload['currency'] ?? '' ),
			'bundle'  => (string) ( $payload['bundle'] ?? '' ),
			'selection' => wp_json_encode( is_array( $payload['selection'] ?? null ) ? $payload['selection'] : array() ),
		);
		if ( ! empty( $payload['single'] ) ) {
			$fields['product']    = (string) absint( $payload['product'] ?? 0 );
			$fields['quantity']   = (string) max( 1, absint( $payload['quantity'] ?? 1 ) );
			$fields['attributes'] = wp_json_encode( is_array( $payload['attributes'] ?? null ) ? $payload['attributes'] : array() );
		} else {
			$fields['type']  = (string) ( $payload['type'] ?? '' );
			$fields['offer'] = (string) ( $payload['offer'] ?? '' );
		}
		add_filter( 'wffn_bundles_ajax_error', array( $this, 'gateway_error' ) );

		// Only these fields reach the handler: the gateway's own form fields stay out.
		Request_Context::run( $fields, empty( $payload['single'] ) ? array( Cart::class, 'ajax_add' ) : array( Cart::class, 'ajax_add_single' ) );
	}
}
