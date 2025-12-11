<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFACP_REST_Funnels
 *
 * * @extends WP_REST_Controller
 */
if ( ! class_exists( 'WFACP_REST_Funnels' ) ) {
	class WFACP_REST_Funnels extends WP_REST_Controller {

		public static $_instance = null;

		protected $namespace = 'wfacp-admin';

		protected $rest_base = 'wfacp';

		protected $rest_base_id = 'wfacp/(?P<wfacp_id>[\d]+)/';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function sanitize_custom( $data ) {
			$data = json_decode( $data, true );

			return bwf_clean( $data );
		}

		public function register_routes() {

			// Register routes to List Checkout.
			register_rest_route( $this->namespace, '/' . $this->rest_base, array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Checkout id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'wfacp_get_posts' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'wfacp_create_page' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes to List Checkout export.
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/export', array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Checkout id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'wfacp_page_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register route for Checkout Single Export.
			register_rest_route( $this->namespace, '/' . $this->rest_base_id . 'export', array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Checkout id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'wfacp_export_single' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes for Checkout.
			register_rest_route( $this->namespace, '/' . $this->rest_base_id, array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Checkout id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'wfacp_get_page' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wfacp_remove_page' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register route to duplicate Checkout.
			register_rest_route( $this->namespace, '/' . $this->rest_base_id . 'duplicate', array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Checkout id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wfocu_duplicate_single' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/activate-plugin', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_plugin' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'status' => array(
							'description'       => __( 'Check plugin status', 'woofunnels-aero-checkout' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'slug'   => array(
							'description'       => __( 'Check plugin slug', 'woofunnels-aero-checkout' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'init'   => array(
							'description'       => __( 'Check builder status', 'woofunnels-aero-checkout' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Route to Search Pages.
			register_rest_route( $this->namespace, '/funnels/pages/search', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_pages' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => array(
						'term' => array(
							'description'       => __( 'search term', 'woofunnels-aero-checkout' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				)
			) );

			// Search for WooCommerce Products.
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/product-search', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'product_list' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => array(
						'term' => array(
							'description'       => __( 'Product name', 'woofunnels-aero-checkout' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => true,
						),
					),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/products' . '/(?P<wfacp_id>[\d]+)', array(
				'args' => array(
					'wfacp_id' => array(
						'description' => __( 'Current step id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'add_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_page_products' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_product' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => $this->get_delete_steps_collection(),
				),

				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes to save Step State.
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/save_state' . '/(?P<wfacp_id>[\d]+)', array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Current step id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
					'options'  => array(
						'description' => __( 'Step state.', 'woofunnels-aero-checkout' ),
						'type'        => 'string',
						'required'    => true
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_state' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes for Step Settings.
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings' . '/(?P<wfacp_id>[\d]+)', array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Current step id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_customsettings' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_customsettings' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Register routes for form fields.
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/form_fields' . '/(?P<wfacp_id>[\d]+)', array(
				'args'   => array(
					'wfacp_id' => array(
						'description' => __( 'Current step id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_form_fields' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_form_fields' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			) );

			// Routes for WFACP Optimizations.
			register_rest_route( $this->namespace, '/optimizations' . '/(?P<id>[\d]+)', array(
				'args' => array(
					'id' => array(
						'description' => __( 'Current step id.', 'woofunnels-aero-checkout' ),
						'type'        => 'integer',
						'required'    => true
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'checkout_optimizations' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' )
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_optimizations' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );
		}

		public function get_read_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'read' );
		}

		public function get_write_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
		}

		// Remove product from Checkout.
		public function remove_product( $options, $wfacp_id ) {
			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			if ( absint( $wfacp_id ) > 0 && ! empty( $options ) ) {

				$posted_data = $this->sanitize_custom( $options );

				$wfacp_id         = absint( $wfacp_id );
				$product_key      = !empty($posted_data['product_key'])?trim( $posted_data['product_key'] ):'';
				$existing_product = WFACP_Common::get_page_product( $wfacp_id );
				if ( isset( $existing_product[ $product_key ] ) ) {
					unset( $existing_product[ $product_key ] );
					WFACP_Common::update_page_product( $wfacp_id, $existing_product );
					$resp['msg']     = __( 'Product removed from checkout page' );
					$resp['success'] = true;
				}
			}

			return $resp;
		}

		// Save products for Checkout.
		public function save_products( $options, $wfacp_id ) {
			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			if ( absint( $wfacp_id ) > 0 && ! empty( $options ) ) {

				$posted_data = $this->sanitize_custom( $options );

				$products = $posted_data['products'];
				$wfacp_id = absint( $wfacp_id );
				$settings = isset( $posted_data['settings'] ) ? $posted_data['settings'] : [];
				foreach ( $products as $key => $val ) {
					if ( isset( $products[ $key ]['variable'] ) ) {

						$pro                = WFACP_Common::wc_get_product( $products[ $key ]['id'], $key );
						$is_found_variation = WFACP_Common::get_default_variation( $pro );

						if ( count( $is_found_variation ) > 0 ) {
							$products[ $key ]['default_variation']      = $is_found_variation['variation_id'];
							$products[ $key ]['default_variation_attr'] = $is_found_variation['attributes'];
						}
					}
					$products[ $key ] = WFACP_Common::remove_product_keys( $products[ $key ] );
				}

				$old_settings = WFACP_Common::get_page_product_settings( $wfacp_id );
				if ( isset( $old_settings['add_to_cart_setting'] ) && $old_settings['add_to_cart_setting'] !== $posted_data['settings']['add_to_cart_setting'] ) {
					//unset default products
					$s = get_post_meta( $wfacp_id, '_wfacp_product_switcher_setting', true );
					if ( ! empty( $s ) ) {
						$s['default_products'] = [];
						update_post_meta( $wfacp_id, '_wfacp_product_switcher_setting', $s );
					}
				}
				WFACP_Common::update_page_product( $wfacp_id, $products );
				WFACP_Common::update_page_product_setting( $wfacp_id, $settings );
				$resp['success'] = true;
			}

			return $resp;
		}

		public function get_post_table_data( $ids = false, $all = false, $posted_data = array() ) {

			$args = array(
				'post_type'      => WFACP_Common::get_post_type_slug(),
				'post_status'    => array( 'publish', WFACP_SLUG . '-disabled' ),
				'posts_per_page' => WFACP_Common::posts_per_page(), //phpcs:ignore  WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			);
			$args = apply_filters( 'wfacp_add_control_meta_query', $args );

			if ( isset( $posted_data['paged'] ) && $posted_data['paged'] > 0 ) {  // phpcs:ignore WordPress.Security.NonceVerification
				$args['paged'] = absint( $posted_data['paged'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( isset( $posted_data['order'] ) && '' !== $posted_data['order'] && isset( $posted_data['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args['orderby'] = wc_clean( $posted_data['orderby'] ); // phpcs:ignore WordPress.Security.NonceVerification
				$args['order']   = wc_clean( $posted_data['order'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			if ( isset( $posted_data['s'] ) && '' !== $posted_data['s'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args['s'] = wc_clean( $posted_data['s'] ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			if ( isset( $posted_data['status'] ) && '' !== $posted_data['status'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( 'active' === wc_clean( $posted_data['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$args['post_status'] = 'publish';
				} elseif ( 'all' === $posted_data['status'] ) { // phpcs:ignore WordPress.Security.NonceVerification
					$args['post_status'] = array( 'publish', WFACP_SLUG . '-disabled' );
				} else {
					$args['post_status'] = WFACP_SLUG . '-disabled';
				}
			} else {
				$args['post_status'] = array( 'publish', WFACP_SLUG . '-disabled' );
			}

			$q = new WP_Query( $args );

			$found_posts = array( 'found_posts' => $q->found_posts );
			$items       = array();
			if ( $q->have_posts() ) {
				while ( $q->have_posts() ) {

					$q->the_post();
					global $post;

					$permalink = get_the_permalink( $post->ID );
					$view      = $permalink;

					$status      = get_post_status( get_the_ID() );
					$priority    = $post->menu_order;
					$funnel_url  = add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'offers',
						'edit'    => get_the_ID(),
					), admin_url( 'admin.php' ) );
					$row_actions = array();

					$row_actions['edit'] = array(
						'action' => 'edit',
						'text'   => __( 'Edit', 'woofunnels-aero-checkout' ),
						'link'   => $funnel_url,
						'attrs'  => '',
					);

					$row_actions['view'] = array(
						'action' => 'view',
						'text'   => __( 'View', 'woofunnels-aero-checkout' ),
						'link'   => $view,
						'attrs'  => 'target="_blank"',
					);

					$row_actions['duplicate'] = array(
						'action' => 'duplicate',
						'text'   => __( 'Duplicate', 'woofunnels-aero-checkout' ),
						'link'   => 'javascript:void(0);',
						'attrs'  => 'class="wfacp-duplicate" data-funnel-id="' . get_the_ID() . '" id="wfacp_duplicate_' . get_the_ID() . '"',
					);

					$row_actions['export'] = array(
						'action' => 'export',
						'text'   => __( 'Export', 'woofunnels-aero-checkout' ),
						'link'   => wp_nonce_url( admin_url( 'admin.php?action=wfacp-export&id=' . get_the_ID() ), 'wfacp-export' ),
						'attrs'  => '',
					);

					$row_actions['delete'] = array(
						'action' => 'delete',
						'text'   => __( 'Delete', 'woofunnels-aero-checkout' ),
						'link'   => get_delete_post_link( get_the_ID(), '', true ),
						'attrs'  => '',
					);
					$items[]               = array(
						'id'           => get_the_ID(),
						'post_title'   => get_the_title(),
						'post_content' => get_the_content(),
						'status'       => $status,
						'row_actions'  => $row_actions,
						'priority'     => $priority,
					);
				}
			}
			$found_posts['items'] = $items;

			return $found_posts;
		}

		public function wfacp_get_posts( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );
			$resp['upsells'] = array();

			$status  = $request->get_param( 'status' ) ? $request->get_param( 'status' ) : '';
			$orderby = $request->get_param( 'orderby' ) ? $request->get_param( 'orderby' ) : '';
			$order   = $request->get_param( 'order' ) ? $request->get_param( 'order' ) : '';

			$posted_data            = array();
			$posted_data['status']  = bwf_clean( $status );
			$posted_data['orderby'] = bwf_clean( $orderby );
			$posted_data['order']   = bwf_clean( $order );

			$posts = $this->get_post_table_data( false, true, $posted_data );

			if ( count( $posts ) ) {
				$resp['upsells'] = $posts;
				$resp['success'] = true;
				$resp['msg']     = __( 'Funnels Loaded', 'woofunnels-aero-checkout' );
			}

			return rest_ensure_response( $resp );
		}

		// Get Page Localize Data.
		public function wfacp_get_page( WP_REST_Request $request ) {
			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );
			$resp['page']    = array();

			$wfacp_id = $request->get_param( 'wfacp_id' );

			if ( absint( $wfacp_id ) > 0 && class_exists( 'WFACP_Admin' ) ) {

				$page_data = get_post( $wfacp_id );

				if ( ! empty( $page_data ) ) {
					$resp['success'] = true;
					$resp['msg']     = __( 'Page Loaded', 'woofunnels-aero' );
					$resp['page']    = $page_data;
				}
			}

			return rest_ensure_response( $resp );
		}

		// Remove Checkout Page.
		public function wfacp_remove_page( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			$wfacp_id = $request->get_param( 'wfacp_id' );

			if ( absint( $wfacp_id ) > 0 ) {
				// Delete Checkout Page Completely.
				$wfacp_id = absint( $wfacp_id );
				wp_delete_post( $wfacp_id, true );
				delete_option( WFACP_SLUG . '_c_' . $wfacp_id );

				$resp['success'] = true;
				$resp['msg']     = __( 'Checkout Deleted', 'woofunnels-aero-checkout' );

			}

			return rest_ensure_response( $resp );
		}

		// Create Checkout Page.
		public function wfacp_create_page( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			$wfacp_id = $request->get_param( 'wfacp_id' );
			$options  = $request->get_param( 'data' );

			if ( absint( $wfacp_id ) > 0 && ! empty( $options ) ) {

				$posted_data = $this->sanitize_custom( $options );
				// Create Checkout Page.
				if ( isset( $posted_data['wfacp_name'] ) && $posted_data['wfacp_name'] !== '' ) {
					$post                = array();
					$post['post_title']  = $_POST['wfacp_name'];
					$post['post_type']   = WFACP_Common::get_post_type_slug();
					$post['post_status'] = 'publish';
					$post['post_name']   = isset( $posted_data['post_name'] ) ? $posted_data['post_name'] : $posted_data['post_title'];
					$post_description    = isset( $posted_data['post_content'] ) ? $posted_data['post_content'] : $posted_data['post_content'];

					if ( ! empty( $post ) ) {

						if ( absint( $wfacp_id ) > 0 ) {
							$wfacp_id = absint( $wfacp_id );

							$status = wp_update_post( [
								'ID'         => $wfacp_id,
								'post_title' => $post['post_title'],
								'post_name'  => $post['post_name'],
							] );

							if ( ! is_wp_error( $status ) ) {
								update_post_meta( $wfacp_id, '_post_description', $post_description );
								$resp['status']       = true;
								$resp['new_url']      = get_the_permalink( $wfacp_id );
								$resp['redirect_url'] = '#';
								$resp['msg']          = __( 'Checkout Page Successfully Update', 'woofunnels-aero-checkout' );
							}

							return rest_ensure_response( $resp );
						}
						$wfacp_id = wp_insert_post( $post );

						if ( $wfacp_id !== 0 && ! is_wp_error( $wfacp_id ) ) {

							$resp['success']      = true;
							$resp['redirect_url'] = add_query_arg( array(
								'page'     => 'wfacp',
								'section'  => 'design',
								'wfacp_id' => $wfacp_id,
							), admin_url( 'admin.php' ) );
							$resp['msg']          = __( 'Checkout Page Successfully Created', 'woofunnels-aero-checkout' );
							update_post_meta( $wfacp_id, '_wfacp_version', WFACP_VERSION );
							update_post_meta( $wfacp_id, '_post_description', $post_description );
							update_post_meta( $wfacp_id, '_wp_page_template', 'default' );

						}
					}
				}
			}

			return rest_ensure_response( $resp );
		}

		// Export Single Checkout Page.
		public function wfacp_page_export( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			if ( class_exists( 'WFACP_Exporter' ) ) {

				$posted_data['wfacp-action'] = 'export';

				// Export Checkout.
				WFACP_Exporter::get_instance()->maybe_export( $posted_data );

				$resp['success'] = true;
				$resp['msg']     = __( 'Checkout Exported', 'woofunnels-aero-checkout' );

			}

			return rest_ensure_response( $resp );
		}

		// Export Single Checkout Page.
		public function wfacp_export_single( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			$wfacp_id = $request->get_param( 'wfacp_id' );

			if ( absint( $wfacp_id ) > 0 && class_exists( 'WFACP_Exporter' ) ) {

				$posted_data['wfacp-action'] = 'export';
				$posted_data['id']           = $wfacp_id;

				// Export Checkout.
				WFACP_Exporter::get_instance()->maybe_export_single( $posted_data );

				$resp['success'] = true;
				$resp['msg']     = __( 'Checkout Exported', 'woofunnels-aero-checkout' );

			}

			return rest_ensure_response( $resp );
		}

		// Duplicate Checkout Page.
		public function wfacp_duplicate_single( WP_REST_Request $request ) {

			$resp            = array();
			$resp['success'] = false;
			$resp['msg']     = __( 'Failed', 'woofunnels-aero-checkout' );

			$wfacp_id = $request->get_param( 'wfacp_id' );

			if ( absint( $wfacp_id ) > 0 && class_exists( 'WFACP_Exporter' ) ) {

				// Duplicate Checkout Page.
				$wfacp_id = absint( $wfacp_id );
				WFACP_Common::make_duplicate( $wfacp_id );

				$resp['success'] = true;
				$resp['msg']     = __( 'Checkout Duplicated', 'woofunnels-aero-checkout' );

			}

			return rest_ensure_response( $resp );
		}

		public function get_templates() {
			$resp = array();

			$resp['all_builder'] = array(
				'funnel'      => [
					'elementor' => 'Elementor',
					'divi'      => 'Divi',
					'gutenberg' => 'Block Editor',
					'oxy'       => 'Oxygen Classic',
					'wp_editor' => 'Other'
				],
				'wc_checkout' => [
					'elementor'  => 'Elementor',
					'divi'       => 'Divi',
					'gutenberg'  => 'Block Editor',
					'oxy'        => 'Oxygen Classic',
					'customizer' => 'Customizer', //pre_built
					'wp_editor'  => 'Other (Using Shortcodes)'
				],
			);

			$resp['sub_filter_group'] = array(
				'funnel'      => [
					'all'   => 'All',
					'sales' => 'Sales Funnels',
					'optin' => 'Optin Funnels'
				],
				'wc_checkout' => [
					'1' => 'One Step',
					'2' => 'Two Step',
					'3' => 'Three Step'
				],
			);

			do_action( 'wffn_rest_before_get_templates' );
			$general_settings        = BWF_Admin_General_Settings::get_instance();
			$default_builder         = $general_settings->get_option( 'default_selected_builder' );
			$resp['default_builder'] = ( ! empty( $default_builder ) ) ? $default_builder : 'elementor';

			$templates = WooFunnels_Dashboard::get_all_templates();
			$json_data = isset( $templates['funnel'] ) ? $templates['funnel'] : [];

			if ( empty( $json_data ) ) {
				$templates = WooFunnels_Dashboard::get_all_templates( true );
				$json_data = isset( $templates['funnel'] ) ? $templates['funnel'] : [];
			}

			foreach ( $templates as $_t => $_template ) {
				$wp_editor                     = [
					'wp_editor_1' => [
						'type'               => 'view',
						'import'             => 'no',
						'show_import_popup'  => 'no',
						'import_button_text' => 'import',
						'slug'               => 'wp_editor_1',
						'build_from_scratch' => true
					]
				];
				$templates[ $_t ]['wp_editor'] = $wp_editor;
			}


			$templates['funnel'] = $json_data;

			if ( is_array( $templates ) && count( $templates ) > 0 ) {
				$resp['templates'] = apply_filters( 'wffn_rest_get_templates', $templates );
			}

			return $resp;
		}

		public function get_edit_url( $builder, $page_id ) {
			$edit_url = '#';

			switch ( $builder ) {
				case 'divi':
					$edit_url = add_query_arg( [ 'p' => $page_id, 'et_fb' => true, 'PageSpeed' => 'off' ], site_url() );
					break;

				case 'elementor':
					$edit_url = add_query_arg( [ 'post' => $page_id, 'action' => 'elementor' ], admin_url( 'post.php' ) );
					break;

				case 'oxy' :
					$edit_url = add_query_arg( [ 'ct_builder' => true ], get_the_permalink( $page_id ) );
					break;

				default:
					$edit_url = htmlspecialchars_decode( get_edit_post_link( $page_id ) );
					break;
			}

			return $edit_url;
		}

		public function get_delete_steps_collection() {
			$params         = array();
			$params['type'] = array(
				'description' => __( 'Step type.', 'woofunnels-aero-checkout' ),
				'type'        => 'string',
				'required'    => true,
			);

			return apply_filters( 'wffn_rest_delete_steps_collection', $params );
		}

	}

	if ( ! function_exists( 'WFACP_REST_Funnels' ) ) {
		function WFACP_REST_Funnels() {  //@codingStandardsIgnoreLine
			return WFACP_REST_Funnels::get_instance();
		}
	}


	WFACP_REST_Funnels();
}
