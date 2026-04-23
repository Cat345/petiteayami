// External dependencies.
import React, { ReactElement } from 'react';

// Divi dependencies.
import {
  StyleContainer,
  StylesProps,
} from '@divi/module';

// Local dependencies.
import { MiniCartAttrs } from './types';

/**
 * Mini Cart Module's style components.
 *
 * @since 1.0.0
 */
export const ModuleStyles = ({
  attrs,
  elements,
  settings,
  orderClass,
  mode,
  state,
  noStyleTag,
}: StylesProps<MiniCartAttrs>): ReactElement => {
  const moduleOrderClass = orderClass || '';
  return (
    <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
      {/* Module decoration styles (border, box shadow, spacing) */}
      {elements.style({
        attrName: 'module',
        styleProps: {
          disabledOn: {
            disabledModuleVisibility: settings?.disabledModuleVisibility,
          },
        },
      })}

      {/* Module Text Typography styles (common font for all text) */}
      {/* Selector: {{selector}} */}
      {elements.style({
        attrName: 'miniCartModuleTextTypo',
      })}

      {/* Heading Typography styles (font, size, color) */}
      {/* Selector: {{selector}} .wfacp_mini_cart_start_h .wfacp-order-summary-label */}
      {elements.style({
        attrName: 'miniCartHeadingTypo',
      })}

      {/* Product Cart Typography styles */}
      {/* Selector: Multiple product selectors (matches Divi 4: mini_cart_product_typo) */}
      {elements.style({
        attrName: 'miniCartProductTypo',
      })}

      {/* Strike Through Typography styles */}
      {/* Selector: .product-total del (matches Divi 4: mini_cart_strike_through_typo) */}
      {elements.style({
        attrName: 'miniCartStrikeThroughTypo',
      })}

      {/* Low Stock Typography styles */}
      {/* Selector: .wfacp_stocks (matches Divi 4: mini_cart_low_stock_message_typo) */}
      {elements.style({
        attrName: 'miniCartLowStockTypo',
      })}

      {/* Saving Text Typography styles */}
      {/* Selector: .wfacp-saving-amount (matches Divi 4: mini_cart_enable_saving_price_message_typo) */}
      {elements.style({
        attrName: 'miniCartSavingTextTypo',
      })}

      {/* Product Cart Image Border styles */}
      {/* Selector: .product-image img (matches Divi 4: mini_cart_product_image_border) */}
      {elements.style({
        attrName: 'miniCartProductImageBorder',
      })}

      {/* Cart Total Typography styles (matches Divi 4: mini_cart_product_meta_typo) */}
      {/* Selector: table.wfacp_mini_cart_reviews tr:not(.order-total):not(.cart-discount) + cells */}
      {elements.style({
        attrName: 'miniCartProductMetaTypo',
      })}

      {/* Cart Total - Cart Label Typography (matches Divi 4: mini_cart_total_label_typo) */}
      {elements.style({
        attrName: 'mini_cart_total_label_typo',
      })}

      {/* Cart Total - Cart Price Typography (matches Divi 4: mini_cart_total_typo) */}
      {elements.style({
        attrName: 'mini_cart_total_typo',
      })}

      {/* Coupon Link Typography styles */}
      {/* Selector: .wfacp_main_showcoupon (matches Divi 4: mini_cart_coupon_heading_typo) */}
      {elements.style({
        attrName: 'miniCartCouponLinkTypo',
      })}

      {/* Coupon Input Label Typography styles */}
      {/* Selector: .wfacp-form-control-label (matches Divi 4: wfacp_form_mini_cart_coupon_label_typo) */}
      {elements.style({
        attrName: 'miniCartCouponInputLabelTypo',
      })}

      {/* Coupon Input Field Typography styles */}
      {/* Selector: .wfacp-form-control (matches Divi 4: wfacp_form_mini_cart_coupon_input_typo) */}
      {elements.style({
        attrName: 'miniCartCouponInputFieldTypo',
      })}

      {/* Coupon Input Field Border styles */}
      {/* Selector: .wfacp-form-control (matches Divi 4: wfacp_form_mini_cart_coupon_border) */}
      {elements.style({
        attrName: 'miniCartCouponInputFieldBorder',
      })}

      {/* Coupon focus color (matches Divi 4: wfacp_form_mini_cart_coupon_focus_color) */}
      {/* Color picker only — manual CSS for border-color + box-shadow */}
      {(() => {
        const focusAttr = attrs?.wfacpFormMiniCartCouponFocusColor;
        // Check user-saved value (D4 path from color picker) FIRST, then D5 default path.
        // Color picker saves to decoration.background.desktop.value.color,
        // but module.json default is at decoration.background.color.desktop.value.hex.
        // If we check D5 path first, the default always wins over the user's saved value.
        const colorValue = focusAttr?.decoration?.background?.desktop?.value?.color
          || focusAttr?.decoration?.background?.color?.desktop?.value?.hex;

        const oc = moduleOrderClass;
        if (colorValue && oc) {
          const selector = `${oc} .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-form-control:focus`;
          const css = `${selector} { border-color: ${colorValue} !important; box-shadow: 0 0 0 1px ${colorValue} !important; }`;
          return <style dangerouslySetInnerHTML={{ __html: css }} />;
        }
        return null;
      })()}

      {/* Mini Cart Settings - Spacing (Margin + Padding) */}
      {elements.style({
        attrName: 'wfacp_mini_cart_spacing',
      })}

      {/* Mini Cart Settings - Border */}
      {elements.style({
        attrName: 'wfacp_mini_cart_border',
        styleProps: {
          advancedStyles: (() => {
            // Fix D4→D5 conversion: border width/color not included when D4 uses defaults.
            const fb = attrs?.wfacp_mini_cart_border?.decoration?.border?.desktop?.value?.styles
              ?? attrs?.wfacp_mini_cart_border?.decoration?.border?.border?.desktop?.value?.styles;
            const hasStyle = fb?.all?.style && fb.all.style !== 'none';
            const hasWidth = fb?.all?.width;
            const hasColor = fb?.all?.color;
            const styles: any[] = [];
            const selector = `${orderClass || ''} .wfacp_mini_cart_start_h`;
            if (hasStyle && !hasWidth) {
              // Set individual sides to avoid overriding per-side widths from conversion
              ['border-top-width', 'border-left-width', 'border-right-width'].forEach(prop => {
                styles.push({
                  componentName: 'divi/common',
                  props: { selector, attr: { desktop: { value: '1px' } }, property: prop, important: true },
                });
              });
              // Only set bottom if no explicit bottom width from conversion
              if (!fb?.bottom?.width) {
                styles.push({
                  componentName: 'divi/common',
                  props: { selector, attr: { desktop: { value: '1px' } }, property: 'border-bottom-width', important: true },
                });
              }
            }
            if (hasStyle && !hasColor) {
              styles.push({
                componentName: 'divi/common',
                props: { selector, attr: { desktop: { value: '#dddddd' } }, property: 'border-color', important: true },
              });
            }
            return styles;
          })(),
        },
      })}

      {/* Mini Cart Settings - Background */}
      {elements.style({
        attrName: 'wfacp_mini_cart_background',
      })}

      {/* Coupon Button Typography styles */}
      {/* Selector: button.wfacp-coupon-btn (matches Divi 4: wfacp_form_mini_cart_coupon_button_typo) */}
      {elements.style({
        attrName: 'miniCartCouponButtonTypo',
      })}

      {/* Coupon Button Background styles (normal state) */}
      {/* Selector: button.wfacp-coupon-btn:not([disabled]) (matches Divi 4: mini_cart_coupon_btn_color) */}
      {elements.style({
        attrName: 'miniCartCouponButtonBackground',
      })}

      {/* Coupon Button Background Hover styles */}
      {elements.style({
        attrName: 'miniCartCouponButtonHoverBackground',
      })}

      {/* Coupon - Hover Label Color (button text color on hover) */}
      {/* Selector: .wfacp_mini_cart_start_h button.wfacp-coupon-btn:not([disabled]):hover */}
      {elements.style({
        attrName: 'mini_cart_coupon_btn_hover_label_color',
      })}

      {/* Coupon Code Label Typography (matches Divi 4: mini_cart_coupon_display_label_color + mini_cart_coupon_display_font_size) */}
      {/* Selector: tr.cart-discount th, th span:not(.wfacp_coupon_code) */}
      {elements.style({
        attrName: 'miniCartCouponCodeLabelTypo',
      })}

      {/* Coupon Code Value Typography (matches Divi 4: mini_cart_coupon_display_val_color) */}
      {/* Selector: tr.cart-discount td, td span, td a, th .wfacp_coupon_code */}
      {elements.style({
        attrName: 'miniCartCouponCodeValueTypo',
      })}

      {/* Divider Border Color (matches Divi 4: mini_cart_divider_color) */}
      {/* Color picker only — manual CSS for border-color */}
      {(() => {
        const dividerAttr = attrs?.mini_cart_divider_color;
        const colorValue = dividerAttr?.decoration?.background?.color?.desktop?.value?.hex
          || dividerAttr?.decoration?.background?.desktop?.value?.color;

        const oc = moduleOrderClass;
        if (colorValue && oc) {
          const selectors = [
            `${oc} .wfacp_mini_cart_start_h .wfacp_mini_cart_divi .cart_item`,
            `${oc} .wfacp_mini_cart_start_h table.shop_table tr.cart-subtotal`,
            `${oc} .wfacp_mini_cart_start_h table.shop_table tr.order-total`,
            `${oc} .wfacp_mini_cart_start_h table.shop_table tr.wfacp_ps_error_state td`,
            `${oc} .wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page`,
            `${oc} .wfacp_wrapper_start.wfacp_mini_cart_start_h .wfacp_mini_cart_elementor .cart_item`,
            `${oc} .wfacp_mini_cart_start_h .wfacp-coupon-section .wfacp-coupon-page`,
          ].join(', ');
          const css = `${selectors} { border-color: ${colorValue} !important; }`;
          return <style dangerouslySetInnerHTML={{ __html: css }} />;
        }
        return null;
      })()}

    </StyleContainer>
  );
};
