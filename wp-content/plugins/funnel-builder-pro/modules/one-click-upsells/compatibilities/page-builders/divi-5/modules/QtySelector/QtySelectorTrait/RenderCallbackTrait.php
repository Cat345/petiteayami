<?php
/**
 * QtySelector::render_callback()
 *
 * @package WFOCU\Modules\QtySelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\QtySelector\QtySelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFACP_Common;
use WFOCU\Modules\QtySelector\QtySelector;

trait RenderCallbackTrait {

	/**
	 * Check if quantity selector is enabled for the offer.
	 *
	 * @since 1.0.0
	 * @return bool True if enabled, false otherwise.
	 */
	private static function is_qty_selector_enabled(): bool {
		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader ) {
			return false;
		}

		$offer_id = WFOCU_Core()->template_loader->get_offer_id();
		if ( empty( $offer_id ) ) {
			return false;
		}

		$offer_settings = get_post_meta( $offer_id, '_wfocu_setting', true );
		if ( ! $offer_settings ) {
			return false;
		}

		$offer_setting        = isset( $offer_settings->settings ) ? (object) $offer_settings->settings : new \stdClass();
		$qty_selector_enabled = isset( $offer_setting->qty_selector ) ? $offer_setting->qty_selector : false;

		return $qty_selector_enabled === true || $qty_selector_enabled === 'on' || $qty_selector_enabled === '1';
	}

	/**
	 * Get product key from selected product.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string Product key.
	 */
	private static function get_product_key( array $attrs ): string {
		\WFOCU\Modules\ModuleRegistry::ensure_product_data();

		// Safely extract selected product with multiple fallback methods
		$selected_product = '';

		if ( isset( $attrs['selectedProduct']['desktop']['value'] ) ) {
			$selected_product = $attrs['selectedProduct']['desktop']['value'];
		} elseif ( isset( $attrs['selectedProduct']['desktop'] ) && is_string( $attrs['selectedProduct']['desktop'] ) ) {
			$selected_product = $attrs['selectedProduct']['desktop'];
		} elseif ( isset( $attrs['selectedProduct'] ) && is_string( $attrs['selectedProduct'] ) ) {
			$selected_product = $attrs['selectedProduct'];
		}

		if ( empty( $selected_product ) ) {
			$selected_product = '0';
		}

		// Get product key from WFOCU template loader
		if ( class_exists( 'WFOCU_Core' ) ) {
			$wfocu_core = WFOCU_Core();
			if ( $wfocu_core && isset( $wfocu_core->template_loader ) && method_exists( $wfocu_core->template_loader, 'default_product_key' ) ) {
				try {
					$product_key = $wfocu_core->template_loader->default_product_key( $selected_product );

					// Verify the key exists in the offer's products — template imports
					// may have stale hashes that don't match the current offer config.
					if ( ! empty( $product_key ) && isset( $wfocu_core->template_loader->product_data->products ) ) {
						$products = (array) $wfocu_core->template_loader->product_data->products;
						if ( ! isset( $products[ $product_key ] ) ) {
							$product_key = $wfocu_core->template_loader->default_product_key( 0 );
						}
					}

					if ( ! empty( $product_key ) ) {
						return $product_key;
					}
				} catch ( \Exception $e ) {
					// Continue with fallback
				}
			}
		}

		return $selected_product;
	}

	/**
	 * Get text value from attributes with fallback to default.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string Text value.
	 */
	private static function get_text( array $attrs ): string {
		$text_value = '';

		// Try to get text from various attribute structures
		if ( isset( $attrs['text']['desktop']['value'] ) ) {
			$text_value = is_string( $attrs['text']['desktop']['value'] )
				? $attrs['text']['desktop']['value']
				: '';
		} elseif ( isset( $attrs['text'] ) && is_string( $attrs['text'] ) ) {
			$text_value = $attrs['text'];
		}

		// Fallback to default if empty
		if ( empty( $text_value ) ) {
			$text_value = __( 'Quantity', 'woofunnels-upstroke-one-click-upsell' );
		}

		return $text_value;
	}

	/**
	 * Get stacked enabled value from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return bool True if stacked is enabled, false otherwise.
	 */
	private static function get_stacked_enabled( array $attrs ): bool {
		if ( isset( $attrs['stackedEnabled']['desktop']['value'] ) ) {
			$value = $attrs['stackedEnabled']['desktop']['value'];
			// Handle toggle values: "on" = enabled, "off" = disabled (matching Product Images pattern)
			return $value === true || $value === 'on' || $value === '1' || $value === 'yes';
		}

		return false;
	}

	/**
	 * Get quantity selector HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $product_key Product key.
	 * @param string $text_value Text value for label.
	 * @param bool   $stacked_enabled Whether stacked layout is enabled.
	 * @return string HTML output.
	 */
	private static function get_qty_selector_html( string $product_key, string $text_value, bool $stacked_enabled ): string {
		if ( empty( $product_key ) || $product_key === '0' ) {
			return '';
		}

		// Determine wrapper class based on stacked setting (matching Divi 4 lines 120-123)
		$wrapper_class = $stacked_enabled ? 'wfocu_proqty_block' : 'wfocu_proqty_inline';

		// Build shortcode (matching Divi 4 line 136)
		$shortcode = sprintf(
			'[wfocu_qty_selector key="%s" label="%s"]',
			esc_attr( $product_key ),
			esc_attr( $text_value )
		);

		// Render shortcode (matching Divi 4 - no empty check before wrapping)
		$shortcode_output = do_shortcode( $shortcode );

		// Wrap in div with appropriate class (matching Divi 4 lines 135-137)
		// Note: Divi 4 doesn't check if shortcode_output is empty before wrapping
		// The shortcode itself will return empty if conditions aren't met
		return HTMLUtility::render(
			array(
				'tag'               => 'div',
				'attributes'        => array(
					'class' => $wrapper_class,
				),
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $shortcode_output,
			)
		);
	}

	/**
	 * Qty Selector module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Qty Selector module.
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
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/qty-selector' );
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

			// Check if product data is available (matching Divi 4 line 94)
			if ( ! class_exists( 'WFOCU_Core' ) || ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return '';
			}

			// Get product data first (matching Divi 4 line 98)
			$product_data = WFOCU_Core()->template_loader->product_data->products;

			// Extract selected product from attributes (matching Divi 4 line 99: $this->props['selected_product'])
			$sel_product = '';
			if ( isset( $attrs['selectedProduct']['desktop']['value'] ) ) {
				$sel_product = $attrs['selectedProduct']['desktop']['value'];
			} elseif ( isset( $attrs['selectedProduct']['desktop'] ) && is_string( $attrs['selectedProduct']['desktop'] ) ) {
				$sel_product = $attrs['selectedProduct']['desktop'];
			} elseif ( isset( $attrs['selectedProduct'] ) && is_string( $attrs['selectedProduct'] ) ) {
				$sel_product = $attrs['selectedProduct'];
			}

			// Get product key using default_product_key (matching Divi 4 line 100)
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			// Verify product exists (matching Divi 4 lines 102-109)
			$product = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}

			if ( ! $product instanceof \WC_Product ) {
				return '';
			}

			// Get text and stacked enabled
			$text_value      = self::get_text( $attrs );
			$stacked_enabled = self::get_stacked_enabled( $attrs );

			// Check if quantity selector is enabled for the offer (matching Divi 4 lines 111-118)
			if ( ! self::is_qty_selector_enabled() ) {
				// Return empty string if not enabled (matches Divi 4 behavior)
				return '';
			}

			// Get quantity selector HTML (matching Divi 4 lines 134-139)
			// Note: Divi 4 only checks if product_key is not empty before rendering
			// The shortcode itself handles empty output scenarios
			if ( empty( $product_key ) ) {
				return '';
			}

			$qty_selector_html = self::get_qty_selector_html( $product_key, $text_value, $stacked_enabled );

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
						'name'                => $block->block_type->name ?? 'wfocu/qty-selector',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( QtySelector::class, 'module_classnames' ),
						'stylesComponent'     => array( QtySelector::class, 'module_styles' ),
						'scriptDataComponent' => array( QtySelector::class, 'module_script_data' ),
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
							$qty_selector_html . $content,
						),
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $qty_selector_html;
			}
		} catch ( \Exception $e ) {
			// Return empty string on error (matches Divi 4 behavior when qty selector is disabled)
			return '';
		}
	}
}
