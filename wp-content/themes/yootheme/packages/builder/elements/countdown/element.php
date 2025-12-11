<?php

namespace YOOtheme;

return [
    'placeholder' => [
        'props' => [
            'date' => date('Y-m-d', strtotime('+1 week')),
        ],
    ],

    'transforms' => [
        'render' => function ($node) {
            // Don't render element if content fields are empty
            return (bool) $node->props['date'];
        },
    ],
];
