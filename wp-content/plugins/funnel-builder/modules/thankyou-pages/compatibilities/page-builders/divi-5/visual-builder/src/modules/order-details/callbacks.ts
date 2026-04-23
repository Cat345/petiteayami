import { getAttrByMode } from '@divi/module-utils';

/**
 * Visibility callback for the Subscription design group.
 * Visible when order_subscription_preview toggle is ON.
 */
export const isSubscriptionGroupVisible = (context: any): boolean => {
  const val = getAttrByMode(context?.attrs?.order_subscription_preview?.innerContent, {
    baseBreakpoint:  context?.baseBreakpoint,
    breakpoint:      context?.breakpoint,
    breakpointNames: context?.breakpointNames,
    state:           context?.state,
  });
  return val === true || val === 'on';
};

/**
 * Visibility callback for the Download design group.
 * Visible when order_download_preview toggle is ON.
 */
export const isDownloadGroupVisible = (context: any): boolean => {
  const val = getAttrByMode(context?.attrs?.order_download_preview?.innerContent, {
    baseBreakpoint:  context?.baseBreakpoint,
    breakpoint:      context?.breakpoint,
    breakpointNames: context?.breakpointNames,
    state:           context?.state,
  });
  return val === true || val === 'on';
};
