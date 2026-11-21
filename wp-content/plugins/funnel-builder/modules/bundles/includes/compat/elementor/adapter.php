<?php
/**
 * Adapter — Elementor / Elementor Pro compatibility.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Compat\Render_Api;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Frontend\Cart;

/**
 * Elementor compatibility adapter.
 *
 * No file here names an `\Elementor\*` symbol at file scope — every reference
 * sits inside a method body behind class_exists/method_exists, so a deactivated
 * Elementor cannot fatal us.
 *
 * The plugin ships no Elementor widget. A bundle reaches an Elementor product
 * page by two routes: the ordinary hook path, whenever a widget calls
 * woocommerce_template_single_add_to_cart(), and this adapter's render_content
 * prepend, for Custom Add To Cart in its default AJAX-button mode.
 */
class Adapter implements Integration {

	/** Elementor Pro's "Custom Add To Cart" widget. */
	const WIDGET_CUSTOM_ATC = 'wc-add-to-cart';

	/** Elementor Pro's Purchase Summary (thank-you) widget. */
	const WIDGET_PURCHASE_SUMMARY = 'woocommerce-purchase-summary';

	/** Compatibility script handle. */
	const SCRIPT = 'wffn-bundles-compat-elementor';

	/** Elementor Pro's Menu Cart stylesheet, which our mini-cart rules ride. */
	const STYLE_MENU_CART = 'widget-woocommerce-menu-cart';

	/** Did a bundle render on this request? Raised by wffn_bundles_bundle_rendered. */
	private $rendered = false;

	/**
	 * Is Elementor loaded at all? Pro is detected separately, where it is needed.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * Register the adapter's hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_assets' ) );
		add_action( 'wffn_bundles_bundle_rendered', array( $this, 'mark_rendered' ) );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_script' ), 5 );

		add_filter( 'elementor/widget/render_content', array( $this, 'filter_widget_content' ), 10, 2 );
		add_filter( 'elementor/element/is_dynamic_content', array( $this, 'render_custom_atc_live' ), 10, 2 );

		Preview_Assets::init();
	}

	/**
	 * Keep the Custom Add To Cart widget out of Elementor's element cache.
	 *
	 * A cached widget is replayed as stored HTML, so filter_widget_content() never runs
	 * on later visits and the bundle markup comes back without the scripts it enqueues.
	 *
	 * @param bool  $is_dynamic Whether Elementor already renders it per request.
	 * @param mixed $raw_data   The element's stored data.
	 * @return bool
	 */
	public function render_custom_atc_live( $is_dynamic, $raw_data ) {
		if ( $is_dynamic || ! is_array( $raw_data ) ) {
			return $is_dynamic;
		}
		// With no product chosen the widget falls back to the current product, which can carry a bundle too.
		return self::WIDGET_CUSTOM_ATC === ( $raw_data['widgetType'] ?? '' );
	}

	/**
	 * The document being rendered, or null.
	 *
	 * @return \Elementor\Core\Base\Document|null
	 */
	public static function current_document() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) ) {
			return null;
		}
		$documents = \Elementor\Plugin::$instance->documents;
		if ( ! method_exists( $documents, 'get_current' ) ) {
			return null;
		}
		return $documents->get_current();
	}

	/**
	 * Is the caller rendering inside a loop template or a Woo products loop?
	 *
	 * @return bool
	 */
	public static function in_loop() {
		$doc  = self::current_document();
		$type = $doc && method_exists( $doc, 'get_type' ) ? (string) $doc->get_type() : '';
		// The flag's only setter is the Woo Products skin, so a Loop Grid on the
		// default post skin never sets it.
		return 'loop-item' === $type || ! empty( $GLOBALS['wp_query']->is_loop_product );
	}

	/**
	 * Register our handles alongside Elementor's, so a widget can declare them.
	 *
	 * @return void
	 */
	public function register_assets() {
		Assets::register_handles( true );
		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script(
				self::SCRIPT,
				WFFN_BUNDLES_URL . 'assets/compat/elementor.js',
				array( 'elementor-frontend', Assets::HANDLE ),
				Assets::ver( 'assets/compat/elementor.js' ),
				true
			);
		}
		if ( ! wp_style_is( 'wffn-bundles-compat-elementor', 'registered' ) ) {
			wp_register_style(
				'wffn-bundles-compat-elementor',
				WFFN_BUNDLES_URL . 'assets/skins/compatibility/builders/elementor.css',
				array(),
				Assets::ver( 'assets/skins/compatibility/builders/elementor.css' )
			);
		}
	}

	/**
	 * A bundle rendered on this request — whichever entry point produced it.
	 *
	 * @return void
	 */
	public function mark_rendered() {
		$this->rendered = true;
	}

	/**
	 * Enqueue the compatibility script, late, when both conditions hold.
	 *
	 * has_elementor_in_page() is only set after the document's buffer closes, so
	 * the test cannot be made at render time. It is needed because
	 * `elementor-frontend` is registered unconditionally while its config object is
	 * printed only for pages with Elementor content.
	 *
	 * @return void
	 */
	public function maybe_enqueue_script() {
		// The Menu Cart dropdown appears on pages with no bundle, so its sheet
		// follows Elementor's own handle rather than the script.
		if ( wp_style_is( self::STYLE_MENU_CART, 'enqueued' ) || wp_style_is( self::STYLE_MENU_CART, 'done' ) ) {
			wp_enqueue_style( 'wffn-bundles-compat-elementor' );
		}
		if ( ! $this->rendered || ! self::has_elementor_in_page() ) {
			return;
		}
		wp_enqueue_script( self::SCRIPT );
		wp_enqueue_style( 'wffn-bundles-compat-elementor' );
	}

	/**
	 * Did Elementor render any of this page?
	 *
	 * @return bool
	 */
	public static function has_elementor_in_page() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->frontend ) ) {
			return false;
		}
		$frontend = \Elementor\Plugin::$instance->frontend;
		return method_exists( $frontend, 'has_elementor_in_page' ) && $frontend->has_elementor_in_page();
	}

	/**
	 * Route a rendered Elementor widget to its own guard.
	 *
	 * @param string                 $content Rendered widget markup.
	 * @param \Elementor\Widget_Base $widget  Widget that produced it.
	 * @return string
	 */
	public function filter_widget_content( $content, $widget ) {
		// Re-checked at render time: Elementor can be deactivated after content was
		// authored with it.
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return $content;
		}
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $content;
		}
		switch ( $widget->get_name() ) {
			case self::WIDGET_CUSTOM_ATC:
				return $this->prepend_bundle( $content, $widget );
			case self::WIDGET_PURCHASE_SUMMARY:
				return $this->thankyou_styles( $content );
		}
		return $content;
	}

	/**
	 * Give the Custom Add To Cart widget's default mode a bundle to sell.
	 *
	 * In that mode the widget prints a bare AJAX link — no form, no hooks, no
	 * bundle field. Always a frontend render (builder_context false): in the
	 * editor, form mode already rendered through the hook path and claimed the slot.
	 *
	 * @param string                 $content Rendered widget markup.
	 * @param \Elementor\Widget_Base $widget  Widget that produced it.
	 * @return string
	 */
	private function prepend_bundle( $content, $widget ) {
		// Inside a loop this would emit one standalone form per card, and the widget
		// resolves the queried object rather than the card's product.
		if ( self::in_loop() ) {
			return $content;
		}
		$args = array( 'builder_context' => false );
		if ( method_exists( $widget, 'get_settings_for_display' ) ) {
			$pid = (int) $widget->get_settings_for_display( 'product_id' );
			if ( $pid > 0 ) {
				$args['product_id'] = $pid;
			}
		}
		$html = Render_Api::widget_html( $args );
		// A body means bare-link mode, so replace the link rather than sit beside it:
		// its ?add-to-cart= carries no bundle and would buy at full price.
		return '' === $html ? $content : $html;
	}

	/**
	 * The order-grouping stylesheet for a Purchase Summary render.
	 *
	 * In the editor `set_preview_order()` synthesises the order-received query var
	 * but is_order_received_page() stays false, so Order_Display's gate never fires.
	 * The editor's per-widget re-render prints no style queue, so it is inlined.
	 *
	 * @param string $content Rendered widget markup.
	 * @return string
	 */
	private function thankyou_styles( $content ) {
		if ( false === strpos( (string) $content, 'wffn-bundles-cart-child' ) ) {
			return $content;
		}
		// Not wp_style_is( 'done' ), false on the AJAX re-render this exists for, and
		// not did_action( 'wp_print_styles' ), true on every frontend page.
		if ( ! ( wp_doing_ajax() || did_action( 'wp_print_footer_scripts' ) ) ) {
			// Not Order_Display::enqueue_css(): its gate is what fails in the editor.
			$rel = 'assets/skins/cart-blocks.css';
			wp_enqueue_style( 'wffn-bundles-cart-grouping', WFFN_BUNDLES_URL . $rel, array(), Assets::ver( $rel ) );
			Cart::enqueue_theme_compat_css();
			return $content;
		}
		return $this->inline_styles() . $content;
	}

	/**
	 * The grouping stylesheet plus its per-theme corrections, in cascade order.
	 *
	 * @return string
	 */
	private function inline_styles() {
		$paths = array_merge( array( 'assets/skins/cart-blocks.css' ), \WFFN\Bundles\Includes\Skins\Theme_Compat::styles() );
		return Assets::print_style_paths( $paths );
	}
}
