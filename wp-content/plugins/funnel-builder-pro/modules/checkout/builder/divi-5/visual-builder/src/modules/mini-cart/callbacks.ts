import { getAttrByMode } from '@divi/module-utils';

/**
 * Visibility callback for the Coupon design group.
 * Returns true when enableCoupon toggle is 'on'.
 * Follows the Divi core WooCommerce pattern for group-level callbacks.visible.
 */
export const isCouponGroupVisible = (context: any): boolean => {
  return 'on' === getAttrByMode(context?.attrs?.enableCoupon, {
    baseBreakpoint:  context?.baseBreakpoint,
    breakpoint:      context?.breakpoint,
    breakpointNames: context?.breakpointNames,
    state:           context?.state,
  });
};
