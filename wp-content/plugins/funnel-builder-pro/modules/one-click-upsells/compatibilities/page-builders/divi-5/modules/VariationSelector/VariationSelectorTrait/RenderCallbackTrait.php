<?php
/**
 * VariationSelector::render_callback()
 *
 * @package WFOCU\Modules\VariationSelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\VariationSelector\VariationSelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\VariationSelector\VariationSelector;

trait RenderCallbackTrait {

	/**
	 * Get stacked enabled value from attributes (Divi 4: attr_value_block).
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return bool True if stacked is enabled, false otherwise.
	 */
	private static function get_stacked_enabled( array $attrs ): bool {
		$value = '';

		// Check attr_value_block (Divi 4 key) first
		if ( isset( $attrs['attr_value_block']['desktop']['value'] ) ) {
			$value = $attrs['attr_value_block']['desktop']['value'];
		} elseif ( isset( $attrs['attr_value_block']['desktop'] ) && is_string( $attrs['attr_value_block']['desktop'] ) ) {
			$value = $attrs['attr_value_block']['desktop'];
		} elseif ( isset( $attrs['attr_value_block'] ) && is_string( $attrs['attr_value_block'] ) ) {
			$value = $attrs['attr_value_block'];
		}

		// Handle toggle values: "on"/"Yes" = enabled, "off"/"No" = disabled (matching Divi 4)
		return $value === true || $value === 'on' || $value === 'Yes' || $value === '1' || $value === 'yes';
	}

	/**
	 * Get variation selector HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $product_key Product key.
	 * @param bool   $stacked_enabled Whether stacked layout is enabled.
	 * @return string HTML output.
	 */
	private static function get_variation_selector_html( string $product_key, bool $stacked_enabled ): string {
		if ( empty( $product_key ) || $product_key === '0' ) {
			return '';
		}

		// Build shortcode (matching Divi 4 line 153)
		$shortcode = sprintf(
			'[wfocu_variation_selector_form key="%s"]',
			esc_attr( $product_key )
		);

		// Render shortcode (matching Divi 4 line 153)
		ob_start();
		echo do_shortcode( $shortcode );
		$html = ob_get_clean();

		// Add class to form wrapper based on stacked setting (similar to Quantity Selector pattern)
		// The form has class .wfocu_variation_selector_form, we'll add inline/block class to it
		if ( ! empty( $html ) ) {
			// Determine wrapper class based on stacked setting (matching Quantity Selector pattern)
			$form_class = $stacked_enabled ? 'wfocu-variation-block' : 'wfocu-variation-inline';

			// Add class to the form wrapper if it exists in the HTML
			// The shortcode outputs a form with class .wfocu_variation_selector_form
			// We'll add our class to that form element using multiple regex patterns

			// Pattern 1: Form with class attribute containing wfocu_variation_selector_form
			$html = preg_replace(
				'/(<form[^>]*class=["\'])([^"\']*wfocu_variation_selector_form[^"\']*)(["\'][^>]*>)/i',
				'$1$2 ' . esc_attr( $form_class ) . '$3',
				$html
			);

			// Pattern 2: Form without class attribute, add class attribute
			if ( strpos( $html, 'wfocu_variation_selector_form' ) !== false && strpos( $html, $form_class ) === false ) {
				$html = preg_replace(
					'/(<form[^>]*>)/i',
					'<form class="wfocu_variation_selector_form ' . esc_attr( $form_class ) . '"$1',
					$html,
					1
				);
			}

			// Pattern 3: Add class to any element with wfocu_variation_selector_form class
			$html = preg_replace(
				'/(<[^>]*class=["\'][^"\']*wfocu_variation_selector_form[^"\']*)(["\'][^>]*>)/i',
				'$1 ' . esc_attr( $form_class ) . '$2',
				$html
			);

			// Fallback: if form/class not found, wrap the entire HTML
			if ( strpos( $html, 'wfocu_variation_selector_form' ) === false && strpos( $html, $form_class ) === false ) {
				$html = '<div class="wfocu_variation_selector_form ' . esc_attr( $form_class ) . '">' . $html . '</div>';
			}
		}

		return $html;
	}

	/**
	 * Variation Selector module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Variation Selector module.
	 */
	public static function render_callback( array $attrs, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {

			// Ensure attrs is an array
			if ( ! is_array( $attrs ) ) {
				$attrs = array();
			}

			// Ensure block and parsed_block exist
			if ( ! isset( $block->parsed_block ) || ! isset( $block->block_type ) ) {
				throw new \Exception( 'Invalid block data: missing parsed_block or block_type' );
			}

			// CRITICAL: Merge defaults from module.json BEFORE processing attributes
			// This ensures empty values get filled with defaults from module.json
			$default_attrs = array();
			if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
				try {
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/variation-selector' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			// This ensures defaults from module.json are always applied, even when attributes are empty
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Ensure product data is loaded (handles REST API / SSR context).
			\WFOCU\Modules\ModuleRegistry::ensure_product_data();

			// Check if product data is available (matching Divi 4 line 110)
			if ( ! class_exists( 'WFOCU_Core' ) || ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}

			// Get product data first (matching Divi 4 line 114)
			$product_data = WFOCU_Core()->template_loader->product_data->products;

			// Extract selected product from attributes (matching Divi 4 line 116: $this->props['selected_product'])
			// Check both camelCase (Divi 5) and snake_case (Divi 4 compatibility) attribute names
			$sel_product = '';
			if ( isset( $attrs['selectedProduct']['desktop']['value'] ) ) {
				$sel_product = $attrs['selectedProduct']['desktop']['value'];
			} elseif ( isset( $attrs['selectedProduct']['desktop'] ) && is_string( $attrs['selectedProduct']['desktop'] ) ) {
				$sel_product = $attrs['selectedProduct']['desktop'];
			} elseif ( isset( $attrs['selectedProduct'] ) && is_string( $attrs['selectedProduct'] ) ) {
				$sel_product = $attrs['selectedProduct'];
			} elseif ( isset( $attrs['selected_product']['desktop']['value'] ) ) { // Divi 4 compatibility
				$sel_product = $attrs['selected_product']['desktop']['value'];
			} elseif ( isset( $attrs['selected_product']['desktop'] ) && is_string( $attrs['selected_product']['desktop'] ) ) {
				$sel_product = $attrs['selected_product']['desktop'];
			} elseif ( isset( $attrs['selected_product'] ) && is_string( $attrs['selected_product'] ) ) {
				$sel_product = $attrs['selected_product'];
			}

			// Get product key using default_product_key (matching Divi 4 line 117)
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			// Verify product exists (matching Divi 4 lines 119-125)
			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}

			if ( ! $product instanceof \WC_Product ) {
				return '';
			}

			// Check if product is variable (matching Divi 4 lines 127-138)
			// Divi 4 checks for both 'variable' and 'variable-subscription' types (line 24)
			$is_variable = false;
			if ( ! empty( $product_key ) && $product instanceof \WC_Product ) {
				$product_type = $product->get_type();
				$is_variable  = in_array( $product_type, array( 'variable', 'variable-subscription' ), true );
			}

			// If product is not variable, return empty (matching Divi 4 line 137)
			if ( false === $is_variable ) {
				return '';
			}

			// Get stacked enabled
			$stacked_enabled = self::get_stacked_enabled( $attrs );

			// Get variation selector HTML (matching Divi 4 lines 140-157)
			// Note: Divi 4 checks product_key after getting stacked_enabled, so we do the same
			if ( empty( $product_key ) ) {
				return '';
			}

			$variation_selector_html = self::get_variation_selector_html( $product_key, $stacked_enabled );

			// Get parent block (following Divi 5 pattern)
			$parent       = null;
			$parent_attrs = array();
			$parent_id    = '';
			$parent_name  = '';

			if ( isset( $block->parsed_block['id'] ) && isset( $block->parsed_block['storeInstance'] ) ) {
				try {
					$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
					if ( $parent ) {
						$parent_attrs = $parent->attrs ?? array();
						$parent_id    = $parent->id ?? '';
						$parent_name  = $parent->blockName ?? '';
					}
				} catch ( \Exception $e ) {
					// Parent not found, continue without parent data
				}
			}

			// Use Module::render() following Divi 5 pattern
			try {
				return Module::render(
					array(
						// FE only.
						'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
						'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,

						// VB equivalent.
						'attrs'               => $attrs,
						'elements'            => $elements,
						'id'                  => $block->parsed_block['id'] ?? '',
						'name'                => $block->block_type->name ?? 'wfocu/variation-selector',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( VariationSelector::class, 'module_classnames' ),
						'stylesComponent'     => array( VariationSelector::class, 'module_styles' ),
						'scriptDataComponent' => array( VariationSelector::class, 'module_script_data' ),
						'parentAttrs'         => $parent_attrs,
						'parentId'            => $parent_id,
						'parentName'          => $parent_name,
						'children'            => array(
							ElementComponents::component(
								array(
									'attrs'         => $attrs['module']['decoration'] ?? array(),
									'id'            => $block->parsed_block['id'] ?? '',

									// FE only.
									'orderIndex'    => $block->parsed_block['orderIndex'] ?? 0,
									'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
								)
							),
							$variation_selector_html . $content,
						),
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $variation_selector_html;
			}
		} catch ( \Exception $e ) {
			// Return empty string on error (matches Divi 4 behavior when product is not variable)
			return '';
		}
	}
}
