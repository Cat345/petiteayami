<?php
/** Variants — variant-picker scope shared by every skin (mirror of variants.ts). */
namespace WFFN\Bundles\Includes\Skins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the variant-picker scope, mirroring admin/app/src/lib/skins/variants.ts.
 */
class Variants {

	/**
	 * Build the variant-picker scope for a product input.
	 *
	 * One axis per attribute when the product defines attributes, otherwise a
	 * single axis of the flat variations list.
	 *
	 * @param array $product Hydrated product row.
	 * @return array Picker scope keys, with variantShow false for non-variable products.
	 */
	public static function picker( $product ) {
		$product    = is_array( $product ) ? $product : array();
		$type       = (string) ( $product['type'] ?? '' );
		$attributes = isset( $product['attributes'] ) && is_array( $product['attributes'] ) ? $product['attributes'] : array();

		// A 'variation' member carrying attributes has open axes left to complete.
		if ( 'variable' !== $type && empty( $attributes ) ) {
			return array( 'variantShow' => false, 'variantAxes' => array(), 'variantMatrix' => '', 'variantInfo' => '', 'variantMode' => 'inline', 'variantSummary' => '', 'variantHead' => '' );
		}

		$axes   = array();
		$matrix = '';
		$names  = array();

		if ( $attributes ) {
			foreach ( $attributes as $attr ) {
				$name    = (string) ( $attr['name'] ?? '' );
				$names[] = $name;
				$options = isset( $attr['options'] ) && is_array( $attr['options'] ) ? array_values( $attr['options'] ) : array();
				$labels  = isset( $attr['labels'] ) && is_array( $attr['labels'] ) ? $attr['labels'] : array();
				// Resolved by Repository::default_option(); '' when none is set.
				$default = (string) ( $attr['default'] ?? '' );
				$axes[]  = self::axis(
					array_map(
						function ( $o ) use ( $name, $labels ) {
							return array( 'value' => $name . ':' . $o, 'label' => (string) ( $labels[ (string) $o ] ?? $o ) );
						},
						$options
					),
					'' === $default ? '' : $name . ':' . $default,
					$name
				);
			}
			// Constrains each dropdown to combinations that exist. See matrix().
			$matrix = self::matrix( $product );
		} else {
			$variations = isset( $product['variations'] ) && is_array( $product['variations'] ) ? array_values( $product['variations'] ) : array();
			if ( $variations ) {
				$axes[] = self::axis( array_map(
					function ( $v ) {
						return array( 'value' => (string) ( $v['id'] ?? '' ), 'label' => (string) ( $v['name'] ?? '' ) );
					},
					$variations
				) );
			}
		}

		// In auto mode more than two attributes counts as complex, so it pops up.
		$complex      = count( $attributes ) > 2;
		$variant_mode = empty( $axes ) ? 'inline' : Options_Display::variant_mode( $complex );

		// JSON_UNESCAPED_SLASHES to stay byte-identical to the preview's
		// JSON.stringify (admin/app/src/lib/skins/variants.ts), which never escapes `/`.
		$info = empty( $axes ) ? '' : (string) wp_json_encode( array(
			'name'  => (string) ( $product['name'] ?? '' ),
			'image' => self::image( (string) ( $product['image'] ?? '' ) ),
		), JSON_UNESCAPED_SLASHES );

		return array( 'variantShow' => ! empty( $axes ), 'variantAxes' => $axes, 'variantMatrix' => $matrix, 'variantInfo' => $info, 'variantMode' => $variant_mode, 'variantSummary' => self::summary( $axes ), 'variantHead' => in_array( '', $names, true ) ? '' : implode( ' · ', $names ) );
	}

	/**
	 * The image of the variation a row starts on, so the first paint matches the
	 * pre-selected dropdowns.
	 *
	 * @param array $product Hydrated product row.
	 * @return string '' when an axis has no default or the variation has no image.
	 */
	public static function start_image( $product ) {
		$attributes = is_array( $product['attributes'] ?? null ) ? $product['attributes'] : array();
		$variations = is_array( $product['variations'] ?? null ) ? $product['variations'] : array();
		if ( ! $attributes || ! $variations ) {
			return '';
		}
		$picked = array();
		foreach ( $attributes as $attr ) {
			$default = (string) ( $attr['default'] ?? '' );
			if ( '' === $default ) {
				return '';
			}
			$picked[ (string) ( $attr['name'] ?? '' ) ] = $default;
		}
		foreach ( $variations as $variation ) {
			if ( false === ( $variation['purchasable'] ?? true ) ) {
				continue;
			}
			$combo = is_array( $variation['attributes'] ?? null ) ? $variation['attributes'] : array();
			foreach ( $picked as $label => $value ) {
				$actual = (string) ( $combo[ $label ] ?? '' );
				if ( '' !== $actual && $actual !== $value ) {
					continue 2;
				}
			}
			return (string) ( $variation['image'] ?? '' );
		}
		return '';
	}

	/**
	 * The quick-view button's label before the script runs: the picked labels
	 * joined ("Red / L"), or "Select options" while any axis is unpicked.
	 *
	 * @param array $axes Axis scopes as built by axis().
	 * @return string '' when there are no axes.
	 */
	private static function summary( array $axes ) {
		if ( ! $axes ) {
			return '';
		}
		$parts = array();
		foreach ( $axes as $axis ) {
			$label = '';
			foreach ( (array) ( $axis['options'] ?? array() ) as $row ) {
				if ( ! empty( $row['selected'] ) && '' !== (string) ( $row['value'] ?? '' ) ) {
					$label = (string) ( $row['label'] ?? '' );
					break;
				}
			}
			if ( '' === $label ) {
				return Shopper_Text::get( 'selectOptions' );
			}
			$parts[] = $label;
		}
		return implode( ' / ', $parts );
	}

	/**
	 * A product image URL, falling back to the store's own placeholder so a
	 * merchant who swapped it gets theirs here too. '' outside WooCommerce.
	 *
	 * @param string $image Product image URL, possibly empty.
	 * @return string
	 */
	public static function image( $image ) {
		$image = (string) $image;
		if ( '' !== $image ) {
			return $image;
		}
		return function_exists( 'wc_placeholder_img_src' ) ? (string) wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '';
	}

	/**
	 * The buyable attribute combinations for a variable product, as a JSON array
	 * of `{ a:{ "{Label}": "{value}" }, img:"{url}", id:{int} }` entries. An empty
	 * combo value is WooCommerce's "any" wildcard.
	 *
	 * @param array $product Hydrated product row.
	 * @return string JSON, or '' when there is nothing to constrain.
	 */
	private static function matrix( array $product ) {
		$variations = isset( $product['variations'] ) && is_array( $product['variations'] ) ? $product['variations'] : array();
		$combos     = array();
		foreach ( $variations as $v ) {
			if ( empty( $v['purchasable'] ) ) {
				continue;
			}
			$combo = isset( $v['attributes'] ) && is_array( $v['attributes'] ) ? $v['attributes'] : array();
			if ( $combo ) {
				$combos[] = array(
					'a'   => $combo,
					'img' => (string) ( $v['image'] ?? '' ),
					// Aero Checkout resolves a row by variation id, not by attributes.
					'id'  => absint( $v['id'] ?? 0 ),
				);
			}
		}
		// JSON_UNESCAPED_SLASHES for the same reason as $info above.
		return $combos ? (string) wp_json_encode( $combos, JSON_UNESCAPED_SLASHES ) : '';
	}

	/**
	 * One dropdown's options, with a value-less placeholder first. It stays in the
	 * list even when a default matches, since the behavior layer reads an empty
	 * value as unresolved (`F.axesComplete`).
	 *
	 * @param array  $options Option rows with value and label.
	 * @param string $default Option value the merchant configured as the default.
	 * @param string $name    Attribute the dropdown picks, '' for a flat variation list.
	 * @return array
	 */
	private static function axis( array $options, $default = '', $name = '' ) {
		$default = (string) $default;
		$matched = false;
		$rows    = array();
		foreach ( array_values( $options ) as $opt ) {
			$value     = (string) ( $opt['value'] ?? '' );
			$is_default = '' !== $default && $value === $default;
			$matched   = $matched || $is_default;
			$rows[]    = array(
				'value'    => $value,
				'label'    => (string) ( $opt['label'] ?? '' ),
				'selected' => $is_default,
			);
		}
		array_unshift(
			$rows,
			array(
				'value'    => '',
				'label'    => '' !== $name ? sprintf( Shopper_Text::get( 'selectAxis' ), $name ) : Shopper_Text::get( 'chooseOption' ),
				'selected' => ! $matched,
			)
		);
		return array( 'options' => $rows );
	}
}
