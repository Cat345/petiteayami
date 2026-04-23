// External Dependencies.
import React, {
  Fragment,
  ReactElement,
} from 'react';

// Divi Dependencies.
import {
  ModuleScriptDataProps,
} from '@divi/module';

// Local Dependencies.
import { OptinFormAttrs } from './types';

/**
 * Optin Form Module script data component.
 *
 * @since 1.0.0
 *
 * @param {ModuleScriptDataProps<OptinFormAttrs>} props React component props.
 *
 * @returns {ReactElement}
 */
export const ModuleScriptData = ({
  elements,
}: ModuleScriptDataProps<OptinFormAttrs>): ReactElement => (
  <Fragment>
    {elements.scriptData({
      attrName: 'module',
    })}
  </Fragment>
);
