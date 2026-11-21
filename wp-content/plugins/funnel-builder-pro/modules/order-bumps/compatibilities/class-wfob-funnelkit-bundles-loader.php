<?php
/**
 * FunnelKit Bundles: read the order-bump glue only on a request that can use it.
 *
 * @package WFOB
 */

if ( ! class_exists( 'WFOB_FunnelKit_Bundles_Loader' ) ) {
	/**
	 * Reads the Bundles glue (WFOB_Compatibility_FunnelKit_Bundles) only on a request that can use it.
	 *
	 * The glue is large and hooks the cart, so it stays out of every request that neither shows a
	 * checkout, reads a bump that sells a bundle, edits a bump nor carries a bump's bundle line in its
	 * cart. Each trigger runs one priority ahead of the glue's own callback on the same hook, so the
	 * glue, once read, still runs in that same pass and in every hook after it.
	 *
	 * Dropping the glue's cached rows needs only the transients, so that never reads the glue.
	 */
	class WFOB_FunnelKit_Bundles_Loader {

		/** Transient holding the bump rows that sell a bundle. */
		const ROWS_CACHE = 'wfob_bundle_bump_rows_v2';

		/** Transient holding when ROWS_CACHE was last dropped, so a rebuild begun earlier does not store stale rows. */
		const ROWS_CLEARED = 'wfob_bundle_bump_rows_cleared';

		/** Transient set while one request rebuilds ROWS_CACHE, so requests that find it missing at the same time do not all rebuild it. */
		const ROWS_LOCK = 'wfob_bundle_bump_rows_lock';

		/** Transient keeping the last rows built, which a bump save does not drop: what the others read while one request rebuilds. */
		const ROWS_STALE = 'wfob_bundle_bump_rows_v2_stale';

		/** Transient holding only the bump ids in ROWS_CACHE, written and dropped with it: all a checkout request reads. */
		const ROWS_IDS = 'wfob_bundle_bump_ids';

		/** Autoloaded copy of ROWS_IDS with its expiry, so a checkout reads the bump ids without a query of its own. */
		const IDS_OPTION = 'wfob_bundle_bump_ids_autoload';

		/** Cart line key Bundles stores its line data under. */
		const LINE_KEY = 'wffn_bundles_bundle';

		/**
		 * Bump ids with a row that sells a bundle, from the glue's cached rows; null when not cached.
		 *
		 * @var int[]|null|false False until read.
		 */
		private static $bump_ids = false;

		/**
		 * Whether the glue was read this request.
		 *
		 * @var bool
		 */
		private static $loaded = false;

		/**
		 * Hook => priority one ahead of the glue's own callback on it, for hooks that always need the glue.
		 *
		 * @return array<string,int>
		 */
		private static function triggers() {
			return array(
				'wfob_before_add_to_cart'        => 9,
				'wfob_qv_summary'                => 19,
				// The bump editor's design tab, product search and REST rows.
				'wfob_admin_design_schema'       => 9,
				'wffn_woocommerce_json_search_found_products' => 9,
				'wffn_rest_api_bump_add_product' => 9,
				'wffn_rest_api_bump_get_product' => 9,
			);
		}

		/**
		 * Register the triggers and the cache hooks.
		 *
		 * This file is required only while Bundles is active (class-wfob-plugin-compatibilities.php),
		 * and each cache hook returns on a key or post type check before doing any work.
		 *
		 * @return void
		 */
		public static function init() {
			if ( self::request_needs_glue() ) {
				self::load();
			} else {
				self::add_triggers();
			}

			add_action( 'save_post_wfob_bump', array( __CLASS__, 'forget_rows' ) );
			add_action( 'deleted_post', array( __CLASS__, 'forget_rows_on_delete' ), 10, 2 );
			add_action( 'updated_post_meta', array( __CLASS__, 'forget_rows_on_meta' ), 10, 3 );
			add_action( 'added_post_meta', array( __CLASS__, 'forget_rows_on_meta' ), 10, 3 );
			add_action( 'deleted_post_meta', array( __CLASS__, 'forget_rows_on_meta' ), 10, 3 );
			add_action( 'wffn_bundles_bundle_saved', array( __CLASS__, 'forget_rows' ) );
			add_action( 'wffn_bundles_bundle_deleted', array( __CLASS__, 'forget_rows' ) );
			// Mirror every write and drop of ROWS_IDS, wherever it happens, into the autoloaded copy.
			add_action( 'set_transient_' . self::ROWS_IDS, array( __CLASS__, 'mirror_ids' ), 10, 2 );
			add_action( 'deleted_transient', array( __CLASS__, 'mirror_ids_dropped' ) );
		}

		/**
		 * The checkout's own AJAX, where bumps are shown, added and removed, and the bump editor's admin-ajax.
		 *
		 * @return bool
		 */
		private static function request_needs_glue() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Only names the request; each handler checks its own nonce and capability.
			$wc_ajax = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : '';
			$action  = wp_doing_ajax() && isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
			// phpcs:enable

			// The editor's admin-ajax needs the glue before any bump sells a bundle; the storefront only once one does.
			if ( 0 === strpos( $action, 'wfob_' ) ) {
				return true;
			}

			return ( in_array( $wc_ajax, array( 'update_order_review', 'checkout' ), true ) || 0 === strpos( $wc_ajax, 'wfob_' ) ) && self::store_sells_bundle_bumps();
		}

		/**
		 * Hook the triggers that read the glue later in the request.
		 *
		 * @return void
		 */
		private static function add_triggers() {
			foreach ( self::triggers() as $hook => $priority ) {
				add_filter( $hook, array( __CLASS__, 'load_and_pass' ), $priority );
			}
			// The module reads bump rows on every cart totals pass; only a bump that sells a bundle needs the glue.
			add_filter( 'wfob_bump_products', array( __CLASS__, 'load_for_bump_rows' ), 9, 2 );
			add_filter( 'wfob_bump_product_in_stock', array( __CLASS__, 'load_for_bump_stock' ), 9, 3 );
			add_action( 'wfob_before_pre_checked_add', array( __CLASS__, 'load_for_bump' ), 9 );
			// Before the glue's own enqueue at 20, which needs it on a checkout that may show a bundle.
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'load_on_checkout' ), 1 );
			// A bump's bundle line in the cart is priced and shown by the glue on any page: cart, side cart, Store API.
			add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'load_for_line' ), 9 );
			// A page switch re-adds every stored bump line; only a bundle's needs the glue.
			add_filter( 'wfob_re_add_selected_bump', array( __CLASS__, 'load_for_re_add' ), 9, 2 );
		}

		/**
		 * Read the glue, once. It starts itself, or stays out against a Bundles that lacks its API.
		 *
		 * @return void
		 */
		public static function load() {
			// The triggers stay hooked: unhooking one while its priority runs makes WordPress skip the next priority, the glue's own.
			if ( self::$loaded ) {
				return;
			}
			self::$loaded = true;

			try {
				include_once __DIR__ . '/class-wfob-compatibility-funnelkit-bundles.php';
			} catch ( Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->debug( 'The Bundles add-on could not be loaded: ' . $e->getMessage(), array( 'source' => 'wfob-funnelkit-bundles' ) );
				}
			}
			// Read for a missing rows cache: rebuild it once this request is done, so the next ones can skip the glue again.
			if ( null === self::bundle_bump_ids() ) {
				add_action( 'shutdown', array( __CLASS__, 'warm_rows' ) );
			}
		}

		/**
		 * Rebuild the rows cache, or, when the glue declined to start, cache that no bump sells a bundle.
		 *
		 * A bump or bundle save, or the day's expiry, drops that answer and asks again. Only one request
		 * rebuilds at a time (the glue takes ROWS_LOCK); the others keep reading the glue until the rows are stored.
		 *
		 * @return void
		 */
		public static function warm_rows() {
			if ( is_array( get_transient( self::ROWS_CACHE ) ) || false !== get_transient( self::ROWS_LOCK ) ) {
				return;
			}
			if ( class_exists( 'WFOB_Compatibility_FunnelKit_Bundles', false ) && WFOB_Compatibility_FunnelKit_Bundles::is_started() ) {
				WFOB_Compatibility_FunnelKit_Bundles::warm_rows();
			} else {
				set_transient( self::ROWS_CACHE, array(), DAY_IN_SECONDS );
				set_transient( self::ROWS_IDS, array(), DAY_IN_SECONDS );
			}
		}

		/**
		 * Read the glue on a trigger, and hand the filtered value back as it came.
		 *
		 * @param mixed $value The hook's first argument.
		 * @return mixed
		 */
		public static function load_and_pass( $value = null ) {
			self::load();

			return $value;
		}

		/**
		 * Read the glue for a bump with a row that sells a bundle, or when the cached rows are gone (the glue rebuilds them).
		 *
		 * @param int $wfob_id Bump id.
		 * @return void
		 */
		public static function load_for_bump( $wfob_id ) {
			if ( self::$loaded ) {
				return;
			}
			$bump_ids = self::bundle_bump_ids();
			if ( null === $bump_ids || in_array( (int) $wfob_id, $bump_ids, true ) ) {
				self::load();
			}
		}

		/**
		 * The bumps whose rows sell a bundle, from the glue's cached rows; read once per request.
		 *
		 * @return int[]|null Null when the cache is missing: only the glue can rebuild it.
		 */
		private static function bundle_bump_ids() {
			if ( false === self::$bump_ids ) {
				$copy = get_option( self::IDS_OPTION );
				if ( is_array( $copy ) && is_array( $copy['ids'] ?? null ) && (int) ( $copy['until'] ?? 0 ) > time() ) {
					self::$bump_ids = array_map( 'intval', $copy['ids'] );

					return self::$bump_ids;
				}
				$ids = get_transient( self::ROWS_IDS );
				if ( is_array( $ids ) ) {
					// Seed the autoloaded copy for a store whose ids were cached before it existed; an hour, as the real expiry is not read here.
					self::mirror_ids( $ids, HOUR_IN_SECONDS );
				}
				if ( ! is_array( $ids ) ) {
					$rows = get_transient( self::ROWS_CACHE );
					$ids  = is_array( $rows ) ? wp_list_pluck( $rows, 'wfob_id' ) : null;
				}
				self::$bump_ids = is_array( $ids ) ? array_map( 'intval', $ids ) : null;
			}

			return self::$bump_ids;
		}

		/**
		 * Whether any bump may sell a bundle: yes when the cached rows hold one, or are missing.
		 *
		 * @return bool
		 */
		private static function store_sells_bundle_bumps() {
			// No live bundle means no bump can sell one: skip the cached-rows read (Bundles keeps this flag in an autoloaded option).
			if ( class_exists( 'WFFN\\Bundles\\Includes\\Storage\\Bundle_Store' ) && ! \WFFN\Bundles\Includes\Storage\Bundle_Store::has_active() ) {
				return false;
			}

			return array() !== self::bundle_bump_ids();
		}

		/**
		 * Run load_for_bump() on a read of a bump's rows.
		 *
		 * @param mixed $products Bump rows.
		 * @param int   $wfob_id  Bump id.
		 * @return mixed
		 */
		public static function load_for_bump_rows( $products, $wfob_id = 0 ) {
			self::load_for_bump( $wfob_id );

			return $products;
		}

		/**
		 * Run load_for_bump() on a bump product's stock check.
		 *
		 * @param bool  $in_stock Whether the bump product is in stock.
		 * @param mixed $product  The bump product.
		 * @param int   $wfob_id  Bump id.
		 * @return bool
		 */
		public static function load_for_bump_stock( $in_stock, $product = null, $wfob_id = 0 ) {
			unset( $product );
			self::load_for_bump( $wfob_id );

			return $in_stock;
		}

		/**
		 * Read the glue on a checkout page that may show a bump that sells a bundle.
		 *
		 * @return void
		 */
		public static function load_on_checkout() {
			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! self::store_sells_bundle_bumps() ) {
				return;
			}
			$bump_ids = self::bundle_bump_ids();
			// The same source the glue's enqueue reads: when the funnel names this checkout's bumps, only one of those that sells a bundle needs the glue.
			/** This filter is documented in includes/class-wfob-public.php */
			$bumps = is_array( $bump_ids ) ? apply_filters( 'wfob_bumps_from_external_base', false, array() ) : false;
			if ( is_array( $bumps ) ) {
				$ids = array_map(
					function ( $bump ) {
						return (int) ( $bump instanceof WP_Post ? $bump->ID : $bump );
					},
					$bumps
				);
				if ( array() === array_intersect( $ids, $bump_ids ) ) {
					return;
				}
			}
			self::load();
		}

		/**
		 * Read the glue for a cart restored from the session with a bump's bundle line in it.
		 *
		 * @param array $item Cart line.
		 * @return array
		 */
		public static function load_for_line( $item ) {
			if ( is_array( $item ) && ! empty( $item['_wfob_product'] ) && ! empty( $item[ self::LINE_KEY ] ) ) {
				self::load();
			}

			return $item;
		}

		/**
		 * Read the glue for a stored bump line that a page switch re-adds, when it is a bundle's.
		 *
		 * @param bool  $re_add    Whether to re-add the line.
		 * @param array $item_data The line's stored cart item data.
		 * @return bool
		 */
		public static function load_for_re_add( $re_add, $item_data = array() ) {
			if ( is_array( $item_data ) && ! empty( $item_data[ self::LINE_KEY ] ) ) {
				self::load();
			}

			return $re_add;
		}

		/**
		 * Keep the autoloaded copy in step with a write of ROWS_IDS.
		 *
		 * @param mixed $value      The bump ids just stored.
		 * @param int   $expiration Seconds until the transient expires.
		 * @return void
		 */
		public static function mirror_ids( $value, $expiration = 0 ) {
			$until = (int) $expiration > 0 ? time() + (int) $expiration : time() + DAY_IN_SECONDS;
			update_option( self::IDS_OPTION, array( 'ids' => is_array( $value ) ? array_values( array_map( 'intval', $value ) ) : null, 'until' => $until ), true );
		}

		/**
		 * Mark the autoloaded copy unknown when ROWS_IDS is dropped; it is kept, not deleted, so reading it never misses.
		 *
		 * @param string $transient Name of the dropped transient.
		 * @return void
		 */
		public static function mirror_ids_dropped( $transient ) {
			if ( self::ROWS_IDS === $transient ) {
				update_option( self::IDS_OPTION, array( 'ids' => null, 'until' => 0 ), true );
			}
		}

		/**
		 * Drop the cached bump rows, and the glue's own copies when it is loaded.
		 *
		 * @return void
		 */
		public static function forget_rows() {
			if ( self::$loaded && class_exists( 'WFOB_Compatibility_FunnelKit_Bundles', false ) ) {
				WFOB_Compatibility_FunnelKit_Bundles::forget_rows();

				return;
			}
			self::$bump_ids = false;
			delete_transient( self::ROWS_CACHE );
			delete_transient( self::ROWS_IDS );
			set_transient( self::ROWS_CLEARED, microtime( true ), HOUR_IN_SECONDS );
		}

		/**
		 * Drop the cached rows when a bump is deleted.
		 *
		 * @param int          $post_id Post id.
		 * @param WP_Post|null $post    The deleted post.
		 * @return void
		 */
		public static function forget_rows_on_delete( $post_id, $post = null ) {
			if ( 'wfob_bump' === ( $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id ) ) ) {
				self::forget_rows();
			}
		}

		/**
		 * Drop the cached rows when a bump's products or design change.
		 *
		 * @param int    $meta_id  Meta id.
		 * @param int    $post_id  Post id.
		 * @param string $meta_key Meta key.
		 * @return void
		 */
		public static function forget_rows_on_meta( $meta_id, $post_id, $meta_key ) {
			if ( in_array( $meta_key, array( '_wfob_design_data', '_wfob_selected_products' ), true ) ) {
				self::forget_rows();
				return;
			}
			// Joining or leaving a funnel or an A/B test changes which checkouts a bump can show on.
			if ( in_array( $meta_key, array( '_bwf_in_funnel', '_bwf_ab_variation_of' ), true ) && class_exists( 'WFOB_Common' ) && WFOB_Common::get_bump_post_type_slug() === get_post_type( (int) $post_id ) ) {
				self::forget_rows();
			}
		}
	}

	WFOB_FunnelKit_Bundles_Loader::init();
}
