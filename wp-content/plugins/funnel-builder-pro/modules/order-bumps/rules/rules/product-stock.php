<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'wfob_Rule_product_stock_status' ) ) {
	#[\AllowDynamicProperties]
	class wfob_Rule_product_stock_status extends wfob_Rule_Base {

		public function __construct() {
			parent::__construct( 'product_stock_status' );
		}

		public function get_possible_rule_operators() {
			return array(
				'in_stock'     => __( 'is in stock', 'woofunnels-order-bump' ),
				'out_of_stock' => __( 'is out of stock', 'woofunnels-order-bump' ),
			);
		}

		public function get_condition_input_type() {
			return 'Product_Select';
		}

		public function get_possible_rule_values() {
			return array();
		}

		public function is_match( $rule_data ) {
			if ( ! isset( $rule_data['condition'] ) || ! is_array( $rule_data['condition'] ) ) {
				return $this->return_is_match( false, $rule_data );
			}
			// Frontend sends condition['products'] = [ id1, id2 ].
			$condition   = $rule_data['condition'];
			$product_ids = ! empty( $condition['products'] ) && is_array( $condition['products'] )
				? array_map( 'absint', $condition['products'] )
				: array_map( 'absint', $condition );
			$product_ids = array_filter( $product_ids );
			if ( empty( $product_ids ) ) {
				return $this->return_is_match( false, $rule_data );
			}
			$operator = isset( $rule_data['operator'] ) ? $rule_data['operator'] : '';

			$statuses = array();
			foreach ( $product_ids as $product_id ) {
				if ( $product_id <= 0 ) {
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$statuses[] = $product->get_stock_status();
			}

			if ( empty( $statuses ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			// Two options: in stock / out of stock. Multiple products use AND logic (all must match).
			$result          = false;
			$is_in_stock_op  = in_array( $operator, array( 'in_stock', 'any_in_stock', 'all_in_stock' ), true );
			$is_out_stock_op = in_array( $operator, array( 'out_of_stock', 'any_out_of_stock', 'all_out_of_stock' ), true );
			if ( $is_in_stock_op ) {
				$result = count(
					array_filter(
						$statuses,
						function ( $s ) {
							return 'instock' === $s;
						}
					)
				) === count( $statuses );
			} elseif ( $is_out_stock_op ) {
				$result = count(
					array_filter(
						$statuses,
						function ( $s ) {
							return 'outofstock' === $s;
						}
					)
				) === count( $statuses );
			}

			return $this->return_is_match( $result, $rule_data );
		}
	}
}
