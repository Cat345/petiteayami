// External Dependencies.
import React, { type ReactElement, useEffect, useState, useMemo, useRef } from 'react';
import { set, cloneDeep, isEqual } from 'lodash';

// Divi Dependencies.
import {
  type Module,
  type FieldLibrary,
} from '@divi/types';
import { ModuleGroups } from '@divi/module';
import { useFetch } from '@divi/rest';

// Local Dependencies.
import { OptinFormAttrs } from './types';
import metadata from './module.json';

/**
 * Form field interface from REST API.
 */
interface FormField {
  inputName: string;
  label: string;
  width: string;
}

/**
 * Width options for form fields.
 */
const WIDTH_OPTIONS: FieldLibrary.Select.Options = {
  'wffn-sm-100': { label: 'Full' },
  'wffn-sm-50': { label: 'One Half' },
  'wffn-sm-33': { label: 'One Third' },
  'wffn-sm-67': { label: 'Two Third' },
};

/**
 * Content panel component for the Optin Form module settings modal.
 *
 * @since 1.0.0
 *
 * @param {Module.Settings.Panel.Props} param0 Content panel props.
 *
 * @returns {ReactElement}
 */
export const SettingsContent = ({
  groupConfiguration,
}: Module.Settings.Panel.Props<OptinFormAttrs>): ReactElement => {
  // Store form fields from REST API
  const [formFields, setFormFields] = useState<FormField[]>([]);

  // Track previous formFields to prevent unnecessary updates
  const prevFormFieldsRef = useRef<FormField[]>([]);
  
  // Track if we've already fetched to prevent infinite loops
  const hasFetchedRef = useRef<boolean>(false);
  
  // Store last known good field configuration to preserve values when groupConfiguration resets
  const lastFieldsConfigRef = useRef<Record<string, any>>({});

  const {
    fetch,
  } = useFetch();

  useEffect(() => {
    // Prevent multiple fetches
    if (hasFetchedRef.current) {
      return;
    }

    // Get current post ID from window (Visual Builder context)
    const getPostId = (): number | null => {
      if (typeof window === 'undefined') {
        return null;
      }

      const win = window as any;

      // Try multiple methods to get post ID (Divi 4 and Divi 5 compatibility)
      const methods = [
        () => win.et_fb_post_id,
        () => {
          const urlParams = new URLSearchParams(window.location.search);
          return urlParams.get('post_id') || urlParams.get('p');
        },
        () => {
          const pathMatch = window.location.pathname.match(/\/post\.php\?post=(\d+)/);
          return pathMatch?.[1];
        },
        () => win.et_pb_post_id,
        () => {
          // Try to extract from URL path like /op/optin-2/
          const pathMatch = window.location.pathname.match(/\/op\/([^\/]+)/);
          if (pathMatch && pathMatch[1]) {
            // Try to get post ID from slug via REST API or return slug for PHP to handle
            return pathMatch[1];
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
        () => win.et_fb?.post_id,
        () => win.etPbPostId,
      ];

      for (const method of methods) {
        const value = method();
        if (value) {
          // If it's a number, parse it
          const id = parseInt(String(value), 10);
          if (!isNaN(id) && id > 0) {
            return id;
          }
          // If it's a slug, return it as string (PHP will handle it)
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
        // Update ref BEFORE setting state so useMemo can detect the change
        prevFormFieldsRef.current = [];
        setFormFields(value);
      } else {
        prevFormFieldsRef.current = [];
        setFormFields([]);
      }
    }).catch((error) => {
      prevFormFieldsRef.current = [];
      setFormFields([]);
      hasFetchedRef.current = false; // Allow retry on error
    });
  }, []); // Empty dependency array - only run once on mount

  // Create/update fields and ensure React detects the change
  const updatedGroupConfiguration = useMemo(() => {
    const config = cloneDeep(groupConfiguration);

    // Ensure contentOptinForm group exists
    if (!config.contentOptinForm) {
      config.contentOptinForm = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    // Ensure fields object exists
    if (!config.contentOptinForm.component) {
      config.contentOptinForm.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentOptinForm.component.props) {
      config.contentOptinForm.component.props = {
        fields: {},
      };
    }

    if (!config.contentOptinForm.component.props.fields) {
      config.contentOptinForm.component.props.fields = {};
    }

    const fields = config.contentOptinForm.component.props.fields;

    // Ensure contentSmartButton group exists
    if (!config.contentSmartButton) {
      config.contentSmartButton = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    // Ensure Smart Button fields object exists
    if (!config.contentSmartButton.component) {
      config.contentSmartButton.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentSmartButton.component.props) {
      config.contentSmartButton.component.props = {
        fields: {},
      };
    }

    if (!config.contentSmartButton.component.props.fields) {
      config.contentSmartButton.component.props.fields = {};
    }

    const smartButtonFields = config.contentSmartButton.component.props.fields;

    // Handle dynamic form field width fields
    // Always ensure fields are created if formFields exist, even if groupConfiguration changes
    // Check if formFields has changed by comparing lengths and content
    const prevLength = prevFormFieldsRef.current.length;
    const currentLength = formFields.length;
    const formFieldsChanged = prevLength !== currentLength || !isEqual(prevFormFieldsRef.current, formFields);
    
    // Check if any dynamic fields are missing (needed when groupConfiguration resets)
    const staticFields = ['button_text', 'subtitle', 'button_submitting_text', 'show_labels', 'input_size'];
    const expectedFieldNames = formFields.map(f => f.inputName);
    const existingDynamicFields = Object.keys(fields).filter(name => !staticFields.includes(name));
    const missingFields = expectedFieldNames.filter(name => !existingDynamicFields.includes(name));
    const needsFieldCreation = missingFields.length > 0;
    
    // Preserve existing field values from current fields or last known config
    if (Object.keys(fields).length > 0) {
      // Update last known config with current field values
      expectedFieldNames.forEach(fieldName => {
        if (fields[fieldName] && fields[fieldName].defaultAttr?.desktop?.value) {
          lastFieldsConfigRef.current[fieldName] = fields[fieldName].defaultAttr.desktop.value;
        }
      });
    }

    // Process fields if formFields changed OR if fields are missing (groupConfiguration reset)
    if ((formFieldsChanged || needsFieldCreation) && formFields.length > 0) {
      // Remove old dynamic fields that no longer exist (only if formFields actually changed)
      if (formFieldsChanged) {
        const currentFieldNames = new Set(formFields.map(f => f.inputName));
        Object.keys(fields).forEach(fieldName => {
          // Only remove fields that look like dynamic width fields (not static fields)
          if (!staticFields.includes(fieldName) && !currentFieldNames.has(fieldName)) {
            delete fields[fieldName];
          }
        });
      }

      // Create or update width fields for each form field
      formFields.forEach((formField, index) => {
        const fieldName = formField.inputName;
        const fieldExists = !!fields[fieldName];

        // Get current selected value with priority:
        // 1. Existing field's saved value (if field exists)
        // 2. Last known saved value from ref (preserved across config resets)
        // 3. Previous groupConfiguration's saved value (if config was reset)
        // 4. Form field's default width
        // 5. Default fallback
        let currentSelectedValue = formField.width || 'wffn-sm-100';
        
        if (fieldExists) {
          // Field exists - preserve its current value
          currentSelectedValue = fields[fieldName]?.defaultAttr?.desktop?.value || currentSelectedValue;
        } else {
          // Field doesn't exist - try to get from last known config first
          if (lastFieldsConfigRef.current[fieldName]) {
            currentSelectedValue = lastFieldsConfigRef.current[fieldName];
          } else {
            // Fallback to previous groupConfiguration
            const prevValue = groupConfiguration?.contentOptinForm?.component?.props?.fields?.[fieldName]?.defaultAttr?.desktop?.value;
            if (prevValue) {
              currentSelectedValue = prevValue;
            }
          }
        }

        if (!fieldExists) {
          // Create the width field if it doesn't exist
          set(fields, fieldName, {
            priority: 5 + index, // Start at 5 to leave room for other fields
            component: {
              name: 'divi/select',
              type: 'field',
              props: {
                options: WIDTH_OPTIONS,
              },
            },
            render: true,
            attrName: fieldName,
            label: formField.label,
            description: `Set the width for ${formField.label} field`,
            features: {
              sticky: false,
              dynamicContent: false,
              responsive: false,
            },
            defaultAttr: {
              desktop: {
                value: currentSelectedValue,
              },
            },
          });
        } else {
          // Field exists - only update label if it changed, preserve the value
          if (fields[fieldName].label !== formField.label) {
            set(fields[fieldName], 'label', formField.label);
          }
        }
      });

      // Update refs AFTER processing to mark as processed
      prevFormFieldsRef.current = formFields;
      
      // Update last known config with all current field values
      expectedFieldNames.forEach(fieldName => {
        if (fields[fieldName] && fields[fieldName].defaultAttr?.desktop?.value) {
          lastFieldsConfigRef.current[fieldName] = fields[fieldName].defaultAttr.desktop.value;
        }
      });
    }

    // Ensure show_labels toggle field exists (matching qty-selector pattern lines 224-251)
    // This field should always render in the Optin Form tab
    const showLabelsFieldExists = !!fields.show_labels;
    
    if (!showLabelsFieldExists) {
      // Get current selected value from existing field first, then groupConfiguration, to preserve saved value
      // This matches qty-selector pattern (line 150)
      const currentSelectedValue = fields.show_labels?.defaultAttr?.desktop?.value
        || groupConfiguration?.contentOptinForm?.component?.props?.fields?.show_labels?.defaultAttr?.desktop?.value
        || 'on';
      
      // Create the show_labels toggle field if it doesn't exist
      set(fields, 'show_labels', {
        priority: 100, // Appears after all dynamic width fields
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Show',
            off: 'Hide',
          },
        },
        render: true,
        attrName: 'show_labels',
        label: 'Label',
        description: 'Show or hide form field labels',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: currentSelectedValue,
          },
        },
      });
    } else {
      // Field exists - ensure it's properly configured
      // Ensure component exists (might come from module.json)
      if (!fields.show_labels.component) {
        fields.show_labels.component = {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Show',
            off: 'Hide',
          },
        };
      }
      // Ensure render is true
      fields.show_labels.render = true;
      // Ensure priority is correct
      if (!fields.show_labels.priority || fields.show_labels.priority < 100) {
        fields.show_labels.priority = 100;
      }
      // Ensure defaultAttr is set if missing
      if (!fields.show_labels.defaultAttr) {
        fields.show_labels.defaultAttr = {
          desktop: {
            value: 'on',
          },
        };
      }
    }

    // Note: Smart Button fields (button_text, subtitle, button_submitting_text) are defined
    // in module.json and will be automatically registered by Divi 5.
    // We don't need to explicitly register them here to avoid duplicates.

    return config;
  }, [groupConfiguration, formFields]);

  return <ModuleGroups groups={updatedGroupConfiguration} />;
};
