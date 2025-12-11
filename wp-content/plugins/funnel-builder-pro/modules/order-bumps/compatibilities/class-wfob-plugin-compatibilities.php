<?php
if ( ! class_exists( 'WFOB_Plugin_Compatibilities' ) ) {
	/**
	 * Class WFOB_Plugin_Compatibilities
	 * Loads all the compatibilities files we have to provide compatibility with each plugin
	 */
	class WFOB_Plugin_Compatibilities {

		public static $plugin_compatibilities = array();

		public static function load_all_compatibilities() {
			add_action( 'after_setup_theme', [ __CLASS__, 'frontend_compatibility' ] );

		}

		public static function frontend_compatibility() {
			$files = [
				'class-aero.php'                                             => class_exists( 'WFACP_Core' ),
				'class-all-product-subscription.php'                         => class_exists( 'WCS_ATT_Cart' ),
				'class-avada.php'                                            => defined( 'AVADA_VERSION' ),
				'class-checkout-wc.php'                                      => defined( 'CFW_NAME' ),
				'class-clearpay.php'                                         => class_exists( 'Clearpay_Plugin' ),
				'class-divi-theme.php'                                       => class_exists( 'ET_GB_Block_Layout' ),
				'class-finale-wc-sale-countdown-timer.php'                   => class_exists( 'WCCT_Core' ),
				'class-happy-elementor.php'                                  => function_exists( 'ha_let_the_journey_begin' ),
				'class-klaviyo.php'                                          => class_exists( 'WooCommerceKlaviyo' ),
				'class-marcado-pago.php'                                     => class_exists( 'WC_WooMercadoPago_Init' ) || class_exists( 'WC_PropulsePay' ) || class_exists( 'MercadoPago\Woocommerce\WoocommerceMercadoPago' ),
				'class-rightpress-discount-pro.php'                          => defined( 'RP_WCDPD_PLUGIN_KEY' ),
				'class-subscriptions.php'                                    => class_exists( 'WCS_Autoloader' ),
				'class-variation-swatch.php'                                 => defined( 'CFVSW_FILE' ),
				'class-wc-checkout-addons.php'                               => class_exists( 'WC_Checkout_Add_Ons_Loader' ),
				'class-wc-deposite.php'                                      => class_exists( '\Webtomizer\WCDP\WC_Deposits' ),
				'class-wcbooster.php'                                        => class_exists( 'WC_Jetpack' ),
				'class-wfob-compatibility-angel-eye.php'                     => class_exists( 'AngellEYE_Gateway_Paypal' ),
				'class-wfob-compatibility-braintree.php'                     => class_exists( 'WC_Braintree' ),
				'class-wfob-compatibility-infusewoo.php'                     => defined( 'INFUSEDWOO_PRO_VER' ),
				'class-wfob-compatibility-paypal-express.php'                => function_exists( 'wc_gateway_ppec' ),
				'class-wfob-compatibility-paypal-plus-gmbh.php'              => class_exists( 'WCPayPalPlus\PlusGateway\gateway' ),
				'class-wfob-compatibility-swatch.php'                        => function_exists( 'ta_wc_variation_swatches_constructor' ),
				'class-wfob-compatibility-theme-savoy.php'                                => defined( 'NM_THEME_DIR' ),
				'class-wfob-compatibility-ti-wishlist.php'                   => class_exists( 'TINVWL_URL' ),
				'class-wfob-compatibility-wc-multicurrency-by-tiv.php'       => defined( 'WOOCOMMERCE_MULTICURRENCY_VERSION' ),
				'class-wfob-compatibility-wc_radio_buttons.php'              => class_exists( 'WC_Radio_Buttons' ),
				'class-wfob-compatibility-with-aelia-cs.php'                 => class_exists( 'Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher' ),
				'class-wfob-compatibility-wp-clever-smart-bundle.php'        => function_exists( 'woosb_init' ),
				'class-wfob-plugin-improved-variable-product-attributes.php' => class_exists( 'WC_Improved_Variable_Product_Attributes_Init' ),
				'class-wfob-quick-view-variable-discounting.php'             => class_exists( 'WFOB_Core' ),
				'class-wfob-wffn-compatibility.php'                          => class_exists( 'WFFN_Core' ),
				'class-woocs.php'                                            => class_exists( 'WOOCS' ),
				'class-yith-currency-switcher.php'                           => defined( 'YITH_WCMCS_VERSION' ),
				'class-woodmart.php'                                         => function_exists( 'woodmart_lazy_attributes' ),
				'class-woomulti-by-curcy.php'                                => class_exists( 'WOOMULTI_CURRENCY_Frontend_Price' ) || class_exists( 'WOOMULTI_CURRENCY_F' ),
				'class-flycart-discounting.php'                              => defined( 'WDR_VERSION' ) || defined( 'WDR_PRO_VERSION' ),
				'class-yith-wc-product-add-ons-extra-options-premium.php'    => defined( 'YITH_WAPO' ),
				'class-price-based-country.php'                              => class_exists( 'WC_Product_Price_Based_Country' ),
				'woocommerce-payments.php'                                   => function_exists( 'wcpay_init' ),
				'class-yaycurrency.php'                                      => defined( 'YAY_CURRENCY_FILE' ),
			];
			self::add_files( $files );
		}

		public static function add_files( $paths ) {

			foreach ( $paths as $file => $condition ) {
				if ( false === $condition ) {
					continue;
				}
				try {
					include_once __DIR__ . '/' . $file;
				} catch ( Exception|Error $e ) {
				}
			}


		}

	}


	WFOB_Plugin_Compatibilities::load_all_compatibilities();

}