<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class WFOB_Contacts_Analytics
 */
if ( ! class_exists( 'WFOB_Contacts_Analytics' ) ) {

	class WFOB_Contacts_Analytics extends WFFN_REST_Controller {

		/**
		 * instance of class
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
		public function get_top_bumps( $start_date, $end_date, $limit_str = ''  ) {
			global $wpdb;
			$where_condition = "WHERE bmp.converted = 1";
			$view_where_condition = "";

			if (!empty($start_date) && !empty($end_date)) {
				$where_condition = "WHERE date >= '$start_date' AND date < '$end_date' AND bmp.converted = 1";
				$view_where_condition = "WHERE date >= '$start_date' AND date < '$end_date'";
			}

			$query = "SELECT v.bid as id,IFNULL(s.revenue, 0) as revenue, IFNULL(s.conversion, 0) as conversion,p.post_title as title,p.post_type as post_type,v.view_count as views FROM (SELECT bid, COUNT(id) as view_count FROM `" . $wpdb->prefix . "wfob_stats` $view_where_condition GROUP BY bid) as v LEFT JOIN " . $wpdb->prefix . "posts as p ON p.id = v.bid LEFT JOIN (SELECT bid, sum(total) as revenue, COUNT(id) as conversion FROM `" . $wpdb->prefix . "wfob_stats` as bmp $where_condition GROUP BY bid) as s ON s.bid = v.bid ORDER BY revenue DESC " . $limit_str;
			$data     = $wpdb->get_results( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}
			if ( ! empty( $data ) && is_array( $data ) ) {
				foreach ( $data as $key => $item ) {
					$data[ $key ]->conversion_rate = $this->get_percentage( absint( $item->views ), $item->conversion );
					$data[ $key ]->type = $item->post_type;
					$funnel_id = get_post_meta( $item->id, '_bwf_in_funnel', true );
					$data[ $key ]->fid = $funnel_id;
					if ( $funnel_id ) {
						$funnel_name = $wpdb->get_var(
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
			$item_data = [];
			$funnel_id = ! empty( $funnel_id ) ? absint( $funnel_id ) : $funnel_id;
			$cid       = ! empty( $cid ) ? absint( $cid ) : $cid;
			$query     = "SELECT bump.oid as order_id, bump.bid as 'object_id', bump.iid as 'item_ids', bump.total as 'total_revenue',p.post_title as 'object_name', bump.converted as 'is_converted',DATE_FORMAT(bump.date, '%Y-%m-%dT%TZ') as 'date','bump' as 'type' FROM " . $wpdb->prefix . 'wfob_stats' . " AS bump LEFT JOIN " . $wpdb->prefix . 'posts' . " as p ON bump.bid  = p.id  WHERE bump.converted = 1 AND bump.fid=$funnel_id AND bump.cid=$cid order by bump.date asc";

			$order_data = $wpdb->get_results( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$db_error   = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			if ( ! is_array( $order_data ) || count( $order_data ) === 0 ) {
				return $item_data;
			}

			$all_item_ids = [];

			/** merge all items ids in one array */
			if ( is_array( $order_data ) && count( $order_data ) > 0 ) {
				foreach ( $order_data as &$i_array ) {
					$i_array->item_ids = ( isset( $i_array->item_ids ) && '' != $i_array->item_ids ) ? json_decode( $i_array->item_ids ) : [];
					if ( is_array( $i_array->item_ids ) && count( $i_array->item_ids ) > 0 ) {
						$all_item_ids = array_merge( $all_item_ids, $i_array->item_ids );
					}
				}
			}

			if ( is_array( $all_item_ids ) && count( $all_item_ids ) > 0 ) {
				/**
				 * get order item product name and quantity by items ids
				 */
				$item_query = "SELECT oi.order_item_id as 'item_id', oi.order_item_name as 'product_name', oim.meta_value as 'qty' FROM " . $wpdb->prefix . "woocommerce_order_items as oi LEFT JOIN " . $wpdb->prefix . "woocommerce_order_itemmeta as oim ON oi.order_item_id = oim.order_item_id WHERE oi.order_item_id IN (" . esc_sql( implode( ',', $all_item_ids ) ) . ") AND oi.order_item_type = 'line_item' AND oim.meta_key = '_qty' GROUP BY oi.order_item_id";
				$item_data  = $wpdb->get_results( $item_query ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$db_error   = WFFN_Common::maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}
			}

			foreach ( $order_data as &$order ) {
				$product_titles = [];
				$qty            = 0;
				if ( is_array( $order->item_ids ) && count( $order->item_ids ) > 0 && is_array( $item_data ) && count( $item_data ) > 0 ) {
					foreach ( $order->item_ids as $item_id ) {
						$search = array_search( intval( $item_id ), array_map( 'intval', wp_list_pluck( $item_data, 'item_id' ) ), true );
						if ( false !== $search && isset( $item_data[ $search ] ) ) {
							$product_titles[] = $item_data[ $search ]->product_name;
							$qty              += absint( $item_data[ $search ]->qty );
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
			$order_ids = ! empty( $order_ids ) ? esc_sql( $order_ids ) : $order_ids;
			$cid       = ! empty( $cid ) ? absint( $cid ) : $cid;
			$query = "SELECT conv.funnel_id as fid, conv.source as order_id, conv.step_id as 'object_id', conv.bump_total as 'total_revenue', p.post_title as 'object_name', CASE WHEN conv.bump_total > 0 AND conv.bump_accepted != '' THEN 1 ELSE 0 END as 'is_converted', DATE_FORMAT(conv.timestamp, '%Y-%m-%d %T') as 'date', 'bump' as 'type' FROM " . $wpdb->prefix . 'bwf_conversion_tracking' . " AS conv LEFT JOIN " . $wpdb->prefix . 'posts' . " as p ON conv.step_id = p.id WHERE conv.type = 2 AND conv.bump_total > 0 AND conv.bump_accepted != '' AND conv.source IN ( $order_ids ) AND conv.contact_id=$cid order by conv.timestamp asc";

			$data     = $wpdb->get_results( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			return $data;

		}

		public function get_bumps_by_order_id( $order_id ) {
			global $wpdb;

			$query    = $wpdb->prepare( "SELECT conv.step_id as 'id', p.post_title as 'bump_name', '' as 'bump_products', CASE WHEN conv.bump_total > 0 AND conv.bump_accepted != '' THEN 'Yes' ELSE 'No' END as 'bump_converted', conv.source as 'bump_order_id', conv.bump_total as 'bump_total' FROM " . $wpdb->prefix . 'bwf_conversion_tracking' . " AS conv LEFT JOIN " . $wpdb->prefix . 'posts' . " as p ON conv.step_id = p.id WHERE conv.type = 2 AND conv.source=%s order by conv.timestamp asc", $order_id );
			$data     = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			$cid       = ! empty( $cid ) ? absint( $cid ) : $cid;
			$query = "SELECT conv.source as order_id, conv.step_id as 'object_id', conv.bump_total as 'total_revenue', p.post_title as 'object_name', CASE WHEN conv.bump_total > 0 AND conv.bump_accepted != '' THEN 1 ELSE 0 END as 'is_converted', DATE_FORMAT(conv.timestamp, '%Y-%m-%dT%TZ') as 'date', 'bump' as 'type' FROM " . $wpdb->prefix . 'bwf_conversion_tracking' . " AS conv LEFT JOIN " . $wpdb->prefix . 'posts' . " as p ON conv.step_id = p.id WHERE conv.type = 2 AND conv.bump_total > 0 AND conv.bump_accepted != '' AND conv.contact_id=$cid order by conv.timestamp asc";

			$data     = $wpdb->get_results( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			if ( ! empty( $data[0]->order_id ) ) {
				$order_products = ! empty( wc_get_order( $data[0]->order_id ) ) ? wffn_rest_funnel_modules()->get_first_item( $data[0]->order_id ) : [];
				if ( ! empty( $order_products ) ) {
					$data[0]->product_name = $order_products['title'];
					$data[0]->product_qty  = $order_products['more'];
				}
			} else if ( ! empty( $data[0] ) ) {
				$data[0]->product_name = '';
				$data[0]->product_qty  = '';
			}

			return $data;

		}


		/**
		 * @param $limit
		 * @param string $order
		 * @param string $order_by
		 *
		 * @return string
		 */
		public function get_timeline_data_query( $limit, $order = 'DESC', $order_by = 'date' ) {
			global $wpdb;
			$limit    = ( $limit !== '' ) ? " LIMIT " . $limit : '';
			$limit    = ! empty( $limit ) ? esc_sql( $limit ) : $limit;
			$order    = ! empty( $order ) ? esc_sql( $order ) : $order;
			$order_by = ! empty( $order_by ) ? esc_sql( $order_by ) : $order_by;

			return "SELECT conv.step_id as id, conv.funnel_id as 'fid', conv.contact_id as 'cid', conv.source as 'order_id', CONVERT( conv.bump_total USING utf8) as 'total_revenue', 'bump' as 'type', posts.post_title as 'post_title', conv.timestamp as date FROM " . $wpdb->prefix . "bwf_conversion_tracking AS conv LEFT JOIN " . $wpdb->prefix . "posts AS posts ON conv.step_id=posts.ID WHERE conv.type = 2 AND conv.bump_total > 0 AND conv.bump_accepted != '' ORDER BY " . $order_by . " " . $order . " " . $limit;

		}

		/**
		 * @param $limit
		 * @param $date_query
		 *
		 * @return array|false[]|object|stdClass[]|null
		 */
		public function get_top_funnels( $limit = '', $date_query = '' ) {
			global $wpdb;
			$limit      = ( $limit !== '' ) ? " LIMIT " . $limit : '';
			$limit    = ! empty( $limit ) ? esc_sql( $limit ) : $limit;
			$date_query = str_replace( '{{COLUMN}}', 'conv.timestamp', $date_query );
			$query      = "SELECT funnel.id as fid, funnel.title as title, stats.total as total FROM " . $wpdb->prefix . "bwf_funnels AS funnel 
    				JOIN ( SELECT funnel_id, SUM( bump_total ) as total FROM " . $wpdb->prefix . "bwf_conversion_tracking as conv 
    				WHERE funnel_id != 0 AND type = 2 AND bump_total > 0 AND bump_accepted != '' AND " . esc_sql( $date_query ) . "  GROUP BY funnel_id ) as stats ON funnel.id = stats.funnel_id WHERE 1 = 1 GROUP BY funnel.id ORDER BY total DESC " . $limit;

			$data     = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
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
			$cid_count                = count( $cids );
			$stringPlaceholders       = array_fill( 0, $cid_count, '%s' );
			$placeholdersForFavFruits = implode( ',', $stringPlaceholders );

			$funnel_query = ( absint( $funnel_id ) > 0 ) ? " AND fid = " . $funnel_id . " " : '';
			$query        = "DELETE FROM " . $wpdb->prefix . "wfob_stats WHERE cid IN (" . esc_sql( $placeholdersForFavFruits ) . ") " . $funnel_query;

			$wpdb->query( $wpdb->prepare( $query, $cids ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			$query = $wpdb->prepare( "DELETE FROM " . $wpdb->prefix . "wfob_stats WHERE fid = %d", $funnel_id );
			$wpdb->query( $query );
		}
	}
}