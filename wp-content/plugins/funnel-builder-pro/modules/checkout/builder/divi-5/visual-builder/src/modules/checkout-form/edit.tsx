// External Dependencies.
import React, { ReactElement, useMemo, useEffect, useState, useRef, useCallback } from 'react';
import { merge } from 'lodash';

// Divi Dependencies.
import { ModuleContainer } from '@divi/module';
import { useFetch } from '@divi/rest';

// Local Dependencies.
import { CheckoutFormEditProps } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import defaultRenderAttributes from './module-default-render-attributes.json';

/**
 * Checkout Form Module edit component of visual builder.
 *
 * Uses debounced AJAX fetch (same pattern as Divi 4) — any attribute change
 * triggers a 600ms debounced server re-render. No client-side DOM updaters.
 *
 * @since 1.0.0
 *
 * @param {CheckoutFormEditProps} props React component props.
 *
 * @returns {ReactElement}
 */
export const CheckoutFormEdit = (props: CheckoutFormEditProps): ReactElement => {
  const {
    attrs,
    elements,
    id,
    name,
  } = props;

  // Merge defaults with attrs to ensure default values are always present.
  // Divi 5 doesn't save attributes that match defaults, so we need to merge them here.
  const mergedAttrs = useMemo(() => {
    if (!attrs || Object.keys(attrs).length === 0) {
      return defaultRenderAttributes as typeof attrs;
    }
    return merge({}, defaultRenderAttributes, attrs) as typeof attrs;
  }, [attrs]);

  // State for rendered HTML.
  const [renderedHtml, setRenderedHtml] = useState<string>('');
  const [isLoading, setIsLoading] = useState<boolean>(true);

  // Fetch REST API for rendering.
  const { fetch } = useFetch();

  // Track request ID to ignore stale responses.
  const requestIdRef = useRef<number>(0);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Memoize post ID to avoid recalculating on every render.
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

    // Try multiple methods to get post ID.
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

  /**
   * Fetch checkout form HTML from server.
   * Handles stale response detection via requestIdRef.
   */
  const runAjax = useCallback(() => {
    if (!postId) {
      setIsLoading(false);
      return;
    }

    // Increment request ID to track this request.
    requestIdRef.current += 1;
    const currentRequestId = requestIdRef.current;

    setIsLoading(true);

    const timestamp = Date.now();
    fetch({
      method: 'POST',
      restRoute: `/wfacp/v1/checkout-form/render?_t=${timestamp}&_rid=${currentRequestId}`,
      body: JSON.stringify({
        attrs: mergedAttrs,
        id: id || '',
        post_id: postId || 0,
      }),
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-cache, no-store, must-revalidate',
      },
    })
      .then((response: any) => {
        // Ignore stale responses.
        if (currentRequestId !== requestIdRef.current) {
          return;
        }

        // Handle Immutable.js response (Divi 5 may return Immutable objects).
        let html: string | null = null;
        if (response && typeof response.toJS === 'function') {
          try { html = response.toJS()?.html || null; } catch (e) {}
        }
        if (!html && response && typeof response.toObject === 'function') {
          try { html = response.toObject()?.html || null; } catch (e) {}
        }
        if (!html && response && typeof response.get === 'function') {
          try { html = response.get('html') || null; } catch (e) {}
        }
        if (!html && response) {
          html = response.html || null;
        }
        if (!html && typeof response === 'string') {
          try { html = JSON.parse(response)?.html || null; } catch (e) {}
        }

        if (currentRequestId === requestIdRef.current) {
          setRenderedHtml(html && typeof html === 'string' && html.length > 0 ? html : '');
          setIsLoading(false);
        }
      })
      .catch(() => {
        if (currentRequestId === requestIdRef.current) {
          setRenderedHtml('');
          setIsLoading(false);
        }
      });
  }, [mergedAttrs, id, postId, fetch]);

  // Stringify mergedAttrs to detect any attribute change (same as Divi 4's JSON.stringify(this.props) comparison).
  const attrsKey = JSON.stringify(mergedAttrs);
  const prevAttrsKeyRef = useRef<string>('');

  useEffect(() => {
    // First render — fetch immediately without debounce (same as Divi 4's componentDidMount).
    if (prevAttrsKeyRef.current === '') {
      prevAttrsKeyRef.current = attrsKey;
      runAjax();
      return;
    }

    // No change — skip.
    if (prevAttrsKeyRef.current === attrsKey) {
      return;
    }

    prevAttrsKeyRef.current = attrsKey;

    // Debounce subsequent updates — 600ms (same as Divi 4).
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    debounceTimerRef.current = setTimeout(() => {
      runAjax();
    }, 600);

    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [attrsKey, runAjax]);

  // Add wfacp_editor_active class to body to prevent shimmer/loading overlays
  // from firing on interactive elements (e.g. Product Switcher) inside the VB preview.
  useEffect(() => {
    document.body.classList.add('wfacp_editor_active');
    return () => {
      document.body.classList.remove('wfacp_editor_active');
    };
  }, []);

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
      {isLoading && !renderedHtml ? (
        <div style={{ padding: '20px', textAlign: 'center' }}>Loading Checkout Form...</div>
      ) : renderedHtml ? (
        <div
          className="wfacp-checkout-form-container"
          data-module-id={id}
          dangerouslySetInnerHTML={{ __html: renderedHtml }}
        />
      ) : (
        <div style={{ padding: '20px', textAlign: 'center', color: '#999' }}>
          Checkout Form (No content available)
        </div>
      )}
    </ModuleContainer>
  );
};
