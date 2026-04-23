<?php
/**
 * VariationSelector::rest_api().
 *
 * @package WFOCU\Modules\VariationSelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\VariationSelector\VariationSelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait RestApiTrait {

	/**
	 * Register REST API endpoints for Variation Selector module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rest_endpoints(): void {
		// Register endpoint to fetch products for the current offer
		register_rest_route(
			'wfocu/v1',
			'/variation-selector/products',
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
	 * REST API callback to get products for Variation Selector.
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

		// Build product options - ONLY include variable products
		foreach ( $wfocu_products as $key => $product ) {
			$product_type = '';
			if ( isset( $product->type ) ) {
				$product_type = $product->type;
			} elseif ( isset( $product->data ) && $product->data instanceof \WC_Product ) {
				$product_type = $product->data->get_type();
			}

			if ( in_array( $product_type, array( 'variable', 'variable-subscription' ), true ) ) {
				$product_obj = $product->data;
				$attributes  = array();

				if ( $product_obj instanceof \WC_Product_Variable ) {
					$variation_attributes = $product_obj->get_variation_attributes();
					foreach ( $variation_attributes as $attr_name => $options ) {
						$label        = wc_attribute_label( $attr_name, $product_obj );
						$slug         = sanitize_title( $attr_name );
						$attributes[] = array(
							'name'    => $slug,
							'label'   => $label,
							'options' => array_values( $options ),
						);
					}
				}

				$response[ (string) $key ] = array(
					'label'      => $product_obj->get_name(),
					'isVariable' => true,
					'attributes' => $attributes,
				);
			} else {
				$response[ (string) $key ] = array(
					'label'      => $product->data->get_name(),
					'isVariable' => false,
				);
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get offer ID from referer URL or template loader (matching ProductTitle exactly).
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
							'post_status'    => 'any',
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
		'/variation-selector/products',
		array(
			'methods'             => 'GET',
			'callback'            => function ( \WP_REST_Request $request ) {
				// Lazy check - class might be loaded by the time this callback is called
				$class_name = 'WFOCU\Modules\VariationSelector\VariationSelector';
				if ( class_exists( $class_name ) && method_exists( $class_name, 'get_products' ) ) {
					return $class_name::get_products( $request );
				}
				// If class not available, return default response (matching ProductTitle behavior)
				// This ensures the endpoint always responds, even if class isn't loaded yet
				return rest_ensure_response(
					array(
						'0' => array(
							'label' => '--No Product--',
						),
					)
				);
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
