<?php
/**
 * ProductTitle::render_callback()
 *
 * @package WFOCU\Modules\ProductTitle
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductTitle\ProductTitleTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\ProductTitle\ProductTitle;

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
	 * Get HTML tag from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string HTML tag (default: 'div').
	 */
	private static function get_html_tag( array $attrs ): string {
		// Valid HTML tags
		$valid_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' );

		if ( isset( $attrs['htmlTag']['desktop']['value'] ) ) {
			$tag = $attrs['htmlTag']['desktop']['value'];
			if ( in_array( $tag, $valid_tags, true ) ) {
				return $tag;
			}
		} elseif ( isset( $attrs['htmlTag']['desktop'] ) && is_string( $attrs['htmlTag']['desktop'] ) ) {
			$tag = $attrs['htmlTag']['desktop'];
			if ( in_array( $tag, $valid_tags, true ) ) {
				return $tag;
			}
		} elseif ( isset( $attrs['htmlTag'] ) && is_string( $attrs['htmlTag'] ) ) {
			$tag = $attrs['htmlTag'];
			if ( in_array( $tag, $valid_tags, true ) ) {
				return $tag;
			}
		}

		return 'div'; // Default to div (matching Divi 4)
	}

	/**
	 * Get product title from WFOCU.
	 *
	 * @since 1.0.0
	 *
	 * @param string $product_key Product key.
	 * @return string Product title or fallback.
	 */
	private static function get_product_title( string $product_key ): string {
		$title = __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' );

		if ( ! class_exists( 'WFOCU_Core' ) || ! WFOCU_Core()->template_loader ) {
			return $title;
		}

		if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
			return $title;
		}

		$product_data = WFOCU_Core()->template_loader->product_data->products;

		$product = '';
		if ( isset( $product_data->{$product_key} ) ) {
			$product = $product_data->{$product_key}->data;
		}

		if ( $product instanceof \WC_Product ) {
			$title = $product->get_title();
		}

		return $title;
	}

	/**
	 * Product Title module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Product Title module.
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
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/product-title' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Get product key and title
			$product_key   = self::get_product_key( $attrs );
			$product_title = self::get_product_title( $product_key );

			// Get HTML tag
			$html_tag = self::get_html_tag( $attrs );

			// Return early if title is empty
			if ( empty( $product_title ) ) {
				return '';
			}

			// Build title HTML (matching Divi 4: sprintf('<%s class="wfocu-product-title">%s</%s>', $html_tag, $title, $html_tag))
			$title_html = HTMLUtility::render(
				array(
					'tag'               => $html_tag,
					'attributes'        => array(
						'class' => 'wfocu-product-title',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => do_shortcode( html_entity_decode( $product_title, ENT_QUOTES | ENT_HTML401 ) ),
				)
			);

			// Build wrapper HTML (matching Divi 4: <div class="wfocu-product-title-wrapper">)
			$wrapper_html = HTMLUtility::render(
				array(
					'tag'               => 'div',
					'attributes'        => array(
						'class' => 'wfocu-product-title-wrapper',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $title_html,
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
						'name'                => $block->block_type->name ?? 'wfocu/product-title',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( ProductTitle::class, 'module_classnames' ),
						'stylesComponent'     => array( ProductTitle::class, 'module_styles' ),
						'scriptDataComponent' => array( ProductTitle::class, 'module_script_data' ),
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
			// Return simple fallback HTML
			$product_key   = self::get_product_key( $attrs );
			$product_title = self::get_product_title( $product_key );
			$html_tag      = self::get_html_tag( $attrs );

			if ( empty( $product_title ) ) {
				return '';
			}

			return '<div class="wfocu-product-title-wrapper"><' . esc_attr( $html_tag ) . ' class="wfocu-product-title">' . esc_html( $product_title ) . '</' . esc_attr( $html_tag ) . '></div>';
		}
	}
}
