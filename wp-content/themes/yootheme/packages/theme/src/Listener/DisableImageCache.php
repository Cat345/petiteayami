<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;

class DisableImageCache
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle($request, callable $next)
    {
        // Prevent image caching in customizer mode
        return $next($request->withAttribute('save', !$this->config->get('app.isCustomizer')));
    }
}
