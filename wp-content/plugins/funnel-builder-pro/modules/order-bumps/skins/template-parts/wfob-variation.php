<?php

if ( ( isset( $data['variable'] ) && false == WFOB_Common::display_not_selected_attribute( $data, $wc_product ) ) || ( isset( $allow_choose_options ) && true === $allow_choose_options ) ) {


	printf( "<a href='#' class='wfob_qv-button var_product' qv-id='%d' qv-var-id='%d'>%s</a>", esc_attr( $data['id'] ), esc_attr( $cart_variation_id ), wp_kses_post( apply_filters( 'wfob_choose_option_text', __( 'Choose an option', 'woocommerce' ) ) ) );
}
