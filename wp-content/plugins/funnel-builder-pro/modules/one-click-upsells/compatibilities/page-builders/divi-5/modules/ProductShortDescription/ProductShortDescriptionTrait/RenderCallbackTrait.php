<?php
/**
 * ProductShortDescription::render_callback()
 *
 * @package WFOCU\Modules\ProductShortDescription
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductShortDescription\ProductShortDescriptionTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\ProductShortDescription\ProductShortDescription;

trait RenderCallbackTrait {

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
	 * Get product short description from WFOCU.
	 * Matching Divi 4 implementation (lines 48-79).
	 *
	 * @since 1.0.0
	 *
	 * @param string $product_key Product key.
	 * @return string Product short description or empty string.
	 */
	private static function get_product_short_description( string $product_key ): string {
		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader ) {
			return '';
		}

		if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
			return '';
		}

		$product_data = WFOCU_Core()->template_loader->product_data->products;

		$product = '';
		if ( isset( $product_data->{$product_key} ) ) {
			$product = $product_data->{$product_key}->data;
		}

		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		// Get post object (matching Divi 4 line 64)
		$post_object = get_post( $product->get_id() );

		// Get description from post_excerpt (matching Divi 4 line 66)
		$description = $post_object->post_excerpt ?? '';

		// Handle product variations (matching Divi 4 lines 67-72)
		if ( 'product_variation' === ( $post_object->post_type ?? '' ) ) {
			$product = wc_get_product( $product->get_id() );
			if ( $product instanceof \WC_Product ) {
				$description = $product->get_description();
			}
		}

		// Apply WooCommerce filter (matching Divi 4 line 74)
		$short_description = apply_filters( 'woocommerce_short_description', $description );

		return $short_description;
	}

	/**
	 * Product Short Description module render callback which outputs server side rendered HTML on the Front-End.
	 * Matching Divi 4 implementation (lines 48-85).
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Product Short Description module.
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

			// CRITICAL: Merge defaults from module.json BEFORE processing
			// This ensures empty values get filled with defaults from module.json
			$default_attrs = array();
			if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
				try {
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/product-short-description' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Get product key and short description
			$product_key       = self::get_product_key( $attrs );
			$short_description = self::get_product_short_description( $product_key );

			// Return early if description is empty (matching Divi 4 lines 76-78)
			if ( empty( $short_description ) ) {
				return '';
			}

			// Build wrapper HTML (matching Divi 4 lines 81-83: <div class="wfocu-widget-container">)
			$wrapper_html = HTMLUtility::render(
				array(
					'tag'               => 'div',
					'attributes'        => array(
						'class' => 'wfocu-widget-container',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => do_shortcode( html_entity_decode( $short_description, ENT_QUOTES | ENT_HTML401 ) ),
				)
			);

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
						'name'                => $block->block_type->name ?? 'wfocu/product-short-description',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( ProductShortDescription::class, 'module_classnames' ),
						'stylesComponent'     => array( ProductShortDescription::class, 'module_styles' ),
						'scriptDataComponent' => array( ProductShortDescription::class, 'module_script_data' ),
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
							$wrapper_html . $content,
						),
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $wrapper_html;
			}
		} catch ( \Exception $e ) {
			// Return simple fallback HTML (matching Divi 4 structure)
			$product_key       = self::get_product_key( $attrs );
			$short_description = self::get_product_short_description( $product_key );

			if ( empty( $short_description ) ) {
				return '';
			}

			return '<div class="wfocu-widget-container">' . wp_kses_post( $short_description ) . '</div>';
		}
	}
}
