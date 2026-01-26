<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\Http\Request;
use YOOtheme\Wordpress\Router;

class LoadCustomizerSession
{
    protected Config $config;
    protected Request $request;

    protected string $cookie = 'yootheme_session';
    protected string $post = 'customizer_session';

    public function __construct(Config $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * Loads theme config from customizer session.
     *
     * @param string|false $config
     *
     * @return string|false
     */
    public function handle($config)
    {
        // If not customizer route
        if ($this->request->getQueryParam(Router::ROUTE_NAME) !== 'customizer') {
            if (is_admin()) {
                return $config;
            }

            // Get params from request or cookie
            $params = $this->handlePost() ?? $this->handleCookie();

            if (!$params) {
                return $config;
            }

            // Hide admin bar
            show_admin_bar(false);

            // Allow `auto-draft` posts to be previewed
            if ($status = get_post_status_object('auto-draft')) {
                $status->protected = true;
            }

            // Override theme config
            $config = $this->applyConfigChanges($config ?: '', $params['config'] ?? []);

            // Pass through e.g. page, widget and template params
            $this->config->add('req.customizer', $params);
        }

        $this->config->set('app.isCustomizer', true);
        $this->config->set('customizer.id', $this->config->get('theme.id'));
        $this->config->set('customizer.session', wp_create_nonce($this->cookie));

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    protected function handlePost(): ?array
    {
        $id = $this->parseSessionId($this->request->getParam($this->post) ?? '');
        $data = $this->request->getParam('customizer');

        if (!$id || !$data) {
            return null;
        }

        // Get params and merge with customizer data from request
        $params = array_replace(get_transient($id) ?: [], json_decode(base64_decode($data), true));

        set_transient($id, Arr::pick($params, ['config']), DAY_IN_SECONDS);

        return $params;
    }

    /**
     * Get params from transient.
     *
     * @return array<string, mixed>
     */
    protected function handleCookie(): ?array
    {
        $id = $this->parseSessionId($this->request->getCookieParam($this->cookie) ?? '');

        return $id ? (get_transient($id) ?: null) : null;
    }

    protected function parseSessionId(string $sessionId): ?string
    {
        return wp_verify_nonce($sessionId, $this->cookie) ? "{$this->cookie}_{$sessionId}" : null;
    }

    /**
     * @param array{type: string, path: list<string>, value?: mixed} $changes
     */
    protected function applyConfigChanges(string $config, array $changes): string
    {
        $config = json_decode($config, true) ?: [];

        foreach ($changes as $change) {
            $index = implode('.', $change['path']);
            if ($change['type'] === 'REMOVE') {
                Arr::del($config, $index);
            } else {
                Arr::set($config, $index, $change['value']);
            }
        }

        return json_encode($config, JSON_UNESCAPED_SLASHES);
    }
}
