<?php
/**
 * Tax calculation operations data base class.
 *
 * @package Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation
 */

namespace Stripe\StripeTaxForWooCommerce\Stripe\Tax_Calculation;

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\StripeTax_Options;
use Stripe\StripeTaxForWooCommerce\Utils\Readonly_Object;

/**
 * Tax calculation operations data base class.
 */
class Data extends Readonly_Object {
	const TAX_BEHAVIOR_INCLUSIVE = 'inclusive';
	const TAX_BEHAVIOR_EXCLUSIVE = 'exclusive';

	const ADDRESS_SOURCE_SHIPPING = 'shipping';
	const ADDRESS_SOURCE_BILLING  = 'billing';

	const SHIPPING_COST_REFERENCE = 'shipping_cost';
	const SHIPPING_COST_KEY       = 'shipping_cost';

	const TAX_EXCLUDED_META_NAME        = StripeTax_Options::__PREFIX . 'tax_excluded';
	const CART_ITEM_REFERENCE_META_NAME = StripeTax_Options::__PREFIX . 'cart_item_reference';

	const REFERENCE_SEPARATOR = '#';

	/**
	 * Builds and returns an item reference to use in API payloads
	 *
	 * @param string       $item_type Item type.
	 * @param int          $item_id Item ID.
	 * @param int          $item_variation_id Item variation id.
	 * @param string       $item_name Item name.
	 * @param array        $item_variation_attributes Variation attributes.
	 * @param array|object $cart_item_data Cart item data.
	 */
	public static function build_item_reference( $item_type, $item_id, $item_variation_id, $item_name, $item_variation_attributes, $cart_item_data = null ) {
		if ( $item_variation_attributes ) {
			ksort( $item_variation_attributes );
			$item_variation_attributes_as_string = implode(
				',',
				array_map(
					function ( $key, $value ) {
						return "$key:$value";
					},
					array_keys( $item_variation_attributes ),
					$item_variation_attributes
				)
			);
		} else {
			$item_variation_attributes_as_string = '';
		}

		if ( ! is_null( $cart_item_data ) ) {
			if ( ( is_array( $cart_item_data ) && count( $cart_item_data ) > 0 ) || is_object( $cart_item_data ) ) {
				$cart_item_data_as_string = wp_json_encode( $cart_item_data );
				/* @phpstan-ignore-next-line */
			} elseif ( is_array( $cart_item_data ) ) {
				$cart_item_data_as_string = '';
			} else {
				$cart_item_data_as_string = '' . $cart_item_data;
			}
		} else {
			$cart_item_data_as_string = '';
		}

		return $item_type . static::REFERENCE_SEPARATOR . md5(
			$item_id . static::REFERENCE_SEPARATOR .
			$item_variation_id . static::REFERENCE_SEPARATOR .
			$item_name . static::REFERENCE_SEPARATOR .
			$item_variation_attributes_as_string . static::REFERENCE_SEPARATOR .
			$cart_item_data_as_string
		);
	}

	/**
	 * Returns a line item type given its reference
	 *
	 * @param string $reference Line item reference.
	 */
	public static function get_line_item_type_by_reference( $reference ) {
		$pos = strpos( $reference, static::REFERENCE_SEPARATOR );

		return substr( $reference, 0, $pos );
	}

	/**
	 * Finds and returns an item byt its reference
	 *
	 * @param string $reference Item reference.
	 */
	public function get_line_item_by_reference( $reference ) {
		if ( static::SHIPPING_COST_REFERENCE === $reference ) {
			return $this[ static::SHIPPING_COST_KEY ];
		}
		// @phpstan-ignore-next-line
		foreach ( $this->line_items as $line_item ) {
			if ( $line_item['reference'] === $reference ) {
				return $line_item;
			}
		}
	}
}
