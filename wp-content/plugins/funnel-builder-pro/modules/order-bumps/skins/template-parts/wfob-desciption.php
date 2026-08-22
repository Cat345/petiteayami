<?php
$wc_product         = isset( $wc_product ) ? $wc_product : null;
$cart_item_key      = isset( $cart_item_key ) ? $cart_item_key : '';
$decode_merge_tags  = isset( $decode_merge_tags ) ? $decode_merge_tags : '';
$wfob_error_message = isset( $wfob_error_message ) ? $wfob_error_message : '';

do_action( 'wfob_before_product_description' );
echo '<div class="wfob_text_inner wfob_skin_description">' . WFOB_Common::kses_bump_content( do_shortcode( $decode_merge_tags ) ) . '</div>';
do_action( 'wfob_after_product_description', $wc_product ?? null, $cart_item_key ?? '', $data ?? array() );
echo '<div class="wfob_error_message">' . esc_html( $wfob_error_message ) . '</div>';
