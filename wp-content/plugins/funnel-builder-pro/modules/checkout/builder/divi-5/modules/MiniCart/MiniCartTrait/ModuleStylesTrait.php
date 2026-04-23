<?php
/**
 * MiniCart::module_styles().
 *
 * @package WFACP\Modules\MiniCart
 * @since 1.0.0
 */

namespace WFACP\Modules\MiniCart\MiniCartTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFACP\Modules\MiniCart\MiniCartTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Mini Cart module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/mini-cart/styles.tsx.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name              Module name.
	 *      @type string $attrs             Module attributes.
	 *      @type string $parentAttrs       Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings          Custom settings.
	 *      @type string $state             Attributes state.
	 *      @type string $mode              Style mode.
	 *      @type ModuleElements $elements         ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'] ?? null;
		$settings                    = $args['settings'] ?? [];
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$order_class                 = $args['orderClass'] ?? '';

		// CRITICAL: Get default attributes from module.json and merge with current attributes
		// This ensures defaults are always applied, even when attributes are empty
		$default_attrs = [];
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfacp/mini-cart' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		// Fix unitless border-width values from D4→D5 conversion (D4 stores "4", D5 needs "4px").
		$border_path = [ 'wfacp_mini_cart_border', 'decoration', 'border', 'border' ];
		foreach ( [ 'desktop', 'tablet', 'phone' ] as $viewport ) {
			$width = $attrs[ $border_path[0] ][ $border_path[1] ][ $border_path[2] ][ $border_path[3] ][ $viewport ]['value']['styles']['all']['width'] ?? null;
			if ( null !== $width && is_numeric( $width ) ) {
				$attrs[ $border_path[0] ][ $border_path[1] ][ $border_path[2] ][ $border_path[3] ][ $viewport ]['value']['styles']['all']['width'] = $width . 'px';
			}
		}

		// Module Text Options Style (for module-level text alignment).
		$module_text_attrs = $attrs['module']['advanced']['text'] ?? [];
		if ( ! empty( $module_text_attrs ) ) {
			TextStyle::style(
				[
					'selector' => $order_class,
					'attr'     => $module_text_attrs,
				]
			);
		}

		// Generate manual CSS for coupon focus color (border-color + box-shadow from color picker)
		$focus_color_css = self::get_focus_color_css( $attrs, $order_class );

		// Generate manual CSS for divider border-color from color picker
		$divider_color_css = self::get_divider_color_css( $attrs, $order_class );

		// Fix D4→D5 conversion: border width/color defaults missing.
		$mini_cart_border_fix = self::get_border_conversion_fix( $attrs, 'wfacp_mini_cart_border', $order_class . ' .wfacp_mini_cart_start_h' );

		Style::add(
			[
				'id'            => $args['id'] ?? '',
				'name'          => $args['name'] ?? '',
				'orderIndex'    => $args['orderIndex'] ?? '',
				'storeInstance' => $args['storeInstance'] ?? null,
				'styles'        => [
					// Module decoration styles (border, box shadow, spacing)
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					// Module Text Typography styles (common font for all text)
					// Selector: {{selector}}
					$elements->style(
						[
							'attrName' => 'miniCartModuleTextTypo',
						]
					),
					// Heading Typography styles (font, size, color)
					// Selector: {{selector}} .wfacp_mini_cart_start_h .wfacp-order-summary-label
					$elements->style(
						[
							'attrName' => 'miniCartHeadingTypo',
						]
					),
					// Product Cart Typography styles
					// Selector: Multiple product selectors (matches Divi 4: mini_cart_product_typo)
					$elements->style(
						[
							'attrName' => 'miniCartProductTypo',
						]
					),
					// Strike Through Typography styles
					// Selector: .product-total del (matches Divi 4: mini_cart_strike_through_typo)
					$elements->style(
						[
							'attrName' => 'miniCartStrikeThroughTypo',
						]
					),
					// Low Stock Typography styles
					// Selector: .wfacp_stocks (matches Divi 4: mini_cart_low_stock_message_typo)
					$elements->style(
						[
							'attrName' => 'miniCartLowStockTypo',
						]
					),
					// Saving Text Typography styles
					// Selector: .wfacp-saving-amount (matches Divi 4: mini_cart_enable_saving_price_message_typo)
					$elements->style(
						[
							'attrName' => 'miniCartSavingTextTypo',
						]
					),
					// Product Cart Image Border styles
					// Selector: .product-image img (matches Divi 4: mini_cart_product_image_border)
					$elements->style(
						[
							'attrName' => 'miniCartProductImageBorder',
						]
					),
					// Cart Total Typography styles (matches Divi 4: mini_cart_product_meta_typo)
					// Selector: table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) + cells
					$elements->style(
						[
							'attrName' => 'miniCartProductMetaTypo',
						]
					),
					// Cart Total - Cart Label Typography (matches Divi 4: mini_cart_total_label_typo)
					$elements->style(
						[
							'attrName' => 'mini_cart_total_label_typo',
						]
					),
					// Cart Total - Cart Price Typography (matches Divi 4: mini_cart_total_typo)
					$elements->style(
						[
							'attrName' => 'mini_cart_total_typo',
						]
					),
					// Coupon Link Typography styles
					// Selector: .wfacp_main_showcoupon (matches Divi 4: mini_cart_coupon_heading_typo)
					$elements->style(
						[
							'attrName' => 'miniCartCouponLinkTypo',
						]
					),
					// Coupon Input Label Typography styles
					// Selector: .wfacp-form-control-label (matches Divi 4: wfacp_form_mini_cart_coupon_label_typo)
					$elements->style(
						[
							'attrName' => 'miniCartCouponInputLabelTypo',
						]
					),
					// Coupon Input Field Typography styles
					// Selector: .wfacp-form-control (matches Divi 4: wfacp_form_mini_cart_coupon_input_typo)
					$elements->style(
						[
							'attrName' => 'miniCartCouponInputFieldTypo',
						]
					),
					// Coupon Input Field Border styles
					// Selector: .wfacp-form-control (matches Divi 4: wfacp_form_mini_cart_coupon_border)
					$elements->style(
						[
							'attrName' => 'miniCartCouponInputFieldBorder',
						]
					),
					// Coupon focus color uses manual CSS via get_focus_color_css()

					// Mini Cart Settings - Spacing (Margin + Padding)
					$elements->style(
						[
							'attrName' => 'wfacp_mini_cart_spacing',
						]
					),
					// Mini Cart Settings - Border
					$elements->style(
						[
							'attrName'   => 'wfacp_mini_cart_border',
							'styleProps' => ! empty( $mini_cart_border_fix ) ? [ 'advancedStyles' => $mini_cart_border_fix ] : [],
						]
					),
					// Mini Cart Settings - Background
					$elements->style(
						[
							'attrName' => 'wfacp_mini_cart_background',
						]
					),
					// Coupon Button Typography styles
					// Selector: button.wfacp-coupon-btn (matches Divi 4: wfacp_form_mini_cart_coupon_button_typo)
					$elements->style(
						[
							'attrName' => 'miniCartCouponButtonTypo',
						]
					),
					// Coupon Button Background styles (normal state)
					// Selector: button.wfacp-coupon-btn:not([disabled]) (matches Divi 4: mini_cart_coupon_btn_color)
					$elements->style(
						[
							'attrName' => 'miniCartCouponButtonBackground',
						]
					),
					// Coupon Button Background Hover styles
					$elements->style(
						[
							'attrName' => 'miniCartCouponButtonHoverBackground',
						]
					),
					// Coupon Button Hover Text Color (matches Divi 4: mini_cart_coupon_btn_hover_label_color)
					$elements->style(
						[
							'attrName' => 'mini_cart_coupon_btn_hover_label_color',
						]
					),
					// Coupon Code Label Typography (matches Divi 4: mini_cart_coupon_display_label_color + mini_cart_coupon_display_font_size)
					$elements->style(
						[
							'attrName' => 'miniCartCouponCodeLabelTypo',
						]
					),
					// Coupon Code Value Typography (matches Divi 4: mini_cart_coupon_display_val_color)
					$elements->style(
						[
							'attrName' => 'miniCartCouponCodeValueTypo',
						]
					),
					// Divider border-color - manual CSS from color picker
					$divider_color_css,
					// Coupon focus color - manual CSS for border-color + box-shadow
					$focus_color_css,
					// Custom CSS (must be last to allow overrides)
					CssStyle::style(
						[
							'selector'  => $order_class,
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Generate manual CSS for coupon focus color (border-color + box-shadow).
	 *
	 * @param array  $attrs       Module attributes.
	 * @param string $order_class Module order class.
	 *
	 * @return array|null Manual CSS entry or null if not needed.
	 */
	/**
	 * Generate manual CSS for divider border-color from color picker.
	 *
	 * @param array  $attrs       Module attributes.
	 * @param string $order_class Module order class.
	 *
	 * @return array|null Manual CSS entry or null if not needed.
	 */
	private static function get_divider_color_css( array $attrs, string $order_class ): ?array {
		$divider_attr = $attrs['mini_cart_divider_color'] ?? null;
		if ( empty( $divider_attr ) || empty( $order_class ) ) {
			return null;
		}

		// Check user-saved value (D4 path) first, then D5 default path.
		$color_value = $divider_attr['decoration']['background']['desktop']['value']['color'] ?? null;
		if ( empty( $color_value ) ) {
			$color_value = $divider_attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		$escaped_color = esc_attr( $color_value );
		$selectors     = implode( ', ', [
			$order_class . ' .wfacp_mini_cart_start_h .wfacp_mini_cart_divi .cart_item',
			$order_class . ' .wfacp_mini_cart_start_h table.shop_table tr.cart-subtotal',
			$order_class . ' .wfacp_mini_cart_start_h table.shop_table tr.order-total',
			$order_class . ' .wfacp_mini_cart_start_h table.shop_table tr.wfacp_ps_error_state td',
			$order_class . ' .wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
			$order_class . ' .wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp_mini_cart_elementor .cart_item',
			$order_class . ' .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page',
		] );

		return [
			[
				'selector'    => $selectors,
				'declaration' => 'border-color: ' . $escaped_color . ' !important;',
			],
		];
	}

	private static function get_focus_color_css( array $attrs, string $order_class ): ?array {
		$focus_attr = $attrs['wfacpFormMiniCartCouponFocusColor'] ?? null;
		if ( empty( $focus_attr ) || empty( $order_class ) ) {
			return null;
		}

		// Extract color: check user-saved value (D4 path from color picker) FIRST, then D5 default.
		// Color picker saves to decoration.background.desktop.value.color,
		// but module.json default is at decoration.background.color.desktop.value.hex.
		// Checking D5 path first would always return the default, ignoring user's saved value.
		$color_value = $focus_attr['decoration']['background']['desktop']['value']['color'] ?? null;
		if ( empty( $color_value ) ) {
			$color_value = $focus_attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		$selector      = $order_class . ' .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control:focus';
		$escaped_color = esc_attr( $color_value );

		return [
			[
				'selector'    => $selector,
				'declaration' => 'border-color: ' . $escaped_color . ' !important;',
			],
			[
				'selector'    => $selector,
				'declaration' => 'box-shadow: 0 0 0 1px ' . $escaped_color . ' !important;',
			],
		];
	}

	/**
	 * Build advancedStyles to fix D4→D5 border conversion defaults.
	 *
	 * @param array  $attrs    Module attributes.
	 * @param string $attr_key Attribute key.
	 * @param string $selector CSS selector.
	 * @return array advancedStyles array.
	 */
	private static function get_border_conversion_fix( array $attrs, string $attr_key, string $selector ): array {
		$styles_path = $attrs[ $attr_key ]['decoration']['border']['desktop']['value']['styles']
			?? $attrs[ $attr_key ]['decoration']['border']['border']['desktop']['value']['styles']
			?? null;

		if ( ! is_array( $styles_path ) ) {
			return [];
		}

		$all_style = $styles_path['all']['style'] ?? '';
		if ( empty( $all_style ) || 'none' === $all_style ) {
			return [];
		}

		$all_width    = $styles_path['all']['width'] ?? null;
		$all_color    = $styles_path['all']['color'] ?? null;
		$bottom_width = $styles_path['bottom']['width'] ?? null;
		$advanced     = [];

		$all_width_num = $all_width ? (int) $all_width : 0;
		if ( ! $all_width || ( $all_width_num > 1 && ! isset( $styles_path['top']['width'] ) && ! isset( $styles_path['left']['width'] ) ) ) {
			foreach ( [ 'border-top-width', 'border-left-width', 'border-right-width' ] as $prop ) {
				$advanced[] = [
					'componentName' => 'divi/common',
					'props'         => [
						'selector'  => $selector,
						'attr'      => [ 'desktop' => [ 'value' => '1px' ] ],
						'property'  => $prop,
						'important' => true,
					],
				];
			}
			$bw = $bottom_width ?: $all_width ?: '1';
			$bw = is_numeric( $bw ) ? $bw . 'px' : $bw;
			$advanced[] = [
				'componentName' => 'divi/common',
				'props'         => [
					'selector'  => $selector,
					'attr'      => [ 'desktop' => [ 'value' => $bw ] ],
					'property'  => 'border-bottom-width',
					'important' => true,
				],
			];
		}

		if ( ! $all_color || '#333333' === $all_color ) {
			$advanced[] = [
				'componentName' => 'divi/common',
				'props'         => [
					'selector'  => $selector,
					'attr'      => [ 'desktop' => [ 'value' => '#dddddd' ] ],
					'property'  => 'border-color',
					'important' => true,
				],
			];
		}

		return $advanced;
	}
}
