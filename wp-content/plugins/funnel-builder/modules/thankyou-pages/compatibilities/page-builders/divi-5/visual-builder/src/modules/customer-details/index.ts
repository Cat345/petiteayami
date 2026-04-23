import type { Metadata, ModuleLibrary } from '@divi/types';
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { CustomerDetailsEdit } from './edit';
import type { CustomerDetailsAttrs } from './types';
import { placeholderContent } from './placeholder-content';
import { SettingsContent } from './settings-content';

export const customerDetailsMetadata = metadata as Metadata.Values<CustomerDetailsAttrs>;

export const customerDetails: ModuleLibrary.Module.RegisterDefinition<CustomerDetailsAttrs> = {
  metadata: metadata as Metadata.Values<CustomerDetailsAttrs>,
  defaultAttrs: defaultRenderAttributes as Metadata.DefaultAttributes<CustomerDetailsAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<CustomerDetailsAttrs>,
  placeholderContent,
  settings: {
    design: SettingsContent,
    content: SettingsContent,
  },
  renderers: { edit: CustomerDetailsEdit },
};
