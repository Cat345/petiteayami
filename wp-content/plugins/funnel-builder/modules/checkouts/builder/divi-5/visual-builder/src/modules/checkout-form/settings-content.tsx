// External Dependencies.
import React, { type ReactElement, useMemo, useState, useEffect, useRef } from 'react';
import { set, cloneDeep } from 'lodash';

// Divi Dependencies.
import {
  type Module,
  type FieldLibrary,
} from '@divi/types';
import { ModuleGroups } from '@divi/module';
import { useFetch } from '@divi/rest';

// Local Dependencies.
import { CheckoutFormAttrs } from './types';
import metadata from './module.json';

/**
 * Content panel component for the Checkout Form module settings modal.
 *
 * @since 1.0.0
 *
 * @param {Module.Settings.Panel.Props} param0 Content panel props.
 *
 * @returns {ReactElement}
 */
export const SettingsContent = ({
  groupConfiguration,
  attrs,
}: Module.Settings.Panel.Props<CheckoutFormAttrs>): ReactElement => {
  const { fetch } = useFetch();
  const [fieldStructure, setFieldStructure] = useState<{
    sections: Array<{
      step_key: string;
      section_key: string;
      name: string;
      fields: Array<{
        id: string;
        label: string;
        type?: string;
        default_class?: string;
      }>;
    }>;
    class_options: Record<string, string>;
    template_slug: string;
    excluded_fields?: string[];
    step_count?: number;
  } | null>(null);
  const [isLoadingFields, setIsLoadingFields] = useState<boolean>(false);

  // Get post ID from window location (same pattern as edit.tsx)
  const postId = useMemo((): number | null => {
    if (typeof window === 'undefined') {
      return null;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const postIdParam = urlParams.get('post_id') || urlParams.get('et_post_id') || urlParams.get('et_wfacp_id');
    if (postIdParam) {
      const parsed = parseInt(postIdParam, 10);
      if (!isNaN(parsed) && parsed > 0) {
        return parsed;
      }
    }

    // Fallback: try to get from window.et_fb_post_id (Divi Visual Builder)
    const win = window as any;
    if (win.et_fb_post_id && typeof win.et_fb_post_id === 'number' && win.et_fb_post_id > 0) {
      return win.et_fb_post_id;
    }

    return null;
  }, []);

  // CRITICAL: Watch for changes in dynamic_field_classes attributes
  // Use useRef to store previous values and only process when they actually change
  const prevAttrsRef = useRef<any>(null);
  
  useEffect(() => {
    if (!attrs) {
      return;
    }

    const dynamicFieldClasses = (attrs as any)?.dynamic_field_classes;
    const prevDynamicFieldClasses = prevAttrsRef.current?.dynamic_field_classes;
    
    // Store current attrs for next comparison
    prevAttrsRef.current = attrs;
    
    if (dynamicFieldClasses && typeof dynamicFieldClasses === 'object') {
      // Only log if this is a change (not initial load)
      const isInitialLoad = !prevDynamicFieldClasses;
      
      
      // Log specific field values with detailed structure inspection
      Object.keys(dynamicFieldClasses).forEach((sectionKey) => {
        const sectionFields = dynamicFieldClasses[sectionKey];
        if (sectionFields && typeof sectionFields === 'object') {
          Object.keys(sectionFields).forEach((fieldKey) => {
            const fieldData = sectionFields[fieldKey];
            
            // Extract the actual value
            let actualValue = null;
            if (fieldData?.desktop?.value) {
              actualValue = fieldData.desktop.value;
            } else if (fieldData?.value) {
              actualValue = fieldData.value;
            } else if (typeof fieldData === 'string') {
              actualValue = fieldData;
            }
            
            // Compare with previous value to detect changes
            let prevValue = null;
            if (prevDynamicFieldClasses?.[sectionKey]?.[fieldKey]) {
              const prevFieldData = prevDynamicFieldClasses[sectionKey][fieldKey];
              if (prevFieldData?.desktop?.value) {
                prevValue = prevFieldData.desktop.value;
              } else if (prevFieldData?.value) {
                prevValue = prevFieldData.value;
              } else if (typeof prevFieldData === 'string') {
                prevValue = prevFieldData;
              }
            }
            
            // Only update preview if value actually changed (not initial load)
            if (!isInitialLoad && actualValue !== prevValue) {
              // CRITICAL: Update preview DOM directly when attribute changes
              // Wait a bit for Divi to save the attribute first
              setTimeout(() => {
                try {
                  const fieldElementId = `${fieldKey}_field`;
                  const oldClasses = [
                    'wfacp-col-full',
                    'wfacp-col-left-half',
                    'wfacp-col-right-half',
                    'wfacp-col-left-third',
                    'wfacp-col-right-third',
                    'wfacp-col-two-third',
                    'wfacp-col-one-third',
                  ];
                  
                  const updateFieldClasses = (element: HTMLElement) => {
                    oldClasses.forEach(cls => element.classList.remove(cls));
                    if (actualValue) {
                      element.classList.add(actualValue);
                      return true;
                    }
                    return false;
                  };
                  
                  // Try to find and update the field element
                  let fieldElement = document.getElementById(fieldElementId) as HTMLElement;
                  
                  if (!fieldElement) {
                    const previewFrame = document.querySelector('iframe[name="et_fb_preview"]') as HTMLIFrameElement;
                    if (previewFrame && previewFrame.contentDocument) {
                      fieldElement = previewFrame.contentDocument.getElementById(fieldElementId) as HTMLElement;
                    }
                  }
                  
                  if (!fieldElement && window.parent && window.parent !== window) {
                    try {
                      fieldElement = (window.parent as any).document.getElementById(fieldElementId) as HTMLElement;
                    } catch (e) {
                      // Cross-origin
                    }
                  }
                  
                  if (fieldElement) {
                    updateFieldClasses(fieldElement);
                  }
                } catch (e) {
                  // Error updating preview DOM - silently fail
                }
              }, 300); // Wait 300ms for Divi to save the attribute
            }
          });
        }
      });
    }
  }, [attrs]);

  // Fetch field structure from REST API
  // CRITICAL: Only depend on postId to prevent infinite loops
  useEffect(() => {
    if (!postId) {
      return;
    }

    // Use ref to track if fetch is in progress to prevent duplicate requests
    let fetchInProgress = false;
    let isMounted = true;

    const fetchFieldStructure = async (retryCount = 0) => {
      // Prevent multiple simultaneous requests
      if (fetchInProgress) {
        return;
      }

      fetchInProgress = true;

      try {
        const timeoutPromise = new Promise((_, reject) => {
          setTimeout(() => reject(new Error('Request timeout')), 15000);
        });

        const restUrl = `/wfacp/v1/checkout-form/fields?post_id=${postId}`;

        const fetchPromise = fetch({
          method: 'GET',
          restRoute: restUrl,
        });

        const response = await Promise.race([fetchPromise, timeoutPromise]) as any;

        if (!isMounted) {
          fetchInProgress = false;
          return;
        }

        if (response && typeof response === 'object' && 'sections' in response && 'class_options' in response) {
          setFieldStructure({
            sections: response.sections || [],
            class_options: response.class_options || {},
            template_slug: response.template_slug || '',
            excluded_fields: response.excluded_fields || [],
            step_count: response.step_count || 0,
          });
        } else if (isMounted && retryCount < 2) {
          fetchInProgress = false;
          setTimeout(() => { if (isMounted) fetchFieldStructure(retryCount + 1); }, 1500);
          return;
        } else if (isMounted) {
          // All retries exhausted with invalid response — set empty to prevent null state.
          setFieldStructure({ sections: [], class_options: {}, template_slug: '', excluded_fields: [], step_count: 0 });
        }
      } catch (error) {
        if (isMounted && retryCount < 2) {
          fetchInProgress = false;
          setTimeout(() => { if (isMounted) fetchFieldStructure(retryCount + 1); }, 1500);
          return;
        } else if (isMounted) {
          // All retries exhausted — set empty to prevent null state.
          setFieldStructure({ sections: [], class_options: {}, template_slug: '', excluded_fields: [], step_count: 0 });
        }
      } finally {
        if (isMounted) {
          fetchInProgress = false;
        }
      }
    };

    fetchFieldStructure();

    // Cleanup function
    return () => {
      isMounted = false;
      fetchInProgress = false;
    };
  }, [postId]); // CRITICAL: Only depend on postId - fetch is stable, don't include it

  // Static options for select_type dropdown - using FieldLibrary.Select.Options format
  // Format: {value: {label: 'Label'}} - matching accept-button pattern for dynamic options
  // NOTE: Matching Divi 4 exactly - only 'tab' and 'bredcrumb' are in dropdown (no 'progress_bar' option)
  const selectTypeOptions: FieldLibrary.Select.Options = {
    tab: { label: 'Tab' },
    bredcrumb: { label: 'Breadcrumb' },
  };

  // Create/update the select_type field - matching accept-button pattern exactly
  // CRITICAL: Memoize to prevent infinite re-renders
  // Only recompute when groupConfiguration, fieldStructure, or attrs actually change
  const updatedGroupConfiguration = useMemo(() => {
    // CRITICAL: Ensure groupConfiguration exists to prevent errors
    if (!groupConfiguration) {
      return {};
    }
    
    // CRITICAL: Clone groupConfiguration to avoid mutating the original
    // This ensures dynamic groups are added without affecting the original structure
    const config = cloneDeep(groupConfiguration);

    // ── Step Tab group (contentSteps) ──
    // Created dynamically from scratch (same pattern as contentCheckoutButtons).
    // Attributes in module.json have NO settings block so the framework won't
    // auto-render them. We add only the fields that match the current toggle /
    // select state read from `attrs`.

    config.contentSteps = {
      render: true,
      panel: 'content',
      priority: 10,
      groupName: 'contentSteps',
      multiElements: true,
      component: {
        type: 'group',
        name: 'divi/composite',
        props: {
          groupLabel: 'Step Tab',
          attrName: 'contentSteps',
          fields: {},
        },
      },
    };

    const stepFields = config.contentSteps.component.props.fields;
    let stepPriority = 10;

    // Helper: add a text field (innerContent attribute, not device-specific)
    const addStepTextField = (attrKey: string, label: string, defaultText?: string) => {
      const fieldDef: Record<string, any> = {
        priority: stepPriority++,
        component: { name: 'divi/text', type: 'field' },
        render: true,
        attrName: `${attrKey}.innerContent`,
        label,
        features: { sticky: false, responsive: false, dynamicContent: false },
      };
      if (defaultText !== undefined) {
        fieldDef.defaultAttr = { desktop: { value: defaultText } };
      }
      stepFields[attrKey] = fieldDef;
    };

    // Helper: add a select field (viewport-wrapped but not responsive in UI)
    const addStepSelect = (
      attrKey: string,
      label: string,
      options: FieldLibrary.Select.Options,
      defaultValue: string,
    ) => {
      stepFields[attrKey] = {
        priority: stepPriority++,
        component: { name: 'divi/select', type: 'field', props: { options } },
        render: true,
        attrName: attrKey,
        label,
        features: { sticky: false, responsive: false, dynamicContent: false },
        defaultAttr: { desktop: { value: defaultValue } },
      };
    };

    // 1. Always show the Enable toggle (viewport-wrapped but not responsive in UI)
    stepFields.enable_progress_bar = {
      priority: stepPriority++,
      component: { name: 'divi/toggle', type: 'field', props: { on: 'Yes', off: 'No' } },
      render: true,
      attrName: 'enable_progress_bar',
      label: 'Enable',
      features: { sticky: false, responsive: false, dynamicContent: false },
      defaultAttr: { desktop: { value: 'off' } },
    };

    // 2. Read current attr values (viewport-wrapped objects)
    const progressBarEnabled = (attrs as any)?.enable_progress_bar?.desktop?.value === 'on';
    // Use step_count from API (includes payment step) instead of counting unique section step_keys
    const numSteps = fieldStructure
      ? (fieldStructure.step_count || new Set(fieldStructure.sections.map((s: any) => s.step_key)).size)
      : 1;
    // Single-step checkout: force tab type, hide dropdown
    const selectedType = numSteps <= 1
      ? 'tab'
      : ((attrs as any)?.select_type?.desktop?.value || 'tab');

    if (progressBarEnabled) {
      // 3. Show Select Type dropdown only for multi-step
      if (numSteps > 1) {
        addStepSelect('select_type', 'Select Type', selectTypeOptions, 'tab');
      }

      // Default tab headings per step index
      const tabDefaults = ['Shipping', 'Products', 'Payment'];

      // 4. Show fields based on selected type, limited to actual step count
      if (selectedType === 'tab') {
        for (let i = 0; i < numSteps; i++) {
          addStepTextField(`step_${i}_heading`, `Tab - Step ${i + 1} - Heading`, tabDefaults[i] || '');
          addStepTextField(`step_${i}_subheading`, `Tab - Step ${i + 1} - Sub Heading`);
        }
      } else if (selectedType === 'bredcrumb') {
        for (let i = 0; i < numSteps; i++) {
          addStepTextField(`step_${i}_bredcrumb`, `Breadcrumb - Step ${i + 1} - Title`, `Step ${i + 1}`);
        }

        // Cart link options for breadcrumb
        addStepSelect(
          'step_cart_link_enable',
          'Add to Breadcrumb',
          { yes: { label: 'Yes' }, no: { label: 'No' } },
          'yes',
        );
        addStepTextField('step_cart_bredcrumb_link', 'Title', 'Cart');
      } else if (selectedType === 'progress_bar') {
        for (let i = 0; i < numSteps; i++) {
          addStepTextField(`step_${i}_progress_bar`, `Progress Bar - Step ${i + 1} - Heading`, `Step ${i + 1}`);
        }

        // Cart link options for progress bar
        addStepSelect(
          'step_cart_link_enable',
          'Add to Breadcrumb',
          { yes: { label: 'Yes' }, no: { label: 'No' } },
          'yes',
        );
        addStepTextField('step_cart_progress_bar_link', 'Cart Title', 'Cart');
      }
    }

    // Ensure contentCollapsibleOrderSummary group exists
    if (!config.contentCollapsibleOrderSummary) {
      config.contentCollapsibleOrderSummary = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    // Ensure fields object exists
    if (!config.contentCollapsibleOrderSummary.component) {
      config.contentCollapsibleOrderSummary.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentCollapsibleOrderSummary.component.props) {
      config.contentCollapsibleOrderSummary.component.props = {
        fields: {},
      };
    }

    if (!config.contentCollapsibleOrderSummary.component.props.fields) {
      config.contentCollapsibleOrderSummary.component.props.fields = {};
    }

    const collapsibleFields = config.contentCollapsibleOrderSummary.component.props.fields;

    // Ensure enable_callapse_order_summary toggle field exists (toggle field from module.json)
    if (!collapsibleFields.enable_callapse_order_summary) {
      set(collapsibleFields, 'enable_callapse_order_summary', {
        priority: 10,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'enable_callapse_order_summary',
        label: 'Enable',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      // Ensure render is true if field already exists
      collapsibleFields.enable_callapse_order_summary.render = true;
    }

    // Only show remaining fields when collapsible order summary is enabled
    const collapsibleEnabled = (attrs as any)?.enable_callapse_order_summary?.desktop?.value === 'on';

    if (collapsibleEnabled) {

    // Ensure order_summary_enable_product_image_collapsed toggle field exists (toggle field from module.json)
    if (!collapsibleFields.order_summary_enable_product_image_collapsed) {
      set(collapsibleFields, 'order_summary_enable_product_image_collapsed', {
        priority: 20,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'order_summary_enable_product_image_collapsed',
        label: 'Image',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      // Ensure render is true if field already exists
      collapsibleFields.order_summary_enable_product_image_collapsed.render = true;
    }

    // Ensure enable_order_field_collapsed toggle field exists (responsive toggle field from module.json)
    if (!collapsibleFields.enable_order_field_collapsed) {
      set(collapsibleFields, 'enable_order_field_collapsed', {
        priority: 25,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'enable_order_field_collapsed',
        label: 'Expanded Order Summary',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: true,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
          tablet: {
            value: 'off',
          },
          mobile: {
            value: 'off',
          },
        },
      });
    } else {
      collapsibleFields.enable_order_field_collapsed.render = true;
    }

    // Ensure cart_collapse_title text field render is true
    if (collapsibleFields.cart_collapse_title) {
      collapsibleFields.cart_collapse_title.render = true;
    }
    if (collapsibleFields.cart_collapse_titleInnercontent) {
      collapsibleFields.cart_collapse_titleInnercontent.render = true;
    }

    // Ensure cart_expanded_title text field render is true
    if (collapsibleFields.cart_expanded_title) {
      collapsibleFields.cart_expanded_title.render = true;
    }
    if (collapsibleFields.cart_expanded_titleInnercontent) {
      collapsibleFields.cart_expanded_titleInnercontent.render = true;
    }

    // Ensure collapse_enable_coupon toggle field exists (toggle field from module.json)
    if (!collapsibleFields.collapse_enable_coupon) {
      set(collapsibleFields, 'collapse_enable_coupon', {
        priority: 40,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'collapse_enable_coupon',
        label: 'Enable Coupon',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      collapsibleFields.collapse_enable_coupon.render = true;
    }

    // Only show coupon sub-fields when coupon is enabled
    const collapsibleCouponEnabled = (attrs as any)?.collapse_enable_coupon?.desktop?.value !== 'off';
    if (collapsibleCouponEnabled) {

    // Ensure collapse_enable_coupon_collapsible toggle field exists (toggle field from module.json)
    if (!collapsibleFields.collapse_enable_coupon_collapsible) {
      set(collapsibleFields, 'collapse_enable_coupon_collapsible', {
        priority: 45,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'collapse_enable_coupon_collapsible',
        label: 'Collapsible Coupon Field',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      collapsibleFields.collapse_enable_coupon_collapsible.render = true;
    }

    // Ensure collapse_coupon_button_text text field exists (text field from module.json)
    // Ensure coupon button text field render is true
    if (collapsibleFields.collapse_coupon_button_text) {
      collapsibleFields.collapse_coupon_button_text.render = true;
    }
    if (collapsibleFields.collapse_coupon_button_textInnercontent) {
      collapsibleFields.collapse_coupon_button_textInnercontent.render = true;
    }

    } else {
      if (collapsibleFields.collapse_enable_coupon_collapsible) {
        collapsibleFields.collapse_enable_coupon_collapsible.render = false;
      }
      if (collapsibleFields.collapse_coupon_button_text) {
        collapsibleFields.collapse_coupon_button_text.render = false;
      }
      if (collapsibleFields.collapse_coupon_button_textInnercontent) {
        collapsibleFields.collapse_coupon_button_textInnercontent.render = false;
      }
    } // end collapsibleCouponEnabled

    // Ensure collapse_order_quantity_switcher toggle field exists (toggle field from module.json)
    if (!collapsibleFields.collapse_order_quantity_switcher) {
      set(collapsibleFields, 'collapse_order_quantity_switcher', {
        priority: 60,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'collapse_order_quantity_switcher',
        label: 'Quantity Switcher',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      collapsibleFields.collapse_order_quantity_switcher.render = true;
    }

    // Ensure collapse_order_delete_item toggle field exists (toggle field from module.json)
    if (!collapsibleFields.collapse_order_delete_item) {
      set(collapsibleFields, 'collapse_order_delete_item', {
        priority: 70,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'collapse_order_delete_item',
        label: 'Allow Deletion',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      collapsibleFields.collapse_order_delete_item.render = true;
    }

    } else {
      // Hide all sub-fields when collapsible order summary is disabled
      // Iterate over all keys in the fields object to catch innerContent keys too
      Object.keys(collapsibleFields).forEach((key) => {
        if (key !== 'enable_callapse_order_summary') {
          collapsibleFields[key].render = false;
        }
      });
    } // end collapsibleEnabled

    // Ensure contentOrderSummary group exists
    if (!config.contentOrderSummary) {
      config.contentOrderSummary = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    // Ensure fields object exists
    if (!config.contentOrderSummary.component) {
      config.contentOrderSummary.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentOrderSummary.component.props) {
      config.contentOrderSummary.component.props = {
        fields: {},
      };
    }

    if (!config.contentOrderSummary.component.props.fields) {
      config.contentOrderSummary.component.props.fields = {};
    }

    const orderSummaryFields = config.contentOrderSummary.component.props.fields;

    // Ensure order_summary_enable_product_image toggle field exists (toggle field from module.json)
    if (!orderSummaryFields.order_summary_enable_product_image) {
      set(orderSummaryFields, 'order_summary_enable_product_image', {
        priority: 10,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'order_summary_enable_product_image',
        label: 'Enable Image',
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      orderSummaryFields.order_summary_enable_product_image.render = true;
    }

    // Create contentPaymentGateway group dynamically (like contentCheckoutButtons below).
    // Attributes are defined in module.json for data persistence, but we create the UI fields here.
    config.contentPaymentGateway = {
      render: true,
      panel: 'content',
      priority: 70,
      groupName: 'contentPaymentGateway',
      multiElements: true,
      component: {
        type: 'group',
        name: 'divi/composite',
        props: {
          groupLabel: 'Payment Gateways',
          attrName: 'contentPaymentGateway',
          fields: {
            wfacp_payment_method_heading_text: {
              priority: 10,
              component: {
                name: 'divi/text',
                type: 'field',
              },
              render: true,
              attrName: 'wfacp_payment_method_heading_text.innerContent',
              label: 'Heading',
              features: {
                sticky: false,
                dynamicContent: false,
              },
              defaultAttr: {
                desktop: {
                  value: 'Payment',
                },
              },
            },
            wfacp_payment_method_subheading: {
              priority: 20,
              component: {
                name: 'divi/text',
                type: 'field',
              },
              render: true,
              attrName: 'wfacp_payment_method_subheading.innerContent',
              label: 'Sub Heading',
              features: {
                sticky: false,
                dynamicContent: false,
              },
              defaultAttr: {
                desktop: {
                  value: '',
                },
              },
            },
          },
        },
      },
    };

    // ── Form Fields group (contentFormFields) ──
    // Label position setting — matches Elementor/D4 form settings.
    config.contentFormFields = {
      render: true,
      panel: 'content',
      priority: 15,
      groupName: 'contentFormFields',
      multiElements: true,
      component: {
        type: 'group',
        name: 'divi/composite',
        props: {
          groupLabel: 'Form Fields',
          attrName: 'contentFormFields',
          fields: {
            wfacp_label_position: {
              priority: 10,
              component: {
                name: 'divi/select',
                type: 'field',
                props: {
                  options: {
                    'wfacp-modern-label': { label: 'Floating' },
                    'wfacp-top': { label: 'Outside' },
                    'wfacp-inside': { label: 'Inside' },
                  },
                },
              },
              render: true,
              attrName: 'wfacp_label_position',
              label: 'Label Position',
              features: { sticky: false, dynamicContent: false, responsive: false },
              defaultAttr: { desktop: { value: 'wfacp-inside' } },
            },
          },
        },
      },
    };

    // Create contentCheckoutButtons group dynamically (like contentDynamic_* groups).
    // Button text attributes are defined in module.json for data persistence (type + default only),
    // but NOT auto-rendered by the framework. We create the group and add only the needed fields
    // based on step count (matching D4 form_buttons() logic).

    // Use step_count from API (matching D4: $template->get_step_count()) — includes payment step
    const stepCount = fieldStructure
      ? Math.max(1, fieldStructure.step_count || new Set(fieldStructure.sections.map((s) => s.step_key)).size)
      : 1;

    // Create the group from scratch (same structure as contentDynamic_* groups)
    config.contentCheckoutButtons = {
      render: true,
      panel: 'content',
      priority: 80,
      groupName: 'contentCheckoutButtons',
      multiElements: true,
      component: {
        type: 'group',
        name: 'divi/composite',
        props: {
          groupLabel: 'Checkout Button(s)',
          attrName: 'contentCheckoutButtons',
          fields: {},
        },
      },
    };

    const btnFields = config.contentCheckoutButtons.component.props.fields;
    let btnPriority = 10;
    const iconOptions: FieldLibrary.Select.Options = {
      'aero-e902': { label: 'Arrow 1' },
      'aero-e906': { label: 'Arrow 2' },
      'aero-e907': { label: 'Arrow 3' },
      'aero-e908': { label: 'Checkmark' },
      'aero-e905': { label: 'Cart 1' },
      'aero-e901': { label: 'Lock 1' },
      'aero-e900': { label: 'Lock 2' },
    };

    // Helper to add a text field to the button group (flat pattern like contentDynamic_* fields)
    const addBtnField = (attrKey: string, label: string, defaultText?: string) => {
      const fieldDef: Record<string, any> = {
        priority: btnPriority++,
        component: {
          name: 'divi/text',
          type: 'field',
        },
        render: true,
        attrName: `${attrKey}.innerContent`,
        label,
        features: {
          sticky: false,
          dynamicContent: false,
        },
      };
      if (defaultText !== undefined) {
        fieldDef.defaultAttr = {
          desktop: {
            value: defaultText,
          },
        };
      }
      btnFields[attrKey] = fieldDef;
    };
    const addBtnToggle = (attrKey: string, label: string) => {
      btnFields[attrKey] = {
        priority: btnPriority++,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: attrKey,
        label,
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      };
    };
    const addBtnSelect = (attrKey: string, label: string) => {
      btnFields[attrKey] = {
        priority: btnPriority++,
        component: {
          name: 'divi/select',
          type: 'field',
          props: {
            options: iconOptions,
          },
        },
        render: true,
        attrName: attrKey,
        label,
        features: {
          sticky: false,
          dynamicContent: false,
        },
        defaultAttr: {
          desktop: {
            value: 'aero-e901',
          },
        },
      };
    };

    // Add fields based on step count (matching D4 form_buttons())
    for (let i = 1; i <= stepCount; i++) {
      const isLastStep = i === stepCount;
      const iconKey = isLastStep ? 'place_order' : `${i}`;
      const iconToggleAttr = `enable_icon_with_place_order_${iconKey}`;
      const iconSelectAttr = `icons_with_place_order_list_${iconKey}`;
      // D4 defaults: non-last steps = "NEXT STEP →", last step = "Place order"
      const btnDefaultText = isLastStep ? 'Place order' : 'NEXT STEP \u2192';
      addBtnField(`wfacp_payment_button_${i}_text`, isLastStep ? 'Place Order' : `Step ${i}`, btnDefaultText);
      addBtnField(
        isLastStep ? 'step_place_order_text_after_place_order' : `step_${i}_text_after_place_order`,
        'Sub Text',
      );
      addBtnToggle(iconToggleAttr, 'Enable Icon');
      // Only show icon select when toggle is on (showIf doesn't work for inline fields)
      const iconToggleValue = (attrs as any)?.[iconToggleAttr]?.desktop?.value;
      if (iconToggleValue === 'on') {
        addBtnSelect(iconSelectAttr, 'Select Icon');
      }
      if (isLastStep) {
        addBtnToggle('enable_price_in_place_order_button', 'Enable Price');
        addBtnField('text_below_placeorder_btn', 'Text Below Place Order Button', '');
      }
    }

    // Multi-step only: return to cart + back buttons
    if (stepCount > 1) {
      addBtnField('return_to_cart_text', 'Return to Cart', '\u00ab Return to Cart');
      for (let i = 2; i <= stepCount; i++) {
        addBtnField(`payment_button_back_${i}_text`, `Return to Step ${i - 1}`, '');
      }
    }

    // CRITICAL: Create separate groups for each section (matching Divi 4 pattern)
    // In Divi 4: Each section creates a tab with $this->add_tab( $title, 5 )
    // In Divi 5: Each section creates a group with groupSlug = contentDynamic_{step_key}_{section_key}
    // Each group contains: Select Dropdown (field class) + Text Field (custom class) for each field
    if (fieldStructure && fieldStructure.sections && fieldStructure.sections.length > 0) {
      
      // Class options for select dropdown (matching Divi 4 get_class_options)
      const classOptions: FieldLibrary.Select.Options = {
        'wfacp-col-full': { label: 'Full' },
        'wfacp-col-left-half': { label: 'One Half' },
        'wfacp-col-left-third': { label: 'One Third' },
        'wfacp-col-two-third': { label: 'Two Third' },
      };

      // Get excluded fields
      const excludedFields = fieldStructure.excluded_fields || [];
      const skipKeys = ['billing_same_as_shipping', 'shipping_same_as_billing'];

      // Process each section to create separate groups
      fieldStructure.sections.forEach((section, sectionIndex) => {
        try {
        const sectionKey = `${section.step_key}_${section.section_key}`;
        const sectionName = section.name || 'Section';

        // Skip sections with no fields or all fields excluded (matching Divi 4 logic)
        if (!section.fields || !Array.isArray(section.fields) || section.fields.length === 0) {
          return;
        }

        // Count excluded fields (matching Divi 4 logic)
        let excludedCount = 0;
        section.fields.forEach((field) => {
          if (excludedFields.includes(field.id) || skipKeys.includes(field.id)) {
            excludedCount++;
          }
        });

        // Skip if all fields are excluded
        if (excludedCount === section.fields.length) {
          return;
        }

        // Create a separate group for each section (like Divi 4 tabs)
        // Group slug format: contentDynamic_{step_key}_{section_key}
        const groupSlug = `contentDynamic_${sectionKey}`;

        // CRITICAL: Match the EXACT structure from module.json groups (contentSteps pattern)
        // Registered groups have: render: true, panel, priority, groupName, multiElements, component (with type: "group", name, props with attrName)
        // Fields are added to component.props.fields (similar to contentSteps)
        if (!config[groupSlug]) {
          config[groupSlug] = {
            render: true, // CRITICAL: Top-level render property (matching registered groups)
            panel: 'content',
            priority: 30 + sectionIndex, // Use sectionIndex instead of indexOf to avoid performance issues
            groupName: groupSlug,
            multiElements: true,
            component: {
              type: 'group', // CRITICAL: Component type must be "group" (matching registered groups)
              name: 'divi/composite',
              props: {
                groupLabel: sectionName,
                attrName: groupSlug, // CRITICAL: attrName property (matching registered groups)
                fields: {},
              },
            },
          };
        } else {
          // Update existing group to ensure structure is correct
          // CRITICAL: Ensure render property exists
          if (config[groupSlug].render === undefined) {
            config[groupSlug].render = true;
          }
          
          if (!config[groupSlug].component) {
            config[groupSlug].component = {
              type: 'group',
              name: 'divi/composite',
              props: {
                groupLabel: sectionName,
                attrName: groupSlug,
                fields: {},
              },
            };
          } else {
            // Ensure component.type is "group"
            if (config[groupSlug].component.type !== 'group') {
              config[groupSlug].component.type = 'group';
            }
            
            if (!config[groupSlug].component.props) {
              config[groupSlug].component.props = {
                groupLabel: sectionName,
                attrName: groupSlug,
                fields: {},
              };
            } else {
              // Ensure attrName exists
              if (!config[groupSlug].component.props.attrName) {
                config[groupSlug].component.props.attrName = groupSlug;
              }
              // Ensure fields object exists
              if (!config[groupSlug].component.props.fields) {
                config[groupSlug].component.props.fields = {};
              }
              // Update groupLabel if section name changed
              if (config[groupSlug].component.props.groupLabel !== sectionName) {
                config[groupSlug].component.props.groupLabel = sectionName;
              }
            }
          }
        }

        // Ensure all required group properties exist (matching module.json structure)
        if (config[groupSlug].render === undefined) {
          config[groupSlug].render = true;
        }
        if (!config[groupSlug].panel) {
          config[groupSlug].panel = 'content';
        }
        if (!config[groupSlug].groupName) {
          config[groupSlug].groupName = groupSlug;
        }
        if (config[groupSlug].multiElements === undefined) {
          config[groupSlug].multiElements = true;
        }
        if (config[groupSlug].priority === undefined) {
          config[groupSlug].priority = 5 + sectionIndex;
        }
        if (config[groupSlug].component?.type !== 'group') {
          config[groupSlug].component.type = 'group';
        }
        if (!config[groupSlug].component?.props?.attrName) {
          config[groupSlug].component.props.attrName = groupSlug;
        }

        const sectionFields = config[groupSlug].component.props.fields;
        let fieldPriority = 10;

        // Process each field in the section (matching Divi 4 register_fields logic)
        section.fields.forEach((field, fieldIndex) => {
          try {
            const fieldKey = field.id;
            if (!fieldKey || !field.label) {
              return;
            }

            // Skip excluded fields and special keys (matching Divi 4 logic)
            if (excludedFields.includes(fieldKey) || skipKeys.includes(fieldKey)) {
              return;
            }

            // Handle divider fields (matching Divi 4 logic for billing/shipping dividers)
            // In Divi 4: if ( in_array( $loop_key, [ 'wfacp_start_divider_billing', 'wfacp_start_divider_shipping' ], true ) )
            // We'll skip these for now as they're handled differently

            // Create attribute names (matching Divi 4 pattern)
            // Select dropdown: wfacp_{template_slug}_{field_key}_field
            // Text field: wfacp_{template_slug}_{field_key}_field_class
            const selectAttrName = `wfacp_${fieldStructure.template_slug}_${fieldKey}_field`;
            const textAttrName = `wfacp_${fieldStructure.template_slug}_${fieldKey}_field_class`;
            
            const selectAttrPath = `dynamic_field_classes.${sectionKey}.${fieldKey}`;
            const textAttrPath = `dynamic_field_classes_custom.${sectionKey}.${fieldKey}`;

            // Get default class value from attrs if exists
            // CRITICAL: Read from nested structure: attrs.dynamic_field_classes[sectionKey][fieldKey].desktop.value
            // Try multiple paths to handle different attribute formats from Visual Builder
            let currentSelectValue = '';
            if ((attrs as any)?.dynamic_field_classes?.[sectionKey]?.[fieldKey]?.desktop?.value) {
              currentSelectValue = (attrs as any).dynamic_field_classes[sectionKey][fieldKey].desktop.value;
            } else if ((attrs as any)?.dynamic_field_classes?.[sectionKey]?.[fieldKey]?.value) {
              // Fallback for flattened structure
              currentSelectValue = (attrs as any).dynamic_field_classes[sectionKey][fieldKey].value;
            }
            
            let currentTextValue = '';
            if ((attrs as any)?.dynamic_field_classes_custom?.[sectionKey]?.[fieldKey]?.innerContent?.desktop?.value) {
              currentTextValue = (attrs as any).dynamic_field_classes_custom[sectionKey][fieldKey].innerContent.desktop.value;
            } else if ((attrs as any)?.dynamic_field_classes_custom?.[sectionKey]?.[fieldKey]?.innerContent?.value) {
              currentTextValue = (attrs as any).dynamic_field_classes_custom[sectionKey][fieldKey].innerContent.value;
            } else if ((attrs as any)?.dynamic_field_classes_custom?.[sectionKey]?.[fieldKey]?.value) {
              currentTextValue = (attrs as any).dynamic_field_classes_custom[sectionKey][fieldKey].value;
            }

            // Determine default class (matching Divi 4 logic)
            // REST API provides default_class in field data
            let defaultValue = (field as any).default_class || 'wfacp-col-full';
            if (field.type === 'wfacp_html') {
              defaultValue = 'wfacp-col-full';
            }
            
            // CRITICAL: Prioritize currentSelectValue from attrs over defaultValue
            // This ensures saved values are used instead of defaults
            // IMPORTANT: Always read the latest value from attrs (which updates when saved)
            const finalSelectValue = currentSelectValue || defaultValue;

            // 1. Create Select Dropdown for field class (matching Divi 4: $this->add_select())
            // NOTE: We don't add a custom onChange here - let Divi handle attribute saving normally
            // We'll use useEffect to watch for attribute changes and update the preview
            const selectFieldConfig = {
              priority: fieldPriority++,
              component: {
                name: 'divi/select',
                type: 'field',
                props: {
                  options: classOptions,
                  // REMOVED: Custom onChange handler - it was preventing Divi from saving attributes
                  // Instead, we'll use useEffect to watch attrs changes and update preview
                },
              },
              render: true,
              attrName: selectAttrPath,
              label: field.label,
              features: {
                sticky: false,
                dynamicContent: false,
              },
              defaultAttr: {
                desktop: {
                  value: finalSelectValue, // CRITICAL: Always use the latest value from attrs
                },
              },
            };
            
            // CRITICAL: Always update defaultAttr to ensure it reflects the latest saved value
            // This is important because attrs can change after save, and we need the dropdown to show the correct value
            if (!sectionFields[selectAttrName]) {
              set(sectionFields, selectAttrName, selectFieldConfig);
            } else {
              // Field exists - update it with latest values
              sectionFields[selectAttrName].render = true;
              if (sectionFields[selectAttrName].component?.props) {
                sectionFields[selectAttrName].component.props.options = classOptions;
              }
              
              // CRITICAL: Always update defaultAttr to the latest value from attrs
              // This ensures the dropdown shows the correct saved value
              // We need to recreate the defaultAttr object to trigger Divi's reactivity
              sectionFields[selectAttrName].defaultAttr = {
                desktop: {
                  value: finalSelectValue,
                },
              };
            }

          // 2. Create Text Field for custom class (matching Divi 4: $this->add_text())
          // In Divi 4: $this->add_text( $this->custom_class_tab_id, 'wfacp_' . $template_slug . '_' . $field_key . '_field_class', ... )
          // Use innerContent structure for text fields (matching module.json pattern like cart_collapse_title)
          // CRITICAL: Field key should match the attribute name pattern, not have _innerContent suffix
          const textFieldKey = textAttrName; // Use the same key pattern as select fields
          if (!sectionFields[textFieldKey]) {
            set(sectionFields, textFieldKey, {
              settings: {
                innerContent: {
                  groupType: 'group-item',
                  item: {
                    groupSlug: groupSlug,
                    priority: fieldPriority++,
                    render: true, // CRITICAL: Must be true for field to appear
                    attrName: `${textAttrPath}.innerContent`,
                    label: `${field.label} - Custom Class`,
                    component: {
                      name: 'divi/text',
                      type: 'field',
                    },
                    features: {
                      sticky: false,
                      dynamicContent: false,
                    },
                    defaultAttr: {
                      desktop: {
                        value: currentTextValue || '',
                      },
                    },
                  },
                },
              },
            });
          } else {
            // Ensure render is true
            if (sectionFields[textFieldKey].settings?.innerContent?.item) {
              sectionFields[textFieldKey].settings.innerContent.item.render = true;
              // CRITICAL: Update defaultAttr with current value from attrs to ensure saved value is displayed
              const finalTextValue = currentTextValue || '';
              if (!sectionFields[textFieldKey].settings.innerContent.item.defaultAttr) {
                sectionFields[textFieldKey].settings.innerContent.item.defaultAttr = {
                  desktop: {
                    value: finalTextValue,
                  },
                };
              } else if (!sectionFields[textFieldKey].settings.innerContent.item.defaultAttr.desktop) {
                sectionFields[textFieldKey].settings.innerContent.item.defaultAttr.desktop = {
                  value: finalTextValue,
                };
              } else if (sectionFields[textFieldKey].settings.innerContent.item.defaultAttr.desktop.value !== finalTextValue) {
                sectionFields[textFieldKey].settings.innerContent.item.defaultAttr.desktop.value = finalTextValue;
              }
            }
          }
          } catch (fieldError) {
            // Error processing field - silently continue
          }
        });
        } catch (sectionError) {
          // Error processing section - silently continue
        }
      });
    }

    // CRITICAL: Ensure ALL design groups exist in config
    // Design groups are defined in module.json settings.groups but may not be in groupConfiguration
    // We need to add them from metadata if they're missing
    const designGroups = (metadata as any)?.settings?.groups || {};
    Object.keys(designGroups).forEach(groupKey => {
      if (groupKey.startsWith('design') && !config[groupKey]) {
        config[groupKey] = cloneDeep(designGroups[groupKey]);
      }
    });
    
    // Specifically ensure designProductSwitcher exists
    if (!config.designProductSwitcher && designGroups.designProductSwitcher) {
      config.designProductSwitcher = cloneDeep(designGroups.designProductSwitcher);
    }

    return config;
  }, [groupConfiguration, fieldStructure, attrs]);


  // CRITICAL: Always return ModuleGroups even if fieldStructure is null
  // This prevents the UI from getting stuck
  // Use a key that changes when fieldStructure loads to force ModuleGroups to remount
  // and pick up the updated dynamic groups (step tabs, checkout buttons, dynamic sections).
  const groupsKey = fieldStructure ? `loaded-${fieldStructure.step_count}` : 'loading';
  try {
    const groupsToPass = { ...updatedGroupConfiguration };
    return <ModuleGroups key={groupsKey} groups={groupsToPass} />;
  } catch (error) {
    // Return empty groups as fallback to prevent UI freeze
    return <ModuleGroups key={groupsKey} groups={groupConfiguration || {}} />;
  }
};
