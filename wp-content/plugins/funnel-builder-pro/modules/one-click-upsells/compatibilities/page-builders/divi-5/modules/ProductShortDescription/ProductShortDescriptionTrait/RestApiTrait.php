<?php
/**
 * ProductShortDescription::rest_api().
 *
 * @package WFOCU\Modules\ProductShortDescription
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductShortDescription\ProductShortDescriptionTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait RestApiTrait {

	/**
	 * Register REST API endpoints for Product Short Description module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rest_endpoints(): void {
		// Register endpoint to fetch products for the current offer
		register_rest_route(
			'wfocu/v1',
			'/product-short-description/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_products' ),
				'permission_callback' => function () {
					// Allow if user can edit posts (Visual Builder access)
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * REST API callback to get products for Product Short Description.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public static function get_products( \WP_REST_Request $request ) {
		// Early return if WFOCU Core is not available
		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader ) {
			return rest_ensure_response(
				array(
					'0' => array(
						'label' => '--No Product--',
					),
				)
			);
		}

		// Ensure product data is loaded via the robust multi-source resolver.
		\WFOCU\Modules\ModuleRegistry::ensure_product_data();

		$template_loader = WFOCU_Core()->template_loader;

		// Fallback: if ensure_product_data() couldn't load products,
		// try the slug-based offer ID resolution from the referer.
		if ( ! isset( $template_loader->product_data->products ) || empty( (array) $template_loader->product_data->products ) ) {
			$offer_id = self::get_offer_id();
			if ( ! empty( $offer_id ) ) {
				$template_loader->offer_id = $offer_id;
				if ( method_exists( $template_loader, 'setup_complete_offer_setup_manual' ) ) {
					$template_loader->setup_complete_offer_setup_manual( $offer_id );
				}
			}
		}

		// Initialize response with default option
		$response = array(
			'0' => array(
				'label' => '--No Product--',
			),
		);

		$wfocu_products = $template_loader->product_data->products ?? array();

		// Build product options with short description for Visual Builder preview
		foreach ( $wfocu_products as $key => $product ) {
			$product_obj       = $product->data;
			$short_description = '';

			if ( $product_obj instanceof \WC_Product ) {
				$post_object = get_post( $product_obj->get_id() );
				if ( $post_object ) {
					$description = $post_object->post_excerpt ?? '';

					// Handle product variations
					if ( 'product_variation' === ( $post_object->post_type ?? '' ) ) {
						$variation_product = wc_get_product( $product_obj->get_id() );
						if ( $variation_product instanceof \WC_Product ) {
							$description = $variation_product->get_description();
						}
					}

					$short_description = apply_filters( 'woocommerce_short_description', $description );
				}

				$response[ (string) $key ] = array(
					'label'            => $product_obj->get_name(),
					'shortDescription' => $short_description,
				);
			}
		}

		// Remove "--No Product--" when real products exist (matches D4 default behavior).
		if ( count( $response ) > 1 ) {
			unset( $response['0'] );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get offer ID from referer URL or template loader.
	 *
	 * @since 1.0.0
	 * @return int Offer ID or 0 if not found.
	 */
	private static function get_offer_id(): int {
		$offer_id = 0;

		// Try to get from referer URL
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			if ( preg_match( '#/offer/([^/?]+)#', $referer, $matches ) ) {
				$offer_slug = sanitize_text_field( $matches[1] );
				$offer_post = get_page_by_path( $offer_slug, OBJECT, 'wfocu_offer' );

				if ( $offer_post && isset( $offer_post->ID ) ) {
					$offer_id = absint( $offer_post->ID );
				} else {
					// Fallback: Try WP_Query
					$query = new \WP_Query(
						array(
							'post_type'      => 'wfocu_offer',
							'name'           => $offer_slug,
							'posts_per_page' => 1,
							'post_status'    => 'publish',
						)
					);

					if ( $query->have_posts() ) {
						$offer_id = absint( $query->posts[0]->ID );
					}
					wp_reset_postdata();
				}
			}
		}

		// Fallback to template_loader if not found in referer
		if ( empty( $offer_id ) && WFOCU_Core()->template_loader ) {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();
		}

		return $offer_id;
	}
}

// Register REST API endpoints when this file is loaded
// Register directly using an anonymous function to ensure endpoint is available
$register_endpoint = function () {
	register_rest_route(
		'wfocu/v1',
		'/product-short-description/products',
		array(
			'methods'             => 'GET',
			'callback'            => function ( \WP_REST_Request $request ) {
				$class_name = 'WFOCU\Modules\ProductShortDescription\ProductShortDescription';
				if ( class_exists( $class_name ) && method_exists( $class_name, 'get_products' ) ) {
					return $class_name::get_products( $request );
				}
				return new \WP_Error( 'class_not_found', 'ProductShortDescription class not found', array( 'status' => 500 ) );
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
};

add_action( 'rest_api_init', $register_endpoint, 10 );

// Register immediately if rest_api_init has already fired
if ( did_action( 'rest_api_init' ) ) {
	$register_endpoint();
}
