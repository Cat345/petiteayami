<?php
/**
 * CheckoutForm::module_styles().
 *
 * @package WFACP\Modules\CheckoutForm
 * @since 1.0.0
 */

namespace WFACP\Modules\CheckoutForm\CheckoutFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WFACP\Modules\CheckoutForm\CheckoutFormTrait\CustomCssTrait;

trait ModuleStylesTrait {

	use CustomCssTrait;

	/**
	 * Checkout Form module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * src/components/checkout-form/styles.tsx.
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
		$attrs                       = $args['attrs'] ?? array();
		$elements                    = $args['elements'] ?? null;
		$settings                    = $args['settings'] ?? array();
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? array();
		$order_index                 = $args['orderIndex'] ?? 0;
		$order_class                 = $args['orderClass'] ?? '';

		// Generate order class with VB prefixes for CSS selector
		// Format: .et-db #et-boc .et-l .wfacp_checkout_form_{orderIndex}
		// This matches the format used by other working modules
		if ( ! empty( $order_class ) && preg_match( '/wfacp_checkout_form_(\d+)/', $order_class, $matches ) ) {
			$order_index = (int) $matches[1];
		}

		// Ensure order class has VB prefixes (required for frontend CSS)
		if ( strpos( $order_class, '.et-db' ) === false ) {
			$order_class = '.et-db #et-boc .et-l .wfacp_checkout_form_' . $order_index;
		}

		// CRITICAL: Get default attributes from module.json and merge with current attributes
		// This ensures defaults are always applied, even when attributes are empty
		$default_attrs = array();
		if ( class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			try {
				$default_attrs = ModuleRegistration::get_default_attrs( 'wfacp/checkout-form' );
			} catch ( \Exception $e ) {
				// Continue without defaults
			}
		}

		// CRITICAL: Merge defaults with current attributes (defaults are base, current overrides)
		// This ensures defaults from module.json are always applied
		$merged_attrs = array_replace_recursive( $default_attrs, $attrs );

		// Update $attrs to use merged values so elements->style() reads merged defaults
		$attrs = $merged_attrs;

		// CRITICAL: $elements is required for style generation
		// Module::render() should always create elements object before calling module_styles()
		// If it's null, we cannot generate styles, so return early
		// This matches MiniCart behavior - Module::render() handles element creation
		if ( null === $elements ) {
			return;
		}

		// Module Text Options Style (for module-level text alignment).
		$module_text_attrs = $attrs['module']['advanced']['text'] ?? array();
		if ( ! empty( $module_text_attrs ) ) {
			TextStyle::style(
				array(
					'selector' => $order_class,
					'attr'     => $module_text_attrs,
				)
			);
		}

		// Process border color attributes (uses divi/color-picker which requires manual CSS fallback for frontend)
		$active_border_color_css              = self::get_border_color_css( $attrs, $order_class, $elements, 'active_step_count_border_color', '#wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber' );
		$inactive_border_color_css            = self::get_border_color_css( $attrs, $order_class, $elements, 'inactive_step_count_border_color', '#wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber' );
		$active_tab_border_bottom_color_css   = self::get_border_bottom_color_css( $attrs, $order_class, $elements, 'active_tab_border_bottom_color', '#wfacp-e-form .wfacp-payment-tab-list.wfacp-active' );
		$inactive_tab_border_bottom_color_css = self::get_border_bottom_color_css( $attrs, $order_class, $elements, 'inactive_tab_border_bottom_color', '#wfacp-e-form .wfacp-payment-tab-list' );
		$order_summary_divider_line_color_css = self::get_border_color_css( $attrs, $order_class, $elements, 'order_summary_divider_line_color', '#wfacp-e-form table.shop_table tbody .wfacp_order_summary_item_name, #wfacp-e-form table.shop_table tr.cart_item, #wfacp-e-form table.shop_table tr.cart-subtotal, #wfacp-e-form table.shop_table tr.order-total' );
		$focus_color_css                      = self::get_border_color_css( $attrs, $order_class, $elements, 'wfacp_form_fields_focus_color', '#wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.wfacp_coupon_code):focus, #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) input[type="radio"]:focus, #wfacp-e-form p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.input-checkbox):focus, #wfacp-e-form .wfacp_main_form.woocommerce p.wfacp_coupon_failed .wfacp_coupon_code, #wfacp-e-form .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single .select2-selection__rendered:focus, #wfacp-e-form .wfacp_main_form.woocommerce .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single:focus>span.select2-selection__rendered', true );
		$validation_color_css                 = self::get_border_color_css( $attrs, $order_class, $elements, 'wfacp_form_fields_validation_color', '#wfacp-e-form .wfacp_main_form.woocommerce p.woocommerce-invalid-required-field .wfacp-form-control, #wfacp-e-form .wfacp_main_form.woocommerce p.woocommerce-invalid-email .wfacp-form-control, #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_coupon_failed .wfacp_coupon_code', true );
		$primary_color_extra_css              = self::get_primary_color_extra_css( $attrs, $order_class );
		$breadcrumb_hover_color_css           = self::get_text_color_css( $attrs, $order_class, 'breadcrumb_text_hover_color', '#wfacp-e-form .wfacp-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a:hover' );
		$payment_desc_color_css               = self::get_text_color_css( $attrs, $order_class, 'wfacp_form_payment_method_description_color', '#wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #payment .payment_methods li .payment_box p, #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #payment .payment_methods li .payment_box p span, #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #payment .payment_methods li .payment_box p strong' );

		// Coupon focus color — color picker only, rendered as box-shadow (matches D4 behavior)
		$coupon_focus_color_css = null;
		$coupon_focus_attr      = $attrs['order_coupon_focus_color'] ?? null;
		if ( ! empty( $coupon_focus_attr ) && ! empty( $order_class ) ) {
			// D5 native path (color picker saves here) or D4-converted path (conversion outline maps here)
			$coupon_focus_value = $coupon_focus_attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
			if ( empty( $coupon_focus_value ) ) {
				$coupon_focus_value = $coupon_focus_attr['decoration']['background']['desktop']['value']['color'] ?? null;
			}
			if ( empty( $coupon_focus_value ) ) {
				$coupon_focus_value = $coupon_focus_attr['decoration']['font']['font']['desktop']['value']['color'] ?? null;
			}
			if ( ! empty( $coupon_focus_value ) ) {
				$escaped_coupon_focus = esc_attr( $coupon_focus_value );
				// Divi's style system auto-adds .et-db #et-boc .et-l prefix — do NOT add it here or it doubles
				// Repeat class selectors to beat general form focus specificity (2,8,1)
				$coupon_focus_sel       = $order_class . ' #wfacp-e-form .wfacp-coupon-section.wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper .wfacp-form-control.wfacp-form-control:focus';
				$coupon_focus_color_css = array(
					array(
						'selector'    => $coupon_focus_sel,
						'declaration' => 'border-color: ' . $escaped_coupon_focus . ' !important;',
					),
					array(
						'selector'    => $coupon_focus_sel,
						'declaration' => 'box-shadow: 0 0 0 1px ' . $escaped_coupon_focus . ' !important;',
					),
				);
			}
		}

		// Merge manual CSS entries
		$border_color_manual_css_entry = null;
		if ( ! empty( $active_border_color_css ) || ! empty( $inactive_border_color_css ) || ! empty( $active_tab_border_bottom_color_css ) || ! empty( $inactive_tab_border_bottom_color_css ) || ! empty( $order_summary_divider_line_color_css ) || ! empty( $focus_color_css ) || ! empty( $validation_color_css ) || ! empty( $primary_color_extra_css ) || ! empty( $breadcrumb_hover_color_css ) || ! empty( $payment_desc_color_css ) || ! empty( $coupon_focus_color_css ) ) {
			$border_color_manual_css_entry = array_merge(
				$active_border_color_css ?? array(),
				$inactive_border_color_css ?? array(),
				$active_tab_border_bottom_color_css ?? array(),
				$inactive_tab_border_bottom_color_css ?? array(),
				$order_summary_divider_line_color_css ?? array(),
				$focus_color_css ?? array(),
				$validation_color_css ?? array(),
				$primary_color_extra_css ?? array(),
				$breadcrumb_hover_color_css ?? array(),
				$payment_desc_color_css ?? array(),
				$coupon_focus_color_css ?? array()
			);
		}

		// Heading Typography
		$heading_typo_result = $elements->style(
			array(
				'attrName' => 'section_heading_typo',
			)
		);

		// Sub Heading Typography
		$sub_heading_typo_result = $elements->style(
			array(
				'attrName' => 'section_sub_heading_typo',
			)
		);

		// Payment Method Typography
		$payment_method_typo_result = $elements->style(
			array(
				'attrName' => 'wfacp_form_payment_method_typo',
			)
		);

		// Fix D4→D5 conversion: border width/color defaults missing from converted attrs.
		// Build advancedStyles arrays to inject missing defaults for section_border and form_border.
		$section_border_fix = self::get_border_conversion_fix( $attrs, 'section_border', $order_class . ' #wfacp-e-form .wfacp-section' );
		$form_border_fix    = self::get_border_conversion_fix( $attrs, 'form_border', $order_class . ' .wfacp_form_divi_container' );

		Style::add(
			array(
				'id'            => $args['id'] ?? '',
				'name'          => $args['name'] ?? '',
				'orderIndex'    => $order_index, // Use calculated order_index instead of args
				'storeInstance' => $args['storeInstance'] ?? null,
				'styles'        => array(
					// Module decoration styles (border, box shadow, spacing)
					$elements->style(
						array(
							'attrName'   => 'module',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? array(),
								'disabledOn'               => array(
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								),
							),
						)
					),

					// Collapsible Order Summary - Collapsed Background
					// Selector: .wfacp_mb_mini_cart_wrap .wfacp_mb_cart_accordian
					$elements->style(
						array(
							'attrName' => 'collapsible_order_summary_bg_color',
						)
					),

					// Collapsible Order Summary - Expanded Background
					// Selector: .wfacp_mb_mini_cart_sec_accordion_content
					$elements->style(
						array(
							'attrName' => 'expanded_order_summary_bg_color',
						)
					),

					// Product Cart Typography
					// Selector: .wfacp_show_icon_wrap a span, .wfacp_show_price_wrap span
					$elements->style(
						array(
							'attrName' => 'expanded_order_summary_link_color',
						)
					),

					// Collapsible Order Summary Border
					// Selector: {{selector}} #wfacp-e-form .wfacp_mb_mini_cart_wrap .wfacp_mb_cart_accordian
					$elements->style(
						array(
							'attrName' => 'wfacp_collapsible_border',
						)
					),

					// Collapsible Order Summary Spacing
					// Selector: {{selector}} #wfacp-e-form .wfacp_collapsible_order_summary_wrap
					$elements->style(
						array(
							'attrName'   => 'wfacp_collapsible_margin',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['wfacp_collapsible_margin']['decoration'] ?? array(),
							),
						)
					),

					// Step Tab Spacing - Spacing
					// Selector: {{selector}} #wfacp-e-form .tab
					$elements->style(
						array(
							'attrName'   => 'wfacp_tab_margin',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['wfacp_tab_margin']['decoration'] ?? array(),
							),
						)
					),

					// Step Tab Heading Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-order2StepTitle.wfacp-order2StepTitleS1
					$elements->style(
						array(
							'attrName' => 'tab_heading_typography',
						)
					),

					// Step Tab Sub-heading Typography - Subheading
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-order2StepSubTitle.wfacp-order2StepSubTitleS1
					$elements->style(
						array(
							'attrName' => 'tab_subheading_typography',
						)
					),

					// Active Step Text Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp_tcolor
					$elements->style(
						array(
							'attrName' => 'active_step_text_color',
						)
					),

					// Inactive Step Text Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp_tcolor
					$elements->style(
						array(
							'attrName' => 'inactive_step_text_color',
						)
					),

					// Breadcrumb Typography - Heading
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a
					$elements->style(
						array(
							'attrName' => 'breadcrumb_heading_typography',
						)
					),

					// Breadcrumb Typography - Color
					// Selector: {{selector}} #wfacp-e-form .wfacp-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a
					$elements->style(
						array(
							'attrName' => 'breadcrumb_text_color',
						)
					),

					// Breadcrumb Typography - Hover Color handled via manual CSS below.

					// Step Tab Active Color - Background Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active
					$elements->style(
						array(
							'attrName' => 'active_step_bg_color',
						)
					),

					// Step Tab Active Color - Count Background Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber
					$elements->style(
						array(
							'attrName' => 'active_step_count_bg_color',
						)
					),

					// Step Tab Active Color - Count Text Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber
					$elements->style(
						array(
							'attrName' => 'active_step_count_text_color',
						)
					),

					// Step Tab Active Color - Count Border Color
					// Note: Border color attrs use decoration.background storage, so $elements->style()
					// would incorrectly generate background-color CSS. Manual CSS handles border-color correctly.

					// Manual CSS for all border-color attributes (border-color, border-bottom-color)
					$border_color_manual_css_entry,

					// Step Tab Inactive Color - Inactive Step Background Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list
					$elements->style(
						array(
							'attrName' => 'inactive_step_bg_color',
						)
					),

					// Step Tab Inactive Color - Inactive Step Count Background Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber
					$elements->style(
						array(
							'attrName' => 'inactive_step_count_bg_color',
						)
					),

					// Step Tab Inactive Color - Inactive Count Text Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber
					$elements->style(
						array(
							'attrName' => 'inactive_step_count_text_color',
						)
					),

					// Step Tab Inactive Color - Inactive Count Border Color
					// Step Tab Active/Inactive Tab Border Color
					// Note: All border color attrs handled by manual CSS above ($border_color_manual_css_entry)
					// $elements->style() is NOT used for these because they store values in
					// decoration.background path, which would incorrectly generate background-color CSS.

					// Section Color - Background Color
					// Selector: {{selector}} #wfacp-e-form .wfacp-section
					$elements->style(
						array(
							'attrName' => 'section_bg_color',
						)
					),

					// Section Color - Text Color
					// Selector: {{selector}} #wfacp-e-form .wfacp-section
					$elements->style(
						array(
							'attrName' => 'section_text_color',
						)
					),

					// Section Spacing
					// Selector: {{selector}} #wfacp-e-form .wfacp-section
					$elements->style(
						array(
							'attrName' => 'section_spacing',
						)
					),

					// Section Box Shadow
					// Selector: {{selector}} #wfacp-e-form .wfacp-section
					$elements->style(
						array(
							'attrName' => 'section_box_shadow',
						)
					),

					// Section Border
					// Selector: {{selector}} #wfacp-e-form .wfacp-section
					$elements->style(
						array(
							'attrName'   => 'section_border',
							'styleProps' => ! empty( $section_border_fix ) ? array( 'advancedStyles' => $section_border_fix ) : array(),
						)
					),

					// Order Summary Product Typography - Product Cart
					// Selector: {{selector}} #wfacp-e-form table.shop_table tbody .wfacp_order_summary_item_name, ...
					$elements->style(
						array(
							'attrName' => 'order_summary_cart_item_typo',
						)
					),

					// Order Summary Sub Total Typography - Sub Total
					// Selector: {{selector}} #wfacp-e-form table.shop_table tfoot tr:not(.order-total):not(.cart-discount), ...
					$elements->style(
						array(
							'attrName' => 'order_summary_product_meta_typo',
						)
					),

					// Order Summary Total Label Typography - Total Label
					// Selector: {{selector}} #wfacp-e-form table.shop_table tfoot tr.order-total th, ...
					$elements->style(
						array(
							'attrName' => 'order_summary_cart_total_label_typo',
						)
					),

					// Order Summary Total Price Typography - Total Price
					// Selector: {{selector}} #wfacp-e-form table.shop_table tfoot tr.order-total td, ...
					$elements->style(
						array(
							'attrName' => 'order_summary_cart_subtotal_heading_typo',
						)
					),

					// Order Summary - Image Border
					// Selector: {{selector}} #wfacp-e-form table.shop_table tr.cart_item .product-image img
					$elements->style(
						array(
							'attrName' => 'order_summary_image_border',
						)
					),

					// Order Summary - Divider Color
					// Selector: {{selector}} #wfacp-e-form table.shop_table tbody .wfacp_order_summary_item_name, ...
					// Note: Manual CSS fallback is included if Border component doesn't generate CSS
				$elements->style(
					array(
						'attrName' => 'order_summary_divider_line_color',
					)
				),

					// Payment Method Typography
					$payment_method_typo_result,

					// Payment Method Description Color handled via manual CSS below.

					// Payment Method Information Background Color
					$elements->style(
						array(
							'attrName' => 'wfacp_form_payment_method_description_bg_color',
						)
					),

					// Privacy Policy Typography
					$elements->style(
						array(
							'attrName' => 'wfacp_privacy_policy_font',
						)
					),

					// Terms & Conditions Typography
					$elements->style(
						array(
							'attrName' => 'wfacp_terms_conditions_font',
						)
					),

					// Coupon Typography Fields
					$elements->style(
						array(
							'attrName' => 'order_coupon_coupon_typography',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_label_typo',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_input_typo',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_button_typo',
						)
					),

					// Coupon Focus Color — color picker only, rendered as box-shadow (matches D4 behavior)
					// Not using elements->style() because this is a color picker that outputs box-shadow, not a border component
					$elements->style(
						array(
							'attrName' => 'order_coupon_btn_bg_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_btn_text_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_btn_bg_hover_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_btn_bg_hover_text_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'order_coupon_coupon_border',
						)
					),

					// Product Switcher Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_name_inner *, ...
					$elements->style(
						array(
							'attrName' => 'selected_item_typography',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text, ...
					$elements->style(
						array(
							'attrName' => 'selected_you_save_typo',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value
					$elements->style(
						array(
							'attrName' => 'product_switching_best_value_typography',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included h3
					$elements->style(
						array(
							'attrName' => 'product_switching_what_included_heading',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description h4
					$elements->style(
						array(
							'attrName' => 'product_switching_what_included_product_title',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description .wfacp_description p, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_what_included_product_description',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_name_inner *, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_optional_item_typography',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text, ...
					$elements->style(
						array(
							'attrName' => 'non_selected_you_save_typo',
						)
					),

					// Product Switcher Colors
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_label_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp-selected-product .product-price, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_price_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form .wfacp_selected_attributes .wfacp_pro_attr_single span, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_variant_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text, ...
					$elements->style(
						array(
							'attrName' => 'selected_you_save_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_best_value_text_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_optional_label_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .product-price, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_optional_price_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text, ...
					$elements->style(
						array(
							'attrName' => 'non_selected_you_save_color',
						)
					),

					// Product Switcher Background Colors
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product
					$elements->style(
						array(
							'attrName' => 'product_switching_item_background',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_best_value_bg_color',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included
					$elements->style(
						array(
							'attrName' => 'product_switching_what_included_bg',
						)
					),
					// Selector: {{selector}} .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product)
					$elements->style(
						array(
							'attrName' => 'product_switching_optional_background',
						)
					),
					// Selector: {{selector}} .wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product):hover
					$elements->style(
						array(
							'attrName' => 'product_switching_optional_background_hover',
						)
					),

					// Product Switcher Border Colors
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form .shop_table.wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item.wfacp_best_val_wrap
					$elements->style(
						array(
							'attrName' => 'product_switching_best_value_border_color',
						)
					),

					// Product Switcher Borders
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product
					$elements->style(
						array(
							'attrName' => 'product_switching_item_border',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value, ...
					$elements->style(
						array(
							'attrName' => 'product_switching_best_value_border',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included
					$elements->style(
						array(
							'attrName' => 'product_switching_what_included_border',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product)
						$elements->style(
							array(
								'attrName' => 'product_switching_border_non_selected',
							)
						),

					// Heading Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_section_heading.wfacp_section_title
					$elements->style(
						array(
							'attrName' => 'section_heading_typo',
						)
					),
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-comm-title h4
					$elements->style(
						array(
							'attrName' => 'section_sub_heading_typo',
						)
					),

					// Heading Design
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title
					$elements->style(
						array(
							'attrName' => 'form_heading_bg_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'form_heading_spacing',
						)
					),
					$elements->style(
						array(
							'attrName' => 'form_heading_border',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_label_typo',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_typo',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_label_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_bg_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_border',
						)
					),
					// Focus Color and Validation Color use manual CSS via get_border_color_css() - no elements->style() needed
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_label_typo',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_typo',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_label_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_bg_color',
						)
					),
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_border',
						)
					),

					// Checkout Form Typography
					$elements->style(
						array(
							'attrName' => 'wfacp_font_family_typography',
						)
					),

					// Checkout Button Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #payment button#place_order, {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce button#place_order, {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-checkout button.button.button-primary.wfacp_next_page_button, {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #ppcp-hosted-fields .button
					$elements->style(
						array(
							'attrName' => 'wfacp_form_payment_button_typo',
						)
					),

					// Checkout Button Sub Text Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-order-place-btn-wrap button:after, {{selector}} #wfacp-e-form .wfacp-next-btn-wrap button:after
					$elements->style(
						array(
							'attrName' => 'checkout_button_sub_text_font_size',
						)
					),

					// Checkout Button Width
					$elements->style(
						array(
							'attrName' => 'wfacp_button_width_width',
						)
					),

					// Checkout Button Background Color
					$elements->style(
						array(
							'attrName' => 'wfacp_button_bg_color',
						)
					),

					// Checkout Button Label Color
					$elements->style(
						array(
							'attrName' => 'wfacp_button_label_color',
						)
					),

					// Checkout Button Background Hover Color
					$elements->style(
						array(
							'attrName' => 'wfacp_button_bg_hover_color',
						)
					),

					// Checkout Button Label Hover Color
					$elements->style(
						array(
							'attrName' => 'wfacp_button_label_hover_color',
						)
					),

					// Checkout Button Border
					$elements->style(
						array(
							'attrName' => 'wfacp_button_border',
						)
					),

					// Checkout Button Padding
					$elements->style(
						array(
							'attrName' => 'wfacp_button_padding',
						)
					),

					// Checkout Button Margin
					$elements->style(
						array(
							'attrName' => 'wfacp_button_margin',
						)
					),

					// Return Link Color
					$elements->style(
						array(
							'attrName' => 'step_back_link_color',
						)
					),

					// Return Link Hover Color
					$elements->style(
						array(
							'attrName' => 'step_back_link_hover_color',
						)
					),

					// Additional Text Color
					$elements->style(
						array(
							'attrName' => 'additional_text_color',
						)
					),

					// Additional Background Color
					$elements->style(
						array(
							'attrName' => 'additional_bg_color',
						)
					),

					// Checkout Button Icon Color
					$elements->style(
						array(
							'attrName' => 'checkout_button_icon_color',
						)
					),

					// Checkout Button Sub Text Color
					$elements->style(
						array(
							'attrName' => 'checkout_button_sub_text_color',
						)
					),

					// Form Background Color
					$elements->style(
						array(
							'attrName'   => 'form_background_color',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['form_background_color']['decoration'] ?? array(),
							),
						)
					),

					// Primary Color
					$elements->style(
						array(
							'attrName' => 'default_primary_color',
						)
					),

					// Content Color
					$elements->style(
						array(
							'attrName' => 'default_text_color',
						)
					),

					// Form Link Color
					$elements->style(
						array(
							'attrName' => 'default_link_color',
						)
					),

					// Link Hover Color
					$elements->style(
						array(
							'attrName' => 'default_link_hover_color',
						)
					),

					// Form Spacing
					$elements->style(
						array(
							'attrName' => 'form_spacing',
						)
					),

					// Form Border
					$elements->style(
						array(
							'attrName'   => 'form_border',
							'styleProps' => ! empty( $form_border_fix ) ? array( 'advancedStyles' => $form_border_fix ) : array(),
						)
					),

					// Fields Typography - Label Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce p.wfacp-form-control-wrapper:not(.wfacp-anim-wrap) label.wfacp-form-control-label, ...
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_label_typo',
						)
					),

					// Fields Typography - Input Typography
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce input[type="text"]:not(.select2-search__field), ...
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_typo',
						)
					),

					// Fields - Label Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-form-control-label, ...
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_label_color',
						)
					),

					// Fields - Input Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-input-wrapper .wfacp-form-control, ...
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_color',
						)
					),

					// Fields - Input Background Color
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-input-wrapper .wfacp-form-control:not(.input-checkbox), ...
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_input_bg_color',
						)
					),

					// Fields - Border
					// Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce input[type="text"]:not(.select2-search__field), ...
					$elements->style(
						array(
							'attrName' => 'wfacp_form_fields_border',
						)
					),

					// Fields - Focus Color and Validation Color use manual CSS via get_border_color_css()

					// Heading Typography
					$heading_typo_result,

					// Sub Heading Typography
					$sub_heading_typo_result,

					// Heading Background Color
					$elements->style(
						array(
							'attrName' => 'form_heading_bg_color',
						)
					),

					// Heading Spacing
					$elements->style(
						array(
							'attrName' => 'form_heading_spacing',
						)
					),

					// Heading Border
					$elements->style(
						array(
							'attrName' => 'form_heading_border',
						)
					),

					// TODO: Add remaining checkout form specific style elements as attributes are added
					// Styles will be added incrementally as we implement each phase

					// Custom CSS (must be last to allow overrides)
						CssStyle::style(
							array(
								'selector'  => $order_class,
								'attr'      => $attrs['css'] ?? array(),
								'cssFields' => self::custom_css(),
							)
						),
				),
			)
		);
	}

	/**
	 * Generate manual CSS for border color attribute.
	 *
	 * The Border component doesn't properly handle deeply nested color-picker paths,
	 * so we generate manual CSS as a fallback to ensure it works on frontend.
	 *
	 * @param array  $attrs      Module attributes.
	 * @param string $order_class Order class with VB prefixes.
	 * @param mixed  $elements    Elements object for style generation.
	 * @param string $key         Attribute key (e.g., 'active_step_count_border_color').
	 * @param string $selector    CSS selector without order class (e.g., '#wfacp-e-form .wfacp-order2StepNumber').
	 *
	 * @return array|null Manual CSS entry or null if not needed.
	 */
	private static function get_border_color_css( array $attrs, string $order_class, $elements, string $key, string $selector, bool $box_shadow = false ): ?array {
		$border_color_attr = $attrs[ $key ] ?? null;
		if ( empty( $border_color_attr ) || empty( $order_class ) ) {
			return null;
		}

		// Extract color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
		// Check both paths and use the first non-empty value (after merge with defaults, both paths may exist)
		$color_value = $border_color_attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
		if ( empty( $color_value ) ) {
			$color_value = $border_color_attr['decoration']['background']['desktop']['value']['color'] ?? null;
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		$full_selector = $order_class . ' ' . $selector;
		$escaped_color = esc_attr( $color_value );

		$css = array(
			array(
				'selector'    => $full_selector,
				'declaration' => 'border-color: ' . $escaped_color . ' !important;',
			),
		);

		if ( $box_shadow ) {
			$css[] = array(
				'selector'    => $full_selector,
				'declaration' => 'box-shadow: 0 0 0 1px ' . $escaped_color . ' !important;',
			);
		}

		return $css;
	}

	/**
	 * Generate manual CSS for border-bottom-color attribute.
	 *
	 * Similar to get_border_color_css() but uses border-bottom-color CSS property instead of border-color.
	 * Used for tab border-bottom-color styling.
	 *
	 * @param array  $attrs      Module attributes.
	 * @param string $order_class Order class with VB prefixes.
	 * @param mixed  $elements    Elements object for style generation.
	 * @param string $key         Attribute key (e.g., 'active_tab_border_bottom_color').
	 * @param string $selector    CSS selector without order class (e.g., '#wfacp-e-form .wfacp-payment-tab-list.wfacp-active').
	 *
	 * @return array|null Manual CSS entry or null if not needed.
	 */
	private static function get_border_bottom_color_css( array $attrs, string $order_class, $elements, string $key, string $selector ): ?array {
		$border_color_attr = $attrs[ $key ] ?? null;
		if ( empty( $border_color_attr ) || empty( $order_class ) ) {
			return null;
		}

		// Extract color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
		// Check both paths and use the first non-empty value (after merge with defaults, both paths may exist)
		$color_value = $border_color_attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
		if ( empty( $color_value ) ) {
			$color_value = $border_color_attr['decoration']['background']['desktop']['value']['color'] ?? null;
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		$full_selector = $order_class . ' ' . $selector;

		return array(
			array(
				'selector'    => $full_selector,
				'declaration' => 'border-bottom-color: ' . esc_attr( $color_value ) . ' !important;',
			),
		);
	}

	/**
	 * Generate manual CSS for text color from a divi/color-picker attribute.
	 *
	 * Used for attributes stored as background decoration but needing CSS `color` output.
	 *
	 * @param array  $attrs       Module attributes.
	 * @param string $order_class Order class with VB prefixes.
	 * @param string $key         Attribute key.
	 * @param string $selector    CSS selector without order class.
	 *
	 * @return array|null Manual CSS entry or null if not needed.
	 */
	private static function get_text_color_css( array $attrs, string $order_class, string $key, string $selector ): ?array {
		$attr = $attrs[ $key ] ?? null;
		if ( empty( $attr ) || empty( $order_class ) ) {
			return null;
		}

		$color_value = $attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
		if ( empty( $color_value ) ) {
			$color_value = $attr['decoration']['background']['desktop']['value']['color'] ?? null;
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		return array(
			array(
				'selector'    => $order_class . ' ' . $selector,
				'declaration' => 'color: ' . esc_attr( $color_value ) . ' !important;',
			),
		);
	}

	/**
	 * Generate manual CSS for primary color checkbox/radio styles.
	 *
	 * The primary color attribute via styleProps only generates background-color.
	 * D4 also generates border-color, border-width, ::before/::after rules for
	 * checkboxes and radios that need manual CSS in D5.
	 *
	 * @param array  $attrs       Module attributes.
	 * @param string $order_class Order class with VB prefixes.
	 *
	 * @return array|null Manual CSS entries or null if primary color is not set.
	 */
	private static function get_primary_color_extra_css( array $attrs, string $order_class ): ?array {
		$primary_attr = $attrs['default_primary_color'] ?? null;
		if ( empty( $primary_attr ) || empty( $order_class ) ) {
			return null;
		}

		// Extract color value (D5 native path and D4-converted path)
		$color_value = $primary_attr['decoration']['background']['color']['desktop']['value']['hex'] ?? null;
		if ( empty( $color_value ) ) {
			$color_value = $primary_attr['decoration']['background']['desktop']['value']['color'] ?? null;
		}

		if ( empty( $color_value ) ) {
			return null;
		}

		$escaped_color = esc_attr( $color_value );
		$css           = array();

		// Focus border-color + box-shadow (selectors must match or exceed specificity of wfacp_form_fields_focus_color)
		// The focus color attribute uses selectors like: $order_class #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.wfacp_coupon_code):focus
		// Primary color focus must use identical or higher specificity selectors so it can override via source order.
		$focus_selectors = implode(
			', ',
			array(
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.wfacp_coupon_code):focus',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) input[type="radio"]:focus',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.input-checkbox):focus',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce p.wfacp_coupon_failed .wfacp_coupon_code',
				$order_class . ' #wfacp-e-form .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single .select2-selection__rendered:focus',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single .select2-selection__rendered:focus',
				$order_class . ' #wfacp-e-form .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single:focus>span.select2-selection__rendered',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single:focus>span.select2-selection__rendered',
			)
		);
		$css[]           = array(
			'selector'    => $focus_selectors,
			'declaration' => 'border-color:' . $escaped_color . ' !important;',
		);
		$css[]           = array(
			'selector'    => $focus_selectors,
			'declaration' => 'box-shadow:0 0 0 1px ' . $escaped_color . ' !important;',
		);

		// Radio: hide default ::before
		$radio_before_selectors = implode(
			', ',
			array(
				$order_class . ' #wfacp-e-form #payment li.wc_payment_method input.input-radio:checked::before',
				$order_class . ' #wfacp-e-form #payment.wc_payment_method input[type=radio]:checked:before',
				$order_class . ' #wfacp-e-form input[type=radio]:checked:before',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce input[type=radio]:checked:before',
			)
		);
		$css[]                  = array(
			'selector'    => $radio_before_selectors,
			'declaration' => 'display:none !important;',
		);

		// Radio: border-width when checked
		$radio_border_width_selectors = implode(
			', ',
			array(
				$order_class . ' #wfacp-e-form .wfacp_main_form #payment li.wc_payment_method input.input-radio:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form #payment.wc_payment_method input[type=radio]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form input[type=radio]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form #add_payment_method #payment ul.payment_methods li input[type=radio]:checked',
			)
		);
		$css[]                        = array(
			'selector'    => $radio_border_width_selectors,
			'declaration' => 'border-width:5px !important;',
		);

		// Radio: border-color when checked
		$radio_border_color_selectors = implode(
			', ',
			array(
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce #payment li.wc_payment_method input.input-radio:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce #payment.wc_payment_method input[type=radio]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce input[type=radio]:checked',
				$order_class . ' #wfacp-e-form input[type=radio]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce #add_payment_method #payment ul.payment_methods li input[type=radio]:checked',
				$order_class . ' #wfacp-e-form #payment ul.payment_methods li input[type=radio]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart #payment ul.payment_methods li input[type=radio]:checked',
			)
		);
		$css[]                        = array(
			'selector'    => $radio_border_color_selectors,
			'declaration' => 'border-color:' . $escaped_color . ' !important;',
		);

		// Checkbox: border-color when checked
		$checkbox_border_selectors = implode(
			', ',
			array(
				$order_class . ' #wfacp-e-form .wfacp-form input[type=checkbox]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form #payment input[type=checkbox]:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control:checked',
				$order_class . ' #wfacp-e-form .wfacp_main_form input[type=checkbox]:checked',
			)
		);
		$css[]                     = array(
			'selector'    => $checkbox_border_selectors,
			'declaration' => 'border-color:' . $escaped_color . ' !important;',
		);

		// Checkbox: show ::after (custom checkmark)
		$css[] = array(
			'selector'    => $order_class . ' #wfacp-e-form .wfacp_main_form input[type=checkbox]:after',
			'declaration' => 'display:block !important;',
		);

		// Checkbox: hide ::before (browser default)
		$css[] = array(
			'selector'    => $order_class . ' #wfacp-e-form .wfacp_main_form input[type=checkbox]:before',
			'declaration' => 'display:none !important;',
		);

		// Checkbox: border-width when checked (creates filled effect)
		$css[] = array(
			'selector'    => $order_class . ' #wfacp-e-form .wfacp_main_form input[type=checkbox]:checked',
			'declaration' => 'border-width:8px !important;',
		);

		// Order bump checkbox styles
		$css[] = array(
			'selector'    => 'body #wfob_wrap .wfob_wrapper .wfob_bump_checkbox input[type=checkbox]:checked',
			'declaration' => 'border-color:' . $escaped_color . ' !important;',
		);
		$css[] = array(
			'selector'    => 'body #wfob_wrap .wfob_wrapper input[type=checkbox]:checked:after',
			'declaration' => 'display:block !important;',
		);
		$css[] = array(
			'selector'    => 'body #wfob_wrap .wfob_wrapper input[type=checkbox]:checked:before',
			'declaration' => 'display:none !important;',
		);
		$css[] = array(
			'selector'    => 'body #wfob_wrap .wfob_wrapper input[type=checkbox]:checked',
			'declaration' => 'border-width:5px !important;',
		);

		return $css;
	}

	/**
	 * Build advancedStyles to fix D4→D5 border conversion defaults.
	 *
	 * Divi's JS conversion doesn't include default border width (1px) or color (#ddd)
	 * when D4 shortcodes don't store them explicitly, and puts per-side widths into
	 * styles.all instead of the specific side.
	 *
	 * @param array  $attrs    Module attributes.
	 * @param string $attr_key Attribute key (e.g. 'section_border', 'form_border').
	 * @param string $selector CSS selector for the border element.
	 * @return array advancedStyles array for elements->style() styleProps.
	 */
	private static function get_border_conversion_fix( array $attrs, string $attr_key, string $selector ): array {
		$styles_path = $attrs[ $attr_key ]['decoration']['border']['desktop']['value']['styles']
			?? $attrs[ $attr_key ]['decoration']['border']['border']['desktop']['value']['styles']
			?? null;

		if ( ! is_array( $styles_path ) ) {
			return array();
		}

		$all_style = $styles_path['all']['style'] ?? '';
		if ( empty( $all_style ) || 'none' === $all_style ) {
			return array();
		}

		$all_width    = $styles_path['all']['width'] ?? null;
		$all_color    = $styles_path['all']['color'] ?? null;
		$bottom_width = $styles_path['bottom']['width'] ?? null;
		$advanced     = array();

		// Fix width: if all.width > 1 and no per-side overrides, it's a D4 bottom-only value.
		$all_width_num = $all_width ? (int) $all_width : 0;
		if ( ! $all_width || ( $all_width_num > 1 && ! isset( $styles_path['top']['width'] ) && ! isset( $styles_path['left']['width'] ) ) ) {
			foreach ( array( 'border-top-width', 'border-left-width', 'border-right-width' ) as $prop ) {
				$advanced[] = array(
					'componentName' => 'divi/common',
					'props'         => array(
						'selector'  => $selector,
						'attr'      => array( 'desktop' => array( 'value' => '1px' ) ),
						'property'  => $prop,
						'important' => true,
					),
				);
			}
			// Bottom: use converted all.width (D4 bottom value) or bottom.width.
			$bw         = $bottom_width ?: $all_width ?: '1';
			$bw         = is_numeric( $bw ) ? $bw . 'px' : $bw;
			$advanced[] = array(
				'componentName' => 'divi/common',
				'props'         => array(
					'selector'  => $selector,
					'attr'      => array( 'desktop' => array( 'value' => $bw ) ),
					'property'  => 'border-bottom-width',
					'important' => true,
				),
			);
		}

		// Fix color: Divi uses #333 when D4 has no explicit color.
		if ( ! $all_color || '#333333' === $all_color ) {
			$advanced[] = array(
				'componentName' => 'divi/common',
				'props'         => array(
					'selector'  => $selector,
					'attr'      => array( 'desktop' => array( 'value' => '#dddddd' ) ),
					'property'  => 'border-color',
					'important' => true,
				),
			);
		}

		return $advanced;
	}
}
