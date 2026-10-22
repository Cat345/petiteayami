<?php

namespace YOOtheme\Theme\Wordpress\Polylang;

return [
    'events' => [
        'url.resolve' => [Listener\AddLanguageParameter::class => 'handle'],
    ],

    'filters' => [
        'wp_nav_menu_objects' => [Listener\FilterMenuItems::class => ['handle', 10, 2]],
    ],
];
