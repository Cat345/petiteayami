<?php
/**
 * Dashboard tracking transport.
 *
 * @package WPDesk\FS\Info
 */

declare( strict_types=1 );

namespace WPDesk\FS\Info;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/**
 * Receives dashboard counter increments from Heartbeat and AJAX.
 */
final class DashboardTrackingReceiver implements Hookable {

	const AJAX_ACTION = 'flexible_shipping_dashboard_tracking';
	const PACKET_KEY  = 'flexible_shipping_dashboard';

	const MAX_VISIBLE_TIME_SECONDS = 300;
	const SCREEN_ID                = 'woocommerce_page_wc-settings';
	const SECTION_ID               = 'flexible_shipping_info';

	/**
	 * @var DashboardTrackingData
	 */
	private DashboardTrackingData $tracking_data;

	public function __construct( DashboardTrackingData $tracking_data ) {
		$this->tracking_data = $tracking_data;
	}

	public function hooks(): void {
		add_filter( 'heartbeat_received', [ $this, 'receive_heartbeat' ], 10, 3 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'receive_ajax' ] );
	}

	/**
	 * @param array<string, mixed> $response Heartbeat response.
	 * @param array<string, mixed> $data     Heartbeat request data.
	 * @param string               $screen_id Current screen ID.
	 *
	 * @return array<string, mixed>
	 */
	public function receive_heartbeat( array $response, array $data, string $screen_id ): array {
		if ( self::SCREEN_ID === $screen_id && $this->request_is_allowed() && isset( $data[ self::PACKET_KEY ] ) ) {
			$this->save_packet( $data[ self::PACKET_KEY ] );
		}

		return $response;
	}

	/**
	 * @internal
	 */
	public function receive_ajax(): void {
		check_ajax_referer( self::AJAX_ACTION );

		if ( ! $this->request_is_allowed() ) {
			wp_send_json_error();
			return;
		}

		$packet = json_decode( sanitize_text_field( wp_unslash( $_POST['events'] ?? '' ) ), true );
		if ( ! $this->save_packet( $packet ) ) {
			wp_send_json_error();
			return;
		}

		wp_send_json_success();
	}

	/**
	 * @param mixed $packet Packet to validate and save.
	 */
	private function save_packet( $packet ): bool {
		if ( ! is_array( $packet ) || [] === $packet ) {
			return false;
		}

		$allowed = $this->allowed_fields();
		foreach ( $packet as $field => $increment ) {
			if ( ! in_array( $field, $allowed, true ) || ! is_int( $increment ) || $increment < 0 ) {
				return false;
			}
		}

		if ( ( $packet['visible_time_seconds'] ?? 0 ) > self::MAX_VISIBLE_TIME_SECONDS ) {
			return false;
		}

		$this->tracking_data->increase(
			$packet,
			wpdesk_is_plugin_active( 'flexible-shipping-pro/flexible-shipping-pro.php' )
		);

		return true;
	}

	private function request_is_allowed(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$query = parse_url( (string) wp_get_referer(), PHP_URL_QUERY );
		parse_str( is_string( $query ) ? $query : '', $parameters );

		return self::SECTION_ID === ( $parameters['section'] ?? '' );
	}

	/**
	 * @return string[]
	 */
	private function allowed_fields(): array {
		return [
			'views_count',
			'visible_time_seconds',
			'navigation_click_count',
			'reached_end_count',
			'create_method_click_count',
			'extensions_click_count',
			'onboarding.step_1_reached_count',
			'onboarding.step_2_reached_count',
			'onboarding.step_3_reached_count',
			'onboarding.step_4_reached_count',
			'onboarding.step_5_reached_count',
			'onboarding.step_6_reached_count',
			'onboarding.completed_count',
			'onboarding.restart_count',
			'onboarding.add_zone_click_count',
			'onboarding.add_method_click_count',
			'onboarding.open_cart_click_count',
			'onboarding.activate_license_click_count',
			'onboarding.check_extensions_click_count',
		];
	}
}
