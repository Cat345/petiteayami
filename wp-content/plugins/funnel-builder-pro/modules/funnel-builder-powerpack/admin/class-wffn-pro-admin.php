<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Admin' ) ) {
	/**
	 * Class to initiate admin functionality
	 * Class WFFN_Pro_Admin
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Admin {

		private static $ins = null;

		/**
		 * Option holding the expiry-email cadence state:
		 * array( 'stage' => int, 'last_sent' => timestamp, 'period' => string ).
		 */
		const LICENSE_EXPIRY_MAIL_OPTION = 'wffn_license_expiry_mail_schedule';

		/**
		 * WFFN_Pro_Admin constructor.
		 */
		public function __construct() {
			add_filter( 'wffn_funnel_settings', array( $this, 'funnel_settings_localized' ) );
			add_filter( 'bwf_settings_config', array( $this, 'add_utm_track_setting' ) );
			if ( $this->is_app_screen() ) {
				include_once plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'admin/class-wffn-pro-react-app.php';
				add_filter( 'wffn_localized_text_admin', array( $this, 'add_license_related_code' ) );
				add_action( 'admin_enqueue_scripts', array( WFFN_Pro_React_App::get_instance(), 'maybe_prevent_lite_and_enqueue_pro' ), 5 );

			}

			add_action( 'init', array( $this, 'maybe_add_notice_backward_compat' ) );
			add_action( 'init', array( $this, 'maybe_register_lite_compat_notice' ), 20 );
			add_action( 'init', array( $this, 'register_license_admin_ui' ), 20 );
			add_action( 'rest_api_init', array( $this, 'register_license_routes' ) );
			add_action( 'fk_license_expired', array( $this, 'maybe_process_license_expiry_email' ), 10, 2 );

			add_filter( 'wffn_rest_tools_list', array( $this, 'add_license_expiry_email_tool' ) );
			add_filter( 'wffn_rest_tools_action_args', array( $this, 'add_license_expiry_email_tool_arg' ) );
			add_filter( 'wffn_rest_tools_action_response', array( $this, 'handle_license_expiry_email_tool' ), 10, 2 );

		}

		/**
		 * Loads the licence expiry mail controller on demand.
		 *
		 * @return bool Whether the controller is available.
		 */
		private function load_license_expiry_mail_controller() {
			if ( ! class_exists( 'WFFN_License_Expiry_Mail_controller' ) && defined( 'WFFN_PRO_PLUGIN_DIR' ) ) {
				require_once WFFN_PRO_PLUGIN_DIR . '/includes/class-wffn-license-expiry-mail-controller.php';
			}

			return class_exists( 'WFFN_License_Expiry_Mail_controller' );
		}

		/**
		 * Adds the licence expiry email switch to the Tools screen.
		 *
		 * @param array $tools Tools listed on the screen.
		 *
		 * @return array
		 */
		public function add_license_expiry_email_tool( $tools ) {
			if ( ! $this->load_license_expiry_mail_controller() ) {
				return $tools;
			}

			$tools[] = array(
				'title' => __( 'License Expiry Email Notification', 'funnel-builder-pro' ),
				'desc'  => __( 'This action controls whether the site admins receive license expiry email from the website\'s own configured SMTP service.', 'funnel-builder-pro' ),
				'cta'   => array(
					'type'         => 'toggle',
					'value'        => WFFN_License_Expiry_Mail_controller::is_notification_enabled(),
					'text_enable'  => __( 'License Expiry Email Enabled', 'funnel-builder-pro' ),
					'text_disable' => __( 'License Expiry Email Disabled', 'funnel-builder-pro' ),
					'slug'         => 'wffn_license_expiry_email',
				),
			);

			return $tools;
		}

		/**
		 * Declares the request argument carrying the switch value.
		 *
		 * @param array $args Route arguments.
		 *
		 * @return array
		 */
		public function add_license_expiry_email_tool_arg( $args ) {
			$args['wffn_license_expiry_email'] = array(
				'description'       => __( 'Toggle the license expiry email notification', 'funnel-builder-pro' ),
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			);

			return $args;
		}

		/**
		 * Stores the switch value posted from the Tools screen.
		 *
		 * @param array           $resp    Response prepared by the free plugin.
		 * @param WP_REST_Request $request Current request.
		 *
		 * @return array
		 */
		public function handle_license_expiry_email_tool( $resp, $request ) {
			if ( ! isset( $request['wffn_license_expiry_email'] ) || ! $this->load_license_expiry_mail_controller() ) {
				return $resp;
			}

			$enabled = WFFN_License_Expiry_Mail_controller::set_notification_state( $request['wffn_license_expiry_email'] );

			return array(
				'status' => true,
				'msg'    => $enabled
					? __( 'License expiry email notification enabled.', 'funnel-builder-pro' )
					: __( 'License expiry email notification disabled.', 'funnel-builder-pro' ),
			);
		}

		public function is_app_screen() {
			if ( ! is_admin() ) {
				return false;
			}
			if ( ! function_exists( 'WFFN_Core' ) || ! is_object( WFFN_Core()->admin ) ) {
				return false;
			}
			$lite_admin = WFFN_Core()->admin;

			if ( ! method_exists( $lite_admin, 'is_wffn_flex_page' ) || ! method_exists( $lite_admin, 'get_license_config' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Hands the licence admin surface its hooks. Deferred to init so the class
		 * is not read on requests that never reach an admin screen or the settings
		 * REST route.
		 *
		 * @return void
		 */
		public function register_license_admin_ui() {
			if ( class_exists( 'WFFN_License_Admin_UI' ) ) {
				WFFN_License_Admin_UI::get_instance();
			}
		}

		/**
		 * Registers the licence REST routes. Only reached on a REST request, so the
		 * controller is never loaded otherwise.
		 *
		 * @return void
		 */
		public function register_license_routes() {
			if ( class_exists( 'WFFN_REST_Licenses' ) ) {
				WFFN_REST_Licenses::get_instance()->register_routes();
			}
		}

		/**
		 * Warns when the installed free plugin is older than this release pairs with.
		 *
		 * The version comparison is two constants, so it happens here rather than
		 * inside the notice class -- on a correctly paired install, which is the
		 * normal case, that class is never loaded at all.
		 *
		 * Not gated on is_admin(): the notice list is also read over REST, where
		 * is_admin() is false.
		 *
		 * @return void
		 */
		public function maybe_register_lite_compat_notice() {
			if ( ! defined( 'WFFN_VERSION' ) || ! defined( 'WFFN_PRO_MIN_LITE_VERSION' ) ) {
				return;
			}

			if ( ! version_compare( WFFN_VERSION, WFFN_PRO_MIN_LITE_VERSION, '<' ) ) {
				return;
			}

			if ( class_exists( 'WFFN_Lite_Compat_Notice' ) ) {
				WFFN_Lite_Compat_Notice::get_instance()->maybe_register_notice();
			}
		}

		/**
		 * @return WFFN_Pro_Admin|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function funnel_settings_localized( $settings ) {
			$settings_pro = array(
				'override_tracking_ids' => array(
					array(
						'text' => __( 'Facebook Pixel ID', 'funnel-builder-powerpack' ),
						'key'  => 'fb_pixel_key',
					),

				),

			);
			array_push(
				$settings_pro['override_tracking_ids'],
				array(
					'text' => __( 'Conversion API Access Token', 'funnel-builder-powerpack' ),
					'key'  => 'conversion_api_access_token',
					'type' => 'textarea',
				)
			);
			array_push(
				$settings_pro['override_tracking_ids'],
				array(
					'text' => __( 'Conversion API Test event code', 'funnel-builder-powerpack' ),
					'key'  => 'conversion_api_test_event_code',
				)
			);
			$settings_pro['override_tracking_ids'][] = array(
				'text' => __( 'Google Analytics ID', 'funnel-builder-powerpack' ),
				'key'  => 'ga_key',
			);
			$settings_pro['override_tracking_ids'][] = array(
				'text' => __( 'Google Ads Conversion ID', 'funnel-builder-powerpack' ),
				'key'  => 'gad_key',
			);
			$settings_pro['override_tracking_ids'][] = array(
				'text' => __( 'Google Ads Conversion Label', 'funnel-builder-powerpack' ),
				'key'  => 'gad_conversion_label',
			);
			$settings_pro['override_tracking_ids'][] = array(
				'text' => __( 'Pinterest Tag ID', 'funnel-builder-powerpack' ),
				'key'  => 'pint_key',
			);
			$settings_pro['override_tracking_ids'][] = array(
				'text' => __( 'TikTok Pixel ID', 'funnel-builder-powerpack' ),
				'key'  => 'tiktok_pixel',
			);
			$settings_pro['override_tracking_ids'][] = array(
				'text' => __( 'Snapchat Pixel ID', 'funnel-builder-powerpack' ),
				'key'  => 'snapchat_pixel',
			);

			return array_merge( $settings, $settings_pro );
		}

		public function add_utm_track_setting( $settings ) {

			if ( ! is_array( $settings ) || ! isset( $settings['utm_parameter'] ) || ! isset( $settings['utm_parameter']['fields'] ) ) {

				return $settings;
			}

			$settings['utm_parameter']['fields'][1] = array(
				'key'          => 'track_utms',
				'type'         => 'checkbox',
				'is_pro'       => true,
				'label'        => __( 'Enable First Party Conversion Tracking', 'funnel-builder-pro' ),
				'styleClasses' => array( 'wfacp_checkbox_wrap', 'wfacp_setting_track_and_events_end' ),
				'hint'         => __( 'Uncover the UTMs and traffic sources that bring conversions. Get additional insights such as Time to convert, Device and Browser details.', 'funnel-builder-pro' ),
			);

			return $settings;
		}

		public function add_license_related_code( $texts ) {
			$texts['license'] = array(
				'states' => array(
					1 => array(
						'notice' => array(
							'text'           => __( 'Your FunnelKit Pro license is not activated!', 'funnel-builder-pro' ),
							'primary_action' => __( 'Activate License', 'funnel-builder-pro' ),
						),

					),
					2 => array(
						'notice' => array(
							'text'           => __( '<strong>FunnelKit Pro is Not Fully Activated!</strong> Please activate your license to continue using premium features without interruption.', 'funnel-builder-pro' ),
							'primary_action' => __( 'Activate License', 'funnel-builder-pro' ),
						),
						'modal'  => array(
							'heading'         => __( 'FunnelKit PRO is not fully Activated', 'funnel-builder-pro' ),
							'sub_heading'     => __( 'Without an active license your checkout is not affected. However, you are missing on', 'funnel-builder-pro' ),
							'features'        => array(
								__( 'New revenue boosting features', 'funnel-builder-pro' ),
								__( 'Critical security updates', 'funnel-builder-pro' ),
								__( 'Revenue from upsells, order bumps and other premium features', 'funnel-builder-pro' ),
								__( 'Access to dedicated support', 'funnel-builder-pro' ),
							),
							'text_before_cta' => __( 'Don\'t miss out on the additional revenue. This problem is easy to fix.', 'funnel-builder-pro' ),
							'primary_action'  => __( 'Activate License', 'funnel-builder-pro' ),
						),

					),
					3 => array(
						'notice' => array(
							'text'             => __( '<strong>Your FunnelKit Pro license has expired!</strong> We\'ve extended its features until {{TIME_GRACE_EXPIRED}}, after which they\'ll be limited.', 'funnel-builder-pro' ),
							'primary_action'   => __( 'Renew Now ', 'funnel-builder-pro' ),
							'secondary_action' => __( 'I have My License Key', 'funnel-builder-pro' ),
						),

					),
					4 => array(
						'notice' => array(
							'text'             => __( '<strong>Your FunnelKit Pro license has expired!</strong> Please renew your license to continue using premium features without interruption.', 'funnel-builder-pro' ),
							'primary_action'   => __( 'Renew Now ', 'funnel-builder-pro' ),
							'secondary_action' => __( 'I have My License Key', 'funnel-builder-pro' ),
						),
						'modal'  => array(
							'heading'          => __( 'Your License has Expired', 'funnel-builder-pro' ),
							'sub_heading'      => __( 'Without an active license your checkout is not affected. However, you are missing on', 'funnel-builder-pro' ),
							'features'         => array(
								__( 'New revenue boosting features', 'funnel-builder-pro' ),
								__( 'Critical security updates', 'funnel-builder-pro' ),
								__( 'Revenue from upsells, order bumps and other premium features', 'funnel-builder-pro' ),
								__( 'Access to dedicated support', 'funnel-builder-pro' ),
							),
							'text_before_cta'  => __( 'Don\'t miss out on the additional revenue. This problem is easy to fix.', 'funnel-builder-pro' ),
							'primary_action'   => __( 'Renew Now ', 'funnel-builder-pro' ),
							'secondary_action' => __( 'I have My License Key', 'funnel-builder-pro' ),
						),

					),
				),
			);

			if ( function_exists( 'wc_price' ) ) {
				$expiry = WFFN_License_Config::get_instance()->get_expiry();
				if ( ( ! empty( $expiry ) && ( strtotime( $expiry ) < time() ) ) || false === WFFN_License_Config::get_instance()->is_license_active() ) {
					global $wpdb;
					$checkout_total = $wpdb->get_results( $wpdb->prepare( 'SELECT SUM(`value`) as `total`,COUNT(`id`) as `orders` from ' . $wpdb->prefix . 'bwf_conversion_tracking WHERE `funnel_id`!=%d', 0 ) );

					if ( ! is_null( $checkout_total ) ) {
						$texts['totals'] = array(
							'total'     => wc_price( $checkout_total[0]->total ),
							'raw_total' => $checkout_total[0]->total,
							'orders'    => $checkout_total[0]->orders,

						);
					}
				}
			}

			return $texts;
		}

		public function maybe_add_notice_backward_compat() {
			if ( defined( 'WFFN_VERSION' ) && ( version_compare( WFFN_VERSION, '3.0.0 beta', '>=' ) || version_compare( WFFN_VERSION, '2.16.1', '<=' ) ) ) {
				return;
			}
			WFFN_Admin_Notifications::get_instance()->notifs[] = array(
				'key'           => 'update_3_0',
				'content'       => '<div class="bwf-notifications-message current">
					<h3 class="bwf-notifications-title">' . __( 'Update Funnel Builder to version 3.0.0', 'funnel-builder' ) . '</h3>
					<p class="bwf-notifications-content">' . __( 'It seems that you are running an older version of Funnel Builder. For a smoother experience, update Funnel Builder  to version 3.0.', 'funnel-builder' ) . '</p>
				</div>',

				'customButtons' => array(
					array(
						'label'     => __( 'Go to plugin updates', 'funnel-builder' ),
						'href'      => admin_url( 'plugins.php?s=funnel+builder' ),
						'className' => 'is-primary',
						'target'    => '__blank',
					),

				),
			);
		}

		/**
		 * Maybe process license expiry email.
		 *
		 * @param array  $plugin_info Plugin information.
		 * @param string $slug Plugin slug.
		 */
		public function maybe_process_license_expiry_email( $plugin_info, $slug ) {
			try {
				if ( ! $this->load_license_expiry_mail_controller() ) {
					return;
				}

				/**
				 * The site can switch this notification off from the Tools screen.
				 */
				if ( ! WFFN_License_Expiry_Mail_controller::is_notification_enabled() ) {
					return;
				}

				if ( is_null( $plugin_info ) ) {
					return;
				}

				$valid_funnelkit_license_hashes = array_values( WFFN_License_Config::get_instance()->get_license_basename_sha1() );
				if ( ! in_array( $slug, $valid_funnelkit_license_hashes, true ) ) {
					return;
				}

				$license = WooFunnels_licenses::get_instance();
				$license->get_plugins_list();
				$expiry = WFFN_License_Config::get_instance()->get_expiry();

				if ( ( ! empty( $expiry ) && ( strtotime( $expiry ) < time() ) ) || false === WFFN_License_Config::get_instance()->is_license_active() ) {

					// fk_license_expired re-fires on every license check, and once per expired slug within
					// the same request, so without a gate the admin would get repeat expiry emails. Send
					// three reminders per expiry period: straight away, after 3 days, after 10 more, then stop.
					if ( ! $this->should_send_license_expiry_email( $expiry ) ) {
						return;
					}

					$email_controller = new WFFN_License_Expiry_Mail_controller();

					$to      = get_option( 'admin_email' );
					$subject = __( 'ATTENTION: FunnelKit License Has Expired', 'funnel-builder-pro' );
					$body    = $email_controller->get_content_html();
					$headers = array( 'Content-Type: text/html; charset=UTF-8' );

					wp_mail( $to, $subject, $body, $headers ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail

					$this->mark_license_expiry_email_sent( $expiry );
				}
			} catch ( Exception $e ) {
				WFFN_Core()->logger->log( 'error', $e->getMessage() );
			}
		}

		/**
		 * Days to wait before each expiry reminder, measured from the previous send.
		 * The first entry is 0 so the first reminder goes out as soon as the expiry
		 * action fires. The list is also the total count of reminders per expiry
		 * period: once it is exhausted the sequence stops. Filterable.
		 *
		 * @return int[]
		 */
		private function get_license_expiry_email_intervals() {
			$defaults  = array( 0, 3, 10 );
			$intervals = apply_filters( 'wffn_license_expiry_email_intervals', $defaults );

			if ( ! is_array( $intervals ) || empty( $intervals ) ) {
				return $defaults;
			}

			return array_values( array_map( 'absint', $intervals ) );
		}

		/**
		 * Key identifying the expiry period the cadence belongs to. A different key means
		 * a fresh sequence, which is how a renewed-then-lapsed license gets its first mail
		 * straight away again.
		 *
		 * get_expiry() synthesises a rolling "+1 year" date when the service reports no
		 * expiry, so only a date that has actually passed is stable enough to key off;
		 * everything else collapses to a single 'inactive' bucket.
		 *
		 * @param string $expiry License expiry date string.
		 *
		 * @return string
		 */
		private function get_license_expiry_period_key( $expiry ) {
			$expiry_ts = empty( $expiry ) ? false : strtotime( $expiry );

			if ( ! empty( $expiry_ts ) && $expiry_ts < time() ) {
				return (string) $expiry_ts;
			}

			return 'inactive';
		}

		/**
		 * Read the cadence state, discarding it if it belongs to an earlier expiry period.
		 *
		 * @param string $expiry License expiry date string.
		 *
		 * @return array{stage:int,last_sent:int,period:string}
		 */
		private function get_license_expiry_email_state( $expiry ) {
			$period = $this->get_license_expiry_period_key( $expiry );
			$state  = get_option( self::LICENSE_EXPIRY_MAIL_OPTION, array() );

			if ( ! is_array( $state ) || ! isset( $state['period'] ) || $state['period'] !== $period ) {
				return array(
					'stage'     => 0,
					'last_sent' => 0,
					'period'    => $period,
				);
			}

			return array(
				'stage'     => isset( $state['stage'] ) ? absint( $state['stage'] ) : 0,
				'last_sent' => isset( $state['last_sent'] ) ? absint( $state['last_sent'] ) : 0,
				'period'    => $period,
			);
		}

		/**
		 * Whether enough time has elapsed to send the next expiry reminder.
		 *
		 * @param string $expiry License expiry date string.
		 *
		 * @return bool
		 */
		private function should_send_license_expiry_email( $expiry ) {
			$state = $this->get_license_expiry_email_state( $expiry );

			// Nothing sent for this expiry period yet, so the first reminder goes out now.
			if ( $state['last_sent'] <= 0 ) {
				return true;
			}

			$intervals = $this->get_license_expiry_email_intervals();

			// Every reminder for this expiry period has gone out; the sequence ends here.
			if ( $state['stage'] >= count( $intervals ) ) {
				return false;
			}

			return ( time() - $state['last_sent'] ) >= ( $intervals[ $state['stage'] ] * DAY_IN_SECONDS );
		}

		/**
		 * Advance the cadence one step after a reminder goes out. This is also what
		 * collapses the repeat fk_license_expired fires (one per expired slug) within a
		 * single request into one send, since last_sent becomes now.
		 *
		 * @param string $expiry License expiry date string.
		 */
		private function mark_license_expiry_email_sent( $expiry ) {
			$state = $this->get_license_expiry_email_state( $expiry );

			update_option(
				self::LICENSE_EXPIRY_MAIL_OPTION,
				array(
					'stage'     => $state['stage'] + 1,
					'last_sent' => time(),
					'period'    => $state['period'],
				),
				false
			);
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'admin', 'WFFN_Pro_Admin' );
	}
}
