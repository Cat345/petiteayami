<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility for shipping plugins that append pickup point selection buttons
 * in the order_review section (e.g. WC_PS Pošta Slovenije, WooCommerce Speedy).
 *
 * Adds wrapper with .woocommerce-shipping-totals class that these plugins'
 * JavaScript needs to append the delivery pickup point button.
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Shipping_Pickup_Point_Order_Review' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Shipping_Pickup_Point_Order_Review {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'actions' ) );
		}

		public function is_enabled() {
			return class_exists( 'WC_Ps' ) || class_exists( 'Speedy' );
		}

		public function actions() {
			if ( ! $this->is_enabled() ) {
				return;
			}

			add_action( 'wfacp_before_shipping_calculator_field', array( $this, 'add_wrapper_open' ), 5 );
			add_action( 'wfacp_after_shipping_calculator_field', array( $this, 'add_wrapper_close' ), 999 );
			add_action( 'wp_footer', array( $this, 'add_anim_wrap_trigger_script' ), 25 );
		}

		public function add_wrapper_open() {
			echo '<div id="order_review"><div class="shop_table woocommerce-checkout-review-order-table wfacp_shipping_wrap"><div class="woocommerce-shipping-totals shipping">';
		}

		public function add_wrapper_close() {
			echo '</div></div></div>';
		}

		/**
		 * After fragments refresh: floating labels only (no mass .trigger('change')).
		 * Pošta SI fills shipping fields from the map popup via JS; that does not fire change.
		 * We patch the value setter only for shipping_* .wfacp-form-control when the field is
		 * not focused (popup fill), so Woo gets one change — not dozens after updated_checkout.
		 */
		public function add_anim_wrap_trigger_script() {
			?>
			<script>
			(function($){
				function patchShippingValueNotify(ctor) {
					try {
						var proto = ctor.prototype;
						var d = Object.getOwnPropertyDescriptor(proto, 'value');
						if (!d || !d.set) {
							return;
						}
						var nativeSet = d.set;
						var nativeGet = d.get;
						Object.defineProperty(proto, 'value', {
							set: function(v) {
								nativeSet.call(this, v);
								if (!this.classList || !this.classList.contains('wfacp-form-control')) {
									return;
								}
								var n = this.getAttribute('name') || '';
								if (n.indexOf('shipping_') !== 0) {
									return;
								}
								if (document.activeElement === this) {
									return;
								}
								$(this).trigger('change');
							},
							get: nativeGet,
							configurable: true,
							enumerable: true
						});
					} catch (e) {}
				}
				patchShippingValueNotify(HTMLInputElement);
				patchShippingValueNotify(HTMLTextAreaElement);
				patchShippingValueNotify(HTMLSelectElement);
				$(document.body).on('updated_checkout', function () {
					if (typeof wfacp_add_anim_wrap === 'function') {
						wfacp_add_anim_wrap();
					}
				});
			})(jQuery);
			</script>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Shipping_Pickup_Point_Order_Review(), 'shipping-pickup-point-order-review' );
}
