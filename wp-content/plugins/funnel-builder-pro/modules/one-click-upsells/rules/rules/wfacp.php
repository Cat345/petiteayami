<?php

/**
 * Created by PhpStorm.
 * User: sandeep
 * Date: 18/9/18
 * Time: 10:43 AM
 */
if ( ! class_exists( 'WFOCU_Rule_WFACP_Page' ) ) {
	class WFOCU_Rule_WFACP_Page extends WFOCU_Rule_Base {
		public $supports = array( 'cart' );

		public function __construct() {
			parent::__construct( 'wfacp_page' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matches any of', 'woofunnels-order-bump' ),
				'none' => __( 'matches none of', 'woofunnels-order-bump' ),
			);

			return $operators;
		}

		public function get_possible_rule_values() {

			if ( ! defined( 'WFACP_SLUG' ) ) {
				return [];
			}
			$result = array();
			$pages  = $pages = WFACP_Common::save_publish_checkout_pages_in_transient();

			if ( is_array( $pages ) && count( $pages ) > 0 ) {
				unset( $pages[0] );
				foreach ( $pages as $page ) {
					$result[ $page['id'] ] = $page['name'];
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$wfacp_id = WFOCU_Core()->data->get_posted( 'wfacp_embed_form_page_id', 0 );
			if ( empty( $wfacp_id ) ) {
				// For Dedicated and Global checkout
				$wfacp_id = WFOCU_Core()->data->get_posted( '_wfacp_post_id', 0 );
			}
			if ( $wfacp_id ) {
				$wfacp_id = absint( $wfacp_id );
			}
			$wfacp_set = [ $wfacp_id ];
			$result    = false;
			$type      = $rule_data['operator'];

			if ( isset( $rule_data['condition'] ) ) {
				switch ( $type ) {

					case 'any':
						if ( is_array( $rule_data['condition'] ) && is_array( $wfacp_set ) ) {
							$result = count( array_intersect( $rule_data['condition'], $wfacp_set ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rule_data['condition'] ) && is_array( $wfacp_set ) ) {
							$result = count( array_intersect( $rule_data['condition'], $wfacp_set ) ) === 0;
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

			return sprintf( __( 'Checkout Page %s %s', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_checkout_title( $rule['condition'] ) );
		}

	}
}