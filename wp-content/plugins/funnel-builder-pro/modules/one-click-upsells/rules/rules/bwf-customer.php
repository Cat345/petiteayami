<?php

if ( ! class_exists( 'WFOCU_Rule_Customer_Purchased_Products' ) ) {
	class WFOCU_Rule_Customer_Purchased_Products extends WFOCU_Rule_Base {

		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'customer_purchased_products' );
		}

		public function get_possible_rule_operators() {

			$operators      = array(
				'any'  => __( 'matches any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of', 'woofunnels-order-bump' ),
			);
			$state          = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );
			$needs_indexing = in_array( $state, array( 0, 1, 2, 3, 6 ), true );

			return ( $needs_indexing ) ? null : $operators;
		}

		public function get_condition_input_type() {
			$state          = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );
			$needs_indexing = in_array( $state, array( 0, 1, 2, 3, 6 ), true );

			return ( $needs_indexing ) ? 'Customer_Rule_Unavailable' : 'Product_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			$type   = $rule_data['operator'];

			if ( $env === 'cart' ) {
				$email       = WFOCU_Core()->data->get_posted( 'billing_email', 0 );
				$user_id     = get_current_user_id();
				$bwf_contact = bwf_get_contact( $user_id, $email );
			} else {
				$order_id    = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order       = wc_get_order( $order_id );
				$bwf_contact = bwf_get_contact( $order->get_customer_id(), $order->get_billing_email() );
			}
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

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Customer ever purchased product %s <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_product_title( $rule['condition'] ) );
		}

	}
}
if ( ! class_exists( 'WFOCU_Rule_Customer_Purchased_Cat' ) ) {

	class WFOCU_Rule_Customer_Purchased_Cat extends WFOCU_Rule_Base {

		public $supports = array( 'cart', 'order' );

		public function __construct() {
			parent::__construct( 'customer_purchased_cat' );
		}

		public function get_possible_rule_operators() {

			$operators      = array(
				'any'  => __( 'matches any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of', 'woofunnels-order-bump' ),
			);
			$state          = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );
			$needs_indexing = in_array( $state, array( 0, 1, 2, 3, 6 ), true );

			return ( $needs_indexing ) ? null : $operators;
		}

		public function get_condition_input_type() {
			$state          = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );
			$needs_indexing = in_array( $state, array( 0, 1, 2, 3, 6 ), true );

			return ( $needs_indexing ) ? 'Customer_Rule_Unavailable' : 'Chosen_Select';
		}

		public function get_possible_rule_values() {
			$result = array();

			$terms = get_terms( 'product_cat', array( 'hide_empty' => false ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$result[ $term->term_id ] = $term->name;
				}
			}

			return $result;
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$result = false;
			$type   = $rule_data['operator'];

			if ( $env === 'cart' ) {
				$email       = WFOCU_Core()->data->get_posted( 'billing_email', 0 );
				$user_id     = get_current_user_id();
				$bwf_contact = bwf_get_contact( $user_id, $email );


			} else {
				$order_id    = WFOCU_Core()->rules->get_environment_var( 'order' );
				$order       = wc_get_order( $order_id );
				$bwf_contact = bwf_get_contact( $order->get_customer_id(), $order->get_billing_email() );
			}
			if ( ! $bwf_contact instanceof WooFunnels_Contact ) {
				if ( 'none' === $type ) {
					return $this->return_is_match( true, $rule_data );
				}

				return $this->return_is_match( false, $rule_data );
			}
			$bwf_contact->set_customer_child();
			$purchased_cats = $bwf_contact->get_customer_purchased_products_cats();

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {
					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $purchased_cats ) ) {
							$result = count( array_intersect( $rule_data['condition'], $purchased_cats ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $purchased_cats ) ) {
							$result = count( array_intersect( $rule_data['condition'], $purchased_cats ) ) === 0;
						}
						break;
					default:
						$result = false;
						break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}

		public function get_nice_string( $rule ) {

			return sprintf( __( 'Customer ever purchased products %s category <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_category_title( $rule['condition'] ) );
		}


	}
}