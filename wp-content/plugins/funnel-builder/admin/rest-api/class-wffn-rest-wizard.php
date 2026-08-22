<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class WFFN_REST_Wizard
 *
 * * @extends WP_REST_Controller
 */
if ( ! class_exists( 'WFFN_REST_Wizard' ) ) {
	#[AllowDynamicProperties]
	class WFFN_REST_Wizard extends WP_REST_Controller {

		public static $_instance = null;

		/**
		 * Route base.
		 *
		 * @var string
		 */

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'wizard';

		public function __construct() {
			add_action( 'init', array( $this, 'suppress_warnings' ) );
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Register the routes for taxes.
		 */
		public function register_routes() {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/other-plugins',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'other_plugins' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
						'args'                => array(),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/optin-setup',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'setup_optin' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
						'args'                => array(
							'op_name'  => array(
								'description'       => __( 'Get optin name', 'funnel-builder' ),
								'type'              => 'string',
								'validate_callback' => 'rest_validate_request_arg',
							),
							'op_email' => array(
								'description'       => __( 'Get optin email', 'funnel-builder' ),
								'type'              => 'string',
								'validate_callback' => 'rest_validate_request_arg',
							),
						),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/optin-track',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'track_optin' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);

			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/get-steps-data',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'maybe_update_steps_data' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				)
			);
		}

		public function get_read_api_permission_check() {
			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'read' );
		}

		public function get_write_api_permission_check() {
			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
		}

		public function activate_builder( $request ) {

			if ( ! function_exists( 'activate_plugin' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$resp = array(
				'status'  => false,
				'message' => __( 'Something went wrong. Please try again', 'funnel-builder' ),
			);

			$plugin_init     = $request->get_param( 'init' );
			$plugin_slug     = $request->get_param( 'slug' );
			$plugin_status   = $request->get_param( 'status' );
			$default_builder = $request->get_param( 'default_builder' );
			$plugin_init     = isset( $plugin_init ) ? $plugin_init : '';
			$plugin_slug     = isset( $plugin_slug ) ? $plugin_slug : '';
			$plugin_status   = isset( $plugin_status ) ? $plugin_status : '';
			$default_builder = isset( $default_builder ) ? $default_builder : '';

			try {
				$activate = '';

				if ( $plugin_init === '' || $plugin_slug === '' ) {
					return rest_ensure_response( $resp );
				}

				if ( ! function_exists( 'activate_plugin' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				if ( $plugin_status === 'install' && $plugin_slug !== '' ) {
					$install_plugin = WFFN_Common::install_plugin( $plugin_slug );
					if ( isset( $install_plugin['status'] ) && $install_plugin['status'] === false ) {
						return rest_ensure_response( $install_plugin );
					}
				}

				$activate = activate_plugin( $plugin_init, '', false, true );

				// Silent activation skips WooCommerce's own installer; run it explicitly.
				WFFN_Common::maybe_install_woocommerce( $plugin_init );

				if ( '' !== $default_builder && ( ! is_wp_error( $activate ) || $plugin_status === 'activated' ) ) {
					$get_config                             = get_option( 'bwf_gen_config', true );
					$get_config['default_selected_builder'] = $default_builder;
					$general_settings                       = BWF_Admin_General_Settings::get_instance();

					$general_settings->update_global_settings_fields( $get_config );
				}

				if ( is_wp_error( $activate ) ) {
					$resp = array(
						'status'  => false,
						'message' => $activate->get_error_message(),
						'slug'    => $plugin_slug,
					);
				} else {
					$resp = array(
						'status' => true,
						'slug'   => $plugin_slug,
					);
				}
			} catch ( Exception | Error $e ) {
				$resp = array(
					'status' => true,
					'slug'   => $plugin_slug,
				);
			}

			return rest_ensure_response( $resp );
		}

		public function other_plugins() {

			if ( ! function_exists( 'activate_plugin' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$resp = array(
				'status'  => false,
				'message' => __( 'Something went wrong. Please try again', 'funnel-builder' ),
			);

			try {
				$plugins = array(
					array(
						'slug'   => 'woocommerce',
						'init'   => 'woocommerce/woocommerce.php',
						'status' => WFFN_Common::get_plugin_status( 'woocommerce/woocommerce.php' ),
					),
					array(
						'slug'   => 'wp-marketing-automations',
						'init'   => 'wp-marketing-automations/wp-marketing-automations.php',
						'status' => WFFN_Common::get_plugin_status( 'wp-marketing-automations/wp-marketing-automations.php' ),
					),
					array(
						'slug'   => 'cart-for-woocommerce',
						'init'   => 'cart-for-woocommerce/plugin.php',
						'status' => WFFN_Common::get_plugin_status( 'cart-for-woocommerce/plugin.php' ),
					),
				);
				if ( ! WFFN_Common::is_wc_square_active() ) {
					$plugins[] = array(
						'slug'   => 'funnelkit-stripe-woo-payment-gateway',
						'init'   => 'funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php',
						'status' => WFFN_Common::get_plugin_status( 'funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php' ),
					);
				}
				if ( WFFN_Common::is_wc_square_active() ) {
					$plugins[] = array(
						'slug'   => 'funnelkit-payment-gateway-square-for-woocommerce',
						'init'   => 'funnelkit-payment-gateway-square-for-woocommerce/funnelkit-square.php',
						'status' => WFFN_Common::get_plugin_status( 'funnelkit-payment-gateway-square-for-woocommerce/funnelkit-square.php' ),
					);
				}

				if ( ! function_exists( 'activate_plugin' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				foreach ( $plugins as $plugin ) {
					$plugin_init   = $plugin['init'];
					$plugin_slug   = $plugin['slug'];
					$plugin_status = $plugin['status'];
					if ( $plugin_status === 'install' && $plugin_slug !== '' ) {
						$install_plugin = WFFN_Common::install_plugin( $plugin_slug );
						if ( isset( $install_plugin['status'] ) && $install_plugin['status'] === false ) {
							return rest_ensure_response( $install_plugin );
						}
					}
					$activate = activate_plugin( $plugin_init, '', false, true );

					if ( 'woocommerce/woocommerce.php' === $plugin_init ) {
						update_option( 'bwf_needs_rewrite', 'yes', true );
						// Silent activation skipped WooCommerce's installer — run it now so its
						// tables/pages exist before the follow-up get-steps-data request fires.
						WFFN_Common::maybe_install_woocommerce( $plugin_init );
					}

					if ( is_wp_error( $activate ) ) {
						$resp = array(
							'status'  => false,
							'message' => $activate->get_error_message(),
							'slug'    => $plugin_slug,
						);

						return rest_ensure_response( $resp );
					}
				}
			} catch ( Exception | Error $e ) {

			}

			$resp = array(
				'status' => true,
				'slug'   => '',
				'api'    => 'get-steps-data',
			);

			return rest_ensure_response( $resp );
		}

		public function maybe_update_steps_data() {
			$resp = array(
				'status'  => false,
				'message' => __( 'Something went wrong. Please try again', 'funnel-builder' ),
			);

			if ( ! class_exists( 'WFFN_Common' ) ) {
				return $resp;
			}

			// Guard against querying a half-installed WooCommerce: if it is active but its
			// tables are missing, finish the install first, then bail with the retry-tolerant
			// default response rather than surfacing raw "Table … doesn't exist" errors.
			if ( wffn_is_wc_active() && ! wffn_is_wc_ready() ) {
				WFFN_Common::maybe_install_woocommerce( 'woocommerce/woocommerce.php' );

				if ( ! wffn_is_wc_ready() ) {
					return rest_ensure_response( $resp );
				}
			}

			$substeps_data            = WFFN_Common::get_substeps_data();
			$substeps_data['substep'] = true;

			$resp = array(
				'status'  => true,
				'steps'   => WFFN_Common::get_steps_data(),
				'substep' => $substeps_data,
			);

			return rest_ensure_response( $resp );
		}

		public function setup_optin( $request ) {
			$resp = array(
				'status'  => false,
				'message' => __( 'Something went wrong. Please try again', 'funnel-builder' ),
			);

			$op_email = $request->get_param( 'op_email' );

			$op_email = isset( $op_email ) ? trim( $op_email ) : '';

			if ( $op_email !== '' ) {

				if ( ! is_email( $op_email ) ) {
					$resp['message'] = __( 'Please enter a valid email address', 'funnel-builder' );

					return rest_ensure_response( $resp );
				}

				$api_params = array(
					'action' => 'woofunnelsapi_email_optin',
					'data'   => array(
						'email'  => $op_email,
						'site'   => home_url(),
						'locale' => get_locale(),
					),
				);

				$request_args = WooFunnels_API::get_request_args(
					array(
						'timeout' => 0.5, //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'sslverify'   => true,
					'body'        => urlencode_deep( $api_params ),
					)
				);

				/**
				 * We do not need to track the result of the call, simply move forward and show success to the user
				 */
				wp_remote_post( WooFunnels_API::get_api_url( WooFunnels_API::$woofunnels_api_url ), $request_args );

				update_option( 'bwf_is_opted_email', 'yes', true );
				update_option( 'bwf_is_opted_data', array( 'email' => $op_email ), true );
				update_option( '_wffn_onboarding_completed', true );
				delete_transient( '_wc_activation_redirect' );

				$resp = array(
					'status' => true,
				);

			}

			return rest_ensure_response( $resp );
		}

		public function track_optin() {

			WooFunnels_optIn_Manager::Allow_optin( true );

			$resp = array(
				'status' => true,
			);

			return rest_ensure_response( $resp );
		}

		/**
		 * Avoid error and db error in wizard api
		 *
		 * @return void
		 */
		function suppress_warnings() {
			$path        = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'path' ) ?? '' ) );
			$request_uri = sanitize_text_field( wp_unslash( filter_input( INPUT_SERVER, 'REQUEST_URI' ) ?? '' ) );

			// Decode before matching so every wizard route is caught on both permalink
			// styles: pretty URLs carry it plainly (/wp-json/funnelkit-app/wizard/…), while
			// plain ones send it encoded as ?rest_route=%2Ffunnelkit-app%2Fwizard%2F…
			$request_uri = '' !== $request_uri ? rawurldecode( $request_uri ) : '';

			if ( '/user-setup' === $path || ( '' !== $request_uri && false !== strpos( $request_uri, 'funnelkit-app/wizard' ) ) ) {
				global $wpdb;
				$wpdb->hide_errors();
				$wpdb->suppress_errors();
				if ( ! function_exists( 'set_error_handler' ) ) {
					return;
				}
				// Scope the handler to E_WARNING only (was a blanket handler) so genuine
				// errors/notices later in the request still surface; the handler stays
				// installed for the wizard request by design — this is a band-aid layered
				// under the real fix (synchronous WooCommerce install), not a bounded read.
				set_error_handler( //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Intentional, scoped to the wizard request; see note above.
					function () {
						return true;
					},
					E_WARNING
				);
			}
		}
	}


	if ( ! function_exists( 'wffn_rest_wizard' ) ) {
		function wffn_rest_wizard() {  //@codingStandardsIgnoreLine
			return WFFN_REST_Wizard::get_instance();
		}
	}

	wffn_rest_wizard();
}
