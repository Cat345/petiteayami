<?php

namespace BWFAN\Importers;

use BWF_WC_Compatibility;
use BWFAN\Importers\Importer;
use BWFCRM_Contact;
use Exception;
use WC_Order;
use WooFunnels_DB_Updater;
use WP_Error;

/**
 * Class WC_Importer
 *
 * This class represents a WordPress importer for the CRM system.
 */
class WC_Importer extends Importer {
	protected $import_type = 'wc';

	public function __construct( $params = array() ) {
		$this->slug        = 'wc';
		$this->name        = __( 'WooCommerce', 'wp-marketing-automations' );
		$this->description = __( 'Import contacts from WooCommerce orders', 'wp-marketing-automations' );
		$this->logo_url    = esc_url( plugin_dir_url( BWFAN_PLUGIN_FILE ) . '/admin/assets/img/importer/wc.png' );
		$this->has_fields  = false;
		$this->group       = 0;
		$this->priority    = 7;
		parent::__construct( $params );

		add_action( 'bwf_normalize_contact_meta_after_save', array( $this, 'update_contact_data_after_import' ), 20, 3 );
	}

	/**
	 * Prepares the import data for creating a new import.
	 *
	 * @param array $import_data The import data.
	 *
	 * @return array The modified import data.
	 */
	public function prepare_create_import_data( $import_data = array(), $fields = array() ) {
		$import_data['count'] = $this->get_contacts_count();

		return $import_data;
	}

	/**
	 * Prepare the data for importing orders.
	 *
	 * This method fetches orders to import based on specified criteria and stores them in the raw_data property.
	 */
	public function populate_contact_data() {

		return self::get_wc_orders( array( 'limit' => 25, 'offset' => $this->get_offset() ) );
	}

	/**
	 * Retrieves the count of orders.
	 *
	 * This function efficiently counts orders that meet the specified criteria using a direct query.
	 * The criteria include the order type, status, and date created.
	 *
	 * @return int The count of orders.
	 */
	public function get_contacts_count() {
		$date_created = gmdate( 'Y-m-d H:i:s', time() );

		$count = self::get_wc_orders( array( 'only_count' => true, 'after_date_created' => $date_created ) );

		return ! empty( $count ) ? absint( $count ) : 0;
	}

	/**
	 * Process an item for import.
	 *
	 * This method is responsible for processing an item for import, which includes creating or updating a contact based on the provided order ID.
	 *
	 * @param int|null $order_id The ID of the order to process.
	 *
	 * @return array|void|WP_Error An array containing the ID of the contact and a flag indicating if the contact was updated, or a WP_Error object if there was an error.
	 */
	public function process_item( $order_id = null ) {
		if ( empty( absint( $order_id ) ) ) {
			return;
		}

		WooFunnels_DB_Updater::$indexing = true;

		try {
			$cid = bwf_create_update_contact( $order_id, array(), 0, true );
		} catch ( Exception $e ) {
			$error_msg = $e->getMessage();
			BWFAN_Core()->logger->log( 'order id #' . $order_id . ' parsing broke with error message: ' . $error_msg, 'import_wc_contacts' );
		}

		WooFunnels_DB_Updater::$indexing = null;

		if ( empty( $cid ) ) {
			// Only load order if we need email for error reporting
			$email = '';
			if ( ! empty( $error_msg ) ) {
				$order = wc_get_order( $order_id );
				$email = $order instanceof WC_Order ? $order->get_billing_email() : '';
			}
			
			$error_msg  = empty( $error_msg ) ? __( 'Order data is not valid.', 'wp-marketing-automations' ) : $error_msg;
			$error_data = array(
				'contact_email' => $email,
				'original_id'   => $order_id,
			);

			return new WP_Error( 'bwfcrm_invalid_order_data', $error_msg, $error_data );
		}

		$contact         = new \WooFunnels_Contact( '', '', '', $cid );
		$update_existing = $this->get_import_meta( 'update_existing' );

		return array(
			'id'      => $contact->get_id(),
			'updated' => $contact->get_id() > 0 && $update_existing,
		);
	}

	/**
	 * Updates contact data after import.
	 *
	 * This function is responsible for updating the contact data after importing it.
	 * It sets the contact tags and contact lists to empty arrays.
	 *
	 * @param object $contact The contact object.
	 *
	 * @return void
	 */
	public function update_contact_data_after_import( $contact, $order_id, $order ) {
		if ( ! $contact instanceof \WooFunnels_Contact ) {
			return;
		}

		$to_be_assigned_tags  = $this->get_new_tags();
		$to_be_assigned_lists = $this->get_new_lists();
		$contact              = $this->set_contact_tags( $contact, $to_be_assigned_tags );
		$contact              = $this->set_contact_lists( $contact, $to_be_assigned_lists );

		if ( ! empty( $to_be_assigned_tags ) || ! empty( $to_be_assigned_lists ) ) {
			$contact->save();
		}

		$update_existing = $this->get_import_meta( 'update_existing' );
		if ( ! $contact instanceof BWFCRM_Contact || empty( $update_existing ) ) {
			return;
		}

		$disable_events = $this->get_import_meta( 'disable_events' );
		$import_status  = $this->get_import_meta( 'imported_contact_status' );
		switch ( intval( $import_status ) ) {
			case 0:
				$contact->unverify();
				break;
			case 1:
				$contact->resubscribe( $disable_events );
				break;
			case 2:
				$contact->mark_as_bounced();
				break;
			case 3:
				if ( $order instanceof WC_Order ) {
					$order->delete_meta_data( 'marketing_status' );
					$order->save();
				}
				$contact->contact->is_subscribed = false;
				$contact->unsubscribe( $disable_events );
				break;
			case 4:
				$contact->mark_as_soft_bounced();
				break;
			case 5:
				$contact->mark_as_complaint();
				break;
		}
	}

	/**
	 * Get the log headers for this importer.
	 *
	 * @return array
	 */
	protected function get_log_headers() {
		return [ 'ID', 'Order ID', 'Status' ];
	}

	/**
	 * Prepare log data for a single item.
	 *
	 * @param mixed $data
	 * @param mixed $result
	 *
	 * @return array
	 */
	protected function prepare_log_data( $data, $result ) {
		return [
			'ID'       => is_wp_error( $result ) ? 0 : $result['id'],
			'Order ID' => $data ?? 0,
			'Status'   => is_wp_error( $result ) ? __( 'Failed', 'wp-marketing-automations' ) : ( isset( $result['skipped'] ) && $result['skipped'] ? __( 'Skipped', 'wp-marketing-automations' ) : __( 'Success', 'wp-marketing-automations' ) ),
		];
	}

	/**
	 * Get the second step fields for wc importer
	 *
	 * @return array
	 *
	 */
	public function get_second_step_fields() {
		$count       = $this->get_contacts_count();
		$this->count = $count;
		$message     = __( 'There are no order to import', 'wp-marketing-automations' );
		$type        = 'error';
		if ( $count > 0 ) {
			/* translators: 1: Order count */
			$message = sprintf( __( 'There are %d orders to be imported', 'wp-marketing-automations' ), $count ); //translators: number of orders to be imported
			$type    = 'warning';
		}

		return [
			[
				'id'          => 'wc_notice',
				'type'        => 'notice',
				'noticeLabel' => __( 'Orders', 'wp-marketing-automations' ),
				'nType'       => $type,
				'isHtml'      => true,
				'text'        => '<span class="bwf-heading8-new">' . __( 'Information', 'wp-marketing-automations' ) . ':</span> ' . $message,
				'desc'        => '',
			]
		];
	}

	/**
	 * Validate the count of contacts to be imported.
	 *
	 * @return bool
	 */
	public function validate_second_step() {
		return intval( $this->count ) > 0;
	}

	/**
	 * Needed only status and disable mebent field
	 *
	 * @return array
	 */
	public function contact_profile_fields() {
		return [
			'marketing_status',
			'disable_events',
			'update_existing'
		];
	}

	/**
	 * Get WooCommerce orders
	 *
	 * Fetches WooCommerce order IDs using direct SQL queries for better performance.
	 * Supports both HPOS (High-Performance Order Storage) and legacy post-based storage.
	 *
	 * @param array $args {
	 *     Optional. Arguments for querying orders.
	 *
	 *     @type string $after_date_created Optional. MySQL datetime string. Fetches orders created before this date.
	 *                                      Format: 'Y-m-d H:i:s' (e.g., '2024-01-01 00:00:00').
	 *     @type int    $limit              Optional. Number of orders to fetch. Default: no limit.
	 *     @type int    $offset             Optional. Number of orders to skip. Default: 0.
	 *     @type bool   $only_count         Optional. If true, returns count instead of order IDs. Default: false.
	 * }
	 *
	 * @return array|int Array of order IDs, or integer count if $only_count is true.
	 *                   Returns empty array if no paid statuses configured or no orders found.
	 */
	public static function get_wc_orders( $args = [] ) {

		global $wpdb;
		$paid_status = wc_get_is_paid_statuses();
		$paid_status = is_array( $paid_status ) ? array_map( function ( $status ) {
			return 'wc-' . $status;
		}, $paid_status ) : [];
		if ( empty( $paid_status ) ) {
			return [];
		}

		$placeholder        = array_fill( 0, count( $paid_status ), '%s' );
		$placeholder        = implode( ", ", $placeholder );
		$order_by           = '';
		$after_date_created = '';
		$limit_query        = '';
		$prepare_args        = $paid_status;
		if ( BWF_WC_Compatibility::is_hpos_enabled() ) {
			$query       = "SELECT `id` FROM {$wpdb->prefix}wc_orders  WHERE `status` IN ($placeholder) AND `type` = 'shop_order' AND ( `parent_order_id` = 0 OR `parent_order_id` IS NULL ) ";
			$count_query = "SELECT COUNT(`id`) FROM {$wpdb->prefix}wc_orders  WHERE `status` IN ($placeholder) AND `type` = 'shop_order' AND (`parent_order_id` = 0 OR `parent_order_id` IS NULL )";

			if ( ! empty( $args['after_date_created'] ) ) {
				/**
				 * Use '<' operator to fetch orders created BEFORE the specified date.
				 * This is correct for importing historical orders and matches the
				 * behavior of wc_get_orders() with 'date_created' => '<' parameter.
				 */
				$after_date_created = " AND `date_created_gmt` < %s ";
				$order_by           = " ORDER BY date_created_gmt DESC";
				$prepare_args[]     = $args['after_date_created'];
			}

		} else {
			$query       = "SELECT `ID` AS id FROM {$wpdb->posts} WHERE `post_parent` = 0 AND `post_type` = 'shop_order' AND post_status IN($placeholder)";
			$count_query = "SELECT COUNT(`ID`) FROM {$wpdb->posts} WHERE `post_parent` = 0  AND `post_type` = 'shop_order' AND post_status IN($placeholder)";

			if ( ! empty( $args['after_date_created'] ) ) {
				/**
				 * Use '<' operator to fetch orders created BEFORE the specified date.
				 * This is correct for importing historical orders and matches the
				 * behavior of wc_get_orders() with 'date_created' => '<' parameter.
				 */
				$after_date_created = " AND `post_date_gmt` < %s ";
				$order_by           = " ORDER BY post_date_gmt DESC";
				$prepare_args[]     = $args['after_date_created'];
			}

		}
		if ( ! empty( $args['only_count'] ) ) {
			$query = $wpdb->prepare( "$count_query $after_date_created", ...$prepare_args );
			$count = $wpdb->get_var( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return ! empty( $count ) ? intval( $count ) : 0;
		}
		$query = $wpdb->prepare( "$query $after_date_created $order_by", ...$prepare_args );
		if ( ! empty( $args['limit'] ) ) {
			$limit_query = " LIMIT %d OFFSET %d";
			$limit_query = $wpdb->prepare( $limit_query, $args['limit'], isset( $args['offset'] ) ? $args['offset'] : 0 );
		}
		$orders = $wpdb->get_col( "$query $limit_query " ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return ! empty( $orders ) ? $orders : [];
	}
}

if ( function_exists( 'bwfan_is_woocommerce_active' ) && bwfan_is_woocommerce_active() ) {
	BWFAN_Core()->importer->register( 'wc', 'BWFAN\Importers\WC_Importer' );
}