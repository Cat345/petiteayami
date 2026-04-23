// External Dependencies.
import React, { ReactElement } from 'react';

// Divi Dependencies.
import {
  StyleContainer,
  StylesProps,
} from '@divi/module';

// Local Dependencies.
import { OptinFormAttrs } from './types';

/**
 * Optin Form Module styles component.
 *
 * @since 1.0.0
 *
 * @param {StylesProps<OptinFormAttrs>} props React component props.
 *
 * @returns {ReactElement}
 */
export const ModuleStyles = ({
  attrs,
  elements,
  orderClass,
  mode,
  state,
  noStyleTag,
}: StylesProps<OptinFormAttrs>): ReactElement => {
  return (
    <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
      {/* Module */}
      {elements.style({
        attrName: 'module',
      })}

      {/* Form Label Typography - Label */}
      {/* Selector: {{selector}} .bwfac_form_sec > label, {{selector}} .bwfac_form_sec.bwfac_form_field_radio label */}
      {elements.style({
        attrName: 'wfop_optin_form_label_typography',
      })}

      {/* Form Input Typography - Input Field */}
      {/* Selector: {{selector}} .bwfac_form_sec .wffn-optin-input, {{selector}} .bwfac_form_sec .wffn-optin-input::placeholder */}
      {elements.style({
        attrName: 'wfop_optin_form_field_typography',
      })}

      {/* Button Heading Typography - Heading */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading */}
      {elements.style({
        attrName: 'wfop_optin_form_button_text_typo',
      })}

      {/* Button Heading Text Alignment — explicit CSS since font textAlign may not output text-align */}
      {(() => {
        const textAlign = attrs?.wfop_optin_form_button_text_typo?.decoration?.font?.font?.desktop?.value?.textAlign ?? 'center';
        const selector = `${orderClass ?? ''} .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading`.trim();
        if (selector && textAlign) {
          return (
            <style dangerouslySetInnerHTML={{
              __html: `${selector} { text-align: ${textAlign} !important; }`,
            }} />
          );
        }
        return null;
      })()}

      {/* Button Sub Heading Typography - Sub Heading */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading */}
      {elements.style({
        attrName: 'wfop_optin_form_button_subheading_text_typo',
      })}

      {/* Form - Asterisk */}
      {/* Selector: {{selector}} .bwfac_form_sec > label > span, {{selector}} .bwfac_form_sec.bwfac_form_field_radio label > span */}
      {elements.style({
        attrName: 'wfop_optin_form_mark_required_color',
      })}

      {/* Form - Background */}
      {/* Selector: {{selector}} .bwfac_form_sec .wffn-optin-input */}
      {elements.style({
        attrName: 'wfop_optin_form_field_background_color',
      })}

      {/* Form Input Border - Border */}
      {/* Selector: {{selector}} .bwfac_form_sec .wffn-optin-input */}
      {elements.style({
        attrName: 'wfop_optin_form_field_border',
      })}

      {/* Form - Columns Gap */}
      {/* Selector: {{selector}} .wffn-custom-optin-from .bwfac_form_sec */}
      {elements.style({
        attrName: 'wfop_optin_form_column_gap_padding',
      })}

      {/* Form - Rows Gap */}
      {/* Selector: {{selector}} .wffn-custom-optin-from .bwfac_form_sec */}
      {elements.style({
        attrName: 'wfop_optin_form_row_gap_margin',
      })}

      {/* Submit Button - Button Alignment - explicit JS output so it applies on assigned selector */}
      {(() => {
        const btnTextAttr = attrs?.wfop_optin_form_submit_button_text?.advanced?.text;
        const textVal = btnTextAttr?.text?.desktop?.value;
        const align = textVal?.orientation ?? textVal?.align ?? 'left';
        const selector = `${orderClass ?? ''} .wffn-custom-optin-from #bwf-custom-button-wrap`.trim();
        return (
          <>
            {elements.style({
              attrName: 'wfop_optin_form_submit_button_text',
            })}
            {selector && align && (
              <style dangerouslySetInnerHTML={{
                __html: `${selector}{ text-align: ${align} !important; }`,
              }} />
            )}
          </>
        );
      })()}

      {/* Submit Button - Button Width */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit */}
      {elements.style({
        attrName: 'wfop_optin_form_button_width',
      })}


      {/* Submit Button - Button Background Color (Normal) */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit */}
      {elements.style({
        attrName: 'wfop_optin_form_button_bg_color',
      })}

      {/* Submit Button - Button Color (Hover) */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_heading, {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_subheading */}
      {elements.style({
        attrName: 'wfop_optin_form_button_hover_color',
      })}

      {/* Submit Button - Button Background Color (Hover) */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit:hover */}
      {elements.style({
        attrName: 'wfop_optin_form_button_hover_bg_color',
      })}

      {/* Submit Button - Button Border */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit */}
      {elements.style({
        attrName: 'bwf_button_border',
      })}

      {/* Submit Button - Button Padding */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit */}
      {elements.style({
        attrName: 'wfop_optin_form_button_text_padding',
      })}

      {/* Submit Button - Button Box Shadow */}
      {/* Selector: {{selector}} .bwfac_form_sec #wffn_custom_optin_submit */}
      {elements.style({
        attrName: 'button_text_alignment_box_shadow',
      })}

      {/* Input Size Padding, Border normalization */}
      {/* D4's divi.css applies box-sizing via .et-db selectors which don't match in D5 */}
      {/* D4 stores border-width as unitless numbers; D5 outputs them as-is → invalid CSS */}
      {(() => {
        const raw = attrs?.input_size;
        const inputSize = (typeof raw === 'string')
          ? raw
          : (raw?.desktop?.value ?? raw?.value ?? '12px');
        const formSelector = `${orderClass ?? ''} .wffn-custom-optin-from`.trim();
        const inputSelector = `${orderClass ?? ''} .bwfac_form_sec .wffn-optin-input`.trim();
        const rules: string[] = [];
        if (formSelector) {
          rules.push(`${formSelector} *, ${formSelector} *::before, ${formSelector} *::after { box-sizing: border-box; }`);
        }
        if (inputSelector && inputSize) {
          rules.push(`${formSelector} .wffn-optin-input { padding: ${inputSize} 15px !important; }`);
        }

        // Background color — apply from the designForm color picker
        const bgHex = attrs?.wfop_optin_form_field_background_color?.decoration?.background?.color?.desktop?.value?.hex;
        if (inputSelector && bgHex) {
          rules.push(`${inputSelector} { background-color: ${bgHex} !important; }`);
        }

        // Border width unit normalization — D4 conversion stores unitless numbers (e.g. "1")
        const borderVal = attrs?.wfop_optin_form_field_border?.decoration?.border?.desktop?.value;
        if (inputSelector && borderVal?.styles?.all?.width) {
          const w = String(borderVal.styles.all.width);
          if (/^\d+(\.\d+)?$/.test(w)) {
            const color = borderVal.styles.all.color || '#d8d8d8';
            const style = borderVal.styles.all.style || 'solid';
            rules.push(`${inputSelector} { border-width: ${w}px !important; border-color: ${color} !important; border-style: ${style} !important; }`);
          }
        }

        if (rules.length > 0) {
          return (
            <style dangerouslySetInnerHTML={{
              __html: rules.join('\n'),
            }} />
          );
        }
        return null;
      })()}

    </StyleContainer>
  );
};
