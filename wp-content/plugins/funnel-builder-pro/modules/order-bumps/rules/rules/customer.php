<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFOB_Rule_Customer_User' ) ) {
	class WFOB_Rule_Customer_User extends WFOB_Rule_Base {

		public function __construct() {
			parent::__construct( 'customer_user' );
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
			return 'User_Select';
		}

		public function is_match( $rule_data ) {
			$result = false;
			$user   = wp_get_current_user();
			if ( is_wp_error( $user ) ) {
				return null;
			}
			$id = $user->ID;

			if ( isset( $rule_data['condition'] ) ) {
				$result = in_array( $id, $rule_data['condition'] );
				$result = $rule_data['operator'] == 'in' ? $result : ! $result;
			}

			return $this->return_is_match( $result, $rule_data );
		}
	}
}
if ( ! class_exists( 'WFOB_Rule_Customer_Role' ) ) {
	class WFOB_Rule_Customer_Role extends WFOB_Rule_Base {

		public function __construct() {
			parent::__construct( 'customer_role' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'in'    => __( 'is', 'woofunnels-order-bump' ),
				'notin' => __( 'is not', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result = array();

			$editable_roles = get_editable_roles();

			if ( $editable_roles ) {
				foreach ( $editable_roles as $role => $details ) {
					$name = translate_user_role( $details['name'] );

					$result[ $role ] = $name;
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data ) {
			$user = wp_get_current_user();
			if ( is_wp_error( $user ) ) {
				return null;
			}
			$result = false;
			$id     = $user->ID;
			if ( isset( $rule_data['condition'] ) && is_array( $rule_data['condition'] ) ) {
				foreach ( $rule_data['condition'] as $role ) {
					$result |= user_can( $id, $role );
				}
			}

			$result = $rule_data['operator'] == 'in' ? $result : ! $result;

			return $this->return_is_match( $result, $rule_data );
		}
	}

}
if ( ! class_exists( 'WFOB_Rule_Customer_Purchased_Products' ) ) {
	class WFOB_Rule_Customer_Purchased_Products extends WFOB_Rule_Base {

		public $supports = array( 'cart' );

		public function __construct() {
			parent::__construct( 'customer_purchased_products' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matches any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_condition_input_type() {
			return 'Product_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$user = wp_get_current_user();
			if ( is_wp_error( $user ) ) {
				return null;
			}
			$result      = false;
			$user_email  = $user->user_email;
			$type        = $rule_data['operator'];
			$email       = $user_email;
			$user_id     = get_current_user_id();
			$bwf_contact = bwf_get_contact( $user_id, $email );
			if ( ! $bwf_contact instanceof WooFunnels_Contact ) {
				if ( 'none' === $type ) {
					return $this->return_is_match( true, $rule_data );
				}

				return $this->return_is_match( false, $rule_data );
			}
			$bwf_contact->set_customer_child();
			$purchased_products = $bwf_contact->get_customer_purchased_products();

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {

					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $purchased_products ) ) {
							$result = count( array_intersect( $rule_data['condition'], $purchased_products ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $purchased_products ) ) {
							$result = count( array_intersect( $rule_data['condition'], $purchased_products ) ) === 0;
						}
						break;
					default:
						$result = false;


						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

	}
}