<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\View\MetadataObject;
use function YOOtheme\app;

class LoadThemeVersion
{
    protected static Config $config;

    /**
     * @param MetadataObject $meta
     */
    public static function handle($meta): MetadataObject
    {
        static::$config ??= app(Config::class);
        $version = static::$config->get('theme.version');

        if ($version && is_null($meta->version)) {
            $meta = $meta->withAttribute('version', $version);
        }

        return $meta;
    }
}
