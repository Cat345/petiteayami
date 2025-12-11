<?php

namespace YOOtheme\Application;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Url;
use YOOtheme\View;

return [
    'extend' => [
        Config::class => function (Config $config) {
            $config->addFilter(
                'url',
                fn($value, $file) => Url::to(Path::resolve(dirname($file), $value)),
            );
        },

        View::class => function (View $view, $app) {
            $view->addGlobal('app', $app);
            $view->addGlobal('config', $app(Config::class));
        },
    ],

    'aliases' => [
        View::class => 'view',
    ],

    'loaders' => [
        'services' => ServiceLoader::class,
        'aliases' => AliasLoader::class,
        'extend' => ExtendLoader::class,
        'events' => EventLoader::class,
        'routes' => RouteLoader::class,
        'config' => ConfigLoader::class,
    ],
];
