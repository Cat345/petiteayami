<?php //phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Class WFFN_Pro_Optin_Pages_Oxygen
 */
if ( ! class_exists( 'WFFN_Pro_Optin_Pages_Oxygen' ) ) {

	#[AllowDynamicProperties]

class WFFN_Pro_Optin_Pages_Oxygen {

		private static $ins = null;

		/**
		 * WFFN_Pro_Optin_Pages_Oxygen constructor.
		 */
		public function __construct() {
			add_filter( 'wffn_op_oxy_modules', [ $this, 'register_widget' ] );
		}

		/**
		 * @return WFFN_Pro_Optin_Pages_Oxygen|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function register_widget( $args ) {
			$args['optin_form_popup'] = array(
				'name' => __( 'Optin Form Popup', 'woofunnels-aero-checkout' ),
				'path' => __DIR__ . '/widgets/class-oxygen-wffn-pro-optin-popup-widget.php',
			);

			return $args;
		}
	}

	WFFN_Pro_Optin_Pages_Oxygen::get_instance();
}
