<?php

namespace YOOtheme\Theme\Styler;

use YOOtheme\Config;
use YOOtheme\ConfigObject;
use YOOtheme\File;
use YOOtheme\Path;
use YOOtheme\Url;

class StylerConfig extends ConfigObject
{
    /**
     * Constructor.
     */
    public function __construct(Config $config)
    {
        // check version in css file, if it needs to be updated
        $style = File::get("~theme/css/theme.{$config('theme.id')}.css");
        $header = $style ? file_get_contents($style, false, null, 0, 34) : '';
        $version = preg_match('/\sv([\w\d.\-]+)\s/', $header, $match) ? $match[1] : '1.0.0';

        parent::__construct([
            'update' => $version !== $config('theme.version'),
            'route' => 'theme/style',
            'worker' => Url::to(Path::get('../app/worker.min.js', __DIR__), [
                'ver' => $config('theme.version'),
            ]),
        ]);
    }
}
