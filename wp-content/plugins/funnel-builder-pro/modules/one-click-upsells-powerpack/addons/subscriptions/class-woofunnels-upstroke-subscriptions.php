<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WooFunnels_UpStroke_Subscriptions' ) ) {
	class WooFunnels_UpStroke_Subscriptions {

		public static $instance;

		public function __construct() {

			$this->init_constants();
			$this->init_hooks();
		}

		public function init_constants() {
			define( 'WFOCU_MIN_WFOCU_VERSION', '2.0.0' );
		}

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function init_hooks() {
			add_action( 'plugins_loaded', array( $this, 'add_files' ) );
		}

		public function add_files() {

			if ( false === wfocu_is_woocommerce_active() ) {
				return;
			}

			if ( ! version_compare( WFOCU_VERSION, WFOCU_MIN_WFOCU_VERSION, '>=' ) ) {
				add_action( 'admin_notices', array( $this, 'wfocu_version_check_notice' ) );

				return false;
			}
			add_action( 'wp_loaded', array( $this, 'load_integration_gateway_files' ) );


			include_once plugin_dir_path( __FILE__ ) . 'class-upstroke-subscriptions.php';
		}

		public function wfocu_version_check_notice() {
			?>
			<div class="error">
				<p>
					<strong><?php esc_html_e( 'Attention', 'woofunnels-upstroke-power-pack' ); ?></strong>
					<?php
					/* translators: %1$s: Min required upstroke version */
					echo sprintf( esc_html__( 'UpStroke Subscriptions requires  WooFunnels UpStroke: One Click Upsell version %1$s or greater. Kindly update the WooFunnels UpStroke: One Click Upsell plugin.', 'woofunnels-upstroke-power-pack' ), esc_attr( WFOCU_MIN_POWERPACK_VERSION ) );
					?>
				</p>
			</div>
			<?php
		}

		function load_integration_gateway_files() {
			$all_subscription_compatibiity            = array(
				'paypal'                        => 'class-upstroke-subscriptions-paypal.php',
				'paypal_express'                => 'class-upstroke-subscriptions-paypal-checkout.php',
				'ppec_paypal'                   => 'class-upstroke-subscriptions-ppec.php',
				'stripe'                        => 'class-upstroke-subscriptions-stripe.php',
				'authorize_net_cim_credit_card' => 'class-upstroke-subscriptions-authorize-cim.php',
				'braintree_credit_card'         => 'class-upstroke-subscriptions-braintree-credit-card.php',
				'braintree_paypal'              => 'class-upstroke-subscriptions-braintree-paypal.php',
				'woocommerce_payments'          => 'class-upstroke-subscriptions-woocommerce-payments.php',
				'ppcp-gateway'                  => 'class-upstroke-subscriptions-woocommerce-paypal-payments.php',
			);
			$all_subscription_compatibiity_dependency = array(
				'paypal'                        => 'WFOCU_Gateway_Integration_PayPal_Standard',
				'paypal_express'                => 'WFOCU_Paypal_For_WC_Gateway_Express_Checkout',
				'ppec_paypal'                   => 'WFOCU_Gateway_Integration_Paypal_Express_Checkout',
				'stripe'                        => 'WFOCU_Gateway_Integration_Stripe',
				'authorize_net_cim_credit_card' => 'WFOCU_Gateway_Integration_Authorize_Net_CIM',
				'braintree_credit_card'         => 'WFOCU_Gateway_Integration_Braintree_CC',
				'braintree_paypal'              => 'WFOCU_Gateway_Integration_Braintree_PayPal',
				'woocommerce_payments'          => 'WFOCU_Gateway_Integration_WooCommerce_Payments',
				'ppcp-gateway'                  => 'WFOCU_Gateway_Integration_PayPal_Payments',
			);

			foreach ( $all_subscription_compatibiity as $key => $val ) {
				if ( isset( $all_subscription_compatibiity_dependency[ $key ] ) && class_exists( $all_subscription_compatibiity_dependency[ $key ], false ) ) {
					include_once plugin_dir_path( __FILE__ ) . '/gateways/' . $val;
				}
			}

		}
	}
}

if ( class_exists( 'WooFunnels_UpStroke_Subscriptions' ) ) {
	WooFunnels_UpStroke_Subscriptions::instance();
}
