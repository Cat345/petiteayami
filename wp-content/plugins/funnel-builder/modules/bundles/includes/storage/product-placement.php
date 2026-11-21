<?php
/**
 * Which bundles are placed on a product, kept on the product as an index built on first view.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps each product's placed bundle ids in one meta key, built lazily.
 *
 * The key holds -1 (checked, nothing placed) or the ids of every bundle placed
 * on the product by name or by category, any status, product exclusions
 * applied. An absent key is resolved with the targets query and written on
 * shutdown. Status and rank are applied at read time from a version-keyed map,
 * so only a change to what a bundle targets, or to the product, deletes keys;
 * nothing ever rewrites one.
 */
class Product_Placement {

	/** Product meta: -1, or the ids of the bundles placed on the product. */
	const META = '_wffn_bundles_idx';

	/** Stored once a product was checked and no bundle is placed on it. */
	const NONE = -1;

	/** Option: active bundles' rank for one data version. */
	const OPTION_MAP = 'wffn_bundles_placement_map';

	/** WooCommerce log source for the index's activity. */
	const LOG_SOURCE = 'wffn-bundles-product-indexer';

	/** Products per direct-SQL delete. */
	const BATCH = 500;

	/** Most product ids one log line lists. */
	const LOG_IDS = 50;

	/** @var array|null The map for this request. */
	private static $map = null;

	/** @var array<int,int[]> Each product's placed ids, read or built this request. */
	private static $cache = array();

	/** @var array<int,int[]> Lists built this request, written on shutdown. */
	private static $queue = array();

	/** @var int Data version the queued lists were built against. */
	private static $queued_version = 0;

	/**
	 * Drop a product's key whenever the product or its categories may have changed.
	 *
	 * @return void
	 */
	public static function boot() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;
		// A product save changes the list only through its categories, which the term hooks cover.
		add_action( 'woocommerce_product_duplicate', array( __CLASS__, 'forget' ) );
		add_filter( 'woocommerce_duplicate_product_exclude_meta', array( __CLASS__, 'exclude_from_copy' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'terms_changed' ), 10, 6 );
		// wp_remove_object_terms() fires only this, not set_object_terms.
		add_action( 'deleted_term_relationships', array( __CLASS__, 'terms_removed' ), 10, 3 );
	}

	/**
	 * Keep the key off a duplicated product, whose list is not the original's.
	 *
	 * @param string[] $keys Meta keys a duplicate leaves out.
	 * @return string[]
	 */
	public static function exclude_from_copy( $keys ) {
		$keys   = (array) $keys;
		$keys[] = self::META;

		return $keys;
	}

	/**
	 * Ordered candidate bundle ids for a product; null when the index cannot answer.
	 *
	 * The same superset the targets query returns: named or by category, less
	 * product exclusions, active only, in rank order. Only a product post is
	 * indexed; a variation or any other id is left to the query.
	 *
	 * @param int $product_id Product id.
	 * @return string[]|null
	 */
	public static function candidates( $product_id ) {
		$pid = absint( $product_id );
		if ( ! isset( self::$cache[ $pid ] ) ) {
			if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
				return null;
			}
			self::$cache[ $pid ] = self::read( $pid );
		}
		if ( ! self::$cache[ $pid ] ) {
			return array();
		}
		$map = self::map();
		if ( null === $map ) {
			return null;
		}

		$ids = array_values(
			array_filter(
				self::$cache[ $pid ],
				function ( $id ) use ( $map ) {
					return isset( $map['active'][ $id ] );
				}
			)
		);
		usort(
			$ids,
			function ( $a, $b ) use ( $map ) {
				return ( $map['active'][ $a ] <=> $map['active'][ $b ] ) ?: ( $a <=> $b );
			}
		);

		return array_map( 'strval', $ids );
	}

	/**
	 * Delete the keys of the products one bundle's new target rows affect.
	 *
	 * Products named or excluded on one side only, and products in a category
	 * targeted on one side only. Excluded categories are not part of the
	 * stored list, so they affect no key.
	 *
	 * @param int   $bundle_id Bundle id.
	 * @param array $was       The bundle's previous `[ target type, object id ]` rows.
	 * @param array $now       Its rows now; empty when it is deleted.
	 * @return void
	 */
	public static function sync( $bundle_id, array $was, array $now ) {
		self::$cache = array();
		self::$queue = array();

		$products = array_merge(
			self::changed( $was, $now, Bundles_Table::TARGET_PRODUCT ),
			self::changed( $was, $now, Bundles_Table::TARGET_EXCL_PRODUCT )
		);
		$forgot   = array_merge(
			self::delete_keys( self::with_key( $products ) ),
			self::delete_keys( self::with_key_in_categories( self::changed( $was, $now, Bundles_Table::TARGET_CATEGORY ) ) )
		);
		if ( $forgot ) {
			$forgot = array_values( array_unique( $forgot ) );
			self::log( 'info', sprintf( 'bundle=%d; forgotten %d product(s): %s', (int) $bundle_id, count( $forgot ), self::list_ids( $forgot ) ) );
		}
	}

	/**
	 * Drop one product's key, so its next view rebuilds it.
	 *
	 * @param int|\WC_Product $product Product id or object.
	 * @return void
	 */
	public static function forget( $product ) {
		$pid = $product instanceof \WC_Product ? $product->get_id() : absint( $product );
		if ( ! $pid ) {
			return;
		}
		unset( self::$cache[ $pid ], self::$queue[ $pid ] );
		if ( '' === get_post_meta( $pid, self::META, true ) ) {
			return;
		}
		delete_post_meta( $pid, self::META );
		self::log( 'info', sprintf( 'product=%d; forgotten on %s', $pid, current_action() ? current_action() : 'request' ) );
	}

	/**
	 * Forget a product whose categories changed.
	 *
	 * @param int    $object_id  Object id.
	 * @param array  $terms      Terms passed in.
	 * @param array  $tt_ids     Term taxonomy ids set.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Whether the terms were appended.
	 * @param array  $old_tt_ids Term taxonomy ids before.
	 * @return void
	 */
	public static function terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( 'product_cat' !== $taxonomy ) {
			return;
		}
		$new = array_map( 'intval', (array) $tt_ids );
		$old = array_map( 'intval', (array) $old_tt_ids );
		self::categories_changed( $object_id, array_merge( array_diff( $new, $old ), $append ? array() : array_diff( $old, $new ) ) );
	}

	/**
	 * Forget a product taken out of categories without set_object_terms.
	 *
	 * @param int    $object_id Object id.
	 * @param array  $tt_ids    Term taxonomy ids removed.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function terms_removed( $object_id, $tt_ids, $taxonomy ) {
		if ( 'product_cat' === $taxonomy ) {
			self::categories_changed( $object_id, (array) $tt_ids );
		}
	}

	/**
	 * Forget a product when a category it gained or lost is one some bundle targets.
	 *
	 * @param int   $object_id Product id.
	 * @param int[] $tt_ids    Term taxonomy ids added or removed.
	 * @return void
	 */
	private static function categories_changed( $object_id, array $tt_ids ) {
		$tt_ids = array_filter( array_map( 'absint', $tt_ids ) );
		$map    = $tt_ids ? self::map() : null;
		if ( empty( $map['categories'] ) ) {
			return;
		}
		foreach ( $tt_ids as $tt_id ) {
			$term = get_term_by( 'term_taxonomy_id', $tt_id, 'product_cat' );
			if ( $term && isset( $map['categories'][ (int) $term->term_id ] ) ) {
				self::forget( $object_id );
				return;
			}
		}
	}

	/**
	 * Drop every product's key; each rebuilds on its next view.
	 *
	 * @return void
	 */
	public static function forget_all() {
		self::$cache = array();
		self::$queue = array();
		delete_metadata( 'post', 0, self::META, '', true );
		self::log( 'info', 'every product index forgotten' );
	}

	/**
	 * Write the lists built this request; a failure is logged, never shown.
	 *
	 * @return void
	 */
	public static function flush() {
		$queue       = self::$queue;
		self::$queue = array();
		if ( ! $queue ) {
			return;
		}
		global $wpdb;
		// A database error here would print after the page with WP_DEBUG_DISPLAY on.
		$quiet = $wpdb->suppress_errors( true );
		try {
			// A bundle saved meanwhile may have deleted the very keys these would restore.
			if ( self::stored_version() !== self::$queued_version ) {
				self::log( 'debug', sprintf( 'skipped %d product(s): bundle data changed during the request', count( $queue ) ) );
				return;
			}
			foreach ( $queue as $pid => $ids ) {
				update_post_meta( $pid, self::META, $ids ? $ids : self::NONE );
				if ( '' !== $wpdb->last_error ) {
					self::log( 'error', sprintf( 'product=%d; index write failed: %s', $pid, $wpdb->last_error ) );
					continue;
				}
				self::log( 'debug', sprintf( 'product=%d; indexed %s', $pid, $ids ? '[' . implode( ',', $ids ) . ']' : self::NONE ) );
			}
		} catch ( \Throwable $e ) {
			self::log( 'error', 'flush failed: ' . $e->getMessage() );
		} finally {
			$wpdb->suppress_errors( $quiet );
		}
	}

	/**
	 * Write one line to the index's log source.
	 *
	 * @param string $level   PSR-3 level.
	 * @param string $message Message.
	 * @return void
	 */
	public static function log( $level, $message ) {
		try {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
			}
		} catch ( \Throwable $e ) {
			// Logging must never break the request that triggered it.
			unset( $e );
		}
	}

	/**
	 * A product's stored list, or the targets query's answer queued for writing.
	 *
	 * @param int $pid Product id.
	 * @return int[]
	 */
	private static function read( $pid ) {
		$stored = get_post_meta( $pid, self::META, true );
		if ( is_array( $stored ) ) {
			return self::ids( $stored );
		}
		if ( '' !== $stored && self::NONE === (int) $stored ) {
			return array();
		}

		$ids = array_map( 'intval', Bundle_Repository::candidate_ids_for_product( $pid, true, true ) );
		if ( ! self::$queue ) {
			self::$queued_version = Bundle_Repository::version();
			if ( false === has_action( 'shutdown', array( __CLASS__, 'flush' ) ) ) {
				add_action( 'shutdown', array( __CLASS__, 'flush' ) );
			}
		}
		self::$queue[ $pid ] = $ids;

		return $ids;
	}

	/**
	 * Active bundles' rank, and every category any bundle targets, for the current data version.
	 *
	 * @return array|null `[ 'v' => version, 'active' => [ id => menu_order ], 'categories' => [ term id => true ] ]`.
	 */
	private static function map() {
		$version = Bundle_Repository::version();
		if ( null !== self::$map && self::$map['v'] === $version ) {
			return self::$map;
		}
		$stored = get_option( self::OPTION_MAP );
		if ( is_array( $stored ) && (int) ( $stored['v'] ?? 0 ) === $version && isset( $stored['active'], $stored['categories'] ) ) {
			self::$map = $stored;
			return self::$map;
		}
		if ( ! Bundles_Table::exists() ) {
			return null;
		}

		global $wpdb;
		$bundles = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, menu_order FROM %i WHERE status = 1', Bundles_Table::table_name() ), ARRAY_A );
		// Every status: a key holds inactive bundles too, so their categories matter.
		$cats = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT object_id FROM %i WHERE target_type = %d', Bundles_Table::targets_table_name(), Bundles_Table::TARGET_CATEGORY ) );

		$map = array(
			'v'          => $version,
			'active'     => array(),
			'categories' => array_fill_keys( array_map( 'intval', $cats ), true ),
		);
		foreach ( $bundles as $row ) {
			$map['active'][ (int) $row['id'] ] = (int) $row['menu_order'];
		}
		update_option( self::OPTION_MAP, $map, true );
		self::$map = $map;

		return $map;
	}

	/**
	 * The bundle data version as stored now, past this request's option cache.
	 *
	 * @return int
	 */
	private static function stored_version() {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, Bundle_Repository::OPTION_VERSION ) );

		return null === $value ? 1 : (int) $value;
	}

	/**
	 * Of these products, the ones holding a key.
	 *
	 * @param int[] $ids Product ids.
	 * @return int[]
	 */
	private static function with_key( array $ids ) {
		global $wpdb;
		$out = array();
		foreach ( array_chunk( $ids, self::BATCH ) as $chunk ) {
			$out = array_merge(
				$out,
				(array) $wpdb->get_col(
					$wpdb->prepare(
						'SELECT post_id FROM %i WHERE meta_key = %s AND post_id IN (' . implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
						array_merge( array( $wpdb->postmeta, self::META ), $chunk )
					)
				)
			);
		}

		return array_map( 'intval', $out );
	}

	/**
	 * Products holding a key that sit directly in any of these categories.
	 *
	 * @param int[] $term_ids product_cat term ids.
	 * @return int[]
	 */
	private static function with_key_in_categories( array $term_ids ) {
		if ( ! $term_ids ) {
			return array();
		}
		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT pm.post_id
				FROM %i pm
				INNER JOIN %i tr ON tr.object_id = pm.post_id
				INNER JOIN %i tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE pm.meta_key = %s AND tt.taxonomy = %s AND tt.term_id IN (' . implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) . ')',
				array_merge( array( $wpdb->postmeta, $wpdb->term_relationships, $wpdb->term_taxonomy, self::META, 'product_cat' ), $term_ids )
			)
		);

		return array_map( 'intval', (array) $rows );
	}

	/**
	 * Delete these products' keys in batches.
	 *
	 * @param int[] $ids Product ids.
	 * @return int[] The ids passed.
	 */
	private static function delete_keys( array $ids ) {
		global $wpdb;
		foreach ( array_chunk( $ids, self::BATCH ) as $chunk ) {
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE meta_key = %s AND post_id IN (' . implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					array_merge( array( $wpdb->postmeta, self::META ), $chunk )
				)
			);
			// Direct SQL bypasses the meta API, so the object cache would keep serving the old value.
			foreach ( $chunk as $pid ) {
				wp_cache_delete( $pid, 'post_meta' );
			}
		}

		return $ids;
	}

	/**
	 * Object ids of one target type present on exactly one side.
	 *
	 * @param array $was  Previous `[ target type, object id ]` rows.
	 * @param array $now  Rows now.
	 * @param int   $type Target type.
	 * @return int[]
	 */
	private static function changed( array $was, array $now, $type ) {
		$before = self::objects( $was, $type );
		$after  = self::objects( $now, $type );

		return array_values( array_merge( array_diff( $before, $after ), array_diff( $after, $before ) ) );
	}

	/**
	 * The object ids of one target type among `[ target type, object id ]` rows.
	 *
	 * @param array $rows Rows.
	 * @param int   $type Target type.
	 * @return int[]
	 */
	private static function objects( array $rows, $type ) {
		$ids = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && (int) ( $row[0] ?? 0 ) === (int) $type && absint( $row[1] ?? 0 ) ) {
				$ids[] = absint( $row[1] );
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * A stored id list as ints.
	 *
	 * @param mixed $value Meta value.
	 * @return int[]
	 */
	private static function ids( $value ) {
		return is_array( $value ) ? array_values( array_filter( array_map( 'intval', $value ) ) ) : array();
	}

	/**
	 * Product ids for a log line, shortened past LOG_IDS.
	 *
	 * @param int[] $ids Product ids.
	 * @return string
	 */
	private static function list_ids( array $ids ) {
		$more = count( $ids ) - self::LOG_IDS;

		return implode( ',', array_slice( $ids, 0, self::LOG_IDS ) ) . ( $more > 0 ? sprintf( ' and %d more', $more ) : '' );
	}
}
