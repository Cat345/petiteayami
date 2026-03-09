<?php

namespace Stripe\StripeTaxForWooCommerce;

defined( 'ABSPATH' ) || exit;

abstract class StripeTax_Options {
	const PREFIX = 'stripe_tax_';
	const _PREFIX = '_' . self::PREFIX;
	const __PREFIX = '__' . self::PREFIX;

	const ALLOW_FEE_PRICE_INCLUDE_TAXES = 'allow_fee_price_include_taxes';
	const ALLOW_SHIPPING_PRICE_INCLUDE_TAXES = 'allow_shipping_price_include_taxes';

	const IGNORE_WC_TAX_BASED_ON = 'ignore_wc_tax_based_on';
	const USE_BASE_ADDRESS_FALLBACK = 'use_base_address_fallback';

	const CART_ITEM_REFERENCE_META = self::_PREFIX . '_cart_item_reference';

	const NON_TAXABLE_TAX_CODE = 'non_taxable_tax_code';

	const SHIPPING_TAX_CODE = 'shipping_tax_code';

	const FEE_TAX_CODE = 'fee_tax_code';
	const NON_TAXABLE_FEE_TAX_CODE = 'non_taxable_fee_tax_code';

	protected static $options = array(
		self::IGNORE_WC_TAX_BASED_ON             => true,
		self::USE_BASE_ADDRESS_FALLBACK          => true,
		self::ALLOW_FEE_PRICE_INCLUDE_TAXES      => true,
		self::ALLOW_SHIPPING_PRICE_INCLUDE_TAXES => true,
		self::NON_TAXABLE_TAX_CODE               => 'txcd_00000000',
		self::SHIPPING_TAX_CODE                  => 'txcd_92010001',
		self::FEE_TAX_CODE                       => 'txcd_20030000',
		self::NON_TAXABLE_FEE_TAX_CODE           => 'txcd_00000000'
	);

	public static function item_allow_price_tax_inclusive($item_type) {
		switch ( $item_type ) {
			case 'line_item':
				return true;
			
			case 'shipping':
				return static::get_option( static::ALLOW_SHIPPING_PRICE_INCLUDE_TAXES );

			case 'fee':
				return static::get_option( static::ALLOW_FEE_PRICE_INCLUDE_TAXES );
		}

		return true;
	}

	public static function get_option( $option_name ) {
		return static::$options[ $option_name ];
	}
}
