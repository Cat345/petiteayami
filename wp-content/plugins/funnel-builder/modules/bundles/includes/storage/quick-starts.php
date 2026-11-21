<?php
/**
 * Quick-start presets: a new bundle created with its settings already filled in.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Registers this plugin's presets and applies the one a create request names.
 */
class Quick_Starts {

	/**
	 * Add the presets for the bundle types this plugin renders.
	 *
	 * Discounts are percentages, so they read the same in every store currency.
	 *
	 * @param array<string,array<string,mixed>> $templates Config to merge, keyed by preset id.
	 * @return array<string,array<string,mixed>>
	 */
	public static function templates( $templates ) {
		$templates = is_array( $templates ) ? $templates : array();

		$templates['save_on_full_set'] = array(
			'fbtDiscountScope' => 'set',
			'fbtDiscountType'  => 'percentage',
			'fbtDiscountValue' => 15,
			'fbtMinCompanions' => 2,
		);
		$templates['starter_kit']      = array(
			'fixedBundleDiscountScope' => 'whole_bundle',
			'fixedBundleDiscountType'  => 'percentage',
			'fixedBundleDiscountValue' => 15,
		);
		$templates['buy_one_or_pack']  = array(
			'fixedBundleShowSingle'    => true,
			'fixedBundleDiscountScope' => 'whole_bundle',
			'fixedBundleDiscountType'  => 'percentage',
			'fixedBundleDiscountValue' => 10,
		);
		$templates['per_item_savings'] = array(
			'fixedBundleDiscountScope' => 'separate_products',
			'fixedBundleDiscountType'  => 'percentage',
			'fixedBundleDiscountValue' => 10,
		);

		return $templates;
	}

	/**
	 * Merge the named preset into a bundle about to be created.
	 *
	 * The preset id is removed once applied, so an add-on applying presets on
	 * `wffn_bundles_bundle_create_payload` finds nothing left to do.
	 *
	 * @param array $payload Incoming bundle payload.
	 * @return array|\WP_Error
	 */
	public static function apply( array $payload ) {
		if ( ! isset( $payload['quick_start'] ) ) {
			return $payload;
		}

		/**
		 * Filters the quick-start presets a create request can name.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,mixed>> $templates Config to merge, keyed by preset id.
		 */
		$templates = (array) apply_filters( 'wffn_bundles_quick_start_templates', array() );
		$key       = (string) $payload['quick_start'];
		unset( $payload['quick_start'] );

		if ( ! isset( $templates[ $key ] ) || ! is_array( $templates[ $key ] ) ) {
			return new \WP_Error( 'wffn_bundles_unknown_quick_start', __( 'Unknown quick start template.', 'funnel-builder' ) );
		}

		$payload['discountConfig'] = array_merge(
			is_array( $payload['discountConfig'] ?? null ) ? $payload['discountConfig'] : array(),
			$templates[ $key ]
		);
		if ( ! isset( $payload['productSelection'] ) ) {
			// Empty specific-products, so a preset bundle is never live catalog-wide.
			$payload['productSelection'] = array(
				'mode'     => Bundle_Store::MODE_SPECIFIC_PRODUCTS,
				'products' => array(),
			);
		}

		return $payload;
	}
}
