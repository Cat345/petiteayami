<?php

namespace YOOtheme\Theme\Wordpress\WPML;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Theme\Wordpress\MenuConfig;

return [
    'events' => [
        MenuConfig::class => [Listener\LoadMenuConfig::class => 'handle'],
        'customizer.init' => [Listener\LoadBuilderConfig::class => ['addFilters', 10]],
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => ['handle', 10]],
        'url.resolve' => [Listener\AddLanguageParameter::class => 'handle'],
    ],
];
