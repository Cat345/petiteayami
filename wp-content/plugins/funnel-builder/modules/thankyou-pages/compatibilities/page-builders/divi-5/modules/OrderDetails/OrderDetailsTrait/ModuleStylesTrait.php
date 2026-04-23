<?php
/**
 * OrderDetails::module_styles()
 *
 * @package WFTY\Modules\OrderDetails
 * @since 1.0.0
 */

namespace WFTY\Modules\OrderDetails\OrderDetailsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

trait ModuleStylesTrait {

	public static function module_styles( array $args ): void {
		$attrs         = $args['attrs'] ?? array();
		$elements      = $args['elements'];
		$order_class   = $args['orderClass'] ?? '';
		$default_attrs = array();
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfty/order-details' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}
		$attrs       = array_replace_recursive( $default_attrs, $attrs );
		$style_attrs = array(
			'module',
			'wfty_order_details_heading_typography',
			'wfty_order_details_product_typography',
			'wfty_order_details_subtotal_typography',
			'wfty_order_details_total_typography',
			'wfty_order_details_variation_typography',
			'wfty_order_details_subscription_typography',
			'wfty_order_details_subscription_text_color',
			'wfty_order_details_download_typography',
			'wfty_order_details_download_text_color',
			'wfty_order_details_subs_button_background_color',
			'wfty_order_details_subs_button_background_hover_color',
			'wfty_order_details_download_button_background_color',
			'wfty_order_details_download_button_background_hover_color',
		);
		$styles      = array();
		foreach ( $style_attrs as $attr_name ) {
			$styles[] = $elements->style( array( 'attrName' => $attr_name ) );
		}

		// Divider border-color — manual CSS from color picker (background decorator).
		$divider_css = self::get_divider_color_css( $attrs, $order_class );
		if ( $divider_css ) {
			$styles[] = $divider_css;
		}

		// Button label colors — background decorator mapped to color CSS.
		$btn_text_css = self::get_button_text_color_css( $attrs, $order_class );
		if ( $btn_text_css ) {
			$styles[] = $btn_text_css;
		}

		// Subscription & download tables need full width (WooCommerce .shop_table styles may not load).
		if ( ! empty( $order_class ) ) {
			$styles[] = array(
				array(
					'selector'    => $order_class . ' .wfty_wrap .wfty_subscription table, ' . $order_class . ' .wfty_wrap .wfty_order_download table',
					'declaration' => 'width: 100%;',
				),
			);
		}

		Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'] ?? 0,
				'storeInstance' => $args['storeInstance'] ?? null,
				'styles'        => $styles,
			)
		);
	}

	/**
	 * Extract hex color from a decorator attribute.
	 * Checks background decorator paths first (user-set via color picker),
	 * then falls back to font decorator path (legacy saved data).
	 *
	 * @param array|null $attr Attribute value.
	 *
	 * @return string|null Color hex or null.
	 */
	private static function extract_color_hex( $attr ): ?string {
		if ( empty( $attr ) || ! is_array( $attr ) ) {
			return null;
		}

		$dec = $attr['decoration'] ?? null;
		if ( empty( $dec ) ) {
			return null;
		}

		$breakpoints = array( 'desktop', 'tablet', 'phone' );

		foreach ( $breakpoints as $bp ) {
			// Background decorator — user-set value (runtime).
			$bg_runtime = $dec['background'][ $bp ]['value']['color'] ?? null;
			if ( ! empty( $bg_runtime ) ) {
				return $bg_runtime;
			}

			// Font decorator — legacy saved data.
			$font_color = $dec['font'][ $bp ]['value']['font']['color'] ?? null;
			if ( ! empty( $font_color ) ) {
				return $font_color;
			}

			// Font decorator — alternate path.
			$font_alt = $dec['font']['font'][ $bp ]['value']['color'] ?? null;
			if ( ! empty( $font_alt ) ) {
				return $font_alt;
			}

			// Background decorator — default format from module.json.
			$bg_default = $dec['background']['color'][ $bp ]['value']['hex'] ?? null;
			if ( ! empty( $bg_default ) ) {
				return $bg_default;
			}
		}

		return null;
	}

	/**
	 * Generate manual CSS for divider border-color from color picker.
	 *
	 * @param array  $attrs       Module attributes.
	 * @param string $order_class Module order class.
	 *
	 * @return array|null Manual CSS entry or null.
	 */
	private static function get_divider_color_css( array $attrs, string $order_class ): ?array {
		$color_value = self::extract_color_hex( $attrs['wfty_order_details_divider_color'] ?? null );
		if ( empty( $color_value ) || empty( $order_class ) ) {
			return null;
		}

		$escaped_color = esc_attr( $color_value );
		$selectors     = implode(
			', ',
			array(
				$order_class . ' .wfty_wrap .wfty_order_details table tfoot tr:last-child td',
				$order_class . ' .wfty_wrap .wfty_order_details table tfoot tr:last-child th',
				$order_class . ' .wfty_wrap .wfty_order_details table',
			)
		);

		return array(
			array(
				'selector'    => $selectors,
				'declaration' => 'border-color: ' . $escaped_color . ' !important;',
			),
		);
	}

	/**
	 * Generate manual CSS for button label text colors.
	 * Uses background decorator value but outputs color CSS property.
	 *
	 * @param array  $attrs       Module attributes.
	 * @param string $order_class Module order class.
	 *
	 * @return array|null Manual CSS entries or null.
	 */
	private static function get_button_text_color_css( array $attrs, string $order_class ): ?array {
		if ( empty( $order_class ) ) {
			return null;
		}

		$entries = array();

		// Subscription button label color.
		$color = self::extract_color_hex( $attrs['wfty_order_details_subs_button_text_color'] ?? null );
		if ( ! empty( $color ) ) {
			$entries[] = array(
				'selector'    => $order_class . ' .wffn_order_details_table .wfty_wrap .wfty_subscription table tr td.subscription-actions a',
				'declaration' => 'color: ' . esc_attr( $color ) . ' !important;',
			);
		}

		// Subscription button label hover color.
		$color = self::extract_color_hex( $attrs['wfty_order_details_subs_button_text_hover_color'] ?? null );
		if ( ! empty( $color ) ) {
			$entries[] = array(
				'selector'    => $order_class . ' .wffn_order_details_table .wfty_wrap .wfty_subscription table tr td.subscription-actions a:hover',
				'declaration' => 'color: ' . esc_attr( $color ) . ' !important;',
			);
		}

		// Download button label color.
		$color = self::extract_color_hex( $attrs['wfty_order_details_download_button_text_color'] ?? null );
		if ( ! empty( $color ) ) {
			$entries[] = array(
				'selector'    => $order_class . ' .wffn_order_details_table .wfty_wrap .wfty_order_download table tr td.download-file a',
				'declaration' => 'color: ' . esc_attr( $color ) . ' !important;',
			);
		}

		// Download button label hover color.
		$color = self::extract_color_hex( $attrs['wfty_order_details_download_button_text_hover_color'] ?? null );
		if ( ! empty( $color ) ) {
			$entries[] = array(
				'selector'    => $order_class . ' .wffn_order_details_table .wfty_wrap .wfty_order_download table tr td.download-file a:hover',
				'declaration' => 'color: ' . esc_attr( $color ) . ' !important;',
			);
		}

		return ! empty( $entries ) ? $entries : null;
	}
}
