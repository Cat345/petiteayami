<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Url;

class SetFavicons
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        foreach (static::load($this->config) as $icon => $url) {
            $this->config->set("~theme.{$icon}", $url);
        }
    }

    /**
     * @return array{favicon: string|false, touchicon: string|false, favicon_svg?: string|false}
     */
    public static function load(Config $config): array
    {
        $icons = [];
        foreach (['favicon', 'favicon_svg', 'touchicon'] as $icon) {
            $path = $config("~theme.{$icon}");
            $icons[$icon] = $path ? Url::to($path) : null;
        }
        return $icons;
    }
}
