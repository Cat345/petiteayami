// Divi dependencies.
import { ModuleClassnamesParams, textOptionsClassnames } from '@divi/module';

// Local dependencies.
import { OptinFormAttrs } from './types';

/**
 * Module classnames function for Optin Form module.
 *
 * @since 1.0.0
 *
 * @param {ModuleClassnamesParams<OptinFormAttrs>} param0 Function parameters.
 */
export const moduleClassnames = ({
  classnamesInstance,
  attrs,
}: ModuleClassnamesParams<OptinFormAttrs>): void => {
  // Text Options.
  classnamesInstance.add(textOptionsClassnames(attrs?.module?.advanced?.text));
};
