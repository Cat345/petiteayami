<?php

namespace YOOtheme\Theme\Cookie;

use YOOtheme\Config;

return [
    'theme' => function (Config $config) {
        return $config->loadFile(__DIR__ . '/config/theme.json');
    },

    'events' => ['theme.head' => [Listener\LoadThemeHead::class => '@handle']],
];
