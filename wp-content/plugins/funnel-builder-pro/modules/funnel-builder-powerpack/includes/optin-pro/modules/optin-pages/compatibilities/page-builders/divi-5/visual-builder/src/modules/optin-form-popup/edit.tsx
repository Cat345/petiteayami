// External Dependencies.
import React, { ReactElement, useMemo } from 'react';
import { merge } from 'lodash';

// Divi Dependencies.
import { ModuleContainer } from '@divi/module';

// Local Dependencies.
import { OptinFormPopupEditProps } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import defaultRenderAttributes from './module-default-render-attributes.json';

/**
 * Optin Form Popup Module edit component of visual builder.
 *
 * @since 1.0.0
 *
 * @param {OptinFormPopupEditProps} props React component props.
 *
 * @returns {ReactElement}
 */
export const OptinFormPopupEdit = (props: OptinFormPopupEditProps): ReactElement => {
  const {
    attrs,
    elements,
    id,
    name,
  } = props;

  // CRITICAL: Merge defaults with attrs to ensure default values are always present
  const mergedAttrs = useMemo(() => {
    if (!attrs || Object.keys(attrs).length === 0) {
      return defaultRenderAttributes as typeof attrs;
    }
    // Merge defaults with current attrs (defaults are base, current overrides)
    return merge({}, defaultRenderAttributes, attrs) as typeof attrs;
  }, [attrs]);

  // Helper: Divi text field saves user edits to desktop.value, default at innerContent.desktop.value.
  // Always check user-saved path first.
  const getText = (attr: any, fallback: string): string =>
    attr?.desktop?.value || attr?.innerContent?.desktop?.value || fallback;

  // Extract content for preview
  const btnText = getText(mergedAttrs?.btn_text, 'Signup Now');
  const btnSubheading = getText(mergedAttrs?.btn_subheading_text, '');

  // Handle icon-picker format: can be string or object {unicode, type, weight}
  const iconValue = mergedAttrs?.btn_icon_field?.desktop?.value || '';
  let iconCode = '';

  if (typeof iconValue === 'string') {
    iconCode = iconValue;
  } else if (iconValue && typeof iconValue === 'object') {
    const unicodeValue = iconValue?.unicode || iconValue?.value || '';
    if (typeof unicodeValue === 'string') {
      iconCode = unicodeValue.split(/[,\s;]+/)[0].trim();
    }
  }

  const iconAlign = mergedAttrs?.btn_icon_field_position?.desktop?.value || 'left';

  const popupHeading = getText(mergedAttrs?.popup_heading, "You're just one step away!");
  const popupSubHeading = getText(mergedAttrs?.popup_sub_heading, "Enter your details below and we'll get you signed up");
  const buttonText = getText(mergedAttrs?.button_text, 'Send Me My Free Guide');
  const subtitle = getText(mergedAttrs?.subtitle, '');
  const showLabels = mergedAttrs?.show_labels?.desktop?.value !== 'off';
  const popupBarText = getText(mergedAttrs?.popup_bar_text, '75% Completed');
  const popupBarEnabled = mergedAttrs?.popup_bar_pp?.desktop?.value !== 'off';
  // Divi range field saves to decoration.sizing.desktop.value.width ("38%"),
  // module.json default is at decoration.sizing.width.desktop.value.value ("75").
  const popupBarWidthRaw = mergedAttrs?.wfop_optin_form_popup_popup_progress_bar_width?.decoration?.sizing?.desktop?.value?.width
    || mergedAttrs?.wfop_optin_form_popup_popup_progress_bar_width?.decoration?.sizing?.width?.desktop?.value?.value
    || '75';
  const popupBarWidth = String(popupBarWidthRaw).replace('%', '');
  const popupBarTextPosition = mergedAttrs?.popup_bar_text_position?.desktop?.value || 'on';
  const barTextAbove = popupBarTextPosition === 'on';
  const footerText = getText(mergedAttrs?.popup_footer_text, 'Your Information is 100% Secure');
  const submittingText = getText(mergedAttrs?.button_submitting_text, 'Submitting...');

  return (
    <ModuleContainer
      attrs={mergedAttrs}
      elements={elements}
      id={id}
      name={name}
      stylesComponent={ModuleStyles}
      classnamesFunction={moduleClassnames}
      scriptDataComponent={ModuleScriptData}
    >
      <div className="wfop_popup_wrapper wfop_pb_widget_wrap">
        {/* CTA Button - Match frontend structure exactly */}
        <div className="bwf-custom-button">
          <div className="wfop_popup_form" id="bwf-custom-button-wrap">
            <a href="#">
              <span className="bwf-text-wrapper">
                {iconAlign === 'left' && iconCode && (
                  <span
                    className={`wfocu-button-icon et-pb-icon ${iconAlign}`}
                    dangerouslySetInnerHTML={{ __html: iconCode }}
                  />
                )}
                {btnText && <span className="bwf_heading">{btnText}</span>}
                {iconAlign === 'right' && iconCode && (
                  <span
                    className={`wfocu-button-icon et-pb-icon ${iconAlign}`}
                    dangerouslySetInnerHTML={{ __html: iconCode }}
                  />
                )}
              </span>
              {btnSubheading && <span className="bwf_subheading">{btnSubheading}</span>}
            </a>
          </div>
        </div>

        {/* Popup Overlay - Match frontend structure exactly */}
        <div className="bwf_pp_overlay bwf_pp_effect_fade">
          <div className="bwf_pp_wrap">
            <a className="bwf_pp_close" href="javascript:void(0);">&times;</a>
            <div className="bwf_pp_cont">
              {/* Progress Bar */}
              {popupBarEnabled && (
                <>
                  <div className={`pp-bar-text-wrapper ${barTextAbove ? 'on' : 'off'}`}>
                    <span className="pp-bar-text above">{popupBarText}</span>
                  </div>
                  <div className={`bwf_pp_bar_wrap ${barTextAbove ? 'on' : 'off'}`}>
                    <div
                      className="bwf_pp_bar bwf_pp_animate"
                      role="progressbar"
                      style={{ width: `${popupBarWidth}%` }}
                    >
                      <span className="pp-bar-text inside">{popupBarText}</span>
                    </div>
                  </div>
                </>
              )}

              {/* Heading & Subheading */}
              <div className="bwf_pp_opt_head">{popupHeading}</div>
              <div className="bwf_pp_opt_sub_head">{popupSubHeading}</div>
              <div className="bwf_clear"></div>

              {/* Form Preview - Match frontend structure */}
              <div className="wffn-optin-form bwfac_forms_outer elementor-form-fields-wrapper">
                <form className="wffn-custom-optin-from" method="post">
                  <div className="wfop_section single_step">
                    <div className={`bwfac_form_sec bwfac_form_field_first_name wffn-sm-100 ${!showLabels ? 'wfop_hide_label' : ''}`}>
                      {showLabels && <label>First Name<span>*</span></label>}
                      <div className="wfop_input_cont">
                        <input type="text" className="wffn-optin-input" placeholder="Your First Name" />
                      </div>
                    </div>
                    <div className={`bwfac_form_sec bwfac_form_field_email wffn-sm-100 ${!showLabels ? 'wfop_hide_label' : ''}`}>
                      {showLabels && <label>Email<span>*</span></label>}
                      <div className="wfop_input_cont">
                        <input type="email" className="wffn-optin-input" placeholder="Your Email" />
                      </div>
                    </div>
                  </div>

                  {/* Submit Button - Match frontend structure exactly */}
                  <div className="bwfac_form_sec submit_button">
                    <div className="bwf-custom-button" id="bwf-custom-button-wrap">
                      <button
                        className="wfop_submit_btn"
                        type="submit"
                        id="wffn_custom_optin_submit"
                        data-subitting-text={submittingText}
                      >
                        <span className="bwf-text-wrapper">
                          <span className="bwf_heading">{buttonText}</span>
                        </span>
                        {subtitle && <span className="bwf_subheading">{subtitle}</span>}
                      </button>
                    </div>
                  </div>
                </form>
              </div>

              {/* Footer Text - Match frontend structure */}
              <div className="bwf_pp_footer">{footerText}</div>
            </div>
          </div>
        </div>
      </div>
    </ModuleContainer>
  );
};
