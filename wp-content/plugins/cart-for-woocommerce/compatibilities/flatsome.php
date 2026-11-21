<?php
/**
 * Flatsome Theme Compatibility
 *
 * Disables Flatsome's auto-reveal off-canvas mini-cart so it doesn't open on top of FKCart.
 */

namespace FKCart\Compatibilities;

use FKCart\Includes\Data;

if ( ! class_exists( '\FKCart\Compatibilities\Flatsome' ) ) {

	class Flatsome {

		public function __construct() {
			if ( is_customize_preview() || is_admin() || ! wc_string_to_bool( Data::get_value( 'enable_cart' ) ) ) {
				return;
			}

			add_filter( 'theme_mod_cart_dropdown_show', '__return_zero' );
			add_filter( 'fkcart_fragments', array( $this, 'add_header_cart_fragments' ) );
		}

		/**
		 * FKCart's add/update/remove responses carry only FKCart's fragments, so Flatsome's header cart
		 * count, icon label and subtotal (normally sent through woocommerce_add_to_cart_fragments) stay stale.
		 * Add Flatsome's own header fragments; their keys are already the selectors FKCart replaces.
		 *
		 * @param array $fragments FKCart fragments.
		 *
		 * @return array
		 */
		public function add_header_cart_fragments( $fragments ) {
			if ( ! is_array( $fragments ) || is_null( WC()->cart ) ) {
				return $fragments;
			}

			$callbacks = array(
				'flatsome_header_add_to_cart_fragment',
				'flatsome_header_add_to_cart_fragment_count',
				'flatsome_header_add_to_cart_fragment_count_label',
				'flatsome_header_add_to_cart_custom_icon_fragment_count_label',
			);

			foreach ( $callbacks as $callback ) {
				if ( function_exists( $callback ) ) {
					$fragments = $callback( $fragments );
				}
			}

			return $fragments;
		}

		public function is_enable() {
			return ( 'flatsome' === get_template() ) || ( 'flatsome' === get_stylesheet() );
		}
	}

	Compatibility::register( new Flatsome(), 'flatsome' );
}
