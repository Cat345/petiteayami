<?php
/**
 * Table-backed persistence for bundles.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the SQL behind Bundle_Store, on three rules.
 *
 * `config` is the source of truth and the promoted columns are a derived index.
 * `bwf_bundle_targets` is an accelerator, never an authority: it returns a
 * superset that targets_product() re-validates. Only the per-product candidate
 * id list is object-cached; the resolved winner depends on the user and on
 * time(), so it never is.
 */
class Bundle_Repository {

	/**
	 * Monotonic counter bumped on every bundle write.
	 *
	 * Part of every cache key, so a save invalidates cached candidate lists across
	 * processes without a cache-group flush.
	 */
	const OPTION_VERSION = 'wffn_bundles_bundles_ver';

	/**
	 * Autoloaded `{ v, types, count, targets }`: the type ids and number of active
	 * bundles at version `v`, and their target map while it holds at most
	 * TARGETS_AUTOLOAD_MAX ids (null past that).
	 *
	 * Storefront paths ask it before any bundle query, so a store whose bundles
	 * are all drafts costs no query; a write moves the version and the next read
	 * rebuilds it once.
	 */
	const OPTION_ACTIVE_TYPES = 'wffn_bundles_active_types';

	/**
	 * Transient `{ v, types }`: per active bundle type, the product and category
	 * ids its bundles target, at data version `v`, when too many to autoload.
	 */
	const TRANSIENT_TARGETS = 'wffn_bundles_active_targets';

	/** Most target ids kept in OPTION_ACTIVE_TYPES; past it the map is TRANSIENT_TARGETS. */
	const TARGETS_AUTOLOAD_MAX = 500;

	/**
	 * Transient `{ v, configs }`: the stored payload of every active bundle, keyed
	 * by id, at data version `v`.
	 */
	const TRANSIENT_CONFIGS = 'wffn_bundles_active_configs';

	/** Most active bundles kept in TRANSIENT_CONFIGS; past it each request reads its own rows. */
	const ACTIVE_CONFIGS_MAX = 50;

	/**
	 * Option `{ v, configs }`: the same payload as TRANSIENT_CONFIGS, autoloaded, for
	 * the stores whose active set is small enough to ride along with the other
	 * autoloaded options instead of costing a read of its own.
	 */
	const OPTION_ACTIVE_CONFIGS = 'wffn_bundles_active_configs_al';

	/**
	 * Largest serialized active set that is worth autoloading, in bytes.
	 *
	 * Every storefront request pays the autoloaded payload whether or not it shows a
	 * bundle, so this is a trade of bytes on every request against one query on the
	 * requests that need the set. The same trade TARGETS_AUTOLOAD_MAX makes for the
	 * target map, counted in bytes because a config is far larger than an id.
	 */
	const CONFIGS_AUTOLOAD_MAX_BYTES = 50000;

	/** Object-cache group for candidate lookups. */
	const CACHE_GROUP = 'wffn_bundles_bundles';

	/** Most target names resolved per bundle for the list; counts stay exact. */
	const TARGET_SAMPLE = 10;

	/**
	 * Payload keys kept out of the JSON blob, because a real column already holds them.
	 *
	 * encode() strips them on write and row_to_payload() overlays them on read, so
	 * the column is the only copy and the two can never drift. Never add a key here
	 * that no column holds.
	 */
	const COLUMN_KEYS = array(
		'id',
		'name',
		'status',
		'discountType',
		'discount_type',
		'createdAt',
		'updatedAt',
	);

	/** @var array<string,array|null> Request memo for get_config(). */
	private static $config_memo = array();

	/** @var array|null Request memo for index(). */
	private static $index_memo = null;

	/** @var int|null Request memo for the version counter. */
	private static $version_memo = null;

	/** @var int[]|null Request memo for active_types(). */
	private static $active_types_memo = null;

	/** @var int|null Request memo for the number of active bundles. */
	private static $active_count_memo = null;

	/** @var array|null Request memo for active_target_map(). */
	private static $targets_memo = null;

	/** @var array|false|null Request memo for active_configs(); false when it cannot answer. */
	private static $active_configs_memo = null;

	/**
	 * Current bundle-data version; part of every cache key.
	 *
	 * @return int
	 */
	public static function version() {
		if ( null === self::$version_memo ) {
			self::$version_memo = (int) get_option( self::OPTION_VERSION, 1 );
		}
		return self::$version_memo;
	}

	/**
	 * Invalidate every derived read after a write.
	 *
	 * Bumping the stored counter changes every cache key, which is what makes
	 * invalidation work across processes and a persistent object cache.
	 *
	 * @return void
	 */
	public static function bump_version() {
		$next = self::version() + 1;
		update_option( self::OPTION_VERSION, $next, true );

		self::$version_memo        = $next;
		self::$config_memo         = array();
		self::$index_memo          = null;
		self::$active_types_memo   = null;
		self::$active_count_memo   = null;
		self::$targets_memo        = null;
		self::$active_configs_memo = null;
	}

	/**
	 * Type ids that at least one active bundle has, without a query once cached.
	 *
	 * A superset of what renders (schedules are not applied), so an empty list
	 * safely means no bundle can show anywhere.
	 *
	 * @return int[]
	 */
	public static function active_types() {
		if ( null !== self::$active_types_memo ) {
			return self::$active_types_memo;
		}

		$version = self::version();
		$stored  = get_option( self::OPTION_ACTIVE_TYPES );
		if ( is_array( $stored ) && isset( $stored['v'], $stored['types'], $stored['count'] ) && array_key_exists( 'targets', $stored ) && (int) $stored['v'] === $version && is_array( $stored['types'] ) ) {
			self::$active_types_memo = array_map( 'intval', $stored['types'] );
			self::$active_count_memo = (int) $stored['count'];
			if ( is_array( $stored['targets'] ) ) {
				self::$targets_memo = $stored['targets'];
			}
			return self::$active_types_memo;
		}

		$types = array();
		$count = 0;
		$map   = array();
		if ( Bundles_Table::exists() ) {
			global $wpdb;
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT discount_type, COUNT(*) AS n FROM %i WHERE status = 1 GROUP BY discount_type', Bundles_Table::table_name() ), ARRAY_A );
			foreach ( $rows as $row ) {
				$types[] = (int) $row['discount_type'];
				$count  += (int) $row['n'];
			}
			$map = $count ? self::query_target_map() : array();
		}

		$ids = 0;
		foreach ( $map as $kinds ) {
			$ids += count( $kinds['products'] ) + count( $kinds['categories'] );
		}
		$autoload = $ids <= self::TARGETS_AUTOLOAD_MAX;
		if ( ! $autoload ) {
			set_transient(
				self::TRANSIENT_TARGETS,
				array(
					'v'     => $version,
					'types' => $map,
				),
				DAY_IN_SECONDS
			);
		}
		update_option(
			self::OPTION_ACTIVE_TYPES,
			array(
				'v'       => $version,
				'types'   => $types,
				'count'   => $count,
				'targets' => $autoload ? $map : null,
			),
			true
		);

		// Nothing live means nothing to cache: a store that pauses its last bundle
		// should stop paying for the autoloaded set on every request.
		if ( 0 === $count ) {
			delete_option( self::OPTION_ACTIVE_CONFIGS );
		}

		self::$active_types_memo = $types;
		self::$active_count_memo = $count;
		self::$targets_memo      = $map;
		return $types;
	}

	/**
	 * How many bundles are active, without a query once cached.
	 *
	 * @return int
	 */
	private static function active_count() {
		self::active_types();
		return (int) self::$active_count_memo;
	}

	/**
	 * Whether at least one bundle of the given types exists, in any status.
	 *
	 * @param int[] $types Bundle type ids.
	 * @return bool
	 */
	public static function has_any( array $types ) {
		$types = array_map( 'intval', $types );
		if ( ! $types ) {
			return false;
		}
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT EXISTS(SELECT 1 FROM %i WHERE discount_type IN (' . implode( ', ', array_fill( 0, count( $types ), '%d' ) ) . ') LIMIT 1)', array_merge( array( Bundles_Table::table_name() ), $types ) ) );
	}

	/**
	 * Bundles counted per type, in any status and active.
	 *
	 * @return array<int,array{total:int,active:int}> Keyed by type id; types with no bundle are absent.
	 */
	public static function type_counts() {
		if ( ! Bundles_Table::exists() ) {
			return array();
		}
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT discount_type, COUNT(*) AS total, SUM(status = 1) AS active FROM %i GROUP BY discount_type', Bundles_Table::table_name() ), ARRAY_A );

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row['discount_type'] ] = array(
				'total'  => (int) $row['total'],
				'active' => (int) $row['active'],
			);
		}
		return $counts;
	}

	/**
	 * Whether any bundle is active.
	 *
	 * @return bool
	 */
	public static function has_active() {
		return array() !== self::active_types();
	}

	/**
	 * Ordered list-item summaries, including the placeholder metric keys.
	 *
	 * Selects summary columns only: pulling the `config` LONGTEXT for every row
	 * would not scale on the unbounded export. Memoized for the request.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function index() {
		if ( null !== self::$index_memo ) {
			return self::$index_memo;
		}
		if ( ! Bundles_Table::exists() ) {
			return array();
		}

		global $wpdb;
		$table = Bundles_Table::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, name, discount_type, status, schedule_mode, start_date, end_date, menu_order, created_at, updated_at FROM %i ORDER BY menu_order ASC, id ASC', $table ),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::row_to_summary( $row );
		}

		self::$index_memo = $out;
		return $out;
	}

	/**
	 * Paginated list payload; search, ordering and pagination all run in SQL.
	 *
	 * @param array $args Accepts `page`, `per_page` and `search`.
	 * @return array
	 */
	public static function list_bundles( array $args = array() ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 10 ) );
		$search   = trim( (string) ( $args['search'] ?? '' ) );

		if ( ! Bundles_Table::exists() ) {
			return array(
				'bundles'   => array(),
				'total'       => 0,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => 0,
			);
		}

		global $wpdb;
		$table  = Bundles_Table::table_name();
		$offset = ( $page - 1 ) * $per_page;

		// Only the types an active plugin names: a deactivated add-on's bundles
		// leave the list and come back with it (Bundle_Store::listed_types()).
		$listed = Bundle_Store::listed_types();

		if ( '' === $search ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE discount_type IN (' . implode( ', ', array_fill( 0, count( $listed ), '%d' ) ) . ')', array_merge( array( $table ), $listed ) ) );
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, name, discount_type, status, schedule_mode, start_date, end_date, menu_order, created_at, updated_at FROM %i WHERE discount_type IN (' . implode( ', ', array_fill( 0, count( $listed ), '%d' ) ) . ') ORDER BY menu_order ASC, id ASC LIMIT %d OFFSET %d',
					array_merge( array( $table ), $listed, array( $per_page, $offset ) )
				),
				ARRAY_A
			);
		} else {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// A type label must return every bundle of that type; -1 matches no type.
			$types = self::types_matching( $search );
			$types = $types ? $types : array( -1 );
			$args  = array_merge( array( $table ), $listed, array( $like, $like, $like, $like ), $types );

			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE discount_type IN (' . implode( ', ', array_fill( 0, count( $listed ), '%d' ) ) . ') AND ( name LIKE %s OR CAST(id AS CHAR) LIKE %s OR start_date LIKE %s OR end_date LIKE %s OR discount_type IN (' . implode( ', ', array_fill( 0, count( $types ), '%d' ) ) . ') )',
					$args
				)
			);
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, name, discount_type, status, schedule_mode, start_date, end_date, menu_order, created_at, updated_at FROM %i WHERE discount_type IN (' . implode( ', ', array_fill( 0, count( $listed ), '%d' ) ) . ') AND ( name LIKE %s OR CAST(id AS CHAR) LIKE %s OR start_date LIKE %s OR end_date LIKE %s OR discount_type IN (' . implode( ', ', array_fill( 0, count( $types ), '%d' ) ) . ') ) ORDER BY menu_order ASC, id ASC LIMIT %d OFFSET %d',
					array_merge( $args, array( $per_page, $offset ) )
				),
				ARRAY_A
			);
		}

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::row_to_summary( $row );
		}

		return array(
			'bundles'   => $items,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * The type ids whose label contains the search term.
	 *
	 * @param string $search Search term.
	 * @return int[]
	 */
	private static function types_matching( $search ) {
		$needle = strtolower( $search );
		$types  = array();
		foreach ( Bundle_Store::listed_types() as $type ) {
			if ( false !== strpos( strtolower( Bundle_Store::type_label( $type ) ), $needle ) ) {
				$types[] = (int) $type;
			}
		}
		return $types;
	}

	/**
	 * Full stored payload for one bundle, or null. Request-memoized.
	 *
	 * @param int|string $id Bundle id.
	 * @return array|null
	 */
	public static function get_config( $id ) {
		$id = self::clean_id( $id );
		if ( '' === $id ) {
			return null;
		}
		if ( array_key_exists( $id, self::$config_memo ) ) {
			return self::$config_memo[ $id ];
		}

		$found = self::get_configs( array( $id ) );
		return $found[ $id ] ?? null;
	}

	/**
	 * Drop the request memo of loaded payloads.
	 *
	 * For a caller that walks a very large set once (the export) and would
	 * otherwise hold every payload in memory until the request ends.
	 *
	 * @return void
	 */
	public static function release_configs() {
		self::$config_memo = array();
	}

	/**
	 * Batch-load payloads for many ids in one query, memoizing each.
	 *
	 * @param array $ids Bundle ids.
	 * @return array<string,array|null> id => payload (null for ids that do not exist).
	 */
	public static function get_configs( array $ids ) {
		$wanted = array();
		foreach ( $ids as $id ) {
			$id = self::clean_id( $id );
			if ( '' !== $id ) {
				$wanted[ $id ] = true;
			}
		}
		$wanted = array_keys( $wanted );
		if ( empty( $wanted ) ) {
			return array();
		}

		$missing = array_values( array_filter(
			$wanted,
			function ( $id ) {
				return ! array_key_exists( $id, self::$config_memo );
			}
		) );

		$active = $missing ? self::active_configs() : null;
		if ( null !== $active ) {
			foreach ( $missing as $i => $id ) {
				if ( isset( $active[ $id ] ) ) {
					self::$config_memo[ $id ] = $active[ $id ];
					unset( $missing[ $i ] );
				}
			}
			$missing = array_values( $missing );
		}

		if ( $missing && Bundles_Table::exists() ) {
			global $wpdb;
			$table   = Bundles_Table::table_name();
			$missing = array_map( 'intval', $missing );
			$rows    = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, name, discount_type, status, config, created_at, updated_at FROM %i WHERE id IN (' . implode( ', ', array_fill( 0, count( $missing ), '%d' ) ) . ')',
					array_merge( array( $table ), $missing )
				),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				self::$config_memo[ (string) (int) $row['id'] ] = self::row_to_payload( $row );
			}
		}

		$out = array();
		foreach ( $wanted as $id ) {
			// Cache the "does not exist" answer, so an old cart line is not re-queried.
			if ( ! array_key_exists( $id, self::$config_memo ) ) {
				self::$config_memo[ $id ] = null;
			}
			$out[ $id ] = self::$config_memo[ $id ];
		}
		return $out;
	}

	/**
	 * Every active bundle's stored payload, keyed by id, or null when this request
	 * must read the table instead.
	 *
	 * Cached across requests at the current data version, so any bundle write
	 * retires it.
	 *
	 * @return array<string,array>|null
	 */
	private static function active_configs() {
		if ( null !== self::$active_configs_memo ) {
			return false === self::$active_configs_memo ? null : self::$active_configs_memo;
		}
		self::$active_configs_memo = false;

		if ( ! self::caches_active_configs() ) {
			return null;
		}

		$version = self::version();

		// The autoloaded copy first: on a store small enough to keep one it is already
		// in memory, so the set costs this request nothing at all.
		$stored = get_option( self::OPTION_ACTIVE_CONFIGS );
		if ( self::valid_configs_payload( $stored, $version ) ) {
			self::$active_configs_memo = $stored['configs'];
			return self::$active_configs_memo;
		}

		self::prime_active_set();
		$stored = get_transient( self::TRANSIENT_CONFIGS );
		if ( self::valid_configs_payload( $stored, $version ) ) {
			// A set written before this store had an autoloaded copy, or before the
			// budget allowed one: move it now rather than wait for the next bundle
			// write, which may be a long way off.
			self::write_configs_cache( $version, $stored['configs'] );
			self::$active_configs_memo = $stored['configs'];
			return self::$active_configs_memo;
		}

		global $wpdb;
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, name, discount_type, status, config, created_at, updated_at FROM %i WHERE status = 1 LIMIT %d', Bundles_Table::table_name(), self::ACTIVE_CONFIGS_MAX + 1 ),
			ARRAY_A
		);
		if ( count( $rows ) > self::ACTIVE_CONFIGS_MAX ) {
			return null;
		}

		$configs = array();
		foreach ( $rows as $row ) {
			$payload = self::row_to_payload( $row );
			if ( null !== $payload ) {
				$configs[ $payload['id'] ] = $payload;
			}
		}
		self::write_configs_cache( $version, $configs );

		self::$active_configs_memo = $configs;
		return $configs;
	}

	/**
	 * Whether a stored active-set payload is usable at this data version.
	 *
	 * @param mixed $stored  What the option or the transient held.
	 * @param int   $version Current data version.
	 * @return bool
	 */
	private static function valid_configs_payload( $stored, $version ) {
		return is_array( $stored )
			&& isset( $stored['v'], $stored['configs'] )
			&& (int) $stored['v'] === $version
			&& is_array( $stored['configs'] );
	}

	/**
	 * Store the active set where this store's size says it belongs.
	 *
	 * Small enough and it goes in an autoloaded option, so the storefront reads it
	 * for free; past that it stays a transient, read on demand, and the option is
	 * dropped so no stale autoloaded copy is left behind. Either way the payload
	 * carries the data version, so a bundle write retires it without a delete.
	 *
	 * @param int   $version Current data version.
	 * @param array $configs Active bundle payloads keyed by id.
	 * @return void
	 */
	private static function write_configs_cache( $version, array $configs ) {
		$payload = array(
			'v'       => $version,
			'configs' => $configs,
		);

		/**
		 * Filters the largest serialized active set that is autoloaded, in bytes.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $bytes   Size budget.
		 * @param array $configs Active bundle payloads keyed by id.
		 */
		$budget = (int) apply_filters( 'wffn_bundles_active_configs_autoload_max_bytes', self::CONFIGS_AUTOLOAD_MAX_BYTES, $configs );

		if ( $budget > 0 && strlen( (string) maybe_serialize( $payload ) ) <= $budget ) {
			update_option( self::OPTION_ACTIVE_CONFIGS, $payload, true );
			delete_transient( self::TRANSIENT_CONFIGS );
			return;
		}

		delete_option( self::OPTION_ACTIVE_CONFIGS );
		set_transient( self::TRANSIENT_CONFIGS, $payload, DAY_IN_SECONDS );
	}

	/**
	 * Whether this request reads active payloads from TRANSIENT_CONFIGS.
	 *
	 * The editor, the REST routes and admin screens always read the table, so no
	 * write path merges a patch into a cached payload.
	 *
	 * @return bool
	 */
	private static function caches_active_configs() {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! Bundles_Table::exists() ) {
			return false;
		}
		$count = self::active_count();
		return $count > 0 && $count <= self::ACTIVE_CONFIGS_MAX;
	}

	/**
	 * Load the active-set transients this request reads in one query, once per request.
	 *
	 * @return void
	 */
	private static function prime_active_set() {
		static $primed = false;
		if ( $primed || wp_using_ext_object_cache() || ! function_exists( 'wp_prime_option_caches' ) ) {
			return;
		}
		$primed = true;

		self::active_types();
		$names = array();
		if ( null === self::$targets_memo ) {
			$names[] = self::TRANSIENT_TARGETS;
		}
		// An autoloaded active set is already in memory; priming its transient would
		// read a pair of rows this request never looks at.
		if ( self::caches_active_configs() && ! self::valid_configs_payload( get_option( self::OPTION_ACTIVE_CONFIGS ), self::version() ) ) {
			$names[] = self::TRANSIENT_CONFIGS;
		}
		if ( ! $names ) {
			return;
		}
		$options = array();
		foreach ( $names as $name ) {
			$options[] = '_transient_' . $name;
			$options[] = '_transient_timeout_' . $name;
		}
		wp_prime_option_caches( $options );
	}

	/**
	 * Ordered candidate bundle ids for a product, from the indexed lookup.
	 *
	 * Returns a superset of the bundles that can match; category exclusions and
	 * every naming subtlety are left to Bundle_Store::targets_product(), which
	 * the caller runs on each candidate in order. The result depends only on the
	 * product and the version counter, so it is safe to share across users; the
	 * schedule and role gates are neither applied nor cached here.
	 *
	 * @param int|string $product_id Product id.
	 * @param bool       $any_status Include inactive bundles too.
	 * @param bool       $fresh      Skip the cached answer, which a category change on the product does not expire.
	 * @return string[]
	 */
	public static function candidate_ids_for_product( $product_id, $any_status = false, $fresh = false ) {
		$pid = absint( $product_id );
		if ( ! $pid || ! Bundles_Table::exists() ) {
			return array();
		}
		// The product's index answers; an absent one is built from the query below, any status.
		if ( ! $any_status ) {
			$placed = Product_Placement::candidates( $pid );
			if ( null !== $placed ) {
				return $placed;
			}
		}

		$cache_key = 'cands_' . $pid . ( $any_status ? '_all' : '' ) . '_v' . self::version();
		$cached    = $fresh ? null : wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$term_ids = self::product_term_ids( $pid );

		global $wpdb;
		$bundles = Bundles_Table::table_name();
		$targets   = Bundles_Table::targets_table_name();

		// Params are collected in placeholder order: the LEFT JOIN binds before the WHERE.
		$params = array( Bundles_Table::TARGET_EXCL_PRODUCT, $pid, Bundles_Table::TARGET_PRODUCT, $pid );

		// A product with no terms matches no category row: object_id 0 never exists,
		// and an empty IN () list would be a syntax error.
		$term_ids = $term_ids ? array_map( 'intval', $term_ids ) : array( 0 );
		$params[] = Bundles_Table::TARGET_CATEGORY;
		$params   = array_merge( $params, $term_ids );
		// `%d = 1 OR c.status = 1`: the status filter applies unless every status was asked for.
		$params   = array_merge( array( $bundles, $targets, $targets ), $params, array( $any_status ? 1 : 0 ) );

		// LEFT JOIN IS NULL, not NOT IN (SELECT): MySQL 5.6 optimizes the anti-join.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT c.id, c.menu_order
				FROM %i c
				INNER JOIN %i t
					ON t.bundle_id = c.id
				LEFT JOIN %i x
					ON x.bundle_id = c.id
					AND x.target_type = %d
					AND x.object_id = %d
				WHERE ( ( t.target_type = %d AND t.object_id = %d ) OR ( t.target_type = %d AND t.object_id IN (' . implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) . ') ) )
					AND x.id IS NULL
					AND ( %d = 1 OR c.status = 1 )
				ORDER BY c.menu_order ASC, c.id ASC',
				$params
			)
		);
		$ids  = array_map( 'strval', array_map( 'intval', (array) $rows ) );

		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP, DAY_IN_SECONDS );
		return $ids;
	}

	/**
	 * Whether any active bundle of these types names the product, directly or by category.
	 *
	 * The same two target rows candidate_ids_for_product() matches, read once per
	 * request for every product, so a page of cards costs one query. A superset:
	 * exclusions and every other gate are left to the caller's full resolution.
	 *
	 * @param int|string $product_id Product id.
	 * @param int[]      $types      Bundle type ids.
	 * @return bool
	 */
	public static function may_target( $product_id, array $types ) {
		$pid     = absint( $product_id );
		$targets = self::active_targets( $types );
		if ( ! $pid || ( ! $targets['products'] && ! $targets['categories'] ) ) {
			return false;
		}
		if ( isset( $targets['products'][ $pid ] ) ) {
			return true;
		}
		if ( ! $targets['categories'] ) {
			return false;
		}
		foreach ( self::product_term_ids( $pid ) as $term_id ) {
			if ( isset( $targets['categories'][ $term_id ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Product and category ids targeted by active bundles of these types.
	 *
	 * @param int[] $types Bundle type ids.
	 * @return array{products: array<int,bool>, categories: array<int,bool>}
	 */
	private static function active_targets( array $types ) {
		static $memo = array();

		$types = array_values( array_unique( array_map( 'intval', $types ) ) );
		sort( $types );
		$out = array(
			'products'   => array(),
			'categories' => array(),
		);
		if ( ! $types || ! Bundles_Table::exists() ) {
			return $out;
		}

		$cache_key = implode( '-', $types ) . '_v' . self::version();
		if ( isset( $memo[ $cache_key ] ) ) {
			return $memo[ $cache_key ];
		}

		$map = self::active_target_map();
		foreach ( $types as $type ) {
			if ( isset( $map[ $type ] ) ) {
				$out['products']   += $map[ $type ]['products'];
				$out['categories'] += $map[ $type ]['categories'];
			}
		}

		$memo[ $cache_key ] = $out;
		return $out;
	}

	/**
	 * Per active bundle type, the product and category ids its bundles target.
	 *
	 * Cached across requests at the current data version, so any bundle write
	 * retires it; it holds bundle data only, never a product's own categories.
	 *
	 * @return array<int,array{products: array<int,bool>, categories: array<int,bool>}>
	 */
	private static function active_target_map() {
		if ( null === self::$targets_memo ) {
			self::active_types();
		}
		if ( null !== self::$targets_memo ) {
			return self::$targets_memo;
		}

		self::prime_active_set();
		$version = self::version();
		$stored  = get_transient( self::TRANSIENT_TARGETS );
		if ( is_array( $stored ) && isset( $stored['v'], $stored['types'] ) && (int) $stored['v'] === $version && is_array( $stored['types'] ) ) {
			self::$targets_memo = $stored['types'];
			return self::$targets_memo;
		}

		$map = self::query_target_map();
		set_transient(
			self::TRANSIENT_TARGETS,
			array(
				'v'     => $version,
				'types' => $map,
			),
			DAY_IN_SECONDS
		);

		self::$targets_memo = $map;
		return $map;
	}

	/**
	 * Per active bundle type, the product and category ids its bundles target, from the table.
	 *
	 * @return array<int,array{products: array<int,bool>, categories: array<int,bool>}>
	 */
	private static function query_target_map() {
		global $wpdb;
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT c.discount_type, t.target_type, t.object_id
				FROM %i c
				INNER JOIN %i t
					ON t.bundle_id = c.id
				WHERE c.status = 1
					AND t.target_type IN (%d, %d)',
				Bundles_Table::table_name(),
				Bundles_Table::targets_table_name(),
				Bundles_Table::TARGET_PRODUCT,
				Bundles_Table::TARGET_CATEGORY
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows as $row ) {
			$type = (int) $row['discount_type'];
			if ( ! isset( $map[ $type ] ) ) {
				$map[ $type ] = array(
					'products'   => array(),
					'categories' => array(),
				);
			}
			$kind                                   = Bundles_Table::TARGET_CATEGORY === (int) $row['target_type'] ? 'categories' : 'products';
			$map[ $type ][ $kind ][ (int) $row['object_id'] ] = true;
		}

		return $map;
	}

	/**
	 * The product's own product_cat term ids.
	 *
	 * wc_get_product_term_ids(), not wc_get_product_cat_ids(): the latter folds in
	 * parent categories, which has_term() does not. Variations resolve to their
	 * parent first.
	 *
	 * @param int|string $product_id Product id.
	 * @return int[]
	 */
	private static function product_term_ids( $product_id ) {
		$post_id = absint( $product_id );
		if ( ! $post_id ) {
			return array();
		}
		if ( 'product_variation' === get_post_type( $post_id ) ) {
			$parent  = (int) wp_get_post_parent_id( $post_id );
			$post_id = $parent ? $parent : $post_id;
		}
		if ( ! function_exists( 'wc_get_product_term_ids' ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'absint', (array) wc_get_product_term_ids( $post_id, 'product_cat' ) ) ) );
	}

	/**
	 * Insert a bundle; the payload is stored verbatim and the columns derived.
	 *
	 * `menu_order` is MIN - 1, landing the row at the top. That order is also the
	 * list rank and the product-page resolution precedence.
	 *
	 * @param array $payload Bundle payload.
	 * @return array|null The stored payload, or null on failure.
	 */
	public static function create( array $payload ) {
		if ( ! Bundles_Table::exists() ) {
			// A dormant site installs on its first save, whichever path makes it.
			Install::maybe_upgrade();
			if ( ! Bundles_Table::exists() ) {
				return null;
			}
		}

		global $wpdb;
		$table = Bundles_Table::table_name();
		$now   = self::now_utc();

		$payload['status']    = isset( $payload['status'] ) ? (int) $payload['status'] : 0;
		$payload['createdAt'] = self::iso( $now );
		$payload['updatedAt'] = self::iso( $now );

		$min   = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(menu_order) FROM %i', $table ) );
		$order = ( null === $min ) ? 0 : ( (int) $min - 1 );

		$row               = self::payload_to_columns( $payload );
		$row['menu_order'] = $order;
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		// One statement: the blob carries no id, so nothing needs writing back.
		$ok = $wpdb->insert( $table, $row );
		if ( ! $ok ) {
			return null;
		}

		$id            = (string) (int) $wpdb->insert_id;
		$payload['id'] = $id;

		self::sync_targets( $id, $payload );
		self::bump_version();
		self::$config_memo[ $id ] = $payload;
		Install::mark_in_use();
		/**
		 * Fires after a bundle is created or updated, with the payload as stored.
		 *
		 * @since 1.0.0
		 *
		 * @param string              $id      Bundle id.
		 * @param array<string,mixed> $payload The stored bundle payload.
		 */
		do_action( 'wffn_bundles_bundle_saved', $id, $payload );

		return $payload;
	}

	/**
	 * Replace a stored bundle with the given, already-merged payload.
	 *
	 * @param int|string $id      Bundle id.
	 * @param array      $payload Merged bundle payload.
	 * @return array|null The stored payload, or null on failure.
	 */
	public static function update( $id, array $payload ) {
		$id = self::clean_id( $id );
		if ( '' === $id || ! Bundles_Table::exists() ) {
			return null;
		}

		global $wpdb;
		$table = Bundles_Table::table_name();
		$now   = self::now_utc();

		$payload['id']        = $id;
		$payload['updatedAt'] = self::iso( $now );

		$row               = self::payload_to_columns( $payload );
		$row['updated_at'] = $now;
		$row['config']     = self::encode( $payload );

		$updated = $wpdb->update( $table, $row, array( 'id' => (int) $id ) );
		if ( false === $updated ) {
			return null;
		}

		self::sync_targets( $id, $payload );
		self::bump_version();
		self::$config_memo[ $id ] = $payload;
		Install::mark_in_use();
		/**
		 * Fires after a bundle is created or updated, with the payload as stored.
		 *
		 * @since 1.0.0
		 *
		 * @param string              $id      Bundle id.
		 * @param array<string,mixed> $payload The stored bundle payload.
		 */
		do_action( 'wffn_bundles_bundle_saved', $id, $payload );

		return $payload;
	}

	/**
	 * Toggle status with a single two-column UPDATE.
	 *
	 * The column is the only copy of `status`, so this never rewrites the LONGTEXT.
	 * The existence read stays because the REST layer needs the 404.
	 *
	 * @param int|string $id     Bundle id.
	 * @param int        $status 0 for draft, 1 for active.
	 * @return int|null The new status, or null when the bundle does not exist.
	 */
	public static function set_status( $id, $status ) {
		$id      = self::clean_id( $id );
		$current = self::get_config( $id );
		if ( null === $current ) {
			return null;
		}

		global $wpdb;
		$table  = Bundles_Table::table_name();
		$now    = self::now_utc();
		$status = 1 === (int) $status ? 1 : 0;

		$wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => $now,
			),
			array( 'id' => (int) $id )
		);

		$current['status']    = $status;
		$current['updatedAt'] = self::iso( $now );

		// bump_version() clears the memo, so re-prime after it, as the write paths do.
		self::bump_version();
		self::$config_memo[ $id ] = $current;
		Install::mark_in_use();

		return $status;
	}

	/**
	 * Delete a bundle and its target rows.
	 *
	 * @param int|string $id Bundle id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$id = self::clean_id( $id );
		if ( '' === $id || ! Bundles_Table::exists() ) {
			return false;
		}

		global $wpdb;
		$old = self::placement_rows( (int) $id );
		$wpdb->delete( Bundles_Table::table_name(), array( 'id' => (int) $id ) );
		$wpdb->delete( Bundles_Table::targets_table_name(), array( 'bundle_id' => (int) $id ) );
		Product_Placement::sync( (int) $id, $old, array() );

		self::bump_version();
		self::$config_memo[ $id ] = null;

		/**
		 * Fires after a bundle and its target rows are deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param string $id Bundle id.
		 */
		do_action( 'wffn_bundles_bundle_deleted', $id );

		return true;
	}

	/**
	 * Re-order a subset in place, leaving every other bundle untouched.
	 *
	 * The ids keep the slots they already occupy, so a drag on one page of the list
	 * cannot move bundles on another. Use this, not reorder(), whenever the caller
	 * holds only part of the list.
	 *
	 * @param string[] $ids Ordered ids from the page being dragged.
	 * @return bool
	 */
	public static function reorder_subset( array $ids ) {
		if ( ! Bundles_Table::exists() ) {
			return true;
		}

		global $wpdb;
		$table = Bundles_Table::table_name();

		$current = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY menu_order ASC, id ASC', $table ) );
		$current = array_map( 'strval', array_map( 'intval', (array) $current ) );
		if ( empty( $current ) ) {
			return true;
		}

		// Only ids that exist, in the order requested, de-duplicated.
		$wanted = array();
		foreach ( $ids as $id ) {
			$id = self::clean_id( $id );
			if ( '' !== $id && in_array( $id, $current, true ) && ! in_array( $id, $wanted, true ) ) {
				$wanted[] = $id;
			}
		}
		if ( count( $wanted ) < 2 ) {
			return true; // Nothing to rearrange.
		}

		// Their current slots, ascending, then the requested sequence dealt into them.
		$slots = array();
		foreach ( $current as $pos => $id ) {
			if ( in_array( $id, $wanted, true ) ) {
				$slots[] = $pos;
			}
		}
		foreach ( $slots as $i => $pos ) {
			$current[ $pos ] = $wanted[ $i ];
		}

		return self::reorder( $current );
	}

	/**
	 * Apply an explicit id order.
	 *
	 * Ids present in $ids take that order; ids absent keep their relative order and
	 * sink below. The sink rule is only correct for a complete order, so a caller
	 * holding part of the list wants reorder_subset().
	 *
	 * @param array $ids Ordered bundle ids.
	 * @return bool
	 */
	public static function reorder( array $ids ) {
		if ( ! Bundles_Table::exists() ) {
			return true;
		}

		global $wpdb;
		$table = Bundles_Table::table_name();

		$current = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY menu_order ASC, id ASC', $table ) );
		$current = array_map( 'strval', array_map( 'intval', (array) $current ) );
		if ( empty( $current ) ) {
			return true;
		}

		$rank = array();
		foreach ( $ids as $i => $id ) {
			$id = self::clean_id( $id );
			if ( '' !== $id ) {
				$rank[ $id ] = $i;
			}
		}

		// usort() is not stable before PHP 8.0, so the position tiebreaker is explicit.
		$decorated = array();
		foreach ( $current as $pos => $id ) {
			$decorated[] = array( $rank[ $id ] ?? PHP_INT_MAX, $pos, $id );
		}
		usort(
			$decorated,
			function ( $a, $b ) {
				return $a[0] === $b[0] ? ( $a[1] <=> $b[1] ) : ( $a[0] <=> $b[0] );
			}
		);

		$args    = array( $table );
		$row_ids = array();
		foreach ( $decorated as $order => $entry ) {
			$args[]    = (int) $entry[2];
			$args[]    = $order;
			$row_ids[] = (int) $entry[2];
		}

		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET menu_order = CASE id ' . implode( ' ', array_fill( 0, count( $row_ids ), 'WHEN %d THEN %d' ) ) . ' END WHERE id IN ( ' . implode( ', ', array_fill( 0, count( $row_ids ), '%d' ) ) . ' )',
				array_merge( $args, $row_ids )
			)
		);

		self::bump_version();
		return true;
	}

	/**
	 * Every bundle id in resolution order, mapped to its 1-based rank.
	 *
	 * The rank, not the raw menu_order, is what the list shows,
	 * since the stored values are neither dense nor non-negative. Global by design,
	 * so a searched or paginated list still reports the position that decides a match.
	 *
	 * @return array<string,int> Bundle id => rank, starting at 1.
	 */
	public static function rank_map() {
		if ( ! Bundles_Table::exists() ) {
			return array();
		}

		$cache_key = 'ranks_v' . self::version();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = Bundles_Table::table_name();

		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i ORDER BY menu_order ASC, id ASC', $table ) );
		$ranks = array();
		foreach ( (array) $ids as $pos => $id ) {
			$ranks[ (string) (int) $id ] = $pos + 1;
		}

		wp_cache_set( $cache_key, $ranks, self::CACHE_GROUP, DAY_IN_SECONDS );
		return $ranks;
	}

	/**
	 * Target rows for a set of bundles, grouped and sampled for display.
	 *
	 * Reads the target index, not the config blobs, so the list reports what the
	 * storefront actually resolves. `kind` is derived from the rows present rather
	 * than the `target_mode` column, matching how targets_product() treats them.
	 *
	 * @param int[] $ids Bundle ids.
	 * @return array<int,array> Bundle id => summary with kind, count, ids, excl_products, excl_categories.
	 */
	public static function target_index( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) || ! Bundles_Table::exists() ) {
			return array();
		}

		$cache_key = 'tgtidx_' . md5( implode( ',', $ids ) ) . '_v' . self::version();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT bundle_id, target_type, object_id
				FROM %i
				WHERE bundle_id IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')
				ORDER BY bundle_id ASC, target_type ASC, object_id ASC',
				array_merge( array( Bundles_Table::targets_table_name() ), array_map( 'intval', $ids ) )
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$cid  = (int) $row['bundle_id'];
			$type = (int) $row['target_type'];
			$oid  = (int) $row['object_id'];

			if ( ! isset( $out[ $cid ] ) ) {
				$out[ $cid ] = array(
					'kind'            => 0,
					'count'           => 0,
					'ids'             => array(),
					'excl_products'   => 0,
					'excl_categories' => 0,
				);
			}

			if ( Bundles_Table::TARGET_EXCL_PRODUCT === $type ) {
				++$out[ $cid ]['excl_products'];
				continue;
			}
			if ( Bundles_Table::TARGET_EXCL_CATEGORY === $type ) {
				++$out[ $cid ]['excl_categories'];
				continue;
			}

			$out[ $cid ]['kind'] = $type;
			++$out[ $cid ]['count'];
			if ( count( $out[ $cid ]['ids'] ) < self::TARGET_SAMPLE ) {
				$out[ $cid ]['ids'][] = $oid;
			}
		}

		wp_cache_set( $cache_key, $out, self::CACHE_GROUP, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * A bundle's product, exclusion and category rows as they stand, `[ target type, object id ]`.
	 *
	 * @param int $bundle_id Bundle id.
	 * @return array[]
	 */
	private static function placement_rows( $bundle_id ) {
		global $wpdb;
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT target_type, object_id FROM %i WHERE bundle_id = %d AND target_type IN (%d, %d, %d)',
				Bundles_Table::targets_table_name(),
				(int) $bundle_id,
				Bundles_Table::TARGET_PRODUCT,
				Bundles_Table::TARGET_EXCL_PRODUCT,
				Bundles_Table::TARGET_CATEGORY
			),
			ARRAY_N
		);

		return array_map(
			function ( $row ) {
				return array( (int) $row[0], (int) $row[1] );
			},
			$rows
		);
	}

	/**
	 * Rewrite a bundle's target rows from its payload.
	 *
	 * Delete-then-insert rather than a diff, and with no transaction: `config` is
	 * the authority and targets_product() re-validates, so a partially written
	 * index can only cost a candidate, never produce a wrong match.
	 *
	 * @param int|string $id      Bundle id.
	 * @param array      $payload Bundle payload.
	 * @return void
	 */
	private static function sync_targets( $id, array $payload ) {
		if ( ! Bundles_Table::exists() ) {
			return;
		}

		global $wpdb;
		$targets = Bundles_Table::targets_table_name();
		$cid     = (int) $id;

		$old = self::placement_rows( $cid );
		$wpdb->delete( $targets, array( 'bundle_id' => $cid ) );

		$sel  = is_array( $payload['productSelection'] ?? null ) ? $payload['productSelection'] : array();
		$mode = (int) ( $sel['mode'] ?? Bundle_Store::MODE_NONE );
		$rows = array();

		// Mirrors Bundle_Store::targets_product(); see includes/storage/bundle-store.php.
		if ( Bundle_Store::MODE_SPECIFIC_CATEGORIES !== $mode ) {
			foreach ( (array) ( $sel['products'] ?? array() ) as $product ) {
				$pid = absint( is_array( $product ) ? ( $product['id'] ?? 0 ) : $product );
				if ( $pid ) {
					$rows[] = array( Bundles_Table::TARGET_PRODUCT, $pid );
				}
			}
		}

		// The lists survive an unchecked box, so rows follow the toggle, not the list.
		if ( ! empty( $sel['excludeEnabled'] ) ) {
			foreach ( (array) ( $sel['excludedProducts'] ?? array() ) as $product ) {
				$pid = absint( is_array( $product ) ? ( $product['id'] ?? 0 ) : $product );
				if ( $pid ) {
					$rows[] = array( Bundles_Table::TARGET_EXCL_PRODUCT, $pid );
				}
			}
		}

		/**
		 * Filters the index rows a bundle's placement writes.
		 *
		 * Each row is `[ target type, object id ]`. The index only narrows which
		 * bundles are worth checking, so an add-on that places a bundle by
		 * something other than an explicit product list adds its rows here and
		 * answers `wffn_bundles_bundle_targets_product` to confirm the match.
		 *
		 * @since 1.0.0
		 *
		 * @param array $rows Index rows so far.
		 * @param array $sel  The bundle's productSelection.
		 */
		$rows = (array) apply_filters( 'wffn_bundles_bundle_target_rows', $rows, $sel );

		if ( empty( $rows ) ) {
			Product_Placement::sync( $cid, $old, $rows );
			return;
		}

		$args = array( $targets );
		foreach ( $rows as $row ) {
			$args[] = $cid;
			$args[] = $row[0];
			$args[] = $row[1];
		}

		// INSERT IGNORE against the UNIQUE key: a payload may name the same
		// product twice, and two category names may resolve to one term.
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one placeholder group per row, all bound from $args.
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (bundle_id, target_type, object_id) VALUES ' . implode( ', ', array_fill( 0, count( $rows ), '(%d, %d, %d)' ) ),
				$args
			)
		);
		// After the insert, so a view rebuilding a deleted key reads the new rows.
		Product_Placement::sync( $cid, $old, $rows );
	}

	/**
	 * Rebuild the stored payload from a row.
	 *
	 * Every COLUMN_KEYS value is overlaid from its column, since encode() strips
	 * them from the blob.
	 *
	 * @param array $row Database row.
	 * @return array|null
	 */
	private static function row_to_payload( array $row ) {
		$payload = self::decode( (string) ( $row['config'] ?? '' ) );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$payload['id']     = (string) (int) ( $row['id'] ?? 0 );
		$payload['status'] = (int) ( $row['status'] ?? 0 );

		if ( array_key_exists( 'name', $row ) ) {
			$payload['name'] = (string) $row['name'];
		}
		$payload['discountType'] = (int) ( $row['discount_type'] ?? 0 );
		unset( $payload['discount_type'] );

		if ( ! empty( $row['created_at'] ) ) {
			$payload['createdAt'] = self::iso( (string) $row['created_at'] );
		}
		if ( ! empty( $row['updated_at'] ) ) {
			$payload['updatedAt'] = self::iso( (string) $row['updated_at'] );
		}

		return $payload;
	}

	/**
	 * Derive the indexed columns from a payload.
	 *
	 * `name` is clamped at write time, so an over-long name cannot fail the insert
	 * on strict-mode MySQL.
	 *
	 * @param array $payload Bundle payload.
	 * @return array
	 */
	private static function payload_to_columns( array $payload ) {
		$schedule = is_array( $payload['schedule'] ?? null ) ? $payload['schedule'] : array();
		$always   = ! is_array( $payload['schedule'] ?? null ) || ! empty( $schedule['alwaysAvailable'] );
		$sel      = is_array( $payload['productSelection'] ?? null ) ? $payload['productSelection'] : array();

		$start = (string) ( $schedule['startDate'] ?? '' );
		$end   = (string) ( $schedule['endDate'] ?? '' );

		// Never 0: the column is the only copy, so row_to_payload() needs something
		// to overlay. A typeless payload is a Fixed Bundle, as create() defaults it.
		$type = (int) ( $payload['discountType'] ?? 0 );
		if ( 0 === $type ) {
			$type = Bundle_Store::TYPE_FIXED_BUNDLES;
		}

		return array(
			'name'          => mb_substr( (string) ( $payload['name'] ?? '' ), 0, Bundles_Table::NAME_MAX ),
			'discount_type' => $type,
			'status'        => (int) ( $payload['status'] ?? 0 ),
			'schedule_mode' => $always ? 0 : 1,
			// Site wall-clock, exactly as normalize_schedule_date() wrote it.
			'start_date'    => '' !== $start ? $start : null,
			'end_date'      => '' !== $end ? $end : null,
			'target_mode'   => (int) ( $sel['mode'] ?? Bundle_Store::MODE_NONE ),
			'config'        => self::encode( $payload ),
		);
	}

	/**
	 * Build the list summary from a summary row.
	 *
	 * @param array $row Summary row.
	 * @return array
	 */
	private static function row_to_summary( array $row ) {
		return array(
			'id'            => (string) (int) ( $row['id'] ?? 0 ),
			'name'          => (string) ( $row['name'] ?? '' ),
			'discountType'  => (int) ( $row['discount_type'] ?? 0 ),
			'status'        => (int) ( $row['status'] ?? 0 ),
			'scheduleMode'  => (int) ( $row['schedule_mode'] ?? 0 ),
			'startDate'     => (string) ( $row['start_date'] ?? '' ),
			'endDate'       => (string) ( $row['end_date'] ?? '' ),
			'createdAt'     => self::iso( (string) ( $row['created_at'] ?? '' ) ),
			'updatedAt'     => self::iso( (string) ( $row['updated_at'] ?? '' ) ),
		);
	}

	/**
	 * Encode a payload for the `config` column.
	 *
	 * COLUMN_KEYS are dropped first, since row_to_payload() overlays them back on
	 * read. The table is utf8mb4, so JSON_UNESCAPED_UNICODE round-trips intact.
	 *
	 * @param array $payload Bundle payload.
	 * @return string
	 */
	public static function encode( array $payload ) {
		$payload = array_diff_key( $payload, array_flip( self::COLUMN_KEYS ) );
		return (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Decode a `config` blob back to the payload array.
	 *
	 * @param string $json Stored JSON.
	 * @return array|null
	 */
	private static function decode( $json ) {
		if ( '' === $json ) {
			return null;
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Current UTC time in MySQL DATETIME form.
	 *
	 * @return string
	 */
	private static function now_utc() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Format a UTC DATETIME back to the ISO-8601 string the REST payload carries.
	 *
	 * @param string $datetime MySQL DATETIME.
	 * @return string
	 */
	private static function iso( $datetime ) {
		$datetime = trim( (string) $datetime );
		if ( '' === $datetime || '0000-00-00 00:00:00' === $datetime ) {
			return '';
		}
		$ts = strtotime( $datetime . ' UTC' );
		return $ts ? gmdate( 'c', $ts ) : '';
	}

	/**
	 * Numeric-only id, matching Bundle_Store::clean_id().
	 *
	 * @param mixed $id Incoming bundle id.
	 * @return string
	 */
	private static function clean_id( $id ) {
		return (string) preg_replace( '/[^0-9]/', '', (string) $id );
	}
}
