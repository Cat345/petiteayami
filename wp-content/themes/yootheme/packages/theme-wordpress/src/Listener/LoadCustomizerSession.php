<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Arr;
use YOOtheme\Config;
use YOOtheme\Http\Request;

class LoadCustomizerSession
{
    public Config $config;
    public Request $request;

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
        $name = 'yootheme_customizer_' . md5(wp_get_session_token());
        $cookie = hash_hmac('md5', $name, $this->config->get('app.secret'));

        // If not customizer route
        if ($this->request->getQueryParam('p') !== 'customizer') {
            // Is frontend request and has customizer cookie
            if (!$this->request->getCookieParam($cookie) || is_admin()) {
                return $config;
            }

            // Hide admin bar
            show_admin_bar(false);

            // Allow `auto-draft` posts to be previewed
            if ($status = get_post_status_object('auto-draft')) {
                $status->protected = true;
            }

            // Get params from transient
            $params = get_transient($name) ?: [];

            // Get customizer config from request
            if ($custom = $this->request->getParam('customizer')) {
                $params = json_decode(base64_decode($custom), true) + $params;
                $values = Arr::pick($params, ['config']);

                set_transient($name, $values, DAY_IN_SECONDS);
            }

            // Override theme config
            if (isset($params['config'])) {
                $config = json_encode($params['config'], JSON_UNESCAPED_SLASHES);
            }

            // Pass through e.g. page, widget and template params
            $this->config->add('req.customizer', $params);
        }

        $this->config->set('app.isCustomizer', true);
        $this->config->set('theme.cookie', $cookie);
        $this->config->set('customizer.id', $this->config->get('theme.id'));

        return $config;
    }
}
