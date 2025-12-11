<?php

namespace YOOtheme\Theme;

use YOOtheme\Config;
use YOOtheme\ImageProvider;
use YOOtheme\View;

return [
    'theme' => fn(Config $config) => $config->loadFile(__DIR__ . '/config/theme.json'),

    'events' => [
        'app.request' => [Listener\DisableImageCache::class => '@handle'],
        'metadata.load' => [Listener\LoadThemeVersion::class => ['@handle', -10]],

        'theme.head' => [
            Listener\LoadThemeHead::class => ['@handle', -10],
        ],

        'customizer.init' => [
            Listener\UpdateBuilderLayouts::class => '@handle',
            Listener\LoadCustomizerData::class => '@handle',
            Listener\LoadConfigData::class => ['@handle', -20],
            Listener\LoadUIkitScript::class => ['@handle', 40],
        ],

        'config.save' => [
            Listener\SaveBuilderLayouts::class => '@handle',
        ],
    ],

    'extend' => [
        View::class => function (View $view, $app) {
            $app(ViewHelper::class)->register($view);
        },

        ImageProvider::class => function (ImageProvider $image, $app) {
            $image->addLoader($app(ImageLoader::class));
        },
    ],

    'services' => [
        Updater::class => function (Config $config) {
            $updater = new Updater($config('theme.version'));
            $updater->add(__DIR__ . '/updates.php');

            return $updater;
        },

        Listener\LoadThemeVersion::class => '',
    ],
];
