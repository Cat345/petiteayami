<?php
/** Adapter — FunnelKit One Click Upsell (OCU) compatibility. */
namespace WFFN\Bundles\Includes\Compat\One_Click_Upsells;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Ajax\Controller;
use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Compat\Render_Api;
use WFFN\Bundles\Includes\Compat\Funnel_Builder\Adapter as Funnel_Builder_Adapter;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Install;
use WFFN\Bundles\Includes\Storage\Order_Attribution;
use WFFN\Bundles\Includes\Storage\Order_Data;
use WFFN\Bundles\Includes\Helpers\Markup;

/**
 * Renders a bundle's own storefront picker on an OCU offer page and, on
 * accept, writes matching order lines — the same job `includes/compat/
 * funnel-builder/` does for Aero Checkout, for a third surface: OCU orders never pass
 * through `WC()->cart`, so nothing here may assume one exists.
 *
 * Fixed Bundle (any member count) writes real order lines,
 * priced and metaed the same way `Order_Attribution::copy_item_meta()` already
 * metas a normal checkout line — a multi-member Fixed Bundle's picks arrive via
 * `wffn_bundles_selection`, kept in sync by assets/offer-bundle.js reading
 * window.wffnBundlesSkins.readAttributes(), the plugin's own public utility. A bundle
 * type this plugin does not build writes its own lines through
 * `wffn_bundles_ocu_bundle_lines`. No `\WFOCU_*` symbol is named at file scope, so a
 * deactivated OCU cannot fatal us.
 *
 * Nothing here needs dragging into the offer layout by hand. OCU's own
 * Accept widgets (Button and Link) already carry a `selected_product`
 * setting naming which offer product they post — the same setting used to
 * auto-inject the picker right before each one, via Elementor's generic
 * `elementor/widget/render_content` filter (the same extension point
 * `includes/compat/elementor/adapter.php` already uses for the product
 * page). An offer that repeats its whole CTA block gets the picker before
 * every repeat, matching one accept button to one picker instance.
 */
class Adapter implements Integration {

	/** OCU's two Accept widgets — the auto-injection anchor points. */
	const ACCEPT_WIDGETS = array( 'wfocu-accept-offer-button', 'wfocu-accept-offer-link' );

	/** OCU's own native WooCommerce variation dropdowns — redundant once our picker owns the same product. */
	const VARIATION_WIDGET = 'wfocu-variation-selector';

	/**
	 * The `product_meta_data` keys an offer search row can carry — the set
	 * stash_meta_on_add()/preserve_meta_on_save() move onto the offer's saved
	 * per-product settings, since a Fixed Bundle's "Bundle" option needs its
	 * anchor id too (Adapter::row_bundle() reads it for the full "X - Bundle"
	 * title), not just the bundle and offer id.
	 */
	const META_KEYS = array( Funnel_Builder_Adapter::BUNDLE_KEY, Funnel_Builder_Adapter::OFFER_KEY, Funnel_Builder_Adapter::ANCHOR_KEY );

	/** Group of the bundle last written, which its add-on lines join as in the cart. */
	private $group = '';

	/** Elementor widget being rendered, '' outside one. */
	private $elementor_widget = '';

	/** Order item ids written for accepted offers, keyed by order id. */
	private $written_lines = array();


	/** Is FunnelKit One Click Upsell loaded? */
	public function is_enable() {
		return class_exists( 'WFOCU_Core' );
	}

	public function init() {
		add_shortcode( 'wffn_bundles_offer_bundle_form', array( $this, 'render_shortcode' ) );
		add_filter( 'elementor/widget/render_content', array( $this, 'auto_inject' ), 20, 2 );
		// OCU's own templates and every other builder's Accept button fire this instead.
		add_action( 'wfocu_add_custom_html_above_accept_button', array( $this, 'print_picker' ), 10, 2 );
		// The picker renders in the body, after the head, so its styles are claimed first.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_offer_styles' ) );
		add_action( 'wfocu_header_print_in_head', array( $this, 'print_offer_styles' ) );
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'enter_elementor_widget' ) );
		// The editor canvas renders widgets without the frontend hook above.
		add_action( 'elementor/widget/before_render_content', array( $this, 'enter_elementor_widget' ) );
		add_action( 'elementor/frontend/widget/after_render', array( $this, 'leave_elementor_widget' ) );
		add_filter( 'woocommerce_product_get_image_id', array( $this, 'container_offer_image' ), 10, 2 );
		add_filter( 'wfocu_add_products_to_the_order', array( $this, 'add_bundle_to_order' ), 10, 2 );
		add_filter( 'wfocu_added_products_to_the_order', array( $this, 'report_written_lines' ), 10, 2 );
		// After the upsell's own package changes, so the amount checked is the one charged.
		add_filter( 'wfocu_upsell_package', array( $this, 'confirm_charge' ), 20 );
		// The offer's own "Add Product" dialog is a separate AJAX endpoint
		// (class-wfocu-ajax-controller.php) from Aero's checkout builder, wrapping
		// its results in this filter instead of wffn_woocommerce_json_search_found_products.
		add_filter( 'wfocu_woocommerce_json_search_found_products', array( new Funnel_Builder_Adapter(), 'append_offer_rows' ) );
		// An offer row picked from that search carries its bundle/offer id
		// as `products_meta_data` alongside the plain product id — their own save
		// handler (wfocu_add_product()) never reads it. Stashed onto the row's own
		// saved settings object instead (which survives to a reload), since
		// that is the only place anything here can still find it on a later GET.
		add_filter( 'wfocu_offer_add_product_fields', array( $this, 'stash_meta_on_add' ), 10, 3 );
		// Their own "Save Layout" call rebuilds that settings object from four
		// hardcoded keys, dropping ours — restore it from what was last saved.
		add_filter( 'wfocu_offer_save_product_fields', array( $this, 'preserve_meta_on_save' ), 10, 3 );
		// The row shown immediately after "Add": $posted_product already carries
		// what stash_meta_on_add() just attached, since that hook runs first.
		add_filter( 'wfocu_offer_product_schema', array( $this, 'describe_offer_row' ), 10, 3 );
		// The row shown on reopening the offer (a fresh GET carries no request body
		// to read from) — re-resolve from what was persisted instead.
		add_filter( 'wfocu_offer_product_details', array( $this, 'describe_offer_row_on_get' ), 10, 5 );
		// The admin filters above never fire on the public offer page — its name,
		// price and image are built separately (class-wfocu-offers.php:824-915),
		// reading the container product raw. This is that surface's equivalent.
		add_filter( 'wfocu_offer_product_data', array( $this, 'describe_front_row' ), 10, 5 );
		add_filter( 'wfocu_build_offer_product_before', array( $this, 'drop_bundle_discounts' ), 10, 3 );
		add_filter( 'wfocu_offer_setting', array( $this, 'one_bundle_per_row' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'hide_posted_fields' ) );
	}

	/**
	 * Drop the raw posted picker fields from an order line's customer-facing meta.
	 *
	 * A line the offer's own add path writes copies every posted field onto the
	 * item; the admin list already hides them, the storefront and emails do not.
	 *
	 * @param array $formatted Formatted meta entries keyed by meta id.
	 * @return array
	 */
	public function hide_posted_fields( $formatted ) {
		foreach ( (array) $formatted as $id => $meta ) {
			if ( isset( $meta->key ) && 0 === strpos( (string) $meta->key, 'wffn_bundles_' ) ) {
				unset( $formatted[ $id ] );
			}
		}
		return $formatted;
	}

	/**
	 * Carry a posted offer row's bundle/offer id onto the product's own
	 * saved settings object, the only place it can still be found on a reload.
	 *
	 * @param object $product_fields Settings object about to be saved for this product.
	 * @param int    $pid            Product id the settings belong to.
	 * @param array  $options        The whole posted "Add Product" payload.
	 * @return object
	 */
	public function stash_meta_on_add( $product_fields, $pid, $options ) {
		if ( ! is_object( $product_fields ) || empty( $options['products_meta_data'] ) || ! is_array( $options['products_meta_data'] ) ) {
			return $product_fields;
		}
		foreach ( $options['products_meta_data'] as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry[0], $entry[1] ) || absint( $entry[0] ) !== absint( $pid ) || ! is_array( $entry[1] ) ) {
				continue;
			}
			foreach ( self::META_KEYS as $key ) {
				if ( isset( $entry[1][ $key ] ) ) {
					$product_fields->{$key} = (string) $entry[1][ $key ];
				}
			}
			break;
		}

		return $product_fields;
	}

	/**
	 * Restore the bundle/offer/anchor id a "Save Layout" call would otherwise drop.
	 *
	 * @param object $fields              Settings object being rebuilt for this product.
	 * @param string $hash_key            The row's key in the offer's saved `fields`.
	 * @param object $existing_offer_meta The offer's settings before this save.
	 * @return object
	 */
	public function preserve_meta_on_save( $fields, $hash_key, $existing_offer_meta ) {
		if ( ! is_object( $existing_offer_meta ) || ! isset( $existing_offer_meta->fields->{$hash_key} ) ) {
			return $fields;
		}
		$existing = $existing_offer_meta->fields->{$hash_key};
		foreach ( self::META_KEYS as $key ) {
			if ( ! empty( $existing->{$key} ) ) {
				$fields->{$key} = (string) $existing->{$key};
			}
		}

		return $fields;
	}

	/**
	 * Reflect a resolved offer row's real name and price in the offer's
	 * saved product schema, shown right after "Add".
	 *
	 * `$posted_product` arrives as a plain array here, not the stdClass
	 * `stash_meta_on_add()` wrote to — their own unstrip_product_data()
	 * (class-wffn-rest-api-helpers.php) casts the whole row through `(array)`
	 * first, which keeps every property (ours included) but changes its shape.
	 *
	 * @param array       $product_details Row about to be saved and displayed.
	 * @param \WC_Product $product         Product the row resolves to.
	 * @param array|object $posted_product The row's settings, carrying whatever
	 *                                      stash_meta_on_add() attached.
	 * @return array
	 */
	public function describe_offer_row( $product_details, $product, $posted_product ) {
		if ( ! is_array( $product_details ) || ! $product instanceof \WC_Product ) {
			return $product_details;
		}

		$meta = self::meta_from( $posted_product );

		return self::lock_bundle_discount( self::apply_resolved_row( $product_details, $product, $meta ), $product, $meta );
	}

	/**
	 * Reflect a saved offer row's real name and price when the offer is
	 * reopened — a fresh GET, so re-resolved from persisted settings rather
	 * than anything the (empty) request body could carry.
	 *
	 * @param array       $product_details Row being built for display.
	 * @param \WC_Product $product         Product the row resolves to.
	 * @param int         $offer_id        The offer's post id.
	 * @param int         $funnel_id       Unused; required by the filter's own signature.
	 * @param string      $key             The row's key in the offer's saved `fields`.
	 * @return array
	 */
	public function describe_offer_row_on_get( $product_details, $product, $offer_id, $funnel_id, $key ) {
		if ( ! is_array( $product_details ) || ! $product instanceof \WC_Product ) {
			return $product_details;
		}
		$offer_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id );
		if ( ! $offer_meta || ! isset( $offer_meta->fields->{$key} ) ) {
			return $product_details;
		}

		$meta = self::meta_from( $offer_meta->fields->{$key} );
		$row  = self::lock_bundle_discount( self::apply_resolved_row( $product_details, $product, $meta ), $product, $meta );

		return Funnel_Builder_Adapter::container_status( $row, $product->get_id() );
	}

	/**
	 * Lock the discount of a row that sells a bundle: the bundle prices it.
	 *
	 * @param array       $product_details Row the editor receives.
	 * @param \WC_Product $product         Product the row sells.
	 * @param array       $meta            The row's META_KEYS.
	 * @return array
	 */
	private static function lock_bundle_discount( array $product_details, \WC_Product $product, array $meta ) {
		return null === self::offer_bundle( $product, $meta ) ? $product_details : Funnel_Builder_Adapter::locked( $product_details );
	}

	/**
	 * Sell one bundle per row whatever quantity was stored: the bundle's type decides its units.
	 *
	 * @param mixed $offer_data Offer settings as stored.
	 * @param int   $offer_id   Offer id.
	 * @return mixed
	 */
	public function one_bundle_per_row( $offer_data, $offer_id = 0 ) {
		unset( $offer_id );
		if ( ! is_object( $offer_data ) || empty( $offer_data->products ) || ! is_object( $offer_data->fields ?? null ) ) {
			return $offer_data;
		}
		foreach ( (array) $offer_data->products as $key => $product_id ) {
			$fields = $offer_data->fields->{$key} ?? null;
			if ( ! is_object( $fields ) || array() === self::meta_from( $fields ) ) {
				continue;
			}
			$product = wc_get_product( absint( $product_id ) );
			if ( $product instanceof \WC_Product && null !== self::offer_bundle( $product, self::meta_from( $fields ) ) ) {
				$fields->quantity = 1;
			}
		}

		return $offer_data;
	}

	/**
	 * Drop the offer's own discount from every row that sells a bundle, so the bundle's price is the one charged.
	 *
	 * @param \stdClass $offer_data Offer settings being built.
	 * @param int       $offer_id   Offer id.
	 * @param bool      $is_front   Whether the offer is built for the storefront.
	 * @return \stdClass
	 */
	public function drop_bundle_discounts( $offer_data, $offer_id, $is_front ) {
		if ( ! $is_front || ! is_object( $offer_data ) || empty( $offer_data->products ) ) {
			return $offer_data;
		}
		foreach ( (array) $offer_data->products as $key => $product_id ) {
			$product = wc_get_product( absint( $product_id ) );
			$fields  = $offer_data->fields->{$key} ?? null;
			if ( ! $product instanceof \WC_Product || ! is_object( $fields ) || null === self::offer_bundle( $product, self::meta_from( $fields ) ) ) {
				continue;
			}
			$fields->discount_amount = 0;
			foreach ( (array) ( $offer_data->variations->{$key} ?? array() ) as $variation ) {
				if ( is_object( $variation ) ) {
					$variation->discount_amount = 0;
				}
			}
		}

		return $offer_data;
	}

	/**
	 * The META_KEYS a settings object/array carries, if any — accepts either
	 * shape, since the same values arrive as a stdClass from a fresh save and
	 * as a plain array once cast through their own unstrip_product_data().
	 */
	private static function meta_from( $source ) {
		$arr  = is_object( $source ) ? (array) $source : ( is_array( $source ) ? $source : array() );
		$meta = array();
		foreach ( self::META_KEYS as $key ) {
			if ( ! empty( $arr[ $key ] ) ) {
				$meta[ $key ] = (string) $arr[ $key ];
			}
		}

		return $meta;
	}

	/**
	 * The bundle an offer row's own product carries, never one for the shared placeholder.
	 *
	 * A bundle targeting the placeholder's category would otherwise outrank the row's pinned one.
	 *
	 * @param int $product_id Product the row sells, or its parent.
	 * @return array|null
	 */
	private static function own_bundle( $product_id ) {
		$container = Cart::container_product();
		if ( $container instanceof \WC_Product && $container->get_id() === (int) $product_id ) {
			return null;
		}

		return Bundle_Store::active_for_product( $product_id );
	}

	/**
	 * Whether a row sells the shared bundle placeholder but no bundle a shopper could be given.
	 *
	 * @param \WC_Product $product Product the row sells.
	 * @param array       $meta    The row's META_KEYS.
	 * @return bool
	 */
	private static function sells_empty_container( \WC_Product $product, array $meta ) {
		$container = Cart::container_product();
		if ( ! $container instanceof \WC_Product || $container->get_id() !== $product->get_id() ) {
			return false;
		}
		return null === self::offer_bundle( $product, $meta );
	}

	/**
	 * The bundle an accepted row is written from, resolved as add_bundle_to_order() resolves it.
	 *
	 * @param \WC_Product $product Product the row sells.
	 * @param array       $meta    The row's META_KEYS.
	 * @return array|null
	 */
	private static function offer_bundle( \WC_Product $product, array $meta ) {
		$anchor_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$is_set    = Bundle_Store::FBT_OFFER === ( $meta[ Funnel_Builder_Adapter::OFFER_KEY ] ?? '' );
		$bundle    = $is_set ? Bundle_Store::active_for_product( $anchor_id, Bundle_Store::SLOT_FBT ) : self::own_bundle( $anchor_id );
		if ( ! $bundle && ! $is_set ) {
			$pinned_id = absint( $meta[ Funnel_Builder_Adapter::BUNDLE_KEY ] ?? 0 );
			$pinned    = $pinned_id ? Bundle_Store::get( $pinned_id ) : null;
			$bundle    = $pinned && Bundle_Store::is_active_now( $pinned ) ? $pinned : null;
		}
		return $bundle ? $bundle : null;
	}

	/** Apply a resolved offer row's name and price onto a display row. */
	private static function apply_resolved_row( array $product_details, \WC_Product $product, array $meta ) {
		if ( self::sells_empty_container( $product, $meta ) ) {
			$product_details['title'] = __( 'Bundle (no bundle selected, so customers skip this offer)', 'funnel-builder' );
			return $product_details;
		}
		if ( array() === $meta ) {
			return $product_details;
		}
		$resolved = Funnel_Builder_Adapter::resolve_meta_row( $meta, $product );
		if ( null === $resolved ) {
			return $product_details;
		}
		$product_details['title']         = $resolved['title'];
		$product_details['regular_price'] = (string) round( $resolved['regular'], 2 );
		if ( $resolved['final'] < $resolved['regular'] ) {
			$product_details['sale_price'] = (string) round( $resolved['final'], 2 );
			$product_details['is_on_sale'] = true;
		} else {
			$product_details['sale_price'] = 0;
			$product_details['is_on_sale'] = false;
		}
		if ( ! empty( $resolved['image'] ) ) {
			$product_details['product_image'] = $resolved['image'];
		}

		return $product_details;
	}

	/**
	 * Correct a container-backed bundle row's name, price and photo on the
	 * live offer page.
	 *
	 * `describe_offer_row()`/`describe_offer_row_on_get()` only fire inside the
	 * funnel-builder editor's own REST endpoint (class-wffn-rest-upsell-api-
	 * endpoint.php) — never on a public front request, which builds its own
	 * name/price/image straight off the container product
	 * (class-wfocu-offers.php:824-915). `$product_details` already carries
	 * `wffn_bundles_bundle_id`/`wffn_bundles_offer`/`wffn_bundles_anchor` as its own properties by the time
	 * this fires (the same loop that copies every other per-row field).
	 *
	 * @param \stdClass $product_details Row about to be shown on the front.
	 * @param mixed     $output          Unused; required by the filter's own signature.
	 * @param \stdClass $offer_data      Unused; required by the filter's own signature.
	 * @param bool      $is_front        False inside the admin editor's own preview call.
	 * @param string    $hash_key        Unused; required by the filter's own signature.
	 * @return \stdClass
	 */
	public function describe_front_row( $product_details, $output, $offer_data, $is_front, $hash_key ) {
		if ( ! $is_front || ! is_object( $product_details ) || ! ( $product_details->data ?? null ) instanceof \WC_Product ) {
			return $product_details;
		}
		$meta = self::meta_from( $product_details );
		if ( self::sells_empty_container( $product_details->data, $meta ) ) {
			// Accepting would add the bare placeholder at no charge, so the vendor skips the offer instead.
			$product_details->is_purchasable = false;
			$product_details->is_in_stock    = false;
			return $product_details;
		}
		if ( array() === $meta ) {
			return $product_details;
		}
		$resolved = Funnel_Builder_Adapter::resolve_meta_row( $meta, $product_details->data );
		if ( null === $resolved ) {
			return $product_details;
		}

		$product_details->name                    = $resolved['title'];
		$product_details->regular_price            = $resolved['regular'];
		$product_details->price                    = $resolved['final'];
		$product_details->price_raw                = $resolved['final'];
		$product_details->display_price            = wc_price( $resolved['final'] );
		// The resolved price is a shelf price: it holds tax exactly when the store enters prices with tax.
		$offer_order                               = function_exists( 'WFOCU_Core' ) && isset( \WFOCU_Core()->data ) ? \WFOCU_Core()->data->get_current_order() : null;
		list( $incl, $excl )                       = self::shelf_tax_split( (float) $resolved['final'], $product_details->data, $offer_order instanceof \WC_Order ? $offer_order : null );
		$product_details->sale_price_incl_tax      = $incl;
		$product_details->sale_price_raw_incl_tax  = $incl;
		$product_details->sale_price_excl_tax      = $excl;
		$product_details->sale_price_incl_tax_html = wc_price( $incl );
		$product_details->sale_price_excl_tax_html = wc_price( $excl );
		if ( ! empty( $resolved['image'] ) ) {
			$product_details->image = $resolved['image'];
		}
		// Only for the container: any other offer's own product already
		// has a real title and photo, and swapping its class for the plain
		// WC_Product_Simple proxy below would lose a variable product's own type.
		$container = Cart::container_product();
		if ( $container instanceof \WC_Product && $container->get_id() === $product_details->data->get_id() ) {
			$product_details->data = self::wrap_container_product( $product_details->data, $resolved );
		}

		return $product_details;
	}

	/**
	 * A product proxy standing in for the container everywhere the vendor
	 * reads a name/price straight off the row's own `->data`, with no filter
	 * of its own — `wfocu_product_title_output()` (class-wfocu-shortcodes.php)
	 * and the Elementor "Product Title" tag both call `get_title()` this way.
	 *
	 * @param \WC_Product $product  The container product.
	 * @param array       $resolved { title, regular, final } from resolve_meta_row().
	 * @return \WC_Product
	 */
	private static function wrap_container_product( \WC_Product $product, array $resolved ) {
		return new class( $product->get_id(), $resolved ) extends \WC_Product_Simple {
			/** @var array */
			private $resolved;

			public function __construct( $id, array $resolved ) {
				parent::__construct( $id );
				$this->resolved = $resolved;
			}

			public function get_title( $context = 'view' ) {
				return $this->resolved['title'];
			}

			public function get_name( $context = 'view' ) {
				return $this->resolved['title'];
			}

			public function get_price( $context = 'view' ) {
				return $this->resolved['final'];
			}

			public function get_regular_price( $context = 'view' ) {
				return $this->resolved['regular'];
			}

			public function get_image_id( $context = 'view' ) {
				return isset( $this->resolved['image_id'] ) ? $this->resolved['image_id'] : parent::get_image_id( $context );
			}
		};
	}

	/**
	 * @hooked over `elementor/widget/render_content`
	 *
	 * Two jobs on the same filter, both keyed off the widget's own
	 * `selected_product` setting so a multi-product offer stays correct per
	 * button:
	 *  - Stamp the picker in right before every OCU Accept widget whose
	 *    product has an active bundle — no manual placement.
	 *  - Suppress OCU's own native variation-selector widget for that same
	 *    product: our picker already carries a variant `<select>` per bundle
	 *    row (and per single-purchase pick), so the native Color/Size
	 *    dropdowns above it would just be a second, disconnected copy that
	 *    picks nothing the accepted order actually reads.
	 *  OCU's own static offer-price widget (computed once server-side at page
	 *  load) is left alone here: assets/offer-bundle.js hides
	 *  `.wfocu_price_wrapper[data-key]` and inserts a live sibling next to it.
	 *
	 * @param string               $content Widget's own rendered markup.
	 * @param \Elementor\Widget_Base $widget The widget that produced it.
	 * @return string
	 */
	public function auto_inject( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $content;
		}
		$name = $widget->get_name();

		if ( in_array( $name, self::ACCEPT_WIDGETS, true ) ) {
			$product_key = (string) $widget->get_settings( 'selected_product' );
			$picker      = $this->picker_markup( $product_key );
			return '' === $picker ? $content : $picker . $content;
		}

		if ( self::VARIATION_WIDGET === $name ) {
			$product_key = (string) $widget->get_settings( 'selected_product' );
			$product     = $this->offer_product( $product_key );
			if ( $product && ( Bundle_Store::active_for_product( $product->get_id() ) || Bundle_Store::FBT_OFFER === self::pinned_offer( $this->resolve_offer_key( $product_key ) ) ) ) {
				return '';
			}
		}

		return $content;
	}

	/**
	 * Print the picker above an Accept button that Elementor's filter never sees.
	 *
	 * OCU's own buy blocks and its Gutenberg, Divi, Oxygen and Bricks Accept buttons
	 * fire this; Elementor's does too, but auto_inject() already placed the picker
	 * there.
	 *
	 * @param int    $product_id  Offer product id.
	 * @param string $product_key Offer product key.
	 * @return void
	 */
	public function print_picker( $product_id, $product_key ) {
		if ( in_array( $this->elementor_widget, self::ACCEPT_WIDGETS, true ) ) {
			return;
		}
		// OCU's multi-product templates turn a buy button outside the product list into
		// a "scroll to the products" button by dropping this class; it sells nothing.
		if ( ! in_array( 'wfocu_upsell', (array) apply_filters( 'wfocu_buy_btn_classes', array( 'wfocu_upsell' ) ), true ) ) {
			return;
		}
		$picker = $this->picker_markup( (string) $product_key );
		if ( '' === $picker ) {
			return;
		}
		Markup::output( $picker );
		add_action( 'wfocu_footer_after_print_scripts', array( $this, 'print_picker_assets' ) );
	}

	/**
	 * Claim the stylesheets the offer's bundle pickers need while the head can still print them.
	 *
	 * Mirrors picker_markup()'s choice of bundle for each offer row. Without
	 * it the picker's own enqueues print late and its markup paints unstyled first.
	 *
	 * @return void
	 */
	public function enqueue_offer_styles() {
		if ( ! function_exists( 'WFOCU_Core' ) || ! isset( \WFOCU_Core()->template_loader->product_data->products ) ) {
			return;
		}
		$display = Product_Display::get_instance();
		$keys    = array();
		foreach ( array_keys( (array) \WFOCU_Core()->template_loader->product_data->products ) as $key ) {
			$product = $this->offer_product( (string) $key );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			if ( Bundle_Store::FBT_OFFER === self::pinned_offer( (string) $key ) ) {
				if ( null !== Bundle_Store::active_for_product( $product->get_id(), Bundle_Store::SLOT_FBT ) ) {
					$keys[] = (string) $key;
				}
				$display->enqueue_fbt_for_product( $product->get_id() );
				continue;
			}
			if ( ! self::own_bundle( $product->get_id() ) ) {
				$anchor = self::pinned_anchor( (string) $key );
				if ( ! $anchor instanceof \WC_Product || ! Bundle_Store::active_for_product( $anchor->get_id() ) ) {
					continue;
				}
				$product = $anchor;
			}
			$keys[] = (string) $key;
			$display->enqueue_for_product( $product->get_id() );
		}
		if ( $keys ) {
			$this->enqueue_selection_bridge();
			$this->hold_offer_prices( $keys );
		}
	}

	/**
	 * Keep the offer's own price out of sight until the bridge writes the bundle's.
	 *
	 * The server prints the row's plain product price; offer-bundle.js swaps in the
	 * picked bundle's a moment later, so the wrong figure flashed first. The reveal
	 * animation shows it anyway should the script never run.
	 *
	 * @param string[] $keys Offer product keys that carry a bundle picker.
	 * @return void
	 */
	private function hold_offer_prices( array $keys ) {
		static $held = false;
		if ( $held ) {
			return;
		}
		$held      = true;
		$selectors = array();
		foreach ( $keys as $key ) {
			$key = preg_replace( '/[^A-Za-z0-9_-]/', '', $key );
			foreach ( array( '.wfocu_price_wrapper', '.wfocu_variable_price_save_percentage', '.wfocu_variable_price_save_percentage_combo' ) as $class ) {
				$selectors[] = $class . '[data-key="' . $key . '"]:not(.wffn-bundles-price-settled)';
			}
		}
		wp_add_inline_style(
			'wffn-bundles-ocu-offer-bundle',
			implode( ',', $selectors ) . '{visibility:hidden;animation:wffn-bundles-price-reveal 0s 4s forwards}@keyframes wffn-bundles-price-reveal{to{visibility:visible}}'
		);
	}

	/**
	 * Print those stylesheets in the head of an OCU customizer template, which skips wp_head().
	 *
	 * @return void
	 */
	public function print_offer_styles() {
		if ( true === apply_filters( 'wfocu_allow_externals_on_customizer', false ) ) {
			return;
		}
		$this->enqueue_offer_styles();
		wp_print_styles(
			array_values(
				array_filter(
					wp_styles()->queue,
					static function ( $handle ) {
						return 0 === strpos( (string) $handle, 'wffn-bundles-' );
					}
				)
			)
		);
	}

	/**
	 * Print the picker's own styles and scripts on an OCU customizer template.
	 *
	 * Those templates print OCU's assets alone and skip wp_head()/wp_footer()
	 * unless wfocu_allow_externals_on_customizer is raised, so what the picker
	 * enqueued would otherwise never reach the page.
	 *
	 * @return void
	 */
	public function print_picker_assets() {
		if ( true === apply_filters( 'wfocu_allow_externals_on_customizer', false ) ) {
			return;
		}
		$own = static function ( $handle ) {
			return 0 === strpos( (string) $handle, 'wffn-bundles-' );
		};
		wp_print_styles( array_values( array_filter( wp_styles()->queue, $own ) ) );
		wp_print_scripts( array_values( array_filter( wp_scripts()->queue, $own ) ) );
	}

	/**
	 * Note which Elementor widget is rendering, so print_picker() can leave the
	 * Accept widgets to auto_inject().
	 *
	 * @param \Elementor\Element_Base $widget Widget about to render.
	 * @return void
	 */
	public function enter_elementor_widget( $widget ) {
		$this->elementor_widget = is_object( $widget ) && method_exists( $widget, 'get_name' ) ? (string) $widget->get_name() : '';
	}

	/**
	 * Clear the widget enter_elementor_widget() noted.
	 *
	 * @return void
	 */
	public function leave_elementor_widget() {
		$this->elementor_widget = '';
	}

	/**
	 * Give the container placeholder its anchor's picture on an offer page.
	 *
	 * OCU's customizer templates build their gallery from a fresh wc_get_product()
	 * of the row, so the proxy describe_front_row() swaps in never reaches it and
	 * the container, which has no image of its own, left the column blank.
	 *
	 * @param int|string  $image_id Image id WooCommerce resolved.
	 * @param \WC_Product $product  Product asked about.
	 * @return int|string
	 */
	public function container_offer_image( $image_id, $product ) {
		if ( ! empty( $image_id ) || ! $product instanceof \WC_Product || (int) get_option( Install::OPTION_CONTAINER_ID, 0 ) !== $product->get_id() || ! function_exists( 'WFOCU_Core' ) ) {
			return $image_id;
		}
		$products = \WFOCU_Core()->template_loader->product_data->products ?? array();
		foreach ( (array) $products as $key => $row ) {
			if ( isset( $row->id ) && (int) $row->id === $product->get_id() ) {
				$anchor = self::pinned_anchor( (string) $key );
				if ( $anchor instanceof \WC_Product ) {
					return $anchor->get_image_id();
				}
			}
		}

		return $image_id;
	}

	/**
	 * `[wffn_bundles_offer_bundle_form key="..."]` — a manual escape hatch for an offer
	 * author who wants the picker somewhere other than right before Accept
	 * (auto_inject() covers the default placement; this needs no widget of
	 * its own, just a Shortcode/Text Editor element).
	 *
	 * @param array $atts Shortcode attributes: `key` (offer product key).
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'key' => '' ), (array) $atts );
		return $this->picker_markup( (string) $atts['key'] );
	}

	/**
	 * The offer id ('fb-single'/'fb-bundle' for a Fixed Bundle) a row was pinned
	 * to when it was added to this offer, '' for a bare-product row (no offer
	 * pinned — the widget then shows every option, same as the product page).
	 *
	 * Read off the offer's own saved settings, keyed the same way
	 * stash_meta_on_add()/describe_offer_row_on_get() already read and write it
	 * (`product_data->fields->{$key}`) — this only ever renders a saved offer,
	 * never a fresh "Add Product" request, so there is no request body to read
	 * it from here.
	 *
	 * @param string $key Offer product key (the row's hash key, not a WC id).
	 * @return string
	 */
	private static function pinned_offer( $key ) {
		if ( '' === $key || ! function_exists( 'WFOCU_Core' ) ) {
			return '';
		}
		$fields = \WFOCU_Core()->template_loader->product_data->fields->{$key} ?? null;

		return ! empty( $fields->wffn_bundles_offer ) ? (string) $fields->wffn_bundles_offer : '';
	}

	/**
	 * The real product a container-backed row was pinned against, or null.
	 *
	 * Read the same way pinned_offer() reads `wffn_bundles_offer` — off the offer's own
	 * saved settings, keyed `product_data->fields->{$key}`.
	 *
	 * @param string $key Offer product key.
	 * @return \WC_Product|null
	 */
	private static function pinned_anchor( $key ) {
		if ( '' === $key || ! function_exists( 'WFOCU_Core' ) ) {
			return null;
		}
		$fields    = \WFOCU_Core()->template_loader->product_data->fields->{$key} ?? null;
		$anchor_id = ! empty( $fields->wffn_bundles_anchor ) ? absint( $fields->wffn_bundles_anchor ) : 0;

		return $anchor_id ? wc_get_product( $anchor_id ) : null;
	}

	/**
	 * The bundle a container-backed "whole bundle" row was pinned against
	 * when the merchant built this offer, or null.
	 *
	 * Read the same way pinned_anchor() reads `wffn_bundles_anchor` — off the offer's
	 * own saved settings, never off the accept request. Only an active
	 * bundle is returned, matching Bundle_Store::active_for_product()'s
	 * own gate for the anchor-resolved path this one falls back from.
	 *
	 * @param string $key Offer product key.
	 * @return array|null
	 */
	private static function pinned_bundle( $key ) {
		if ( '' === $key || ! function_exists( 'WFOCU_Core' ) ) {
			return null;
		}
		$fields      = \WFOCU_Core()->template_loader->product_data->fields->{$key} ?? null;
		$bundle_id = ! empty( $fields->wffn_bundles_bundle_id ) ? absint( $fields->wffn_bundles_bundle_id ) : 0;
		if ( ! $bundle_id ) {
			return null;
		}
		$bundle = Bundle_Store::get( $bundle_id );

		return $bundle && Bundle_Store::is_active_now( $bundle ) ? $bundle : null;
	}

	/**
	 * The picker markup for one offer product key, or '' when the product
	 * doesn't resolve or carries no active bundle.
	 *
	 * Mirrors WFOCU_Shortcodes::wfocu_variation_selector_form_output()'s
	 * key-to-product resolution.
	 *
	 * @param string $key Offer product key.
	 * @return string
	 */
	private function picker_markup( $key ) {
		$key     = $this->resolve_offer_key( $key );
		$product = $this->offer_product( $key );
		if ( ! $product ) {
			return '';
		}
		// A set is sold from its own row; the product's plain row stays the plain product.
		if ( Bundle_Store::FBT_OFFER === self::pinned_offer( $key ) ) {
			return $this->fbt_picker_markup( $key, $product );
		}
		$bundle = self::own_bundle( $product->get_id() );
		if ( ! $bundle ) {
			// A container-backed "whole bundle" row sells the shared placeholder,
			// which carries no bundle of its own — resolve one from the row's
			// own pinned anchor instead, the same product resolve_meta_row() (and
			// the anchor image fix above) already key off.
			$anchor = self::pinned_anchor( $key );
			if ( $anchor instanceof \WC_Product ) {
				$bundle = Bundle_Store::active_for_product( $anchor->get_id() );
				if ( $bundle ) {
					$product = $anchor;
				}
			}
		}
		if ( ! $bundle ) {
			return '';
		}

		// button_mode 'never': bare widget markup only. WFFN_Bundles' own standalone
		// <form> (with its own add-to-cart submit) must not appear here — OCU's
		// existing Accept button owns the submit, not WFFN_Bundles'.
		$html = Render_Api::widget_html(
			array(
				'product_id'   => $product->get_id(),
				'button_mode'  => 'never',
				'allow_repeat' => true,
				'lock_offer'   => self::pinned_offer( $key ),
			)
		);
		if ( '' === $html ) {
			return '';
		}
		$html = $this->pin_price_element( $html );
		// Not a product page, so the per-theme sheet was never queued early.
		Product_Display::get_instance()->enqueue_for_product( $product->get_id() );

		// button_mode 'never' skips Cart::form_fields(), so the bundle id (and
		// whatever the `wffn_bundles_form_fields` action Cart::form_fields() fires
		// adds) has to be printed here instead — calling the same public method the product page
		// calls, not reprinting a `wffn_bundles_bundle_id` field by hand.
		ob_start();
		Cart::get_instance()->form_fields( $product );
		$fields = ob_get_clean();

		return $this->offer_form( $key, $product, $fields, $html );
	}

	/**
	 * The picker markup for a product that offers a bought-together set, or ''.
	 *
	 * The set renders without its own button; Accept adds it.
	 *
	 * @param string      $key     Offer product key.
	 * @param \WC_Product $product The offer product.
	 * @return string
	 */
	private function fbt_picker_markup( $key, $product ) {
		$bundle = Bundle_Store::active_for_product( $product->get_id(), Bundle_Store::SLOT_FBT );
		if ( ! $bundle ) {
			return '';
		}
		$html = Product_Display::get_instance()->render_fbt_product(
			$product,
			array(
				'button'       => false,
				'allow_repeat' => true,
			)
		);
		if ( '' === $html ) {
			return '';
		}
		ob_start();
		printf( '<input type="hidden" name="wffn_bundles_bundle_id" value="%s" />', esc_attr( (string) ( $bundle['id'] ?? '' ) ) );
		/** This action is documented in includes/frontend/cart.php */
		do_action( 'wffn_bundles_form_fields', $bundle, $product );
		$fields = ob_get_clean();

		return $this->offer_form( $key, $product, $fields, $html );
	}

	/**
	 * Wrap a widget in the form OCU's Accept posts, with the fields it reads back.
	 *
	 * @param string      $key     Offer product key.
	 * @param \WC_Product $product The product the widget renders for.
	 * @param string      $fields  Hidden bundle fields.
	 * @param string      $html    The widget markup.
	 * @return string
	 */
	private function offer_form( $key, $product, $fields, $html ) {
		// A multi-member Fixed Bundle has no add-to-cart form to post through, so
		// its member picks travel in this field instead — kept in sync by
		// assets/offer-bundle.js, which reads them the same way
		// fixed-bundle.js's own collect() does (window.wffnBundlesSkins.readAttributes()
		// and window.wffnBundlesSkins.selectionExtras()).
		$selection_field = '<input type="hidden" name="wffn_bundles_selection" value="" />';
		// The widget's own add gate drives this stand-in; Accept is refused while it is disabled.
		$selection_field .= '<button type="button" class="single_add_to_cart_button wffn-bundles-ocu-gate" hidden tabindex="-1" aria-hidden="true"></button>';

		$this->enqueue_selection_bridge();

		// Everything above and the widget itself MUST sit inside a real
		// `form.cart[data-product_id]`: WFFN_Bundles' own JS —
		// core.js's own field lookups — locates
		// wffn_bundles_bundle_id via F.cartForm(widget), whose first and only
		// reliable check is `widget.closest('form.cart')`. Without this wrapper
		// it falls through to a page-wide `document.querySelector('form.cart')`
		// fallback that can resolve to an unrelated form elsewhere on the offer
		// page (e.g. a related-products Add To Cart widget), and the
		// shopper's pick then never reaches the field this adapter reads at
		// accept time. No submit button inside it: OCU's own Accept link/button
		// stays the only way this ever gets posted.
		//
		// data-wffn-bundles-offer-key: the same offer-product hash OCU's own
		// .wfocu_price_wrapper[data-key] carries — offer-bundle.js reads it to
		// hide/replace that element with a live price.
		//
		// class wfocu_attributes_selector_form + data-key: OCU's own accept
		// click handler (wfocu-public.min.js: bindAcceptButton) recognises this
		// exact class+data-key pair as a form to `.serialize()` directly into the
		// accepted item's itemsData — the one native extension point it offers
		// for arbitrary custom fields. Confirmed necessary, not just convenient:
		// for a SIMPLE (non-variable) anchor, OCU prints no hidden data form of
		// its own at all, so there is nothing else to piggyback wffn_bundles_bundle_id /
		// wffn_bundles_selection onto — without this class none of
		// them ever reach $_POST for that bundle.
		$row_fields   = \WFOCU_Core()->template_loader->product_data->fields->{$key} ?? null;
		$price_format = array(
			'decimals' => wc_get_price_decimals(),
			'decimal'  => wc_get_price_decimal_separator(),
			'thousand' => wc_get_price_thousand_separator(),
		);

		return sprintf(
			'<form class="cart wffn-bundles-ocu-form wfocu_attributes_selector_form" data-product_id="%d" data-wffn-bundles-offer-key="%s" data-key="%s" data-wffn-bundles-offer-qty="%d" data-wffn-bundles-price-format="%s">%s</form>',
			(int) $product->get_id(),
			esc_attr( $key ),
			esc_attr( $key ),
			max( 1, (int) ( $row_fields->quantity ?? 1 ) ),
			esc_attr( wp_json_encode( $price_format ) ),
			$fields . $selection_field . $html
		);
	}

	/**
	 * Give the widget a `[data-wffn-bundles-price]` element of its own.
	 *
	 * `F.liveQuote()`'s `findPriceEl()` (core.js) checks this attribute first,
	 * before falling back to theme price selectors (`p.price`,
	 * `.woocommerce-variation-price`, …) that simply don't exist on an OCU
	 * offer page — so without this, `findPriceEl()` returns null and
	 * `F.liveQuote()` silently no-ops *entirely* (confirmed live: the
	 * `wffn_bundles_quote` AJAX call never fires at all on this template). That one
	 * silent no-op was the real cause of everything downstream looking
	 * broken: the widget's own price never collapsing from a range to an
	 * exact figure once every variant is picked. The element itself only needs to exist, inside the
	 * widget, hidden — the visible sync (`.wffn-bundles-fb-price-final`, this file's
	 * own OCU header mirror) already happens elsewhere once the quote runs.
	 *
	 * @param string $html Rendered widget markup.
	 * @return string
	 */
	private function pin_price_element( $html ) {
		if ( false !== strpos( $html, 'data-wffn-bundles-price' ) ) {
			return $html; // Already pinned (a future WFFN_Bundles version might add one).
		}
		// As the LAST child, not the first: inserted as the first child (right
		// after the widget root's own opening tag), this shifted the sibling
		// index of everything else inside — confirmed live to make the Fixed
		// Bundle single/bundle toggle silently revert back to the bundle a few
		// hundred ms after picking "single" (never reproduces on the real
		// product page, only here, and never reproduces with this element
		// removed entirely). Right before the root's own closing tag carries
		// none of that risk.
		$span = '<span data-wffn-bundles-price style="display:none"></span>';
		// Add-on and gift blocks follow the widget as sibling roots; the widget closes where the next one opens.
		if ( preg_match_all( '/<div\b[^>]*\sclass="wffn-bundles\s/', $html, $roots, PREG_OFFSET_CAPTURE ) && count( $roots[0] ) > 1 ) {
			$cut  = (int) $roots[0][1][1];
			$head = rtrim( substr( $html, 0, $cut ) );
			if ( '</div>' === substr( $head, -6 ) ) {
				return substr( $head, 0, -6 ) . $span . '</div>' . substr( $html, strlen( $head ) );
			}
		}
		$html = rtrim( $html );
		if ( '</div>' !== substr( $html, -6 ) ) {
			return $html; // Unexpected shape — skip pinning rather than guess where the root closes.
		}
		return substr( $html, 0, -6 ) . $span . '</div>';
	}

	/**
	 * An offer key, or — when empty — the offer's first product key, the
	 * same fallback Elementor_WFOCU_Variation_Selector_Widget's own control
	 * default resolves to (`key($product_options)`).
	 *
	 * @param string $key Offer product key, possibly empty.
	 * @return string
	 */
	private function resolve_offer_key( $key ) {
		if ( '' !== $key || ! function_exists( 'WFOCU_Core' ) ) {
			return $key;
		}
		$core = \WFOCU_Core();
		if ( ! isset( $core->template_loader->product_data->products ) ) {
			return $key;
		}
		return (string) array_key_first( (array) $core->template_loader->product_data->products );
	}

	/**
	 * The multi-member Fixed Bundle selection bridge — only needed where the
	 * picker itself rendered, so enqueued from render_shortcode() rather than
	 * unconditionally on every page.
	 */
	private function enqueue_selection_bridge() {
		$handle = 'wffn-bundles-ocu-offer-bundle';
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script(
			$handle,
			plugins_url( 'assets/offer-bundle.js', __FILE__ ),
			array( Assets::HANDLE ),
			Assets::ver( 'includes/compat/one-click-upsells/assets/offer-bundle.js' ),
			true
		);
		wp_enqueue_style(
			$handle,
			plugins_url( 'assets/offer-bundle.css', __FILE__ ),
			array(),
			Assets::ver( 'includes/compat/one-click-upsells/assets/offer-bundle.css' )
		);
		wp_localize_script(
			$handle,
			'wffnBundlesOcuOffer',
			array(
				'incomplete' => __( 'Complete your selection above to add it to your order.', 'funnel-builder' ),
			)
		);

		/**
		 * Fires once the offer-page selection bridge is enqueued.
		 *
		 * @since 1.0.0
		 *
		 * @param string $handle The bridge's script handle, for a dependent script.
		 */
		do_action( 'wffn_bundles_ocu_offer_scripts', $handle );
	}

	/**
	 * The offer product for a given offer key, or null.
	 *
	 * Same lookup Elementor_WFOCU_Variation_Selector_Widget::render() uses. An
	 * empty key falls back to the offer's first product — every OCU widget's
	 * own `selected_product` control defaults to `key($product_options)` (that
	 * same first product), but the default isn't always re-resolved on every
	 * render, so a widget can reach this with the setting genuinely unset.
	 *
	 * @param string $key Offer product key.
	 * @return \WC_Product|null
	 */
	private function offer_product( $key ) {
		if ( ! function_exists( 'WFOCU_Core' ) ) {
			return null;
		}
		$core = \WFOCU_Core();
		if ( ! isset( $core->template_loader->product_data->products ) ) {
			return null;
		}
		$products = $core->template_loader->product_data->products;
		if ( '' === $key ) {
			$key = (string) array_key_first( (array) $products );
		}
		if ( '' === $key || ! isset( $products->{$key} ) ) {
			return null;
		}
		$product = $products->{$key}->data;
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * @hooked over `wfocu_add_products_to_the_order`
	 *
	 * For every accepted-offer product resolving to an active WFFN_Bundles
	 * bundle, write the order line(s) ourselves — priced and metaed the way
	 * a normal checkout would — then remove it from $products so OCU's own
	 * add-product loop does not also add it at full price.
	 *
	 * @param array     $products The accepted-offer package, `['products' => [...], ...]`.
	 * @param \WC_Order $order    Order being built (the original order in
	 *                            `batching` mode, a new one in `create_order` mode).
	 * @return array
	 */
	public function add_bundle_to_order( $products, $order ) {
		if ( empty( $products['products'] ) || ! is_array( $products['products'] ) ) {
			return $products;
		}

		$posted = $this->posted_offer_fields();

		foreach ( $products['products'] as $key => $product_row ) {
			$before = array_keys( $order->get_items() );
			$bundle = $this->write_row( $key, $product_row, isset( $posted[ $key ] ) ? (array) $posted[ $key ] : array(), $order );

			if ( null !== $bundle ) {
				unset( $products['products'][ $key ] );
				$item_ids = array_values( array_diff( array_keys( $order->get_items() ), $before ) );

				$this->written_lines[ $order->get_id() ] = array_merge( $this->written_lines[ $order->get_id() ] ?? array(), $item_ids );

				/**
				 * Fires once the lines of an accepted offer's bundle are on the order.
				 *
				 * @since 1.0.0
				 *
				 * @param \WC_Order $order       The order the lines were written to.
				 * @param int[]     $item_ids    The order items written for this bundle.
				 * @param array     $bundle      The bundle.
				 * @param array     $product_row The accepted package row, posted fields included.
				 */
				do_action( 'wffn_bundles_ocu_bundle_written', $order, $item_ids, $bundle, $product_row );
			}
		}

		return $products;
	}

	/**
	 * Write one accepted package row's bundle lines to an order.
	 *
	 * @param int|string $key         The row's key in the package.
	 * @param array      $product_row The accepted package row.
	 * @param array      $fields      The row's posted offer fields.
	 * @param \WC_Order  $order       The order to write to.
	 * @return array|null The bundle written, or null when the row sells none.
	 */
	private function write_row( $key, array $product_row, array $fields, $order ) {
		$wc_product = $product_row['data'] ?? null;
		if ( ! $wc_product instanceof \WC_Product ) {
			return null;
		}
		// The accepted line can resolve to a specific VARIATION (e.g. the
		// shopper's own "Buy 1" pick) rather than the anchor itself —
		// bundles are only ever stored against the anchor/parent id.
		$anchor_id = $wc_product->get_parent_id() ? $wc_product->get_parent_id() : $wc_product->get_id();
		// Package rows are keyed by position; the saved settings by offer row hash.
		$hash   = isset( $product_row['hash'] ) ? (string) $product_row['hash'] : (string) $key;
		$fields = self::with_pinned_fields( $fields, $hash );
		$is_set = Bundle_Store::FBT_OFFER === self::pinned_offer( $hash );
		$bundle = $is_set ? Bundle_Store::active_for_product( $anchor_id, Bundle_Store::SLOT_FBT ) : self::own_bundle( $anchor_id );
		$anchor = wc_get_product( $anchor_id );
		if ( ! $bundle && ! $is_set ) {
			// A container-backed "whole bundle" row sells the shared
			// placeholder, which carries no bundle of its own — the same
			// gap picker_markup() falls back from, resolved here from the
			// row's own pinned bundle (the offer's saved settings, never
			// the accept request) so a paused, draft or ended bundle
			// cannot be pushed onto the order.
			$bundle = self::pinned_bundle( $hash );
			$anchor = self::pinned_anchor( $hash );
		}
		if ( ! $bundle ) {
			return null;
		}

		// One bundle per offer row, as the editor locks it; its type decides the units.
		$qty  = 1;
		$type = (int) ( $bundle['discountType'] ?? 0 );
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === $type ) {
			$written = $this->write_fixed_bundle_lines( $bundle, $qty, $fields, $order, $anchor, self::pinned_offer( $hash ) );
		} elseif ( Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			// A set row pins no anchor: the offer's own product hosts the set.
			$written = $this->write_fbt_lines( $bundle, $qty, $fields, $order, $anchor instanceof \WC_Product ? $anchor : wc_get_product( $anchor_id ) );
		} else {
			/**
			 * Filters whether an add-on wrote the lines for a bundle type this plugin does not build.
			 *
			 * @since 1.0.0
			 *
			 * @param bool      $written  False until something writes them.
			 * @param array     $bundle The bundle.
			 * @param int       $qty      Accepted offer quantity.
			 * @param array     $fields   Posted offer fields.
			 * @param \WC_Order   $order    The order being written.
			 * @param Adapter     $adapter  This adapter, for its line writers.
			 * @param \WC_Product $product  The accepted offer's product, possibly a variation.
			 */
			$written = (bool) apply_filters( 'wffn_bundles_ocu_bundle_lines', false, $bundle, $qty, $fields, $order, $this, $wc_product );
		}

		return $written ? $bundle : null;
	}

	/**
	 * Charge each accepted bundle row what its lines come to, never what the offer page sent.
	 *
	 * The page posts the amount it charges, so a page that misreads its own price,
	 * or a request edited on its way, would charge less than the lines written.
	 * Each row's lines are written to a stand-in order first by the same code that
	 * writes them for real, and the row is charged their total.
	 *
	 * Left as posted: a package carrying tax, since its tax cannot be recomputed
	 * here (noted on the order), and a row whose posted fields carry anything but
	 * product options and this plugin's own fields, since another add-on prices
	 * that row (a plan, say) after its lines are written.
	 *
	 * @param array $package The package about to be charged.
	 * @return array
	 */
	public function confirm_charge( $package ) {
		$order = function_exists( 'WFOCU_Core' ) && isset( \WFOCU_Core()->data ) ? \WFOCU_Core()->data->get_current_order() : null;
		if ( ! is_array( $package ) || empty( $package['products'] ) || ! is_array( $package['products'] ) || ! $order instanceof \WC_Order ) {
			return $package;
		}
		$posted   = $this->posted_offer_fields();
		$decimals = wc_get_price_decimals();
		$taxed    = (float) ( $package['taxes'] ?? 0 ) > 0;

		foreach ( $package['products'] as $key => $product_row ) {
			$fields = isset( $posted[ $key ] ) ? (array) $posted[ $key ] : array();
			if ( ! is_array( $product_row ) || self::priced_elsewhere( $fields ) ) {
				continue;
			}
			$probe = new Charge_Probe( $order );
			if ( null === $this->write_row( $key, $product_row, $fields, $probe ) ) {
				continue;
			}
			$expected = $probe->lines_total();
			$charged  = round( (float) ( $product_row['price'] ?? 0 ), $decimals );
			$delta    = round( $expected - $charged, $decimals );
			if ( 0.0 === (float) $delta ) {
				continue;
			}
			// A one-cent gap is the page rounding its shown price, not worth a note.
			$noted = abs( $delta ) > 1.5 / pow( 10, $decimals );
			$name  = $product_row['data'] instanceof \WC_Product ? $product_row['data']->get_name() : '';
			$from  = wc_price( $charged, array( 'currency' => $order->get_currency() ) );
			$to    = wc_price( $expected, array( 'currency' => $order->get_currency() ) );
			if ( $taxed ) {
				if ( $noted ) {
					/* translators: 1: offer product name, 2: amount the offer page sent, 3: what its bundle lines come to. */
					$order->add_order_note( sprintf( __( 'Upsell offer "%1$s" was charged %2$s, but its bundle lines come to %3$s. Check the charge.', 'funnel-builder' ), $name, $from, $to ) );
				}
				continue;
			}
			$package['products'][ $key ]['price']            = $expected;
			$package['products'][ $key ]['args']['total']    = $expected;
			$package['products'][ $key ]['args']['subtotal'] = $expected;
			$package['total'] = round( (float) ( $package['total'] ?? 0 ) + $delta, $decimals );
			if ( $noted ) {
				/* translators: 1: offer product name, 2: amount the offer page sent, 3: what its bundle lines come to. */
				$order->add_order_note( sprintf( __( 'Upsell offer "%1$s": the offer page sent %2$s, so the charge was set to its bundle lines, %3$s.', 'funnel-builder' ), $name, $from, $to ) );
			}
		}

		return $package;
	}

	/**
	 * Whether an accepted row's posted fields carry anything but product options and this plugin's own.
	 *
	 * @param array $fields The row's posted offer fields.
	 * @return bool
	 */
	private static function priced_elsewhere( array $fields ) {
		foreach ( array_keys( $fields ) as $name ) {
			$name = (string) $name;
			if ( 0 !== strpos( $name, 'attribute_' ) && 0 !== strpos( $name, 'wffn_bundles_' ) && 0 !== strpos( $name, 'wffn-bundles-' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add the bundle lines written for the accepted offer to the ids the upsell reports as added.
	 *
	 * The upsell refunds an offer by these ids, so without them its refund names no line.
	 *
	 * @param int[]     $ids   Order item ids the upsell added itself.
	 * @param \WC_Order $order The order the offer was added to.
	 * @return int[]
	 */
	public function report_written_lines( $ids, $order ) {
		$order_id = $order instanceof \WC_Order ? $order->get_id() : 0;
		if ( empty( $this->written_lines[ $order_id ] ) ) {
			return $ids;
		}
		$written = $this->written_lines[ $order_id ];
		unset( $this->written_lines[ $order_id ] );
		return array_merge( is_array( $ids ) ? $ids : array(), $written );
	}

	/**
	 * The posted offer fields, falling back to the offer the row was saved on.
	 *
	 * An offer page that renders no picker posts none, and the row still names it.
	 *
	 * @param array  $fields Posted offer fields for this row.
	 * @param string $key    Offer product key.
	 * @return array
	 */
	private static function with_pinned_fields( array $fields, $key ) {
		if ( array_key_exists( 'wffn_bundles_selection', $fields ) || '' === $key || ! function_exists( 'WFOCU_Core' ) ) {
			return $fields;
		}
		$offer = self::pinned_offer( $key );
		if ( '' === $offer || in_array( $offer, array( Funnel_Builder_Adapter::OFFER_BUNDLE, Funnel_Builder_Adapter::OFFER_SINGLE, Bundle_Store::FBT_OFFER ), true ) ) {
			return $fields;
		}

		/**
		 * Filters an offer row's posted fields with the offer it was saved on, for an offer this plugin does not sell itself.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $fields Posted offer fields for this row.
		 * @param string $offer  Offer id the row was saved on.
		 */
		return (array) apply_filters( 'wffn_bundles_ocu_pinned_fields', $fields, $offer );
	}

	/**
	 * What a member list sells for: each priced unit times its quantity.
	 *
	 * @param array[] $members Rows from Cart::fixed_bundle_members().
	 * @return float
	 */
	private static function members_total( array $members ) {
		$total = 0.0;
		foreach ( $members as $m ) {
			$total += (float) ( $m['pricedUnit'] ?? 0 ) * max( 1, (int) ( $m['qty'] ?? 1 ) );
		}
		return $total;
	}

	/**
	 * A Fixed Bundle, priced from the shopper's posted picks (`wffn_bundles_selection`,
	 * kept in sync by assets/offer-bundle.js) the same way Cart::ajax_add()
	 * prices it from a real add-to-cart request. A bundle with no per-member
	 * choice (e.g. every member simple) works with an empty selection too,
	 * since Cart::fixed_bundle_members() falls back to each member's default.
	 *
	 * Mirrors Cart::ajax_add()'s own branching (cart.php:1400-1505): 2+ members
	 * get a shared container line (the money) plus a zero-priced child line per
	 * member (read-only, for display/renewal), matching how a normal checkout
	 * would show the same bundle; exactly 1 member is written directly, no
	 * container needed.
	 *
	 * @param array            $bundle Resolved bundle.
	 * @param int              $qty      Accepted quantity.
	 * @param array            $fields   Posted offer fields.
	 * @param \WC_Order        $order    Order being built.
	 * @param \WC_Product|null $anchor   Product the row was pinned against.
	 * @param string           $pinned   The row's saved offer, '' when the shopper chooses.
	 * @return bool True when line(s) were written.
	 */
	private function write_fixed_bundle_lines( array $bundle, $qty, array $fields, $order, $anchor = null, $pinned = '' ) {
		$decoded = $this->decode_selection( $fields );
		// A row saved as the pack or the single is charged as that, whatever the request says.
		if ( Funnel_Builder_Adapter::OFFER_BUNDLE === $pinned ) {
			$decoded['single'] = false;
		} elseif ( Funnel_Builder_Adapter::OFFER_SINGLE === $pinned ) {
			$decoded['single'] = true;
		}

		if ( ! empty( $decoded['single'] ) ) {
			$written = $this->write_single_purchase_line( $bundle, $qty, $decoded, $fields, $order );
		} else {
			$members = Cart::fixed_bundle_members( $bundle, $decoded['items'] );
			// OCU charged the row priced on these members, so picks that price differently are not honoured.
			$charged = Funnel_Builder_Adapter::priced_pack_members( $bundle );
			if ( $charged && abs( self::members_total( $members ) - self::members_total( $charged ) ) > 0.005 ) {
				$members = $charged;
			}
			if ( empty( $members ) ) {
				return false;
			}
			$written = ( 1 === count( $members ) )
				? $this->write_single_member_line( $members[0], $bundle, $qty, $fields, $order, Bundle_Store::TYPE_FIXED_BUNDLES, 'fb-bundle' )
				: $this->write_grouped_bundle_lines( $members, $bundle, $qty, $fields, $order, Bundle_Store::TYPE_FIXED_BUNDLES, 'fb-bundle', $anchor );
		}
		if ( ! $written ) {
			return false;
		}

		// Ticked add-ons ride the bundle but are never part of its container total:
		// each is its own order line, priced and added separately.
		if ( ! empty( $decoded['extras'] ) ) {
			$this->write_addon_lines( self::addon_rows( $bundle, Bundle_Store::TYPE_FIXED_BUNDLES, 'fb-bundle', $decoded['extras'], $decoded['items'] ), $bundle, $fields, $order, Bundle_Store::TYPE_FIXED_BUNDLES, 'fb-bundle' );
		}
		return true;
	}

	/**
	 * A bought-together set: the offer product and each ticked companion as its
	 * own priced line, grouped as the cart groups them.
	 *
	 * @param array            $bundle The bundle.
	 * @param int              $qty      Accepted offer quantity.
	 * @param array            $fields   Posted offer fields.
	 * @param \WC_Order        $order    The order being written.
	 * @param \WC_Product|null $host     The offer product.
	 * @return bool True when the set was written.
	 */
	private function write_fbt_lines( array $bundle, $qty, array $fields, $order, $host ) {
		if ( ! $host instanceof \WC_Product ) {
			return false;
		}
		$decoded   = $this->decode_selection( $fields );
		$selection = array_merge(
			$decoded['extras'],
			array(
				'product'  => $host->get_id(),
				'quantity' => 1,
				'items'    => $decoded['items'],
			)
		);
		$dc    = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		// An offer page with no picker posts no ticks; the set then opens as its page does.
		$built = array_key_exists( 'wffn_bundles_selection', $fields )
			? Cart::members_fbt( $bundle, $dc, $selection )
			: array( 'code' => '', 'members' => Cart::fbt_set_members( $bundle, $host, null ) );
		if ( '' !== $built['code'] || empty( $built['members'] ) ) {
			return false;
		}
		$group   = $this->new_group();
		$title   = (string) ( $built['members'][0]['label'] ?? '' );
		$written = false;
		foreach ( $built['members'] as $member ) {
			$product = wc_get_product( $member['variation_id'] ? (int) $member['variation_id'] : (int) $member['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$unit_price = (float) $member['pricedUnit'];
			$item_id    = $order->add_product(
				$product,
				$qty,
				array(
					'subtotal' => self::booked( self::line_total( $unit_price, 1, $qty ), $product, $order ),
					'total'    => self::booked( self::line_total( $unit_price, 1, $qty ), $product, $order ),
				)
			);
			if ( ! $item_id ) {
				continue;
			}
			$item = $this->order_item( $order, $item_id );
			$this->meta_line(
				$item,
				$order,
				array(
					'bundle'     => (int) ( $bundle['id'] ?? 0 ),
					'type'         => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
					'group'        => $group,
					'role'         => (string) $member['role'],
					'offer'        => Bundle_Store::FBT_OFFER,
					'label'        => $title,
					'bundleTitle'  => $title,
					'baseQty'      => 1,
					'unit'         => (float) $member['unit'],
					'pricedUnit'   => $unit_price,
					'discountType' => Cart::offer_discount_type( $dc, Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER, Bundle_Store::FBT_OFFER ),
				)
			);
			$written = true;
		}
		return $written;
	}

	/**
	 * `wffn_bundles_selection`, decoded. `single` distinguishes a "Buy 1" pick (its own
	 * price, no bundle discount, no members) from a bundle pick with defaulted
	 * members — conflating the two would silently add the whole bundle when
	 * the shopper only bought the anchor. No field posted at all still tries
	 * with an empty bundle pick list, for a bundle whose members need no
	 * choice (Cart::fixed_bundle_members() itself resolves that case).
	 *
	 * `offer` is set by a type whose offer id travels inside `wffn_bundles_selection`
	 * itself; empty for every type that never sets it.
	 *
	 * `extras` holds every other posted key, for whatever answers the add-on seam.
	 *
	 * @return array{single: bool, attributes: array, variationId: int, items: array, extras: array, offer: string}
	 */
	public function decode_selection( array $fields ) {
		if ( ! array_key_exists( 'wffn_bundles_selection', $fields ) ) {
			return array(
				'single'      => false,
				'attributes'  => array(),
				'variationId' => 0,
				'items'       => array(),
				'extras'      => array(),
				'offer'       => '',
			);
		}
		// wp_unslash(): $fields came from raw $_POST via WFOCU_Offers::parse_posted_data(),
		// which never strips WordPress's own magic-quotes escaping — json_decode()
		// on the still-slashed string silently returns null and every pick is lost.
		$raw     = wp_unslash( (string) $fields['wffn_bundles_selection'] );
		$decoded = Controller::decode_json( $raw ) ?? array();
		return array(
			'single'      => ! empty( $decoded['single'] ),
			'attributes'  => is_array( $decoded['attributes'] ?? null ) ? $decoded['attributes'] : array(),
			'variationId' => absint( $decoded['variationId'] ?? 0 ),
			'items'       => is_array( $decoded['items'] ?? null ) ? $decoded['items'] : array(),
			'extras'      => array_diff_key( $decoded, array_flip( array( 'single', 'attributes', 'variationId', 'items', 'offer' ) ) ),
			'offer'       => (string) ( $decoded['offer'] ?? '' ),
		);
	}

	/**
	 * The "Buy 1" pick: the anchor itself at its own price, no bundle discount
	 * — mirrors Cart::ajax_add_single()'s pricing (cart.php:1587), reusing its
	 * same building blocks (Cart::resolve_variation(),
	 * Currency\Layer::raw_base_price()) rather than going through
	 * WC()->cart, which OCU orders never have.
	 */
	private function write_single_purchase_line( array $bundle, $qty, array $decoded, array $fields, $order ) {
		$anchor_id = (int) ( $bundle['productSelection']['products'][0]['id'] ?? 0 );
		$anchor    = $anchor_id ? wc_get_product( $anchor_id ) : null;
		if ( ! $anchor ) {
			return false;
		}

		$product = $anchor;
		if ( $anchor->is_type( 'variable' ) ) {
			$variation_id = (int) $decoded['variationId'];
			if ( ! $variation_id && ! empty( $decoded['attributes'] ) ) {
				list( $variation_id ) = Cart::resolve_variation( $anchor->get_id(), $decoded['attributes'] );
			}
			if ( ! $variation_id ) {
				return false; // No matching variation — nothing safe to price/add.
			}
			$product = wc_get_product( $variation_id );
			// A posted variation of any other product would be written at its own price.
			if ( ! $product || $anchor->get_id() !== $product->get_parent_id() ) {
				return false;
			}
		}

		$unit_price = (float) \WFFN\Bundles\Includes\Compat\Currency\Layer::raw_base_price( $product );
		$item_id    = $order->add_product(
			$product,
			$qty,
			array(
				'subtotal' => self::booked( self::line_total( $unit_price, 1, $qty ), $product, $order ),
				'total'    => self::booked( self::line_total( $unit_price, 1, $qty ), $product, $order ),
			)
		);
		if ( ! $item_id ) {
			return false;
		}
		$item = $this->order_item( $order, $item_id );
		$this->meta_line(
			$item,
			$order,
			array(
				'bundle'    => (int) ( $bundle['id'] ?? 0 ),
				'type'        => Bundle_Store::TYPE_FIXED_BUNDLES,
				'group'       => $this->new_group(),
				'role'        => 'single',
				'offer'       => 'fb-bundle',
				'label'       => $anchor->get_name(),
				'unit'        => $unit_price,
				'pricedUnit'  => $unit_price,
			)
		);
		return true;
	}

	/**
	 * Each priced add-on row as its own order line — the row's own role and
	 * price, no container/group of its own (mirrors Cart::ajax_add()'s
	 * `$addons` loop; an add-on is bundle-dependent to add, not bundle-priced).
	 */
	public function write_addon_lines( array $addon_members, array $bundle, array $fields, $order, $type, $offer ) {
		foreach ( $addon_members as $member ) {
			$member_id = $member['variation_id'] ? (int) $member['variation_id'] : (int) $member['product_id'];
			$product   = wc_get_product( $member_id );
			if ( ! $product ) {
				continue;
			}
			$member_qty = max( 1, (int) ( $member['qty'] ?? 1 ) );
			$unit_price = (float) ( $member['pricedUnit'] ?? 0 );
			$item_id    = $order->add_product(
				$product,
				$member_qty,
				array(
					'subtotal' => self::booked( self::line_total( $unit_price, $member_qty ), $product, $order ),
					'total'    => self::booked( self::line_total( $unit_price, $member_qty ), $product, $order ),
				)
			);
			if ( ! $item_id ) {
				continue;
			}
			$item = $this->order_item( $order, $item_id );
			$this->meta_line(
				$item,
				$order,
				array(
					'bundle'     => (int) ( $bundle['id'] ?? 0 ),
					'type'         => $type,
					'group'        => $this->group,
					'role'         => (string) $member['role'],
					'offer'        => (string) ( $member['offer'] ?? '' ),
					'label'        => (string) ( $member['label'] ?? '' ),
					'unit'         => (float) ( $member['unit'] ?? 0 ),
					'pricedUnit'   => $unit_price,
					'discountType' => (string) ( $member['discountType'] ?? Cart::offer_discount_type( $bundle['discountConfig'] ?? array(), (int) $type, $offer ) ),
				)
			);
		}
	}

	/**
	 * One member, no container — the plain single-bundle-of-one case, for
	 * any bundle type whose members share this same priced-row shape
	 * (Fixed Bundle, and any type an add-on writes through `wffn_bundles_ocu_bundle_lines`).
	 */
	public function write_single_member_line( array $member, array $bundle_config, $qty, array $fields, $order, $type, $offer, array $meta = array() ) {
		$member_id = $member['variation_id'] ? (int) $member['variation_id'] : (int) $member['product_id'];
		$product   = wc_get_product( $member_id );
		if ( ! $product ) {
			return false;
		}
		$member_qty = max( 1, (int) ( $member['qty'] ?? 1 ) ) * $qty;
		$unit_price = (float) ( $member['pricedUnit'] ?? 0 );

		$item_id = $order->add_product(
			$product,
			$member_qty,
			array(
				'subtotal' => self::booked( self::line_total( $unit_price, max( 1, (int) ( $member['qty'] ?? 1 ) ), $qty ), $product, $order ),
				'total'    => self::booked( self::line_total( $unit_price, max( 1, (int) ( $member['qty'] ?? 1 ) ), $qty ), $product, $order ),
			)
		);
		if ( ! $item_id ) {
			return false;
		}
		$item   = $this->order_item( $order, $item_id );
		$bundle = array(
			'bundle'     => (int) ( $bundle_config['id'] ?? 0 ),
			'type'         => $type,
			'group'        => $this->new_group(),
			'role'         => 'member',
			'offer'        => $offer,
			'label'        => (string) ( $member['label'] ?? '' ),
			'bundleTitle'  => (string) ( $member['label'] ?? '' ),
			'unit'         => (float) ( $member['unit'] ?? 0 ),
			'pricedUnit'   => $unit_price,
			'discountType' => (string) ( $member['discountType'] ?? Cart::offer_discount_type( $bundle_config['discountConfig'] ?? array(), (int) $type, $offer ) ),
		);
		// A null override drops the key.
		$bundle = array_filter(
			array_merge( $bundle, $meta ),
			static function ( $value ) {
				return null !== $value;
			}
		);
		$this->meta_line( $item, $order, $bundle );
		return true;
	}

	/**
	 * 2+ members — one container line carrying the money, one zero-priced
	 * child line per member. Mirrors Cart::ajax_add()'s own container+child
	 * construction (cart.php:1406-1504), for an order instead of a cart.
	 * Generic over bundle type for the same reason as write_single_member_line().
	 *
	 * @param \WC_Product|null $anchor Product the bundle is offered on; names an untitled Fixed Bundle.
	 */
	public function write_grouped_bundle_lines( array $members, array $bundle, $qty, array $fields, $order, $type, $offer, $anchor = null ) {
		$container = Cart::container_product();
		if ( ! $container ) {
			// Cart::ajax_add() fails the same way when the shared container
			// product is missing (re-run activation/upgrade) — nothing safe to
			// add here either.
			return false;
		}

		$total = 0.0;
		foreach ( $members as $m ) {
			$total += (float) ( $m['pricedUnit'] ?? 0 ) * max( 1, (int) ( $m['qty'] ?? 1 ) );
		}
		$total        = self::line_total( $total, 1, $qty );
		$group        = $this->new_group();
		$bundle_id  = (int) ( $bundle['id'] ?? 0 );
		$bundle_title = '' !== (string) ( $members[0]['label'] ?? '' ) ? (string) $members[0]['label'] : __( 'Bundle', 'funnel-builder' );
		$dc           = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( Bundle_Store::TYPE_FIXED_BUNDLES === (int) $type && '' === trim( (string) ( $dc['fixedBundleTitle'] ?? '' ) ) && $anchor instanceof \WC_Product ) {
			/* translators: %s: name of the product the bundle is offered on. */
			$bundle_title = sprintf( __( '%s - Bundle', 'funnel-builder' ), $anchor->get_name() );
		}

		$container_item_id = $order->add_product(
			$container,
			1,
			array(
				'subtotal' => self::booked( $total, $container, $order ),
				'total'    => self::booked( $total, $container, $order ),
			)
		);
		if ( ! $container_item_id ) {
			return false;
		}
		$container_item = $this->order_item( $order, $container_item_id );
		// The line sells the shared placeholder; name it after the bundle, as checkout does.
		if ( $container_item instanceof \WC_Order_Item_Product ) {
			$container_item->set_name( $bundle_title );
		}
		$this->meta_line(
			$container_item,
			$order,
			array(
				'bundle'     => $bundle_id,
				'type'         => $type,
				'group'        => $group,
				'role'         => 'container',
				'offer'        => $offer,
				'label'        => $bundle_title,
				'bundleTitle'  => $bundle_title,
				'unit'         => $total,
				'pricedUnit'   => $total,
				'discountType' => Cart::offer_discount_type( $dc, (int) $type, $offer ),
			)
		);

		foreach ( $members as $member ) {
			$member_id = $member['variation_id'] ? (int) $member['variation_id'] : (int) $member['product_id'];
			$product   = wc_get_product( $member_id );
			if ( ! $product ) {
				continue; // As Cart::ajax_add() does for an out-of-stock member: skip it rather than sink the whole accept.
			}
			$member_qty = max( 1, (int) ( $member['qty'] ?? 1 ) ) * $qty;
			$child_id   = $order->add_product(
				$product,
				$member_qty,
				array(
					'subtotal' => 0,
					'total'    => 0,
				)
			);
			if ( ! $child_id ) {
				continue;
			}
			$child_item = $this->order_item( $order, $child_id );
			$this->meta_line(
				$child_item,
				$order,
				array(
					'bundle'    => $bundle_id,
					'type'        => $type,
					'group'       => $group,
					'role'        => 'child',
					'offer'       => $offer,
					'label'       => (string) ( $member['label'] ?? '' ),
					'bundleTitle' => $bundle_title,
					'baseQty'     => max( 1, (int) ( $member['qty'] ?? 1 ) ),
					'unit'        => (float) ( $member['unit'] ?? 0 ),
					'pricedUnit'  => 0.0,
				)
			);
		}
		return true;
	}

	/**
	 * 2+ distinct picks as one offer: a zero-priced heading line naming the
	 * group, then each pick as its own priced line nested under it. The shape
	 * the cart gives a bundle split across lines, for an order.
	 *
	 * @param array            $members  Built member rows.
	 * @param array            $bundle The bundle.
	 * @param int              $qty      Accepted offer quantity.
	 * @param array            $fields   Posted offer fields.
	 * @param \WC_Order        $order    The order being written.
	 * @param int              $type     Bundle type.
	 * @param string           $offer    Offer id the lines sell.
	 * @param \WC_Product|null $anchor   Product the bundle is offered on.
	 * @return bool True when at least one pick was written.
	 */
	public function write_nested_lines( array $members, array $bundle, $qty, array $fields, $order, $type, $offer, $anchor = null ) {
		$container = Cart::container_product();
		if ( ! $container ) {
			return false;
		}
		$dc          = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$group       = $this->new_group();
		$bundle_id = (int) ( $bundle['id'] ?? 0 );
		$label       = '' !== (string) ( $members[0]['label'] ?? '' ) ? (string) $members[0]['label'] : __( 'Bundle', 'funnel-builder' );
		$offer_type  = Cart::offer_discount_type( $dc, (int) $type, $offer );
		$thumb       = Cart::heading_thumb( $dc, $anchor instanceof \WC_Product ? $anchor->get_id() : 0, $members, $offer );

		$heading_id = $order->add_product(
			$container,
			1,
			array(
				'subtotal' => 0,
				'total'    => 0,
			)
		);
		if ( ! $heading_id ) {
			return false;
		}
		$this->meta_line(
			$this->order_item( $order, $heading_id ),
			$order,
			array(
				'bundle'     => $bundle_id,
				'type'         => $type,
				'group'        => $group,
				'role'         => 'heading',
				'offer'        => $offer,
				'label'        => $label,
				'bundleTitle'  => sprintf(
					/* translators: 1: bundle type name, 2: box size label. */
					__( '%1$s: %2$s', 'funnel-builder' ),
					Bundle_Store::type_label( $type ),
					$label
				),
				'thumbSrc'     => (string) ( $thumb['src'] ?? '' ),
				'unit'         => 0.0,
				'pricedUnit'   => 0.0,
				'discountType' => $offer_type,
			)
		);

		$written = false;
		foreach ( $members as $member ) {
			$member_id = $member['variation_id'] ? (int) $member['variation_id'] : (int) $member['product_id'];
			$product   = wc_get_product( $member_id );
			if ( ! $product ) {
				continue;
			}
			$line_qty   = max( 1, (int) ( $member['qty'] ?? 1 ) ) * $qty;
			$unit_price = (float) ( $member['pricedUnit'] ?? 0 );
			$item_id    = $order->add_product(
				$product,
				$line_qty,
				array(
					'subtotal' => self::booked( self::line_total( $unit_price, max( 1, (int) ( $member['qty'] ?? 1 ) ), $qty ), $product, $order ),
					'total'    => self::booked( self::line_total( $unit_price, max( 1, (int) ( $member['qty'] ?? 1 ) ), $qty ), $product, $order ),
				)
			);
			if ( ! $item_id ) {
				continue;
			}
			$item = $this->order_item( $order, $item_id );
			$this->meta_line(
				$item,
				$order,
				array(
					'bundle'     => $bundle_id,
					'type'         => $type,
					'group'        => $group,
					'role'         => 'member',
					'offer'        => $offer,
					'label'        => (string) ( $member['label'] ?? '' ),
					'bundleTitle'  => $label,
					'baseQty'      => max( 1, (int) ( $member['qty'] ?? 1 ) ),
					'unit'         => (float) ( $member['unit'] ?? 0 ),
					'pricedUnit'   => $unit_price,
					'discountType' => (string) ( $member['discountType'] ?? $offer_type ),
					'nested'       => true,
				)
			);
			$written = true;
		}
		if ( ! $written ) {
			$order->remove_item( $heading_id );
		}
		return $written;
	}

	/**
	 * The just-added item, as the SAME object `$order->add_product()` cached
	 * on `$order` itself — not `$order->get_item($id)`, which fetches an
	 * independent instance. Modifying that independent instance persists to
	 * the database fine, but a later same-request `$order->get_items()`
	 * (WFOCU's own order-total/display code runs on the very order object
	 * this filter was handed) can still return the ORIGINAL, un-metaed
	 * instance for it — confirmed by direct testing: reliably wrong for the
	 * last item added in a >1-item sequence, right for the earlier ones.
	 * Pulling from `get_items()`'s own array sidesteps the whole question of
	 * why by using the one object every other order-reader also sees.
	 *
	 * @return \WC_Order_Item_Product|false
	 */
	private function order_item( $order, $item_id ) {
		$items = $order->get_items();
		return isset( $items[ $item_id ] ) ? $items[ $item_id ] : $order->get_item( $item_id );
	}

	/**
	 * Start a new bundle group and remember it for the bundle's add-on lines.
	 *
	 * @return string
	 */
	private function new_group() {
		$this->group = uniqid( 'wffn-bundles', false );
		return $this->group;
	}

	/**
	 * A shelf price as its including-tax and excluding-tax amounts, for the
	 * offer row. In a store that enters prices with tax the shown price is what
	 * the customer pays wherever they are, so the tax comes out at the rates the
	 * order applies; otherwise the tax is added at those rates. The excluding-tax
	 * amount keeps WooCommerce's rounding precision, since WFOCU taxes it.
	 *
	 * @param float          $shelf   Price as entered.
	 * @param \WC_Product    $product Product whose tax class applies.
	 * @param \WC_Order|null $order   The order the offer is for, when known.
	 * @return float[] { incl, excl }.
	 */
	private static function shelf_tax_split( $shelf, $product, $order = null ) {
		if ( ! wc_tax_enabled() || ! $product instanceof \WC_Product || ! $product->is_taxable() || $shelf <= 0 || self::vat_exempt( $order ) ) {
			return array( $shelf, $shelf );
		}
		$rates = self::line_tax_rates( $product, $order );
		$dp    = wc_get_rounding_precision();
		if ( wc_prices_include_tax() ) {
			return array( $shelf, round( $shelf - array_sum( \WC_Tax::calc_tax( $shelf, $rates, true ) ), $dp ) );
		}
		return array( round( $shelf + array_sum( \WC_Tax::calc_tax( $shelf, $rates, false ) ), wc_get_price_decimals() ), $shelf );
	}

	/**
	 * Whether the order is VAT exempt, as WooCommerce reads it when it calculates
	 * the order's taxes (the `is_vat_exempt` meta, through its filter). An exempt
	 * customer pays the price the offer shows, booked with no tax.
	 *
	 * @param \WC_Order|null $order The order.
	 * @return bool
	 */
	private static function vat_exempt( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		return (bool) apply_filters( 'woocommerce_order_is_vat_exempt', 'yes' === $order->get_meta( 'is_vat_exempt' ), $order );
	}

	/**
	 * The tax rates an order applies to a product's line: the order's own tax
	 * location (WooCommerce's reading of woocommerce_tax_based_on, local pickup
	 * and its location filter) and the product's tax class. Base rates when
	 * there is no order.
	 *
	 * @param \WC_Product    $product The line's product.
	 * @param \WC_Order|null $order   The order.
	 * @return array
	 */
	private static function line_tax_rates( $product, $order ) {
		$class = $product->get_tax_class( 'unfiltered' );
		if ( ! $order instanceof \WC_Order ) {
			return \WC_Tax::get_base_tax_rates( $class );
		}
		// The order's own location, read by WooCommerce exactly as calculate_taxes() reads it.
		$location = $order->get_taxable_location();

		return \WC_Tax::find_rates(
			array(
				'country'   => (string) ( $location['country'] ?? '' ),
				'state'     => (string) ( $location['state'] ?? '' ),
				'postcode'  => (string) ( $location['postcode'] ?? '' ),
				'city'      => (string) ( $location['city'] ?? '' ),
				'tax_class' => $class,
			)
		);
	}

	/**
	 * A line total as the order books it: excluding tax.
	 *
	 * Every price here is the shelf price the offer showed and charges, which
	 * in a store that enters prices including tax already holds the tax.
	 * WooCommerce books a line's subtotal and total excluding tax and adds the
	 * tax of the order's location on top, so that same tax is taken out first:
	 * booked + the tax the order then calculates = the shelf price, for a
	 * customer at the base location, in another taxed country or untaxed. The
	 * amount keeps WooCommerce's rounding precision, as a cart line does.
	 *
	 * @param float       $amount  Shelf amount, as the offer showed it.
	 * @param \WC_Product $product The line's product.
	 * @param \WC_Order   $order   The order.
	 * @return float
	 */
	private static function booked( $amount, $product, $order ) {
		$amount = (float) $amount;
		// The charge check measures what the offer charges: its shelf amounts.
		if ( $order instanceof Charge_Probe ) {
			return $amount;
		}
		$incl = $order instanceof \WC_Order ? $order->get_prices_include_tax() : wc_prices_include_tax();
		if ( $amount <= 0 || ! $incl || ! wc_tax_enabled() || ! $product instanceof \WC_Product || ! $product->is_taxable() || self::vat_exempt( $order ) ) {
			return $amount;
		}
		$tax = array_sum( \WC_Tax::calc_tax( $amount, self::line_tax_rates( $product, $order ), true ) );

		return round( $amount - $tax, wc_get_rounding_precision() );
	}

	/**
	 * A line's total at the store's price precision, as a cart line is stored.
	 *
	 * Rounded once per bundle before the offer quantity multiplies it, so two
	 * bundles cost exactly twice the one price the offer page shows.
	 *
	 * @param float $unit      Unit price.
	 * @param int   $qty       Units in one bundle.
	 * @param int   $offer_qty Bundles the accepted offer sells.
	 * @return float
	 */
	private static function line_total( $unit, $qty, $offer_qty = 1 ) {
		$decimals = wc_get_price_decimals();

		return round( round( (float) $unit * (int) $qty, $decimals ) * max( 1, (int) $offer_qty ), $decimals );
	}

	/**
	 * Write the `_wffn_bundles_*` order-item meta a checkout-built line would already
	 * carry. `Order_Attribution::copy_item_meta()` is written against
	 * `woocommerce_checkout_create_order_line_item`'s ($item, $key, $values,
	 * $order) shape, but reads only $item and $values — safe to call directly
	 * for a line that was never built from a cart item.
	 *
	 * @param \WC_Order_Item|false $item
	 * @param \WC_Order            $order
	 * @param array                $bundle `wffn_bundles_bundle`-shaped data.
	 */
	private function meta_line( $item, $order, array $bundle ) {
		if ( ! $item ) {
			return;
		}
		Order_Attribution::get_instance()->copy_item_meta( $item, '', array( 'wffn_bundles_bundle' => $bundle ), $order );
		$item->save();
		// The line was added to an order already saved, so its values reach the order meta here.
		Order_Data::flush( $order );
	}

	/**
	 * Every accepted item's posted form fields, keyed by offer product key.
	 *
	 * WFOCU_Offers::parse_posted_data() reads $_POST itself (no per-item
	 * lookup) and returns every accepted item's `data.attributes` map — the
	 * same map `_wfocu_variation` is excluded from, so our own `wffn_bundles_bundle_id`
	 * and other offer fields land there under their own names.
	 *
	 * @return array Offer key => field name => value.
	 */
	private function posted_offer_fields() {
		if ( ! function_exists( 'WFOCU_Core' ) || ! isset( \WFOCU_Core()->offers ) ) {
			return array();
		}
		$posted = \WFOCU_Core()->offers->parse_posted_data();
		$out    = array();
		foreach ( (array) $posted as $key => $row ) {
			$out[ $key ] = is_array( $row ) && isset( $row['data']['attributes'] ) ? (array) $row['data']['attributes'] : array();
		}
		return $out;
	}

	/**
	 * The add-on rows a posted selection resolves to.
	 *
	 * Goes through the add-on seam rather than resolving add-ons here, so the
	 * rows are built by whatever owns them and this stays a bundle adapter.
	 *
	 * @param array  $bundle Resolved bundle.
	 * @param int    $type     Bundle type.
	 * @param string $offer    Offer the bundle was bought at.
	 * @param array  $extras   Posted selection keys beyond the bundle's own picks.
	 * @param array  $items    The bundle's own picks; an unlock can depend on them.
	 * @return array[] Rows carrying an add-on role, empty when nothing answers.
	 */
	public static function addon_rows( array $bundle, $type, $offer, $extras, $items = array() ) {
		$selection = is_array( $extras ) ? $extras : array();
		if ( ! empty( $items ) && is_array( $items ) ) {
			$selection['items'] = $items;
		}
		$addons = (array) apply_filters(
			'wffn_bundles_cart_addons',
			array(),
			array(
				'bundle'  => $bundle,
				'type'      => (int) $type,
				'offer'     => $offer,
				'selection' => $selection,
			)
		);

		return array_values(
			array_filter(
				$addons,
				static function ( $row ) {
					return is_array( $row ) && in_array( (string) ( $row['role'] ?? '' ), Cart::addon_roles(), true );
				}
			)
		);
	}
}
