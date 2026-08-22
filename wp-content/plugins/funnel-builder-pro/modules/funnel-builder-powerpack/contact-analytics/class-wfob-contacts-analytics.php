<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFOB_Contacts_Analytics
 */
if ( ! class_exists( 'WFOB_Contacts_Analytics' ) ) {

	#[\AllowDynamicProperties]
	class WFOB_Contacts_Analytics extends WFFN_REST_Controller {

		/**
		 * instance of class
		 *
		 * @var null
		 */
		private static $ins = null;

		/**
		 * WFOB_Contacts_Analytics constructor.
		 */
		public function __construct() {
		}

		/**
		 * @return WFOB_Contacts_Analytics|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * @param $start_date
		 * @param $end_date
		 * @param string $limit
		 *
		 * @return array
		 */
		public function get_top_bumps( $start_date, $end_date, $limit_str = '' ) {
			global $wpdb;
			if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
				$data = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT v.bid as id, IFNULL(s.revenue, 0) as revenue, IFNULL(s.`conversion`, 0) as `conversion`, p.post_title as title, p.post_type as post_type, v.view_count as views FROM (SELECT bid, COUNT(id) as view_count FROM `{$wpdb->prefix}wfob_stats` WHERE date >= %s AND date < %s GROUP BY bid) as v LEFT JOIN {$wpdb->prefix}posts as p ON p.id = v.bid LEFT JOIN (SELECT bid, sum(total) as revenue, COUNT(id) as `conversion` FROM `{$wpdb->prefix}wfob_stats` as bmp WHERE date >= %s AND date < %s AND bmp.converted = 1 GROUP BY bid) as s ON s.bid = v.bid ORDER BY revenue DESC",
						$start_date,
						$end_date,
						$start_date,
						$end_date
					)
				);
			} else {
				$data = $wpdb->get_results(
					"SELECT v.bid as id, IFNULL(s.revenue, 0) as revenue, IFNULL(s.`conversion`, 0) as `conversion`, p.post_title as title, p.post_type as post_type, v.view_count as views FROM (SELECT bid, COUNT(id) as view_count FROM `{$wpdb->prefix}wfob_stats` GROUP BY bid) as v LEFT JOIN {$wpdb->prefix}posts as p ON p.id = v.bid LEFT JOIN (SELECT bid, sum(total) as revenue, COUNT(id) as `conversion` FROM `{$wpdb->prefix}wfob_stats` as bmp WHERE bmp.converted = 1 GROUP BY bid) as s ON s.bid = v.bid ORDER BY revenue DESC"
				);
			}
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}
			if ( ! empty( $data ) && is_array( $data ) ) {
				foreach ( $data as $key => $item ) {
					$data[ $key ]->conversion_rate = $this->get_percentage( absint( $item->views ), $item->conversion );
					$data[ $key ]->type            = $item->post_type;
					$funnel_id                     = get_post_meta( $item->id, '_bwf_in_funnel', true );
					$data[ $key ]->fid             = $funnel_id;
					if ( $funnel_id ) {
						$funnel_name               = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT title FROM {$wpdb->prefix}bwf_funnels WHERE id = %d",
								$funnel_id
							)
						);
						$data[ $key ]->funnel_name = $funnel_name;
					} else {
						$data[ $key ]->funnel_name = '';
					}
				}
			}
			return $data;
		}

		/**
		 * @param $funnel_id
		 * @param $cid
		 *
		 * @return array|object|null
		 */
		public function get_all_contacts_records( $funnel_id, $cid ) {
			global $wpdb;
			$item_data  = array();
			$funnel_id  = ! empty( $funnel_id ) ? absint( $funnel_id ) : $funnel_id;
			$cid        = ! empty( $cid ) ? absint( $cid ) : $cid;
			$order_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT bump.oid as order_id, bump.bid as 'object_id', bump.iid as 'item_ids', bump.total as 'total_revenue', p.post_title as 'object_name', bump.converted as 'is_converted', DATE_FORMAT(bump.date, '%%Y-%%m-%%dT%%TZ') as 'date', 'bump' as 'type' FROM {$wpdb->prefix}wfob_stats AS bump LEFT JOIN {$wpdb->prefix}posts as p ON bump.bid = p.id WHERE bump.converted = 1 AND bump.fid=%d AND bump.cid=%d order by bump.date asc",
					$funnel_id,
					$cid
				)
			);
			$db_error   = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			if ( ! is_array( $order_data ) || count( $order_data ) === 0 ) {
				return $item_data;
			}

			$all_item_ids = array();

			/** merge all items ids in one array */
			if ( is_array( $order_data ) && count( $order_data ) > 0 ) {
				foreach ( $order_data as &$i_array ) {
					$i_array->item_ids = ( isset( $i_array->item_ids ) && '' != $i_array->item_ids ) ? json_decode( $i_array->item_ids ) : array();
					if ( is_array( $i_array->item_ids ) && count( $i_array->item_ids ) > 0 ) {
						$all_item_ids = array_merge( $all_item_ids, $i_array->item_ids );
					}
				}
			}

			if ( is_array( $all_item_ids ) && count( $all_item_ids ) > 0 ) {
				/**
				 * get order item product name and quantity by items ids
				 */
				$item_ids  = array_map( 'absint', $all_item_ids );
				$item_data = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT oi.order_item_id as 'item_id', oi.order_item_name as 'product_name', oim.meta_value as 'qty' FROM {$wpdb->prefix}woocommerce_order_items as oi LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as oim ON oi.order_item_id = oim.order_item_id WHERE oi.order_item_id IN ( " . implode( ', ', array_fill( 0, count( $item_ids ), '%d' ) ) . " ) AND oi.order_item_type = 'line_item' AND oim.meta_key = '_qty' GROUP BY oi.order_item_id",
						$item_ids
					)
				);
				$db_error  = WFFN_Common::maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}
			}

			foreach ( $order_data as &$order ) {
				$product_titles = array();
				$qty            = 0;
				if ( is_array( $order->item_ids ) && count( $order->item_ids ) > 0 && is_array( $item_data ) && count( $item_data ) > 0 ) {
					foreach ( $order->item_ids as $item_id ) {
						$search = array_search( intval( $item_id ), array_map( 'intval', wp_list_pluck( $item_data, 'item_id' ) ), true );
						if ( false !== $search && isset( $item_data[ $search ] ) ) {
							$product_titles[] = $item_data[ $search ]->product_name;
							$qty             += absint( $item_data[ $search ]->qty );
						}
					}
				}
				unset( $order->item_ids );
				$order->product_name = implode( ', ', $product_titles );
				$order->product_qty  = $qty;
			}

			return $order_data;
		}

		public function get_contacts_revenue_records( $cid, $order_ids ) {
			global $wpdb;
			$cid = ! empty( $cid ) ? absint( $cid ) : $cid;

			// Handle order_ids - can be comma-separated string or array
			if ( is_string( $order_ids ) ) {
				$order_ids_array = array_map( 'absint', array_filter( explode( ',', $order_ids ) ) );
			} else {
				$order_ids_array = array_map( 'absint', (array) $order_ids );
			}

			if ( empty( $order_ids_array ) ) {
				return array();
			}

			$query_args = array_merge( $order_ids_array, array( $cid ) );

			$data     = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT bump.fid as fid, bump.oid as order_id, bump.bid as 'object_id', CAST(bump.total AS DECIMAL(10,2)) as 'total_revenue', p.post_title as 'object_name', bump.converted as 'is_converted', DATE_FORMAT(bump.date, '%%Y-%%m-%%d %%T') as 'date', 'bump' as 'type' FROM {$wpdb->prefix}wfob_stats AS bump LEFT JOIN {$wpdb->prefix}posts as p ON bump.bid = p.id WHERE bump.converted = 1 AND bump.oid IN ( " . implode( ', ', array_fill( 0, count( $order_ids_array ), '%d' ) ) . ' ) AND bump.cid = %d ORDER BY bump.date ASC',
					...$query_args
				)
			);
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			return $data;
		}

		public function get_bumps_by_order_id( $order_id ) {
			global $wpdb;

			$data     = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT bump.bid as 'id', p.post_title as 'bump_name', '' as 'bump_products', CASE WHEN bump.converted = 1 THEN 'Yes' ELSE 'No' END as 'bump_converted', bump.oid as 'bump_order_id', CAST(bump.total AS DECIMAL(10,2)) as 'bump_total' FROM {$wpdb->prefix}wfob_stats AS bump LEFT JOIN {$wpdb->prefix}posts as p ON bump.bid = p.id WHERE bump.oid = %d order by bump.date asc",
					$order_id
				),
				ARRAY_A
			);
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			return $data;
		}

		/**
		 * @param $cid
		 *
		 * @return array|object|null
		 */
		public function get_all_contact_record_by_cid( $cid ) {
			global $wpdb;
			$cid = ! empty( $cid ) ? absint( $cid ) : $cid;

			$data     = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT bump.oid as order_id, bump.bid as 'object_id', CAST(bump.total AS DECIMAL(10,2)) as 'total_revenue', p.post_title as 'object_name', bump.converted as 'is_converted', DATE_FORMAT(bump.date, '%%Y-%%m-%%dT%%TZ') as 'date', 'bump' as 'type' FROM {$wpdb->prefix}wfob_stats AS bump LEFT JOIN {$wpdb->prefix}posts as p ON bump.bid = p.id WHERE bump.converted = 1 AND bump.cid = %d ORDER BY bump.date ASC",
					$cid
				)
			);
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			if ( ! empty( $data[0]->order_id ) ) {
				$order_products = ! empty( wc_get_order( $data[0]->order_id ) ) ? wffn_rest_funnel_modules()->get_first_item( $data[0]->order_id ) : array();
				if ( ! empty( $order_products ) ) {
					$data[0]->product_name = $order_products['title'];
					$data[0]->product_qty  = $order_products['more'];
				}
			} elseif ( ! empty( $data[0] ) ) {
				$data[0]->product_name = '';
				$data[0]->product_qty  = '';
			}

			return $data;
		}

		/**
		 * @param $cids
		 * @param $funnel_id
		 *
		 * @return array|false[]|true
		 */
		public function delete_contact( $cids, $funnel_id = 0 ) {
			global $wpdb;
			$cid_count = count( $cids );

			if ( empty( $cid_count ) ) {
				return true;
			}

			$placeholders = implode( ',', array_fill( 0, $cid_count, '%d' ) );
			$funnel_id    = absint( $funnel_id );

			if ( $funnel_id > 0 ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare(
					'DELETE FROM ' . $wpdb->prefix . "wfob_stats WHERE cid IN ($placeholders) AND fid = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is generated from array_fill with %d placeholders
					...array_merge( $cids, array( $funnel_id ) )
				);
				// phpcs:enable
			} else {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$query = $wpdb->prepare(
					'DELETE FROM ' . $wpdb->prefix . "wfob_stats WHERE cid IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is generated from array_fill with %d placeholders
					...$cids
				);
				// phpcs:enable
			}

			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			return true;
		}

		/**
		 * @param $funnel_id
		 */
		public function reset_analytics( $funnel_id ) {
			global $wpdb;
			$query = $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'wfob_stats WHERE fid = %d', $funnel_id );
			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		}
	}
}
