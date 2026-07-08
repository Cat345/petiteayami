<?php
/**
 * ProductImages::rest_api().
 *
 * @package WFOCU\Modules\ProductImages
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductImages\ProductImagesTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait RestApiTrait {

	/**
	 * Register REST API endpoints for Product Images module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rest_endpoints(): void {
		// Endpoint registration is handled at the bottom of this file
		// This method is kept for consistency but not used to avoid duplicate registration
	}

	/**
	 * REST API callback to get products for Product Images.
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

		// Build product options with image data for Visual Builder preview
		foreach ( $wfocu_products as $key => $product ) {
			$product_obj = $product->data;

			if ( $product_obj instanceof \WC_Product ) {
				// Get featured image URL for Visual Builder preview
				$featured_image_id  = $product_obj->get_image_id();
				$featured_image_url = '';
				$featured_thumb_url = '';
				if ( ! empty( $featured_image_id ) ) {
					$image_array = wp_get_attachment_image_src( $featured_image_id, 'medium' );
					$thumb_array = wp_get_attachment_image_src( $featured_image_id, 'thumbnail' );
					if ( $image_array && isset( $image_array[0] ) ) {
						$featured_image_url = $image_array[0];
					}
					if ( $thumb_array && isset( $thumb_array[0] ) ) {
						$featured_thumb_url = $thumb_array[0];
					}
				}

				// Get gallery images for slider (matching Divi 4 lines 74-88)
				$gallery_images    = array();
				$gallery_image_ids = $product_obj->get_gallery_image_ids();

				// Add featured image first
				if ( ! empty( $featured_image_id ) ) {
					$full_img  = wp_get_attachment_image_src( $featured_image_id, 'full' );
					$thumb_img = wp_get_attachment_image_src( $featured_image_id, 'thumbnail' );
					if ( $full_img && isset( $full_img[0] ) ) {
						$gallery_images[] = array(
							'id'        => $featured_image_id,
							'src'       => $full_img[0],
							'thumb_src' => $thumb_img && isset( $thumb_img[0] ) ? $thumb_img[0] : $full_img[0],
						);
					}
				}

				// Add gallery images
				if ( is_array( $gallery_image_ids ) && count( $gallery_image_ids ) > 0 ) {
					foreach ( $gallery_image_ids as $gallery_id ) {
						$full_img  = wp_get_attachment_image_src( $gallery_id, 'full' );
						$thumb_img = wp_get_attachment_image_src( $gallery_id, 'thumbnail' );
						if ( $full_img && isset( $full_img[0] ) ) {
							$gallery_images[] = array(
								'id'        => $gallery_id,
								'src'       => $full_img[0],
								'thumb_src' => $thumb_img && isset( $thumb_img[0] ) ? $thumb_img[0] : $full_img[0],
							);
						}
					}
				}

				// Add variation images if available
				if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) ) {
					// Use array_flip for faster lookup instead of nested loop
					$existing_ids = array_flip( array_column( $gallery_images, 'id' ) );
					foreach ( $product->variations_data['images'] as $var_image_id ) {
						// Skip if already added
						if ( isset( $existing_ids[ $var_image_id ] ) ) {
							continue;
						}
						$full_img  = wp_get_attachment_image_src( $var_image_id, 'full' );
						$thumb_img = wp_get_attachment_image_src( $var_image_id, 'thumbnail' );
						if ( $full_img && isset( $full_img[0] ) ) {
							$gallery_images[]              = array(
								'id'        => $var_image_id,
								'src'       => $full_img[0],
								'thumb_src' => $thumb_img && isset( $thumb_img[0] ) ? $thumb_img[0] : $full_img[0],
							);
							$existing_ids[ $var_image_id ] = count( $gallery_images ) - 1;
						}
					}
				}

				$response[ (string) $key ] = array(
					'label'         => $product_obj->get_name(),
					'featuredImage' => $featured_image_url,
					'galleryImages' => $gallery_images,
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
		'/product-images/products',
		array(
			'methods'             => 'GET',
			'callback'            => function ( \WP_REST_Request $request ) {
				$class_name = 'WFOCU\Modules\ProductImages\ProductImages';
				if ( class_exists( $class_name ) && method_exists( $class_name, 'get_products' ) ) {
					return $class_name::get_products( $request );
				}
				return new \WP_Error( 'class_not_found', 'ProductImages class not found', array( 'status' => 500 ) );
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
