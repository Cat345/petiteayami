<?php
$product_field  = WFACP_Common::get_product_field();
$advanced_field = WFACP_Common::get_advanced_fields();

$settings = [
	'show_on_next_step'          => [
		'single_step' => array(
			'billing_email'      => 'true',
			'billing_first_name' => 'true',
			'billing_last_name'  => 'true',
			'shipping-address'   => 'true',
			'address'            => 'true',
			'billing_phone'      => 'true',
		),
		'two_step'    => array(
			'shipping_calculator' => 'true'
		)
	],
	'coupons'                    => '',
	'enable_coupon'              => 'false',
	'disable_coupon'             => 'false',
	'autocomplete_enable'        => 'false',
	'autocomplete_google_key'    => '',
	'preferred_countries_enable' => 'false',
	'enable_autopopulate_fields' => 'true',
	'enable_autopopulate_state'  => 'true',
	'autopopulate_state_service' => 'zippopotamus',
	'preferred_countries'        => '',
	'enable_smart_buttons'       => 'false',
	'smart_button_position'      => 'wfacp_form_single_step_start',
	'collapsible_optional_fields'    => [
		'shipping_company'   => "false",
		'shipping_address_2' => "true",
		'shipping_phone'     => "false",
		'billing_company'    => "false",
		'billing_address_2'  => "true",
		'billing_phone'      => "false",

	],
	'collapsible_optional_link_text' => __( "Add", 'woocommerce' ),
];

$shipping_fields = WFACP_Common::get_single_address_fields( 'shipping' );
if ( isset( $shipping_fields['fields_options'] ) && isset( $shipping_fields['fields_options']['address_2']['street_address2'] ) ) {
	$shipping_fields['fields_options']['address_2']['street_address2'] = "true";
}

$billing_fields = WFACP_Common::get_single_address_fields();
if ( isset( $billing_fields['fields_options'] ) && isset( $billing_fields['fields_options']['address_2']['street_address2'] ) ) {
	$billing_fields['fields_options']['address_2']['street_address2'] = "true";
}

$pageLayout = [
	'steps'     => WFACP_Common::get_default_steps_fields( true ),
	'fieldsets' => [
		'single_step' => array(
			array(
				'name'        => __( 'Contact Information', 'woofunnels-aero-checkout' ),
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

				),
			),
			array(
				'name'        => __( 'Shipping Address', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'fields'      => array(
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
					$shipping_fields,
					$billing_fields,
					array(
						'label'        => __( 'Phone', 'woocommerce' ),
						'required'     => 'false',
						'class'        => array(
							0 => 'form-row-last',
						),
						'autocomplete' => 'phone',
						'priority'     => '20',
						'type'         => 'tel',
						'id'           => 'billing_phone',
						'field_type'   => 'billing',
						'placeholder'  => '',
					),
				),

			),

		),
		'two_step'    => array(
			array(
				'name'        => __( 'Shipping Method', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'fields'      => array(
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : []
				)
			)
		)
	],

	'product_settings'            => [

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


$product_settings                     = [];
$product_settings['settings']         = $pageLayout['product_settings'];
$product_settings['products']         = [];
$product_settings['default_products'] = [];

return [
	'page_layout'                    => $pageLayout,
	'page_settings'                  => $settings,
	'wfacp_product_switcher_setting' => $product_settings,
];

