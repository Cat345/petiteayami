<?php
/**
 * Store API + Blocks integration — render a bundle as a grouped product in the
 * block Cart / Checkout / mini-cart.
 *
 * Block cart rows are React-rendered from the Store API, so PHP and CSS filters
 * cannot reach them: the grouping data rides an endpoint-data extension and a
 * small script registers the checkout filters that indent and label the rows.
 */
namespace WFFN\Bundles\Includes\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Storage\Bundle_Store;

/**
 * Exposes a bundle's cart-item grouping to the Store API and the cart blocks.
 */
class Store_API {

	const NAMESPACE = 'wffn-bundles';

	/** Cart roles whose quantity the offer fixes, besides add-on roles (see Cart::lock_quantities). */
	const FIXED_QTY_ROLES = array( 'child' );

	private static $instance = null;

	/**
	 * The shared instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register the Store API extension and the block quantity limits.
	 */
	private function __construct() {
		// WooCommerce fires woocommerce_blocks_loaded at plugins_loaded:10, before
		// our own plugins_loaded:20 init — so if it already fired, register now.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register' ) );
		}
		// Enforced, not cosmetic: QuantityLimits::validate_cart_item_quantity()
		// rejects an update to a non-editable line above its maximum.
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'quantity_editable' ), 10, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'quantity_maximum' ), 10, 3 );
	}

	/**
	 * Whether this cart item's quantity is the shopper's to change.
	 *
	 * @param bool        $editable  Editability so far.
	 * @param \WC_Product $product   Product on the line.
	 * @param array|null  $cart_item The cart item.
	 * @return bool
	 */
	public function quantity_editable( $editable, $product, $cart_item = null ) {
		return self::has_fixed_quantity( $cart_item ) ? false : $editable;
	}

	/**
	 * A fixed-quantity line can never exceed what it currently holds, and a
	 * Fixed Bundle container never more packs than its members' stock supplies.
	 *
	 * @param int         $maximum   Maximum so far.
	 * @param \WC_Product $product   Product on the line.
	 * @param array|null  $cart_item The cart item.
	 * @return int
	 */
	public function quantity_maximum( $maximum, $product, $cart_item = null ) {
		if ( self::has_fixed_quantity( $cart_item ) ) {
			return max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
		}
		$b = is_array( $cart_item ) && is_array( $cart_item['wffn_bundles_bundle'] ?? null ) ? $cart_item['wffn_bundles_bundle'] : array();
		if ( in_array( (string) ( $b['role'] ?? '' ), array( 'container', 'fbt_host' ), true ) && ! empty( $cart_item['key'] ) && function_exists( 'WC' ) && WC()->cart ) {
			$limit = Cart::group_pack_limit( WC()->cart, (string) $cart_item['key'] );
			if ( null !== $limit ) {
				return min( (int) $maximum, $limit );
			}
		}
		return $maximum;
	}

	/**
	 * True when the line is one this plugin granted at a fixed quantity.
	 *
	 * @param array|null $cart_item The cart item.
	 * @return bool
	 */
	private static function has_fixed_quantity( $cart_item ) {
		$b    = is_array( $cart_item ) ? ( $cart_item['wffn_bundles_bundle'] ?? array() ) : array();
		$role = is_array( $b ) ? (string) ( $b['role'] ?? '' ) : '';
		// A single-product Fixed Bundle pack is not re-priced by quantity, unlike a
		// quantity-priced 'member' line; see Cart::granted_quantity().
		if ( 'member' === $role && is_array( $b ) && Bundle_Store::TYPE_FIXED_BUNDLES === (int) ( $b['type'] ?? 0 ) ) {
			return true;
		}
		if ( 'heading' === $role || Cart::nested_quantity_locked( $cart_item ) ) {
			return true;
		}
		if ( 'fbt_companion' === $role ) {
			return Cart::fbt_companion_locked( $cart_item );
		}
		return in_array( $role, array_merge( self::FIXED_QTY_ROLES, Cart::addon_roles() ), true );
	}

	/**
	 * Register the cart-item endpoint data and the Blocks script integration.
	 *
	 * @return void
	 */
	public function register() {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
					'namespace'       => self::NAMESPACE,
					'data_callback'   => array( $this, 'cart_item_data' ),
					'schema_callback' => array( $this, 'cart_item_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}

		// Block names (so the registration hook matches): cart, checkout, mini-cart.
		$integration = new Cart_Blocks_Integration();
		foreach ( array( 'cart', 'checkout', 'mini-cart' ) as $block ) {
			add_action(
				"woocommerce_blocks_{$block}_block_registration",
				function ( $registry ) use ( $integration ) {
					$registry->register( $integration );
				}
			);
		}
	}

	/**
	 * The grouping fields exposed to the block cart for one cart item.
	 *
	 * @param array $cart_item The cart item.
	 * @return array
	 */
	public function cart_item_data( $cart_item ) {
		$b = is_array( $cart_item['wffn_bundles_bundle'] ?? null ) ? $cart_item['wffn_bundles_bundle'] : array();
		return array(
			'group'          => (string) ( $b['group'] ?? '' ),
			'role'           => (string) ( $b['role'] ?? '' ),
			'bundleTitle'    => (string) ( $b['bundleTitle'] ?? '' ),
			'nested'         => Cart::is_nested( $b ),
			'fixedQty'       => self::has_fixed_quantity( $cart_item ),
			'leads'          => Cart::fbt_leads( $cart_item ),
			'indent'         => Cart::fbt_companion_locked( $cart_item ),
			'headingTotal'   => 'heading' === (string) ( $b['role'] ?? '' ) ? html_entity_decode( wp_strip_all_tags( wc_price( Cart::heading_total( $cart_item ) ) ), ENT_QUOTES, 'UTF-8' ) : '',
			'saveTotal'      => self::save_total( $cart_item, $b ),
		);
	}

	/**
	 * The line's saving as regular line total minus charged line total, formatted.
	 *
	 * The block cart's own badge multiplies a whole-cent unit saving by the
	 * quantity, which drifts from the real saving once the unit price has
	 * fractional cents.
	 *
	 * @param array $cart_item The cart item.
	 * @param array $b         Its bundle data.
	 * @return string Empty when the line is no bundle line or saves nothing.
	 */
	private static function save_total( $cart_item, $b ) {
		$product = $cart_item['data'] ?? null;
		if ( ! $b || 'child' === (string) ( $b['role'] ?? '' ) || ! $product instanceof \WC_Product || '' === (string) $product->get_regular_price() ) {
			return '';
		}

		$qty      = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );
		$with_tax = WC()->cart && WC()->cart->display_prices_including_tax();
		$args     = array(
			'qty'   => $qty,
			'price' => $product->get_regular_price(),
		);
		$regular  = $with_tax ? wc_get_price_including_tax( $product, $args ) : wc_get_price_excluding_tax( $product, $args );
		$charged  = (float) ( $cart_item['line_subtotal'] ?? 0 ) + ( $with_tax ? (float) ( $cart_item['line_subtotal_tax'] ?? 0 ) : 0 );
		$saving   = round( (float) $regular - $charged, wc_get_price_decimals() );

		return $saving > 0 ? html_entity_decode( wp_strip_all_tags( wc_price( $saving ) ), ENT_QUOTES, 'UTF-8' ) : '';
	}

	/**
	 * Schema for the fields above (required by the Store API).
	 *
	 * @return array
	 */
	public function cart_item_schema() {
		$str = array( 'type' => array( 'string', 'null' ), 'readonly' => true );
		return array(
			'group'          => $str,
			'role'           => $str,
			'bundleTitle'    => $str,
			'nested'         => array( 'type' => 'boolean', 'readonly' => true ),
			'leads'          => array( 'type' => 'boolean', 'readonly' => true ),
			'indent'         => array( 'type' => 'boolean', 'readonly' => true ),
			'headingTotal'   => $str,
			'saveTotal'      => $str,
		);
	}
}

/**
 * Blocks integration: registers the grouping script (and CSS) so it loads only
 * when a Cart / Checkout / mini-cart block is on the page.
 */
class Cart_Blocks_Integration implements IntegrationInterface {

	/**
	 * The integration handle.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wffn-bundles';
	}

	/**
	 * Register the grouping script and stylesheet.
	 *
	 * @return void
	 */
	public function initialize() {
		$dir = WFFN_BUNDLES_DIR;
		$url = WFFN_BUNDLES_URL;
		$js  = 'assets/skins/cart-blocks.js';
		$css = 'assets/skins/cart-blocks.css';
		wp_register_script(
			'wffn-bundles-cart-blocks',
			$url . $js,
			array( 'wc-blocks-checkout' ),
			file_exists( $dir . '/' . $js ) ? (string) filemtime( $dir . '/' . $js ) : '1',
			true
		);
		wp_register_style(
			'wffn-bundles-cart-blocks',
			$url . $css,
			array(),
			file_exists( $dir . '/' . $css ) ? (string) filemtime( $dir . '/' . $css ) : '1'
		);

		// Registered, not enqueued: initialize() runs on every request, while
		// `render_block_{$name}` fires only where that block really renders.
		foreach ( array( 'woocommerce/cart', 'woocommerce/checkout' ) as $block ) {
			add_filter( "render_block_{$block}", array( $this, 'enqueue_style_on_render' ) );
		}
		add_filter( 'render_block_woocommerce/mini-cart', array( $this, 'mini_cart_assets' ) );
	}

	/**
	 * Load the grouping assets with a header mini-cart only when a bundle is active or in the cart.
	 *
	 * The mini-cart renders on every page of a block theme. A pass-through filter.
	 *
	 * @param string $html Block markup.
	 * @return string
	 */
	public function mini_cart_assets( $html ) {
		if ( Cart::has_bundle_line() ) {
			return $this->enqueue_style_on_render( $html );
		}
		// The cart and checkout blocks on this page still need the script.
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			return $html;
		}
		// The page's widgets have not rendered yet; decide once they have.
		if ( Bundle_Store::has_active() ) {
			add_action( 'wp_footer', array( $this, 'mini_cart_assets_for_widget' ), 1 );
			return $html;
		}
		wp_dequeue_script( 'wffn-bundles-cart-blocks' );
		return $html;
	}

	/**
	 * Keep the mini-cart grouping only on a page whose widget can add a bundle without a reload.
	 *
	 * The script pulls in WooCommerce's block cart store, which posts a customer update on every page it loads on.
	 *
	 * @return void
	 */
	public function mini_cart_assets_for_widget() {
		if ( wp_script_is( Assets::HANDLE, 'enqueued' ) ) {
			wp_enqueue_style( 'wffn-bundles-cart-blocks' );
			return;
		}
		wp_dequeue_script( 'wffn-bundles-cart-blocks' );
	}

	/**
	 * Enqueue the grouping stylesheet because a cart block is rendering. A
	 * pass-through filter: the markup is returned untouched.
	 *
	 * Classic themes have no mini-cart block; see Cart::enqueue_cart_css().
	 *
	 * @param string $html Block markup.
	 * @return string
	 */
	public function enqueue_style_on_render( $html ) {
		wp_enqueue_style( 'wffn-bundles-cart-blocks' );
		return $html;
	}

	/**
	 * Script handles the blocks framework enqueues on render.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'wffn-bundles-cart-blocks' );
	}

	/**
	 * Script handles for the block editor.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Data passed to the registered scripts.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array();
	}
}
