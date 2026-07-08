<?php
/**
 * Pro Tracking Scheduler Class
 *
 * Overrides Lite schedule actions to ensure Pro tracking runs
 *
 * @package FunnelKit Funnel Builder Pro
 * @since 3.13.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Tracking_Scheduler_Pro' ) ) {

	/**
	 * Class WFFN_Tracking_Scheduler_Pro
	 */
	#[\AllowDynamicProperties]
	class WFFN_Tracking_Scheduler_Pro {

		/**
		 * Singleton instance
		 *
		 * @var WFFN_Tracking_Scheduler_Pro|null
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @return WFFN_Tracking_Scheduler_Pro
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			// Hook into init to remove Lite hooks after they're added
			// WooFunnels_OptIn_Manager::init() runs on admin_init, so we hook into init (earlier)
			// to ensure we can remove the hooks before they fire
			add_action( 'init', array( $this, 'remove_lite_schedule_actions' ), 20 );
			add_action( 'init', array( $this, 'add_pro_schedule_actions' ), 21 );
		}

		/**
		 * Initialize scheduler (lazy loading - only when Pro collector is registered)
		 * Hooked to init to ensure it runs before schedule actions fire
		 */
		public static function maybe_init() {
			// Only initialize if Pro collector class exists (means Pro is active)
			if ( class_exists( 'WFFN_Usage_Collector_Pro' ) && null === self::$instance ) {
				self::get_instance();
			}
		}

		/**
		 * Remove Lite schedule actions
		 * Called on init hook to ensure Lite hooks are already added
		 */
		public function remove_lite_schedule_actions() {
			// Remove Lite's maybe_track_usage hook
			remove_action( 'fk_fb_every_day', array( 'WooFunnels_OptIn_Manager', 'maybe_track_usage' ), 10 );
			remove_action( 'bwf_track_usage_scheduled_single', array( 'WooFunnels_OptIn_Manager', 'maybe_track_usage' ), 10 );
		}

		/**
		 * Add Pro schedule actions
		 * Called on init hook after removing Lite hooks
		 */
		public function add_pro_schedule_actions() {
			// Add Pro tracking hooks (Pro always tracks, no opt-in check)
			add_action( 'fk_fb_every_day', array( $this, 'maybe_track_usage' ), 10, 2 );
			add_action( 'bwf_track_usage_scheduled_single', array( $this, 'maybe_track_usage' ), 10, 2 );
		}

		/**
		 * Pro tracking callback (always tracks, no opt-in check)
		 *
		 * @param bool|string $is_jit Whether this is a just-in-time tracking call
		 * @param string      $product Product identifier
		 */
		public function maybe_track_usage( $is_jit = false, $product = 'FB' ) {
			// Pro always tracks (no opt-in check)
			if ( ! class_exists( 'WooFunnels_OptIn_Manager' ) ) {
				return;
			}

			// Collect all tracking data
			$data = WooFunnels_OptIn_Manager::collect_all_tracking_data();

			if ( $is_jit === 'yes' ) {
				$data['jit'] = 'yes';
			}
			$data['product'] = $product;

			// Post data to API
			if ( class_exists( 'WooFunnels_API' ) ) {
				WooFunnels_API::post_tracking_data( $data );
			}
		}
	}

	// Auto-initialize scheduler on init hook (lazy loading - only if Pro collector exists)
	// Priority 15 ensures it runs after WooFunnels_OptIn_Manager::init() (which runs on admin_init)
	// but before schedule actions might fire
	add_action( 'init', array( 'WFFN_Tracking_Scheduler_Pro', 'maybe_init' ), 15 );
}
