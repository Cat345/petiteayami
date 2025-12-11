<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'BWFABT_WooFunnels_Support' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_WooFunnels_Support {

		public static $_instance = null;
		/** Can't be change this further, as is used for license activation */
		public $full_name = '';
		public $is_license_needed = true;
		/**
		 * @var WooFunnels_License_check
		 */
		public $license_instance;
		protected $encoded_basename = '';

		public function __construct() {

			$this->encoded_basename = sha1( BWFABT_PLUGIN_BASENAME );
			$this->full_name        = BWFABT_FULL_NAME;

			add_filter( 'woofunnels_plugins_license_needed', array( $this, 'add_license_support' ), 10 );
			add_action( 'init', array( $this, 'init_licensing' ), 12 );
			add_action( 'woofunnels_licenses_submitted', array( $this, 'process_licensing_form' ) );
			add_action( 'woofunnels_deactivate_request', array( $this, 'maybe_process_deactivation' ) );
		}

		/**
		 * @return null|BWFABT_PLUGIN_BASENAME
		 */
		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}


		public function woofunnels_page() {
			if ( null !== filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW ) ) {
				WooFunnels_dashboard::$selected = 'licenses';
			}
			WooFunnels_dashboard::load_page();
		}

		/**
		 * License management helper function to create a slug that is friendly with edd
		 *
		 * @param type $name
		 *
		 * @return type
		 */
		public function slugify_module_name( $name ) {
			return preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $name ) ) );
		}

		public function add_license_support( $plugins ) {
			$status  = 'invalid';
			$renew   = 'Please Activate';
			$license = array(
				'key'     => '',
				'email'   => '',
				'expires' => '',
			);

			$plugins_in_database = WooFunnels_License_check::get_plugins();

			if ( is_array( $plugins_in_database ) && isset( $plugins_in_database[ $this->encoded_basename ] ) && count( $plugins_in_database[ $this->encoded_basename ] ) > 0 ) {
				$status  = 'active';
				$renew   = '';
				$license = array(
					'key'     => $plugins_in_database[ $this->encoded_basename ]['data_extra']['api_key'],
					'email'   => $plugins_in_database[ $this->encoded_basename ]['data_extra']['license_email'],
					'expires' => $plugins_in_database[ $this->encoded_basename ]['data_extra']['expires'],
				);
			}

			$plugins[ $this->encoded_basename ] = array(
				'plugin'            => $this->full_name,
				'product_version'   => BWFABT_VERSION,
				'product_status'    => $status,
				'license_expiry'    => $renew,
				'product_file_path' => $this->encoded_basename,
				'existing_key'      => $license,
			);

			return $plugins;
		}

		public function woofunnels_slugify_module_name( $name ) {
			return preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $name ) ) );
		}

		public function init_licensing() {
			if ( class_exists( 'WooFunnels_License_check' ) && $this->is_license_needed ) {
				$this->license_instance = new WooFunnels_License_check( $this->encoded_basename );

				$plugins = WooFunnels_License_check::get_plugins();
				if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
					$data = array(
						'plugin_slug' => BWFABT_PLUGIN_BASENAME,
						'plugin_name' => BWFABT_FULL_NAME,
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => BWFABT_VERSION,
					);
					$this->license_instance->setup_data( $data );
					$this->license_instance->start_updater();
				}
			}

		}

		public function process_licensing_form( $posted_data ) {

			if ( isset( $posted_data['license_keys'][ $this->encoded_basename ] ) ) {
				$key  = $posted_data['license_keys'][ $this->encoded_basename ]['key'];
				$data = array(
					'plugin_slug' => BWFABT_PLUGIN_BASENAME,
					'plugin_name' => BWFABT_PLUGIN_BASENAME,

					'license_key' => $key,
					'product_id'  => $this->full_name,
					'version'     => BWFABT_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$this->license_instance->activate_license();
			}
		}

		/**
		 * Validate is it is for email product deactivation
		 *
		 * @param type $posted_data
		 */
		public function maybe_process_deactivation( $posted_data ) {
			if ( isset( $posted_data['filepath'] ) && $posted_data['filepath'] === $this->encoded_basename ) {
				$plugins = WooFunnels_License_check::get_plugins();
				if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
					$data = array(
						'plugin_slug' => BWFABT_PLUGIN_BASENAME,
						'plugin_name' => BWFABT_PLUGIN_BASENAME,
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => BWFABT_VERSION,
					);
					$this->license_instance->setup_data( $data );
					$this->license_instance->deactivate_license();
					wp_safe_redirect( 'admin.php?page=' . $posted_data['page'] . '&tab=' . $posted_data['tab'] );
					exit;
				}
			}
		}

		public function license_check() {
			$plugins = WooFunnels_License_check::get_plugins();
			if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
				$data = array(
					'plugin_slug' => BWFABT_PLUGIN_BASENAME,
					'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
					'product_id'  => $this->full_name,
					'version'     => BWFABT_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$this->license_instance->license_status();
			}
		}


	}

	BWFABT_WooFunnels_Support::get_instance();
}