<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! class_exists( 'WFFN_Lite_Handshake' ) ) {
	/**
	 * Which Pro features can run against the Funnel Builder installed alongside.
	 *
	 * A feature with `requires_lite` is Pro's own and is gated by that version. One without it
	 * is shared, and must appear in WFFN_Features::all() in the free plugin. A held feature is
	 * switched off silently. Gate code on WFFN_Lite_Handshake::is_available( 'key' ).
	 */
	class WFFN_Lite_Handshake {

		/**
		 * @var string[]|null
		 */
		private static $held = null;

		/**
		 * Every feature Pro provides.
		 *
		 * @return array<string,array{requires_lite?:string}>
		 */
		public static function features() {
			return array(
				'announcements' => array(),
			);
		}

		/**
		 * Features that cannot run against the installed free plugin.
		 *
		 * @return string[]
		 */
		public static function held() {
			if ( null !== self::$held ) {
				return self::$held;
			}

			$lite = class_exists( 'WFFN_Features' ) ? WFFN_Features::all() : array();
			$held = array();

			foreach ( self::features() as $key => $feature ) {
				$missing = isset( $feature['requires_lite'] )
					? version_compare( WFFN_VERSION, $feature['requires_lite'], '<' )
					: ! in_array( $key, $lite, true );

				if ( $missing ) {
					$held[] = $key;
				}
			}

			self::$held = $held;

			return self::$held;
		}

		/**
		 * Whether a feature can run against the installed free plugin. An unknown key is unavailable.
		 *
		 * @param string $key Feature key.
		 *
		 * @return bool
		 */
		public static function is_available( $key ) {
			return array_key_exists( $key, self::features() ) && ! in_array( $key, self::held(), true );
		}
	}
}
