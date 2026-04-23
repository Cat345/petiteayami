// Divi dependencies.
import { ModuleClassnamesParams, textOptionsClassnames } from '@divi/module';

// Local dependencies.
import { OptinFormPopupAttrs } from './types';

/**
 * Module classnames function for Optin Form Popup module.
 *
 * @since 1.0.0
 *
 * @param {ModuleClassnamesParams<OptinFormPopupAttrs>} param0 Function parameters.
 */
export const moduleClassnames = ({
  classnamesInstance,
  attrs,
}: ModuleClassnamesParams<OptinFormPopupAttrs>): void => {
  // Text Options.
  classnamesInstance.add(textOptionsClassnames(attrs?.module?.advanced?.text));
};
