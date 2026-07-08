<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class WFFN_Remote_Template_Importer
 *
 * @package WFFN
 * @author XlPlugins
 */
if ( ! class_exists( 'WFFN_Remote_Template_Importer' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Remote_Template_Importer {

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
				'license-expired'           => __( 'This site does not have access to template library as license has expired. To get access renew the license. For any further help contact support.', 'funnel-builder' ),
				'invalid-step'              => sprintf( __( 'Please check if you have valid license key. Try activating the license again. For any further help contact support. <a href=%s target="_blank">Go to Licenses</a>', 'funnel-builder' ), esc_url( admin_url( 'admin.php?page=bwf&path=/settings' ) ) ),
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
		 * @param $step
		 * @param $template_slug
		 * @param $builder
		 * @param $steps
		 *
		 * @return array|false|mixed|string
		 */
		public function get_remote_template( $step, $template_slug, $builder, $steps = array() ) {

			if ( empty( $step ) || empty( $template_slug ) || empty( $builder ) ) {
				return '';
			}

			$license = $this->get_license_key();

			$requestBody = array(
				'step'            => $step,
				'domain'          => $this->get_domain(),
				'license'         => $license,
				'template'        => $template_slug,
				'builder'         => $builder,
				'version'         => 4,
				'builder_version' => WFFN_Common::get_builder_version( $builder ),
				'locale'          => get_locale(),
			);

			if ( 'elementor' === $builder && class_exists( 'WFFN_Common' ) ) {
				$requestBody['elementor_container'] = WFFN_Common::is_elementor_container_active() ? 'active' : 'inactive';
			}

			if ( 'funnel' === $step && count( $steps ) > 0 ) {
				$requestBody['steps'] = $steps;
			}

			$requestBody = wp_json_encode( $requestBody );

			$endpoint_url = $this->get_template_api_url();

			require_once WFFN_PLUGIN_DIR . '/includes/class-wffn-content-validator.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
			WFFN_Content_Validator::register_http_guard();
			WFFN_Content_Validator::begin_import();
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
			WFFN_Content_Validator::end_import();

			BWF_Logger::get_instance()->log( 'Import $requestBody: ' . print_r( $requestBody, true ), 'wffn_template_import' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			if ( $response instanceof WP_Error ) {
				if ( is_object( $response ) && $response->errors ) {
					if ( is_array( $response->errors ) && $response->errors['http_request_failed'] ) {
						return array( 'error' => isset( $response->errors['http_request_failed'][0] ) ? $response->errors['http_request_failed'] : __( 'HTTP Request Failed', 'funnel-builder' ) );
					}
				}

				return false;
			}
			$body = wp_remote_retrieve_body( $response );
			if ( strlen( $body ) > 5 * 1024 * 1024 ) {
				return array( 'error' => __( 'Template response is too large to process.', 'funnel-builder' ) );
			}

			$response = json_decode( $body, true );
			if ( ! is_array( $response ) ) {
				return array( 'error' => __( 'It seems we are unable to import this template from the cloud library. Please contact support.', 'funnel-builder' ) );
			}

			$alien_count = WFFN_Content_Validator::sanitize_response_urls( $response );
			if ( $alien_count >= 5 ) {
				return array( 'error' => __( 'We are unable to import this template. Please contact support.', 'funnel-builder' ) );
			}

			if ( isset( $response['error'] ) ) {
				return array( 'error' => self::get_error_message( $response['error'] ) );
			}

			if ( 'funnel' !== $step && ! isset( $response[ $step ] ) ) {
				return array( 'error' => __( 'No Template found', 'funnel-builder' ) );
			}

			if ( 'funnel' === $step ) {
				$funnels = array();
				foreach ( $steps as $type => $template ) {

					if ( isset( $response[ $type ] ) ) {
						$data = array(
							'type'          => $type,
							'title'         => $this->get_step_title( $type ),
							'template'      => $template,
							'template_type' => $builder,
						);

						if ( 'wc_upsells' === $type ) {
							$data['status']          = true;
							$data['meta']['steps'][] = array(
								'type'          => 'upsell',
								'title'         => __( 'Offer', 'funnel-builder' ),
								'template'      => $template,
								'template_type' => $builder,
								'state'         => true,
								'meta'          => array(
									'_wfocu_setting' => array(
										'products'       => array(),
										'fields'         => array(),
										'template'       => '',
										'template_group' => '',
										'settings'       => array(),
									),
								),
							);
						}
						if ( 'wc_checkout' === $type && isset( $response['wc_order_bump'] ) ) {

							$order_bump_json = $response['wc_order_bump'];
							$order_bump_data = json_decode( $order_bump_json, true );
							if ( is_array( $order_bump_data ) ) {
								$order_bump_data['products'] = array();
							}
							$data['meta']['substeps'] = array( 'wc_order_bump' => array( $order_bump_data ) );

						}

						$funnels['steps'][] = $data;

						$safe_builder  = sanitize_file_name( $builder );
						$safe_type     = sanitize_file_name( $type );
						$safe_template = sanitize_file_name( $template );
						$directory     = $safe_builder . '/' . $safe_type . '/' . $safe_template;

						wp_mkdir_p( WFFN_TEMPLATE_UPLOAD_DIR . '/' . $safe_builder . '/' . $safe_type );

						$template_path = WFFN_TEMPLATE_UPLOAD_DIR . $directory . '.json';

						$real_upload_dir = realpath( WFFN_TEMPLATE_UPLOAD_DIR );
						$real_parent     = realpath( dirname( $template_path ) );
						if ( false === $real_upload_dir || false === $real_parent || 0 !== strpos( $real_parent, $real_upload_dir ) ) {
							continue;
						}

						$template_content = $response[ $type ];
						if ( WFFN_Content_Validator::contains_php_code( $template_content ) ) {
							BWF_Logger::get_instance()->log( 'Blocked template with PHP code: ' . $safe_template, 'wffn_template_import' );
							continue;
						}

						global $wp_filesystem;
						if ( ! $wp_filesystem ) {
							require_once ABSPATH . 'wp-admin/includes/file.php';
							WP_Filesystem();
						}
						$wp_filesystem->put_contents( $template_path, $template_content, FS_CHMOD_FILE );

					}
				}

				if ( 0 === count( $funnels ) ) {
					return array( 'error' => __( 'No Template found', 'funnel-builder' ) );
				}

				return array( $funnels );
			}

			return $response[ $step ];
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

				if ( is_array( $active_plugins ) && in_array( WFFN_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFFN_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) {
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
		public function get_license_key( $additional_info = false ) {
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
					$woofunnels_data = get_blog_option( get_network()->site_id, 'woofunnels_plugins_info', array() );
				} else {
					$woofunnels_data = get_option( 'woofunnels_plugins_info', array() );
				}
			} else {
				$woofunnels_data = get_option( 'woofunnels_plugins_info' );
			}

			if ( ! is_array( $woofunnels_data ) || 0 === count( $woofunnels_data ) || ! defined( 'WFFN_PRO_PLUGIN_BASENAME' ) ) {
				return false;
			}

			$plugin_hash = sha1( WFFN_PRO_PLUGIN_BASENAME );

			/** Not present */
			if ( ! isset( $woofunnels_data[ $plugin_hash ] ) ) {
				return false;
			}

			if ( true === $additional_info ) {
				return $woofunnels_data[ $plugin_hash ];
			}

			return $woofunnels_data[ $plugin_hash ]['data_extra']['api_key'];
		}

		public function get_template_api_url() {
			return 'https://gettemplates.funnelkit.com/';
		}

		public function get_step_title( $type ) {
			$args = array(
				'landing'     => __( 'Landing Page', 'funnel-builder' ),
				'wc_checkout' => __( 'Checkout Page', 'funnel-builder' ),
				'wc_upsells'  => __( 'Upsells', 'funnel-builder' ),
				'wc_thankyou' => __( 'Thank you Page', 'funnel-builder' ),
				'optin'       => __( 'Optin', 'funnel-builder' ),
				'optin_ty'    => __( 'Optin Confirmation Page', 'funnel-builder' ),
			);
			if ( isset( $args[ $type ] ) ) {
				return $args[ $type ];
			}
		}
	}

	WFFN_Core::register( 'remote_importer', 'WFFN_Remote_Template_Importer' );
}
