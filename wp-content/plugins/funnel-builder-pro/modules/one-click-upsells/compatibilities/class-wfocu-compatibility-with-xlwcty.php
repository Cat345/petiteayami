<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_XLWCTY' ) ) {
	class WFOCU_Compatibility_With_XLWCTY {


		const LITE_MIN_VAR = '2.9.5';
		const PRO_MIN_VAR = '1.11.0';
		public $order_id_in_process = null;

		public function __construct() {


			if ( true === function_exists( 'XLWCTY_Core' ) ) {

				/**
				 * Remove compatibility provided by snippet
				 */
				remove_action( 'xlwcty_woocommerce_order_details_after_order_table', 'wfocu_maybe_show_additional_order', 10, 1 );
				remove_action( 'woocommerce_order_details_after_order_table', 'wfocu_maybe_show_additional_order', 10, 1 );
				/**
				 * Show Additional order details in NextMove's Order page.
				 */
				add_action( 'xlwcty_woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 1 );
				add_action( 'woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 1 );


				add_filter( 'xlwcty_before_rules', array( $this, 'capture_order_id_for_which_nextmove_validating_rules' ), 10, 3 );
				add_filter( 'xlwcty_before_rules_validation', array( $this, 'capture_order_id_nextmove_lite_validating_rules' ), 10, 4 );
				add_filter( 'xlwcty_rules_is_match', array( $this, 'maybe_validate_rule_in_case_of_new_and_cancel' ), 10, 2 );

			}
		}


		public function is_enable() {
			if ( true === function_exists( 'XLWCTY_Core' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * @param WC_Order $order_object Current order opening in thank you page.
		 */
		function wfocu_maybe_show_additional_order( $order_object ) {

			if ( ! class_exists( 'XLWCTY_Core' ) ) {
				return;
			}

			if ( ( null === XLWCTY_Core()->public ) || ( false === XLWCTY_Core()->public->is_xlwcty_page() ) ) {
				return;
			}

			if ( ! function_exists( 'WFOCU_Core' ) ) {
				return;
			}
			remove_action( 'xlwcty_woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 1 );
			remove_action( 'woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 1 );

			$sustain_id = $order_object->get_id();

			/**
			 * get primary order id if primary order cancel from upsell order
			 */
			$primary_id = $order_object->get_meta( '_wfocu_primary_order', true );

			if ( empty( $primary_id ) ) {
				$primary_id = $sustain_id;
			}

			/**
			 * get upsell id for get upsell setting
			 */
			$funnel_id = WFOCU_Common::get_order_meta( wc_get_order( $primary_id ), '_wfocu_funnel_id' );


			if ( empty( $funnel_id ) ) {
				return;
			}

			WFOCU_Core()->funnels->setup_funnel_options( $funnel_id );
			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );

			/**
			 * return if upsell setting not configure for create new order
			 */
			if ( empty( $order_behavior ) || 'create_order' !== $order_behavior ) {
				return;
			}

			/**
			 * Try to get if any upstroke order is created for this order as parent
			 */
			$results = WFOCU_Core()->track->query_results( array(
				'data'         => array(),
				'where'        => array(
					array(
						'key'      => 'session.order_id',
						'value'    => WFOCU_WC_Compatibility::get_order_id( $order_object ),
						'operator' => '=',
					),
					array(
						'key'      => 'events.action_type_id',
						'value'    => 4,
						'operator' => '=',
					),
				),
				'where_meta'   => array(
					array(
						'type'       => 'meta',
						'meta_key'   => '_new_order',
						'meta_value' => '',
						'operator'   => '!=',
					),
				),
				'session_join' => true,
				'order_by'     => 'events.id DESC',
				'query_type'   => 'get_results',
			) );

			if ( is_wp_error( $results ) || ( is_array( $results ) && empty( $results ) ) ) {

				/**
				 * Fallback when we are unable to fetch it through our session table, case of cancellation of primary order
				 */
				$get_meta = $order_object->get_meta( '_wfocu_sibling_order', false );
				if ( ( is_array( $get_meta ) && ! empty( $get_meta ) ) ) {
					$results = [];
					foreach ( $get_meta as $meta ) {
						$single = new stdClass();
						if ( $meta->get_data()['value'] instanceof WC_Order ) {
							$single->meta_value = $meta->get_data()['value']->get_id();
						} else {
							$single->meta_value = absint( $meta->get_data()['value'] );
						}

						$results[] = $single;
					}
				}
			}
			if ( empty( $results ) ) {
				return;
			}
			foreach ( $results as $rows ) {

				XLWCTY_Core()->data->load_order( $rows->meta_value );
				XLWCTY_Components::get_components( '_xlwcty_order_details' )->render_view( '_xlwcty_order_details' );

			}

			XLWCTY_Core()->data->load_order( $sustain_id );

		}


		public function capture_order_id_for_which_nextmove_validating_rules( $bool, $content_id, $order_id ) {
			$this->order_id_in_process = $order_id;

			return $bool;
		}

		/**
		 * Capturing order id for nextmove lite validation rules
		 */
		public function capture_order_id_nextmove_lite_validating_rules( $contents, $order_id, $xlwcty_data, $skip_rules ) {
			$this->order_id_in_process = $order_id;

			return $contents;
		}

		public function maybe_validate_rule_in_case_of_new_and_cancel( $result, $rule_data ) {
			if ( true === $result ) {
				return $result;
			}

			if ( 'upstroke' !== $rule_data['rule_type'] ) {
				return $result;
			}

			$get_if_cancelled = WFOCU_Core()->data->get( 'cancelled', false );

			if ( empty( $get_if_cancelled ) ) {
				return $result;
			}

			if ( isset( $rule_data['condition'] ) && isset( $rule_data['operator'] ) ) {
				$funnel_id = get_post_meta( $get_if_cancelled, '_wfocu_funnel_id', true );
				$in        = false;
				if ( in_array( $funnel_id, $rule_data['condition'], true ) ) {
					$in = true;
				}

				$result = 'in' === $rule_data['operator'] ? $in : ! $in;
			}

			return $result;

		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_XLWCTY(), 'xlwcty' );

}

