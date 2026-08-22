<?php //phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

use Elementor\Plugin;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Optin_Pages_Elementor' ) ) {
	/**
	 * Class WFFN_Pro_Optin_Pages_Elementor
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Optin_Pages_Elementor {

		private static $ins             = null;
		protected $template_type        = array();
		protected $design_template_data = array();
		protected $templates            = array();

		/**
		 * WFFN_Optin_Pages_Elementor constructor.
		 */
		public function __construct() {
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
				add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ), 11 );
			} else {
				add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ), 11 );
			}

			/**
			 * Declare our widget to the Lite optin module, which owns the
			 * `elementor/document/config` filter that hides FunnelKit optin widgets from the
			 * Elementor panel (and its search) on documents that are not optin pages.
			 */
			add_filter( 'wffn_optin_elementor_widget_names', array( $this, 'add_widget_name' ) );
		}

		/**
		 * @param array $names
		 *
		 * @return array
		 */
		public function add_widget_name( $names ) {
			$names   = is_array( $names ) ? $names : array();
			$names[] = 'wffn-optin-popup';

			return array_values( array_unique( $names ) );
		}

		/**
		 * @return WFFN_Pro_Optin_Pages_Elementor|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Register our widget on every request.
		 *
		 * The `did_action()` guard is a dependency check on the Lite optin module having loaded -
		 * that action now fires unconditionally on every `elementor/widgets/register`, so this no
		 * longer gates registration on the current post being an optin page. See the note in
		 * WFFN_Optin_Pages_Elementor::register_widgets() for why per-request context gating broke
		 * Elementor's element cache.
		 */
		public function register_widgets() {
			if ( did_action( 'wffn_optin_elementor_lite_loaded' ) ) {
				require_once __DIR__ . '/widgets/class-elementor-wffn-pro-optin-popup-widget.php';
				if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
					\Elementor\Plugin::instance()->widgets_manager->register( new \Elementor_WFFN_Pro_Optin_Popup_Widget() );
				} else {
					\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \Elementor_WFFN_Pro_Optin_Popup_Widget() );
				}
			}
		}
	}

	WFFN_Pro_Optin_Pages_Elementor::get_instance();
}
