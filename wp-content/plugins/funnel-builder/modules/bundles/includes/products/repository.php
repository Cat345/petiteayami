<?php
/** Product Repository — the single source for product data. */
namespace WFFN\Bundles\Includes\Products;

use WFFN\Bundles\Includes\Helpers\WooCommerce;
use WFFN\Bundles\Includes\Compat\Currency\Layer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single source for product data shaped for the admin app and storefront.
 */
class Repository {

	/** Default page size when the caller doesn't ask for one. */
	const DEFAULT_PER_PAGE = 20;

	/** Hard ceiling on one page, however large a per_page the caller sends. */
	const MAX_PER_PAGE = 100;

	/** Most whitespace-separated words honoured in one search term. */
	const MAX_SEARCH_WORDS = 10;

	/** How long a shaped product row may sit in the cache before it is rebuilt. */
	const SHAPE_CACHE_TTL = DAY_IN_SECONDS;

	/** WooCommerce's own cache group for a product's raw meta rows (WC_Product::$cache_group). */
	const WC_META_GROUP = 'products';

	/**
	 * Post meta holding a product's shaped row: `{ salt, sig, data }`.
	 *
	 * The shape lives on the product it describes rather than in an option of its own,
	 * because every caller has already read that product's meta in the batch
	 * prime_meta() runs. Read from there it costs nothing; as a transient it cost one
	 * option read per request that drew a bundle. `salt` stands in for what the old
	 * transient name carried, so a moved site or an upgraded plugin does not read a
	 * stale row, and `sig` is the same product fingerprint as before.
	 */
	const SHAPE_META_KEY = '_wffn_bundles_shape';

	/**
	 * Which fields product_signature() hashes.
	 *
	 * Part of shape_salt(), so changing the basis retires every row hashed on the
	 * old one instead of leaving rows that can never match.
	 */
	const SIGNATURE_BASIS = 3;

	/** Term held only for the duration of a single wc_get_products() call. */
	private static $search_term = '';

	/** Product objects already built this request, keyed by id. */
	private static $objects = array();

	/** Shaped product rows already built by find() this request, keyed by id. */
	private static $shaped = array();

	/** Product signatures, memoized per request; filled in bulk by prime(). */
	private static $sigs = array();

	/**
	 * A product object, built at most once per request.
	 *
	 * wc_get_product() constructs a fresh object every call, and currency or
	 * dynamic-pricing plugins hang filters off that construction. Private on
	 * purpose: callers get shaped arrays, never a shared mutable product.
	 *
	 * @param int $id Product id.
	 * @return \WC_Product|false
	 */
	private static function product( $id ) {
		$id = absint( $id );
		if ( ! array_key_exists( $id, self::$objects ) ) {
			self::boot_cache();
			self::$objects[ $id ] = wc_get_product( $id );
		}
		return self::$objects[ $id ];
	}

	/**
	 * Drop the per-request product and shape caches.
	 *
	 * @internal Public only so it can be hooked; not part of the API.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$objects = array();
		self::$shaped  = array();
		self::$sigs    = array();
	}

	/**
	 * Register cache invalidation once, the first time anything is cached.
	 *
	 * @return void
	 */
	private static function boot_cache() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;
		// Each passes an id or the object, both of which purge_product() accepts.
		$hooks = array(
			'clean_post_cache'                       => 2,
			'woocommerce_update_product'             => 1,
			'woocommerce_new_product'                => 1,
			'woocommerce_update_product_variation'   => 1,
			'woocommerce_new_product_variation'      => 1,
			'woocommerce_product_set_stock'          => 1,
			'woocommerce_variation_set_stock'        => 1,
			'woocommerce_delete_product_transients'  => 1,
		);
		foreach ( $hooks as $hook => $args ) {
			add_action( $hook, array( __CLASS__, 'purge_product' ), 10, $args );
		}
	}

	/**
	 * Warm only the post, term and meta caches for a set of product ids, in one read each.
	 *
	 * For loops that load each product in turn; prime() does more and costs more.
	 *
	 * @param int[] $ids Product ids.
	 * @return void
	 */
	public static function prime_posts( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( count( $ids ) > 1 ) {
			_prime_post_caches( $ids, true, false );
			self::prime_meta( $ids );
		}
	}

	/**
	 * Fill WordPress's post-meta cache and WooCommerce's raw-meta cache from one read.
	 *
	 * _prime_post_caches() fills WordPress's cache only. WooCommerce keeps its own copy of
	 * the raw rows, meta_id included, and WC_Data::read_meta_data() reads that copy before
	 * it queries, so without it the first wc_get_product() on each id pays
	 * WC_Data_Store_WP::read_meta() again: one query per product, after the batch already
	 * had the rows. The same rows answer both caches, so this reads them once and primes
	 * each in the shape it expects. WooCommerce primes its order meta the same way
	 * (WC_Data::prime_raw_meta_data_cache), so the contract is a supported one.
	 *
	 * Ids already in WordPress's cache are left alone: their WooCommerce entry is primed
	 * from the cached rows where it can be, and otherwise left for WooCommerce to read.
	 *
	 * @param int[] $ids Product ids.
	 * @return void
	 */
	public static function prime_meta( array $ids ) {
		global $wpdb;

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids || ! class_exists( 'WC_Product' ) || ! is_callable( array( 'WC_Product', 'prime_raw_meta_data_cache' ) ) ) {
			return;
		}

		// Only ids WooCommerce has not already cached are worth a read.
		$wanted = array();
		foreach ( $ids as $id ) {
			$key = \WC_Product::generate_meta_cache_key( $id, self::WC_META_GROUP );
			if ( false === wp_cache_get( $key, self::WC_META_GROUP ) ) {
				$wanted[] = $id;
			}
		}
		if ( ! $wanted ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this read is what fills the caches below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, meta_id, meta_key, meta_value FROM %i WHERE post_id IN (' . implode( ', ', array_fill( 0, count( $wanted ), '%d' ) ) . ') ORDER BY meta_id ASC',
				array_merge( array( $wpdb->postmeta ), $wanted )
			)
		);
		// phpcs:enable

		$wp_cache = array_fill_keys( $wanted, array() );
		$wc_cache = array_fill_keys( $wanted, array() );
		foreach ( (array) $rows as $row ) {
			$id = (int) $row->post_id;
			if ( ! isset( $wp_cache[ $id ] ) ) {
				continue;
			}
			// WordPress keeps raw values grouped by key; get_post_meta() unserializes on the way out.
			$wp_cache[ $id ][ $row->meta_key ][] = $row->meta_value;
			// WooCommerce keeps the rows as they came, meta_id included, and filters them itself.
			$wc_cache[ $id ][] = (object) array(
				'meta_id'    => $row->meta_id,
				'meta_key'   => $row->meta_key,
				'meta_value' => $row->meta_value,
			);
		}

		foreach ( $wp_cache as $id => $meta ) {
			if ( false === wp_cache_get( $id, 'post_meta' ) ) {
				wp_cache_set( $id, $meta, 'post_meta' );
			}
		}
		\WC_Product::prime_raw_meta_data_cache( $wc_cache, self::WC_META_GROUP );
	}

	/**
	 * Warm the post / term / meta caches for a set of product ids in one go.
	 *
	 * find() is a single-product call, so hydrating a list one id at a time costs
	 * a post read and a term query per product. Safe to call with ids already in
	 * cache. Variation children are primed too, since shape() expands them.
	 *
	 * @param int[] $ids     Product ids.
	 * @param int[] $lookups Ids find() is about to read, primed for its cache check only.
	 * @return void
	 */
	public static function prime( array $ids, array $lookups = array() ) {
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$lookups = array_values( array_unique( array_merge( $ids, array_filter( array_map( 'absint', $lookups ) ) ) ) );
		if ( empty( $lookups ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		if ( empty( $ids ) ) {
			self::prime_signatures( $lookups );
			return;
		}

		// Posts, terms and meta first, in two batches. A stored shape that still
		// matches spares the shaping work, but not these reads: the bundle's own
		// display checks build the products either way, and one batch for the set
		// is cheaper than one read per product. Filling them first is also what
		// lets prime_signatures() answer from cache instead of querying.
		_prime_post_caches( $ids, true, false );
		// The shapes ride each product's own meta, so this one batch answers them all.
		self::prime_meta( $ids );

		// A stored shape is only trusted against the current signature.
		self::prime_signatures( $lookups );

		foreach ( $ids as $id ) {
			self::warm_shape( $id );
		}

		$children = array();
		foreach ( $ids as $id ) {
			$product = self::product( $id );
			if ( $product instanceof \WC_Product_Variable ) {
				foreach ( $product->get_children() as $child_id ) {
					$children[] = absint( $child_id );
				}
			}
		}

		$children = array_values( array_unique( array_filter( $children ) ) );
		if ( $children ) {
			// Term cache included, or the factory issues a term query per variation.
			_prime_post_caches( $children, true, false );
			self::prime_meta( $children );
			self::prime_product_terms( $children );
		}
	}

	/**
	 * Drop every stored shape; each rebuilds on its next view.
	 *
	 * The signature check already refuses a stale row, so this is for the moments
	 * when the rows should not be left sitting in the table at all: the plugin
	 * going away, coming back after a spell switched off, or a version whose shaped
	 * rows mean something different.
	 *
	 * @return void
	 */
	public static function forget_all_shapes() {
		self::$shaped = array();
		delete_metadata( 'post', 0, self::SHAPE_META_KEY, '', true );
	}

	/**
	 * The stored shape for a product when it is still current, memoised for find().
	 *
	 * Reads the product's own meta, which prime_meta() has already batched, so a page
	 * whose shapes are all current never builds a WC_Product at all.
	 *
	 * @param int $id Product id.
	 * @return array|null The shaped row, or null when there is nothing usable stored.
	 */
	private static function warm_shape( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		if ( array_key_exists( $id, self::$shaped ) ) {
			return self::$shaped[ $id ];
		}
		if ( ! self::shape_is_cacheable( $id ) ) {
			return null;
		}
		// The purge hooks have to be on before a stored shape is trusted.
		self::boot_cache();
		$cached = get_post_meta( $id, self::SHAPE_META_KEY, true );
		if ( ! is_array( $cached ) || ! isset( $cached['salt'], $cached['sig'], $cached['data'] ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $cached['salt'], self::shape_salt() ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $cached['sig'], self::product_signature( $id ) ) ) {
			return null;
		}
		self::$shaped[ $id ] = self::localize_prices( (array) $cached['data'] );

		return self::$shaped[ $id ];
	}

	/**
	 * Fill the signature memo for a set of ids, from cache where the caches hold it.
	 *
	 * product_signature() is the cache check every find() pays before it can trust a
	 * stored row, so it runs on every bundle view. The pieces it is built from are the
	 * same pieces prime() has just batched into the post and meta caches, so for an id
	 * already primed the signature costs nothing. Ids the caches cannot answer for —
	 * a variable product, or one nothing has loaded — fall through to one query that
	 * reads the identical fields, so a hash never depends on which path produced it.
	 *
	 * @param int[] $ids Product ids.
	 * @return void
	 */
	private static function prime_signatures( array $ids ) {
		// wp_parse_id_list() is the escaping step: the ids reach the IN() list as ints.
		$ids = array_values( array_filter( wp_parse_id_list( $ids ), fn( $id ) => ! isset( self::$sigs[ $id ] ) ) );
		if ( ! $ids ) {
			return;
		}

		$cold = array();
		foreach ( $ids as $id ) {
			$warm = self::signature_from_cache( $id );
			if ( null === $warm ) {
				$cold[] = $id;
				continue;
			}
			self::$sigs[ $id ] = $warm;
		}

		if ( $cold ) {
			self::signatures_from_db( $cold );
		}
	}

	/**
	 * The fields a signature is made of, for one post, read from the caches only.
	 *
	 * Null means the caches cannot answer, never that the post has no values: the
	 * caller falls back to the query rather than hash a guess.
	 *
	 * @param int $id Post id.
	 * @return array|null
	 */
	private static function signature_tuple_from_cache( $id ) {
		// update_post_cache() stores the row as it came back, not a WP_Post.
		$post = wp_cache_get( $id, 'posts' );
		if ( ! is_object( $post ) || ! isset( $post->post_status, $post->post_modified_gmt ) ) {
			return null;
		}
		$meta = wp_cache_get( $id, 'post_meta' );
		if ( false === $meta ) {
			return null;
		}

		return array(
			(int) $id,
			(string) $post->post_status,
			(string) $post->post_modified_gmt,
			self::meta_token( $meta, '_price' ),
			self::meta_token( $meta, '_stock_status' ),
			self::meta_token( $meta, '_stock' ),
		);
	}

	/**
	 * One meta key's values as a single comparable string.
	 *
	 * Sorted, because the cache and the query can hand the same rows back in a
	 * different order and the hash must not notice.
	 *
	 * @param array  $meta Cached meta, key => values.
	 * @param string $key  Meta key.
	 * @return string
	 */
	private static function meta_token( $meta, $key ) {
		if ( ! isset( $meta[ $key ] ) ) {
			return '';
		}
		$values = array_map( 'strval', (array) $meta[ $key ] );
		sort( $values );

		return implode( ',', $values );
	}

	/**
	 * A product's signature from the caches, or null when they cannot answer.
	 *
	 * A variable product's variations are part of what its shaped row is derived
	 * from, and the caches hold no list of them, so it always takes the query.
	 *
	 * @param int $id Product id.
	 * @return string|null
	 */
	private static function signature_from_cache( $id ) {
		$tuple = self::signature_tuple_from_cache( $id );
		if ( null === $tuple ) {
			return null;
		}

		// get_the_terms() would query on a cold relationship cache; only read a warm one.
		if ( false === wp_cache_get( $id, 'product_type_relationships' ) ) {
			return null;
		}
		$terms = get_the_terms( $id, 'product_type' );
		if ( is_wp_error( $terms ) ) {
			return null;
		}
		foreach ( (array) $terms as $term ) {
			if ( isset( $term->slug ) && 'variable' === $term->slug ) {
				return null;
			}
		}

		return md5( (string) wp_json_encode( array( $tuple ) ) );
	}

	/**
	 * Fill the signature memo for ids the caches could not answer for, in one query.
	 *
	 * Reads the same fields signature_tuple_from_cache() reads, in the same shape, so
	 * the two paths agree byte for byte. A product's variations ride its hash, which
	 * is what `post_parent` is doing in the WHERE.
	 *
	 * @param int[] $ids Product ids.
	 * @return void
	 */
	private static function signatures_from_db( array $ids ) {
		global $wpdb;

		$ids = wp_parse_id_list( $ids );
		if ( ! $ids ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this read is the cache check itself.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.ID, p.post_status, p.post_modified_gmt, p.post_parent, m.meta_key, m.meta_value
				FROM %i p
				LEFT JOIN %i m ON m.post_id = p.ID AND m.meta_key IN ( %s, %s, %s )
				WHERE p.ID IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')
				OR p.post_parent IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')
				ORDER BY p.ID ASC',
				array_merge(
					array( $wpdb->posts, $wpdb->postmeta, '_price', '_stock_status', '_stock' ),
					$ids,
					$ids
				)
			)
		);
		// phpcs:enable

		// Fold the joined rows back into one record per post.
		$posts = array();
		foreach ( (array) $rows as $row ) {
			$own = (int) $row->ID;
			if ( ! isset( $posts[ $own ] ) ) {
				$posts[ $own ] = array(
					'status' => (string) $row->post_status,
					'mod'    => (string) $row->post_modified_gmt,
					'parent' => (int) $row->post_parent,
					'meta'   => array(),
				);
			}
			if ( null !== $row->meta_key ) {
				$posts[ $own ]['meta'][ (string) $row->meta_key ][] = (string) $row->meta_value;
			}
		}

		$by_id = array_fill_keys( $ids, array() );
		foreach ( $posts as $own => $rec ) {
			$tuple = array(
				(int) $own,
				$rec['status'],
				$rec['mod'],
				self::meta_token( $rec['meta'], '_price' ),
				self::meta_token( $rec['meta'], '_stock_status' ),
				self::meta_token( $rec['meta'], '_stock' ),
			);
			if ( isset( $by_id[ $own ] ) ) {
				$by_id[ $own ][] = $tuple;
			}
			if ( $rec['parent'] && isset( $by_id[ $rec['parent'] ] ) ) {
				$by_id[ $rec['parent'] ][] = $tuple;
			}
		}

		foreach ( $by_id as $id => $tuples ) {
			self::$sigs[ $id ] = md5( (string) wp_json_encode( $tuples ) );
		}
	}

	/**
	 * Prime the `product` taxonomies against a set of variation ids.
	 *
	 * _prime_post_caches() only primes taxonomies registered for the ids' own post
	 * type, so a variation never gets `product_type` cached and plugins reading it
	 * issue one term query each.
	 *
	 * @param int[] $ids Variation ids.
	 * @return void
	 */
	private static function prime_product_terms( array $ids ) {
		if ( $ids && function_exists( 'update_object_term_cache' ) ) {
			update_object_term_cache( $ids, 'product' );
		}
	}

	/**
	 * Display titles for a set of product ids, in one query.
	 *
	 * prime() is the wrong tool for a name-only read: it hydrates every product and
	 * walks variable children. Ids with no post are omitted.
	 *
	 * @param int[] $ids Product ids.
	 * @return array<int,string> Product id => title.
	 */
	public static function titles( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		_prime_post_caches( $ids, false, false );

		$titles = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$titles[ $id ] = get_the_title( $post );
			}
		}

		return $titles;
	}

	/**
	 * Hydrate one product id into the admin-app product shape, or null.
	 *
	 * Memoized: a member that is also the page product would otherwise be shaped
	 * twice, and on a variable product that is the whole variation walk twice.
	 *
	 * @param int $id Product id.
	 * @return array|null
	 */
	public static function find( $id ) {
		$id = absint( $id );
		if ( ! $id || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		if ( array_key_exists( $id, self::$shaped ) ) {
			return self::$shaped[ $id ];
		}
		self::boot_cache();

		$cacheable = self::shape_is_cacheable( $id );
		$sig       = null;
		if ( $cacheable ) {
			$warm = self::warm_shape( $id );
			if ( null !== $warm ) {
				return $warm;
			}
			$sig = self::product_signature( $id );
		}

		$product = self::product( $id );
		if ( ! $product || ! $product->exists() ) {
			self::$shaped[ $id ] = null;
			return null;
		}
		// Through the batch shaper, so children and image attachments prime in bulk.
		$items               = self::shape_all( array( $product ), true );
		self::$shaped[ $id ] = $items ? $items[0] : null;

		if ( $cacheable && null !== self::$shaped[ $id ] ) {
			update_post_meta(
				$id,
				self::SHAPE_META_KEY,
				array(
					'salt' => self::shape_salt(),
					'sig'  => $sig,
					'data' => self::$shaped[ $id ],
				)
			);
		}
		return self::$shaped[ $id ];
	}

	/**
	 * Cache slot for a product's shaped row, or null when it must not be cached.
	 *
	 * One deterministic slot per product, so purge_product() can delete it by name.
	 * Nothing currency-specific is stored; see localize_prices(). Anyone who can
	 * edit products is served fresh: variation_buyable() answers differently for
	 * them on unpublished variations.
	 *
	 * @param int $id Product id.
	 * @return string|null
	 */
	private static function shape_is_cacheable( $id ) {
		/**
		 * Filters whether shaped product rows are cached between requests.
		 *
		 * @since 1.0.0
		 * @param bool $enabled Whether to use the cache.
		 * @param int  $id      Product id being shaped.
		 */
		if ( ! apply_filters( 'wffn_bundles_cache_shaped_products', true, $id ) ) {
			return false;
		}
		if ( current_user_can( 'edit_products' ) ) {
			return false;
		}
		// The row holds one currency's base price, so a converted view never shares it.
		if ( Layer::is_active() && Layer::get_current_currency() !== Layer::get_base_currency() ) {
			return false;
		}
		return true;
	}

	/**
	 * What a stored shape has to agree with to be read.
	 *
	 * A shape holds absolute URLs, so a moved site or an upgraded plugin must not read
	 * an old one. The old transient carried this in its name; the meta row carries it
	 * as a field.
	 *
	 * @return string
	 */
	private static function shape_salt() {
		return md5( WFFN_BUNDLES_VERSION . '|' . WFFN_BUNDLES_URL . '|' . self::SIGNATURE_BASIS );
	}

	/**
	 * A fingerprint of everything a shaped row is derived from.
	 *
	 * Post status, modification time, price and stock, for the product and for any
	 * variations it has. prime_signatures() answers from the caches prime() has just
	 * filled where it can, and queries once for the rest.
	 *
	 * The backstop, not the primary guard: post_modified_gmt has only second
	 * granularity. purge_product() is what makes an edit take effect immediately.
	 *
	 * @param int $id Product id.
	 * @return string
	 */
	private static function product_signature( $id ) {
		$id = absint( $id );
		if ( ! isset( self::$sigs[ $id ] ) ) {
			self::prime_signatures( array( $id ) );
		}

		return isset( self::$sigs[ $id ] ) ? self::$sigs[ $id ] : '';
	}

	/**
	 * Re-format a cached row's prices for the currency this request is showing.
	 *
	 * Only the formatted strings are rebuilt, from `priceRaw`, which is always the
	 * base amount. An empty `price` means no price at all, not a price of zero.
	 *
	 * @param array $row Cached shaped row.
	 * @return array
	 */
	private static function localize_prices( array $row ) {
		if ( isset( $row['price'] ) && '' !== $row['price'] ) {
			$row['price'] = self::price( $row['priceRaw'] ?? 0.0 );
		}
		if ( ! empty( $row['variations'] ) && is_array( $row['variations'] ) ) {
			foreach ( $row['variations'] as $i => $variation ) {
				if ( isset( $variation['price'] ) && '' !== $variation['price'] ) {
					$row['variations'][ $i ]['price'] = self::price( $variation['priceRaw'] ?? 0.0 );
				}
			}
		}
		return $row;
	}

	/**
	 * Drop a product's cached row — and its parent's, for a variation.
	 *
	 * @internal Public only so it can be hooked; not part of the API.
	 *
	 * @param int|\WC_Product|\WP_Post $product Product, post or id that changed.
	 * @param \WP_Post|null            $post    Post object, when the hook supplies one.
	 * @return void
	 */
	public static function purge_product( $product = 0, $post = null ) {
		$id = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : absint( $product );
		if ( ! $id ) {
			return;
		}
		self::flush_cache();

		$parent = 0;
		if ( $post instanceof \WP_Post ) {
			if ( ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
				return;
			}
			$parent = (int) $post->post_parent;
		} elseif ( $product instanceof \WC_Product ) {
			$parent = (int) $product->get_parent_id();
		} else {
			$parent = (int) wp_get_post_parent_id( $id );
		}

		// The signature check already refuses a stale row; this keeps the table tidy.
		delete_post_meta( $id, self::SHAPE_META_KEY );
		delete_transient( self::shape_slot( $id ) );
		if ( $parent ) {
			delete_post_meta( $parent, self::SHAPE_META_KEY );
			delete_transient( self::shape_slot( $parent ) );
		}
	}

	/**
	 * The slot name for a product id, independent of who is asking.
	 *
	 * @param int $id Product id.
	 * @return string
	 */
	private static function shape_slot( $id ) {
		// A shape holds absolute URLs, so a moved site or plugin folder must not read an old one.
		return 'wffn_bundles_shape_' . absint( $id ) . '_' . md5( WFFN_BUNDLES_VERSION . '|' . WFFN_BUNDLES_URL );
	}

	/**
	 * Clamp a requested page size into the range this repository will serve.
	 *
	 * @param int $per_page Requested page size.
	 * @return int
	 */
	public static function clamp_per_page( $per_page ) {
		$per_page = absint( $per_page );
		if ( $per_page < 1 ) {
			return self::DEFAULT_PER_PAGE;
		}
		return min( $per_page, self::MAX_PER_PAGE );
	}

	/**
	 * Search products, returning rows in the admin-app product shape.
	 *
	 * @param array $args search, category, per_page, page, include_variations.
	 * @return array
	 */
	public static function search( array $args = array() ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$search   = trim( sanitize_text_field( (string) ( $args['search'] ?? '' ) ) );
		$category = sanitize_text_field( (string) ( $args['category'] ?? '' ) );

		// Inlined by default for the offer picker; list-only pickers pass false.
		$with_variations = ! isset( $args['include_variations'] ) || (bool) $args['include_variations'];

		$query = array(
			'status'  => 'publish',
			'limit'   => self::clamp_per_page( $args['per_page'] ?? 0 ),
			// Not absint(): that would turn page -3 into page 3 and answer a
			// malformed request with an arbitrary slice of the catalogue.
			'page'    => max( 1, (int) ( $args['page'] ?? 1 ) ),
			'orderby' => 'title',
			'order'   => 'ASC',
		);

		// The hidden placeholder a pack is charged on is not something to sell or place a bundle on.
		$container = (int) get_option( \WFFN\Bundles\Includes\Storage\Install::OPTION_CONTAINER_ID, 0 );
		if ( $container > 0 ) {
			$query['exclude'] = array( $container );
		}

		if ( '' !== $category && 'All Categories' !== $category ) {
			// Comma-separated is an OR: "products in any of these categories".
			$slugs = array();
			foreach ( explode( ',', $category ) as $ref ) {
				$ref = trim( $ref );
				if ( '' === $ref ) {
					continue;
				}
				$term = self::resolve_category( $ref );
				if ( $term ) {
					$slugs[] = $term->slug;
				}
			}
			// A filter that resolves to nothing matches nothing.
			if ( ! $slugs ) {
				return array();
			}
			$query['category'] = array_values( array_unique( $slugs ) );
		}

		// Deliberately no 's': WP would LIKE post_content and post_excerpt too.
		// filter_search_clause() narrows the match to title, SKU and id.
		if ( '' !== $search ) {
			self::$search_term = $search;
			add_filter( 'posts_search', array( __CLASS__, 'filter_search_clause' ), 10, 2 );
			add_filter( 'posts_join', array( __CLASS__, 'filter_search_join' ), 10, 2 );
		}

		try {
			$products = wc_get_products( $query );
		} finally {
			if ( '' !== $search ) {
				remove_filter( 'posts_search', array( __CLASS__, 'filter_search_clause' ), 10 );
				remove_filter( 'posts_join', array( __CLASS__, 'filter_search_join' ), 10 );
				self::$search_term = '';
			}
		}

		return self::shape_all( $products, $with_variations );
	}

	/**
	 * Resolve a category reference to its term.
	 *
	 * @param string|int $value Term id, slug or display name.
	 * @return \WP_Term|null
	 */
	private static function resolve_category( $value ) {
		if ( is_numeric( $value ) ) {
			$term = get_term( absint( $value ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
		$term = get_term_by( 'slug', $value, 'product_cat' );
		if ( ! $term ) {
			$term = get_term_by( 'name', $value, 'product_cat' );
		}
		return $term ?: null;
	}

	/**
	 * Replace WP's search WHERE with a title/SKU/id match.
	 *
	 * Every word must match something, so "blue shirt" still finds "Shirt - Blue".
	 *
	 * @internal Public only so it can be (un)hooked; not part of the API.
	 *
	 * @param string    $search Existing search clause.
	 * @param \WP_Query $query  Query being filtered.
	 * @return string
	 */
	public static function filter_search_clause( $search, $query ) {
		global $wpdb;

		if ( '' === self::$search_term || ! self::is_product_query( $query ) ) {
			return $search;
		}

		$has_lookup = ! empty( $wpdb->wc_product_meta_lookup );
		$words      = array_slice(
			array_filter( preg_split( '/\s+/', self::$search_term ) ),
			0,
			self::MAX_SEARCH_WORDS
		);
		if ( ! $words ) {
			return $search;
		}

		$clauses = array();
		foreach ( $words as $word ) {
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			$any  = array( $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like ) );
			if ( $has_lookup ) {
				$any[] = $wpdb->prepare( 'wffn_bundles_lookup.sku LIKE %s', $like );
			}
			$clauses[] = '( ' . implode( ' OR ', $any ) . ' )';
		}

		$where = implode( ' AND ', $clauses );

		// Merchants on large catalogues paste product ids straight in.
		if ( is_numeric( self::$search_term ) ) {
			$where = '( ' . $where . ' ) OR '
				. $wpdb->prepare( "{$wpdb->posts}.ID = %d", absint( self::$search_term ) );
		}

		return " AND ( {$where} ) ";
	}

	/**
	 * Join the product lookup table so SKUs are searchable off their index.
	 *
	 * @internal Public only so it can be (un)hooked; not part of the API.
	 *
	 * @param string    $join  Existing JOIN clause.
	 * @param \WP_Query $query Query being filtered.
	 * @return string
	 */
	public static function filter_search_join( $join, $query ) {
		global $wpdb;

		if ( '' === self::$search_term || empty( $wpdb->wc_product_meta_lookup ) || ! self::is_product_query( $query ) ) {
			return $join;
		}

		return $join . " LEFT JOIN {$wpdb->wc_product_meta_lookup} AS wffn_bundles_lookup"
			. " ON wffn_bundles_lookup.product_id = {$wpdb->posts}.ID ";
	}

	/**
	 * Guard so the search filters only ever touch a product query.
	 *
	 * @param mixed $query Query being filtered.
	 * @return bool
	 */
	private static function is_product_query( $query ) {
		if ( ! $query instanceof \WP_Query ) {
			return false;
		}
		$post_type = $query->get( 'post_type' );
		return is_array( $post_type )
			? in_array( 'product', $post_type, true )
			: 'product' === $post_type;
	}

	/**
	 * Shape a page of products, priming in bulk every cache the shaper would
	 * otherwise fill one product at a time.
	 *
	 * @param \WC_Product[] $products        Products to shape.
	 * @param bool          $with_variations Inline variations and attributes.
	 * @return array
	 */
	private static function shape_all( array $products, $with_variations ) {
		$image_ids = array();
		$child_ids = array();

		foreach ( $products as $product ) {
			$image_ids[] = (int) $product->get_image_id();
			if ( $with_variations && $product instanceof \WC_Product_Variable ) {
				foreach ( $product->get_children() as $child_id ) {
					$child_ids[] = absint( $child_id );
				}
			}
		}

		// Variations first: hydrating them surfaces the image ids below.
		$children  = array();
		$child_ids = array_values( array_unique( array_filter( $child_ids ) ) );
		if ( $child_ids ) {
			// See prime(): term cache included, or the factory queries per variation.
			_prime_post_caches( $child_ids, true, true );
			self::prime_product_terms( $child_ids );
			foreach ( $child_ids as $child_id ) {
				$variation = self::product( $child_id );
				if ( $variation && $variation->exists() ) {
					$children[ $child_id ] = $variation;
					$image_ids[]           = (int) $variation->get_image_id();
				}
			}
		}

		$image_ids = array_values( array_unique( array_filter( $image_ids ) ) );
		if ( $image_ids ) {
			_prime_post_caches( $image_ids, false, true );
		}

		$items = array();
		foreach ( $products as $product ) {
			$items[] = self::shape( $product, $with_variations, $children );
		}
		return $items;
	}

	/**
	 * Search product categories, one page at a time.
	 *
	 * @param array $args search, per_page, page.
	 * @return array
	 */
	public static function categories( array $args = array() ) {
		$search   = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$per_page = self::clamp_per_page( $args['per_page'] ?? 0 );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		$query = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( '' !== $search ) {
			$query['search'] = $search;
		}

		$terms = get_terms( $query );
		$items = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$items[] = array(
					'id'    => (string) $term->term_id,
					'name'  => wp_specialchars_decode( $term->name, ENT_QUOTES ),
					'slug'  => $term->slug,
					'count' => (int) $term->count,
				);
			}
		}
		return $items;
	}

	/**
	 * Resolve stored category references to display rows, so a picker can label a
	 * saved selection without paging the whole taxonomy.
	 *
	 * @param array $refs Term ids, slugs or names.
	 * @return array
	 */
	public static function categories_by_ref( array $refs ) {
		$items = array();
		foreach ( $refs as $ref ) {
			$ref = sanitize_text_field( (string) $ref );
			if ( '' === $ref ) {
				continue;
			}
			$term = self::resolve_category( $ref );
			if ( $term ) {
				$items[] = array(
					'id'    => (string) $term->term_id,
					'name'  => wp_specialchars_decode( $term->name, ENT_QUOTES ),
					'slug'  => $term->slug,
					'count' => (int) $term->count,
				);
			}
		}
		return $items;
	}

	/**
	 * Shape a WC_Product into the admin-app product object.
	 *
	 * @param \WC_Product   $product         Product to shape.
	 * @param bool          $with_variations Inline variations and attributes.
	 * @param array|null    $children        Pre-hydrated variations keyed by id,
	 *                                       from shape_all(). Null hydrates here.
	 * @return array
	 */
	private static function shape( $product, $with_variations = true, $children = null ) {
		$type = $product->get_type();

		// get_the_terms(), not wp_get_post_terms(): only the former reads the
		// object term cache that WC primes for the whole page at once.
		$terms    = get_the_terms( $product->get_id(), 'product_cat' );
		$category = ( $terms && ! is_wp_error( $terms ) ) ? wp_specialchars_decode( $terms[0]->name, ENT_QUOTES ) : '';

		$base = self::base_price( $product );
		$item = array(
			'id'        => (string) $product->get_id(),
			'name'      => $product->get_name(),
			'type'      => $type,
			'image'     => WooCommerce::get_product_image_url( $product->get_image_id() ),
			'imageId'   => (int) $product->get_image_id(),
			'price'     => self::price( $base ),
			'permalink' => $product->get_permalink(),
			// Arithmetic must use this: parsing `price` back out of wc_price() markup
			// loses the cents under a comma decimal separator.
			'priceRaw'  => (float) $base,
			'category'  => $category,
		);

		if ( $with_variations && 'variable' === $type && $product instanceof \WC_Product_Variable ) {
			$var_attributes = $product->get_variation_attributes();
			foreach ( $var_attributes as $name => $options ) {
				$var_attributes[ $name ] = self::term_ordered( $product->get_id(), $name, $options );
			}

			// Keyed by display label like the picker axes, since the storefront JS
			// matches on "{Label}:{value}" when constraining dependent dropdowns.
			$label_by_akey = array();
			foreach ( $var_attributes as $name => $options ) {
				$label_by_akey[ 'attribute_' . sanitize_title( $name ) ] = wc_attribute_label( $name, $product );
			}

			$variations = array();
			foreach ( $product->get_children() as $child_id ) {
				$child_id  = absint( $child_id );
				$variation = null !== $children
					? ( $children[ $child_id ] ?? null )
					: wc_get_product( $child_id );
				if ( ! $variation || ! $variation->exists() ) {
					continue;
				}
				$img_id     = $variation->get_image_id() ?: $product->get_image_id();
				$child_base = self::base_price( $variation );
				// An empty value is WooCommerce's "any" wildcard for that axis.
				$combo = array();
				foreach ( $variation->get_variation_attributes() as $akey => $val ) {
					if ( isset( $label_by_akey[ $akey ] ) ) {
						$combo[ $label_by_akey[ $akey ] ] = (string) $val;
					}
				}
				$variations[] = array(
					'id'          => (string) $child_id,
					'name'        => $variation->get_name(),
					'type'        => 'variation',
					'image'       => WooCommerce::get_product_image_url( $img_id ),
					'imageId'     => (int) $img_id,
					'price'       => self::price( $child_base ),
					'priceRaw'    => (float) $child_base,
					'attributes'  => $combo,
					// Only buyable, in-stock variations constrain the dropdowns, mirroring
					// core's get_available_variations().
					'purchasable' => self::variation_buyable( $variation, $child_base ),
				);
			}
			if ( $variations ) {
				$item['variations'] = $variations;
			}

			// One picker dropdown per attribute. Axes the merchant left unconfigured
			// fall back to the first buyable combo, as Cart::default_variation does.
			$defaults   = $product->get_default_attributes();
			$labels     = array();
			$matched    = array();
			foreach ( $var_attributes as $name => $options ) {
				$options = array_values( array_filter( (array) $options ) );
				if ( $options ) {
					$labels[ $name ]  = wc_attribute_label( $name, $product );
					$matched[ $labels[ $name ] ] = self::default_option( $defaults, $name, $options );
				}
			}
			$fallback   = self::first_available_combo( $variations, $matched );
			$attributes = array();
			foreach ( $var_attributes as $name => $options ) {
				$options = array_values( array_filter( (array) $options ) );
				if ( $options ) {
					$label   = $labels[ $name ];
					$default = '' !== $matched[ $label ]
						? $matched[ $label ]
						: self::match_option( (string) ( $fallback[ $label ] ?? '' ), $options );
					$attributes[] = array(
						'name'    => $label,
						'options' => $options,
						'labels'  => self::option_labels( $name, $options ),
						'default' => $default,
					);
				}
			}
			if ( $attributes ) {
				$item['attributes'] = $attributes;
			}
		}

		// A specific-variation member whose parent leaves an attribute "Any" carries
		// those open axes, so the picker renders for it.
		if ( 'variation' === $type ) {
			$open_axes = self::partial_variation_axes( $product->get_id() );
			if ( $open_axes ) {
				$item['attributes'] = $open_axes;
			}
		}

		return $item;
	}

	/**
	 * Can this variation be bought right now?
	 *
	 * The same answer as `is_purchasable() && is_in_stock()`, running the same three
	 * filters, but taking "has a price" from the edit-context price the caller
	 * already read: the display-price filter chain cannot change whether a price
	 * exists, only what it is.
	 *
	 * @param \WC_Product_Variation $variation  Variation to test.
	 * @param string|float          $base_price Edit-context price, '' when unset.
	 * @return bool
	 */
	private static function variation_buyable( $variation, $base_price ) {
		$id        = $variation->get_id();
		$parent_id = $variation->get_parent_id();
		$has_price = '' !== $base_price && null !== $base_price;

		$parent_data   = $variation->get_parent_data();
		$parent_status = (string) ( $parent_data['status'] ?? get_post_status( $parent_id ) );

		$visible = apply_filters( 'woocommerce_variation_is_visible', 'publish' === get_post_status( $id ) && $has_price, $id, $parent_id, $variation );

		$own_status = $variation->get_status();
		$sellable   = apply_filters( 'woocommerce_is_purchasable', $variation->exists() && ( 'publish' === $own_status || current_user_can( 'edit_post', $id ) ) && $has_price, $variation );

		$parent_ok   = 'publish' === $parent_status || current_user_can( 'edit_post', $parent_id );
		$purchasable = apply_filters( 'woocommerce_variation_is_purchasable', $visible && $sellable && $parent_ok, $variation );

		return (bool) $purchasable && $variation->is_in_stock();
	}

	/**
	 * A product's base-currency unit price, or '' when it has none.
	 *
	 * Not a bare `get_price( 'edit' )`: that misses a switcher-resolved manual
	 * per-currency price.
	 *
	 * @param \WC_Product $product Product to price.
	 * @return string|float
	 */
	private static function base_price( $product ) {
		$raw = $product->get_price( 'edit' );
		return ( '' === $raw || null === $raw ) ? '' : Layer::get_base_price( $product );
	}

	/**
	 * Format a base-currency price for display, the twin of Skins\Pricing::format().
	 *
	 * @param string|float $price Base-currency price, or '' when the product has none.
	 * @return string
	 */
	private static function price( $price ) {
		if ( '' === $price || null === $price ) {
			return '';
		}
		$display = Layer::convert( (float) $price );
		return html_entity_decode( wp_strip_all_tags( Layer::format( $display ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * What a shopper reads for one attribute option.
	 *
	 * A global attribute stores its options as term slugs; the term's own name is
	 * the label. A custom attribute's option already is its label.
	 *
	 * @param string $attribute Attribute name or `attribute_`-prefixed key.
	 * @param string $value     Option as stored.
	 * @return string
	 */
	public static function option_label( $attribute, $value ) {
		$attribute = (string) $attribute;
		$value     = (string) $value;
		$taxonomy  = 0 === strpos( $attribute, 'attribute_' ) ? substr( $attribute, 10 ) : $attribute;
		if ( '' !== $value && taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				return $term->name;
			}
		}

		return $value;
	}

	/**
	 * Labels for an attribute's options, keyed by option.
	 *
	 * @param string   $attribute Attribute name.
	 * @param string[] $options   Options as stored.
	 * @return array<string,string>
	 */
	private static function option_labels( $attribute, array $options ) {
		$labels = array();
		foreach ( $options as $option ) {
			$labels[ (string) $option ] = self::option_label( $attribute, $option );
		}

		return $labels;
	}

	/**
	 * The merchant's configured default for one variation attribute, as one of the
	 * option strings the picker renders, or '' when there is no usable default.
	 *
	 * `get_default_attributes()` is keyed by the sanitized attribute name while
	 * `get_variation_attributes()` is keyed by the raw one, so the key match runs
	 * both through `sanitize_title`. A default naming an option that no longer
	 * exists returns '', leaving the placeholder selected.
	 *
	 * @param array  $defaults Merchant default attributes.
	 * @param string $name     Raw attribute name.
	 * @param array  $options  Option strings for that axis.
	 * @return string
	 */
	private static function default_option( array $defaults, $name, array $options ) {
		if ( ! $defaults ) {
			return '';
		}
		$key   = sanitize_title( (string) $name );
		$value = '';
		foreach ( $defaults as $default_name => $default_value ) {
			if ( sanitize_title( (string) $default_name ) === $key ) {
				$value = (string) $default_value;
				break;
			}
		}
		return self::match_option( $value, $options );
	}

	/**
	 * One stored attribute value resolved to the option string the picker renders.
	 *
	 * A taxonomy attribute stores a term slug against slug options, a custom one
	 * stores the value, so an exact match is tried first and a sanitized one second.
	 *
	 * @param string $value   Stored attribute value.
	 * @param array  $options Option strings for that axis.
	 * @return string The matching option, or '' when none matches.
	 */
	private static function match_option( $value, array $options ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		foreach ( $options as $option ) {
			$option = (string) $option;
			if ( $option === $value || sanitize_title( $option ) === sanitize_title( $value ) ) {
				return $option;
			}
		}
		return '';
	}

	/**
	 * The first buyable variation's attribute combo, keyed by attribute label.
	 *
	 * Only purchasable, in-stock variations qualify. Combos contradicting an axis
	 * the merchant did configure are skipped, so the two sources can never mix into
	 * a pairing with no variation behind it; an empty value is the "any" wildcard.
	 *
	 * @param array $variations Variation rows as built above (attributes + purchasable).
	 * @param array $matched    Resolved merchant defaults, keyed by attribute label.
	 * @return array Attribute combo keyed by label, or [] when nothing is buyable.
	 */
	private static function first_available_combo( array $variations, array $matched ) {
		$loose = array();
		foreach ( $variations as $variation ) {
			if ( empty( $variation['purchasable'] ) || ! is_array( $variation['attributes'] ?? null ) ) {
				continue;
			}
			$combo = $variation['attributes'];
			if ( ! $loose ) {
				$loose = $combo;
			}
			$fits = true;
			foreach ( $matched as $label => $value ) {
				$actual = (string) ( $combo[ $label ] ?? '' );
				if ( '' !== $value && '' !== $actual && sanitize_title( $actual ) !== sanitize_title( $value ) ) {
					$fits = false;
					break;
				}
			}
			if ( $fits ) {
				return $combo;
			}
		}
		return $loose;
	}

	/**
	 * The unresolved ("Any") axes of a partial variation, as picker attributes.
	 *
	 * WooCommerce won't add such a variation to the cart until a concrete value is
	 * chosen, so a picker is rendered for the open axes, with options pulled fresh
	 * from the parent. Fixed axes stay on the variation and re-apply at resolve time.
	 *
	 * @param int $product_id Variation id.
	 * @return array List of { name, options } for each open axis; empty when the
	 *               product is not a variation, or is a fully-specified one.
	 */
	public static function partial_variation_axes( $product_id ) {
		$variation = wc_get_product( (int) $product_id );
		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return array();
		}

		$open = array();
		foreach ( $variation->get_variation_attributes() as $akey => $value ) {
			if ( '' === $value ) {
				$open[] = $akey; // an "Any" axis this variation does not pin
			}
		}
		if ( ! $open ) {
			return array();
		}

		$parent = wc_get_product( $variation->get_parent_id() );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return array();
		}

		$axes = array();
		foreach ( $parent->get_variation_attributes() as $name => $options ) {
			if ( ! in_array( 'attribute_' . sanitize_title( $name ), $open, true ) ) {
				continue;
			}
			$options = array_values( array_filter( self::term_ordered( $parent->get_id(), $name, $options ) ) );
			if ( $options ) {
				$axes[] = array(
					'name'    => wc_attribute_label( $name, $parent ),
					'options' => $options,
				);
			}
		}
		return $axes;
	}

	/**
	 * A taxonomy attribute's options in the product's term order, as its own dropdown lists them.
	 *
	 * Options with no matching term (a custom text attribute) keep their order at the end.
	 *
	 * @param int    $product_id Product id.
	 * @param string $name       Attribute name, e.g. `pa_color`.
	 * @param array  $options    Option slugs.
	 * @return array
	 */
	private static function term_ordered( $product_id, $name, $options ) {
		$options = (array) $options;
		if ( ! taxonomy_exists( $name ) ) {
			return $options;
		}
		$order = wc_get_product_terms( $product_id, $name, array( 'fields' => 'slugs' ) );
		$order = is_array( $order ) ? $order : array();
		return array_values( array_merge( array_intersect( $order, $options ), array_diff( $options, $order ) ) );
	}
}
