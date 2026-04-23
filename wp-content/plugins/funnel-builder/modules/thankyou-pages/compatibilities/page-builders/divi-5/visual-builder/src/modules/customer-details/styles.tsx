import React, { ReactElement } from 'react';
import { StyleContainer, StylesProps } from '@divi/module';
import type { CustomerDetailsAttrs } from './types';

export const ModuleStyles = ({
  attrs,
  elements,
  mode,
  state,
  noStyleTag,
}: StylesProps<CustomerDetailsAttrs>): ReactElement => (
  <StyleContainer mode={mode} state={state} noStyleTag={noStyleTag}>
    {elements.style({ attrName: 'module' })}
    {elements.style({ attrName: 'wfty_customer_details_heading_typography' })}
    {elements.style({ attrName: 'wfty_customer_details_det_heading_typography' })}
    {elements.style({ attrName: 'wfty_customer_details_det_text_typography' })}
  </StyleContainer>
);
