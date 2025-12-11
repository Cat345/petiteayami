<?php
$product_field  = WFACP_Common::get_product_field();
$advanced_field = WFACP_Common::get_advanced_fields();

$settings = [
	'show_on_next_step' => [
		'single_step' => array(
			'billing_email'      => 'true',
			'billing_first_name' => 'false',
			'billing_last_name'  => 'false',
			'shipping-address'   => 'true',
			'address'            => 'true',
			'billing_phone'      => 'false',
		),
		'two_step'    => array(
			'shipping_calculator' => 'true'
		)
	]
];

$pageLayout = [
	'steps'     => WFACP_Common::get_default_steps_fields( true ),
	'fieldsets' => [
		'single_step' => array(
			array(
				'name'        => __( 'Shipping Information', 'woofunnels-aero-checkout' ),
				'class'       => '',
				'is_default'  => 'yes',
				'sub_heading' => '',
				'fields'      => array(
					array(
						'label'        => __( 'Email', 'woocommerce' ),
						'required'     => 'true',
						'type'         => 'email',
						'class'        => array(
							0 => 'form-row-wide',
						),
						'validate'     => array(
							0 => 'email',
						),
						'autocomplete' => 'email username',
						'priority'     => '110',
						'id'           => 'billing_email',
						'field_type'   => 'billing',
						'placeholder'  => '',
					),
					array(
						'label'        => __( 'First name', 'woocommerce' ),
						'required'     => 'true',
						'class'        => array(
							0 => 'form-row-first',
						),
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
						'class'        => array(
							0 => 'form-row-last',
						),
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

				),
			),
			[
				'name'        => __( 'Shipping Method', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'html_fields' => [ 'shipping_calculator' => true ],
				'fields'      => [
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : []
				],
			],



		),
		'two_step'    => [
			[
				'name'        => __( 'Your Products', 'woofunnels-aero-checkout' ),
				'class'       => '',
				'sub_heading' => '',
				'html_fields' => [
					'product_switching' => 'true',
					'order_coupon'      => 'true',
				],
				'fields'      => [
					$product_field['product_switching'],
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
			'status' => 'true',
			'label'  => __( 'Use a different shipping address', 'woofunnels-aero-checkout' ),
		),
	),
);


$product_settings                     = [];
$product_settings['settings']         = $pageLayout['product_settings'];
$product_settings['products']         = [];
$product_settings['default_products'] = [];

return [
	'page_layout'                    => $pageLayout,
	'page_settings'                  => $settings,
	'wfacp_product_switcher_setting' => $product_settings,
];

