import React, { Fragment, ReactElement } from 'react';
import type { ModuleScriptDataProps } from '@divi/module';
import type { CustomerDetailsAttrs } from './types';

export const ModuleScriptData = ({
  elements,
}: ModuleScriptDataProps<CustomerDetailsAttrs>): ReactElement => (
  <Fragment>
    {elements.scriptData({ attrName: 'module' })}
  </Fragment>
);
