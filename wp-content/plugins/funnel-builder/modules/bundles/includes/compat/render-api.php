<?php
/** Render_Api — the builder-agnostic facade page-builder adapters render through. */
namespace WFFN\Bundles\Includes\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Ajax\Controller;

/**
 * The plugin's only render entry point for third-party builders.
 *
 * Nothing here may name a builder's symbols; builder state arrives as plain
 * values in $args.
 */
class Render_Api {

	/**
	 * The bundle widget's markup for a product, as a string.
	 *
	 * @param array $args {
	 *     @type bool   $builder_context Caller is an editor/preview render, supplied
	 *                                   by the adapter.
	 *     @type string $button_mode     'auto' | 'always' | 'never'. Default 'auto'.
	 *     @type int    $product_id      Explicit product, overriding query resolution.
	 *     @type bool   $allow_repeat    Render again for a product already rendered
	 *                                   this request, without consuming its slot.
	 *     @type string $lock_offer      Restrict the widget to one specific offer id
	 *                                   ('fb-single'/'fb-bundle' for a Fixed Bundle) —
	 *                                   the offer a row was pinned to when added to a
	 *                                   One-Click Upsell.
	 * }
	 * @return string
	 */
	public static function widget_html( array $args = array() ) {
		$editor = ! empty( $args['builder_context'] );
		$mode   = isset( $args['button_mode'] ) ? (string) $args['button_mode'] : 'auto';

		$product = self::resolve_product( $args );
		if ( ! $product ) {
			return $editor ? self::notice( __( 'Select a product to preview the bundle.', 'funnel-builder' ) ) : '';
		}

		// Checked here so a wrong-hook pass never burns the product's render slot.
		if ( empty( $args['allow_repeat'] ) && Product_Display::is_rendered( $product->get_id() ) ) {
			return $editor ? self::notice( __( 'The bundle is already displayed by the Add To Cart widget on this template.', 'funnel-builder' ) ) : '';
		}

		// plugin.php skips Product_Display on admin requests, which the editor render is.
		$html = Product_Display::get_instance()->render_product( $product, $args );
		if ( '' === $html ) {
			return $editor ? self::notice( __( 'No active bundle for this product. The widget stays hidden on the storefront.', 'funnel-builder' ) ) : '';
		}

		return self::wffn_bundles_cart_fallback() . self::wrap( $html, $product, $mode );
	}

	/**
	 * The selectors the live re-quote writes the bundle price into.
	 *
	 * @return string[]
	 */
	public static function price_selectors() {
		/**
		 * Product-price elements the live quote updates, most specific first.
		 *
		 * @param string[] $selectors CSS selectors.
		 */
		return (array) apply_filters(
			'wffn_bundles_price_selectors',
			array( 'p.price', '.wp-block-woocommerce-product-price', '.woocommerce-variation-price' )
		);
	}

	/**
	 * The product this render is for: explicit id, then the global, then the query.
	 *
	 * @param array $args Render arguments.
	 * @return \WC_Product|null
	 */
	private static function resolve_product( array $args ) {
		$pid = (int) ( $args['product_id'] ?? 0 );
		if ( $pid > 0 ) {
			$explicit = wc_get_product( $pid );
			return $explicit ? $explicit : null;
		}
		if ( ( $GLOBALS['product'] ?? null ) instanceof \WC_Product ) {
			return $GLOBALS['product'];
		}
		$queried = wc_get_product( get_the_ID() );
		return $queried ? $queried : null;
	}

	/**
	 * Give the bundle a `form.cart` of its own.
	 *
	 * A builder template may carry no add-to-cart form at all, so the wrapper is
	 * always emitted and resolved client-side. See behavior/core.js resolveStandalone.
	 *
	 * @param string      $html    Rendered bundle markup.
	 * @param \WC_Product $product Product the form posts.
	 * @param string      $mode    'auto' | 'always' | 'never'.
	 * @return string
	 */
	private static function wrap( $html, $product, $mode ) {
		if ( 'never' === $mode ) {
			return $html;
		}
		$pid  = (int) $product->get_id();
		$auto = 'always' !== $mode;

		// Hidden for every type: the widget owns the quantity.
		$quantity = '<input data-wffn-bundles-standalone-field type="hidden" name="quantity" value="1" />';

		ob_start();
		Cart::get_instance()->form_fields( $product );
		$fields = ob_get_clean();

		$out = '';
		if ( $auto ) {
			// Keyed on an attribute the JS adds, so a page without our JS keeps a visible button.
			$out .= Assets::inline_style_markup( 'wffn-bundles-standalone-guard', 'form[data-wffn-bundles-standalone]:not([data-wffn-bundles-standalone-resolved]) .wffn-bundles-standalone-submit{visibility:hidden}', true );
		}

		$out .= '<form class="cart wffn-bundles-standalone-form" method="post" enctype="multipart/form-data"'
			. ( $auto
				? ' data-wffn-bundles-standalone="' . esc_attr( (string) $pid ) . '"'
				: ' data-wffn-bundles-standalone-resolved="1"' )
			. '>'
			. '<input data-wffn-bundles-standalone-field type="hidden" name="add-to-cart" value="' . esc_attr( (string) $pid ) . '" />'
			. '<input data-wffn-bundles-standalone-field type="hidden" name="product_id" value="' . esc_attr( (string) $pid ) . '" />'
			. $quantity
			// Wrapped so the client-side unwrap can drop every injected field at once.
			. '<span class="wffn-bundles-standalone-fields" data-wffn-bundles-standalone-field>' . $fields . '</span>'
			. $html
			. '<button type="submit" class="single_add_to_cart_button button alt wffn-bundles-standalone-submit" data-wffn-bundles-standalone-field>'
			. esc_html( $product->single_add_to_cart_text() )
			. '</button>'
			. '</form>';

		return $out;
	}

	/**
	 * Inline `window.wffnBundlesCart` where the script queue can no longer carry it.
	 *
	 * An editor's per-widget AJAX re-render never runs the print hooks, so
	 * `wp_script_is( …, 'done' )` is false in exactly the case this exists for.
	 *
	 * @return string
	 */
	private static function wffn_bundles_cart_fallback() {
		if ( ! ( wp_doing_ajax() || did_action( 'wp_print_footer_scripts' ) || wp_script_is( Assets::HANDLE, 'done' ) ) ) {
			return '';
		}
		$json = wp_json_encode( Controller::config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		return wp_get_inline_script_tag( 'window.wffnBundlesCart=window.wffnBundlesCart||' . ( $json ? $json : '{}' ) . ';', array( 'id' => 'wffn-bundles-cart-fallback' ) );
	}

	/**
	 * An editor-only diagnosis. Never reachable on the storefront: callers gate it.
	 *
	 * @param string $text Message.
	 * @return string
	 */
	private static function notice( $text ) {
		return '<div class="wffn-bundles-editor-notice">' . esc_html( $text ) . '</div>';
	}
}
