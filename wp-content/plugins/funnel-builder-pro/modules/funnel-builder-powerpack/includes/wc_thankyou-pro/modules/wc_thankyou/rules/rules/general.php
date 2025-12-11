<?php
if ( ! class_exists( 'WFTY_Rule_General_Always' ) ) {
	class WFTY_Rule_General_Always extends WFTY_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'general_always' );
		}

		public function get_possible_rule_operators() {
			return null;
		}

		public function get_possible_rule_values() {
			return null;
		}

		public function get_condition_input_type() {
			return 'Html_Always';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			return true;
		}

	}

}
if ( ! class_exists( 'WFTY_Rule_General_Always_2' ) ) {
	class WFTY_Rule_General_Always_2 extends WFTY_Rule_Base {
		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'general_always_2' );
		}

		public function get_possible_rule_operators() {
			return null;
		}

		public function get_possible_rule_values() {
			return null;
		}

		public function get_condition_input_type() {
			return 'Html_Always';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			return true;
		}

	}
}