<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Funnel Public facing functionality
 * Class WFFN_Pro_Public
 */
if ( ! class_exists( 'WFFN_Pro_Public' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Public {

		private static $ins = null;
		public $environment = null;

		/**
		 * WFFN_Pro_Public constructor.
		 *
		 * The view hooks are registered here rather than inside the classes that
		 * implement them. Registration costs a handful of add_action calls; the
		 * implementing class is pulled in only when one of these actually fires,
		 * so a request that touches no funnel step and asks for no report never
		 * loads it at all.
		 */
		public function __construct() {
			$this->register_view_reporting();

			/**
			 * REST controllers. Their route registration is the only reason they
			 * were ever loaded, so hook it from here and resolve the controller
			 * when the hook fires -- see register_rest_controllers().
			 */
			$this->register_rest_controllers();

			/**
			 * Licence state is read during a frontend purchase by upsells and order
			 * bumps, so these cannot be gated behind is_admin().
			 */
			$this->register_license_config();

			/**
			 * Recording only. An older free plugin still records these itself, so
			 * stand down there rather than counting everything twice.
			 */
			if ( class_exists( 'WFFN_Public' ) && method_exists( 'WFFN_Public', 'increase_funnel_visit_session_view' ) ) {
				return;
			}

			add_action( 'wffn_mark_pending_conversions', array( $this, 'record_unique_funnel_session' ), 5, 3 );
			add_action( 'wffn_event_step_viewed_landing', array( $this, 'record_landing_viewed' ), 10, 1 );
			add_action( 'wffn_event_step_converted_landing', array( $this, 'record_landing_converted' ), 10, 1 );
			add_action( 'wffn_event_step_viewed_wc_thankyou', array( $this, 'record_thankyou_viewed' ), 10, 1 );
			add_action( 'wffn_event_step_viewed_optin', array( $this, 'record_optin_viewed' ), 10, 1 );
			add_action( 'wffn_event_step_viewed_optin_ty', array( $this, 'record_optin_ty_viewed' ), 10, 1 );
			add_action( 'wffn_event_step_converted_optin_ty', array( $this, 'record_optin_ty_converted' ), 10, 1 );
			add_action( 'woocommerce_thankyou', array( $this, 'maybe_record_native_thankyou_view' ), 999, 1 );
		}

		/**
		 * Licence state seams. Registered unconditionally -- upsells and order
		 * bumps read them during a frontend purchase.
		 *
		 * @return void
		 */
		private function register_license_config() {
			add_filter( 'wffn_license_config', array( $this, 'license_config' ), 10, 3 );
			add_filter( 'wffn_license_expiry', array( $this, 'license_expiry' ) );
		}

		/**
		 * @return WFFN_License_Config|null
		 */
		private function license_config_store() {
			return class_exists( 'WFFN_License_Config' ) ? WFFN_License_Config::get_instance() : null;
		}

		/**
		 * @param array $config      Incoming default.
		 * @param bool  $expiry_only Expiry information only.
		 * @param bool  $get_ad      Include activation dates.
		 *
		 * @return array
		 */
		public function license_config( $config, $expiry_only = false, $get_ad = true ) {
			$store = $this->license_config_store();

			return $store ? $store->filter_license_config( $config, $expiry_only, $get_ad ) : $config;
		}

		/**
		 * @param string $expiry Incoming default.
		 *
		 * @return string
		 */
		public function license_expiry( $expiry ) {
			$store = $this->license_config_store();

			return $store ? $store->filter_license_expiry( $expiry ) : $expiry;
		}

		/**
		 * Read-side seams the free plugin exposes. Registered unconditionally --
		 * they are asked for from the admin, from REST and from usage reporting on
		 * cron, so they cannot be gated behind is_admin().
		 *
		 * @return void
		 */
		private function register_view_reporting() {
			add_filter( 'wffn_report_views_rows', array( $this, 'report_views_rows' ), 10, 2 );
			add_filter( 'wffn_report_views_row', array( $this, 'report_views_row' ), 10, 2 );
			add_filter( 'wffn_report_views_count', array( $this, 'report_views_count' ), 10, 2 );
		}

		/**
		 * REST controller seams.
		 *
		 * These three controllers used to be required on every request -- roughly
		 * 140 KB of PHP parsed to register routes that only a REST request can
		 * reach. They are autoloadable by class name, so hook the registration
		 * here and let the callback pull the controller in when it actually fires.
		 *
		 * Registered from the public class rather than the admin one because
		 * rest_api_init is not admin-only; admin_post_ simply never fires outside
		 * wp-admin, so it costs nothing to sit here too.
		 *
		 * @return void
		 */
		private function register_rest_controllers() {
			add_action( 'rest_api_init', array( $this, 'register_analytics_routes' ), 12 );
			add_action( 'rest_api_init', array( $this, 'register_reset_routes' ), 12 );
			add_action( 'rest_api_init', array( $this, 'register_import_export_routes' ) );
			add_action( 'admin_post_bwf_contact_export_download', array( $this, 'download_export' ) );
			add_action( 'wffn_top_sales_funnels', array( $this, 'top_sales_funnels' ), 10, 2 );
		}

		/**
		 * The analytics controller only declares itself when the free plugin's
		 * WFFN_REST_Controller base is present, so class_exists() is a real check
		 * here, not just an autoload trigger.
		 *
		 * @return void
		 */
		public function register_analytics_routes() {
			if ( class_exists( 'WFFN_REST_API_EndPoint' ) ) {
				WFFN_REST_API_EndPoint::get_instance()->register_endpoint();
			}
		}

		/**
		 * @return void
		 */
		public function register_reset_routes() {
			if ( class_exists( 'WFFN_RESET_API_EndPoint' ) ) {
				WFFN_RESET_API_EndPoint::get_instance()->register_endpoint();
			}
		}

		/**
		 * @return void
		 */
		public function register_import_export_routes() {
			if ( class_exists( 'WFFN_REST_Import_Export' ) ) {
				WFFN_REST_Import_Export::get_instance()->register_routes();
			}
		}

		/**
		 * @return void
		 */
		public function download_export() {
			if ( class_exists( 'WFFN_REST_Import_Export' ) ) {
				WFFN_REST_Import_Export::get_instance()->download_export();
			}
		}

		/**
		 * @param mixed $total_sale Running total passed by the action.
		 * @param mixed $ids        Funnel ids passed by the action.
		 *
		 * @return void
		 */
		public function top_sales_funnels( $total_sale, $ids ) {
			if ( class_exists( 'WFFN_REST_API_EndPoint' ) ) {
				WFFN_REST_API_EndPoint::get_instance()->get_top_sales_funnels( $total_sale, $ids );
			}
		}

		/**
		 * @return WFFN_Pro_Public|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Loads the recorder on first use.
		 *
		 * @return WFFN_Views_Tracking|null
		 */
		private function views_tracking() {
			return class_exists( 'WFFN_Views_Tracking' ) ? WFFN_Views_Tracking::get_instance() : null;
		}

		/**
		 * Loads the reader on first use.
		 *
		 * @return WFFN_Views_Reporting|null
		 */
		private function views_reporting() {
			return class_exists( 'WFFN_Views_Reporting' ) ? WFFN_Views_Reporting::get_instance() : null;
		}

		/**
		 * @param mixed $current_step    Current step.
		 * @param mixed $get_step_object Step object.
		 * @param mixed $funnel          Funnel.
		 *
		 * @return void
		 */
		public function record_unique_funnel_session( $current_step, $get_step_object, $funnel ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_unique_funnel_session( $current_step, $get_step_object, $funnel );
			}
		}

		/**
		 * @param int $landing_id Landing step id.
		 *
		 * @return void
		 */
		public function record_landing_viewed( $landing_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_landing_viewed( $landing_id );
			}
		}

		/**
		 * @param int $landing_id Landing step id.
		 *
		 * @return void
		 */
		public function record_landing_converted( $landing_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_landing_converted( $landing_id );
			}
		}

		/**
		 * @param int $thankyou_id Thank-you step id.
		 *
		 * @return void
		 */
		public function record_thankyou_viewed( $thankyou_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_thankyou_viewed( $thankyou_id );
			}
		}

		/**
		 * @param int $optin_id Optin step id.
		 *
		 * @return void
		 */
		public function record_optin_viewed( $optin_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_optin_viewed( $optin_id );
			}
		}

		/**
		 * @param int $oty_id Optin thank-you step id.
		 *
		 * @return void
		 */
		public function record_optin_ty_viewed( $oty_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_optin_ty_viewed( $oty_id );
			}
		}

		/**
		 * @param int $oty_id Optin thank-you step id.
		 *
		 * @return void
		 */
		public function record_optin_ty_converted( $oty_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->record_optin_ty_converted( $oty_id );
			}
		}

		/**
		 * @param int $order_id Order id.
		 *
		 * @return void
		 */
		public function maybe_record_native_thankyou_view( $order_id ) {
			$tracker = $this->views_tracking();
			if ( $tracker ) {
				$tracker->maybe_record_native_thankyou_view( $order_id );
			}
		}

		/**
		 * @param array $rows Default rows.
		 * @param array $args Query arguments.
		 *
		 * @return array
		 */
		public function report_views_rows( $rows, $args = array() ) {
			$reader = $this->views_reporting();

			return $reader ? $reader->get_rows( $rows, $args ) : $rows;
		}

		/**
		 * @param array $row  Default row.
		 * @param array $args Query arguments.
		 *
		 * @return array
		 */
		public function report_views_row( $row, $args = array() ) {
			$reader = $this->views_reporting();

			return $reader ? $reader->get_row( $row, $args ) : $row;
		}

		/**
		 * @param int   $count Default count.
		 * @param array $args  Query arguments.
		 *
		 * @return int
		 */
		public function report_views_count( $count, $args = array() ) {
			$reader = $this->views_reporting();

			return $reader ? $reader->get_count( $count, $args ) : $count;
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'public', 'WFFN_Pro_Public' );
	}
}
