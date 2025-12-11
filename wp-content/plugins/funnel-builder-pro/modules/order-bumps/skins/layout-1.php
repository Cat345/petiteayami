<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * @var $this WFOB_Bump;
 * @var $wc_product WC_Product;
 */
$cart_item_key = '';
$cart_item     = [];

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
$do_not_show_bump = isset( $cart_item['_wfob_swap_cart_key'] ) ? true : false;

if ( $this->need_to_hide( $wc_product, $cart_item_key ) ) {
	return 'success';
}
$product_title = '';
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
$description = '';
if ( ! isset( $design_data["product_{$product_key}_description"] ) || '' == $design_data["product_{$product_key}_description"] ) {
	$description = $parent_product->get_short_description();
} else {
	$description = $design_data["product_{$product_key}_description"];
}
$featured_image = true;
if ( ! isset( $design_data["product_{$product_key}_featured_image"] ) || '' == $design_data["product_{$product_key}_featured_image"] ) {
	$featured_image = true;
} else {
	$featured_image = $design_data["product_{$product_key}_featured_image"];
}
$price_data = apply_filters( 'wfob_product_switcher_price_data', [], $wc_product, $qty );
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
$output_response    = isset( $output_response['response'][ $product_key ] ) ? $output_response['response'][ $product_key ] : [];
$wfob_error_message = '';
if ( ! empty( $output_response ) && false == $output_response['status'] && isset( $output_response['error'] ) && $output_response['wfob_id'] == $this->get_id() && $output_response['wfob_product_key'] == $product_key ) {
	$wfob_error_message = $output_response['error'];
}
$do_not_show_bump = isset( $cart_item['_wfob_swap_cart_key'] ) ? true : false;
if ( apply_filters( 'wfob_do_not_display_order_bump_product', false, $this->get_id(), $wc_product, $cart_item_key ) ) {
	return 'success';
}
$featured_image     = wc_string_to_bool( $featured_image );
$img_position       = '';
$image_position_cls = '';
$image_width        = '';
if ( $featured_image ) {
	if ( isset( $design_data["product_{$product_key}_featured_image_options"] ) && isset( $design_data["product_{$product_key}_featured_image_options"]['position'] ) ) {
		$img_position       = $design_data["product_{$product_key}_featured_image_options"]['position'];
		$image_width        = $design_data["product_{$product_key}_featured_image_options"]['width'];
		$image_position_cls = 'wfob_img_position_' . $img_position;
	}
	$inline_style = '';
	$inline_style .= '<style>@media (min-width: 768px) {';
	$inline_style .= 'body #wfob_wrap .wfob_wrapper .wfob_bump[data-product-key="' . $product_key . '"] .wfob_contentBox .wfob_pro_img_wrap{ width: ' . $image_width . 'px;}';
	$inline_style .= '}</style>';
	echo $inline_style;
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
        <div class="<?php echo implode( ' ', [ 'wfob_wrapper', "wfob_" . $selected_layout ] ) ?>" data-wfob-id="<?php echo $this->get_id(); ?>">
            <div class="wfob_bump wfob_clear <?php echo $image_position_cls ?>" data-product-key="<?php echo $product_key; ?>" data-wfob-id="<?php echo $this->get_id(); ?>" cart_key="<?php echo $cart_item_key; ?>">
                <div class="wfob_outer">
                    <div class="wfob_Box">
                        <div class="wfob_bgBox_table no_table">
                            <div class="wfob_bgBox_tablecell no_table_cell wfob_check_container">
                                <div class="wfob_order_wrap wfob_content_bottom_wrap">
                                    <div class="wfob_bgBox_table_box">
                                        <div class="wfob_bgBox_cell wfob_img_box">
                                            <div class="wfob_checkbox_input_wrap">
												<?php
												if ( wc_string_to_bool( $design_data['header_enable_pointing_arrow'] ) ) {
													if ( '1' == $design_data['point_animation'] ) {
														$blink_url = WFOB_PLUGIN_URL . '/assets/img/arrow-blink.gif';
													} else {
														$blink_url = WFOB_PLUGIN_URL . '/assets/img/arrow-no-blink.gif';
													}
													$enable_pointer = 'wfob_enable_pointer';
													?>
                                                    <span class="wfob_blink_img_wrap"><img src="<?php echo $blink_url; ?>"></span>
													<?php
												}
												$disabled = '';
												if ( true === apply_filters( 'wfob_disabled_checkbox', false, $product_key ) ) {
													$disabled = "disabled";
												}
												$enable_for_label        = $product_key;
												$wfob_merge_tag_in_title = "";
												if ( $selected_layout == 'layout_1' && strpos( $product_title, ' {{quantity_incrementer}}' ) !== false ) {
													$enable_for_label        = '';
													$wfob_merge_tag_in_title = "wfob_merge_tag_in_title";
												}
												?>
                                                <span class="wfob_bump_checkbox">
                                                <input type="checkbox" name="<?php echo $product_key; ?>" id="<?php echo $product_key; ?>" data-value="<?php echo $product_key; ?>" class="wfob_checkbox <?php echo $checkbox_class ?>" <?php echo '' != $cart_item_key ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                                            </span>
                                            </div>
                                        </div>
										<?php
										$titleHeading = WFOB_Common::decode_merge_tags( $product_title, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
										?>
                                        <div class="wfob_bgBox_cell">
                                            <div class="wfob_content_sec <?php echo $enable_pointer; ?>">
                                                <label for="<?php echo $enable_for_label; ?>" class="wfob_title wfob_bgBox_cell <?php echo $wfob_merge_tag_in_title; ?>"> <?php echo do_shortcode( $titleHeading ); ?> </label>
												<?php
												if ( $enable_price ) {
													?>
                                                    <div class=" wfob_price_container wfob_not_mobile wfob_bgBox_cell">
                                                        <div class="wfob_price">
															<?php
															echo $printed_price;
															?>
                                                        </div>
                                                    </div>
												<?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="wfob_error_message"><?php echo $wfob_error_message ?></div>
                                </div>
								<?php if ( $enable_price ) { ?>
                                    <div class="wfob_bgBox_tablecell wfob_price_container wfob_yes_mobile">
                                        <div class="wfob_price">
											<?php
											echo $printed_price;
											?>
                                        </div>
                                    </div>
								<?php } ?>
                            </div>
                        </div>
                        <div class="wfob_contentBox wfob_clear">
							<?php
							if ( $featured_image ) {
								?>
                                <div class="wfob_pro_img_wrap">
									<?php
									include __DIR__ . '/image.php';
									?>
                                </div>
								<?php
							}
							$decode_merge_tags = WFOB_Common::decode_merge_tags( $description, $price_data, $wc_product, $data, $cart_item, $cart_item_key, $product_key, $design_data );
							?>
                            <div class="wfob_pro_txt_wrap">
                                <div class="wfob_text_inner"><?php echo do_shortcode( $decode_merge_tags ); ?> </div>
								<?php
								if ( ( isset( $data['variable'] ) && false == WFOB_Common::display_not_selected_attribute( $data, $wc_product ) ) || true == $allow_choose_options ) {
									printf( "<a href='#' class='wfob_qv-button var_product' qv-id='%d' qv-var-id='%d'>%s</a>", $data['id'], $cart_variation_id, apply_filters( 'wfob_choose_option_text', __( 'Choose an option', 'woocommerce' ) ) );
								}
								?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<?php do_action( 'wfob_after_wrapper', $this->get_id(), $wc_product, $product_key, $cart_item_key ); ?>
    </div>
<?php
return 'success';
