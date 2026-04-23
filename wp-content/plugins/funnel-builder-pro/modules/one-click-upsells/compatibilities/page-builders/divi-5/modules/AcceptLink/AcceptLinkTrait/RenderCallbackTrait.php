<?php
/**
 * AcceptLink::render_callback()
 *
 * @package WFOCU\Modules\AcceptLink
 * @since 1.0.0
 */

namespace WFOCU\Modules\AcceptLink\AcceptLinkTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\AcceptLink\AcceptLink;

trait RenderCallbackTrait {

	/**
	 * Extract text content from attributes with fallback to default.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string Text content.
	 */
	private static function extract_text_content( array $attrs ): string {
		$text_content = '';

		// Try to get text from various attribute structures
		if ( isset( $attrs['text']['innerContent']['desktop']['value'] ) ) {
			$text_content = is_string( $attrs['text']['innerContent']['desktop']['value'] )
				? $attrs['text']['innerContent']['desktop']['value']
				: ( $attrs['text']['innerContent']['desktop']['value']['text'] ?? '' );
		} elseif ( isset( $attrs['text']['innerContent'] ) ) {
			$text_content = is_string( $attrs['text']['innerContent'] )
				? $attrs['text']['innerContent']
				: '';
		}

		// Fallback to default if empty
		if ( empty( $text_content ) ) {
			$text_content = __( 'Accept this offer', 'woofunnels-upstroke-one-click-upsell' );
		}

		return $text_content;
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
	 * Accept Link module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Accept Link module.
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
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/accept-link' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			// This ensures defaults from module.json are always applied, even when attributes are empty
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Get product key (with safe fallback)
			$product_key = self::get_product_key( $attrs );

			// Render text element using elements->render() (following Divi 5 pattern)
			// If elements->render() fails, fall back to manual rendering
			$text_html = '';
			try {
				// Try to use elements->render() first (like RejectLink)
				$rendered_text = $elements->render(
					array(
						'attrName' => 'text',
					)
				);

				// If rendering succeeded, we need to add data-key attribute
				// Extract text content to rebuild with data-key
				$text_content = self::extract_text_content( $attrs );

				// Create link HTML with data-key attribute (matching Divi 4 structure)
				$text_html = HTMLUtility::render(
					array(
						'tag'               => 'a',
						'attributes'        => array(
							'class'    => 'wfocu-wfocu-accept wfocu_upsell wfocu_paypal_in_context_btn',
							'href'     => '#',
							'onclick'  => 'return false;',
							'data-key' => $product_key,
						),
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => $text_content,
					)
				);
			} catch ( \Exception $e ) {
				// Fallback: extract text manually if elements->render() fails
				// Extract text content using helper method
				$text_content = self::extract_text_content( $attrs );

				// Create link HTML manually with data-key attribute
				$text_html = HTMLUtility::render(
					array(
						'tag'               => 'a',
						'attributes'        => array(
							'class'    => 'wfocu-wfocu-accept wfocu_upsell wfocu_paypal_in_context_btn',
							'href'     => '#',
							'onclick'  => 'return false;',
							'data-key' => $product_key,
						),
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => $text_content,
					)
				);
			}

			// Ensure we have valid HTML
			if ( empty( $text_html ) ) {
				$text_content = self::extract_text_content( $attrs );
				$text_html    = '<a class="wfocu-wfocu-accept wfocu_upsell wfocu_paypal_in_context_btn" href="javascript:void(0);" data-key="' . esc_attr( $product_key ) . '">' . esc_html( $text_content ) . '</a>';
			}

			// Wrap text in button wrapper div
			$button_wrapper = HTMLUtility::render(
				array(
					'tag'               => 'div',
					'attributes'        => array(
						'class' => 'wfocu-button-wrapper',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $text_html,
				)
			);

			// Get parent block (following Divi 5 pattern) - matching RejectLink exactly
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

			// Use Module::render() following Divi 5 pattern (matching RejectLink)
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
						'name'                => $block->block_type->name ?? 'wfocu/accept-link',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( AcceptLink::class, 'module_classnames' ),
						'stylesComponent'     => array( AcceptLink::class, 'module_styles' ),
						'scriptDataComponent' => array( AcceptLink::class, 'module_script_data' ),
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
							$button_wrapper . $content,
						),
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $button_wrapper;
			}
		} catch ( \Exception $e ) {
			// Return simple fallback HTML
			$text_content = self::extract_text_content( $attrs );
			$product_key  = self::get_product_key( $attrs );

			return '<div class="wfocu-button-wrapper"><a class="wfocu-wfocu-accept wfocu_upsell wfocu_paypal_in_context_btn" href="javascript:void(0);" data-key="' . esc_attr( $product_key ) . '">' . esc_html( $text_content ) . '</a></div>';
		}
	}
}
