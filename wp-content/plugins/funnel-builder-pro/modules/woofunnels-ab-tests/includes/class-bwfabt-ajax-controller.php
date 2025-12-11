<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_AJAX_Controller' ) ) {
	/**
	 * Class BWFABT_AJAX_Controller
	 * Handles All the request
	 */
	#[AllowDynamicProperties]
	class BWFABT_AJAX_Controller {

		public static function init() {

			/**
			 * Backend AJAX actions
			 */
			if ( is_admin() && BWFABT_Core()->role->user_access( 'funnel', 'write' ) ) {
				self::handle_admin_ajax();
			}
		}

		/**
		 * Handling admin ajax
		 */
		public static function handle_admin_ajax() {
			add_action( 'wp_ajax_bwfabt_add_new_experiment', array( __CLASS__, 'add_new_experiment' ) );
			add_action( 'wp_ajax_bwfabt_get_experiment_controls', array( __CLASS__, 'get_experiment_controls' ) );
			add_action( 'wp_ajax_bwfabt_page_search', array( __CLASS__, 'page_search' ) );
			add_action( 'wp_ajax_bwfabt_delete_experiment', array( __CLASS__, 'delete_experiment' ) );
			add_action( 'wp_ajax_bwfabt_update_experiment', array( __CLASS__, 'update_experiment' ) );
			add_action( 'wp_ajax_bwfabt_add_variant', array( __CLASS__, 'add_variant' ) );
			add_action( 'wp_ajax_bwfabt_duplicate_variant', array( __CLASS__, 'duplicate_variant' ) );
			add_action( 'wp_ajax_bwfabt_delete_variant', array( __CLASS__, 'delete_variant' ) );
			add_action( 'wp_ajax_bwfabt_update_traffic', array( __CLASS__, 'update_traffic' ) );
			add_action( 'wp_ajax_bwfabt_start_experiment', array( __CLASS__, 'start_experiment' ) );
			add_action( 'wp_ajax_bwfabt_check_readiness', array( __CLASS__, 'check_readiness' ) );
			add_action( 'wp_ajax_bwfabt_stop_experiment', array( __CLASS__, 'stop_experiment' ) );
			add_action( 'wp_ajax_bwfabt_draft_variant', array( __CLASS__, 'draft_variant' ) );
			add_action( 'wp_ajax_bwfabt_publish_variant', array( __CLASS__, 'publish_variant' ) );
			add_action( 'wp_ajax_bwfabt_choose_winner', array( __CLASS__, 'choose_winner' ) );
			add_action( 'wp_ajax_bwfabt_reset_stats', array( __CLASS__, 'reset_stats' ) );
		}

		/**
		 * Creating a new experiment
		 */
		public static function add_new_experiment() {
			check_admin_referer( 'bwfabt_add_new_experiment', '_nonce' );

			$experiment_data                    = array();
			$experiment_data['title']           = isset( $_POST['experiment_name'] ) ? bwfabt_clean( $_POST['experiment_name'] ) : '';
			$experiment_data['status']          = isset( $_POST['status'] ) ? bwfabt_clean( $_POST['status'] ) : '1';
			$experiment_data['desc']            = isset( $_POST['experiment_desc'] ) ? bwfabt_clean( $_POST['experiment_desc'] ) : '';
			$experiment_data['type']            = isset( $_POST['experiment_type'] ) ? bwfabt_clean( $_POST['experiment_type'] ) : '';
			$experiment_data['control']         = isset( $_POST['experiment_control'] ) ? bwfabt_clean( $_POST['experiment_control'] ) : '';
			$experiment_data['date_added']      = isset( $_POST['date_added'] ) ? bwfabt_clean( $_POST['date_added'] ) : BWFABT_Core()->get_dataStore()->now();
			$experiment_data['date_started']    = isset( $_POST['date_started'] ) ? bwfabt_clean( $_POST['date_started'] ) : '0000-00-00 00:00';
			$experiment_data['last_reset_date'] = isset( $_POST['last_reset_date'] ) ? bwfabt_clean( $_POST['last_reset_date'] ) : '0000-00-00 00:00';
			$experiment_data['date_completed']  = isset( $_POST['date_completed'] ) ? bwfabt_clean( $_POST['date_completed'] ) : '0000-00-00 00:00';

			$resp = array(
				'status' => false,
				'msg'    => __( 'Unable to create test.', 'woofunnels-ab-tests' ),
				'reason' => '',
			);


			if ( empty( $experiment_data['title'] ) || empty( $experiment_data['control'] ) ) {
				$resp['reason'] = __( 'Empty title or no control is selected.', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}
			$type = $experiment_data['type'];

			if ( empty( $type ) ) {
				$resp['reason'] = __( 'No experiment type provided', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}
			$resp['type'] = $type;

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
						$resp['msg']          = $get_controller->get_title() . ' > ' . $get_controller->get_variant_title( $variant_id );
						$resp['redirect_url'] = add_query_arg( array( 'page' => 'bwf_ab_tests', 'section' => 'variants', 'edit' => $experiment_id, ), admin_url( 'admin.php' ) );
					} else {
						$resp['reason'] = __( 'Unable to add variant', 'woofunnels-ab-tests' );
					}
				}
			}

			wp_send_json( $resp );
		}

		public static function page_search() {
			check_admin_referer( 'bwfabt_page_search', '_nonce' );
			$term = ( isset( $_POST['term'] ) && bwfabt_clean( $_POST['term'] ) ) ? stripslashes( bwfabt_clean( $_POST['term'] ) ) : '';//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$type = isset( $_POST['type'] ) ? bwfabt_clean( $_POST['type'] ) : '';//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$result = array(
				'status'   => false,
				'controls' => array(),
			);

			if ( empty( $term ) || empty( $type ) ) {
				wp_send_json( $result );
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			$controls       = $get_controller->get_controls( $term );

			if ( is_array( $controls ) && count( $controls ) > 0 ) {
				$controls = BWFABT_Core()->admin->add_existing_to_controls( $controls, $type );
				$result   = array(
					'status'   => true,
					'controls' => $controls,
				);
			}

			wp_send_json( $result );
		}

		public static function get_experiment_controls() {
			check_admin_referer( 'bwfabt_get_experiment_controls', '_nonce' );
			$type = isset( $_POST['type'] ) ? bwfabt_clean( $_POST['type'] ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( empty( $type ) ) {
				wp_send_json( array(
					'status'   => true,
					'msg'      => __( 'Unable to get controls, due to empty experiment type.', 'woofunnels-ab-tests' ),
					'controls' => array(),
				) );
			}
			$get_controller = BWFABT_Core()->controllers->get_integration( $type );

			wp_send_json( array(
				'status' => true,
				'title'  => $get_controller->get_title(),
			) );
		}

		/**
		 * Deleting an experiment
		 */
		public static function delete_experiment() {
			check_admin_referer( 'bwfabt_delete_experiment', '_nonce' );
			$deleted       = false;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;
			if ( $experiment_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
				$type       = $experiment->get_type();
				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );
					$deleted        = $get_controller->delete_experiment( $experiment );
				}
			}
			if ( $deleted ) {
				wp_send_json( array( 'status' => true ) );
			}
			wp_send_json( array( 'status' => false, 'msg' => __( 'Unable to delete the test.', 'woofunnels-ab-tests' ) ) );
		}

		/**
		 * Updating the experiment
		 */
		public static function update_experiment() {
			check_admin_referer( 'bwfabt_update_experiment', '_nonce' );

			$experiment_id = isset( $_POST['experiment_id'] ) ? absint( bwfabt_clean( $_POST['experiment_id'] ) ) : 0;

			$experiment_data          = array();
			$experiment_data['id']    = $experiment_id;
			$experiment_data['title'] = isset( $_POST['experiment_name'] ) ? bwfabt_clean( $_POST['experiment_name'] ) : '';
			$experiment_data['desc']  = isset( $_POST['experiment_desc'] ) ? bwfabt_clean( $_POST['experiment_desc'] ) : '';

			$resp = array(
				'status' => false,
				'exp_id' => $experiment_id,
				'msg'    => __( 'Unable to update the test.', 'woofunnels-ab-tests' ),
			);

			if ( empty( $experiment_data['title'] ) || $experiment_id < 1 ) {
				$resp['reason'] = __( 'Empty title or no experiment id.', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
			$type       = $experiment->get_type();

			if ( ! empty( $type ) ) {
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );

				$updated = $get_controller->save_experiment( $experiment_data );
			} else {
				$updated = $experiment->save( $experiment_data );
			}

			if ( false !== $updated ) {
				$resp = array(
					'status' => true,
					'ex_id'  => $experiment_id,
					'msg'    => __( 'Test updated successfully.', 'woofunnels-ab-tests' ),
				);
			}
			wp_send_json( $resp );
		}

		/**
		 * Creating a new variant
		 */
		public static function add_variant() {
			check_admin_referer( 'bwfabt_add_variant', '_nonce' );

			$variant_data                  = array();
			$variant_data['experiment_id'] = isset( $_POST['experiment_id'] ) ? absint( bwfabt_clean( $_POST['experiment_id'] ) ) : 0;
			$variant_data['variant_title'] = isset( $_POST['variant_title'] ) ? bwfabt_clean( $_POST['variant_title'] ) : '';
			$variant_data['variant_desc']  = isset( $_POST['variant_desc'] ) ? bwfabt_clean( $_POST['variant_desc'] ) : '';
			$variant_data['traffic']       = isset( $_POST['traffic'] ) ? bwfabt_clean( $_POST['traffic'] ) : "0.00";
			$variant_data['control']       = false;

			$experiment_id = $variant_data['experiment_id'];

			$resp = array(
				'status'        => false,
				'experiment_id' => $experiment_id,
				'msg'           => __( 'Unable to create variant.', 'woofunnels-ab-tests' ),
			);

			if ( empty( $variant_data['variant_title'] ) || $experiment_id < 1 ) {
				$resp['msg'] = __( 'Empty variant title or no experiment id', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

			BWF_Admin_Breadcrumbs::register_ref( 'bwf_exp_ref', $experiment_id );
			$type = $experiment->get_type();
			if ( empty( $type ) ) {
				$resp['msg'] = __( 'Empty experiment type', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}


			$contorol_id                = $experiment->get_control();
			$variant_data['control_id'] = $contorol_id;

			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			$variant_data   = $get_controller->add_variant( $variant_data );

			$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;

			if ( $variant_id > 0 ) {

				$resp['variant_id'] = $variant_id;

				$variant_data['variant_title'] = $get_controller->get_variant_title( $variant_id );
				$variant_data['traffic']       = isset( $variant_data['traffic'] ) ? $variant_data['traffic'] : "0.00";

				$variant = $experiment->add_variant( $variant_data );

				$variantid = $variant->get_id();
				if ( $variantid > 0 ) {
					$resp['data']          = array(
						"edit"        => $get_controller->get_variant_heading_url( $variant, $experiment ),
						"title"       => $get_controller->get_variant_title( $variant_id ),
						"desc"        => "",
						"traffic"     => "0.00",
						"status"      => "1",
						"row_actions" => $get_controller->get_variant_row_actions( $variant, $experiment ),
						"control"     => false,
						"winner"      => false,
					);
					$resp["id"]            = $variant_id;
					$resp['variant_order'] = count( $experiment->get_variants() ) - 1;
					$resp['status']        = true;
					$resp['msg']           = sprintf( __( 'Variant %s created successfully.', 'woofunnels-ab-tests' ), $get_controller->get_variant_title( $variant_id ) );
					$resp['redirect_url']  = add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'variants',
						'edit'    => $experiment_id,
					), admin_url( 'admin.php' ) );
				}
			}

			wp_send_json( $resp );
		}

		/**
		 * Duplicating a variant
		 */
		public static function duplicate_variant() {
			check_admin_referer( 'bwfabt_duplicate_variant', '_nonce' );

			$variant_data                  = array();
			$variant_data['variant_id']    = $variant_id = isset( $_POST['variant_id'] ) ? bwfabt_clean( $_POST['variant_id'] ) : 0;
			$variant_data['experiment_id'] = $experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;

			$resp = array(
				'status'     => false,
				'exp_id'     => $experiment_id,
				'variant_id' => $variant_id,
				'msg'        => __( 'Unable to duplicate variant.', 'woofunnels-ab-tests' ),
				'variant'    => $variant_data,
			);

			if ( $variant_id < 1 || $experiment_id < 1 ) {
				$resp['msg'] = __( 'No experiment id or no variant id', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}

			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
			BWF_Admin_Breadcrumbs::register_ref( 'bwf_exp_ref', $experiment_id );
			$type = $experiment->get_type();
			if ( empty( $type ) ) {
				$resp['msg'] = __( 'Empty experiment type', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}


			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			if ( is_null( $get_controller ) || ! $get_controller instanceof BWFABT_Controller ) {
				$resp['msg'] = __( 'Respective controller is not registered.', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}
			$variant_data['control_id'] = $experiment->get_control();
			$variant_data               = $get_controller->duplicate_variant( $variant_data );

			$duplicated_variant_id = $variant_data['variant_id'];

			if ( $duplicated_variant_id > 0 ) {

				$resp['duplicated_variant_id'] = $duplicated_variant_id;

				$variant_data['variant_title'] = $get_controller->get_variant_title( $duplicated_variant_id );
				$variant_data['traffic']       = isset( $variant_data['traffic'] ) ? $variant_data['traffic'] : "0.00";
				$variant_data['control']       = false;

				$new_variant    = $experiment->add_variant( $variant_data );
				$new_variant_id = $new_variant->get_id();
				if ( $new_variant_id > 0 ) {
					$resp['data']          = array(
						"edit"        => $get_controller->get_variant_heading_url( $new_variant, $experiment ),
						"title"       => $get_controller->get_variant_title( $new_variant_id ),
						"desc"        => "",
						"traffic"     => "0.00",
						"status"      => "1",
						"row_actions" => $get_controller->get_variant_row_actions( $new_variant, $experiment ),
						"control"     => false,
						"winner"      => false,
					);
					$resp['variant_order'] = count( $experiment->get_variants() ) - 1;
					$resp['status']        = true;
					$resp['msg']           = sprintf( __( 'Variant "%s" duplicated successfully. New Variant is: %s', 'woofunnels-ab-tests' ), $get_controller->get_variant_title( $variant_id ), $get_controller->get_variant_title( $new_variant_id ) );
					$resp['redirect_url']  = add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'variants',
						'edit'    => $experiment_id,
					), admin_url( 'admin.php' ) );
				}
			}

			wp_send_json( $resp );
		}

		/**
		 * Deleting a variant
		 */
		public static function delete_variant() {
			check_admin_referer( 'bwfabt_delete_variant', '_nonce' );
			$deleted       = false;
			$variant_id    = isset( $_POST['variant_id'] ) ? bwfabt_clean( $_POST['variant_id'] ) : 0;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;

			$resp = array(
				'status'       => false,
				'exp_id'       => $experiment_id,
				'variant_id'   => $variant_id,
				'control_only' => false,
				'msg'          => __( 'Unable to delete this variant.', 'woofunnels-ab-tests' ),
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
				$resp['status'] = true;
				$resp['msg']    = __( 'Variant deleted successfully!', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}
			wp_send_json( $resp );
		}

		/**
		 * Updating traffic for an experiment
		 */
		public static function update_traffic() {
			check_admin_referer( 'bwfabt_update_traffic', '_nonce' );
			$updated       = false;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;
			$traffics      = isset( $_POST['traffics'] ) ? array_map( 'bwfabt_clean', $_POST['traffics'] ) : array();

			$resp = array(
				'status'   => false,
				'exp_id'   => $experiment_id,
				'traffics' => $traffics,
				'msg'      => __( 'Unable to update the traffic.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

				$type = $experiment->get_type();

				$active_variants = $experiment->get_active_variants();
				BWFABT_Core()->admin->log( "Active variants for experiment id: $experiment_id, Type: $type and active variants: " . print_r( $active_variants, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );
					$updated        = $get_controller->update_traffics( $traffics, $experiment_id );
				} else {
					$updated = $experiment->update_traffic( $traffics );
				}
			}
			if ( $updated ) {
				$resp['status'] = true;
				$resp['msg']    = __( 'Traffic updated successfully.', 'woofunnels-ab-tests' );
			}
			BWFABT_Core()->admin->log( "Updated: $updated traffic for experiment id: $experiment_id: " . print_r( $traffics, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_send_json( $resp );
		}


		/**
		 * Starting an experiment
		 */
		public static function start_experiment() {
			check_admin_referer( 'bwfabt_start_experiment', '_nonce' );
			$success       = false;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;

			$resp = array(
				'status' => $success,
				'exp_id' => $experiment_id,
				'msg'    => __( 'Unable to start this test.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id < 1 ) {
				$resp['reason'] = __( 'No experiment id.', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "No experiment $experiment_id in start experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_send_json( $resp );
			}
			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

			$type = $experiment->get_type();
			if ( ! empty( $type ) ) {
				$get_controller = BWFABT_Core()->controllers->get_integration( $type );
				$success        = $get_controller->start_experiment( $experiment );
			} else {
				$success = $experiment->start();
			}

			if ( $success ) {
				$resp['status'] = $success;
				$resp['msg']    = __( 'Test started successfully.', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "Experiment started $experiment_id in start_experiment for type: $type Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_send_json( $resp );
			}
			BWFABT_Core()->admin->log( "Experiment not started $experiment_id, Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_send_json( $resp );
		}

		/**
		 * Checking readiness of an experiment
		 */
		public static function check_readiness() {
			check_admin_referer( 'bwfabt_check_readiness', '_nonce' );
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;

			$resp = array(
				'status'           => true,
				'exp_id'           => $experiment_id,
				'readiness_state'  => 3,
				'no_variant'       => false,
				'inactive_variant' => false,
				'InValid_traffic'  => false,
			);

			if ( $experiment_id < 1 ) {
				$resp['reason'] = __( 'No experiment id', 'woofunnels-ab-tests' );
				$resp['status'] = false;
				BWFABT_Core()->admin->log( "No experiment $experiment_id in check readiness. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_send_json( $resp );
			}

			$experiment        = BWFABT_Core()->admin->get_experiment( $experiment_id );
			$type              = $experiment->get_type();
			$inactive_variants = array();

			if ( empty( $type ) ) {
				$resp['reason'] = __( 'No controller type is defined', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "Empty type: $type for experiment: $experiment_id in check readiness. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_send_json( $resp );
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $type );
			$variants       = $experiment->get_active_variants();

			if ( count( $variants ) < 2 ) {
				$resp['readiness_state'] = 2;
				$resp['no_variant']      = true;
			}
			$traffic_total = 0;
			foreach ( $variants as $variant_id => $variant ) {
				if ( floatval( 0 ) === floatval( $variant['traffic'] ) ) {
					$resp['InValid_traffic'] = true;
					$resp['readiness_state'] = 2;
				}

				if ( false === $get_controller->is_variant_active( $variant_id ) ) {
					$resp['inactive_variant']         = true;
					$resp['readiness_state']          = 2;
					$inactive_variants[ $variant_id ] = $get_controller->get_variant_title( $variant_id );
				}
				$traffic_total = round( floatval( $traffic_total ) + floatval( $variant['traffic'] ), 2 );
			}
			$valid_traffic = ( floatval( 100 ) === floatval( ceil( $traffic_total ) ) || floatval( 100 ) === floatval( floor( $traffic_total ) ) );

			if ( ! $valid_traffic ) {
				$resp['InValid_traffic'] = true;
				$resp['readiness_state'] = 2;
			}
			$resp['inactive_variants'] = $inactive_variants;
			BWFABT_Core()->admin->log( "Readiness response for experiment id: $experiment_id: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_send_json( $resp );
		}

		/**
		 * Stopping an experiment
		 */
		public static function stop_experiment() {
			check_admin_referer( 'bwfabt_stop_experiment', '_nonce' );
			$success       = false;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;

			$resp = array(
				'status' => $success,
				'exp_id' => $experiment_id,
				'msg'    => __( 'Unable to stop this test.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id < 1 ) {
				$resp['reason'] = __( 'No experiment id', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "No experiment $experiment_id in stop_experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_send_json( $resp );
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
				$resp['status'] = $success;
				$resp['msg']    = __( 'Test Paused successfully.', 'woofunnels-ab-tests' );
				BWFABT_Core()->admin->log( "Experiment paused $experiment_id in stop experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				wp_send_json( $resp );
			}
			$resp['reason'] = __( "Success:  $success.", 'woofunnels-ab-tests' );
			BWFABT_Core()->admin->log( "Experiment pause result for $experiment_id in stop experiment. Response: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_send_json( $resp );
		}

		/**
		 * Removing a variant from a running test
		 */
		public static function draft_variant() {
			check_admin_referer( 'bwfabt_draft_variant', '_nonce' );
			$removed       = false;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;
			$variant_id    = isset( $_POST['variant_id'] ) ? bwfabt_clean( $_POST['variant_id'] ) : 0;

			$resp = array(
				'status'     => false,
				'exp_id'     => $experiment_id,
				'variant_id' => $variant_id,
				'error'      => '',
				'message'    => __( 'Unable to remove this variant.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 && $variant_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

				$type = $experiment->get_type();
				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					if ( $experiment->get_status() === $experiment::STATUS_START ) {

						if ( absint( $variant_id ) === absint( $experiment->get_control() ) ) {
							$resp['status']  = false;
							$resp['error']   = __( 'Unable to draft ', 'woofunnels-ab-tests' );
							$resp['message'] = __( 'At least one Original & Variant step should be active. Use Pause to stop the experiment.', 'woofunnels-ab-tests' );

							wp_send_json( $resp );

						} else {
							$get_active_variants = $experiment->get_active_variants( true );
							if ( count( $get_active_variants ) === 2 ) {

								$resp['status']  = false;
								$resp['error']   = __( 'Unable to draft', 'woofunnels-ab-tests' );
								$resp['message'] = __( 'At least one Original & Variant step should be active. Use Pause to stop the experiment.', 'woofunnels-ab-tests' );

								wp_send_json( $resp );

							}
						}
					}
					$removed = $get_controller->draft_variant( $experiment, $variant_id );
				}
			}

			if ( $removed ) {
				$resp['status']  = true;
				$resp['message'] = __( 'Variant draft successfully.', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}
			wp_send_json( $resp );
		}

		/**
		 * publish a variant from a running test
		 */
		public static function publish_variant() {
			check_admin_referer( 'bwfabt_publish_variant', '_nonce' );
			$publish       = false;
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;
			$variant_id    = isset( $_POST['variant_id'] ) ? bwfabt_clean( $_POST['variant_id'] ) : 0;

			$resp = array(
				'status'     => false,
				'exp_id'     => $experiment_id,
				'variant_id' => $variant_id,
				'msg'        => __( 'Unable to publish this variant.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 && $variant_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

				$type = $experiment->get_type();
				if ( ! empty( $type ) ) {
					$get_controller = BWFABT_Core()->controllers->get_integration( $type );

					$publish = $get_controller->publish_variant( $experiment, $variant_id );

				}
			}

			if ( $publish ) {
				$resp['status'] = true;
				$resp['msg']    = __( 'Variant published successfully.', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
			}
			wp_send_json( $resp );
		}

		/**
		 * Choosing winner
		 */
		public static function choose_winner() {
			check_admin_referer( 'bwfabt_choose_winner', '_nonce' );
			$selected      = false; //winner is chosen flag
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;
			$winner_id     = isset( $_POST['winner'] ) ? bwfabt_clean( $_POST['winner'] ) : 0;

			$resp = array(
				'status'    => false,
				'exp_id'    => $experiment_id,
				'winner_id' => $winner_id,
				'msg'       => __( 'Unable to declare the winner.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id > 0 && $winner_id > 0 ) {
				$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

				$type = $experiment->get_type();
				if ( ! empty( $type ) ) {
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
										$resp['reason'] = __( 'Unable to transfer the control', 'woofunnels-ab-tests' );
										wp_send_json( $resp );
									}
									$deleted = $get_controller->delete_variant( $control_variant, $experiment, false ); //Only unset from experiment but don't delete actual post

									if ( false === $deleted ) {
										$resp['reason'] = __( "Unable to delete the old control variant $control_id", 'woofunnels-ab-tests' );
										wp_send_json( $resp );
									}

									$variantid = $new_variant_id;
								}

								$draft = $get_controller->draft_variant( $experiment, $variantid );
								if ( false === $draft ) {
									$resp['reason'] = __( "Unable to draft the variant $variantid", 'woofunnels-ab-tests' );
								}
							}
							$get_controller->copy_control_data_to_new_control( array( 'control_id' => $control_id ), $new_variant_id );

							if ( ! $is_control ) {
								$get_controller->copy_winner_data_to_control( $control_id, $winner_id );
							}

							$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );
							$selected   = $experiment->choose_winner( $winner_id, $new_variant_id );
						}
					}
				}
			}

			if ( $selected ) {
				$resp['status'] = true;
				$resp['msg']    = __( 'Winner selected successfully.', 'woofunnels-ab-tests' );
			}
			BWFABT_Core()->admin->log( "Winner response for experiment id: $experiment_id, winner id: $winner_id: " . print_r( $resp, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_send_json( $resp );
		}

		/**
		 * Resetting stats for query to start from last_reset_date
		 */
		public static function reset_stats() {
			check_admin_referer( 'bwfabt_reset_stats', '_nonce' );
			$experiment_id = isset( $_POST['experiment_id'] ) ? bwfabt_clean( $_POST['experiment_id'] ) : 0;
			$success       = false;

			$resp = array(
				'status' => $success,
				'exp_id' => $experiment_id,
				'msg'    => __( 'Unable to reset stats.', 'woofunnels-ab-tests' ),
			);

			if ( $experiment_id < 1 ) {
				$resp['reason'] = __( 'No experiment id provided', 'woofunnels-ab-tests' );
				wp_send_json( $resp );
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

			$resp['status']       = true;
			$resp['msg']          = __( 'Analytics reset successfully.', 'woofunnels-ab-tests' );
			$resp['redirect_url'] = admin_url( 'admin.php?page=bwf_ab_tests&section=variants&edit=' . $experiment_id );

			wp_send_json( $resp );
		}
	}

	BWFABT_AJAX_Controller::init();
}