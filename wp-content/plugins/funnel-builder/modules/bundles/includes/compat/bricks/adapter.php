<?php
/**
 * Adapter — Bricks theme compatibility.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Helpers\Request_Context;

/**
 * Bricks compatibility adapter.
 *
 * No file here names a Bricks symbol at file scope — every reference sits inside
 * a method body behind defined/function_exists/class_exists. Bricks is a theme,
 * so switching theme deactivates it mid-content; that must not fatal us.
 *
 * The plugin ships no Bricks element: Bricks' Add To Cart element fires
 * woocommerce_before_add_to_cart_form, so the bundle already renders. What needs
 * adapting is the four ways Bricks then breaks it — its AJAX add-to-cart claiming
 * our button, the WooCommerce stylesheets it dequeues, the live cart its builder
 * canvas force-populates, and its REST render routes, which print no queue.
 */
class Adapter implements Integration {

	/** Compatibility script handle. */
	const SCRIPT = 'wffn-bundles-compat-bricks';

	/** Restores what Bricks' global form-control reset takes from our offer rows. */
	const RESET_ANTIDOTE = 'assets/skins/compatibility/builders/bricks.css';

	/**
	 * Oldest supported Bricks; below 2.0 the antidote sheet's assumptions about
	 * where Bricks' resets sit in the cascade do not hold.
	 */
	const MIN_VERSION = '2.0';

	/** Bricks' storefront REST render routes — popup, filters, infinite scroll. */
	const AJAX_ROUTES = array( 'load_popup_content', 'query_result', 'load_query_page' );

	/**
	 * Is Bricks the active theme?
	 *
	 * Keyed on the template slug, not `BRICKS_VERSION`: the theme sets that on
	 * after_setup_theme, long after Manager::boot() runs on plugins_loaded. The
	 * constant and its version floor are re-checked in boot_hooks(). Bricks in a
	 * renamed theme directory is the documented limit of this gate.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return 'bricks' === get_template() || defined( 'BRICKS_VERSION' );
	}

	/**
	 * Wait for the theme, then register — see is_enable().
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'after_setup_theme', array( $this, 'boot_hooks' ), 20 );
	}

	/**
	 * Every hook this adapter owns, once Bricks is known to be loaded.
	 *
	 * @return void
	 */
	public function boot_hooks() {
		if ( ! defined( 'BRICKS_VERSION' ) || version_compare( BRICKS_VERSION, self::MIN_VERSION, '<' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_popup_host' ), 20 );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_script' ), 5 );

		add_filter( 'wffn_bundles_skin_styles', array( $this, 'add_reset_antidote' ) );
		add_filter( 'wffn_bundles_inline_skin_css', array( $this, 'inline_skin_css' ) );
		add_filter( 'wffn_bundles_inline_design_vars', array( $this, 'inline_design_vars' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'relax_canvas_validation' ), 9 );
		// 11: after Cart::loop_add_to_cart_link@5 and Bricks'
		// add_quantity_input_field@10. See the method.
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'unclaim_loop_link' ), 11 );

		Preview_Assets::init();
	}

	/**
	 * Is Bricks' own builder driving this request?
	 *
	 * Spelled out rather than delegated to `bricks_is_builder_call()`, which also
	 * returns true for any request whose HTTP_REFERER contains `bricks=run` — a
	 * genuine shopper request would then lose the bare-add guard. The legs kept are
	 * the ones a referer cannot forge.
	 *
	 * @return bool
	 */
	public static function in_builder() {
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return true;
		}
		// Both markers below are guest-settable (a plain request header, or a
		// posted field); require the same capability Bricks' own builder does,
		// or a guest can force the builder-only inline-CSS branch for themselves.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		if ( ! empty( $_SERVER['HTTP_X_BRICKS_IS_BUILDER'] ) ) {
			return true;
		}
		return wp_doing_ajax() && null !== Request_Context::raw_request( 'bricks-is-builder' );
	}

	/**
	 * Is this one of Bricks' storefront REST render routes?
	 *
	 * Separate from in_builder(): a quick-view popup and an AJAX-filtered query loop
	 * carry neither `?bricks=` nor the X-Bricks-Is-Builder header, yet they are
	 * exactly the routes that print no style queue.
	 *
	 * @return bool
	 */
	public static function in_ajax_render() {
		if ( ! class_exists( '\Bricks\Api' ) || ! method_exists( '\Bricks\Api', 'is_current_endpoint' ) ) {
			return false;
		}
		foreach ( self::AJAX_ROUTES as $route ) {
			if ( \Bricks\Api::is_current_endpoint( $route ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does this page have a Bricks popup template attached to it?
	 *
	 * @return bool
	 */
	private static function has_popup_template() {
		if ( ! class_exists( '\Bricks\Database' ) || ! isset( \Bricks\Database::$active_templates['popup'] ) ) {
			return false;
		}
		return ! empty( \Bricks\Database::$active_templates['popup'] );
	}

	/**
	 * Register our handles early, so anything may declare them as a dependency.
	 *
	 * @return void
	 */
	public function register_assets() {
		Assets::register_handles();
		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			// The `bricks-scripts` dependency is load-bearing: Bricks localizes
			// `bricksWooCommerce` onto it and bricks.js overwrites one of its keys.
			wp_register_script(
				self::SCRIPT,
				WFFN_BUNDLES_URL . 'assets/compat/bricks.js',
				array( 'bricks-scripts', Assets::HANDLE ),
				Assets::ver( 'assets/compat/bricks.js' ),
				true
			);
		}
	}

	/**
	 * Put the full skin set on an archive whose popup could open a bundle.
	 *
	 * Product_Display::enqueue_early() is gated on is_product(), so a shop page
	 * enqueues nothing of ours and a popup's `<script>`, inserted via innerHTML,
	 * never runs. Which popup a click opens is not knowable here, so the presence
	 * of any popup template is the gate and the over-enqueue is accepted.
	 *
	 * @return void
	 */
	public function enqueue_popup_host() {
		if ( self::in_builder() || ! function_exists( 'is_product' ) || is_product() ) {
			return;
		}
		if ( ! self::has_popup_template() ) {
			return;
		}
		Assets::enqueue_skin_styles();
		// null loads every module: the popup's bundle type is not known here.
		Assets::enqueue_behavior( true, null );
		// wffnBundlesCart cannot ride the popup's REST response, for the same innerHTML reason.
		Product_Display::ensure_cart_config();
	}

	/**
	 * Ship bricks.js wherever the behavior layer shipped.
	 *
	 * Late, because a bundle can claim the queue from enqueue_early() or from
	 * render() inside the loop, and only the footer sees both. Still ahead of
	 * wp_print_footer_scripts, so our file parses before DOMContentLoaded.
	 *
	 * @return void
	 */
	public function maybe_enqueue_script() {
		if ( ! wp_script_is( Assets::HANDLE, 'enqueued' ) && ! wp_script_is( Assets::HANDLE, 'done' ) ) {
			return;
		}
		wp_enqueue_script( self::SCRIPT );
	}

	/**
	 * Append the sheet that restores what Bricks' reset takes.
	 *
	 * Rides the skin-style list rather than its own handle, to inherit that path's
	 * cache-busting, its ordering (last) and its builder inlining.
	 *
	 * @param string[] $css Plugin-root-relative stylesheet paths.
	 * @return string[]
	 */
	public function add_reset_antidote( $css ) {
		$css   = (array) $css;
		$css[] = self::RESET_ANTIDOTE;
		return $css;
	}

	/**
	 * Bricks' REST render routes run outside the enqueue lifecycle, so every
	 * style-queue write is discarded and the markup would arrive with no CSS.
	 *
	 * @param bool $inline Whether the caller already decided to inline.
	 * @return bool
	 */
	public function inline_skin_css( $inline ) {
		return $inline || self::in_builder() || self::in_ajax_render();
	}

	/**
	 * Same routes, same reason: the design tokens are a late style-queue write, so
	 * the root keeps its wffn-bundles-design-* class while nothing defines those properties.
	 *
	 * @param bool $inline Whether the caller already decided to inline.
	 * @return bool
	 */
	public function inline_design_vars( $inline ) {
		return $inline || self::in_builder() || self::in_ajax_render();
	}

	/**
	 * Give a loop permalink back to the shopper, twice over.
	 *
	 * Cart::loop_add_to_cart_link@5 replaces the button with an anchor to the
	 * product page, and Bricks takes it back two ways: the anchor carries
	 * `brx_ajax_add_to_cart` through `$args['class']`, the second selector Bricks
	 * binds its AJAX handler to; and with "quantity in loop" on,
	 * `add_quantity_input_field`@10 wraps whatever it is given in a real form.
	 *
	 * Hence priority 11, after both. Keyed on the absence of `data-product_id`,
	 * which WooCommerce's own add_to_cart_link always emits and the permalink
	 * replacement never does, plus one of Bricks' own markers.
	 *
	 * @param string $html Loop button markup.
	 * @return string
	 */
	public function unclaim_loop_link( $html ) {
		$html    = (string) $html;
		$wrapped = false !== strpos( $html, 'brx-loop-product-form' );
		$claimed = false !== strpos( $html, 'brx_ajax_add_to_cart' );
		if ( false !== strpos( $html, 'data-product_id' ) || ( ! $wrapped && ! $claimed ) ) {
			return $html;
		}
		// The only anchor in the fragment: woocommerce_quantity_input emits no link.
		if ( $wrapped && preg_match( '#<a\b[^>]*>.*?</a>#s', $html, $anchor ) ) {
			$html = $anchor[0];
		}
		return str_replace( 'brx_ajax_add_to_cart', '', $html );
	}

	/**
	 * Let the builder canvas' synthetic cart fill.
	 *
	 * Opening the builder force-adds up to 5 purchasable products to the real
	 * session cart, and Cart::guard_bare_add would reject a bundle-targeted one.
	 * Detaching our own callback is the only safe widening: returning true would
	 * overwrite the false that wc_protected_product_add_to_cart sets at the same
	 * priority. in_builder() excludes Bricks' referer heuristic, and a request
	 * posting its own add-to-cart is skipped, so a shopper's add keeps the guard.
	 *
	 * @param bool $passed Whether the add has validated so far.
	 * @return bool
	 */
	public function relax_canvas_validation( $passed ) {
		if ( ! self::in_builder() || ! empty( Request_Context::raw_request( 'add-to-cart' ) ) ) {
			return $passed;
		}
		// Mirrors plugin.php:99, the only place Cart is instantiated; elsewhere
		// get_instance() would register all of its hooks mid-request to remove one.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $passed;
		}
		remove_filter( 'woocommerce_add_to_cart_validation', array( Cart::get_instance(), 'guard_bare_add' ), 10 );
		return $passed;
	}
}
