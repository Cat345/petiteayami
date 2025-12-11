<?php

namespace YOOtheme\Theme\Wordpress\Listener;

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

    /**
     * @link https://developer.wordpress.org/reference/hooks/init/
     */
    public function handle()
    {
        if (!is_child_theme()) {
            return;
        }

        $childDir = strtr(realpath(get_stylesheet_directory()), '\\', '/');

        // add childDir to config
        $this->config->set('theme.childDir', $childDir);

        // add ~theme alias resolver
        Event::on(
            'path ~theme',
            fn($path, $file) => $file && File::find($childDir . $file) ? $childDir . $file : $path,
        );
    }
}
