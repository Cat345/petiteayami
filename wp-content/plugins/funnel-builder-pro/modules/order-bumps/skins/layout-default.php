<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * @var $this WFOB_Bump;
 * @var $wc_product WC_Product;
 */


if ( ! file_exists( WFOB_SKIN_DIR . "/" . $selected_layout . '/layout-body.php' ) ) {
	return;
}


if ( $preview_bump == false ) {


	if ( ! $wc_product instanceof WC_Product ) {
		return;
	}

	if ( ! $wc_product->is_purchasable() && '' == $cart_item_key ) {
		return '';
	}


	$wc_product  = WFOB_Common::set_product_price( $wc_product, $data, $cart_item_key );
	$design_data = $this->get_design_data();

	$parent_product = WFOB_Common::wc_get_product( $parent_id );
	if ( false === $parent_product ) {
		return;
	}

	/*-----------------------------------------Product Title-----------------------------------------------*/
	$product_title = $this->get_bump_heading( $product_key, $wc_product, $cart_item_key, $data, $selected_layout, $skin_type );


	/*-----------------------------------------Product Exclusive Content-----------------------------------------------*/


	$exclusive_content_enable   = isset( $design_data[ 'product_' . $product_key . '_exclusive_content_enable' ] ) ? $design_data[ 'product_' . $product_key . '_exclusive_content_enable' ] : $this->wfob_default_model['exclusive_content_enable'];
	$exclusive_content          = isset( $design_data[ 'product_' . $product_key . '_exclusive_content' ] ) ? $design_data[ 'product_' . $product_key . '_exclusive_content' ] : $this->wfob_default_model['exclusive_content'];
	$exclusive_content_position = isset( $design_data['exclusive_content_position'] ) ? $design_data['exclusive_content_position'] : $this->wfob_default_model['exclusive_content_position'];


	/*-----------------------------------------Social Proof Content-----------------------------------------------*/


	$social_proof_enable  = isset( $design_data[ 'product_' . $product_key . '_social_proof_enable' ] ) ? $design_data[ 'product_' . $product_key . '_social_proof_enable' ] : $this->wfob_default_model['social_proof_enable'];
	$social_proof_heading = '';
	$social_proof_content = '';
	if ( $social_proof_enable ) {
		$social_proof_heading = isset( $design_data[ 'product_' . $product_key . '_social_proof_heading' ] ) ? $design_data[ 'product_' . $product_key . '_social_proof_heading' ] : $this->wfob_default_model['social_proof_heading'];
		$social_proof_content = isset( $design_data[ 'product_' . $product_key . '_social_proof_content' ] ) ? $design_data[ 'product_' . $product_key . '_social_proof_content' ] : $this->wfob_default_model['social_proof_content'];
	}

	$social_proof_content = WFOB_Common::decode_merge_tags( $social_proof_content, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );


	/*-----------------------------------------Product Description-----------------------------------------------*/

	$description = $this->get_bump_description( $product_key, $parent_product, $cart_item_key, $data, $selected_layout, $skin_type );

	/*-----------------------------------------Product Image-----------------------------------------------*/

	$featured_image = $this->get_bump_featured_image( $product_key, $selected_layout, $skin_type );


	$variable_checkbox = '';
	if ( isset( $data['variable'] ) && $cart_variation_id == 0 ) {
		$variable_checkbox = 'wfob_choose_variation';
	}
	$price_data = apply_filters( 'wfob_product_switcher_price_data', [], $wc_product, $qty );

	if ( empty( $price_data ) ) {
		$price_data['regular_org'] = $wc_product->get_regular_price( 'edit' );
		$price_data['price']       = $wc_product->get_price( 'edit' );
	}
	if ( isset( $price_data['regular_org'] ) ) {
		$price_data['regular_org'] = floatval( $price_data['regular_org'] );
	}
	if ( isset( $price_data['price'] ) ) {
		$price_data['price'] = floatval( $price_data['price'] );
	}

	$price_data['regular_org'] *= $qty;
	$price_data['price']       *= $qty;
	$price_data['quantity']    = $qty;

	$enable_price = true;


	if ( isset( $design_data['enable_price'] ) ) {
		if ( '0' === $design_data['enable_price'] || false === wc_string_to_bool( $design_data['enable_price'] ) ) {
			$enable_price = false;
		}
	}

	$variation_attributes = [];
	$product_attributes   = [];
	if ( ! is_null( $cart_item ) && isset( $cart_item['variation_id'] ) ) {
		if ( is_array( $cart_item['variation'] ) && count( $cart_item['variation'] ) ) {
			$product_attributes = $cart_item['variation'];
		} elseif ( 'variation' == $cart_item['data']->get_type() ) {
			$product_attributes = $cart_item['data']->get_attributes();
		}
	} elseif ( 'variation' == $wc_product->get_type() ) {
		$product_attributes = $wc_product->get_attributes();
	}

	$enable_pointer = '';
	if ( '' !== $cart_item_key ) {
		$price_data = WFOB_Common::get_cart_product_price_data( $wc_product, $cart_item, $cart_item['quantity'] );
	} else {
		$price_data = WFOB_Common::get_product_price_data( $wc_product, $price_data );

		$price_data['quantity'] = $qty;
	}

	/*-----------------------------------------Printed Price-----------------------------------------------*/


	$printed_price_raw = $this->get_bump_printed_price( $product_key, $wc_product, $cart_item_key, $price_data, $selected_layout, $skin_type, $cart_item, $design_data, $data );
	$printed_price     = apply_filters( 'wfob_printed_price', $printed_price_raw, $price_data, $wc_product );


	if ( ! empty( $tax_label ) ) {
		$printed_price .= $tax_label;
	}


	$output_response = WFOB_AJAX_Controller::output_resp();


	$output_response    = isset( $output_response['response'] ) && isset( $output_response['response'][ $product_key ] ) ? $output_response['response'][ $product_key ] : $output_response;
	$wfob_error_message = '';
	if ( ! empty( $output_response ) && false == $output_response['status'] && isset( $output_response['error'] ) && $output_response['wfob_id'] == $this->get_id() && $output_response['wfob_product_key'] == $product_key ) {
		$wfob_error_message = $output_response['error'];
	}

	if ( $this->need_to_hide( $wc_product, $cart_item_key ) ) {
		return 'success';
	}


}

if ( true == wc_string_to_bool( $exclusive_content_enable ) ) {
	$css_class[] = 'wfob_active_exclusive';

}

if ( true == wc_string_to_bool( $social_proof_enable ) ) {
	$css_class[] = 'wfob_active_social_proof';

}
if ( isset( $exclusive_content_position ) ) {
	$css_class[] = $exclusive_content_position;
}


$inner_wrapper_class = implode( ' ', $css_class );

?>

    <div class="wfob_wrap_start <?php echo "wfob_" . $selected_layout; ?>" data-product-title="<?php echo $wc_product_name; ?>" data-product-price="<?php echo $wc_product_price ?>">


		<?php
		if ( isset( $wc_product ) && ! is_null( $wc_product ) ) {
			do_action( 'wfob_before_wrapper', $this->get_id(), $wc_product, $product_key, $cart_item_key );
		}


		?>
        <div class="wfob_wrapper" data-wfob-id="<?php echo $bump_id; ?>">


            <div class="<?php echo $inner_wrapper_class; ?>" data-product-key="<?php echo $product_key; ?>"
                 data-wfob-id="<?php echo $bump_id; ?>" cart_key="<?php echo $cart_item_key; ?>">


                <div id="wfob_wrapper_<?php echo $bump_id; ?>" class="wfob_sec_start">

					<?php

					/**
					 * Special Offer Html
					 */
					$special_offer_position = 'wfob_exclusive_outside_top_left';
					include WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';
					$special_offer_position = 'wfob_exclusive_outside_top_right';
					include WFOB_SKIN_DIR . '/template-parts/wfob-special-offer.php';

					include WFOB_SKIN_DIR . "/" . $selected_layout . '/layout-body.php';
					?>

                </div>

            </div>
        </div>
		<?php
		if ( isset( $wc_product ) && ! is_null( $wc_product ) ) {
			do_action( 'wfob_after_wrapper', $this->get_id(), $wc_product, $product_key, $cart_item_key );
		}

		?>
    </div>
<?php
