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

    public static function load(Config $config): array
    {
        $images = "~yootheme/theme-{$config->get('app.platform')}/assets/images";
        $icons = [
            'favicon' => Url::to($config('~theme.favicon') ?: "{$images}/favicon.png"),
            'touchicon' => Url::to($config('~theme.touchicon') ?: "{$images}/apple-touch-icon.png"),
        ];

        if ($config('~theme.favicon_svg')) {
            $icons['favicon_svg'] = Url::to($config('~theme.favicon_svg'));
        }

        return $icons;
    }
}
