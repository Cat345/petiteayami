<?php

namespace YOOtheme\Theme\Styler;

use YOOtheme\Config;
use YOOtheme\Path;

return [
    'theme' => fn(Config $config) => $config->loadFile(__DIR__ . '/config/theme.json'),

    'config' => [
        StylerConfig::class => __DIR__ . '/config/styler.json',
    ],

    'routes' => [
        ['get', '/theme/styles', [StyleController::class, 'index']],
        ['get', '/theme/style', [StyleController::class, 'get']],
        ['post', '/theme/style', [StyleController::class, 'save']],
        ['get', '/styler/library', [LibraryController::class, 'index']],
        ['post', '/styler/library', [LibraryController::class, 'save']],
        ['delete', '/styler/library', [LibraryController::class, 'delete']],
    ],

    'events' => [
        'customizer.init' => [Listener\LoadStylerData::class => '@handle'],
        'styler.imports' => [Listener\LoadStylerImports::class => ['@handle', 10]],
    ],

    'services' => [
        StylerConfig::class => '',
        StyleFontLoader::class => [
            'arguments' => [
                '$cache' => fn() => Path::get('~theme/fonts'),
            ],
        ],
    ],
];
