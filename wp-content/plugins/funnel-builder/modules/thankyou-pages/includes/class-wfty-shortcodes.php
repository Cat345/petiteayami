<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class WFTY_Shortcodes
 *
 * @package WFTY
 * @author XlPlugins
 */
if ( ! class_exists( 'WFTY_Shortcodes' ) ) {
	#[AllowDynamicProperties]

	class WFTY_Shortcodes {

		public static function init() {
			$data = WFFN_Core()->thank_you_pages->data;

			/**
			 * WordPress prints whatever a shortcode returns, so the escaping has to
			 * happen here. Wrapping at registration keeps the data methods usable
			 * elsewhere -- templates and the wfty_* helper functions still get the
			 * raw value.
			 */
			$plain_text = array(
				'wfty_order_number'          => 'get_order_id',
				'wfty_customer_first_name'   => 'get_customer_first_name',
				'wfty_customer_last_name'    => 'get_customer_last_name',
				'wfty_customer_email'        => 'get_customer_email',
				'wfty_customer_phone_number' => 'get_customer_phone',
			);

			foreach ( $plain_text as $tag => $method ) {
				add_shortcode(
					$tag,
					static function ( $atts = array() ) use ( $data, $method ) {
						return esc_html( (string) call_user_func( array( $data, $method ), $atts ) );
					}
				);
			}

			/** wfty_order_meta() escapes the stored value itself -- wrapping it again double-encodes entities. */
			add_shortcode( 'wfty_order_meta', array( $data, 'wfty_order_meta' ) );

			/** A formatted price -- markup, but our own, and nothing hooks into it. */
			add_shortcode(
				'wfty_order_total',
				static function ( $atts = array() ) use ( $data ) {
					return wp_kses_post( (string) call_user_func( array( $data, 'get_order_total' ), $atts ) );
				}
			);

			/**
			 * Deliberately NOT escaped, and it has to stay that way.
			 *
			 * These two do not return a value -- they return a whole rendered template
			 * (the order details table and the address block), which is our drop-in
			 * replacement for WooCommerce's order/order-details.php and
			 * order/order-details-customer.php. Two things follow from that:
			 *
			 * 1. Our views already escape every value they print, inline, at the point of
			 *    print -- same as the WooCommerce templates do. There is nothing left for
			 *    an outer pass to escape.
			 * 2. The only unescaped bytes in the buffer come from the WooCommerce order
			 *    hooks the views fire (woocommerce_order_details_before/after_order_table,
			 *    woocommerce_thankyou_{$gateway}, woocommerce_order_details_after_customer_details
			 *    and friends). WooCommerce prints those raw, and so must we. Running kses
			 *    over them is not escaping our output, it is censoring another plugin's --
			 *    it deletes payment gateway markup and leaves customers unable to pay.
			 *
			 * See #9436: wp_kses_post() here broke every gateway that renders its
			 * instructions on woocommerce_thankyou_{$gateway}.
			 */
			add_shortcode( 'wfty_customer_details', array( $data, 'get_customer_info' ) );
			add_shortcode( 'wfty_order_details', array( $data, 'get_order_details' ) );
		}
	}
}


function wfty_customer_first_name() {
	return WFFN_Core()->thank_you_pages->data->get_customer_first_name();
}

function wfty_customer_last_name() {
	return WFFN_Core()->thank_you_pages->data->get_customer_last_name();
}

function wfty_customer_email() {
	return WFFN_Core()->thank_you_pages->data->get_customer_email();
}

function wfty_customer_phone_number() {
	return WFFN_Core()->thank_you_pages->data->get_customer_phone();
}

function wfty_order_number() {
	return WFFN_Core()->thank_you_pages->data->get_order_id();
}

function wfty_order_total( $args ) {
	return WFFN_Core()->thank_you_pages->data->get_order_total( $args );
}

function wfty_order_meta( $args ) {
	return WFFN_Core()->thank_you_pages->data->wfty_order_meta( $args );
}
