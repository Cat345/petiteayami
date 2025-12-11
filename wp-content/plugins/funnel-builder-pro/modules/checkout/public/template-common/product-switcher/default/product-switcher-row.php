<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $pro WC_Product;
 * @var $product_data []
 */
if ( ! $pro instanceof WC_Product ) {
	return;
}
if ( isset( WC()->cart->removed_cart_contents[ $cart_item_key ] ) ) {
	return;
}

//Check Product is Purchasable for all type Product setting  (Radio,Checkbox) Except Variable Product
if ( '' == $cart_item_key && ! in_array( $product_data['type'], WFACP_Common::get_variable_product_type() ) ) {
	$manage_stock = WFACP_Common::check_manage_stock( $pro, $product_data['org_quantity'] );
	if ( false == $manage_stock || false == $pro->is_purchasable() ) {
		return;
	}
}


$hide_quick_view        = wc_string_to_bool( $switcher_settings['settings']['hide_quick_view'] );
$hide_quantity_switcher = wc_string_to_bool( $switcher_settings['settings']['hide_quantity_switcher'] );
$enable_delete_item     = false;
$you_save_text          = isset( $product_data['you_save_text'] ) ? $product_data['you_save_text'] : '';
$input_class            = 'wfacp_product_choosen';
if ( 'radio' === $type ) {
	$input_class = 'wfacp_product_switch';
} elseif ( 'hidden' == $type ) {
	$enable_delete_item = wc_string_to_bool( $switcher_settings['settings']['enable_delete_item'] );
}
$force_all_setting = false;
if ( isset( $switcher_settings['product_settings']['add_to_cart_setting'] ) && 1 == $switcher_settings['product_settings']['add_to_cart_setting'] ) {
	$force_all_setting = true;

}
$cart_variation_id = 0;
if ( ! is_null( $cart_item ) ) {
	if ( isset( $cart_item['variation_id'] ) ) {
		$cart_variation_id = $cart_item['variation_id'];
	}
}
$product_is_hide_cls = 'wfacp_without_qty';
if ( true != $hide_quantity_switcher ) {
	$product_is_hide_cls = 'wfacp_with_qty';
}

list( $product_attributes, $is_variation_error, $attributes_keys, $variation_attributes ) = WFACP_Common::get_cart_item_attributes( $cart_item, $pro, $product_data, $cart_variation_id );

$checked_cls = 'wfacp_ps_checked';
if ( '' == $product_data['is_checked'] ) {
	$checked_cls = 'wfacp_ps_not_checked';
}
$ps_cls     = 'ps_' . $type;
$cart_count = count( WC()->cart->get_cart_contents() );

$product_selected_class = '';
if ( ! isset( WC()->cart->removed_cart_contents[ $cart_item_key ] ) ) {
	if ( 'radio' === $type && '' !== $product_data['is_checked'] && '' !== $cart_item_key ) {
		$product_selected_class = 'wfacp-selected-product';
	} else {
		if ( '' !== $cart_item_key ) {
			$product_selected_class = 'wfacp-selected-product';
		}
	}
}
$is_sold_individually = false;
$item_key             = isset( $product_data['item_key'] ) ? $product_data['item_key'] : '';
$product_title        = WFACP_Common::get_product_switcher_item_title( $cart_item, $cart_item_key, $pro, $switcher_settings, $product_data, $variation_attributes );


$best_value          = isset( $product_data['best_value_text'] ) ? $product_data['best_value_text'] : '';
$best_value_position = isset( $product_data['best_value_position'] ) ? $product_data['best_value_position'] : null;
$quick_preview       = '';
$eye_icon_url        = '<svg xmlns="http://www.w3.org/2000/svg" width="101" height="100" viewBox="0 0 101 100" fill="#999999">
<path d="M50.9961 0C37.7351 0 25.0177 5.26782 15.641 14.6449C6.26391 24.0213 0.996094 36.7394 0.996094 50C0.996094 63.2606 6.26391 75.9784 15.641 85.3551C25.0173 94.7322 37.7355 100 50.9961 100C64.2567 100 76.9745 94.7322 86.3512 85.3551C95.7283 75.9787 100.996 63.2606 100.996 50C100.996 36.7394 95.7283 24.0216 86.3512 14.6449C76.9748 5.26782 64.2567 0 50.9961 0ZM51.6162 75.5298C52.0536 75.7642 52.5788 75.7642 53.0162 75.5298C53.8797 74.902 54.6659 74.1738 55.3558 73.3598C56.3561 72.1698 57.2356 70.88 58.2254 69.54L59.6156 70.3799C57.6158 73.7197 55.556 76.8195 52.4958 79.1396C50.8887 80.4196 48.9566 81.2268 46.9163 81.4694C42.1465 82.0093 39.0961 78.7595 40.2367 74.0797C41.2369 69.9697 42.5065 65.9199 43.6664 61.8399C44.8265 57.76 45.9963 53.5196 47.1764 49.3798C47.2713 48.9711 47.341 48.5574 47.3864 48.1403C47.5698 47.4337 47.4282 46.6818 47.0006 46.0903C46.5738 45.4988 45.9048 45.1284 45.1758 45.0803C44.4511 45.0161 43.7236 44.9896 42.996 45.0001C43.0086 44.7601 43.0456 44.5223 43.1062 44.29L43.2464 43.2897L60.4861 40.5295L59.2864 44.7092L59.0164 45.7094C56.5157 54.4433 54.0059 63.2065 51.4858 72.0003C51.2291 72.6888 51.0644 73.4087 50.9961 74.1403C50.9954 74.6711 51.2207 75.1769 51.6162 75.5305V75.5298ZM55.7562 30.9998C54.103 30.9886 52.5225 30.3204 51.3625 29.1423C50.2032 27.9641 49.56 26.3723 49.576 24.7198C49.576 22.5009 50.7598 20.4509 52.6816 19.3418C54.6026 18.232 56.9701 18.232 58.891 19.3418C60.8128 20.4509 61.9965 22.5009 61.9965 24.7198C61.9937 26.3772 61.3366 27.9669 60.1683 29.143C59 30.3191 57.4144 30.9866 55.7564 30.9998H55.7562Z" fill="#999999"/>
</svg>';
$eye_icon_url        = apply_filters( 'wfacp_show_popup_icon', $eye_icon_url );
$img_show=true;
$img_show_icon="<img src='%s'>";
if ( false !== strpos( $eye_icon_url, '<svg' ) ) {
	$img_show=false;
	$img_show_icon=$eye_icon_url;
}
$choose_label        = '';
if ( in_array( $product_data['type'], WFACP_Common::get_variable_product_type() ) ) {
	/**
	 * @var $pro WC_Product_Variable;
	 */
	$choose_label = sprintf( "<a href='#' class='wfacp_qv-button var_product $is_variation_error' qv-id='%d' qv-var-id='%d'>%s</a>", $product_data['id'], $cart_variation_id, apply_filters( 'wfacp_choose_option_text', __( 'Choose an option', 'woocommerce' ) ) );
	if ( true != $hide_quick_view ) {
		$quick_preview = sprintf( "<a class='wfacp_qv-button' qv-id='%d'  qv-var-id='%d'>$img_show_icon</a>", $product_data['id'], $cart_variation_id, $eye_icon_url );
	}
} else {
	if ( true != $hide_quick_view ) {
		$quick_preview = sprintf( "<a class='wfacp_qv-button' qv-id='%d'>$img_show_icon</a>", $pro->get_id(), $eye_icon_url );
	}
}
list( $you_save_text_html, $subscription_product_string ) = WFACP_Common::get_product_switcher_item_you_save( $you_save_text, $price_data, $pro, $product_data, $cart_item, $cart_item_key );

if ( $pro->is_sold_individually() ) {
	$is_sold_individually = true;

}
$best_val_class = '';
if ( '' != $best_value ) {
	$best_val_class = 'wfacp_best_val_wrap';
}


$enable_delete_options = WFACP_Common::delete_option_enable_in_product_switcher();
if ( false == $enable_delete_options ) {
	$product_data['enable_delete'] = true;
}
$enable_hide_img = '';
if ( ( isset( $product_data['hide_product_image'] ) && true === $product_data['hide_product_image'] ) && true === $force_all_setting ) {
	$enable_hide_img = 'wfacp_ps_enable_hideImg1';
} else {
	$enable_hide_img = 'wfacp_ps_disable_hideImg1';
}
$wfacp_ps_active_radio_checkbox = '';
if ( false === $force_all_setting ) {
	$wfacp_ps_active_radio_checkbox = 'wfacp_ps_active_radio_checkbox';
}

$hide_qty_switcher_cls = '';
if ( $hide_quantity_switcher == true ) {
	$hide_qty_switcher_cls = 'wfacp_hide_qty_switcher';
}

if ( 'hidden' == $type && true === $enable_delete_item && ! is_null( WFACP_Common::get_cart_item_from_removed_items( $item_key ) ) ) {
	return;
}


$wfacp_you_save_text_html = 'wfacp_you_save_text_blank';
if ( '' !== $you_save_text_html ) {
	$wfacp_you_save_text_html = '';
}

$inner_class        = [ $best_val_class, $best_value_position, $product_is_hide_cls, $product_selected_class ];
$inner_class_string = implode( ' ', $inner_class );


if ( apply_filters( 'wfacp_product_switcher_hide_row', false, $product_data, $pro, $cart_item_key ) ) {
	return;
}
add_filter( 'wp_get_attachment_image_attributes', 'WFACP_Common::remove_src_set' );


?>

    <fieldset class="woocommerce-cart-form__cart-item cart_item wfacp_product_row <?php echo trim( $inner_class_string ); ?>" data-item-key="<?php echo $item_key; ?>" cart_key="<?php echo $cart_item_key; ?>" data-id="<?php echo $pro->get_id(); ?>">
		<?php

		if ( '' != $best_value && ( 'top_left_corner' == $best_value_position || 'top_right_corner' == $best_value_position ) ) {
			printf( "<legend class='wfacp_best_value wfacp_%s'>%s</legend>", $best_value_position, $best_value );
		}
		?>
        <div class="wfacp_row_wrap <?php echo $ps_cls . ' ' . $enable_hide_img . ' ' . $wfacp_you_save_text_html; ?>">
            <div class="wfacp_ps_title_wrap">
                <div class="wfacp_product_switcher_col wfacp_product_switcher_col_1 ">
                    <input id="wfacp_product_choosen_<?php echo $item_key; ?>" type="<?php echo $type; ?>" name="wfacp_product_choosen" class="<?php echo $input_class; ?> wfacp_switcher_checkbox input-checkbox" data-item-key="<?php echo $item_key; ?>" <?php echo $product_data['is_checked']; ?> cart_key="<?php echo $cart_item_key; ?>">
					<?php
					if ( 'hidden' == $type && true === $enable_delete_item && true === wc_string_to_bool( $product_data['enable_delete'] ) && isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
						$item_class = 'wfacp_remove_item_from_cart';
						$item_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
  <path d="M16.3394 9.32245C16.7434 8.94589 16.7657 8.31312 16.3891 7.90911C16.0126 7.50509 15.3798 7.48283 14.9758 7.85938L12.0497 10.5866L9.32245 7.66048C8.94589 7.25647 8.31312 7.23421 7.90911 7.61076C7.50509 7.98731 7.48283 8.62008 7.85938 9.0241L10.5866 11.9502L7.66048 14.6775C7.25647 15.054 7.23421 15.6868 7.61076 16.0908C7.98731 16.4948 8.62008 16.5171 9.0241 16.1405L11.9502 13.4133L14.6775 16.3394C15.054 16.7434 15.6868 16.7657 16.0908 16.3891C16.4948 16.0126 16.5171 15.3798 16.1405 14.9758L13.4133 12.0497L16.3394 9.32245Z" fill="currentColor"></path>
  <path fill-rule="evenodd" clip-rule="evenodd" d="M1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12ZM12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21Z" fill="currentColor"></path>
</svg>';
						?>
                        <div class="wfacp_product_switcher_remove_product wfacp_delete_item">
                            <a href="javascript:void(0)" class="<?php echo $item_class; ?>" data-cart_key="<?php echo $cart_item_key; ?>" data-item_key="<?php echo $item_key; ?>"><?php echo $item_icon; ?></a>
                        </div>
						<?php
					}

					$merge_tag_quantity = WFACP_Common::product_switcher_merge_tags( "{{quantity}}", $price_data, $pro, $product_data, $cart_item, $cart_item_key );
					if ( false == $product_data['hide_product_image'] ) {
						$qtyHtml = sprintf( '<div class="wfacp-qty-ball"><span class="wfacp-qty-count wfacp_product_switcher_quantity"><span class="wfacp-pro-count">%s</span></span></div>', $merge_tag_quantity );

						$default_size = apply_filters( 'wfacp_product_image_size', [ 100, 100 ] );
						$thumbnail    = $pro->get_image( $default_size, [ 'srcset' => false ] );
						echo sprintf( '<div class="product-image"><div class="wfacp-pro-thumb">%s</div>%s</div>', $thumbnail, $qtyHtml );
					}
					?>
                </div>
                <div class="wfacp_product_switcher_col wfacp_product_switcher_col_2">
					<?php
					echo "<div class='wfacp_product_switcher_description'>";
					$variation_class = count( $variation_attributes ) > 0 ? 'wfacp_variation_product_title' : '';
					$best_value_html = '';
					if ( '' != $best_value ) {
						$best_value_html = sprintf( "<span class='wfacp_best_value wfacp_best_value_below'>%s</span>", $best_value );
					}
					if ( '' != $best_value && ( 'above' == $best_value_position ) ) {
						echo "<div class='wfacp_best_value_container'>" . $best_value_html . "</div>";
					}
					$best_value_default = ( '' != $best_value && '' == $best_value_position ) ? $best_value_html : '';
					if ( true == $product_data['hide_product_image'] ) {

						if ( apply_filters( 'wfacp_display_product_qty_with_title', false ) ) {
							$product_title .= '<span class="wfacp_product_row_quantity wfacp_product_switcher_quantity"> x' . $merge_tag_quantity . "</span>";
						}

					}


					?>
                    <div class="product-name product_name">
                        <div class="wfacp_product_sec">
                            <div class="wfacp_product_name_inner">
                            <span class="wfacp_product_choosen_label_wrap <?php echo $variation_class ?>">
                                <span class="wfacp_product_choosen_label" for="<?php echo "wfacp_product_{$item_key}" ?>"><?php echo $product_title . " " . $best_value_default; ?></span>
                            </span>
								<?php echo $quick_preview ?>
                            </div>
                            <div class="wfacp_product_attributes">
								<?php
								$attribute_html = WFACP_Common::get_attribute_html( $cart_item, $cart_item_key, $pro, $switcher_settings, $product_data );
								if ( ! is_null( $attribute_html ) && isset( $attribute_html['selected'] ) ) {
									echo $attribute_html['selected'];
								}
								?>
                                <div class="wfacp_product_select_options">
									<?php
									echo ! empty( $attribute_html['not_selected'] ) ? $attribute_html['not_selected'] : $choose_label;
									?>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
					if ( '' != $best_value && 'below' == $best_value_position ) {
						echo "<div class='wfacp_best_value_container'>" . $best_value_html . "</div>";
					}

					echo '<div class="wfacp_ps_div_row">';
					if ( $you_save_text_html != '' ) {

						echo $you_save_text_html;
					}
					echo apply_filters( 'wfacp_subscription_string', $subscription_product_string, $pro, $price_data, $cart_item_key );
					echo '</div>';
					echo '</div>';
					$cls_sold_individually = '';
					if ( isset( $is_sold_individually ) && $is_sold_individually == 1 ) {
						$cls_sold_individually = 'wfacp_sold_indi';
					}
					?>
                </div>
            </div>
            <div class="wfacp_product_sec_start ">

				<?php
				$hide_qty_switcher_cls = '';
				if ( true == $hide_quantity_switcher ) {
					$hide_qty_switcher_cls = 'wfacp_hide_qty_switcher1';
				}
				?>
                <div class="wfacp_product_switcher_col wfacp_product_switcher_col_3 <?php echo $cls_sold_individually . " " . $hide_qty_switcher_cls; ?>">
                    <div class="wfacp_product_quantity_container">
						<?php
						if ( ! $pro->is_sold_individually() && apply_filters( 'wfacp_product_switcher_show_quantity_incrementer', true, $product_data, $pro, $cart_item_key ) ) {
							$rqty       = 1;
							$disableQty = '';
							$qty_step   = 1;
							if ( '' !== $cart_item_key ) {
								$qty_step = 0;
								$rqty     = $product_data['quantity'];
							} else {
								if ( $type == 'radio' ) {
									$disableQty = 'disabled';
								}
							}
							$minMax = apply_filters( 'wfacp_product_item_min_max_quantity', [ 'min' => $qty_step, 'max' => '', 'step' => 1 ], $pro, $item_key, $cart_item_key );

							$rqty               = apply_filters( 'wfacp_item_quantity', $rqty, $cart_item, $cart_item_key );
							$quantity_input_cls = 'wfacp_product_switcher_quantity wfacp_product_quantity_number_field';
							$quantity_input_cls = apply_filters( 'wfacp_product_switcher_row_dedicated_quantity_input_switcher', $quantity_input_cls, $cart_item_key );
							?>
                            <div class="wfacp_quantity_selector" style="<?php echo ( true != $hide_quantity_switcher ) ? 'display:flex' : 'display:none;pointer-events:none;'; ?>">

                                <div class="wfacp_quantity q_h">
                                    <div class="wfacp_qty_wrap">
                                        <div class="value-button wfacp_decrease_item" data-item-key='<?php echo $item_key; ?>' onclick="decreaseItmQty(this,'')" value="Decrease Value">-</div>
                                        <input type="number" step="<?php echo $minMax['step']; ?>" min="<?php echo $minMax['min']; ?>" max="<?php echo $minMax['max']; ?>" value="<?php echo $rqty; ?>" data-value="<?php echo $rqty; ?>" name="wfacp_product_switcher_quantity_<?php echo $item_key; ?>" class="<?php echo $quantity_input_cls ?>" onfocusout="this.value = (Math.abs(this.value)<0?0:Math.abs(this.value))" <?php echo $disableQty; ?>>
                                        <div class="value-button wfacp_increase_item" data-item-key='<?php echo $item_key; ?>' onclick="increaseItmQty(this,'')" value="Increase Value">+</div>
                                    </div>
                                </div>

                            </div>
							<?php
						} elseif ( $is_sold_individually ) {
							?>
                            <span class="wfacp_sold_individually">1</span>
							<?php
						}
						do_action( 'wfacp_product_below_the_quantity_incrementer', $product_data, $pro, $cart_item_key );
						?>
                    </div>
                    <div class="wfacp_product_price_container product-price">
                        <div class="wfacp_product_price_sec">
							<?php


							if ( apply_filters( 'wfacp_show_product_price', true, $pro, $cart_item_key, $price_data ) ) {
								$price_html = '';

								if ( in_array( $pro->get_type(), WFACP_Common::get_subscription_product_type() ) ) {

									if ( '' !== $cart_item_key ) {
										$price_html = wc_price( $price_data['price'] );
									} else {
										$price_html = wc_price( WFACP_Common::get_subscription_price( $pro, $price_data ) );
									}
								} else {

									if ( $price_data['regular_org'] == 0 ) {
										echo $pro->get_price_html();
									} else {

                                        /* $price_data['price'] > 0 Condition removed because when 100% Discount
                                         * added on products the price strike was not displaying

                                         */

										if ( $price_data['regular_org'] > 0 && ( round( $price_data['price'], 2 ) !== round( $price_data['regular_org'], 2 ) ) ) {
											if ( $price_data['price'] > $price_data['regular_org'] ) {
												$price_html = wc_price( $price_data['price'] );


											} else {

												$price_html = wc_format_sale_price( $price_data['regular_org'], $price_data['price'] );
											}
										} else {

											$price_html = wc_price( $price_data['price'] );
										}
									}
								}
								echo apply_filters( 'wfacp_product_switcher_price_text', $price_html, $pro, $price_data, $product_data );
							} else {
								do_action( 'wfacp_show_product_price_placeholder', $pro, $cart_item_key, $price_data );
							}
							?>


                        </div>


                    </div>
					<?php
					if ( 'hidden' == $type && true === $enable_delete_item && true === wc_string_to_bool( $product_data['enable_delete'] ) && isset( $cart_item_key ) && '' != $cart_item_key ) {
						$item_class = 'wfacp_remove_item_from_cart';
						$item_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
  <path d="M16.3394 9.32245C16.7434 8.94589 16.7657 8.31312 16.3891 7.90911C16.0126 7.50509 15.3798 7.48283 14.9758 7.85938L12.0497 10.5866L9.32245 7.66048C8.94589 7.25647 8.31312 7.23421 7.90911 7.61076C7.50509 7.98731 7.48283 8.62008 7.85938 9.0241L10.5866 11.9502L7.66048 14.6775C7.25647 15.054 7.23421 15.6868 7.61076 16.0908C7.98731 16.4948 8.62008 16.5171 9.0241 16.1405L11.9502 13.4133L14.6775 16.3394C15.054 16.7434 15.6868 16.7657 16.0908 16.3891C16.4948 16.0126 16.5171 15.3798 16.1405 14.9758L13.4133 12.0497L16.3394 9.32245Z" fill="currentColor"></path>
  <path fill-rule="evenodd" clip-rule="evenodd" d="M1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12ZM12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21Z" fill="currentColor"></path>
</svg>';
						?>
                        <div class="wfacp_crossicon_for_mb">
                            <div class="wfacp_product_switcher_remove_product wfacp_delete_item">
                                <a href="javascript:void(0)" class="<?php echo $item_class; ?>" data-cart_key="<?php echo $cart_item_key; ?>" data-item_key="<?php echo $item_key; ?>"><?php echo $item_icon; ?></a>
                            </div>
                        </div>
						<?php
					}
					?>


                </div>
            </div>

        </div>


    </fieldset>
<?php
remove_filter( 'wp_get_attachment_image_attributes', 'WFACP_Common::remove_src_set' );
?>