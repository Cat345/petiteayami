<?php


$get_product_object = $data['product']->data;

if ( is_a( $get_product_object, 'WC_Product_Variation' ) ) {

	$attributes_variation = $get_product_object->get_attributes();
	$product_id           = $get_product_object->get_parent_id();
	$adding_to_cart       = wc_get_product( $product_id );
	$attributes           = $adding_to_cart->get_variation_attributes();

	$options = array();
	?>
    <div class="wfocu-product-attr-wrapper">
        <form class="wfocu_attributes_selector_form" data-key="<?php echo $data['key']; ?>">
            <div class="wfocu_attributes_selector_wrap" data-key="<?php echo $data['key']; ?>">
                <table class="variations" cellspacing="0">
                    <tbody>
					<?php

					foreach ( $attributes as $name => $select ) {
					if ( isset( $attributes_variation[ $name ] ) && $attributes_variation[ $name ] !== '' ) {
						continue;
					}
					if ( isset( $attributes_variation[ sanitize_title( $name ) ] ) && $attributes_variation[ sanitize_title( $name ) ] !== '' ) {
						continue;
					}

					?>
                    <tr>
                        <td class="label"><label for="<?php echo sanitize_title( $name ); ?>"><?php echo wc_attribute_label( $name ); ?></label></td>
                        <td class="value" data-attribute-title="<?php echo wc_attribute_label( $name ); ?>">

							<?php
							$html = '<select id="' . esc_attr( $name ) . '"  name="attribute_' . esc_attr( $name ) . '" data-attribute_name="attribute_' . esc_attr( sanitize_title( $name ) ) . '" >';

							if ( ! empty( $select ) ) {
								if ( $adding_to_cart && taxonomy_exists( $name ) ) {
									// Get terms if this is a taxonomy - ordered. We need the names too.
									$terms = wc_get_product_terms( $adding_to_cart->get_id(), $name, array(
										'fields' => 'all',
									) );

									foreach ( $terms as $term ) {

										if ( in_array( $term->slug, $select, true ) ) {
											$html .= '<option value="' . esc_attr( $term->slug ) . '" ' . selected( sanitize_title( $select[0] ), $term->slug, false ) . '>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name ) ) . '</option>';
										}
									}
								} else {
									foreach ( $select as $option ) {
										// This handles < 2.4.0 bw compatibility where text attributes were not sanitized.
										$selected = sanitize_title( $args['selected'] ) === $args['selected'] ? selected( $args['selected'], sanitize_title( $option ), false ) : selected( $args['selected'], $option, false );
										$html     .= '<option value="' . esc_attr( $option ) . '" ' . $selected . '>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) . '</option>';
									}
								}
							}

							$html .= '</select></td></tr>';
							echo $html;
							}
							?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
	<?php
}

