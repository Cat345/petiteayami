// Divi dependencies.
import { ModuleEditProps } from '@divi/module-library';
import {
  FormatBreakpointStateAttr,
  InternalAttrs,
  type Element,
  type Module,
} from '@divi/types';

export interface OptinFormPopupCssAttr extends Module.Css.AttributeValue {
  btn_text?: string;
  btn_subheading_text?: string;
  popup_heading?: string;
  popup_sub_heading?: string;
  button_text?: string;
  subtitle?: string;
  button_submitting_text?: string;
  popup_footer_text?: string;
  popup_bar_text?: string;
}

export type OptinFormPopupCssGroupAttr = FormatBreakpointStateAttr<OptinFormPopupCssAttr>;

export interface OptinFormPopupAttrs extends InternalAttrs {
  // CSS options is used across multiple elements inside the module thus it deserves its own top property.
  css?: OptinFormPopupCssGroupAttr;

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

  // Content fields
  btn_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  btn_subheading_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  btn_icon_field?: {
    desktop?: {
      value?: string | {
        unicode?: string;
        type?: string;
        weight?: string;
      };
    };
  };
  btn_icon_field_position?: {
    desktop?: {
      value?: 'left' | 'right';
    };
  };
  enable_popup_bar_on_builder?: {
    desktop?: {
      value?: string;
    };
  };
  popup_open_animation?: string;
  popup_bar_pp?: {
    desktop?: {
      value?: string;
    };
  };
  popup_bar_text_position?: {
    desktop?: {
      value?: string;
    };
  };
  popup_bar_animation?: {
    desktop?: {
      value?: string;
    };
  };
  popup_bar_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  popup_heading?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  popup_sub_heading?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  show_labels?: {
    desktop?: {
      value?: string;
    };
  };
  button_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  subtitle?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  button_submitting_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  popup_footer_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  input_size?: string;
  wfop_optin_form_popup_btn_text_spacing?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };

  // Style attributes (simplified - add more as needed)
  [key: string]: any;
}

export type OptinFormPopupEditProps = ModuleEditProps<OptinFormPopupAttrs>;
