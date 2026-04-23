import React, { ReactElement } from 'react';
import { StyleContainer, StylesProps } from '@divi/module';
import type { OrderDetailsAttrs } from './types';

const STYLE_ATTRS = [
  'module',
  'wfty_order_details_heading_typography',
  'wfty_order_details_product_typography',
  'wfty_order_details_subtotal_typography',
  'wfty_order_details_total_typography',
  'wfty_order_details_variation_typography',
  'wfty_order_details_subscription_typography',
  'wfty_order_details_subscription_text_color',
  'wfty_order_details_subs_button_background_color',
  'wfty_order_details_subs_button_background_hover_color',
  'wfty_order_details_download_typography',
  'wfty_order_details_download_text_color',
  'wfty_order_details_download_button_background_color',
  'wfty_order_details_download_button_background_hover_color',
] as const;

const BREAKPOINTS = ['desktop', 'tablet', 'phone'] as const;

/**
 * Extract hex color from a decorator attribute.
 * Checks background decorator paths first (user-set via color picker),
 * then falls back to font decorator path (legacy saved data).
 * Iterates through all breakpoints to find the first available value.
 */
const getColorHex = (attr: unknown): string | null => {
  if (!attr || typeof attr !== 'object') return null;
  const dec = (attr as Record<string, unknown>).decoration as Record<string, unknown> | undefined;
  if (!dec) return null;

  for (const bp of BREAKPOINTS) {
    // Background decorator — user-set value (runtime).
    const bgRuntime = (dec as any)?.background?.[bp]?.value?.color;
    if (bgRuntime) return bgRuntime;

    // Font decorator — legacy saved data.
    const fontColor = (dec as any)?.font?.[bp]?.value?.font?.color;
    if (fontColor) return fontColor;

    // Background decorator — default format from module.json.
    const bgDefault = (dec as any)?.background?.color?.[bp]?.value?.hex;
    if (bgDefault) return bgDefault;
  }

  return null;
};

export const ModuleStyles = ({
  attrs,
  elements,
  orderClass,
  mode,
  state,
  noStyleTag,
}: StylesProps<OrderDetailsAttrs>): ReactElement => {
  const oc = orderClass || '';
  return (
    <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
      {STYLE_ATTRS.map((attrName) => elements.style({ attrName }))}

      {/* Divider Color — color picker mapped to border-color via custom CSS */}
      {(() => {
        const colorValue = getColorHex(attrs?.wfty_order_details_divider_color);
        if (colorValue && oc) {
          const selectors = [
            `${oc} .wfty_wrap .wfty_order_details table tfoot tr:last-child td`,
            `${oc} .wfty_wrap .wfty_order_details table tfoot tr:last-child th`,
            `${oc} .wfty_wrap .wfty_order_details table`,
          ].join(', ');
          return <style dangerouslySetInnerHTML={{ __html: `${selectors} { border-color: ${colorValue} !important; }` }} />;
        }
        return null;
      })()}

      {/* Subscription button label color */}
      {(() => {
        const hex = getColorHex(attrs?.wfty_order_details_subs_button_text_color);
        if (hex && oc) {
          return <style dangerouslySetInnerHTML={{ __html: `${oc} .wffn_order_details_table .wfty_wrap .wfty_subscription table tr td.subscription-actions a { color: ${hex} !important; }` }} />;
        }
        return null;
      })()}

      {/* Subscription button label hover color */}
      {(() => {
        const hex = getColorHex(attrs?.wfty_order_details_subs_button_text_hover_color);
        if (hex && oc) {
          return <style dangerouslySetInnerHTML={{ __html: `${oc} .wffn_order_details_table .wfty_wrap .wfty_subscription table tr td.subscription-actions a:hover { color: ${hex} !important; }` }} />;
        }
        return null;
      })()}

      {/* Download button label color */}
      {(() => {
        const hex = getColorHex(attrs?.wfty_order_details_download_button_text_color);
        if (hex && oc) {
          return <style dangerouslySetInnerHTML={{ __html: `${oc} .wffn_order_details_table .wfty_wrap .wfty_order_download table tr td.download-file a { color: ${hex} !important; }` }} />;
        }
        return null;
      })()}

      {/* Download button label hover color */}
      {(() => {
        const hex = getColorHex(attrs?.wfty_order_details_download_button_text_hover_color);
        if (hex && oc) {
          return <style dangerouslySetInnerHTML={{ __html: `${oc} .wffn_order_details_table .wfty_wrap .wfty_order_download table tr td.download-file a:hover { color: ${hex} !important; }` }} />;
        }
        return null;
      })()}
    </StyleContainer>
  );
};
