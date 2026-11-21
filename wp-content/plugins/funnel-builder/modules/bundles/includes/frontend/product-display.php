<?php
/** Product_Display — render the saved bundle on the storefront product page. */
namespace WFFN\Bundles\Includes\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Skins\Fixed_Bundle;
use WFFN\Bundles\Includes\Skins\Registry;
use WFFN\Bundles\Includes\Skins\Frequently_Bought_Together;
use WFFN\Bundles\Includes\Storage\Install;
use WFFN\Bundles\Includes\Skins\Variants;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Skins\Css_Vars;
use WFFN\Bundles\Includes\Skins\Options_Display;
use WFFN\Bundles\Includes\Skins\Theme_Compat;
use WFFN\Bundles\Includes\Ajax\Controller;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Products\Repository;
use WFFN\Bundles\Includes\Compat\Currency\Layer;
use WFFN\Bundles\Includes\Compat\Discount\Layer as Discount_Layer;
use WFFN\Bundles\Includes\Helpers\Markup;
use WFFN\Bundles\Includes\Helpers\WooCommerce;

/**
 * Renders the winning bundle's bundle widget on the storefront product page.
 */
class Product_Display {

	/**
	 * Where the widget can sit on a product page, and the hooks that put it there.
	 *
	 * Themes disagree about this region, so it is a setting rather than a
	 * filter: the merchant who needs to move it is rarely the one who can
	 * write a callback.
	 */
	const PLACEMENTS = array(
		'before_cart'   => array( 'woocommerce_before_add_to_cart_form' => 5, 'woocommerce_before_variations_form' => 5 ),
		'after_cart'    => array( 'woocommerce_after_add_to_cart_form' => 5, 'woocommerce_after_variations_form' => 5 ),
		'after_summary' => array( 'woocommerce_single_product_summary' => 45 ),
		'after_excerpt' => array( 'woocommerce_single_product_summary' => 25 ),
	);

	/**
	 * Blocks a block-template product page renders the summary placements after.
	 *
	 * A block template fires woocommerce_single_product_summary once, ahead of the
	 * excerpt, so its priorities cannot order the widget there.
	 */
	const BLOCK_ANCHORS = array(
		'after_excerpt' => array( 'core/post-excerpt', 'woocommerce/product-summary' ),
		'after_summary' => array( 'woocommerce/product-meta' ),
	);

	/** The placement used until the merchant picks another. */
	const DEFAULT_PLACEMENT = 'before_cart';

	private static $instance = null;

	/** Resolved bundle per product id (one lookup per request). */
	private $cache = array();


	/** Whether the cart AJAX config was already localized this request. */
	private $cart_localized = false;

	/** Scope classes whose design CSS vars were already emitted this request. */
	private $vars_emitted = array();

	/** Whether the store-wide Settings colours were already emitted this request. */
	private $global_vars_emitted = false;

	/** Whether the quick-view pre-paint guard was already emitted this request. */
	private $guard_emitted = false;

	/** @var bool */
	private $paint_guard_emitted = false;

	/**
	 * Product ids whose widget has already been rendered this request.
	 *
	 * render() fires once per add-to-cart form, not once per page, and a theme
	 * with a sticky add-to-cart bar prints a second `form.cart` for the same
	 * product. Cart::form_fields() is hooked separately and stays ungated, so the
	 * sticky bar's CTA still carries the hidden inputs.
	 */
	private static $rendered = array();

	/** Per-request counter behind the unique radio-group suffix. */
	private static $instance_seq = 0;

	/** Product ids whose bought-together set has already been rendered this request. */
	private static $fbt_rendered = array();

	/**
	 * How many offers stay visible before the "Show all N options" expander.
	 *
	 * PHP owns the value and ships it to behavior/collapse.js through
	 * `wffnBundlesCart.offersLimit`: the pre-paint guard must hide exactly the rows
	 * collapse.js will hide.
	 */
	const OFFERS_LIMIT = 6;

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
	 * The hooks this install renders on, keyed by hook name.
	 *
	 * @return array<string,int>
	 */
	private static function hooks() {
		return self::PLACEMENTS[ self::placement() ] ?? self::PLACEMENTS[ self::DEFAULT_PLACEMENT ];
	}

	/**
	 * The hooks a Frequently Bought Together set renders on: the placement's, one step later.
	 *
	 * @return array<string,int>
	 */
	public static function fbt_hooks() {
		return array_map(
			function ( $priority ) {
				return $priority + 1;
			},
			self::hooks()
		);
	}

	/**
	 * The stored placement key, unvalidated.
	 *
	 * @return string
	 */
	private static function placement() {
		$stored = get_option( Install::OPTION_SETTINGS, array() );
		return is_array( $stored ) ? (string) ( $stored[ Install::SETTINGS_PLACEMENT ] ?? '' ) : '';
	}

	/**
	 * Does the block template being rendered hold this placement's anchor block?
	 *
	 * Only a block named in the template itself counts; one nested in a pattern
	 * or template part is not seen, and that page keeps the hook placement.
	 *
	 * @return bool
	 */
	private static function anchored_in_template() {
		global $_wp_current_template_content;
		if ( ! is_string( $_wp_current_template_content ) || '' === $_wp_current_template_content ) {
			return false;
		}
		foreach ( self::BLOCK_ANCHORS[ self::placement() ] ?? array() as $block ) {
			$name = 0 === strpos( $block, 'core/' ) ? substr( $block, 5 ) : $block;
			if ( preg_match( '/<!--\s+wp:' . preg_quote( $name, '/' ) . '[\s\/]/', $_wp_current_template_content ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register the render hooks and the early stylesheet enqueue.
	 */
	private function __construct() {
		foreach ( self::hooks() as $hook => $priority ) {
			add_action( $hook, array( $this, 'render' ), $priority );
		}
		foreach ( self::BLOCK_ANCHORS[ self::placement() ] ?? array() as $block ) {
			add_filter( "render_block_{$block}", array( $this, 'render_after_block' ), 10, 3 );
		}
		foreach ( self::fbt_hooks() as $hook => $priority ) {
			add_action( $hook, array( $this, 'render_fbt' ), $priority );
		}
		add_filter( 'body_class', array( $this, 'body_class' ) );
		// Skin CSS is claimed here, before wp_head, so it lands in <head>.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_early' ) );
	}

	/**
	 * Claim the skin stylesheets while wp_head can still print them.
	 *
	 * render() runs inside the loop, after wp_head, so its own enqueues print as
	 * late assets and the widget paints unstyled first. render() still enqueues —
	 * wp_enqueue_style() is idempotent — and stays authoritative for the contexts
	 * this hook cannot see (a loop, a shortcode, a quick-view). The gates here
	 * therefore mirror body_class() rather than render()'s item-level checks.
	 *
	 * @return void
	 */
	public function enqueue_early() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$pid = get_queried_object_id();
		if ( $pid ) {
			$this->enqueue_for_product( $pid );
			$this->enqueue_fbt_for_product( $pid );
		}
	}

	/**
	 * Enqueue what a product's bought-together set needs, or nothing when it has none.
	 *
	 * @param int $product_id Product whose set to load assets for.
	 * @return void
	 */
	public function enqueue_fbt_for_product( $product_id ) {
		$bundle = Bundle_Store::active_for_product( absint( $product_id ), Bundle_Store::SLOT_FBT );
		if ( null === $bundle ) {
			return;
		}
		$type   = Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER;
		$dc     = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$layout = self::fbt_layout( $dc );
		$this->enqueue( $type, $layout, false, $dc );
		Assets::enqueue_behavior( true, Assets::modules_for( $type, $dc ) );
		$this->enqueue_theme_compat();
	}

	/**
	 * The layout a Frequently Bought Together bundle draws.
	 *
	 * @param array $dc Saved discount config.
	 * @return string
	 */
	private static function fbt_layout( array $dc ) {
		return 'list' === (string) ( $dc['fbtLayout'] ?? 'row' ) ? 'list' : 'row';
	}

	/**
	 * Render the bought-together set for the current product.
	 *
	 * It leaves the theme's own add-to-cart form alone: the set has its own
	 * button, and the product can still be bought on its own at its own price.
	 *
	 * @return void
	 */
	public function render_fbt() {
		$product = ( $GLOBALS['product'] ?? null ) instanceof \WC_Product ? $GLOBALS['product'] : wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}
		/** This filter is documented in includes/frontend/product-display.php */
		if ( ! apply_filters( 'wffn_bundles_render_product_widget', true, $product ) ) {
			return;
		}
		if ( $this->skips_current_hook( $product ) ) {
			return;
		}
		Markup::output( $this->render_fbt_product( $product ) );
	}

	/**
	 * Whether the placement hook now firing is not the one this product renders on.
	 *
	 * @param \WC_Product $product Product the hook fired for.
	 * @return bool
	 */
	private function skips_current_hook( $product ) {
		// render_after_block() owns this placement on a template holding its anchor block.
		if ( 'woocommerce_single_product_summary' === current_action() && self::anchored_in_template() ) {
			return true;
		}

		// Only a placement with its own variations-form hook needs to pick a
		// side; a single-hook placement renders for every product type.
		$var_hook = null;
		foreach ( array_keys( self::hooks() ) as $hook ) {
			if ( false !== strpos( $hook, 'variations_form' ) ) {
				$var_hook = $hook;
				break;
			}
		}
		return null !== $var_hook && $product->is_type( 'variable' ) !== ( current_action() === $var_hook );
	}

	/**
	 * The bought-together markup for a resolved product, as a string.
	 *
	 * @param \WC_Product $product Resolved product.
	 * @param array       $args    {
	 *     Optional.
	 *
	 *     @type bool $button       Draw the set's own add button. Default true; a host
	 *                              that owns the submit draws it without.
	 *     @type bool $allow_repeat Render even when this product's set already rendered.
	 *     @type array $set         The set already in the cart: { host: picks, items: { companion id: picks } }.
	 *                              Its companions show ticked on those picks, the rest unticked.
	 * }
	 * @return string
	 */
	public function render_fbt_product( $product, array $args = array() ) {
		return Discount_Layer::with_display_suppressed(
			function () use ( $product, $args ) {
				return $this->render_fbt_markup( $product, $args );
			}
		);
	}

	/**
	 * The bought-together markup, with any discount engine's price display suspended.
	 *
	 * @param \WC_Product $product Resolved product.
	 * @param array       $args    See render_fbt_product().
	 * @return string
	 */
	private function render_fbt_markup( $product, array $args = array() ) {
		$pid = self::anchor_id( $product );
		if ( isset( self::$fbt_rendered[ $pid ] ) && empty( $args['allow_repeat'] ) ) {
			return '';
		}
		$bundle = Bundle_Store::active_for_product( $pid, Bundle_Store::SLOT_FBT );
		if ( null === $bundle ) {
			return '';
		}
		$type   = Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER;
		$dc     = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$design = is_array( $dc['bundleDesign'] ?? null ) ? $dc['bundleDesign'] : array();
		$set = is_array( $args['set'] ?? null ) ? $args['set'] : null;
		/**
		 * Filters the bought-together set the widget opens on.
		 *
		 * Null opens it on its defaults. A context that reopens a set already in the cart supplies
		 * `array( 'items' => array( product id => variation attributes ) )`, so the companions in
		 * that set are ticked rather than shown as already in the cart.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null $set Set the caller supplied.
		 * @param int        $pid Product the widget renders for.
		 */
		$set   = apply_filters( 'wffn_bundles_fbt_set', $set, $pid );
		$input = $this->fbt_input( $bundle, $dc, $design, $product, is_array( $set ) ? $set : null );
		if ( null === $input ) {
			return '';
		}
		self::$fbt_rendered[ $pid ] = true;
		$input['buttonShow']        = ! isset( $args['button'] ) || ! empty( $args['button'] );

		$layout      = self::fbt_layout( $dc );
		$bundle_id = (string) ( $bundle['id'] ?? '' );
		/** This filter is documented in includes/frontend/product-display.php */
		$input = (array) apply_filters(
			'wffn_bundles_bundle_args',
			$input,
			$product,
			$type,
			$dc,
			array(
				'layout'      => $layout,
				'addons'      => false,
				'bundle_id' => $bundle_id,
			)
		);
		$out         = $this->enqueue( $type, $layout, true, $dc );
		Assets::enqueue_behavior( true, Assets::modules_for( $type, $dc ) );
		$out .= $this->emit_design_vars( $design, $bundle_id, $dc );
		$this->localize_cart();
		$out .= $this->emit_variant_guard();
		// No `wffn-bundles-has-bundle`: that flag hides the theme's quantity and variation controls.
		$flags = array_values(
			array_filter(
				self::bundle_flags( $type, $dc, $product ),
				function ( $flag ) {
					return 'wffn-bundles-has-bundle' !== $flag;
				}
			)
		);
		$out .= self::scoped( Registry::render( $type, $layout, $input ), self::scope_class( $bundle_id ), $flags );

		/** This action is documented in includes/frontend/product-display.php */
		do_action( 'wffn_bundles_bundle_rendered', $product, array() );

		return $out;
	}

	/**
	 * The viewed product's picker axes, for the widget's own "This item" row.
	 *
	 * Only the store's own default attributes are preselected, never the
	 * fallback combination a companion's picker starts on.
	 *
	 * @param \WC_Product $host     The viewed product.
	 * @param array       $host_row Its shaped product row.
	 * @return array[]
	 */
	private static function fbt_host_axes( $host, array $host_row ) {
		$rows = is_array( $host_row['attributes'] ?? null ) ? array_values( $host_row['attributes'] ) : array();
		if ( ! $rows || ! $host->is_type( 'variable' ) ) {
			return array();
		}
		$defaults = array();
		foreach ( (array) $host->get_default_attributes() as $name => $value ) {
			$defaults[ sanitize_title( (string) $name ) ] = (string) $value;
		}
		$axes = array();
		$i    = 0;
		// Same order and the same empty-attribute skip as the shaped row's attributes.
		foreach ( $host->get_variation_attributes() as $name => $options ) {
			if ( ! array_filter( (array) $options ) || ! isset( $rows[ $i ] ) ) {
				continue;
			}
			$key               = sanitize_title( (string) $name );
			$axis              = $rows[ $i ];
			$axis['default'] = in_array( $defaults[ $key ] ?? '', (array) ( $axis['options'] ?? array() ), true ) ? $defaults[ $key ] : '';
			$axes[]          = $axis;
			++$i;
		}
		return $axes;
	}

	/**
	 * The view-model input for a bought-together set, or null when it has nothing to offer.
	 *
	 * @param array       $bundle Resolved bundle.
	 * @param array       $dc       Saved discount config.
	 * @param array       $design   Design tokens.
	 * @param \WC_Product $product  The viewed product.
	 * @param array|null  $set      The set already in the cart; see render_fbt_product().
	 * @return array|null
	 */
	private function fbt_input( array $bundle, array $dc, array $design, $product, $set = null ) {
		$pid  = self::anchor_id( $product );
		$host = wc_get_product( $pid );
		if ( ! $host instanceof \WC_Product || ! $host->is_purchasable() ) {
			return null;
		}
		$ids = Frequently_Bought_Together::companion_ids( $dc, $host );
		if ( empty( $ids ) ) {
			return null;
		}

		$in_cart = array();
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $line ) {
				// Only a line bought on its own counts: a bundle member or free gift is not the shopper's own pick.
				if ( empty( $line['wffn_bundles_bundle'] ) ) {
					$in_cart[ (int) ( $line['product_id'] ?? 0 ) ] = true;
				}
			}
		}

		$image_size                  = $design['image_size'] ?? '';
		$host_row                    = WooCommerce::with_image_size( Repository::find( $pid ), $image_size );
		$host_row                    = is_array( $host_row ) ? $host_row : array();
		list( $host_min, $host_max ) = self::price_range( $host_row );

		$picked = is_array( $set ) ? (array) ( $set['items'] ?? array() ) : null;
		$items  = array();
		foreach ( $ids as $id ) {
			$row = WooCommerce::with_image_size( Repository::find( $id ), $image_size );
			if ( ! is_array( $row ) ) {
				continue;
			}
			list( $min, $max ) = self::price_range( $row );
			$axes              = Repository::partial_variation_axes( $id );
			$already           = null === $picked && isset( $in_cart[ $id ] );
			$items[]           = array(
				'productId'  => $id,
				'name'       => (string) ( $row['name'] ?? '' ),
				'image'      => (string) ( $row['image'] ?? '' ),
				'price'      => $min,
				'priceMax'   => $max,
				'type'       => (string) ( $row['type'] ?? '' ),
				'variations' => is_array( $row['variations'] ?? null ) ? $row['variations'] : array(),
				'attributes' => self::with_picks( $axes ? $axes : ( $row['attributes'] ?? array() ), (array) ( $picked[ $id ] ?? array() ) ),
				'inCart'     => $already,
				'checked'    => null === $picked ? ! $already && Frequently_Bought_Together::starts_checked( $dc, $id ) : isset( $picked[ $id ] ),
			);
		}
		if ( empty( $items ) ) {
			return null;
		}

		$rule    = Frequently_Bought_Together::rule( $dc );
		$heading = (string) ( $dc['bundleHeading'] ?? '' );
		return array(
			'bundleId'    => (string) ( $bundle['id'] ?? '' ),
			'heading'       => '' !== trim( $heading ) ? $heading : __( 'Frequently bought together', 'funnel-builder' ),
			'subheading'    => (string) ( $dc['bundleSubheading'] ?? '' ),
			'design'        => $design,
			'buttonLabel'   => (string) ( $dc['fbtButtonLabel'] ?? '' ),
			'scope'         => $rule['scope'],
			'mode'          => $rule['mode'],
			'value'         => $rule['value'],
			'minCompanions' => $rule['min'],
			'host'          => array(
				'productId'  => $pid,
				'name'       => (string) ( $host_row['name'] ?? $host->get_name() ),
				'image'      => (string) ( $host_row['image'] ?? '' ),
				'price'      => $host_min,
				'priceMax'   => $host_max,
				'type'       => (string) ( $host_row['type'] ?? '' ),
				'variations' => is_array( $host_row['variations'] ?? null ) ? $host_row['variations'] : array(),
				'attributes' => self::with_picks( self::fbt_host_axes( $host, $host_row ), is_array( $set ) ? (array) ( $set['host'] ?? array() ) : array() ),
			),
			'items'         => $items,
		);
	}

	/**
	 * Picker axes opened on a cart line's own choices.
	 *
	 * @param array $axes  Picker axes, each with `name`, `options` and `default`.
	 * @param array $picks The line's variation attributes, keyed `attribute_*`.
	 * @return array
	 */
	private static function with_picks( array $axes, array $picks ) {
		if ( ! $picks ) {
			return $axes;
		}
		foreach ( $axes as $i => $axis ) {
			$slug = sanitize_title( (string) ( $axis['name'] ?? '' ) );
			$pick = (string) ( $picks[ 'attribute_' . $slug ] ?? $picks[ 'attribute_pa_' . $slug ] ?? '' );
			if ( '' !== $pick && in_array( $pick, (array) ( $axis['options'] ?? array() ), true ) ) {
				$axes[ $i ]['default'] = $pick;
			}
		}
		return $axes;
	}

	/**
	 * Enqueue every asset one product's bundle widget needs, or nothing when it
	 * has no bundle to render.
	 *
	 * Public because a context that renders the widget over AJAX cannot enqueue
	 * for itself — that response prints no style or script queue — so the page
	 * hosting the AJAX has to claim them up front. See
	 * Compat\Funnel_Builder\Adapter::enqueue_quick_view_assets().
	 *
	 * @param int $product_id Product whose bundle to load assets for.
	 * @return void
	 */
	public function enqueue_for_product( $product_id ) {
		$bundle = Bundle_Store::active_for_product( absint( $product_id ) );
		if ( null === $bundle ) {
			return;
		}

		$type = (int) ( $bundle['discountType'] ?? 0 );
		$dc   = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();

		// A Fixed Bundle with a deleted/unpurchasable member renders nothing, so
		// it must not pull in stylesheets either (same gate as body_class()).
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type && ! self::fb_members_complete( $dc ) ) {
			return;
		}

		$layout = $this->bundle_layout( $type, $dc );
		if ( null === Registry::layout( $type, $layout ) ) {
			return; // Unsupported type; render() would bail too.
		}
		$this->enqueue( $type, $layout, false, $dc );
		Assets::enqueue_behavior( true, Assets::modules_for( $type, $dc ) );

		// Last, so a per-theme correction also wins on source order.
		$this->enqueue_theme_compat();
	}

	/**
	 * Flag bundle product pages so the skin CSS can hide WooCommerce's native
	 * quantity and variation controls — the bundle widget drives both.
	 *
	 * @param string[] $classes Body classes so far.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$pid      = get_queried_object_id();
			$bundle = $pid ? Bundle_Store::active_for_product( $pid ) : null;
			if ( null !== $bundle ) {
				$type = (int) ( $bundle['discountType'] ?? 0 );
				$dc   = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
				// A Fixed Bundle with an unpurchasable member renders nothing; keep the
				// flags consistent so CSS never targets a hidden widget.
				if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type && ! self::fb_members_complete( $dc ) ) {
					return $classes;
				}
				$classes = array_merge( $classes, self::bundle_flags( $type, $dc ) );

				/**
				 * Filters the body classes a page carrying a bundle gets.
				 *
				 * Body-only, not widget-root flags: a store-wide stylesheet
				 * scoped on the body still covers the relocated modals.
				 *
				 * @since 1.0.0
				 *
				 * @param string[] $extra Body classes to add. Empty by default.
				 * @param int      $type  Bundle type on the page.
				 * @param array    $dc    Bundle discount config.
				 */
				$classes = array_merge( $classes, (array) apply_filters( 'wffn_bundles_body_classes', array(), $type, $dc ) );
			} elseif ( $pid ) {
				// A bought-together set alone leaves the theme's own controls in place, so it takes none of the bundle flags.
				$set = Bundle_Store::active_for_product( $pid, Bundle_Store::SLOT_FBT );
				if ( null !== $set ) {
					$dc = is_array( $set['discountConfig'] ?? null ) ? $set['discountConfig'] : array();
					/** This filter is documented in includes/frontend/product-display.php */
					$classes = array_merge( $classes, (array) apply_filters( 'wffn_bundles_body_classes', array(), Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER, $dc ) );
				}
			}
		}
		return $classes;
	}

	/**
	 * The body flags one product's bundle needs, for a page that renders the
	 * widget somewhere other than a product page.
	 *
	 * Same gates body_class() applies, so a bundle that renders nothing stamps
	 * nothing. The caller decides which of them are safe on its page.
	 *
	 * @param int $product_id Product whose bundle to flag for.
	 * @return string[]
	 */
	public static function flags_for_product( $product_id ) {
		$bundle = Bundle_Store::active_for_product( absint( $product_id ) );
		if ( null === $bundle ) {
			return array();
		}
		$type = (int) ( $bundle['discountType'] ?? 0 );
		$dc   = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type && ! self::fb_members_complete( $dc ) ) {
			return array();
		}
		$product = wc_get_product( absint( $product_id ) );

		return self::bundle_flags( $type, $dc, $product instanceof \WC_Product ? $product : null );
	}

	/**
	 * The flag classes a rendered bundle needs.
	 *
	 * `wffn-bundles-type-*` lets CSS hide the native pickers only where the skin replaces
	 * them; `wffn-bundles-theme-*` is the slug Theme_Compat resolves its file by, so a
	 * compatibility sheet can scope its rules instead of raising weight. Also
	 * stamped on the widget root — see applyBodyFlags() in behavior/core.js.
	 *
	 * `wffn-bundles-owns-pickers` is what hides the page's native quantity and variation
	 * controls, kept apart from `wffn-bundles-has-bundle` so a page whose product forms are
	 * not all the widget's can drop that one hide and keep the bundle styling.
	 *
	 * @param int              $type    Bundle type being rendered.
	 * @param array            $dc      Saved discount config.
	 * @param \WC_Product|null $product Resolved product, when the caller has one.
	 * @return string[]
	 */
	private static function bundle_flags( $type, array $dc = array(), $product = null ) {
		$flags = array(
			'wffn-bundles-has-bundle',
			'wffn-bundles-owns-pickers',
			'wffn-bundles-type-' . sanitize_html_class( $type ),
			'wffn-bundles-theme-' . sanitize_html_class( Theme_Compat::slug() ),
		);

		/**
		 * Filters the flag classes stamped on the page body and the widget root
		 * for a rendered bundle.
		 *
		 * @since 1.0.0
		 *
		 * @param string[]         $flags   Flag classes.
		 * @param int              $type    Bundle type being rendered.
		 * @param array            $dc      Saved discount config.
		 * @param \WC_Product|null $product Resolved product; null on the body_class path.
		 */
		return (array) apply_filters( 'wffn_bundles_bundle_flags', $flags, $type, $dc, $product );
	}

	/**
	 * Is every configured Fixed Bundle member still purchasable?
	 *
	 * Mirrors Cart::members_fixed_bundle: an all-or-nothing pack with an
	 * unpurchasable member cannot be added, so the widget is suppressed.
	 * Unconfigured rows (no product picked) are not broken members.
	 *
	 * @param array $dc Saved discount config.
	 * @return bool
	 */
	private static function fb_members_complete( array $dc ) {
		foreach ( (array) ( $dc['fixedBundleItems'] ?? array() ) as $it ) {
			$member_id = (int) ( is_array( $it['product'] ?? null ) ? ( $it['product']['id'] ?? 0 ) : 0 );
			if ( ! $member_id ) {
				continue;
			}
			$member_product = function_exists( 'wc_get_product' ) ? wc_get_product( $member_id ) : null;
			// Trash stays purchasable for editors, but the cart refuses it outright.
			if ( ! $member_product || ! $member_product->is_purchasable() || 'trash' === $member_product->get_status() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Render the winning bundle for the current product: the saved type drives
	 * the skin, with the upsell and gift strips composed beneath it.
	 *
	 * @return void
	 */
	public function render() {
		// Prefer the global: a builder's add-to-cart widget can point at another product.
		$product = ( $GLOBALS['product'] ?? null ) instanceof \WC_Product ? $GLOBALS['product'] : wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}
		/**
		 * Filters whether the product-page hooks print a product's bundle widget.
		 *
		 * @param bool        $render  Whether to print the widget. Default true.
		 * @param \WC_Product $product Product the hook fired for.
		 */
		if ( ! apply_filters( 'wffn_bundles_render_product_widget', true, $product ) ) {
			return;
		}
		if ( $this->skips_current_hook( $product ) ) {
			return;
		}

		Markup::output( $this->render_product( $product ) );
	}

	/**
	 * Append the widget after a summary placement's anchor block on a block template.
	 *
	 * @param string         $content  Block markup.
	 * @param array          $block    Parsed block.
	 * @param \WP_Block|null $instance Block instance carrying the resolved context.
	 * @return string
	 */
	public function render_after_block( $content, $block, $instance = null ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! self::anchored_in_template() ) {
			return $content;
		}
		$post_id = (int) ( $instance instanceof \WP_Block ? ( $instance->context['postId'] ?? 0 ) : 0 );
		$page_id = (int) get_queried_object_id();
		// The same block also renders inside related and upsell product lists.
		if ( ( $post_id ? $post_id : (int) get_the_ID() ) !== $page_id ) {
			return $content;
		}
		$product = wc_get_product( $page_id );
		if ( ! $product ) {
			return $content;
		}

		return $content . $this->render_product( $product ) . $this->render_fbt_product( $product );
	}

	/**
	 * Has this product's widget already been rendered this request?
	 *
	 * @param int $product_id Product to check.
	 * @return bool
	 */
	public static function is_rendered( $product_id ) {
		return isset( self::$rendered[ (int) $product_id ] );
	}

	/**
	 * The widget markup for a resolved product, as a string.
	 *
	 * Everything from the bundle resolve down is a pure function of $product,
	 * so the compatibility layer can call it for a product the query never named.
	 * The paint guard must still precede the widget markup in the returned
	 * string for the pre-paint hide to work.
	 *
	 * @param \WC_Product $product Resolved product.
	 * @param array       $args    Reserved for callers in Compat\Render_Api.
	 * @return string
	 */
	public function render_product( $product, array $args = array() ) {
		return Discount_Layer::with_display_suppressed(
			function () use ( $product, $args ) {
				return $this->render_product_markup( $product, $args );
			}
		);
	}

	/**
	 * The widget markup, with any discount engine's price display already suspended.
	 *
	 * @param \WC_Product $product Resolved product.
	 * @param array       $args    Reserved for callers in Compat\Render_Api.
	 * @return string
	 */
	private function render_product_markup( $product, array $args = array() ) {
		$out = '';

		$bundle_config = $this->resolve( $product );
		if ( null === $bundle_config ) {
			return '';
		}

		$type   = (int) ( $bundle_config['discountType'] ?? 0 );
		$dc     = is_array( $bundle_config['discountConfig'] ?? null ) ? $bundle_config['discountConfig'] : array();
		$design = is_array( $dc['bundleDesign'] ?? null ) ? $dc['bundleDesign'] : array();

		$layout = $this->bundle_layout( $type, $dc );

		/**
		 * Whether to render add-on content: rows threaded into an offer and the
		 * layers drawn under the widget.
		 *
		 * A context that sells one offer on its own turns them off, or an add-on
		 * ticked there would be added without ever appearing as a row.
		 *
		 * @since 1.0.0
		 *
		 * @param bool        $render  Whether to render the add-on layers.
		 * @param \WC_Product $product Product the widget renders for.
		 * @param int         $type    Bundle type being rendered.
		 * @param array       $dc      Saved discount config.
		 */
		$addons = (bool) apply_filters( 'wffn_bundles_render_addons', true, $product, $type, $dc );

		$bundle = $this->bundle_input( $type, $dc, $design, $product, $args );
		if ( null === $bundle ) {
			return '';
		}
		/**
		 * Filters the assembled view-model input for the bundle widget.
		 *
		 * Runs after the type's own input is built and before the skin renders,
		 * so an add-on can carry extra keys into the view model.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $bundle  View-model input args.
		 * @param \WC_Product $product Resolved product.
		 * @param int         $type    Bundle type being rendered.
		 * @param array       $dc      Saved discount config.
		 * @param array       $context { layout, addons, bundle_id }: the layout drawn, whether
		 *                             add-on content renders, and the bundle this widget renders.
		 */
		$bundle = (array) apply_filters(
			'wffn_bundles_bundle_args',
			$bundle,
			$product,
			$type,
			$dc,
			array(
				'layout'      => $layout,
				'addons'      => $addons,
				'bundle_id' => (string) ( $bundle_config['id'] ?? '' ),
			)
		);

		// Claimed here, not at the top of render(), so the early bails above never
		// burn the slot for a later form on the same page. See self::$rendered.
		$product_key = (int) $product->get_id();
		if ( empty( $args['allow_repeat'] ) ) {
			if ( isset( self::$rendered[ $product_key ] ) ) {
				return '';
			}
			self::$rendered[ $product_key ] = true;
		}

		$out .= $this->enqueue( $type, $layout, true, $dc );
		Assets::enqueue_behavior( true, Assets::modules_for( $type, $dc ) );
		$bundle_id = (string) ( $bundle_config['id'] ?? '' );
		$scope       = self::scope_class( $bundle_id );
		$group       = self::group_suffix( $bundle_id, $product_key );
		$out .= $this->emit_design_vars( $design, $bundle_id, $dc );
		$this->localize_cart();
		$out .= $this->emit_variant_guard();
		// Before the widget's markup, so the guard is in effect for its first paint.
		$out .= $this->emit_paint_guard( $type, $bundle );
		$flags = self::bundle_flags( $type, $dc, $product );
		$out .= self::grouped( self::scoped( Registry::render( $type, $layout, $bundle ), $scope, $flags ), $group );

		// This plugin owns the enqueue and the scoping, so an add-on layer only
		// says what it wants rendered.
		if ( $addons ) {
			/**
			 * Filters the add-on layers drawn under the bundle widget.
			 *
			 * Each entry is array( 'skin' => string, 'layout' => string,
			 * 'args' => array ) and is rendered through the same registry every
			 * built-in type uses. Unknown skins are skipped.
			 *
			 * @param array $layers  Layers to render. Empty by default.
			 * @param array $context config, design, layout, product, type, bundle_id.
			 */
			$layers = (array) apply_filters(
				'wffn_bundles_addon_layers',
				array(),
				array(
					'config'      => $dc,
					'design'      => $design,
					'layout'      => $layout,
					'product'     => $product,
					'type'        => $type,
					'bundle_id' => $bundle_id,
				)
			);

			foreach ( $layers as $layer ) {
				$skin   = (string) ( $layer['skin'] ?? '' );
				$layout = (string) ( $layer['layout'] ?? '' );
				if ( '' === $skin || null === Registry::layout( $skin, $layout ) ) {
					continue;
				}
				$out .= $this->enqueue( $skin, $layout, true );
				$html = Registry::render( $skin, $layout, (array) ( $layer['args'] ?? array() ) );
				$out .= self::scoped( $html, $scope );
			}
		}

		/**
		 * A bundle widget was rendered. Builder-agnostic: a page-builder adapter
		 * uses it to know its compatibility assets are needed on this request.
		 *
		 * @param \WC_Product $product Product the widget rendered for.
		 * @param array       $args    Caller args (see Compat\Render_Api).
		 */
		do_action( 'wffn_bundles_bundle_rendered', $product, $args );

		return $out;
	}


	/**
	 * Build the bundle view-model input for a type, or null if unsupported.
	 *
	 * @param int         $type         Bundle type being rendered.
	 * @param array       $dc           Saved discount config.
	 * @param array       $design       Design tokens.
	 * @param \WC_Product $product      Resolved product.
	 * @param array       $args         Reserved for callers in Compat\Render_Api. A
	 *                                  `lock_offer` key restricts the widget to one
	 *                                  offer, e.g. 'fb-single'/'fb-bundle' (Fixed
	 *                                  Bundle) — the offer a row was pinned to when it
	 *                                  was added to a One-Click Upsell,
	 *                                  rather than the full picker every offer is
	 *                                  normally shown in.
	 * @return array|null
	 */
	private function bundle_input( $type, array $dc, array $design, $product, array $args = array() ) {
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			// All-or-nothing: an unpurchasable member makes the pack un-addable
			// server-side, so render nothing. Mirrors Cart::members_fixed_bundle.
			if ( ! self::fb_members_complete( $dc ) ) {
				return null;
			}
			// A member that cannot supply its quantity blocks the pack too
			// (Cart::validate_stock). Backorderable members stay available.
			$out_of_stock = false;
			$oos_names    = array();
			$items        = array();
			$image_size   = $design['image_size'] ?? '';
			foreach ( (array) ( $dc['fixedBundleItems'] ?? array() ) as $it ) {
				$member_id = (int) ( is_array( $it['product'] ?? null ) ? ( $it['product']['id'] ?? 0 ) : 0 );
				if ( ! $member_id ) {
					continue; // Unconfigured row (no product picked) — not a broken member.
				}
				$it['product'] = WooCommerce::with_image_size( $it['product'], $image_size );
				// The reason goes on the member's own row, so it is clear which product
				// blocks the pack. Backorderable members report available, no label.
				list( $member_ok, $member_oos ) = self::member_availability( $member_id, (int) ( $it['quantity'] ?? 1 ) );
				if ( ! $member_ok ) {
					$out_of_stock = true;
					$oos_names[]  = (string) ( $it['product']['name'] ?? '' );
				}
				$fb_variations           = is_array( $it['product']['variations'] ?? null ) ? $it['product']['variations'] : array();
				list( $fb_min, $fb_max ) = self::price_range( $it['product'] );
				// Expose a partial variation's open axes (e.g. "Any Size") as a picker.
				$fb_axes = Repository::partial_variation_axes( $member_id );
				$items[] = array(
					'name'          => (string) ( $it['product']['name'] ?? '' ),
					'productId'     => $member_id,
					'price'         => $fb_min,
					'priceMax'      => $fb_max,
					'image'         => (string) ( $it['product']['image'] ?? '' ),
					'category'      => (string) ( $it['product']['category'] ?? '' ),
					'quantity'      => (int) ( $it['quantity'] ?? 1 ),
					'discountType'  => (string) ( $it['discountType'] ?? 'percentage' ),
					'discountValue' => (float) ( $it['discountValue'] ?? 0 ),
					'type'          => (string) ( $it['product']['type'] ?? '' ),
					'variations'    => $fb_variations,
					'attributes'    => $fb_axes ? $fb_axes : ( $it['product']['attributes'] ?? array() ),
					'available'     => $member_ok,
					'oosLabel'      => $member_oos,
				);
			}
			// Both key off the page (anchor) product: the one the `single` offer adds
			// (Cart::quote_fixed_bundle) and the one the native form submits.
			$pid                             = self::anchor_id( $product );
			$anchor_product                  = WooCommerce::with_image_size( Repository::find( $pid ), $image_size );
			$anchor_product                  = is_array( $anchor_product ) ? $anchor_product : array();
			list( $anchor_min, $anchor_max ) = self::price_range( $anchor_product );
			$anchor                          = array(
				'name'       => (string) ( $anchor_product['name'] ?? '' ),
				'price'      => $anchor_min,
				'priceMax'   => $anchor_max,
				'image'      => (string) ( $anchor_product['image'] ?? '' ),
				'type'       => (string) ( $anchor_product['type'] ?? '' ),
				'variations' => is_array( $anchor_product['variations'] ?? null ) ? $anchor_product['variations'] : array(),
				'attributes' => is_array( $anchor_product['attributes'] ?? null ) ? $anchor_product['attributes'] : array(),
			);
			// A row pinned to one specific offer ('fb-single' or 'fb-bundle' — an
			// Upsell product added as just the anchor, or as the whole
			// pack) shows only that option, not the single-vs-bundle toggle.
			$lock_offer  = (string) ( $args['lock_offer'] ?? '' );
			$show_single = ! empty( $dc['fixedBundleShowSingle'] );
			$selected    = 'bundle';
			if ( 'fb-single' === $lock_offer ) {
				$show_single = true;
				$selected    = 'single';
			} elseif ( 'fb-bundle' === $lock_offer ) {
				$show_single = false;
			}
			return array(
				'heading'        => (string) ( $dc['bundleHeading'] ?? '' ),
				'subheading'     => (string) ( $dc['bundleSubheading'] ?? '' ),
				'title'          => Fixed_Bundle::default_text( $dc['fixedBundleTitle'] ?? '' ),
				'subtitle'       => Fixed_Bundle::default_text( $dc['fixedBundleSubtitle'] ?? '' ),
				'singleTitle'    => Fixed_Bundle::default_text( $dc['fixedBundleSingleTitle'] ?? '' ),
				'singleSubtitle' => Fixed_Bundle::default_text( $dc['fixedBundleSingleSubtitle'] ?? '' ),
				'showSingle'     => $show_single,
				'selected'       => $selected,
				'design'         => $design,
				'scope'          => (string) ( $dc['fixedBundleDiscountScope'] ?? 'whole_bundle' ),
				'mode'           => (string) ( $dc['fixedBundleDiscountType'] ?? 'percentage' ),
				'value'          => (float) ( $dc['fixedBundleDiscountValue'] ?? 0 ),
				'showImages'     => self::offer_images_on( $dc ),
				'singleImage'    => self::offer_image( $dc, $pid, 'fb-single', '' ),
				'bundleImage'    => self::offer_image( $dc, $pid, 'fb-bundle', $anchor['image'] ),
				'anchor'         => $anchor,
				'items'          => $items,
				'outOfStock'     => $out_of_stock,
				'oosNames'       => $oos_names,
			);
		}

		/**
		 * Filters the view-model input for a bundle type this plugin does not build.
		 *
		 * Answering with an array makes the type renderable: it is handed straight
		 * to the skin registered for it. Null leaves the widget unrendered.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null $bundle  View-model input.
		 * @param array      $context { type, config, design, product, args }; `args` carries
		 *                            the caller's `lock_offer`.
		 */
		return apply_filters(
			'wffn_bundles_bundle_input',
			null,
			array(
				'type'    => $type,
				'config'  => $dc,
				'design'  => $design,
				'product' => $product,
				'args'    => $args,
			)
		);
	}

	/**
	 * Saved bundle layout to the registered layout id for the type.
	 *
	 * @param int   $type Bundle type.
	 * @param array $dc   Saved discount config.
	 * @return string
	 */
	private function bundle_layout( $type, array $dc ) {
		if ( Bundle_Store::TYPE_FIXED_BUNDLES !== $type ) {
			/**
			 * Filters the registered layout id another type's saved layout maps to.
			 *
			 * @since 1.0.0
			 *
			 * @param string $layout Registered layout id.
			 * @param int    $type   Bundle type.
			 * @param array  $dc     Bundle discount config.
			 */
			return (string) apply_filters( 'wffn_bundles_bundle_layout', 'stacked', $type, $dc );
		}
		// Fixed Bundle is stacked-only: the selection machinery lives only in that
		// template, driven by assets/skins/behavior/fixed-bundle.js.
		return 'stacked';
	}

	/**
	 * Is this offer's thumbnail merchant artwork rather than the product's image?
	 *
	 * A live re-quote swaps a thumbnail to the chosen variation, which must never
	 * overwrite an image the merchant set for the offer itself — that artwork says
	 * "buy 3, save 20%", not "this is the product".
	 *
	 * @param array  $dc         Bundle design config.
	 * @param int    $product_id Product the widget is rendering on.
	 * @param string $offer_id   Offer id.
	 * @return bool
	 */
	public static function has_offer_image( array $dc, $product_id, $offer_id ) {
		return '' !== self::offer_image( $dc, $product_id, $offer_id, '' );
	}

	/**
	 * Whether a bundle's offers carry images of their own.
	 *
	 * @param array $dc Bundle discount config.
	 * @return bool
	 */
	public static function offer_images_on( array $dc ) {
		/**
		 * Filters whether a bundle's offers show an image each.
		 *
		 * Offer images are an add-on layer, so a bundle that carries the config
		 * still renders without them.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $on Whether offers show images. False by default.
		 * @param array $dc Bundle discount config.
		 */
		return (bool) apply_filters( 'wffn_bundles_offer_images', false, $dc );
	}

	/**
	 * The image an offer shows on a given product, or the caller's fallback.
	 *
	 * The single resolution point: the cart's container thumbnail comes through
	 * here too, so an offer is pictured the same way wherever it appears.
	 *
	 * @param array  $dc         Bundle discount config.
	 * @param int    $product_id Product the widget is rendering on.
	 * @param string $offer_id   Offer id.
	 * @param string $fallback   Image an offer that names none falls back to.
	 * @return string
	 */
	public static function offer_image( array $dc, $product_id, $offer_id, $fallback = '' ) {
		/**
		 * Filters the image an offer shows.
		 *
		 * Empty by default, and the fallback is passed rather than used: an offer
		 * only pictures itself once something is picturing offers, so a bundle
		 * without that renders no image at all rather than the skin's stand-in.
		 *
		 * @since 1.0.0
		 *
		 * @param string $image      Image URL. Empty by default.
		 * @param array  $dc         Bundle discount config.
		 * @param int    $product_id Product the widget is rendering on.
		 * @param string $offer_id   Offer id.
		 * @param string $fallback   Image an offer that names none falls back to.
		 */
		return (string) apply_filters( 'wffn_bundles_offer_image', '', $dc, $product_id, $offer_id, (string) $fallback );
	}

	/**
	 * Is a product id currently purchasable?
	 *
	 * Deliberately does not check stock: an out-of-stock member stays visible and
	 * is handled by the aggregated stock gate, as fb_members_complete() does.
	 *
	 * @param int $product_id Product to check.
	 * @return bool
	 */
	public static function purchasable( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$product = wc_get_product( $product_id );
		return $product && $product->is_purchasable() && 'trash' !== $product->get_status();
	}

	/**
	 * Units of a pool member the widget may let a shopper pick, or 0 for no
	 * client-side cap (unmanaged, backorderable, or variable — a variation's own
	 * stock is not known until one is chosen).
	 *
	 * Cart::validate_stock stays authoritative; this only stops the widget
	 * promising a box the add was always going to reject.
	 *
	 * @param int $product_id Pool member id.
	 * @return int Units available, or 0 for no cap.
	 */
	public static function stock_cap( $product_id ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variable' ) || ! $product->managing_stock() || $product->backorders_allowed() ) {
			return 0;
		}
		$left = (int) $product->get_stock_quantity();
		return $left > 0 ? $left : 0;
	}

	/**
	 * Can this product supply `$qty` right now?
	 *
	 * Mirrors Cart::validate_stock so the display never promises what the add
	 * path will reject. Backorderable members pass, so they render as ordinary
	 * in-stock items.
	 *
	 * @param int $product_id Product to check.
	 * @param int $qty        Units the bundle needs.
	 * @return bool
	 */
	public static function available( $product_id, $qty = 1 ) {
		$product_id = (int) $product_id;
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return false;
		}
		return $product->has_enough_stock( max( 1, (int) $qty ) );
	}

	/**
	 * Per-member availability for a Fixed Bundle row: the same gate as
	 * self::available, but returning why it failed so the row can name it.
	 *
	 * The unit count is shown only for stock-managed simple products, and only
	 * when the store is not hiding stock amounts. Backorderable members return
	 * available with no label.
	 *
	 * @param int $product_id Member product id.
	 * @param int $qty        Units the bundle needs.
	 * @return array{0:bool,1:string} [ available, label ]
	 */
	private static function member_availability( $product_id, $qty = 1 ) {
		$qty        = max( 1, (int) $qty );
		$product_id = (int) $product_id;
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return array( false, __( 'Out of stock', 'funnel-builder' ) );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return array( false, __( 'Out of stock', 'funnel-builder' ) );
		}
		// Cart::validate_stock refuses more than one of these, so the pack could never be added.
		if ( $qty > 1 && $product->is_sold_individually() ) {
			return array( false, __( 'Limit one per order', 'funnel-builder' ) );
		}
		if ( $product->has_enough_stock( $qty ) ) {
			return array( true, '' );
		}
		$label = __( 'Insufficient stock', 'funnel-builder' );
		if ( 'no_amount' !== get_option( 'woocommerce_stock_format' ) && ! $product->is_type( 'variable' ) ) {
			$left = (int) $product->get_stock_quantity();
			if ( $left > 0 ) {
				/* translators: %s: number of units left in stock */
				/* translators: %s: number of items left in stock. */
				$left_text = __( '(only %s left in stock)', 'funnel-builder' );
				$woo_left  = WooCommerce::translated_or_woo( $left_text, '(only %s left in stock)', 'Only %s left in stock' );
				$left_text = $woo_left !== $left_text ? '(' . $woo_left . ')' : $left_text;
				$label    .= ' ' . sprintf( $left_text, $left );
			}
		}
		return array( false, $label );
	}

	/**
	 * The dear end of the anchor's base-currency unit price.
	 *
	 * The variation-prices array a switcher converts with no raw equivalent, so
	 * the range goes through the layer. A simple product has no range, so its own
	 * base price is both ends.
	 *
	 * @param \WC_Product $product Page product.
	 * @return float
	 */
	public static function base_price_max( $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return Layer::get_base_price( $product );
		}
		list( , $max ) = Layer::get_base_variation_range( $product );
		return $max > 0 ? $max : Layer::get_base_price( $product );
	}


	/**
	 * The bundle-anchor product id for the current page (variation to parent).
	 *
	 * @param \WC_Product $product Page product.
	 * @return int
	 */
	public static function anchor_id( $product ) {
		return $product->is_type( 'variation' ) && $product->get_parent_id() > 0
			? $product->get_parent_id()
			: $product->get_id();
	}

	/**
	 * A hydrated product's base-currency price.
	 *
	 * The numeric field Repository::shape() carries, never the formatted `price`
	 * string: that is display currency under a switcher, and parsing it back
	 * misreads a comma-decimal locale.
	 *
	 * @param array $product Hydrated product row.
	 * @return float
	 */
	public static function price_of( $product ) {
		return (float) ( is_array( $product ) ? ( $product['priceRaw'] ?? 0 ) : 0 );
	}

	/**
	 * A hydrated product's [min, max] base-currency price. Variable products span
	 * their variations; simple products collapse to the same value twice.
	 *
	 * @param array $product Hydrated product row.
	 * @return array{0:float,1:float}
	 */
	private static function price_range( $product ) {
		$product = is_array( $product ) ? $product : array();
		$min     = self::price_of( $product );
		$max     = $min;
		$variations = is_array( $product['variations'] ?? null ) ? $product['variations'] : array();
		foreach ( $variations as $v ) {
			$vp = self::price_of( $v );
			if ( $vp <= 0 ) {
				continue;
			}
			$min = $min > 0 ? min( $min, $vp ) : $vp;
			$max = max( $max, $vp );
		}
		return array( $min, $max );
	}

	/**
	 * Enqueue a layout's stylesheets plus the shared selection behavior.
	 *
	 * The resolved list is filterable so a site can append a stylesheet without
	 * redefining the skin registry. Paths are plugin-root-relative, as in
	 * Registry::types(); one outside it is ignored, so a filter cannot point the
	 * enqueue at an arbitrary URL.
	 *
	 * @param string[] $css   Plugin-root-relative stylesheet paths.
	 * @param string   $type         Bundle type being rendered.
	 * @param string   $layout       Layout id being rendered.
	 * @param bool     $allow_inline Whether this caller may inline instead of enqueue.
	 * @param array|null $dc         The bundle's saved discount config, so an add-on can skip sheets
	 *                               this bundle never draws; null when the caller has no bundle.
	 * @return string Stylesheet markup, or '' when the queue carried them.
	 */
	private function enqueue( $type, $layout, $allow_inline = false, $dc = null ) {
		$cfg = Registry::layout( $type, $layout );
		$css = apply_filters( 'wffn_bundles_skin_styles', (array) ( $cfg['css'] ?? array() ), $type, $layout, $dc );

		// The queue is the only thing that can put a sheet in <head>, and it still
		// works on wp_enqueue_scripts, so only a render may inline.
		if ( ! $allow_inline ) {
			$this->enqueue_paths( $css );
			return '';
		}

		/**
		 * Carry the skin stylesheets in the markup instead of the style queue.
		 *
		 * A builder's editor can render into a document that never prints the queue,
		 * leaving the widget with its markup and none of its CSS. Adapters raise this.
		 *
		 * @param bool   $inline Whether to inline.
		 * @param string $type   Bundle type.
		 * @param string $layout Skin layout.
		 */
		if ( apply_filters( 'wffn_bundles_inline_skin_css', false, $type, $layout ) ) {
			// Last: the theme sheets correct the skin sheets and must keep winning
			// on source order.
			return $this->inline_style_paths( array_merge( $css, Theme_Compat::styles() ) );
		}

		$this->enqueue_paths( $css );
		return '';
	}

	/**
	 * Plugin-root-relative stylesheets as `<link>` markup, in cascade order.
	 *
	 * Printed by the style API for a render that lands where the queue cannot. Same
	 * path validation as enqueue_paths(): anything escaping the plugin's asset
	 * tree or missing is skipped.
	 *
	 * @param string[] $paths Plugin-root-relative stylesheet paths.
	 * @return string
	 */
	private function inline_style_paths( array $paths ) {
		return Assets::print_style_paths( $paths );
	}

	/**
	 * Enqueue plugin-root-relative stylesheet paths, skipping anything that
	 * escapes the plugin's asset tree (`../`, an absolute URL) or does not exist.
	 *
	 * @param string[] $css Plugin-root-relative stylesheet paths.
	 */
	private function enqueue_paths( array $css ) {
		Assets::enqueue_style_paths( $css );
	}

	/**
	 * Enqueue the active theme's compatibility stylesheet, if it has one.
	 *
	 * Called last in enqueue_early() so it also wins on source order. Almost
	 * always a no-op — see Theme_Compat for when a theme gets a file.
	 *
	 * @return void
	 */
	private function enqueue_theme_compat() {
		$this->enqueue_paths( Theme_Compat::styles() );
	}

	/**
	 * Emit the design's `--wffn-bundles-*` custom properties.
	 *
	 * The `.wffn-bundles-design-*` block is what the widgets read, so two bundles on one
	 * page keep their own designs; the `:root` block, emitted for the first
	 * design only, covers anything outside a widget root. A third block carries
	 * the colours the merchant changed, above the per-theme files.
	 *
	 * @param array  $design      Design tokens.
	 * @param string $bundle_id Bundle the tokens belong to.
	 * @param array  $dc          Discount config, for the section design layers.
	 * @return string Inline style markup when the queue can no longer carry it, else ''.
	 */
	private function emit_design_vars( array $design, $bundle_id, array $dc = array() ) {
		// Before the early return below, so a bundle that sets nothing still
		// gets the store-wide colours. Ordering is by specificity.
		$this->emit_global_design_vars();

		$css   = Css_Vars::for_design( $design );
		$class = self::scope_class( $bundle_id );
		/**
		 * Filters the bundle-scoped design rules an add-on emits after the widget's own.
		 *
		 * @since 1.0.0
		 *
		 * @param string $rules Rules to append. Empty by default.
		 * @param string $class The bundle's scope class.
		 * @param array  $dc    Saved discount config.
		 */
		$extra = (string) apply_filters( 'wffn_bundles_design_rules', '', $class, $dc );
		// No CSS rule needs a '<'; one could close the style element.
		$extra = false === strpos( $extra, '<' ) ? $extra : '';
		if ( '' === $css && '' === $extra ) {
			return '';
		}
		if ( isset( $this->vars_emitted[ $class ] ) ) {
			return '';
		}
		$first                        = empty( $this->vars_emitted );
		$this->vars_emitted[ $class ] = true;

		// Colours the merchant actually moved, re-emitted above the theme layer.
		$active = Css_Vars::for_merchant_colors( $design );
		// A popup moved to <body> has no widget to inherit from; nothing broader gets the copy, or
		// another bundle on the page (a cart drawer's, say) would wear this one's design.
		$page   = Css_Vars::page_wide( $css );
		$rules  = '' === $css ? '' : ( $first && '' !== $page ? 'body > .wffn-bundles-portal{' . $page . '}' : '' ) . '.' . $class . '{' . $css . '}';
		$rules .= '' === $active ? '' : 'html body .wffn-bundles.' . $class . '{' . $active . '}';

		// After the merchant layer, so an add-on's section overrides outrank it.
		$rules .= $extra;

		// Past this point wp_add_inline_style() is a queue write nothing prints.
		// Same predicate shape as Compat\Render_Api::wffn_bundles_cart_fallback().
		$inline = wp_doing_ajax() || did_action( 'wp_print_footer_scripts' ) || wp_style_is( 'wffn-bundles-skin-vars', 'done' );

		/**
		 * Emit the design tokens inline in the markup instead of through the style queue.
		 *
		 * A builder's editor can render the layout on a request that never prints the
		 * queue at all, which the predicate above cannot see. Adapters raise this.
		 *
		 * @param bool $inline Whether to inline.
		 */
		if ( apply_filters( 'wffn_bundles_inline_design_vars', $inline ) ) {
			return Assets::inline_style_markup( 'wffn-bundles-skin-vars-' . $class, $rules );
		}

		if ( ! wp_style_is( 'wffn-bundles-skin-vars', 'registered' ) ) {
			wp_register_style( 'wffn-bundles-skin-vars', false, array(), WFFN_BUNDLES_VERSION );
		}
		wp_enqueue_style( 'wffn-bundles-skin-vars' );
		wp_add_inline_style( 'wffn-bundles-skin-vars', $rules );
		return '';
	}

	/**
	 * Emit the store-wide Settings colours, once per request.
	 *
	 * Scoped on a body class `body_class()` stamps, at a weight above every
	 * per-theme compatibility file.
	 */
	private function emit_global_design_vars() {
		if ( $this->global_vars_emitted ) {
			return;
		}

		/**
		 * Filters a store-wide stylesheet emitted once per request.
		 *
		 * Printed above the per-theme compatibility files, so an add-on's
		 * store-wide design outranks them. Empty by default.
		 *
		 * @since 1.0.0
		 *
		 * @param string $block A complete stylesheet block, or ''.
		 */
		$block = (string) apply_filters( 'wffn_bundles_global_style_block', '' );
		if ( '' === $block ) {
			return;
		}
		$this->global_vars_emitted = true;

		if ( ! wp_style_is( 'wffn-bundles-skin-vars', 'registered' ) ) {
			wp_register_style( 'wffn-bundles-skin-vars', false, array(), WFFN_BUNDLES_VERSION );
		}
		wp_enqueue_style( 'wffn-bundles-skin-vars' );
		wp_add_inline_style( 'wffn-bundles-skin-vars', $block );
	}

	/**
	 * The per-bundle class carrying that bundle's design tokens.
	 *
	 * @param string $bundle_id Bundle the tokens belong to.
	 * @return string
	 */
	private static function scope_class( $bundle_id ) {
		return 'wffn-bundles-design-' . substr( md5( (string) $bundle_id ), 0, 8 );
	}

	/**
	 * Stamp the scope class onto a rendered widget's root element.
	 *
	 * Every skin template opens with `class="wffn-bundles ` on its root and
	 * Registry::render() returns that root first, so the first match is the root.
	 * Done here rather than in the templates because their scopes feed the PHP/JS
	 * drift guard in bin/tests/skins/equivalence.test.mjs.
	 *
	 * @param string   $html  Rendered widget markup.
	 * @param string   $scope Scope class.
	 * @param string[] $flags Flag classes to stamp as a data attribute.
	 * @return string Markup with the class applied.
	 */
	private static function scoped( $html, $scope, array $flags = array() ) {
		$attr = $flags ? 'data-wffn-bundles-flags="' . esc_attr( implode( ' ', $flags ) ) . '" ' : '';
		return preg_replace( '/class="wffn-bundles /', $attr . 'class="wffn-bundles ' . $scope . ' ', (string) $html, 1 );
	}

	/**
	 * A stable, per-widget suffix for the radio-group names.
	 *
	 * Deterministic in the bundle and product, so two different products on one
	 * page never share a group; the per-request sequence covers the same product
	 * rendering twice, which an overridden caller can still reach.
	 *
	 * @param string $bundle_id Bundle being rendered.
	 * @param int    $product_id  Product being rendered.
	 * @return string
	 */
	private static function group_suffix( $bundle_id, $product_id ) {
		++self::$instance_seq;
		$suffix = substr( md5( (string) $bundle_id . '|' . (string) $product_id ), 0, 8 ) . '-' . self::$instance_seq;
		/**
		 * Whether this render is a fragment that lands on a page which may already hold this widget.
		 *
		 * The sequence above restarts with every request, so a fragment (the side cart's quick view) of a
		 * bundle the page also shows would reuse the page widget's group names, and picking in one would
		 * clear the other. A fragment gets a suffix of its own.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $fragment Whether the render is a fragment; true on an admin-ajax request.
		 */
		if ( apply_filters( 'wffn_bundles_fragment_render', function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			$suffix .= '-' . substr( md5( uniqid( '', true ) ), 0, 5 );
		}
		return $suffix;
	}

	/**
	 * Give this widget's radios their own radio group.
	 *
	 * The skin templates hardcode the group names, `wffn-bundles-` plus hyphens, and a radio
	 * group is document-wide, so a second widget on the page steals the checked
	 * state from the first. Rewritten here rather than bound in the templates because those
	 * scopes feed the drift guard in bin/tests/skins/equivalence.test.mjs; nothing
	 * reads these names, so the rewrite is invisible to the JS and CSS.
	 *
	 * @param string $html   Rendered widget markup.
	 * @param string $suffix Per-widget group suffix.
	 * @return string Markup with per-instance radio group names.
	 */
	private static function grouped( $html, $suffix ) {
		if ( '' === (string) $suffix ) {
			return (string) $html;
		}
		return preg_replace(
			'/name="(wffn-bundles-[a-z0-9-]+)"/',
			'name="$1-' . $suffix . '"',
			(string) $html
		);
	}

	/**
	 * Pre-paint guard: render the state the behavior layer is about to apply, so
	 * the shopper never sees the state it is about to replace.
	 *
	 * Both the collapse and the add-to-cart gate are progressive enhancement, so
	 * the guard is keyed to `.wffn-bundles-js`, a class only JavaScript sets: with no JS
	 * there is no marker and no guard. The marker script drops the class after a
	 * few seconds, so a behavior bundle that never arrives cannot leave offers
	 * hidden with no way to reach them.
	 *
	 * @param int   $type   Bundle type being rendered.
	 * @param array $bundle The view-model input being rendered.
	 * @return string Inline script plus style markup, or ''.
	 */
	private function emit_paint_guard( $type, array $bundle ) {
		if ( $this->paint_guard_emitted ) {
			return '';
		}
		$this->paint_guard_emitted = true;

		$rules = array();

		// Only the lists collapse.js actually collapses: at or below the limit it
		// returns early and there is nothing to guard.
		$lists = array();
		/**
		 * Filters the collapsible offer list each bundle type draws.
		 *
		 * Keyed by type, each entry is
		 * `[ list selector, item selector, item count, selected index ]`.
		 *
		 * @since 1.0.0
		 *
		 * @param array $lists  Type => list spec.
		 * @param array $bundle The view-model input being rendered.
		 */
		$lists = (array) apply_filters( 'wffn_bundles_collapse_lists', $lists, $bundle );
		if ( isset( $lists[ $type ] ) ) {
			list( $list_sel, $item_sel, $count, $selected ) = $lists[ $type ];
			// A bundle whose default offer is past the limit opens expanded, so
			// guarding it would hide rows the JS is about to show.
			if ( $count > self::OFFERS_LIMIT && $selected < self::OFFERS_LIMIT ) {
				$rules[] = ':root.wffn-bundles-js ' . $list_sel . ':not(.wffn-bundles-collapse-ready)>' . $item_sel
					. ':nth-child(n+' . ( self::OFFERS_LIMIT + 1 ) . '){display:none}';
			}
		}

		// Only where the gate really will block, and a fade rather than `disabled`
		// so a page whose JavaScript fails is still purchasable.
		if ( $this->starts_blocked( $type, $bundle ) ) {
			$rules[] = ':root.wffn-bundles-js body.wffn-bundles-has-bundle form.cart:not(.fkcart-drawer-content *) .single_add_to_cart_button:not(.wffn-bundles-gate-ready)'
				. '{opacity:.45;filter:grayscale(1)}';
		}

		if ( ! $rules ) {
			return '';
		}

		return wp_get_inline_script_tag(
			'document.documentElement.classList.add("wffn-bundles-js");'
			. 'setTimeout(function(){document.documentElement.classList.remove("wffn-bundles-js")},4000);',
			array( 'id' => 'wffn-bundles-paint-guard-js' )
		)
			. Assets::inline_style_markup( 'wffn-bundles-paint-guard', implode( '', $rules ) );
	}

	/**
	 * Will the add-to-cart gate block on first paint?
	 *
	 * A proxy, not a second copy of the gate: asking `Variants::picker()` — the
	 * call the view-models already make — answers "is a variation unresolved"
	 * without restating the gate's logic.
	 *
	 * @param int   $type   Bundle type being rendered.
	 * @param array $bundle The view-model input being rendered.
	 * @return bool
	 */
	private function starts_blocked( $type, array $bundle ) {
		/**
		 * Filters whether a type's widget starts with its add-to-cart blocked.
		 *
		 * A type whose selection is always incomplete at load answers true, so the
		 * paint guard is in place before its first paint.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $blocked Whether to block at load.
		 * @param int   $type    Bundle type.
		 * @param array $bundle  The view-model input being rendered.
		 */
		if ( (bool) apply_filters( 'wffn_bundles_starts_blocked', false, $type, $bundle ) ) {
			return true;
		}
		if ( ! empty( $bundle['outOfStock'] ) ) {
			return true;
		}

		$candidates = is_array( $bundle['items'] ?? null ) ? array_values( $bundle['items'] ) : array();
		foreach ( array( 'anchor', 'product' ) as $key ) {
			if ( is_array( $bundle[ $key ] ?? null ) ) {
				$candidates[] = $bundle[ $key ];
			}
		}
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			if ( self::has_unresolved_axis( Variants::picker( $candidate ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does this picker still need a choice?
	 *
	 * The test is "an axis whose selected option has no value", not "a picker
	 * exists": a fully defaulted member renders dropdowns and still satisfies the
	 * gate. Mirrors `F.axesComplete` in behavior/core.js.
	 *
	 * @param array $picker Picker view model from Variants::picker().
	 * @return bool
	 */
	private static function has_unresolved_axis( array $picker ) {
		if ( empty( $picker['variantShow'] ) ) {
			return false;
		}
		foreach ( (array) ( $picker['variantAxes'] ?? array() ) as $axis ) {
			foreach ( (array) ( $axis['options'] ?? array() ) as $option ) {
				if ( ! empty( $option['selected'] ) && '' === (string) ( $option['value'] ?? '' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Pre-paint guard for the quick-view popup: hide the inline dropdowns and
	 * stand a "Select options" chip in until variant-modal.js replaces it.
	 *
	 * Both rules stop matching the instant `.wffn-bundles-variant--modal` is added, so the
	 * JS keeps ownership of the upgraded state. Pickers under a `.wffn-bundles-portal` or
	 * `[data-wffn-bundles-prompt]` root are excluded: their script injects them with
	 * innerHTML and never re-attaches behavior, so they must keep their dropdowns.
	 *
	 * @return string Inline style markup, or ''.
	 */
	private function emit_variant_guard() {
		if ( $this->guard_emitted ) {
			return '';
		}
		$this->guard_emitted = true;

		$mode = Options_Display::mode();
		if ( Options_Display::MODE_INLINE === $mode ) {
			return '';
		}

		$auto = Options_Display::MODE_AUTO === $mode;
		$base = '.wffn-bundles .wffn-bundles-variant:not(.wffn-bundles-variant--modal):not(.wffn-bundles-portal *):not([data-wffn-bundles-prompt] *)';

		// The mode test mirrors wantsModal in behavior/variant-modal.js: auto takes
		// only pickers with three or more axes, outside a skin that keeps them inline.
		$pending = $base . ( $auto ? ':not([data-wffn-bundles-inline] *):has(> .wffn-bundles-variant-axis:nth-of-type(3))' : '' );

		// :has() tells a real picker from an empty wrapper; where it is unsupported
		// the hide rule still stands on its own in modal mode.
		$chip = $auto ? $pending : $base . ':has(> .wffn-bundles-variant-axis)';

		// _shared.css is itself a late asset, so the placeholder repeats its
		// literal defaults to look right pre-paint.
		// Shows the label the button will carry, so the swap does not change the text.
		$caret = 'url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2012%2012%22%3E%3Cpath%20d%3D%22M2.5%204.5L6%208l3.5-3.5%22%20stroke%3D%22%23353030%22%20stroke-width%3D%221.5%22%20fill%3D%22none%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E")';
		$css   = $pending . '>.wffn-bundles-variant-axis{display:none}'
			. $chip . '::after{content:"' . self::css_string( __( 'Select options', 'funnel-builder' ) ) . '";'
			. 'display:inline-flex;align-items:center;box-sizing:border-box;max-width:100%;min-height:26px;padding:2px 22px 2px 6px;'
			. 'font-size:11px;font-weight:400;line-height:1.3;white-space:normal;overflow-wrap:anywhere;text-align:start;'
			. 'color:var(--wffn-bundles-control-fg,#353030);background:var(--wffn-bundles-control-bg,#fff) ' . $caret . ' no-repeat right 6px center/10px;'
			. 'border:1px solid var(--wffn-bundles-control-border,#dedfea);border-radius:6px}'
			. $chip . '[data-wffn-bundles-vsummary]:not([data-wffn-bundles-vsummary=""])::after{content:attr(data-wffn-bundles-vsummary)}'
			. '[dir="rtl"] ' . $chip . '::after{padding:2px 6px 2px 22px;background-position:left 6px center}'
			. '@media (max-width:600px){' . $chip . '::after{display:block;width:100%;contain:inline-size;min-height:36px;font-size:16px;line-height:30px;'
			. 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;unicode-bidi:plaintext;padding:2px 26px 2px 8px;background-position:right 8px center}'
			. '[dir="rtl"] ' . $chip . '::after{padding:2px 8px 2px 26px;background-position:left 8px center}}';

		return Assets::inline_style_markup( 'wffn-bundles-variant-guard', $css );
	}

	/**
	 * Escape a translated label for use inside a double-quoted CSS string. `<` is
	 * encoded too: a style element is a raw-text element, so a translation containing
	 * "</style" would otherwise close it.
	 *
	 * @param string $text Translated label.
	 * @return string
	 */
	private static function css_string( $text ) {
		$text = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $text );
		// Six-digit form so no whitespace terminator is needed (and none is eaten).
		$text = str_replace( '<', '\\00003c', $text );
		return preg_replace( '/[\r\n]+/', ' ', $text );
	}

	/**
	 * Put `window.wffnBundlesCart` on the page even though no bundle rendered on it.
	 *
	 * An innerHTML-inserted `<script>` never executes, so a builder's AJAX render
	 * surface cannot carry the config and the host page must have it first.
	 * Routed through the singleton so the once-flag below is not bypassed.
	 *
	 * @return void
	 */
	public static function ensure_cart_config() {
		self::get_instance()->localize_cart();
	}

	/**
	 * Expose the admin-ajax endpoint and nonce so the behavior can add to cart.
	 *
	 * @return void
	 */
	private function localize_cart() {
		if ( $this->cart_localized ) {
			return;
		}
		$this->cart_localized = true;
		wp_localize_script( Assets::HANDLE, 'wffnBundlesCart', Controller::config() );
	}


	/**
	 * Winning bundle for a product (cached per request).
	 *
	 * @param \WC_Product $product Product to resolve for.
	 * @return array|null
	 */
	private function resolve( $product ) {
		$id = $product->is_type( 'variation' ) && $product->get_parent_id() > 0
			? $product->get_parent_id()
			: $product->get_id();

		if ( ! array_key_exists( $id, $this->cache ) ) {
			$this->cache[ $id ] = Bundle_Store::active_for_product( $id );
		}
		return $this->cache[ $id ];
	}
}
