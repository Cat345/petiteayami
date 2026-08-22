<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_License_Admin_UI
 *
 * Every admin surface that talks about a license: the settings-screen license
 * fields, the "License Expired" admin-bar node and submenu, and the two
 * plugins-screen renewal notices.
 *
 * All of it was relocated out of the free plugin per the WP.org review -- the
 * free build ships no license or update-checker code -- and none of it is
 * reachable without a license anyway. The free plugin still renders its own
 * "Lite needs no license" panel; this class replaces that panel with the real
 * fields by hooking the same filter at a later priority.
 */
if ( ! class_exists( 'WFFN_License_Admin_UI' ) ) {
	#[AllowDynamicProperties]
	class WFFN_License_Admin_UI {

		/**
		 * @var WFFN_License_Admin_UI|null
		 */
		private static $ins = null;

		/**
		 * Invoked by WFFN_Pro_Admin once, on init, so this class is not read on
		 * requests that never reach an admin screen or the settings REST route.
		 */
		public function __construct() {
			/** Runs for REST reads of the settings screen too, so it is not admin-gated. */
			add_filter( 'bwf_settings_config_general', array( $this, 'settings_config' ), 20 );
			add_filter( 'bwf_general_settings_pro_status', array( $this, 'pro_status' ) );

			if ( ! is_admin() ) {
				return;
			}

			add_filter( 'wffn_dashboard_widget_state', array( $this, 'dashboard_widget_state' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_license_notice' ), 100 );
			add_action( 'admin_menu', array( $this, 'register_license_expired_menu' ), 999 );
			add_action( 'after_plugin_row_meta', array( $this, 'maybe_add_notice' ), 10 );
			add_filter( 'plugin_action_links', array( $this, 'plugin_action_link' ), 10, 2 );
		}

		/**
		 * @return WFFN_License_Admin_UI|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Whether the Pro plugin folder has been renamed in a way that blocks license activation.
		 *
		 * Compares the canonical folder hash against the live basename hash. The "already activated
		 * under the live hash" guard prevents false positives for legacy installs that were activated
		 * under a non-canonical folder slug.
		 *
		 * Ported from Lite PR fb#9089 — the helper lived in WFFN_Common there; every caller now
		 * sits in this plugin after the license relocation, so it lives here instead.
		 *
		 * @return bool True when Pro is active, the live folder hash differs from the canonical
		 *              hash, and no activated license exists under the live hash; false otherwise.
		 */
		public static function is_pro_folder_renamed() {
			if ( ! defined( 'WFFN_PRO_PLUGIN_BASENAME' ) ) {
				return false;
			}

			$canonical_sha = sha1( 'funnel-builder-pro/funnel-builder-pro.php' );
			$live_sha      = sha1( WFFN_PRO_PLUGIN_BASENAME );

			/* Folder is canonical (hash matches) — license can activate normally, nothing to warn about. */
			if ( hash_equals( $canonical_sha, $live_sha ) ) {
				return false;
			}

			$bwf_licenses = get_option( 'woofunnels_plugins_info', false );
			if ( is_multisite() && defined( 'WFFN_PLUGIN_BASENAME' ) ) {
				$active_plugins = get_site_option( 'active_sitewide_plugins', array() );
				if ( is_array( $active_plugins ) && ( in_array( WFFN_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFFN_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) && ! is_main_site() ) {
					$bwf_licenses = get_blog_option( get_network()->site_id, 'woofunnels_plugins_info', array() );
				}
			}

			/* Legacy non-canonical install that activated under its own hash — license works, do not warn. */
			if ( is_array( $bwf_licenses ) && ! empty( $bwf_licenses[ $live_sha ]['activated'] ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Folder-renamed warning shared by the plugins screen, the settings screen and the REST guard.
		 *
		 * @param string $link_class Optional class for the anchors (the in-app REST error uses 'bwf-a-no-underline').
		 *
		 * @return string
		 */
		public static function get_folder_renamed_message( $link_class = '' ) {
			$class_attr = '' !== $link_class ? ' class="' . esc_attr( $link_class ) . '"' : '';

			return wp_kses_post( __( 'We have detected that plugin directory has been renamed. Please restore the directory to its original name or download the latest files from', 'funnel-builder-pro' ) ) . ' <a' . $class_attr . ' href="' . esc_url( 'https://myaccount.funnelkit.com/' ) . '">' . esc_html__( 'your account', 'funnel-builder-pro' ) . '</a> ' . esc_html__( 'or', 'funnel-builder-pro' ) . ' <a' . $class_attr . ' href="' . esc_url( 'https://funnelkit.com/support/' ) . '">' . esc_html__( 'contact support', 'funnel-builder-pro' ) . '</a> ' . esc_html__( 'for assistance.', 'funnel-builder-pro' );
		}

		/**
		 * Expiry-only license config.
		 *
		 * @return array
		 */
		private function get_expiry_config() {
			return WFFN_License_Config::get_instance()->get_config( true );
		}

		/**
		 * Whether the Funnel Builder license has an expiry date already in the past.
		 *
		 * @return bool
		 */
		private function is_expired() {
			$config = $this->get_expiry_config();
			if ( empty( $config['f']['ed'] ) ) {
				return false;
			}

			$expiry  = new DateTime( $config['f']['ed'] );
			$current = new DateTime( current_time( 'mysql', true ) );

			return $expiry->getTimestamp() < $current->getTimestamp();
		}

		/**
		 * Unlocks the WordPress dashboard widget and supplies its notice.
		 *
		 * The free plugin renders the free experience and asks for this on a
		 * filter; the license-derived state that used to live in its widget now
		 * lives here.
		 *
		 * @param array $state Defaults from the free plugin.
		 *
		 * @return array
		 */
		public function dashboard_widget_state( $state ) {
			if ( ! is_array( $state ) ) {
				return $state;
			}

			$license_config = WFFN_License_Config::get_instance()->get_config();
			$app_state      = $this->get_current_app_state( $license_config );
			$locked_states  = array( 'lite', 'basic', 'pro_without_license', 'license_expired_on_grace_period', 'license_expired' );

			$state['analytics_path']   = ( 'lite' !== $app_state ) ? '&path=/analytics' : '';
			$state['square_supported'] = ( 'lite' !== $app_state );
			$state['show_upgrade_cta'] = ( 'lite' === $app_state );
			$state['metrics_locked']   = in_array( $app_state, $locked_states, true );
			$state['notice']           = $this->get_widget_notice( $app_state, $license_config );

			return $state;
		}

		/**
		 * Notice shown above the dashboard widget for the current license state.
		 *
		 * @param string $app_state      Resolved app state.
		 * @param array  $license_config License payload.
		 *
		 * @return array
		 */
		private function get_widget_notice( $app_state, $license_config ) {
			$texts = apply_filters( 'wffn_localized_text_admin', array() );
			$none  = array(
				'severity' => 'none',
				'text'     => '',
			);

			if ( 'pro_without_license' === $app_state || 'pro_without_license_on_grace_period' === $app_state ) {
				return array(
					'severity' => 'warning',
					'text'     => sprintf( /* translators: %s: URL to license settings page */
						__( '<strong>FunnelKit Pro is Not Fully Activated!</strong>  Please activate your license to continue using premium features without interruption. <a href="%s" target="_blank">Activate License</a>', 'funnel-builder-pro' ),
						esc_url( admin_url( 'admin.php?page=bwf&path=/settings/woofunnels_general_settings' ) )
					),
				);
			}

			$renew_link = 'https://funnelkit.com/exclusive-offer/?utm_source=WordPress&utm_campaign=FB+Lite+Plugin&utm_medium=Dashboard+Widget+TopBar';

			if ( 'license_expired' === $app_state ) {
				if ( empty( $texts['license']['states'][4]['notice']['text'] ) ) {
					return $none;
				}

				return array(
					'severity' => 'danger',
					'text'     => $texts['license']['states'][4]['notice']['text'] . ' <a href="' . esc_url( $renew_link ) . '">' . esc_html( $texts['license']['states'][4]['notice']['primary_action'] ) . '</a>',
				);
			}

			if ( 'license_expired_on_grace_period' === $app_state ) {
				if ( empty( $texts['license']['states'][3]['notice']['text'] ) || empty( $license_config['f']['ed'] ) ) {
					return $none;
				}

				$grace_days = isset( $license_config['gp'][0] ) ? absint( $license_config['gp'][0] ) : 2;
				$expires_on = ( new DateTime( $license_config['f']['ed'] ) )->modify( '+' . $grace_days . ' days' )->format( 'F j, Y' );

				return array(
					'severity' => 'danger',
					'text'     => str_replace( '{{TIME_GRACE_EXPIRED}}', $expires_on, $texts['license']['states'][3]['notice']['text'] ) . ' <a href="' . esc_url( $renew_link ) . '">' . esc_html( $texts['license']['states'][3]['notice']['primary_action'] ) . '</a>',
				);
			}

			/** Licensed, or running Basic: nothing to say. */
			return $none;
		}

		/**
		 * Resolves the license payload into a single app state.
		 *
		 * Relocated from the free plugin's dashboard widget -- every branch of it
		 * is about premium licensing.
		 *
		 * @param array  $proData License payload.
		 * @param string $module  Module key.
		 *
		 * @return string
		 */
		public function get_current_app_state( $proData, $module = 'f' ) {
			$data = ( isset( $proData[ $module ] ) && is_array( $proData[ $module ] ) ) ? $proData[ $module ] : array();
			$e    = isset( $data['e'] ) ? $data['e'] : false;
			$la   = isset( $data['la'] ) ? $data['la'] : false;
			$ed   = isset( $data['ed'] ) ? $data['ed'] : '';
			$ad   = isset( $data['ad'] ) ? $data['ad'] : '';
			$ib   = isset( $data['ib'] ) ? $data['ib'] : false;
			$gp   = ( isset( $proData['gp'] ) && is_array( $proData['gp'] ) ) ? $proData['gp'] : array( 2, 2 );

			if ( $ib && 'f' === $module ) {
				return 'basic';
			}
			if ( ! $e ) {
				return 'lite';
			} elseif ( $ed && strtotime( 'now' ) > strtotime( $ed ) ) {
				if ( strtotime( 'now' ) - strtotime( $ed ) < $gp[0] * 24 * 3600 ) {
					return 'license_expired_on_grace_period';
				}

				return 'license_expired';
			} elseif ( true === $la ) {
				return 'pro';
			} elseif ( strtotime( 'now' ) - strtotime( $ad ) < $gp[1] * 24 * 3600 ) {
				return 'pro_without_license_on_grace_period';
			}

			return 'pro_without_license';
		}

		/**
		 * Every licence row for the settings screen, FB Pro/Basic first.
		 *
		 * `WooFunnels_licenses::$plugins_list` is lazy -- it stays null until
		 * something calls get_plugins_list(), and on a REST read of the settings
		 * screen nothing does, because every other caller sits on an admin-only
		 * hook. So populate it here rather than trusting the request to have done
		 * it; otherwise the list reads empty and the Pro licence row is lost.
		 *
		 * @return array {
		 *     @type array      $fields Licence rows, FB Pro/Basic unshifted to the front.
		 *     @type array|null $fb_pro The FB Pro/Basic row, or null when absent.
		 * }
		 */
		private function get_license_rows() {
			$fields = array();
			$fb_pro = null;

			if ( ! class_exists( 'WooFunnels_licenses' ) ) {
				return array(
					'fields' => $fields,
					'fb_pro' => $fb_pro,
				);
			}

			$License = WooFunnels_licenses::get_instance();
			$License->get_plugins_list();

			if ( ! is_array( $License->plugins_list ) || 0 === count( $License->plugins_list ) ) {
				return array(
					'fields' => $fields,
					'fb_pro' => $fb_pro,
				);
			}

			foreach ( $License->plugins_list as $license ) {
				/**
				 * Excluding data for automation and connector addon
				 */
				if ( in_array( $license['product_file_path'], array( '7b31c172ac2ca8d6f19d16c4bcd56d31026b1bd8', '913d39864d876b7c6a17126d895d15322e4fd2e8' ), true ) ) {
					continue;
				}

				$license_data = array();
				if ( isset( $license['_data'] ) && isset( $license['_data']['data_extra'] ) ) {
					$license_data = $license['_data']['data_extra'];
					if ( isset( $license_data['api_key'] ) ) {
						$license_data['api_key'] = 'xxxxxxxxxxxxxxxxxxxxxxxxxx' . substr( $license_data['api_key'], - 6 );
						$license_data['licence'] = 'xxxxxxxxxxxxxxxxxxxxxxxxxx' . substr( $license_data['api_key'], - 6 );
					}
				}

				/* Surface a rename error on the license field so the settings UI shows it before the user clicks Activate. */
				if ( 'FunnelKit Funnel Builder Pro' === $license['plugin'] && true === self::is_pro_folder_renamed() ) {
					if ( ! is_array( $license_data ) ) {
						$license_data = array();
					}
					$license_data['error_msg'] = self::get_folder_renamed_message();
				}

				$data = array(
					'id'                      => $license['product_file_path'],
					'label'                   => $license['plugin'],
					'type'                    => 'license',
					'key'                     => $license['product_file_path'],
					'license'                 => ! empty( $license_data ) ? $license_data : false,
					'is_manually_deactivated' => ( isset( $license['_data']['manually_deactivated'] ) && true === bwf_string_to_bool( $license['_data']['manually_deactivated'] ) ) ? 1 : 0,
					'activated'               => ( isset( $license['_data']['activated'] ) && true === bwf_string_to_bool( $license['_data']['activated'] ) ) ? 1 : 0,
					'expired'                 => ( isset( $license['_data']['expired'] ) && true === bwf_string_to_bool( $license['_data']['expired'] ) ) ? 1 : 0,
				);

				if ( $license['plugin'] === 'FunnelKit Funnel Builder Pro' || $license['plugin'] === 'FunnelKit Funnel Builder Basic' ) {
					$fb_pro         = $data;
					$data['module'] = 'f';
					array_unshift( $fields, $data );
				} else {
					$fields[] = $data;
				}
			}

			return array(
				'fields' => $fields,
				'fb_pro' => $fb_pro,
			);
		}

		/**
		 * FB Pro licence state for the admin general settings localized data.
		 *
		 * Same rows the settings screen renders, reduced to the single FB Pro
		 * entry the free plugin's `bwf_general_settings_pro_status` filter expects.
		 *
		 * @param array $pro_status Empty default from the free plugin.
		 *
		 * @return array
		 */
		public function pro_status( $pro_status ) {
			$rows = $this->get_license_rows();

			return is_array( $rows['fb_pro'] ) ? $rows['fb_pro'] : $pro_status;
		}

		/**
		 * Replaces the free plugin's upgrade panel with the real license fields.
		 *
		 * @param array $config Settings fields.
		 *
		 * @return array
		 */
		public function settings_config( $config ) {
			if ( ! is_array( $config ) ) {
				$config = array();
			}

			/**
			 * Drop the free plugin's "Lite needs no license" panel before adding ours.
			 * Old lite builds (<= 3.15.0.x) also render the real license rows themselves
			 * (type "license", keyed by product_file_path) from the same plugins_list this
			 * class reads — drop those too or every license shows twice.
			 */
			foreach ( $config as $index => $field ) {
				if ( isset( $field['key'] ) && in_array( $field['key'], array( 'no_license', 'label_no_license', 'pro_control', 'label_pro_control' ), true ) ) {
					unset( $config[ $index ] );
				}
				if ( isset( $field['type'] ) && 'license' === $field['type'] ) {
					unset( $config[ $index ] );
				}
			}
			$config = array_values( $config );

			$rows       = $this->get_license_rows();
			$fields     = $rows['fields'];
			$has_fb_pro = is_array( $rows['fb_pro'] );

			if ( empty( $has_fb_pro ) && defined( 'WFFN_PRO_PLUGIN_BASENAME' ) ) {
				$file_path = sha1( WFFN_PRO_PLUGIN_BASENAME );
				array_unshift(
					$fields,
					array(
						'id'                      => $file_path,
						'label'                   => __( 'FunnelKit Funnel Builder Pro', 'funnel-builder-pro' ),
						'type'                    => 'license',
						'key'                     => $file_path,
						'license'                 => false,
						'is_manually_deactivated' => 0,
						'activated'               => 0,
						'expired'                 => 0,
						'module'                  => 'f',
					)
				);
			} elseif ( is_multisite() ) {

				/**
				 * Check if sitewide installed, if yes then get the plugin info from primary site
				 */
				$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

				if ( is_array( $active_plugins ) && defined( 'WFFN_PLUGIN_BASENAME' ) && ( in_array( WFFN_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ), true ) || array_key_exists( WFFN_PLUGIN_BASENAME, apply_filters( 'active_plugins', $active_plugins ) ) ) && ! is_main_site() ) {
					$fields              = array();
					$main_site_id        = 1; // Main site ID in Multisite
					$main_site_admin_url = get_site_url( $main_site_id, 'wp-admin/admin.php?page=bwf&path=/settings' );

					array_unshift(
						$fields,
						array(
							'type'         => 'label',
							'key'          => 'label_no_license',
							'label'        => __( 'FunnelKit Funnel Builder Pro', 'funnel-builder-pro' ),
							'styleClasses' => array( 'wfacp_setting_track_and_events_start', 'bwf_wrap_custom_html_tracking_general' ),
						)
					);
					array_unshift(
						$fields,
						array(
							'key'          => 'no_license',
							'type'         => 'multisite_notice',
							'linkButton'   => esc_url( $main_site_admin_url ),
							'label'        => __( 'You have activated FunnelKit on a multisite network, So the licenses will be managed on the main site and not on the sub sites. ', 'funnel-builder-pro' ),
							'styleClasses' => array( 'wfacp_checkbox_wrap', 'wfacp_setting_track_and_events_end' ),
							'hint'         => '',
						)
					);
				}
			}

			return array_merge( $fields, $config );
		}

		/**
		 * "License Expired" node under the Funnel Builder admin-bar menu.
		 *
		 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
		 *
		 * @return void
		 */
		public function admin_bar_license_notice( $wp_admin_bar ) {
			if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'add_menu' ) ) {
				return;
			}

			/** Only attach when the free plugin actually rendered the parent node. */
			if ( method_exists( $wp_admin_bar, 'get_node' ) && null === $wp_admin_bar->get_node( 'wffn_funnel' ) ) {
				return;
			}

			if ( class_exists( 'WooFunnels_licenses' ) ) {
				WooFunnels_licenses::get_instance()->get_plugins_list();
			}

			if ( ! $this->is_expired() ) {
				return;
			}

			$link = add_query_arg(
				array(
					'utm_source'   => 'WordPress',
					'utm_medium'   => 'Toolbar+Menu',
					'utm_campaign' => 'FB+Lite+Plugin',
				),
				'https://funnelkit.com/my-account/'
			);

			$wp_admin_bar->add_menu(
				array(
					'id'     => 'wffn_funnel-license',
					'parent' => 'wffn_funnel',
					'title'  => __( 'License Expired', 'funnel-builder-pro' ),
					'href'   => $link,
				)
			);
			?>
			<style type="text/css">
				ul#wp-admin-bar-wffn_funnel-default li#wp-admin-bar-wffn_funnel-license a.ab-item {
					color: white;
					background-color: #e15334;
				}
			</style>
			<?php
		}

		/**
		 * "License Expired" entry in the Funnel Builder admin menu.
		 *
		 * @return void
		 */
		public function register_license_expired_menu() {
			if ( class_exists( 'WooFunnels_licenses' ) ) {
				WooFunnels_licenses::get_instance()->get_plugins_list();
			}

			if ( ! $this->is_expired() ) {
				return;
			}

			$link = add_query_arg(
				array(
					'utm_source'   => 'WordPress',
					'utm_medium'   => 'Admin+Menu',
					'utm_campaign' => 'FB+Lite+Plugin',
				),
				'https://funnelkit.com/exclusive-offer/'
			);

			add_submenu_page(
				'woofunnels',
				null,
				'<a href="' . $link . '" style="background-color:#e15334; color:white;" target="_blank"><strong>' . __( 'License Expired', 'funnel-builder-pro' ) . '</strong></a>',
				'manage_options',
				'upgrade_pro',
				function () {
				},
				99
			);
		}

		/**
		 * Renewal notice rendered under the Pro plugin row.
		 *
		 * @param string $plugin_file Plugin basename of the row being rendered.
		 *
		 * @return void
		 */
		public function maybe_add_notice( $plugin_file ) {
			if ( ! defined( 'WFFN_PRO_PLUGIN_BASENAME' ) || $plugin_file !== WFFN_PRO_PLUGIN_BASENAME ) {
				return;
			}

			if ( true === self::is_pro_folder_renamed() ) {
				$notice = wp_get_admin_notice(
					self::get_folder_renamed_message(),
					array(
						'type'               => 'error',
						'additional_classes' => array( 'inline', 'notice-alt' ),
					)
				);
				printf( '<div class="requires">%s</div>', $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from translated wp_kses_post/esc_* parts via wp_get_admin_notice.

				return;
			}

			$render_css = false;
			if ( class_exists( 'WooFunnels_licenses' ) ) {
				WooFunnels_licenses::get_instance()->get_plugins_list();
			}

			$current = new DateTime( current_time( 'mysql', true ) );
			$a       = $this->get_expiry_config();

			if ( ! empty( $a['f']['ed'] ) ) {

				$expiry = new DateTime( $a['f']['ed'] );

				$diff_in_days = $expiry->diff( $current )->format( '%a' );

				if ( ( $expiry->getTimestamp() < $current->getTimestamp() && absint( $diff_in_days ) <= 7 ) ) {
					$render_css = true;

					$time = $current->modify( '+7 days' )->format( 'F j, Y' );
					?>
					<tr class="plugin-update-tr fb_license_notice active fbk_renew" id="cart-for-woocommerce-update"
						data-slug="cart-for-woocommerce" data-plugin="cart-for-woocommerce/plugin.php">
						<td colspan="4" class="plugin-update colspanchange">
							<div class="update-message notice inline notice-error notice-alt">

								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path
										d="M21.8012 18.6522L13.336 3.78261C13.0546 3.28702 12.5687 3 12.0061 3C11.4435 3 10.9575 3.28702 10.6763 3.78261L2.21104 18.6522C1.92965 19.1478 1.92965 19.7218 2.21104 20.2174C2.49242 20.713 2.97829 21 3.54089 21H20.4459C21.0085 21 21.4946 20.713 21.7758 20.2174C22.0572 19.7218 22.0827 19.1478 21.8013 18.6522H21.8012ZM20.9317 19.6956C20.8805 19.7739 20.7527 19.9564 20.4969 19.9564L3.56641 19.9566C3.31071 19.9566 3.15726 19.774 3.13157 19.6958C3.08036 19.6175 3.00363 19.4088 3.13157 19.174L11.5968 4.3044C11.7247 4.06962 11.9549 4.04359 12.0316 4.04359C12.1084 4.04359 12.3385 4.06962 12.4665 4.3044L20.9317 19.174C21.0596 19.4088 20.9829 19.6173 20.9317 19.6956V19.6956Z"
										fill="#d63638" stroke="#d63638" stroke-width="0.3"/>
									<path
										d="M12.0316 10.5216C11.7502 10.5216 11.52 10.7564 11.52 11.0434V17.0435C11.52 17.3306 11.7502 17.5653 12.0316 17.5653C12.313 17.5653 12.5431 17.3306 12.5431 17.0435V11.0434C12.5431 10.7564 12.313 10.5216 12.0316 10.5216Z"
										fill="#d63638" stroke="#d63638" stroke-width="0.3"/>
									<path
										d="M12.5433 8.95637C12.5433 9.24461 12.3141 9.47817 12.0317 9.47817C11.7493 9.47817 11.5201 9.24461 11.5201 8.95637C11.5201 8.66831 11.7493 8.43475 12.0317 8.43475C12.3141 8.43475 12.5433 8.66832 12.5433 8.95637Z"
										fill="#d63638" stroke="#d63638" stroke-width="0.5"/>
								</svg>

								<p>
									<?php
									printf( wp_kses_post( __( '<strong>Your FunnelKit Pro license has expired!</strong> We\'ve extended its features until %1$s, after which they\'ll be limited. <a href="https://funnelkit.com/exclusive-offer/?utm_source=WordPress&utm_campaign=FB+Lite+Plugin&utm_medium=Plugin+Inline+Notice">Renew Now</a> or <a href="%2$s">I have My License Key</a>', 'funnel-builder-pro' ) ), esc_html( $time ), esc_url( admin_url( 'admin.php?page=bwf&path=/settings/woofunnels_general_settings' ) ) );
									?>
								</p>


							</div>
						</td>
					</tr>

					<?php
				} /**
				 * the expiry should always be less than on current utc
				 */ elseif ( $expiry->getTimestamp() < $current->getTimestamp() ) {
					$render_css = true;
					?>
					<tr class="plugin-update-tr fb_license_notice active">
						<td colspan="4" class="plugin-update colspanchange">
							<div class="update-message notice inline notice-error notice-alt">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path
										d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47716 6.47715 2 12 2C17.5228 2 22 6.47716 22 12ZM16.119 9.45234C16.5529 9.01843 16.5529 8.31491 16.119 7.88099C15.6851 7.44708 14.9816 7.44708 14.5477 7.88099L12 10.4287L9.45234 7.88099C9.01843 7.44708 8.31491 7.44708 7.88099 7.88099C7.44708 8.31491 7.44708 9.01843 7.88099 9.45234L10.4287 12L7.88099 14.5477C7.44708 14.9816 7.44708 15.6851 7.88099 16.119C8.31491 16.5529 9.01842 16.5529 9.45234 16.119L12 13.5714L14.5477 16.119C14.9816 16.5529 15.6851 16.5529 16.119 16.119C16.5529 15.6851 16.5529 14.9816 16.119 14.5477L13.5713 12L16.119 9.45234Z"
										fill="#d63638"/>
								</svg>

								<p>
									<?php
									printf( wp_kses_post( __( '<strong>Your FunnelKit Pro license has expired!</strong> Please renew your license to continue using premium features without interruption. <a href="https://funnelkit.com/my-account/?utm_source=WordPress&utm_campaign=FB+Lite+Plugin&utm_medium=Plugin+Inline+Notice">Renew Now</a> or <a href="%s">I have My License Key</a>', 'funnel-builder-pro' ) ), esc_url( admin_url( 'admin.php?page=bwf&path=/settings/woofunnels_general_settings' ) ) );
									?>
								</p>
							</div>
						</td>
					</tr>
					<?php
				}
			}

			if ( $render_css ) {
				?>
				<style>
					tr[data-slug="funnelkit-funnel-builder-pro"] th,
					tr[data-slug="funnelkit-funnel-builder-pro"] td {
						box-shadow: none !important;
					}

					.fb_license_notice .update-message {
						position: relative;
					}

					.fb_license_notice .update-message svg {
						position: absolute;
						left: 12px;
						top: 5px;
						width: 20px;
					}

					.fb_license_notice .update-message p {
						padding-left: 14px !important;
					}

					.fb_license_notice.fbk_renew .update-message svg {
						top: 4px;
						width: 16px;
					}

					.fb_license_notice .update-message.notice-error p::before {
						content: "";
					}
				</style>
				<?php
			}
		}

		/**
		 * "Renew Expired License" action link on the Pro plugin row.
		 *
		 * @param array  $actions     Existing action links.
		 * @param string $plugin_file Plugin basename of the row being rendered.
		 *
		 * @return array
		 */
		public function plugin_action_link( $actions, $plugin_file ) {
			$new_action = array();

			if ( ! is_array( $actions ) ) {
				$actions = array();
			}

			if ( ! defined( 'WFFN_PRO_PLUGIN_BASENAME' ) || $plugin_file !== WFFN_PRO_PLUGIN_BASENAME ) {
				return $actions;
			}

			if ( true === self::is_pro_folder_renamed() ) {
				$new_action['folder_renamed'] = '<span class="wffn_folder_renamed" style="color: #d63638;">' . esc_html__( 'License inactive', 'funnel-builder-pro' ) . '</span>';

				return array_merge( $new_action, $actions );
			}

			if ( class_exists( 'WooFunnels_licenses' ) ) {
				WooFunnels_licenses::get_instance()->get_plugins_list();
			}

			if ( $this->is_expired() ) {
				$link                          = esc_url( 'https://funnelkit.com/my-account/?utm_source=WordPress&utm_campaign=FB+Lite+Plugin&utm_medium=Plugin+Inline+Notice' );
				$new_action['renewal_license'] = '<style>tr[data-slug="funnelkit-funnel-builder-pro"] .renewal_license{position: relative}tr[data-slug="funnelkit-funnel-builder-pro"] .renewal_license svg{position:absolute;top:1px;left:0}</style><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_835_18634)">
<path d="M10.2957 1.75368C10.1928 1.76698 10.0983 1.81626 10.0298 1.89236C9.9613 1.96846 9.92347 2.06621 9.92333 2.16745C9.92336 2.18598 9.92462 2.2045 9.92711 2.22287L10.0257 2.94891C9.06453 2.28807 7.90358 1.96102 6.729 2.02021C5.55442 2.0794 4.43425 2.52139 3.54808 3.27532C2.66191 4.02926 2.06109 5.05145 1.84194 6.17802C1.6228 7.30459 1.79802 8.47027 2.33952 9.48816C2.88102 10.5061 3.75743 11.3172 4.82823 11.7915C5.89903 12.2659 7.10219 12.3759 8.2448 12.104C9.38741 11.8322 10.4033 11.1941 11.1295 10.2922C11.8558 9.39026 12.2504 8.2767 12.25 7.13005C12.25 7.0192 12.2048 6.9129 12.1244 6.83452C12.044 6.75614 11.935 6.7121 11.8213 6.7121C11.7076 6.7121 11.5986 6.75614 11.5182 6.83452C11.4378 6.9129 11.3926 7.0192 11.3926 7.13005C11.3936 8.09095 11.0634 9.02432 10.4552 9.78043C9.847 10.5366 8.9959 11.0716 8.03846 11.2997C7.08103 11.5279 6.07273 11.4359 5.17532 11.0386C4.27792 10.6412 3.5434 9.96156 3.0896 9.10856C2.6358 8.25557 2.48902 7.2787 2.6728 6.33466C2.85658 5.39061 3.36028 4.5341 4.10308 3.90251C4.84589 3.27093 5.78476 2.90087 6.76909 2.85171C7.75342 2.80255 8.72617 3.07712 9.53129 3.63139L8.58699 3.6711C8.47664 3.67573 8.37239 3.7217 8.29596 3.79943C8.21953 3.87715 8.17681 3.98064 8.17672 4.08832C8.17672 4.09444 8.17692 4.10046 8.17713 4.10658C8.18202 4.21732 8.23183 4.32162 8.3156 4.39656C8.39938 4.47149 8.51025 4.51092 8.62384 4.50616L10.6202 4.4223C10.6265 4.42202 10.6322 4.42021 10.6384 4.41973C10.6427 4.41935 10.647 4.41973 10.6515 4.41922C10.6536 4.41897 10.6557 4.41933 10.6581 4.41904H10.6583C10.6669 4.41791 10.6754 4.41498 10.684 4.41333C10.6969 4.41089 10.7094 4.40825 10.7218 4.40472C10.7283 4.40286 10.7351 4.402 10.7416 4.39983C10.7497 4.39711 10.7568 4.39281 10.7646 4.38965C10.7761 4.38499 10.7874 4.38027 10.7984 4.37469C10.8048 4.37139 10.8118 4.36918 10.8181 4.36552C10.8261 4.36098 10.8328 4.35507 10.8404 4.34995C10.8495 4.34387 10.8585 4.33775 10.8671 4.33102C10.8698 4.32893 10.8728 4.32725 10.8754 4.3251C10.8791 4.32209 10.8833 4.32011 10.8869 4.31695C10.8942 4.31068 10.8995 4.30314 10.9062 4.29649C10.913 4.28985 10.9188 4.2837 10.9248 4.27696C10.9307 4.27021 10.9373 4.26461 10.9427 4.25769C10.9494 4.24916 10.9543 4.23988 10.9603 4.23096C10.9643 4.22486 10.968 4.21865 10.9718 4.21234C10.9765 4.20436 10.9822 4.197 10.9864 4.18871C10.9911 4.17924 10.9942 4.16929 10.9982 4.15957C11.0012 4.15253 11.0037 4.14543 11.0062 4.1382C11.0092 4.12952 11.0132 4.12129 11.0156 4.11239C11.0182 4.10309 11.0191 4.09358 11.021 4.08416C11.0229 4.07473 11.0244 4.06535 11.0256 4.0559C11.0267 4.04784 11.0288 4.0401 11.0293 4.03191C11.0299 4.0233 11.0289 4.01474 11.0289 4.00608C11.0289 3.99961 11.0304 3.99342 11.0302 3.98686C11.0299 3.9803 11.028 3.97482 11.0275 3.96864C11.0272 3.9655 11.0275 3.96237 11.0271 3.95925C11.0267 3.95614 11.0272 3.95298 11.0268 3.94991V3.94916V3.94849L10.7773 2.11314C10.7699 2.05869 10.7516 2.00619 10.7234 1.95864C10.6952 1.9111 10.6577 1.86944 10.613 1.83605C10.5682 1.80266 10.5172 1.7782 10.4628 1.76407C10.4083 1.74994 10.3516 1.74641 10.2957 1.75368Z" fill="#C5443F"/>
</g>
<defs>
<clipPath id="clip0_835_18634">
<rect width="14" height="14" fill="white"/>
</clipPath>
</defs>
</svg>
<a href="' . $link . '" class="wffn_renew_license" style="color: #d63638;padding-left: 20px;">' . __( 'Renew Expired License', 'funnel-builder-pro' ) . '</a>';
			}

			return array_merge( $new_action, $actions );
		}
	}

}
