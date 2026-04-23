// External Dependencies.
import React, { ReactElement, useMemo, useEffect, useState, useRef, useCallback } from 'react';
import { merge } from 'lodash';

// Divi Dependencies.
import { ModuleContainer } from '@divi/module';
// useFetch removed — using native window.fetch with AbortController for request cancellation

// Local Dependencies.
import { MiniCartEditProps } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import defaultRenderAttributes from './module-default-render-attributes.json';

/**
 * Component to update heading inside rendered HTML to match frontend structure
 */
const HeadingUpdater = ({
  headingValue,
  cartContentRef,
  renderedHtml
}: {
  headingValue: string;
  cartContentRef: React.RefObject<HTMLDivElement>;
  renderedHtml: string;
}) => {
  useEffect(() => {
    if (!cartContentRef.current || !renderedHtml) {
      return;
    }

    // Use setTimeout to ensure DOM is updated after dangerouslySetInnerHTML
    const timeoutId = setTimeout(() => {
      // Find the .wfacp_mini_cart_start_h container
      const cartContainer = cartContentRef.current?.querySelector('.wfacp_mini_cart_start_h');
      if (!cartContainer) {
        return;
      }

      // Find existing heading to update
      const existingHeading = cartContainer.querySelector('.wfacp-order-summary-label');
      if (!existingHeading) {
        return;
      }

      // Update text content directly - styles are handled by styles.tsx
      // Respect empty value — hide heading element when user clears the input
      existingHeading.textContent = headingValue || '';
      (existingHeading as HTMLElement).style.display = headingValue ? '' : 'none';
    }, 0);

    return () => clearTimeout(timeoutId);
  }, [headingValue, renderedHtml, cartContentRef]);

  return null;
};

/**
 * Component to update saving price message text directly in DOM
 * Works like CouponButtonTextUpdater - updates immediately when typing
 * NOTE: Only skips update if messageValue contains shortcodes ({{saving_amount}}, {{saving_percentage}})
 * because those need to be processed by the server. The toggle effect handles those via AJAX.
 */
const SavingMessageUpdater = ({
  messageValue,
  cartContentRef,
  renderedHtml,
  isToggleEnabled
}: {
  messageValue: string;
  cartContentRef: React.RefObject<HTMLDivElement>;
  renderedHtml: string;
  isToggleEnabled: boolean;
}) => {
  useEffect(() => {
    if (!cartContentRef.current || !renderedHtml) {
      return;
    }

    // Only update if toggle is enabled (element exists in DOM)
    if (!isToggleEnabled) {
      return;
    }

    // CRITICAL: Only check messageValue for shortcodes (like CouponButtonTextUpdater pattern)
    // If messageValue contains shortcodes, skip DOM update - server needs to process them
    // Don't check DOM content - it might have processed values that don't match
    const hasShortcodes = messageValue && (messageValue.includes('{{saving_amount}}') || messageValue.includes('{{saving_percentage}}'));
    
    if (hasShortcodes) {
      return; // Skip DOM update - server needs to process shortcodes
    }

    // Use setTimeout to ensure DOM is updated (same pattern as CouponButtonTextUpdater)
    const timeoutId = setTimeout(() => {
      // Find the saving message element - it's in a table row with class wfacp-saving-amount
      const savingRow = cartContentRef.current?.querySelector('.wfacp-saving-amount');
      if (savingRow) {
        // Find the td element that contains the message text
        const messageCell = savingRow.querySelector('td');
        if (messageCell) {
          // CRITICAL: Preserve the span wrapper structure (td > span > svg + text)
          // The CSS expects td>span, and the span contains an SVG icon + text
          let spanElement = messageCell.querySelector('span');
          if (!spanElement) {
            // Create span if it doesn't exist
            spanElement = document.createElement('span');
            messageCell.appendChild(spanElement);
          }
          
          // Preserve the SVG icon if it exists
          const svgElement = spanElement.querySelector('svg');
          if (svgElement) {
            // Clone the SVG to preserve it
            const svgClone = svgElement.cloneNode(true) as SVGElement;
            // Update span content: SVG + message text
            spanElement.innerHTML = '';
            spanElement.appendChild(svgClone);
            spanElement.appendChild(document.createTextNode(messageValue || ''));
          } else {
            // No SVG, just update span textContent (but keep span structure)
            spanElement.textContent = messageValue || '';
          }
        }
      }
    }, 0);

    return () => clearTimeout(timeoutId);
  }, [messageValue, renderedHtml, cartContentRef, isToggleEnabled]);

  return null;
};

/**
 * Component to update low stock message text directly in DOM
 * Works like CouponButtonTextUpdater - updates immediately when typing
 * NOTE: Only skips update if messageValue contains shortcodes ({{quantity}})
 * because those need to be processed by the server. The toggle effect handles those via AJAX.
 */
const LowStockMessageUpdater = ({
  messageValue,
  cartContentRef,
  renderedHtml,
  isToggleEnabled
}: {
  messageValue: string;
  cartContentRef: React.RefObject<HTMLDivElement>;
  renderedHtml: string;
  isToggleEnabled: boolean;
}) => {
  useEffect(() => {
    if (!cartContentRef.current || !renderedHtml) {
      return;
    }

    // Only update if toggle is enabled (element exists in DOM)
    if (!isToggleEnabled) {
      return;
    }

    // CRITICAL: Only check messageValue for shortcodes (like CouponButtonTextUpdater pattern)
    // If messageValue contains shortcodes, skip DOM update - server needs to process them
    // Don't check DOM content - it might have processed values that don't match
    const hasShortcodes = messageValue && messageValue.includes('{{quantity}}');
    
    if (hasShortcodes) {
      return; // Skip DOM update - server needs to process shortcodes
    }

    // Use setTimeout to ensure DOM is updated (same pattern as CouponButtonTextUpdater)
    const timeoutId = setTimeout(() => {
      // Find low stock message elements (class wfacp_stocks)
      // CRITICAL: Only update the first element to avoid duplicates
      // Duplicates are caused by PHP rendering issues, but we only need to update one
      const lowStockElement = cartContentRef.current?.querySelector('.wfacp_stocks');
      if (lowStockElement) {
        // Update only the first element (same pattern as CouponButtonTextUpdater)
        lowStockElement.textContent = messageValue || '';
        
        // Remove any duplicate elements that might exist
        const allLowStockElements = cartContentRef.current?.querySelectorAll('.wfacp_stocks');
        if (allLowStockElements && allLowStockElements.length > 1) {
          // Keep the first one, remove the rest
          for (let i = 1; i < allLowStockElements.length; i++) {
            const duplicate = allLowStockElements[i];
            if (duplicate.parentNode) {
              duplicate.parentNode.removeChild(duplicate);
            }
          }
        }
      }
    }, 0);

    return () => clearTimeout(timeoutId);
  }, [messageValue, renderedHtml, cartContentRef, isToggleEnabled]);

  return null;
};

/**
 * Component to update coupon button text directly in DOM
 */
const CouponButtonTextUpdater = ({
  buttonTextValue,
  cartContentRef,
  renderedHtml
}: {
  buttonTextValue: string;
  cartContentRef: React.RefObject<HTMLDivElement>;
  renderedHtml: string;
}) => {
  useEffect(() => {
    if (!cartContentRef.current || !renderedHtml) {
      return;
    }

    // Use setTimeout to ensure DOM is updated
    const timeoutId = setTimeout(() => {
      // Find the coupon button element (class wfacp_coupon_button)
      const couponButton = cartContentRef.current?.querySelector('.wfacp_coupon_button');
      if (couponButton) {
        // Update both the button text content and the value attribute
        couponButton.textContent = buttonTextValue || 'Apply';
        couponButton.setAttribute('value', buttonTextValue || 'Apply');
      }
    }, 0);

    return () => clearTimeout(timeoutId);
  }, [buttonTextValue, renderedHtml, cartContentRef]);

  return null;
};

// Note: Toggle updates now use immediate AJAX fetch (like Divi 4 pattern)
// No CSS manipulation needed - server returns correct HTML structure

/**
 * Mini Cart Module edit component of visual builder.
 *
 * @since 1.0.0
 *
 * @param {MiniCartEditProps} props React component props.
 *
 * @returns {ReactElement}
 */
export const MiniCartEdit = (props: MiniCartEditProps): ReactElement => {
  const {
    attrs,
    elements,
    id,
    name,
  } = props;

  // CRITICAL: Merge defaults with attrs to ensure default values are always present
  // This prevents placeholder from showing when default value is saved
  // Divi 5 doesn't save attributes that match defaults, so we need to merge them here
  const mergedAttrs = useMemo(() => {
    if (!attrs || Object.keys(attrs).length === 0) {
      return defaultRenderAttributes as typeof attrs;
    }
    // Merge defaults with current attrs (defaults are base, current overrides)
    return merge({}, defaultRenderAttributes, attrs) as typeof attrs;
  }, [attrs]);


  // State for rendered HTML
  const [renderedHtml, setRenderedHtml] = useState<string>('');
  const [isLoading, setIsLoading] = useState<boolean>(true);

  // Track request ID to ignore stale responses
  const requestIdRef = useRef<number>(0);
  const isFetchingRef = useRef<boolean>(false);
  const abortControllerRef = useRef<AbortController | null>(null);
  const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);
  const prevAttrsRef = useRef<string>('');
  const cartContentRef = useRef<HTMLDivElement | null>(null);
  const latestHtmlRef = useRef<string>(''); // Store latest HTML for selective replacement
  const replacementDoneRef = useRef<boolean>(false); // Track if selective replacement was done
  const isInitialRenderRef = useRef<boolean>(true); // Track if this is the initial render
  const [selectiveUpdateTrigger, setSelectiveUpdateTrigger] = useState<number>(0); // Trigger for selective replacement

  // Memoize post ID to avoid recalculating on every render
  const postId = useMemo((): number | null => {
    if (typeof window === 'undefined') {
      return null;
    }

    const win = window as any;
    const urlParams = new URLSearchParams(window.location.search);
    const etWfacpId = urlParams.get('et_wfacp_id');
    if (etWfacpId) {
      const parsed = parseInt(etWfacpId, 10);
      if (!isNaN(parsed) && parsed > 0) {
        return parsed;
      }
    }

    const methods = [
      () => win.et_fb_post_id,
      () => win.et_fb?.post_id,
      () => urlParams.get('post_id') || urlParams.get('p') || urlParams.get('et_post_id'),
      () => {
        const pathMatch = window.location.pathname.match(/\/post\.php\?post=(\d+)/);
        return pathMatch?.[1];
      },
      () => win.et_pb_post_id,
      () => win.etPbPostId,
      () => win.et?.builder?.postId,
      () => win.et?.builder?.post_id,
      () => {
        const postIdInput = document.querySelector('input[name="post_ID"]') as HTMLInputElement;
        return postIdInput?.value || null;
      },
    ];

    for (const method of methods) {
      const value = method();
      if (value) {
        const parsed = parseInt(String(value), 10);
        if (!isNaN(parsed) && parsed > 0) {
          return parsed;
        }
      }
    }

    return null;
  }, []);

  // Track heading value specifically for instant updates (like accept-link text)
  // Empty string is valid — means user intentionally cleared the heading
  const headingValue = useMemo(() => {
    try {
      const value = mergedAttrs?.miniCartHeading?.innerContent?.desktop?.value;
      // Handle both string and object types
      if (typeof value === 'string') {
        return value;
      }
      if (value && typeof value === 'object' && 'text' in value) {
        return String(value.text ?? '');
      }
      return '';
    } catch (e) {
      return '';
    }
  }, [mergedAttrs?.miniCartHeading?.innerContent?.desktop?.value]);

  // Create a key based on heading value to force re-render when heading changes
  // This ensures elements.render() updates instantly when heading/title changes
  const headingKey = useMemo(() => {
    try {
      const headingVal = mergedAttrs?.miniCartHeading?.innerContent?.desktop?.value ?? '';
      return `heading-${typeof headingVal === 'string' ? headingVal : JSON.stringify(headingVal)}`;
    } catch (e) {
      return `heading-${id || 'default'}`;
    }
  }, [mergedAttrs?.miniCartHeading?.innerContent?.desktop?.value, id]);

  // Serialize attrs for change detection (for other attributes, excluding toggle fields and text fields with instant updates)
  // These are handled separately with instant updates
  const attrsKey = useMemo(() => {
    try {
      // Create a copy without fields that have instant updates
      const attrsForDebounce: any = { ...mergedAttrs };
      // Exclude toggle fields (handled by toggle effect)
      if (attrsForDebounce.enableProductImage) delete attrsForDebounce.enableProductImage;
      if (attrsForDebounce.enableQuantityBox) delete attrsForDebounce.enableQuantityBox;
      if (attrsForDebounce.enableDeleteItem) delete attrsForDebounce.enableDeleteItem;
      if (attrsForDebounce.miniCartEnableStrikeThroughPrice) delete attrsForDebounce.miniCartEnableStrikeThroughPrice;
      if (attrsForDebounce.miniCartEnableLowStockTrigger) delete attrsForDebounce.miniCartEnableLowStockTrigger;
      if (attrsForDebounce.miniCartEnableSavingPriceMessage) delete attrsForDebounce.miniCartEnableSavingPriceMessage;
      if (attrsForDebounce.enableCoupon) delete attrsForDebounce.enableCoupon;
      if (attrsForDebounce.enableCouponCollapsible) delete attrsForDebounce.enableCouponCollapsible;
      // Exclude text/textarea fields with instant updates
      if (attrsForDebounce.miniCartCouponButtonText) delete attrsForDebounce.miniCartCouponButtonText;
      if (attrsForDebounce.miniCartHeading) delete attrsForDebounce.miniCartHeading;
      if (attrsForDebounce.miniCartSavingPriceMessage) delete attrsForDebounce.miniCartSavingPriceMessage;
      if (attrsForDebounce.miniCartLowStockMessage) delete attrsForDebounce.miniCartLowStockMessage;
      return JSON.stringify(attrsForDebounce);
    } catch (e) {
      return String(mergedAttrs);
    }
  }, [mergedAttrs]);

  // Fetch HTML function - not memoized to avoid circular dependencies
  const fetchHtml = useCallback(() => {
    // Clear any pending debounce
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
      debounceTimerRef.current = null;
    }

    // Abort any in-flight request so we always get the latest content
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    abortControllerRef.current = new AbortController();

    // Increment request ID to track this request
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;

    isFetchingRef.current = true;
    setIsLoading(true);

    // CRITICAL: Ensure all toggle values are explicitly set with defaults
    // This prevents server from using its own defaults when module is first dragged
    const attrsForFetch = JSON.parse(JSON.stringify(mergedAttrs));
    
    // Ensure all toggle attributes exist with proper structure and default values
    if (!attrsForFetch.enableProductImage) attrsForFetch.enableProductImage = { desktop: { value: 'on' } };
    if (!attrsForFetch.enableQuantityBox) attrsForFetch.enableQuantityBox = { desktop: { value: 'on' } };
    if (!attrsForFetch.enableDeleteItem) attrsForFetch.enableDeleteItem = { desktop: { value: 'on' } };
    if (!attrsForFetch.miniCartEnableStrikeThroughPrice) attrsForFetch.miniCartEnableStrikeThroughPrice = { desktop: { value: 'off' } };
    if (!attrsForFetch.miniCartEnableLowStockTrigger) attrsForFetch.miniCartEnableLowStockTrigger = { desktop: { value: 'off' } };
    if (!attrsForFetch.miniCartEnableSavingPriceMessage) attrsForFetch.miniCartEnableSavingPriceMessage = { desktop: { value: 'off' } };
    if (!attrsForFetch.enableCoupon) attrsForFetch.enableCoupon = { desktop: { value: 'off' } };
    if (!attrsForFetch.enableCouponCollapsible) attrsForFetch.enableCouponCollapsible = { desktop: { value: 'off' } };

    // Ensure nested structure exists
    if (!attrsForFetch.miniCartEnableStrikeThroughPrice.desktop) attrsForFetch.miniCartEnableStrikeThroughPrice.desktop = { value: 'off' };
    if (!attrsForFetch.miniCartEnableLowStockTrigger.desktop) attrsForFetch.miniCartEnableLowStockTrigger.desktop = { value: 'off' };
    if (!attrsForFetch.miniCartEnableSavingPriceMessage.desktop) attrsForFetch.miniCartEnableSavingPriceMessage.desktop = { value: 'off' };
    if (!attrsForFetch.enableCoupon.desktop) attrsForFetch.enableCoupon.desktop = { value: 'off' };
    if (!attrsForFetch.enableCouponCollapsible.desktop) attrsForFetch.enableCouponCollapsible.desktop = { value: 'off' };

    // Set the actual values (use mergedAttrs values if they exist, otherwise use defaults)
    attrsForFetch.miniCartEnableStrikeThroughPrice.desktop.value = mergedAttrs?.miniCartEnableStrikeThroughPrice?.desktop?.value ?? 'off';
    attrsForFetch.miniCartEnableLowStockTrigger.desktop.value = mergedAttrs?.miniCartEnableLowStockTrigger?.desktop?.value ?? 'off';
    attrsForFetch.miniCartEnableSavingPriceMessage.desktop.value = mergedAttrs?.miniCartEnableSavingPriceMessage?.desktop?.value ?? 'off';
    attrsForFetch.enableCoupon.desktop.value = mergedAttrs?.enableCoupon?.desktop?.value ?? 'off';
    attrsForFetch.enableCouponCollapsible.desktop.value = mergedAttrs?.enableCouponCollapsible?.desktop?.value ?? 'off';

    const restRoute = '/wp-json/wfacp/v1/mini-cart/render';

    // Get nonce: try current window, parent window, and Divi's et_fb_options
    const win = window as any;
    const nonce = win.wpApiSettings?.nonce
      || win.parent?.wpApiSettings?.nonce
      || win.et_fb_options?.nonce
      || '';
    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    if (nonce) {
      headers['X-WP-Nonce'] = nonce;
    }

    window.fetch(restRoute, {
      method: 'POST',
      signal: abortControllerRef.current.signal,
      credentials: 'same-origin',
      body: JSON.stringify({
        attrs: attrsForFetch,
        id: id || '',
        post_id: postId || 0,
      }),
      headers,
    })
      .then((r) => r.json())
      .then((response: any) => {
        // Ignore stale responses
        if (currentRequestId !== requestIdRef.current) {
          return;
        }

        const html = response?.html || null;

        // Update state only if this is still the current request
        if (currentRequestId === requestIdRef.current) {
          const newHtml = html && typeof html === 'string' && html.length > 0 ? html : '';
          setRenderedHtml(newHtml);
          setIsLoading(false);
          isFetchingRef.current = false;
          // Mark initial render as complete after first successful fetch
          if (isInitialRenderRef.current && newHtml) {
            isInitialRenderRef.current = false;
          }
        }
      })
      .catch((error: any) => {
        // Don't update state if aborted — a new request is already in flight
        if (error?.name === 'AbortError') return;
        if (currentRequestId === requestIdRef.current) {
          setRenderedHtml('');
          setIsLoading(false);
          isFetchingRef.current = false;
        }
      });
  }, [mergedAttrs, id, postId]);

  // Initial mount effect - fetch immediately without debounce
  useEffect(() => {
    // Fetch immediately on mount
    fetchHtml();
  }, []); // Empty deps - only run on mount

  // Track coupon button text for instant DOM updates (no AJAX - use DOM updater only)
  const couponButtonTextValue = useMemo(() => {
    try {
      const value = mergedAttrs?.miniCartCouponButtonText?.innerContent?.desktop?.value;
      return String(value || 'Apply');
    } catch (e) {
      return 'Apply';
    }
  }, [mergedAttrs?.miniCartCouponButtonText?.innerContent?.desktop?.value]);

  // Track saving price message for updates (same pattern as coupon button text)
  // These updaters handle DOM updates directly - AJAX only needed when toggles change (handled by toggle effect)
  const savingPriceMessageValue = useMemo(() => {
    try {
      return mergedAttrs?.miniCartSavingPriceMessage?.innerContent?.desktop?.value || '';
    } catch (e) {
      return '';
    }
  }, [mergedAttrs?.miniCartSavingPriceMessage?.innerContent?.desktop?.value]);

  // Track low stock message for updates (same pattern as coupon button text)
  const lowStockMessageValue = useMemo(() => {
    try {
      return mergedAttrs?.miniCartLowStockMessage?.innerContent?.desktop?.value || '';
    } catch (e) {
      return '';
    }
  }, [mergedAttrs?.miniCartLowStockMessage?.innerContent?.desktop?.value]);

  // Track toggle field values - watch attrs directly like Divi 4's componentDidUpdate
  // Extract toggle values from attrs to catch changes immediately when Divi updates them
  const getToggleValue = (key: string, defaultValue: string) => {
    return attrs?.[key]?.desktop?.value ?? mergedAttrs?.[key]?.desktop?.value ?? defaultValue;
  };

  const currentToggleValues = useMemo(() => {
    return {
      enableProductImage: getToggleValue('enableProductImage', 'on'),
      enableQuantityBox: getToggleValue('enableQuantityBox', 'on'),
      enableDeleteItem: getToggleValue('enableDeleteItem', 'on'),
      miniCartEnableStrikeThroughPrice: getToggleValue('miniCartEnableStrikeThroughPrice', 'off'),
      miniCartEnableLowStockTrigger: getToggleValue('miniCartEnableLowStockTrigger', 'off'),
      miniCartEnableSavingPriceMessage: getToggleValue('miniCartEnableSavingPriceMessage', 'off'),
      enableCoupon: getToggleValue('enableCoupon', 'off'),
      enableCouponCollapsible: getToggleValue('enableCouponCollapsible', 'off'),
    };
  }, [
    attrs?.enableProductImage?.desktop?.value,
    attrs?.enableQuantityBox?.desktop?.value,
    attrs?.enableDeleteItem?.desktop?.value,
    attrs?.miniCartEnableStrikeThroughPrice?.desktop?.value,
    attrs?.miniCartEnableLowStockTrigger?.desktop?.value,
    attrs?.miniCartEnableSavingPriceMessage?.desktop?.value,
    attrs?.enableCoupon?.desktop?.value,
    attrs?.enableCouponCollapsible?.desktop?.value,
  ]);

  const toggleValuesKey = JSON.stringify(currentToggleValues);
  const prevToggleValuesRef = useRef<string>('');
  const prevToggleValuesObjRef = useRef<any>(null);

  // Effect to handle toggle changes - immediate AJAX fetch (like Divi 4 Blog module)
  // This matches Divi 4's pattern: when toggles change → fetch new HTML from server immediately
  useEffect(() => {
    // Skip on initial mount
    if (prevToggleValuesRef.current === '') {
      prevToggleValuesRef.current = toggleValuesKey;
      prevToggleValuesObjRef.current = { ...currentToggleValues };
      return;
    }

    // Check if toggle values actually changed
    if (prevToggleValuesRef.current === toggleValuesKey) {
      return;
    }

    // Store previous values before updating (capture for use in promise callback)
    const prevToggleValues = prevToggleValuesObjRef.current ? { ...prevToggleValuesObjRef.current } : {};
    
    // Check if this toggle change affects sections that need full replacement
    // Total Saving and Coupon toggles change the overall structure significantly
    const isStructuralToggle = 
      prevToggleValues.miniCartEnableSavingPriceMessage !== currentToggleValues.miniCartEnableSavingPriceMessage ||
      prevToggleValues.enableCoupon !== currentToggleValues.enableCoupon ||
      prevToggleValues.enableCouponCollapsible !== currentToggleValues.enableCouponCollapsible;
    
    // Update refs after capturing previous values
    prevToggleValuesRef.current = toggleValuesKey;
    prevToggleValuesObjRef.current = { ...currentToggleValues };

    // Clear any existing debounce timer
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
      debounceTimerRef.current = null;
    }

    // Abort any in-flight request so we get the latest toggle state
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    abortControllerRef.current = new AbortController();

    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;
    isFetchingRef.current = false;
    // Don't set loading state for toggle updates - keep existing HTML visible
    // Only set loading on initial render when there's no HTML yet
    if (!renderedHtml || renderedHtml.length === 0) {
      setIsLoading(true);
    }

    // CRITICAL: Use mergedAttrs directly - it already has all attributes merged with defaults
    // mergedAttrs contains all current attribute values including toggles
    // Create a deep copy to avoid mutating mergedAttrs, then ensure toggle values are current
    // This preserves ALL other attributes and settings, preventing any from being disabled
    const attrsWithToggles = JSON.parse(JSON.stringify(mergedAttrs));

    // Ensure toggle values match currentToggleValues (which reads directly from attrs)
    // This ensures we send the exact toggle state that Divi has, without affecting other attributes
    if (!attrsWithToggles.enableProductImage) attrsWithToggles.enableProductImage = { desktop: { value: currentToggleValues.enableProductImage } };
    else attrsWithToggles.enableProductImage.desktop.value = currentToggleValues.enableProductImage;

    if (!attrsWithToggles.enableQuantityBox) attrsWithToggles.enableQuantityBox = { desktop: { value: currentToggleValues.enableQuantityBox } };
    else attrsWithToggles.enableQuantityBox.desktop.value = currentToggleValues.enableQuantityBox;

    if (!attrsWithToggles.enableDeleteItem) attrsWithToggles.enableDeleteItem = { desktop: { value: currentToggleValues.enableDeleteItem } };
    else attrsWithToggles.enableDeleteItem.desktop.value = currentToggleValues.enableDeleteItem;

    if (!attrsWithToggles.miniCartEnableStrikeThroughPrice) attrsWithToggles.miniCartEnableStrikeThroughPrice = { desktop: { value: currentToggleValues.miniCartEnableStrikeThroughPrice } };
    else attrsWithToggles.miniCartEnableStrikeThroughPrice.desktop.value = currentToggleValues.miniCartEnableStrikeThroughPrice;

    if (!attrsWithToggles.miniCartEnableLowStockTrigger) attrsWithToggles.miniCartEnableLowStockTrigger = { desktop: { value: currentToggleValues.miniCartEnableLowStockTrigger } };
    else attrsWithToggles.miniCartEnableLowStockTrigger.desktop.value = currentToggleValues.miniCartEnableLowStockTrigger;

    if (!attrsWithToggles.miniCartEnableSavingPriceMessage) attrsWithToggles.miniCartEnableSavingPriceMessage = { desktop: { value: currentToggleValues.miniCartEnableSavingPriceMessage } };
    else attrsWithToggles.miniCartEnableSavingPriceMessage.desktop.value = currentToggleValues.miniCartEnableSavingPriceMessage;

    if (!attrsWithToggles.enableCoupon) attrsWithToggles.enableCoupon = { desktop: { value: currentToggleValues.enableCoupon } };
    else attrsWithToggles.enableCoupon.desktop.value = currentToggleValues.enableCoupon;

    if (!attrsWithToggles.enableCouponCollapsible) attrsWithToggles.enableCouponCollapsible = { desktop: { value: currentToggleValues.enableCouponCollapsible } };
    else attrsWithToggles.enableCouponCollapsible.desktop.value = currentToggleValues.enableCouponCollapsible;

    // Add timestamp to request payload to ensure uniqueness and prevent caching
    const requestPayload = {
      attrs: attrsWithToggles,
      id: id || '',
      post_id: postId || 0,
      _timestamp: Date.now(), // Cache-busting timestamp
      _requestId: currentRequestId, // Track this specific request
    };

    const timestamp = Date.now();
    const restRouteWithCacheBust = `/wp-json/wfacp/v1/mini-cart/render?_t=${timestamp}&_rid=${currentRequestId}`;

    const win = window as any;
    const nonce = win.wpApiSettings?.nonce
      || win.parent?.wpApiSettings?.nonce
      || win.et_fb_options?.nonce
      || '';
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-cache, no-store, must-revalidate',
    };
    if (nonce) {
      headers['X-WP-Nonce'] = nonce;
    }

    window.fetch(restRouteWithCacheBust, {
      method: 'POST',
      signal: abortControllerRef.current!.signal,
      credentials: 'same-origin',
      body: JSON.stringify(requestPayload),
      headers,
    })
      .then((r) => r.json())
      .then((response: any) => {
        if (currentRequestId !== requestIdRef.current) {
          return;
        }

        if (response && response.success === false) {
          setIsLoading(false);
          isFetchingRef.current = false;
          return;
        }

        const html = response?.html || null;

        if (currentRequestId === requestIdRef.current) {
          const newHtml = html && typeof html === 'string' && html.length > 0 ? html : '';

          // Store the new HTML in ref for selective replacement
          latestHtmlRef.current = newHtml;
          replacementDoneRef.current = false;

          // If we already have rendered HTML and it's not a structural toggle change, do selective replacement
          const savingToggleJustEnabled = isStructuralToggle && currentToggleValues.miniCartEnableSavingPriceMessage === 'on';
          if (renderedHtml && renderedHtml.length > 0 && !isInitialRenderRef.current && !isStructuralToggle && !savingToggleJustEnabled) {
            setSelectiveUpdateTrigger(prev => prev + 1);
          } else {
            setRenderedHtml(newHtml);
          }
          setIsLoading(false);
          isFetchingRef.current = false;
        }
      })
      .catch((error: any) => {
        if (error?.name === 'AbortError') return;
        if (currentRequestId === requestIdRef.current) {
          setRenderedHtml('');
          setIsLoading(false);
          isFetchingRef.current = false;
        }
      });
  }, [toggleValuesKey, currentToggleValues, mergedAttrs, id, postId]);

  // Effect to handle other attribute changes with debouncing (for less critical changes)
  useEffect(() => {
    // Skip on initial mount (handled by above effects)
    if (prevAttrsRef.current === '') {
      prevAttrsRef.current = attrsKey;
      return;
    }

    // Check if attrs actually changed
    if (prevAttrsRef.current === attrsKey) {
      return;
    }

    prevAttrsRef.current = attrsKey;

    // Clear any existing debounce timer
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    // Debounce rapid changes (50ms delay for near-instant feel while preventing excessive API calls)
    debounceTimerRef.current = setTimeout(() => {
      fetchHtml();
    }, 50);

    // Cleanup
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
        debounceTimerRef.current = null;
      }
      // Invalidate pending requests
      requestIdRef.current += 1;
      isFetchingRef.current = false;
    };
  }, [attrsKey, fetchHtml]);

  // Effect to perform selective DOM replacement
  useEffect(() => {
    // Skip on initial render or if no trigger
    if (isInitialRenderRef.current || selectiveUpdateTrigger === 0) {
      return;
    }

    // Skip if replacement already done or no new HTML available
    if (replacementDoneRef.current || !latestHtmlRef.current) {
      return;
    }

    // Use setTimeout to ensure DOM is ready
    const timeoutId = setTimeout(() => {
      if (cartContentRef.current && latestHtmlRef.current) {
        // Parse the new HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = latestHtmlRef.current.trim();

        // 1. Replace mini cart items table (contains product images, quantity boxes, delete buttons)
        const newMiniCartItemsTable = tempDiv.querySelector('.wfacp_mini_cart_items');
        if (newMiniCartItemsTable) {
          const existingTable = cartContentRef.current.querySelector('.wfacp_mini_cart_items');
          if (existingTable && existingTable.outerHTML !== newMiniCartItemsTable.outerHTML) {
            const clonedTable = newMiniCartItemsTable.cloneNode(true) as HTMLElement;
            existingTable.replaceWith(clonedTable);
          }
        }

        // 2. Replace saving price message section (if it exists in new HTML)
        const newSavingSection = tempDiv.querySelector('.wfacp_mini_cart_saving_message, .wfacp_mini_cart_saving_price_message, [class*="saving"]');
        const existingSavingSection = cartContentRef.current.querySelector('.wfacp_mini_cart_saving_message, .wfacp_mini_cart_saving_price_message, [class*="saving"]');
        if (newSavingSection && existingSavingSection) {
          // Both exist - replace if different
          if (existingSavingSection.outerHTML !== newSavingSection.outerHTML) {
            const clonedSaving = newSavingSection.cloneNode(true) as HTMLElement;
            existingSavingSection.replaceWith(clonedSaving);
          }
        } else if (newSavingSection && !existingSavingSection) {
          // New section exists but old doesn't - insert it
          const cartContainer = cartContentRef.current.querySelector('.wfacp_mini_cart_start_h');
          if (cartContainer) {
            const clonedSaving = newSavingSection.cloneNode(true) as HTMLElement;
            // Insert before coupon section or at end
            const couponSection = cartContainer.querySelector('.wfacp_woocommerce_form_coupon');
            if (couponSection) {
              couponSection.parentNode?.insertBefore(clonedSaving, couponSection);
            } else {
              cartContainer.appendChild(clonedSaving);
            }
          }
        } else if (!newSavingSection && existingSavingSection) {
          // Old section exists but new doesn't - remove it
          existingSavingSection.remove();
        }

        // 3. Replace coupon section (if it exists in new HTML)
        const newCouponSection = tempDiv.querySelector('.wfacp_woocommerce_form_coupon, .wfacp-coupon-section');
        const existingCouponSection = cartContentRef.current.querySelector('.wfacp_woocommerce_form_coupon, .wfacp-coupon-section');
        if (newCouponSection && existingCouponSection) {
          // Both exist - replace if different
          if (existingCouponSection.outerHTML !== newCouponSection.outerHTML) {
            const clonedCoupon = newCouponSection.cloneNode(true) as HTMLElement;
            existingCouponSection.replaceWith(clonedCoupon);
          }
        } else if (newCouponSection && !existingCouponSection) {
          // New section exists but old doesn't - insert it
          const cartContainer = cartContentRef.current.querySelector('.wfacp_mini_cart_start_h');
          if (cartContainer) {
            const clonedCoupon = newCouponSection.cloneNode(true) as HTMLElement;
            cartContainer.appendChild(clonedCoupon);
          }
        } else if (!newCouponSection && existingCouponSection) {
          // Old section exists but new doesn't - remove it
          existingCouponSection.remove();
        }

        // Mark replacement as done
        replacementDoneRef.current = true;
      }
    }, 10);

    return () => clearTimeout(timeoutId);
  }, [selectiveUpdateTrigger]); // Run when selectiveUpdateTrigger changes

  return (
    <ModuleContainer
      attrs={mergedAttrs}
      elements={elements}
      id={id}
      name={name}
      stylesComponent={ModuleStyles}
      classnamesFunction={moduleClassnames}
      scriptDataComponent={ModuleScriptData}
    >
      {/* Render cart content via AJAX */}
      {isLoading && !renderedHtml ? (
        <div style={{ padding: '20px', textAlign: 'center' }}>Loading Mini Cart...</div>
      ) : renderedHtml ? (
        <div
          key={`cart-${renderedHtml.length}`}
          ref={cartContentRef}
          dangerouslySetInnerHTML={{ __html: renderedHtml }}
          className="wfacp-mini-cart-content"
        />
      ) : (
        <div style={{ padding: '20px', textAlign: 'center', color: '#999' }}>
          Mini Cart (No content available)
        </div>
      )}
      {/* Effect to update heading when it changes - updates text content directly */}
      {renderedHtml && (
        <>
          <HeadingUpdater
            headingValue={String(headingValue || '')}
            cartContentRef={cartContentRef}
            renderedHtml={renderedHtml}
          />
          {/* Update saving price message text directly in DOM */}
          <SavingMessageUpdater
            messageValue={savingPriceMessageValue}
            cartContentRef={cartContentRef}
            renderedHtml={renderedHtml}
            isToggleEnabled={mergedAttrs?.miniCartEnableSavingPriceMessage?.desktop?.value === 'on'}
          />
          {/* Update low stock message text directly in DOM */}
          <LowStockMessageUpdater
            messageValue={lowStockMessageValue}
            cartContentRef={cartContentRef}
            renderedHtml={renderedHtml}
            isToggleEnabled={mergedAttrs?.miniCartEnableLowStockTrigger?.desktop?.value === 'on'}
          />
          {/* Update coupon button text directly in DOM */}
          <CouponButtonTextUpdater
            buttonTextValue={couponButtonTextValue}
            cartContentRef={cartContentRef}
            renderedHtml={renderedHtml}
          />
        </>
      )}
    </ModuleContainer>
  );
};
