<?php

/**
 * Show Additional order details in Funnel Builder thankyou page Order page.
 */
if ( ! class_exists( 'WFOCU_Compatibility_With_WFTY' ) ) {
	class WFOCU_Compatibility_With_WFTY {

		public function __construct() {

			if ( true === function_exists( 'WFFN_Core' ) ) {
				add_action( 'wfty_woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 2 );
				add_action( 'woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 1 );
				add_filter( 'wfty_maybe_change_order_id', array( $this, 'maybe_parent_order_cancel' ), 10, 3 );
			}
		}


		public function is_enable() {
			if ( true === function_exists( 'WFFN_Core' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * @param WC_Order $order_object Current order opening in thank you page.
		 */
		function wfocu_maybe_show_additional_order( $order_object, $args = [] ) {

			if ( ! class_exists( 'WFFN_Core' ) ) {
				return;
			}

			if ( false === WFFN_Core()->thank_you_pages->is_wfty_page() ) {
				return;
			}

			if ( ! function_exists( 'WFOCU_Core' ) ) {
				return;
			}

			remove_action( 'wfty_woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 2 );
			remove_action( 'woocommerce_order_details_after_order_table', array( $this, 'wfocu_maybe_show_additional_order' ), 10, 2 );

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

				if ( 0 !== $rows->meta_value ) {

					$order = wc_get_order( $rows->meta_value );

					if ( $order instanceof WC_Order ) {
						echo WFTY_Woo_Order_Data::get_order_details( $order, $args );
					}

				}

			}

			$sustain_order = wc_get_order( $sustain_id );
			if ( $sustain_order instanceof WC_Order ) {
				$order_details_component = new WFTY_Order_Details_Component( $args );
				$order_details_component->load_order( $sustain_order );
			}

		}


		/**
		 * @param $key
		 * @param $order
		 * @param $current_step
		 *
		 * @return bool
		 *
		 * Open thankyou page when primary order cancel and thankyou rule match upsall new created order
		 */
		function maybe_parent_order_cancel( $key, $order, $current_step ) {

			if ( $key === false ) {

				$current_order = WFOCU_Core()->data->get_current_order();
				if ( $current_order instanceof WC_Order ) {
					$wfacp_id = WFOCU_Common::get_order_meta( $current_order, '_wfacp_post_id' );
					if ( ! empty( $wfacp_id ) ) {
						if ( absint( $current_step['id'] ) === absint( $wfacp_id ) ) {
							return true;
						}
					}
				}
				$parent_order = WFOCU_Core()->data->get_parent_order();
				if ( $parent_order instanceof WC_Order ) {
					$wfacp_id = WFOCU_Common::get_order_meta( $parent_order, '_wfacp_post_id' );
					if ( ! empty( $wfacp_id ) ) {
						if ( absint( $current_step['id'] ) === absint( $wfacp_id ) ) {
							return true;
						}
					}
				}
			}

			return false;

		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WFTY(), 'fbwfty' );
}



