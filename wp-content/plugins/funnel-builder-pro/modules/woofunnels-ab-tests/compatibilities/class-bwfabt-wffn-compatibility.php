<?php

/**
 * Compatibility functions with the funnel builder plugin
 */
if ( ! class_exists( 'BWFABT_WFFN_Compatibility' ) ) {

	#[AllowDynamicProperties]
	class BWFABT_WFFN_Compatibility {
		public static $_instance = null;

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function __construct() {
			add_filter( 'wffn_rest_get_funnel_steps', array( $this, 'maybe_experiment_run' ) );
			add_filter( 'wffn_rest_get_step_post', array( $this, 'maybe_experiment_run_on_step' ), 10, 2 );
			add_action( 'wffn_state_toggle_step', array( $this, 'maybe_pause_experiment' ), 10, 2 );

		}


		public function maybe_experiment_run( $steps ) {


			foreach ( $steps as &$step ) {

				if ( isset( $step['id'] ) ) {
					$exp_data = BWFABT_Core()->get_dataStore()->get_experiment_by_control_id( $step['id'], 'DESC' );

					if ( is_array( $exp_data ) && count( $exp_data ) > 0 ) {
						$step['experiment_status'] = $exp_data[0]['status'];

					}
				}

				if ( isset( $step['substeps'] ) && is_array( $step['substeps'] ) && isset( $step['substeps']['wc_order_bump'] ) && count( $step['substeps']['wc_order_bump'] ) > 0 ) {
					$step['substeps']['wc_order_bump'] = $this->maybe_experiment_run( $step['substeps']['wc_order_bump'] );
				}

				if ( isset( $step['substeps'] ) && is_array( $step['substeps'] ) && isset( $step['substeps']['offer'] ) && count( $step['substeps']['offer'] ) > 0 ) {
					$step['substeps']['offer'] = $this->maybe_experiment_run( $step['substeps']['offer'] );
				}
			}

			return $steps;

		}

		public function maybe_experiment_run_on_step( $resp, $step_id ) {
			$exp_data = BWFABT_Core()->get_dataStore()->get_active_experiment_for_control( $step_id );
			if ( is_array( $exp_data ) && count( $exp_data ) > 0 ) {
				$resp['experiment_status'] = $exp_data[0]['status'];
				$variants                  = json_decode( $exp_data[0]['variants'], true );
				if ( is_array( $variants ) && $count = count( $variants ) ) {
					if ( isset( $resp['global_slug'] ) && empty( $resp['global_slug'] ) ) {
						$get_controller = BWFABT_Core()->controllers->get_integration( $exp_data[0]['type'] );

						$variant_data = [];
						foreach ( $variants as $variant_id => $data ) {
							if ( absint( $step_id ) === absint( $variant_id ) ) {
								continue;
							}
							$post_data = get_post( $variant_id );

							if ( $post_data instanceof WP_Post ) {
								$variant_data[] = array(
									'id'        => $variant_id,
									'title'     => html_entity_decode( $post_data->post_title ),
									'view_link' => $get_controller->get_entity_view_link( $variant_id ),
									'post_slug' => $post_data->post_name
								);
							}
						}
						if ( count( $variant_data ) > 0 ) {
							$resp['variants'] = $variant_data;
						}
					}
					$resp['experiment_message'] = sprintf( "A/B test with %d variants was started %s", $count, date( 'F d,Y', strtotime( $exp_data[0]['date_added'] ) ) );
				}
			}

			return $resp;
		}

		public function maybe_pause_experiment( $status, $request ) {
			$step_id = $request->get_param( 'step_id' );
			if ( $status === 'draft' || false === $status ) {
				$exp_data = BWFABT_Core()->get_dataStore()->get_active_experiment_for_control( $step_id );
				foreach ( $exp_data as $exp ) {
					$experiment = new BWFABT_Experiment( $exp['id'] );
					if ( $experiment->is_started() && false === $experiment->is_paused() && false === $experiment->is_completed() ) {
						$type           = $experiment->get_type();
						$get_controller = BWFABT_Core()->controllers->get_integration( $type );
						$get_controller->stop_experiment( $experiment );
					}
				}

			}
		}

	}

	if ( true === function_exists( 'WFFN_Core' ) ) {
		return BWFABT_WFFN_Compatibility::get_instance();
	}


}



