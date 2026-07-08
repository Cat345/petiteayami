<?php
/**
 * Class MethodLogoTracker
 *
 * @package WPDesk\FS\TableRate\ShippingMethod\Tracker
 */

declare( strict_types=1 );

namespace WPDesk\FS\TableRate\ShippingMethod\Tracker;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use WPDesk\FS\TableRate\ShippingMethod\CommonMethodSettings;

/**
 * Can append shipping method logo usage data to tracker.
 */
final class MethodLogoTracker implements Hookable {

	const TRACKER_DATA_NAME = 'method_logo_count';

	public function hooks(): void {
		add_filter( 'flexible-shipping/tracker/method-settings', [ $this, 'append_logo_data_to_tracker' ], 10, 2 );
	}

	/**
	 * @param mixed $data                     Tracker data.
	 * @param mixed $shipping_method_settings Shipping method settings.
	 *
	 * @return mixed
	 */
	public function append_logo_data_to_tracker( $data, $shipping_method_settings ) {
		if ( ! is_array( $data ) || ! is_array( $shipping_method_settings ) ) {
			return $data;
		}

		if ( ! isset( $data[ self::TRACKER_DATA_NAME ] ) ) {
			$data[ self::TRACKER_DATA_NAME ] = 0;
		}

		if ( 0 < (int) ( $shipping_method_settings[ CommonMethodSettings::METHOD_LOGO_ID ] ?? 0 ) ) {
			++$data[ self::TRACKER_DATA_NAME ];
		}

		return $data;
	}
}
