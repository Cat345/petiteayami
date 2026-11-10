<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! class_exists( 'WFFN_Features' ) ) {
	/**
	 * The optional admin features of this plugin and whether each may open on this site.
	 *
	 * A feature's screen lives in the admin app. This plugin enqueues that app itself
	 * unless something else takes the enqueue over and serves its own build of the app;
	 * whoever does so says through HELD_FILTER which features that build cannot run. The
	 * admin surfaces this plugin owns for a feature (its menu entries) open only for a
	 * feature the app being served can run and the site has not switched off.
	 */
	class WFFN_Features {

		/**
		 * Filter: the feature keys to hold back, or null when nothing has answered.
		 */
		const HELD_FILTER = 'wffn_features_held';

		/**
		 * The hook on which this plugin enqueues the admin app, and its priority.
		 */
		const APP_ENQUEUE_HOOK     = 'admin_enqueue_scripts';
		const APP_ENQUEUE_PRIORITY = 99;

		/**
		 * @return string[]
		 */
		public static function all() {
			return array( 'announcements', 'funnelkit_ai' );
		}

		/**
		 * Whether a feature can open on this site: it is a known key, this site has not
		 * switched it off, and the admin app being served can run it.
		 *
		 * With no answer on HELD_FILTER, the app can run every feature only when it is this
		 * plugin's own: a build served by something that never answered cannot be assumed
		 * to carry the feature's screen. Ask on APP_ENQUEUE_HOOK or later, once whatever
		 * serves the app has claimed the enqueue.
		 *
		 * @param string $key Feature key.
		 *
		 * @return bool
		 */
		public static function is_available( $key ) {
			if ( ! in_array( $key, self::all(), true ) || in_array( $key, self::disabled(), true ) ) {
				return false;
			}

			$held = apply_filters( self::HELD_FILTER, null );

			if ( null === $held ) {
				return self::serves_admin_app();
			}

			return ! in_array( $key, (array) $held, true );
		}

		/**
		 * Whether this plugin's own enqueue of the admin app is still in place, i.e. nothing
		 * has taken over serving the app.
		 *
		 * @return bool
		 */
		public static function serves_admin_app() {
			if ( ! function_exists( 'WFFN_Core' ) || ! is_object( WFFN_Core()->admin ) ) {
				return false;
			}

			return self::APP_ENQUEUE_PRIORITY === has_action( self::APP_ENQUEUE_HOOK, array( WFFN_Core()->admin, 'admin_enqueue_assets' ) );
		}

		/**
		 * Features this site has switched off.
		 *
		 * @return string[]
		 */
		public static function disabled() {
			$disabled = array();

			if ( class_exists( 'WFFN_REST_Announcements' ) && ! WFFN_REST_Announcements::is_enabled() ) {
				$disabled[] = 'announcements';
			}

			return $disabled;
		}
	}
}
