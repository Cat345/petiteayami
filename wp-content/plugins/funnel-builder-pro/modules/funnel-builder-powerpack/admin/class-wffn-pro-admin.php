<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Admin' ) ) {
	/**
	 * Class to initiate admin functionality
	 * Class WFFN_Pro_Admin
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Admin {

		private static $ins = null;

		/**
		 * WFFN_Pro_Admin constructor.
		 */
		public function __construct() {
			add_filter( 'wffn_funnel_settings', array( $this, 'funnel_settings_localized' ) );
			add_filter( 'bwf_settings_config', array( $this, 'add_utm_track_setting' ) );
			if ( is_admin() ) {
				add_filter( 'wffn_localized_text_admin', array( $this, 'add_license_related_code' ) );

			}


			add_action( 'init', array( $this, 'maybe_add_notice_backward_compat' ) );
			add_action( 'fk_license_expired', array( $this, 'maybe_process_license_expiry_email' ), 10, 2 );

		}

		/**
		 * @return WFFN_Pro_Admin|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function funnel_settings_localized( $settings ) {
			$settings_pro      = array(
				'override_tracking_ids' => array(
					array( 'text' => __( 'Facebook Pixel ID', 'funnel-builder-powerpack' ), 'key' => 'fb_pixel_key' ),

				)

			);
			array_push( $settings_pro['override_tracking_ids'], array(
				'text' => __( 'Conversion API Access Token', 'funnel-builder-powerpack' ),
				'key'  => 'conversion_api_access_token',
				'type' => 'textarea'
			) );
			array_push( $settings_pro['override_tracking_ids'], array(
				'text' => __( 'Conversion API Test event code', 'funnel-builder-powerpack' ),
				'key'  => 'conversion_api_test_event_code'
			) );
			$settings_pro['override_tracking_ids'][] = array( 'text' => __( 'Google Analytics ID', 'funnel-builder-powerpack' ), 'key' => 'ga_key' );
			$settings_pro['override_tracking_ids'][] = array( 'text' => __( 'Google Ads Conversion ID', 'funnel-builder-powerpack' ), 'key' => 'gad_key' );
			$settings_pro['override_tracking_ids'][] = array( 'text' => __( 'Google Ads Conversion Label', 'funnel-builder-powerpack' ), 'key' => 'gad_conversion_label' );
			$settings_pro['override_tracking_ids'][] = array( 'text' => __( 'Pinterest Tag ID', 'funnel-builder-powerpack' ), 'key' => 'pint_key' );
			$settings_pro['override_tracking_ids'][] = array( 'text' => __( 'TikTok Pixel ID', 'funnel-builder-powerpack' ), 'key' => 'tiktok_pixel' );
			$settings_pro['override_tracking_ids'][] = array( 'text' => __( 'Snapchat Pixel ID', 'funnel-builder-powerpack' ), 'key' => 'snapchat_pixel' );

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
			'styleClasses' => [ 'wfacp_checkbox_wrap', 'wfacp_setting_track_and_events_end' ],
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
							'primary_action' => __( 'Activate License', 'funnel-builder-pro' )
						),

					),
					2 => array(
						'notice' => array(
							'text'           => __( '<strong>FunnelKit Pro is Not Fully Activated!</strong> Please activate your license to continue using premium features without interruption.', 'funnel-builder-pro' ),
							'primary_action' => __( 'Activate License', 'funnel-builder-pro' )
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
						)

					),
					3 => array(
						'notice' => array(
							'text'             => __( '<strong>Your FunnelKit Pro license has expired!</strong> We\'ve extended its features until {{TIME_GRACE_EXPIRED}}, after which they\'ll be limited.', 'funnel-builder-pro' ),
							'primary_action'   => __( 'Renew Now ', 'funnel-builder-pro' ),
							'secondary_action' => __( 'I have My License Key', 'funnel-builder-pro' )
						),

					),
					4 => array(
						'notice' => array(
							'text'             => __( '<strong>Your FunnelKit Pro license has expired!</strong> Please renew your license to continue using premium features without interruption.', 'funnel-builder-pro' ),
							'primary_action'   => __( 'Renew Now ', 'funnel-builder-pro' ),
							'secondary_action' => __( 'I have My License Key', 'funnel-builder-pro' )
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
							'secondary_action' => __( 'I have My License Key', 'funnel-builder-pro' )
						)

					)
				)
			);

			if ( function_exists( 'wc_price' ) ) {
				$expiry = WFFN_Core()->admin->get_license_expiry();
				if ( ( ! empty( $expiry ) && ( strtotime( $expiry ) < current_time( 'timestamp', true ) ) ) || false === WFFN_Core()->admin->is_license_active() ) {
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
				'key'     => 'update_3_0',
				'content' => '<div class="bwf-notifications-message current">
					<h3 class="bwf-notifications-title">' . __( "Update Funnel Builder to version 3.0.0", "funnel-builder" ) . '</h3>
					<p class="bwf-notifications-content">' . __( "It seems that you are running an older version of Funnel Builder. For a smoother experience, update Funnel Builder  to version 3.0.", "funnel-builder" ) . '</p>
				</div>',

				'customButtons' => [
					[
						'label'     => __( "Go to plugin updates", "funnel-builder" ),
						'href'      => admin_url( "plugins.php?s=funnel+builder" ),
						'className' => 'is-primary',
						'target'    => '__blank',
					],

				]
			);
		}

		/**
		 * Maybe process license expiry email.
		 *
		 * @param array $plugin_info Plugin information.
		 * @param string $slug Plugin slug.
		 */
		public function maybe_process_license_expiry_email( $plugin_info, $slug ) {
			try {
				require_once WFFN_PRO_PLUGIN_DIR . '/includes/class-wffn-license-expiry-mail-controller.php';

				if ( is_null( $plugin_info ) ) {
					return;
				}

				if ( ! in_array( $slug, WFFN_Core()->admin->get_license_hashes(), true ) ) {
					return;
				}

				$license = WooFunnels_licenses::get_instance();
				$license->get_plugins_list();
				$expiry = WFFN_Core()->admin->get_license_expiry();

				if ( ( ! empty( $expiry ) && ( strtotime( $expiry ) < current_time( 'timestamp', true ) ) ) || false === WFFN_Core()->admin->is_license_active() ) {
					$email_controller = new WFFN_License_Expiry_Mail_controller();

					$to      = get_option( 'admin_email' );
					$subject = __( 'ATTENTION: FunnelKit License Has Expired', 'funnel-builder-pro' );
					$body    = $email_controller->get_content_html();
					$headers = array( 'Content-Type: text/html; charset=UTF-8' );

					wp_mail( $to, $subject, $body, $headers ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
				}
			} catch ( Exception $e ) {
				WFFN_Core()->logger->log( 'error', $e->getMessage() );
			}
		}

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'admin', 'WFFN_Pro_Admin' );
	}
}