// External dependencies.
import React, { ReactElement } from 'react';

// Divi dependencies.
import {
  StyleContainer,
  StylesProps,
} from '@divi/module';
import { useModule } from '@divi/module';

// Local dependencies.
import { CheckoutFormAttrs } from './types';

/**
 * Checkout Form Module's style components.
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
}: StylesProps<CheckoutFormAttrs>): ReactElement => {
  // Get orderClass for manual CSS injection if needed
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

      {/* Collapsible Order Summary - Collapsed Background */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_mb_mini_cart_wrap .wfacp_mb_cart_accordian */}
      {elements.style({
        attrName: 'collapsible_order_summary_bg_color',
      })}

      {/* Collapsible Order Summary - Expanded Background */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_mb_mini_cart_sec_accordion_content */}
      {elements.style({
        attrName: 'expanded_order_summary_bg_color',
      })}

      {/* Product Cart Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_show_icon_wrap a span, {{selector}} #wfacp-e-form .wfacp_show_price_wrap span */}
      {elements.style({
        attrName: 'expanded_order_summary_link_color',
      })}

      {/* Collapsible Order Summary Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_mb_mini_cart_wrap .wfacp_mb_cart_accordian */}
      {elements.style({
        attrName: 'wfacp_collapsible_border',
      })}

      {/* Collapsible Order Summary Spacing */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_collapsible_order_summary_wrap */}
      {elements.style({
        attrName: 'wfacp_collapsible_margin',
      })}

      {/* Step Tab Spacing - Spacing */}
      {/* Selector: {{selector}} #wfacp-e-form .tab */}
      {elements.style({
        attrName: 'wfacp_tab_margin',
      })}

      {/* Step Tab Heading Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-order2StepTitle.wfacp-order2StepTitleS1 */}
      {elements.style({
        attrName: 'tab_heading_typography',
      })}

      {/* Step Tab Sub-heading Typography - Subheading */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-order2StepSubTitle.wfacp-order2StepSubTitleS1 */}
      {elements.style({
        attrName: 'tab_subheading_typography',
      })}

      {/* Active Step Text Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp_tcolor */}
      {elements.style({
        attrName: 'active_step_text_color',
      })}

      {/* Inactive Step Text Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp_tcolor */}
      {elements.style({
        attrName: 'inactive_step_text_color',
      })}

      {/* Breadcrumb Typography - Heading */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a */}
      {elements.style({
        attrName: 'breadcrumb_heading_typography',
      })}

      {/* Breadcrumb Typography - Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a */}
      {elements.style({
        attrName: 'breadcrumb_text_color',
      })}

      {/* Breadcrumb Typography - Hover Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a:hover */}
      {(() => {
        const hoverColorAttr = attrs?.breadcrumb_text_hover_color;
        const colorValue = hoverColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || hoverColorAttr?.decoration?.background?.desktop?.value?.color;

        if (colorValue && moduleOrderClass) {
          const selector = `${moduleOrderClass} #wfacp-e-form .wfacp-form .wfacp_main_form.woocommerce .wfacp_steps_sec ul li a:hover`;
          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selector} { color: ${colorValue} !important; }`,
              }}
            />
          );
        }
        return null;
      })()}

      {/* Step Tab Active Color - Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active */}
      {elements.style({
        attrName: 'active_step_bg_color',
      })}

      {/* Step Tab Active Color - Count Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber */}
      {elements.style({
        attrName: 'active_step_count_bg_color',
      })}

      {/* Step Tab Active Color - Count Text Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber */}
      {elements.style({
        attrName: 'active_step_count_text_color',
      })}

      {/* Step Tab Active Color - Count Border Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber */}
      {(() => {
        const borderColorAttr = attrs?.active_step_count_border_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = borderColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || borderColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selector = `${finalOrderClass} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber`;

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selector} { border-color: ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Step Tab Inactive Color - Inactive Step Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list */}
      {elements.style({
        attrName: 'inactive_step_bg_color',
      })}

      {/* Step Tab Inactive Color - Inactive Step Count Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber */}
      {elements.style({
        attrName: 'inactive_step_count_bg_color',
      })}

      {/* Step Tab Inactive Color - Inactive Count Text Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber */}
      {elements.style({
        attrName: 'inactive_step_count_text_color',
      })}

      {/* Step Tab Inactive Color - Inactive Count Border Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber */}
      {(() => {
        const borderColorAttr = attrs?.inactive_step_count_border_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = borderColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || borderColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selector = `${finalOrderClass} #wfacp-e-form .wfacp_form_steps .wfacp-payment-tab-list .wfacp-order2StepNumber`;

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selector} { border-color: ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Step Tab Active Color - Active Tab Border Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-payment-tab-list.wfacp-active */}
      {(() => {
        const borderColorAttr = attrs?.active_tab_border_bottom_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = borderColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || borderColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selector = `${finalOrderClass} #wfacp-e-form .wfacp-payment-tab-list.wfacp-active`;

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selector} { border-bottom-color: ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Step Tab Inactive Color - Tab Border Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-payment-tab-list */}
      {(() => {
        const borderColorAttr = attrs?.inactive_tab_border_bottom_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = borderColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || borderColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selector = `${finalOrderClass} #wfacp-e-form .wfacp-payment-tab-list`;

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selector} { border-bottom-color: ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Section Color - Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-section */}
      {elements.style({
        attrName: 'section_bg_color',
      })}

      {/* Section Color - Text Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-section */}
      {elements.style({
        attrName: 'section_text_color',
      })}

      {/* Section Spacing */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-section */}
      {elements.style({
        attrName: 'section_spacing',
      })}

      {/* Section Box Shadow */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-section */}
      {elements.style({
        attrName: 'section_box_shadow',
      })}

      {/* Section Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-section */}
      {elements.style({
        attrName: 'section_border',
        styleProps: {
          advancedStyles: (() => {
            // Fix D4→D5 conversion: border width/color not included when D4 uses defaults.
            const sb = attrs?.section_border?.decoration?.border?.desktop?.value?.styles
              ?? attrs?.section_border?.decoration?.border?.border?.desktop?.value?.styles;
            const hasStyle = sb?.all?.style && sb.all.style !== 'none';
            const allWidth = sb?.all?.width;
            const hasColor = sb?.all?.color;
            const bottomWidth = sb?.bottom?.width;
            const styles: any[] = [];
            const selector = `${moduleOrderClass} #wfacp-e-form .wfacp-section`;

            // Fix width: D4 converter puts per-side width into styles.all.
            const allWidthNum = allWidth ? parseInt(allWidth, 10) : 0;
            const needsWidthFix = hasStyle && (!allWidth || (allWidthNum > 1 && !sb?.top?.width && !sb?.left?.width));
            if (needsWidthFix) {
              ['border-top-width', 'border-left-width', 'border-right-width'].forEach(prop => {
                styles.push({
                  componentName: 'divi/common',
                  props: { selector, attr: { desktop: { value: '1px' } }, property: prop, important: true },
                });
              });
              const bw = bottomWidth || allWidth || '1';
              styles.push({
                componentName: 'divi/common',
                props: { selector, attr: { desktop: { value: bw + (String(bw).includes('px') ? '' : 'px') } }, property: 'border-bottom-width', important: true },
              });
            }
            if (hasStyle && (!hasColor || hasColor === '#333333')) {
              styles.push({
                componentName: 'divi/common',
                props: { selector, attr: { desktop: { value: '#dddddd' } }, property: 'border-color', important: true },
              });
            }
            return styles;
          })(),
        },
      })}

      {/* Order Summary Product Typography - Product Cart */}
      {/* Selector: {{selector}} #wfacp-e-form table.shop_table tbody .wfacp_order_summary_item_name, ... */}
      {elements.style({
        attrName: 'order_summary_cart_item_typo',
      })}

      {/* Order Summary Sub Total Typography - Sub Total */}
      {/* Selector: {{selector}} #wfacp-e-form table.shop_table tfoot tr:not(.order-total):not(.cart-discount), ... */}
      {elements.style({
        attrName: 'order_summary_product_meta_typo',
      })}

      {/* Order Summary Total Label Typography - Total Label */}
      {/* Selector: {{selector}} #wfacp-e-form table.shop_table tfoot tr.order-total th, ... */}
      {elements.style({
        attrName: 'order_summary_cart_total_label_typo',
      })}

      {/* Order Summary Total Price Typography - Total Price */}
      {/* Selector: {{selector}} #wfacp-e-form table.shop_table tfoot tr.order-total td, ... */}
      {elements.style({
        attrName: 'order_summary_cart_subtotal_heading_typo',
      })}

      {/* Order Summary - Image Border */}
      {/* Selector: {{selector}} #wfacp-e-form table.shop_table tr.cart_item .product-image img */}
      {elements.style({
        attrName: 'order_summary_image_border',
      })}

      {/* Order Summary - Divider Color */}
      {/* Selector: {{selector}} #wfacp-e-form table.shop_table tbody .wfacp_order_summary_item_name, ... */}
      {(() => {
        const borderColorAttr = attrs?.order_summary_divider_line_color;

        // Check for responsive color value first (desktop.value.color)
        let colorValue = borderColorAttr?.decoration?.border?.border?.desktop?.value?.styles?.all?.desktop?.value?.color;
        if (colorValue) {
          // Merge responsive color into base path so Border component can process it
          if (attrs?.order_summary_divider_line_color?.decoration?.border?.border?.desktop?.value?.styles?.all) {
            attrs.order_summary_divider_line_color.decoration.border.border.desktop.value.styles.all.color = colorValue;
          }
        } else {
          // Fall back to default color
          colorValue = borderColorAttr?.decoration?.border?.border?.desktop?.value?.styles?.all?.color;
        }

        const styleResult = elements.style({
          attrName: 'order_summary_divider_line_color',
        });

        // CRITICAL FALLBACK: If Border component doesn't generate CSS, manually inject it
        const finalOrderClass = styleResult?.props?.orderClass || moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selector = `${finalOrderClass} #wfacp-e-form table.shop_table tbody .wfacp_order_summary_item_name, ${finalOrderClass} #wfacp-e-form table.shop_table tr.cart_item, ${finalOrderClass} #wfacp-e-form table.shop_table tr.cart-subtotal, ${finalOrderClass} #wfacp-e-form table.shop_table tr.order-total`;

          return (
            <>
              {styleResult}
              <style
                dangerouslySetInnerHTML={{
                  __html: `${selector} { border-color: ${colorValue} !important; }`,
                }}
              />
            </>
          );
        }

        return styleResult;
      })()}

      {/* Payment Method Typography */}
      {elements.style({
        attrName: 'wfacp_form_payment_method_typo',
      })}

      {/* Payment Method Description Color - manual CSS for text color from color-picker */}
      {(() => {
        const descColorAttr = attrs?.wfacp_form_payment_method_description_color;
        const colorValue = descColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || descColorAttr?.decoration?.background?.desktop?.value?.color;
        if (colorValue && moduleOrderClass) {
          const selector = `${moduleOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #payment .payment_methods li .payment_box p, ${moduleOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #payment .payment_methods li .payment_box p span, ${moduleOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #payment .payment_methods li .payment_box p strong`;
          return (
            <style dangerouslySetInnerHTML={{
              __html: `${selector} { color: ${colorValue} !important; }`,
            }} />
          );
        }
        return null;
      })()}

      {/* Payment Method Information Background Color */}
      {elements.style({
        attrName: 'wfacp_form_payment_method_description_bg_color',
      })}

      {/* Privacy Policy Typography */}
      {elements.style({
        attrName: 'wfacp_privacy_policy_font',
      })}

      {/* Terms & Conditions Typography */}
      {elements.style({
        attrName: 'wfacp_terms_conditions_font',
      })}

      {/* Coupon Typography Fields */}
      {elements.style({
        attrName: 'order_coupon_coupon_typography',
      })}

      {elements.style({
        attrName: 'order_coupon_label_typo',
      })}

      {elements.style({
        attrName: 'order_coupon_input_typo',
      })}

      {elements.style({
        attrName: 'order_coupon_button_typo',
      })}

      {/* Coupon Focus Color — color picker only, rendered as box-shadow (matches D4 behavior) */}
      {(() => {
        const focusAttr = attrs?.order_coupon_focus_color;
        // D5 native path (color picker saves here) or D4-converted path (conversion outline maps here)
        const colorValue = focusAttr?.decoration?.background?.color?.desktop?.value?.hex
          || focusAttr?.decoration?.background?.desktop?.value?.color
          || focusAttr?.decoration?.font?.font?.desktop?.value?.color;
        const oc = moduleOrderClass;
        if (colorValue && oc) {
          // Divi's StyleContainer auto-adds .et-db #et-boc .et-l prefix — do NOT add it or it doubles
          // Repeat class selectors to beat general form focus specificity (0,2,8,1) → ours becomes (0,2,10,1)
          const selector = `${oc} #wfacp-e-form .wfacp-coupon-section.wfacp-coupon-section .wfacp-coupon-page p.wfacp-form-control-wrapper .wfacp-form-control.wfacp-form-control:focus`;
          const css = `${selector} { border-color: ${colorValue} !important; box-shadow: 0 0 0 1px ${colorValue} !important; }`;
          return <style dangerouslySetInnerHTML={{ __html: css }} />;
        }
        return null;
      })()}

      {elements.style({
        attrName: 'order_coupon_btn_bg_color',
      })}

      {elements.style({
        attrName: 'order_coupon_btn_text_color',
      })}

      {elements.style({
        attrName: 'order_coupon_btn_bg_hover_color',
      })}

      {elements.style({
        attrName: 'order_coupon_btn_bg_hover_text_color',
      })}

      {elements.style({
        attrName: 'order_coupon_coupon_border',
      })}

      {/* Product Switcher Selected Item Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_product_sec .wfacp_product_name_inner *, ... */}
      {elements.style({
        attrName: 'selected_item_typography',
      })}

      {/* Product Switcher Selected Saving Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text, ... */}
      {elements.style({
        attrName: 'selected_you_save_typo',
      })}

      {/* Product Switcher Best Value Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value */}
      {elements.style({
        attrName: 'product_switching_best_value_typography',
      })}

      {/* Product Switcher Whats Include Heading Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included h3 */}
      {elements.style({
        attrName: 'product_switching_what_included_heading',
      })}

      {/* Product Switcher Whats Include Product Title Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description h4 */}
      {elements.style({
        attrName: 'product_switching_what_included_product_title',
      })}

      {/* Product Switcher Whats Include Product Description Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included .wfacp_product_switcher_description .wfacp_description p, ... */}
      {elements.style({
        attrName: 'product_switching_what_included_product_description',
      })}

      {/* Product Switcher Non Selected Item Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_product_sec .wfacp_product_name_inner *, ... */}
      {elements.style({
        attrName: 'product_switching_optional_item_typography',
      })}

      {/* Product Switcher Non Selected Saving Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text, ... */}
      {elements.style({
        attrName: 'non_selected_you_save_typo',
      })}

      {/* Heading Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_section_heading.wfacp_section_title */}
      {elements.style({
        attrName: 'section_heading_typo',
      })}

      {/* Sub Heading Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-comm-title h4 */}
      {elements.style({
        attrName: 'section_sub_heading_typo',
      })}

      {/* Heading Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title */}
      {elements.style({
        attrName: 'form_heading_bg_color',
      })}

      {/* Heading Spacing */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title */}
      {elements.style({
        attrName: 'form_heading_spacing',
      })}

      {/* Heading Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title */}
      {elements.style({
        attrName: 'form_heading_border',
      })}

      {/* Form Fields Label Typography */}
      {elements.style({
        attrName: 'wfacp_form_fields_label_typo',
      })}

      {/* Form Fields Input Typography */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_typo',
      })}

      {/* Form Fields Input Background Color */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_bg_color',
      })}

      {/* Form Fields Border */}
      {elements.style({
        attrName: 'wfacp_form_fields_border',
      })}

      {/* Form Fields Focus Color */}
      {(() => {
        const focusColorAttr = attrs?.wfacp_form_fields_focus_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = focusColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || focusColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selectors = [
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.wfacp_coupon_code):focus`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) input[type="radio"]:focus`,
            `${finalOrderClass} #wfacp-e-form p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.input-checkbox):focus`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.wfacp_coupon_failed .wfacp_coupon_code`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single .select2-selection__rendered:focus`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single:focus>span.select2-selection__rendered`,
          ].join(', ');

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selectors} { border-color: ${colorValue} !important; box-shadow: 0 0 0 1px ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Form Fields Validation Color */}
      {(() => {
        const validationColorAttr = attrs?.wfacp_form_fields_validation_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = validationColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || validationColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selectors = [
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.woocommerce-invalid-required-field .wfacp-form-control`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.woocommerce-invalid-email .wfacp-form-control`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_coupon_failed .wfacp_coupon_code`,
          ].join(', ');

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selectors} { border-color: ${colorValue} !important; box-shadow: 0 0 0 1px ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Product Switcher Selected Item Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item, ... */}
      {elements.style({
        attrName: 'product_switching_label_color',
      })}

      {/* Product Switcher Selected Item Price Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .wfacp-selected-product .product-price, ... */}
      {elements.style({
        attrName: 'product_switching_price_color',
      })}

      {/* Product Switcher Selected Item Variant Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form .wfacp_selected_attributes .wfacp_pro_attr_single span, ... */}
      {elements.style({
        attrName: 'product_switching_variant_color',
      })}

      {/* Product Switcher Selected You Save Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel .wfacp-selected-product .wfacp_you_save_text, ... */}
      {elements.style({
        attrName: 'selected_you_save_color',
      })}

      {/* Product Switcher Best Value Text Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value, ... */}
      {elements.style({
        attrName: 'product_switching_best_value_text_color',
      })}

      {/* Product Switcher Non Selected Item Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item .wfacp_row_wrap .wfacp_product_choosen_label .wfacp_product_switcher_item, ... */}
      {elements.style({
        attrName: 'product_switching_optional_label_color',
      })}

      {/* Product Switcher Non Selected Item Price Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .shop_table.wfacp-product-switch-panel .product-price, ... */}
      {elements.style({
        attrName: 'product_switching_optional_price_color',
      })}

      {/* Product Switcher Non Selected You Save Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-product-switch-panel fieldset:not(.wfacp-selected-product) .wfacp_you_save_text, ... */}
      {elements.style({
        attrName: 'non_selected_you_save_color',
      })}

      {/* Product Switcher Selected Item Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product */}
      {elements.style({
        attrName: 'product_switching_item_background',
      })}

      {/* Product Switcher Best Value Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value, ... */}
      {elements.style({
        attrName: 'product_switching_best_value_bg_color',
      })}

      {/* Product Switcher Whats Include Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included */}
      {elements.style({
        attrName: 'product_switching_what_included_bg',
      })}

      {/* Product Switcher Non Selected Background Color */}
      {/* Selector: {{selector}} .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product) */}
      {elements.style({
        attrName: 'product_switching_optional_background',
      })}

      {/* Product Switcher Non Selected Background Hover Color */}
      {/* Selector: {{selector}} .wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product):hover */}
      {elements.style({
        attrName: 'product_switching_optional_background_hover',
      })}

      {/* Product Switcher Best Value Item Border Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form .shop_table.wfacp-product-switch-panel .woocommerce-cart-form__cart-item.cart_item.wfacp_best_val_wrap */}
      {elements.style({
        attrName: 'product_switching_best_value_border_color',
      })}

      {/* Product Switcher Selected Item Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item.wfacp-selected-product */}
      {elements.style({
        attrName: 'product_switching_item_border',
      })}

      {/* Product Switcher Best Value Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #product_switching_field fieldset .wfacp_best_value, ... */}
      {elements.style({
        attrName: 'product_switching_best_value_border',
      })}

      {/* Product Switcher Whats Include Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_whats_included */}
      {elements.style({
        attrName: 'product_switching_what_included_border',
      })}

      {/* Product Switcher Non Select Cart Item Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-cart-form__cart-item.cart_item:not(.wfacp-selected-product) */}
      {elements.style({
        attrName: 'product_switching_border_non_selected',
      })}

      {/* Checkout Form Typography */}
      {/* Selector: body.wfacp_main_wrapper, body #wfacp-e-form *, ... (many selectors) */}
      {elements.style({
        attrName: 'wfacp_font_family_typography',
      })}

      {/* Checkout Button Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce #payment button#place_order, {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce button#place_order, {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-checkout button.button.button-primary.wfacp_next_page_button, {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_payment #ppcp-hosted-fields .button */}
      {elements.style({
        attrName: 'wfacp_form_payment_button_typo',
      })}

      {/* Checkout Button Sub Text Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-order-place-btn-wrap button:after, {{selector}} #wfacp-e-form .wfacp-next-btn-wrap button:after */}
      {elements.style({
        attrName: 'checkout_button_sub_text_font_size',
      })}

      {/* Checkout Button Width */}
      {elements.style({
        attrName: 'wfacp_button_width_width',
      })}

      {/* Checkout Button Background Color */}
      {elements.style({
        attrName: 'wfacp_button_bg_color',
      })}

      {/* Checkout Button Label Color */}
      {elements.style({
        attrName: 'wfacp_button_label_color',
      })}

      {/* Checkout Button Background Hover Color */}
      {elements.style({
        attrName: 'wfacp_button_bg_hover_color',
      })}

      {/* Checkout Button Label Hover Color */}
      {elements.style({
        attrName: 'wfacp_button_label_hover_color',
      })}

      {/* Checkout Button Border */}
      {elements.style({
        attrName: 'wfacp_button_border',
      })}

      {/* Checkout Button Padding */}
      {elements.style({
        attrName: 'wfacp_button_padding',
      })}

      {/* Checkout Button Margin */}
      {elements.style({
        attrName: 'wfacp_button_margin',
      })}

      {/* Return Link Color */}
      {elements.style({
        attrName: 'step_back_link_color',
      })}

      {/* Return Link Hover Color */}
      {elements.style({
        attrName: 'step_back_link_hover_color',
      })}

      {/* Additional Text Color */}
      {elements.style({
        attrName: 'additional_text_color',
      })}

      {/* Additional Background Color */}
      {elements.style({
        attrName: 'additional_bg_color',
      })}

      {/* Checkout Button Icon Color */}
      {elements.style({
        attrName: 'checkout_button_icon_color',
      })}

      {/* Checkout Button Sub Text Color */}
      {elements.style({
        attrName: 'checkout_button_sub_text_color',
      })}

      {/* Form Background Color */}
      {/* Selector: {{selector}} .wfacp_form_divi_container */}
      {elements.style({
        attrName: 'form_background_color',
      })}

      {/* Primary Color */}
      {/* Selector: {{selector}} #wfacp-e-form #payment li.wc_payment_method input.input-radio:checked::before, ... */}
      {elements.style({
        attrName: 'default_primary_color',
      })}

      {/* Primary Color: Extra checkbox/radio styles (not covered by styleProps background-color) */}
      {(() => {
        const primaryAttr = attrs?.default_primary_color;
        const colorValue = primaryAttr?.decoration?.background?.color?.desktop?.value?.hex
          || primaryAttr?.decoration?.background?.desktop?.value?.color;

        const oc = moduleOrderClass;
        if (colorValue && oc) {
          const css = `
            /* Radio: hide default ::before */
            ${oc} #wfacp-e-form #payment li.wc_payment_method input.input-radio:checked::before,
            ${oc} #wfacp-e-form #payment.wc_payment_method input[type=radio]:checked:before,
            ${oc} #wfacp-e-form input[type=radio]:checked:before,
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce input[type=radio]:checked:before { display:none !important; }

            /* Radio: border-width when checked */
            ${oc} #wfacp-e-form .wfacp_main_form #payment li.wc_payment_method input.input-radio:checked,
            ${oc} #wfacp-e-form .wfacp_main_form #payment.wc_payment_method input[type=radio]:checked,
            ${oc} #wfacp-e-form .wfacp_main_form input[type=radio]:checked { border-width:5px !important; }

            /* Radio: border-color when checked */
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce #payment li.wc_payment_method input.input-radio:checked,
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce #payment.wc_payment_method input[type=radio]:checked,
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce input[type=radio]:checked,
            ${oc} #wfacp-e-form input[type=radio]:checked { border-color:${colorValue} !important; }

            /* Checkbox: border-color when checked */
            ${oc} #wfacp-e-form .wfacp-form input[type=checkbox]:checked,
            ${oc} #wfacp-e-form .wfacp_main_form #payment input[type=checkbox]:checked,
            ${oc} #wfacp-e-form .wfacp_main_form .woocommerce-input-wrapper .wfacp-form-control:checked,
            ${oc} #wfacp-e-form .wfacp_main_form input[type=checkbox]:checked { border-color:${colorValue} !important; }

            /* Checkbox: show ::after, hide ::before */
            ${oc} #wfacp-e-form .wfacp_main_form input[type=checkbox]:after { display:block !important; }
            ${oc} #wfacp-e-form .wfacp_main_form input[type=checkbox]:before { display:none !important; }

            /* Checkbox: border-width when checked (creates filled effect) */
            ${oc} #wfacp-e-form .wfacp_main_form input[type=checkbox]:checked { border-width:8px !important; }

            /* Focus: border-color + box-shadow with primary color */
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.wfacp_coupon_code):focus,
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) input[type="radio"]:focus,
            ${oc} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.input-checkbox):focus { border-color:${colorValue} !important; box-shadow:0 0 0 1px ${colorValue} !important; }
          `;

          return (
            <style dangerouslySetInnerHTML={{ __html: css }} />
          );
        }

        return null;
      })()}

      {/* Content Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form .woocommerce-form-login.login p, ... */}
      {elements.style({
        attrName: 'default_text_color',
      })}

      {/* Form Link Color */}
      {/* Selector: {{selector}} #wfacp-e-form .woocommerce-form-login-toggle .woocommerce-info a, ... */}
      {elements.style({
        attrName: 'default_link_color',
      })}

      {/* Link Hover Color */}
      {/* Selector: {{selector}} #wfacp-e-form .woocommerce-form-login-toggle .woocommerce-info a:hover, ... */}
      {elements.style({
        attrName: 'default_link_hover_color',
      })}

      {/* Form Spacing */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp-form */}
      {elements.style({
        attrName: 'form_spacing',
      })}

      {/* Form Border */}
      {/* Selector: {{selector}} .wfacp_form_divi_container */}
      {elements.style({
        attrName: 'form_border',
        styleProps: {
          advancedStyles: (() => {
            // Fix D4→D5 conversion: border width not included when D4 uses default 1px.
            const fb = attrs?.form_border?.decoration?.border?.desktop?.value?.styles
              ?? attrs?.form_border?.decoration?.border?.border?.desktop?.value?.styles;
            const hasStyle = fb?.all?.style && fb.all.style !== 'none';
            const hasWidth = fb?.all?.width;
            if (hasStyle && !hasWidth) {
              return [{
                componentName: 'divi/common',
                props: {
                  selector: `${moduleOrderClass} .wfacp_form_divi_container`,
                  attr: { desktop: { value: '1px' } },
                  property: 'border-width',
                  important: true,
                },
              }];
            }
            return [];
          })(),
        },
      })}

      {/* Fields Typography - Label Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce p.wfacp-form-control-wrapper:not(.wfacp-anim-wrap) label.wfacp-form-control-label, ... */}
      {elements.style({
        attrName: 'wfacp_form_fields_label_typo',
      })}

      {/* Fields Typography - Input Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce input[type="text"]:not(.select2-search__field), ... */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_typo',
      })}

      {/* Fields - Label Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-form-control-label, ... */}
      {elements.style({
        attrName: 'wfacp_form_fields_label_color',
      })}

      {/* Fields - Input Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-input-wrapper .wfacp-form-control, ... */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_color',
      })}

      {/* Fields - Input Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-input-wrapper .wfacp-form-control:not(.input-checkbox), ... */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_bg_color',
      })}

      {/* Fields - Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce input[type="text"]:not(.select2-search__field), ... */}
      {elements.style({
        attrName: 'wfacp_form_fields_border',
      })}

      {/* Fields - Focus Color and Validation Color use manual CSS via IIFE above */}

      {/* Heading Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_section_heading.wfacp_section_title */}
      {elements.style({
        attrName: 'section_heading_typo',
      })}

      {/* Sub Heading Typography */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-comm-title h4 */}
      {elements.style({
        attrName: 'section_sub_heading_typo',
      })}

      {/* Heading Background Color */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title */}
      {elements.style({
        attrName: 'form_heading_bg_color',
      })}

      {/* Heading Spacing */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title */}
      {elements.style({
        attrName: 'form_heading_spacing',
      })}

      {/* Heading Border */}
      {/* Selector: {{selector}} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section .wfacp-comm-title */}
      {elements.style({
        attrName: 'form_heading_border',
      })}

      {/* Form Fields Label Typography */}
      {elements.style({
        attrName: 'wfacp_form_fields_label_typo',
      })}

      {/* Form Fields Input Typography */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_typo',
      })}

      {/* Form Fields Input Background Color */}
      {elements.style({
        attrName: 'wfacp_form_fields_input_bg_color',
      })}

      {/* Form Fields Border */}
      {elements.style({
        attrName: 'wfacp_form_fields_border',
      })}

      {/* Form Fields Focus Color */}
      {(() => {
        const focusColorAttr = attrs?.wfacp_form_fields_focus_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = focusColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || focusColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selectors = [
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.wfacp_coupon_code):focus`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.form-row:not(.woocommerce-invalid-email) input[type="radio"]:focus`,
            `${finalOrderClass} #wfacp-e-form p.form-row:not(.woocommerce-invalid-email) .wfacp-form-control:not(.input-checkbox):focus`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.wfacp_coupon_failed .wfacp_coupon_code`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single .select2-selection__rendered:focus`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .form-row:not(.woocommerce-invalid-required-field) .woocommerce-input-wrapper .select2-container .select2-selection--single:focus>span.select2-selection__rendered`,
          ].join(', ');

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selectors} { border-color: ${colorValue} !important; box-shadow: 0 0 0 1px ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* Form Fields Validation Color */}
      {(() => {
        const validationColorAttr = attrs?.wfacp_form_fields_validation_color;

        // Read color from background decoration (D5 native: color.desktop.value.hex, D4-converted: desktop.value.color)
        const colorValue = validationColorAttr?.decoration?.background?.color?.desktop?.value?.hex
          || validationColorAttr?.decoration?.background?.desktop?.value?.color;

        const finalOrderClass = moduleOrderClass;
        if (colorValue && finalOrderClass) {
          const selectors = [
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.woocommerce-invalid-required-field .wfacp-form-control`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce p.woocommerce-invalid-email .wfacp-form-control`,
            `${finalOrderClass} #wfacp-e-form .wfacp_main_form.woocommerce .wfacp_coupon_failed .wfacp_coupon_code`,
          ].join(', ');

          return (
            <style
              dangerouslySetInnerHTML={{
                __html: `${selectors} { border-color: ${colorValue} !important; box-shadow: 0 0 0 1px ${colorValue} !important; }`,
              }}
            />
          );
        }

        return null;
      })()}

      {/* TODO: Add remaining checkout form specific style elements as attributes are added */}
      {/* Styles will be added incrementally as we implement each phase */}
    </StyleContainer>
  );
};
