<?php
/**
 * Funnel Builder Pro Usage Collector Class
 *
 * Collects usage data for Funnel Builder Pro (includes Lite data)
 *
 * @package FunnelKit Funnel Builder Pro
 * @since 3.13.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Usage_Collector_Pro' ) && class_exists( 'WooFunnels_Usage_Collector_Abstract' ) ) {

	/**
	 * Class WFFN_Usage_Collector_Pro
	 */
	class WFFN_Usage_Collector_Pro extends WooFunnels_Usage_Collector_Abstract {

		/**
		 * Static cutoff date: installations after this date collect plugin-specific usage data
		 * Format: YYYY-MM-DD
		 * Note: Same as Lite collector for consistency
		 *
		 * @var string
		 */
		private static $cutoff_date = '2025-12-17';

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
		protected $module = 'pro';

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
		 * @var WFFN_Installation_Config_Pro
		 */
		private $installation_config;

		/**
		 * @var WFFN_Feature_Pro
		 */
		private $feature;

		/**
		 * @var WFFN_Feature_Performance_Pro
		 */
		private $feature_performance;

		/**
		 * WFFN_Usage_Collector_Pro constructor.
		 */
		public function __construct() {
			// Call parent constructor to register with registry (Pro overrides Lite)
			parent::__construct();

			// Override Lite's cutoff date check for WooCommerce usage data collection
			// Pro always collects WooCommerce data, regardless of Lite's cutoff date
			// This ensures rich WooCommerce tracking data is available when Pro is active
			add_filter( 'woofunnels_should_collect_plugin_usage_data', array( $this, 'override_woocommerce_collection_check' ), 999 );

			// Initialize Pro collectors (lazy-loaded, only when this class is instantiated)
			// Ensure classes are loaded before instantiating
			$usage_dir = WFFN_PRO_PLUGIN_DIR . '/includes/usage';
			if ( ! class_exists( 'WFFN_Installation_Config_Pro' ) && file_exists( $usage_dir . '/class-wffn-installation-config-pro.php' ) ) {
				require_once $usage_dir . '/class-wffn-installation-config-pro.php';
			}
			if ( ! class_exists( 'WFFN_Feature_Pro' ) && file_exists( $usage_dir . '/class-wffn-feature-pro.php' ) ) {
				require_once $usage_dir . '/class-wffn-feature-pro.php';
			}
			if ( ! class_exists( 'WFFN_Feature_Performance_Pro' ) && file_exists( $usage_dir . '/class-wffn-feature-performance-pro.php' ) ) {
				require_once $usage_dir . '/class-wffn-feature-performance-pro.php';
			}
			// Load WooCommerce advanced data collector class (Pro version)
			// This class hooks into 'woofunnels_advanced_woocommerce_usage_data' filter
			// to add advanced WooCommerce data to core 'woocommerce' key
			if ( ! class_exists( 'WFFN_WooCommerce_Usage_Pro' ) && file_exists( $usage_dir . '/class-wffn-woocommerce-usage-pro.php' ) ) {
				require_once $usage_dir . '/class-wffn-woocommerce-usage-pro.php';
				// Instantiate to register filter hook
				if ( class_exists( 'WFFN_WooCommerce_Usage_Pro' ) ) {
					new WFFN_WooCommerce_Usage_Pro();
				}
			}



			// Load Lite WooCommerce Usage Collector class (for async tracking)
			// This class extends WooFunnels_Usage_Collector_Abstract and registers with WooFunnels_Usage_Registry
			// It collects WooCommerce data asynchronously via scheduled events
			if ( class_exists( 'WooCommerce' ) && ! class_exists( 'WFFN_WooCommerce_Usage_Collector' ) ) {
				$lite_collector_file = defined( 'WFFN_PLUGIN_DIR' ) ? WFFN_PLUGIN_DIR . '/includes/usage/class-wffn-woocommerce-usage-collector.php' : '';
				if ( ! empty( $lite_collector_file ) && file_exists( $lite_collector_file ) ) {
					require_once $lite_collector_file;
					// Instantiate to register with registry (filter hook at bottom of file will register it)
					if ( class_exists( 'WFFN_WooCommerce_Usage_Collector' ) ) {
						WFFN_WooCommerce_Usage_Collector::get_instance();
					}
				}
			}

			// Only instantiate if classes exist
			if ( class_exists( 'WFFN_Installation_Config_Pro' ) ) {
				$this->installation_config = new WFFN_Installation_Config_Pro();
			}
			if ( class_exists( 'WFFN_Feature_Pro' ) ) {
				$this->feature = new WFFN_Feature_Pro();
			}
			if ( class_exists( 'WFFN_Feature_Performance_Pro' ) ) {
				$this->feature_performance = new WFFN_Feature_Performance_Pro();
			}
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
		 * @param array $collectors Existing collectors
		 * @return array
		 */
		public static function add_to_registry( $collectors ) {
			$collectors['funnel-builder'] = array(
				'class'  => __CLASS__,
				'module' => 'pro',
			);
			return $collectors;
		}


		/**
		 * Helper method to log messages to WFFN logger
		 *
		 * @param string $message Log message
		 */
		private function log( $message ) {
			// Log to WFFN logger
			if ( class_exists( 'WFFN_Core' ) ) {
				$logger = WFFN_Core()->logger;
				if ( $logger && method_exists( $logger, 'log' ) ) {
					$logger->log( '[WFFN_Usage_Pro] ' . $message, 'wffn-usage', true );
				}
			}
		}

		/**
		 * Get instance
		 *
		 * @return WFFN_Usage_Collector_Pro|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Collect usage data (includes Lite + Pro data)
		 *
		 * @return array Usage data
		 */
		public function collect() {
			// Pro always collects (no opt-in check needed)
			try {

				// Collect Lite data first - only load classes if not already loaded
				$lite_installation = array();
				$lite_feature      = array();
				$lite_performance  = array();

				// Try to load Lite classes only if they don't exist (minimal loading)
				// class_exists() will trigger autoload if available, otherwise we require manually
				$lite_usage_dir = WP_PLUGIN_DIR . '/funnel-builder/includes/usage';

				if ( ! class_exists( 'WFFN_Installation_Config' ) ) {
					$file = $lite_usage_dir . '/class-wffn-installation-config.php';
					if ( file_exists( $file ) ) {
						require_once $file;
					}
				}
				if ( class_exists( 'WFFN_Installation_Config' ) ) {
					$lite_installation_config = new WFFN_Installation_Config();
					$lite_installation       = $lite_installation_config->collect();
				}

				if ( ! class_exists( 'WFFN_Feature' ) ) {
					$file = $lite_usage_dir . '/class-wffn-feature.php';
					if ( file_exists( $file ) ) {
						require_once $file;
					}
				}
				if ( class_exists( 'WFFN_Feature' ) ) {
					$lite_feature_adoption = new WFFN_Feature();
					$lite_feature          = $lite_feature_adoption->collect();
				}

				if ( ! class_exists( 'WFFN_Feature_Performance' ) ) {
					$file = $lite_usage_dir . '/class-wffn-feature-performance.php';
					if ( file_exists( $file ) ) {
						require_once $file;
					}
				}
				if ( class_exists( 'WFFN_Feature_Performance' ) ) {
					$lite_performance_metrics = new WFFN_Feature_Performance();
					$lite_performance         = $lite_performance_metrics->collect();
				}

				// Note: WooCommerce advanced data is handled separately in core
				// and merged into core 'woocommerce' key, not collected here
				// This prevents redundancy: woocommerce at root AND funnel-builder.woocommerce

				// Collect Pro-specific data (only if initialized)
				$pro_installation = ( $this->installation_config instanceof WFFN_Installation_Config_Pro ) ? $this->installation_config->collect() : array();
				$pro_feature      = ( $this->feature instanceof WFFN_Feature_Pro ) ? $this->feature->collect() : array();
				$pro_performance  = ( $this->feature_performance instanceof WFFN_Feature_Performance_Pro ) ? $this->feature_performance->collect() : array();

				// Merge Lite installation config with Pro config
				$merged_installation = array_merge( $lite_installation, $pro_installation );

				// Merge Lite feature adoption with Pro feature adoption
				$merged_feature = array_merge( $lite_feature, $pro_feature );

				// Merge Lite performance metrics with Pro performance metrics
				$merged_performance = array_merge( $lite_performance, $pro_performance );

				// Build usage data structure
				// Note: Version is not included here as it's already tracked in active_plugins array
				$data = array(
					'module'              => $this->module,
					'installation_config' => $merged_installation,
					'feature'             => $merged_feature,
					'feature_performance' => $merged_performance,
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
		 * Override Lite's cutoff date check for WooCommerce usage data collection
		 * Pro always collects WooCommerce data, regardless of Lite's cutoff date
		 * This ensures rich WooCommerce tracking data is available when Pro is active
		 *
		 * @param bool $should_collect Whether to collect plugin usage data
		 * @return bool Always returns true when Pro is active
		 */
		public function override_woocommerce_collection_check( $should_collect ) {
			// Pro always collects WooCommerce usage data (no cutoff date restrictions)
			return true;
		}

		/**
		 * Override should_collect - Pro always collects
		 *
		 * @return bool
		 */
		public function should_collect() {
			// Pro always collects (no opt-in check)
			return true;
		}

		/**
		 * Setup data - Collects data and saves to options table
		 * Called by individual tracker schedule
		 *
		 * @return bool True on success, false on failure
		 */
		public function setup_data() {
			// Check if should collect (Pro always collects, but check for consistency)
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

