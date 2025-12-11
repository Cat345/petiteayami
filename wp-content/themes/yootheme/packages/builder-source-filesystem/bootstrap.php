<?php

namespace YOOtheme\Builder\Source\Filesystem;

use YOOtheme\Config;

return [
    'events' => [
        // -5 to show the 'External' Group after the 'Custom' Group
        'source.init' => [Listener\LoadSourceTypes::class => ['@handle', -5]],
    ],

    'services' => [
        FileHelper::class => function (Config $config) {
            return new FileHelper($config('app.uploadDir'));
        },
    ],
];
