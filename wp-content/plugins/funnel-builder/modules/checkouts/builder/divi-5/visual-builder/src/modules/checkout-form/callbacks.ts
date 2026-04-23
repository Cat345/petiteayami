import { getAttrByMode } from '@divi/module-utils';

/**
 * Visibility callback for the Tab design group.
 * Visible when select_type is 'tab' (default).
 */
export const isTabGroupVisible = (context: any): boolean => {
  const type = getAttrByMode(context?.attrs?.select_type, {
    baseBreakpoint:  context?.baseBreakpoint,
    breakpoint:      context?.breakpoint,
    breakpointNames: context?.breakpointNames,
    state:           context?.state,
  });
  // Default to 'tab' when no type is set
  return !type || type === 'tab';
};

/**
 * Visibility callback for the Breadcrumb design group.
 * Visible when select_type is 'bredcrumb'.
 */
export const isBreadcrumbGroupVisible = (context: any): boolean => {
  const type = getAttrByMode(context?.attrs?.select_type, {
    baseBreakpoint:  context?.baseBreakpoint,
    breakpoint:      context?.breakpoint,
    breakpointNames: context?.breakpointNames,
    state:           context?.state,
  });
  return type === 'bredcrumb';
};

/**
 * Visibility callback for the Collapsible Order Summary design group.
 * Visible when enable_callapse_order_summary is 'on'.
 */
export const isCollapsibleOrderSummaryVisible = (context: any): boolean => {
  return 'on' === getAttrByMode(context?.attrs?.enable_callapse_order_summary, {
    baseBreakpoint:  context?.baseBreakpoint,
    breakpoint:      context?.breakpoint,
    breakpointNames: context?.breakpointNames,
    state:           context?.state,
  });
};
