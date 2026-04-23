<?php
$product_field  = WFACP_Common::get_product_field();
$advanced_field = WFACP_Common::get_advanced_fields();
$settings       = array(
	'show_on_next_step'          => array(
		'single_step' => array(
			'billing_email'      => 'true',
			'billing_first_name' => 'true',
			'billing_last_name'  => 'true',
			'shipping-address'   => 'true',
			'billing'            => 'true',
			'billing_phone'      => 'true',
		),
	),
	'autocomplete_enable'        => 'true',
	'autocomplete_google_key'    => '',
	'enable_autopopulate_state'  => 'true',
	'autopopulate_state_service' => 'zippopotamus',
	'enable_phone_flag'          => 'true',
	'enable_phone_validation'    => 'true',
	'preferred_countries_enable' => 'false',
	'preferred_countries'        => '',
);
$steps          = WFACP_Common::get_default_steps_fields( true );
if ( ! isset( $advanced_field['shipping_calculator']['data_label'] ) ) {
	$advanced_field['shipping_calculator']['data_label'] = __( 'Shipping Method', 'woocommerce' );
}
$pageLayout = array(
	'steps'                       => $steps,
	'fieldsets'                   => array(
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
						'class'        => array( 'form-row-wide' ),
						'validate'     => array( 'email' ),
						'autocomplete' => 'email',
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
					WFACP_Common::get_single_address_fields( 'shipping' ),
					WFACP_Common::get_single_address_fields(),
					array(
						'label'        => __( 'Phone', 'woocommerce' ),
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

		),
		'two_step'    => array(
			array(
				'name'        => __( 'Shipping Method', 'woocommerce' ),
				'class'       => '',
				'sub_heading' => '',
				'html_fields' => array(
					'shipping_calculator' => 'true',
				),
				'fields'      => array(
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : array(),
				),
			),

		),
	),
	'product_settings'            => array(
		'coupons'                             => '',
		'enable_coupon'                       => 'false',
		'disable_coupon'                      => 'false',
		'hide_quantity_switcher'              => 'false',
		'enable_delete_item'                  => 'false',
		'hide_product_image'                  => 'false',
		'is_hide_additional_information'      => 'true',
		'additional_information_title'        => WFACP_Common::get_default_additional_information_title(),
		'hide_quick_view'                     => 'false',
		'hide_you_save'                       => 'true',
		'hide_best_value'                     => 'false',
		'best_value_product'                  => '',
		'best_value_text'                     => __( 'Best Value', 'woofunnels-aero-checkout' ),
		'best_value_position'                 => 'above',
		'enable_custom_name_in_order_summary' => 'false',
		'product_switcher_template'           => 'default',
	),
	'have_coupon_field'           => 'false',
	'have_billing_address'        => 'true',
	'have_shipping_address'       => 'true',
	'have_billing_address_index'  => '5',
	'have_shipping_address_index' => '4',
	'enabled_product_switching'   => 'yes',
	'have_shipping_method'        => 'true',
	'current_step'                => 'third_step',
);

return array(
	'page_layout'   => $pageLayout,
	'page_settings' => $settings,
);
