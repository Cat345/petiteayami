<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
return array(
	'AL' => array(
		'state' => array(
			'label'       => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'AO' => array(
		'postcode' => array(
			'required' => false,
			'hidden'   => true,
		),
		'state'    => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'AU' => array(
		'city'     => array(
			'label'       => __( 'Suburb', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Suburb', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'postcode' => array(
			'label'       => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'BA' => array(
		'postcode' => array(
			'priority' => 65,
		),
		'state'    => array(
			'label'       => __( 'Canton', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'hidden'      => true,
			'placeholder' => __( 'Canton', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'BD' => array(
		'postcode' => array(
			'required' => false,
		),
		'state'    => array(
			'label'       => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'BO' => array(
		'postcode' => array(
			'required' => false,
			'hidden'   => true,
		),
		'state'    => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'CA' => array(
		'postcode' => array(
			'label'       => __( 'Postal code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Postal code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'CH' => array(
		'postcode' => array(
			'priority' => 65,
		),
		'state'    => array(
			'label'       => __( 'Canton', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'placeholder' => __( 'Canton', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'CL' => array(
		'city'     => array(
			'required' => true,
		),
		'postcode' => array(
			'required' => false,
		),
		'state'    => array(
			'label'       => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'CN' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'CO' => array(
		'postcode' => array(
			'required' => false,
		),
		'state'    => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'CR' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'DO' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'EC' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'GH' => array(
		'postcode' => array(
			'required' => false,
		),
		'state'    => array(
			'label'       => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'GT' => array(
		'postcode' => array(
			'required' => false,
		),
		'state'    => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'HK' => array(
		'postcode' => array(
			'required' => false,
		),
		'city'     => array(
			'label'       => __( 'Town / District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Town / District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'HN' => array(
		'state' => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'HU' => array(
		'last_name'  => array(
			'class'    => array(
				0 => 'form-row-first',
			),
			'priority' => 10,
		),
		'first_name' => array(
			'class'    => array(
				0 => 'form-row-last',
			),
			'priority' => 20,
		),
		'postcode'   => array(
			'class'    => array(
				0 => 'form-row-first',
				1 => 'address-field',
			),
			'priority' => 65,
		),
		'city'       => array(
			'class' => array(
				0 => 'form-row-last',
				1 => 'address-field',
			),
		),
		'address_1'  => array(
			'priority' => 71,
		),
		'address_2'  => array(
			'priority' => 72,
		),
		'state'      => array(
			'label'       => 'County',
			'placeholder' => 'County',
		),
	),
	'ID' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'IE' => array(
		'postcode' => array(
			'required'    => false,
			'label'       => __( 'Eircode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Eircode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'IN' => array(
		'postcode' => array(
			'label'       => __( 'PIN Code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'PIN Code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'IT' => array(
		'postcode' => array(
			'priority' => 65,
		),
		'state'    => array(
			'required'    => true,
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'JM' => array(
		'city'     => array(
			'label'       => __( 'Town / City / Post Office', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Town / City / Post Office', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'postcode' => array(
			'required'    => false,
			'label'       => __( 'Postal Code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Postal Code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'required'    => true,
			'label'       => __( 'Parish', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Parish', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'JP' => array(
		'last_name'  => array(
			'class'    => array(
				0 => 'form-row-first',
			),
			'priority' => 10,
		),
		'first_name' => array(
			'class'    => array(
				0 => 'form-row-last',
			),
			'priority' => 20,
		),
		'postcode'   => array(
			'class'    => array(
				0 => 'form-row-first',
				1 => 'address-field',
			),
			'priority' => 65,
		),
		'state'      => array(
			'priority'    => 66,
			'placeholder' => __( 'Prefecture', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'label'       => __( 'Prefecture', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'city'       => array(
			'priority' => 67,
		),
		'address_1'  => array(
			'priority' => 68,
		),
		'address_2'  => array(
			'priority' => 69,
		),

	),
	'LV' => array(
		'state' => array(
			'label'       => __( 'Municipality', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'placeholder' => __( 'Municipality', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'MZ' => array(
		'postcode' => array(
			'required' => false,
			'hidden'   => true,
		),
		'state'    => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'NI' => array(
		'state' => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'NG' => array(
		'postcode' => array(
			'label'       => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'hidden'      => true,
			'placeholder' => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'NZ' => array(
		'postcode' => array(
			'label'       => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'required'    => false,
			'label'       => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Region', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'NP' => array(
		'state'    => array(
			'label'       => __( 'State / Zone', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'State / Zone', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'postcode' => array(
			'required' => false,
		),
	),
	'PA' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'PR' => array(
		'city'  => array(
			'label'       => __( 'Municipality', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Municipality', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state' => array(
			'required' => false,
			'hidden'   => true,
		),
	),
	'PY' => array(
		'state' => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'RO' => array(
		'state' => array(
			'label'       => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => true,
			'placeholder' => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'RS' => array(
		'city'     => array(
			'required' => true,
		),
		'postcode' => array(
			'required' => true,
		),
		'state'    => array(
			'label'       => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'placeholder' => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'SV' => array(
		'state' => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'ES' => array(
		'postcode' => array(
			'priority' => 65,
		),
		'state'    => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'LI' => array(
		'postcode' => array(
			'priority' => 65,
		),
		'state'    => array(
			'label'       => __( 'Municipality', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'placeholder' => __( 'Municipality', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'MD' => array(
		'state' => array(
			'label'       => __( 'Municipality / District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Municipality / District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'TR' => array(
		'postcode' => array(
			'priority' => 65,
		),
		'state'    => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'UG' => array(
		'postcode' => array(
			'required' => false,
			'hidden'   => true,
		),
		'city'     => array(
			'label'       => __( 'Town / Village', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => true,
			'placeholder' => __( 'Town / Village', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => true,
			'placeholder' => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'US' => array(
		'postcode' => array(
			'label'       => __( 'ZIP Code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'ZIP Code', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'State', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'UY' => array(
		'state' => array(
			'label'       => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Department', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'GB' => array(
		'postcode' => array(
			'label'       => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Postcode', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
		'state'    => array(
			'label'       => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'required'    => false,
			'placeholder' => __( 'County', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'ST' => array(
		'postcode' => array(
			'required' => false,
			'hidden'   => true,
		),
		'state'    => array(
			'label'       => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'District', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
	'ZA' => array(
		'state' => array(
			'label'       => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
			'placeholder' => __( 'Province', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- deliberate: reuse WooCommerce core translation.
		),
	),
);
