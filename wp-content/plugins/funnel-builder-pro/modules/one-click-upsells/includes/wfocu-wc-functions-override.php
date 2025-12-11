<?php
if ( ! function_exists( 'wc_wfocu_dropdown_variation_attribute_options' ) ) {

	/**
	 * Output a list of variation attributes for use in the cart forms.
	 *
	 * @param array $args Arguments.
	 *
	 * @since 2.4.0
	 */
	function wc_wfocu_dropdown_variation_attribute_options( $args = array() ) {
		$args = wp_parse_args( apply_filters( 'woocommerce_wfocu_dropdown_variation_attribute_options_args', $args ), array(
			'options'          => false,
			'attribute'        => false,
			'product'          => false,
			'selected'         => false,
			'name'             => '',
			'id'               => '',
			'class'            => '',
			'show_option_none' => __( 'Choose an option', 'woocommerce' ),
		) );

		// Get selected value.
		if ( false === $args['selected'] && $args['attribute'] && $args['product'] instanceof WC_Product ) {
			$selected_key     = 'attribute_' . sanitize_title( $args['attribute'] );
			$args['selected'] = isset( $_REQUEST[ $selected_key ] ) ? wc_clean( urldecode( wp_unslash( $_REQUEST[ $selected_key ] ) ) ) : $args['product']->get_variation_default_attribute( $args['attribute'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended , WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$options               = $args['options'];
		$product               = $args['product'];
		$attribute             = $args['attribute'];
		$name                  = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title( $attribute );
		$id                    = $args['id'] ? $args['id'] : sanitize_title( $attribute );
		$class                 = $args['class'];
		$show_option_none      = (bool) $args['show_option_none'];
		$show_option_none_text = $args['show_option_none'] ? $args['show_option_none'] : __( 'Choose an option', 'woocommerce' ); // We'll do our best to hide the placeholder, but we'll need to show something when resetting options.

		if ( empty( $options ) && ! empty( $product ) && ! empty( $attribute ) ) {
			$attributes = $product->get_variation_attributes();
			$options    = $attributes[ $attribute ];
		}

		$html = '<select id="' . WFOCU_Common::clean_ascii_characters( esc_attr( $id ) ) . '" class="' . WFOCU_Common::clean_ascii_characters( esc_attr( $class ) ) . '" name="' . WFOCU_Common::clean_ascii_characters( esc_attr( $name ) ) . '" data-attribute_name="attribute_' . WFOCU_Common::clean_ascii_characters( esc_attr( sanitize_title( $attribute ) ) ) . '" data-show_option_none="' . ( $show_option_none ? 'yes' : 'no' ) . '">';
		$html .= '<option value="">' . esc_html( $show_option_none_text ) . '</option>';

		if ( ! empty( $options ) ) {
			if ( $product && taxonomy_exists( $attribute ) ) {
				// Get terms if this is a taxonomy - ordered. We need the names too.
				$terms = wc_get_product_terms( $product->get_id(), $attribute, array(
					'fields' => 'all',
				) );

				foreach ( $terms as $term ) {
					if ( in_array( $term->slug, $options, true ) ) {
						$selected = apply_filters( 'wfocu_show_default_variation_on_load', true ) ? selected( sanitize_title( $args['selected'] ), $term->slug, false ) : '';
						$html     .= '<option value="' . esc_attr( WFOCU_Common::handle_single_quote_variation( $term->slug ) ) . '" ' . $selected . '>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name ) ) . '</option>';

					}
				}
			} else {
				foreach ( $options as $option ) {
					// This handles < 2.4.0 bw compatibility where text attributes were not sanitized.
					$selected = sanitize_title( $args['selected'] ) === $args['selected'] ? selected( $args['selected'], sanitize_title( $option ), false ) : selected( $args['selected'], $option, false );
					$selected = apply_filters( 'wfocu_show_default_variation_on_load', true ) ? $selected : '';
					$html     .= '<option value="' . esc_attr( WFOCU_Common::handle_single_quote_variation( $option ) ) . '" ' . $selected . '>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) . '</option>';

				}
			}
		}

		$html .= '</select>';

		echo apply_filters_deprecated( 'woocommerce_wfocu_dropdown_variation_attribute_options_html', [ $html ], '2.0', 'This filter is removed as our javascript does not support any other HTML structure.' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
