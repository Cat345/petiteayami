import React, { type ReactElement, useMemo } from 'react';
import { cloneDeep } from 'lodash';
import type { Module } from '@divi/types';
import { ModuleGroups } from '@divi/module';
import type { OrderDetailsAttrs } from './types';

export const SettingsContent = ({
  groupConfiguration,
  attrs,
}: Module.Settings.Panel.Props<OrderDetailsAttrs>): ReactElement => {
  if (!groupConfiguration) {
    return <></>;
  }

  const updatedConfig = useMemo(() => {
    const config = cloneDeep(groupConfiguration);

    const isTruthy = (v: unknown): boolean => v === true || v === 'on';

    // Subscription toggle value.
    const subEnabled = isTruthy(
      (attrs as any)?.order_subscription_preview?.innerContent?.desktop?.value
    );

    // Download toggle value.
    const dlEnabled = isTruthy(
      (attrs as any)?.order_download_preview?.innerContent?.desktop?.value
    );

    // Conditionally render subscription fields.
    const subFields = config?.contentSubscription?.component?.props?.fields;
    if (subFields) {
      if (subFields.order_subscription_headingInnercontent) {
        subFields.order_subscription_headingInnercontent.render = subEnabled;
      }
    }

    // Conditionally render download fields.
    const dlFields = config?.contentDownload?.component?.props?.fields;
    if (dlFields) {
      if (dlFields.order_download_headingInnercontent) {
        dlFields.order_download_headingInnercontent.render = dlEnabled;
      }
      if (dlFields.order_downloads_btn_textInnercontent) {
        dlFields.order_downloads_btn_textInnercontent.render = dlEnabled;
      }
      if (dlFields.order_downloads_fileInnercontent) {
        dlFields.order_downloads_fileInnercontent.render = dlEnabled;
      }
      if (dlFields.order_downloads_file_expiryInnercontent) {
        dlFields.order_downloads_file_expiryInnercontent.render = dlEnabled;
      }
    }

    return config;
  }, [groupConfiguration, attrs]);

  return <ModuleGroups groups={updatedConfig} />;
};
