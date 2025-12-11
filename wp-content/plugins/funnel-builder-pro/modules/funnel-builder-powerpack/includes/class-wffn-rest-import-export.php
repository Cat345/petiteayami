<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class WFFN_REST_Import_Export
 *
 * * @extends WFFN_REST_Import_Export
 */
if ( ! class_exists( 'WFFN_REST_Import_Export' ) ) {
	class WFFN_REST_Import_Export extends WP_REST_Controller {

		public static $_instance = null;

		/**
		 * Route base.
		 *
		 * @var string
		 */

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'funnels';
		protected $rest_base_id = 'funnels/(?P<funnel_id>[\d]+)';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_action( 'admin_post_bwf_contact_export_download', [ $this, 'download_export' ] );
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		/**
		 * Register the routes for taxes.
		 */
		public function register_routes() {
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/contact/export/column-head', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_column_head' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [],
				),
			) );
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/utms/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_utms_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/utms/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_utms_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			) );

			$this->export_handlers();
			$this->contacts_endpoints();
			$this->referrers_endpoints();
			$this->orders_endpoints();
			$this->leads_endpoints();
			$this->campaigns_endpoints();
		}

		private function export_handlers() {
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/export/(?P<export_id>[\d]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_status' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => []
				),
			) );
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/export/delete/', array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [
						'export_ids' => array(
							'description'       => __( 'Export ids', 'funnel-builder-powerpack' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						)
					],
				),
			) );
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/export/status', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_export_status' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [
						'export_type' => array(
							'description'       => __( 'Export Type', 'funnel-builder-powerpack' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						)
					],
				),
			) );
		}

		private function contacts_endpoints() {
			//GLobal
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/contact/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_contact_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'title'  => array(
							'description'       => __( 'title', 'funnel-builder-powerpack' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'fields' => array(
							'description'       => __( 'fields', 'funnel-builder-powerpack' ),
							'type'              => 'array',
							'validate_callback' => 'rest_validate_request_arg',
						)
					),
				),
			) );
			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/contact/export', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_export' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => array(
						'offset' => array(
							'description'       => __( 'Offset', 'funnel-builder-powerpack' ),
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'limit'  => array(
							'description'       => __( 'Limit', 'funnel-builder-powerpack' ),
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
						)
					),
				),
			) );
			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/contact/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_contact_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'title'  => array(
							'description'       => __( 'title', 'funnel-builder-powerpack' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'fields' => array(
							'description'       => __( 'fields', 'funnel-builder-powerpack' ),
							'type'              => 'array',
							'validate_callback' => 'rest_validate_request_arg',
						)
					),
				),
			) );
		}

		private function referrers_endpoints() {

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/referrers/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_referrer_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/referrers/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_referrer_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );


		}

		private function orders_endpoints() {

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/orders/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_orders_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/orders/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_orders_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );


		}

		private function leads_endpoints() {

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/leads/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_leads_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/leads/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_leads_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );


		}

		private function campaigns_endpoints() {

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/campaigns/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_campaign_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/campaigns/export/add', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_global_campaign_export' ),
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

		public function get_export_status( WP_REST_Request $request ) {

			$result = [
				'status'  => false,
				'message' => __( 'No Running Export data found', 'funnel-builder-powerpack' )
			];


			$export_type = $request->get_param( 'export_type' );
			$funnel_id   = $request->get_param( 'funnel_id' );
			$funnel_id   = absint( $funnel_id );

			if ( is_null( $export_type ) ) {
				return rest_ensure_response( $result );
			}
			$args = [
				'post_type'      => 'fk_export',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1
			];

			$args['meta_query'] = [
				[
					'key'     => 'export_type',
					'value'   => $export_type,
					'compare' => '='
				],
			];
			if ( $funnel_id > 0 ) {
				$args['meta_query']['relation'] = 'AND';
				$args['meta_query'][]           = [
					'key'     => 'fid',
					'value'   => $funnel_id,
					'compare' => '='
				];
			}

			$query = new WP_Query( $args );

			$ids = $query->get_posts();
			if ( ! empty( $ids ) ) {
				$result['status']           = true;
				$result['message']          = __( 'Export already running', 'funnel-builder-powerpack' );
				$last_export                = $this->export_status( [ 'export_id' => $ids[0] ], true );
				$result['last_export_data'] = $last_export['export'];
				$result['records']          = $ids;
			}

			return rest_ensure_response( $result );
		}

		public function get_column_head( WP_REST_Request $request ) {
			$resp                = [
				'status'  => true,
				'message' => __( 'Get all column heads', 'funnel-builder-powerpack' )
			];
			$resp['column_head'] = array(
				array(
					'contact' => array(
						'email'  => __( 'Email', 'funnel-builder-powerpack' ),
						'f_name' => __( 'First Name', 'funnel-builder-powerpack' ),
						'l_name' => __( 'Last Name', 'funnel-builder-powerpack' ),
					)
				),
				array(
					'checkout' => array(
						'checkout_order_id' => __( 'Order ID', 'funnel-builder-powerpack' ),
						'checkout_name'     => __( 'Checkout Name', 'funnel-builder-powerpack' ),
						'checkout_products' => __( 'Products Purchased', 'funnel-builder-powerpack' ),
						'checkout_total'    => __( 'Order Total', 'funnel-builder-powerpack' ),
						'checkout_coupon'   => __( 'Coupon Applied', 'funnel-builder-powerpack' ),
					)
				),
				array(
					'bump' => array(
						'bump_name'      => __( 'Bump Name', 'funnel-builder-powerpack' ),
						'bump_converted' => __( 'Bump Accepted', 'funnel-builder-powerpack' ),
						'bump_products'  => __( 'Product Purchased', 'funnel-builder-powerpack' ),
						'bump_total'     => __( 'Bump Total', 'funnel-builder-powerpack' )
					)
				),
				array(
					'upsell' => array(
						'offer_name'      => __( 'Offer Name', 'funnel-builder-powerpack' ),
						'offer_converted' => __( 'Offer Accepted', 'funnel-builder-powerpack' ),
						'offer_total'     => __( 'Offer Price', 'funnel-builder-powerpack' ),
					)
				),
				array(
					'optin' => array(
						'optin_custom' => __( 'Optin Custom Field', 'funnel-builder-powerpack' )
					)
				)
			);

			return rest_ensure_response( $resp );
		}

		public function get_all_export( WP_REST_Request $request ) {
			$result  = [
				'status'  => false,
				'message' => __( 'No Export data found', 'funnel-builder-powerpack' )
			];
			$exports = [];

			$funnel_id   = ! empty( $request->get_param( 'funnel_id' ) ) ? $request->get_param( 'funnel_id' ) : 0;
			$limit       = ! empty( $request->get_param( 'limit' ) ) ? $request->get_param( 'limit' ) : 0;
			$offset      = ! empty( $request->get_param( 'offset' ) ) ? $request->get_param( 'offset' ) : 0;
			$export_data = WFFN_Pro_Core()->exporter->get_export_import( $funnel_id, $limit, $offset );

			$exports['total_count'] = isset( $export_data['total'] ) ? intval( $export_data['total'] ) : 0;
			if ( is_array( $export_data['data'] ) && count( $export_data['data'] ) > 0 ) {
				$export_data = array_map( function ( $export ) {
					$temp = ! empty( $export['meta'] ) ? json_decode( $export['meta'], true ) : [];
					unset( $export['meta'] );
					if ( isset( $temp['file'] ) ) {
						if ( file_exists( WFFN_PRO_EXPORT_DIR . '/' . $temp['file'] ) ) {
							$temp['file'] = true;
						}
					}

					return ! empty( $temp ) ? array_merge( $export, $temp ) : $export;
				}, $export_data['data'] );

				$exports['data']        = $export_data;
				$exports['status']      = true;
				$exports['message']     = __( 'Got Export data', 'funnel-builder-powerpack' );
				$exports['funnel_data'] = wffn_rest_funnels()->get_funnel_data( $funnel_id );

				return rest_ensure_response( $exports );
			}

			return rest_ensure_response( $result );
		}


		public function add_contact_export( $request ) {
			$resp              = array(
				'status'  => false,
				'message' => __( 'Error in exporting contacts', 'funnel-builder-powerpack' )
			);
			$data              = [];
			$data['funnel_id'] = isset( $request['funnel_id'] ) ? $request['funnel_id'] : '';
			$data['title']     = isset( $request['title'] ) ? $request['title'] : '';
			$data['fields']    = isset( $request['fields'] ) ? $request['fields'] : [];
			$data['filters']   = isset( $request['filters'] ) ? $request['filters'] : [];

			$funnel_data = wffn_rest_funnels()->get_funnel_data( $data['funnel_id'] );
			if ( empty( $funnel_data ) ) {
				$resp['message'] = __( 'Not a valid funnel id', 'funnel-builder-powerpack' );

				return rest_ensure_response( $resp );
			}

			$resp['funnel_data'] = $funnel_data;


			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Contact::get_instance()->get_slug() );

			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$response = $export_contact->handle_export( $data );

			if ( ! $response['status'] ) {
				$response['funnel_data'] = $funnel_data;

				return rest_ensure_response( $response );
			}

			$response['funnel_id'] = $data['funnel_id'];

			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );

		}

		public function add_global_contact_export( $request ) {
			$resp            = array(
				'status'  => false,
				'message' => __( 'Error in exporting contacts', 'funnel-builder-powerpack' )
			);
			$data            = [];
			$data['title']   = isset( $request['title'] ) ? $request['title'] : '';
			$data['fields']  = isset( $request['fields'] ) ? $request['fields'] : [];
			$data['filters'] = isset( $request['filters'] ) ? $request['filters'] : [];

			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Contact_Global::get_instance()->get_slug() );

			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$response = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				return rest_ensure_response( $response );
			}

			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );


		}


		public function export_status( $request, $return = false ) {

			$resp = array(
				'status'  => false,
				'message' => __( 'Unable to get export data with id', 'funnel-builder-powerpack' )
			);

			$export_id = $request['export_id'];

			if ( $export_id ) {
				$export = WFFN_Pro_Core()->exporter->get_export_post_meta( $export_id, true );
				if ( $export ) {
					$temp = ! empty( $export['meta'] ) ? json_decode( $export['meta'], true ) : [];
					unset( $export['meta'] );
					if ( isset( $temp['file'] ) ) {
						$temp['filename'] = $temp['file'];
						if ( file_exists( WFFN_PRO_EXPORT_DIR . '/' . $temp['file'] ) ) {
							$temp['file'] = WFFN_PRO_EXPORT_URL . $temp['file'];
						} else {
							$temp['file'] = false;
						}
					} else {
						$temp['file'] = false;
					}
					$export = array_merge( $export, $temp );
					$resp   = array(
						'status'  => true,
						'message' => __( 'Successfully fetched export data with id', 'funnel-builder-powerpack' ),
						'export'  => $export
					);
				}
			}

			return $return ? $resp : rest_ensure_response( $resp );

		}


		/**
		 * export referrers Exports
		 *
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function add_referrer_export( $request ) {
			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting referrer', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Referrer::get_instance()->get_slug() );
			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data               = [];
			$data['funnel_id']  = isset( $request['funnel_id'] ) ? $request['funnel_id'] : '';
			$data['filters']    = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['fields']     = $export_contact->get_columns();
			$data['csv_header'] = [ 'header' => $data['fields'] ];
			$funnel_data        = wffn_rest_funnels()->get_funnel_data( $data['funnel_id'] );
			if ( empty( $funnel_data ) ) {
				$resp['message'] = __( 'Not a valid funnel id', 'funnel-builder-powerpack' );

				return rest_ensure_response( $resp );
			}

			$resp['funnel_data'] = $funnel_data;
			$response            = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				$response['funnel_data'] = $funnel_data;

				return rest_ensure_response( $response );
			}

			$response['funnel_id'] = $data['funnel_id'];
			$resp['status']        = true;
			$resp['message']       = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response']      = $response;

			return rest_ensure_response( $resp );

		}

		public function add_global_referrer_export( $request ) {
			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting referrer', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Referrer::get_instance()->get_slug() );
			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data                     = [];
			$data['fields']           = $export_contact->get_columns();
			$data['filters']          = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['csv_header']       = [ 'header' => $data['fields'] ];
			$data['is_global_export'] = 'yes';
			$data['title']            = __( 'Global Referrer Export' );
			$response                 = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				return rest_ensure_response( $response );
			}
			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );
		}


		/**
		 * export referrers Exports
		 *
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function add_orders_export( $request ) {
			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting orders', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Orders::get_instance()->get_slug() );
			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data              = [];
			$data['funnel_id'] = isset( $request['funnel_id'] ) ? $request['funnel_id'] : '';
			$data['filters']   = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['fields']    = $export_contact->get_columns();
			$funnel_data       = wffn_rest_funnels()->get_funnel_data( $data['funnel_id'] );
			if ( empty( $funnel_data ) ) {
				$resp['message'] = __( 'Not a valid funnel id', 'funnel-builder-powerpack' );

				return rest_ensure_response( $resp );
			}

			$resp['funnel_data'] = $funnel_data;
			$response            = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				$response['funnel_data'] = $funnel_data;

				return rest_ensure_response( $response );
			}

			$response['funnel_id'] = $data['funnel_id'];
			$resp['status']        = true;
			$resp['message']       = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response']      = $response;

			return rest_ensure_response( $resp );

		}

		public function add_global_orders_export( $request ) {

			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting Orders', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Orders_Global::get_instance()->get_slug() );

			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data                     = [];
			$data['fields']           = $export_contact->get_columns();
			$data['filters']          = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['is_global_export'] = 'yes';
			$data['title']            = __( 'Global Order Export' );
			$response                 = $export_contact->handle_export( $data );

			if ( ! $response['status'] ) {
				return rest_ensure_response( $response );
			}
			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );
		}

		/**
		 * export utms Exports
		 *
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function add_global_utms_export( $request ) {
			$resp        = [
				'status'  => false,
				'message' => __( 'Error in exporting UTM Campaigns', 'funnel-builder-powerpack' )
			];
			$export_utms = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_UTMs_Global::get_instance()->get_slug() );

			if ( ! $export_utms instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data                     = [];
			$data['fields']           = $export_utms->get_columns();
			$data['filters']          = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['is_global_export'] = 'yes';
			$data['title']            = __( 'Global UTM Campaign Export', 'funnel-builder-powerpack' );




			$response = $export_utms->handle_export( $data );

			if ( ! $response['status'] ) {
				return rest_ensure_response( $response );
			}

			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );
		}

		/**
		 * export referrers Exports
		 *
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function add_leads_export( $request ) {
			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting Leads', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Leads::get_instance()->get_slug() );
			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data              = [];
			$data['funnel_id'] = isset( $request['funnel_id'] ) ? $request['funnel_id'] : '';
			$data['filters']   = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['fields']    = $export_contact->get_columns();
			$funnel_data       = wffn_rest_funnels()->get_funnel_data( $data['funnel_id'] );
			if ( empty( $funnel_data ) ) {
				$resp['message'] = __( 'Not a valid funnel id', 'funnel-builder-powerpack' );

				return rest_ensure_response( $resp );
			}

			$resp['funnel_data'] = $funnel_data;
			$response            = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				$response['funnel_data'] = $funnel_data;

				return rest_ensure_response( $response );
			}

			$response['funnel_id'] = $data['funnel_id'];
			$resp['status']        = true;
			$resp['message']       = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response']      = $response;

			return rest_ensure_response( $resp );

		}

		public function add_global_leads_export( $request ) {

			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting Leads', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Leads_Global::get_instance()->get_slug() );

			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data                     = [];
			$data['fields']           = $export_contact->get_columns();
			$data['filters']          = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['is_global_export'] = 'yes';
			$data['title']            = __( 'Global Order Export' );
			$response                 = $export_contact->handle_export( $data );

			if ( ! $response['status'] ) {
				return rest_ensure_response( $response );
			}
			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );
		}

		public function add_campaign_export( $request ) {
			$resp           = array(
				'status'  => false,
				'message' => __( 'Error in exporting referrer', 'funnel-builder-powerpack' )
			);
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( WFFN_Export_Campaign::get_instance()->get_slug() );
			if ( ! $export_contact instanceof WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data                  = [];
			$data['funnel_id']     = isset( $request['funnel_id'] ) ? $request['funnel_id'] : '';
			$data['campaign_type'] = isset( $request['type'] ) ? $request['type'] : 'campaign';
			$data['fields']        = $export_contact->get_columns();
			$data['csv_header']    = [ 'header' => $data['fields'] ];

			$funnel_data = wffn_rest_funnels()->get_funnel_data( $data['funnel_id'] );

			if ( empty( $funnel_data ) ) {
				$resp['message'] = __( 'Not a valid funnel id', 'funnel-builder-powerpack' );

				return rest_ensure_response( $resp );
			}

			$resp['funnel_data'] = $funnel_data;
			$response            = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				$response['funnel_data'] = $funnel_data;

				return rest_ensure_response( $response );
			}

			$response['funnel_id'] = $data['funnel_id'];
			$resp['status']        = true;
			$resp['message']       = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response']      = $response;

			return rest_ensure_response( $resp );
		}

		public function delete_export( $request ) {

			$result     = [
				'status'  => false,
				'message' => __( 'Something wrong', 'funnel-builder-powerpack' ),
			];
			$export_ids = ! empty( $request->get_param( 'export_ids' ) ) ? $request->get_param( 'export_ids' ) : '';

			if ( empty( $export_ids ) ) {
				return rest_ensure_response( $result );
			}

			$export_ids = explode( ',', $export_ids );

			foreach ( $export_ids as $export_id ) {
				WFFN_Pro_Core()->exporter->delete_export_entry( $export_id );
			}

			$result = [
				'status'  => true,
				'message' => __( 'Deleted Successfully', 'funnel-builder-powerpack' )
			];

			return rest_ensure_response( $result );
		}

		/**
		 * @return void
		 */
		public function download_export() {
			check_ajax_referer( 'bwf_contact_export_download', '_nonce' );
			$export_id = isset( $_GET['id'] ) ? $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === $export_id ) {
				wp_die( esc_html__( 'Invalid Export ID.', 'funnel-builder-powerpack' ) );
			}

			$export = WFFN_Pro_Core()->exporter->get_export_post_meta( $export_id );
			if ( empty( $export['meta'] ) ) {
				wp_die( esc_html__( 'Export file is not ready yet.', 'funnel-builder-powerpack' ) );
			}

			$export_meta = json_decode( $export['meta'], true );
			if ( ! is_array( $export_meta ) || ! isset( $export_meta['file'] ) ) {
				wp_die( esc_html__( 'Export file url is missing.', 'funnel-builder-powerpack' ) );
			}

			$filename = WFFN_PRO_EXPORT_DIR . '/' . $export_meta['file'];
			if ( file_exists( $filename ) ) {
				// Define header information
				header( 'Content-Description: File Transfer' );
				header( 'Content-Type: application/octet-stream' );
				header( 'Cache-Control: no-cache, must-revalidate' );
				header( 'Expires: 0' );
				header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
				header( 'Content-Length: ' . filesize( $filename ) );
				header( 'Pragma: public' );

				// Clear system output buffer
				flush();

				// Read the size of the file
				readfile( $filename );
				exit;
			} else {
				wp_die( esc_html__( 'Sorry, we are unable to locate export file. Please try exporting again.', 'funnel-builder-powerpack' ) );

			}
		}

		public function sanitize_custom( $data, $skip_clean = 0 ) {
			$data = json_decode( $data, true );

			if ( 0 === $skip_clean ) {
				return wffn_clean( $data );
			}

			return $data;
		}
	}


	if ( ! function_exists( 'wffn_rest_import_export' ) ) {

		function wffn_rest_import_export() {  //@codingStandardsIgnoreLine
			return WFFN_REST_Import_Export::get_instance();
		}
	}

	WFFN_REST_Import_Export();
}
