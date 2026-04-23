// External Dependencies.
import React, { ReactElement } from 'react';

// Divi Dependencies.
import {
  StyleContainer,
  StylesProps,
} from '@divi/module';

// Local Dependencies.
import { OptinFormPopupAttrs } from './types';

/**
 * Optin Form Popup Module styles component.
 *
 * @since 1.0.0
 *
 * @param {StylesProps<OptinFormPopupAttrs>} props React component props.
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
}: StylesProps<OptinFormPopupAttrs>): ReactElement => {
  return (
    <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
      {/* Base styles — matches D4 inline CSS output (rendered FIRST so elements.style() can override) */}
      {(() => {
        const raw = attrs?.input_size;
        const inputSize = (typeof raw === 'string')
          ? raw
          : (raw?.desktop?.value ?? raw?.value ?? '12px');
        const sel = orderClass ?? '';
        const formSelector = `${sel} .wffn-custom-optin-from`.trim();
        const inputSelector = `${sel} .bwfac_form_sec .wffn-optin-input`.trim();
        const btnSelector = `${sel} #wffn_custom_optin_submit`.trim();
        const rules: string[] = [];

        // Box sizing for form elements
        if (formSelector) {
          rules.push(`${formSelector} *, ${formSelector} *::before, ${formSelector} *::after { box-sizing: border-box; }`);
        }

        // Input padding from input_size attr
        if (inputSelector && inputSize) {
          rules.push(`${inputSelector} { padding: ${inputSize} 15px !important; }`);
        }

        // Submit button base display
        if (btnSelector) {
          rules.push(`${btnSelector} { display: inline-block; width: 100%; cursor: pointer; text-align: center; border-radius: 0; border-width: 2px; border-style: solid; }`);
        }

        // Submit button heading line-height (D4 default: 1.5)
        if (sel) {
          const btnFontVal = attrs?.button_text_typo?.decoration?.font?.font?.desktop?.value;
          const hasBtnLineHeight = btnFontVal?.lineHeight && btnFontVal.lineHeight !== '';
          if (!hasBtnLineHeight) {
            rules.push(`${sel} #wffn_custom_optin_submit .bwf_heading { line-height: 1.5; }`);
          }
        }

        // Overlay background
        if (sel) {
          rules.push(`${sel} .bwf_pp_overlay { background: rgba(0,0,0,0.75); }`);
        }

        // Popup wrap base styles
        if (sel) {
          rules.push(`${sel} .bwf_pp_wrap { position: relative; margin: auto; background: #fff; }`);
        }

        // Close button base styles (from plugin base CSS + Divi overrides)
        if (sel) {
          // Note: background and color are omitted — controlled by close_button_background_color and close_button_color via elements.style()
          // font-size is omitted — controlled by wfop_optin_form_popup_icon_size_font_size via elements.style()
          rules.push(`${sel} .bwf_pp_close { position: absolute; top: 0; right: 0; display: flex; align-items: center; justify-content: center; width: 25px; height: 25px; border-radius: 50%; text-decoration: none; border: none; z-index: 99; cursor: pointer; font-weight: 700; font-family: serif; line-height: 1; text-align: center; padding: 0 !important; margin: -12px -14px 0 0; }`);
        }

        // Input border fallback (D4 default: 2px solid #d8d8d8)
        if (sel) {
          rules.push(`${sel} .bwfac_form_sec .wffn-optin-input { border: 2px solid #d8d8d8; }`);
        }

        // Base font-size for form elements (D4 uses 16px base)
        if (sel) {
          rules.push(`${sel} .bwf_pp_cont { font-size: 16px; }`);
        }

        // Progress bar base styles (from plugin base CSS)
        if (sel) {
          // Note: padding, height, background-color are omitted here — they are controlled by
          // elements.style() via wfop_optin_form_popup_popup_progress_bar_padding,
          // wfop_optin_form_popup_popup_progress_bar_height, wfop_optin_form_popup_progress_background_color,
          // and wfop_optin_form_popup_progress_color respectively.
          rules.push(`${sel} .bwf_pp_bar_wrap { display: flex; overflow: hidden; margin-bottom: 20px; }`);

          // Progress bar fill width — read from the sizing attribute (same as settings panel).
          // User-saved path: decoration.sizing.desktop.value.width ("38%")
          // Default path: decoration.sizing.width.desktop.value.value ("75")
          const barWidthAttr = attrs?.wfop_optin_form_popup_popup_progress_bar_width;
          const barWidthSaved = barWidthAttr?.decoration?.sizing?.desktop?.value?.width;
          const barWidthDefault = barWidthAttr?.decoration?.sizing?.width?.desktop?.value?.value;
          const barPP = barWidthSaved ? String(barWidthSaved).replace('%', '') : (barWidthDefault || '75');
          if (barPP && !isNaN(Number(barPP))) {
            rules.push(`${sel} .bwf_pp_bar_wrap .bwf_pp_bar { width: ${barPP}%; }`);
          }
          rules.push(`${sel} .bwf_pp_bar_wrap .bwf_pp_bar { display: flex; flex-direction: column; justify-content: center; color: #fff; text-align: center; white-space: nowrap; font-size: 16px; font-weight: 600; transition: width 500ms ease-in-out; max-width: 100%; }`);
          rules.push(`${sel} .bwf_pp_bar_wrap .bwf_pp_bar.bwf_pp_animate { position: relative; }`);
          rules.push(`${sel} .bwf_pp_cont .bwf_pp_bar_wrap { box-sizing: unset !important; }`);
          rules.push(`${sel} .pp-bar-text { font-size: 16px; font-weight: 600; }`);
          rules.push(`${sel} .pp-bar-text-wrapper { text-align: center; display: none; }`);
          rules.push(`${sel} .pp-bar-text-wrapper.on { display: block; }`);
          rules.push(`${sel} .bwf_pp_bar_wrap.on span.pp-bar-text.inside { display: none; }`);
        }

        // Heading/subheading base styles
        if (sel) {
          rules.push(`${sel} .bwf_pp_opt_head { text-align: center; margin: 0 0 15px; }`);
          rules.push(`${sel} .bwf_pp_opt_sub_head { text-align: center; margin: 0 0 15px; }`);
        }

        // Footer base styles
        if (sel) {
          rules.push(`${sel} .bwf_pp_footer { text-align: center; margin-top: 10px; }`);
        }

        // Form field base styles
        if (sel) {
          rules.push(`${sel} .bwfac_form_sec label { display: block; text-align: left; }`);
          rules.push(`${sel} .wffn-optin-input { width: 100%; outline: none; text-align: left; }`);
          rules.push(`${sel} .wfop_hide_label label { display: none; }`);
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

      {/* Module */}
      {elements.style({
        attrName: 'module',
      })}

      {/* CTA Button - Alignment */}
      {elements.style({ attrName: 'btn_alignment' })}
      {elements.style({ attrName: 'btn_text_alignment' })}

      {/* CTA Button - Button Width */}
      {/* Selector: {{selector}} .bwf-custom-button .wfop_popup_form */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_popup_button_width',
      })}

      {/* CTA Button - Background Color (Normal) */}
      {/* Selector: {{selector}} .bwf-custom-button a */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_bg_color',
      })}

      {/* CTA Button - Text Color (Normal) */}
      {/* Selector: {{selector}} .bwf-custom-button a span */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_color',
      })}

      {/* CTA Button - Background Color (Hover) */}
      {/* Selector: {{selector}} .bwf-custom-button a:hover */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_hover_bg_color',
      })}

      {/* CTA Button - Text Color (Hover) */}
      {/* Selector: {{selector}} .bwf-custom-button a:hover span */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_hover_color',
      })}

      {/* CTA Button - Title Typography */}
      {/* Selector: {{selector}} .bwf-custom-button a span:not(.bwf_subheading) */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_text_typo',
      })}

      {/* CTA Button - Default line-height to match D4 output */}
      {(() => {
        const fontVal = attrs?.wfop_optin_form_popup_btn_text_typo?.decoration?.font?.font?.desktop?.value;
        const hasLineHeight = fontVal?.lineHeight && fontVal.lineHeight !== '';
        if (!hasLineHeight && orderClass) {
          return (
            <style dangerouslySetInnerHTML={{
              __html: `${orderClass} .bwf-custom-button a span:not(.bwf_subheading) { line-height: 1.5; }`,
            }} />
          );
        }
        return null;
      })()}

      {/* CTA Button - SubTitle Typography */}
      {/* Selector: {{selector}} .bwf-custom-button .bwf_subheading */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_subheading_text_typo',
      })}

      {/* Call To Action - Button Text Spacing (padding + margin) */}
      {/* Selector: {{selector}} .bwf-custom-button a */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_text_spacing',
      })}

      {/* CTA Button - Border */}
      {/* Selector: {{selector}} .bwf-custom-button a */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_text_alignment_border',
      })}

      {/* CTA Button - Box Shadow */}
      {/* Selector: {{selector}} .bwf-custom-button a */}
      {elements.style({
        attrName: 'wfop_optin_form_popup_btn_text_alignment_box_shadow',
      })}

      {/* ── Popup Overlay ── */}

      {/* Popup Bar Width */}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_bar_width' })}

      {/* Popup Content Padding */}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_padding' })}

      {/* Progress Bar */}
      {elements.style({ attrName: 'wfop_optin_form_popup_progress_bar_typography' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_progress_text_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_progress_background_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_progress_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_progress_bar_width' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_progress_bar_height' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_progress_bar_padding' })}

      {/* Popup Heading / Subheading */}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_heading' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_heading_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_subheading_typography' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_popup_subheading_color' })}

      {/* ── Form Fields ── */}

      {/* Label Typography & Colors */}
      {elements.style({ attrName: 'label_typography' })}
      {elements.style({ attrName: 'label_color' })}
      {elements.style({ attrName: 'mark_required_color' })}

      {/* Input Typography & Colors */}
      {elements.style({ attrName: 'field_typography' })}
      {elements.style({ attrName: 'field_text_color' })}
      {elements.style({ attrName: 'field_background_color' })}
      {elements.style({ attrName: 'field_border' })}

      {/* Form Column/Row Gap */}
      {elements.style({ attrName: 'wfop_optin_form_popup_pop_column_gap_padding' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_pop_row_gap_margin' })}

      {/* ── Submit Button (inside popup form) ── */}

      {/* Submit Button Alignment */}
      {elements.style({ attrName: 'wfop_optin_form_popup_button_alignment' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_button_text_alignment' })}

      {elements.style({ attrName: 'button_text_typo' })}
      {elements.style({ attrName: 'button_subheading_text_typo' })}
      {elements.style({ attrName: 'button_color' })}
      {elements.style({ attrName: 'button_hover_color' })}
      {elements.style({ attrName: 'button_bg_color' })}
      {elements.style({ attrName: 'button_hover_bg_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_button_width' })}
      {elements.style({ attrName: 'button_text_padding' })}
      {elements.style({ attrName: 'button_text_margin' })}
      {elements.style({ attrName: 'bwf_button_border' })}
      {elements.style({ attrName: 'button_text_alignment_box_shadow' })}

      {/* ── Text After Submit ── */}
      {elements.style({ attrName: 'wfop_optin_form_popup_text_after_submit_typography' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_text_after_submit_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_text_after_submit_letter_spacing' })}

      {/* ── Close Button ── */}
      {elements.style({ attrName: 'close_button_color' })}
      {elements.style({ attrName: 'close_button_background_color' })}
      {elements.style({ attrName: 'close_button_hover_color' })}
      {elements.style({ attrName: 'close_button_hover_background_color' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_icon_size_font_size' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_close_icon_position_margin' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_close_btn_inner_gap_padding' })}
      {elements.style({ attrName: 'wfop_optin_form_popup_close_btn_border' })}

      {/* Popup wrap max-width: convert percentage to pixels based on parent window
          so the popup appears the same size as on the frontend.
          Converted value is at decoration.sizing.desktop.value.maxWidth (e.g. "31%")
          Default is at decoration.sizing.maxWidth.desktop.value (e.g. {value:"50",unit:"%"}) */}
      {(() => {
        const sel = orderClass ?? '';
        if (!sel || typeof window === 'undefined') return null;
        try {
          const parentWidth = window?.top?.innerWidth || window.innerWidth || 1280;
          const barWidthAttr = attrs?.wfop_optin_form_popup_popup_bar_width?.decoration?.sizing;
          // Read converted value first (e.g. "31%"), fallback to default structure
          const convertedVal = barWidthAttr?.desktop?.value?.maxWidth;
          let pctValue = 0;
          if (typeof convertedVal === 'string' && convertedVal.endsWith('%')) {
            pctValue = parseFloat(convertedVal);
          } else {
            const defVal = barWidthAttr?.maxWidth?.desktop?.value;
            if (defVal?.unit === '%' && defVal?.value) {
              pctValue = parseFloat(defVal.value);
            }
          }
          if (pctValue > 0) {
            const pxWidth = Math.round((pctValue / 100) * parentWidth);
            return (
              <style dangerouslySetInnerHTML={{
                __html: `${sel} .bwf_pp_wrap { max-width: ${pxWidth}px; }`,
              }} />
            );
          }
        } catch (e) { /* cross-origin or SSR */ }
        return null;
      })()}

    </StyleContainer>
  );
};
