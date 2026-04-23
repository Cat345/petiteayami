// Divi dependencies.
import {
  type Metadata,
  type ModuleLibrary,
} from '@divi/types';

// Local dependencies.
import metadata from './module.json';
import defaultRenderAttributes from './module-default-render-attributes.json';
import defaultPrintedStyleAttributes from './module-default-printed-style-attributes.json';
import { MiniCartEdit } from './edit';
import { MiniCartAttrs } from './types';
import { placeholderContent } from './placeholder-content';
import { SettingsContent } from './settings-content';
import { isCouponGroupVisible } from './callbacks';

export const miniCartMetadata = metadata as Metadata.Values<MiniCartAttrs>;

export const miniCart: ModuleLibrary.Module.RegisterDefinition<MiniCartAttrs> = {
  // Imported json has no inferred type hence type-cast is necessary.
  metadata:                 metadata as Metadata.Values<MiniCartAttrs>,
  defaultAttrs:             defaultRenderAttributes as Metadata.DefaultAttributes<MiniCartAttrs>,
  defaultPrintedStyleAttrs: defaultPrintedStyleAttributes as Metadata.DefaultAttributes<MiniCartAttrs>,
  placeholderContent,
  settings: {
    content: SettingsContent,
  },
  callbacks: {
    design: {
      designCoupon: {
        visible: isCouponGroupVisible,
      },
    },
  } as any,
  renderers: {
    edit: MiniCartEdit,
  },
};
