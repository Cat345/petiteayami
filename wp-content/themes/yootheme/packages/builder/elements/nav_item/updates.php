<?php

namespace YOOtheme;

return [
    '3.0.10.1' => function ($node) {
        if (Arr::get($node->props, 'type') === 'header') {
            $node->props['type'] = 'heading';
        }
    },
];
