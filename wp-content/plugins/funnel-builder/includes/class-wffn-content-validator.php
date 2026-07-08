<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFFN_Content_Validator' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Content_Validator {

		private static $guard_registered = false;

		public static function contains_php_code( $content ) {
			if ( ! is_string( $content ) ) {
				return false;
			}

			return (bool) preg_match( '/<\?(php|=|\s)/i', $content );
		}

		public static function contains_dangerous_tags( $content ) {
			if ( ! is_string( $content ) ) {
				return false;
			}

			if ( preg_match( '/<(script|iframe|object|embed)[\s>]/i', $content ) ) {
				return true;
			}

			if ( preg_match( '/\bon(load|error|click|mouseover|mouseout|focus|blur|submit|change|input|keydown|keyup|keypress)\s*=/i', $content ) ) {
				return true;
			}

			return false;
		}

		public static function validate_image_mime( $decoded_bytes ) {
			if ( empty( $decoded_bytes ) ) {
				return false;
			}
			$finfo         = new finfo( FILEINFO_MIME_TYPE );
			$detected_mime = $finfo->buffer( $decoded_bytes );
			$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' );

			return in_array( $detected_mime, $allowed_mimes, true );
		}

		public static function sanitize_meta_keys( $meta_data, $allowed_keys = array() ) {
			if ( ! is_array( $meta_data ) ) {
				return array();
			}

			if ( empty( $allowed_keys ) ) {
				$allowed_keys = self::get_default_allowed_meta_keys();
			}

			$sanitized = array();
			foreach ( $meta_data as $key => $value ) {
				if ( in_array( $key, $allowed_keys, true ) ) {
					$sanitized[ $key ] = $value;
				}
			}

			return $sanitized;
		}

		public static function get_default_allowed_meta_keys() {
			$keys = array(
				'_wp_page_template',
				'_thumbnail_id',
				'_elementor_data',
				'_elementor_edit_mode',
				'_elementor_version',
				'_et_pb_use_builder',
			);

			return apply_filters( 'wffn_allowed_import_meta_keys', $keys );
		}

		public static function validate_elementor_content( $content ) {
			if ( ! is_array( $content ) ) {
				return $content;
			}
			array_walk_recursive( $content, array( __CLASS__, 'sanitize_element_value' ) );

			return $content;
		}

		public static function sanitize_element_value( &$value ) {
			if ( ! is_string( $value ) ) {
				return;
			}
			if ( self::contains_php_code( $value ) ) {
				$value = '';
			}
			if ( self::contains_dangerous_tags( $value ) ) {
				$value = wp_kses_post( $value );
			}
		}

		public static function validate_bricks_content( $elements ) {
			if ( ! is_array( $elements ) ) {
				return $elements;
			}
			array_walk_recursive(
				$elements,
				function ( &$value ) {
					if ( ! is_string( $value ) ) {
						return;
					}
					if ( WFFN_Content_Validator::contains_php_code( $value ) ) {
						$value = '';
					}
					if ( WFFN_Content_Validator::contains_dangerous_tags( $value ) ) {
						$value = wp_kses_post( $value );
					}
				}
			);

			return $elements;
		}

		/**
		 * Register the scoped pre_http_request guard. Safe to call multiple times — registers once.
		 */
		public static function register_http_guard() {
			if ( self::$guard_registered ) {
				return;
			}
			add_filter( 'pre_http_request', array( __CLASS__, 'pre_http_request_guard' ), PHP_INT_MAX, 3 );
			self::$guard_registered = true;
		}

		public static function begin_import() {
			$GLOBALS['wffn_import_in_progress'] = true;
		}

		public static function end_import() {
			$GLOBALS['wffn_import_in_progress'] = false;
		}

		/**
		 * Allowlist of image CDN hosts used in template JSON responses.
		 */
		public static function get_allowed_image_hosts() {
			return array(
				'woofunnels.s3.us-east-1.amazonaws.com',
				'woofunnels.s3.amazonaws.com',
				'd3ldyx3r2ad3ic.cloudfront.net',
				'www.w3.org',
				'youtu.be',
				'www.youtube.com',
				'templates-elementor.funnelswp.com',
				'vimeo.com',
				'videopress.com',
				'dailymotion.com',
			);
		}

		/**
		 * Canonical allowlist for all import HTTP requests — template API + image CDNs.
		 * Used by the pre_http_request guard to cover both fetch types.
		 */
		public static function get_allowed_import_hosts() {
			return apply_filters(
				'wffn_allowed_import_hosts',
				array_merge(
					array(
						'gettemplates.funnelkit.com',
						'templates.funnelkit.com',
					),
					self::get_allowed_image_hosts()
				)
			);
		}

		/**
		 * Walk a decoded template response array and scan every string value for HTTP
		 * URLs — both standalone values and URLs embedded inside larger strings (e.g.
		 * Divi5 block content). Any URL whose host is not in the image CDN allowlist
		 * is stripped and counted. Caller should abort on count >= 1 (zero tolerance).
		 *
		 * @param array $data Decoded JSON response array, passed by reference.
		 * @return int Number of alien URLs stripped.
		 */
		public static function sanitize_response_urls( array &$data ) {
			$allowed_hosts = array_map(
				static function ( $h ) {
					return strtolower( rtrim( (string) $h, '.' ) );
				},
				self::get_allowed_image_hosts()
			);

			$alien_count = 0;

			array_walk_recursive(
				$data,
				function ( &$value, $key ) use ( $allowed_hosts, &$alien_count ) {
					if ( ! is_string( $value ) || false === strpos( $value, 'http' ) ) {
						return;
					}

					// Extract every http(s) URL embedded anywhere in the string.
					if ( ! preg_match_all( '#https?://[^\s\'"<>\\\\]+#i', $value, $matches ) ) {
						return;
					}

					foreach ( $matches[0] as $url ) {
						$host = strtolower( rtrim( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' ) );
						if ( empty( $host ) || in_array( $host, $allowed_hosts, true ) ) {
							continue;
						}

						$value = str_replace( $url, '', $value );
						$alien_count++;
					}
				}
			);

			return $alien_count;
		}

		/**
		 * Validate a URL before dispatching an import request.
		 * Enforces HTTPS, port 443, no userinfo, and exact host allowlist match.
		 *
		 * @param string $url
		 * @return bool
		 */
		public static function validate_import_url( $url ) {
			$scheme   = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host     = wp_parse_url( $url, PHP_URL_HOST );
			$port     = wp_parse_url( $url, PHP_URL_PORT );
			$userinfo = wp_parse_url( $url, PHP_URL_USER );

			if ( 'https' !== $scheme || empty( $host ) ) {
				return false;
			}
			if ( ! empty( $userinfo ) ) {
				return false;
			}
			if ( ! empty( $port ) && 443 !== (int) $port ) {
				return false;
			}

			$host          = strtolower( rtrim( (string) $host, '.' ) );
			$allowed_hosts = array_map(
				static function ( $h ) {
					return strtolower( rtrim( (string) $h, '.' ) );
				},
				self::get_allowed_import_hosts()
			);

			return in_array( $host, $allowed_hosts, true );
		}

		/**
		 * pre_http_request callback — hard-blocks any outgoing request while an import is
		 * in progress unless it passes the same HTTPS + allowlist checks.
		 * Registered at PHP_INT_MAX so it runs last, after all plugins, as the final gate before transport dispatch.
		 *
		 * @param false|array|WP_Error $pre
		 * @param array                $args
		 * @param string               $url
		 * @return false|array|WP_Error
		 */
		public static function pre_http_request_guard( $pre, $args, $url ) {
			if ( empty( $GLOBALS['wffn_import_in_progress'] ) ) {
				return $pre;
			}

			$scheme   = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host     = wp_parse_url( $url, PHP_URL_HOST );
			$port     = wp_parse_url( $url, PHP_URL_PORT );
			$userinfo = wp_parse_url( $url, PHP_URL_USER );

			if ( 'https' !== $scheme ) {
				return new WP_Error( 'wffn_import_blocked', 'Import blocked: only HTTPS allowed.' );
			}
			if ( ! empty( $userinfo ) ) {
				return new WP_Error( 'wffn_import_blocked', 'Import blocked: userinfo not allowed in URL.' );
			}
			if ( ! empty( $port ) && 443 !== (int) $port ) {
				return new WP_Error( 'wffn_import_blocked', 'Import blocked: non-443 port.' );
			}

			$host          = strtolower( rtrim( (string) $host, '.' ) );
			$allowed_hosts = array_map(
				static function ( $h ) {
					return strtolower( rtrim( (string) $h, '.' ) );
				},
				self::get_allowed_import_hosts()
			);

			if ( ! in_array( $host, $allowed_hosts, true ) ) {
				return new WP_Error( 'wffn_import_blocked', 'Import blocked: host not in allowlist.' );
			}

			return $pre;
		}
	}
}
