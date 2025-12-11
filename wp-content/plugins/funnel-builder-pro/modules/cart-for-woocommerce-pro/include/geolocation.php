<?php
/**
 * Override WooCommerce Geolocation Class
 */

namespace FKCart\Pro;
if ( ! class_exists( '\FKCart\Pro\Geolocation' ) ) {
	#[\AllowDynamicProperties]
	class Geolocation extends \WC_Geolocation {

		/**
		 * Get user location attributes based on IP address
		 *
		 * @param $ip_address
		 * @param $fallback
		 * @param $api_fallback
		 *
		 * @return array
		 */
		public static function geolocate_ip( $ip_address = '', $fallback = false, $api_fallback = true ) {

			$geolocation         = [
				'country'  => '',
				'state'    => '',
				'city'     => '',
				'postcode' => ''
			];
			$allow_native_filter = apply_filters( 'fkcart_allow_wc_geolocate_filters', false );
			$geolocation         = apply_filters( 'fkcart_woocommerce_geolocate_ip', $geolocation, $ip_address );
			if ( true === $allow_native_filter && empty( $geolocation['country'] ) ) {
				$country_code = apply_filters( 'woocommerce_geolocate_ip', false, $ip_address, $fallback, $api_fallback );
				if ( false !== $country_code ) {
					$country_code_data = array(
						'country'  => $country_code,
						'state'    => '',
						'city'     => '',
						'postcode' => '',
					);

					do_action( 'fkcart_geolocation', $country_code_data, $ip_address );

					return $country_code_data;
				}

				/**
				 * Get geolocation filter.
				 *
				 * @param array $geolocation Geolocation data, including country, state, city, and postcode.
				 * @param string $ip_address IP Address.
				 *
				 * @since 3.9.0
				 */
				$geolocation = apply_filters( 'woocommerce_get_geolocation', array(
					'country'  => $country_code,
					'state'    => '',
					'city'     => '',
					'postcode' => '',
				), $ip_address );

				do_action( 'fkcart_geolocation', $geolocation, $ip_address );
			}

			if ( '' === $geolocation['country'] ) {
				$geolocation = self::geolocate_via_api( $ip_address );
			}

			// It's possible that we're in a local environment, in which case the geolocation needs to be done from the
			// external address.
			if ( '' === $geolocation['country'] && $fallback ) {
				$external_ip_address = self::get_external_ip_address();

				// Only bother with this if the external IP differs.
				if ( '0.0.0.0' !== $external_ip_address && $external_ip_address !== $ip_address ) {
					return self::geolocate_ip( $external_ip_address, false, $api_fallback );
				}
			}

			return array(
				'country'  => $geolocation['country'],
				'state'    => $geolocation['state'],
				'city'     => $geolocation['city'],
				'postcode' => $geolocation['postcode'],
			);
		}

		/**
		 * Override Parent Class Private function For Getting More Geo Data (state,postcode,city)
		 *
		 * @param $ip_address
		 *
		 * @return array|false|mixed|string|string[]
		 */
		private static function geolocate_via_api( $ip_address ) {
			$country_data = get_transient( 'fkcart_geoip_' . $ip_address );
			if ( is_array( $country_data ) && isset( $country_data['country'] ) && ! empty( $country_data['country'] ) ) {
				do_action( 'fkcart_geolocation', $country_data, $ip_address );

				return $country_data;
			}
			$country_data   = [ 'country' => '', 'state' => '', 'postcode' => '', 'city' => '' ];
			$geoip_services = apply_filters( 'woocommerce_geolocation_geoip_apis', array(
				'ip-api.com' => 'http://ip-api.com/json/%s',
				'ipinfo.io'  => 'https://ipinfo.io/%s/json'
			) );

			if ( empty( $geoip_services ) ) {
				return $country_data;
			}

			$geoip_services_keys = array_keys( $geoip_services );
			foreach ( $geoip_services_keys as $service_name ) {
				$service_endpoint = $geoip_services[ $service_name ];
				$response         = wp_safe_remote_get( sprintf( $service_endpoint, $ip_address ), array(
					'timeout'    => 2,
					'user-agent' => 'WooCommerce/' . wc()->version,
				) );
				if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
					continue;
				}
				switch ( $service_name ) {
					case 'ip-api.com':
						$data                     = json_decode( $response['body'] );
						$country_data['country']  = isset( $data->countryCode ) ? $data->countryCode : ''; // @codingStandardsIgnoreLine
						$country_data['state']    = isset( $data->region ) ? $data->region : ''; // @codingStandardsIgnoreLine
						$country_data['postcode'] = isset( $data->zip ) ? $data->zip : ''; // @codingStandardsIgnoreLine
						$country_data['city']     = isset( $data->city ) ? $data->city : ''; // @codingStandardsIgnoreLine
						break;
					case 'ipinfo.io':
						$data                     = json_decode( $response['body'] );
						$country_data['country']  = isset( $data->country ) ? $data->country : '';
						$country_data['state']    = '';// ipinfo service not provide state 2 digit code
						$country_data['postcode'] = isset( $data->postal ) ? $data->postal : '';
						$country_data['city']     = isset( $data->city ) ? $data->city : '';
						break;
					default:
						$country_data = [ 'country' => '', 'state' => '', 'postcode' => '', 'city' => '' ];
						break;
				}
				if ( isset( $country_data['country'] ) && ! empty( $country_data['country'] ) ) {
					set_transient( 'geoip_' . $ip_address, $country_data['country'], DAY_IN_SECONDS );
					break;
				}
			}

			do_action( 'fkcart_geolocation', $country_data, $ip_address );
			set_transient( 'fkcart_geoip_' . $ip_address, $country_data, DAY_IN_SECONDS );

			return $country_data;
		}
	}
}
