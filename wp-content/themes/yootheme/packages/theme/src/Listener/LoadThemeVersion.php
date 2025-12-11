<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;

class LoadThemeVersion
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle($meta)
    {
        $version = $this->config->get('theme.version');

        if ($version && is_null($meta->version)) {
            $meta = $meta->withAttribute('version', $version);
        }

        return $meta;
    }
}
