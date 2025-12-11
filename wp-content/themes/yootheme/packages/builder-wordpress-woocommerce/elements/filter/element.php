<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node) {
            $config = app(Config::class);

            // Force reload, because WooCommerce depends on jQuery.ready()
            // specifically the price filter
            if ($config('app.isCustomizer')) {
                $node->attrs['data-preview'] = 'reload';
            }
        },
    ],
];
