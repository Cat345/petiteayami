<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Experiment' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_Experiment {

		/**
		 * @var $ins
		 */
		private static $ins = null;

		/**
		 * @var $id
		 */
		public $id = 0;

		/**
		 * @var $title
		 */
		public $title = '';

		/**
		 * @var $desc
		 */
		public $desc = '';

		/**
		 * @var $status
		 */
		public $status = '';

		/**
		 * @var $type
		 */
		public $type = '';

		/**
		 * @var $date_added
		 */
		public $date_added = null;

		/**
		 * @var $date_started
		 */
		public $date_started = null;

		/**
		 * @var $last_reset_date
		 */
		public $last_reset_date = null;

		/**
		 * @var $date_commpleted
		 */
		public $date_completed = null;

		/**
		 * @var $goal
		 */
		public $goal = '';

		/**
		 * @var $control
		 */
		public $control = '';

		/**
		 * @var $variants
		 */
		public $variants = '';


		const STATUS_DRAFT = 1;
		const STATUS_START = 2;
		const STATUS_PAUSE = 3;
		const STATUS_COMPLETE = 4;

		/**
		 * BWFABT_Experiment constructor..
		 * @since  1.0.0
		 */
		public function __construct( $id ) {
			$this->id = $id;

			if ( $this->id > 0 ) {
				$exp_data = BWFABT_Core()->get_dataStore()->get( $this->id );

				if ( ! empty( $exp_data ) && is_array( $exp_data ) ) {
					foreach ( $exp_data as $col => $value ) {
						if ( 'variants' === $col ) {
							$this->$col = json_decode( $value, true );
						} else {
							$this->$col = $value;
						}
					}
				}
			}
		}

		/**
		 * @return BWFABT_Experiment|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function get_id() {
			return $this->id;
		}

		public function get_title() {
			return $this->title;
		}

		public function get_status() {
			return (int) $this->status;
		}

		public function get_desc() {
			return $this->desc;
		}

		public function get_type() {
			return $this->type;
		}

		public function get_date_added() {
			return $this->date_added;
		}

		public function get_date_started() {
			return $this->date_started;
		}

		public function get_last_reset_date() {
			return $this->last_reset_date;
		}

		public function get_date_completed() {
			return $this->date_completed;
		}

		public function get_goal() {
			return $this->goal;
		}

		public function get_control() {
			return $this->control;
		}

		public function get_variants() {
			return empty( $this->variants ) ? array() : $this->variants;
		}

		public function set_id( $id ) {
			$this->id = empty( $id ) ? $this->id : $id;
		}

		public function set_title( $title ) {
			$this->title = empty( $title ) ? $this->title : $title;
		}

		public function set_status( $status ) {
			$this->status = empty( $status ) ? $this->status : $status;
		}

		public function set_desc( $desc ) {
			$this->desc = empty( $desc ) ? $this->desc : $desc;
		}

		public function set_type( $type ) {
			$this->type = empty( $type ) ? $this->type : $type;
		}

		public function set_date_added( $date_added ) {
			$this->date_added = empty( $date_added ) ? $this->date_added : $date_added;
		}

		public function set_date_started( $date_started ) {
			$this->date_started = empty( $date_started ) ? $this->date_started : $date_started;
		}

		public function set_last_reset_date( $last_reset_date ) {
			$this->last_reset_date = empty( $last_reset_date ) ? $this->last_reset_date : $last_reset_date;
		}

		public function set_date_completed( $date_completed ) {
			$this->date_completed = empty( $date_completed ) ? $this->date_completed : $date_completed;
		}

		public function set_goal( $goal ) {
			$this->goal = empty( $goal ) ? $this->goal : $goal;
		}

		public function set_control( $control ) {
			$this->control = empty( $control ) ? $this->control : $control;
		}

		public function set_variants( $variants ) {
			$this->variants = empty( $variants ) ? $this->variants : $variants;
		}

		/**
		 * Create/update experiment using given or set experiment data
		 *
		 * @param $data
		 *
		 * @return mixed
		 */
		public function save( $data = array() ) {
			if ( count( $data ) > 0 ) {
				foreach ( $data as $col => $value ) {
					$this->$col = $value;
				}
			}
			$experiment_data                    = array();
			$experiment_data['title']           = $this->get_title();
			$experiment_data['status']          = $this->get_status();
			$experiment_data['desc']            = $this->get_desc();
			$experiment_data['type']            = $this->get_type();
			$experiment_data['date_added']      = $this->get_date_added();
			$experiment_data['date_started']    = $this->get_date_started();
			$experiment_data['last_reset_date'] = $this->get_last_reset_date();
			$experiment_data['date_completed']  = $this->get_date_completed();
			$experiment_data['goal']            = $this->get_goal();
			$experiment_data['control']         = $this->get_control();
			$experiment_data['variants']        = wp_json_encode( $this->get_variants() );

			$experiment_id = $this->get_id();

			if ( $experiment_id > 0 ) {
				$updated = BWFABT_Core()->get_dataStore()->update( $experiment_data, array( 'id' => $experiment_id ) );

				return $updated;
			}

			BWFABT_Core()->admin->log( "Experiment data to save for experiment id $experiment_id: " . print_r( $experiment_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			BWFABT_Core()->get_dataStore()->insert( $experiment_data );
			$experiment_id = BWFABT_Core()->get_dataStore()->insert_id();

			return $experiment_id;
		}

		/**
		 * Deleting an experiment
		 * @return mixed
		 */
		public function delete() {
			return BWFABT_Core()->get_dataStore()->delete( $this->get_id() );
		}

		/**
		 * Adding a new variant
		 *
		 * @param $variant_data
		 *
		 * @return BWFABT_Variant
		 */
		public function add_variant( $variant_data ) {

			$variant_id = $variant_data['variant_id'];
			$variant    = new BWFABT_Variant( $variant_id, $this );

			$variant->set_traffic( $variant_data['traffic'] );
			$variant->set_control( $variant_data['control'] );
			$variant_id = $variant->save( array() );

			BWFABT_Core()->admin->log( "Added variant: $variant_id" );

			return $variant;
		}


		/**
		 * @param $variant
		 * @param false $force_delete
		 *
		 * @return bool
		 */
		public function delete_variant( $variant, $force_delete = false ) {
			$variant_id = $variant->get_id();
			BWFABT_Core()->admin->log( "Delete variant: $variant_id, Force: $force_delete" );

			$deleted  = false;
			$variants = $this->get_variants();
			if ( isset( $variants[ $variant_id ] ) && ( false === $variant->get_control() || false === $force_delete ) ) { // Delete variant if it is not control/control is winner/deleting experiment
				unset( $variants[ $variant_id ] );
				$this->set_variants( $variants );
				$this->save( array() );
				$deleted = true;
			}

			return $deleted;
		}

		/**
		 * @return array|string
		 */
		public function get_active_variants( $filter_entity_status = false ) {
			$get_controller = BWFABT_Core()->controllers->get_integration( $this->get_type() );
			$variants       = $this->get_variants();


			if ( true === $filter_entity_status ) {

				foreach ( array_keys( $variants ) as $variant_id ) {
					if ( false === $get_controller->is_variant_active( $variant_id ) ) {
						unset( $variants[ $variant_id ] );
					}
				}
			}


			return $variants;
		}

		/**
		 * @param $traffic_data
		 *
		 * @return bool
		 */
		public function update_traffic( $traffic_data ) {
			$updated = false;
			if ( is_array( $traffic_data ) && count( $traffic_data ) > 0 ) {
				foreach ( $traffic_data as $traffic ) {
					$variant = new BWFABT_Variant( $traffic['variant_id'], $this );
					$variant->set_traffic( $traffic['traffic'] );
					$variant->save( array() );
				}
				$updated = true;
			}

			return $updated;
		}


		/**
		 * @param $variant_id
		 *
		 * @return bool
		 */
		public function start() {
			$this->set_status( self::STATUS_START );
			$get_started_date = $this->get_date_started();
			if ( '0000-00-00 00:00:00' === $get_started_date ) {
				$this->set_date_started( BWFABT_Core()->get_dataStore()->now() );
			}
			$this->save( array() );

			$experiment_data = [
				'entity_id' => $this->get_id(),
				'type'      => 1,
				'date'      => BWFABT_Core()->get_dataStore()->now(),
			];

			$data = BWFABT_Core()->get_dataStore()->experiment_status_time( $experiment_data, true );

			/**
			 * Handle case when experiment start in same day
			 * Reset control analytics
			 */
			if ( isset( $data['activity'] ) && $data['activity'] !== '' ) {
				$new_data = json_decode( $data['activity'], true );
				if ( is_array( $new_data['activity'] ) && count( $new_data['activity'] ) === 1 ) {
					if ( ! empty( $this->get_type() ) ) {
						$get_controller = BWFABT_Core()->controllers->get_integration( $this->get_type() );
						if ( $get_controller instanceof BWFABT_Controller ) {
							$get_controller->reset_stats( $this );
							$this->reset_stats();
							update_post_meta( $this->get_control(), '_experiment_status', '' );
						}
					}

				}
			}

			return true;
		}

		/**
		 * @param $variant_id
		 *
		 * @return bool
		 */
		public function stop() {

			$this->set_status( self::STATUS_PAUSE );
			$this->save( array() );

			$experiment_data = [
				'entity_id' => $this->get_id(),
				'type'      => 2,
				'date'      => BWFABT_Core()->get_dataStore()->now(),
			];
			BWFABT_Core()->get_dataStore()->experiment_status_time( $experiment_data );

			return true;
		}

		/**
		 * @param $variant_id
		 *
		 * @return bool
		 */
		public function complete() {
			$this->set_status( self::STATUS_COMPLETE );
			$this->set_date_completed( BWFABT_Core()->get_dataStore()->now() );
			$this->save( array() );

			$experiment_data = [
				'entity_id' => $this->get_id(),
				'type'      => 4,
				'date'      => BWFABT_Core()->get_dataStore()->now(),
			];

			BWFABT_Core()->get_dataStore()->experiment_status_time( $experiment_data );

			return true;
		}

		/**
		 * Remove a variant from running test
		 *
		 * @param $variant_id
		 *
		 * @return bool
		 */
		public function draft_variant( $variant_id ) {
			$variant = new BWFABT_Variant( $variant_id, $this );
			$variant->set_traffic( '0.00' );
			$variant->save( array() );

			return true;
		}

		/**
		 * @param $winner_id
		 * @param $new_funnel_id
		 *
		 * @return bool
		 */
		public function choose_winner( $winner_id, $new_funnel_id ) {
			$variants = $this->get_variants();

			$winner_variant = array_key_exists( $winner_id, $variants ) ? new BWFABT_Variant( $winner_id, $this ) : new BWFABT_Variant( $new_funnel_id, $this );
			$winner_variant->set_winner( true );
			$winner_variant->save( array() );

			return $this->complete();

		}

		/**
		 * @param $winner_id
		 *
		 * @return bool
		 */
		public function set_winner( $winner_id ) {
			$winner_variant = new BWFABT_Variant( $winner_id, $this );
			$winner_variant->set_winner( true );
			$winner_variant->save( array() );

			return true;
		}

		/**
		 * @param $control_variant
		 * @param $new_control_variant
		 */
		public function transfer_control( $control_variant, $new_control_variant ) {
			$new_control_variant->set_control( true );
			$new_control_variant->save( array() );
		}

		public function reset_stats() {
			$this->set_last_reset_date( BWFABT_Core()->get_dataStore()->now() );
			$this->save( array() );

			$experiment_data = [
				'entity_id' => $this->get_id(),
				'type'      => 3,
				'date'      => BWFABT_Core()->get_dataStore()->now(),
			];

			BWFABT_Core()->get_dataStore()->experiment_status_time( $experiment_data );

			return true;
		}


		public function is_started() {
			$get_status = $this->get_status();

			return ( self::STATUS_DRAFT !== $get_status );
		}

		public function is_completed() {
			$get_status = $this->get_status();

			return ( self::STATUS_COMPLETE === $get_status );
		}

		public function is_paused() {
			$get_status = $this->get_status();

			return ( self::STATUS_PAUSE === $get_status );
		}

		public function is_draft() {
			$get_status = $this->get_status();

			return ( self::STATUS_DRAFT === $get_status );
		}

		public function get_status_nice_name() {
			$exp_state = __( 'Draft', 'woofunnels-ab-tests' );
			if ( ( $this->is_started() && false === $this->is_paused() && false === $this->is_completed() ) ) {
				$exp_state = __( 'Running', 'woofunnels-ab-tests' );
			} elseif ( $this->is_completed() ) {
				$exp_state = __( 'Completed', 'woofunnels-ab-tests' );
			} elseif ( $this->is_paused() ) {
				$exp_state = __( 'Paused', 'woofunnels-ab-tests' );
			}

			return $exp_state;
		}

		/**
		 * @return false|int
		 */
		public function get_report_start_date( $format = 'timestamp' ) {
			$from_date = ( '0000-00-00 00:00:00' === $this->get_last_reset_date() ) ? $this->get_date_started() : $this->get_last_reset_date();

			if ( '0000-00-00 00:00:00' === $from_date ) {
				return ( $format === 'timestamp' ) ? current_time( 'timestamp' ) : current_time( 'mysql' );
			} else {
				return ( $format === 'timestamp' ) ? strtotime( $from_date ) : $from_date;

			}

		}

		/**
		 * @return false|int
		 */
		public function get_report_end_date( $format = 'timestamp' ) {
			$till_date = $this->get_date_completed();

			if ( '0000-00-00 00:00:00' === $till_date ) {
				return ( $format === 'timestamp' ) ? current_time( 'timestamp' ) : current_time( 'mysql' );
			} else {
				return ( $format === 'timestamp' ) ? strtotime( $till_date ) : $till_date;

			}
		}
	}
}