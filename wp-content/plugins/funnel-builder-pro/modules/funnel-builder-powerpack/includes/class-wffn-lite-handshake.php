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
				'funnelkit_ai'  => array(),
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
		 * Answers the free plugin's WFFN_Features::HELD_FILTER, so the surfaces it owns (its
		 * admin menu entries) open only for the features this bundle can run against it.
		 *
		 * Held are the features of ours the free plugin is too old for (held()) and, the other
		 * way round, every feature the free plugin lists that this build does not know: a
		 * bundle cannot carry a screen it predates, so a newer free plugin must not open it.
		 *
		 * Pro builds before this method never answer, so the free plugin falls back to checking
		 * whether its own app enqueue was taken over (WFFN_Features::serves_admin_app). Once
		 * WFFN_MIN_PRO_VERSION is at or above the first Pro release carrying this method, that
		 * fallback and the APP_ENQUEUE_* constants can be dropped from the free plugin and a
		 * null answer there can simply mean "the app is its own".
		 *
		 * @param string[]|null $held Ignored; a filter default of null means "no answer".
		 *
		 * @return string[]
		 */
		public static function filter_held( $held = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			$lite    = class_exists( 'WFFN_Features' ) ? WFFN_Features::all() : array();
			$unknown = array_diff( $lite, array_keys( self::features() ) );

			return array_values( array_unique( array_merge( self::held(), $unknown ) ) );
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
