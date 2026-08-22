<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class WFFN_REST_Licenses
 *
 * License activation/deactivation endpoints. Relocated out of the free plugin
 * per the WP.org review -- no license or update-checker code may ship in the
 * .org zip -- so this controller lives with the license classes it drives.
 *
 * * @extends WP_REST_Controller
 */
if ( ! class_exists( 'WFFN_REST_Licenses' ) ) {
	#[AllowDynamicProperties]
	class WFFN_REST_Licenses extends WP_REST_Controller {

		public static $_instance = null;

		/**
		 * Route base.
		 *
		 * @var string
		 */

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'license';

		/**
		 * Registered by WFFN_Pro_Admin on rest_api_init, so this controller is
		 * only loaded on a REST request. Nothing to hook here.
		 */
		public function __construct() {
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
			/** License classes ship with the premium plugin; without them these endpoints cannot operate */
			if ( ! class_exists( 'WooFunnels_licenses' ) || ! class_exists( 'WooFunnels_License_check' ) ) {
				return;
			}
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/',
				array(
					'args' => array(
						'action' => array(
							'description' => __( 'Unique tab for the resource.', 'funnel-builder-pro' ),
							'type'        => 'string',
							'required'    => true,
						),
						'key'    => array(
							'description' => __( 'Unique tab for the resource.', 'funnel-builder-pro' ),
							'type'        => 'string',
							'required'    => true,
						),
						'name'   => array(
							'description' => __( 'Unique tab for the resource.', 'funnel-builder-pro' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'woofunnels_Licenses' ),
						'permission_callback' => array( $this, 'get_write_api_permission_check' ),

					),
				)
			);
		}

		public function get_write_api_permission_check() {
			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'write' );
		}

		public function woofunnels_Licenses( $request ) {
			$action      = $request['action'];
			$key         = $request['key'];
			$plugin_name = $request['name'];

			if ( empty( $key ) || empty( $plugin_name ) ) {

				return rest_ensure_response(
					array(
						'code'  => 400,
						'error' => __( 'Please input correct license key', 'funnel-builder-pro' ),
					)
				);
			}
			$resp                  = $this->process_license_call( $plugin_name, $key, $action );
			$resp['name']          = $plugin_name;
			$License               = WooFunnels_licenses::get_instance();
			$License->plugins_list = null;
			$License->get_plugins_list();
			$resp['lev'] = WFFN_License_Config::get_instance()->get_config();

			return rest_ensure_response( $resp );
		}

		protected function process_license_call( $plugin_name, $key, $action ) {

			/** Activation & deactivation cannot succeed when the Pro folder slug differs from the canonical name — the remote license record is keyed by canonical basename. */
			if ( class_exists( 'WFFN_License_Admin_UI' ) && defined( 'WFFN_PRO_PLUGIN_BASENAME' ) && $plugin_name === sha1( WFFN_PRO_PLUGIN_BASENAME ) && true === WFFN_License_Admin_UI::is_pro_folder_renamed() ) {
				return array(
					'code'  => 400,
					'error' => WFFN_License_Admin_UI::get_folder_renamed_message( 'bwf-a-no-underline' ),
				);
			}

			/** Deactivate call */
			if ( 'deactivate' === $action ) {
				$result = $this->process_deactivation( $plugin_name );

				if ( ( isset( $result['deactivated'] ) && $result['deactivated'] === true ) || ( isset( $result['code'] ) && 100 === absint( $result['code'] ) ) ) {
					$msg = __( 'License deactivated successfully.', 'funnel-builder-pro' );

					return array(
						'code' => 200,
						'msg'  => $msg,

					);
				} else {
					$msg = is_array( $result['error'] ) && isset( $result['error'] ) ? $result['error'] : __( 'Invalid Request.', 'funnel-builder-pro' );

					return array(
						'code' => 400,
						'msg'  => $msg,
					);
				}
			}

			/** Activate call */
			if ( 'activate' === $action ) {
				$data = $this->process_activation( $plugin_name, $key );

				/**
				 * error: 103 - product mismatch
				 * error: 101 - No license in our db
				 * error: 999 - Expired
				 */
				if ( isset( $data['error'] ) && isset( $data['code'] ) && 105 === absint( $data['code'] ) ) {
					return array(
						'code'  => 400,
						'error' => __( 'The license key you\'re using is for a <strong>different product</strong>. Please double-check and enter the correct key for this product.', 'funnel-builder-pro' ),
					);
				} elseif ( isset( $data['error'] ) ) {
					return array(
						'code'  => 400,
						'error' => __( 'Sorry, we are unable to activate your license for this domain. Please contact support ', 'funnel-builder-pro' ),
					);

				}

				$license_data = '';
				if ( isset( $data['activated'] ) && true === $data['activated'] && isset( $data['data_extra'] ) ) {
					$license_data = $data['data_extra'];
				}

				$msg = __( 'License activated successfully.', 'funnel-builder-pro' );

				return array(
					'code'         => 200,
					'msg'          => $msg,
					'license_data' => $license_data,

				);
			}
		}

		protected function process_deactivation( $plugin ) {
			$instance   = new WooFunnels_License_check( $plugin );
			$get_config = $this->get_license_config( $plugin );

			$data = array(
				'plugin_slug' => $get_config['plugin'],
				'plugin_name' => $get_config['plugin'],
				'license_key' => $get_config['_data']['data_extra']['api_key'],
				'product_id'  => $get_config['plugin'],
				'version'     => $get_config['product_version'],
			);

			$instance->setup_data( $data );

			return $instance->deactivate_license();
		}

		protected function process_activation( $plugin, $api_key ) {
			$instance   = new WooFunnels_License_check( $plugin );
			$get_config = $this->get_license_config( $plugin );

			$data = array(
				'plugin_slug' => $get_config['plugin'],
				'plugin_name' => $get_config['plugin'],
				'license_key' => $api_key,
				'product_id'  => $get_config['plugin'],
				'version'     => $get_config['product_version'],
			);

			$instance->setup_data( $data );

			return $instance->activate_license();
		}

		protected function get_license_config( $key ) {
			$License = WooFunnels_licenses::get_instance();
			$list    = $License->get_data();

			if ( is_array( $list ) && count( $list ) ) {
				foreach ( $list as $license ) {

					if ( $license['product_file_path'] === $key ) {
						return $license;
					}
				}
			}

			return array();
		}
	}


}
