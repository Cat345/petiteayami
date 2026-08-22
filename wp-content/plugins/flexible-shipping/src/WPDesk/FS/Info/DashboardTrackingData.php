<?php
/**
 * Dashboard tracking data.
 *
 * @package WPDesk\FS\Info
 */

declare( strict_types=1 );

namespace WPDesk\FS\Info;

/**
 * Stores normalized dashboard counters.
 */
final class DashboardTrackingData {

	const OPTION_NAME = 'flexible_shipping_dashboard_tracking';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_data(): array {
		$data = get_option( self::OPTION_NAME, [] );

		return [
			Dashboard::VARIANT_FREE => $this->normalize_variant( is_array( $data ) ? ( $data[ Dashboard::VARIANT_FREE ] ?? [] ) : [], false ),
			Dashboard::VARIANT_PRO  => $this->normalize_variant( is_array( $data ) ? ( $data[ Dashboard::VARIANT_PRO ] ?? [] ) : [], true ),
		];
	}

	/**
	 * @param array<string, int> $increments    Counter increments.
	 * @param bool               $is_pro_active Is Flexible Shipping PRO active.
	 */
	public function increase( array $increments, bool $is_pro_active ): void {
		$data    = $this->get_data();
		$variant = $is_pro_active ? Dashboard::VARIANT_PRO : Dashboard::VARIANT_FREE;

		foreach ( $increments as $path => $increment ) {
			if ( ! in_array( $path, $this->allowed_paths( $is_pro_active ), true ) || $increment < 0 ) {
				continue;
			}

			if ( 0 === strpos( $path, 'onboarding.' ) ) {
				$key                                     = substr( $path, strlen( 'onboarding.' ) );
				$data[ $variant ]['onboarding'][ $key ] += $increment;
			} else {
				$data[ $variant ][ $path ] += $increment;
			}
		}

		update_option( self::OPTION_NAME, $data, false );
	}

	/**
	 * @param mixed $stored Stored variant data.
	 * @param bool  $is_pro Is PRO variant.
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_variant( $stored, bool $is_pro ): array {
		$normalized        = $this->defaults( $is_pro );
		$stored            = is_array( $stored ) ? $stored : [];
		$stored_onboarding = is_array( $stored['onboarding'] ?? null ) ? $stored['onboarding'] : [];

		foreach ( $normalized as $key => $default ) {
			if ( 'onboarding' === $key ) {
				foreach ( $default as $onboarding_key => $value ) {
					$normalized['onboarding'][ $onboarding_key ] = max( 0, (int) ( $stored_onboarding[ $onboarding_key ] ?? 0 ) );
				}
			} else {
				$normalized[ $key ] = max( 0, (int) ( $stored[ $key ] ?? 0 ) );
			}
		}

		return $normalized;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults( bool $is_pro ): array {
		$onboarding = [
			'step_1_reached_count' => 0,
			'step_2_reached_count' => 0,
			'step_3_reached_count' => 0,
			'step_4_reached_count' => 0,
			'step_5_reached_count' => 0,
			'completed_count'      => 0,
			'restart_count'        => 0,
		];

		if ( $is_pro ) {
			$onboarding['step_6_reached_count']         = 0;
			$onboarding['activate_license_click_count'] = 0;
			$onboarding['check_extensions_click_count'] = 0;
		} else {
			$onboarding['add_zone_click_count']   = 0;
			$onboarding['add_method_click_count'] = 0;
			$onboarding['open_cart_click_count']  = 0;
		}

		return [
			'views_count'               => 0,
			'visible_time_seconds'      => 0,
			'navigation_click_count'    => 0,
			'reached_end_count'         => 0,
			'create_method_click_count' => 0,
			'extensions_click_count'    => 0,
			'onboarding'                => $onboarding,
		];
	}

	/**
	 * @return string[]
	 */
	private function allowed_paths( bool $is_pro ): array {
		$paths = [];

		foreach ( $this->defaults( $is_pro ) as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( array_keys( $value ) as $onboarding_key ) {
					$paths[] = 'onboarding.' . $onboarding_key;
				}
			} else {
				$paths[] = $key;
			}
		}

		return $paths;
	}
}
