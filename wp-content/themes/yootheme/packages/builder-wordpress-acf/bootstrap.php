<?php

namespace YOOtheme\Builder\Wordpress\Acf;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\UpdateTransform;

return [
    'events' => [
        'source.init' => [Listener\LoadSourceTypes::class => ['handle', -10]],
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => ['handle', -10]],
    ],

    'extend' => [
        UpdateTransform::class => function (UpdateTransform $update) {
            $update->addGlobals(require __DIR__ . '/updates.php');
        },
    ],
];
