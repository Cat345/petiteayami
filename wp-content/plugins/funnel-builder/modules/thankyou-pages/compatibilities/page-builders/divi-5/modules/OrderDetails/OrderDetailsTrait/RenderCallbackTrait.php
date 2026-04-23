<?php
/**
 * OrderDetails::render_callback()
 *
 * @package WFTY\Modules\OrderDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\OrderDetails\OrderDetailsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Module;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use WFTY\Modules\OrderDetails\OrderDetails;

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

	public static function render_callback( array $block_attributes, string $content, \WP_Block $block, $elements, array $default_printed_style_attrs = array() ): string {
		try {
			$attrs                       = is_array( $block_attributes ) ? $block_attributes : array();
			$order_heading_text          = self::extract_text( $attrs, 'order_details_heading', __( 'Order Details', 'funnel-builder' ) );
			$order_subscription_heading  = self::extract_text( $attrs, 'order_subscription_heading', __( 'Subscription', 'funnel-builder' ) );
			$order_download_heading      = self::extract_text( $attrs, 'order_download_heading', __( 'Downloads', 'funnel-builder' ) );
			$download_btn_text           = self::extract_text( $attrs, 'order_downloads_btn_text', __( 'Download', 'funnel-builder' ) );
			$order_details_img           = self::extract_boolean( $attrs, 'order_details_img', true );
			$show_column_download        = self::extract_boolean( $attrs, 'order_downloads_file', false );
			$show_column_file_expiry     = self::extract_boolean( $attrs, 'order_downloads_file_expiry', false );
			$order_details_img_str       = $order_details_img ? 'true' : 'false';
			$show_column_download_str    = $show_column_download ? 'true' : 'false';
			$show_column_file_expiry_str = $show_column_file_expiry ? 'true' : 'false';
			$shortcode_html              = do_shortcode(
				'[wfty_order_details order_details_img="' . $order_details_img_str . '" order_details_heading="' . esc_attr( $order_heading_text ) . '" order_subscription_heading="' . esc_attr( $order_subscription_heading ) . '" order_download_heading="' . esc_attr( $order_download_heading ) . '" order_downloads_btn_text="' . esc_attr( $download_btn_text ) . '" order_downloads_show_file_downloads="' . $show_column_download_str . '" order_downloads_show_file_expiry="' . $show_column_file_expiry_str . '"]'
			);
			$wrapper                     = HTMLUtility::render(
				array(
					'tag'               => 'div',
					'attributes'        => array( 'id' => 'wfty_order_details' ),
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
					'name'                => $block->block_type->name ?? 'wfty/order-details',
					'moduleCategory'      => $block->block_type->category ?? 'module',
					'classnamesFunction'  => array( OrderDetails::class, 'module_classnames' ),
					'stylesComponent'     => array( OrderDetails::class, 'module_styles' ),
					'scriptDataComponent' => array( OrderDetails::class, 'module_script_data' ),
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
			return '<div id="wfty_order_details">' . do_shortcode( '[wfty_order_details order_details_img="true" order_details_heading="Order Details" order_subscription_heading="Subscription" order_download_heading="Downloads" order_downloads_btn_text="Download" order_downloads_show_file_downloads="false" order_downloads_show_file_expiry="false"]' ) . '</div>';
		}
	}
}
