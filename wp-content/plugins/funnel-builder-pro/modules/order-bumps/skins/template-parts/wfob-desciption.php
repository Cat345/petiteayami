<?php
$wc_product         = isset( $wc_product ) ? $wc_product : null;
$cart_item_key      = isset( $cart_item_key ) ? $cart_item_key : '';
$decode_merge_tags  = isset( $decode_merge_tags ) ? $decode_merge_tags : '';
$wfob_error_message = isset( $wfob_error_message ) ? $wfob_error_message : '';

do_action( 'wfob_before_product_description' );
echo '<div class="wfob_text_inner wfob_skin_description">' . WFOB_Common::kses_bump_content( do_shortcode( $decode_merge_tags ) ) . '</div>';

/**
 * Fires after a bump's description.
 *
 * @since 1.28.0 Added the `$product_key` and `$wfob_id` arguments.
 *
 * @param WC_Product|null $wc_product    The product the bump sells.
 * @param string          $cart_item_key Cart item key, empty until the bump is accepted.
 * @param array           $data          The bump row's data.
 * @param string          $product_key   The row's key in the bump.
 * @param int             $wfob_id       Bump id, 0 when unknown.
 */
do_action( 'wfob_after_product_description', $wc_product ?? null, $cart_item_key ?? '', $data ?? array(), $product_key ?? '', isset( $this ) && method_exists( $this, 'get_id' ) ? (int) $this->get_id() : 0 );
echo '<div class="wfob_error_message">' . esc_html( $wfob_error_message ) . '</div>';
