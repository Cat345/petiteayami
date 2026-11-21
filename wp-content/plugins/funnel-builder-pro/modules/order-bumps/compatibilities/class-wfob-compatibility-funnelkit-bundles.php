<?php
/**
 * FunnelKit Bundles: sell a bundle from an order bump.
 *
 * @package WFOB
 */

use WFFN\Bundles\Includes\Compat\Currency\Layer as WFOB_Bundles_Currency;
use WFFN\Bundles\Includes\Compat\Discount\Layer as WFOB_Bundles_Discount_Layer;
use WFFN\Bundles\Includes\Compat\Render_Api as WFOB_Bundles_Render_Api;
use WFFN\Bundles\Includes\Frontend\Cart as WFOB_Bundles_Cart;
use WFFN\Bundles\Includes\Frontend\Product_Display as WFOB_Bundles_Product_Display;
use WFFN\Bundles\Includes\Skins\Assets as WFOB_Bundles_Assets;
use WFFN\Bundles\Includes\Skins\Frequently_Bought_Together as WFOB_Bundles_FBT;
use WFFN\Bundles\Includes\Skins\Registry as WFOB_Bundles_Registry;
use WFFN\Bundles\Includes\Storage\Bundle_Store as WFOB_Bundles_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_FunnelKit_Bundles' ) ) {
	/**
	 * Lets a bump product sell its bundle: a Bundle accordion in the bump editor's
	 * Style tab, then the bundle widget inside the bump and the bundle's lines.
	 *
	 * Settings are the bump's own design data, `bundle_<product key>_enable` and
	 * `bundle_<product key>_display`; a `product_wfob_` key would land on the Content tab.
	 */
	#[\AllowDynamicProperties]
	class WFOB_Compatibility_FunnelKit_Bundles {

		/** Cart item data key Bundles reads its line data from. */
		const LINE_KEY = 'wffn_bundles_bundle';

		/** Widget opens under the bump row. */
		const DISPLAY_INLINE = 'inline';

		/** Widget opens in the bump's quick view. */
		const DISPLAY_QUICK_VIEW = 'quick_view';

		/** Bump skins whose narrow cards cannot carry the widget inline. */
		const QUICK_VIEW_LAYOUTS = array( 'layout_12' );

		/** Row key the step editors' product table reads to lock a row's discount, holding the note it shows. */
		const LOCK_KEY = 'discount_locked';

		/** Bundles Pro's Quantity Breaks type id; the free plugin has no constant for it. */
		const QUANTITY_BREAKS = 1;

		/** Transient listing the bump rows that have their bundle setting on; the loader drops it without reading this class. */
		const ROWS_CACHE = WFOB_FunnelKit_Bundles_Loader::ROWS_CACHE;

		/** Transient holding when ROWS_CACHE was last dropped, so a rebuild begun earlier does not store stale rows. */
		const ROWS_CLEARED = WFOB_FunnelKit_Bundles_Loader::ROWS_CLEARED;

		/** Transient set while one request rebuilds ROWS_CACHE. */
		const ROWS_LOCK = WFOB_FunnelKit_Bundles_Loader::ROWS_LOCK;

		/** Transient keeping the last rows built, read while another request rebuilds ROWS_CACHE. */
		const ROWS_STALE = WFOB_FunnelKit_Bundles_Loader::ROWS_STALE;

		/** Meta, on a bump pack line's own product instance, naming the image it shows. */
		const IMAGE_META = '_wfob_bundle_image';

		/** Row meta the product search's bundle result carries, so the added row sells its bundle. */
		const SEARCH_KEY = 'wfob_bundle';

		/**
		 * The one instance.
		 *
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * The pack the add in flight still has to place lines for.
		 *
		 * @var array
		 */
		private $pending = array();

		/**
		 * Set while add_pack_lines() places lines, since each re-enters the add hooks.
		 *
		 * @var bool
		 */
		private $placing = false;

		/**
		 * Bundle choices the bump's add posted, read once per add.
		 *
		 * @var array
		 */
		private $posted = array();

		/**
		 * The offer an accepted bump's line is on, for select_line_offer().
		 *
		 * @var string
		 */
		private $line_offer = '';

		/**
		 * The add-on product ids an accepted bump's lines hold, for select_line_addons(); null when not redrawing an accepted bump.
		 *
		 * @var int[]|null
		 */
		private $line_addons = null;

		/**
		 * The tier the add in flight sells, worked out before WooCommerce fixes its quantity.
		 *
		 * @var array
		 */
		private $tier_plan = array();

		/**
		 * Widget markup and prices already worked out this request; a bump draws each twice.
		 *
		 * @var array
		 */
		private static $memo = array();

		/**
		 * Whether the product getters reading this class's line meta are hooked yet.
		 *
		 * @var bool
		 */
		private $watching = false;

		/**
		 * Set while drop_bundle_discounts() reads a bump's settings, which can read the bump's rows again.
		 *
		 * @var bool
		 */
		private $dropping = false;

		/**
		 * Whether skip_bundle_re_add() has told the shopper this request.
		 *
		 * @var bool
		 */
		private $told_re_add = false;

		/**
		 * Each product's sellable bundle this request, by anchor product id; forgotten when a bundle is saved.
		 *
		 * @var array
		 */
		private static $sellable = array();

		/**
		 * What bundle_for() answered this request, by bump id, row key and product id; forgotten when a bump or bundle is saved.
		 *
		 * @var array
		 */
		private static $bundles = array();

		/**
		 * The product whose own stock core skips while a Fixed Bundle pack's lead line is added; 0 when none.
		 *
		 * The pack is sold from its members, whose stock add_pack_lines() checks.
		 *
		 * @var int
		 */
		private $stock_free = 0;

		/**
		 * The cart's hash for memo_key(), worked out once until the cart changes; null when not worked out.
		 *
		 * @var string|null
		 */
		private static $cart_hash = null;

		/**
		 * Each bump's design data this request, by bump id; forgotten when a bump is saved.
		 *
		 * @var array
		 */
		private static $designs = array();

		/**
		 * Whether a bump row sells its bundle, by "bump id|row key|product id"; forgotten when a bump or bundle is saved.
		 *
		 * @var bool[]
		 */
		private static $sells_bundle = array();

		/**
		 * The rows enabled_rows() read this request, so the transient is read once.
		 *
		 * @var array[]|null
		 */
		private static $rows = null;

		/**
		 * The Bundles functions this glue calls, by class.
		 *
		 * Funnel Builder Pro and the Bundles module (inside Funnel Builder Lite) update on their own, so a newer Pro can meet an older Bundles that lacks one of them.
		 *
		 * @return array<string,string[]>
		 */
		private static function required_api() {
			return array(
				'WFFN\Bundles\Includes\Storage\Bundle_Store' => array( 'get_config', 'type_label', 'active_for_product' ),
				'WFFN\Bundles\Includes\Compat\Discount\Layer' => array( 'claim_line' ),
				'WFFN\Bundles\Includes\Compat\Currency\Layer' => array( 'raw_base_price' ),
				'WFFN\Bundles\Includes\Skins\Registry'    => array( 'types' ),
				'WFFN\Bundles\Includes\Frontend\Cart'     => array( 'tier_unit_members', 'fixed_bundle_members', 'fbt_set_members', 'container_thumb', 'container_product', 'tier_bundle_data', 'stored_unit', 'offer_discount_type', 'fbt_line_set', 'addon_roles' ),
				'WFFN\Bundles\Includes\Compat\Render_Api' => array( 'widget_html' ),
				'WFFN\Bundles\Includes\Frontend\Product_Display' => array( 'get_instance', 'ensure_cart_config' ),
				'WFFN\Bundles\Includes\Skins\Frequently_Bought_Together' => array( 'rule' ),
				'WFFN\Bundles\Includes\Skins\Assets'      => array( 'style_handle', 'modules_for', 'enqueue_skin_styles', 'enqueue_behavior' ),
			);
		}

		/**
		 * The first Bundles function this glue needs that the running Bundles does not have.
		 *
		 * @param array<string,string[]>|null $required Class => methods; the glue's own list when null.
		 * @return string `Class::method`, or an empty string when everything is there.
		 */
		public static function missing_api( $required = null ) {
			foreach ( is_array( $required ) ? $required : self::required_api() as $class => $methods ) {
				foreach ( $methods as $method ) {
					if ( ! method_exists( $class, $method ) ) {
						return $class . '::' . $method;
					}
				}
			}

			return '';
		}

		/**
		 * Whether the glue started: false when the running Bundles lacks a function it calls.
		 *
		 * @return bool
		 */
		public static function is_started() {
			return null !== self::$instance;
		}

		/**
		 * Build and cache the bump rows that sell a bundle, so the loader can tell again which requests need the glue.
		 *
		 * @return void
		 */
		public static function warm_rows() {
			if ( self::is_started() ) {
				self::enabled_rows();
			}
		}

		/**
		 * The one instance, made on first use.
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
		 * Register hooks.
		 */
		private function __construct() {
			add_filter( 'wfob_admin_design_schema', array( $this, 'add_style_group' ), 10, 2 );
			add_filter( 'wffn_woocommerce_json_search_found_products', array( $this, 'add_search_rows' ) );
			// Shown on the add, never saved: the bundle's name and price follow its Style tab tick on every read.
			add_filter( 'wffn_rest_api_bump_add_product_response', array( $this, 'describe_row' ), 10, 4 );
			add_filter( 'wffn_rest_api_bump_get_product', array( $this, 'describe_row' ), 10, 4 );

			add_action( 'wfob_after_wrapper', array( $this, 'render_widget' ), 10, 4 );
			add_action( 'wfob_after_bump_content', array( $this, 'render_preview_widget' ), 10, 4 );
			add_filter( 'wfob_allow_choose_options', array( $this, 'force_quick_view' ), 10, 5 );
			add_filter( 'wfob_show_product_price', array( $this, 'own_bundle_price' ), 10, 6 );
			add_filter( 'wfob_show_product_price_placeholder', array( $this, 'print_bundle_price' ), 10, 6 );
			add_action( 'wfob_qv_summary', array( $this, 'render_quick_view_widget' ), 20 );
			add_filter( 'wffn_bundles_render_product_widget', array( $this, 'quiet_product_widget' ), 10, 2 );
			add_action( 'wfob_before_add_to_cart', array( $this, 'read_posted' ) );
			add_action( 'wfob_after_add_to_cart', array( $this, 'clear_posted' ) );
			add_action( 'wfob_add_to_cart_refused', array( $this, 'clear_posted' ) );
			// An auto-added row never posts a choice; it is built on the offer its widget opens on.
			add_action( 'wfob_before_pre_checked_add', array( $this, 'read_pre_checked' ), 10, 3 );
			add_action( 'wfob_after_pre_checked_add', array( $this, 'clear_posted' ) );
			add_filter( 'wfob_re_add_selected_bump', array( $this, 'skip_bundle_re_add' ), 10, 2 );
			add_filter( 'wfob_bump_quantity_stock_ok', array( $this, 'group_has_stock' ), 10, 3 );
			add_filter( 'wfob_bump_stock_ok', array( $this, 'pack_stock_is_the_members' ), 10, 2 );
			add_filter( 'wfob_bump_product_in_stock', array( $this, 'pack_in_stock' ), 10, 4 );

			add_filter( 'woocommerce_add_to_cart_quantity', array( $this, 'tier_quantity' ), 20, 2 );
			// After Bundles' own cart item data at 10.
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'pack_the_line' ), 20, 3 );
			// After the module's own swap at 10, and before anything that tags the line's product at 20.
			add_filter( 'woocommerce_add_cart_item', array( $this, 'place_on_line_product' ), 15 );
			add_action( 'woocommerce_add_to_cart', array( $this, 'add_pack_lines' ), 10, 6 );
			// Right after the module prices its bump lines at 11, before Bundles prices its own at 20.
			add_filter( 'wfob_after_discount_added_to_item', array( $this, 'leave_price_to_bundles' ) );
			// The bundle's own price is the price: the bump's discount never reaches a bundle line.
			add_filter( 'wfob_discount_amount_data', array( $this, 'no_early_discount' ), 10, 3 );
			add_filter( 'wfob_bump_products', array( $this, 'drop_bundle_discounts' ), 10, 2 );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );

			// Any change to the cart's lines or totals makes memo_key() hash the cart again.
			foreach ( array( 'woocommerce_add_to_cart', 'woocommerce_cart_item_removed', 'woocommerce_cart_item_restored', 'woocommerce_after_cart_item_quantity_update', 'woocommerce_cart_emptied', 'woocommerce_cart_loaded_from_session', 'woocommerce_after_calculate_totals' ) as $cart_hook ) {
				add_action( $cart_hook, array( __CLASS__, 'forget_cart_hash' ) );
			}

			// A pack's line sits on Bundles' hidden pack product; the bump still names and shows its own.
			add_filter( 'wfob_product_name_merge_tags', array( $this, 'bump_line_name' ), 20, 4 );
			add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'mark_line_image' ) );
		}

		/**
		 * Add a Bundle accordion to the Style tab for the bump products that have a bundle.
		 *
		 * @param array $schema   Bump admin schema.
		 * @param array $products The bump's saved product rows, keyed by product key.
		 * @return array
		 */
		public function add_style_group( $schema, $products = array() ) {
			if ( ! is_array( $schema ) || ! is_array( $schema['design'] ?? null ) || ! is_array( $products ) ) {
				return $schema;
			}
			$rows = array();
			foreach ( $products as $product_key => $row ) {
				if ( is_array( $row ) && null !== self::sellable_bundle( wc_get_product( absint( $row['id'] ?? 0 ) ) ) ) {
					$rows[ $product_key ] = $row;
				}
			}
			if ( array() === $rows ) {
				return $schema;
			}

			$fields = array();
			foreach ( $rows as $product_key => $row ) {
				$prefix   = self::setting_prefix( $product_key );
				$fields[] = array(
					'type'  => 'checkbox',
					'key'   => $prefix . 'enable',
					'label' => count( $rows ) > 1
						/* translators: %s: product name. */
						? sprintf( __( 'Sell the bundle for %s', 'woofunnels-order-bump' ), (string) ( $row['title'] ?? '' ) )
						: __( 'Sell this product\'s bundle', 'woofunnels-order-bump' ),
					'hint'  => __( 'Offer the bundle set up for this product in FunnelKit Bundles instead of the plain product.', 'woofunnels-order-bump' ),
				);
				$fields[] = array(
					'type'    => 'select',
					'key'     => $prefix . 'display',
					'label'   => __( 'Show bundle as', 'woofunnels-order-bump' ),
					'hint'    => __( 'The carousel layout always opens the bundle in the quick view.', 'woofunnels-order-bump' ),
					'class'   => 'bwf-field-one-full',
					'options' => array(
						array(
							'label' => __( 'Inline', 'woofunnels-order-bump' ),
							'value' => self::DISPLAY_INLINE,
							'key'   => self::DISPLAY_INLINE,
						),
						array(
							'label' => __( 'Quick view', 'woofunnels-order-bump' ),
							'value' => self::DISPLAY_QUICK_VIEW,
							'key'   => self::DISPLAY_QUICK_VIEW,
						),
					),
					'toggler' => array(
						'key'   => $prefix . 'enable',
						'value' => true,
					),
				);
				if ( empty( $schema['values'][ $prefix . 'display' ] ) ) {
					$schema['values'][ $prefix . 'display' ] = self::DISPLAY_INLINE;
				}
				if ( ! isset( $schema['values'][ $prefix . 'enable' ] ) && self::picked_as_bundle( $row ) ) {
					$schema['values'][ $prefix . 'enable' ] = true;
				}
			}

			$schema['design'][] = array(
				'key'    => 'wfob_group_bundle',
				'label'  => __( 'Bundle', 'woofunnels-order-bump' ),
				'fields' => $fields,
			);

			return $schema;
		}

		/**
		 * The design-data key prefix of one bump product's bundle settings.
		 *
		 * @param string $product_key The row's key in the bump.
		 * @return string
		 */
		private static function setting_prefix( $product_key ) {
			return 'bundle_' . $product_key . '_';
		}

		/**
		 * Offer each searched product's bundle as its own result in the bump's product search.
		 *
		 * The result keeps the product's id; its row meta is posted back with the add.
		 *
		 * @param array $products Search results.
		 * @return array
		 */
		public function add_search_rows( $products ) {
			$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'bump' !== $type || ! is_array( $products ) ) {
				return $products;
			}
			$rows = array();
			$seen = array();
			foreach ( $products as $row ) {
				// Another plugin's result, such as a subscription plan.
				if ( ! is_array( $row ) || ! empty( $row['product_meta_data'] ) ) {
					continue;
				}
				$id = absint( $row['id'] ?? 0 );
				if ( ! $id || isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				// A variation's own result: its parent's covers the bundle.
				if ( false !== strpos( (string) ( $row['product_type'] ?? '' ), 'variation' ) ) {
					continue;
				}
				// Only a product with a bundle is loaded; the search already returned the rest.
				$bundle = self::sellable_for_anchor( $id );
				if ( null === $bundle ) {
					continue;
				}
				$product = wc_get_product( $id );
				if ( ! $product instanceof WC_Product || $product->get_parent_id() ) {
					continue;
				}
				$quote = self::opening_quote( $bundle, $product );
				if ( null === $quote ) {
					continue;
				}
				$rows[] = array_merge(
					$row,
					array(
						'product'           => self::titled( rawurldecode( $product->get_title() ), self::bundle_label( $bundle ) ),
						'product_attribute' => '',
						'product_price'     => html_entity_decode( wp_strip_all_tags( wc_price( $quote['final'] ) ), ENT_QUOTES, 'UTF-8' ),
						'product_meta_data' => array( self::SEARCH_KEY => (string) ( $bundle['id'] ?? '' ) ),
					)
				);
			}

			return array_merge( $products, $rows );
		}

		/**
		 * Name and price a bump row that sells its bundle after the bundle, and lock its discount.
		 *
		 * @param array           $row     Row as the bump's product list receives it.
		 * @param WC_Product|null $product The row's product.
		 * @param int             $wfob_id Bump id.
		 * @param string          $row_key The row's key in the bump.
		 * @return array
		 */
		public function describe_row( $row, $product = null, $wfob_id = 0, $row_key = '' ) {
			if ( ! is_array( $row ) ) {
				return $row;
			}
			$bundle = self::sellable_bundle( $product );
			// The Style tab's tick decides: unticked, the row is the plain product everywhere.
			if ( null === $bundle || ! self::settings( (int) $wfob_id, (string) $row_key, $row )['enabled'] ) {
				return $row;
			}
			$row[ self::LOCK_KEY ] = __( 'The bundle sets this price.', 'woofunnels-order-bump' );
			// The bump adds one bundle; its type decides the units, so the row's quantity is not used.
			$row['quantity']        = 1;
			$row['quantity_locked'] = __( 'The bundle sets the quantity.', 'woofunnels-order-bump' );
			$row['title']          = self::titled( $product->get_title(), self::bundle_label( $bundle ) );
			$quote                 = self::opening_quote( $bundle, $product );
			if ( null === $quote ) {
				return $row;
			}
			// The add answers price markup; the saved list answers numbers.
			if ( isset( $row['price'] ) ) {
				$row['price']         = wc_price( $quote['final'] );
				$row['regular_price'] = wc_price( $quote['original'] );
				unset( $row['sale_price'] );
				if ( $quote['final'] < $quote['original'] ) {
					$row['sale_price'] = wc_price( $quote['final'] );
				}

				return $row;
			}
			$row['regular_price'] = round( $quote['original'], wc_get_price_decimals() );
			$row['sale_price']    = $quote['final'] < $quote['original'] ? round( $quote['final'], wc_get_price_decimals() ) : 0;
			$row['is_on_sale']    = $quote['final'] < $quote['original'];
			$row['price_range']   = '';

			return $row;
		}

		/**
		 * Whether a bump row was added as the product search's bundle result.
		 *
		 * @param array $row Bump product row.
		 * @return bool
		 */
		private static function picked_as_bundle( $row ) {
			$meta = is_array( $row['products_meta_data'][1] ?? null ) ? $row['products_meta_data'][1] : array();

			return '' !== (string) ( $meta[ self::SEARCH_KEY ] ?? '' );
		}

		/**
		 * The posted attribute picks that are plain, non-empty values.
		 *
		 * A nested array or object is not a pick, and is dropped rather than handed to a string function.
		 *
		 * @param array $attributes Posted attribute => value map.
		 * @return array
		 */
		private static function plain_picks( array $attributes ) {
			$picks = array();
			foreach ( $attributes as $name => $value ) {
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$picks[ $name ] = $value;
				}
			}

			return $picks;
		}

		/**
		 * A product's name followed by what the bump sells it as, in one translatable format.
		 *
		 * @param string $name  Product name.
		 * @param string $label The bundle's or tier's label.
		 * @return string
		 */
		private static function titled( $name, $label ) {
			/* translators: 1: product name, 2: bundle type or tier label. */
			return sprintf( __( '%1$s - %2$s', 'woofunnels-order-bump' ), $name, $label );
		}

		/**
		 * What a bundle is called after its product's name.
		 *
		 * @param array $bundle Bundle payload.
		 * @return string
		 */
		private static function bundle_label( array $bundle ) {
			$title = WFOB_Bundles_Store::TYPE_FIXED_BUNDLES === (int) ( $bundle['discountType'] ?? 0 ) ? trim( (string) ( $bundle['discountConfig']['fixedBundleTitle'] ?? '' ) ) : '';

			return '' !== $title ? $title : (string) WFOB_Bundles_Store::type_label( (int) ( $bundle['discountType'] ?? 0 ), __( 'Bundle', 'woofunnels-order-bump' ) );
		}

		/**
		 * Bundle types a bump can sell.
		 *
		 * @return int[]
		 */
		private static function types() {
			$types = array( WFOB_Bundles_Store::TYPE_FIXED_BUNDLES, WFOB_Bundles_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER );
			// Quantity Breaks only while the add-on that renders and prices it is active.
			if ( array_key_exists( self::QUANTITY_BREAKS, WFOB_Bundles_Registry::types() ) ) {
				$types[] = self::QUANTITY_BREAKS;
			}

			return $types;
		}

		/**
		 * A bump's design data, read once per request.
		 *
		 * @param int $wfob_id Bump id.
		 * @return array
		 */
		private static function design( $wfob_id ) {
			if ( ! isset( self::$designs[ $wfob_id ] ) ) {
				$design                    = WFOB_Common::get_design_data_meta( $wfob_id );
				self::$designs[ $wfob_id ] = is_array( $design ) ? $design : array();
			}

			return self::$designs[ $wfob_id ];
		}

		/**
		 * One bump product's bundle settings.
		 *
		 * @param int        $wfob_id     Bump id.
		 * @param string     $product_key The row's key in the bump.
		 * @param array|null $row         The row itself, when the caller already holds it.
		 * @return array{enabled:bool,display:string}
		 */
		private static function settings( $wfob_id, $product_key, $row = null ) {
			$design  = $wfob_id && '' !== (string) $product_key ? self::design( (int) $wfob_id ) : array();
			$prefix  = self::setting_prefix( $product_key );
			$display = (string) ( $design[ $prefix . 'display' ] ?? '' );
			$enable  = $prefix . 'enable';

			return array(
				// Until the Style tab saves it, a row added as the search's bundle result sells its bundle.
				'enabled' => array_key_exists( $enable, $design ) ? wc_string_to_bool( $design[ $enable ] ) : self::picked_as_bundle( is_array( $row ) ? $row : self::bump_options( $wfob_id, $product_key ) ),
				'display' => self::DISPLAY_QUICK_VIEW === $display ? self::DISPLAY_QUICK_VIEW : self::DISPLAY_INLINE,
			);
		}

		/**
		 * The parent of a variation, else the product itself.
		 *
		 * @param WC_Product $product Product.
		 * @return int
		 */
		private static function anchor_id( $product ) {
			return $product->is_type( 'variation' ) && $product->get_parent_id() > 0 ? (int) $product->get_parent_id() : (int) $product->get_id();
		}

		/**
		 * The live bundle of a type a bump can sell for this product, or null.
		 *
		 * @param WC_Product|null $product Product the bump sells.
		 * @return array|null
		 */
		private static function sellable_bundle( $product ) {
			if ( ! $product instanceof WC_Product ) {
				return null;
			}

			return self::sellable_for_anchor( self::anchor_id( $product ) );
		}

		/**
		 * The live bundle of a type a bump can sell, placed on this anchor product, or null.
		 *
		 * @param int $anchor Anchor product id, see anchor_id().
		 * @return array|null
		 */
		private static function sellable_for_anchor( $anchor ) {
			$anchor = (int) $anchor;
			if ( $anchor < 1 ) {
				return null;
			}
			if ( array_key_exists( $anchor, self::$sellable ) ) {
				return self::$sellable[ $anchor ];
			}
			self::$sellable[ $anchor ] = null;
			foreach ( array( WFOB_Bundles_Store::SLOT_BUNDLE, WFOB_Bundles_Store::SLOT_FBT ) as $slot ) {
				$bundle = WFOB_Bundles_Store::active_for_product( $anchor, $slot );
				if ( is_array( $bundle ) && in_array( (int) ( $bundle['discountType'] ?? 0 ), self::types(), true ) ) {
					self::$sellable[ $anchor ] = $bundle;
					break;
				}
			}

			return self::$sellable[ $anchor ];
		}

		/**
		 * The bundle a bump product sells, or null when it stays a plain product.
		 *
		 * @param WC_Product|null $product     Product the bump sells.
		 * @param int             $wfob_id     Bump id.
		 * @param string          $product_key The row's key in the bump.
		 * @return array|null
		 */
		private static function bundle_for( $product, $wfob_id, $product_key ) {
			$key = (int) $wfob_id . '|' . (string) $product_key . '|' . ( $product instanceof WC_Product ? $product->get_id() : 0 );
			if ( ! array_key_exists( $key, self::$bundles ) ) {
				self::$bundles[ $key ] = self::settings( $wfob_id, $product_key )['enabled'] ? self::sellable_bundle( self::bump_product( $product, $wfob_id, $product_key ) ) : null;
			}

			return self::$bundles[ $key ];
		}

		/**
		 * The product a bump row offers.
		 *
		 * An accepted bump renders from its cart line, and a pack's line sits on
		 * Bundles' hidden pack product, which has no bundle of its own.
		 *
		 * @param WC_Product|null $product     Product the bump passed.
		 * @param int             $wfob_id     Bump id.
		 * @param string          $product_key The row's key in the bump.
		 * @return WC_Product|null
		 */
		private static function bump_product( $product, $wfob_id, $product_key ) {
			$row = self::bump_options( $wfob_id, $product_key );
			$own = absint( $row['id'] ?? 0 ) ? wc_get_product( absint( $row['id'] ) ) : null;
			if ( ! $own instanceof WC_Product ) {
				return $product;
			}
			// The shopper's variation of the row's product stays; anything else is the row's own.
			return $product instanceof WC_Product && self::anchor_id( $product ) === self::anchor_id( $own ) ? $product : $own;
		}

		/**
		 * Whether a bump product shows its bundle in the quick view rather than inline.
		 *
		 * @param int    $wfob_id     Bump id.
		 * @param string $product_key The row's key in the bump.
		 * @return bool
		 */
		private static function in_quick_view( $wfob_id, $product_key ) {
			if ( self::DISPLAY_QUICK_VIEW === self::settings( $wfob_id, $product_key )['display'] ) {
				return true;
			}
			return in_array( (string) ( self::design( (int) $wfob_id )['layout'] ?? '' ), self::QUICK_VIEW_LAYOUTS, true );
		}

		/**
		 * Route a quick-view bundle through the bump's "choose options" control.
		 *
		 * Lowered for an inline bundle too: the widget carries its own pickers.
		 *
		 * @param bool            $allow         Whether the bump offers its quick view.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @param string          $product_key   The row's key in the bump.
		 * @param int             $wfob_id       Bump id.
		 * @return bool
		 */
		public function force_quick_view( $allow, $product = null, $cart_item_key = '', $product_key = '', $wfob_id = 0 ) {
			if ( null === self::bundle_for( $product, $wfob_id, $product_key ) ) {
				return $allow;
			}

			return self::in_quick_view( $wfob_id, $product_key );
		}

		/**
		 * Print the bundle widget beside an inline bump row.
		 *
		 * @param int             $wfob_id       Bump id.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $product_key   The row's key in the bump.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @return void
		 */
		public function render_widget( $wfob_id, $product = null, $product_key = '', $cart_item_key = '' ) {
			if ( self::in_preview() ) {
				return;
			}
			$this->echo_widget( $wfob_id, $product, $product_key, $cart_item_key );
		}

		/**
		 * Print the bundle widget inside the bump's box in the bump designer's preview,
		 * where no script runs to move it there.
		 *
		 * @param int             $wfob_id       Bump id.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $product_key   The row's key in the bump.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @return void
		 */
		public function render_preview_widget( $wfob_id, $product = null, $product_key = '', $cart_item_key = '' ) {
			if ( ! self::in_preview() ) {
				return;
			}
			$this->echo_widget( $wfob_id, $product, $product_key, $cart_item_key );
		}

		/**
		 * Whether this render is the bump designer's preview, which arrives over REST
		 * with no style queue and no script. The Store API is REST too but serves shoppers.
		 *
		 * @return bool
		 */
		private static function in_preview() {
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				return false;
			}

			return ! ( function_exists( 'WC' ) && method_exists( WC(), 'is_store_api_request' ) && WC()->is_store_api_request() );
		}

		/**
		 * Print the widget, rendered once per bump row.
		 *
		 * @param int             $wfob_id       Bump id.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $product_key   The row's key in the bump.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @return void
		 */
		private function echo_widget( $wfob_id, $product, $product_key, $cart_item_key ) {
			$key = self::memo_key( 'widget', $wfob_id, $product, $product_key, $cart_item_key );
			if ( ! isset( self::$memo[ $key ] ) ) {
				ob_start();
				$this->print_widget( $wfob_id, $product, $product_key, $cart_item_key );
				self::$memo[ $key ] = (string) ob_get_clean();
			}
			echo self::$memo[ $key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in print_widget().
		}

		/**
		 * The bundle widget's markup for an inline bump row.
		 *
		 * @param int             $wfob_id       Bump id.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $product_key   The row's key in the bump.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @return void
		 */
		private function print_widget( $wfob_id, $product, $product_key, $cart_item_key ) {
			$bundle = self::bundle_for( $product, $wfob_id, $product_key );
			if ( null === $bundle || self::in_quick_view( $wfob_id, $product_key ) ) {
				return;
			}
			$product = self::bump_product( $product, $wfob_id, $product_key );

			// No style queue prints in the preview, so the widget carries its sheets.
			$preview = self::in_preview();
			$sheet   = '';
			if ( $preview ) {
				$printed = wp_styles()->done;
				add_filter( 'wffn_bundles_inline_skin_css', '__return_true' );
			}
			$html = $this->widget_html( $bundle, $product, (string) $cart_item_key );
			if ( $preview ) {
				remove_filter( 'wffn_bundles_inline_skin_css', '__return_true' );
				$sheet = self::preview_sheet();
				// The designer shows each product's preview on its own, so the next one prints every sheet again.
				wp_styles()->done = $printed;
				// A linked sheet applies after the swapped-in preview first paints, so it flashes unstyled.
				$html  = self::inline_sheets( $html );
				$sheet = self::inline_sheets( $sheet );
			}
			if ( '' === $html ) {
				return;
			}

			$attrs = array(
				'data-wfob-bundle'           => (string) $wfob_id,
				'data-wfob-bundle-key'       => (string) $product_key,
				'data-wffn-bundles-bundle'   => (string) ( $bundle['id'] ?? '' ),
				'data-wffn-bundles-product'  => (string) self::anchor_id( $product ),
				'data-wffn-bundles-formless' => '1',
			);
			if ( WFOB_Bundles_Store::TYPE_FIXED_BUNDLES === (int) $bundle['discountType'] ) {
				$attrs['data-wfob-bundle-defaults'] = (string) wp_json_encode( array_values( self::member_picks( $bundle ) ) );
			}
			$variations = self::variation_map( $product );
			if ( array() !== $variations ) {
				$attrs['data-wfob-bundle-variations'] = (string) wp_json_encode( $variations );
			}
			$chosen = self::line_chosen_gifts( (string) $cart_item_key, $bundle );
			if ( array() !== $chosen ) {
				$attrs['data-wfob-bundle-gifts'] = (string) wp_json_encode( $chosen );
			}

			$out = '';
			foreach ( $attrs as $name => $value ) {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}

			// No script runs in the preview to reveal the widget on accept, so it shows as accepted, and inert.
			printf(
				'%1$s%2$s<div class="wfob-bundle-widget wfob-bundle-design-%3$s"%4$s %5$s>%6$s</div>',
				$sheet, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Printed by the style API.
				self::design_block( (int) $wfob_id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in design_block().
				esc_attr( (string) $wfob_id ),
				$out, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above.
				$preview ? 'inert' : 'hidden',
				$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered skin template.
			);
		}

		/**
		 * Swap each local stylesheet link in preview markup for its rules in a style element.
		 *
		 * A sheet stays linked when it cannot be read from this site or refers to other files.
		 *
		 * @param string $markup Preview markup.
		 * @return string
		 */
		private static function inline_sheets( $markup ) {
			if ( false === strpos( (string) $markup, '<link' ) ) {
				return (string) $markup;
			}
			$base = trailingslashit( content_url() );

			return (string) preg_replace_callback(
				'/<link\b[^>]*\brel=([\'"])stylesheet\1[^>]*>/i',
				function ( $match ) use ( $base ) {
					if ( ! preg_match( '/\bhref=([\'"])([^\'"]+)\1/i', $match[0], $href ) ) {
						return $match[0];
					}
					$url = html_entity_decode( strtok( $href[2], '?' ), ENT_QUOTES );
					$rel = 0 === strpos( $url, $base ) ? substr( $url, strlen( $base ) ) : '';
					if ( '' === $rel || false !== strpos( $rel, '..' ) || '.css' !== substr( $rel, -4 ) ) {
						return $match[0];
					}
					$path = WP_CONTENT_DIR . '/' . $rel;
					if ( ! is_readable( $path ) ) {
						return $match[0];
					}
					$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local stylesheet, path checked above.
					if ( '' === $css || false !== stripos( $css, '</style' ) || preg_match( '/url\(\s*(?![\'"]?data:)/i', $css ) ) {
						return $match[0];
					}

					return '<style>' . $css . '</style>';
				},
				(string) $markup
			);
		}

		/**
		 * The bump's own widget stylesheet as markup, for the designer preview.
		 *
		 * @return string
		 */
		private static function preview_sheet() {
			$handle = 'wfob-funnelkit-bundles';
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, WFOB_PLUGIN_URL . '/assets/css/funnelkit-bundles.min.css', array(), defined( 'WFOB_VERSION' ) ? WFOB_VERSION : false );
			}
			ob_start();
			wp_print_styles( array( $handle ) );

			return (string) ob_get_clean();
		}

		/**
		 * Print the bundle widget inside the bump's quick view.
		 *
		 * @return void
		 */
		public function render_quick_view_widget() {
			global $product;
			$wfob_id = self::quick_view_bump_id();
			$bundle  = self::quick_view_bundle( $product );
			if ( null === $bundle ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- A shopper's quick view, nonce verified by the module; the value only picks what to render.
			$cart_key = isset( $_POST['cart_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_key'] ) ) : '';
			$html     = $this->widget_html( $bundle, $product, $cart_key );
			if ( '' === $html ) {
				return;
			}

			printf(
				'%1$s<div class="wfob-bundle-widget wfob-bundle-in-quick-view wfob-bundle-design-%2$s" data-wffn-bundles-bundle="%3$s" data-wffn-bundles-product="%4$d" data-wffn-bundles-formless="1">%5$s</div>',
				self::design_block( $wfob_id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in design_block().
				esc_attr( (string) $wfob_id ),
				esc_attr( (string) ( $bundle['id'] ?? '' ) ),
				(int) self::anchor_id( $product ),
				$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered skin template.
			);
		}

		/**
		 * Keep Bundles' product-page widget out of every bump quick view.
		 *
		 * The quick view sells what the bump offers: the bundle it prints itself, or
		 * the plain product when the bump does not sell its bundle.
		 *
		 * @param bool            $render  Whether Bundles prints its widget.
		 * @param WC_Product|null $product Product the hook fired for.
		 * @return bool
		 */
		public function quiet_product_widget( $render, $product = null ) {
			unset( $product );

			return doing_action( 'wfob_qv_summary' ) ? false : $render;
		}

		/**
		 * The bump id the quick view request carries.
		 *
		 * @return int
		 */
		private static function quick_view_bump_id() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- A shopper's quick view, nonce verified by the module; the value only picks what to render.
			return isset( $_POST['wfob_id'] ) ? absint( $_POST['wfob_id'] ) : 0;
		}

		/**
		 * The bundle the open quick view shows, or null when it shows none.
		 *
		 * @param WC_Product|null $product Product in the quick view.
		 * @return array|null
		 */
		private static function quick_view_bundle( $product ) {
			$wfob_id = self::quick_view_bump_id();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- As above.
			$product_key = isset( $_POST['item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['item_key'] ) ) : '';
			// Only the row's own product (or an accepted pack's line product) gets the row's bundle drawn for it.
			$own       = $product instanceof WC_Product ? self::bump_product( $product, $wfob_id, $product_key ) : null;
			$container = WFOB_Bundles_Cart::container_product();
			if ( ! $own instanceof WC_Product || ( self::anchor_id( $product ) !== self::anchor_id( $own ) && ! ( $container instanceof WC_Product && $container->get_id() === $product->get_id() ) ) ) {
				return null;
			}
			$bundle = self::bundle_for( $product, $wfob_id, $product_key );

			return null !== $bundle && self::in_quick_view( $wfob_id, $product_key ) ? $bundle : null;
		}

		/**
		 * The widget's markup, opened on the offer an accepted bump's line holds.
		 *
		 * @param array      $bundle        Bundle payload.
		 * @param WC_Product $product       Product the bump sells.
		 * @param string     $cart_item_key Cart item key, empty until accepted.
		 * @return string
		 */
		private function widget_html( array $bundle, $product, $cart_item_key ) {
			if ( WFOB_Bundles_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) $bundle['discountType'] ) {
				return WFOB_Bundles_Product_Display::get_instance()->render_fbt_product(
					wc_get_product( self::anchor_id( $product ) ),
					array(
						'button'       => false,
						'allow_repeat' => true,
						'set'          => WFOB_Bundles_Cart::fbt_line_set( $cart_item_key ),
					)
				);
			}

			$this->line_offer = self::line_offer( $cart_item_key );
			if ( '' !== $this->line_offer ) {
				add_filter( 'wffn_bundles_view_model', array( $this, 'select_line_offer' ), 20, 3 );
				add_filter( 'wffn_bundles_bundle_input', array( $this, 'select_line_tier' ), 20 );
			}
			$this->line_addons = self::line_addons( $cart_item_key );
			if ( null !== $this->line_addons ) {
				add_filter( 'wffn_bundles_addon_layers', array( $this, 'select_line_addons' ), 20 );
			}
			// The bump owns the add, and a form.cart would nest in the checkout's own form.
			$html = WFOB_Bundles_Render_Api::widget_html(
				array(
					'product_id'   => self::anchor_id( $product ),
					'allow_repeat' => true,
					'button_mode'  => 'never',
				)
			);
			remove_filter( 'wffn_bundles_view_model', array( $this, 'select_line_offer' ), 20 );
			remove_filter( 'wffn_bundles_bundle_input', array( $this, 'select_line_tier' ), 20 );
			remove_filter( 'wffn_bundles_addon_layers', array( $this, 'select_line_addons' ), 20 );
			$this->line_offer  = '';
			$this->line_addons = null;

			return $html;
		}

		/**
		 * Open a Fixed Bundle widget on the half its accepted line holds.
		 *
		 * @param array $vm   View model.
		 * @param array $args View-model input.
		 * @param int   $type Bundle type.
		 * @return array
		 */
		public function select_line_offer( $vm, $args = array(), $type = 0 ) {
			if ( ! is_array( $vm ) || WFOB_Bundles_Store::TYPE_FIXED_BUNDLES !== (int) $type || ! array_key_exists( 'bundleSelected', $vm ) ) {
				return $vm;
			}
			$on_bundle                 = 'bundle' === $this->line_offer;
			$vm['bundleSelected']      = $on_bundle;
			$vm['bundleSelectedClass'] = $on_bundle ? 'wffn-bundles-fb-card--selected' : '';
			$vm['singleSelected']      = ! $on_bundle;
			$vm['singleSelectedClass'] = $on_bundle ? '' : 'wffn-bundles-fb-single--selected';

			return $vm;
		}

		/**
		 * Open a Quantity Breaks widget on the tier its accepted lines hold.
		 *
		 * @param array $input View-model input.
		 * @return array
		 */
		public function select_line_tier( $input ) {
			if ( ! is_array( $input ) || ! is_array( $input['tiers'] ?? null ) ) {
				return $input;
			}
			foreach ( array_values( $input['tiers'] ) as $i => $tier ) {
				if ( is_array( $tier ) && (string) ( $tier['id'] ?? '' ) === $this->line_offer ) {
					$input['selected_index'] = $i;
				}
			}

			return $input;
		}

		/**
		 * Tick an accepted bump's add-ons by its cart lines, not the bundle's defaults.
		 *
		 * Each add-on change re-places the bump and redraws it; drawn on the defaults, an
		 * add-on taken out showed ticked, and the next click sent it unticked again.
		 *
		 * @param array $layers Add-on layers to draw under the widget.
		 * @return array
		 */
		public function select_line_addons( $layers ) {
			if ( ! is_array( $layers ) || ! is_array( $this->line_addons ) ) {
				return $layers;
			}
			foreach ( $layers as $i => $layer ) {
				if ( ! is_array( $layer ) || 'upsell' !== (string) ( $layer['skin'] ?? '' ) || ! is_array( $layer['args']['items'] ?? null ) ) {
					continue;
				}
				foreach ( $layer['args']['items'] as $j => $item ) {
					if ( is_array( $item ) ) {
						$layers[ $i ]['args']['items'][ $j ]['isDefault'] = in_array( (int) ( $item['productId'] ?? 0 ), $this->line_addons, true );
					}
				}
			}

			return $layers;
		}

		/**
		 * The add-on products an accepted bump's lines hold.
		 *
		 * @param string $cart_item_key Cart item key of the bump's lead line.
		 * @return int[]|null Product and variation ids; null when the bump is not accepted.
		 */
		private static function line_addons( $cart_item_key ) {
			$lead = self::cart_item( $cart_item_key );
			if ( null === $lead ) {
				return null;
			}
			$group = (string) ( $lead[ self::LINE_KEY ]['group'] ?? '' );
			// A line bought alone, outside any group, holds no add-on: drawn on the defaults, they showed ticked.
			if ( '' === $group ) {
				return array();
			}
			$ids = array();
			foreach ( WC()->cart->get_cart() as $item ) {
				$line = is_array( $item[ self::LINE_KEY ] ?? null ) ? $item[ self::LINE_KEY ] : array();
				if ( (string) ( $line['group'] ?? '' ) === $group && 'upsell' === (string) ( $line['role'] ?? '' ) ) {
					$ids[] = (int) ( $item['product_id'] ?? 0 );
					$ids[] = (int) ( $item['variation_id'] ?? 0 );
				}
			}

			return array_values( array_filter( $ids ) );
		}

		/**
		 * The gifts an accepted bump's lines hold that the shopper picked from a list.
		 *
		 * The widget draws a list gift unpicked, and the next re-place posts only what it shows,
		 * so its script picks these again; shaped as Bundles' restore-gifts event takes them.
		 *
		 * @param string $cart_item_key Cart item key of the bump's lead line.
		 * @param array  $bundle        Bundle payload.
		 * @return array[] Rows of { id, name, image, attributes }.
		 */
		private static function line_chosen_gifts( $cart_item_key, array $bundle ) {
			$lead  = self::cart_item( $cart_item_key );
			$group = null === $lead ? '' : (string) ( $lead[ self::LINE_KEY ]['group'] ?? '' );
			if ( '' === $group ) {
				return array();
			}
			$choice = array();
			foreach ( (array) ( $bundle['discountConfig']['progressiveGifts'] ?? array() ) as $gift ) {
				if ( is_array( $gift ) && 'choice' === (string) ( $gift['selection'] ?? '' ) ) {
					$choice[] = (string) ( $gift['id'] ?? '' );
				}
			}
			$chosen = array();
			foreach ( WC()->cart->get_cart() as $item ) {
				$line = is_array( $item[ self::LINE_KEY ] ?? null ) ? $item[ self::LINE_KEY ] : array();
				if ( (string) ( $line['group'] ?? '' ) !== $group || 'gift' !== (string) ( $line['role'] ?? '' ) || ! in_array( (string) ( $line['offer'] ?? '' ), $choice, true ) ) {
					continue;
				}
				$gift = wc_get_product( absint( $item['product_id'] ?? 0 ) );
				if ( ! $gift instanceof WC_Product ) {
					continue;
				}
				// Keyed by label, as the widget's picker posts a variation.
				$attributes = array();
				foreach ( (array) ( $item['variation'] ?? array() ) as $name => $value ) {
					$attributes[ wc_attribute_label( (string) preg_replace( '/^attribute_/', '', (string) $name ), $gift ) ] = (string) $value;
				}
				$chosen[] = array(
					'id'         => $gift->get_id(),
					'name'       => $gift->get_name(),
					'image'      => (string) wp_get_attachment_image_url( $gift->get_image_id(), 'woocommerce_thumbnail' ),
					'attributes' => $attributes,
				);
			}

			return $chosen;
		}

		/**
		 * The offer an accepted bump's line was bought on.
		 *
		 * @param string $cart_item_key Cart item key.
		 * @return string `bundle`, `single`, `fbt-set`, a tier id or ''.
		 */
		private static function line_offer( $cart_item_key ) {
			$item = self::cart_item( $cart_item_key );
			if ( null === $item ) {
				return '';
			}
			if ( 'single' === (string) ( $item['wfob_bundle_offer'] ?? '' ) ) {
				return 'single';
			}
			$line = is_array( $item[ self::LINE_KEY ] ?? null ) ? $item[ self::LINE_KEY ] : array();
			if ( array() === $line ) {
				return '';
			}
			if ( WFOB_Bundles_Store::TYPE_FIXED_BUNDLES === (int) ( $line['type'] ?? 0 ) ) {
				return 'container' === (string) ( $line['role'] ?? '' ) ? 'bundle' : 'single';
			}

			return (string) ( $line['offer'] ?? '' );
		}

		/**
		 * Whether an accepted bump's line holds its product alone, outside any bundle group.
		 *
		 * An FBT set reduced to its host product leaves the cart as a plain line, priced as one.
		 *
		 * @param string $cart_item_key Cart item key, empty until accepted.
		 * @return bool
		 */
		private static function is_plain_line( $cart_item_key ) {
			$item = self::cart_item( $cart_item_key );
			if ( null === $item ) {
				return false;
			}
			$line = is_array( $item[ self::LINE_KEY ] ?? null ) ? $item[ self::LINE_KEY ] : array();

			return '' === (string) ( $line['group'] ?? '' );
		}

		/**
		 * A cart line, or null.
		 *
		 * @param string $cart_item_key Cart item key.
		 * @return array|null
		 */
		private static function cart_item( $cart_item_key ) {
			if ( '' === (string) $cart_item_key || ! function_exists( 'WC' ) || empty( WC()->cart ) ) {
				return null;
			}
			$item = WC()->cart->get_cart_item( (string) $cart_item_key );

			return is_array( $item ) && array() !== $item ? $item : null;
		}

		/**
		 * Hand the bump's price line to the bundle, except for a line bought alone.
		 *
		 * @param bool            $show          Whether the bump prints its price.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @param array           $price_data    The bump's price data.
		 * @param string          $product_key   The row's key in the bump.
		 * @param int             $wfob_id       Bump id.
		 * @return bool
		 */
		public function own_bundle_price( $show, $product = null, $cart_item_key = '', $price_data = array(), $product_key = '', $wfob_id = 0 ) {
			if ( null === self::bundle_for( $product, $wfob_id, $product_key ) ) {
				return $show;
			}

			return self::is_plain_line( (string) $cart_item_key ) ? $show : false;
		}

		/**
		 * The bundle's price, in the markup the bump prints for its own.
		 *
		 * @param string          $html          Price markup the bump would print.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @param array           $price_data    The bump's price data.
		 * @param string          $product_key   The row's key in the bump.
		 * @param int             $wfob_id       Bump id.
		 * @return string
		 */
		public function print_bundle_price( $html, $product = null, $cart_item_key = '', $price_data = array(), $product_key = '', $wfob_id = 0 ) {
			$key = self::memo_key( 'price', $wfob_id, $product, $product_key, $cart_item_key );
			if ( isset( self::$memo[ $key ] ) ) {
				return self::$memo[ $key ];
			}
			$bundle = self::bundle_for( $product, $wfob_id, $product_key );
			if ( null === $bundle || self::is_plain_line( (string) $cart_item_key ) ) {
				return $html;
			}
			$product = self::bump_product( $product, $wfob_id, $product_key );
			$quote   = self::line_quote( (string) $cart_item_key );
			if ( null === $quote ) {
				// Shown the way the neighbouring rows show prices: with tax where the store displays it.
				$quote = self::opening_quote( $bundle, $product, true );
			}
			if ( null === $quote ) {
				self::$memo[ $key ] = '';
				return '';
			}

			$decimals           = wc_get_price_decimals();
			self::$memo[ $key ] = round( $quote['final'], $decimals ) === round( $quote['original'], $decimals )
				? wc_price( $quote['final'] )
				: wc_format_sale_price( $quote['original'], $quote['final'] );
			return self::$memo[ $key ];
		}

		/**
		 * What one bump row's widget or price is worked out from, cart included.
		 *
		 * @param string          $kind          'widget' or 'price'.
		 * @param int             $wfob_id       Bump id.
		 * @param WC_Product|null $product       Product the bump sells.
		 * @param string          $product_key   The row's key in the bump.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @return string
		 */
		private static function memo_key( $kind, $wfob_id, $product, $product_key, $cart_item_key ) {
			if ( null === self::$cart_hash ) {
				self::$cart_hash = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_hash() : '';
			}
			$cart = self::$cart_hash;

			return implode( '|', array( $kind, (int) $wfob_id, (string) $product_key, (string) $cart_item_key, $product instanceof WC_Product ? $product->get_id() : 0, $cart ) );
		}

		/**
		 * Hash the cart again on the next memo_key().
		 *
		 * @return void
		 */
		public static function forget_cart_hash() {
			self::$cart_hash = null;
		}

		/**
		 * What an accepted bump's lines cost, read off the cart.
		 *
		 * @param string $cart_item_key Cart item key of the bump's line.
		 * @return array|null { original, final }
		 */
		private static function line_quote( $cart_item_key ) {
			$item  = self::cart_item( $cart_item_key );
			$line  = null === $item ? array() : ( is_array( $item[ self::LINE_KEY ] ?? null ) ? $item[ self::LINE_KEY ] : array() );
			$group = (string) ( $line['group'] ?? '' );
			if ( '' === $group ) {
				return null;
			}
			$original = 0.0;
			$final    = 0.0;
			$with_tax = wc_tax_enabled() && 'incl' === WC()->cart->get_tax_price_display_mode();
			foreach ( WC()->cart->get_cart() as $other ) {
				$b = is_array( $other[ self::LINE_KEY ] ?? null ) ? $other[ self::LINE_KEY ] : array();
				if ( (string) ( $b['group'] ?? '' ) !== $group ) {
					continue;
				}
				$qty  = max( 1, (int) ( $other['quantity'] ?? 1 ) );
				$role = (string) ( $b['role'] ?? '' );
				// What the cart's own totals charge for the line, its own tax included: plan and bump discount included.
				$final += isset( $other['line_subtotal'] ) ? (float) $other['line_subtotal'] : WFOB_Bundles_Cart::stored_unit( $other ) * $qty;
				if ( $with_tax ) {
					$final += (float) ( $other['line_subtotal_tax'] ?? 0 );
				}
				// A container carries the pack price; the undiscounted total sits on its children. A free gift is not part of the price: Bundles' own checkout rows leave it out too, so the struck-through price is the bundle's, not the bundle plus what it gives away.
				if ( 'container' !== $role && 'gift' !== $role ) {
					// Each line at its own product's tax class: a child line charges nothing, so the cart has no tax on it to go by.
					$original += (float) ( $b['unit'] ?? 0 ) * $qty * ( $with_tax ? self::tax_display_factor( $other['data'] ?? null ) : 1.0 );
				}
			}

			$original = $original > 0 ? $original : $final;

			return array(
				'original' => $original,
				'final'    => $final,
			);
		}

		/**
		 * The multiplier that turns a stored (raw) price of a product into the price the store displays in the cart.
		 *
		 * @param WC_Product|null $product Product the amount belongs to.
		 * @return float
		 */
		private static function tax_display_factor( $product ) {
			if ( ! $product instanceof WC_Product || ! wc_tax_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return 1.0;
			}
			$probe   = 1000.0;
			$display = 'incl' === WC()->cart->get_tax_price_display_mode() ? wc_get_price_including_tax( $product, array( 'price' => $probe ) ) : wc_get_price_excluding_tax( $product, array( 'price' => $probe ) );

			return $display > 0 ? (float) $display / $probe : 1.0;
		}

		/**
		 * What ticking the bump would cost, on the offer its widget opens on, with the add-ons it opens ticked.
		 *
		 * @param array      $bundle       Bundle payload.
		 * @param WC_Product $product      Product the bump sells.
		 * @param bool       $with_display Price each member as the cart displays it, at its own tax class.
		 * @return array|null { original, final }
		 */
		private static function opening_quote( array $bundle, $product, $with_display = false ) {
			$type = (int) $bundle['discountType'];
			if ( self::QUANTITY_BREAKS === $type ) {
				$members = self::opening_tier_rows( $bundle, $product );
				$tier    = self::opening_tier( $bundle );
				$addons  = self::opening_addons( $bundle, (string) ( $tier['id'] ?? '' ) );
			} elseif ( WFOB_Bundles_Store::TYPE_FIXED_BUNDLES === $type ) {
				$members = WFOB_Bundles_Cart::fixed_bundle_members( $bundle, self::member_picks( $bundle ) );
				$addons  = self::opening_addons( $bundle, 'fb-bundle' );
			} else {
				// The set's widget draws no add-ons, and the set's add places none.
				$members = WFOB_Bundles_Cart::fbt_set_members( $bundle, $product, null );
				$addons  = array();
			}
			if ( empty( $members ) ) {
				return null;
			}
			$original = 0.0;
			$final    = 0.0;
			foreach ( array_merge( $members, $addons ) as $m ) {
				$qty    = max( 1, (int) ( $m['qty'] ?? 1 ) );
				$factor = 1.0;
				if ( $with_display ) {
					$member = wc_get_product( absint( ! empty( $m['variation_id'] ) ? $m['variation_id'] : ( $m['product_id'] ?? 0 ) ) );
					$factor = self::tax_display_factor( $member instanceof WC_Product ? $member : $product );
				}
				$original += (float) ( $m['unit'] ?? 0 ) * $qty * $factor;
				$final    += (float) ( $m['pricedUnit'] ?? 0 ) * $qty * $factor;
			}

			return array(
				'original' => $original,
				'final'    => $final,
			);
		}

		/**
		 * The paid add-ons ticking the bump places with its bundle, as the cart prices them.
		 *
		 * The bump posts what its hidden widget holds, and the widget opens with each add-on the
		 * bundle pre-selects ticked; a variable one only once its default options name a variation.
		 * A gift is free and the cart's struck price leaves it out, so only the add-ons are asked for.
		 *
		 * @param array  $bundle Bundle payload.
		 * @param string $offer  The offer the widget opens on.
		 * @return array[] Member rows, each with unit, pricedUnit and qty.
		 */
		private static function opening_addons( array $bundle, $offer ) {
			$dc = is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array();
			if ( empty( $dc['upsellsEnabled'] ) ) {
				return array();
			}
			$picks = array();
			foreach ( (array) ( $dc['upsells'] ?? array() ) as $upsell ) {
				$id = is_array( $upsell ) && ! empty( $upsell['isDefault'] ) ? absint( $upsell['product']['id'] ?? 0 ) : 0;
				if ( $id ) {
					$picks[] = array(
						'id'         => $id,
						'attributes' => self::preset_attributes( wc_get_product( $id ) ),
					);
				}
			}
			if ( array() === $picks ) {
				return array();
			}
			$rows = array();
			foreach ( self::addon_rows( $bundle, $offer, array( 'upsells' => $picks ) ) as $m ) {
				// A gift is free, and the cart's struck price leaves it out.
				if ( 'gift' !== (string) ( $m['role'] ?? '' ) ) {
					$rows[] = $m;
				}
			}

			return $rows;
		}

		/**
		 * The options a variable add-on's picker opens on: the product's default attributes, keyed by label.
		 *
		 * @param WC_Product|false|null $product Add-on product, or one of its variations.
		 * @return array<string,string> Empty for a simple product or when a default is missing.
		 */
		private static function preset_attributes( $product ) {
			$parent = $product instanceof WC_Product && $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
			if ( ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) ) {
				return array();
			}
			$defaults = (array) $parent->get_default_attributes();
			$out      = array();
			foreach ( $parent->get_attributes() as $attribute ) {
				if ( ! is_object( $attribute ) || ! $attribute->get_variation() ) {
					continue;
				}
				$name  = (string) $attribute->get_name();
				$value = (string) ( $defaults[ sanitize_title( $name ) ] ?? '' );
				if ( '' !== $value ) {
					$out[ wc_attribute_label( $name, $parent ) ] = $value;
				}
			}

			return $out;
		}

		/**
		 * The tier a Quantity Breaks widget opens on: the one selected by default, else the first.
		 *
		 * @param array $bundle Bundle payload.
		 * @return array|null
		 */
		private static function opening_tier( array $bundle ) {
			$tiers = array_values( array_filter( (array) ( $bundle['discountConfig']['quantityBreaksTiers'] ?? array() ), 'is_array' ) );
			foreach ( $tiers as $candidate ) {
				if ( ! empty( $candidate['selectedByDefault'] ) ) {
					return $candidate;
				}
			}

			return $tiers[0] ?? null;
		}

		/**
		 * The lines a Quantity Breaks widget's opening tier adds, each unit on its default variation.
		 *
		 * @param array      $bundle  Bundle payload.
		 * @param WC_Product $product Product the bump sells.
		 * @return array[]
		 */
		private static function opening_tier_rows( array $bundle, $product ) {
			$tier   = self::opening_tier( $bundle );
			$anchor = wc_get_product( self::anchor_id( $product ) );
			if ( ! is_array( $tier ) || ! $anchor instanceof WC_Product ) {
				return array();
			}
			$units = array();
			if ( $anchor->is_type( 'variable' ) ) {
				$attributes = self::default_attributes( $anchor );
				if ( array() === $attributes ) {
					return array();
				}
				$units = array_fill( 0, max( 1, (int) ( $tier['quantity'] ?? 1 ) ), array( 'attributes' => $attributes ) );
			}

			return WFOB_Bundles_Cart::tier_unit_members( $bundle, (string) ( $tier['id'] ?? '' ), $anchor->get_id(), $units );
		}

		/**
		 * The discount settings of one bump product.
		 *
		 * @param int    $wfob_id     Bump id.
		 * @param string $product_key The row's key in the bump.
		 * @return array
		 */
		private static function bump_options( $wfob_id, $product_key ) {
			$products = $wfob_id ? WFOB_Common::get_bump_products( (int) $wfob_id ) : array();

			return is_array( $products[ $product_key ] ?? null ) ? $products[ $product_key ] : array();
		}

		/**
		 * Read the bundle choices the bump's add posted.
		 *
		 * @param array $post The bump's posted data, nonce-checked by the module.
		 * @return void
		 */
		public function read_posted( $post = array() ) {
			// Start every bump's add clean, so a choice left from an earlier add in this request cannot reach this bump's tier.
			$this->clear_posted();
			$post         = is_array( $post ) ? $post : array();
			$offer        = sanitize_text_field( (string) ( $post['wfob_bundle_offer'] ?? '' ) );
			$this->posted = array(
				'wfob_id' => absint( $post['wfob_id'] ?? 0 ),
				'key'     => sanitize_text_field( (string) ( $post['product_key'] ?? '' ) ),
				// A tier id is checked against the bundle's own tiers when the line is built.
				'offer'   => in_array( $offer, array( 'single', 'bundle', WFOB_Bundles_Store::FBT_OFFER ), true ) || preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $offer ) ? $offer : '',
				'items'   => self::decode( $post['wfob_bundle_items'] ?? '' ),
				'addons'  => self::decode( $post['wfob_bundle_addons'] ?? '' ),
			);
		}

		/**
		 * Set up an auto-added bundle row's add on the offer its widget opens on, the one its price is shown for.
		 *
		 * @param int    $wfob_id     Bump id.
		 * @param string $product_key The row's key in the bump.
		 * @param array  $row         The bump row's settings.
		 * @return void
		 */
		public function read_pre_checked( $wfob_id, $product_key = '', $row = array() ) {
			$this->clear_posted();
			$product = wc_get_product( absint( is_array( $row ) ? ( $row['id'] ?? 0 ) : 0 ) );
			$bundle  = $product instanceof WC_Product ? self::bundle_for( $product, (int) $wfob_id, (string) $product_key ) : null;
			if ( null === $bundle ) {
				return;
			}
			$type  = (int) $bundle['discountType'];
			$offer = 'bundle';
			$items = array();
			if ( self::QUANTITY_BREAKS === $type ) {
				$tier = self::opening_tier( $bundle );
				if ( null === $tier ) {
					return;
				}
				$offer  = (string) ( $tier['id'] ?? '' );
				$anchor = wc_get_product( self::anchor_id( $product ) );
				if ( $anchor instanceof WC_Product && $anchor->is_type( 'variable' ) ) {
					$items = array_fill( 0, max( 1, (int) ( $tier['quantity'] ?? 1 ) ), array( 'attributes' => self::default_attributes( $anchor ) ) );
				}
			} elseif ( WFOB_Bundles_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
				$offer = WFOB_Bundles_Store::FBT_OFFER;
			}
			$this->posted = array(
				'wfob_id' => absint( $wfob_id ),
				'key'     => (string) $product_key,
				'offer'   => $offer,
				'items'   => $items,
				'addons'  => array(),
			);
		}

		/**
		 * Leave a bundle line out when a checkout page switch re-adds the bump from the session.
		 *
		 * The re-add carries only the bump's own keys, so a bundle line would come back as its
		 * bare product (a pack as the free hidden pack product); the shopper is told to tick the bump again.
		 *
		 * @param bool  $re_add    Whether to re-add the line.
		 * @param array $item_data The line's stored cart item data.
		 * @return bool
		 */
		public function skip_bundle_re_add( $re_add, $item_data = array() ) {
			if ( ! is_array( $item_data ) || ! isset( $item_data[ self::LINE_KEY ] ) ) {
				return $re_add;
			}
			// A bundle has several lines; one notice covers them all.
			if ( ! $this->told_re_add ) {
				$this->told_re_add = true;
				wc_add_notice( __( 'A bundle offer was removed when the checkout page changed. Tick it again to add it back.', 'woofunnels-order-bump' ), 'notice' );
			}

			return false;
		}

		/**
		 * Check the stock of every line that follows a bundle bump's quantity before the bump's stepper changes it.
		 *
		 * Bundles moves those lines with the lead line, so the module's own check, on the lead alone, would let
		 * the shopper reach checkout with more than the store has. Gifts and add-ons keep their own quantity.
		 *
		 * @param bool      $ok       Whether the lead line's product has the stock.
		 * @param string    $cart_key Cart key of the bump's lead line.
		 * @param int|float $quantity The lead line's new quantity.
		 * @return bool
		 */
		public function group_has_stock( $ok, $cart_key = '', $quantity = 0 ) {
			$cart = WC()->cart;
			$lead = $cart ? $cart->get_cart_item( (string) $cart_key ) : array();
			if ( ! $ok || empty( $lead[ self::LINE_KEY ]['group'] ) || (float) ( $lead['quantity'] ?? 0 ) <= 0 ) {
				return $ok;
			}
			$group = (string) $lead[ self::LINE_KEY ]['group'];
			// Demand is totalled per stock-holding product, over the whole cart, as WooCommerce checks it at checkout.
			$held     = $cart->get_cart_item_quantities();
			$delta    = array();
			$products = array();
			foreach ( $cart->get_cart() as $key => $item ) {
				$line    = isset( $item[ self::LINE_KEY ] ) && is_array( $item[ self::LINE_KEY ] ) ? $item[ self::LINE_KEY ] : array();
				$is_lead = (string) $key === (string) $cart_key;
				if ( ! $is_lead && ( (string) ( $line['group'] ?? '' ) !== $group || ! in_array( (string) ( $line['role'] ?? '' ), array( 'child', 'fbt_companion', 'member' ), true ) ) ) {
					continue;
				}
				if ( ! ( $item['data'] ?? null ) instanceof WC_Product ) {
					continue;
				}
				$now = (float) $item['quantity'];
				// A following line keeps its share of the lead's quantity.
				$new = $is_lead ? (float) $quantity : (float) ceil( $now * (float) $quantity / (float) $lead['quantity'] );
				$sid = $item['data']->get_stock_managed_by_id();

				$delta[ $sid ]    = ( $delta[ $sid ] ?? 0 ) + $new - $now;
				$products[ $sid ] = $item['data'];
			}
			foreach ( $delta as $sid => $more ) {
				if ( $more > 0 && ! $products[ $sid ]->has_enough_stock( (float) ( $held[ $sid ] ?? 0 ) + $more ) ) {
					return false;
				}
			}

			return $ok;
		}

		/**
		 * A Fixed Bundle pack is sold from its members, whose stock add_pack_lines() checks; the bump
		 * product's own stock only matters when the shopper takes it alone ("single").
		 *
		 * @param bool            $ok      Whether the bump product has the stock.
		 * @param WC_Product|null $product Product the bump adds.
		 * @return bool
		 */
		public function pack_stock_is_the_members( $ok, $product = null ) {
			if ( $ok || array() === $this->posted || 'single' === ( $this->posted['offer'] ?? '' ) || ! $product instanceof WC_Product ) {
				return $ok;
			}
			$bundle = self::bundle_for( $product, (int) $this->posted['wfob_id'], (string) $this->posted['key'] );

			return null !== $bundle && WFOB_Bundles_Store::TYPE_FIXED_BUNDLES === (int) $bundle['discountType'] ? true : $ok;
		}

		/**
		 * Forget the posted choices; one request can add several bumps.
		 *
		 * @return void
		 */
		public function clear_posted() {
			$this->posted    = array();
			$this->pending   = array();
			$this->tier_plan = array();
			$this->free_lead_stock( 0 );
		}

		/**
		 * A sold-out bump product still shows its bump when it sells a Fixed Bundle whose members are all in stock.
		 *
		 * @param bool            $in_stock    Whether the bump product is in stock.
		 * @param WC_Product|null $product     The bump product.
		 * @param int             $wfob_id     Bump id.
		 * @param string          $product_key The product's key in the bump.
		 * @return bool
		 */
		public function pack_in_stock( $in_stock, $product = null, $wfob_id = 0, $product_key = '' ) {
			// Reading a bump's settings can build its default design, which prepares its products and asks again.
			static $asking = false;
			if ( $in_stock || $asking || ! $product instanceof WC_Product ) {
				return $in_stock;
			}
			$asking = true;
			try {
				$bundle = self::bundle_for( $product, (int) $wfob_id, (string) $product_key );
			} finally {
				$asking = false;
			}
			if ( null === $bundle || WFOB_Bundles_Store::TYPE_FIXED_BUNDLES !== (int) $bundle['discountType'] ) {
				return $in_stock;
			}
			$members = WFOB_Bundles_Cart::fixed_bundle_members( $bundle, self::member_picks( $bundle ) );
			if ( empty( $members ) ) {
				return $in_stock;
			}
			foreach ( $members as $m ) {
				$member = wc_get_product( absint( ! empty( $m['variation_id'] ) ? $m['variation_id'] : ( $m['product_id'] ?? 0 ) ) );
				if ( ! $member instanceof WC_Product || ! $member->is_in_stock() || ! $member->has_enough_stock( max( 1, (int) ( $m['qty'] ?? 1 ) ) ) ) {
					return $in_stock;
				}
			}

			return true;
		}

		/**
		 * Let core's stock checks pass this lead product while its line is added; 0 ends that.
		 *
		 * The checks are hooked only meanwhile, so no other product's stock check runs them.
		 *
		 * @param int $product_id The lead product or variation, or 0.
		 * @return void
		 */
		private function free_lead_stock( $product_id ) {
			$this->stock_free = absint( $product_id );
			if ( $this->stock_free ) {
				add_filter( 'woocommerce_product_is_in_stock', array( $this, 'lead_in_stock' ), 99, 2 );
				add_filter( 'woocommerce_product_backorders_allowed', array( $this, 'lead_backorders_allowed' ), 99, 2 );

				return;
			}
			remove_filter( 'woocommerce_product_is_in_stock', array( $this, 'lead_in_stock' ), 99 );
			remove_filter( 'woocommerce_product_backorders_allowed', array( $this, 'lead_backorders_allowed' ), 99 );
		}

		/**
		 * The pack's lead product passes core's in-stock check while its line is added.
		 *
		 * @param bool       $in_stock Whether the product is in stock.
		 * @param WC_Product $product  Product checked.
		 * @return bool
		 */
		public function lead_in_stock( $in_stock, $product = null ) {
			return $in_stock || ( $this->stock_free && $product instanceof WC_Product && $product->get_id() === $this->stock_free );
		}

		/**
		 * The pack's lead product passes core's quantity checks while its line is added.
		 *
		 * @param bool $allowed    Whether backorders are allowed.
		 * @param int  $product_id Product id.
		 * @return bool
		 */
		public function lead_backorders_allowed( $allowed, $product_id = 0 ) {
			return $allowed || ( $this->stock_free && absint( $product_id ) === $this->stock_free );
		}

		/**
		 * A posted JSON blob, decoded, or [].
		 *
		 * @param mixed $raw Posted value.
		 * @return array
		 */
		private static function decode( $raw ) {
			// Already unslashed: it reaches here from inside the bump's decoded JSON.
			$raw = is_string( $raw ) ? $raw : '';
			if ( '' === $raw || strlen( $raw ) > 65536 ) {
				return array();
			}
			$data = json_decode( $raw, true );

			return is_array( $data ) ? self::clean( $data ) : array();
		}

		/**
		 * Sanitize every scalar in a decoded payload.
		 *
		 * @param array $data Decoded payload.
		 * @param int   $depth Nesting so far.
		 * @return array
		 */
		private static function clean( array $data, $depth = 0 ) {
			$out = array();
			if ( $depth > 4 ) {
				return $out;
			}
			foreach ( array_slice( $data, 0, 100, true ) as $key => $value ) {
				$key = is_int( $key ) ? $key : sanitize_text_field( (string) $key );
				if ( is_array( $value ) ) {
					$out[ $key ] = self::clean( $value, $depth + 1 );
				} elseif ( is_bool( $value ) ) {
					$out[ $key ] = $value;
				} elseif ( is_scalar( $value ) ) {
					$out[ $key ] = sanitize_text_field( (string) $value );
				}
			}

			return $out;
		}

		/**
		 * Each Fixed Bundle member's variation: the shopper's pick, else its default.
		 *
		 * @param array $bundle Bundle payload.
		 * @return array[] Rows of { attributes }, in config order.
		 */
		private function member_picks_posted( array $bundle ) {
			$posted = is_array( $this->posted['items'] ?? null ) ? array_values( $this->posted['items'] ) : array();
			$picks  = self::member_picks( $bundle );
			foreach ( $picks as $i => $row ) {
				$chosen                    = is_array( $posted[ $i ]['attributes'] ?? null ) ? self::plain_picks( $posted[ $i ]['attributes'] ) : array();
				$picks[ $i ]['attributes'] = array_merge( $row['attributes'], $chosen );
			}

			return $picks;
		}

		/**
		 * Each Fixed Bundle member's default variation.
		 *
		 * @param array $bundle Bundle payload.
		 * @return array[] Rows of { attributes }, in config order.
		 */
		private static function member_picks( array $bundle ) {
			$picks = array();
			foreach ( array_values( (array) ( $bundle['discountConfig']['fixedBundleItems'] ?? array() ) ) as $i => $item ) {
				$id          = absint( is_array( $item['product'] ?? null ) ? ( $item['product']['id'] ?? 0 ) : 0 );
				$picks[ $i ] = array( 'attributes' => self::default_attributes( $id ? wc_get_product( $id ) : null ) );
			}

			return $picks;
		}

		/**
		 * The first purchasable variation's attributes, keyed by label.
		 *
		 * @param WC_Product|null $product Member product.
		 * @return array
		 */
		private static function default_attributes( $product ) {
			$map = self::variation_map( $product );
			foreach ( $map as $row ) {
				if ( ! in_array( '', $row, true ) ) {
					return $row;
				}
			}

			return array();
		}

		/**
		 * A variable product's purchasable variations, keyed by attribute label.
		 *
		 * @param WC_Product|null $product Product, or one of its variations.
		 * @return array<int,array<string,string>> Variation id => label => value; '' means any.
		 */
		private static function variation_map( $product ) {
			if ( $product instanceof WC_Product && $product->is_type( 'variation' ) ) {
				$product = wc_get_product( $product->get_parent_id() );
			}
			if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
				return array();
			}
			// It loads every variation, so it is kept per request and, until any product changes, in a transient.
			static $maps = array();
			if ( isset( $maps[ $product->get_id() ] ) ) {
				return $maps[ $product->get_id() ];
			}
			// The labels are translated, so the locale is part of what a cached map is valid for.
			$version   = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) . '|' . determine_locale() : '';
			$cache_key = 'wfob_bundle_vmap_' . $product->get_id();
			$cached    = '' !== $version ? get_transient( $cache_key ) : false;
			if ( is_array( $cached ) && ( $cached['version'] ?? '' ) === $version && is_array( $cached['map'] ?? null ) ) {
				$maps[ $product->get_id() ] = $cached['map'];

				return $cached['map'];
			}
			$labels = array();
			foreach ( $product->get_attributes() as $attribute ) {
				if ( is_object( $attribute ) && $attribute->get_variation() ) {
					$labels[ 'attribute_' . sanitize_title( (string) $attribute->get_name() ) ] = wc_attribute_label( (string) $attribute->get_name(), $product );
				}
			}
			$map      = array();
			$children = $product->get_children();
			if ( function_exists( '_prime_post_caches' ) ) {
				_prime_post_caches( $children, false, true );
			}
			foreach ( $children as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( ! $child instanceof WC_Product || ! $child->is_purchasable() ) {
					continue;
				}
				$row = array();
				foreach ( $child->get_variation_attributes() as $key => $value ) {
					if ( isset( $labels[ $key ] ) ) {
						$row[ $labels[ $key ] ] = (string) $value;
					}
				}
				if ( array() !== $row ) {
					$map[ (int) $child_id ] = $row;
				}
			}
			$maps[ $product->get_id() ] = $map;
			if ( '' !== $version ) {
				set_transient(
					$cache_key,
					array(
						'version' => $version,
						'map'     => $map,
					),
					DAY_IN_SECONDS
				);
			}

			return $map;
		}

		/**
		 * Add a Quantity Breaks bump at its tier's quantity, or its first unit's.
		 *
		 * WooCommerce settles the quantity before the line's data is built, so the
		 * tier's lines are worked out here and tag_tier_line() uses them.
		 *
		 * @param int $quantity   Quantity being added.
		 * @param int $product_id Product being added.
		 * @return int
		 */
		public function tier_quantity( $quantity, $product_id = 0 ) {
			if ( $this->placing || array() === $this->posted || array() !== $this->pending ) {
				return $quantity;
			}
			$product = wc_get_product( (int) $product_id );
			// Posted state left by an add that returned early must not re-quantify another product's add.
			$row         = self::bump_options( (int) $this->posted['wfob_id'], (string) $this->posted['key'] );
			$row_product = wc_get_product( absint( $row['id'] ?? 0 ) );
			if ( ! $product instanceof WC_Product || ! $row_product instanceof WC_Product || self::anchor_id( $product ) !== self::anchor_id( $row_product ) ) {
				return $quantity;
			}
			$bundle = self::bundle_for( $product, $this->posted['wfob_id'], $this->posted['key'] );
			if ( null === $bundle || self::QUANTITY_BREAKS !== (int) $bundle['discountType'] ) {
				return $quantity;
			}
			$rows = $this->tier_rows( $bundle, $product );
			if ( empty( $rows ) ) {
				return $quantity;
			}
			$total = 0;
			foreach ( $rows as $row ) {
				$total += max( 1, (int) ( $row['qty'] ?? 1 ) );
			}
			$this->tier_plan = array(
				'bundle' => $bundle,
				'offer'  => (string) $this->posted['offer'],
				'rows'   => $rows,
				'total'  => $total,
				'group'  => uniqid( 'wffn-bundles', false ),
			);

			return max( 1, (int) ( $rows[0]['qty'] ?? 1 ) );
		}

		/**
		 * The priced lines the posted tier and units resolve to; identical picks share a line.
		 *
		 * @param array      $bundle  Bundle payload.
		 * @param WC_Product $product Product the bump sells.
		 * @return array[] Empty when the tier or a unit's variation does not resolve.
		 */
		private function tier_rows( array $bundle, $product ) {
			$offer    = (string) ( $this->posted['offer'] ?? '' );
			$known    = false;
			$tier_qty = 0;
			foreach ( (array) ( $bundle['discountConfig']['quantityBreaksTiers'] ?? array() ) as $tier ) {
				if ( is_array( $tier ) && (string) ( $tier['id'] ?? '' ) === $offer ) {
					$known    = true;
					$tier_qty = absint( $tier['quantity'] ?? 0 );
				}
			}
			$anchor = wc_get_product( self::anchor_id( $product ) );
			if ( ! $known || ! $anchor instanceof WC_Product ) {
				return array();
			}
			$units = array();
			foreach ( array_slice( (array) ( $this->posted['items'] ?? array() ), 0, 100 ) as $unit ) {
				$attributes = is_array( $unit['attributes'] ?? null ) ? self::plain_picks( $unit['attributes'] ) : array();
				if ( array() !== $attributes ) {
					$units[] = array( 'attributes' => $attributes );
				}
			}
			// A variable tier is priced per unit's variation, so every unit needs one.
			if ( $anchor->is_type( 'variable' ) && array() === $units ) {
				return array();
			}

			$rows  = array();
			$total = 0;
			foreach ( WFOB_Bundles_Cart::tier_unit_members( $bundle, $offer, $anchor->get_id(), $units ) as $row ) {
				if ( is_array( $row ) && absint( $row['product_id'] ?? 0 ) ) {
					$rows[] = $row;
					$total += max( 1, (int) ( $row['qty'] ?? 1 ) );
				}
			}
			// The tier's price is earned at its quantity: fewer units posted would carry its discount on less.
			if ( $tier_qty > 0 && $total < $tier_qty ) {
				return array();
			}

			return $rows;
		}

		/**
		 * Turn the bump's own line into the bundle's lead line.
		 *
		 * A Fixed Bundle pack becomes the container carrying the pack price; a set's
		 * host carries its own priced share. add_pack_lines() places the rest.
		 *
		 * @param array $data         Cart item data being built.
		 * @param int   $product_id   Product being added.
		 * @param int   $variation_id Variation being added, 0 for a product without one.
		 * @return array
		 */
		public function pack_the_line( $data, $product_id = 0, $variation_id = 0 ) {
			// The bump keeps its posted request on the line; the bundle choices were read from it already.
			if ( isset( $data['_wfob_options']['posted_data'] ) && is_array( $data['_wfob_options']['posted_data'] ) ) {
				unset( $data['_wfob_options']['posted_data']['wfob_bundle_items'], $data['_wfob_options']['posted_data']['wfob_bundle_addons'] );
			}
			if ( empty( $data['_wfob_product'] ) || $this->placing || array() === $this->posted || isset( $data[ self::LINE_KEY ] ) ) {
				return $data;
			}
			$product = wc_get_product( (int) $product_id );
			$bundle  = self::bundle_for( $product, $this->posted['wfob_id'], $this->posted['key'] );
			if ( null === $bundle ) {
				return $data;
			}
			if ( WFOB_Bundles_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) $bundle['discountType'] ) {
				return $this->pack_the_set( $data, $bundle, $product, absint( $variation_id ) );
			}
			if ( self::QUANTITY_BREAKS === (int) $bundle['discountType'] ) {
				return $this->tag_tier_line( $data );
			}
			if ( 'single' === ( $this->posted['offer'] ?? '' ) ) {
				$data['wfob_bundle_offer'] = 'single';

				return $this->group_the_single( $data, $bundle, absint( $variation_id ? $variation_id : $product_id ) );
			}

			$members = WFOB_Bundles_Cart::fixed_bundle_members( $bundle, $this->member_picks_posted( $bundle ) );
			if ( empty( $members ) ) {
				return $data;
			}
			$total = 0.0;
			foreach ( $members as $m ) {
				$total += (float) ( $m['pricedUnit'] ?? 0 ) * max( 1, (int) ( $m['qty'] ?? 1 ) );
			}
			$title = (string) ( $members[0]['label'] ?? '' );
			if ( '' === trim( (string) ( $bundle['discountConfig']['fixedBundleTitle'] ?? '' ) ) ) {
				/* translators: %s: name of the product the bundle is offered on. */
				$title = sprintf( __( '%s - Bundle', 'woofunnels-order-bump' ), $product->get_name() );
			}
			$group = uniqid( 'wffn-bundles', false );
			$this->free_lead_stock( absint( $variation_id ? $variation_id : $product_id ) );
			$this->pending = array(
				'bundle'  => $bundle,
				'members' => $members,
				'group'   => $group,
				'title'   => $title,
			);
			$thumb_id      = 0;
			foreach ( $members as $m ) {
				$thumb_id = $thumb_id ? $thumb_id : ( absint( $m['variation_id'] ?? 0 ) ? absint( $m['variation_id'] ) : absint( $m['product_id'] ?? 0 ) );
			}
			$thumb = WFOB_Bundles_Cart::container_thumb( (array) $bundle['discountConfig'], self::anchor_id( $product ), $members );

			// place_on_line_product() moves this line onto Bundles' hidden pack product.
			$data[ self::LINE_KEY ] = array(
				'bundle'       => (string) ( $bundle['id'] ?? '' ),
				'type'         => WFOB_Bundles_Store::TYPE_FIXED_BUNDLES,
				'group'        => $group,
				'role'         => 'container',
				'offer'        => 'fb-bundle',
				'anchor'       => self::anchor_id( $product ),
				'label'        => $title,
				'bundleTitle'  => $title,
				'thumbId'      => $thumb_id,
				'thumbImgId'   => (int) ( $thumb['imgId'] ?? 0 ),
				'thumbSrc'     => (string) ( $thumb['src'] ?? '' ),
				'unit'         => $total,
				'pricedUnit'   => $total,
				'discountType' => WFOB_Bundles_Cart::offer_discount_type( $bundle['discountConfig'], WFOB_Bundles_Store::TYPE_FIXED_BUNDLES, 'fb-bundle' ),
			);
			WFOB_Bundles_Discount_Layer::claim_line( $data );

			return $data;
		}

		/**
		 * Give a single pick a group of its own when add-ons are ticked, so they ride it as on the product page.
		 *
		 * Bundles' own single add places the product at its own price, grouped with its add-ons only when
		 * it has some; left ungrouped, a ticked add-on was never placed and the widget kept drawing it ticked.
		 *
		 * @param array $data      Cart item data being built.
		 * @param array $bundle    Bundle payload.
		 * @param int   $priced_id The product or variation the line is.
		 * @return array
		 */
		private function group_the_single( array $data, array $bundle, $priced_id ) {
			if ( array() === self::addon_rows( $bundle, 'fb-single', (array) ( $this->posted['addons'] ?? array() ) ) ) {
				return $data;
			}
			$product = wc_get_product( $priced_id );
			$unit    = $product instanceof WC_Product ? (float) WFOB_Bundles_Currency::raw_base_price( $product ) : 0.0;
			$group   = uniqid( 'wffn-bundles', false );

			$this->pending = array(
				'single' => true,
				'bundle' => $bundle,
				'group'  => $group,
			);
			// As Bundles' own single add shapes it: no bundle id, so only the add-ons are attributed to the bundle.
			$data[ self::LINE_KEY ] = array(
				'bundle'     => '',
				'type'       => WFOB_Bundles_Store::TYPE_FIXED_BUNDLES,
				'group'      => $group,
				'role'       => 'single',
				'unit'       => $unit,
				'pricedUnit' => $unit,
			);
			WFOB_Bundles_Discount_Layer::claim_line( $data );

			return $data;
		}

		/**
		 * Turn the bump's own line into the host of a bought-together set.
		 *
		 * @param array      $data         Cart item data being built.
		 * @param array      $bundle       The set's bundle.
		 * @param WC_Product $product      The bump's product.
		 * @param int        $variation_id The variation the cart line is, 0 for a product without one.
		 * @return array
		 */
		private function pack_the_set( array $data, array $bundle, $product, $variation_id = 0 ) {
			$selection = is_array( $this->posted['items'] ?? null ) && isset( $this->posted['items']['items'] ) ? $this->posted['items'] : null;
			// A variable host is priced as the variation the cart line is, never as the attributes posted beside it: those would let a cheaper variation's price be stamped on a dearer line. Handed the variation itself, the set reads the host's attributes off it.
			if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
				$line_variation = $variation_id ? wc_get_product( $variation_id ) : null;
				if ( ! $line_variation instanceof WC_Product || (int) $line_variation->get_parent_id() !== (int) $product->get_id() ) {
					// No variation to price: leave the line a plain product rather than guess.
					return $data;
				}
				$product = $line_variation;
				if ( null !== $selection ) {
					unset( $selection['hostAttributes'] );
				}
			}
			if ( null !== $selection ) {
				// The bump's own line is always the host. A posted hostOn flag belongs to the product page's widget, where a shopper may untick "this item"; honouring it here would let the first companion take the host's place and its price.
				$selection['hostOn'] = true;
			}
			$built = WFOB_Bundles_Cart::fbt_set_members( $bundle, $product, $selection );
			if ( count( $built ) < 2 || 'fbt_host' !== (string) ( $built[0]['role'] ?? '' ) ) {
				return $data;
			}
			$host  = array_shift( $built );
			$rule  = WFOB_Bundles_FBT::rule( (array) $bundle['discountConfig'] );
			$group = uniqid( 'wffn-bundles', false );
			$meta  = array(
				'bundle'       => (string) ( $bundle['id'] ?? '' ),
				'type'         => WFOB_Bundles_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
				'group'        => $group,
				'offer'        => WFOB_Bundles_Store::FBT_OFFER,
				'label'        => (string) ( $host['label'] ?? '' ),
				'bundleTitle'  => (string) ( $host['label'] ?? '' ),
				'baseQty'      => 1,
				'discountType' => $rule['mode'],
				'fbtRule'      => $rule,
				'anchor'       => self::anchor_id( $product ),
				// Bundles takes the companions out with the host only for a set sold whole.
				'whole'        => true,
			);

			$this->pending          = array(
				'set'     => true,
				'members' => $built,
				'meta'    => $meta,
			);
			$data[ self::LINE_KEY ] = array_merge(
				$meta,
				array(
					'role'       => (string) ( $host['role'] ?? 'fbt_host' ),
					'unit'       => (float) $host['unit'],
					'pricedUnit' => (float) $host['pricedUnit'],
				)
			);
			WFOB_Bundles_Discount_Layer::claim_line( $data );

			return $data;
		}

		/**
		 * Turn the bump's own line into a Quantity Breaks tier's first line.
		 *
		 * @param array $data Cart item data being built.
		 * @return array
		 */
		private function tag_tier_line( array $data ) {
			$plan            = $this->tier_plan;
			$this->tier_plan = array();
			if ( array() === $plan ) {
				return $data;
			}
			$first  = $plan['rows'][0];
			$priced = wc_get_product( absint( $first['variation_id'] ?? 0 ) ? absint( $first['variation_id'] ) : absint( $first['product_id'] ?? 0 ) );
			$datum  = WFOB_Bundles_Cart::tier_bundle_data( $plan['bundle'], $plan['offer'], $priced, $plan['total'] );
			if ( ! is_array( $datum ) ) {
				return $data;
			}
			// A group even for one line, so the tier's add-ons leave with it.
			$datum['group'] = (string) $plan['group'];
			if ( count( $plan['rows'] ) > 1 ) {
				$datum = self::as_unit( $datum, $first, $plan['group'] );
			}
			$data[ self::LINE_KEY ]    = $datum;
			$data['wfob_bundle_place'] = array(
				'variation_id' => absint( $first['variation_id'] ?? 0 ),
				'variation'    => is_array( $first['variation'] ?? null ) ? $first['variation'] : array(),
			);
			WFOB_Bundles_Discount_Layer::claim_line( $data );
			$this->pending = array(
				'tier'   => true,
				'bundle' => $plan['bundle'],
				'offer'  => $plan['offer'],
				'rows'   => array_slice( $plan['rows'], 1 ),
				'base'   => $datum,
				'group'  => (string) $plan['group'],
			);

			return $data;
		}

		/**
		 * A tier's line datum recast as one of its units.
		 *
		 * @param array  $datum Tier datum.
		 * @param array  $row   Priced unit row.
		 * @param string $group The tier's cart group.
		 * @return array
		 */
		private static function as_unit( array $datum, array $row, $group ) {
			$datum['role']       = 'member';
			$datum['group']      = (string) $group;
			$datum['unit']       = (float) ( $row['unit'] ?? 0 );
			$datum['pricedUnit'] = (float) ( $row['pricedUnit'] ?? 0 );
			if ( '' !== (string) ( $row['label'] ?? '' ) ) {
				$datum['label'] = (string) $row['label'];
			}

			return $datum;
		}

		/**
		 * Put the bump's lead line on the product the bundle charges it on.
		 *
		 * A pack sits on Bundles' hidden pack product, as on the product page; a tier's
		 * first line on its first unit's variation.
		 *
		 * @param array $item Cart line being added.
		 * @return array
		 */
		public function place_on_line_product( $item ) {
			// Core's stock checks on the lead product are done; the members are checked as themselves.
			$this->free_lead_stock( 0 );
			if ( ! is_array( $item ) || empty( $item['_wfob_product'] ) || ! is_array( $item[ self::LINE_KEY ] ?? null ) ) {
				return $item;
			}
			$line = $item[ self::LINE_KEY ];
			$to   = null;
			if ( 'container' === (string) ( $line['role'] ?? '' ) && WFOB_Bundles_Store::TYPE_FIXED_BUNDLES === (int) ( $line['type'] ?? 0 ) ) {
				$to                = WFOB_Bundles_Cart::container_product();
				$item['variation'] = array();
			} elseif ( is_array( $item['wfob_bundle_place'] ?? null ) && absint( $item['wfob_bundle_place']['variation_id'] ?? 0 ) && absint( $item['wfob_bundle_place']['variation_id'] ?? 0 ) !== absint( $item['variation_id'] ?? 0 ) ) {
				$to                = wc_get_product( absint( $item['wfob_bundle_place']['variation_id'] ?? 0 ) );
				$item['variation'] = is_array( $item['wfob_bundle_place']['variation'] ?? null ) ? $item['wfob_bundle_place']['variation'] : array();
			}
			unset( $item['wfob_bundle_place'] );
			if ( ! $to instanceof WC_Product ) {
				return $item;
			}
			$item['product_id']   = $to->get_parent_id() ? $to->get_parent_id() : $to->get_id();
			$item['variation_id'] = $to->get_parent_id() ? $to->get_id() : 0;
			$item['data']         = $to;
			$item['data_hash']    = wc_get_cart_item_data_hash( $to );

			return $this->mark_line_image( $item );
		}

		/**
		 * Show a pack line in the bump with the image of the product the bump offers.
		 *
		 * @param array $item Cart line.
		 * @return array
		 */
		public function mark_line_image( $item ) {
			$line = is_array( $item ) && ! empty( $item['_wfob_product'] ) && is_array( $item[ self::LINE_KEY ] ?? null ) ? $item[ self::LINE_KEY ] : array();
			if ( 'container' !== (string) ( $line['role'] ?? '' ) || ! ( $item['data'] ?? null ) instanceof WC_Product ) {
				return $item;
			}
			$anchor = wc_get_product( absint( $line['anchor'] ?? 0 ) );
			if ( $anchor instanceof WC_Product && $anchor->get_image_id() ) {
				$this->watch_line_products();
				$item['data']->update_meta_data( self::IMAGE_META, (int) $anchor->get_image_id() );
			}

			return $item;
		}

		/**
		 * The image a marked pack line's product shows.
		 *
		 * @param int|string      $image_id Image id so far.
		 * @param WC_Product|null $product  Product.
		 * @return int|string
		 */
		public function line_image_id( $image_id, $product = null ) {
			$marked = $product instanceof WC_Product ? (int) $product->get_meta( self::IMAGE_META ) : 0;

			return $marked ? $marked : $image_id;
		}

		/**
		 * Name an accepted bump by the product it offers: a pack by that product, a tier
		 * by the product and the tier's label rather than one unit's variation.
		 *
		 * @param string          $name          Resolved product name.
		 * @param WC_Product|null $product       Product the merge tag resolved against.
		 * @param string          $cart_item_key Cart item key, empty until accepted.
		 * @param array           $product_data  The bump's saved row.
		 * @return string
		 */
		public function bump_line_name( $name, $product = null, $cart_item_key = '', $product_data = array() ) {
			$item = self::cart_item( (string) $cart_item_key );
			$line = null === $item || empty( $item['_wfob_product'] ) || ! is_array( $item[ self::LINE_KEY ] ?? null ) ? array() : $item[ self::LINE_KEY ];
			$own  = is_array( $product_data ) && absint( $product_data['id'] ?? 0 ) ? wc_get_product( absint( $product_data['id'] ) ) : null;
			if ( array() === $line || ! $own instanceof WC_Product ) {
				return $name;
			}
			$base = $own->get_parent_id() ? wc_get_product( $own->get_parent_id() ) : $own;
			if ( 'container' === (string) ( $line['role'] ?? '' ) ) {
				return $base->get_name();
			}
			if ( self::QUANTITY_BREAKS === (int) ( $line['type'] ?? 0 ) && '' !== (string) ( $line['label'] ?? '' ) ) {
				return self::titled( $base->get_name(), (string) $line['label'] );
			}

			return $name;
		}

		/**
		 * Place the rest of the bundle behind the bump's own line.
		 *
		 * Every line carries the bump's keys, so the module removes and tracks them as its own.
		 *
		 * @param string $cart_key     Cart key of the line just added.
		 * @param int    $product_id   Product added.
		 * @param int    $quantity     Quantity added.
		 * @param int    $variation_id Variation added.
		 * @param array  $variation    Variation attributes.
		 * @param array  $data         Cart item data.
		 * @return void
		 */
		public function add_pack_lines( $cart_key, $product_id = 0, $quantity = 1, $variation_id = 0, $variation = array(), $data = array() ) {
			if ( $this->placing || array() === $this->pending || empty( $data['_wfob_product'] ) || ! isset( $data[ self::LINE_KEY ] ) ) {
				return;
			}
			$pack          = $this->pending;
			$this->pending = array();
			$this->placing = true;
			$bump          = array(
				'_wfob_product'     => true,
				'_wfob_product_key' => (string) ( $data['_wfob_product_key'] ?? '' ),
				'_wfob_options'     => isset( $data['_wfob_options'] ) ? $data['_wfob_options'] : array(),
			);

			$placed = true;
			$thrown = false;
			$errors = count( wc_get_notices( 'error' ) );
			// The lead line already ran the bump's product swap; each member carries the bump's options and would run it again.
			$swap = has_filter( 'woocommerce_add_cart_item', array( 'WFOB_Common', 'handle_swap_product' ) );
			if ( false !== $swap ) {
				remove_filter( 'woocommerce_add_cart_item', array( 'WFOB_Common', 'handle_swap_product' ), $swap );
			}
			try {
				if ( ! empty( $pack['single'] ) ) {
					$this->add_addons( $pack['bundle'], (string) $pack['group'], $bump, 'fb-single' );

					return;
				}

				if ( ! empty( $pack['set'] ) ) {
					foreach ( (array) $pack['members'] as $m ) {
						$line   = array_merge(
							$pack['meta'],
							array(
								'role'       => (string) ( $m['role'] ?? 'fbt_companion' ),
								'unit'       => (float) $m['unit'],
								'pricedUnit' => (float) $m['pricedUnit'],
							)
						);
						$placed = self::add_line( $m, max( 1, (int) $quantity ), array_merge( $bump, array( self::LINE_KEY => $line ) ) ) && $placed;
					}

					return;
				}

				if ( ! empty( $pack['tier'] ) ) {
					foreach ( (array) $pack['rows'] as $row ) {
						$line   = self::as_unit( $pack['base'], $row, $pack['group'] );
						$placed = self::add_line( $row, max( 1, (int) ( $row['qty'] ?? 1 ) ), array_merge( $bump, array( self::LINE_KEY => $line ) ) ) && $placed;
					}
					if ( $placed && array() !== (array) $pack['rows'] ) {
						self::place_tier_heading( (string) $pack['group'] );
					}
					if ( $placed ) {
						$this->add_addons( $pack['bundle'], (string) $pack['group'], $bump, (string) $pack['offer'] );
					}

					return;
				}

				$bundle = $pack['bundle'];
				// A bump row's quantity buys that many bundles; adding each member at it lets the stock check see every unit.
				$packs = max( 1, (int) $quantity );
				foreach ( (array) $pack['members'] as $m ) {
					$line   = array(
						'bundle'      => (string) ( $bundle['id'] ?? '' ),
						'type'        => WFOB_Bundles_Store::TYPE_FIXED_BUNDLES,
						'group'       => (string) $pack['group'],
						'role'        => 'child',
						'offer'       => 'fb-bundle',
						'label'       => (string) ( $m['label'] ?? '' ),
						'bundleTitle' => (string) $pack['title'],
						'baseQty'     => max( 1, (int) ( $m['qty'] ?? 1 ) ),
						'unit'        => (float) ( $m['unit'] ?? 0 ),
						'pricedUnit'  => 0.0,
						'pricedShare' => (float) ( $m['pricedUnit'] ?? 0 ),
					);
					$placed = self::add_line( $m, max( 1, (int) ( $m['qty'] ?? 1 ) ) * $packs, array_merge( $bump, array( self::LINE_KEY => $line ) ) ) && $placed;
				}
				if ( $placed ) {
					$this->add_addons( $bundle, (string) $pack['group'], $bump, 'fb-bundle' );
				}
			} catch ( Throwable $e ) {
				$placed = false;
				$thrown = true;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->debug( 'A bundle bump could not place its lines: ' . $e->getMessage(), array( 'source' => 'wfob-funnelkit-bundles' ) );
				}
			} finally {
				$this->placing = false;
				if ( false !== $swap ) {
					add_filter( 'woocommerce_add_cart_item', array( 'WFOB_Common', 'handle_swap_product' ), $swap, 2 );
				}
				// All or nothing: the lead line takes every line of its group with it.
				if ( ! $placed ) {
					WC()->cart->remove_cart_item( $cart_key );
					self::forget_removed( $cart_key );
					// WooCommerce already says why a member's add failed (stock, purchasability); say something only when nothing did.
					if ( $thrown || count( wc_get_notices( 'error' ) ) <= $errors ) {
						wc_add_notice( __( 'Sorry, this bundle could not be added. Please try again.', 'woofunnels-order-bump' ), 'error' );
					}
				}
			}
		}

		/**
		 * Keep a pack that was never placed out of WooCommerce's undo list: the lead line, and
		 * every line that left with it, could otherwise be restored without the rest of the pack.
		 *
		 * @param string $cart_key Cart key of the pack's lead line.
		 * @return void
		 */
		private static function forget_removed( $cart_key ) {
			$cart = WC()->cart;
			if ( ! $cart ) {
				return;
			}
			$with = defined( WFOB_Bundles_Cart::class . '::REMOVED_WITH' ) ? WFOB_Bundles_Cart::REMOVED_WITH : '';
			foreach ( (array) $cart->removed_cart_contents as $key => $line ) {
				if ( (string) $key === (string) $cart_key || ( '' !== $with && (string) ( $line[ $with ] ?? '' ) === (string) $cart_key ) ) {
					unset( $cart->removed_cart_contents[ $key ] );
				}
			}
		}

		/**
		 * Show a tier spread over several variations as one bundle: a zero-priced
		 * heading line with its units nested beneath it, as the product page adds it.
		 *
		 * @param string $group The tier's cart group.
		 * @return void
		 */
		private static function place_tier_heading( $group ) {
			$cart      = WC()->cart;
			$container = WFOB_Bundles_Cart::container_product();
			$contents  = $cart->get_cart_contents();
			$units     = array();
			foreach ( $contents as $k => $ci ) {
				if ( (string) ( $ci[ self::LINE_KEY ]['group'] ?? '' ) === $group && 'member' === (string) ( $ci[ self::LINE_KEY ]['role'] ?? '' ) ) {
					$units[] = (string) $k;
				}
			}
			if ( ! $container || count( $units ) < 2 ) {
				return;
			}
			$members = array();
			foreach ( $units as $k ) {
				// A nested line is held to its baseQty, so it follows the bundle's stepper.
				$contents[ $k ][ self::LINE_KEY ]['nested']  = true;
				$contents[ $k ][ self::LINE_KEY ]['baseQty'] = max( 1, (int) ( $contents[ $k ]['quantity'] ?? 1 ) );
				$members[] = array(
					'product_id'   => absint( $contents[ $k ]['product_id'] ?? 0 ),
					'variation_id' => absint( $contents[ $k ]['variation_id'] ?? 0 ),
				);
			}
			$cart->set_cart_contents( $contents );
			$first  = (array) $contents[ $units[0] ][ self::LINE_KEY ];
			$anchor = absint( $contents[ $units[0] ]['product_id'] ?? 0 );
			$bundle = WFOB_Bundles_Store::get_config( (string) ( $first['bundle'] ?? '' ) );
			$thumb  = WFOB_Bundles_Cart::container_thumb( is_array( $bundle['discountConfig'] ?? null ) ? $bundle['discountConfig'] : array(), $anchor, $members, (string) ( $first['offer'] ?? '' ) );
			$label  = (string) ( $first['label'] ?? '' );
			$data   = array(
				self::LINE_KEY => array(
					'bundle'       => (string) ( $first['bundle'] ?? '' ),
					'type'         => (int) ( $first['type'] ?? 0 ),
					'group'        => $group,
					'role'         => 'heading',
					'offer'        => (string) ( $first['offer'] ?? '' ),
					'anchor'       => $anchor,
					'label'        => $label,
					/* translators: 1: bundle type name, 2: tier label. */
					'bundleTitle'  => sprintf( __( '%1$s: %2$s', 'woofunnels-order-bump' ), WFOB_Bundles_Store::type_label( (int) ( $first['type'] ?? 0 ) ), $label ),
					'thumbId'      => $members[0]['variation_id'] ? $members[0]['variation_id'] : $members[0]['product_id'],
					'thumbImgId'   => (int) ( $thumb['imgId'] ?? 0 ),
					'thumbSrc'     => (string) ( $thumb['src'] ?? '' ),
					'unit'         => 0.0,
					'pricedUnit'   => 0.0,
					'discountType' => (string) ( $first['discountType'] ?? '' ),
				),
			);
			// Added through the cart, as Bundles adds its own heading, so every add hook sees it; no bump hook sells it.
			$key      = $cart->add_to_cart( $container->get_id(), 1, 0, array(), $data );
			$contents = $cart->get_cart_contents();
			if ( ! $key || ! isset( $contents[ $key ] ) ) {
				return;
			}
			// The heading leads its units, so the cart lists the tier together.
			$heading = $contents[ $key ];
			$ordered = array();
			foreach ( $contents as $k => $ci ) {
				if ( $k === $key ) {
					continue;
				}
				if ( $k === $units[0] ) {
					$ordered[ $key ] = $heading;
				}
				$ordered[ $k ] = $ci;
			}
			$cart->set_cart_contents( $ordered );
		}

		/**
		 * Place the add-ons the shopper ticked, in the bundle's group.
		 *
		 * @param array  $bundle Bundle payload.
		 * @param string $group  The bundle's cart group.
		 * @param array  $bump   The bump's own cart item keys.
		 * @param string $offer  The offer the bundle was bought on.
		 * @return void
		 */
		private function add_addons( array $bundle, $group, array $bump, $offer ) {
			$type = (int) $bundle['discountType'];
			foreach ( self::addon_rows( $bundle, $offer, (array) ( $this->posted['addons'] ?? array() ) ) as $m ) {
				$line = array(
					'bundle'     => (string) ( $bundle['id'] ?? '' ),
					'type'       => $type,
					'group'      => $group,
					'role'       => (string) ( $m['role'] ?? '' ),
					'offer'      => (string) ( $m['offer'] ?? '' ),
					'label'      => (string) ( $m['label'] ?? '' ),
					'baseQty'    => max( 1, (int) ( $m['qty'] ?? 1 ) ),
					'unit'       => (float) ( $m['unit'] ?? 0 ),
					'pricedUnit' => (float) ( $m['pricedUnit'] ?? 0 ),
				);
				self::add_line( $m, max( 1, (int) ( $m['qty'] ?? 1 ) ), array_merge( $bump, array( self::LINE_KEY => $line ) ) );
			}
		}

		/**
		 * The add-on rows a posted selection earns, each one a row placed as an add-on.
		 *
		 * @param array  $bundle    Bundle payload.
		 * @param string $offer     The offer the bundle is bought on.
		 * @param array  $selection The add-on picks the widget posted.
		 * @return array[]
		 */
		private static function addon_rows( array $bundle, $offer, array $selection ) {
			/** This filter is documented in Bundles' includes/frontend/cart.php (ajax_add). */
			$addons = (array) apply_filters(
				'wffn_bundles_cart_addons',
				array(),
				array(
					'bundle'    => $bundle,
					'type'      => (int) $bundle['discountType'],
					'offer'     => (string) $offer,
					'selection' => array_merge( $selection, array( 'units' => array() ) ),
				)
			);
			$roles  = WFOB_Bundles_Cart::addon_roles();
			$rows   = array();
			foreach ( $addons as $m ) {
				// Only a row placed as an add-on rides the bundle, as on the product page and in One-Click Upsells.
				if ( is_array( $m ) && absint( $m['product_id'] ?? 0 ) && in_array( (string) ( $m['role'] ?? '' ), $roles, true ) ) {
					$rows[] = $m;
				}
			}

			return $rows;
		}

		/**
		 * Add one bundle line.
		 *
		 * @param array $m        Member row: product_id, variation_id, variation.
		 * @param int   $quantity Quantity.
		 * @param array $data     Cart item data.
		 * @return bool Whether the line went in.
		 */
		private static function add_line( array $m, $quantity, array $data ) {
			WFOB_Bundles_Discount_Layer::claim_line( $data );

			return false !== WC()->cart->add_to_cart(
				absint( $m['product_id'] ?? 0 ),
				$quantity,
				absint( $m['variation_id'] ?? 0 ),
				is_array( $m['variation'] ?? null ) ? $m['variation'] : array(),
				$data
			);
		}

		/**
		 * Leave a bundle line's price to Bundles, as on the product page.
		 *
		 * The module's own price meta on a line's product makes its price getter, and the
		 * currency compatibilities that read that meta, answer with the product's own price.
		 * Without it the line keeps the price Bundles sets, which Bundles converts for the
		 * active currency the same way it does on the product page.
		 *
		 * @param array $item Cart line the module has just priced.
		 * @return array
		 */
		public function leave_price_to_bundles( $item ) {
			if ( ! is_array( $item ) || empty( $item['_wfob_product'] ) || ! is_array( $item[ self::LINE_KEY ] ?? null ) || ! ( $item['data'] ?? null ) instanceof WC_Product ) {
				return $item;
			}
			foreach ( array( '_wfob_price', '_wfob_regular_price', '_wfob_sale_price', '_wfob_options', '_wfob_product' ) as $meta ) {
				$item['data']->delete_meta_data( $meta );
			}

			return $item;
		}

		/**
		 * Drop the bump's own discount from every row that sells a bundle: the bundle prices it.
		 *
		 * The module reads its rows through this filter wherever it prices one,
		 * including while the cart loads from the session, before any line can be looked up.
		 *
		 * @param array $products Bump rows, keyed by row key.
		 * @param int   $wfob_id  Bump id.
		 * @return array
		 */
		public function drop_bundle_discounts( $products, $wfob_id = 0 ) {
			// A bump with no saved design reads its default design from these same rows, which would re-enter here without end.
			if ( ! is_array( $products ) || $this->dropping || self::in_bump_editor() ) {
				return $products;
			}
			$this->dropping = true;
			try {
				foreach ( $products as $key => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					// Answered once per bump row and request: the module reads its rows on every price it works out.
					$memo = (int) $wfob_id . '|' . $key . '|' . absint( $row['id'] ?? 0 );
					if ( ! isset( self::$sells_bundle[ $memo ] ) ) {
						self::$sells_bundle[ $memo ] = self::settings( (int) $wfob_id, (string) $key, $row )['enabled'] && null !== self::sellable_for_anchor( self::row_anchor( absint( $row['id'] ?? 0 ) ) );
					}
					if ( self::$sells_bundle[ $memo ] ) {
						$products[ $key ]['discount_amount'] = 0;
					}
				}
			} finally {
				$this->dropping = false;
			}

			return $products;
		}

		/**
		 * Whether this is the bump editor's own request: an admin screen, or one of its `wfob_*` admin-ajax actions.
		 *
		 * The editor and its add, remove and save handlers read the rows to show or save them back, so they
		 * must see the stored discount; the storefront prices through wc-ajax and page loads, never admin-ajax.
		 *
		 * @return bool
		 */
		private static function in_bump_editor() {
			if ( ! is_admin() ) {
				return false;
			}
			if ( ! wp_doing_ajax() ) {
				return true;
			}
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Only names the request; each handler checks its own nonce and capability.

			return 0 === strpos( $action, 'wfob_' );
		}

		/**
		 * No discount from the module's own pass on a bundle line.
		 *
		 * @param float|string $amount Discount amount.
		 * @param string       $type   Discount type.
		 * @param string       $key    Cart item key.
		 * @return float|string
		 */
		public function no_early_discount( $amount, $type = '', $key = '' ) {
			$item = self::cart_item( (string) $key );

			return null !== $item && is_array( $item[ self::LINE_KEY ] ?? null ) ? 0 : $amount;
		}

		/**
		 * The bump's own colours, mapped onto the widget's tokens.
		 *
		 * @param int $wfob_id Bump id.
		 * @return string Style element, or ''.
		 */
		private static function design_block( $wfob_id ) {
			if ( ! $wfob_id || ! class_exists( 'WFOB_Common' ) ) {
				return '';
			}
			$design = self::design( (int) $wfob_id );
			$hex    = function ( $key ) use ( $design ) {
				$value = trim( (string) ( $design[ $key ] ?? '' ) );

				return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? $value : '';
			};
			$accent = $hex( 'border_color' );
			$badge  = $hex( 'exclusive_content_color' );
			$tokens = array_filter(
				array(
					'--wffn-bundles-border-active'       => $accent,
					'--wffn-bundles-radio-active'        => $accent,
					'--wffn-bundles-control-accent'      => $accent,
					'--wffn-bundles-connector'           => $accent,
					'--wffn-bundles-progress-fill'       => $accent,
					'--wffn-bundles-badge-bg'            => $badge,
					'--wffn-bundles-badge-fg'            => '' === $badge ? '' : ( self::is_light( $badge ) ? '#353030' : '#ffffff' ),
					// A light badge colour would erase the savings text it also colours.
					'--wffn-bundles-success'             => self::is_light( $badge ) ? '' : $badge,
					'--wffn-bundles-heading-color'       => $hex( 'heading_color' ),
					'--wffn-bundles-title-color'         => $hex( 'content_color' ),
					'--wffn-bundles-sale-price-color'    => $hex( 'price_sale_color' ),
					'--wffn-bundles-regular-price-color' => $hex( 'price_color' ),
				),
				'strlen'
			);
			if ( empty( $tokens ) ) {
				return '';
			}
			$declarations = '';
			foreach ( $tokens as $property => $value ) {
				$declarations .= $property . ':' . $value . ';';
			}

			return sprintf( '<style>body .wfob-bundle-design-%1$s .wffn-bundles{%2$s}</style>', esc_attr( (string) $wfob_id ), esc_html( $declarations ) );
		}

		/**
		 * Whether dark text belongs on this colour (Rec. 601 luma).
		 *
		 * @param string $hex Colour.
		 * @return bool
		 */
		private static function is_light( $hex ) {
			$hex = ltrim( (string) $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			if ( 6 !== strlen( $hex ) ) {
				return false;
			}

			return ( hexdec( substr( $hex, 0, 2 ) ) * 299 + hexdec( substr( $hex, 2, 2 ) ) * 587 + hexdec( substr( $hex, 4, 2 ) ) * 114 ) / 1000 > 160;
		}

		/**
		 * Every published bump id, read in pages so no store size is cut off.
		 *
		 * Read only when the rows cache is rebuilt, so the pages cost nothing on a normal request.
		 *
		 * @param int $per_page Bumps per page.
		 * @return int[]
		 */
		private static function all_bump_ids( $per_page = 200 ) {
			$per_page = max( 1, (int) $per_page );
			$pages    = array();
			// A ceiling on the pages keeps a misbehaving query from looping for ever.
			for ( $page = 1; $page <= 500; $page++ ) {
				$batch   = get_posts(
					array(
						'post_type'      => 'wfob_bump',
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'no_found_rows'  => true,
					)
				);
				$pages[] = array_map( 'intval', $batch );
				if ( count( $batch ) < $per_page ) {
					break;
				}
			}

			return array() === $pages ? array() : array_merge( ...$pages );
		}

		/**
		 * The bump rows that have their bundle setting on, read once and kept until a bump changes.
		 *
		 * One request rebuilds them at a time; a request that finds another rebuilding reads the
		 * last rows built. When there are none it builds only the bumps it asked for, or every
		 * bump when it asked for none.
		 *
		 * @param int[]|null $bump_ids The bumps the caller needs, or null for every bump.
		 * @return array[] Rows of { wfob_id, key, id }.
		 */
		private static function enabled_rows( $bump_ids = null ) {
			if ( is_array( self::$rows ) ) {
				return self::$rows;
			}
			$rows = get_transient( self::ROWS_CACHE );
			if ( is_array( $rows ) ) {
				self::$rows = $rows;

				return $rows;
			}
			$locked = false !== get_transient( self::ROWS_LOCK );
			if ( $locked ) {
				$stale = get_transient( self::ROWS_STALE );
				if ( is_array( $stale ) ) {
					self::$rows = $stale;

					return $stale;
				}
				// A cold cache: the requests arriving during the rebuild each read only their own bumps, not every bump.
				if ( is_array( $bump_ids ) ) {
					return self::build_rows( $bump_ids );
				}
				// A caller wanting every bump waits briefly for the rebuild instead of repeating it; nothing is kept, so a later call retries.
				for ( $tries = 0; $tries < 15; $tries++ ) {
					usleep( 200000 );
					$rows = get_transient( self::ROWS_CACHE );
					if ( is_array( $rows ) ) {
						self::$rows = $rows;

						return $rows;
					}
				}

				return array();
			} else {
				set_transient( self::ROWS_LOCK, 1, MINUTE_IN_SECONDS );
			}
			try {
				$started = microtime( true );
				$rows    = self::build_rows( self::all_bump_ids() );
				// A bump saved while this ran dropped the cache; storing these rows would undo that.
				$cleared = (float) get_transient( self::ROWS_CLEARED );
				if ( ! $locked && $cleared < $started ) {
					set_transient( self::ROWS_CACHE, $rows, DAY_IN_SECONDS );
					set_transient( WFOB_FunnelKit_Bundles_Loader::ROWS_IDS, array_values( array_unique( array_map( 'intval', wp_list_pluck( $rows, 'wfob_id' ) ) ) ), DAY_IN_SECONDS );
					set_transient( self::ROWS_STALE, $rows, WEEK_IN_SECONDS );
				}
			} finally {
				if ( ! $locked ) {
					delete_transient( self::ROWS_LOCK );
				}
			}
			self::$rows = $rows;

			return $rows;
		}

		/**
		 * The rows of these bumps that have their bundle setting on.
		 *
		 * @param int[] $bump_ids Bump ids.
		 * @return array[] Rows of { wfob_id, key, id, anchor }.
		 */
		private static function build_rows( array $bump_ids ) {
			$rows = array();
			// One query for every bump's meta instead of one per bump.
			update_meta_cache( 'post', $bump_ids );
			foreach ( $bump_ids as $wfob_id ) {
				// A bump in a funnel or an A/B test shows only on that funnel's checkout, never on the store's own.
				$standalone = '' === (string) get_post_meta( (int) $wfob_id, '_bwf_in_funnel', true ) && '' === (string) get_post_meta( (int) $wfob_id, '_bwf_ab_variation_of', true );
				foreach ( (array) WFOB_Common::get_bump_products( (int) $wfob_id ) as $product_key => $row ) {
					if ( is_array( $row ) && self::settings( (int) $wfob_id, (string) $product_key, $row )['enabled'] ) {
						$rows[] = array(
							'wfob_id'    => (int) $wfob_id,
							'key'        => (string) $product_key,
							'id'         => absint( $row['id'] ?? 0 ),
							'anchor'     => self::row_anchor( absint( $row['id'] ?? 0 ) ),
							'standalone' => $standalone,
						);
					}
				}
			}

			return $rows;
		}

		/**
		 * The anchor_id() of a product id, read from the cached post rather than a built product: a variation's parent, else the product itself; 0 when it is not a product.
		 *
		 * @param int $product_id Product the bump row offers.
		 * @return int
		 */
		private static function row_anchor( $product_id ) {
			$post = $product_id ? get_post( $product_id ) : null;
			if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
				return 0;
			}

			return 'product_variation' === $post->post_type && $post->post_parent > 0 ? (int) $post->post_parent : (int) $post->ID;
		}

		/**
		 * Drop the cached rows.
		 *
		 * @return void
		 */
		public static function forget_rows() {
			self::$rows         = null;
			self::$sellable     = array();
			self::$designs      = array();
			self::$bundles      = array();
			self::$sells_bundle = array();
			delete_transient( self::ROWS_CACHE );
			delete_transient( WFOB_FunnelKit_Bundles_Loader::ROWS_IDS );
			set_transient( self::ROWS_CLEARED, microtime( true ), HOUR_IN_SECONDS );
		}

		/**
		 * Hook the product getters that read this class's line meta, once, when a line first gets it.
		 *
		 * Only cart line products carry the meta, so the getters stay off every other product.
		 *
		 * @return void
		 */
		private function watch_line_products() {
			if ( $this->watching ) {
				return;
			}
			$this->watching = true;
			add_filter( 'woocommerce_product_get_image_id', array( $this, 'line_image_id' ), 10, 2 );
		}

		/**
		 * The live bundles the bump rows with their setting on sell.
		 *
		 * @param int[]|null $bump_ids Only these bumps' rows; null for every bump.
		 * @return array[] Bundle payloads, keyed by bundle id.
		 */
		private static function sold_bundles( $bump_ids = null ) {
			$bundles = array();
			$seen    = array();
			foreach ( self::enabled_rows( $bump_ids ) as $row ) {
				if ( is_array( $bump_ids ) && ! in_array( (int) $row['wfob_id'], $bump_ids, true ) ) {
					continue;
				}
				// No funnel decides this checkout, so Order Bumps offers only bumps outside any funnel or A/B test.
				if ( null === $bump_ids && false === ( $row['standalone'] ?? true ) ) {
					continue;
				}
				// Two bumps offering the same product sell the same bundle.
				if ( isset( $seen[ $row['id'] ] ) ) {
					continue;
				}
				$seen[ $row['id'] ] = true;
				// A product no live bundle can target is not loaded at all.
				if ( ! empty( $row['anchor'] ) && method_exists( WFOB_Bundles_Store::class, 'may_target_product' ) && ! WFOB_Bundles_Store::may_target_product( $row['anchor'], self::types() ) ) {
					continue;
				}
				$bundle = self::sellable_for_anchor( self::row_anchor( (int) $row['id'] ) );
				if ( null !== $bundle ) {
					$bundles[ (string) ( $bundle['id'] ?? '' ) ] = $bundle;
				}
			}

			return $bundles;
		}

		/**
		 * The bumps the funnel gives this checkout, or null when no funnel decides and any bump may show.
		 *
		 * @return int[]|null
		 */
		private static function checkout_bump_ids() {
			// The same source the bump setup reads, before its cart rules narrow it.
			/** This filter is documented in includes/class-wfob-public.php */
			$bumps = apply_filters( 'wfob_bumps_from_external_base', false, array() );
			if ( ! is_array( $bumps ) ) {
				return null;
			}

			return array_values(
				array_filter(
					array_map(
						function ( $bump ) {
							return (int) ( $bump instanceof WP_Post ? $bump->ID : $bump );
						},
						$bumps
					)
				)
			);
		}

		/**
		 * Load the widget's styles, behavior and this glue on a checkout that may show one.
		 *
		 * The widget renders in fragment responses that print no queue of their own.
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			// The order-received page counts as a checkout but shows no bumps.
			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
				return;
			}
			$bundles = self::sold_bundles( self::checkout_bump_ids() );
			if ( array() === $bundles ) {
				return;
			}
			// Only the skins and behavior of the types the bumps sell.
			$sold    = array();
			$modules = array();
			foreach ( $bundles as $bundle ) {
				$sold[]    = (int) $bundle['discountType'];
				$modules[] = (array) WFOB_Bundles_Assets::modules_for( (int) $bundle['discountType'], (array) ( $bundle['discountConfig'] ?? array() ) );
			}
			$modules = array() === $modules ? array() : array_merge( ...$modules );
			$others  = array_filter(
				array_keys( WFOB_Bundles_Registry::types() ),
				function ( $id ) use ( $sold ) {
					return is_int( $id ) && ! in_array( $id, $sold, true );
				}
			);
			WFOB_Bundles_Assets::enqueue_skin_styles( array_values( $others ) );
			WFOB_Bundles_Assets::enqueue_behavior( true, array_values( array_unique( $modules ) ) );
			WFOB_Bundles_Product_Display::ensure_cart_config();
			self::enqueue_checkout_sheet();
			self::enqueue_summary_assets();

			$version = defined( 'WFOB_VERSION' ) ? WFOB_VERSION : false;
			wp_enqueue_style( 'wfob-funnelkit-bundles', WFOB_PLUGIN_URL . '/assets/css/funnelkit-bundles.min.css', array(), $version );
			wp_enqueue_script( 'wfob-funnelkit-bundles', WFOB_PLUGIN_URL . '/assets/js/funnelkit-bundles.min.js', array( WFOB_Bundles_Assets::HANDLE ), $version, true );
		}

		/**
		 * Bundles' own corrections for the FunnelKit checkout, which it loads only for
		 * a checkout whose product rows sell a bundle; a bump selling one needs them too.
		 *
		 * @return void
		 */
		private static function enqueue_checkout_sheet() {
			$handle = 'wffn-bundles-compat-funnel-builder';
			$rel    = 'assets/compat/funnel-builder.css';
			if ( ! is_singular( 'wfacp_checkout' ) || wp_style_is( $handle, 'enqueued' ) || ! defined( 'WFFN_BUNDLES_URL' ) || ! defined( 'WFFN_BUNDLES_DIR' ) || ! file_exists( WFFN_BUNDLES_DIR . '/' . $rel ) ) {
				return;
			}
			wp_enqueue_style( $handle, WFFN_BUNDLES_URL . $rel, array( WFOB_Bundles_Assets::style_handle( 'assets/skins/_shared.css' ) ), (string) filemtime( WFFN_BUNDLES_DIR . '/' . $rel ) );
		}

		/**
		 * The order summary's bundle styling and fold toggle, for a bundle a bump adds.
		 *
		 * Bundles loads these only when the cart already holds a bundle or the checkout's
		 * own product rows sell one. A bump adds its bundle by AJAX, after the page is
		 * printed, so without this the summary redraws with an unstyled "Hide / Show items"
		 * button, both labels showing, and full-size member rows.
		 *
		 * @return void
		 */
		private static function enqueue_summary_assets() {
			if ( ! is_singular( 'wfacp_checkout' ) || ! defined( 'WFFN_BUNDLES_URL' ) || ! defined( 'WFFN_BUNDLES_DIR' ) ) {
				return;
			}
			$grouping = 'wffn-bundles-cart-grouping';
			$summary  = 'wffn-bundles-compat-funnel-builder-summary';
			$sheet    = 'assets/skins/cart-blocks.css';
			$css      = 'assets/compat/funnel-builder-summary.css';
			$js       = 'assets/compat/funnel-builder-summary.js';
			// Bundles' summary files not shipped by this Bundles build: nothing to load.
			if ( ! file_exists( WFFN_BUNDLES_DIR . '/' . $sheet ) || ! file_exists( WFFN_BUNDLES_DIR . '/' . $css ) || ! file_exists( WFFN_BUNDLES_DIR . '/' . $js ) ) {
				return;
			}
			if ( ! wp_style_is( $grouping, 'enqueued' ) ) {
				wp_enqueue_style( $grouping, WFFN_BUNDLES_URL . $sheet, array(), (string) filemtime( WFFN_BUNDLES_DIR . '/' . $sheet ) );
			}
			if ( ! wp_style_is( $summary, 'enqueued' ) ) {
				wp_enqueue_style( $summary, WFFN_BUNDLES_URL . $css, array( $grouping ), (string) filemtime( WFFN_BUNDLES_DIR . '/' . $css ) );
			}
			if ( ! wp_script_is( $summary, 'enqueued' ) ) {
				wp_enqueue_script( $summary, WFFN_BUNDLES_URL . $js, array(), (string) filemtime( WFFN_BUNDLES_DIR . '/' . $js ), true );
			}
		}
	}

	// WFOB_FunnelKit_Bundles_Loader includes this file only on a request that can use it; stay out against a Bundles build without the render API used below.
	if ( class_exists( 'WFFN\Bundles\Includes\Storage\Bundle_Store' ) && method_exists( 'WFFN\Bundles\Includes\Compat\Render_Api', 'widget_html' ) ) {
		// An older Bundles missing a function this glue calls would fatal a checkout: stay out and let the bump sell plain products.
		$wfob_missing_api = WFOB_Compatibility_FunnelKit_Bundles::missing_api();
		if ( '' === $wfob_missing_api ) {
			WFOB_Compatibility_FunnelKit_Bundles::get_instance();
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( 'The Bundles add-on is not loaded, this Bundles has no ' . $wfob_missing_api . '.', array( 'source' => 'wfob-funnelkit-bundles' ) );
		}
	}
}
