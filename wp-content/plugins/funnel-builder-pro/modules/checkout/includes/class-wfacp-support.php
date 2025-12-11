<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_WooFunnels_Support' ) ) {
	#[AllowDynamicProperties]
	class WFACP_WooFunnels_Support {

		public static $_instance = null;
		/** Can't be change this further, as is used for license activation */
		public $full_name = 'FunnelKit Checkout';
		public $is_license_needed = true;
		/**
		 * @var WooFunnels_License_check
		 */
		public $license_instance;
		protected $slug = 'woofunnels-aero-checkout';
		protected $encoded_basename = '';

		public function __construct() {

			$this->encoded_basename = sha1( WFACP_PLUGIN_BASENAME );

			add_action( 'wfacp_page_right_content', array( $this, 'wfacp_options_page_right_content' ), 10 );


			add_action( 'admin_menu', array( $this, 'add_menus' ), 81 );
			add_filter( 'woofunnels_plugins_license_needed', array( $this, 'add_license_support' ), 10 );
			add_action( 'init', array( $this, 'init_licensing' ), 12 );
			add_action( 'admin_init', array( $this, 'maybe_handle_license_activation_wizard' ), 1 );
			add_action( 'woofunnels_licenses_submitted', array( $this, 'process_licensing_form' ) );
			add_action( 'woofunnels_deactivate_request', array( $this, 'maybe_process_deactivation' ) );


			add_filter( 'woofunnels_default_reason_' . WFACP_PLUGIN_BASENAME, function () {
				return 1;
			} );
			add_filter( 'woofunnels_default_reason_default', function () {
				return 1;
			} );

		}

		/**
		 * @return null|WFACP_WooFunnels_Support
		 */
		public static function get_instance() {
			if ( null == self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}


		public function wfacp_options_page_right_content() {

			$notifications_list = [];
			if ( class_exists( 'WooFunnels_Notifications' ) ) {

				$notifications_list = WooFunnels_Notifications::get_instance()->get_all_notifications();

			}

			if ( is_array( $notifications_list ) && count( $notifications_list ) > 0 ) {
				?>
                <div class="postbox wfacp_side_content wfacp_allow_panel_close wf_notification_list_wrap">
                    <button type="button" class="handlediv">
                        <span class="toggle-indicator"></span>
                    </button>
                    <h3 class="hndle"><span>FunnelKit Checkout Alert(s)</span></h3>
					<?php
					WooFunnels_Notifications::get_instance()->get_notification_html( $notifications_list );
					?>
                </div>
				<?php
			}
			?>

            <div class="postbox wfacp_side_content wfacp_allow_panel_close">
                <button type="button" class="handlediv">
                    <span class="toggle-indicator"></span>
                </button>
                <h3 class="hndle"><span>Must Read Links</span></h3>
                <div class="inside">
					<?php
					$support_link    = add_query_arg( array(
						'utm_source'   => 'wfacp-pro',
						'utm_medium'   => 'banner-click',
						'utm_campaign' => 'resource',
						'utm_term'     => 'support',
					), 'https://funnelkit.com/support' );
					$getting_started = add_query_arg( array(
						'utm_source'   => 'wfacp-pro',
						'utm_medium'   => 'text-click',
						'utm_campaign' => 'resource',
						'utm_term'     => 'getting-started',
					), 'https://funnelkit.com/docs/checkout-pages/getting-started/' );

					$first_checkout  = add_query_arg( array(
						'utm_source'   => 'wfacp-pro',
						'utm_medium'   => 'text-click',
						'utm_campaign' => 'resource',
						'utm_term'     => 'creating-first-checkout-page',
					), 'https://funnelkit.com/docs/checkout-pages/getting-started/creating-first-checkout-page/' );
					$global_checkout = add_query_arg( array(
						'utm_source'   => 'wfacp-pro',
						'utm_medium'   => 'text-click',
						'utm_campaign' => 'resource',
						'utm_term'     => 'global-checkout-page',
					), 'https://funnelkit.com/docs/checkout-pages/getting-started/replace-default-checkout' );
					$doc_link        = add_query_arg( array(
						'utm_source'   => 'wfacp-pro',
						'utm_medium'   => 'text-click',
						'utm_campaign' => 'resource',
						'utm_term'     => 'documentation',
					), 'https://funnelkit.com/docs/checkout-pages/' );

					?>
                    <p>Before you start building the Checkout Pages, visit these 3 important links.</p>
                    <ul class="wfacp-list-dec">
                        <li><a href="<?php echo $getting_started; ?>" target="_blank">Getting Started</a></li>

                        <li><a href="<?php echo $first_checkout; ?>" target="_blank">Create First Checkout Page</a></li>
                        <li><a href="<?php echo $global_checkout; ?>" target="_blank">Setup Page as Global Checkout Page</a></li>
                    </ul>
                    <p>Unable to find answers?<br/><a href="<?php echo $doc_link; ?>" target="_blank">Read Documentation</a></p>
                    <p>Still need Help? We will be happy to answer.</p>
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
				$user = WFACP_Core()->role->user_access( 'menu', 'read' );
				if ( false !== $user ) {
					add_menu_page( __( 'WooFunnels', 'woofunnels' ), __( 'WooFunnels', 'woofunnels' ), $user, 'woofunnels', array( $this, 'woofunnels_page' ), '', 59 );
					add_submenu_page( 'woofunnels', __( 'Licenses', 'woofunnels' ), __( 'License', 'woofunnels' ), $user, 'woofunnels' );
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
				'product_version'   => WFACP_VERSION,
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
				$plugins                = WooFunnels_License_check::get_plugins();
				if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
					$data = array(
						'plugin_slug' => WFACP_PLUGIN_BASENAME,
						'plugin_name' => WFACP_FULL_NAME,
						//	'email'       => $plugins[ $this->encoded_basename ]['data_extra']['license_email'],
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WFACP_VERSION,
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
					'plugin_slug' => WFACP_PLUGIN_BASENAME,
					'plugin_name' => WFACP_FULL_NAME,
					//'email'       => $email,

					'license_key' => $key,
					'product_id'  => $this->full_name,
					'version'     => WFACP_VERSION,
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
						'plugin_slug' => WFACP_PLUGIN_BASENAME,
						'plugin_name' => WFACP_FULL_NAME,
						'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
						'product_id'  => $this->full_name,
						'version'     => WFACP_VERSION,
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

		public function maybe_handle_license_activation_wizard() {

			if ( filter_input( INPUT_POST, 'wfacp_verify_license' ) !== null ) {
				$data = array(
					'plugin_slug' => WFACP_PLUGIN_BASENAME,
					'plugin_name' => WFACP_FULL_NAME,
					'license_key' => filter_input( INPUT_POST, 'license_key' ),
					'product_id'  => $this->full_name,
					'version'     => WFACP_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$data_response = $this->license_instance->activate_license();

				if ( is_array( $data_response ) && $data_response['activated'] === true ) {
					WFACP_Wizard::set_license_state( true );
					do_action( 'wfacp_license_activated', 'woofunnels-aero-checkout' );
					if ( filter_input( INPUT_POST, '_redirect_link' ) !== null ) {
						wp_redirect( filter_input( INPUT_POST, '_redirect_link' ) );
					}
				} else {
					WFACP_Wizard::set_license_state( false );
					WFACP_Wizard::set_license_key( filter_input( INPUT_POST, 'license_key' ) );

				}
			}
		}
	}

	if ( class_exists( 'WFACP_WooFunnels_Support' ) ) {
		WFACP_Core::register( 'support', 'WFACP_WooFunnels_Support' );
	}
}