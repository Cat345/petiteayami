import type { Metadata, ModuleLibrary } from '@divi/types';
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { OrderDetailsEdit } from './edit';
import type { OrderDetailsAttrs } from './types';
import { placeholderContent } from './placeholder-content';
import { SettingsContent } from './settings-content';
import { isSubscriptionGroupVisible, isDownloadGroupVisible } from './callbacks';

export const orderDetailsMetadata = metadata as Metadata.Values<OrderDetailsAttrs>;

export const orderDetails: ModuleLibrary.Module.RegisterDefinition<OrderDetailsAttrs> = {
  metadata: metadata as Metadata.Values<OrderDetailsAttrs>,
  defaultAttrs: defaultRenderAttributes as Metadata.DefaultAttributes<OrderDetailsAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<OrderDetailsAttrs>,
  placeholderContent,
  settings: {
    content: SettingsContent,
  },
  callbacks: {
    design: {
      designSubscription: {
        visible: isSubscriptionGroupVisible,
      },
      designDownload: {
        visible: isDownloadGroupVisible,
      },
    },
  } as any,
  renderers: { edit: OrderDetailsEdit },
};
