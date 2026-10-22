<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_Zamp_Tax' ) ) {
	/**
	 * Zamp reads its rate from WC()->cart and ignores the location WooCommerce passes to
	 * `woocommerce_find_rates`, so upsell tax breaks in two spots. Both are fixed with the parent
	 * order's own rate (Zamp_Calculations::calculate_tax_for_admin_order, cart-free):
	 *  1. Accept-recalc (empty cart): only find_rates fires -> inject_rate() overrides Zamp's $0.
	 *  2. Offer build ('init') + dynamic-tax AJAX: Zamp reads the empty cart -> $0, and on 'init' also
	 *     trips the "get_cart before wp_loaded" notice. We remove Zamp's two rate filters for the window
	 *     and supply the order rate, restoring Zamp when the window closes.
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Compatibility_With_Zamp_Tax {

		private $order      = null;
		private $rate       = null;
		private $computed   = false;
		private $taken_over = false;

		public function __construct() {
			// Case 1: accept-recalc (only find_rates fires there).
			add_filter( 'woocommerce_order_get_tax_location', array( $this, 'capture_order' ), 20, 2 );
			add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'release_order' ), 999 );
			add_filter( 'woocommerce_find_rates', array( $this, 'inject_rate' ), 2100, 2 );

			// Case 2: take Zamp over for the offer build + dynamic-tax AJAX windows; wp_loaded failsafe
			// so the 'init' window can never leak past bootstrap.
			add_filter( 'wfocu_build_offer_product_before', array( $this, 'take_over_for_offer' ), 1, 3 );
			add_action( 'wfocu_offer_setup_completed', array( $this, 'restore_zamp' ) );
			add_action( 'wfocu_dynamic_tax_before_calculate_totals', array( $this, 'take_over_for_dynamic_tax' ) );
			add_action( 'wfocu_dynamic_tax_after_calculate_totals', array( $this, 'restore_zamp' ) );
			add_action( 'wp_loaded', array( $this, 'restore_zamp' ), PHP_INT_MAX );
		}

		public function take_over_for_offer( $offer_data = null, $offer_id = 0, $is_front = false ) {
			if ( function_exists( 'WFOCU_Core' ) && isset( WFOCU_Core()->data ) ) {
				$this->take_over( WFOCU_Core()->data->get_current_order() );
			}
			return $offer_data;
		}

		public function take_over_for_dynamic_tax( $order = null ) {
			$this->take_over( $order );
		}

		private function take_over( $order ) {
			if ( $this->taken_over || ! $order instanceof WC_Order || ! class_exists( 'Zamp_Tax_Provider' ) ) {
				return;
			}
			$this->order    = $order;
			$this->rate     = null;
			$this->computed = false;
			remove_filter( 'woocommerce_find_rates', array( 'Zamp_Tax_Provider', 'find_tax_rates' ), 2000 );
			remove_filter( 'woocommerce_matched_rates', array( 'Zamp_Tax_Provider', 'calculate_matched_rates' ), 2000 );
			add_filter( 'woocommerce_find_rates', array( $this, 'provide_offer_rate' ), 2100, 2 );
			$this->taken_over = true;
		}

		public function restore_zamp() {
			if ( ! $this->taken_over ) {
				return;
			}
			remove_filter( 'woocommerce_find_rates', array( $this, 'provide_offer_rate' ), 2100 );
			if ( class_exists( 'Zamp_Tax_Provider' ) ) {
				add_filter( 'woocommerce_find_rates', array( 'Zamp_Tax_Provider', 'find_tax_rates' ), 2000, 2 );
				add_filter( 'woocommerce_matched_rates', array( 'Zamp_Tax_Provider', 'calculate_matched_rates' ), 2000, 2 );
			}
			$this->order      = null;
			$this->taken_over = false;
		}

		// Stand-in for Zamp during the offer window: ADD the dummy rate (inject_rate() only overrides).
		public function provide_offer_rate( $rates, $args = array() ) {
			if ( ! $this->order instanceof WC_Order
				|| ! method_exists( 'Zamp_Tax_Rate_Manager', 'get_dummy_tax_rate_id' )
				|| ! method_exists( 'Zamp_Calculations', 'calculate_tax_for_admin_order' ) ) {
				return $rates;
			}
			if ( ! is_array( $rates ) ) {
				$rates = array();
			}
			$rate_id = Zamp_Tax_Rate_Manager::get_dummy_tax_rate_id();
			if ( ! $rate_id ) {
				return $rates;
			}
			if ( ! $this->computed ) {
				$this->computed = true; // set first: order_rate() must never re-enter into a fresh compute
				$this->rate     = $this->order_rate( $this->order );
			}
			if ( null !== $this->rate ) {
				$rates[ $rate_id ] = array(
					'rate'     => $this->rate,
					'label'    => __( 'Sales Tax', 'zamp-tax' ),
					'shipping' => 'yes',
					'compound' => 'yes',
					'priority' => 1,
				);
			}
			return $rates;
		}

		// Required by WFOCU_Plugin_Compatibilities. Not a currency handler.
		public function is_enable() {
			return false;
		}

		public function capture_order( $location, $order = null ) {
			// Front-end empty-cart order recalc only (upsell accept / subscription renewal); skip wp-admin.
			if ( ! $order instanceof WC_Order || is_admin() || ! function_exists( 'WC' ) ) {
				return $location;
			}

			// did_action() checked before is_empty() to avoid the get_cart-before-wp_loaded notice.
			if ( is_null( WC()->cart ) || ! did_action( 'wp_loaded' ) || WC()->cart->is_empty() ) {
				$this->order    = $order;
				$this->rate     = null;
				$this->computed = false;
			}
			return $location;
		}

		public function release_order() {
			$this->order = null;
		}

		public function inject_rate( $rates, $args = array() ) {
			if ( ! $this->order instanceof WC_Order || ! is_array( $rates )
				|| ! method_exists( 'Zamp_Tax_Rate_Manager', 'get_dummy_tax_rate_id' )
				|| ! method_exists( 'Zamp_Calculations', 'calculate_tax_for_admin_order' ) ) {
				return $rates;
			}

			$rate_id = Zamp_Tax_Rate_Manager::get_dummy_tax_rate_id();
			if ( ! $rate_id || ! isset( $rates[ $rate_id ] ) ) {
				return $rates;
			}

			if ( ! $this->computed ) {
				$this->rate     = $this->order_rate( $this->order );
				$this->computed = true;
			}
			if ( null !== $this->rate ) {
				$rates[ $rate_id ]['rate'] = $this->rate;
			}

			return $rates;
		}

		/**
		 * Effective % (taxDue / taxable) for the order, from Zamp's own public order-tax calc.
		 */
		private function order_rate( $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
				if ( $product ) {
					$items[] = array(
						'product_id' => $product->get_id(),
						'qty'        => $item->get_quantity(),
						'subtotal'   => (float) $item->get_subtotal(),
						'product'    => $product,
					);
				}
			}
			if ( empty( $items ) ) {
				return null;
			}

			$address = $order->get_address( 'shipping' );
			if ( empty( $address['address_1'] ) || empty( $address['state'] ) || empty( $address['postcode'] ) ) {
				$address = $order->get_address( 'billing' );
			}

			$result = Zamp_Calculations::calculate_tax_for_admin_order(
				array(
					'items'            => $items,
					'shipping_address' => $address,
					'shipping_total'   => (float) $order->get_shipping_total(),
					'discount_total'   => (float) $order->get_total_discount(),
					'exempt'           => $order->get_meta( '_zamp_order_exempt' ),
					'exempt_type'      => $order->get_meta( '_zamp_order_exempt_type' ),
					'force_taxable'    => $order->get_meta( '_zamp_order_force_taxable' ),
					'customer_id'      => $order->get_customer_id(),
				),
				$order->get_id()
			);
			if ( ! is_array( $result ) || isset( $result['error'] ) || empty( $result['taxDue'] ) ) {
				return null;
			}

			$taxable = (float) ( isset( $result['taxSubTotal'] ) ? $result['taxSubTotal'] : 0 ) + (float) ( isset( $result['taxShippingHandling'] ) ? $result['taxShippingHandling'] : 0 );
			return $taxable > 0 ? ( (float) $result['taxDue'] / $taxable ) * 100 : null;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Zamp_Tax(), 'zamp_tax' );
}
