<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_MultiProduct' ) ) {
	/**
	 * Class WFOCU_MultiProduct
	 */
	final class WFOCU_MultiProduct {
		private static $_instance = null;

		/**
		 * Variable to have multiproduct core
		 *
		 * @var WFOCU_MultiProductCore
		 */
		public $template;

		/**
		 * WFOCU_MultiProduct constructor.
		 */
		public function __construct() {
			/**
			 * Load important variables and constants
			 */
			$this->define_multi_product_properties();
			$this->include_files();

			add_filter( 'wfocu_assets_scripts', array( $this, 'mp_assets_scripts' ), 10, 1 );
		}

		/**
		 * Creating instance
		 *
		 * @return WFOCU_MultiProduct|null
		 */
		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 *  Deifining module constants
		 */
		public function define_multi_product_properties() {
			define( 'WFOCU_MP_VERSION', '1.2.0' );
			define( 'WFOCU_MP_PLUGIN_FILE', __FILE__ );
			define( 'WFOCU_MP_TEMPLATE_DIR', plugin_dir_path( WFOCU_MP_PLUGIN_FILE ) . 'templates' );
			define( 'WFOCU_MP_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFOCU_MP_PLUGIN_FILE ) ) );
			define( 'WFOCU_MP_ADMIN_ASSETS_URL', WFOCU_MP_PLUGIN_URL . '/admin/assets' );
		}

		/**
		 *  Including files
		 */
		public function include_files() {

			include_once __DIR__ . '/includes/class-wfocu-mp.php';
			$this->template = WFOCU_MultiProductCore::get_instance();
		}

		/**
		 * Register scripts
		 *
		 * @param $script_array
		 *
		 * @return mixed
		 */
		public function mp_assets_scripts( $script_array ) {
			$script_array['mp-customizer'] = array(
				'path'      => plugin_dir_url( WFOCU_MP_PLUGIN_FILE ) . 'assets/js/customizer.js',
				'version'   => null,
				'in_footer' => true,
				'supports'  => array(
					'customizer',
					'customizer-preview',
				),
			);

			return $script_array;
		}
	}
}

/**
 * Intializing module if Woofunnels is enabled and activated.
 */

add_action( 'plugins_loaded', 'wfocu_multi_product' );
/**
 * Initializing this addon
 */
function wfocu_multi_product() {
	if ( defined( 'WFOCU_VERSION' ) && version_compare( WFOCU_VERSION, '1.7.2', '<' ) ) {
		add_action( 'admin_notices', 'wfocu_upstroke_version_not_supported_notice' );
	} elseif ( class_exists( 'WFOCU_MultiProduct' ) ) {
		return WFOCU_MultiProduct::get_instance();
	}
}

/**
 * Adding notice for low version of UpStroke
 */
function wfocu_upstroke_version_not_supported_notice() {
	?>
	<div class="wfocu-notice notice notice-error">
		<strong><?php esc_html_e( 'Attention', 'woofunnels-upstroke-power-pack' ); ?></strong>
		<p>
			<?php
			esc_html_e( 'To work "UpStroke PowerPack -Multi Product Offers- module", "UpStroke: WooCommerce One Click Upsells" version should not be lower than 1.7.2', 'woofunnels-upstroke-power-pack' );
			?>
		</p>
	</div>
	<?php
}
