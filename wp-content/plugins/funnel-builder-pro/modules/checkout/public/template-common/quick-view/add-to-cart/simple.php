<?php


if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

global $product, $wfacp_qv_data;

if ( ! $product->is_purchasable() ) {
	return;
}


$page_settings = WFACP_Common::get_page_settings( WFACP_Common::get_id() );
if ( wc_string_to_bool( $page_settings['hide_quantity_switcher'] ) ) {
	return;
}
$btn_name = esc_html( $product->single_add_to_cart_text() );
if ( isset( $wfacp_qv_data['cart_key'] ) && '' != $wfacp_qv_data['cart_key'] ) {
	$btn_name = __( 'Update', 'woocommerce' );
}


//echo wc_get_stock_html( $product ); // WPCS: XSS ok.

if ( $product->is_in_stock() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

    <form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<?php

		do_action( 'woocommerce_before_add_to_cart_quantity' );

		woocommerce_quantity_input( array(
			'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
			'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
			'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
		) );

		do_action( 'woocommerce_after_add_to_cart_quantity' );


		?>
        <button type="button" name="wfacp_update" value="<?php echo esc_attr( $product->get_id() ); ?>" class="wfacp_single_add_to_cart_button button alt" id="wfacp_update_item"><?php echo $btn_name; ?></button>
		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
    </form>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>
