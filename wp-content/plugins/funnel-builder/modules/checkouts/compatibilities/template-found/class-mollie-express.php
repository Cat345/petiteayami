<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name: Mollie Payments for WooCommerce
 * Plugin URI: https://www.mollie.com
 *
 * Mollie shows its express checkout only on the WooCommerce block checkout. This shows the same
 * buttons in the FunnelKit Checkout express area, the way Mollie decides them on the block checkout:
 * - the Express Component (every wallet Mollie offers) when Mollie makes it available;
 * - otherwise its Apple Pay and PayPal express buttons.
 * Everything runs inside try/catch: a Mollie change only hides the buttons, it never touches the checkout.
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Mollie_Express' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Mollie_Express {
		/**
		 * One express slot per button, like every other gateway, so the express row lays them out side by side.
		 */
		const SLOTS = array(
			'component' => 'mollie_express',
			'applepay'  => 'mollie_applepay',
			'paypal'    => 'mollie_paypal',
		);
		const COMPONENT_HANDLE = 'mollie-v2';
		const COMPONENT_SRC = 'https://js.mollie.com/v2/mollie.js?compatible';
		const SCRIPT_HANDLE = 'wfacp-mollie-express';

		/**
		 * @var array|null Buttons to show, decided once per request.
		 */
		private $buttons = null;

		public function __construct() {
			add_filter( 'wfacp_smart_buttons', array( $this, 'add_buttons' ) );
			add_action( 'wfacp_smart_button_container_' . self::SLOTS['component'], array( $this, 'render_component' ) );
			add_action( 'wfacp_smart_button_container_' . self::SLOTS['applepay'], array( $this, 'render_apple_pay' ) );
			add_action( 'wfacp_smart_button_container_' . self::SLOTS['paypal'], array( $this, 'render_paypal' ) );
			add_filter( 'wfacp_template_localize_data', array( $this, 'register_wrapper' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 100 );
		}

		public function is_enable() {
			return defined( 'M4W_PLUGIN_DIR' ) && function_exists( 'mollieWooCommerceIsGatewayEnabled' );
		}

		/**
		 * @return array{component: array|null, applepay: bool, paypal: bool}
		 */
		private function buttons() {
			if ( null !== $this->buttons ) {
				return $this->buttons;
			}
			$this->buttons = array(
				'component' => null,
				'applepay'  => false,
				'paypal'    => false,
			);
			try {
				if ( ! $this->is_enable() || is_admin() || ! function_exists( 'WC' ) || ! WC()->cart instanceof WC_Cart || WC()->cart->is_empty() ) {
					return $this->buttons;
				}
				$this->buttons['component'] = $this->component_data();
				if ( null !== $this->buttons['component'] ) {
					return $this->buttons;
				}
				$this->buttons['applepay'] = $this->can_show_apple_pay();
				$this->buttons['paypal']   = $this->can_show_paypal();
			} catch ( Exception | Error $e ) {
				$this->log( $e );
			}

			return $this->buttons;
		}

		/**
		 * The data Mollie gives its block checkout, when it would show the Express Component there.
		 * Waiting for the shipping address (blocked) still loads it: the script mounts it once the address is complete.
		 *
		 * @return array|null
		 */
		private function component_data() {
			try {
				if ( ! function_exists( '\Mollie\WooCommerce\initialize' ) || ! class_exists( '\Mollie\WooCommerce\ExpressComponent\Rules\ExpressAvailability' ) ) {
					return null;
				}
				$container = call_user_func( '\Mollie\WooCommerce\initialize' );
				if ( ! is_object( $container ) || ! method_exists( $container, 'get' ) ) {
					return null;
				}
				$facts = $container->get( 'Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder' );
				$carts = $container->get( 'Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder' );
				$cart  = $carts->fromCart();
				if ( null === $cart ) {
					return null;
				}
				$settings = $facts->settings();
				$shop     = $facts->shopFacts();
				$status   = call_user_func( array( 'Mollie\WooCommerce\ExpressComponent\Rules\ExpressAvailability', 'resolve' ), $settings, $shop, $cart, 'checkout' )->status();
				if ( ! in_array( $status, array( 'available', 'blocked' ), true ) ) {
					return null;
				}
				$data = $container->get( 'Mollie\WooCommerce\ExpressComponent\Entry\ExpressBlocksData' )->build( $settings, $shop );

				return is_array( $data ) && ! empty( $data['restUrl'] ) && ! empty( $data['nonce'] ) ? $data : null;
			} catch ( Exception | Error $e ) {
				$this->log( $e );

				return null;
			}
		}

		/**
		 * Mollie's own conditions for the Apple Pay express button on the checkout.
		 */
		private function can_show_apple_pay() {
			if ( ! function_exists( 'mollieWooCommerceisApplePayDirectEnabled' ) || ! mollieWooCommerceisApplePayDirectEnabled( 'express_checkout' ) || ! is_ssl() ) {
				return false;
			}
			if ( ! is_user_logged_in() && $this->cart_has_subscription() ) {
				return false;
			}

			return wp_script_is( 'mollie_applepaydirectCart', 'registered' );
		}

		/**
		 * Mollie's own conditions for the PayPal express button: no subscription, nothing to ship.
		 */
		private function can_show_paypal() {
			if ( ! function_exists( 'mollieWooCommerceIsPayPalButtonEnabled' ) || ! mollieWooCommerceIsPayPalButtonEnabled( 'checkout' ) || $this->cart_has_subscription() ) {
				return false;
			}
			foreach ( WC()->cart->get_cart_contents() as $item ) {
				if ( ! isset( $item['data'] ) || ! $item['data'] instanceof WC_Product || ! $item['data']->is_virtual() ) {
					return false;
				}
			}

			return wp_script_is( 'mollie_paypalButtonCart', 'registered' ) && class_exists( '\Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal' );
		}

		private function cart_has_subscription() {
			foreach ( WC()->cart->get_cart_contents() as $item ) {
				if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product && false !== strpos( $item['data']->get_type(), 'subscription' ) ) {
					return true;
				}
			}

			return false;
		}

		private function has_buttons() {
			return ! empty( $this->active_slots() );
		}

		/**
		 * @return string[] Slugs of the express slots to show.
		 */
		private function active_slots() {
			$buttons = $this->buttons();
			$slots   = array();
			foreach ( self::SLOTS as $key => $slug ) {
				if ( 'component' === $key ? null !== $buttons['component'] : $buttons[ $key ] ) {
					$slots[] = $slug;
				}
			}

			return $slots;
		}

		public function add_buttons( $buttons ) {
			try {
				foreach ( $this->active_slots() as $slug ) {
					$buttons[ $slug ] = array(
						'iframe' => true,
						'name'   => 'Mollie Express Checkout',
					);
				}
			} catch ( Exception | Error $e ) {
				$this->log( $e );
			}

			return $buttons;
		}

		/**
		 * The express area reveals the slot once something is inserted into this wrapper.
		 */
		public function register_wrapper( $data ) {
			try {
				if ( isset( $data['smart_button_wrappers']['dynamic_buttons'] ) && is_array( $data['smart_button_wrappers']['dynamic_buttons'] ) ) {
					foreach ( $this->active_slots() as $slug ) {
						$data['smart_button_wrappers']['dynamic_buttons'][ '#wfacp_' . $slug . '_buttons' ] = '#wfacp_smart_button_' . $slug;
					}
				}
			} catch ( Exception | Error $e ) {
				$this->log( $e );
			}

			return $data;
		}

		public function render_component() {
			echo '<div id="wfacp_mollie_express_buttons" class="wfacp_mollie_slot"><div id="wfacp_mollie_express_component"></div></div>';
		}

		public function render_apple_pay() {
			// Mollie's script adds the Apple Pay button here when the browser supports it.
			echo '<div id="wfacp_mollie_applepay_buttons" class="wfacp_mollie_slot"><div id="mollie-applepayDirect-button"></div></div>';
		}

		public function render_paypal() {
			try {
				$paypal_data = 'Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal';
				$data        = new $paypal_data( plugin_dir_url( defined( 'M4W_FILE' ) ? M4W_FILE : M4W_PLUGIN_DIR . '/mollie-payments-for-woocommerce.php' ) );
				// Mollie's button image is narrower than the slot: fill the rest with the image's own colour and shape.
				$settings = (array) get_option( 'mollie_wc_gateway_paypal_settings', array() );
				$style    = explode( '-', isset( $settings['color'] ) && is_string( $settings['color'] ) ? $settings['color'] : 'en-checkout-pill-golden' );
				$shape    = isset( $style[2] ) && 'rounded' === $style[2] ? 'rounded' : 'pill';
				$color    = isset( $style[3] ) && in_array( $style[3], array( 'golden', 'gold', 'blue', 'gray', 'silver', 'white', 'black' ), true ) ? $style[3] : 'golden';
				echo '<div id="wfacp_mollie_paypal_buttons" class="wfacp_mollie_slot"><div id="mollie-PayPal-button" class="mol-PayPal">';
				wp_nonce_field( 'mollie_PayPal_button' );
				echo '<input type="image" class="wfacp-mollie-paypal-' . esc_attr( $shape ) . ' wfacp-mollie-paypal-' . esc_attr( $color ) . '" src="' . esc_url( $data->selectedPaypalButtonUrl() ) . '" alt="PayPal Button"></div></div>';
			} catch ( Exception | Error $e ) {
				$this->log( $e );
			}
		}

		public function enqueue() {
			try {
				if ( ! $this->has_buttons() ) {
					return;
				}
				$buttons = $this->buttons();
				$cart    = WC()->cart;
				$deps    = array( 'jquery' );

				if ( null !== $buttons['component'] ) {
					// Null version: a ?ver= would break Mollie's exact ?compatible URL.
					wp_register_script( self::COMPONENT_HANDLE, self::COMPONENT_SRC, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
					$deps[] = self::COMPONENT_HANDLE;
					$deps[] = 'wp-api-fetch';
				}
				if ( $buttons['applepay'] ) {
					$base = wc_get_base_location();
					wp_enqueue_style( 'mollie-applepaydirect' );
					wp_enqueue_script( 'mollie_applepaydirectCart' );
					wp_localize_script( 'mollie_applepaydirectCart', 'mollieApplePayDirectDataCart', array(
						'product' => array(
							'needShipping' => $cart->needs_shipping(),
							'subtotal'     => $cart->get_subtotal(),
						),
						'shop'    => array(
							'countryCode'  => $base['country'],
							'currencyCode' => get_woocommerce_currency(),
							'totalLabel'   => get_bloginfo( 'name' ),
						),
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					) );
				}
				if ( $buttons['paypal'] ) {
					$settings = (array) get_option( 'mollie_wc_gateway_paypal_settings', array() );
					$min_fee  = isset( $settings['mollie_paypal_button_minimum_amount'] ) && $settings['mollie_paypal_button_minimum_amount'] > 0 ? $settings['mollie_paypal_button_minimum_amount'] : 0;
					wp_enqueue_style( 'unabledButton' );
					wp_enqueue_script( 'mollie_paypalButtonCart' );
					wp_localize_script( 'mollie_paypalButtonCart', 'molliepaypalButtonCart', array(
						'product' => array(
							'needShipping' => $cart->needs_shipping(),
							'minFee'       => $min_fee,
						),
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					) );
				}

				wp_enqueue_script( self::SCRIPT_HANDLE, plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/js/mollie-express.js', $deps, WFACP_VERSION_DEV, true );
				wp_localize_script( self::SCRIPT_HANDLE, 'wfacpMollieExpress', array(
					'component' => $buttons['component'],
				) );
				wp_register_style( self::SCRIPT_HANDLE, false, array(), WFACP_VERSION_DEV );
				wp_enqueue_style( self::SCRIPT_HANDLE );
				wp_add_inline_style( self::SCRIPT_HANDLE, '.wfacp_mollie_slot #mollie-applepayDirect-button,.wfacp_mollie_slot #mollie-PayPal-button{margin:0;padding:0}.wfacp_mollie_slot #mollie_applepay_button{display:block;width:100%;height:48px;margin:0;border:0;border-radius:4px;cursor:pointer;-webkit-appearance:-apple-pay-button;-apple-pay-button-style:black;-apple-pay-button-type:plain}.wfacp_mollie_slot #mollie-PayPal-button input[type=image]{display:block;width:100%;height:48px;margin:0;padding:0;border:0;object-fit:contain;cursor:pointer}.wfacp_mollie_slot .wfacp-mollie-paypal-pill{border-radius:24px}.wfacp_mollie_slot .wfacp-mollie-paypal-rounded{border-radius:4px}.wfacp_mollie_slot .wfacp-mollie-paypal-golden{background:#f7c557}.wfacp_mollie_slot .wfacp-mollie-paypal-gold{background:#ffc439}.wfacp_mollie_slot .wfacp-mollie-paypal-silver{background:#eeeeee}.wfacp_mollie_slot .wfacp-mollie-paypal-blue{background:#439bd8}.wfacp_mollie_slot .wfacp-mollie-paypal-gray{background:#eeeeee}.wfacp_mollie_slot .wfacp-mollie-paypal-black{background:#2c2e2f}.wfacp_mollie_slot #mollie-PayPal-button .wfacp-mollie-paypal-white{background:#fff;border:1px solid #000;box-sizing:border-box}' );
			} catch ( Exception | Error $e ) {
				$this->log( $e );
			}
		}

		private function log( $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( $e->getMessage(), array( 'source' => 'funnelkit-checkout-mollie-express' ) );
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Mollie_Express(), 'mollie_express' );
}
