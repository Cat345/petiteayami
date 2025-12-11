<?php
if ( ! class_exists( 'WFOCU_LearnDash_Compatibility' ) ) {
	class WFOCU_LearnDash_Compatibility {

		public function __construct() {
			add_filter( 'wfocu_offer_product_types', array( $this, 'add_course_in_product_type_support' ), 10, 1 );
			add_action( 'plugins_loaded', function () {
				if ( $this->is_enable() ) {
					add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'maybe_create_learn_dash_user' ), 999, 4 );
				}
			} );
		}

		public function is_enable() {
			if ( class_exists( 'learndash_woocommerce' ) ) {
				return true;
			}

			return false;
		}

		public function add_course_in_product_type_support( $types ) {
			array_push( $types, 'course' );

			return $types;
		}

		/**
		 * @param $get_offer_id
		 * @param $get_package
		 * @param $get_parent_order
		 * @param $new_order
		 *
		 * @throws WC_Data_Exception
		 */
		public function maybe_create_learn_dash_user( $get_offer_id, $get_package, $order, $new_order ) {

			if ( ! $order instanceof WC_Order && ! class_exists( 'WC_Product_Course' ) ) {
				return;
			}

			foreach ( $get_package['products'] as $product ) {

				if ( isset( $product['_offer_data'] ) && 'course' === $product['_offer_data']->type ) {
					$get_product = $product['data'];
					if ( ! is_user_logged_in() && true === apply_filters( 'wfocu_create_learndash_user', true, $product, $get_offer_id, $get_package, $order, $new_order ) ) {
						$user_id = WFOCU_Common::create_new_customer( WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' ) );
						$order->set_customer_id( $user_id );
						$order->save();

						WFOCU_Core()->log->log( "A new learndash user is created and logged in for offer id: $get_offer_id with product_id: " . print_r( $get_product->get_id(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						break;
					}
				}
			}
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_LearnDash_Compatibility(), 'wfocu_learndash_compatibility' );
}