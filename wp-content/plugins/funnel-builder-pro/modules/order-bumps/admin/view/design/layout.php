<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$product = WFOB_Common::get_bump_products( WFOB_Common::get_id() );

if ( count( $product ) > 0 ) {
	foreach ( $product as $product_key => $data ) {

		$wc_product = wc_get_product( $data['id'] );
		if ( ! $wc_product instanceof WC_Product ) {
			return;
		}

		$type = $wc_product->get_type();
		$qty  = absint( $data['quantity'] );
		if ( isset( $data['variable'] ) && isset( $data['default_variation'] ) ) {
			$variation_id = absint( $data['default_variation'] );
			if ( $variation_id > 0 ) {
				$wc_product = WFOB_Common::wc_get_product( $variation_id );
				$wc_product = WFOB_Common::set_product_price( $wc_product, $data );
			}
		} else {
			$wc_product = WFOB_Common::set_product_price( $wc_product, $data );
		}
		if ( ! $wc_product instanceof WC_Product ) {
			echo '<div class="wfob_design_preview_error">';
			echo __( 'Unable to show Preview. Please save the Products again.', 'woofunnels-order-bumps' );
			echo '</div>';

			return;
		}
		$price_data = apply_filters( 'wfob_product_switcher_price_data', [], $wc_product,1 );
		if ( empty( $price_data ) ) {
			$price_data['regular_org'] = $wc_product->get_regular_price( 'edit' );
			$price_data['price']       = $wc_product->get_price( 'edit' );
		}
		$price_data['regular_org'] *= $qty;
		$price_data['price']       *= $qty;

		$price_data = WFOB_Common::get_product_price_data( $wc_product, $price_data );
		include __DIR__ . '/skin-1.php';
		include __DIR__ . '/skin-3.php';
		include __DIR__ . '/skin-5.php';
		include __DIR__ . '/skin-6.php';
	}
}
?>
