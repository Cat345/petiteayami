<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


$temp_dynamic_css         = [];
$temp_dynamic_fields_data = [];


$bump_selector_wrapper = 'body #wfob_wrap .wfob_wrapper[data-wfob-id="' . $bump_id . '"] .wfob_bump.wfob_' . $selected_layout;


$bump_selectors = $this->get_bump_design_selectors();

$tmp = [];

$field_changes       = [];
$field_not_avialable = [];

$new_key_value_updated = [ 'new_key_value_updated' => [] ];


if ( ! isset( $design_data['price_sale_font_size'] ) && isset( $design_data['price_font_size'] ) ) {
	$design_data['price_sale_font_size'] = $design_data['price_font_size'];

	$new_key_value_updated['new_key_value_updated']['price_sale_font_size'] = $design_data['price_font_size'];
}
if ( ! isset( $design_data['price_sale_color'] ) && isset( $design_data['price_color'] ) ) {
	$design_data['price_sale_color']                                    = $design_data['price_color'];
	$new_key_value_updated['new_key_value_updated']['price_sale_color'] = $design_data['price_color'];
}


if ( isset( $design_data['enable_featured_image_border'] ) && false == wc_string_to_bool( $design_data['enable_featured_image_border'] ) ) {
	$design_data['featured_image_border_width'] = '0 0 0 0';
}
if ( isset( $design_data['enable_box_border'] ) && false == wc_string_to_bool( $design_data['enable_box_border'] ) ) {
	$design_data['border_width'] = '0 0 0 0';
}


foreach ( $this->get_default_models() as $key => $value ) {
	$selector_value = '';


	if ( ! isset( $bump_selectors[ $key ]['selectors'] ) || ! isset( $bump_selectors[ $key ]['value'] ) ) {


		continue;
	}


	if ( ! isset( $design_data[ $key ] ) ) {
		$field_value = $value;
	} else {
		$field_value = $design_data[ $key ];

	}


	$px = 'px';

	$tmp[ $key ] = $field_value;
	if ( strpos( $key, '_padding' ) !== false || strpos( $key, '_margin' ) !== false || strpos( $key, 'border_width' ) !== false ) {
		$explode_data = explode( ' ', trim( $field_value ) );


		if ( is_array( $explode_data ) && count( $explode_data ) == 1 ) {
			$field_value = $field_value . " " . $field_value . " " . $field_value . " " . $field_value;
		}
		$field_changes[ $key ] = $field_value;
		$tmp[ $key ]           = $field_value;
		$field_value           = str_replace( ' ', $px . ' ', $field_value );

	}


	if ( ! empty( $field_value ) ) {
		$selector_value = str_replace( '{{value}}', $field_value, $bump_selectors[ $key ]['value'] );
	}

	foreach ( $bump_selectors[ $key ]['selectors'] as $selector_key => $selector ) {
		if ( ! empty( $field_value ) ) {
			$dynamic_css['desktop'][] = $selector . '{' . $selector_value . ';}';
		} elseif ( isset( $bump_selectors[ $key ]['ref_key'] ) && false == $field_value && isset( $bump_selectors[ $key ]['ref_key']['value'] ) ) {
			$selector_value           = $bump_selectors[ $key ]['ref_key']['value'];
			$dynamic_css['desktop'][] = $selector . '{' . $selector_value . ' !important;}';
		}
		$temp_dynamic_css[ $key ][] = $selector;
	}
}


/**
 * Check Exclusive Css
 */

if ( in_array( $selected_layout, [ 'layout_7', 'layout_11' ] ) && isset( $design_data['box_padding'] ) ) {

	$box_padding_array = explode( ' ', $design_data['box_padding'] );

	if ( isset( $box_padding_array[0] ) ) {
		$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section.wfob_exclusive_outside_top_left #wfob_wrapper_' . $bump_id . ' .wfob_exclusive_content{margin-top:-' . ( (int) $box_padding_array[0] + 13 ) . 'px;padding-bottom:' . $box_padding_array[0] . 'px}';
		$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section.wfob_exclusive_outside_top_right #wfob_wrapper_' . $bump_id . ' .wfob_exclusive_content{margin-top:-' . ( (int) $box_padding_array[0] + 13 ) . 'px;padding-bottom:' . $box_padding_array[0] . 'px}';
	}

}

$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present{display: inline-block;}';

$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present .wfob_btn_text_added{display: inline-block;}';

$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present .wfob_btn_text_remove{display: none;}';

if ( $selected_layout == 'layout_3' || $selected_layout == 'layout_4' ) {
	$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present:hover > .wfob_btn_text_added{display: none;}';
	$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present:hover > .wfob_btn_text_remove{display: inline-block;}';
}


if ( isset( $design_data['added_button_bg_color'] ) ) {

	if ( $selected_layout = 'layout_3' || $selected_layout == 'layout_4' ) {
		$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present{ border-color: ' . $design_data['added_button_bg_color'] . ' }';
	} else {
		$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present{ border-color: #000000 }';
	}

}

if ( isset( $design_data['social_proof_tooltip_bg_color'] ) && ! empty( $design_data['social_proof_tooltip_bg_color'] ) ) {
	$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' .wfob-social-proof-tooltip:after{ border-top-color: ' . $design_data['social_proof_tooltip_bg_color'] . '}';

}

if ( isset( $design_data['remove_button_bg_color'] ) ) {
	$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . ' .wfob_l3_s_btn a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present:hover{ border-color: ' . $design_data['remove_button_bg_color'] . '}';
	$dynamic_css['desktop'][] = $bump_selector_wrapper . '.wfob_bump_section #wfob_wrapper_' . $bump_id . '.wfacp_bump_clicked .wfob_l3_s_btn a.wfob_l3_f_btn.wfob_btn_remove.wfob_item_present { border-color: ' . $design_data['remove_button_bg_color'] . '}';
}


if ( isset( $design_data['add_button_width'] ) && ! empty( $design_data['add_button_width'] ) && $selected_layout !== 'layout_9' ) {

	if ( $selected_layout == 'layout_10' ) {
		$btn_width = (float) $design_data['add_button_width'] + 30;
	} else {
		$btn_width = (float) $design_data['add_button_width'] + 16;
	}


	//$dynamic_css['min-media']['768'][] = $bump_selector_wrapper . '.wfob_bump_section:not(.wfob_layout_7) #wfob_wrapper_' . $bump_id . ' .bwf_display_col_flex > .wfob_pro_txt_wrap{flex: 0 0 calc(100% - ' . $btn_width . 'px);-webkit-flex: 0 0 calc(100% - ' . $btn_width . 'px);}';
//	$dynamic_css['min-media']['768'][] = $bump_selector_wrapper . '.wfob_bump_section:not(.wfob_layout_7) #wfob_wrapper_' . $bump_id . ' .bwf_display_col_flex > .wfob_add_to_cart_button{width:' . $design_data['add_button_width'] . 'px; }';

}


$merged_array                          = $tmp + $this->design_data;
$dynamic_css_array['dynamic_css']      = $dynamic_css;
$dynamic_css_array['temp_dynamic_css'] = $temp_dynamic_css;
$dynamic_css_array['field_changes']    = $field_changes;
$dynamic_css_array['merged_array']     = $merged_array;

if ( isset( $new_key_value_updated['new_key_value_updated'] ) ) {
	$dynamic_css_array['new_key_value_updated'] = $new_key_value_updated['new_key_value_updated'];
}

return $dynamic_css_array;
