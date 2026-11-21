<?php
/**
 * Adapter — Divi Builder / Divi theme compatibility.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Divi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Helpers\Request_Context;

/**
 * Divi compatibility adapter.
 *
 * No file here names an `ET_*` symbol at file scope — every reference sits inside
 * a method body behind function_exists/defined, so a deactivated Divi cannot
 * fatal us. The plugin ships no Divi module; Divi's own Add To Cart module
 * renders the ordinary WooCommerce template and so fires our hooks.
 *
 * What needs adapting is that Divi renders the product layout more than once per
 * request, while Product_Display renders a bundle once, so whichever pass runs
 * first claims it. Only the pass inside Divi's own bracket reaches the page, so
 * that is the only pass we render in.
 */
class Adapter implements Integration {

	/** Fired by Divi only when its layout takeover is active for this request. */
	const HOOK_REGISTER = 'et_builder_wc_product_before_render_layout_registration';

	/** Restores the paint properties Divi's `#et-boc .et-l` reset strips. */
	const RESET_ANTIDOTE = 'assets/skins/compatibility/builders/divi.css';

	/** The bracket around the layout render whose output reaches the page. */
	const HOOK_BEFORE = 'et_builder_wc_product_before_render_layout';
	const HOOK_AFTER  = 'et_builder_wc_product_after_render_layout';

	/** Our render callbacks, as (hook, priority) pairs. */
	const RENDER_HOOKS = array(
		array( 'woocommerce_before_add_to_cart_form', 5 ),
		array( 'woocommerce_before_variations_form', 5 ),
	);

	/** The product the page is actually for, resolved once takeover is known. */
	private $queried_id = 0;

	/** Did Divi take the product layout over this request? */
	private $takeover = false;

	/** Is a module-owned pass currently being skipped? */
	private $skipping = false;

	/** Divi's own template renderer, the frame that marks a module-owned pass. */
	const MODULE_RENDERER = 'et_builder_wc_render_module_template';

	/**
	 * Is Divi loaded at all? Covers the plugin, the theme and Extra.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return defined( 'ET_BUILDER_PLUGIN_VERSION' )
			|| defined( 'ET_BUILDER_THEME' )
			|| function_exists( 'et_builder_wc_init' );
	}

	/**
	 * Hook the takeover detector, the module-pass guard and the inlining filters.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::HOOK_REGISTER, array( $this, 'takeover_detected' ) );
		foreach ( self::RENDER_HOOKS as $hook ) {
			add_action( $hook[0], array( $this, 'skip_module_pass' ), $hook[1] - 1 );
			add_action( $hook[0], array( $this, 'resume_after_module_pass' ), $hook[1] + 1 );
		}
		add_filter( 'wffn_bundles_inline_design_vars', array( $this, 'inline_design_vars' ) );
		add_filter( 'wffn_bundles_inline_skin_css', array( $this, 'inline_skin_css' ) );
		add_filter( 'wffn_bundles_skin_styles', array( $this, 'add_reset_antidote' ) );
	}

	/**
	 * Is this add-to-cart pass owned by one of Divi's WooCommerce modules?
	 *
	 * Divi brackets et_builder_wc_render_module_template() in no hook of its own,
	 * so the call stack is the only marker available.
	 *
	 * @return bool
	 */
	private static function in_module_template() {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Divi offers no hook around the pass; see docblock.
			if ( self::MODULE_RENDERER === ( $frame['function'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Let the theme's own pass render the widget, not Divi's module pass.
	 *
	 * Without takeover, a block theme serialises the module pass into script data
	 * rather than printing it, while it still claims Product_Display's
	 * once-per-request slot. Skipping it leaves exactly one candidate.
	 *
	 * @return void
	 */
	public function skip_module_pass() {
		// Only a single-product page has a second, theme-owned pass to fall back on;
		// elsewhere skipping would remove the only candidate.
		if ( $this->takeover || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		if ( ! self::in_module_template() ) {
			return;
		}
		$this->detach();
		$this->skipping = true;
	}

	/**
	 * Put the render back for the next pass.
	 *
	 * @return void
	 */
	public function resume_after_module_pass() {
		if ( ! $this->skipping ) {
			return;
		}
		$this->skipping = false;
		$display        = Product_Display::get_instance();
		foreach ( self::RENDER_HOOKS as $hook ) {
			add_action( $hook[0], array( $display, 'render' ), $hook[1] );
		}
	}

	/**
	 * Append the sheet that restores what Divi's layout reset strips.
	 *
	 * Rides the skin-style list rather than its own handle, to inherit that path's
	 * cache-busting, its ordering (last) and its builder inlining.
	 *
	 * @param string[] $css Plugin-root-relative stylesheet paths.
	 * @return string[]
	 */
	public function add_reset_antidote( $css ) {
		/**
		 * Filters the sheets restoring what Divi's layout reset strips.
		 *
		 * An add-on appends its own for the skins it renders.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $sheets Stylesheet paths, plugin-root-relative or absolute.
		 */
		return array_merge( (array) $css, (array) apply_filters( 'wffn_bundles_divi_reset_sheets', array( self::RESET_ANTIDOTE ) ) );
	}

	/**
	 * Inline the skin sheets in the Visual Builder, whose app-frame document never
	 * prints our style queue.
	 *
	 * @param bool $inline Whether the caller already decided to inline.
	 * @return bool
	 */
	public function inline_skin_css( $inline ) {
		return $inline || self::in_builder();
	}

	/**
	 * Same reason: the design tokens are a late style-queue write, so the root keeps
	 * its wffn-bundles-design-* class while nothing defines those properties.
	 *
	 * @param bool $inline Whether the caller already decided to inline.
	 * @return bool
	 */
	public function inline_design_vars( $inline ) {
		return $inline || self::in_builder();
	}

	/**
	 * Divi is about to take the product layout over.
	 *
	 * Detach our render until Divi's own bracket opens. Fired on `wp`, before any
	 * template runs, so it lands ahead of every pass.
	 *
	 * @return void
	 */
	public function takeover_detected() {
		// The Visual Builder renders outside the bracket and has one pass only.
		if ( self::in_builder() ) {
			return;
		}
		$this->takeover   = true;
		$this->queried_id = (int) get_queried_object_id();
		$this->detach();
		add_action( self::HOOK_BEFORE, array( $this, 'attach' ) );
		add_action( self::HOOK_AFTER, array( $this, 'detach' ) );
	}

	/**
	 * Is Divi's own editor or preview driving this render?
	 *
	 * The third leg is load-bearing: on the Visual Builder's own admin-ajax render
	 * et_core_is_fb_enabled() is false, because `$_GET['et_fb']` is not carried.
	 *
	 * @return bool
	 */
	public static function in_builder() {
		if ( ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() )
			|| ( function_exists( 'is_et_pb_preview' ) && is_et_pb_preview() ) ) {
			return true;
		}
		if ( ! wp_doing_ajax() ) {
			return false;
		}
		$action = Request_Context::raw_request( 'action' );
		$action = null === $action ? '' : sanitize_key( $action );
		return 0 === strpos( $action, 'et_fb_' ) || 0 === strpos( $action, 'et_pb_' );
	}

	/**
	 * Let the bundle render inside Divi's bracket, through our own gate.
	 *
	 * @return void
	 */
	public function attach() {
		foreach ( self::RENDER_HOOKS as $hook ) {
			add_action( $hook[0], array( $this, 'render' ), $hook[1] );
		}
	}

	/**
	 * Take the render back off, on both the plugin's hooks and our own.
	 *
	 * @return void
	 */
	public function detach() {
		$display = Product_Display::get_instance();
		foreach ( self::RENDER_HOOKS as $hook ) {
			remove_action( $hook[0], array( $display, 'render' ), $hook[1] );
			remove_action( $hook[0], array( $this, 'render' ), $hook[1] );
		}
	}

	/**
	 * Render, unless the template is running for a neighbouring product.
	 *
	 * Divi's Upsells and Related modules drive the single add-to-cart template for
	 * a neighbouring product inside this same bracket, one form per card.
	 *
	 * @return void
	 */
	public function render() {
		$current = ( $GLOBALS['product'] ?? null ) instanceof \WC_Product ? (int) $GLOBALS['product']->get_id() : 0;
		if ( $this->queried_id && $current && $current !== $this->queried_id ) {
			return;
		}
		Product_Display::get_instance()->render();
	}
}
