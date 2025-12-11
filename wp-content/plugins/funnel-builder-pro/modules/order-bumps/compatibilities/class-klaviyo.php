<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Klaviyo' ) ) {

	class WFOB_Compatibility_With_Klaviyo {
		public function __construct() {
			add_filter( 'wfob_checkout_data', [ $this, 'prepare_checkout_data' ], 10, 2 );
			add_action( 'wp_footer', [ $this, 'js_event' ], 100 );
		}

		public function is_enable() {
			if ( class_exists( 'WooCommerceKlaviyo' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $checkout_data
		 * @param $cart WC_Cart;
		 *
		 * @return mixed
		 */
		public function prepare_checkout_data( $checkout_data, $cart ) {


			if ( ! $this->is_enable() ) {
				return $checkout_data;
			}

			$items = $cart->get_cart_contents();
			if ( empty( $items ) ) {
				return $checkout_data;
			}
			$event_data = array(
				'$service' => 'woocommerce',
				'$value'   => $cart->total,
				'$extra'   => array(
					'Items'         => array(),
					'SubTotal'      => $cart->subtotal,
					'ShippingTotal' => $cart->shipping_total,
					'TaxTotal'      => $cart->tax_total,
					'GrandTotal'    => $cart->total
				)
			);

			foreach ( $cart->get_cart() as $cart_item_key => $values ) {
				/**
				 * @var $product WC_Product;
				 */
				$product = $values['data'];

				$event_data['$extra']['Items'] [] = array(
					'Quantity'     => $values['quantity'],
					'ProductID'    => $product->get_id(),
					'Name'         => $product->get_title(),
					'URL'          => $product->get_permalink(),
					'Images'       => [
						[
							'URL' => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) )
						]
					],
					'Categories'   => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
					'Description'  => $product->get_description(),
					'Variation'    => $values['variation'],
					'SubTotal'     => $values['line_subtotal'],
					'Total'        => $values['line_subtotal_tax'],
					'LineTotal'    => $values['line_total'],
					'Tax'          => $values['line_tax'],
					'TotalWithTax' => $values['line_total'] + $values['line_tax']
				);
			}
			$checkout_data['klaviyo'] = $event_data;

			return $checkout_data;

		}


		public function js_event() {
			if ( ! $this->is_enable() ) {
				return;
			}

			?>
            <script>
                window.addEventListener('load', function () {
                    (function ($) {
                        let _learnq = window.klaviyo || window._learnq;
                        if (typeof _learnq == "undefined") {
                            return;
                        }
                        $(document.body).on('wfob_bump_trigger', function (e, v) {
                            if (typeof v !== "object") {
                                return;
                            }
                            if (!v.hasOwnProperty('analytics_data')) {
                                return;
                            }

                            if (!v.analytics_data.hasOwnProperty('klaviyo')) {
                                return;
                            }
                            wfob_storage.klaviyo = v.analytics_data.klaviyo;
                            _learnq.push(["track", "$started_checkout", v.analytics_data.klaviyo])
                        });
                    })(jQuery);
                });
            </script>
			<?php

		}


	}

	new WFOB_Compatibility_With_Klaviyo();
}