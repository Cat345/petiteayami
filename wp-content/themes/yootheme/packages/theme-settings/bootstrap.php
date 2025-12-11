<?php

namespace YOOtheme\Theme;

return [
    'routes' => [
        ['get', '/systemcheck', [SystemCheckController::class, 'index']],
        ['get', '/cache', [CacheController::class, 'index']],
        ['post', '/cache/clear', [CacheController::class, 'clear']],
        ['post', '/import', [SettingsController::class, 'import']],
    ],

    'events' => [
        'theme.head' => [
            Listener\SetFavicons::class => '@handle',
            Listener\SetBodyClass::class => '@handle',
        ],

        'customizer.init' => [
            Listener\ShowNewsModal::class => '@handle',
            Listener\AvifImageSupport::class => '@handle',
        ],
    ],
];
