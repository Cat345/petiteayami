import type { ModuleClassnamesParams } from '@divi/module';
import { textOptionsClassnames } from '@divi/module';
import type { CustomerDetailsAttrs } from './types';

export const moduleClassnames = ({
  classnamesInstance,
  attrs,
}: ModuleClassnamesParams<CustomerDetailsAttrs>): void => {
  classnamesInstance.add(textOptionsClassnames(attrs?.module?.advanced?.text));
};
