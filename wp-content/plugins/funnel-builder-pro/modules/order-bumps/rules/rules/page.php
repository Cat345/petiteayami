<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'wfob_Rule_Single_Page' ) ) {
	class wfob_Rule_Single_Page extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'single_page' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'in'    => __( 'is', 'woofunnels-order-bump' ),
				'notin' => __( 'is not', 'woofunnels-order-bump' ),
			);

			return $operators;

		}

		public function get_possible_rule_values() {
			return null;
		}

		public function get_condition_input_type() {
			return 'Page_Select';
		}

		public function is_match( $rule_data ) {

			global $post;

			$result = false;

			if ( is_page( $post ) && isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {
				$in     = (bool) ( $post->ID == $rule_data['condition'] );
				$result = $rule_data['operator'] == 'in' ? $in : ! $in;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}
if ( ! class_exists( 'wfob_Rule_Single_Term_Page' ) ) {
	class wfob_Rule_Single_Term_Page extends wfob_Rule_Base {

		public function __construct() {

			parent::__construct( 'single_term_page' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'in'    => __( 'is', 'woofunnels-order-bump' ),
				'notin' => __( 'is not', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			return null;
		}

		public function get_condition_input_type() {
			return 'Term_Select';
		}


		public function is_match( $rule_data ) {
			global $wp_query;

			$result = false;

			if ( is_tax( 'product_cat' ) && isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {
				$get_tax = $wp_query->get_queried_object();

				$in     = (bool) ( $get_tax->term_id == $rule_data['condition'] );
				$result = $rule_data['operator'] == 'in' ? $in : ! $in;
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}

}