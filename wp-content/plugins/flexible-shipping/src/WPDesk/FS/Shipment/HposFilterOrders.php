<?php
/**
 * Filter the HPOS orders list by shipping integration and shipment status.
 */

declare( strict_types=1 );

namespace WPDesk\FS\Shipment;

use Automattic\WooCommerce\Utilities\OrderUtil;
use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Keep order metadata and shipment matches within the HPOS list query.
 */
final class HposFilterOrders implements Hookable {

	public function hooks(): void {
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', [ $this, 'filter_query_args' ] );
		add_filter( 'woocommerce_orders_table_query_clauses', [ $this, 'filter_query_clauses' ], 10, 3 );
	}

	/**
	 * @internal
	 * @param array $args Order list query arguments.
	 * @return array
	 */
	public function filter_query_args( $args ): array {
		$integration = $this->get_request_filter( 'flexible_shipping_integration_filter' );
		$status      = $this->get_request_filter( 'flexible_shipping_status_filter' );

		if ( '' !== $integration || '' !== $status ) {
			$args['flexible_shipping_order_filters'] = [
				'integration' => $integration,
				'status'      => $status,
			];
		}

		return $args;
	}

	/**
	 * @internal
	 * @param string[] $clauses SQL clauses.
	 * @param object   $query   HPOS query (unused).
	 * @param array    $args    Order query arguments.
	 * @return string[]
	 */
	public function filter_query_clauses( $clauses, $query, $args ): array {
		global $wpdb;

		if ( empty( $args['flexible_shipping_order_filters'] ) ) {
			return $clauses;
		}

		$filters      = $args['flexible_shipping_order_filters'];
		$orders_table = OrderUtil::get_table_for_orders();
		$meta_table   = OrderUtil::get_table_for_order_meta();
		$order        = [];
		$shipment     = [];

		if ( '' !== $filters['integration'] ) {
			$order[]    = $this->order_meta_condition( $orders_table, $meta_table, '_flexible_shipping_integration', $filters['integration'] );
			$shipment[] = $wpdb->prepare(
				'EXISTS ( SELECT 1 FROM %i fs_shipment INNER JOIN %i fs_shipment_meta ON fs_shipment.ID = fs_shipment_meta.post_id
				WHERE fs_shipment.post_parent = %i.id AND fs_shipment.post_type = %s AND fs_shipment_meta.meta_key = %s AND fs_shipment_meta.meta_value = %s )',
				$wpdb->posts,
				$wpdb->postmeta,
				$orders_table,
				'shipment',
				'_integration',
				$filters['integration']
			);
		}

		if ( '' !== $filters['status'] ) {
			$order[]    = $this->order_meta_condition( $orders_table, $meta_table, '_flexible_shipping_status', $filters['status'] );
			$shipment[] = $wpdb->prepare(
				'EXISTS ( SELECT 1 FROM %i fs_shipment WHERE fs_shipment.post_parent = %i.id AND fs_shipment.post_type = %s AND fs_shipment.post_status = %s )',
				$wpdb->posts,
				$orders_table,
				'shipment',
				'fs-' . $filters['status']
			);
		}

		if ( $order ) {
			$clauses['where'] .= ' AND ( ( ' . implode( ' AND ', $order ) . ' ) OR ( ' . implode( ' AND ', $shipment ) . ' ) )';
		}

		return $clauses;
	}

	private function order_meta_condition( string $orders_table, string $meta_table, string $key, string $value ): string {
		global $wpdb;

		return $wpdb->prepare(
			'EXISTS ( SELECT 1 FROM %i fs_order_meta WHERE %i.id = fs_order_meta.order_id AND fs_order_meta.meta_key = %s AND fs_order_meta.meta_value = %s )',
			$meta_table,
			$orders_table,
			$key,
			$value
		);
	}

	private function get_request_filter( string $name ): string {
		// Read-only list filters use GET parameters without a nonce, just like WooCommerce's filters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? sanitize_key( wp_unslash( $_GET[ $name ] ) ) : '';
	}
}
