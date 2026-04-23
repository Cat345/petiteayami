import type { Element } from '@divi/types';

export interface OrderDetailsAttrs {
  module?: Element.Decoration.Attributes;
  order_details_heading?: { innerContent?: { desktop?: { value?: string } } };
  order_subscription_heading?: { innerContent?: { desktop?: { value?: string } } };
  order_download_heading?: { innerContent?: { desktop?: { value?: string } } };
  order_downloads_btn_text?: { innerContent?: { desktop?: { value?: string } } };
  order_details_img?: { innerContent?: { desktop?: { value?: boolean } } };
  order_downloads_file?: { innerContent?: { desktop?: { value?: boolean } } };
  order_downloads_file_expiry?: { innerContent?: { desktop?: { value?: boolean } } };
  wfty_order_details_heading_typography?: Element.Decoration.Attributes;
  wfty_order_details_product_typography?: Element.Decoration.Attributes;
  wfty_order_details_subtotal_typography?: Element.Decoration.Attributes;
  wfty_order_details_total_typography?: Element.Decoration.Attributes;
  wfty_order_details_variation_typography?: Element.Decoration.Attributes;
  wfty_order_details_divider_color?: Element.Decoration.Attributes;
  wfty_order_details_subscription_typography?: Element.Decoration.Attributes;
  wfty_order_details_download_typography?: Element.Decoration.Attributes;
  wfty_order_details_download_text_color?: Element.Decoration.Attributes;
}

export interface OrderDetailsEditProps {
  attrs: OrderDetailsAttrs;
  state?: OrderDetailsAttrs;
  elements: any;
  id: string;
  name: string;
}
