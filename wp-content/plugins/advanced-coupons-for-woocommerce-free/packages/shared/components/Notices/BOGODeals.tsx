// Import library.
import { useSelect, WC_STORE_CART } from '../../library/StoreAPI';
import { decodeHTML } from '../../library/html';
import { sanitizeHtml } from '../../utils';

// Global Variables.
declare var acfwfObj: any;
declare var wp: any;
declare var wc: any;

const { useEffect, useState, createPortal, RawHTML } = wp.element;

/**
 * Selector for the cart/checkout block root the notices belong to.
 *
 * @since 4.7.7
 */
const BLOCK_ROOT_SELECTOR = '[data-block-name="woocommerce/cart"], [data-block-name="woocommerce/checkout"]';

/**
 * Selector for WooCommerce's own store-notices region.
 *
 * @since 4.7.7
 */
const NOTICES_REGION_SELECTOR = '.wc-block-components-notices';

/**
 * Class name of the node the notices are portalled into.
 *
 * @since 4.7.7
 */
const HOST_CLASS = 'acfw-bogo-block-notices';

/**
 * Tags and attributes the notice markup is allowed to keep.
 *
 * The notice is built server-side in BOGO\Frontend::_add_notice_for_eligible_deals() and
 * tagged in BOGO\Frontend::get_eligible_deal_notices_message_wc_blocks() as
 * `<span class="acfw-bogo-notice-text" data-coupon="CODE" data-notice-type="TYPE">MESSAGE
 * <a href="URL" class="button">TEXT</a></span>`, and the message itself is an
 * admin-authored coupon setting that may contain inline markup. Keep the wrapper, its
 * coupon key, its notice type and the button class — which is the whole point of this
 * component — and nothing that can execute.
 *
 * Naming the two `data-` attributes in ALLOWED_ATTR is enough to keep them;
 * `ALLOW_DATA_ATTR` is left off so that every other `data-*` is still dropped.
 * ADD_URI_SAFE_ATTR exempts `data-coupon` from the URI check, which a coupon code shaped
 * like `word:something` would otherwise trip.
 *
 * @since 4.7.7
 */
const ALLOWED_NOTICE_HTML = {
  ALLOWED_TAGS: ['span', 'a', 'b', 'strong', 'em', 'i', 'br', 'abbr', 'small'],
  ALLOWED_ATTR: ['class', 'href', 'target', 'rel', 'title', 'data-coupon', 'data-notice-type'],
  ADD_URI_SAFE_ATTR: ['data-coupon'],
};

/**
 * The coupon's Notice Type setting, mapped onto the statuses StoreNotice renders.
 *
 * The stored values are WooCommerce's own classic notice types, so `notice` is the one
 * that does not name itself: it is the setting labelled "Info". `info` is accepted
 * alongside it because the plugin's own notice-type allow-list treats the two as
 * interchangeable (see BOGO\Frontend::display_bogo_notices_on_classic_pages()), and
 * `warning` because the stored value is filterable through
 * `acfw_sanitize_allowed_notice_types` and StoreNotice renders that status too.
 *
 * @since 4.7.7
 */
const NOTICE_STATUS: { [type: string]: string } = {
  notice: 'info',
  info: 'info',
  success: 'success',
  error: 'error',
  warning: 'warning',
};

/**
 * Resolve a notice's stored type to the status StoreNotice should render it with.
 *
 * The lookup is own-property only. A plain index would resolve an inherited member such
 * as `constructor` to a truthy value, which would then be passed straight into the
 * banner's class name, so every value that is not a mapped type falls back to `info`.
 *
 * @since 4.7.7
 * @param {string|null} type The notice type read off the wrapper, if it carries one.
 * @return {string} The StoreNotice status to render.
 */
const noticeStatus = (type: string | null | undefined) => {
  return type && Object.prototype.hasOwnProperty.call(NOTICE_STATUS, type) ? NOTICE_STATUS[type] : 'info';
};

/**
 * Read the coupon code and the plain text out of a sanitized notice, and force a
 * `rel` on any anchor that opens a new tab.
 *
 * `ALLOWED_ATTR` keeps `target` so an admin-authored message can still open a link in a
 * new tab, but a `target="_blank"` anchor with no `rel` hands the opened page a
 * `window.opener` reference back to the store. Current browsers imply `noopener` for
 * `_blank`, so this is belt-and-braces rather than a live hole — but the markup is
 * admin-authored and the pairing costs nothing, so it is forced here instead of being
 * left to the browser. It is done on this holder rather than through a DOMPurify
 * `afterSanitizeAttributes` hook because hooks are global to DOMPurify and would apply to
 * every other `sanitizeHtml()` caller in the plugin.
 *
 * The safety tokens are merged into whatever `rel` the admin already wrote rather than
 * replacing it, so an authored `rel="nofollow"` survives alongside them.
 *
 * @since 4.7.7
 * @param {string} html Sanitized notice markup.
 * @param {number} index Position of the notice, used when a notice carries no coupon code.
 * @return {Object} The notice's markup, its coupon code, its banner status and its
 *         text-only form.
 */
const parseNotice = (html: string, index: number) => {
  const holder = document.createElement('div');
  holder.innerHTML = html;

  Array.from(holder.querySelectorAll('a[target="_blank"]')).forEach((anchor: Element) => {
    const rel = new Set((anchor.getAttribute('rel') || '').split(/\s+/).filter(Boolean));

    rel.add('noopener');
    rel.add('noreferrer');

    anchor.setAttribute('rel', Array.from(rel).join(' '));
  });

  const wrapper = holder.querySelector('.acfw-bogo-notice-text');

  return {
    html: holder.innerHTML,
    // The coupon code is the only value that is both unique per notice and stable while
    // the cart changes, so it keys the notice. The index is a fallback for markup that
    // predates the data attribute.
    key: wrapper?.getAttribute('data-coupon') || `notice-${index}`,
    // The coupon's Notice Type setting, which reaches the block pages on the wrapper
    // because the payload carries the notice as markup only (see issue #1630). Anything
    // unmapped falls back to the info banner these pages have always rendered, which
    // covers both a notice built by a server that predates the attribute and a type added
    // through the acfw_sanitize_allowed_notice_types filter that StoreNotice has no
    // status for. The lookup is own-property only: a plain index would resolve an
    // inherited member such as "constructor" to a truthy value and put it in the banner's
    // class, and this render is what the whole notices plugin returns.
    status: noticeStatus(wrapper?.getAttribute('data-notice-type')),
    text: (holder.textContent || '').trim(),
  };
};

/**
 * Mount a host node for the notices, inside the block root and above its notices region.
 *
 * The notice used to be dispatched into the `core/notices` store, but WooCommerce 11
 * sanitizes every store notice against a fixed allow-list that drops `<span>` and every
 * `class` attribute, so the `acfw-bogo-notice-text` wrapper and the View Deals button
 * styling never reached the DOM (see issue #1609). Rendering our own markup is the only
 * supported way to keep it.
 *
 * A plugin registered in the `woocommerce-checkout` scope renders as the *last* child of
 * the block, which would move the notice below the cart, so the notices are portalled
 * back up to where store notices actually appear.
 *
 * Two things make that placement fiddly, and both are handled here:
 *
 * - `.wc-block-components-notices` is not unique. The checkout block renders one per step,
 *   the mini-cart drawer renders one, and the Pro plugin renders another for its own
 *   notice context. A document-wide lookup can therefore anchor to the mini-cart in the
 *   header, so the region is only ever resolved *within* the block root.
 * - The region is created by WooCommerce's React tree after hydration, is absent from the
 *   server-rendered HTML, and is unmounted again while an express payment method is
 *   active. The observer is therefore kept running for the life of the component and
 *   re-places the host whenever it is lost, instead of giving up after the first mount.
 *
 * @since 4.7.7
 * @param {boolean} enabled Whether there is anything to render; no host is mounted when false.
 * @return {HTMLElement|null} The host node, or null until it is placed.
 */
const useNoticesHost = (enabled: boolean) => {
  const [host, setHost] = useState(null);

  useEffect(() => {
    if (!enabled) {
      setHost(null);

      return;
    }

    const node = document.createElement('div');
    node.className = HOST_CLASS;

    /**
     * Place the host if it is not already sitting in the right spot.
     *
     * @return {void}
     */
    const place = () => {
      // Cheap guard first: this runs on every DOM mutation inside the block, which on the
      // checkout page is a lot, so the already-placed case must not touch the document.
      // The parent has to be checked too, not just the sibling: the notices region is not
      // unique, so a host that ended up beside a nested one would otherwise declare itself
      // placed for good and the notice would render mid-form with no way back.
      if (
        node.parentElement?.matches(BLOCK_ROOT_SELECTOR) &&
        node.nextElementSibling?.matches(NOTICES_REGION_SELECTOR)
      ) {
        return;
      }

      const root = document.querySelector(BLOCK_ROOT_SELECTOR);

      if (!root) {
        return;
      }

      // Only this block's own top-level region, never a checkout step's or the mini-cart's.
      const region = Array.from(root.children).find((child: Element) =>
        child.matches(NOTICES_REGION_SELECTOR)
      );

      if (!region) {
        return;
      }

      root.insertBefore(node, region);
      setHost(node);
    };

    place();

    // Watch the block root only. Falling back to document.body would put a subtree
    // observer on the whole page for its lifetime, and there is nothing to place if the
    // block root is absent.
    const target = document.querySelector(BLOCK_ROOT_SELECTOR);

    if (!target) {
      return () => node.remove();
    }

    const observer = new MutationObserver(place);
    observer.observe(target, { childList: true, subtree: true });

    return () => {
      observer.disconnect();
      node.remove();
    };
  }, [enabled]);

  return host;
};

/**
 * BOGO Deals Notice component.
 *
 * Renders the "eligible to redeem deals" notices for the applied BOGO coupons, using
 * WooCommerce's own notice banner with our markup as its children so the markup is not
 * sanitized away.
 *
 * @since 4.6.0
 * @since 4.7.7 Render the notices directly so the wrapper and button styling survive.
 * @return {JSX.Element|null} The notices, or null when none are eligible.
 */
const BOGODeals = () => {
  // Read defensively. This render is what the whole notices plugin returns now, so a
  // throw here would take the cart-conditions and store-credit notices down with it.
  const StoreNotice = wc?.blocksCheckout?.StoreNotice;
  const [dismissed, setDismissed] = useState([]);

  const cartData = useSelect((select: any) => {
    return select(WC_STORE_CART).getCartData();
  });

  // Read these reactively: the effect below keys off them, so the component has to
  // re-render when they flip rather than sampling them once during a render pass.
  const isApplyingCoupon = useSelect((select: any) => {
    return select(WC_STORE_CART).isApplyingCoupon();
  });

  const isRemovingCoupon = useSelect((select: any) => {
    return select(WC_STORE_CART).isRemovingCoupon();
  });

  // Wait until the data is loaded, this is due to Store API is asynchronous. This render
  // is what the whole notices plugin returns now, so it must not throw on a cart payload
  // that has not arrived yet.
  const payload: string[] = cartData?.extensions?.acfwf_block?.bogo_deals ?? [];
  const notices = payload.map((notice: string, index: number) => {
    return parseNotice(sanitizeHtml(decodeHTML(notice), ALLOWED_NOTICE_HTML), index);
  });
  const noticeKeys = notices.map((notice: any) => notice.key).join('|');

  // Only mount a host once there is something to portal into it, so a cart with no BOGO
  // coupon in play does not get an empty div injected into the block.
  const host = useNoticesHost(Boolean(StoreNotice) && notices.length > 0);

  // We must trigger dummyUpdateCart after coupon being applied / removed, so we get the
  // latest bogo deals data from the Store API. This has to stay out of the render pass:
  // firing a cart request while rendering would feed straight back into a re-render.
  useEffect(() => {
    if (isApplyingCoupon || isRemovingCoupon) {
      acfwfObj.wc.dummyUpdateCart();
    }
  }, [isApplyingCoupon, isRemovingCoupon]);

  // Forget dismissals for coupons that are no longer eligible, so a notice shows again if
  // the cart changes and the deal becomes eligible once more. Without this a dismissal
  // would last for the life of the component.
  useEffect(() => {
    setDismissed((previous: string[]) => {
      const keys = noticeKeys ? noticeKeys.split('|') : [];
      const next = previous.filter((key: string) => keys.includes(key));

      return next.length === previous.length ? previous : next;
    });
  }, [noticeKeys]);

  if (!StoreNotice || !host || !notices.length) {
    return null;
  }

  return createPortal(
    notices
      .filter((notice: any) => !dismissed.includes(notice.key))
      .map((notice: any) => (
        <StoreNotice
          key={notice.key}
          status={notice.status}
          spokenMessage={notice.text}
          onRemove={() => setDismissed((previous: string[]) => [...previous, notice.key])}
        >
          <RawHTML>{notice.html}</RawHTML>
        </StoreNotice>
      )),
    host
  );
};

export default BOGODeals;
