<?php

namespace YOOtheme;

return [
    'transforms' => [
        'render' => function ($node, $params) {
            /** @var Config $config */
            $config = app(Config::class);

            // Set section transparent header
            if (is_null($config('header.section.transparent'))) {
                $config->set(
                    'header.section.transparent',
                    (bool) $node->props['header_transparent'],
                );
            }
        },
    ],
];
