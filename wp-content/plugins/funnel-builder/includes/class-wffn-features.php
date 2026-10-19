<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! class_exists( 'WFFN_Features' ) ) {
	/**
	 * The features this plugin shares with Funnel Builder Pro.
	 *
	 * Pro compares these keys with its own list and switches off what the two cannot run
	 * together. This plugin only publishes the list.
	 */
	class WFFN_Features {

		/**
		 * @return string[]
		 */
		public static function all() {
			return array( 'announcements' );
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
