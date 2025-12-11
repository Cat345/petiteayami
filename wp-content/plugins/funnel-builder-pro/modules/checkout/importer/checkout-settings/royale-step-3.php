<?php
$product_field  = WFACP_Common::get_product_field();
$advanced_field = WFACP_Common::get_advanced_fields();

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
		'active'        => 'yes',
	],
	'third_step'  => [
		'name'          => __( 'Step 3', 'woofunnels-aero-checkout' ),
		'slug'          => 'third_step',
		'friendly_name' => __( 'Three Step Checkout', 'woofunnels-aero-checkout' ),
		'active'        => 'yes',
	],
];

$customizer_data = [
	'wfacp_form'      => [
		'wfacp_form_section_layout_9_btn_next_btn_text'                  => __( 'Continue to Shipping →', 'woofunnels-aero-checkout' ),
		'wfacp_form_section_layout_9_btn_order-place_talign'             => 'right',
		'wfacp_form_section_layout_9_btn_order-place_top_bottom_padding' => '19',
		'wfacp_form_section_layout_9_btn_order-place_left_right_padding' => '40',
		'wfacp_form_section_layout_9_btn_order-place_bg_color'           => '#dd3333',
		'wfacp_form_section_breadcrumb_0_step_text'                      => __( 'Contact Information', 'woofunnels-aero-checkout' ),
		'wfacp_form_section_breadcrumb_1_step_text'                      => __( 'Shipping Method', 'woocommerce' ),
		'wfacp_form_section_breadcrumb_2_step_text'                      => __( 'Payment', 'woocommerce' ),
		'wfacp_form_section_layout_9_btn_order-place_fs'                 => [
			'desktop'      => '18',
			'tablet'       => '14',
			'mobile'       => '20',
			'desktop-unit' => 'px',
			'tablet-unit'  => 'px',
			'mobile-unit'  => 'px',
		],
		'wfacp_form_section_layout_9_color_type'                         => 'hover',
		'wfacp_form_section_layout_9_btn_order-place_bg_hover_color'     => '#dd3333',
		'wfacp_form_section_layout_9_btn_back_btn_text'                  => __( 'Continue to payment →', 'woofunnels-aero-checkout' ),
		'wfacp_form_section_layout_9_btn_order-place_btn_text'           => __( 'Place order', 'woocommerce' ),
		'wfacp_form_section_layout_9_field_style_fs'                     => [
			'desktop'      => '14',
			'tablet'       => '13',
			'mobile'       => '13',
			'desktop-unit' => 'px',
			'tablet-unit'  => 'px',
			'mobile-unit'  => 'px',
		],
		'wfacp_form_section_layout_9_field_border_color'                 => '#d9d9d9',
		'wfacp_form_section_layout_9_btn_back_text_color'                => '#dd7575',
		'wfacp_form_section_layout_9_backlink_color_type'                => 'hover',
		'wfacp_form_section_layout_9_btn_back_text_hover_color'          => '#965d5d',
		'wfacp_form_section_layout_9_btn_order-place_btn_font_weight'    => 'normal',
		'wfacp_form_section_layout_9_form_content_link_color_type'       => 'hover',
		'wfacp_form_section_layout_9_heading_talign'                     => 'wfacp-text-left',
		'wfacp_form_section_cart_text'                                   => 'Cart',
	],
	'wfacp_layout'    => [
		'wfacp_layout_section_layout_9_sidebar_layout_order'       => [
			'wfacp_cart',
			'wfacp_testimonials_0',
		],
		'wfacp_layout_section_layout_9_mobile_sections_page_order' => [ 'wfacp_form', 'wfacp_testimonials_0', 'wfacp_promises_0' ],
		'wfacp_layout_section_layout_9_other_layout_widget'        => [ 'wfacp_promises_0' ],
	],
	'wfacp_footer'    => [
		'wfacp_footer_section_layout_9_section_bg_color'   => '#000000',
		'wfacp_footer_section_layout_9_content_text_color' => '#ffffff',
	],
	'wfacp_header'    => [
		'wfacp_header_section_layout_9_header_layout'      => 'top_bottom',
		'wfacp_header_section_layout_9_section_bg_color'   => '#000000',
		'wfacp_header_section_layout_9_header_icon_color'  => '#ffffff',
		'wfacp_header_section_layout_9_content_text_color' => '#ffffff',
		'wfacp_header_section_header_text'                 => '',
		'wfacp_header_section_helpdesk_text'               => '',
		'wfacp_header_section_helpdesk_url'                => '',
		'wfacp_header_section_email'                       => '',
		'wfacp_header_section_phone'                       => '',
		'wfacp_header_section_tel_number'                  => '',
		'wfacp_header_section_logo'                        => 'https://woofunnels.s3.amazonaws.com/templates/checkout/customizer/logo_white_aero.png',
		'wfacp_header_section_logo_width'                  => '242',
	],
	'wfacp_style'     => [
		'wfacp_style_colors_layout_9_body_background_color'    => '#ffffff',
		'wfacp_style_colors_layout_9_sidebar_background_color' => '#fafafa',
	],
	'wfacp_form_cart' => [
		'wfacp_form_cart_section_layout_9_enable_coupon_right_side_coupon' => false,
	],
];


$pageLayout = [
	'steps'     => $steps,
	'fieldsets' => [
		'single_step' => [
			[
				'name'        => __( 'Contact Information', 'woofunnels-aero-checkout' ),
				'class'       => '',
				'is_default'  => 'yes',
				'sub_heading' => '',
				'fields'      => [
					[
						'label'        => __( 'Email', 'woocommerce' ),
						'required'     => 'true',
						'type'         => 'email',
						'class'        => [ 'form-row-wide' ],
						'validate'     => [ 'email' ],
						'autocomplete' => 'email username',
						'priority'     => '110',
						'id'           => 'billing_email',
						'field_type'   => 'billing',
						'placeholder'  => '',
						'data_label'   => 'Email',
					],
				],
			],
			[
				'name'        => __( 'Shipping Address', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'fields'      => [
					array(
						'label'        => __( 'First name', 'woocommerce' ),
						'required'     => 'true',
						'class'        => [ 'form-row-first' ],
						'autocomplete' => 'given-name',
						'priority'     => '10',
						'type'         => 'text',
						'id'           => 'billing_first_name',
						'field_type'   => 'billing',
						'placeholder'  => '',

					),
					array(
						'label'        => __( 'Last name', 'woocommerce' ),
						'required'     => 'true',
						'class'        => [ 'form-row-last' ],
						'autocomplete' => 'family-name',
						'priority'     => '20',
						'type'         => 'text',
						'id'           => 'billing_last_name',
						'field_type'   => 'billing',
						'placeholder'  => '',
					),
					WFACP_Common::get_single_address_fields( 'shipping' ),
					WFACP_Common::get_single_address_fields(),
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

		],
		'two_step'    => [
			[
				'name'        => __( 'Shipping Method', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'html_fields' => [ 'shipping_calculator' => true ],
				'fields'      => [
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : []
				],
			],

		],
	],

	'product_settings'            => [
		'coupons'                             => '',
		'enable_coupon'                       => 'false',
		'disable_coupon'                      => 'false',
		'hide_quantity_switcher'              => 'false',
		'enable_delete_item'                  => 'false',
		'hide_product_image'                  => 'true',
		'is_hide_additional_information'      => 'false',
		'additional_information_title'        => __( 'WHAT\'S INCLUDED IN YOUR PLAN?', 'woofunnels-aero-checkout' ),
		'hide_quick_view'                     => 'false',
		'hide_you_save'                       => 'true',
		'hide_best_value'                     => 'false',
		'best_value_product'                  => '',
		'best_value_text'                     => __( 'Best Value', 'woofunnels-aero-checkout' ),
		'best_value_position'                 => 'below',
		'enable_custom_name_in_order_summary' => 'false',
		'autocomplete_enable'                 => 'false',
		'autocomplete_google_key'             => '',
		'preferred_countries_enable'          => 'false',
		'enable_autopopulate_fields'          => 'true',
		'enable_autopopulate_state'           => 'true',
		'autopopulate_state_service'          => 'zippopotamus',
		'preferred_countries'                 => '',
		'enable_smart_buttons'                => 'false',
		'smart_button_position'               => 'wfacp_form_single_step_start',
		'product_switcher_template'           => 'default',
	],
	'have_coupon_field'           => 'false',
	'have_billing_address'        => 'true',
	'have_shipping_address'       => 'true',
	'have_billing_address_index'  => '5',
	'have_shipping_address_index' => '4',
	'enabled_product_switching'   => 'no',
	'have_shipping_method'        => 'true',
	'current_step'                => 'third_step',

];

$page_settings = [
	'show_on_next_step' => [
		'single_step' => [
			'billing_email'    => 'true',
			'shipping-address' => 'true',
			'address'          => 'true',
		],
		'two_step'    => [
			[
				'name'        => __( 'Shipping Method', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'html_fields' => [ 'shipping_calculator' => true ],
				'fields'      => [
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : []
				],
			],

		],
	]
];

$pageLayout['address_order'] = array(
	'shipping-address' => array(
		array(
			'key'         => 'first_name',
			'status'      => 'false',
			'label'       => __( 'First Name', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'false',
		),
		array(
			'key'         => 'last_name',
			'status'      => 'false',
			'label'       => __( 'Last Name', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'false',
		),
		array(
			'key'         => 'company',
			'status'      => 'false',
			'label'       => __( 'Company', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'false',
		),
		array(
			'key'         => 'address_1',
			'status'      => 'true',
			'label'       => __( 'Street address', 'woocommerce' ),
			'placeholder' => __( '', 'woocommerce' ),
			'required'    => 'true',
		),
		array(
			'key'         => 'address_2',
			'status'      => 'false',
			'label'       => __( 'Street address 2', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'false',
		),
		array(
			'key'         => 'city',
			'status'      => 'true',
			'label'       => __( 'Town / City', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'true',
		),
		array(
			'key'         => 'postcode',
			'status'      => 'true',
			'label'       => __( 'Postcode', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'true',
		),
		array(
			'key'         => 'country',
			'status'      => 'true',
			'label'       => __( 'Country', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'true',
		),
		array(
			'key'         => 'state',
			'status'      => 'true',
			'label'       => __( 'State', 'woocommerce' ),
			'placeholder' => '',
			'required'    => 'false',
		),
		array(
			'key'    => 'same_as_billing',
			'status' => 'false',
			'label'  => __( 'Use a different shipping address', 'woofunnels-aero-checkout' ),
		),
	),
	'address'          => array(
		array(
			'key'    => 'same_as_shipping',
			'status' => 'true',
			'label'  => __( 'Use a different Billing address', 'woofunnels-aero-checkout' ),
		),
	)
);

$product_settings                     = [];
$product_settings['settings']         = $pageLayout['product_settings'];
$product_settings['products']         = [];
$product_settings['default_products'] = [];

return [
	'default_customizer_value'       => $customizer_data,
	'page_layout'                    => $pageLayout,
	'page_settings'                  => $page_settings,
	'wfacp_product_switcher_setting' => $product_settings,
];
