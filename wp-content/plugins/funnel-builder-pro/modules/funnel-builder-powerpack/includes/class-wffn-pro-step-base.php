<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Step_Base' ) ) {
	/**
	 * This class will be extended by all all single step(like upstroke, aero etc) to register different steps
	 * Class WFFN_Pro_Step_Base
	 */
	#[AllowDynamicProperties]
	abstract class WFFN_Pro_Step_Base {

		public $slug = '';
		public $supports = [];
		public $id;
		public $list_priority;

		/**
		 * WFFN_Pro_Step_Base constructor.
		 *
		 * @param string $id
		 */
		public function __construct( $id = '' ) {
			$this->id = $id;
		}

		public function should_register() {
			return true;
		}

		public function get_enitity_data( $step_data, $key ) {
			return array_key_exists( $key, $step_data ) ? $step_data[ $key ] : false;
		}

		/**
		 * @param $step_id
		 *
		 * @return array
		 */
		public function get_step_report( $funnel_id, $step_id, $filter_data ) {
			return array();
		}

		/**
		 * @param $funnel_id
		 * @param $substep_id
		 * @param $filter_data
		 *
		 * @return array
		 */
		public function get_substep_report( $funnel_id, $substep_id, $filter_data ) {
			return [];

		}

		/**
		 * @param $control_id
		 *
		 * @return array
		 */
		public function maybe_get_ab_variants( $control_id ) {
			return [];
		}
	}
}