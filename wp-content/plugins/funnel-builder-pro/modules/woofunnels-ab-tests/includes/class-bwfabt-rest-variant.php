<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BWFABT_REST_Variant
 *
 * * @extends WP_REST_Controller
 */
if ( ! class_exists( 'BWFABT_REST_Variant' ) ) {

	#[AllowDynamicProperties]
	class BWFABT_REST_Variant extends WP_REST_Controller {

		public static $_instance = null;

		/**
		 * Route base.
		 *
		 * @var string
		 */

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'experiment/(?P<experiment_id>[\d]+)';

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

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/variant', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_variant' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => array(
						'variant_title' => array(
							'description'       => __( 'Variant title', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'variant_desc'  => array(
							'description'       => __( 'Variant description', 'woofunnels-ab-tests' ),
							'type'              => 'string',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/variant/(?P<variant_id>[\d]+)', array(
				'args' => array(
					'variant_id' => array(
						'description' => __( 'Unique variant id.', 'woofunnels-ab-tests' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_variant' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/draft/(?P<variant_id>[\d]+)', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'draft_variant' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),
			) );

			register_rest_route( $this->namespace, '/' . $this->rest_base . '/publish/(?P<variant_id>[\d]+)', array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish_variant' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					'args'                => [],
				),
			) );

		}

		public function get_write_api_permission_check() {
			if ( BWFABT_Core()->role->user_access( 'funnel', 'write' ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Create or duplicate a variant
		 */
		public function add_variant( WP_REST_Request $request ) {

			$variant_data  = array();
			$experiment_id = $request->get_param( 'experiment_id' );
			$variant_title = $request->get_param( 'variant_title' );
			$variant_desc  = $request->get_param( 'variant_desc' );
			$variant_id    = $request->get_param( 'duplicate_id' );

			$variant_data['experiment_id'] = isset( $experiment_id ) ? absint( bwfabt_clean( $experiment_id ) ) : 0;
			$variant_data['variant_id']    = isset( $variant_id ) ? absint( bwfabt_clean( $variant_id ) ) : 0;
			$variant_data['variant_title'] = isset( $variant_title ) ? bwfabt_clean( $variant_title ) : '';
			$variant_data['variant_desc']  = isset( $variant_desc ) ? bwfabt_clean( $variant_desc ) : '';
			$variant_data['traffic']       = "0.00";
			$variant_data['control']       = false;

			$experiment_id = $variant_data['experiment_id'];

			$resp = array(
				'status'        => false,
				'experiment_id' => $experiment_id,
				'message'       => __( 'Unable to create variant.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id === 0 ) {
				$resp['message'] = __( 'Empty variant title or no experiment id', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

			$type = $experiment->get_type();
			if ( empty( $type ) ) {
				$resp['message'] = __( 'Empty experiment type', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			$control_id                 = $experiment->get_control();
			$variant_data['control_id'] = $control_id;

			/**
			 * update experiment status in control
			 * Always add '_experiment_status' in control meta
			 * it handles that senior old control scenario which have not a meta
			 */
			if ( $experiment->get_status() !== BWFABT_Experiment::STATUS_START ) {
				update_post_meta( $control_id, '_experiment_status', 'not_active' );
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $type );

			if ( $variant_data['variant_id'] > 0 ) {
				$variant_data = $get_controller->duplicate_variant( $variant_data );
			} else {
				$variant_data = $get_controller->add_variant( $variant_data );
			}

			$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;

			if ( $variant_id > 0 ) {

				$resp['variant_id'] = $variant_id;

				$variant_data['variant_title'] = ! empty( $variant_data['variant_title'] ) ? $variant_data['variant_title'] : $get_controller->get_variant_title( $variant_id );
				$variant_data['traffic']       = isset( $variant_data['traffic'] ) ? $variant_data['traffic'] : "0.00";

				$variant = $experiment->add_variant( $variant_data );

				$variantid = $variant->get_id();
				if ( $variantid > 0 ) {
					$resp['data']          = array(
						"id"          => $variant_id,
						"edit"        => $get_controller->get_variant_heading_url( $variant, $experiment ),
						"view_link"   => $get_controller->get_entity_view_link( $variantid ),
						"title"       => html_entity_decode( $get_controller->get_variant_title( $variant->get_id() ) ),
						"desc"        => "",
						"traffic"     => "0.00",
						"row_actions" => $get_controller->get_variant_row_actions( $variant, $experiment ),
						"control"     => false,
						"winner"      => false,
						'active'      => $get_controller->is_variant_active( $variantid ),

					);
					$resp["id"]            = $variant_id;
					$resp['variant_order'] = count( $experiment->get_variants() ) - 1;
					$resp['status']        = true;
					$resp['message']       = sprintf( __( 'Variant %s created successfully.', 'woofunnels-ab-tests' ), $get_controller->get_variant_title( $variant_id ) );
					$resp['redirect_url']  = add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'variants',
						'edit'    => $experiment_id,
					), admin_url( 'admin.php' ) );
				}
			}

			return rest_ensure_response( $resp );
		}

		/**
		 * Deleting a variant
		 */
		public function delete_variant( WP_REST_Request $request ) {

			$deleted       = false;
			$experiment_id = $request->get_param( 'experiment_id' );
			$variant_id    = $request->get_param( 'variant_id' );

			$experiment_id = isset( $experiment_id ) ? bwfabt_clean( $experiment_id ) : 0;
			$variant_id    = isset( $variant_id ) ? bwfabt_clean( $variant_id ) : 0;


			$resp = array(
				'status'       => false,
				'exp_id'       => $experiment_id,
				'variant_id'   => $variant_id,
				'control_only' => false,
				'message'      => __( 'Unable to delete this variant.', 'woofunnels-ab-tests' ),
			);

			if ( $variant_id > 0 && $experiment_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
				$variant    = new BWFABT_Variant( $variant_id, $experiment );

				$type = $experiment->get_type();
				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );
					$deleted        = $get_controller->delete_variant( $variant, $experiment, true );

					$active_variants = $experiment->get_active_variants();
					if ( $deleted && count( $active_variants ) < 2 ) {
						$control_id    = key( $active_variants );
						$control_varnt = new BWFABT_Variant( $control_id, $experiment );
						$control_varnt->set_traffic( 100 );
						$updated_id = $control_varnt->save( [] );

						if ( abs( $updated_id ) === $control_id ) {
							$resp['control_only'] = true;
							$resp['control_id']   = $control_id;
						}
					}
				}
			}
			if ( $deleted ) {
				$resp['status']  = true;
				$resp['message'] = __( 'Variant deleted successfully!', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			return rest_ensure_response( $resp );
		}

		/**
		 * Removing a variant from a running test
		 */
		public function draft_variant( WP_REST_Request $request ) {
			$removed       = false;
			$experiment_id = $request->get_param( 'experiment_id' );
			$variant_id    = $request->get_param( 'variant_id' );

			$resp = array(
				'status'     => false,
				'exp_id'     => $experiment_id,
				'variant_id' => $variant_id,
				'error'    => __( 'Unable to remove this variant.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 && $variant_id > 0 ) {

				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );


				$type = $experiment->get_type();


				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );


					if ( $experiment->get_status()  === $experiment::STATUS_START) {

						if ( absint( $variant_id ) === absint( $experiment->get_control() ) ) {
							$resp['status']  = false;
							$resp['error'] = __( 'Unable to draft ', 'woofunnels-ab-tests' );
							$resp['description'] = __( 'At least one Original & Variant step should be active. Use Pause to stop the experiment.', 'woofunnels-ab-tests' );

							return rest_ensure_response( $resp );

						} else {
							$get_active_variants = $experiment->get_active_variants( true );
							if ( count( $get_active_variants ) === 2 ) {

								$resp['status']  = false;
								$resp['error'] = __( 'Unable to draft', 'woofunnels-ab-tests' );
								$resp['description'] = __( 'At least one Original & Variant step should be active. Use Pause to stop the experiment.', 'woofunnels-ab-tests' );

								return rest_ensure_response( $resp );

							}
						}


					}
					$removed = $get_controller->draft_variant( $experiment, $variant_id );
				}
			}
			if ( $removed ) {
				$resp['status']  = true;
				$resp['error'] = __( 'Variant draft successfully.', 'woofunnels-ab-tests' );


				return rest_ensure_response( $resp );
			}

			return rest_ensure_response( $resp );
		}


		/**
		 * publish a variant from a running test
		 */
		public function publish_variant( WP_REST_Request $request ) {
			$removed       = false;
			$experiment_id = $request->get_param( 'experiment_id' );
			$variant_id    = $request->get_param( 'variant_id' );

			$resp = array(
				'status'     => false,
				'exp_id'     => $experiment_id,
				'variant_id' => $variant_id,
				'message'    => __( 'Unable to publish this variant.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 && $variant_id > 0 ) {

				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );


				$type = $experiment->get_type();

				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );
					$removed        = $get_controller->publish_variant( $experiment, $variant_id );
				}
			}
			if ( $removed ) {
				$resp['status']  = true;
				$resp['message'] = __( 'Variant published successfully.', 'woofunnels-ab-tests' );

				return rest_ensure_response( $resp );
			}

			return rest_ensure_response( $resp );
		}

		public function sanitize_custom( $data ) {

			return json_decode( $data, true );
		}

	}

	if ( ! function_exists( 'bwfabt_rest_variant' ) ) {

		function bwfabt_rest_variant() {  //@codingStandardsIgnoreLine
			return BWFABT_REST_Variant::get_instance();
		}
	}

	bwfabt_rest_variant();
}
