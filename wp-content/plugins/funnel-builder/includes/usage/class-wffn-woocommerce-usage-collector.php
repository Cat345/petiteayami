<?php
/**
 * WooCommerce Usage Tracker Class
 *
 * Collects advanced WooCommerce usage data asynchronously
 * Extends WooFunnels_Usage_Collector_Abstract for async scheduling
 *
 * @package FunnelKit Funnel Builder
 * @since 3.13.1.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_WooCommerce_Usage_Collector' ) && class_exists( 'WooFunnels_Usage_Collector_Abstract' ) ) {

	/**
	 * Class WFFN_WooCommerce_Usage_Collector
	 */
	class WFFN_WooCommerce_Usage_Collector extends WooFunnels_Usage_Collector_Abstract {

		/**
		 * Plugin identifier
		 *
		 * @var string
		 */
		protected $plugin_id = 'woocommerce';

		/**
		 * Module type (WooCommerce is considered 'lite' module)
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
		 * Singleton instance
		 *
		 * @var null
		 */
		private static $ins = null;

		/**
		 * WFFN_WooCommerce_Usage_Collector constructor.
		 */
		public function __construct() {
			// Call parent constructor
			parent::__construct();
		}

		/**
		 * Get singleton instance
		 *
		 * @return WFFN_WooCommerce_Usage_Collector|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Add this collector to the registry filter
		 * Only registers if WooCommerce is active
		 *
		 * @param array $collectors Existing collectors
		 * @return array
		 */
		public static function add_to_registry( $collectors ) {
			// Only register if WooCommerce is active
			if ( class_exists( 'WooCommerce' ) ) {
				$collectors['woocommerce'] = array(
					'class'  => __CLASS__,
					'module' => 'lite',
				);
			}

			return $collectors;
		}

			/**
			 * Check if collector should collect data
			 * Overrides abstract method to include activation date cutoff check
			 *
			 * @return bool
			 */
		public function should_collect() {
			// Only collect if WooCommerce is active
			if ( ! class_exists( 'WooCommerce' ) ) {
				return false;
			}

			// First check opt-in status (from parent)
			if ( ! parent::should_collect() ) {
				return false;
			}

			// Then check activation date cutoff before collecting plugin-specific usage data
			// WooCommerce advanced data collection is tied to Funnel Builder's activation date cutoff
			// Basic/Pro can override this via 'woofunnels_should_collect_plugin_usage_data' filter
			if ( class_exists( 'WFFN_Usage_Collector' ) ) {
				return WFFN_Usage_Collector::should_collect_plugin_usage_data();
			}
			return false;
		}

		/**
		 * Collect WooCommerce advanced usage data
		 *
		 * @return array
		 */
		public function collect() {
			$data = array(
				'orders'          => array(),
				'settings'        => array(),
				'cart_checkout'   => array(),
				'mini_cart_block' => array(),
			);

			// Check if we should collect data (includes WooCommerce check, opt-in, and activation date cutoff)
			if ( ! $this->should_collect() ) {
				return $data;
			}

			try {
				// Collect orders data (non-revenue metrics only)
				$data['orders'] = $this->get_orders_data();

				// Collect WooCommerce settings
				$data['settings'] = $this->get_woocommerce_settings();

				// Collect cart & checkout info
				$data['cart_checkout'] = $this->get_cart_checkout_data();

				// Collect mini cart block info
				$data['mini_cart_block'] = $this->get_mini_cart_block_data();
			} catch ( Throwable $e ) {
				// If collection fails, return empty structure
				// Error is silently caught to prevent breaking usage collection
			}

			return $data;
		}

		/**
		 * Setup data - Collects data and saves to options table
		 * Called by individual tracker schedule
		 *
		 * @return bool True on success, false on failure
		 */
		public function setup_data() {
			// Check if we should collect data (includes WooCommerce check, opt-in, and activation date cutoff)
			if ( ! $this->should_collect() ) {
				return false;
			}

			$data = $this->collect();
			if ( ! empty( $data ) ) {
				$option_key = $this->get_option_key();
				update_option(
					$option_key,
					array(
						'timestamp' => time(),
						'data'      => $data,
					),
					false
				);
				return true;
			}
			return false;
		}

		/**
		 * Return data - Returns saved data from options table
		 * Called by final collector to retrieve saved data
		 *
		 * @return array Saved data or empty array if not found
		 */
		public function return_data() {
			$option_key = $this->get_option_key();
			$saved_data = get_option( $option_key, array() );
			if ( isset( $saved_data['data'] ) && is_array( $saved_data['data'] ) ) {
				return $saved_data['data'];
			}
			return array();
		}

		/**
		 * Get option key - Returns the option key name for this tracker's data
		 *
		 * @return string Option key name
		 */
		public function get_option_key() {
			return 'fk_usage_data_' . $this->plugin_id;
		}

		/**
		 * Get orders data using WooCommerce Tracker logic
		 * Returns only non-revenue metrics (dates, gateway counts, origins) - excludes revenue/totals and status counts
		 *
		 * @return array
		 */
		private function get_orders_data() {
			$order_dates    = $this->get_order_dates();
			$order_gateways = $this->get_orders_by_gateway_counts_only();
			$order_origin   = $this->get_orders_origins();

			// Merge all non-revenue data (excluding status counts)
			return array_merge( $order_dates, $order_gateways, $order_origin );
		}

		/**
		 * Get order dates (replicates WC_Tracker::get_order_dates logic)
		 *
		 * @return array
		 */
		private function get_order_dates() {
			global $wpdb;

			if ( ! class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) || ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				return array(
					'first'            => '-',
					'last'             => '-',
					'processing_first' => '-',
					'processing_last'  => '-',
				);
			}

			$orders_table    = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
			$is_hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

			if ( $is_hpos_enabled ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$min_max = $wpdb->get_row(
					"
					SELECT
						MIN( date_created_gmt ) as 'first', MAX( date_created_gmt ) as 'last'
					FROM $orders_table
					WHERE status = 'wc-completed';
					",
					ARRAY_A
				);
				// phpcs:enable
			} else {
				$min_max = $wpdb->get_row(
					"
						SELECT
							MIN( post_date_gmt ) as 'first', MAX( post_date_gmt ) as 'last'
						FROM {$wpdb->prefix}posts
						WHERE post_type = 'shop_order'
						AND post_status = 'wc-completed'
					",
					ARRAY_A
				);
			}

			// Check if query returned NULL row or if values are NULL/empty (no completed orders)
			if ( is_null( $min_max ) ||
				! is_array( $min_max ) ||
				! isset( $min_max['first'] ) ||
				empty( $min_max['first'] ) ||
				! isset( $min_max['last'] ) ||
				empty( $min_max['last'] ) ) {
				$min_max = array(
					'first' => '-',
					'last'  => '-',
				);
			} else {
				// Ensure values are strings (handle any type conversion issues)
				$min_max['first'] = (string) $min_max['first'];
				$min_max['last']  = (string) $min_max['last'];
			}

			if ( $is_hpos_enabled ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$processing_min_max = $wpdb->get_row(
					"
					SELECT
						MIN( date_created_gmt ) as 'processing_first', MAX( date_created_gmt ) as 'processing_last'
					FROM $orders_table
					WHERE status = 'wc-processing';
					",
					ARRAY_A
				);
				// phpcs:enable
			} else {
				$processing_min_max = $wpdb->get_row(
					"
						SELECT
							MIN( post_date_gmt ) as 'processing_first', MAX( post_date_gmt ) as 'processing_last'
						FROM {$wpdb->prefix}posts
						WHERE post_type = 'shop_order'
						AND post_status = 'wc-processing'
				",
					ARRAY_A
				);
			}

			// Check if query returned NULL row or if values are NULL/empty (no processing orders)
			if ( is_null( $processing_min_max ) ||
				! is_array( $processing_min_max ) ||
				! isset( $processing_min_max['processing_first'] ) ||
				empty( $processing_min_max['processing_first'] ) ||
				! isset( $processing_min_max['processing_last'] ) ||
				empty( $processing_min_max['processing_last'] ) ) {
				$processing_min_max = array(
					'processing_first' => '-',
					'processing_last'  => '-',
				);
			} else {
				// Ensure values are strings (handle any type conversion issues)
				$processing_min_max['processing_first'] = (string) $processing_min_max['processing_first'];
				$processing_min_max['processing_last']  = (string) $processing_min_max['processing_last'];
			}

			return array_merge( $min_max, $processing_min_max );
		}

		/**
		 * Get orders by gateway - counts only (excludes totals/revenue)
		 * Replicates WC_Tracker::get_orders_by_gateway but only returns counts
		 *
		 * @return array
		 */
		private function get_orders_by_gateway_counts_only() {
			global $wpdb;

			if ( ! class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) || ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				return array();
			}

			$is_hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

			if ( $is_hpos_enabled ) {
				$orders_table = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$orders_and_gateway_details = $wpdb->get_results(
					"
					SELECT IFNULL(payment_method, '') AS gateway, currency AS currency, count( id ) AS counts
					FROM $orders_table
					WHERE status IN ( 'wc-completed', 'wc-processing', 'wc-refunded' )
					GROUP BY gateway, currency;
					"
				);
				// phpcs:enable
			} else {
				$orders_and_gateway_details = $wpdb->get_results(
					"
					SELECT
						gateway, currency, COUNT(order_id) AS counts
					FROM (
						SELECT
							orders.id AS order_id,
							IFNULL(MAX(CASE WHEN meta_key = '_payment_method' THEN meta_value END), '') gateway,
							MAX(CASE WHEN meta_key = '_order_currency' THEN meta_value END) currency
						FROM
							{$wpdb->prefix}posts orders
						LEFT JOIN
							{$wpdb->prefix}postmeta order_meta ON order_meta.post_id = orders.id
						WHERE orders.post_type = 'shop_order'
							AND orders.post_status in ( 'wc-completed', 'wc-processing', 'wc-refunded' )
							AND meta_key in( '_payment_method','_order_currency')
						GROUP BY orders.id
					) order_gateways
					GROUP BY gateway, currency
					"
				);
			}

			$orders_by_gateway_currency = array();

			// Use same grouping logic as WC_Tracker
			$orders_by_gateway = $this->extract_group_key_wc_style(
				array_reduce(
					$orders_and_gateway_details,
					function ( $result, $item ) {
						$item->gateway  = preg_replace( '/\s+/', ' ', $item->gateway ?? '' );
						$key            = $item->currency . '==' . $item->gateway;
						$result[ $key ] = $item;
						return $result;
					},
					array()
				),
				'gateway'
			);

			// Aggregate using group_key - only counts, no totals
			foreach ( $orders_by_gateway as $orders_details ) {
				$gkey = $orders_details->group_key;

				// Remove currency as prefix of key for backward compatibility
				if ( str_contains( $gkey, '==' ) ) {
					$tokens = preg_split( '/==/', $gkey );
					$key    = $tokens[1];
				} else {
					$key = $gkey;
				}

				$key = str_replace( array( 'payment method', 'payment gateway', 'gateway' ), '', strtolower( $key ) );
				$key = trim( preg_replace( '/[: ,#*\-_]+/', ' ', $key ) );

				// Add currency as postfix of gateway for backward compatibility
				$key       = 'gateway_' . $key . '_' . $orders_details->currency;
				$count_key = $key . '_count';

				if ( array_key_exists( $count_key, $orders_by_gateway_currency ) ) {
					$orders_by_gateway_currency[ $count_key ] = $orders_by_gateway_currency[ $count_key ] + $orders_details->counts;
				} else {
					$orders_by_gateway_currency[ $count_key ] = $orders_details->counts;
				}
			}

			return $orders_by_gateway_currency;
		}

		/**
		 * Get orders origins (replicates WC_Tracker::get_orders_origins logic)
		 *
		 * @return array
		 */
		private function get_orders_origins() {
			global $wpdb;

			if ( ! class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) || ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				return array( 'created_via' => array() );
			}

			$is_hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

			if ( $is_hpos_enabled ) {
				$op_table_name = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_operational_data_table_name();
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$orders_origin = $wpdb->get_results(
					"
					SELECT IFNULL(created_via, '') as origin, COUNT( order_id ) as count
					FROM $op_table_name
					GROUP BY origin;
					"
				);
				// phpcs:enable
			} else {
				$orders_origin = $wpdb->get_results(
					"
					SELECT
						IFNULL(meta_value, '') as origin, COUNT( DISTINCT ( orders.id ) ) as count
					FROM
						$wpdb->posts orders
					LEFT JOIN
						$wpdb->postmeta order_meta ON order_meta.post_id = orders.id
					WHERE
						meta_key = '_created_via'
					GROUP BY
						origin;
				"
				);
			}

			// Use same grouping logic as WC_Tracker
			$orders_and_origins = $this->extract_group_key_wc_style(
				array_reduce(
					$orders_origin,
					function ( $result, $item ) {
						$key            = $item->origin;
						$result[ $key ] = $item;
						return $result;
					},
					array()
				),
				'origin'
			);

			$orders_by_origin = array();

			// Aggregate using group_key
			foreach ( $orders_and_origins as $origin ) {
				$key = strtolower( $origin->group_key ?? '' );

				if ( array_key_exists( $key, $orders_by_origin ) ) {
					$orders_by_origin[ $key ] = $orders_by_origin[ $key ] + (int) $origin->count;
				} else {
					$orders_by_origin[ $key ] = (int) $origin->count;
				}
			}

			return array( 'created_via' => $orders_by_origin );
		}

		/**
		 * Extract group key (replicates WC_Tracker::extract_group_key logic)
		 *
		 * @param array  $objects     The array of objects that need to be grouped
		 * @param string $default_key The property that will be the default group_key
		 * @return array Contains the objects with a group_key property
		 */
		private function extract_group_key_wc_style( $objects, $default_key ) {
			$keys = array_keys( $objects );

			// Sort keys by length and then by characters within the same length keys
			usort(
				$keys,
				function ( $a, $b ) {
					if ( strlen( $a ) === strlen( $b ) ) {
						return strcmp( $a, $b );
					}
					return ( strlen( $a ) < strlen( $b ) ) ? -1 : 1;
				}
			);

			// Look for common tokens in every pair of adjacent keys
			$prev = '';
			foreach ( $keys as $key ) {
				if ( $prev ) {
					$comm_tokens = array();

					// Tokenize the current and previous gateway names
					$curr_tokens = preg_split( '/[ :,\-_]+/', $key );
					$prev_tokens = preg_split( '/[ :,\-_]+/', $prev );

					$len_curr = is_array( $curr_tokens ) ? count( $curr_tokens ) : 0;
					$len_prev = is_array( $prev_tokens ) ? count( $prev_tokens ) : 0;

					$index_unique = -1;
					// Gather the common tokens
					for ( $i = 0; $i < $len_curr && $i < $len_prev; $i++ ) {
						if ( $curr_tokens[ $i ] === $prev_tokens[ $i ] ) {
							$comm_tokens[] = $curr_tokens[ $i ];
						} elseif ( preg_match( '/\d/', $curr_tokens[ $i ] ) && preg_match( '/\d/', $prev_tokens[ $i ] ) ) {
							$index_unique = $i;
						}
					}

					// If only one token is different, and those tokens contain digits, then that could be the unique id
					if ( $len_curr - count( $comm_tokens ) <= 1 && count( $comm_tokens ) > 0 && $index_unique > -1 ) {
						$objects[ $key ]->group_key  = implode( ' ', $comm_tokens );
						$objects[ $prev ]->group_key = implode( ' ', $comm_tokens );
					} else {
						$objects[ $key ]->group_key = $objects[ $key ]->$default_key;
					}
				} else {
					$objects[ $key ]->group_key = $objects[ $key ]->$default_key;
				}
				$prev = $key;
			}
			return $objects;
		}

		/**
		 * Get WooCommerce settings
		 * Gets settings directly since WC_Tracker method is private
		 *
		 * @return array
		 */
		private function get_woocommerce_settings() {
			// Get settings directly (WC_Tracker::get_all_woocommerce_options_values() is private)
			$settings = array(
				'coupons_enabled'                       => get_option( 'woocommerce_enable_coupons', 'no' ),
				'guest_checkout'                        => get_option( 'woocommerce_enable_guest_checkout', 'no' ),
				'delayed_account_creation'              => get_option( 'woocommerce_enable_delayed_account_creation', 'no' ),
				'checkout_login_reminder'               => get_option( 'woocommerce_enable_checkout_login_reminder', 'no' ),
				'secure_checkout'                       => get_option( 'woocommerce_force_ssl_checkout', 'no' ),
				'enable_signup_and_login_from_checkout' => get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ),
				'enable_myaccount_registration'         => get_option( 'woocommerce_enable_myaccount_registration', 'no' ),
				'registration_generate_username'        => get_option( 'woocommerce_registration_generate_username', 'no' ),
				'registration_generate_password'        => get_option( 'woocommerce_registration_generate_password', 'no' ),
			);

			return $settings;
		}

		/**
		 * Get cart & checkout info
		 * Uses WooCommerce Tracker methods where available
		 *
		 * @return array
		 */
		private function get_cart_checkout_data() {
			// Use WooCommerce Tracker method if available
			if ( class_exists( 'WC_Tracker' ) && method_exists( 'WC_Tracker', 'get_cart_checkout_info' ) ) {
				return WC_Tracker::get_cart_checkout_info();
			}

			// Fallback: Return empty structure
			return array(
				'cart_page_contains_cart_shortcode'     => 'No',
				'checkout_page_contains_checkout_shortcode' => 'No',
				'cart_page_contains_cart_block'         => 'No',
				'cart_block_attributes'                 => array(),
				'checkout_page_contains_checkout_block' => 'No',
				'checkout_block_attributes'             => array(),
			);
		}

		/**
		 * Get mini cart block info
		 * Uses WooCommerce Tracker methods where available
		 *
		 * @return array
		 */
		private function get_mini_cart_block_data() {
			// Only available in WordPress 5.9+
			if ( version_compare( get_bloginfo( 'version' ), '5.9', '<' ) ) {
				return array(
					'mini_cart_used'             => 'No',
					'mini_cart_block_attributes' => array(),
				);
			}

			// Use WooCommerce Tracker method if available
			if ( class_exists( 'WC_Tracker' ) && method_exists( 'WC_Tracker', 'get_mini_cart_info' ) ) {
				// Method is private, so we need to use reflection or duplicate logic
				// Since it's private, we'll implement our own version
				return $this->get_mini_cart_info();
			}

			// Fallback
			return array(
				'mini_cart_used'             => 'No',
				'mini_cart_block_attributes' => array(),
			);
		}

		/**
		 * Get mini cart info (duplicated from WC_Tracker since it's private)
		 *
		 * @return array
		 */
		private function get_mini_cart_info() {
			$mini_cart_block_name = 'woocommerce/mini-cart';

			// Check if BlocksUtil is available (WooCommerce 8.0+)
			if ( class_exists( '\Automattic\WooCommerce\Internal\Utilities\BlocksUtil' ) ) {
				$mini_cart_block_data = wp_is_block_theme()
					? \Automattic\WooCommerce\Internal\Utilities\BlocksUtil::get_block_from_template_part( $mini_cart_block_name, 'header' )
					: \Automattic\WooCommerce\Internal\Utilities\BlocksUtil::get_blocks_from_widget_area( $mini_cart_block_name );

				return array(
					'mini_cart_used'             => empty( $mini_cart_block_data[0] ) ? 'No' : 'Yes',
					'mini_cart_block_attributes' => empty( $mini_cart_block_data[0] ) ? array() : $mini_cart_block_data[0]['attrs'],
				);
			}

			// Fallback for older WooCommerce versions
			return array(
				'mini_cart_used'             => 'No',
				'mini_cart_block_attributes' => array(),
			);
		}
	}

	// Register collector with registry via filter hook
	// This ensures the collector is discovered by WooFunnels_Usage_Registry
	add_filter( 'woofunnels_register_usage_collectors', array( 'WFFN_WooCommerce_Usage_Collector', 'add_to_registry' ), 15, 1 );
}
