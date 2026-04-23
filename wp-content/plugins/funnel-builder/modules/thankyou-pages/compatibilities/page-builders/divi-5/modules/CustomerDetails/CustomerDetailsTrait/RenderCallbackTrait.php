<?php
/**
 * CustomerDetails::render_callback()
 *
 * @package WFTY\Modules\CustomerDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\CustomerDetails\CustomerDetailsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use WFTY\Modules\CustomerDetails\CustomerDetails;

trait RenderCallbackTrait {

	private static function extract_text( array $attrs, string $key, string $default = '' ): string {
		if ( isset( $attrs[ $key ]['innerContent']['desktop']['value'] ) ) {
			$v = $attrs[ $key ]['innerContent']['desktop']['value'];
			return is_string( $v ) ? $v : ( $v['text'] ?? $default );
		}
		if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
			return $attrs[ $key ];
		}
		return $default;
	}

	private static function extract_boolean( array $attrs, string $key, bool $default = false ): bool {
		if ( isset( $attrs[ $key ]['innerContent']['desktop']['value'] ) ) {
			$v = $attrs[ $key ]['innerContent']['desktop']['value'];
			return is_bool( $v ) ? $v : ( $v === 'on' || $v === true );
		}
		return $default;
	}

	/**
	 * Render callback for Customer Details module.
	 *
	 * @param array     $block_attributes Block attributes.
	 * @param string    $content          Block content.
	 * @param \WP_Block $block            Block object.
	 * @param object    $elements         ModuleElements instance.
	 * @param array     $default_printed_style_attrs Default printed style attributes.
	 * @return string
	 */
	public static function render_callback( array $block_attributes, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {
			$attrs                = is_array( $block_attributes ) ? $block_attributes : array();
			$heading              = self::extract_text( $attrs, 'heading', __( 'Customer Details', 'funnel-builder' ) );
			$customer_layout      = self::extract_text( $attrs, 'customer_layout', '2c' );
			$enable_extra_content = self::extract_boolean( $attrs, 'enable_extra_content', false );
			$layout_settings      = ( '2c' !== $customer_layout ) ? ' wfty_full_width' : '2c';
			if ( '' !== $layout_settings && '2c' !== $layout_settings ) {
				$layout_settings .= ' wfty_cont_style';
			}
			$enable_extra_content_str = $enable_extra_content ? 'yes' : 'no';
			$shortcode_html           = do_shortcode( '[wfty_customer_details layout_settings="' . esc_attr( $layout_settings ) . '" customer_details_heading="' . esc_attr( $heading ) . '" enable_extra_content="' . esc_attr( $enable_extra_content_str ) . '"]' );
			$wrapper                  = HTMLUtility::render(
				array(
					'tag'               => 'div',
					'attributes'        => array( 'id' => 'wfty_customer_details' ),
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $shortcode_html,
				)
			);
			return Module::render(
				array(
					'orderIndex'          => $block->parsed_block['orderIndex'] ?? 0,
					'storeInstance'       => $block->parsed_block['storeInstance'] ?? null,
					'attrs'               => $attrs,
					'elements'            => $elements,
					'id'                  => $block->parsed_block['id'] ?? '',
					'name'                => $block->block_type->name ?? 'wfty/customer-details',
					'moduleCategory'      => $block->block_type->category ?? 'module',
					'classnamesFunction'  => array( CustomerDetails::class, 'module_classnames' ),
					'stylesComponent'     => array( CustomerDetails::class, 'module_styles' ),
					'scriptDataComponent' => array( CustomerDetails::class, 'module_script_data' ),
					'parentAttrs'         => array(),
					'parentId'            => '',
					'parentName'          => '',
					'children'            => array(
						ElementComponents::component(
							array(
								'attrs'         => $attrs['module']['decoration'] ?? array(),
								'id'            => $block->parsed_block['id'] ?? '',
								'orderIndex'    => $block->parsed_block['orderIndex'] ?? 0,
								'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
							)
						),
						$wrapper,
					),
				)
			);
		} catch ( \Exception $e ) {
			return '<div id="wfty_customer_details">' . do_shortcode( '[wfty_customer_details layout_settings="2c" customer_details_heading="' . esc_attr__( 'Customer Details', 'funnel-builder' ) . '" enable_extra_content="no"]' ) . '</div>';
		}
	}
}
