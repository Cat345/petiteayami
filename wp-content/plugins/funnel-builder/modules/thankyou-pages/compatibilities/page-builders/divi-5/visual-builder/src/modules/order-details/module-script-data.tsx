import React, { Fragment, ReactElement } from 'react';
import type { ModuleScriptDataProps } from '@divi/module';
import type { OrderDetailsAttrs } from './types';

export const ModuleScriptData = ({
  elements,
}: ModuleScriptDataProps<OrderDetailsAttrs>): ReactElement => (
  <Fragment>
    {elements.scriptData({ attrName: 'module' })}
  </Fragment>
);
