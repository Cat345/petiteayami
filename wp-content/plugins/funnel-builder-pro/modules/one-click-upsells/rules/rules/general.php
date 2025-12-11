<?php
if ( ! class_exists( 'WFOCU_Rule_General_Always' ) ) {
	class WFOCU_Rule_General_Always extends WFOCU_Rule_Base {
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

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return true;
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_General_Always_2' ) ) {

	class WFOCU_Rule_General_Always_2 extends WFOCU_Rule_Base {
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

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return true;
		}

	}
}