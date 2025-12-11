<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFFN_Pro_WooFunnels_Support' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_WooFunnels_Support {

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

			$this->encoded_basename = sha1( WFFN_PRO_PLUGIN_BASENAME );
			$this->full_name        = WFFN_PRO_FULL_NAME;

			add_filter( 'woofunnels_plugins_license_needed', array( $this, 'add_license_support' ), 10 );
			add_action( 'init', array( $this, 'init_licensing' ), 12 );
			add_action( 'woofunnels_licenses_submitted', array( $this, 'process_licensing_form' ) );
			add_action( 'woofunnels_deactivate_request', array( $this, 'maybe_process_deactivation' ) );

			add_filter( 'woofunnels_default_reason_' . WFFN_PRO_PLUGIN_BASENAME, function () {
				return 1;
			} );
			add_filter( 'woofunnels_default_reason_default', function () {
				return 1;
			} );

			add_action( 'admin_menu', array( $this, 'add_menus' ), 81 );
			add_action( 'admin_init', array( $this, 'maybe_handle_onboarding_wizard_licence_check' ), 1 );
			add_action( 'wffn_wizard_steps', array( $this, 'wizard_activation_step' ) );
		}

		/**
		 * @return WFFN_Pro_WooFunnels_Support|null
		 */
		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function woofunnels_page() {
			if ( null === filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW ) ) {
				WooFunnels_dashboard::$selected = 'licenses';
			}
			WooFunnels_dashboard::load_page();
		}

		/**
		 * License management helper function to create a slug that is friendly with edd
		 *
		 * @param $name
		 *
		 * @return string|string[]|null
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
				'plugin'            => 'FunnelKit Funnel Builder Pro',
				'product_version'   => WFFN_PRO_VERSION,
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
						'plugin_slug'        => WFFN_PRO_PLUGIN_BASENAME,
						'plugin_name'        => WFFN_PRO_FULL_NAME,
						'license_key'        => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'         => $this->full_name,
						'version'            => WFFN_PRO_VERSION,
						'version_dependency' => WFFN_VERSION,
					);
					$this->license_instance->setup_data( $data );
					$this->license_instance->start_updater();
				}
			}

		}

		public function process_licensing_form( $posted_data ) {
			if ( class_exists( 'WooFunnels_License_check' ) ) {
				$this->license_instance = new WooFunnels_License_check( $this->encoded_basename );

				if ( isset( $posted_data['license_keys'][ $this->encoded_basename ] ) ) {
					$key  = $posted_data['license_keys'][ $this->encoded_basename ]['key'];
					$data = array(
						'plugin_slug' => WFFN_PRO_PLUGIN_BASENAME,
						'plugin_name' => WFFN_PRO_PLUGIN_BASENAME,

						'license_key' => $key,
						'product_id'  => $this->full_name,
						'version'     => WFFN_PRO_VERSION,
					);
					$this->license_instance->setup_data( $data );
					$this->license_instance->activate_license();
				}
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
						'plugin_slug' => WFFN_PRO_PLUGIN_BASENAME,
						'plugin_name' => WFFN_PRO_PLUGIN_BASENAME,
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WFFN_PRO_VERSION,
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
					'plugin_slug' => WFFN_PRO_PLUGIN_BASENAME,
					'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
					'product_id'  => $this->full_name,
					'version'     => WFFN_PRO_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$this->license_instance->license_status();
			}
		}

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

				if ( is_array( $active_plugins ) && ( in_array( WFFN_PRO_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFFN_PRO_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) ) {
					$woofunnels_data = get_blog_option( get_network()->site_id, 'woofunnels_plugins_info', [] );
				} else {
					$woofunnels_data = get_option( 'woofunnels_plugins_info', [] );
				}
			} else {
				$woofunnels_data = get_option( 'woofunnels_plugins_info' );
			}


			if ( is_array( $woofunnels_data ) && count( $woofunnels_data ) > 0 && defined( 'WFFN_PRO_PLUGIN_BASENAME' ) ) {

				foreach ( $woofunnels_data as $key => $license ) {
					if ( is_array( $license ) && isset( $license['activated'] ) && $license['activated'] && sha1( WFFN_PRO_PLUGIN_BASENAME ) === $key ) {
						$licenseKey = $license['data_extra']['api_key'];
						break;
					}
				}
			}

			return $licenseKey;
		}

		public function is_license_present() {
			$plugins = WooFunnels_License_check::get_plugins();

			if ( ! isset( $plugins[ $this->encoded_basename ] ) ) {
				return false;
			}

			return true;

		}

		/**
		 * Adding WooCommerce sub-menu for global options
		 */
		public function add_menus() {
			if ( ! WooFunnels_dashboard::$is_core_menu ) {
				add_menu_page( __( 'WooFunnels', 'funnel-builder' ), __( 'WooFunnels', 'funnel-builder' ), 'manage_woocommerce', 'woofunnels', array(
					$this,
					'woofunnels_page',
				), '', 59 );
				add_submenu_page( 'woofunnels', __( 'Licenses', 'funnel-builder' ), __( 'License', 'funnel-builder' ), 'manage_woocommerce', 'woofunnels' );
				WooFunnels_dashboard::$is_core_menu = true;
			}
		}


		public function maybe_handle_onboarding_wizard_licence_check() {
			if ( filter_input( INPUT_POST, 'wffn_verify_license', FILTER_UNSAFE_RAW ) !== null ) {
				$data = array(
					'plugin_slug' => WFFN_PRO_PLUGIN_BASENAME,
					'plugin_name' => WFFN_PRO_FULL_NAME,
					'license_key' => filter_input( INPUT_POST, 'license_key', FILTER_UNSAFE_RAW ),
					'product_id'  => $this->full_name,
					'version'     => WFFN_PRO_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$data_response = $this->license_instance->activate_license();

				if ( is_array( $data_response ) && $data_response['activated'] === true ) {
					WFFN_Wizard::set_license_state( true );
					do_action( 'wffn_license_activated', 'funnel-builder-pro' );
					if ( filter_input( INPUT_POST, '_redirect_link', FILTER_UNSAFE_RAW ) !== null ) {
						wp_redirect( filter_input( INPUT_POST, '_redirect_link', FILTER_UNSAFE_RAW ) );
						exit;
					}
				} else {
					WFFN_Wizard::set_license_state( false );
					WFFN_Wizard::set_license_key( filter_input( INPUT_POST, 'license_key', FILTER_UNSAFE_RAW ) );
				}
			}
		}


		public function wizard_activation_step( $args ) {

			if ( WFFN_PRO_Core()->support->is_license_present() === false ) {
				$args['welcome'] = array(
					'name' => __( 'Welcome', 'funnel-builder' ),
					'view' => array( __CLASS__, 'wffn_setup_activate' ),
				);
			}

			return $args;

		}

		public static function wffn_setup_activate() { ?>
            <h2> <?php esc_html_e( 'Activate FunnelKit Funnel Builder Pro on your site', 'funnel-builder' ); ?></h2>
            <form id="wffn_verify_license" action="" method="POST">
                <input type="hidden" name="_step_name" value="license_key">
                <div class="about-text">
                    <p>
						<?php
						esc_html_e( 'You\'re all set! Get your license keys from your accounts page and paste them here below to get started.', 'funnel-builder' ); ?>
                    </p>
                    <p>
                        <input style="width: 100%; padding: 10px;" type="text" required="required" class="regular-text" id="license_key" value="<?php echo esc_attr( WFFN_Wizard::$key ); ?>" name="license_key" placeholder="Enter Your License Key">
						<?php
						if ( WFFN_Wizard::$license_state === false ) {
							echo '<span class="wffn_invalid_license">Invalid Key. Ensure that your are using valid license key. Try again.</span>';
						}
						?>
                    </p>
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'woocommerce-settings' ) ); ?>"/>
                    <input type="hidden" name="_redirect_link" value="<?php echo esc_url( WFFN_Wizard::get_next_step_link() ); ?>"/>
                </div>
                <div>
                    <p class="wffn-setup-actions step">
                        <input class="button-primary button button-large button-next" type="submit" value="Activate" name="wffn_verify_license">
                    </p>
                </div>

                <p><?php esc_html_e( 'Unable to find license key?', 'funnel-builder' ); ?> <br/>
					<?php esc_html_e( 'Follow', 'funnel-builder' ); ?>
                    <a target="_blank" href="<?php echo esc_url( 'https://buildwoofunnels.com/docs/upstroke/getting-started/installation/' ) ?>"><?php esc_html_e( 'this step by step guide', 'funnel-builder' ); ?></a><?php esc_html_e( ' to find the license key.', 'funnel-builder' ); ?>
                </p>

                <p><strong><?php esc_html_e( 'Note:', 'woofunnels-flex-funnels' ); ?></strong> <?php esc_html_e( 'This is just a one time activation process.', 'funnel-builder' ); ?>
                    <i><?php esc_html_e( 'You plugin would continue to work as it is even if your license key is expired.', 'funnel-builder' ); ?></i> <?php esc_html_e( ' Ofcourse,you would loose access to support and future updates if your license expires.', 'funnel-builder' ); ?>
                </p>
            </form>
			<?php
		}
	}

	if ( class_exists( 'WFFN_Pro_WooFunnels_Support' ) ) {
		WFFN_Pro_Core::register( 'support', 'WFFN_Pro_WooFunnels_Support' );
	}
}