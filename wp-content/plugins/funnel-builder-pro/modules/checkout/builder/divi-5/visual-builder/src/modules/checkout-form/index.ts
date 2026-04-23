// Divi dependencies.
import {
  type Metadata,
  type ModuleLibrary,
} from '@divi/types';

// Local dependencies.
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { CheckoutFormEdit } from './edit';
import { CheckoutFormAttrs } from './types';
import { placeholderContent } from './placeholder-content';
import { SettingsContent } from './settings-content';
import { isTabGroupVisible, isBreadcrumbGroupVisible, isCollapsibleOrderSummaryVisible } from './callbacks';

export const checkoutFormMetadata = metadata as Metadata.Values<CheckoutFormAttrs>;

export const checkoutForm: ModuleLibrary.Module.RegisterDefinition<CheckoutFormAttrs> = {
  // Imported json has no inferred type hence type-cast is necessary.
  metadata:                 metadata as Metadata.Values<CheckoutFormAttrs>,
  defaultAttrs:             defaultRenderAttributes as Metadata.DefaultAttributes<CheckoutFormAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<CheckoutFormAttrs>,
  placeholderContent,
  settings: {
    content: SettingsContent,
  },
  callbacks: {
    design: {
      designHeaderTab: {
        visible: isTabGroupVisible,
      },
      designHeaderBreadcrumb: {
        visible: isBreadcrumbGroupVisible,
      },
      designCollapsibleOrderSummaryBackground: {
        visible: isCollapsibleOrderSummaryVisible,
      },
    },
  } as any,
  renderers: {
    edit: CheckoutFormEdit,
  },
};
