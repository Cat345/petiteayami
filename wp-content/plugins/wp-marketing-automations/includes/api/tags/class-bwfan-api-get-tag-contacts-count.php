<?php

class BWFAN_API_Get_Tag_Contacts_Count extends BWFAN_API_Base {

	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::READABLE;
		$this->route  = '/v3/tags/contacts';
	}

	public function default_args_values() {
		return array( 'tag_ids' => [] );
	}

	public function process_api_call() {
		$tag_ids = $this->get_sanitized_arg( '', 'text_field', $this->args['tag_ids'] );

		$this->response_code = 200;

		/** In case empty **/
		if ( empty( $tag_ids ) || ! is_array( $tag_ids ) ) {
			return $this->success_response( [] );
		}

		$data = [];
		foreach ( $tag_ids as $tag_id ) {
			if ( ! isset( $data[ $tag_id ] ) ) {
				$data[ $tag_id ] = [];
			}

			$count_data                           = $this->get_contact_count( $tag_id );
			$data[ $tag_id ]['contact_count']     = $count_data['count'];
			$data[ $tag_id ]['customer_count']    = $count_data['customers'];
			$data[ $tag_id ]['total_revenue']     = $count_data['revenue'];
			$data[ $tag_id ]['subscribers_count'] = $this->get_contact_count( $tag_id, true );
		}

		return $this->success_response( $data );
	}

	/**
	 * @param $tag_id
	 * @param $exclude_unsubs
	 *
	 * @return array|int|int[]
	 */
	public function get_contact_count( $tag_id, $exclude_unsubs = false ) {
		global $wpdb;
		$tag_id = '%"' . $tag_id . '"%';
		if ( true === $exclude_unsubs ) {
			$query = "SELECT COUNT(DISTINCT c.id) FROM {$wpdb->prefix}bwf_contact as c   WHERE 1=1 AND ( c.email != '' AND c.email IS NOT NULL ) AND ( c.tags LIKE %s  )";
			$query .= " AND ( c.status = 1 )    AND (   NOT EXISTS   (SELECT 1 FROM {$wpdb->prefix}bwfan_message_unsubscribe AS unsub WHERE c.email = unsub.recipient ) AND   NOT EXISTS  (SELECT 1 FROM {$wpdb->prefix}bwfan_message_unsubscribe AS unsub1 WHERE c.contact_no = unsub1.recipient ))";

			return intval( $wpdb->get_var( $wpdb->prepare( $query, $tag_id ) ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		$query     = "SELECT COUNT(DISTINCT c.`id`) AS contacts, COUNT(DISTINCT wc.`id`) AS customers, SUM( wc.`total_order_value`) AS total_revenue  FROM {$wpdb->prefix}bwf_contact as c LEFT JOIN {$wpdb->prefix}bwf_wc_customers AS wc ON c.`id`=wc.`cid` WHERE 1=1 AND ( c.email != '' AND c.email IS NOT NULL ) AND ( c.tags LIKE %s  )";
		$customers = $wpdb->get_row( $wpdb->prepare( $query, $tag_id ), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $customers ) ) {
			return [
				'count'     => $customers['contacts'],
				'customers' => $customers['customers'],
				'revenue'   => $customers['total_revenue'] ?? 0,
			];
		}

		return [
			'count'     => 0,
			'customers' => 0,
			'revenue'   => 0,
		];
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Get_Tag_Contacts_Count' );
