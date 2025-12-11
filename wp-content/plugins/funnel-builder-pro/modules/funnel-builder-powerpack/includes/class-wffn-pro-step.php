<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * This class will be extended by all all single step(like upstroke, aero etc) to register different steps
 * Class WFFN_Pro_Step
 */
if ( ! class_exists( 'WFFN_Pro_Step' ) ) {
	#[AllowDynamicProperties]
	abstract class WFFN_Pro_Step extends WFFN_Pro_Step_Base {

		public $slug = '';
		public $substeps = [];
		public $id;
		public $list_priority;

		/**
		 * WFFN_Step constructor.
		 *
		 * @param string $id
		 */
		public function __construct( $id = '' ) {
			$this->id = $id;
		}

	}
}