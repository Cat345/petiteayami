<?php

namespace YOOtheme;

return [
    'theme' => fn() => [
        'name' => 'YOOtheme',

        'version' => '4.5.28',

        'rootDir' => __DIR__,

        'menus' => [
            'navbar' => 'Navbar',
            'header' => 'Header',
            'toolbar-left' => 'Toolbar Left',
            'toolbar-right' => 'Toolbar Right',
            'dialog' => 'Dialog',
            'navbar-mobile' => 'Mobile Navbar',
            'header-mobile' => 'Mobile Header',
            'dialog-mobile' => 'Mobile Dialog',
        ],

        'positions' => [
            'toolbar-left' => 'Toolbar Left',
            'toolbar-right' => 'Toolbar Right',
            'logo' => 'Logo',
            'navbar' => 'Navbar',
            'header' => 'Header',
            'dialog' => 'Dialog',
            'logo-mobile' => 'Mobile Logo',
            'navbar-mobile' => 'Mobile Navbar',
            'header-mobile' => 'Mobile Header',
            'dialog-mobile' => 'Mobile Dialog',
            'top' => 'Top',
            'sidebar' => 'Sidebar',
            'bottom' => 'Bottom',
            'builder-1' => 'Builder 1',
            'builder-2' => 'Builder 2',
            'builder-3' => 'Builder 3',
            'builder-4' => 'Builder 4',
            'builder-5' => 'Builder 5',
            'builder-6' => 'Builder 6',
        ],

        'styles' => [
            'imports' => [
                __DIR__ . '/vendor/assets/uikit/src/images/backgrounds/*.svg',
                __DIR__ . '/vendor/assets/uikit-themes/master/images/*.svg',
            ],
        ],
    ],

    'config' => [
        'image' => [
            'cacheDir' => __DIR__ . '/cache',
        ],
    ],
];
