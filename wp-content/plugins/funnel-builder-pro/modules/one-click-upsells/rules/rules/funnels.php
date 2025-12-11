<?php
if ( ! class_exists( 'WFOCU_Rule_Funnel_Skip' ) ) {

	class WFOCU_Rule_Funnel_Skip extends WFOCU_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'funnel_skip' );
		}

		public function get_possible_rule_operators() {
			$operators = array(
				'if' => __( 'If', 'woofunnels-upstroke-one-click-upsell' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {
			return array(
				'onetime' => __( 'User previously viewed funnel', 'woofunnels-upstroke-one-click-upsell' ),
			);
		}

		public function get_condition_input_type() {
			return 'Select';
		}

		public function is_match( $rule_data, $env = 'order' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			if ( ! isset( $rule_data['condition'] ) ) {
				return false;
			}
			if ( 'onetime' === $rule_data['condition'] ) {
				$funnel_id = WFOCU_Core()->rules->get_environment_var( 'funnel_id' );
				$email     = WFOCU_Core()->data->get_posted( 'billing_email', '' );

				if ( '' === $email ) {
					return true;
				}

				$results = WFOCU_Core()->track->query_results( array(
					'data'         => array(),
					'where'        => array(
						array(
							'key'      => 'session.email',
							'value'    => $email,
							'operator' => '=',
						),
						array(
							'key'      => 'events.action_type_id',
							'value'    => 1,
							'operator' => '=',
						),
						array(
							'key'      => 'events.object_id',
							'value'    => $funnel_id,
							'operator' => '=',
						),

					),
					'session_join' => true,
					'order_by'     => 'events.id DESC',
					'query_type'   => 'get_results',
				) );

				if ( count( $results ) > 0 ) {
					return false;
				}

				return true;
			}

			if ( 'products' === $rule_data['condition'] ) {
				global $woocommerce;
				$funnel_id    = WFOCU_Core()->rules->get_environment_var( 'funnel_id' );
				$get_products = WFOCU_Core()->funnels->get_funnel_products( $funnel_id );

				$cart_contents = $woocommerce->cart->get_cart();

				$found_quantity = 0;
				if ( empty( $get_products ) ) {
					return false;
				}
				$get_products = WFOCU_Common::array_flatten( $get_products );
				/**
				 * This logic only supports simple and variation products
				 * We save variable product in the backend and hence if we have variable product in the db thats gonna be ignored as cart cannot have variables.
				 */
				if ( $cart_contents && is_array( $cart_contents ) && count( $cart_contents ) ) {
					foreach ( $cart_contents as $cart_item ) {

						$cart_item_product = $cart_item['product_id'];
						if ( isset( $cart_item['variation_id'] ) && 0 < $cart_item['variation_id'] ) {
							$cart_item_product = $cart_item['variation_id'];
						}

						if ( in_array( $cart_item_product, $get_products, true ) ) {
							$found_quantity ++;
						}
					}
				}

				if ( count( $get_products ) === $found_quantity ) {
					return false;
				}

				return true;
			}

			return false;

		}

		public function get_nice_string( $rule ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			return sprintf( __( 'Skip One Click Upsells if User Previously Viewed Upsells', 'woofunnels-upstroke-one-click-upsell' ) );
		}

	}
}