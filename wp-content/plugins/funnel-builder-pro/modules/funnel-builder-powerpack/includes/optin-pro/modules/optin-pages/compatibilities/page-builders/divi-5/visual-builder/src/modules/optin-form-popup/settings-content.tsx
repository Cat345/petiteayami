// External Dependencies.
import React, { type ReactElement, useMemo } from 'react';
import { set, cloneDeep } from 'lodash';

// Divi Dependencies.
import {
  type Module,
} from '@divi/types';
import { ModuleGroups } from '@divi/module';

// Local Dependencies.
import { OptinFormPopupAttrs } from './types';
import metadata from './module.json';

/**
 * Content panel component for the Optin Form Popup module settings modal.
 *
 * @since 1.0.0
 *
 * @param {Module.Settings.Panel.Props} param0 Content panel props.
 *
 * @returns {ReactElement}
 */
export const SettingsContent = ({
  groupConfiguration,
  metadata: panelMetadata,
}: Module.Settings.Panel.Props<OptinFormPopupAttrs>): ReactElement => {
  // Use module metadata from import, fallback to panel metadata
  const moduleMetadata = metadata || panelMetadata;

  // Process group configuration to inject icon field manually
  const processedConfig = useMemo(() => {
    if (!groupConfiguration) {
      return groupConfiguration;
    }

    // Clone to avoid mutations
    const config = cloneDeep(groupConfiguration);

    // Get contentCTAButton group
    const contentGroup = config?.contentCTAButton;
    if (!contentGroup || !contentGroup.component?.props?.fields) {
      return config;
    }

    const fields = contentGroup.component.props.fields;

    // Add icon field if it doesn't exist - use set() for consistency
    if (!fields.btn_icon_field && moduleMetadata?.attributes?.btn_icon_field?.settings) {
      const iconSettings = moduleMetadata.attributes.btn_icon_field.settings.item;
      set(fields, 'btn_icon_field', {
        priority: iconSettings.priority || 30,
        component: iconSettings.component || {
          name: 'divi/icon-picker',
          type: 'field',
        },
        render: iconSettings.render !== false,
        attrName: iconSettings.attrName || 'btn_icon_field',
        label: iconSettings.label || 'Icon',
        description: iconSettings.description || 'Select an icon for the button',
        features: iconSettings.features || {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: moduleMetadata.attributes.btn_icon_field.default || {
          desktop: {
            value: '',
          },
        },
      });
    }

    // Handle btn_icon_field_position field - use FieldLibrary.Select.Options format like product-title's htmlTag
    // Convert simple format from module.json to FieldLibrary.Select.Options format
    const iconPositionFieldExists = !!fields.btn_icon_field_position;

    if (!iconPositionFieldExists) {
      // Get options from module.json and convert to FieldLibrary.Select.Options format
      const moduleOptions = moduleMetadata?.attributes?.btn_icon_field_position?.settings?.item?.component?.props?.options || {
        left: 'Before',
        right: 'After',
      };

      // Convert simple format to FieldLibrary.Select.Options format
      const iconPositionOptions: Record<string, { label: string }> = {};
      Object.keys(moduleOptions).forEach((key) => {
        iconPositionOptions[key] = { label: moduleOptions[key] };
      });

      set(fields, 'btn_icon_field_position', {
        priority: moduleMetadata?.attributes?.btn_icon_field_position?.settings?.item?.priority || 31,
        component: {
          name: 'divi/select',
          type: 'field',
          props: {
            options: iconPositionOptions,
          },
        },
        render: true,
        attrName: 'btn_icon_field_position',
        label: moduleMetadata?.attributes?.btn_icon_field_position?.settings?.item?.label || 'Icon Position',
        description: moduleMetadata?.attributes?.btn_icon_field_position?.settings?.item?.description || 'Position of the icon relative to the title',
        features: moduleMetadata?.attributes?.btn_icon_field_position?.settings?.item?.features || {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: moduleMetadata?.attributes?.btn_icon_field_position?.default || {
          desktop: {
            value: 'left',
          },
        },
      });
    }

    return config;
  }, [groupConfiguration, moduleMetadata]);

  return <ModuleGroups groups={processedConfig} />;
};
