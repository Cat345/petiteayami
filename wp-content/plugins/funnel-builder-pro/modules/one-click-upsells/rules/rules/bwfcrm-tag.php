<?php

/**
 * Created by PhpStorm.
 * User: sandeep
 * Date: 18/9/18
 * Time: 10:43 AM
 */
if ( ! class_exists( 'WFOCU_Rule_Automation_Tag' ) ) {
	class WFOCU_Rule_Automation_Tag extends WFOCU_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'automation_tag' );
		}

		public function get_possible_rule_operators() {

			$operators = array(
				'any'  => __( 'matched any of', 'woofunnels-upstroke-one-click-upsell' ),
				'all'  => __( 'matches all of ', 'woofunnels-upstroke-one-click-upsell' ),
				'none' => __( 'matches none of ', 'woofunnels-upstroke-one-click-upsell' ),

			);

			return $operators;
		}

		public function get_possible_rule_values() {
			$result   = array();
			$tag_data = BWFCRM_Tag::get_tags( array(), false, '', '' );
			if ( is_array( $tag_data ) && count( $tag_data ) > 0 ) {
				foreach ( $tag_data as $tag ) {
					if ( ! empty ( $tag['ID'] ) ) {
						$result[ $tag['ID'] ] = $tag['name'];
					}
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {//phpcs:ignore
			$result    = false;
			$type      = $rule_data['operator'];
			$all_terms = array();

			$order_id = WFOCU_Core()->rules->get_environment_var( 'order' );
			$order    = wc_get_order( $order_id );
			$cid      = BWF_WC_Compatibility::get_order_meta( $order, '_woofunnel_cid' );

			if ( empty( $cid ) ) {
				$cid = $order->get_user_id();
			}
			$bwf_contact = bwf_get_contact( $cid, $order->get_billing_email() );
			if ( $bwf_contact instanceof WooFunnels_Contact && 0 !== $bwf_contact->get_id() ) {
				$all_terms = $bwf_contact->get_tags();
			}

			if ( empty( $all_terms ) ) {
				return $this->return_is_match( false, $rule_data );
			}

			if ( isset( $rule_data['condition'] ) && isset( $rule_data['condition']['categories'] ) ) {
				$rules = $rule_data['condition']['categories'];
				switch ( $type ) {
					case 'all':
						if ( is_array( $rules ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rules, $all_terms ) ) === count( $rules );
						}
						break;
					case 'any':
						if ( is_array( $rules ) && is_array( $all_terms ) ) {
							$result = count( array_intersect( $rules, $all_terms ) ) >= 1;
						}
						break;
					case 'none':
						if ( is_array( $rules ) && is_array( $all_terms ) ) {
							$result = ( count( array_intersect( $rules, $all_terms ) ) === 0 );
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

			return sprintf( __( 'Open Upsell %s Automation Tags <strong>%s</strong>', 'woofunnels-upstroke-one-click-upsell' ), $this->get_operators_string( $rule['operator'] ), $this->get_category_title( $rule['condition'] ) );
		}

	}
}