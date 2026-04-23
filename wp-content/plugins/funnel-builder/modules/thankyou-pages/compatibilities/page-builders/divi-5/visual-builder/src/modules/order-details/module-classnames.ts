import type { ModuleClassnamesParams } from '@divi/module';
import { textOptionsClassnames } from '@divi/module';
import type { OrderDetailsAttrs } from './types';

export const moduleClassnames = ({
  classnamesInstance,
  attrs,
}: ModuleClassnamesParams<OrderDetailsAttrs>): void => {
  classnamesInstance.add(textOptionsClassnames(attrs?.module?.advanced?.text));

  // Conditionally add hide classes based on toggle values (supports both D5 boolean and D4 "on"/"off" string).
  const subVal = attrs?.order_subscription_preview?.innerContent?.desktop?.value;
  const dlVal  = attrs?.order_download_preview?.innerContent?.desktop?.value;
  const isTruthy = (v: unknown): boolean => v === true || v === 'on';

  if (!isTruthy(subVal)) {
    classnamesInstance.add('wfty-hide-subscription', true);
  }
  if (!isTruthy(dlVal)) {
    classnamesInstance.add('wfty-hide-download', true);
  }
};
