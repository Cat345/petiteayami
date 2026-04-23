<?php
/**
 * Funnel Builder Lite Usage Collector Class
 *
 * Collects usage data for Funnel Builder Lite
 *
 * @package FunnelKit Funnel Builder
 * @since 3.13.1.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Usage_Collector' ) && class_exists( 'WooFunnels_Usage_Collector_Abstract' ) ) {

	/**
	 * Class WFFN_Usage_Collector
	 */
	class WFFN_Usage_Collector extends WooFunnels_Usage_Collector_Abstract {

		/**
		 * Static cutoff date: installations after this date collect plugin-specific usage data
		 * Format: YYYY-MM-DD
		 *
		 * @var string
		 */
		private static $cutoff_date = '2026-01-19';

		/**
		 * Plugin identifier
		 *
		 * @var string
		 */
		protected $plugin_id = 'funnel-builder';

		/**
		 * Module type
		 *
		 * @var string
		 */
		protected $module = 'lite';

		/**
		 * Usage version
		 *
		 * @var string
		 */
		protected $usage_version = '1.0.0';

		/**
		 * @var null
		 */
		private static $ins = null;

		/**
		 * @var WFFN_Installation_Config
		 */
		private $installation_config;

		/**
		 * @var WFFN_Feature
		 */
		private $feature;

		/**
		 * @var WFFN_Feature_Performance
		 */
		private $feature_performance;

		/**
		 * WFFN_Usage_Collector constructor.
		 */
		public function __construct() {

			// Don't register if Pro is active (Pro will handle usage)
			if ( class_exists( 'WFFN_Usage_Collector_Pro' ) ) {
				return;
			}

			// Call parent constructor to register with registry
			parent::__construct();

			// Load required usage classes (lazy-loaded, only when this class is instantiated)
			$usage_dir = WFFN_PLUGIN_DIR . '/includes/usage';

			if ( ! class_exists( 'WFFN_Installation_Config' ) && file_exists( $usage_dir . '/class-wffn-installation-config.php' ) ) {
				require_once $usage_dir . '/class-wffn-installation-config.php';
			}
			if ( ! class_exists( 'WFFN_Feature' ) && file_exists( $usage_dir . '/class-wffn-feature.php' ) ) {
				require_once $usage_dir . '/class-wffn-feature.php';
			}
			if ( ! class_exists( 'WFFN_Feature_Performance' ) && file_exists( $usage_dir . '/class-wffn-feature-performance.php' ) ) {
				require_once $usage_dir . '/class-wffn-feature-performance.php';
			}

			// Load WooCommerce usage tracker class
			// This class extends WooFunnels_Usage_Collector_Abstract and registers with WooFunnels_Usage_Registry
			// It collects WooCommerce data asynchronously via scheduled events
			if ( ! class_exists( 'WFFN_WooCommerce_Usage_Collector' ) && file_exists( $usage_dir . '/class-wffn-woocommerce-usage-collector.php' ) ) {
				require_once $usage_dir . '/class-wffn-woocommerce-usage-collector.php';
				// Instantiate to register with registry
				if ( class_exists( 'WFFN_WooCommerce_Usage_Collector' ) ) {
					WFFN_WooCommerce_Usage_Collector::get_instance();
				}
			}

			// Initialize collectors (only if classes exist)
			if ( class_exists( 'WFFN_Installation_Config' ) ) {
				$this->installation_config = new WFFN_Installation_Config();
			}
			if ( class_exists( 'WFFN_Feature' ) ) {
				$this->feature = new WFFN_Feature();
			}
			if ( class_exists( 'WFFN_Feature_Performance' ) ) {
				$this->feature_performance = new WFFN_Feature_Performance();
			}
		}

		/**
		 * Check if plugin-specific usage data should be collected
		 * Uses activation date cutoff logic
		 * Centralized method to avoid duplication across usage classes
		 *
		 * @return bool True if should collect, false otherwise
		 */
		public static function should_collect_plugin_usage_data() {
			$active_date = get_option( 'fk_fb_active_date', array() );

			if ( is_array( $active_date ) && isset( $active_date['lite'] ) && ! empty( $active_date['lite'] ) ) {
				$activation_timestamp = $active_date['lite'];
				$activation_date      = gmdate( 'Y-m-d', $activation_timestamp );

				// If activation date is greater than cutoff date, collect plugin data
				if ( $activation_date > self::$cutoff_date ) {
					return true;
				}
			}

			// Allow Pro to override via filter
			return apply_filters( 'woofunnels_should_collect_plugin_usage_data', false );
		}

		/**
		 * Get the cutoff date
		 *
		 * @return string Cutoff date in YYYY-MM-DD format
		 */
		public static function get_cutoff_date() {
			return self::$cutoff_date;
		}

		/**
		 * Add this collector to the registry filter
		 *
		 * Note: Always registers the collector for scheduling purposes.
		 * The cutoff date check only affects whether data is collected, not whether schedules are created.
		 *
		 * @param array $collectors Existing collectors
		 * @return array
		 */
		public static function add_to_registry( $collectors ) {
			// Always register collector for scheduling purposes
			// The cutoff date check in should_collect_plugin_usage_data() only affects data collection,
			// not whether the collector is registered and can create schedules
			$collectors['funnel-builder'] = array(
				'class'  => __CLASS__,
				'module' => 'lite',
			);

			return $collectors;
		}



		/**
		 * Get instance
		 *
		 * @return WFFN_Usage_Collector|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Check if collector should collect data
		 * Overrides abstract method to include activation date cutoff check
		 *
		 * @return bool
		 */
		public function should_collect() {
			// First check opt-in status (from parent)
			if ( ! parent::should_collect() ) {
				return false;
			}

			// Then check activation date cutoff before collecting plugin-specific usage data
			return self::should_collect_plugin_usage_data();
		}

		/**
		 * Collect usage data
		 *
		 * @return array Usage data
		 */
		public function collect() {

			// Check if should collect (includes opt-in and activation date cutoff checks)
			if ( ! $this->should_collect() ) {
				return array();
			}

			try {
				// Collect all usage data
				$installation_config = $this->installation_config ? $this->installation_config->collect() : array();
				$feature             = $this->feature ? $this->feature->collect() : array();
				$feature_performance = $this->feature_performance ? $this->feature_performance->collect() : array();

				// Build usage data structure
				// Note: Version is not included here as it's already tracked in active_plugins array
				$data = array(
					'module'              => $this->module,
					'installation_config' => $installation_config,
					'feature'             => $feature,
					'feature_performance' => $feature_performance,
				);

				// Note: WooCommerce advanced data is handled separately in core
				// and merged into core 'woocommerce' key, not nested here
				// This prevents redundancy: woocommerce at root AND funnel-builder.woocommerce

				return $data;

			} catch ( Throwable $e ) {
				// Return empty array if collection fails
				return array();
			}
		}

		/**
		 * Setup data - Collects data and saves to options table
		 * Called by individual tracker schedule
		 *
		 * @return bool True on success, false on failure
		 */
		public function setup_data() {
			// Check if should collect (includes opt-in and activation date cutoff checks)
			if ( ! $this->should_collect() ) {
				return false;
			}

			try {
				// Collect all usage data using existing collect() logic
				$data = $this->collect();

				// Save to options table using option key
				$option_key = $this->get_option_key();
				if ( ! empty( $option_key ) ) {
					// Add timestamp to track when data was collected
					$data['_collected_at'] = current_time( 'mysql' );
					update_option( $option_key, $data, false );
					return true;
				}

				return false;

			} catch ( Throwable $e ) {
				// Return false if collection fails
				return false;
			}
		}

		/**
		 * Return data - Returns saved data from options table
		 * Called by final collector to retrieve saved data
		 *
		 * @return array Saved data or empty array if not found
		 */
		public function return_data() {
			$option_key = $this->get_option_key();
			if ( empty( $option_key ) ) {
				return array();
			}

			$data = get_option( $option_key, array() );

			// Remove internal metadata before returning
			if ( is_array( $data ) && isset( $data['_collected_at'] ) ) {
				unset( $data['_collected_at'] );
			}

			return is_array( $data ) ? $data : array();
		}

		/**
		 * Get option key - Returns the option key name for this tracker's data
		 * Each tracker decides its own option key format
		 *
		 * @return string Option key name
		 */
		public function get_option_key() {
			// Use plugin_id to create unique option key
			// Format: fk_usage_data_{plugin_id}
			return 'fk_usage_data_' . $this->plugin_id;
		}
	}

	// Register collector via filter hook (lazy registration - only when class is loaded)
}
