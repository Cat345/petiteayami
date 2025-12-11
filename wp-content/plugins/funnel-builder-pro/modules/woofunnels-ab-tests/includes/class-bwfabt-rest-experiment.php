<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BWFABT_REST_Experiment
 *
 * * @extends WP_REST_Controller
 */
if ( ! class_exists( 'BWFABT_REST_Experiment' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_REST_Experiment extends WP_REST_Controller {

		public static $_instance = null;

		/**
		 * Route base.
		 *
		 * @var string
		 */

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'experiment';
		protected $rest_base_id = 'experiment/(?P<experiment_id>[\d]+)';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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

			register_rest_route( $this->namespace, '/' . $this->rest_base, array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_experiment' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => array(
						'offset'  => array(
							'description'       => __( 'Offset', 'woofunnels-ab-tests' ),
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'limit'   => array(
							'description'       => __( 'Limit', 'woofunnels-ab-tests' ),
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'status'  => array(
							'description'       => __( 'Experiment status', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						's'       => array(
							'description'       => __( 'Search experiment', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'control' => array(
							'description'       => __( 'Control ID', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'data'    => array(
							'description'       => __( 'Get all experiment with variant data', 'woofunnels-ab-tests' ),
							'default'           => false,
							'type'              => 'boolean',
							'sanitize_callback' => 'bwfabt_string_to_bool',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'default' => array(
							'description'       => __( 'Create default variant if not exists', 'woofunnels-ab-tests' ),
							'default'           => false,
							'type'              => 'boolean',
							'sanitize_callback' => 'bwfabt_string_to_bool',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_new_experiment' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'control' => array(
							'description'       => __( 'Control ID', 'woofunnels-ab-tests' ),
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'title'   => array(
							'description'       => __( 'Title', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'type'    => array(
							'description'       => __( 'Variant Type', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/controls/', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_controls' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => array(
						'type' => array(
							'description'       => __( 'Variant Type', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_single_experiment' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [],
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id, array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_experiment' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'title'    => array(
							'description'       => __( 'Title', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'desc'     => array(
							'description'       => __( 'Description', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'traffics' => array(
							'description'       => __( 'Traffics', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => array( $this, 'sanitize_custom' ),
						),
					),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/start/', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_experiment' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/pause/', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'stop_experiment' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/reset/', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_stats' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id, array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_experiment' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/winner/(?P<winner_id>[\d]+)', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'choose_winner' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/step/search', array(

				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_entity' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => array(
						's'     => array(
							'description'       => __( 'search term', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'type'  => array(
							'description'       => __( 'Type of step', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
						),
						'limit' => array(
							'description'       => __( 'Set search limit', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						)
					),
				),
			) );
		}

		public function get_read_api_permission_check() {
			if ( BWFABT_Core()->role->user_access( 'funnel', 'read' ) ) {
				return true;
			}

			return false;

		}

		public function get_write_api_permission_check() {
			if ( BWFABT_Core()->role->user_access( 'funnel', 'write' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Get all experiments
		 */
		public function get_all_experiment( WP_REST_Request $request ) {

			$result = [
				'status'  => false,
				'message' => __( 'No experiment found', 'woofunnels-ab-tests' )
			];

			$args            = [];
			$status          = $request->get_param( 'status' );
			$control         = $request->get_param( 'control' );
			$get_data        = $request->get_param( 'data' );
			$experiment_type = '';

			if ( isset( $status ) ) {
				$args['status'] = $status;
			}
			if ( isset( $control ) ) {
				$args['control'] = $control;
			}
			if ( count( $args ) === 0 ) {
				$args['experiments'] = 'all';
			}
			$args['order']   = 'DESC';
			$args['funnels'] = 'DESC';

			$experiments = BWFABT_Core()->admin->get_experiments( $args );

			$create_experiment = true;

			if ( is_array( $experiments ) && isset( $experiments['found_posts'] ) && $experiments['found_posts'] > 0 ) {

				foreach ( $experiments['items'] as &$experiment ) {
					$experiment_type = $experiment['type'];
					$statuses        = BWFABT_Core()->admin->get_experiment_statuses();

					$experiment['status'] = isset( $statuses[ $experiment['status'] ] ) ? $statuses[ $experiment['status'] ] : '';
					if ( 'aero' === $experiment_type || 'offer' === $experiment_type ) {
						$bwb_admin_setting_obj     = BWF_Admin_General_Settings::get_instance();
						$step_base                 = ( 'offer' === $experiment_type ) ? 'wfocu_page_base' : 'checkout_page_base';
						$experiment['global_slug'] = $bwb_admin_setting_obj->get_option( $step_base );
					}
					if ( $get_data === true ) {
						$data = $this->get_experiment( $experiment['id'] );
						if ( ! isset( $data['variants'] ) || ! is_array( $data['variants'] ) || 0 === count( $data['variants'] ) ) {
							$del_experiment = BWFABT_Core()->admin->get_experiment( $experiment['id'] );
							$type           = $del_experiment->get_type();
							if ( ! empty( $type ) ) {
								$del_experiment->delete();
							}
						}

						$experiment['variants'] = isset( $data['variants'] ) ? $data['variants'] : [];

						if ( count( $experiment['variants'] ) > 0 && isset( $experiment['global_slug'] ) && empty( $experiment['global_slug'] ) ) {
							foreach ( $experiment['variants'] as &$variant_data ) {
								$post_data = get_post( $variant_data['id'] );
								if ( $post_data instanceof WP_Post ) {
									$variant_data['post_slug'] = $post_data->post_name;
								}
							}
						}

					}
				}

				$exp_state = array_filter( $experiments['items'], function ( $a ) {
					$statuses = BWFABT_Core()->admin->get_experiment_statuses();
					if ( $a['status'] !== 'Completed' && in_array( $a['status'], $statuses, true ) ) {
						return true;

					}
				} );

				if ( is_array( $exp_state ) && count( $exp_state ) > 0 ) {
					$create_experiment = false;
				}

				$result                  = $experiments;
				$result['control_id']    = ! empty( $control ) ? absint( $control ) : 0;
				$result['exp_create']    = $create_experiment;
				$result['type']          = $experiment_type;
				$result['step_slug']     = $this->get_step_slug_type( $experiment_type );
				$result['control_title'] = ( isset( $control ) && absint( $control ) > 0 ) ? html_entity_decode( get_the_title( $control ) ) : '';
				$result['status']        = true;
				$result['message']       = __( 'Get all experiments', 'woofunnels-ab-tests' );

				if ( isset( $offset ) ) {
					$result['offset'] = $offset;
				}
				if ( isset( $limit ) ) {
					$result['limit'] = $limit;
				}
			} else {
				$result['control_id']    = ! empty( $control ) ? absint( $control ) : 0;
				$result['control_title'] = ( isset( $control ) && absint( $control ) > 0 ) ? html_entity_decode( get_the_title( $control ) ) : '';
				$result['status']        = true;
				$result['exp_create']    = $create_experiment;
				$result['found_posts']   = 0;
				$result['message']       = __( 'Get all experiments', 'woofunnels-ab-tests' );
			}

			/** return message for dynamic show message on design screen */
			$result['head_message'] = __( 'A/B test with {variant_count} variants was started on {start_date}', 'woofunnels-ab-tests' );
			
			$funnel_id = get_post_meta( $control, '_bwf_in_funnel', true );

			if ( empty( $funnel_id ) ) {
				$upsell_id = get_post_meta( $control, '_funnel_id', true );
				$funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );
			}
			$funnel = WFFN_Core()->admin->get_funnel( $funnel_id );
			if ( $funnel instanceof WFFN_Funnel ) {
				$result['funnel_id']    = absint( $funnel_id );
				$result['funnel_title'] = $funnel->get_title();
			}

			return rest_ensure_response( $result );
		}

		/**
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function add_new_experiment( $request ) {

			$experiment_data            = array();
			$experiment_data['title']   = isset( $request['title'] ) ? bwfabt_clean( $request['title'] ) : '';
			$experiment_data['desc']    = isset( $request['desc'] ) ? bwfabt_clean( $request['desc'] ) : '';
			$experiment_data['control'] = isset( $request['control'] ) ? bwfabt_clean( $request['control'] ) : '';
			$experiment_data['type']    = isset( $request['type'] ) ? bwfabt_clean( $request['type'] ) : '';
			$default                    = isset( $request['default'] ) ? $request['default'] : false;

			$experiment_data['date_added']      = BWFABT_Core()->get_dataStore()->now();
			$experiment_data['status']          = '1';
			$experiment_data['date_started']    = '0000-00-00 00:00';
			$experiment_data['last_reset_date'] = '0000-00-00 00:00';
			$experiment_data['date_completed']  = '0000-00-00 00:00';

			$resp = array(
				'status'  => false,
				'message' => __( 'Unable to create test.', 'woofunnels-ab-tests' ),
			);

			if ( true === $default ) {
				$type  = $this->get_control_type( $experiment_data['control'] );
				$title = $this->get_experiment_title( $experiment_data['control'] );
			} else {
				$type  = $experiment_data['type'];
				$title = $experiment_data['title'];

			}

			if ( empty( $type ) || empty( $title ) ) {
				$resp['message'] = __( 'No experiment type or title provided.', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			if ( 'upstroke' === $type ) {
				$resp['message'] = __( 'Unable to create Upsell test', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			if ( empty( $type ) || empty( $title ) ) {
				$resp['message'] = __( 'No experiment type or title provided.', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			if ( ! empty( $experiment_data['control'] ) && $experiment_data['control'] > 0 ) {
				$args = [ 'control' => $experiment_data['control'] ];

				if ( true === $default ) {
					$experiments = BWFABT_Core()->admin->get_experiments( $args );

					if ( is_array( $experiments ) && isset( $experiments['found_posts'] ) && $experiments['found_posts'] > 0 ) {
						$experiment_data['title'] = $title . ' ' . ( absint( $experiments['found_posts'] ) + 1 );
					} else {
						$experiment_data['title'] = $title . ' ' . 1;
					}
				}

				$experiment_data['type'] = $type;

				$existing = BWFABT_Core()->admin->maybe_existing_control( $experiment_data['control'], $experiment_data['type'] );

				if ( $existing === true ) {
					$resp['message'] = __( 'An experiment is already running on this original variant. Please select other variant.', 'woofunnels-ab-tests' );

					return rest_ensure_response( $resp );
				}
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			$experiment_id  = $get_controller->save_experiment( $experiment_data );

			if ( $experiment_id > 0 ) {
				$experiment   = BWFABT_Core()->admin->get_experiment( $experiment_id );
				$variant_data = array();

				$resp['ex_id'] = $experiment_id;

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					$variant_data['variant_id'] = $experiment_data['control'];
					$variant_data['traffic']    = "100.00";
					$variant_data['control']    = true;
					$variant_data['control_id'] = $experiment->get_control();

					$variant_data = $get_controller->add_variant( $variant_data );
				}

				$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;

				if ( $variant_id > 0 ) {

					$resp['variant_id'] = $variant_id;

					$variant_data['variant_title'] = $get_controller->get_variant_title( $variant_id );
					$variant_data['traffic']       = isset( $variant_data['traffic'] ) ? $variant_data['traffic'] : "0.00";

					$control    = $experiment->add_variant( $variant_data );
					$control_id = $control->get_id();
					if ( $control_id > 0 ) {
						$resp['status']       = true;
						$resp['control_id']   = $control_id;
						$resp['redirect_url'] = add_query_arg( array( 'page' => 'bwf_ab_tests', 'section' => 'variants', 'edit' => $experiment_id, ), admin_url( 'admin.php' ) );

						$resp['message'] = __( 'Successfully created', 'woofunnels-ab-tests' );
						$resp            = BWFABT_Core()->controllers->maybe_get_step_list_data( $control_id, $resp );
					} else {
						$resp['message'] = __( 'Unable to add variant', 'woofunnels-ab-tests' );
					}
				}
			}

			return rest_ensure_response( $resp );
		}

		/**
		 * Get all selected type controls
		 */
		public function get_controls( WP_REST_Request $request ) {
			$type = isset( $request['type'] ) ? bwfabt_clean( $request['type'] ) : '';

			$resp = array(
				'status'   => false,
				'controls' => array(),
			);

			if ( empty( $type ) ) {
				$resp['message'] = __( 'Unable to get controls, due to empty experiment type.', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			$term           = 'get_all';
			$controls       = $get_controller->get_controls( $term );

			if ( is_array( $controls ) && count( $controls ) > 0 ) {
				$controls = BWFABT_Core()->admin->add_existing_to_controls( $controls, $type );
			}

			$resp['status']   = true;
			$resp['controls'] = $controls;
			$resp['title']    = $get_controller->get_title();

			return rest_ensure_response( $resp );
		}

		/**
		 * Get single experiment by id
		 */
		public function get_single_experiment( WP_REST_Request $request ) {

			$experiment_id = $request->get_param( 'experiment_id' );
			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;

			return rest_ensure_response( $this->get_experiment( $experiment_id ) );

		}


		public function get_experiment( $experiment_id ) {

			$result = [
				'status'  => false,
				'message' => __( 'No experiment found', 'woofunnels-ab-tests' )
			];

			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;

			if ( $experiment_id > 0 ) {

				$experiment_obj = BWFABT_Core()->admin->get_experiment( $experiment_id );
				$experiment     = (array) $experiment_obj;
				if ( ! empty( $experiment ) ) {
					$statuses             = BWFABT_Core()->admin->get_experiment_statuses();
					$experiment['status'] = isset( $statuses[ $experiment['status'] ] ) ? $statuses[ $experiment['status'] ] : '';

					$variants = [];
					if ( isset( $experiment['variants'] ) && $experiment['variants'] > 0 ) {
						foreach ( $experiment['variants'] as $variant_id => $item ) {

							$variant        = [];
							$variant_obj    = new BWFABT_Variant( $variant_id, $experiment_obj );
							$get_controller = BWFABT_Core()->controllers->get_integration( $experiment_obj->get_type() );
							$heading_urls   = $get_controller->get_variant_heading_url( $variant_obj, $experiment_obj );

							$variant['id']        = $variant_id;
							$variant['edit']      = $heading_urls;
							$variant['title']     = html_entity_decode( $get_controller->get_variant_title( $variant_obj->get_id() ) );
							$variant['desc']      = $get_controller->get_variant_desc( $variant_obj->get_id() );
							$variant['traffic']   = $variant_obj->get_traffic();
							$variant['control']   = $variant_obj->get_control();
							$variant['winner']    = $variant_obj->get_winner();
							$variant['active']    = $get_controller->is_variant_active( $variant_obj->get_id() );
							$variant['view_link'] = $get_controller->get_entity_view_link( $variant_obj->get_id() );
							$variants[]           = $variant;

						}

					}


					$control = array_filter( $variants, function ( $c ) {
						return ! empty( $c['control'] );
					} );


					if ( is_array( $control ) && count( $control ) > 0 ) {
						foreach ( $control as $key => $con ) {
							unset( $variants[ $key ] );
						}
					}

					$experiment['variants'] = array_merge( $control, $variants );

					return $experiment;
				}
			}

			return $result;
		}

		/**
		 * Updating traffic for an experiment
		 */
		public function update_experiment( WP_REST_Request $request ) {
			$experiment_data = [];

			$experiment_id            = $request->get_param( 'experiment_id' );
			$title                    = $request->get_param( 'title' );
			$desc                     = $request->get_param( 'desc' );
			$traffics                 = $request->get_param( 'traffics' );
			$experiment_id            = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;
			$experiment_data['id']    = $experiment_id;
			$experiment_data['title'] = isset( $title ) ? bwfabt_clean( $title ) : '';
			$experiment_data['desc']  = isset( $desc ) ? bwfabt_clean( $desc ) : '';
			$traffics                 = isset( $traffics ) ? array_map( 'bwfabt_clean', $traffics ) : '';

			$resp = array(
				'status'  => false,
				'exp_id'  => $experiment_id,
				'message' => __( 'Unable to update the test.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id === 0 ) {
				return rest_ensure_response( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
			$type       = $experiment->get_type();

			if ( is_array( $traffics ) && count( $traffics ) > 0 ) {

				$active_variants = $experiment->get_active_variants();
				BWFABT_Core()->admin->log( "Active variants for experiment id: $experiment_id, Type: $type and active variants: " . print_r( $active_variants, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );
					$updated        = $get_controller->update_traffics( $traffics, $experiment_id );
				} else {
					$updated = $experiment->update_traffic( $traffics );
				}
				$resp['message'] = __( 'Unable to update the traffic.', 'woofunnels-ab-tests' );

				if ( $updated ) {
					$resp['status']  = true;
					$resp['message'] = __( 'Traffic updated successfully.', 'woofunnels-ab-tests' );
				}

				BWFABT_Core()->admin->log( "Updated: $updated traffic for experiment id: $experiment_id: " . print_r( $traffics, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return rest_ensure_response( $resp );
			}

			if ( empty( $experiment_data['title'] ) ) {
				$resp['message'] = __( 'Empty title or no experiment id.', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			if ( ! empty( $type ) ) {
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );
				$updated        = $get_controller->save_experiment( $experiment_data );
			} else {
				$updated = $experiment->save( $experiment_data );
			}

			if ( false !== $updated ) {
				$resp['status']  = true;
				$resp['message'] = __( 'Test updated successfully.', 'woofunnels-ab-tests' );
				$resp            = BWFABT_Core()->controllers->maybe_get_step_list_data( $experiment->get_control(), $resp );

			}

			return rest_ensure_response( $resp );
		}

		/**
		 * Check readiness and start an experiment
		 */
		public function start_experiment( WP_REST_Request $request ) {

			$experiment_id = $request->get_param( 'experiment_id' );
			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;

			$resp = array(
				'status'           => false,
				'exp_id'           => $experiment_id,
				'readiness_state'  => 3,
				'no_variant'       => false,
				'inactive_variant' => false,
				'invalid_traffic'  => false,
				'message'          => __( 'Unable to start this test.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id < 1 ) {
				$resp['message'] = __( 'No experiment id', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "No experiment $experiment_id in check readiness. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return rest_ensure_response( $resp );
			}


			$experiment        = BWFABT_Core()->admin->get_experiment( $experiment_id );
			$type              = $experiment->get_type();
			$inactive_variants = false;

			if ( empty( $type ) ) {
				$resp['message'] = __( 'No controller type is defined', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "Empty type: $type for experiment: $experiment_id in check readiness. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return rest_ensure_response( $resp );
			}


			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			$variants       = $experiment->get_active_variants( true );


			if ( count( $variants ) < 2 ) {
				$inactive_variants        = true;
				$resp['inactive_variant'] = true;
				$resp['readiness_state']  = 2;
			}
			$traffic_total = 0;


			if ( false === $get_controller->is_variant_active( $experiment->get_control() ) ) {
				$inactive_variants        = true;
				$resp['inactive_variant'] = true;
				$resp['readiness_state']  = 2;

			}

			$select_template = [];
			$select_product  = [];

			foreach ( $variants as $variant_id => $variant ) {

				/**
				 * Check if any variant having zero traffic
				 */
				$step_data = $this->get_step_data( $type, $variant_id );

				if ( ! is_array( $step_data ) || empty( $step_data['selected_type'] ) || ( isset( $step_data['template_active'] ) && 'no' === $step_data['template_active'] ) ) {
					$select_template[] = html_entity_decode( $get_controller->get_variant_title( $variant_id ) );
				}

				if ( is_array( $step_data ) && isset( $step_data['is_product'] ) && false === $step_data['is_product'] ) {
					$select_product[] = html_entity_decode( $get_controller->get_variant_title( $variant_id ) );
				}

				if ( floatval( 0 ) === floatval( $variant['traffic'] ) ) {
					$resp['invalid_traffic'] = true;
					$resp['readiness_state'] = 2;
				}

				$traffic_total = round( floatval( $traffic_total ) + floatval( $variant['traffic'] ), 2 );
			}

			if ( is_array( $select_template ) && 0 < count( $select_template ) ) {

				if ( count( $select_template ) > 1 ) {
					$s_last         = array_pop( $select_template );
					$variant_titles = implode( ', ', $select_template ) . ' and ' . $s_last;
				} else {
					$variant_titles = implode( ', ', $select_template );
				}
				$resp['inactive_variant'] = true;
				$resp['readiness_state']  = 2;
				$resp['message']          = __( "Template missing for steps: {$variant_titles}", 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			if ( is_array( $select_product ) && 0 < count( $select_product ) ) {

				if ( count( $select_product ) > 1 ) {
					$p_last         = array_pop( $select_product );
					$variant_titles = implode( ', ', $select_product ) . ' and ' . $p_last;
				} else {
					$variant_titles = implode( ', ', $select_product );
				}

				$resp['inactive_variant'] = true;
				$resp['readiness_state']  = 2;
				$resp['message']          = __( "Product missing for steps: {$variant_titles}", 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			/**
			 * Check if total is not 100
			 */
			$valid_traffic = ( floatval( 100 ) === floatval( ceil( $traffic_total ) ) || floatval( 100 ) === floatval( floor( $traffic_total ) ) );

			if ( ! $valid_traffic ) {
				$resp['invalid_traffic'] = true;
				$resp['readiness_state'] = 2;

			}


			if ( 2 === $resp['readiness_state'] ) {

				if ( $inactive_variants === true ) {
					$resp['message'] = __( 'At least one Original & Variant step should be active.', 'woofunnels-ab-tests' );

				} else {
					$resp['invalid_traffic'] = true;
					$resp['readiness_state'] = 2;
					$resp['message']         = __( 'Invalid traffic', 'woofunnels-ab-tests' );

				}

				return rest_ensure_response( $resp );
			}


			BWFABT_Core()->admin->log( "Readiness response for experiment id: $experiment_id: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			$type = $experiment->get_type();

			if ( ! empty( $type ) ) {
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );
				$success        = $get_controller->start_experiment( $experiment );
			} else {
				$success = $experiment->start();
			}

			if ( $success ) {
				$resp['status']          = $success;
				$resp['readiness_state'] = 3;
				$resp['message']         = __( 'Test started successfully.', 'woofunnels-ab-tests' );

				$resp = BWFABT_Core()->controllers->maybe_get_step_list_data( $experiment->get_control(), $resp );

				BWFABT_Core()->admin->log( "Experiment started $experiment_id in start_experiment for type: $type Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return rest_ensure_response( $resp );
			}

			BWFABT_Core()->admin->log( "Experiment not started $experiment_id, Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return rest_ensure_response( $resp );

		}

		/**
		 * Stopping an experiment
		 */
		public function stop_experiment( WP_REST_Request $request ) {

			$experiment_id = $request->get_param( 'experiment_id' );
			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;

			$success = false;
			$resp    = array(
				'status'  => $success,
				'exp_id'  => $experiment_id,
				'message' => __( 'Unable to stop this test.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id === 0 ) {
				$resp['message'] = __( 'No experiment id', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "No experiment $experiment_id in stop_experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return rest_ensure_response( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

			$type = $experiment->get_type();
			if ( ! empty( $type ) ) {
				$resp['type']   = $type;
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );
				$success        = $get_controller->stop_experiment( $experiment );
			} else {
				$success = $experiment->stop();
			}

			if ( $success ) {
				$resp['status']  = $success;
				$resp['message'] = __( 'Test Paused successfully.', 'woofunnels-ab-tests' );
				$resp            = BWFABT_Core()->controllers->maybe_get_step_list_data( $experiment->get_control(), $resp );

				BWFABT_Core()->admin->log( "Experiment paused $experiment_id in stop experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return rest_ensure_response( $resp );
			}

			$resp['message'] = __( "Success:  $success.", 'woofunnels-ab-tests' );
			BWFABT_Core()->admin->log( "Experiment pause result for $experiment_id in stop experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return rest_ensure_response( $resp );
		}

		/**
		 * Resetting stats for query to start from last_reset_date
		 */
		public static function reset_stats( WP_REST_Request $request ) {

			$experiment_id = $request->get_param( 'experiment_id' );
			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;

			$resp = array(
				'status'  => false,
				'exp_id'  => $experiment_id,
				'message' => __( 'Unable to reset stats.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id < 1 ) {
				$resp['message'] = __( 'No experiment id provided', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
			$type       = $experiment->get_type();

			if ( ! empty( $type ) ) {
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );
				if ( $get_controller instanceof BWFABT_Controller ) {
					$get_controller->reset_stats( $experiment );
					$experiment->reset_stats();
				}
			}
			$success = true;
			if ( $success ) {
				$resp['status']       = true;
				$resp['message']      = __( 'Analytics reset successfully.', 'woofunnels-ab-tests' );
				$resp['redirect_url'] = admin_url( 'admin.php?page=bwf_ab_tests&section=variants&edit=' . $experiment_id );
			}

			return rest_ensure_response( $resp );

		}

		/**
		 * Deleting an experiment
		 */
		public function delete_experiment( WP_REST_Request $request ) {

			$experiment_id = $request->get_param( 'experiment_id' );
			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;
			$deleted       = false;

			$resp = array(
				'status'  => $deleted,
				'exp_id'  => $experiment_id,
				'message' => __( 'Unable to delete the test.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
				$type       = $experiment->get_type();
				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );
					$deleted        = $get_controller->delete_experiment( $experiment );
					$resp           = BWFABT_Core()->controllers->maybe_get_step_list_data( $experiment->get_control(), $resp );

				}
			}

			if ( $deleted ) {
				$resp['status']  = $deleted;
				$resp['message'] = __( 'Successfully deleted test.', 'woofunnels-ab-tests' );

			}

			return rest_ensure_response( $resp );
		}

		/**
		 * Choosing winner
		 */
		public function choose_winner( WP_REST_Request $request ) {
			$experiment_id = $request->get_param( 'experiment_id' );
			$winner_id     = $request->get_param( 'winner_id' );
			$selected      = false;

			$resp = array(
				'status'    => false,
				'exp_id'    => $experiment_id,
				'winner_id' => $winner_id,
				'message'   => __( 'Unable to declare the winner.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 && $winner_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

				$type = $experiment->get_type();
				if ( ! empty( $type ) ) {

					if ( absint( $experiment->status ) === 4 ) {
						$resp['message'] = __( 'Winner already declared', 'woofunnels-ab-tests' );

						return rest_ensure_response( $resp );
					}

					$get_controller     = BWFABT_Core()->controllers->get_integration( $type );
					$control_id         = $experiment->get_control();
					$resp['control_id'] = $control_id;

					$winner_variant = new BWFABT_Variant( $winner_id, $experiment );
					$is_control     = $winner_variant->get_control();


					$control_variant = new BWFABT_Variant( $control_id, $experiment ); //c1
					$variant_data    = array(
						'variant_id'    => $control_id,
						'control_id'    => $control_id,
						'experiment_id' => $experiment_id,
						'traffic'       => $control_variant->get_traffic(),
						'control'       => true,
					);

					$variant_data            = $get_controller->duplicate_variant( $variant_data ); //n1
					$variant_data['control'] = true;
					$resp['winner_title']    = html_entity_decode( $get_controller->get_variant_title( $winner_id ) );

					$duplicated_variant_id = $variant_data['variant_id'];

					if ( $duplicated_variant_id > 0 ) {
						$resp['duplicated_variant_id'] = $duplicated_variant_id;

						$new_variant    = $experiment->add_variant( $variant_data );
						$new_variant_id = $new_variant->get_id();
						if ( $new_variant_id > 0 ) {
							$resp['new_variant_id'] = $new_variant_id;

							$experiment      = BWFABT_Core()->admin->get_experiment( $experiment_id );
							$active_variants = $experiment->get_active_variants();

							BWFABT_Core()->admin->log( "Choose winner: Winner_id: $winner_id, New Variant id: $new_variant_id, Original: $control_id, Experiment: $experiment_id, control: $is_control, Act_variants: " . print_r( $active_variants, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

							foreach ( array_keys( $active_variants ) as $variantid ) {

								if ( intval( $variantid ) === intval( $control_id ) ) { //Creating new control with copied old control
									$transfered = $get_controller->transfer_control( $control_variant, $experiment, $new_variant );
									if ( false === $transfered ) {
										$resp['message'] = __( 'Unable to transfer the control', 'woofunnels-ab-tests' );

										return rest_ensure_response( $resp );
									}
									$deleted = $get_controller->delete_variant( $control_variant, $experiment, false ); //Only unset from experiment but don't delete actual post

									if ( false === $deleted ) {
										$resp['message'] = __( "Unable to delete the old control variant $control_id", 'woofunnels-ab-tests' );

										return rest_ensure_response( $resp );
									}

									$variantid = $new_variant_id;
								}

								$draft = $get_controller->draft_variant( $experiment, $variantid );
								if ( false === $draft ) {
									$resp['message'] = __( "Unable to draft the variant $variantid", 'woofunnels-ab-tests' );
								}
							}

							update_post_meta( $control_id, '_experiment_status', 'not_active' );
							$get_controller->copy_control_data_to_new_control( array( 'control_id' => $control_id ), $new_variant_id );

							if ( ! $is_control ) {
								$get_controller->copy_winner_data_to_control( $control_id, $winner_id );
							}

							$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
							$selected   = $experiment->choose_winner( $winner_id, $new_variant_id );
						}
					}

					$resp = BWFABT_Core()->controllers->maybe_get_step_list_data( $experiment->get_control(), $resp );

				}
			}

			if ( $selected ) {
				$resp['status']  = true;
				$resp['message'] = __( 'Winner selected successfully.', 'woofunnels-ab-tests' );
			}
			BWFABT_Core()->admin->log( "Winner response for experiment id: $experiment_id, winner id: $winner_id: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return rest_ensure_response( $resp );
		}


		/**
		 * @param WP_REST_Request $request
		 *
		 * Search funnel steps
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function search_entity( WP_REST_Request $request ) {
			$search  = $request->get_param( 's' );
			$type    = $request->get_param( 'type' );
			$limit   = $request->get_param( 'limit' );
			$designs = $this->get_step_designs( $type, $search, $limit );

			return rest_ensure_response( $designs );
		}


		/**
		 * @param $type
		 * @param $term
		 *
		 * @return array
		 */
		public function get_step_designs( $type, $term, $limit ) {

			$slug         = '';
			$active_pages = array();
			$steps        = array();

			if ( class_exists( 'WFOCU_Core' ) && ( 'upstroke' === $type ) ) {
				$slug = WFOCU_Common::get_funnel_post_type_slug();
			}

			if ( class_exists( 'WFOCU_Core' ) && ( 'offer' === $type ) ) {
				$slug = WFOCU_Common::get_offer_post_type_slug();
			}

			if ( class_exists( 'WFACP_Common' ) && ( 'aero' === $type ) ) {
				$slug = WFACP_Common::get_post_type_slug();
			}

			if ( class_exists( 'WFOB_Common' ) && ( 'order_bump' === $type ) ) {
				$slug = WFOB_Common::get_bump_post_type_slug();
			}

			if ( function_exists( 'WFFN_Core' ) && class_exists( 'WFFN_Core' ) ) {
				if ( 'landing' === $type ) {
					$slug = WFFN_Core()->landing_pages->get_post_type_slug();
				}

				if ( 'optin' === $type ) {
					$slug = WFOPP_Core()->optin_pages->get_post_type_slug();
				}

				if ( 'optin_ty' === $type ) {
					$slug = WFOPP_Core()->optin_ty_pages->get_post_type_slug();
				}

				if ( 'thank_you' === $type ) {
					$slug = WFFN_Core()->thank_you_pages->get_post_type_slug();
				}

			}

			if ( '' !== $slug ) {
				$active_pages = $this->search_funnel_steps( $slug, $term, $limit );
			}

			$data = [];

			if ( count( $active_pages ) > 0 ) {

				foreach ( $active_pages as $active_page ) {
					if ( class_exists( 'WFFN_Core' ) && ! empty( $active_page->funnel_id ) ) {
						$data   = array(
							'ID'   => $active_page->ID,
							'name' => $active_page->post_title,
						);
						$funnel = new WFFN_Funnel( $active_page->funnel_id );
						if ( ! empty( $funnel->get_id() ) ) {
							$data['funnel_id']    = absint( $active_page->funnel_id );
							$data['funnel_title'] = $funnel->get_title();
						}
					}
					if ( count( $data ) > 0 ) {
						$steps[] = $data;
					}
				}
			}

			return $steps;
		}

		/**
		 * @param $slug
		 * @param $term
		 *
		 * @return array|int[]|WP_Post[]
		 */
		public function search_funnel_steps( $slug, $term, $limit ) {

			$args = apply_filters( 'bwfabt_search_custom_post_type', array(
				'post_type'   => array( $slug ),
				'post_status' => 'any',

			) );

			if ( ! empty( $limit ) ) {
				$args['posts_per_page'] = $limit;
			}

			if ( ! empty( $term ) ) {
				if ( is_numeric( $term ) ) {
					$args['p'] = $term;
				} else {
					$args['s'] = $term;
				}
			}
			$query_result = new WP_Query( $args );

			if ( $query_result->have_posts() ) {
				foreach ( $query_result->posts as &$p ) {
					if ( 'wfocu_offer' === $p->post_type ) {
						$upsell_id    = get_post_meta( $p->ID, '_funnel_id', true );
						$p->funnel_id = get_post_meta( $upsell_id, '_bwf_in_funnel', true );
					} else {
						$p->funnel_id = get_post_meta( $p->ID, '_bwf_in_funnel', true );
					}
				}


				return $query_result->posts;
			}

			return array();
		}

		public function get_control_type( $post_id ) {
			$post_type  = get_post_type( $post_id );
			$wffn_posts = array(
				'aero'       => 'wfacp_checkout',
				'upstroke'   => 'wfocu_funnel',
				'order_bump' => 'wfob_bump',
				'landing'    => 'wffn_landing',
				'thank_you'  => 'wffn_ty',
				'optin'      => 'wffn_optin',
				'optin_ty'   => 'wffn_oty',
				'offer'      => 'wfocu_offer',
			);

			return array_search( $post_type, $wffn_posts, true );
		}

		/*
		 * return step slug for manage react url
		 */
		public function get_step_slug_type( $experiment_type ) {
			switch ( $experiment_type ) {
				case 'optin_ty':
					$experiment_type = 'optin-confirmation';
					break;
				case 'order_bump':
					$experiment_type = 'bump';
					break;
				case 'aero':
					$experiment_type = 'checkout';
					break;
				case 'thank_you':
					$experiment_type = 'thankyou';
					break;
				case 'upstroke':
					$experiment_type = 'upsell';
					break;
				default:
					return $experiment_type;

			}

			return $experiment_type;

		}

		public function get_step_data( $type, $step_id ) {
			$design = [];
			if ( empty( $type ) || 0 === absint( $step_id ) ) {
				return $design;
			}
			switch ( $type ) {
				case 'landing':
					$page_instance = WFFN_Landing_Pages::get_instance();

					return $page_instance->get_page_design( $step_id );
				case 'thank_you':
					$page_instance = WFFN_Thank_You_WC_Pages::get_instance();

					return $page_instance->get_page_design( $step_id );
				case 'optin':
					$page_instance = WFFN_Optin_Pages::get_instance();

					return $page_instance->get_page_design( $step_id );
				case 'optin_ty':
					$page_instance = WFFN_Optin_TY_Pages::get_instance();

					return $page_instance->get_page_design( $step_id );
				case 'offer':
					if ( class_exists( 'WFOCU_Core' ) ) {
						$offer_data = WFOCU_Core()->offers->get_offer( $step_id );
						$offer_data = $this->sanitize_custom( wp_json_encode( $offer_data ), 1 );

						if ( ! is_array( $offer_data ) ) {
							return $design;
						}

						if ( ! empty( $offer_data['template'] ) && ! empty( $offer_data['template_group'] ) ) {
							$design = array(
								'selected'      => $offer_data['template'],
								'selected_type' => $offer_data['template_group']
							);
						}

						if ( empty( $offer_data['products'] ) || 0 === count( $offer_data['products'] ) ) {
							$design['is_product'] = false;
						}
					}

					return $design;
				case 'aero':
					if ( class_exists( 'WFACP_Common' ) ) {
						$design = WFACP_Common::get_post_meta_data( $step_id, '_wfacp_selected_design', true );

						if ( empty( $design ) || ! is_array( $design ) ) {
							return [];
						}

						$funnel_id = get_post_meta( $step_id, '_bwf_in_funnel', true );

						if ( empty( $funnel_id ) || ( absint( $funnel_id ) !== WFFN_Common::get_store_checkout_id() ) ) {
							$sel_product = WFACP_Common::get_page_product( $step_id );
							if ( empty( $sel_product ) || 0 === count( $sel_product ) ) {
								$design['is_product'] = false;
							}
						}
					}

					return $design;
				case 'order_bump':
					if ( class_exists( 'WFOB_Common' ) ) {
						$design = WFOB_Common::get_post_meta_data( $step_id, '_wfob_selected_products', true );

						if ( empty( $design ) || ! is_array( $design ) ) {
							return [];
						}
						/*
						 * bump step not have any specific key for template
						 * so check product data
						 */
						if ( is_array( $design ) ) {
							$design = array(
								'selected'      => 'template',
								'selected_type' => 'template_group'
							);
						}

						$sel_product = WFOB_Common::get_bump_products( $step_id );
						if ( empty( $sel_product ) || 0 === count( $sel_product ) ) {
							$design['is_product'] = false;
						}
					}

					return $design;
				default:
					return $design;
			}
		}

		public function get_experiment_title( $post_id ) {

			return ( isset( $post_id ) && absint( $post_id ) > 0 ) ? html_entity_decode( get_the_title( $post_id ) ) . '- Test' : '';

		}

		public function sanitize_custom( $data ) {

			return json_decode( $data, true );
		}

	}

	if ( ! function_exists( 'bwfabt_rest_experiment' ) ) {

		function bwfabt_rest_experiment() {  //@codingStandardsIgnoreLine
			return BWFABT_REST_Experiment::get_instance();
		}
	}

	bwfabt_rest_experiment();
}