<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\File;

class LoadChildTheme
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle(): void
    {
        if (empty(($childDir = $this->config->get('theme.childDir')))) {
            return;
        }

        Event::on(
            'path ~theme',
            fn($path, $file) => $file && File::find($childDir . $file) ? $childDir . $file : $path,
        );
    }
}
