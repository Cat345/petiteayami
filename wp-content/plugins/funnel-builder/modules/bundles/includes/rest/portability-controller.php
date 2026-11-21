<?php
/**
 * Export and import of a bundle's full configuration.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Rest;

use WFFN\Bundles\Includes\Storage\Bundle_Rules;
use WFFN\Bundles\Includes\Storage\Bundle_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Portable bundle config, product references resolved by ID, then SKU,
 * then exact name — never blindly trusted. Every import lands as a Draft.
 */
class Portability_Controller extends Base {

	/**
	 * The most bundles one import request takes. Each create rewrites the
	 * index, so memory grows with the request: a single request of a few
	 * thousand exhausts a 256M limit part-way and leaves a partial import.
	 * The admin app sends bigger files in batches under this.
	 */
	const MAX_PER_REQUEST = 250;

	/** Bundles loaded, encoded and released together while exporting. */
	const EXPORT_BATCH = 100;

	/**
	 * Register the export/import routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET for a link or a script; POST so a selection of thousands of ids
		// travels in the body, not a URL the web server would refuse.
		register_rest_route(
			$this->namespace,
			'/bundles/export',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/bundles/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_write' ),
			)
		);
	}

	/**
	 * GET|POST /bundles/export — every field a bundle has, streamed as a
	 * JSON attachment. `ids` is a comma list (GET) or an array or comma list in
	 * the body (POST); empty exports every bundle.
	 *
	 * Written one batch at a time and released as it goes, so the memory a
	 * request needs does not grow with the number of bundles.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return void
	 */
	public function export( $request ) {
		$raw = $request->get_param( 'ids' );
		$ids = array();
		foreach ( is_array( $raw ) ? $raw : explode( ',', (string) $raw ) as $id ) {
			$id = trim( (string) $id );
			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}
		if ( empty( $ids ) ) {
			foreach ( Bundle_Store::index() as $summary ) {
				$ids[] = (string) ( $summary['id'] ?? '' );
			}
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wffn-bundles-export-' . gmdate( 'Y-m-d-His' ) . '.json"' );

		// Nothing buffered may hold the whole file back.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		echo '[';
		$first = true;
		foreach ( array_chunk( $ids, self::EXPORT_BATCH ) as $batch ) {
			Bundle_Store::prime_configs( $batch );
			foreach ( $batch as $id ) {
				$config = Bundle_Store::get_config( $id );
				if ( null === $config ) {
					continue;
				}
				$config = Bundle_Store::walk_products( $config, array( $this, 'decorate_reference' ) );
				$json   = wp_json_encode( $config );
				if ( false === $json ) {
					continue;
				}
				echo ( $first ? '' : ',' ) . $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw JSON attachment, wp_json_encode()'d.
				$first = false;
			}
			Bundle_Store::release_configs();
			flush();
		}
		echo ']';
		exit;
	}

	/**
	 * A product reference, decorated with what an import on another site needs
	 * to find it again — the id alone means nothing once it leaves this site.
	 *
	 * @param array $ref { id }.
	 * @return array|null
	 */
	public function decorate_reference( $ref ) {
		$id = is_array( $ref ) ? absint( $ref['id'] ?? 0 ) : 0;
		if ( ! $id ) {
			return $ref;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product ) {
			return $ref;
		}
		return array_merge(
			$ref,
			array(
				'name' => $product->get_name(),
				'sku'  => $product->get_sku(),
			)
		);
	}

	/**
	 * POST /bundles/import { bundles: [ … ] } — resolves every product
	 * reference against this site, then creates each bundle as a Draft.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function import( $request ) {
		$payload   = $this->payload( $request );
		$bundles = is_array( $payload['bundles'] ?? null ) ? $payload['bundles'] : array();
		if ( empty( $bundles ) ) {
			return self::err( __( 'No bundles in this file.', 'funnel-builder' ), 400 );
		}
		if ( count( $bundles ) > self::MAX_PER_REQUEST ) {
			return self::err(
				sprintf(
					/* translators: %d: the most bundles one import request takes. */
					__( 'Too many bundles in one request. Send at most %d at a time.', 'funnel-builder' ),
					self::MAX_PER_REQUEST
				),
				400
			);
		}

		$created = array();
		$matched = 0;
		$blank   = 0;
		$skipped = 0;
		$review  = 0;
		$types   = Bundle_Store::accepted_types();

		foreach ( $bundles as $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}
			// The create route refuses these too; a file must not store what nothing here renders.
			if ( isset( $config['discountType'] ) && ! in_array( (int) $config['discountType'], $types, true ) ) {
				++$skipped;
				continue;
			}
			$config = Bundle_Store::walk_products(
				$config,
				function ( $ref ) use ( &$matched, &$blank ) {
					$resolved = $this->resolve_reference( $ref );
					if ( null === $resolved ) {
						++$blank;
						return null;
					}
					++$matched;
					return $resolved;
				}
			);

			// References outside the lists this plugin knows about (a file from a
			// site running the add-on) still name the source site's products.
			$config = $this->settle_unwalked_references( $config, $matched, $blank );

			// Never trust an imported status: every import is a Draft, even
			// when every product matched, so nothing goes live unreviewed.
			$config['status'] = 0;
			// The editor keys rows by id and refuses to save a bare one, so a file's bare rows get ids here.
			$config = Bundle_Store::fill_row_ids( $config );

			$stored = Bundle_Store::create( $config );
			if ( null !== $stored ) {
				// Still imported as a Draft; the count tells the merchant which ones the editor will flag.
				if ( ! empty( Bundle_Rules::validate( $config ) ) ) {
					++$review;
				}
				$created[] = array(
					'id'   => (string) ( $stored['id'] ?? '' ),
					'name' => (string) ( $stored['name'] ?? '' ),
				);
			}
		}

		if ( empty( $created ) ) {
			return self::err( __( 'No bundles were imported. Nothing in this file could be created.', 'funnel-builder' ), 400 );
		}

		return self::ok(
			array(
				'created'            => $created,
				'productsMatched'    => $matched,
				'productsLeftBlank'  => $blank,
				'skippedUnknownType' => $skipped,
				'needsReview'        => $review,
			),
			__( 'Import complete.', 'funnel-builder' )
		);
	}

	/**
	 * Verify every exported product reference the known-list walk did not reach.
	 *
	 * The walk only knows the lists this plugin owns; a file from a site running
	 * the add-on carries more (offers, upsells, gifts), each still holding the
	 * source site's product id. An exported reference is recognisable by the
	 * `name` and `sku` decorate_reference() adds, and the walk strips those from
	 * the ones it resolves, so anything still wearing them is unverified. Each is
	 * resolved the same way, or dropped: a blank slot is safe, a stranger's
	 * product is not.
	 *
	 * @param array $node    Config, or any array inside it.
	 * @param int   $matched Running count of references resolved.
	 * @param int   $blank   Running count of references left blank.
	 * @return array
	 */
	private function settle_unwalked_references( array $node, &$matched, &$blank ) {
		$dropped = false;
		foreach ( $node as $key => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( array_key_exists( 'sku', $child ) && array_key_exists( 'name', $child ) && ctype_digit( (string) ( $child['id'] ?? '' ) ) ) {
				$resolved = $this->resolve_reference( $child );
				if ( null === $resolved ) {
					++$blank;
					if ( is_int( $key ) ) {
						unset( $node[ $key ] );
						$dropped = true;
					} else {
						$node[ $key ] = null;
					}
				} else {
					++$matched;
					$node[ $key ] = $resolved;
				}
				continue;
			}
			$node[ $key ] = $this->settle_unwalked_references( $child, $matched, $blank );
		}

		// A JSON list must stay a list once members are dropped from it.
		if ( $dropped && array_keys( $node ) === array_filter( array_keys( $node ), 'is_int' ) ) {
			$node = array_values( $node );
		}
		return $node;
	}

	/**
	 * Resolve one exported product reference against this site: the same id
	 * (and name, so a recycled id can't adopt a stranger's row), else its SKU,
	 * else an unambiguous exact name match. Anything else resolves to nothing
	 * rather than guessing.
	 *
	 * @param mixed $ref Exported reference, shaped by decorate_reference().
	 * @return array|null { id }, or null to leave the slot empty.
	 */
	private function resolve_reference( $ref ) {
		if ( ! is_array( $ref ) || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$id   = absint( $ref['id'] ?? 0 );
		$name = (string) ( $ref['name'] ?? '' );
		$sku  = (string) ( $ref['sku'] ?? '' );

		if ( $id && '' !== $name ) {
			$product = wc_get_product( $id );
			if ( $product && $product->get_name() === $name ) {
				return array( 'id' => (string) $id );
			}
		}

		if ( '' !== $sku ) {
			$found = wc_get_product_id_by_sku( $sku );
			if ( $found ) {
				return array( 'id' => (string) $found );
			}
		}

		if ( '' !== $name ) {
			// The same arguments get_posts() would add: no filters, no sticky posts, no row count, newest first.
			$matches = ( new \WP_Query(
				array(
					'post_type'           => 'product',
					'title'               => $name,
					'post_status'         => array( 'publish', 'private', 'draft' ),
					'posts_per_page'      => 2,
					'fields'              => 'ids',
					'suppress_filters'    => true,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'date',
					'order'               => 'DESC',
				)
			) )->posts;
			if ( 1 === count( $matches ) ) {
				return array( 'id' => (string) $matches[0] );
			}
		}

		return null;
	}
}
