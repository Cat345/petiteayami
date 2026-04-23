// Divi dependencies.
import { ModuleEditProps } from '@divi/module-library';
import {
  FormatBreakpointStateAttr,
  InternalAttrs,
  type Element,
  type Module,
} from '@divi/types';

export interface MiniCartCssAttr extends Module.Css.AttributeValue {
  // Add custom CSS attributes if needed
}

export type MiniCartCssGroupAttr = FormatBreakpointStateAttr<MiniCartCssAttr>;

export interface MiniCartAttrs extends InternalAttrs {
  // CSS options is used across multiple elements inside the module thus it deserves its own top property.
  css?: MiniCartCssGroupAttr;

  // Module
  module?: {
    meta?: Element.Meta.Attributes;
    advanced?: {
      htmlAttributes?: Element.Advanced.IdClasses.Attributes;
      text?: Element.Advanced.Text.Attributes;
    };
    decoration?: Element.Decoration.PickedAttributes<
      'animation' |
      'background' |
      'border' |
      'boxShadow' |
      'disabledOn' |
      'filters' |
      'overflow' |
      'position' |
      'scroll' |
      'sizing' |
      'spacing' |
      'sticky' |
      'transform' |
      'transition' |
      'zIndex'
    > & {
      // Custom Attributes are stored at module.decoration.attributes
      attributes?: any;
    };
  };

  // MiniCart Heading element
  miniCartHeading?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };

  // Content settings
  enableProductImage?: {
    desktop?: {
      value?: string;
    };
  };

  enableQuantityBox?: {
    desktop?: {
      value?: string;
    };
  };

  enableDeleteItem?: {
    desktop?: {
      value?: string;
    };
  };

  enableCoupon?: {
    desktop?: {
      value?: string;
    };
  };

  enableCouponCollapsible?: {
    desktop?: {
      value?: string;
    };
  };

  miniCartCouponButtonText?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };

  wfacp_mini_cart_font_family?: Element.Decoration.Attributes;

  miniCartProductMetaTypo?: Element.Decoration.Attributes;

  mini_cart_total_label_typo?: Element.Decoration.Attributes;
  mini_cart_total_typo?: Element.Decoration.Attributes;

  mini_cart_coupon_btn_hover_label_color?: Element.Decoration.Attributes;

  miniCartCouponCodeLabelTypo?: Element.Decoration.Attributes;
  miniCartCouponCodeValueTypo?: Element.Decoration.Attributes;

  mini_cart_divider_color?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };

  wfacp_form_mini_cart_coupon_focus_color_box_shadow?: {
    decoration?: {
      boxShadow?: Element.Decoration.BoxShadow.Attributes;
    };
  };

  wfacp_mini_cart_spacing?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };

  wfacp_mini_cart_border?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };

  wfacp_mini_cart_background?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
}

export type MiniCartEditProps = ModuleEditProps<MiniCartAttrs>;
