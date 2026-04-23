// External Dependencies.
import React, { ReactElement, useMemo, useEffect, useState, useRef } from 'react';
import { merge } from 'lodash';

// Divi Dependencies.
import { ModuleContainer } from '@divi/module';
import { useFetch } from '@divi/rest';

// Local Dependencies.
import { OptinFormEditProps } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import defaultRenderAttributes from './module-default-render-attributes.json';

/**
 * Form field interface from REST API.
 */
interface FormField {
  inputName: string;
  label: string;
  width: string;
  type?: string;
}

/**
 * Optin Form Module edit component of visual builder.
 *
 * @since 1.0.0
 *
 * @param {OptinFormEditProps} props React component props.
 *
 * @returns {ReactElement}
 */
export const OptinFormEdit = (props: OptinFormEditProps): ReactElement => {
  const {
    attrs,
    elements,
    id,
    name,
  } = props;

  // CRITICAL: Merge defaults with attrs to ensure default values are always present
  // This prevents placeholder from showing when default value is saved
  // Divi 5 doesn't save attributes that match defaults, so we need to merge them here
  const mergedAttrs = useMemo(() => {
    if (!attrs || Object.keys(attrs).length === 0) {
      return defaultRenderAttributes as typeof attrs;
    }
    // Merge defaults with current attrs (defaults are base, current overrides)
    return merge({}, defaultRenderAttributes, attrs) as typeof attrs;
  }, [attrs]);

  // Extract form settings for preview
  const buttonText = mergedAttrs?.button_text?.innerContent?.desktop?.value || 'Send Me My Free Guide';
  const subtitle = mergedAttrs?.subtitle?.innerContent?.desktop?.value || '';
  const showLabels = mergedAttrs?.show_labels?.desktop?.value !== 'off';

  // Fetch form fields dynamically
  const [formFields, setFormFields] = useState<FormField[]>([]);
  const hasFetchedRef = useRef<boolean>(false);
  const { fetch } = useFetch();

  useEffect(() => {
    if (hasFetchedRef.current) {
      return;
    }

    const getPostId = (): number | string | null => {
      const win = window as any;
      const methods = [
        () => {
          if (win.et_fb?.post_id) {
            return win.et_fb.post_id;
          }
          return null;
        },
        () => {
          if (win.etPbPostId) {
            return win.etPbPostId;
          }
          return null;
        },
        () => {
          if (document.referrer) {
            const referrerMatch = document.referrer.match(/[?&]post=(\d+)/);
            return referrerMatch?.[1];
          }
          return null;
        },
        () => {
          const urlMatch = window.location.href.match(/[?&]post=(\d+)/);
          return urlMatch?.[1];
        },
        () => {
          // Try to extract slug from URL
          const pathMatch = window.location.pathname.match(/\/([^\/]+)\/?$/);
          if (pathMatch && pathMatch[1] && pathMatch[1] !== 'wp-admin') {
            return pathMatch[1];
          }
          return null;
        },
      ];

      for (const method of methods) {
        const value = method();
        if (value) {
          const id = parseInt(String(value), 10);
          if (!isNaN(id) && id > 0) {
            return id;
          }
          if (typeof value === 'string' && value.length > 0) {
            return value as any;
          }
        }
      }

      return null;
    };

    const postId = getPostId();
    const restRoute = '/wfop/v1/optin-form/fields';

    hasFetchedRef.current = true;

    fetch({
      method: 'GET',
      restRoute,
      params: postId ? { post_id: postId, slug: typeof postId === 'string' ? postId : undefined } : {},
    }).then((value: FormField[]) => {
      if (value && Array.isArray(value)) {
        setFormFields(value);
      } else {
        setFormFields([]);
      }
    }).catch(() => {
      setFormFields([]);
      hasFetchedRef.current = false;
    });
  }, []);

  // Helper function to get field width from attributes
  const getFieldWidth = (inputName: string): string => {
    const widthAttr = mergedAttrs?.[inputName]?.desktop?.value;
    if (widthAttr && typeof widthAttr === 'string') {
      return widthAttr;
    }
    // Fallback to default
    return 'wffn-sm-100';
  };

  // Helper function to determine field type and render appropriate input
  const renderFieldInput = (field: FormField, index: number) => {
    const inputName = field.inputName;
    const fieldId = `wfop_id_${inputName}_preview_${index}`;
    const fieldType = field.type?.toLowerCase() || '';
    const width = getFieldWidth(inputName);
    const fieldClass = `bwfac_form_field_${inputName.replace('wfop_optin_', '')}`;

    // Determine input type based on field type or input name
    let inputType = 'text';
    let inputElement: ReactElement;

    if (fieldType === 'email' || inputName.includes('email')) {
      inputType = 'email';
    } else if (fieldType === 'tel' || fieldType === 'phone' || inputName.includes('phone')) {
      inputType = 'tel';
    } else if (fieldType === 'select' || fieldType === 'dropdown') {
      // Render select dropdown
      inputElement = (
        <select
          id={fieldId}
          className="wffn-optin-input"
          name={inputName}
          disabled
        >
          <option value="">Select {field.label}</option>
        </select>
      );
    } else {
      inputType = 'text';
    }

    // Render text/email/tel input if not already rendered as select
    if (!inputElement) {
      inputElement = (
        <input
          id={fieldId}
          className="wffn-optin-input wfop_required"
          type={inputType}
          name={inputName}
          placeholder={`Your ${field.label}`}
          disabled
        />
      );
    }

    return (
      <div
        key={inputName}
        className={`bwfac_form_sec ${fieldClass} ${width} ${!showLabels ? 'wfop_hide_label' : ''}`}
      >
        {showLabels && (
          <label htmlFor={fieldId} className="wfop-form-label">
            {field.label}
            <span>*</span>
          </label>
        )}
        <div className="wfop_input_cont">
          {inputElement}
        </div>
      </div>
    );
  };

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
      {elements.styleComponents({
        attrName: 'module',
      })}
      <div className="wffn-optin-form bwfac_forms_outer divi-form-fields-wrapper" data-field-size="small">
        {/* Form preview - matches PHP render_callback structure exactly */}
        <form className="wffn-custom-optin-from" method="post">
          <div className="wfop_section single_step">
            {/* Dynamically render all form fields */}
            {formFields.length > 0 ? (
              formFields.map((field, index) => renderFieldInput(field, index))
            ) : (
              // Fallback: Show default fields if API hasn't loaded yet
              <>
                <div className={`bwfac_form_sec bwfac_form_field_first_name ${getFieldWidth('wfop_optin_first_name')} ${!showLabels ? 'wfop_hide_label' : ''}`}>
                  {showLabels && (
                    <label htmlFor="wfop_id_wfop_optin_first_name_preview" className="wfop-form-label">
                      First Name<span>*</span>
                    </label>
                  )}
                  <div className="wfop_input_cont">
                    <input
                      id="wfop_id_wfop_optin_first_name_preview"
                      className="wffn-optin-input wfop_required"
                      type="text"
                      name="wfop_optin_first_name"
                      placeholder="Your First Name"
                      disabled
                    />
                  </div>
                </div>
                <div className={`bwfac_form_sec bwfac_form_field_email ${getFieldWidth('wfop_optin_email')} ${!showLabels ? 'wfop_hide_label' : ''}`}>
                  {showLabels && (
                    <label htmlFor="wfop_id_wfop_optin_email_preview" className="wfop-form-label">
                      Email<span>*</span>
                    </label>
                  )}
                  <div className="wfop_input_cont">
                    <input
                      id="wfop_id_wfop_optin_email_preview"
                      className="wffn-optin-input wfop_required"
                      type="email"
                      name="wfop_optin_email"
                      placeholder="Your Email"
                      disabled
                    />
                  </div>
                </div>
              </>
            )}
          </div>

          {/* Submit button - matching PHP structure */}
          <div className="bwfac_form_sec submit_button">
            <input type="hidden" value="" name="optin_is_admin" />
            <input type="hidden" value="" name="optin_is_ajax" />
            <input type="hidden" value="1" name="optin_is_preview" />
            <input type="hidden" value="" name="optin_page_id" />
            <input type="hidden" value="" name="formBuilder" />
            <div className="bwf-custom-button" id="bwf-custom-button-wrap">
              <button
                className="wfop_submit_btn"
                data-subitting-text={mergedAttrs?.button_submitting_text?.innerContent?.desktop?.value || 'Submitting...'}
                type="button"
                id="wffn_custom_optin_submit"
                data-size="med"
                disabled
              >
                <span className="bwf-text-wrapper">
                  <span className="bwf_heading">{buttonText}</span>
                </span>
                {subtitle && (
                  <span className="bwf_subheading">{subtitle}</span>
                )}
              </button>
            </div>
          </div>
        </form>
      </div>
    </ModuleContainer>
  );
};
