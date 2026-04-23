// Divi dependencies.
import { ModuleEditProps } from '@divi/module-library';
import {
  FormatBreakpointStateAttr,
  InternalAttrs,
  type Element,
  type Module,
} from '@divi/types';

export interface CheckoutFormCssAttr extends Module.Css.AttributeValue {
  // Add custom CSS attributes if needed
}

export type CheckoutFormCssGroupAttr = FormatBreakpointStateAttr<CheckoutFormCssAttr>;

export interface CheckoutFormAttrs extends InternalAttrs {
  // CSS options is used across multiple elements inside the module thus it deserves its own top property.
  css?: CheckoutFormCssGroupAttr;

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

  // Phase 2: Collapsible Order Summary settings
  enable_callapse_order_summary?: {
    desktop?: { value: string };
    tablet?: { value: string };
    mobile?: { value: string };
  };
  order_summary_enable_product_image_collapsed?: {
    desktop?: { value: string };
  };
  enable_order_field_collapsed?: {
    desktop?: { value: string };
    tablet?: { value: string };
    mobile?: { value: string };
  };
  cart_collapse_title?: {
    desktop?: { value: string };
  };
  cart_expanded_title?: {
    desktop?: { value: string };
  };
  collapse_enable_coupon?: {
    desktop?: { value: string };
  };
  collapse_enable_coupon_collapsible?: {
    desktop?: { value: string };
  };
  collapse_coupon_button_text?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  collapse_order_quantity_switcher?: {
    desktop?: { value: string };
  };
  collapse_order_delete_item?: {
    desktop?: { value: string };
  };
  // Order Summary Content settings
  order_summary_enable_product_image?: {
    desktop?: { value: string };
  };
  // Payment Gateway Content settings
  wfacp_payment_method_heading_text?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  wfacp_payment_method_subheading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  form_coupon_button_text?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  enable_icon_with_place_order_1?: {
    desktop?: { value: string };
  };
  enable_icon_with_place_order_2?: {
    desktop?: { value: string };
  };
  enable_icon_with_place_order_place_order?: {
    desktop?: { value: string };
  };
  icons_with_place_order_list_1?: {
    desktop?: { value: string };
  };
  icons_with_place_order_list_2?: {
    desktop?: { value: string };
  };
  icons_with_place_order_list_place_order?: {
    desktop?: { value: string };
  };
  order_coupon_coupon_typography?: Element.Decoration.Attributes;
  order_coupon_label_typo?: Element.Decoration.Attributes;
  order_coupon_input_typo?: Element.Decoration.Attributes;
  order_coupon_button_typo?: Element.Decoration.Attributes;
  order_coupon_focus_color?: Element.Decoration.Attributes;
  order_coupon_btn_bg_color?: Element.Decoration.Attributes;
  order_coupon_btn_text_color?: Element.Decoration.Attributes;
  order_coupon_btn_bg_hover_color?: Element.Decoration.Attributes;
  order_coupon_btn_bg_hover_text_color?: Element.Decoration.Attributes;
  order_coupon_coupon_border?: Element.Decoration.Attributes;
  collapsible_order_summary_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  expanded_order_summary_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  active_step_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  active_step_count_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  inactive_step_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  active_step_text_color?: Element.Decoration.Attributes;
  active_step_count_text_color?: Element.Decoration.Attributes;
  active_step_count_border_color?: {
    decoration?: {
      border?: {
        border?: {
          desktop?: {
            value?: {
              styles?: {
                all?: {
                  color?: string;
                };
              };
            };
          };
        };
      };
    };
  };
  inactive_step_text_color?: Element.Decoration.Attributes;
  inactive_step_count_text_color?: Element.Decoration.Attributes;
  inactive_step_count_border_color?: {
    decoration?: {
      border?: {
        border?: {
          desktop?: {
            value?: {
              styles?: {
                all?: {
                  width?: string;
                  color?: string;
                  desktop?: {
                    value?: {
                      color?: string;
                    };
                  };
                };
              };
            };
          };
        };
      };
    };
  };
  active_tab_border_bottom_color?: {
    decoration?: {
      border?: {
        border?: {
          desktop?: {
            value?: {
              styles?: {
                all?: {
                  width?: string;
                  color?: string;
                  desktop?: {
                    value?: {
                      color?: string;
                    };
                  };
                };
              };
            };
          };
        };
      };
    };
  };
  inactive_tab_border_bottom_color?: {
    decoration?: {
      border?: {
        border?: {
          desktop?: {
            value?: {
              styles?: {
                all?: {
                  width?: string;
                  color?: string;
                  desktop?: {
                    value?: {
                      color?: string;
                    };
                  };
                };
              };
            };
          };
        };
      };
    };
  };
  inactive_step_count_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  expanded_order_summary_link_color?: Element.Decoration.Attributes;
  wfacp_collapsible_border?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };
  wfacp_collapsible_margin?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };
  wfacp_tab_margin?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };
  tab_heading_typography?: Element.Decoration.Attributes;
  tab_subheading_typography?: Element.Decoration.Attributes;
  breadcrumb_heading_typography?: Element.Decoration.Attributes;
  breadcrumb_text_color?: Element.Decoration.Attributes;
  breadcrumb_text_hover_color?: Element.Decoration.Attributes;

  // Order Summary Typography settings
  order_summary_cart_item_typo?: Element.Decoration.Attributes;
  order_summary_product_meta_typo?: Element.Decoration.Attributes;
  order_summary_cart_total_label_typo?: Element.Decoration.Attributes;
  order_summary_cart_subtotal_heading_typo?: Element.Decoration.Attributes;

  // Payment Method Typography settings
  wfacp_form_payment_method_typo?: Element.Decoration.Attributes;
  wfacp_form_payment_method_description_color?: Element.Decoration.Attributes;
  wfacp_form_payment_method_description_bg_color?: Element.Decoration.Attributes;

  // Privacy Policy Typography settings
  wfacp_privacy_policy_font?: Element.Decoration.Attributes;

  // Terms & Conditions Typography settings
  wfacp_terms_conditions_font?: Element.Decoration.Attributes;

  // Order Summary Border settings
  order_summary_image_border?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };
  order_summary_divider_line_color?: {
    decoration?: {
      border?: {
        border?: {
          desktop?: {
            value?: {
              styles?: {
                all?: {
                  width?: string;
                  color?: string;
                  desktop?: {
                    value?: {
                      color?: string;
                    };
                  };
                };
              };
            };
          };
        };
      };
    };
  };

  // Section settings
  section_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  section_text_color?: Element.Decoration.Attributes;
  section_spacing?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };
  section_box_shadow?: {
    decoration?: {
      boxShadow?: Element.Decoration.BoxShadow.Attributes;
    };
  };
  section_border?: {
    decoration?: {
      border?: Element.Decoration.Border.Attributes;
    };
  };

  // Phase 3: Steps settings
  enable_progress_bar?: {
    desktop?: { value: string };
  };
  select_type?: {
    desktop?: { value: string };
  };
  step_0_bredcrumb?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_0_progress_bar?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_0_heading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_0_subheading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_1_bredcrumb?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_1_progress_bar?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_1_heading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_1_subheading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_2_bredcrumb?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_2_progress_bar?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_2_heading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_2_subheading?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_cart_link_enable?: {
    desktop?: { value: string };
  };
  step_cart_progress_bar_link?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };
  step_cart_bredcrumb_link?: {
    innerContent?: {
      desktop?: { value: string };
    };
  };

  // Dynamic section field classes (select dropdown values)
  dynamic_field_classes?: {
    [sectionKey: string]: {
      [fieldKey: string]: {
        desktop?: {
          value: string;
        };
      };
    };
  };

  // Dynamic section custom field classes (text field values)
  dynamic_field_classes_custom?: {
    [sectionKey: string]: {
      [fieldKey: string]: {
        innerContent?: {
          desktop?: {
            value: string;
          };
        };
      };
    };
  };

  // Product Switcher Typography settings
  selected_item_typography?: Element.Decoration.Attributes;
  selected_you_save_typo?: Element.Decoration.Attributes;
  product_switching_best_value_typography?: Element.Decoration.Attributes;
  product_switching_what_included_heading?: Element.Decoration.Attributes;
  product_switching_what_included_product_title?: Element.Decoration.Attributes;
  product_switching_what_included_product_description?: Element.Decoration.Attributes;
  product_switching_optional_item_typography?: Element.Decoration.Attributes;
  non_selected_you_save_typo?: Element.Decoration.Attributes;

  // Heading Typography settings
  section_heading_typo?: Element.Decoration.Attributes;
  section_sub_heading_typo?: Element.Decoration.Attributes;

  // Heading Design settings
  form_heading_bg_color?: Element.Decoration.Attributes;
  form_heading_spacing?: Element.Decoration.Attributes;
  form_heading_border?: Element.Decoration.Attributes;

  // Fields Typography
  wfacp_form_fields_label_typo?: Element.Decoration.Attributes;
  wfacp_form_fields_input_typo?: Element.Decoration.Attributes;

  // Fields Colors and Styles
  wfacp_form_fields_label_color?: Element.Decoration.Attributes;
  wfacp_form_fields_input_color?: Element.Decoration.Attributes;
  wfacp_form_fields_input_bg_color?: Element.Decoration.Attributes;
  wfacp_form_fields_border?: Element.Decoration.Attributes;
  wfacp_form_fields_focus_color?: Element.Decoration.Attributes;
  wfacp_form_fields_validation_color?: Element.Decoration.Attributes;

  // Product Switcher Color settings
  product_switching_label_color?: Element.Decoration.Attributes;
  product_switching_price_color?: Element.Decoration.Attributes;
  product_switching_variant_color?: Element.Decoration.Attributes;
  selected_you_save_color?: Element.Decoration.Attributes;
  product_switching_best_value_text_color?: Element.Decoration.Attributes;
  product_switching_optional_label_color?: Element.Decoration.Attributes;
  product_switching_optional_price_color?: Element.Decoration.Attributes;
  non_selected_you_save_color?: Element.Decoration.Attributes;

  // Product Switcher Background Color settings
  product_switching_item_background?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  product_switching_best_value_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  product_switching_what_included_bg?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  product_switching_optional_background?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  product_switching_optional_background_hover?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };

  // Product Switcher Border Color settings
  product_switching_best_value_border_color?: Element.Decoration.Attributes;

  // Product Switcher Border settings
  product_switching_item_border?: Element.Decoration.Attributes;
  product_switching_best_value_border?: Element.Decoration.Attributes;
  product_switching_what_included_border?: Element.Decoration.Attributes;
  product_switching_border_non_selected?: Element.Decoration.Attributes;

  // Checkout Form Typography
  wfacp_font_family_typography?: Element.Decoration.Attributes;

  // Checkout Button Typography
  wfacp_form_payment_button_typo?: Element.Decoration.Attributes;

  // Checkout Button Sub Text Typography
  checkout_button_sub_text_font_size?: Element.Decoration.Attributes;
  wfacp_button_bg_color?: Element.Decoration.Attributes;
  wfacp_button_label_color?: Element.Decoration.Attributes;
  wfacp_button_bg_hover_color?: Element.Decoration.Attributes;
  wfacp_button_label_hover_color?: Element.Decoration.Attributes;
  wfacp_button_border?: Element.Decoration.Attributes;
  wfacp_button_padding?: Element.Decoration.Attributes;
  wfacp_button_margin?: Element.Decoration.Attributes;
  step_back_link_color?: Element.Decoration.Attributes;
  step_back_link_hover_color?: Element.Decoration.Attributes;
  additional_text_color?: Element.Decoration.Attributes;
  additional_bg_color?: Element.Decoration.Attributes;
  checkout_button_icon_color?: Element.Decoration.Attributes;
  checkout_button_sub_text_color?: Element.Decoration.Attributes;

  // Checkout Form Background Color settings
  form_background_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  default_primary_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };

  // Checkout Form Color settings
  default_text_color?: Element.Decoration.Attributes;
  default_link_color?: Element.Decoration.Attributes;
  default_link_hover_color?: Element.Decoration.Attributes;

  // Checkout Form Spacing settings
  form_spacing?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };

  // Checkout Form Border settings
  form_border?: Element.Decoration.Attributes;

  // Heading Typography
  section_heading_typo?: Element.Decoration.Attributes;
  section_sub_heading_typo?: Element.Decoration.Attributes;

  // Heading Background Color settings
  form_heading_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };

  // Heading Spacing settings
  form_heading_spacing?: {
    decoration?: {
      spacing?: Element.Decoration.Spacing.Attributes;
    };
  };

  // Heading Border settings
  form_heading_border?: Element.Decoration.Attributes;

  // Fields Typography
  wfacp_form_fields_label_typo?: Element.Decoration.Attributes;
  wfacp_form_fields_input_typo?: Element.Decoration.Attributes;

  // Fields Colors and Styles
  wfacp_form_fields_input_bg_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  wfacp_form_fields_border?: Element.Decoration.Attributes;
  wfacp_form_fields_focus_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };
  wfacp_form_fields_validation_color?: {
    decoration?: {
      background?: Element.Decoration.Background.Attributes;
    };
  };

  // TODO: Add remaining checkout form specific attributes as we implement each phase
  // - Payment Gateway settings
  // - Checkout Button settings
  // - Coupon settings
}

export type CheckoutFormEditProps = ModuleEditProps<CheckoutFormAttrs>;
