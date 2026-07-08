<?php
/**
 * WooCommerce Advanced Usage Data Collector (Pro)
 *
 * Collects advanced WooCommerce usage data including:
 * - Orders banner_data (numeric values from order meta)
 * - WooCommerce settings
 * - Cart & Checkout info
 * - Mini Cart block info
 *
 * @package FunnelKit Funnel Builder Pro
 * @since 3.13.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_WooCommerce_Usage_Pro' ) ) {

	/**
	 * Class WFFN_WooCommerce_Usage_Pro
	 */
	#[\AllowDynamicProperties]
	class WFFN_WooCommerce_Usage_Pro {

		/**
		 * Constructor - hooks into filter to add advanced WooCommerce data
		 */
		public function __construct() {

			// Hook into filter to add advanced WooCommerce data to core 'woocommerce' key
			// Pro always collects data (no activation date check needed)
			add_filter( 'woofunnels_advanced_woocommerce_usage_data', array( $this, 'add_advanced_woocommerce_data' ), 10, 2 );
		}

		/**
		 * Filter callback to add advanced WooCommerce data
		 * Pro always collects data (no activation date check needed)
		 *
		 * @param array $advanced_wc_data Existing advanced WC data
		 * @param array $core_data Core usage data
		 * @return array
		 */
		public function add_advanced_woocommerce_data( $advanced_wc_data, $core_data ) {
			// Pro always collects advanced WooCommerce data (no activation date check)
			// Collect and merge advanced WooCommerce data
			$collected_data = $this->collect();

			if ( ! empty( $collected_data ) ) {
				// Merge with any existing advanced WC data
				$advanced_wc_data = array_merge( $advanced_wc_data, $collected_data );
			}

			return $advanced_wc_data;
		}

		/**
		 * Collect WooCommerce advanced usage data
		 * Reuses Lite class logic if available, otherwise implements own collection
		 *
		 * @return array
		 */
		public function collect() {
			// Force require Lite class file if it doesn't exist
			if ( ! class_exists( 'WFFN_WooCommerce_Usage' ) ) {
				$lite_usage_file = defined( 'WFFN_PLUGIN_DIR' ) ? WFFN_PLUGIN_DIR . '/includes/usage/class-wffn-woocommerce-usage.php' : '';
				if ( ! empty( $lite_usage_file ) && file_exists( $lite_usage_file ) ) {
					require_once $lite_usage_file;
					// Ensure Lite class is instantiated to register its filter hook
					if ( class_exists( 'WFFN_WooCommerce_Usage' ) && ! isset( $GLOBALS['wffn_woocommerce_usage_instance'] ) ) {

						$GLOBALS['wffn_woocommerce_usage_instance'] = new WFFN_WooCommerce_Usage();
					}
				}
			}

			// Use existing Lite instance if available (reuse logic)
			if ( class_exists( 'WFFN_WooCommerce_Usage' ) ) {
				// Reuse existing instance if available, otherwise create new one
				$lite_instance = isset( $GLOBALS['wffn_woocommerce_usage_instance'] ) ? $GLOBALS['wffn_woocommerce_usage_instance'] : new WFFN_WooCommerce_Usage();
				return $lite_instance->collect();
			}

			// Fallback: Return empty structure if Lite class not available
			return array(
				'orders'          => array(),
				'settings'        => array(),
				'cart_checkout'   => array(),
				'mini_cart_block' => array(),
			);
		}
	}

	// Auto-instantiate to hook into filter when class is loaded
	// This ensures the filter hook is registered even if class is loaded via autoloader
	// Note: Instantiation happens here to register the filter hook
	// Pro always collects data (no activation date check)
	if ( ! isset( $GLOBALS['wffn_woocommerce_usage_pro_instance'] ) ) {
		$GLOBALS['wffn_woocommerce_usage_pro_instance'] = new WFFN_WooCommerce_Usage_Pro();
	}
}
