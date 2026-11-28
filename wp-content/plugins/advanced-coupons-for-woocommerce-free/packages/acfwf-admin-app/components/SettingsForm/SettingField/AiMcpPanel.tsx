// #region [Imports] ===================================================================================================

// Libraries
import { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Switch, Button, message } from 'antd';
import { LockOutlined, RightCircleFilled } from '@ant-design/icons';
// @ts-ignore
import { CopyToClipboard } from 'react-copy-to-clipboard';

// Components
import PluginInstallerButton from '../../PluginInstallerButton';

// Actions
import { SectionActions } from '../../../store/actions/section';

// Types
import { IStore } from '../../../types/store';

// Helpers
import axiosInstance from '../../../helpers/axios';

// SCSS
import './AiMcpPanel.scss';

// #endregion [Imports]

// #region [Interfaces] ================================================================================================

interface ICta {
  action: 'install' | 'activate' | 'link' | 'none';
  label: string;
  note: string;
  url?: string;
}

interface IRecipe {
  title: string;
  prompt: string;
  steps: string[];
  needs: string;
}

interface IPanelData {
  cta: ICta;
  wporg_url: string;
  plugin_slug: string;
  install_nonce: string;
  write_access: { available: boolean; note: string; enabled: boolean; allowed: number; total: number };
  endpoint: { available: boolean; url: string; help: string };
  clients: { name: string; image: string; dark: boolean }[];
  recipes: IRecipe[];
  abilities_url: string;
  labels: { [key: string]: string };
}

interface IProps {
  data: IPanelData;
}

// #endregion [Interfaces]

// #region [Component] =================================================================================================

const AiMcpPanel = (props: IProps) => {
  const {
    cta,
    wporg_url,
    plugin_slug,
    install_nonce,
    write_access,
    endpoint,
    clients,
    recipes,
    abilities_url,
    labels,
  } = props.data;
  const [writeEnabled, setWriteEnabled] = useState<boolean>(write_access.enabled);
  const [allowedCount, setAllowedCount] = useState<number>(write_access.allowed);
  const [writeLoading, setWriteLoading] = useState<boolean>(false);
  const dispatch = useDispatch();
  const sections = useSelector((store: IStore) => store.sections);

  // The intro wraps the StoreAgent name in <link></link>. If a translation breaks the tags, show plain text.
  const introParts = labels.intro.match(/^([\s\S]*)<link>([\s\S]*?)<\/link>([\s\S]*)$/);
  const isExternalCta = !!cta.url && cta.url.startsWith('https://wordpress.org');
  // Show how many writes are allowed when only some are (e.g. set in StoreAgent directly).
  const writeCount =
    write_access.available && !writeEnabled && allowedCount > 0 ? `${allowedCount} / ${write_access.total}` : '';

  const handleWriteToggle = (checked: boolean) => {
    setWriteLoading(true);
    axiosInstance
      .post('coupons/v1/ai-mcp/write-access', { enabled: checked })
      .then((response: any) => {
        const { status, enabled, allowed, message: text } = response.data;
        setWriteEnabled(enabled);
        setAllowedCount(allowed);
        // Update the cached section, so the panel shows the saved state when the user comes back to it.
        const section = sections.find(({ id }) => 'ai_mcp_section' === id);
        if (section) {
          const fields = section.fields.map((field) =>
            'acfw_ai_mcp' === field.type
              ? { ...field, data: { ...field.data, write_access: { ...field.data.write_access, enabled, allowed } } }
              : field,
          );
          dispatch(SectionActions.setStoreSectionItem({ data: { ...section, fields } }));
        }
        'warning' === status ? message.warning(text) : message.success(text);
      })
      .catch((e: any) => message.error(e?.response?.data?.message || labels.write_error))
      .finally(() => setWriteLoading(false));
  };

  return (
    <div className="acfw-ai-mcp">
      <section className="acfw-ai-mcp-hero">
        <div className="acfw-ai-mcp-hero-main">
          <div className="acfw-ai-mcp-eyebrow">{labels.eyebrow}</div>
          <h3 className="acfw-ai-mcp-heading">{labels.heading}</h3>
          <p className="acfw-ai-mcp-intro">
            {introParts ? (
              <>
                {introParts[1]}
                <a href={wporg_url} target="_blank" rel="noopener noreferrer">
                  {introParts[2]}
                </a>
                {introParts[3]}
              </>
            ) : (
              labels.intro.replace(/<\/?link>/g, '')
            )}
          </p>
          <div className="acfw-ai-mcp-actions">
            {'install' === cta.action || 'activate' === cta.action ? (
              <PluginInstallerButton
                pluginSlug={plugin_slug}
                className="acfw-ai-mcp-cta"
                type="default"
                size="large"
                text={cta.label}
                nonce={install_nonce}
                successMessage={labels.install_done}
                isActivation={'activate' === cta.action}
              />
            ) : null}
            {'link' === cta.action ? (
              <a
                className="acfw-ai-mcp-cta"
                href={cta.url}
                target={isExternalCta ? '_blank' : undefined}
                rel={isExternalCta ? 'noopener noreferrer' : undefined}
              >
                {cta.label}
                <RightCircleFilled aria-hidden />
              </a>
            ) : null}
            <label className={`acfw-ai-mcp-write${write_access.available ? '' : ' is-disabled'}`}>
              <Switch
                checked={writeEnabled}
                disabled={!write_access.available}
                loading={writeLoading}
                onChange={handleWriteToggle}
              />
              <span className="acfw-ai-mcp-write-label">{labels.write_toggle}</span>
              {writeCount ? <span className="acfw-ai-mcp-write-count">{writeCount}</span> : null}
            </label>
          </div>
          {cta.note ? <p className="acfw-ai-mcp-note">{cta.note}</p> : null}
          {write_access.note ? <p className="acfw-ai-mcp-note">{write_access.note}</p> : null}
        </div>
        <div className="acfw-ai-mcp-clients">
          {clients.map((client) => (
            <div className="acfw-ai-mcp-client" key={client.name}>
              <span className={`acfw-ai-mcp-client-icon${client.dark ? ' is-dark' : ''}`}>
                <img src={client.image} alt="" />
              </span>
              <span className="acfw-ai-mcp-client-name">{client.name}</span>
            </div>
          ))}
          <div className="acfw-ai-mcp-client-more">{labels.any_client}</div>
        </div>
      </section>

      <section className="acfw-ai-mcp-recipes">
        <div className="acfw-ai-mcp-recipes-head">
          <h4>{labels.recipes_title}</h4>
          <a href={abilities_url} target="_blank" rel="noopener noreferrer">
            {labels.abilities_docs} →
          </a>
        </div>
        <p className="acfw-ai-mcp-recipes-desc">{labels.recipes_desc}</p>
        <div className="acfw-ai-mcp-recipe-grid">
          {recipes.map((recipe) => (
            <div className="acfw-ai-mcp-recipe" key={recipe.title}>
              <div className="acfw-ai-mcp-recipe-head">
                <span className="acfw-ai-mcp-recipe-title">{recipe.title}</span>
                {recipe.needs ? (
                  <span className="acfw-ai-mcp-recipe-lock">
                    <LockOutlined aria-hidden />
                    {recipe.needs}
                  </span>
                ) : null}
              </div>
              <div className="acfw-ai-mcp-recipe-prompt">“{recipe.prompt}”</div>
              <div className="acfw-ai-mcp-recipe-steps">
                {recipe.steps.map((step) => (
                  <code key={step}>{step}</code>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="acfw-ai-mcp-endpoint">
        <div className="acfw-ai-mcp-endpoint-title">{labels.endpoint_title}</div>
        <div className="acfw-ai-mcp-endpoint-main">
          <div className="acfw-ai-mcp-endpoint-row">
            <code className={endpoint.available ? '' : 'is-disabled'}>{endpoint.url}</code>
            <CopyToClipboard text={endpoint.url} onCopy={() => message.success(labels.copied)}>
              <Button disabled={!endpoint.available}>{labels.copy}</Button>
            </CopyToClipboard>
          </div>
          <div className="acfw-ai-mcp-endpoint-help">{endpoint.help}</div>
        </div>
      </section>
    </div>
  );
};

export default AiMcpPanel;

// #endregion [Component]
