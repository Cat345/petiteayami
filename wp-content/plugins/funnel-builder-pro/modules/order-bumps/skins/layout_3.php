<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * @var $this WFOB_Bump;
 * @var $wc_product WC_Product;
 */
$cart_item_key       = '';
$cart_item           = [];
$result              = WFOB_Common::get_cart_item_key( $product_key );
$is_variable_product = false;
$parent_id           = absint( $data['id'] );
if ( $data['parent_product_id'] && $data['parent_product_id'] > 0 ) {
	$parent_id = absint( $data['parent_product_id'] );
}
if ( ! is_null( $result ) ) {
	$cart_item_key = $result[0];
	$cart_item     = $result[1];
}
$qty               = absint( $data['quantity'] );
$cart_variation_id = 0;
if ( ! empty( $cart_item ) && ! is_null( $cart_item ) ) {
	$qty        = $cart_item['quantity'];
	$wc_product = $cart_item['data'];
	if ( isset( $cart_item['variation_id'] ) ) {
		$cart_variation_id = $cart_item['variation_id'];
	}
} else {
	if ( isset( $data['variable'] ) ) {
		$is_variable_product = true;
		if ( isset( $data['default_variation'] ) ) {
			$variation_id = absint( $data['default_variation'] );
			$wc_product   = WFOB_Common::wc_get_product( $variation_id );
		}
	} else {
		$wc_product = WFOB_Common::wc_get_product( $data['id'] );
	}
}
if ( ! $wc_product instanceof WC_Product ) {
	return;
}
if ( ! $wc_product->is_purchasable() && '' == $cart_item_key ) {
	return '';
}
$wc_product       = WFOB_Common::set_product_price( $wc_product, $data, $cart_item_key );
$design_data      = $this->get_design_data();
$parent_product   = WFOB_Common::wc_get_product( $parent_id );
$product_title    = '';
$do_not_show_bump = isset( $cart_item['_wfob_swap_cart_key'] ) ? true : false;
if ( apply_filters( 'wfob_do_not_display_order_bump_product', false, $this->get_id(), $wc_product, $cart_item_key ) ) {
	return 'success';
}
if ( ! isset( $design_data["product_{$product_key}_title"] ) || '' == $design_data["product_{$product_key}_title"] ) {
	$product_title = $wc_product->get_title();
	if ( in_array( $wc_product->get_type(), WFOB_Common::get_variation_product_type() ) ) {
		if ( absint( $data['parent_product_id'] ) > 0 || '' !== $cart_item_key ) {
			$product_title = $wc_product->get_name();
		}
	}
	$product_title = __( "<span style='color:#ff0000'>Yes!</span> Add ", 'woofunnels-order-bump' ) . '{{product_name}}' . __( ' to my order', 'woofunnels-order-bump' );
} else {
	$product_title = $design_data["product_{$product_key}_title"];
}
$default_data      = WFOB_Common::get_default_model_data( $this->get_id() );
$default_data      = $default_data[ $design_data['layout'] ];
$small_description = isset( $design_data["product_{$product_key}_small_description"] ) ? $design_data["product_{$product_key}_small_description"] : $default_data['product_small_description'];
$sub_title         = isset( $design_data["product_{$product_key}_sub_title"] ) ? $design_data["product_{$product_key}_sub_title"] : $default_data['product_small_title'];
$add_btn_text      = isset( $design_data["product_{$product_key}_add_btn_text"] ) ? $design_data["product_{$product_key}_add_btn_text"] : $default_data['product_add_button_text'];
$added_btn_text    = isset( $design_data["product_{$product_key}_added_btn_text"] ) ? $design_data["product_{$product_key}_added_btn_text"] : $default_data['product_added_button_text'];
$remove_btn_text   = isset( $design_data["product_{$product_key}_remove_btn_text"] ) ? $design_data["product_{$product_key}_remove_btn_text"] : $default_data['product_remove_button_text'];
$description       = isset( $design_data["product_{$product_key}_description"] ) ? $design_data["product_{$product_key}_description"] : $default_data['product_description'];
$featured_image    = true;
if ( ! isset( $design_data["product_{$product_key}_featured_image"] ) || '' == $design_data["product_{$product_key}_featured_image"] ) {
	$featured_image = true;
} else {
	$featured_image = $design_data["product_{$product_key}_featured_image"];
}
$price_data = apply_filters( 'wfob_product_switcher_price_data', [], $wc_product,$qty );
if ( empty( $price_data ) ) {
	$price_data['regular_org'] = $wc_product->get_regular_price( 'edit' );
	$price_data['price']       = $wc_product->get_price( 'edit' );
}
$price_data['regular_org'] *= $qty;
$price_data['price']       *= $qty;
$price_data['quantity']    = $qty;
$enable_price              = true;
if ( isset( $design_data['enable_price'] ) ) {
	$enable_price = wc_string_to_bool( $design_data['enable_price'] );
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
$variable_checkbox = '';
if ( isset( $data['variable'] ) && $cart_variation_id == 0 ) {
	$variable_checkbox = 'wfob_choose_variation';
}
$enable_pointer = '';
if ( '' !== $cart_item_key ) {
	$price_data = WFOB_Common::get_cart_product_price_data( $wc_product, $cart_item, $cart_item['quantity'] );
} else {
	$price_data             = WFOB_Common::get_product_price_data( $wc_product, $price_data );
	$price_data['quantity'] = $qty;
}
$printed_price = '';
if ( apply_filters( 'wfob_show_product_price', true, $wc_product, $cart_item_key, $price_data ) ) {
	$printed_price = WFOB_Common::decode_merge_tags( "{{price}}", $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
} else {
	$printed_price = apply_filters( 'wfob_show_product_price_placeholder', $printed_price, $wc_product, $cart_item_key, $price_data );
}
$description_display_none = '';
if ( false !== strpos( $product_title, '{{more}}' ) || false !== strpos( $sub_title, '{{more}}' ) || false !== strpos( $small_description, '{{more}}' ) ) {
	$description_display_none = 'display:none';
}
$product_title      = WFOB_Common::decode_merge_tags( $product_title, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
$sub_title          = WFOB_Common::decode_merge_tags( $sub_title, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
$small_description  = WFOB_Common::decode_merge_tags( $small_description, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
$description        = WFOB_Common::decode_merge_tags( $description, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
$featured_image     = wc_string_to_bool( $featured_image );
$img_position       = '';
$image_position_cls = '';
$image_width        = '';
if ( $featured_image ) {
	if ( isset( $design_data["product_{$product_key}_featured_image_options"] ) && isset( $design_data["product_{$product_key}_featured_image_options"]['position'] ) ) {
		$img_position       = $design_data["product_{$product_key}_featured_image_options"]['position'];
		$image_position_cls = 'wfob_img_position_' . $img_position;
		$image_width        = floatval( $design_data["product_{$product_key}_featured_image_options"]['width'] );

		if ( ! empty( $image_width ) ) {
			$inline_style = '';
			$inline_style .= '<style>@media (min-width: 768px) {';
			$inline_style .= 'body #wfob_wrap .wfob_wrapper .wfob_bump[data-product-key="' . $product_key . '"]:not(.wfob_img_position_top) .wfob_l3_wrap .wfob_product_image{    -webkit-flex: 0 0 ' . $image_width . 'px; width: calc(100% - ' . $image_width . 'px);}';
			$inline_style .= 'body #wfob_wrap .wfob_wrapper .wfob_bump[data-product-key="' . $product_key . '"]:not(.wfob_img_position_top) .wfob_l3_wrap .wfob_l3_s_c {    -webkit-flex: 0 0 calc(100% - ' . ( $image_width + 15 ) . 'px);}';
			$inline_style .= '}</style>';
			echo $inline_style;
		}

	}
}
$selected_layout = '';
if ( isset( $design_data['layout'] ) ) {
	$selected_layout = $design_data['layout'];
}
$checkbox_class       = 'wfob_bump_product';
$allow_choose_options = apply_filters( 'wfob_allow_choose_options', isset( $data['variable'] ), $wc_product, $cart_item_key );
if ( ( true == $allow_choose_options || ( isset( $data['variable'] ) && $cart_variation_id == 0 ) ) && empty( $cart_item_key ) ) {
	$checkbox_class = 'wfob_choose_variation';
}
?>
    <div id="wfob_wrap" class="wfob_wrap_start" data-product-title="<?php echo $wc_product->get_name() ?>" data-product-price="<?php echo $wc_product->get_price() ?>">
		<?php do_action( 'wfob_before_wrapper', $this->get_id(), $wc_product, $product_key, $cart_item_key ); ?>
        <div id="wfob_main_wrapper_start" class="wfob_wrapper" data-wfob-id="<?php echo $this->get_id(); ?>">
            <div class="wfob_bump wfob_bump_r_outer_wrap wfob_layout_3 <?php echo $image_position_cls ?>" data-product-key="<?php echo $product_key; ?>" data-wfob-id="<?php echo $this->get_id(); ?>" cart_key="<?php echo $cart_item_key; ?>">
                <div class="wfob_l3_wrap">
                    <div class="wfob_l3_s <?php echo ! $featured_image ? 'wfob_l3_no_img' : '' ?>">
						<?php
						if ( true == $featured_image && "right" !== $img_position ) {
							?>
                            <div class="wfob_l3_s_img wfob_product_image">
								<?php
								include __DIR__ . '/image.php'
								?>
                            </div>
							<?php
						}
						?>
                        <div class="wfob_l3_s_c">
                            <div class="wfob_l3_s_data">
								<?php
								if ( '' != $product_title ) {
									?>
                                    <div class="wfob_l3_c_head"><?php echo $product_title; ?></div>
									<?php
								}
								if ( '' != $sub_title ) {
									?>
                                    <div class="wfob_l3_c_sub_head"><?php echo $sub_title ?></div>
									<?php
								}
								if ( '' != $small_description ) {
									?>
                                    <div class="wfob_l3_c_sub_desc show-read-more"><?php echo $small_description ?></div>
									<?php
								}
								if ( ( isset( $data['variable'] ) && false == WFOB_Common::display_not_selected_attribute( $data, $wc_product ) ) || true == $allow_choose_options ) {
									echo sprintf( "<div class='wfob_l3_c_sub_desc_choose_option'><a href='#' class='wfob_qv-button var_product' qv-id='%d' qv-var-id='%d'>%s</a></div>", $data['id'], $cart_variation_id, apply_filters( 'wfob_choose_option_text', __( 'Choose an option', 'woocommerce' ) ) );
								}
								?>
                            </div>
                            <div class="wfob_l3_s_btn">
								<?php
								if ( $enable_price ) {
									?>
                                    <div class="wfob_price">
										<?php echo $printed_price ?>
                                    </div>
									<?php
								}
								?>
                                <a href="#" class="wfob_l3_f_btn wfob_btn_add <?php echo $checkbox_class; ?>" style="<?php echo '' !== $cart_item_key ? 'display:none' : '' ?>"><?php echo $add_btn_text ?></a>
                                <a href="#" class="wfob_l3_f_btn wfob_btn_add wfob_btn_remove <?php echo '' !== $cart_item_key ? 'wfob_item_present' : '' ?>">
                                    <span class="wfob_btn_text_added"><?php echo $added_btn_text ?></span>
                                    <span class="wfob_btn_text_remove"><?php echo $remove_btn_text ?></span>
                                </a>
                            </div>
                            <div class="wfob_clearfix"></div>
                        </div>
						<?php
						if ( true == $featured_image && "right" === $img_position ) {
							?>
                            <div class="wfob_l3_s_img wfob_product_image" style="<?php echo absint( $image_width ) > 0 ? "width:{$image_width}px" : '' ?>">
								<?php
								include __DIR__ . '/image.php'
								?>
                            </div>
							<?php
						}
						?>
                        <div class="wfob_clearfix"></div>
                    </div>
					<?php
					if ( '' !== $description ) {
						if ( isset( $data['variable'] ) && false == WFOB_Common::display_not_selected_attribute( $data, $wc_product ) ) {
							$description .= sprintf( "<a href='#' class='wfob_qv-button var_product' qv-id='%d' qv-var-id='%d'>%s</a>", $data['id'], $cart_variation_id, apply_filters( 'wfob_choose_option_text', __( 'Choose an option', 'woocommerce' ) ) );
						}
						?>
                        <div class="wfob_l3_s_desc" style="<?php echo $description_display_none; ?>">
                            <div class="wfob_l3_l_desc"><?php echo $description ?></div>
                        </div>
						<?php
					}
					?>
                </div>
            </div>
        </div>
		<?php do_action( 'wfob_after_wrapper', $this->get_id(), $wc_product, $product_key, $cart_item_key ); ?>
    </div>
<?php
return 'success';