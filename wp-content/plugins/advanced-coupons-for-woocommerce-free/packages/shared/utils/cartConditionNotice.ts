/**
 * Class of the wrapper that the server puts around a Cart Conditions notice with markup.
 * Must match the wrapper class in Cart_Conditions::_get_cart_condition_notice() (Models/Cart_Conditions.php).
 *
 * @since 4.7.7
 */
const NOTICE_CLASS = 'acfw-cart-condition-notice';

/**
 * Opening tag of the wrapper, as it shows in the coupon error text when the response is entity-encoded.
 *
 * @since 4.7.7
 */
const NOTICE_OPEN_TAG = `<span class="${NOTICE_CLASS}">`;

/**
 * Flag on `window` that stops a second bundle from adding a second handler.
 * When `acfw_force_load_frontend_js` is on, the cart and checkout bundles both load on the same page.
 *
 * @since 4.7.7
 */
const INIT_FLAG = 'acfwCartConditionNoticeInit';

type SanitizeHtml = (html: string) => string;

/**
 * Replace a part of a text node with the sanitized notice markup.
 *
 * @since 4.7.7
 *
 * @param {Text} textNode The text node that holds the notice as plain text.
 * @param {number} start Start of the notice text in the node.
 * @param {number} length Length of the notice text in the node.
 * @param {string} html Notice markup from the server.
 * @param {SanitizeHtml} sanitizeHtml The DOMPurify helper.
 */
function replaceWithMarkup(textNode: Text, start: number, length: number, html: string, sanitizeHtml: SanitizeHtml) {
  const noticeNode = textNode.splitText(start);
  noticeNode.splitText(length);

  const markup = document.createElement('span');
  markup.className = NOTICE_CLASS;
  markup.innerHTML = sanitizeHtml(html.trim());

  noticeNode.replaceWith(markup);
}

/**
 * Get the text nodes directly inside an element.
 *
 * @since 4.7.7
 *
 * @param {HTMLElement} element The element.
 * @returns {Text[]} The text nodes.
 */
function getTextNodes(element: HTMLElement): Text[] {
  return Array.from(element.childNodes).filter((node): node is Text => node.nodeType === Node.TEXT_NODE);
}

/**
 * Get the end of the wrapper markup that starts at `start`, counting nested spans.
 *
 * @since 4.7.7
 *
 * @param {string} text Text that holds the wrapper markup as plain text.
 * @param {number} start Index of the wrapper opening tag.
 * @returns {number} Index just after the wrapper closing tag, or -1 when the markup is not complete.
 */
function findWrapperEnd(text: string, start: number): number {
  const tags = /<span\b[^>]*>|<\/span>/g;
  let depth = 0;

  tags.lastIndex = start;

  for (let match = tags.exec(text); match; match = tags.exec(text)) {
    depth += match[0] === '</span>' ? -1 : 1;

    if (depth === 0) {
      return match.index + match[0].length;
    }
  }

  return -1;
}

/**
 * Put the Cart Conditions notice markup back into the classic coupon error.
 *
 * WooCommerce cart.js and checkout.js show the `apply_coupon` AJAX error as plain text in `#coupon-error-notice`.
 * This removes the Non-Qualifying button link. Find the notice in that text, and replace it with the sanitized
 * notice markup.
 *
 * The text content of the error does not change, so the `role="alert"` region has the same text to announce.
 *
 * @since 4.7.7
 *
 * @param {string} response The `apply_coupon` AJAX response HTML.
 * @param {SanitizeHtml} sanitizeHtml The DOMPurify helper.
 */
function restoreCartConditionNotice(response: string, sanitizeHtml: SanitizeHtml): void {
  const errorElement = document.getElementById('coupon-error-notice');

  // WooCommerce did not show a coupon error.
  if (!errorElement) {
    return;
  }

  // Parse into an inert document, so that no script or event handler in the response runs.
  const notices = new DOMParser()
    .parseFromString(response, 'text/html')
    .querySelectorAll<HTMLElement>(`.${NOTICE_CLASS}`);

  // A raw response: WooCommerce shows the text of the notice. Find that text.
  notices.forEach((notice) => {
    const noticeText = (notice.textContent ?? '').trim();
    const textNode = getTextNodes(errorElement).find((node) => noticeText && node.data.includes(noticeText));

    // A text node that was already replaced has no match.
    if (textNode) {
      replaceWithMarkup(textNode, textNode.data.indexOf(noticeText), noticeText.length, notice.innerHTML, sanitizeHtml);
    }
  });

  // An entity-encoded response (the `acfw_encode_cart_condition_notice` filter is on): WooCommerce shows the
  // notice markup as text. Find that markup.
  if (!notices.length) {
    getTextNodes(errorElement).forEach((textNode) => {
      const start = textNode.data.indexOf(NOTICE_OPEN_TAG);
      const end = start === -1 ? -1 : findWrapperEnd(textNode.data, start);

      if (end !== -1) {
        const html = textNode.data.slice(start + NOTICE_OPEN_TAG.length, end - '</span>'.length);
        replaceWithMarkup(textNode, start, end - start, html, sanitizeHtml);
      }
    });
  }

  // Keep the space between the coupon field and the button, as before WooCommerce 9.4.
  if (errorElement.querySelector(`.${NOTICE_CLASS}`)) {
    errorElement.style.marginTop = '10px';
  }
}

/**
 * Restore the Cart Conditions notice markup after each classic `apply_coupon` AJAX request.
 *
 * @since 4.7.7
 */
export default function initCartConditionNotice(): void {
  const flags = window as unknown as Record<string, boolean>;

  if (flags[INIT_FLAG]) {
    return;
  }

  flags[INIT_FLAG] = true;

  jQuery(document).ajaxSuccess((_event, xhr, settings) => {
    const response = xhr.responseText ?? '';

    // Only the classic `apply_coupon` request, and only when the response has a Cart Conditions notice with markup.
    if (!settings.url || settings.url.indexOf('wc-ajax=apply_coupon') === -1 || response.indexOf(NOTICE_CLASS) === -1) {
      return;
    }

    // Load DOMPurify only when a notice needs it.
    import('../helpers/sanitize').then(({ sanitizeHtml }) => restoreCartConditionNotice(response, sanitizeHtml));
  });
}
