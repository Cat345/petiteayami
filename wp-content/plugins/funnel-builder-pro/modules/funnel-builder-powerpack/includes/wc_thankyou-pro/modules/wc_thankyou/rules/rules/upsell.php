<?php
if ( ! class_exists( 'WFTY_Rule_Upsell_Offer' ) ) {
	#[\AllowDynamicProperties]
	class WFTY_Rule_Upsell_Offer extends WFTY_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'upsell_offer' );
		}

		public function get_possible_rule_operators() {
			return array(
				'accepted'  => __( 'accepted', 'funnel-builder-powerpack' ),
				'rejected'  => __( 'rejected', 'funnel-builder-powerpack' ),
				'came_from' => __( 'came from', 'funnel-builder-powerpack' ),
			);
		}

		/**
		 * Offers of the funnel this thank you step belongs to. Offer names repeat across
		 * upsell steps, so each label carries its step and offer id to stay unique.
		 *
		 * @return array
		 */
		public function get_possible_rule_values() {
			$result      = array();
			$thankyou_id = $this->get_rule_instance()->get_environment_var( 'thankyou_id' );
			$funnel_id   = $thankyou_id ? get_post_meta( $thankyou_id, '_bwf_in_funnel', true ) : 0;

			if ( empty( $funnel_id ) || ! class_exists( 'WFOCU_Core' ) ) {
				return $result;
			}

			$funnel = WFFN_Core()->admin->get_funnel( $funnel_id );
			if ( ! $funnel instanceof WFFN_Funnel ) {
				return $result;
			}

			foreach ( $funnel->get_steps() as $step ) {
				if ( 'wc_upsells' !== $step['type'] ) {
					continue;
				}
				$offers = WFOCU_Core()->funnels->get_funnel_steps( $step['id'] );
				foreach ( is_array( $offers ) ? $offers : array() as $offer ) {
					/* translators: 1: upsell step title, 2: offer title, 3: offer id */
					$result[ $offer['id'] ] = sprintf( __( '%1$s - %2$s (#%3$d)', 'funnel-builder-powerpack' ), get_the_title( $step['id'] ), $offer['name'], $offer['id'] );
				}
			}

			return $result;
		}

		public function get_condition_input_type() {
			return 'Chosen_Select';
		}

		public function is_match( $rule_data, $env = 'cart' ) {
			$order = wc_get_order( $this->get_rule_instance()->get_environment_var( 'order' ) );
			if ( ! $order instanceof WC_Order || empty( $rule_data['condition'] ) || empty( $rule_data['operator'] ) ) {
				return false;
			}

			$offers = array_map( 'absint', (array) $rule_data['condition'] );
			$states = $this->get_rule_instance()->get_offer_states( $order );

			if ( 'came_from' === $rule_data['operator'] ) {
				$visited = array_keys( $states );

				return $this->return_is_match( in_array( absint( end( $visited ) ), $offers, true ), $rule_data );
			}

			$result = false;
			foreach ( $offers as $offer_id ) {
				if ( isset( $states[ $offer_id ] ) && $rule_data['operator'] === $states[ $offer_id ] ) {
					$result = true;
					break;
				}
			}

			return $this->return_is_match( $result, $rule_data );
		}
	}
}

if ( ! class_exists( 'WFTY_Rule_Upsell_Skipped' ) ) {
	#[\AllowDynamicProperties]
	class WFTY_Rule_Upsell_Skipped extends WFTY_Rule_Base {
		public $supports = array( 'order' );

		public function __construct() {
			parent::__construct( 'upsell_skipped' );
		}

		public function get_possible_rule_operators() {
			return null;
		}

		public function get_possible_rule_values() {
			return array(
				'yes' => __( 'Yes', 'funnel-builder-powerpack' ),
				'no'  => __( 'No', 'funnel-builder-powerpack' ),
			);
		}

		/**
		 * Skipped means the order reached the thank you page without the buyer being shown
		 * any offer, whatever the reason.
		 */
		public function is_match( $rule_data, $env = 'cart' ) {
			$order = wc_get_order( $this->get_rule_instance()->get_environment_var( 'order' ) );
			if ( ! $order instanceof WC_Order || empty( $rule_data['condition'] ) ) {
				return false;
			}

			$skipped = empty( $this->get_rule_instance()->get_offer_states( $order ) );

			return $this->return_is_match( 'yes' === $rule_data['condition'] ? $skipped : ! $skipped, $rule_data );
		}
	}
}
