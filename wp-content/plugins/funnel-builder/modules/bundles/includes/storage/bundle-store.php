<?php
/**
 * The public bundle API.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

use WFFN\Bundles\Includes\Admin\Navigation;
use WFFN\Bundles\Includes\Products\Repository as Products;
use WFFN\Bundles\Includes\Helpers\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The bundle facade, and the authority on bundle matching and gating.
 *
 * Persistence lives in Bundle_Repository. The target table only narrows which
 * bundles are worth checking; every candidate it produces still runs through
 * targets_product() here before it can win.
 */
class Bundle_Store {

	/**
	 * Bundle type ids as stored in `discount_type` and carried end-to-end.
	 *
	 * An add-on registers its own types through `wffn_bundles_bundle_types`; their
	 * bundles are still listed and their rows kept when it is inactive.
	 */
	const TYPE_FIXED_BUNDLES              = 2;
	const TYPE_FREQUENTLY_BOUGHT_TOGETHER = 4;

	/** The one offer a Frequently Bought Together bundle sells: the whole set. */
	const FBT_OFFER = 'fbt-set';

	/** Which bundle a product page resolves: its bundle, or its bought-together set. */
	const SLOT_BUNDLE = 'bundle';
	const SLOT_FBT    = 'fbt';

	/** Target mode ids as stored in `target_mode` and `productSelection.mode`. */
	const MODE_NONE                = 0;
	const MODE_SPECIFIC_PRODUCTS   = 1;
	const MODE_SPECIFIC_CATEGORIES = 2;

	/**
	 * Human label per bundle type, for the list search.
	 *
	 * A bundle of a type this plugin does not render still has a name, so the
	 * map is filterable. Falling back to the id reads as a bug wherever it lands.
	 *
	 * @param int|string  $type    Bundle type id.
	 * @param string|null $unknown Label for a type nothing registers; null for the generic one.
	 * @return string
	 */
	public static function type_label( $type, $unknown = null ) {
		$map = self::type_labels();
		if ( isset( $map[ (int) $type ] ) ) {
			return (string) $map[ (int) $type ];
		}
		return null !== $unknown ? (string) $unknown : __( 'Premium bundle', 'funnel-builder' );
	}

	/**
	 * The display name of every type an active plugin names, keyed by id.
	 *
	 * @return array<int,string>
	 */
	private static function type_labels() {
		$map = array(
			self::TYPE_FIXED_BUNDLES              => __( 'Fixed Bundle', 'funnel-builder' ),
			self::TYPE_FREQUENTLY_BOUGHT_TOGETHER => __( 'Frequently Bought Together', 'funnel-builder' ),
		);

		/**
		 * Filters the display name of each bundle type, keyed by id.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,string> $map Labels for the types this plugin renders.
		 */
		return (array) apply_filters( 'wffn_bundles_bundle_type_labels', $map );
	}

	/**
	 * The bundle type ids the admin lists: every type an active plugin names.
	 *
	 * The plugin that renders a type names it whether or not it may serve it
	 * right now, so its bundles stay listed (and locked) while, say, a licence
	 * has lapsed. With that plugin deactivated nobody names the type, and its
	 * bundles leave the admin, as Funnel Builder leaves out the steps of an
	 * add-on that is off. Their rows are kept and return with the plugin.
	 *
	 * @return int[]
	 */
	public static function listed_types() {
		$types = array_merge( self::accepted_types(), array_keys( self::type_labels() ) );

		return array_values( array_unique( array_map( 'intval', $types ) ) );
	}

	/**
	 * A bundle type posted by the storefront, as its id.
	 *
	 * A form body is always strings, so a posted type never matches a TYPE_*
	 * constant on `===` without this. The storefront templates post the type's slug.
	 *
	 * @param mixed $type Posted type: an id, a numeric string, or a type slug.
	 * @return int TYPE_* id, or 0 when it matches nothing.
	 */
	public static function type_id( $type ) {
		$slugs = array(
			'fixed_bundles'              => self::TYPE_FIXED_BUNDLES,
			'frequently_bought_together' => self::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
		);

		/**
		 * Filters the type slugs a posted type may use, keyed by slug.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,int> $slugs Slug => type id for the types this plugin renders.
		 */
		$slugs = (array) apply_filters( 'wffn_bundles_bundle_type_slugs', $slugs );
		$id    = isset( $slugs[ (string) $type ] ) ? $slugs[ (string) $type ] : (int) $type;
		return in_array( $id, self::accepted_types(), true ) ? $id : 0;
	}

	/**
	 * The bundle type ids a bundle may be saved as.
	 *
	 * `TYPES` is what this plugin renders. An add-on renders others, and a
	 * bundle of one still has to be creatable and savable here.
	 *
	 * @return int[]
	 */
	public static function accepted_types() {
		/**
		 * Filters the bundle type ids a bundle may be saved as.
		 *
		 * @since 1.0.0
		 *
		 * @param int[] $types Type ids this plugin renders.
		 */
		$types = (array) apply_filters( 'wffn_bundles_bundle_types', self::TYPES );

		return array_values( array_unique( array_map( 'intval', $types ) ) );
	}

	/**
	 * Ordered list-item summaries; summary columns only, never the config blob.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function index() {
		return Bundle_Repository::index();
	}

	/**
	 * Type ids of the active bundles; cached until the next bundle write.
	 *
	 * @return int[]
	 */
	public static function active_types() {
		return Bundle_Repository::active_types();
	}

	/**
	 * Whether any bundle is active, without a query once cached.
	 *
	 * @return bool
	 */
	public static function has_active() {
		return Bundle_Repository::has_active();
	}

	/**
	 * Bundles counted per type, in any status and active.
	 *
	 * @return array<int,array{total:int,active:int}> Keyed by type id.
	 */
	public static function type_counts() {
		return Bundle_Repository::type_counts();
	}

	/**
	 * Whether at least one bundle of a listed type exists, in any status.
	 *
	 * @return bool
	 */
	public static function has_listed() {
		return Bundle_Repository::has_any( self::listed_types() );
	}

	/**
	 * Paginated list payload, searched, sorted and paged in SQL.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function list_bundles( array $args = array() ) {
		return Bundle_Repository::list_bundles( $args );
	}

	/**
	 * Every bundle id mapped to its 1-based resolution rank; lower wins.
	 *
	 * @return array<string,int>
	 */
	public static function rank_map() {
		return Bundle_Repository::rank_map();
	}

	/**
	 * Grouped, sampled target rows for a set of bundles.
	 *
	 * @param int[] $ids Bundle ids.
	 * @return array
	 */
	public static function target_index( array $ids ) {
		return Bundle_Repository::target_index( $ids );
	}

	/**
	 * Raw stored payload with product references only, or null.
	 *
	 * @param int|string $id Bundle id.
	 * @return array|null
	 */
	private static function get_raw( $id ) {
		return Bundle_Repository::get_config( $id );
	}

	/**
	 * Warm the config cache for a set of bundle ids with a single query.
	 *
	 * Public so the cart can prime before walking its per-bundle lines.
	 *
	 * @param array $ids Bundle ids.
	 * @return void
	 */
	public static function prime_configs( array $ids ) {
		Bundle_Repository::get_configs( $ids );
	}

	/**
	 * Release the payloads loaded so far this request, once a bulk walk is done
	 * with them.
	 *
	 * @return void
	 */
	public static function release_configs() {
		Bundle_Repository::release_configs();
	}

	/**
	 * Raw stored payload without WooCommerce hydration, or null.
	 *
	 * The pricing paths need only the discount config and product ids, so they skip
	 * the per-product hydration get() does for the editor.
	 *
	 * @param int|string $id Bundle id.
	 * @return array|null
	 */
	public static function get_config( $id ) {
		return self::get_raw( $id );
	}

	/**
	 * Full editor payload with products hydrated from WooCommerce, or null.
	 *
	 * @param int|string $id Bundle id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$payload = self::get_raw( $id );
		return null === $payload ? null : self::hydrate_products( $payload );
	}

	/**
	 * Whether any active bundle of these types targets the product, by id or category.
	 *
	 * A superset: false means none of those types can be the product's bundle;
	 * true still needs active_for_product().
	 *
	 * @param int|string $product_id Product id.
	 * @param int[]      $types      Bundle type ids.
	 * @return bool
	 */
	public static function may_target_product( $product_id, array $types ) {
		return Bundle_Repository::may_target( $product_id, $types );
	}

	/**
	 * Ids of every bundle placed on a product, any status, in rank order.
	 *
	 * A superset: run targets_product() on each before trusting it.
	 *
	 * @param int|string $product_id Product id.
	 * @return string[]
	 */
	public static function placed_on_product( $product_id ) {
		return Bundle_Repository::candidate_ids_for_product( $product_id, true );
	}

	/**
	 * Resolve the winning active bundle for a product, hydrated, or null.
	 *
	 * The gates run in order: status, schedule, placement, role. Only the winner
	 * is hydrated. Memoised per request only, keyed by product, data version and
	 * user, because the answer depends on the visitor and on time().
	 *
	 * A product page shows one bundle type at a time. The first live bundle in
	 * rank order wins, whatever its type; the slot says which side the caller asks
	 * for, so a Frequently Bought Together winner leaves the bundle slot empty and
	 * any other winner leaves the bought-together slot empty.
	 *
	 * @param int|string $product_id Product id.
	 * @param string     $slot       self::SLOT_BUNDLE or self::SLOT_FBT.
	 * @return array|null
	 */
	public static function active_for_product( $product_id, $slot = self::SLOT_BUNDLE ) {
		$pid = (string) absint( $product_id );
		if ( '' === $pid || '0' === $pid || ( ! Bundle_Repository::has_active() && ! self::staff_preview() ) ) {
			return null;
		}
		$want_fbt = self::SLOT_FBT === $slot;

		// Four entry points resolve the same bundle per request, each re-hydrating.
		static $memo = array();
		$key = $pid . '_' . ( $want_fbt ? 'f' : 'b' ) . '_v' . Bundle_Repository::version() . '_u' . get_current_user_id();
		if ( array_key_exists( $key, $memo ) ) {
			return $memo[ $key ];
		}

		$raw = self::winner( $pid );
		// The winner is the only bundle shown. A bundle of the other kind
		// does not sit beside it, so the slot the winner is not in stays empty.
		if ( null === $raw || ( self::TYPE_FREQUENTLY_BOUGHT_TOGETHER === (int) ( $raw['discountType'] ?? 0 ) ) !== $want_fbt ) {
			$memo[ $key ] = null;
			return null;
		}
		// The widget reads the anchor's own row right after its members.
		$memo[ $key ] = self::hydrate_products( $raw, false, array( (int) $pid ) );
		return $memo[ $key ];
	}

	/**
	 * The type of the product's winning active bundle, without hydrating it; 0 when none wins.
	 *
	 * @param int|string $product_id Product id.
	 * @return int
	 */
	public static function active_type_for_product( $product_id ) {
		$pid = (string) absint( $product_id );
		if ( '' === $pid || '0' === $pid || ( ! Bundle_Repository::has_active() && ! self::staff_preview() ) ) {
			return 0;
		}
		$raw = self::winner( $pid );
		return null === $raw ? 0 : (int) ( $raw['discountType'] ?? 0 );
	}

	/**
	 * The first bundle in rank order that passes every gate for the product, unhydrated, or null.
	 *
	 * @param string $pid Product id.
	 * @return array|null
	 */
	private static function winner( $pid ) {
		static $memo = array();
		$key = $pid . '_v' . Bundle_Repository::version() . '_u' . get_current_user_id();
		if ( array_key_exists( $key, $memo ) ) {
			return $memo[ $key ];
		}
		$memo[ $key ] = null;

		// Staff who may manage Bundles preview drafts and bundles outside their
		// window or audience; every other visitor sees only what runs for them.
		$preview    = self::staff_preview();
		$candidates = Bundle_Repository::candidate_ids_for_product( $pid, $preview );
		if ( empty( $candidates ) ) {
			return null;
		}

		// One query for every candidate config, in menu_order.
		Bundle_Repository::get_configs( $candidates );

		$types = self::accepted_types();

		foreach ( $candidates as $cid ) {
			$raw = self::get_raw( $cid );
			if ( null === $raw ) {
				continue;
			}
			if ( ! $preview && 1 !== (int) ( $raw['status'] ?? 0 ) ) {
				continue;
			}
			// A type no active plugin renders would take the slot and show nothing.
			if ( ! in_array( (int) ( $raw['discountType'] ?? 0 ), $types, true ) ) {
				continue;
			}
			if ( ! $preview && ! self::is_live( $raw ) ) {
				continue;
			}
			// The target table is only a filter; placement is decided here, by the
			// same function the add-to-cart path uses.
			if ( ! self::targets_product( $raw, $pid ) ) {
				continue;
			}
			if ( ! $preview && ! self::visible_to_user( $raw ) ) {
				continue;
			}
			$memo[ $key ] = $raw;
			break;
		}
		return $memo[ $key ];
	}

	/**
	 * May the current visitor see this bundle?
	 *
	 * Shared by the render path and the add-to-cart eligibility gate, so a bundle
	 * hidden from a visitor cannot be bought by them.
	 *
	 * @param array $bundle Raw bundle config.
	 * @return bool
	 */
	private static function visible_to_user( array $bundle ) {
		// A stored audience fails closed: with nothing to check the visitor against
		// it, a role-targeted price never reaches shoppers outside the audience.
		$targeting = is_array( $bundle['targeting'] ?? null ) ? $bundle['targeting'] : array();
		$audience  = array_filter( array_map( 'strval', (array) ( $targeting['userRoles'] ?? array() ) ) );

		/**
		 * Filters whether the current visitor may see a bundle.
		 *
		 * A bundle with no stored audience is open to every visitor. One with a
		 * stored audience (targeting.userRoles) is closed unless an add-on that
		 * enforces the audience answers true for a visitor inside it.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $visible  Whether the visitor may see it.
		 * @param array $bundle Raw bundle config.
		 */
		return (bool) apply_filters( 'wffn_bundles_bundle_visible', empty( $audience ), $bundle );
	}

	/**
	 * Is the current visitor staff previewing the storefront?
	 *
	 * The users Funnel Builder lets into Bundles (Navigation::can_manage()) see
	 * and may add drafts and bundles outside their window or audience, to test
	 * the cart and checkout before going live. Nobody else does.
	 *
	 * @return bool
	 */
	public static function staff_preview() {
		return is_user_logged_in() && Navigation::can_manage();
	}

	/**
	 * Is a stored bundle config eligible to add/quote for the current visitor?
	 *
	 * The server-side mirror of the render gate: the type must be one an active
	 * plugin serves; then staff previewing (staff_preview()) bypass, and everyone else needs
	 * status 1, a live bundle and a matching audience.
	 * Operates on the full config shape, not the list summary.
	 *
	 * @param array $bundle Full bundle config.
	 * @return bool
	 */
	public static function is_active_now( array $bundle ) {
		// A type no active plugin serves never runs, so a group of it already in
		// a cart is dropped rather than keeping its discount.
		if ( ! in_array( (int) ( $bundle['discountType'] ?? 0 ), self::accepted_types(), true ) ) {
			return false;
		}
		if ( self::staff_preview() ) {
			return true;
		}
		if ( 1 !== (int) ( $bundle['status'] ?? 0 ) ) {
			return false;
		}
		if ( ! self::is_live( $bundle ) ) {
			return false;
		}
		return self::visible_to_user( $bundle );
	}

	/**
	 * Whether an active bundle is running right now, for every visitor.
	 *
	 * @param array $bundle Full bundle config.
	 * @return bool
	 */
	public static function is_live( array $bundle ) {
		/**
		 * Filters whether an active bundle is running right now.
		 *
		 * The stored schedule window is enforced here, in every edition: a
		 * bundle before its start or past its end does not run. An add-on that
		 * limits when a bundle runs further answers false outside that time.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $live     Whether the bundle is running.
		 * @param array $bundle Full bundle config.
		 */
		return (bool) apply_filters( 'wffn_bundles_bundle_live', self::in_schedule_window( $bundle ), $bundle );
	}

	/**
	 * Whether now falls inside a bundle's stored schedule window.
	 *
	 * No schedule saved, or Always available, is open. A scheduled bundle with
	 * no readable bound fails closed. Bounds are site-local wall-clock values,
	 * as normalize_schedule_date() stores them.
	 *
	 * @param array $bundle Full bundle config.
	 * @return bool
	 */
	public static function in_schedule_window( array $bundle ) {
		$schedule = $bundle['schedule'] ?? null;
		if ( ! is_array( $schedule ) || ! empty( $schedule['alwaysAvailable'] ) ) {
			return true;
		}
		$start = self::schedule_ts( $schedule['startDate'] ?? '' );
		$end   = self::schedule_ts( $schedule['endDate'] ?? '' );
		if ( ! $start && ! $end ) {
			return false;
		}
		$now = time();

		return ! ( ( $start && $now < $start ) || ( $end && $now > $end ) );
	}

	/**
	 * A stored schedule bound as an epoch, read in the site timezone.
	 *
	 * @param mixed $value Stored "Y-m-d H:i:s" value.
	 * @return int 0 for empty or unparseable input, which means no bound.
	 */
	public static function schedule_ts( $value ) {
		if ( is_bool( $value ) || is_array( $value ) ) {
			return 0;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		try {
			return ( new \DateTime( $value, wp_timezone() ) )->getTimestamp();
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	/**
	 * Normalize a schedule value to a site-timezone wall-clock "Y-m-d H:i:s" string.
	 *
	 * Accepts the two shapes the admin picker emits: a bare datetime, or an ISO 8601
	 * string with an offset.
	 *
	 * @param mixed $value Incoming schedule value.
	 * @return string Normalized value, or '' when empty or unparseable.
	 */
	private static function normalize_schedule_date( $value ) {
		if ( is_bool( $value ) || is_array( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		try {
			// A bare value is site-local; an ISO offset is honored, then converted below.
			$dt = new \DateTime( $value, wp_timezone() );
			$dt->setTimezone( wp_timezone() );
			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Does a raw bundle target this product id?
	 *
	 * The anchor is the bundle's configured placement. Bundle members are not
	 * anchors, and excluded products always lose. There is no catalog-wide
	 * wildcard: a bundle with no target matches nothing, for every type.
	 *
	 * @param array      $p   Raw bundle config.
	 * @param int|string $pid Product id being resolved.
	 * @return bool
	 */
	public static function targets_product( array $p, $pid ) {
		$sel  = is_array( $p['productSelection'] ?? null ) ? $p['productSelection'] : array();
		$mode = (int) ( $sel['mode'] ?? self::MODE_NONE );

		if ( ! empty( $sel['excludeEnabled'] ) ) {
			foreach ( (array) ( $sel['excludedProducts'] ?? array() ) as $ex ) {
				if ( (string) ( $ex['id'] ?? '' ) === $pid ) {
					return false;
				}
			}
		}

		// Every mode but the category one targets exactly the products it lists;
		// reading it here keeps this in step with has_target().
		$ids = array();
		if ( self::MODE_SPECIFIC_CATEGORIES !== $mode ) {
			foreach ( (array) ( $sel['products'] ?? array() ) as $pr ) {
				$ids[] = (string) ( $pr['id'] ?? '' );
			}
		}

		/**
		 * Filters whether a bundle is placed on a product.
		 *
		 * True when the product is one this bundle lists. An add-on that places
		 * a bundle by something other than an explicit list answers here, and
		 * one that narrows a placement answers false.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $targets Whether the bundle is placed on the product.
		 * @param array  $p       Raw bundle config.
		 * @param string $pid     Product id being resolved.
		 */
		return (bool) apply_filters(
			'wffn_bundles_bundle_targets_product',
			in_array( $pid, array_filter( $ids ), true ),
			$p,
			(string) $pid
		);
	}

	/**
	 * Does a raw bundle name at least one placement target?
	 *
	 * The save-time counterpart of targets_product(). A bundle that answers false
	 * renders nowhere, so activating it is always a misconfiguration. Mirrored by
	 * targetIssues() in the admin app's schema.ts; keep the two in step.
	 *
	 * @param array $p Raw bundle config.
	 * @return bool
	 */
	public static function has_target( array $p ) {
		$sel  = is_array( $p['productSelection'] ?? null ) ? $p['productSelection'] : array();
		$mode = (int) ( $sel['mode'] ?? self::MODE_NONE );

		$has = false;
		// Every mode targets exactly the products it lists, so empty means no target.
		foreach ( (array) ( $sel['products'] ?? array() ) as $pr ) {
			if ( '' !== (string) ( is_array( $pr ) ? ( $pr['id'] ?? '' ) : $pr ) ) {
				$has = true;
				break;
			}
		}

		/**
		 * Filters whether a bundle names at least one placement target.
		 *
		 * The save-time counterpart of `wffn_bundles_bundle_targets_product`: a
		 * bundle that answers false renders nowhere and cannot be activated.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $has Whether the bundle names a target.
		 * @param array $p   Raw bundle config.
		 */
		return (bool) apply_filters( 'wffn_bundles_bundle_has_target', $has, $p );
	}

	/**
	 * Create a bundle from a partial payload.
	 *
	 * @param array $payload Bundle payload.
	 * @return array|null List-item shape, or null on failure.
	 */
	public static function create( array $payload ) {
		// The repository assigns the id and the menu_order that lands it at the top.
		$stored = Bundle_Repository::create( self::new_payload( $payload ) );

		return null === $stored ? null : self::summary( $stored );
	}

	/**
	 * The editor payload a bundle created from this partial payload starts
	 * with, without storing it.
	 *
	 * @param array $payload Bundle payload.
	 * @return array
	 */
	public static function starter( array $payload ) {
		return self::hydrate_products( self::new_payload( $payload ) );
	}

	/**
	 * A partial payload with the defaults a new bundle is stored with.
	 *
	 * @param array $payload Bundle payload.
	 * @return array
	 */
	private static function new_payload( array $payload ) {
		// Defaulted before the prune, which is type-driven: an unknown type keeps every key.
		if ( empty( $payload['discountType'] ) ) {
			$payload['discountType'] = self::TYPE_FIXED_BUNDLES;
		}
		$payload           = self::strip_products( self::prune_config( self::sanitize( $payload ) ) );
		$payload['status'] = isset( $payload['status'] ) ? (int) $payload['status'] : 0;
		if ( empty( $payload['name'] ) ) {
			$payload['name'] = __( 'New Bundle', 'funnel-builder' );
		}
		// The editor shows a bundle saved without a schedule as always available.
		if ( ! is_array( $payload['schedule'] ?? null ) ) {
			$payload['schedule'] = array(
				'alwaysAvailable' => true,
				'startDate'       => '',
				'endDate'         => '',
			);
		}

		return $payload;
	}

	/**
	 * Merge a partial payload into a stored bundle.
	 *
	 * @param int|string $id    Bundle id.
	 * @param array      $patch Partial payload.
	 * @return array|null Hydrated bundle detail, or null when it does not exist.
	 */
	public static function update( $id, array $patch ) {
		$id      = self::clean_id( $id );
		$current = self::get_raw( $id );
		if ( null === $current ) {
			return null;
		}

		$payload              = self::strip_products( self::prune_config( self::sanitize( array_merge( $current, $patch ) ) ) );
		$payload['id']        = $id;
		$payload['createdAt'] = $current['createdAt'] ?? self::now();

		$stored = Bundle_Repository::update( $id, $payload );
		if ( null === $stored ) {
			return null;
		}

		// Return the hydrated payload so the editor reloads with full products.
		return self::hydrate_products( $stored );
	}

	/**
	 * Toggle status.
	 *
	 * @param int|string $id     Bundle id.
	 * @param int        $status 0 for draft, 1 for active.
	 * @return int The new status.
	 */
	public static function set_status( $id, $status ) {
		return Bundle_Repository::set_status( $id, $status );
	}

	/**
	 * Delete a bundle and its target rows.
	 *
	 * @param int|string $id Bundle id.
	 * @return bool
	 */
	public static function delete( $id ) {
		return Bundle_Repository::delete( $id );
	}

	/**
	 * Reorder to match the given id order.
	 *
	 * Ids absent from $ids keep their relative order and sink below the listed ones.
	 *
	 * @param array $ids Bundle ids in the wanted order.
	 * @return bool
	 */
	public static function reorder( array $ids ) {
		return Bundle_Repository::reorder( $ids );
	}

	/**
	 * Re-order a subset in the slots it already occupies.
	 *
	 * @param array $ids Bundle ids in the wanted order.
	 * @return bool
	 */
	public static function reorder_subset( array $ids ) {
		return Bundle_Repository::reorder_subset( $ids );
	}

	/**
	 * Copy fields the merchant types, stored as plain text.
	 *
	 * Escaping happens at render, not in storage, so these accept no markup.
	 */
	const PLAIN_TEXT_FIELDS = array(
		'bundleHeading',
		'bundleSubheading',
		'fixedBundleTitle',
		'fixedBundleSubtitle',
		'fixedBundleSingleTitle',
		'fixedBundleSingleSubtitle',
		'fbtButtonLabel',
	);

	/**
	 * Decode any stored entities, then store as plain text.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	private static function plain_text( $value ) {
		return sanitize_text_field( wp_specialchars_decode( (string) $value, ENT_QUOTES ) );
	}

	/**
	 * The bundle types this plugin renders.
	 *
	 * An add-on adds the ones it renders through `wffn_bundles_bundle_types`; see
	 * accepted_types().
	 */
	const TYPES = array( self::TYPE_FIXED_BUNDLES, self::TYPE_FREQUENTLY_BOUGHT_TOGETHER );

	/**
	 * `discountConfig` keys every type keeps.
	 *
	 * The layout, upsell and gift layers are switchable on any bundle type, so
	 * they are shared by construction.
	 */
	const SHARED_CONFIG_KEYS = array(
		'bundleHeading',
		'bundleSubheading',
		'bundleDesign',
		'designPresetId',
		'designBrandColor',
		'bundleLayout',
		'skin',
		'upsellsEnabled',
		'upsellsTitle',
		'upsellsSubtitle',
		'upsells',
		'upsellsParked',
		'upsellDesign',
		'progressiveGiftsEnabled',
		'progressiveGiftsBeforeText',
		'progressiveGiftsAfterText',
		'progressiveGifts',
		'progressiveGiftsParked',
		'giftDesign',
		'offerImagesEnabled',
		'offerImages',
		'offerImageOverrides',
		self::ADDONS_CONFIG_KEY,
	);

	/** `discountConfig` key holding add-on settings, stored whole and never read here. */
	const ADDONS_CONFIG_KEY = 'addons';

	/**
	 * `discountConfig` keys owned by exactly ONE bundle type.
	 *
	 * A bundle only ever renders as one type, and the type is chosen once, so
	 * the other types' keys are dead weight.
	 */
	const TYPE_CONFIG_KEYS = array(
		self::TYPE_FIXED_BUNDLES   => array(
			'fixedBundleDiscountScope',
			'fixedBundleDiscountType',
			'fixedBundleDiscountValue',
			'fixedBundleItems',
			'fixedBundleTitle',
			'fixedBundleSubtitle',
			'fixedBundleShowSingle',
			'fixedBundleSingleTitle',
			'fixedBundleSingleSubtitle',
		),
		self::TYPE_FREQUENTLY_BOUGHT_TOGETHER => array(
			'fbtItems',
			'fbtFallback',
			'fbtMaxItems',
			'fbtDiscountScope',
			'fbtDiscountType',
			'fbtDiscountValue',
			'fbtMinCompanions',
			'fbtLayout',
			'fbtButtonLabel',
		),
	);

	/**
	 * Top-level payload keys dropped on every write, whatever the type.
	 *
	 * `discount_type` is the snake-case alias the create dialog posts; dropping it
	 * keeps a create response identical in shape to a read. `displaySettings` has
	 * no reader anywhere.
	 */
	const DEAD_PAYLOAD_KEYS = array( 'discount_type', 'displaySettings' );

	/**
	 * The only `schedule` sub-keys stored.
	 *
	 * Whitelisted, not blacklisted: an add-on may gate add-to-cart on them, so an
	 * unrecognized key must never ride along.
	 */
	const SCHEDULE_KEYS = array( 'alwaysAvailable', 'startDate', 'endDate' );

	/**
	 * Every `discountConfig` key a bundle of this type may store.
	 *
	 * The single authority for the whitelist: the Install prune reads it, and
	 * admin/app.php ships it to the editor so schema.ts trims against this list.
	 *
	 * @param string $type Bundle type.
	 * @return string[]|null Allowed keys, or null for a type this plugin does not
	 *                       render, meaning keep every key it arrived with.
	 */
	public static function config_keys( $type ) {
		$own = self::TYPE_CONFIG_KEYS[ (int) $type ] ?? null;

		/**
		 * Filters the `discountConfig` keys one bundle type owns, shared keys aside.
		 *
		 * @since 1.0.0
		 *
		 * @param string[]|null $own  This type's own keys, or null to keep every key.
		 * @param int           $type Bundle type id.
		 */
		$own = apply_filters( 'wffn_bundles_bundle_type_config_keys', $own, (int) $type );
		if ( ! is_array( $own ) ) {
			return null;
		}
		return array_merge( self::SHARED_CONFIG_KEYS, $own );
	}

	/**
	 * This type's offers, flattened to the shape the offer rows store.
	 *
	 * Each type spells the same concept differently, so the translation lives
	 * here rather than in the repository that writes the rows. Fixed Bundle has no
	 * offer list, so its two structural slots are synthesised from bundle keys.
	 * Returns null when the type gives no trustworthy list, so the caller leaves
	 * whatever rows exist alone rather than archiving all of them.
	 *
	 * @param int                 $type Bundle type.
	 * @param array<string,mixed> $dc   The bundle's discountConfig.
	 * @return array<int,array<string,mixed>>|null
	 */
	public static function offer_rows( $type, array $dc ) {
		$type = (int) $type;

		if ( self::TYPE_FIXED_BUNDLES === $type ) {
			$whole = 'separate_products' !== (string) ( $dc['fixedBundleDiscountScope'] ?? 'whole_bundle' );
			return array(
				array(
					'key'           => 'fb-single',
					'title'         => (string) ( $dc['fixedBundleSingleTitle'] ?? '' ),
					'quantity'      => 1,
					'discountType'  => 'no_discount',
					'discountValue' => 0.0,
				),
				array(
					'key'           => 'fb-bundle',
					'title'         => (string) ( $dc['fixedBundleTitle'] ?? '' ),
					'quantity'      => 1,
					'discountType'  => $whole ? (string) ( $dc['fixedBundleDiscountType'] ?? 'percentage' ) : 'no_discount',
					'discountValue' => $whole ? (float) ( $dc['fixedBundleDiscountValue'] ?? 0 ) : 0.0,
				),
			);
		}

		if ( self::TYPE_FREQUENTLY_BOUGHT_TOGETHER === $type ) {
			$type_key = (string) ( $dc['fbtDiscountType'] ?? 'percentage' );
			return array(
				array(
					'key'           => self::FBT_OFFER,
					'title'         => (string) ( $dc['bundleHeading'] ?? '' ),
					'quantity'      => 1,
					'discountType'  => in_array( $type_key, array( 'percentage', 'fixed' ), true ) ? $type_key : 'percentage',
					'discountValue' => 'none' === (string) ( $dc['fbtDiscountScope'] ?? 'companions' ) ? 0.0 : (float) ( $dc['fbtDiscountValue'] ?? 0 ),
				),
			);
		}

		/**
		 * Filters the offers of a bundle type this plugin does not render.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,array<string,mixed>>|null $rows Offer rows, or null to leave the stored ones alone.
		 * @param int                                 $type Bundle type id.
		 * @param array<string,mixed>                 $dc   The bundle's discountConfig.
		 */
		$rows = apply_filters( 'wffn_bundles_bundle_offer_rows', null, $type, $dc );
		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * The `discountConfig` lists whose rows carry an `id`, and each list's prefix.
	 *
	 * The offer list's ids are the only ones anything else points at; the rest are
	 * self-contained rows.
	 */
	const ID_LISTS = array(
		'fixedBundleItems' => 'p',
		'fbtItems'         => 'c',
	);

	/**
	 * Every id-bearing list, this plugin's own plus any an add-on stores.
	 *
	 * @return array<string,string> List key => one-letter prefix.
	 */
	public static function id_lists() {
		/**
		 * Filters the `discountConfig` lists whose rows carry an `id`.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $lists List key => one-letter prefix.
		 */
		return array_merge( self::ID_LISTS, (array) apply_filters( 'wffn_bundles_bundle_id_lists', array() ) );
	}

	/**
	 * A fresh, opaque id for a tier / offer / item row.
	 *
	 * These ids are not secrets; they are rendered into the storefront markup and
	 * re-validated on submit. What the randomness buys is that an id is never
	 * reused, so a new row cannot adopt a deleted one's images or references.
	 *
	 * @param string $prefix One-letter list prefix, for readability only.
	 * @return string e.g. `b_9f3c1ad20e5b7a44`
	 */
	public static function new_offer_id( $prefix ) {
		try {
			$suffix = bin2hex( random_bytes( 8 ) );
		} catch ( \Throwable $e ) {
			$suffix = strtolower( wp_generate_password( 16, false, false ) );
		}
		return $prefix . '_' . $suffix;
	}

	/**
	 * Give a fresh id to every row of an id-carrying list that has none.
	 *
	 * The editor keys these rows by id and refuses to save one without it. It
	 * mints them itself; any other writer, an ability or an import, may send
	 * rows bare. Rows that already have an id keep it.
	 *
	 * @param array<string,mixed> $p Bundle payload or patch.
	 * @return array<string,mixed>
	 */
	public static function fill_row_ids( array $p ) {
		if ( ! isset( $p['discountConfig'] ) || ! is_array( $p['discountConfig'] ) ) {
			return $p;
		}

		foreach ( self::id_lists() as $list => $prefix ) {
			if ( empty( $p['discountConfig'][ $list ] ) || ! is_array( $p['discountConfig'][ $list ] ) ) {
				continue;
			}
			foreach ( $p['discountConfig'][ $list ] as $i => $row ) {
				if ( is_array( $row ) && ( ! isset( $row['id'] ) || ! is_string( $row['id'] ) || '' === $row['id'] ) ) {
					$p['discountConfig'][ $list ][ $i ]['id'] = self::new_offer_id( $prefix );
				}
			}
		}

		return $p;
	}

	/**
	 * Reduce a payload to the keys its own bundle type actually uses.
	 *
	 * Runs on every write, after sanitize() and before strip_products(), so the
	 * stored blob is type-scoped whoever wrote it.
	 *
	 * @param array<string,mixed> $p Bundle payload.
	 * @return array<string,mixed>
	 */
	public static function prune_config( array $p ) {
		foreach ( self::DEAD_PAYLOAD_KEYS as $key ) {
			unset( $p[ $key ] );
		}

		if ( isset( $p['schedule'] ) && is_array( $p['schedule'] ) ) {
			$p['schedule'] = array_intersect_key(
				$p['schedule'],
				array_flip( self::SCHEDULE_KEYS )
			);
		}

		if ( ! isset( $p['discountConfig'] ) || ! is_array( $p['discountConfig'] ) ) {
			return $p;
		}

		$dc = $p['discountConfig'];

		// The whitelist is all that runs here; there is deliberately no blacklist.
		$type    = (int) ( $p['discountType'] ?? 0 );
		$allowed = self::config_keys( $type );
		if ( null !== $allowed ) {
			$dc = array_intersect_key( $dc, array_flip( $allowed ) );
		}

		$p['discountConfig'] = $dc;
		return $p;
	}

	/**
	 * Sanitize untrusted payload fields at the storage boundary.
	 *
	 * Kept here rather than in the view model so PHP and JS render parity is exact.
	 *
	 * @param array $p Bundle payload.
	 * @return array
	 */
	private static function sanitize( array $p ) {
		if ( isset( $p['name'] ) && is_string( $p['name'] ) ) {
			$p['name'] = self::plain_text( $p['name'] );
		}
		if ( isset( $p['schedule'] ) && is_array( $p['schedule'] ) ) {
			$p['schedule']['alwaysAvailable'] = ! empty( $p['schedule']['alwaysAvailable'] );
			foreach ( array( 'startDate', 'endDate' ) as $field ) {
				if ( array_key_exists( $field, $p['schedule'] ) ) {
					$p['schedule'][ $field ] = self::normalize_schedule_date( $p['schedule'][ $field ] );
				}
			}
		}
		if ( isset( $p['discountConfig'] ) && is_array( $p['discountConfig'] ) ) {
			foreach ( self::PLAIN_TEXT_FIELDS as $field ) {
				if ( isset( $p['discountConfig'][ $field ] ) && is_string( $p['discountConfig'][ $field ] ) ) {
					$p['discountConfig'][ $field ] = self::plain_text( $p['discountConfig'][ $field ] );
				}
			}
		}

		if ( isset( $p['discountConfig']['bundleDesign']['image_size'] ) ) {
			$p['discountConfig']['bundleDesign']['image_size'] = WooCommerce::image_size( $p['discountConfig']['bundleDesign']['image_size'] );
		}

		if ( isset( $p['discountConfig'][ self::ADDONS_CONFIG_KEY ] ) && ! is_array( $p['discountConfig'][ self::ADDONS_CONFIG_KEY ] ) ) {
			unset( $p['discountConfig'][ self::ADDONS_CONFIG_KEY ] );
		}

		/**
		 * Filters a bundle payload after sanitizing, before it is stored.
		 *
		 * @since 1.0.0
		 *
		 * @param array $p The sanitized payload.
		 */
		return (array) apply_filters( 'wffn_bundles_bundle_sanitize', $p );
	}

	/**
	 * Reduce every product object in a payload to a bare `{ id }` reference.
	 *
	 * @param array $payload Bundle payload.
	 * @return array
	 */
	private static function strip_products( array $payload ) {
		return self::map_products( $payload, function ( $p ) {
			$id = is_array( $p ) ? (string) ( $p['id'] ?? '' ) : '';
			return '' !== $id ? array( 'id' => $id ) : null;
		} );
	}

	/**
	 * Expand every product reference back into the full WooCommerce shape.
	 *
	 * A first pass collects every product id so the post, term and meta caches are
	 * primed with one set of queries before any product is loaded.
	 *
	 * @param array $payload        Stored bundle payload.
	 * @param bool  $with_selection Expand the targeting lists too; the editor needs
	 *                              them and the storefront never reads them.
	 * @param int[] $lookups        Products the caller reads next, primed in the same batch.
	 * @return array
	 */
	private static function hydrate_products( array $payload, $with_selection = true, array $lookups = array() ) {
		$ids = array();
		self::map_products( $payload, function ( $ref ) use ( &$ids ) {
			$id = is_array( $ref ) ? absint( $ref['id'] ?? 0 ) : 0;
			if ( $id ) {
				$ids[] = $id;
			}
			return $ref;
		}, $with_selection );
		// A Fixed Bundle is charged on the shared container product, which the page,
		// the quote and the cart all load moments later. Named here it rides the same
		// batch as the bundle's own items instead of costing a product read of its own.
		if ( self::TYPE_FIXED_BUNDLES === (int) ( $payload['discountType'] ?? 0 ) ) {
			$container = (int) get_option( Install::OPTION_CONTAINER_ID, 0 );
			if ( $container > 0 ) {
				$ids[] = $container;
			}
		}

		Products::prime( $ids, $lookups );

		return self::map_products( $payload, function ( $ref ) {
			$id = is_array( $ref ) ? (string) ( $ref['id'] ?? '' ) : '';
			if ( '' === $id ) {
				return null;
			}
			$product = Products::find( $id );
			// Keep the reference (with a name fallback) if the product is gone.
			return $product ? $product : array_merge(
				array( 'id' => $id, 'name' => '', 'type' => '', 'image' => '', 'price' => '', 'priceRaw' => 0.0, 'category' => '' ),
				is_array( $ref ) ? $ref : array()
			);
		}, $with_selection );
	}

	/**
	 * Apply $fn to every product object on the payload's known product-bearing paths.
	 *
	 * @param array    $p              Bundle payload.
	 * @param callable $fn             Transform per product reference; null drops it.
	 * @param bool     $with_selection Include productSelection's targeting lists.
	 * @return array
	 */
	private static function map_products( array $p, callable $fn, $with_selection = true ) {
		if ( $with_selection && isset( $p['productSelection'] ) && is_array( $p['productSelection'] ) ) {
			foreach ( array( 'products', 'excludedProducts' ) as $k ) {
				if ( isset( $p['productSelection'][ $k ] ) && is_array( $p['productSelection'][ $k ] ) ) {
					$p['productSelection'][ $k ] = array_values( array_filter( array_map( $fn, $p['productSelection'][ $k ] ) ) );
				}
			}
		}

		if ( isset( $p['discountConfig'] ) && is_array( $p['discountConfig'] ) ) {
			/**
			 * Filters the discountConfig lists whose rows hold product references.
			 *
			 * A row's `product`, `products` and `choices` are reduced to ids on save
			 * and expanded again on read.
			 *
			 * @since 1.0.0
			 *
			 * @param string[] $lists discountConfig keys.
			 */
			$lists = (array) apply_filters( 'wffn_bundles_bundle_product_lists', array( 'fixedBundleItems', 'fbtItems' ) );
			foreach ( $lists as $k ) {
				if ( ! isset( $p['discountConfig'][ $k ] ) || ! is_array( $p['discountConfig'][ $k ] ) ) {
					continue;
				}
				foreach ( $p['discountConfig'][ $k ] as &$row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					if ( array_key_exists( 'product', $row ) ) {
						$row['product'] = $row['product'] ? $fn( $row['product'] ) : null;
					}
					foreach ( array( 'products', 'choices' ) as $list_key ) {
						if ( isset( $row[ $list_key ] ) && is_array( $row[ $list_key ] ) ) {
							$row[ $list_key ] = array_values( array_filter( array_map( $fn, $row[ $list_key ] ) ) );
						}
					}
				}
				unset( $row );
			}
		}

		return $p;
	}

	/**
	 * Apply $fn to every product reference on a payload's known product-bearing
	 * paths — the same walk write/read hydration uses, exposed for export and
	 * import to reuse instead of re-enumerating those paths themselves.
	 *
	 * @param array    $payload        Bundle payload.
	 * @param callable $fn             Transform per product reference; null drops it.
	 * @param bool     $with_selection Include productSelection's targeting lists.
	 * @return array
	 */
	public static function walk_products( array $payload, callable $fn, $with_selection = true ) {
		return self::map_products( $payload, $fn, $with_selection );
	}

	/**
	 * Derive the list-item summary from a full payload.
	 *
	 * @param array $p Bundle payload.
	 * @return array
	 */
	private static function summary( array $p ) {
		$schedule = is_array( $p['schedule'] ?? null ) ? $p['schedule'] : array();
		$always   = ! is_array( $p['schedule'] ?? null ) || ! empty( $schedule['alwaysAvailable'] );

		return array(
			'id'           => (string) ( $p['id'] ?? '' ),
			'name'         => (string) ( $p['name'] ?? '' ),
			'discountType' => (int) ( $p['discountType'] ?? 0 ),
			'status'       => (int) ( $p['status'] ?? 0 ),
			'orders'       => 0,
			'revenue'      => '–',
			'aov'          => '–',
			'upsellRevenue' => '–',
			'scheduleMode' => $always ? 0 : 1,
			'startDate'    => (string) ( $schedule['startDate'] ?? '' ),
			'endDate'      => (string) ( $schedule['endDate'] ?? '' ),
			'createdAt'    => (string) ( $p['createdAt'] ?? self::now() ),
			'updatedAt'    => (string) ( $p['updatedAt'] ?? self::now() ),
		);
	}

	/**
	 * Reduce an incoming id to digits.
	 *
	 * @param mixed $id Incoming bundle id.
	 * @return string
	 */
	private static function clean_id( $id ) {
		return preg_replace( '/[^0-9]/', '', (string) $id );
	}

	/**
	 * The current time as an ISO-8601 UTC string.
	 *
	 * @return string
	 */
	private static function now() {
		return gmdate( 'c' );
	}
}
