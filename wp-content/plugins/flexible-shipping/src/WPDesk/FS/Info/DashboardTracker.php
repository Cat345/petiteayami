<?php
/**
 * Dashboard tracker integration.
 *
 * @package WPDesk\FS\Info
 */

declare( strict_types=1 );

namespace WPDesk\FS\Info;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Adds dashboard counters to the Flexible Shipping tracker payload.
 */
final class DashboardTracker implements Hookable {

	/**
	 * @var DashboardTrackingData
	 */
	private DashboardTrackingData $tracking_data;

	public function __construct( DashboardTrackingData $tracking_data ) {
		$this->tracking_data = $tracking_data;
	}

	public function hooks(): void {
		add_filter( 'wpdesk_tracker_data', [ $this, 'add_tracking_data' ], 12 );
	}

	/**
	 * @param array<string, mixed> $data Tracker data.
	 *
	 * @return array<string, mixed>
	 */
	public function add_tracking_data( array $data ): array {
		$data['flexible_shipping']['dashboard'] = $this->tracking_data->get_data();

		return $data;
	}
}
