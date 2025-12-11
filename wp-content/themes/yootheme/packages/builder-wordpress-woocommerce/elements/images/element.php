<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node) {
            if (!is_product()) {
                return false;
            }

            $config = app(Config::class);

            // Force reload, because WooCommerce depends on jQuery.ready()
            // triggered through 'GALLERY THUMBNAIL COLUMNS' setting in config
            if ($config('app.isCustomizer')) {
                $node->attrs['data-preview'] = 'reload';
            }
        },
    ],
];
