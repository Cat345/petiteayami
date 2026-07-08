<?php
/**
 * OfferPrice::rest_api().
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice\OfferPriceTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait RestApiTrait {

	public static function get_products( \WP_REST_Request $request ) {
		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader ) {
			return rest_ensure_response(
				array(
					'0' => array(
						'label'          => '--No Product--',
						'isSubscription' => false,
					),
				)
			);
		}

		$offer_id = 0;

		// Try post_id parameter first
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post && 'wfocu_offer' === $post->post_type ) {
				$offer_id = $post_id;
			}
		}

		// Try to get from referer URL (Visual Builder context)
		if ( 0 === $offer_id && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

			// Try /offer/{slug} pattern
			if ( preg_match( '#/offer/([^/?]+)#', $referer, $matches ) ) {
				$offer_slug = sanitize_text_field( $matches[1] );
				$offer_post = get_page_by_path( $offer_slug, OBJECT, 'wfocu_offer' );

				if ( $offer_post && isset( $offer_post->ID ) ) {
					$offer_id = absint( $offer_post->ID );
				} else {
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

			// Try post_id or p parameter from referer URL
			if ( 0 === $offer_id ) {
				$referer_parts = wp_parse_url( $referer );
				if ( ! empty( $referer_parts['query'] ) ) {
					parse_str( $referer_parts['query'], $referer_params );
					$referer_post_id = 0;
					if ( ! empty( $referer_params['post_id'] ) ) {
						$referer_post_id = absint( $referer_params['post_id'] );
					} elseif ( ! empty( $referer_params['p'] ) ) {
						$referer_post_id = absint( $referer_params['p'] );
					}
					if ( $referer_post_id > 0 ) {
						$post = get_post( $referer_post_id );
						if ( $post && 'wfocu_offer' === $post->post_type ) {
							$offer_id = $referer_post_id;
						}
					}
				}
			}
		}

		// Fallback to template_loader
		if ( 0 === $offer_id && WFOCU_Core()->template_loader ) {
			$offer_id = absint( WFOCU_Core()->template_loader->get_offer_id() );
		}

		$response = array(
			'0' => array(
				'label'          => '--No Product--',
				'isSubscription' => false,
			),
		);

		if ( $offer_id > 0 ) {
			$template_loader           = WFOCU_Core()->template_loader;
			$template_loader->offer_id = $offer_id;
			if ( method_exists( $template_loader, 'setup_complete_offer_setup_manual' ) ) {
				$template_loader->setup_complete_offer_setup_manual( $offer_id );
			}

			$wfocu_products = $template_loader->product_data->products ?? array();
			foreach ( $wfocu_products as $key => $product ) {
				$type = isset( $product->type ) ? $product->type : '';

				// Get real prices via merge tags.
				$regular_price     = class_exists( 'WFOCU_Common' ) ? \WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $key . '"}}' ) : '';
				$offer_price       = class_exists( 'WFOCU_Common' ) ? \WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $key . '"}}' ) : '';
				$regular_price_raw = class_exists( 'WFOCU_Common' ) ? (float) \WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $key . '"}}' ) : 0;
				$sale_price_raw    = class_exists( 'WFOCU_Common' ) ? (float) \WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $key . '"}}' ) : 0;
				$signup_fee        = '';
				$recurring         = '';

				$is_subscription = in_array( $type, array( 'subscription', 'variable-subscription', 'subscription_variation' ), true );
				if ( $is_subscription && class_exists( 'WFOCU_Common' ) ) {
					$signup_fee = \WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $key . '" signup_label=""}}' );
					$recurring  = \WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $key . '" recurring_label=""}}' );
				}

				$response[ (string) $key ] = array(
					'label'           => $product->data->get_name(),
					'isSubscription'  => $is_subscription,
					'regularPrice'    => $regular_price,
					'offerPrice'      => $offer_price,
					'regularPriceRaw' => round( $regular_price_raw, 2 ),
					'offerPriceRaw'   => round( $sale_price_raw, 2 ),
					'signupFee'       => $signup_fee,
					'recurring'       => $recurring,
				);
			}
		}

		// Remove "--No Product--" when real products exist (matches D4 default behavior).
		if ( count( $response ) > 1 ) {
			unset( $response['0'] );
		}

		return rest_ensure_response( $response );
	}
}

$register_endpoint = function () {
	register_rest_route(
		'wfocu/v1',
		'/offer-price/products',
		array(
			'methods'             => 'GET',
			'callback'            => function ( \WP_REST_Request $request ) {
				$class_name = 'WFOCU\\Modules\\OfferPrice\\OfferPrice';
				if ( class_exists( $class_name ) && method_exists( $class_name, 'get_products' ) ) {
					return $class_name::get_products( $request );
				}
				return new \WP_Error( 'class_not_found', 'OfferPrice class not found', array( 'status' => 500 ) );
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
};

add_action( 'rest_api_init', $register_endpoint, 10 );
if ( did_action( 'rest_api_init' ) ) {
	$register_endpoint();
}
