<?php
/**
 * ========================
 * Quick View Template
 * ========================
 * */
//Exit if accessed directly
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
global $product;


add_action( 'wfacp_qv_summary', 'woocommerce_template_single_title', 5 );
//add_action( 'wfacp_qv_summary', 'woocommerce_template_single_rating', 10 );
add_action( 'wfacp_qv_summary', 'woocommerce_template_single_price', 10 );
add_action( 'wfacp_qv_summary', [ WFACP_Core()->public, 'woocommerce_template_single_excerpt' ], 20 );
add_action( 'wfacp_qv_summary', [ WFACP_Core()->public, 'woocommerce_template_single_add_to_cart' ], 25 );
add_action( 'wfacp_qv_summary', function () {
	global $product, $wfacp_product;
	if ( ! is_null( $wfacp_product ) ) {
		$shortDescription = $wfacp_product->get_description();
		if ( '' == $shortDescription ) {
			$shortDescription = $product->get_description();
		}
		echo $shortDescription;
	}
}, 18 );


remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
// hide update qty input field for single variation and simple product type
//add_action( 'wfacp_woocommerce_simple_add_to_cart', [ WFACP_Core()->public, 'woocommerce_simple_add_to_cart' ] );
add_action( 'woocommerce_single_variation', [ WFACP_Core()->public, 'woocommerce_single_variation_add_to_cart_button' ], 20 );

add_action( 'wfac_qv_images', function () {
	include_once WFACP_TEMPLATE_COMMON . '/quick-view/images/product-image.php';
}, 20 );


global $wfacp_product, $product;

if ( is_null( $wfacp_product ) ) {
	add_action( 'wfacp_woocommerce_variable_add_to_cart', [ WFACP_Core()->public, 'woocommerce_variable_add_to_cart' ] );
	add_action( 'wfacp_woocommerce_variable-subscription_add_to_cart', [ WFACP_Core()->public, 'woocommerce_variable_subscription_add_to_cart' ] );
}
add_filter( 'woocommerce_single_product_flexslider_enabled', '__return_true' );

$productType = '';
if ( $product instanceof WC_Product ) {
	$productType = "wfacp_type_" . $product->get_type();
}

add_filter( 'woocommerce_is_sold_individually', '__return_false', 100 );
add_filter( 'woocommerce_is_is_purchasable', '__return_true', 100 );
add_filter( 'woocommerce_variation_is_purchasable', '__return_true', 100 );
add_filter( 'woocommerce_available_variation', [ WFACP_Core()->public, 'force_purchasable_quick_view' ], 99 );
?>
<div id="wfacp_qr_model_wrap" class=" wfacp_qv-inner-modal <?php echo $productType; ?>" data-item-key="<?php echo $item_key; ?>" data-cart-key="<?php echo $cart_key; ?>">
    <div class="wfacp_qv-container woocommerce single-product">
        <div class="wfacp_qv-top-panel">
            <div class="wfacp_qv-close wfacp_qv xooqv-cross"></div>
            <div class="wfacp_qv-preloader wfacp_qv-mpl">
                <div class="wfacp_qv-speeding-wheel"></div>
            </div>
        </div>
        <div class="wfacp_qv-main">
            <div>
                <div class="wfacp_qr_wrap product">
                    <div class="wfacp_qv-images">
						<?php do_action( 'wfac_qv_images' ); ?>
						<?php do_action( 'wfacp_qv_images' ); ?>
                    </div>
                    <div class="wfacp_qv-summary">
						<?php
						/**
						 * @todo
						 * Using our custom hook display only few content
						 * some themes like flatsome changes the normal behaviour of components
						 *
						 */
						?>
						<?php

						do_action( 'wfacp_qv_summary' ); ?>
                    </div>

                    <div class="wfacp_clear"></div>
                </div>
            </div>
        </div>
        <div class="wfacp_option_btn"><?php _e( 'Choose an option', 'woocommerce' ); ?></div>
    </div>
</div>

