<?php

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

global $product, $wfacp_qv_data;
$page_settings = WFACP_Common::get_page_settings( WFACP_Common::get_id() );


$btn_name = esc_html( $product->single_add_to_cart_text() );
if ( isset( $wfacp_qv_data['cart_key'] ) && '' != $wfacp_qv_data['cart_key'] ) {
	$btn_name = __( 'Update', 'woocommerce' );
}


?>
<div class="woocommerce-variation-add-to-cart variations_button">
	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

    <button type="button" class="wfacp_single_add_to_cart_button button alt" name="wfacp_update"><?php echo $btn_name; ?></button>
	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

    <input type="hidden" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>"/>
    <input type="hidden" name="product_id" value="<?php echo absint( $product->get_id() ); ?>"/>
    <input type="hidden" name="variation_id" class="variation_id" value="0"/>
</div>

<?php


include WFACP_TEMPLATE_COMMON . '/quick-view/short-description.php';

