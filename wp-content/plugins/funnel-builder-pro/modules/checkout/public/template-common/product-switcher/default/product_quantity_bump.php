<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$field    = WC()->session->get( 'wfacp_product_switcher_field_' . WFACP_Common::get_id() );
$instance = wfacp_template();

$switcher_settings                             = WFACP_Common::get_product_switcher_data( WFACP_Common::get_id() );
$sec_heading                                   = trim( $switcher_settings['settings']['additional_information_title'] );
$hide_whats_included                           = wc_string_to_bool( $switcher_settings['settings']['is_hide_additional_information'] );
$hide_quantity_switcher                        = wc_string_to_bool( $switcher_settings['settings']['hide_quantity_switcher'] );
$enable_delete_item                            = wc_string_to_bool( $switcher_settings['settings']['enable_delete_item'] );
$classes                                       = isset( $field['cssready'] ) ? implode( ' ', $field['cssready'] ) : '';
$label_products                                = isset( $field['label'] ) ? $field['label'] : __( 'Products', 'woocommerce' );
$product_switcher_description_html             = [];
$show_additional_information_and_you_save_text = apply_filters( 'show_additional_information_and_you_save_text', false );
$hide_product_image                            = isset( $switcher_settings['settings']['hide_product_image'] ) ? wc_string_to_bool( $switcher_settings['settings']['hide_product_image'] ) : false;


$instance_temp      = wfacp_template();
$template_type_temp = $instance_temp->get_template_type();

$detectDevice  = WFACP_Mobile_Detect::get_instance();
$instance_temp = wfacp_template();


$deviceType = 'wfacp_for_desktop_tablet desk_only ';

$mb_style = '';
$mb_style = apply_filters( 'wfacp_for_mb_style', $mb_style );

if ( ( $detectDevice->isMobile() && ! $detectDevice->istablet() ) || $mb_style === 'wfacp_for_mb_style' ) {
	$deviceType = 'wfacp_for_desktop_tablet wfacp_for_mb_style';
} elseif ( $template_type_temp == 'embed_form' ) {

	$selected_template_slug = $instance_temp->get_template_slug();
	$layout_key             = '';
	$layout_key             = '';
	if ( isset( $selected_template_slug ) && $selected_template_slug != '' ) {
		$layout_key = $selected_template_slug . '_';
	}
	$step_form_max_width = WFACP_Common::get_option( 'wfacp_form_section_' . $layout_key . 'step_form_max_width' );
	if ( $step_form_max_width <= 374 ) {
		$wfacp_hide_img_wrap = apply_filters( 'wfacp_hide_product_image_for_less_width_form', 'wfacp_hideimg_wrap' );
		$deviceType          = 'wfacp_for_desktop_tablet wfacp_for_mb_style ' . $wfacp_hide_img_wrap . ' ';
	} else if ( $step_form_max_width >= 375 && $step_form_max_width <= 600 ) {

		$deviceType = 'wfacp_for_desktop_tablet wfacp_for_mb_style wfacp_ps_mb_active ';
	}
}

$enable_hide_img = 'wfacp_ps_disable_hideImg1';

if ( isset( $switcher_settings['settings']['hide_product_image'] ) && wc_string_to_bool( $switcher_settings['settings']['hide_product_image'] ) ) {
	$enable_hide_img = 'wfacp_ps_enable_hideImg1';

}

$hide_qty_switcher_cls = '';
if ( $hide_quantity_switcher == true ) {
	$hide_qty_switcher_cls = 'wfacp_hide_qty_switcher1';
}


$ps_cls_settings = [
	'ps_productSelection'    => 'wfacp_force_all',
	'ps_other_image_setting' => 'wfacp_setting_not_image_hide',
	'ps_other_qty_setting'   => 'wfacp_setting_not_qty_hide',
	'ps_delete_item'         => 'wfacp_enable_delete_item',
];


$ps_other_image_setting = 'wfacp_setting_not_image_hide';
$ps_other_qty_setting   = 'wfacp_setting_not_qty_hide';


if ( isset( $switcher_settings['settings']['hide_product_image'] ) ) {
	$hide_product_image = wc_string_to_bool( $switcher_settings['settings']['hide_product_image'] );
	if ( $hide_product_image == true ) {
		$ps_other_image_setting                    = 'wfacp_setting_image_hide';
		$ps_cls_settings['ps_other_image_setting'] = $ps_other_image_setting;
	}

}

if ( true === $hide_quantity_switcher ) {
	$ps_other_qty_setting                    = 'wfacp_setting_qty_hide';
	$ps_cls_settings['ps_other_qty_setting'] = $ps_other_qty_setting;
}


$enable_delete_item = '';
$enableDeleteItem   = wc_string_to_bool( $switcher_settings['settings']['enable_delete_item'] );


if ( isset( $enableDeleteItem ) && false === $enableDeleteItem ) {
	$enable_delete_item = 'wfacp_disable_delete_item';

	$ps_cls_settings['ps_delete_item'] = $enable_delete_item;
}

$ps_setting_wrapper_class = '';
if ( is_array( $ps_cls_settings ) && count( $ps_cls_settings ) > 0 ) {
	$ps_setting_wrapper_class = implode( ' ', $ps_cls_settings );
}

$wfacp_cart = WC()->cart->get_cart();
$cart_count = count( $wfacp_cart );

$you_save_text = '';
if ( isset( $switcher_settings['products'] ) && count( $switcher_settings['products'] ) > 0 ) {
	$temp_pro = array_values( $switcher_settings['products'] );
	foreach ( $temp_pro as $tp ) {
		if ( isset( $tp['you_save_text'] ) && '' !== $tp['you_save_text'] && '' == $you_save_text ) {
			$you_save_text = $tp['you_save_text'];
			continue;
		}
	}
}

?>
    <div class="wfacp_anim wfacp_pro_switch <?php echo $deviceType . ' ' . $ps_setting_wrapper_class; ?> shop_table wfacp-product-switch-panel <?php echo $classes ?> wfacp_df_ps" cellspacing="0" id="product_switching_field" <?php echo WFACP_Common::get_fragments_attr() ?> >


        <div class="wfacp_cross_enabled1">
			<?php do_action( 'wfacp_before_product_switcher_html' );

			if ( empty( $wfacp_cart ) ) {
				WFACP_Common::show_cart_empty_message();
			} else {

				?>
                <div class="wfacp-product-switch-title">
                    <div class="product-remove"><?php echo $label_products; ?></div>

                    <div class="wfacp_qty_price_wrap">
						<?php if ( ! $hide_quantity_switcher ) { ?>
                            <div class="product-quantity"><?php _e( 'Qty', 'woocommerce' ); ?></div>
						<?php } ?>
                        <div class="product-name"><?php _e( 'Price', 'woocommerce' ); ?></div>
                    </div>
                </div>
				<?php
				$is_sold_individually = false;
				do_action( 'woocommerce_review_order_before_cart_contents' );
				foreach ( $wfacp_cart as $cart_item_key => $cart_item ) {

					if ( apply_filters( 'wfacp_skip_global_switcher_item', false, $cart_item, $cart_item_key ) ) {
						do_action( 'wfacp_skip_global_switcher_item_placeholder', $cart_item, $cart_item_key );
						continue;
					}
					$enable_you_save = apply_filters( 'wfacp_show_you_save_text', true, $cart_item, $cart_item_key );
					$_product        = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
					$qty             = $cart_item['quantity'];
					if ( $_product && $_product->exists() && $qty > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
						$price_data = [];
						$pro        = $cart_item['data'];
						if ( $pro instanceof WC_Product ) {
							$price_data = WFACP_Common::get_cart_product_price_data( $pro, $cart_item, $qty );
						}
						$product_data = [];
						if ( isset( $cart_item['_wfacp_options'] ) ) {
							$product_data = $cart_item['_wfacp_options'];
						}


						if ( $cart_item['quantity'] >= 1 ) {
							$qtyHtml = sprintf( '<div class="wfacp-qty-ball"><span class="wfacp-qty-count wfacp_product_switcher_quantity"><span class="wfacp-pro-count">%d</span></span></div>', $cart_item['quantity'] );
						}

						$you_save_text_temp = $you_save_text;
						if ( isset( $cart_item['_wfacp_product'] ) ) {
							$temp_data          = $cart_item['_wfacp_options'];
							$you_save_text_temp = isset( $temp_data['you_save_text'] ) ? $temp_data['you_save_text'] : '';
							if ( ! $hide_whats_included || $show_additional_information_and_you_save_text ) {
								if ( isset( $temp_data['old_title'] ) ) {
									$temp_data['title'] = $temp_data['old_title'];
								}
								$temp_data['is_added_cart']          = true;
								$product_switcher_description_html[] = WFACP_Common::get_product_switcher_row_description( $temp_data, $_product, $switcher_settings, true );
							}

						}


						$saveTextHtml = '';
						if ( '' !== $you_save_text_temp && ! empty( $price_data ) ) {
							$subscription_tryl   = 0;
							$subscription_signup = 0;
							if ( in_array( $pro->get_type(), WFACP_Common::get_subscription_product_type() ) ) {
								$subscription_tryl   = WC_Subscriptions_Product::get_trial_length( $pro );
								$subscription_signup = WC_Subscriptions_Product::get_sign_up_fee( $pro );
							}
							$have_saving_value_merge_tag      = strpos( $you_save_text_temp, '{{saving_value}}' );
							$have_saving_percentage_merge_tag = strpos( $you_save_text_temp, '{{saving_percentage}}' );

							if ( ( false !== $have_saving_value_merge_tag || false !== $have_saving_percentage_merge_tag ) ) {

								if ( $subscription_tryl > 0 || $subscription_signup > 0 ) {
									//available  for future updates
								} else {
									$saveTextHtml = WFACP_Common::product_switcher_merge_tags( $you_save_text_temp, $price_data, $pro, $product_data, $cart_item, $cart_item_key );

									if ( $saveTextHtml != '' ) {
										$saveTextHtml = sprintf( '<div class="wfacp_you_save_text">%s</div>', $saveTextHtml );
									}
								}
							} else {

								// do not have merge tag Or Static you save text
								$saveTextHtml = WFACP_Common::product_switcher_merge_tags( $you_save_text_temp, $price_data, $pro, $product_data, $cart_item, $cart_item_key );

								if ( $saveTextHtml != '' ) {
									$saveTextHtml = sprintf( '<div class="wfacp_you_save_text">%s</div>', $saveTextHtml );
								}
							}
						}
						$subscription_product_string = '';
						if ( in_array( $pro->get_type(), WFACP_Common::get_subscription_product_type() ) ) {

							$subscription_product_string = sprintf( "<div class='wfacp_product_subs_details'>%s</div>", WFACP_Common::subscription_product_string( $pro, $product_data, $cart_item, $cart_item_key ) );
						}
						$wfacp_you_save_text_html = 'wfacp_you_save_text_blank';
						if ( $saveTextHtml != '' ) {
							$wfacp_you_save_text_html = '';
						}
						add_filter( 'wp_get_attachment_image_attributes', 'WFACP_Common::remove_src_set' );
						$row_item_class = apply_filters( 'woocommerce_cart_item_class', 'woocommerce-cart-form__cart-item cart_item wfacp_product_row wfacp-selected-product', $cart_item, $cart_item_key );
						?>
                        <div class="<?php echo $row_item_class ?>" cart_key="<?php echo $cart_item_key; ?>">
                            <div class="wfacp_row_wrap <?php echo $enable_hide_img . " " . $hide_qty_switcher_cls . ' ' . $wfacp_you_save_text_html; ?>">
                                <div class="wfacp_ps_title_wrap">
                                    <div class="wfacp_product_switcher_col wfacp_product_switcher_col_1">
										<?php
										$yes_enableDeleteItem = apply_filters( 'wfacp_enable_delete_item', $enableDeleteItem, $cart_item, $cart_item_key );
										if ( true === $yes_enableDeleteItem ) {
											$item_class = 'wfacp_remove_item_from_cart';


											$item_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
  <path d="M16.3394 9.32245C16.7434 8.94589 16.7657 8.31312 16.3891 7.90911C16.0126 7.50509 15.3798 7.48283 14.9758 7.85938L12.0497 10.5866L9.32245 7.66048C8.94589 7.25647 8.31312 7.23421 7.90911 7.61076C7.50509 7.98731 7.48283 8.62008 7.85938 9.0241L10.5866 11.9502L7.66048 14.6775C7.25647 15.054 7.23421 15.6868 7.61076 16.0908C7.98731 16.4948 8.62008 16.5171 9.0241 16.1405L11.9502 13.4133L14.6775 16.3394C15.054 16.7434 15.6868 16.7657 16.0908 16.3891C16.4948 16.0126 16.5171 15.3798 16.1405 14.9758L13.4133 12.0497L16.3394 9.32245Z" fill="currentColor"></path>
  <path fill-rule="evenodd" clip-rule="evenodd" d="M1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12ZM12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21Z" fill="currentColor"></path>
</svg>';
											?>
                                            <div class="wfacp_product_switcher_remove_product wfacp_delete_item">
                                                <a href="javascript:void(0)" class="<?php echo $item_class; ?>" data-cart_key="<?php echo $cart_item_key; ?>"><?php echo $item_icon; ?></a>
                                            </div>
											<?php
										}
										if ( false == $hide_product_image ) {

											$default_size = apply_filters( 'wfacp_product_image_size', [ 100, 100 ] );
											$thumbnail    = $pro->get_image( $default_size, [ 'srcset' => false ] );

											echo sprintf( '<div class="product-image"><div class="wfacp-pro-thumb">%s</div>%s</div>', $thumbnail, $qtyHtml );
										}
										?>
                                    </div>
                                    <div class="wfacp_product_switcher_col wfacp_product_switcher_col_2">
                                        <div class='wfacp_product_switcher_description'>
                                            <div class="product-name product_name">
                                                <div class="wfacp_product_sec">
                                                    <div class="wfacp_product_name_inner">
													<?php echo "<span class='wfacp_product_switcher_item'>" . apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . "</span>"; ?>
													<?php
													if ( apply_filters( 'wfacp_allow_woocommerce_after_cart_item_name_product_switcher_global', false, $cart_item, $cart_item_key ) ) {
														/**
														 * added in 2.0.0
														 */
														do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
													}
													echo wc_get_formatted_cart_item_data( $cart_item );
													?>
                                                    </div>
                                                </div>
                                            </div>
											<?php
											if ( '' !== $saveTextHtml || '' != $subscription_product_string ) {
												?>
                                                <div class="wfacp_ps_div_row">
													<?php
													if ( true == $enable_you_save ) {
														echo $saveTextHtml;
													}
													echo $subscription_product_string;
													?>
                                                </div>
												<?php
											} ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="wfacp_product_sec_start">
									<?php
									$clssaleIndi = '';
									if ( $_product->is_sold_individually() ) {
										$clssaleIndi = 'wfacp_sold_indi';
									}

									?>

                                    <div class="wfacp_product_switcher_col wfacp_product_switcher_col_3 <?php echo $clssaleIndi; ?>">
                                        <div class="wfacp_product_quantity_container">
											<?php

											$cart_item_quantity = apply_filters( 'wfacp_item_quantity', $cart_item['quantity'], $cart_item );
											if ( apply_filters( 'wfacp_show_item_quantity', true, $cart_item ) ) {
												if ( $_product->is_sold_individually() ) {
													$is_sold_individually = true;
												}
												if ( ! $_product->is_sold_individually() && ! $hide_quantity_switcher ) {

													$minMax = apply_filters( 'wfacp_cart_item_min_max_quantity', [ 'min' => 0, 'max' => '', 'step' => '1' ], $cart_item, $cart_item_key );
													?>
                                                    <div class="wfacp_quantity_selector">
                                                        <div class="wfacp_quantity q_h">
                                                            <div class="wfacp_qty_wrap">
                                                                <div class="value-button wfacp_decrease_item" onclick="decreaseItmQty(this,'')" value="Decrease Value">-</div>
                                                                <input type="number" step="<?php echo $minMax['step'] ?>" min="<?php echo $minMax['min'] ?>" max="<?php echo $minMax['max'] ?>" value="<?php echo $cart_item_quantity; ?>" data-value="<?php echo $cart_item['quantity']; ?>" onfocusout="this.value = (Math.abs(this.value)==0?0:Math.abs(this.value))" class="wfacp_product_switcher_quantity wfacp_product_global_quantity_bump wfacp_product_quantity_number_field">
                                                                <div class="value-button wfacp_increase_item" onclick="increaseItmQty(this,'')" value="Increase Value">+</div>
                                                            </div>
                                                        </div>

                                                    </div>
													<?php
												} elseif ( $is_sold_individually ) {
													?>
                                                    <span>1</span>
													<?php
												}
											} else {
												do_action( 'wfacp_show_item_quantity_placeholder', $cart_item, $cart_item_key );
											}
											?>

                                        </div>
                                        <div class="wfacp_product_price_container product-price">
                                            <div class="wfacp_product_price_sec">
												<?php

												if ( apply_filters( 'wfacp_show_item_price', true, $cart_item ) ) {
													if ( in_array( $pro->get_type(), WFACP_Common::get_subscription_product_type() ) ) {
														$price_show = apply_filters( 'wfacp_subscription_price_display', wc_price( $price_data['price'] ), $_product, $cart_item, $cart_item_key );
														echo $price_show;

													} else {
														/* $price_data['price'] > 0 Condition removed because when 100% Discount
										                    added on products the price strike was not displaying
										               */
														if ( absint( $price_data['price'] ) !== absint( $price_data['regular_org'] ) ) {
															if ( $price_data['price'] > $price_data['regular_org'] ) {
																echo wc_price( $price_data['price'] );
															} else {
																echo wc_format_sale_price( $price_data['regular_org'], $price_data['price'] );
															}
														} else {
															echo wc_price( $price_data['price'] );
														}
													}
												} else {
													do_action( 'wfacp_show_item_price_placeholder', $_product, $cart_item, $cart_item_key );
												}
												?>
                                            </div>
                                        </div>
										<?php

										if ( true === $yes_enableDeleteItem && $cart_count > 0 ) {
											$item_class = 'wfacp_remove_item_from_cart';
											$item_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
  <path d="M16.3394 9.32245C16.7434 8.94589 16.7657 8.31312 16.3891 7.90911C16.0126 7.50509 15.3798 7.48283 14.9758 7.85938L12.0497 10.5866L9.32245 7.66048C8.94589 7.25647 8.31312 7.23421 7.90911 7.61076C7.50509 7.98731 7.48283 8.62008 7.85938 9.0241L10.5866 11.9502L7.66048 14.6775C7.25647 15.054 7.23421 15.6868 7.61076 16.0908C7.98731 16.4948 8.62008 16.5171 9.0241 16.1405L11.9502 13.4133L14.6775 16.3394C15.054 16.7434 15.6868 16.7657 16.0908 16.3891C16.4948 16.0126 16.5171 15.3798 16.1405 14.9758L13.4133 12.0497L16.3394 9.32245Z" fill="currentColor"></path>
  <path fill-rule="evenodd" clip-rule="evenodd" d="M1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12ZM12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12C21 16.9706 16.9706 21 12 21Z" fill="currentColor"></path>
</svg>';
											?>
                                            <div class="wfacp_crossicon_for_mb">
                                                <div class="wfacp_product_switcher_remove_product wfacp_delete_item">
                                                    <a href="javascript:void(0)" class="<?php echo $item_class; ?>" data-cart_key="<?php echo $cart_item_key; ?>"><?php echo $item_icon; ?></a>
                                                </div>
                                            </div>
											<?php
										}

										?>


                                    </div>

                                </div>

                            </div>
                        </div>
						<?php
					}
				}
				do_action( 'wfacp_after_product_switcher_html' );
				if ( ! empty( $product_switcher_description_html ) && count( $product_switcher_description_html ) == count( WC()->cart->get_cart() ) && ! $hide_whats_included ) {

					$product_switcher_description_html = implode( "\n", $product_switcher_description_html );
					if ( '' !== $product_switcher_description_html ) {

						?>
                        <div class="wfacp_whats_included ">
							<?php
							echo $sec_heading ? '<h3>' . $sec_heading . '</h3>' : '';
							echo $product_switcher_description_html;
							?>
                        </div>
						<?php
					}
				}
				if ( true == $is_sold_individually && count( WC()->cart->get_cart() ) == 1 ) {
					?>
                    <style>
                        .wfacp-product-switch-title .product-quantity {
                            display: none
                        }
                    </style>
					<?php
				}
			}
			?>
        </div>

    </div>
<?php
remove_filter( 'wp_get_attachment_image_attributes', 'WFACP_Common::remove_src_set' );
?>