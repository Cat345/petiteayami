import React, { ReactElement, useMemo } from 'react';
import { merge } from 'lodash';
import { ModuleContainer } from '@divi/module';
import type { OrderDetailsEditProps } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import defaultRenderAttributes from './module-default-render-attributes.json';

declare global {
  interface Window {
    wftyDivi5VB?: {
      placeholderImgSrc?: string;
    };
  }
}

/**
 * VB preview: same structure and classes as frontend (wfty_order_details)
 * so layout and any custom CSS match. Placeholder content for builder.
 */
export const OrderDetailsEdit = (props: OrderDetailsEditProps): ReactElement => {
  const { attrs, state, elements, id, name } = props;
  const mergedAttrs = useMemo(() => {
    if (!attrs || Object.keys(attrs).length === 0) {
      return defaultRenderAttributes as typeof attrs;
    }
    return merge({}, defaultRenderAttributes, attrs) as typeof attrs;
  }, [attrs]);
  const effectiveAttrs = useMemo(
    () => (state ? (merge({}, mergedAttrs, state) as OrderDetailsEditProps['attrs']) : mergedAttrs),
    [mergedAttrs, state]
  );
  const orderHeading = effectiveAttrs?.order_details_heading?.innerContent?.desktop?.value || 'Order Details';
  const subscriptionHeading = effectiveAttrs?.order_subscription_heading?.innerContent?.desktop?.value || 'Subscription';
  const downloadHeading = effectiveAttrs?.order_download_heading?.innerContent?.desktop?.value || 'Downloads';
  const downloadBtnText = effectiveAttrs?.order_downloads_btn_text?.innerContent?.desktop?.value || 'Download';
  const isTruthy = (v: unknown): boolean => v === true || v === 'on';
  const showImages = effectiveAttrs?.order_details_img?.innerContent?.desktop?.value !== false && effectiveAttrs?.order_details_img?.innerContent?.desktop?.value !== 'off';
  const showImagesClass = showImages ? 'wfty_show_images' : 'wfty_hide_images';
  const showDownloadsFile = isTruthy(effectiveAttrs?.order_downloads_file?.innerContent?.desktop?.value);
  const showFileExpiry = isTruthy(effectiveAttrs?.order_downloads_file_expiry?.innerContent?.desktop?.value);
  const showDownloadPreview = isTruthy(effectiveAttrs?.order_download_preview?.innerContent?.desktop?.value);
  const showSubscriptionPreview = isTruthy(effectiveAttrs?.order_subscription_preview?.innerContent?.desktop?.value);
  return (
    <ModuleContainer
      name={name}
      attrs={effectiveAttrs}
      elements={elements}
      id={id}
      classnamesFunction={moduleClassnames}
      stylesComponent={ModuleStyles}
      scriptDataComponent={ModuleScriptData}
    >
      {/* Same nesting as frontend: #wfty_order_details > .wffn_order_details_table > .wfty_wrap > .wfty_box sections; heading = div; table/price markup match PHP view */}
      <div id="wfty_order_details">
        <div className="wffn_order_details_table">
          <div className="wfty_wrap">
            <div className="wfty_box wfty_order_details">
              <div className="wfty-order-details-heading wfty_title">{orderHeading}</div>
              <div className={`wfty_pro_list_cont ${showImagesClass}`}>
                <div className="wfty_pro_list wfty_clearfix">
                  <div className="wfty_leftDiv wfty_clearfix">
                    {showImages && (
                      <div className="wfty_p_img">
                        <a href="javascript:void(0);">
                          <img height="100" width="100" className="attachment-shop_thumbnail size-shop_thumbnail" src={window?.wftyDivi5VB?.placeholderImgSrc ?? ''} alt="" />
                        </a>
                      </div>
                    )}
                    <div className="wfty_p_name">
                      <a href="javascript:void(0);">
                        <span className="wfty_t">Test Product</span>
                      </a>
                      <span className="wfty_quantity_value_box">
                        <span className="multiply">x</span>
                        {showImages ? '1' : <span className="qty">1</span>}
                      </span>
                      <div className="wfty_info">
                        <ul className="wc-item-meta">
                          <li><strong className="wc-item-meta-label">Color: </strong><p>Blue</p></li>
                          <li><strong className="wc-item-meta-label">Size: </strong><p>Large</p></li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div className="wfty_rightDiv">
                    <span className="woocommerce-Price-amount amount"><span className="woocommerce-Price-currencySymbol">$</span>12.00</span>
                  </div>
                  <div className="wfty-clearfix" />
                </div>
                <table>
                  <tfoot>
                    <tr>
                      <th scope="row">Subtotal</th>
                      <td><span className="woocommerce-Price-amount amount"><span className="woocommerce-Price-currencySymbol">$</span>12.00</span></td>
                    </tr>
                    <tr>
                      <th scope="row">Shipping</th>
                      <td><span className="woocommerce-Price-amount amount"><span className="woocommerce-Price-currencySymbol">$</span>3.00</span></td>
                    </tr>
                    <tr>
                      <th scope="row">Payment method</th>
                      <td>Credit card</td>
                    </tr>
                    <tr>
                      <th scope="row">Total</th>
                      <td><span className="woocommerce-Price-amount amount"><span className="woocommerce-Price-currencySymbol">$</span>15.00</span></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
            {showDownloadPreview && (
            <div className="wfty_box wfty_order_download">
              <div className="wfty_title">{downloadHeading}</div>
              <table className="shop_table shop_table_responsive wfty_order_downloads" style={{ width: '100%' }}>
                <thead>
                  <tr>
                    <th className="download-product"><span className="nobr">File</span></th>
                    {showDownloadsFile && <th className="download-remaining"><span className="nobr">Downloads</span></th>}
                    {showFileExpiry && <th className="download-expires"><span className="nobr">Expires</span></th>}
                    <th className="download-file"><span className="nobr"></span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td className="download-product" data-title="File">Your_file_name.pdf</td>
                    {showDownloadsFile && <td className="download-remaining" data-title="Downloads">3</td>}
                    {showFileExpiry && <td className="download-expires" data-title="Expires">2026-04-12</td>}
                    <td className="download-file"><a href="javascript:void(0);">{downloadBtnText}</a></td>
                  </tr>
                </tbody>
              </table>
            </div>
            )}
            {showSubscriptionPreview && (
            <div className="wfty_box wfty_subscription">
              <div className="wfty_title">{subscriptionHeading}</div>
              <table className="shop_table shop_table_responsive my_account_orders" style={{ width: '100%' }}>
                <thead>
                  <tr>
                    <th className="order-number wfty_left"><span className="nobr">Subscription</span></th>
                    <th className="order-status wfty_center"><span className="nobr">Next Payment</span></th>
                    <th className="order-total wfty_center"><span className="nobr">Total</span></th>
                    <th className="order-total wfty_center"><span className="nobr"></span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="order">
                    <td data-title="Subscription" className="subscription-id order-number wfty_left">
                      <a href="javascript:void(0);"><strong>#1234</strong></a>
                      <small>(Active)</small>
                    </td>
                    <td data-title="Next Payment" className="subscription-next-payment order-date wfty_center">In 30 days</td>
                    <td data-title="Total" className="subscription-total order-total wfty_center">
                      <span className="woocommerce-Price-amount amount"><span className="woocommerce-Price-currencySymbol">$</span>7.50</span> / month
                    </td>
                    <td data-title="Action" className="subscription-actions order-actions wfty_center">
                      <a href="javascript:void(0);" className="button view">View</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            )}
          </div>
        </div>
      </div>
    </ModuleContainer>
  );
};
