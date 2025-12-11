<?php
if ( ! class_exists( 'WFOB_Reporting' ) ) {
	class WFOB_Reporting {

		private static $ins = null;
		private $no_of_bump_used_order = [];
		private $no_of_bump_used_total = [];

		private function __construct() {
			add_action( 'plugins_loaded', [ $this, 'init_db' ], 2 );
			add_action( 'admin_init', [ $this, 'create_table' ] );
			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'woocommerce_create_order_line_item' ], 999999, 4 );
			add_action( 'woocommerce_thankyou', [ $this, 'insert_custom_row_from_meta' ], 99, 2 );

			add_filter( 'woocommerce_admin_reports', [ $this, 'add_report_menu' ] );
			add_filter( 'wc_admin_reports_path', [ $this, 'initialize_bump_reports_path' ], 12, 3 );

			if ( class_exists( 'BWF_WC_Compatibility' ) && BWF_WC_Compatibility::is_hpos_enabled() ) {
				add_action( 'woocommerce_delete_order', [ $this, 'delete_report_for_order' ] );
			} else {
				add_action( 'delete_post', [ $this, 'delete_report_for_order' ] );
			}

			add_action( 'woocommerce_checkout_create_order', [ $this, 'update_used_bump_in_order_meta' ] );

			add_action( 'woocommerce_order_status_changed', array( $this, 'insert_row_for_ipn_based_gateways' ), 10, 3 );

			add_action( 'woocommerce_order_fully_refunded', array( $this, 'fully_refunded_process' ), 10, 1 );
			add_action( 'woocommerce_order_partially_refunded', array( $this, 'partially_refunded_process' ), 8, 2 );

		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function init_db() {
			global $wpdb;
			$wpdb->wfob_stats = $wpdb->prefix . 'wfob_stats';
		}

		public function create_table() {
			/** create table in ver 1.0 */
			if ( false !== get_option( 'wfob_db_ver_3_0', false ) ) {
				return;
			}
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			global $wpdb;
			$collate = '';
			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}

			$creationSQL = "CREATE TABLE {$wpdb->prefix}wfob_stats (
 		  ID bigint(20) unsigned NOT NULL auto_increment,
 		  oid bigint(20) unsigned NOT NULL,
 		  bid bigint(20) unsigned NOT NULL,
 		  iid varchar(255) NOT NULL,
 		  converted tinyint(1) not null default 0,
 		  total varchar(255) not null default 0,
 		  date datetime NOT NULL, 	
 		  cid BIGINT(20) unsigned NOT NULL DEFAULT 0,
 		  fid BIGINT(20) unsigned NOT NULL DEFAULT 0, 
		  PRIMARY KEY  (ID),
		  KEY ID (ID),
		  KEY oid (oid),
		  KEY bid (bid),
		  KEY date (date)
		) $collate;";
			dbDelta( $creationSQL );

			update_option( 'wfob_db_ver_3_0', date( 'Y-m-d' ) );
		}


		/**
		 * @param $item WC_Order_Item
		 * @param $cart_item_key
		 * @param $values
		 * @param $order WC_Order
		 */
		public function woocommerce_create_order_line_item( $item, $cart_item_key, $values, $order ) {
			if ( isset( $values['_wfob_product'] ) ) {
				$bump_id = absint( $values['_wfob_options']['_wfob_id'] );
				$item->add_meta_data( '_bump_purchase', 'yes' );
				$item->add_meta_data( '_wfob_id', $bump_id );
				if ( ! isset( $this->no_of_bump_used_total[ $bump_id ] ) ) {
					$this->no_of_bump_used_total[ $bump_id ] = 0;
				}
				$total                                     = floatval( $item->get_total() ) + floatval( $item->get_total_tax() );
				$this->no_of_bump_used_total[ $bump_id ]   += BWF_Plugin_Compatibilities::get_fixed_currency_price_reverse( $total, BWF_WC_Compatibility::get_order_currency( $order ) );
				$this->no_of_bump_used_order[ $bump_id ][] = [ $values ];
			}
		}

		/**
		 * @param WC_Order $order
		 */
		public function update_used_bump_in_order_meta( $order ) {
			$bump_data = [];

			$show_bumps = WC()->session->get( 'wfob_no_of_bump_shown', [] );
			if ( empty( $show_bumps ) && ! empty( $_POST['wfob_input_bump_shown_ids'] ) ) {
				$show_bumps = explode( ',', $_POST['wfob_input_bump_shown_ids'] );
			}
			$funnel_id = 0;

			foreach ( $show_bumps as $bump_id ) {
				$converted = isset( $this->no_of_bump_used_order[ $bump_id ] ) ? 1 : 0;
				$total     = 0;
				if ( $converted ) {
					$total = $this->no_of_bump_used_total[ $bump_id ];
				}

				$fid = get_post_meta( $bump_id, '_bwf_in_funnel', true );
				if ( $fid > 0 ) {
					$funnel_id = $fid;
				}

				$data                  = [
					'converted' => $converted,
					'bid'       => absint( $bump_id ),
					'total'     => $total,
					'iid'       => '{}',
					'fid'       => $funnel_id,
				];
				$bump_data[ $bump_id ] = $data;
			}
			do_action( 'wfob_used_bump_in_order', $order, $bump_data );
			$order->update_meta_data( '_wfob_report_data', $bump_data );
		}

		/**
		 * hooked @woocommerce_thankyou
		 *
		 * @param $order_id
		 *
		 * @return bool|mixed
		 */
		public function insert_custom_row_from_meta( $order_id ) {
			global $wpdb;
			$order = apply_filters( 'wfob_maybe_update_order', wc_get_order( $order_id ) );

			$order_id = $order->get_id();

			$order_status = $order->get_status();
			/**
			 * @var $order WC_Order;
			 */

			/**
			 * If this is a renewal order then delete the meta if exists and return straight away
			 */
			if ( $this->is_order_renewal( $order ) ) {
				$order->delete_meta_data( '_wfob_report_data' );
				$order->delete_meta_data( '_wfob_report_needs_normalization' );
				$order->save();

				return false;
			}


			add_filter( 'woocommerce_order_is_paid_statuses', [ $this, 'wfob_custom_order_status' ] );

			$payment_method = $order->get_payment_method();
			/**
			 * if woocommerce thank you showed up and order status not paid, save meta to normalize status later
			 */
			if ( did_action( 'woocommerce_thankyou' ) ) {

				if ( in_array( $payment_method, $this->get_ipn_gateways(), true ) || ! in_array( $order_status, wc_get_is_paid_statuses(), true ) ) {
					$order->update_meta_data( '_wfob_report_needs_normalization', 'yes' );
					$order->save();

					return false;
				}
			}
			$bump_data = WFOB_Common::get_order_meta( $order, '_wfob_report_data' );
			if ( ! is_array( $bump_data ) ) {
				return $bump_data;
			}

			$sql     = "select item.order_item_id as item_id ,meta.meta_value as bump_id from {$wpdb->prefix}woocommerce_order_items as item INNER JOIN  {$wpdb->prefix}woocommerce_order_itemmeta as meta on item.order_item_id=meta.order_item_id and item.order_id='{$order_id}' and meta.meta_key='_wfob_id';";
			$results = $wpdb->get_results( $sql, ARRAY_A );

			$bump_items = [];
			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {
					$bump_id                  = absint( $result['bump_id'] );
					$item_id                  = $result['item_id'];
					$bump_items[ $bump_id ][] = absint( $item_id );
				}
			}
			$get_cid      = WFOB_Common::get_order_meta( $order, '_woofunnel_cid' );
			$get_cid      = empty( $get_cid ) ? 0 : $get_cid;
			$date_created = $order->get_date_created();
			if ( ! empty( $date_created ) ) {

				$timezone = new DateTimeZone( wp_timezone_string() );
				$date_created->setTimezone( $timezone );
				$date_created = $date_created->format( 'Y-m-d H:i:s' );
			}

			$bump_total = 0;
			foreach ( $bump_data as $id => $insert_data ) {
				if ( isset( $bump_items[ $id ] ) ) {
					$insert_data['iid'] = json_encode( $bump_items[ $id ] );
				}
				$insert_data['cid']  = $get_cid;
				$insert_data['fid']  = isset( $insert_data['fid'] ) ? $insert_data['fid'] : 0;
				$insert_data['oid']  = $order_id;
				$insert_data['date'] = empty( $date_created ) ? current_time( 'mysql' ) : $date_created;
				$report_id           = $this->insert_data( $insert_data );

				if ( ! is_null( $report_id ) && isset( $insert_data['converted'] ) && 1 === intval( $insert_data['converted'] ) ) {
					$bump_total += isset( $insert_data['total'] ) ? floatval( $insert_data['total'] ) : 0;
				}
			}

			$order->update_meta_data( '_bump_purchase_item_total', $bump_total );
			$order->delete_meta_data( '_wfob_report_data' );
			$order->delete_meta_data( '_wfob_report_needs_normalization' );
			$order->save();
			remove_filter( 'woocommerce_order_is_paid_statuses', [ $this, 'wfob_custom_order_status' ] );

			if ( ! is_null( WC()->session ) ) {
				WC()->session->set( 'license_expired_bump_rejected', '' );
			}
		}

		public function delete_report_for_order( $order_id ) {
			if ( empty( $order_id ) || absint( 0 === $order_id ) ) {
				return;
			}
			if ( 0 < did_action( 'delete_post' ) ) {
				$get_post_type = get_post_type( $order_id );
				if ( 'shop_order' !== $get_post_type ) {
					return;
				}
			}
			global $wpdb;
			$wpdb->delete( $wpdb->wfob_stats, [ 'oid' => $order_id ], [ '%d' ] );
		}

		private function insert_data( $data ) {
			global $wpdb;
			/* //phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			 'converted' => 1,
			  'bid' => XXXXX,
			  'total' => XX.XX,
			  'iid' => '{}',
			  'fid' => 'XX',
			  'cid' => 'XX',
			  'oid' => 'XX',
			  'date' => 'XX',
				 */
			$status = $wpdb->insert( $wpdb->wfob_stats, $data, [ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ] );
			if ( false !== $status ) {
				return $wpdb->insert_id;
			}

			return null;
		}

		private function update_data( $data, $where ) {
			global $wpdb;
			$status = $wpdb->update( $wpdb->wfob_stats, $data, $where, [ '%s' ], [ '%d', '%d' ] );
			if ( false !== $status ) {
				return true;
			}

			return null;
		}

		public function add_report_menu( $menu ) {
			$menu['wfob_bump'] = array(
				'title'   => __( 'Order Bumps', 'woofunnels-order-bump' ),
				'reports' => array(
					'wfob_by_date' => array(
						'title'       => __( 'Sales By Date', 'woofunnels-order-bump' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
					),

					'wfob_bumps' => array(
						'title'       => __( 'Sales By Bump', 'woofunnels-order-bump' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => array( __CLASS__, 'get_report' ),
					),
				),
			);

			return $menu;
		}

		public function initialize_bump_reports_path( $reporting_path, $name, $class ) {
			if ( in_array( strtolower( $class ), [ 'wc_report_wfob_bumps', 'wc_report_wfob_by_date' ], true ) ) {
				$reporting_path = dirname( __FILE__ ) . '/reports/class-' . $name . '.php';
			}

			return $reporting_path;
		}

		public static function get_report() {
			include_once __DIR__ . '/reports/class-wfob-bumps.php';
			WC_Report_wfob_bumps::get_report();
		}

		/**
		 * hooked @ 'woocommerce_order_status_changed'
		 *
		 * @param $order_id
		 * @param $from
		 * @param $to
		 */
		public function insert_row_for_ipn_based_gateways( $order_id, $from, $to ) {

			if ( in_array( $from, wc_get_is_paid_statuses(), true ) ) {
				return false;
			}

			$order          = wc_get_order( $order_id );
			$payment_method = $order->get_payment_method();

			$ipn_gateways = $this->get_ipn_gateways();

			/**
			 * condition1 : if one of IPN gateways
			 * condition2: Thankyou page hook with pending status ran on this order
			 * condition3: In case thankyou page not open and order mark complete by IPN
			 */
			if ( in_array( $payment_method, $ipn_gateways, true ) || 'yes' === $order->get_meta( '_wfob_report_needs_normalization' ) || ( class_exists( 'WC_Geolocation' ) && ( $order->get_customer_ip_address() !== WC_Geolocation::get_ip_address() ) ) ) {
				/**
				 * reaching this code means, 1) we have a ipn gateway OR 2) we have meta stored during thankyou
				 */
				add_filter( 'woocommerce_order_is_paid_statuses', [ $this, 'wfob_custom_order_status' ] );
				if ( $order_id > 0 && in_array( $to, wc_get_is_paid_statuses(), true ) ) {
					$this->insert_custom_row_from_meta( $order_id );
				}
			}

		}

		public function is_order_renewal( $order ) {
			if ( is_numeric( $order ) ) {
				$order = wc_get_order( $order );
			}
			$subscription_renewal = BWF_WC_Compatibility::get_order_data( $order, '_subscription_renewal' );

			return ! empty( $subscription_renewal );
		}

		public function wfob_custom_order_status( $all_status ) {
			if ( is_array( $all_status ) ) {
				$all_status = apply_filters( 'wfob_analytics_custom_order_status', $all_status );
			}

			return $all_status;
		}

		/**
		 * @param $order_id
		 *
		 * Full refunded process for analytics
		 */
		public function fully_refunded_process( $order_id ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . "wfob_stats", [ 'total' => 0 ], [ 'oid' => $order_id ] );
		}

		/**
		 * @param $order_id
		 * @param $refund_id
		 * Partially refunded process for analytics
		 */
		public function partially_refunded_process( $order_id, $refund_id ) {
			global $wpdb;
			$order         = wc_get_order( $order_id );
			$refund        = wc_get_order( $refund_id );
			$refund_amount = 0;

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( ! $refund instanceof WC_Order_Refund ) {
				return;
			}

			if ( 0 < count( $refund->get_items() ) ) {
				foreach ( $refund->get_items() as $refund_item ) {
					$item_id        = $refund_item->get_meta( '_refunded_item_id', true );
					$item           = $order->get_item( $item_id );
					$_bump_purchase = $item->get_meta( '_bump_purchase' );
					if ( '' !== $_bump_purchase ) {
						$refund_amount += abs( $refund_item->get_total() );
					}

				}
			}

			if ( $refund_amount > 0 ) {
				$get_total     = $wpdb->get_var( "SELECT total FROM " . $wpdb->prefix . "wfob_stats WHERE oid = " . $order_id ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared*/
				$refund_amount = ( $get_total <= $refund_amount ) ? 0 : $get_total - $refund_amount;

				$wpdb->update( $wpdb->prefix . "wfob_stats", [ 'total' => $refund_amount ], [ 'oid' => $order_id ] );
			}
		}

		public function get_ipn_gateways() {
			$ipn_gateways = array(
				'paypal',
				'mollie_wc_gateway_ideal',
				'mollie_wc_gateway_bancontact',
				'mollie_wc_gateway_sofort',
				'infusionsoft_cc',
				'valitor',
				'payplus-payment-gateway',
				'bayarcash',
				'duitnownets',
				'directdebit',
				'duitnowboost',
				'duitnow',
				'duitnowshopee',
				'linecredit',
				'duitnowqriswallet',
				'duitnowqr',
				'duitnowqris'
			);

			return apply_filters( 'wfob_ipn_gateways_list', $ipn_gateways );
		}
	}

	WFOB_Reporting::get_instance();
}