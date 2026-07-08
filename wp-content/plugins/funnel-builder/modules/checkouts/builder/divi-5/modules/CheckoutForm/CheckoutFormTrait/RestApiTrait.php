<?php
/**
 * CheckoutForm::REST API Trait
 *
 * @package WFACP\Modules\CheckoutForm
 * @since 1.0.0
 */

namespace WFACP\Modules\CheckoutForm\CheckoutFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WFACP\Modules\CheckoutForm\CheckoutForm;

trait RestApiTrait {

	/**
	 * Register REST API routes for CheckoutForm module.
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
			function () {
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
			'/checkout-form/render',
			array(
				'methods'             => 'POST',
				'callback'            => array( CheckoutForm::class, 'rest_render_callback' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'attrs'   => array(
						'required'          => false,
						'type'              => 'object',
						'default'           => array(),
						'sanitize_callback' => function ( $value ) {
							// SECURITY: Ensure attrs is an array
							return is_array( $value ) ? $value : array();
						},
					),
					'id'      => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							// SECURITY: Ensure post_id is non-negative
							return is_numeric( $value ) && $value >= 0;
						},
					),
					'_t'      => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'_rid'    => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

				register_rest_route(
					'wfacp/v1',
					'/checkout-form/fields',
					array(
						'methods'             => 'GET',
						'callback'            => array( CheckoutForm::class, 'rest_get_field_structure' ),
						'permission_callback' => function () {
							return current_user_can( 'manage_woocommerce' );
						},
						'args'                => array(
							'post_id' => array(
								'required'          => false,
								'type'              => 'integer',
								'default'           => 0,
								'sanitize_callback' => 'absint',
								'validate_callback' => function ( $value ) {
									// SECURITY: Ensure post_id is non-negative
									return is_numeric( $value ) && $value >= 0;
								},
							),
						),
					)
				);
	}

	/**
	 * REST API callback to render CheckoutForm HTML.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_render_callback( \WP_REST_Request $request ) {
		// SECURITY: Validate and sanitize input parameters
		// Try to get params from JSON body first (POST requests)
		$json_params = $request->get_json_params();
		if ( ! empty( $json_params ) && is_array( $json_params ) ) {
			$attrs   = is_array( $json_params['attrs'] ?? null ) ? $json_params['attrs'] : array();
			$id      = sanitize_text_field( $json_params['id'] ?? '' );
			$post_id = isset( $json_params['post_id'] ) ? absint( $json_params['post_id'] ) : 0;
		} else {
			// Fallback to regular params
			$attrs   = is_array( $request->get_param( 'attrs' ) ) ? $request->get_param( 'attrs' ) : array();
			$id      = sanitize_text_field( $request->get_param( 'id' ) ?? '' );
			$post_id = absint( $request->get_param( 'post_id' ) ?? 0 );
		}

		// CRITICAL: Initialize template loader with a VALID checkout post ID.
		// Divi Visual Builder often sends the edited page ID (post/page), not the checkout CPT ID.
		// Resolve candidate IDs and only accept IDs of post_type `wfacp_checkout`.
		$wfacp_post_id = 0;

		$resolve_checkout_post_id = static function ( array $candidates ): int {
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

		$candidates = array();

		// Prioritize explicit checkout context from Divi.
		if ( isset( $_REQUEST['et_wfacp_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidates[] = wp_unslash( $_REQUEST['et_wfacp_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		// Then request payload values.
		$candidates[] = $post_id;
		if ( isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidates[] = wp_unslash( $_REQUEST['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( isset( $_REQUEST['et_post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidates[] = wp_unslash( $_REQUEST['et_post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

		// Method 6: Fallback - get any published WFACP checkout page for preview
		// SCALABILITY: Only use fallback in Visual Builder context to avoid unnecessary queries
		if ( $wfacp_post_id === 0 ) {
			$fallback_posts = get_posts(
				array(
					'post_type'              => 'wfacp_checkout',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'no_found_rows'          => true, // SCALABILITY: Skip count query for better performance
					'update_post_meta_cache' => false, // SCALABILITY: Skip meta cache for better performance
					'update_post_term_cache' => false, // SCALABILITY: Skip term cache for better performance
				)
			);

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
		// SECURITY: Verify post exists, is correct type, and user has access
		if ( $wfacp_post_id > 0 ) {
			// Verify it's a WFACP post type and user can access it
			$post = get_post( $wfacp_post_id );
			if ( $post && $post->post_type === 'wfacp_checkout' ) {
				// SECURITY: Check if user can edit this post (for Visual Builder)
				if ( current_user_can( 'edit_post', $wfacp_post_id ) || get_post_status( $wfacp_post_id ) === 'publish' ) {
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
		}

		// Ensure attrs is an array
		if ( ! is_array( $attrs ) ) {
			$attrs = array();
		}

		// Create parsed block array
		// SECURITY: Sanitize block ID
		$block_id     = ! empty( $id ) ? sanitize_text_field( $id ) : 'wfacp_checkout_form_widget';
		$parsed_block = array(
			'blockName'    => 'wfacp/checkout-form',
			'attrs'        => $attrs,
			'innerHTML'    => '',
			'innerContent' => array(),
			'id'           => $block_id,
			'orderIndex'   => 0,
		);

		// Create block type object
		$block_type = (object) array(
			'name'     => 'wfacp/checkout-form',
			'category' => 'module',
		);

		// Create a proper WP_Block object
		$block = new \WP_Block( $parsed_block );

		// Set block_type property using reflection (since it's not directly settable)
		$reflection = new \ReflectionClass( $block );
		$property   = $reflection->getProperty( 'block_type' );
		$property->setAccessible( true );
		$property->setValue( $block, $block_type );

		// Create mock elements (not used in render_callback but required)
		$elements = null;

		try {
			// Call the render callback directly using the class method
			$html = CheckoutForm::render_callback(
				$attrs,
				'',
				$block,
				$elements,
				array()
			);

			// CRITICAL: Add cache-busting headers to prevent Visual Builder preview from using cached responses
			// This ensures that when attributes change, the preview updates immediately
			$response = new \WP_REST_Response(
				array(
					'success' => true,
					'html'    => $html,
				),
				200
			);

			// Prevent caching of the preview response
			$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Expires', '0' );

			return $response;
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'render_failed',
				'Failed to render CheckoutForm',
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * REST API callback to get field structure for dynamic settings.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_get_field_structure( \WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' ) ?? 0;

		// Try to get post ID from various sources (same as render callback)
		// SECURITY: Sanitize all inputs
		$wfacp_post_id = 0;
		if ( $post_id > 0 ) {
			$wfacp_post_id = absint( $post_id );
		} elseif ( isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$wfacp_post_id = absint( wp_unslash( $_REQUEST['post_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_REQUEST['et_post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$wfacp_post_id = absint( wp_unslash( $_REQUEST['et_post_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_REQUEST['et_wfacp_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$wfacp_post_id = absint( wp_unslash( $_REQUEST['et_wfacp_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		// Fallback to any published checkout page
		// SCALABILITY: Only use fallback in Visual Builder context
		if ( $wfacp_post_id === 0 ) {
			$is_visual_builder = false;
			if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
				$is_visual_builder = true;
			} elseif ( defined( 'ET_FB_ENABLED' ) && ET_FB_ENABLED ) {
				$is_visual_builder = true;
			} elseif ( isset( $_GET['et_fb'] ) && sanitize_text_field( wp_unslash( $_GET['et_fb'] ) ) === '1' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$is_visual_builder = true;
			}

			if ( $is_visual_builder ) {
				$fallback_posts = get_posts(
					array(
						'post_type'              => 'wfacp_checkout',
						'post_status'            => 'publish',
						'posts_per_page'         => 1,
						'orderby'                => 'date',
						'order'                  => 'DESC',
						'no_found_rows'          => true, // SCALABILITY: Skip count query
						'update_post_meta_cache' => false, // SCALABILITY: Skip meta cache
						'update_post_term_cache' => false, // SCALABILITY: Skip term cache
					)
				);

				if ( ! empty( $fallback_posts ) ) {
					$wfacp_post_id = absint( $fallback_posts[0]->ID );
				}
			}
		}

		if ( $wfacp_post_id === 0 ) {
			return new \WP_Error(
				'no_template',
				'No checkout template found',
				array( 'status' => 404 )
			);
		}

		// SECURITY: Verify post exists and user has access before initializing template
		$post = get_post( $wfacp_post_id );
		if ( ! $post || $post->post_type !== 'wfacp_checkout' ) {
			return new \WP_Error(
				'invalid_post',
				'Invalid checkout template',
				array( 'status' => 404 )
			);
		}

		// SECURITY: Check if user can access this post
		if ( ! current_user_can( 'edit_post', $wfacp_post_id ) && get_post_status( $wfacp_post_id ) !== 'publish' ) {
			return new \WP_Error(
				'access_denied',
				'Access denied',
				array( 'status' => 403 )
			);
		}

		// Initialize template — must call load_template() so that set_data()
		// populates fieldsets with the user's saved section names/layout.
		if ( class_exists( '\WFACP_Common' ) ) {
			\WFACP_Common::set_id( $wfacp_post_id );

			if ( class_exists( '\WFACP_Core' ) && ! is_null( WFACP_Core()->template_loader ) ) {
				WFACP_Core()->template_loader->load_template( $wfacp_post_id );
			}
		}

		$template = \wfacp_template();

		if ( ! $template ) {
			return new \WP_Error(
				'no_template',
				'Template not found',
				array( 'status' => 404 )
			);
		}

		// Get field structure from template
		// SCALABILITY: Consider caching this data if it doesn't change frequently
		try {
			$fieldsets = $template->get_fieldsets();
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'fieldsets_error',
				'Failed to get fieldsets: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}

		$step_count    = $template->get_step_count();
		$template_slug = $template->get_slug();

		// Build sections array with default field classes (matching Divi 4 logic)
		// SECURITY: Ensure all data is safe (fieldsets come from template, should be safe)
		$template_cls = $template->get_template_fields_class();
		$default_cls  = $template->default_css_class();

		$sections = array();
		if ( is_array( $fieldsets ) ) {
			// Matching Divi 4 structure: foreach ( $steps as $step_key => $fieldsets )
			// Then: foreach ( $fieldsets as $section_key => $section_data )
			// So fieldsets[step_key] = array of sections, where each section has 'fields' key
			foreach ( $fieldsets as $step_key => $step_fieldsets ) {
				if ( ! is_array( $step_fieldsets ) ) {
					continue;
				}

				// $step_fieldsets is an array of sections (matching Divi 4: foreach ( $fieldsets as $section_key => $section_data ) )
				foreach ( $step_fieldsets as $section_key => $section ) {
					if ( ! is_array( $section ) ) {
						continue;
					}

					// Skip sections without fields (matching Divi 4 logic)
					if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
						continue;
					}

						// Process fields and add default class information (matching Divi 4 register_fields logic)
						$processed_fields = array();
					if ( is_array( $section['fields'] ?? array() ) ) {
						foreach ( $section['fields'] as $field ) {
							if ( ! is_array( $field ) || ! isset( $field['id'] ) ) {
								continue;
							}

							$field_key  = $field['id'];
							$field_data = $field;

							// Get default class (matching Divi 4: $template_cls[ $field_key ]['class'] or $default_cls['class'])
							if ( isset( $template_cls[ $field_key ] ) && isset( $template_cls[ $field_key ]['class'] ) ) {
								$field_data['default_class'] = $template_cls[ $field_key ]['class'];
							} else {
								$field_data['default_class'] = $default_cls['class'] ?? 'wfacp-col-full';
							}

							// Override for HTML fields (matching Divi 4 logic)
							if ( isset( $field['type'] ) && 'wfacp_html' === $field['type'] ) {
								$field_data['default_class'] = 'wfacp-col-full';
							}

							$processed_fields[] = $field_data;
						}
					}

					$sections[] = array(
						'step_key'    => sanitize_key( $step_key ),
						'section_key' => sanitize_key( $section_key ),
						'name'        => sanitize_text_field( $section['name'] ?? '' ),
						'fields'      => $processed_fields,
					);
				}
			}
		}

		// Get class options (for field class dropdowns)
		$class_options = array();
		if ( class_exists( '\WFACP_Common' ) ) {
			// Get from filter or default options
			$default_class_options = array(
				'wfacp-col-full'       => 'Full',
				'wfacp-col-left-half'  => 'One Half',
				'wfacp-col-left-third' => 'One Third',
				'wfacp-col-two-third'  => 'Two Third',
			);
			try {
				$class_options = apply_filters( 'wfacp_widget_fields_classes', $default_class_options, array(), $default_class_options );
			} catch ( \Throwable $e ) {
				$class_options = $default_class_options;
			}
			// SECURITY: Ensure filter output is array
			if ( ! is_array( $class_options ) ) {
				$class_options = $default_class_options;
			}
		}

		// SECURITY: Sanitize template_slug
		$template_slug = sanitize_text_field( $template_slug ?? '' );

		$response_data = array(
			'template_slug'   => $template_slug,
			'step_count'      => absint( $step_count ),
			'sections'        => $sections,
			'excluded_fields' => class_exists( '\WFACP_Common' ) ? \WFACP_Common::get_html_excluded_field() : array(),
			'class_options'   => $class_options,
		);

		return new \WP_REST_Response( $response_data, 200 );
	}
}
