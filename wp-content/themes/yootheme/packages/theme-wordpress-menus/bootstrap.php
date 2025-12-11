<?php

namespace YOOtheme\Theme\Wordpress;

return [
    'events' => [
        'theme.init' => [Listener\LoadMenuLocations::class => '@handle'],
        'config.save' => [Listener\SaveMenuLocations::class => '@handle'],
        'customizer.init' => [Listener\LoadMenuData::class => '@handle'],
    ],

    'actions' => [
        'init' => [Listener\AddMenus::class => '@handle'],
    ],

    'filters' => [
        'theme_mod_nav_menu_locations' => [Listener\FilterMenuLocations::class => '@handle'],
        'widget_nav_menu_args' => [Listener\FilterWidgetMenuArgs::class => ['handle', 10, 4]],
        'wp_nav_menu_args' => [Listener\FilterMenuArgs::class => 'handle'],
        'wp_nav_menu_objects' => [Listener\FilterMenuItems::class => ['handle', 10, 2]],
    ],

    'services' => [
        Listener\FilterMenuLocations::class => '',
    ],
];
