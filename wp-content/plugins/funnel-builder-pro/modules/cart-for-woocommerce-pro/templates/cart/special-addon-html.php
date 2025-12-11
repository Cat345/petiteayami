<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$front    = \FKCart\Includes\Front::get_instance();
$settings = $front->get_special_addon_products();
if ( empty( $settings ) ) {
	return;
}

if ( fkcart_is_preview() && is_array( $settings['special_addon_product'] ) && count( $settings['special_addon_product'] ) == 0 ) {


	$products = fkcart_get_dummy_products();
	$front    = \FKCart\Includes\Front::get_instance();
	$items    = $front->get_dummy_preview_item( $products[0] );


	$settings['special_addon_product'] = [
		'key'      => $items['product_id'],
		'value'    => $items['product_name'],
		'image'    => esc_url( plugin_dir_url( FKCART_PLUGIN_FILE ) . 'admin/assets/img/dummy/no-product-found.jpg' ),
		'price'    => $items['price'],
		'is_dummy' => true,
	];


}

if ( ( ! fkcart_is_preview() && ( is_null( WC()->cart ) || WC()->cart->get_cart_contents_count() == 0 ) ) ) {
	return;
}
if ( ! is_array( $settings['special_addon_product'] ) || count( $settings['special_addon_product'] ) <= 0 || ! isset( $settings['special_addon_product']['key'] ) ) {
	return;
}

$special_addon_product_id = ! empty( $settings['special_addon_product']['key'] ) ? $settings['special_addon_product']['key'] : '';
$special_addon_product_id = FKCart\Pro\Special_Add_On::get_map_product( $special_addon_product_id );
if ( empty( $special_addon_product_id ) ) {
	return;
}
/**
 * WC Product data
 */
if ( ! isset( $settings['special_addon_product']['is_dummy'] ) ) {

	$product_obj   = wc_get_product( $special_addon_product_id );
	$product_types = array( 'subscription', 'variable-subscription', 'bundle' );


	if ( ! $product_obj || ! is_a( $product_obj, 'WC_Product' ) ) {
		return;
	}
	if ( ! fkcart_is_preview() && ( ! $product_obj->is_in_stock() || 'publish' !== $product_obj->get_status() ) ) {
		return;
	}
	if ( ! fkcart_is_preview() && ( in_array( $product_obj->get_type(), $product_types ) ) ) {
		return;
	}
	$special_addon_product_price       = $product_obj->get_price_html();
	$special_addon_product_is_variable = ( fkcart_is_variable_product_type( $product_obj->get_type() ) );

} else {
	$special_addon_product_price       = $settings['special_addon_product']['price'];
	$special_addon_product_is_variable = false;
}
$special_addon_product_button = $special_addon_product_is_variable ? __( 'Select options', 'woocommerce' ) : __( '', 'woocommerce' );
$special_addon_product_title  = ! empty( $settings['special_addon_product']['title'] ) ? $settings['special_addon_product']['title'] : '';
$special_addon_product_image  = ! empty( $settings['special_addon_product']['image'] ) ? $settings['special_addon_product']['image'] : '';
/**
 * Wrapper Base Classes on the special addon
 */

$base_class = [ 'fkcart-spl-addons-wrap' ];
if ( $special_addon_product_is_variable === true ) {
	$base_class[] = 'fkcart-spl-addons-vaiation-product';
}


/**
 * Pre-select Special add on
 */
$preselect_special_addon = ! empty( $settings['preselect_special_addon'] ) ? $settings['preselect_special_addon'] : false;

$checked = '';

if ( $preselect_special_addon ) {
	$base_class[] = 'fkcart-spl-addon-preselect';
	$checked      = 'checked=checked';

}

if ( ! isset( $settings['special_addon_product']['is_dummy'] ) && ! empty( $product_obj->get_type() ) ) {
	$base_class[] = 'fkcart-spl-addon-product-type-' . $product_obj->get_type();
}
/**
 * Special Addon Product Heading
 */
$special_addon_heading = ! empty( $settings['special_addon_heading'] ) ? $settings['special_addon_heading'] : '';


/**
 * Special Addon Product Description
 */
$special_addon_desc = ! empty( $settings['special_addon_desc'] ) ? $settings['special_addon_desc'] : '';
/**
 * Special Addon Product Image
 */
$enable_special_addon_image = ! empty( $settings['enable_special_addon_image'] ) ? wc_string_to_bool( $settings['enable_special_addon_image'] ) : false;

/**
 * Special Addon Product Image type to check its Custom or product image
 */
$special_addon_image_type = ! empty( $settings['special_addon_image_type'] ) ? $settings['special_addon_image_type'] : '';

/**
 * If Special Addon Product Image Selected then below variable assigne
 */

$special_addon_custom_image = ! empty( $settings['special_addon_custom_image'] ) ? $settings['special_addon_custom_image'] : '';
/**
 * Special Addon Product Image Size defined width and hieght
 */
$special_addon_image_size = ! empty( $settings['special_addon_image_size'] ) ? $settings['special_addon_image_size'] : '48';

/**
 * Special Addon Product Selection type will be checkbox and toggle
 */

$special_addon_selection_type = ! empty( $settings['special_addon_selection_type'] ) ? $settings['special_addon_selection_type'] : 'checkbox';

/**
 * empty check for heading empty
 */
if ( empty( $special_addon_heading ) ) {
	$base_class[] = 'fkcart-title-empty';
}

/**
 * empty check for Description empty
 *
 */
if ( empty( $special_addon_desc ) ) {
	$base_class[] = 'fkcart-description-empty';
}
/**
 * empty check for Image empty
 *
 */
if ( empty( $enable_special_addon_image ) || false == $enable_special_addon_image ) {
	$base_class[] = 'fkcart-image-disabled';
}

/**
 * empty check for Product price empty
 *
 */
if ( empty( $special_addon_product_price ) ) {
	$base_class = [ 'fkcart-price-empty' ];
}


$fkspl_cart_item_key = '';
$disabled            = '';
$variable_meta       = '';
$tmp                 = [];
$same_product        = false;
if ( ! is_null( WC()->session ) && ! is_null( WC()->cart ) && WC()->cart->get_cart() ) {

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		if ($cart_item['product_id'] !== $special_addon_product_id  && $cart_item['variation_id'] != $special_addon_product_id) {
			continue;
		}
		if ( true === $special_addon_product_is_variable ) {
			$product_meta  = fkcart_get_formatted_cart_item_data( $cart_item );
			$variable_meta = wp_kses_post( $product_meta );
		}
		$fkspl_cart_item_key = $cart_item_key;
		break;
	}
}


if ( true === $same_product ) {
	return true;
}
$special_addon_product_image_src = '';
if ( true === $enable_special_addon_image ) {
	if ( $special_addon_image_type == 'product' ) {
		$special_addon_product_image_src = $special_addon_product_image;
	} else {
		$special_addon_product_image_src = $special_addon_custom_image;
	}
}


if ( $special_addon_selection_type == 'toggle' ) {
	$base_class[] = 'fkcart-toggle-selected';
} else {
	$base_class[] = 'fkcart-checkbox-selected';
}

\FKCart\Includes\cart::get_instance()->update_addon_views( $special_addon_product_id );
?>

<div class="<?php echo esc_attr( implode( ' ', $base_class ) ); ?>" id="fkcart-spl-addon" data-fkcart-product-cart-key='<?php echo $fkspl_cart_item_key; ?>' data-fkcart-product-id='<?php echo $special_addon_product_id; ?>'>
    <div class="fkcart--item">
        <div class="fkcart-d-flex fkcart-gap-12 fkcart-align-items-center">
            <div class="fkcart-d-col-flex fkcart-spl-addon-image-wrap">
                <div class="fkcart-product-image">
                    <img src="<?php echo $special_addon_product_image_src; ?>" alt="">
                </div>
            </div>
            <div class="fkcart-d-col-flex fkcart-item-meta-wrap">
                <div class="fkcart-item-meta">
                    <a target="_blank" href="<?php echo get_the_permalink( $special_addon_product_id ); ?>" class="fkcart-item-title">
						<?php echo $special_addon_heading; ?>
                    </a>

                    <div class="fkcart-item-meta-content">
                        <p><?php echo $special_addon_desc; ?></p>
                        <a href="javascript:void(0)" class="fkcart-learn-more"><?php echo apply_filters( 'fkcart_shipping_protection_learn_more', __( 'Learn More', 'woocommerce' ) ) ?></a>
                    </div>
                    <div class="fkcart-item-meta-content-wrap"><?php echo $variable_meta; ?></div>
                    <a href="javascript:void(0)" class="fkcart-select-product fkcart-spl-addon-select" data-id="<?php echo $special_addon_product_id; ?>" data-action-type="special_addon"><?php echo $special_addon_product_button; ?></a>

                </div>
            </div>
            <div class="fkcart-d-col-flex">
                <div class="fkcart-toggle-switcher">
                    <input type="checkbox" name="fkcart_spl_addon_checkbox" id="fkcart-spl-addon-checkbox" class="fkcart-spl-checkbox fkcart-switch" <?php echo '' != $fkspl_cart_item_key ? 'checked' : ''; ?> <?php echo $disabled; ?> data-fkcart-product-id="<?php echo $special_addon_product_id; ?>">
                    <label for="fkcart-spl-addon-checkbox"><span class="sw"></span></label>
                </div>

                <div class="fkcart-price-wrap">
					<?php echo $special_addon_product_price; ?>
                </div>

            </div>

        </div>
    </div>

</div>

