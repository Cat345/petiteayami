// Divi dependencies.
import {
  type Metadata,
  type ModuleLibrary,
} from '@divi/types';

// Local dependencies.
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { OptinFormPopupEdit } from './edit';
import { OptinFormPopupAttrs } from './types';
import { placeholderContent } from './placeholder-content';
import { SettingsContent } from './settings-content';

export const optinFormPopupMetadata = metadata as Metadata.Values<OptinFormPopupAttrs>;

export const optinFormPopup: ModuleLibrary.Module.RegisterDefinition<OptinFormPopupAttrs> = {
  // Imported json has no inferred type hence type-cast is necessary.
  metadata:                 metadata as Metadata.Values<OptinFormPopupAttrs>,
  defaultAttrs:             defaultRenderAttributes as Metadata.DefaultAttributes<OptinFormPopupAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<OptinFormPopupAttrs>,
  placeholderContent,
  settings: {
    content: SettingsContent,
  },
  renderers: {
    edit: OptinFormPopupEdit,
  },
};
