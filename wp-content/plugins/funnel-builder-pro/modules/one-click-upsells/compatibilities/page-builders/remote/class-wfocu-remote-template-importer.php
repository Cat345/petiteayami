<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFOCU_Remote_Template_Importer' ) ) {
	/**
	 * Class WFOCU_Remote_Template_Importer
	 *
	 * @package WFOCU
	 * @author XlPlugins
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Remote_Template_Importer {

		private static $instance = null;

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public static function get_error_message( $code ) {
			$messages = array(
				'license-or-domain-invalid' => __( 'This site does not have access to template library.  To get access activate the license. For any further help contact support.', 'funnel-builder' ),
				'license-not-provided'      => __( 'This site does not have access to template library.  To get access activate the license. For any further help contact support.', 'funnel-builder' ),
				'unauthorized-access'       => sprintf( __( 'Please check if you have valid license key. <a href=%s target="_blank">Go to Licenses</a>', 'woofunnels-aero' ), esc_url( admin_url( 'admin.php?page=woofunnels' ) ) ),
				'template-not-exists'       => __( 'Template not available in cloud library. Please contact support.', 'funnel-builder' ),
			);

			if ( isset( $messages[ $code ] ) ) {
				return $messages[ $code ];
			}

			return $code;
		}

		/**
		 * Import template remotely.
		 *
		 * @return mixed
		 */
		public function get_remote_template( $template_id, $builder ) {
			if ( empty( $template_id ) || empty( $builder ) ) {
				return '';
			}

			$funnel_step = 'wc_upsells';

			$safe_builder  = sanitize_file_name( $builder );
			$safe_template = sanitize_file_name( $template_id );

			$template_file_path = $safe_builder . '/' . $funnel_step . '/' . $safe_template;
			$defined_wffn       = defined( 'WFFN_TEMPLATE_UPLOAD_DIR' );
			$full_path          = WFFN_TEMPLATE_UPLOAD_DIR . $template_file_path . '.json';
			$file_exist         = ( $defined_wffn ) ? file_exists( $full_path ) : false;

			if ( $defined_wffn && $file_exist ) {
				$real_upload_dir = realpath( WFFN_TEMPLATE_UPLOAD_DIR );
				$real_file_path  = realpath( $full_path );
				if ( false === $real_upload_dir || false === $real_file_path || 0 !== strpos( $real_file_path, $real_upload_dir ) ) {
					return '';
				}
				$content = file_get_contents( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
				wp_delete_file( $full_path );

				return WFOCU_Core()->template_loader->get_group( $builder )->handle_remote_import( $content );
			}

			$license = $this->get_license_key();

			$step = 'wc_upsells';

			if ( empty( $license ) && class_exists( 'WFFN_Pro_Core' ) ) {
				$license = WFFN_Pro_Core()->support->get_license_key();
			}
			$requestBody = array(
				'step'            => $step,
				'domain'          => $this->get_domain(),
				'license'         => $license,
				'template'        => $template_id,
				'builder'         => $builder,
				'builder_version' => WFOCU_Common::get_builder_version( $builder ),
				'locale'          => get_locale(),
			);

			if ( 'elementor' === $builder && class_exists( 'WFFN_Common' ) && method_exists( 'WFFN_Common', 'is_elementor_container_active' ) ) {
				$requestBody['elementor_container'] = WFFN_Common::is_elementor_container_active() ? 'active' : 'inactive';
			}

			$requestBody  = wp_json_encode( $requestBody );
			$endpoint_url = $this->get_template_api_url();

			require_once WFOCU_PLUGIN_DIR . '/includes/class-wfocu-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			WFOCU_Content_Validator::register_http_guard();
			WFOCU_Content_Validator::begin_import();
			$response = wp_remote_post(
				$endpoint_url,
				array(
					'body'               => $requestBody,
					'timeout'            => 30, //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'sslverify'          => true,
					'reject_unsafe_urls' => true,
					'redirection'        => 0,
					'headers'            => array(
						'content-type' => 'application/json',
					),
				)
			);
			WFOCU_Content_Validator::end_import();

			if ( $response instanceof WP_Error ) {
				return array( 'error' => __( 'Unable to import template', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			$body = wp_remote_retrieve_body( $response );
			if ( strlen( $body ) > 5 * 1024 * 1024 ) {
				return array( 'error' => __( 'Template response is too large to process.', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			$response = json_decode( $body, true );

			if ( ! is_array( $response ) ) {
				return array( 'error' => __( 'It seems we are unable to import this template from the cloud library. Please contact support.', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			$alien_count = WFOCU_Content_Validator::sanitize_response_urls( $response );
			if ( $alien_count >= 5 ) {
				return array( 'error' => __( 'We are unable to import this template. Please contact support.', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			if ( isset( $response['error'] ) ) {
				return array( 'error' => self::get_error_message( $response['error'] ) );
			}

			if ( ! isset( $response[ $step ] ) ) {
				return array( 'error' => __( 'No Template found', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			return WFOCU_Core()->template_loader->get_group( $builder )->handle_remote_import( $response[ $step ] );
		}

		public function get_domain() {
			global $sitepress;
			$domain = site_url();

			if ( isset( $sitepress ) && ! is_null( $sitepress ) ) {
				$default_language = $sitepress->get_default_language();
				$domain           = $sitepress->convert_url( $sitepress->get_wp_api()->get_home_url(), $default_language );
			}

			// Check if Polylang is active
			if ( function_exists( 'pll_default_language' ) && function_exists( 'pll_home_url' ) ) {
				// Get the default language
				$default_language = pll_default_language();
				// Get the home URL in the default language
				$domain = pll_home_url( $default_language );
			}

			/**
			 * Get woofunnels plugins data from the options
			 * consider multisite setups
			 */
			if ( is_multisite() ) {
				/**
				 * Check if sitewide installed, if yes then get the plugin info from primary site
				 */
				$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

				if ( is_array( $active_plugins ) && defined( 'WFFN_PRO_PLUGIN_BASENAME' ) && ( in_array( WFFN_PRO_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFFN_PRO_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) ) {
					$domain = get_site_url( get_network()->site_id );
				} elseif ( is_array( $active_plugins ) && in_array( WFOCU_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFOCU_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) {
					$domain = get_site_url( get_network()->site_id );
				}
			}
			$domain = str_replace( array( 'https://', 'http://' ), '', $domain );
			$domain = trim( $domain, '/' );

			return $domain;
		}

		/**
		 * Get license key.
		 *
		 * @return mixed
		 */
		public function get_license_key() {
			$licenseKey = false;
			/**
			 * Get woofunnels plugins data from the options
			 * consider multisite setups
			 */
			if ( is_multisite() ) {
				/**
				 * Check if sitewide installed, if yes then get the plugin info from primary site
				 */
				$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

				if ( is_array( $active_plugins ) && in_array( WFOCU_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFOCU_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) {
					$woofunnels_data = get_blog_option( get_network()->site_id, 'woofunnels_plugins_info', array() );
				} else {
					$woofunnels_data = get_option( 'woofunnels_plugins_info', array() );
				}
			} else {
				$woofunnels_data = get_option( 'woofunnels_plugins_info' );
			}
			if ( is_array( $woofunnels_data ) && count( $woofunnels_data ) > 0 && defined( 'WFOCU_PLUGIN_BASENAME' ) ) {

				foreach ( $woofunnels_data as $key => $license ) {
					if ( is_array( $license ) && isset( $license['activated'] ) && $license['activated'] && sha1( WFOCU_PLUGIN_BASENAME ) === $key ) {
						$licenseKey = $license['data_extra']['api_key'];
						break;
					}
				}
			}

			return $licenseKey;
		}


		public function get_template_api_url() {
			return 'https://gettemplates.funnelkit.com/';
		}
	}
}
