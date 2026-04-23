import type { Element } from '@divi/types';

export interface CustomerDetailsAttrs {
  module?: Element.Decoration.Attributes;
  heading?: {
    innerContent?: {
      desktop?: { value?: string };
    };
  };
  customer_layout?: {
    innerContent?: {
      desktop?: { value?: string };
    };
  };
  enable_extra_content?: {
    innerContent?: {
      desktop?: { value?: boolean };
    };
  };
  wfty_customer_details_heading_typography?: Element.Decoration.Attributes;
  wfty_customer_details_det_heading_typography?: Element.Decoration.Attributes;
}

export interface CustomerDetailsEditProps {
  attrs: CustomerDetailsAttrs;
  elements: any;
  id: string;
  name: string;
  /** Live editing state from VB – merged with attrs for live typography preview */
  state?: CustomerDetailsAttrs;
}
