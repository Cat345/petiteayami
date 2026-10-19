<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
$product_field   = WFACP_Common::get_product_field();
$advanced_field  = WFACP_Common::get_advanced_fields();
$customizer_data = array(
	'wfacp_form' => array(
		'wfacp_form_section_embed_forms_2_disable_steps_bar' => true,
		'wfacp_form_section_embed_forms_2_select_type'    => 'tab',
		'wfacp_form_section_text_below_placeorder_btn'    => __( '* 100% Secure &amp; Safe Payments *', 'funnel-builder' ),
		'wfacp_form_section_embed_forms_2_step_alignment' => 'center',
		'wfacp_form_section_embed_forms_2_step_form_max_width' => '664',
		'wfacp_form_form_fields_1_embed_forms_2_billing_email' => 'wfacp-col-full',
		'wfacp_form_form_fields_1_embed_forms_2_billing_phone' => 'wfacp-col-full',
		'wfacp_form_form_fields_1_embed_forms_2_billing_first_name' => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_last_name' => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_address_1' => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_city' => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_postcode' => 'wfacp-col-left-third',
		'wfacp_form_form_fields_1_embed_forms_2_billing_country' => 'wfacp-col-left-third',
		'wfacp_form_form_fields_1_embed_forms_2_billing_state' => 'wfacp-col-left-third',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_address_1' => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_city' => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_postcode' => 'wfacp-col-left-third',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_country' => 'wfacp-col-left-third',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_state' => 'wfacp-col-left-third',
		'wfacp_form_section_embed_forms_2_sec_heading_color' => '#333',
		'wfacp_form_product_switcher_section_embed_forms_2_product_switcher_bg_color' => '#f7f7f7',
		'wfacp_form_section_embed_forms_2_heading_fs'     => 20,
		'wfacp_form_section_embed_forms_2_btn_order-place_bg_color' => '#24ae4e',
		'wfacp_form_section_embed_forms_2_btn_order-place_text_color' => '#ffffff',
		'wfacp_form_section_embed_forms_2_btn_order-place_bg_hover_color' => '#7aa631',
		'wfacp_form_section_embed_forms_2_btn_order-place_border_radius' => '4',


		'wfacp_form_section_embed_forms_2_form_border_width' => '1',
		'wfacp_form_section_embed_forms_2_form_border_type' => 'none',
		'wfacp_form_section_embed_forms_2_form_border_color' => '#bbbbbb',
		'wfacp_form_section_embed_forms_2_form_inner_padding' => '16',
		'wfacp_form_section_embed_forms_2_name_0'         => __( 'GET YOUR FREE COPY OF AMAZING BOOK', 'funnel-builder' ),
		'wfacp_form_section_embed_forms_2_headline_0'     => __( 'Shipped in less than 3 days!', 'funnel-builder' ),

		'wfacp_form_section_embed_forms_2_heading_talign' => 'wfacp-text-left',
		'wfacp_form_section_embed_forms_2_sec_bg_color'   => 'transparent',
		'wfacp_form_section_embed_forms_2_rbox_border_type' => 'none',
		'wfacp_form_section_embed_forms_2_rbox_border_width' => '1',
		'wfacp_form_section_embed_forms_2_rbox_padding'   => '0',
		'wfacp_form_section_embed_forms_2_rbox_margin'    => '10',
		'wfacp_form_section_embed_forms_2_sub_heading_fs' => 13,
		'wfacp_form_section_embed_forms_2_sub_heading_talign' => 'wfacp-text-left',
		'wfacp_form_section_embed_forms_2_sec_sub_heading_color' => '#000',
		'wfacp_form_section_embed_forms_2_field_style_fs' => 13,

		'wfacp_form_section_ct_active_inactive_tab'       => 'active',
		'wfacp_form_section_embed_forms_2_field_border_width' => '1',
		'wfacp_form_section_embed_forms_2_field_border_layout' => 'solid',
		'wfacp_form_section_embed_forms_2_field_style_color' => '#888888',
		'wfacp_form_section_embed_forms_2_field_focus_color' => '#61bdf7',
		'wfacp_form_section_embed_forms_2_field_input_color' => '#404040',
		'wfacp_form_section_payment_methods_sub_heading'  => '',
		'wfacp_form_section_embed_forms_2_btn_order-place_fs' => 25,
		'wfacp_form_section_embed_forms_2_btn_order-place_top_bottom_padding' => '14',
		'wfacp_form_section_embed_forms_2_btn_order-place_left_right_padding' => '22',
		'wfacp_form_section_embed_forms_2_btn_order-place_width' => '100%',
		'wfacp_form_section_embed_forms_2_btn_order-place_make_button_sticky_on_mobile' => 'no_sticky',
		'wfacp_form_section_embed_forms_2_additional_text_color' => '#000000',
		'wfacp_form_section_embed_forms_2_additional_bg_color' => '#f8f8f8',
		'wfacp_form_section_embed_forms_2_validation_color' => '#ff0000',
		'wfacp_form_section_embed_forms_2_btn_order-place_text_hover_color' => '#ffffff',
		'wfacp_form_section_embed_forms_2_form_content_color' => '#737373',
		'wfacp_form_section_embed_forms_2_form_content_link_color' => '#dd7575',
		'wfacp_form_section_embed_forms_2_section_bg_color' => '#ffffff',
		'wfacp_form_section_embed_forms_2_form_content_link_color_type' => 'normal',
		'wfacp_form_section_embed_forms_2_form_content_link_hover_color' => '#965d5d',
		'wfacp_style_typography_embed_forms_2_content_ff' => 'ABeeZee',
		'wfacp_form_section_embed_forms_2_field_border_color' => '#bfbfbf',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_same_as_billing' => 'wfacp-col-full',
		'wfacp_form_form_fields_1_embed_forms_2_billing_same_as_shipping' => 'wfacp-col-full',
	),
);


$steps = array(
	'single_step' => array(
		'name'          => __( 'Step 1', 'funnel-builder' ),
		'slug'          => 'single_step',
		'friendly_name' => __( 'Single Step Checkout', 'funnel-builder' ),
		'active'        => 'yes',
	),
	'two_step'    => array(
		'name'          => __( 'Step 2', 'funnel-builder' ),
		'slug'          => 'two_step',
		'friendly_name' => __( 'Two Step Checkout', 'funnel-builder' ),
		'active'        => 'no',
	),
	'third_step'  => array(
		'name'          => __( 'Step 3', 'funnel-builder' ),
		'slug'          => 'third_step',
		'friendly_name' => __( 'Three Step Checkout', 'funnel-builder' ),
		'active'        => 'no',
	),
);


$pageLayout = array(
	'steps'                       => WFACP_Common::get_default_steps_fields(),
	'fieldsets'                   => array(
		'single_step' => array(
			0 => array(
				'name'        => WFACP_Common::translation_string_to_check( __( 'Customer Information', 'funnel-builder' ) ),
				'class'       => '',
				'sub_heading' => '',
				'fields'      => array(
					array(
						'label'        => __( 'Email', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
						'required'     => 'true',
						'type'         => 'email',
						'class'        => array( 0 => 'form-row-wide' ),
						'validate'     => array( 0 => 'email' ),
						'autocomplete' => 'email username',
						'priority'     => '110',
						'id'           => 'billing_email',
						'field_type'   => 'billing',
						'placeholder'  => '',
					),
					array(
						'label'        => __( 'First name', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
						'required'     => 'true',
						'class'        => array( 0 => 'form-row-first' ),
						'autocomplete' => 'given-name',
						'priority'     => '10',
						'type'         => 'text',
						'id'           => 'billing_first_name',
						'field_type'   => 'billing',
						'placeholder'  => '',
					),
					array(
						'label'        => __( 'Last name', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
						'required'     => 'true',
						'class'        => array( 0 => 'form-row-last' ),
						'autocomplete' => 'family-name',
						'priority'     => '20',
						'type'         => 'text',
						'id'           => 'billing_last_name',
						'field_type'   => 'billing',
						'placeholder'  => '',
					),
					WFACP_Common::get_single_address_fields(),
					WFACP_Common::get_single_address_fields( 'shipping' ),
					array(
						'label'        => __( 'Phone', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
						'type'         => 'tel',
						'class'        => array( 'form-row-wide' ),
						'id'           => 'billing_phone',
						'field_type'   => 'billing',
						'validate'     => array( 'phone' ),
						'placeholder'  => '',
						'autocomplete' => 'tel',
						'priority'     => 100,
					),
				),
			),
			array(
				'name'        => WFACP_Common::translation_string_to_check( __( 'Shipping Method', 'funnel-builder' ) ),
				'class'       => '',
				'sub_heading' => '',
				'html_fields' => array( 'shipping_calculator' => true ),
				'fields'      => array(
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : array(),
				),
			),
			array(
				'name'        => WFACP_Common::translation_string_to_check( __( 'Order Summary', 'woocommerce' ) ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
				'class'       => 'wfacp_order_summary_box',
				'sub_heading' => '',
				'html_fields' => array(
					'order_coupon'  => 'true',
					'order_summary' => 'true',
				),
				'fields'      => array(
					$advanced_field['order_coupon'],
					$advanced_field['order_summary'],
				),
			),


		),
	),
	'product_settings'            => array(),
	'have_coupon_field'           => 'true',
	'have_billing_address'        => 'true',
	'have_shipping_address'       => 'true',
	'have_billing_address_index'  => '6',
	'have_shipping_address_index' => '7',
	'enabled_product_switching'   => 'yes',
	'have_shipping_method'        => 'true',
	'current_step'                => 'single_step',
);

return array(
	'default_customizer_value' => $customizer_data,
	'page_layout'              => $pageLayout,
);
