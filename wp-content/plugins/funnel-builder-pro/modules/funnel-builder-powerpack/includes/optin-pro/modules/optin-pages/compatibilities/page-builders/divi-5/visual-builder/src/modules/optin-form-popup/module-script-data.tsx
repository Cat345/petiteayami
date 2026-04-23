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
import { OptinFormPopupAttrs } from './types';

/**
 * Optin Form Popup Module script data component.
 *
 * @since 1.0.0
 *
 * @param {ModuleScriptDataProps<OptinFormPopupAttrs>} props React component props.
 *
 * @returns {ReactElement}
 */
export const ModuleScriptData = ({
  elements,
}: ModuleScriptDataProps<OptinFormPopupAttrs>): ReactElement => (
  <Fragment>
    {elements.scriptData({
      attrName: 'module',
    })}
  </Fragment>
);
