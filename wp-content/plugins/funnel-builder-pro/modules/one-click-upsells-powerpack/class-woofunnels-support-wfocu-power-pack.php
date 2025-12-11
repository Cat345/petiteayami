<?php
if ( ! class_exists( 'WooFunnels_Support_WFOCU_PowerPack' ) ) {
	/**
	 * @author woofunnels
	 * @package WooFunnels
	 * Class WooFunnels_Support_WFOCU_PowerPack
	 */
	class WooFunnels_Support_WFOCU_PowerPack {

		protected static $instance;
		public $full_name = 'UpStroke PowerPack';
		public $is_license_needed = true;
		public $license_instance;
		protected $encoded_basename = '';

		/**
		 * WooFunnels_Support_WFOCU_PowerPack constructor.
		 */
		public function __construct() {

			add_filter( 'plugin_action_links_' . WF_UPSTROKE_POWERPACK_BASENAME, array( $this, 'plugin_actions' ) );
			add_filter( 'woofunnels_default_reason_' . WF_UPSTROKE_POWERPACK_BASENAME, function () {
				return 1;
			} );

			add_filter( 'woofunnels_default_reason_default', function () {
				return 1;
			} );
			$this->encoded_basename = sha1( WF_UPSTROKE_POWERPACK_BASENAME );
			$this->full_name        = __( 'UpStroke PowerPack', 'woofunnels-upstroke-power-pack' );
			add_filter( 'woofunnels_plugins_license_needed', array( $this, 'add_license_support' ), 10 );
			add_action( 'init', array( $this, 'init_licensing' ), 12 );
			add_action( 'woofunnels_licenses_submitted', array( $this, 'process_licensing_form' ) );
			add_action( 'woofunnels_deactivate_request', array( $this, 'maybe_process_deactivation' ) );
			add_filter( 'wfocu_shortcode_list', array( $this, 'update_shortcode_list' ) );

		}

		/**
		 * @return WooFunnels_Support_WFOCU_PowerPack
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}


		/**
		 * Hooked over 'plugin_action_links_{PLUGIN_BASENAME}' WordPress hook to add deactivate popup support
		 *
		 * @param array $links array of existing links
		 *
		 * @return array modified array
		 */
		public function plugin_actions( $links ) {
			if ( isset( $links['deactivate'] ) ) {
				$links['deactivate'] .= '<i class="woofunnels-slug" data-slug="' . WF_UPSTROKE_POWERPACK_BASENAME . '"></i>';
			}

			return $links;
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
				'product_version'   => WF_UPSTROKE_POWERPACK_VERSION,
				'product_status'    => $status,
				'license_expiry'    => $renew,
				'product_file_path' => $this->encoded_basename,
				'existing_key'      => $license,
			);

			return $plugins;
		}

		public function init_licensing() {
			if ( class_exists( 'WooFunnels_License_check' ) && $this->is_license_needed ) {
				$this->license_instance = new WooFunnels_License_check( $this->encoded_basename );

				$plugins = WooFunnels_License_check::get_plugins();
				if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
					$data = array(
						'plugin_slug' => WF_UPSTROKE_POWERPACK_BASENAME,
						'plugin_name' => $this->full_name,
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WF_UPSTROKE_POWERPACK_VERSION,
					);

					$this->license_instance->setup_data( $data );
					$this->license_instance->start_updater();
				}
			}

		}

		public function process_licensing_form( $posted_data ) {

			if ( isset( $posted_data['license_keys'][ $this->encoded_basename ] ) && '' !== $posted_data['license_keys'][ $this->encoded_basename ] ) {
				$key  = $posted_data['license_keys'][ $this->encoded_basename ]['key'];
				$data = array(
					'plugin_slug' => WF_UPSTROKE_POWERPACK_BASENAME,
					'plugin_name' => $this->full_name,
					'license_key' => $key,
					'product_id'  => $this->full_name,
					'version'     => WF_UPSTROKE_POWERPACK_VERSION,
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
						'plugin_slug' => WF_UPSTROKE_POWERPACK_BASENAME,
						'plugin_name' => $this->full_name,
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WF_UPSTROKE_POWERPACK_VERSION,
					);

					$this->license_instance->setup_data( $data );
					$this->license_instance->deactivate_license();
					wp_safe_redirect( 'admin.php?page=' . $posted_data['page'] . '&tab=' . $posted_data['tab'] );
					exit();
				}
			}
		}

		public function license_check() {
			$plugins = WooFunnels_License_check::get_plugins();
			if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
				$data = array(
					'plugin_slug' => WF_UPSTROKE_POWERPACK_BASENAME,
					'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
					'product_id'  => $this->full_name,
					'version'     => WF_UPSTROKE_POWERPACK_VERSION,
				);

				$this->license_instance->setup_data( $data );
				$this->license_instance->license_status();
			}
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

		/**
		 * @param $shortcode_list
		 *
		 * @return mixed
		 */
		public function update_shortcode_list( $shortcode_list ) {
			$key      = array_search( 'Product Price HTML', wp_list_pluck( $shortcode_list, 'label' ), true );
			$pro_list = array(
				array(
					'label' => __( 'Product Signup Fee', 'woofunnels-upstroke-power-pack' ),
					'code'  => array(
						'single' => '[wfocu_product_signup_fee]',
						'multi'  => '[wfocu_product_signup_fee key="%s" signup_label="Signup Fee"]'
					),
				),
				array(
					'label' => __( 'Product Recurring Total String', 'woofunnels-upstroke-power-pack' ),
					'code'  => array(
						'single' => '[wfocu_product_recurring_total_string]',
						'multi'  => '[wfocu_product_recurring_total_string key="%s" recurring_label="Recurring Price"]'
					),
				),
				array(
					'label' => __( 'All Products Subscription Plan List', 'woofunnels-upstroke-power-pack' ),
					'code'  => array(
						'single' => '[wfocu_subscription_plans_list]',
						'multi'  => '[wfocu_subscription_plans_list key="%s"]'
					),
				)
			);
			array_splice( $shortcode_list, $key, 0, $pro_list );

			return $shortcode_list;
		}
	}

	WooFunnels_Support_WFOCU_PowerPack::get_instance();
}