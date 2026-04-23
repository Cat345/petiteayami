// Divi dependencies.
import { ModuleEditProps } from '@divi/module-library';
import {
  FormatBreakpointStateAttr,
  InternalAttrs,
  type Element,
  type Module,
} from '@divi/types';

export interface OptinFormCssAttr extends Module.Css.AttributeValue {
  button_text?: string;
  subtitle?: string;
  button_submitting_text?: string;
  show_labels?: boolean;
}

export type OptinFormCssGroupAttr = FormatBreakpointStateAttr<OptinFormCssAttr>;

export interface OptinFormAttrs extends InternalAttrs {
  // CSS options is used across multiple elements inside the module thus it deserves its own top property.
  css?: OptinFormCssGroupAttr;

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
  button_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  subtitle?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  button_submitting_text?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  show_labels?: {
    innerContent?: Element.Types.Text.InnerContent.Attributes;
  };
  input_size?: string;
  wfop_optin_form_label_typography?: Element.Decoration.Attributes;
  wfop_optin_form_field_typography?: Element.Decoration.Attributes;
  wfop_optin_form_button_text_typo?: Element.Decoration.Attributes;
  wfop_optin_form_button_subheading_text_typo?: Element.Decoration.Attributes;
  wfop_optin_form_mark_required_color?: Element.Decoration.Attributes;
  wfop_optin_form_field_background_color?: Element.Decoration.Background.Attributes;
  wfop_optin_form_field_border?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };
  wfop_optin_form_column_gap_padding?: Element.Decoration.Attributes;
  wfop_optin_form_row_gap_margin?: Element.Decoration.Attributes;
  wfop_optin_form_submit_button_text?: {
    advanced?: {
      text?: Element.Advanced.Text.Attributes;
    };
  };
  wfop_optin_form_button_width?: Element.Decoration.Attributes;
  wfop_optin_form_button_bg_color?: Element.Decoration.Background.Attributes;
  wfop_optin_form_button_hover_color?: Element.Decoration.Attributes;
  wfop_optin_form_button_hover_bg_color?: Element.Decoration.Background.Attributes;
  bwf_button_border?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };
  wfop_optin_form_button_text_padding?: Element.Decoration.Attributes;
  button_text_alignment_box_shadow?: Element.Decoration.Attributes;
}

export type OptinFormEditProps = ModuleEditProps<OptinFormAttrs>;
