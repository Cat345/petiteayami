<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFOB_WooFunnels_Support' ) ) {
	class WFOB_WooFunnels_Support {

		public static $_instance = null;
		/** Can't be change this further, as is used for license activation */
		public $full_name = '';
		public $is_license_needed = true;
		/**
		 * @var WooFunnels_License_check
		 */
		public $license_instance;
		protected $slug = 'FunnelKit Order Bumps';
		protected $encoded_basename = '';

		public function __construct() {

			$this->encoded_basename = sha1( WFOB_PLUGIN_BASENAME );
			$this->full_name        = WFOB_FULL_NAME;

			//add_action( 'wfob_page_right_content', array( $this, 'wfob_options_page_right_content' ), 10 );
			add_action( 'admin_menu', array( $this, 'add_menus' ), 81 );
			add_filter( 'woofunnels_plugins_license_needed', array( $this, 'add_license_support' ), 10 );
			add_action( 'init', array( $this, 'init_licensing' ), 12 );
			add_action( 'woofunnels_licenses_submitted', array( $this, 'process_licensing_form' ) );

			add_action( 'woofunnels_deactivate_request', array( $this, 'maybe_process_deactivation' ) );

			add_filter( 'woofunnels_default_reason_' . WFOB_PLUGIN_BASENAME, function () {
				return 1;
			} );
			add_filter( 'woofunnels_default_reason_default', function () {
				return 1;
			} );
		}

		/**
		 * @return null|WFOB_WooFunnels_Support
		 */
		public static function get_instance() {
			if ( null == self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function wfob_options_page_right_content() {
			?>
            <div class="postbox wfob_side_content wfob_allow_panel_close">
                <button type="button" class="handlediv">
                    <span class="toggle-indicator"></span>
                </button>
                <h3 class="hndle"><span>Must Checks</span></h3>
                <div class="inside">
					<?php
					$support_link = add_query_arg( array(
						'utm_source'   => 'wfob-pro',
						'utm_medium'   => 'button-click',
						'utm_campaign' => 'resource',
						'utm_term'     => 'support',
					), 'https://funnelkit.com/support' );
					?>
                    <p>Do checkout the Bump rules and make sure product is in Stock.</p>
                    <p align="center"><a class="button button-primary" href="<?php echo $support_link; ?>" target="_blank">Contact Support</a></p>
                </div>
            </div>
			<?php
		}

	/**
	 * Adding WooCommerce sub-menu for global options
	 */
	public function add_menus() {
		if ( ! WooFunnels_dashboard::$is_core_menu ) {
			$user = WFOB_Core()->role->user_access( 'menu', 'read' );
			if ( false !== $user ) {
				add_menu_page( __( 'WooFunnels', 'woofunnels-order-bump' ), __( 'WooFunnels', 'woofunnels-order-bump' ), $user, 'woofunnels', array( $this, 'woofunnels_page' ), '', 59 );
				add_submenu_page( 'woofunnels', __( 'Licenses', 'woofunnels-order-bump' ), __( 'License', 'woofunnels-order-bump' ), $user, 'woofunnels' );
				WooFunnels_dashboard::$is_core_menu = true;
			}
		}
	}

		public function woofunnels_page() {
			if ( ! isset( $_GET['tab'] ) ) {
				WooFunnels_dashboard::$selected = 'licenses';
			}
			if ( class_exists( 'WFFN_Header' ) ) {
				$header_ins = new WFFN_Header();
				$header_ins->set_level_1_navigation_active( 'licenses' );
				$header_ins->set_level_2_side_navigation( WFFN_Header::level_2_navigation_licenses() );
				$header_ins->set_level_2_side_navigation_active( WooFunnels_dashboard::$selected );
				echo $header_ins->render();
			}
			?>
            <div class="woofunnels_licenses_wrapper">
				<?php WooFunnels_dashboard::load_page(); ?>
            </div>
			<?php
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
				'product_version'   => WFOB_VERSION,
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
						'plugin_slug' => WFOB_PLUGIN_BASENAME,
						'plugin_name' => WFOB_FULL_NAME,
						//	'email'       => $plugins[ $this->encoded_basename ]['data_extra']['license_email'],
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WFOB_VERSION,
					);
					$this->license_instance->setup_data( $data );
					$this->license_instance->start_updater();
				}
			}

		}

		public function process_licensing_form( $posted_data ) {

			if ( isset( $posted_data['license_keys'][ $this->encoded_basename ] ) ) {
				$key = $posted_data['license_keys'][ $this->encoded_basename ]['key'];
				//	$email = $posted_data['license_keys'][ $this->encoded_basename ]['email'];
				$data = array(
					'plugin_slug' => WFOB_PLUGIN_BASENAME,
					'plugin_name' => WFOB_PLUGIN_BASENAME,
					//'email'       => $email,

					'license_key' => $key,
					'product_id'  => $this->full_name,
					'version'     => WFOB_VERSION,
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
			if ( isset( $posted_data['filepath'] ) && $posted_data['filepath'] == $this->encoded_basename ) {
				$plugins = WooFunnels_License_check::get_plugins();
				if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
					$data = array(
						'plugin_slug' => WFOB_PLUGIN_BASENAME,
						'plugin_name' => WFOB_PLUGIN_BASENAME,
						//	'email'       => $plugins[ $this->encoded_basename ]['data_extra']['license_email'],
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WFOB_VERSION,
					);
					$this->license_instance->setup_data( $data );
					$this->license_instance->deactivate_license();
					wp_safe_redirect( 'admin.php?page=' . $posted_data['page'] . '&tab=' . $posted_data['tab'] );
				}
			}
		}

		public function is_license_present() {
			$plugins = WooFunnels_License_check::get_plugins();

			if ( ! isset( $plugins[ $this->encoded_basename ] ) ) {
				return false;
			}

			return true;

		}

	}

	WFOB_WooFunnels_Support::get_instance();
}