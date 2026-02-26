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
        'customizer.init' => [
            Listener\ShowNewsModal::class => '@handle',
            Listener\AvifImageSupport::class => '@handle',
            Listener\ImageUrlSupport::class => '@handle',
        ],

        'systemcheck.extra' => [
            Listener\ImageUrlSupport::class => '@systemcheck',
        ],
    ],
];
