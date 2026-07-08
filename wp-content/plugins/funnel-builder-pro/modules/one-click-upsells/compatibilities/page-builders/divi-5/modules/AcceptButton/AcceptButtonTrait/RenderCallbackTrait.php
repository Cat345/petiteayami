<?php
/**
 * AcceptButton::render_callback()
 *
 * @package WFOCU\Modules\AcceptButton
 * @since 1.0.0
 */

namespace WFOCU\Modules\AcceptButton\AcceptButtonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\Module\Options\Element\ModuleElements;
use WFOCU\Modules\AcceptButton\AcceptButton;

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
			$text_content = __( 'Yes, Add This To My Order', 'woofunnels-upstroke-one-click-upsell' );
		}

		return $text_content;
	}

	/**
	 * Extract subtitle content from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string Subtitle content.
	 */
	private static function extract_subtitle_content( array $attrs ): string {
		$subtitle_content = '';

		// Try to get subtitle from various attribute structures
		if ( isset( $attrs['subtitle']['innerContent']['desktop']['value'] ) ) {
			$subtitle_content = is_string( $attrs['subtitle']['innerContent']['desktop']['value'] )
				? $attrs['subtitle']['innerContent']['desktop']['value']
				: ( $attrs['subtitle']['innerContent']['desktop']['value']['text'] ?? '' );
		} elseif ( isset( $attrs['subtitle']['innerContent'] ) ) {
			$subtitle_content = is_string( $attrs['subtitle']['innerContent'] )
				? $attrs['subtitle']['innerContent']
				: '';
		} elseif ( isset( $attrs['subtitle'] ) && is_string( $attrs['subtitle'] ) ) {
			$subtitle_content = $attrs['subtitle'];
		}

		return $subtitle_content;
	}

	/**
	 * Get icon from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return array{ code: string, type: string, weight: string } Icon code, type ('fa' or 'divi'), and font weight.
	 */
	private static function get_icon( array $attrs ): array {
		$result = array(
			'code'   => '',
			'type'   => 'divi',
			'weight' => '400',
		);

		// Handle Divi 5 icon-picker format: icon.desktop.value can be string or object {unicode, type, weight}
		if ( isset( $attrs['icon']['desktop']['value'] ) ) {
			$icon_value = $attrs['icon']['desktop']['value'];

			// If it's already a string, return it
			if ( is_string( $icon_value ) ) {
				$result['code'] = $icon_value;
				return $result;
			}

			// If it's an array/object, try to extract the icon string and type
			if ( is_array( $icon_value ) ) {
				if ( isset( $icon_value['type'] ) ) {
					$result['type'] = $icon_value['type'];
				}
				if ( isset( $icon_value['weight'] ) ) {
					$result['weight'] = $icon_value['weight'];
				}

				if ( isset( $icon_value['unicode'] ) && is_string( $icon_value['unicode'] ) ) {
					$result['code'] = $icon_value['unicode'];
					return $result;
				}
				if ( isset( $icon_value['value'] ) && is_string( $icon_value['value'] ) ) {
					$result['code'] = $icon_value['value'];
					return $result;
				}
				if ( isset( $icon_value['icon'] ) && is_string( $icon_value['icon'] ) ) {
					$result['code'] = $icon_value['icon'];
					return $result;
				}
				if ( count( $icon_value ) === 1 && is_string( reset( $icon_value ) ) ) {
					$result['code'] = reset( $icon_value );
					return $result;
				}
			}
		}

		// Fallback: check if icon is a direct string
		if ( isset( $attrs['icon'] ) && is_string( $attrs['icon'] ) ) {
			$result['code'] = $attrs['icon'];
		}

		return $result;
	}

	/**
	 * Get icon alignment from attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Module attributes.
	 * @return string Icon alignment ('left' or 'right').
	 */
	private static function get_icon_align( array $attrs ): string {
		if ( isset( $attrs['iconAlign']['desktop']['value'] ) ) {
			return $attrs['iconAlign']['desktop']['value'];
		} elseif ( isset( $attrs['icon_align'] ) ) {
			return $attrs['icon_align'];
		} elseif ( isset( $attrs['iconAlign'] ) && is_string( $attrs['iconAlign'] ) ) {
			return $attrs['iconAlign'];
		}

		return 'left'; // Default to left
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
	 * Process icon HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $icon_code Icon code (HTML entity like &#xf0b1;).
	 * @param string $icon_type Icon type ('fa' or 'divi').
	 * @return string Icon HTML.
	 */
	private static function process_icon( string $icon_code, string $icon_type = 'divi' ): string {
		if ( empty( $icon_code ) ) {
			return '';
		}

		// FA icons: just decode the HTML entity directly.
		// et_pb_process_font_icon() maps FA unicode to wrong ETmodules codepoints.
		if ( 'fa' === $icon_type ) {
			return html_entity_decode( $icon_code, ENT_QUOTES | ENT_HTML401 );
		}

		// ETmodules (divi) icons: use Divi's processing function.
		if ( function_exists( 'et_pb_process_font_icon' ) ) {
			return html_entity_decode( et_pb_process_font_icon( $icon_code ), ENT_QUOTES | ENT_HTML401 );
		}

		return '';
	}

	/**
	 * Accept Button module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * @since 1.0.0
	 * @param array     $attrs                       Block attributes that were saved by VB.
	 * @param string    $content                     Block content.
	 * @param \WP_Block $block                       Parsed block object that being rendered.
	 * @param mixed     $elements                    ModuleElements instance (can be different types in different contexts).
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Accept Button module.
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
					$default_attrs = \ET\Builder\Packages\ModuleLibrary\ModuleRegistration::get_default_attrs( 'wfocu/accept-button' );
				} catch ( \Exception $e ) {
					// Continue without defaults
				}
			}

			// Merge defaults with current attributes (defaults are base, current overrides)
			if ( ! empty( $default_attrs ) ) {
				$attrs = array_replace_recursive( $default_attrs, $attrs );
			}

			// Get product key (with safe fallback)
			$product_key = self::get_product_key( $attrs );

			// Extract content
			$text_content     = self::extract_text_content( $attrs );
			$subtitle_content = self::extract_subtitle_content( $attrs );
			$icon_data        = self::get_icon( $attrs );
			$icon_code        = $icon_data['code'];
			$icon_type        = $icon_data['type'];
			$icon_weight      = $icon_data['weight'];
			$icon_align       = self::get_icon_align( $attrs );

			// Process icon HTML if icon exists
			$icon_html = '';
			if ( ! empty( $icon_code ) ) {
				$icon_html = self::process_icon( $icon_code, $icon_type );
			}

			// Determine icon CSS class and inline style based on type (FA vs ETmodules)
			$is_fa_icon = ( 'fa' === $icon_type );
			$icon_class = $is_fa_icon ? 'wfocu-button-icon wfocu-fa-icon' : 'wfocu-button-icon et-pb-icon';
			// FA icons need correct font-weight to select the right font file (regular=400, solid=900, brands=400)
			$icon_style = $is_fa_icon ? ' style="font-weight:' . esc_attr( $icon_weight ) . '"' : '';

			// Build content wrapper HTML
			$content_wrapper_children = '';

			// Add icon before text if icon_align is 'left'
			if ( 'left' === $icon_align && ! empty( $icon_html ) ) {
				$content_wrapper_children .= '<span class="' . esc_attr( $icon_class ) . '"' . $icon_style . '>' . $icon_html . '</span>';
			}

			// Add title text
			$content_wrapper_children .= '<span class="wfocu-button-text">' . do_shortcode( html_entity_decode( $text_content, ENT_QUOTES | ENT_HTML401 ) ) . '</span>';

			// Add icon after text if icon_align is 'right'
			if ( 'right' === $icon_align && ! empty( $icon_html ) ) {
				$content_wrapper_children .= '<span class="' . esc_attr( $icon_class ) . '"' . $icon_style . '>' . $icon_html . '</span>';
			}

			// Build content wrapper
			$content_wrapper = HTMLUtility::render(
				array(
					'tag'               => 'span',
					'attributes'        => array(
						'class' => 'wfocu-button-content-wrapper',
						'style' => 'display:block',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $content_wrapper_children,
				)
			);

			// Build subtitle
			$subtitle_html = '';
			if ( ! empty( $subtitle_content ) ) {
				$subtitle_html = HTMLUtility::render(
					array(
						'tag'               => 'span',
						'attributes'        => array(
							'class' => 'wfocu-button-subtitle',
							'style' => 'display:block',
						),
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => do_shortcode( html_entity_decode( $subtitle_content, ENT_QUOTES | ENT_HTML401 ) ),
					)
				);
			}

			// Get additional attributes from WFOCU
			$additional_attrs = '';
			if ( class_exists( 'WFOCU_Core' ) && WFOCU_Core()->template_loader && method_exists( WFOCU_Core()->template_loader, 'add_attributes_to_buy_button' ) ) {
				ob_start();
				WFOCU_Core()->template_loader->add_attributes_to_buy_button();
				$additional_attrs = ob_get_clean();
			}

			// Build button link HTML
			$button_link = HTMLUtility::render(
				array(
					'tag'               => 'a',
					'attributes'        => array_merge(
						array(
							'id'       => 'wfocu-accept-button-link',
							'class'    => 'wfocu_upsell wfocu-accept-button-link wfocu_paypal_in_context_btn',
							'href'     => '#',
							'onclick'  => 'return false;',
							'data-key' => $product_key,
						),
						// Parse additional attributes if they exist
						! empty( $additional_attrs ) ? self::parse_additional_attributes( $additional_attrs ) : array()
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $content_wrapper . $subtitle_html,
				)
			);

			// Build button wrapper
			$button_wrapper = HTMLUtility::render(
				array(
					'tag'               => 'div',
					'attributes'        => array(
						'class' => 'wfocu-button-wrapper',
					),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $button_link,
				)
			);

			// Add custom styles for icon
			$custom_styles = '';
			if ( ! empty( $icon_code ) ) {
				if ( $is_fa_icon ) {
					$custom_styles = '<style>
						#wfocu_accept_button .wfocu-fa-icon {
							font-family: "FontAwesome" !important;
						}
						.wfocu-button-content-wrapper span {
							display: inline !important;
						}
					</style>';
				} else {
					$custom_styles = '<style>
						#wfocu_accept_button .et-pb-icon {
							font-family: ETmodules !important;
						}
						.wfocu-button-content-wrapper span {
							display: inline !important;
						}
					</style>';
				}
			}

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
						'name'                => $block->block_type->name ?? 'wfocu/accept-button',
						'moduleCategory'      => $block->block_type->category ?? 'module',
						'classnamesFunction'  => array( AcceptButton::class, 'module_classnames' ),
						'stylesComponent'     => array( AcceptButton::class, 'module_styles' ),
						'scriptDataComponent' => array( AcceptButton::class, 'module_script_data' ),
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
							$custom_styles . $button_wrapper . $content,
						),
					)
				);
			} catch ( \Exception $e ) {
				// If Module::render() fails, return fallback HTML
				return $custom_styles . $button_wrapper;
			}
		} catch ( \Exception $e ) {
			// Return simple fallback HTML
			$text_content = self::extract_text_content( $attrs );
			$product_key  = self::get_product_key( $attrs );

			return '<div class="wfocu-button-wrapper"><a id="wfocu-accept-button-link" class="wfocu_upsell wfocu-accept-button-link wfocu_paypal_in_context_btn" href="javascript:void(0);" data-key="' . esc_attr( $product_key ) . '"><span class="wfocu-button-content-wrapper" style="display:block"><span class="wfocu-button-text">' . esc_html( $text_content ) . '</span></span></a></div>';
		}
	}

	/**
	 * Parse additional attributes string into array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $attrs_string Attributes string.
	 * @return array Parsed attributes.
	 */
	private static function parse_additional_attributes( string $attrs_string ): array {
		$parsed = array();
		// Simple parsing - extract key="value" pairs
		if ( preg_match_all( '/(\w+)="([^"]*)"/', $attrs_string, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$parsed[ $match[1] ] = $match[2];
			}
		}
		return $parsed;
	}
}
