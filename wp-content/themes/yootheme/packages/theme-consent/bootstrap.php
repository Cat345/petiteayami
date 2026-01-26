<?php

namespace YOOtheme\Theme\Consent;

use YOOtheme\Config;
use YOOtheme\Theme\ThemeConfig;
use YOOtheme\Theme\Updater;

return [
    'theme' => fn(Config $config) => $config->loadFile(__DIR__ . '/config/theme.php'),

    'events' => [
        'theme.init' => [
            Listener\LoadDefaultScripts::class => '@handle',
        ],

        'theme.head' => [
            Listener\LoadGoogleAnalyticsScript::class => ['@head', 10],
        ],

        'customizer.init' => [
            Listener\LoadGoogleAnalyticsScript::class => ['@handle', -5],
        ],

        ThemeConfig::class => [
            Listener\SkipDisabledScripts::class => ['config', 10],
            Listener\LoadGoogleAnalyticsScript::class => 'config',
        ],
    ],

    'extend' => [
        Updater::class => function (Updater $updater) {
            $updater->add(__DIR__ . '/updates.php');
        },
    ],

    'services' => [
        ConsentHelper::class => '',
    ],
];
