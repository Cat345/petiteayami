<?php
if ( ! class_exists( 'WFFN_RESET_API_EndPoint' ) ) {
	#[AllowDynamicProperties]
	class WFFN_RESET_API_EndPoint {

		private static $ins = null;

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'funnel-analytics';

		/**
		 * WFFN_RESET_API_EndPoint constructor.
		 */
		public function __construct() {

			add_action( 'rest_api_init', [ $this, 'register_endpoint' ], 12 );

		}

		/**
		 * @return WFFN_RESET_API_EndPoint|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function register_endpoint() {
			register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/reset/', array(
				array(
					'args'                => [],
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'reset_stats' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' ),
				),
			) );
		}

		/**
		 * @return bool
		 */
		public function get_write_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'analytics', 'write' );
		}

		public function reset_stats( $request ) {
			$response = array(
				'status' => false
			);

			$funnel_id = ( isset( $request['id'] ) && '' !== $request['id'] ) ? intval( $request['id'] ) : 0;

			if ( $funnel_id > 0 ) {
				$response = $this->get_all_funnel_posts( $funnel_id );
			}

			return rest_ensure_response( $response );
		}

		public function get_all_funnel_posts( $funnel_id ) {
			global $wpdb;

			$response = array(
				'status' => false
			);

			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} AS posts LEFT JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id WHERE postmeta.meta_key = '_bwf_in_funnel' AND postmeta.meta_value LIKE %s ORDER BY posts.ID ASC", $funnel_id ) );

			if ( ! is_array( $ids ) || count( $ids ) === 0 ) {
				return $response;
			}

			if ( class_exists( 'WFACP_Contacts_Analytics' ) ) {
				$aero_obj = WFACP_Contacts_Analytics::get_instance();
				$aero_obj->reset_analytics( $funnel_id );
			}

			if ( class_exists( 'WFFN_Optin_Contacts_Analytics' ) ) {
				$optin_obj = WFFN_Optin_Contacts_Analytics::get_instance();
				$optin_obj->reset_analytics( $funnel_id );
			}

			if ( class_exists( 'WFOB_Contacts_Analytics' ) ) {
				$bump_obj = WFOB_Contacts_Analytics::get_instance();
				$bump_obj->reset_analytics( $funnel_id );
			}

			if ( class_exists( 'WFOCU_Contacts_Analytics' ) ) {
				$upsell_obj = WFOCU_Contacts_Analytics::get_instance();
				$upsell_obj->reset_analytics( $funnel_id );
			}

			$wpdb->query( $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "bwf_conversion_tracking WHERE funnel_id=%d", $funnel_id ) );


			$ids[] = $funnel_id;

			foreach ( $ids as $id ) {
				$wfco_table = $wpdb->prefix . "wfco_report_views";
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wfco_table} WHERE object_id=%d", $id ) );
			}

			/**
			 * Delete native checkout data if store checkout funnel not have checkout steps
			 */
			if ( method_exists( 'WFFN_Common', 'get_store_checkout_id' ) && WFFN_Common::get_store_checkout_id() === absint( $funnel_id ) ) {
				$wfco_table = $wpdb->prefix . "wfco_report_views";
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wfco_table} WHERE WHERE object_id= %d AND type = %d", 0, 4 ) );
			}

			WooFunnels_Transient::get_instance()->delete_transient( '_bwf_contacts_funnels_' . $funnel_id );
			$funnel = new WFFN_Funnel( $funnel_id );

			return array(
				'status'     => true,
				'count_data' => array(
					'steps' => $funnel->get_step_count(),
				)
			);

		}

	}

	WFFN_RESET_API_EndPoint::get_instance();
}