<?php
/**
 * Class ThresholdAlertTracker
 *
 * @package WPDesk\FS\TableRate\FreeShipping\Tracker
 */

declare( strict_types=1 );

namespace WPDesk\FS\TableRate\FreeShipping\Tracker;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Can append free shipping threshold alert data to tracker.
 */
final class ThresholdAlertTracker implements Hookable {

	const DISPLAYED_ACTION   = 'flexible-shipping/free-shipping-threshold-alert/displayed';
	const OPTION_NAME        = 'fs-free-shipping-threshold-alert-displayed';
	const TRACKER_DATA_NAME  = 'free_shipping_threshold_alert_displayed';
	const OPTION_VALUE_TRUE  = 1;
	const TRACKER_DATA_FALSE = 0;
	const TRACKER_DATA_TRUE  = 1;

	const PRIORITY_AFTER_FLEXIBLE_SHIPPING_TRACKER = \WPDesk_Flexible_Shipping_Tracker::TRACKER_DATA_FILTER_PRIORITY + 1;

	public function hooks(): void {
		add_action( self::DISPLAYED_ACTION, [ $this, 'mark_alert_as_displayed' ] );
		add_filter( 'wpdesk_tracker_data', [ $this, 'append_tracker_data' ], self::PRIORITY_AFTER_FLEXIBLE_SHIPPING_TRACKER );
	}

	/**
	 * @return bool
	 */
	public function mark_alert_as_displayed(): bool {
		if ( self::OPTION_VALUE_TRUE === (int) get_option( self::OPTION_NAME, 0 ) ) {
			return false;
		}

		return update_option( self::OPTION_NAME, self::OPTION_VALUE_TRUE, false );
	}

	/**
	 * @param mixed $data Tracker data.
	 *
	 * @return mixed
	 */
	public function append_tracker_data( $data ) {
		if ( is_array( $data ) && isset( $data['flexible_shipping'] ) && is_array( $data['flexible_shipping'] ) ) {
			$data['flexible_shipping'][ self::TRACKER_DATA_NAME ] = self::OPTION_VALUE_TRUE === (int) get_option( self::OPTION_NAME, 0 ) ? self::TRACKER_DATA_TRUE : self::TRACKER_DATA_FALSE;
		}

		return $data;
	}
}
