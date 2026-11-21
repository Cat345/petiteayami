<?php
/**
 * Adapter — WordPress block editor (Gutenberg) compatibility.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Compat\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Compat\Render_Api;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Storage\Bundle_Store;

/**
 * The bundle's editor preview for WooCommerce's add-to-cart blocks.
 *
 * Neither woocommerce/add-to-cart-with-options nor woocommerce/add-to-cart-form
 * server-renders in the canvas, so woocommerce_before_add_to_cart_form — the only
 * hook Product_Display binds — cannot fire while the merchant is editing.
 *
 * The block registered here is inserter-hidden, never saved into post content,
 * and is a render target for wp.serverSideRender only. See
 * assets/compat/blocks-editor.js.
 */
class Adapter implements Integration {

	/** Inserter-hidden block backing the editor preview. */
	const BLOCK = 'wffn-bundles/bundle-preview';

	/** Editor script handle. */
	const SCRIPT = 'wffn-bundles-compat-blocks-editor';

	/** Editor script, relative to the plugin root. */
	const SCRIPT_PATH = 'assets/compat/blocks-editor.js';

	/**
	 * Products whose Add to Cart Button renders inside a Single Product block.
	 *
	 * @var array<int,true>
	 */
	private static $in_single_product = array();

	/**
	 * No third-party plugin backs this adapter — block registration is core.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return function_exists( 'register_block_type' );
	}

	/**
	 * Register the adapter's hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
		add_action( 'admin_notices', array( $this, 'classic_theme_notice' ) );
		add_filter( 'render_block_context', array( $this, 'note_single_product' ), 20, 2 );
		add_filter( 'render_block_woocommerce/product-button', array( $this, 'prepend_bundle' ), 10, 3 );
	}

	/**
	 * Remember a button the Single Product block wraps.
	 *
	 * That block flags its inner blocks with `singleProduct`, but ProductButton does
	 * not declare the key in usesContext, so the flag is gone from its instance by the
	 * time it renders; this filter is the last place it can be seen.
	 *
	 * @param array $context      Context the block will receive.
	 * @param array $parsed_block Block being rendered.
	 * @return array
	 */
	public function note_single_product( $context, $parsed_block ) {
		if ( 'woocommerce/product-button' === ( $parsed_block['blockName'] ?? '' ) && ! empty( $context['singleProduct'] ) && ! empty( $context['postId'] ) ) {
			self::$in_single_product[ (int) $context['postId'] ] = true;
		}
		return $context;
	}

	/**
	 * Render the bundle above the Add to Cart Button block.
	 *
	 * ProductButton emits a bare Store API button and fires none of the add-to-cart
	 * hooks. It is also the button on every card of a products grid, hence the
	 * single-product gate.
	 *
	 * @param string    $content  Block markup.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance carrying the resolved context.
	 * @return string
	 */
	public function prepend_bundle( $content, $block, $instance = null ) {
		if ( ! $instance instanceof \WP_Block || ! is_array( $instance->context ) ) {
			return $content;
		}
		// Also the submit button inside add-to-cart-with-options, whose own render
		// already fired the hook.
		if ( ! empty( $instance->context['woocommerce/isDescendantOfAddToCartWithOptions'] ) ) {
			return $content;
		}
		$product_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;
		if ( ! $product_id || ! self::single_product( $product_id ) ) {
			return $content;
		}

		// 'auto': a Store API post leaves no form.cart for Cart::form_fields().
		$html = Render_Api::widget_html(
			array(
				'product_id'  => $product_id,
				'button_mode' => 'auto',
			)
		);

		// Replaces the button rather than standing beside it: a Store API add carries
		// no wffn_bundles_bundle_id, so its click would buy the anchor at full price.
		return '' === $html ? $content : $html;
	}

	/**
	 * Is this block showing the product page's own product, rather than a
	 * card for a different product (grid, related products, upsells)?
	 *
	 * Two kinds of evidence: a Single Product block wrapping the button (any page,
	 * see note_single_product()), or the product page's queried object matching the
	 * block's product. A grid card has neither.
	 *
	 * @param int $product_id Product id the block resolved from context.
	 * @return bool
	 */
	private static function single_product( $product_id ) {
		return isset( self::$in_single_product[ $product_id ] ) || ( is_product() && get_queried_object_id() === $product_id );
	}

	/**
	 * Register the preview block.
	 *
	 * Unconditional rather than editor-gated: wp.serverSideRender reaches it over
	 * the block-renderer REST route, a separate request from the editor's.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			return;
		}
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'productId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
				'supports'        => array(
					'inserter' => false,
					'html'     => false,
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * The widget markup for the product the edited block points at.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$product_id = isset( $attributes['productId'] ) ? (int) $attributes['productId'] : 0;
		if ( ! $product_id ) {
			return '';
		}

		// The canvas injects the response as raw HTML and never prints our style
		// queue, so sheets and design vars have to ride in the markup.
		add_filter( 'wffn_bundles_inline_skin_css', '__return_true' );
		add_filter( 'wffn_bundles_inline_design_vars', '__return_true' );

		$html = Render_Api::widget_html(
			array(
				'product_id'      => $product_id,
				'builder_context' => true,
				// The edited block prints its own Add to cart.
				'button_mode'     => 'never',
			)
		);

		remove_filter( 'wffn_bundles_inline_skin_css', '__return_true' );
		remove_filter( 'wffn_bundles_inline_design_vars', '__return_true' );

		return $html;
	}

	/**
	 * Warn when the edited post pairs a bundle product with a block the active
	 * theme cannot render.
	 *
	 * WooCommerce registers woocommerce/add-to-cart-with-options only for block
	 * themes, so on a classic one the hook the widget rides never fires.
	 *
	 * @return void
	 */
	public function classic_theme_notice() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! has_block( 'woocommerce/add-to-cart-with-options', $post ) ) {
			return;
		}
		if ( ! self::bundle_product_in( parse_blocks( $post->post_content ) ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Bundles For WooCommerce: this page uses the Add to Cart + Options block, which WooCommerce supports only on block themes. Your bundle will not appear on the published page. Swap it for the Add to Cart block to show the bundle.', 'funnel-builder' )
		);
	}

	/**
	 * Does any block in this tree name a product with an active bundle?
	 *
	 * @param array $blocks Parsed blocks.
	 * @return bool
	 */
	private static function bundle_product_in( array $blocks ) {
		foreach ( $blocks as $block ) {
			$pid = isset( $block['attrs']['productId'] ) ? (int) $block['attrs']['productId'] : 0;
			if ( $pid && null !== Bundle_Store::active_for_product( $pid ) ) {
				return true;
			}
			if ( ! empty( $block['innerBlocks'] ) && self::bundle_product_in( $block['innerBlocks'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The editor filter that puts the preview above both add-to-cart blocks.
	 *
	 * @return void
	 */
	public function enqueue_editor() {
		wp_enqueue_script(
			self::SCRIPT,
			WFFN_BUNDLES_URL . self::SCRIPT_PATH,
			array( 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-compose', 'wp-server-side-render' ),
			Assets::ver( self::SCRIPT_PATH ),
			true
		);
	}
}
