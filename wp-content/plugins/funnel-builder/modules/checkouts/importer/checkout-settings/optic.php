<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
$product_field  = WFACP_Common::get_product_field();
$advanced_field = WFACP_Common::get_advanced_fields();
$settings       = array(
	'show_on_next_step'              => array(
		'single_step' => array(
			'billing_email'       => 'false',
			'billing_first_name'  => 'false',
			'billing_last_name'   => 'false',
			'address'             => 'false',
			'shipping-address'    => 'false',
			'billing_phone'       => 'false',
			'shipping_calculator' => 'false',
		),
	),
	'collapsible_optional_fields'    => array(
		'shipping_company'   => 'false',
		'shipping_address_2' => 'true',
		'shipping_phone'     => 'false',
		'billing_company'    => 'false',
		'billing_address_2'  => 'true',
		'billing_phone'      => 'false',

	),
	'collapsible_optional_link_text' => __( 'Add', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
);


$shipping_fields = WFACP_Common::get_single_address_fields( 'shipping' );
if ( isset( $shipping_fields['fields_options'] ) && isset( $shipping_fields['fields_options']['address_2']['street_address2'] ) ) {
	$shipping_fields['fields_options']['address_2']['street_address2'] = 'true';
}

$billing_fields = WFACP_Common::get_single_address_fields();
if ( isset( $billing_fields['fields_options'] ) && isset( $billing_fields['fields_options']['address_2']['street_address2'] ) ) {
	$billing_fields['fields_options']['address_2']['street_address2'] = 'true';
}

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


if ( ! isset( $advanced_field['shipping_calculator']['data_label'] ) ) {
	$advanced_field['shipping_calculator']['data_label'] = WFACP_Common::translation_string_to_check( __( 'Shipping Method', 'funnel-builder' ) );
}

$pageLayout = array(
	'steps'                       => $steps,
	'fieldsets'                   => array(
		'single_step' => array(
			array(
				'name'        => WFACP_Common::translation_string_to_check( __( 'Shipping Information', 'funnel-builder' ) ),
				'class'       => '',
				'is_default'  => 'yes',
				'sub_heading' => '',
				'fields'      => array(
					array(
						'label'        => __( 'Email', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
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
						'label'        => __( 'First name', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
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
						'label'        => __( 'Last name', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
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
				'fields'      => array(
					isset( $advanced_field['shipping_calculator'] ) ? $advanced_field['shipping_calculator'] : array(),
				),

			),
			array(
				'name'        => WFACP_Common::translation_string_to_check( __( 'Order Summary', 'woocommerce' ) ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
				'class'       => '',
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
	'have_billing_address_index'  => '5',
	'have_shipping_address_index' => '4',
	'enabled_product_switching'   => 'no',
	'have_shipping_method'        => 'true',
	'current_step'                => 'single_step',
);

return array(
	'page_layout'   => $pageLayout,
	'page_settings' => $settings,
);
