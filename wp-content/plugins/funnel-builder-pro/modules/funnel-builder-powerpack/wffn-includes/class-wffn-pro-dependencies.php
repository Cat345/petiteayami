<?php
if ( ! class_exists( 'WFFN_Pro_Dependencies' ) ) {
	/**
	 * WFFN Dependency Checker
	 * Check if primary plugin is active
	 */
	class WFFN_Pro_Dependencies {

		private static $active_plugins;

		public static function init() {

			self::$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}
		}

		public static function wffn_lite_active_check() {
			if ( ! self::$active_plugins ) {
				self::init();
			}

			return in_array( 'funnel-builder/funnel-builder.php', self::$active_plugins, true ) || array_key_exists( 'funnel-builder/funnel-builder.php', self::$active_plugins );
		}

	}
}