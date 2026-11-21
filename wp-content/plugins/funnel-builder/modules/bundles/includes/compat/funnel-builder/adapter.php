<?php
/** Adapter — FunnelKit Funnel Builder (Aero Checkout) compatibility. */
namespace WFFN\Bundles\Includes\Compat\Funnel_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WFFN\Bundles\Includes\Skins\Fixed_Bundle;
use WFFN\Bundles\Includes\Compat\Integration;
use WFFN\Bundles\Includes\Compat\Currency\Layer;
use WFFN\Bundles\Includes\Compat\Discount\Layer as Discount_Layer;
use WFFN\Bundles\Includes\Frontend\Cart;
use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Skins\Assets;
use WFFN\Bundles\Includes\Skins\Variants;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Ajax\Controller;
use WFFN\Bundles\Includes\Products\Repository;
use WFFN\Bundles\Includes\Helpers\Request_Context;
use WFFN\Bundles\Includes\Helpers\Markup;

/**
 * A bundle offer, sold as its own row on an Aero Checkout page.
 *
 * The checkout renders no bundle widget, so each offer is its own entry in the
 * product search and the row carries the offer id. Every row still resolves its
 * bundle at add-to-cart time.
 *
 * No `WFACP_*` symbol is named at file scope and nothing is registered outside
 * init(), so a deactivated Funnel Builder cannot fatal us.
 */
class Adapter implements Integration {

	/** Row meta key holding the chosen offer. */
	const OFFER_KEY = 'wffn_bundles_offer';

	/** Row meta key holding the bundle that offer belonged to when it was picked. */
	const BUNDLE_KEY = 'wffn_bundles_bundle_id';

	/** Row meta key holding the product a fixed-bundle row's bundle hangs off. */
	const ANCHOR_KEY = 'wffn_bundles_anchor';

	/** Row key the step editors' product table reads to lock a row's discount, holding the note it shows. */
	const LOCK_KEY = 'discount_locked';

	/**
	 * The offer a fixed-bundle row sells.
	 *
	 * A Fixed Bundle has two structural slots, allowlisted in Cart and described by
	 * Bundle_Store::offer_rows(); both are synthesised here.
	 */
	const OFFER_BUNDLE = 'fb-bundle';

	/** The pack's sibling slot: the target product bought on its own. */
	const OFFER_SINGLE = 'fb-single';

	/** Session key holding the member variations the row is currently built from. */
	const PICKS_KEY = 'wffn_bundles_aero_picks';

	/** Session key holding what an open quick view has quoted but not committed. */
	const PENDING_PICKS_KEY = 'wffn_bundles_aero_picks_pending';

	/** Body flag under which `_shared.css` hides every `form.cart`'s native pickers. */
	const NATIVE_PICKERS_FLAG = 'wffn-bundles-owns-pickers';

	/** A fixed bundle the add in flight is placing: { bundle, members, total, title, anchor }. */
	private static $pending_bundle = array();

	/** The companions a set row's own list ticked for the add in flight, or null for its opening ticks. */
	private static $pending_set_ticks = null;

	/** The checkout row whose set the quick view draws: { host, key }. */
	private static $qv_set = array();

	/** The bundle group the add in flight is building. */
	private static $pending_group = '';

	/** The switcher's Product Selection setting: '1', '2' or '3'. */
	private static $tray_mode = '2';

	/** Anchor whose bundle the quick view is showing, 0 when it is not a pack. */
	private static $qv_anchor = 0;

	/** Bundle the quick view's pack belongs to. */
	private static $qv_bundle = '';

	/** What the pack row currently rendering charges, 0 when the row is not one. */
	private static $row_pack_total = 0.0;

	/** The add-ons' share of $row_pack_total. */
	private static $row_pack_extras = 0.0;

	/** The anchor image a pack row borrows, and the product id it stands in for. */
	private static $row_anchor_img = 0;

	/** Product id of the switcher row currently rendering. */
	private static $row_product_id = 0;

	/** The variation the row currently rendering is already set to, or ''. */
	private static $row_choice = '';

	/** Whether the row currently rendering lists its own units, so its own single resolved variation would mislabel them. */
	private static $row_units_present = false;

	/** The removed pack Aero is naming next, as [ placeholder product id, bundle title ]. */
	private static $removed_pack = array( 0, '' );

	/**
	 * Always on: Funnel Builder loads an Aero Checkout before this module.
	 *
	 * @return bool
	 */
	public function is_enable() {
		return true;
	}

	/**
	 * Register this adapter's hooks. Only called when is_enable() is true.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wffn_woocommerce_json_search_found_products', array( $this, 'append_offer_rows' ) );
		add_filter( 'wffn_rest_api_checkout_add_product', array( $this, 'describe_row' ), 10, 2 );
		add_filter( 'wffn_rest_api_checkout_get_product', array( $this, 'describe_admin_row' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_script' ) );
		add_filter( 'wfacp_save_products', array( $this, 'backfill_row_variations' ) );
		add_filter( 'wfacp_save_products', array( $this, 'drop_bundle_discounts' ), 11 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'tag_line' ), 10, 4 );
		add_filter( 'wfacp_product_switcher_price_text', array( $this, 'switcher_price_html' ), 10, 4 );
		add_filter( 'wfacp_get_product_switcher_item_title', array( $this, 'switcher_row_title' ), 10, 6 );
		add_filter( 'wfacp_show_product_price', array( $this, 'own_pack_price' ), 10, 2 );
		add_filter( 'wfacp_show_item_price', array( $this, 'hide_heading_price' ), 10, 2 );
		add_action( 'wfacp_show_item_price_placeholder', array( $this, 'print_heading_price' ), 10, 2 );
		add_filter( 'wfacp_show_item_quantity', array( $this, 'hide_grouped_quantity' ), 10, 2 );
		add_filter( 'wfacp_enable_delete_item', array( $this, 'hide_nested_remove' ), 10, 3 );
		// The sidebar and collapsible summaries' own forms of the delete and quantity gates.
		add_filter( 'wfacp_mini_cart_enable_delete_item', array( $this, 'hide_nested_remove' ), 10, 3 );
		add_filter( 'wfacp_display_quantity_increment', array( $this, 'hide_grouped_quantity' ), 10, 2 );
		add_filter( 'wfacp_product_switcher_show_quantity_incrementer', array( $this, 'restore_pack_quantity' ), 110, 3 );
		// Fires before any of the row's markup, so the image below is already set.
		add_filter( 'wfacp_product_switcher_hide_row', array( $this, 'open_row_state' ), 10, 3 );
		add_filter( 'wfacp_product_switcher_price_data', array( $this, 'open_row_choice' ), 10, 4 );
		add_filter( 'wfacp_product_switcher_cart_price_data', array( $this, 'set_row_price_data' ), 10, 5 );
		add_filter( 'wfacp_choose_option_text', array( $this, 'name_row_choice' ) );
		add_filter( 'woocommerce_product_get_image_id', array( $this, 'pack_row_image' ), 10, 2 );
		add_filter( 'wfacp_cart_image', array( $this, 'cart_container_image' ), 10, 2 );
		add_filter( 'wffn_bundles_quote_response', array( $this, 'remember_row_picks' ), 5, 2 );
		add_action( 'wfacp_show_product_price_placeholder', array( $this, 'print_pack_price' ), 10, 2 );
		add_action( 'wfacp_before_quick_view_ajax', array( $this, 'open_quick_view' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'refresh_row_unit' ), 15 );
		add_action( 'wfacp_before_add_to_cart', array( $this, 'prepare_units' ) );
		add_filter( 'wfacp_switch_product_ajax_custom_data', array( $this, 'prepare_switch_units' ), 10, 4 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_remaining_units' ), 10, 6 );
		add_filter( 'wfacp_item_quantity', array( $this, 'row_group_quantity' ), 10, 2 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'drop_row_addons' ), 10, 2 );
		add_filter( 'wfacp_show_undo_message_for_item', array( $this, 'undo_pack_once' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_quick_view_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'hide_replaced_native_pickers' ), 20 );
		// After the core flags and any other integration's, so nothing re-adds it.
		add_filter( 'wffn_bundles_bundle_flags', array( $this, 'unflag_checkout_widget' ), 99 );
		add_action( 'wfacp_after_product_switcher_attributes', array( $this, 'render_pack_members' ), 14, 5 );
		add_action( 'wfacp_after_product_switcher_attributes', array( $this, 'render_set_members' ), 20, 5 );
		// Ahead of every row renderer: they gate on the mode this reads.
		add_action( 'wfacp_after_product_switcher_attributes', array( $this, 'read_selection_mode' ), 4, 5 );
	}

	/**
	 * Claim the skin assets Aero's quick-view modal cannot claim for itself.
	 *
	 * The modal injects AJAX markup, so the widget's own wp_enqueue_style() calls
	 * land in a queue that response never prints. Design tokens survive because
	 * emit_design_vars() inlines them under wp_doing_ajax(); the sheets do not.
	 *
	 * @return void
	 */
	public function enqueue_quick_view_assets() {
		if ( ! is_singular( 'wfacp_checkout' ) || ! self::checkout_draws_widget() ) {
			return;
		}
		$display = Product_Display::get_instance();
		$found   = false;
		foreach ( self::checkout_product_ids() as $id ) {
			$display->enqueue_for_product( $id );
			$display->enqueue_fbt_for_product( $id );
			$found = $found || null !== Bundle_Store::active_for_product( $id ) || null !== Bundle_Store::active_for_product( $id, Bundle_Store::SLOT_FBT );
		}
		if ( $found ) {
			// Localising happens inside the widget's own render, which here is an
			// AJAX response printing no script queue, so the host page carries it.
			Product_Display::ensure_cart_config();
		}
	}

	/**
	 * Re-read a checkout row's unit price from the variation it now sells.
	 *
	 * `wffn_bundles_bundle.unit` is stamped once at add time, and Aero swaps a row's
	 * variation in place without changing the cart key.
	 *
	 * Priority 15, between Aero's own pricing and Cart::apply_pricing() at 20. Read
	 * from a fresh product object: the line's own may already carry a bundle price,
	 * which would compound.
	 *
	 * @param \WC_Cart $cart Cart being calculated.
	 * @return void
	 */
	public function refresh_row_unit( $cart ) {
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! self::owns_line( $item ) || empty( $item['wffn_bundles_bundle'] ) ) {
				continue;
			}
			$variation = absint( $item['variation_id'] ?? 0 );
			if ( ! $variation ) {
				continue;
			}
			$product = wc_get_product( $variation );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$unit = Layer::raw_base_price( $product );
			if ( $unit > 0 ) {
				$cart->cart_contents[ $key ]['wffn_bundles_bundle']['unit'] = $unit;
			}
		}
	}

	/**
	 * Load the script that keeps a row's bundle picks in step with Aero's add.
	 *
	 * @return void
	 */
	public function enqueue_checkout_script() {
		if ( ! is_singular( 'wfacp_checkout' ) || ! self::checkout_sells_a_bundle() ) {
			return;
		}
		$widget = self::checkout_draws_widget();
		if ( ! $widget ) {
			// The sheet still styles the bundle's cart lines, and no skin sheet registers its dependency.
			Assets::enqueue_style_paths( array( 'assets/skins/_shared.css' ) );
		}
		foreach ( array( 'js', 'css' ) as $ext ) {
			$rel = 'assets/compat/funnel-builder.' . $ext;
			if ( ! file_exists( WFFN_BUNDLES_DIR . '/' . $rel ) ) {
				continue;
			}
			$mtime = filemtime( WFFN_BUNDLES_DIR . '/' . $rel );
			$ver   = (string) ( $mtime ? $mtime : WFFN_BUNDLES_VERSION );
			if ( 'js' === $ext ) {
				if ( ! $widget ) {
					continue;
				}
				wp_enqueue_script( 'wffn-bundles-compat-funnel-builder', WFFN_BUNDLES_URL . $rel, array(), $ver, true );
				wp_localize_script(
					'wffn-bundles-compat-funnel-builder',
					'wffnBundlesAeroAddons',
					array(
						// The wc-ajax endpoint rather than admin-ajax, to skip the admin
						// bootstrap. One URL per action: wc-ajax carries it in the query.
						'endpoints' => class_exists( '\\WC_AJAX' ) ? array(
							'pack'   => \WC_AJAX::get_endpoint( 'wffn_bundles_aero_pack' ),
							'set'    => \WC_AJAX::get_endpoint( 'wffn_bundles_aero_set' ),
							'cancel' => \WC_AJAX::get_endpoint( 'wffn_bundles_aero_cancel' ),
						) : array(),
					)
				);

				/**
				 * Fires once the Funnel Builder checkout script is enqueued.
				 *
				 * @since 1.0.0
				 *
				 * @param string $handle The script's handle, for a dependent script.
				 */
				do_action( 'wffn_bundles_funnel_builder_scripts', 'wffn-bundles-compat-funnel-builder' );
				continue;
			}
			// After the skin sheets, so these corrections win on order.
			wp_enqueue_style( 'wffn-bundles-compat-funnel-builder', WFFN_BUNDLES_URL . $rel, array( Assets::style_handle( 'assets/skins/_shared.css' ) ), $ver );

			/**
			 * Fires once the Funnel Builder compatibility sheet is enqueued.
			 *
			 * @since 1.0.0
			 *
			 * @param string $handle The sheet's style handle, for a dependent sheet.
			 */
			do_action( 'wffn_bundles_funnel_builder_styles', 'wffn-bundles-compat-funnel-builder' );
		}
	}

	/**
	 * Aero's posted payload, sanitized whole.
	 *
	 * Nonce verification is Aero's: WFACP_Ajax_Controller::check_nonce() runs
	 * before any of the hooks this adapter reads it from.
	 *
	 * @return array
	 */
	public static function posted_data() {
		$data = Request_Context::raw_post( 'data' );
		if ( ! is_array( $data ) ) {
			return array();
		}

		return (array) map_deep( $data, 'sanitize_text_field' );
	}

	/**
	 * Resolve what the posted row sells, before the add begins.
	 *
	 * Aero fires this inside addon_product() ahead of add_to_cart().
	 *
	 * @return void
	 */
	public function prepare_units() {
		self::plan_units( self::posted_data() );
	}

	/**
	 * Set up what a switched row sells.
	 *
	 * Aero's switch endpoint reaches `add_to_cart()` without raising
	 * `wfacp_before_add_to_cart`, so the preparation is driven from the switch's
	 * own filter instead. Returns its input untouched.
	 *
	 * @param array $custom_data Cart item data Aero is about to add with.
	 * @param mixed $removing    Cart item being replaced; unused.
	 * @param mixed $product     Product being added; unused.
	 * @param array $post        The switch's posted data.
	 * @return array
	 */
	public function prepare_switch_units( $custom_data, $removing = null, $product = null, $post = array() ) {
		self::plan_units( is_array( $post ) ? $post : array() );

		return $custom_data;
	}

	/**
	 * Work out what an add is really placing, from posted data.
	 *
	 * @param array $data The request's own data payload.
	 * @return void
	 */
	private static function plan_units( array $data ) {
		// The add and the switch both reach here, and both mean the shopper kept
		// what the modal was quoting.
		self::commit_picks();

		self::$pending_group     = '';
		self::$pending_bundle    = array();
		self::$pending_set_ticks = self::set_ticks_in( $data );

		self::prepare_bundle( self::stored_row( $data ) );
	}

	/**
	 * Resolve a fixed-bundle row into the pack it sells, before the add begins.
	 *
	 * @param array $row Stored row.
	 * @return bool Whether the add in flight is a fixed bundle.
	 */
	private static function prepare_bundle( array $row ) {
		if ( array() === $row ) {
			return false;
		}
		$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		if ( self::OFFER_BUNDLE !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
			return false;
		}
		$bundle = Bundle_Store::get_config( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) );
		if ( ! is_array( $bundle ) || Bundle_Store::TYPE_FIXED_BUNDLES !== (int) ( $bundle['discountType'] ?? 0 ) ) {
			return false;
		}
		// A paused, ended or deleted bundle cannot still price a checkout row;
		// mirrors the add path's own gate.
		if ( ! Bundle_Store::is_active_now( $bundle ) ) {
			return false;
		}
		$members = Cart::fixed_bundle_members( $bundle, self::fb_picks( $bundle ) );
		if ( empty( $members ) ) {
			return false;
		}
		$total = 0.0;
		foreach ( $members as $m ) {
			$total += (float) ( $m['pricedUnit'] ?? 0 ) * max( 1, (int) ( $m['qty'] ?? 1 ) );
		}
		$dc    = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$title = trim( Fixed_Bundle::default_text( $dc['fixedBundleTitle'] ?? '' ) );
		if ( '' === $title ) {
			$title = trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) );
		}
		self::$pending_group  = uniqid( 'wffn-bundles', false );
		self::$pending_bundle = array(
			'bundle' => (string) ( $bundle['id'] ?? '' ),
			'members'  => $members,
			'total'    => $total,
			'title'    => '' !== $title ? $title : (string) ( $members[0]['label'] ?? __( 'Bundle', 'funnel-builder' ) ),
			'anchor'   => absint( $meta[ self::ANCHOR_KEY ] ?? 0 ),
		);

		return true;
	}

	/**
	 * Resolve a set row into the set it sells, before its host line is added.
	 *
	 * @param array            $row   Stored row.
	 * @param \WC_Product|null $added The product or variation the row's line is added at.
	 * @return bool Whether the add in flight is a set.
	 */
	private static function prepare_set( array $row, $added ) {
		$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		if ( Bundle_Store::FBT_OFFER !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) || ! $added instanceof \WC_Product ) {
			return false;
		}
		$bundle = Bundle_Store::get_config( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) );
		if ( ! is_array( $bundle ) || Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER !== (int) ( $bundle['discountType'] ?? 0 ) || ! Bundle_Store::is_active_now( $bundle ) ) {
			return false;
		}
		$members = Cart::fbt_set_members( $bundle, $added, self::$pending_set_ticks );
		if ( count( $members ) < 2 ) {
			return false;
		}
		$dc   = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$host = array_shift( $members );
		$rule = \WFFN\Bundles\Includes\Skins\Frequently_Bought_Together::rule( $dc );
		self::$pending_group  = uniqid( 'wffn-bundles', false );
		self::$pending_bundle = array(
			'set'      => true,
			'bundle' => (string) ( $bundle['id'] ?? '' ),
			'offer'    => Bundle_Store::FBT_OFFER,
			'members'  => $members,
			'meta'     => array(
				'bundle'     => (string) ( $bundle['id'] ?? '' ),
				'type'         => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
				'group'        => self::$pending_group,
				'offer'        => Bundle_Store::FBT_OFFER,
				'anchor'       => (int) $host['product_id'],
				'label'        => (string) ( $host['label'] ?? '' ),
				'bundleTitle'  => (string) ( $host['label'] ?? '' ),
				'baseQty'      => 1,
				'discountType' => $rule['mode'],
				'fbtRule'      => $rule,
				'whole'        => true,
			),
			'host'     => $host,
		);

		return true;
	}

	/**
	 * The companions a set row's own list ticked, posted with Aero's add, or null.
	 *
	 * @param array $data The request's own data payload.
	 * @return array|null
	 */
	private static function set_ticks_in( array $data ) {
		$set = isset( $data['wffn_bundles_set'] ) ? Controller::decode_json( (string) $data['wffn_bundles_set'] ) : null;

		return is_array( $set ) && is_array( $set['items'] ?? null ) ? array( 'items' => $set['items'] ) : null;
	}

	/**
	 * The container datum for a fixed bundle placed from a checkout row.
	 *
	 * Mirrors Cart::add_bundle's own container: the pack's money sits here and
	 * every child line carries zero, so the cart shows one priced bundle.
	 *
	 * @return array
	 */
	private static function container_bundle_data() {
		$b        = self::$pending_bundle;
		$bundle = Bundle_Store::get_config( (string) $b['bundle'] );
		$dc       = ( is_array( $bundle ) && is_array( $bundle['discountConfig'] ?? null ) ) ? $bundle['discountConfig'] : array();
		$members  = (array) ( $b['members'] ?? array() );
		$thumb    = 0;
		foreach ( $members as $m ) {
			$thumb = absint( $m['variation_id'] ?? 0 ) ? absint( $m['variation_id'] ) : absint( $m['product_id'] ?? 0 );
			if ( $thumb ) {
				break;
			}
		}
		// The lead image the product page's own container line gets.
		$meta = Cart::container_thumb( $dc, absint( $b['anchor'] ), $members );

		return array(
			'bundle'    => (string) $b['bundle'],
			'type'        => Bundle_Store::TYPE_FIXED_BUNDLES,
			'group'       => self::$pending_group,
			'role'        => 'container',
			'offer'       => self::OFFER_BUNDLE,
			'anchor'      => absint( $b['anchor'] ),
			'label'       => (string) $b['title'],
			'bundleTitle' => (string) $b['title'],
			'thumbId'     => $thumb,
			'thumbImgId'  => $meta['imgId'],
			'thumbSrc'    => $meta['src'],
			'unit'        => (float) $b['total'],
			'pricedUnit'  => (float) $b['total'],
		);
	}

	/**
	 * Place a row's bundle lines beside the one Aero just added, and announce it.
	 *
	 * @param string $cart_key     Cart key of the line just added.
	 * @param int    $product_id   Product added.
	 * @param int    $quantity     Quantity added.
	 * @param int    $variation_id Variation added.
	 * @param array  $variation    Variation attributes.
	 * @param array  $data         Cart item data.
	 * @return void
	 */
	public function add_remaining_units( $cart_key, $product_id, $quantity = 1, $variation_id = 0, $variation = array(), $data = array() ) {
		if ( array() !== self::$pending_bundle && self::owns_line( $data ) ) {
			$this->add_bundle_children( (string) $cart_key, $data, $quantity );
			self::row_added( $data );

			return;
		}
		if ( self::owns_line( $data ) ) {
			self::row_added( $data );
		}
	}

	/**
	 * Keep a bundle group's cart lines together where the group first appears:
	 * its container or heading, its items, then its add-ons.
	 *
	 * Re-pointing a line re-keys it, and a re-keyed line lands at the end of the
	 * cart, which would split the group around its add-ons in every summary.
	 *
	 * @param string $group Bundle group id.
	 * @return void
	 */
	public static function gather_group( $group ) {
		$cart = function_exists( 'WC' ) ? WC()->cart : null;
		if ( '' === (string) $group || ! $cart ) {
			return;
		}
		$lead   = array();
		$items  = array();
		$addons = array();
		foreach ( $cart->cart_contents as $k => $ci ) {
			$b = is_array( $ci['wffn_bundles_bundle'] ?? null ) ? $ci['wffn_bundles_bundle'] : array();
			if ( (string) ( $b['group'] ?? '' ) !== (string) $group ) {
				continue;
			}
			$role = (string) ( $b['role'] ?? '' );
			if ( in_array( $role, array( 'container', 'heading' ), true ) ) {
				$lead[] = (string) $k;
			} elseif ( in_array( $role, Cart::addon_roles(), true ) ) {
				$addons[] = (string) $k;
			} else {
				$items[] = (string) $k;
			}
		}
		$block = array_merge( $lead, $items, $addons );
		if ( count( $block ) < 2 ) {
			return;
		}
		$ordered = array();
		$placed  = false;
		foreach ( $cart->cart_contents as $k => $ci ) {
			if ( ! in_array( (string) $k, $block, true ) ) {
				$ordered[ $k ] = $ci;
				continue;
			}
			if ( ! $placed ) {
				foreach ( $block as $key ) {
					$ordered[ $key ] = $cart->cart_contents[ $key ];
				}
				$placed = true;
			}
		}
		$cart->cart_contents = $ordered;
	}

	/**
	 * Announce a row's bundle line, for anything that places lines beside it.
	 *
	 * Every line a row places reaches this, its bundle line among them.
	 *
	 * @param array $data Cart item data of the row's bundle line.
	 * @return void
	 */
	private static function row_added( array $data ) {
		/**
		 * Fires after a checkout row's bundle line is placed.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Cart item data of the row's bundle line.
		 */
		do_action( 'wffn_bundles_funnel_builder_row_added', $data );
	}

	/**
	 * Place a fixed bundle's members beneath the container line just added.
	 *
	 * Children carry no money: `pricedUnit` is zero and `pricedShare` keeps their
	 * discounted value, exactly as Cart::add_bundle builds the pack.
	 *
	 * All or nothing, as Cart::add_bundle() places a pack: when WooCommerce refuses
	 * any member, every line this add placed comes back out, the row's own priced
	 * line included, and the add fails with WooCommerce's own reason.
	 *
	 * @param string $cart_key Cart key of the row's own line (the container, or a set's host).
	 * @param array  $data     Cart item data of the container line.
	 * @param int    $quantity Quantity of the row's own line; a set's companions follow it.
	 * @return void
	 * @throws \Exception When a member could not be added; WC_Cart::add_to_cart() catches it.
	 */
	private function add_bundle_children( $cart_key, array $data, $quantity = 1 ) {
		$bundle = self::$pending_bundle;
		$group  = self::$pending_group;
		// Cleared first: each add below re-enters this hook.
		self::$pending_bundle = array();
		self::$pending_group  = '';

		$placed = array( $cart_key );
		if ( ! empty( $bundle['set'] ) ) {
			self::add_set_companions( $bundle, $data, $quantity, $placed );

			return;
		}

		foreach ( (array) $bundle['members'] as $m ) {
			$key = WC()->cart->add_to_cart(
				absint( $m['product_id'] ),
				max( 1, (int) ( $m['qty'] ?? 1 ) ),
				absint( $m['variation_id'] ?? 0 ),
				isset( $m['variation'] ) && is_array( $m['variation'] ) ? $m['variation'] : array(),
				array(
					'wffn_bundles_bundle'         => array(
						'bundle'    => (string) $bundle['bundle'],
						'type'        => Bundle_Store::TYPE_FIXED_BUNDLES,
						'group'       => (string) $group,
						'role'        => 'child',
						'offer'       => self::OFFER_BUNDLE,
						'label'       => (string) ( $m['label'] ?? '' ),
						'bundleTitle' => (string) $bundle['title'],
						'baseQty'     => max( 1, (int) ( $m['qty'] ?? 1 ) ),
						'unit'        => (float) ( $m['unit'] ?? 0 ),
						'pricedUnit'  => 0.0,
						'pricedShare' => (float) ( $m['pricedUnit'] ?? 0 ),
					),
					'_wfacp_product'     => true,
					'_wfacp_product_key' => (string) ( $data['_wfacp_product_key'] ?? '' ),
					'_wfacp_options'     => isset( $data['_wfacp_options'] ) ? $data['_wfacp_options'] : array(),
				)
			);
			if ( ! is_string( $key ) ) {
				self::roll_back_row( $placed );
				// WooCommerce already showed why the member was refused.
				throw new \Exception();
			}
			$placed[] = $key;
		}
	}

	/**
	 * Place a set's companions beside the checkout row's host line.
	 *
	 * @param array    $set      The set prepare_set() held.
	 * @param array    $data     Cart item data of the host line.
	 * @param int      $quantity The host line's quantity.
	 * @param string[] $placed   Cart keys already placed for this row, its host line first.
	 * @return void
	 * @throws \Exception When a companion could not be added; WC_Cart::add_to_cart() catches it.
	 */
	private static function add_set_companions( array $set, array $data, $quantity, array $placed ) {
		foreach ( (array) $set['members'] as $m ) {
			$key = WC()->cart->add_to_cart(
				absint( $m['product_id'] ),
				max( 1, (int) $quantity ),
				absint( $m['variation_id'] ?? 0 ),
				isset( $m['variation'] ) && is_array( $m['variation'] ) ? $m['variation'] : array(),
				array(
					'wffn_bundles_bundle'         => array_merge(
						$set['meta'],
						array(
							'role'       => 'fbt_companion',
							'unit'       => (float) $m['unit'],
							'pricedUnit' => (float) $m['pricedUnit'],
						)
					),
					'_wfacp_product'     => true,
					'_wfacp_product_key' => (string) ( $data['_wfacp_product_key'] ?? '' ),
					'_wfacp_options'     => isset( $data['_wfacp_options'] ) ? $data['_wfacp_options'] : array(),
				)
			);
			// A host left alone would keep the set's price, so the set goes whole or not at all.
			if ( ! is_string( $key ) ) {
				self::roll_back_row( $placed );
				throw new \Exception();
			}
			$placed[] = $key;
		}
	}

	/**
	 * Report a pack row at its container's quantity.
	 *
	 * WFACP_Common::get_cart_item_key() resolves a row to the first cart line
	 * carrying its key, which for a pack can be one of its members.
	 *
	 * @param int   $quantity  Quantity Aero resolved.
	 * @param array $cart_item Cart item data.
	 * @return int
	 */
	public function row_group_quantity( $quantity, $cart_item = array() ) {
		if ( ! self::owns_line( $cart_item ) ) {
			return $quantity;
		}
		$b     = isset( $cart_item['wffn_bundles_bundle'] ) && is_array( $cart_item['wffn_bundles_bundle'] ) ? $cart_item['wffn_bundles_bundle'] : array();
		$group = (string) ( $b['group'] ?? '' );
		if ( '' === $group || 'container' !== (string) ( $b['role'] ?? '' ) || ! function_exists( 'WC' ) || empty( WC()->cart ) ) {
			return $quantity;
		}
		foreach ( WC()->cart->get_cart() as $ci ) {
			if ( (string) ( $ci['wffn_bundles_bundle']['group'] ?? '' ) === $group && 'container' === (string) ( $ci['wffn_bundles_bundle']['role'] ?? '' ) ) {
				return max( 1, (int) ( $ci['quantity'] ?? 1 ) );
			}
		}

		return $quantity;
	}

	/**
	 * Re-point a fixed bundle's child lines at the variations now chosen.
	 *
	 * The pack's own lines are edited in one request rather than re-triggering the
	 * row's checkbox, which puts Aero through a full rebuild.
	 *
	 * @return void
	 */
	public static function ajax_repoint_pack() {
		Controller::verify();
		// Only ever sent from the quick view's Update, and prepare_bundle() below
		// reads the picks directly rather than through plan_units().
		self::commit_picks();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			Controller::fail( 'no_cart' );
		}
		$item_key = Controller::param( 'item_key' );
		$page_id  = absint( Controller::param( 'wfacp_id' ) );
		if ( '' === $item_key || ! $page_id ) {
			wp_send_json_success( array( 'moved' => false ) );
		}

		$row = self::stored_row( array( 'item_key' => $item_key, 'wfacp_id' => $page_id ) );
		if ( ! self::prepare_bundle( $row ) ) {
			wp_send_json_success( array( 'moved' => false ) );
		}
		$bundle               = self::$pending_bundle;
		self::$pending_bundle = array();
		self::$pending_group  = '';

		wp_send_json_success( array( 'moved' => self::repoint_pack_lines( $item_key, $bundle, $page_id ) ) );
	}

	/**
	 * Move a pack's lines onto a freshly quoted set of members.
	 *
	 * Only when the pack still holds the same members in the same order — a pack
	 * of a different shape is not a variation change, and is left to the add path
	 * that builds one from scratch.
	 *
	 * @param string $item_key Aero's row key.
	 * @param array  $bundle   Pack resolved from the shopper's stored picks.
	 * @param int    $page_id  Checkout page id, for Aero's own row pointer.
	 * @return bool
	 */
	private static function repoint_pack_lines( $item_key, array $bundle, $page_id ) {
		$cart      = WC()->cart;
		$container = '';
		$children  = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( (string) ( $item['_wfacp_product_key'] ?? '' ) !== $item_key ) {
				continue;
			}
			$role = (string) ( $item['wffn_bundles_bundle']['role'] ?? '' );
			if ( 'container' === $role && '' === $container ) {
				$container = $key;
			} elseif ( 'child' === $role ) {
				$children[] = $key;
			}
		}
		$members = array_values( (array) ( $bundle['members'] ?? array() ) );
		if ( '' === $container || count( $children ) !== count( $members ) || array() === $members ) {
			return false;
		}

		foreach ( $members as $i => $m ) {
			self::point_line_at_member( $children[ $i ], $m );
		}
		// The pack is priced on its container; a member's variation moves that total.
		$item = $cart->cart_contents[ $container ];
		$item['wffn_bundles_bundle']['unit']       = (float) ( $bundle['total'] ?? 0 );
		$item['wffn_bundles_bundle']['pricedUnit'] = (float) ( $bundle['total'] ?? 0 );
		$cart->cart_contents[ $container ] = $item;
		self::gather_group( (string) ( $item['wffn_bundles_bundle']['group'] ?? '' ) );

		$cart->calculate_totals();
		self::point_row_at_line( $page_id, $item_key, $container );

		return true;
	}

	/**
	 * Re-point one child line of a fixed bundle at the member it now sells.
	 *
	 * The pack's own quantity stays: a member's share does not change with a
	 * variation.
	 *
	 * @param string $key Cart key to rewrite.
	 * @param array  $m   Member row the line now sells.
	 * @return string The line's key, which a new variation changes.
	 */
	private static function point_line_at_member( $key, array $m ) {
		$item = WC()->cart->cart_contents[ $key ];
		$vid  = absint( $m['variation_id'] ?? 0 );
		$pid  = absint( $m['product_id'] ?? 0 );

		$item['product_id']   = $pid;
		$item['variation_id'] = $vid;
		$item['variation']    = isset( $m['variation'] ) && is_array( $m['variation'] ) ? $m['variation'] : array();
		$item['data']         = wc_get_product( $vid ? $vid : $pid );

		$b                = isset( $item['wffn_bundles_bundle'] ) && is_array( $item['wffn_bundles_bundle'] ) ? $item['wffn_bundles_bundle'] : array();
		$b['unit']        = (float) ( $m['unit'] ?? 0 );
		$b['pricedShare'] = (float) ( $m['pricedUnit'] ?? 0 );
		if ( '' !== (string) ( $m['label'] ?? '' ) ) {
			$b['label'] = (string) $m['label'];
		}
		$item['wffn_bundles_bundle'] = $b;

		return self::rekey_line( $key, $item );
	}

	/**
	 * Store an edited line under the key its new contents hash to.
	 *
	 * A cart key is a hash of what the line sells, and Aero looks its rows up that
	 * way, dropping what it cannot place.
	 *
	 * @param string $key  The line's current key.
	 * @param array  $item The line, already rewritten.
	 * @return string The key it now lives under.
	 */
	public static function rekey_line( $key, array $item ) {
		$cart = WC()->cart;
		// The add-time price stamp must be the new product's, or drop_stale_bundles() reads the swap as a price change.
		if ( '' !== (string) ( $item['wffn_bundles_bundle']['bundle'] ?? '' ) ) {
			$item['wffn_bundles_bundle']['baseAt'] = Layer::raw_base_price( wc_get_product( absint( $item['variation_id'] ) ? absint( $item['variation_id'] ) : absint( $item['product_id'] ) ) );
		}
		$extra = $item;
		foreach ( array( 'key', 'product_id', 'variation_id', 'variation', 'quantity', 'data', 'data_hash' ) as $own ) {
			unset( $extra[ $own ] );
		}
		$fresh             = $cart->generate_cart_id( absint( $item['product_id'] ), absint( $item['variation_id'] ), (array) $item['variation'], $extra );
		$item['key']       = $fresh;
		$item['data_hash'] = wc_get_cart_item_data_hash( $item['data'] );

		unset( $cart->cart_contents[ $key ] );
		// Another line may already hash to the same key; merge rather than overwrite it.
		if ( $fresh !== $key && isset( $cart->cart_contents[ $fresh ] ) ) {
			$item['quantity'] += (int) $cart->cart_contents[ $fresh ]['quantity'];
		}
		$cart->cart_contents[ $fresh ] = $item;

		return $fresh;
	}

	/**
	 * Take a row's add-ons with it when the row leaves the cart.
	 *
	 * Aero removes only the line it placed for a deselected row; the add-ons hung
	 * off that row are ours and nothing else would collect them.
	 *
	 * @param string   $key  Cart key just removed.
	 * @param \WC_Cart $cart The cart.
	 * @return void
	 */
	public function drop_row_addons( $key, $cart ) {
		if ( ! is_object( $cart ) || ! isset( $cart->removed_cart_contents[ $key ] ) ) {
			return;
		}
		$gone = $cart->removed_cart_contents[ $key ];
		$b    = isset( $gone['wffn_bundles_bundle'] ) && is_array( $gone['wffn_bundles_bundle'] ) ? $gone['wffn_bundles_bundle'] : array();
		if ( ! in_array( (string) ( $b['role'] ?? '' ), array( 'container', 'anchor', 'member' ), true ) ) {
			return;
		}
		$bundle = (string) ( $b['bundle'] ?? '' );
		$row      = (string) ( $gone['_wfacp_product_key'] ?? '' );
		if ( '' === $bundle || '' === $row ) {
			return;
		}
		// A row split across lines loses one at a time; add-ons stay until the last.
		foreach ( $cart->cart_contents as $ci ) {
			$cb = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : array();
			if ( (string) ( $cb['bundle'] ?? '' ) === $bundle
				&& (string) ( $ci['_wfacp_product_key'] ?? '' ) === $row
				&& in_array( (string) ( $cb['role'] ?? '' ), array( 'container', 'anchor', 'member' ), true ) ) {
				return;
			}
		}
		foreach ( array_keys( $cart->cart_contents ) as $k ) {
			$ci = $cart->cart_contents[ $k ];
			$cb = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : array();
			if ( (string) ( $cb['bundle'] ?? '' ) !== $bundle
				|| (string) ( $ci['_wfacp_product_key'] ?? '' ) !== $row
				|| ! in_array( (string) ( $cb['role'] ?? '' ), array_merge( array( 'child' ), Cart::addon_roles() ), true ) ) {
				continue;
			}
			$cart->remove_cart_item( $k );
		}
	}

	/**
	 * Whether Aero's checkout is the one driving this request.
	 *
	 * @return bool
	 */
	public static function aero_ajax() {
		return wp_doing_ajax() && ! empty( Request_Context::raw_request( 'wfacp_id' ) );
	}

	/**
	 * Decide what Aero's quick-view modal shows for this row.
	 *
	 * A row selling no offer gets the plain product quick view, since the widget
	 * would be a second picker over a choice already made. A row that does sell one
	 * keeps the widget, for whoever answers the offer to cut down.
	 *
	 * @param array $post The quick view's posted data.
	 * @return void
	 */
	public function open_quick_view( $post = array() ) {
		self::$qv_anchor = 0;
		// The set's own button would be a second add beside the modal's.
		foreach ( Product_Display::fbt_hooks() as $hook => $priority ) {
			remove_action( $hook, array( Product_Display::get_instance(), 'render_fbt' ), $priority );
		}
		$row   = self::stored_row( $post );
		$offer = null;
		if ( array() !== $row ) {
			if ( $this->open_pack_quick_view( $row ) || $this->open_set_quick_view( $row, $post ) ) {
				return;
			}
			$offer = self::row_offer( $row, wc_get_product( absint( $row['id'] ?? 0 ) ) );
		}
		if ( null === $offer ) {
			$display = Product_Display::get_instance();
			remove_action( 'woocommerce_before_add_to_cart_form', array( $display, 'render' ), 5 );
			remove_action( 'woocommerce_before_variations_form', array( $display, 'render' ), 5 );

			return;
		}

		/**
		 * Fires when a checkout row's quick view keeps the widget for the offer it sells.
		 *
		 * @since 1.0.0
		 *
		 * @param array $offer What `wffn_bundles_aero_row_offer` resolved for the row.
		 * @param array $row   Stored row the modal was opened from.
		 */
		do_action( 'wffn_bundles_aero_quick_view_offer', $offer, $row );
		// An add-on ticked in the modal would join the cart with no row of its own.
		add_filter( 'wffn_bundles_render_addons', '__return_false' );
	}

	/**
	 * Show the pack's own bundle in the quick view, not the placeholder's product.
	 *
	 * A pack row sells the shared container, which carries no bundle, so the
	 * widget would draw nothing. render_product() takes the anchor instead.
	 *
	 * @param array $row Stored row the modal was opened from.
	 * @return bool Whether the row was a pack and has been handled.
	 */
	private function open_pack_quick_view( array $row ) {
		/**
		 * Render the bundle widget inside a pack row's quick view.
		 *
		 * @param bool $enabled Whether to draw the bundle in the modal.
		 */
		if ( array() === self::row_bundle( $row ) ) {
			return false;
		}
		$meta   = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		$anchor = wc_get_product( absint( $meta[ self::ANCHOR_KEY ] ?? 0 ) );
		if ( ! $anchor instanceof \WC_Product ) {
			return false;
		}

		// The container has no image of its own, whether or not the widget is on.
		self::$row_product_id = absint( $row['id'] ?? 0 );
		self::$row_anchor_img = absint( $anchor->get_image_id() );

		if ( ! apply_filters( 'wffn_bundles_aero_pack_quick_view', true ) ) {
			return false;
		}
		self::$qv_anchor   = $anchor->get_id();
		self::$qv_bundle = (string) ( $meta[ self::BUNDLE_KEY ] ?? '' );

		// The container's own render resolves no bundle, so it is replaced.
		$display = Product_Display::get_instance();
		remove_action( 'woocommerce_before_add_to_cart_form', array( $display, 'render' ), 5 );
		remove_action( 'woocommerce_before_variations_form', array( $display, 'render' ), 5 );
		// Inside form.cart, not before it: the widget's hidden fields have to post
		// with the add, and the live quote reads its selection from that form.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_pack_widget' ), 5 );

		// The row already sells the pack, so the single-product radio has no answer.
		add_filter( 'wffn_bundles_bundle_args', array( $this, 'restrict_to_pack' ), 20 );
		add_filter( 'wffn_bundles_render_addons', '__return_false' );

		return true;
	}

	/**
	 * Show a set row's set in the quick view, ticked as the row's lines hold it.
	 *
	 * @param array $row  Stored row the modal was opened from.
	 * @param array $post The quick view's posted data.
	 * @return bool Whether the row was a set and has been handled.
	 */
	private function open_set_quick_view( array $row, $post ) {
		$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		$host = wc_get_product( absint( $row['id'] ?? 0 ) );
		if ( Bundle_Store::FBT_OFFER !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) || ! $host instanceof \WC_Product ) {
			return false;
		}
		self::$qv_set = array(
			'host' => $host->get_id(),
			'key'  => is_array( $post ) ? sanitize_text_field( (string) ( $post['item_key'] ?? '' ) ) : '',
		);
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_set_widget' ), 5 );

		return true;
	}

	/**
	 * The set row's set, inside its quick view.
	 *
	 * @return void
	 */
	public function render_set_widget() {
		$host = wc_get_product( (int) ( self::$qv_set['host'] ?? 0 ) );
		if ( ! $host instanceof \WC_Product ) {
			return;
		}
		$html = Product_Display::get_instance()->render_fbt_product(
			$host,
			array(
				'button'       => false,
				'allow_repeat' => true,
				'set'          => Cart::fbt_line_set( self::row_host_key( (string) ( self::$qv_set['key'] ?? '' ) ) ),
			)
		);
		Markup::output( $html );
	}

	/**
	 * The cart line hosting a checkout row's set, or ''.
	 *
	 * @param string $item_key Aero's row key.
	 * @return string
	 */
	private static function row_host_key( $item_key ) {
		if ( '' === $item_key || ! function_exists( 'WC' ) || empty( WC()->cart ) ) {
			return '';
		}
		foreach ( WC()->cart->get_cart() as $key => $line ) {
			if ( (string) ( $line['_wfacp_product_key'] ?? '' ) === $item_key && 'fbt_host' === (string) ( $line['wffn_bundles_bundle']['role'] ?? '' ) ) {
				return (string) $key;
			}
		}
		return '';
	}

	/**
	 * Re-place a set row's companions from the quick view's ticks and picks.
	 *
	 * Aero's Update rewrites only the row's own line, so its set is rebuilt here.
	 *
	 * @return void
	 */
	public static function ajax_rebuild_set() {
		Controller::verify();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			Controller::fail( 'no_cart' );
		}
		$selection = Controller::json( 'selection' );
		$host_key  = self::row_host_key( Controller::param( 'item_key' ) );
		$item      = '' === $host_key ? null : WC()->cart->get_cart_item( $host_key );
		$b         = is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : array();
		$bundle  = Bundle_Store::get_config( (string) ( $b['bundle'] ?? '' ) );
		if ( ! is_array( $selection ) || ! is_array( $bundle ) || ! Bundle_Store::is_active_now( $bundle ) ) {
			wp_send_json_success( array( 'moved' => false ) );
		}
		$members = Cart::fbt_set_members( $bundle, $item['data'], $selection );
		if ( empty( $members ) ) {
			wp_send_json_success( array( 'moved' => false ) );
		}
		$host = array_shift( $members );
		// The set is rebuilt whole or not at all: a refused companion puts the cart back
		// as it was, so the host never keeps the new set price without its companions.
		$before = WC()->cart->cart_contents;
		// Out quietly: the set is re-placed whole, so no line is left behind to warn about.
		foreach ( WC()->cart->cart_contents as $key => $line ) {
			if ( 'fbt_companion' === (string) ( $line['wffn_bundles_bundle']['role'] ?? '' ) && (string) ( $line['wffn_bundles_bundle']['group'] ?? '' ) === (string) ( $b['group'] ?? '' ) ) {
				unset( WC()->cart->cart_contents[ $key ] );
			}
		}
		WC()->cart->cart_contents[ $host_key ]['wffn_bundles_bundle']['unit']       = (float) $host['unit'];
		WC()->cart->cart_contents[ $host_key ]['wffn_bundles_bundle']['pricedUnit'] = (float) $host['pricedUnit'];
		try {
			self::add_set_companions(
				array(
					'members' => $members,
					'meta'    => array_diff_key( $b, array_flip( array( 'role', 'unit', 'pricedUnit', 'baseAt' ) ) ),
				),
				$item,
				(int) ( $item['quantity'] ?? 1 ),
				array()
			);
		} catch ( \Throwable $e ) {
			WC()->cart->cart_contents = $before;
			WC()->cart->calculate_totals();
			// WooCommerce has already queued why the companion was refused.
			Controller::fail( 'out_of_stock', 409 );
		}
		WC()->cart->calculate_totals();

		wp_send_json_success( array( 'moved' => true ) );
	}

	/**
	 * The anchor's bundle widget, inside the pack row's quick view.
	 *
	 * @return void
	 */
	public function render_pack_widget() {
		$anchor = wc_get_product( self::$qv_anchor );
		if ( ! $anchor instanceof \WC_Product ) {
			return;
		}
		Markup::output( Product_Display::get_instance()->render_product( $anchor ) );
		// Cart::form_fields() runs on this hook but reads the global product, which
		// here is the container and carries no bundle.
		Cart::get_instance()->form_fields( $anchor );
	}

	/**
	 * List a Fixed Bundle pack's own members under its row, each on its own line.
	 *
	 * Aero's row sells the simple container placeholder and never lists what a
	 * pack actually contains. A member with a variable product gets its own
	 * inline picker rather than one combined control for the whole pack, so
	 * picking a new Color for the Hoodie never disturbs the Mug beside it.
	 *
	 * @param array|null  $cart_item         Cart item, null for an uncarted row.
	 * @param string      $cart_item_key     Cart item key.
	 * @param \WC_Product $pro               Product the row sells.
	 * @param array       $switcher_settings Product switcher settings.
	 * @param array       $product_data      Stored row.
	 * @return void
	 */
	public function render_pack_members( $cart_item, $cart_item_key, $pro, $switcher_settings = array(), $product_data = array() ) {
		if ( ! is_array( $product_data ) ) {
			return;
		}
		$bundle = self::row_bundle( $product_data );
		$members = array_values( (array) ( $bundle['members'] ?? array() ) );
		if ( array() === $members ) {
			return;
		}
		// The picker's own quote/commit round trip rides wffnBundlesCart's endpoint and
		// nonce, the same config every other live-priced widget on this page uses.
		Product_Display::ensure_cart_config();

		printf(
			'<div class="wffn-bundles wffn-bundles-aero-pack-members" data-pack-bundle="%s" data-pack-offer="%s">',
			esc_attr( (string) ( $bundle['bundle']['id'] ?? '' ) ),
			esc_attr( self::OFFER_BUNDLE )
		);
		foreach ( $members as $i => $m ) {
			$this->render_pack_member( $i, (array) $m );
		}
		echo '</div>';
	}

	/**
	 * List a set row's products under it, each companion with its own tick and picker.
	 *
	 * A carted row lists the set its lines hold; an uncarted one, the set's opening ticks.
	 *
	 * @param array|null  $cart_item         Cart item, null for an uncarted row.
	 * @param string      $cart_item_key     Cart item key.
	 * @param \WC_Product $pro               Product the row sells.
	 * @param array       $switcher_settings Product switcher settings.
	 * @param array       $product_data      Stored row.
	 * @return void
	 */
	public function render_set_members( $cart_item, $cart_item_key, $pro, $switcher_settings = array(), $product_data = array() ) {
		$meta = is_array( $product_data ) && is_array( $product_data['products_meta_data'][1] ?? null ) ? $product_data['products_meta_data'][1] : array();
		if ( Bundle_Store::FBT_OFFER !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
			return;
		}
		$bundle = Bundle_Store::get_config( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) );
		$host     = wc_get_product( absint( $product_data['id'] ?? 0 ) );
		if ( ! is_array( $bundle ) || ! $host instanceof \WC_Product || ! Bundle_Store::is_active_now( $bundle ) ) {
			return;
		}
		$dc     = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$set    = Cart::fbt_line_set( self::row_host_key( (string) ( $product_data['item_key'] ?? '' ) ) );
		$picked = is_array( $set ) ? (array) ( $set['items'] ?? array() ) : null;
		$ids    = \WFFN\Bundles\Includes\Skins\Frequently_Bought_Together::companion_ids( $dc, $host );
		if ( empty( $ids ) ) {
			return;
		}
		Product_Display::ensure_cart_config();

		$hydrated = Repository::find( $host->get_id() );
		printf( '<div class="wffn-bundles wffn-bundles-aero-pack-members wffn-bundles-aero-set-members" data-set-product="%s">', esc_attr( (string) $host->get_id() ) );
		echo '<div class="wffn-bundles-aero-pack-member wffn-bundles-aero-set-member is-host">';
		printf(
			'<input type="checkbox" class="wffn-bundles-aero-set-check" aria-label="%s" checked disabled />',
			/* translators: %s: name of the product the set is bought with. */
			esc_attr( sprintf( __( 'This item: %s', 'funnel-builder' ), $host->get_name() ) )
		);
		if ( is_array( $hydrated ) && '' !== (string) ( $hydrated['image'] ?? '' ) ) {
			printf( '<img class="wffn-bundles-aero-pack-member-img" src="%s" alt="" />', esc_url( (string) $hydrated['image'] ) );
		}
		/* translators: %s: name of the product the set is bought with. */
		printf( '<span class="wffn-bundles-aero-pack-member-body"><span class="wffn-bundles-aero-pack-member-name">%s</span></span>', esc_html( sprintf( __( 'This item: %s', 'funnel-builder' ), $host->get_name() ) ) );
		echo '</div>';
		foreach ( $ids as $id ) {
			$this->render_set_member( (int) $id, $dc, $picked );
		}
		echo '</div>';
	}

	/**
	 * One companion's line in a set row: its tick, thumbnail, name and picker.
	 *
	 * @param int        $id     Companion product id.
	 * @param array      $dc     The set bundle's discount config.
	 * @param array|null $picked The carted set's companions and their picks, or null when uncarted.
	 * @return void
	 */
	private function render_set_member( $id, array $dc, $picked ) {
		$hydrated = Repository::find( $id );
		if ( ! is_array( $hydrated ) ) {
			return;
		}
		$on     = null === $picked ? \WFFN\Bundles\Includes\Skins\Frequently_Bought_Together::starts_checked( $dc, $id ) : isset( $picked[ $id ] );
		$chosen = null === $picked ? array() : self::member_chosen_labels( $id, (array) ( $picked[ $id ] ?? array() ) );
		if ( array() === $chosen ) {
			foreach ( (array) ( $hydrated['attributes'] ?? array() ) as $axis ) {
				if ( '' !== (string) ( $axis['default'] ?? '' ) ) {
					$chosen[ (string) ( $axis['name'] ?? '' ) ] = (string) $axis['default'];
				}
			}
		}
		$picker = Variants::picker( $hydrated );

		printf( '<div class="wffn-bundles-aero-pack-member wffn-bundles-aero-set-member%s" data-set-key="%s">', esc_attr( $on ? '' : ' is-off' ), esc_attr( (string) $id ) );
		printf(
			'<input type="checkbox" class="wffn-bundles-aero-set-check" aria-label="%s"%s />',
			/* translators: %s: companion product name. */
			esc_attr( sprintf( __( 'Add %s', 'funnel-builder' ), (string) ( $hydrated['name'] ?? '' ) ) ),
			checked( $on, true, false )
		);
		if ( '' !== (string) ( $hydrated['image'] ?? '' ) ) {
			printf( '<img class="wffn-bundles-aero-pack-member-img" src="%s" alt="" />', esc_url( (string) $hydrated['image'] ) );
		}
		echo '<span class="wffn-bundles-aero-pack-member-body">';
		printf( '<span class="wffn-bundles-aero-pack-member-name">%s</span>', esc_html( (string) ( $hydrated['name'] ?? '' ) ) );
		if ( ! empty( $picker['variantShow'] ) ) {
			$this->render_pack_member_axes( $id, $id, (array) $picker, $chosen, 'wffn-bundles-variant-axis' );
		}
		echo '</span></div>';
	}

	/**
	 * One pack member's own line: thumbnail, name, and its variant picker when
	 * the member is a variable product.
	 *
	 * @param int   $index  The member's position in the bundle's own list —
	 *                      the same index `Cart::fixed_bundle_members()`'s
	 *                      `$picks` argument and the picker's `data-pack-index`
	 *                      read and write.
	 * @param array $member One resolved member row from row_bundle().
	 * @return void
	 */
	private function render_pack_member( $index, array $member ) {
		$product_id = absint( $member['product_id'] ?? 0 );
		$hydrated   = $product_id ? Repository::find( $product_id ) : null;
		if ( ! is_array( $hydrated ) ) {
			return;
		}
		$qty  = max( 1, (int) ( $member['qty'] ?? 1 ) );
		$picker = Variants::picker( $hydrated );
		$image  = self::member_image( absint( $member['variation_id'] ?? 0 ), (string) ( $hydrated['image'] ?? '' ) );

		printf( '<div class="wffn-bundles-aero-pack-member" data-pack-index="%s">', esc_attr( (string) $index ) );
		if ( '' !== $image ) {
			printf( '<img class="wffn-bundles-aero-pack-member-img" src="%s" alt="" />', esc_url( $image ) );
		}
		echo '<span class="wffn-bundles-aero-pack-member-body">';
		echo '<span class="wffn-bundles-aero-pack-member-name">';
		if ( $qty > 1 ) {
			echo absint( $qty ) . '&times; ';
		}
		echo esc_html( (string) ( $hydrated['name'] ?? '' ) ) . '</span>';
		if ( ! empty( $picker['variantShow'] ) ) {
			$this->render_pack_member_axes( $index, $product_id, (array) $picker, self::member_chosen_labels( $product_id, (array) ( $member['variation'] ?? array() ) ) );
		}
		echo '</span></div>';
	}

	/**
	 * A resolved member's own variation image, falling back to its parent's when
	 * the variation carries none of its own.
	 *
	 * @param int    $variation_id Resolved variation id, 0 for a simple member.
	 * @param string $fallback     The parent product's own image URL.
	 * @return string
	 */
	private static function member_image( $variation_id, $fallback ) {
		if ( ! $variation_id ) {
			return $fallback;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! $variation instanceof \WC_Product ) {
			return $fallback;
		}
		$url = wp_get_attachment_image_url( $variation->get_image_id(), 'thumbnail' );

		return $url ? $url : $fallback;
	}

	/**
	 * The picker's own axes for one member, pre-selected to its resolved variation.
	 *
	 * @param int    $index      The member's position; carried so a change event
	 *                           on any axis names which member it belongs to.
	 * @param int    $product_id The member's parent product id.
	 * @param array  $picker     Variants::picker() output.
	 * @param array  $chosen     Currently resolved { "Display Label": "value" }.
	 * @param string $select     Class list for each select; a set's picks are not a pack's.
	 * @return void
	 */
	private function render_pack_member_axes( $index, $product_id, array $picker, array $chosen, $select = 'wffn-bundles-pack-axis wffn-bundles-variant-axis' ) {
		// `.wffn-bundles-variant` and `data-wffn-bundles-vinfo` are the contract `variant-modal.js`
		// already scans every `.wffn-bundles` widget for; it decides inline vs. popup
		// itself from `Options_Display::mode()`, so nothing else opts in here.
		printf(
			'<span class="wffn-bundles-aero-pack-member-axes wffn-bundles-variant" data-pack-index="%s" data-pack-product="%s" data-wffn-bundles-variations="%s" data-wffn-bundles-vinfo="%s">',
			esc_attr( (string) $index ),
			esc_attr( (string) $product_id ),
			esc_attr( (string) ( $picker['variantMatrix'] ?? '' ) ),
			esc_attr( (string) ( $picker['variantInfo'] ?? '' ) )
		);
		foreach ( (array) ( $picker['variantAxes'] ?? array() ) as $axis ) {
			printf( '<select class="%s">', esc_attr( $select ) );
			foreach ( (array) ( $axis['options'] ?? array() ) as $opt ) {
				$value = (string) ( $opt['value'] ?? '' );
				$parts = explode( ':', $value, 2 );
				$selected = '' !== $value && isset( $parts[1], $chosen[ $parts[0] ] ) && $chosen[ $parts[0] ] === $parts[1];
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $value ),
					selected( $selected, true, false ),
					esc_html( (string) ( $opt['label'] ?? '' ) )
				);
			}
			echo '</select>';
		}
		echo '</span>';
	}

	/**
	 * A resolved member's variation attributes, keyed by display label rather
	 * than WooCommerce's raw `attribute_pa_color` slug — the format the
	 * picker's own option values are built from (Variants::axis()).
	 *
	 * @param int   $product_id Member's parent product id.
	 * @param array $variation  Raw `{ attribute_<slug>: value }` map, as
	 *                          `Cart::resolve_member()` returns it.
	 * @return array{string:string}
	 */
	public static function member_chosen_labels( $product_id, array $variation ) {
		if ( array() === $variation ) {
			return array();
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}
		$labels = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( is_object( $attr ) && $attr->get_variation() ) {
				$labels[ 'attribute_' . sanitize_title( $attr->get_name() ) ] = wc_attribute_label( (string) $attr->get_name(), $product );
			}
		}
		$chosen = array();
		foreach ( $variation as $slug => $value ) {
			if ( isset( $labels[ $slug ] ) && '' !== (string) $value ) {
				$chosen[ $labels[ $slug ] ] = (string) $value;
			}
		}

		return $chosen;
	}

	/**
	 * What a pack row charges: the pack at the row's quantity, plus its add-ons.
	 *
	 * The stepper multiplies the pack but not the add-ons, which stay one apiece,
	 * so only the pack half is scaled here.
	 *
	 * @param array $bundle       Resolved row bundle.
	 * @param array $product_data Stored row being priced.
	 * @return float
	 */
	private static function row_pack_charge( array $bundle, $product_data ) {
		return self::row_pack_base( $bundle, $product_data ) + self::row_extras_total( (array) $bundle['bundle'], $product_data )['final'];
	}

	/**
	 * The pack alone at the row's quantity, without its add-ons.
	 *
	 * @param array $bundle       Resolved row bundle.
	 * @param array $product_data Stored row being priced.
	 * @return float
	 */
	private static function row_pack_base( array $bundle, $product_data ) {
		$qty = is_array( $product_data ) ? max( 1, (int) ( $product_data['quantity'] ?? 1 ) ) : 1;

		return (float) ( $bundle['total'] ?? 0 ) * $qty;
	}

	/**
	 * What a row's add-ons add to its price, before and after their own discount.
	 *
	 * @param array $bundle     Resolved bundle.
	 * @param mixed $product_data Stored row being priced.
	 * @return array{regular:float,final:float}
	 */
	public static function row_extras_total( array $bundle, $product_data ) {
		/**
		 * Filters what a checkout row's add-ons add to its price.
		 *
		 * @since 1.0.0
		 *
		 * @param array $totals       { regular, final }, both 0 by default.
		 * @param array $bundle     The bundle backing this row.
		 * @param mixed $product_data Stored row being priced.
		 */
		$totals = (array) apply_filters( 'wffn_bundles_funnel_builder_row_extras_total', array( 'regular' => 0.0, 'final' => 0.0 ), $bundle, $product_data );

		return array(
			'regular' => (float) ( $totals['regular'] ?? 0 ),
			'final'   => (float) ( $totals['final'] ?? 0 ),
		);
	}

	/**
	 * The bundle and offer a switcher row sells, or null.
	 *
	 * A pack row's bundle rides the row meta; any other row's comes from
	 * `wffn_bundles_aero_row_offer`.
	 *
	 * @param array $row Stored row.
	 * @param mixed $pro Product the row sells.
	 * @return array{bundle:array,offer:string}|null
	 */
	public static function row_bundle_offer( $row, $pro ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$bundle = self::row_bundle( $row );
		if ( array() !== $bundle ) {
			return array( 'bundle' => self::hydrated_pack_bundle( $row, (array) $bundle['bundle'] ), 'offer' => self::OFFER_BUNDLE );
		}
		$offer = self::row_offer( $row, $pro );

		return null === $offer ? null : array( 'bundle' => $offer['bundle'], 'offer' => $offer['offer'] );
	}

	/**
	 * Whether this row's bundle is one the shopper is actually buying.
	 *
	 * Sold-together has no control and no `is_checked`, so every row counts.
	 *
	 * @param array $product_data Stored row.
	 * @return bool
	 */
	public static function row_is_live( array $product_data ) {
		if ( '1' === self::$tray_mode ) {
			return true;
		}

		return '' !== (string) ( $product_data['is_checked'] ?? '' );
	}

	/**
	 * A pack row's bundle with its referenced products filled in.
	 *
	 * row_bundle() resolves through Bundle_Store::get_config(), whose raw payload
	 * carries a referenced product as a bare `{ id }`. active_for_product() hydrates.
	 *
	 * @param array $row      Stored row.
	 * @param array $bundle Raw bundle row_bundle() resolved.
	 * @return array
	 */
	private static function hydrated_pack_bundle( array $row, array $bundle ) {
		$meta   = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		$anchor = absint( $meta[ self::ANCHOR_KEY ] ?? 0 );
		$full   = $anchor ? Bundle_Store::active_for_product( $anchor ) : null;

		// An anchor can carry more than one bundle; only the row's own will do.
		if ( is_array( $full ) && (string) ( $full['id'] ?? '' ) === (string) ( $bundle['id'] ?? '' ) ) {
			return $full;
		}
		$own = Bundle_Store::get( (string) ( $bundle['id'] ?? '' ) );

		return is_array( $own ) ? $own : $bundle;
	}

	/**
	 * The checkout's Product Selection setting, as Aero stores it.
	 *
	 * `2` draws a radio and keeps one row live, `3` a checkbox and any number, `1`
	 * no control because every row is sold together. Aero defaults a checkout with
	 * no meta to `2`, so this matches on a legacy page.
	 *
	 * @param array $switcher_settings Product switcher settings.
	 * @return string One of '1', '2', '3'.
	 */
	private static function selection_mode( $switcher_settings ) {
		$settings = is_array( $switcher_settings ) && is_array( $switcher_settings['product_settings'] ?? null )
			? $switcher_settings['product_settings']
			: array();
		$mode     = (string) ( $settings['add_to_cart_setting'] ?? '' );

		return in_array( $mode, array( '1', '2', '3' ), true ) ? $mode : '2';
	}

	/**
	 * Note the selection mode while a row is rendering.
	 *
	 * The mode reaches us only as a row argument, and the tray that needs it is
	 * printed after the last row.
	 *
	 * @param array|null  $cart_item         Cart item, null for an uncarted row.
	 * @param string      $cart_item_key     Cart item key.
	 * @param \WC_Product $pro               Product the row sells.
	 * @param array       $switcher_settings Product switcher settings.
	 * @param array       $product_data      Stored row.
	 * @return void
	 */
	public function read_selection_mode( $cart_item, $cart_item_key, $pro, $switcher_settings = array(), $product_data = array() ) {
		self::$tray_mode = self::selection_mode( $switcher_settings );
	}

	/**
	 * Pin a pack row's quick view to the bundle rather than the single product.
	 *
	 * The single option is hidden in CSS, not dropped: removing it takes the
	 * `.wffn-bundles-fb-card` wrapper the live quote collects its selection from.
	 *
	 * @param array $bundle View-model input args.
	 * @return array
	 */
	public function restrict_to_pack( $bundle ) {
		if ( ! is_array( $bundle ) || ! self::$qv_anchor ) {
			return $bundle;
		}
		$bundle['selected'] = 'bundle';

		// Open on what the row sells: the row prices the remembered picks, and the
		// bundle's defaults would quote different money for the same offer.
		$picks = self::stored_picks( self::$qv_bundle );
		if ( array() === $picks || ! is_array( $bundle['items'] ?? null ) ) {
			return $bundle;
		}
		$items = array_values( $bundle['items'] );
		foreach ( $items as $i => $item ) {
			if ( ! isset( $picks[ $i ] ) || ! is_array( $item['attributes'] ?? null ) ) {
				continue;
			}
			foreach ( array_keys( $item['attributes'] ) as $a ) {
				$name = (string) ( $item['attributes'][ $a ]['name'] ?? '' );
				if ( '' !== $name && isset( $picks[ $i ][ $name ] ) ) {
					$items[ $i ]['attributes'][ $a ]['default'] = (string) $picks[ $i ][ $name ];
				}
			}
		}
		$bundle['items'] = $items;

		return $bundle;
	}

	/**
	 * The stored row a quick view was opened from, or an empty array.
	 *
	 * @param array $post The quick view's posted data.
	 * @return array
	 */
	public static function stored_row( $post ) {
		if ( ! is_array( $post ) || ! class_exists( '\\WFACP_Common' ) ) {
			return array();
		}
		$item_key = isset( $post['item_key'] ) ? sanitize_text_field( (string) $post['item_key'] ) : '';
		if ( '' === $item_key ) {
			// Aero's switch endpoint names the row being switched to `new_item`.
			$item_key = sanitize_text_field( (string) ( $post['new_item'] ?? '' ) );
		}
		$page_id  = absint( $post['wfacp_id'] ?? 0 );
		if ( '' === $item_key || ! $page_id ) {
			return array();
		}
		$rows = (array) \WFACP_Common::get_page_product( $page_id );

		return isset( $rows[ $item_key ] ) && is_array( $rows[ $item_key ] ) ? $rows[ $item_key ] : array();
	}

	/**
	 * Keep the native-picker flag off a widget drawn for this checkout.
	 *
	 * The widget's root carries its flags as `data-wffn-bundles-flags`, and the behavior
	 * layer copies them onto <body> once it wires the widget. So a single widget
	 * in a quick view or a switcher row would put the flag
	 * checkout_widget_flags() leaves out back on the body, where it stays for
	 * every quick view opened after it. Only this flag is dropped: the others
	 * scope the widget's own look and hide nothing of the host's.
	 *
	 * @param string[] $flags Flag classes for the widget being rendered.
	 * @return string[]
	 */
	public function unflag_checkout_widget( $flags ) {
		if ( ! is_array( $flags ) || ! self::renders_for_checkout() ) {
			return $flags;
		}

		return array_values( array_diff( $flags, array( self::NATIVE_PICKERS_FLAG ) ) );
	}

	/**
	 * Hide the native pickers only in a quick view the widget actually sits in.
	 *
	 * `_shared.css` hides WooCommerce's quantity and variation controls under
	 * `body.wffn-bundles-owns-pickers form.cart`, which suits a product page: one form, and
	 * the widget replaces it. A checkout opens a new `form.cart` in every quick
	 * view, and most of them hold no widget -- a product with no bundle, a row
	 * that sells no offer (open_quick_view()), or a bundle type the modal does not
	 * draw -- so under a page-wide flag they showed an add button and nothing to
	 * choose with. The flag stays off this page (checkout_widget_flags(),
	 * unflag_checkout_widget()) and the hide is redone here, per modal.
	 *
	 * The id outweighs the host theme's own `table.variations` display rules, so
	 * plain weight is enough.
	 *
	 * @return void
	 */
	public function hide_replaced_native_pickers() {
		$handle = Assets::style_handle( 'assets/skins/_shared.css' );
		if ( ! is_singular( 'wfacp_checkout' ) || ! wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}
		$controls = ':is(.quantity,.variations,.reset_variations,.woocommerce-variation-price)';
		wp_add_inline_style(
			$handle,
			'#wfacp_qr_model_wrap:has(.wffn-bundles) form.cart ' . $controls . '{display:none}'
		);
	}

	/**
	 * Is this request drawing something for an Aero checkout page?
	 *
	 * The page itself, or one of its AJAX calls (fragments, either quick view),
	 * which all carry the page's id.
	 *
	 * @return bool
	 */
	private static function renders_for_checkout() {
		return did_action( 'wfacp_after_checkout_page_found' ) > 0 || self::aero_ajax();
	}

	/**
	 * Whether any product this checkout lists belongs to a bundle.
	 *
	 * The corrections in `assets/compat/funnel-builder.*` are written against Aero's
	 * markup, so a checkout with no bundle has nothing for them to correct.
	 * Answered once per request: the switcher's product list cannot change within one.
	 *
	 * @return bool
	 */
	public static function checkout_sells_a_bundle() {
		static $answer = null;
		if ( null !== $answer ) {
			return $answer;
		}
		$answer = false;
		foreach ( self::checkout_product_ids() as $id ) {
			if ( null !== Bundle_Store::active_for_product( $id ) || null !== Bundle_Store::active_for_product( $id, Bundle_Store::SLOT_FBT ) ) {
				$answer = true;
				break;
			}
		}

		return $answer;
	}

	/**
	 * Whether this checkout can draw a bundle widget: one of its own rows sells a
	 * bundle.
	 *
	 * A cart line alone cannot: its quick view is no stored row, so
	 * open_quick_view() takes the widget out.
	 *
	 * @return bool
	 */
	public static function checkout_draws_widget() {
		static $answer = null;
		if ( null !== $answer ) {
			return $answer;
		}
		$answer = false;
		foreach ( self::checkout_product_ids( false ) as $id ) {
			if ( null !== Bundle_Store::active_for_product( $id ) || null !== Bundle_Store::active_for_product( $id, Bundle_Store::SLOT_FBT ) ) {
				$answer = true;

				return $answer;
			}
		}
		return $answer;
	}

	/**
	 * Product ids this checkout may show: its own rows, plus whatever the cart holds.
	 *
	 * @param bool $with_cart Whether to add the cart's products to the page's rows.
	 * @return array<int,int>
	 */
	public static function checkout_product_ids( $with_cart = true ) {
		$ids = array();
		$page_id = get_queried_object_id();
		if ( ! $page_id && class_exists( '\\WFACP_Common' ) ) {
			$page_id = absint( \WFACP_Common::get_id() );
		}
		if ( $page_id && class_exists( '\\WFACP_Common' ) ) {
			foreach ( (array) \WFACP_Common::get_page_product( $page_id ) as $row ) {
				$id = is_array( $row ) ? absint( $row['id'] ?? 0 ) : 0;
				if ( $id ) {
					$ids[ $id ] = $id;
				}
				// A pack row's container carries no bundle; the sheets and the quote
				// endpoint belong to the anchor it stands for.
				$meta   = ( is_array( $row ) && isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ) ? $row['products_meta_data'][1] : array();
				$anchor = absint( $meta[ self::ANCHOR_KEY ] ?? 0 );
				if ( $anchor ) {
					$ids[ $anchor ] = $anchor;
				}
			}
		}
		if ( $with_cart && function_exists( 'WC' ) && ! empty( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $ci ) {
				$id = absint( $ci['product_id'] ?? 0 );
				if ( $id ) {
					$ids[ $id ] = $id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Is this cart line one the Aero Checkout page put there?
	 *
	 * @param array $cart_item Cart item data.
	 * @return bool
	 */
	public static function owns_line( $cart_item ) {
		return is_array( $cart_item ) && ! empty( $cart_item['_wfacp_product'] );
	}

	/**
	 * Offer each of a searched product's bundle offers as its own search result.
	 *
	 * The row keeps the real product id, which the consuming endpoint resolves with
	 * wc_get_product(). The offer rides `product_meta_data`, posted back verbatim.
	 *
	 * @param array $products Search results.
	 * @return array
	 */
	public function append_offer_rows( $products ) {
		if ( ! is_array( $products ) || empty( $products ) || ! self::is_checkout_search() ) {
			return $products;
		}

		$rows     = array();
		$seen     = array();
		$replaced = array();
		$symbol   = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

		foreach ( $products as $row ) {
			// Someone else's synthesised row; expanding those would multiply the lists.
			if ( ! empty( $row['product_meta_data'] ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( ! $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;

			$product = wc_get_product( $id );
			// A variation's own row: the parent's offers already cover it.
			if ( ! $product instanceof \WC_Product || $product->get_parent_id() ) {
				continue;
			}
			$set = Bundle_Store::active_for_product( $id, Bundle_Store::SLOT_FBT );
			if ( null !== $set ) {
				$rows = array_merge( $rows, self::set_rows( $row, $product, $set, $symbol ) );
			}

			$bundle = self::fb_bundle_for( $product );
			if ( null !== $bundle ) {
				$pack = self::bundle_row( $row, $product, $bundle, $symbol );
				// Nothing synthesised: leave the bare product row as the only result.
				if ( array() === $pack ) {
					continue;
				}
				$single = self::single_row( $row, $product, $bundle, $symbol );
				if ( array() !== $single ) {
					$rows[] = $single;
				}
				$rows[]        = $pack;
				$replaced[ $id ] = true;
				continue;
			}

			/**
			 * Filters the search results a product without a fixed bundle adds.
			 *
			 * @since 1.0.0
			 *
			 * @param array[]     $found   Results to add, built with search_row().
			 * @param array       $row     Row the product's own result was built from.
			 * @param \WC_Product $product Product searched.
			 * @param string      $symbol  Currency symbol, already decoded.
			 */
			$rows = array_merge( $rows, array_values( array_filter( (array) apply_filters( 'wffn_bundles_aero_search_rows', array(), $row, $product, $symbol ), 'is_array' ) ) );
		}

		if ( empty( $rows ) ) {
			return $products;
		}

		// A fixed bundle names both offers, so the bare product row would be a third,
		// unnamed duplicate. Other plugins' rows carry `product_meta_data`.
		if ( ! empty( $replaced ) ) {
			$kept = array();
			foreach ( $products as $row ) {
				if ( empty( $row['product_meta_data'] ) && isset( $replaced[ absint( $row['id'] ?? 0 ) ] ) ) {
					continue;
				}
				$kept[] = $row;
			}
			$products = $kept;
		}

		return array_merge( $products, $rows );
	}

	/**
	 * A bought-together set as search results: the set.
	 *
	 * @param array       $row      Row the product's own result was built from.
	 * @param \WC_Product $product  The set's host.
	 * @param array       $bundle Resolved set bundle.
	 * @param string      $symbol   Currency symbol, already decoded.
	 * @return array[]
	 */
	private static function set_rows( array $row, \WC_Product $product, array $bundle, $symbol ) {
		$meta = array(
			self::BUNDLE_KEY => (string) ( $bundle['id'] ?? '' ),
			self::OFFER_KEY    => Bundle_Store::FBT_OFFER,
		);
		$set  = self::set_meta_figures( $meta, $product );
		if ( null === $set ) {
			return array();
		}
		return array( self::search_row( $row, $set['title'], $set['final'], $set['final_max'], $symbol, $meta ) );
	}

	/**
	 * A set row's title and figures, from its stored meta: the opening ticks.
	 * A variable host is priced from its
	 * cheapest to its dearest variation.
	 *
	 * @param array       $meta    Row meta: bundle and offer.
	 * @param \WC_Product $product The set's host.
	 * @return array|null { title, regular, final, regular_max, final_max }
	 */
	public static function set_meta_figures( array $meta, \WC_Product $product ) {
		if ( Bundle_Store::FBT_OFFER !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
			return null;
		}
		$bundle = Bundle_Store::get_config( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) );
		if ( ! is_array( $bundle ) || Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER !== (int) ( $bundle['discountType'] ?? 0 ) || ! Bundle_Store::is_active_now( $bundle ) ) {
			return null;
		}
		$figures = array();
		foreach ( self::set_hosts( $product ) as $host ) {
			$priced = self::set_figures_for( $bundle, $host );
			if ( null !== $priced ) {
				$figures[] = $priced;
			}
		}
		if ( empty( $figures ) ) {
			return null;
		}
		$low  = $figures[0];
		$high  = $figures[ count( $figures ) - 1 ];
		$title = rawurldecode( $product->get_title() ) . ' - ' . $low['label'];
		return array(
			'title'       => $title,
			'regular'     => $low['regular'],
			'final'       => $low['final'],
			'regular_max' => max( $low['regular'], $high['regular'] ),
			'final_max'   => max( $low['final'], $high['final'] ),
		);
	}

	/**
	 * The host the set is priced on: the product itself, or a variable product's
	 * cheapest and dearest purchasable variations.
	 *
	 * @param \WC_Product $product The set's host.
	 * @return \WC_Product[]
	 */
	private static function set_hosts( \WC_Product $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return array( $product );
		}
		$priced = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child instanceof \WC_Product && $child->is_purchasable() && $child->is_in_stock() ) {
				$priced[] = array( Layer::raw_base_price( $child ), $child );
			}
		}
		if ( empty( $priced ) ) {
			return array();
		}
		usort(
			$priced,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$low  = $priced[0][1];
		$high = $priced[ count( $priced ) - 1 ][1];

		return $low === $high ? array( $low ) : array( $low, $high );
	}

	/**
	 * One host's set: its opening ticks at set prices.
	 *
	 * @param array       $bundle The set's bundle.
	 * @param \WC_Product $host     The host, or one of its variations.
	 * @return array|null { label, regular, final }
	 */
	private static function set_figures_for( array $bundle, \WC_Product $host ) {
		$members = Cart::fbt_set_members( $bundle, $host, null );
		if ( count( $members ) < 2 ) {
			return null;
		}
		$regular = 0.0;
		$final   = 0.0;
		foreach ( $members as $m ) {
			$regular += (float) $m['unit'];
			$final   += (float) $m['pricedUnit'];
		}

		return $final > 0 ? array( 'label' => (string) ( $members[0]['label'] ?? '' ), 'regular' => $regular, 'final' => $final ) : null;
	}

	/**
	 * A carted set row's price data, read off the whole set rather than its host line.
	 *
	 * @param array       $price_data    { regular_org, price, quantity } of the host line.
	 * @param \WC_Product $pro           The row's product.
	 * @param string      $cart_item_key Cart item key.
	 * @param array       $product_data  Stored row.
	 * @param array       $cart_item     Cart item.
	 * @return array
	 */
	public function set_row_price_data( $price_data, $pro, $cart_item_key = '', $product_data = array(), $cart_item = array() ) {
		if ( ! is_array( $price_data ) || ! is_array( $product_data ) || ! $pro instanceof \WC_Product ) {
			return $price_data;
		}
		if ( 'fbt_host' !== (string) ( $cart_item['wffn_bundles_bundle']['role'] ?? '' ) ) {
			return $price_data;
		}
		$set = self::set_row_figures( $product_data, $pro );
		if ( null === $set ) {
			return $price_data;
		}
		$price_data['regular_org'] = (float) $set['regular'];
		$price_data['price']       = (float) $set['final'];

		return $price_data;
	}

	/**
	 * A set row's name and what it costs, or null for any other row.
	 *
	 * A carted row is read off its own lines; an uncarted one prices its opening ticks.
	 *
	 * @param array       $product_data Stored row.
	 * @param \WC_Product $pro          The row's product.
	 * @return array|null { name, regular, final }
	 */
	private static function set_row_figures( array $product_data, \WC_Product $pro ) {
		$meta = isset( $product_data['products_meta_data'][1] ) && is_array( $product_data['products_meta_data'][1] ) ? $product_data['products_meta_data'][1] : array();
		$set  = self::set_meta_figures( $meta, $pro );
		if ( null === $set ) {
			return null;
		}
		$host = self::row_host_key( (string) ( $product_data['item_key'] ?? '' ) );
		if ( '' !== $host ) {
			$group          = (string) ( WC()->cart->get_cart_item( $host )['wffn_bundles_bundle']['group'] ?? '' );
			$set['regular'] = 0.0;
			$set['final']   = 0.0;
			foreach ( WC()->cart->get_cart() as $line ) {
				if ( (string) ( $line['wffn_bundles_bundle']['group'] ?? '' ) !== $group ) {
					continue;
				}
				$qty             = max( 1, (int) ( $line['quantity'] ?? 1 ) );
				$set['regular'] += (float) ( $line['wffn_bundles_bundle']['unit'] ?? 0 ) * $qty;
				$set['final']   += isset( $line['line_total'] ) ? (float) $line['line_total'] : Cart::stored_unit( $line ) * $qty;
			}
		}

		return $set['final'] > 0 ? array( 'name' => $set['title'], 'regular' => $set['regular'], 'final' => $set['final'], 'final_max' => '' !== $host ? $set['final'] : $set['final_max'] ) : null;
	}

	/**
	 * The "just this product" offer of a fixed-bundle, as a search result.
	 *
	 * Empty when the bundle does not offer one: `fixedBundleShowSingle` is what
	 * the product page tests before drawing the plain radio.
	 *
	 * @param array       $row      Row the product's own result was built from.
	 * @param \WC_Product $product  Product the bundle is attached to.
	 * @param array       $bundle Resolved fixed-bundle.
	 * @param string      $symbol   Currency symbol, already decoded.
	 * @return array Empty when the bundle offers no single buy.
	 */
	private static function single_row( array $row, \WC_Product $product, array $bundle, $symbol ) {
		$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		if ( empty( $dc['fixedBundleShowSingle'] ) ) {
			return array();
		}
		list( $min, $max ) = self::price_range( $product );
		if ( $min <= 0 ) {
			return array();
		}
		$label = trim( Fixed_Bundle::default_text( $dc['fixedBundleSingleTitle'] ?? '' ) );
		$title = rawurldecode( $product->get_title() );

		return self::search_row(
			$row,
			'' === $label ? $title : $title . ' - ' . $label,
			$min,
			$max,
			$symbol,
			array(
				self::BUNDLE_KEY => (string) ( $bundle['id'] ?? '' ),
				self::OFFER_KEY    => self::OFFER_SINGLE,
			)
		);
	}

	/**
	 * The whole fixed bundle as its own search result.
	 *
	 * Priced through the bundle's own member builder, so whole-bundle and
	 * per-member discount scopes both come out as the product page prices them.
	 *
	 * @param array       $row      Row the product's own result was built from.
	 * @param \WC_Product $product  Product the bundle is attached to.
	 * @param array       $bundle Resolved fixed-bundle.
	 * @param string      $symbol   Currency symbol, already decoded.
	 * @return array Empty when the pack does not resolve.
	 */
	private static function bundle_row( array $row, \WC_Product $product, array $bundle, $symbol ) {
		$members = Cart::fixed_bundle_members( $bundle, self::fb_picks( $bundle ) );
		if ( empty( $members ) ) {
			return array();
		}
		$total = 0.0;
		foreach ( $members as $m ) {
			$total += (float) ( $m['pricedUnit'] ?? 0 ) * max( 1, (int) ( $m['qty'] ?? 1 ) );
		}
		if ( $total <= 0 ) {
			return array();
		}
		$dc    = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$label = trim( Fixed_Bundle::default_text( $dc['fixedBundleTitle'] ?? '' ) );
		if ( '' === $label ) {
			$label = __( 'Bundle', 'funnel-builder' );
		}

		$container = Cart::container_product();
		if ( ! $container instanceof \WC_Product ) {
			return array();
		}
		$out = self::search_row(
			$row,
			rawurldecode( $product->get_title() ) . ' - ' . $label,
			$total,
			$total,
			$symbol,
			array(
				self::BUNDLE_KEY => (string) ( $bundle['id'] ?? '' ),
				self::OFFER_KEY    => self::OFFER_BUNDLE,
				self::ANCHOR_KEY   => (string) $product->get_id(),
			)
		);
		// The row sells the container, not the bundle's anchor: the anchor is
		// usually not one of the pack's members.
		$out['id']   = $container->get_id();
		$out['type'] = $container->get_type();

		return $out;
	}

	/**
	 * One synthesised search result, priced as a range when the product is one.
	 *
	 * @param array  $row    Row the product's own result was built from.
	 * @param string $label  What the result is called.
	 * @param float  $price  Cheapest total this row sells for.
	 * @param float  $max    Dearest total this row sells for.
	 * @param string $symbol Currency symbol, already decoded.
	 * @param array  $meta   Keys the admin app posts back verbatim.
	 * @return array
	 */
	public static function search_row( array $row, $label, $price, $max, $symbol, array $meta ) {
		$shown = $symbol . number_format( (float) $price, 2 );
		if ( (float) $max > (float) $price ) {
			$shown .= ' - ' . $symbol . number_format( (float) $max, 2 );
		}

		return array_merge(
			$row,
			array(
				'product'           => $label,
				'product_attribute' => '',
				'product_price'     => $shown,
				'currency_symbol'   => $symbol,
				'product_meta_data' => $meta,
			)
		);
	}

	/**
	 * Name the row after the offer it sells, and seed its quantity from that offer.
	 *
	 * Quantity is only seeded: the cart prices the line at whatever its own
	 * quantity earns.
	 *
	 * @param array       $row     Row being created.
	 * @param \WC_Product $product Product the row sells.
	 * @return array
	 */
	public function describe_row( $row, $product ) {
		if ( ! is_array( $row ) || ! $product instanceof \WC_Product ) {
			return $row;
		}
		$row    = self::lock_bundle_discount( $row );
		$bundle = self::row_bundle( $row );
		if ( array() !== $bundle ) {
			$row['title']    = $bundle['label'];
			$row['quantity'] = 1;

			// The row's own product is the shared container, which carries no image.
			$meta   = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
			$anchor = wc_get_product( absint( $meta[ self::ANCHOR_KEY ] ?? 0 ) );
			if ( $anchor instanceof \WC_Product ) {
				$thumb = wp_get_attachment_thumb_url( $anchor->get_image_id() );
				if ( $thumb ) {
					$row['image'] = $thumb;
				}
			}

			return $row;
		}
		$single = self::row_single_label( $row, $product );
		if ( '' !== $single ) {
			$row['title']    = $single;
			$row['quantity'] = 1;

			return self::seed_default_variation( $row, $product );
		}
		$offer = self::row_offer( $row, $product );
		if ( null === $offer ) {
			return $row;
		}

		$row['title']    = ( isset( $row['title'] ) ? $row['title'] : $product->get_title() ) . ' - ' . (string) ( $offer['name'] ?? '' );
		$row['quantity'] = max( 1, (int) ( $offer['quantity'] ?? 1 ) );

		return self::seed_default_variation( $row, $product );
	}

	/**
	 * Point a pack row's edit link at the product the bundle hangs off.
	 *
	 * A DOM rewrite only: the field the link is built from is what the save path
	 * re-keys the stored row by.
	 *
	 * @return void
	 */
	public function enqueue_admin_script() {
		$page = isset( $GLOBALS['plugin_page'] ) ? sanitize_key( (string) $GLOBALS['plugin_page'] ) : '';
		if ( 'bwf' !== $page ) {
			return;
		}
		$packs = self::admin_pack_targets();
		if ( array() === $packs ) {
			return;
		}
		$rel = 'assets/compat/funnel-builder-admin.js';
		if ( ! file_exists( WFFN_BUNDLES_DIR . '/' . $rel ) ) {
			return;
		}
		$mtime = filemtime( WFFN_BUNDLES_DIR . '/' . $rel );
		$ver   = (string) ( $mtime ? $mtime : WFFN_BUNDLES_VERSION );
		wp_enqueue_script( 'wffn-bundles-compat-funnel-builder-admin', WFFN_BUNDLES_URL . $rel, array(), $ver, true );
		wp_localize_script(
			'wffn-bundles-compat-funnel-builder-admin',
			'wffnBundlesAeroAdmin',
			array(
				'packs'   => $packs,
				'editUrl' => admin_url( 'post.php?post=%d&action=edit' ),
			)
		);
	}

	/**
	 * Every pack row this site sells, as [ row title => anchor product id ].
	 *
	 * Keyed by title: the row's product id is the shared container, identical
	 * across every pack.
	 *
	 * @return array<string,int>
	 */
	private static function admin_pack_targets() {
		$out = array();
		// The same arguments get_posts() would add: no filters, no sticky posts, newest first.
		$ids = ( new \WP_Query(
			array(
				'post_type'           => 'wfacp_checkout',
				'post_status'         => 'any',
				'posts_per_page'      => 100,
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'suppress_filters'    => true,
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		) )->posts;
		// The stored rows already carry the pack tags; the read filter would load every product they name.
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $page_id ) {
			$rows = get_post_meta( $page_id, '_wfacp_selected_products', true );
			if ( is_array( $rows ) ) {
				self::collect_pack_targets( $rows, $out );
			}
		}

		return $out;
	}

	/**
	 * Add every pack row in `$rows` to `$out`, keyed by its title.
	 *
	 * @param array $rows Checkout rows, each carrying `products_meta_data[1]` and `title`.
	 * @param array $out  Accumulator, passed by reference.
	 * @return void
	 */
	private static function collect_pack_targets( array $rows, array &$out ) {
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
			if ( self::OFFER_BUNDLE !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
				continue;
			}
			$anchor = absint( $meta[ self::ANCHOR_KEY ] ?? 0 );
			$title  = trim( (string) ( $row['title'] ?? '' ) );
			if ( $anchor && '' !== $title ) {
				$out[ $title ] = $anchor;
			}
		}
	}

	/**
	 * Show the pack, not the placeholder, in the checkout's product list.
	 *
	 * The admin list rebuilds every row's image and price from the product the row
	 * sells, and a pack row's container has neither.
	 *
	 * @param array $row Row as the admin app will receive it.
	 * @return array
	 */
	public function describe_admin_row( $row ) {
		if ( ! is_array( $row ) ) {
			return $row;
		}
		$row    = self::lock_bundle_discount( $row );
		$bundle = self::row_bundle( $row );
		if ( array() === $bundle ) {
			return self::describe_admin_row_offer( $row );
		}

		// The list rebuilds the title from the container too.
		$row['title'] = $bundle['label'];
		$row          = self::container_status( $row, isset( $row['key'] ) ? absint( $row['key'] ) : absint( $row['id'] ?? 0 ) );

		$offer = 0.0;
		foreach ( (array) ( $bundle['members'] ?? array() ) as $m ) {
			$offer += (float) ( $m['pricedUnit'] ?? 0 ) * max( 1, (int) ( $m['qty'] ?? 1 ) );
		}
		if ( $offer > 0 ) {
			// A bundle discount is not a WooCommerce sale, so the pack total is the
			// regular price and no sale line is set.
			$row['regular_price'] = round( $offer, wc_get_price_decimals() );
			$row['sale_price']    = 0;
			$row['is_on_sale']    = false;
		}

		$meta   = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		$anchor = wc_get_product( absint( $meta[ self::ANCHOR_KEY ] ?? 0 ) );
		if ( $anchor instanceof \WC_Product ) {
			$thumb = wp_get_attachment_thumb_url( $anchor->get_image_id() );
			if ( $thumb ) {
				$row['product_image'] = $thumb;
			}
		}

		return $row;
	}

	/**
	 * Name a saved offer or Fixed Bundle "single" row on redisplay.
	 *
	 * describe_row() already names these on save; the list rebuilds `title` from
	 * the row's plain product on every read (product_list()), so the offer suffix
	 * has to be reapplied here too.
	 *
	 * @param array $row Row as the admin app will receive it.
	 * @return array
	 */
	private static function describe_admin_row_offer( $row ) {
		// The list swaps `id` for the row's own unique key just before this filter
		// runs and leaves the real product id in `key`.
		$product_id = isset( $row['key'] ) ? absint( $row['key'] ) : absint( $row['id'] ?? 0 );
		$product    = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return $row;
		}
		$single = self::row_single_label( $row, $product );
		if ( '' !== $single ) {
			$row['title'] = $single;

			return $row;
		}
		$offer = self::row_offer( $row, $product );
		if ( null !== $offer ) {
			$row['title'] = ( isset( $row['title'] ) ? $row['title'] : $product->get_title() ) . ' - ' . (string) ( $offer['name'] ?? '' );

			// The list prices the row from one unit; show what the offer itself costs.
			$meta     = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
			$resolved = self::resolve_meta_row( $meta, $product );
			if ( null !== $resolved && (float) $resolved['regular'] > 0 ) {
				$decimals             = wc_get_price_decimals();
				$row['regular_price'] = round( (float) $resolved['regular'], $decimals );
				$on_sale              = (float) $resolved['final'] < (float) $resolved['regular'];
				$row['sale_price']    = $on_sale ? round( (float) $resolved['final'], $decimals ) : 0;
				$row['is_on_sale']    = $on_sale;
			}
		}

		return $row;
	}

	/**
	 * Lock the discount of a row that sells one of a bundle's offers: the bundle prices it.
	 *
	 * @param array $row Checkout row.
	 * @return array
	 */
	private static function lock_bundle_discount( array $row ) {
		return empty( $row['products_meta_data'][1][ self::OFFER_KEY ] ) ? $row : self::locked( $row, self::sells_one_bundle( $row ) );
	}

	/**
	 * Whether a row sells one whole bundle, so its quantity is always one.
	 *
	 * Any other offer, such as a tier, sells the units its own quantity holds.
	 *
	 * @param array $row Checkout row.
	 * @return bool
	 */
	private static function sells_one_bundle( array $row ) {
		$offer = (string) ( $row['products_meta_data'][1][ self::OFFER_KEY ] ?? '' );

		return in_array( $offer, array( self::OFFER_BUNDLE, self::OFFER_SINGLE, Bundle_Store::FBT_OFFER ), true );
	}

	/**
	 * A step editor row for the bundle container, reported as published.
	 *
	 * The container is private by design, so the editor's "Private" warning would be false.
	 *
	 * @param array $row        Row as the step editor receives it.
	 * @param int   $product_id Product the row sells.
	 * @return array
	 */
	public static function container_status( array $row, $product_id ) {
		$container = Cart::container_product();
		if ( $container && (int) $container->get_id() === (int) $product_id && isset( $row['product_status'] ) ) {
			$row['product_status'] = 'publish';
		}

		return $row;
	}

	/**
	 * A step editor row with its discount locked.
	 *
	 * @param array $row        Row as the step editor receives it.
	 * @param bool  $one_bundle Whether the row sells one bundle, so its quantity is locked to one too.
	 * @return array
	 */
	public static function locked( array $row, $one_bundle = true ) {
		$row[ self::LOCK_KEY ] = __( 'The bundle sets this price.', 'funnel-builder' );
		if ( $one_bundle ) {
			$row['quantity']        = 1;
			$row['quantity_locked'] = __( 'The bundle sets the quantity.', 'funnel-builder' );
		}

		return $row;
	}

	/**
	 * Seed the variation on offer rows authored before describe_row() did it.
	 *
	 * Aero runs this filter every time it reads a page's stored rows, so a row saved
	 * without a default is corrected on read. Only offer rows are touched.
	 *
	 * @param array $products Stored rows, keyed by their unique id.
	 * @return array
	 */
	public function backfill_row_variations( $products ) {
		if ( ! is_array( $products ) ) {
			return $products;
		}
		// One query set for every row's product, not one per row.
		$ids = array();
		foreach ( $products as $row ) {
			$ids[] = is_array( $row ) ? absint( $row['id'] ?? 0 ) : 0;
		}
		$ids = array_filter( $ids );
		if ( count( $ids ) > 1 ) {
			_prime_post_caches( array_unique( $ids ), true, true );
		}
		foreach ( $products as $key => $row ) {
			if ( ! is_array( $row ) || ! empty( $row['default_variation'] ) ) {
				continue;
			}
			$product = wc_get_product( absint( $row['id'] ?? 0 ) );
			// Only a variable product is seeded, so the offer lookup is skipped for the rest.
			if ( ! $product instanceof \WC_Product || ! class_exists( '\\WFACP_Common' ) || ! in_array( $product->get_type(), (array) \WFACP_Common::get_variable_product_type(), true ) ) {
				continue;
			}
			if ( null === self::row_offer( $row, $product ) ) {
				continue;
			}
			$products[ $key ] = self::seed_default_variation( $row, $product );
		}

		return $products;
	}

	/**
	 * Drop the row's own discount from every row that sells a bundle's offer: the bundle prices it.
	 *
	 * @param array $products Stored rows, keyed by their unique id.
	 * @return array
	 */
	public function drop_bundle_discounts( $products ) {
		if ( ! is_array( $products ) ) {
			return $products;
		}
		foreach ( $products as $key => $row ) {
			if ( is_array( $row ) && ! empty( $row['products_meta_data'][1][ self::OFFER_KEY ] ) ) {
				$products[ $key ]['discount_amount'] = 0;
				if ( self::sells_one_bundle( $row ) ) {
					$products[ $key ]['quantity'] = 1;
				}
			}
		}

		return $products;
	}

	/**
	 * Give a variable row the variation Aero would have picked for it.
	 *
	 * The app's add-product endpoint sets `variable` and a price string but no
	 * `default_variation`, where every other Aero row-building path calls
	 * get_default_variation(). The shopper can still change it on the checkout.
	 *
	 * @param array       $row     Row being created.
	 * @param \WC_Product $product Product the row sells.
	 * @return array
	 */
	private static function seed_default_variation( array $row, \WC_Product $product ) {
		if ( isset( $row['default_variation'] ) && absint( $row['default_variation'] ) ) {
			return $row;
		}
		if ( ! class_exists( '\\WFACP_Common' ) || ! in_array( $product->get_type(), (array) \WFACP_Common::get_variable_product_type(), true ) ) {
			return $row;
		}
		$default = (array) \WFACP_Common::get_default_variation( $product );
		if ( empty( $default['variation_id'] ) ) {
			return $row;
		}

		$row['variable']               = 'yes';
		$row['default_variation']      = absint( $default['variation_id'] );
		$row['default_variation_attr'] = isset( $default['attributes'] ) ? $default['attributes'] : array();

		return $row;
	}

	/**
	 * Tag an Aero Checkout line with the pack or set its row was built from, so the
	 * cart prices and attributes it exactly like one chosen on the product page.
	 *
	 * @param array $data         Cart item data being built.
	 * @param int   $product_id   Product being added.
	 * @param int   $variation_id Variation being added, if any.
	 * @param int   $quantity     Quantity being added.
	 * @return array
	 */
	public function tag_line( $data, $product_id, $variation_id = 0, $quantity = 1 ) {
		if ( ! self::owns_line( $data ) || isset( $data['wffn_bundles_bundle'] ) ) {
			return $data;
		}
		$row = isset( $data['_wfacp_options'] ) && is_array( $data['_wfacp_options'] ) ? $data['_wfacp_options'] : array();
		if ( empty( $row ) ) {
			return $data;
		}
		// The switcher posts nothing, so the row riding this line declares the pack.
		// Built here so only the pack's own line becomes a container.
		if ( array() === self::$pending_bundle && ! self::prepare_bundle( $row ) ) {
			self::prepare_set( $row, wc_get_product( $variation_id ? $variation_id : $product_id ) );
		}

		// A fixed-bundle row's container resolves no bundle of its own.
		if ( array() !== self::$pending_bundle ) {
			// The stock gate the shop's own add runs, here because every row add
			// (page load, switch, Aero's add) reaches it once the cart is final.
			// WooCommerce catches the throw, shows the reason and adds nothing.
			$refusal = self::pending_unavailable( $quantity );
			if ( '' !== $refusal ) {
				self::$pending_bundle = array();
				self::$pending_group  = '';
				throw new \Exception( esc_html( $refusal ) );
			}
			$host = self::$pending_bundle['host'] ?? null;
			$data['wffn_bundles_bundle'] = is_array( $host )
				? array_merge(
					self::$pending_bundle['meta'],
					array(
						'role'       => 'fbt_host',
						'unit'       => (float) $host['unit'],
						'pricedUnit' => (float) $host['pricedUnit'],
					)
				)
				: self::container_bundle_data();
			Discount_Layer::claim_line( $data );
		}

		return $data;
	}

	/**
	 * Why the pack or set in flight cannot be sold, or '' when every product in it can.
	 *
	 * The same gate Cart::add_bundle() runs (Cart::validate_stock()): each product
	 * purchasable, in stock and with enough stock for what the row adds, counting
	 * what the cart already holds.
	 *
	 * @param int $quantity Quantity of the row's own line.
	 * @return string Translated reason, or ''.
	 */
	private static function pending_unavailable( $quantity ) {
		$b       = self::$pending_bundle;
		$members = (array) ( $b['members'] ?? array() );
		// A set's host and companions each go in at the row's quantity; a pack's
		// children at their own (add_bundle_children()).
		if ( is_array( $b['host'] ?? null ) ) {
			$members = array_map(
				static function ( $m ) use ( $quantity ) {
					return array_merge( (array) $m, array( 'qty' => max( 1, (int) $quantity ) ) );
				},
				array_merge( array( $b['host'] ), $members )
			);
		}
		$code   = '';
		$detail = '';
		if ( Cart::validate_stock( $members, $code, $detail ) ) {
			return '';
		}

		return '' !== (string) $detail ? (string) $detail : __( 'An item in this bundle is out of stock, so it can’t be added.', 'funnel-builder' );
	}

	/**
	 * Take a row's lines back out of the cart when one of them could not be added.
	 *
	 * Removed directly rather than through remove_cart_item(): the lines were placed
	 * in this same add and never shown, so there is nothing to undo or announce.
	 *
	 * @param string[] $keys Cart keys this add placed, the row's own line among them.
	 * @return void
	 */
	private static function roll_back_row( array $keys ) {
		foreach ( $keys as $key ) {
			unset( WC()->cart->cart_contents[ (string) $key ] );
		}
	}

	/**
	 * Name a switcher row after the offer it sells.
	 *
	 * Aero's own test in WFACP_Common_Helper::get_product_switcher_item_title()
	 * never passes for a row we authored, so the name falls back to the product's,
	 * which for a pack is the shared container placeholder.
	 *
	 * Recomposed from the row's bundle meta rather than read back from `title`:
	 * rows authored before describe_row() carry the bare product name.
	 *
	 * @param string      $name              Title Aero resolved.
	 * @param array|null  $cart_item         Cart item, null for an uncarted row.
	 * @param string      $cart_item_key     Cart item key, empty until the row is added.
	 * @param \WC_Product $pro               Product the row sells.
	 * @param array       $switcher_settings Product switcher settings.
	 * @param array       $product_data      Stored row.
	 * @return string
	 */
	public function switcher_row_title( $name, $cart_item, $cart_item_key, $pro, $switcher_settings, $product_data ) {
		if ( ! is_array( $product_data ) || ! $pro instanceof \WC_Product ) {
			return $name;
		}
		// Aero's own test for a row the merchant renamed by hand, which wins.
		$key = (string) ( $product_data['item_key'] ?? '' );
		$row = ( is_array( $switcher_settings ) && is_array( $switcher_settings['products'][ $key ] ?? null ) )
			? $switcher_settings['products'][ $key ]
			: array();
		if ( '' !== (string) ( $row['title'] ?? '' ) && ( $row['title'] ?? '' ) !== ( $row['old_title'] ?? '' ) ) {
			return $name;
		}

		// Aero prints the returned title as HTML; our labels are plain text.
		$bundle = self::row_bundle( $product_data );
		if ( array() !== $bundle ) {
			return esc_html( $bundle['label'] );
		}

		$set = self::set_row_figures( $product_data, $pro );
		if ( null !== $set ) {
			return esc_html( $set['name'] );
		}

		$single = self::row_single_label( $product_data, $pro );
		if ( '' !== $single ) {
			return esc_html( $single );
		}

		$offer      = self::row_offer( $product_data, $pro );
		$offer_name = null === $offer ? '' : trim( (string) ( $offer['name'] ?? '' ) );

		return '' === $offer_name ? $name : esc_html( $pro->get_title() . ' - ' . $offer_name );
	}

	/**
	 * Record what the switcher row about to render sells.
	 *
	 * The image column prints before the title, so the pack's figures cannot wait
	 * for switcher_row_title(). Never changes the value it is given.
	 *
	 * @param bool        $hide         Whether Aero should skip this row.
	 * @param array       $product_data Stored row.
	 * @param \WC_Product $pro          Product the row sells.
	 * @return bool
	 */
	public function open_row_state( $hide, $product_data = array(), $pro = null ) {
		self::$row_pack_total  = 0.0;
		self::$row_pack_extras = 0.0;
		self::$row_anchor_img  = 0;
		self::$row_product_id = $pro instanceof \WC_Product ? $pro->get_id() : 0;

		if ( ! is_array( $product_data ) ) {
			return $hide;
		}
		$bundle = self::row_bundle( $product_data );
		if ( array() === $bundle ) {
			return $hide;
		}
		self::$row_pack_total  = self::row_pack_charge( $bundle, $product_data );
		self::$row_pack_extras = self::$row_pack_total - self::row_pack_base( $bundle, $product_data );

		$meta   = isset( $product_data['products_meta_data'][1] ) && is_array( $product_data['products_meta_data'][1] ) ? $product_data['products_meta_data'][1] : array();
		$anchor = wc_get_product( absint( $meta[ self::ANCHOR_KEY ] ?? 0 ) );
		if ( $anchor instanceof \WC_Product ) {
			self::$row_anchor_img = absint( $anchor->get_image_id() );
		}

		return $hide;
	}

	/**
	 * Record what the switcher row about to render is already set to.
	 *
	 * Aero builds its "Choose an option" label at the top of the row template
	 * (product-switcher-row.php:95), before the hide_row filter open_row_state()
	 * rides at :148 — so the choice has to be read from the last hook that still
	 * runs ahead of the include. Returns its input untouched; it is read for the
	 * side effect only.
	 *
	 * @param array  $price_data    Price data Aero is assembling.
	 * @param mixed  $pro           Product the row sells.
	 * @param string $cart_item_key Cart item key; unused.
	 * @param array  $product_data  Stored row.
	 * @return array
	 */
	public function open_row_choice( $price_data, $pro = null, $cart_item_key = '', $product_data = array() ) {
		self::$row_choice        = self::row_choice_summary( $product_data, $pro );
		/**
		 * Filters whether a switcher row lists its own units, so its single resolved
		 * variation would mislabel them.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $lists        Whether the row lists its units. Default false.
		 * @param array $product_data Stored row.
		 */
		self::$row_units_present = is_array( $product_data ) && (bool) apply_filters( 'wffn_bundles_aero_row_lists_units', false, $product_data );

		return $price_data;
	}

	/**
	 * What a seeded variable row is already set to, as "Black, XS".
	 *
	 * Only for a row seed_default_variation() stamped a variation onto; every
	 * other variable row keeps Aero's own prompt untouched.
	 *
	 * @param mixed $product_data Stored row.
	 * @param mixed $pro          Product the row sells.
	 * @return string
	 */
	private static function row_choice_summary( $product_data, $pro ) {
		if ( ! is_array( $product_data ) || ! $pro instanceof \WC_Product ) {
			return '';
		}
		$variation_id = absint( $product_data['default_variation'] ?? 0 );
		if ( ! $variation_id || null === self::row_offer( $product_data, $pro ) ) {
			return '';
		}
		$variation = wc_get_product( $variation_id );

		return $variation instanceof \WC_Product ? (string) wc_get_formatted_variation( $variation, true, false ) : '';
	}

	/**
	 * Name the variation a row is set to, rather than inviting a choice already made.
	 *
	 * Aero prints "Choose an option" on every variable row, including one this
	 * adapter has already stamped a variation onto — so the row offered a choice
	 * while its line item carried attributes the shopper never picked. The link
	 * still opens the same quick view; it now says what it would be changing.
	 *
	 * A row listing its own units keeps Aero's plain prompt instead: its single
	 * resolved variation names only the first unit.
	 *
	 * @param string $text Aero's own prompt.
	 * @return string
	 */
	public function name_row_choice( $text ) {
		if ( self::$row_units_present ) {
			return __( 'Choose an option', 'funnel-builder' );
		}

		// class-wfacp-common-helper.php:955 raises the same filter with the unchosen
		// attribute markup rather than a prompt; only a plain prompt is replaced.
		if ( '' === self::$row_choice || false !== strpos( (string) $text, '<' ) ) {
			return $text;
		}

		/* translators: %s: the variation the row is currently set to, e.g. "Black, XS". */
		return esc_html( sprintf( __( '%s - Change', 'funnel-builder' ), self::$row_choice ) );
	}

	/**
	 * Give a pack row its quantity stepper back.
	 *
	 * Aero hides the stepper for a product it reads as free (priority 100), and a
	 * pack row's container is zero-priced because the money sits on the line.
	 * Answered at 110, and only for a row that resolves to a priced pack.
	 *
	 * @param bool             $show         Whether Aero will draw the stepper.
	 * @param array            $product_data Stored row.
	 * @param \WC_Product|null $pro          Product the row sells.
	 * @return bool
	 */
	public function restore_pack_quantity( $show, $product_data = array(), $pro = null ) {
		$bundle = self::row_bundle( $product_data );

		return ( array() !== $bundle && (float) ( $bundle['total'] ?? 0 ) > 0 ) ? true : $show;
	}

	/**
	 * Lend the pack row the anchor's image.
	 *
	 * The shared container has no image of its own. Scoped to the one product the
	 * row is rendering, and only while that row is a pack.
	 *
	 * @param int         $id      Image id WooCommerce resolved.
	 * @param \WC_Product $product Product being asked.
	 * @return int
	 */
	public function pack_row_image( $id, $product = null ) {
		if ( ! self::$row_anchor_img || ! $product instanceof \WC_Product ) {
			return $id;
		}

		return $product->get_id() === self::$row_product_id ? self::$row_anchor_img : $id;
	}

	/**
	 * Show the target product's image on the pack's order-summary line.
	 *
	 * This filter is handed the product and not the line, and the container has no
	 * image. The container is shared, so the line is found in the cart.
	 *
	 * @param string      $html    Thumbnail markup Aero built.
	 * @param \WC_Product $product Product the line sells.
	 * @return string
	 */
	public function cart_container_image( $html, $product = null ) {
		if ( ! $product instanceof \WC_Product || ! function_exists( 'WC' ) || empty( WC()->cart ) ) {
			return $html;
		}
		$container = Cart::container_product();
		if ( ! $container instanceof \WC_Product || $product->get_id() !== $container->get_id() ) {
			return $html;
		}

		foreach ( WC()->cart->get_cart() as $ci ) {
			$b    = isset( $ci['wffn_bundles_bundle'] ) && is_array( $ci['wffn_bundles_bundle'] ) ? $ci['wffn_bundles_bundle'] : array();
			$role = (string) ( $b['role'] ?? '' );
			if ( 'heading' === $role && '' !== (string) ( $b['thumbSrc'] ?? '' ) ) {
				return '<img src="' . esc_url( (string) $b['thumbSrc'] ) . '" alt="" width="100" height="100" />';
			}
			if ( 'container' !== $role ) {
				continue;
			}
			// The target product the pack was bought from, matching the switcher row.
			// Its image id is recorded on the line at add time, so the usual path needs
			// no product read at all; a line added before that is read the old way.
			$anchor_img = absint( $b['anchorImgId'] ?? 0 );
			if ( $anchor_img ) {
				return wp_get_attachment_image( $anchor_img, array( 100, 100 ) );
			}
			if ( ! array_key_exists( 'anchorImgId', $b ) ) {
				$anchor = wc_get_product( absint( $b['anchor'] ?? 0 ) );
				if ( $anchor instanceof \WC_Product && $anchor->get_image_id() ) {
					return $anchor->get_image( array( 100, 100 ) );
				}
			}
			$src = (string) ( $b['thumbSrc'] ?? '' );
			if ( '' !== $src ) {
				return '<img src="' . esc_url( $src ) . '" alt="" width="100" height="100" />';
			}
			$img = absint( $b['thumbImgId'] ?? 0 );
			if ( $img ) {
				return wp_get_attachment_image( $img, array( 100, 100 ) );
			}
			break;
		}

		return $html;
	}

	/**
	 * Print the pack's price ourselves, rather than after the container's own.
	 *
	 * A zero-priced container makes Aero take its `regular_org == 0` branch, which
	 * echoes get_price_html() and then the price filter, printing two figures. The
	 * row title renders first and records what the pack charges.
	 *
	 * @param bool        $show Whether Aero should print the price itself.
	 * @param \WC_Product $pro  Product the row sells.
	 * @return bool
	 */
	public function own_pack_price( $show, $pro = null ) {
		if ( self::$row_pack_total <= 0 || ! $pro instanceof \WC_Product ) {
			return $show;
		}

		// Tied to the row's own product: the switcher renders more than once a page.
		return $pro->get_id() === self::$row_product_id ? false : $show;
	}

	/**
	 * Whether a cart line is a heading or a member nested under one.
	 *
	 * @param array $cart_item Cart item.
	 * @return string 'heading', 'nested' or ''.
	 */
	private static function grouped_kind( $cart_item ) {
		$b = is_array( $cart_item['wffn_bundles_bundle'] ?? null ) ? $cart_item['wffn_bundles_bundle'] : array();
		if ( 'heading' === (string) ( $b['role'] ?? '' ) ) {
			return 'heading';
		}

		return Cart::is_nested( $b ) ? 'nested' : '';
	}

	/**
	 * A heading's zero price is replaced by the total of the picks under it.
	 *
	 * @param bool  $show      Whether Aero prints the row's price itself.
	 * @param array $cart_item Cart item.
	 * @return bool
	 */
	public function hide_heading_price( $show, $cart_item = array() ) {
		return 'heading' === self::grouped_kind( $cart_item ) ? false : $show;
	}

	/**
	 * Print the heading's total where Aero left its price empty.
	 *
	 * @param \WC_Product $product   Product on the row.
	 * @param array       $cart_item Cart item.
	 * @return void
	 */
	public function print_heading_price( $product, $cart_item = array() ) {
		if ( 'heading' === self::grouped_kind( $cart_item ) ) {
			echo wp_kses_post( wc_price( Cart::heading_total( $cart_item ) ) );
		}
	}

	/**
	 * A heading and its picks have no stepper: the box is one unit.
	 *
	 * @param bool  $show      Whether Aero draws the stepper.
	 * @param array $cart_item Cart item.
	 * @return bool
	 */
	public function hide_grouped_quantity( $show, $cart_item = array() ) {
		// A bought-together companion follows its host's quantity, as in the block cart.
		return '' === self::grouped_kind( $cart_item ) && ! Cart::fbt_companion_locked( $cart_item ) ? $show : false;
	}

	/**
	 * A pick or a Fixed Bundle child cannot be dropped alone; only the heading
	 * or the bundle's own line removes the group, as in the classic cart.
	 *
	 * @param bool   $enabled   Whether Aero draws the remove control.
	 * @param array  $cart_item Cart item.
	 * @param string $key       Cart item key.
	 * @return bool
	 */
	public function hide_nested_remove( $enabled, $cart_item = array(), $key = '' ) {
		$child = 'child' === (string) ( $cart_item['wffn_bundles_bundle']['role'] ?? '' );

		return $child || 'nested' === self::grouped_kind( $cart_item ) ? false : $enabled;
	}

	/**
	 * The pack total, in the column own_pack_price() emptied.
	 *
	 * @param \WC_Product|null $pro           Product on the row.
	 * @param string           $cart_item_key The row's cart line, '' when it is not in the cart.
	 * @return void
	 */
	public function print_pack_price( $pro = null, $cart_item_key = '' ) {
		if ( self::$row_pack_total <= 0 ) {
			return;
		}
		$total = self::$row_pack_total;
		$line  = '' !== (string) $cart_item_key && WC()->cart ? WC()->cart->get_cart_item( (string) $cart_item_key ) : array();
		// In the cart the pack costs what its line charges, e.g. a subscription plan's price.
		if ( isset( $line['data'] ) && $line['data'] instanceof \WC_Product ) {
			$total = (float) wc_get_price_to_display( $line['data'], array( 'qty' => max( 1, (int) $line['quantity'] ) ) ) + self::$row_pack_extras;
		}
		echo wp_kses_post( wc_price( $total ) );
	}

	/**
	 * A fixed-bundle single row's label, or '' when the row is not one.
	 *
	 * @param array       $row Stored row.
	 * @param \WC_Product $pro Product the row sells.
	 * @return string
	 */
	private static function row_single_label( array $row, \WC_Product $pro ) {
		$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		if ( self::OFFER_SINGLE !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
			return '';
		}
		$bundle = Bundle_Store::get_config( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) );
		if ( ! is_array( $bundle ) || Bundle_Store::TYPE_FIXED_BUNDLES !== (int) ( $bundle['discountType'] ?? 0 ) ) {
			return '';
		}
		$dc    = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$label = trim( Fixed_Bundle::default_text( $dc['fixedBundleSingleTitle'] ?? '' ) );

		return '' === $label ? $pro->get_title() : $pro->get_title() . ' - ' . $label;
	}

	/**
	 * Price a switcher row at what its offer sells for.
	 *
	 * The switcher prices an unselected row straight off the product, which reads as
	 * one unit at catalog price whatever the row's offer holds.
	 *
	 * @param string      $html         Price markup the template built.
	 * @param \WC_Product $pro          Row's product.
	 * @param array       $price_data   Resolved price data.
	 * @param array       $product_data Stored row.
	 * @return string
	 */
	public function switcher_price_html( $html, $pro, $price_data, $product_data ) {
		if ( ! is_array( $product_data ) || ! is_array( $price_data ) || ! $pro instanceof \WC_Product ) {
			return $html;
		}
		// A subscription product's row carries a recurring string this would flatten.
		if ( in_array( $pro->get_type(), (array) \WFACP_Common::get_subscription_product_type(), true ) ) {
			return $html;
		}
		$bundle = self::row_bundle( $product_data );
		if ( array() !== $bundle ) {
			// The container is zero-priced; the pack's price lives in the bundle.
			$pack = self::row_pack_charge( $bundle, $product_data );

			return $pack > 0 ? wc_price( $pack ) : $html;
		}
		$set = self::set_row_figures( $product_data, $pro );
		if ( null !== $set ) {
			// An unpicked variable host's set is still a range.
			if ( $set['final_max'] > $set['final'] ) {
				return wc_format_price_range( $set['final'], $set['final_max'] );
			}
			return $set['regular'] > $set['final'] ? wc_format_sale_price( $set['regular'], $set['final'] ) : wc_price( $set['final'] );
		}
		$offer = self::row_offer( $product_data, $pro );
		if ( null === $offer ) {
			return $html;
		}

		/**
		 * Filters the price markup of a checkout row selling an offer that is not a pack or a set.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $html         Price markup the template built.
		 * @param array       $offer        What `wffn_bundles_aero_row_offer` resolved for the row.
		 * @param \WC_Product $pro          Row's product.
		 * @param array       $price_data   Resolved price data.
		 * @param array       $product_data Stored row.
		 */
		return (string) apply_filters( 'wffn_bundles_aero_row_price_html', $html, $offer, $pro, $price_data, $product_data );
	}

	/**
	 * The fixed bundle a stored row sells, or an empty array.
	 *
	 * @param array $row Stored row.
	 * @return array { bundle, members, total, label } or empty.
	 */
	private static function row_bundle( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}
		$meta = isset( $row['products_meta_data'][1] ) && is_array( $row['products_meta_data'][1] ) ? $row['products_meta_data'][1] : array();
		if ( self::OFFER_BUNDLE !== (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
			return array();
		}
		// Many call sites resolve the same row per render, and each rebuild loads
		// every member. Keyed by the picks too, so a re-quote is never stale.
		static $memo = array();
		$key = (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) . '|' . (string) ( $row['item_key'] ?? $row['id'] ?? '' )
			. '|' . md5( (string) wp_json_encode( self::stored_picks( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) ) ) );
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}

		$bundle = Bundle_Store::get_config( (string) ( $meta[ self::BUNDLE_KEY ] ?? '' ) );
		if ( ! is_array( $bundle ) || Bundle_Store::TYPE_FIXED_BUNDLES !== (int) ( $bundle['discountType'] ?? 0 ) ) {
			$memo[ $key ] = array();

			return $memo[ $key ];
		}
		// A paused, ended or deleted bundle cannot still price a checkout row;
		// mirrors the add path's own gate.
		if ( ! Bundle_Store::is_active_now( $bundle ) ) {
			$memo[ $key ] = array();

			return $memo[ $key ];
		}
		$members = Cart::fixed_bundle_members( $bundle, self::fb_picks( $bundle ) );
		if ( empty( $members ) ) {
			$memo[ $key ] = array();

			return $memo[ $key ];
		}
		$total   = 0.0;
		$regular = 0.0;
		foreach ( $members as $m ) {
			$qty      = max( 1, (int) ( $m['qty'] ?? 1 ) );
			$total   += (float) ( $m['pricedUnit'] ?? 0 ) * $qty;
			$regular += (float) ( $m['unit'] ?? 0 ) * $qty;
		}
		$dc    = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$label = trim( Fixed_Bundle::default_text( $dc['fixedBundleTitle'] ?? '' ) );
		$title = wc_get_product( absint( $meta[ self::ANCHOR_KEY ] ?? 0 ) );

		$memo[ $key ] = array(
			'bundle' => $bundle,
			'members'  => $members,
			'total'    => $total,
			'regular'  => max( $total, $regular ),
			'label'    => ( $title instanceof \WC_Product ? $title->get_title() . ' - ' : '' ) . ( '' !== $label ? $label : __( 'Bundle', 'funnel-builder' ) ),
		);

		return $memo[ $key ];
	}

	/**
	 * The title and price a search-picked offer resolves to, from the
	 * `product_meta_data` append_offer_rows() stamped on its row.
	 *
	 * A surface whose own "add product" endpoint carries that meta no further
	 * (it is not every one of them — Aero's checkout repackages it into a
	 * stored row and call describe_row() themselves) needs the offer re-resolved
	 * from scratch, the same way it was first priced for the search result.
	 * A bare product's own row needs no resolving at all.
	 *
	 * @param array       $meta    { wffn_bundles_bundle_id, wffn_bundles_offer, wffn_bundles_anchor? }, as posted.
	 * @param \WC_Product $product Product the row was posted against.
	 * @return array{title:string,regular:float,final:float,image?:string,image_id?:int}|null Null when the meta does not resolve to either.
	 */
	public static function resolve_meta_row( array $meta, \WC_Product $product ) {
		if ( Bundle_Store::FBT_OFFER === (string) ( $meta[ self::OFFER_KEY ] ?? '' ) ) {
			return self::set_meta_figures( $meta, $product );
		}
		$row    = array( 'products_meta_data' => array( 1 => $meta ) );
		$bundle = self::row_bundle( $row );
		if ( array() !== $bundle ) {
			$resolved = array(
				'title'   => $bundle['label'],
				// What the members cost bought apart, so a host computing the saving sees it.
				'regular' => (float) $bundle['regular'],
				'final'   => (float) $bundle['total'],
			);

			// The row sells the shared container placeholder, which has no image of
			// its own; the anchor's is the one a merchant recognises the pack by.
			$anchor = wc_get_product( absint( $meta[ self::ANCHOR_KEY ] ?? 0 ) );
			if ( $anchor instanceof \WC_Product && $anchor->get_image_id() ) {
				$thumb = wp_get_attachment_thumb_url( $anchor->get_image_id() );
				if ( $thumb ) {
					$resolved['image']    = $thumb;
					$resolved['image_id'] = $anchor->get_image_id();
				}
			}

			return $resolved;
		}

		/**
		 * Filters the title and price a search-picked offer that is not a pack or a set resolves to.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null  $resolved { title, regular, final }, or null when the meta resolves to nothing.
		 * @param array       $meta     { wffn_bundles_bundle_id, wffn_bundles_offer }, as posted.
		 * @param \WC_Product $product  Product the row was posted against.
		 */
		$resolved = apply_filters( 'wffn_bundles_aero_resolve_meta_row', null, $meta, $product );

		return is_array( $resolved ) ? $resolved : null;
	}

	/**
	 * A product's cheapest and dearest purchasable unit price, base currency.
	 *
	 * A variable parent holds no price of its own, so its children answer for it.
	 *
	 * @param \WC_Product $product Product to price.
	 * @return float[] { min, max }
	 */
	public static function price_range( \WC_Product $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			$base = Layer::raw_base_price( $product );

			return array( $base, $base );
		}
		$min = null;
		$max = null;
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child instanceof \WC_Product || ! $child->is_purchasable() ) {
				continue;
			}
			$price = Layer::raw_base_price( $child );
			if ( $price <= 0 ) {
				continue;
			}
			$min = null === $min ? $price : min( $min, $price );
			$max = null === $max ? $price : max( $max, $price );
		}

		return array( (float) ( null === $min ? 0 : $min ), (float) ( null === $max ? 0 : $max ) );
	}

	/**
	 * The offer a stored row sells when it is not a pack or a set, or null.
	 *
	 * @param array $row     Stored row.
	 * @param mixed $product Product the row sells.
	 * @return array|null { bundle, offer, name, quantity }, plus whatever the answer adds.
	 */
	private static function row_offer( array $row, $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		/**
		 * Filters the offer a checkout row sells when it is not a pack or a set.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null  $offer   { bundle, offer, name, quantity }, or null when the row sells none.
		 * @param array       $row     Stored row.
		 * @param \WC_Product $product Product the row sells.
		 */
		$offer = apply_filters( 'wffn_bundles_aero_row_offer', null, $row, $product );

		return is_array( $offer ) && is_array( $offer['bundle'] ?? null ) && '' !== (string) ( $offer['offer'] ?? '' ) ? $offer : null;
	}

	/**
	 * The product's active fixed-bundle, or null.
	 *
	 * @param mixed $product Product to resolve from.
	 * @return array|null
	 */
	private static function fb_bundle_for( $product ) {
		return self::bundle_of( $product, Bundle_Store::TYPE_FIXED_BUNDLES );
	}

	/**
	 * The product's active bundle, when it is of the type asked for.
	 *
	 * @param mixed $product Product to resolve from; a variation reads its parent.
	 * @param int   $type    Bundle type the caller handles.
	 * @return array|null
	 */
	private static function bundle_of( $product, $type ) {
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}
		$parent   = $product->get_parent_id();
		$bundle = Bundle_Store::active_for_product( $parent ? $parent : $product->get_id() );
		if ( ! $bundle || (int) ( $bundle['discountType'] ?? 0 ) !== (int) $type ) {
			return null;
		}

		return $bundle;
	}

	/**
	 * A member's default attributes, as the display labels Cart::resolve_variation
	 * matches on.
	 *
	 * A checkout row is authored without anyone picking a variation, and
	 * members_fixed_bundle() rejects an unresolved member rather than guessing. The
	 * first purchasable variation is the fallback Aero applies to its own rows.
	 *
	 * @param \WC_Product $product Member product.
	 * @return array
	 */
	public static function member_default_attrs( $product ) {
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			return array();
		}
		$labels = array();
		foreach ( $product->get_attributes() as $attr ) {
			if ( is_object( $attr ) && $attr->get_variation() ) {
				$labels[ sanitize_title( (string) $attr->get_name() ) ] = wc_attribute_label( (string) $attr->get_name(), $product );
			}
		}
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child instanceof \WC_Product || ! $child->is_purchasable() ) {
				continue;
			}
			$out = array();
			foreach ( (array) $child->get_variation_attributes() as $key => $value ) {
				$slug  = sanitize_title( preg_replace( '/^attribute_/', '', (string) $key ) );
				$label = $labels[ $slug ] ?? '';
				if ( '' === $label ) {
					continue;
				}
				// An "Any <axis>" variation leaves that axis empty, which would never
				// complete a pick.
				$out[ $label ] = '' !== (string) $value ? (string) $value : self::first_axis_option( $product, $slug );
			}
			if ( count( $out ) === count( $labels ) && ! in_array( '', $out, true ) ) {
				return $out;
			}
		}

		return array();
	}

	/**
	 * The first option a variable product offers on one attribute axis.
	 *
	 * @param \WC_Product $product Variable product.
	 * @param string      $slug    Sanitized attribute name.
	 * @return string Option value, or '' when the axis offers none.
	 */
	private static function first_axis_option( $product, $slug ) {
		foreach ( (array) $product->get_variation_attributes() as $name => $options ) {
			if ( sanitize_title( (string) $name ) === $slug ) {
				$first = reset( $options );
				return false === $first ? '' : (string) $first;
			}
		}

		return '';
	}

	/**
	 * The members a pack row is priced and charged on.
	 *
	 * @param array $bundle Resolved bundle.
	 * @return array[] Rows from Cart::fixed_bundle_members(), empty when they do not resolve.
	 */
	public static function priced_pack_members( array $bundle ) {
		return Cart::fixed_bundle_members( $bundle, self::fb_picks( $bundle ) );
	}

	/**
	 * Default picks for every member of a fixed bundle, in the config's own order.
	 *
	 * @param array $bundle Resolved bundle.
	 * @return array[]
	 */
	private static function fb_picks( array $bundle ) {
		$dc    = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
		$picks = array();
		foreach ( array_values( (array) ( $dc['fixedBundleItems'] ?? array() ) ) as $i => $item ) {
			$id = absint( is_array( $item['product'] ?? null ) ? ( $item['product']['id'] ?? 0 ) : 0 );
			$picks[ $i ] = array( 'attributes' => $id ? self::member_default_attrs( wc_get_product( $id ) ) : array() );
		}

		// The switcher's update carries no bundle payload, so the session is the only
		// place the shopper's quick-view pick survives a rebuild.
		foreach ( self::stored_picks( (string) ( $bundle['id'] ?? '' ) ) as $i => $attrs ) {
			if ( isset( $picks[ $i ] ) ) {
				$picks[ $i ]['attributes'] = $attrs;
			}
		}

		return $picks;
	}

	/**
	 * Tell Aero which cart line a switcher row now owns.
	 *
	 * Aero decides whether a row is ticked from a session list whose `is_added_cart`
	 * holds the cart key the row created, so re-keying a line leaves it dangling.
	 *
	 * There is no filter for this, so the entry is rewritten in the shape and place
	 * Aero's own restore_cart_item writes it. Only this row's pointer is touched.
	 *
	 * @param int    $page_id  Checkout page id.
	 * @param string $item_key Aero's key for the row.
	 * @param string $cart_key The line the row now owns.
	 * @return void
	 */
	public static function point_row_at_line( $page_id, $item_key, $cart_key ) {
		if ( ! $cart_key || ! $page_id || ! function_exists( 'WC' ) || empty( WC()->session ) ) {
			return;
		}
		$field = 'wfacp_product_data_' . absint( $page_id );
		$list  = WC()->session->get( $field );
		if ( ! is_array( $list ) || ! isset( $list[ $item_key ] ) || ! is_array( $list[ $item_key ] ) ) {
			return;
		}
		$list[ $item_key ]['is_added_cart'] = (string) $cart_key;
		WC()->session->set( $field, $list );
	}

	/**
	 * Remember what the quick view was last quoting, per bundle.
	 *
	 * Aero's switcher rebuilds the cart from the stored row, which knows nothing of
	 * the member variations picked. The quote already receives them; this keeps them.
	 *
	 * Staged, not committed: a quote fires on every change inside the modal, so
	 * writing to PICKS_KEY here re-priced the row for a variation the shopper was
	 * still only looking at. commit_picks() promotes it.
	 *
	 * @param array $response Quote response, returned untouched.
	 * @param array $context  { bundle, type, offer, selection, totals }.
	 * @return array
	 */
	public function remember_row_picks( $response, $context = array() ) {
		if ( ! is_array( $context ) || ! function_exists( 'WC' ) || empty( WC()->session ) ) {
			return $response;
		}
		$bundle = is_array( $context['bundle'] ?? null ) ? $context['bundle'] : array();
		if ( Bundle_Store::TYPE_FIXED_BUNDLES !== (int) ( $bundle['discountType'] ?? 0 ) ) {
			return $response;
		}
		$id = (string) ( $bundle['id'] ?? '' );
		if ( '' === $id ) {
			return $response;
		}

		$items = array();
		$sel   = is_array( $context['selection'] ?? null ) ? $context['selection'] : array();
		foreach ( array_values( (array) ( $sel['items'] ?? array() ) ) as $i => $item ) {
			$attrs = ( is_array( $item ) && is_array( $item['attributes'] ?? null ) ) ? $item['attributes'] : array();
			$clean = array();
			foreach ( $attrs as $name => $value ) {
				$clean[ sanitize_text_field( (string) $name ) ] = sanitize_text_field( (string) $value );
			}
			if ( array() !== $clean ) {
				$items[ (int) $i ] = $clean;
			}
		}

		$all        = (array) WC()->session->get( self::PENDING_PICKS_KEY, array() );
		$all[ $id ] = $items;
		WC()->session->set( self::PENDING_PICKS_KEY, $all );

		return $response;
	}

	/**
	 * Promote what the quick view quoted into the picks the row is built from.
	 *
	 * Called from the two requests that mean the shopper committed: Aero's add
	 * and its switch. A modal closed any other way leaves the staged picks for
	 * ajax_discard_picks() to drop, so the row keeps what it already had.
	 *
	 * @return void
	 */
	private static function commit_picks() {
		if ( ! function_exists( 'WC' ) || empty( WC()->session ) ) {
			return;
		}
		$pending = (array) WC()->session->get( self::PENDING_PICKS_KEY, array() );
		if ( array() === $pending ) {
			return;
		}
		$all = (array) WC()->session->get( self::PICKS_KEY, array() );
		WC()->session->set( self::PICKS_KEY, array_replace( $all, $pending ) );
		WC()->session->set( self::PENDING_PICKS_KEY, array() );
	}

	/**
	 * Drop what a quick view quoted before it was closed without confirming.
	 *
	 * @return void
	 */
	public static function ajax_discard_picks() {
		Controller::verify();
		if ( function_exists( 'WC' ) && ! empty( WC()->session ) ) {
			WC()->session->set( self::PENDING_PICKS_KEY, array() );
		}

		wp_send_json_success( array( 'discarded' => true ) );
	}

	/**
	 * The remembered member picks for a bundle, as [ index => attributes ].
	 *
	 * @param string $bundle_id Bundle the pack belongs to.
	 * @return array
	 */
	private static function stored_picks( $bundle_id ) {
		if ( '' === $bundle_id || ! function_exists( 'WC' ) || empty( WC()->session ) ) {
			return array();
		}
		$all = (array) WC()->session->get( self::PICKS_KEY, array() );

		return is_array( $all[ $bundle_id ] ?? null ) ? $all[ $bundle_id ] : array();
	}

	/**
	 * Is this the checkout's product search?
	 *
	 * The same filter serves upsells.
	 *
	 * @return bool
	 */
	private static function is_checkout_search() {
		$type = Request_Context::raw_get( 'type' );
		$type = null === $type ? '' : sanitize_key( $type );
		// The shared REST search (class-wffn-rest-funnel-modules.php's product_search_variant()) names a One-Click Upsell offer "offer".
		return in_array( $type, array( '', 'checkout', 'offer' ), true );
	}

	/**
	 * One "removed. Undo?" line per removed pack, named by the bundle's title.
	 *
	 * Aero names the line from a fresh product just after this filter, so the
	 * placeholder's next name read is answered once with the title.
	 *
	 * @param bool  $skip Whether Aero should leave the line out.
	 * @param array $item The removed cart item.
	 * @return bool
	 */
	public function undo_pack_once( $skip, $item ) {
		remove_filter( 'woocommerce_product_get_name', array( $this, 'name_removed_pack' ), 10 );
		$b = is_array( $item ) && is_array( $item['wffn_bundles_bundle'] ?? null ) ? $item['wffn_bundles_bundle'] : null;
		if ( $skip || ! $b ) {
			return $skip;
		}
		$role = (string) ( $b['role'] ?? '' );
		if ( 'child' === $role || Cart::is_nested( $b ) ) {
			foreach ( (array) WC()->cart->removed_cart_contents as $removed ) {
				$rb = is_array( $removed['wffn_bundles_bundle'] ?? null ) ? $removed['wffn_bundles_bundle'] : array();
				// Undoing the pack's own line restores its members with it.
				if ( '' !== Cart::placeholder_title( $removed ) && (string) ( $rb['group'] ?? '' ) === (string) ( $b['group'] ?? '' ) ) {
					return true;
				}
			}
			return $skip;
		}
		$title = Cart::placeholder_title( $item );
		if ( '' !== $title ) {
			self::$removed_pack = array( (int) $item['product_id'], $title );
			add_filter( 'woocommerce_product_get_name', array( $this, 'name_removed_pack' ), 10, 2 );
		}
		return $skip;
	}

	/**
	 * Answer the removed pack's name read once, then step aside.
	 *
	 * @param string      $name    Product name.
	 * @param \WC_Product $product Product read.
	 * @return string
	 */
	public function name_removed_pack( $name, $product ) {
		if ( ! $product instanceof \WC_Product || $product->get_id() !== self::$removed_pack[0] ) {
			return $name;
		}
		remove_filter( 'woocommerce_product_get_name', array( $this, 'name_removed_pack' ), 10 );
		return esc_html( self::$removed_pack[1] );
	}
}
