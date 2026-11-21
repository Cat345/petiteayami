<?php
/** REST Controller — real endpoints under wffn-bundles-admin/v1. */
namespace WFFN\Bundles\Includes\Rest;

use WFFN\Bundles\Includes\Storage\Bundle_Schema;
use WFFN\Bundles\Includes\Storage\Bundle_Service;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Skins\Frequently_Bought_Together;
use WFFN\Bundles\Includes\Products\Repository as Products;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin REST endpoints for bundles, products and categories.
 */
class Controller extends Base {


	/**
	 * Register every route this controller serves.
	 *
	 * @return void
	 */
	public function register_routes() {
		$perm  = array( $this, 'can_manage' );
		$write = array( $this, 'can_write' );
		// The editor opens on a bundle that does not exist yet and still needs
		// everything `include` carries, so the reference covers that case too.
		$id   = '(?P<id>[0-9]+|new)';

		register_rest_route( $this->namespace, '/bundles', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'list_bundles' ), 'permission_callback' => $perm ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create_bundle' ), 'permission_callback' => $write ),
		) );

		register_rest_route( $this->namespace, '/bundles/reorder', array(
			'methods' => 'POST', 'callback' => array( $this, 'reorder_bundles' ), 'permission_callback' => $write,
		) );

		register_rest_route( $this->namespace, '/bundles/bulk-delete', array(
			'methods' => 'POST', 'callback' => array( $this, 'bulk_delete_bundles' ), 'permission_callback' => $write,
		) );

		register_rest_route( $this->namespace, '/bundles/bulk-assign-product', array(
			'methods' => 'POST', 'callback' => array( $this, 'bulk_assign_product' ), 'permission_callback' => $write,
		) );

		register_rest_route( $this->namespace, '/bundles/bulk-status', array(
			'methods' => 'POST', 'callback' => array( $this, 'bulk_set_status' ), 'permission_callback' => $write,
		) );

		register_rest_route( $this->namespace, '/bundles/' . $id, array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_bundle' ),
				'permission_callback' => $perm,
				'args'                => array(
					'include' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
			array( 'methods' => 'PUT', 'callback' => array( $this, 'update_bundle' ), 'permission_callback' => $write ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_bundle' ), 'permission_callback' => $write ),
		) );

		register_rest_route( $this->namespace, '/bundles/' . $id . '/status', array(
			'methods' => 'PUT', 'callback' => array( $this, 'set_status' ), 'permission_callback' => $write,
		) );

		register_rest_route( $this->namespace, '/products', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_products' ),
			'permission_callback' => $perm,
			'args'                => array(
				'search'             => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'category'           => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				// Clamped, not absint()ed: absint would read page -3 as page 3.
				'page'               => array(
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => static function ( $value ) {
						return max( 1, (int) $value );
					},
				),
				'per_page'           => array( 'type' => 'integer', 'default' => Products::DEFAULT_PER_PAGE, 'sanitize_callback' => 'absint' ),
				// Off for list-only pickers: skips inlining every variation.
				'include_variations' => array( 'type' => 'boolean', 'default' => true ),
				// Comma-separated product ids to hydrate directly, in order.
				'include'            => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				// A product whose bought-together companions to return instead of a search.
				'linked_to'          => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'fallback'           => array(
					'type'    => 'string',
					'enum'    => array( 'cross_sells', 'cross_sells_upsells', 'none' ),
					'default' => 'cross_sells',
				),
				'max'                => array( 'type' => 'integer', 'default' => Frequently_Bought_Together::DEFAULT_MAX_ITEMS, 'sanitize_callback' => 'absint' ),
			),
		) );
		register_rest_route( $this->namespace, '/products/categories', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_categories' ),
			'permission_callback' => $perm,
			'args'                => array(
				'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'page'     => array(
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => static function ( $value ) {
						return max( 1, (int) $value );
					},
				),
				'per_page' => array( 'type' => 'integer', 'default' => Products::DEFAULT_PER_PAGE, 'sanitize_callback' => 'absint' ),
				// Comma-separated ids/slugs/names to resolve to labels without paging the taxonomy.
				'include'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		register_rest_route(
			$this->namespace,
			'/search/roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_roles' ),
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * GET /bundles — a page of list rows with metrics and targeting overlaid.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function list_bundles( $request ) {
		$data = Bundle_Store::list_bundles( array(
			'page'     => $request->get_param( 'page' ),
			'per_page' => $request->get_param( 'per_page' ),
			'search'   => $request->get_param( 'search' ),
		) );

		$data['bundles'] = $this->overlay_list_rank( $data['bundles'] );

		return self::ok( $data );
	}

	/**
	 * Overlay each row's resolution rank at response time.
	 *
	 * @param array[] $bundles List rows.
	 * @return array[]
	 */
	private function overlay_list_rank( array $bundles ) {
		$ranks = Bundle_Store::rank_map();

		foreach ( $bundles as &$row ) {
			$row['priority'] = (int) ( $ranks[ (string) ( $row['id'] ?? 0 ) ] ?? 0 );
		}
		unset( $row );

		return $bundles;
	}



	/**
	 * POST /bundles — create a bundle.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function create_bundle( $request ) {
		$item = Bundle_Service::create( $this->payload( $request ) );
		if ( is_wp_error( $item ) ) {
			return self::refusal( $item );
		}
		return self::ok( $item, __( 'Bundle created.', 'funnel-builder' ), 201 );
	}

	/**
	 * GET /bundles/{id} — the full bundle record, or for `new` the one a create
	 * with the same `discount_type`, `name` and `quick_start` would start with.
	 *
	 * `include` names what else the caller wants beside it, and asking at all
	 * wraps the reply. The editor is built from the record plus the schema and
	 * the settings, so it asks for whichever of those it has not already cached
	 * rather than spending a round trip on each.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function get_bundle( $request ) {
		$unsaved = 'new' === self::route_id( $request );
		$payload = $unsaved ? Bundle_Service::starter( $this->starter_fields( $request ) ) : Bundle_Store::get( self::route_id( $request ) );
		if ( is_wp_error( $payload ) ) {
			return self::refusal( $payload );
		}
		// A bundle of a type no active plugin names is not listed, so it is not found either.
		if ( null !== $payload && ! in_array( (int) ( $payload['discountType'] ?? 0 ), Bundle_Store::listed_types(), true ) ) {
			$payload = null;
		}
		if ( null === $payload && ! $unsaved ) {
			return self::err( __( 'Bundle not found.', 'funnel-builder' ), 404 );
		}

		if ( null === $request->get_param( 'include' ) ) {
			return self::ok( $payload );
		}

		$include = array_filter( array_map( 'trim', explode( ',', (string) $request->get_param( 'include' ) ) ) );
		$out     = array( 'bundle' => $payload );

		if ( in_array( 'schema', $include, true ) ) {
			$out['schema'] = Bundle_Schema::get();
		}

		if ( in_array( 'settings', $include, true ) ) {
			$out['settings'] = Settings_Controller::read();
		}

		return self::ok( $out );
	}

	/**
	 * The create fields a request for an unsaved bundle names.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return array
	 */
	private function starter_fields( $request ) {
		$fields = array();
		foreach ( array( 'discount_type', 'name', 'quick_start' ) as $key ) {
			$value = $request->get_param( $key );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$fields[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $fields;
	}

	/**
	 * PUT /bundles/{id} — validate and apply a partial bundle patch.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function update_bundle( $request ) {
		$payload = Bundle_Service::update( self::route_id( $request ), $this->payload( $request ) );
		if ( is_wp_error( $payload ) ) {
			return self::refusal( $payload );
		}
		return self::ok( $payload, __( 'Saved.', 'funnel-builder' ) );
	}

	/**
	 * The error envelope for a refusal from Bundle_Service.
	 *
	 * @param \WP_Error $error The refusal.
	 * @return \WP_REST_Response
	 */
	private static function refusal( \WP_Error $error ) {
		$data = (array) $error->get_error_data();
		return self::err(
			$error->get_error_message(),
			(int) ( $data['status'] ?? 400 ),
			$data['path'] ?? null,
			$data['issues'] ?? null
		);
	}

	/**
	 * DELETE /bundles/{id} — remove a bundle.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function delete_bundle( $request ) {
		$deleted = Bundle_Service::delete( self::route_id( $request ) );
		if ( is_wp_error( $deleted ) ) {
			return self::refusal( $deleted );
		}
		return self::ok( new \stdClass() );
	}

	/**
	 * PUT /bundles/{id}/status — activate or deactivate a bundle.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function set_status( $request ) {
		$body   = $this->payload( $request );
		$status = Bundle_Service::set_status( self::route_id( $request ), $body['status'] ?? 0 );
		if ( is_wp_error( $status ) ) {
			return self::refusal( $status );
		}
		return self::ok( array( 'status' => $status ) );
	}

	/**
	 * POST /bundles/bulk-status { ids: [...], status } — set every listed
	 * bundle's status in one request. Each id still runs the same activation
	 * gates as the single-bundle route; one that fails them is skipped
	 * rather than failing the whole batch.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function bulk_set_status( $request ) {
		$body   = $this->payload( $request );
		$ids    = array_filter( array_map( 'sanitize_text_field', (array) ( $body['ids'] ?? array() ) ) );
		$status = isset( $body['status'] ) ? (int) $body['status'] : 0;
		if ( empty( $ids ) ) {
			return self::err( __( 'No bundles selected.', 'funnel-builder' ), 400 );
		}

		$result = Bundle_Service::bulk_set_status( $ids, $status );

		return self::ok(
			array(
				'updated'   => count( $result['updated'] ),
				'skipped'   => count( $result['skipped'] ),
				'unchanged' => count( $result['unchanged'] ),
				'reasons'   => array_values( array_unique( wp_list_pluck( $result['skipped'], 'reason' ) ) ),
			)
		);
	}

	/**
	 * POST /bundles/reorder — re-order bundles from a drag in the list.
	 *
	 * `reorder_subset`, not `reorder`: the list is paginated, so `order` covers
	 * only the dragged page and rows the client never loaded must keep their rank.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function reorder_bundles( $request ) {
		$body = $this->payload( $request );
		Bundle_Store::reorder_subset( is_array( $body['order'] ?? null ) ? $body['order'] : array() );
		return self::ok( null );
	}

	/**
	 * POST /bundles/bulk-delete { ids: [...] } — remove every listed bundle
	 * in one request, so a list selection deletes without one call per row.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function bulk_delete_bundles( $request ) {
		$body = $this->payload( $request );
		$ids  = array_filter( array_map( 'sanitize_text_field', (array) ( $body['ids'] ?? array() ) ) );
		if ( empty( $ids ) ) {
			return self::err( __( 'No bundles selected.', 'funnel-builder' ), 400 );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( true === Bundle_Service::delete( $id ) ) {
				++$deleted;
			}
		}

		return self::ok( array( 'deleted' => $deleted ) );
	}

	/**
	 * POST /bundles/bulk-assign-product { ids: [...], product_ids: [...] } — adds
	 * the products to every listed bundle's target list in one request. A
	 * category-placed bundle is refused, as on the product tab, and every
	 * other bundle is saved behind the single-save checks.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function bulk_assign_product( $request ) {
		$body        = $this->payload( $request );
		$ids         = array_filter( array_map( 'sanitize_text_field', (array) ( $body['ids'] ?? array() ) ) );
		// A single `product_id` is still accepted from callers that send one.
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $body['product_ids'] ?? ( $body['product_id'] ?? array() ) ) ) ) ) );
		if ( empty( $ids ) || empty( $product_ids ) ) {
			return self::err( __( 'Select at least one bundle and a product.', 'funnel-builder' ), 400 );
		}
		foreach ( $product_ids as $product_id ) {
			if ( ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) {
				return self::err( __( 'Product not found.', 'funnel-builder' ), 404 );
			}
		}

		$updated = 0;
		$skipped = array();
		foreach ( $ids as $id ) {
			$config = Bundle_Store::get_config( $id );
			if ( null === $config ) {
				continue;
			}
			$selection = is_array( $config['productSelection'] ?? null ) ? $config['productSelection'] : array();
			if ( Bundle_Store::MODE_SPECIFIC_CATEGORIES === (int) ( $selection['mode'] ?? Bundle_Store::MODE_NONE ) ) {
				$skipped[] = array(
					'id'     => (string) $id,
					'reason' => __( 'This bundle is placed by category, so edit it to change where it shows.', 'funnel-builder' ),
				);
				continue;
			}
			$products = is_array( $selection['products'] ?? null ) ? $selection['products'] : array();

			$held = array_map( 'strval', wp_list_pluck( $products, 'id' ) );
			foreach ( $product_ids as $product_id ) {
				if ( ! in_array( (string) $product_id, $held, true ) ) {
					$products[] = array( 'id' => (string) $product_id );
					$held[]     = (string) $product_id;
				}
			}

			$selection['products'] = $products;
			$selection['mode']     = Bundle_Store::MODE_SPECIFIC_PRODUCTS;

			$saved = Bundle_Service::update( $id, array( 'productSelection' => $selection ) );
			if ( is_wp_error( $saved ) ) {
				$skipped[] = array(
					'id'     => (string) $id,
					'reason' => $saved->get_error_message(),
				);
				continue;
			}
			++$updated;
		}

		return self::ok(
			array(
				'updated' => $updated,
				'skipped' => $skipped,
			)
		);
	}

	/**
	 * GET /search/roles — every WP role as { id: slug, name: translated label }.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_roles() {
		$items = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$items[] = array(
				'id'   => (string) $slug,
				'name' => translate_user_role( $name ),
			);
		}
		return self::ok( array( 'items' => $items ) );
	}

	/**
	 * GET /products — search the catalogue, or hydrate an explicit id list.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function list_products( $request ) {
		$linked_to = absint( $request->get_param( 'linked_to' ) );
		if ( $linked_to ) {
			// The storefront's own resolution, so the editor preview shows what the product page will.
			$host = wc_get_product( $linked_to );
			$ids  = $host ? Frequently_Bought_Together::companion_ids(
				array(
					'fbtFallback' => (string) $request->get_param( 'fallback' ),
					'fbtMaxItems' => (int) $request->get_param( 'max' ),
				),
				$host
			) : array();
			return self::ok( array(
				'items'    => $this->hydrate_ids( $ids ),
				'has_more' => false,
			) );
		}

		// Id-resolution mode: hand back exactly the referenced products, in order.
		$include = (string) $request->get_param( 'include' );
		if ( '' !== $include ) {
			$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $include ) ) ) ) );
			return self::ok( array(
				'items'    => $this->hydrate_ids( array_slice( $ids, 0, Products::MAX_PER_PAGE ) ),
				'has_more' => false,
			) );
		}

		$per_page = Products::clamp_per_page( $request->get_param( 'per_page' ) );

		$items = Products::search( array(
			'search'             => $request->get_param( 'search' ),
			'category'           => $request->get_param( 'category' ),
			'page'               => $request->get_param( 'page' ),
			'per_page'           => $per_page,
			'include_variations' => $request->get_param( 'include_variations' ),
		) );

		$items = array_map( array( $this, 'extend_product_data' ), $items );

		// No total: counting matches costs a second scan, and "load more" only needs a full page.
		return self::ok( array(
			'items'    => $items,
			'has_more' => count( $items ) >= $per_page,
		) );
	}

	/**
	 * Shaped product rows for these ids, in order, skipping any that no longer exist.
	 *
	 * @param int[] $ids Product ids.
	 * @return array[]
	 */
	private function hydrate_ids( array $ids ) {
		Products::prime( $ids );
		$items = array();
		foreach ( $ids as $id ) {
			$item = Products::find( $id );
			if ( $item ) {
				$items[] = $this->extend_product_data( $item );
			}
		}
		return $items;
	}

	/**
	 * Let integrations extend one shaped product row on the admin wire.
	 *
	 * @param array $item Shaped product row from Products\Repository.
	 * @return array
	 */
	private function extend_product_data( $item ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $item['id'] ?? 0 ) ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return $item;
		}

		/**
		 * Filters a shaped product row before it is served to the admin app.
		 *
		 * Compat layers ride extra per-product data into the editor through this.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $item    Shaped product row.
		 * @param \WC_Product $product The product behind the row.
		 */
		return (array) apply_filters( 'wffn_bundles_admin_product_data', $item, $product );
	}

	/**
	 * GET /products/categories — search categories, or resolve an explicit ref list.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function list_categories( $request ) {
		// Label-resolution mode: hand back exactly the referenced terms.
		$include = (string) $request->get_param( 'include' );
		if ( '' !== $include ) {
			return self::ok( array(
				'items'    => Products::categories_by_ref( array_map( 'trim', explode( ',', $include ) ) ),
				'has_more' => false,
			) );
		}

		$per_page = Products::clamp_per_page( $request->get_param( 'per_page' ) );

		$items = Products::categories( array(
			'search'   => $request->get_param( 'search' ),
			'page'     => $request->get_param( 'page' ),
			'per_page' => $per_page,
		) );

		return self::ok( array(
			'items'    => $items,
			'has_more' => count( $items ) >= $per_page,
		) );
	}
}
