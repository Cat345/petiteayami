<?php
/**
 * RejectLink::render_callback()
 *
 * @package WFOCU\Modules\RejectLink
 * @since 1.0.0
 */

namespace WFOCU\Modules\RejectLink\RejectLinkTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\RejectLink\RejectLink;

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
			$text_content = __( 'No thanks, I don\'t want to take advantage of this one-time offer', 'woofunnels-upstroke-one-click-upsell' );
		}

		return $text_content;
	}

	/**
	 * Reject Link module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Reject Link module.
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
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/reject-link' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			// This ensures defaults from module.json are always applied, even when attributes are empty
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Build the reject link ourselves instead of relying on $elements->render(),
			// because Divi 5's HTMLUtility strips javascript: protocol from href
			// attributes, leaving href="" which navigates to the current page on click.
			$text_content = self::extract_text_content( $attrs );

			$text_html = HTMLUtility::render(
				array(
					'tag'               => 'a',
					'attributes'        => array(
						'class'   => 'wfocu-reject wfocu_skip_offer',
						'href'    => '#',
						'onclick' => 'return false;',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $text_content,
				)
			);

			// Ensure we have valid HTML
			if ( empty( $text_html ) ) {
				$text_html = '<a class="wfocu-reject wfocu_skip_offer" href="javascript:void(0);">' . esc_html( __( 'No thanks, I don\'t want to take advantage of this one-time offer', 'woofunnels-upstroke-one-click-upsell' ) ) . '</a>';
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
			return Module::render(
				array(
					// FE only.
					'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
					'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,

					// VB equivalent.
					'attrs'               => $attrs,
					'elements'            => $elements,
					'id'                  => $block->parsed_block['id'] ?? '',
					'name'                => $block->block_type->name ?? 'wfocu/reject-link',
					'moduleCategory'      => $block->block_type->category ?? 'module',
					'classnamesFunction'  => array( RejectLink::class, 'module_classnames' ),
					'stylesComponent'     => array( RejectLink::class, 'module_styles' ),
					'scriptDataComponent' => array( RejectLink::class, 'module_script_data' ),
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
			// Return simple fallback HTML
			$text_content = self::extract_text_content( $attrs );

			return '<div class="wfocu-button-wrapper"><a class="wfocu-reject wfocu_skip_offer" href="javascript:void(0);">' . esc_html( $text_content ) . '</a></div>';
		}
	}
}
