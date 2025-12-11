<?php

if ( class_exists( 'WFFN_REST_Controller' ) ) {

	if ( ! class_exists( 'WFFN_REST_API_EndPoint' ) ) {
		#[AllowDynamicProperties]
		class WFFN_REST_API_EndPoint extends WFFN_REST_Controller {

			private static $ins = null;
			protected $namespace = 'funnelkit-app';
			protected $rest_base = 'funnel-analytics';
			protected $rest_base_lite = 'funnels';
			/**
			 * WFFN_REST_API_EndPoint constructor.
			 */
			public function __construct() {

				add_action( 'rest_api_init', [ $this, 'register_endpoint' ], 12 );
				add_action( 'wffn_top_sales_funnels', [ $this, 'get_top_sales_funnels' ], 10, 2 );

			}

			/**
			 * @return WFFN_REST_API_EndPoint|null
			 */
			public static function get_instance() {
				if ( null === self::$ins ) {
					self::$ins = new self;
				}

				return self::$ins;
			}

			public function register_endpoint() {

				/**
				 * if we received funnel id 0 those api return data for global analytics
				 */

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/overview/', array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_overview' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/stats/', array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_stats' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/steps/', array(
					array(
						'args'                => $this->get_stats_collection_for_steps(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_steps' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/conversion/stats/', array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_conversion_stats' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/conversion/campaign-data/', array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_campaign_data' ),
						'args'                => array(
							'after'  => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'before' => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'utms'   => array(
								'description'       => __( 'steps', 'funnel-builder-powerpack' ),
								'type'              => 'string',
								'validate_callback' => 'rest_validate_request_arg',
								'sanitize_callback' => array( $this, 'sanitize_custom' ),
							),
						),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/referrer/', array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_conversion_referrer' ),
						'args'                => array(
							'after'  => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'before' => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							)
						),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/global/referrer/', array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_conversion_referrer' ),
						'args'                => array(
							'after'  => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'before' => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							)
						),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );
				register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/referrer-social-stats/', array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_conversion_social_data' ),
						'args'                => array(
							'after'  => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'before' => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							)
						),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/global/referrer-social-stats/', array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_conversion_social_data' ),
						'args'                => array(
							'after'  => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							),
							'before' => array(
								'type'              => 'string',
								'format'            => 'date-time',
								'validate_callback' => 'rest_validate_request_arg',
								'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
							)
						),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/global/funnel-list/', array(
					array(
						'args'                => $this->get_stats_collection_for_steps(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_global_funnel_list' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base . '/global/utms/', [
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_global_utms' ],
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [
						'page_no'     => [
							'required'          => true,
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						],
						'funnel_id'   => [
							'required'          => false,
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						],
						's'           => [
							'required'          => false,
							'validate_callback' => function ( $param, $request, $key ) {
								return is_string( $param );
							},
						],
						'limit'       => [
							'required'          => true,
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						],
						'total_count' => [
							'required'          => true,
							'validate_callback' => function ( $param, $request, $key ) {
								return in_array( $param, [ 'yes', 'no' ] );
							},
						],
					],
				] );
				register_rest_route( $this->namespace, '/' . $this->rest_base_lite . '/category/', array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_funnel_category' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
						'args'                => array(
							'name' => array(
								'description'       => __( 'Funnel Add Category', 'funnel-builder' ),
								'type'              => 'string',
								'required'          => true,
								'validate_callback' => 'rest_validate_request_arg'
							),
						),
					),
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $this, 'delete_funnel_category' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
						'args'                => array(
							'slug' => array(
								'description'       => __( 'Funnel Delete Category', 'funnel-builder' ),
								'type'              => 'string',
								'required'          => true,
								'validate_callback' => 'rest_validate_request_arg'
							),
						),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'rename_category' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
						'args'                => array(
							'old_slug' => array(
								'description'       => __( 'Old category slug to rename', 'funnel-builder' ),
								'type'              => 'string',
								'required'          => true,
								'validate_callback' => 'rest_validate_request_arg'
							),
							'new_name' => array(
								'description'       => __( 'New category name', 'funnel-builder' ),
								'type'              => 'string',
								'required'          => true,
								'validate_callback' => 'rest_validate_request_arg'
							),
						),
					),
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_funnel_categories' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
						'args'                => array(
							'search' => array(
								'description'       => __( 'Search term to filter categories', 'funnel-builder' ),
								'type'              => 'string',
								'required'          => false,
								'validate_callback' => 'rest_validate_request_arg',
							),
						),
					),
				) );

				register_rest_route( $this->namespace, '/' . $this->rest_base_lite . '/assign-categories/', array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'assign_categories_to_funnels' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'funnel_ids' => array(
							'description'       => __( 'Funnel ID Assign Category', 'funnel-builder' ),
							'required'          => true,
							'type'              => '',
							'validate_callback' => 'rest_validate_request_arg'
						),
						'categories' => array(
							'description'       => __( 'Funnel Assign Category Slug', 'funnel-builder' ),
							'required'          => true,
							'type'              => 'array',
							'validate_callback' => 'rest_validate_request_arg'
						),
					),
				) );

			}

			/**
			 * @return bool
			 */
			public function get_read_api_permission_check() {
				if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
					return false;
				}

				return wffn_rest_api_helpers()->get_api_permission_check( 'analytics', 'read' );
			}

			public function get_write_api_permission_check() {
				return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
			}
			/**
			 * Get all funnel categories with optional search functionality.
			 *
			 * @param WP_REST_Request $data The request data containing an optional search term.
			 *
			 * @return WP_REST_Response The response object with categories list.
			 */
			public function get_funnel_categories( $data ) {

				try {
					$search     = sanitize_text_field( $data->get_param( 'search' ) );
					$categories = WFFN_Category_DB::get_categories();
					if ( ! empty( $search ) ) {
						$categories = array_filter( $categories, function ( $name, $slug ) use ( $search ) {
							return ( strpos( strtolower( $name ), strtolower( $search ) ) !== false || strpos( strtolower( $slug ), strtolower( $search ) ) !== false );
						}, ARRAY_FILTER_USE_BOTH );
					}
					$category_data = [];
					foreach ( $categories as $slug => $name ) {
						$count = WFFN_Category_DB::get_category_funnel_count( $slug );

						$category_data[] = [
							'slug'  => $slug,
							'name'  => $name,
							'count' => $count,
						];
					}

					return rest_ensure_response( [
						'result'  => [
							'items' => $category_data
						],
						'status'  => true,
						'message' => __( 'Category Fetched Successfully ', 'funnel-builder' )
					] );
				} catch ( Exception|Error $e ) {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => false,
						'message' => __( $e->getMessage(), 'funnel-builder' )
					] );
				}
			}

			/**
			 * Create a new funnel category.
			 *
			 * @param WP_REST_Request $data The request data containing the category name.
			 *
			 * @return WP_REST_Response The response object with success or error message.
			 */
			public function create_funnel_category( $data ) {
				if ( ! empty( $data->name ) ) {
					$category_name = sanitize_text_field( $data->name );
				}
				if ( empty( $category_name ) ) {
					$category_name = sanitize_text_field( $data->get_param( 'name' ) );
				}

				$category_slug = sanitize_title( $category_name );
				$category_slug = str_replace( '-', '_', $category_slug );
				if ( WFFN_Category_DB::category_exists( $category_slug ) ) {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => false,
						'message' => __( 'Category already exists ', 'funnel-builder' )
					] );
				}
				$result = WFFN_Category_DB::add_or_update_category( $category_name, $category_slug );

				if ( $result ) {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => true,
						'message' => __( 'Category created successfully ', 'funnel-builder' )
					] );
				} else {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => false,
						'message' => __( 'Failed to create category ', 'funnel-builder' )
					] );
				}
			}

			/**
			 * Delete a funnel category.
			 *
			 * @param WP_REST_Request $data The request data containing the category slug.
			 *
			 * @return WP_REST_Response The response object with success or error message.
			 */
			public function delete_funnel_category( $data ) {
				try {
					$category_slug = sanitize_text_field( $data->get_param( 'slug' ) );

					if ( ! WFFN_Category_DB::category_exists( $category_slug ) ) {
						return rest_ensure_response( [ 'result' => [], 'success' => false, 'message' => __( 'Category not found ', 'funnel-builder' ) ] );
					}

					$result = WFFN_Category_DB::delete_category( $category_slug );

					if ( ! $result ) {
						$message = WFFN_Category_DB::get_message();

						return rest_ensure_response( [
							'result'  => [],
							'status'  => false,
							'message' => __( $message, 'funnel-builder' )
						] );
					}

					$remove_from_funnels = WFFN_Category_DB::remove_category_from_funnels( $category_slug );

					if ( ! $remove_from_funnels ) {
						return rest_ensure_response( [
							'result'  => [],
							'status'  => false,
							'message' => __( 'Failed to remove category ', 'funnel-builder' )
						] );
					}

					return rest_ensure_response( [
						'result'  => [],
						'status'  => true,
						'message' => __( 'Category deleted successfully ', 'funnel-builder' )
					] );

				} catch ( Exception|Error $e ) {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => false,
						'message' => __( 'An error occurred ', 'funnel-builder' )
					] );
				}
			}

			/**
			 * Rename an existing funnel category.
			 *
			 * @param WP_REST_Request $data The request data containing the old slug and the new name.
			 *
			 * @return WP_REST_Response The response object with success or error message.
			 */
			public function rename_category( $data ) {
				try {
					$old_slug = sanitize_text_field( $data->get_param( 'old_slug' ) );
					$new_name = sanitize_text_field( $data->get_param( 'new_name' ) );

					$result = WFFN_Category_DB::rename_category_in_funnels( $old_slug, $new_name );

					if ( $result ) {
						$new_slug = sanitize_title( $new_name );
						$new_slug = str_replace( '-', '_', $new_slug );

						return rest_ensure_response( [
							'result'  => [
								'newslug' => $new_slug
							],
							'status'  => true,
							'message' => __( 'Category renamed successfully ', 'funnel-builder' )
						] );
					} else {
						$message = WFFN_Category_DB::get_message();

						return rest_ensure_response( [
							'result'  => [],
							'status'  => false,
							'message' => __( $message, 'funnel-builder' )
						] );
					}

				} catch ( Exception|Error $e ) {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => false,
						'message' => __( 'An error occurred ', 'funnel-builder' )
					] );
				}
			}

			/**
			 * Assign categories to specific funnels.
			 *
			 * @param WP_REST_Request $data The request data containing funnel IDs and categories slugs.
			 *
			 * @return WP_REST_Response The response object with success or error message.
			 */
			public function assign_categories_to_funnels( $data ) {
				try {
					$funnel_ids = $data->get_param( 'funnel_ids' );
					$categories = $data->get_param( 'categories' );

					$categories = array_map( function ( $category ) {
						if ( is_array( $category ) && isset( $category['slug'], $category['name'] ) ) {
							return [
								'slug' => sanitize_text_field( $category['slug'] ),
								'name' => sanitize_text_field( $category['name'] )
							];
						}

						return [];
					}, $categories );

					$categories_slugs = array_map( function ( $category ) {
						return $category['slug'];
					}, $categories );
					foreach ( $categories as $key => $category ) {
						if ( $category['slug'] === '0' ) {
							$category_name = sanitize_text_field( $category['name'] );
							$category_slug = sanitize_title( $category_name );
							$category_slug = str_replace( '-', '_', $category_slug );

							$create_category_response = $this->create_funnel_category( (object) [ 'name' => $category_name ] );

							if ( isset( $create_category_response->data['status'] ) && $create_category_response->data['status'] === false ) {
								return rest_ensure_response( [
									'result'  => [],
									'status'  => false,
									'message' => __( $create_category_response->data['message'] . $category_name, 'funnel-builder' )
								] );
							}
							$categories[ $key ]['slug'] = sanitize_title( $category_slug );

							$categories_slugs[ $key ] = sanitize_title( $category_slug );
						}
					}

					if ( is_string( $funnel_ids ) ) {
						$result = WFFN_Category_DB::insert_funnel_meta( $funnel_ids, WFFN_Category_DB::$funnel_meta_key, wp_json_encode( $categories_slugs ) );

					} else {
						$funnel_ids = is_string( $funnel_ids ) ? explode( ',', $funnel_ids ) : $funnel_ids;
						$result     = WFFN_Category_DB::assign_categories_to_funnels( $funnel_ids, $categories_slugs );
					}
					if ( $result ) {
						return rest_ensure_response( [
							'result'  => [],
							'status'  => true,
							'message' => __( 'Categories assigned to funnels successfully ', 'funnel-builder' )
						] );
					} else {
						return rest_ensure_response( [
							'result'  => [],
							'status'  => false,
							'message' => __( 'Failed to assign categories to funnels ', 'funnel-builder' )
						] );
					}

				} catch ( Exception|Error $e ) {
					return rest_ensure_response( [
						'result'  => [],
						'status'  => false,
						'message' => __( $e->getMessage(), 'funnel-builder' )
					] );
				}
			}

			public function get_overview( $request ) {
				$resp = array(
					'status' => false,
					'data'   => []
				);
				$data = $this->prepare_item_for_response( $request );

				if ( ! is_array( $data ) || 0 === count( $data ) ) {
					return $resp;
				}

				$resp['status'] = true;
				$resp['data']   = $data;

				return rest_ensure_response( $resp );
			}

			public function get_stats( $request ) {
				$resp = array(
					'status' => false,
					'data'   => []
				);
				$data = $this->prepare_item_for_response( $request, 'interval' );// phpcs:ignore WordPress.Security.NonceVerification.Missing

				if ( ! is_array( $data ) || 0 === count( $data ) ) {
					return rest_ensure_response( $resp );
				}

				$resp['status'] = true;
				$resp['data']   = $data;

				return rest_ensure_response( $resp );
			}

			/**
			 * @param $item
			 * @param $is_interval
			 *
			 * @return array
			 * @throws Exception
			 */
			public function prepare_item_for_response( $item, $is_interval = '' ) {
				if ( isset( $item['overall'] ) ) {
					if ( $is_interval === 'interval' ) {
						global $wpdb;
						$item['after']    = $wpdb->get_var( $wpdb->prepare( "SELECT timestamp as date FROM {$wpdb->prefix}bwf_conversion_tracking WHERE funnel_id != '' AND type = 2 ORDER BY ID ASC LIMIT %d", 1 ) );
						$start_date       = ( isset( $item['after'] ) && '' !== $item['after'] ) ? $item['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
						$end_date         = ( isset( $item['before'] ) && '' !== $item['before'] ) ? $item['before'] : self::default_date()->format( self::$sql_datetime_format );
						$item['interval'] = $this->get_two_date_interval( $start_date, $end_date );
						$interval_type    = $item['interval'];

					} else {
						$start_date = '';
						$end_date   = '';
					}

				} else {
					$start_date = ( isset( $item['after'] ) && '' !== $item['after'] ) ? $item['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
					$end_date   = ( isset( $item['before'] ) && '' !== $item['before'] ) ? $item['before'] : self::default_date()->format( self::$sql_datetime_format );
				}
				$total_revenue    = 0;
				$checkout_revenue = 0;
				$upsell_revenue   = 0;
				$bump_revenue     = 0;
				$int_request      = ( '' !== $is_interval ) ? ( isset( $item['interval'] ) && '' !== $item['interval'] ? $item['interval'] : 'week' ) : '';
				$funnel_id        = ( isset( $item['id'] ) && '' !== $item['id'] ) ? intval( $item['id'] ) : 0;

				$get_total_visits = $this->get_unique_visits( $funnel_id, $start_date, $end_date, $is_interval, $int_request );
				if ( is_array( $get_total_visits ) && isset( $get_total_visits['db_error'] ) ) {
					$get_total_visits = 0;
				}

				$get_total_orders = $this->get_total_orders( $funnel_id, $start_date, $end_date, $is_interval, $int_request );
				if ( is_array( $get_total_orders ) && isset( $get_total_orders['db_error'] ) ) {
					$get_total_orders = 0;
				}

				$get_total_revenue = $this->get_total_revenue( $funnel_id, $start_date, $end_date, $is_interval, $int_request );
				if ( is_array( $get_total_revenue ) && isset( $get_total_revenue['db_error'] ) ) {
					$get_total_revenue = 0;
				}

				$get_total_contacts = $this->get_total_contacts( $funnel_id, $start_date, $end_date, $is_interval, $int_request );
				if ( is_array( $get_total_contacts ) && isset( $get_total_contacts['db_error'] ) ) {
					$get_total_contacts = 0;
				}

				$total_convert_seconds = $this->get_convert_time( $funnel_id, $start_date, $end_date, $is_interval, $int_request );
				if ( is_array( $total_convert_seconds ) && isset( $total_convert_seconds['db_error'] ) ) {
					$total_convert_seconds = 0;
				}

				$result    = [];
				$intervals = array();
				if ( ! empty( $is_interval ) ) {
					$result['intervals']     = [];
					$result['interval_type'] = isset( $interval_type ) ? $interval_type : '';
					$overall                 = isset( $item['overall'] ) ? true : false;
					$intervals_all           = $this->intervals_between( $start_date, $end_date, $int_request, $overall );
					foreach ( $intervals_all as $all_interval ) {
						$interval   = $all_interval['time_interval'];
						$start_date = $all_interval['start_date'];
						$end_date   = $all_interval['end_date'];

						$get_total_visit = is_array( $get_total_visits ) ? $this->maybe_interval_exists( $get_total_visits, 'time_interval', $interval ) : 0;
						$get_total_order = is_array( $get_total_orders ) ? $this->maybe_interval_exists( $get_total_orders, 'time_interval', $interval ) : 0;

						if ( is_array( $get_total_revenue ) ) {
							$get_revenue = $this->maybe_interval_exists( $get_total_revenue['aero'], 'time_interval', $interval );

							$total_revenue_aero = is_array( $get_revenue ) ? $get_revenue[0]['sum_aero'] : 0;
							$total_revenue      = is_array( $get_revenue ) ? $get_revenue[0]['total'] : $get_revenue;

							$total_revenue_bump = $this->maybe_interval_exists( $get_total_revenue['bump'], 'time_interval', $interval );
							$total_revenue_bump = is_array( $total_revenue_bump ) ? $total_revenue_bump[0]['sum_bump'] : 0;

							$total_revenue_upsells = $this->maybe_interval_exists( $get_total_revenue['upsell'], 'time_interval', $interval );
							$total_revenue_upsells = is_array( $total_revenue_upsells ) ? $total_revenue_upsells[0]['sum_upsells'] : 0;
						} else {
							$total_revenue_aero    = 0;
							$total_revenue_bump    = 0;
							$total_revenue_upsells = 0;
							$total_revenue         = 0;
						}

						$get_total_contact    = is_array( $get_total_contacts ) ? $this->maybe_interval_exists( $get_total_contacts, 'time_interval', $interval ) : 0;
						$total_convert_second = is_array( $total_convert_seconds ) ? $this->maybe_interval_exists( $total_convert_seconds, 'time_interval', $interval ) : 0;


						$get_total_visit             = is_array( $get_total_visit ) ? $get_total_visit[0]['unique_views'] : 0;
						$get_total_order             = is_array( $get_total_order ) ? $get_total_order[0]['total_orders'] : 0;
						$get_total_contact           = is_array( $get_total_contact ) ? $get_total_contact[0]['contacts'] : 0;
						$total_convert_second        = is_array( $total_convert_second ) ? $total_convert_second[0]['seconds'] : 0;
						$intervals['interval']       = $interval;
						$intervals['start_date']     = $start_date;
						$intervals['date_start_gmt'] = $this->convert_local_datetime_to_gmt( $start_date )->format( self::$sql_datetime_format );
						$intervals['end_date']       = $end_date;
						$intervals['date_end_gmt']   = $this->convert_local_datetime_to_gmt( $end_date )->format( self::$sql_datetime_format );

						$intervals['subtotals'] = array(
							'unique_visits'       => $get_total_visit,
							'total_orders'        => $get_total_order,
							'total_revenue'       => $total_revenue,
							'revenue_per_visit'   => ( absint( $get_total_visit ) !== 0 ) ? ( $total_revenue ) / $get_total_visit : 0,
							'checkout_revenue'    => floatval( $total_revenue_aero ),
							'upsell_revenue'      => floatval( $total_revenue_upsells ),
							'bump_revenue'        => floatval( $total_revenue_bump ),
							'convert_time_second' => $total_convert_second,
							'contacts'            => $get_total_contact,
							'average_order_value' => ( absint( $get_total_order ) !== 0 ) ? ( $total_revenue ) / $get_total_order : 0,
						);

						$result['intervals'][] = $intervals;

					}

				} else {

					$unique_visits         = is_array( $get_total_visits ) && ! is_null( $get_total_visits[0]['unique_views'] ) ? $get_total_visits[0]['unique_views'] : 0;
					$total_orders          = is_array( $get_total_orders ) && ! is_null( $get_total_orders[0]['total_orders'] ) ? $get_total_orders[0]['total_orders'] : 0;
					$get_total_contacts    = is_array( $get_total_contacts ) && ! is_null( $get_total_contacts[0]['contacts'] ) ? $get_total_contacts[0]['contacts'] : 0;
					$total_convert_seconds = ( is_array( $total_convert_seconds ) && count( $total_convert_seconds ) > 0 ) && ! is_null( $total_convert_seconds[0]['seconds'] ) ? $total_convert_seconds[0]['seconds'] : 0;

					if ( is_array( $get_total_revenue ) ) {
						$total_revenue = $checkout_revenue = $get_total_revenue['aero'][0]['total'];
						if ( count( $get_total_revenue['aero'] ) > 0 ) {
							$checkout_revenue = $get_total_revenue['aero'][0]['sum_aero'];
						}
						if ( count( $get_total_revenue['bump'] ) > 0 ) {
							$bump_revenue = $get_total_revenue['bump'][0]['sum_bump'];
						}
						if ( count( $get_total_revenue['upsell'] ) > 0 ) {
							$upsell_revenue = $get_total_revenue['upsell'][0]['sum_upsells'];
						}
					}

					if ( absint( $total_revenue ) > 0 && absint( $unique_visits ) > 0 ) {
						$revenue_per_visit = $total_revenue / $unique_visits;
					} else {
						$revenue_per_visit = 0;
					}
					if ( absint( $total_revenue ) > 0 && absint( $total_orders ) > 0 ) {
						$average_order_value = $total_revenue / $total_orders;
					} else {
						$average_order_value = 0;
					}

					$result = [
						'unique_visits'       => is_null( $unique_visits ) ? 0 : $unique_visits,
						'contacts'            => $get_total_contacts,
						'total_orders'        => is_null( $total_orders ) ? 0 : $total_orders,
						'total_revenue'       => is_null( $total_revenue ) ? 0 : $total_revenue,
						'checkout_revenue'    => floatval( $checkout_revenue ),
						'upsell_revenue'      => floatval( $upsell_revenue ),
						'bump_revenue'        => floatval( $bump_revenue ),
						'convert_time'        => $total_convert_seconds,
						'average_order_value' => $average_order_value,
						'revenue_per_visit'   => $revenue_per_visit,
					];
				}

				return $result;

			}

			public function get_unique_visits( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {

				global $wpdb;
				$table          = $wpdb->prefix . 'wfco_report_views';
				$date_col       = "date";
				$interval_query = '';
				$group_by       = '';
				$date           = ( '' !== $start_date && '' !== $end_date ) ? " AND `" . $date_col . "` >= '" . esc_sql( $start_date ) . "' AND `" . $date_col . "` < '" . esc_sql( $end_date ) . "' " : '';
				$funnel_query   = ( 0 === intval( $funnel_id ) ) ? " AND object_id != " . $funnel_id . " " : " AND object_id = " . $funnel_id . " ";

				if ( 'interval' === $is_interval ) {
					$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
					$interval_query = $get_interval['interval_query'];
					$interval_group = $get_interval['interval_group'];
					$group_by       = " GROUP BY " . $interval_group;

				}

				$unique_views = $wpdb->get_results( "SELECT SUM(no_of_sessions) as unique_views" . $interval_query . "  FROM `" . $table . "` WHERE 1=1 " . $date . " AND `type` = 7 " . $funnel_query . $group_by . " ORDER BY id ASC", ARRAY_A ); //phpcs:ignore
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

				return $unique_views;
			}

			public function get_convert_time( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {
				global $wpdb;
				$date_col       = "timestamp";
				$interval_query = '';
				$group_by       = '';
				$date           = ( '' !== $start_date && '' !== $end_date ) ? " AND `" . $date_col . "` >= '" . esc_sql( $start_date ) . "' AND `" . $date_col . "` < '" . esc_sql( $end_date ) . "' " : '';

				if ( 'interval' === $is_interval ) {
					$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
					$interval_query = $get_interval['interval_query'];
					$interval_group = $get_interval['interval_group'];
					$group_by       = " GROUP BY " . $interval_group;

				}
				$query = "SELECT (CASE WHEN SUM( TIMESTAMPDIFF( SECOND, first_click, timestamp ) ) != 0 THEN SUM( TIMESTAMPDIFF( SECOND, first_click, timestamp ) )/COUNT(id) ELSE 0 END ) as 'seconds' " . $interval_query . " 
        FROM " . $wpdb->prefix . "bwf_conversion_tracking WHERE 1=1 " . $date . " AND funnel_id = " . $funnel_id . $group_by . " ORDER BY funnel_id ASC";
				$data  = $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore

						return 0;
					}
				}

				return $data;
			}

			public function get_total_orders( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {
				global $wpdb;
				$table          = $wpdb->prefix . 'bwf_conversion_tracking';
				$date_col       = "tracking.timestamp";
				$interval_query = '';
				$group_by       = '';
				$limit          = '';
				$total_orders   = [];
				$funnel_query   = ( 0 === intval( $funnel_id ) ) ? " AND tracking.funnel_id != " . $funnel_id . " " : " AND tracking.funnel_id = " . $funnel_id . " ";

				if ( 'interval' === $is_interval ) {
					$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
					$interval_query = $get_interval['interval_query'];
					$interval_group = $get_interval['interval_group'];
					$group_by       = " GROUP BY " . $interval_group;

				}

				$date = ( '' !== $start_date && '' !== $end_date ) ? " AND " . $date_col . " >= '" . $start_date . "' AND " . $date_col . " < '" . $end_date . "' " : '';

				if ( class_exists( 'WFACP_Contacts_Analytics' ) && version_compare( WFACP_VERSION, '2.0.7', '>' ) ) {
					$total_orders = $wpdb->get_results( "SELECT count(DISTINCT tracking.source) as total_orders " . $interval_query . "  FROM `" . $table . "` as tracking JOIN `" . $wpdb->prefix . "bwf_contact` as cust ON cust.id=tracking.contact_id WHERE 1=1 AND tracking.type=2 " . $date . $funnel_query . $group_by . " ORDER BY tracking.id ASC $limit", ARRAY_A );//phpcs:ignore
					if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
						$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
						if ( true === $db_error['db_error'] ) {
							WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore

							return 0;
						}
					}
				}

				return $total_orders;
			}

			public function get_total_revenue( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {

				/**
				 * get revenue
				 */ global $wpdb;
				$total_revenue_aero    = [];
				$total_revenue_bump    = [];
				$total_revenue_upsells = [];

				/**
				 * get revenue
				 */
				$table          = $wpdb->prefix . 'bwf_conversion_tracking';
				$date_col       = "conv.timestamp";
				$interval_query = '';
				$group_by       = '';
				$funnel_query   = ( 0 === intval( $funnel_id ) ) ? " AND conv.funnel_id != " . $funnel_id . " " : " AND conv.funnel_id = " . $funnel_id . " ";

				if ( 'interval' === $is_interval ) {
					$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
					$interval_query = $get_interval['interval_query'];
					$interval_group = $get_interval['interval_group'];
					$group_by       = " GROUP BY " . $interval_group;

				}

				$date = ( '' !== $start_date && '' !== $end_date ) ? " AND " . esc_sql( $date_col ) . " >= '" . $start_date . "' AND " . $date_col . " < '" . esc_sql( $end_date ) . "' " : '';

				if ( class_exists( 'WFACP_Core' ) ) {
					$query              = "SELECT SUM(conv.value) as total, SUM(conv.checkout_total) as sum_aero " . $interval_query . "  FROM `" . $table . "` as conv WHERE 1=1 " . $date . $funnel_query . $group_by . " ORDER BY conv.id ASC";
					$total_revenue_aero = $wpdb->get_results( $query, ARRAY_A );
					if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
						$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
						if ( true === $db_error['db_error'] ) {
							$total_revenue_aero = [];
							WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
						}
					}
				}

				if ( class_exists( 'WFOB_Core' ) ) {
					$query              = "SELECT SUM(conv.bump_total) as sum_bump " . $interval_query . "  FROM `" . $table . "` as conv WHERE 1=1 " . $date . $funnel_query . $group_by . " ORDER BY conv.id ASC";
					$total_revenue_bump = $wpdb->get_results( $query, ARRAY_A );
					if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
						$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
						if ( true === $db_error['db_error'] ) {
							$total_revenue_bump = [];
							WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
						}
					}
				}

				if ( class_exists( 'WFOCU_Core' ) ) {
					$query                 = "SELECT SUM(conv.offer_total) as sum_upsells " . $interval_query . "  FROM `" . $table . "` as conv WHERE 1=1 " . $date . $funnel_query . $group_by . " ORDER BY conv.id ASC";
					$total_revenue_upsells = $wpdb->get_results( $query, ARRAY_A );
					if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
						$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
						if ( true === $db_error['db_error'] ) {
							$total_revenue_upsells = [];
							WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
						}
					}
				}

				return array( 'aero' => $total_revenue_aero, 'bump' => $total_revenue_bump, 'upsell' => $total_revenue_upsells );
			}

			public function get_stats_collection() {
				$params = array();

				$params['after']  = array(
					'type'              => 'string',
					'format'            => 'date-time',
					'validate_callback' => 'rest_validate_request_arg',
					'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
				);
				$params['before'] = array(
					'type'              => 'string',
					'format'            => 'date-time',
					'validate_callback' => 'rest_validate_request_arg',
					'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
				);

				$params['interval'] = array(
					'type'              => 'string',
					'default'           => 'week',
					'validate_callback' => 'rest_validate_request_arg',
					'description'       => __( 'Time interval to use for buckets in the returned data.', 'woofunnels-upstroke-one-click-upsell' ),
					'enum'              => array(
						'hour',
						'day',
						'week',
						'month',
						'quarter',
						'year',
					),
				);

				return apply_filters( 'wfocu_rest_funnels_stats_collection', $params );
			}

			public function get_stats_collection_for_steps() {
				$params = array();

				$params['after']  = array(
					'type'              => 'string',
					'format'            => 'date-time',
					'validate_callback' => 'rest_validate_request_arg',
					'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
				);
				$params['before'] = array(
					'type'              => 'string',
					'format'            => 'date-time',
					'validate_callback' => 'rest_validate_request_arg',
					'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
				);

				return apply_filters( 'wfocu_rest_funnels_stats_collection', $params );
			}

			public function get_steps( $request ) {
				$funnel_id = (int) $request['id'];//phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $request['overall'] ) ) {
					$start_date = '';
					$end_date   = '';
				} else {
					$start_date = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
					$end_date   = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );
				}

				$funnel = new WFFN_Funnel( $funnel_id );

				/**
				 * need to remove this commit code and respective function
				 * no needed
				 */
				$get_records = $this->prepare_analytics_data( $funnel, $funnel_id, $start_date, $end_date );
				$steps       = $funnel->get_steps();

				if ( true === self::maybe_store_checkout_config( $funnel ) ) {
					if ( true === $funnel->is_funnel_has_native_checkout() ) {
						$bumps           = WFFN_Common::get_store_checkout_global_substeps( $funnel_id );
						$native_checkout = array(
							'id'       => 0,
							'type'     => WFFN_Common::store_native_checkout_slug(),
							'substeps' => [],
						);
						$native_checkout_record          = array(
							'type'            => WFFN_Common::store_native_checkout_slug(),
							'object_id'       => 0,
							'object_name'     => __( 'Store Checkout', 'funnel-builder' ),
							'revenue'         => 0,
							'conversions'     => 0,
							'views'           => 0,
							'substeps'        => [],
							'conversion_rate' => 0,
						);
						if ( is_array( $bumps ) && count( $bumps ) > 0 ) {
							$native_checkout['substeps'] = $bumps;
							$native_checkout_record['substeps'] = $bumps;
						}
						array_unshift($get_records, $native_checkout_record );
						array_unshift( $steps, $native_checkout );
					}
				}

				$records = array();
				if ( is_array( $get_records ) && count( $get_records ) > 0 && is_array( $steps ) && count( $steps ) > 0 ) {
					foreach ( $steps as $step ) {
						foreach ( $get_records as $data ) {
							if ( $this->check_type( $step['type'] ) === $data['type'] && absint( $step['id'] ) === absint( $data['object_id'] ) ) {
								$records[] = $data;
								$key       = array_search( $data['object_id'], wp_list_pluck( $get_records, 'object_id' ) );//phpcs:ignore


								if ( $key !== false && isset( $get_records[ $key ] ) ) {
									unset( $get_records[ $key ] );
								}
								if ( ( ( $data['type'] === 'wc_native' ) || ( $data['type'] === 'checkout' ) ) && isset( $step['substeps']['wc_order_bump'] ) && $step['substeps']['wc_order_bump'] > 0 ) {
									$substeps = $step['substeps'];
									foreach ( $substeps['wc_order_bump'] as $substep ) {
										foreach ( $get_records as $item ) {
											if ( $item['type'] === 'bump' && absint( $substep ) === absint( $item['object_id'] ) ) {
												$records[] = $item;
												$key       = array_search( $item['object_id'], wp_list_pluck( $get_records, 'object_id' ) );//phpcs:ignore
												if ( $key !== false && isset( $get_records[ $key ] ) ) {
													unset( $get_records[ $key ] );
												}
											}
										}
									}
								}
							}
						}
					}
				}

				$resp = array(
					'status' => true,
					'msg'    => __( 'success', 'funnel-builder-powerpack' ),
					'data'   => array(
						'records'     => array_merge( $records, $get_records ),
						'funnel_data' => WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id )

					)
				);

				return rest_ensure_response( $resp );
			}

			public function check_type( $type ) {
				switch ( $type ) {
					case 'wc_native':
						$result = 'wc_native';
						break;
					case 'wc_checkout':
						$result = 'checkout';
						break;
					case 'wc_upsells':
						$result = 'upsell';
						break;
					case 'wc_order_bump':
						$result = 'bump';
						break;
					case 'wc_thankyou':
						$result = 'thankyou';
						break;
					default:
						$result = $type;
						break;
				}

				return $result;
			}

			public function prepare_analytics_data( $funnel, $funnel_id, $start_date, $end_date ) {
				$data      = [];
				$ids       = [];
				$get_steps = [];
				if ( $funnel instanceof WFFN_Funnel && 0 < $funnel->get_id() ) {

					$is_wc     = wffn_is_wc_active();
					$get_steps = $funnel->get_steps();
					$get_steps = $this->maybe_add_ab_variants( $get_steps );
					/**
					 * prepare conversion data
					 */
					$get_steps = $this->prepare_optin_conversion( $get_steps, $funnel_id, $start_date, $end_date );

					if ( $is_wc ) {
						$get_steps = $this->prepare_checkout_conversion( $get_steps, $funnel_id, $start_date, $end_date );
					}

					/**
					 * Get all steps and set default data for each step
					 */
					if ( is_array( $get_steps ) && count( $get_steps ) > 0 ) {
						$get_data  = $this->map_defult_step_value( $get_steps );
						$get_steps = $get_data['step_data'];
						$ids       = $get_data['step_ids'];
					}
				}

				if ( ! is_array( $ids ) || count( $ids ) < 1 ) {
					return $data;
				}
				$step_ids = implode( ',', $ids );

				global $wpdb;
				$date_range = ( '' !== $start_date && '' !== $end_date ) ? " AND date BETWEEN '" . esc_sql( $start_date ) . "' AND '" . esc_sql( $end_date ) . "' " : "";
				/**
				 * Get conversion data form report view table
				 * landing - 2 view, 3 convert
				 * checkout - 4 view
				 * thankyou - 5 view
				 * optin - 8 view
				 * optin ty - 10 view, 11 convert
				 */
				$get_query = "SELECT object_id, SUM(CASE WHEN type IN( 2, 4, 5, 8, 10 ) THEN `no_of_sessions` END) AS 'views' ,SUM(CASE WHEN type IN( 3, 11 ) THEN `no_of_sessions` END) AS 'conversions' FROM  `" . $wpdb->prefix . "wfco_report_views` WHERE object_id IN (" . esc_sql( $step_ids ) . ") " . $date_range . " GROUP BY object_id";
				$conv_data = $wpdb->get_results( $get_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
					}
				}

				/*
				 * prepare substeps data
				 */
				if ( $is_wc ) {
					$get_steps = $this->prepare_bump_conversion( $get_steps, $funnel_id, $start_date, $end_date );
					$get_steps = $this->prepare_offer_conversion( $get_steps, $funnel_id, $start_date, $end_date );
				}

				$get_steps = $this->add_revenue_to_optins_sales( $get_steps );
				$get_steps = $this->update_data_with_step_item( $get_steps, $conv_data );

				if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
					return [];
				}

				return array_values( $get_steps );
			}

			public function add_revenue_to_optins_sales( $get_steps ) {

				foreach ( $get_steps as $step_id => &$step_data ) {
					if ( $step_data['type'] === 'optin' || $step_data['type'] === 'landing' ) {

						foreach ( $get_steps as $checkout_step_id => $checkout_step_data ) {
							if ( $checkout_step_data['type'] === 'checkout' ) {
								foreach ( $checkout_step_data['source_revenue'] as $source_revenue ) {
									if ( is_array( $source_revenue ) ) {
										$source_revenue = (object) $source_revenue;
									}
									if ( $source_revenue->source_id == $step_data['object_id'] && $step_data['type'] === 'optin' ) {
										$step_data['revenue'] += (float) $source_revenue->amount;
									}

									if ( $source_revenue->source_id == $step_data['object_id'] && $step_data['type'] === 'landing' ) {
										$step_data['revenue'] += (float) $source_revenue->amount;
									}
								}
							}
						}
					}
				}

				return $get_steps;
			}

			public function map_defult_step_value( $get_steps ) {
				$data = [
					'step_data' => [],
					'step_ids'  => []
				];
				if ( is_array( $get_steps ) && count( $get_steps ) > 0 ) {

					foreach ( $get_steps as $step ) {
						$step_id            = absint( $step['id'] );
						$step_type          = $this->check_type( $step['type'] );
						$data['step_ids'][] = $step_id;
						$step_data          = array(
							'type'            => $step_type,
							'object_id'       => $step_id,
							'object_name'     => html_entity_decode( get_the_title( $step_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
							'revenue'         => isset( $step['revenue'] ) ? $step['revenue'] : 0,
							'conversions'     => isset( $step['conversions'] ) ? $step['conversions'] : 0,
							'views'           => isset( $step['views'] ) ? $step['views'] : 0,
							'substeps'        => isset( $step['substeps'] ) ? $step['substeps'] : [],
							'conversion_rate' => 0,
						);

						/**
						 * add source revenue in checkout step
						 */
						if ( 'checkout' === $step_type ) {
							$step_data['source_revenue'] = isset( $step['source_revenue'] ) ? $step['source_revenue'] : [];
						}

						if ( isset( $step['is_variant'] ) && isset( $step['control'] ) && absint( $step['control'] ) !== 0 ) {
							$step_data['control_id'] = absint( $step['control'] );
						}

						if ( isset( $step_data['source_revenue'] ) ) {
							// Find the element with blank 'type' and move it to the last position
							foreach ( $step_data['source_revenue'] as $key => $item ) {
								if ( $item['type'] === '' ) {
									$blankItem = $step_data['source_revenue'][ $key ];
									unset( $step_data['source_revenue'][ $key ] );
									$step_data['source_revenue'][] = $blankItem;
									break;
								}
							}
							$step_data['source_revenue'] = array_values( $step_data['source_revenue'] );
						}

						if ( ! isset( $step['is_variant'] ) ) {
							$exp_data = BWFABT_Core()->get_dataStore()->get_experiment_by_control_id( $step_id, 'DESC' );
							/**
							 * ab test tag not show when experiment is complete
							 */
							if ( is_array( $exp_data ) && count( $exp_data ) > 0 && isset( $exp_data[0]['status'] ) && 4 !== absint( $exp_data[0]['status'] ) ) {
								$step_data['experiment_status'] = absint( $exp_data[0]['status'] );

							}
						}

						$data['step_data'][ $step_id ] = $step_data;
					}
				}

				return $data;

			}

			/**
			 * Prepare final analytics
			 *
			 * @param $data
			 * @param $conversion_data
			 *
			 * @return mixed
			 */
			public function update_data_with_step_item( $data, $conversion_data ) {
				foreach ( $data as &$item ) {

					if ( is_array( $conversion_data ) && count( $conversion_data ) > 0 ) {
						foreach ( $conversion_data as $entry ) {
							$views       = isset( $entry['views'] ) ? (int) $entry['views'] : 0;
							$revenue     = isset( $entry['revenue'] ) ? (float) $entry['revenue'] : 0;
							$conversions = isset( $entry['conversions'] ) ? (float) $entry['conversions'] : 0;
							if ( absint( $entry['object_id'] ) === absint( $item['object_id'] ) ) {
								$item['views']       += $views;
								$item['conversions'] += $conversions;
								$item['revenue']     += $revenue;

								// Recalculate the conversion rate
								$item['conversion_rate'] = $this->get_percentage( $item['views'], $item['conversions'] );
							}
						}
					}

					/**
					 * merge variant data in control and remove from list
					 */
					if ( isset( $item['control_id'] ) ) {
						$data[ $item['control_id'] ]['views']           += $item['views'];
						$data[ $item['control_id'] ]['conversions']     += $item['conversions'];
						$data[ $item['control_id'] ]['revenue']         += $item['revenue'];
						$data[ $item['control_id'] ]['conversion_rate'] = $this->get_percentage( $data[ $item['control_id'] ]['views'], $data[ $item['control_id'] ]['conversions'] );
						unset( $data[ $item['object_id'] ] );
					}
				}

				return $data;
			}

			public function prepare_optin_conversion( $steps, $funnel_id, $start_date, $end_date ) {
				if ( ! class_exists( 'WFOPP_Core' ) ) {
					return $steps;
				}

				global $wpdb;

				$date_range = ( '' !== $start_date && '' !== $end_date ) ? " AND date BETWEEN '" . esc_sql( $start_date ) . "' AND '" . esc_sql( $end_date ) . "' " : '';
				$sql_query  = "SELECT optin.step_id as 'id', COUNT(optin.id) as 'conversions' FROM " . $wpdb->prefix . 'bwf_optin_entries' . " AS optin WHERE optin.funnel_id=" . $funnel_id . " " . $date_range . " GROUP by optin.step_id ORDER BY optin.step_id ASC";

				$get_all_records = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore

						return $steps;
					}
				}

				if ( ! is_array( $get_all_records ) || count( $get_all_records ) === 0 ) {
					return $steps;
				}

				foreach ( $get_all_records as $key => &$items ) {
					// $items['revenue'] = 1024;
					foreach ( $steps as &$step ) {
						if ( absint( $step['id'] ) === absint( $items['id'] ) ) {
							$step = array_merge( $step, $items );
							unset( $get_all_records[ $key ] );
						}
					}
				}

				/**
				 * handle deleted steps conversions
				 */
				foreach ( $get_all_records as $item ) {
					$item['type']     = 'optin';
					$item['substeps'] = array();
					$steps[]          = $item;
				}

				return $steps;
			}

			public function prepare_checkout_conversion( $steps, $funnel_id, $start_date, $end_date ) {

				if ( version_compare( WFACP_VERSION, '2.0.7', '<' ) ) {
					return $steps;

				}

				global $wpdb;

				$conv_range = ( '' !== $start_date && '' !== $end_date ) ? " AND timestamp BETWEEN '" . esc_sql( $start_date ) . "' AND '" . esc_sql( $end_date ) . "' " : '';
				$sql_query  = "SELECT conv.step_id AS 'id', conv.source_id AS 'source_id', SUM(conv.checkout_total) AS 'amount', COUNT(conv.id) AS 'conversions',  conv.timestamp AS 'date', 'wc_checkout' AS 'type' FROM " . $wpdb->prefix . 'bwf_conversion_tracking' . "  AS conv WHERE type=2 AND conv.step_id != 0 AND conv.funnel_id=" . $funnel_id . " " . $conv_range . " GROUP BY conv.step_id, conv.source_id ORDER BY conv.step_id ASC";

				$aero_result = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore

						return $steps;
					}
				}
				if ( ! is_array( $aero_result ) || count( $aero_result ) === 0 ) {
					return $steps;
				}

				$get_all_records = [];

				foreach ( $aero_result as $entry ) {
					$object_id = $entry['id'];
					foreach ( $steps as $step ) {
						if ( isset( $step['control'] ) && absint( $step['id'] ) === absint( $entry['id'] ) ) {
							$object_id = $step['control'];
						}
					}

					if ( ! isset( $get_all_records[ $object_id ] ) ) {
						$get_all_records[ $object_id ] = array(
							'id'             => $object_id,
							'source_revenue' => array(),
							'revenue'        => 0,
							'conversions'    => 0,
							'type'           => $entry['type']
						);
					}
					// Add sources
					$post_type = get_post_type( $entry['source_id'] );
					$tag       = '';
					if ( $post_type ) {
						if ( 'wffn_optin' === $post_type ) {
							$tag = __( 'Optin', 'funnel-builder' );
						}
						if ( 'wffn_landing' === $post_type ) {
							$tag = __( 'Sales', 'funnel-builder' );
						}
					}

					if ( isset( $get_all_records[ $object_id ]['source_revenue'][ $entry['source_id'] ] ) ) {
						$get_all_records[ $object_id ]['source_revenue'][ $entry['source_id'] ]['amount'] += floatval( $entry['amount'] );
						$get_all_records[ $object_id ]['source_revenue'][ $entry['source_id'] ]['order']  += floatval( $entry['conversions'] );
					} else {
						$get_all_records[ $object_id ]['source_revenue'][ $entry['source_id'] ] = array(
							'title'     => ( empty( $entry['source_id'] ) || ( absint( $entry['source_id'] ) === 0 ) ) ? __( 'Other', 'funnel-builder' ) : html_entity_decode( get_the_title( $entry['source_id'] ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
							'type'      => $tag,
							'source_id' => ( empty( $entry['source_id'] ) || ( absint( $entry['source_id'] ) === 0 ) ) ? 0 : $entry['source_id'],
							'amount'    => $entry['amount'],
							'order'     => $entry['conversions']
						);

					}

					$get_all_records[ $object_id ]['revenue']     += floatval( $entry['amount'] );
					$get_all_records[ $object_id ]['conversions'] += floatval( $entry['conversions'] );

				}

				$get_all_records = array_values( $get_all_records );

				foreach ( $get_all_records as $key => &$items ) {
					foreach ( $steps as &$step ) {
						if ( absint( $step['id'] ) === absint( $items['id'] ) ) {
							$step = array_merge( $step, $items );
							unset( $get_all_records[ $key ] );
						}
					}
				}
				/**
				 * handle deleted steps conversions
				 */
				foreach ( $get_all_records as $item ) {
					$item['substeps'] = array();
					$item['type']     = 'checkout';
					$steps[]          = $item;
				}

				return $steps;
			}

			public function prepare_offer_conversion( $steps, $funnel_id, $start_date, $end_date ) {
				if ( ! class_exists( 'WFOCU_Core' ) || version_compare( WFOCU_VERSION, '2.2.0', '<=' ) ) {
					return $steps;
				}

				$upsells = [];
				foreach ( $steps as $step ) {
					if ( isset( $step['type'] ) && $step['type'] === 'upsell' ) {
						$upsells[] = $step['object_id'];
					}
				}

				global $wpdb;

				$date_range      = ( '' !== $start_date && '' !== $end_date ) ? " AND event.timestamp BETWEEN '" . esc_sql( $start_date ) . "' AND '" . esc_sql( $end_date ) . "' " : '';
				$sql_query       = "SELECT DISTINCT(event.object_id) as 'object_id' FROM " . $wpdb->prefix . 'wfocu_event' . " as event LEFT JOIN " . $wpdb->prefix . 'wfocu_session' . " as session ON event.sess_id = session.id WHERE (event.action_type_id = 1) AND session.fid=" . $funnel_id . " " . $date_range . "  order by event.object_id asc";
				$get_all_records = $wpdb->get_col( $sql_query );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore

						return $steps;
					}
				}

				if ( is_array( $get_all_records ) && count( $get_all_records ) > 0 ) {
					$upsells = array_merge( $upsells, $get_all_records );
				} else {
					$get_all_records = [];
				}

				/**
				 * merge all upsell and also cover deleted upsell
				 */
				$upsell_ids = array_unique( $upsells );

				if ( count( $upsell_ids ) > 0 ) {
					$upsells = [];
					foreach ( $upsell_ids as $upsell ) {
						if ( ! isset( $steps[ $upsell ] ) ) {
							$upsells[ $upsell ] = [
								'type'     => 'upsell',
								'id'       => $upsell,
								'substeps' => array(),
							];
						}
					}

					$upsells = $this->map_defult_step_value( $upsells );

					$upsells = $upsells['step_data'];
					if ( is_array( $upsells ) && count( $upsells ) > 0 ) {
						$steps = array_replace( $steps, $upsells );
					}

					foreach ( $upsell_ids as $upsell_id ) {
						$get_steps = get_post_meta( $upsell_id, '_funnel_steps', true );

						if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
							$get_steps = [];
						}

						$date_range = ( '' !== $start_date && '' !== $end_date ) ? " AND events.timestamp BETWEEN '" . esc_sql( $start_date ) . "' AND '" . esc_sql( $end_date ) . "' " : '';
						$sql_query  = "SELECT COUNT(CASE WHEN action_type_id = 4 THEN 1 END) AS 'conversions', COUNT(CASE WHEN action_type_id = 2 THEN 1 END) AS 'views', object_id  as 'object_id', object_id  as 'id', SUM(value) as 'revenue' FROM " . $wpdb->prefix . 'wfocu_event' . "  as events INNER JOIN " . $wpdb->prefix . 'wfocu_event_meta' . " AS events_meta__funnel_id ON ( events.ID = events_meta__funnel_id.event_id ) 
			                        AND ( ( events_meta__funnel_id.meta_key   = '_funnel_id' AND events_meta__funnel_id.meta_value = $upsell_id )) AND (events.action_type_id = '2' OR events.action_type_id = '4' ) " . $date_range . "  GROUP BY events.object_id";

						$get_all_records = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
							$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
							if ( true === $db_error['db_error'] ) {
								return $db_error;
							}
						}

						/**
						 * merge all offer and also cover deleted offer
						 */
						$get_steps = ( is_array( $get_all_records ) && count( $get_all_records ) > 0 ) ? array_merge( $get_steps, $get_all_records ) : $get_steps;

						if ( count( $get_steps ) === 0 ) {
							return $steps;
						}

						$offer_data = [];
						foreach ( $get_steps as $step_data ) {
							$item_id    = $step_data['id'];
							$item       = [
								'type'     => 'offer',
								'id'       => $item_id,
								'substeps' => array(),
							];
							$control_id = get_post_meta( $item_id, '_bwf_ab_variation_of', true );
							if ( ! empty( $control_id ) ) {
								$item['control']    = $control_id;
								$item['is_variant'] = 'yes';
							}
							$offer_data[ $item_id ] = $item;
						}

						$offer_data = array_values( $offer_data );
						$get_data   = $this->map_defult_step_value( $offer_data );
						$offer_data = $get_data['step_data'];
						$get_offers = $this->update_data_with_step_item( $offer_data, $get_all_records );

						if ( is_array( $get_offers ) && count( $get_offers ) > 0 ) {
							$steps[ $upsell_id ]['offers'] = $get_offers;
						}


					}
				}

				return $steps;
			}

			public function prepare_bump_conversion( $steps, $funnel_id, $start_date, $end_date ) {
				if ( ! class_exists( 'WFOB_Core' ) || version_compare( WFOB_VERSION, '1.8,1', '<=' ) ) {
					return $steps;
				}

				$substeps = [];
				foreach ( $steps as $step ) {
					if ( isset( $step['type'] ) && $step['type'] === 'checkout' && isset( $step['substeps'] ) && count( $step['substeps'] ) > 0 ) {
						$substeps = array_merge( $substeps, $step['substeps']['wc_order_bump'] );
					}
				}

				/**
				 * handle native checkout bump
				 */
				if ( WFFN_Common::get_store_checkout_id() === absint( $funnel_id ) ) {
					$global_bumps = WFFN_Common::get_store_checkout_global_substeps( $funnel_id );
					$substeps     = is_array( $global_bumps ) && isset( $global_bumps['wc_order_bump'] ) ? array_merge( $substeps, $global_bumps ) : $substeps;
				}

				if ( count( $substeps ) === 0 ) {
					return $steps;
				}

				global $wpdb;
				$date_range = ( '' !== $start_date && '' !== $end_date ) ? " AND timestamp BETWEEN '" . esc_sql( $start_date ) . "' AND '" . esc_sql( $end_date ) . "' " : '';

				$get_all_records = array();
				
				foreach ($substeps as $bump_id) {
					$bump_id = intval($bump_id);
					
					$bump_sql = "SELECT 
						" . $bump_id . " as 'object_id',
						" . $bump_id . " as 'id',
						COUNT(CASE WHEN conv.bump_total > 0 AND (conv.bump_accepted LIKE '%" . $bump_id . "%' OR conv.bump_rejected LIKE '%" . $bump_id . "%') THEN 1 END) AS 'conversions', 
						SUM(CASE WHEN (conv.bump_accepted LIKE '%" . $bump_id . "%' OR conv.bump_rejected LIKE '%" . $bump_id . "%') THEN conv.bump_total ELSE 0 END) as 'revenue',
						COUNT(CASE WHEN (conv.bump_accepted LIKE '%" . $bump_id . "%' OR conv.bump_rejected LIKE '%" . $bump_id . "%') THEN 1 END) as 'views', 
						'bump' as 'type' 
						FROM " . $wpdb->prefix . 'bwf_conversion_tracking' . " AS conv 
						WHERE conv.funnel_id = " . $funnel_id . " 
						AND conv.type = 2 
						" . $date_range;
					
					$bump_result = $wpdb->get_results($bump_sql, ARRAY_A);
					if (!empty($bump_result)) {
						$get_all_records[] = $bump_result[0];
					}
				}
				

				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

				/**
				 * merge all bump and also cover deleted bump
				 */
				$substeps = isset( $substeps['wc_order_bump'] ) ? $substeps['wc_order_bump'] : $substeps;

				if ( is_array( $get_all_records ) && count( $get_all_records ) > 0 ) {
					$substeps = array_unique( array_merge( $substeps, wp_list_pluck( $get_all_records, 'object_id' ) ) );
				}

				/**
				 * prepare default array for bump as funnel get_step data
				 * for run same code for create sum
				 */

				$bump_data = [];
				foreach ( $substeps as $item_id ) {
					$item_id    = absint( $item_id );
					$item       = [
						'type'     => 'bump',
						'id'       => $item_id,
						'substeps' => array(),
					];
					$control_id = get_post_meta( $item_id, '_bwf_ab_variation_of', true );
					if ( ! empty( $control_id ) ) {
						$item['control']    = $control_id;
						$item['is_variant'] = 'yes';
					}
					$bump_data[ $item_id ] = $item;
				}

				$bump_data = array_values( $bump_data );
				$get_data  = $this->map_defult_step_value( $bump_data );
				$bump_data = $get_data['step_data'];
				$get_bumps = $this->update_data_with_step_item( $bump_data, $get_all_records );
				if ( is_array( $get_bumps ) && count( $get_bumps ) > 0 ) {
					$steps = array_replace( $steps, $get_bumps );
				}
				return $steps;
			}

			/**
			 * @param $funnel_id
			 * @param $start_date
			 * @param $end_date
			 * @param $is_interval
			 * @param $int_request
			 *
			 * @return array|object|stdClass[]
			 */
			public function get_total_contacts( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {
				global $wpdb;
				$table          = $wpdb->prefix . 'bwf_conversion_tracking';
				$date_col       = "timestamp";
				$interval_query = '';
				$group_by       = '';
				$funnel_query   = ( 0 === intval( $funnel_id ) ) ? " AND funnel_id != " . esc_sql( $funnel_id ) . " " : " AND funnel_id = " . esc_sql( $funnel_id ) . " ";

				if ( 'interval' === $is_interval ) {
					$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
					$interval_query = $get_interval['interval_query'];
					$interval_group = $get_interval['interval_group'];
					$group_by       = " GROUP BY " . $interval_group;
				}

				$date = ( '' !== $start_date && '' !== $end_date ) ? " AND `timestamp` >= '" . esc_sql( $start_date ) . "' AND `timestamp` < '" . esc_sql( $end_date ) . "' " : '';

				$query        = "SELECT COUNT( DISTINCT contact_id ) as contacts " . $interval_query . " FROM `" . $table . "` WHERE 1=1 " . $date . " " . $funnel_query . " " . $group_by;
				$get_contacts = $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared


				if ( is_array( $get_contacts ) && count( $get_contacts ) > 0 ) {
					return $get_contacts;
				}

				return [];
			}

			public function get_conversion_stats( $request ) {

				$response = array();

				/*
				 * get data group by type and referrer
				 */
				$get_conversion = $this->get_conversion_data( $request );
				if ( is_array( $get_conversion ) && isset( $get_conversion['db_error'] ) ) {
					$response['state'] = $get_conversion;

					return rest_ensure_response( $response );
				}

				$item_data       = array(
					'total_orders'    => 0,
					'total_revenue'   => 0,
					'average_revenue' => 0
				);
				$social_platform = array(
					'facebook'  => $item_data,
					'google'    => $item_data,
					'instagram' => $item_data,
					'tiktok'    => $item_data,
					'youtube'   => $item_data,
					'linkedIn'  => $item_data,
					'others'    => $item_data
				);
				$data            = [
					'checkout' => $social_platform,
					'optin'    => $social_platform
				];

				/*
				 * filter data base on type like optin and orders
				 */
				if ( is_array( $get_conversion ) && count( $get_conversion ) > 0 ) {

					foreach ( $get_conversion as $item ) {
						if ( isset( $data[ $item['type'] ] ) ) {
							$platform_name                                              = $this->get_referrer_nice_name( $item['referrer'] );
							$data[ $item['type'] ][ $platform_name ]['total_orders']    += (float) $item['total_orders'];
							$data[ $item['type'] ][ $platform_name ]['total_revenue']   += (float) $item['total_revenue'];
							$data[ $item['type'] ][ $platform_name ]['average_revenue'] = $this->get_percentage( $data[ $item['type'] ][ $platform_name ]['total_revenue'], $data[ $item['type'] ][ $platform_name ]['total_orders'] );

						}
					}

				}

				$response['state']       = $data;
				$response['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $request['id'] );


				return rest_ensure_response( $response );
			}

			public function get_campaign_data( $request ) {
				$data       = array();
				$funnel_id  = ( isset( $request['id'] ) && '' !== $request['id'] ) ? intval( $request['id'] ) : 0;
				$funnel_id  = ( isset( $request['funnel_id'] ) && '' !== $request['funnel_id'] ) ? intval( $request['funnel_id'] ) : $funnel_id;
				$filter_key = ( isset( $request['utms'] ) && is_array( $request['utms'] ) && count( $request['utms'] ) > 0 ) ? implode( ', ', $request['utms'] ) . ", ( CASE WHEN type = 1 THEN 'Lead' WHEN type = 2 THEN 'Customer' ELSE 'Edd Order' END ) AS 'type', COUNT(source) as total_orders, source as order_id, " : " ( CASE WHEN type = 1 THEN 'Lead' WHEN type = 2 THEN 'Customer' ELSE 'Edd Order' END ) AS 'type', COUNT(source) as total_orders, source as order_id, ";
				$type       = isset( $request['type'] ) ? $request['type'] : '';

				$type_args = array(
					'filter_key' => $filter_key,
					'group_by'   => " GROUP BY id ",
				);

				if ( 'order' === $type ) {
					$type_args['other_filters'] = " AND source != 0 ";
				}

				$get_conversion = $this->get_conversion_data( $request, $type_args );
				if ( is_array( $get_conversion ) && isset( $get_conversion['db_error'] ) ) {
					return rest_ensure_response( $get_conversion );
				}

				if ( isset( $get_conversion['total_count'] ) ) {
					$data['total_records'] = $get_conversion['total_count'];
					unset( $get_conversion['total_count'] );
				}

				$data['conversions'] = $get_conversion;
				$data['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );

				return rest_ensure_response( $data );
			}

			/**
			 * @param $request
			 *
			 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
			 */
			public function get_conversion_referrer( $request ) {
				$data      = array( 'conversions' => [], 'total_records' => 0 );
				$funnel_id = ( isset( $request['id'] ) && '' !== $request['id'] ) ? intval( $request['id'] ) : 0;

				$args = array(
					'funnel_id' => $funnel_id
				);

				$case_string          = WFFN_Conversion_Data::get_instance()->get_conversion_cases_string();
				$type_args            = array(
					'filter_key' => " {$case_string} , COUNT(case when type=2 then 1 else null end)as total_orders, ",
					'group_by'   => " GROUP BY referrers",
					'filters'    => isset( $request['filters'] ) ? $request['filters'] : '',
				);
				$data['filters_list'] = $this->filters_list( $args );
				$data['funnel_data']  = WFFN_REST_Funnels::get_instance()->get_funnel_data( $funnel_id );
				$get_conversion       = $this->get_conversion_data( $request, $type_args );

				if ( is_array( $get_conversion ) && isset( $get_conversion['db_error'] ) ) {
					return rest_ensure_response( $data );
				}
				if ( isset( $get_conversion['total_count'] ) ) {
					$data['total_records'] = $get_conversion['total_count'];
					unset( $get_conversion['total_count'] );
				}

				$data['conversions']                 = $get_conversion;
				$data['conversion_migration_status'] = WFFN_Core()->admin_notifications->is_conversion_migration_required();

				return rest_ensure_response( $data );
			}

			/**
			 * Hold Campaign Right
			 *
			 * @param $funnel_id
			 *
			 * @param $type
			 * @param $global
			 *
			 * @return array|false[]|object|stdClass[]
			 */
			public function get_campaign_count( $funnel_id = 0, $type = 'campaign', $global = false ) {

				$filter_key = " ( CASE WHEN type = 1 THEN 'Lead' WHEN type = 2 THEN 'Customer' ELSE 'Edd Order' END ) AS 'type', COUNT(source) as total_orders, source as order_id, ";

				$type_args = array(
					'filter_key' => $filter_key,
					'group_by'   => " GROUP BY id ",
				);

				if ( 'order' === $type ) {
					$type_args['other_filters'] = " AND source != 0 ";
				}


				$request = [];
				if ( false === $global ) {
					$request['id'] = $funnel_id;
				}
				$request['only_total_count'] = 'yes';

				return $this->get_conversion_data( $request, $type_args );
			}


			public function get_conversion_count( $funnel_id = 0, $global = false, $filters = [] ) {
				$case_string = WFFN_Conversion_Data::get_instance()->get_conversion_cases_string();
				$type_args   = array(
					'filter_key' => " {$case_string} , COUNT(case when type=2 then 1 else null end)as total_orders, ",
					'group_by'   => " GROUP BY referrers ",
					'filters'    => $filters,
				);
				$request     = [];
				if ( false === $global ) {
					$request['id'] = $funnel_id;
				}
				$request['only_total_count'] = 'yes';

				return $this->get_conversion_data( $request, $type_args );
			}

			public function get_conversion_export_data( $request, $filters = [] ) {
				$case_string = WFFN_Conversion_Data::get_instance()->get_conversion_cases_string();

				$type_args = array(
					'filter_key' => " {$case_string} , COUNT(case when type=2 then 1 else null end)as total_orders, ",
					'group_by'   => " GROUP BY referrers",
					'filters'    => $filters
				);

				return $this->get_conversion_data( $request, $type_args );
			}

			public function get_conversion_social_data( $request ) {

				global $wpdb;
				$table     = $wpdb->prefix . 'bwf_conversion_tracking';
				$funnel_id = ( isset( $request['id'] ) && '' !== $request['id'] ) ? intval( $request['id'] ) : 0;
				$date_col  = "timestamp";

				$search_filters = [];
				if ( isset( $request['filters'] ) ) {
					$search_filters = $this->prepare_filters( $request['filters'] );
				}

				/*
				 * Get total count for pagination
				 */
				$where_conditions = [ "1=1" ];
				if ( ! empty( $search_filters['funnels']['data'] ) ) {
					$where_conditions[] = "funnel_id IN (" . esc_sql( $search_filters['funnels']['data'] ) . ") ";
				} elseif ( $funnel_id > 0 ) {
					$where_conditions[] = "funnel_id =" . esc_sql( $funnel_id );
				} else {
					$where_conditions[] = "funnel_id != 0";
				}
				if ( isset( $search_filters['period'] ) ) {
					$where_conditions[] = "{$date_col} >= '" . esc_sql( $search_filters['period']['data']['after'] ) . "' AND {$date_col} < '" . esc_sql( $search_filters['period']['data']['before'] ) . "'";
				}


				if ( isset( $search_filters['search'] ) && ! empty( $search_filters['search']['data'] ) ) {
					$referrer = $search_filters['search']['data'];

					$refs_all  = WFFN_Common::get_refs();
					$other_set = $refs_all;
					$is_other  = false;

					if ( ! in_array( 'all', $referrer, true ) ) {
						$referrer_where = [];
						$domain_string  = [];

						foreach ( $referrer as $ref ) {

							if ( $ref === 'others' ) {
								$is_other = true;
								continue;
							}

							if ( $ref === 'direct' ) {
								$referrer_where[] = "(referrer = '') ";
								continue;
							}
							if ( array_key_exists( $ref, $refs_all ) ) {
								$domain_string[] = $refs_all[ $ref ];
								unset( $other_set[ $ref ] );
							}
						}

						if ( $is_other && ! empty( $other_set ) ) {
							$domain_string_other = implode( '|', WFACP_Common::array_flatten( $other_set ) );
							$domain_string_other = str_replace( '://', '', $domain_string_other );
							$regex               = '\\\b(?:' . $domain_string_other . ')\\\b';
							if ( empty( $referrer_where ) ) {
								$referrer_where[] = "(referrer NOT REGEXP '{$regex}') AND referrer != '' ";

							} else {
								$referrer_where[] = "(referrer NOT REGEXP '{$regex}') ";
							}
						}

						if ( ! empty( $domain_string ) ) {
							$domain_string    = implode( '|', WFACP_Common::array_flatten( $domain_string ) );
							$domain_string    = str_replace( '://', '', $domain_string );
							$regex            = '\\\b(?:' . $domain_string . ')\\\b';
							$referrer_where[] = "(referrer REGEXP '{$regex}')";
						}
						$where_conditions[] = '(' . implode( ' OR ', $referrer_where ) . ')';
					}


				}


				$where_query = implode( ' AND ', $where_conditions );

				$refs = WFFN_Common::get_refs( true );

				$new_results = [];
				foreach ( $refs as $ref_Key => $ref ) {

					$ref_index                 = strtolower( str_replace( " ", "-", $ref_Key ) );
					$new_results[ $ref_index ] = [
						'ref_key'           => $ref_index,
						'referrers'         => $ref_Key,
						'percentage_orders' => 0,
						'percentage_optins' => 0,
						'orders'            => 0,
						'optins'            => 0,
						'total_revenue'     => 0,
						'average_revenue'   => 0,
					];
				}
				$case_string = WFFN_Conversion_Data::get_instance()->get_conversion_cases_string( true );
				$sql         = "select {$case_string} ,count(case when type=1 then 1 else null end)as total_optins,count(case when type=2 then 1 else null end)as number_of_order,ROUND( SUM(value), 2 ) as total_revenue, ROUND( SUM(value), 2 ) as total_revenue, COALESCE(ROUND(SUM(value) / NULLIF(COUNT(case when type=2 then 1 else null end), 0), 2), 0) as average_revenue from {$table} WHERE {$where_query} GROUP by referrers";


				$results = $wpdb->get_results( $sql, ARRAY_A );//phpcs:ignore
				if ( empty( $results ) ) {
					return rest_ensure_response( $new_results );
				}

				$total_order  = array_sum( wp_list_pluck( $results, 'number_of_order' ) );
				$total_optins = array_sum( wp_list_pluck( $results, 'total_optins' ) );


				foreach ( $results as $result ) {
					$referrer = empty( $result['referrers'] ) ? 'direct' : $result['referrers'];
					$referrer = strtolower( str_replace( " ", "-", $referrer ) );
					if ( isset( $new_results[ $referrer ] ) ) {
						$new_results[ $referrer ]['percentage_orders'] = $this->get_percentage( $total_order, absint( $result['number_of_order'] ) );
						$new_results[ $referrer ]['percentage_optins'] = $this->get_percentage( $total_optins, absint( $result['total_optins'] ) );
						$new_results[ $referrer ]['orders']            = absint( $result['number_of_order'] );
						$new_results[ $referrer ]['optins']            = absint( $result['total_optins'] );
						$new_results[ $referrer ]['total_revenue']     = $result['total_revenue'];
						$new_results[ $referrer ]['average_revenue']   = $result['average_revenue'];
					}
				}

				return rest_ensure_response( $new_results );

			}

			public function get_conversion_data( $request, $type_args = array() ) {
				global $wpdb;
				$table            = $wpdb->prefix . 'bwf_conversion_tracking';
				$date_col         = "timestamp";
				$funnel_id        = ( isset( $request['id'] ) && '' !== $request['id'] ) ? intval( $request['id'] ) : 0;
				$need_total_count = isset( $request['only_total_count'] );
				$limit            = isset( $request['limit'] ) ? $request['limit'] : 10;
				$offset           = isset( $request['offset'] ) ? $request['offset'] : 0;
				$group_by         = isset( $type_args['group_by'] ) ? $type_args['group_by'] : '';
				$filter_key       = isset( $type_args['filter_key'] ) ? $type_args['filter_key'] : '';
				$other_filters    = isset( $type_args['other_filters'] ) ? $type_args['other_filters'] : '';
				$search_filters   = [];
				if ( ! empty( $type_args['filters'] ) ) {
					$search_filters = $this->prepare_filters( $type_args['filters'] );
				}


				$where_conditions = [ "1=1" ];
				$order_by_id      = ' ORDER BY id ASC ';
				if ( ! empty( $group_by ) ) {
					$order_by_id = '';
				}

				/*
				 * Get total count for pagination
				 */
				if ( ! empty( $search_filters['funnels']['data'] ) ) {
					$where_conditions[] = "funnel_id IN (" . esc_sql( $search_filters['funnels']['data'] ) . ") ";
				} elseif ( $funnel_id > 0 ) {
					$where_conditions[] = "funnel_id =" . esc_sql( $funnel_id );
				}
				if ( isset( $search_filters['period'] ) ) {
					$where_conditions[] = "{$date_col} >= '" . esc_sql( $search_filters['period']['data']['after'] ) . "' AND {$date_col} < '" . esc_sql( $search_filters['period']['data']['before'] ) . "'";
				}

				if ( isset( $search_filters['search'] ) && ! empty( $search_filters['search']['data'] ) ) {
					$referrer = $search_filters['search']['data'];

					$refs_all  = WFFN_Common::get_refs();
					$other_set = $refs_all;
					$is_other  = false;
					if ( ! in_array( 'all', $referrer, true ) ) {
						$referrer_where = [];
						$domain_string  = [];

						foreach ( $referrer as $ref ) {

							if ( $ref === 'others' ) {
								$is_other = true;
								continue;
							}

							if ( $ref === 'direct' ) {
								$referrer_where[] = "(referrer = '') ";
								continue;
							}
							if ( array_key_exists( $ref, $refs_all ) ) {
								$domain_string[] = $refs_all[ $ref ];
								unset( $other_set[ $ref ] );
							}
						}
						if ( $is_other && ! empty( $other_set ) ) {
							$domain_string_other = implode( '|', WFACP_Common::array_flatten( $other_set ) );
							$domain_string_other = str_replace( '://', '', $domain_string_other );
							$regex               = '\\\b(?:' . $domain_string_other . ')\\\b';
							if ( empty( $referrer_where ) ) {
								$referrer_where[] = "(referrer NOT REGEXP '{$regex}') AND referrer != '' ";

							} else {
								$referrer_where[] = "(referrer NOT REGEXP '{$regex}') ";

							}

						}


						if ( ! empty( $domain_string ) ) {
							$domain_string    = implode( '|', WFACP_Common::array_flatten( $domain_string ) );
							$domain_string    = str_replace( '://', '', $domain_string );
							$regex            = '\\\b(?:' . $domain_string . ')\\\b';
							$referrer_where[] = "(referrer REGEXP '{$regex}')";
						}
						$where_conditions[] = '(' . implode( ' OR ', $referrer_where ) . ')';
					}


				}


				$where_query = implode( ' AND ', $where_conditions );
				if ( ! empty( $other_filters ) ) {
					$where_query .= " {$other_filters}";
				}
				$count_query = "SELECT " . $filter_key . " ROUND( AVG(value), 2 ) as average_revenue FROM " . $table . " WHERE {$where_query}  " . $group_by;

				$count_result = $wpdb->get_results( $count_query, ARRAY_A ); //phpcs:ignore
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}
				if ( $need_total_count ) {
					return [ 'total_count' => count( $count_result ) ];
				}

				/**
				 * Get data query
				 */
				$query = "SELECT " . $filter_key . " ROUND( SUM(value), 2 ) as total_revenue, COALESCE(ROUND(SUM(value) / NULLIF(COUNT(case when type=2 then 1 else null end), 0), 2), 0) as average_revenue,count(case when type=1 then 1 else null end)as total_optins, COUNT(`id`) as total_count FROM " . $table . " WHERE {$where_query} {$group_by}  {$order_by_id} ORDER BY total_count DESC LIMIT {$offset}, {$limit}";

				$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}
				foreach ( $result as &$results ) {
					if ( isset( $results['referrers'] ) && $results['referrers'] === 'Direct' ) {
						$results['referrers'] = __( 'Direct', 'funnel-builder' );
					}
				}
				$result['total_count'] = is_array( $count_result ) ? count( $count_result ) : 0;

				return $result;
			}

			public function get_referrer_nice_name( $url ) {
				if ( strpos( $url, 'facebook.com' ) ) {
					return 'facebook';
				}
				if ( strpos( $url, 'google.com' ) ) {
					return 'google';
				}
				if ( strpos( $url, 'instagram.com' ) ) {
					return 'instagram';
				}
				if ( strpos( $url, 'youtube.com' ) || strpos( $url, 'youtu.be' ) ) {
					return 'youtube';
				}
				if ( strpos( $url, 'tiktok.com' ) ) {
					return 'tiktok';
				}
				if ( strpos( $url, 'linkedIn.com' ) ) {
					return 'linkedIn';
				} else {
					return 'others';
				}
			}

			/**
			 * @param $steps
			 *
			 * @return mixed
			 */
			public function maybe_add_ab_variants( $steps ) {
				$temp_steps = [];
				foreach ( $steps as $step ) {
					$step_type = $step['type'];
					$step_id   = $step['id'];
					$get_step  = WFFN_Pro_Core()->steps->get_integration_object( $step_type );
					if ( ! $get_step instanceof WFFN_Pro_Step ) {
						continue;
					}
					$temp_steps[] = $step;
					$variant_ids  = $get_step->maybe_get_ab_variants( $step_id );
					if ( is_array( $variant_ids ) && count( $variant_ids ) > 0 ) {
						foreach ( $variant_ids as $variant_id ) {
							$ab_control = get_post_meta( $variant_id, '_bwf_ab_control', true );
							if ( empty( $ab_control ) ) {
								$temp_steps[] = array( 'type' => $step_type, 'control' => $step_id, 'is_variant' => 'yes', 'id' => (string) $variant_id, 'substeps' => [] );
							}
						}
					}
				}

				return $temp_steps;
			}

			/**
			 * @param $funnel
			 *
			 * @return bool
			 */
			public static function maybe_store_checkout_config( $funnel ) {
				if ( method_exists( 'WFFN_Common', 'get_store_checkout_id' ) && $funnel instanceof WFFN_Funnel && WFFN_Common::get_store_checkout_id() === absint( $funnel->get_id() ) ) {
					return true;
				}

				return false;
			}

			public function filters_list( $args = array() ) {
				$filters = array(
					array(
						"type"  => "sticky",
						"rules" => array(
							array(
								"slug"          => "period",
								"title"         => __( "Time Period", 'funnel-builder' ),
								"type"          => "date-range",
								"op_label"      => __( "Time Period", 'funnel-builder' ),
								"required"      => array( "rule", "data" ),
								"readable_text" => "{{value /}}",
							),
							array(
								'slug'          => 'search',
								'title'         => __( 'Referrer' ),
								"type"          => "checklist",
								'multiple'      => true,
								'options'       => WFFN_Common::get_refs( true, true ),
								'op_label'      => __( 'Referrer' ),
								'required'      => array( 'data' ),
								'readable_text' => '{{rule /}} - {{value /}}',
								'is_pro'        => true,
							),
						)
					)
				);

				if ( ! isset( $args['funnel_id'] ) || intval( $args['funnel_id'] ) === 0 ) {
					$filters[0]['rules'][] = array(
						'slug'          => 'funnels',
						'title'         => __( 'Funnel' ),
						'type'          => 'search',
						'api'           => '/funnels/?s={{search}}&search_filter',
						'op_label'      => __( 'Funnel' ),
						'required'      => array( 'data' ),
						'readable_text' => '{{rule /}} - {{value /}}',
					);
				}
				if ( isset( $args['utm_id'] ) ) {
					$utm_filter          = array(
						array(
							"slug"            => "utm_campaign",
							"title"           => __( "UTM Campaign" ),
							"type"            => "search",
							"op_label"        => __( "UTM Campaign" ),
							"required"        => array( "data" ),
							"api"             => "/funnel-utms/?utm_key=utm_campaign&s={{search}}",
							"readable_text"   => "{{rule /}} - {{value /}}",
							"default_options" => array()
						),
						array(
							"slug"            => "utm_source",
							"title"           => __( "UTM Source" ),
							"type"            => "search",
							"op_label"        => __( "UTM Source" ),
							"required"        => array( "data" ),
							"api"             => "/funnel-utms/?utm_key=utm_source&s={{search}}",
							"readable_text"   => "{{rule /}} - {{value /}}",
							"default_options" => array()
						),
						array(
							"slug"            => "utm_medium",
							"title"           => __( "UTM Medium" ),
							"type"            => "search",
							"op_label"        => __( "UTM Medium" ),
							"required"        => array( "data" ),
							"api"             => "/funnel-utms/?utm_key=utm_medium&s={{search}}",
							"readable_text"   => "{{rule /}} - {{value /}}",
							"default_options" => array()
						),
						array(
							"slug"            => "utm_content",
							"title"           => __( "UTM Content" ),
							"type"            => "search",
							"op_label"        => __( "UTM Content" ),
							"required"        => array( "data" ),
							"api"             => "/funnel-utms/?utm_key=utm_content&s={{search}}",
							"readable_text"   => "{{rule /}} - {{value /}}",
							"default_options" => array()
						),
						array(
							"slug"            => "utm_term",
							"title"           => __( "UTM Term" ),
							"type"            => "search",
							"op_label"        => __( "UTM Term" ),
							"required"        => array( "data" ),
							"api"             => "/funnel-utms/?utm_key=utm_term&s={{search}}",
							"readable_text"   => "{{rule /}} - {{value /}}",
							"default_options" => array()
						),
						array(
							"slug"          => "conversions",
							"title"         => __( "Conversions" ),
							"type"          => "select",
							"val_label"     => __( "Type" ),
							"required"      => array( "data" ),
							"options"       => array(
								"all"    => __( "All" ),
								"orders" => __( "Orders" ),
								"optins" => __( "Optins" )
							),
							"readable_text" => "{{value /}}"
						),
					);
					$filters[0]['rules'] = array_merge( $filters[0]['rules'], $utm_filter );

				}

				return $filters;
			}

			public function prepare_filters( $filters ) {
				if ( ! is_array( $filters ) ) {
					$filters = json_decode( $filters, true );
				}

				$single_data = [];
				if ( ! is_array( $filters ) || count( $filters ) === 0 ) {
					return $single_data;
				}
				foreach ( $filters as $filter ) {
					$single_data[ $filter['filter'] ] = $filter;
					if ( is_array( $filter['data'] ) ) {
						$ids = array_column( $filter['data'], 'id' );
						if ( ! empty( $ids ) ) {
							$single_data[ $filter['filter'] ]['data'] = implode( ',', $ids );
						}
					}
				}

				return $single_data;
			}

			public function get_global_funnel_list( $request ) {
				$response = array(
					'status'  => true,
					'msg'     => __( 'success', 'funnel-builder-powerpack' ),
					'funnels' => $this->get_all_funnels( $request )
				);

				return rest_ensure_response( $response );
			}

			public function get_all_funnels( $request ) {

				if ( isset( $request['overall'] ) ) {
					$start_date = '';
					$end_date   = '';
				} else {
					$start_date = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
					$end_date   = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );
				}
				$limit       = ( isset( $request['limit'] ) && '' !== $request['limit'] ) ? $request['limit'] : get_option( 'posts_per_page' );
				$page_no     = isset( $request['page_no'] ) ? intval( $request['page_no'] ) : 1;
				$funnel_type = isset( $request['funnel_type'] ) ? $request['funnel_type'] : 'sale';
				$offset      = intval( $limit ) * intval( $page_no - 1 );
				$limit_str   = ( $page_no > 0 ) ? " LIMIT " . ( $offset ) . " , " . $limit : '';

				global $wpdb;
				$sales_funnels = [];
				$lead_funnels  = [];
				$all_funnels   = array(
					'data'        => array(),
					'total_count' => 0
				);

				/**
				 * get all sales funnel data
				 */
				if ( 'sale' === $funnel_type ) {

					$funnel_count = "SELECT COUNT( id) AS total_count FROM " . $wpdb->prefix . "bwf_funnels WHERE steps LIKE '%wc_%'";
					$funnel_count = $wpdb->get_var( $funnel_count );//phpcs:ignore

					if ( empty( $funnel_count ) || absint( $funnel_count ) === 0 ) {
						return $all_funnels;
					}

					/**
					 * get all funnel conversion from conversion table order by top conversion table
					 */
					$report_range = ( '' !== $start_date && '' !== $end_date ) ? " AND conv.timestamp >= '" . esc_sql( $start_date ) . "' AND conv.timestamp < '" . esc_sql( $end_date ) . "' " : '';

					$f_query = "SELECT funnel.id as fid, funnel.title as title, SUM( conv.value ) as total, 0 as views, COUNT(conv.ID) as conversion, 0 as conversion_rate
FROM " . $wpdb->prefix . "bwf_funnels AS funnel LEFT JOIN " . $wpdb->prefix . "bwf_conversion_tracking AS conv ON funnel.id = conv.funnel_id  AND conv.type = 2 " . $report_range . "
WHERE 1=1 AND funnel.steps LIKE '%wc_%' GROUP BY funnel.id ORDER BY SUM( conv.value ) DESC " . $limit_str;

					$get_funnels = $wpdb->get_results( $f_query, ARRAY_A ); //phpcs:ignore
					if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
						$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
						if ( false === $db_error['db_error'] ) {
							$sales_funnels = $get_funnels;
						}
					}

					/**
					 * calculate total funnels revenue
					 */
					if ( ! is_array( $sales_funnels ) || count( $sales_funnels ) === 0 ) {
						return $all_funnels;
					}

					/**
					 *  get funnel unique views and conversion rate
					 */
					$sales_funnels = $this->get_funnel_views_data( $sales_funnels, $start_date, $end_date );

					$all_funnels = array(
						'data'        => $sales_funnels,
						'total_count' => $funnel_count
					);

				}

				/**
				 * get all lead funnel data
				 */
				if ( 'lead' === $funnel_type ) {


					$lead_count = "SELECT COUNT( id) AS total_count FROM " . $wpdb->prefix . "bwf_funnels WHERE steps NOT LIKE '%wc_%'";
					$lead_count = $wpdb->get_var( $lead_count );//phpcs:ignore

					if ( empty( $lead_count ) || absint( $lead_count ) === 0 ) {
						return $all_funnels;
					}

					/**
					 * get all funnel conversion from conversion table order by top conversion table
					 */
					$report_range = ( '' !== $start_date && '' !== $end_date ) ? " AND conv.timestamp >= '" . esc_sql( $start_date ) . "' AND conv.timestamp < '" . esc_sql( $end_date ) . "' " : '';

					$l_query = "SELECT funnel.id as fid, funnel.title as title, 0 as total, 0 as views, COUNT(conv.id) as conversion, 0 as conversion_rate
FROM " . $wpdb->prefix . "bwf_funnels AS funnel LEFT JOIN " . $wpdb->prefix . "bwf_conversion_tracking AS conv ON funnel.id = conv.funnel_id AND conv.type = 1 " . $report_range . "
WHERE 1=1 AND funnel.steps NOT LIKE '%wc_%' GROUP BY funnel.id ORDER BY COUNT(conv.id) DESC " . $limit_str;

					$l_funnels = $wpdb->get_results( $l_query, ARRAY_A ); //phpcs:ignore
					if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
						$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
						if ( false === $db_error['db_error'] ) {
							$lead_funnels = $l_funnels;
						}
					}

					/**
					 * get all funnels by optin entries if deleted funnel exists
					 */
					if ( ! is_array( $lead_funnels ) || count( $lead_funnels ) === 0 ) {
						return $all_funnels;
					}

					/**
					 *  get funnel unique views and conversion rate
					 */
					$lead_funnels = $this->get_funnel_views_data( $lead_funnels, $start_date, $end_date );


					$all_funnels = array(
						'data'        => $lead_funnels,
						'total_count' => $lead_count
					);

				}

				return $all_funnels;

			}

			/**
			 * @param $total
			 * @param $number
			 *
			 * @return float|int
			 */
			public function get_percentage( $total, $number ) {
				if ( $total > 0 ) {
					return round( $number / ( $total / 100 ), 2 );
				} else {
					return 0;
				}
			}

			/**
			 * Add top five funnel unique views, conversion and conversion rate
			 *
			 * @param $total_sale
			 * @param $ids
			 *
			 * @return array|object|stdClass[]
			 */
			public function get_top_sales_funnels( $total_sale, $ids ) {
				global $wpdb;
				$ids          = esc_sql( implode( ',', $ids ) );
				$report_query = "SELECT report.object_id as fid, report.views AS views, COUNT( DISTINCT stats.ID) as conversion, (CASE WHEN report.views != 0 THEN ROUND(COUNT( DISTINCT stats.ID) * 100/report.views, 2 ) ELSE 0 END) as conversion_rate FROM ( SELECT object_id, type, SUM( no_of_sessions ) AS views FROM " . $wpdb->prefix . "wfco_report_views WHERE type = 7 GROUP by object_id ORDER BY object_id ) AS report
				JOIN " . $wpdb->prefix . "wfacp_stats AS stats ON report.object_id = stats.fid
				WHERE report.object_id IN (" . $ids . ") AND report.type = 7 GROUP BY report.object_id ORDER BY FIELD(report.object_id," . $ids . ")";

				$report_data = $wpdb->get_results( $report_query, ARRAY_A ); //phpcs:ignore
				if ( is_array( $report_data ) && count( $report_data ) > 0 ) {
					foreach ( $report_data as &$v ) {
						$search = array_search( intval( $v['fid'] ), array_map( 'intval', wp_list_pluck( $total_sale, 'fid' ) ), true );
						if ( false !== $search && isset( $total_sale[ $search ] ) ) {
							$v['total'] = $total_sale[ $search ]['total'];
							$v['title'] = ! empty( $total_sale[ $search ]['title'] ) ? $total_sale[ $search ]['title'] : '#' . $v['fid'];
						} else {
							$v['total'] = 0;
							$v['title'] = '#' . $v['fid'];
						}
					}

					return $report_data;
				}

				return $total_sale;

			}

			public function get_funnel_views_data( $funnels, $start_date, $end_date ) {
				global $wpdb;

				$ids = array_unique( wp_list_pluck( $funnels, 'fid' ) );

				$report_range = ( '' !== $start_date && '' !== $end_date ) ? " AND date >= '" . $start_date . "' AND date < '" . $end_date . "' " : '';
				$view_query   = "SELECT object_id as fid , SUM(COALESCE(no_of_sessions, 0)) AS views FROM " . $wpdb->prefix . "wfco_report_views WHERE type = 7 AND object_id IN (" . esc_sql( implode( ',', $ids ) ) . ") " . $report_range . " GROUP BY object_id";
				$report_data  = $wpdb->get_results( $view_query, ARRAY_A ); //phpcs:ignore
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( false === $db_error['db_error'] ) {
						if ( is_array( $report_data ) && count( $report_data ) > 0 ) {
							/**
							 * prepare data for sales funnels and add views and conversion
							 */
							$funnels = array_map( function ( $item ) use ( $report_data ) {
								$search_view = array_search( intval( $item['fid'] ), array_map( 'intval', wp_list_pluck( $report_data, 'fid' ) ), true );
								if ( false !== $search_view && isset( $report_data[ $search_view ]['views'] ) && absint( $report_data[ $search_view ]['views'] ) > 0 ) {
									$item['views']           = absint( $report_data[ $search_view ]['views'] );
									$item['conversion_rate'] = $this->get_percentage( absint( $item['views'] ), $item['conversion'] );
								} else {
									$item['views']           = '0';
									$item['conversion']      = '0';
									$item['conversion_rate'] = '0';
								}

								return $item;
							}, $funnels );
						}
					}
				}

				return $funnels;

			}

			/**
			 * Retrieves UTM campaign filters and records for frontend display.
			 *
			 * Filters a specific set of UTM-related filters and includes UTM campaign records,
			 * organized for frontend use.
			 *
			 * @param WP_REST_Request $request The request object.
			 *
			 * @return WP_REST_Response Response containing UTM filters and records.
			 */
			public function get_global_utms( $request ) {
				$utm_id = ! empty( $request['id'] ) ? (int) $request['id'] : 0;

				// Define required Filters
				$required_filters = [ 'period', 'utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_term', 'funnels', 'conversions' ];
				$all_filters      = $this->filters_list( [ 'utm_id' => $utm_id, 'funnel_id' => ( isset( $request['funnel_id'] ) ) ? $request['funnel_id'] : 0 ] );
				$filtered_rules   = array_filter( $all_filters[0]['rules'] ?? [], fn( $rule ) => in_array( $rule['slug'], $required_filters, true ) );

				usort( $filtered_rules, fn( $a, $b ) => array_search( $a['slug'], $required_filters ) <=> array_search( $b['slug'], $required_filters ) );

				$response = [
					'filters_list' => [ [ 'type' => 'sticky', 'rules' => array_values( $filtered_rules ) ] ],
					'records'      => [],
					'total_count'  => 0
				];

				$utm_campaigns = apply_filters( 'wffn_utm_campaign_campaigns', $request );
				if ( isset( $utm_campaigns['records'], $utm_campaigns['total_count'] ) ) {
					$response['records']     = $utm_campaigns['records'];
					$response['total_count'] = (int) $utm_campaigns['total_count'];
				}

				if ( isset( $request['funnel_id'] ) ) {
					$response['funnel_data'] = WFFN_REST_Funnels::get_instance()->get_funnel_data( $request['funnel_id'] );

				}

				return rest_ensure_response( $response );
			}

		}

		WFFN_REST_API_EndPoint::get_instance();
	}
}
