import React, { type ReactElement } from 'react';
import type { Module } from '@divi/types';
import { ModuleGroups } from '@divi/module';
import metadata from './module.json';

export const SettingsContent = ({
  groupConfiguration,
  panel,
}: Module.Settings.Panel.Props<any>): ReactElement => {
  let groups = groupConfiguration;
  if (!groups && (metadata as any)?.settings?.groups) {
    const allGroups = (metadata as any).settings.groups as Record<string, { panel?: string }>;
    groups = Object.fromEntries(
      Object.entries(allGroups).filter(([, g]) => !panel || g.panel === panel)
    );
  }
  if (!groups || typeof groups !== 'object') {
    return <></>;
  }
  return <ModuleGroups groups={groups} />;
};
