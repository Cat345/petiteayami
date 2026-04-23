import React, { ReactElement, useMemo } from 'react';
import { merge } from 'lodash';
import { ModuleContainer } from '@divi/module';
import type { CustomerDetailsEditProps } from './types';
import { ModuleStyles } from './styles';
import { moduleClassnames } from './module-classnames';
import { ModuleScriptData } from './module-script-data';
import defaultRenderAttributes from './module-default-render-attributes.json';

/** Same dummy data as frontend (WFTY_Data::$dummy_order_data) so backend and frontend preview match */
const DUMMY_EMAIL = 'john.doe@example.com';
const DUMMY_PHONE = '(999) 999-9999';
const DUMMY_ADDRESS = 'John Doe\n711-2880 Nulla St\nNew York, NY 10001\nUnited States (US)';

/**
 * VB preview: same structure and classes as frontend (wfty_customer_info)
 * so layout and any custom CSS match. Placeholder content matches frontend dummy data.
 */
export const CustomerDetailsEdit = (props: CustomerDetailsEditProps): ReactElement => {
  const { attrs, elements, id, name, state } = props;
  const mergedAttrs = useMemo(() => {
    if (!attrs || Object.keys(attrs).length === 0) {
      return defaultRenderAttributes as typeof attrs;
    }
    return merge({}, defaultRenderAttributes, attrs) as typeof attrs;
  }, [attrs]);
  // Merge state so typography (color, alignment, font size) updates live in VB before save
  const effectiveAttrs = useMemo(
    () => (state ? merge({}, mergedAttrs, state) : mergedAttrs),
    [mergedAttrs, state]
  );
  const heading = effectiveAttrs?.heading?.innerContent?.desktop?.value || 'Customer Details';
  const layout = effectiveAttrs?.customer_layout?.innerContent?.desktop?.value ?? '2c';
  // Match frontend: 2c => " 2c", else " wfty_full_width wfty_cont_style" (same as PHP layout_settings)
  const layoutClass = layout === '2c' ? ' 2c' : ' wfty_full_width wfty_cont_style';

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
      {/* Same nesting and tags as frontend: #wfty_customer_details > .wfty_wrap > .wfty_box.wfty_customer_info; heading must be div not h2 */}
      <div id="wfty_customer_details">
        <div className="wfty_wrap">
          <div className="wfty_box wfty_customer_info">
            <div className="wfty-customer-info-heading wfty_title">{heading}</div>
            <div className={`wfty_content wfty_clearfix wfty_text${layoutClass}`}>
            <div className="wfty_2_col_left">
              <div className="wfty_text_bold"><strong>Email</strong></div>
              <div className="wfty_view">{DUMMY_EMAIL}</div>
            </div>
            <div className="wfty_2_col_right">
              <div className="wfty_text_bold"><strong>Phone</strong></div>
              <div className="wfty_view">{DUMMY_PHONE}</div>
            </div>
            <div className="wfty_clear_15" />
            <div className="wfty_2_col_left">
              <div className="wfty_text">
                <div className="wfty_text_bold"><strong>Billing address</strong></div>
                <div className="wfty_view">{DUMMY_ADDRESS.split('\n').map((line, i) => <React.Fragment key={i}>{i > 0 && <br />}{line}</React.Fragment>)}</div>
              </div>
            </div>
            <div className="wfty_2_col_right">
              <div className="wfty_text">
                <div className="wfty_text_bold"><strong>Shipping address</strong></div>
                <div className="wfty_view">{DUMMY_ADDRESS.split('\n').map((line, i) => <React.Fragment key={i}>{i > 0 && <br />}{line}</React.Fragment>)}</div>
              </div>
            </div>
            <div className="wfty_clear" />
            </div>
          </div>
        </div>
      </div>
    </ModuleContainer>
  );
};
