<?php

namespace XooWL\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Xoo_Geolocation {

	private static $_instance;

	/**
	 * HTTPS endpoints for looking up the visitor IP when server variables are unavailable.
	 *
	 * @var array
	 */
	private $ip_lookup_apis = array(
		'ipify'  => 'http://api.ipify.org/',
		'ipecho' => 'http://ipecho.net/plain',
		'ident'  => 'http://ident.me',
		'tnedi'  => 'http://tnedi.me',
	);

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Get geolocation data for the current visitor.
	 *
	 * @param bool $from_cookie Whether to read cached cookie data first.
	 * @return array
	 */
	public function get_data( $from_cookie = true ) {

		if ( $from_cookie && isset( $_COOKIE['xoo_user_ip_data'] ) && ! empty( $_COOKIE['xoo_user_ip_data'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$data = json_decode( wp_unslash( $_COOKIE['xoo_user_ip_data'] ), true );
			$data = $this->sanitize_geolocation_data( $data );

			if ( is_array( $data ) ) {
				return $data;
			}
		}

		$ip_address = $this->get_ip_address();

		if( !$ip_address ){
			$ip_address = $this->get_external_ip_address();
		}

		$data = array(
			'countryCode' => '',
			'state'       => '',
			'city'        => '',
			'source'      => '',
		);

		if( $ip_address ){
			$data = array_merge( $data, self::geolocate_via_api( $ip_address ) );
		}

		$this->set_geolocation_cookie( $data );

		return $data;
	}

	/**
	 * Gets user country code.
	 *
	 * @return string
	 */
	public function get_country_code() {
		$data = $this->get_data();

		if ( isset( $data['countryCode'] ) ) {
			return $data['countryCode'];
		}

		return '';
	}

	/**
	 * Gets user country phone code.
	 *
	 * @param string $country_code Country code.
	 * @return string
	 */
	public function get_phone_code( $country_code = '' ) {

		if ( ! $country_code ) {
			$country_code = $this->get_country_code();
		}

		$phone_codes = (array) include XOO_FW_DIR.'/countries/phone.php';

		if ( isset( $phone_codes[ $country_code ] ) ) {
			return $phone_codes[ $country_code ];
		}

		return '';
	}



	/**
	 * Get the current user's IP address from server variables.
	 *
	 * @return string
	 */
	public static function get_ip_address() {

		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2
			// Make sure we always only send through the first IP in the list which should always be the client IP.
			$ip = trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );
			// Account for the '<IPv4 address>:<port>', '[<IPv6>]' and '[<IPv6>]:<port>' cases, removing the port.
			// The regular expression is oversimplified on purpose, later 'rest_is_ip_address' will do the actual IP address validation.
			$ip = preg_replace( '/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\:.*|\[([^]]+)\].*/', '$1$2', $ip );
			$ip = (string) rest_is_ip_address( $ip );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			// Make sure we always only send through the first IP in the list which should always be the client IP.
			$ip = trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) ) );
			$ip = (string) rest_is_ip_address( $ip );
		}
		else{
			$ip = '';
		}

		$localhostCheck = array(
		    '127.0.0.1',
		    '::1'
		);

		$ip = in_array( $ip , $localhostCheck ) ? '' : $ip;

		return $ip;
	}


	/**
	 * Get the server's public IP address.
	 *
	 * Intended only as a geolocation fallback for localhost or private
	 * network environments. Do not use this for authentication, rate
	 * limiting, or security decisions.
	 *
	 * @return string
	 */
	public function get_external_ip_address() {


		$transient_name = 'xoo_external_ip_' . md5( $this->get_ip_address() );

		$external_ip = get_transient( $transient_name );

		if ( false !== $external_ip ) {
			return $external_ip;
		}

		foreach ( $this->ip_lookup_apis as $service_url ) {

			$response = wp_safe_remote_get(
				$service_url,
				array(
					'timeout'   => 2,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$body = trim( wp_remote_retrieve_body( $response ) );

			if ( rest_is_ip_address( $body ) ) {
				set_transient( $transient_name, $body, DAY_IN_SECONDS );

				return $body;
			}
		}

		return '';
	}


	/**
	 * Geolocate an IP address.
	 *
	 * @param string $ip_address IP address.
	 * @return array
	 */
	private static function geolocate_via_api( $ip_address ) {

		$location = array(
			'countryCode' => '',
			'state'       => '',
			'city'        => '',
			'source'      => '',
		);

		if ( ! rest_is_ip_address( $ip_address ) ) {
			$location['source'] = 'none';
			return $location;
		}

		if ( class_exists( 'WooCommerce' ) && class_exists( 'WC_Geolocation' ) ) {

			$wc_location = \WC_Geolocation::geolocate_ip( $ip_address );

			if ( ! empty( $wc_location['country'] ) ) {
				$location['countryCode'] = sanitize_text_field( $wc_location['country'] );
				$location['state']       = isset( $wc_location['state'] ) ? sanitize_text_field( $wc_location['state'] ) : '';
				$location['city']        = isset( $wc_location['city'] ) ? sanitize_text_field( $wc_location['city'] ) : '';
				$location['source']      = 'woocommerce';

				return $location;
			}
		}

		if ( ! apply_filters( 'xoo_fw_use_external_geolocation', true ) ) {
			$location['source'] = 'none';
			return $location;
		}

		$api_url  = 'https://ip-api.com/json/' . rawurlencode( $ip_address );
		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'   => 5,
				'sslverify' => true,
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( is_array( $data ) && ! empty( $data['countryCode'] ) ) {
				$location['countryCode'] = sanitize_text_field( $data['countryCode'] );
				$location['state']       = isset( $data['region'] ) ? sanitize_text_field( $data['region'] ) : '';
				$location['city']        = isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '';
				$location['source']      = 'ip-api';

				return $location;
			}
		}

		$location['source'] = 'none';

		return $location;
	}

	/**
	 * Sanitize geolocation data from cookies or API responses.
	 *
	 * @param mixed $data Raw geolocation data.
	 * @return array|false
	 */
	private function sanitize_geolocation_data( $data ) {

		if ( ! is_array( $data ) ) {
			return false;
		}

		return array(
			'countryCode' => isset( $data['countryCode'] ) ? sanitize_text_field( $data['countryCode'] ) : '',
			'state'       => isset( $data['state'] ) ? sanitize_text_field( $data['state'] ) : '',
			'city'        => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
			'source'      => isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : '',
		);
	}

	/**
	 * Cache geolocation data in a cookie. IP addresses are never stored.
	 *
	 * @param array $data Geolocation data.
	 */
	private function set_geolocation_cookie( $data ) {

		$data = $this->sanitize_geolocation_data( $data );

		if ( ! is_array( $data ) ) {
			return;
		}

		$cookie_value = wp_json_encode( $data );
		$expires      = time() + DAY_IN_SECONDS;
		$path         = COOKIEPATH ? COOKIEPATH : '/';
		$domain       = COOKIE_DOMAIN;
		$secure       = is_ssl();
		$httponly     = true;

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				'xoo_user_ip_data',
				$cookie_value,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => $secure,
					'httponly' => $httponly,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( 'xoo_user_ip_data', $cookie_value, $expires, $path, $domain, $secure, $httponly );
		}
	}
}

/**
 * Returns the geolocation instance.
 *
 * @return Xoo_Geolocation
 */
function xoo_geolocate() {
	return Xoo_Geolocation::get_instance();
}
