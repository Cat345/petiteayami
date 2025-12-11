<?php
$product_field  = WFACP_Common::get_product_field();
$advanced_field = WFACP_Common::get_advanced_fields();
$settings       = [
	'show_on_next_step' => [
		'single_step' => [
			'billing_email'       => 'false',
			'billing_first_name'  => 'false',
			'billing_last_name'   => 'false',
			'address'             => 'false',
			'shipping-address'    => 'false',
			'billing_phone'       => 'false',
			'shipping_calculator' => 'false',
		],
	],
];

$customizer_data = [
	'wfacp_form' => [
		'wfacp_form_section_embed_forms_2_step_form_max_width'                        => '450',
		'wfacp_form_section_text_below_placeorder_btn'                                => __( "* 100% Secure &amp; Safe Payments *", 'woofunnels-aero-checkout' ),
		'wfacp_form_section_embed_forms_2_active_step_bg_color'                       => '#4c4c4c',
		'wfacp_form_section_embed_forms_2_active_step_text_color'                     => '#ffffff',
		'wfacp_form_section_embed_forms_2_active_step_count_bg_color'                 => '#ffffff',
		'wfacp_form_section_embed_forms_2_active_step_count_border_color'             => '#ffffff',
		'wfacp_form_section_embed_forms_2_active_step_tab_border_color'               => '#f58e2d',
		'wfacp_form_section_embed_forms_2_inactive_step_bg_color'                     => '#f2f2f2',
		'wfacp_form_section_embed_forms_2_inactive_step_text_color'                   => '#979090',
		'wfacp_form_section_embed_forms_2_inactive_step_count_bg_color'               => 'rgba(255,255,255,0)',
		'wfacp_form_section_embed_forms_2_inactive_step_count_text_color'             => '#979090',
		'wfacp_form_section_embed_forms_2_inactive_step_count_border_color'           => '#979090',
		'wfacp_form_section_embed_forms_2_inactive_step_tab_border_color'             => '#ededed',
		'wfacp_form_section_embed_forms_2_active_step_count_text_color'               => '#4c4c4c',
		'wfacp_form_section_embed_forms_2_step_heading_font_size'                     => array(
			'desktop'      => '19',
			'tablet'       => '14',
			'mobile'       => '14',
			'desktop-unit' => 'px',
			'tablet-unit'  => 'px',
			'mobile-unit'  => 'px',
		),
		'wfacp_form_form_fields_1_embed_forms_2_billing_first_name'                   => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_last_name'                    => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_city'                         => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_postcode'                     => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_country'                      => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_billing_state'                        => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_city'                        => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_postcode'                    => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_country'                     => 'wfacp-col-left-half',
		'wfacp_form_form_fields_1_embed_forms_2_shipping_state'                       => 'wfacp-col-left-half',
		'wfacp_form_section_embed_forms_2_field_border_width'                         => '1',
		'wfacp_form_section_embed_forms_2_btn_order-place_bg_color'                   => '#f58e2d',
		'wfacp_form_section_embed_forms_2_btn_order-place_text_color'                 => '#ffffff',
		'wfacp_form_section_embed_forms_2_color_type'                                 => 'hover',
		'wfacp_form_section_embed_forms_2_btn_order-place_bg_hover_color'             => '#d46a06',
		'wfacp_order_summary_section_embed_forms_2_order_summary_hide_img'            => true,
		'wfacp_form_section_embed_forms_2_disable_steps_bar'                          => false,
		'wfacp_form_section_embed_forms_2_select_type'                                => "tab",
		'wfacp_form_section_embed_forms_2_step_sub_heading_font_size'                 => [
			'desktop'      => '15',
			'tablet'       => '12',
			'mobile'       => '12',
			'desktop-unit' => 'px',
			'tablet-unit'  => 'px',
			'mobile-unit'  => 'px',
		],
		'wfacp_form_section_payment_methods_heading'                                  => 'Payment method',
		'wfacp_form_section_embed_forms_2_heading_fs'                                 => [
			'desktop'      => '18',
			'tablet'       => '18',
			'mobile'       => '18',
			'desktop-unit' => 'px',
			'tablet-unit'  => 'px',
			'mobile-unit'  => 'px',
		],
		'wfacp_form_section_embed_forms_2_heading_font_weight'                        => 'wfacp-bold',
		'wfacp_form_section_embed_forms_2_sub_heading_font_weight'                    => 'wfacp-normal',
		'wfacp_form_section_embed_forms_2_sec_heading_color'                          => '#424141',
		'wfacp_form_section_embed_forms_2_field_border_color'                         => '#c3c0c0',
		'wfacp_form_section_embed_forms_2_btn_order-place_btn_text'                   => __( 'Place order', 'woocommerce' ),
		'wfacp_form_section_embed_forms_2_btn_order-place_btn_font_weight'            => 'bold',
		'wfacp_form_product_switcher_section_embed_forms_2_product_switcher_bg_color' => '#ffffff',
		'wfacp_form_section_embed_forms_2_btn_order-place_border_radius'              => '10',
	],

];


$steps = [
	'single_step' => [
		'name'          => __( 'Step 1', 'woofunnels-aero-checkout' ),
		'slug'          => 'single_step',
		'friendly_name' => __( 'Single Step Checkout', 'woofunnels-aero-checkout' ),
		'active'        => 'yes',
	],
	'two_step'    => [
		'name'          => __( 'Step 2', 'woofunnels-aero-checkout' ),
		'slug'          => 'two_step',
		'friendly_name' => __( 'Two Step Checkout', 'woofunnels-aero-checkout' ),
		'active'        => 'no',
	],
	'third_step'  => [
		'name'          => __( 'Step 3', 'woofunnels-aero-checkout' ),
		'slug'          => 'third_step',
		'friendly_name' => __( 'Three Step Checkout', 'woofunnels-aero-checkout' ),
		'active'        => 'no',
	],
];


$pageLayout = [
	'steps'                       => $steps,
	'fieldsets'                   => [
		'single_step' => [
			[
				'name'        => __( '', 'woofunnels-aero-checkout' ),
				'class'       => '',
				'sub_heading' => '',
				'fields'      => [
					[
						'label'        => __( 'Email', 'woocommerce' ),
						'required'     => 'true',
						'type'         => 'email',
						'class'        => [ 'form-row-wide', ],
						'validate'     => [ 'email', ],
						'autocomplete' => 'email username',
						'priority'     => '110',
						'id'           => 'billing_email',
						'field_type'   => 'billing',
						'placeholder'  => '',
					],
					[
						'label'        => __( 'First name', 'woocommerce' ),
						'required'     => 'true',
						'class'        => [ 'form-row-first', ],
						'autocomplete' => 'given-name',
						'priority'     => '10',
						'type'         => 'text',
						'id'           => 'billing_first_name',
						'field_type'   => 'billing',
						'placeholder'  => '',
					],
					[
						'label'        => __( 'Last name', 'woocommerce' ),
						'required'     => 'true',
						'class'        => [ 'form-row-last', ],
						'autocomplete' => 'family-name',
						'priority'     => '20',
						'type'         => 'text',
						'id'           => 'billing_last_name',
						'field_type'   => 'billing',
						'placeholder'  => '',
					],
					[
						'label'        => __( 'Phone', 'woocommerce' ),
						'type'         => 'tel',
						'class'        => [ 'form-row-wide' ],
						'id'           => 'billing_phone',
						'field_type'   => 'billing',
						'validate'     => [ 'phone' ],
						'placeholder'  => '',
						'autocomplete' => 'tel',
						'priority'     => 100,
					],
				],
			],
			[
				'name'        => __( 'Shipping Address', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'fields'      => [
					WFACP_Common::get_single_address_fields( 'shipping' ),
					WFACP_Common::get_single_address_fields(),
				],
			],
			[
				'name'        => __( 'Order summary', 'woocommerce' ),
				'class'       => 'wfacp_order_summary_box',
				'sub_heading' => '',
				'html_fields' => [
					'order_coupon'  => 'true',
					'order_summary' => 'true',
				],
				'fields'      => [
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : [],
					$advanced_field['order_coupon'],
					$advanced_field['order_total'] = [
						'type'        => 'wfacp_html',
						'field_type'  => 'advanced',
						'class'       => [ 'wfacp_order_total' ],
						'default'     => false,
						'id'          => 'order_total',
						'html_fields' => [
							'order_total' => true
						],
						'label'       => __( 'Order Total', 'woocommerce' ),
					],
				],
			],
		],
	],
	'product_settings'            => [
		'coupons'                             => '',
		'enable_coupon'                       => 'false',
		'disable_coupon'                      => 'false',
		'hide_quantity_switcher'              => 'true',
		'enable_delete_item'                  => 'false',
		'hide_product_image'                  => 'false',
		'is_hide_additional_information'      => 'true',
		'additional_information_title'        => WFACP_Common::get_default_additional_information_title(),
		'hide_quick_view'                     => 'true',
		'hide_you_save'                       => 'true',
		'hide_best_value'                     => 'false',
		'best_value_product'                  => '',
		'best_value_text'                     => __( 'Best Value', 'woofunnels-aero-checkout' ),
		'best_value_position'                 => 'above',
		'enable_custom_name_in_order_summary' => 'false',
		'autocomplete_enable'                 => 'false',
		'autocomplete_google_key'             => '',
		'preferred_countries_enable'          => 'false',
		'preferred_countries'                 => '',
		'product_switcher_template'           => 'default',
	],
	'have_coupon_field'           => 'true',
	'have_billing_address'        => 'true',
	'have_shipping_address'       => 'true',
	'have_billing_address_index'  => '6',
	'have_shipping_address_index' => '5',
	'enabled_product_switching'   => 'no',
	'have_shipping_method'        => 'true',
	'current_step'                => 'single_step',
];

$product_settings                     = [];
$product_settings['settings']         = $pageLayout['product_settings'];
$product_settings['products']         = [];
$product_settings['default_products'] = [];

return [
	'default_customizer_value'       => $customizer_data,
	'page_layout'                    => $pageLayout,
	'page_settings'                  => $settings,
	'wfacp_product_switcher_setting' => $product_settings,
];