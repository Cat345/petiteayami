<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$wfacp_id = WFACP_Common::get_id();
if ( apply_filters( 'wfacp_hide_product_switcher', false, $wfacp_id ) ) {
	return;
}

WFACP_Core()->public->get_page_data( $wfacp_id );
$hide_product_image     = '';
$switcher_settings      = WFACP_Common::get_product_switcher_data( $wfacp_id );
$page_settings          = WFACP_Common::get_page_product_settings( $wfacp_id );
$products               = WC()->session->get( 'wfacp_product_objects_' . $wfacp_id );
$products_data          = WC()->session->get( 'wfacp_product_data_' . $wfacp_id );
$field                  = WC()->session->get( 'wfacp_product_switcher_field_' . $wfacp_id );
$hide_whats_included    = wc_string_to_bool( $switcher_settings['settings']['is_hide_additional_information'] );
$hide_quantity_switcher = wc_string_to_bool( $switcher_settings['settings']['hide_quantity_switcher'] );
$sec_heading            = $switcher_settings['settings']['additional_information_title'];
$add_to_cart_setting    = $page_settings['add_to_cart_setting'];
$type                   = $add_to_cart_setting == 2 ? 'radio' : 'checkbox';

$ps_cls_settings = [
	'ps_productSelection'    => 'wfacp_not_force_all',
	'ps_other_image_setting' => 'wfacp_setting_not_image_hide',
	'ps_other_qty_setting'   => 'wfacp_setting_not_qty_hide',
	'ps_delete_item'         => 'wfacp_enable_delete_item',
];


$ps_productSelection    = 'wfacp_not_force_all';
$ps_other_image_setting = 'wfacp_setting_not_image_hide';
$ps_other_qty_setting   = 'wfacp_setting_not_qty_hide';
if ( 1 == $add_to_cart_setting ) {
	$type                                   = 'hidden';
	$ps_productSelection                    = 'wfacp_force_all';
	$ps_cls_settings['ps_productSelection'] = $ps_productSelection;
}

if ( isset( $switcher_settings['settings']['hide_product_image'] ) ) {
	$hide_product_image = wc_string_to_bool( $switcher_settings['settings']['hide_product_image'] );

	if ( $hide_product_image == true ) {
		$ps_other_image_setting                    = 'wfacp_setting_image_hide';
		$ps_cls_settings['ps_other_image_setting'] = $ps_other_image_setting;
	}
}


$enableDeleteItem   = wc_string_to_bool( $switcher_settings['settings']['enable_delete_item'] );
$enable_delete_item = '';

if ( isset( $enableDeleteItem ) && false === $enableDeleteItem ) {
	$enable_delete_item                = 'wfacp_disable_delete_item';
	$ps_cls_settings['ps_delete_item'] = $enable_delete_item;

}

if ( true === $hide_quantity_switcher ) {
	$ps_other_qty_setting                    = 'wfacp_setting_qty_hide';
	$ps_cls_settings['ps_other_qty_setting'] = $ps_other_qty_setting;
}


if ( ! isset( $hide_section ) || '' == $hide_section ) {
	$hide_section = '';
}

$detectDevice             = WFACP_Mobile_Detect::get_instance();
$instance_temp            = wfacp_template();
$template_type_temp       = $instance_temp->get_template_type();
$deviceType               = '';
$is_sold_individually_arr = [];
$deviceType               = 'wfacp_for_desktop_tablet desk_only ';
$mb_style                 = '';
$mb_style                 = apply_filters( 'wfacp_for_mb_style', $mb_style );


if ( ( $detectDevice->isMobile() && ! $detectDevice->istablet() ) || $mb_style === 'wfacp_for_mb_style' ) {
	$deviceType = 'wfacp_for_desktop_tablet wfacp_for_mb_style ';

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
	} elseif ( $step_form_max_width >= 375 && $step_form_max_width <= 600 ) {
		$deviceType = 'wfacp_for_desktop_tablet wfacp_for_mb_style wfacp_ps_mb_active ';
	}
}


$ps_setting_wrapper_class = '';
if ( is_array( $ps_cls_settings ) && count( $ps_cls_settings ) > 0 ) {
	$ps_setting_wrapper_class = implode( ' ', $ps_cls_settings );
}

if ( is_array( $products ) && count( $products ) > 0 ) {
	$classes = isset( $field['cssready'] ) ? implode( ' ', $field['cssready'] ) : '';

	?>
    <div class="wfacp_anim wfacp_pro_switch <?php echo $deviceType; ?>shop_table wfacp-product-switch-panel <?php echo $classes . ' ' . $ps_setting_wrapper_class . ' wfacp_type_' . $type ?>" cellspacing="0" id="product_switching_field" <?php echo WFACP_Common::get_fragments_attr() ?> >
		<?php
		do_action( 'wfacp_before_product_switcher_html' );

		$status = WFACP_Common::enable_cart_deletion();
		if ( true === $status && WC()->cart->is_empty() ) {
			WFACP_Common::show_cart_empty_message();
		}

		$product_switcher_label = __( 'Products', 'woocommerce' );
		if ( isset( $field['label'] ) && ! is_null( $field['label'] ) ) {
			$product_switcher_label = $field['label'];
		}

		if ( false == ( true === $status && 'hidden' == $type && WC()->cart->is_empty() ) ) {


			?>
            <div class="wfacp-product-switch-title">

                <div class="product-remove"><?php echo $product_switcher_label; ?> </div>
                <div class="wfacp_qty_price_wrap">
					<?php if ( ! $hide_quantity_switcher ) { ?>
                        <div class="product-quantity"><?php _e( 'Qty', 'woocommerce' ); ?></div>
					<?php } ?>
                    <div class="product-name"><?php _e( 'Price', 'woocommerce' ); ?></div>
                </div>
            </div>
		<?php } ?>
        <div class="wfacp_product_switcher_container">
			<?php
			global $wfacp_products_attributes_data;
			$counter                           = 0;
			$best_value_counter                = 1;
			$product_switcher_description_html = '';
			$cart_count                        = count( WC()->cart->get_cart_contents() );
			foreach ( $products as $item_key => $product_obj ) {
				$product_data = $products_data[ $item_key ];

				if ( isset( $switcher_settings['products'][ $item_key ]['whats_included'] ) ) {
					$product_data['whats_included'] = $switcher_settings['products'][ $item_key ]['whats_included'];
				}
				$product_data['item_key'] = $item_key;
				$is_checked               = '';
				if ( isset( $product_data['is_added_cart'] ) ) {
					$is_checked = 'checked="checked"';
				}
				$product_data['is_checked'] = $is_checked;
				if ( ! isset( $is_sold_individually_arr['total_products'] ) ) {
					$is_sold_individually_arr['total_products'] = 1;
				} else {
					$is_sold_individually_arr['total_products'] ++;
				}
				if ( $product_obj->is_sold_individually() ) {
					if ( ! isset( $is_sold_individually_arr['is_sold_individual'] ) ) {
						$is_sold_individually_arr['is_sold_individual'] = 1;
					} else {
						$is_sold_individually_arr['is_sold_individual'] ++;
					}
				} else {
					if ( ! isset( $is_sold_individually_arr['not_sold_individual'] ) ) {
						$is_sold_individually_arr['not_sold_individual'] = 1;
					} else {
						$is_sold_individually_arr['not_sold_individual'] ++;
					}
				}
				$product_data['hide_product_image'] = $hide_product_image;
				WFACP_Common::get_product_switcher_row( $product_data, $item_key, $type, $switcher_settings );
				$product_switcher_description_html .= WFACP_Common::get_product_switcher_row_description( $product_data, $product_obj, $switcher_settings, true );
				$best_value_counter ++;
			}
			?>
        </div>
		<?php

		if ( '' != $product_switcher_description_html && false == $hide_whats_included ) {
			?>
            <div class="wfacp_whats_included">
				<?php
				echo $sec_heading ? '<h3>' . $sec_heading . '</h3>' : '';
				echo $product_switcher_description_html;
				?>
            </div>
			<?php
		}

		do_action( 'wfacp_after_product_switcher_html' );
		?>
    </div>
	<?php

} elseif ( is_super_admin() ) {
	$product_url = add_query_arg( [
		'page'     => 'bwf',
		'path'  => '/funnel-checkout/'.$wfacp_id.'/products',
	], admin_url( 'admin.php' ) );


	$no_products_msg = __( 'No products added to this checkout page. <a target="_blank" href="' . $product_url . '">Add Product</a>', 'woofunnels-aero-checkout' );


	if ( empty( $wfacp_no_product ) ) {
		echo '<div class="wfacp_cart_empty wfacp_no_product">' . $no_products_msg . '</div>';
	}
} else {
	echo '<div class="wfacp_anim wfacp_pro_switch wfacp-product-switch-panel"></div>';
}


$total_products = 0;
if ( isset( $is_sold_individually_arr['total_products'] ) ) {
	$total_products = $is_sold_individually_arr['total_products'];
}


if ( ( is_array( $is_sold_individually_arr ) && count( $is_sold_individually_arr ) > 0 ) ) {
	if ( isset( $is_sold_individually_arr['is_sold_individual'] ) && $total_products > 0 ) {
		if ( $total_products == $is_sold_individually_arr['is_sold_individual'] ) {
			?>
            <style>.wfacp-product-switch-title .product-quantity {
                    display: none
                }

                body .wfacp_main_form .wfacp_qty_price_wrap .product-name {
                    width: 100%;
                    padding-left: 0;
                }

                body .wfacp_main_form #product_switching_field.wfacp_for_desktop_tablet .wfacp_product_switcher_col_2 {
                    padding-right: 0;
                }
            </style>
			<?php
		}
	}
}

if ( wp_doing_ajax() ) {
	return;
}
global $wfacp_products_attributes_data;
?>
<script>
    var wfacp_variation_attributes_data =<?php echo wp_json_encode( $wfacp_products_attributes_data ); ?>;
</script>