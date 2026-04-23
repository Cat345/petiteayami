// Divi dependencies.
import {
  type Metadata,
  type ModuleLibrary,
} from '@divi/types';

// Local dependencies.
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { OptinFormEdit } from './edit';
import { OptinFormAttrs } from './types';
import { placeholderContent } from './placeholder-content';
import { SettingsContent } from './settings-content';

export const optinFormMetadata = metadata as Metadata.Values<OptinFormAttrs>;

export const optinForm: ModuleLibrary.Module.RegisterDefinition<OptinFormAttrs> = {
  // Imported json has no inferred type hence type-cast is necessary.
  metadata:                 metadata as Metadata.Values<OptinFormAttrs>,
  defaultAttrs:             defaultRenderAttributes as Metadata.DefaultAttributes<OptinFormAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<OptinFormAttrs>,
  placeholderContent,
  settings: {
    content: SettingsContent,
  },
  renderers: {
    edit: OptinFormEdit,
  },
};
