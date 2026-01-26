<?php

if ( ! class_exists( 'BWFAN_Model_Engagement_Trackingmeta' ) && BWFAN_Common::is_pro_3_0() ) {
	class BWFAN_Model_Engagement_Trackingmeta extends BWFAN_Model {
		static $primary_key = 'ID';

		static function get_meta( $con_id, $key = '' ) {
			global $wpdb;
			$table = self::_table();

			if ( ! empty( $key ) ) {
				$query = $wpdb->prepare( "SELECT meta_key, meta_value FROM {$table} WHERE meta_key = %s AND eid = %d", $key, $con_id ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$query = $wpdb->prepare( "SELECT meta_key, meta_value FROM {$table} WHERE eid = %d", $con_id ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			return $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		static function get_merge_tags( $con_id ) {
			$result = self::get_meta( $con_id, 'merge_tags' );
			if ( empty( $result ) ) {
				return array();
			}

			try {
				$merge_tags = json_decode( $result[0]['meta_value'], true );
			} catch ( Exception $e ) {
				return array();
			}

			return $merge_tags;
		}

		static function get_notification_data( $con_id ) {
			$result = self::get_meta( $con_id, 'notification_data' );
			if ( empty( $result ) ) {
				return array();
			}
			try {
				$merge_tags = json_decode( $result[0]['meta_value'], true );
			} catch ( Exception $e ) {
				return array();
			}

			return $merge_tags;
		}

		public static function delete_engagements_meta( $ids ) {
			if ( empty( $ids ) ) {
				return;
			}

			global $wpdb;
			$table = self::_table();

			$placeholders = array_fill( 0, count( $ids ), '%d' );
			$placeholders = implode( ', ', $placeholders );

			$query = $wpdb->prepare( "DELETE FROM {$table} WHERE eid IN ($placeholders)", $ids );

			return $wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}