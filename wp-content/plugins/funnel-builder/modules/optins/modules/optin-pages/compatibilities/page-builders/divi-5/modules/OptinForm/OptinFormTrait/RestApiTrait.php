<?php
/**
 * OptinForm::rest_api().
 *
 * @package WFOP\Modules\OptinForm
 * @since 1.0.0
 */

namespace WFOP\Modules\OptinForm\OptinFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait RestApiTrait {

	/**
	 * REST API callback to get form fields for Optin Form.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public static function get_form_fields( \WP_REST_Request $request ) {
		// Early return if WFOPP Core is not available
		if ( ! function_exists( 'WFOPP_Core' ) || ! WFOPP_Core()->optin_pages ) {
			return rest_ensure_response( array() );
		}

		// Get optin page ID from request or current context
		$optin_page_id = self::get_optin_page_id( $request );

		// Initialize response
		$response = array();

		// Get form fields if optin_page_id exists
		if ( ! empty( $optin_page_id ) && $optin_page_id > 0 ) {
			$get_fields = WFOPP_Core()->optin_pages->form_builder->get_form_fields( $optin_page_id );

			if ( is_array( $get_fields ) && count( $get_fields ) > 0 ) {
				foreach ( $get_fields as $index => $field ) {
					// Try multiple possible keys for InputName (case-insensitive)
					$input_name = '';
					if ( isset( $field['InputName'] ) ) {
						$input_name = $field['InputName'];
					} elseif ( isset( $field['inputName'] ) ) {
						$input_name = $field['inputName'];
					} elseif ( isset( $field['input_name'] ) ) {
						$input_name = $field['input_name'];
					} elseif ( isset( $field['name'] ) ) {
						$input_name = $field['name'];
					}

					// Try multiple possible keys for label
					$label = '';
					if ( isset( $field['label'] ) ) {
						$label = $field['label'];
					} elseif ( isset( $field['Label'] ) ) {
						$label = $field['Label'];
					} elseif ( isset( $field['title'] ) ) {
						$label = $field['title'];
					} elseif ( isset( $field['Title'] ) ) {
						$label = $field['Title'];
					}

					$width = isset( $field['width'] ) ? $field['width'] : 'wffn-sm-100';
					$type  = isset( $field['type'] ) ? $field['type'] : 'unknown';

					// Only skip if both are empty - if we have at least InputName, try to use it
					if ( ! empty( $input_name ) ) {
						// If label is empty, use InputName as fallback label
						if ( empty( $label ) ) {
							$label = $input_name;
						}

						$response[] = array(
							'inputName' => $input_name,
							'label'     => $label,
							'width'     => $width,
							'type'      => $type, // Include field type for proper rendering
						);
					}
				}
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get optin page ID from request or current context.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return int Optin page ID or 0 if not found.
	 */
	private static function get_optin_page_id( ?\WP_REST_Request $request = null ): int {
		$optin_page_id = 0;

		// Try to get from request parameter (post_id or slug)
		if ( $request ) {
			$post_id = $request->get_param( 'post_id' );
			$slug    = $request->get_param( 'slug' );

			if ( ! empty( $post_id ) ) {
				$optin_page_id = absint( $post_id );
				if ( $optin_page_id > 0 ) {
					return $optin_page_id;
				}
			}

			// If slug provided, try to get post ID from slug
			if ( ! empty( $slug ) && function_exists( 'WFOPP_Core' ) && WFOPP_Core()->optin_pages ) {
				$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();
				$post      = get_page_by_path( $slug, OBJECT, $post_type );
				if ( $post && isset( $post->ID ) ) {
					return absint( $post->ID );
				}
			}
		}

		// Fallback: Try to get from referer URL
		if ( empty( $optin_page_id ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

			// Try to extract post ID from referer
			if ( preg_match( '#[?&]post=(\d+)#', $referer, $matches ) ) {
				$optin_page_id = absint( $matches[1] );
				if ( $optin_page_id > 0 ) {
					return $optin_page_id;
				}
			} elseif ( preg_match( '#/post\.php\?post=(\d+)#', $referer, $matches ) ) {
				$optin_page_id = absint( $matches[1] );
				if ( $optin_page_id > 0 ) {
					return $optin_page_id;
				}
			}

			// Try to extract slug from referer URL path like /op/optin-2/ or any path structure
			if ( function_exists( 'WFOPP_Core' ) && WFOPP_Core()->optin_pages ) {
				$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();

				// Parse the referer URL
				$referer_parsed = wp_parse_url( $referer );
				$referer_path   = isset( $referer_parsed['path'] ) ? $referer_parsed['path'] : '';

				if ( $referer_path ) {
					// Try to match any path segment that might be a post slug
					// Common patterns: /op/optin-2/, /optin-2/, etc.
					$path_segments = explode( '/', trim( $referer_path, '/' ) );

					// Try each segment as a potential slug
					foreach ( $path_segments as $segment ) {
						if ( ! empty( $segment ) ) {
							$post = get_page_by_path( $segment, OBJECT, $post_type );
							if ( $post && isset( $post->ID ) ) {
								return absint( $post->ID );
							}
						}
					}
				}
			}
		}

		// Fallback: Try to get from WFOPP Core
		if ( empty( $optin_page_id ) && function_exists( 'WFOPP_Core' ) && WFOPP_Core()->optin_pages ) {
			$optin_page_id = WFOPP_Core()->optin_pages->get_optin_id();
			if ( $optin_page_id > 0 ) {
				return $optin_page_id;
			}
		}

		// Final fallback: Try to get current post ID
		if ( empty( $optin_page_id ) && function_exists( 'get_the_ID' ) ) {
			$current_id = get_the_ID();
			if ( $current_id > 0 && function_exists( 'WFOPP_Core' ) && WFOPP_Core()->optin_pages ) {
				$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();
				if ( get_post_type( $current_id ) === $post_type ) {
					return absint( $current_id );
				}
			}
		}

		return $optin_page_id;
	}
}

// Register REST API endpoints when this file is loaded
// Register directly using an anonymous function to ensure endpoint is available
$register_endpoint = function () {
	register_rest_route(
		'wfop/v1',
		'/optin-form/fields',
		array(
			'methods'             => 'GET',
			'callback'            => function ( \WP_REST_Request $request ) {
				$class_name = 'WFOP\Modules\OptinForm\OptinForm';
				if ( class_exists( $class_name ) && method_exists( $class_name, 'get_form_fields' ) ) {
					return $class_name::get_form_fields( $request );
				}
				return new \WP_Error( 'class_not_found', 'OptinForm class not found', array( 'status' => 500 ) );
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
