<?php
if ( ! class_exists( 'WFTY_Rule_Is_First_Order' ) ) {
	class WFTY_Rule_Is_First_Order extends WFTY_Rule_Base {

		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'is_first_order' );
		}

		public function get_possible_rule_operators() {
			return null;
		}

		public function get_possible_rule_values() {
			$operators = array(
				'yes' => __( 'Yes', 'funnel-builder-powerpack' ),
				'no'  => __( 'No', 'funnel-builder-powerpack' ),
			);

			return $operators;
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$is_first = false;

			$order_id      = $this->get_rule_instance()->get_environment_var( 'order' );
			$order         = wc_get_order( $order_id );
			$billing_email = $order->get_billing_email();


			if ( class_exists( 'WFOCU_Common' ) && method_exists( 'WFOCU_Common', 'wc_get_orders' ) ) {
				$orders = WFOCU_Common::wc_get_orders( array(
					'customer'  => $billing_email,
					'limit'     => 2,
					'return'    => 'ids',
					'post_type' => 'shop_order'
				) );
			} else {
				$orders = wc_get_orders( array(
					'customer' => $billing_email,
					'limit'    => 2,
					'return'   => 'ids',
				) );
			}


			if ( ! isset( $rule_data['condition'] ) ) {
				$rule_data['condition'] = 'yes';
			}

			if ( ( 'yes' === $rule_data['condition'] && count( $orders ) === 1 ) || ( 'no' === $rule_data['condition'] && count( $orders ) > 1 ) ) {
				return true;
			}

			return $is_first;
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Is First order of a customer. ', 'funnel-builder-powerpack' ) );
		}

	}
}
if ( ! class_exists( 'WFTY_Rule_Customer_User' ) ) {

	class WFTY_Rule_Customer_User extends WFTY_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'customer_user' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'in'    => __( 'is', 'funnel-builder-powerpack' ),
				'notin' => __( 'is not', 'funnel-builder-powerpack' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			return null;
		}

		public function get_condition_input_type() {
			return 'User_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {

			$order_id = $this->get_rule_instance()->get_environment_var( 'order' );
			$order    = wc_get_order( $order_id );
			$id       = $order->get_user_id();
			$result   = false;
			if ( isset( $rule_data['condition'] ) ) {
				$result = in_array( $id, $rule_data['condition'] );
				$result = $rule_data['operator'] == 'in' ? $result : ! $result;
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Customer is %s', 'funnel-builder-powerpack' ), $this->get_users_name( $rule['condition'] ) );
		}
	}
}
if ( ! class_exists( 'WFTY_Rule_Customer_Role' ) ) {
	class WFTY_Rule_Customer_Role extends WFTY_Rule_Base {

		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'customer_role' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'in'    => __( 'is', 'funnel-builder-powerpack' ),
				'notin' => __( 'is not', 'funnel-builder-powerpack' ),
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

		public function is_match( $rule_data, $env = 'cart' ) {
			$order_id = $this->get_rule_instance()->get_environment_var( 'order' );
			$order    = wc_get_order( $order_id );
			$id       = $order->get_user_id();
			$count    = 0;
			if ( isset( $rule_data['condition'] ) && is_array( $rule_data['condition'] ) ) {
				foreach ( $rule_data['condition'] as $role ) {

					/**
					 * This is a bitwise operator used below, it will true on any true returns.
					 */
					$count |= user_can( $id, $role );
				}
			}


			if ( $rule_data['operator'] === 'in' ) {
				return wffn_string_to_bool( $count );
			} else {
				return ! wffn_string_to_bool( $count );
			}

		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Customer role %s %s', 'funnel-builder-powerpack' ), $this->get_operators_string( $rule['operator'] ), $this->get_user_role_title( $rule['condition'] ) );
		}
	}
}
if ( ! class_exists( 'WFTY_Rule_Is_Guest' ) ) {
	class WFTY_Rule_Is_Guest extends WFTY_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'is_guest' );
		}

		public function get_possible_rule_operators() {
			return null;
		}

		public function get_possible_rule_values() {
			$operators = array(
				'yes' => __( 'Yes', 'funnel-builder-powerpack' ),
				'no'  => __( 'No', 'funnel-builder-powerpack' ),
			);

			return $operators;
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$order_id = $this->get_rule_instance()->get_environment_var( 'order' );
			$order    = wc_get_order( $order_id );
			if ( ! empty( $order ) && isset( $rule_data['condition'] ) ) {
				$result = ( $order->get_user_id() === 0 );

				return ( 'yes' === $rule_data['condition'] ) ? $result : ! $result;
			}

			return true;

		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Customer %s a guest user.', 'funnel-builder-powerpack' ), $this->get_operators_string( $rule['operator'] ) );
		}


	}
}