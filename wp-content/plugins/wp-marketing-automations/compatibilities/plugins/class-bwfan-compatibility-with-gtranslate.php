<?php

/**
 * WordPress gTranslate Plugin
 * https://gtranslate.io/
 *
 */
if ( ! class_exists( 'BWFAN_Compatibility_With_GTRANSLATE' ) ) {
	class BWFAN_Compatibility_With_GTRANSLATE {

		public function __construct() {
			add_filter( 'bwfan_enable_language_settings', array( $this, 'bwfan_add_gtranslate_language_settings' ) );
		}

		/**
		 * Get GTranslate language code from various sources
		 *
		 * @return string
		 */
		public static function get_gtranslate_language() {
			$lang = '';

			switch ( true ) {
				// Check if googtrans cookie is set
				case isset( $_COOKIE['googtrans'] ):
					$googtrans = bwf_clean( $_COOKIE['googtrans'] );
					$googtrans = explode( '/', $googtrans );
					$lang      = is_array( $googtrans ) ? end( $googtrans ) : '';
					break;

				// Check if HTTP_X_GT_LANG header is set
				case isset( $_SERVER['HTTP_X_GT_LANG'] ):
					$lang = bwf_clean( $_SERVER['HTTP_X_GT_LANG'] );
					break;

				// Check domain mapping if HTTP_HOST is set
				case isset( $_SERVER['HTTP_HOST'] ):
					$current_domain = bwf_clean( $_SERVER['HTTP_HOST'] );
					$gtranslate     = get_option( 'GTranslate' );
					$domain         = wp_parse_url( home_url(), PHP_URL_HOST );

					if ( $current_domain === $domain ) {
						$lang = isset( $gtranslate['default_language'] ) ? $gtranslate['default_language'] : 'en';
					} elseif ( isset( $gtranslate['custom_domains'] ) && ! empty( $gtranslate['custom_domains'] ) ) {
						$custom_domains_data = $gtranslate['custom_domains_data'];
						if ( is_string( $custom_domains_data ) ) {
							$custom_domains_data = json_decode( stripslashes( $custom_domains_data ), true );
						}

						if ( is_array( $custom_domains_data ) ) {
							$found_lang = array_search( $current_domain, $custom_domains_data, true );
							if ( ! empty( $found_lang ) ) {
								$lang = $found_lang;
							}
						}
					} elseif ( isset( $gtranslate['enterprise_version'] ) && $gtranslate['enterprise_version'] ) {
						$parts = explode( '.', $current_domain );
						if ( count( $parts ) > 2 ) {
							$potential_lang = $parts[0];

							// Check if the subdomain is a valid language code
							$enabled_languages = array();
							if ( isset( $gtranslate['fincl_langs'] ) ) {
								$enabled_languages = $gtranslate['fincl_langs'];
							} elseif ( isset( $gtranslate['incl_langs'] ) ) {
								$enabled_languages = $gtranslate['incl_langs'];
							}

							if ( in_array( $potential_lang, $enabled_languages, true ) ) {
								$lang = $potential_lang;
							}
						}
					}
					break;
			}

			return $lang;
		}

		/**
		 * Add GTranslate languages to FunnelKit Automation language settings
		 *
		 * @param array $settings Existing language settings array
		 *
		 * @return array Modified settings array with GTranslate languages added
		 */
		public function bwfan_add_gtranslate_language_settings( $settings ) {
			$gtranslate_options = get_option( 'gtranslate' );

			if ( empty( $gtranslate_options ) || ! isset( $gtranslate_options['incl_langs'] ) ) {
				return $settings;
			}

			$gtranslate_language_codes = $gtranslate_options['incl_langs'];

			$gtranslate_languages = [];
			foreach ( $gtranslate_language_codes as $code ) {
				$gtranslate_languages[ $code ] = locale_get_display_language( $code, $code ); // Native name;
			}

			if ( ! empty( $gtranslate_languages ) ) {
				$settings['lang_options'] = array_merge( isset( $settings['lang_options'] ) ? $settings['lang_options'] : [], $gtranslate_languages );
				$settings['enable_lang']  = 1;
			}

			return $settings;
		}

		/**
		 * Convert a URL to its language-specific domain equivalent
		 *
		 * @param string $url Original URL to be translated
		 * @param string $lang Language code to translate the URL to
		 *
		 * @return string URL with the appropriate language-specific domain or path
		 */
		public static function get_translated_domain_url( $url, $lang ) {
			$gtranslate_settings = get_option( 'GTranslate' );

			// Parse the URL
			$url_parts   = wp_parse_url( $url );
			$base_domain = $url_parts['host'];
			$scheme      = $url_parts['scheme'] ?? 'https';
			$path        = $url_parts['path'] ?? '';
			$query       = isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '';
			$fragment    = isset( $url_parts['fragment'] ) ? '#' . $url_parts['fragment'] : '';

			if ( isset( $gtranslate_settings['custom_domains'] ) && ! empty( $gtranslate_settings['custom_domains'] ) ) {

				$custom_domains_data = $gtranslate_settings['custom_domains_data'];
				$custom_domains_data = json_decode( stripslashes( $custom_domains_data ), true );

				// Check if language exists in custom domains data
				if ( is_array( $custom_domains_data ) && isset( $custom_domains_data[ $lang ] ) ) {
					$new_domain = $custom_domains_data[ $lang ];
					$url        = $scheme . '://' . $new_domain . $path . $query . $fragment;
				}
			} elseif ( isset( $gtranslate_settings['enterprise_version'] ) && ! empty( $gtranslate_settings['enterprise_version'] ) ) {
				$url = $scheme . '://' . $lang . '.' . $base_domain . $path . $query . $fragment;
			} elseif ( isset( $gtranslate_settings['pro_version'] ) && ! empty( $gtranslate_settings['pro_version'] ) ) {
				$url = $scheme . '://' . $base_domain . '/' . $lang . $path . $query . $fragment;
			}

			return $url;
		}
	}

	new BWFAN_Compatibility_With_GTRANSLATE();
}
