<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'wfob_Rule_General_Always' ) ) {
	class wfob_Rule_General_Always extends wfob_Rule_Base {

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

		public function is_match( $rule_data ) {
			return true;
		}

	}
}
if ( ! class_exists( 'wfob_Rule_General_All_Pages' ) ) {
	class wfob_Rule_General_All_Pages extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'general_all_pages' );
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

		public function is_match( $rule_data ) {
			return is_singular( 'product' );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_General_All_Product_Cats' ) ) {
	class wfob_Rule_General_All_Product_Cats extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'general_all_product_cats' );
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

		public function is_match( $rule_data ) {
			return is_tax( 'product_cat' );
		}

	}
}