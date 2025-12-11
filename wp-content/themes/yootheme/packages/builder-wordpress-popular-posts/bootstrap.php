<?php

namespace YOOtheme\Builder\Wordpress\PopularPosts;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\UpdateTransform;

return [
    'events' => [
        BuilderConfig::class => [Listener\LoadBuilderConfig::class => ['handle', -5]],
        'source.resolve.posts' => [Listener\ResolveSourcePosts::class => 'handle'],
    ],

    'extend' => [
        UpdateTransform::class => function (UpdateTransform $update) {
            $update->addGlobals(require __DIR__ . '/updates.php');
        },
    ],
];
