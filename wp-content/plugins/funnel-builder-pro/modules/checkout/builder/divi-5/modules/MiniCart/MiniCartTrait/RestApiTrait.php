<?php
/**
 * MiniCart::REST API Trait
 *
 * @package WFACP\Modules\MiniCart
 * @since 1.0.0
 */

namespace WFACP\Modules\MiniCart\MiniCartTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFACP\Modules\MiniCart\MiniCart;

trait RestApiTrait {

	/**
	 * Register REST API routes for MiniCart module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	/**
	 * Register REST routes directly (called from within rest_api_init).
	 *
	 * @since 1.0.0
	 */
	public static function register_rest_routes_direct(): void {
		self::do_register_rest_routes();
	}

	public static function register_rest_routes(): void {
		add_action(
			'rest_api_init',
			function() {
				self::do_register_rest_routes();
			}
		);
	}

	private static function do_register_rest_routes(): void {
		// Prevent duplicate registration.
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		register_rest_route(
			'wfacp/v1',
			'/mini-cart/render',
			[
				'methods'             => 'POST',
				'callback'            => [ MiniCart::class, 'rest_render_callback' ],
				'permission_callback' => function() {
					// Allow in Visual Builder context
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'attrs'   => [
						'required' => false,
						'type'     => 'object',
						'default'  => [],
					],
					'id'      => [
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					],
					'post_id' => [
						'required' => false,
						'type'     => 'integer',
						'default'  => 0,
					],
					'_t'      => [
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					],
					'_rid'     => [
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					],
				],
			]
		);
	}

	/**
	 * REST API callback to render MiniCart HTML.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_render_callback( \WP_REST_Request $request ) {
		// Try to get params from JSON body first (POST requests)
		$json_params = $request->get_json_params();
		if ( ! empty( $json_params ) ) {
			$attrs   = $json_params['attrs'] ?? [];
			$id      = $json_params['id'] ?? '';
			$post_id = $json_params['post_id'] ?? 0;
		} else {
			// Fallback to regular params
			$attrs   = $request->get_param( 'attrs' ) ?? [];
			$id      = $request->get_param( 'id' ) ?? '';
			$post_id = $request->get_param( 'post_id' ) ?? 0;
		}

		// CRITICAL: Initialize template loader with a VALID checkout post ID.
		// Divi Visual Builder often sends edited page ID (post/page), not checkout CPT ID.
		$wfacp_post_id = 0;

		$resolve_checkout_post_id = static function( array $candidates ): int {
			foreach ( $candidates as $candidate ) {
				$candidate_id = absint( $candidate );
				if ( $candidate_id <= 0 ) {
					continue;
				}

				$post = get_post( $candidate_id );
				if ( $post && $post->post_type === 'wfacp_checkout' ) {
					return $candidate_id;
				}
			}

			return 0;
		};

		$candidates = [];

		// Prioritize explicit checkout context from Divi.
		if ( isset( $_REQUEST['et_wfacp_id'] ) ) {
			$candidates[] = wp_unslash( $_REQUEST['et_wfacp_id'] );
		}
		// Then request payload values.
		$candidates[] = $post_id;
		if ( isset( $_REQUEST['post_id'] ) ) {
			$candidates[] = wp_unslash( $_REQUEST['post_id'] );
		}
		if ( isset( $_REQUEST['et_post_id'] ) ) {
			$candidates[] = wp_unslash( $_REQUEST['et_post_id'] );
		}

		// Try extracting IDs from referrer URL.
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			if ( preg_match( '/[?&]et_wfacp_id=(\d+)/', $referer, $matches ) ) {
				$candidates[] = $matches[1];
			}
			if ( preg_match( '/[?&](?:post|post_id|et_post_id|p)=(\d+)/', $referer, $matches ) ) {
				$candidates[] = $matches[1];
			}
		}

		$wfacp_post_id = $resolve_checkout_post_id( $candidates );

		// Fallback: get latest published checkout for preview.
		if ( $wfacp_post_id === 0 ) {
			$fallback_posts = get_posts( [
				'post_type'      => 'wfacp_checkout',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );

			if ( ! empty( $fallback_posts ) ) {
				$wfacp_post_id = absint( $fallback_posts[0]->ID );
			}
		}

		// CRITICAL: Initialize WooCommerce before template initialization
		// In REST API context, WooCommerce might not be fully initialized
		if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
			// Ensure WooCommerce is loaded
			if ( ! did_action( 'woocommerce_init' ) ) {
				do_action( 'woocommerce_init' );
			}

			// Ensure cart is initialized
			if ( ! WC()->cart ) {
				wc_load_cart();
			}

			// Ensure session is started
			if ( ! WC()->session ) {
				WC()->initialize_session();
			}
		}

		// Initialize template if we have a valid post ID
		if ( $wfacp_post_id > 0 ) {
			// Verify it's a WFACP post type
			$post = get_post( $wfacp_post_id );
			if ( $post && $post->post_type === 'wfacp_checkout' ) {
				// Set the ID and initialize template loader
				if ( class_exists( '\WFACP_Common' ) ) {
					\WFACP_Common::set_id( $wfacp_post_id );

					// Initialize template loader
					if ( class_exists( '\WFACP_Core' ) && ! is_null( WFACP_Core()->template_loader ) ) {
						WFACP_Core()->template_loader->load_template( $wfacp_post_id );
					}
				}
			}
		}

		// Ensure attrs is an array
		if ( ! is_array( $attrs ) ) {
			$attrs = [];
		}

		// Create parsed block array
		$parsed_block = [
			'blockName'  => 'wfacp/mini-cart',
			'attrs'      => $attrs,
			'innerHTML'  => '',
			'innerContent' => [],
			'id'         => $id ?: 'wfacp_order_summary_widget',
			'orderIndex' => 0,
		];

		// Create block type object
		$block_type = (object) [
			'name'     => 'wfacp/mini-cart',
			'category' => 'module',
		];

		// Create a proper WP_Block object
		// WP_Block constructor: __construct( array $parsed_block, array $available_context = [] )
		$block = new \WP_Block( $parsed_block );

		// Set block_type property using reflection (since it's not directly settable)
		$reflection = new \ReflectionClass( $block );
		$property = $reflection->getProperty( 'block_type' );
		$property->setAccessible( true );
		$property->setValue( $block, $block_type );

		// Create mock elements (not used in render_callback but required)
		$elements = null;

		try {
			// Call the render callback directly using the class method
			$html = MiniCart::render_callback(
				$attrs,
				'',
				$block,
				$elements,
				[]
			);

			return new \WP_REST_Response(
				[
					'success' => true,
					'html'    => $html,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'render_failed',
				'Failed to render MiniCart: ' . $e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}
