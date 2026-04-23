// External Dependencies.
import React, { type ReactElement, useMemo } from 'react';
import { set, cloneDeep } from 'lodash';

// Divi Dependencies.
import {
  type Module,
} from '@divi/types';
import { ModuleGroups } from '@divi/module';

// Local Dependencies.
import { MiniCartAttrs } from './types';

/**
 * Content panel component for the Mini Cart module settings modal.
 *
 * @since 1.0.0
 *
 * @param {Module.Settings.Panel.Props} param0 Content panel props.
 *
 * @returns {ReactElement}
 */
export const SettingsContent = ({
  groupConfiguration,
  attrs,
}: Module.Settings.Panel.Props<MiniCartAttrs>): ReactElement => {
  // Ensure fields exist from module.json - match quantity selector pattern exactly
  const updatedGroupConfiguration = useMemo(() => {
    const config = cloneDeep(groupConfiguration);

    // Ensure contentHeading group exists
    if (!config.contentHeading) {
      config.contentHeading = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    if (!config.contentHeading.component) {
      config.contentHeading.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentHeading.component.props) {
      config.contentHeading.component.props = {
        fields: {},
      };
    }

    if (!config.contentHeading.component.props.fields) {
      config.contentHeading.component.props.fields = {};
    }

    const headingFields = config.contentHeading.component.props.fields;

    // Ensure miniCartHeading text field exists (static field from module.json)
    // Text fields with innerContent are auto-loaded from module.json, but ensure render is true
    if (headingFields.miniCartHeading) {
      headingFields.miniCartHeading.render = true;
    }

    // Ensure contentProducts group exists
    if (!config.contentProducts) {
      config.contentProducts = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    // Ensure fields object exists
    if (!config.contentProducts.component) {
      config.contentProducts.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentProducts.component.props) {
      config.contentProducts.component.props = {
        fields: {},
      };
    }

    if (!config.contentProducts.component.props.fields) {
      config.contentProducts.component.props.fields = {};
    }

    const productFields = config.contentProducts.component.props.fields;

    // Ensure enableProductImage toggle field exists (toggle field from module.json)
    // Match quantity selector pattern: only set if field doesn't exist
    if (!productFields.enableProductImage) {
      set(productFields, 'enableProductImage', {
        priority: 10,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'enableProductImage',
        label: 'Image',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      // Ensure render is true if field already exists
      productFields.enableProductImage.render = true;
    }

    // Ensure enableQuantityBox toggle field exists (toggle field from module.json)
    if (!productFields.enableQuantityBox) {
      set(productFields, 'enableQuantityBox', {
        priority: 20,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'enableQuantityBox',
        label: 'Quantity Switcher',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      // Ensure render is true if field already exists
      productFields.enableQuantityBox.render = true;
    }

    // Ensure enableDeleteItem toggle field exists (toggle field from module.json)
    if (!productFields.enableDeleteItem) {
      set(productFields, 'enableDeleteItem', {
        priority: 30,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'enableDeleteItem',
        label: 'Allow Deletion',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'on',
          },
        },
      });
    } else {
      // Ensure render is true if field already exists
      productFields.enableDeleteItem.render = true;
    }

    // Ensure miniCartEnableStrikeThroughPrice toggle field exists (toggle field from module.json)
    if (!productFields.miniCartEnableStrikeThroughPrice) {
      set(productFields, 'miniCartEnableStrikeThroughPrice', {
        priority: 40,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'miniCartEnableStrikeThroughPrice',
        label: 'Regular & Discounted Price',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      productFields.miniCartEnableStrikeThroughPrice.render = true;
    }

    // Ensure miniCartEnableLowStockTrigger toggle field exists (toggle field from module.json)
    if (!productFields.miniCartEnableLowStockTrigger) {
      set(productFields, 'miniCartEnableLowStockTrigger', {
        priority: 50,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'miniCartEnableLowStockTrigger',
        label: 'Low Stock Trigger',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      productFields.miniCartEnableLowStockTrigger.render = true;
    }

    // Only show low stock message when low stock trigger is enabled
    const lowStockEnabled = (attrs as any)?.miniCartEnableLowStockTrigger?.desktop?.value === 'on';
    if (productFields.miniCartLowStockMessage) {
      productFields.miniCartLowStockMessage.render = lowStockEnabled;
    }
    if (productFields.miniCartLowStockMessageInnercontent) {
      productFields.miniCartLowStockMessageInnercontent.render = lowStockEnabled;
    }

    // Ensure miniCartEnableSavingPriceMessage toggle field exists (toggle field from module.json)
    if (!productFields.miniCartEnableSavingPriceMessage) {
      set(productFields, 'miniCartEnableSavingPriceMessage', {
        priority: 70,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'miniCartEnableSavingPriceMessage',
        label: 'Total Saving Message',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      productFields.miniCartEnableSavingPriceMessage.render = true;
    }

    // Only show saving price message when saving price toggle is enabled
    const savingPriceEnabled = (attrs as any)?.miniCartEnableSavingPriceMessage?.desktop?.value === 'on';
    if (productFields.miniCartSavingPriceMessage) {
      productFields.miniCartSavingPriceMessage.render = savingPriceEnabled;
    }
    if (productFields.miniCartSavingPriceMessageInnercontent) {
      productFields.miniCartSavingPriceMessageInnercontent.render = savingPriceEnabled;
    }

    // Ensure contentCoupon group exists
    if (!config.contentCoupon) {
      config.contentCoupon = {
        component: {
          props: {
            fields: {},
          },
        },
      };
    }

    // Ensure fields object exists
    if (!config.contentCoupon.component) {
      config.contentCoupon.component = {
        props: {
          fields: {},
        },
      };
    }

    if (!config.contentCoupon.component.props) {
      config.contentCoupon.component.props = {
        fields: {},
      };
    }

    if (!config.contentCoupon.component.props.fields) {
      config.contentCoupon.component.props.fields = {};
    }

    const couponFields = config.contentCoupon.component.props.fields;

    // Ensure enableCoupon toggle field exists (toggle field from module.json)
    if (!couponFields.enableCoupon) {
      set(couponFields, 'enableCoupon', {
        priority: 10,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: true,
        attrName: 'enableCoupon',
        label: 'Enable',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      couponFields.enableCoupon.render = true;
    }

    // Only show coupon sub-fields when coupon is enabled
    const couponEnabled = (attrs as any)?.enableCoupon?.desktop?.value === 'on';

    // Ensure enableCouponCollapsible toggle field exists (toggle field from module.json)
    if (!couponFields.enableCouponCollapsible) {
      set(couponFields, 'enableCouponCollapsible', {
        priority: 20,
        component: {
          name: 'divi/toggle',
          type: 'field',
          props: {
            on: 'Yes',
            off: 'No',
          },
        },
        render: couponEnabled,
        attrName: 'enableCouponCollapsible',
        label: 'Collapsible',
        features: {
          sticky: false,
          dynamicContent: false,
          responsive: false,
        },
        defaultAttr: {
          desktop: {
            value: 'off',
          },
        },
      });
    } else {
      couponFields.enableCouponCollapsible.render = couponEnabled;
    }

    // Only show coupon button text when coupon is enabled
    if (couponFields.miniCartCouponButtonText) {
      couponFields.miniCartCouponButtonText.render = couponEnabled;
    }
    if (couponFields.miniCartCouponButtonTextInnercontent) {
      couponFields.miniCartCouponButtonTextInnercontent.render = couponEnabled;
    }

    return config;
  }, [groupConfiguration, attrs]);

  return <ModuleGroups groups={updatedGroupConfiguration} />;
};
