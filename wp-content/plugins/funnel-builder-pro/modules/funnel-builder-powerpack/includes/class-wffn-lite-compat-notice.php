<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_Lite_Compat_Notice
 *
 * Warns the store owner when the installed Funnel Builder (free) build is older
 * than the build this Pro release pairs with. Pro keeps loading in that state,
 * but functionality that lives in the free plugin can be missing or behave
 * differently, so the notice asks for a Funnel Builder update.
 *
 * The notice is rendered by the free plugin's WFFN_Admin_Notifications, through
 * its filter when available and through its public notification bag on older
 * builds -- which is exactly the case this notice targets.
 */
if ( ! class_exists( 'WFFN_Lite_Compat_Notice' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Lite_Compat_Notice {

		/**
		 * @var WFFN_Lite_Compat_Notice|null
		 */
		private static $ins = null;

		/**
		 * WFFN_Pro_Admin registers this on init and only reaches for this class
		 * when the installed free plugin is actually behind, so there is nothing
		 * to hook here.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Lite_Compat_Notice|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Registers the notice with the free plugin when the installed build is behind.
		 *
		 * @return void
		 */
		public function maybe_register_notice() {
			if ( ! $this->is_lite_outdated() ) {
				return;
			}

			if ( ! class_exists( 'WFFN_Admin_Notifications' ) ) {
				return;
			}

			/**
			 * Builds carrying add_notification_data() also carry the filter, so the
			 * probe covers both. Filter first: it is evaluated when the list is read,
			 * so it cannot land too early or be dropped.
			 */
			if ( method_exists( 'WFFN_Admin_Notifications', 'add_notification_data' ) ) {
				add_filter( 'wffn_admin_notifications', array( $this, 'append_notice' ) );

				return;
			}

			$this->push_to_legacy_bag();
		}

		/**
		 * Whether the installed free plugin is older than the paired build.
		 *
		 * @return bool
		 */
		public function is_lite_outdated() {
			if ( ! defined( 'WFFN_VERSION' ) || ! defined( 'WFFN_PRO_MIN_LITE_VERSION' ) ) {
				return false;
			}

			return version_compare( WFFN_VERSION, WFFN_PRO_MIN_LITE_VERSION, '<' );
		}

		/**
		 * Filter callback for builds that expose the notification seam.
		 *
		 * @param array $notifs Registered notifications.
		 *
		 * @return array
		 */
		public function append_notice( $notifs ) {
			if ( ! is_array( $notifs ) ) {
				return $notifs;
			}

			$notice = $this->get_notice();

			foreach ( $notifs as $registered ) {
				if ( isset( $registered['key'] ) && $registered['key'] === $notice['key'] ) {
					return $notifs;
				}
			}

			$notifs[] = $notice;

			return $notifs;
		}

		/**
		 * Appends the notice on builds without the seam by using the public
		 * notification bag. prepare_notifications() adds to that bag rather than
		 * replacing it, so an entry queued here survives to the read.
		 *
		 * @return void
		 */
		private function push_to_legacy_bag() {
			if ( ! function_exists( 'WFFN_Core' ) ) {
				return;
			}

			$core = WFFN_Core();
			if ( ! is_object( $core ) || ! isset( $core->admin_notifications ) || ! is_object( $core->admin_notifications ) ) {
				return;
			}

			$notifications = $core->admin_notifications;
			if ( ! property_exists( $notifications, 'notifs' ) || ! is_array( $notifications->notifs ) ) {
				return;
			}

			$notice = $this->get_notice();

			foreach ( $notifications->notifs as $registered ) {
				if ( isset( $registered['key'] ) && $registered['key'] === $notice['key'] ) {
					return;
				}
			}

			$notifications->notifs[] = $notice;
		}

		/**
		 * The notification payload, in the shape WFFN_Admin_Notifications builds.
		 *
		 * @return array
		 */
		private function get_notice() {
			return array(
				'key'             => 'lite_outdated_' . str_replace( '.', '_', WFFN_PRO_MIN_LITE_VERSION ),
				'content'         => $this->get_content(),
				'customButtons'   => array(
					array(
						'label'     => __( 'Update Now', 'funnel-builder-pro' ),
						'href'      => admin_url( 'plugins.php?s=FunnelKit+Funnel+Builder' ),
						'className' => 'is-primary',
						'target'    => '__blank',
					),
				),
				'not_dismissible' => true,
				'index'           => 2,
			);
		}

		/**
		 * @return string
		 */
		private function get_content() {
			return '<div class="bwf-notifications-message current">
					<h3 class="bwf-notifications-title">' . esc_html__( 'Update FunnelKit Funnel Builder', 'funnel-builder-pro' ) . '</h3>
					<p class="bwf-notifications-content">' . sprintf( /* translators: 1: installed free plugin version, 2: required free plugin version */
					esc_html__( 'You are running Funnel Builder %1$s, which is older than your version of Funnel Builder Pro. Please update Funnel Builder to %2$s or newer so all of your funnel features keep working as expected.', 'funnel-builder-pro' ), esc_html( WFFN_VERSION ), esc_html( WFFN_PRO_MIN_LITE_VERSION ) ) . '</p>
				</div>';
		}
	}

}
