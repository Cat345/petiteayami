<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class WFFN_Views_Tracking
 *
 * Pro owns funnel page-view collection (wfco_report_views): funnel sessions,
 * landing / thank-you / optin / optin-TY step views and conversions, and the
 * native store-checkout thank-you view. The free plugin only fires its
 * generic step events; this class records views off those events. Checkout
 * views (types 4 + 7 on cart initiate) are recorded by the Pro checkout
 * module's own reporting class and are not handled here.
 */
if ( ! class_exists( 'WFFN_Views_Tracking' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Views_Tracking {
		private static $ins = null;

		/**
		 * Hooks are registered by WFFN_Pro_Public so this class is only loaded
		 * when a funnel step event actually fires. Nothing to do here.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Views_Tracking|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		private function record( $object_id, $type ) {
			if ( absint( $object_id ) < 1 || ! class_exists( 'WFFN_Report_Views' ) ) {
				return;
			}
			WFFN_Report_Views::update_data( gmdate( 'Y-m-d', current_time( 'timestamp' ) ), $object_id, $type );
		}

		public function record_unique_funnel_session( $current_step, $get_step_object, $funnel ) {
			$funnel_id          = $funnel->get_id();
			$recorded_funnel_id = WFFN_Core()->data->get( 'recorded_funnel_id_' . $funnel_id );

			if ( ( absint( $funnel_id ) ) !== absint( $recorded_funnel_id ) ) {
				$this->record( $funnel_id, 7 );
				WFFN_Core()->data->set( 'recorded_funnel_id_' . $funnel_id, $funnel_id )->save();
				WFFN_Core()->logger->log( __FUNCTION__ . ':: ' . $funnel_id );
			}
		}

		public function record_landing_viewed( $landing_id ) {
			$this->record( $landing_id, 2 );
		}

		public function record_landing_converted( $landing_id ) {
			$this->record( $landing_id, 3 );
		}

		public function record_thankyou_viewed( $thankyou_id ) {
			$this->record( $thankyou_id, 5 );
		}

		public function record_optin_viewed( $optin_id ) {
			$this->record( $optin_id, 8 );
		}

		public function record_optin_ty_viewed( $oty_id ) {
			$this->record( $oty_id, 10 );
		}

		public function record_optin_ty_converted( $oty_id ) {
			$this->record( $oty_id, 11 );
		}

		public function maybe_record_native_thankyou_view( $order_id ) {
			global $post;

			if ( is_null( $post ) || ! isset( $_GET['wfty_source'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			if ( isset( $_COOKIE[ 'wfty_native_' . $order_id ] ) && 'yes' === $_COOKIE[ 'wfty_native_' . $order_id ] ) {
				return;
			}

			/**
			 * increase store checkout funnel thankyou page views when native checkout set
			 */
			if ( 0 === WFFN_Common::get_store_checkout_id() ) {
				return;
			}

			$funnel = new WFFN_Funnel( WFFN_Common::get_store_checkout_id() );

			/**
			 * Check if this is a valid funnel and has native checkout
			 */
			if ( ! wffn_is_valid_funnel( $funnel ) || false === $funnel->is_funnel_has_native_checkout() ) {
				return;
			}

			/**
			 * Record thankyou page views for native store checkout
			 */
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order && empty( $order->get_meta( '_wfacp_post_id' ) ) ) {
				$this->record( $post->ID, 5 );
				WFFN_Core()->data->set_cookie( 'wfty_native_' . $order_id, 'yes', time() + ( DAY_IN_SECONDS * 1 ) );
				WFFN_Core()->logger->log( 'Order #' . $order_id . ': record view thankyou page #' . $_GET['wfty_source'], 'wffn', true ); // phpcs:ignore
			}
		}
	}

}
