<?php
/**
 * Plugin Name: UpStroke PowerPack
 * Plugin URI: https://funnelkit.com
 * Description: This provides UpStroke dynamic shipping, subscriptions, multiple products and reporting features to your store.
 * Version: 1.11.0
 * Author: Funnelkit
 * Author URI: https://funnelkit.com
 * Text Domain: woofunnels-upstroke-power-pack
 * Domain Path: /languages/
 *
 * Requires at least: 5.0.0
 * Tested up to: 6.8.1
 * WC requires at least: 4.5.0
 * WC tested up to: 9.9.4
 * WooFunnels: true
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'woofunnels_upstroke_powerpack_dependency' ) ) {

	/**
	 * Function to check if woofunnels upstroke pro version is loaded and activated or not?
	 * @return bool True|False
	 */
	function woofunnels_upstroke_powerpack_dependency() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		$is_funnel_pro = in_array( 'funnel-builder-pro/funnel-builder-pro.php', $active_plugins, true ) || array_key_exists( 'funnel-builder-pro/funnel-builder-pro.php', $active_plugins );

		$is_upstroke_pro = in_array( 'woofunnels-upstroke-one-click-upsell/woofunnels-upstroke-one-click-upsell.php', $active_plugins, true ) || array_key_exists( 'woofunnels-upstroke-one-click-upsell/woofunnels-upstroke-one-click-upsell.php', $active_plugins );

		return $is_upstroke_pro || $is_funnel_pro;
	}
}
if ( ! class_exists( 'WooFunnels_UpStroke_PowerPack' ) ) {
	class WooFunnels_UpStroke_PowerPack {

		public static $instance;
		public $old_plugins;

		public function __construct() {

			$this->init_constants();
			$this->init_hooks();
			$this->old_plugins = array();
		}

		public function init_constants() {
			define( 'WF_UPSTROKE_POWERPACK_VERSION', '1.11.0' );
			define( 'WFOCU_MIN_POWERPACK_VERSION', '2.0.5' );
			define( 'WF_UPSTROKE_POWERPACK_BASENAME', plugin_basename( __FILE__ ) );
		}

		public function init_hooks() {
			add_action( 'plugins_loaded', array( $this, 'maybe_check_version' ) );
			add_action( 'plugins_loaded', array( $this, 'add_licence_support_file' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'wfocu_loaded', array( $this, 'load_upstroke_powerpack' ), 999 );
			add_action( 'wfocu_load_rule_files', array( $this, 'load_rule_files' ) );
			add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );

		}

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function load_upstroke_powerpack() {
			if ( class_exists( 'WooFunnels_UpStroke_Dynamic_Shipping' ) ) {
				$this->old_plugins['dynamic_shpping'] = __( 'Dynamic Shipping', 'woofunnels-upstroke-power-pack' );
			} else {
				include_once plugin_dir_path( __FILE__ ) . 'addons/dynamic-shipping/class-woofunnels-upstroke-dynamic-shipping.php';
			}

			if ( class_exists( 'WFOCU_MultiProduct' ) ) {
				$this->old_plugins['multiple_products'] = __( 'Multiple Products', 'woofunnels-upstroke-power-pack' );
			} else {
				include_once plugin_dir_path( __FILE__ ) . 'addons/multiple-products/class-woofunnels-upstroke-multiple-products.php';
			}

			if ( class_exists( 'WFOCU_Admin_Reports' ) ) {
				$this->old_plugins['reports'] = __( 'Woocommerce Reports', 'woofunnels-upstroke-power-pack' );
			} else {
				include_once plugin_dir_path( __FILE__ ) . 'addons/reports/class-woofunnels-upstroke-reports.php';
			}

			if ( class_exists( 'WooFunnels_UpStroke_Subscriptions' ) ) {
				$this->old_plugins['subscriptions'] = __( 'Subscriptions', 'woofunnels-upstroke-power-pack' );
			} else {
				include_once plugin_dir_path( __FILE__ ) . 'addons/subscriptions/class-woofunnels-upstroke-subscriptions.php';
			}

			include_once plugin_dir_path( __FILE__ ) . 'addons/subscriptions/compatibilities/class-wfocu-wc-atts-compatibility.php';


			if ( count( $this->old_plugins ) > 0 ) {
				add_action( 'admin_notices', array( $this, 'old_plugins_notices' ) );
			}
		}

		public function old_plugins_notices() {
			foreach ( $this->old_plugins as $p_name ) { ?>
                <div class="error">
                    <p>
                        <strong><?php esc_html_e( 'Attention', 'woofunnels-upstroke-power-pack' ); ?></strong>
						<?php
						/* translators: %1$s: Plugin name %2$s Plugin name */
						echo sprintf( esc_html__( 'Old version of "UpStroke: %1$s" is installed and active. Please deactivate it to run updated "Upstroke PowerPack - %2$s" module', 'woofunnels-upstroke-power-pack' ), esc_attr( $p_name ), esc_attr( $p_name ) );
						?>
                    </p>
                </div>
				<?php
			}
		}

		public function add_licence_support_file() {
			include_once plugin_dir_path( __FILE__ ) . 'class-woofunnels-support-wfocu-power-pack.php';
		}

		/**
		 * Show notice if upstroke is not updated to run addons
		 */
		public function wfocu_version_check_notice() {
			?>
            <div class="error">
                <p>
                    <strong><?php esc_html_e( 'Attention', 'woofunnels-upstroke-power-pack' ); ?></strong>
					<?php
					/* translators: %1$s: Min required upstroke version */
					echo sprintf( esc_html__( 'UpStroke PowerPack requires  WooFunnels UpStroke: One Click Upsell version %1$s or greater. Kindly update the WooFunnels UpStroke: One Click Upsell plugin.', 'woofunnels-upstroke-power-pack' ), esc_attr( WFOCU_MIN_POWERPACK_VERSION ) );
					?>
                </p>
            </div>
			<?php
		}

		public function maybe_check_version() {
			if ( defined( 'WFOCU_VERSION' ) && ! version_compare( WFOCU_VERSION, WFOCU_MIN_POWERPACK_VERSION, '>=' ) ) {
				add_action( 'admin_notices', array( $this, 'wfocu_version_check_notice' ) );

				return false;
			}
		}

		/**
		 *
		 */
		public function load_textdomain() {

			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

			unload_textdomain( 'woofunnels-upstroke-power-pack' );
			load_textdomain( 'woofunnels-upstroke-power-pack', WP_LANG_DIR . '/woofunnels-upstroke-power-pack' . $locale . '.mo' );

			load_plugin_textdomain( 'woofunnels-upstroke-power-pack', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		public function load_rule_files() {

			/** add new rule for all thing subscription */
			include_once plugin_dir_path( __FILE__ ) . 'addons/rules/wfocu-rule-ats-order-subs.php';
		}
		public static function is_hpos_enabled() {
			return ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		}


		public static function get_order_meta( $order, $key = '' ) {
			if ( empty( $key ) ) {
				return '';
			}
			if ( ! $order instanceof WC_Abstract_Order ) {
				return '';
			}

			$meta_value = $order->get_meta( $key );
			if ( ! empty( $meta_value ) ) {
				return $meta_value;
			}

			if ( true === self::is_hpos_enabled() ) {
				global $wpdb;
				$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT `meta_value` FROM `{$wpdb->prefix}wc_orders_meta` WHERE `meta_key`=%s AND `order_id`=%d", $key, $order->get_id() ) );
			}

			if ( ! empty( $meta_value ) ) {
				return maybe_unserialize( $meta_value );
			}

			return get_post_meta( $order->get_id(), $key, true );
		}

		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	}
}

if ( true === woofunnels_upstroke_powerpack_dependency() ) {
	WooFunnels_UpStroke_PowerPack::instance();
} else {
	add_action( 'admin_notices', 'wfocu_powerpack_upstroke_not_installed_notice' );
}

/**
 * Adding notice for inactive state of Woofunnels One Click Upsells
 */
if ( ! function_exists( 'wfocu_powerpack_upstroke_not_installed_notice' ) ) {


	function wfocu_powerpack_upstroke_not_installed_notice() {
		?>
        <div class="error">
            <p>
                <strong><?php esc_html_e( 'Attention', 'woofunnels-upstroke-power-pack' ); ?></strong>
				<?php
				esc_html_e( 'UpStroke PowerPack contains a "UpStroke: WooCommerce One Click Upsells" addons and would only work if it is installed and activated. Please install and activate it first.', 'woofunnels-upstroke-power-pack' );
				?>
            </p>
        </div>
		<?php
	}
}
